<?php

/**
 * Plugin Name: CPT Hub Publisher
 * Description: Create multiple Custom Post Types from the admin and expose them via a clean RSS feed endpoint for consumption on other sites.
 * Version:     1.0.0
 * Author:      Lime
 * License:     GPL-2.0-or-later
 */

if (!defined('ABSPATH')) exit;

final class CPT_Hub_Publisher
{
    const OPTION_KEY   = 'cphub_types';        // array of CPT definitions
    const OPTION_FEED  = 'cphub_feed_settings'; // feed settings (secret key, default per_page)
    const OPTION_TAX   = 'cphub_taxonomies';    // array of taxonomy definitions
    const OPTION_STYLES= 'cphub_styles';        // per-CPT style/layout config
    const OPTION_LOC_SEEDED = 'cphub_locations_seeded'; // one-time location seeding flag
    const OPTION_GLOBAL_CSS = 'cphub_global_css'; // sitewide custom CSS payload
    const NONCE_ACTION = 'cphub_manage_types';

    public function __construct()
    {
        // Admin UI
        add_action('admin_menu',        [$this, 'admin_menu']);
        add_action('admin_init',        [$this, 'handle_admin_post']);
        add_action('admin_init',        [$this, 'register_settings']);
        add_action('admin_init',        [$this, 'register_admin_columns']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_enqueue_scripts',  [$this, 'enqueue_global_css']);

        // Register taxonomies then CPTs so bindings exist
        add_action('init',              [$this, 'register_dynamic_taxonomies'], 9);
        add_action('init',              [$this, 'register_dynamic_cpts'], 10);
        add_action('admin_init',        [$this, 'maybe_seed_locations']);
        // Protect default location term and slug
        add_filter('pre_delete_term',   [$this, 'protect_all_locations_delete'], 10, 2);
        add_filter('wp_update_term_data', [$this, 'protect_all_locations_slug'], 10, 4);

        // Feed endpoint
        add_action('init',              [$this, 'register_feed']);
        add_filter('query_vars',        [$this, 'register_query_vars']);

        // REST endpoints
        add_action('rest_api_init',     [$this, 'register_rest']);
        // Maintenance tools
        add_action('admin_post_cphub_publisher_cleanup', [$this, 'handle_cleanup_old']);

        // Cache busting when content of any registered CPT changes
        add_action('save_post',         [$this, 'bust_feed_cache_on_save'], 10, 3);
        add_action('deleted_post',      [$this, 'bust_feed_cache_on_delete']);

        // Meta boxes for custom fields
        add_action('add_meta_boxes',    [$this, 'register_meta_boxes']);
        add_action('save_post',         [$this, 'save_meta_fields'], 9, 3);
        add_action('save_post',         [$this, 'ensure_default_location'], 20, 3);

        // Force Classic Editor for CPT Hub types
        add_filter('use_block_editor_for_post_type', [$this, 'disable_block_editor_for_cphub'], 10, 2);

        // Removed Elementor rendering hooks; templates can be managed manually if desired

        // Activation / Deactivation
        register_activation_hook(__FILE__, [__CLASS__, 'activate']);
        register_deactivation_hook(__FILE__, [__CLASS__, 'deactivate']);

        // Publisher-side shortcodes to render local CPT content
        add_shortcode('cphub_list',  [$this, 'sc_list']);
        add_shortcode('cphub_item',  [$this, 'sc_item']);
        add_shortcode('cphub_location',  [$this, 'sc_location']);
        add_shortcode('cphub_meta',  [$this, 'sc_meta']);
    }

    /* ---------------------- Known Locations ---------------- */
    private function get_known_locations()
    {
        // Slugs are derived from hostnames for consistency with consumer config.
        return [
            ['slug' => 'merrymaidsoshawa',       'label' => 'Oshawa, Whitby, Pickering, Ajax (Durham)', 'url' => 'https://merrymaidsoshawa.ca/'],
            ['slug' => 'merrymaidspeterborough', 'label' => 'Peterborough and Lindsay',                 'url' => 'https://merrymaidspeterborough.ca/'],
            ['slug' => 'merrymaidsbarrie',       'label' => 'Barrie',                                   'url' => 'https://merrymaidsbarrie.ca/'],
            ['slug' => 'merrymaidstorontowest',  'label' => 'Toronto West (Former Etobicoke)',          'url' => 'https://merrymaidstorontowest.ca/'],
            ['slug' => 'merrymaidstoronto',      'label' => 'Toronto',                                  'url' => 'https://merrymaidstoronto.ca/'],
            ['slug' => 'merrymaidsbrampton',     'label' => 'Brampton',                                 'url' => 'https://merrymaidsbrampton.ca/'],
            ['slug' => 'merrymaidsburnaby',      'label' => 'Burnaby, New Westminster and Tri-Cities',  'url' => 'https://merrymaidsburnaby.ca/'],
            ['slug' => 'merrymaidsvancouver',    'label' => 'Vancouver',                                'url' => 'https://merrymaidsvancouver.ca/'],
            ['slug' => 'merrymaidscalgarynse',   'label' => 'Calgary NSE',                              'url' => 'https://merrymaidscalgarynse.ca/'],
            ['slug' => 'merrymaidscalgarysw',    'label' => 'Calgary SW',                               'url' => 'https://merrymaidscalgarysw.ca/'],
            ['slug' => 'merrymaidskwc',          'label' => 'KWC (Kitchener, Waterloo, Cambridge)',     'url' => 'https://merrymaidskwc.ca/'],
            ['slug' => 'merrymaidsguelph',       'label' => 'Guelph',                                   'url' => 'https://merrymaidsguelph.ca/'],
            ['slug' => 'merrymaidssurrey',       'label' => 'Surrey, Delta, Langley, White Rock',       'url' => 'https://merrymaidssurrey.ca/'],
            ['slug' => 'merrymaidshamilton',     'label' => 'Hamilton and Stoney Creek',                'url' => 'https://merrymaidshamilton.ca/'],
            ['slug' => 'merrymaidsoakville',     'label' => 'Oakville',                                 'url' => 'https://merrymaidsoakville.ca/'],
            ['slug' => 'merrymaidsmilton',       'label' => 'Milton and Georgetown',                    'url' => 'https://merrymaidsmilton.ca/'],
            ['slug' => 'merrymaidsburlington',   'label' => 'Burlington',                               'url' => 'https://merrymaidsburlington.ca/'],
            ['slug' => 'merrymaidsmississauga',  'label' => 'Mississauga',                              'url' => 'https://merrymaidsmississauga.ca/'],
            ['slug' => 'merrymaidsorangeville',  'label' => 'Orangeville',                              'url' => 'https://merrymaidsorangeville.ca/'],
            ['slug' => 'merrymaidskingston',     'label' => 'Kingston',                                 'url' => 'https://www.merrymaidskingston.ca/'],
            ['slug' => 'merrymaidslethbridge',   'label' => 'Lethbridge',                               'url' => 'https://merrymaidslethbridge.ca/'],
            ['slug' => 'merrymaidslondon',       'label' => 'London',                                   'url' => 'https://merrymaidslondon.ca/'],
            ['slug' => 'merrymaidsuxbridge',     'label' => 'Uxbridge and Markham',                     'url' => 'https://merrymaidsuxbridge.ca/'],
            ['slug' => 'merrymaidshalifax',      'label' => 'Metro (Halifax)',                          'url' => 'https://merrymaidshalifax.ca/'],
            ['slug' => 'merrymaidsnorthvancouver','label'=> 'North & West Vancouver',                   'url' => 'https://merrymaidsnorthvancouver.ca/'],
            ['slug' => 'merrymaidsottawa',       'label' => 'Ottawa',                                   'url' => 'https://merrymaidsottawa.ca/'],
            ['slug' => 'merrymaidsottawawest',   'label' => 'Ottawa West',                              'url' => 'https://merrymaidsottawawest.ca/'],
            ['slug' => 'merrymaidsregina',       'label' => 'Regina',                                   'url' => 'https://merrymaidsregina.ca/'],
            ['slug' => 'merrymaidswinnipeg',     'label' => 'Winnipeg',                                 'url' => 'https://merrymaidswinnipeg.ca/'],
            ['slug' => 'merrymaidsrichmondhill', 'label' => 'Richmond Hill and Vaughan',                'url' => 'https://merrymaidsrichmondhill.ca/'],
            ['slug' => 'merrymaidssaskatoon',    'label' => 'Saskatoon',                                'url' => 'https://merrymaidssaskatoon.ca/'],
            ['slug' => 'merrymaidsscarborough',  'label' => 'Scarborough',                              'url' => 'https://merrymaidsscarborough.ca/'],
            ['slug' => 'merrymaidsbelleville',   'label' => 'Belleville and Trenton',                   'url' => 'https://merrymaidsbelleville.ca/'],
            ['slug' => 'merrymaidsniagara',      'label' => 'St. Catharines, Niagara',                  'url' => 'https://merrymaidsniagara.ca/'],
            ['slug' => 'home-office',            'label' => 'Home Office',                              'url' => 'https://merrymaids.ca/'],
        ];
    }

    public function maybe_seed_locations()
    {
        if (get_option(self::OPTION_LOC_SEEDED)) return;
        // Ensure 'location' taxonomy is registered
        if (!taxonomy_exists('location')) return;

        $locations = $this->get_known_locations();
        $added = 0;
        foreach ($locations as $loc) {
            $slug  = sanitize_key($loc['slug']);
            $label = sanitize_text_field($loc['label']);
            $url   = esc_url_raw($loc['url']);
            $exists = term_exists($slug, 'location');
            if (!$exists) {
                $res = wp_insert_term($label, 'location', ['slug' => $slug]);
                if (!is_wp_error($res)) {
                    $term_id = (int)$res['term_id'];
                    if ($url) update_term_meta($term_id, 'cphub_location_url', $url);
                    $added++;
                }
            }
        }
        update_option(self::OPTION_LOC_SEEDED, ['time' => time(), 'added' => $added]);
        if (is_admin()) {
            add_settings_error('cphub', 'loc_seeded', sprintf('Location seeding complete. Added %d locations.', $added), 'updated');
        }
    }

    /* ---------------------- Activation ---------------------- */
    public static function activate()
    {
        // Set sane defaults if missing
        if (!get_option(self::OPTION_FEED)) {
            add_option(self::OPTION_FEED, [
                'items_per_feed' => 20,
                'secret_key'     => '', // optional token; empty = public
            ]);
        }
        // Ensure rewrite rules exist
        (new self())->register_feed();
        flush_rewrite_rules();
    }

    public static function deactivate()
    {
        flush_rewrite_rules();
    }

    public function disable_block_editor_for_cphub($use_block_editor, $post_type)
    {
        $types = get_option(self::OPTION_KEY, []);
        if (isset($types[$post_type])) return false;
        return $use_block_editor;
    }

    /* ---------------------- Admin UI ------------------------ */
    public function admin_menu()
    {
        add_menu_page(
            'CPT Hub',
            'CPT Hub',
            'manage_options',
            'cphub',
            [$this, 'render_admin_page'],
            'dashicons-rss',
            27
        );
    }

    public function register_settings()
    {
        register_setting('cphub_feed', self::OPTION_FEED, [$this, 'sanitize_feed_settings']);
    }

    public function sanitize_feed_settings($input)
    {
        return [
            'items_per_feed'        => max(1, intval($input['items_per_feed'] ?? 20)),
            'secret_key'            => sanitize_text_field($input['secret_key'] ?? ''),
            'publisher_location_name' => sanitize_text_field($input['publisher_location_name'] ?? ''),
        ];
    }

    public function handle_admin_post()
    {
        if (!current_user_can('manage_options')) return;

        if (!empty($_POST['cphub_action']) && check_admin_referer(self::NONCE_ACTION)) {
            $types = get_option(self::OPTION_KEY, []);
            $taxes = get_option(self::OPTION_TAX, []);

            if ($_POST['cphub_action'] === 'add') {
                $slug  = sanitize_key($_POST['slug'] ?? '');
                $label = sanitize_text_field($_POST['label'] ?? '');
                $supports = array_map('sanitize_text_field', (array)($_POST['supports'] ?? []));
                $has_archive = !empty($_POST['has_archive']) ? true : false;

                if ($slug && $label) {
                    if (strlen($slug) > 20) {
                        add_settings_error('cphub', 'cpt_slug_len', 'CPT slug too long. WordPress requires 20 characters or fewer.', 'error');
                    } elseif (isset($types[$slug])) {
                        add_settings_error('cphub', 'cpt_exists', 'A Custom Post Type with this slug already exists.', 'error');
                    } else {
                    $rewrite_in = isset($_POST['rewrite_slug']) ? sanitize_title($_POST['rewrite_slug']) : '';
                    $types[$slug] = [
                        'label'       => $label,
                        'supports'    => array_values(array_intersect($supports, ['title', 'editor', 'excerpt', 'thumbnail', 'custom-fields'])),
                        'has_archive' => $has_archive,
                        'fields'      => [], // custom meta field defs
                        'taxonomies'  => [], // assigned custom taxonomies
                        'rewrite_slug'=> $rewrite_in ?: '',
                    ];
                    update_option(self::OPTION_KEY, $types);
                    add_settings_error('cphub', 'cpt_added', 'Custom Post Type added.', 'updated');
                    flush_rewrite_rules();
                    }
                } else {
                    add_settings_error('cphub', 'cpt_error', 'Please provide a valid slug and label.', 'error');
                }
            }

            if ($_POST['cphub_action'] === 'update') {
                $slug  = sanitize_key($_POST['slug'] ?? '');
                $label = sanitize_text_field($_POST['label'] ?? '');
                $supports = array_map('sanitize_text_field', (array)($_POST['supports'] ?? []));
                $has_archive = !empty($_POST['has_archive']) ? true : false;
                $fields_in = isset($_POST['fields']) && is_array($_POST['fields']) ? $_POST['fields'] : [];
                $tax_in = isset($_POST['taxonomies']) ? array_map('sanitize_key', (array)$_POST['taxonomies']) : [];

                if ($slug && isset($types[$slug])) {
                    if (strlen($slug) > 20) {
                        add_settings_error('cphub', 'cpt_update_slug_len', 'CPT slug exceeds 20 characters. Please shorten the slug.', 'error');
                        return;
                    }
                    if (!$label) {
                        add_settings_error('cphub', 'cpt_update_error', 'Please provide a valid label.', 'error');
                    } else {
                        $types[$slug]['label']       = $label;
                        $types[$slug]['supports']    = array_values(array_intersect($supports, ['title', 'editor', 'excerpt', 'thumbnail', 'custom-fields']));
                        $types[$slug]['has_archive'] = $has_archive;
                        $types[$slug]['rewrite_slug']= isset($_POST['rewrite_slug']) ? (sanitize_title($_POST['rewrite_slug']) ?: '') : ($types[$slug]['rewrite_slug'] ?? '');
                        // Elementor archive template selection removed.

                        // Normalize custom field definitions
                        $allowed_types = ['text', 'textarea', 'number', 'url', 'select', 'media', 'wysiwyg'];
                        $norm_fields = [];
                        $rows = max(
                            count($fields_in['key'] ?? []),
                            count($fields_in['label'] ?? []),
                            count($fields_in['type'] ?? []),
                            count($fields_in['options'] ?? []),
                            count($fields_in['media_type'] ?? [])
                        );
                        for ($i = 0; $i < $rows; $i++) {
                            $k = isset($fields_in['key'][$i]) ? sanitize_key($fields_in['key'][$i]) : '';
                            // Disallow protected keys (leading underscore) because they won't appear in feed
                            if ($k && $k[0] === '_') $k = ltrim($k, '_');
                            $lab = isset($fields_in['label'][$i]) ? sanitize_text_field($fields_in['label'][$i]) : '';
                            $typ = isset($fields_in['type'][$i]) ? sanitize_key($fields_in['type'][$i]) : 'text';
                            $opt_raw = isset($fields_in['options'][$i]) ? sanitize_text_field($fields_in['options'][$i]) : '';
                            $media_type = isset($fields_in['media_type'][$i]) ? sanitize_key($fields_in['media_type'][$i]) : 'file';
                            if (!$k || !$lab) continue;
                            if (!in_array($typ, $allowed_types, true)) $typ = 'text';
                            $opts = [];
                            if ($typ === 'select' && $opt_raw !== '') {
                                $parts = array_map('trim', explode(',', $opt_raw));
                                foreach ($parts as $p) {
                                    if ($p === '') continue;
                                    $opts[] = $p;
                                }
                            }
                            $norm_fields[] = [
                                'key'     => $k,
                                'label'   => $lab,
                                'type'    => $typ,
                                'options' => $opts,
                                'media_type' => in_array($media_type, ['image','file'], true) ? $media_type : 'file',
                            ];
                        }
                        $types[$slug]['fields'] = $norm_fields;

                        // Assign taxonomies (only ones that exist)
                        $available_tax = array_keys($taxes);
                        $assigned = array_values(array_intersect($tax_in, $available_tax));
                        $types[$slug]['taxonomies'] = $assigned;

                        update_option(self::OPTION_KEY, $types);
                        add_settings_error('cphub', 'cpt_updated', 'Custom Post Type updated.', 'updated');
                        flush_rewrite_rules();
                    }
                } else {
                    add_settings_error('cphub', 'cpt_update_missing', 'Unknown CPT to update.', 'error');
                }
            }

            if ($_POST['cphub_action'] === 'delete') {
                $slug = sanitize_key($_POST['slug'] ?? '');
                if ($slug && isset($types[$slug])) {
                    unset($types[$slug]);
                    update_option(self::OPTION_KEY, $types);
                    add_settings_error('cphub', 'cpt_deleted', 'Custom Post Type removed.', 'updated');
                    flush_rewrite_rules();
                }
            }

            // Taxonomy add/delete
            if ($_POST['cphub_action'] === 'tax_add') {
                $slug  = sanitize_key($_POST['slug'] ?? '');
                $label = sanitize_text_field($_POST['label'] ?? '');
                $hier  = !empty($_POST['hierarchical']) ? true : false;
                if ($slug && $label) {
                    $taxes[$slug] = [
                        'label'        => $label,
                        'hierarchical' => $hier,
                        'show_in_rest' => true,
                        'public'       => true,
                    ];
                    update_option(self::OPTION_TAX, $taxes);
                    add_settings_error('cphub', 'tax_added', 'Taxonomy added.', 'updated');
                    flush_rewrite_rules();
                } else {
                    add_settings_error('cphub', 'tax_error', 'Please provide a valid taxonomy slug and label.', 'error');
                }
            }

            if ($_POST['cphub_action'] === 'tax_delete') {
                $slug = sanitize_key($_POST['slug'] ?? '');
                if ($slug && isset($taxes[$slug])) {
                    unset($taxes[$slug]);
                    update_option(self::OPTION_TAX, $taxes);
                    // Remove from CPT assignments
                    foreach ($types as $cslug => $def) {
                        if (!empty($def['taxonomies'])) {
                            $types[$cslug]['taxonomies'] = array_values(array_diff($def['taxonomies'], [$slug]));
                        }
                    }
                    update_option(self::OPTION_KEY, $types);
                    add_settings_error('cphub', 'tax_deleted', 'Taxonomy removed.', 'updated');
                    flush_rewrite_rules();
                }
            }

            if ($_POST['cphub_action'] === 'tax_update') {
                $slug  = sanitize_key($_POST['slug'] ?? '');
                $label = sanitize_text_field($_POST['label'] ?? '');
                $hier  = !empty($_POST['hierarchical']) ? true : false;
                if ($slug && isset($taxes[$slug])) {
                    if (!$label) {
                        add_settings_error('cphub', 'tax_update_error', 'Please provide a valid taxonomy label.', 'error');
                    } else {
                        $taxes[$slug]['label']        = $label;
                        $taxes[$slug]['hierarchical'] = $hier;
                        update_option(self::OPTION_TAX, $taxes);
                        add_settings_error('cphub', 'tax_updated', 'Taxonomy updated.', 'updated');
                        flush_rewrite_rules();
                    }
                } else {
                    add_settings_error('cphub', 'tax_update_missing', 'Unknown taxonomy to update.', 'error');
                }
            }

            // Update a Location term's URL meta
            if ($_POST['cphub_action'] === 'loc_update') {
                $term_id = intval($_POST['term_id'] ?? 0);
                $url     = esc_url_raw($_POST['url'] ?? '');
                $term    = $term_id ? get_term($term_id, 'location') : null;
                if ($term && !is_wp_error($term)) {
                    update_term_meta($term_id, 'cphub_location_url', $url);
                    add_settings_error('cphub', 'loc_url_updated', 'Location URL updated.', 'updated');
                } else {
                    add_settings_error('cphub', 'loc_url_error', 'Invalid location term.', 'error');
                }
            }

            // Save Styles config per CPT
            if ($_POST['cphub_action'] === 'styles_save') {
                $cpt = sanitize_key($_POST['cpt'] ?? '');
                if ($cpt && isset($types[$cpt])) {
                    $supports = (array)($types[$cpt]['supports'] ?? []);
                    $fields   = (array)($types[$cpt]['fields'] ?? []);
                    $has_meta = !empty($fields);
                    $allowed  = [];
                    if (in_array('title', $supports, true))    $allowed[] = 'title';
                    if (in_array('thumbnail', $supports, true))$allowed[] = 'image';
                    if (in_array('excerpt', $supports, true))  $allowed[] = 'excerpt';
                    if (in_array('editor', $supports, true))   $allowed[] = 'content';
                    if ($has_meta) array_push($allowed, 'meta1','meta2','meta3');
                    $allowed[] = 'button';

                    $order = array_map('sanitize_key', (array)($_POST['layout']['order'] ?? []));
                    // keep only allowed and unique
                    $order = array_values(array_unique(array_filter($order, function($k) use ($allowed){ return in_array($k, $allowed, true); })));

                    $enabled_raw = (array)($_POST['layout']['enabled'] ?? []);
                    $enabled = [];
                    foreach (['title','image','excerpt','content','meta1','meta2','meta3','button'] as $el) {
                        if (!in_array($el, $allowed, true)) { $enabled[$el] = false; continue; }
                        $enabled[$el] = !empty($enabled_raw[$el]) ? true : false;
                    }
                    // Responsive visibility
                    $enabled_tab_raw = (array)($_POST['layout']['enabled_tab'] ?? []);
                    $enabled_mob_raw = (array)($_POST['layout']['enabled_mob'] ?? []);
                    $groups = ['title','image','excerpt','content','meta','button'];
                    $enabled_tab = [];
                    $enabled_mob = [];
                    foreach ($groups as $g) {
                        $enabled_tab[$g] = !empty($enabled_tab_raw[$g]) ? true : false;
                        $enabled_mob[$g] = !empty($enabled_mob_raw[$g]) ? true : false;
                    }
                    $meta_keys = [];
                    if ($has_meta) {
                        foreach (['meta1','meta2','meta3'] as $m) {
                            $meta_keys[$m] = sanitize_key($_POST['layout']['meta_keys'][$m] ?? '');
                        }
                    } else {
                        $meta_keys = ['meta1'=>'','meta2'=>'','meta3'=>''];
                    }
                    // Meta placement (wrap)
                    $meta_wrap = ['meta1'=>'content','meta2'=>'content','meta3'=>'content'];
                    if ($has_meta) {
                        $wrap_in = isset($_POST['layout']['meta_wrap']) && is_array($_POST['layout']['meta_wrap']) ? $_POST['layout']['meta_wrap'] : [];
                        foreach (['meta1','meta2','meta3'] as $m) {
                            $val = isset($wrap_in[$m]) ? sanitize_key($wrap_in[$m]) : 'content';
                            $meta_wrap[$m] = in_array($val, ['thumb','content'], true) ? $val : 'content';
                        }
                    }
                    // Styles (global + per-element)
                    $styles_in = isset($_POST['styles']) && is_array($_POST['styles']) ? $_POST['styles'] : [];
                    $styles = [
                        // layout presets
                        'layout_type'  => in_array(($styles_in['layout_type'] ?? 'list'), ['list','grid'], true) ? $styles_in['layout_type'] : 'list',
                        'grid_cols'    => max(1, intval($styles_in['grid_cols'] ?? 3)),
                        'grid_gap'     => isset($styles_in['grid_gap']) && $styles_in['grid_gap'] !== '' ? max(0, intval($styles_in['grid_gap'])) : null,
                        'grid_cols_tab'=> isset($styles_in['grid_cols_tab']) && $styles_in['grid_cols_tab'] !== '' ? max(1, intval($styles_in['grid_cols_tab'])) : 2,
                        'grid_cols_mob'=> isset($styles_in['grid_cols_mob']) && $styles_in['grid_cols_mob'] !== '' ? max(1, intval($styles_in['grid_cols_mob'])) : 1,
                        // legacy/globals
                        'primary'      => sanitize_hex_color($styles_in['primary'] ?? '#0d6efd') ?: '#0d6efd',
                        'text'         => sanitize_hex_color($styles_in['text'] ?? '#111111') ?: '#111111',
                        'font_size'    => max(10, intval($styles_in['font_size'] ?? 16)),
                        'spacing'      => max(0, intval($styles_in['spacing'] ?? 12)),
                        'radius'       => max(0, intval($styles_in['radius'] ?? 8)),
                        // animations
                        'anim_enable'  => !empty($styles_in['anim_enable']) ? true : false,
                        'anim_stagger_enable'     => !empty($styles_in['anim_stagger_enable']) ? true : false,
                        'anim_stagger_duration'   => isset($styles_in['anim_stagger_duration']) && $styles_in['anim_stagger_duration'] !== '' ? max(50, intval($styles_in['anim_stagger_duration'])) : 400,
                        'anim_stagger_delay_step' => isset($styles_in['anim_stagger_delay_step']) && $styles_in['anim_stagger_delay_step'] !== '' ? max(0, intval($styles_in['anim_stagger_delay_step'])) : 80,
                        'anim_stagger_offset'     => isset($styles_in['anim_stagger_offset']) && $styles_in['anim_stagger_offset'] !== '' ? max(0, intval($styles_in['anim_stagger_offset'])) : 8,
                        'anim_stagger_ease'       => ($styles_in['anim_stagger_ease'] ?? '') !== '' ? sanitize_text_field($styles_in['anim_stagger_ease']) : 'ease-out',
                        // card
                        'card_bg'      => sanitize_hex_color($styles_in['card_bg'] ?? '#ffffff') ?: '#ffffff',
                        'card_border'  => sanitize_hex_color($styles_in['card_border'] ?? '#e5e7eb') ?: '#e5e7eb',
                        'card_shadow'  => !empty($styles_in['card_shadow']) ? true : false,
                        'card_padding' => isset($styles_in['card_padding']) && $styles_in['card_padding'] !== '' ? max(0, intval($styles_in['card_padding'])) : null,
                        'card_margin_y'=> isset($styles_in['card_margin_y']) && $styles_in['card_margin_y'] !== '' ? max(0, intval($styles_in['card_margin_y'])) : null,
                        // title
                        'title_color'  => sanitize_hex_color($styles_in['title_color'] ?? '') ?: null,
                        'title_size'   => isset($styles_in['title_size']) && $styles_in['title_size'] !== '' ? max(10, intval($styles_in['title_size'])) : null,
                        'title_weight' => isset($styles_in['title_weight']) && $styles_in['title_weight'] !== '' ? max(100, min(900, intval($styles_in['title_weight']))) : 600,
                        'title_mt'     => isset($styles_in['title_mt']) && $styles_in['title_mt'] !== '' ? max(0, intval($styles_in['title_mt'])) : null,
                        'title_mb'     => isset($styles_in['title_mb']) && $styles_in['title_mb'] !== '' ? max(0, intval($styles_in['title_mb'])) : null,
                        'title_pad_v'  => isset($styles_in['title_pad_v']) && $styles_in['title_pad_v'] !== '' ? max(0, intval($styles_in['title_pad_v'])) : null,
                        'title_pad_h'  => isset($styles_in['title_pad_h']) && $styles_in['title_pad_h'] !== '' ? max(0, intval($styles_in['title_pad_h'])) : null,
                        'title_lh'     => isset($styles_in['title_lh']) && $styles_in['title_lh'] !== '' ? max(0.8, min(3.0, floatval($styles_in['title_lh']))) : null,
                        'title_align'  => in_array(($styles_in['title_align'] ?? ''), ['left','center','right'], true) ? $styles_in['title_align'] : null,
                        'title_w'      => ($styles_in['title_w'] ?? '') !== '' ? sanitize_text_field($styles_in['title_w']) : null,
                        'title_min_w'  => ($styles_in['title_min_w'] ?? '') !== '' ? sanitize_text_field($styles_in['title_min_w']) : null,
                        'title_max_w'  => ($styles_in['title_max_w'] ?? '') !== '' ? sanitize_text_field($styles_in['title_max_w']) : null,
                        // excerpt
                        'excerpt_color'=> sanitize_hex_color($styles_in['excerpt_color'] ?? '#333333') ?: '#333333',
                        'excerpt_size' => isset($styles_in['excerpt_size']) && $styles_in['excerpt_size'] !== '' ? max(10, intval($styles_in['excerpt_size'])) : null,
                        'excerpt_mt'   => isset($styles_in['excerpt_mt']) && $styles_in['excerpt_mt'] !== '' ? max(0, intval($styles_in['excerpt_mt'])) : null,
                        'excerpt_mb'   => isset($styles_in['excerpt_mb']) && $styles_in['excerpt_mb'] !== '' ? max(0, intval($styles_in['excerpt_mb'])) : null,
                        'excerpt_pad_v'=> isset($styles_in['excerpt_pad_v']) && $styles_in['excerpt_pad_v'] !== '' ? max(0, intval($styles_in['excerpt_pad_v'])) : null,
                        'excerpt_pad_h'=> isset($styles_in['excerpt_pad_h']) && $styles_in['excerpt_pad_h'] !== '' ? max(0, intval($styles_in['excerpt_pad_h'])) : null,
                        'excerpt_lh'   => isset($styles_in['excerpt_lh']) && $styles_in['excerpt_lh'] !== '' ? max(0.8, min(3.0, floatval($styles_in['excerpt_lh']))) : null,
                        'excerpt_align'=> in_array(($styles_in['excerpt_align'] ?? ''), ['left','center','right'], true) ? $styles_in['excerpt_align'] : null,
                        'excerpt_w'    => ($styles_in['excerpt_w'] ?? '') !== '' ? sanitize_text_field($styles_in['excerpt_w']) : null,
                        'excerpt_min_w'=> ($styles_in['excerpt_min_w'] ?? '') !== '' ? sanitize_text_field($styles_in['excerpt_min_w']) : null,
                        'excerpt_max_w'=> ($styles_in['excerpt_max_w'] ?? '') !== '' ? sanitize_text_field($styles_in['excerpt_max_w']) : null,
                        // content
                        'content_color'=> sanitize_hex_color($styles_in['content_color'] ?? '#333333') ?: '#333333',
                        'content_size' => isset($styles_in['content_size']) && $styles_in['content_size'] !== '' ? max(10, intval($styles_in['content_size'])) : null,
                        'content_mt'   => isset($styles_in['content_mt']) && $styles_in['content_mt'] !== '' ? max(0, intval($styles_in['content_mt'])) : null,
                        'content_mb'   => isset($styles_in['content_mb']) && $styles_in['content_mb'] !== '' ? max(0, intval($styles_in['content_mb'])) : null,
                        'content_pad_v'=> isset($styles_in['content_pad_v']) && $styles_in['content_pad_v'] !== '' ? max(0, intval($styles_in['content_pad_v'])) : null,
                        'content_pad_h'=> isset($styles_in['content_pad_h']) && $styles_in['content_pad_h'] !== '' ? max(0, intval($styles_in['content_pad_h'])) : null,
                        'content_lh'   => isset($styles_in['content_lh']) && $styles_in['content_lh'] !== '' ? max(0.8, min(3.0, floatval($styles_in['content_lh']))) : null,
                        'content_align'=> in_array(($styles_in['content_align'] ?? ''), ['left','center','right'], true) ? $styles_in['content_align'] : null,
                        'content_w'    => ($styles_in['content_w'] ?? '') !== '' ? sanitize_text_field($styles_in['content_w']) : null,
                        'content_min_w'=> ($styles_in['content_min_w'] ?? '') !== '' ? sanitize_text_field($styles_in['content_min_w']) : null,
                        'content_max_w'=> ($styles_in['content_max_w'] ?? '') !== '' ? sanitize_text_field($styles_in['content_max_w']) : null,
                        // meta
                        'meta_color'   => sanitize_hex_color($styles_in['meta_color'] ?? '#555555') ?: '#555555',
                        'meta_size'    => isset($styles_in['meta_size']) && $styles_in['meta_size'] !== '' ? max(10, intval($styles_in['meta_size'])) : null,
                        'meta_mt'      => isset($styles_in['meta_mt']) && $styles_in['meta_mt'] !== '' ? max(0, intval($styles_in['meta_mt'])) : null,
                        'meta_mb'      => isset($styles_in['meta_mb']) && $styles_in['meta_mb'] !== '' ? max(0, intval($styles_in['meta_mb'])) : null,
                        'meta_pad_v'   => isset($styles_in['meta_pad_v']) && $styles_in['meta_pad_v'] !== '' ? max(0, intval($styles_in['meta_pad_v'])) : null,
                        'meta_pad_h'   => isset($styles_in['meta_pad_h']) && $styles_in['meta_pad_h'] !== '' ? max(0, intval($styles_in['meta_pad_h'])) : null,
                        'meta_lh'      => isset($styles_in['meta_lh']) && $styles_in['meta_lh'] !== '' ? max(0.8, min(3.0, floatval($styles_in['meta_lh']))) : null,
                        'meta_align'   => in_array(($styles_in['meta_align'] ?? ''), ['left','center','right'], true) ? $styles_in['meta_align'] : null,
                        'meta_w'       => ($styles_in['meta_w'] ?? '') !== '' ? sanitize_text_field($styles_in['meta_w']) : null,
                        'meta_min_w'   => ($styles_in['meta_min_w'] ?? '') !== '' ? sanitize_text_field($styles_in['meta_min_w']) : null,
                        'meta_max_w'   => ($styles_in['meta_max_w'] ?? '') !== '' ? sanitize_text_field($styles_in['meta_max_w']) : null,
                        'meta_bg'      => isset($styles_in['meta_bg']) ? sanitize_text_field($styles_in['meta_bg']) : '',
                        'meta_pos'     => isset($styles_in['meta_pos']) ? sanitize_text_field($styles_in['meta_pos']) : '',
                        'meta_top'     => isset($styles_in['meta_top']) ? sanitize_text_field($styles_in['meta_top']) : '',
                        'meta_right'   => isset($styles_in['meta_right']) ? sanitize_text_field($styles_in['meta_right']) : '',
                        'meta_bottom'  => isset($styles_in['meta_bottom']) ? sanitize_text_field($styles_in['meta_bottom']) : '',
                        'meta_left'    => isset($styles_in['meta_left']) ? sanitize_text_field($styles_in['meta_left']) : '',
                        // image
                        'image_radius' => isset($styles_in['image_radius']) && $styles_in['image_radius'] !== '' ? max(0, intval($styles_in['image_radius'])) : null,
                        'image_mt'     => isset($styles_in['image_mt']) && $styles_in['image_mt'] !== '' ? max(0, intval($styles_in['image_mt'])) : null,
                        'image_mb'     => isset($styles_in['image_mb']) && $styles_in['image_mb'] !== '' ? max(0, intval($styles_in['image_mb'])) : null,
                        'image_pad_v'  => isset($styles_in['image_pad_v']) && $styles_in['image_pad_v'] !== '' ? max(0, intval($styles_in['image_pad_v'])) : null,
                        'image_pad_h'  => isset($styles_in['image_pad_h']) && $styles_in['image_pad_h'] !== '' ? max(0, intval($styles_in['image_pad_h'])) : null,
                        'image_align'  => in_array(($styles_in['image_align'] ?? ''), ['left','center','right'], true) ? $styles_in['image_align'] : null,
                        'image_w'      => ($styles_in['image_w'] ?? '') !== '' ? sanitize_text_field($styles_in['image_w']) : null,
                        'image_h'      => ($styles_in['image_h'] ?? '') !== '' ? sanitize_text_field($styles_in['image_h']) : null,
                        'image_min_w'  => ($styles_in['image_min_w'] ?? '') !== '' ? sanitize_text_field($styles_in['image_min_w']) : null,
                        'image_max_w'  => ($styles_in['image_max_w'] ?? '') !== '' ? sanitize_text_field($styles_in['image_max_w']) : null,
                        // image hover scale
                        'image_hover_scale_enable' => !empty($styles_in['image_hover_scale_enable']) ? true : false,
                        'image_hover_scale'        => isset($styles_in['image_hover_scale']) && $styles_in['image_hover_scale'] !== '' ? max(1.0, min(2.0, floatval($styles_in['image_hover_scale']))) : 1.05,
                        'image_hover_duration'     => isset($styles_in['image_hover_duration']) && $styles_in['image_hover_duration'] !== '' ? max(0, intval($styles_in['image_hover_duration'])) : 300,
                        'image_hover_ease'         => ($styles_in['image_hover_ease'] ?? '') !== '' ? sanitize_text_field($styles_in['image_hover_ease']) : 'ease',
                        'image_object_fit'         => ($styles_in['image_object_fit'] ?? '') !== '' && in_array(strtolower($styles_in['image_object_fit']), ['cover','contain','fill','none','scale-down'], true) ? strtolower($styles_in['image_object_fit']) : null,
                        // responsive scaling factors
                        'scale_tab'    => isset($styles_in['scale_tab']) && $styles_in['scale_tab'] !== '' ? max(0.1, min(3.0, floatval($styles_in['scale_tab']))) : 1.0,
                        'scale_mob'    => isset($styles_in['scale_mob']) && $styles_in['scale_mob'] !== '' ? max(0.1, min(3.0, floatval($styles_in['scale_mob']))) : 1.0,
                        // button
                        'button_bg'    => sanitize_hex_color($styles_in['button_bg'] ?? '') ?: null,
                        'button_text'  => sanitize_hex_color($styles_in['button_text'] ?? '#ffffff') ?: '#ffffff',
                        'button_radius'=> isset($styles_in['button_radius']) && $styles_in['button_radius'] !== '' ? max(0, intval($styles_in['button_radius'])) : null,
                        'button_pad_v' => max(0, intval($styles_in['button_pad_v'] ?? 8)),
                        'button_pad_h' => max(0, intval($styles_in['button_pad_h'] ?? 12)),
                        'button_mt'    => isset($styles_in['button_mt']) && $styles_in['button_mt'] !== '' ? max(0, intval($styles_in['button_mt'])) : null,
                        'button_mb'    => isset($styles_in['button_mb']) && $styles_in['button_mb'] !== '' ? max(0, intval($styles_in['button_mb'])) : null,
                        'button_lh'    => isset($styles_in['button_lh']) && $styles_in['button_lh'] !== '' ? max(0.8, min(3.0, floatval($styles_in['button_lh']))) : null,
                        'button_shadow'=> !empty($styles_in['button_shadow']) ? true : false,
                        'button_shadow_css' => isset($styles_in['button_shadow_css']) ? sanitize_text_field($styles_in['button_shadow_css']) : '',
                        'button_full'  => !empty($styles_in['button_full']) ? true : false,
                        'button_align' => in_array(($styles_in['button_align'] ?? ''), ['left','center','right'], true) ? $styles_in['button_align'] : null,
                        'button_w'     => ($styles_in['button_w'] ?? '') !== '' ? sanitize_text_field($styles_in['button_w']) : null,
                        'button_min_w' => ($styles_in['button_min_w'] ?? '') !== '' ? sanitize_text_field($styles_in['button_min_w']) : null,
                        'button_max_w' => ($styles_in['button_max_w'] ?? '') !== '' ? sanitize_text_field($styles_in['button_max_w']) : null,
                        'button_stick_bottom' => !empty($styles_in['button_stick_bottom']) ? true : false,
                        // button ripple
                        'button_ripple_enable'   => !empty($styles_in['button_ripple_enable']) ? true : false,
                        'button_ripple_color'    => sanitize_hex_color($styles_in['button_ripple_color'] ?? '') ?: null,
                        'button_ripple_opacity'  => isset($styles_in['button_ripple_opacity']) && $styles_in['button_ripple_opacity'] !== '' ? max(0.0, min(1.0, floatval($styles_in['button_ripple_opacity']))) : 0.7,
                        'button_ripple_scale'    => isset($styles_in['button_ripple_scale']) && $styles_in['button_ripple_scale'] !== '' ? max(1.0, min(4.0, floatval($styles_in['button_ripple_scale']))) : 2.25,
                        'button_ripple_duration' => isset($styles_in['button_ripple_duration']) && $styles_in['button_ripple_duration'] !== '' ? max(0, intval($styles_in['button_ripple_duration'])) : 500,
                        'button_ripple_ease'     => ($styles_in['button_ripple_ease'] ?? '') !== '' ? sanitize_text_field($styles_in['button_ripple_ease']) : 'ease',
                        // hover reveal
                        'hover_reveal_enable'   => !empty($styles_in['hover_reveal_enable']) ? true : false,
                        'hover_reveal_style'    => in_array(($styles_in['hover_reveal_style'] ?? 'solid'), ['solid','sheen'], true) ? $styles_in['hover_reveal_style'] : 'solid',
                        'hover_reveal_color'    => sanitize_hex_color($styles_in['hover_reveal_color'] ?? '#ffffff') ?: '#ffffff',
                        'hover_reveal_opacity'  => isset($styles_in['hover_reveal_opacity']) && $styles_in['hover_reveal_opacity'] !== '' ? max(0.0, min(1.0, floatval($styles_in['hover_reveal_opacity']))) : 0.15,
                        'hover_reveal_duration' => isset($styles_in['hover_reveal_duration']) && $styles_in['hover_reveal_duration'] !== '' ? max(0, intval($styles_in['hover_reveal_duration'])) : 400,
                        'hover_reveal_ease'     => ($styles_in['hover_reveal_ease'] ?? '') !== '' ? sanitize_text_field($styles_in['hover_reveal_ease']) : 'ease',
                        'hover_reveal_angle'    => isset($styles_in['hover_reveal_angle']) && $styles_in['hover_reveal_angle'] !== '' ? intval($styles_in['hover_reveal_angle']) : 20,
                        'hover_reveal_thickness'=> ($styles_in['hover_reveal_thickness'] ?? '') !== '' ? sanitize_text_field($styles_in['hover_reveal_thickness']) : '20%',
                        'hover_reveal_direction'=> in_array(($styles_in['hover_reveal_direction'] ?? 'tl-br'), ['tl-br','br-tl'], true) ? $styles_in['hover_reveal_direction'] : 'tl-br',
                    ];
                    $cfg = [ 'layout' => ['order'=>$order,'enabled'=>$enabled,'enabled_tab'=>$enabled_tab,'enabled_mob'=>$enabled_mob,'meta_keys'=>$meta_keys,'meta_wrap'=>$meta_wrap], 'styles'=>$styles ];
                    $saved = $this->set_styles_config($cpt, $cfg);
                    add_settings_error('cphub', 'styles_saved', 'Styles saved (version ' . substr($saved['version'],0,7) . ').', 'updated');
                } else {
                    add_settings_error('cphub', 'styles_error', 'Invalid CPT for styles.', 'error');
                }
            }

            // Copy Styles config from another CPT
            if ($_POST['cphub_action'] === 'styles_copy') {
                $to   = sanitize_key($_POST['cpt'] ?? '');
                $from = sanitize_key($_POST['copy_from'] ?? '');
                if (!$to || !isset($types[$to])) {
                    add_settings_error('cphub', 'styles_copy_to', 'Invalid destination CPT for styles copy.', 'error');
                } elseif (!$from || !isset($types[$from])) {
                    add_settings_error('cphub', 'styles_copy_from', 'Invalid source CPT for styles copy.', 'error');
                } elseif ($to === $from) {
                    add_settings_error('cphub', 'styles_copy_same', 'Source and destination CPT are the same.', 'error');
                } else {
                    $all = get_option(self::OPTION_STYLES, []);
                    $src = $all[$from] ?? null;
                    if (!$src || !is_array($src)) {
                        add_settings_error('cphub', 'styles_copy_missing', 'No styles found on source CPT.', 'error');
                    } else {
                        // Save a fresh copy to destination with new version/modified
                        $saved = $this->set_styles_config($to, $src);
                        add_settings_error('cphub', 'styles_copied', sprintf('Styles copied from %s (version %s).', esc_html($from), substr($saved['version'],0,7)), 'updated');
                    }
                }
            }

            // Save Global CSS
            if ($_POST['cphub_action'] === 'global_save') {
                $css = isset($_POST['css']) ? (string)$_POST['css'] : '';
                // Normalize line endings
                $css = str_replace(["\r\n","\r"], "\n", $css);
                $ver = md5($css);
                $payload = [
                    'css'      => $css,
                    'version'  => $ver,
                    'modified' => time(),
                ];
                update_option(self::OPTION_GLOBAL_CSS, $payload, false);
                add_settings_error('cphub', 'global_saved', 'Global CSS saved.', 'updated');
            }
        }
    }

    public function handle_cleanup_old()
    {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        check_admin_referer('cphub_publisher_cleanup');

        // Remove old Elementor-related meta across all posts
        if (function_exists('delete_post_meta_by_key')) {
            delete_post_meta_by_key('_cphub_el_template');
            delete_post_meta_by_key('cphub_el_template_key');
            delete_post_meta_by_key('cphub_el_template_title');
        }

        // Remove archive_template keys from CPT definitions and prune orphan styles
        $types  = get_option(self::OPTION_KEY, []);
        $styles = get_option(self::OPTION_STYLES, []);
        if (is_array($types)) {
            foreach ($types as $slug => &$def) {
                if (isset($def['archive_template'])) unset($def['archive_template']);
            }
            unset($def);
            update_option(self::OPTION_KEY, $types, false);
        }
        if (is_array($styles)) {
            $keep = array_keys($types ?: []);
            foreach (array_keys($styles) as $slug) {
                if (!in_array($slug, $keep, true)) unset($styles[$slug]);
            }
            update_option(self::OPTION_STYLES, $styles, false);
        }

        // Flush feed caches
        $this->flush_all_feed_caches();

        add_settings_error('cphub', 'cleanup_ok', 'Cleanup complete: removed old meta, pruned styles, and cleared caches.', 'updated');
        wp_safe_redirect(add_query_arg(['page' => 'cphub', 'tab' => 'feed'], admin_url('admin.php')));
        exit;
    }

    public function render_admin_page()
    {
        if (!current_user_can('manage_options')) return;
        settings_errors('cphub');

        $types = get_option(self::OPTION_KEY, []);
        $feed  = get_option(self::OPTION_FEED, ['items_per_feed' => 20, 'secret_key' => '']);
        $taxes = get_option(self::OPTION_TAX, []);
        $feed_base     = home_url('/feed/cphub');
        $example_cpt   = key($types) ?: 'your_cpt_slug';
        $example_url   = esc_url(trailingslashit($feed_base) . $example_cpt);
        $example_query = esc_url(add_query_arg(['feed' => 'cphub', 'cpt' => $example_cpt], home_url('/')));
        $edit_slug = isset($_GET['edit']) ? sanitize_key($_GET['edit']) : '';
        $edit_def  = ($edit_slug && isset($types[$edit_slug])) ? $types[$edit_slug] : null;
        $tax_edit_slug = isset($_GET['tax_edit']) ? sanitize_key($_GET['tax_edit']) : '';
        $tax_edit_def  = ($tax_edit_slug && isset($taxes[$tax_edit_slug])) ? $taxes[$tax_edit_slug] : null;

        // Tabbed layout: render tab content and return early
        $tab  = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'types';
        $base = plugin_dir_path(__FILE__);
?>
        <div class="wrap">
            <h1>CPT Hub – Publisher</h1>
            <h2 class="nav-tab-wrapper">
                <?php $tabs = ['types' => 'Content Types', 'tax' => 'Taxonomies', 'feed' => 'Feed Settings', 'styles' => 'Styles', 'global' => 'Global CSS', 'code' => 'Register Code', 'docs' => 'Documentation'];
                foreach ($tabs as $t_key => $t_label):
                    $url = esc_url(add_query_arg(['page' => 'cphub', 'tab' => $t_key], admin_url('admin.php')));
                    $class = 'nav-tab' . ($tab === $t_key ? ' nav-tab-active' : ''); ?>
                    <a href="<?php echo $url; ?>" class="<?php echo esc_attr($class); ?>"><?php echo esc_html($t_label); ?></a>
                <?php endforeach; ?>
            </h2>
            <div class="cphub-tab-panel">
                <?php
                switch ($tab) {
                    case 'tax':
                        include $base . 'views/admin/tab-tax.php';
                        break;
                    case 'feed':
                        include $base . 'views/admin/tab-feed.php';
                        break;
                    case 'styles':
                        include $base . 'views/admin/tab-styles.php';
                        break;
                    case 'global':
                        include $base . 'views/admin/tab-global.php';
                        break;
                    case 'docs':
                        include $base . 'views/admin/tab-docs.php';
                        break;
                    case 'code':
                        include $base . 'views/admin/tab-code.php';
                        break;
                    case 'types':
                    default:
                        include $base . 'views/admin/tab-types.php';
                        break;
                }
                ?>
            </div>
        </div>
    <?php
        return;
?>
        <div class="wrap">
            <h1>CPT Hub – Publisher</h1>

            <h2 class="title">Your Content Types</h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Slug</th>
                        <th>Label</th>
                        <th>Supports</th>
                        <th>Archive</th>
                        <th>Feed</th>
                        <th style="width:120px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$types): ?>
                        <tr>
                            <td colspan="6">No custom post types yet. Add one below.</td>
                        </tr>
                        <?php else: foreach ($types as $slug => $def): ?>
                            <tr>
                                <td><code><?php echo esc_html($slug); ?></code></td>
                                <td><?php echo esc_html($def['label']); ?></td>
                                <td><?php echo esc_html(implode(', ', $def['supports'])); ?></td>
                                <td><?php echo !empty($def['has_archive']) ? 'Yes' : 'No'; ?></td>
                                <td>
                                    <div>Pretty: <a href="<?php echo esc_url(trailingslashit($feed_base) . $slug); ?>" target="_blank">/feed/cphub/<?php echo esc_html($slug); ?></a></div>
                                    <div>Query: <a href="<?php echo esc_url(add_query_arg(['feed' => 'cphub', 'cpt' => $slug], home_url('/'))); ?>" target="_blank">?feed=cphub&amp;cpt=<?php echo esc_html($slug); ?></a></div>
                                </td>
                                <td>
                                    <a class="button" href="<?php echo esc_url(add_query_arg(['page' => 'cphub', 'edit' => $slug], admin_url('admin.php'))); ?>">Edit</a>
                                    <form method="post" style="display:inline">
                                        <?php wp_nonce_field(self::NONCE_ACTION); ?>
                                        <input type="hidden" name="cphub_action" value="delete">
                                        <input type="hidden" name="slug" value="<?php echo esc_attr($slug); ?>">
                                        <button class="button button-link-delete" onclick="return confirm('Delete this CPT? Posts will remain registered under their type until you remove them manually.');">Delete</button>
                                    </form>
                                </td>
                            </tr>
                    <?php endforeach;
                    endif; ?>
                </tbody>
            </table>

            <?php if ($edit_def): ?>
            <h2 class="title" style="margin-top:2em;">Edit Type</h2>
            <form method="post" class="card" style="padding:1em;max-width:100%;">
                <?php wp_nonce_field(self::NONCE_ACTION); ?>
                <input type="hidden" name="cphub_action" value="update">
                <input type="hidden" name="slug" value="<?php echo esc_attr($edit_slug); ?>">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Slug</th>
                        <td><code><?php echo esc_html($edit_slug); ?></code> <span class="description">Slug cannot be changed here.</span></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cphub_label_edit">Label</label></th>
                        <td><input id="cphub_label_edit" name="label" type="text" class="regular-text" value="<?php echo esc_attr($edit_def['label']); ?>" required></td>
                    </tr>
                    <tr>
                        <th scope="row">Supports</th>
                        <td>
                            <?php $opts = ['title' => 'Title', 'editor' => 'Editor', 'excerpt' => 'Excerpt', 'thumbnail' => 'Featured Image', 'custom-fields' => 'Custom Fields'];
                            foreach ($opts as $key => $lab): ?>
                                <label style="margin-right:1em;"><input type="checkbox" name="supports[]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, (array)$edit_def['supports'], true)); ?>> <?php echo esc_html($lab); ?></label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Has Archive</th>
                        <td><label><input type="checkbox" name="has_archive" value="1" <?php checked(!empty($edit_def['has_archive'])); ?>> Enable archive</label></td>
                    </tr>
                    <tr>
                        <th scope="row">Assigned Taxonomies</th>
                        <td>
                            <?php if (!$taxes) { echo '<em>No custom taxonomies yet. Add some below.</em>'; }
                            foreach ($taxes as $t_slug => $t_def): ?>
                                <label style="margin-right:1em; display:inline-block;">
                                    <input type="checkbox" name="taxonomies[]" value="<?php echo esc_attr($t_slug); ?>" <?php checked(in_array($t_slug, (array)($edit_def['taxonomies'] ?? []), true)); ?>>
                                    <?php echo esc_html($t_def['label']); ?> <code>(<?php echo esc_html($t_slug); ?>)</code>
                                </label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Custom Meta Fields</th>
                        <td>
                            <p class="description">Define fields stored as public post meta and exposed in the feed. Avoid leading underscores in keys.</p>
                            <table class="widefat" style="max-width:860px;">
                                <thead>
                                    <tr>
                                        <th style="width:20%">Key</th>
                                        <th style="width:30%">Label</th>
                                        <th style="width:20%">Type</th>
                                        <th class="cphub-col-extra">Field Options</th>
                                    </tr>
                                </thead>
                                <tbody id="cphub-fields-rows">
                                    <?php $fields = isset($edit_def['fields']) && is_array($edit_def['fields']) ? $edit_def['fields'] : []; ?>
                                    <?php if (!$fields) $fields = []; ?>
                                    <?php foreach ($fields as $f): ?>
                                        <tr>
                                            <td><input type="text" name="fields[key][]" value="<?php echo esc_attr($f['key']); ?>" placeholder="e.g. price" /></td>
                                            <td><input type="text" name="fields[label][]" value="<?php echo esc_attr($f['label']); ?>" placeholder="e.g. Price" /></td>
                                            <td>
                                                <select name="fields[type][]">
                                                    <?php $typesel = $f['type'] ?? 'text';
                                                    foreach (['text'=>'Text','textarea'=>'Textarea','number'=>'Number','url'=>'URL','select'=>'Select','media'=>'Media'] as $tk=>$tv): ?>
                                                        <option value="<?php echo esc_attr($tk); ?>" <?php selected($typesel === $tk); ?>><?php echo esc_html($tv); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td class="cphub-field-extra">
                                                <input type="text" class="cphub-extra-select" name="fields[options][]" value="<?php echo esc_attr(isset($f['options']) ? implode(', ', (array)$f['options']) : ''); ?>" placeholder="Red, Green, Blue" />
                                                <?php $mt = isset($f['media_type']) ? $f['media_type'] : 'file'; ?>
                                                <select class="cphub-extra-media" name="fields[media_type][]">
                                                    <option value="file" <?php selected($mt==='file'); ?>>Any File</option>
                                                    <option value="image" <?php selected($mt==='image'); ?>>Image Only</option>
                                                </select>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <!-- blank row template -->
                                    <tr class="cphub-field-template" style="display:none;">
                                        <td><input type="text" name="fields[key][]" placeholder="e.g. price" /></td>
                                        <td><input type="text" name="fields[label][]" placeholder="e.g. Price" /></td>
                                        <td>
                                            <select name="fields[type][]">
                                                <option value="text">Text</option>
                                                <option value="textarea">Textarea</option>
                                                <option value="number">Number</option>
                                                <option value="url">URL</option>
                                                <option value="select">Select</option>
                                                <option value="media">Media</option>
                                            </select>
                                        </td>
                                        <td class="cphub-field-extra">
                                            <input type="text" class="cphub-extra-select" name="fields[options][]" placeholder="Red, Green, Blue" />
                                            <select class="cphub-extra-media" name="fields[media_type][]">
                                                <option value="file">Any File</option>
                                                <option value="image">Image Only</option>
                                            </select>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <p><button type="button" class="button" id="cphub-add-field">Add field</button></p>
                            <script>
                                (function(){
                                    var btn = document.getElementById('cphub-add-field');
                                    if (!btn) return;
                                    function wireRow(row){
                                        var sel = row.querySelector('select[name="fields[type][]"]');
                                        var extraCell = row.querySelector('.cphub-field-extra');
                                        if (!sel || !extraCell) return;
                                        var optInput = extraCell.querySelector('.cphub-extra-select');
                                        var mediaSelect = extraCell.querySelector('.cphub-extra-media');
                                        function apply(){
                                            if (sel.value === 'select') {
                                                extraCell.style.display = '';
                                                if (optInput) { optInput.style.display=''; optInput.disabled=false; }
                                                if (mediaSelect) { mediaSelect.style.display='none'; mediaSelect.disabled=true; }
                                            } else if (sel.value === 'media') {
                                                extraCell.style.display = '';
                                                if (optInput) { optInput.style.display='none'; optInput.disabled=true; }
                                                if (mediaSelect) { mediaSelect.style.display=''; mediaSelect.disabled=false; }
                                            } else {
                                                extraCell.style.display = 'none';
                                                if (optInput) { optInput.disabled=true; }
                                                if (mediaSelect) { mediaSelect.disabled=true; }
                                            }
                                        }
                                        sel.addEventListener('change', apply);
                                        apply();
                                    }
                                    // Wire existing rows
                                    document.querySelectorAll('#cphub-fields-rows > tr:not(.cphub-field-template)')
                                        .forEach(function(r){ wireRow(r); });
                                    // Add new rows
                                    btn.addEventListener('click', function(){
                                        var tbody = document.getElementById('cphub-fields-rows');
                                        var tpl = tbody.querySelector('.cphub-field-template');
                                        if (!tpl) return;
                                        var clone = tpl.cloneNode(true);
                                        clone.style.display = '';
                                        clone.classList.remove('cphub-field-template');
                                        tbody.appendChild(clone);
                                        wireRow(clone);
                                    });
                                })();
                            </script>
                        </td>
                    </tr>
                </table>
                <p>
                    <button class="button button-primary">Save Changes</button>
                    <a class="button" href="<?php echo esc_url(remove_query_arg('edit')); ?>">Cancel</a>
                </p>
            </form>
            <?php endif; ?>

            <h2 class="title" style="margin-top:2em;">Add New Type</h2>
            <form method="post" class="card" style="padding:1em;max-width:100%;">
                <?php wp_nonce_field(self::NONCE_ACTION); ?>
                <input type="hidden" name="cphub_action" value="add">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="cphub_slug">Slug</label></th>
                        <td><input id="cphub_slug" name="slug" type="text" class="regular-text" placeholder="e.g. slides, recipes, dealers" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cphub_label">Label</label></th>
                        <td><input id="cphub_label" name="label" type="text" class="regular-text" placeholder="Plural label shown in admin" required></td>
                    </tr>
                    <tr>
                        <th scope="row">Supports</th>
                        <td>
                            <?php
                            $opts = ['title' => 'Title', 'editor' => 'Editor', 'excerpt' => 'Excerpt', 'thumbnail' => 'Featured Image', 'custom-fields' => 'Custom Fields'];
                            foreach ($opts as $key => $lab): ?>
                                <label style="margin-right:1em;"><input type="checkbox" name="supports[]" value="<?php echo esc_attr($key); ?>"> <?php echo esc_html($lab); ?></label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Has Archive</th>
                        <td><label><input type="checkbox" name="has_archive" value="1"> Enable archive</label></td>
                    </tr>
                </table>
                <p><button class="button button-primary">Add Type</button></p>
            </form>

            <h2 class="title" style="margin-top:2em;">Custom Taxonomies</h2>
            <table class="widefat striped" style="max-width:100%;">
                <thead>
                    <tr>
                        <th>Slug</th>
                        <th>Label</th>
                        <th>Hierarchical</th>
                        <th style="width:180px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$taxes): ?>
                        <tr><td colspan="4">No taxonomies yet. Add one below.</td></tr>
                    <?php else: foreach ($taxes as $t_slug => $t_def): ?>
                        <tr>
                            <td><code><?php echo esc_html($t_slug); ?></code></td>
                            <td><?php echo esc_html($t_def['label']); ?></td>
                            <td><?php echo !empty($t_def['hierarchical']) ? 'Yes' : 'No'; ?></td>
                            <td>
                                <a class="button" href="<?php echo esc_url(add_query_arg(['page' => 'cphub', 'tax_edit' => $t_slug], admin_url('admin.php'))); ?>">Edit</a>
                                <form method="post" style="display:inline">
                                    <?php wp_nonce_field(self::NONCE_ACTION); ?>
                                    <input type="hidden" name="cphub_action" value="tax_delete">
                                    <input type="hidden" name="slug" value="<?php echo esc_attr($t_slug); ?>">
                                    <button class="button button-link-delete" onclick="return confirm('Delete this taxonomy? Terms will remain in the database but may become orphaned.');">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>

            <?php if ($tax_edit_def): ?>
            <h3 class="title" style="margin-top:1em;">Edit Taxonomy</h3>
            <form method="post" class="card" style="padding:1em;max-width:100%;">
                <?php wp_nonce_field(self::NONCE_ACTION); ?>
                <input type="hidden" name="cphub_action" value="tax_update">
                <input type="hidden" name="slug" value="<?php echo esc_attr($tax_edit_slug); ?>">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Slug</th>
                        <td><code><?php echo esc_html($tax_edit_slug); ?></code> <span class="description">Slug cannot be changed here.</span></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cphub_tax_label_edit">Label</label></th>
                        <td><input id="cphub_tax_label_edit" name="label" type="text" class="regular-text" value="<?php echo esc_attr($tax_edit_def['label']); ?>" required></td>
                    </tr>
                    <tr>
                        <th scope="row">Hierarchical</th>
                        <td><label><input type="checkbox" name="hierarchical" value="1" <?php checked(!empty($tax_edit_def['hierarchical'])); ?>> Category-like (unchecked = tag-like)</label></td>
                    </tr>
                </table>
                <p>
                    <button class="button button-primary">Save Taxonomy</button>
                    <a class="button" href="<?php echo esc_url(remove_query_arg('tax_edit')); ?>">Cancel</a>
                </p>
            </form>
            <?php endif; ?>

            <h3 class="title" style="margin-top:1em;">Add Taxonomy</h3>
            <form method="post" class="card" style="padding:1em;max-width:100%;">
                <?php wp_nonce_field(self::NONCE_ACTION); ?>
                <input type="hidden" name="cphub_action" value="tax_add">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="cphub_tax_slug">Slug</label></th>
                        <td><input id="cphub_tax_slug" name="slug" type="text" class="regular-text" placeholder="e.g. brand, region" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cphub_tax_label">Label</label></th>
                        <td><input id="cphub_tax_label" name="label" type="text" class="regular-text" placeholder="Plural label shown in admin" required></td>
                    </tr>
                    <tr>
                        <th scope="row">Hierarchical</th>
                        <td><label><input type="checkbox" name="hierarchical" value="1"> Category-like (unchecked = tag-like)</label></td>
                    </tr>
                </table>
                <p><button class="button button-primary">Add Taxonomy</button></p>
            </form>

            <h2 class="title" style="margin-top:2em;">Feed Settings</h2>
            <form method="post" action="options.php" class="card" style="padding:1em;max-width:100%;">
                <?php settings_fields('cphub_feed'); ?>
                <?php $fs = get_option(self::OPTION_FEED, []); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="items_per_feed">Items per feed</label></th>
                        <td><input id="items_per_feed" name="<?php echo esc_attr(self::OPTION_FEED); ?>[items_per_feed]" type="number" min="1" value="<?php echo esc_attr($fs['items_per_feed'] ?? 20); ?>"> <span class="description">Default limit (override with <code>&n=NN</code> in URL)</span></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="secret_key">Optional secret key</label></th>
                        <td>
                            <input id="secret_key" name="<?php echo esc_attr(self::OPTION_FEED); ?>[secret_key]" type="text" class="regular-text" value="<?php echo esc_attr($fs['secret_key'] ?? ''); ?>">
                            <p class="description">If set, consumers must include <code>&key=YOUR_KEY</code> (or pretty URL <code>/feed/cphub/&lt;cpt&gt;?key=...</code>).</p>
                        </td>
                    </tr>
                </table>
                <p><button class="button button-primary">Save Feed Settings</button></p>
            </form>

            <h2 class="title" style="margin-top:2em;">How to Consume</h2>
            <p>Each CPT has its own feed:
                <br>Pretty URL: <code><?php echo esc_html($example_url); ?></code>
                <br>Query URL: <code><?php echo esc_html($example_query); ?></code>
                <br>Add <code>&n=50</code> to change page size. Paginate with <code>&paged=2</code>. Filter by update date with <code>&modified_since=2025-01-01</code> (YYYY-MM-DD).
            </p>
        </div>
    <?php
    }

    /* ---------------------- Register Taxonomies ------------- */
    public function register_dynamic_taxonomies()
    {
        $taxes = get_option(self::OPTION_TAX, []);
        $types = get_option(self::OPTION_KEY, []);

        // Register global 'location' taxonomy for all CPTs
        $all_cpts = array_keys($types);
        $loc_labels = [
            'name'          => 'Locations',
            'singular_name' => 'Location',
            'menu_name'     => 'Locations',
        ];
        $loc_args = [
            'labels'        => $loc_labels,
            'hierarchical'  => false,
            'public'        => true,
            'show_in_rest'  => true,
        ];
        register_taxonomy('location', $all_cpts ?: null, $loc_args);
        if (!term_exists('all-locations', 'location')) {
            wp_insert_term('all-locations', 'location', ['description' => 'Applies to all locations']);
        }

        if (!$taxes) return;

        // Build mapping: taxonomy => [post types]
        $map = [];
        foreach ($types as $slug => $def) {
            $assigned = isset($def['taxonomies']) ? (array)$def['taxonomies'] : [];
            foreach ($assigned as $t) {
                $map[$t][] = $slug;
            }
        }

        foreach ($taxes as $t_slug => $t_def) {
            $labels = [
                'name'          => $t_def['label'],
                'singular_name' => ucfirst($t_slug),
                'menu_name'     => $t_def['label'],
            ];
            $args = [
                'labels'        => $labels,
                'hierarchical'  => !empty($t_def['hierarchical']),
                'public'        => !empty($t_def['public']),
                'show_in_rest'  => !empty($t_def['show_in_rest']),
            ];
            $object_types = isset($map[$t_slug]) ? array_values(array_unique($map[$t_slug])) : [];
            register_taxonomy($t_slug, $object_types ?: null, $args);
        }
    }

    /* ---------------------- Register CPTs -------------------- */
    public function register_dynamic_cpts()
    {
        $types = get_option(self::OPTION_KEY, []);
        foreach ($types as $slug => $def) {
            if (strlen($slug) > 20) {
                // Invalid CPT key length; skip registering to avoid WP notices
                continue;
            }
            $labels = [
                'name'          => $def['label'],
                'singular_name' => ucfirst($slug),
                'menu_name'     => $def['label'],
            ];
            $assigned_tax = isset($def['taxonomies']) ? (array)$def['taxonomies'] : [];
            $assigned_tax[] = 'location';
            $assigned_tax = array_values(array_unique(array_filter($assigned_tax)));

            $rw_slug = isset($def['rewrite_slug']) && is_string($def['rewrite_slug']) && $def['rewrite_slug'] !== '' ? sanitize_title($def['rewrite_slug']) : $slug;
            $has_arch = !empty($def['has_archive']) ? ($rw_slug ?: true) : false;
            $args = [
                'labels'       => $labels,
                'public'       => true,
                'show_in_rest' => true,
                'has_archive'  => $has_arch,
                'supports'     => $def['supports'] ?: ['title'],
                'rewrite'      => ['slug' => $rw_slug, 'with_front' => false],
                'taxonomies'   => $assigned_tax,
            ];
            register_post_type($slug, $args);
        }
    }

    /* ---------------------- Feed ---------------------------- */
    public function register_feed()
    {
        // Single feed name: /?feed=cphub or /feed/cphub
        add_feed('cphub', [$this, 'render_feed']);

        // Pretty permalinks for per-CPT: /feed/cphub/{cpt}
        add_rewrite_rule('^feed/cphub/([^/]+)/?$', 'index.php?feed=cphub&cpt=$matches[1]', 'top');
    }

    public function register_query_vars($vars)
    {
        $vars[] = 'cpt';
        $vars[] = 'n';
        $vars[] = 'paged';
        $vars[] = 'key';
        $vars[] = 'modified_since';
        $vars[] = 'location';
        return $vars;
    }

    private function get_cache_key($args)
    {
        ksort($args);
        return 'cphub_feed_' . md5(serialize($args));
    }

    /* ---------------------- REST API ----------------------- */
    public function register_rest()
    {
        register_rest_route('cphub/v1', '/items', [
            'methods'  => 'GET',
            'callback' => [$this, 'rest_items'],
            'permission_callback' => '__return_true',
            'args' => [
                'cpt' => [ 'type' => 'string', 'required' => false ],
                'n'   => [ 'type' => 'integer', 'required' => false ],
                'paged' => [ 'type' => 'integer', 'required' => false ],
                'modified_since' => [ 'type' => 'string', 'required' => false ],
                'location' => [ 'type' => 'string', 'required' => false ],
                'key' => [ 'type' => 'string', 'required' => false ],
            ],
        ]);
        register_rest_route('cphub/v1', '/assets', [
            'methods'  => 'GET',
            'callback' => [$this, 'rest_assets'],
            'permission_callback' => '__return_true',
            'args' => [
                'cpt' => [ 'type' => 'string', 'required' => true ],
                'key' => [ 'type' => 'string', 'required' => false ],
            ],
        ]);
        register_rest_route('cphub/v1', '/global', [
            'methods'  => 'GET',
            'callback' => [$this, 'rest_global_css'],
            'permission_callback' => '__return_true',
            'args' => [
                'key' => [ 'type' => 'string', 'required' => false ],
            ],
        ]);
        register_rest_route('cphub/v1', '/health', [
            'methods'  => 'GET',
            'callback' => [$this, 'rest_health'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function rest_items(WP_REST_Request $request)
    {
        // Gate: optional secret key
        $feed_settings = get_option(self::OPTION_FEED, []);
        $secret = $feed_settings['secret_key'] ?? '';
        $req_key = sanitize_text_field($request->get_param('key'));
        if ($secret && $req_key !== $secret) {
            return new WP_Error('cphub_forbidden', 'Forbidden: invalid or missing key', ['status' => 403]);
        }

        $types = get_option(self::OPTION_KEY, []);
        $cpt   = sanitize_key((string)$request->get_param('cpt'));
        $per   = intval($request->get_param('n')) ?: intval($feed_settings['items_per_feed'] ?? 20);
        // Cap page size to a safe maximum
        $per   = max(1, min(100, $per));
        $paged = max(1, intval($request->get_param('paged')));
        $modified_since = sanitize_text_field((string)$request->get_param('modified_since'));
        $location = sanitize_key((string)$request->get_param('location'));

        if ($cpt && !isset($types[$cpt])) {
            return new WP_Error('cphub_bad_cpt', 'Unknown CPT: ' . $cpt, ['status' => 400]);
        }

        $query_args = [
            'post_type'           => $cpt ? [$cpt] : array_keys($types),
            'post_status'         => 'publish',
            'posts_per_page'      => $per,
            'paged'               => $paged,
            'ignore_sticky_posts' => true,
            'orderby'             => 'date',
            'order'               => 'DESC',
        ];
        if ($modified_since && preg_match('/^\d{4}-\d{2}-\d{2}$/', $modified_since)) {
            $query_args['date_query'] = [ ['column' => 'post_modified_gmt', 'after' => $modified_since] ];
            $query_args['orderby'] = 'modified';
        }
        if ($location) {
            $query_args['tax_query'] = [[
                'taxonomy' => 'location',
                'field'    => 'slug',
                'terms'    => [$location, 'all-locations'],
                'operator' => 'IN',
            ]];
        }

        $cache_key = $this->get_cache_key($query_args) . '_json';
        $payload = get_transient($cache_key);
        if ($payload === false) {
            $q = new WP_Query($query_args);
            $items = [];
            if ($q->have_posts()) {
                while ($q->have_posts()) {
                    $q->the_post();
                    $items[] = $this->map_post_to_feed_item(get_post());
                }
                wp_reset_postdata();
            }
            $payload = [
                'paged'       => (int)$paged,
                'per_page'    => (int)$per,
                'total'       => isset($q) ? (int)$q->found_posts : 0,
                'total_pages' => isset($q) ? (int)$q->max_num_pages : 0,
                'items'       => $items,
            ];
            set_transient($cache_key, $payload, 60 * 5);
        }

        // Compute Last-Modified from items (max of item.modified)
        $last_mod_ts = 0;
        if (!empty($payload['items']) && is_array($payload['items'])) {
            foreach ($payload['items'] as $it) {
                if (!empty($it['modified'])) {
                    $ts = strtotime($it['modified']);
                    if ($ts && $ts > $last_mod_ts) $last_mod_ts = $ts;
                }
            }
        }
        $last_modified_http = $last_mod_ts ? gmdate('D, d M Y H:i:s', $last_mod_ts) . ' GMT' : '';

        // Build an ETag from args + last modified + total
        $etag_raw = md5(wp_json_encode([
            'cpt' => $cpt,
            'per' => $per,
            'paged' => $paged,
            'modified_since' => $modified_since,
            'location' => $location,
            'last' => $last_mod_ts,
            'total' => (int)($payload['total'] ?? 0),
            'v' => 1,
        ]));
        $etag = '"' . $etag_raw . '"';

        // Conditional request handling
        $if_none_match    = trim((string)$request->get_header('if-none-match'));
        $if_modified_since= trim((string)$request->get_header('if-modified-since'));
        $if_none_match    = $if_none_match !== '' ? $if_none_match : '';

        $send_304 = false;
        if ($if_none_match && $if_none_match === $etag) {
            $send_304 = true;
        } elseif ($last_mod_ts && $if_modified_since) {
            $ims_ts = strtotime($if_modified_since);
            if ($ims_ts && $ims_ts >= $last_mod_ts) $send_304 = true;
        }

        if ($send_304) {
            $resp = new WP_REST_Response(null, 304);
            $resp->header('ETag', $etag);
            if ($last_modified_http) $resp->header('Last-Modified', $last_modified_http);
            $resp->header('Cache-Control', 'public, max-age=300');
            return $resp;
        }

        $response = new WP_REST_Response($payload, 200);
        $response->header('ETag', $etag);
        if ($last_modified_http) $response->header('Last-Modified', $last_modified_http);
        $response->header('Cache-Control', 'public, max-age=300');
        return $response;
    }

    public function rest_assets(WP_REST_Request $request)
    {
        $feed_settings = get_option(self::OPTION_FEED, []);
        $secret = $feed_settings['secret_key'] ?? '';
        $req_key = sanitize_text_field($request->get_param('key'));
        if ($secret && $req_key !== $secret) {
            return new WP_Error('cphub_forbidden', 'Forbidden: invalid or missing key', ['status' => 403]);
        }
        $cpt = sanitize_key((string)$request->get_param('cpt'));
        $types = get_option(self::OPTION_KEY, []);
        if (!$cpt || !isset($types[$cpt])) {
            return new WP_Error('cphub_bad_cpt', 'Unknown CPT: ' . $cpt, ['status' => 400]);
        }
        $cfg = $this->get_styles_config($cpt);
        // Inject responsive visibility maps into styles for CSS builder
        $styles = $cfg['styles'];
        $enabled_tab = isset($cfg['layout']['enabled_tab']) ? (array)$cfg['layout']['enabled_tab'] : [];
        $enabled_mob = isset($cfg['layout']['enabled_mob']) ? (array)$cfg['layout']['enabled_mob'] : [];
        // Fallbacks: if not set, inherit desktop enabled
        if (!$enabled_tab) {
            $enabled_tab = [
                'title' => !empty($cfg['layout']['enabled']['title']),
                'image' => !empty($cfg['layout']['enabled']['image']),
                'excerpt' => !empty($cfg['layout']['enabled']['excerpt']),
                'content' => !empty($cfg['layout']['enabled']['content']),
                'meta' => (!empty($cfg['layout']['enabled']['meta1']) || !empty($cfg['layout']['enabled']['meta2']) || !empty($cfg['layout']['enabled']['meta3'])),
                'button' => !empty($cfg['layout']['enabled']['button']),
            ];
        }
        if (!$enabled_mob) {
            $enabled_mob = $enabled_tab;
        }
        $styles['__vis_tab'] = $enabled_tab;
        $styles['__vis_mob'] = $enabled_mob;
        // ETag from style version; Last-Modified from saved modified time
        $etag = '"' . (string)($cfg['version'] ?? '0') . '"';
        $mod_ts = isset($cfg['modified']) ? intval($cfg['modified']) : 0;
        $last_modified_http = $mod_ts ? gmdate('D, d M Y H:i:s', $mod_ts) . ' GMT' : '';

        // Conditional headers
        $if_none_match     = trim((string)$request->get_header('if-none-match'));
        $if_modified_since = trim((string)$request->get_header('if-modified-since'));
        $send_304 = false;
        if ($if_none_match && $if_none_match === $etag) {
            $send_304 = true;
        } elseif ($mod_ts && $if_modified_since) {
            $ims_ts = strtotime($if_modified_since);
            if ($ims_ts && $ims_ts >= $mod_ts) $send_304 = true;
        }

        if ($send_304) {
            $resp = new WP_REST_Response(null, 304);
            $resp->header('ETag', $etag);
            if ($last_modified_http) $resp->header('Last-Modified', $last_modified_http);
            $resp->header('Cache-Control', 'public, max-age=300');
            return $resp;
        }

        // Compute which meta slots are WYSIWYG (HTML) based on mapped field types
        $types_opt = get_option(self::OPTION_KEY, []);
        $field_types = [];
        if (isset($types_opt[$cpt]['fields']) && is_array($types_opt[$cpt]['fields'])) {
            foreach ($types_opt[$cpt]['fields'] as $f) {
                if (!empty($f['key'])) $field_types[$f['key']] = $f['type'] ?? 'text';
            }
        }
        $meta_html = ['meta1' => false, 'meta2' => false, 'meta3' => false];
        $mk = isset($cfg['layout']['meta_keys']) ? (array)$cfg['layout']['meta_keys'] : [];
        foreach ($meta_html as $slot => $_) {
            $k = $mk[$slot] ?? '';
            if ($k && isset($field_types[$k]) && $field_types[$k] === 'wysiwyg') {
                $meta_html[$slot] = true;
            }
        }
        $layout_payload = $cfg['layout'];
        $layout_payload['meta_html'] = $meta_html;

        // Build CSS only if needed to return 200
        $css = $this->build_styles_css($styles);
        // Build rewrite/archives info for Consumer
        $rewrite_slug = null;
        $archive_base = null;
        if ($cpt && isset($types_opt[$cpt])) {
            $def = $types_opt[$cpt];
            // Rewrite + archive base
            $rw = isset($def['rewrite_slug']) && is_string($def['rewrite_slug']) && $def['rewrite_slug'] !== '' ? sanitize_title($def['rewrite_slug']) : $cpt;
            $rewrite_slug = $rw;
            $archive_base = !empty($def['has_archive']) ? $rw : false;
        }
        $payload = [
            'version'     => $cfg['version'],
            'layout'      => $layout_payload,
            'layout_type' => isset($cfg['styles']['layout_type']) && $cfg['styles']['layout_type'] === 'grid' ? 'grid' : 'list',
            'css'         => $css,
            'rewrite_slug' => $rewrite_slug,
            'archive_base' => $archive_base,
            // Provide label for Consumer local CPT registration
            'label'       => ($cpt && isset($types_opt[$cpt]['label'])) ? (string)$types_opt[$cpt]['label'] : null,
        ];
        $response = new WP_REST_Response($payload, 200);
        $response->header('ETag', $etag);
        if ($last_modified_http) $response->header('Last-Modified', $last_modified_http);
        $response->header('Cache-Control', 'public, max-age=300');
        return $response;
    }

    public function rest_global_css(WP_REST_Request $request)
    {
        $feed_settings = get_option(self::OPTION_FEED, []);
        $secret = $feed_settings['secret_key'] ?? '';
        $req_key = sanitize_text_field($request->get_param('key'));
        if ($secret && $req_key !== $secret) {
            return new WP_Error('cphub_forbidden', 'Forbidden: invalid or missing key', ['status' => 403]);
        }
        $opt = get_option(self::OPTION_GLOBAL_CSS, ['css'=>'','version'=>'','modified'=>0]);
        $css = (string)($opt['css'] ?? '');
        $ver = (string)($opt['version'] ?? '');
        $mod = intval($opt['modified'] ?? 0);
        $etag = '"' . ($ver ?: md5($css)) . '"';
        $last_modified_http = $mod ? gmdate('D, d M Y H:i:s', $mod) . ' GMT' : '';

        $if_none_match     = trim((string)$request->get_header('if-none-match'));
        $if_modified_since = trim((string)$request->get_header('if-modified-since'));
        $send_304 = false;
        if ($if_none_match && $if_none_match === $etag) {
            $send_304 = true;
        } elseif ($mod && $if_modified_since) {
            $ims_ts = strtotime($if_modified_since);
            if ($ims_ts && $ims_ts >= $mod) $send_304 = true;
        }
        if ($send_304) {
            $resp = new WP_REST_Response(null, 304);
            $resp->header('ETag', $etag);
            if ($last_modified_http) $resp->header('Last-Modified', $last_modified_http);
            $resp->header('Cache-Control', 'public, max-age=300');
            return $resp;
        }
        $payload = [ 'version' => ($ver ?: md5($css)), 'css' => $css ];
        $resp = new WP_REST_Response($payload, 200);
        $resp->header('ETag', $etag);
        if ($last_modified_http) $resp->header('Last-Modified', $last_modified_http);
        $resp->header('Cache-Control', 'public, max-age=300');
        return $resp;
    }

    /* ---------------------- Publisher Shortcodes ----------- */
    private function enqueue_style_for_cpt($cpt)
    {
        $cfg = $this->get_styles_config($cpt);
        $styles = $cfg['styles'];
        $ver = isset($cfg['version']) ? substr((string)$cfg['version'], 0, 10) : '0';
        $css = $this->build_styles_css($styles);
        $handle = 'cphub-pub-' . sanitize_key($cpt) . '-' . $ver;
        if (!wp_style_is($handle, 'enqueued')) {
            if (!wp_style_is($handle, 'registered')) {
                wp_register_style($handle, false, [], null);
            }
            wp_add_inline_style($handle, (string)$css);
            wp_enqueue_style($handle);
        }
        return $css; // return css string for helpers (overlay detection)
    }

    private function render_card_from_item(array $item, $cpt, array $layout, $assets_css)
    {
        $enabled   = isset($layout['enabled']) && is_array($layout['enabled']) ? $layout['enabled'] : [];
        $order     = isset($layout['order']) && is_array($layout['order']) ? $layout['order'] : ['image','title','excerpt','content','meta1','meta2','meta3','button'];
        $meta_keys = isset($layout['meta_keys']) && is_array($layout['meta_keys']) ? $layout['meta_keys'] : ['meta1'=>'','meta2'=>'','meta3'=>''];
        $meta_wrap = isset($layout['meta_wrap']) && is_array($layout['meta_wrap']) ? $layout['meta_wrap'] : ['meta1'=>'content','meta2'=>'content','meta3'=>'content'];

        // Compute which meta slots are HTML (WYSIWYG) by checking mapped field types
        $types_opt = get_option(self::OPTION_KEY, []);
        $field_types = [];
        if (isset($types_opt[$cpt]['fields']) && is_array($types_opt[$cpt]['fields'])) {
            foreach ($types_opt[$cpt]['fields'] as $f) {
                if (!empty($f['key'])) $field_types[$f['key']] = $f['type'] ?? 'text';
            }
        }
        $meta_html = ['meta1'=>false,'meta2'=>false,'meta3'=>false];
        foreach ($meta_html as $slot => $_) {
            $k = $meta_keys[$slot] ?? '';
            if ($k && isset($field_types[$k]) && $field_types[$k] === 'wysiwyg') $meta_html[$slot] = true;
        }

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
                    $content_html .= '<div class="cphub-excerpt">' . wp_kses_post(wpautop($item['excerpt'])) . '</div>';
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
                            if (!empty($meta_html[$el])) {
                                $html = '<div class="cphub-meta">' . wp_kses_post(wpautop((string)$item['meta'][$key])) . '</div>';
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
            'location' => '',
        ], $atts, 'cphub_list');
        $cpt = sanitize_key($a['cpt']);
        if (!$cpt) return '';
        $n = max(1, intval($a['n']));
        $paged = max(1, intval($a['paged']));
        $loc = sanitize_key($a['location']);

        // Enqueue styles for this CPT
        $assets_css = $this->enqueue_style_for_cpt($cpt);
        $cfg = $this->get_styles_config($cpt);
        $layout = $cfg['layout'];
        $layout_type = isset($cfg['styles']['layout_type']) && $cfg['styles']['layout_type'] === 'grid' ? 'grid' : 'list';

        $args = [
            'post_type' => [$cpt],
            'post_status' => 'publish',
            'posts_per_page' => $n,
            'paged' => $paged,
            'ignore_sticky_posts' => true,
            'orderby' => 'date',
            'order' => 'DESC',
        ];
        if ($loc && taxonomy_exists('location')) {
            $args['tax_query'] = [[
                'taxonomy' => 'location',
                'field' => 'slug',
                'terms' => [$loc, 'all-locations'],
                'operator' => 'IN',
            ]];
        }
        $q = new WP_Query($args);
        if (!$q->have_posts()) return '';

        ob_start();
        echo '<div class="' . esc_attr($layout_type === 'grid' ? 'cphub-grid' : 'cphub-list') . '">';
        while ($q->have_posts()) {
            $q->the_post();
            $item = $this->map_post_to_feed_item(get_post());
            echo $this->render_card_from_item($item, $cpt, $layout, $assets_css);
        }
        wp_reset_postdata();
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
        $id = intval($a['id']);
        if (!$cpt || $id <= 0) return '';
        $p = get_post($id);
        if (!$p || $p->post_type !== $cpt || $p->post_status !== 'publish') return '';
        $assets_css = $this->enqueue_style_for_cpt($cpt);
        $cfg = $this->get_styles_config($cpt);
        $layout = $cfg['layout'];
        $item = $this->map_post_to_feed_item($p);
        return $this->render_card_from_item($item, $cpt, $layout, $assets_css);
    }

    public function sc_location($atts)
    {
        // If a Publisher Location Name is set in Feed Settings, prefer it
        $feed = get_option(self::OPTION_FEED, []);
        $name = isset($feed['publisher_location_name']) ? trim((string)$feed['publisher_location_name']) : '';
        if ($name !== '') return esc_html($name);

        // Backward-compatible behavior: try to derive from taxonomy on current post
        $a = shortcode_atts([
            'slug' => '',
            'fallback' => '',
        ], $atts, 'cphub_location');
        $slug = sanitize_key($a['slug']);
        if ($slug === '') {
            $post_id = get_the_ID();
            if ($post_id && taxonomy_exists('location')) {
                $terms = get_the_terms($post_id, 'location');
                if (is_array($terms) && $terms) {
                    $chosen = null;
                    foreach ($terms as $t) { if ($t->slug !== 'all-locations') { $chosen = $t; break; } }
                    if (!$chosen) $chosen = $terms[0];
                    if ($chosen) $slug = sanitize_key($chosen->slug);
                }
            }
        }
        if ($slug === '') return esc_html((string)$a['fallback']);

        // Fallback mapping/humanize
        $map = [];
        if (method_exists($this, 'get_known_locations')) {
            foreach ($this->get_known_locations() as $loc) $map[sanitize_key($loc['slug'])] = (string)$loc['label'];
        }
        if (isset($map[$slug])) return esc_html($map[$slug]);
        if (taxonomy_exists('location')) { $t = get_term_by('slug', $slug, 'location'); if ($t && !is_wp_error($t)) return esc_html($t->name); }
        return esc_html(ucwords(str_replace(['-', '_'], ' ', $slug)));
    }

    public function sc_meta($atts)
    {
        $a = shortcode_atts([
            'key'  => '',      // meta key (required)
            'id'   => 0,       // optional post ID, defaults to current
            'size' => 'full',  // image size for media=image
            'width'=> '',      // optional CSS width for images (e.g., 300px, 100%)
            'height'=> '',     // optional CSS height for images (e.g., 200px, auto)
            'object_fit' => '',// optional object-fit for images (cover, contain, fill, none, scale-down)
            'link' => '',      // when type=url or media=file: custom link text (defaults to filename or URL)
        ], $atts, 'cphub_meta');

        $key = sanitize_key($a['key']);
        if ($key === '') return '';
        $post_id = intval($a['id']);
        if ($post_id <= 0) $post_id = get_the_ID();
        if ($post_id <= 0) return '';
        $p = get_post($post_id);
        if (!$p) return '';

        $types = get_option(self::OPTION_KEY, []);
        $def = isset($types[$p->post_type]) ? $types[$p->post_type] : null;
        $field_type = 'text';
        $media_kind = 'file';
        if ($def && is_array($def) && !empty($def['fields'])) {
            foreach ($def['fields'] as $f) {
                if (!empty($f['key']) && $f['key'] === $key) {
                    $field_type = $f['type'] ?? 'text';
                    if ($field_type === 'media' && !empty($f['media_type'])) $media_kind = $f['media_type'];
                    break;
                }
            }
        }

        $val = get_post_meta($post_id, $key, true);
        if ($val === '' || $val === null) return '';

        switch ($field_type) {
            case 'wysiwyg':
                // Value is already sanitized on save; re-apply safe HTML and paragraphs
                return wpautop(wp_kses_post((string)$val));
            case 'textarea':
                return '<p>' . nl2br(esc_html((string)$val)) . '</p>';
            case 'url':
                $url = esc_url((string)$val);
                if ($url === '') return '';
                $text = trim((string)$a['link']) !== '' ? esc_html((string)$a['link']) : esc_html($url);
                return '<a href="' . $url . '">' . $text . '</a>';
            case 'number':
            case 'select':
            case 'text':
            default:
                return esc_html((string)$val);
            case 'media':
                $aid = intval($val);
                if ($aid <= 0) return '';
                $mime = get_post_mime_type($aid) ?: '';
                $is_image = strpos((string)$mime, 'image/') === 0 || $media_kind === 'image';
                if ($is_image) {
                    // Render image tag using WP helper
                    // Build optional style attributes
                    $style = [];
                    $dim = function($v){
                        $v = trim((string)$v);
                        if ($v === '') return '';
                        if ($v === 'auto') return 'auto';
                        // allow digits with optional unit
                        if (preg_match('/^\\d+(px|%|rem|em|vw|vh)?$/', $v)) return $v;
                        return '';
                    };
                    $w = $dim($a['width'] ?? '');
                    $h = $dim($a['height'] ?? '');
                    // accept both object_fit and object-fit
                    $fit_in = isset($a['object_fit']) ? $a['object_fit'] : (isset($atts['object-fit']) ? $atts['object-fit'] : '');
                    $fit_in = is_string($fit_in) ? strtolower(trim($fit_in)) : '';
                    $fit_allowed = ['cover','contain','fill','none','scale-down'];
                    $fit = in_array($fit_in, $fit_allowed, true) ? $fit_in : '';
                    if ($w !== '') $style[] = 'width:' . $w;
                    if ($h !== '') $style[] = 'height:' . $h;
                    if ($fit !== '') $style[] = 'object-fit:' . $fit;
                    $attr = ['class' => 'cphub-meta-media'];
                    if ($style) $attr['style'] = implode(';', $style);
                    $img = wp_get_attachment_image($aid, sanitize_key($a['size']) ?: 'full', false, $attr);
                    if (is_string($img) && $img !== '') return $img;
                    $src = wp_get_attachment_url($aid);
                    $alt = get_post_meta($aid, '_wp_attachment_image_alt', true) ?: '';
                    if ($src) {
                        $style_attr = $style ? ' style="' . esc_attr(implode(';', $style)) . '"' : '';
                        return '<img class="cphub-meta-media" src="' . esc_url($src) . '" alt="' . esc_attr($alt) . '"' . $style_attr . ' />';
                    }
                    return '';
                }
                // Non-image: link to file
                $url = wp_get_attachment_url($aid);
                if (!$url) return '';
                $text = trim((string)$a['link']) !== '' ? (string)$a['link'] : basename(parse_url($url, PHP_URL_PATH) ?: $url);
                return '<a class="cphub-meta-file" href="' . esc_url($url) . '" target="_blank" rel="noopener">' . esc_html($text) . '</a>';
        }
    }

    private function get_styles_config($cpt)
    {
        $all = get_option(self::OPTION_STYLES, []);
        $cfg = $all[$cpt] ?? [];
        $defaults = [
            'layout' => [
                'order'     => ['title','image','excerpt','content','meta1','meta2','meta3','button'],
                'enabled'   => ['title'=>true,'image'=>true,'excerpt'=>true,'content'=>false,'meta1'=>false,'meta2'=>false,'meta3'=>false,'button'=>true],
                // responsive visibility (tablet/mobile) — per group key
                'enabled_tab' => ['title'=>true,'image'=>true,'excerpt'=>true,'content'=>false,'meta'=>false,'button'=>true],
                'enabled_mob' => ['title'=>true,'image'=>true,'excerpt'=>true,'content'=>false,'meta'=>false,'button'=>true],
                'meta_keys' => ['meta1'=>'','meta2'=>'','meta3'=>''],
                // placement of meta elements: 'thumb' or 'content'
                'meta_wrap' => ['meta1'=>'content','meta2'=>'content','meta3'=>'content'],
            ],
            // Back-compat global styles + new per-element/card styles
            'styles' => [
                // layout presets
                'layout_type'  => 'list', // 'list' or 'grid'
                'grid_cols'    => 3,
                'grid_gap'     => null, // null = derive from spacing
                'grid_cols_tab' => 2,
                'grid_cols_mob' => 1,
                // legacy globals
                'primary'    => '#0d6efd',
                'text'       => '#111111',
                'font_size'  => 16,
                'spacing'    => 12,
                'radius'     => 8,
                // animations
                'anim_enable'  => false,
                // entrance stagger
                'anim_stagger_enable'     => false,
                'anim_stagger_duration'   => 400,
                'anim_stagger_delay_step' => 80,
                'anim_stagger_offset'     => 8,
                'anim_stagger_ease'       => 'ease-out',
                // card
                'card_bg'        => '#ffffff',
                'card_border'    => '#e5e7eb',
                'card_shadow'    => false,
                'card_padding'   => null,   // null = derive from spacing
                'card_margin_y'  => null,   // null = derive from spacing
                // title
                'title_color'    => null,   // null = derive from primary
                'title_size'     => null,   // null = derive from font_size
                'title_weight'   => 600,
                'title_mt'       => null,
                'title_mb'       => null,
                'title_pad_v'    => null,
                'title_pad_h'    => null,
                'title_lh'       => null,
                'title_align'    => null,
                'title_w'        => null,
                'title_min_w'    => null,
                'title_max_w'    => null,
                // excerpt
                'excerpt_color'  => '#333333',
                'excerpt_size'   => null,
                'excerpt_mt'     => null,
                'excerpt_mb'     => null,
                'excerpt_pad_v'  => null,
                'excerpt_pad_h'  => null,
                'excerpt_lh'     => null,
                'excerpt_align'  => null,
                'excerpt_w'      => null,
                'excerpt_min_w'  => null,
                'excerpt_max_w'  => null,
                // content
                'content_color'  => '#333333',
                'content_size'   => null,
                'content_mt'     => null,
                'content_mb'     => null,
                'content_pad_v'  => null,
                'content_pad_h'  => null,
                'content_lh'     => null,
                'content_align'  => null,
                'content_w'      => null,
                'content_min_w'  => null,
                'content_max_w'  => null,
                // meta
                'meta_color'     => '#555555',
                'meta_size'      => null,
                'meta_mt'        => null,
                'meta_mb'        => null,
                'meta_pad_v'     => null,
                'meta_pad_h'     => null,
                'meta_lh'        => null,
                'meta_align'     => null,
                'meta_w'         => null,
                'meta_min_w'     => null,
                'meta_max_w'     => null,
                'meta_bg'        => '',
                'meta_pos'       => '',
                'meta_top'       => '',
                'meta_right'     => '',
                'meta_bottom'    => '',
                'meta_left'      => '',
                // image
                'image_radius'   => null,   // null = derive from radius
                'image_mt'       => null,
                'image_mb'       => null,
                'image_pad_v'    => null,
                'image_pad_h'    => null,
                'image_align'    => null,
                'image_w'        => null,
                'image_h'        => null,
                'image_min_w'    => null,
                'image_max_w'    => null,
                // image hover scale
                'image_hover_scale_enable' => false,
                'image_hover_scale'        => 1.05,
                'image_hover_duration'     => 300,
                'image_hover_ease'         => 'ease',
                // responsive scaling factors
                'scale_tab'      => 1.0,
                'scale_mob'      => 1.0,
                // button
                'button_bg'      => null,   // null = derive from primary
                'button_text'    => '#ffffff',
                'button_radius'  => null,   // null = derive from radius
                'button_pad_v'   => 8,
                'button_pad_h'   => 12,
                'button_mt'      => null,
                'button_mb'      => null,
                'button_lh'      => null,
                'button_shadow'  => false,
                'button_shadow_css' => '',
                'button_full'    => false,
                'button_align'   => null,
                'button_w'       => null,
                'button_min_w'   => null,
                'button_max_w'   => null,
                'button_stick_bottom' => false,
                // button ripple hover
                'button_ripple_enable'   => false,
                'button_ripple_color'    => null, // null = derive from primary or button_bg
                'button_ripple_opacity'  => 0.7,
                'button_ripple_scale'    => 2.25,
                'button_ripple_duration' => 500,
                'button_ripple_ease'     => 'ease',
                // hover reveal overlay on thumbnail
                'hover_reveal_enable'   => false,
                'hover_reveal_style'    => 'solid',
                'hover_reveal_color'    => '#ffffff',
                'hover_reveal_opacity'  => 0.15,
                'hover_reveal_duration' => 400,
                'hover_reveal_ease'     => 'ease',
                'hover_reveal_angle'    => 20,
                'hover_reveal_thickness'=> '20%',
                'hover_reveal_direction'=> 'tl-br',
            ],
            'version' => '0',
            'modified' => 0,
        ];
        return array_replace_recursive($defaults, $cfg);
    }

    private function set_styles_config($cpt, array $cfg)
    {
        $all = get_option(self::OPTION_STYLES, []);
        $cfg['version'] = md5(wp_json_encode([$cfg['layout'] ?? [], $cfg['styles'] ?? []]));
        $cfg['modified'] = time();
        $all[$cpt] = $cfg;
        update_option(self::OPTION_STYLES, $all);
        return $cfg;
    }

    public function rest_health(WP_REST_Request $request)
    {
        global $wpdb;
        $types = get_option(self::OPTION_KEY, []);
        $styles_all = get_option(self::OPTION_STYLES, []);

        // Feed cache stats
        $cache_count = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name NOT LIKE %s",
            '_transient_cphub_feed_%', '_transient_timeout_cphub_feed_%'
        ));
        $timeout_min = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT MIN(option_value) FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_timeout_cphub_feed_%'
        ));
        $now = time();
        $ttl_remaining = $timeout_min ? max(0, $timeout_min - $now) : null;

        $example_cpt = $types ? key($types) : '';
        $feed_base = home_url('/feed/cphub');
        $rest_base = rest_url('cphub/v1');

        $styles = [];
        foreach ($styles_all as $cpt => $cfg) {
            $styles[$cpt] = [
                'version'  => isset($cfg['version']) ? (string)$cfg['version'] : '0',
                'modified' => isset($cfg['modified']) ? (int)$cfg['modified'] : 0,
            ];
        }

        $payload = [
            'status' => 'ok',
            'time' => gmdate('c', $now),
            'feed' => [
                'base' => (string)$feed_base,
                'example' => $example_cpt ? trailingslashit($feed_base) . $example_cpt : (string)$feed_base,
                'cache_entries' => $cache_count,
                'cache_ttl_seconds' => 300,
                'any_cache_expires_in' => $ttl_remaining,
            ],
            'rest' => [
                'base' => (string)$rest_base,
                'items_example' => (string)rest_url('cphub/v1/items') . ($example_cpt ? '?cpt=' . $example_cpt : ''),
                'assets_example' => (string)rest_url('cphub/v1/assets') . ($example_cpt ? '?cpt=' . $example_cpt : ''),
            ],
            'styles' => $styles,
        ];
        $resp = new WP_REST_Response($payload, 200);
        $resp->header('Cache-Control', 'no-store');
        return $resp;
    }

    private function hex_to_rgba($hex, $alpha = 1.0)
    {
        $hex = trim((string)$hex);
        if ($hex === '') return 'rgba(0,0,0,0)';
        if ($hex[0] === '#') $hex = substr($hex, 1);
        if (strlen($hex) === 3) {
            $r = hexdec(str_repeat($hex[0], 2));
            $g = hexdec(str_repeat($hex[1], 2));
            $b = hexdec(str_repeat($hex[2], 2));
        } else {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        }
        $a = max(0.0, min(1.0, (float)$alpha));
        return 'rgba(' . $r . ',' . $g . ',' . $b . ',' . $a . ')';
    }

    private function build_styles_css(array $styles)
    {
        // Base legacy globals
        $primary = sanitize_hex_color($styles['primary'] ?? '#0d6efd') ?: '#0d6efd';
        $text    = sanitize_hex_color($styles['text'] ?? '#111111') ?: '#111111';
        $fs      = max(10, intval($styles['font_size'] ?? 16));
        $sp      = max(0, intval($styles['spacing'] ?? 12));
        $rad     = max(0, intval($styles['radius'] ?? 8));

        // Card
        $card_bg      = sanitize_hex_color($styles['card_bg'] ?? '#ffffff') ?: '#ffffff';
        $card_border  = sanitize_hex_color($styles['card_border'] ?? '#e5e7eb') ?: '#e5e7eb';
        $card_shadow  = !empty($styles['card_shadow']);
        $card_pad     = isset($styles['card_padding']) ? max(0, intval($styles['card_padding'])) : $sp;
        $card_my      = isset($styles['card_margin_y']) ? max(0, intval($styles['card_margin_y'])) : $sp;

        // Title
        $title_color  = sanitize_hex_color($styles['title_color'] ?? '') ?: $primary;
        $title_size   = isset($styles['title_size']) ? max(10, intval($styles['title_size'])) : $fs + 2;
        $title_weight = isset($styles['title_weight']) ? max(100, min(900, intval($styles['title_weight']))) : 600;

        // Excerpt
        $excerpt_color = sanitize_hex_color($styles['excerpt_color'] ?? '#333333') ?: '#333333';
        $excerpt_size  = isset($styles['excerpt_size']) ? max(10, intval($styles['excerpt_size'])) : $fs;

        // Content
        $content_color = sanitize_hex_color($styles['content_color'] ?? '#333333') ?: '#333333';
        $content_size  = isset($styles['content_size']) ? max(10, intval($styles['content_size'])) : $fs;

        // Meta
        $meta_color = sanitize_hex_color($styles['meta_color'] ?? '#555555') ?: '#555555';
        $meta_size  = isset($styles['meta_size']) ? max(10, intval($styles['meta_size'])) : max(10, $fs - 2);

        // Image
        $image_radius = isset($styles['image_radius']) ? max(0, intval($styles['image_radius'])) : $rad;

        // Button
        $btn_bg     = sanitize_hex_color($styles['button_bg'] ?? '') ?: $primary;
        $btn_text   = sanitize_hex_color($styles['button_text'] ?? '#ffffff') ?: '#ffffff';
        $btn_rad    = isset($styles['button_radius']) ? max(0, intval($styles['button_radius'])) : $rad;
        $btn_pv     = max(0, intval($styles['button_pad_v'] ?? 8));
        $btn_ph     = max(0, intval($styles['button_pad_h'] ?? 12));

        $shadow_css = $card_shadow ? 'box-shadow:0 1px 2px rgba(0,0,0,0.06), 0 1px 3px rgba(0,0,0,0.10);' : '';

        $css = '';
        // Collection presets (list/grid)
        $layout_type = ($styles['layout_type'] ?? 'list') === 'grid' ? 'grid' : 'list';
        $grid_cols   = max(1, intval($styles['grid_cols'] ?? 3));
        $grid_gap    = isset($styles['grid_gap']) ? max(0, intval($styles['grid_gap'])) : $sp;
        if ($layout_type === 'grid') {
            $css .= ".cphub-grid{display:grid;grid-template-columns:repeat({$grid_cols},minmax(0,1fr));gap:{$grid_gap}px}";
        } else {
            $css .= ".cphub-list{display:block}";
        }
        $css .= ".cphub-card{font-size:{$fs}px;color:{$text};margin:{$card_my}px 0;padding:{$card_pad}px;background:{$card_bg};border:1px solid {$card_border};border-radius:{$rad}px;{$shadow_css}}";
        // Animations — basic fade OR stagger if enabled
        $stagger_enable = !empty($styles['anim_stagger_enable']);
        if (!empty($styles['anim_enable']) && !$stagger_enable) {
            $css .= '@keyframes cphubFadeIn{0%{opacity:0;transform:translateY(8px)}100%{opacity:1;transform:none}}';
            $css .= '.cphub-card{animation:cphubFadeIn .4s ease-out both}';
        }
        // Button hover transitions stay when anims enabled
        if (!empty($styles['anim_enable'])) {
            $css .= '.cphub-card .cphub-btn{transition:transform .2s ease, box-shadow .2s ease, background-color .2s ease}';
            $css .= '.cphub-card .cphub-btn:hover{transform:translateY(-1px)}';
        }
        // Staggered entrance animation
        if ($stagger_enable) {
            $dur  = max(50, intval($styles['anim_stagger_duration'] ?? 400));
            $step = max(0, intval($styles['anim_stagger_delay_step'] ?? 80));
            $off  = max(0, intval($styles['anim_stagger_offset'] ?? 8));
            $ease = trim((string)($styles['anim_stagger_ease'] ?? 'ease-out'));
            if ($ease === '') $ease = 'ease-out';
            $css .= '@keyframes cphubStaggerIn{0%{opacity:0;transform:translateY(' . $off . 'px)}100%{opacity:1;transform:none}}';
            $css .= '.cphub-card{opacity:0;transform:translateY(' . $off . 'px);animation:cphubStaggerIn ' . $dur . 'ms ' . $ease . ' forwards}';
            // nth-child delays for first N items (list and grid)
            $max = 12;
            for ($i = 1; $i <= $max; $i++) {
                $delay = ($i - 1) * $step;
                $css .= '.cphub-list .cphub-card:nth-child(' . $i . '){animation-delay:' . $delay . 'ms}';
                $css .= '.cphub-grid > .cphub-card:nth-child(' . $i . '){animation-delay:' . $delay . 'ms}';
            }
        }
        // Reduced motion guard
        if (!empty($styles['anim_enable']) || $stagger_enable) {
            $css .= '@media (prefers-reduced-motion: reduce){.cphub-card{animation:none}.cphub-card .cphub-btn{transition:none}}';
        }

        // Thumb/content wrappers and optional hover reveal overlay
        $css .= ".cphub-thumb-wrap,.cphub-content-wrap{position:relative}";
        if (!empty($styles['hover_reveal_enable'])) {
            $angle = intval($styles['hover_reveal_angle'] ?? 20);
            $thick = trim((string)($styles['hover_reveal_thickness'] ?? '20%'));
            if ($thick === '') $thick = '20%';
            $durHR = max(0, intval($styles['hover_reveal_duration'] ?? 400));
            $easeHR = trim((string)($styles['hover_reveal_ease'] ?? 'ease'));
            if ($easeHR === '') $easeHR = 'ease';
            $color = sanitize_hex_color($styles['hover_reveal_color'] ?? '#ffffff') ?: '#ffffff';
            $op    = max(0.0, min(1.0, (float)($styles['hover_reveal_opacity'] ?? 0.15)));
            $rgba  = $this->hex_to_rgba($color, $op);
            $dir   = in_array(($styles['hover_reveal_direction'] ?? 'tl-br'), ['tl-br','br-tl'], true) ? $styles['hover_reveal_direction'] : 'tl-br';
            $start = $dir === 'tl-br' ? '-150%' : '150%';
            $end   = $dir === 'tl-br' ? '150%' : '-150%';
            $style = in_array(($styles['hover_reveal_style'] ?? 'solid'), ['solid','sheen'], true) ? $styles['hover_reveal_style'] : 'solid';
            $css .= '.cphub-thumb-wrap{overflow:hidden}';
            if ($style === 'sheen') {
                // gradient sheen: width uses thickness, skew across
                $grad = 'linear-gradient(to right, rgba(255,255,255,0) 0%,' . $rgba . ' 100%)';
                $css .= '.cphub-thumb-wrap::before{content:"";position:absolute;top:0;left:0;width:' . $thick . ';height:100%;pointer-events:none;background:' . $grad . ';transform:translateX(' . $start . ') skewX(' . $angle . 'deg);transition:transform ' . $durHR . 'ms ' . $easeHR . ';z-index: 1;}';
                $css .= '.cphub-card:hover .cphub-thumb-wrap::before{transform:translateX(' . $end . ') skewX(' . $angle . 'deg);}';
            } else {
                // solid overlay: inset thickness, diagonal sweep via rotate
                $css .= '.cphub-thumb-wrap::before{content:"";position:absolute;inset:calc(-1 * ' . $thick . ');pointer-events:none;background:' . $rgba . ';transform:translateX(' . $start . ') rotate(' . $angle . 'deg);transition:transform ' . $durHR . 'ms ' . $easeHR . ';}';
                $css .= '.cphub-card:hover .cphub-thumb-wrap::before{transform:translateX(' . $end . ') rotate(' . $angle . 'deg);}';
            }
        }
        // Utility color classes for WYSIWYG meta content
        $css .= '.cphub-meta .cphub-color-primary{color:' . $primary . '}';
        $css .= '.cphub-meta .cphub-color-text{color:' . $text . '}';
        // Title spacing
        $title_mt = isset($styles['title_mt']) ? max(0, intval($styles['title_mt'])) : null;
        $title_mb = isset($styles['title_mb']) ? max(0, intval($styles['title_mb'])) : max(4, intval($sp/2));
        $title_pad_v = isset($styles['title_pad_v']) ? max(0, intval($styles['title_pad_v'])) : null;
        $title_pad_h = isset($styles['title_pad_h']) ? max(0, intval($styles['title_pad_h'])) : null;
        $title_box = '';
        if ($title_mt !== null) $title_box .= 'margin-top:'.$title_mt.'px;';
        if ($title_mb !== null) $title_box .= 'margin-bottom:'.$title_mb.'px;';
        if ($title_pad_v !== null || $title_pad_h !== null) {
            $pv = $title_pad_v !== null ? $title_pad_v : 0;
            $ph = $title_pad_h !== null ? $title_pad_h : 0;
            $title_box .= 'padding:'.$pv.'px '.$ph.'px;';
        }
        $title_align = isset($styles['title_align']) && in_array($styles['title_align'], ['left','center','right'], true) ? $styles['title_align'] : null;
        if ($title_align) $title_box .= 'text-align:'.$title_align.';';
        $tw = trim((string)($styles['title_w'] ?? ''));
        $tmin = trim((string)($styles['title_min_w'] ?? ''));
        $tmax = trim((string)($styles['title_max_w'] ?? ''));
        if ($tw !== '') $title_box .= 'width:'.$tw.';';
        if ($tmin !== '') $title_box .= 'min-width:'.$tmin.';';
        if ($tmax !== '') $title_box .= 'max-width:'.$tmax.';';
        $t_lh = isset($styles['title_lh']) ? max(0.8, floatval($styles['title_lh'])) : null;
        if ($t_lh !== null) $title_box .= 'line-height:'.$t_lh.';';
        $css .= ".cphub-card .cphub-title{ {$title_box} font-size:{$title_size}px; font-weight:{$title_weight};box-sizing:border-box;max-width:100%;}";
        $css .= ".cphub-card .cphub-title a{color:{$title_color};text-decoration:none}";
        // Excerpt spacing
        $excerpt_mt = isset($styles['excerpt_mt']) ? max(0, intval($styles['excerpt_mt'])) : null;
        $excerpt_mb = isset($styles['excerpt_mb']) ? max(0, intval($styles['excerpt_mb'])) : null;
        $excerpt_pv = isset($styles['excerpt_pad_v']) ? max(0, intval($styles['excerpt_pad_v'])) : null;
        $excerpt_ph = isset($styles['excerpt_pad_h']) ? max(0, intval($styles['excerpt_pad_h'])) : null;
        $excerpt_box = '';
        if ($excerpt_mt !== null) $excerpt_box .= 'margin-top:'.$excerpt_mt.'px;';
        if ($excerpt_mb !== null) $excerpt_box .= 'margin-bottom:'.$excerpt_mb.'px;';
        if ($excerpt_pv !== null || $excerpt_ph !== null) $excerpt_box .= 'padding:'.($excerpt_pv?:0).'px '.($excerpt_ph?:0).'px;';
        $ex_align = isset($styles['excerpt_align']) && in_array($styles['excerpt_align'], ['left','center','right'], true) ? $styles['excerpt_align'] : null;
        if ($ex_align) $excerpt_box .= 'text-align:'.$ex_align.';';
        $ex_w = trim((string)($styles['excerpt_w'] ?? ''));
        $ex_min = trim((string)($styles['excerpt_min_w'] ?? ''));
        $ex_max = trim((string)($styles['excerpt_max_w'] ?? ''));
        if ($ex_w !== '') $excerpt_box .= 'width:'.$ex_w.';';
        if ($ex_min !== '') $excerpt_box .= 'min-width:'.$ex_min.';';
        if ($ex_max !== '') $excerpt_box .= 'max-width:'.$ex_max.';';
        $ex_lh = isset($styles['excerpt_lh']) ? max(0.8, floatval($styles['excerpt_lh'])) : null;
        if ($ex_lh !== null) $excerpt_box .= 'line-height:'.$ex_lh.';';
        $css .= ".cphub-card .cphub-excerpt{ {$excerpt_box} color:{$excerpt_color}; font-size:{$excerpt_size}px;box-sizing:border-box;max-width:100%;}";

        // Content spacing
        $content_mt = isset($styles['content_mt']) ? max(0, intval($styles['content_mt'])) : null;
        $content_mb = isset($styles['content_mb']) ? max(0, intval($styles['content_mb'])) : null;
        $content_pv = isset($styles['content_pad_v']) ? max(0, intval($styles['content_pad_v'])) : null;
        $content_ph = isset($styles['content_pad_h']) ? max(0, intval($styles['content_pad_h'])) : null;
        $content_box = '';
        if ($content_mt !== null) $content_box .= 'margin-top:'.$content_mt.'px;';
        if ($content_mb !== null) $content_box .= 'margin-bottom:'.$content_mb.'px;';
        if ($content_pv !== null || $content_ph !== null) $content_box .= 'padding:'.($content_pv?:0).'px '.($content_ph?:0).'px;';
        $co_align = isset($styles['content_align']) && in_array($styles['content_align'], ['left','center','right'], true) ? $styles['content_align'] : null;
        if ($co_align) $content_box .= 'text-align:'.$co_align.';';
        $co_w = trim((string)($styles['content_w'] ?? ''));
        $co_min = trim((string)($styles['content_min_w'] ?? ''));
        $co_max = trim((string)($styles['content_max_w'] ?? ''));
        if ($co_w !== '') $content_box .= 'width:'.$co_w.';';
        if ($co_min !== '') $content_box .= 'min-width:'.$co_min.';';
        if ($co_max !== '') $content_box .= 'max-width:'.$co_max.';';
        $co_lh = isset($styles['content_lh']) ? max(0.8, floatval($styles['content_lh'])) : null;
        if ($co_lh !== null) $content_box .= 'line-height:'.$co_lh.';';
        $css .= ".cphub-card .cphub-content{ {$content_box} color:{$content_color}; font-size:{$content_size}px;box-sizing:border-box;max-width:100%;}";

        // Meta spacing
        $meta_mt = isset($styles['meta_mt']) ? max(0, intval($styles['meta_mt'])) : null;
        $meta_mb = isset($styles['meta_mb']) ? max(0, intval($styles['meta_mb'])) : null;
        $meta_pv = isset($styles['meta_pad_v']) ? max(0, intval($styles['meta_pad_v'])) : null;
        $meta_ph = isset($styles['meta_pad_h']) ? max(0, intval($styles['meta_pad_h'])) : null;
        $meta_box = '';
        if ($meta_mt !== null) $meta_box .= 'margin-top:'.$meta_mt.'px;';
        if ($meta_mb !== null) $meta_box .= 'margin-bottom:'.$meta_mb.'px;';
        if ($meta_pv !== null || $meta_ph !== null) $meta_box .= 'padding:'.($meta_pv?:0).'px '.($meta_ph?:0).'px;';
        $me_align = isset($styles['meta_align']) && in_array($styles['meta_align'], ['left','center','right'], true) ? $styles['meta_align'] : null;
        if ($me_align) $meta_box .= 'text-align:'.$me_align.';';
        $me_w = trim((string)($styles['meta_w'] ?? ''));
        $me_min = trim((string)($styles['meta_min_w'] ?? ''));
        $me_max = trim((string)($styles['meta_max_w'] ?? ''));
        if ($me_w !== '') $meta_box .= 'width:'.$me_w.';';
        if ($me_min !== '') $meta_box .= 'min-width:'.$me_min.';';
        if ($me_max !== '') $meta_box .= 'max-width:'.$me_max.';';
        $me_lh = isset($styles['meta_lh']) ? max(0.8, floatval($styles['meta_lh'])) : null;
        if ($me_lh !== null) $meta_box .= 'line-height:'.$me_lh.';';
        $meta_bg = trim((string)($styles['meta_bg'] ?? ''));
        $meta_pos = trim((string)($styles['meta_pos'] ?? ''));
        $meta_top = trim((string)($styles['meta_top'] ?? ''));
        $meta_right = trim((string)($styles['meta_right'] ?? ''));
        $meta_bottom = trim((string)($styles['meta_bottom'] ?? ''));
        $meta_left = trim((string)($styles['meta_left'] ?? ''));
        if ($meta_bg !== '') $meta_box .= 'background:'.$meta_bg.';';
        if ($meta_pos !== '') $meta_box .= 'position:'.$meta_pos.';';
        if ($meta_top !== '') $meta_box .= 'top:'.$meta_top.';';
        if ($meta_right !== '') $meta_box .= 'right:'.$meta_right.';';
        if ($meta_bottom !== '') $meta_box .= 'bottom:'.$meta_bottom.';';
        if ($meta_left !== '') $meta_box .= 'left:'.$meta_left.';';
        $css .= ".cphub-card .cphub-meta{ {$meta_box} color:{$meta_color};font-size:{$meta_size}px;box-sizing:border-box;}";

        // Image spacing
        $image_mt = isset($styles['image_mt']) ? max(0, intval($styles['image_mt'])) : null;
        $image_mb = isset($styles['image_mb']) ? max(0, intval($styles['image_mb'])) : null;
        $image_pv = isset($styles['image_pad_v']) ? max(0, intval($styles['image_pad_v'])) : null;
        $image_ph = isset($styles['image_pad_h']) ? max(0, intval($styles['image_pad_h'])) : null;
        $image_box = '';
        if ($image_mt !== null) $image_box .= 'margin-top:'.$image_mt.'px;';
        if ($image_mb !== null) $image_box .= 'margin-bottom:'.$image_mb.'px;';
        if ($image_pv !== null || $image_ph !== null) $image_box .= 'padding:'.($image_pv?:0).'px '.($image_ph?:0).'px;';
        $im_align = isset($styles['image_align']) && in_array($styles['image_align'], ['left','center','right'], true) ? $styles['image_align'] : null;
        if ($im_align === 'center') $image_box .= 'margin-left:auto;margin-right:auto;display:block;';
        if ($im_align === 'right') $image_box .= 'margin-left:auto;display:block;';
        $im_w = trim((string)($styles['image_w'] ?? ''));
        $im_h = trim((string)($styles['image_h'] ?? ''));
        $im_min = trim((string)($styles['image_min_w'] ?? ''));
        $im_max = trim((string)($styles['image_max_w'] ?? ''));
        if ($im_w !== '') $image_box .= 'width:'.$im_w.';';
        if ($im_min !== '') $image_box .= 'min-width:'.$im_min.';';
        if ($im_max !== '') $image_box .= 'max-width:'.$im_max.';';
        $dim_defaults = '';
        if ($im_w === '' && $im_min === '' && $im_max === '') {
            $dim_defaults .= 'width:100%;';
        }
        // Always keep max-width to guard overflow
        $dim_defaults .= 'max-width:100%;';
        $dim_defaults .= ($im_h !== '' ? 'height:'.$im_h.';' : 'height:auto;');
        $fit = trim((string)($styles['image_object_fit'] ?? ''));
        if ($fit !== '') $dim_defaults .= 'object-fit:'.$fit.';';
        $css .= ".cphub-card .cphub-img{ {$image_box} display:block;{$dim_defaults}border-radius:{$image_radius}px;box-sizing:border-box;}";
        if (!empty($styles['image_hover_scale_enable'])) {
            $scale = max(1.0, (float)($styles['image_hover_scale'] ?? 1.05));
            $durI  = max(0, intval($styles['image_hover_duration'] ?? 300));
            $easeI = trim((string)($styles['image_hover_ease'] ?? 'ease'));
            if ($easeI === '') $easeI = 'ease';
            // ensure the image clips within thumb wrap when scaling
            $css .= '.cphub-thumb-wrap{overflow:hidden}';
            $css .= '.cphub-card .cphub-img{transition:transform ' . $durI . 'ms ' . $easeI . ';will-change:transform}';
            $css .= '.cphub-card:hover .cphub-img{transform:scale(' . $scale . ')}';
        }

        // Button spacing
        $button_mt = isset($styles['button_mt']) ? max(0, intval($styles['button_mt'])) : null;
        $button_mb = isset($styles['button_mb']) ? max(0, intval($styles['button_mb'])) : null;
        $btn_box = '';
        if ($button_mt !== null) $btn_box .= 'margin-top:'.$button_mt.'px;';
        if ($button_mb !== null) $btn_box .= 'margin-bottom:'.$button_mb.'px;';
        $btn_align = isset($styles['button_align']) && in_array($styles['button_align'], ['left','center','right'], true) ? $styles['button_align'] : null;
        if ($btn_align === 'center') $btn_box .= 'display:block;margin-left:auto;margin-right:auto;text-align:center;';
        if ($btn_align === 'right') $btn_box .= 'display:block;margin-left:auto;text-align:right;';
        $bt_w = trim((string)($styles['button_w'] ?? ''));
        $bt_min = trim((string)($styles['button_min_w'] ?? ''));
        $bt_max = trim((string)($styles['button_max_w'] ?? ''));
        if ($bt_w !== '') $btn_box .= 'width:'.$bt_w.';';
        if ($bt_min !== '') $btn_box .= 'min-width:'.$bt_min.';';
        if ($bt_max !== '') $btn_box .= 'max-width:'.$bt_max.';';
        $btn_lh = isset($styles['button_lh']) ? max(0.8, floatval($styles['button_lh'])) : null;
        $shadow_custom = trim((string)($styles['button_shadow_css'] ?? ''));
        if ($shadow_custom !== '') {
            $btn_shadow_css = 'box-shadow:'.$shadow_custom.';';
        } else {
            $btn_shadow_css = !empty($styles['button_shadow']) ? 'box-shadow:0 1px 2px rgba(0,0,0,0.10), 0 2px 4px rgba(0,0,0,0.12);' : '';
        }
        $css .= ".cphub-card .cphub-btn{ {$btn_box} background:{$btn_bg};color:{$btn_text};padding:{$btn_pv}px {$btn_ph}px;border-radius:{$btn_rad}px;box-sizing:border-box;".($btn_lh!==null?'line-height:'.$btn_lh.';':'').$btn_shadow_css."text-decoration:none}";
        if (!empty($styles['button_stick_bottom'])) {
            $css .= '.cphub-card{display:flex;flex-direction:column;}';
            $css .= '.cphub-content-wrap{display:flex;flex-direction:column;flex:1;}';
            $css .= '.cphub-card .cphub-btn{margin-top:auto;}';
        }

        // Button ripple hover via layered background (center by default, can follow cursor in preview)
        if (!empty($styles['button_ripple_enable'])) {
            $rip_color = isset($styles['button_ripple_color']) && $styles['button_ripple_color'] ? $styles['button_ripple_color'] : ($styles['button_bg'] ?: $primary);
            $rip_rgba  = $this->hex_to_rgba($rip_color, max(0.0, min(1.0, (float)($styles['button_ripple_opacity'] ?? 0.7))));
            $rip_scale = max(1.0, (float)($styles['button_ripple_scale'] ?? 2.25));
            $rip_dur   = max(0, intval($styles['button_ripple_duration'] ?? 500));
            $rip_ease  = trim((string)($styles['button_ripple_ease'] ?? 'ease')) ?: 'ease';
            // Background ripple method (fallback) only when element does not have overlay child
            $css .= '.cphub-card .cphub-btn:not(.has-hover){position:relative;overflow:hidden;background-image:radial-gradient(circle at var(--cphub-btn-x,50%) var(--cphub-btn-y,50%), ' . $rip_rgba . ' 0%,' . $rip_rgba . ' 60%, transparent 61%), linear-gradient(' . $btn_bg . ',' . $btn_bg . ');}';
            $css .= '.cphub-card .cphub-btn:not(.has-hover){background-repeat:no-repeat;background-size:0 0, auto;transition:background-size ' . $rip_dur . 'ms ' . $rip_ease . ';}';
            $css .= '.cphub-card .cphub-btn:not(.has-hover):hover{background-size:calc(100% * ' . $rip_scale . ') calc(100% * ' . $rip_scale . '), auto;}';
            // Overlay ripple method (matches reference markup) when .cphub-btn-hover child exists
            $css .= '.cphub-card .cphub-btn.has-hover{position:relative;overflow:hidden}';
            $css .= '.cphub-card .cphub-btn .cphub-btn-inner{position:relative;display:inline-block;z-index:1}';
            $css .= '.cphub-card .cphub-btn .cphub-btn-base{display:flex;align-items:center;position:relative;color:' . $btn_text . ';transition:all ' . max(200, $rip_dur) . 'ms ' . $rip_ease . ';z-index:1}';
            $css .= '.cphub-card .cphub-btn .cphub-btn-hover{position:absolute;display:inline-block;top:0;left:0;width:0;height:0;transform:translate(-50%,-50%);border-radius:50%;z-index:0;opacity:' . max(0.0, min(1.0, (float)($styles['button_ripple_opacity'] ?? 0.7))) . ';background-color:' . $rip_color . ';transition:all ' . $rip_dur . 'ms ' . $rip_ease . ';}';
            $css .= '.cphub-card .cphub-btn:hover .cphub-btn-hover{width:calc(100% * ' . $rip_scale . ');padding-top:calc(100% * ' . $rip_scale . ');opacity:1}';
        }

        // Responsive toggles and scaling
        $tab_break = 1024; // px
        $mob_break = 640;  // px
        // Default visibility fallbacks (if not present, keep visible)
        $vis_tab = isset($styles['__vis_tab']) && is_array($styles['__vis_tab']) ? $styles['__vis_tab'] : [];
        $vis_mob = isset($styles['__vis_mob']) && is_array($styles['__vis_mob']) ? $styles['__vis_mob'] : [];
        $css_tab = '';
        if (isset($vis_tab['title']) && !$vis_tab['title']) $css_tab .= '.cphub-card .cphub-title{display:none !important;}';
        if (isset($vis_tab['image']) && !$vis_tab['image']) $css_tab .= '.cphub-card .cphub-img{display:none !important;}';
        if (isset($vis_tab['excerpt']) && !$vis_tab['excerpt']) $css_tab .= '.cphub-card .cphub-excerpt{display:none !important;}';
        if (isset($vis_tab['content']) && !$vis_tab['content']) $css_tab .= '.cphub-card .cphub-content{display:none !important;}';
        if (isset($vis_tab['meta']) && !$vis_tab['meta']) $css_tab .= '.cphub-card .cphub-meta{display:none !important;}';
        if (isset($vis_tab['button']) && !$vis_tab['button']) $css_tab .= '.cphub-card .cphub-btn{display:none !important;}';
        // Scaling tablet
        $s_tab = isset($styles['scale_tab']) ? max(0.1, floatval($styles['scale_tab'])) : 1.0;
        if ($s_tab !== 1.0) {
            $fs_t = max(10, intval(round($fs * $s_tab)));
            $sp_t = max(0, intval(round($sp * $s_tab)));
            $rad_t = max(0, intval(round($rad * $s_tab)));
            $title_size_t = max(10, intval(round($title_size * $s_tab)));
            $excerpt_size_t = max(10, intval(round($excerpt_size * $s_tab)));
            $content_size_t = max(10, intval(round($content_size * $s_tab)));
            $meta_size_t = max(10, intval(round($meta_size * $s_tab)));
            $btn_pv_t = max(0, intval(round($btn_pv * $s_tab)));
            $btn_ph_t = max(0, intval(round($btn_ph * $s_tab)));
            $card_pad_t = max(0, intval(round($card_pad * $s_tab)));
            $card_my_t  = max(0, intval(round($card_my * $s_tab)));
            $image_radius_t = max(0, intval(round($image_radius * $s_tab)));
            $css_tab .= '.cphub-card{font-size:'.$fs_t.'px;margin:'.$card_my_t.'px 0;padding:'.$card_pad_t.'px;border-radius:'.$rad_t.'px;}';
            $css_tab .= '.cphub-card .cphub-title{font-size:'.$title_size_t.'px;}';
            $css_tab .= '.cphub-card .cphub-excerpt{font-size:'.$excerpt_size_t.'px;}';
            $css_tab .= '.cphub-card .cphub-content{font-size:'.$content_size_t.'px;}';
            $css_tab .= '.cphub-card .cphub-meta{font-size:'.$meta_size_t.'px;}';
            $css_tab .= '.cphub-card .cphub-img{border-radius:'.$image_radius_t.'px;}';
            $css_tab .= '.cphub-card .cphub-btn{padding:'.$btn_pv_t.'px '.$btn_ph_t.'px;border-radius:'.max(0, intval(round($btn_rad * $s_tab))).'px;}';
            // Scale margins/padding when explicitly set
            if ($title_mt !== null || $title_mb !== null) {
                if ($title_mt !== null) $css_tab .= '.cphub-card .cphub-title{margin-top:'.max(0, intval(round($title_mt * $s_tab))).'px;}';
                if ($title_mb !== null) $css_tab .= '.cphub-card .cphub-title{margin-bottom:'.max(0, intval(round($title_mb * $s_tab))).'px;}';
            }
            if ($title_pad_v !== null || $title_pad_h !== null) $css_tab .= '.cphub-card .cphub-title{padding:'.max(0, intval(round(($title_pad_v?:0) * $s_tab))).'px '.max(0, intval(round(($title_pad_h?:0) * $s_tab))).'px;}';
            if ($excerpt_mt !== null) $css_tab .= '.cphub-card .cphub-excerpt{margin-top:'.max(0, intval(round($excerpt_mt * $s_tab))).'px;}';
            if ($excerpt_mb !== null) $css_tab .= '.cphub-card .cphub-excerpt{margin-bottom:'.max(0, intval(round($excerpt_mb * $s_tab))).'px;}';
            if ($excerpt_pv !== null || $excerpt_ph !== null) $css_tab .= '.cphub-card .cphub-excerpt{padding:'.max(0, intval(round(($excerpt_pv?:0) * $s_tab))).'px '.max(0, intval(round(($excerpt_ph?:0) * $s_tab))).'px;}';
            if ($content_mt !== null) $css_tab .= '.cphub-card .cphub-content{margin-top:'.max(0, intval(round($content_mt * $s_tab))).'px;}';
            if ($content_mb !== null) $css_tab .= '.cphub-card .cphub-content{margin-bottom:'.max(0, intval(round($content_mb * $s_tab))).'px;}';
            if ($content_pv !== null || $content_ph !== null) $css_tab .= '.cphub-card .cphub-content{padding:'.max(0, intval(round(($content_pv?:0) * $s_tab))).'px '.max(0, intval(round(($content_ph?:0) * $s_tab))).'px;}';
            if ($meta_mt !== null) $css_tab .= '.cphub-card .cphub-meta{margin-top:'.max(0, intval(round($meta_mt * $s_tab))).'px;}';
            if ($meta_mb !== null) $css_tab .= '.cphub-card .cphub-meta{margin-bottom:'.max(0, intval(round($meta_mb * $s_tab))).'px;}';
            if ($meta_pv !== null || $meta_ph !== null) $css_tab .= '.cphub-card .cphub-meta{padding:'.max(0, intval(round(($meta_pv?:0) * $s_tab))).'px '.max(0, intval(round(($meta_ph?:0) * $s_tab))).'px;}';
            if ($image_mt !== null) $css_tab .= '.cphub-card .cphub-img{margin-top:'.max(0, intval(round($image_mt * $s_tab))).'px;}';
            if ($image_mb !== null) $css_tab .= '.cphub-card .cphub-img{margin-bottom:'.max(0, intval(round($image_mb * $s_tab))).'px;}';
            if ($image_pv !== null || $image_ph !== null) $css_tab .= '.cphub-card .cphub-img{padding:'.max(0, intval(round(($image_pv?:0) * $s_tab))).'px '.max(0, intval(round(($image_ph?:0) * $s_tab))).'px;}';
            if ($button_mt !== null) $css_tab .= '.cphub-card .cphub-btn{margin-top:'.max(0, intval(round($button_mt * $s_tab))).'px;}';
            if ($button_mb !== null) $css_tab .= '.cphub-card .cphub-btn{margin-bottom:'.max(0, intval(round($button_mb * $s_tab))).'px;}';
        }
        // Grid columns at tablet breakpoint
        if ($layout_type === 'grid') {
            $gc_t = max(1, intval($styles['grid_cols_tab'] ?? 2));
            $css_tab .= '.cphub-grid{grid-template-columns:repeat('.$gc_t.',minmax(0,1fr));}';
        }
        if ($css_tab !== '') $css .= '@media (max-width: '.$tab_break.'px){'.$css_tab.'}';

        $css_mob = '';
        if (isset($vis_mob['title']) && !$vis_mob['title']) $css_mob .= '.cphub-card .cphub-title{display:none !important;}';
        if (isset($vis_mob['image']) && !$vis_mob['image']) $css_mob .= '.cphub-card .cphub-img{display:none !important;}';
        if (isset($vis_mob['excerpt']) && !$vis_mob['excerpt']) $css_mob .= '.cphub-card .cphub-excerpt{display:none !important;}';
        if (isset($vis_mob['content']) && !$vis_mob['content']) $css_mob .= '.cphub-card .cphub-content{display:none !important;}';
        if (isset($vis_mob['meta']) && !$vis_mob['meta']) $css_mob .= '.cphub-card .cphub-meta{display:none !important;}';
        if (isset($vis_mob['button']) && !$vis_mob['button']) $css_mob .= '.cphub-card .cphub-btn{display:none !important;}';
        // Scaling mobile
        $s_mob = isset($styles['scale_mob']) ? max(0.1, floatval($styles['scale_mob'])) : 1.0;
        if ($s_mob !== 1.0) {
            $fs_m = max(10, intval(round($fs * $s_mob)));
            $sp_m = max(0, intval(round($sp * $s_mob)));
            $rad_m = max(0, intval(round($rad * $s_mob)));
            $title_size_m = max(10, intval(round($title_size * $s_mob)));
            $excerpt_size_m = max(10, intval(round($excerpt_size * $s_mob)));
            $content_size_m = max(10, intval(round($content_size * $s_mob)));
            $meta_size_m = max(10, intval(round($meta_size * $s_mob)));
            $btn_pv_m = max(0, intval(round($btn_pv * $s_mob)));
            $btn_ph_m = max(0, intval(round($btn_ph * $s_mob)));
            $card_pad_m = max(0, intval(round($card_pad * $s_mob)));
            $card_my_m  = max(0, intval(round($card_my * $s_mob)));
            $image_radius_m = max(0, intval(round($image_radius * $s_mob)));
            $css_mob .= '.cphub-card{font-size:'.$fs_m.'px;margin:'.$card_my_m.'px 0;padding:'.$card_pad_m.'px;border-radius:'.$rad_m.'px;}';
            $css_mob .= '.cphub-card .cphub-title{font-size:'.$title_size_m.'px;}';
            $css_mob .= '.cphub-card .cphub-excerpt{font-size:'.$excerpt_size_m.'px;}';
            $css_mob .= '.cphub-card .cphub-content{font-size:'.$content_size_m.'px;}';
            $css_mob .= '.cphub-card .cphub-meta{font-size:'.$meta_size_m.'px;}';
            $css_mob .= '.cphub-card .cphub-img{border-radius:'.$image_radius_m.'px;}';
            $css_mob .= '.cphub-card .cphub-btn{padding:'.$btn_pv_m.'px '.$btn_ph_m.'px;border-radius:'.max(0, intval(round($btn_rad * $s_mob))).'px;}';
            if ($title_mt !== null) $css_mob .= '.cphub-card .cphub-title{margin-top:'.max(0, intval(round($title_mt * $s_mob))).'px;}';
            if ($title_mb !== null) $css_mob .= '.cphub-card .cphub-title{margin-bottom:'.max(0, intval(round($title_mb * $s_mob))).'px;}';
            if ($title_pad_v !== null || $title_pad_h !== null) $css_mob .= '.cphub-card .cphub-title{padding:'.max(0, intval(round(($title_pad_v?:0) * $s_mob))).'px '.max(0, intval(round(($title_pad_h?:0) * $s_mob))).'px;}';
            if ($excerpt_mt !== null) $css_mob .= '.cphub-card .cphub-excerpt{margin-top:'.max(0, intval(round($excerpt_mt * $s_mob))).'px;}';
            if ($excerpt_mb !== null) $css_mob .= '.cphub-card .cphub-excerpt{margin-bottom:'.max(0, intval(round($excerpt_mb * $s_mob))).'px;}';
            if ($excerpt_pv !== null || $excerpt_ph !== null) $css_mob .= '.cphub-card .cphub-excerpt{padding:'.max(0, intval(round(($excerpt_pv?:0) * $s_mob))).'px '.max(0, intval(round(($excerpt_ph?:0) * $s_mob))).'px;}';
            if ($content_mt !== null) $css_mob .= '.cphub-card .cphub-content{margin-top:'.max(0, intval(round($content_mt * $s_mob))).'px;}';
            if ($content_mb !== null) $css_mob .= '.cphub-card .cphub-content{margin-bottom:'.max(0, intval(round($content_mb * $s_mob))).'px;}';
            if ($content_pv !== null || $content_ph !== null) $css_mob .= '.cphub-card .cphub-content{padding:'.max(0, intval(round(($content_pv?:0) * $s_mob))).'px '.max(0, intval(round(($content_ph?:0) * $s_mob))).'px;}';
            if ($meta_mt !== null) $css_mob .= '.cphub-card .cphub-meta{margin-top:'.max(0, intval(round($meta_mt * $s_mob))).'px;}';
            if ($meta_mb !== null) $css_mob .= '.cphub-card .cphub-meta{margin-bottom:'.max(0, intval(round($meta_mb * $s_mob))).'px;}';
            if ($meta_pv !== null || $meta_ph !== null) $css_mob .= '.cphub-card .cphub-meta{padding:'.max(0, intval(round(($meta_pv?:0) * $s_mob))).'px '.max(0, intval(round(($meta_ph?:0) * $s_mob))).'px;}';
            if ($image_mt !== null) $css_mob .= '.cphub-card .cphub-img{margin-top:'.max(0, intval(round($image_mt * $s_mob))).'px;}';
            if ($image_mb !== null) $css_mob .= '.cphub-card .cphub-img{margin-bottom:'.max(0, intval(round($image_mb * $s_mob))).'px;}';
            if ($image_pv !== null || $image_ph !== null) $css_mob .= '.cphub-card .cphub-img{padding:'.max(0, intval(round(($image_pv?:0) * $s_mob))).'px '.max(0, intval(round(($image_ph?:0) * $s_mob))).'px;}';
            if ($button_mt !== null) $css_mob .= '.cphub-card .cphub-btn{margin-top:'.max(0, intval(round($button_mt * $s_mob))).'px;}';
            if ($button_mb !== null) $css_mob .= '.cphub-card .cphub-btn{margin-bottom:'.max(0, intval(round($button_mb * $s_mob))).'px;}';
        }
        // Grid columns at mobile breakpoint
        if ($layout_type === 'grid') {
            $gc_m = max(1, intval($styles['grid_cols_mob'] ?? 1));
            $css_mob .= '.cphub-grid{grid-template-columns:repeat('.$gc_m.',minmax(0,1fr));}';
        }
        if ($css_mob !== '') $css .= '@media (max-width: '.$mob_break.'px){'.$css_mob.'}';
        return $css;
    }

    private function sanitize_wysiwyg_html($html)
    {
        $html = (string)$html;
        // Ensure paragraphs from single-linebreaks
        $html = wpautop($html);
        // Limit style attributes to color only on spans
        $html = preg_replace_callback('/<span\b([^>]*?)>/i', function($m){
            $attrs = $m[1];
            $colorDecl = '';
            if (preg_match('/style\s*=\s*(["\'])(.*?)\1/i', $attrs, $sm)) {
                $style = $sm[2];
                foreach (explode(';', $style) as $decl) {
                    $decl = trim($decl);
                    if ($decl === '') continue;
                    if (stripos($decl, 'color:') === 0) {
                        // Normalize to keep only color
                        $colorDecl = $decl;
                        break;
                    }
                }
                // Remove existing style attribute entirely
                $attrs = preg_replace('/\s*style\s*=\s*(["\'])(.*?)\1/i', '', $attrs);
            }
            if ($colorDecl !== '') {
                $attrs .= ' style="' . esc_attr($colorDecl . ';') . '"';
            }
            return '<span' . $attrs . '>';
        }, $html);
        // Allowed HTML same as post content
        $allowed = wp_kses_allowed_html('post');
        // Ensure span allows class and style
        if (!isset($allowed['span'])) $allowed['span'] = [];
        $allowed['span']['class'] = true;
        $allowed['span']['style'] = true;
        // Kses sanitize
        return wp_kses($html, $allowed);
    }

    public function enqueue_admin_assets($hook)
    {
        if ($hook !== 'toplevel_page_cphub') return;
        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'types';
        if ($tab === 'styles') {
            wp_enqueue_script('jquery-ui-sortable');
        }
    }

    public function enqueue_global_css()
    {
        // Enqueue Global CSS on Publisher front end if defined
        if (is_admin()) return;
        $opt = get_option(self::OPTION_GLOBAL_CSS, ['css'=>'','version'=>'']);
        $css = isset($opt['css']) ? (string)$opt['css'] : '';
        if ($css === '') return;
        $ver = isset($opt['version']) ? substr((string)$opt['version'], 0, 10) : '0';
        $handle = 'cphub-pub-global-' . $ver;
        if (!wp_style_is($handle, 'registered')) {
            wp_register_style($handle, false, [], null);
            wp_add_inline_style($handle, $css);
        }
        wp_enqueue_style($handle);
    }

    public function render_feed()
    {
        // Gate: optional secret key
        $feed_settings = get_option(self::OPTION_FEED, []);
        $secret = $feed_settings['secret_key'] ?? '';
        $req_key = sanitize_text_field(get_query_var('key'));
        if ($secret && $req_key !== $secret) {
            status_header(403);
            header('Content-Type: text/plain; charset=' . get_option('blog_charset'));
            echo 'Forbidden: invalid or missing key';
            return;
        }

        $types = get_option(self::OPTION_KEY, []);
        $cpt   = sanitize_key(get_query_var('cpt'));
        $per   = intval(get_query_var('n')) ?: intval($feed_settings['items_per_feed'] ?? 20);
        // Cap page size to a safe maximum
        $per   = max(1, min(100, $per));
        $paged = max(1, intval(get_query_var('paged')));
        $modified_since = sanitize_text_field(get_query_var('modified_since'));
        $location = sanitize_key(get_query_var('location'));

        if ($cpt && !isset($types[$cpt])) {
            status_header(400);
            header('Content-Type: text/plain; charset=' . get_option('blog_charset'));
            echo 'Unknown CPT: ' . esc_html($cpt);
            return;
        }

        $query_args = [
            'post_type'           => $cpt ? [$cpt] : array_keys($types),
            'post_status'         => 'publish',
            'posts_per_page'      => $per,
            'paged'               => $paged,
            'ignore_sticky_posts' => true,
            'orderby'             => 'date',
            'order'               => 'DESC',
        ];
        if ($modified_since && preg_match('/^\d{4}-\d{2}-\d{2}$/', $modified_since)) {
            $query_args['date_query'] = [
                ['column' => 'post_modified_gmt', 'after' => $modified_since]
            ];
            $query_args['orderby'] = 'modified';
        }
        if ($location) {
            $query_args['tax_query'] = [
                [
                    'taxonomy' => 'location',
                    'field'    => 'slug',
                    'terms'    => [$location, 'all-locations'],
                    'operator' => 'IN',
                ]
            ];
        }

        $cache_key = $this->get_cache_key($query_args);
        $items = get_transient($cache_key);
        if ($items === false) {
            $q = new WP_Query($query_args);
            $items = [];
            if ($q->have_posts()) {
                while ($q->have_posts()) {
                    $q->the_post();
                    $items[] = $this->map_post_to_feed_item(get_post());
                }
                wp_reset_postdata();
            }
            set_transient($cache_key, $items, 60 * 5); // 5 mins
        }

        // Compute Last-Modified across items and build ETag
        $last_mod_ts = 0;
        if (is_array($items)) {
            foreach ($items as $it) {
                if (!empty($it['modified'])) {
                    $ts = strtotime($it['modified']);
                    if ($ts && $ts > $last_mod_ts) $last_mod_ts = $ts;
                }
            }
        }
        $last_modified_http = $last_mod_ts ? gmdate('D, d M Y H:i:s', $last_mod_ts) . ' GMT' : '';
        $etag_raw = md5(wp_json_encode([
            'cpt' => $cpt,
            'per' => $per,
            'paged' => $paged,
            'modified_since' => $modified_since,
            'location' => $location,
            'last' => $last_mod_ts,
            'count' => is_array($items) ? count($items) : 0,
            'v' => 1,
        ]));
        $etag = '"' . $etag_raw . '"';

        // Conditional request handling
        $if_none_match = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim($_SERVER['HTTP_IF_NONE_MATCH']) : '';
        $if_modified_since = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? trim($_SERVER['HTTP_IF_MODIFIED_SINCE']) : '';
        $send_304 = false;
        if ($if_none_match && $if_none_match === $etag) {
            $send_304 = true;
        } elseif ($last_mod_ts && $if_modified_since) {
            $ims_ts = strtotime($if_modified_since);
            if ($ims_ts && $ims_ts >= $last_mod_ts) $send_304 = true;
        }

        if ($send_304) {
            status_header(304);
            if ($last_modified_http) header('Last-Modified: ' . $last_modified_http);
            header('ETag: ' . $etag);
            header('Cache-Control: public, max-age=300');
            return;
        }

        // Normal RSS output with caching headers
        if ($last_modified_http) header('Last-Modified: ' . $last_modified_http);
        header('ETag: ' . $etag);
        header('Cache-Control: public, max-age=300');
        $this->output_rss($items, $cpt, $paged);
    }

    private function map_post_to_feed_item(WP_Post $p)
    {
        $thumb = get_the_post_thumbnail_url($p, 'full');
        $cats  = get_the_terms($p, 'category'); // only if CPT supports default category; harmless if none
        $cats  = is_array($cats) ? wp_list_pluck($cats, 'name') : [];
        $meta  = get_post_meta($p->ID);
        // Reduce meta: flatten scalar values only
        $meta_slim = [];
        foreach ($meta as $k => $v) {
            if (is_protected_meta($k, 'post')) continue;
            $first = is_array($v) ? reset($v) : $v;
            if (is_scalar($first)) $meta_slim[$k] = (string)$first;
        }

        // Enrich media fields with URL and mime so the feed contains full info
        $types = get_option(self::OPTION_KEY, []);
        $def = $types[$p->post_type] ?? null;
        $fields = $def && is_array($def) ? ($def['fields'] ?? []) : [];
        if ($fields) {
            foreach ($fields as $f) {
                if (($f['type'] ?? '') === 'media') {
                    $key = $f['key'];
                    $id = isset($meta_slim[$key]) ? intval($meta_slim[$key]) : 0;
                    if ($id > 0) {
                        $url = wp_get_attachment_url($id);
                        $mime = get_post_mime_type($id);
                        if ($url) {
                            $meta_slim[$key . '_id']   = (string)$id;
                            $meta_slim[$key . '_url']  = (string)$url;
                            if ($mime) $meta_slim[$key . '_mime'] = (string)$mime;
                        }
                    }
                }
            }
        }

        // Collect custom taxonomy terms for this post type (include 'location' explicitly for visibility)
        $tax_terms = [];
        $assigned_tax = $def && is_array($def) ? (array)($def['taxonomies'] ?? []) : [];
        if (taxonomy_exists('location')) $assigned_tax[] = 'location';
        $assigned_tax = array_values(array_unique(array_filter($assigned_tax)));
        foreach ($assigned_tax as $tax) {
            $terms = get_the_terms($p, $tax);
            if (is_array($terms)) {
                foreach ($terms as $t) {
                    $tax_terms[] = [
                        'tax'  => $tax,
                        'name' => $t->name,
                        'slug' => $t->slug,
                        'id'   => (int)$t->term_id,
                    ];
                }
            }
        }

        return [
            'id'          => (string)$p->ID,
            'title'       => get_the_title($p),
            'link'        => get_permalink($p),
            'excerpt'     => $p->post_excerpt ?: wp_trim_words(wp_strip_all_tags($p->post_content), 40, '…'),
            'content'     => apply_filters('the_content', $p->post_content),
            'author'      => get_the_author_meta('display_name', $p->post_author),
            'date'        => mysql2date(DATE_RSS, $p->post_date_gmt, false),
            'modified'    => mysql2date(DATE_RSS, $p->post_modified_gmt, false),
            'categories'  => $cats,
            'thumb'       => $thumb,
            'meta'        => $meta_slim,
            'post_type'   => $p->post_type,
            'tax_terms'   => $tax_terms,
        ];
    }

    /* ---------------------- Meta boxes ---------------------- */
    public function register_meta_boxes()
    {
        $types = get_option(self::OPTION_KEY, []);
        foreach ($types as $slug => $def) {
            $fields = isset($def['fields']) && is_array($def['fields']) ? $def['fields'] : [];
            if (!$fields) continue;
            add_meta_box(
                'cphub_meta_' . $slug,
                'CPT Hub Fields',
                [$this, 'render_meta_box'],
                $slug,
                'normal',
                'default',
                ['slug' => $slug, 'fields' => $fields]
            );
        }
    }

    /* ---------------- Elementor Single Template (Publisher) ------------- */
    public function register_elementor_meta_box() { /* Elementor removed */ }

    // Elementor integrations removed.

    // Elementor integrations removed.

    // Elementor integrations removed.

    public function render_elementor_single_content($content) { return $content; }

    public function restore_autop_filters() { /* Elementor removed */ }

    public function mark_elementor_single_context() { /* Elementor removed */ }

    public function filter_body_class($classes) { return $classes; }

    // Elementor integrations removed.

    public function render_meta_box($post, $box)
    {
        $slug   = $box['args']['slug'];
        $fields = $box['args']['fields'];
        wp_nonce_field('cphub_meta_' . $slug, 'cphub_meta_nonce');
        echo '<table class="form-table">';
        foreach ($fields as $f) {
            $key = esc_attr($f['key']);
            $lab = esc_html($f['label']);
            $typ = $f['type'] ?? 'text';
            $val = get_post_meta($post->ID, $f['key'], true);
            echo '<tr><th><label for="cphub_meta_' . $key . '">' . $lab . '</label></th><td>';
            switch ($typ) {
                case 'wysiwyg':
                    // Compact WYSIWYG editor
                    $settings = [
                        'textarea_name' => 'cphub_meta[' . $key . ']',
                        'textarea_rows' => 8,
                        'media_buttons' => false,
                        'teeny' => false,
                        'quicktags' => true,
                        'tinymce' => [
                            'toolbar1' => 'formatselect,|,bold,italic,underline,|,bullist,numlist,|,link,unlink,|,removeformat',
                            'toolbar2' => '',
                            'block_formats' => 'Paragraph=p;Heading 2=h2;Heading 3=h3;Heading 4=h4',
                            'wpautop' => true,
                        ],
                    ];
                    if (function_exists('wp_editor')) {
                        wp_editor((string)$val, 'cphub_meta_' . $key, $settings);
                    } else {
                        echo '<textarea id="cphub_meta_' . $key . '" name="cphub_meta[' . $key . ']" rows="8" class="large-text">' . esc_textarea($val) . '</textarea>';
                    }
                    break;
                case 'textarea':
                    echo '<textarea id="cphub_meta_' . $key . '" name="cphub_meta[' . $key . ']" rows="4" class="large-text">' . esc_textarea($val) . '</textarea>';
                    break;
                case 'number':
                    echo '<input type="number" id="cphub_meta_' . $key . '" name="cphub_meta[' . $key . ']" value="' . esc_attr($val) . '" class="regular-text" />';
                    break;
                case 'url':
                    echo '<input type="url" id="cphub_meta_' . $key . '" name="cphub_meta[' . $key . ']" value="' . esc_attr($val) . '" class="regular-text" />';
                    break;
                case 'select':
                    $opts = isset($f['options']) ? (array)$f['options'] : [];
                    echo '<select id="cphub_meta_' . $key . '" name="cphub_meta[' . $key . ']">';
                    echo '<option value="">— Select —</option>';
                    foreach ($opts as $o) {
                        $sel = selected((string)$val === (string)$o, true, false);
                        echo '<option value="' . esc_attr($o) . '" ' . $sel . '>' . esc_html($o) . '</option>';
                    }
                    echo '</select>';
                    break;
                case 'media':
                    // Ensure media scripts are available
                    if (function_exists('wp_enqueue_media')) { wp_enqueue_media(); }
                    $mt = isset($f['media_type']) ? $f['media_type'] : 'file';
                    $att_id = intval($val);
                    $preview = '';
                    if ($att_id) {
                        if ($mt === 'image') {
                            $img = wp_get_attachment_image($att_id, 'thumbnail', false, ['style' => 'max-width:150px;height:auto;']);
                            if ($img) $preview = $img;
                        }
                        if (!$preview) {
                            $url = wp_get_attachment_url($att_id);
                            if ($url) $preview = '<a href="' . esc_url($url) . '" target="_blank">' . esc_html(basename($url)) . '</a>';
                        }
                    }
                    echo '<div class="cphub-media-field" data-mediatype="' . esc_attr($mt) . '">';
                    echo '<input type="hidden" name="cphub_meta[' . $key . ']" value="' . esc_attr($att_id ?: '') . '" class="cphub-media-input" />';
                    echo '<button type="button" class="button cphub-media-select">Select Media</button> ';
                    echo '<button type="button" class="button cphub-media-clear" ' . ($att_id ? '' : 'style="display:none;"') . '>Clear</button> ';
                    echo '<span class="cphub-media-preview" style="display:inline-block;margin-left:8px;vertical-align:middle;">' . $preview . '</span>';
                    echo '</div>';
?>
                    <script>
                        (function(){
                            function bindMediaPickers(){
                                document.querySelectorAll('#poststuff .cphub-media-field').forEach(function(wrap){
                                    if (wrap.__cphub_bound) return; // prevent double-binding
                                    wrap.__cphub_bound = true;
                                    var selectBtn = wrap.querySelector('.cphub-media-select');
                                    var clearBtn  = wrap.querySelector('.cphub-media-clear');
                                    var input     = wrap.querySelector('.cphub-media-input');
                                    var preview   = wrap.querySelector('.cphub-media-preview');
                                    var type = wrap.getAttribute('data-mediatype') || 'file';
                                    var frame;
                                    function open(){
                                        if (!window.wp || !wp.media) return; // safety
                                        if (frame) { frame.open(); return; }
                                        frame = wp.media({
                                            title: type === 'image' ? 'Select Image' : 'Select File',
                                            library: { type: type === 'image' ? 'image' : null },
                                            multiple: false,
                                        });
                                        frame.on('select', function(){
                                            var att = frame.state().get('selection').first().toJSON();
                                            input.value = att.id || '';
                                            if (type === 'image' && att.sizes && att.sizes.thumbnail) {
                                                preview.innerHTML = '<img src="' + att.sizes.thumbnail.url + '" style="max-width:150px;height:auto;" />';
                                            } else if (att.url) {
                                                var name = att.filename || att.url.split('/').pop();
                                                preview.innerHTML = '<a target="_blank" href="' + att.url + '">' + name + '</a>';
                                            } else {
                                                preview.textContent = '';
                                            }
                                            clearBtn.style.display = '';
                                        });
                                        frame.open();
                                    }
                                    if (selectBtn) selectBtn.addEventListener('click', open);
                                    if (clearBtn) clearBtn.addEventListener('click', function(){
                                        input.value=''; preview.textContent=''; clearBtn.style.display='none';
                                    });
                                });
                            }
                            function init(){
                                if (window.wp && wp.media) {
                                    bindMediaPickers();
                                } else {
                                    // Retry shortly until wp.media is available
                                    var tries = 0;
                                    var timer = setInterval(function(){
                                        tries++;
                                        if (window.wp && wp.media) {
                                            clearInterval(timer);
                                            bindMediaPickers();
                                        } else if (tries > 20) {
                                            clearInterval(timer);
                                        }
                                    }, 200);
                                }
                            }
                            if (document.readyState === 'complete' || document.readyState === 'interactive') {
                                init();
                            } else {
                                document.addEventListener('DOMContentLoaded', init);
                            }
                        })();
                    </script>
<?php
                    break;
                case 'text':
                default:
                    echo '<input type="text" id="cphub_meta_' . $key . '" name="cphub_meta[' . $key . ']" value="' . esc_attr($val) . '" class="regular-text" />';
            }
            echo '</td></tr>';
        }
        echo '</table>';
    }

    public function save_meta_fields($post_id, $post, $update)
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!$post || $post->post_type === 'revision') return;
        if (!current_user_can('edit_post', $post_id)) return;

        $types = get_option(self::OPTION_KEY, []);
        if (!isset($types[$post->post_type])) return;
        $def = $types[$post->post_type];
        $fields = isset($def['fields']) && is_array($def['fields']) ? $def['fields'] : [];
        if (!$fields) return;

        if (!isset($_POST['cphub_meta_nonce']) || !wp_verify_nonce($_POST['cphub_meta_nonce'], 'cphub_meta_' . $post->post_type)) return;

        $incoming = isset($_POST['cphub_meta']) && is_array($_POST['cphub_meta']) ? $_POST['cphub_meta'] : [];
        foreach ($fields as $f) {
            $key = $f['key'];
            $typ = $f['type'] ?? 'text';
            $raw = $incoming[$key] ?? '';
            $val = '';
            switch ($typ) {
                case 'wysiwyg':
                    // Allow safe HTML; keep only allowed tags and filter style to color only
                    $val = is_string($raw) ? $this->sanitize_wysiwyg_html($raw) : '';
                    break;
                case 'textarea':
                    $val = is_string($raw) ? sanitize_textarea_field($raw) : '';
                    break;
                case 'number':
                    // keep as string; if numeric store as is
                    if (is_numeric($raw)) {
                        $val = (string)$raw;
                    } else {
                        $val = '';
                    }
                    break;
                case 'url':
                    $val = is_string($raw) ? esc_url_raw($raw) : '';
                    break;
                case 'select':
                    $opts = isset($f['options']) ? (array)$f['options'] : [];
                    $raw = is_string($raw) ? trim($raw) : '';
                    $val = in_array($raw, $opts, true) ? $raw : '';
                    break;
                case 'media':
                    $id = is_scalar($raw) ? intval($raw) : 0;
                    if ($id > 0) {
                        $att = get_post($id);
                        $ok = $att && $att->post_type === 'attachment';
                        $mt = isset($f['media_type']) ? $f['media_type'] : 'file';
                        if ($ok && $mt === 'image') {
                            $mime = get_post_mime_type($att);
                            $ok = is_string($mime) && strpos($mime, 'image/') === 0;
                        }
                        $val = $ok ? (string)$id : '';
                    } else {
                        $val = '';
                    }
                    break;
                case 'text':
                default:
                    $val = is_string($raw) ? sanitize_text_field($raw) : '';
            }

            if ($val === '' || $val === null) {
                delete_post_meta($post_id, $key);
            } else {
                update_post_meta($post_id, $key, $val);
            }
        }
    }

    private function output_rss(array $items, $cpt, $paged)
    {
        $blogname = get_bloginfo('name');
        header('Content-Type: application/rss+xml; charset=' . get_option('blog_charset'), true);
        echo "<?xml version=\"1.0\" encoding=\"" . get_option('blog_charset') . "\"?>\n";
    ?>
        <rss version="2.0"
            xmlns:content="http://purl.org/rss/1.0/modules/content/"
            xmlns:media="http://search.yahoo.com/mrss/"
            xmlns:cphub="https://example.com/cphub/ns">
            <channel>
                <title><?php echo esc_html($blogname . ' – CPT Hub' . ($cpt ? (' – ' . $cpt) : '')); ?></title>
                <link><?php echo esc_url(home_url('/')); ?></link>
                <description>Structured feed of custom post types for cross-site consumption.</description>
                <language><?php echo esc_html(get_bloginfo('language')); ?></language>
                <generator>CPT Hub Publisher</generator>
                <lastBuildDate><?php echo esc_html(date(DATE_RSS, current_time('timestamp', true))); ?></lastBuildDate>
                <cphub:paged><?php echo (int)$paged; ?></cphub:paged>
                <?php foreach ($items as $it): ?>
                    <item>
                        <title><?php echo esc_html($it['title']); ?></title>
                        <link><?php echo esc_url($it['link']); ?></link>
                        <guid isPermaLink="true"><?php echo esc_url($it['link']); ?></guid>
                        <pubDate><?php echo esc_html($it['date']); ?></pubDate>
                        <cphub:modified><?php echo esc_html($it['modified']); ?></cphub:modified>
                        <cphub:post_type><?php echo esc_html($it['post_type']); ?></cphub:post_type>
                        <?php if (!empty($it['categories'])): foreach ($it['categories'] as $cat): ?>
                                <category><?php echo esc_html($cat); ?></category>
                        <?php endforeach;
                        endif; ?>
                        <?php if (!empty($it['tax_terms'])): foreach ($it['tax_terms'] as $tt): ?>
                                <cphub:term tax="<?php echo esc_attr($tt['tax']); ?>" id="<?php echo (int)$tt['id']; ?>" slug="<?php echo esc_attr($tt['slug']); ?>"><?php echo esc_html($tt['name']); ?></cphub:term>
                        <?php endforeach; endif; ?>
                        <description>
                            <![CDATA[<?php echo $it['excerpt']; ?>]]>
                        </description>
                        <content:encoded>
                            <![CDATA[<?php echo $it['content']; ?>]]>
                        </content:encoded>
                        <?php if (!empty($it['thumb'])): ?>
                            <media:content url="<?php echo esc_url($it['thumb']); ?>" medium="image" />
                        <?php endif; ?>
                        <?php if (!empty($it['meta'])): foreach ($it['meta'] as $k => $v): ?>
                                <cphub:meta key="<?php echo esc_attr($k); ?>">
                                    <![CDATA[<?php echo $v; ?>]]>
                                </cphub:meta>
                        <?php endforeach;
                        endif; ?>
                    </item>
                <?php endforeach; ?>
            </channel>
        </rss>
<?php
    }

    /* ---------------------- Admin Columns ------------------ */
    public function register_admin_columns()
    {
        if (!is_admin()) return;
        $types = get_option(self::OPTION_KEY, []);
        foreach ($types as $slug => $def) {
            add_filter("manage_edit-{$slug}_columns", [$this, 'filter_admin_columns']);
            add_action("manage_{$slug}_posts_custom_column", [$this, 'render_admin_column'], 10, 2);
        }
    }

    public function filter_admin_columns($columns)
    {
        // Insert Locations column after Title if possible
        $new = [];
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
            if ($key === 'title') {
                $new['cphub_location'] = 'Locations';
            }
        }
        if (!isset($new['cphub_location'])) {
            $new['cphub_location'] = 'Locations';
        }
        return $new;
    }

    public function render_admin_column($column, $post_id)
    {
        if ($column !== 'cphub_location') return;
        $terms = get_the_terms($post_id, 'location');
        if (is_array($terms) && $terms) {
            $names = wp_list_pluck($terms, 'name');
            echo esc_html(implode(', ', $names));
        } else {
            echo '—';
        }
    }

    /* ---------------------- Locations defaults -------------- */
    public function ensure_default_location($post_id, $post, $update)
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!$post || $post->post_type === 'revision') return;
        $types = get_option(self::OPTION_KEY, []);
        if (!isset($types[$post->post_type])) return;
        if (!taxonomy_exists('location')) return;
        $terms = get_the_terms($post_id, 'location');
        if (!$terms || is_wp_error($terms) || empty($terms)) {
            if (!term_exists('all-locations', 'location')) {
                wp_insert_term('All Locations', 'location', ['slug' => 'all-locations']);
            }
            wp_set_post_terms($post_id, ['all-locations'], 'location', false);
        }
    }

    public function protect_all_locations_delete($term, $taxonomy)
    {
        if ($taxonomy !== 'location') return $term;
        $t = get_term($term, 'location');
        if ($t && !is_wp_error($t) && $t->slug === 'all-locations') {
            return new WP_Error('cannot_delete', 'The "All Locations" term cannot be deleted.');
        }
        return $term;
    }

    public function protect_all_locations_slug($data, $term_id, $taxonomy, $args)
    {
        if ($taxonomy !== 'location') return $data;
        $t = get_term($term_id, 'location');
        if ($t && !is_wp_error($t) && $t->slug === 'all-locations') {
            $data['slug'] = 'all-locations';
        }
        return $data;
    }

    /* ---------------------- Cache busting ------------------- */
    public function bust_feed_cache_on_save($post_id, $post, $update)
    {
        if (wp_is_post_revision($post_id)) return;
        $types = get_option(self::OPTION_KEY, []);
        if (isset($types[$post->post_type])) {
            $this->flush_all_feed_caches();
        }
    }

    public function bust_feed_cache_on_delete($post_id)
    {
        $post = get_post($post_id);
        if (!$post) return;
        $types = get_option(self::OPTION_KEY, []);
        if (isset($types[$post->post_type])) {
            $this->flush_all_feed_caches();
        }
    }

    private function flush_all_feed_caches()
    {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_cphub_feed_%' OR option_name LIKE '_transient_timeout_cphub_feed_%'");
    }
}

new CPT_Hub_Publisher();

/* -------------------------------------------------------------
 * Optional: Consumer snippet for other sites (paste into theme or a small plugin):
 *
 * add_shortcode('cphub_list', function($atts){
 *   $url = esc_url_raw($atts['url'] ?? '');
 *   if (!$url) return '';
 *   include_once ABSPATH . WPINC . '/feed.php';
 *   $rss = fetch_feed($url);
 *   if (is_wp_error($rss)) return '<em>Feed unavailable.</em>';
 *   $max = $rss->get_item_quantity(10);
 *   $items = $rss->get_items(0, $max);
 *   $out = '<ul class="cphub-list">';
 *   foreach ($items as $item) {
 *     $out .= '<li><a href="'. esc_url($item->get_link()) .'">'. esc_html($item->get_title()) .'</a></li>';
 *   }
 *   $out .= '</ul>';
 *   return $out;
 * });
 *
 * Usage: [cphub_list url="https://publisher-site.com/feed/cphub/slides"]
 * ------------------------------------------------------------- */
