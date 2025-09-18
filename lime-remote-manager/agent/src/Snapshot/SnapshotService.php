<?php

namespace LimeRM\Agent\Snapshot;

class SnapshotService
{
    /** @var SnapshotRepository */
    private $repository;

    /** @var SnapshotStorage */
    private $storage;

    public function __construct(?SnapshotRepository $repository = null, ?SnapshotStorage $storage = null)
    {
        $this->repository = $repository ?: new SnapshotRepository();
        $this->storage = $storage ?: new SnapshotStorage();
    }

    public function queueSnapshot(array $args): array
    {
        $blogId = isset($args['blog_id']) ? (int) $args['blog_id'] : 0;
        $mode = $args['mode'] ?? 'full';

        $snapshotId = $this->repository->create([
            'blog_id'  => $blogId,
            'mode'     => $mode,
            'metadata' => [
                'created_by' => 'remote_controller',
            ],
        ]);

        $switched = false;

        if (\is_multisite() && $blogId > 0) {
            \switch_to_blog($blogId);
            $switched = true;
        }

        try {
            $this->storage->ensureBaseDirectory($blogId);
        } finally {
            if ($switched) {
                \restore_current_blog();
            }
        }

        \wp_schedule_single_event(time() + 5, 'lime_remote_agent_run_snapshot', [$snapshotId]);

        return [
            'snapshot_id' => $snapshotId,
            'status'      => 'queued',
        ];
    }
}
