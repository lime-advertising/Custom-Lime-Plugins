<?php

/**
 * Plugin Name: Lime JSON Viewer
 * Description: View and export the raw _elementor_data JSON for any Elementor-built post/page/template.
 * Version: 1.0.1
 * Author: Lime
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: lime-json-viewer
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

final class Elementor_JSON_Viewer
{
    const MENU_SLUG   = 'lime-json-viewer';
    const NONCE_KEY   = 'eljv_nonce';
    const CAP         = 'edit_posts';
    const META_KEY    = '_elementor_data';
    const REST_NS     = 'eljv/v1';

    public function __construct()
    {
        // Admin UI
        add_action('admin_menu', [$this, 'add_tools_page']);
        add_action('admin_enqueue_scripts', [$this, 'assets']);
        add_filter('post_row_actions', [$this, 'row_action'], 10, 2);
        add_filter('page_row_actions', [$this, 'row_action'], 10, 2);
        add_action('admin_bar_menu', [$this, 'admin_bar'], 100);

        // REST
        add_action('rest_api_init', [$this, 'register_rest']);

        // Ajax download (forces a file download with headers)
        add_action('admin_post_eljv_download', [$this, 'download']);
    }

    public function add_tools_page()
    {
        add_management_page(
            __('Lime JSON Viewer', 'lime-json-viewer'),
            __('Lime JSON Viewer', 'lime-json-viewer'),
            self::CAP,
            self::MENU_SLUG,
            [$this, 'render_tools_page']
        );
    }

    public function assets($hook)
    {
        if ($hook !== 'tools_page_' . self::MENU_SLUG) return;

        wp_add_inline_style('common', '
            .eljv-wrap .widefat { margin-top: 10px; }
            .eljv-json { width: 100%; min-height: 480px; font-family: Menlo,Consolas,monospace; font-size:12px; }
            .eljv-controls { display:flex; gap:8px; align-items:center; margin:10px 0; }
            .eljv-meta { color:#666; font-size:12px; margin-top:6px; }
        ');
        wp_add_inline_script('common', "(function(){\n  var ta = document.getElementById('eljv-json');\n  var copyBtn = document.getElementById('eljv-copy');\n  if (!copyBtn || !ta) return;\n  function setStatus(msg){ copyBtn.textContent = msg; setTimeout(function(){ copyBtn.textContent = 'Copy'; }, 1200);}\n  copyBtn.addEventListener('click', function(e){\n    e.preventDefault();\n    var text = ta.value || '';\n    if (navigator.clipboard && navigator.clipboard.writeText) {\n      navigator.clipboard.writeText(text).then(function(){ setStatus('Copied'); }).catch(function(){\n        ta.select();\n        try { document.execCommand('copy'); setStatus('Copied'); } catch(err) { setStatus('Failed'); }\n        ta.blur();\n      });\n    } else {\n      ta.select();\n      try { document.execCommand('copy'); setStatus('Copied'); } catch(err) { setStatus('Failed'); }\n      ta.blur();\n    }\n  });\n})();");
    }

    public function row_action($actions, $post)
    {
        if (!current_user_can('edit_post', $post->ID)) return $actions;
        $url = $this->viewer_url($post->ID);
        $actions['eljv'] = '<a href="' . esc_url($url) . '">View Elementor JSON</a>';
        return $actions;
    }

    public function admin_bar($wp_admin_bar)
    {
        if (!is_admin() || !is_admin_bar_showing()) return;
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->base !== 'post') return;

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only usage; capability check follows.
        $post_id = isset($_GET['post']) ? absint(wp_unslash($_GET['post'])) : 0;
        if (!$post_id || !current_user_can('edit_post', $post_id)) return;

        $wp_admin_bar->add_node([
            'id'    => 'eljv-view',
            'title' => 'View Elementor JSON',
            'href'  => $this->viewer_url($post_id),
            'meta'  => ['title' => 'Open Elementor JSON Viewer']
        ]);
    }

    private function viewer_url($post_id)
    {
        return add_query_arg([
            'page'  => self::MENU_SLUG,
            'post'  => $post_id,
            '_wpnonce' => wp_create_nonce(self::NONCE_KEY)
        ], admin_url('tools.php'));
    }

    private function pretty_json($raw)
    {
        if ($raw === '' || $raw === null) return '';
        // _elementor_data can be JSON string or serialized JSON array; normalize.
        if (is_array($raw)) {
            return wp_json_encode($raw, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }
        if (is_string($raw)) {
            // Try JSON decode directly
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return wp_json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            }
            // Try maybe_unserialize then decode
            $maybe = maybe_unserialize($raw);
            if (is_string($maybe)) {
                $decoded2 = json_decode($maybe, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return wp_json_encode($decoded2, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                }
            } elseif (is_array($maybe)) {
                return wp_json_encode($maybe, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            }
        }
        // Fallback: return raw
        return (string) $raw;
    }

    public function render_tools_page()
    {
        if (!current_user_can(self::CAP)) wp_die('Insufficient permissions.');

        // Read sanitized inputs from the query string.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified below via wp_verify_nonce.
        $post_id = isset($_GET['post']) ? absint(wp_unslash($_GET['post'])) : 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $nonce_param = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        $nonce_ok = $nonce_param && wp_verify_nonce($nonce_param, self::NONCE_KEY);

        echo '<div class="wrap eljv-wrap"><h1>Elementor JSON Viewer</h1>';

        // Selector form
        echo '<form method="get" action="">';
        echo '<input type="hidden" name="page" value="' . esc_attr(self::MENU_SLUG) . '">';
        wp_nonce_field(self::NONCE_KEY);
        // --- Selector form (robust, works for any post type/status) ---
        echo '<p><label for="eljv-post">Select a Post/Page/Template:</label><br>';

        $types = apply_filters('eljv_post_types', ['page', 'post', 'elementor_library']); // add your CPTs here
        $q = new WP_Query([
            'post_type'      => $types,
            'post_status'    => ['publish', 'draft', 'pending', 'future', 'private'],
            'posts_per_page' => 500,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'fields'         => 'ids',
        ]);

        if (!$q->have_posts()) {
            echo '<em>No posts found for types: ' . esc_html(implode(', ', (array) $types)) . '.</em>';
            echo '</p>';
        } else {
            echo '<select name="post" id="eljv-post">';
            echo '<option value="0">-- Select --</option>';

            // Group IDs by post type for clarity
            $by_type = [];
            foreach ($q->posts as $pid) {
                $by_type[get_post_type($pid)][] = $pid;
            }
            foreach ($by_type as $ptype => $ids) {
                $pto   = get_post_type_object($ptype);
                $label = $pto ? $pto->labels->name : $ptype;
                echo '<optgroup label="' . esc_attr($label) . '">';
                foreach ($ids as $pid) {
                    $pid_int = absint($pid);
                    $title   = get_the_title($pid_int);
                    if ($title === '') {
                        $title = '(no title)';
                    }
                    echo '<option value="' . esc_attr((string) $pid_int) . '"';
                    selected($post_id, $pid_int);
                    echo '>' . esc_html($title) . ' (ID ' . esc_html((string) $pid_int) . ')</option>';
                }
                echo '</optgroup>';
            }
            echo '</select> ';
            echo '<button class="button button-primary" type="submit">View JSON</button></p>';
        }
        // --- end selector ---

        echo '</form>';

        if ($post_id && $nonce_ok) {
            if (!current_user_can('edit_post', $post_id)) {
                echo '<p>You do not have permission to view this post.</p></div>';
                return;
            }
            $post = get_post($post_id);
            if (!$post) {
                echo '<p>Post not found.</p></div>';
                return;
            }

            $raw = get_post_meta($post_id, self::META_KEY, true);
            $json = $this->pretty_json($raw);

            $is_elementor = class_exists('\Elementor\Plugin') ? \Elementor\Plugin::$instance->documents->get($post_id) : null;
            $uses_elementor = $is_elementor ? $is_elementor->is_built_with_elementor() : !!$raw;

            echo '<hr>';
            echo '<h2>' . esc_html(get_the_title($post_id)) . ' <span class="screen-reader-text">JSON</span></h2>';
            echo '<div class="eljv-meta">';
            echo 'Post ID: ' . intval($post_id) . ' | Type: ' . esc_html($post->post_type) . ' | Elementor: ' . ($uses_elementor ? 'Yes' : 'No');
            echo '</div>';

            echo '<div class="eljv-controls">';
            echo '<button id="eljv-copy" class="button">Copy</button>';

            $download_url = wp_nonce_url(add_query_arg([
                'action'  => 'eljv_download',
                'post'    => $post_id
            ], admin_url('admin-post.php')), self::NONCE_KEY);

            echo '<a class="button" href="' . esc_url($download_url) . '">Download .json</a>';

            $rest = rest_url(sprintf('%s/json/%d?_wpnonce=%s', self::REST_NS, $post_id, wp_create_nonce(self::NONCE_KEY)));

            echo '<a class="button" href="' . esc_url($rest) . '" target="_blank" rel="noopener">Open via REST</a>';
            echo '</div>';

            if (!$raw) {
                echo '<div class="notice notice-warning"><p>No ' . esc_html(self::META_KEY) . ' meta found. This post may not be built with Elementor.</p></div>';
                echo '</div>';
                return;
            }

            echo '<textarea id="eljv-json" class="eljv-json" readonly>' . esc_textarea($json) . '</textarea>';
        }

        echo '</div>';
    }

    public function download()
    {
        if (!current_user_can(self::CAP)) wp_die('Insufficient permissions.');
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verifying below
        $nonce_param = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        if (!$nonce_param || !wp_verify_nonce($nonce_param, self::NONCE_KEY)) wp_die('Bad nonce.');
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only usage; capability check follows.
        $post_id = isset($_GET['post']) ? absint(wp_unslash($_GET['post'])) : 0;
        if (!$post_id || !current_user_can('edit_post', $post_id)) wp_die('Invalid post.');

        $raw  = get_post_meta($post_id, self::META_KEY, true);
        $json = $this->pretty_json($raw);
        if ($json === '') $json = '{}';

        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="elementor-' . absint($post_id) . '.json"');
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- raw JSON download, not HTML output.
        echo $json;
        exit;
    }

    public function register_rest()
    {
        register_rest_route(self::REST_NS, '/json/(?P<id>\d+)', [
            'methods'  => 'GET',
            'callback' => function (\WP_REST_Request $req) {
                $post_id = intval($req['id']);
                if (!current_user_can('edit_post', $post_id)) {
                    return new \WP_REST_Response(['error' => 'forbidden'], 403);
                }
                $nonce = $req->get_param('_wpnonce');
                if (!wp_verify_nonce($nonce, self::NONCE_KEY)) {
                    return new \WP_REST_Response(['error' => 'bad_nonce'], 401);
                }
                $raw  = get_post_meta($post_id, self::META_KEY, true);
                $data = $this->normalize_to_array($raw);
                return new \WP_REST_Response([
                    'post_id'    => $post_id,
                    'meta_key'   => self::META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Output payload key only; not a DB query.
                    'data'       => $data,
                    'is_elementor' => !!$data
                ], 200);
            },
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },

        ]);
    }

    private function normalize_to_array($raw)
    {
        if (is_array($raw)) return $raw;
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) return $decoded;
            $maybe = maybe_unserialize($raw);
            if (is_array($maybe)) return $maybe;
            if (is_string($maybe)) {
                $decoded2 = json_decode($maybe, true);
                if (json_last_error() === JSON_ERROR_NONE) return $decoded2;
            }
        }
        return [];
    }
}

new Elementor_JSON_Viewer();
