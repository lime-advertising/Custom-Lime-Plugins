<?php

/**
 * Plugin Name: Kucht Affiliate Archive
 * Description: Adds /affiliate/{store} archives listing products that have that store’s ACF link filled.
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) exit;

register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();
});


class Kucht_Affiliate_Archive
{
    const QV = 'affiliate_store';

    // Map slugs to ACF meta keys + labels
    private static $stores = [
        'amazon'      => ['meta' => 'amazon',      'label' => 'Amazon'],
        'best-buy'    => ['meta' => 'best_buy',    'label' => 'Best Buy'],
        'home-depot'  => ['meta' => 'the_home_depot',  'label' => 'The Home Depot'],
        'rona'        => ['meta' => 'rona',        'label' => 'RONA'],
        'wayfair'     => ['meta' => 'wayfair',     'label' => 'Wayfair'],
        'walmart'     => ['meta' => 'walmart',     'label' => 'Walmart'],
    ];

    public function __construct()
    {
        add_action('init', [$this, 'add_rewrite']);
        add_filter('query_vars', [$this, 'add_query_var']);
        add_action('parse_query', [$this, 'mark_main_query']);
        add_action('template_redirect', [$this, 'render_if_affiliate']);
        add_action('wp_enqueue_scripts', [$this, 'styles']);
        add_shortcode('affiliate_products', [$this, 'shortcode']);
        register_activation_hook(__FILE__, [$this, 'activate']);
    }

    public function mark_main_query($q)
    {
        if (is_admin() || !$q->is_main_query()) return;
        if ($q->get(self::QV)) {
            // prevent WP from treating it like the blog and from 404’ing on higher pages
            $q->is_home    = false;
            $q->is_archive = true;
            $q->is_404     = false;
        }
    }

    public function render_if_affiliate()
    {
        $slug = get_query_var(self::QV);
        if (!$slug) return;

        // Make the main query look like a valid archive, not the blog, not a 404
        global $wp_query;
        $wp_query->is_home    = false;
        $wp_query->is_archive = true;
        $wp_query->is_404     = false;
        // (optional) if you want: $wp_query->is_post_type_archive = true;

        status_header(200); // ensure 200 OK, not 404

        get_header();
        echo '<main class="site-main">';
        echo $this->render_archive(sanitize_title($slug));
        echo '</main>';
        get_footer();
        exit;
    }



    public function activate()
    {
        $this->add_rewrite();
        flush_rewrite_rules();
    }

    public function add_rewrite()
    {
        add_rewrite_tag('%' . self::QV . '%', '([^&]+)');
        // /affiliate/{store}
        add_rewrite_rule('^affiliate/([^/]+)/?$', 'index.php?' . self::QV . '=$matches[1]', 'top');
        // /affiliate/{store}/page/2
        add_rewrite_rule('^affiliate/([^/]+)/page/([0-9]+)/?$', 'index.php?' . self::QV . '=$matches[1]&paged=$matches[2]', 'top');
    }


    public function add_query_var($vars)
    {
        $vars[] = self::QV;
        return $vars;
    }

    public function styles()
    {
        // minimal styles
        $css = "
        .affiliate-archive {max-width:1720px;margin:0px auto;padding:0 40px;}
        .affiliate-archive h1 {margin:0 0 24px;font-size:28px;line-height:1.2;}
        .affiliate-grid {display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:20px;}
        .affiliate-card {overflow:hidden;display:flex;flex-direction:column;align-items: center;}
        .affiliate-card .thumb {aspect-ratio:1/1;display:block;background:#fafafa;overflow:hidden;}
        .affiliate-card .thumb img {width:100%;height:100%;object-fit:cover;display:block;}
        .affiliate-card .body {text-align: center;padding:14px 14px 0;}
        .affiliate-card h3 {font-size:16px;margin:0 0 6px;line-height:1.35;}
        .affiliate-card .sku {color:#666;font-size:13px;margin:0 0 12px;}
        .affiliate-card .actions {padding:14px;}
        .affiliate-card .btn { width: 100%; display: inline-block; text-align: center; padding: 14px; border-radius: 100px; text-decoration: none; background: var(--wdtPrimaryColor); color: #fff; }
        .affiliate-pagination {margin:24px 0;display:flex;gap:8px;flex-wrap:wrap;}
        .affiliate-pagination a, .affiliate-pagination span {padding:8px 12px;border:1px solid #ddd;border-radius:8px;text-decoration:none}
        .affiliate-empty {padding:24px;border:1px dashed #ddd;border-radius:12px;background:#fcfcfc;}
        ";
        wp_register_style('kucht-aff-archive', false);
        wp_enqueue_style('kucht-aff-archive');
        wp_add_inline_style('kucht-aff-archive', $css);
    }

    private function get_store($slug)
    {
        return self::$stores[$slug] ?? null;
    }

    private function render_archive($slug)
    {
        $store = $this->get_store($slug);
        if (!$store) return $this->render_404();

        $meta_key = $store['meta'];
        $label    = $store['label'];

        $paged = max(1, get_query_var('paged') ?: 1);

        $q = new WP_Query([
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 24,
            'paged'          => $paged,
            'meta_query'     => [
                'relation' => 'AND',
                ['key' => $meta_key, 'compare' => 'EXISTS'],
                ['key' => $meta_key, 'value' => '', 'compare' => '!='],
            ],
        ]);

        ob_start(); ?>
        <div class="affiliate-archive">
            <h1><?php echo esc_html("Products available on {$label}"); ?></h1>

            <?php if ($q->have_posts()): ?>
                <div class="affiliate-grid">
                    <?php while ($q->have_posts()): $q->the_post();
                        $product = function_exists('wc_get_product') ? wc_get_product(get_the_ID()) : null;

                        // $sku     = $product ? $product->get_sku() : '';
                        $sku = '';
                        if ($product) {
                            $sku = $product->get_sku();
                            if (!$sku) {
                                $sku = 'ID-' . $product->get_id();
                            } // fallback to ensure not blank
                        }
                        $store_key = self::$stores[$slug]['meta']; // e.g. best_buy

                        $url     = get_post_meta(get_the_ID(), $meta_key, true);
                        $placeholder = function_exists('wc_placeholder_img_src') ? wc_placeholder_img_src() : includes_url('images/media/default.png');
                    ?>
                        <article class="affiliate-card">
                            <a class="thumb" href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr(get_the_title()); ?>">
                                <?php if (has_post_thumbnail()) {
                                    the_post_thumbnail('medium_large');
                                } else {
                                    echo '<img alt="" src="' . esc_url($placeholder) . '">';
                                } ?>
                            </a>
                            <div class="body">
                                <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                            </div>
                            <div class="actions">
                                <?php if ($url): ?>
                                    <a data-store="<?php echo esc_attr($store_key); ?>"
                                        data-sku="<?php echo esc_attr($sku); ?>"
                                        class="affiliate-button"
                                        href="<?php echo esc_url($url); ?>"
                                        target="_blank" rel="nofollow sponsored noopener">
                                        <?php echo esc_html("View on {$label}"); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endwhile;
                    wp_reset_postdata(); ?>
                </div>

                <?php
                // Pagination
                $base = user_trailingslashit(home_url("affiliate/$slug/page/%#%"));
                $links = paginate_links([
                    'base'      => $base,
                    'format'    => '',
                    'current'   => $paged,
                    'total'     => $q->max_num_pages,
                    'type'      => 'array',
                    'prev_text' => '« Prev',
                    'next_text' => 'Next »',
                ]);



                if ($links): ?>
                    <nav class="affiliate-pagination">
                        <?php foreach ($links as $l) echo $l; ?>
                    </nav>
                <?php endif; ?>

            <?php else: ?>
                <div class="affiliate-empty">
                    <strong>No products found.</strong> Try a different retailer.
                </div>
            <?php endif; ?>
        </div>
    <?php
        return ob_get_clean();
    }

    private function render_404()
    {
        status_header(404);
        ob_start(); ?>
        <div class="affiliate-archive">
            <h1>Retailer not found</h1>
            <p>Try one of: <?php echo esc_html(implode(', ', array_keys(self::$stores))); ?>.</p>
        </div>
<?php
        return ob_get_clean();
    }

    public function maybe_template($template)
    {
        $slug = get_query_var(self::QV);
        if (!$slug) return $template;

        // Render the archive directly, outside of the Loop.
        add_action('template_redirect', function () use ($slug) {
            get_header();
            echo '<main class="site-main">';
            echo $this->render_archive(sanitize_title($slug));
            echo '</main>';
            get_footer();
            exit; // prevent WP from continuing to load another template
        });

        return $template; // we’ll exit in template_redirect
    }

    // Shortcode: [affiliate_products store="amazon"]
    public function shortcode($atts)
    {
        $atts = shortcode_atts(['store' => ''], $atts, 'affiliate_products');
        $slug = sanitize_title($atts['store']);
        if (!$slug) return '';
        return $this->render_archive($slug);
    }
}
new Kucht_Affiliate_Archive();
