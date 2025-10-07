<?php
// Exit if accessed directly
if (!defined('ABSPATH')) exit;

// Register settings
add_action('admin_init', function () {
    register_setting('wcp_settings_group', 'wcp_settings');

    add_settings_section(
        'wcp_main_section',
        'Compare Settings',
        null,
        'wcp-settings'
    );

    add_settings_field(
        'enable_compare',
        'Enable Compare Buttons',
        function () {
            $options = get_option('wcp_settings', []);
            if (!is_array($options)) {
                $options = [];
            }
?>
        <input type="hidden" name="wcp_settings[enable_compare]" value="0" />
        <input type="checkbox" name="wcp_settings[enable_compare]" value="1" <?php checked(1, $options['enable_compare'] ?? 1); ?> />

    <?php
        },
        'wcp-settings',
        'wcp_main_section'
    );

    add_settings_field(
        'product_card_class',
        'Product Card CSS Class',
        function () {
            $options = get_option('wcp_settings', []);
            if (!is_array($options)) {
                $options = [];
            }
            $value = isset($options['product_card_class']) ? esc_attr($options['product_card_class']) : '';
?>
        <input type="text" name="wcp_settings[product_card_class]" value="<?php echo $value; ?>" class="regular-text" placeholder="e.g. lf-product-card" />
        <p class="description">Optional. Limit compare buttons to product cards with this class. Leave blank for default behaviour.</p>
    <?php
        },
        'wcp-settings',
        'wcp_main_section'
    );
});

// Add admin menu
add_action('admin_menu', function () {
    add_menu_page(
        'Compare Settings',
        'Product Compare',
        'manage_options',
        'wcp-settings',
        function () {
    ?>
        <div class="wrap">
            <h1>WooCommerce Product Compare Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('wcp_settings_group');
                do_settings_sections('wcp-settings');
                submit_button();
                ?>
            </form>
        </div>
<?php
        },
        'dashicons-controls-repeat',
        56
    );
});
