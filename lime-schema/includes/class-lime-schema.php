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
        // i18n
        add_action('init', [$this, 'load_textdomain']);

        // Admin
        $this->admin = new Lime_Schema_Admin();
        $this->admin->hooks();

        // Frontend renderer
        $this->renderer = new Lime_Schema_Renderer();
        $this->renderer->hooks();
    }

    public function load_textdomain(): void
    {
        load_plugin_textdomain(LIME_SCHEMA_TEXT_DOMAIN, false, dirname(plugin_basename(LIME_SCHEMA_FILE)) . '/languages');
    }

    public function renderer(): Lime_Schema_Renderer
    {
        return $this->renderer;
    }
}
