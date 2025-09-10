<?php

namespace WCSE;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if (!defined('ABSPATH')) exit;

class Rest_Controller
{
    const NS = 'wcse/v1';

    public static function register_routes(): void
    {
        register_rest_route(self::NS, '/products', [
            [
                'methods'  => 'GET',
                'callback' => [self::class, 'get_products'],
                'permission_callback' => [self::class, 'can_manage'],
                'args' => [
                    'page' => ['type' => 'integer', 'default' => 1, 'minimum' => 1],
                    'per_page' => ['type' => 'integer', 'default' => 50, 'minimum' => 1, 'maximum' => 200],
                    'search' => ['type' => 'string'],
                    'status' => [
                        'type' => 'string',
                        'enum' => ['publish', 'draft', 'pending', 'private'],
                        'description' => 'Filter by product post_status',
                    ],
                ],
            ],
            [
                'methods'  => 'POST',
                'callback' => [self::class, 'update_products'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
        ]);

        register_rest_route(self::NS, '/settings', [
            [
                'methods'  => 'GET',
                'callback' => function () {
                    $opt = get_option('wcse_visible_fields');
                    return rest_ensure_response([
                        'visible_fields' => is_array($opt) ? array_values($opt) : null,
                    ]);
                },
                'permission_callback' => [self::class, 'can_manage'],
            ],
            [
                'methods'  => 'POST',
                'callback' => [self::class, 'save_settings'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
        ]);
    }

    public static function can_manage(): bool
    {
        return current_user_can('manage_woocommerce') && wp_verify_nonce($_SERVER['HTTP_X_WP_NONCE'] ?? '', 'wp_rest');
    }

    public static function get_products(WP_REST_Request $req): WP_REST_Response
    {
        $acf_fields = self::acf_fields();
        $args = [
            'post_type'      => 'product',
            'post_status'    => $req->get_param('status') ?: ['publish', 'draft', 'pending', 'private'],
            'posts_per_page' => (int) $req->get_param('per_page'),
            'paged'          => (int) $req->get_param('page'),
            's'              => (string) $req->get_param('search'),
            'fields'         => 'ids',
        ];
        $q = new \WP_Query($args);
        $items = [];
        foreach ($q->posts as $id) {
            $product = wc_get_product($id);
            if (!$product) continue;
            $cats = wp_get_post_terms($id, 'product_cat', ['fields' => 'names']);
            $acf_values = [];
            foreach ($acf_fields as $f) {
                // Retrieve value; for nested groups, read the top-level group and follow the path
                $parents = isset($f['parent_keys']) && is_array($f['parent_keys']) ? $f['parent_keys'] : [];
                if (count($parents) > 0) {
                    $top_key = (string)$parents[0];
                    $group = function_exists('get_field') ? get_field($top_key, $id) : null;
                    $names = array_values(array_filter(array_map('strval', $f['parent_names'] ?? [])));
                    $names[] = (string)($f['name'] ?? '');
                    $val = self::array_get_path(is_array($group) ? $group : [], $names);
                } else {
                    $val = function_exists('get_field') ? get_field($f['key'], $id) : null;
                }
                // Normalize values for UI
                if (($f['type'] === 'checkbox') || ($f['type'] === 'select' && !empty($f['multiple']))) {
                    $vals = [];
                    if (is_array($val)) {
                        foreach ($val as $v) {
                            if (is_array($v) && isset($v['value'])) $vals[] = (string) $v['value'];
                            else $vals[] = (string) $v;
                        }
                    }
                    // If return_format is 'label', map labels back to values using choices
                    if (!empty($f['return_format']) && $f['return_format'] === 'label' && !empty($f['choices'])) {
                        $label_to_val = array_change_key_case(array_flip($f['choices']), CASE_LOWER);
                        $vals = array_map(function($x) use ($label_to_val){ $k = strtolower((string)$x); return $label_to_val[$k] ?? (string)$x; }, $vals);
                    }
                    $acf_values[$f['key']] = array_values(array_unique($vals));
                } elseif ($f['type'] === 'select' || $f['type'] === 'radio') {
                    if (is_array($val) && isset($val['value'])) $val = (string) $val['value'];
                    if (!empty($f['return_format']) && $f['return_format'] === 'label' && !empty($f['choices'])) {
                        $label_to_val = array_change_key_case(array_flip($f['choices']), CASE_LOWER);
                        $val = $label_to_val[strtolower((string)$val)] ?? (string) $val;
                    }
                    $acf_values[$f['key']] = ($val === null) ? '' : (string)$val;
                } else {
                    if (is_array($val)) $val = implode(', ', array_map('strval', $val));
                    $acf_values[$f['key']] = ($val === null) ? '' : (string)$val;
                }
            }
            $items[] = [
                'ID'       => $id,
                'name'     => $product->get_name(),
                'sku'      => $product->get_sku(),
                'regular_price' => $product->get_regular_price(),
                'sale_price'    => $product->get_sale_price(),
                'stock_status'  => $product->get_stock_status(),
                'stock_qty'     => $product->managing_stock() ? (int) $product->get_stock_quantity() : null,
                'status'        => get_post_status($id),
                'type'          => $product->get_type(),
                'categories'    => implode(', ', $cats),
                'short_description' => (string) get_post_field('post_excerpt', $id),
                'description'       => (string) get_post_field('post_content', $id),
                'acf'           => $acf_values,
            ];
        }
        return new WP_REST_Response([
            'total' => (int) $q->found_posts,
            'pages' => (int) $q->max_num_pages,
            'items' => $items,
            'acf_fields' => $acf_fields,
        ]);
    }

    public static function update_products(WP_REST_Request $req)
    {
        $rows = $req->get_json_params();
        if (!is_array($rows)) return new WP_Error('wcse_bad_request', 'Invalid payload', ['status' => 400]);

        $acf_fields = self::acf_fields();
        $acf_index = [];
        foreach ($acf_fields as $f) { $acf_index[$f['key']] = $f; }

        $out = [];
        foreach ($rows as $row) {
            $id = isset($row['ID']) ? (int) $row['ID'] : 0;
            $product = $id ? wc_get_product($id) : null;
            if (!$product) {
                $out[] = ['ID' => $id, 'ok' => false, 'error' => 'Product not found'];
                continue;
            }

            // Basic fields
            if (isset($row['name']))           $product->set_name(wp_kses_post($row['name']));
            if (isset($row['sku']))            $product->set_sku(sanitize_text_field($row['sku']));
            if (isset($row['regular_price']))  $product->set_regular_price(self::num_or_empty($row['regular_price']));
            if (isset($row['sale_price']))     $product->set_sale_price(self::num_or_empty($row['sale_price']));
            if (isset($row['stock_status']))   $product->set_stock_status(in_array($row['stock_status'], ['instock', 'outofstock', 'onbackorder'], true) ? $row['stock_status'] : 'instock');
            if (array_key_exists('short_description', $row)) {
                wp_update_post(['ID' => $id, 'post_excerpt' => wp_kses_post((string)$row['short_description'])]);
            }
            if (array_key_exists('description', $row)) {
                wp_update_post(['ID' => $id, 'post_content' => wp_kses_post((string)$row['description'])]);
            }

            if (array_key_exists('stock_qty', $row)) {
                $qty = $row['stock_qty'];
                $product->set_manage_stock($qty !== null && $qty !== '');
                if ($qty !== null && $qty !== '' && is_numeric($qty)) $product->set_stock_quantity((int) $qty);
            }

            if (isset($row['status']) && in_array($row['status'], ['publish', 'draft', 'pending', 'private'], true)) {
                wp_update_post(['ID' => $id, 'post_status' => $row['status']]);
            }

            if (isset($row['categories'])) {
                $names = array_filter(array_map('trim', explode(',', (string) $row['categories'])));
                $term_ids = [];
                foreach ($names as $name) {
                    $term = term_exists($name, 'product_cat');
                    if (!$term) $term = wp_insert_term($name, 'product_cat');
                    if (!is_wp_error($term)) $term_ids[] = (int)($term['term_id'] ?? $term);
                }
                wp_set_post_terms($id, $term_ids, 'product_cat', false);
            }

            // ACF fields (simple + some multi types)
            if (!empty($row['acf']) && is_array($row['acf']) && function_exists('update_field')) {
                $group_updates = [];
                foreach ($row['acf'] as $fieldKey => $value) {
                    if (!isset($acf_index[$fieldKey])) continue;
                    $f = $acf_index[$fieldKey];
                    $val = $value;
                    switch ($f['type']) {
                        case 'number':
                            $val = is_numeric($val) ? 0 + $val : null;
                            break;
                        case 'true_false':
                            $val = (int) (!!$val);
                            break;
                        case 'select':
                            if (!empty($f['multiple'])) {
                                $allowed = isset($f['choices']) && is_array($f['choices']) ? array_keys($f['choices']) : [];
                                $arr = is_array($val) ? $val : [$val];
                                $outv = [];
                                foreach ($arr as $vv) {
                                    $sv = (string)$vv;
                                    if ($allowed && !in_array($sv, array_map('strval', $allowed), true)) continue;
                                    $outv[] = $sv;
                                }
                                $val = $outv;
                                break;
                            }
                            // fall through to single-select handling
                        case 'radio':
                            $allowed = isset($f['choices']) && is_array($f['choices']) ? array_keys($f['choices']) : [];
                            if ($allowed && !in_array((string)$val, array_map('strval', $allowed), true)) {
                                continue 2; // skip invalid choice
                            }
                            $val = (string) $val;
                            break;
                        case 'checkbox':
                            $allowed = isset($f['choices']) && is_array($f['choices']) ? array_keys($f['choices']) : [];
                            $arr = is_array($val) ? $val : [$val];
                            $outv = [];
                            foreach ($arr as $vv) {
                                $sv = (string)$vv;
                                if ($allowed && !in_array($sv, array_map('strval', $allowed), true)) continue;
                                $outv[] = $sv;
                            }
                            $val = $outv;
                            break;
                        case 'wysiwyg':
                            $val = (string) $val; // sanitize on write
                            break;
                        default:
                            $val = sanitize_text_field((string) $val);
                    }
                    if ($val !== null) {
                        $parents = isset($f['parent_keys']) && is_array($f['parent_keys']) ? $f['parent_keys'] : [];
                        if (count($parents) > 0) {
                            $top = (string)$parents[0];
                            $path = array_values(array_filter(array_map('strval', $f['parent_names'] ?? [])));
                            $leaf = (string)($f['name'] ?? '');
                            $path[] = $leaf;
                            $group_updates[$top] = self::array_set_path(
                                isset($group_updates[$top]) && is_array($group_updates[$top]) ? $group_updates[$top] : [],
                                $path,
                                ($f['type'] === 'wysiwyg') ? wp_kses_post($val) : $val
                            );
                        } else {
                            if ($f['type'] === 'wysiwyg') $val = wp_kses_post($val);
                            update_field($f['key'], $val, $id);
                        }
                    }
                }
                // Apply group updates per parent group
                foreach ($group_updates as $top_key => $patch) {
                    $existing = function_exists('get_field') ? get_field($top_key, $id) : [];
                    if (!is_array($existing)) $existing = [];
                    $merged = array_replace_recursive($existing, $patch);
                    update_field($top_key, $merged, $id);
                }
            }

            try {
                $product->save();
                $out[] = ['ID' => $id, 'ok' => true];
            } catch (\Throwable $e) {
                $out[] = ['ID' => $id, 'ok' => false, 'error' => $e->getMessage()];
            }
        }
        return rest_ensure_response($out);
    }

    private static function num_or_empty($v): string
    {
        return is_numeric($v) ? (string) $v : '';
    }

    public static function save_settings(WP_REST_Request $req)
    {
        $data = $req->get_json_params();
        $list = $data['visible_fields'] ?? null;
        if ($list !== null && !is_array($list)) {
            return new WP_Error('wcse_bad_request', 'visible_fields must be an array or null', ['status' => 400]);
        }
        if (is_array($list)) {
            $allowed_core = [
                'name','sku','regular_price','sale_price','stock_status','stock_qty','status','categories','type'
            ];
            $clean = [];
            foreach ($list as $k) {
                $k = (string)$k;
                if (in_array($k, $allowed_core, true)) { $clean[] = $k; continue; }
                if (preg_match('/^acf:[A-Za-z0-9_\-]+$/', $k)) { $clean[] = $k; continue; }
            }
            $clean = array_values(array_unique($clean));
            update_option('wcse_visible_fields', $clean, false);
            $saved = $clean;
        } else {
            delete_option('wcse_visible_fields');
            $saved = null;
        }
        return rest_ensure_response(['visible_fields' => $saved]);
    }

    private static function acf_fields(): array
    {
        if (!function_exists('acf_get_field_groups')) return [];
        $allowed = ['text','textarea','email','url','number','true_false','select','radio','checkbox','wysiwyg'];
        $out = [];
        $groups = acf_get_field_groups(['post_type' => 'product']);
        foreach ($groups as $group) {
            $key_or_id = $group['key'] ?? ($group['ID'] ?? 0);
            $fields = function_exists('acf_get_fields') ? acf_get_fields($key_or_id) : [];
            if (!$fields) continue;
            $out = array_merge($out, self::acf_flatten_fields($fields, [], $allowed));
        }
        return $out;
    }

    private static function acf_flatten_fields(array $fields, array $parents, array $allowed): array
    {
        $out = [];
        foreach ($fields as $f) {
            $type = $f['type'] ?? 'text';
            // Skip group fields entirely (do not expose subfields in the sheet)
            if ($type === 'group') continue;
            if (!in_array($type, $allowed, true)) continue;
            $choices = [];
            if (in_array($type, ['select','radio','checkbox'], true) && !empty($f['choices']) && is_array($f['choices'])) {
                $choices = $f['choices'];
            }
            $label = (string)($f['label'] ?? ($f['name'] ?? ''));
            $out[] = [
                'key'           => (string)($f['key'] ?? ''),
                'name'          => (string)($f['name'] ?? ''),
                'label'         => $label,
                'type'          => $type,
                'choices'       => $choices,
                'multiple'      => (bool)!empty($f['multiple']),
                'return_format' => (string)($f['return_format'] ?? ''),
                'parent_keys'   => [],
                'parent_names'  => [],
            ];
        }
        return $out;
    }

    private static function array_set_path(array $base, array $path, $value): array
    {
        if (empty($path)) return $base;
        $ref =& $base;
        foreach ($path as $seg) {
            if ($seg === '') continue;
            if (!isset($ref[$seg]) || !is_array($ref[$seg])) $ref[$seg] = [];
            $ref =& $ref[$seg];
        }
        $ref = $value;
        return $base;
    }

    private static function array_get_path(array $base, array $path)
    {
        $ref = $base;
        foreach ($path as $seg) {
            if ($seg === '') continue;
            if (!is_array($ref) || !array_key_exists($seg, $ref)) return null;
            $ref = $ref[$seg];
        }
        return $ref;
    }
}
