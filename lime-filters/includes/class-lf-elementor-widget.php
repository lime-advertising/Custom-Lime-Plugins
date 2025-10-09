<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class LF_Elementor_Widget {
    public static function maybe_register() {
        // Elementor 3.5+
        if ( did_action('elementor/loaded') ) {
            add_action('elementor/widgets/register', [__CLASS__, 'register']);
        }
    }

    public static function register($widgets_manager) {
        if (!class_exists('\Elementor\Widget_Base')) return;

        require_once __DIR__ . '/class-lf-elementor-filters-widget.php';
        if (class_exists('LF_Elementor_Filters_Widget')) {
            $widgets_manager->register( new \LF_Elementor_Filters_Widget() );
        }

        $tabs_widget_path = LF_PLUGIN_DIR . 'includes/elementor/category-tabs/class-lf-elementor-category-tabs-widget.php';
        if (file_exists($tabs_widget_path)) {
            require_once $tabs_widget_path;
            if (class_exists('LF_Elementor_Category_Tabs_Widget')) {
                $widgets_manager->register( new \LF_Elementor_Category_Tabs_Widget() );
            }
        }

        $product_attributes_path = LF_PLUGIN_DIR . 'includes/elementor/product-attributes/class-lf-elementor-product-attributes-widget.php';
        if (file_exists($product_attributes_path)) {
            require_once $product_attributes_path;
            if (class_exists('LF_Elementor_Product_Attributes_Widget')) {
                $widgets_manager->register( new \LF_Elementor_Product_Attributes_Widget() );
            }
        }
    }
}
