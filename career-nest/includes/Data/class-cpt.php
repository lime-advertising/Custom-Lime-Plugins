<?php

namespace CareerNest\Data;

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

class CPT
{
    public static function register(): void
    {
        self::register_job_listing();
        self::register_employer();
        self::register_applicant();
        self::register_job_application();
    }

    private static function register_job_listing(): void
    {
        $labels = [
            'name'               => __('Jobs', 'careernest'),
            'singular_name'      => __('Job', 'careernest'),
            'add_new'            => __('Add New', 'careernest'),
            'add_new_item'       => __('Add New Job', 'careernest'),
            'edit_item'          => __('Edit Job', 'careernest'),
            'new_item'           => __('New Job', 'careernest'),
            'view_item'          => __('View Job', 'careernest'),
            'search_items'       => __('Search Jobs', 'careernest'),
            'not_found'          => __('No jobs found.', 'careernest'),
            'not_found_in_trash' => __('No jobs found in Trash.', 'careernest'),
            'all_items'          => __('All Jobs', 'careernest'),
            'menu_name'          => __('Jobs', 'careernest'),
        ];

        register_post_type('job_listing', [
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => false,
            'show_in_rest'       => true,
            'has_archive'        => true,
            'rewrite'            => ['slug' => 'jobs', 'with_front' => false],
            'supports'           => ['title'],
            'menu_position'      => 26,
            'menu_icon'          => 'dashicons-portfolio',
        ]);
    }

    private static function register_employer(): void
    {
        $labels = [
            'name'               => __('Employers', 'careernest'),
            'singular_name'      => __('Employer', 'careernest'),
            'add_new_item'       => __('Add New Employer', 'careernest'),
            'edit_item'          => __('Edit Employer', 'careernest'),
            'new_item'           => __('New Employer', 'careernest'),
            'view_item'          => __('View Employer', 'careernest'),
            'search_items'       => __('Search Employers', 'careernest'),
            'all_items'          => __('All Employers', 'careernest'),
            'menu_name'          => __('Employers', 'careernest'),
            // Featured image labels customized to "Company Logo" for this CPT.
            'featured_image'        => __('Company Logo', 'careernest'),
            'set_featured_image'    => __('Set company logo', 'careernest'),
            'remove_featured_image' => __('Remove company logo', 'careernest'),
            'use_featured_image'    => __('Use as company logo', 'careernest'),
        ];

        register_post_type('employer', [
            'labels'        => $labels,
            'public'        => true,
            'show_ui'       => true,
            'show_in_rest'  => true,
            'supports'      => ['title', 'thumbnail'],
            'has_archive'   => false,
            'rewrite'       => ['slug' => 'employers', 'with_front' => false],
            'menu_position' => 27,
            'show_in_menu'  => false,
            'menu_icon'     => 'dashicons-store',
        ]);
    }

    private static function register_applicant(): void
    {
        $labels = [
            'name'               => __('Applicants', 'careernest'),
            'singular_name'      => __('Applicant', 'careernest'),
            'add_new_item'       => __('Add New Applicant', 'careernest'),
            'edit_item'          => __('Edit Applicant', 'careernest'),
            'new_item'           => __('New Applicant', 'careernest'),
            'view_item'          => __('View Applicant', 'careernest'),
            'search_items'       => __('Search Applicants', 'careernest'),
            'all_items'          => __('All Applicants', 'careernest'),
            'menu_name'          => __('Applicants', 'careernest'),
            // Featured image labels customized to "Photo" for Applicant.
            'featured_image'        => __('Photo', 'careernest'),
            'set_featured_image'    => __('Set photo', 'careernest'),
            'remove_featured_image' => __('Remove photo', 'careernest'),
            'use_featured_image'    => __('Use as photo', 'careernest'),
        ];

        register_post_type('applicant', [
            'labels'        => $labels,
            'public'        => true,
            'publicly_queryable' => true,
            'show_ui'       => true,
            'show_in_rest'  => false,
            'supports'      => ['title', 'editor', 'thumbnail'],
            'has_archive'   => false,
            'rewrite'       => ['slug' => 'applicants', 'with_front' => false],
            'menu_position' => 28,
            'show_in_menu'  => false,
            'menu_icon'     => 'dashicons-id-alt',
        ]);
    }

    private static function register_job_application(): void
    {
        $labels = [
            'name'               => __('Applications', 'careernest'),
            'singular_name'      => __('Application', 'careernest'),
            'add_new_item'       => __('Add New Application', 'careernest'),
            'edit_item'          => __('Edit Application', 'careernest'),
            'new_item'           => __('New Application', 'careernest'),
            'view_item'          => __('View Application', 'careernest'),
            'search_items'       => __('Search Applications', 'careernest'),
            'all_items'          => __('All Applications', 'careernest'),
            'menu_name'          => __('Applications', 'careernest'),
        ];

        register_post_type('job_application', [
            'labels'        => $labels,
            'public'        => false,
            'publicly_queryable' => false,
            'show_ui'       => true,
            'show_in_rest'  => false,
            'supports'      => ['title'],
            'has_archive'   => false,
            'rewrite'       => false,
            'menu_position' => 29,
            'show_in_menu'  => false,
            'menu_icon'     => 'dashicons-clipboard',
        ]);
    }
}
