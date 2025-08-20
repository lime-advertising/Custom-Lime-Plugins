<?php
if (!defined('ABSPATH')) exit;

class Lime_Schema_Admin
{
    public function hooks(): void
    {
        add_action('admin_init',        [$this, 'register_settings']);
        add_action('admin_menu',        [$this, 'add_settings_page']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('add_meta_boxes',    [$this, 'add_meta_box']);
        add_action('save_post',         [$this, 'save_post_meta'], 10, 2);
        add_action('wp_ajax_lime_schema_preview', [$this, 'ajax_preview']);
        // Author profile fields (sameAs)
        add_action('show_user_profile', [$this, 'render_user_fields']);
        add_action('edit_user_profile', [$this, 'render_user_fields']);
        add_action('personal_options_update', [$this, 'save_user_fields']);
        add_action('edit_user_profile_update', [$this, 'save_user_fields']);
        // Admin notice for SEO plugin detection
        add_action('admin_init',        [$this, 'handle_dismiss_seo_notice']);
        add_action('admin_notices',     [$this, 'seo_notice']);
    }

    public function admin_assets($hook)
    {
        $is_settings = ($hook === 'settings_page_' . LIME_SCHEMA_OPTION_KEY);
        $is_post_edit = ($hook === 'post.php' || $hook === 'post-new.php');
        if (!$is_settings && !$is_post_edit) return;

        $base_url  = plugin_dir_url(LIME_SCHEMA_FILE);
        $base_path = plugin_dir_path(LIME_SCHEMA_FILE);

        // Styles
        $css_rel = 'assets/css/admin.css';
        $css_ver = file_exists($base_path . $css_rel) ? filemtime($base_path . $css_rel) : LIME_SCHEMA_VERSION;
        wp_enqueue_style('lime-schema-admin', $base_url . $css_rel, [], $css_ver);

        // Scripts
        $js_rel = 'assets/js/admin.js';
        $js_ver = file_exists($base_path . $js_rel) ? filemtime($base_path . $js_rel) : LIME_SCHEMA_VERSION;
        wp_register_script('lime-schema-admin', $base_url . $js_rel, [], $js_ver, true);
        wp_localize_script('lime-schema-admin', 'LimeSchemaAdmin', [
            'optionKey' => LIME_SCHEMA_OPTION_KEY,
            'metaKey'   => LIME_SCHEMA_META_KEY,
            'rrtUrlBase' => 'https://search.google.com/test/rich-results',
            'ajaxNonce' => wp_create_nonce('lime_schema_preview'),
        ]);
        wp_enqueue_script('lime-schema-admin');
    }

    public function handle_dismiss_seo_notice(): void
    {
        if (!current_user_can('manage_options')) return;
        if (isset($_GET['lime_schema_dismiss_seo_notice']) && isset($_GET['_wpnonce'])) {
            $nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );
            if (wp_verify_nonce($nonce, 'lime_schema_dismiss_seo_notice')) {
                update_option('lime_schema_dismissed_seo_notice', 1);
            }
        }
    }

    public function seo_notice(): void
    {
        if (!current_user_can('manage_options')) return;
        $opts = get_option(LIME_SCHEMA_OPTION_KEY, []);
        if (empty($opts['auto_disable_with_seo'])) return;
        if (get_option('lime_schema_dismissed_seo_notice')) return;
        $det = Lime_Schema_Utils::detect_seo();
        if (empty($det['active'])) return;
        $which = $det['yoast'] ? 'Yoast SEO' : ($det['rankmath'] ? 'Rank Math' : 'SEO plugin');
        $dismiss_url = wp_nonce_url(add_query_arg('lime_schema_dismiss_seo_notice', '1'), 'lime_schema_dismiss_seo_notice');
        $settings_url = admin_url('options-general.php?page=' . LIME_SCHEMA_OPTION_KEY);
        echo '<div class="notice notice-info is-dismissible">'
            . '<p><strong>Lime Schema:</strong> ' . esc_html($which) . ' detected. To avoid duplicate structured data, Organization/WebSite/WebPage nodes are disabled by default. '
            . 'You can change this in <a href="' . esc_url($settings_url) . '">Settings</a>. '
            . '<a href="' . esc_url($dismiss_url) . '" style="margin-left:8px">Dismiss</a>'
            . '</p></div>';
    }

    /* Settings */
    public function register_settings()
    {
        register_setting(LIME_SCHEMA_OPTION_KEY, LIME_SCHEMA_OPTION_KEY, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitize_options'],
            'default'           => [
                'org_name'       => '',
                'site_url'       => '',
                'description'    => '',
                'logo'           => '',
                'images'         => '',
                'founding_date'  => '',
                'founder'        => '',
                'phone'          => '',
                'email'          => '',
                'languages'      => 'en-CA',
                'sameas'         => '',
                'enable_website' => 1,
                'search_target'  => '',
                'lb_type'        => 'LocalBusiness',
                'locations'      => [],
                'faqs'           => [],
                'locations_json' => '[]',
                // Feature toggles
                'disable_org'    => 0,
                'disable_website'=> 0,
                'disable_webpage'=> 0,
                'enable_article' => 0,
                'auto_disable_with_seo' => 1,
            ]
        ]);

        add_settings_section('lime_schema_tabs', '', '__return_false', LIME_SCHEMA_OPTION_KEY);
    }

    public function sanitize_options($raw)
    {
        $out = [];
        foreach (['org_name','site_url','description','logo','images','founding_date','founder','phone','email','languages','sameas','search_target','lb_type','locations_json'] as $k) {
            $out[$k] = isset($raw[$k]) ? wp_kses_post($raw[$k]) : '';
        }
        $out['enable_website'] = !empty($raw['enable_website']) ? 1 : 0;
        $out['disable_org']     = !empty($raw['disable_org']) ? 1 : 0;
        $out['disable_website'] = !empty($raw['disable_website']) ? 1 : 0;
        $out['disable_webpage'] = !empty($raw['disable_webpage']) ? 1 : 0;
        $out['enable_article']  = !empty($raw['enable_article']) ? 1 : 0;
        $out['auto_disable_with_seo'] = !empty($raw['auto_disable_with_seo']) ? 1 : 0;

        $allowed = Lime_Schema_Utils::allowed_lb_types();
        $out['lb_type'] = in_array($out['lb_type'], $allowed, true) ? $out['lb_type'] : 'LocalBusiness';

        // locations repeater
        $out['locations'] = [];
        if (!empty($raw['locations']) && is_array($raw['locations'])) {
            foreach ($raw['locations'] as $loc) {
                $L = [];
                $L['name']  = isset($loc['name']) ? sanitize_text_field($loc['name']) : '';
                $L['slug']  = isset($loc['slug']) ? sanitize_title($loc['slug']) : '';
                if (!$L['slug'] || strpos($L['slug'], 'http') === 0) {
                    $L['slug'] = sanitize_title($L['name'] ?: 'location');
                }
                $L['phone'] = isset($loc['phone']) ? sanitize_text_field($loc['phone']) : '';
                $L['email'] = isset($loc['email']) ? sanitize_email($loc['email']) : '';
                $L['priceRange'] = isset($loc['priceRange']) ? sanitize_text_field($loc['priceRange']) : '';

                $img_csv   = isset($loc['image_csv']) ? sanitize_text_field($loc['image_csv']) : '';
                $areas_csv = isset($loc['areas_csv']) ? sanitize_text_field($loc['areas_csv']) : '';
                $L['image'] = $img_csv ? array_values(array_filter(array_map('trim', explode(',', $img_csv)))) : [];
                $L['areaServed'] = $areas_csv ? array_values(array_filter(array_map('trim', explode(',', $areas_csv)))) : [];

                $addr = isset($loc['address']) && is_array($loc['address']) ? $loc['address'] : [];
                $L['address'] = [
                    'streetAddress'   => isset($addr['streetAddress']) ? sanitize_text_field($addr['streetAddress']) : '',
                    'addressLocality' => isset($addr['addressLocality']) ? sanitize_text_field($addr['addressLocality']) : '',
                    'addressRegion'   => isset($addr['addressRegion']) ? sanitize_text_field($addr['addressRegion']) : '',
                    'postalCode'      => isset($addr['postalCode']) ? sanitize_text_field($addr['postalCode']) : '',
                    'addressCountry'  => isset($addr['addressCountry']) ? sanitize_text_field($addr['addressCountry']) : ''
                ];

                $geo = isset($loc['geo']) && is_array($loc['geo']) ? $loc['geo'] : [];
                $lat = isset($geo['latitude'])  ? floatval($geo['latitude'])  : null;
                $lng = isset($geo['longitude']) ? floatval($geo['longitude']) : null;
                $L['geo'] = [];
                if ($lat !== null) $L['geo']['latitude'] = $lat;
                if ($lng !== null) $L['geo']['longitude'] = $lng;

                $L['hasMap'] = isset($loc['hasMap']) ? esc_url_raw($loc['hasMap']) : '';

                $services_text = isset($loc['services_text']) ? trim(wp_kses_post($loc['services_text'])) : '';
                if ($services_text !== '') {
                    $L['services'] = [];
                    foreach (preg_split('/\r\n|\r|\n/', $services_text) as $line) {
                        $name = trim($line);
                        if ($name !== '') $L['services'][] = ['serviceType' => $name];
                    }
                }

                $L = $this->clean($L);
                if ($L) $out['locations'][] = $L;
            }
        }

        // FAQs repeater
        $out['faqs'] = [];
        if (!empty($raw['faqs']) && is_array($raw['faqs'])) {
            foreach ($raw['faqs'] as $faq) {
                $q = isset($faq['question']) ? sanitize_text_field($faq['question']) : '';
                $a = isset($faq['answer']) ? wp_kses_post($faq['answer']) : '';
                if ($q && $a) {
                    $out['faqs'][] = [
                        'question' => $q,
                        'answer'   => $a,
                    ];
                }
            }
        }

        $decoded = json_decode($out['locations_json'], true);
        if (json_last_error() !== JSON_ERROR_NONE) $out['locations_json'] = '[]';

        return $out;
    }

    public function add_settings_page()
    {
        add_options_page('Lime Schema', 'Lime Schema', 'manage_options', LIME_SCHEMA_OPTION_KEY, [$this, 'render_settings_page']);
    }

    public function render_settings_page()
    {
        $opts = get_option(LIME_SCHEMA_OPTION_KEY, []);
        $opts = is_array($opts) ? $opts : [];

        if (empty($opts['locations']) && !empty($opts['locations_json'])) {
            $tmp = json_decode($opts['locations_json'], true);
            if (is_array($tmp)) $opts['locations'] = $tmp;
        }

        echo '<div class="wrap"><h1>Lime Schema</h1><form method="post" action="options.php">';
        settings_fields(LIME_SCHEMA_OPTION_KEY);

        echo '<div class="ls-tabs">';
        echo '<div class="ls-tab-nav">
              <a href="#" class="active" data-target="#ls-org">' . esc_html__('Organization', 'lime-schema') . '</a>
              <a href="#" data-target="#ls-website">' . esc_html__('Website', 'lime-schema') . '</a>
              <a href="#" data-target="#ls-locations">' . esc_html__('Locations', 'lime-schema') . '</a>
              <a href="#" data-target="#ls-faqs">' . esc_html__('FAQs', 'lime-schema') . '</a>
              <a href="#" data-target="#ls-preview">' . esc_html__('Preview', 'lime-schema') . '</a>
            </div>';

        echo '<div id="ls-org" class="ls-tab active">';
        $this->field_text('org_name', 'Business Legal Name', $opts);
        $this->field_url('site_url', 'Site URL (canonical)', $opts);
        $this->field_textarea('description', 'Short Description (1–2 sentences)', $opts);
        $this->field_url('logo', 'Logo URL (PNG/SVG)', $opts);
        echo '<p class="description">' . esc_html__('Logo should be square, at least 112×112, PNG/SVG preferred.', 'lime-schema') . '</p>';
        $this->field_textarea('images', 'Brand Image URLs (comma-separated)', $opts);
        echo '<p class="description">' . esc_html__('Use high-quality images (1200px width recommended for rich results).', 'lime-schema') . '</p>';
        $this->field_text('founding_date', 'Founding Date (YYYY-MM-DD)', $opts);
        $this->field_text('founder', 'Founder Name', $opts);
        $this->field_text('phone', 'Primary Phone (+1-xxx-xxx-xxxx)', $opts);
        $this->field_email('email', 'Contact Email', $opts);
        $this->field_text('languages', 'Languages (comma-separated, e.g., en-CA, fr-CA)', $opts);
        $this->field_textarea('sameas', 'Official Profiles (comma-separated URLs)', $opts);
        $this->field_checkbox('disable_org', 'Disable Organization node globally?', $opts);
        $this->field_lb_select('lb_type','Default LocalBusiness subtype', $opts,
            'We use a vetted list of schema.org LocalBusiness subtypes to prevent typos and invalid values. Pick the closest match; search engines ignore unknown types.'
        );
        echo '</div>';

        echo '<div id="ls-website" class="ls-tab">';
        $this->field_checkbox('enable_website', 'Output WebSite/SearchAction?', $opts);
        $this->field_url('search_target', 'Search URL pattern (e.g., https://example.com/?s={search_term_string})', $opts);
        $this->field_checkbox('disable_website', 'Disable WebSite node globally?', $opts);
        $this->field_checkbox('disable_webpage', 'Disable WebPage node globally?', $opts);
        $this->field_checkbox('enable_article', 'Enable Article schema for posts?', $opts);
        $this->field_checkbox('auto_disable_with_seo', 'Automatically disable core nodes when Yoast or Rank Math is active', $opts);
        // Detection status message
        $det = Lime_Schema_Utils::detect_seo();
        if (!empty($det['active'])) {
            $which = $det['yoast'] ? 'Yoast SEO' : ($det['rankmath'] ? 'Rank Math' : 'SEO plugin');
            echo '<p class="description">' . esc_html($which . ' detected. Core nodes (Organization/WebSite/WebPage) will be disabled by default to avoid duplicates. Uncheck the option above to force output.') . '</p>';
        }
        echo '</div>';

        echo '<div id="ls-locations" class="ls-tab">';
        echo '<p>Add one block per physical location. Leave fields blank if unknown—empties are removed automatically.</p>';
        echo '<div id="ls-locations-wrap">';
        $locs = isset($opts['locations']) && is_array($opts['locations']) ? $opts['locations'] : [];
        foreach ($locs as $loc) {
            $this->render_location_block($loc);
        }
        echo '</div>'; // close #ls-locations-wrap
        echo '<p class="ls-actions"><a href="#" id="ls-add-location" class="button">+ Add Location</a></p>';
        echo '<template id="ls-location-proto">';
        $this->render_location_block([], true);
        echo '</template>';
        echo '<input type="hidden" name="' . esc_attr(LIME_SCHEMA_OPTION_KEY . '[locations_json]') . '" value="' . esc_attr($this->v($opts, 'locations_json', '[]')) . '">';
        echo '</div>'; // close #ls-locations

        // FAQs TAB (after Locations)
        echo '<div id="ls-faqs" class="ls-tab">';
        echo '<p>' . esc_html__('Add site-wide FAQ questions and answers. You can include them on specific pages via the meta box.', 'lime-schema') . '</p>';
        echo '<div id="ls-faqs-wrap">';
        $faqs = isset($opts['faqs']) && is_array($opts['faqs']) ? $opts['faqs'] : [];
        foreach ($faqs as $faq) {
            $this->render_faq_block($faq);
        }
        echo '</div>';
        echo '<p class="ls-actions"><a href="#" id="ls-add-faq" class="button">+ ' . esc_html__('Add FAQ', 'lime-schema') . '</a></p>';
        echo '<template id="ls-faq-proto">';
        $this->render_faq_block([], true);
        echo '</template>';
        echo '</div>';

        // PREVIEW TAB (after FAQs)
        echo '<div id="ls-preview" class="ls-tab">';
        echo '<p>' . esc_html__('This preview shows the JSON-LD payload that will be output on the homepage. Click Refresh to use the current, unsaved form values.', 'lime-schema') . '</p>';
        echo '<p class="ls-preview-actions">'
            . '<a href="#" class="button" id="ls-preview-refresh">' . esc_html__('Refresh', 'lime-schema') . '</a> '
            . '<a href="#" class="button" id="ls-preview-copy">' . esc_html__('Copy', 'lime-schema') . '</a> '
            . '<a href="#" class="button" id="ls-preview-validate">' . esc_html__('Validate in Rich Results Test', 'lime-schema') . '</a>'
            . '</p>';
        $renderer = Lime_Schema::instance()->renderer();
        $payload  = method_exists($renderer, 'build_preview_payload') ? $renderer->build_preview_payload($opts) : [];
        echo '<pre id="ls-preview-output" style="max-height:400px;overflow:auto;background:#1e1e1e;color:#ddd;padding:12px;border-radius:6px">' . esc_html($payload ? wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '{}') . '</pre>';
        // Initial hints
        $issues = method_exists($renderer, 'build_preview_issues') ? $renderer->build_preview_issues($opts) : [];
        echo '<div id="ls-preview-hints" class="ls-hints">';
        if (!empty($issues)) {
            echo '<p><strong>' . esc_html__('Recommendations', 'lime-schema') . ':</strong></p><ul style="list-style:disc;padding-left:20px">';
            foreach ($issues as $msg) echo '<li>' . esc_html($msg) . '</li>';
            echo '</ul>';
        } else {
            echo '<p>' . esc_html__('All key fields look good.', 'lime-schema') . '</p>';
        }
        echo '</div>';
        echo '</div>';

        echo '</div>';
        submit_button();
        echo '</form></div>';
    }

    public function ajax_preview()
    {
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'forbidden'], 403);
        check_ajax_referer('lime_schema_preview', 'nonce');
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw form values are unslashed and passed to sanitize_options() immediately below.
        $posted = isset($_POST[LIME_SCHEMA_OPTION_KEY]) ? (array) wp_unslash($_POST[LIME_SCHEMA_OPTION_KEY]) : [];
        // Reuse sanitize to normalize posted values
        $clean  = $this->sanitize_options($posted);
        $renderer = Lime_Schema::instance()->renderer();
        if (!method_exists($renderer, 'build_preview_payload')) wp_send_json_error(['message' => 'no_renderer'], 500);
        $payload = $renderer->build_preview_payload($clean);
        $issues  = method_exists($renderer, 'build_preview_issues') ? $renderer->build_preview_issues($clean) : [];
        wp_send_json_success([ 'payload' => $payload, 'issues' => $issues ]);
    }

    /* Meta box */
    public function add_meta_box()
    {
        $screens = get_post_types(['public' => true], 'names');
        foreach ($screens as $pt) {
            add_meta_box('lime_schema_box', 'Lime Schema (Page-specific)', [$this, 'render_meta_box'], $pt, 'side', 'default');
        }
    }

    public function render_meta_box($post)
    {
        wp_nonce_field(LIME_SCHEMA_NONCE, LIME_SCHEMA_NONCE);
        $meta = get_post_meta($post->ID, LIME_SCHEMA_META_KEY, true);
        $meta = is_array($meta) ? $meta : [];

        $fields = [
            'include_org'      => ['Include Organization node on this page?', 'checkbox'],
            'include_loc'      => ['Include LocalBusiness node(s)?', 'checkbox'],
            'include_webpage'  => ['Include WebPage node?', 'checkbox'],
            'include_website'  => ['Include WebSite node?', 'checkbox'],
            'include_faq'      => ['Include FAQ (from Settings) on this page?', 'checkbox'],
            'include_article'  => ['Include Article schema (this page)?', 'checkbox'],
            'override_lb_type' => ['LocalBusiness subtype override', 'select'],
            'override_name'    => ['Override Name (this page)', 'text'],
            'override_desc'    => ['Override Description (this page)', 'textarea'],
            'override_image'   => ['Primary Image URL (this page)', 'url'],
            'override_lang'    => ['Language override (e.g., en-CA)', 'text'],
        ];

        foreach ($fields as $key => $info) {
            $val = isset($meta[$key]) ? $meta[$key] : '';
            echo '<p style="margin:8px 0">';
            echo '<label><strong>' . esc_html($info[0]) . '</strong></label><br>';

            if ($info[1] === 'checkbox') {
                echo '<label><input type="checkbox" name="' . esc_attr(LIME_SCHEMA_META_KEY . '[' . $key . ']') . '" value="1" ' . checked($val, 1, false) . '> Yes</label>';
            } elseif ($info[1] === 'textarea') {
                echo '<textarea class="widefat" rows="2" name="' . esc_attr(LIME_SCHEMA_META_KEY . '[' . $key . ']') . '">' . esc_textarea($val) . '</textarea>';
            } elseif ($key === 'override_lb_type') {
                $this->meta_lb_select('override_lb_type', $val);
                echo '<br><span class="description">Choose a valid schema.org subtype to avoid typos.</span>';
            } else {
                echo '<input type="text" class="widefat" name="' . esc_attr(LIME_SCHEMA_META_KEY . '[' . $key . ']') . '" value="' . esc_attr($val) . '">';
            }
            echo '</p>';
        }

        // Custom FAQs for this page
        echo '<hr><p><strong>' . esc_html__('Per-page FAQs', 'lime-schema') . '</strong></p>';
        echo '<p class="description">' . esc_html__('Check “Include FAQ” above to output FAQ schema. If you add custom FAQs here and enable FAQ, these will be used instead of the site-wide FAQs.', 'lime-schema') . '</p>';
        echo '<p><label><input type="checkbox" name="' . esc_attr(LIME_SCHEMA_META_KEY . '[use_custom_faq]') . '" value="1" ' . checked(!empty($meta['use_custom_faq']), 1, false) . '> ' . esc_html__('Use custom FAQs on this page', 'lime-schema') . '</label></p>';

        $faqs = isset($meta['faqs']) && is_array($meta['faqs']) ? $meta['faqs'] : [];
        echo '<div id="ls-meta-faqs-wrap">';
        if (!empty($faqs)) {
            foreach ($faqs as $faq) {
                $this->render_meta_faq_block($faq);
            }
        }
        echo '</div>';
        echo '<p><a href="#" id="ls-meta-add-faq" class="button">+ ' . esc_html__('Add FAQ', 'lime-schema') . '</a></p>';
        echo '<template id="ls-meta-faq-proto">';
        $this->render_meta_faq_block([], true);
        echo '</template>';

        echo '<p><em>Tip:</em> ' . esc_html__('On the homepage, include Organization + WebSite + WebPage. On other pages, WebPage plus (optionally) LocalBusiness is typical.', 'lime-schema') . '</p>';
    }

    /* User profile: author sameAs */
    public function render_user_fields($user)
    {
        if (!current_user_can('edit_user', $user->ID)) return;
        $sameas = get_user_meta($user->ID, 'lime_author_sameas', true);
        echo '<h2>' . esc_html__('Lime Schema', 'lime-schema') . '</h2>';
        echo '<table class="form-table"><tr><th><label for="lime_author_sameas">' . esc_html__('Author sameAs URLs', 'lime-schema') . '</label></th>';
        echo '<td><textarea name="lime_author_sameas" id="lime_author_sameas" rows="3" class="regular-text">' . esc_textarea($sameas) . '</textarea><p class="description">' . esc_html__('Comma-separated list of profile URLs for this author (e.g., Twitter, LinkedIn).', 'lime-schema') . '</p></td></tr></table>';
    }
    public function save_user_fields($user_id)
    {
        if (!current_user_can('edit_user', $user_id)) return;
        // Verify the core user edit nonce and unslash input before sanitizing.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Using core-provided nonce for user profile updates.
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'update-user_' . $user_id)) {
            return;
        }
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Unslash then sanitize immediately below.
        $raw = isset($_POST['lime_author_sameas']) ? wp_unslash($_POST['lime_author_sameas']) : '';
        $val = wp_kses_post($raw);
        update_user_meta($user_id, 'lime_author_sameas', $val);
    }

    public function save_post_meta($post_id, $post)
    {
        if (!isset($_POST[LIME_SCHEMA_NONCE])) return;
        $nonce = sanitize_text_field( wp_unslash( $_POST[LIME_SCHEMA_NONCE] ) );
        if (!wp_verify_nonce($nonce, LIME_SCHEMA_NONCE)) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Values are unslashed here and sanitized field-by-field below.
        $raw = isset($_POST[LIME_SCHEMA_META_KEY]) ? (array) wp_unslash($_POST[LIME_SCHEMA_META_KEY]) : [];
        $clean = [];
        foreach ($raw as $k => $v) {
            if ($k === 'faqs' && is_array($v)) {
                $clean['faqs'] = [];
                foreach ($v as $faq) {
                    $q = isset($faq['question']) ? sanitize_text_field($faq['question']) : '';
                    $a = isset($faq['answer']) ? wp_kses_post($faq['answer']) : '';
                    if ($q && $a) $clean['faqs'][] = ['question' => $q, 'answer' => $a];
                }
            } else {
                $clean[$k] = is_string($v) ? sanitize_text_field($v) : (int)!empty($v);
            }
        }
        update_post_meta($post_id, LIME_SCHEMA_META_KEY, $clean);
    }

    /* Field rendering helpers */
    private function field_text($key, $label, $opts)
    { echo '<p><label><strong>' . esc_html($label) . '</strong><br><input type="text" class="regular-text" name="' . esc_attr(LIME_SCHEMA_OPTION_KEY . "[$key]") . '" value="' . esc_attr($this->v($opts, $key)) . '"></label></p>'; }
    private function field_email($key, $label, $opts)
    { echo '<p><label><strong>' . esc_html($label) . '</strong><br><input type="email" class="regular-text" name="' . esc_attr(LIME_SCHEMA_OPTION_KEY . "[$key]") . '" value="' . esc_attr($this->v($opts, $key)) . '"></label></p>'; }
    private function field_url($key, $label, $opts)
    { echo '<p><label><strong>' . esc_html($label) . '</strong><br><input type="url" class="regular-text" name="' . esc_attr(LIME_SCHEMA_OPTION_KEY . "[$key]") . '" value="' . esc_attr($this->v($opts, $key)) . '"></label></p>'; }
    private function field_textarea($key, $label, $opts)
    { echo '<p><label><strong>' . esc_html($label) . '</strong><br><textarea rows="3" class="large-text" name="' . esc_attr(LIME_SCHEMA_OPTION_KEY . "[$key]") . '">' . esc_textarea($this->v($opts, $key)) . '</textarea></label></p>'; }
    private function field_checkbox($key, $label, $opts)
    { $v = !empty($opts[$key]) ? 1 : 0; echo '<p><label><input type="checkbox" name="' . esc_attr(LIME_SCHEMA_OPTION_KEY . "[$key]") . '" value="1" ' . checked($v, 1, false) . '> <strong>' . esc_html($label) . '</strong></label></p>'; }

    private function field_lb_select($key, $label, $opts, $help = '')
    {
        $current = $this->v($opts, $key, 'LocalBusiness');
        $options = Lime_Schema_Utils::allowed_lb_types();
        echo '<p><label><strong>' . esc_html($label) . '</strong><br>';
        echo '<select name="' . esc_attr(LIME_SCHEMA_OPTION_KEY . "[$key]") . '" class="regular-text">';
        foreach ($options as $t) {
            echo '<option value="' . esc_attr($t) . '"' . selected($current, $t, false) . '>' . esc_html($t) . '</option>';
        }
        echo '</select></label>';
        if ($help) echo '<br><span class="description">' . wp_kses_post($help) . '</span>';
        echo '</p>';
    }

    private function meta_lb_select($name, $value)
    {
        $options = Lime_Schema_Utils::allowed_lb_types();
        echo '<select class="widefat" name="' . esc_attr(LIME_SCHEMA_META_KEY . "[$name]") . '">';
        echo '<option value="">— Use default —</option>';
        foreach ($options as $t) {
            echo '<option value="' . esc_attr($t) . '"' . selected($value, $t, false) . '>' . esc_html($t) . '</option>';
        }
        echo '</select>';
    }

    private function render_location_block($loc = [], $is_proto = false)
    {
        echo '<div class="ls-loc">';
        echo '<h4>Location</h4>';
        echo '<div class="ls-row">';
        echo '<div><label><strong>Name</strong><br><input type="text" data-name="name" value="' . esc_attr($this->v($loc, 'name')) . '"></label></div>';
        echo '<div><label><strong>Slug</strong><br><input type="text" data-name="slug" value="' . esc_attr($this->v($loc, 'slug')) . '"></label></div>';
        echo '</div>';
        echo '<div class="ls-row">';
        echo '<div><label><strong>Phone</strong><br><input type="text" data-name="phone" value="' . esc_attr($this->v($loc, 'phone')) . '"></label></div>';
        echo '<div><label><strong>Email</strong><br><input type="email" data-name="email" value="' . esc_attr($this->v($loc, 'email')) . '"></label></div>';
        echo '<div><label><strong>Price Range</strong><br><input type="text" data-name="priceRange" value="' . esc_attr($this->v($loc, 'priceRange')) . '"></label></div>';
        echo '</div>';
        echo '<div class="ls-row">';
        echo '<div><label><strong>Image URLs (comma-separated)</strong><br><input type="text" data-name="image_csv" value="' . esc_attr(is_array($this->v($loc, 'image')) ? implode(', ', $loc['image']) : $this->v($loc, 'image_csv')) . '"></label></div>';
        echo '<div><label><strong>Google Map Link</strong><br><input type="url" data-name="hasMap" value="' . esc_attr($this->v($loc, 'hasMap')) . '"></label></div>';
        echo '</div>';

        $addr = isset($loc['address']) && is_array($loc['address']) ? $loc['address'] : [];
        echo '<fieldset style="border:1px dashed #ccc;padding:8px;margin-top:8px"><legend><strong>Address</strong></legend>';
        echo '<div class="ls-row"><div><label>Street<br><input type="text" data-name="address[streetAddress]" value="' . esc_attr($this->v($addr, 'streetAddress')) . '"></label></div>
            <div><label>City<br><input type="text" data-name="address[addressLocality]" value="' . esc_attr($this->v($addr, 'addressLocality')) . '"></label></div>
            <div><label>Region/State<br><input type="text" data-name="address[addressRegion]" value="' . esc_attr($this->v($addr, 'addressRegion')) . '"></label></div></div>';
        echo '<div class="ls-row"><div><label>Postal/ZIP<br><input type="text" data-name="address[postalCode]" value="' . esc_attr($this->v($addr, 'postalCode')) . '"></label></div>
            <div><label>Country (code)<br><input type="text" data-name="address[addressCountry]" value="' . esc_attr($this->v($addr, 'addressCountry')) . '"></label></div></div>';
        echo '</fieldset>';

        $geo = isset($loc['geo']) && is_array($loc['geo']) ? $loc['geo'] : [];
        echo '<fieldset style="border:1px dashed #ccc;padding:8px;margin-top:8px"><legend><strong>Geo</strong></legend>';
        echo '<div class="ls-row"><div><label>Latitude<br><input type="text" data-name="geo[latitude]" value="' . esc_attr($this->v($geo, 'latitude')) . '"></label></div>
            <div><label>Longitude<br><input type="text" data-name="geo[longitude]" value="' . esc_attr($this->v($geo, 'longitude')) . '"></label></div></div>';
        echo '</fieldset>';

        echo '<div class="ls-row"><div><label><strong>Areas Served (comma-separated)</strong><br><input type="text" data-name="areas_csv" value="' . esc_attr(isset($loc['areaServed']) && is_array($loc['areaServed']) ? implode(", ", $loc['areaServed']) : $this->v($loc, 'areas_csv')) . '"></label></div></div>';
        echo '<div class="ls-row"><div><label><strong>Services (one per line)</strong><br><textarea rows="3" data-name="services_text">' . esc_textarea($this->services_to_text($loc)) . '</textarea></label></div></div>';

        echo '<p class="ls-actions"><a href="#" class="button-link-delete ls-remove">Remove location</a></p>';
        echo '</div>';
    }

    private function render_faq_block($faq = [], $is_proto = false)
    {
        echo '<div class="ls-loc">';
        echo '<h4>' . esc_html__('FAQ', 'lime-schema') . '</h4>';
        echo '<div class="ls-row">';
        echo '<div><label><strong>' . esc_html__('Question', 'lime-schema') . '</strong><br>';
        echo '<input type="text" data-name="question" value="' . esc_attr(isset($faq['question']) ? $faq['question'] : '') . '"></label></div>';
        echo '</div>';
        echo '<div class="ls-row">';
        echo '<div><label><strong>' . esc_html__('Answer', 'lime-schema') . '</strong><br>';
        echo '<textarea rows="3" data-name="answer">' . esc_textarea(isset($faq['answer']) ? $faq['answer'] : '') . '</textarea></label></div>';
        echo '</div>';
        echo '<p class="ls-actions"><a href="#" class="button-link-delete ls-remove">' . esc_html__('Remove', 'lime-schema') . '</a></p>';
        echo '</div>';
    }

    private function render_meta_faq_block($faq = [], $is_proto = false)
    {
        echo '<div class="ls-loc">';
        echo '<div class="ls-row">';
        echo '<div><label><strong>' . esc_html__('Question', 'lime-schema') . '</strong><br>';
        echo '<input type="text" data-name="question" value="' . esc_attr(isset($faq['question']) ? $faq['question'] : '') . '"></label></div>';
        echo '</div>';
        echo '<div class="ls-row">';
        echo '<div><label><strong>' . esc_html__('Answer', 'lime-schema') . '</strong><br>';
        echo '<textarea rows="3" data-name="answer">' . esc_textarea(isset($faq['answer']) ? $faq['answer'] : '') . '</textarea></label></div>';
        echo '</div>';
        echo '<p class="ls-actions"><a href="#" class="button-link-delete ls-remove">' . esc_html__('Remove', 'lime-schema') . '</a></p>';
        echo '</div>';
    }

    private function services_to_text($loc)
    {
        if (!empty($loc['services']) && is_array($loc['services'])) {
            $names = [];
            foreach ($loc['services'] as $s) {
                if (!empty($s['serviceType'])) $names[] = $s['serviceType'];
            }
            return implode("\n", $names);
        }
        return $this->v($loc, 'services_text', '');
    }

    /* small helpers */
    private function v($arr, $key, $default = '')
    { return isset($arr[$key]) && $arr[$key] !== '' ? $arr[$key] : $default; }

    private function clean($value)
    {
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $vv = $this->clean($v);
                if ($vv === null || $vv === '' || (is_array($vv) && empty($vv))) unset($value[$k]);
                else $value[$k] = $vv;
            }
            return empty($value) ? null : $value;
        }
        return $value;
    }
}
