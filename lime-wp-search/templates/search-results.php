<?php

/**
 * Grouped-by-category search results (grid)
 */
if (!defined('ABSPATH')) exit;

get_header();

$groups = [];   // term_id => ['term' => WP_Term, 'items' => [post_id,...]]
$others = [];   // non-products (or products without categories)

// Collect results first
if (have_posts()) {
    while (have_posts()) {
        the_post();
        $post_id = get_the_ID();
        if (get_post_type() === 'product') {
            $terms = get_the_terms($post_id, 'product_cat');
            if (!is_wp_error($terms) && !empty($terms)) {
                // Use the first category (keeps one row per product)
                $primary = array_shift($terms);
                $tid = $primary->term_id;
                if (!isset($groups[$tid])) {
                    $groups[$tid] = ['term' => $primary, 'items' => []];
                }
                $groups[$tid]['items'][] = $post_id;
            } else {
                $others[] = $post_id;
            }
        } else {
            $others[] = $post_id;
        }
    }
    wp_reset_postdata();
}
?>
<style>
    #primary {
        width: calc(100%) !important;
    }

    #header-wrapper {
        margin-bottom: 60px !important;
    }
</style>


<main id="primary" class="site-main lime-wp-search-archive">
    <?php
    // Helper to render a grid for a list of post IDs
    function lws_render_card_grid($post_ids, $show_type_fallback = false)
    {
        if (empty($post_ids)) return;
        echo '<ul class="lws-grid">';
        foreach ($post_ids as $pid) {
            $permalink  = get_permalink($pid);
            $title      = get_the_title($pid);

            // Thumb: Woo product uses Woo size, else 'thumbnail'
            $thumb_size = (get_post_type($pid) === 'product' && has_post_thumbnail($pid)) ? 'woocommerce_thumbnail' : 'thumbnail';
            $thumb_html = get_the_post_thumbnail($pid, $thumb_size, [
                'class'    => 'lws-card__img',
                'loading'  => 'lazy',
                'decoding' => 'async',
                'alt'      => $title,
            ]);

            // Optional price for products (if WooCommerce exists)
            $price_html = '';
            if (function_exists('wc_get_product') && get_post_type($pid) === 'product') {
                $product = wc_get_product($pid);
                if ($product && $product->get_price_html()) {
                    $price_html = $product->get_price_html();
                }
            }

            // Fallback label for non-products (optional)
            $type_label = '';
            if ($show_type_fallback && get_post_type($pid) !== 'product') {
                $pt = get_post_type_object(get_post_type($pid));
                $type_label = $pt && !empty($pt->labels->singular_name) ? $pt->labels->singular_name : '';
            }

            echo '<li class="lws-card">';
            echo   '<a class="lws-card__link" href="' . esc_url($permalink) . '">';
            echo     '<span class="lws-card__thumb">' . ($thumb_html ?: '<span class="lws-card__placeholder" aria-hidden="true"></span>') . '</span>';
            echo     '<span class="lws-card__body">';
            echo       '<h5 class="lws-card__title">' . esc_html($title) . '</span>';
            if ($price_html) {
                echo   '<span class="lws-card__price">' . $price_html . '</span>';
            } elseif ($type_label) {
                echo   '<span class="lws-card__meta">' . esc_html($type_label) . '</span>';
            }
            echo     '</span>';
            echo   '</a>';
            echo '</li>';
        }
        echo '</ul>';
    }

    // Sort groups alphabetically, but move "Accessories" to the end
    $__is_accessories = function ($term) {
        if (! $term instanceof WP_Term) return false;
        $slug = strtolower($term->slug);
        $name = strtolower($term->name);
        return ($slug === 'accessories' || $name === 'accessories');
    };

    if (!empty($groups)) {
        uasort($groups, function ($a, $b) use ($__is_accessories) {
            $aAcc = $__is_accessories($a['term']);
            $bAcc = $__is_accessories($b['term']);

            // If one is Accessories, it goes last
            if ($aAcc && !$bAcc) return 1;
            if ($bAcc && !$aAcc) return -1;

            // Otherwise alphabetical by name
            return strcasecmp($a['term']->name, $b['term']->name);
        });

        foreach ($groups as $g) {
            $term = $g['term'];
            $term_link = get_term_link($term);
    ?>
            <section class="lws-section">
                <div class="lws-section__head">
                    <h2 class="lws-section__title">
                        <?php echo esc_html($term->name); ?>
                    </h2>
                    <?php if (!is_wp_error($term_link)) : ?>
                        <a class="lws-section__more" href="<?php echo esc_url($term_link); ?>">
                            <?php esc_html_e('View all', 'lime-wp-search'); ?>
                        </a>
                    <?php endif; ?>
                </div>
                <hr />
                <?php lws_render_card_grid($g['items']); ?>
            </section>
        <?php
        }
    }

    // Non-product results
    if (!empty($others)) {
        ?>
        <section class="lws-section">
            <div class="lws-section__head">
                <h2 class="lws-section__title"><?php esc_html_e('Other results', 'lime-wp-search'); ?></h2>
            </div>
            <?php lws_render_card_grid($others, true); ?>
        </section>
    <?php
    }

    if (empty($groups) && empty($others)) {
        echo '<p>' . esc_html__('No results found.', 'lime-wp-search') . '</p>';
    }
    ?>
</main>

<?php get_footer();
