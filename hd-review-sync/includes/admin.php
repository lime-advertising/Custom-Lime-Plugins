<?php
add_action('admin_menu', function () {
    add_submenu_page(
        'tools.php',
        'HD Review Sync',
        'HD Review Sync',
        'manage_options',
        'hd-review-sync',
        'hd_render_admin_page'
    );
});

function hd_render_admin_page() {
    if (isset($_POST['force_sync']) && check_admin_referer('hd_force_sync')) {
        hd_run_review_cron();
        echo '<div class="notice notice-success"><p>Reviews synced.</p></div>';
    }

    echo '<div class="wrap">';
    echo '<h1>Home Depot Review Sync</h1>';
    echo '<form method="post">';
    wp_nonce_field('hd_force_sync');
    submit_button('Force Sync All Reviews', 'primary', 'force_sync');
    echo '</form></div>';
}
