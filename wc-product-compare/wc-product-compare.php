<?php

/**
 * Plugin Name: WooCommerce Product Compare (Simple)
 * Description: Adds a compare button to WooCommerce products to show a modal comparison table (up to 4 products).
 * Version: 1.0
 * Author: Lime Advertising
 */

if (!defined('ABSPATH')) exit;

// Enqueue scripts and styles
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('wcp-compare-style', plugin_dir_url(__FILE__) . 'compare.css');
    wp_enqueue_script('wcp-compare-script', plugin_dir_url(__FILE__) . 'compare.js', ['jquery'], null, true);
    wp_localize_script('wcp-compare-script', 'wcp_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
    ]);
});

// Add compare button under shop loop
add_action('woocommerce_after_shop_loop_item', 'wcp_add_buttons_wrapper', 20);
function wcp_add_buttons_wrapper()
{
    if (is_product()) return; // Skip single product pages

    global $product;
    $product_url = get_permalink($product->get_id());

    $options = get_option('wcp_settings');
    if (!empty($options['enable_compare'])) {
        $label = $options['compare_button_label'] ?? 'Compare';
        echo '<div class="wcp-button-group">';
        echo '<a href="' . esc_url($product_url) . '" class="wcp-view-product">View</a>';
        echo '<button class="wcp-compare-button" data-product-id="' . esc_attr($product->get_id()) . '">' . esc_html($label) . '</button>';
        echo '</div>';
    }
}


// Shortcode for compare button
add_shortcode('compare_button', function () {
    global $product;
    if (!$product) return '';
    $id = $product->get_id();
    return '<button class="wcp-compare-button" data-product-id="' . esc_attr($id) . '">Compare</button>';
});

// Output compare modal
add_action('wp_footer', 'wcp_render_compare_modal');
function wcp_render_compare_modal()
{
?>
    <div id="wcp-compare-modal" style="display:none;">
        <div class="wcp-overlay"></div>
        <div class="wcp-content">
            <button class="wcp-close-compare">×</button>
            <div id="wcp-compare-table-container"></div>
        </div>
    </div>
<?php
}

// AJAX handler for compare data
add_action('wp_ajax_get_compare_data', 'wcp_get_compare_data');
add_action('wp_ajax_nopriv_get_compare_data', 'wcp_get_compare_data');

function get_store_logo_html($store_name, $url = '')
{
    $logos = [
        'bestbuy'   => 'https://kucht.ca/wp-content/uploads/2025/06/Bestbuy.svg',
        'amazon'    => 'https://kucht.ca/wp-content/uploads/2025/06/Amazon.svg',
        'homedepot' => 'https://kucht.ca/wp-content/uploads/2025/06/HomedepotLogo.svg',
        'rona'      => 'https://kucht.ca/wp-content/uploads/2025/06/Ronalogo-1.svg',
        'hod'       => 'https://kucht.ca/wp-content/uploads/2025/06/HODlogo.svg',
    ];

    $key = strtolower(sanitize_title($store_name));

    if (isset($logos[$key])) {
        $img = '<img class="affiliate_img" src="' . esc_url($logos[$key]) . '" alt="' . esc_attr($store_name) . '" style="max-height: 30px;">';
        return '<a href="' . esc_url($url) . '" target="_blank" rel="nofollow">' . $img . '</a>';
    }

    return '<a href="' . esc_url($url) . '" target="_blank" rel="nofollow">' . esc_html($store_name) . '</a>';
}

function wcp_get_compare_data()
{
    $product_ids = isset($_POST['product_ids']) ? array_map('intval', $_POST['product_ids']) : [];

    if (count($product_ids) < 1) {
        wp_send_json_error('Select at least one product.');
    }

    ob_start();

    echo '<div class="wcp-compare-header">';
    echo '<h3>Compare Products</h3>';
    echo '<div class="wcp-swipe-hint"><span>Swipe right to view more →</span></div>';
    echo '<button id="wcp-clear-all" class="wcp-clear-all">Clear All</button>';
    echo '</div>';
    echo '<div class="wcp-table-scroll"><table class="wcp-compare-table"><thead><tr><th>Feature</th>';
    foreach ($product_ids as $id) {
        $product_link = get_permalink($id);
        $product = wc_get_product($id);
        echo '<th><a href="' . esc_url($product_link) . '" target="_blank" rel="noopener noreferrer">' . esc_html($product->get_name()) . '</a><br><button class="wcp-remove-item" data-remove-id="' . esc_attr($id) . '">Remove</button></th>';
    }
    echo '</tr></thead><tbody>';

    // Define comparison rows
    $rows = [
        'Image' => fn($p) => $p->get_image(),
        'Price' => fn($p) => $p->get_price_html(),
        'Category' => fn($p) => wc_get_product_category_list($p->get_id()),
        'SKU' => fn($p) => $p->get_sku(),
        'Available in' => function ($p) {
            $affiliates = get_post_meta($p->get_id(), '_additional_affiliate_links', true);
            if (!is_array($affiliates) || empty($affiliates)) return '-';

            $output = '';
            foreach ($affiliates as $link) {
                if (!empty($link['name']) && !empty($link['url'])) {
                    $output .= get_store_logo_html($link['name'], $link['url']) . ' ';
                }
            }

            return $output ?: '-';
        }
    ];

    // Output one row per feature
    foreach ($rows as $label => $callback) {
        echo '<tr>';
        echo '<td class="wcp-heading-cell"><strong>' . esc_html($label) . '</strong></td>';
        foreach ($product_ids as $id) {
            $product = wc_get_product($id);
            echo '<td>' . $callback($product) . '</td>';
        }
        echo '</tr>';
    }


    // Collect all attribute names across products
    $all_attributes = [];

    foreach ($product_ids as $id) {
        $product = wc_get_product($id);
        if (!$product) continue;

        foreach ($product->get_attributes() as $attribute) {
            // if ($attribute->get_variation()) continue; // Skip variation-only attributes if needed
            $name = $attribute->get_name();
            $label = wc_attribute_label($name);
            $all_attributes[$name] = $label;
        }
    }


    // Now display each attribute row
    foreach ($all_attributes as $attribute_slug => $attribute_label) {
        echo '<tr>';
        echo '<td class="wcp-heading-cell"><strong>' . esc_html($attribute_label) . '</strong></td>';

        foreach ($product_ids as $id) {
            $product = wc_get_product($id);
            $value = '-';

            if ($product && $product->has_attributes()) {
                $attributes = $product->get_attributes();
                if (isset($attributes[$attribute_slug])) {
                    $attr = $attributes[$attribute_slug];

                    if ($attr->is_taxonomy()) {
                        $terms = wc_get_product_terms($id, $attribute_slug, ['fields' => 'names']);
                        if (!empty($terms)) {
                            $value = implode(', ', array_map('ucwords', $terms));
                        }
                    } else {
                        $value = implode(', ', array_map('ucwords', wc_get_product_attribute_list($id, $attribute_slug)));
                    }
                }
            }

            echo '<td>' . $value . '</td>';
        }

        echo '</tr>';
    }



    echo '</tbody></table></div>';


    wp_send_json_success(ob_get_clean());
}


// Load admin settings page
if (is_admin()) {
    require_once plugin_dir_path(__FILE__) . 'admin-settings.php';
}
