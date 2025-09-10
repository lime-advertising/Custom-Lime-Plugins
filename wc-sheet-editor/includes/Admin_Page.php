<?php

namespace WCSE;

if (!defined('ABSPATH')) exit;

class Admin_Page
{
    const SLUG = 'wcse-sheet-editor';

    public static function register_menu(): void
    {
        add_submenu_page(
            'edit.php?post_type=product',
            __('Sheet Editor', 'wc-sheet-editor'),  // (Title)
            __('Sheet Editor', 'wc-sheet-editor'),  // Menu
            'manage_woocommerce',
            self::SLUG,
            [self::class, 'render_page'],
            58
        );
    }

    public static function enqueue_assets($hook): void
    {
        if ($hook !== 'product_page_' . self::SLUG) return;
        // Base styles
        wp_enqueue_style('wcse-admin', WCSE_URL . 'assets/css/admin.css', [], WCSE_VER);

        // jQuery and DataTables for a richer, responsive grid UI
        wp_enqueue_script('jquery');
        wp_enqueue_style('datatables', 'https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css', [], '1.13.6');
        wp_enqueue_style('datatables-responsive', 'https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css', ['datatables'], '2.5.0');
        wp_enqueue_style('datatables-fixedcolumns', 'https://cdn.datatables.net/fixedcolumns/4.3.0/css/fixedColumns.dataTables.min.css', ['datatables'], '4.3.0');
        wp_enqueue_script('datatables', 'https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js', ['jquery'], '1.13.6', true);
        wp_enqueue_script('datatables-responsive', 'https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js', ['datatables'], '2.5.0', true);
        wp_enqueue_script('datatables-fixedcolumns', 'https://cdn.datatables.net/fixedcolumns/4.3.0/js/dataTables.fixedColumns.min.js', ['datatables'], '4.3.0', true);

        // WordPress classic editor (TinyMCE) for WYSIWYG modal
        if (function_exists('wp_enqueue_editor')) {
            wp_enqueue_editor();
        } else {
            wp_enqueue_script('wp-tinymce');
        }
        // Ensure WordPress REST API fetch utility is available
        wp_enqueue_script('wp-api-fetch'); // bundled
        wp_enqueue_script(
            'wcse-admin',
            WCSE_URL . 'assets/js/admin.js',
            ['wp-api-fetch', 'jquery', 'datatables', 'datatables-responsive', 'datatables-fixedcolumns'],
            WCSE_VER,
            true
        );
        wp_localize_script(
            'wcse-admin',
            'WCSE',
            [
                'root'  => esc_url_raw(rest_url('wcse/v1/')),
                'nonce' => wp_create_nonce('wp_rest'),
                'perPage' => 50,
                'visibleFields' => (function(){
                    $opt = get_option('wcse_visible_fields');
                    return is_array($opt) ? array_values(array_filter(array_map('strval', $opt))) : null;
                })(),
                'i18n'  => [
                    'save' => __('Save changes', 'wc-sheet-editor'),
                    'saving' => __('Saving…', 'wc-sheet-editor'),
                    'columns' => __('Columns', 'wc-sheet-editor'),
                    'apply' => __('Apply', 'wc-sheet-editor'),
                    'cancel' => __('Cancel', 'wc-sheet-editor'),
                ],
            ]
        );
    }

    public static function render_page(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'wc-sheet-editor'));
        }
        echo '<div class="wrap"><h1>' . esc_html__('WooCommerce Sheet Editor', 'wc-sheet-editor') . '</h1>';
        echo '<div id="wcse-app" class="wcse-app"></div></div>';
    }
}
