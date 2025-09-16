<?php

namespace LimeRM\Agent\REST\Actions;

use LimeRM\Agent\Security\RequestValidator;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Handles snapshot requests from the controller.
 */
class SnapshotEndpoint
{
    private const NAMESPACE = 'lrma/v1';
    private const ROUTE = '/snapshot';

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
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'handle'],
                'permission_callback' => [$this, 'authorize'],
            ]
        );
    }

    public function handle(WP_REST_Request $request)
    {
        $mode = $request->get_param('mode') ?: 'full';
        $blogId = (int) $request->get_param('blog_id');

        if (\is_multisite() && $blogId > 0) {
            if (! \current_user_can('manage_network')) {
                return new WP_Error('lrm_snapshot_capability', \__('Insufficient capability.', 'lime-remote-agent'), ['status' => 403]);
            }
        } elseif (! \current_user_can('manage_options')) {
            return new WP_Error('lrm_snapshot_capability', \__('Insufficient capability.', 'lime-remote-agent'), ['status' => 403]);
        }

        // Snapshot orchestration will be implemented soon.
        return [
            'status'        => 'accepted',
            'mode'          => $mode,
            'blog_id'       => $blogId ?: null,
            'message'       => \__('Snapshot request accepted. Processing pipeline to be implemented.', 'lime-remote-agent'),
        ];
    }

    public function authorize(WP_REST_Request $request)
    {
        $result = $this->validator->authorize($request);

        if ($result instanceof WP_Error) {
            return $result;
        }

        return true;
    }
}

