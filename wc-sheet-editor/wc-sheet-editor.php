<?php

/**
 * Plugin Name: WooCommerce Sheet Editor
 * Description: Spreadsheet-like grid to edit WooCommerce products directly in WP Admin.
 * Version:     0.2.0
 * Author:      Lime
 * License:     GPL-2.0-or-later
 * Text Domain: wc-sheet-editor
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) exit;

define('WCSE_FILE', __FILE__);
define('WCSE_DIR', plugin_dir_path(__FILE__));
define('WCSE_URL', plugin_dir_url(__FILE__));
define('WCSE_VER', '0.2.0');

require_once WCSE_DIR . 'includes/Plugin.php';

add_action('plugins_loaded', function () {
    load_plugin_textdomain('wc-sheet-editor', false, dirname(plugin_basename(WCSE_FILE)) . '/languages/');
    // Ensure WooCommerce is active
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>'
                . esc_html__('WooCommerce Sheet Editor requires WooCommerce to be active.', 'wc-sheet-editor')
                . '</p></div>';
        });
        return;
    }
    \WCSE\Plugin::instance()->init();
});

// Activation / deactivation hooks (kept minimal, no DB tables)
register_activation_hook(__FILE__, function () {
    if (!current_user_can('activate_plugins')) return;
    // Capabilities can be added here if you create custom caps.
});
register_deactivation_hook(__FILE__, function () {
    // Cleanup scheduled events etc if added in the future.
});
