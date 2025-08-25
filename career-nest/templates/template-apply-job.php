<?php

/**
 * Template: CareerNest — Apply for Job
 */

defined('ABSPATH') || exit;

get_header();

// Get job ID from query parameter
$job_id = isset($_GET['job_id']) ? absint($_GET['job_id']) : 0;
$job_post = $job_id ? get_post($job_id) : null;

// Validate job exists and is published
if (!$job_post || $job_post->post_type !== 'job_listing' || $job_post->post_status !== 'publish') {
?>
    <main id="primary" class="site-main">
        <div class="cn-apply-container">
            <div class="cn-apply-error">
                <h1><?php echo esc_html__('Job Not Found', 'careernest'); ?></h1>
                <p><?php echo esc_html__('The job you are trying to apply for could not be found or is no longer available.', 'careernest'); ?>
                </p>
                <?php
                $pages = get_option('careernest_pages', []);
                $jobs_page_id = isset($pages['jobs']) ? (int) $pages['jobs'] : 0;
                if ($jobs_page_id && get_post_status($jobs_page_id) === 'publish'):
                ?>
                    <a href="<?php echo esc_url(get_permalink($jobs_page_id)); ?>" class="cn-back-to-jobs">← Back to Job
                        Listings</a>
                <?php endif; ?>
            </div>
        </div>
    </main>
<?php
    get_footer();
    return;
}

// Get job details
$employer_id = get_post_meta($job_id, '_employer_id', true);
$company_name = $employer_id ? get_the_title($employer_id) : '';
$job_location = get_post_meta($job_id, '_job_location', true);
$closing_date = get_post_meta($job_id, '_closing_date', true);
$position_filled = get_post_meta($job_id, '_position_filled', true);

// Check if job is still accepting applications
$can_apply = true;
$apply_message = '';

if ($position_filled) {
    $can_apply = false;
    $apply_message = 'This position has been filled and is no longer accepting applications.';
} elseif ($closing_date) {
    $closing_timestamp = strtotime($closing_date . ' 23:59:59');
    if ($closing_timestamp < current_time('timestamp')) {
        $can_apply = false;
        $apply_message = 'The application deadline for this position has passed.';
    }
}

// Handle form submission
$form_submitted = false;
$form_errors = [];
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cn_apply_nonce'])) {
    // Verify nonce
    if (!wp_verify_nonce($_POST['cn_apply_nonce'], 'cn_apply_job_' . $job_id)) {
        $form_errors[] = 'Security verification failed. Please try again.';
    } else {
        // Process form submission
        $result = process_job_application($job_id);
        if ($result['success']) {
            $form_submitted = true;
            $success_message = $result['message'];
        } else {
            $form_errors = $result['errors'];
        }
    }
}

/**
 * Process job application submission
 */
function process_job_application($job_id)
{
    $errors = [];

    // Sanitize and validate form data
    $applicant_name = sanitize_text_field($_POST['applicant_name'] ?? '');
    $applicant_email = sanitize_email($_POST['applicant_email'] ?? '');
    $applicant_phone = sanitize_text_field($_POST['applicant_phone'] ?? '');
    $cover_letter = wp_kses_post($_POST['cover_letter'] ?? '');

    // Validation
    if (empty($applicant_name)) {
        $errors[] = 'Full name is required.';
    }

    if (empty($applicant_email) || !is_email($applicant_email)) {
        $errors[] = 'A valid email address is required.';
    }

    if (empty($cover_letter)) {
        $errors[] = 'Cover letter is required.';
    }

    // Handle resume upload
    $resume_id = 0;
    if (isset($_FILES['resume_file']) && $_FILES['resume_file']['error'] === UPLOAD_ERR_OK) {
        $upload_result = handle_resume_upload();
        if ($upload_result['success']) {
            $resume_id = $upload_result['attachment_id'];
        } else {
            $errors[] = $upload_result['error'];
        }
    } else {
        $errors[] = 'Resume file is required.';
    }

    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }

    // Find or create user account and applicant profile
    $user_data = find_or_create_applicant_account($applicant_email, $applicant_name, $applicant_phone);
    $user_id = $user_data['user_id'];
    $applicant_id = $user_data['applicant_id'];
    $is_new_user = $user_data['is_new_user'];

    // Create job application
    $application_id = wp_insert_post([
        'post_type' => 'job_application',
        'post_title' => $applicant_name . ' - ' . get_the_title($job_id),
        'post_status' => 'publish',
        'post_author' => $user_id ?: 1,
    ]);

    if (is_wp_error($application_id)) {
        return ['success' => false, 'errors' => ['Failed to create application. Please try again.']];
    }

    // Save application meta data
    update_post_meta($application_id, '_job_id', $job_id);
    update_post_meta($application_id, '_applicant_id', $applicant_id);
    update_post_meta($application_id, '_user_id', $user_id);
    update_post_meta($application_id, '_applicant_name', $applicant_name);
    update_post_meta($application_id, '_applicant_email', $applicant_email);
    update_post_meta($application_id, '_applicant_phone', $applicant_phone);
    update_post_meta($application_id, '_cover_letter', $cover_letter);
    update_post_meta($application_id, '_resume_id', $resume_id);
    update_post_meta($application_id, '_app_status', 'new');
    update_post_meta($application_id, '_application_date', current_time('mysql'));

    // Send notifications
    send_application_notifications($application_id, $job_id, $applicant_email, $is_new_user);

    $success_message = 'Your application has been submitted successfully!';
    if ($is_new_user) {
        $success_message .= ' We\'ve created an account for you and sent a password setup link to your email.';
    } else {
        $success_message .= ' You will receive a confirmation email shortly.';
    }

    return [
        'success' => true,
        'message' => $success_message
    ];
}

/**
 * Find or create user account and applicant profile
 */
function find_or_create_applicant_account($email, $name, $phone)
{
    // Check if user already exists
    $existing_user = get_user_by('email', $email);

    if ($existing_user) {
        // User exists, find their applicant profile
        $applicant_query = new \WP_Query([
            'post_type' => 'applicant',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'meta_query' => [
                [
                    'key' => '_user_id',
                    'value' => $existing_user->ID,
                    'compare' => '='
                ]
            ]
        ]);

        $applicant_id = 0;
        if ($applicant_query->have_posts()) {
            $applicant_id = $applicant_query->posts[0]->ID;
        } else {
            // Create applicant profile for existing user
            $applicant_id = wp_insert_post([
                'post_type' => 'applicant',
                'post_title' => $name,
                'post_status' => 'publish',
                'post_author' => $existing_user->ID,
            ]);

            if (!is_wp_error($applicant_id)) {
                update_post_meta($applicant_id, '_user_id', $existing_user->ID);
                update_post_meta($applicant_id, '_phone', $phone);
                update_post_meta($applicant_id, '_available_for_work', true);
            }
        }

        return [
            'user_id' => $existing_user->ID,
            'applicant_id' => $applicant_id,
            'is_new_user' => false
        ];
    }

    // Create new user account
    $user_id = wp_create_user($email, wp_generate_password(12, true, true), $email);

    if (is_wp_error($user_id)) {
        // If user creation fails, return system user
        return [
            'user_id' => 1,
            'applicant_id' => 0,
            'is_new_user' => false
        ];
    }

    // Update user profile
    wp_update_user([
        'ID' => $user_id,
        'display_name' => $name,
        'first_name' => explode(' ', $name)[0],
        'last_name' => substr($name, strlen(explode(' ', $name)[0]) + 1),
    ]);

    // Set applicant role
    $user = new WP_User($user_id);
    $user->set_role('applicant');

    // Create applicant profile
    $applicant_id = wp_insert_post([
        'post_type' => 'applicant',
        'post_title' => $name,
        'post_status' => 'publish',
        'post_author' => $user_id,
    ]);

    if (!is_wp_error($applicant_id)) {
        update_post_meta($applicant_id, '_user_id', $user_id);
        update_post_meta($applicant_id, '_phone', $phone);
        update_post_meta($applicant_id, '_available_for_work', true);
    }

    return [
        'user_id' => $user_id,
        'applicant_id' => $applicant_id,
        'is_new_user' => true
    ];
}

/**
 * Handle resume file upload
 */
function handle_resume_upload()
{
    $allowed_types = ['pdf', 'doc', 'docx'];
    $max_size = 5 * 1024 * 1024; // 5MB

    $file = $_FILES['resume_file'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    // Validate file type
    if (!in_array($file_ext, $allowed_types)) {
        return ['success' => false, 'error' => 'Resume must be a PDF, DOC, or DOCX file.'];
    }

    // Validate file size
    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'Resume file must be smaller than 5MB.'];
    }

    // Upload file
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');

    $upload = wp_handle_upload($file, ['test_form' => false]);

    if (isset($upload['error'])) {
        return ['success' => false, 'error' => 'Upload failed: ' . $upload['error']];
    }

    // Create attachment
    $attachment_id = wp_insert_attachment([
        'post_title' => sanitize_file_name(pathinfo($file['name'], PATHINFO_FILENAME)),
        'post_content' => '',
        'post_status' => 'inherit',
        'post_mime_type' => $upload['type'],
    ], $upload['file']);

    if (is_wp_error($attachment_id)) {
        return ['success' => false, 'error' => 'Failed to create attachment.'];
    }

    return ['success' => true, 'attachment_id' => $attachment_id];
}

/**
 * Send application notifications
 */
function send_application_notifications($application_id, $job_id, $applicant_email, $is_new_user = false)
{
    $job_title = get_the_title($job_id);
    $employer_id = get_post_meta($job_id, '_employer_id', true);
    $company_name = $employer_id ? get_the_title($employer_id) : '';
    $applicant_name = get_post_meta($application_id, '_applicant_name', true);

    // Send to applicant
    $applicant_subject = 'Application Received - ' . $job_title;
    $applicant_message = "Hi {$applicant_name},\n\n";
    $applicant_message .= "Thank you for applying to {$job_title}";
    if ($company_name) {
        $applicant_message .= " at {$company_name}";
    }
    $applicant_message .= ".\n\nYour application has been received and will be reviewed by our team.\n\n";

    if ($is_new_user) {
        // Send password reset link for new users
        $user = get_user_by('email', $applicant_email);
        if ($user) {
            $reset_key = get_password_reset_key($user);
            if (!is_wp_error($reset_key)) {
                $reset_url = network_site_url("wp-login.php?action=rp&key={$reset_key}&login=" . rawurlencode($user->user_login), 'login');

                $applicant_message .= "We've created an account for you to track your applications and apply for future positions.\n\n";
                $applicant_message .= "To set up your password and access your account, please click the link below:\n";
                $applicant_message .= $reset_url . "\n\n";
                $applicant_message .= "Once you set up your password, you can:\n";
                $applicant_message .= "- Track your application status\n";
                $applicant_message .= "- Apply for other positions\n";
                $applicant_message .= "- Update your professional profile\n";
                $applicant_message .= "- Upload additional documents\n\n";
            }
        }
    } else {
        $applicant_message .= "You can track your application status by logging into your CareerNest account.\n\n";
    }

    $applicant_message .= "Thank you for your interest in joining our team!";

    wp_mail($applicant_email, $applicant_subject, $applicant_message);

    // Send to employer/team
    $employer_emails = [];
    if ($employer_id) {
        // Get employer team emails
        $team_users = get_users([
            'role' => 'employer_team',
            'meta_key' => '_employer_id',
            'meta_value' => $employer_id,
            'fields' => ['user_email']
        ]);
        foreach ($team_users as $user) {
            $employer_emails[] = $user->user_email;
        }
    }

    if (!empty($employer_emails)) {
        $employer_subject = 'New Job Application - ' . $job_title;
        $employer_message = "A new application has been received for {$job_title}.\n\n";
        $employer_message .= "Applicant: {$applicant_name}\n";
        $employer_message .= "Email: {$applicant_email}\n";
        if ($is_new_user) {
            $employer_message .= "Status: New user account created\n";
        }
        $employer_message .= "\nPlease log in to your CareerNest dashboard to review the application.";

        foreach ($employer_emails as $email) {
            wp_mail($email, $employer_subject, $employer_message);
        }
    }
}
?>

<main id="primary" class="site-main">
    <div class="cn-apply-container">
        <?php if ($form_submitted): ?>
            <!-- Success Message -->
            <div class="cn-apply-success">
                <div class="cn-success-icon">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="12" cy="12" r="10" stroke="#10B981" stroke-width="2" />
                        <path d="m9 12 2 2 4-4" stroke="#10B981" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round" />
                    </svg>
                </div>
                <h1><?php echo esc_html__('Application Submitted!', 'careernest'); ?></h1>
                <p><?php echo esc_html($success_message); ?></p>
                <div class="cn-success-actions">
                    <a href="<?php echo esc_url(get_permalink($job_id)); ?>" class="cn-btn cn-btn-secondary">← Back to
                        Job</a>
                    <?php
                    $pages = get_option('careernest_pages', []);
                    $jobs_page_id = isset($pages['jobs']) ? (int) $pages['jobs'] : 0;
                    if ($jobs_page_id && get_post_status($jobs_page_id) === 'publish'):
                    ?>
                        <a href="<?php echo esc_url(get_permalink($jobs_page_id)); ?>" class="cn-btn cn-btn-primary">View More
                            Jobs</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php elseif (!$can_apply): ?>
            <!-- Cannot Apply Message -->
            <div class="cn-apply-error">
                <h1><?php echo esc_html__('Application Not Available', 'careernest'); ?></h1>
                <p><?php echo esc_html($apply_message); ?></p>
                <a href="<?php echo esc_url(get_permalink($job_id)); ?>" class="cn-btn cn-btn-secondary">← Back to Job</a>
            </div>
        <?php else: ?>
            <!-- Application Form -->
            <div class="cn-apply-form-container">
                <!-- Job Information Header -->
                <div class="cn-apply-job-info">
                    <h1><?php echo esc_html__('Apply for Position', 'careernest'); ?></h1>
                    <div class="cn-job-details">
                        <h2><?php echo esc_html(get_the_title($job_id)); ?></h2>
                        <?php if ($company_name): ?>
                            <p class="cn-company"><?php echo esc_html($company_name); ?></p>
                        <?php endif; ?>
                        <?php if ($job_location): ?>
                            <p class="cn-location">📍 <?php echo esc_html($job_location); ?></p>
                        <?php endif; ?>
                        <?php if ($closing_date): ?>
                            <p class="cn-deadline">⏰ Applications close:
                                <?php echo esc_html(date('F j, Y', strtotime($closing_date))); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Error Messages -->
                <?php if (!empty($form_errors)): ?>
                    <div class="cn-apply-errors">
                        <h3><?php echo esc_html__('Please correct the following errors:', 'careernest'); ?></h3>
                        <ul>
                            <?php foreach ($form_errors as $error): ?>
                                <li><?php echo esc_html($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- Application Form -->
                <form method="post" enctype="multipart/form-data" class="cn-apply-form">
                    <?php wp_nonce_field('cn_apply_job_' . $job_id, 'cn_apply_nonce'); ?>

                    <div class="cn-form-section">
                        <h3><?php echo esc_html__('Personal Information', 'careernest'); ?></h3>

                        <div class="cn-form-row">
                            <div class="cn-form-field">
                                <label for="applicant_name"><?php echo esc_html__('Full Name', 'careernest'); ?> <span
                                        class="required">*</span></label>
                                <input type="text" id="applicant_name" name="applicant_name"
                                    value="<?php echo esc_attr($_POST['applicant_name'] ?? ''); ?>" required
                                    class="cn-input">
                            </div>

                            <div class="cn-form-field">
                                <label for="applicant_email"><?php echo esc_html__('Email Address', 'careernest'); ?> <span
                                        class="required">*</span></label>
                                <input type="email" id="applicant_email" name="applicant_email"
                                    value="<?php echo esc_attr($_POST['applicant_email'] ?? ''); ?>" required
                                    class="cn-input">
                            </div>
                        </div>

                        <div class="cn-form-row">
                            <div class="cn-form-field">
                                <label for="applicant_phone"><?php echo esc_html__('Phone Number', 'careernest'); ?></label>
                                <input type="tel" id="applicant_phone" name="applicant_phone"
                                    value="<?php echo esc_attr($_POST['applicant_phone'] ?? ''); ?>" class="cn-input">
                            </div>
                        </div>
                    </div>

                    <div class="cn-form-section">
                        <h3><?php echo esc_html__('Application Materials', 'careernest'); ?></h3>

                        <div class="cn-form-field">
                            <label for="resume_file"><?php echo esc_html__('Resume/CV', 'careernest'); ?> <span
                                    class="required">*</span></label>
                            <input type="file" id="resume_file" name="resume_file" accept=".pdf,.doc,.docx" required
                                class="cn-file-input">
                            <p class="cn-field-help">
                                <?php echo esc_html__('Accepted formats: PDF, DOC, DOCX (Max 5MB)', 'careernest'); ?></p>
                        </div>

                        <div class="cn-form-field">
                            <label for="cover_letter"><?php echo esc_html__('Cover Letter', 'careernest'); ?> <span
                                    class="required">*</span></label>
                            <textarea id="cover_letter" name="cover_letter" rows="8" required class="cn-textarea"
                                placeholder="<?php echo esc_attr__('Tell us why you\'re interested in this position and what makes you a great fit...', 'careernest'); ?>"><?php echo esc_textarea($_POST['cover_letter'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <div class="cn-form-actions">
                        <button type="submit" class="cn-btn cn-btn-primary cn-btn-large">
                            <?php echo esc_html__('Submit Application', 'careernest'); ?>
                        </button>
                        <a href="<?php echo esc_url(get_permalink($job_id)); ?>" class="cn-btn cn-btn-secondary">
                            <?php echo esc_html__('Cancel', 'careernest'); ?>
                        </a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</main>

<style>
    /* Container */
    .site-main {
        max-width: 800px;
        margin: 0 auto;
        padding: 0 20px;
    }

    .cn-apply-container {
        margin: 2rem 0;
    }

    /* Job Information Header */
    .cn-apply-job-info {
        background: #f8f9fa;
        padding: 2rem;
        border-radius: 8px;
        margin-bottom: 2rem;
        border-left: 4px solid #0073aa;
    }

    .cn-apply-job-info h1 {
        color: #333;
        font-size: 1.5rem;
        margin: 0 0 1rem 0;
    }

    .cn-job-details h2 {
        color: #0073aa;
        font-size: 1.8rem;
        margin: 0 0 0.5rem 0;
    }

    .cn-company {
        color: #666;
        font-size: 1.1rem;
        margin: 0.25rem 0;
        font-weight: 500;
    }

    .cn-location,
    .cn-deadline {
        color: #666;
        font-size: 0.95rem;
        margin: 0.25rem 0;
    }

    /* Form Styling */
    .cn-apply-form-container {
        background: white;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 2rem;
    }

    .cn-form-section {
        margin-bottom: 2rem;
        padding-bottom: 2rem;
        border-bottom: 1px solid #e0e0e0;
    }

    .cn-form-section:last-of-type {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }

    .cn-form-section h3 {
        color: #333;
        font-size: 1.3rem;
        margin: 0 0 1.5rem 0;
        font-weight: 600;
    }

    .cn-form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .cn-form-field {
        margin-bottom: 1.5rem;
    }

    .cn-form-field label {
        display: block;
        color: #333;
        font-weight: 500;
        margin-bottom: 0.5rem;
    }

    .required {
        color: #dc3545;
    }

    .cn-input,
    .cn-textarea,
    .cn-file-input {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 1rem;
        transition: border-color 0.2s ease;
    }

    .cn-input:focus,
    .cn-textarea:focus,
    .cn-file-input:focus {
        outline: none;
        border-color: #0073aa;
        box-shadow: 0 0 0 2px rgba(0, 115, 170, 0.1);
    }

    .cn-textarea {
        resize: vertical;
        min-height: 120px;
    }

    .cn-field-help {
        color: #666;
        font-size: 0.85rem;
        margin: 0.5rem 0 0 0;
    }

    /* Buttons */
    .cn-btn {
        display: inline-block;
        padding: 0.75rem 1.5rem;
        border: none;
        border-radius: 4px;
        font-size: 1rem;
        font-weight: 500;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .cn-btn-primary {
        background: #0073aa;
        color: white;
    }

    .cn-btn-primary:hover {
        background: #005a87;
        color: white;
    }

    .cn-btn-secondary {
        background: #6c757d;
        color: white;
    }

    .cn-btn-secondary:hover {
        background: #545b62;
        color: white;
    }

    .cn-btn-large {
        padding: 1rem 2rem;
        font-size: 1.1rem;
    }

    .cn-form-actions {
        display: flex;
        gap: 1rem;
        justify-content: flex-start;
        margin-top: 2rem;
    }

    /* Success/Error Messages */
    .cn-apply-success,
    .cn-apply-error {
        text-align: center;
        padding: 3rem 2rem;
        background: white;
        border-radius: 8px;
        border: 1px solid #e0e0e0;
    }

    .cn-success-icon {
        margin-bottom: 1rem;
    }

    .cn-apply-success h1 {
        color: #10B981;
        font-size: 2rem;
        margin: 0 0 1rem 0;
    }

    .cn-apply-error h1 {
        color: #dc3545;
        font-size: 2rem;
        margin: 0 0 1rem 0;
    }

    .cn-success-actions {
        display: flex;
        gap: 1rem;
        justify-content: center;
        margin-top: 2rem;
    }

    .cn-apply-errors {
        background: #f8d7da;
        border: 1px solid #f5c6cb;
        border-radius: 4px;
        padding: 1rem;
        margin-bottom: 2rem;
    }

    .cn-apply-errors h3 {
        color: #721c24;
        margin: 0 0 0.5rem 0;
    }

    .cn-apply-errors ul {
        margin: 0;
        padding-left: 1.5rem;
    }

    .cn-apply-errors li {
        color: #721c24;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .cn-form-row {
            grid-template-columns: 1fr;
        }

        .cn-form-actions {
            flex-direction: column;
        }

        .cn-success-actions {
            flex-direction: column;
        }

        .cn-apply-job-info,
        .cn-apply-form-container {
            padding: 1.5rem;
        }
    }
</style>

<?php
get_footer();
