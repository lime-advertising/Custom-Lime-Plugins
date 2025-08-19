<?php

/**
 * Plugin Name: MM Slides Consumer
 * Description: Pulls remote slides via RSS and renders a Simple Slider compatible markup.
 * Version: 1.2.0
 */

if (!defined('ABSPATH')) exit;

final class MM_Slides_Consumer
{
    const OPT_FEED_URL = 'mm_remote_slides_feed';
    const OPT_CACHE    = 'mm_remote_slides_cache';
    const CRON_HOOK    = 'mm_fetch_remote_slides';

    public function __construct()
    {
        add_action('admin_menu',        [$this, 'admin_menu']);
        add_action('admin_init',        [$this, 'register_settings']);

        add_action('update_option_' . self::OPT_FEED_URL, [$this, 'on_feed_url_updated'], 10, 2);

        add_action('init',              [$this, 'maybe_schedule']);
        add_action(self::CRON_HOOK,     [$this, 'fetch_and_store']);

        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_shortcode('mm_remote_slider', [$this, 'shortcode_slider']);
    }

    public function admin_menu()
    {
        add_options_page('MM Remote Slides', 'MM Remote Slides', 'manage_options', 'mm-remote-slides', [$this, 'settings_page']);
    }

    public function register_settings()
    {
        register_setting('mm_remote_slides', self::OPT_FEED_URL, ['type' => 'string', 'sanitize_callback' => 'esc_url_raw']);
    }

    public function settings_page()
    {
?>
        <div class="wrap">
            <h1>MM Remote Slides</h1>
            <form method="post" action="options.php">
                <?php settings_fields('mm_remote_slides'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="feed">Feed URL</label></th>
                        <td>
                            <input name="<?php echo esc_attr(self::OPT_FEED_URL); ?>"
                                id="feed" type="url" class="regular-text"
                                value="<?php echo esc_attr(get_option(self::OPT_FEED_URL)); ?>"
                                placeholder="https://MASTER-SITE.tld/feed/mm-slides?location=barrie">
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <form method="post" style="margin-top:1rem">
                <?php wp_nonce_field('mm_force_fetch', 'mm_force_fetch_nonce'); ?>
                <p><button class="button button-primary" name="mm_force_fetch" value="1">Fetch Now</button></p>
            </form>
        </div>
        <?php
        if (!empty($_POST['mm_force_fetch']) && check_admin_referer('mm_force_fetch', 'mm_force_fetch_nonce')) {
            $this->fetch_and_store();
            echo '<div class="updated"><p>Fetched.</p></div>';
        }
    }

    public function maybe_schedule()
    {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 120, 'hourly', self::CRON_HOOK);
        }
    }

    public function on_feed_url_updated($old, $new)
    {
        if ($old !== $new) {
            $this->fetch_and_store();
        }
    }

    public function fetch_and_store()
    {
        $url = get_option(self::OPT_FEED_URL);
        if (!$url) return;

        $req_url = add_query_arg('_mm_nocache', time(), $url);
        $res = wp_remote_get($req_url, [
            'timeout' => 15,
            'headers' => ['User-Agent' => 'MM-Slides-Consumer/1.2 (+wordpress)'],
        ]);
        if (is_wp_error($res)) {
            error_log('MM Slides fetch error: ' . $res->get_error_message());
            return;
        }

        $code = wp_remote_retrieve_response_code($res);
        if ($code !== 200) {
            error_log('MM Slides fetch HTTP ' . $code);
            return;
        }

        $body = wp_remote_retrieve_body($res);
        if (!$body) {
            error_log('MM Slides fetch empty body');
            return;
        }

        $xml = @simplexml_load_string($body);
        if (!$xml || !$xml->channel) {
            error_log('MM Slides invalid feed');
            return;
        }

        $ns = $xml->getNamespaces(true);

        // Global vars at channel level
        $vars = [];
        if (isset($ns['mm'])) {
            $ch = $xml->channel->children($ns['mm']);
            if (isset($ch->vars)) {
                $json = (string) $ch->vars;
                $vars = json_decode($json, true) ?: [];
            }
        }

        $slides = [];
        if ($xml->channel->item) {
            foreach ($xml->channel->item as $item) {
                $title = (string)$item->title;
                $desc  = (string)$item->description;
                $data  = [];
                if (isset($ns['mm'])) {
                    $mm = $item->children($ns['mm']);
                    if (isset($mm->data)) {
                        $json = (string)$mm->data;
                        $data = json_decode($json, true) ?: [];
                    }
                }
                $slides[] = [
                    'title'    => $title,
                    'desc'     => $desc,
                    'subtitle' => $data['subtitle'] ?? '',
                    'active'   => $data['active'] ?? 'no',
                    'bg'       => $data['bg_image'] ?? '',
                    'url1'     => ['text' => $data['btn1_text'] ?? '', 'url' => $data['btn1_url'] ?? ''],
                    'url2'     => ['text' => $data['btn2_text'] ?? '', 'url' => $data['btn2_url'] ?? ''],
                ];
            }
        }

        update_option(self::OPT_CACHE, [
            'updated' => current_time('timestamp'),
            'slides'  => $slides,
            'vars'    => $vars
        ], false);
    }

    /* Assets */
    public function enqueue_assets()
    {
        $this->ensure_script('gsap', 'https://cdn.jsdelivr.net/npm/gsap@3/dist/gsap.min.js', [], '3');
        $this->ensure_script('touchSwipe', 'https://cdn.jsdelivr.net/npm/jquery-touchswipe@1.6.19/jquery.touchswipe.min.js', ['jquery'], '1.6.19');
        $this->ensure_script('splitting', 'https://cdn.jsdelivr.net/npm/splitting@1.0.6/dist/splitting.min.js', [], '1.0.6');

        $cache = get_option(self::OPT_CACHE);
        $vars  = $cache['vars'] ?? [];
        $base  = isset($vars['mm-assets-base']) ? rtrim($vars['mm-assets-base'], '/') : '';
        $ver   = $vars['mm-assets-ver'] ?? '';

        $use_remote = !empty($base); // allow override
        $use_remote = apply_filters('mm_use_remote_assets', $use_remote);

        if ($use_remote) {
            $js  = $base . '/mm-slider.js'  . ($ver ? '?v=' . rawurlencode($ver) : '');
            $css = $base . '/mm-slider.css' . ($ver ? '?v=' . rawurlencode($ver) : '');

            // Dequeue any theme slider to avoid doubles
            if ($theme = $this->find_registered_script_handle()) {
                wp_dequeue_script($theme);
                wp_deregister_script($theme);
            }

            $init_handle = 'mm-slider';

            wp_enqueue_script('mm-slider', $js, ['jquery', 'gsap', 'touchSwipe', 'splitting'], null, true);
            wp_enqueue_style('mm-slider', $css, [], null);
        } else {
            $local_js_path = plugin_dir_path(__FILE__) . 'assets/mm-slider.js';
            $local_css_path = plugin_dir_path(__FILE__) . 'assets/mm-slider.css';

            // Always prefer local if it exists
            if (file_exists($local_js_path)) {
                // If the theme registered a slider, remove it to avoid duplicates
                if ($theme = $this->find_registered_script_handle()) {
                    wp_dequeue_script($theme);
                    wp_deregister_script($theme);
                }
                if ($theme_css = $this->find_registered_style_handle()) {
                    // optional: keep theme CSS, or dequeue it and use your own CSS
                    // wp_dequeue_style($theme_css);
                    // wp_deregister_style($theme_css);
                }

                // Enqueue your fixed slider
                wp_enqueue_script(
                    'mm-slider',
                    plugins_url('assets/mm-slider.js', __FILE__),
                    ['jquery', 'gsap', 'touchSwipe', 'splitting'],
                    '1.0.1',
                    true
                );
                $init_handle = 'mm-slider';

                // Use your CSS if present (you can keep the theme CSS instead if you prefer)
                if (file_exists($local_css_path)) {
                    wp_enqueue_style('mm-slider', plugins_url('assets/mm-slider.css', __FILE__), [], '1.0.0');
                }
            } else {
                // Fallback to theme’s assets
                if ($theme_css = $this->find_registered_style_handle()) wp_enqueue_style($theme_css);
                if ($theme_js  = $this->find_registered_script_handle()) {
                    wp_enqueue_script($theme_js);
                    $init_handle = $theme_js;
                } else {
                    return; // neither local nor theme: nothing to init
                }
            }

            $init_handle = isset($init_handle) ? $init_handle : 'mm-slider';
        }
        // init for both paths:
        if (!empty($init_handle)) {
            wp_add_inline_script($init_handle, "jQuery(function($){ $('.master-slider').masterSlider(); });");
        }
    }


    private function ensure_script($handle, $src, $deps = [], $ver = null)
    {
        if (!wp_script_is($handle, 'registered') && !wp_script_is($handle, 'enqueued')) {
            wp_register_script($handle, $src, $deps, $ver, true);
        }
        wp_enqueue_script($handle);
    }

    private function find_registered_script_handle()
    {
        global $wp_scripts;
        if (!$wp_scripts || empty($wp_scripts->registered)) return null;
        foreach ($wp_scripts->registered as $handle => $obj) {
            $hay = strtolower($handle . ' ' . ($obj->src ?? ''));
            if (strpos($hay, 'slider') !== false && (strpos($hay, 'master') !== false || strpos($hay, 'mae') !== false || strpos($hay, 'qondri') !== false)) {
                return $handle;
            }
        }
        foreach ($wp_scripts->registered as $handle => $obj) {
            $hay = strtolower($handle . ' ' . ($obj->src ?? ''));
            if (strpos($hay, 'slider') !== false) return $handle;
        }
        return null;
    }

    private function find_registered_style_handle()
    {
        global $wp_styles;
        if (!$wp_styles || empty($wp_styles->registered)) return null;
        foreach ($wp_styles->registered as $handle => $obj) {
            $hay = strtolower($handle . ' ' . ($obj->src ?? ''));
            if (strpos($hay, 'slider') !== false && (strpos($hay, 'master') !== false || strpos($hay, 'mae') !== false || strpos($hay, 'qondri') !== false)) {
                return $handle;
            }
        }
        foreach ($wp_styles->registered as $handle => $obj) {
            $hay = strtolower($handle . ' ' . ($obj->src ?? ''));
            if (strpos($hay, 'slider') !== false) return $handle;
        }
        return null;
    }

    private function inline_vars_style($vars)
    {
        if (empty($vars) || !is_array($vars)) return '';
        $pairs = [];
        foreach ($vars as $k => $v) {
            if (strpos($k, '--') === 0) {
                // allow safe CSS chars incl quotes/underscore/semicolon/colon
                $v = preg_replace('/[^a-zA-Z0-9#.%\s,\-()\/:;_\'"]/', '', (string)$v);
                $pairs[] = $k . ':' . trim($v);
            }
        }
        return implode(';', $pairs);
    }


    public function shortcode_slider($atts)
    {
        $atts = shortcode_atts([
            'autoplay' => 'yes',
            'speed'    => 9000,
            'style'    => 'full-width',
            'debug'    => '0',
        ], $atts);

        $cache  = get_option(self::OPT_CACHE);
        if (empty($cache['slides'])) {
            $this->fetch_and_store();
            $cache = get_option(self::OPT_CACHE);
        }
        $slides = $cache['slides'] ?? [];
        $vars   = $cache['vars'] ?? [];

        if (!$slides) {
            return $atts['debug'] === '1'
                ? '<pre>mm_remote_slider: no slides cached. Feed=' . esc_html(get_option(self::OPT_FEED_URL)) . '</pre>'
                : '<!-- mm_remote_slider: no slides -->';
        }

        $config = [
            'bgEffIn'          => ['eff' => 'reveal2'],
            'bgEffOut'         => ['eff' => 'reveal2'],
            'subEffIn'         => ['eff' => 'none'],
            'subEffOut'        => ['eff' => 'none'],
            'titleEffIn'       => ['eff' => 'none'],
            'titleEffOut'      => ['eff' => 'none'],
            'descEffIn'        => ['eff' => 'none'],
            'descEffOut'       => ['eff' => 'none'],
            'url1EffIn'        => ['eff' => 'none'],
            'url1EffOut'       => ['eff' => 'none'],
            'url2EffIn'        => ['eff' => 'none'],
            'url2EffOut'       => ['eff' => 'none'],
            'wrapEffIn'        => ['eff' => 'fadeLeft', 'prop' => ['duration' => '0.5', 'delay' => '0.7']],
            'wrapEffOut'       => ['eff' => 'fadeLeft', 'prop' => ['duration' => '0.5']],
            'autoplay'         => $atts['autoplay'],
            'autoplaySpeed'    => (int) $atts['speed'],
            'kenburns'         => 'yes',
            'kenburnsZoom'     => 1.1,
            'kenburnsDuration' => 5000,
        ];

        $preset = isset($vars['mm-anim']) ? strtolower(trim($vars['mm-anim'])) : 'reveal';

        if ($preset === 'fade') {
            $fade = ['eff' => 'fade'];

            // Backgrounds
            $config['bgEffIn']  = $fade;
            $config['bgEffOut'] = $fade;

            // Whole slide wrapper
            $config['wrapEffIn']  = $fade;
            $config['wrapEffOut'] = $fade;

            // Text + buttons
            foreach (['sub', 'title', 'desc'] as $k) {
                $config[$k . 'EffIn']  = $fade;
                $config[$k . 'EffOut'] = $fade;
            }
            foreach (['url1', 'url2'] as $k) {
                $config[$k . 'EffIn']  = $fade;
                $config[$k . 'EffOut'] = $fade;
            }

            // Optional: if you prefer to keep text static on fade, comment the loops above.
        }

        $style_inline = $this->inline_vars_style($vars);

        ob_start(); ?>
        <div class="slider-<?php echo esc_attr($atts['style']); ?>">
            <div class="master-slider" style="<?php echo esc_attr($style_inline); ?>"
                data-config='<?php echo esc_attr(wp_json_encode($config)); ?>'>
                <div class="bg-wrap">
                    <?php $foundActive = false;
                    foreach ($slides as $s):
                        $active = ($s['active'] === 'yes' && !$foundActive) ? ' active' : '';
                        if ($active) $foundActive = true; ?>
                        <div class="bg<?php echo esc_attr($active); ?>" style="<?php echo $s['bg'] ? 'background-image:url(' . esc_url($s['bg']) . ');' : ''; ?>"></div>
                    <?php endforeach; ?>
                    <div class="mm-overlay"></div>
                </div>

                <div class="content-wrap">
                    <?php $foundActive = false;
                    foreach ($slides as $s):
                        $active = ($s['active'] === 'yes' && !$foundActive) ? ' active' : '';
                        if ($active) $foundActive = true; ?>
                        <div class="slide<?php echo esc_attr($active); ?>">
                            <?php if (!empty($s['subtitle'])): ?><div class="sub-title"><?php echo esc_html($s['subtitle']); ?></div><?php endif; ?>
                            <?php if (!empty($s['title'])): ?><h1 class="title"><?php echo esc_html($s['title']); ?></h1><?php endif; ?>
                            <?php if (!empty($s['desc'])): ?><div class="desc"><?php echo wp_kses_post($s['desc']); ?></div><?php endif; ?>
                            <div class="url-wrap">
                                <?php if (!empty($s['url1']['url'])): ?>
                                    <div class="slide-url url1"><a class="master-button big" href="<?php echo esc_url($s['url1']['url']); ?>"><span class="inner"><span class="content-base"><?php echo esc_html($s['url1']['text'] ?: 'Learn more'); ?></span></span><span class="bg-hover"></span></a></div>
                                <?php endif; ?>
                                <?php if (!empty($s['url2']['url'])): ?>
                                    <div class="slide-url url2"><a class="master-link" href="<?php echo esc_url($s['url2']['url']); ?>"><?php echo esc_html($s['url2']['text'] ?: 'More'); ?></a></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="control-wrap">
                    <div class="nav-arrow">
                        <div class="arrow arrow-prev"></div>
                        <div class="arrow arrow-next"></div>
                    </div>
                    <div class="nav-dots"></div>
                </div>
            </div>
        </div>
<?php
        return ob_get_clean();
    }
}

new MM_Slides_Consumer();
