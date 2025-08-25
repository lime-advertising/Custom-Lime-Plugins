<?php

/**
 * Template: CareerNest — Single Job Listing
 */

defined('ABSPATH') || exit;

get_header();

// Get job meta data
$job_id = get_the_ID();
$employer_id = get_post_meta($job_id, '_employer_id', true);
$company_name = $employer_id ? get_the_title($employer_id) : '';
$location = get_post_meta($job_id, '_job_location', true);
$remote_position = get_post_meta($job_id, '_remote_position', true);
$salary_mode = get_post_meta($job_id, '_salary_mode', true);
$salary_range = get_post_meta($job_id, '_salary_range', true);
$salary_numeric = get_post_meta($job_id, '_salary', true);
$opening_date = get_post_meta($job_id, '_opening_date', true);
$closing_date = get_post_meta($job_id, '_closing_date', true);
$apply_externally = get_post_meta($job_id, '_apply_externally', true);
$external_apply = get_post_meta($job_id, '_external_apply', true);
$position_filled = get_post_meta($job_id, '_position_filled', true);

// Check if current user has already applied for this job
$user_already_applied = false;
$application_date = '';
if (is_user_logged_in()) {
    $current_user_id = get_current_user_id();
    $existing_application = new WP_Query([
        'post_type' => 'job_application',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'meta_query' => [
            [
                'key' => '_job_id',
                'value' => $job_id,
                'compare' => '='
            ],
            [
                'key' => '_user_id',
                'value' => $current_user_id,
                'compare' => '='
            ]
        ]
    ]);

    if ($existing_application->have_posts()) {
        $user_already_applied = true;
        $application_post = $existing_application->posts[0];
        $application_date = get_post_meta($application_post->ID, '_application_date', true);
        if (!$application_date) {
            $application_date = $application_post->post_date;
        }
    }
    wp_reset_postdata();
}

// Job content sections
$job_overview = get_post_meta($job_id, '_job_overview', true);
$who_we_are = get_post_meta($job_id, '_job_who_we_are', true);
$what_we_offer = get_post_meta($job_id, '_job_what_we_offer', true);
$responsibilities = get_post_meta($job_id, '_job_responsibilities', true);
$how_to_apply = get_post_meta($job_id, '_job_how_to_apply', true);

// Get taxonomies
$job_categories = get_the_terms($job_id, 'job_category');
$job_types = get_the_terms($job_id, 'job_type');
?>

<main id="primary" class="site-main">
    <div class="cn_job-listing-container">
        <article id="post-<?php the_ID(); ?>" <?php post_class('cn_job-listing-main'); ?>>
            <div class="cn_job-listing-header">
                <?php
                // Get employer logo if available
                $employer_logo = '';
                $employer_website = '';
                if ($employer_id) {
                    $employer_logo = get_the_post_thumbnail_url($employer_id, 'medium');
                    $employer_website = get_post_meta($employer_id, '_website', true);
                }
                ?>

                <?php if ($employer_logo): ?>
                    <div>
                        <?php if ($employer_website): ?>
                            <a href="<?php echo esc_url($employer_website); ?>" target="_blank" rel="noopener noreferrer">
                                <img decoding="async" class="cn_job-listing-logo" src="<?php echo esc_url($employer_logo); ?>"
                                    alt="<?php echo esc_attr(get_the_title()); ?>">
                            </a>
                        <?php else: ?>
                            <img decoding="async" class="cn_job-listing-logo" src="<?php echo esc_url($employer_logo); ?>"
                                alt="<?php echo esc_attr(get_the_title()); ?>">
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <h1 class="cn_job-listing-title"><?php echo esc_html(get_the_title()); ?></h1>

                <p class="cn_job-listing-companyName">
                    <?php if ($company_name): ?>
                        <?php if ($employer_website): ?>
                            <a style="color: #757575;" href="<?php echo esc_url($employer_website); ?>" target="_blank"
                                rel="noopener noreferrer"><?php echo esc_html($company_name); ?></a>
                        <?php else: ?>
                            <span style="color: #757575;"><?php echo esc_html($company_name); ?></span>
                        <?php endif; ?>
                        &nbsp;
                    <?php endif; ?>
                    <?php
                    // Back to jobs listing
                    $pages = get_option('careernest_pages', []);
                    $jobs_page_id = isset($pages['jobs']) ? (int) $pages['jobs'] : 0;
                    if ($jobs_page_id && get_post_status($jobs_page_id) === 'publish'):
                    ?>
                        <a class="back-btn" href="<?php echo esc_url(get_permalink($jobs_page_id)); ?>">View all jobs</a>
                    <?php endif; ?>
                </p>

                <div class="cn_job-listing-meta">
                    <?php if ($location): ?>
                        <span class="cn_job-listing-location">
                            <svg width="20" height="21" viewBox="0 0 20 21" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M3.33337 8.95258C3.33337 5.20473 6.31814 2.1665 10 2.1665C13.6819 2.1665 16.6667 5.20473 16.6667 8.95258C16.6667 12.6711 14.5389 17.0102 11.2192 18.5619C10.4453 18.9236 9.55483 18.9236 8.78093 18.5619C5.46114 17.0102 3.33337 12.6711 3.33337 8.95258Z"
                                    stroke="#7C7C7D" stroke-width="1.5"></path>
                                <ellipse cx="10" cy="8.8335" rx="2.5" ry="2.5" stroke="#7C7C7D" stroke-width="1.5">
                                </ellipse>
                            </svg>
                            <?php echo esc_html($location); ?>
                            <?php if ($remote_position): ?>
                                <span class="remote-indicator"> (Remote)</span>
                            <?php endif; ?>
                        </span>
                    <?php endif; ?>

                    <?php if ($job_types): ?>
                        <?php foreach ($job_types as $type):
                            $type_slug = $type->slug;
                            $type_color = '#17B86A'; // Default green, can be customized per type
                            switch ($type_slug) {
                                case 'part-time':
                                    $type_color = '#856404';
                                    break;
                                case 'contract':
                                    $type_color = '#0c5460';
                                    break;
                                case 'temporary':
                                    $type_color = '#721c24';
                                    break;
                                case 'internship':
                                    $type_color = '#383d41';
                                    break;
                                case 'freelance':
                                    $type_color = '#6c5ce7';
                                    break;
                            }
                        ?>
                            <span class="cn_job-listing-type" style="color: <?php echo esc_attr($type_color); ?>;">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M4.97883 9.68508C2.99294 8.89073 2 8.49355 2 8C2 7.50645 2.99294 7.10927 4.97883 6.31492L7.7873 5.19153C9.77318 4.39718 10.7661 4 12 4C13.2339 4 14.2268 4.39718 16.2127 5.19153L19.0212 6.31492C21.0071 7.10927 22 7.50645 22 8C22 8.49355 21.0071 8.89073 19.0212 9.68508L16.2127 10.8085C14.2268 11.6028 13.2339 12 12 12C10.7661 12 9.77318 11.6028 7.7873 10.8085L4.97883 9.68508Z"
                                        stroke="#7C7C7D" stroke-width="1.5"></path>
                                    <path
                                        d="M5.76613 10L4.97883 10.3149C2.99294 11.1093 2 11.5065 2 12C2 12.4935 2.99294 12.8907 4.97883 13.6851L7.7873 14.8085C9.77318 15.6028 10.7661 16 12 16C13.2339 16 14.2268 15.6028 16.2127 14.8085L19.0212 13.6851C21.0071 12.8907 22 12.4935 22 12C22 11.5065 21.0071 11.1093 19.0212 10.3149L18.2339 10"
                                        stroke="#7C7C7D" stroke-width="1.5"></path>
                                    <path
                                        d="M5.76613 14L4.97883 14.3149C2.99294 15.1093 2 15.5065 2 16C2 16.4935 2.99294 16.8907 4.97883 17.6851L7.7873 18.8085C9.77318 19.6028 10.7661 20 12 20C13.2339 20 14.2268 19.6028 16.2127 18.8085L19.0212 17.6851C21.0071 16.8907 22 16.4935 22 16C22 15.5065 21.0071 15.1093 19.0212 14.3149L18.2339 14"
                                        stroke="#7C7C7D" stroke-width="1.5"></path>
                                </svg>
                                <?php echo esc_html($type->name); ?>
                            </span>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if ($closing_date): ?>
                        <div class="cn_job-listing-deadline">
                            <svg width="20" height="21" viewBox="0 0 20 21" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M1.66659 10.5003C1.66659 15.1027 5.39755 18.8337 9.99992 18.8337C14.6023 18.8337 18.3333 15.1027 18.3333 10.5003C18.3333 5.89795 14.6023 2.16699 9.99992 2.16699"
                                    stroke="#7C7C7D" stroke-width="1.5" stroke-linecap="round"></path>
                                <path d="M10 8V11.3333H13.3333" stroke="#7C7C7D" stroke-width="1.5" stroke-linecap="round"
                                    stroke-linejoin="round"></path>
                                <circle cx="10" cy="10.5003" r="8.33333" stroke="#7C7C7D" stroke-width="1.5"
                                    stroke-linecap="round" stroke-dasharray="0.5 3.5"></circle>
                            </svg>
                            <span style="color: #757b8a;">Applications close: </span>
                            <span><?php echo esc_html(date('jS \of F, Y', strtotime($closing_date))); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php
                    // Display salary information
                    if ($salary_mode === 'numeric' && $salary_numeric): ?>
                        <span class="cn_job-listing-salary">
                            <svg width="20" height="21" viewBox="0 0 20 21" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="10" cy="10.4998" r="8.33333" stroke="#7C7C7D" stroke-width="1.5"></circle>
                                <path d="M10 5.5V15.5" stroke="#7C7C7D" stroke-width="1.5" stroke-linecap="round"></path>
                                <path
                                    d="M12.5 8.41683C12.5 7.26624 11.3807 6.3335 10 6.3335C8.61929 6.3335 7.5 7.26624 7.5 8.41683C7.5 9.56742 8.61929 10.5002 10 10.5002C11.3807 10.5002 12.5 11.4329 12.5 12.5835C12.5 13.7341 11.3807 14.6668 10 14.6668C8.61929 14.6668 7.5 13.7341 7.5 12.5835"
                                    stroke="#7C7C7D" stroke-width="1.5" stroke-linecap="round"></path>
                            </svg>
                            $<?php echo esc_html(number_format($salary_numeric)); ?>
                        </span>
                    <?php elseif ($salary_range): ?>
                        <span class="cn_job-listing-salary">
                            <svg width="20" height="21" viewBox="0 0 20 21" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="10" cy="10.4998" r="8.33333" stroke="#7C7C7D" stroke-width="1.5"></circle>
                                <path d="M10 5.5V15.5" stroke="#7C7C7D" stroke-width="1.5" stroke-linecap="round"></path>
                                <path
                                    d="M12.5 8.41683C12.5 7.26624 11.3807 6.3335 10 6.3335C8.61929 6.3335 7.5 7.26624 7.5 8.41683C7.5 9.56742 8.61929 10.5002 10 10.5002C11.3807 10.5002 12.5 11.4329 12.5 12.5835C12.5 13.7341 11.3807 14.6668 10 14.6668C8.61929 14.6668 7.5 13.7341 7.5 12.5835"
                                    stroke="#7C7C7D" stroke-width="1.5" stroke-linecap="round"></path>
                            </svg>
                            <?php echo esc_html($salary_range); ?>
                        </span>
                    <?php endif; ?>

                    <?php if ($position_filled): ?>
                        <span class="cn_job-listing-filled" style="color: #dc3545;">✅ Position Filled</span>
                    <?php endif; ?>
                </div>

                <?php if ($job_categories): ?>
                    <div class="cn_job-listing-categories">
                        <?php foreach ($job_categories as $category): ?>
                            <span class="cn_job-category-tag"><?php echo esc_html($category->name); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($user_already_applied): ?>
                    <div class="cn_job-already-applied">
                        <div class="cn_already-applied-message">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M20 6L9 17L4 12" stroke="#10B981" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" />
                            </svg>
                            <span>You already applied for this job</span>
                            <?php if ($application_date): ?>
                                <span class="application-date">on
                                    <?php echo esc_html(date('F j, Y', strtotime($application_date))); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="entry-content">
                <?php if ($job_overview): ?>
                    <section class="job-overview">
                        <h2>Overview</h2>
                        <div><?php echo wp_kses_post(wpautop($job_overview)); ?></div>
                    </section>
                <?php endif; ?>

                <?php if ($who_we_are): ?>
                    <section class="job-who-we-are">
                        <h2>Who We Are</h2>
                        <div><?php echo wp_kses_post(wpautop($who_we_are)); ?></div>
                    </section>
                <?php endif; ?>

                <?php if ($what_we_offer): ?>
                    <section class="job-what-we-offer">
                        <h2>What We Offer</h2>
                        <div><?php echo wp_kses_post(wpautop($what_we_offer)); ?></div>
                    </section>
                <?php endif; ?>

                <?php if ($responsibilities): ?>
                    <section class="job-responsibilities">
                        <h2>Key Responsibilities</h2>
                        <div><?php echo wp_kses_post(wpautop($responsibilities)); ?></div>
                    </section>
                <?php endif; ?>

                <?php if ($how_to_apply): ?>
                    <section class="job-how-to-apply">
                        <h2>How to Apply</h2>
                        <div><?php echo wp_kses_post(wpautop($how_to_apply)); ?></div>
                    </section>
                <?php endif; ?>

                <?php
                // Show the main post content if it exists
                if (get_the_content()):
                ?>
                    <section class="additional-content">
                        <h2>Additional Information</h2>
                        <?php the_content(); ?>
                    </section>
                <?php endif; ?>
            </div>

            <footer class="entry-footer">
                <?php
                // Only show apply section if user hasn't already applied and position isn't filled
                if (!$user_already_applied && !$position_filled):
                    // Handle application logic - external vs internal
                    if ($apply_externally && $external_apply): ?>
                        <div class="job-apply-section">
                            <?php if (filter_var($external_apply, FILTER_VALIDATE_EMAIL)): ?>
                                <a href="mailto:<?php echo esc_attr($external_apply); ?>?subject=Application for <?php echo esc_attr(get_the_title()); ?>"
                                    class="btn btn-primary job-apply-btn">
                                    Apply via Email
                                </a>
                            <?php else: ?>
                                <a href="<?php echo esc_url($external_apply); ?>" target="_blank" rel="noopener noreferrer"
                                    class="btn btn-primary job-apply-btn">
                                    Apply Externally
                                </a>
                            <?php endif; ?>
                        </div>
                        <?php else:
                        // Internal application
                        $pages = get_option('careernest_pages', []);
                        $apply_page_id = isset($pages['apply-job']) ? (int) $pages['apply-job'] : 0;

                        if ($apply_page_id && get_post_status($apply_page_id) === 'publish'):
                            $apply_url = add_query_arg('job_id', $job_id, get_permalink($apply_page_id));
                        ?>
                            <div class="job-apply-section">
                                <a href="<?php echo esc_url($apply_url); ?>" class="btn btn-primary job-apply-btn">
                                    Apply for this Job
                                </a>
                            </div>
                    <?php endif;
                    endif;
                elseif ($user_already_applied): ?>
                    <div class="job-apply-section already-applied">
                        <div class="already-applied-notice">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M20 6L9 17L4 12" stroke="#10B981" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" />
                            </svg>
                            <div class="already-applied-text">
                                <strong>Application Submitted</strong>
                                <p>You have already applied for this position<?php if ($application_date): ?> on
                                    <?php echo esc_html(date('F j, Y', strtotime($application_date))); ?><?php endif; ?>.
                                </p>
                                <?php
                                // Link to applicant dashboard
                                $pages = get_option('careernest_pages', []);
                                $dashboard_page_id = isset($pages['applicant-dashboard']) ? (int) $pages['applicant-dashboard'] : 0;
                                if ($dashboard_page_id && get_post_status($dashboard_page_id) === 'publish'):
                                ?>
                                    <a href="<?php echo esc_url(get_permalink($dashboard_page_id)); ?>"
                                        class="view-application-btn">
                                        View Your Applications →
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php elseif ($position_filled): ?>
                    <div class="job-apply-section position-filled">
                        <div class="position-filled-notice">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="12" cy="12" r="10" stroke="#dc3545" stroke-width="2" />
                                <path d="m15 9-6 6" stroke="#dc3545" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" />
                                <path d="m9 9 6 6" stroke="#dc3545" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" />
                            </svg>
                            <div class="position-filled-text">
                                <strong>Position Filled</strong>
                                <p>This position has been filled and is no longer accepting applications.</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="job-navigation">
                    <?php
                    // Previous/Next job navigation
                    $prev_job = get_previous_post(true, '', 'job_category');
                    $next_job = get_next_post(true, '', 'job_category');

                    if ($prev_job || $next_job):
                    ?>
                        <nav class="job-nav">
                            <?php if ($prev_job): ?>
                                <a href="<?php echo esc_url(get_permalink($prev_job->ID)); ?>" class="prev-job">
                                    ← Previous Job
                                </a>
                            <?php endif; ?>

                            <?php if ($next_job): ?>
                                <a href="<?php echo esc_url(get_permalink($next_job->ID)); ?>" class="next-job">
                                    Next Job →
                                </a>
                            <?php endif; ?>
                        </nav>
                    <?php endif; ?>

                    <?php
                    // Back to jobs listing
                    $jobs_page_id = isset($pages['jobs']) ? (int) $pages['jobs'] : 0;
                    if ($jobs_page_id && get_post_status($jobs_page_id) === 'publish'):
                    ?>
                        <a href="<?php echo esc_url(get_permalink($jobs_page_id)); ?>" class="back-to-jobs">
                            ← Back to All Jobs
                        </a>
                    <?php endif; ?>
                </div>
            </footer>
        </article>

        <aside class="cn_job-listing-sidebar">
            <?php
            // Additional sidebar content can be added here
            // For example: Company info, job alerts signup, etc.
            if ($employer_id):
                $employer_tagline = get_post_meta($employer_id, '_tagline', true);
                $employer_about = get_post_meta($employer_id, '_about', true);
                $employer_website = get_post_meta($employer_id, '_website', true);
            ?>
                <div class="cn_employer-info">
                    <h3>About <?php echo esc_html($company_name); ?></h3>
                    <?php if ($employer_tagline): ?>
                        <p class="employer-tagline"><em><?php echo esc_html($employer_tagline); ?></em></p>
                    <?php endif; ?>
                    <?php if ($employer_about): ?>
                        <div class="employer-about">
                            <?php echo wp_kses_post(wp_trim_words(wpautop($employer_about), 50, '...')); ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($employer_website): ?>
                        <p><a href="<?php echo esc_url($employer_website); ?>" target="_blank" rel="noopener noreferrer"
                                class="employer-website-link">Visit Company Website →</a></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="cn_related-jobs">
                <h3>Related Jobs</h3>
                <?php
                // Get related jobs - start with simple query and add filters progressively
                $related_jobs = null;

                // Try 1: Same employer jobs (if employer exists)
                if ($employer_id) {
                    $related_args = [
                        'post_type' => 'job_listing',
                        'post_status' => 'publish',
                        'posts_per_page' => 5,
                        'post__not_in' => [$job_id],
                        'meta_query' => [
                            [
                                'key' => '_employer_id',
                                'value' => $employer_id,
                                'compare' => '='
                            ]
                        ]
                    ];
                    $related_jobs = new WP_Query($related_args);
                }

                // Try 2: Same category jobs (if no employer jobs found and categories exist)
                if ((!$related_jobs || !$related_jobs->have_posts()) && $job_categories && !is_wp_error($job_categories)) {
                    $category_ids = wp_list_pluck($job_categories, 'term_id');
                    $related_args = [
                        'post_type' => 'job_listing',
                        'post_status' => 'publish',
                        'posts_per_page' => 5,
                        'post__not_in' => [$job_id],
                        'tax_query' => [
                            [
                                'taxonomy' => 'job_category',
                                'field' => 'term_id',
                                'terms' => $category_ids,
                            ]
                        ]
                    ];
                    $related_jobs = new WP_Query($related_args);
                }

                // Try 3: Any recent jobs (fallback)
                if (!$related_jobs || !$related_jobs->have_posts()) {
                    $related_args = [
                        'post_type' => 'job_listing',
                        'post_status' => 'publish',
                        'posts_per_page' => 5,
                        'post__not_in' => [$job_id],
                        'orderby' => 'date',
                        'order' => 'DESC'
                    ];
                    $related_jobs = new WP_Query($related_args);
                }

                if ($related_jobs->have_posts()): ?>
                    <div class="cn_related-jobs-list">
                        <?php while ($related_jobs->have_posts()): $related_jobs->the_post();
                            $related_id = get_the_ID();
                            $related_employer_id = get_post_meta($related_id, '_employer_id', true);
                            $related_company = $related_employer_id ? get_the_title($related_employer_id) : '';
                            $related_location = get_post_meta($related_id, '_job_location', true);
                            $related_salary_range = get_post_meta($related_id, '_salary_range', true);
                            $related_salary_numeric = get_post_meta($related_id, '_salary', true);
                            $related_salary_mode = get_post_meta($related_id, '_salary_mode', true);
                            $related_employer_logo = $related_employer_id ? get_the_post_thumbnail_url($related_employer_id, 'thumbnail') : '';

                            // Calculate days until closing
                            $related_closing_date = get_post_meta($related_id, '_closing_date', true);
                            $expiry_text = '';
                            if ($related_closing_date) {
                                $closing_timestamp = strtotime($related_closing_date . ' 23:59:59');
                                $current_timestamp = current_time('timestamp');
                                $days_diff = ceil(($closing_timestamp - $current_timestamp) / DAY_IN_SECONDS);

                                if ($days_diff > 0) {
                                    $expiry_text = 'Expires in ' . $days_diff . ' day' . ($days_diff > 1 ? 's' : '');
                                } elseif ($days_diff === 0) {
                                    $expiry_text = 'Expires today';
                                } else {
                                    $expiry_text = 'Expired';
                                }
                            } else {
                                $expiry_text = 'No closing date';
                            }
                        ?>
                            <div class="cn_related_job_card">
                                <div class="cn_related_job_card-top">
                                    <?php if ($related_employer_logo): ?>
                                        <img class="cn_related_job_card__img" src="<?php echo esc_url($related_employer_logo); ?>"
                                            alt="<?php echo esc_attr(get_the_title()); ?>">
                                    <?php else: ?>
                                        <div class="cn_related_job_card__img-placeholder">
                                            <span><?php echo esc_html(substr($related_company ?: 'Job', 0, 1)); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <h4 class="cn_related_job_card__title">
                                            <a
                                                href="<?php echo esc_url(get_permalink()); ?>"><?php echo esc_html(get_the_title()); ?></a>
                                        </h4>
                                        <span
                                            class="cn_related_job_card__company"><?php echo esc_html($related_company); ?></span>
                                        <?php if ($related_location): ?>
                                            <span style="color: #CACACA; font-size: 14px;"> | </span>
                                            <span
                                                class="cn_related_job_card__location"><?php echo esc_html($related_location); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if ($related_salary_mode === 'numeric' && $related_salary_numeric): ?>
                                    <div class="cn_related_job_card__salary">
                                        <svg width="20" height="21" viewBox="0 0 20 21" fill="none"
                                            xmlns="http://www.w3.org/2000/svg">
                                            <circle cx="10" cy="10.4998" r="8.33333" stroke="#3D3935" stroke-width="1.5"></circle>
                                            <path d="M10 5.5V15.5" stroke="#3D3935" stroke-width="1.5" stroke-linecap="round">
                                            </path>
                                            <path
                                                d="M12.5 8.41683C12.5 7.26624 11.3807 6.3335 10 6.3335C8.61929 6.3335 7.5 7.26624 7.5 8.41683C7.5 9.56742 8.61929 10.5002 10 10.5002C11.3807 10.5002 12.5 11.4329 12.5 12.5835C12.5 13.7341 11.3807 14.6668 10 14.6668C8.61929 14.6668 7.5 13.7341 7.5 12.5835"
                                                stroke="#3D3935" stroke-width="1.5" stroke-linecap="round"></path>
                                        </svg>
                                        <span>$ <?php echo esc_html(number_format($related_salary_numeric)); ?></span>
                                    </div>
                                <?php elseif ($related_salary_range): ?>
                                    <div class="cn_related_job_card__salary">
                                        <svg width="20" height="21" viewBox="0 0 20 21" fill="none"
                                            xmlns="http://www.w3.org/2000/svg">
                                            <circle cx="10" cy="10.4998" r="8.33333" stroke="#3D3935" stroke-width="1.5"></circle>
                                            <path d="M10 5.5V15.5" stroke="#3D3935" stroke-width="1.5" stroke-linecap="round">
                                            </path>
                                            <path
                                                d="M12.5 8.41683C12.5 7.26624 11.3807 6.3335 10 6.3335C8.61929 6.3335 7.5 7.26624 7.5 8.41683C7.5 9.56742 8.61929 10.5002 10 10.5002C11.3807 10.5002 12.5 11.4329 12.5 12.5835C12.5 13.7341 11.3807 14.6668 10 14.6668C8.61929 14.6668 7.5 13.7341 7.5 12.5835"
                                                stroke="#3D3935" stroke-width="1.5" stroke-linecap="round"></path>
                                        </svg>
                                        <span><?php echo esc_html($related_salary_range); ?></span>
                                    </div>
                                <?php endif; ?>

                                <hr>

                                <div class="cn_related_job_card-bottom">
                                    <div class="cn_related_job_card__published">
                                        <!-- Published date can be added here if needed -->
                                    </div>
                                    <span class="cn_related_job_card__modified"><?php echo esc_html($expiry_text); ?></span>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                    <?php wp_reset_postdata(); ?>
                <?php else: ?>
                    <p class="no-related-jobs">No related jobs found.</p>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</main>

<style>
    /* CareerNest Job Listing Container */
    .site-main {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px;
    }

    .cn_job-listing-container {
        display: grid;
        grid-template-columns: 1fr 300px;
        gap: 2rem;
        align-items: start;
    }

    .cn_job-listing-main {
        min-width: 0;
        /* Prevents grid overflow */
    }

    /* CareerNest Job Listing Header */
    .cn_job-listing-header {
        margin-bottom: 2rem;
        padding-bottom: 1.5rem;
        border-bottom: 1px solid #e0e0e0;
    }

    .cn_job-listing-logo {
        max-width: 120px;
        max-height: 60px;
        margin-bottom: 1rem;
    }

    .cn_job-listing-title {
        font-size: 2rem;
        font-weight: bold;
        margin: 0.5rem 0;
        color: #333;
    }

    .cn_job-listing-companyName {
        margin: 0.5rem 0 1rem 0;
        font-size: 1rem;
    }

    .back-btn {
        color: #0073aa;
        text-decoration: none;
        font-weight: 500;
    }

    .back-btn:hover {
        text-decoration: underline;
    }

    .cn_job-listing-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        margin: 1rem 0;
    }

    .cn_job-listing-location,
    .cn_job-listing-type,
    .cn_job-listing-salary {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.9rem;
        color: #333;
    }

    .cn_job-listing-location svg,
    .cn_job-listing-type svg,
    .cn_job-listing-salary svg {
        flex-shrink: 0;
    }

    .cn_job-listing-deadline {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.9rem;
        color: #333;
    }

    .cn_job-listing-deadline svg {
        flex-shrink: 0;
    }

    .cn_job-listing-filled {
        font-weight: 600;
    }

    .cn_job-listing-categories {
        margin-top: 1rem;
    }

    /* Already Applied Styling */
    .cn_job-already-applied {
        background: #d4edda;
        border: 1px solid #c3e6cb;
        border-radius: 8px;
        padding: 1rem;
        margin: 1rem 0;
    }

    .cn_already-applied-message {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        color: #155724;
        font-weight: 500;
    }

    .cn_already-applied-message svg {
        flex-shrink: 0;
    }

    .application-date {
        color: #155724;
        font-weight: normal;
        font-size: 0.9rem;
    }

    /* Application Status Styling */
    .job-apply-section.already-applied {
        background: #d4edda;
        border: 1px solid #c3e6cb;
    }

    .job-apply-section.position-filled {
        background: #f8d7da;
        border: 1px solid #f5c6cb;
    }

    .already-applied-notice,
    .position-filled-notice {
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        text-align: left;
    }

    .already-applied-notice svg,
    .position-filled-notice svg {
        flex-shrink: 0;
        margin-top: 0.25rem;
    }

    .already-applied-text,
    .position-filled-text {
        flex: 1;
    }

    .already-applied-text strong,
    .position-filled-text strong {
        color: #155724;
        font-size: 1.1rem;
        display: block;
        margin-bottom: 0.5rem;
    }

    .position-filled-text strong {
        color: #721c24;
    }

    .already-applied-text p,
    .position-filled-text p {
        margin: 0 0 1rem 0;
        color: #155724;
    }

    .position-filled-text p {
        color: #721c24;
    }

    .view-application-btn {
        color: #0073aa;
        text-decoration: none;
        font-weight: 500;
        display: inline-block;
        padding: 0.5rem 1rem;
        background: white;
        border: 1px solid #0073aa;
        border-radius: 4px;
        transition: all 0.2s ease;
    }

    .view-application-btn:hover {
        background: #0073aa;
        color: white;
        text-decoration: none;
    }

    .cn_job-category-tag {
        display: inline-block;
        background: #e0e0e0;
        color: #333;
        padding: 0.25rem 0.5rem;
        margin: 0.25rem 0.25rem 0.25rem 0;
        border-radius: 4px;
        font-size: 0.8rem;
    }

    .remote-indicator {
        color: #17B86A;
        font-weight: 500;
    }

    /* Job Content */
    .entry-content {
        margin: 2rem 0;
    }

    .entry-content section {
        margin-bottom: 2rem;
    }

    .entry-content h2 {
        color: #333;
        font-size: 1.5rem;
        margin-bottom: 1rem;
        border-bottom: 2px solid #0073aa;
        padding-bottom: 0.5rem;
    }

    /* Application Section */
    .job-apply-section {
        margin: 2rem 0;
        text-align: center;
        padding: 2rem;
        background: #f8f9fa;
        border-radius: 8px;
    }

    .job-apply-btn {
        display: inline-block;
        background: #0073aa;
        color: white;
        padding: 1rem 2rem;
        text-decoration: none;
        border-radius: 4px;
        font-weight: bold;
        font-size: 1.1rem;
    }

    .job-apply-btn:hover {
        background: #005a87;
        color: white;
    }

    /* Navigation */
    .job-nav {
        display: flex;
        justify-content: space-between;
        margin: 1rem 0;
    }

    .job-navigation {
        border-top: 1px solid #e0e0e0;
        padding-top: 1rem;
        margin-top: 2rem;
    }

    .back-to-jobs {
        display: inline-block;
        margin-top: 1rem;
        color: #0073aa;
        text-decoration: none;
    }

    .back-to-jobs:hover {
        text-decoration: underline;
    }

    /* Sidebar Styling */
    .cn_job-listing-sidebar {
        background: #f8f9fa;
        padding: 1.5rem;
        border-radius: 8px;
        height: fit-content;
        position: sticky;
        top: 2rem;
    }

    .cn_related-jobs,
    .cn_employer-info {
        margin-bottom: 2rem;
    }

    .cn_related-jobs h3,
    .cn_employer-info h3 {
        color: #333;
        font-size: 1.2rem;
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid #0073aa;
    }

    /* Related Job Cards */
    .cn_related_job_card {
        background: white;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 1rem;
        transition: box-shadow 0.2s ease;
    }

    .cn_related_job_card:hover {
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .cn_related_job_card-top {
        display: flex;
        gap: 0.75rem;
        margin-bottom: 1rem;
    }

    .cn_related_job_card__img {
        width: 50px;
        height: 50px;
        object-fit: cover;
        border-radius: 4px;
        flex-shrink: 0;
    }

    .cn_related_job_card__img-placeholder {
        width: 50px;
        height: 50px;
        background: #e0e0e0;
        border-radius: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        color: #666;
        flex-shrink: 0;
    }

    .cn_related_job_card__title {
        margin: 0 0 0.25rem 0;
        font-size: 0.95rem;
        line-height: 1.3;
    }

    .cn_related_job_card__title a {
        color: #333;
        text-decoration: none;
        font-weight: 600;
    }

    .cn_related_job_card__title a:hover {
        color: #0073aa;
        text-decoration: underline;
    }

    .cn_related_job_card__company,
    .cn_related_job_card__location {
        color: #666;
        font-size: 0.85rem;
    }

    .cn_related_job_card__salary {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 1rem;
        color: #333;
        font-size: 0.9rem;
    }

    .cn_related_job_card__salary svg {
        flex-shrink: 0;
    }

    .cn_related_job_card hr {
        border: none;
        border-top: 1px solid #e0e0e0;
        margin: 1rem 0;
    }

    .cn_related_job_card-bottom {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .cn_related_job_card__modified {
        color: #999;
        font-size: 0.8rem;
    }

    .no-related-jobs {
        color: #666;
        font-style: italic;
        margin: 1rem 0;
    }

    /* Employer Info Styling */
    .employer-tagline {
        color: #666;
        margin: 0.5rem 0;
    }

    .employer-about {
        color: #555;
        font-size: 0.9rem;
        line-height: 1.5;
        margin: 1rem 0;
    }

    .employer-website-link {
        color: #0073aa;
        text-decoration: none;
        font-weight: 500;
    }

    .employer-website-link:hover {
        text-decoration: underline;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .cn_job-listing-container {
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        .cn_job-listing-sidebar {
            order: -1;
            /* Show sidebar above main content on mobile */
            position: static;
            margin-bottom: 2rem;
        }

        .cn_job-listing-title {
            font-size: 1.5rem;
        }

        .cn_job-listing-meta {
            flex-direction: column;
            gap: 0.5rem;
        }

        .job-nav {
            flex-direction: column;
            gap: 0.5rem;
        }

        .job-apply-section {
            padding: 1rem;
        }
    }
</style>

<?php
get_footer();
