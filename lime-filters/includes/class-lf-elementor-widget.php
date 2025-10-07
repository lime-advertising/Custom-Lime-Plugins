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
        if (!class_exists('LF_Elementor_Filters_Widget')) return;

        $widgets_manager->register( new \LF_Elementor_Filters_Widget() );
    }
}
