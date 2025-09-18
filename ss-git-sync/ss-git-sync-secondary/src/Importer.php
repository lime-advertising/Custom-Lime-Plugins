<?php

namespace SSGSS;

use RuntimeException;
use SSGSS\Support\Git;
use SSGSS\Support\Logger;
use SSGSS\Support;

if (!defined('ABSPATH')) {
    exit;
}

class Importer {
    private Git $git;
    private array $settings;

    public function __construct() {
        $this->settings = Plugin::getSettings();
        $this->git = new Git($this->settings['exports']);
    }

    public function pullAndImportAll(): void {
        if (empty($this->settings['repo'])) {
            throw new RuntimeException('Repository URL not configured.');
        }

        $this->git->ensureRepo($this->settings['repo'], $this->settings['branch']);

        $before = $this->git->currentCommit();
        $this->git->pull($this->settings['branch']);
        $after = $this->git->currentCommit();

        $sameCommit = ($before !== '' && $before === $after);
        $importedAny = false;

        foreach ($this->settings['projects'] as $slug => $filename) {
            $path = $this->projectPath($filename);
            if (!file_exists($path)) {
                Logger::log('import', 'Missing file for project ' . $slug . ' at ' . $path, 1);
                continue;
            }

            $existingId = $this->settings['project_ids'][$slug] ?? null;
            if (!$sameCommit || $this->fileChangedSinceLastImport($filename, $after) || !$existingId) {
                if ($this->importProject($slug, $path, $existingId)) {
                    $this->markFileImported($filename, $after);
                    $importedAny = true;
                }
            }
        }

        if (!$importedAny && $sameCommit) {
            Logger::log('import', 'No changes detected after pull.');
        }
    }

    private function importProject(string $slug, string $path, ?int $existingId = null): bool {
        $slug = sanitize_title($slug);
        if ($slug === '') {
            return false;
        }

        if ($this->smartSliderImport($slug, $path, $existingId)) {
            Logger::log('import', sprintf('Imported %s from %s', $slug, $path));

            $settings = Plugin::getSettings();
            $resolved = $this->resolveProjectId($slug);
            if ($resolved) {
                $settings['project_ids'][$slug] = $resolved;
                Plugin::saveSettings($settings);
                $this->settings = $settings;
            }

            return true;
        }

        $this->fallbackImport($slug);
        return false;
    }

    private function smartSliderImport(string $slug, string $path, ?int $existingId = null): bool {
        if (!class_exists('Nextend\\SmartSlider3\\Application\\ApplicationSmartSlider3') || !class_exists('Nextend\\SmartSlider3\\BackupSlider\\ImportSlider')) {
            return false;
        }

        try {
            $application = \Nextend\SmartSlider3\Application\ApplicationSmartSlider3::getInstance();
            $adminContext = $application->getApplicationTypeAdmin();
            if (!$adminContext) {
                return false;
            }

            if ($existingId) {
                $this->deleteSlider($existingId, $adminContext);
            }

            $import = new \Nextend\SmartSlider3\BackupSlider\ImportSlider($adminContext);
            $result = $import->import($path, 0, 'clone', 1);
            if ($result === false) {
                return false;
            }

            $targetId = (int) $result;
            if (!$targetId) {
                $targetId = $this->lookupSliderId($slug, $adminContext) ?? 0;
            }

            if ($targetId) {
                $this->updateAlias($targetId, $slug, $adminContext);
                $settings = Plugin::getSettings();
                $settings['project_ids'][$slug] = $targetId;
                Plugin::saveSettings($settings);
                $this->settings = $settings;
            }

            if ($targetId && class_exists('Nextend\\SmartSlider3\\PublicApi\\Project')) {
                try {
                    \Nextend\SmartSlider3\PublicApi\Project::clearCache($targetId);
                } catch (\Throwable $cacheError) {
                    Logger::log('import', 'Cache clear failed for project ' . $targetId . ': ' . $cacheError->getMessage(), 1);
                }
            }

            return true;
        } catch (\Throwable $e) {
            Logger::log('import', 'Smart Slider import failed: ' . $e->getMessage(), 1);
            return false;
        }
    }

    private function deleteSlider(int $sliderId, $adminContext): void {
        if (!class_exists('Nextend\\SmartSlider3\\Application\\Model\\ModelSliders')) {
            return;
        }

        try {
            $model = new \Nextend\SmartSlider3\Application\Model\ModelSliders($adminContext);
            $model->deletePermanently($sliderId);
        } catch (\Throwable $cleanupError) {
            Logger::log('import', 'Failed to delete existing slider ' . $sliderId . ': ' . $cleanupError->getMessage(), 1);
        }
    }

    private function updateAlias(int $sliderId, string $slug, $adminContext): void {
        if (!class_exists('Nextend\\SmartSlider3\\Application\\Model\\ModelSliders')) {
            return;
        }

        try {
            $model = new \Nextend\SmartSlider3\Application\Model\ModelSliders($adminContext);
            $model->updateAlias($sliderId, $slug);
        } catch (\Throwable $aliasError) {
            Logger::log('import', 'Failed to update alias for ' . $slug . ': ' . $aliasError->getMessage(), 1);
        }
    }

    private function fileChangedSinceLastImport(string $filename, string $commit): bool {
        $state = get_option('ssgss_file_state', []);
        if (!is_array($state)) {
            $state = [];
        }

        return ($state[$filename] ?? '') !== $commit;
    }

    private function markFileImported(string $filename, string $commit): void {
        $state = get_option('ssgss_file_state', []);
        if (!is_array($state)) {
            $state = [];
        }

        $state[$filename] = $commit;
        update_option('ssgss_file_state', $state, false);
    }

    private function projectPath(string $filename): string {
        return trailingslashit($this->settings['exports']) . ltrim($filename, '/');
    }

    private function resolveProjectId(string $slug): ?int {
        if (!class_exists('Nextend\\SmartSlider3\\Application\\ApplicationSmartSlider3') || !class_exists('Nextend\\SmartSlider3\\Application\\Model\\ModelSliders')) {
            return null;
        }

        try {
            $application = \Nextend\SmartSlider3\Application\ApplicationSmartSlider3::getInstance();
            $adminContext = $application->getApplicationTypeAdmin();
            if (!$adminContext) {
                return null;
            }

            $model = new \Nextend\SmartSlider3\Application\Model\ModelSliders($adminContext);
            $row = $model->getByAlias($slug);
            if (is_array($row) && isset($row['id'])) {
                return (int) $row['id'];
            }
        } catch (\Throwable $e) {
            Logger::log('import', 'Failed to resolve id for ' . $slug . ': ' . $e->getMessage(), 1);
        }

        return null;
    }

    private function lookupSliderId(string $slug, $adminContext): ?int {
        if (!class_exists('Nextend\\SmartSlider3\\Application\\Model\\ModelSliders')) {
            return null;
        }

        try {
            $model = new \Nextend\SmartSlider3\Application\Model\ModelSliders($adminContext);
            $row = $model->getByAlias($slug);
            if (is_array($row) && isset($row['id'])) {
                return (int) $row['id'];
            }
        } catch (\Throwable $e) {
            Logger::log('import', 'Lookup slider id failed for ' . $slug . ': ' . $e->getMessage(), 1);
        }

        return null;
    }

    private function fallbackImport(string $slug): void {
        throw new RuntimeException('Automatic import unavailable for project ' . $slug . '. Use manual import helper.');
    }
}
