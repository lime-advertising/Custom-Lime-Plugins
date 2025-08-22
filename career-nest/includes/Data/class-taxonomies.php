<?php
namespace CareerNest\Data;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Taxonomies {
    public static function register(): void {
        self::register_job_category();
        self::register_job_type();
    }

    private static function register_job_category(): void {
        $labels = [
            'name'              => __( 'Job Categories', 'careernest' ),
            'singular_name'     => __( 'Job Category', 'careernest' ),
            'search_items'      => __( 'Search Job Categories', 'careernest' ),
            'all_items'         => __( 'All Job Categories', 'careernest' ),
            'edit_item'         => __( 'Edit Job Category', 'careernest' ),
            'update_item'       => __( 'Update Job Category', 'careernest' ),
            'add_new_item'      => __( 'Add New Job Category', 'careernest' ),
            'new_item_name'     => __( 'New Job Category Name', 'careernest' ),
            'menu_name'         => __( 'Job Categories', 'careernest' ),
        ];

        register_taxonomy( 'job_category', [ 'job_listing' ], [
            'labels'            => $labels,
            'hierarchical'      => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => [ 'slug' => 'job-category' ],
            'show_in_rest'      => true,
        ] );
    }

    private static function register_job_type(): void {
        $labels = [
            'name'                       => __( 'Job Types', 'careernest' ),
            'singular_name'              => __( 'Job Type', 'careernest' ),
            'search_items'               => __( 'Search Job Types', 'careernest' ),
            'popular_items'              => __( 'Popular Job Types', 'careernest' ),
            'all_items'                  => __( 'All Job Types', 'careernest' ),
            'edit_item'                  => __( 'Edit Job Type', 'careernest' ),
            'update_item'                => __( 'Update Job Type', 'careernest' ),
            'add_new_item'               => __( 'Add New Job Type', 'careernest' ),
            'new_item_name'              => __( 'New Job Type Name', 'careernest' ),
            'separate_items_with_commas' => __( 'Separate job types with commas', 'careernest' ),
            'add_or_remove_items'        => __( 'Add or remove job types', 'careernest' ),
            'choose_from_most_used'      => __( 'Choose from the most used job types', 'careernest' ),
            'menu_name'                  => __( 'Job Types', 'careernest' ),
        ];

        register_taxonomy( 'job_type', [ 'job_listing' ], [
            'labels'            => $labels,
            'hierarchical'      => false,
            'show_ui'           => true,
            'show_admin_column' => true,
            'update_count_callback' => '_update_post_term_count',
            'query_var'         => true,
            'rewrite'           => [ 'slug' => 'job-type' ],
            'show_in_rest'      => true,
        ] );
    }
}

