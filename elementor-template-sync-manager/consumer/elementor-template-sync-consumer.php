<?php
/**
 * Plugin Name: Elementor Template Sync Manager — Consumer
 * Description: Consumer-side plugin to receive and apply Elementor template updates from Publisher.
 * Version: 0.1.0
 * Author: Lime Advertising
 * Requires at least: 6.2
 * Requires PHP: 8.1
 * Text Domain: etsm-consumer
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'ETSM_CONS_VERSION', '0.1.0' );
define( 'ETSM_CONS_FILE', __FILE__ );
define( 'ETSM_CONS_DIR', plugin_dir_path( __FILE__ ) );
define( 'ETSM_CONS_URL', plugin_dir_url( __FILE__ ) );

// Shared utilities
require_once dirname( __DIR__ ) . '/shared/includes/class-hmac.php';
require_once dirname( __DIR__ ) . '/shared/includes/class-json.php';

// Includes
require_once ETSM_CONS_DIR . 'includes/class-plugin.php';
require_once ETSM_CONS_DIR . 'includes/class-db.php';
require_once ETSM_CONS_DIR . 'includes/class-auth.php';
require_once ETSM_CONS_DIR . 'includes/class-rest-controller.php';
require_once ETSM_CONS_DIR . 'includes/class-admin.php';
require_once ETSM_CONS_DIR . 'includes/class-sync.php';
require_once ETSM_CONS_DIR . 'includes/class-media.php';
require_once ETSM_CONS_DIR . 'includes/class-rollback.php';

add_action( 'plugins_loaded', function() {
    \LimeAds\ETSM\Consumer\Plugin::instance();
});

register_activation_hook( __FILE__, [ '\\LimeAds\\ETSM\\Consumer\\Plugin', 'activate' ] );
register_deactivation_hook( __FILE__, [ '\\LimeAds\\ETSM\\Consumer\\Plugin', 'deactivate' ] );

