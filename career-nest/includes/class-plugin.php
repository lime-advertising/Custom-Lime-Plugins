<?php

namespace CareerNest;

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

class Plugin
{

    public function run(): void
    {
        add_action('pre_get_posts', [$this, 'hide_managed_pages_in_admin']);
        // Register CPTs and taxonomies on init.
        add_action('init', function () {
            \CareerNest\Data\CPT::register();
            \CareerNest\Data\Taxonomies::register();
        }, 5);

        // Force classic editor for CareerNest CPTs (disable block editor).
        add_filter('use_block_editor_for_post_type', [$this, 'disable_block_editor'], 10, 2);

        // Dequeue block editor assets on edit screens for our CPTs.
        add_action('admin_enqueue_scripts', [$this, 'strip_block_assets_on_cpt_edit'], 100);

        // Hide admin bar for applicants on the frontend.
        add_filter('show_admin_bar', [$this, 'maybe_hide_admin_bar'], 10, 1);

        // Frontend dashboard access redirects.
        add_action('template_redirect', [$this, 'dashboard_access_redirects']);

        // Expire jobs past closing date on init (lightweight pass)
        add_action('init', [$this, 'expire_due_jobs'], 20);

        // Template routing for plugin pages and CPTs
        add_filter('template_include', [$this, 'template_loader'], 99);

        // Link guest applications to new user accounts
        add_action('user_register', [$this, 'link_guest_applications_to_user']);

        // Enqueue frontend assets for specific pages
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
    }

    public function hide_managed_pages_in_admin(\WP_Query $query): void
    {
        if (! is_admin() || ! $query->is_main_query()) {
            return;
        }
        if (current_user_can('manage_options')) {
            return;
        }
        $post_type = $query->get('post_type');
        if ('page' !== $post_type && ! (is_array($post_type) && in_array('page', $post_type, true))) {
            return;
        }
        $meta_query   = (array) $query->get('meta_query', []);
        $meta_query[] = ['relation' => 'OR', ['key' => '_careernest_hidden', 'compare' => 'NOT EXISTS'], ['key' => '_careernest_hidden', 'value' => '1', 'compare' => '!=']];
        $query->set('meta_query', $meta_query);
    }

    public function disable_block_editor(bool $use_block_editor, string $post_type): bool
    {
        $cpts = ['job_listing', 'employer', 'applicant', 'job_application'];
        if (in_array($post_type, $cpts, true)) {
            return false;
        }
        return $use_block_editor;
    }

    public function strip_block_assets_on_cpt_edit(): void
    {
        if (! is_admin()) {
            return;
        }
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (! $screen || ! in_array($screen->base, ['post', 'post-new'], true)) {
            return;
        }
        $cpts = ['job_listing', 'employer', 'applicant', 'job_application'];
        if (empty($screen->post_type) || ! in_array($screen->post_type, $cpts, true)) {
            return;
        }
        $block_styles = ['wp-block-library', 'wp-block-library-theme', 'wp-edit-blocks', 'wp-block-editor', 'wp-block-directory', 'wp-nux', 'wp-components', 'wp-format-library'];
        foreach ($block_styles as $handle) {
            wp_dequeue_style($handle);
            wp_deregister_style($handle);
        }
        $block_scripts = ['wp-edit-post', 'wp-format-library', 'wp-blocks', 'wp-block-editor', 'wp-block-directory', 'wp-nux'];
        foreach ($block_scripts as $handle) {
            wp_dequeue_script($handle);
            wp_deregister_script($handle);
        }
    }

    public function maybe_hide_admin_bar(bool $show): bool
    {
        if (is_admin()) {
            return $show;
        }
        if (! is_user_logged_in()) {
            return $show;
        }
        $user = wp_get_current_user();
        $roles = (array) $user->roles;
        if (in_array('applicant', $roles, true) || in_array('employer_team', $roles, true)) {
            return false;
        }
        return $show;
    }

    public function dashboard_access_redirects(): void
    {
        $pages = get_option('careernest_pages', []);
        $applicant_id = isset($pages['applicant-dashboard']) ? (int) $pages['applicant-dashboard'] : 0;
        $employer_id  = isset($pages['employer-dashboard']) ? (int) $pages['employer-dashboard'] : 0;
        if (($applicant_id && is_page($applicant_id)) || ($employer_id && is_page($employer_id))) {
            if (! is_user_logged_in()) {
                wp_safe_redirect(wp_login_url(get_permalink()));
                exit;
            }
            $user  = wp_get_current_user();
            $roles = (array) $user->roles;
            if ($employer_id && is_page($employer_id) && in_array('applicant', $roles, true)) {
                if ($applicant_id) {
                    wp_safe_redirect(get_permalink($applicant_id));
                    exit;
                }
            }
            if ($applicant_id && is_page($applicant_id) && in_array('employer_team', $roles, true)) {
                if ($employer_id) {
                    wp_safe_redirect(get_permalink($employer_id));
                    exit;
                }
            }
        }
    }

    public function expire_due_jobs(): void
    {
        $today = current_time('Y-m-d');
        $q = new \WP_Query([
            'post_type'      => 'job_listing',
            'post_status'    => ['publish', 'future', 'pending'],
            'posts_per_page' => 10,
            'fields'         => 'ids',
            'meta_query'     => [['key' => '_closing_date', 'value' => $today, 'compare' => '<', 'type' => 'DATE']],
            'no_found_rows'  => true,
            'orderby'        => 'date',
            'order'          => 'ASC',
        ]);
        if ($q->have_posts()) {
            foreach ($q->posts as $pid) {
                $post = get_post($pid);
                if ($post && 'draft' !== $post->post_status) {
                    wp_update_post(['ID' => $pid, 'post_status' => 'draft']);
                }
            }
        }
    }

    /**
     * Template loader for CareerNest pages and CPTs.
     * Maps stored page IDs to plugin templates and handles single job listings.
     *
     * @param string $template The path of the template to include.
     * @return string The path of the template to include.
     */
    public function template_loader(string $template): string
    {
        $original_template = $template;

        // Handle single job listing posts
        if (is_singular('job_listing')) {
            $plugin_template = $this->locate_template('single-job_listing.php');
            if ($plugin_template) {
                $template = $plugin_template;
            }

            /**
             * Filter the single job listing template.
             *
             * @param string $template The template path.
             * @param string $original_template The original template path.
             */
            return apply_filters('careernest_single_job_template', $template, $original_template);
        }

        // Handle single employer posts
        if (is_singular('employer')) {
            $plugin_template = $this->locate_template('single-employer.php');
            if ($plugin_template) {
                $template = $plugin_template;
            }

            /**
             * Filter the single employer template.
             *
             * @param string $template The template path.
             * @param string $original_template The original template path.
             */
            return apply_filters('careernest_single_employer_template', $template, $original_template);
        }

        // Handle single applicant posts
        if (is_singular('applicant')) {
            $plugin_template = $this->locate_template('single-applicant.php');
            if ($plugin_template) {
                $template = $plugin_template;
            }

            /**
             * Filter the single applicant template.
             *
             * @param string $template The template path.
             * @param string $original_template The original template path.
             */
            return apply_filters('careernest_single_applicant_template', $template, $original_template);
        }

        // Handle plugin-managed pages
        if (is_page()) {
            $page_id = get_queried_object_id();
            $pages = get_option('careernest_pages', []);

            // Map page IDs to template files
            $page_templates = [
                'jobs' => 'template-jobs.php',
                'employer-dashboard' => 'template-employer-dashboard.php',
                'applicant-dashboard' => 'template-applicant-dashboard.php',
                'register-employer' => 'template-register-employer.php',
                'register-applicant' => 'template-register-applicant.php',
                'apply-job' => 'template-apply-job.php',
            ];

            /**
             * Filter the page template mapping.
             *
             * @param array $page_templates Array of page slug => template file mappings.
             */
            $page_templates = apply_filters('careernest_page_templates', $page_templates);

            foreach ($page_templates as $page_slug => $template_file) {
                if (isset($pages[$page_slug]) && (int) $pages[$page_slug] === $page_id) {
                    $plugin_template = $this->locate_template($template_file);
                    if ($plugin_template) {
                        $template = $plugin_template;
                    }

                    /**
                     * Filter the page template for a specific page.
                     *
                     * @param string $template The template path.
                     * @param string $original_template The original template path.
                     * @param string $page_slug The page slug.
                     * @param int $page_id The page ID.
                     */
                    $template = apply_filters("careernest_page_template_{$page_slug}", $template, $original_template, $page_slug, $page_id);

                    /**
                     * Filter the page template for any CareerNest page.
                     *
                     * @param string $template The template path.
                     * @param string $original_template The original template path.
                     * @param string $page_slug The page slug.
                     * @param int $page_id The page ID.
                     */
                    return apply_filters('careernest_page_template', $template, $original_template, $page_slug, $page_id);
                }
            }
        }

        return $template;
    }

    /**
     * Locate a template file, checking theme first, then plugin.
     * Allows theme overrides of plugin templates.
     *
     * @param string $template_name The template filename to locate.
     * @return string|false The template path if found, false otherwise.
     */
    private function locate_template(string $template_name)
    {
        // Allow themes to override plugin templates
        $theme_template = locate_template([
            'careernest/' . $template_name,
            $template_name,
        ]);

        if ($theme_template) {
            return $theme_template;
        }

        // Fall back to plugin template
        $plugin_template = CAREERNEST_DIR . 'templates/' . $template_name;
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }

        return false;
    }

    /**
     * Link guest applications to newly created user accounts
     * 
     * @param int $user_id The newly created user ID
     */
    public function link_guest_applications_to_user(int $user_id): void
    {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return;
        }

        $user_email = $user->user_email;

        // Find guest applications with this email address (simplified query)
        $guest_applications = new \WP_Query([
            'post_type' => 'job_application',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_applicant_email',
                    'value' => $user_email,
                    'compare' => '='
                ]
            ]
        ]);

        if ($guest_applications->have_posts()) {
            // Check if user has applicant role
            $user_roles = (array) $user->roles;
            $is_applicant = in_array('applicant', $user_roles, true);

            $applicant_profile_id = 0;

            // If user is an applicant, find their applicant profile
            if ($is_applicant) {
                // Look for existing applicant profile linked to this user
                $existing_applicant = new \WP_Query([
                    'post_type' => 'applicant',
                    'post_status' => 'publish',
                    'posts_per_page' => 1,
                    'meta_query' => [
                        [
                            'key' => '_user_id',
                            'value' => $user_id,
                            'compare' => '='
                        ]
                    ]
                ]);

                if ($existing_applicant->have_posts()) {
                    $applicant_profile_id = $existing_applicant->posts[0]->ID;
                }
            }

            // Link all guest applications to the user/applicant profile
            foreach ($guest_applications->posts as $application) {
                // Only link if not already linked to avoid duplicates
                $existing_user_id = get_post_meta($application->ID, '_user_id', true);
                if (!$existing_user_id) {
                    if ($applicant_profile_id) {
                        update_post_meta($application->ID, '_applicant_id', $applicant_profile_id);
                    }
                    update_post_meta($application->ID, '_user_id', $user_id);

                    // Add a note that this was originally a guest application
                    update_post_meta($application->ID, '_was_guest_application', true);
                }
            }

            // Send notification to user about linked applications
            $linked_count = count($guest_applications->posts);
            $subject = 'Your Job Applications Have Been Linked';
            $message = "Welcome to CareerNest!\n\n";
            $message .= "We found {$linked_count} job application" . ($linked_count > 1 ? 's' : '') . " associated with your email address and have linked them to your new account.\n\n";
            $message .= "You can now view and track your applications by logging into your dashboard.\n\n";
            $message .= "Thank you for joining CareerNest!";

            wp_mail($user_email, $subject, $message);
        }

        wp_reset_postdata();
    }

    /**
     * Enqueue frontend assets for specific pages
     */
    public function enqueue_frontend_assets(): void
    {
        $pages = get_option('careernest_pages', []);
        $applicant_dashboard_id = isset($pages['applicant-dashboard']) ? (int) $pages['applicant-dashboard'] : 0;
        $employer_dashboard_id = isset($pages['employer-dashboard']) ? (int) $pages['employer-dashboard'] : 0;

        // Check if we're on the applicant dashboard page
        if ($applicant_dashboard_id && is_page($applicant_dashboard_id)) {
            // Enqueue applicant dashboard specific assets
            wp_enqueue_style(
                'careernest-applicant-dashboard',
                CAREERNEST_URL . 'assets/css/applicant-dashboard.css',
                [],
                CAREERNEST_VERSION
            );

            wp_enqueue_script(
                'careernest-applicant-dashboard',
                CAREERNEST_URL . 'assets/js/applicant-dashboard.js',
                ['jquery'],
                CAREERNEST_VERSION,
                true
            );
        }

        // Check if we're on the employer dashboard page
        if ($employer_dashboard_id && is_page($employer_dashboard_id)) {
            // Enqueue employer dashboard specific assets
            wp_enqueue_style(
                'careernest-employer-dashboard',
                CAREERNEST_URL . 'assets/css/employer-dashboard.css',
                [],
                CAREERNEST_VERSION
            );

            wp_enqueue_script(
                'careernest-employer-dashboard',
                CAREERNEST_URL . 'assets/js/employer-dashboard.js',
                ['jquery'],
                CAREERNEST_VERSION,
                true
            );
        }
    }
}
