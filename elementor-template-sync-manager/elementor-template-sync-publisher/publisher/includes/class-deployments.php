<?php
namespace LimeAds\ETSM\Publisher;

use LimeAds\ETSM\Shared\HMAC;

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

/**
 * Minimal deploy sender: push inline artifact to a Consumer webhook with HMAC headers.
 */
class DeploySender {
    public static function send_inline_to_consumer( object $consumer, array $artifact, bool $dry_run = false, array $options = [] ): array {
        $site_url = rtrim( (string) $consumer->site_url, '/' );
        $endpoint = $site_url . '/wp-json/etsm/v1/webhook/deploy';
        $secret = (string) ( $consumer->secret ?? '' );
        if ( ! $secret ) {
            return [ 'ok' => false, 'error' => 'Missing consumer secret' ];
        }

        $payload = [ 'artifact' => $artifact ];
        if ( $dry_run ) { $payload['dry_run'] = true; }
        if ( ! empty( $options ) ) { $payload['options'] = $options; }
        $body = wp_json_encode( $payload );

        $method = 'POST';
        // IMPORTANT: Use the REST route path (no /wp-json prefix) to match $request->get_route().
        $path = '/etsm/v1/webhook/deploy';
        $ts = (string) time();
        $nonce = wp_generate_uuid4();
        $sig = HMAC::sign( $method, $path, $ts, $nonce, $body, $secret );

        $headers = [
            'Content-Type' => 'application/json',
            'X-ETSM-Token' => $secret, // token==secret in MVP
            'X-ETSM-Timestamp' => $ts,
            'X-ETSM-Nonce' => $nonce,
            'X-ETSM-Signature' => $sig,
        ];

        $response = wp_remote_post( $endpoint, [ 'headers' => $headers, 'body' => $body, 'timeout' => 60 ] );
        if ( is_wp_error( $response ) ) {
            return [ 'ok' => false, 'error' => $response->get_error_message() ];
        }
        $code = wp_remote_retrieve_response_code( $response );
        $resp_body = wp_remote_retrieve_body( $response );
        $parsed = json_decode( $resp_body, true );
        if ( $code >= 200 && $code < 300 ) {
            return [ 'ok' => true, 'response' => $parsed ?: $resp_body ];
        }
        return [ 'ok' => false, 'status' => $code, 'response' => $parsed ?: $resp_body ];
    }
}
