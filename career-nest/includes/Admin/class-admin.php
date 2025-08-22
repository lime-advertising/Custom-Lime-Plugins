<?php
namespace CareerNest\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Admin {
    public function hooks(): void {
        ( new Admin_Menus() )->hooks();
        ( new Meta_Boxes() )->hooks();
        add_action( 'admin_init', [ $this, 'redirect_roles_from_admin' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
    }

    /**
     * Redirect low-privileged roles away from wp-admin (except profile and AJAX).
     */
    public function redirect_roles_from_admin(): void {
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            return;
        }
        if ( ! is_user_logged_in() || ! is_admin() ) {
            return;
        }

        $user = wp_get_current_user();
        $roles = (array) $user->roles;

        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        // Allow profile pages.
        $is_profile = $screen && in_array( $screen->id, [ 'profile', 'profile-network' ], true );
        if ( $is_profile ) {
            return;
        }

        $pages = get_option( 'careernest_pages', [] );
        if ( in_array( 'applicant', $roles, true ) ) {
            $target = ! empty( $pages['applicant-dashboard'] ) ? get_permalink( (int) $pages['applicant-dashboard'] ) : home_url( '/' );
            wp_safe_redirect( $target );
            exit;
        }
        if ( in_array( 'employer_team', $roles, true ) ) {
            $target = ! empty( $pages['employer-dashboard'] ) ? get_permalink( (int) $pages['employer-dashboard'] ) : home_url( '/' );
            wp_safe_redirect( $target );
            exit;
        }
    }

    public function enqueue_admin_assets(): void {
        // Load minimal CSS to style submenu section headings and dashboard.
        wp_enqueue_style( 'careernest-admin', CAREERNEST_URL . 'assets/css/admin.css', [], CAREERNEST_VERSION );

        // Enqueue admin JS only on our CPT edit screens for conditional UI.
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( $screen && in_array( $screen->id, [ 'job_listing', 'employer', 'applicant' ], true ) ) {
            wp_enqueue_script( 'careernest-admin', CAREERNEST_URL . 'assets/js/admin.js', [ 'jquery' ], CAREERNEST_VERSION, true );
        }
    }
}
