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
    $options = get_option('wcp_settings', []);
    if (!is_array($options)) {
        $options = [];
    }

    wp_enqueue_style('wcp-compare-style', plugin_dir_url(__FILE__) . 'compare.css');
    wp_enqueue_script('wcp-compare-script', plugin_dir_url(__FILE__) . 'compare.js', ['jquery'], null, true);
    wp_localize_script('wcp-compare-script', 'wcp_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'card_class' => isset($options['product_card_class']) ? sanitize_text_field($options['product_card_class']) : '',
    ]);
});

// Add compare button under shop loop
add_action('woocommerce_after_shop_loop_item', 'wcp_add_buttons_wrapper', 20);
function wcp_add_buttons_wrapper()
{
    if (is_product()) return; // Skip single product pages

    global $product;
    $product_url = get_permalink($product->get_id());

    $options = get_option('wcp_settings', []);
    if (!is_array($options)) {
        $options = [];
    }

    if (!empty($options['enable_compare'])) {
        $label = $options['compare_button_label'] ?? '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" stroke-width="0.00024000000000000003"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"><path d="M1,8A1,1,0,0,1,2,7H9.586L7.293,4.707A1,1,0,1,1,8.707,3.293l4,4a1,1,0,0,1,0,1.414l-4,4a1,1,0,1,1-1.414-1.414L9.586,9H2A1,1,0,0,1,1,8Zm21,7H14.414l2.293-2.293a1,1,0,0,0-1.414-1.414l-4,4a1,1,0,0,0,0,1.414l4,4a1,1,0,0,0,1.414-1.414L14.414,17H22a1,1,0,0,0,0-2Z"></path></g></svg>';
        echo '<div class="wcp-button-group">';
        echo '<a href="' . esc_url($product_url) . '" class="wcp-view-product">View</a>';
        echo '<button class="wcp-compare-button" data-product-id="' . esc_attr($product->get_id()) . '">' . wp_kses_post($label) . '</button>';
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

add_shortcode('compare_icon', function () {
    return '<span class="wcp-shortcode-icon">
    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" stroke-width="0.00024000000000000003"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"><path d="M1,8A1,1,0,0,1,2,7H9.586L7.293,4.707A1,1,0,1,1,8.707,3.293l4,4a1,1,0,0,1,0,1.414l-4,4a1,1,0,1,1-1.414-1.414L9.586,9H2A1,1,0,0,1,1,8Zm21,7H14.414l2.293-2.293a1,1,0,0,0-1.414-1.414l-4,4a1,1,0,0,0,0,1.414l4,4a1,1,0,0,0,1.414-1.414L14.414,17H22a1,1,0,0,0,0-2Z"></path></g></svg>    
    <span class="wcp-count">0</span>
    </span>';
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

function get_store_logo_html($store_name, $url = '', $sku = '')
{
    $logos = [
        'best_buy'   => '/wp-content/plugins/wc-product-compare/icons/compare-icons/best_buy-ico.svg',
        'amazon'    => '/wp-content/plugins/wc-product-compare/icons/compare-icons/amazon-ico.svg',
        'the_home_depot' => '/wp-content/plugins/wc-product-compare/icons/compare-icons/the_home_depot-ico.svg',
        'rona'      => '/wp-content/plugins/wc-product-compare/icons/compare-icons/rona-ico.svg',
        'wayfair'       => '/wp-content/plugins/wc-product-compare/icons/compare-icons/wayfair-ico.svg',
        'walmart'       => '/wp-content/plugins/wc-product-compare/icons/compare-icons/walmart-ico.svg',
    ];

    $key = strtolower(sanitize_title($store_name));
    $attrs = sprintf(
        'class="affiliate-button" data-store="%s" data-sku="%s"',
        esc_attr($key),
        esc_attr($sku)
    );

    if (isset($logos[$key])) {
        $img = '<img class="affiliate_img" src="' . esc_url($logos[$key]) . '" alt="' . esc_attr($store_name) . '" style="max-height: 30px;">';
        return '<a href="' . esc_url($url) . '" target="_blank" rel="nofollow" ' . $attrs . '>' . $img . '</a>';
    }

    return '<a href="' . esc_url($url) . '" target="_blank" rel="nofollow" ' . $attrs . '>' . esc_html($store_name) . '</a>';
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
        'Image' => function ($p) {
            $product_link = get_permalink($p->get_id());
            return '<a href="' . esc_url($product_link) . '" target="_blank" rel="noopener noreferrer">'
                . $p->get_image()
                . '</a>';
        },
        'Price' => fn($p) => $p->get_price_html(),
        'Category' => fn($p) => wc_get_product_category_list($p->get_id()),
        'SKU' => fn($p) => $p->get_sku(),
        'Available in' => function ($p) {
            $output = '';
            $stores = ['amazon', 'best_buy', 'rona', 'the_home_depot', 'wayfair', 'walmart'];
            $sku    = $p->get_sku();

            foreach ($stores as $store) {
                $url = get_field($store, $p->get_id());
                if (!empty($url)) {
                    $output .= get_store_logo_html($store, $url, $sku) . ' ';
                }
            }

            return $output ?: '-';


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
