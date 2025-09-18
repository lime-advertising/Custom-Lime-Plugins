<?php

namespace LimeRM\Agent\Snapshot;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

class ArchiveBuilder
{
    public function build(string $zipPath, string $dbDumpPath, string $uploadsPath): int
    {
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Unable to create snapshot archive.');
        }

        if (! $zip->addFile($dbDumpPath, 'database.sql')) {
            throw new \RuntimeException('Unable to add database dump to archive.');
        }

        if (is_dir($uploadsPath)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($uploadsPath, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterator as $file) {
                if ($file->isDir()) {
                    continue;
                }

                $filePath = $file->getRealPath();
                $relativePath = ltrim(str_replace($uploadsPath, '', $filePath), '/\\');
                $zip->addFile($filePath, 'uploads/' . $relativePath);
            }
        }

        $zip->close();

        return filesize($zipPath) ?: 0;
    }
}

