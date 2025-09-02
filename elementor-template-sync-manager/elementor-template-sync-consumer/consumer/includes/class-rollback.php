<?php
namespace LimeAds\ETSM\Consumer;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Rollback {
    public static function snapshot_before_apply( string $global_id, int $post_id, array $artifact ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'etsm_history';
        $post = get_post( $post_id, ARRAY_A );
        // Capture only relevant meta to reduce memory footprint
        $keys = [ '_elementor_data', '_elementor_edit_mode', '_elementor_template_type', '_etsm_global_template_id', '_etsm_version', '_etsm_checksum' ];
        $meta = [];
        foreach ( $keys as $k ) {
            $meta[ $k ] = get_post_meta( $post_id, $k, true );
        }
        $snapshot = [ 'post' => $post, 'meta' => $meta ];
        $wpdb->insert( $table, [
            'global_template_id' => $global_id,
            'version' => (string) ( $artifact['version'] ?? '' ),
            'snapshot_json' => wp_json_encode( $snapshot ),
            'created_at' => current_time( 'mysql' )
        ], [ '%s','%s','%s','%s' ] );
    }

    public static function rollback_to_last( string $global_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'etsm_history';
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE global_template_id = %s ORDER BY id DESC LIMIT 1", $global_id ) );
        if ( ! $row ) {
            return [ 'ok' => false, 'error' => 'No snapshot' ];
        }
        $snapshot = json_decode( (string) $row->snapshot_json, true );
        $post = $snapshot['post'] ?? null;
        $meta = $snapshot['meta'] ?? [];
        if ( ! $post ) return [ 'ok' => false, 'error' => 'Invalid snapshot' ];

        $post_id = wp_insert_post( [
            'ID' => (int) $post['ID'],
            'post_title' => (string) $post['post_title'],
            'post_status' => (string) $post['post_status'],
            'post_type' => (string) $post['post_type'],
            'post_content' => (string) $post['post_content'],
        ], true );
        if ( is_wp_error( $post_id ) ) {
            return [ 'ok' => false, 'error' => $post_id->get_error_message() ];
        }
        foreach ( $meta as $k => $v ) {
            update_post_meta( $post_id, $k, $v );
        }
        return [ 'ok' => true, 'post_id' => $post_id ];
    }
}
