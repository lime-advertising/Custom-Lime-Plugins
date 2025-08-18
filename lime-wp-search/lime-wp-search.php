<?php

/**
 * Plugin Name: Lime WP Search
 * Description: Custom search functionality for WordPress.
 * Version: 1.0
 * Author: Lime Advertising
 */

if (!defined('ABSPATH')) exit;

define('LWS_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('LWS_PLUGIN_URL', plugin_dir_url(__FILE__));

// Only load code, not output
require_once LWS_PLUGIN_PATH . 'includes/search-form.php';
require_once LWS_PLUGIN_PATH . 'includes/search-query.php';

// Enqueue styles and scripts
add_action('wp_enqueue_scripts', function () {
    // Only front end
    wp_enqueue_style('lime-wp-search-style', LWS_PLUGIN_URL . 'assets/css/search.css', [], '1.0');
    wp_enqueue_script('lime-wp-search-script', LWS_PLUGIN_URL . 'assets/js/search.js', ['jquery'], '1.0', true);

    $opts = get_option('lime_wp_search_options', []);
    wp_localize_script('lime-wp-search-script', 'lws_ajax', [
        'ajax_url'         => admin_url('admin-ajax.php'),
        'nonce'            => wp_create_nonce('lws_search'),
        'click_to_show'    => !empty($opts['click_to_show']) ? 1 : 0,
        'trigger_selector' => isset($opts['trigger_selector']) ? $opts['trigger_selector'] : '',
    ]);
});


// Shortcode
add_shortcode('lime_wp_search', function ($atts) {
    return function_exists('lws_get_search_form_markup')
        ? lws_get_search_form_markup()
        : '';
});

function lime_wp_search_form_shortcode($atts)
{
    ob_start();
    include LWS_PLUGIN_PATH . 'includes/search-form.php';
    return ob_get_clean();
}

// Load admin settings
if (is_admin()) {
    require_once LWS_PLUGIN_PATH . 'includes/admin-settings.php';
}


// Force our search results template
add_filter('template_include', function ($template) {
    if (is_admin() || !is_search()) {
        return $template;
    }

    $opts = get_option('lime_wp_search_options', []);
    if (empty($opts['enabled'])) {
        return $template; // respect the plugin toggle
    }

    // Allow a theme override first (optional)
    $theme_override = locate_template(['lime-search-results.php', 'templates/lime-search-results.php'], false);
    if ($theme_override) {
        return $theme_override;
    }

    // Then fall back to the plugin template
    $plugin_template = LWS_PLUGIN_PATH . 'templates/search-results.php';
    if (file_exists($plugin_template)) {
        return $plugin_template;
    }

    return $template;
}, 1000); // high priority so we win over the theme/builder

// include SKU & variation SKU in the query
add_filter('posts_search', function ($search, \WP_Query $q) {
    if (is_admin() || !$q->is_main_query() || !$q->is_search()) {
        return $search;
    }

    $opts = get_option('lime_wp_search_options', []);
    if (empty($opts['enabled'])) return $search;

    $s = $q->get('s');
    if ($s === '' || $s === null) return $search;

    global $wpdb;
    $like = '%' . $wpdb->esc_like($s) . '%';

    // Normal title/excerpt/content search (simplified)
    $text_part = $wpdb->prepare("
        ({$wpdb->posts}.post_title LIKE %s
         OR {$wpdb->posts}.post_excerpt LIKE %s
         OR {$wpdb->posts}.post_content LIKE %s)
    ", $like, $like, $like);

    // SKU on product
    $sku_part = $wpdb->prepare("
        EXISTS (
            SELECT 1 FROM {$wpdb->postmeta} pm
            WHERE pm.post_id = {$wpdb->posts}.ID
              AND pm.meta_key = '_sku'
              AND pm.meta_value LIKE %s
        )
    ", $like);

    // Variation SKU -> parent product
    $var_part = $wpdb->prepare("
        {$wpdb->posts}.ID IN (
            SELECT DISTINCT v.post_parent
            FROM {$wpdb->posts} v
            INNER JOIN {$wpdb->postmeta} pm2 ON pm2.post_id = v.ID AND pm2.meta_key = '_sku'
            WHERE v.post_type = 'product_variation'
              AND pm2.meta_value LIKE %s
        )
    ", $like);

    // Replace default search with our OR-combined search
    $search = " AND ( {$text_part} OR {$sku_part} OR {$var_part} ) ";

    return $search;
}, 10, 2);
