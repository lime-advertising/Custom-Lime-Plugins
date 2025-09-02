<?php
namespace LimeAds\ETSM\Consumer;

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
        add_action( 'etsm_consumer_cron_pull', [ Sync::class, 'cron_pull' ] );
        add_action( 'etsm_consumer_run_job', [ Sync::class, 'run_job' ], 10, 1 );
    }

    public static function activate(): void {
        DB::create_tables();
        if ( ! wp_next_scheduled( 'etsm_consumer_cron_pull' ) ) {
            wp_schedule_event( time() + 60, 'hourly', 'etsm_consumer_cron_pull' );
        }
        // Capability for local admins to manage sync.
        $role = get_role( 'administrator' );
        if ( $role && ! $role->has_cap( 'manage_template_sync' ) ) {
            $role->add_cap( 'manage_template_sync' );
        }
    }

    public static function deactivate(): void {
        wp_clear_scheduled_hook( 'etsm_consumer_cron_pull' );
        $should_drop = (bool) get_option( 'etsm_cons_drop_on_deactivate', false );
        if ( $should_drop ) {
            DB::drop_tables();
            // Clean options as well
            delete_option( 'etsm_publisher_url' );
            delete_option( 'etsm_site_token' );
        }
    }

    public function init(): void {
        // Placeholder for anything on init.
    }

    public function register_rest(): void {
        ( new REST_Controller() )->register_routes();
    }
}
