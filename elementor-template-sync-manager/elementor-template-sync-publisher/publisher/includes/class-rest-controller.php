<?php
namespace LimeAds\ETSM\Publisher;

use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class REST_Controller {
    const NAMESPACE = 'etsm/v1';

    public function register_routes(): void {
        register_rest_route( self::NAMESPACE, '/templates', [
            [
                'methods'  => WP_REST_Server::READABLE,
                'callback' => [ $this, 'list_templates' ],
                'permission_callback' => [ $this, 'auth_consumer' ],
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/templates/(?P<id>[a-f0-9\-]{36})', [
            [
                'methods'  => WP_REST_Server::READABLE,
                'callback' => [ $this, 'get_template' ],
                'permission_callback' => [ $this, 'auth_consumer' ],
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/updates', [
            [
                'methods'  => WP_REST_Server::READABLE,
                'callback' => [ $this, 'get_updates_feed' ],
                'permission_callback' => [ $this, 'auth_consumer' ],
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/deploy', [
            [
                'methods'  => WP_REST_Server::CREATABLE,
                'callback' => [ $this, 'deploy' ],
                'permission_callback' => function() {
                    return current_user_can( 'manage_template_sync' );
                }
            ],
        ] );
    }

    public function auth_consumer( $request ): bool {
        return Auth::verify_signed_request( $request );
    }

    public function list_templates( \WP_REST_Request $request ) {
        return Templates::rest_list( $request );
    }

    public function get_template( \WP_REST_Request $request ) {
        return Templates::rest_get( $request );
    }

    public function get_updates_feed( \WP_REST_Request $request ) {
        return Templates::rest_updates( $request );
    }

    public function deploy( \WP_REST_Request $request ) {
        return Deployments::rest_deploy( $request );
    }
}

