<?php
namespace CareerNest;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Activator {

    public static function activate(): void {
        // Ensure CPTs and taxonomies are registered before flushing rewrites.
        if ( ! class_exists( '\\CareerNest\\Data\\CPT' ) ) {
            require_once CAREERNEST_DIR . 'includes/Data/class-cpt.php';
        }
        if ( ! class_exists( '\\CareerNest\\Data\\Taxonomies' ) ) {
            require_once CAREERNEST_DIR . 'includes/Data/class-taxonomies.php';
        }
        \CareerNest\Data\CPT::register();
        \CareerNest\Data\Taxonomies::register();
        // Seed default options if not present.
        $defaults = [
            'delete_on_uninstall' => false,
            'maps_api_key'        => '',
        ];
        $existing = get_option( 'careernest_options', [] );
        if ( ! is_array( $existing ) ) {
            $existing = [];
        }
        update_option( 'careernest_options', array_merge( $defaults, $existing ) );

        // Create required pages and store IDs.
        $pages = self::create_required_pages();
        update_option( 'careernest_pages', $pages );

        // Add plugin roles and capabilities.
        if ( ! class_exists( '\\CareerNest\\Data\\Roles' ) ) {
            require_once CAREERNEST_DIR . 'includes/Data/class-roles.php';
        }
        \CareerNest\Data\Roles::add_roles();

        // Flush rewrite rules at the end of activation.
        flush_rewrite_rules();
    }

    private static function create_required_pages(): array {
        $definitions = [
            // slug => [title, template, is_private]
            'jobs'               => [ 'Job Listings', 'template-jobs.php', false ],
            'employer-dashboard' => [ 'Employer Dashboard', 'template-employer-dashboard.php', false ],
            'applicant-dashboard'=> [ 'Applicant Dashboard', 'template-applicant-dashboard.php', false ],
            'register-employer'  => [ 'Employer Registration', 'template-register-employer.php', false ],
            'register-applicant' => [ 'Applicant Registration', 'template-register-applicant.php', false ],
            'apply-job'          => [ 'Apply for Job', 'template-apply-job.php', false ],
        ];

        $created = [];
        foreach ( $definitions as $slug => $def ) {
            [ $title, $template, $is_private ] = $def;
            $page_id = self::create_or_get_page( $slug, $title, $template, $is_private );
            if ( $page_id ) {
                $created[ $slug ] = absint( $page_id );
            }
        }
        return $created;
    }

    private static function create_or_get_page( string $slug, string $title, string $template_file, bool $is_private ): int {
        $existing = get_page_by_path( $slug, OBJECT, 'page' );

        $content  = sprintf( 'This page is managed by CareerNest (%s).', esc_html( $slug ) );
        $status   = $is_private ? 'private' : 'publish';

        if ( $existing && $existing instanceof \WP_Post ) {
            // Ensure correct status/meta/template.
            $update = [
                'ID'           => $existing->ID,
                'post_status'  => $status,
                'post_title'   => $title,
            ];
            wp_update_post( $update );
            update_post_meta( $existing->ID, '_careernest_hidden', '1' );
            update_post_meta( $existing->ID, '_wp_page_template', $template_file );
            return (int) $existing->ID;
        }

        $page_id = wp_insert_post( [
            'post_type'    => 'page',
            'post_name'    => $slug,
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => $status,
            'post_author'  => get_current_user_id() ?: 1,
        ] );

        if ( is_wp_error( $page_id ) ) {
            return 0;
        }

        update_post_meta( $page_id, '_careernest_hidden', '1' );
        update_post_meta( $page_id, '_wp_page_template', $template_file );

        return (int) $page_id;
    }
}
