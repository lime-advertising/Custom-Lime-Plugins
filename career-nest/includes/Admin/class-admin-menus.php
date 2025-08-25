<?php

namespace CareerNest\Admin;

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

class Admin_Menus
{
    public function hooks(): void
    {
        add_action('admin_menu', [$this, 'register_menus']);
        add_filter('parent_file', [$this, 'highlight_parent']);
        add_filter('submenu_file', [$this, 'highlight_submenu'], 10, 2);
    }

    public function register_menus(): void
    {
        // Top-level menu
        add_menu_page(
            __('CareerNest', 'careernest'),
            __('CareerNest', 'careernest'),
            'manage_careernest',
            'careernest',
            [$this, 'render_welcome'],
            'dashicons-filter',
            25
        );

        // Section: Jobs (heading)
        add_submenu_page('careernest', __('Jobs', 'careernest'), __('Jobs', 'careernest'), 'manage_careernest', 'careernest-section-jobs', '__return_null');
        // Jobs items
        add_submenu_page('careernest', __('All Jobs', 'careernest'), __('All Jobs', 'careernest'), 'manage_careernest', 'edit.php?post_type=job_listing');
        add_submenu_page('careernest', __('Add New Job', 'careernest'), __('Add New Job', 'careernest'), 'manage_careernest', 'post-new.php?post_type=job_listing');
        add_submenu_page('careernest', __('Job Categories', 'careernest'), __('Job Categories', 'careernest'), 'manage_careernest', 'edit-tags.php?taxonomy=job_category&post_type=job_listing');
        add_submenu_page('careernest', __('Job Types', 'careernest'), __('Job Types', 'careernest'), 'manage_careernest', 'edit-tags.php?taxonomy=job_type&post_type=job_listing');
        add_submenu_page('careernest', __('Applications', 'careernest'), __('Applications', 'careernest'), 'manage_careernest', 'edit.php?post_type=job_application');

        // Section: Employers (heading)
        add_submenu_page('careernest', __('Employers', 'careernest'), __('Employers', 'careernest'), 'manage_careernest', 'careernest-section-employers', '__return_null');
        add_submenu_page('careernest', __('All Employers', 'careernest'), __('All Employers', 'careernest'), 'manage_careernest', 'edit.php?post_type=employer');

        // Section: Applicants (heading)
        add_submenu_page('careernest', __('Applicants', 'careernest'), __('Applicants', 'careernest'), 'manage_careernest', 'careernest-section-applicants', '__return_null');
        add_submenu_page('careernest', __('All Applicants', 'careernest'), __('All Applicants', 'careernest'), 'manage_careernest', 'edit.php?post_type=applicant');

        // Section: Settings (heading)
        add_submenu_page('careernest', __('Settings', 'careernest'), __('Settings', 'careernest'), 'manage_settings', 'careernest-section-settings', '__return_null');
        add_submenu_page('careernest', __('Settings', 'careernest'), __('Settings', 'careernest'), 'manage_settings', 'careernest-settings', [$this, 'render_settings_placeholder']);
    }

    public function render_welcome(): void
    {
        if (! current_user_can('manage_careernest')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'careernest'));
        }
        echo '<div class="wrap careernest-dashboard">';
        echo '<h1>' . esc_html__('CareerNest Overview', 'careernest') . '</h1>';

        $cards = [
            [
                'title' => __('Jobs', 'careernest'),
                'type'  => 'job_listing',
                'icon'  => 'dashicons-portfolio',
                'manage_link' => admin_url('edit.php?post_type=job_listing'),
                'add_link'    => admin_url('post-new.php?post_type=job_listing'),
            ],
            [
                'title' => __('Employers', 'careernest'),
                'type'  => 'employer',
                'icon'  => 'dashicons-store',
                'manage_link' => admin_url('edit.php?post_type=employer'),
                'add_link'    => admin_url('post-new.php?post_type=employer'),
            ],
            [
                'title' => __('Applicants', 'careernest'),
                'type'  => 'applicant',
                'icon'  => 'dashicons-id-alt',
                'manage_link' => admin_url('edit.php?post_type=applicant'),
                'add_link'    => admin_url('post-new.php?post_type=applicant'),
            ],
            [
                'title' => __('Applications', 'careernest'),
                'type'  => 'job_application',
                'icon'  => 'dashicons-clipboard',
                'manage_link' => admin_url('edit.php?post_type=job_application'),
                'add_link'    => admin_url('post-new.php?post_type=job_application'),
            ],
        ];

        echo '<div class="cn-cards">';
        foreach ($cards as $card) {
            $count = $this->get_post_type_count($card['type']);
            echo '<div class="cn-card">';
            echo '<span class="dashicons ' . esc_attr($card['icon']) . '"></span>';
            echo '<div class="cn-card-meta">';
            echo '<h2>' . esc_html($card['title']) . '</h2>';
            echo '<p class="cn-count"><strong>' . esc_html(number_format_i18n($count)) . '</strong></p>';
            echo '<p class="cn-actions">';
            echo '<a class="button button-primary" href="' . esc_url($card['manage_link']) . '">' . esc_html__('Manage', 'careernest') . '</a> ';
            echo '<a class="button" href="' . esc_url($card['add_link']) . '">' . esc_html__('Add New', 'careernest') . '</a>';
            echo '</p>';
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
        echo '</div>';
    }

    private function get_post_type_count(string $post_type): int
    {
        $counts = wp_count_posts($post_type);
        if (! $counts) {
            return 0;
        }
        $sum = 0;
        foreach (['publish', 'private', 'pending', 'draft', 'future'] as $st) {
            if (isset($counts->{$st})) {
                $sum += (int) $counts->{$st};
            }
        }
        return $sum;
    }

    public function render_settings_placeholder(): void
    {
        if (! current_user_can('manage_settings')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'careernest'));
        }
        echo '<div class="wrap"><h1>' . esc_html__('CareerNest Settings', 'careernest') . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields('careernest_options_group');
        do_settings_sections('careernest_settings');
        submit_button();
        echo '</form></div>';
    }

    /**
     * Ensure the CareerNest top-level menu stays highlighted on CPT screens.
     */
    public function highlight_parent(string $parent_file): string
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (! $screen) {
            return $parent_file;
        }
        $cpts = ['job_listing', 'employer', 'applicant', 'job_application'];
        $taxes = ['job_category', 'job_type'];

        if (! empty($screen->post_type) && in_array($screen->post_type, $cpts, true)) {
            return 'careernest';
        }
        if ($screen->base === 'edit-tags' || $screen->base === 'term') {
            $tax = isset($_GET['taxonomy']) ? sanitize_key((string) $_GET['taxonomy']) : '';
            if (in_array($tax, $taxes, true)) {
                return 'careernest';
            }
        }
        return $parent_file;
    }

    /**
     * Ensure the correct CareerNest submenu item is highlighted.
     */
    public function highlight_submenu(?string $submenu_file, string $parent_file): ?string
    {
        if ($parent_file !== 'careernest') {
            return $submenu_file;
        }
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (! $screen) {
            return $submenu_file;
        }

        // Map post type screens
        $pt = $screen->post_type ?? '';
        if ($pt === 'job_listing') {
            // Highlight Add New when creating, else All Jobs
            if (in_array($screen->base, ['post-new'], true)) {
                return 'post-new.php?post_type=job_listing';
            }
            return 'edit.php?post_type=job_listing';
        }
        if ($pt === 'employer') {
            if (in_array($screen->base, ['post-new'], true)) {
                return 'post-new.php?post_type=employer';
            }
            return 'edit.php?post_type=employer';
        }
        if ($pt === 'applicant') {
            if (in_array($screen->base, ['post-new'], true)) {
                return 'post-new.php?post_type=applicant';
            }
            return 'edit.php?post_type=applicant';
        }
        if ($pt === 'job_application') {
            return 'edit.php?post_type=job_application';
        }

        // Map taxonomy screens under Jobs
        if ($screen->base === 'edit-tags' || $screen->base === 'term') {
            $tax = isset($_GET['taxonomy']) ? sanitize_key((string) $_GET['taxonomy']) : '';
            if ($tax === 'job_category') {
                return 'edit-tags.php?taxonomy=job_category&post_type=job_listing';
            }
            if ($tax === 'job_type') {
                return 'edit-tags.php?taxonomy=job_type&post_type=job_listing';
            }
        }

        return $submenu_file;
    }
}
