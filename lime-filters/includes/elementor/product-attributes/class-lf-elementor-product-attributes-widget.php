<?php
if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;

if (class_exists('LF_Elementor_Product_Attributes_Widget')) {
    return;
}

if (!class_exists('\Elementor\Widget_Base')) {
    return;
}

class LF_Elementor_Product_Attributes_Widget extends \Elementor\Widget_Base {
    public function get_name() {
        return 'lf-product-attributes';
    }

    public function get_title() {
        return __('LF Product Attributes', 'lime-filters');
    }

    public function get_icon() {
        return 'eicon-product-info';
    }

    public function get_categories() {
        return ['general'];
    }

    protected function register_controls() {
        $this->start_controls_section('section_content', [
            'label' => __('Content', 'lime-filters'),
        ]);

        $this->add_control('show_heading', [
            'label' => __('Show Heading', 'lime-filters'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('heading_text', [
            'label' => __('Heading Text', 'lime-filters'),
            'type' => Controls_Manager::TEXT,
            'default' => __('Product Details', 'lime-filters'),
            'condition' => [
                'show_heading' => 'yes',
            ],
        ]);

        $this->add_control('attribute_layout', [
            'label' => __('Attribute Layout', 'lime-filters'),
            'type' => Controls_Manager::SELECT,
            'default' => 'list',
            'options' => [
                'list' => __('List', 'lime-filters'),
                'grid' => __('Grid', 'lime-filters'),
            ],
        ]);

        $this->add_control('swatch_layout', [
            'label' => __('Swatch Layout', 'lime-filters'),
            'type' => Controls_Manager::SELECT,
            'default' => 'row',
            'options' => [
                'row' => __('Row', 'lime-filters'),
                'grid' => __('Grid', 'lime-filters'),
            ],
        ]);

        $this->end_controls_section();

        $this->start_controls_section('section_style_pills', [
            'label' => __('Pills', 'lime-filters'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('pill_bg_color', [
            'label' => __('Background Color', 'lime-filters'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .lf-pill' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('pill_text_color', [
            'label' => __('Text Color', 'lime-filters'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .lf-pill' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'pill_border',
                'selector' => '{{WRAPPER}} .lf-pill',
            ]
        );

        $this->add_responsive_control('pill_border_radius', [
            'label' => __('Border Radius', 'lime-filters'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', '%'],
            'selectors' => [
                '{{WRAPPER}} .lf-pill' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('pill_padding', [
            'label' => __('Padding', 'lime-filters'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', '%'],
            'selectors' => [
                '{{WRAPPER}} .lf-pill' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('pill_gap', [
            'label' => __('Gap', 'lime-filters'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => [
                'px' => [
                    'min' => 0,
                    'max' => 60,
                ],
            ],
            'selectors' => [
                '{{WRAPPER}} .lf-attribute-group__pills' => 'gap: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();

        $this->start_controls_section('section_style_swatches', [
            'label' => __('Swatches', 'lime-filters'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('swatch_bg_color', [
            'label' => __('Background Color', 'lime-filters'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .lf-swatch' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('swatch_text_color', [
            'label' => __('Text Color', 'lime-filters'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .lf-swatch' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'swatch_border',
                'selector' => '{{WRAPPER}} .lf-swatch',
            ]
        );

        $this->add_responsive_control('swatch_border_radius', [
            'label' => __('Border Radius', 'lime-filters'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', '%'],
            'selectors' => [
                '{{WRAPPER}} .lf-swatch' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('swatch_padding', [
            'label' => __('Padding', 'lime-filters'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em'],
            'selectors' => [
                '{{WRAPPER}} .lf-swatch' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('swatch_gap', [
            'label' => __('Gap', 'lime-filters'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => [
                'px' => [
                    'min' => 0,
                    'max' => 60,
                ],
            ],
            'selectors' => [
                '{{WRAPPER}} .lf-attribute-group__swatches' => 'gap: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();

        $this->start_controls_section('section_style_labels', [
            'label' => __('Labels', 'lime-filters'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('attribute_label_color', [
            'label' => __('Attribute Label Color', 'lime-filters'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .lf-attribute-group__label' => 'color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_section();
    }

    public function render() {
        $product = $this->get_current_product();
        if (!$product instanceof WC_Product) {
            echo '<div class="lf-product-attributes lf-product-attributes--empty">' . esc_html__('Product context not found.', 'lime-filters') . '</div>';
            return;
        }

        wp_enqueue_style(
            'lf-product-attributes-widget',
            LF_PLUGIN_URL . 'includes/elementor/product-attributes/product-attributes.css',
            [],
            LF_VERSION
        );

        $settings = $this->get_settings_for_display();

        $is_variable = $product instanceof WC_Product_Variable;
        $attribute_layout = isset($settings['attribute_layout']) ? $settings['attribute_layout'] : 'list';
        $swatch_layout = isset($settings['swatch_layout']) ? $settings['swatch_layout'] : 'row';

        $content = $is_variable
            ? $this->render_variable_attributes($product, $settings)
            : $this->render_simple_attributes($product, $settings);

        if ($content === '') {
            echo '<div class="lf-product-attributes lf-product-attributes--empty">' . esc_html__('No attributes available for this product.', 'lime-filters') . '</div>';
            return;
        }

        $wrapper_classes = [
            'lf-product-attributes',
            $is_variable ? 'lf-product-attributes--variable' : 'lf-product-attributes--simple',
            'lf-attributes-layout-' . sanitize_html_class($attribute_layout),
        ];
        if ($is_variable) {
            $wrapper_classes[] = 'lf-swatches-layout-' . sanitize_html_class($swatch_layout);
        }

        ?>
        <div class="<?php echo esc_attr(implode(' ', $wrapper_classes)); ?>">
            <?php if ($settings['show_heading'] === 'yes' && !empty($settings['heading_text'])) : ?>
                <h3 class="lf-product-attributes__title"><?php echo esc_html($settings['heading_text']); ?></h3>
            <?php endif; ?>
            <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
        <?php
    }

    protected function render_simple_attributes(WC_Product $product, array $settings) {
        $attributes = $product->get_attributes();
        if (empty($attributes)) {
            return '';
        }

        $layout = isset($settings['attribute_layout']) ? $settings['attribute_layout'] : 'list';
        $wrapper_classes = ['lf-attribute-groups', 'lf-attribute-groups--pills'];
        $wrapper_classes[] = $layout === 'grid' ? 'lf-attribute-groups--grid' : 'lf-attribute-groups--list';

        ob_start();
        echo '<div class="' . esc_attr(implode(' ', $wrapper_classes)) . '">';
        foreach ($attributes as $attribute) {
            if ($attribute->is_taxonomy()) {
                $terms = wc_get_product_terms($product->get_id(), $attribute->get_name(), ['fields' => 'all']);
                if (empty($terms)) {
                    continue;
                }
                echo '<div class="lf-attribute-group">';
                echo '<span class="lf-attribute-group__label">' . esc_html(wc_attribute_label($attribute->get_name())) . '</span>';
                echo '<div class="lf-attribute-group__pills">';
                foreach ($terms as $term) {
                    echo '<span class="lf-pill" title="' . esc_attr($term->name) . '">' . esc_html($term->name) . '</span>';
                }
                echo '</div></div>';
            } else {
                $options = $attribute->get_options();
                if (empty($options)) {
                    continue;
                }
                echo '<div class="lf-attribute-group">';
                echo '<span class="lf-attribute-group__label">' . esc_html($attribute->get_name()) . '</span>';
                echo '<div class="lf-attribute-group__pills">';
                foreach ($options as $option) {
                    echo '<span class="lf-pill" title="' . esc_attr($option) . '">' . esc_html($option) . '</span>';
                }
                echo '</div></div>';
            }
        }
        echo '</div>';
        return ob_get_clean();
    }

    protected function render_variable_attributes(WC_Product_Variable $product, array $settings) {
        $attributes = $product->get_variation_attributes();
        if (empty($attributes)) {
            return '';
        }

        $attribute_layout = isset($settings['attribute_layout']) ? $settings['attribute_layout'] : 'list';
        $swatch_layout = isset($settings['swatch_layout']) ? $settings['swatch_layout'] : 'row';

        $wrapper_classes = [
            'lf-attribute-groups',
            'lf-attribute-groups--swatches',
        ];
        $wrapper_classes[] = $attribute_layout === 'grid' ? 'lf-attribute-groups--grid' : 'lf-attribute-groups--list';

        ob_start();
        echo '<div class="' . esc_attr(implode(' ', $wrapper_classes)) . '">';

        foreach ($attributes as $taxonomy => $options) {
            if (empty($options)) {
                continue;
            }

            $label = wc_attribute_label($taxonomy);
            echo '<div class="lf-attribute-group">';
            echo '<span class="lf-attribute-group__label">' . esc_html($label) . '</span>';
            $swatch_wrap_classes = ['lf-attribute-group__swatches'];
            if ($swatch_layout === 'grid') {
                $swatch_wrap_classes[] = 'lf-attribute-group__swatches--grid';
            }
            echo '<div class="' . esc_attr(implode(' ', $swatch_wrap_classes)) . '" role="list">';

            foreach ($options as $option_slug) {
                $term = taxonomy_exists($taxonomy) ? get_term_by('slug', $option_slug, $taxonomy) : null;
                $name = $term ? $term->name : $option_slug;
                $color = $term ? sanitize_hex_color(get_term_meta($term->term_id, 'lf_swatch_color', true)) : '';
                $image_id = $term ? absint(get_term_meta($term->term_id, 'lf_swatch_image_id', true)) : 0;
                $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '';

                $swatch_classes = ['lf-swatch'];
                $style_attr = '';
                if ($image_url) {
                    $swatch_classes[] = 'lf-swatch--image';
                    $style_attr = 'style="background-image:url(' . esc_url($image_url) . ');"';
                } elseif ($color) {
                    $swatch_classes[] = 'lf-swatch--color';
                    $style_attr = 'style="background-color:' . esc_attr($color) . ';"';
                } else {
                    $swatch_classes[] = 'lf-swatch--text';
                }

                echo '<span class="' . esc_attr(implode(' ', $swatch_classes)) . '" role="listitem" ' . $style_attr . ' title="' . esc_attr($name) . '">';
                if (!$image_url && !$color) {
                    echo esc_html($name);
                }
                echo '</span>';
            }

            echo '</div></div>';
        }

        echo '</div>';
        return ob_get_clean();
    }

    protected function get_current_product() {
        global $product;
        if ($product instanceof WC_Product) {
            return $product;
        }

        $post = get_post();
        if ($post instanceof WP_Post && $post->post_type === 'product') {
            return wc_get_product($post->ID);
        }

        return null;
    }
}
