<?php
// Register Code tab — show register_post_type() code that Publisher uses based on current settings.
if (!defined('ABSPATH')) exit;
$types = get_option(CPT_Hub_Publisher::OPTION_KEY, []);
?>

<h2 class="title">Register CPT Code (Publisher)</h2>
<p class="description">This shows the exact arguments used by CPT Hub to register each Custom Post Type on this site.</p>

<?php if (!$types): ?>
  <p>No CPTs defined yet.</p>
<?php else: ?>
  <?php foreach ($types as $slug => $def):
        if (strlen($slug) > 20) continue; // invalid key length won't be registered
        $labels = [
          'name' => $def['label'],
          'singular_name' => ucfirst($slug),
          'menu_name' => $def['label'],
        ];
        $rw = isset($def['rewrite_slug']) && is_string($def['rewrite_slug']) && $def['rewrite_slug'] !== '' ? sanitize_title($def['rewrite_slug']) : $slug;
        $has_arch = !empty($def['has_archive']) ? ($rw ?: true) : false;
        $assigned_tax = isset($def['taxonomies']) ? (array)$def['taxonomies'] : [];
        $assigned_tax[] = 'location';
        $assigned_tax = array_values(array_unique(array_filter($assigned_tax)));
        $args = [
          'labels' => $labels,
          'public' => true,
          'show_in_rest' => true,
          'has_archive' => $has_arch,
          'supports' => $def['supports'] ?: ['title'],
          'rewrite' => ['slug' => $rw, 'with_front' => false],
          'taxonomies' => $assigned_tax,
        ];
        $code = "register_post_type('" . esc_html($slug) . "', " . var_export($args, true) . ");";
  ?>
    <h3><code><?php echo esc_html($slug); ?></code></h3>
    <pre class="code"><code><?php echo esc_html($code); ?></code></pre>
  <?php endforeach; ?>
<?php endif; ?>

<p class="description">Note: WordPress enforces CPT keys ≤ 20 characters. Public URL slug (rewrite slug) can be longer and is shown above under <code>rewrite['slug']</code>.</p>

