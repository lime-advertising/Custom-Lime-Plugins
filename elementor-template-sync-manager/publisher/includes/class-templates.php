<?php
namespace LimeAds\ETSM\Publisher;

use LimeAds\ETSM\Shared\JSON as JSONUtil;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Templates {
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

