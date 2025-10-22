<?php
namespace Lime\ACF_Path;

if (!defined('ABSPATH')) exit;

class ACF_Field_Path_Shortcode {

    public static function boot() {
        add_shortcode('myacf', [__CLASS__, 'shortcode_handler']);
    }

    /**
     * Shortcode: [myacf field_path="group/subfield" post_id="options" mailto="true" format="money" delimiter="; "]
     */
    public static function shortcode_handler($atts) {
        $atts = shortcode_atts([
            'field_path' => '',
            'mailto'     => 'false',
            'format'     => '',
            'post_id'    => '',       // numeric ID, slug, or "options"
            'delimiter'  => ', ',
            'debug'      => 'false',
            'raw'        => 'false',  // NEW: output raw HTML (useful for wysiwyg)
        ], $atts, 'myacf');

        if (!function_exists('get_field')) {
            return esc_html__('ACF is not available.', 'acf-field-path-shortcode');
        }

        $field_path = trim((string)$atts['field_path']);
        if ($field_path === '') return '';

        $field_keys = array_values(array_filter(array_map('trim', explode('/', $field_path)), 'strlen'));

        // --- Robust post_id (WooCommerce-aware) ---
        $post_id = $atts['post_id'];
        if ($post_id === '' || strtolower($post_id) === 'current') {
            $pid = 0;

            // 1) Current post in loop
            if (function_exists('get_the_ID')) {
                $pid = (int) get_the_ID();
            }

            // 2) WooCommerce: current product object
            if (!$pid && class_exists('WooCommerce')) {
                global $product;
                if (is_object($product) && method_exists($product, 'get_id')) {
                    $pid = (int) $product->get_id();
                }
                // fallback: try to resolve queried object to product
                if (!$pid && function_exists('get_queried_object_id')) {
                    $qid = (int) get_queried_object_id();
                    if ($qid > 0) {
                        $pid = $qid;
                    }
                }
            }

            // 3) Last resort: queried object id (non-Woo)
            if (!$pid && function_exists('get_queried_object_id')) {
                $pid = (int) get_queried_object_id();
            }

            $post_id = $pid ?: false;
        }

        $value = self::resolve_field_path($field_keys, $post_id);

        // Debug block (optional)
        $debug = in_array(strtolower($atts['debug']), ['true','1','yes'], true);
        if ($debug) {
            ob_start();
            echo '<pre style="white-space:pre-wrap;word-break:break-word;">';
            echo "DEBUG myacf\n";
            echo "post_id: "; var_export($post_id); echo "\n";
            echo "field_path: "; var_export($field_path); echo "\n";
            echo "resolved (raw): "; var_export($value); echo "\n";
            echo "</pre>";
            echo ob_get_clean();
        }

        if (is_array($value)) {
            $value = self::stringify_array_value($value, $atts['delimiter']);
        }

        // Formatters
        $value = self::maybe_format_mailto($value, $atts['mailto']);
        $value = self::maybe_format_money($value, $atts['format']);

        // If raw="true", output as-is (safe if you trust the source, typical for WYSIWYG)
        $raw = in_array(strtolower($atts['raw']), ['true','1','yes'], true);
        if ($raw) {
            return (string) $value;
        }

        // Detect HTML more broadly so <p>…</p> etc. don’t get escaped
        return self::looks_like_html($value) ? (string) $value : esc_html((string) $value);
    }


    /**
     * Core resolver that can drill into groups, repeaters/flexible content, and arrays.
     */
    protected static function resolve_field_path(array $field_keys, $post_id = false, $is_repeater_subfield = false, $current_value = null) {
        if (empty($field_keys)) {
            return $current_value;
        }

        $current_key = array_shift($field_keys);

        // Decide the value at this step
        if ($current_value !== null) {
            $value = $current_value;
        } else {
            if ($is_repeater_subfield) {
                $value = function_exists('get_sub_field') ? get_sub_field($current_key) : null;
            } else {
                $value = function_exists('get_field') ? get_field($current_key, $post_id) : null;
            }
        }

        if (empty($field_keys)) {
            return $value;
        }

        // Top-level repeater/flex
        if ($current_value === null && !$is_repeater_subfield && function_exists('have_rows') && have_rows($current_key, $post_id)) {
            $collected = [];
            while (have_rows($current_key, $post_id)) {
                the_row();
                $collected[] = self::resolve_field_path($field_keys, $post_id, true, null);
            }
            if (function_exists('reset_rows')) {
                reset_rows($current_key, $post_id);
            } elseif (function_exists('acf_reset_loop')) {
                acf_reset_loop();
            }
            return $collected; // let caller stringify/format
        }

        // Group/array: drill into next key
        if (is_array($value)) {
            $remaining_keys = $field_keys;
            $next_key = array_shift($remaining_keys);

            if ($next_key === null) {
                return $value;
            }

            if (array_key_exists($next_key, $value)) {
                return self::resolve_field_path($remaining_keys, $post_id, false, $value[$next_key]);
            }

            if (self::is_sequential_list($value)) {
                $collected = [];
                foreach ($value as $row) {
                    if (!is_array($row) || !array_key_exists($next_key, $row)) {
                        continue;
                    }
                    $collected[] = self::resolve_field_path($remaining_keys, $post_id, false, $row[$next_key]);
                }
                return $collected === [] ? null : $collected;
            }
            return null;
        }

        // Primitive but path continues -> not resolvable
        return null;
    }


    /**
     * Convert common ACF array structures into a readable string.
     */
    protected static function stringify_array_value($value, $delimiter = ', ') {
        // Images: if ACF returns array with 'url' or 'ID'
        if (isset($value['url']) && is_string($value['url'])) {
            return esc_url($value['url']);
        }
        if (isset($value['ID']) && is_numeric($value['ID'])) {
            $url = wp_get_attachment_url((int)$value['ID']);
            return $url ? esc_url($url) : (string)$value['ID'];
        }

        // Post object arrays: try titles
        if (isset($value['post_title'])) {
            return sanitize_text_field($value['post_title']);
        }

        // Generic shallow stringify for mixed arrays
        $flat = [];
        foreach ($value as $k => $v) {
            if (is_scalar($v)) {
                $flat[] = (string)$v;
            } elseif (is_array($v)) {
                // One level deep to avoid huge dumps
                $flat[] = implode(' ', array_filter(array_map(function ($vv) {
                    return is_scalar($vv) ? (string)$vv : '';
                }, $v)));
            }
        }
        $flat = array_filter(array_map('trim', $flat));
        return implode($delimiter, $flat);
    }

    protected static function maybe_format_mailto($value, $mailto_flag) {
        if (!is_string($value) || $value === '') return $value;
        $truthy = in_array(strtolower((string)$mailto_flag), ['true', '1', 'yes'], true);
        if (!$truthy) return $value;

        // Basic email validation
        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $email_attr = esc_attr($value);
            $email_text = esc_html($value);
            return '<a href="mailto:' . $email_attr . '">' . $email_text . '</a>';
        }
        return $value;
    }

    protected static function maybe_format_money($value, $format) {
        if ($format !== 'money') return $value;
        if (is_numeric($value)) {
            return '$' . number_format((float)$value, 2, '.', ',');
        }
        return $value;
    }

    protected static function looks_like_html($value) {
        if (!is_string($value)) return false;
        // Generic check: any tag-like content
        if (strpos($value, '<') !== false && strpos($value, '>') !== false) return true;
        // Specific common tags often returned by WYSIWYG
        foreach (['<p', '<br', '<strong', '<em', '<ul', '<ol', '<li', '<a ', '<img '] as $needle) {
            if (stripos($value, $needle) !== false) return true;
        }
        return false;
    }

    protected static function is_sequential_list($value) {
        if (!is_array($value)) return false;
        if ($value === []) return true;
        return array_keys($value) === range(0, count($value) - 1);
    }

}
