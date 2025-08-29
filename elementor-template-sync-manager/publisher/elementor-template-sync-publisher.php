<?php
/**
 * Plugin Name: Elementor Template Sync Manager — Publisher
 * Description: Publisher-side plugin to manage and deploy Elementor templates to Consumer sites.
 * Version: 0.1.0
 * Author: Lime Advertising
 * Requires at least: 6.2
 * Requires PHP: 8.1
 * Text Domain: etsm-publisher
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'ETSM_PUB_VERSION', '0.1.0' );
define( 'ETSM_PUB_FILE', __FILE__ );
define( 'ETSM_PUB_DIR', plugin_dir_path( __FILE__ ) );
define( 'ETSM_PUB_URL', plugin_dir_url( __FILE__ ) );

// Load shared utilities.
require_once dirname( __DIR__ ) . '/shared/includes/class-hmac.php';
require_once dirname( __DIR__ ) . '/shared/includes/class-json.php';

// Autoload Publisher includes.
require_once ETSM_PUB_DIR . 'includes/class-plugin.php';
require_once ETSM_PUB_DIR . 'includes/class-db.php';
require_once ETSM_PUB_DIR . 'includes/class-auth.php';
require_once ETSM_PUB_DIR . 'includes/class-rest-controller.php';
require_once ETSM_PUB_DIR . 'includes/class-admin.php';
require_once ETSM_PUB_DIR . 'includes/class-templates.php';
require_once ETSM_PUB_DIR . 'includes/class-deployments.php';

// Bootstrap plugin.
add_action( 'plugins_loaded', function () {
    \LimeAds\ETSM\Publisher\Plugin::instance();
});

// Activation/Deactivation.
register_activation_hook( __FILE__, [ '\\LimeAds\\ETSM\\Publisher\\Plugin', 'activate' ] );
register_deactivation_hook( __FILE__, [ '\\LimeAds\\ETSM\\Publisher\\Plugin', 'deactivate' ] );

