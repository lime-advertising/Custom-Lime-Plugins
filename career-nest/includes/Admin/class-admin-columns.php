<?php
namespace CareerNest\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Admin_Columns {
    public function hooks(): void {
        add_filter( 'manage_employer_posts_columns', [ $this, 'employer_columns' ] );
        add_action( 'manage_employer_posts_custom_column', [ $this, 'render_employer_column' ], 10, 2 );

        add_filter( 'manage_job_listing_posts_columns', [ $this, 'job_columns' ] );
        add_action( 'manage_job_listing_posts_custom_column', [ $this, 'render_job_column' ], 10, 2 );

        add_action( 'restrict_manage_posts', [ $this, 'jobs_quick_filters' ] );
        add_action( 'pre_get_posts', [ $this, 'apply_jobs_quick_filters' ] );

        add_filter( 'manage_job_application_posts_columns', [ $this, 'application_columns' ] );
        add_action( 'manage_job_application_posts_custom_column', [ $this, 'render_application_column' ], 10, 2 );

        // Applicants
        add_filter( 'manage_applicant_posts_columns', [ $this, 'applicant_columns' ] );
        add_action( 'manage_applicant_posts_custom_column', [ $this, 'render_applicant_column' ], 10, 2 );
    }

    public function employer_columns( array $columns ): array {
        // Preserve the checkbox column if present.
        $new = [];
        if ( isset( $columns['cb'] ) ) {
            $new['cb'] = $columns['cb'];
        }
        $new['id']     = __( 'ID', 'careernest' );
        $new['logo']   = __( 'Logo', 'careernest' );
        $new['title']  = __( 'Company Name', 'careernest' );
        $new['website']= __( 'Website', 'careernest' );
        $new['modified']= __( 'Last Modified', 'careernest' );
        return $new;
    }

    public function render_employer_column( string $column, int $post_id ): void {
        switch ( $column ) {
            case 'id':
                echo (int) $post_id;
                break;
            case 'logo':
                if ( has_post_thumbnail( $post_id ) ) {
                    $thumb = get_the_post_thumbnail( $post_id, [ 48, 48 ], [ 'style' => 'width:48px;height:48px;object-fit:cover;border-radius:4px;' ] );
                    if ( $thumb ) {
                        echo $thumb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    }
                } else {
                    echo '<span class="dashicons dashicons-format-image" style="font-size:24px;color:#a7aaad;"></span>';
                }
                break;
            case 'website':
                $url = (string) get_post_meta( $post_id, '_website', true );
                if ( $url ) {
                    $disp = preg_replace( '#^https?://#', '', $url );
                    echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noreferrer noopener">' . esc_html( $disp ) . '</a>';
                } else {
                    echo '&mdash;';
                }
                break;
            case 'modified':
                $ts = get_post_modified_time( 'U', false, $post_id, true );
                if ( $ts ) {
                    $fmt = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
                    echo esc_html( date_i18n( $fmt, (int) $ts ) );
                } else {
                    echo '&mdash;';
                }
                break;
        }
    }

    public function job_columns( array $columns ): array {
        $new = [];
        if ( isset( $columns['cb'] ) ) {
            $new['cb'] = $columns['cb'];
        }
        // Composite title column
        $new['job']    = __( 'Job Title', 'careernest' );
        $new['logo']   = __( 'Logo', 'careernest' );
        $new['expiry'] = __( 'Expiry Date', 'careernest' );
        $new['status'] = __( 'Status', 'careernest' );
        $new['apps']   = __( 'Applications', 'careernest' );
        return $new;
    }

    public function render_job_column( string $column, int $post_id ): void {
        switch ( $column ) {
            case 'job':
                $title = get_the_title( $post_id );
                $edit  = get_edit_post_link( $post_id );
                echo '<a class="row-title" href="' . esc_url( $edit ) . '">' . esc_html( $title ) . '</a>';
                $emp_id = (int) get_post_meta( $post_id, '_employer_id', true );
                if ( $emp_id ) {
                    $emp_title = get_the_title( $emp_id );
                    $emp_link  = get_edit_post_link( $emp_id );
                    if ( $emp_title ) {
                        echo '<div class="cn-under" style="color:#646970;font-size:12px;margin-top:2px;">';
                        echo esc_html__( 'Employer:', 'careernest' ) . ' ';
                        echo $emp_link ? '<a href="' . esc_url( $emp_link ) . '">' . esc_html( $emp_title ) . '</a>' : esc_html( $emp_title );
                        echo '</div>';
                    }
                }
                break;
            case 'logo':
                $emp_id = (int) get_post_meta( $post_id, '_employer_id', true );
                if ( $emp_id && has_post_thumbnail( $emp_id ) ) {
                    $thumb = get_the_post_thumbnail( $emp_id, [ 40, 40 ], [ 'style' => 'width:40px;height:40px;object-fit:cover;border-radius:4px;' ] );
                    if ( $thumb ) { echo $thumb; }
                } else {
                    echo '<span class="dashicons dashicons-format-image" style="font-size:20px;color:#a7aaad;"></span>';
                }
                break;
            case 'expiry':
                $val = (string) get_post_meta( $post_id, '_closing_date', true );
                if ( $val ) {
                    // Display in site format
                    $ts = strtotime( $val . ' 00:00:00' );
                    $fmt = get_option( 'date_format' );
                    echo esc_html( $ts ? date_i18n( $fmt, $ts ) : $val );
                } else {
                    echo '&mdash;';
                }
                break;
            case 'status':
                $st = get_post_status( $post_id );
                $obj = $st ? get_post_status_object( $st ) : null;
                echo esc_html( $obj && ! empty( $obj->label ) ? $obj->label : ucfirst( (string) $st ) );
                break;
            case 'apps':
                $q = new \WP_Query( [
                    'post_type'              => 'job_application',
                    'post_status'            => 'any',
                    'posts_per_page'         => -1,
                    'fields'                 => 'ids',
                    'no_found_rows'          => true,
                    'update_post_meta_cache' => false,
                    'update_post_term_cache' => false,
                    'meta_query'             => [ [ 'key' => '_job_id', 'value' => $post_id, 'compare' => '=' ] ],
                ] );
                $count = is_wp_error( $q ) ? 0 : (int) count( $q->posts );
                echo esc_html( (string) $count );
                break;
        }
    }

    /**
     * Add quick filter dropdown for job expiry (Active/Expired).
     */
    public function jobs_quick_filters(): void {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || 'edit-job_listing' !== $screen->id ) {
            return;
        }
        $current = isset( $_GET['cn_expiry'] ) ? sanitize_key( (string) $_GET['cn_expiry'] ) : '';
        echo '<label for="filter-by-expiry" class="screen-reader-text">' . esc_html__( 'Filter by expiry', 'careernest' ) . '</label>';
        echo '<select name="cn_expiry" id="filter-by-expiry">';
        echo '<option value="">' . esc_html__( 'All jobs', 'careernest' ) . '</option>';
        echo '<option value="active"' . selected( $current, 'active', false ) . '>' . esc_html__( 'Active (not expired)', 'careernest' ) . '</option>';
        echo '<option value="expired"' . selected( $current, 'expired', false ) . '>' . esc_html__( 'Expired', 'careernest' ) . '</option>';
        echo '</select>';
    }

    /**
     * Apply quick filter for Active/Expired jobs via closing date.
     */
    public function apply_jobs_quick_filters( \WP_Query $query ): void {
        if ( ! is_admin() || ! $query->is_main_query() ) {
            return;
        }
        $post_type = $query->get( 'post_type' );
        if ( 'job_listing' !== $post_type ) {
            return;
        }
        $filter = isset( $_GET['cn_expiry'] ) ? sanitize_key( (string) $_GET['cn_expiry'] ) : '';
        if ( ! in_array( $filter, [ 'active', 'expired' ], true ) ) {
            return;
        }
        $today = current_time( 'Y-m-d' );
        $meta_query = (array) $query->get( 'meta_query', [] );
        if ( 'expired' === $filter ) {
            $meta_query[] = [ 'key' => '_closing_date', 'value' => $today, 'compare' => '<', 'type' => 'DATE' ];
        } elseif ( 'active' === $filter ) {
            // Active = no closing date OR closing date >= today
            $meta_query[] = [
                'relation' => 'OR',
                [ 'key' => '_closing_date', 'compare' => 'NOT EXISTS' ],
                [ 'key' => '_closing_date', 'value' => '', 'compare' => '=' ],
                [ 'key' => '_closing_date', 'value' => $today, 'compare' => '>=', 'type' => 'DATE' ],
            ];
        }
        $query->set( 'meta_query', $meta_query );
    }

    public function application_columns( array $columns ): array {
        $new = [];
        if ( isset( $columns['cb'] ) ) { $new['cb'] = $columns['cb']; }
        $new['applicant'] = __( 'Applicant', 'careernest' );
        $new['job']       = __( 'Job', 'careernest' );
        $new['status']    = __( 'Status', 'careernest' );
        $new['email']     = __( 'Email', 'careernest' );
        return $new;
    }

    public function render_application_column( string $column, int $post_id ): void {
        switch ( $column ) {
            case 'applicant':
                $title = get_the_title( $post_id );
                $edit  = get_edit_post_link( $post_id );
                echo '<a class="row-title" href="' . esc_url( $edit ) . '">' . esc_html( $title ) . '</a>';
                break;
            case 'job':
                $job_id = (int) get_post_meta( $post_id, '_job_id', true );
                if ( $job_id ) {
                    $title = get_the_title( $job_id );
                    $edit  = get_edit_post_link( $job_id );
                    echo $edit ? '<a href="' . esc_url( $edit ) . '">' . esc_html( $title ) . '</a>' : esc_html( $title );
                } else {
                    echo '&mdash;';
                }
                break;
            case 'status':
                $st = (string) get_post_meta( $post_id, '_app_status', true );
                $labels = [
                    'new'            => __( 'New', 'careernest' ),
                    'interviewed'    => __( 'Interviewed', 'careernest' ),
                    'offer_extended' => __( 'Offer Extended', 'careernest' ),
                    'hired'          => __( 'Hired', 'careernest' ),
                    'rejected'       => __( 'Rejected', 'careernest' ),
                    'archived'       => __( 'Archived', 'careernest' ),
                ];
                $label = $labels[ $st ] ?? __( 'New', 'careernest' );
                $class = 'status-' . ( $st ?: 'new' );
                echo '<span class="cn-badge ' . esc_attr( $class ) . '">' . esc_html( $label ) . '</span>';
                break;
            case 'email':
                $applicant_id = (int) get_post_meta( $post_id, '_applicant_id', true );
                $email = '';
                if ( $applicant_id ) {
                    $uid = (int) get_post_meta( $applicant_id, '_user_id', true );
                    if ( $uid ) {
                        $u = get_user_by( 'id', $uid );
                        if ( $u ) { $email = (string) $u->user_email; }
                    }
                }
                echo $email ? '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>' : '&mdash;';
                break;
        }
    }

    public function applicant_columns( array $columns ): array {
        $new = [];
        if ( isset( $columns['cb'] ) ) { $new['cb'] = $columns['cb']; }
        $new['photo']     = __( 'Photo', 'careernest' );
        $new['title']     = __( 'Name', 'careernest' );
        $new['position']  = __( 'Position', 'careernest' );
        $new['email']     = __( 'Email', 'careernest' );
        $new['location']  = __( 'Location', 'careernest' );
        $new['resume']    = __( 'Resume', 'careernest' );
        $new['pref']      = __( 'Work Preference', 'careernest' );
        return $new;
    }

    public function render_applicant_column( string $column, int $post_id ): void {
        switch ( $column ) {
            case 'photo':
                if ( has_post_thumbnail( $post_id ) ) {
                    $thumb = get_the_post_thumbnail( $post_id, [ 40, 40 ], [ 'style' => 'width:40px;height:40px;object-fit:cover;border-radius:50%;' ] );
                    if ( $thumb ) { echo $thumb; }
                } else {
                    echo '<span class="dashicons dashicons-id" style="font-size:20px;color:#a7aaad;"></span>';
                }
                break;
            case 'position':
                $pos = (string) get_post_meta( $post_id, '_professional_title', true );
                echo $pos !== '' ? esc_html( $pos ) : '&mdash;';
                break;
            case 'email':
                $uid = (int) get_post_meta( $post_id, '_user_id', true );
                if ( $uid > 0 ) {
                    $u = get_user_by( 'id', $uid );
                    if ( $u && $u->user_email ) {
                        $email = (string) $u->user_email;
                        echo '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>';
                        break;
                    }
                }
                echo '&mdash;';
                break;
            case 'location':
                $loc = (string) get_post_meta( $post_id, '_location', true );
                echo $loc !== '' ? esc_html( $loc ) : '&mdash;';
                break;
            case 'resume':
                $att_id = (int) get_post_meta( $post_id, '_resume_attachment_id', true );
                if ( $att_id ) {
                    $url = wp_get_attachment_url( $att_id );
                    $name = get_the_title( $att_id );
                    if ( $url ) {
                        echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noreferrer noopener">' . esc_html( $name ?: basename( $url ) ) . '</a>';
                        break;
                    }
                }
                echo '&mdash;';
                break;
            case 'pref':
                $badges = [];
                $avail = (bool) get_post_meta( $post_id, '_available_for_work', true );
                $badges[] = '<span class="cn-badge ' . ( $avail ? 'cn-badge-ok' : 'cn-badge-muted' ) . '">' . esc_html( $avail ? __( 'Available', 'careernest' ) : __( 'Not available', 'careernest' ) ) . '</span>';
                $types = get_post_meta( $post_id, '_work_types', true );
                $types = is_array( $types ) ? array_map( 'sanitize_text_field', $types ) : [];
                $labels = [
                    'full_time'  => __( 'Full-time', 'careernest' ),
                    'part_time'  => __( 'Part-time', 'careernest' ),
                    'contract'   => __( 'Contract', 'careernest' ),
                    'temporary'  => __( 'Temporary', 'careernest' ),
                    'internship' => __( 'Internship', 'careernest' ),
                    'freelance'  => __( 'Freelance', 'careernest' ),
                    'remote'     => __( 'Remote', 'careernest' ),
                    'on_site'    => __( 'On-site', 'careernest' ),
                    'hybrid'     => __( 'Hybrid', 'careernest' ),
                ];
                foreach ( $types as $t ) {
                    $slug = sanitize_key( (string) $t );
                    $label = $labels[ $slug ] ?? ucfirst( str_replace( '_', ' ', $slug ) );
                    $badges[] = '<span class="cn-badge cn-badge-type cn-badge-type-' . esc_attr( $slug ) . '">' . esc_html( $label ) . '</span>';
                }
                echo implode( ' ', $badges );
                break;
        }
    }
}
