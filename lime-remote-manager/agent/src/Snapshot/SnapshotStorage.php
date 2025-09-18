<?php

namespace LimeRM\Agent\Snapshot;

class SnapshotStorage
{
    public function ensureBaseDirectory(int $blogId): string
    {
        $dir = $this->getBaseDirectory($blogId);

        if (! is_dir($dir)) {
            \wp_mkdir_p($dir);
        }

        return $dir;
    }

    public function createSnapshotDirectory(int $blogId): string
    {
        $base = $this->ensureBaseDirectory($blogId);
        $timestamp = gmdate('Ymd-His');
        $path = \trailingslashit($base) . $timestamp;

        if (! \wp_mkdir_p($path)) {
            throw new \RuntimeException('Unable to create snapshot directory.');
        }

        return $path;
    }

    public function getZipPath(string $snapshotDir): string
    {
        return \trailingslashit($snapshotDir) . 'snapshot.zip';
    }

    private function getBaseDirectory(int $blogId): string
    {
        $uploads = \wp_upload_dir();
        $base = \trailingslashit($uploads['basedir']) . 'lrm-snapshots';

        if ($blogId > 0) {
            $base .= '/' . $blogId;
        } else {
            $base .= '/primary';
        }

        return $base;
    }
}
