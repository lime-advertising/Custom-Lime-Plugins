<?php

add_filter('pre_get_posts', 'lime_extend_search_query');

function lime_extend_search_query($query)
{
    if (!is_admin() && $query->is_main_query() && $query->is_search()) {
        $options = get_option('lime_wp_search_options');

        if (empty($options['enabled'])) return;

        if (!empty($options['post_types'])) {
            $query->set('post_type', $options['post_types']);
        }

        if (!empty($options['meta_keys'])) {
            $keys = array_map('trim', explode(',', $options['meta_keys']));
            $meta_query = ['relation' => 'OR'];

            foreach ($keys as $key) {
                $meta_query[] = [
                    'key' => $key,
                    'value' => $query->get('s'),
                    'compare' => 'LIKE',
                ];
            }

            $query->set('meta_query', $meta_query);
        }
    }
}

add_action('wp_ajax_lime_wp_live_search', 'lime_wp_ajax_search');
add_action('wp_ajax_nopriv_lime_wp_live_search', 'lime_wp_ajax_search');

function lws_get_product_ids_by_sku_like($keyword)
{
    global $wpdb;
    if ($keyword === '') return [];
    $like = '%' . $wpdb->esc_like($keyword) . '%';

    // Direct product SKUs
    $product_ids = $wpdb->get_col($wpdb->prepare("
        SELECT DISTINCT p.ID
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
        WHERE p.post_type = 'product'
          AND p.post_status = 'publish'
          AND pm.meta_key = '_sku'
          AND pm.meta_value LIKE %s
    ", $like));

    // Variation SKUs -> parent product
    $variation_parent_ids = $wpdb->get_col($wpdb->prepare("
        SELECT DISTINCT v.post_parent
        FROM {$wpdb->posts} v
        INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = v.ID
        INNER JOIN {$wpdb->posts} p ON p.ID = v.post_parent
        WHERE v.post_type = 'product_variation'
          AND pm.meta_key = '_sku'
          AND pm.meta_value LIKE %s
          AND p.post_status = 'publish'
    ", $like));

    $all = array_merge((array)$product_ids, (array)$variation_parent_ids);
    $all = array_values(array_unique(array_map('intval', $all)));
    return $all;
}


function lime_wp_ajax_search()
{
    // Verify nonce (looks for _ajax_nonce or security automatically)
    check_ajax_referer('lws_search'); // dies with -1 if invalid

    $keyword = sanitize_text_field($_POST['keyword'] ?? '');
    $options = get_option('lime_wp_search_options');

    if (empty($keyword) || empty($options['enabled'])) {
        wp_die();
    }

    $args = [
        's' => $keyword,
        'post_type' => $options['post_types'] ?? ['post', 'page'],
        'posts_per_page' => 5,
        'post_status' => 'publish',
    ];

    $meta_keys = !empty($options['meta_keys']) ? array_map('trim', explode(',', $options['meta_keys'])) : [];
    if ($meta_keys) {
        $meta_query = ['relation' => 'OR'];
        foreach ($meta_keys as $key) {
            $meta_query[] = [
                'key' => $key,
                'value' => $keyword,
                'compare' => 'LIKE',
            ];
        }
        $args['meta_query'] = $meta_query;
    }

    $query = new WP_Query($args);

    // Collect normal search IDs (title/content/meta)
    $normal_ids = $query->posts ? wp_list_pluck($query->posts, 'ID') : [];

    // Collect SKU matched product IDs
    $sku_ids = lws_get_product_ids_by_sku_like($keyword);

    // Merge (SKU-first), then cap to posts_per_page
    $limit = isset($args['posts_per_page']) ? (int)$args['posts_per_page'] : 5;
    $final_ids = array_values(array_unique(array_merge($sku_ids, $normal_ids)));
    $final_ids = array_slice($final_ids, 0, max(1, $limit));

    if (!empty($final_ids)) {
        echo '<ul class="lime-wp-search-list">';
        foreach ($final_ids as $pid) {
            $permalink  = get_permalink($pid);
            $title      = get_the_title($pid);
            $thumb_html = get_the_post_thumbnail(
                $pid,
                'thumbnail',
                [
                    'class'    => 'lws-thumb-img',
                    'loading'  => 'lazy',
                    'decoding' => 'async',
                    'alt'      => $title,
                ]
            );

            // Secondary line: product categories (clickable) or post type label
            $secondary_html = '';
            if (get_post_type($pid) === 'product') {
                $terms = get_the_terms($pid, 'product_cat');
                if (!is_wp_error($terms) && !empty($terms)) {
                    $links = [];
                    foreach ($terms as $term) {
                        $url = get_term_link($term);
                        if (!is_wp_error($url)) {
                            $links[] = '<a href="' . esc_url($url) . '" class="lws-cat-link">' . esc_html($term->name) . '</a>';
                        }
                    }
                    $secondary_html = implode(', ', $links);
                }
            } else {
                $pt_obj = get_post_type_object(get_post_type($pid));
                if ($pt_obj && !empty($pt_obj->labels->singular_name)) {
                    $secondary_html = esc_html($pt_obj->labels->singular_name);
                }
            }

            echo '<li class="lime-wp-search-item">';
            echo   '<a href="' . esc_url($permalink) . '">';
            echo     '<span class="lws-thumb">';
            echo        $thumb_html ? $thumb_html : '<span class="lws-thumb--placeholder" aria-hidden="true"></span>';
            echo     '</span>';
            echo   '</a>';

            echo   '<div class="lws-meta">';
            echo     '<a href="' . esc_url($permalink) . '"><span class="lws-title">' . esc_html($title) . '</span></a>';
            if ($secondary_html !== '') {
                echo   '<div class="lws-cats">' . $secondary_html . '</div>';
            }
            echo   '</div>';

            echo '</li>';
        }
        echo '</ul>';
    } else {
        echo '<p>No results found.</p>';
    }

    // Always show the "View all" link
    $search_url = add_query_arg('s', $keyword, home_url('/'));
    echo '<div class="lws-view-all-wrap">
        <a class="lws-view-all" href="' . esc_url($search_url) . '" aria-label="' . esc_attr(sprintf(__('View all results for “%s”', 'lime-wp-search'), $keyword)) . '">' . esc_html__('View all results', 'lime-wp-search') . '</a>
      </div>';

    wp_reset_postdata();
    wp_die();
}
