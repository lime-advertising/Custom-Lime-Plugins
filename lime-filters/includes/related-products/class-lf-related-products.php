<?php
if (! defined('ABSPATH')) {
    exit;
}

class LF_Related_Products
{
    const SHORTCODE = 'lf_related_products';

    public static function init()
    {
        add_shortcode(self::SHORTCODE, [__CLASS__, 'shortcode']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'register_assets']);
    }

    public static function register_assets()
    {
        wp_register_style(
            'lf-related-products',
            LF_PLUGIN_URL . 'includes/related-products/related-products.css',
            [],
            LF_VERSION
        );
    }

    public static function shortcode($atts = [])
    {
        if (!function_exists('wc_get_product')) {
            return '';
        }

        $atts = shortcode_atts([
            'product'        => '',
            'limit'          => 4,
            'columns'        => 4,
            'columns_tablet' => 2,
            'columns_mobile' => 1,
            'class'          => '',
            'orderby'        => 'rand',
            'order'          => 'desc',
        ], $atts, self::SHORTCODE);

        $limit = max(1, (int) $atts['limit']);
        $product = null;

        if ($atts['product'] !== '') {
            $product = wc_get_product(absint($atts['product']));
        } elseif (isset($GLOBALS['product']) && $GLOBALS['product'] instanceof WC_Product) {
            $product = $GLOBALS['product'];
        }

        if (!$product instanceof WC_Product) {
            return '';
        }

        $related_ids = wc_get_related_products($product->get_id(), $limit);
        if (empty($related_ids)) {
            return '';
        }

        $columns        = max(1, (int) $atts['columns']);
        $columns_tablet = max(1, (int) $atts['columns_tablet']);
        $columns_mobile = max(1, (int) $atts['columns_mobile']);

        $wrapper_classes = ['lf-related-products'];
        if ($atts['class']) {
            $wrapper_classes[] = sanitize_html_class($atts['class']);
        }

        if (wp_style_is('lime-filters', 'registered') || wp_style_is('lime-filters', 'enqueued')) {
            wp_enqueue_style('lime-filters');
        } else {
            wp_enqueue_style(
                'lime-filters',
                LF_PLUGIN_URL . 'includes/assets/css/lime-filters.css',
                [],
                LF_VERSION
            );
        }

        wp_enqueue_style('lf-related-products');

        ob_start();
?>
        <div class="<?php echo esc_attr(implode(' ', $wrapper_classes)); ?>"
            style="--lf-related-columns:<?php echo esc_attr($columns); ?>;
                    --lf-related-columns-tablet:<?php echo esc_attr($columns_tablet); ?>;
                    --lf-related-columns-mobile:<?php echo esc_attr($columns_mobile); ?>;">
            <?php foreach ($related_ids as $related_id): ?>
                <?php
                $related_product = wc_get_product($related_id);
                if (!$related_product) {
                    continue;
                }
                $permalink = get_permalink($related_id);
                $title     = $related_product->get_name();
                $thumbnail = $related_product->get_image('woocommerce_thumbnail');
                if (!$thumbnail || strpos($thumbnail, 'woocommerce-placeholder') !== false) {
                    $thumbnail = '<img src="' . esc_url(LF_Helpers::placeholder_image_url()) . '" alt="" class="attachment-woocommerce_thumbnail size-woocommerce_thumbnail lf-placeholder" />';
                }
                if ($thumbnail && class_exists('LF_Product_Background') && method_exists('LF_Product_Background', 'apply_background_wrapper')) {
                    $thumbnail = LF_Product_Background::apply_background_wrapper($thumbnail);
                }
                $price     = LF_Helpers::product_price_columns($related_product);
                $sku       = $related_product->get_sku();
                $categories = self::category_links($related_id);

                $previous_product = isset($GLOBALS['product']) ? $GLOBALS['product'] : null;
                $GLOBALS['product'] = $related_product;
                ob_start();
                woocommerce_template_loop_add_to_cart();
                $add_to_cart = ob_get_clean();
                $GLOBALS['product'] = $previous_product;
                $compare_btn = self::get_compare_button($related_product);
                $is_compare_allowed = $compare_btn !== '';
                ?>
                <article class="lf-product">
                    <a class="lf-product__thumb" href="<?php echo esc_url($permalink); ?>">
                        <?php echo $thumbnail; ?>
                        <?php if ($sku) : ?>
                            <div class="lf-product__sku"><?php echo esc_html($sku); ?></div>
                        <?php endif; ?>
                    </a>
                    <div class="lf-product__body">
                        <?php if ($categories) : ?>
                            <div class="lf-product__cats"><?php echo $categories; ?></div>
                        <?php endif; ?>
                        <h3 class="lf-product__title">
                            <a href="<?php echo esc_url($permalink); ?>"><?php echo esc_html($title); ?></a>
                        </h3>
                        <?php if (!empty($price)) : ?>
                            <div class="lf-product__price lf-product__price--columns"><?php echo $price; ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="lf-product__actions">
                        <?php if (!$is_compare_allowed) : ?>
                            <?php echo $add_to_cart; ?>
                        <?php endif; ?>
                        <?php if ($compare_btn) : ?>
                            <?php echo $compare_btn; ?>
                        <?php endif; ?>
                        <a class="lf-button lf-button--secondary" href="<?php echo esc_url($permalink); ?>"><?php esc_html_e('View Product', 'lime-filters'); ?></a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
<?php
        return ob_get_clean();
    }

    protected static function category_links($product_id)
    {
        $terms = get_the_terms($product_id, 'product_cat');
        if (!$terms || is_wp_error($terms)) {
            return '';
        }

        $links = [];
        foreach ($terms as $term) {
            $url = get_term_link($term);
            if (is_wp_error($url)) {
                continue;
            }
            $links[] = sprintf('<a href="%s">%s</a>', esc_url($url), esc_html($term->name));
        }

        return implode(', ', $links);
    }

    protected static function get_compare_button($product)
    {
        if (!$product instanceof WC_Product) {
            return '';
        }

        $product_id = $product->get_id();
        if (!$product_id) {
            return '';
        }

        $blocked = ['accessories', 'parts'];
        $slugs = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'slugs']);
        if (!is_wp_error($slugs) && array_intersect($blocked, (array) $slugs)) {
            return '';
        }

        $label = apply_filters('lime_filters_compare_button_label', __('Compare', 'lime-filters'), $product);

        return sprintf(
            '<button type="button" class="lf-button lf-button--ghost wcp-compare-button" data-product-id="%d">%s</button>',
            (int) $product_id,
            esc_html($label)
        );
    }
}
