<?php
namespace LimeAds\ETSM\Consumer;

use LimeAds\ETSM\Shared\JSON as JSONUtil;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Sync {
    public static function calculate_diff( array $artifact ): array {
        // Minimal diff: compare checksum with local mapping.
        $global_id = (string) ( $artifact['global_template_id'] ?? '' );
        $local = self::get_mapping( $global_id );
        $diff = [ 'will_create' => false, 'will_update' => false, 'notes' => [] ];
        if ( ! $local ) {
            $diff['will_create'] = true;
        } else {
            $old = $local->last_checksum ?? '';
            $new = (string) ( $artifact['checksum'] ?? '' );
            $diff['will_update'] = $old !== $new;
        }
        return $diff;
    }

    public static function apply_artifact( array $artifact ): array {
        if ( ! JSONUtil::validate_artifact( $artifact ) ) {
            return [ 'ok' => false, 'error' => 'Artifact validation failed' ];
        }
        $global_id = (string) $artifact['global_template_id'];
        $name = sanitize_text_field( (string) $artifact['name'] );
        $type = sanitize_text_field( (string) $artifact['type'] );
        $version = sanitize_text_field( (string) $artifact['version'] );
        $checksum = sanitize_text_field( (string) $artifact['checksum'] );
        $elementor_data = $artifact['_elementor_data'];

        // Media remap (default copy strategy)
        $elementor_data = Media::remap_media( $elementor_data );

        $mapped = self::get_mapping( $global_id );
        $post_id = $mapped ? intval( $mapped->post_id ) : 0;

        // Prepare content
        $content = ''; // Elementor stores JSON in meta; keep post content minimal.
        $postarr = [
            'post_title' => $name,
            'post_status' => 'publish',
            'post_type' => 'elementor_library',
            'post_content' => $content,
        ];
        if ( $post_id ) { $postarr['ID'] = $post_id; }

        // Snapshot before change for rollback
        if ( $post_id ) {
            Rollback::snapshot_before_apply( $global_id, $post_id, $artifact );
        }

        $result_id = wp_insert_post( $postarr, true );
        if ( is_wp_error( $result_id ) ) {
            return [ 'ok' => false, 'error' => $result_id->get_error_message() ];
        }
        $post_id = intval( $result_id );

        // Update Elementor meta
        update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $elementor_data ) ) );
        update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
        update_post_meta( $post_id, '_elementor_template_type', $type );
        update_post_meta( $post_id, '_etsm_global_template_id', $global_id );
        update_post_meta( $post_id, '_etsm_version', $version );
        update_post_meta( $post_id, '_etsm_checksum', $checksum );

        self::upsert_mapping( $global_id, $post_id, $version, $checksum );

        return [ 'ok' => true, 'post_id' => $post_id, 'version' => $version ];
    }

    public static function cron_pull(): void {
        $publisher = Auth::get_publisher_url();
        $token = Auth::get_publisher_secret();
        if ( ! $publisher || ! $token ) return;
        // Minimal pull: call updates feed and do nothing for now.
        // Extend to iterate feed and apply as per policy.
    }

    private static function get_mapping( string $global_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'etsm_map';
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE global_template_id = %s", $global_id ) );
        if ( $row ) return $row;
        // Fallback: look for existing Elementor template with meta key
        $q = new \WP_Query( [
            'post_type' => 'elementor_library',
            'post_status' => 'any',
            'meta_key' => '_etsm_global_template_id',
            'meta_value' => $global_id,
            'fields' => 'ids',
            'posts_per_page' => 1,
            'no_found_rows' => true,
        ] );
        if ( $q->have_posts() ) {
            $post_id = (int) $q->posts[0];
            // Seed mapping row for future lookups
            $version = (string) get_post_meta( $post_id, '_etsm_version', true );
            $checksum = (string) get_post_meta( $post_id, '_etsm_checksum', true );
            self::upsert_mapping( $global_id, $post_id, $version, $checksum );
            return (object) [
                'id' => 0,
                'global_template_id' => $global_id,
                'post_id' => $post_id,
                'installed_version' => $version,
                'status' => 'active',
                'last_sync_at' => current_time( 'mysql' ),
                'last_checksum' => $checksum,
            ];
        }
        return null;
    }

    private static function upsert_mapping( string $global_id, int $post_id, string $version, string $checksum ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'etsm_map';
        $exists = self::get_mapping( $global_id );
        $data = [
            'global_template_id' => $global_id,
            'post_id' => $post_id,
            'installed_version' => $version,
            'status' => 'active',
            'last_sync_at' => current_time( 'mysql' ),
            'last_checksum' => $checksum,
        ];
        $formats = [ '%s','%d','%s','%s','%s','%s' ];
        if ( $exists ) {
            $wpdb->update( $table, $data, [ 'id' => $exists->id ], $formats, [ '%d' ] );
        } else {
            $wpdb->insert( $table, $data, $formats );
        }
    }
}
