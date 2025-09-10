<?php

namespace WCSE;

if (!defined('ABSPATH')) exit;

require_once WCSE_DIR . 'includes/Admin_Page.php';
require_once WCSE_DIR . 'includes/Rest_Controller.php';
require_once WCSE_DIR . 'includes/Helpers.php';

final class Plugin
{
    private static $instance = null;

    public static function instance(): self
    {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    public function init(): void
    {
        // Admin page and assets
        add_action('admin_menu', [Admin_Page::class, 'register_menu']);
        add_action('admin_enqueue_scripts', [Admin_Page::class, 'enqueue_assets']);

        // REST
        add_action('rest_api_init', [Rest_Controller::class, 'register_routes']);
    }
}
