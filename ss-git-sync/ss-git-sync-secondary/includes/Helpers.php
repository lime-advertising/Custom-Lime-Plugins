<?php

namespace SSGSS\Support;

if (!defined('ABSPATH')) {
    exit;
}

function normalize_projects(array $projects): array {
    $normalized = [];
    foreach ($projects as $key => $value) {
        if (is_array($value) && isset($value['slug'], $value['file'])) {
            $slug = sanitize_title((string) $value['slug']);
            $file = sanitize_file_name((string) $value['file']);
        } else {
            $slug = sanitize_title((string) $key);
            $file = sanitize_file_name((string) $value);
        }

        if ($slug !== '' && $file !== '') {
            $normalized[$slug] = $file;
        }
    }

    return $normalized;
}

function load_settings(string $option, array $defaults): array {
    $stored = get_option($option, []);
    if (!is_array($stored)) {
        $stored = [];
    }

    $settings = array_merge($defaults, $stored);
    $settings['projects'] = normalize_projects($settings['projects'] ?? []);

    $ids = [];
    if (isset($settings['project_ids']) && is_array($settings['project_ids'])) {
        foreach ($settings['project_ids'] as $slug => $id) {
            $slug = sanitize_title((string) $slug);
            $id   = (int) $id;
            if ($slug !== '' && $id > 0) {
                $ids[$slug] = $id;
            }
        }
    }
    $settings['project_ids'] = $ids;

    if (empty($settings['exports'])) {
        $settings['exports'] = trailingslashit($defaults['exports'] ?? '');
    } else {
        $settings['exports'] = trailingslashit(wp_normalize_path($settings['exports']));
    }

    return $settings;
}

function save_settings(string $option, array $settings): void {
    $settings['projects'] = normalize_projects($settings['projects'] ?? []);

    if (isset($settings['project_ids']) && is_array($settings['project_ids'])) {
        $ids = [];
        foreach ($settings['project_ids'] as $slug => $id) {
            $slug = sanitize_title((string) $slug);
            $id   = (int) $id;
            if ($slug !== '' && $id > 0) {
                $ids[$slug] = $id;
            }
        }
        $settings['project_ids'] = $ids;
    }

    if (isset($settings['exports'])) {
        $settings['exports'] = trailingslashit(wp_normalize_path($settings['exports']));
    }

    update_option($option, $settings, false);
}
