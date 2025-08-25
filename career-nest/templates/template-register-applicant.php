<?php

/**
 * Template: CareerNest — Applicant Registration
 */

defined('ABSPATH') || exit;

get_header();

// Handle form submission
$form_submitted = false;
$form_errors = [];
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cn_register_nonce'])) {
    // Verify nonce
    if (!wp_verify_nonce($_POST['cn_register_nonce'], 'cn_register_applicant')) {
        $form_errors[] = 'Security verification failed. Please try again.';
    } else {
        // Process registration
        $result = process_applicant_registration();
        if ($result['success']) {
            $form_submitted = true;
            $success_message = $result['message'];
        } else {
            $form_errors = $result['errors'];
        }
    }
}

/**
 * Process applicant registration
 */
function process_applicant_registration()
{
    $errors = [];

    // Sanitize and validate form data
    $full_name = sanitize_text_field($_POST['full_name'] ?? '');
    $email = sanitize_email($_POST['email'] ?? '');
    $phone = sanitize_text_field($_POST['phone'] ?? '');
    $location = sanitize_text_field($_POST['location'] ?? '');
    $professional_title = sanitize_text_field($_POST['professional_title'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $right_to_work = sanitize_text_field($_POST['right_to_work'] ?? '');
    $work_types = isset($_POST['work_types']) ? array_map('sanitize_text_field', $_POST['work_types']) : [];

    // Validation
    if (empty($full_name)) {
        $errors[] = 'Full name is required.';
    }

    if (empty($email) || !is_email($email)) {
        $errors[] = 'A valid email address is required.';
    }

    if (email_exists($email)) {
        $errors[] = 'An account with this email address already exists.';
    }

    if (empty($password)) {
        $errors[] = 'Password is required.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }

    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }

    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }

    // Create user account
    $user_id = wp_create_user($email, $password, $email);

    if (is_wp_error($user_id)) {
        return ['success' => false, 'errors' => ['Failed to create user account: ' . $user_id->get_error_message()]];
    }

    // Update user profile
    wp_update_user([
        'ID' => $user_id,
        'display_name' => $full_name,
        'first_name' => explode(' ', $full_name)[0],
        'last_name' => substr($full_name, strlen(explode(' ', $full_name)[0]) + 1),
    ]);

    // Set applicant role (remove default subscriber role)
    $user = new WP_User($user_id);
    $user->set_role('applicant');

    // Create applicant profile
    $applicant_id = wp_insert_post([
        'post_type' => 'applicant',
        'post_title' => $full_name,
        'post_status' => 'publish',
        'post_author' => $user_id,
    ]);

    if (!is_wp_error($applicant_id)) {
        // Save applicant meta data
        update_post_meta($applicant_id, '_user_id', $user_id);
        update_post_meta($applicant_id, '_professional_title', $professional_title);
        update_post_meta($applicant_id, '_phone', $phone);
        update_post_meta($applicant_id, '_location', $location);
        update_post_meta($applicant_id, '_right_to_work', $right_to_work);
        update_post_meta($applicant_id, '_work_types', $work_types);
        update_post_meta($applicant_id, '_available_for_work', true);
    }

    // Send welcome email
    $subject = 'Welcome to CareerNest!';
    $message = "Hi {$full_name},\n\n";
    $message .= "Welcome to CareerNest! Your applicant account has been created successfully.\n\n";
    $message .= "You can now:\n";
    $message .= "- Apply for jobs\n";
    $message .= "- Track your applications\n";
    $message .= "- Update your profile\n";
    $message .= "- Upload your resume\n\n";

    // Check for existing guest applications
    $guest_apps = new \WP_Query([
        'post_type' => 'job_application',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => '_applicant_email',
                'value' => $email,
                'compare' => '='
            ]
        ]
    ]);

    if ($guest_apps->have_posts()) {
        $app_count = $guest_apps->found_posts;
        $message .= "We found {$app_count} previous application" . ($app_count > 1 ? 's' : '') . " associated with your email and have linked them to your account.\n\n";
    }

    $pages = get_option('careernest_pages', []);
    $dashboard_id = isset($pages['applicant-dashboard']) ? (int) $pages['applicant-dashboard'] : 0;
    if ($dashboard_id && get_post_status($dashboard_id) === 'publish') {
        $message .= "Access your dashboard: " . get_permalink($dashboard_id) . "\n\n";
    }

    $message .= "Thank you for joining CareerNest!";

    wp_mail($email, $subject, $message);

    // Note: The user_register hook will automatically trigger the guest application linking

    return [
        'success' => true,
        'message' => 'Your account has been created successfully! Please check your email for login details and next steps.'
    ];
}
?>

<main id="primary" class="site-main">
    <div class="cn-register-container">
        <?php if ($form_submitted): ?>
            <!-- Success Message -->
            <div class="cn-register-success">
                <div class="cn-success-icon">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="12" cy="12" r="10" stroke="#10B981" stroke-width="2" />
                        <path d="m9 12 2 2 4-4" stroke="#10B981" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round" />
                    </svg>
                </div>
                <h1><?php echo esc_html__('Registration Successful!', 'careernest'); ?></h1>
                <p><?php echo esc_html($success_message); ?></p>
                <div class="cn-success-actions">
                    <a href="<?php echo esc_url(wp_login_url()); ?>" class="cn-btn cn-btn-primary">Login to Your Account</a>
                    <?php
                    $pages = get_option('careernest_pages', []);
                    $jobs_page_id = isset($pages['jobs']) ? (int) $pages['jobs'] : 0;
                    if ($jobs_page_id && get_post_status($jobs_page_id) === 'publish'):
                    ?>
                        <a href="<?php echo esc_url(get_permalink($jobs_page_id)); ?>" class="cn-btn cn-btn-secondary">Browse
                            Jobs</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <!-- Registration Form -->
            <div class="cn-register-form-container">
                <div class="cn-register-header">
                    <h1><?php echo esc_html__('Create Your Applicant Account', 'careernest'); ?></h1>
                    <p><?php echo esc_html__('Join CareerNest to apply for jobs, track your applications, and build your professional profile.', 'careernest'); ?>
                    </p>
                </div>

                <!-- Error Messages -->
                <?php if (!empty($form_errors)): ?>
                    <div class="cn-register-errors">
                        <h3><?php echo esc_html__('Please correct the following errors:', 'careernest'); ?></h3>
                        <ul>
                            <?php foreach ($form_errors as $error): ?>
                                <li><?php echo esc_html($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- Registration Form -->
                <form method="post" class="cn-register-form">
                    <?php wp_nonce_field('cn_register_applicant', 'cn_register_nonce'); ?>

                    <div class="cn-form-section">
                        <h3><?php echo esc_html__('Account Information', 'careernest'); ?></h3>

                        <div class="cn-form-row">
                            <div class="cn-form-field">
                                <label for="full_name"><?php echo esc_html__('Full Name', 'careernest'); ?> <span
                                        class="required">*</span></label>
                                <input type="text" id="full_name" name="full_name"
                                    value="<?php echo esc_attr($_POST['full_name'] ?? ''); ?>" required class="cn-input">
                            </div>

                            <div class="cn-form-field">
                                <label for="email"><?php echo esc_html__('Email Address', 'careernest'); ?> <span
                                        class="required">*</span></label>
                                <input type="email" id="email" name="email"
                                    value="<?php echo esc_attr($_POST['email'] ?? ''); ?>" required class="cn-input">
                                <p class="cn-field-help">
                                    <?php echo esc_html__('This will be your username for logging in', 'careernest'); ?></p>
                            </div>
                        </div>

                        <div class="cn-form-row">
                            <div class="cn-form-field">
                                <label for="password"><?php echo esc_html__('Password', 'careernest'); ?> <span
                                        class="required">*</span></label>
                                <input type="password" id="password" name="password" required class="cn-input"
                                    minlength="8">
                                <p class="cn-field-help"><?php echo esc_html__('Minimum 8 characters', 'careernest'); ?></p>
                            </div>

                            <div class="cn-form-field">
                                <label for="confirm_password"><?php echo esc_html__('Confirm Password', 'careernest'); ?>
                                    <span class="required">*</span></label>
                                <input type="password" id="confirm_password" name="confirm_password" required
                                    class="cn-input" minlength="8">
                            </div>
                        </div>
                    </div>

                    <div class="cn-form-section">
                        <h3><?php echo esc_html__('Professional Information', 'careernest'); ?></h3>

                        <div class="cn-form-row">
                            <div class="cn-form-field">
                                <label
                                    for="professional_title"><?php echo esc_html__('Professional Title', 'careernest'); ?></label>
                                <input type="text" id="professional_title" name="professional_title"
                                    value="<?php echo esc_attr($_POST['professional_title'] ?? ''); ?>" class="cn-input"
                                    placeholder="e.g., Senior Software Engineer">
                            </div>

                            <div class="cn-form-field">
                                <label for="phone"><?php echo esc_html__('Phone Number', 'careernest'); ?></label>
                                <input type="tel" id="phone" name="phone"
                                    value="<?php echo esc_attr($_POST['phone'] ?? ''); ?>" class="cn-input">
                            </div>
                        </div>

                        <div class="cn-form-row">
                            <div class="cn-form-field">
                                <label for="location"><?php echo esc_html__('Location', 'careernest'); ?></label>
                                <input type="text" id="location" name="location"
                                    value="<?php echo esc_attr($_POST['location'] ?? ''); ?>" class="cn-input"
                                    placeholder="e.g., Melbourne, VIC">
                            </div>

                            <div class="cn-form-field">
                                <label for="right_to_work"><?php echo esc_html__('Right to Work', 'careernest'); ?></label>
                                <select id="right_to_work" name="right_to_work" class="cn-input">
                                    <option value="foreign" <?php selected($_POST['right_to_work'] ?? '', 'foreign'); ?>>
                                        <?php echo esc_html__('Foreign Citizen', 'careernest'); ?></option>
                                    <option value="australian"
                                        <?php selected($_POST['right_to_work'] ?? '', 'australian'); ?>>
                                        <?php echo esc_html__('Australian Citizen', 'careernest'); ?></option>
                                </select>
                            </div>
                        </div>

                        <div class="cn-form-field">
                            <label><?php echo esc_html__('Preferred Work Types', 'careernest'); ?></label>
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
                                $selected_types = $_POST['work_types'] ?? [];
                                foreach ($work_type_options as $value => $label):
                                ?>
                                    <label class="cn-checkbox-label">
                                        <input type="checkbox" name="work_types[]" value="<?php echo esc_attr($value); ?>"
                                            <?php checked(in_array($value, $selected_types)); ?> class="cn-checkbox">
                                        <span><?php echo esc_html($label); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="cn-form-actions">
                        <button type="submit" class="cn-btn cn-btn-primary cn-btn-large">
                            <?php echo esc_html__('Create Account', 'careernest'); ?>
                        </button>
                        <p class="cn-login-link">
                            <?php echo esc_html__('Already have an account?', 'careernest'); ?>
                            <a
                                href="<?php echo esc_url(wp_login_url()); ?>"><?php echo esc_html__('Login here', 'careernest'); ?></a>
                        </p>
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

    .cn-register-container {
        margin: 2rem 0;
    }

    /* Header */
    .cn-register-header {
        text-align: center;
        margin-bottom: 2rem;
    }

    .cn-register-header h1 {
        color: #333;
        font-size: 2rem;
        margin: 0 0 1rem 0;
    }

    .cn-register-header p {
        color: #666;
        font-size: 1.1rem;
        margin: 0;
    }

    /* Form Styling */
    .cn-register-form-container {
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

    .cn-input {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 1rem;
        transition: border-color 0.2s ease;
    }

    .cn-input:focus {
        outline: none;
        border-color: #0073aa;
        box-shadow: 0 0 0 2px rgba(0, 115, 170, 0.1);
    }

    .cn-field-help {
        color: #666;
        font-size: 0.85rem;
        margin: 0.5rem 0 0 0;
    }

    /* Checkbox Group */
    .cn-checkbox-group {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 0.5rem;
        margin-top: 0.5rem;
    }

    .cn-checkbox-label {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        cursor: pointer;
        padding: 0.5rem;
        border-radius: 4px;
        transition: background-color 0.2s ease;
    }

    .cn-checkbox-label:hover {
        background: #f8f9fa;
    }

    .cn-checkbox {
        margin: 0;
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
        text-align: center;
        margin-top: 2rem;
    }

    .cn-login-link {
        margin-top: 1rem;
        color: #666;
    }

    .cn-login-link a {
        color: #0073aa;
        text-decoration: none;
    }

    .cn-login-link a:hover {
        text-decoration: underline;
    }

    /* Success/Error Messages */
    .cn-register-success {
        text-align: center;
        padding: 3rem 2rem;
        background: white;
        border-radius: 8px;
        border: 1px solid #e0e0e0;
    }

    .cn-success-icon {
        margin-bottom: 1rem;
    }

    .cn-register-success h1 {
        color: #10B981;
        font-size: 2rem;
        margin: 0 0 1rem 0;
    }

    .cn-success-actions {
        display: flex;
        gap: 1rem;
        justify-content: center;
        margin-top: 2rem;
    }

    .cn-register-errors {
        background: #f8d7da;
        border: 1px solid #f5c6cb;
        border-radius: 4px;
        padding: 1rem;
        margin-bottom: 2rem;
    }

    .cn-register-errors h3 {
        color: #721c24;
        margin: 0 0 0.5rem 0;
    }

    .cn-register-errors ul {
        margin: 0;
        padding-left: 1.5rem;
    }

    .cn-register-errors li {
        color: #721c24;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .cn-form-row {
            grid-template-columns: 1fr;
        }

        .cn-checkbox-group {
            grid-template-columns: 1fr;
        }

        .cn-success-actions {
            flex-direction: column;
        }

        .cn-register-form-container {
            padding: 1.5rem;
        }

        .cn-register-header h1 {
            font-size: 1.5rem;
        }
    }
</style>

<?php
get_footer();
