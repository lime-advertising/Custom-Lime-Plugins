<?php

/**
 * Template: CareerNest — Single Employer Profile
 */

defined('ABSPATH') || exit;

get_header();

// Get employer data
$employer_id = get_the_ID();
$website = get_post_meta($employer_id, '_website', true);
$location = get_post_meta($employer_id, '_location', true);
$tagline = get_post_meta($employer_id, '_tagline', true);
$industry_desc = get_post_meta($employer_id, '_industry_description', true);
$about = get_post_meta($employer_id, '_about', true);
$mission = get_post_meta($employer_id, '_mission', true);
$spotlight = get_post_meta($employer_id, '_spotlight', true);
$interested = get_post_meta($employer_id, '_interested_in_working', true);
$specialities = get_post_meta($employer_id, '_specialities', true);
$company_size = get_post_meta($employer_id, '_company_size', true);
$founded_year = get_post_meta($employer_id, '_founded_year', true);
$employer_logo = get_the_post_thumbnail_url($employer_id, 'medium');

// Get company name
$company_name = get_the_title($employer_id);
$company_initial = substr($company_name, 0, 1);
?>

<main id="primary" class="site-main">
    <!-- Employer Profile Header -->
    <section id="cn_employer_profile_header" class="cn-section">
        <div class="cn-section-inner-wrap">
            <div class="cn-header-spacer"></div>
            <div class="cn-header-content">
                <div class="cn-logo-container">
                    <?php if ($employer_logo): ?>
                        <img src="<?php echo esc_url($employer_logo); ?>" alt="<?php echo esc_attr($company_name); ?>"
                            class="cn-employer-logo">
                    <?php else: ?>
                        <div class="cn-employer-logo-placeholder">
                            <span><?php echo esc_html($company_initial); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="cn-company-info">
                    <div class="cn-company-header">
                        <h2 class="cn-company-name">
                            <span
                                class="cn-company-initial"><?php echo esc_html($company_initial); ?></span><?php echo esc_html(substr($company_name, 1)); ?>
                        </h2>
                        <div class="cn-company-meta">
                            <?php if ($tagline): ?>
                                <div class="cn-tagline">
                                    <span><?php echo esc_html($tagline); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($location): ?>
                                <img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMiIgaGVpZ2h0PSIyIiB2aWV3Qm94PSIwIDAgMiAyIiBmaWxsPSJub25lIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPgo8Y2lyY2xlIGN4PSIxIiBjeT0iMSIgcj0iMSIgZmlsbD0iIzMzMzMzMyIvPgo8L3N2Zz4K"
                                    alt="dot" class="cn-dot">
                                <div class="cn-location">
                                    <span><?php echo esc_html($location); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="cn-edit-button">
                        <?php if (current_user_can('edit_post', $employer_id)): ?>
                            <a class="cn-edit-link" href="<?php echo esc_url(get_edit_post_link($employer_id)); ?>">Edit
                                Company</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Employer Profile Body -->
    <section id="cn_employer_profile_body" class="cn-section">
        <div class="cn-section-inner-wrap">
            <div class="cn-profile-columns">
                <div class="cn-profile-left">
                    <?php if ($about): ?>
                        <div class="cn-profile-section">
                            <h4>About</h4>
                            <div class="cn-profile-content">
                                <?php echo wp_kses_post(wpautop($about)); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($mission): ?>
                        <div class="cn-profile-section">
                            <h4>Our Mission Statement</h4>
                            <div class="cn-profile-content">
                                <?php echo wp_kses_post(wpautop($mission)); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($spotlight): ?>
                        <div class="cn-profile-section">
                            <h4>Company Spotlight</h4>
                            <div class="cn-profile-content">
                                <?php echo wp_kses_post(wpautop($spotlight)); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($interested): ?>
                        <div class="cn-profile-section">
                            <h4>Interested in Working for Us?</h4>
                            <div class="cn-profile-content">
                                <?php echo wp_kses_post(wpautop($interested)); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php
                    // Show the main post content if it exists
                    if (get_the_content()):
                    ?>
                        <div class="cn-profile-section">
                            <h4>Additional Information</h4>
                            <div class="cn-profile-content">
                                <?php the_content(); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php
                    // Debug: Show if no content is available
                    if (!$about && !$mission && !$spotlight && !$interested && !get_the_content()):
                    ?>
                        <div class="cn-profile-section">
                            <h4>Company Information</h4>
                            <div class="cn-profile-content">
                                <p><em>No additional company information available. Please add content in the employer admin
                                        panel.</em></p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="cn-profile-right">
                    <div class="cn-profile-section">
                        <h4>Company Overview</h4>

                        <?php if ($website): ?>
                            <div class="cn-overview-item">
                                <div class="cn-overview-icon">
                                    <svg width="54" height="54" viewBox="0 0 54 54" fill="none"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <rect width="54" height="54" rx="27" fill="#EEEEEE"></rect>
                                        <path
                                            d="M26.9997 38.6668C20.5562 38.6668 15.333 33.4437 15.333 27.0002C15.333 20.5567 20.5562 15.3335 26.9997 15.3335C33.4432 15.3335 38.6663 20.5567 38.6663 27.0002C38.6663 33.4437 33.4432 38.6668 26.9997 38.6668ZM24.328 35.945C23.177 33.5035 22.5102 30.8621 22.3645 28.1668H17.7387C17.966 29.9621 18.7096 31.6528 19.8793 33.0335C21.0491 34.4143 22.5945 35.4257 24.328 35.945ZM24.7013 28.1668C24.8775 31.0123 25.6907 33.6852 26.9997 36.0442C28.3442 33.6229 29.1296 30.9313 29.298 28.1668H24.7013ZM36.2607 28.1668H31.6348C31.4892 30.8621 30.8224 33.5035 29.6713 35.945C31.4048 35.4257 32.9503 34.4143 34.12 33.0335C35.2897 31.6528 36.0334 29.9621 36.2607 28.1668ZM17.7387 25.8335H22.3645C22.5102 23.1382 23.177 20.4968 24.328 18.0553C22.5945 18.5746 21.0491 19.586 19.8793 20.9668C18.7096 22.3475 17.966 24.0382 17.7387 25.8335ZM24.7025 25.8335H29.2968C29.1287 23.0692 28.3438 20.3775 26.9997 17.9562C25.6551 20.3774 24.8698 23.0691 24.7013 25.8335M29.6702 18.0553C30.8216 20.4967 31.4888 23.1382 31.6348 25.8335H36.2607C36.0334 24.0382 35.2897 22.3475 34.12 20.9668C32.9503 19.586 31.4048 18.5746 29.6713 18.0553"
                                            fill="#101010"></path>
                                    </svg>
                                </div>
                                <div class="cn-overview-content">
                                    <h6>Website</h6>
                                    <a href="<?php echo esc_url($website); ?>" target="_blank" rel="noopener noreferrer">
                                        <?php echo esc_html($website); ?>
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($industry_desc): ?>
                            <div class="cn-overview-item">
                                <div class="cn-overview-icon">
                                    <svg width="54" height="54" viewBox="0 0 54 54" fill="none"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <rect width="54" height="54" rx="27" fill="#EEEEEE"></rect>
                                        <path
                                            d="M16.5 37.5H37.5M18.8333 37.5V23.5L24.6667 28.1667V23.5L30.5 28.1667H35.1667"
                                            stroke="#101010" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round"></path>
                                        <path
                                            d="M35.1667 37.5V28.1667L33.4913 16.997C33.4706 16.8587 33.4009 16.7324 33.2949 16.6412C33.1889 16.55 33.0537 16.4999 32.9138 16.5H31.578C31.4397 16.4998 31.3059 16.5487 31.2003 16.638C31.0947 16.7273 31.0243 16.8513 31.0017 16.9877L29.3333 27M23.5 32.8333H24.6667M29.3333 32.8333H30.5"
                                            stroke="#101010" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round"></path>
                                    </svg>
                                </div>
                                <div class="cn-overview-content">
                                    <h6>Industry</h6>
                                    <span><?php echo esc_html($industry_desc); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($company_size): ?>
                            <div class="cn-overview-item">
                                <div class="cn-overview-icon">
                                    <svg width="54" height="54" viewBox="0 0 54 54" fill="none"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <rect width="54" height="54" rx="27" fill="#EEEEEE"></rect>
                                        <path
                                            d="M22.3333 37.5V36.3333C22.3333 35.7145 22.5792 35.121 23.0168 34.6834C23.4543 34.2458 24.0478 34 24.6667 34H29.3333C29.9522 34 30.5457 34.2458 30.9832 34.6834C31.4208 35.121 31.6667 35.7145 31.6667 36.3333V37.5M32.8333 24.6667H35.1667C35.7855 24.6667 36.379 24.9125 36.8166 25.3501C37.2542 25.7877 37.5 26.3812 37.5 27V28.1667M16.5 28.1667V27C16.5 26.3812 16.7458 25.7877 17.1834 25.3501C17.621 24.9125 18.2145 24.6667 18.8333 24.6667H21.1667M24.6667 28.1667C24.6667 28.7855 24.9125 29.379 25.3501 29.8166C25.7877 30.2542 26.3812 30.5 27 30.5C27.6188 30.5 28.2123 30.2542 28.6499 29.8166C29.0875 29.379 29.3333 28.7855 29.3333 28.1667C29.3333 27.5478 29.0875 26.9543 28.6499 26.5168C28.2123 26.0792 27.6188 25.8333 27 25.8333C26.3812 25.8333 25.7877 26.0792 25.3501 26.5168C24.9125 26.9543 24.6667 27.5478 24.6667 28.1667ZM30.5 18.8333C30.5 19.4522 30.7458 20.0457 31.1834 20.4832C31.621 20.9208 32.2145 21.1667 32.8333 21.1667C33.4522 21.1667 34.0457 20.9208 34.4832 20.4832C34.9208 20.0457 35.1667 19.4522 35.1667 18.8333C35.1667 18.2145 34.9208 17.621 34.4832 17.1834C34.0457 16.7458 33.4522 16.5 32.8333 16.5C32.2145 16.5 31.621 16.7458 31.1834 17.1834C30.7458 17.621 30.5 18.2145 30.5 18.8333ZM18.8333 18.8333C18.8333 19.4522 19.0792 20.0457 19.5168 20.4832C19.9543 20.9208 20.5478 21.1667 21.1667 21.1667C21.7855 21.1667 22.379 20.9208 22.8166 20.4832C23.2542 20.0457 23.5 19.4522 23.5 18.8333C23.5 18.2145 23.2542 17.621 22.8166 17.1834C22.379 16.7458 21.7855 16.5 21.1667 16.5C20.5478 16.5 19.9543 16.7458 19.5168 17.1834C19.0792 17.621 18.8333 18.2145 18.8333 18.8333Z"
                                            stroke="#101010" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round"></path>
                                    </svg>
                                </div>
                                <div class="cn-overview-content">
                                    <h6>Company size</h6>
                                    <span><?php echo esc_html($company_size ?: 'Not specified'); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($location): ?>
                            <div class="cn-overview-item">
                                <div class="cn-overview-icon">
                                    <svg width="54" height="54" viewBox="0 0 54 54" fill="none"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <rect width="54" height="54" rx="27" fill="#EEEEEE"></rect>
                                        <path
                                            d="M23.5 25.8335C23.5 26.7618 23.8687 27.652 24.5251 28.3084C25.1815 28.9647 26.0717 29.3335 27 29.3335C27.9283 29.3335 28.8185 28.9647 29.4749 28.3084C30.1313 27.652 30.5 26.7618 30.5 25.8335C30.5 24.9052 30.1313 24.015 29.4749 23.3586C28.8185 22.7022 27.9283 22.3335 27 22.3335C26.0717 22.3335 25.1815 22.7022 24.5251 23.3586C23.8687 24.015 23.5 24.9052 23.5 25.8335Z"
                                            stroke="#101010" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round"></path>
                                        <path
                                            d="M33.6002 32.4333L28.65 37.3835C28.2125 37.8206 27.6193 38.0661 27.0009 38.0661C26.3825 38.0661 25.7893 37.8206 25.3518 37.3835L20.4005 32.4333C19.0953 31.128 18.2064 29.465 17.8463 27.6545C17.4862 25.844 17.6711 23.9674 18.3775 22.262C19.0839 20.5566 20.2802 19.0989 21.8151 18.0734C23.3499 17.0479 25.1544 16.5005 27.0003 16.5005C28.8463 16.5005 30.6508 17.0479 32.1856 18.0734C33.7204 19.0989 34.9167 20.5566 35.6232 22.262C36.3296 23.9674 36.5144 25.844 36.1544 27.6545C35.7943 29.465 34.9054 31.128 33.6002 32.4333Z"
                                            stroke="#101010" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round"></path>
                                    </svg>
                                </div>
                                <div class="cn-overview-content">
                                    <h6>Headquarters</h6>
                                    <span><?php echo esc_html($location); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($founded_year): ?>
                            <div class="cn-overview-item">
                                <div class="cn-overview-icon">
                                    <svg width="54" height="54" viewBox="0 0 54 54" fill="none"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <rect width="54" height="54" rx="27" fill="#EEEEEE"></rect>
                                        <path
                                            d="M26.417 37.5H20.0003C19.3815 37.5 18.788 37.2542 18.3504 36.8166C17.9128 36.379 17.667 35.7855 17.667 35.1667V21.1667C17.667 20.5478 17.9128 19.9543 18.3504 19.5168C18.788 19.0792 19.3815 18.8333 20.0003 18.8333H34.0003C34.6192 18.8333 35.2127 19.0792 35.6502 19.5168C36.0878 19.9543 36.3337 20.5478 36.3337 21.1667V28.1667M31.667 16.5V21.1667M22.3337 16.5V21.1667M17.667 25.8333H36.3337M30.5003 35.1667L32.8337 37.5L37.5003 32.8333"
                                            stroke="#101010" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round"></path>
                                    </svg>
                                </div>
                                <div class="cn-overview-content">
                                    <h6>Founded</h6>
                                    <span><?php echo esc_html($founded_year); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($specialities): ?>
                            <div class="cn-overview-item">
                                <div class="cn-overview-icon">
                                    <svg width="54" height="54" viewBox="0 0 54 54" fill="none"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <rect width="54" height="54" rx="27" fill="#EEEEEE"></rect>
                                        <path
                                            d="M27.0003 33.7086L19.7996 37.4944L21.1751 29.4759L15.3418 23.7978L23.3918 22.6311L26.9921 15.3359L30.5925 22.6311L38.6425 23.7978L32.8091 29.4759L34.1846 37.4944L27.0003 33.7086Z"
                                            stroke="#101010" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round"></path>
                                    </svg>
                                </div>
                                <div class="cn-overview-content">
                                    <h6>Specialities</h6>
                                    <span><?php echo esc_html($specialities); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Current Openings -->
                    <div class="cn-profile-section">
                        <h4>Current Openings</h4>
                        <?php
                        // Get jobs for this employer
                        $employer_jobs = new WP_Query([
                            'post_type' => 'job_listing',
                            'post_status' => 'publish',
                            'posts_per_page' => 5,
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

                        if ($employer_jobs->have_posts()): ?>
                            <div class="cn-employer-jobs">
                                <?php while ($employer_jobs->have_posts()): $employer_jobs->the_post();
                                    $job_id = get_the_ID();
                                    $job_location = get_post_meta($job_id, '_job_location', true);
                                    $job_salary_range = get_post_meta($job_id, '_salary_range', true);
                                    $job_salary_numeric = get_post_meta($job_id, '_salary', true);
                                    $job_salary_mode = get_post_meta($job_id, '_salary_mode', true);
                                    $job_closing_date = get_post_meta($job_id, '_closing_date', true);

                                    // Calculate expiry
                                    $expiry_text = '';
                                    if ($job_closing_date) {
                                        $closing_timestamp = strtotime($job_closing_date . ' 23:59:59');
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
                                            <?php if ($employer_logo): ?>
                                                <img class="cn_related_job_card__img" src="<?php echo esc_url($employer_logo); ?>"
                                                    alt="<?php echo esc_attr(get_the_title()); ?>">
                                            <?php else: ?>
                                                <div class="cn_related_job_card__img-placeholder">
                                                    <span><?php echo esc_html($company_initial); ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <h4 class="cn_related_job_card__title">
                                                    <a
                                                        href="<?php echo esc_url(get_permalink()); ?>"><?php echo esc_html(get_the_title()); ?></a>
                                                </h4>
                                                <span
                                                    class="cn_related_job_card__company"><?php echo esc_html($company_name); ?></span>
                                                <?php if ($job_location): ?>
                                                    <span style="color: #CACACA; font-size: 14px;"> | </span>
                                                    <span
                                                        class="cn_related_job_card__location"><?php echo esc_html($job_location); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <?php if ($job_salary_mode === 'numeric' && $job_salary_numeric): ?>
                                            <div class="cn_related_job_card__salary">
                                                <svg width="20" height="21" viewBox="0 0 20 21" fill="none"
                                                    xmlns="http://www.w3.org/2000/svg">
                                                    <circle cx="10" cy="10.4998" r="8.33333" stroke="#3D3935" stroke-width="1.5">
                                                    </circle>
                                                    <path d="M10 5.5V15.5" stroke="#3D3935" stroke-width="1.5"
                                                        stroke-linecap="round"></path>
                                                    <path
                                                        d="M12.5 8.41683C12.5 7.26624 11.3807 6.3335 10 6.3335C8.61929 6.3335 7.5 7.26624 7.5 8.41683C7.5 9.56742 8.61929 10.5002 10 10.5002C11.3807 10.5002 12.5 11.4329 12.5 12.5835C12.5 13.7341 11.3807 14.6668 10 14.6668C8.61929 14.6668 7.5 13.7341 7.5 12.5835"
                                                        stroke="#3D3935" stroke-width="1.5" stroke-linecap="round"></path>
                                                </svg>
                                                <span>$ <?php echo esc_html(number_format($job_salary_numeric)); ?></span>
                                            </div>
                                        <?php elseif ($job_salary_range): ?>
                                            <div class="cn_related_job_card__salary">
                                                <svg width="20" height="21" viewBox="0 0 20 21" fill="none"
                                                    xmlns="http://www.w3.org/2000/svg">
                                                    <circle cx="10" cy="10.4998" r="8.33333" stroke="#3D3935" stroke-width="1.5">
                                                    </circle>
                                                    <path d="M10 5.5V15.5" stroke="#3D3935" stroke-width="1.5"
                                                        stroke-linecap="round"></path>
                                                    <path
                                                        d="M12.5 8.41683C12.5 7.26624 11.3807 6.3335 10 6.3335C8.61929 6.3335 7.5 7.26624 7.5 8.41683C7.5 9.56742 8.61929 10.5002 10 10.5002C11.3807 10.5002 12.5 11.4329 12.5 12.5835C12.5 13.7341 11.3807 14.6668 10 14.6668C8.61929 14.6668 7.5 13.7341 7.5 12.5835"
                                                        stroke="#3D3935" stroke-width="1.5" stroke-linecap="round"></path>
                                                </svg>
                                                <span><?php echo esc_html($job_salary_range); ?></span>
                                            </div>
                                        <?php endif; ?>

                                        <hr>

                                        <div class="cn_related_job_card-bottom">
                                            <div class="cn_related_job_card__published"></div>
                                            <span
                                                class="cn_related_job_card__modified"><?php echo esc_html($expiry_text); ?></span>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                            <?php wp_reset_postdata(); ?>

                            <?php
                            // Link to view more jobs
                            $pages = get_option('careernest_pages', []);
                            $jobs_page_id = isset($pages['jobs']) ? (int) $pages['jobs'] : 0;
                            if ($jobs_page_id && get_post_status($jobs_page_id) === 'publish'):
                            ?>
                                <a class="cn-view-more-jobs" href="<?php echo esc_url(get_permalink($jobs_page_id)); ?>">View
                                    More Jobs</a>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="no-current-jobs">No current openings available.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<style>
    /* Container */
    .site-main {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px;
    }

    .cn-section {
        margin: 2rem 0;
    }

    .cn-section-inner-wrap {
        width: 100%;
    }

    /* Header Section */
    #cn_employer_profile_header {
        background: #f8f9fa;
        padding: 3rem 0;
        border-bottom: 1px solid #e0e0e0;
    }

    .cn-header-content {
        display: flex;
        align-items: center;
        gap: 2rem;
    }

    .cn-logo-container {
        flex-shrink: 0;
    }

    .cn-employer-logo {
        width: 120px;
        height: 120px;
        object-fit: cover;
        border-radius: 8px;
    }

    .cn-employer-logo-placeholder {
        width: 120px;
        height: 120px;
        background: #e0e0e0;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 3rem;
        font-weight: bold;
        color: #666;
    }

    .cn-company-info {
        flex: 1;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }

    .cn-company-name {
        font-size: 2.5rem;
        font-weight: bold;
        margin: 0 0 1rem 0;
        color: #333;
    }

    .cn-company-initial {
        color: #0073aa;
    }

    .cn-company-meta {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .cn-tagline span {
        color: #666;
        font-size: 1.1rem;
    }

    .cn-dot {
        width: 4px;
        height: 4px;
    }

    .cn-location span {
        color: #666;
        font-size: 1rem;
    }

    .cn-edit-link {
        background: #0073aa;
        color: white;
        padding: 0.75rem 1.5rem;
        text-decoration: none;
        border-radius: 4px;
        font-weight: 500;
    }

    .cn-edit-link:hover {
        background: #005a87;
        color: white;
    }

    /* Body Section */
    #cn_employer_profile_body {
        padding: 3rem 0;
    }

    .cn-profile-columns {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 3rem;
    }

    .cn-profile-section {
        margin-bottom: 2.5rem;
    }

    .cn-profile-section h4 {
        color: #333;
        font-size: 1.5rem;
        margin-bottom: 1rem;
        font-weight: 600;
    }

    .cn-profile-content {
        color: #555;
        line-height: 1.6;
    }

    /* Company Overview Items */
    .cn-overview-item {
        display: flex;
        gap: 1rem;
        margin-bottom: 1.5rem;
        align-items: flex-start;
    }

    .cn-overview-icon {
        flex-shrink: 0;
    }

    .cn-overview-content h6 {
        color: #333;
        font-size: 1rem;
        margin: 0 0 0.5rem 0;
        font-weight: 600;
    }

    .cn-overview-content span,
    .cn-overview-content a {
        color: #666;
        font-size: 0.95rem;
        line-height: 1.4;
    }

    .cn-overview-content a {
        color: #0073aa;
        text-decoration: none;
    }

    .cn-overview-content a:hover {
        text-decoration: underline;
    }

    /* Current Openings */
    .cn-employer-jobs {
        margin-bottom: 1.5rem;
    }

    .cn-view-more-jobs {
        color: #0073aa;
        text-decoration: none;
        font-weight: 500;
        display: inline-block;
        margin-top: 1rem;
    }

    .cn-view-more-jobs:hover {
        text-decoration: underline;
    }

    .no-current-jobs {
        color: #666;
        font-style: italic;
        margin: 1rem 0;
    }

    /* Related Job Cards (reuse from single job template) */
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

    /* Responsive Design */
    @media (max-width: 768px) {
        .cn-header-content {
            flex-direction: column;
            text-align: center;
            gap: 1.5rem;
        }

        .cn-company-info {
            flex-direction: column;
            align-items: center;
            gap: 1rem;
        }

        .cn-company-name {
            font-size: 2rem;
        }

        .cn-profile-columns {
            grid-template-columns: 1fr;
            gap: 2rem;
        }

        #cn_employer_profile_header,
        #cn_employer_profile_body {
            padding: 2rem 0;
        }
    }
</style>

<?php
get_footer();
