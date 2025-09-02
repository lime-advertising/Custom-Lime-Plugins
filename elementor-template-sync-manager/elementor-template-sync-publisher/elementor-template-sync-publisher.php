<?php
/**
 * Plugin Name: Elementor Template Sync Manager — Publisher
 * Description: Publisher-side plugin to manage and deploy Elementor templates to Consumer sites.
 * Version: 0.1.0
 * Author: Lime Advertising
 * Requires at least: 6.2
 * Requires PHP: 8.1
 * Text Domain: etsm-publisher
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/publisher/elementor-template-sync-publisher.php';

// Ensure activation/deactivation run when this root plugin is toggled.
register_activation_hook( __FILE__, [ '\\LimeAds\\ETSM\\Publisher\\Plugin', 'activate' ] );
register_deactivation_hook( __FILE__, [ '\\LimeAds\\ETSM\\Publisher\\Plugin', 'deactivate' ] );
