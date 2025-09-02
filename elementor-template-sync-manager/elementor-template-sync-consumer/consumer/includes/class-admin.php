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
        echo '<div class="wrap"><h1>Template Sync</h1>';

        // Handle actions (rollback)
        if ( isset( $_POST['etsm_consumer_nonce'] ) && wp_verify_nonce( $_POST['etsm_consumer_nonce'], 'etsm_consumer_actions' ) ) {
            if ( isset( $_POST['rollback_global_id'] ) ) {
                $gid = sanitize_text_field( (string) $_POST['rollback_global_id'] );
                $res = Rollback::rollback_to_last( $gid );
                if ( ! empty( $res['ok'] ) ) {
                    echo '<div class="notice notice-success"><p>Rollback completed for ' . esc_html( $gid ) . '.</p></div>';
                } else {
                    $err = isset( $res['error'] ) ? $res['error'] : 'Unknown error';
                    echo '<div class="notice notice-error"><p>Rollback failed: ' . esc_html( $err ) . '</p></div>';
                }
            }
        }

        global $wpdb; $map = $wpdb->prefix . 'etsm_map';
        // Seed mapping for any Elementor templates carrying ETSM meta but missing from map
        $pm = $wpdb->postmeta; $posts = $wpdb->posts;
        $missing = $wpdb->get_results(
            "SELECT p.ID as post_id, m.meta_value as gid FROM {$posts} p 
             INNER JOIN {$pm} m ON p.ID = m.post_id 
             WHERE p.post_type='elementor_library' AND m.meta_key='_etsm_global_template_id' 
             AND NOT EXISTS (SELECT 1 FROM {$map} mp WHERE mp.global_template_id = m.meta_value) LIMIT 50"
        );
        foreach ( (array) $missing as $m ) {
            $gid = (string) $m->gid; $pid = (int) $m->post_id;
            $version = (string) get_post_meta( $pid, '_etsm_version', true );
            $checksum = (string) get_post_meta( $pid, '_etsm_checksum', true );
            Sync::enqueue_apply_job( [ 'global_template_id' => $gid, 'version' => $version, 'name' => get_the_title( $pid ), 'slug' => sanitize_title( get_the_title( $pid ) ), 'type' => (string) get_post_meta( $pid, '_elementor_template_type', true ), '_elementor_data' => json_decode( (string) get_post_meta( $pid, '_elementor_data', true ), true ), 'checksum' => $checksum ], [ 'skip_media' => true ] );
            // Also upsert lightweight mapping immediately
            $wpdb->insert( $map, [ 'global_template_id' => $gid, 'post_id' => $pid, 'installed_version' => $version, 'status' => 'active', 'last_sync_at' => current_time( 'mysql' ), 'last_checksum' => $checksum ], [ '%s','%d','%s','%s','%s','%s' ] );
        }
        $rows = $wpdb->get_results( "SELECT * FROM {$map} ORDER BY last_sync_at DESC, id DESC" );

        echo '<h2>Installed Templates</h2>';
        echo '<form method="post">';
        wp_nonce_field( 'etsm_consumer_actions', 'etsm_consumer_nonce' );
        echo '<table class="widefat"><thead><tr><th>Title</th><th>Global ID</th><th>Post ID</th><th>Version</th><th>Last Sync</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
        if ( $rows ) {
            foreach ( $rows as $r ) {
                $title = get_the_title( (int) $r->post_id );
                $edit_link = get_edit_post_link( (int) $r->post_id );
                echo '<tr>';
                echo '<td>' . ( $edit_link ? '<a href="' . esc_url( $edit_link ) . '">' . esc_html( $title ?: '(no title)' ) . '</a>' : esc_html( $title ?: '(no title)' ) ) . '</td>';
                echo '<td><code>' . esc_html( $r->global_template_id ) . '</code></td>';
                echo '<td>' . intval( $r->post_id ) . '</td>';
                echo '<td>' . esc_html( $r->installed_version ?: '-' ) . '</td>';
                echo '<td>' . esc_html( $r->last_sync_at ?: '-' ) . '</td>';
                echo '<td>' . esc_html( $r->status ) . '</td>';
                echo '<td>';
                echo '<button class="button" name="rollback_global_id" value="' . esc_attr( $r->global_template_id ) . '" onclick="return confirm(\'Rollback last snapshot for this template?\')">Rollback</button>';
                echo '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="7">No templates installed yet.</td></tr>';
        }
        echo '</tbody></table>';
        echo '</form>';

        // Danger zone: setting to drop tables on deactivate
        echo '<h2>Danger Zone</h2>';
        if ( isset( $_POST['etsm_cons_danger_nonce'] ) && wp_verify_nonce( $_POST['etsm_cons_danger_nonce'], 'etsm_cons_danger' ) ) {
            $flag = ! empty( $_POST['drop_on_deactivate'] );
            update_option( 'etsm_cons_drop_on_deactivate', $flag ? 1 : 0 );
            echo '<div class="notice notice-success"><p>Setting saved.</p></div>';
        }
        $flag = (bool) get_option( 'etsm_cons_drop_on_deactivate', false );
        echo '<form method="post">';
        wp_nonce_field( 'etsm_cons_danger', 'etsm_cons_danger_nonce' );
        echo '<p><label><input type="checkbox" name="drop_on_deactivate" ' . checked( $flag, true, false ) . ' /> Drop ETSM Consumer tables and options when this plugin is deactivated</label></p>';
        submit_button( 'Save Danger Zone Setting', 'delete' );
        echo '</form>';
        echo '</div>';
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
