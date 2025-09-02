<?php

/**
 * Plugin Name: Elementor Template Sync Manager — Consumer
 * Description: Consumer-side plugin to receive and apply Elementor template updates from Publisher.
 * Version: 0.1.0
 * Author: Lime Advertising
 * Requires at least: 6.2
 * Requires PHP: 8.1
 * Text Domain: etsm-consumer
 */

if (! defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/consumer/elementor-template-sync-consumer.php';

// Ensure activation/deactivation run when this root plugin is toggled.
register_activation_hook( __FILE__, [ '\\LimeAds\\ETSM\\Consumer\\Plugin', 'activate' ] );
register_deactivation_hook( __FILE__, [ '\\LimeAds\\ETSM\\Consumer\\Plugin', 'deactivate' ] );
