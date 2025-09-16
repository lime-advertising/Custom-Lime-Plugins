<?php
/**
 * Plugin Name: Lime Remote Agent
 * Description: Provides secure remote management endpoints for Lime's controller.
 * Version: 0.1.0
 * Author: Lime Advertising Inc.
 */

if (! defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/src/Autoloader.php';

LimeRM\Agent\Autoloader::init();

$bootstrap = new LimeRM\Agent\Bootstrap();
$bootstrap->init();

