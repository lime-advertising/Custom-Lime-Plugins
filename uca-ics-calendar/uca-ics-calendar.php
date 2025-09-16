<?php
/**
 * Plugin Name: UCA ICS Calendar
 * Description: Subscribe to a public .ics feed and display events via shortcode.
 * Version:     1.0.0
 * Author:      Rohan T George
 * License:     GPL-2.0-or-later
 * Text Domain: uca-ics
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'UCA_ICS_VER', '1.0.0' );
define( 'UCA_ICS_PATH', plugin_dir_path( __FILE__ ) );
define( 'UCA_ICS_URL', plugin_dir_url( __FILE__ ) );
define( 'UCA_ICS_OPT', 'uca_ics_settings' );          // option name
define( 'UCA_ICS_TRANSIENT', 'uca_ics_cache' );       // transient name
define( 'UCA_ICS_CRON_HOOK', 'uca_ics_refresh_hook' );

require_once UCA_ICS_PATH . 'includes/helpers.php';
require_once UCA_ICS_PATH . 'includes/class-uca-ics-calendar.php';
require_once UCA_ICS_PATH . 'includes/class-uca-ics-admin.php';
// Optional Calendar View module (admin tab + shortcode)
if (file_exists(UCA_ICS_PATH . 'includes/class-uca-ics-calendar-view.php')) {
    require_once UCA_ICS_PATH . 'includes/class-uca-ics-calendar-view.php';
}

add_action( 'plugins_loaded', function () {
    // Frontend + shortcode
    ( new UCA_ICS_Calendar() )->register();
    // Admin settings
    if ( is_admin() ) {
        ( new UCA_ICS_Admin() )->register();
    }
    // Optional Calendar View module
    if ( class_exists( 'UCA_ICS_Calendar_View' ) ) {
        ( new UCA_ICS_Calendar_View() )->register();
    }
} );

// Assets
add_action( 'wp_enqueue_scripts', function () {
    // Register and enqueue base stylesheet early so inline vars can attach before output
    wp_register_style( 'uca-ics-frontend', UCA_ICS_URL . 'assets/css/frontend.css', [], UCA_ICS_VER );
    wp_enqueue_style( 'uca-ics-frontend' );
    // Attach inline CSS variables from saved settings (site-wide)
    if ( function_exists( 'uca_ics_style_inline_css' ) ) {
        $opts   = get_option( UCA_ICS_OPT, [] );
        $inline = uca_ics_style_inline_css( $opts );
        if ( $inline ) {
            wp_add_inline_style( 'uca-ics-frontend', $inline );
        }
    }
}, 20 );

// Optional: set up WP-Cron auto-refresh using the chosen cache interval.
register_activation_hook( __FILE__, function () {
    $opts = get_option( UCA_ICS_OPT, [] );
    $interval = isset( $opts['cache_minutes'] ) ? max( 5, (int) $opts['cache_minutes'] ) : 360;
    if ( ! wp_next_scheduled( UCA_ICS_CRON_HOOK ) ) {
        wp_schedule_event( time() + 60, 'hourly', UCA_ICS_CRON_HOOK ); // use hourly tick; fetch uses cache_minutes gate
    }
} );

register_deactivation_hook( __FILE__, function () {
    $ts = wp_next_scheduled( UCA_ICS_CRON_HOOK );
    if ( $ts ) wp_unschedule_event( $ts, UCA_ICS_CRON_HOOK );
    delete_transient( UCA_ICS_TRANSIENT );
} );

// Cron handler: refresh cache if stale
add_action( UCA_ICS_CRON_HOOK, function () {
    $calendar = new UCA_ICS_Calendar();
    $calendar->maybe_refresh_cache();
} );
