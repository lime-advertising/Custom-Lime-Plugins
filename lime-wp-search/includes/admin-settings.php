<?php

// Register menu
add_action('admin_menu', function () {
    add_menu_page(
        'Lime WP Search Settings',
        'Lime WP Search',
        'manage_options',
        'lime-wp-search-settings',
        'lime_wp_search_settings_page',
        'dashicons-search',
        80
    );
});

// Register settings
add_action('admin_init', function () {
    // register_setting('lime_wp_search_group', 'lime_wp_search_options');

    register_setting(
        'lime_wp_search_group',
        'lime_wp_search_options',
        [
            'sanitize_callback' => function ($input) {
                $out = [];
                $out['enabled'] = !empty($input['enabled']) ? 1 : 0;

                // existing fields
                $out['post_types'] = isset($input['post_types']) ? array_map('sanitize_text_field', (array)$input['post_types']) : [];
                $out['meta_keys']  = isset($input['meta_keys']) ? sanitize_text_field($input['meta_keys']) : '';

                // NEW fields
                $out['click_to_show']    = !empty($input['click_to_show']) ? 1 : 0;
                $out['trigger_selector'] = isset($input['trigger_selector']) ? sanitize_text_field($input['trigger_selector']) : '';

                return $out;
            }
        ]
    );

    add_settings_section(
        'lime_wp_search_main',
        'Search Configuration',
        function () {
            echo '<p>Customize how Lime WP Search behaves across your site.</p>';
        },
        'lime-wp-search-settings'
    );

    add_settings_field('enabled', 'Enable Custom Search', 'lime_wp_field_enabled', 'lime-wp-search-settings', 'lime_wp_search_main');
    add_settings_field('post_types', 'Searchable Post Types', 'lime_wp_field_post_types', 'lime-wp-search-settings', 'lime_wp_search_main');
    add_settings_field('meta_keys', 'Custom Fields to Search', 'lime_wp_field_meta_keys', 'lime-wp-search-settings', 'lime_wp_search_main');
    add_settings_field('click_to_show', 'Show on Trigger Click Only', 'lime_wp_field_click_to_show', 'lime-wp-search-settings', 'lime_wp_search_main');
    add_settings_field('trigger_selector', 'Trigger CSS Selector', 'lime_wp_field_trigger_selector', 'lime-wp-search-settings', 'lime_wp_search_main');
});

// Field renderers
function lime_wp_field_enabled()
{
    $options = get_option('lime_wp_search_options');
    $checked = !empty($options['enabled']) ? 'checked' : '';
    echo "<input type='checkbox' name='lime_wp_search_options[enabled]' value='1' $checked> Enable custom search override";
}

function lime_wp_field_post_types()
{
    $options = get_option('lime_wp_search_options');
    $post_types = get_post_types(['public' => true], 'objects');
    $selected = isset($options['post_types']) ? (array)$options['post_types'] : [];

    foreach ($post_types as $pt) {
        $checked = in_array($pt->name, $selected) ? 'checked' : '';
        echo "<label><input type='checkbox' name='lime_wp_search_options[post_types][]' value='{$pt->name}' $checked> {$pt->label}</label><br>";
    }
}

function lime_wp_field_meta_keys()
{
    $options = get_option('lime_wp_search_options');
    $value = isset($options['meta_keys']) ? esc_attr($options['meta_keys']) : '';
    echo "<input type='text' name='lime_wp_search_options[meta_keys]' value='$value' class='regular-text' placeholder='_sku,_custom_field'>";
}

// Settings page renderer
function lime_wp_search_settings_page()
{
?>
    <div class="wrap">
        <h1>Lime WP Search Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('lime_wp_search_group');
            do_settings_sections('lime-wp-search-settings');
            submit_button();
            ?>
        </form>
    </div>
<?php
}

function lime_wp_field_click_to_show()
{
    $options = get_option('lime_wp_search_options');
    $checked = !empty($options['click_to_show']) ? 'checked' : '';
    echo "<label><input type='checkbox' name='lime_wp_search_options[click_to_show]' value='1' $checked> Only display the form after the trigger is clicked</label>";
}

function lime_wp_field_trigger_selector()
{
    $options = get_option('lime_wp_search_options');
    $value = isset($options['trigger_selector']) ? esc_attr($options['trigger_selector']) : '';
    echo "<input type='text' name='lime_wp_search_options[trigger_selector]' value='$value' class='regular-text' placeholder='.open-search or #search-toggle'>";
    echo "<p class='description'>Provide a CSS selector for the element that should open the search (e.g., a header icon/button).</p>";
}
