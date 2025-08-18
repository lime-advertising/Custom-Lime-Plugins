<?php

/**
 * Plugin Name: MM Slides Publisher
 * Description: Central slide manager + RSS feed for remote consumption (with tabbed global style settings).
 * Version: 1.4.0
 */

if (!defined('ABSPATH')) exit;

final class MM_Slides_Publisher
{
    const CPT       = 'mm_slide';
    const NS        = 'mm';               // RSS XML namespace
    const OPT_STYLE = 'mm_slides_style';  // stores global styles

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
        <p><em>Use the main editor for the description. Use Featured Image for the background.</em></p>
    <?php
    }

    public function save_meta($post_id)
    {
        if (!isset($_POST['mm_slide_nonce']) || !wp_verify_nonce($_POST['mm_slide_nonce'], 'mm_slide_fields')) return;
        foreach (['mm_subtitle', 'mm_active', 'mm_btn1_text', 'mm_btn1_url', 'mm_btn2_text', 'mm_btn2_url'] as $f) {
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

        // WP color picker + alpha support
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        // lightweight alpha addon
        wp_register_script('wp-color-picker-alpha', 'https://cdn.jsdelivr.net/npm/wp-color-picker-alpha@3.0.0/dist/wp-color-picker-alpha.min.js', ['wp-color-picker'], '3.0.0', true);
        wp_enqueue_script('wp-color-picker-alpha');

        wp_add_inline_style('wp-admin', '
            .mm-tabs{margin-top:20px}
            .mm-tabs .nav-tab-wrapper{margin-bottom:0}
            .mm-tab-panel{display:none;background:#fff;padding:16px 20px;border:1px solid #c3c4c7;border-top:0}
            .mm-tab-panel.active{display:block}
            .mm-two{display:grid;grid-template-columns:1fr 1fr;gap:14px}
            .mm-field small{opacity:.75}
            @media (max-width:900px){.mm-two{grid-template-columns:1fr}}
        ');
        wp_add_inline_script('jquery-core', "
            jQuery(function($){
                // tabs
                const key='mmSlidesSettingsTab';
                function showTab(id){
                    $('.mm-tab-panel').removeClass('active'); $(id).addClass('active');
                    $('.mm-tabs .nav-tab').removeClass('nav-tab-active');
                    $('.mm-tabs .nav-tab[data-target=\"'+id+'\"]').addClass('nav-tab-active');
                    try{localStorage.setItem(key,id);}catch(e){}
                }
                $('.mm-tabs .nav-tab').on('click', function(e){ e.preventDefault(); showTab($(this).data('target')); });
                showTab(localStorage.getItem(key) || '#mm-general');

                // color pickers (with alpha)
                $('.mm-color').each(function(){
                    $(this).attr('data-alpha-enabled','true').wpColorPicker();
                });

                // font selectors: toggle custom input
                $('.mm-font-select').each(function(){
                    const select=$(this), custom=select.closest('.mm-field').find('.mm-font-custom');
                    function sync(){ (select.val()==='custom') ? custom.show() : custom.hide(); }
                    select.on('change', sync); sync();
                });
            });
        ");
    }

    /** Predefined fonts for selectors */
    private function font_choices()
    {
        return [
            'inherit' => 'Use Theme (inherit)',
            'system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif' => 'System UI',
            'Inter, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif' => 'Inter',
            'Roboto, system-ui, -apple-system, Segoe UI, Helvetica, Arial, sans-serif' => 'Roboto',
            'Open Sans, Helvetica, Arial, sans-serif' => 'Open Sans',
            'Lato, Helvetica, Arial, sans-serif' => 'Lato',
            'Montserrat, Helvetica, Arial, sans-serif' => 'Montserrat',
            'Poppins, Helvetica, Arial, sans-serif' => 'Poppins',
            'Merriweather, Georgia, serif' => 'Merriweather',
            'Playfair Display, Georgia, serif' => 'Playfair Display',
            'Georgia, serif' => 'Georgia',
            'Times New Roman, Times, serif' => 'Times New Roman',
            'Arial, Helvetica, sans-serif' => 'Arial',
            'custom' => 'Custom…',
        ];
    }

    /** Field groups and defaults */
    private function field_groups()
    {
        return [
            'general' => [
                'slider_height' => ['Slider Height (e.g. 700px or 90vh)', '700px'],
                'overlay'       => ['Overlay Color (supports alpha)', 'rgba(0,0,0,0)'],
                'align_v'       => ['Vertical Align (flex-start|center|flex-end)', 'center'],
                'align_h'       => ['Horizontal Align (flex-start|center|flex-end)', 'flex-start'],
                'text_align'    => ['Text Align (left|center|right)', 'left'],
                'content_pad'   => ['Content Padding (CSS shorthand)', '0 0 0 0'],
                'slider_height_md' => ['Slider Height (≤1024px)', ''],
                'slider_height_sm' => ['Slider Height (≤640px)', ''],
                'content_pad_md'   => ['Content Padding (≤1024px)', ''],
                'content_pad_sm'   => ['Content Padding (≤640px)', ''],
                'text_align_md'    => ['Text Align (≤1024px)', ''],
                'text_align_sm'    => ['Text Align (≤640px)', ''],
                'align_v_md'       => ['Vertical Align (≤1024px)', ''],
                'align_v_sm'       => ['Vertical Align (≤640px)', ''],
                'align_h_md'       => ['Horizontal Align (≤1024px)', ''],
                'align_h_sm'       => ['Horizontal Align (≤640px)', ''],
            ],
            'title' => [
                'title_color'     => ['Title Color', '#ffffff'],
                'title_font'      => ['Title Font Family', 'inherit'],
                'title_size'      => ['Title Size', '90px'],
                'title_line'      => ['Title Line-height', '1'],
                'title_weight'    => ['Title Weight', '800'],
                'title_tracking'  => ['Title Letter-spacing', '-1.6px'],
                'title_margin'    => ['Title Margin', '0 0 30px 0'],
                'title_max'       => ['Title Max-width', 'none'],
                'title_size_md'     => ['Title Size (≤1024px)', ''],
                'title_size_sm'     => ['Title Size (≤640px)', ''],
                'title_line_md'     => ['Title Line-height (≤1024px)', ''],
                'title_line_sm'     => ['Title Line-height (≤640px)', ''],
                'title_margin_md'   => ['Title Margin (≤1024px)', ''],
                'title_margin_sm'   => ['Title Margin (≤640px)', ''],
                'title_max_md'      => ['Title Max-width (≤1024px)', ''],
                'title_max_sm'      => ['Title Max-width (≤640px)', ''],
                'title_tracking_md' => ['Title Letter-spacing (≤1024px)', ''],
                'title_tracking_sm' => ['Title Letter-spacing (≤640px)', ''],
            ],
            'subtitle' => [
                'sub_color'   => ['Subtitle Color', '#ffffff'],
                'sub_font'    => ['Subtitle Font Family', 'inherit'],
                'sub_size'    => ['Subtitle Size', '20px'],
                'sub_line'    => ['Subtitle Line-height', '36px'],
                'sub_weight'  => ['Subtitle Weight', '500'],
                'sub_margin'  => ['Subtitle Margin', '0 0 12px 0'],
                'sub_size_md'   => ['Subtitle Size (≤1024px)', ''],
                'sub_size_sm'   => ['Subtitle Size (≤640px)', ''],
                'sub_line_md'   => ['Subtitle Line-height (≤1024px)', ''],
                'sub_line_sm'   => ['Subtitle Line-height (≤640px)', ''],
                'sub_margin_md' => ['Subtitle Margin (≤1024px)', ''],
                'sub_margin_sm' => ['Subtitle Margin (≤640px)', ''],
                'sub_max'       => ['Subtitle Max-width', 'none'],
                'sub_max_md'    => ['Subtitle Max-width (≤1024px)', ''],
                'sub_max_sm'    => ['Subtitle Max-width (≤640px)', ''],
            ],
            'description' => [
                'desc_color'  => ['Description Color', '#ffffff'],
                'desc_font'   => ['Description Font Family', 'inherit'],
                'desc_size'   => ['Description Size', '20px'],
                'desc_line'   => ['Description Line-height', '40px'],
                'desc_weight' => ['Description Weight', '600'],
                'desc_margin' => ['Description Margin', '0 0 35px 0'],
                'desc_max'    => ['Description Max-width', 'none'],
                'desc_size_md'   => ['Description Size (≤1024px)', ''],
                'desc_size_sm'   => ['Description Size (≤640px)', ''],
                'desc_line_md'   => ['Description Line-height (≤1024px)', ''],
                'desc_line_sm'   => ['Description Line-height (≤640px)', ''],
                'desc_margin_md' => ['Description Margin (≤1024px)', ''],
                'desc_margin_sm' => ['Description Margin (≤640px)', ''],
                'desc_max_md'    => ['Description Max-width (≤1024px)', ''],
                'desc_max_sm'    => ['Description Max-width (≤640px)', ''],
            ],
            // Button 1 (primary)
            'button1' => [
                'btn1_bg'           => ['Button 1 BG', 'var(--e-global-color-qondri_accent)'],
                'btn1_text'         => ['Button 1 Text', '#ffffff'],
                'btn1_border'       => ['Button 1 Border', '#ffffff'],
                'btn1_radius'       => ['Button 1 Radius', '0'],
                'btn1_pad'          => ['Button 1 Padding', '12px 24px'],
                'btn1_shadow'       => ['Button 1 Box-Shadow', 'none'],
                'btn1_bg_hover'     => ['Button 1 BG (Hover)', 'var(--mm-btn1-bg)'],
                'btn1_text_hover'   => ['Button 1 Text (Hover)', 'var(--mm-btn1-text)'],
                'btn1_border_hover' => ['Button 1 Border (Hover)', 'var(--mm-btn1-border)'],
                'btn1_shadow_hover' => ['Button 1 Box-Shadow (Hover)', 'var(--mm-btn1-shadow)'],
                'btn1_pad_md' => ['Button 1 Padding (≤1024px)', ''],
                'btn1_pad_sm' => ['Button 1 Padding (≤640px)', ''],
            ],
            // Button 2 (secondary/link)
            'button2' => [
                'btn2_bg'           => ['Button 2 BG', 'transparent'],
                'btn2_text'         => ['Button 2 Text', '#ffffff'],
                'btn2_border'       => ['Button 2 Border', '#ffffff'],
                'btn2_radius'       => ['Button 2 Radius', '0'],
                'btn2_pad'          => ['Button 2 Padding', '0'],
                'btn2_shadow'       => ['Button 2 Box-Shadow', 'none'],
                'btn2_bg_hover'     => ['Button 2 BG (Hover)', 'var(--mm-btn2-bg)'],
                'btn2_text_hover'   => ['Button 2 Text (Hover)', 'var(--mm-btn2-text)'],
                'btn2_border_hover' => ['Button 2 Border (Hover)', 'var(--mm-btn2-border)'],
                'btn2_shadow_hover' => ['Button 2 Box-Shadow (Hover)', 'var(--mm-btn2-shadow)'],
                'btn2_pad_md' => ['Button 2 Padding (≤1024px)', ''],
                'btn2_pad_sm' => ['Button 2 Padding (≤640px)', ''],
            ],
        ];
    }

    /** Helper: is this a color-like key? */
    private function is_color_key($key)
    {
        return (bool) preg_match('/(color|_bg$|_bg_hover$|_text$|_text_hover$|_border$|_border_hover$|^overlay$)/', $key);
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

                // If this is a font selector and the choice is "custom",
                // replace it with the custom value (or fallback to inherit).
                if (preg_match('/_font$/', $key)) {
                    if ($val === 'custom') {
                        $custom_key = $key . '_custom';
                        if (!empty($input[$custom_key])) {
                            $val = (string)$input[$custom_key];
                        } else {
                            $val = 'inherit';
                        }
                    }
                }

                // allow safe CSS characters (incl quotes, semicolons for safety)
                $val = preg_replace('/[^a-zA-Z0-9#.%\s,\-()\/:;_\[\]\'"\+\*]/', '', $val);
                $out[$key] = trim($val);
            }
        }
        return $out;
    }

    /** Map options -> CSS custom properties */
    private function option_to_css_vars(array $opt)
    {
        // sensible defaults
        $def = [
            '--mm-slider-height'  => '700px',
            '--mm-overlay'        => 'rgba(0,0,0,0)',
            '--mm-align-v'        => 'center',
            '--mm-align-h'        => 'flex-start',
            '--mm-text-align'     => 'left',
            '--mm-content-pad'    => '0 0 0 0',

            '--mm-sub-color'      => '#fff',
            '--mm-sub-font'       => 'inherit',
            '--mm-sub-size'       => '20px',
            '--mm-sub-line'       => '36px',
            '--mm-sub-weight'     => '500',
            '--mm-sub-margin'     => '0 0 12px 0',

            '--mm-title-color'    => '#fff',
            '--mm-title-font'     => 'inherit',
            '--mm-title-size'     => '90px',
            '--mm-title-line'     => '1',
            '--mm-title-weight'   => '800',
            '--mm-title-tracking' => '-1.6px',
            '--mm-title-margin'   => '0 0 30px 0',
            '--mm-title-max'      => 'none',

            '--mm-desc-color'     => '#fff',
            '--mm-desc-font'      => 'inherit',
            '--mm-desc-size'      => '20px',
            '--mm-desc-line'      => '40px',
            '--mm-desc-weight'    => '600',
            '--mm-desc-margin'    => '0 0 35px 0',
            '--mm-desc-max'       => 'none',

            // Button 1 (also mirrored to legacy --mm-btn-* for back-compat)
            '--mm-btn1-bg'         => 'var(--e-global-color-qondri_accent)',
            '--mm-btn1-text'       => '#fff',
            '--mm-btn1-border'     => '#fff',
            '--mm-btn1-radius'     => '0',
            '--mm-btn1-pad'        => '12px 24px',
            '--mm-btn1-shadow'     => 'none',
            '--mm-btn1-bg-hover'   => 'var(--mm-btn1-bg)',
            '--mm-btn1-text-hover' => 'var(--mm-btn1-text)',
            '--mm-btn1-border-hover' => 'var(--mm-btn1-border)',
            '--mm-btn1-shadow-hover' => 'var(--mm-btn1-shadow)',

            // Button 2
            '--mm-btn2-bg'         => 'transparent',
            '--mm-btn2-text'       => '#fff',
            '--mm-btn2-border'     => '#fff',
            '--mm-btn2-radius'     => '0',
            '--mm-btn2-pad'        => '0',
            '--mm-btn2-shadow'     => 'none',
            '--mm-btn2-bg-hover'   => 'var(--mm-btn2-bg)',
            '--mm-btn2-text-hover' => 'var(--mm-btn2-text)',
            '--mm-btn2-border-hover' => 'var(--mm-btn2-border)',
            '--mm-btn2-shadow-hover' => 'var(--mm-btn2-shadow)',
        ];

        // map option keys to CSS vars
        $map = [
            'slider_height'  => '--mm-slider-height',
            'overlay'        => '--mm-overlay',
            'align_v'        => '--mm-align-v',
            'align_h'        => '--mm-align-h',
            'text_align'     => '--mm-text-align',
            'content_pad'    => '--mm-content-pad',
            // General responsive
            'slider_height_md' => '--mm-slider-height-md',
            'slider_height_sm' => '--mm-slider-height-sm',
            'content_pad_md'   => '--mm-content-pad-md',
            'content_pad_sm'   => '--mm-content-pad-sm',
            'text_align_md'    => '--mm-text-align-md',
            'text_align_sm'    => '--mm-text-align-sm',
            'align_v_md'       => '--mm-align-v-md',
            'align_v_sm'       => '--mm-align-v-sm',
            'align_h_md'       => '--mm-align-h-md',
            'align_h_sm'       => '--mm-align-h-sm',

            'sub_color'      => '--mm-sub-color',
            'sub_font'       => '--mm-sub-font',
            'sub_size'       => '--mm-sub-size',
            'sub_line'       => '--mm-sub-line',
            'sub_weight'     => '--mm-sub-weight',
            'sub_margin'     => '--mm-sub-margin',
            // Subtitle responsive
            'sub_size_md'      => '--mm-sub-size-md',
            'sub_size_sm'      => '--mm-sub-size-sm',
            'sub_line_md'      => '--mm-sub-line-md',
            'sub_line_sm'      => '--mm-sub-line-sm',
            'sub_margin_md'    => '--mm-sub-margin-md',
            'sub_margin_sm'    => '--mm-sub-margin-sm',
            'sub_max_md'       => '--mm-sub-max-md',
            'sub_max_sm'       => '--mm-sub-max-sm',

            'title_color'    => '--mm-title-color',
            'title_font'     => '--mm-title-font',
            'title_size'     => '--mm-title-size',
            'title_line'     => '--mm-title-line',
            'title_weight'   => '--mm-title-weight',
            'title_tracking' => '--mm-title-tracking',
            'title_margin'   => '--mm-title-margin',
            'title_max'      => '--mm-title-max',
            // Title responsive
            'title_size_md'    => '--mm-title-size-md',
            'title_size_sm'    => '--mm-title-size-sm',
            'title_line_md'    => '--mm-title-line-md',
            'title_line_sm'    => '--mm-title-line-sm',
            'title_margin_md'  => '--mm-title-margin-md',
            'title_margin_sm'  => '--mm-title-margin-sm',
            'title_max_md'     => '--mm-title-max-md',
            'title_max_sm'     => '--mm-title-max-sm',
            'title_tracking_md' => '--mm-title-tracking-md',
            'title_tracking_sm' => '--mm-title-tracking-sm',

            'desc_color'     => '--mm-desc-color',
            'desc_font'      => '--mm-desc-font',
            'desc_size'      => '--mm-desc-size',
            'desc_line'      => '--mm-desc-line',
            'desc_weight'    => '--mm-desc-weight',
            'desc_margin'    => '--mm-desc-margin',
            'desc_max'       => '--mm-desc-max',
            // Description responsive
            'desc_size_md'     => '--mm-desc-size-md',
            'desc_size_sm'     => '--mm-desc-size-sm',
            'desc_line_md'     => '--mm-desc-line-md',
            'desc_line_sm'     => '--mm-desc-line-sm',
            'desc_margin_md'   => '--mm-desc-margin-md',
            'desc_margin_sm'   => '--mm-desc-margin-sm',
            'desc_max_md'      => '--mm-desc-max-md',
            'desc_max_sm'      => '--mm-desc-max-sm',

            'btn1_bg'           => '--mm-btn1-bg',
            'btn1_text'         => '--mm-btn1-text',
            'btn1_border'       => '--mm-btn1-border',
            'btn1_radius'       => '--mm-btn1-radius',
            'btn1_pad'          => '--mm-btn1-pad',
            'btn1_shadow'       => '--mm-btn1-shadow',
            'btn1_bg_hover'     => '--mm-btn1-bg-hover',
            'btn1_text_hover'   => '--mm-btn1-text-hover',
            'btn1_border_hover' => '--mm-btn1-border-hover',
            'btn1_shadow_hover' => '--mm-btn1-shadow-hover',
            // Button 1 responsive
            'btn1_pad_md'      => '--mm-btn1-pad-md',
            'btn1_pad_sm'      => '--mm-btn1-pad-sm',

            'btn2_bg'           => '--mm-btn2-bg',
            'btn2_text'         => '--mm-btn2-text',
            'btn2_border'       => '--mm-btn2-border',
            'btn2_radius'       => '--mm-btn2-radius',
            'btn2_pad'          => '--mm-btn2-pad',
            'btn2_shadow'       => '--mm-btn2-shadow',
            'btn2_bg_hover'     => '--mm-btn2-bg-hover',
            'btn2_text_hover'   => '--mm-btn2-text-hover',
            'btn2_border_hover' => '--mm-btn2-border-hover',
            'btn2_shadow_hover' => '--mm-btn2-shadow-hover',
            // Button 2 responsive
            'btn2_pad_md'      => '--mm-btn2-pad-md',
            'btn2_pad_sm'      => '--mm-btn2-pad-sm',
        ];

        foreach ($map as $k => $var) {
            if (!empty($opt[$k]) || $opt[$k] === '0') {
                $def[$var] = $opt[$k];
            }
        }   

        // Back-compat: mirror Button 1 into legacy --mm-btn-* vars
        $def['--mm-btn-bg']           = $def['--mm-btn1-bg'];
        $def['--mm-btn-text']         = $def['--mm-btn1-text'];
        $def['--mm-btn-border']       = $def['--mm-btn1-border'];
        $def['--mm-btn-radius']       = $def['--mm-btn1-radius'];
        $def['--mm-btn-pad']          = $def['--mm-btn1-pad'];
        $def['--mm-btn-bg-hover']     = $def['--mm-btn1-bg-hover'];
        $def['--mm-btn-text-hover']   = $def['--mm-btn1-text-hover'];
        $def['--mm-btn-border-hover'] = $def['--mm-btn1-border-hover'];

        return $def;
    }

    /** Render input helper */
    private function render_input($key, $label, $value, $is_color = false, $is_font = false)
    {
        $id = esc_attr($key);
        echo '<p class="mm-field">';
        echo '<label for="' . $id . '"><strong>' . esc_html($label) . '</strong></label><br>';

        if ($is_font) {
            $choices = $this->font_choices();

            // If saved value is not one of the presets, show Custom selected
            $is_custom    = ($value !== '' && !array_key_exists($value, $choices));
            $select_value = $is_custom ? 'custom' : ($value !== '' ? $value : 'inherit');
            $custom_val   = $is_custom ? $value : '';

            echo '<select id="' . $id . '" name="' . esc_attr(self::OPT_STYLE . "[$key]") . '" class="mm-font-select">';
            foreach ($choices as $stack => $text) {
                $sel = selected($select_value, $stack, false);
                echo '<option value="' . esc_attr($stack) . '" ' . $sel . '>' . esc_html($text) . '</option>';
            }
            echo '</select>';

            // Custom free-text stack (only shown when "Custom…" is selected)
            echo '<input type="text" placeholder="e.g. &quot;Alexandria&quot;, Arial, sans-serif" class="regular-text mm-font-custom" style="margin-top:6px;display:none" name="' . esc_attr(self::OPT_STYLE . "[$key]") . '_custom" value="' . esc_attr($custom_val) . '">';
            echo '<small>Note: this does not load webfonts; ensure the family is available on your site.</small>';
        } else {
            $cls = $is_color ? 'mm-color regular-text' : 'regular-text';
            echo '<input type="text" id="' . $id . '" name="' . esc_attr(self::OPT_STYLE . "[$key]") . '" value="' . esc_attr($value) . '" class="' . $cls . '"' . ($is_color ? ' data-alpha-enabled="true"' : '') . '>';
        }

        echo '</p>';
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
                    <a href="#" class="nav-tab" data-target="#mm-button1">Button 1</a>
                    <a href="#" class="nav-tab" data-target="#mm-button2">Button 2</a>
                </h2>

                <form method="post" action="options.php">
                    <?php settings_fields('mm_slides_style_group'); ?>

                    <!-- General -->
                    <div id="mm-general" class="mm-tab-panel">
                        <div class="mm-two">
                            <?php foreach ($groups['general'] as $key => $meta):
                                $val = $opt[$key] ?? $meta[1];
                                $this->render_input($key, $meta[0], $val, $this->is_color_key($key), false);
                            endforeach; ?>
                        </div>
                    </div>

                    <!-- Title -->
                    <div id="mm-title" class="mm-tab-panel">
                        <div class="mm-two">
                            <?php foreach ($groups['title'] as $key => $meta):
                                $val = $opt[$key] ?? $meta[1];
                                $this->render_input($key, $meta[0], $val, $this->is_color_key($key), $key === 'title_font');
                            endforeach; ?>
                        </div>
                    </div>

                    <!-- Subtitle -->
                    <div id="mm-subtitle" class="mm-tab-panel">
                        <div class="mm-two">
                            <?php foreach ($groups['subtitle'] as $key => $meta):
                                $val = $opt[$key] ?? $meta[1];
                                $this->render_input($key, $meta[0], $val, $this->is_color_key($key), $key === 'sub_font');
                            endforeach; ?>
                        </div>
                    </div>

                    <!-- Description -->
                    <div id="mm-desc" class="mm-tab-panel">
                        <div class="mm-two">
                            <?php foreach ($groups['description'] as $key => $meta):
                                $val = $opt[$key] ?? $meta[1];
                                $this->render_input($key, $meta[0], $val, $this->is_color_key($key), $key === 'desc_font');
                            endforeach; ?>
                        </div>
                    </div>

                    <!-- Button 1 -->
                    <div id="mm-button1" class="mm-tab-panel">
                        <div class="mm-two">
                            <?php foreach ($groups['button1'] as $key => $meta):
                                $val = $opt[$key] ?? $meta[1];
                                $this->render_input($key, $meta[0], $val, $this->is_color_key($key), false);
                            endforeach; ?>
                        </div>
                    </div>

                    <!-- Button 2 -->
                    <div id="mm-button2" class="mm-tab-panel">
                        <div class="mm-two">
                            <?php foreach ($groups['button2'] as $key => $meta):
                                $val = $opt[$key] ?? $meta[1];
                                $this->render_input($key, $meta[0], $val, $this->is_color_key($key), false);
                            endforeach; ?>
                        </div>
                    </div>

                    <?php submit_button('Save Styles'); ?>
                </form>
            </div>

            <p><em>Values are exported in the feed as CSS variables and applied on consumer sites.</em></p>
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
        $q = new \WP_Query($args);

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
