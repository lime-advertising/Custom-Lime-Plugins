<?php
/**
 * Plugin Name: ACF Field Path Shortcode
 * Description: Display ACF values using a path syntax (e.g., group/subfield), with optional mailto and money formatting.
 * Version:     1.0.0
 * Author:      Lime
 * License:     GPL-2.0-or-later
 * Text Domain: acf-field-path-shortcode
 */

if (!defined('ABSPATH')) exit;

define('ACF_FPS_VERSION', '1.0.0');
define('ACF_FPS_PATH', plugin_dir_path(__FILE__));
define('ACF_FPS_URL', plugin_dir_url(__FILE__));

spl_autoload_register(function ($class) {
    $prefix = 'Lime\\ACF_Path\\';
    $base_dir = ACF_FPS_PATH . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;

    $relative_class = substr($class, $len);
    $file_slug = strtolower(str_replace(['\\', '_'], ['-', '-'], $relative_class));
    $file = $base_dir . 'class-' . $file_slug . '.php';

    if (file_exists($file)) require $file;
});

add_action('plugins_loaded', function () {
    // Fail early if ACF isn't active.
    if (!function_exists('get_field')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>ACF Field Path Shortcode</strong> requires Advanced Custom Fields.</p></div>';
        });
        return;
    }

    // Boot the shortcode.
    Lime\ACF_Path\ACF_Field_Path_Shortcode::boot();
});
