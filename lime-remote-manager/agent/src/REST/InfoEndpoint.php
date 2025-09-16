<?php

namespace LimeRM\Agent\REST;

use LimeRM\Agent\Security\RequestValidator;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Implements the `GET /info` endpoint.
 */
class InfoEndpoint
{
    private const NAMESPACE = 'lrma/v1';
    private const ROUTE = '/info';

    /** @var RequestValidator */
    private $validator;

    public function __construct(RequestValidator $validator)
    {
        $this->validator = $validator;
    }

    public function register(): void
    {
        \register_rest_route(
            self::NAMESPACE,
            self::ROUTE,
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'handle'],
                'permission_callback' => [$this, 'authorize'],
            ]
        );
    }

    /**
     * REST callback returning minimal site information.
     */
    public function handle(WP_REST_Request $request): array
    {
        return [
            'site_type'   => \is_multisite() ? 'multisite' : 'single',
            'wp_version'  => \get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'timestamp'   => \current_time('mysql'),
        ];
    }

    /**
     * Permission callback delegating to the request validator.
     */
    public function authorize(WP_REST_Request $request)
    {
        $result = $this->validator->authorize($request);

        if ($result instanceof WP_Error) {
            return $result;
        }

        return $result === true;
    }
}
