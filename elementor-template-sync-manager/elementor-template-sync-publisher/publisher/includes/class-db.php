<?php
namespace LimeAds\ETSM\Publisher;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class DB {
    public static function create_tables(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        $templates = $wpdb->prefix . 'etsm_templates';
        $template_versions = $wpdb->prefix . 'etsm_template_versions';
        $consumers = $wpdb->prefix . 'etsm_consumers';
        $deployments = $wpdb->prefix . 'etsm_deployments';

        $sql_templates = "CREATE TABLE {$templates} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            global_template_id CHAR(36) NOT NULL,
            slug VARCHAR(191) NOT NULL,
            type VARCHAR(64) NOT NULL,
            name VARCHAR(191) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY global_template_id (global_template_id),
            UNIQUE KEY slug (slug)
        ) {$charset_collate};";

        $sql_template_versions = "CREATE TABLE {$template_versions} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            template_id BIGINT UNSIGNED NOT NULL,
            version VARCHAR(32) NOT NULL,
            artifact_json LONGTEXT NOT NULL,
            checksum CHAR(64) NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY template_id (template_id),
            KEY version (version)
        ) {$charset_collate};";

        $sql_consumers = "CREATE TABLE {$consumers} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            site_name VARCHAR(191) NOT NULL,
            site_url VARCHAR(255) NOT NULL,
            token_hash CHAR(64) NOT NULL,
            secret TEXT NULL,
            tags TEXT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'active',
            last_seen_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY site_url (site_url)
        ) {$charset_collate};";

        $sql_deployments = "CREATE TABLE {$deployments} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            template_id BIGINT UNSIGNED NOT NULL,
            version VARCHAR(32) NOT NULL,
            targets LONGTEXT NOT NULL,
            status VARCHAR(32) NOT NULL,
            results LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            completed_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY template_id (template_id),
            KEY status (status)
        ) {$charset_collate};";

        dbDelta( $sql_templates );
        dbDelta( $sql_template_versions );
        dbDelta( $sql_consumers );
        dbDelta( $sql_deployments );
    }

    public static function drop_tables(): void {
        global $wpdb;
        $templates = $wpdb->prefix . 'etsm_templates';
        $template_versions = $wpdb->prefix . 'etsm_template_versions';
        $consumers = $wpdb->prefix . 'etsm_consumers';
        $deployments = $wpdb->prefix . 'etsm_deployments';
        // Use direct queries to drop if they exist.
        foreach ( [ $deployments, $template_versions, $templates, $consumers ] as $table ) {
            $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
        }
    }
}
