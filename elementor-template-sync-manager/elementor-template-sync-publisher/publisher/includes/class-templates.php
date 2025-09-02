<?php
namespace LimeAds\ETSM\Publisher;

use LimeAds\ETSM\Shared\JSON as JSONUtil;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Templates {
    /**
     * Create or update an artifact from an Elementor library post.
     */
    public static function create_or_update_artifact_from_post( int $post_id, string $version, array $extra = [] ) {
        $post = get_post( $post_id );
        if ( ! $post || 'elementor_library' !== $post->post_type ) {
            return new \WP_Error( 'etsm_bad_post', 'Invalid Elementor template post' );
        }

        $name = (string) $post->post_title;
        $type = (string) get_post_meta( $post_id, '_elementor_template_type', true );
        if ( ! $type ) { $type = 'section'; }
        $data_json = get_post_meta( $post_id, '_elementor_data', true );
        $data = is_string( $data_json ) ? json_decode( $data_json, true ) : (array) $data_json;
        if ( ! is_array( $data ) ) { $data = []; }

        $global_id = get_post_meta( $post_id, '_etsm_global_template_id', true );
        if ( ! $global_id ) { $global_id = wp_generate_uuid4(); }

        $artifact = array_merge( [
            'global_template_id' => $global_id,
            'version' => $version,
            'name' => $name,
            'slug' => sanitize_title( $name ),
            'type' => $type,
            '_elementor_data' => $data,
            'created_at' => current_time( 'mysql' ),
            'updated_at' => current_time( 'mysql' ),
        ], $extra );

        $copy = $artifact; unset( $copy['checksum'] );
        $artifact['checksum'] = hash( 'sha256', wp_json_encode( $copy ) );

        global $wpdb;
        $templates = $wpdb->prefix . 'etsm_templates';
        $versions  = $wpdb->prefix . 'etsm_template_versions';

        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$templates} WHERE global_template_id = %s", $global_id ) );
        if ( ! $row ) {
            $wpdb->insert( $templates, [
                'global_template_id' => $global_id,
                'slug' => $artifact['slug'],
                'type' => $type,
                'name' => $name,
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ], [ '%s','%s','%s','%s','%s','%s' ] );
            $template_id = (int) $wpdb->insert_id;
        } else {
            $template_id = (int) $row->id;
            $wpdb->update( $templates, [ 'updated_at' => current_time( 'mysql' ), 'name' => $name, 'type' => $type, 'slug' => $artifact['slug'] ], [ 'id' => $template_id ], [ '%s','%s','%s' ], [ '%d' ] );
        }

        $wpdb->insert( $versions, [
            'template_id' => $template_id,
            'version' => $version,
            'artifact_json' => wp_json_encode( $artifact ),
            'checksum' => $artifact['checksum'],
            'created_at' => current_time( 'mysql' ),
        ], [ '%d','%s','%s','%s','%s' ] );

        update_post_meta( $post_id, '_etsm_global_template_id', $global_id );
        update_post_meta( $post_id, '_etsm_version', $version );
        update_post_meta( $post_id, '_etsm_checksum', $artifact['checksum'] );

        return [ 'template_id' => $template_id, 'artifact' => $artifact ];
    }

    public static function get_latest_artifact( string $global_template_id ) {
        global $wpdb;
        $templates = $wpdb->prefix . 'etsm_templates';
        $versions  = $wpdb->prefix . 'etsm_template_versions';
        $tpl = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$templates} WHERE global_template_id = %s", $global_template_id ) );
        if ( ! $tpl ) { return null; }
        $ver = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$versions} WHERE template_id = %d ORDER BY id DESC LIMIT 1", $tpl->id ) );
        if ( ! $ver ) { return null; }
        return json_decode( (string) $ver->artifact_json, true );
    }

    /**
     * Build a fresh artifact from the current Elementor post linked by _etsm_global_template_id.
     * Does not persist a new version; intended for on-the-fly deploys.
     */
    public static function build_artifact_from_global( string $global_template_id, ?string $version = null, array $extra = [] ) {
        global $wpdb;
        $pm = $wpdb->postmeta; $posts = $wpdb->posts;
        $post_id = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT p.ID FROM {$posts} p INNER JOIN {$pm} m ON p.ID = m.post_id WHERE p.post_type='elementor_library' AND m.meta_key='_etsm_global_template_id' AND m.meta_value=%s LIMIT 1",
            $global_template_id
        ) );
        if ( ! $post_id ) { return null; }

        $post = get_post( $post_id );
        $name = (string) $post->post_title;
        $type = (string) get_post_meta( $post_id, '_elementor_template_type', true );
        if ( ! $type ) { $type = 'section'; }
        $data_json = get_post_meta( $post_id, '_elementor_data', true );
        $data = is_string( $data_json ) ? json_decode( $data_json, true ) : (array) $data_json;
        if ( ! is_array( $data ) ) { $data = []; }

        if ( ! $version ) {
            $version = (string) get_post_meta( $post_id, '_etsm_version', true );
            if ( ! $version ) { $version = '1.0.0'; }
        }

        $artifact = array_merge( [
            'global_template_id' => $global_template_id,
            'version' => $version,
            'name' => $name,
            'slug' => sanitize_title( $name ),
            'type' => $type,
            '_elementor_data' => $data,
            'created_at' => current_time( 'mysql' ),
            'updated_at' => current_time( 'mysql' ),
        ], $extra );

        $copy = $artifact; unset( $copy['checksum'] );
        $artifact['checksum'] = hash( 'sha256', wp_json_encode( $copy ) );
        return $artifact;
    }
    public static function rest_list( \WP_REST_Request $request ) {
        global $wpdb;
        $table = $wpdb->prefix . 'etsm_templates';
        $type  = sanitize_text_field( $request->get_param( 'type' ) );

        $where = '1=1';
        $args  = [];
        if ( $type ) {
            $where .= ' AND type = %s';
            $args[] = $type;
        }
        $rows = $wpdb->get_results( $args ? $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where}", ...$args ) : "SELECT * FROM {$table} WHERE {$where}" );
        return rest_ensure_response( $rows );
    }

    public static function rest_get( \WP_REST_Request $request ) {
        global $wpdb;
        $id = sanitize_text_field( $request['id'] );
        $templates = $wpdb->prefix . 'etsm_templates';
        $versions  = $wpdb->prefix . 'etsm_template_versions';

        $tpl = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$templates} WHERE global_template_id = %s", $id ) );
        if ( ! $tpl ) {
            return new \WP_Error( 'etsm_not_found', 'Template not found', [ 'status' => 404 ] );
        }
        $ver = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$versions} WHERE template_id = %d ORDER BY id DESC LIMIT 1", $tpl->id ) );
        if ( ! $ver ) {
            return new \WP_Error( 'etsm_no_version', 'No versions available', [ 'status' => 404 ] );
        }
        $artifact = json_decode( $ver->artifact_json, true );
        if ( ! JSONUtil::validate_artifact( $artifact ) ) {
            return new \WP_Error( 'etsm_invalid', 'Artifact validation failed', [ 'status' => 500 ] );
        }
        return rest_ensure_response( $artifact );
    }

    public static function rest_updates( \WP_REST_Request $request ) {
        // Minimal placeholder: return empty updates feed for now.
        return rest_ensure_response( [ 'updates' => [] ] );
    }
}
