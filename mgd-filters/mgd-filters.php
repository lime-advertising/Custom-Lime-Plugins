<?php
/**
 * Plugin Name:       MGD Filters
 * Plugin URI:        https://example.com/mgd-filters
 * Description:       Provides shortcode-based filters and customizable layouts for posts and custom post types.
 * Version:           0.1.0
 * Author:            Lime Advertising
 * Author URI:        https://limeadvertising.com
 * Text Domain:       mgd-filters
 * Domain Path:       /languages
 *
 * @package MGD_Filters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MGD_FILTERS_VERSION', '0.1.0' );
define( 'MGD_FILTERS_FILE', __FILE__ );
define( 'MGD_FILTERS_PATH', plugin_dir_path( __FILE__ ) );
define( 'MGD_FILTERS_URL', plugin_dir_url( __FILE__ ) );

require_once MGD_FILTERS_PATH . 'includes/class-loader.php';

/**
 * Initialize plugin after all plugins are loaded.
 *
 * @return void
 */
function mgd_filters_init() {
	$plugin = new \MGD_Filters\Plugin();
	$plugin->register_hooks();
}
add_action( 'plugins_loaded', 'mgd_filters_init' );

/**
 * Handle plugin activation.
 *
 * @return void
 */
function mgd_filters_activate() {
	\MGD_Filters\Plugin::activate();
}

/**
 * Handle plugin deactivation.
 *
 * @return void
 */
function mgd_filters_deactivate() {
	\MGD_Filters\Plugin::deactivate();
}

register_activation_hook( __FILE__, 'mgd_filters_activate' );
register_deactivation_hook( __FILE__, 'mgd_filters_deactivate' );

