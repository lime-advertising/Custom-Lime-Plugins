<?php
namespace LimeAds\ETSM\Consumer;

use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class REST_Controller {
    const NAMESPACE = 'etsm/v1';

    public function register_routes(): void {
        register_rest_route( self::NAMESPACE, '/webhook/deploy', [
            [
                'methods'  => WP_REST_Server::CREATABLE,
                'callback' => [ $this, 'webhook_deploy' ],
                'permission_callback' => [ $this, 'auth_publisher' ],
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/register', [
            [
                'methods'  => WP_REST_Server::CREATABLE,
                'callback' => [ $this, 'register_site' ],
                'permission_callback' => '__return_true',
            ],
        ] );
    }

    public function auth_publisher( $request ): bool {
        return Auth::verify_publisher_request( $request );
    }

    public function webhook_deploy( \WP_REST_Request $request ) {
        $params = $request->get_json_params();
        $artifact_url = isset( $params['artifact_url'] ) ? esc_url_raw( $params['artifact_url'] ) : '';
        $dry_run = ! empty( $params['dry_run'] );

        if ( ! $artifact_url ) {
            return new \WP_Error( 'etsm_bad_request', 'artifact_url is required', [ 'status' => 400 ] );
        }

        // Fetch artifact from Publisher.
        $response = wp_remote_get( $artifact_url, [ 'timeout' => 20 ] );
        if ( is_wp_error( $response ) ) {
            return new \WP_Error( 'etsm_fetch_failed', $response->get_error_message(), [ 'status' => 502 ] );
        }
        $body = wp_remote_retrieve_body( $response );
        $artifact = json_decode( $body, true );

        if ( $dry_run ) {
            $diff = Sync::calculate_diff( $artifact );
            return rest_ensure_response( [ 'dry_run' => true, 'diff' => $diff ] );
        }

        $result = Sync::apply_artifact( $artifact );
        return rest_ensure_response( $result );
    }

    public function register_site( \WP_REST_Request $request ) {
        // Enrollment handshake placeholder: save Publisher URL and token provided by admin UI flow.
        $params = $request->get_json_params();
        $publisher_url = isset( $params['publisher_url'] ) ? esc_url_raw( $params['publisher_url'] ) : '';
        $token = isset( $params['token'] ) ? sanitize_text_field( $params['token'] ) : '';
        if ( ! $publisher_url || ! $token ) {
            return new \WP_Error( 'etsm_bad_request', 'publisher_url and token are required', [ 'status' => 400 ] );
        }
        update_option( 'etsm_publisher_url', $publisher_url );
        update_option( 'etsm_site_token', $token );
        return rest_ensure_response( [ 'status' => 'ok' ] );
    }
}

