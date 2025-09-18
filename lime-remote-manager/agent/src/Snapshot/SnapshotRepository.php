<?php

namespace LimeRM\Agent\Snapshot;

use wpdb;

class SnapshotRepository
{
    private const TABLE = 'lrm_snapshots';

    public function maybeCreateTable(): void
    {
        global $wpdb;

        $table = $this->getTableName();
        $charsetCollate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            blog_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            mode VARCHAR(20) NOT NULL DEFAULT 'full',
            status VARCHAR(20) NOT NULL DEFAULT 'queued',
            path TEXT NULL,
            file_size BIGINT(20) UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            started_at DATETIME NULL,
            completed_at DATETIME NULL,
            error TEXT NULL,
            metadata LONGTEXT NULL,
            PRIMARY KEY  (id),
            KEY blog_status (blog_id, status)
        ) {$charsetCollate};";

        \dbDelta($sql);
    }

    public function create(array $data): int
    {
        global $wpdb;

        $now = \current_time('mysql');

        $defaults = [
            'blog_id'     => 0,
            'mode'        => 'full',
            'status'      => 'queued',
            'path'        => null,
            'file_size'   => null,
            'created_at'  => $now,
            'started_at'  => null,
            'completed_at'=> null,
            'error'       => null,
            'metadata'    => null,
        ];

        $payload = array_merge($defaults, $data);

        if (isset($payload['metadata']) && is_array($payload['metadata'])) {
            $payload['metadata'] = \wp_json_encode($payload['metadata']);
        }

        $formats = [
            '%d',
            '%s',
            '%s',
            $payload['path'] !== null ? '%s' : null,
            $payload['file_size'] !== null ? '%d' : null,
            '%s',
            $payload['started_at'] !== null ? '%s' : null,
            $payload['completed_at'] !== null ? '%s' : null,
            $payload['error'] !== null ? '%s' : null,
            $payload['metadata'] !== null ? '%s' : null,
        ];

        $wpdb->insert($this->getTableName(), $payload, $formats);

        return (int) $wpdb->insert_id;
    }

    public function update(int $id, array $data): void
    {
        global $wpdb;

        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $data['metadata'] = \wp_json_encode($data['metadata']);
        }

        $format = [];

        foreach ($data as $value) {
            if (is_int($value)) {
                $format[] = '%d';
            } elseif (is_float($value)) {
                $format[] = '%f';
            } else {
                $format[] = $value === null ? null : '%s';
            }
        }

        $wpdb->update($this->getTableName(), $data, ['id' => $id], $format, ['%d']);
    }

    public function find(int $id): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . $this->getTableName() . ' WHERE id = %d', $id),
            ARRAY_A
        );

        if (! $row) {
            return null;
        }

        if (! empty($row['metadata'])) {
            $decoded = json_decode($row['metadata'], true);
            if (is_array($decoded)) {
                $row['metadata'] = $decoded;
            }
        }

        return $row;
    }

    private function getTableName(): string
    {
        global $wpdb;

        return $wpdb->prefix . self::TABLE;
    }
}
