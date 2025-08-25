<?php

namespace CareerNest\Admin;

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

class Meta_Boxes
{
    public function hooks(): void
    {
        add_action('add_meta_boxes', [$this, 'register_meta_boxes']);
        add_action('save_post', [$this, 'save_job_meta']);
        add_action('save_post', [$this, 'save_employer_meta']);
        add_action('save_post', [$this, 'save_applicant_meta']);
        add_action('save_post', [$this, 'save_application_meta']);
    }

    public function register_meta_boxes(): void
    {
        add_meta_box('careernest_job_details', __('Job Details', 'careernest'), [$this, 'render_job_details'], 'job_listing', 'normal', 'high');
        add_meta_box('careernest_employer_details', __('Employer Details', 'careernest'), [$this, 'render_employer_details'], 'employer', 'normal', 'default');
        add_meta_box('careernest_applicant_details', __('Applicant Details', 'careernest'), [$this, 'render_applicant_details'], 'applicant', 'normal', 'default');
        add_meta_box('careernest_employer_team', __('Employer Team Members', 'careernest'), [$this, 'render_employer_team_members'], 'employer', 'side', 'low');
        add_meta_box('careernest_applicant_skills', __('Skills', 'careernest'), [$this, 'render_applicant_skills'], 'applicant', 'side', 'default');
        add_meta_box('careernest_applicant_prefs', __('Work Preferences', 'careernest'), [$this, 'render_applicant_prefs'], 'applicant', 'side', 'default');
        add_meta_box('careernest_application_details', __('Application Details', 'careernest'), [$this, 'render_application_details'], 'job_application', 'normal', 'default');
        add_meta_box('careernest_application_status', __('Application Status', 'careernest'), [$this, 'render_application_status'], 'job_application', 'side', 'high');
    }

    private function get_employers_for_dropdown(): array
    {
        $args = [
            'post_type'      => 'employer',
            'posts_per_page' => 200,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'fields'         => 'ids',
        ];
        $ids = get_posts($args);
        $out = [];
        foreach ($ids as $id) {
            $out[$id] = get_the_title($id);
        }
        return $out;
    }

    public function render_job_details(\WP_Post $post): void
    {
        wp_nonce_field('careernest_job_meta', 'careernest_job_meta_nonce');

        $employer_id      = (int) get_post_meta($post->ID, '_employer_id', true);
        $job_location     = (string) get_post_meta($post->ID, '_job_location', true);
        $job_place_id     = (string) get_post_meta($post->ID, '_job_location_place_id', true);
        $job_lat          = (string) get_post_meta($post->ID, '_job_location_lat', true);
        $job_lng          = (string) get_post_meta($post->ID, '_job_location_lng', true);
        $remote_position  = (bool) get_post_meta($post->ID, '_remote_position', true);
        $opening_date     = (string) get_post_meta($post->ID, '_opening_date', true);
        $closing_date     = (string) get_post_meta($post->ID, '_closing_date', true);
        $salary_range     = (string) get_post_meta($post->ID, '_salary_range', true);
        $salary           = get_post_meta($post->ID, '_salary', true);
        $apply_externally = (bool) get_post_meta($post->ID, '_apply_externally', true);
        $external_apply   = (string) get_post_meta($post->ID, '_external_apply', true);
        $posted_by        = (int) get_post_meta($post->ID, '_posted_by', true);
        $position_filled  = (bool) get_post_meta($post->ID, '_position_filled', true);

        $employers = $this->get_employers_for_dropdown();
        $team_users_raw = get_users(['role' => 'employer_team', 'fields' => ['ID', 'display_name', 'user_email'], 'number' => 1000]);
        $team_by_employer = [];
        foreach ($team_users_raw as $u) {
            $eid = (int) get_user_meta($u->ID, '_employer_id', true);
            if ($eid > 0) {
                $team_by_employer[$eid][] = ['id' => (int) $u->ID, 'label' => $u->display_name . ' (' . $u->user_email . ')'];
            }
        }

        echo '<table class="form-table">';
        // Status moved to sidebar meta box for visibility
        // Employer dropdown
        echo '<tr><th><label for="careernest_employer_id">' . esc_html__('Employer', 'careernest') . '</label></th><td>';
        echo '<select id="careernest_employer_id" name="careernest_employer_id">';
        echo '<option value="">' . esc_html__('Select employer…', 'careernest') . '</option>';
        foreach ($employers as $id => $label) {
            echo '<option value="' . esc_attr((string) $id) . '"' . selected($employer_id, $id, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '</td></tr>';
        if (current_user_can('manage_options')) {
            echo '<tr><th><label for="careernest_posted_by">' . esc_html__('Job Posted By', 'careernest') . '</label></th><td>';
            echo '<select id="careernest_posted_by" name="careernest_posted_by">';
            if ($employer_id && ! empty($team_by_employer[$employer_id])) {
                echo '<option value="">' . esc_html__('Select user…', 'careernest') . '</option>';
                foreach ($team_by_employer[$employer_id] as $row) {
                    $uid = (int) $row['id'];
                    echo '<option value="' . esc_attr((string) $uid) . '"' . selected($posted_by, $uid, false) . '>' . esc_html($row['label']) . '</option>';
                }
            } else {
                echo '<option value="">' . esc_html__('Select employer first (or no team assigned)', 'careernest') . '</option>';
            }
            echo '</select>';
            echo '</td></tr>';
        }

        // Location + Remote (with Google Maps autocomplete + pick-on-map)
        echo '<tr><th><label for="careernest_job_location">' . esc_html__('Job Location', 'careernest') . '</label></th><td>';
        echo '<input type="text" id="careernest_job_location" name="careernest_job_location" class="regular-text" value="' . esc_attr($job_location) . '" style=" margin-bottom: 8px;" /> ';
        // Hidden metadata fields
        echo '<input type="hidden" id="careernest_job_place_id" name="careernest_job_place_id" value="' . esc_attr($job_place_id) . '" />';
        echo '<input type="hidden" id="careernest_job_lat" name="careernest_job_lat" value="' . esc_attr($job_lat) . '" />';
        echo '<input type="hidden" id="careernest_job_lng" name="careernest_job_lng" value="' . esc_attr($job_lng) . '" />';
        // View on map
        $job_gmaps_query = '';
        if ($job_lat !== '' && $job_lng !== '') {
            $job_gmaps_query = $job_lat . ',' . $job_lng;
        } elseif ($job_location !== '') {
            $job_gmaps_query = $job_location;
        }
        if ($job_gmaps_query !== '') {
            $job_gmaps_url = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($job_gmaps_query);
            echo ' <a class="button button-small" style="margin-right:8px;" target="_blank" rel="noreferrer noopener" href="' . esc_url($job_gmaps_url) . '">' . esc_html__('View on map', 'careernest') . '</a>';
        }
        // Pick on map button
        echo ' <button type="button" class="button button-small" id="careernest_job_pick_map" style="margin-right:6px;">' . esc_html__('Pick on map', 'careernest') . '</button>';
        echo ' <span style="margin-left:8px">';
        echo '<label><input type="checkbox" name="careernest_remote_position" value="1" ' . checked($remote_position, true, false) . ' /> ' . esc_html__('Remote position', 'careernest') . '</label>';
        echo '</span>';
        echo '<p class="description" style="margin-top:6px">' . esc_html__('Autocomplete and map picker require a Google Maps API key in CareerNest Settings.', 'careernest') . '</p>';
        // Modal for map picker
        echo '<div id="careernest_job_map_modal" class="cn-map-modal" style="display:none">';
        echo '  <div class="cn-map-dialog" role="dialog" aria-modal="true" aria-labelledby="cn-map-title-job">';
        echo '    <div class="cn-map-header">';
        echo '      <h2 id="cn-map-title-job">' . esc_html__('Pick Location', 'careernest') . '</h2>';
        echo '      <button type="button" class="button-link cn-map-close" id="careernest_job_map_cancel" aria-label="' . esc_attr__('Close', 'careernest') . '">×</button>';
        echo '    </div>';
        echo '    <div class="cn-map-body">';
        echo '      <div id="careernest_job_map_canvas" class="cn-map-canvas"></div>';
        echo '      <p class="description" style="margin:6px 0 0">' . esc_html__('Click on the map to set location. Drag to adjust.', 'careernest') . '</p>';
        echo '    </div>';
        echo '    <div class="cn-map-footer">';
        echo '      <button type="button" class="button button-primary" id="careernest_job_map_use">' . esc_html__('Use this location', 'careernest') . '</button>';
        echo '      <button type="button" class="button" id="careernest_job_map_cancel_2">' . esc_html__('Cancel', 'careernest') . '</button>';
        echo '    </div>';
        echo '  </div>';
        echo '</div>';
        echo '</td></tr>';

        // Dates
        echo '<tr><th>' . esc_html__('Opening Date', 'careernest') . '</th><td>';
        echo '<input type="date" name="careernest_opening_date" value="' . esc_attr($opening_date) . '" />';
        echo '</td></tr>';
        echo '<tr><th>' . esc_html__('Closing Date', 'careernest') . '</th><td>';
        $min_close = date_i18n('Y-m-d');
        echo '<input type="date" name="careernest_closing_date" value="' . esc_attr($closing_date) . '" min="' . esc_attr($min_close) . '" />';
        echo '</td></tr>';

        // Salary: toggle (admin only) and mutually exclusive fields
        $salary_mode = (string) get_post_meta($post->ID, '_salary_mode', true);
        if ($salary_mode !== 'numeric') {
            $salary_mode = 'range';
        }
        if (current_user_can('manage_options')) {
            echo '<tr><th>' . esc_html__('Salary Mode', 'careernest') . '</th><td>';
            echo '<label><input type="radio" name="careernest_salary_mode" value="range" ' . checked($salary_mode, 'range', false) . ' /> ' . esc_html__('Salary Range', 'careernest') . '</label> ';
            echo '<label style="margin-left:12px;"><input type="radio" name="careernest_salary_mode" value="numeric" ' . checked($salary_mode, 'numeric', false) . ' /> ' . esc_html__('Numeric Salary', 'careernest') . '</label>';
            echo '</td></tr>';
        }
        // Range field row
        $range_style = (current_user_can('manage_options') && $salary_mode === 'numeric') ? ' style="display:none;"' : '';
        echo '<tr class="cn-salary-range-row"' . $range_style . '><th><label for="careernest_salary_range">' . esc_html__('Salary Range', 'careernest') . '</label></th><td>';
        echo '<input type="text" id="careernest_salary_range" name="careernest_salary_range" class="regular-text" value="' . esc_attr($salary_range) . '" />';
        echo '</td></tr>';
        // Numeric salary row (admin only)
        if (current_user_can('manage_options')) {
            $num_style = ($salary_mode === 'numeric') ? '' : ' style="display:none;"';
            echo '<tr class="cn-salary-numeric-row"' . $num_style . '><th><label for="careernest_salary">' . esc_html__('Salary (numeric)', 'careernest') . '</label></th><td>';
            echo '<input type="number" step="0.01" id="careernest_salary" name="careernest_salary" class="small-text" value="' . esc_attr((string) $salary) . '" />';
            echo '</td></tr>';
        }

        // Apply externally
        echo '<tr><th>' . esc_html__('Apply Externally', 'careernest') . '</th><td>';
        echo '<label><input type="checkbox" id="careernest_apply_externally" name="careernest_apply_externally" value="1" ' . checked($apply_externally, true, false) . ' /> ' . esc_html__('Check if applications are handled externally (link or email).', 'careernest') . '</label>';
        echo '<div id="careernest_external_container" style="margin-top:8px;' . ($apply_externally ? '' : 'display:none;') . '">';
        echo '<input type="text" id="careernest_external_apply" name="careernest_external_apply" class="regular-text" placeholder="https://example.com/apply or jobs@example.com" value="' . esc_attr($external_apply) . '" />';
        echo '</div>';
        echo '</td></tr>';

        // Admin-only: Position filled
        if (current_user_can('manage_options')) {
            echo '<tr><th>' . esc_html__('Position Filled', 'careernest') . '</th><td>';
            echo '<label><input type="checkbox" name="careernest_position_filled" value="1" ' . checked($position_filled, true, false) . ' /> ' . esc_html__('Mark as filled', 'careernest') . '</label>';
            echo '</td></tr>';
        }
        // WYSIWYG sections
        $overview         = (string) get_post_meta($post->ID, '_job_overview', true);
        $who_we_are       = (string) get_post_meta($post->ID, '_job_who_we_are', true);
        $what_we_offer    = (string) get_post_meta($post->ID, '_job_what_we_offer', true);
        $responsibilities = (string) get_post_meta($post->ID, '_job_responsibilities', true);
        $how_to_apply     = (string) get_post_meta($post->ID, '_job_how_to_apply', true);

        echo '<tr class="cn-span-2 cn-section"><th><label for="careernest_job_overview">' . esc_html__('Overview', 'careernest') . '</label></th><td>';
        echo '<p class="description" style="margin:0 0 6px">' . esc_html__('Provide a high-level summary of the role and impact.', 'careernest') . '</p>';
        ob_start();
        wp_editor($overview, 'careernest_job_overview', [
            'textarea_name' => 'careernest_job_overview',
            'textarea_rows' => 6,
            'media_buttons' => false,
            'teeny' => false,
            'quicktags' => true,
            'tinymce' => [
                'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,|,bullist,numlist,|,link,unlink,|,undo,redo',
                'toolbar2' => '',
                'block_formats' => 'Paragraph=p;Heading 2=h2;Heading 3=h3;Heading 4=h4;Heading 5=h5;Heading 6=h6'
            ]
        ]);
        echo ob_get_clean();
        echo '</td></tr>';

        echo '<tr class="cn-span-2 cn-section"><th><label for="careernest_job_who_we_are">' . esc_html__('Who we are?', 'careernest') . '</label></th><td>';
        echo '<p class="description" style="margin:0 0 6px">' . esc_html__('Introduce the company, culture, and mission.', 'careernest') . '</p>';
        ob_start();
        wp_editor($who_we_are, 'careernest_job_who_we_are', [
            'textarea_name' => 'careernest_job_who_we_are',
            'textarea_rows' => 6,
            'media_buttons' => false,
            'teeny' => false,
            'quicktags' => true,
            'tinymce' => [
                'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,|,bullist,numlist,|,link,unlink,|,undo,redo',
                'toolbar2' => '',
                'block_formats' => 'Paragraph=p;Heading 2=h2;Heading 3=h3;Heading 4=h4;Heading 5=h5;Heading 6=h6'
            ]
        ]);
        echo ob_get_clean();
        echo '</td></tr>';

        echo '<tr class="cn-span-2 cn-section"><th><label for="careernest_job_what_we_offer">' . esc_html__('What do we offer?', 'careernest') . '</label></th><td>';
        echo '<p class="description" style="margin:0 0 6px">' . esc_html__('Outline compensation, benefits, growth, and perks.', 'careernest') . '</p>';
        ob_start();
        wp_editor($what_we_offer, 'careernest_job_what_we_offer', [
            'textarea_name' => 'careernest_job_what_we_offer',
            'textarea_rows' => 6,
            'media_buttons' => false,
            'teeny' => false,
            'quicktags' => true,
            'tinymce' => [
                'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,|,bullist,numlist,|,link,unlink,|,undo,redo',
                'toolbar2' => '',
                'block_formats' => 'Paragraph=p;Heading 2=h2;Heading 3=h3;Heading 4=h4;Heading 5=h5;Heading 6=h6'
            ]
        ]);
        echo ob_get_clean();
        echo '</td></tr>';

        echo '<tr class="cn-span-2 cn-section"><th><label for="careernest_job_responsibilities">' . esc_html__('Key responsibilities', 'careernest') . '</label></th><td>';
        echo '<p class="description" style="margin:0 0 6px">' . esc_html__('List main responsibilities and expectations.', 'careernest') . '</p>';
        ob_start();
        wp_editor($responsibilities, 'careernest_job_responsibilities', [
            'textarea_name' => 'careernest_job_responsibilities',
            'textarea_rows' => 6,
            'media_buttons' => false,
            'teeny' => false,
            'quicktags' => true,
            'tinymce' => [
                'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,|,bullist,numlist,|,link,unlink,|,undo,redo',
                'toolbar2' => '',
                'block_formats' => 'Paragraph=p;Heading 2=h2;Heading 3=h3;Heading 4=h4;Heading 5=h5;Heading 6=h6'
            ]
        ]);
        echo ob_get_clean();
        echo '</td></tr>';

        echo '<tr class="cn-span-2 cn-section"><th><label for="careernest_job_how_to_apply">' . esc_html__('How to apply?', 'careernest') . '</label></th><td>';
        echo '<p class="description" style="margin:0 0 6px">' . esc_html__('Explain the application process and required materials.', 'careernest') . '</p>';
        ob_start();
        wp_editor($how_to_apply, 'careernest_job_how_to_apply', [
            'textarea_name' => 'careernest_job_how_to_apply',
            'textarea_rows' => 6,
            'media_buttons' => false,
            'teeny' => false,
            'quicktags' => true,
            'tinymce' => [
                'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,|,bullist,numlist,|,link,unlink,|,undo,redo',
                'toolbar2' => '',
                'block_formats' => 'Paragraph=p;Heading 2=h2;Heading 3=h3;Heading 4=h4;Heading 5=h5;Heading 6=h6'
            ]
        ]);
        echo ob_get_clean();
        echo '</td></tr>';

        echo '</table>';
    }

    public function render_applicant_skills(\WP_Post $post): void
    {
        $skills = get_post_meta($post->ID, '_skills', true);
        $skills = is_array($skills) ? array_map('sanitize_text_field', $skills) : [];
        echo '<p class="description" style="margin:0 0 6px">' . esc_html__('Type a skill and press Enter. Add multiple skills.', 'careernest') . '</p>';
        echo '<input type="text" id="careernest_skill_input" class="regular-text" placeholder="' . esc_attr__('e.g., PHP, React, SQL', 'careernest') . '" autocomplete="off" />';
        echo '<div id="careernest_skill_pills" class="cn-skill-pills" style="margin-top:8px">';
        foreach ($skills as $sk) {
            if ($sk === '') {
                continue;
            }
            echo '<div class="cn-skill-pill" data-skill="' . esc_attr(strtolower($sk)) . '">';
            echo '<span>' . esc_html($sk) . '</span>';
            echo '<button type="button" class="cn-skill-remove" aria-label="' . esc_attr__('Remove', 'careernest') . '">×</button>';
            echo '<input type="hidden" name="careernest_skills[]" value="' . esc_attr($sk) . '" />';
            echo '</div>';
        }
        echo '</div>';
    }

    public function render_application_details(\WP_Post $post): void
    {
        wp_nonce_field('careernest_application_meta', 'careernest_application_meta_nonce');
        $applicant_id = (int) get_post_meta($post->ID, '_applicant_id', true);
        $job_id       = (int) get_post_meta($post->ID, '_job_id', true);
        $resume_id    = (int) get_post_meta($post->ID, '_resume_id', true);
        $cover_letter = (string) get_post_meta($post->ID, '_cover_letter', true);
        $app_status   = (string) get_post_meta($post->ID, '_app_status', true);

        $applicants = get_posts(['post_type' => 'applicant', 'posts_per_page' => 200, 'orderby' => 'title', 'order' => 'ASC', 'fields' => 'ids']);
        $jobs = get_posts(['post_type' => 'job_listing', 'posts_per_page' => 200, 'orderby' => 'date', 'order' => 'DESC', 'fields' => 'ids']);
        $contact_email = '';
        if ($applicant_id) {
            $uid = (int) get_post_meta($applicant_id, '_user_id', true);
            if ($uid) {
                $u = get_user_by('id', $uid);
                if ($u) {
                    $contact_email = (string) $u->user_email;
                }
            }
        }

        echo '<table class="form-table">';
        echo '<tr><th><label for="careernest_application_applicant">' . esc_html__('Applicant', 'careernest') . '</label></th><td>';
        echo '<select id="careernest_application_applicant" name="careernest_application_applicant">';
        echo '<option value="">' . esc_html__('Select applicant…', 'careernest') . '</option>';
        foreach ($applicants as $aid) {
            echo '<option value="' . esc_attr((int)$aid) . '"' . selected($applicant_id, (int)$aid, false) . '>' . esc_html(get_the_title($aid)) . '</option>';
        }
        echo '</select>';
        echo '</td></tr>';

        echo '<tr><th><label for="careernest_application_job">' . esc_html__('Job', 'careernest') . '</label></th><td>';
        echo '<select id="careernest_application_job" name="careernest_application_job">';
        echo '<option value="">' . esc_html__('Select job…', 'careernest') . '</option>';
        foreach ($jobs as $jid) {
            echo '<option value="' . esc_attr((int)$jid) . '"' . selected($job_id, (int)$jid, false) . '>' . esc_html(get_the_title($jid)) . '</option>';
        }
        echo '</select>';
        echo '</td></tr>';

        echo '<tr><th>' . esc_html__('Contact Email', 'careernest') . '</th><td>';
        echo $contact_email ? '<code>' . esc_html($contact_email) . '</code>' : '&mdash;';
        echo '<p class="description">' . esc_html__('Derived from the linked applicant’s user.', 'careernest') . '</p>';
        echo '</td></tr>';

        $resume_label = $resume_id ? get_the_title($resume_id) : '';
        $resume_url   = $resume_id ? wp_get_attachment_url($resume_id) : '';
        echo '<tr><th><label>' . esc_html__('Resume File', 'careernest') . '</label></th><td>';
        echo '<input type="hidden" id="careernest_app_resume_id" name="careernest_app_resume_id" value="' . esc_attr((string)($resume_id ?: 0)) . '" />';
        echo '<div id="careernest_app_resume_preview">';
        if ($resume_id && $resume_url) {
            echo '<a href="' . esc_url($resume_url) . '" target="_blank" rel="noreferrer noopener">' . esc_html($resume_label ?: basename($resume_url)) . '</a>';
        } else {
            echo '<em>' . esc_html__('No file selected', 'careernest') . '</em>';
        }
        echo '</div>';
        echo '<p><button type="button" class="button" id="careernest_app_resume_select">' . esc_html__('Select/Upload Resume', 'careernest') . '</button> ';
        echo '<button type="button" class="button" id="careernest_app_resume_clear">' . esc_html__('Clear', 'careernest') . '</button></p>';
        echo '</td></tr>';

        echo '<tr class="cn-span-2"><th><label for="careernest_cover_letter">' . esc_html__('Cover Letter', 'careernest') . '</label></th><td>';
        ob_start();
        wp_editor($cover_letter, 'careernest_cover_letter', [
            'textarea_name' => 'careernest_cover_letter',
            'textarea_rows' => 8,
            'media_buttons' => false,
            'teeny' => false,
            'quicktags' => true,
            'tinymce' => [
                'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,|,bullist,numlist,|,link,unlink,|,undo,redo',
                'toolbar2' => '',
                'block_formats' => 'Paragraph=p;Heading 2=h2;Heading 3=h3;Heading 4=h4;Heading 5=h5;Heading 6=h6'
            ]
        ]);
        echo ob_get_clean();
        echo '</td></tr>';

        echo '</table>';
    }

    public function render_application_status(\WP_Post $post): void
    {
        $app_status = (string) get_post_meta($post->ID, '_app_status', true);
        $statuses = [
            'new'            => __('New', 'careernest'),
            'interviewed'    => __('Interviewed', 'careernest'),
            'offer_extended' => __('Offer Extended', 'careernest'),
            'hired'          => __('Hired', 'careernest'),
            'rejected'       => __('Rejected', 'careernest'),
            'archived'       => __('Archived', 'careernest'),
        ];
        if ('' === $app_status || ! array_key_exists($app_status, $statuses)) {
            $app_status = 'new';
        }
        echo '<p><label for="careernest_app_status" class="screen-reader-text">' . esc_html__('Application Status', 'careernest') . '</label>';
        echo '<select id="careernest_app_status" name="careernest_app_status" style="width:100%">';
        foreach ($statuses as $val => $label) {
            echo '<option value="' . esc_attr($val) . '"' . selected($app_status, $val, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></p>';
    }

    public function save_application_meta(int $post_id): void
    {
        if (get_post_type($post_id) !== 'job_application') {
            return;
        }
        if (! isset($_POST['careernest_application_meta_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['careernest_application_meta_nonce'] ?? '')), 'careernest_application_meta')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (! current_user_can('edit_post', $post_id)) {
            return;
        }

        $applicant_id = isset($_POST['careernest_application_applicant']) ? absint($_POST['careernest_application_applicant']) : 0;
        $job_id       = isset($_POST['careernest_application_job']) ? absint($_POST['careernest_application_job']) : 0;
        update_post_meta($post_id, '_applicant_id', $applicant_id);
        update_post_meta($post_id, '_job_id', $job_id);

        if ($applicant_id) {
            $target_title = get_the_title($applicant_id);
            $current = get_post_field('post_title', $post_id);
            if ($target_title && $current !== $target_title) {
                remove_action('save_post', [$this, 'save_application_meta']);
                wp_update_post(['ID' => $post_id, 'post_title' => $target_title]);
                add_action('save_post', [$this, 'save_application_meta']);
            }
        }

        $resume_id = isset($_POST['careernest_app_resume_id']) ? absint($_POST['careernest_app_resume_id']) : 0;
        if ($resume_id > 0) {
            update_post_meta($post_id, '_resume_id', $resume_id);
        } else {
            delete_post_meta($post_id, '_resume_id');
        }

        $cover = isset($_POST['careernest_cover_letter']) ? wp_kses_post(wp_unslash($_POST['careernest_cover_letter'])) : '';
        if ($cover !== '') {
            update_post_meta($post_id, '_cover_letter', $cover);
        } else {
            delete_post_meta($post_id, '_cover_letter');
        }

        // Application Status
        $allowed = ['new', 'interviewed', 'offer_extended', 'hired', 'rejected', 'archived'];
        if (isset($_POST['careernest_app_status'])) {
            $st = sanitize_text_field(wp_unslash($_POST['careernest_app_status']));
            if (in_array($st, $allowed, true)) {
                update_post_meta($post_id, '_app_status', $st);
            }
        } else {
            // Ensure a default on first save
            $existing = get_post_meta($post_id, '_app_status', true);
            if ('' === (string) $existing) {
                update_post_meta($post_id, '_app_status', 'new');
            }
        }
    }

    public function render_applicant_prefs(\WP_Post $post): void
    {
        $work_types = get_post_meta($post->ID, '_work_types', true);
        $work_types = is_array($work_types) ? array_map('sanitize_text_field', $work_types) : [];
        $all_work_types = [
            'full_time'  => __('Full-time', 'careernest'),
            'part_time'  => __('Part-time', 'careernest'),
            'contract'   => __('Contract', 'careernest'),
            'temporary'  => __('Temporary', 'careernest'),
            'internship' => __('Internship', 'careernest'),
            'remote'     => __('Remote', 'careernest'),
            'on_site'    => __('On-site', 'careernest'),
            'hybrid'     => __('Hybrid', 'careernest'),
        ];
        echo '<p class="description" style="margin:0 0 6px">' . esc_html__('Select all work types you prefer.', 'careernest') . '</p>';
        echo '<select id="careernest_work_types" name="careernest_work_types[]" multiple size="8" style="min-width:100%;max-width:100%">';
        foreach ($all_work_types as $val => $label) {
            $sel = in_array($val, $work_types, true);
            echo '<option value="' . esc_attr($val) . '"' . selected($sel, true, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__('Hold Ctrl/Command to select multiple.', 'careernest') . '</p>';
    }

    public function render_employer_details(\WP_Post $post): void
    {
        wp_nonce_field('careernest_employer_meta', 'careernest_employer_meta_nonce');
        $website          = (string) get_post_meta($post->ID, '_website', true);
        $location         = (string) get_post_meta($post->ID, '_location', true);
        $em_loc_place_id  = (string) get_post_meta($post->ID, '_location_place_id', true);
        $em_loc_lat       = (string) get_post_meta($post->ID, '_location_lat', true);
        $em_loc_lng       = (string) get_post_meta($post->ID, '_location_lng', true);
        $tagline          = (string) get_post_meta($post->ID, '_tagline', true);
        $industry_desc    = (string) get_post_meta($post->ID, '_industry_description', true);
        $about            = (string) get_post_meta($post->ID, '_about', true);
        $mission          = (string) get_post_meta($post->ID, '_mission', true);
        $spotlight        = (string) get_post_meta($post->ID, '_spotlight', true);
        $interested       = (string) get_post_meta($post->ID, '_interested_in_working', true);
        $specialities     = (string) get_post_meta($post->ID, '_specialities', true);
        $company_size     = (string) get_post_meta($post->ID, '_company_size', true);
        $founded_year     = (string) get_post_meta($post->ID, '_founded_year', true);
        if ($founded_year === '') {
            $old_date = (string) get_post_meta($post->ID, '_date_founded', true);
            if ($old_date !== '') {
                $founded_year = substr($old_date, 0, 4);
            }
        }
        $current_year = (int) gmdate('Y');
        echo '<table class="form-table">';
        echo '<tr><th><label for="careernest_website">' . esc_html__('Company Website', 'careernest') . '</label></th><td>';
        echo '<input type="url" id="careernest_website" name="careernest_website" class="regular-text" value="' . esc_attr($website) . '" placeholder="https://example.com" />';
        echo '</td></tr>';
        echo '<tr><th><label for="careernest_tagline">' . esc_html__('Company Tagline', 'careernest') . '</label></th><td>';
        echo '<input type="text" id="careernest_tagline" name="careernest_tagline" class="regular-text" value="' . esc_attr($tagline) . '" placeholder="' . esc_attr__('e.g., Building careers that matter', 'careernest') . '" />';
        echo '</td></tr>';
        echo '<tr><th><label for="careernest_location">' . esc_html__('Location', 'careernest') . '</label></th><td>';
        echo '<input type="text" id="careernest_location" name="careernest_location" class="regular-text" value="' . esc_attr($location) . '" placeholder="' . esc_attr__('e.g., Toronto, ON (HQ)', 'careernest') . '" style=" margin-bottom: 8px;" />';
        // Hidden fields for Google Maps metadata
        echo '<input type="hidden" id="careernest_employer_place_id" name="careernest_employer_place_id" value="' . esc_attr($em_loc_place_id) . '" />';
        echo '<input type="hidden" id="careernest_employer_lat" name="careernest_employer_lat" value="' . esc_attr($em_loc_lat) . '" />';
        echo '<input type="hidden" id="careernest_employer_lng" name="careernest_employer_lng" value="' . esc_attr($em_loc_lng) . '" />';
        // View on map button
        $em_gmaps_query = '';
        if ($em_loc_lat !== '' && $em_loc_lng !== '') {
            $em_gmaps_query = $em_loc_lat . ',' . $em_loc_lng;
        } elseif ($location !== '') {
            $em_gmaps_query = $location;
        }
        if ($em_gmaps_query !== '') {
            $em_gmaps_url = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($em_gmaps_query);
            echo ' <a class="button button-small" style="margin-right:8px;" target="_blank" rel="noreferrer noopener" href="' . esc_url($em_gmaps_url) . '">' . esc_html__('View on map', 'careernest') . '</a>';
        }
        echo ' <button type="button" class="button button-small" id="careernest_employer_pick_map" style="margin-right:6px;">' . esc_html__('Pick on map', 'careernest') . '</button>';
        echo '<p class="description" style="margin-top:6px">' . esc_html__('Autocomplete requires a Google Maps API key in CareerNest Settings.', 'careernest') . '</p>';
        // Modal for picking location on map
        echo '<div id="careernest_employer_map_modal" class="cn-map-modal" style="display:none">';
        echo '  <div class="cn-map-dialog" role="dialog" aria-modal="true" aria-labelledby="cn-map-title-employer">';
        echo '    <div class="cn-map-header">';
        echo '      <h2 id="cn-map-title-employer">' . esc_html__('Pick Location', 'careernest') . '</h2>';
        echo '      <button type="button" class="button-link cn-map-close" id="careernest_employer_map_cancel" aria-label="' . esc_attr__('Close', 'careernest') . '">×</button>';
        echo '    </div>';
        echo '    <div class="cn-map-body">';
        echo '      <div id="careernest_employer_map_canvas" class="cn-map-canvas"></div>';
        echo '      <p class="description" style="margin:6px 0 0">' . esc_html__('Click on the map to set location. Drag to adjust.', 'careernest') . '</p>';
        echo '    </div>';
        echo '    <div class="cn-map-footer">';
        echo '      <button type="button" class="button button-primary" id="careernest_employer_map_use">' . esc_html__('Use this location', 'careernest') . '</button>';
        echo '      <button type="button" class="button" id="careernest_employer_map_cancel_2">' . esc_html__('Cancel', 'careernest') . '</button>';
        echo '    </div>';
        echo '  </div>';
        echo '</div>';
        echo '</td></tr>';


        // Company Size select
        $size_options = [
            ''         => __('Select size…', 'careernest'),
            '1-10'     => __('1–10', 'careernest'),
            '11-50'    => __('11–50', 'careernest'),
            '51-200'   => __('51–200', 'careernest'),
            '201-500'  => __('201–500', 'careernest'),
            '501-1000' => __('501–1,000', 'careernest'),
            '1000+'    => __('1,000+', 'careernest'),
        ];
        echo '<tr><th><label for="careernest_company_size">' . esc_html__('Company Size', 'careernest') . '</label></th><td>';
        echo '<select id="careernest_company_size" name="careernest_company_size">';
        foreach ($size_options as $val => $label) {
            echo '<option value="' . esc_attr($val) . '"' . selected($company_size, $val, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '</td></tr>';

        // Industry Description (moved after Year Founded)
        echo '<tr class="cn-span-2"><th><label for="careernest_industry_description">' . esc_html__('Industry Description', 'careernest') . '</label></th><td>';
        echo '<textarea id="careernest_industry_description" name="careernest_industry_description" class="large-text" rows="4" placeholder="' . esc_attr__('Briefly describe the industry focus, niche, or domain.', 'careernest') . '">';
        echo esc_textarea($industry_desc);
        echo '</textarea>';
        echo '</td></tr>';
        // Specialities (comma-separated list in a text field) — moved after Year Founded
        echo '<tr><th><label for="careernest_specialities">' . esc_html__('Specialities', 'careernest') . '</label></th><td>';
        echo '<p class="description" style="margin:0 0 6px">' . esc_html__('List the areas your company specializes in.', 'careernest') . '</p>';
        echo '<input type="text" id="careernest_specialities" name="careernest_specialities" class="regular-text" value="' . esc_attr($specialities) . '" placeholder="' . esc_attr__('e.g., Healthcare, FinTech, Logistics', 'careernest') . '" />';
        echo '</td></tr>';
        // Year Founded
        echo '<tr><th><label for="careernest_founded_year">' . esc_html__('Year Founded', 'careernest') . '</label></th><td>';
        echo '<p class="description" style="margin:0 0 6px">' . esc_html__('Enter the Year the company was founded.', 'careernest') . '</p>';
        echo '<input type="number" id="careernest_founded_year" name="careernest_founded_year" class="small-text" min="1800" max="' . esc_attr((string) $current_year) . '" value="' . esc_attr($founded_year) . '" placeholder="' . esc_attr((string) $current_year) . '" />';
        echo '</td></tr>';
        // About (WYSIWYG)
        echo '<tr class="cn-span-2"><th><label for="careernest_about">' . esc_html__('About', 'careernest') . '</label></th><td>';
        echo '<p class="description" style="margin:0 0 6px">' . esc_html__('Give us an overview of your company.', 'careernest') . '</p>';
        ob_start();
        wp_editor($about, 'careernest_about', [
            'textarea_name' => 'careernest_about',
            'textarea_rows' => 8,
            'media_buttons' => false,
            'teeny' => false,
            'quicktags' => true,
            'tinymce' => [
                'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,|,bullist,numlist,|,link,unlink,|,undo,redo',
                'toolbar2' => '',
                'block_formats' => 'Paragraph=p;Heading 2=h2;Heading 3=h3;Heading 4=h4;Heading 5=h5;Heading 6=h6'
            ]
        ]);
        echo ob_get_clean();
        echo '</td></tr>';
        // Mission (WYSIWYG) — placed after About to continue the pattern
        echo '<tr class="cn-span-2"><th><label for="careernest_mission">' . esc_html__('Mission', 'careernest') . '</label></th><td>';
        echo '<p class="description" style="margin:0 0 6px">' . esc_html__('Describe your mission.', 'careernest') . '</p>';
        ob_start();
        wp_editor($mission, 'careernest_mission', [
            'textarea_name' => 'careernest_mission',
            'textarea_rows' => 6,
            'media_buttons' => false,
            'teeny' => false,
            'quicktags' => true,
            'tinymce' => [
                'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,|,bullist,numlist,|,link,unlink,|,undo,redo',
                'toolbar2' => '',
                'block_formats' => 'Paragraph=p;Heading 2=h2;Heading 3=h3;Heading 4=h4;Heading 5=h5;Heading 6=h6'
            ]
        ]);
        echo ob_get_clean();
        echo '</td></tr>';
        // Spotlight (WYSIWYG) — continues the pattern
        echo '<tr class="cn-span-2"><th><label for="careernest_spotlight">' . esc_html__('Spotlight', 'careernest') . '</label></th><td>';
        echo '<p class="description" style="margin:0 0 6px">' . esc_html__('Describe what your company offers.', 'careernest') . '</p>';
        ob_start();
        wp_editor($spotlight, 'careernest_spotlight', [
            'textarea_name' => 'careernest_spotlight',
            'textarea_rows' => 6,
            'media_buttons' => false,
            'teeny' => false,
            'quicktags' => true,
            'tinymce' => [
                'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,|,bullist,numlist,|,link,unlink,|,undo,redo',
                'toolbar2' => '',
                'block_formats' => 'Paragraph=p;Heading 2=h2;Heading 3=h3;Heading 4=h4;Heading 5=h5;Heading 6=h6'
            ]
        ]);
        echo ob_get_clean();
        echo '</td></tr>';
        // Interested in working for us? (WYSIWYG)
        echo '<tr class="cn-span-2"><th><label for="careernest_interested_in_working">' . esc_html__('Interested in working for us?', 'careernest') . '</label></th><td>';
        echo '<p class="description" style="margin:0 0 6px">' . esc_html__('Explain why candidates should join and how to express interest.', 'careernest') . '</p>';
        ob_start();
        wp_editor($interested, 'careernest_interested_in_working', [
            'textarea_name' => 'careernest_interested_in_working',
            'textarea_rows' => 6,
            'media_buttons' => false,
            'teeny' => false,
            'quicktags' => true,
            'tinymce' => [
                'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,|,bullist,numlist,|,link,unlink,|,undo,redo',
                'toolbar2' => '',
                'block_formats' => 'Paragraph=p;Heading 2=h2;Heading 3=h3;Heading 4=h4;Heading 5=h5;Heading 6=h6'
            ]
        ]);
        echo ob_get_clean();
        echo '</td></tr>';
        echo '</table>';
    }

    public function render_applicant_details(\WP_Post $post): void
    {
        wp_nonce_field('careernest_applicant_meta', 'careernest_applicant_meta_nonce');
        $user_id            = (int) get_post_meta($post->ID, '_user_id', true);
        $prof_title         = (string) get_post_meta($post->ID, '_professional_title', true);
        $right_to_work      = (string) get_post_meta($post->ID, '_right_to_work', true);
        $work_types         = get_post_meta($post->ID, '_work_types', true);
        $work_types         = is_array($work_types) ? array_map('sanitize_text_field', $work_types) : [];
        $contact_email      = '';
        if ($user_id) {
            $u = get_user_by('id', $user_id);
            if ($u) {
                $contact_email = (string) $u->user_email;
            }
        }
        $location           = (string) get_post_meta($post->ID, '_location', true);
        $loc_place_id       = (string) get_post_meta($post->ID, '_location_place_id', true);
        $loc_lat            = (string) get_post_meta($post->ID, '_location_lat', true);
        $loc_lng            = (string) get_post_meta($post->ID, '_location_lng', true);
        $available_for_work = (bool) get_post_meta($post->ID, '_available_for_work', true);
        $resume_id          = (int) get_post_meta($post->ID, '_resume_attachment_id', true);

        echo '<table class="form-table">';

        // Linked user (admins can change)
        echo '<tr><th><label for="careernest_applicant_user">' . esc_html__('Linked User', 'careernest') . '</label></th><td>';
        if (current_user_can('manage_options')) {
            $users = get_users(['role' => 'applicant', 'fields' => ['ID', 'display_name', 'user_email'], 'number' => 500]);
            echo '<select id="careernest_applicant_user" name="careernest_applicant_user">';
            echo '<option value="">' . esc_html__('— None —', 'careernest') . '</option>';
            foreach ($users as $u) {
                $label = $u->display_name . ' (' . $u->user_email . ')';
                echo '<option data-name="' . esc_attr($u->display_name) . '" value="' . esc_attr((string) $u->ID) . '"' . selected($user_id, (int) $u->ID, false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select>';
            echo '<p class="description">' . esc_html__('Superadmins can link an Applicant to a WP user (Applicant role).', 'careernest') . '</p>';
        } else {
            echo '<code>' . esc_html((string) ($user_id ?: 0)) . '</code>';
        }
        echo '</td></tr>';

        // Professional Title
        echo '<tr><th><label for="careernest_professional_title">' . esc_html__('Professional Title', 'careernest') . '</label></th><td>';
        echo '<input type="text" id="careernest_professional_title" name="careernest_professional_title" class="regular-text" value="' . esc_attr($prof_title) . '" placeholder="' . esc_attr__('e.g., Senior Software Engineer', 'careernest') . '" />';
        echo '</td></tr>';

        // Right to Work
        $rtw_opts = ['foreign' => __('Foreign Citizen', 'careernest'), 'australian' => __('Australian Citizen', 'careernest')];
        echo '<tr><th><label for="careernest_right_to_work">' . esc_html__('Right to Work', 'careernest') . '</label></th><td>';
        echo '<select id="careernest_right_to_work" name="careernest_right_to_work">';
        foreach ($rtw_opts as $val => $label) {
            $current = in_array($right_to_work, array_keys($rtw_opts), true) ? $right_to_work : 'foreign';
            echo '<option value="' . esc_attr($val) . '"' . selected($current, $val, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '</td></tr>';





        // Contact Email (derived)
        echo '<tr><th><label>' . esc_html__('Contact Email', 'careernest') . '</label></th><td>';
        echo $contact_email ? '<code>' . esc_html($contact_email) . '</code>' : '&mdash;';
        echo '<p class="description">' . esc_html__('Derived from the linked user’s email.', 'careernest') . '</p>';
        echo '</td></tr>';

        // Phone Number
        $phone = (string) get_post_meta($post->ID, '_phone', true);
        echo '<tr><th><label for="careernest_applicant_phone">' . esc_html__('Phone Number', 'careernest') . '</label></th><td>';
        echo '<input type="text" id="careernest_applicant_phone" name="careernest_applicant_phone" class="regular-text" value="' . esc_attr($phone) . '" placeholder="' . esc_attr__('e.g., +61 4xx xxx xxx', 'careernest') . '" />';
        echo '</td></tr>';

        // LinkedIn URL
        $linkedin = (string) get_post_meta($post->ID, '_linkedin_url', true);
        echo '<tr><th><label for="careernest_applicant_linkedin">' . esc_html__('LinkedIn URL', 'careernest') . '</label></th><td>';
        echo '<input type="url" id="careernest_applicant_linkedin" name="careernest_applicant_linkedin" class="regular-text" value="' . esc_attr($linkedin) . '" placeholder="' . esc_attr__('https://www.linkedin.com/in/username', 'careernest') . '" />';
        echo '<p class="description">' . esc_html__('Link to the applicant’s LinkedIn profile.', 'careernest') . '</p>';
        echo '</td></tr>';

        // Location (with Google Maps autocomplete + hidden metadata fields)
        echo '<tr><th><label for="careernest_applicant_location">' . esc_html__('Location', 'careernest') . '</label></th><td>';
        echo '<input type="text" id="careernest_applicant_location" name="careernest_applicant_location" class="regular-text" value="' . esc_attr($location) . '" placeholder="' . esc_attr__('e.g., Melbourne, VIC', 'careernest') . '" style=" margin-bottom: 8px;" />';
        // Hidden fields for place metadata
        echo '<input type="hidden" id="careernest_applicant_place_id" name="careernest_applicant_place_id" value="' . esc_attr($loc_place_id) . '" />';
        echo '<input type="hidden" id="careernest_applicant_lat" name="careernest_applicant_lat" value="' . esc_attr($loc_lat) . '" />';
        echo '<input type="hidden" id="careernest_applicant_lng" name="careernest_applicant_lng" value="' . esc_attr($loc_lng) . '" />';
        // View on map link (prefers lat/lng if available)
        $gmaps_query = '';
        if ($loc_lat !== '' && $loc_lng !== '') {
            $gmaps_query = $loc_lat . ',' . $loc_lng;
        } elseif ($location !== '') {
            $gmaps_query = $location;
        }
        if ($gmaps_query !== '') {
            $gmaps_url = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($gmaps_query);
            echo ' <a class="button button-small" style="margin-right:8px;" target="_blank" rel="noreferrer noopener" href="' . esc_url($gmaps_url) . '">' . esc_html__('View on map', 'careernest') . '</a>';
        }
        echo ' <button type="button" class="button button-small" id="careernest_applicant_pick_map" style="margin-right:6px;">' . esc_html__('Pick on map', 'careernest') . '</button>';
        echo '<p class="description" style="margin-top:6px">' . esc_html__('Autocomplete requires a Google Maps API key in CareerNest Settings.', 'careernest') . '</p>';
        // Modal for picking location on map
        echo '<div id="careernest_applicant_map_modal" class="cn-map-modal" style="display:none">';
        echo '  <div class="cn-map-dialog" role="dialog" aria-modal="true" aria-labelledby="cn-map-title-applicant">';
        echo '    <div class="cn-map-header">';
        echo '      <h2 id="cn-map-title-applicant">' . esc_html__('Pick Location', 'careernest') . '</h2>';
        echo '      <button type="button" class="button-link cn-map-close" id="careernest_applicant_map_cancel" aria-label="' . esc_attr__('Close', 'careernest') . '">×</button>';
        echo '    </div>';
        echo '    <div class="cn-map-body">';
        echo '      <div id="careernest_applicant_map_canvas" class="cn-map-canvas"></div>';
        echo '      <p class="description" style="margin:6px 0 0">' . esc_html__('Click on the map to set location. Drag to adjust.', 'careernest') . '</p>';
        echo '    </div>';
        echo '    <div class="cn-map-footer">';
        echo '      <button type="button" class="button button-primary" id="careernest_applicant_map_use">' . esc_html__('Use this location', 'careernest') . '</button>';
        echo '      <button type="button" class="button" id="careernest_applicant_map_cancel_2">' . esc_html__('Cancel', 'careernest') . '</button>';
        echo '    </div>';
        echo '  </div>';
        echo '</div>';
        echo '</td></tr>';

        // Available for Work
        echo '<tr><th><label for="careernest_available_for_work">' . esc_html__('Available for Work', 'careernest') . '</label></th><td>';
        echo '<label><input type="checkbox" id="careernest_available_for_work" name="careernest_available_for_work" value="1" ' . checked($available_for_work, true, false) . ' /> ' . esc_html__('Currently available', 'careernest') . '</label>';
        echo '</td></tr>';

        // Resume upload
        $resume_label = $resume_id ? get_the_title($resume_id) : '';
        $resume_url   = $resume_id ? wp_get_attachment_url($resume_id) : '';
        echo '<tr><th><label>' . esc_html__('Resume File', 'careernest') . '</label></th><td>';
        echo '<input type="hidden" id="careernest_resume_id" name="careernest_resume_id" value="' . esc_attr((string) ($resume_id ?: 0)) . '" />';
        echo '<div id="careernest_resume_preview">';
        if ($resume_id && $resume_url) {
            echo '<a href="' . esc_url($resume_url) . '" target="_blank" rel="noreferrer noopener">' . esc_html($resume_label ?: basename($resume_url)) . '</a>';
        } else {
            echo '<em>' . esc_html__('No file selected', 'careernest') . '</em>';
        }
        echo '</div>';
        echo '<p><button type="button" class="button" id="careernest_resume_select">' . esc_html__('Select/Upload Resume', 'careernest') . '</button> ';
        echo '<button type="button" class="button" id="careernest_resume_clear">' . esc_html__('Clear', 'careernest') . '</button></p>';
        echo '<p class="description">' . esc_html__('Accepted: PDF, DOC, DOCX. The file is stored in the Media Library.', 'careernest') . '</p>';
        echo '</td></tr>';

        // Education repeater
        $education = get_post_meta($post->ID, '_education', true);
        $education = is_array($education) ? $education : [];
        echo '<tr class="cn-span-2 cn-section"><th><label>' . esc_html__('Education', 'careernest') . '</label></th><td>';
        echo '<p class="description" style="margin:0 0 8px">' . esc_html__('Add your qualifications. Include dates and whether the qualification is complete.', 'careernest') . '</p>';
        echo '<div id="careernest-edu-list">';
        if (!empty($education)) {
            foreach ($education as $idx => $row) {
                $inst  = isset($row['institution']) ? (string) $row['institution'] : '';
                $cert  = isset($row['certification']) ? (string) $row['certification'] : '';
                $start = isset($row['start_date']) ? (string) $row['start_date'] : '';
                $end   = isset($row['end_date']) ? (string) $row['end_date'] : '';
                $notes = isset($row['notes']) ? (string) $row['notes'] : '';
                $done  = !empty($row['complete']) ? 1 : 0;
                echo '<div class="cn-edu-item">';
                echo '<div class="cn-edu-handle"><span class="dashicons dashicons-move"></span> ' . esc_html__('Drag to reorder', 'careernest') . '</div>';
                echo '<div class="cn-edu-grid">';
                echo '<p><label>' . esc_html__('Institution', 'careernest') . '</label><br /><input type="text" name="careernest_edu_institution[]" class="regular-text" value="' . esc_attr($inst) . '" /></p>';
                echo '<p><label>' . esc_html__('Certification', 'careernest') . '</label><br /><input type="text" name="careernest_edu_certification[]" class="regular-text" value="' . esc_attr($cert) . '" /></p>';
                echo '<p><label>' . esc_html__('Start Date', 'careernest') . '</label><br /><input type="date" name="careernest_edu_start[]" value="' . esc_attr($start) . '" /></p>';
                echo '<p><label>' . esc_html__('End Date', 'careernest') . '</label><br /><input type="date" name="careernest_edu_end[]" value="' . esc_attr($end) . '" /></p>';
                echo '<p class="cn-span-2"><label>' . esc_html__('Notes', 'careernest') . '</label><br /><textarea name="careernest_edu_notes[]" rows="3" class="large-text">' . esc_textarea($notes) . '</textarea></p>';
                echo '<p><input type="hidden" name="careernest_edu_complete_row[]" value="' . ($done ? '1' : '0') . '" />';
                echo '<label><input type="checkbox" class="cn-edu-complete" ' . checked($done, 1, false) . ' /> ' . esc_html__('Qualification Complete', 'careernest') . '</label></p>';
                echo '</div>';
                echo '<p><button type="button" class="button-link-delete cn-edu-remove">' . esc_html__('Remove', 'careernest') . '</button></p>';
                echo '<hr /></div>';
            }
        }
        echo '</div>';
        echo '<p><button type="button" class="button" id="careernest-edu-add">' . esc_html__('Add Education', 'careernest') . '</button></p>';
        echo '</td></tr>';

        // Work Experience repeater
        $experience = get_post_meta($post->ID, '_experience', true);
        $experience = is_array($experience) ? $experience : [];
        echo '<tr class="cn-span-2 cn-section"><th><label>' . esc_html__('Work Experience', 'careernest') . '</label></th><td>';
        echo '<p class="description" style="margin:0 0 8px">' . esc_html__('Add previous roles and responsibilities.', 'careernest') . '</p>';
        echo '<div id="careernest-exp-list">';
        if (!empty($experience)) {
            foreach ($experience as $row) {
                $company = isset($row['company']) ? (string) $row['company'] : '';
                $title   = isset($row['title']) ? (string) $row['title'] : '';
                $start   = isset($row['start_date']) ? (string) $row['start_date'] : '';
                $end     = isset($row['end_date']) ? (string) $row['end_date'] : '';
                $notes   = isset($row['notes']) ? (string) $row['notes'] : '';
                $current = ! empty($row['current']) ? 1 : 0;
                echo '<div class="cn-exp-item">';
                echo '<div class="cn-exp-handle"><span class="dashicons dashicons-move"></span> ' . esc_html__('Drag to reorder', 'careernest') . '</div>';
                echo '<div class="cn-exp-grid">';
                echo '<p><label>' . esc_html__('Company', 'careernest') . '</label><br /><input type="text" name="careernest_exp_company[]" class="regular-text" value="' . esc_attr($company) . '" /></p>';
                echo '<p><label>' . esc_html__('Job Title', 'careernest') . '</label><br /><input type="text" name="careernest_exp_title[]" class="regular-text" value="' . esc_attr($title) . '" /></p>';
                echo '<p><label>' . esc_html__('Start Date', 'careernest') . '</label><br /><input type="date" name="careernest_exp_start[]" value="' . esc_attr($start) . '" /></p>';
                echo '<p><label>' . esc_html__('End Date', 'careernest') . '</label><br /><input type="date" class="cn-exp-end" name="careernest_exp_end[]" value="' . esc_attr($end) . '" ' . ($current ? 'disabled' : '') . ' /></p>';
                echo '<p class="cn-span-2"><label>' . esc_html__('Notes', 'careernest') . '</label><br /><textarea name="careernest_exp_notes[]" rows="3" class="large-text">' . esc_textarea($notes) . '</textarea></p>';
                echo '<p><input type="hidden" name="careernest_exp_current_row[]" value="' . ($current ? '1' : '0') . '" />';
                echo '<label><input type="checkbox" class="cn-exp-current" ' . checked($current, 1, false) . ' /> ' . esc_html__('Current Role', 'careernest') . '</label></p>';
                echo '</div>';
                echo '<p><button type="button" class="button-link-delete cn-exp-remove">' . esc_html__('Remove', 'careernest') . '</button></p>';
                echo '<hr /></div>';
            }
        }
        echo '</div>';
        echo '<p><button type="button" class="button" id="careernest-exp-add">' . esc_html__('Add Work Experience', 'careernest') . '</button></p>';
        echo '</td></tr>';

        // Licences & Certifications repeater
        $licenses = get_post_meta($post->ID, '_licenses', true);
        $licenses = is_array($licenses) ? $licenses : [];
        echo '<tr class="cn-span-2 cn-section"><th><label>' . esc_html__('Licences & Certifications', 'careernest') . '</label></th><td>';
        echo '<p class="description" style="margin:0 0 8px">' . esc_html__('Add any licences or certifications, with issuing organization and expiry if applicable.', 'careernest') . '</p>';
        echo '<div id="careernest-lic-list">';
        if (!empty($licenses)) {
            foreach ($licenses as $row) {
                $name    = isset($row['name']) ? (string) $row['name'] : '';
                $issuer  = isset($row['issuer']) ? (string) $row['issuer'] : '';
                $expiry  = isset($row['expiry_date']) ? (string) $row['expiry_date'] : '';
                $notes   = isset($row['notes']) ? (string) $row['notes'] : '';
                echo '<div class="cn-lic-item">';
                echo '<div class="cn-lic-handle"><span class="dashicons dashicons-move"></span> ' . esc_html__('Drag to reorder', 'careernest') . '</div>';
                echo '<div class="cn-lic-grid">';
                echo '<p><label>' . esc_html__('Name', 'careernest') . '</label><br /><input type="text" name="careernest_lic_name[]" class="regular-text" value="' . esc_attr($name) . '" /></p>';
                echo '<p><label>' . esc_html__('Issuing Company', 'careernest') . '</label><br /><input type="text" name="careernest_lic_issuer[]" class="regular-text" value="' . esc_attr($issuer) . '" /></p>';
                echo '<p><label>' . esc_html__('Expiry Date', 'careernest') . '</label><br /><input type="date" name="careernest_lic_expiry[]" value="' . esc_attr($expiry) . '" /></p>';
                echo '<p class="cn-span-2"><label>' . esc_html__('Notes', 'careernest') . '</label><br /><textarea name="careernest_lic_notes[]" rows="3" class="large-text">' . esc_textarea($notes) . '</textarea></p>';
                echo '</div>';
                echo '<p><button type="button" class="button-link-delete cn-lic-remove">' . esc_html__('Remove', 'careernest') . '</button></p>';
                echo '<hr /></div>';
            }
        }
        echo '</div>';
        echo '<p><button type="button" class="button" id="careernest-lic-add">' . esc_html__('Add Licence/Certification', 'careernest') . '</button></p>';
        echo '</td></tr>';

        // Links repeater (websites/social profiles)
        $links = get_post_meta($post->ID, '_links', true);
        $links = is_array($links) ? $links : [];
        echo '<tr class="cn-span-2 cn-section"><th><label>' . esc_html__('Websites & Social Profiles', 'careernest') . '</label></th><td>';
        echo '<p class="description" style="margin:0 0 8px">' . esc_html__('Add links to your website or social profiles (e.g., portfolio, GitHub, Twitter).', 'careernest') . '</p>';
        echo '<div id="careernest-link-list">';
        if (!empty($links)) {
            foreach ($links as $row) {
                $label = isset($row['label']) ? (string) $row['label'] : '';
                $url   = isset($row['url']) ? (string) $row['url'] : '';
                $notes = isset($row['notes']) ? (string) $row['notes'] : '';
                echo '<div class="cn-link-item">';
                echo '<div class="cn-link-handle"><span class="dashicons dashicons-move"></span> ' . esc_html__('Drag to reorder', 'careernest') . '</div>';
                echo '<div class="cn-link-grid">';
                echo '<p><label>' . esc_html__('Label', 'careernest') . '</label><br /><input type="text" name="careernest_link_label[]" class="regular-text" value="' . esc_attr($label) . '" placeholder="' . esc_attr__('e.g., Portfolio, GitHub', 'careernest') . '" /></p>';
                echo '<p><label>' . esc_html__('URL', 'careernest') . '</label><br /><input type="url" name="careernest_link_url[]" class="regular-text" value="' . esc_attr($url) . '" placeholder="https://" /></p>';
                echo '<p class="cn-span-2"><label>' . esc_html__('Notes', 'careernest') . '</label><br /><textarea name="careernest_link_notes[]" rows="2" class="large-text">' . esc_textarea($notes) . '</textarea></p>';
                echo '</div>';
                echo '<p><button type="button" class="button-link-delete cn-link-remove">' . esc_html__('Remove', 'careernest') . '</button></p>';
                echo '<hr /></div>';
            }
        }
        echo '</div>';
        echo '<p><button type="button" class="button" id="careernest-link-add">' . esc_html__('Add Link', 'careernest') . '</button></p>';
        echo '</td></tr>';

        echo '</table>';
    }

    /**
     * Sidebar: Employer Team Members list and inline link control.
     */
    public function render_employer_team_members(\WP_Post $post): void
    {
        if (! current_user_can('edit_post', $post->ID)) {
            echo '<p>' . esc_html__('You do not have permission to view this.', 'careernest') . '</p>';
            return;
        }

        $linked = get_users([
            'role'       => 'employer_team',
            'meta_key'   => '_employer_id',
            'meta_value' => (int) $post->ID,
            'fields'     => ['ID', 'display_name', 'user_email'],
            'number'     => 200,
        ]);

        if (empty($linked)) {
            echo '<p>' . esc_html__('No team members linked yet.', 'careernest') . '</p>';
        } else {
            echo '<ul style="margin:0;">';
            foreach ($linked as $u) {
                $uid   = (int) $u->ID;
                $name  = $u->display_name;
                $email = $u->user_email;
                $edit  = esc_url(admin_url('user-edit.php?user_id=' . $uid));
                echo '<li style="margin:0 0 8px;">';
                echo '<a href="' . $edit . '"><strong>' . esc_html($name) . '</strong></a><br />';
                echo '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
                echo '<div><label><input type="checkbox" name="careernest_unlink_users[]" value="' . esc_attr((string) $uid) . '" /> ' . esc_html__('Unlink', 'careernest') . '</label></div>';
                echo '</li>';
            }
            echo '</ul>';
            echo '<p class="description" style="margin-top:6px">' . esc_html__('Check “Unlink” and click Update to remove selected members from this employer.', 'careernest') . '</p>';
        }

        echo '<hr style="margin:10px 0" />';
        echo '<p class="description" style="margin:0 0 6px">' . esc_html__('Link an existing Employer Team Member to this Employer.', 'careernest') . '</p>';
        wp_nonce_field('careernest_employer_team_link', 'careernest_employer_team_link_nonce');

        $candidates = get_users(['role' => 'employer_team', 'fields' => ['ID', 'display_name'], 'number' => 500]);
        $options    = [];
        foreach ($candidates as $cu) {
            $linked_id = (int) get_user_meta($cu->ID, '_employer_id', true);
            if ($linked_id === (int) $post->ID) {
                // Already linked to this employer; skip.
                continue;
            }
            $label = $cu->display_name . ' (#' . (int) $cu->ID . ')';
            if ($linked_id > 0) {
                $label .= ' — ' . sprintf(
                    /* translators: %s: employer title */
                    esc_html__('currently: %s', 'careernest'),
                    get_the_title($linked_id) ?: esc_html__('Unknown Employer', 'careernest')
                );
            }
            $options[(int) $cu->ID] = $label;
        }
        if (empty($options)) {
            echo '<p class="description">' . esc_html__('No Employer Team Members available to link or reassign.', 'careernest') . '</p>';
        } else {
            echo '<p><label for="careernest_link_team_member_user_id" class="screen-reader-text">' . esc_html__('Select team member', 'careernest') . '</label>';
            echo '<select id="careernest_link_team_member_user_id" name="careernest_link_team_member_user_id" style="max-width:100%">';
            echo '<option value="">' . esc_html__('Select team member…', 'careernest') . '</option>';
            foreach ($options as $id => $label) {
                echo '<option value="' . esc_attr((string) $id) . '">' . esc_html($label) . '</option>';
            }
            echo '</select></p>';
            echo '<p><button type="submit" class="button button-secondary">' . esc_html__('Link Member', 'careernest') . '</button></p>';
        }
        echo '<p class="description" style="margin-top:6px">' . esc_html__('You can also assign an employer on the user’s profile page.', 'careernest') . '</p>';
    }



    public function save_job_meta(int $post_id): void
    {
        if (get_post_type($post_id) !== 'job_listing') {
            return;
        }
        if (! isset($_POST['careernest_job_meta_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['careernest_job_meta_nonce'] ?? '')), 'careernest_job_meta')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (! current_user_can('edit_post', $post_id)) {
            return;
        }

        // Employer
        $employer_id = isset($_POST['careernest_employer_id']) ? absint($_POST['careernest_employer_id']) : 0;
        update_post_meta($post_id, '_employer_id', $employer_id);

        // Location + Remote
        $job_location    = isset($_POST['careernest_job_location']) ? sanitize_text_field(wp_unslash($_POST['careernest_job_location'])) : '';
        $remote_position = ! empty($_POST['careernest_remote_position']) ? 1 : 0;
        update_post_meta($post_id, '_job_location', $job_location);
        update_post_meta($post_id, '_remote_position', $remote_position);
        // Save Google Maps place metadata for job location
        $job_place_id = isset($_POST['careernest_job_place_id']) ? sanitize_text_field(wp_unslash($_POST['careernest_job_place_id'])) : '';
        $job_lat_raw  = isset($_POST['careernest_job_lat']) ? wp_unslash($_POST['careernest_job_lat']) : '';
        $job_lng_raw  = isset($_POST['careernest_job_lng']) ? wp_unslash($_POST['careernest_job_lng']) : '';
        $job_lat_val  = is_numeric($job_lat_raw) ? (float) $job_lat_raw : null;
        $job_lng_val  = is_numeric($job_lng_raw) ? (float) $job_lng_raw : null;
        if ($job_place_id !== '') {
            update_post_meta($post_id, '_job_location_place_id', $job_place_id);
        } else {
            delete_post_meta($post_id, '_job_location_place_id');
        }
        if ($job_lat_val !== null && $job_lat_val >= -90 && $job_lat_val <= 90) {
            update_post_meta($post_id, '_job_location_lat', (string) $job_lat_val);
        } else {
            delete_post_meta($post_id, '_job_location_lat');
        }
        if ($job_lng_val !== null && $job_lng_val >= -180 && $job_lng_val <= 180) {
            update_post_meta($post_id, '_job_location_lng', (string) $job_lng_val);
        } else {
            delete_post_meta($post_id, '_job_location_lng');
        }

        // Dates
        $opening_date = isset($_POST['careernest_opening_date']) ? preg_replace('/[^0-9\-]/', '', (string) $_POST['careernest_opening_date']) : '';
        $closing_date = isset($_POST['careernest_closing_date']) ? preg_replace('/[^0-9\-]/', '', (string) $_POST['careernest_closing_date']) : '';
        update_post_meta($post_id, '_opening_date', $opening_date);
        update_post_meta($post_id, '_closing_date', $closing_date);
        // If past closing date, set status to draft
        if ($closing_date) {
            $close_ts = strtotime($closing_date . ' 23:59:59');
            if ($close_ts && $close_ts < current_time('timestamp')) {
                $p = get_post($post_id);
                if ($p && $p->post_status !== 'draft') {
                    wp_update_post([
                        'ID' => $post_id,
                        'post_status' => 'draft',
                    ]);
                }
            }
        }

        // Salary range / numeric (mutually exclusive)
        $salary_range = isset($_POST['careernest_salary_range']) ? sanitize_text_field(wp_unslash($_POST['careernest_salary_range'])) : '';
        if (current_user_can('manage_options')) {
            $salary_mode = isset($_POST['careernest_salary_mode']) && $_POST['careernest_salary_mode'] === 'numeric' ? 'numeric' : 'range';
            update_post_meta($post_id, '_salary_mode', $salary_mode);
            if ($salary_mode === 'numeric') {
                // Save numeric, clear range
                $salary = isset($_POST['careernest_salary']) && $_POST['careernest_salary'] !== '' ? (float) $_POST['careernest_salary'] : '';
                if ($salary !== '') {
                    update_post_meta($post_id, '_salary', $salary);
                } else {
                    delete_post_meta($post_id, '_salary');
                }
                delete_post_meta($post_id, '_salary_range');
            } else {
                // Save range, clear numeric
                update_post_meta($post_id, '_salary_range', $salary_range);
                delete_post_meta($post_id, '_salary');
                update_post_meta($post_id, '_salary_mode', 'range');
            }
        } else {
            // Non-admins: only salary range allowed
            update_post_meta($post_id, '_salary_range', $salary_range);
        }

        // Apply externally toggle + external value
        $apply_externally = ! empty($_POST['careernest_apply_externally']) ? 1 : 0;
        update_post_meta($post_id, '_apply_externally', $apply_externally);
        $external_apply_raw = isset($_POST['careernest_external_apply']) ? trim((string) $_POST['careernest_external_apply']) : '';
        $external_apply_val = '';
        if ($external_apply_raw !== '') {
            if (strpos($external_apply_raw, '@') !== false) {
                $maybe = sanitize_email($external_apply_raw);
                if (is_email($maybe)) {
                    $external_apply_val = $maybe;
                }
            } else {
                $maybe = esc_url_raw($external_apply_raw);
                if ($maybe) {
                    $external_apply_val = $maybe;
                }
            }
        }
        if ($external_apply_val !== '') {
            update_post_meta($post_id, '_external_apply', $external_apply_val);
        } else {
            delete_post_meta($post_id, '_external_apply');
        }

        // Admin-only fields
        if (current_user_can('manage_options')) {
            $posted_by = isset($_POST['careernest_posted_by']) ? absint($_POST['careernest_posted_by']) : 0;
            $emp_id_check = isset($_POST['careernest_employer_id']) ? absint($_POST['careernest_employer_id']) : 0;
            if ($posted_by > 0 && $emp_id_check > 0) {
                $user_emp = (int) get_user_meta($posted_by, '_employer_id', true);
                if ($user_emp === $emp_id_check) {
                    update_post_meta($post_id, '_posted_by', $posted_by);
                } else {
                    delete_post_meta($post_id, '_posted_by');
                }
            } else {
                delete_post_meta($post_id, '_posted_by');
            }
            $position_filled = ! empty($_POST['careernest_position_filled']) ? 1 : 0;
            update_post_meta($post_id, '_position_filled', $position_filled);
        }

        // Job WYSIWYG sections
        $overview = isset($_POST['careernest_job_overview']) ? wp_kses_post(wp_unslash($_POST['careernest_job_overview'])) : '';
        if ($overview !== '') {
            update_post_meta($post_id, '_job_overview', $overview);
        } else {
            delete_post_meta($post_id, '_job_overview');
        }

        $who_we_are = isset($_POST['careernest_job_who_we_are']) ? wp_kses_post(wp_unslash($_POST['careernest_job_who_we_are'])) : '';
        if ($who_we_are !== '') {
            update_post_meta($post_id, '_job_who_we_are', $who_we_are);
        } else {
            delete_post_meta($post_id, '_job_who_we_are');
        }

        $what_we_offer = isset($_POST['careernest_job_what_we_offer']) ? wp_kses_post(wp_unslash($_POST['careernest_job_what_we_offer'])) : '';
        if ($what_we_offer !== '') {
            update_post_meta($post_id, '_job_what_we_offer', $what_we_offer);
        } else {
            delete_post_meta($post_id, '_job_what_we_offer');
        }

        $responsibilities = isset($_POST['careernest_job_responsibilities']) ? wp_kses_post(wp_unslash($_POST['careernest_job_responsibilities'])) : '';
        if ($responsibilities !== '') {
            update_post_meta($post_id, '_job_responsibilities', $responsibilities);
        } else {
            delete_post_meta($post_id, '_job_responsibilities');
        }

        $how_to_apply = isset($_POST['careernest_job_how_to_apply']) ? wp_kses_post(wp_unslash($_POST['careernest_job_how_to_apply'])) : '';
        if ($how_to_apply !== '') {
            update_post_meta($post_id, '_job_how_to_apply', $how_to_apply);
        } else {
            delete_post_meta($post_id, '_job_how_to_apply');
        }
    }

    public function save_employer_meta(int $post_id): void
    {
        if (get_post_type($post_id) !== 'employer') {
            return;
        }
        $nonce_ok = false;
        if (isset($_POST['careernest_employer_meta_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['careernest_employer_meta_nonce'])), 'careernest_employer_meta')) {
            $nonce_ok = true;
        }
        if (isset($_POST['careernest_employer_team_link_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['careernest_employer_team_link_nonce'])), 'careernest_employer_team_link')) {
            $nonce_ok = true;
        }
        if (! $nonce_ok) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (! current_user_can('edit_post', $post_id)) {
            return;
        }

        $website = isset($_POST['careernest_website']) ? esc_url_raw((string) $_POST['careernest_website']) : '';
        if ($website) {
            update_post_meta($post_id, '_website', $website);
        } else {
            delete_post_meta($post_id, '_website');
        }

        // Tagline
        $tagline = isset($_POST['careernest_tagline']) ? sanitize_text_field(wp_unslash($_POST['careernest_tagline'])) : '';
        if ($tagline !== '') {
            update_post_meta($post_id, '_tagline', $tagline);
        } else {
            delete_post_meta($post_id, '_tagline');
        }

        // Location
        $location = isset($_POST['careernest_location']) ? sanitize_text_field(wp_unslash($_POST['careernest_location'])) : '';
        if ($location !== '') {
            update_post_meta($post_id, '_location', $location);
        } else {
            delete_post_meta($post_id, '_location');
        }
        // Google Maps place metadata for employer (optional)
        $em_place_id = isset($_POST['careernest_employer_place_id']) ? sanitize_text_field(wp_unslash($_POST['careernest_employer_place_id'])) : '';
        $em_lat_raw  = isset($_POST['careernest_employer_lat']) ? wp_unslash($_POST['careernest_employer_lat']) : '';
        $em_lng_raw  = isset($_POST['careernest_employer_lng']) ? wp_unslash($_POST['careernest_employer_lng']) : '';
        $em_lat_val  = is_numeric($em_lat_raw) ? (float) $em_lat_raw : null;
        $em_lng_val  = is_numeric($em_lng_raw) ? (float) $em_lng_raw : null;
        if ($em_place_id !== '') {
            update_post_meta($post_id, '_location_place_id', $em_place_id);
        } else {
            delete_post_meta($post_id, '_location_place_id');
        }
        if ($em_lat_val !== null && $em_lat_val >= -90 && $em_lat_val <= 90) {
            update_post_meta($post_id, '_location_lat', (string) $em_lat_val);
        } else {
            delete_post_meta($post_id, '_location_lat');
        }
        if ($em_lng_val !== null && $em_lng_val >= -180 && $em_lng_val <= 180) {
            update_post_meta($post_id, '_location_lng', (string) $em_lng_val);
        } else {
            delete_post_meta($post_id, '_location_lng');
        }

        // Industry Description (allow limited HTML)
        $industry_desc = isset($_POST['careernest_industry_description']) ? wp_kses_post(wp_unslash($_POST['careernest_industry_description'])) : '';
        if ($industry_desc !== '') {
            update_post_meta($post_id, '_industry_description', $industry_desc);
        } else {
            delete_post_meta($post_id, '_industry_description');
        }

        // About (WYSIWYG; allow limited HTML)
        $about = isset($_POST['careernest_about']) ? wp_kses_post(wp_unslash($_POST['careernest_about'])) : '';
        if ($about !== '') {
            update_post_meta($post_id, '_about', $about);
        } else {
            delete_post_meta($post_id, '_about');
        }

        // Mission (WYSIWYG; allow limited HTML)
        $mission = isset($_POST['careernest_mission']) ? wp_kses_post(wp_unslash($_POST['careernest_mission'])) : '';
        if ($mission !== '') {
            update_post_meta($post_id, '_mission', $mission);
        } else {
            delete_post_meta($post_id, '_mission');
        }

        // Spotlight (WYSIWYG; allow limited HTML)
        $spotlight = isset($_POST['careernest_spotlight']) ? wp_kses_post(wp_unslash($_POST['careernest_spotlight'])) : '';
        if ($spotlight !== '') {
            update_post_meta($post_id, '_spotlight', $spotlight);
        } else {
            delete_post_meta($post_id, '_spotlight');
        }

        // Interested in working for us? (WYSIWYG; allow limited HTML)
        $interested = isset($_POST['careernest_interested_in_working']) ? wp_kses_post(wp_unslash($_POST['careernest_interested_in_working'])) : '';
        if ($interested !== '') {
            update_post_meta($post_id, '_interested_in_working', $interested);
        } else {
            delete_post_meta($post_id, '_interested_in_working');
        }

        // Specialities (comma-separated text; normalize and sanitize)
        $specialities_raw = isset($_POST['careernest_specialities']) ? (string) $_POST['careernest_specialities'] : '';
        $specialities_val = '';
        if ($specialities_raw !== '') {
            $parts = array_map('trim', explode(',', wp_unslash($specialities_raw)));
            $parts = array_filter($parts, static function ($v) {
                return $v !== '';
            });
            if (! empty($parts)) {
                $parts = array_unique(array_map('sanitize_text_field', $parts));
                $specialities_val = implode(', ', $parts);
            }
        }
        if ($specialities_val !== '') {
            update_post_meta($post_id, '_specialities', $specialities_val);
        } else {
            delete_post_meta($post_id, '_specialities');
        }

        // Company Size (enum)
        $allowed_sizes = ['1-10', '11-50', '51-200', '201-500', '501-1000', '1000+'];
        $company_size  = isset($_POST['careernest_company_size']) ? sanitize_text_field(wp_unslash($_POST['careernest_company_size'])) : '';
        if (in_array($company_size, $allowed_sizes, true)) {
            update_post_meta($post_id, '_company_size', $company_size);
        } else {
            delete_post_meta($post_id, '_company_size');
        }

        // Year Founded (4-digit year)
        $current_year = (int) gmdate('Y');
        $year_raw     = isset($_POST['careernest_founded_year']) ? trim((string) $_POST['careernest_founded_year']) : '';
        $year_digits  = $year_raw === '' ? '' : preg_replace('/[^0-9]/', '', $year_raw);
        $year_int     = $year_digits === '' ? 0 : (int) $year_digits;
        if ($year_int >= 1800 && $year_int <= $current_year) {
            update_post_meta($post_id, '_founded_year', (string) $year_int);
        } else {
            delete_post_meta($post_id, '_founded_year');
        }

        // Inline link: team member to this employer
        if (isset($_POST['careernest_link_team_member_user_id'])) {
            $uid = absint($_POST['careernest_link_team_member_user_id']);
            if ($uid > 0) {
                $user = get_user_by('id', $uid);
                if ($user && in_array('employer_team', (array) $user->roles, true)) {
                    update_user_meta($uid, '_employer_id', (int) $post_id);
                }
            }
        }

        // Inline unlink: remove selected team members from this employer
        if (! empty($_POST['careernest_unlink_users']) && is_array($_POST['careernest_unlink_users'])) {
            $to_unlink = array_map('absint', (array) $_POST['careernest_unlink_users']);
            foreach ($to_unlink as $uid) {
                if ($uid <= 0) {
                    continue;
                }
                if (! current_user_can('edit_user', $uid)) {
                    continue;
                }
                $curr = (int) get_user_meta($uid, '_employer_id', true);
                if ($curr === (int) $post_id) {
                    delete_user_meta($uid, '_employer_id');
                }
            }
        }
    }

    public function save_applicant_meta(int $post_id): void
    {
        if (get_post_type($post_id) !== 'applicant') {
            return;
        }
        if (! isset($_POST['careernest_applicant_meta_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['careernest_applicant_meta_nonce'] ?? '')), 'careernest_applicant_meta')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (! current_user_can('edit_post', $post_id)) {
            return;
        }
        // Link user (admins only)
        if (current_user_can('manage_options')) {
            $uid = isset($_POST['careernest_applicant_user']) ? absint($_POST['careernest_applicant_user']) : 0;
            if ($uid > 0) {
                $u = get_user_by('id', $uid);
                if ($u && in_array('applicant', (array) $u->roles, true)) {
                    update_post_meta($post_id, '_user_id', $uid);
                }
            } else {
                delete_post_meta($post_id, '_user_id');
            }
        }

        // Professional title
        $prof = isset($_POST['careernest_professional_title']) ? sanitize_text_field(wp_unslash($_POST['careernest_professional_title'])) : '';
        if ($prof !== '') {
            update_post_meta($post_id, '_professional_title', $prof);
        } else {
            delete_post_meta($post_id, '_professional_title');
        }

        // Right to work
        $rtw = isset($_POST['careernest_right_to_work']) ? sanitize_text_field(wp_unslash($_POST['careernest_right_to_work'])) : '';
        $allowed_rtw = ['foreign', 'australian'];
        if (in_array($rtw, $allowed_rtw, true)) {
            update_post_meta($post_id, '_right_to_work', $rtw);
        } else {
            delete_post_meta($post_id, '_right_to_work');
        }

        // Preferred work types
        $types = isset($_POST['careernest_work_types']) ? (array) $_POST['careernest_work_types'] : [];
        $types = array_map(static function ($v) {
            return sanitize_text_field((string) $v);
        }, $types);
        $types = array_values(array_intersect($types, ['full_time', 'part_time', 'contract', 'temporary', 'internship', 'remote', 'on_site', 'hybrid']));
        update_post_meta($post_id, '_work_types', $types);

        // Location
        $loc = isset($_POST['careernest_applicant_location']) ? sanitize_text_field(wp_unslash($_POST['careernest_applicant_location'])) : '';
        if ($loc !== '') {
            update_post_meta($post_id, '_location', $loc);
        } else {
            delete_post_meta($post_id, '_location');
        }
        // Google Maps place metadata (optional)
        $place_id = isset($_POST['careernest_applicant_place_id']) ? sanitize_text_field(wp_unslash($_POST['careernest_applicant_place_id'])) : '';
        $lat_raw  = isset($_POST['careernest_applicant_lat']) ? wp_unslash($_POST['careernest_applicant_lat']) : '';
        $lng_raw  = isset($_POST['careernest_applicant_lng']) ? wp_unslash($_POST['careernest_applicant_lng']) : '';
        $lat_val  = is_numeric($lat_raw) ? (float) $lat_raw : null;
        $lng_val  = is_numeric($lng_raw) ? (float) $lng_raw : null;
        // Validate ranges: lat [-90,90], lng [-180,180]
        if ($place_id !== '') {
            update_post_meta($post_id, '_location_place_id', $place_id);
        } else {
            delete_post_meta($post_id, '_location_place_id');
        }
        if ($lat_val !== null && $lat_val >= -90 && $lat_val <= 90) {
            update_post_meta($post_id, '_location_lat', (string) $lat_val);
        } else {
            delete_post_meta($post_id, '_location_lat');
        }
        if ($lng_val !== null && $lng_val >= -180 && $lng_val <= 180) {
            update_post_meta($post_id, '_location_lng', (string) $lng_val);
        } else {
            delete_post_meta($post_id, '_location_lng');
        }

        // Phone
        $phone = isset($_POST['careernest_applicant_phone']) ? sanitize_text_field(wp_unslash($_POST['careernest_applicant_phone'])) : '';
        if ($phone !== '') {
            update_post_meta($post_id, '_phone', $phone);
        } else {
            delete_post_meta($post_id, '_phone');
        }

        // LinkedIn URL
        $linkedin = isset($_POST['careernest_applicant_linkedin']) ? esc_url_raw((string) $_POST['careernest_applicant_linkedin']) : '';
        if ($linkedin && preg_match('#^https?://#i', $linkedin)) {
            update_post_meta($post_id, '_linkedin_url', $linkedin);
        } else {
            delete_post_meta($post_id, '_linkedin_url');
        }

        // Available for work
        $avail = ! empty($_POST['careernest_available_for_work']) ? 1 : 0;
        update_post_meta($post_id, '_available_for_work', $avail);

        // Resume attachment id
        $resume_id = isset($_POST['careernest_resume_id']) ? absint($_POST['careernest_resume_id']) : 0;
        if ($resume_id > 0) {
            update_post_meta($post_id, '_resume_attachment_id', $resume_id);
        } else {
            delete_post_meta($post_id, '_resume_attachment_id');
        }

        // Skills
        $skills = isset($_POST['careernest_skills']) ? (array) $_POST['careernest_skills'] : [];
        $skills = array_filter(array_map(function ($v) {
            return sanitize_text_field(wp_unslash($v));
        }, $skills), function ($v) {
            return $v !== '';
        });
        // de-dupe case-insensitively
        $seen = [];
        $uniq = [];
        foreach ($skills as $s) {
            $k = strtolower($s);
            if (isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $uniq[] = $s;
        }
        if (!empty($uniq)) {
            update_post_meta($post_id, '_skills', $uniq);
        } else {
            delete_post_meta($post_id, '_skills');
        }

        // Education repeater save
        $inst  = isset($_POST['careernest_edu_institution']) ? (array) $_POST['careernest_edu_institution'] : [];
        $cert  = isset($_POST['careernest_edu_certification']) ? (array) $_POST['careernest_edu_certification'] : [];
        $start = isset($_POST['careernest_edu_start']) ? (array) $_POST['careernest_edu_start'] : [];
        $end   = isset($_POST['careernest_edu_end']) ? (array) $_POST['careernest_edu_end'] : [];
        $notes = isset($_POST['careernest_edu_notes']) ? (array) $_POST['careernest_edu_notes'] : [];
        $comp  = isset($_POST['careernest_edu_complete_row']) ? (array) $_POST['careernest_edu_complete_row'] : [];
        $max   = max(count($inst), count($cert), count($start), count($end), count($notes));
        $rows  = [];
        for ($i = 0; $i < $max; $i++) {
            $ri = isset($inst[$i]) ? sanitize_text_field(wp_unslash($inst[$i])) : '';
            $rc = isset($cert[$i]) ? sanitize_text_field(wp_unslash($cert[$i])) : '';
            $rs = isset($start[$i]) ? preg_replace('/[^0-9\-]/', '', (string) $start[$i]) : '';
            $re = isset($end[$i]) ? preg_replace('/[^0-9\-]/', '', (string) $end[$i]) : '';
            $rn = isset($notes[$i]) ? sanitize_textarea_field(wp_unslash($notes[$i])) : '';
            $rcmp = isset($comp[$i]) && (int) $comp[$i] === 1 ? 1 : 0;
            // Skip empty rows (no institution and certification)
            if ($ri === '' && $rc === '' && $rs === '' && $re === '' && $rn === '') {
                continue;
            }
            $rows[] = [
                'institution'  => $ri,
                'certification' => $rc,
                'start_date'   => $rs,
                'end_date'     => $re,
                'notes'        => $rn,
                'complete'     => $rcmp,
            ];
        }
        if (!empty($rows)) {
            update_post_meta($post_id, '_education', $rows);
        } else {
            delete_post_meta($post_id, '_education');
        }

        // Work experience save
        $c = isset($_POST['careernest_exp_company']) ? (array) $_POST['careernest_exp_company'] : [];
        $t = isset($_POST['careernest_exp_title']) ? (array) $_POST['careernest_exp_title'] : [];
        $s = isset($_POST['careernest_exp_start']) ? (array) $_POST['careernest_exp_start'] : [];
        $e = isset($_POST['careernest_exp_end']) ? (array) $_POST['careernest_exp_end'] : [];
        $n = isset($_POST['careernest_exp_notes']) ? (array) $_POST['careernest_exp_notes'] : [];
        $cur = isset($_POST['careernest_exp_current_row']) ? (array) $_POST['careernest_exp_current_row'] : [];
        $maxe = max(count($c), count($t), count($s), count($e), count($n), count($cur));
        $exp_rows = [];
        for ($i = 0; $i < $maxe; $i++) {
            $rc = isset($c[$i]) ? sanitize_text_field(wp_unslash($c[$i])) : '';
            $rt = isset($t[$i]) ? sanitize_text_field(wp_unslash($t[$i])) : '';
            $rs = isset($s[$i]) ? preg_replace('/[^0-9\-]/', '', (string) $s[$i]) : '';
            $re = isset($e[$i]) ? preg_replace('/[^0-9\-]/', '', (string) $e[$i]) : '';
            $rn = isset($n[$i]) ? sanitize_textarea_field(wp_unslash($n[$i])) : '';
            $rcur = isset($cur[$i]) && (int) $cur[$i] === 1 ? 1 : 0;
            if ($rc === '' && $rt === '' && $rs === '' && $re === '' && $rn === '') {
                continue;
            }
            $exp_rows[] = [
                'company'    => $rc,
                'title'      => $rt,
                'start_date' => $rs,
                'end_date'   => $rcur ? '' : $re,
                'notes'      => $rn,
                'current'    => $rcur,
            ];
        }
        if (!empty($exp_rows)) {
            update_post_meta($post_id, '_experience', $exp_rows);
        } else {
            delete_post_meta($post_id, '_experience');
        }

        // Licences & Certifications save
        $ln = isset($_POST['careernest_lic_name']) ? (array) $_POST['careernest_lic_name'] : [];
        $li = isset($_POST['careernest_lic_issuer']) ? (array) $_POST['careernest_lic_issuer'] : [];
        $le = isset($_POST['careernest_lic_expiry']) ? (array) $_POST['careernest_lic_expiry'] : [];
        $lnotes = isset($_POST['careernest_lic_notes']) ? (array) $_POST['careernest_lic_notes'] : [];
        $maxl = max(count($ln), count($li), count($le), count($lnotes));
        $lic_rows = [];
        for ($i = 0; $i < $maxl; $i++) {
            $rname = isset($ln[$i]) ? sanitize_text_field(wp_unslash($ln[$i])) : '';
            $riss  = isset($li[$i]) ? sanitize_text_field(wp_unslash($li[$i])) : '';
            $rexp  = isset($le[$i]) ? preg_replace('/[^0-9\-]/', '', (string) $le[$i]) : '';
            $rnot  = isset($lnotes[$i]) ? sanitize_textarea_field(wp_unslash($lnotes[$i])) : '';
            if ($rname === '' && $riss === '' && $rexp === '' && $rnot === '') {
                continue;
            }
            $lic_rows[] = ['name' => $rname, 'issuer' => $riss, 'expiry_date' => $rexp, 'notes' => $rnot];
        }
        if (!empty($lic_rows)) {
            update_post_meta($post_id, '_licenses', $lic_rows);
        } else {
            delete_post_meta($post_id, '_licenses');
        }

        // Links save
        $ll = isset($_POST['careernest_link_label']) ? (array) $_POST['careernest_link_label'] : [];
        $lu = isset($_POST['careernest_link_url']) ? (array) $_POST['careernest_link_url'] : [];
        $ln = isset($_POST['careernest_link_notes']) ? (array) $_POST['careernest_link_notes'] : [];
        $maxlinks = max(count($ll), count($lu), count($ln));
        $link_rows = [];
        for ($i = 0; $i < $maxlinks; $i++) {
            $rlabel = isset($ll[$i]) ? sanitize_text_field(wp_unslash($ll[$i])) : '';
            $rurl   = isset($lu[$i]) ? esc_url_raw((string) $lu[$i]) : '';
            $rnotes = isset($ln[$i]) ? sanitize_textarea_field(wp_unslash($ln[$i])) : '';
            if ($rlabel === '' && $rurl === '' && $rnotes === '') {
                continue;
            }
            // Accept only http/https URLs
            if ($rurl && ! preg_match('#^https?://#i', $rurl)) {
                $rurl = '';
            }
            $link_rows[] = ['label' => $rlabel, 'url' => $rurl, 'notes' => $rnotes];
        }
        if (!empty($link_rows)) {
            update_post_meta($post_id, '_links', $link_rows);
        } else {
            delete_post_meta($post_id, '_links');
        }
    }
}
