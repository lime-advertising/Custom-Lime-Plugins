<?php
// Styles tab: lite builder with ordering, toggles, and minimal style controls.
$cpts = array_keys($types);
$active_cpt = isset($_GET['cpt']) && in_array(sanitize_key($_GET['cpt']), $cpts, true) ? sanitize_key($_GET['cpt']) : ($cpts ? $cpts[0] : '');
if (!$active_cpt) {
  echo '<p>No CPTs defined yet. Create one in the Content Types tab.</p>';
  return;
}
$cfg = method_exists($this, 'get_styles_config') ? $this->get_styles_config($active_cpt) : [];
$els = ['title'=>'Title','image'=>'Featured Image','excerpt'=>'Excerpt','content'=>'Content','meta1'=>'Meta #1','meta2'=>'Meta #2','meta3'=>'Meta #3','button'=>'Read More Button'];
$order = $cfg['layout']['order'];
$enabled = $cfg['layout']['enabled'];
$meta_keys = $cfg['layout']['meta_keys'];
$fields = $types[$active_cpt]['fields'] ?? [];
$supports = (array)($types[$active_cpt]['supports'] ?? []);
$has_meta = !empty($fields);
// Build allowed element set based on CPT supports and available fields
$allowed_list = [];
if (in_array('title', $supports, true))   $allowed_list[] = 'title';
if (in_array('thumbnail', $supports, true)) $allowed_list[] = 'image';
if (in_array('excerpt', $supports, true)) $allowed_list[] = 'excerpt';
if (in_array('editor', $supports, true))  $allowed_list[] = 'content';
if ($has_meta) { $allowed_list = array_merge($allowed_list, ['meta1','meta2','meta3']); }
$allowed_list[] = 'button';
$allowed = array_fill_keys($allowed_list, true);
// Filter saved order to allowed, and append any newly allowed not present
$order = array_values(array_filter($order, function($k) use ($allowed){ return isset($allowed[$k]); }));
$order = array_values(array_unique($order));
foreach ($allowed_list as $k) { if (!in_array($k, $order, true)) $order[] = $k; }
$field_options = [''=>'— Select —'];
foreach ($fields as $f) { $field_options[$f['key']] = $f['label'] . ' (' . $f['key'] . ')'; }
?>

<h2 class="title">Styles for CPT: <code><?php echo esc_html($active_cpt); ?></code></h2>

<form method="get" style="margin-bottom:1em;">
  <input type="hidden" name="page" value="cphub" />
  <input type="hidden" name="tab" value="styles" />
  <label>Choose CPT: 
    <select name="cpt" onchange="this.form.submit()">
      <?php foreach ($cpts as $slug): ?>
        <option value="<?php echo esc_attr($slug); ?>" <?php selected($slug===$active_cpt); ?>><?php echo esc_html($slug); ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <noscript><button class="button">Go</button></noscript>
  <span class="description">Version: <?php echo esc_html(substr($cfg['version'],0,7)); ?></span>
</form>

<form method="post" class="card" style="padding:1em; max-width:1100px;">
  <?php wp_nonce_field(CPT_Hub_Publisher::NONCE_ACTION); ?>
  <input type="hidden" name="cphub_action" value="styles_save" />
  <input type="hidden" name="cpt" value="<?php echo esc_attr($active_cpt); ?>" />

  <h3>Elements</h3>
  <p class="description">Drag to reorder. Use the checkboxes to show/hide. Meta fields can be bound to keys below.</p>
  <ul id="cphub-style-order" class="widefat" style="padding:8px;max-width:700px;">
    <?php foreach ($order as $key): if (empty($allowed[$key])) continue; ?>
      <li class="cphub-style-item" style="display:flex;align-items:center;gap:8px;padding:6px;border:1px solid #e5e7eb;margin-bottom:6px;background:#fff;">
        <span class="dashicons dashicons-move"></span>
        <input type="hidden" name="layout[order][]" value="<?php echo esc_attr($key); ?>" />
        <label style="flex:1 1 auto;">
          <input type="checkbox" name="layout[enabled][<?php echo esc_attr($key); ?>]" value="1" <?php checked(!empty($enabled[$key])); ?> /> <?php echo esc_html($els[$key]); ?>
        </label>
      </li>
    <?php endforeach; ?>
  </ul>

  <div style="display:flex;gap:40px;flex-wrap:wrap;">
    <div>
      <h3>Meta Field Mapping</h3>
      <?php if ($has_meta): ?>
        <table class="form-table"><tbody>
          <?php foreach (['meta1','meta2','meta3'] as $mk): ?>
            <tr>
              <th scope="row"><?php echo esc_html($els[$mk]); ?></th>
              <td>
                <select name="layout[meta_keys][<?php echo esc_attr($mk); ?>]">
                  <?php foreach ($field_options as $k => $label): ?>
                    <option value="<?php echo esc_attr($k); ?>" <?php selected(($meta_keys[$mk]??'')===$k); ?>><?php echo esc_html($label); ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody></table>
      <?php else: ?>
        <p class="description">No custom fields defined for this CPT. Add fields in the Content Types tab to enable meta elements.</p>
      <?php endif; ?>
    </div>

    <div>
      <h3>Styles</h3>
      <table class="form-table"><tbody>
        <tr><th scope="row"><label>Primary color</label></th>
          <td><input type="text" name="styles[primary]" value="<?php echo esc_attr($cfg['styles']['primary']); ?>" class="regular-text" placeholder="#0d6efd" /></td></tr>
        <tr><th scope="row"><label>Text color</label></th>
          <td><input type="text" name="styles[text]" value="<?php echo esc_attr($cfg['styles']['text']); ?>" class="regular-text" placeholder="#111111" /></td></tr>
        <tr><th scope="row"><label>Base font size</label></th>
          <td><input type="number" name="styles[font_size]" value="<?php echo esc_attr($cfg['styles']['font_size']); ?>" min="10" /> px</td></tr>
        <tr><th scope="row"><label>Spacing</label></th>
          <td><input type="number" name="styles[spacing]" value="<?php echo esc_attr($cfg['styles']['spacing']); ?>" min="0" /> px</td></tr>
        <tr><th scope="row"><label>Border radius</label></th>
          <td><input type="number" name="styles[radius]" value="<?php echo esc_attr($cfg['styles']['radius']); ?>" min="0" /> px</td></tr>
      </tbody></table>
    </div>
  </div>

  <p><button class="button button-primary">Save Styles</button></p>
</form>

<h3>Preview</h3>
<p class="description">Preview uses the latest post of this CPT. Saved styles are applied.</p>
<?php
  // Build a tiny preview using latest post
  $q = new WP_Query(['post_type'=>$active_cpt,'post_status'=>'publish','posts_per_page'=>1]);
  if ($q->have_posts()) { $q->the_post();
    $item = $this->map_post_to_feed_item(get_post());
    wp_reset_postdata();
    $css = $this->build_styles_css($cfg['styles']);
    echo '<style>'.$css.'</style>';
    echo '<div class="cphub-card">';
    foreach ($order as $k) {
      if (empty($allowed[$k]) || empty($enabled[$k])) continue;
      switch ($k) {
        case 'title': echo '<h3 class="cphub-title"><a href="#">'.esc_html($item['title']).'</a></h3>'; break;
        case 'image': if (!empty($item['thumb'])) echo '<img class="cphub-img" src="'.esc_url($item['thumb']).'" alt="" />'; break;
        case 'excerpt': echo '<div class="cphub-excerpt">'.wp_kses_post($item['excerpt']).'</div>'; break;
        case 'content': echo '<div class="cphub-content">'.wp_kses_post($item['content']).'</div>'; break;
        case 'meta1': case 'meta2': case 'meta3':
          $key = $meta_keys[$k] ?? '';
          if ($key && isset($item['meta'][$key])) echo '<div class="cphub-meta"><strong>'.esc_html($key).':</strong> '.esc_html($item['meta'][$key]).'</div>';
          break;
        case 'button': echo '<a class="cphub-btn" href="#">Read More</a>'; break;
      }
    }
    echo '</div>';
  } else {
    echo '<p><em>No posts found for this CPT.</em></p>';
  }
?>

<script>
  jQuery(function($){ $('#cphub-style-order').sortable({ handle: '.dashicons-move', placeholder: 'ui-state-highlight' }); });
</script>
