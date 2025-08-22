<?php
/**
 * Template: CareerNest — Applicant Dashboard
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_user_logged_in() ) {
    wp_safe_redirect( wp_login_url( get_permalink() ) );
    exit;
}

get_header();
?>
<main id="primary" class="site-main">
    <h1><?php echo esc_html__( 'Applicant Dashboard', 'careernest' ); ?></h1>
    <p><?php echo esc_html__( 'Applicant profile and applications will appear here.', 'careernest' ); ?></p>
</main>
<?php
get_footer();

