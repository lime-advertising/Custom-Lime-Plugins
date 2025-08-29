<?php
namespace LimeAds\ETSM\Publisher;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Admin {
    public static function register_menus(): void {
        add_menu_page(
            __( 'Template Sync', 'etsm-publisher' ),
            __( 'Template Sync', 'etsm-publisher' ),
            'manage_template_sync',
            'etsm-publisher',
            [ self::class, 'render_registry_page' ],
            'dashicons-migrate',
            58
        );

        add_submenu_page(
            'etsm-publisher',
            __( 'Deploy', 'etsm-publisher' ),
            __( 'Deploy', 'etsm-publisher' ),
            'manage_template_sync',
            'etsm-publisher-deploy',
            [ self::class, 'render_deploy_page' ]
        );

        add_submenu_page(
            'etsm-publisher',
            __( 'Consumers', 'etsm-publisher' ),
            __( 'Consumers', 'etsm-publisher' ),
            'manage_template_sync',
            'etsm-publisher-consumers',
            [ self::class, 'render_consumers_page' ]
        );
    }

    public static function render_registry_page(): void {
        if ( ! current_user_can( 'manage_template_sync' ) ) { wp_die( __( 'Insufficient permissions', 'etsm-publisher' ) ); }
        echo '<div class="wrap"><h1>Templates Registry</h1><p>React UI placeholder. List, filter, select templates.</p></div>';
    }

    public static function render_deploy_page(): void {
        if ( ! current_user_can( 'manage_template_sync' ) ) { wp_die( __( 'Insufficient permissions', 'etsm-publisher' ) ); }
        echo '<div class="wrap"><h1>Deploy Wizard</h1><p>React UI placeholder. Select version → targets → options.</p></div>';
    }

    public static function render_consumers_page(): void {
        if ( ! current_user_can( 'manage_template_sync' ) ) { wp_die( __( 'Insufficient permissions', 'etsm-publisher' ) ); }
        echo '<div class="wrap"><h1>Consumers</h1><p>React UI placeholder. Site registration, tokens, health.</p></div>';
    }
}

