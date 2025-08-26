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
    const CRON_HOOK = 'cphub_consumer_cron_refresh';
    private static $needs_styles = [];

    public function __construct()
    {
        add_action('admin_menu',          [$this, 'admin_menu']);
        add_action('admin_init',          [$this, 'register_settings']);
        add_action('admin_post_cphub_consumer_refresh', [$this, 'handle_manual_refresh']);
        add_action('admin_post_cphub_consumer_clear',   [$this, 'handle_clear_cache']);
        add_action('admin_post_cphub_consumer_health',  [$this, 'handle_check_health']);
        add_action('wp_enqueue_scripts',  [$this, 'enqueue_styles']);
        add_shortcode('cphub_list',       [$this, 'sc_list']);
        add_shortcode('cphub_item',       [$this, 'sc_item']);

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
    }

    public static function deactivate()
    {
        $ts = wp_next_scheduled(self::CRON_HOOK);
        if ($ts) wp_unschedule_event($ts, self::CRON_HOOK);
    }

    public function add_cron_schedules($schedules)
    {
        if (!isset($schedules['cphub_10min'])) {
            $schedules['cphub_10min'] = [ 'interval' => 600, 'display' => __('Every 10 minutes', 'cphub') ];
        }
        return $schedules;
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

          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="card" style="padding:1em;max-width:900px;margin-top:12px;">
            <?php wp_nonce_field('cphub_consumer_clear'); ?>
            <input type="hidden" name="action" value="cphub_consumer_clear">
            <button class="button button-secondary">Clear Cache</button>
            <span class="description">Removes local items and assets caches for all CPTs.</span>
          </form>

          <h2 style="margin-top:1.5em;">Cache Status</h2>
          <table class="widefat striped" style="max-width:1100px;">
            <thead><tr><th>CPT</th><th>Items</th><th>ETag</th><th>Last-Modified</th><th>Updated</th><th>Last Status</th><th>Last Error</th><th>Assets ver</th><th>Assets updated</th><th>Assets Status</th></tr></thead>
            <tbody>
              <?php $rows = array_unique(array_merge(array_keys((array)$cache_items), array_keys((array)$cache_assets), $enabled));
              if (!$rows) echo '<tr><td colspan="7">No cache yet.</td></tr>';
              foreach ($rows as $slug):
                $ci = $cache_items[$slug] ?? [];
                $ca = $cache_assets[$slug] ?? [];
              ?>
                <tr>
                  <td><code><?php echo esc_html($slug); ?></code></td>
                  <td><?php echo isset($ci['items']) && is_array($ci['items']) ? count($ci['items']) : 0; ?></td>
                  <td style="word-break:break-all;">&quot;<?php echo esc_html(trim((string)($ci['etag'] ?? ''), '"')); ?>&quot;</td>
                  <td><?php echo esc_html($ci['last_modified'] ?? ''); ?></td>
                  <td><?php echo !empty($ci['updated']) ? esc_html(date('Y-m-d H:i', (int)$ci['updated'])) : ''; ?></td>
                  <td><?php echo isset($ci['last_status']) ? (int)$ci['last_status'] : ''; ?></td>
                  <td style="max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo esc_attr(substr((string)($ci['last_error'] ?? ''),0,500)); ?>"><?php echo esc_html(substr((string)($ci['last_error'] ?? ''),0,60)); ?></td>
                  <td><?php echo esc_html($ca['version'] ?? ''); ?></td>
                  <td><?php echo !empty($ca['updated']) ? esc_html(date('Y-m-d H:i', (int)$ca['updated'])) : ''; ?></td>
                  <td><?php echo isset($ca['last_status']) ? (int)$ca['last_status'] : ''; ?></td>
                </tr>
              <?php endforeach; ?>
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
    }

    public function handle_clear_cache()
    {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        check_admin_referer('cphub_consumer_clear');
        delete_option(self::OPT_CACHE_ITEMS);
        delete_option(self::OPT_CACHE_ASSETS);
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
    private function refresh_all()
    {
        $s = get_option(self::OPT_SETTINGS, []);
        $enabled = (array)($s['enabled_cpts'] ?? []);
        if (!$enabled) return;
        foreach ($enabled as $slug) {
            $this->fetch_items($slug);
            $this->fetch_assets($slug);
        }
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
            $entry['last_error'] = wp_remote_retrieve_body($res);
            $entry['updated']    = time();
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
        $cache[$cpt] = $entry;
        update_option(self::OPT_CACHE_ITEMS, $cache, false);
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

        $headers = [];
        if (!empty($entry['etag'])) $headers['If-None-Match'] = $entry['etag'];
        if (!empty($entry['last_modified'])) $headers['If-Modified-Since'] = $entry['last_modified'];

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
            $entry['last_error'] = wp_remote_retrieve_body($res);
            $entry['updated']    = time();
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
        if (isset($data['layout_type']) && $data['layout_type'] === 'grid') {
            $entry['layout_type'] = 'grid';
        } else {
            // Fallback detection from CSS if layout_type missing
            $entry['layout_type'] = (strpos($entry['css'], '.cphub-grid{display:grid') !== false) ? 'grid' : 'list';
        }
        $entry['updated'] = time();
        $cache[$cpt] = $entry;
        update_option(self::OPT_CACHE_ASSETS, $cache, false);
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

    public function enqueue_styles()
    {
        $s = get_option(self::OPT_SETTINGS, []);
        if (empty($s['use_styles'])) return;
        $assets = get_option(self::OPT_CACHE_ASSETS, []);
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

        foreach ($order as $el) {
            if ($el === 'title') {
                if (!empty($enabled['title']) && !empty($item['title'])) {
                    $content_html .= '<h3 class="cphub-title"><a href="' . esc_url($item['link']) . '">' . esc_html($item['title']) . '</a></h3>';
                }
            } elseif ($el === 'image') {
                if (!empty($enabled['image']) && !empty($item['thumb'])) {
                    $thumb_html .= '<img class="cphub-img" src="' . esc_url($item['thumb']) . '" alt="" />';
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
                            $html = '<div class="cphub-meta">' . esc_html((string)$item['meta'][$key]) . '</div>';
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
                    if ($use_overlay_btn) {
                        $content_html .= '<a class="cphub-btn has-hover" href="' . esc_url($item['link']) . '">' .
                                         '<span class="cphub-btn-inner"><span class="cphub-btn-base"><span class="cphub-btn-text">Read More</span></span></span>' .
                                         '<span class="cphub-btn-hover"></span>' .
                                         '</a>';
                    } else {
                        $content_html .= '<a class="cphub-btn" href="' . esc_url($item['link']) . '">Read More</a>';
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
