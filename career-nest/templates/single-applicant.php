<?php

/**
 * Template: CareerNest — Single Applicant Profile
 */

defined('ABSPATH') || exit;

get_header();

// Get applicant data
$applicant_id = get_the_ID();
$user_id = get_post_meta($applicant_id, '_user_id', true);
$prof_title = get_post_meta($applicant_id, '_professional_title', true);
$right_to_work = get_post_meta($applicant_id, '_right_to_work', true);
$work_types = get_post_meta($applicant_id, '_work_types', true);
$location = get_post_meta($applicant_id, '_location', true);
$available_for_work = get_post_meta($applicant_id, '_available_for_work', true);
$resume_id = get_post_meta($applicant_id, '_resume_attachment_id', true);
$phone = get_post_meta($applicant_id, '_phone', true);
$linkedin_url = get_post_meta($applicant_id, '_linkedin_url', true);
$skills = get_post_meta($applicant_id, '_skills', true);
$education = get_post_meta($applicant_id, '_education', true);
$experience = get_post_meta($applicant_id, '_experience', true);
$licenses = get_post_meta($applicant_id, '_licenses', true);
$links = get_post_meta($applicant_id, '_links', true);

// Get user email if linked
$contact_email = '';
if ($user_id) {
    $user = get_user_by('id', $user_id);
    if ($user) {
        $contact_email = $user->user_email;
    }
}

// Get applicant name and photo
$applicant_name = get_the_title($applicant_id);
$applicant_photo = get_the_post_thumbnail_url($applicant_id, 'thumbnail');

// Get resume file info
$resume_url = '';
$resume_title = '';
if ($resume_id) {
    $resume_url = wp_get_attachment_url($resume_id);
    $resume_title = get_the_title($resume_id);
}
?>

<main id="primary" class="site-main">
    <div class="single-resume-content cn-section-inner-wrap" id="cn_resume">

        <div class="cn_resume_header">
            <img decoding="async" class="cn_resume_header_img"
                src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgdmlld0JveD0iMCAwIDEwMCAxMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxMDAiIGhlaWdodD0iMTAwIiBmaWxsPSIjZjhmOWZhIi8+Cjx0ZXh0IHg9IjUwIiB5PSI1NSIgZm9udC1mYW1pbHk9IkFyaWFsLCBzYW5zLXNlcmlmIiBmb250LXNpemU9IjE0IiBmaWxsPSIjNjY2IiB0ZXh0LWFuY2hvcj0ibWlkZGxlIj5SZXN1bWUgSGVhZGVyPC90ZXh0Pgo8L3N2Zz4K"
                alt="Single Resume Header">
            <div class="cn_resume_header-content">
                <div class="cn_resume_header-left">
                    <?php if ($applicant_photo): ?>
                        <img decoding="async" class="candidate_photo" src="<?php echo esc_url($applicant_photo); ?>"
                            alt="Photo">
                    <?php else: ?>
                        <div class="candidate_photo_placeholder">
                            <span><?php echo esc_html(substr($applicant_name, 0, 1)); ?></span>
                        </div>
                    <?php endif; ?>

                    <h1 class="candidate_title">
                        <?php echo esc_html($applicant_name); ?>
                        <?php if (current_user_can('edit_post', $applicant_id)): ?>
                            <form method="get" action="<?php echo esc_url(get_edit_post_link($applicant_id)); ?>"
                                class="cn_resume_card-bookmark_form">
                                <a class="add-bookmark" href="<?php echo esc_url(get_edit_post_link($applicant_id)); ?>"
                                    data-tooltip="Edit Profile">
                                    <svg width="22" height="22" viewBox="0 0 22 22" fill="none"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <path
                                            d="M16.1269 3.04559C17.1353 3.16292 17.875 4.03284 17.875 5.0485V19.2504L11 15.8129L4.125 19.2504V5.0485C4.125 4.03284 4.86383 3.16292 5.87308 3.04559C9.27959 2.65017 12.7204 2.65017 16.1269 3.04559Z"
                                            stroke="#636363" stroke-width="1.5" stroke-linecap="round"
                                            stroke-linejoin="round"></path>
                                    </svg>
                                </a>
                            </form>
                        <?php endif; ?>
                    </h1>

                    <div class="cn_resume_header-meta">
                        <?php if ($location): ?>
                            <div class="candidate_location_wrapper">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M4 10.1433C4 5.64588 7.58172 2 12 2C16.4183 2 20 5.64588 20 10.1433C20 14.6055 17.4467 19.8124 13.4629 21.6744C12.5343 22.1085 11.4657 22.1085 10.5371 21.6744C6.55332 19.8124 4 14.6055 4 10.1433Z"
                                        stroke="#101010" stroke-width="1.5"></path>
                                    <circle cx="12" cy="10" r="3" stroke="#101010" stroke-width="1.5"></circle>
                                </svg>
                                <span><?php echo esc_html($location); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="cn_resume_header-right">
                    <?php if ($contact_email): ?>
                        <div class="cn_icon-box">
                            <div class="cn_icon-box-icon">
                                <svg width="28" height="28" viewBox="0 0 28 28" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path fill-rule="evenodd" clip-rule="evenodd"
                                        d="M12.7694 1.4585H15.228C16.5496 1.45847 17.6297 1.45845 18.4823 1.57308C19.3736 1.69291 20.1463 1.95224 20.7631 2.56905C21.38 3.18586 21.6393 3.95859 21.7591 4.84986C21.818 5.28819 21.8467 5.78659 21.8606 6.347C23.0952 6.54617 24.107 6.93824 24.9172 7.74851C25.7903 8.6216 26.1778 9.7287 26.3617 11.0965C26.5404 12.4256 26.5404 14.1239 26.5404 16.2679V16.3995C26.5404 18.5436 26.5404 20.2418 26.3617 21.5709C26.1778 22.9387 25.7903 24.0459 24.9172 24.9189C24.0442 25.792 22.937 26.1795 21.5692 26.3634C20.2401 26.5421 18.5419 26.5421 16.3978 26.5421H11.5996C9.4555 26.5421 7.75726 26.5421 6.42817 26.3634C5.06035 26.1795 3.95324 25.792 3.08015 24.9189C2.20706 24.0459 1.81959 22.9387 1.63569 21.5709C1.457 20.2418 1.45701 18.5436 1.45703 16.3995V16.2679C1.45701 14.1239 1.457 12.4256 1.63569 11.0965C1.81959 9.7287 2.20706 8.6216 3.08015 7.74851C3.89041 6.93824 4.90222 6.54617 6.13682 6.347C6.15072 5.78659 6.17935 5.28819 6.23828 4.84986C6.35811 3.95859 6.61744 3.18586 7.23425 2.56905C7.85106 1.95224 8.62379 1.69291 9.51506 1.57308C10.3677 1.45845 11.4478 1.45847 12.7694 1.4585ZM6.1237 8.12732C5.27088 8.29852 4.7304 8.57313 4.31759 8.98594C3.82384 9.47969 3.52789 10.1559 3.37008 11.3297C3.20889 12.5287 3.20703 14.1091 3.20703 16.3337C3.20703 18.5583 3.20889 20.1388 3.37008 21.3377C3.52789 22.5115 3.82384 23.1878 4.31759 23.6815C4.81133 24.1753 5.48758 24.4712 6.66135 24.629C7.8603 24.7902 9.44074 24.7921 11.6654 24.7921H16.332C18.5567 24.7921 20.1371 24.7902 21.336 24.629C22.5098 24.4712 23.1861 24.1753 23.6798 23.6815C24.1736 23.1878 24.4695 22.5115 24.6273 21.3377C24.7885 20.1388 24.7904 18.5583 24.7904 16.3337C24.7904 14.1091 24.7885 12.5287 24.6273 11.3297C24.4695 10.1559 24.1736 9.47969 23.6798 8.98594C23.267 8.57313 22.7265 8.29852 21.8737 8.12732V9.48108C21.8737 9.53491 21.8738 9.58809 21.8738 9.64064C21.8748 10.5577 21.8756 11.2841 21.5708 11.9348C21.266 12.5855 20.7075 13.0499 20.0024 13.6363C19.962 13.6699 19.9211 13.7039 19.8797 13.7383L18.9963 14.4745C17.9622 15.3363 17.1241 16.0347 16.3843 16.5105C15.6137 17.0061 14.8633 17.3192 13.9987 17.3192C13.1341 17.3192 12.3837 17.0061 11.6131 16.5105C10.8733 16.0347 10.0352 15.3363 9.00113 14.4745L8.11768 13.7383C8.07633 13.7039 8.03544 13.6699 7.99504 13.6363C7.28989 13.0499 6.73137 12.5855 6.4266 11.9348C6.12183 11.2841 6.12261 10.5577 6.12359 9.64063C6.12364 9.58808 6.1237 9.53491 6.1237 9.48108L6.1237 8.12732ZM9.74825 3.30748C9.05103 3.40122 8.70915 3.56903 8.47169 3.80649C8.23423 4.04395 8.06642 4.38583 7.97268 5.08305C7.87556 5.80544 7.8737 6.76717 7.8737 8.16683V9.48108C7.8737 10.6376 7.89303 10.9398 8.01138 11.1925C8.12973 11.4452 8.34953 11.6535 9.238 12.3939L10.0776 13.0936C11.166 14.0006 11.9217 14.6283 12.5597 15.0386C13.1773 15.4359 13.5961 15.5692 13.9987 15.5692C14.4013 15.5692 14.8201 15.4359 15.4377 15.0386C16.0757 14.6283 16.8314 14.0006 17.9198 13.0936L18.7594 12.3939C19.6479 11.6535 19.8677 11.4452 19.986 11.1925C20.1044 10.9398 20.1237 10.6376 20.1237 9.48108V8.16683C20.1237 6.76717 20.1218 5.80544 20.0247 5.08305C19.931 4.38583 19.7632 4.04395 19.5257 3.80649C19.2882 3.56903 18.9464 3.40122 18.2491 3.30748C17.5268 3.21036 16.565 3.2085 15.1654 3.2085H12.832C11.4324 3.2085 10.4706 3.21036 9.74825 3.30748ZM10.7904 7.00016C10.7904 6.51692 11.1821 6.12516 11.6654 6.12516H16.332C16.8153 6.12516 17.207 6.51692 17.207 7.00016C17.207 7.48341 16.8153 7.87516 16.332 7.87516H11.6654C11.1821 7.87516 10.7904 7.48341 10.7904 7.00016ZM11.957 10.5002C11.957 10.0169 12.3488 9.62516 12.832 9.62516H15.1654C15.6486 9.62516 16.0404 10.0169 16.0404 10.5002C16.0404 10.9834 15.6486 11.3752 15.1654 11.3752H12.832C12.3488 11.3752 11.957 10.9834 11.957 10.5002Z"
                                        fill="white"></path>
                                </svg>
                            </div>
                            <div class="cn_icon-box-info">
                                <h6>Email Address</h6>
                                <a
                                    href="mailto:<?php echo esc_attr($contact_email); ?>"><?php echo esc_html($contact_email); ?></a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($phone): ?>
                        <div class="cn_icon-box">
                            <div class="cn_icon-box-icon">
                                <svg width="28" height="28" viewBox="0 0 28 28" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path fill-rule="evenodd" clip-rule="evenodd"
                                        d="M6.68943 2.3835C8.10965 0.971347 10.4481 1.22271 11.6373 2.81123L13.1084 4.77636C14.0761 6.06889 13.9907 7.87537 12.8421 9.01744L12.5635 9.29442C12.5514 9.32976 12.5219 9.44084 12.5546 9.65195C12.6284 10.1274 13.0254 11.1358 14.692 12.793C16.358 14.4495 17.3736 14.8461 17.8557 14.9201C18.0743 14.9536 18.1885 14.9224 18.2239 14.91L18.6999 14.4367C19.721 13.4215 21.29 13.2317 22.5542 13.9189L24.7831 15.1306C26.6918 16.1681 27.174 18.7625 25.6103 20.3173L23.953 21.9652C23.4308 22.4844 22.7286 22.9174 21.8716 22.9972C19.761 23.194 14.8398 22.9429 9.66899 17.8015C4.84143 13.0013 3.91502 8.81534 3.79781 6.75287L4.6714 6.70322L3.79781 6.75287C3.73854 5.70997 4.23136 4.82762 4.85823 4.20431L6.68943 2.3835ZM10.2364 3.85999C9.6453 3.07046 8.54364 3.00768 7.92334 3.62446L6.09214 5.44526C5.70723 5.82799 5.52204 6.24977 5.54499 6.65358C5.63812 8.29227 6.3863 12.0696 10.9029 16.5605C15.6413 21.272 20.0176 21.4125 21.7091 21.2548C22.0547 21.2226 22.3984 21.043 22.7191 20.7242L24.3764 19.0763C25.0501 18.4065 24.9015 17.1868 23.9473 16.6681L21.7184 15.4564C21.1029 15.1218 20.3819 15.2322 19.9338 15.6777L19.4025 16.206L18.7855 15.5855C19.4025 16.206 19.4017 16.2069 19.4008 16.2077L19.3991 16.2094L19.3956 16.2128L19.388 16.2201L19.3709 16.236C19.3586 16.2472 19.3446 16.2595 19.3287 16.2726C19.2971 16.2989 19.2581 16.3286 19.2115 16.3598C19.1181 16.4224 18.9947 16.4904 18.8398 16.5482C18.5237 16.6659 18.107 16.7291 17.5904 16.6499C16.5792 16.4948 15.2395 15.8053 13.4581 14.0339C11.6772 12.2632 10.9819 10.9297 10.8253 9.92024C10.7452 9.40404 10.8091 8.98719 10.9282 8.67096C10.9866 8.51598 11.0554 8.39275 11.1186 8.29954C11.1501 8.25306 11.1801 8.21419 11.2065 8.18263C11.2197 8.16684 11.2321 8.15287 11.2433 8.14065L11.2594 8.12364L11.2667 8.1161L11.2702 8.11257L11.2719 8.11087C11.2727 8.11003 11.2736 8.1092 11.8905 8.72968L11.2736 8.1092L11.6082 7.77649C12.1082 7.27929 12.1783 6.45393 11.7075 5.82513L10.2364 3.85999Z"
                                        fill="white"></path>
                                </svg>
                            </div>
                            <div class="cn_icon-box-info">
                                <h6>Phone Number</h6>
                                <a href="tel:<?php echo esc_attr($phone); ?>"><?php echo esc_html($phone); ?></a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($linkedin_url): ?>
                        <div class="cn_icon-box">
                            <div class="cn_icon-box-icon">
                                <svg width="26" height="26" viewBox="0 0 26 26" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M6.6387 3.28147C6.64374 3.92364 6.39581 4.54199 5.94854 5.00282C5.50127 5.46364 4.89059 5.72991 4.24856 5.74404C3.60786 5.72076 3.00061 5.45221 2.55231 4.99389C2.10401 4.53557 1.84895 3.92252 1.83984 3.28147C1.86753 2.65673 2.13186 2.06593 2.57919 1.62895C3.02652 1.19196 3.62335 0.94153 4.24856 0.928467C4.87192 0.94178 5.46663 1.19284 5.91096 1.63026C6.35528 2.06768 6.61563 2.65839 6.6387 3.28147ZM2.09799 10.1195C2.09799 8.70432 2.9987 8.92532 4.24856 8.92532C5.49841 8.92532 6.38056 8.70432 6.38056 10.1195V23.9069C6.38056 25.3406 5.47984 25.0472 4.24856 25.0472C3.01727 25.0472 2.09799 25.3406 2.09799 23.9069V10.1195ZM10.0948 10.1213C10.0948 9.33018 10.3883 9.0349 10.847 8.9439C11.3057 8.8529 12.888 8.9439 13.4396 8.9439C13.9911 8.9439 14.2121 9.84461 14.1936 10.5243C14.6659 9.89196 15.2922 9.39102 16.0129 9.06919C16.7336 8.74736 17.5247 8.61536 18.3108 8.68575C19.0829 8.63861 19.8563 8.7544 20.5808 9.0256C21.3052 9.2968 21.9645 9.71734 22.5158 10.2599C23.0672 10.8025 23.4982 11.455 23.781 12.175C24.0637 12.895 24.1919 13.6664 24.1571 14.4392V23.8512C24.1571 25.2849 23.275 24.9915 22.0233 24.9915C20.7734 24.9915 19.8913 25.2849 19.8913 23.8512V16.4988C19.9236 16.1204 19.8742 15.7395 19.7463 15.382C19.6185 15.0244 19.4151 14.6986 19.1502 14.4265C18.8852 14.1545 18.5648 13.9427 18.2107 13.8055C17.8567 13.6683 17.4772 13.6089 17.0981 13.6313C16.7205 13.6214 16.3451 13.6918 15.9968 13.838C15.6485 13.9841 15.3352 14.2026 15.0778 14.4791C14.8203 14.7555 14.6246 15.0835 14.5035 15.4413C14.3825 15.7991 14.3388 16.1785 14.3756 16.5545V23.9069C14.3756 25.3406 13.4748 25.0472 12.225 25.0472C10.9751 25.0472 10.093 25.3406 10.093 23.9069V10.1195L10.0948 10.1213Z"
                                        stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                    </path>
                                </svg>
                            </div>
                            <div class="cn_icon-box-info">
                                <h6>LinkedIn</h6>
                                <a href="<?php echo esc_url($linkedin_url); ?>" target="_blank"
                                    rel="noopener noreferrer"><?php echo esc_html($applicant_name); ?></a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="cn_resume_body">
            <div class="cn_resume_body-left">
                <!-- Personal Summary -->
                <?php if (get_the_content()): ?>
                    <div class="cn_resume_body-summary cn_resume_body-card toggle-text">
                        <h2>Personal Summary</h2>
                        <?php the_content(); ?>
                    </div>
                <?php endif; ?>

                <!-- Career History -->
                <?php if ($experience && is_array($experience) && !empty($experience)): ?>
                    <div class="cn_resume_body-career cn_resume_body-card">
                        <h2>Career History</h2>
                        <?php foreach ($experience as $index => $exp):
                            $company = isset($exp['company']) ? $exp['company'] : '';
                            $title = isset($exp['title']) ? $exp['title'] : '';
                            $start_date = isset($exp['start_date']) ? $exp['start_date'] : '';
                            $end_date = isset($exp['end_date']) ? $exp['end_date'] : '';
                            $notes = isset($exp['notes']) ? $exp['notes'] : '';
                            $current = isset($exp['current']) ? $exp['current'] : false;

                            $date_range = '';
                            if ($start_date) {
                                $date_range = date('F Y', strtotime($start_date));
                                if ($current) {
                                    $date_range .= ' to present';
                                } elseif ($end_date) {
                                    $date_range .= ' - ' . date('F Y', strtotime($end_date));
                                }
                            }
                        ?>
                            <div>
                                <h3><?php echo esc_html($title); ?></h3>
                                <strong class="cn_resume-subtitle"><?php echo esc_html($company); ?></strong>
                                <?php if ($date_range): ?>
                                    <div>
                                        <span class="cn_resume-date"><?php echo esc_html($date_range); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($notes): ?>
                                    <p><?php echo wp_kses_post(wpautop($notes)); ?></p>
                                <?php endif; ?>
                            </div>
                            <?php if ($index < count($experience) - 1): ?>
                                <hr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Education -->
                <?php if ($education && is_array($education) && !empty($education)): ?>
                    <div class="cn_resume_body-education cn_resume_body-card">
                        <h2>Education</h2>
                        <?php foreach ($education as $index => $edu):
                            $institution = isset($edu['institution']) ? $edu['institution'] : '';
                            $certification = isset($edu['certification']) ? $edu['certification'] : '';
                            $start_date = isset($edu['start_date']) ? $edu['start_date'] : '';
                            $end_date = isset($edu['end_date']) ? $edu['end_date'] : '';
                            $notes = isset($edu['notes']) ? $edu['notes'] : '';
                            $complete = isset($edu['complete']) ? $edu['complete'] : false;

                            $date_display = '';
                            if ($end_date) {
                                $date_display = date('F Y', strtotime($end_date));
                            } elseif ($start_date) {
                                $date_display = date('F Y', strtotime($start_date));
                            }
                        ?>
                            <div>
                                <h3><?php echo esc_html($certification); ?></h3>
                                <strong class="cn_resume-subtitle"><?php echo esc_html($institution); ?></strong>
                                <?php if ($date_display): ?>
                                    <div>
                                        <span class="cn_resume-date"><?php echo esc_html($date_display); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($notes): ?>
                                    <div class="cn-toggle-text">
                                        <p><?php echo wp_kses_post(wpautop($notes)); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if ($index < count($education) - 1): ?>
                                <hr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Licenses & Certifications -->
                <?php if ($licenses && is_array($licenses) && !empty($licenses)): ?>
                    <div class="cn_resume_body-certification cn_resume_body-card">
                        <h2>Licences &amp; Certifications</h2>
                        <?php foreach ($licenses as $index => $license):
                            $name = isset($license['name']) ? $license['name'] : '';
                            $issuer = isset($license['issuer']) ? $license['issuer'] : '';
                            $expiry_date = isset($license['expiry_date']) ? $license['expiry_date'] : '';
                            $notes = isset($license['notes']) ? $license['notes'] : '';

                            $issue_display = '';
                            $expiry_display = '';
                            if ($expiry_date) {
                                $expiry_display = 'Expiry Date: ' . date('Y-m-d', strtotime($expiry_date));
                            }
                        ?>
                            <div>
                                <h3><?php echo esc_html($name); ?></h3>
                                <strong class="cn_resume-subtitle"><?php echo esc_html($issuer); ?></strong>
                                <div>
                                    <?php if ($expiry_display): ?>
                                        <span class="cn_resume-date"><?php echo esc_html($expiry_display); ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($notes): ?>
                                    <p><?php echo wp_kses_post(wpautop($notes)); ?></p>
                                <?php endif; ?>
                            </div>
                            <?php if ($index < count($licenses) - 1): ?>
                                <hr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Professional Skills -->
                <?php if ($skills && is_array($skills) && !empty($skills)): ?>
                    <div class="cn_resume_body-skills cn_resume_body-card">
                        <h2>Professional Skills</h2>
                        <?php foreach ($skills as $skill): ?>
                            <span class="cn_resume-skill"><?php echo esc_html($skill); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="cn_resume_body-right">
                <h2>More Information</h2>

                <!-- Professional Title / Role -->
                <?php if ($prof_title): ?>
                    <div class="cn_icon-box">
                        <div class="cn_icon-box-icon">
                            <svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="13.9987" cy="7.00016" r="4.66667" stroke="#101010" stroke-width="1.8"></circle>
                                <path
                                    d="M17.5013 15.5481C16.4205 15.302 15.239 15.1665 14.0013 15.1665C8.84664 15.1665 4.66797 17.517 4.66797 20.4165C4.66797 23.316 4.66797 25.6665 14.0013 25.6665C20.6366 25.6665 22.5547 24.4785 23.1092 22.7498"
                                    stroke="#101010" stroke-width="1.8"></path>
                                <circle cx="20.9987" cy="18.6667" r="4.66667" stroke="#101010" stroke-width="1.8"></circle>
                                <path d="M21 17.1111V20.2222" stroke="#101010" stroke-width="1.8" stroke-linecap="round"
                                    stroke-linejoin="round"></path>
                                <path d="M19.4434 18.6667L22.5545 18.6667" stroke="#101010" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round"></path>
                            </svg>
                        </div>
                        <div class="cn_icon-box-info">
                            <h6>Role</h6>
                            <span><?php echo esc_html($prof_title); ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Right to Work -->
                <?php if ($right_to_work): ?>
                    <div class="cn_icon-box">
                        <div class="cn_icon-box-icon">
                            <svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M3.5 12.1527C3.5 8.42217 3.5 6.55691 3.94043 5.9294C4.38087 5.30188 6.13471 4.70154 9.6424 3.50084L10.3107 3.27209C12.1391 2.6462 13.0534 2.33325 14 2.33325C14.9466 2.33325 15.8609 2.6462 17.6893 3.27209L18.3576 3.50084C21.8653 4.70154 23.6191 5.30188 24.0596 5.9294C24.5 6.55691 24.5 8.42217 24.5 12.1527C24.5 12.7162 24.5 13.3272 24.5 13.9898C24.5 20.5676 19.5545 23.7597 16.4517 25.1151C15.61 25.4827 15.1891 25.6666 14 25.6666C12.8109 25.6666 12.39 25.4827 11.5483 25.1151C8.44546 23.7597 3.5 20.5676 3.5 13.9898C3.5 13.3272 3.5 12.7162 3.5 12.1527Z"
                                    stroke="#101010" stroke-width="1.8"></path>
                                <circle cx="14.0013" cy="10.4998" r="2.33333" stroke="#101010" stroke-width="1.8"></circle>
                                <path
                                    d="M18.6654 17.4998C18.6654 18.7885 18.6654 19.8332 13.9987 19.8332C9.33203 19.8332 9.33203 18.7885 9.33203 17.4998C9.33203 16.2112 11.4214 15.1665 13.9987 15.1665C16.576 15.1665 18.6654 16.2112 18.6654 17.4998Z"
                                    stroke="#101010" stroke-width="1.8"></path>
                            </svg>
                        </div>
                        <div class="cn_icon-box-info">
                            <h6>Right to Work</h6>
                            <span><?php echo esc_html($right_to_work === 'australian' ? 'Australian Citizen' : 'Foreign Citizen'); ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Preferred Work Types -->
                <?php if ($work_types && is_array($work_types) && !empty($work_types)): ?>
                    <div class="cn_icon-box">
                        <div class="cn_icon-box-icon">
                            <svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M14 25.6667C20.4435 25.6667 25.6667 20.4435 25.6667 14C25.6667 7.55648 20.4435 2.33334 14 2.33334C7.55648 2.33334 2.33334 7.55648 2.33334 14C2.33334 20.4435 7.55648 25.6667 14 25.6667Z"
                                    stroke="#101010" stroke-width="1.8"></path>
                                <path d="M14 7V14L18.6667 18.6667" stroke="#101010" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round"></path>
                            </svg>
                        </div>
                        <div class="cn_icon-box-info">
                            <h6>Preferred Work Types</h6>
                            <span><?php echo esc_html(implode(', ', array_map('ucfirst', str_replace('_', ' ', $work_types)))); ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Resume File -->
                <?php if ($resume_url): ?>
                    <div class="cn_resume-file">
                        <h3>File Attachment</h3>
                        <a target="_blank" href="<?php echo esc_url($resume_url); ?>">
                            <span>Download Resume</span>
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M3 16.5V18.75C3 19.3467 3.23705 19.919 3.65901 20.341C4.08097 20.7629 4.65326 21 5.25 21H18.75C19.3467 21 19.919 20.7629 20.341 20.341C20.7629 19.919 21 19.3467 21 18.75V16.5M16.5 12L12 16.5M12 16.5L7.5 12M12 16.5V3"
                                    stroke="#FF8200" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                </path>
                            </svg>
                        </a>
                    </div>
                <?php endif; ?>

                <!-- Additional Links -->
                <?php if ($links && is_array($links) && !empty($links)): ?>
                    <div class="cn_resume-links">
                        <h3>Additional Links</h3>
                        <?php foreach ($links as $link):
                            $label = isset($link['label']) ? $link['label'] : '';
                            $url = isset($link['url']) ? $link['url'] : '';
                            $notes = isset($link['notes']) ? $link['notes'] : '';
                        ?>
                            <?php if ($url): ?>
                                <div class="cn_link-item">
                                    <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer">
                                        <?php echo esc_html($label ?: $url); ?>
                                    </a>
                                    <?php if ($notes): ?>
                                        <p><?php echo esc_html($notes); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<style>
    /* Container */
    .site-main {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px;
    }

    .single-resume-content {
        margin: 2rem 0;
    }

    /* Header Section */
    .cn_resume_header {
        position: relative;
        background: #f8f9fa;
        padding: 3rem 0;
        border-radius: 8px;
        margin-bottom: 2rem;
    }

    .cn_resume_header_img {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 8px;
        opacity: 0.1;
    }

    .cn_resume_header-content {
        position: relative;
        z-index: 2;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 2rem;
    }

    .cn_resume_header-left {
        display: flex;
        align-items: center;
        gap: 1.5rem;
    }

    .candidate_photo {
        width: 150px;
        height: 150px;
        object-fit: cover;
        border-radius: 8px;
    }

    .candidate_photo_placeholder {
        width: 150px;
        height: 150px;
        background: #e0e0e0;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 4rem;
        font-weight: bold;
        color: #666;
    }

    .candidate_title {
        font-size: 2.5rem;
        font-weight: bold;
        margin: 0 0 1rem 0;
        color: #333;
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .add-bookmark {
        color: #636363;
        text-decoration: none;
    }

    .add-bookmark:hover {
        color: #0073aa;
    }

    .candidate_location_wrapper {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: #666;
        font-size: 1.1rem;
    }

    /* Header Right - Contact Info */
    .cn_resume_header-right {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .cn_icon-box {
        display: flex;
        align-items: center;
        gap: 1rem;
        background: white;
        padding: 1rem;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .cn_icon-box-icon {
        background: #0073aa;
        border-radius: 8px;
        padding: 0.75rem;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .cn_icon-box-info h6 {
        margin: 0 0 0.25rem 0;
        color: #333;
        font-size: 1rem;
        font-weight: 600;
    }

    .cn_icon-box-info a,
    .cn_icon-box-info span {
        color: #666;
        text-decoration: none;
        font-size: 0.95rem;
    }

    .cn_icon-box-info a:hover {
        color: #0073aa;
        text-decoration: underline;
    }

    /* Body Section */
    .cn_resume_body {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 3rem;
    }

    .cn_resume_body-card {
        background: white;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 2rem;
        margin-bottom: 2rem;
    }

    .cn_resume_body-card h2 {
        color: #333;
        font-size: 1.5rem;
        margin-bottom: 1.5rem;
        font-weight: 600;
        border-bottom: 2px solid #0073aa;
        padding-bottom: 0.5rem;
    }

    .cn_resume_body-card h3 {
        color: #333;
        font-size: 1.2rem;
        margin: 0 0 0.5rem 0;
        font-weight: 600;
    }

    .cn_resume-subtitle {
        color: #0073aa;
        font-size: 1rem;
        display: block;
        margin-bottom: 0.5rem;
    }

    .cn_resume-date {
        color: #666;
        font-size: 0.9rem;
        font-style: italic;
    }

    .cn_resume_body-card hr {
        border: none;
        border-top: 1px solid #e0e0e0;
        margin: 1.5rem 0;
    }

    .cn_resume_body-card p {
        color: #555;
        line-height: 1.6;
        margin: 1rem 0;
    }

    /* Skills */
    .cn_resume-skill {
        display: inline-block;
        background: #f0f0f0;
        color: #333;
        padding: 0.5rem 1rem;
        margin: 0.25rem 0.25rem 0.25rem 0;
        border-radius: 20px;
        font-size: 0.9rem;
    }

    /* Resume File */
    .cn_resume-file {
        background: white;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 1.5rem;
        margin-bottom: 2rem;
    }

    .cn_resume-file h3 {
        color: #333;
        font-size: 1.2rem;
        margin-bottom: 1rem;
        font-weight: 600;
    }

    .cn_resume-file a {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: #FF8200;
        text-decoration: none;
        font-weight: 500;
    }

    .cn_resume-file a:hover {
        text-decoration: underline;
    }

    /* Links */
    .cn_resume-links {
        background: white;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 1.5rem;
        margin-bottom: 2rem;
    }

    .cn_resume-links h3 {
        color: #333;
        font-size: 1.2rem;
        margin-bottom: 1rem;
        font-weight: 600;
    }

    .cn_link-item {
        margin-bottom: 1rem;
    }

    .cn_link-item a {
        color: #0073aa;
        text-decoration: none;
        font-weight: 500;
    }

    .cn_link-item a:hover {
        text-decoration: underline;
    }

    .cn_link-item p {
        color: #666;
        font-size: 0.9rem;
        margin: 0.25rem 0 0 0;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .cn_resume_header-content {
            flex-direction: column;
            text-align: center;
            gap: 1.5rem;
        }

        .cn_resume_header-left {
            flex-direction: column;
            text-align: center;
        }

        .candidate_title {
            font-size: 2rem;
            flex-direction: column;
            gap: 0.5rem;
        }

        .cn_resume_body {
            grid-template-columns: 1fr;
            gap: 2rem;
        }

        .cn_resume_header {
            padding: 2rem 0;
        }
    }
</style>

<?php
get_footer();
