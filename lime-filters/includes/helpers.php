<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class LF_Helpers {
    public static function colors() {
        $colors = get_option('lime_filters_brand_colors', []);
        $defaults = [
            'accent'     => '#009688',
            'border'     => '#E0E0E0',
            'background' => '#FFFFFF',
            'text'       => '#222222',
        ];
        return wp_parse_args($colors, $defaults);
    }

    public static function mapping() {
        $map = get_option('lime_filters_map', []);
        if (!is_array($map)) {
            $map = [];
        }

        $normalized = [];
        foreach ($map as $key => $value) {
            $slug = is_string($key) ? $key : '';
            if ($slug === '') {
                continue;
            }
            if (is_string($value)) {
                $value = array_filter(array_map('trim', explode(',', $value)));
            } elseif (is_array($value)) {
                $value = array_filter(array_map('trim', $value));
            } else {
                $value = [];
            }

            $value = array_map([__CLASS__, 'sanitize_attr_tax'], $value);

            $value = array_values(array_unique($value));
            $normalized[$slug] = $value;
        }

        return $normalized;
    }

    public static function current_category_slug() {
        if (is_product_category()) {
            $term = get_queried_object();
            return $term ? $term->slug : '';
        }
        // Allow override via shortcode attr `category`
        return '';
    }

    public static function get_attr_terms($taxonomy) {
        $args = ['hide_empty' => false];
        $terms = get_terms($taxonomy, $args);
        if (is_wp_error($terms)) return [];
        return $terms;
    }

    public static function sanitize_attr_tax($attr) {
        $attr = wc_sanitize_taxonomy_name($attr);
        if (strpos($attr, 'pa_') !== 0) $attr = 'pa_' . $attr;
        return $attr;
    }

    public static function attributes_for_context($context_slug) {
        $map = self::mapping();
        $key = $context_slug !== '' ? $context_slug : '__shop__';
        $attrs = isset($map[$key]) ? (array) $map[$key] : [];

        if (empty($attrs) && $key !== '__shop__' && isset($map['__shop__'])) {
            $attrs = (array) $map['__shop__'];
        }

        if (empty($attrs)) {
            $all = [];
            foreach ($map as $set) {
                $all = array_merge($all, (array) $set);
            }
            $attrs = array_values(array_unique($all));
        }

        return $attrs;
    }

    public static function shop_show_categories() {
        $value = get_option('lime_filters_shop_show_categories', 'yes');
        return $value === 'yes';
    }
}
