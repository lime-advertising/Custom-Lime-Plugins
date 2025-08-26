<?php

/**
 * Template: CareerNest — Employer Dashboard
 */

defined('ABSPATH') || exit;

if (!is_user_logged_in()) {
    wp_safe_redirect(wp_login_url(get_permalink()));
    exit;
}

get_header();

// Handle profile update
$profile_updated = false;
$profile_errors = [];
$profile_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cn_update_employer_profile_nonce'])) {
    if (!wp_verify_nonce($_POST['cn_update_employer_profile_nonce'], 'cn_update_employer_profile')) {
        $profile_errors[] = 'Security verification failed. Please try again.';
    } else {
        $result = process_employer_profile_update();
        if ($result['success']) {
            $profile_updated = true;
            $profile_success = $result['message'];
        } else {
            $profile_errors = $result['errors'];
        }
    }
}

/**
 * Process personal profile update for employer team member
 */
function process_employer_profile_update()
{
    global $current_user;

    $errors = [];

    // Sanitize form data for personal profile
    $full_name = sanitize_text_field($_POST['full_name'] ?? '');
    $job_title = sanitize_text_field($_POST['job_title'] ?? '');
    $personal_email = sanitize_email($_POST['personal_email'] ?? '');
    $personal_phone = sanitize_text_field($_POST['personal_phone'] ?? '');
    $bio = wp_kses_post($_POST['bio'] ?? '');

    // Validation
    if (empty($full_name)) {
        $errors[] = 'Full name is required.';
    }

    if (!empty($personal_email) && !is_email($personal_email)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }

    // Update user profile
    $user_data = [
        'ID' => $current_user->ID,
        'display_name' => $full_name,
        'first_name' => explode(' ', $full_name)[0],
        'last_name' => substr($full_name, strlen(explode(' ', $full_name)[0]) + 1),
    ];

    // Only update email if it's different and not empty
    if (!empty($personal_email) && $personal_email !== $current_user->user_email) {
        $user_data['user_email'] = $personal_email;
    }

    $result = wp_update_user($user_data);
    if (is_wp_error($result)) {
        return ['success' => false, 'errors' => [$result->get_error_message()]];
    }

    // Update user meta fields
    update_user_meta($current_user->ID, '_job_title', $job_title);
    update_user_meta($current_user->ID, '_personal_phone', $personal_phone);
    update_user_meta($current_user->ID, '_bio', $bio);

    return [
        'success' => true,
        'message' => 'Your personal profile has been updated successfully!'
    ];
}

// Get current user and employer profile
$current_user = wp_get_current_user();
$user_roles = (array) $current_user->roles;

// Verify user has employer_team role
if (!in_array('employer_team', $user_roles, true)) {
?>
    <main id="primary" class="site-main">
        <div class="cn-dashboard-error">
            <h1><?php echo esc_html__('Access Denied', 'careernest'); ?></h1>
            <p><?php echo esc_html__('You do not have permission to access this dashboard.', 'careernest'); ?></p>
        </div>
    </main>
<?php
    get_footer();
    return;
}

// Find employer profile - employer team members are linked via user meta _employer_id
$employer_id = (int) get_user_meta($current_user->ID, '_employer_id', true);
$employer_profile = null;

if ($employer_id) {
    $employer_profile = get_post($employer_id);
    // Verify the employer post exists and is published
    if (!$employer_profile || $employer_profile->post_status !== 'publish' || $employer_profile->post_type !== 'employer') {
        $employer_id = 0;
        $employer_profile = null;
    }
}

// Get employer data
$company_name = $employer_profile ? $employer_profile->post_title : '';
$company_description = $employer_profile ? $employer_profile->post_content : '';
$website = $employer_id ? get_post_meta($employer_id, '_website', true) : '';
$contact_email = $employer_id ? get_post_meta($employer_id, '_contact_email', true) : '';
$phone = $employer_id ? get_post_meta($employer_id, '_phone', true) : '';
$location = $employer_id ? get_post_meta($employer_id, '_location', true) : '';
$employee_count = $employer_id ? get_post_meta($employer_id, '_employee_count', true) : '';

// If no employee count in new field, check the old company_size field
if (!$employee_count) {
    $employee_count = $employer_id ? get_post_meta($employer_id, '_company_size', true) : '';
}

// Get personal profile data for the team member
$personal_job_title = get_user_meta($current_user->ID, '_job_title', true);
$personal_phone = get_user_meta($current_user->ID, '_personal_phone', true);
$personal_bio = get_user_meta($current_user->ID, '_bio', true);

// Get employer's jobs
$jobs_query = new WP_Query([
    'post_type' => 'job_listing',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'meta_query' => [
        [
            'key' => '_employer_id',
            'value' => $employer_id,
            'compare' => '='
        ]
    ],
    'orderby' => 'date',
    'order' => 'DESC'
]);

// Get job statistics
$total_jobs = $jobs_query->found_posts;
$active_jobs = 0;
$filled_jobs = 0;
$expired_jobs = 0;

if ($jobs_query->have_posts()) {
    foreach ($jobs_query->posts as $job) {
        $position_filled = get_post_meta($job->ID, '_position_filled', true);
        $closing_date = get_post_meta($job->ID, '_closing_date', true);

        if ($position_filled) {
            $filled_jobs++;
        } elseif ($closing_date && strtotime($closing_date) < current_time('timestamp')) {
            $expired_jobs++;
        } else {
            $active_jobs++;
        }
    }
}

// Get applications for employer's jobs
$applications_query = new WP_Query([
    'post_type' => 'job_application',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'meta_query' => [
        [
            'key' => '_job_id',
            'value' => wp_list_pluck($jobs_query->posts, 'ID'),
            'compare' => 'IN'
        ]
    ],
    'orderby' => 'date',
    'order' => 'DESC'
]);

// Get application statistics
$total_applications = $applications_query->found_posts;
$new_applications = 0;
$reviewed_applications = 0;

if ($applications_query->have_posts()) {
    foreach ($applications_query->posts as $app) {
        $status = get_post_meta($app->ID, '_app_status', true) ?: 'new';
        if ($status === 'new') {
            $new_applications++;
        } elseif (in_array($status, ['interviewed', 'offer_extended', 'hired'])) {
            $reviewed_applications++;
        }
    }
}
?>

<main id="primary" class="site-main">
    <div class="cn-employer-dashboard-container">
        <!-- Dashboard Header -->
        <div class="cn-dashboard-header">
            <div class="cn-header-content">
                <div class="cn-user-info">
                    <h1><?php echo esc_html__('Employer Dashboard', 'careernest'); ?></h1>
                    <?php if ($company_name): ?>
                        <p class="cn-company-name"><?php echo esc_html($company_name); ?></p>
                    <?php endif; ?>
                    <?php if ($location): ?>
                        <p class="cn-company-location">📍 <?php echo esc_html($location); ?></p>
                    <?php endif; ?>
                </div>
                <div class="cn-header-actions">
                    <?php if ($employer_id): ?>
                        <a href="<?php echo esc_url(get_permalink($employer_id)); ?>" target="_blank"
                            class="cn-btn cn-btn-secondary">
                            <?php echo esc_html__('View Public Profile', 'careernest'); ?>
                        </a>
                    <?php endif; ?>
                    <button type="button" class="cn-btn cn-btn-primary" id="cn-toggle-edit">
                        <span class="cn-edit-text">Edit My Profile</span>
                        <span class="cn-cancel-text" style="display: none;">Cancel Edit</span>
                    </button>
                    <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="cn-btn cn-btn-outline">Logout</a>
                </div>
            </div>
        </div>

        <!-- Dashboard Stats -->
        <div class="cn-dashboard-stats">
            <div class="cn-stat-card">
                <div class="cn-stat-number"><?php echo esc_html($total_jobs); ?></div>
                <div class="cn-stat-label"><?php echo esc_html__('Total Jobs', 'careernest'); ?></div>
            </div>
            <div class="cn-stat-card">
                <div class="cn-stat-number"><?php echo esc_html($active_jobs); ?></div>
                <div class="cn-stat-label"><?php echo esc_html__('Active Jobs', 'careernest'); ?></div>
            </div>
            <div class="cn-stat-card">
                <div class="cn-stat-number"><?php echo esc_html($total_applications); ?></div>
                <div class="cn-stat-label"><?php echo esc_html__('Total Applications', 'careernest'); ?></div>
            </div>
            <div class="cn-stat-card">
                <div class="cn-stat-number"><?php echo esc_html($new_applications); ?></div>
                <div class="cn-stat-label"><?php echo esc_html__('New Applications', 'careernest'); ?></div>
            </div>
        </div>

        <!-- Dashboard Content -->
        <div class="cn-dashboard-content">
            <!-- Main Content -->
            <div class="cn-dashboard-main">
                <!-- Job Listings -->
                <div class="cn-dashboard-section">
                    <div class="cn-section-header">
                        <h2><?php echo esc_html__('Your Job Listings', 'careernest'); ?></h2>
                        <button type="button" class="cn-btn cn-btn-primary" id="cn-show-job-form">
                            <?php echo esc_html__('Post New Job', 'careernest'); ?>
                        </button>
                    </div>

                    <?php if ($jobs_query->have_posts()): ?>
                        <div class="cn-jobs-list">
                            <?php while ($jobs_query->have_posts()): $jobs_query->the_post();
                                $job_id = get_the_ID();
                                $job_location = get_post_meta($job_id, '_job_location', true);
                                $position_filled = get_post_meta($job_id, '_position_filled', true);
                                $closing_date = get_post_meta($job_id, '_closing_date', true);
                                $opening_date = get_post_meta($job_id, '_opening_date', true);

                                // Get application count for this job
                                $job_applications = new WP_Query([
                                    'post_type' => 'job_application',
                                    'post_status' => 'publish',
                                    'posts_per_page' => -1,
                                    'meta_query' => [
                                        [
                                            'key' => '_job_id',
                                            'value' => $job_id,
                                            'compare' => '='
                                        ]
                                    ]
                                ]);
                                $application_count = $job_applications->found_posts;
                                wp_reset_postdata();

                                // Determine job status
                                $job_status = 'active';
                                $status_color = '#10B981';
                                if ($position_filled) {
                                    $job_status = 'filled';
                                    $status_color = '#0073aa';
                                } elseif ($closing_date && strtotime($closing_date) < current_time('timestamp')) {
                                    $job_status = 'expired';
                                    $status_color = '#dc3545';
                                }
                            ?>
                                <div class="cn-job-card">
                                    <div class="cn-job-header">
                                        <div class="cn-job-info">
                                            <h3>
                                                <a
                                                    href="<?php echo esc_url(get_permalink($job_id)); ?>"><?php echo esc_html(get_the_title($job_id)); ?></a>
                                            </h3>
                                            <?php if ($job_location): ?>
                                                <p class="cn-job-location">📍 <?php echo esc_html($job_location); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="cn-job-status">
                                            <span class="cn-status-badge"
                                                style="background-color: <?php echo esc_attr($status_color); ?>">
                                                <?php echo esc_html(ucfirst($job_status)); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="cn-job-meta">
                                        <span class="cn-job-date">Posted: <?php echo esc_html(get_the_date('F j, Y')); ?></span>
                                        <?php if ($closing_date): ?>
                                            <span class="cn-job-closing">Closes:
                                                <?php echo esc_html(date('F j, Y', strtotime($closing_date))); ?></span>
                                        <?php endif; ?>
                                        <span class="cn-job-applications"><?php echo esc_html($application_count); ?>
                                            applications</span>
                                    </div>

                                    <div class="cn-job-actions">
                                        <a href="<?php echo esc_url(get_permalink($job_id)); ?>"
                                            class="cn-btn cn-btn-small cn-btn-outline">View Job</a>
                                        <button type="button" class="cn-btn cn-btn-small cn-btn-outline cn-edit-job"
                                            data-job-id="<?php echo esc_attr($job_id); ?>">Edit</button>
                                        <?php if ($application_count > 0): ?>
                                            <button type="button" class="cn-btn cn-btn-small cn-btn-primary cn-view-applications"
                                                data-job-id="<?php echo esc_attr($job_id); ?>">
                                                View Applications (<?php echo esc_html($application_count); ?>)
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                        <?php wp_reset_postdata(); ?>
                    <?php else: ?>
                        <div class="cn-empty-state">
                            <div class="cn-empty-icon">
                                <svg width="64" height="64" viewBox="0 0 24 24" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path d="M20 14.66V20a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h5.34" stroke="#ccc"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    <polygon points="18,2 22,6 12,16 8,16 8,12 18,2" stroke="#ccc" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </div>
                            <h3><?php echo esc_html__('No Job Listings Yet', 'careernest'); ?></h3>
                            <p><?php echo esc_html__('Start by posting your first job listing to attract qualified candidates.', 'careernest'); ?>
                            </p>
                            <button type="button" class="cn-btn cn-btn-primary" id="cn-show-job-form">
                                <?php echo esc_html__('Post Your First Job', 'careernest'); ?>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Personal Profile Display -->
                <div class="cn-dashboard-section">
                    <h3><?php echo esc_html__('My Profile', 'careernest'); ?></h3>

                    <div class="cn-profile-item">
                        <strong><?php echo esc_html__('Name:', 'careernest'); ?></strong>
                        <span><?php echo esc_html($current_user->display_name); ?></span>
                    </div>

                    <div class="cn-profile-item">
                        <strong><?php echo esc_html__('Email:', 'careernest'); ?></strong>
                        <span><?php echo esc_html($current_user->user_email); ?></span>
                    </div>

                    <?php if ($personal_job_title): ?>
                        <div class="cn-profile-item">
                            <strong><?php echo esc_html__('Job Title:', 'careernest'); ?></strong>
                            <span><?php echo esc_html($personal_job_title); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($personal_phone): ?>
                        <div class="cn-profile-item">
                            <strong><?php echo esc_html__('Phone:', 'careernest'); ?></strong>
                            <span><?php echo esc_html($personal_phone); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($personal_bio): ?>
                        <div class="cn-profile-item">
                            <strong><?php echo esc_html__('Bio:', 'careernest'); ?></strong>
                            <div class="cn-personal-bio"><?php echo wp_kses_post(wpautop($personal_bio)); ?></div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Applications -->
                <?php if ($applications_query->have_posts()): ?>
                    <div class="cn-dashboard-section">
                        <div class="cn-section-header">
                            <h2><?php echo esc_html__('Recent Applications', 'careernest'); ?></h2>
                            <button type="button" class="cn-btn cn-btn-outline" id="cn-show-all-applications">
                                <?php echo esc_html__('View All Applications', 'careernest'); ?>
                            </button>
                        </div>

                        <div class="cn-applications-list">
                            <?php
                            $recent_applications = array_slice($applications_query->posts, 0, 5);
                            foreach ($recent_applications as $app):
                                $app_id = $app->ID;
                                $job_id = get_post_meta($app_id, '_job_id', true);
                                $applicant_id = get_post_meta($app_id, '_applicant_id', true);
                                $app_status = get_post_meta($app_id, '_app_status', true) ?: 'new';
                                $app_date = get_post_meta($app_id, '_application_date', true);
                                $resume_id = get_post_meta($app_id, '_resume_id', true);

                                $job_title = $job_id ? get_the_title($job_id) : 'Unknown Job';
                                $applicant_name = $applicant_id ? get_the_title($applicant_id) : 'Unknown Applicant';

                                $status_labels = [
                                    'new' => 'New',
                                    'reviewed' => 'Reviewed',
                                    'interviewed' => 'Interviewed',
                                    'offer_extended' => 'Offer Extended',
                                    'hired' => 'Hired',
                                    'rejected' => 'Rejected'
                                ];

                                $status_colors = [
                                    'new' => '#0073aa',
                                    'reviewed' => '#f39c12',
                                    'interviewed' => '#e67e22',
                                    'offer_extended' => '#27ae60',
                                    'hired' => '#10B981',
                                    'rejected' => '#e74c3c'
                                ];
                            ?>
                                <div class="cn-application-card">
                                    <div class="cn-app-header">
                                        <div class="cn-app-info">
                                            <h4><?php echo esc_html($applicant_name); ?></h4>
                                            <p class="cn-app-job">Applied for:
                                                <strong><?php echo esc_html($job_title); ?></strong>
                                            </p>
                                        </div>
                                        <div class="cn-app-status">
                                            <span class="cn-status-badge"
                                                style="background-color: <?php echo esc_attr($status_colors[$app_status] ?? '#666'); ?>">
                                                <?php echo esc_html($status_labels[$app_status] ?? 'Unknown'); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="cn-app-meta">
                                        <span class="cn-app-date">Applied:
                                            <?php echo esc_html($app_date ? date('F j, Y', strtotime($app_date)) : get_the_date('F j, Y', $app_id)); ?></span>
                                        <?php if ($resume_id): ?>
                                            <a href="<?php echo esc_url(wp_get_attachment_url($resume_id)); ?>" target="_blank"
                                                class="cn-app-resume">📄 View Resume</a>
                                        <?php endif; ?>
                                    </div>

                                    <div class="cn-app-actions">
                                        <button type="button" class="cn-btn cn-btn-small cn-btn-outline cn-review-application"
                                            data-app-id="<?php echo esc_attr($app_id); ?>">Review Application</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Success/Error Messages -->
                <?php if ($profile_updated): ?>
                    <div class="cn-profile-success">
                        <p><?php echo esc_html($profile_success); ?></p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($profile_errors)): ?>
                    <div class="cn-profile-errors">
                        <ul>
                            <?php foreach ($profile_errors as $error): ?>
                                <li><?php echo esc_html($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- Personal Profile Display -->
                <div class="cn-dashboard-section">
                    <h3><?php echo esc_html__('My Profile', 'careernest'); ?></h3>

                    <div class="cn-profile-item">
                        <strong><?php echo esc_html__('Name:', 'careernest'); ?></strong>
                        <span><?php echo esc_html($current_user->display_name); ?></span>
                    </div>

                    <div class="cn-profile-item">
                        <strong><?php echo esc_html__('Email:', 'careernest'); ?></strong>
                        <span><?php echo esc_html($current_user->user_email); ?></span>
                    </div>

                    <?php if ($personal_job_title): ?>
                        <div class="cn-profile-item">
                            <strong><?php echo esc_html__('Job Title:', 'careernest'); ?></strong>
                            <span><?php echo esc_html($personal_job_title); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($personal_phone): ?>
                        <div class="cn-profile-item">
                            <strong><?php echo esc_html__('Phone:', 'careernest'); ?></strong>
                            <span><?php echo esc_html($personal_phone); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($personal_bio): ?>
                        <div class="cn-profile-item">
                            <strong><?php echo esc_html__('Bio:', 'careernest'); ?></strong>
                            <div class="cn-personal-bio"><?php echo wp_kses_post(wpautop($personal_bio)); ?></div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Personal Profile Edit Form (Hidden by default) -->
                <div class="cn-profile-edit-form" id="cn-profile-edit-form" style="display: none;">
                    <form method="post" class="cn-profile-form">
                        <?php wp_nonce_field('cn_update_employer_profile', 'cn_update_employer_profile_nonce'); ?>

                        <div class="cn-dashboard-section">
                            <h3><?php echo esc_html__('My Profile', 'careernest'); ?></h3>

                            <div class="cn-form-field">
                                <label for="full_name"><?php echo esc_html__('Full Name', 'careernest'); ?> <span
                                        class="required">*</span></label>
                                <input type="text" id="full_name" name="full_name"
                                    value="<?php echo esc_attr($current_user->display_name); ?>" required
                                    class="cn-input">
                            </div>

                            <div class="cn-form-field">
                                <label for="job_title"><?php echo esc_html__('Job Title', 'careernest'); ?></label>
                                <input type="text" id="job_title" name="job_title"
                                    value="<?php echo esc_attr($personal_job_title); ?>" class="cn-input"
                                    placeholder="e.g., HR Manager, Recruiter">
                            </div>

                            <div class="cn-form-field">
                                <label
                                    for="personal_email"><?php echo esc_html__('Email Address', 'careernest'); ?></label>
                                <input type="email" id="personal_email" name="personal_email"
                                    value="<?php echo esc_attr($current_user->user_email); ?>" class="cn-input">
                                <p class="cn-field-help">
                                    <?php echo esc_html__('This will update your login email address.', 'careernest'); ?>
                                </p>
                            </div>

                            <div class="cn-form-field">
                                <label
                                    for="personal_phone"><?php echo esc_html__('Phone Number', 'careernest'); ?></label>
                                <input type="tel" id="personal_phone" name="personal_phone"
                                    value="<?php echo esc_attr($personal_phone); ?>" class="cn-input">
                            </div>

                            <div class="cn-form-field">
                                <label for="bio"><?php echo esc_html__('Bio', 'careernest'); ?></label>
                                <textarea id="bio" name="bio" rows="4" class="cn-input"
                                    placeholder="Tell us about yourself and your role..."><?php echo esc_textarea($personal_bio); ?></textarea>
                            </div>

                            <div class="cn-form-actions">
                                <button type="submit"
                                    class="cn-btn cn-btn-primary"><?php echo esc_html__('Save Changes', 'careernest'); ?></button>
                                <button type="button" class="cn-btn cn-btn-outline"
                                    id="cn-cancel-edit"><?php echo esc_html__('Cancel', 'careernest'); ?></button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="cn-dashboard-sidebar">
                <!-- Company Information -->
                <div class="cn-dashboard-section">
                    <h3><?php echo esc_html__('Company Information', 'careernest'); ?></h3>

                    <?php if ($company_name): ?>
                        <div class="cn-profile-item">
                            <strong><?php echo esc_html__('Company:', 'careernest'); ?></strong>
                            <span><?php echo esc_html($company_name); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($website): ?>
                        <div class="cn-profile-item">
                            <strong><?php echo esc_html__('Website:', 'careernest'); ?></strong>
                            <a href="<?php echo esc_url($website); ?>" target="_blank"
                                rel="noopener noreferrer"><?php echo esc_html($website); ?></a>
                        </div>
                    <?php endif; ?>

                    <?php if ($contact_email): ?>
                        <div class="cn-profile-item">
                            <strong><?php echo esc_html__('Contact:', 'careernest'); ?></strong>
                            <span><?php echo esc_html($contact_email); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($phone): ?>
                        <div class="cn-profile-item">
                            <strong><?php echo esc_html__('Phone:', 'careernest'); ?></strong>
                            <span><?php echo esc_html($phone); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($location): ?>
                        <div class="cn-profile-item">
                            <strong><?php echo esc_html__('Location:', 'careernest'); ?></strong>
                            <span><?php echo esc_html($location); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($employee_count): ?>
                        <div class="cn-profile-item">
                            <strong><?php echo esc_html__('Employees:', 'careernest'); ?></strong>
                            <span><?php echo esc_html($employee_count); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($company_description): ?>
                        <div class="cn-profile-item">
                            <strong><?php echo esc_html__('About:', 'careernest'); ?></strong>
                            <div class="cn-company-description"><?php echo wp_kses_post(wpautop($company_description)); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Quick Actions -->
                <div class="cn-dashboard-section">
                    <h3><?php echo esc_html__('Quick Actions', 'careernest'); ?></h3>
                    <div class="cn-quick-actions">
                        <button type="button" class="cn-quick-action" id="cn-show-job-form">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" />
                            </svg>
                            <?php echo esc_html__('Post New Job', 'careernest'); ?>
                        </button>
                        <button type="button" class="cn-quick-action" id="cn-show-job-management">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                xmlns="http://www.w3.org/2000/svg">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" />
                                <polyline points="14,2 14,8 20,8" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <?php echo esc_html__('Manage Jobs', 'careernest'); ?>
                        </button>
                        <button type="button" class="cn-quick-action" id="cn-show-all-applications">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                xmlns="http://www.w3.org/2000/svg">
                                <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" />
                                <rect x="8" y="2" width="8" height="4" rx="1" ry="1" stroke="currentColor"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <?php echo esc_html__('View Applications', 'careernest'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
get_footer();
