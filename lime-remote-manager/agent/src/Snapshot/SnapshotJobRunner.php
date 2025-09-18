<?php

namespace LimeRM\Agent\Snapshot;

class SnapshotJobRunner
{
    /** @var SnapshotRepository */
    private $repository;

    /** @var SnapshotStorage */
    private $storage;

    /** @var DatabaseDumper */
    private $dumper;

    /** @var ArchiveBuilder */
    private $archiver;

    public function __construct(
        ?SnapshotRepository $repository = null,
        ?SnapshotStorage $storage = null,
        ?DatabaseDumper $dumper = null,
        ?ArchiveBuilder $archiver = null
    ) {
        $this->repository = $repository ?: new SnapshotRepository();
        $this->storage = $storage ?: new SnapshotStorage();
        $this->dumper = $dumper ?: new DatabaseDumper();
        $this->archiver = $archiver ?: new ArchiveBuilder();
    }

    public function run(int $snapshotId): void
    {
        $snapshot = $this->repository->find($snapshotId);

        if (! $snapshot || $snapshot['status'] !== 'queued') {
            return;
        }

        $blogId = (int) $snapshot['blog_id'];

        $this->repository->update($snapshotId, [
            'status'     => 'running',
            'started_at' => \current_time('mysql'),
        ]);

        try {
            $snapshotDir = $this->storage->createSnapshotDirectory($blogId);
            $dbDumpPath = \trailingslashit($snapshotDir) . 'database.sql';

            $this->dumper->dump($blogId, $dbDumpPath);

            $uploadsPath = $this->getUploadsPath($blogId);

            $zipPath = $this->storage->getZipPath($snapshotDir);
            $fileSize = $this->archiver->build($zipPath, $dbDumpPath, $uploadsPath);

            @unlink($dbDumpPath);

            $this->repository->update($snapshotId, [
                'status'       => 'completed',
                'completed_at' => \current_time('mysql'),
                'path'         => $zipPath,
                'file_size'    => $fileSize,
            ]);
        } catch (\Throwable $e) {
            $this->repository->update($snapshotId, [
                'status' => 'failed',
                'error'  => $e->getMessage(),
            ]);
        }
    }

    private function getUploadsPath(int $blogId): string
    {
        $switched = false;

        if (\is_multisite() && $blogId > 0) {
            \switch_to_blog($blogId);
            $switched = true;
        }

        try {
            $uploads = \wp_upload_dir();
            return $uploads['basedir'];
        } finally {
            if ($switched) {
                \restore_current_blog();
            }
        }
    }
}
