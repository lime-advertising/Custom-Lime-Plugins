<?php
add_shortcode('homedepot_reviews', 'hd_render_reviews_shortcode');

function hd_render_reviews_shortcode($atts) {
    global $post;
    if (!$post || get_post_type($post) !== 'product') return '';

    $reviews = get_transient("hd_reviews_{$post->ID}");
    if (!$reviews) {
        hd_sync_reviews_for_product($post->ID);
        $reviews = get_transient("hd_reviews_{$post->ID}");
    }

    if (!$reviews || !is_array($reviews)) return '';

    ob_start();
    echo '<div class="hd-reviews">';
    foreach ($reviews as $review) {
        echo '<div class="hd-review">';
        echo '<strong>' . esc_html($review['Title']) . '</strong>';
        echo '<p>' . esc_html($review['ReviewText']) . '</p>';
        echo '<div>⭐ ' . intval($review['Rating']) . ' / 5</div>';
        echo '<em>' . esc_html($review['UserNickname']) . '</em>';
        echo '</div><hr>';
    }
    echo '</div>';
    return ob_get_clean();
}
