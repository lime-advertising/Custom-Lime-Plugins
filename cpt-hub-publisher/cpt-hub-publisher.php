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
    const OPTION_LOC_SEEDED = 'cphub_locations_seeded'; // one-time location seeding flag
    const NONCE_ACTION = 'cphub_manage_types';

    public function __construct()
    {
        // Admin UI
        add_action('admin_menu',        [$this, 'admin_menu']);
        add_action('admin_init',        [$this, 'handle_admin_post']);
        add_action('admin_init',        [$this, 'register_settings']);
        add_action('admin_init',        [$this, 'register_admin_columns']);

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

        // Cache busting when content of any registered CPT changes
        add_action('save_post',         [$this, 'bust_feed_cache_on_save'], 10, 3);
        add_action('deleted_post',      [$this, 'bust_feed_cache_on_delete']);

        // Meta boxes for custom fields
        add_action('add_meta_boxes',    [$this, 'register_meta_boxes']);
        add_action('save_post',         [$this, 'save_meta_fields'], 9, 3);
        add_action('save_post',         [$this, 'ensure_default_location'], 20, 3);

        // Activation / Deactivation
        register_activation_hook(__FILE__, [__CLASS__, 'activate']);
        register_deactivation_hook(__FILE__, [__CLASS__, 'deactivate']);
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
            'items_per_feed' => max(1, intval($input['items_per_feed'] ?? 20)),
            'secret_key'     => sanitize_text_field($input['secret_key'] ?? ''),
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
                    $types[$slug] = [
                        'label'       => $label,
                        'supports'    => array_values(array_intersect($supports, ['title', 'editor', 'excerpt', 'thumbnail', 'custom-fields'])),
                        'has_archive' => $has_archive,
                        'fields'      => [], // custom meta field defs
                        'taxonomies'  => [], // assigned custom taxonomies
                    ];
                    update_option(self::OPTION_KEY, $types);
                    add_settings_error('cphub', 'cpt_added', 'Custom Post Type added.', 'updated');
                    flush_rewrite_rules();
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
                    if (!$label) {
                        add_settings_error('cphub', 'cpt_update_error', 'Please provide a valid label.', 'error');
                    } else {
                        $types[$slug]['label']       = $label;
                        $types[$slug]['supports']    = array_values(array_intersect($supports, ['title', 'editor', 'excerpt', 'thumbnail', 'custom-fields']));
                        $types[$slug]['has_archive'] = $has_archive;

                        // Normalize custom field definitions
                        $allowed_types = ['text', 'textarea', 'number', 'url', 'select', 'media'];
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
        }
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
                <?php $tabs = ['types' => 'Content Types', 'tax' => 'Taxonomies', 'feed' => 'Feed Settings', 'docs' => 'Documentation'];
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
                    case 'docs':
                        include $base . 'views/admin/tab-docs.php';
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
            <form method="post" class="card" style="padding:1em;max-width:900px;">
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
            <form method="post" class="card" style="padding:1em;max-width:900px;">
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
            <table class="widefat striped" style="max-width:900px;">
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
            <form method="post" class="card" style="padding:1em;max-width:900px;">
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
            <form method="post" class="card" style="padding:1em;max-width:900px;">
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
            <form method="post" action="options.php" class="card" style="padding:1em;max-width:900px;">
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
            $labels = [
                'name'          => $def['label'],
                'singular_name' => ucfirst($slug),
                'menu_name'     => $def['label'],
            ];
            $assigned_tax = isset($def['taxonomies']) ? (array)$def['taxonomies'] : [];
            $assigned_tax[] = 'location';
            $assigned_tax = array_values(array_unique(array_filter($assigned_tax)));

            $args = [
                'labels'       => $labels,
                'public'       => true,
                'show_in_rest' => true,
                'has_archive'  => !empty($def['has_archive']),
                'supports'     => $def['supports'] ?: ['title'],
                'rewrite'      => ['slug' => $slug],
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
                'total'       => (int)$q->found_posts,
                'total_pages' => (int)$q->max_num_pages,
                'items'       => $items,
            ];
            set_transient($cache_key, $payload, 60 * 5);
        }

        $response = new WP_REST_Response($payload, 200);
        $response->header('Cache-Control', 'public, max-age=300');
        return $response;
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

        // Collect custom taxonomy terms for this post type
        $tax_terms = [];
        $assigned_tax = $def && is_array($def) ? (array)($def['taxonomies'] ?? []) : [];
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
