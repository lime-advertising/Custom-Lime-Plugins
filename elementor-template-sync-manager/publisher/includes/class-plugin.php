<?php
namespace LimeAds\ETSM\Publisher;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Plugin {
    private static ?Plugin $instance = null;

    public static function instance(): Plugin {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init', [ $this, 'init' ] );
        add_action( 'rest_api_init', [ $this, 'register_rest' ] );
        add_action( 'admin_menu', [ Admin::class, 'register_menus' ] );
    }

    public static function activate(): void {
        DB::create_tables();
        // Ensure capability exists for admins.
        $role = get_role( 'administrator' );
        if ( $role && ! $role->has_cap( 'manage_template_sync' ) ) {
            $role->add_cap( 'manage_template_sync' );
        }
    }

    public static function deactivate(): void {
        // No-op for now. Consider clearing scheduled events / queues.
    }

    public function init(): void {
        // Placeholders for custom post statuses, etc., if ever needed.
    }

    public function register_rest(): void {
        ( new REST_Controller() )->register_routes();
    }
}

