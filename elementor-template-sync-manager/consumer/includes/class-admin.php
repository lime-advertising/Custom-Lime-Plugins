<?php
namespace LimeAds\ETSM\Consumer;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Admin {
    public static function register_menus(): void {
        add_menu_page(
            __( 'Template Sync', 'etsm-consumer' ),
            __( 'Template Sync', 'etsm-consumer' ),
            'manage_template_sync',
            'etsm-consumer',
            [ self::class, 'render_dashboard' ],
            'dashicons-migrate',
            59
        );

        add_submenu_page(
            'etsm-consumer',
            __( 'Enroll', 'etsm-consumer' ),
            __( 'Enroll', 'etsm-consumer' ),
            'manage_template_sync',
            'etsm-consumer-enroll',
            [ self::class, 'render_enroll' ]
        );
    }

    public static function render_dashboard(): void {
        if ( ! current_user_can( 'manage_template_sync' ) ) { wp_die( __( 'Insufficient permissions', 'etsm-consumer' ) ); }
        echo '<div class="wrap"><h1>Template Sync</h1><p>React UI placeholder. Pending updates, history, health.</p></div>';
    }

    public static function render_enroll(): void {
        if ( ! current_user_can( 'manage_template_sync' ) ) { wp_die( __( 'Insufficient permissions', 'etsm-consumer' ) ); }
        $publisher_url = esc_attr( get_option( 'etsm_publisher_url', '' ) );
        $token = esc_attr( get_option( 'etsm_site_token', '' ) );
        echo '<div class="wrap"><h1>Enroll with Publisher</h1>';
        echo '<p>Placeholder form. Save Publisher URL and token.</p>';
        echo '<form method="post">';
        wp_nonce_field( 'etsm_enroll', 'etsm_enroll_nonce' );
        echo '<table class="form-table"><tr><th><label>Publisher URL</label></th><td><input type="url" name="etsm_publisher_url" value="' . $publisher_url . '" class="regular-text"/></td></tr>';
        echo '<tr><th><label>Site Token</label></th><td><input type="text" name="etsm_site_token" value="' . $token . '" class="regular-text"/></td></tr></table>';
        submit_button( 'Save' );
        echo '</form></div>';

        if ( isset( $_POST['etsm_enroll_nonce'] ) && wp_verify_nonce( $_POST['etsm_enroll_nonce'], 'etsm_enroll' ) ) {
            update_option( 'etsm_publisher_url', esc_url_raw( $_POST['etsm_publisher_url'] ?? '' ) );
            update_option( 'etsm_site_token', sanitize_text_field( $_POST['etsm_site_token'] ?? '' ) );
            echo '<div class="notice notice-success"><p>Saved.</p></div>';
        }
    }
}

