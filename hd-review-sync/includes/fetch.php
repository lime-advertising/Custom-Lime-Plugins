<?php
function hd_fetch_reviews($product_id, $limit = 10) {
    $url = add_query_arg([
        'Filter' => "ProductId:$product_id",
        'Sort' => 'SubmissionTime:desc',
        'Limit' => $limit,
        'Offset' => 0,
        'Include' => 'Products',
        'Stats' => 'Reviews',
        'passkey' => 'cad__en_CA_key',
        'apiversion' => '5.4'
    ], 'https://api.bazaarvoice.com/data/reviews.json');

    $response = wp_remote_get($url);
    if (is_wp_error($response)) return [];

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    return $data['Results'] ?? [];
}

function hd_sync_reviews_for_product($post_id) {
    $url = get_field('the_home_depot', $post_id);
    $product_id = hd_extract_product_id($url);
    if (!$product_id) return;

    $reviews = hd_fetch_reviews($product_id);
    set_transient("hd_reviews_$post_id", $reviews, DAY_IN_SECONDS);
}
