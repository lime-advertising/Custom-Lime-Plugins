<?php
add_action('hd_daily_review_sync', 'hd_run_review_cron');

function hd_run_review_cron() {
    $args = [
        'post_type' => 'product',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => 'the_home_depot',
                'compare' => 'EXISTS'
            ]
        ]
    ];

    $products = get_posts($args);
    foreach ($products as $product) {
        hd_sync_reviews_for_product($product->ID);
    }
}

register_activation_hook(__FILE__, function () {
    if (!wp_next_scheduled('hd_daily_review_sync')) {
        wp_schedule_event(time(), 'daily', 'hd_daily_review_sync');
    }
});

register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('hd_daily_review_sync');
});
