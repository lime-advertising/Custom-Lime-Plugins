<?php

namespace LimeRM\Agent\REST\Actions;

use LimeRM\Agent\Security\RequestValidator;
use LimeRM\Agent\Snapshot\SnapshotService;
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

    /** @var SnapshotService */
    private $service;

    public function __construct(RequestValidator $validator, SnapshotService $service)
    {
        $this->validator = $validator;
        $this->service = $service;
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
        $confirm = $request->get_param('confirm_token');

        if ($confirm !== 'SNAPSHOT') {
            return new WP_Error('lrm_snapshot_confirm', \__('Confirmation token missing or invalid.', 'lime-remote-agent'), ['status' => 400]);
        }

        if (\is_multisite() && $blogId > 0) {
            if ($blogId === 1) {
                return new WP_Error('lrm_snapshot_primary_blocked', \__('Primary site snapshots must be run without specifying a blog_id.', 'lime-remote-agent'), ['status' => 400]);
            }
        }

        /**
         * Allow hosting environments to short-circuit or approve snapshot requests.
         */
        $allowed = \apply_filters('lime_remote_agent_allow_snapshot', true, [
            'mode'    => $mode,
            'blog_id' => $blogId,
        ]);

        if (! $allowed) {
            return new WP_Error('lrm_snapshot_blocked', \__('Snapshot request blocked by server policy.', 'lime-remote-agent'), ['status' => 403]);
        }

        $allowedModes = ['full', 'db', 'uploads'];

        if (! in_array($mode, $allowedModes, true)) {
            return new WP_Error('lrm_snapshot_mode', \__('Snapshot mode is invalid.', 'lime-remote-agent'), ['status' => 400]);
        }

        $result = $this->service->queueSnapshot([
            'mode'    => $mode,
            'blog_id' => $blogId,
        ]);

        return [
            'status'       => 'queued',
            'snapshot_id'  => $result['snapshot_id'],
            'message'      => \__('Snapshot request accepted.', 'lime-remote-agent'),
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
