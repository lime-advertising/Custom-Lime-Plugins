<?php
namespace LimeAds\ETSM\Consumer;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class DB {
    public static function create_tables(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $map = $wpdb->prefix . 'etsm_map';
        $history = $wpdb->prefix . 'etsm_history';
        $jobs = $wpdb->prefix . 'etsm_jobs';

        $sql_map = "CREATE TABLE {$map} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            global_template_id CHAR(36) NOT NULL,
            post_id BIGINT UNSIGNED NOT NULL,
            installed_version VARCHAR(32) NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'active',
            last_sync_at DATETIME NULL,
            last_checksum CHAR(64) NULL,
            PRIMARY KEY (id),
            UNIQUE KEY global_template_id (global_template_id),
            KEY post_id (post_id)
        ) {$charset};";

        $sql_history = "CREATE TABLE {$history} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            global_template_id CHAR(36) NOT NULL,
            version VARCHAR(32) NOT NULL,
            snapshot_json LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY global_template_id (global_template_id)
        ) {$charset};";

        $sql_jobs = "CREATE TABLE {$jobs} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            job_type VARCHAR(64) NOT NULL,
            payload_json LONGTEXT NOT NULL,
            status VARCHAR(32) NOT NULL,
            attempts INT NOT NULL DEFAULT 0,
            last_error TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY status (status)
        ) {$charset};";

        dbDelta( $sql_map );
        dbDelta( $sql_history );
        dbDelta( $sql_jobs );
    }
}

