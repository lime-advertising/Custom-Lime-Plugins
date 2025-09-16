<?php
/**
 * Plugin Name: Lime Remote Manager Controller
 * Description: Centralized dashboard and orchestration for Lime-managed WordPress sites.
 * Version: 0.1.0
 * Author: Lime Advertising Inc.
 */

if (! defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/src/Autoloader.php';

LimeRM\Controller\Autoloader::init();

\register_activation_hook(__FILE__, [LimeRM\Controller\Plugin::class, 'activate']);

$bootstrap = new LimeRM\Controller\Bootstrap();
$bootstrap->init();

