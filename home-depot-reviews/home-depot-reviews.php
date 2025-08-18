<?php

/**
 * Plugin Name: Home Depot Reviews Shortcode
 * Description: Adds a shortcode to display up to 10 Home Depot product reviews via SerpAPI. Pulls product_id from WooCommerce ACF custom field. Includes visual stars, fallback handling, admin API key storage, and conditional rendering.
 * Version: 1.8
 * Author: Lime Advertising
 */


// === Shortcode to render reviews ===
add_shortcode('homedepot_reviews', 'render_homedepot_reviews');

function render_homedepot_reviews($atts)
{
    global $post;

    $atts = shortcode_atts([
        'product_id' => ''
    ], $atts);

    // Allow pulling from ACF field if shortcode doesn't specify ID
    if (empty($atts['product_id']) && isset($post->ID)) {
        $atts['product_id'] = get_field('hd_product_id', $post->ID);
    }

    // Hide entire block if "show_reviews" is false
    if (function_exists('get_field') && !get_field('show_reviews', $post->ID)) {
        return '';
    }

    if (!$atts['product_id']) {
        return '<p class="hd-review-error">No Home Depot product ID found.</p>';
    }

    $cache_key = 'hd_reviews_' . $atts['product_id'];
    $data = get_transient($cache_key);

    if (!$data) {
        $api_key = get_option('hd_serpapi_key');
        if (!$api_key) {
            return '<p class="hd-review-error">SerpAPI key is not configured. Please set it in plugin settings.</p>';
        }

        $url = add_query_arg([
            'engine' => 'home_depot_product_reviews',
            'product_id' => $atts['product_id'],
            'sort_by' => 'newest',
            'pagesize' => 10,
            'api_key' => $api_key
        ], 'https://serpapi.com/search.json');

        $response = wp_remote_get($url);
        if (is_wp_error($response)) {
            return '<p class="hd-review-error">Error fetching Home Depot reviews.</p>';
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($data['reviews'])) {
            return '<p class="hd-review-none">No reviews found for this product.</p>';
        }

        set_transient($cache_key, $data, HOUR_IN_SECONDS);
    }

    ob_start();
?>
    <div class="hd-review-wrapper">
        <div class="hd-review-summary">
            <div style="margin-bottom: 24px;" class="hd-review-header">
                <h3 style="margin: 0;" class=" hd-review-heading">Customer Reviews</h3>

                <p style="margin: 0;" class=" hd-review-average"><?= esc_html($data['overall_rating']) ?>
                    <?= render_star_rating($data['overall_rating']) ?>
                    <span>(<?= esc_html($data['total_review']) ?> ratings)</span>
                </p>
            </div>
        </div>

        <div class="hd-review-swiper swiper">
            <div class="swiper-wrapper">
                <?php foreach (array_slice($data['reviews'], 0, 10) as $review): ?>
                    <div class="swiper-slide">
                        <div class="hd-review-item">
                            <p class="hd-review-author"><strong><?= esc_html($review['reviewer']['name'] ?? 'Anonymous') ?></strong>
                                <?= render_star_rating($review['rating'] ?? 0) ?></p>
                            <?php if (!empty($review['title'])): ?>
                                <p class="hd-review-title"><strong><?= esc_html($review['title']) ?></strong></p>
                            <?php endif; ?>
                            <div class="hd-review-content">
                                <p class="hd-review-text"><?= esc_html($review['text'] ?? 'No review content provided.') ?></p>
                                <?php if (!empty($review['images'][0]['thumbnail'])): ?>
                                    <img src="<?= esc_url($review['images'][0]['thumbnail']) ?>" alt="Review Image" class="hd-review-image" />
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="hd-custom-nav">
            <button class="hd-button-prev">&#10094;</button>
            <button class="hd-button-next">&#10095;</button>
        </div>
    </div>
<?php
    return ob_get_clean();
}

function render_star_rating($rating)
{
    $full_stars = floor($rating);
    $half_star = ($rating - $full_stars >= 0.25 && $rating - $full_stars <= 0.75);
    $html = '<span class="hd-star-rating">';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $full_stars) {
            $html .= '<span class="star full' . '">&#x2605;</span>';
        } elseif ($i == $full_stars + 1 && $half_star) {
            $html .= '<span class="star half' . '">&#x2605;</span>';
        } else {
            $html .= '<span class="star empty' . '">&#x2605;</span>';
        }
    }
    $html .= '</span>';
    return $html;
}

// === Enqueue styles/scripts ===
function enqueue_hd_reviews_assets()
{
    if (!is_admin()) {
        wp_enqueue_style('swiper-css', 'https://unpkg.com/swiper/swiper-bundle.min.css', [], '11.0.5');
        wp_enqueue_script('swiper-js', 'https://unpkg.com/swiper/swiper-bundle.min.js', [], '11.0.5', true);

        wp_enqueue_style('hd-reviews-css', plugin_dir_url(__FILE__) . 'hd-reviews.css', [], '1.0');
        wp_enqueue_script('hd-reviews-js', plugin_dir_url(__FILE__) . 'hd-reviews.js', ['swiper-js'], '1.0', true);
    }
}
add_action('wp_enqueue_scripts', 'enqueue_hd_reviews_assets');

// === Admin: add 2 options pages ===
add_action('admin_menu', function () {
    add_options_page(
        'Home Depot Reviews',
        'Home Depot Reviews',
        'manage_options',
        'hd-reviews-settings',
        'hd_reviews_settings_page'
    );

    // add_options_page(
    //     'Update Product Review IDs',
    //     'Update HD Review IDs',
    //     'manage_options',
    //     'update-hd-review-ids',
    //     'render_update_review_ids_page'
    // );
});

// === Admin: API key settings page ===
function hd_reviews_settings_page()
{
    if (isset($_POST['hd_serpapi_key'])) {
        update_option('hd_serpapi_key', sanitize_text_field($_POST['hd_serpapi_key']));
        echo '<div class="updated"><p>API key updated.</p></div>';
    }
    $api_key = get_option('hd_serpapi_key', '');
?>
    <div class="wrap hd-settings-page">
        <h1>Home Depot Reviews Settings</h1>
        <form method="post">
            <label for="hd_serpapi_key">SerpAPI Key:</label><br>
            <input type="text" id="hd_serpapi_key" name="hd_serpapi_key" value="<?= esc_attr($api_key) ?>" style="width:400px;" /><br><br>
            <input type="submit" value="Save" class="button button-primary" />
        </form>
    </div>
<?php
}


// === Rating summary shortcode ===
add_shortcode('homedepot_rating_summary', 'render_homedepot_rating_summary');

function render_homedepot_rating_summary($atts)
{
    global $post;

    if (function_exists('get_field') && !get_field('show_reviews', $post->ID)) {
        return '';
    }

    $atts = shortcode_atts([
        'product_id' => ''
    ], $atts);

    if (empty($atts['product_id']) && isset($post->ID)) {
        $atts['product_id'] = get_field('hd_product_id', $post->ID);
    }

    if (!$atts['product_id']) {
        return '<span class="hd-review-error">Rating unavailable</span>';
    }

    $cache_key = 'hd_reviews_' . $atts['product_id'];
    $data = get_transient($cache_key);

    if (!$data) {
        $api_key = get_option('hd_serpapi_key');
        if (!$api_key) {
            return '<span class="hd-review-error">API key missing</span>';
        }

        $url = add_query_arg([
            'engine' => 'home_depot_product_reviews',
            'product_id' => $atts['product_id'],
            'sort_by' => 'newest',
            'pagesize' => 10,
            'api_key' => $api_key
        ], 'https://serpapi.com/search.json');

        $response = wp_remote_get($url);
        if (is_wp_error($response)) {
            return '<span class="hd-review-error">Rating unavailable</span>';
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($data['reviews'])) {
            return '<span class="hd-review-error">No ratings yet</span>';
        }

        set_transient($cache_key, $data, HOUR_IN_SECONDS);
    }

    $rating = esc_html($data['overall_rating']);
    $stars = render_star_rating($rating);
    $count = esc_html($data['total_review']);

    return "<span class='hd-rating-summary'>{$rating} {$stars} ({$count} ratings)</span>";
}

// require_once plugin_dir_path(__FILE__) . 'inc/update-review-ids.php';
