<?php
/**
 * Plugin Name: CareerNest
 * Description: Standalone job portal plugin using only WordPress core APIs.
 * Version: 1.0.0
 * Author: Rohan T George
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Text Domain: careernest
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Basic constants.
define( 'CAREERNEST_VERSION', '1.0.0' );
define( 'CAREERNEST_FILE', __FILE__ );
define( 'CAREERNEST_DIR', plugin_dir_path( __FILE__ ) );
define( 'CAREERNEST_URL', plugin_dir_url( __FILE__ ) );

// Includes.
require_once CAREERNEST_DIR . 'includes/class-activator.php';
require_once CAREERNEST_DIR . 'includes/class-deactivator.php';
require_once CAREERNEST_DIR . 'includes/class-plugin.php';
require_once CAREERNEST_DIR . 'includes/Data/class-cpt.php';
require_once CAREERNEST_DIR . 'includes/Data/class-taxonomies.php';
require_once CAREERNEST_DIR . 'includes/Data/class-roles.php';
require_once CAREERNEST_DIR . 'includes/Admin/class-admin.php';
require_once CAREERNEST_DIR . 'includes/Admin/class-admin-menus.php';
require_once CAREERNEST_DIR . 'includes/Admin/class-meta-boxes.php';
require_once CAREERNEST_DIR . 'includes/Admin/class-admin-columns.php';
require_once CAREERNEST_DIR . 'includes/Admin/class-users.php';
require_once CAREERNEST_DIR . 'includes/Admin/class-settings.php';
require_once CAREERNEST_DIR . 'includes/Security/class-caps.php';

// Hooks.
register_activation_hook( __FILE__, [ '\\CareerNest\\Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ '\\CareerNest\\Deactivator', 'deactivate' ] );

// Bootstrap plugin runtime.
add_action( 'plugins_loaded', function () {
    // Lazy instantiate the plugin runtime.
    if ( class_exists( '\\CareerNest\\Plugin' ) ) {
        ( new \CareerNest\Plugin() )->run();
    }

    // Hook admin and security subsystems.
    if ( is_admin() ) {
        ( new \CareerNest\Admin\Admin() )->hooks();
    }
    ( new \CareerNest\Security\Caps() )->hooks();
    \CareerNest\Data\Roles::ensure_caps();
} );
