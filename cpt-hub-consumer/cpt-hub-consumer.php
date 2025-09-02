<?php
/**
 * Plugin Name: CPT Hub Consumer
 * Description: Connects to a CPT Hub Publisher to fetch content and styles per CPT, with conditional caching and cron refresh.
 * Version:     0.1.0
 * Author:      Lime
 * License:     GPL-2.0-or-later
 */

if (!defined('ABSPATH')) exit;

final class CPT_Hub_Consumer
{
    const OPT_SETTINGS = 'cphub_consumer_settings';
    const OPT_CACHE_ITEMS = 'cphub_consumer_cache_items';
    const OPT_CACHE_ASSETS = 'cphub_consumer_cache_assets';
    const OPT_CACHE_GLOBAL = 'cphub_consumer_cache_global';
    const OPT_CRON_META = 'cphub_consumer_cron_meta';
    const CRON_HOOK = 'cphub_consumer_cron_refresh';
    private static $needs_styles = [];
    private static $did_register = false;
    private $cphub_el_rendering = false; // reentrancy guard for Elementor render
    private $cphub_el_single = false;    // context flag when a template will render
    private $cphub_el_autop_removed = null; // store removed filter priorities for restore

    public function __construct()
    {
        add_action('admin_menu',          [$this, 'admin_menu']);
        add_action('admin_init',          [$this, 'register_settings']);
        add_action('init',                [$this, 'register_dynamic_cpts'], 9);
        add_action('init',                [$this, 'ensure_cron_scheduled'], 10);
        add_action('wp',                  [$this, 'mark_styles_from_query']);
        add_action('admin_post_cphub_consumer_refresh', [$this, 'handle_manual_refresh']);
        add_action('admin_post_cphub_consumer_clear',   [$this, 'handle_clear_cache']);
        add_action('admin_post_cphub_consumer_health',  [$this, 'handle_check_health']);
        add_action('admin_post_cphub_consumer_cleanup', [$this, 'handle_cleanup_retired']);
        add_action('wp_enqueue_scripts',  [$this, 'enqueue_styles']);
        // Removed archive template override: let theme handle archives
        // Elementor template rendering (Consumer): auto-link by slug/title from Publisher meta
        add_action('wp',                  [$this, 'mark_elementor_single_context']);
        add_filter('the_content',         [$this, 'render_elementor_single_content'], 9);
        add_filter('body_class',          [$this, 'filter_body_class']);
        add_filter('post_thumbnail_html', [$this, 'maybe_hide_featured_image'], 10, 5);
        add_action('wp_head',             [$this, 'output_theme_suppress_css'], 99);
        add_filter('use_block_editor_for_post_type', [$this, 'disable_block_editor_for_cphub'], 10, 2);
        add_action('add_meta_boxes',      [$this, 'add_readonly_meta_box']);
        add_filter('default_hidden_meta_boxes', [$this, 'hide_default_custom_fields_box'], 10, 2);
        add_shortcode('cphub_list',       [$this, 'sc_list']);
        add_shortcode('cphub_item',       [$this, 'sc_item']);
        add_shortcode('cphub_location',   [$this, 'sc_location']);

        add_filter('cron_schedules',      [$this, 'add_cron_schedules']);
        add_action(self::CRON_HOOK,       [$this, 'cron_refresh']);

        register_activation_hook(__FILE__, [__CLASS__, 'activate']);
        register_deactivation_hook(__FILE__, [__CLASS__, 'deactivate']);
    }

    public static function activate()
    {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 60, 'cphub_10min', self::CRON_HOOK);
        }
        update_option(self::OPT_CRON_META, [
            'last_run' => 0,
            'scheduled' => wp_next_scheduled(self::CRON_HOOK) ?: 0,
        ], false);
    }

    public static function deactivate()
    {
        $ts = wp_next_scheduled(self::CRON_HOOK);
        if ($ts) wp_unschedule_event($ts, self::CRON_HOOK);
        delete_option(self::OPT_CRON_META);
    }

    public function add_cron_schedules($schedules)
    {
        if (!isset($schedules['cphub_10min'])) {
            $schedules['cphub_10min'] = [ 'interval' => 600, 'display' => __('Every 10 minutes', 'cphub') ];
        }
        return $schedules;
    }

    public function ensure_cron_scheduled()
    {
        // Self-heal scheduling if activation hook missed
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 60, 'cphub_10min', self::CRON_HOOK);
        }
        // Keep next scheduled time visible in settings
        $meta = get_option(self::OPT_CRON_META, []);
        $meta['scheduled'] = wp_next_scheduled(self::CRON_HOOK) ?: 0;
        if (!isset($meta['last_run'])) $meta['last_run'] = 0;
        update_option(self::OPT_CRON_META, $meta, false);
    }

    public function register_settings()
    {
        register_setting('cphub_consumer', self::OPT_SETTINGS, [$this, 'sanitize_settings']);
    }

    public function sanitize_settings($in)
    {
        $out = [];
        $out['publisher_url'] = isset($in['publisher_url']) ? esc_url_raw(trim($in['publisher_url'])) : '';
        $out['secret_key']    = isset($in['secret_key']) ? sanitize_text_field(trim($in['secret_key'])) : '';
        $out['location']      = isset($in['location']) ? sanitize_key(trim($in['location'])) : '';
        $out['use_styles']    = !empty($in['use_styles']) ? true : false;
        $out['save_local']    = !empty($in['save_local']) ? true : false;
        // Enabled CPTs: merge checkboxes + CSV
        $enabled = [];
        if (!empty($in['enabled_cpts']) && is_array($in['enabled_cpts'])) {
            foreach ($in['enabled_cpts'] as $slug => $val) {
                if (!empty($val)) $enabled[] = sanitize_key($slug);
            }
        }
        if (!empty($in['enabled_cpts_csv'])) {
            $parts = array_map('trim', explode(',', $in['enabled_cpts_csv']));
            foreach ($parts as $p) if ($p !== '') $enabled[] = sanitize_key($p);
        }
        $out['enabled_cpts'] = array_values(array_unique(array_filter($enabled)));
        return $out;
    }

    public function admin_menu()
    {
        add_options_page('CPT Hub Consumer', 'CPT Hub Consumer', 'manage_options', 'cphub-consumer', [$this, 'render_settings_page']);
    }

    public function render_settings_page()
    {
        if (!current_user_can('manage_options')) return;
        settings_errors('cphub_consumer');
        $s = get_option(self::OPT_SETTINGS, ['publisher_url'=>'','secret_key'=>'','location'=>'','enabled_cpts'=>[]]);
        if (!isset($s['use_styles'])) $s['use_styles'] = true;
        if (!isset($s['save_local'])) $s['save_local'] = false;
        $cache_items  = get_option(self::OPT_CACHE_ITEMS, []);
        $cache_assets = get_option(self::OPT_CACHE_ASSETS, []);
        $enabled = (array)($s['enabled_cpts'] ?? []);
        $example = trailingslashit($s['publisher_url']) . 'wp-json/cphub/v1/health';
        $health = get_transient('cphub_consumer_last_health');
        ?>
        <div class="wrap">
          <h1>CPT Hub Consumer</h1>
          <form method="post" action="options.php" class="card" style="padding:1em;max-width:900px;">
            <?php settings_fields('cphub_consumer'); ?>
            <table class="form-table" role="presentation">
              <tr>
                <th scope="row"><label for="cphub_pub_url">Publisher URL</label></th>
                <td><input id="cphub_pub_url" name="<?php echo esc_attr(self::OPT_SETTINGS); ?>[publisher_url]" type="url" class="regular-text" placeholder="https://publisher.example.com" value="<?php echo esc_attr($s['publisher_url']); ?>" required></td>
              </tr>
              <tr>
                <th scope="row"><label for="cphub_secret">Secret Key</label></th>
                <td><input id="cphub_secret" name="<?php echo esc_attr(self::OPT_SETTINGS); ?>[secret_key]" type="text" class="regular-text" value="<?php echo esc_attr($s['secret_key']); ?>"> <span class="description">Optional</span></td>
              </tr>
              <tr>
                <th scope="row"><label for="cphub_loc">Location Slug</label></th>
                <td><input id="cphub_loc" name="<?php echo esc_attr(self::OPT_SETTINGS); ?>[location]" type="text" class="regular-text" placeholder="e.g. merrymaidsottawa" value="<?php echo esc_attr($s['location']); ?>"></td>
              </tr>
              <tr>
                <th scope="row"><label>Use Publisher Styles</label></th>
                <td><label><input type="checkbox" name="<?php echo esc_attr(self::OPT_SETTINGS); ?>[use_styles]" value="1" <?php checked(!empty($s['use_styles'])); ?>> Enqueue CSS from Publisher assets</label></td>
              </tr>
              <tr>
                <th scope="row"><label>Local Content</label></th>
                <td>
                  <label><input type="checkbox" name="<?php echo esc_attr(self::OPT_SETTINGS); ?>[save_local]" value="1" <?php checked(!empty($s['save_local'])); ?>> Store items as local posts and sideload media</label>
                  <p class="description">Enables local CPTs for enabled slugs, imports posts/media so links point to local permalinks and assets are available if the Publisher is offline.</p>
                </td>
              </tr>
              <tr>
                <th scope="row">Enabled CPTs</th>
                <td>
                  <p class="description">Enter CPT slugs to ingest (comma‑separated) or use the toggles below.</p>
                  <input type="text" name="<?php echo esc_attr(self::OPT_SETTINGS); ?>[enabled_cpts_csv]" class="regular-text" placeholder="slides, mm-services" value="<?php echo esc_attr(implode(', ', $enabled)); ?>">
                  <div style="margin-top:8px;">
                    <?php
                      // Show toggles for enabled CPTs and any discovered via last health payload
                      $toggle_slugs = $enabled;
                      if ($health && is_array($health) && isset($health['payload']['styles']) && is_array($health['payload']['styles'])) {
                          $toggle_slugs = array_unique(array_merge($toggle_slugs, array_keys($health['payload']['styles'])));
                      }
                      foreach ($toggle_slugs as $slug): if (!$slug) continue; $checked = in_array($slug, $enabled, true);
                    ?>
                      <label style="margin-right:1em;display:inline-block;">
                        <input type="checkbox" name="<?php echo esc_attr(self::OPT_SETTINGS); ?>[enabled_cpts][<?php echo esc_attr($slug); ?>]" value="1" <?php checked($checked); ?>> <?php echo esc_html($slug); ?>
                      </label>
                    <?php endforeach; ?>
                  </div>
                </td>
              </tr>
            </table>
            <p>
              <button class="button button-primary">Save Settings</button>
              <?php if (!empty($s['publisher_url'])): ?>
                <a class="button" href="<?php echo esc_url($example); ?>" target="_blank">Open Health</a>
              <?php endif; ?>
            </p>
          </form>

          <h2 style="margin-top:1.5em;">Sync</h2>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="card" style="padding:1em;max-width:900px;">
            <?php wp_nonce_field('cphub_consumer_refresh'); ?>
            <input type="hidden" name="action" value="cphub_consumer_refresh">
            <button class="button">Refresh Now</button>
            <span class="description">Fetch items and assets for enabled CPTs with conditional GET.</span>
          </form>

          <?php $cron = get_option(self::OPT_CRON_META, ['last_run'=>0,'scheduled'=>0]); $cron_enabled = !defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON; ?>
          <div class="card" style="padding:1em;max-width:900px;margin-top:12px;">
            <p><strong>Cron status:</strong> <?php echo $cron_enabled ? 'WP‑Cron enabled' : 'WP‑Cron disabled'; ?><?php if (!$cron_enabled): ?> <span class="description">(set DISABLE_WP_CRON to false to enable)</span><?php endif; ?></p>
            <p><strong>Last run:</strong> <?php echo !empty($cron['last_run']) ? esc_html(date('Y-m-d H:i', (int)$cron['last_run'])) : '—'; ?> | <strong>Next scheduled:</strong> <?php echo !empty($cron['scheduled']) ? esc_html(date('Y-m-d H:i', (int)$cron['scheduled'])) : '—'; ?></p>
            <p class="description">WP‑Cron triggers on page views. Low‑traffic sites should add a server cron to request <code><?php echo esc_html(site_url('wp-cron.php')); ?></code> every 5–10 minutes.</p>
          </div>

          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="card" style="padding:1em;max-width:900px;margin-top:12px;">
            <?php wp_nonce_field('cphub_consumer_clear'); ?>
            <input type="hidden" name="action" value="cphub_consumer_clear">
            <button class="button button-secondary">Clear Cache</button>
            <span class="description">Removes local items and assets caches for all CPTs.</span>
          </form>

          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="card" style="padding:1em;max-width:900px;margin-top:12px;">
            <?php wp_nonce_field('cphub_consumer_cleanup'); ?>
            <input type="hidden" name="action" value="cphub_consumer_cleanup">
            <button class="button">Clean Up Unknown CPTs</button>
            <span class="description">Removes enabled CPTs that the Publisher reports as unknown (deleted/retired).</span>
          </form>

          <h2 style="margin-top:1.5em;">Cache Status</h2>
          <table class="widefat striped" style="max-width:1100px;">
            <thead><tr><th>CPT</th><th>Items</th><th>ETag</th><th>Last-Modified</th><th>Updated</th><th>Last Status</th><th>Last Error</th><th>Assets ver</th><th>Assets updated</th><th>Assets Status</th><th>Template</th><th>Rewrite</th></tr></thead>
            <tbody>
              <?php $rows = array_unique(array_merge(array_keys((array)$cache_items), array_keys((array)$cache_assets), $enabled));
              if (!$rows) echo '<tr><td colspan="12">No cache yet.</td></tr>';
              foreach ($rows as $slug):
                $ci = $cache_items[$slug] ?? [];
                $ca = $cache_assets[$slug] ?? [];
                $retired = !empty($ci['retired']) || !empty($ca['retired']);
                // Template resolution status (archive template) + show Elementor conditions meta for debug
                $tpl_cell = '—';
                if (isset($ca['archive_template']) && is_array($ca['archive_template'])) {
                  $tmap = $ca['archive_template'];
                  $tslug = isset($tmap['slug']) ? (string)$tmap['slug'] : '';
                  $ttitle= isset($tmap['title']) ? (string)$tmap['title'] : '';
                  if ($tslug !== '' || $ttitle !== '') {
                    $matched = '';
                    $rid = 0;
                    if ($tslug !== '') {
                      $p = get_page_by_path($tslug, OBJECT, 'elementor_library');
                      if ($p && $p->post_status === 'publish') { $rid = (int)$p->ID; $matched = 'slug'; }
                    }
                    if (!$rid && $ttitle !== '') {
                      $p = get_page_by_title($ttitle, OBJECT, 'elementor_library');
                      if ($p && $p->post_status === 'publish') { $rid = (int)$p->ID; $matched = 'title'; }
                      if (!$rid) {
                        $dec = html_entity_decode($ttitle, ENT_QUOTES | ENT_HTML5);
                        if ($dec !== $ttitle) {
                          $p = get_page_by_title($dec, OBJECT, 'elementor_library');
                          if ($p && $p->post_status === 'publish') { $rid = (int)$p->ID; $matched = 'title'; }
                        }
                      }
                    }
                    if ($rid > 0) {
                      // Fetch Elementor conditions meta for this template (for debugging)
                      $cond_keys = ['_elementor_conditions','_elementor_template_conditions'];
                      $cond_dump = '';
                      foreach ($cond_keys as $ck) {
                        $raw = get_post_meta($rid, $ck, true);
                        if ($raw) {
                          $arr = is_string($raw) ? json_decode($raw, true) : ( (array)$raw );
                          // Encode compact and truncate to keep table readable
                          $json = wp_json_encode($arr, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                          if (!is_string($json)) $json = (string)print_r($arr, true);
                          $short = mb_substr($json, 0, 380);
                          if (mb_strlen($json) > 380) $short .= '…';
                          $cond_dump .= '<div style="margin-top:4px;"><span class="description">' . esc_html($ck) . ':</span><br><code style="white-space:pre-wrap; word-break:break-word; display:block; max-height:8.5em; overflow:auto;">' . esc_html($short) . '</code></div>';
                        }
                      }
                      if ($cond_dump === '') {
                        $cond_dump = '<div class="description" style="margin-top:4px;">No conditions meta found.</div>';
                      }
                      $tpl_cell = 'OK (' . esc_html($matched) . ' → ID ' . $rid . ')' . $cond_dump;
                    } else {
                      $tpl_cell = 'Missing (slug: ' . esc_html($tslug) . ')';
                    }
                  }
                }
              ?>
                <tr>
                  <td><code><?php echo esc_html($slug); ?></code></td>
                  <td><?php echo isset($ci['items']) && is_array($ci['items']) ? count($ci['items']) : 0; ?></td>
                  <td style="word-break:break-all;">&quot;<?php echo esc_html(trim((string)($ci['etag'] ?? ''), '"')); ?>&quot;</td>
                  <td><?php echo esc_html($ci['last_modified'] ?? ''); ?></td>
                  <td><?php echo !empty($ci['updated']) ? esc_html(date('Y-m-d H:i', (int)$ci['updated'])) : ''; ?></td>
                  <td><?php echo isset($ci['last_status']) ? (int)$ci['last_status'] : ''; ?></td>
                  <td style="max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo esc_attr(substr((string)($ci['last_error'] ?? ''),0,500)); ?>"><?php if ($retired) { echo 'Unknown on Publisher — disable in settings'; } else { echo esc_html(substr((string)($ci['last_error'] ?? ''),0,60)); } ?></td>
                  <td><?php echo esc_html($ca['version'] ?? ''); ?></td>
                  <td><?php echo !empty($ca['updated']) ? esc_html(date('Y-m-d H:i', (int)$ca['updated'])) : ''; ?></td>
                  <td><?php echo isset($ca['last_status']) ? (int)$ca['last_status'] : ''; ?></td>
                  <td><?php echo $tpl_cell; ?></td>
                  <td><?php echo isset($ca['rewrite_slug']) && $ca['rewrite_slug'] !== '' ? esc_html($ca['rewrite_slug']) : '—'; ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>

          <?php $g = get_option(self::OPT_CACHE_GLOBAL, []); ?>
          <h3 style="margin-top:1em;">Global CSS Status</h3>
          <table class="widefat striped" style="max-width:1100px;">
            <thead><tr><th>Version</th><th>ETag</th><th>Last-Modified</th><th>Updated</th><th>Last Status</th><th>Last Error</th></tr></thead>
            <tbody>
              <tr>
                <td><?php echo esc_html($g['version'] ?? ''); ?></td>
                <td style="word-break:break-all;">&quot;<?php echo esc_html(trim((string)($g['etag'] ?? ''), '"')); ?>&quot;</td>
                <td><?php echo esc_html($g['last_modified'] ?? ''); ?></td>
                <td><?php echo !empty($g['updated']) ? esc_html(date('Y-m-d H:i', (int)$g['updated'])) : ''; ?></td>
                <td><?php echo isset($g['last_status']) ? (int)$g['last_status'] : ''; ?></td>
                <td style="max-width:420px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo esc_attr(substr((string)($g['last_error'] ?? ''),0,500)); ?>"><?php echo esc_html(substr((string)($g['last_error'] ?? ''),0,80)); ?></td>
              </tr>
            </tbody>
          </table>

        <h2 style="margin-top:1.5em;">Publisher Health</h2>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="card" style="padding:1em;max-width:900px;">
            <?php wp_nonce_field('cphub_consumer_health'); ?>
            <input type="hidden" name="action" value="cphub_consumer_health">
            <button class="button">Check Health</button>
            <span class="description">Fetches status from the Publisher’s health endpoint and displays summary below.</span>
          </form>
          <div class="card" style="padding:1em;max-width:900px;">
            <?php if ($health && is_array($health)): ?>
              <p><strong>Last Checked:</strong> <?php echo esc_html(date('Y-m-d H:i', (int)($health['time'] ?? time()))); ?></p>
              <?php $hp = isset($health['payload']) && is_array($health['payload']) ? $health['payload'] : []; ?>
              <?php if ($hp): ?>
                <p><strong>Status:</strong> <?php echo esc_html($hp['status'] ?? 'unknown'); ?> | <strong>Time:</strong> <?php echo esc_html($hp['time'] ?? ''); ?></p>
                <p><strong>Feed:</strong> <?php echo esc_html($hp['feed']['base'] ?? ''); ?> | <strong>REST:</strong> <?php echo esc_html($hp['rest']['base'] ?? ''); ?></p>
                <p><strong>Publisher CPTs (styles known):</strong>
                  <?php echo isset($hp['styles']) && is_array($hp['styles']) ? esc_html(implode(', ', array_keys($hp['styles']))) : '—'; ?></p>
              <?php else: ?>
                <p><em>No health data yet. Click "Check Health".</em></p>
              <?php endif; ?>
            <?php else: ?>
              <p><em>No health data yet. Click "Check Health".</em></p>
            <?php endif; ?>
          </div>

          <h2 style="margin-top:1.5em;">Register CPT Code (Consumer)</h2>
          <div class="card" style="padding:1em;max-width:1100px;">
            <p class="description">Local CPT registration based on cached assets. Useful to compare with Publisher.</p>
            <?php
              $assets = get_option(self::OPT_CACHE_ASSETS, []);
              if (!$enabled) {
                echo '<p>No enabled CPTs.</p>';
              } else {
                foreach ($enabled as $slug) {
                  $pt = sanitize_key($slug);
                  $label = isset($assets[$pt]['label']) && is_string($assets[$pt]['label']) && $assets[$pt]['label'] !== '' ? (string)$assets[$pt]['label'] : ucwords(str_replace(['-','_'],' ',$pt));
                  $rw   = isset($assets[$pt]['rewrite_slug']) ? (string)$assets[$pt]['rewrite_slug'] : $pt;
                  $arch = isset($assets[$pt]['archive_base']) && $assets[$pt]['archive_base'] !== '' ? (string)$assets[$pt]['archive_base'] : true;
                  $args = [
                    'label' => $label,
                    'labels' => [ 'name' => $label, 'singular_name' => $label ],
                    'public' => true,
                    'has_archive' => $arch,
                    'show_in_rest' => false,
                    'supports' => ['title','editor','excerpt','thumbnail','custom-fields'],
                    'menu_icon' => 'dashicons-archive',
                    'rewrite' => ['slug' => $rw, 'with_front' => false],
                  ];
                  $code = "register_post_type('" . esc_html($pt) . "', " . var_export($args, true) . ");";
                  echo '<h3><code>' . esc_html($pt) . '</code></h3>';
                  echo '<pre class="code"><code>' . esc_html($code) . '</code></pre>';
                }
              }
            ?>
          </div>
        </div>
        <?php
    }

    public function handle_manual_refresh()
    {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        check_admin_referer('cphub_consumer_refresh');
        $this->refresh_all();
        wp_safe_redirect(add_query_arg(['page'=>'cphub-consumer','refreshed'=>'1'], admin_url('options-general.php')));
        exit;
    }

    public function cron_refresh()
    {
        $this->refresh_all();
        update_option(self::OPT_CRON_META, [
            'last_run' => time(),
            'scheduled' => wp_next_scheduled(self::CRON_HOOK) ?: 0,
        ], false);
    }

    public function handle_clear_cache()
    {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        check_admin_referer('cphub_consumer_clear');
        delete_option(self::OPT_CACHE_ITEMS);
        delete_option(self::OPT_CACHE_ASSETS);
        delete_option(self::OPT_CACHE_GLOBAL);
        delete_transient('cphub_consumer_last_health');
        add_settings_error('cphub_consumer', 'cache_cleared', 'Consumer cache cleared.', 'updated');
        wp_safe_redirect(add_query_arg(['page'=>'cphub-consumer'], admin_url('options-general.php')));
        exit;
    }

    public function handle_check_health()
    {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        check_admin_referer('cphub_consumer_health');
        $s = get_option(self::OPT_SETTINGS, []);
        $base = isset($s['publisher_url']) ? trim($s['publisher_url']) : '';
        if ($base !== '') {
            $url = trailingslashit($base) . 'wp-json/cphub/v1/health';
            $res = wp_remote_get($url, ['timeout'=>10]);
            if (!is_wp_error($res) && wp_remote_retrieve_response_code($res) === 200) {
                $payload = json_decode(wp_remote_retrieve_body($res), true);
                set_transient('cphub_consumer_last_health', ['payload'=>$payload, 'time'=>time()], 300);
                add_settings_error('cphub_consumer', 'health_ok', 'Health fetched successfully.', 'updated');
            } else {
                add_settings_error('cphub_consumer', 'health_err', 'Failed to fetch health from Publisher.', 'error');
            }
        } else {
            add_settings_error('cphub_consumer', 'health_url', 'Set Publisher URL first.', 'error');
        }
        wp_safe_redirect(add_query_arg(['page'=>'cphub-consumer'], admin_url('options-general.php')));
        exit;
    }

    public function handle_cleanup_retired()
    {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        check_admin_referer('cphub_consumer_cleanup');
        $s = get_option(self::OPT_SETTINGS, []);
        $enabled = array_map('sanitize_key', (array)($s['enabled_cpts'] ?? []));
        $removed = [];
        if ($enabled) {
            $items_cache  = get_option(self::OPT_CACHE_ITEMS, []);
            $assets_cache = get_option(self::OPT_CACHE_ASSETS, []);
            $kept = [];
            foreach ($enabled as $slug) {
                if (!empty($items_cache[$slug]['retired']) || !empty($assets_cache[$slug]['retired'])) {
                    $removed[] = $slug;
                } else {
                    $kept[] = $slug;
                }
            }
            $s['enabled_cpts'] = $kept;
            update_option(self::OPT_SETTINGS, $s, false);
        }
        if ($removed) {
            add_settings_error('cphub_consumer', 'cleanup_ok', sprintf('Removed %d unknown CPT(s): %s', count($removed), implode(', ', $removed)), 'updated');
        } else {
            add_settings_error('cphub_consumer', 'cleanup_none', 'No unknown CPTs found to remove.', 'updated');
        }
        wp_safe_redirect(add_query_arg(['page'=>'cphub-consumer'], admin_url('options-general.php')));
        exit;
    }
    private function refresh_all()
    {
        $s = get_option(self::OPT_SETTINGS, []);
        $enabled = (array)($s['enabled_cpts'] ?? []);
        if (!$enabled) return;
        // Ensure CPTs are registered if Local Content is enabled
        if (!empty($s['save_local']) && !self::$did_register) {
            $this->register_dynamic_cpts();
        }
        $items_cache  = get_option(self::OPT_CACHE_ITEMS, []);
        $assets_cache = get_option(self::OPT_CACHE_ASSETS, []);
        foreach ($enabled as $slug) {
            // Skip permanently retired CPTs (Publisher no longer has them)
            if (!empty($items_cache[$slug]['retired']) || !empty($assets_cache[$slug]['retired'])) {
                continue;
            }
            $this->fetch_items($slug);
            $this->fetch_assets($slug);
        }
        // Refresh global CSS as well
        $this->fetch_global_css();
    }

    private function build_url($base, $path, $args)
    {
        $url = trailingslashit($base) . ltrim($path, '/');
        return add_query_arg($args, $url);
    }

    private function fetch_items($cpt)
    {
        $s = get_option(self::OPT_SETTINGS, []);
        $base = isset($s['publisher_url']) ? trim($s['publisher_url']) : '';
        if ($base === '') return;
        $cache = get_option(self::OPT_CACHE_ITEMS, []);
        $entry = isset($cache[$cpt]) && is_array($cache[$cpt]) ? $cache[$cpt] : [];

        $args = [ 'cpt' => $cpt, 'n' => 100 ];
        if (!empty($s['location'])) $args['location'] = $s['location'];
        if (!empty($s['secret_key'])) $args['key'] = $s['secret_key'];
        $url = $this->build_url($base, 'wp-json/cphub/v1/items', $args);

        $headers = [];
        if (!empty($entry['etag'])) $headers['If-None-Match'] = $entry['etag'];
        if (!empty($entry['last_modified'])) $headers['If-Modified-Since'] = $entry['last_modified'];

        $res = wp_remote_get($url, [ 'timeout' => 15, 'headers' => $headers, 'user-agent' => 'CPT-Hub-Consumer/0.1' ]);
        if (is_wp_error($res)) {
            $entry['last_status'] = 0;
            $entry['last_error']  = $res->get_error_message();
            $entry['updated']     = time();
            $cache[$cpt] = $entry;
            update_option(self::OPT_CACHE_ITEMS, $cache, false);
            return;
        }
        $code = wp_remote_retrieve_response_code($res);
        $entry['last_status'] = $code;
        if ($code === 304) {
            $entry['updated'] = time();
            $cache[$cpt] = $entry;
            update_option(self::OPT_CACHE_ITEMS, $cache, false);
            return;
        }
        if ($code !== 200) {
            $body_err = wp_remote_retrieve_body($res);
            $entry['last_error'] = $body_err;
            $entry['updated']    = time();
            // If Publisher reports unknown CPT, mark as retired to skip future fetches
            $json = json_decode((string)$body_err, true);
            if (is_array($json) && isset($json['code']) && $json['code'] === 'cphub_bad_cpt') {
                $entry['retired'] = true;
            }
            $cache[$cpt] = $entry;
            update_option(self::OPT_CACHE_ITEMS, $cache, false);
            return;
        }
        $body = wp_remote_retrieve_body($res);
        $data = json_decode($body, true);
        if (!is_array($data)) return;
        $etag = wp_remote_retrieve_header($res, 'etag');
        $last = wp_remote_retrieve_header($res, 'last-modified');
        $entry['etag'] = is_string($etag) ? $etag : '';
        $entry['last_modified'] = is_string($last) ? $last : '';
        $entry['items'] = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];
        $entry['updated'] = time();
        if (isset($entry['retired'])) unset($entry['retired']);
        $cache[$cpt] = $entry;
        update_option(self::OPT_CACHE_ITEMS, $cache, false);

        // When Local Content is enabled, upsert items into local CPT and sideload assets
        if (!empty($s['save_local'])) {
            $this->sync_local_posts($cpt, $entry['items']);
        }
    }

    private function fetch_assets($cpt)
    {
        $s = get_option(self::OPT_SETTINGS, []);
        $base = isset($s['publisher_url']) ? trim($s['publisher_url']) : '';
        if ($base === '') return;
        $cache = get_option(self::OPT_CACHE_ASSETS, []);
        $entry = isset($cache[$cpt]) && is_array($cache[$cpt]) ? $cache[$cpt] : [];

        $args = [ 'cpt' => $cpt ];
        if (!empty($s['secret_key'])) $args['key'] = $s['secret_key'];
        $url = $this->build_url($base, 'wp-json/cphub/v1/assets', $args);

        // If archive template mapping is missing from cache, force a full fetch (avoid 304 with stale schema)
        $headers = [];
        $force_full = empty($entry) || !isset($entry['archive_template']);
        if (!$force_full) {
            if (!empty($entry['etag'])) $headers['If-None-Match'] = $entry['etag'];
            if (!empty($entry['last_modified'])) $headers['If-Modified-Since'] = $entry['last_modified'];
        }

        $res = wp_remote_get($url, [ 'timeout' => 15, 'headers' => $headers, 'user-agent' => 'CPT-Hub-Consumer/0.1' ]);
        if (is_wp_error($res)) {
            $entry['last_status'] = 0;
            $entry['last_error']  = $res->get_error_message();
            $entry['updated']     = time();
            $cache[$cpt] = $entry;
            update_option(self::OPT_CACHE_ASSETS, $cache, false);
            return;
        }
        $code = wp_remote_retrieve_response_code($res);
        $entry['last_status'] = $code;
        if ($code === 304) {
            $entry['updated'] = time();
            $cache[$cpt] = $entry;
            update_option(self::OPT_CACHE_ASSETS, $cache, false);
            return;
        }
        if ($code !== 200) {
            $body_err = wp_remote_retrieve_body($res);
            $entry['last_error'] = $body_err;
            $entry['updated']    = time();
            $json = json_decode((string)$body_err, true);
            if (is_array($json) && isset($json['code']) && $json['code'] === 'cphub_bad_cpt') {
                $entry['retired'] = true;
            }
            $cache[$cpt] = $entry;
            update_option(self::OPT_CACHE_ASSETS, $cache, false);
            return;
        }
        $body = wp_remote_retrieve_body($res);
        $data = json_decode($body, true);
        if (!is_array($data)) return;
        $etag = wp_remote_retrieve_header($res, 'etag');
        $last = wp_remote_retrieve_header($res, 'last-modified');
        $entry['etag'] = is_string($etag) ? $etag : '';
        $entry['last_modified'] = is_string($last) ? $last : '';
        $entry['version'] = isset($data['version']) ? (string)$data['version'] : '';
        $entry['css'] = isset($data['css']) ? (string)$data['css'] : '';
        $entry['layout'] = isset($data['layout']) && is_array($data['layout']) ? $data['layout'] : [];
        if (isset($data['archive_template']) && is_array($data['archive_template'])) {
            $entry['archive_template'] = [
                'slug'  => isset($data['archive_template']['slug']) ? (string)$data['archive_template']['slug'] : '',
                'title' => isset($data['archive_template']['title']) ? (string)$data['archive_template']['title'] : '',
            ];
        } else {
            unset($entry['archive_template']);
        }
        // Rewrite + archive base from Publisher
        $old_rw = isset($entry['rewrite_slug']) ? (string)$entry['rewrite_slug'] : '';
        $entry['rewrite_slug'] = isset($data['rewrite_slug']) ? (string)$data['rewrite_slug'] : '';
        $entry['archive_base'] = isset($data['archive_base']) && $data['archive_base'] !== false ? (string)$data['archive_base'] : '';
        // If rewrite slug changed, flush rewrite rules so URLs work
        if ($old_rw !== '' && $entry['rewrite_slug'] !== '' && $old_rw !== $entry['rewrite_slug']) {
            flush_rewrite_rules();
        }
        if (isset($data['layout_type']) && $data['layout_type'] === 'grid') {
            $entry['layout_type'] = 'grid';
        } else {
            // Fallback detection from CSS if layout_type missing
            $entry['layout_type'] = (strpos($entry['css'], '.cphub-grid{display:grid') !== false) ? 'grid' : 'list';
        }
        $entry['updated'] = time();
        if (isset($entry['retired'])) unset($entry['retired']);
        $cache[$cpt] = $entry;
        update_option(self::OPT_CACHE_ASSETS, $cache, false);

        // Removed: previously ensured Elementor archive display conditions on Consumer.
    }

    private function mark_need_style($cpt)
    {
        if (!in_array($cpt, self::$needs_styles, true)) self::$needs_styles[] = $cpt;
    }

    private function enqueue_style_for_cpt($cpt)
    {
        $s = get_option(self::OPT_SETTINGS, []);
        if (empty($s['use_styles'])) return;
        $assets = get_option(self::OPT_CACHE_ASSETS, []);
        if (empty($assets[$cpt]['css'])) return;
        $ver = isset($assets[$cpt]['version']) ? substr((string)$assets[$cpt]['version'], 0, 10) : '0';
        $handle = 'cphub-' . sanitize_key($cpt) . '-' . $ver;
        if (!wp_style_is($handle, 'enqueued')) {
            if (!wp_style_is($handle, 'registered')) {
                wp_register_style($handle, false, [], null);
            }
            wp_add_inline_style($handle, (string)$assets[$cpt]['css']);
            wp_enqueue_style($handle);
        }
    }

    // Removed: ensure_elementor_archive_condition — Consumer no longer manages archive conditions.

    public function enqueue_styles()
    {
        $s = get_option(self::OPT_SETTINGS, []);
        if (empty($s['use_styles'])) return;
        $assets = get_option(self::OPT_CACHE_ASSETS, []);
        // Enqueue global CSS first if present
        $global = get_option(self::OPT_CACHE_GLOBAL, []);
        if (!empty($global['css'])) {
            $gver = isset($global['version']) ? substr((string)$global['version'], 0, 10) : '0';
            $gh = 'cphub-global-' . $gver;
            if (!wp_style_is($gh, 'registered')) {
                wp_register_style($gh, false, [], null);
                wp_add_inline_style($gh, (string)$global['css']);
            }
            wp_enqueue_style($gh);
        }
        foreach (self::$needs_styles as $cpt) {
            if (empty($assets[$cpt]['css'])) continue;
            $ver = isset($assets[$cpt]['version']) ? substr((string)$assets[$cpt]['version'], 0, 10) : '0';
            $handle = 'cphub-' . sanitize_key($cpt) . '-' . $ver;
            if (!wp_style_is($handle, 'registered')) {
                wp_register_style($handle, false, [], null);
                wp_add_inline_style($handle, (string)$assets[$cpt]['css']);
            }
            wp_enqueue_style($handle);
        }
    }

    private function fetch_global_css()
    {
        $s = get_option(self::OPT_SETTINGS, []);
        $base = isset($s['publisher_url']) ? trim($s['publisher_url']) : '';
        if ($base === '') return;
        $cache = get_option(self::OPT_CACHE_GLOBAL, []);
        $args = [];
        if (!empty($s['secret_key'])) $args['key'] = $s['secret_key'];
        $url = $this->build_url($base, 'wp-json/cphub/v1/global', $args);
        $headers = [];
        if (!empty($cache['etag'])) $headers['If-None-Match'] = $cache['etag'];
        if (!empty($cache['last_modified'])) $headers['If-Modified-Since'] = $cache['last_modified'];
        $res = wp_remote_get($url, [ 'timeout' => 15, 'headers' => $headers, 'user-agent' => 'CPT-Hub-Consumer/0.1' ]);
        if (is_wp_error($res)) {
            $cache['last_status'] = 0;
            $cache['last_error']  = $res->get_error_message();
            $cache['updated']     = time();
            update_option(self::OPT_CACHE_GLOBAL, $cache, false);
            return;
        }
        $code = wp_remote_retrieve_response_code($res);
        $cache['last_status'] = $code;
        if ($code === 304) {
            $cache['updated'] = time();
            update_option(self::OPT_CACHE_GLOBAL, $cache, false);
            return;
        }
        if ($code !== 200) {
            $cache['last_error'] = wp_remote_retrieve_body($res);
            $cache['updated']    = time();
            update_option(self::OPT_CACHE_GLOBAL, $cache, false);
            return;
        }
        $body = wp_remote_retrieve_body($res);
        $data = json_decode($body, true);
        if (!is_array($data)) return;
        $etag = wp_remote_retrieve_header($res, 'etag');
        $last = wp_remote_retrieve_header($res, 'last-modified');
        $cache['etag'] = is_string($etag) ? $etag : '';
        $cache['last_modified'] = is_string($last) ? $last : '';
        $cache['version'] = isset($data['version']) ? (string)$data['version'] : '';
        $cache['css'] = isset($data['css']) ? (string)$data['css'] : '';
        $cache['updated'] = time();
        update_option(self::OPT_CACHE_GLOBAL, $cache, false);
    }

    public function mark_styles_from_query()
    {
        // Auto-enqueue styles on local CPT archive/single views (when Use Publisher Styles is enabled)
        $s = get_option(self::OPT_SETTINGS, []);
        if (empty($s['use_styles'])) return;
        $enabled = (array)($s['enabled_cpts'] ?? []);
        if (!$enabled) return;
        foreach ($enabled as $slug) {
            $slug = sanitize_key($slug);
            if (function_exists('is_singular') && is_singular($slug)) {
                $this->mark_need_style($slug);
            } elseif (function_exists('is_post_type_archive') && is_post_type_archive($slug)) {
                $this->mark_need_style($slug);
            }
        }
    }

    private function get_cached_items($cpt)
    {
        $cache = get_option(self::OPT_CACHE_ITEMS, []);
        $items = isset($cache[$cpt]['items']) && is_array($cache[$cpt]['items']) ? $cache[$cpt]['items'] : [];
        return $items;
    }

    private function get_layout_type($cpt)
    {
        $assets = get_option(self::OPT_CACHE_ASSETS, []);
        $type = isset($assets[$cpt]['layout_type']) ? (string)$assets[$cpt]['layout_type'] : '';
        if ($type === 'grid' || $type === 'list') return $type;
        // Fallback: inspect CSS for grid rule
        $css = isset($assets[$cpt]['css']) ? (string)$assets[$cpt]['css'] : '';
        if ($css !== '' && strpos($css, '.cphub-grid{display:grid') !== false) {
            $assets[$cpt]['layout_type'] = 'grid';
            update_option(self::OPT_CACHE_ASSETS, $assets, false);
            return 'grid';
        }
        return 'list';
    }

    /* ================= Local Content ================= */

    public function register_dynamic_cpts()
    {
        if (self::$did_register) return;
        $s = get_option(self::OPT_SETTINGS, []);
        if (empty($s['save_local'])) return;
        $enabled = (array)($s['enabled_cpts'] ?? []);
        $assets = get_option(self::OPT_CACHE_ASSETS, []);
        foreach ($enabled as $slug) {
            $pt = sanitize_key($slug);
            $label = isset($assets[$pt]['label']) && is_string($assets[$pt]['label']) && $assets[$pt]['label'] !== ''
                ? (string)$assets[$pt]['label']
                : ucwords(str_replace(['-', '_'], ' ', $pt));
            $rw = isset($assets[$pt]['rewrite_slug']) ? (string)$assets[$pt]['rewrite_slug'] : $pt;
            $arch = isset($assets[$pt]['archive_base']) && $assets[$pt]['archive_base'] !== '' ? (string)$assets[$pt]['archive_base'] : true;
            if (!post_type_exists($pt)) {
                register_post_type($pt, [
                    'label' => $label,
                    'labels' => [ 'name' => $label, 'singular_name' => $label ],
                    'public' => true,
                    'has_archive' => $arch,
                    'show_in_rest' => false,
                    'supports' => ['title','editor','excerpt','thumbnail','custom-fields'],
                    'menu_icon' => 'dashicons-archive',
                    'rewrite' => ['slug' => $rw, 'with_front' => false],
                ]);
            } else {
                // Update labels and rewrite for already-registered types (no re-register in WP)
                global $wp_post_types;
                if (isset($wp_post_types[$pt])) {
                    $obj = $wp_post_types[$pt];
                    // Update labels
                    if (isset($obj->labels)) {
                        $obj->labels->name = $label;
                        $obj->labels->singular_name = $label;
                        $obj->label = $label;
                    }
                    // Update archive and rewrite slug
                    $obj->has_archive = $arch;
                    if (!isset($obj->rewrite) || !is_array($obj->rewrite)) $obj->rewrite = [];
                    $obj->rewrite['slug'] = $rw;
                    $obj->rewrite['with_front'] = false;
                }
            }
            // Ensure classic Custom Fields panel is available
            add_post_type_support($pt, 'custom-fields');
        }
        self::$did_register = true;
    }

    public function disable_block_editor_for_cphub($use_block_editor, $post_type)
    {
        // Force Classic Editor for CPT Hub local content to match Publisher behavior
        $s = get_option(self::OPT_SETTINGS, []);
        if (empty($s['save_local'])) return $use_block_editor;
        $enabled = array_map('sanitize_key', (array)($s['enabled_cpts'] ?? []));
        if (in_array($post_type, $enabled, true)) return false;
        return $use_block_editor;
    }

    public function add_readonly_meta_box()
    {
        $s = get_option(self::OPT_SETTINGS, []);
        if (empty($s['save_local'])) return;
        $enabled = array_map('sanitize_key', (array)($s['enabled_cpts'] ?? []));
        foreach ($enabled as $pt) {
            add_meta_box(
                'cphub_meta_readonly',
                'CPT Hub Meta (Read‑only)',
                [$this, 'render_readonly_meta_box'],
                $pt,
                'normal',
                'default'
            );
        }
    }

    public function hide_default_custom_fields_box($hidden, $screen)
    {
        // Hide the native Custom Fields box by default for CPT Hub CPTs (still available via Screen Options)
        $s = get_option(self::OPT_SETTINGS, []);
        $enabled = array_map('sanitize_key', (array)($s['enabled_cpts'] ?? []));
        if (!empty($screen->post_type) && in_array($screen->post_type, $enabled, true)) {
            if (!in_array('postcustom', $hidden, true)) $hidden[] = 'postcustom';
        }
        return $hidden;
    }

    public function render_readonly_meta_box($post)
    {
        $all = get_post_meta($post->ID);
        if (!$all || !is_array($all)) { echo '<p><em>No meta.</em></p>'; return; }

        // Collect base values and media helpers grouped by base key
        $base = [];
        $media = [];
        foreach ($all as $k => $vals) {
            if (!is_array($vals)) continue;
            $val = reset($vals);
            if (!is_scalar($val)) continue;
            $val = (string)$val;
            if ($k === '') continue;
            if (preg_match('/^(.*)_(id|url|mime)$/', $k, $m)) {
                $b = $m[1]; $t = $m[2];
                if ($b !== '' && $b[0] !== '_') {
                    if (!isset($media[$b])) $media[$b] = [];
                    $media[$b][$t] = $val;
                }
                continue;
            }
            if ($k[0] === '_') continue; // protected
            $base[$k] = $val;
        }

        // Union of keys: base keys and media bases
        $keys = array_unique(array_merge(array_keys($base), array_keys($media)));
        if (!$keys) { echo '<p><em>No public meta fields.</em></p>'; return; }
        sort($keys, SORT_NATURAL | SORT_FLAG_CASE);

        echo '<table class="widefat striped"><thead><tr><th style="width:40%">Meta key</th><th>Value</th></tr></thead><tbody>';
        foreach ($keys as $k) {
            $display = '';
            // Prefer URL from media helpers if present
            if (isset($media[$k]['url']) && $media[$k]['url'] !== '') {
                $url = esc_url($media[$k]['url']);
                $mime = isset($media[$k]['mime']) ? (string)$media[$k]['mime'] : '';
                if (is_string($mime) && strpos($mime, 'image/') === 0) {
                    $display = '<a style=" display: block; background: #000; width: 60px; padding: 10px; box-sizing: border-box; href="' . $url . '" target="_blank" rel="noopener"><img src="' . $url . '" alt="" style="max-width:140px;height:auto;" /></a>';
                } else {
                    $display = '<a href="' . $url . '" target="_blank" rel="noopener">' . esc_html(basename(parse_url($media[$k]['url'], PHP_URL_PATH) ?: $media[$k]['url'])) . '</a>';
                }
            } elseif (isset($base[$k]) && $base[$k] !== '' && ctype_digit($base[$k])) {
                // Looks like an attachment ID; try to resolve URL
                $aid = intval($base[$k]);
                if ($aid > 0) {
                    $url = wp_get_attachment_url($aid);
                    $mime = get_post_mime_type($aid);
                    if ($url) {
                        $eurl = esc_url($url);
                        if (is_string($mime) && strpos($mime, 'image/') === 0) {
                            $display = '<a style=" display: block; background: #000; width: 60px; padding: 10px; box-sizing: border-box; href="' . $eurl . '" target="_blank" rel="noopener"><img src="' . $eurl . '" alt="" style="max-width:140px;height:auto;" /></a>';
                        } else {
                            $display = '<a href="' . $eurl . '" target="_blank" rel="noopener">' . esc_html(basename(parse_url($url, PHP_URL_PATH) ?: $url)) . '</a>';
                        }
                    }
                }
            }
            if ($display === '') {
                $display = isset($base[$k]) ? esc_html($base[$k]) : '<em>—</em>';
            }
            echo '<tr><td><code>' . esc_html($k) . '</code></td><td>' . $display . '</td></tr>';
        }
        echo '</tbody></table>';
        echo '<p class="description">Read‑only view of Publisher meta. Media helpers (e.g., <code>_id</code>, <code>_url</code>, <code>_mime</code>) are hidden; media shows a preview or file link.</p>';
    }

    /* ================= Elementor Single Templates (auto-link by slug/title) ================= */
    public function mark_elementor_single_context()
    {
        $this->cphub_el_single = false;
        if (!class_exists('Elementor\\Plugin')) return;
        if (!function_exists('is_singular') || !is_singular()) return;
        $post = get_queried_object();
        if (!$post || empty($post->post_type)) return;
        $s = get_option(self::OPT_SETTINGS, []);
        $enabled = array_map('sanitize_key', (array)($s['enabled_cpts'] ?? []));
        if (!in_array($post->post_type, $enabled, true)) return;
        $slug = (string)get_post_meta($post->ID, 'cphub_el_template_key', true);
        $title= (string)get_post_meta($post->ID, 'cphub_el_template_title', true);
        if ($slug === '' && $title === '') return;
        // Try resolving a template now to decide if we should suppress theme elements
        $tpl_id = $this->resolve_elementor_template_id($slug, $title);
        if ($tpl_id > 0) $this->cphub_el_single = true;
    }

    private function resolve_elementor_template_id($slug, $title)
    {
        $slug = sanitize_title($slug);
        if ($slug !== '') {
            $tpl = get_page_by_path($slug, OBJECT, 'elementor_library');
            if ($tpl && $tpl->post_status === 'publish') return (int)$tpl->ID;
        }
        $title = is_string($title) ? trim($title) : '';
        if ($title !== '') {
            // Try raw title
            $tpl = get_page_by_title($title, OBJECT, 'elementor_library');
            if ($tpl && $tpl->post_status === 'publish') return (int)$tpl->ID;
            // Try decoding HTML entities (e.g., &#8211; → –)
            $decoded = html_entity_decode($title, ENT_QUOTES | ENT_HTML5);
            if ($decoded !== $title) {
                $tpl = get_page_by_title($decoded, OBJECT, 'elementor_library');
                if ($tpl && $tpl->post_status === 'publish') return (int)$tpl->ID;
            }
        }
        return 0;
    }

    public function render_elementor_single_content($content)
    {
        if (!class_exists('Elementor\\Plugin')) return $content;
        if (is_admin() || is_feed() || !in_the_loop() || !is_main_query()) return $content;
        $post = get_post();
        if (!$post) return $content;
        if (!is_singular($post->post_type)) return $content;
        $s = get_option(self::OPT_SETTINGS, []);
        $enabled = array_map('sanitize_key', (array)($s['enabled_cpts'] ?? []));
        if (!in_array($post->post_type, $enabled, true)) return $content;
        $slug = (string)get_post_meta($post->ID, 'cphub_el_template_key', true);
        $title= (string)get_post_meta($post->ID, 'cphub_el_template_title', true);
        if ($slug === '' && $title === '') return $content;

        $tpl_id = $this->resolve_elementor_template_id($slug, $title);
        if ($tpl_id <= 0) return $content;
        if ($this->cphub_el_rendering) return $content;
        $this->cphub_el_rendering = true;
        $this->cphub_el_single = true;

        // Disable wpautop/shortcode_unautop while rendering
        $had_unautop = has_filter('the_content', 'shortcode_unautop');
        $had_wpautop = has_filter('the_content', 'wpautop');
        if ($had_unautop !== false) remove_filter('the_content', 'shortcode_unautop', is_int($had_unautop) ? $had_unautop : 10);
        if ($had_wpautop !== false) remove_filter('the_content', 'wpautop', is_int($had_wpautop) ? $had_wpautop : 10);
        $this->cphub_el_autop_removed = [ 'unautop' => $had_unautop, 'wpautop' => $had_wpautop ];
        add_action('wp_footer', [$this, 'restore_autop_filters'], 1);

        try {
            $html = do_shortcode('[elementor-template id="' . (int)$tpl_id . '"]');
        } catch (\Throwable $e) {
            $html = '';
        }

        $this->cphub_el_rendering = false;
        if (is_string($html) && $html !== '') return $html;
        return $content;
    }

    public function restore_autop_filters()
    {
        if (!is_array($this->cphub_el_autop_removed)) return;
        $u = $this->cphub_el_autop_removed['unautop'] ?? false;
        $w = $this->cphub_el_autop_removed['wpautop'] ?? false;
        if ($u !== false) add_filter('the_content', 'shortcode_unautop', is_int($u) ? $u : 10);
        if ($w !== false) add_filter('the_content', 'wpautop', is_int($w) ? $w : 10);
        $this->cphub_el_autop_removed = null;
        remove_action('wp_footer', [$this, 'restore_autop_filters'], 1);
    }

    public function filter_body_class($classes)
    {
        if ($this->cphub_el_single && is_array($classes)) $classes[] = 'cphub-el-single';
        return $classes;
    }

    public function maybe_hide_featured_image($html, $post_id, $post_thumbnail_id, $size, $attr)
    {
        if ($this->cphub_el_single && is_singular()) return '';
        return $html;
    }

    public function output_theme_suppress_css()
    {
        if (!$this->cphub_el_single) return;
        echo '<style id="cphub-el-template-suppress">'
           . '.cphub-el-single .post-media,'
           . '.cphub-el-single .post-meta,'
           . '.cphub-el-single .post-meta-content,'
           . '.cphub-el-single .post-meta-content-inner,'
           . '.cphub-el-single .entry-meta,'
           . '.cphub-el-single .inner-content > .post-meta{display:none !important;}'
           . '</style>';
    }

    // Removed: maybe_use_elementor_archive_template — archives no longer overridden by Consumer.

    private function sync_local_posts($cpt, array $items)
    {
        foreach ($items as $it) {
            $this->upsert_local_post($cpt, $it);
        }
    }

    private function upsert_local_post($cpt, array $item)
    {
        $remote_id = (string)($item['id'] ?? '');
        if ($remote_id === '') return 0;
        $post_id = $this->find_local_post_id($cpt, $remote_id);
        $postarr = [
            'post_type'   => $cpt,
            'post_status' => 'publish',
            'post_title'  => isset($item['title']) ? wp_strip_all_tags((string)$item['title']) : '',
            'post_content'=> isset($item['content']) ? (string)$item['content'] : '',
            'post_excerpt'=> isset($item['excerpt']) ? wp_strip_all_tags((string)$item['excerpt']) : '',
        ];
        if ($post_id) {
            $postarr['ID'] = $post_id;
        }
        // Preserve original publish/modified dates when first creating
        if (!$post_id) {
            if (!empty($item['date'])) {
                $ts = strtotime((string)$item['date']);
                if ($ts) { $postarr['post_date_gmt'] = gmdate('Y-m-d H:i:s', $ts); $postarr['post_date'] = get_date_from_gmt($postarr['post_date_gmt']); }
            }
            if (!empty($item['modified'])) {
                $ms = strtotime((string)$item['modified']);
                if ($ms) { $postarr['post_modified_gmt'] = gmdate('Y-m-d H:i:s', $ms); $postarr['post_modified'] = get_date_from_gmt($postarr['post_modified_gmt']); }
            }
        }
        $new_id = wp_insert_post($postarr, true);
        if (is_wp_error($new_id) || !$new_id) return 0;
        $post_id = $new_id;

        // Mark mapping
        update_post_meta($post_id, '_cphub_remote_id', $remote_id);
        if (!empty($item['modified'])) update_post_meta($post_id, '_cphub_remote_modified', (string)$item['modified']);

        // Featured image
        if (!empty($item['thumb'])) {
            $att_id = $this->sideload_media((string)$item['thumb'], $post_id);
            if ($att_id) set_post_thumbnail($post_id, $att_id);
        }

        // Copy meta (including media helpers). Store scalar values directly.
        if (!empty($item['meta']) && is_array($item['meta'])) {
            foreach ($item['meta'] as $k => $v) {
                if ($k === '' || strpos($k, '_') === 0) continue; // avoid protected keys here
                if (is_scalar($v)) update_post_meta($post_id, $k, (string)$v);
            }
            // Sideload any meta with _url companions
            foreach ($item['meta'] as $k => $v) {
                if (substr($k, -4) === '_url' && is_string($v) && $v !== '') {
                    $base_key = substr($k, 0, -4);
                    $att = $this->sideload_media((string)$v, $post_id);
                    if ($att) {
                        update_post_meta($post_id, $base_key . '_id', (string)$att);
                        $url = wp_get_attachment_url($att);
                        if ($url) update_post_meta($post_id, $base_key . '_url', (string)$url);
                        $mime = get_post_mime_type($att);
                        if ($mime) update_post_meta($post_id, $base_key . '_mime', (string)$mime);
                    }
                }
            }
        }

        return $post_id;
    }

    private function find_local_post_id($cpt, $remote_id)
    {
        $q = new WP_Query([
            'post_type' => $cpt,
            'post_status' => 'any',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_query' => [[
                'key' => '_cphub_remote_id',
                'value' => (string)$remote_id,
                'compare' => '=',
            ]],
        ]);
        if ($q->have_posts()) {
            $ids = $q->posts;
            return $ids ? (int)$ids[0] : 0;
        }
        return 0;
    }

    private function sideload_media($url, $post_id = 0)
    {
        $url = esc_url_raw(trim((string)$url));
        if ($url === '') return 0;
        // Check by source URL to avoid duplicate downloads
        $existing = get_posts([
            'post_type' => 'attachment',
            'posts_per_page' => 1,
            'post_status' => 'inherit',
            'meta_query' => [[ 'key' => '_cphub_source_url', 'value' => $url, 'compare' => '=' ]],
            'fields' => 'ids',
        ]);
        if (!empty($existing)) {
            $aid = (int)$existing[0];
            if ($post_id) wp_update_post(['ID'=>$aid,'post_parent'=>$post_id]);
            return $aid;
        }
        if (!function_exists('download_url')) require_once ABSPATH . 'wp-admin/includes/file.php';
        if (!function_exists('wp_handle_sideload')) require_once ABSPATH . 'wp-admin/includes/file.php';
        if (!function_exists('wp_insert_attachment')) require_once ABSPATH . 'wp-admin/includes/image.php';
        $tmp = download_url($url, 30);
        if (is_wp_error($tmp)) return 0;
        $file_array = [
            'name' => basename(parse_url($url, PHP_URL_PATH) ?: 'remote-file'),
            'tmp_name' => $tmp,
        ];
        $overrides = ['test_form' => false];
        $sideload = wp_handle_sideload($file_array, $overrides);
        if (isset($sideload['error'])) {
            @unlink($tmp);
            return 0;
        }
        $file = $sideload['file'];
        $type = $sideload['type'];
        $title = sanitize_file_name(pathinfo($file, PATHINFO_FILENAME));
        $attachment = [
            'post_mime_type' => $type,
            'post_title'     => $title,
            'post_content'   => '',
            'post_status'    => 'inherit',
            'post_parent'    => $post_id,
        ];
        $attach_id = wp_insert_attachment($attachment, $file, $post_id);
        if (!is_wp_error($attach_id)) {
            $attach_data = wp_generate_attachment_metadata($attach_id, $file);
            wp_update_attachment_metadata($attach_id, $attach_data);
            update_post_meta($attach_id, '_cphub_source_url', $url);
            return (int)$attach_id;
        }
        return 0;
    }

    private function render_card(array $item, $cpt)
    {
        $assets = get_option(self::OPT_CACHE_ASSETS, []);
        $entry  = isset($assets[$cpt]) && is_array($assets[$cpt]) ? $assets[$cpt] : [];
        $layout = isset($entry['layout']) && is_array($entry['layout']) ? $entry['layout'] : [];
        $enabled = isset($layout['enabled']) && is_array($layout['enabled']) ? $layout['enabled'] : [];
        $order   = isset($layout['order']) && is_array($layout['order']) ? $layout['order'] : ['image','title','excerpt','content','meta1','meta2','meta3','button'];
        $meta_keys = isset($layout['meta_keys']) && is_array($layout['meta_keys']) ? $layout['meta_keys'] : ['meta1'=>'','meta2'=>'','meta3'=>''];
        $meta_wrap = isset($layout['meta_wrap']) && is_array($layout['meta_wrap']) ? $layout['meta_wrap'] : ['meta1'=>'content','meta2'=>'content','meta3'=>'content'];

        $assets_css = isset($entry['css']) ? (string)$entry['css'] : '';
        $use_overlay_btn = ($assets_css && strpos($assets_css, '.cphub-btn.has-hover') !== false);

        $thumb_html = '';
        $content_html = '';

        $local_link = '';
        $local_thumb = '';
        $s = get_option(self::OPT_SETTINGS, []);
        $local_id = 0;
        if (!empty($s['save_local'])) {
            $local_id = $this->find_local_post_id($cpt, (string)($item['id'] ?? ''));
            if ($local_id) {
                $perma = get_permalink($local_id);
                if ($perma) $local_link = $perma;
                $turl = get_the_post_thumbnail_url($local_id, 'full');
                if ($turl) $local_thumb = $turl;
            }
        }

        foreach ($order as $el) {
            if ($el === 'title') {
                if (!empty($enabled['title']) && !empty($item['title'])) {
                    $href = $local_link !== '' ? $local_link : (string)$item['link'];
                    $content_html .= '<h3 class="cphub-title"><a href="' . esc_url($href) . '">' . esc_html($item['title']) . '</a></h3>';
                }
            } elseif ($el === 'image') {
                if (!empty($enabled['image']) && !empty($item['thumb'])) {
                    $src = $local_thumb !== '' ? $local_thumb : (string)$item['thumb'];
                    $thumb_html .= '<img class="cphub-img" src="' . esc_url($src) . '" alt="" />';
                }
            } elseif ($el === 'excerpt') {
                if (!empty($enabled['excerpt']) && !empty($item['excerpt'])) {
                    $content_html .= '<div class="cphub-excerpt">' . wp_kses_post($item['excerpt']) . '</div>';
                }
            } elseif ($el === 'content') {
                if (!empty($enabled['content']) && !empty($item['content'])) {
                    $content_html .= '<div class="cphub-content">' . wp_kses_post($item['content']) . '</div>';
                }
            } elseif (in_array($el, ['meta1','meta2','meta3'], true)) {
                if (!empty($enabled[$el])) {
                    $key = isset($meta_keys[$el]) ? $meta_keys[$el] : '';
                    if ($key !== '' && isset($item['meta']) && isset($item['meta'][$key])) {
                        $html = '';
                        $url = isset($item['meta'][$key . '_url']) ? $item['meta'][$key . '_url'] : '';
                        $mime= isset($item['meta'][$key . '_mime']) ? $item['meta'][$key . '_mime'] : '';
                        if ($url) {
                            if (is_string($mime) && strpos($mime, 'image/') === 0) {
                                $html = '<div class="cphub-meta"><img class="cphub-meta-media" src="' . esc_url($url) . '" alt="" /></div>';
                            } else {
                                $html = '<div class="cphub-meta"><a class="cphub-meta-file" href="' . esc_url($url) . '" target="_blank" rel="noopener">Download</a></div>';
                            }
                        } else {
                            $is_html = isset($layout['meta_html']) && !empty($layout['meta_html'][$el]);
                            if ($is_html) {
                                $html_val = (string)$item['meta'][$key];
                                // Autop to ensure paragraphs are wrapped; then sanitize
                                $html = '<div class="cphub-meta">' . wp_kses_post(wpautop($html_val)) . '</div>';
                            } else {
                                $html = '<div class="cphub-meta">' . esc_html((string)$item['meta'][$key]) . '</div>';
                            }
                        }
                        if (($meta_wrap[$el] ?? 'content') === 'thumb') {
                            $thumb_html .= $html;
                        } else {
                            $content_html .= $html;
                        }
                    }
                }
            } elseif ($el === 'button') {
                if (!empty($enabled['button'])) {
                    $href = $local_link !== '' ? $local_link : (string)$item['link'];
                    if ($use_overlay_btn) {
                        $content_html .= '<a class="cphub-btn has-hover" href="' . esc_url($href) . '">' .
                                         '<span class="cphub-btn-inner"><span class="cphub-btn-base"><span class="cphub-btn-text">Read More</span></span></span>' .
                                         '<span class="cphub-btn-hover"></span>' .
                                         '</a>';
                    } else {
                        $content_html .= '<a class="cphub-btn" href="' . esc_url($href) . '">Read More</a>';
                    }
                }
            }
        }

        ob_start();
        echo '<div class="cphub-card">';
        echo '<div class="cphub-thumb-wrap">' . $thumb_html . '</div>';
        echo '<div class="cphub-content-wrap">' . $content_html . '</div>';
        echo '</div>';
        return ob_get_clean();
    }

    /* ================= Location Shortcode ================= */
    private function get_known_locations_map()
    {
        // Mirror of Publisher known locations for friendly labels
        return [
            'merrymaidsoshawa' => 'Oshawa, Whitby, Pickering, Ajax (Durham)',
            'merrymaidspeterborough' => 'Peterborough and Lindsay',
            'merrymaidsbarrie' => 'Barrie',
            'merrymaidstorontowest' => 'Toronto West (Former Etobicoke)',
            'merrymaidstoronto' => 'Toronto',
            'merrymaidsbrampton' => 'Brampton',
            'merrymaidsburnaby' => 'Burnaby, New Westminster and Tri-Cities',
            'merrymaidsvancouver' => 'Vancouver',
            'merrymaidscalgarynse' => 'Calgary NSE',
            'merrymaidscalgarysw' => 'Calgary SW',
            'merrymaidskwc' => 'KWC (Kitchener, Waterloo, Cambridge)',
            'merrymaidsguelph' => 'Guelph',
            'merrymaidssurrey' => 'Surrey, Delta, Langley, White Rock',
            'merrymaidshamilton' => 'Hamilton and Stoney Creek',
            'merrymaidsoakville' => 'Oakville',
            'merrymaidsmilton' => 'Milton and Georgetown',
            'merrymaidsburlington' => 'Burlington',
            'merrymaidsmississauga' => 'Mississauga',
            'merrymaidsorangeville' => 'Orangeville',
            'merrymaidskingston' => 'Kingston',
            'merrymaidslethbridge' => 'Lethbridge',
            'merrymaidslondon' => 'London',
            'merrymaidsuxbridge' => 'Uxbridge and Markham',
            'merrymaidshalifax' => 'Metro (Halifax)',
            'merrymaidsnorthvancouver' => 'North & West Vancouver',
            'merrymaidsottawa' => 'Ottawa',
            'merrymaidsottawawest' => 'Ottawa West',
            'merrymaidsregina' => 'Regina',
            'merrymaidswinnipeg' => 'Winnipeg',
            'merrymaidsrichmondhill' => 'Richmond Hill and Vaughan',
            'merrymaidssaskatoon' => 'Saskatoon',
            'merrymaidsscarborough' => 'Scarborough',
            'merrymaidsbelleville' => 'Belleville and Trenton',
            'merrymaidsniagara' => 'St. Catharines, Niagara',
            'home-office' => 'Home Office',
        ];
    }

    private function get_location_label($slug)
    {
        $slug = sanitize_key((string)$slug);
        if ($slug === '') return '';
        $map = $this->get_known_locations_map();
        if (isset($map[$slug])) return $map[$slug];
        // Fallback: humanize slug
        $label = str_replace(['-', '_'], ' ', $slug);
        return ucwords($label);
    }

    public function sc_location($atts)
    {
        $a = shortcode_atts([
            'slug' => '', // optional override; defaults to settings location
        ], $atts, 'cphub_location');
        $slug = sanitize_key($a['slug']);
        if ($slug === '') {
            $s = get_option(self::OPT_SETTINGS, []);
            $slug = isset($s['location']) ? sanitize_key((string)$s['location']) : '';
        }
        if ($slug === '') return '';
        return esc_html($this->get_location_label($slug));
    }

    public function sc_list($atts)
    {
        $a = shortcode_atts([
            'cpt' => '',
            'n' => 10,
            'paged' => 1,
        ], $atts, 'cphub_list');
        $cpt = sanitize_key($a['cpt']);
        if (!$cpt) return '';
        // Ensure CSS is enqueued at render time so timing is correct
        $this->enqueue_style_for_cpt($cpt);
        $items = $this->get_cached_items($cpt);
        if (!$items) return '';
        $this->mark_need_style($cpt);
        $n = max(1, intval($a['n']));
        $paged = max(1, intval($a['paged']));
        $offset = ($paged - 1) * $n;
        $slice = array_slice($items, $offset, $n);
        $layout_type = $this->get_layout_type($cpt);
        $wrap_class = $layout_type === 'grid' ? 'cphub-grid' : 'cphub-list';
        ob_start();
        echo '<div class="' . esc_attr($wrap_class) . '">';
        foreach ($slice as $it) {
            echo $this->render_card($it, $cpt);
        }
        echo '</div>';
        return ob_get_clean();
    }

    public function sc_item($atts)
    {
        $a = shortcode_atts([
            'cpt' => '',
            'id'  => '',
        ], $atts, 'cphub_item');
        $cpt = sanitize_key($a['cpt']);
        $id  = (string)($a['id']);
        if (!$cpt || $id === '') return '';
        // Ensure CSS is enqueued at render time so timing is correct
        $this->enqueue_style_for_cpt($cpt);
        $items = $this->get_cached_items($cpt);
        if (!$items) return '';
        foreach ($items as $it) {
            if ((string)($it['id'] ?? '') === $id) {
                $this->mark_need_style($cpt);
                return $this->render_card($it, $cpt);
            }
        }
        return '';
    }
}

new CPT_Hub_Consumer();
