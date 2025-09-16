<?php

namespace LimeRM\Controller\Repository;

use LimeRM\Controller\Model\Site;

/**
 * Handles persistence for managed sites.
 */
class SiteRepository
{
    public function all(): array
    {
        global $wpdb;

        $table = $wpdb->lrm_sites;

        $rows = $wpdb->get_results(
            "SELECT * FROM {$table} ORDER BY created_at DESC",
            ARRAY_A
        );

        if (! $rows) {
            return [];
        }

        return array_map([$this, 'hydrate'], $rows);
    }

    public function create(array $data): Site
    {
        global $wpdb;

        $now = \current_time('mysql');

        $defaults = [
            'name'          => '',
            'base_url'      => '',
            'site_type'     => 'unknown',
            'shared_secret' => '',
            'status'        => 'unknown',
            'last_seen'     => null,
            'info'          => null,
            'created_at'    => $now,
            'updated_at'    => $now,
        ];

        $payload = array_merge($defaults, $data);

        if (isset($payload['info']) && is_array($payload['info'])) {
            $payload['info'] = \wp_json_encode($payload['info']);
        }

        $formats = ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'];

        $wpdb->insert($wpdb->lrm_sites, $payload, $formats);

        $id = (int) $wpdb->insert_id;

        return $this->find($id);
    }

    public function find(int $id): ?Site
    {
        global $wpdb;

        $table = $wpdb->lrm_sites;
        $query = $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id);
        $row = $wpdb->get_row($query, ARRAY_A);

        if (! $row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function update(int $id, array $data): ?Site
    {
        global $wpdb;

        if (isset($data['info']) && is_array($data['info'])) {
            $data['info'] = \wp_json_encode($data['info']);
        }

        $data['updated_at'] = \current_time('mysql');

        $wpdb->update(
            $wpdb->lrm_sites,
            $data,
            ['id' => $id]
        );

        return $this->find($id);
    }

    private function hydrate(array $row): Site
    {
        $info = [];

        if (! empty($row['info'])) {
            $decoded = json_decode($row['info'], true);

            if (is_array($decoded)) {
                $info = $decoded;
            }
        }

        return new Site(
            isset($row['id']) ? (int) $row['id'] : null,
            $row['name'] ?? '',
            $row['base_url'] ?? '',
            $row['site_type'] ?? 'unknown',
            $row['shared_secret'] ?? '',
            $row['status'] ?? 'unknown',
            $row['last_seen'] ?? null,
            $info,
            $row['created_at'] ?? '',
            $row['updated_at'] ?? ''
        );
    }
}
