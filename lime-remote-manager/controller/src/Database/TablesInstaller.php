<?php

namespace LimeRM\Controller\Database;

/**
 * Creates and registers controller database tables.
 */
class TablesInstaller
{
    private const SITES_TABLE = 'lrm_sites';
    private const LOGS_TABLE = 'lrm_logs';

    /**
     * Create database tables required by the controller.
     */
    public function install(): void
    {
        global $wpdb;

        $this->registerTableReferences();

        $charsetCollate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sitesTable = sprintf(
            "CREATE TABLE %1\$s (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL,
                base_url TEXT NOT NULL,
                site_type VARCHAR(20) NOT NULL DEFAULT 'single',
                shared_secret TEXT NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'unknown',
                last_seen DATETIME NULL,
                info LONGTEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY site_type (site_type),
                KEY status (status)
            ) %2\$s;",
            $this->getSitesTableName(),
            $charsetCollate
        );

        $logsTable = sprintf(
            "CREATE TABLE %1\$s (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                site_id BIGINT(20) UNSIGNED NOT NULL,
                subsite_id BIGINT(20) UNSIGNED NULL,
                user_id BIGINT(20) UNSIGNED NULL,
                action VARCHAR(191) NOT NULL,
                context LONGTEXT NULL,
                result VARCHAR(20) NOT NULL,
                created_at DATETIME NOT NULL,
                INDEX site_idx (site_id),
                INDEX action_idx (action),
                INDEX created_at_idx (created_at),
                PRIMARY KEY (id)
            ) %2\$s;",
            $this->getLogsTableName(),
            $charsetCollate
        );

        \dbDelta($sitesTable);
        \dbDelta($logsTable);
    }

    /**
     * Register custom table references on the global $wpdb instance.
     */
    public function registerTableReferences(): void
    {
        global $wpdb;

        $wpdb->lrm_sites = $this->getSitesTableName();
        $wpdb->lrm_logs = $this->getLogsTableName();
    }

    private function getSitesTableName(): string
    {
        global $wpdb;

        return $wpdb->prefix . self::SITES_TABLE;
    }

    private function getLogsTableName(): string
    {
        global $wpdb;

        return $wpdb->prefix . self::LOGS_TABLE;
    }
}
