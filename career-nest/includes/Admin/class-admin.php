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
        ( new Admin_Columns() )->hooks();
        ( new Users() )->hooks();
        ( new Settings() )->hooks();
        add_action( 'wp_ajax_careernest_get_employer_team', [ $this, 'ajax_employer_team' ] );
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
        if ( $screen && in_array( $screen->id, [ 'job_listing', 'employer', 'applicant', 'job_application' ], true ) ) {
            if ( in_array( $screen->id, [ 'applicant', 'employer', 'job_listing' ], true ) ) {
                wp_enqueue_media();
                wp_enqueue_script( 'jquery-ui-sortable' );
                // Conditionally load Google Maps Places for Applicant/Employer location autocomplete
                $opts = get_option( 'careernest_options', [] );
                $key  = is_array( $opts ) && ! empty( $opts['maps_api_key'] ) ? trim( (string) $opts['maps_api_key'] ) : '';
                if ( $key ) {
                    $maps_url = add_query_arg(
                        [
                            'key'       => rawurlencode( $key ),
                            'libraries' => 'places',
                        ],
                        'https://maps.googleapis.com/maps/api/js'
                    );
                    wp_enqueue_script( 'careernest-google-maps', $maps_url, [], null, true );
                    wp_enqueue_script( 'careernest-maps', CAREERNEST_URL . 'assets/js/maps.js', [ 'careernest-google-maps' ], CAREERNEST_VERSION, true );
                }
            }
            if ( 'job_application' === $screen->id ) {
                wp_enqueue_media();
            }
            wp_enqueue_script( 'careernest-admin', CAREERNEST_URL . 'assets/js/admin.js', [ 'jquery' ], CAREERNEST_VERSION, true );
            wp_localize_script( 'careernest-admin', 'careernestAdmin', [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'careernest_admin' ),
                'i18n'    => [
                    'selectUser' => __( 'Select user…', 'careernest' ),
                    'selectEmployerFirst' => __( 'Select employer first (or no team assigned)', 'careernest' ),
                ],
            ] );
        }
    }

    public function ajax_employer_team(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'forbidden' ], 403 );
        }
        $nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'careernest_admin' ) ) {
            wp_send_json_error( [ 'message' => 'bad_nonce' ], 400 );
        }
        $employer_id = isset( $_REQUEST['employer_id'] ) ? absint( $_REQUEST['employer_id'] ) : 0;
        if ( ! $employer_id ) {
            wp_send_json_success( [ 'items' => [] ] );
        }
        $users = get_users( [
            'role'       => 'employer_team',
            'meta_key'   => '_employer_id',
            'meta_value' => $employer_id,
            'fields'     => [ 'ID', 'display_name', 'user_email' ],
            'number'     => 200,
        ] );
        $items = [];
        foreach ( $users as $u ) {
            $items[] = [ 'id' => (int) $u->ID, 'label' => $u->display_name . ' (' . $u->user_email . ')' ];
        }
        wp_send_json_success( [ 'items' => $items ] );
    }
}
