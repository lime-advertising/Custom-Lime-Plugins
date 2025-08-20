<?php
if (!defined('ABSPATH')) exit;

class Lime_Schema
{
    private static $instance;
    private $admin;
    private $renderer;

    public static function instance(): self
    {
        if (!self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct()
    {
        // Admin
        $this->admin = new Lime_Schema_Admin();
        $this->admin->hooks();

        // Frontend renderer
        $this->renderer = new Lime_Schema_Renderer();
        $this->renderer->hooks();
    }

    // No explicit load_plugin_textdomain() call needed (WP loads translations automatically on WordPress.org since 4.6).

    public function renderer(): Lime_Schema_Renderer
    {
        return $this->renderer;
    }
}
