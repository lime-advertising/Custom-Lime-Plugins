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

    public static function apply_artifact( array $artifact, array $options = [] ): array {
        if ( ! JSONUtil::validate_artifact( $artifact ) ) {
            return [ 'ok' => false, 'error' => 'Artifact validation failed' ];
        }
        $global_id = (string) $artifact['global_template_id'];
        $name = sanitize_text_field( (string) $artifact['name'] );
        $type = sanitize_text_field( (string) $artifact['type'] );
        $version = sanitize_text_field( (string) $artifact['version'] );
        $checksum = sanitize_text_field( (string) $artifact['checksum'] );
        $elementor_data = $artifact['_elementor_data'];

        // Media remap (default copy strategy) unless skipped.
        $skip_media = ! empty( $options['skip_media'] );
        if ( ! $skip_media ) {
            $elementor_data = Media::remap_media( $elementor_data );
        }

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

        // Apply display conditions if provided and policy allows.
        self::maybe_apply_display_conditions( $post_id, $type, $artifact );

        self::upsert_mapping( $global_id, $post_id, $version, $checksum );

        // Nudge Elementor to regenerate CSS/data so frontend reflects updates immediately.
        if ( function_exists( 'wp_update_post' ) ) {
            // Touch the post to update modified date without changing content.
            wp_update_post( [ 'ID' => $post_id ] );
        }
        // Regenerate per-post CSS if Elementor is available.
        if ( class_exists( '\\Elementor\\Core\\Files\\CSS\\Post' ) ) {
            try {
                $css = \Elementor\Core\Files\CSS\Post::create( $post_id );
                if ( $css ) { $css->update(); }
            } catch ( \Throwable $e ) {}
        }
        if ( class_exists( '\\Elementor\\Plugin' ) ) {
            try { \Elementor\Plugin::instance()->files_manager->clear_cache(); } catch ( \Throwable $e ) {}
        }

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
        // Fallback: direct lookup in postmeta to avoid heavy WP_Query/meta query building
        $pm = $wpdb->postmeta;
        $posts = $wpdb->posts;
        $post_id = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT p.ID FROM {$posts} p INNER JOIN {$pm} m ON p.ID = m.post_id WHERE m.meta_key = %s AND m.meta_value = %s AND p.post_type = 'elementor_library' LIMIT 1",
            '_etsm_global_template_id', $global_id
        ) );
        if ( $post_id ) {
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

    /**
     * Apply Elementor display conditions from artifact.
     * Supports modes: replace (default), merge, skip.
     * Requires Elementor Pro Theme Builder on the Consumer site.
     */
    private static function maybe_apply_display_conditions( int $post_id, string $type, array $artifact ): void {
        if ( empty( $artifact['display_conditions'] ) ) {
            return;
        }

        // Default policy: do NOT apply unless explicitly enabled.
        $apply_conditions = array_key_exists( 'apply_conditions', $artifact ) ? (bool) $artifact['apply_conditions'] : false;
        if ( ! $apply_conditions ) {
            return;
        }

        $mode = isset( $artifact['conditions_mode'] ) ? strtolower( (string) $artifact['conditions_mode'] ) : 'skip';
        if ( ! in_array( $mode, [ 'replace', 'merge', 'skip' ], true ) ) {
            $mode = 'replace';
        }
        if ( 'skip' === $mode ) {
            return;
        }

        // Require Elementor Pro Theme Builder for conditions.
        if ( ! class_exists( '\\ElementorPro\\Modules\\ThemeBuilder\\Module' ) ) {
            return;
        }

        $new_conditions = $artifact['display_conditions'];
        if ( is_string( $new_conditions ) ) {
            $decoded = json_decode( $new_conditions, true );
            if ( json_last_error() === JSON_ERROR_NONE ) {
                $new_conditions = $decoded;
            }
        }
        if ( ! is_array( $new_conditions ) ) {
            return;
        }

        // Ensure the taxonomy assignment for template type exists if taxonomy is registered.
        if ( taxonomy_exists( 'elementor_library_type' ) ) {
            // Best-effort: associate the type term (e.g., header, footer, section, single, archive, popup).
            wp_set_object_terms( $post_id, $type, 'elementor_library_type', false );
        }

        $final_conditions = $new_conditions;
        if ( 'merge' === $mode ) {
            $existing = get_post_meta( $post_id, '_elementor_conditions', true );
            if ( is_string( $existing ) ) {
                $decoded = json_decode( $existing, true );
                if ( json_last_error() === JSON_ERROR_NONE ) {
                    $existing = $decoded;
                }
            }
            if ( is_array( $existing ) ) {
                // Shallow merge: append unique condition groups by JSON identity.
                $seen = [];
                foreach ( (array) $existing as $g ) {
                    $seen[ md5( wp_json_encode( $g ) ) ] = true;
                }
                $final = $existing;
                foreach ( (array) $new_conditions as $g ) {
                    $k = md5( wp_json_encode( $g ) );
                    if ( ! isset( $seen[ $k ] ) ) {
                        $final[] = $g;
                        $seen[ $k ] = true;
                    }
                }
                $final_conditions = $final;
            }
        }

        // Store as array; WordPress will serialize. Elementor reads `_elementor_conditions`.
        update_post_meta( $post_id, '_elementor_conditions', $final_conditions );
    }

    // Queue helpers
    public static function enqueue_apply_job( array $artifact, array $options = [] ) : ?int {
        global $wpdb;
        $table = $wpdb->prefix . 'etsm_jobs';
        $payload = [ 'artifact' => $artifact, 'options' => $options ];
        $ok = $wpdb->insert( $table, [
            'job_type' => 'apply_artifact',
            'payload_json' => wp_json_encode( $payload ),
            'status' => 'queued',
            'attempts' => 0,
            'last_error' => null,
            'created_at' => current_time( 'mysql' ),
            'updated_at' => null,
        ], [ '%s','%s','%s','%d','%s','%s','%s' ] );
        if ( ! $ok ) { return null; }
        return (int) $wpdb->insert_id;
    }

    public static function run_job( int $job_id ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'etsm_jobs';
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $job_id ) );
        if ( ! $row || $row->status !== 'queued' ) { return; }
        $payload = json_decode( (string) $row->payload_json, true );
        $artifact = $payload['artifact'] ?? null;
        $options = $payload['options'] ?? [];
        if ( ! is_array( $artifact ) ) {
            $wpdb->update( $table, [ 'status' => 'failed', 'last_error' => 'invalid payload', 'updated_at' => current_time( 'mysql' ) ], [ 'id' => $job_id ], [ '%s','%s','%s' ], [ '%d' ] );
            return;
        }
        $res = self::apply_artifact( $artifact, $options );
        $status = ! empty( $res['ok'] ) ? 'done' : 'failed';
        $wpdb->update( $table, [ 'status' => $status, 'updated_at' => current_time( 'mysql' ), 'last_error' => ! empty( $res['ok'] ) ? null : ( $res['error'] ?? 'error' ) ], [ 'id' => $job_id ], [ '%s','%s','%s' ], [ '%d' ] );
    }
}
