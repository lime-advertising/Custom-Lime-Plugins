<?php

/**
 * Plugin Name: MM Slides Publisher
 * Description: Central slide manager + RSS feed for remote consumption (with tabbed global style settings).
 * Version: 1.3.0
 */

if (!defined('ABSPATH')) exit;

final class MM_Slides_Publisher
{
    const CPT       = 'mm_slide';
    const NS        = 'mm';                 // RSS XML namespace
    const OPT_STYLE = 'mm_slides_style';    // stores global styles

    public function __construct()
    {
        add_action('init', [$this, 'register_cpt']);
        add_action('init', [$this, 'register_tax']);
        add_action('init', function () {
            add_feed('mm-slides', [$this, 'render_feed']);
        });
        add_action('add_meta_boxes', [$this, 'meta_boxes']);
        add_action('save_post_' . self::CPT, [$this, 'save_meta']);

        // Settings UI
        add_action('admin_menu',  [$this, 'add_settings_submenu']);
        add_action('admin_init',  [$this, 'register_style_settings']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
    }

    /* ---------------- CPT & Tax ---------------- */

    public function register_cpt()
    {
        register_post_type(self::CPT, [
            'label' => 'MM Slides',
            'public' => true,
            'show_in_rest' => true,
            'menu_icon' => 'dashicons-images-alt2',
            'supports' => ['title', 'editor', 'thumbnail', 'custom-fields'],
        ]);
    }

    public function register_tax()
    {
        register_taxonomy('mm_location', self::CPT, [
            'label' => 'MM Locations',
            'public' => true,
            'hierarchical' => false,
            'show_in_rest' => true,
        ]);
    }

    public function meta_boxes()
    {
        add_meta_box('mm_slide_fields', 'Slide Details', [$this, 'box_html'], self::CPT, 'normal', 'high');
    }

    public function box_html($post)
    {
        wp_nonce_field('mm_slide_fields', 'mm_slide_nonce');
        $get = function ($k, $d = '') use ($post) {
            return get_post_meta($post->ID, $k, true) ?: $d;
        };
?>
        <style>
            .mm-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 12px
            }
        </style>
        <div class="mm-grid">
            <p><label>Subtitle<br>
                    <input type="text" name="mm_subtitle" class="widefat" value="<?php echo esc_attr($get('mm_subtitle')); ?>"></label></p>
            <p><label>Active?<br>
                    <select name="mm_active">
                        <option value="yes" <?php selected($get('mm_active'), 'yes'); ?>>Yes</option>
                        <option value="no" <?php selected($get('mm_active'), 'no');  ?>>No</option>
                    </select></label></p>
            <p><label>Button 1 Text<br><input type="text" name="mm_btn1_text" class="widefat" value="<?php echo esc_attr($get('mm_btn1_text')); ?>"></label></p>
            <p><label>Button 1 URL<br><input type="url" name="mm_btn1_url" class="widefat" value="<?php echo esc_attr($get('mm_btn1_url')); ?>"></label></p>
            <p><label>Button 2 Text<br><input type="text" name="mm_btn2_text" class="widefat" value="<?php echo esc_attr($get('mm_btn2_text')); ?>"></label></p>
            <p><label>Button 2 URL<br><input type="url" name="mm_btn2_url" class="widefat" value="<?php echo esc_attr($get('mm_btn2_url')); ?>"></label></p>
        </div>
        <p><em>Use the main editor for the slide description. Use Featured Image for the slide background.</em></p>
    <?php
    }

    public function save_meta($post_id)
    {
        if (!isset($_POST['mm_slide_nonce']) || !wp_verify_nonce($_POST['mm_slide_nonce'], 'mm_slide_fields')) return;
        $fields = ['mm_subtitle', 'mm_active', 'mm_btn1_text', 'mm_btn1_url', 'mm_btn2_text', 'mm_btn2_url'];
        foreach ($fields as $f) {
            if (isset($_POST[$f])) update_post_meta($post_id, $f, sanitize_text_field($_POST[$f]));
        }
    }

    /* ---------------- Settings (Tabbed) ---------------- */

    public function add_settings_submenu()
    {
        add_submenu_page(
            'edit.php?post_type=' . self::CPT,
            'MM Slides - Settings',
            'Settings',
            'manage_options',
            'mm-slides-settings',
            [$this, 'settings_page']
        );
    }

    public function register_style_settings()
    {
        register_setting('mm_slides_style_group', self::OPT_STYLE, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitize_style_option'],
            'default'           => []
        ]);
    }

    public function admin_assets($hook)
    {
        if ($hook !== 'mm_slide_page_mm-slides-settings') return;
        wp_add_inline_style('wp-admin', '
            .mm-tabs { margin-top: 20px; }
            .mm-tabs .nav-tab-wrapper { margin-bottom: 0; }
            .mm-tab-panel { display:none; background:#fff; padding:16px 20px; border:1px solid #c3c4c7; border-top:0; }
            .mm-tab-panel.active { display:block; }
            .mm-two { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
            @media (max-width: 900px){ .mm-two{ grid-template-columns:1fr; } }
        ');
        wp_add_inline_script('jquery-core', "
            jQuery(function($){
                const key='mmSlidesSettingsTab';
                function showTab(id){
                    $('.mm-tab-panel').removeClass('active');
                    $(id).addClass('active');
                    $('.mm-tabs .nav-tab').removeClass('nav-tab-active');
                    $('.mm-tabs .nav-tab[data-target=\"'+id+'\"]').addClass('nav-tab-active');
                    try{ localStorage.setItem(key,id); }catch(e){}
                }
                $('.mm-tabs .nav-tab').on('click', function(e){
                    e.preventDefault(); showTab($(this).data('target'));
                });
                let saved=null; try{ saved=localStorage.getItem(key); }catch(e){}
                showTab(saved || '#mm-general');
            });
        ");
    }

    /** Field groups and defaults */
    private function field_groups()
    {
        return [
            'general' => [
                'slider_height' => ['Slider Height (e.g. 700px or 90vh)', '700px'],
                'overlay'       => ['Overlay (CSS color with alpha, e.g. rgba(0,0,0,.35))', 'rgba(0,0,0,0)'],
                'align_v'       => ['Vertical Align (flex-start|center|flex-end)', 'center'],
                'align_h'       => ['Horizontal Align (flex-start|center|flex-end)', 'flex-start'],
                'text_align'    => ['Text Align (left|center|right)', 'left'],
                'content_pad'   => ['Content Padding (CSS shorthand)', '0 0 0 0'],
            ],
            'title' => [
                'title_color'     => ['Title Color', '#ffffff'],
                'title_size'      => ['Title Size', '90px'],
                'title_line'      => ['Title Line-height', '1'],
                'title_weight'    => ['Title Weight', '800'],
                'title_tracking'  => ['Title Letter-spacing', '-1.6px'],
                'title_margin'    => ['Title Margin', '0 0 30px 0'],
                'title_max'       => ['Title Max-width', 'none'],
            ],
            'subtitle' => [
                'sub_color'   => ['Subtitle Color', '#ffffff'],
                'sub_size'    => ['Subtitle Size', '20px'],
                'sub_line'    => ['Subtitle Line-height', '36px'],
                'sub_weight'  => ['Subtitle Weight', '500'],
                'sub_margin'  => ['Subtitle Margin', '0 0 12px 0'],
            ],
            'description' => [
                'desc_color'  => ['Description Color', '#ffffff'],
                'desc_size'   => ['Description Size', '20px'],
                'desc_line'   => ['Description Line-height', '40px'],
                'desc_weight' => ['Description Weight', '600'],
                'desc_margin' => ['Description Margin', '0 0 35px 0'],
                'desc_max'    => ['Description Max-width', 'none'],
            ],
            'buttons' => [
                'btn_bg'           => ['Button BG', 'transparent'],
                'btn_text'         => ['Button Text Color', '#ffffff'],
                'btn_border'       => ['Button Border Color', '#ffffff'],
                'btn_radius'       => ['Button Border Radius', '0'],
                'btn_pad'          => ['Button Padding', '12px 24px'],
                'btn_bg_hover'     => ['Button BG (Hover)', 'var(--mm-btn-bg)'],
                'btn_text_hover'   => ['Button Text (Hover)', 'var(--mm-btn-text)'],
                'btn_border_hover' => ['Button Border (Hover)', 'var(--mm-btn-border)'],
            ],
        ];
    }

    /** Make available to WP as sanitize callback */
    public function sanitize_style_option($input)
    {
        $groups = $this->field_groups();
        $out = [];
        foreach ($groups as $fields) {
            foreach ($fields as $key => $_meta) {
                if (!isset($input[$key])) continue;
                $val = (string)$input[$key];
                // allow safe CSS chars
                $val = preg_replace('/[^a-zA-Z0-9#.%\s,\-()\/:rgbaRGBAVar\[\]]/', '', $val);
                $out[$key] = trim($val);
            }
        }
        return $out;
    }

    /** Map options -> CSS custom properties */
    private function option_to_css_vars(array $opt)
    {
        $def = [
            '--mm-slider-height'  => '700px',
            '--mm-overlay'        => 'rgba(0,0,0,0)',
            '--mm-align-v'        => 'center',
            '--mm-align-h'        => 'flex-start',
            '--mm-text-align'     => 'left',
            '--mm-content-pad'    => '0 0 0 0',
            '--mm-sub-color'      => '#fff',
            '--mm-sub-size'       => '20px',
            '--mm-sub-line'       => '36px',
            '--mm-sub-weight'     => '500',
            '--mm-sub-margin'     => '0 0 12px 0',
            '--mm-title-color'    => '#fff',
            '--mm-title-size'     => '90px',
            '--mm-title-line'     => '1',
            '--mm-title-weight'   => '800',
            '--mm-title-tracking' => '-1.6px',
            '--mm-title-margin'   => '0 0 30px 0',
            '--mm-title-max'      => 'none',
            '--mm-desc-color'     => '#fff',
            '--mm-desc-size'      => '20px',
            '--mm-desc-line'      => '40px',
            '--mm-desc-weight'    => '600',
            '--mm-desc-margin'    => '0 0 35px 0',
            '--mm-desc-max'       => 'none',
            '--mm-btn-bg'         => 'var(--e-global-color-qondri_accent)',
            '--mm-btn-text'       => '#fff',
            '--mm-btn-border'     => '#fff',
            '--mm-btn-radius'     => '0',
            '--mm-btn-pad'        => '12px 24px',
            '--mm-btn-bg-hover'   => 'var(--mm-btn-bg)',
            '--mm-btn-text-hover' => 'var(--mm-btn-text)',
            '--mm-btn-border-hover' => 'var(--mm-btn-border)',
        ];

        $map = [
            'slider_height'  => '--mm-slider-height',
            'overlay'        => '--mm-overlay',
            'align_v'        => '--mm-align-v',
            'align_h'        => '--mm-align-h',
            'text_align'     => '--mm-text-align',
            'content_pad'    => '--mm-content-pad',

            'sub_color'      => '--mm-sub-color',
            'sub_size'       => '--mm-sub-size',
            'sub_line'       => '--mm-sub-line',
            'sub_weight'     => '--mm-sub-weight',
            'sub_margin'     => '--mm-sub-margin',

            'title_color'    => '--mm-title-color',
            'title_size'     => '--mm-title-size',
            'title_line'     => '--mm-title-line',
            'title_weight'   => '--mm-title-weight',
            'title_tracking' => '--mm-title-tracking',
            'title_margin'   => '--mm-title-margin',
            'title_max'      => '--mm-title-max',

            'desc_color'     => '--mm-desc-color',
            'desc_size'      => '--mm-desc-size',
            'desc_line'      => '--mm-desc-line',
            'desc_weight'    => '--mm-desc-weight',
            'desc_margin'    => '--mm-desc-margin',
            'desc_max'       => '--mm-desc-max',

            'btn_bg'           => '--mm-btn-bg',
            'btn_text'         => '--mm-btn-text',
            'btn_border'       => '--mm-btn-border',
            'btn_radius'       => '--mm-btn-radius',
            'btn_pad'          => '--mm-btn-pad',
            'btn_bg_hover'     => '--mm-btn-bg-hover',
            'btn_text_hover'   => '--mm-btn-text-hover',
            'btn_border_hover' => '--mm-btn-border-hover',
        ];

        foreach ($map as $k => $var) {
            if (!empty($opt[$k])) $def[$var] = $opt[$k];
        }
        return $def;
    }

    public function settings_page()
    {
        $groups = $this->field_groups();
        $opt    = get_option(self::OPT_STYLE, []);
    ?>
        <div class="wrap">
            <h1>MM Slides – Settings</h1>

            <div class="mm-tabs">
                <h2 class="nav-tab-wrapper">
                    <a href="#" class="nav-tab" data-target="#mm-general">General</a>
                    <a href="#" class="nav-tab" data-target="#mm-title">Title</a>
                    <a href="#" class="nav-tab" data-target="#mm-subtitle">Subtitle</a>
                    <a href="#" class="nav-tab" data-target="#mm-desc">Description</a>
                    <a href="#" class="nav-tab" data-target="#mm-buttons">Buttons</a>
                </h2>

                <form method="post" action="options.php">
                    <?php settings_fields('mm_slides_style_group'); ?>

                    <!-- General -->
                    <div id="mm-general" class="mm-tab-panel">
                        <div class="mm-two">
                            <?php foreach ($groups['general'] as $key => $meta): ?>
                                <p>
                                    <label for="<?php echo esc_attr($key); ?>"><strong><?php echo esc_html($meta[0]); ?></strong></label><br>
                                    <input type="text" id="<?php echo esc_attr($key); ?>"
                                        name="<?php echo esc_attr(self::OPT_STYLE . "[$key]"); ?>"
                                        value="<?php echo esc_attr($opt[$key] ?? $meta[1]); ?>" class="regular-text">
                                </p>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Title -->
                    <div id="mm-title" class="mm-tab-panel">
                        <div class="mm-two">
                            <?php foreach ($groups['title'] as $key => $meta): ?>
                                <p>
                                    <label for="<?php echo esc_attr($key); ?>"><strong><?php echo esc_html($meta[0]); ?></strong></label><br>
                                    <input type="text" id="<?php echo esc_attr($key); ?>"
                                        name="<?php echo esc_attr(self::OPT_STYLE . "[$key]"); ?>"
                                        value="<?php echo esc_attr($opt[$key] ?? $meta[1]); ?>" class="regular-text">
                                </p>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Subtitle -->
                    <div id="mm-subtitle" class="mm-tab-panel">
                        <div class="mm-two">
                            <?php foreach ($groups['subtitle'] as $key => $meta): ?>
                                <p>
                                    <label for="<?php echo esc_attr($key); ?>"><strong><?php echo esc_html($meta[0]); ?></strong></label><br>
                                    <input type="text" id="<?php echo esc_attr($key); ?>"
                                        name="<?php echo esc_attr(self::OPT_STYLE . "[$key]"); ?>"
                                        value="<?php echo esc_attr($opt[$key] ?? $meta[1]); ?>" class="regular-text">
                                </p>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Description -->
                    <div id="mm-desc" class="mm-tab-panel">
                        <div class="mm-two">
                            <?php foreach ($groups['description'] as $key => $meta): ?>
                                <p>
                                    <label for="<?php echo esc_attr($key); ?>"><strong><?php echo esc_html($meta[0]); ?></strong></label><br>
                                    <input type="text" id="<?php echo esc_attr($key); ?>"
                                        name="<?php echo esc_attr(self::OPT_STYLE . "[$key]"); ?>"
                                        value="<?php echo esc_attr($opt[$key] ?? $meta[1]); ?>" class="regular-text">
                                </p>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Buttons -->
                    <div id="mm-buttons" class="mm-tab-panel">
                        <div class="mm-two">
                            <?php foreach ($groups['buttons'] as $key => $meta): ?>
                                <p>
                                    <label for="<?php echo esc_attr($key); ?>"><strong><?php echo esc_html($meta[0]); ?></strong></label><br>
                                    <input type="text" id="<?php echo esc_attr($key); ?>"
                                        name="<?php echo esc_attr(self::OPT_STYLE . "[$key]"); ?>"
                                        value="<?php echo esc_attr($opt[$key] ?? $meta[1]); ?>" class="regular-text">
                                </p>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <?php submit_button('Save Styles'); ?>
                </form>
            </div>

            <p><em>These values are exported in the feed as CSS variables and applied on all consumer sites.</em></p>
        </div>
    <?php
    }

    /* ---------------- Feed ---------------- */

    public function render_feed()
    {
        header('Content-Type: application/rss+xml; charset=' . get_option('blog_charset'), true);

        $location = isset($_GET['location']) ? sanitize_text_field($_GET['location']) : '';
        $args = [
            'post_type'      => self::CPT,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'menu_order date',
            'order'          => 'ASC',
        ];
        if ($location) {
            $args['tax_query'] = [[
                'taxonomy' => 'mm_location',
                'field'    => 'slug',
                'terms'    => $location
            ]];
        }
        $q = new WP_Query($args);

        $vars = $this->option_to_css_vars(get_option(self::OPT_STYLE, []));

        echo '<?xml version="1.0" encoding="' . esc_attr(get_option('blog_charset')) . '"?>';
    ?>
        <rss version="2.0" xmlns:<?php echo self::NS; ?>="https://example.com/mm">
            <channel>
                <title><?php echo esc_html(get_bloginfo('name')); ?> - MM Slides</title>
                <link><?php echo esc_url(home_url('/')); ?></link>
                <description>Remote slides feed</description>
                <lastBuildDate><?php echo esc_html(mysql2date(DATE_RSS, current_time('mysql'))); ?></lastBuildDate>

                <<?php echo self::NS; ?>:vars>
                    <![CDATA[<?php echo wp_json_encode($vars); ?>]]>
                </<?php echo self::NS; ?>:vars>

                <?php while ($q->have_posts()): $q->the_post();
                    $id  = get_the_ID();
                    $img = get_the_post_thumbnail_url($id, 'full');
                    $meta = [
                        'subtitle'   => get_post_meta($id, 'mm_subtitle', true),
                        'active'     => get_post_meta($id, 'mm_active', true) ?: 'no',
                        'btn1_text'  => get_post_meta($id, 'mm_btn1_text', true),
                        'btn1_url'   => get_post_meta($id, 'mm_btn1_url', true),
                        'btn2_text'  => get_post_meta($id, 'mm_btn2_text', true),
                        'btn2_url'   => get_post_meta($id, 'mm_btn2_url', true),
                        'bg_image'   => $img,
                    ];
                ?>
                    <item>
                        <title><?php echo esc_html(get_the_title()); ?></title>
                        <link><?php the_permalink(); ?></link>
                        <guid isPermaLink="false"><?php echo esc_html($id . ':' . get_the_modified_time('U')); ?></guid>
                        <pubDate><?php echo esc_html(get_post_time(DATE_RSS)); ?></pubDate>
                        <description>
                            <![CDATA[<?php echo wp_kses_post(get_the_content()); ?>]]>
                        </description>
                        <<?php echo self::NS; ?>:data>
                            <![CDATA[<?php echo wp_json_encode($meta); ?>]]>
                        </<?php echo self::NS; ?>:data>
                    </item>
                <?php endwhile;
                wp_reset_postdata(); ?>
            </channel>
        </rss>
<?php
        exit;
    }
}

new MM_Slides_Publisher();
