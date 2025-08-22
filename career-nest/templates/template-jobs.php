<?php
/**
 * Template: CareerNest — Job Listings
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="primary" class="site-main">
    <h1><?php echo esc_html( get_the_title() ); ?></h1>
    <p><?php echo esc_html__( 'CareerNest job listings will render here.', 'careernest' ); ?></p>
</main>
<?php
get_footer();

