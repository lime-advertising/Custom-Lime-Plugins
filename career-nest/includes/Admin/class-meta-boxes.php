<?php
namespace CareerNest\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Meta_Boxes {
    public function hooks(): void {
        add_action( 'add_meta_boxes', [ $this, 'register_meta_boxes' ] );
        add_action( 'save_post', [ $this, 'save_job_meta' ] );
        add_action( 'save_post', [ $this, 'save_employer_meta' ] );
        add_action( 'save_post', [ $this, 'save_applicant_meta' ] );
    }

    public function register_meta_boxes(): void {
        add_meta_box( 'careernest_job_details', __( 'Job Details', 'careernest' ), [ $this, 'render_job_details' ], 'job_listing', 'normal', 'high' );
        add_meta_box( 'careernest_employer_details', __( 'Employer Details', 'careernest' ), [ $this, 'render_employer_details' ], 'employer', 'normal', 'default' );
        add_meta_box( 'careernest_applicant_details', __( 'Applicant Details', 'careernest' ), [ $this, 'render_applicant_details' ], 'applicant', 'normal', 'default' );
    }

    private function get_employers_for_dropdown(): array {
        $args = [
            'post_type'      => 'employer',
            'posts_per_page' => 200,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'fields'         => 'ids',
        ];
        $ids = get_posts( $args );
        $out = [];
        foreach ( $ids as $id ) {
            $out[ $id ] = get_the_title( $id );
        }
        return $out;
    }

    public function render_job_details( \WP_Post $post ): void {
        wp_nonce_field( 'careernest_job_meta', 'careernest_job_meta_nonce' );

        $employer_id      = (int) get_post_meta( $post->ID, '_employer_id', true );
        $job_location     = (string) get_post_meta( $post->ID, '_job_location', true );
        $remote_position  = (bool) get_post_meta( $post->ID, '_remote_position', true );
        $opening_date     = (string) get_post_meta( $post->ID, '_opening_date', true );
        $closing_date     = (string) get_post_meta( $post->ID, '_closing_date', true );
        $salary_range     = (string) get_post_meta( $post->ID, '_salary_range', true );
        $salary           = get_post_meta( $post->ID, '_salary', true );
        $apply_externally = (bool) get_post_meta( $post->ID, '_apply_externally', true );
        $external_apply   = (string) get_post_meta( $post->ID, '_external_apply', true );
        $posted_by        = (int) get_post_meta( $post->ID, '_posted_by', true );
        $position_filled  = (bool) get_post_meta( $post->ID, '_position_filled', true );

        $employers = $this->get_employers_for_dropdown();
        $users     = get_users( [ 'fields' => [ 'ID', 'display_name' ] ] );

        echo '<table class="form-table">';
        // Employer dropdown
        echo '<tr><th><label for="careernest_employer_id">' . esc_html__( 'Employer', 'careernest' ) . '</label></th><td>';
        echo '<select id="careernest_employer_id" name="careernest_employer_id">';
        echo '<option value="">' . esc_html__( 'Select employer…', 'careernest' ) . '</option>';
        foreach ( $employers as $id => $label ) {
            echo '<option value="' . esc_attr( (string) $id ) . '"' . selected( $employer_id, $id, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></td></tr>';

        // Location + Remote
        echo '<tr><th><label for="careernest_job_location">' . esc_html__( 'Job Location', 'careernest' ) . '</label></th><td>';
        echo '<input type="text" id="careernest_job_location" name="careernest_job_location" class="regular-text" value="' . esc_attr( $job_location ) . '" /> ';
        echo '<label><input type="checkbox" name="careernest_remote_position" value="1" ' . checked( $remote_position, true, false ) . ' /> ' . esc_html__( 'Remote position', 'careernest' ) . '</label>';
        echo '</td></tr>';

        // Dates
        echo '<tr><th>' . esc_html__( 'Opening Date', 'careernest' ) . '</th><td>';
        echo '<input type="date" name="careernest_opening_date" value="' . esc_attr( $opening_date ) . '" />';
        echo '</td></tr>';
        echo '<tr><th>' . esc_html__( 'Closing Date', 'careernest' ) . '</th><td>';
        echo '<input type="date" name="careernest_closing_date" value="' . esc_attr( $closing_date ) . '" />';
        echo '</td></tr>';

        // Salary range
        echo '<tr><th><label for="careernest_salary_range">' . esc_html__( 'Salary Range', 'careernest' ) . '</label></th><td>';
        echo '<input type="text" id="careernest_salary_range" name="careernest_salary_range" class="regular-text" value="' . esc_attr( $salary_range ) . '" />';
        echo '</td></tr>';

        // Admin-only: Salary toggle + Salary
        if ( current_user_can( 'manage_options' ) ) {
            echo '<tr class="cn-salary-row"><th><label for="careernest_salary">' . esc_html__( 'Salary (numeric)', 'careernest' ) . '</label></th><td>';
            echo '<input type="number" step="0.01" id="careernest_salary" name="careernest_salary" class="small-text" value="' . esc_attr( (string) $salary ) . '" />';
            echo '</td></tr>';
        }

        // Apply externally
        echo '<tr><th>' . esc_html__( 'Apply Externally', 'careernest' ) . '</th><td>';
        echo '<label><input type="checkbox" id="careernest_apply_externally" name="careernest_apply_externally" value="1" ' . checked( $apply_externally, true, false ) . ' /> ' . esc_html__( 'Check if applications are handled externally (link or email).', 'careernest' ) . '</label>';
        echo '<div id="careernest_external_container" style="margin-top:8px;' . ( $apply_externally ? '' : 'display:none;' ) . '">';
        echo '<input type="text" id="careernest_external_apply" name="careernest_external_apply" class="regular-text" placeholder="https://example.com/apply or jobs@example.com" value="' . esc_attr( $external_apply ) . '" />';
        echo '</div>';
        echo '</td></tr>';

        // Admin-only: Posted by + Position filled
        if ( current_user_can( 'manage_options' ) ) {
            echo '<tr><th><label for="careernest_posted_by">' . esc_html__( 'Job Posted By', 'careernest' ) . '</label></th><td>';
            echo '<select id="careernest_posted_by" name="careernest_posted_by">';
            echo '<option value="">' . esc_html__( 'Select user…', 'careernest' ) . '</option>';
            foreach ( $users as $u ) {
                $uid   = (int) $u->ID;
                $label = $u->display_name . ' (#' . $uid . ')';
                echo '<option value="' . esc_attr( (string) $uid ) . '"' . selected( $posted_by, $uid, false ) . '>' . esc_html( $label ) . '</option>';
            }
            echo '</select>';
            echo '</td></tr>';

            echo '<tr><th>' . esc_html__( 'Position Filled', 'careernest' ) . '</th><td>';
            echo '<label><input type="checkbox" name="careernest_position_filled" value="1" ' . checked( $position_filled, true, false ) . ' /> ' . esc_html__( 'Mark as filled', 'careernest' ) . '</label>';
            echo '</td></tr>';
        }
        echo '</table>';
    }

    public function render_employer_details( \WP_Post $post ): void {
        wp_nonce_field( 'careernest_employer_meta', 'careernest_employer_meta_nonce' );
        $website = (string) get_post_meta( $post->ID, '_website', true );
        echo '<table class="form-table">';
        echo '<tr><th><label for="careernest_website">' . esc_html__( 'Company Website', 'careernest' ) . '</label></th><td>';
        echo '<input type="url" id="careernest_website" name="careernest_website" class="regular-text" value="' . esc_attr( $website ) . '" placeholder="https://example.com" />';
        echo '</td></tr>';
        echo '</table>';
    }

    public function render_applicant_details( \WP_Post $post ): void {
        wp_nonce_field( 'careernest_applicant_meta', 'careernest_applicant_meta_nonce' );
        $user_id = (int) get_post_meta( $post->ID, '_user_id', true );
        echo '<p>' . esc_html__( 'Linked WP User ID:', 'careernest' ) . ' <code>' . esc_html( (string) ( $user_id ?: 0 ) ) . '</code></p>';
        echo '<p class="description">' . esc_html__( 'User linkage is managed by registration flows and cannot be edited here yet.', 'careernest' ) . '</p>';
    }

    public function save_job_meta( int $post_id ): void {
        if ( get_post_type( $post_id ) !== 'job_listing' ) {
            return;
        }
        if ( ! isset( $_POST['careernest_job_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['careernest_job_meta_nonce'] ?? '' ) ), 'careernest_job_meta' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Employer
        $employer_id = isset( $_POST['careernest_employer_id'] ) ? absint( $_POST['careernest_employer_id'] ) : 0;
        update_post_meta( $post_id, '_employer_id', $employer_id );

        // Location + Remote
        $job_location    = isset( $_POST['careernest_job_location'] ) ? sanitize_text_field( wp_unslash( $_POST['careernest_job_location'] ) ) : '';
        $remote_position = ! empty( $_POST['careernest_remote_position'] ) ? 1 : 0;
        update_post_meta( $post_id, '_job_location', $job_location );
        update_post_meta( $post_id, '_remote_position', $remote_position );

        // Dates
        $opening_date = isset( $_POST['careernest_opening_date'] ) ? preg_replace( '/[^0-9\-]/', '', (string) $_POST['careernest_opening_date'] ) : '';
        $closing_date = isset( $_POST['careernest_closing_date'] ) ? preg_replace( '/[^0-9\-]/', '', (string) $_POST['careernest_closing_date'] ) : '';
        update_post_meta( $post_id, '_opening_date', $opening_date );
        update_post_meta( $post_id, '_closing_date', $closing_date );

        // Salary range
        $salary_range = isset( $_POST['careernest_salary_range'] ) ? sanitize_text_field( wp_unslash( $_POST['careernest_salary_range'] ) ) : '';
        update_post_meta( $post_id, '_salary_range', $salary_range );

        // Salary (admin only)
        if ( current_user_can( 'manage_options' ) ) {
            $salary = isset( $_POST['careernest_salary'] ) ? (float) $_POST['careernest_salary'] : '';
            if ( $salary !== '' ) {
                update_post_meta( $post_id, '_salary', $salary );
            } else {
                delete_post_meta( $post_id, '_salary' );
            }
        }

        // Apply externally toggle + external value
        $apply_externally = ! empty( $_POST['careernest_apply_externally'] ) ? 1 : 0;
        update_post_meta( $post_id, '_apply_externally', $apply_externally );
        $external_apply_raw = isset( $_POST['careernest_external_apply'] ) ? trim( (string) $_POST['careernest_external_apply'] ) : '';
        $external_apply_val = '';
        if ( $external_apply_raw !== '' ) {
            if ( strpos( $external_apply_raw, '@' ) !== false ) {
                $maybe = sanitize_email( $external_apply_raw );
                if ( is_email( $maybe ) ) {
                    $external_apply_val = $maybe;
                }
            } else {
                $maybe = esc_url_raw( $external_apply_raw );
                if ( $maybe ) {
                    $external_apply_val = $maybe;
                }
            }
        }
        if ( $external_apply_val !== '' ) {
            update_post_meta( $post_id, '_external_apply', $external_apply_val );
        } else {
            delete_post_meta( $post_id, '_external_apply' );
        }

        // Admin-only fields
        if ( current_user_can( 'manage_options' ) ) {
            $posted_by = isset( $_POST['careernest_posted_by'] ) ? absint( $_POST['careernest_posted_by'] ) : 0;
            update_post_meta( $post_id, '_posted_by', $posted_by );
            $position_filled = ! empty( $_POST['careernest_position_filled'] ) ? 1 : 0;
            update_post_meta( $post_id, '_position_filled', $position_filled );
        }
    }

    public function save_employer_meta( int $post_id ): void {
        if ( get_post_type( $post_id ) !== 'employer' ) {
            return;
        }
        if ( ! isset( $_POST['careernest_employer_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['careernest_employer_meta_nonce'] ?? '' ) ), 'careernest_employer_meta' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $website = isset( $_POST['careernest_website'] ) ? esc_url_raw( (string) $_POST['careernest_website'] ) : '';
        if ( $website ) {
            update_post_meta( $post_id, '_website', $website );
        } else {
            delete_post_meta( $post_id, '_website' );
        }
    }

    public function save_applicant_meta( int $post_id ): void {
        if ( get_post_type( $post_id ) !== 'applicant' ) {
            return;
        }
        if ( ! isset( $_POST['careernest_applicant_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['careernest_applicant_meta_nonce'] ?? '' ) ), 'careernest_applicant_meta' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        // Nothing editable yet; placeholder for future fields like resume upload.
    }
}

