<?php
/*
Plugin Name: Home Depot Review Sync
Description: Sync and display reviews from HomeDepot.ca via Bazaarvoice API
Version: 1.0
Author: Lime Advertising
*/

define('HD_REVIEW_SYNC_PATH', plugin_dir_path(__FILE__));
define('HD_REVIEW_SYNC_URL', plugin_dir_url(__FILE__));

require_once HD_REVIEW_SYNC_PATH . 'includes/util.php';
require_once HD_REVIEW_SYNC_PATH . 'includes/fetch.php';
require_once HD_REVIEW_SYNC_PATH . 'includes/render.php';
require_once HD_REVIEW_SYNC_PATH . 'includes/cron.php';
require_once HD_REVIEW_SYNC_PATH . 'includes/admin.php';

// Hook: WP All Import
add_action('pmxi_saved_post', 'hd_sync_reviews_on_import', 10, 1);
function hd_sync_reviews_on_import($post_id) {
    if (get_post_type($post_id) !== 'product') return;
    hd_sync_reviews_for_product($post_id);
}
