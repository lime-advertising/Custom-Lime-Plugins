<?php
/**
 * Plugin Name: Lime Schema Boilerplate
 * Description: Admin UI + per-page controls to output clean JSON-LD that hides empty fields and follows best practices (@graph with stable @id).
 * Version: 1.0.0
 * Author: Lime
 * Text Domain: lime-schema
 */

if (!defined('ABSPATH')) exit;

// Plugin constants
define('LIME_SCHEMA_VERSION', '1.0.0');
define('LIME_SCHEMA_FILE', __FILE__);
define('LIME_SCHEMA_PATH', plugin_dir_path(__FILE__));
define('LIME_SCHEMA_URL', plugin_dir_url(__FILE__));
define('LIME_SCHEMA_TEXT_DOMAIN', 'lime-schema');

// Data keys
define('LIME_SCHEMA_OPTION_KEY', 'lime_schema_options');
define('LIME_SCHEMA_NONCE', 'lime_schema_nonce');
define('LIME_SCHEMA_META_KEY', '_lime_schema_meta');

// Includes
require_once LIME_SCHEMA_PATH . 'includes/class-lime-schema-utils.php';
require_once LIME_SCHEMA_PATH . 'includes/class-lime-schema-admin.php';
require_once LIME_SCHEMA_PATH . 'includes/class-lime-schema-renderer.php';
require_once LIME_SCHEMA_PATH . 'includes/class-lime-schema.php';

// Bootstrap
add_action('plugins_loaded', function(){
    Lime_Schema::instance();
});

// Add Settings link on the Plugins screen
add_filter('plugin_action_links_' . plugin_basename(LIME_SCHEMA_FILE), function ($links) {
    $url = admin_url('options-general.php?page=' . LIME_SCHEMA_OPTION_KEY);
    $settings_link = '<a href="' . esc_url($url) . '">' . esc_html__('Settings', LIME_SCHEMA_TEXT_DOMAIN) . '</a>';
    array_unshift($links, $settings_link);
    return $links;
});
