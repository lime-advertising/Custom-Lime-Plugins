<?php

/**
 * Template: CareerNest — Applicant Dashboard
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cn_update_profile_nonce'])) {
    if (!wp_verify_nonce($_POST['cn_update_profile_nonce'], 'cn_update_profile')) {
        $profile_errors[] = 'Security verification failed. Please try again.';
    } else {
        $result = process_profile_update();
        if ($result['success']) {
            $profile_updated = true;
            $profile_success = $result['message'];
        } else {
            $profile_errors = $result['errors'];
        }
    }
}

/**
 * Process profile update
 */
function process_profile_update()
{
    global $current_user;

    $errors = [];

    // Sanitize form data
    $full_name = sanitize_text_field($_POST['full_name'] ?? '');
    $professional_title = sanitize_text_field($_POST['professional_title'] ?? '');
    $phone = sanitize_text_field($_POST['phone'] ?? '');
    $location = sanitize_text_field($_POST['location'] ?? '');
    $right_to_work = sanitize_text_field($_POST['right_to_work'] ?? '');
    $work_types = isset($_POST['work_types']) ? array_map('sanitize_text_field', $_POST['work_types']) : [];
    $available_for_work = isset($_POST['available_for_work']) ? 1 : 0;
    $skills_input = sanitize_text_field($_POST['skills_input'] ?? '');
    $personal_summary = wp_kses_post($_POST['personal_summary'] ?? '');
    $linkedin_url = esc_url_raw($_POST['linkedin_url'] ?? '');

    // Process education data
    $education_data = [];
    if (isset($_POST['education']) && is_array($_POST['education'])) {
        foreach ($_POST['education'] as $edu) {
            if (!empty($edu['institution']) || !empty($edu['certification'])) {
                $education_data[] = [
                    'institution' => sanitize_text_field($edu['institution'] ?? ''),
                    'certification' => sanitize_text_field($edu['certification'] ?? ''),
                    'end_date' => sanitize_text_field($edu['end_date'] ?? ''),
                    'complete' => isset($edu['complete']) ? 1 : 0
                ];
            }
        }
    }

    // Process work experience data
    $experience_data = [];
    if (isset($_POST['experience']) && is_array($_POST['experience'])) {
        foreach ($_POST['experience'] as $exp) {
            if (!empty($exp['company']) || !empty($exp['title'])) {
                $experience_data[] = [
                    'company' => sanitize_text_field($exp['company'] ?? ''),
                    'title' => sanitize_text_field($exp['title'] ?? ''),
                    'start_date' => sanitize_text_field($exp['start_date'] ?? ''),
                    'end_date' => sanitize_text_field($exp['end_date'] ?? ''),
                    'current' => isset($exp['current']) ? 1 : 0,
                    'description' => sanitize_textarea_field($exp['description'] ?? '')
                ];
            }
        }
    }

    // Process licenses data
    $licenses_data = [];
    if (isset($_POST['licenses']) && is_array($_POST['licenses'])) {
        foreach ($_POST['licenses'] as $license) {
            if (!empty($license['name'])) {
                $licenses_data[] = [
                    'name' => sanitize_text_field($license['name'] ?? ''),
                    'issuer' => sanitize_text_field($license['issuer'] ?? ''),
                    'issue_date' => sanitize_text_field($license['issue_date'] ?? ''),
                    'expiry_date' => sanitize_text_field($license['expiry_date'] ?? ''),
                    'credential_id' => sanitize_text_field($license['credential_id'] ?? '')
                ];
            }
        }
    }

    // Process links data
    $links_data = [];
    if (isset($_POST['links']) && is_array($_POST['links'])) {
        foreach ($_POST['links'] as $link) {
            if (!empty($link['url'])) {
                $links_data[] = [
                    'label' => sanitize_text_field($link['label'] ?? ''),
                    'url' => esc_url_raw($link['url'] ?? '')
                ];
            }
        }
    }

    // Validation
    if (empty($full_name)) {
        $errors[] = 'Full name is required.';
    }

    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }

    // Find applicant profile
    $applicant_query = new WP_Query([
        'post_type' => 'applicant',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'meta_query' => [
            [
                'key' => '_user_id',
                'value' => $current_user->ID,
                'compare' => '='
            ]
        ]
    ]);

    $applicant_id = 0;
    if ($applicant_query->have_posts()) {
        $applicant_id = $applicant_query->posts[0]->ID;
    }

    if (!$applicant_id) {
        return ['success' => false, 'errors' => ['Profile not found.']];
    }

    // Update user display name
    wp_update_user([
        'ID' => $current_user->ID,
        'display_name' => $full_name,
        'first_name' => explode(' ', $full_name)[0],
        'last_name' => substr($full_name, strlen(explode(' ', $full_name)[0]) + 1),
    ]);

    // Update applicant profile
    wp_update_post([
        'ID' => $applicant_id,
        'post_title' => $full_name
    ]);

    // Update applicant post content (personal summary)
    wp_update_post([
        'ID' => $applicant_id,
        'post_title' => $full_name,
        'post_content' => $personal_summary
    ]);

    // Update meta fields
    update_post_meta($applicant_id, '_professional_title', $professional_title);
    update_post_meta($applicant_id, '_phone', $phone);
    update_post_meta($applicant_id, '_location', $location);
    update_post_meta($applicant_id, '_right_to_work', $right_to_work);
    update_post_meta($applicant_id, '_work_types', $work_types);
    update_post_meta($applicant_id, '_available_for_work', $available_for_work);
    update_post_meta($applicant_id, '_linkedin_url', $linkedin_url);
    update_post_meta($applicant_id, '_education', $education_data);
    update_post_meta($applicant_id, '_experience', $experience_data);
    update_post_meta($applicant_id, '_licenses', $licenses_data);
    update_post_meta($applicant_id, '_links', $links_data);

    // Handle skills
    if ($skills_input) {
        $skills = array_map('trim', explode(',', $skills_input));
        $skills = array_filter($skills, function ($skill) {
            return !empty($skill);
        });
        update_post_meta($applicant_id, '_skills', $skills);
    }

    return [
        'success' => true,
        'message' => 'Your profile has been updated successfully!'
    ];
}

// Get current user and applicant profile
$current_user = wp_get_current_user();
$user_roles = (array) $current_user->roles;

// Verify user has applicant role
if (!in_array('applicant', $user_roles, true)) {
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

// Find applicant profile
$applicant_query = new WP_Query([
    'post_type' => 'applicant',
    'post_status' => 'publish',
    'posts_per_page' => 1,
    'meta_query' => [
        [
            'key' => '_user_id',
            'value' => $current_user->ID,
            'compare' => '='
        ]
    ]
]);

$applicant_profile = null;
$applicant_id = 0;
if ($applicant_query->have_posts()) {
    $applicant_profile = $applicant_query->posts[0];
    $applicant_id = $applicant_profile->ID;
}

// Get applicant data
$prof_title = $applicant_id ? get_post_meta($applicant_id, '_professional_title', true) : '';
$phone = $applicant_id ? get_post_meta($applicant_id, '_phone', true) : '';
$location = $applicant_id ? get_post_meta($applicant_id, '_location', true) : '';
$available_for_work = $applicant_id ? get_post_meta($applicant_id, '_available_for_work', true) : false;
$skills = $applicant_id ? get_post_meta($applicant_id, '_skills', true) : [];
$work_types = $applicant_id ? get_post_meta($applicant_id, '_work_types', true) : [];
$education = $applicant_id ? get_post_meta($applicant_id, '_education', true) : [];
$experience = $applicant_id ? get_post_meta($applicant_id, '_experience', true) : [];
$licenses = $applicant_id ? get_post_meta($applicant_id, '_licenses', true) : [];
$links = $applicant_id ? get_post_meta($applicant_id, '_links', true) : [];
$linkedin_url = $applicant_id ? get_post_meta($applicant_id, '_linkedin_url', true) : '';

// Get personal summary from post content
$personal_summary = '';
if ($applicant_profile) {
    $personal_summary = $applicant_profile->post_content;
}

// Ensure arrays are properly formatted
$skills = is_array($skills) ? $skills : [];
$work_types = is_array($work_types) ? $work_types : [];
$education = is_array($education) ? $education : [];
$experience = is_array($experience) ? $experience : [];
$licenses = is_array($licenses) ? $licenses : [];
$links = is_array($links) ? $links : [];

// Get user's applications
$applications_query = new WP_Query([
    'post_type' => 'job_application',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'meta_query' => [
        [
            'key' => '_user_id',
            'value' => $current_user->ID,
            'compare' => '='
        ]
    ],
    'orderby' => 'date',
    'order' => 'DESC'
]);

// Get application statistics
$total_applications = $applications_query->found_posts;
$status_counts = [
    'new' => 0,
    'interviewed' => 0,
    'offer_extended' => 0,
    'hired' => 0,
    'rejected' => 0,
    'archived' => 0
];

if ($applications_query->have_posts()) {
    foreach ($applications_query->posts as $app) {
        $status = get_post_meta($app->ID, '_app_status', true) ?: 'new';
        if (isset($status_counts[$status])) {
            $status_counts[$status]++;
        }
    }
}

// Get recommended jobs based on user's profile
$recommended_jobs = [];
if ($applicant_id && ($skills || $work_types)) {
    $job_args = [
        'post_type' => 'job_listing',
        'post_status' => 'publish',
        'posts_per_page' => 5,
        'orderby' => 'date',
        'order' => 'DESC'
    ];

    $recommended_query = new WP_Query($job_args);
    if ($recommended_query->have_posts()) {
        $recommended_jobs = $recommended_query->posts;
    }
    wp_reset_postdata();
}
?>

<main id="primary" class="site-main">
    <div class="cn-dashboard-container">
        <!-- Dashboard Header -->
        <div class="cn-dashboard-header">
            <div class="cn-header-content">
                <div class="cn-user-info">
                    <h1><?php echo esc_html__('Welcome back,', 'careernest'); ?>
                        <?php echo esc_html($current_user->display_name); ?>!</h1>
                    <?php if ($prof_title): ?>
                        <p class="cn-user-title"><?php echo esc_html($prof_title); ?></p>
                    <?php endif; ?>
                    <?php if ($location): ?>
                        <p class="cn-user-location">📍 <?php echo esc_html($location); ?></p>
                    <?php endif; ?>
                </div>
                <div class="cn-header-actions">
                    <?php if ($applicant_id): ?>
                        <a href="<?php echo esc_url(get_permalink($applicant_id)); ?>" target="_blank"
                            class="cn-btn cn-btn-secondary">
                            <?php echo esc_html__('View Public Profile', 'careernest'); ?>
                        </a>
                    <?php endif; ?>
                    <button type="button" class="cn-btn cn-btn-primary" id="cn-toggle-edit">
                        <span class="cn-edit-text">Edit Profile</span>
                        <span class="cn-cancel-text" style="display: none;">Cancel Edit</span>
                    </button>
                    <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="cn-btn cn-btn-outline">Logout</a>
                </div>
            </div>
        </div>

        <!-- Dashboard Stats -->
        <div class="cn-dashboard-stats">
            <div class="cn-stat-card">
                <div class="cn-stat-number"><?php echo esc_html($total_applications); ?></div>
                <div class="cn-stat-label"><?php echo esc_html__('Total Applications', 'careernest'); ?></div>
            </div>
            <div class="cn-stat-card">
                <div class="cn-stat-number">
                    <?php echo esc_html($status_counts['new'] + $status_counts['interviewed']); ?></div>
                <div class="cn-stat-label"><?php echo esc_html__('Active Applications', 'careernest'); ?></div>
            </div>
            <div class="cn-stat-card">
                <div class="cn-stat-number"><?php echo esc_html($status_counts['interviewed']); ?></div>
                <div class="cn-stat-label"><?php echo esc_html__('Interviews', 'careernest'); ?></div>
            </div>
            <div class="cn-stat-card">
                <div class="cn-stat-number"><?php echo esc_html($status_counts['hired']); ?></div>
                <div class="cn-stat-label"><?php echo esc_html__('Offers/Hired', 'careernest'); ?></div>
            </div>
        </div>

        <!-- Dashboard Content -->
        <div class="cn-dashboard-content">
            <!-- Left Column -->
            <div class="cn-dashboard-main">
                <!-- Recent Applications -->
                <div class="cn-dashboard-section">
                    <div class="cn-section-header">
                        <h2><?php echo esc_html__('Your Applications', 'careernest'); ?></h2>
                        <?php
                        $pages = get_option('careernest_pages', []);
                        $jobs_page_id = isset($pages['jobs']) ? (int) $pages['jobs'] : 0;
                        if ($jobs_page_id && get_post_status($jobs_page_id) === 'publish'):
                        ?>
                            <a href="<?php echo esc_url(get_permalink($jobs_page_id)); ?>"
                                class="cn-btn cn-btn-primary">Browse Jobs</a>
                        <?php endif; ?>
                    </div>

                    <?php if ($applications_query->have_posts()): ?>
                        <div class="cn-applications-list">
                            <?php while ($applications_query->have_posts()): $applications_query->the_post();
                                $app_id = get_the_ID();
                                $job_id = get_post_meta($app_id, '_job_id', true);
                                $app_status = get_post_meta($app_id, '_app_status', true) ?: 'new';
                                $app_date = get_post_meta($app_id, '_application_date', true);
                                $resume_id = get_post_meta($app_id, '_resume_id', true);

                                $job_title = $job_id ? get_the_title($job_id) : 'Unknown Job';
                                $employer_id = $job_id ? get_post_meta($job_id, '_employer_id', true) : 0;
                                $company_name = $employer_id ? get_the_title($employer_id) : '';

                                $status_labels = [
                                    'new' => 'New',
                                    'interviewed' => 'Interviewed',
                                    'offer_extended' => 'Offer Extended',
                                    'hired' => 'Hired',
                                    'rejected' => 'Rejected',
                                    'archived' => 'Archived'
                                ];

                                $status_colors = [
                                    'new' => '#0073aa',
                                    'interviewed' => '#f39c12',
                                    'offer_extended' => '#27ae60',
                                    'hired' => '#10B981',
                                    'rejected' => '#e74c3c',
                                    'archived' => '#95a5a6'
                                ];
                            ?>
                                <div class="cn-application-card">
                                    <div class="cn-app-header">
                                        <div class="cn-app-job-info">
                                            <h3>
                                                <?php if ($job_id && get_post_status($job_id) === 'publish'): ?>
                                                    <a
                                                        href="<?php echo esc_url(get_permalink($job_id)); ?>"><?php echo esc_html($job_title); ?></a>
                                                <?php else: ?>
                                                    <?php echo esc_html($job_title); ?>
                                                <?php endif; ?>
                                            </h3>
                                            <?php if ($company_name): ?>
                                                <p class="cn-app-company"><?php echo esc_html($company_name); ?></p>
                                            <?php endif; ?>
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
                                        <?php if (current_user_can('edit_post', $app_id)): ?>
                                            <a href="<?php echo esc_url(get_edit_post_link($app_id)); ?>"
                                                class="cn-btn cn-btn-small cn-btn-outline">View Details</a>
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
                                    <path d="M20 6L9 17L4 12" stroke="#ccc" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round" />
                                </svg>
                            </div>
                            <h3><?php echo esc_html__('No Applications Yet', 'careernest'); ?></h3>
                            <p><?php echo esc_html__('You haven\'t applied for any jobs yet. Start browsing available positions!', 'careernest'); ?>
                            </p>
                            <?php if ($jobs_page_id && get_post_status($jobs_page_id) === 'publish'): ?>
                                <a href="<?php echo esc_url(get_permalink($jobs_page_id)); ?>"
                                    class="cn-btn cn-btn-primary">Browse Jobs</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Profile Sections -->
                <!-- Personal Summary -->
                <?php if ($personal_summary): ?>
                    <div class="cn-dashboard-section">
                        <h3><?php echo esc_html__('Personal Summary', 'careernest'); ?></h3>
                        <div class="cn-summary-content">
                            <?php echo wp_kses_post(wpautop($personal_summary)); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Work Experience -->
                <?php if ($experience && !empty($experience)): ?>
                    <div class="cn-dashboard-section">
                        <h3><?php echo esc_html__('Work Experience', 'careernest'); ?></h3>
                        <div class="cn-experience-list">
                            <?php foreach (array_slice($experience, 0, 5) as $exp):
                                $company = isset($exp['company']) ? $exp['company'] : '';
                                $title = isset($exp['title']) ? $exp['title'] : '';
                                $start_date = isset($exp['start_date']) ? $exp['start_date'] : '';
                                $end_date = isset($exp['end_date']) ? $exp['end_date'] : '';
                                $current = isset($exp['current']) ? $exp['current'] : false;
                                $description = isset($exp['description']) ? $exp['description'] : '';

                                $date_range = '';
                                if ($start_date) {
                                    $date_range = date('M Y', strtotime($start_date));
                                    if ($current) {
                                        $date_range .= ' - Present';
                                    } elseif ($end_date) {
                                        $date_range .= ' - ' . date('M Y', strtotime($end_date));
                                    }
                                }
                            ?>
                                <div class="cn-experience-item">
                                    <h4><?php echo esc_html($title); ?></h4>
                                    <p class="cn-exp-company"><?php echo esc_html($company); ?></p>
                                    <?php if ($date_range): ?>
                                        <p class="cn-exp-date"><?php echo esc_html($date_range); ?></p>
                                    <?php endif; ?>
                                    <?php if ($description): ?>
                                        <p class="cn-exp-description"><?php echo esc_html($description); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            <?php if (count($experience) > 5): ?>
                                <p class="cn-more-items">+<?php echo count($experience) - 5; ?> more positions</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Education -->
                <?php if ($education && !empty($education)): ?>
                    <div class="cn-dashboard-section">
                        <h3><?php echo esc_html__('Education', 'careernest'); ?></h3>
                        <div class="cn-education-list">
                            <?php foreach (array_slice($education, 0, 5) as $edu):
                                $institution = isset($edu['institution']) ? $edu['institution'] : '';
                                $certification = isset($edu['certification']) ? $edu['certification'] : '';
                                $end_date = isset($edu['end_date']) ? $edu['end_date'] : '';
                                $complete = isset($edu['complete']) ? $edu['complete'] : false;

                                $date_display = '';
                                if ($end_date) {
                                    $date_display = date('Y', strtotime($end_date));
                                }
                            ?>
                                <div class="cn-education-item">
                                    <h4><?php echo esc_html($certification); ?></h4>
                                    <p class="cn-edu-institution"><?php echo esc_html($institution); ?></p>
                                    <?php if ($date_display): ?>
                                        <p class="cn-edu-date"><?php echo esc_html($date_display); ?></p>
                                    <?php endif; ?>
                                    <?php if (!$complete): ?>
                                        <p class="cn-edu-status">In Progress</p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            <?php if (count($education) > 5): ?>
                                <p class="cn-more-items">+<?php echo count($education) - 5; ?> more qualifications</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Licenses & Certifications -->
                <?php if ($licenses && !empty($licenses)): ?>
                    <div class="cn-dashboard-section">
                        <h3><?php echo esc_html__('Licenses & Certifications', 'careernest'); ?></h3>
                        <div class="cn-licenses-list">
                            <?php foreach (array_slice($licenses, 0, 5) as $license):
                                $name = isset($license['name']) ? $license['name'] : '';
                                $issuer = isset($license['issuer']) ? $license['issuer'] : '';
                                $expiry_date = isset($license['expiry_date']) ? $license['expiry_date'] : '';
                                $credential_id = isset($license['credential_id']) ? $license['credential_id'] : '';

                                $expiry_display = '';
                                if ($expiry_date) {
                                    $expiry_display = 'Expires: ' . date('M Y', strtotime($expiry_date));
                                }
                            ?>
                                <div class="cn-license-item">
                                    <h4><?php echo esc_html($name); ?></h4>
                                    <p class="cn-license-issuer"><?php echo esc_html($issuer); ?></p>
                                    <?php if ($expiry_display): ?>
                                        <p class="cn-license-expiry"><?php echo esc_html($expiry_display); ?></p>
                                    <?php endif; ?>
                                    <?php if ($credential_id): ?>
                                        <p class="cn-license-credential">ID: <?php echo esc_html($credential_id); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            <?php if (count($licenses) > 5): ?>
                                <p class="cn-more-items">+<?php echo count($licenses) - 5; ?> more certifications</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Skills -->
                <?php if ($skills && is_array($skills) && !empty($skills)): ?>
                    <div class="cn-dashboard-section">
                        <h3><?php echo esc_html__('Your Skills', 'careernest'); ?></h3>
                        <div class="cn-skills-list">
                            <?php foreach (array_slice($skills, 0, 15) as $skill): ?>
                                <span class="cn-skill-tag"><?php echo esc_html($skill); ?></span>
                            <?php endforeach; ?>
                            <?php if (count($skills) > 15): ?>
                                <span class="cn-skill-more">+<?php echo count($skills) - 15; ?> more</span>
                            <?php endif; ?>
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

                <!-- Profile Edit Form (Hidden by default) -->
                <div class="cn-profile-edit-form" id="cn-profile-edit-form" style="display: none;">
                    <form method="post" class="cn-profile-form">
                        <?php wp_nonce_field('cn_update_profile', 'cn_update_profile_nonce'); ?>

                        <!-- Basic Profile Fields -->
                        <div class="cn-dashboard-section">
                            <h3><?php echo esc_html__('Basic Information', 'careernest'); ?></h3>

                            <div class="cn-form-field">
                                <label for="full_name"><?php echo esc_html__('Full Name', 'careernest'); ?> <span
                                        class="required">*</span></label>
                                <input type="text" id="full_name" name="full_name"
                                    value="<?php echo esc_attr($current_user->display_name); ?>" required
                                    class="cn-input">
                            </div>

                            <div class="cn-form-field">
                                <label
                                    for="professional_title"><?php echo esc_html__('Professional Title', 'careernest'); ?></label>
                                <input type="text" id="professional_title" name="professional_title"
                                    value="<?php echo esc_attr($prof_title); ?>" class="cn-input"
                                    placeholder="e.g., Senior Software Engineer">
                            </div>

                            <div class="cn-form-field">
                                <label for="phone"><?php echo esc_html__('Phone Number', 'careernest'); ?></label>
                                <input type="tel" id="phone" name="phone" value="<?php echo esc_attr($phone); ?>"
                                    class="cn-input">
                            </div>

                            <div class="cn-form-field">
                                <label for="location"><?php echo esc_html__('Location', 'careernest'); ?></label>
                                <input type="text" id="location" name="location"
                                    value="<?php echo esc_attr($location); ?>" class="cn-input"
                                    placeholder="e.g., Melbourne, VIC">
                            </div>

                            <div class="cn-form-field">
                                <label
                                    for="right_to_work"><?php echo esc_html__('Right to Work', 'careernest'); ?></label>
                                <select id="right_to_work" name="right_to_work" class="cn-input">
                                    <option value="foreign"
                                        <?php selected(get_post_meta($applicant_id, '_right_to_work', true), 'foreign'); ?>>
                                        <?php echo esc_html__('Foreign Citizen', 'careernest'); ?></option>
                                    <option value="australian"
                                        <?php selected(get_post_meta($applicant_id, '_right_to_work', true), 'australian'); ?>>
                                        <?php echo esc_html__('Australian Citizen', 'careernest'); ?></option>
                                </select>
                            </div>

                            <div class="cn-form-field">
                                <label><?php echo esc_html__('Work Preferences', 'careernest'); ?></label>
                                <div class="cn-checkbox-group">
                                    <?php
                                    $work_type_options = [
                                        'full_time' => 'Full-time',
                                        'part_time' => 'Part-time',
                                        'contract' => 'Contract',
                                        'temporary' => 'Temporary',
                                        'internship' => 'Internship',
                                        'remote' => 'Remote',
                                        'on_site' => 'On-site',
                                        'hybrid' => 'Hybrid'
                                    ];
                                    foreach ($work_type_options as $value => $label):
                                    ?>
                                        <label class="cn-checkbox-label">
                                            <input type="checkbox" name="work_types[]"
                                                value="<?php echo esc_attr($value); ?>"
                                                <?php checked(in_array($value, $work_types)); ?> class="cn-checkbox">
                                            <span><?php echo esc_html($label); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="cn-form-field">
                                <label for="skills_input"><?php echo esc_html__('Skills', 'careernest'); ?></label>
                                <input type="text" id="skills_input" name="skills_input"
                                    value="<?php echo esc_attr(is_array($skills) ? implode(', ', $skills) : ''); ?>"
                                    class="cn-input" placeholder="e.g., PHP, JavaScript, Project Management">
                                <p class="cn-field-help">
                                    <?php echo esc_html__('Separate skills with commas', 'careernest'); ?></p>
                            </div>

                            <div class="cn-form-field">
                                <label class="cn-checkbox-label">
                                    <input type="checkbox" name="available_for_work" value="1"
                                        <?php checked($available_for_work); ?> class="cn-checkbox">
                                    <span><?php echo esc_html__('Available for Work', 'careernest'); ?></span>
                                </label>
                            </div>
                        </div>

                        <!-- Personal Summary Edit -->
                        <div class="cn-dashboard-section">
                            <h3><?php echo esc_html__('Personal Summary', 'careernest'); ?></h3>
                            <div class="cn-form-field">
                                <label
                                    for="personal_summary"><?php echo esc_html__('Personal Summary', 'careernest'); ?></label>
                                <textarea id="personal_summary" name="personal_summary" rows="6" class="cn-input"
                                    placeholder="Brief summary about yourself and your career goals..."><?php echo esc_textarea($personal_summary); ?></textarea>
                            </div>
                        </div>

                        <!-- LinkedIn Edit -->
                        <div class="cn-dashboard-section">
                            <h3><?php echo esc_html__('LinkedIn Profile', 'careernest'); ?></h3>
                            <div class="cn-form-field">
                                <label
                                    for="linkedin_url"><?php echo esc_html__('LinkedIn URL', 'careernest'); ?></label>
                                <input type="url" id="linkedin_url" name="linkedin_url"
                                    value="<?php echo esc_attr($linkedin_url); ?>" class="cn-input"
                                    placeholder="https://linkedin.com/in/yourprofile">
                            </div>
                        </div>

                        <!-- Education Edit -->
                        <div class="cn-dashboard-section">
                            <h3><?php echo esc_html__('Education', 'careernest'); ?></h3>
                            <div id="cn-education-fields">
                                <?php if (!empty($education)): ?>
                                    <?php foreach ($education as $index => $edu): ?>
                                        <div class="cn-repeater-item" data-index="<?php echo $index; ?>">
                                            <div class="cn-form-field">
                                                <label><?php echo esc_html__('Institution', 'careernest'); ?></label>
                                                <input type="text" name="education[<?php echo $index; ?>][institution]"
                                                    value="<?php echo esc_attr($edu['institution'] ?? ''); ?>" class="cn-input">
                                            </div>
                                            <div class="cn-form-field">
                                                <label><?php echo esc_html__('Degree/Certification', 'careernest'); ?></label>
                                                <input type="text" name="education[<?php echo $index; ?>][certification]"
                                                    value="<?php echo esc_attr($edu['certification'] ?? ''); ?>"
                                                    class="cn-input">
                                            </div>
                                            <div class="cn-form-field">
                                                <label><?php echo esc_html__('Completion Date', 'careernest'); ?></label>
                                                <input type="month" name="education[<?php echo $index; ?>][end_date]"
                                                    value="<?php echo esc_attr($edu['end_date'] ?? ''); ?>" class="cn-input">
                                            </div>
                                            <div class="cn-form-field">
                                                <label class="cn-checkbox-label">
                                                    <input type="checkbox" name="education[<?php echo $index; ?>][complete]"
                                                        value="1" <?php checked($edu['complete'] ?? false); ?>
                                                        class="cn-checkbox">
                                                    <span><?php echo esc_html__('Completed', 'careernest'); ?></span>
                                                </label>
                                            </div>
                                            <button type="button"
                                                class="cn-btn cn-btn-small cn-btn-outline cn-remove-item"><?php echo esc_html__('Remove', 'careernest'); ?></button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="cn-repeater-item" data-index="0">
                                        <div class="cn-form-field">
                                            <label><?php echo esc_html__('Institution', 'careernest'); ?></label>
                                            <input type="text" name="education[0][institution]" class="cn-input">
                                        </div>
                                        <div class="cn-form-field">
                                            <label><?php echo esc_html__('Degree/Certification', 'careernest'); ?></label>
                                            <input type="text" name="education[0][certification]" class="cn-input">
                                        </div>
                                        <div class="cn-form-field">
                                            <label><?php echo esc_html__('Completion Date', 'careernest'); ?></label>
                                            <input type="month" name="education[0][end_date]" class="cn-input">
                                        </div>
                                        <div class="cn-form-field">
                                            <label class="cn-checkbox-label">
                                                <input type="checkbox" name="education[0][complete]" value="1"
                                                    class="cn-checkbox">
                                                <span><?php echo esc_html__('Completed', 'careernest'); ?></span>
                                            </label>
                                        </div>
                                        <button type="button"
                                            class="cn-btn cn-btn-small cn-btn-outline cn-remove-item"><?php echo esc_html__('Remove', 'careernest'); ?></button>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="cn-btn cn-btn-small cn-btn-outline"
                                id="cn-add-education"><?php echo esc_html__('Add Education', 'careernest'); ?></button>
                        </div>

                        <!-- Work Experience Edit -->
                        <div class="cn-dashboard-section">
                            <h3><?php echo esc_html__('Work Experience', 'careernest'); ?></h3>
                            <div id="cn-experience-fields">
                                <?php if (!empty($experience)): ?>
                                    <?php foreach ($experience as $index => $exp): ?>
                                        <div class="cn-repeater-item" data-index="<?php echo $index; ?>">
                                            <div class="cn-form-field">
                                                <label><?php echo esc_html__('Company', 'careernest'); ?></label>
                                                <input type="text" name="experience[<?php echo $index; ?>][company]"
                                                    value="<?php echo esc_attr($exp['company'] ?? ''); ?>" class="cn-input">
                                            </div>
                                            <div class="cn-form-field">
                                                <label><?php echo esc_html__('Job Title', 'careernest'); ?></label>
                                                <input type="text" name="experience[<?php echo $index; ?>][title]"
                                                    value="<?php echo esc_attr($exp['title'] ?? ''); ?>" class="cn-input">
                                            </div>
                                            <div class="cn-form-field">
                                                <label><?php echo esc_html__('Start Date', 'careernest'); ?></label>
                                                <input type="month" name="experience[<?php echo $index; ?>][start_date]"
                                                    value="<?php echo esc_attr($exp['start_date'] ?? ''); ?>" class="cn-input">
                                            </div>
                                            <div class="cn-form-field">
                                                <label><?php echo esc_html__('End Date', 'careernest'); ?></label>
                                                <input type="month" name="experience[<?php echo $index; ?>][end_date]"
                                                    value="<?php echo esc_attr($exp['end_date'] ?? ''); ?>"
                                                    class="cn-input cn-end-date">
                                            </div>
                                            <div class="cn-form-field">
                                                <label class="cn-checkbox-label">
                                                    <input type="checkbox" name="experience[<?php echo $index; ?>][current]"
                                                        value="1" <?php checked($exp['current'] ?? false); ?>
                                                        class="cn-checkbox cn-current-job">
                                                    <span><?php echo esc_html__('Current Position', 'careernest'); ?></span>
                                                </label>
                                            </div>
                                            <div class="cn-form-field">
                                                <label><?php echo esc_html__('Description', 'careernest'); ?></label>
                                                <textarea name="experience[<?php echo $index; ?>][description]" rows="4"
                                                    class="cn-input"><?php echo esc_textarea($exp['description'] ?? ''); ?></textarea>
                                            </div>
                                            <button type="button"
                                                class="cn-btn cn-btn-small cn-btn-outline cn-remove-item"><?php echo esc_html__('Remove', 'careernest'); ?></button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="cn-repeater-item" data-index="0">
                                        <div class="cn-form-field">
                                            <label><?php echo esc_html__('Company', 'careernest'); ?></label>
                                            <input type="text" name="experience[0][company]" class="cn-input">
                                        </div>
                                        <div class="cn-form-field">
                                            <label><?php echo esc_html__('Job Title', 'careernest'); ?></label>
                                            <input type="text" name="experience[0][title]" class="cn-input">
                                        </div>
                                        <div class="cn-form-field">
                                            <label><?php echo esc_html__('Start Date', 'careernest'); ?></label>
                                            <input type="month" name="experience[0][start_date]" class="cn-input">
                                        </div>
                                        <div class="cn-form-field">
                                            <label><?php echo esc_html__('End Date', 'careernest'); ?></label>
                                            <input type="month" name="experience[0][end_date]" class="cn-input cn-end-date">
                                        </div>
                                        <div class="cn-form-field">
                                            <label class="cn-checkbox-label">
                                                <input type="checkbox" name="experience[0][current]" value="1"
                                                    class="cn-checkbox cn-current-job">
                                                <span><?php echo esc_html__('Current Position', 'careernest'); ?></span>
                                            </label>
                                        </div>
                                        <div class="cn-form-field">
                                            <label><?php echo esc_html__('Description', 'careernest'); ?></label>
                                            <textarea name="experience[0][description]" rows="4"
                                                class="cn-input"></textarea>
                                        </div>
                                        <button type="button"
                                            class="cn-btn cn-btn-small cn-btn-outline cn-remove-item"><?php echo esc_html__('Remove', 'careernest'); ?></button>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="cn-btn cn-btn-small cn-btn-outline"
                                id="cn-add-experience"><?php echo esc_html__('Add Experience', 'careernest'); ?></button>
                        </div>

                        <!-- Licenses Edit -->
                        <div class="cn-dashboard-section">
                            <h3><?php echo esc_html__('Licenses & Certifications', 'careernest'); ?></h3>
                            <div id="cn-licenses-fields">
                                <?php if (!empty($licenses)): ?>
                                    <?php foreach ($licenses as $index => $license): ?>
                                        <div class="cn-repeater-item" data-index="<?php echo $index; ?>">
                                            <div class="cn-form-field">
                                                <label><?php echo esc_html__('Name', 'careernest'); ?></label>
                                                <input type="text" name="licenses[<?php echo $index; ?>][name]"
                                                    value="<?php echo esc_attr($license['name'] ?? ''); ?>" class="cn-input">
                                            </div>
                                            <div class="cn-form-field">
                                                <label><?php echo esc_html__('Issuing Organization', 'careernest'); ?></label>
                                                <input type="text" name="licenses[<?php echo $index; ?>][issuer]"
                                                    value="<?php echo esc_attr($license['issuer'] ?? ''); ?>" class="cn-input">
                                            </div>
                                            <div class="cn-form-field">
                                                <label><?php echo esc_html__('Issue Date', 'careernest'); ?></label>
                                                <input type="month" name="licenses[<?php echo $index; ?>][issue_date]"
                                                    value="<?php echo esc_attr($license['issue_date'] ?? ''); ?>"
                                                    class="cn-input">
                                            </div>
                                            <div class="cn-form-field">
                                                <label><?php echo esc_html__('Expiry Date', 'careernest'); ?></label>
                                                <input type="month" name="licenses[<?php echo $index; ?>][expiry_date]"
                                                    value="<?php echo esc_attr($license['expiry_date'] ?? ''); ?>"
                                                    class="cn-input">
                                            </div>
                                            <div class="cn-form-field">
                                                <label><?php echo esc_html__('Credential ID', 'careernest'); ?></label>
                                                <input type="text" name="licenses[<?php echo $index; ?>][credential_id]"
                                                    value="<?php echo esc_attr($license['credential_id'] ?? ''); ?>"
                                                    class="cn-input">
                                            </div>
                                            <button type="button"
                                                class="cn-btn cn-btn-small cn-btn-outline cn-remove-item"><?php echo esc_html__('Remove', 'careernest'); ?></button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="cn-repeater-item" data-index="0">
                                        <div class="cn-form-field">
                                            <label><?php echo esc_html__('Name', 'careernest'); ?></label>
                                            <input type="text" name="licenses[0][name]" class="cn-input">
                                        </div>
                                        <div class="cn-form-field">
                                            <label><?php echo esc_html__('Issuing Organization', 'careernest'); ?></label>
                                            <input type="text" name="licenses[0][issuer]" class="cn-input">
                                        </div>
                                        <div class="cn-form-field">
                                            <label><?php echo esc_html__('Issue Date', 'careernest'); ?></label>
                                            <input type="month" name="licenses[0][issue_date]" class="cn-input">
                                        </div>
                                        <div class="cn-form-field">
                                            <label><?php echo esc_html__('Expiry Date', 'careernest'); ?></label>
                                            <input type="month" name="licenses[0][expiry_date]" class="cn-input">
                                        </div>
                                        <div class="cn-form-field">
                                            <label><?php echo esc_html__('Credential ID', 'careernest'); ?></label>
                                            <input type="text" name="licenses[0][credential_id]" class="cn-input">
                                        </div>
                                        <button type="button"
                                            class="cn-btn cn-btn-small cn-btn-outline cn-remove-item"><?php echo esc_html__('Remove', 'careernest'); ?></button>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="cn-btn cn-btn-small cn-btn-outline"
                                id="cn-add-license"><?php echo esc_html__('Add License/Certification', 'careernest'); ?></button>
                        </div>

                        <!-- Links Edit -->
                        <div class="cn-dashboard-section">
                            <h3><?php echo esc_html__('Websites & Social Profiles', 'careernest'); ?></h3>
                            <div id="cn-links-fields">
                                <?php if (!empty($links)): ?>
                                    <?php foreach ($links as $index => $link): ?>
                                        <div class="cn-repeater-item" data-index="<?php echo $index; ?>">
                                            <div class="cn-form-field">
                                                <label><?php echo esc_html__('Label', 'careernest'); ?></label>
                                                <input type="text" name="links[<?php echo $index; ?>][label]"
                                                    value="<?php echo esc_attr($link['label'] ?? ''); ?>" class="cn-input"
                                                    placeholder="e.g., Portfolio, GitHub, Twitter">
                                            </div>
                                            <div class="cn-form-field">
                                                <label><?php echo esc_html__('URL', 'careernest'); ?></label>
                                                <input type="url" name="links[<?php echo $index; ?>][url]"
                                                    value="<?php echo esc_attr($link['url'] ?? ''); ?>" class="cn-input"
                                                    placeholder="https://example.com">
                                            </div>
                                            <button type="button"
                                                class="cn-btn cn-btn-small cn-btn-outline cn-remove-item"><?php echo esc_html__('Remove', 'careernest'); ?></button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="cn-repeater-item" data-index="0">
                                        <div class="cn-form-field">
                                            <label><?php echo esc_html__('Label', 'careernest'); ?></label>
                                            <input type="text" name="links[0][label]" class="cn-input"
                                                placeholder="e.g., Portfolio, GitHub, Twitter">
                                        </div>
                                        <div class="cn-form-field">
                                            <label><?php echo esc_html__('URL', 'careernest'); ?></label>
                                            <input type="url" name="links[0][url]" class="cn-input"
                                                placeholder="https://example.com">
                                        </div>
                                        <button type="button"
                                            class="cn-btn cn-btn-small cn-btn-outline cn-remove-item"><?php echo esc_html__('Remove', 'careernest'); ?></button>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="cn-btn cn-btn-small cn-btn-outline"
                                id="cn-add-link"><?php echo esc_html__('Add Website/Link', 'careernest'); ?></button>
                        </div>

                        <!-- Form Actions -->
                        <div class="cn-dashboard-section">
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

            <!-- Right Sidebar -->
            <div class="cn-dashboard-sidebar">
                <!-- Work Preferences -->
                <?php if ($work_types && is_array($work_types) && !empty($work_types)): ?>
                    <div class="cn-dashboard-section">
                        <h3><?php echo esc_html__('Work Preferences', 'careernest'); ?></h3>
                        <div class="cn-work-types">
                            <?php foreach ($work_types as $type): ?>
                                <span
                                    class="cn-work-type-tag"><?php echo esc_html(ucfirst(str_replace('_', ' ', $type))); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Websites & Social Profiles -->
                <?php if (($links && !empty($links)) || $linkedin_url): ?>
                    <div class="cn-dashboard-section">
                        <h3><?php echo esc_html__('Websites & Social Profiles', 'careernest'); ?></h3>
                        <div class="cn-links-list">
                            <?php if ($linkedin_url): ?>
                                <div class="cn-link-item">
                                    <a href="<?php echo esc_url($linkedin_url); ?>" target="_blank" rel="noopener noreferrer">
                                        <strong>LinkedIn</strong>
                                    </a>
                                </div>
                            <?php endif; ?>
                            <?php foreach (array_slice($links, 0, 5) as $link):
                                $label = isset($link['label']) ? $link['label'] : '';
                                $url = isset($link['url']) ? $link['url'] : '';
                            ?>
                                <?php if ($url): ?>
                                    <div class="cn-link-item">
                                        <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer">
                                            <strong><?php echo esc_html($label ?: 'Website'); ?></strong>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <?php if (count($links) > 5): ?>
                                <p class="cn-more-items">+<?php echo count($links) - 5; ?> more links</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Recommended Jobs -->
                <?php if (!empty($recommended_jobs)): ?>
                    <div class="cn-dashboard-section">
                        <h3><?php echo esc_html__('Recommended Jobs', 'careernest'); ?></h3>
                        <div class="cn-recommended-jobs">
                            <?php foreach ($recommended_jobs as $job):
                                $job_employer_id = get_post_meta($job->ID, '_employer_id', true);
                                $job_company = $job_employer_id ? get_the_title($job_employer_id) : '';
                                $job_location = get_post_meta($job->ID, '_job_location', true);
                            ?>
                                <div class="cn-recommended-job">
                                    <h4><a
                                            href="<?php echo esc_url(get_permalink($job->ID)); ?>"><?php echo esc_html($job->post_title); ?></a>
                                    </h4>
                                    <?php if ($job_company): ?>
                                        <p class="cn-job-company"><?php echo esc_html($job_company); ?></p>
                                    <?php endif; ?>
                                    <?php if ($job_location): ?>
                                        <p class="cn-job-location">📍 <?php echo esc_html($job_location); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($jobs_page_id && get_post_status($jobs_page_id) === 'publish'): ?>
                            <a href="<?php echo esc_url(get_permalink($jobs_page_id)); ?>" class="cn-view-all-jobs">View All
                                Jobs →</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php
get_footer();
