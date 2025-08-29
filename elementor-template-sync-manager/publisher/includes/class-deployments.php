<?php
namespace LimeAds\ETSM\Publisher;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Deployments {
    public static function rest_deploy( \WP_REST_Request $request ) {
        $params = $request->get_json_params();
        $template_ids = isset( $params['template_ids'] ) && is_array( $params['template_ids'] ) ? array_map( 'sanitize_text_field', $params['template_ids'] ) : [];
        $targets = $params['targets'] ?? [];
        $options = $params['options'] ?? [];

        if ( empty( $template_ids ) ) {
            return new \WP_Error( 'etsm_bad_request', 'template_ids is required', [ 'status' => 400 ] );
        }

        // Persist a deployment record (placeholder implementation).
        global $wpdb;
        $table = $wpdb->prefix . 'etsm_deployments';
        $created = current_time( 'mysql' );
        $status  = 'queued';
        $wpdb->insert( $table, [
            'template_id' => 0, // Multi-template batch; future: split per template.
            'version'     => $options['version'] ?? '',
            'targets'     => wp_json_encode( $targets ),
            'status'      => $status,
            'results'     => null,
            'created_at'  => $created,
            'completed_at'=> null,
        ], [ '%d','%s','%s','%s','%s','%s','%s' ] );

        $deployment_id = (int) $wpdb->insert_id;

        // Schedule async processing (placeholder — replace with Action Scheduler).
        if ( ! wp_next_scheduled( 'etsm_process_deployment', [ $deployment_id ] ) ) {
            wp_schedule_single_event( time() + 5, 'etsm_process_deployment', [ $deployment_id ] );
        }

        return rest_ensure_response( [ 'deployment_id' => $deployment_id, 'status' => $status ] );
    }
}

// Worker hook (placeholder).
add_action( 'etsm_process_deployment', function( int $deployment_id ) {
    // TODO: Load deployment, iterate targets, call consumer webhooks with signed payloads.
}, 10, 1 );

