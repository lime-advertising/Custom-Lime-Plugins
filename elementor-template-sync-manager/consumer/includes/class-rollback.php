<?php
namespace LimeAds\ETSM\Consumer;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Rollback {
    public static function snapshot_before_apply( string $global_id, int $post_id, array $artifact ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'etsm_history';
        $snapshot = [
            'post' => get_post( $post_id ),
            'meta' => get_post_meta( $post_id ),
        ];
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
        foreach ( $meta as $k => $values ) {
            delete_post_meta( $post_id, $k );
            foreach ( (array) $values as $v ) {
                add_post_meta( $post_id, $k, maybe_unserialize( $v ) );
            }
        }
        return [ 'ok' => true, 'post_id' => $post_id ];
    }
}

