<?php
namespace CareerNest;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Plugin {

    public function run(): void {
        add_action( 'pre_get_posts', [ $this, 'hide_managed_pages_in_admin' ] );
        // Register CPTs and taxonomies on init.
        add_action( 'init', function () {
            \CareerNest\Data\CPT::register();
            \CareerNest\Data\Taxonomies::register();
        }, 5 );

        // Force classic editor for CareerNest CPTs (disable block editor).
        add_filter( 'use_block_editor_for_post_type', [ $this, 'disable_block_editor' ], 10, 2 );

        // Dequeue block editor assets on edit screens for our CPTs.
        add_action( 'admin_enqueue_scripts', [ $this, 'strip_block_assets_on_cpt_edit' ], 100 );

        // Hide admin bar for applicants on the frontend.
        add_filter( 'show_admin_bar', [ $this, 'maybe_hide_admin_bar' ], 10, 1 );

        // Frontend dashboard access redirects.
        add_action( 'template_redirect', [ $this, 'dashboard_access_redirects' ] );
    }

    /**
     * Hide CareerNest-managed pages from the Pages list for non-admin users.
     */
    public function hide_managed_pages_in_admin( \WP_Query $query ): void {
        if ( ! is_admin() || ! $query->is_main_query() ) {
            return;
        }

        // Allow site administrators to see everything.
        if ( current_user_can( 'manage_options' ) ) {
            return;
        }

        $post_type = $query->get( 'post_type' );
        if ( 'page' !== $post_type && ! ( is_array( $post_type ) && in_array( 'page', $post_type, true ) ) ) {
            return;
        }

        $meta_query   = (array) $query->get( 'meta_query', [] );
        $meta_query[] = [
            'relation' => 'OR',
            [
                'key'     => '_careernest_hidden',
                'compare' => 'NOT EXISTS',
            ],
            [
                'key'     => '_careernest_hidden',
                'value'   => '1',
                'compare' => '!=',
            ],
        ];

        $query->set( 'meta_query', $meta_query );
    }

    /**
     * Disable Gutenberg editor for plugin CPTs.
     */
    public function disable_block_editor( bool $use_block_editor, string $post_type ): bool {
        $cpts = [ 'job_listing', 'employer', 'applicant', 'job_application' ];
        if ( in_array( $post_type, $cpts, true ) ) {
            return false;
        }
        return $use_block_editor;
    }

    /**
     * Remove Gutenberg-related assets on classic edit screens for plugin CPTs.
     * Keeps classic editor assets intact.
     */
    public function strip_block_assets_on_cpt_edit(): void {
        if ( ! is_admin() ) {
            return;
        }
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || ! in_array( $screen->base, [ 'post', 'post-new' ], true ) ) {
            return;
        }
        $cpts = [ 'job_listing', 'employer', 'applicant', 'job_application' ];
        if ( empty( $screen->post_type ) || ! in_array( $screen->post_type, $cpts, true ) ) {
            return;
        }

        // Block editor styles that are safe to remove on classic editor screens.
        $block_styles = [
            'wp-block-library',
            'wp-block-library-theme',
            'wp-edit-blocks',
            'wp-block-editor',
            'wp-block-directory',
            'wp-nux',
            'wp-components',
            'wp-format-library',
        ];
        foreach ( $block_styles as $handle ) {
            wp_dequeue_style( $handle );
            wp_deregister_style( $handle );
        }

        // Block editor scripts to remove; do NOT remove 'wp-editor' (TinyMCE) or core admin deps.
        $block_scripts = [
            'wp-edit-post',
            'wp-format-library',
            'wp-blocks',
            'wp-block-editor',
            'wp-block-directory',
            'wp-nux',
        ];
        foreach ( $block_scripts as $handle ) {
            wp_dequeue_script( $handle );
            wp_deregister_script( $handle );
        }
    }

    /**
     * Hide the admin bar for users with the Applicant role on the frontend.
     */
    public function maybe_hide_admin_bar( bool $show ): bool {
        if ( is_admin() ) {
            return $show;
        }
        if ( ! is_user_logged_in() ) {
            return $show;
        }
        $user = wp_get_current_user();
        $roles = (array) $user->roles;
        if ( in_array( 'applicant', $roles, true ) || in_array( 'employer_team', $roles, true ) ) {
            return false;
        }
        return $show;
    }

    /**
     * Redirect users to their proper dashboard when visiting the wrong one.
     * Also enforce login requirement on dashboards.
     */
    public function dashboard_access_redirects(): void {
        $pages = get_option( 'careernest_pages', [] );
        $applicant_id = isset( $pages['applicant-dashboard'] ) ? (int) $pages['applicant-dashboard'] : 0;
        $employer_id  = isset( $pages['employer-dashboard'] ) ? (int) $pages['employer-dashboard'] : 0;

        if ( ( $applicant_id && is_page( $applicant_id ) ) || ( $employer_id && is_page( $employer_id ) ) ) {
            if ( ! is_user_logged_in() ) {
                wp_safe_redirect( wp_login_url( get_permalink() ) );
                exit;
            }
            $user  = wp_get_current_user();
            $roles = (array) $user->roles;

            // If viewing employer dashboard but user is applicant → redirect to applicant dashboard.
            if ( $employer_id && is_page( $employer_id ) && in_array( 'applicant', $roles, true ) ) {
                if ( $applicant_id ) {
                    wp_safe_redirect( get_permalink( $applicant_id ) );
                    exit;
                }
            }

            // If viewing applicant dashboard but user is employer team → redirect to employer dashboard.
            if ( $applicant_id && is_page( $applicant_id ) && in_array( 'employer_team', $roles, true ) ) {
                if ( $employer_id ) {
                    wp_safe_redirect( get_permalink( $employer_id ) );
                    exit;
                }
            }
            // Admins/AES admins can access both.
        }
    }
}
