<?php
// Styles tab: lite builder with ordering, toggles, and minimal style controls.
$cpts = array_keys($types);
$active_cpt = isset($_GET['cpt']) && in_array(sanitize_key($_GET['cpt']), $cpts, true) ? sanitize_key($_GET['cpt']) : ($cpts ? $cpts[0] : '');
if (!$active_cpt) {
  echo '<p>No CPTs defined yet. Create one in the Content Types tab.</p>';
  return;
}
$cfg = method_exists($this, 'get_styles_config') ? $this->get_styles_config($active_cpt) : [];
$els = ['title' => 'Title', 'image' => 'Featured Image', 'excerpt' => 'Excerpt', 'content' => 'Content', 'meta1' => 'Meta #1', 'meta2' => 'Meta #2', 'meta3' => 'Meta #3', 'button' => 'Read More Button'];
$order = $cfg['layout']['order'];
$enabled = $cfg['layout']['enabled'];
$meta_keys = $cfg['layout']['meta_keys'];
$meta_wrap = isset($cfg['layout']['meta_wrap']) && is_array($cfg['layout']['meta_wrap']) ? $cfg['layout']['meta_wrap'] : ['meta1'=>'content','meta2'=>'content','meta3'=>'content'];
$fields = $types[$active_cpt]['fields'] ?? [];
$field_index = [];
foreach ((array)$fields as $f) {
  if (!empty($f['key'])) {
    $field_index[$f['key']] = $f;
  }
}
$supports = (array)($types[$active_cpt]['supports'] ?? []);
$has_meta = !empty($fields);
// Build allowed element set based on CPT supports and available fields
$allowed_list = [];
if (in_array('title', $supports, true))   $allowed_list[] = 'title';
if (in_array('thumbnail', $supports, true)) $allowed_list[] = 'image';
if (in_array('excerpt', $supports, true)) $allowed_list[] = 'excerpt';
if (in_array('editor', $supports, true))  $allowed_list[] = 'content';
if ($has_meta) {
  $allowed_list = array_merge($allowed_list, ['meta1', 'meta2', 'meta3']);
}
$allowed_list[] = 'button';
$allowed = array_fill_keys($allowed_list, true);
// Filter saved order to allowed, and append any newly allowed not present
$order = array_values(array_filter($order, function ($k) use ($allowed) {
  return isset($allowed[$k]);
}));
$order = array_values(array_unique($order));
foreach ($allowed_list as $k) {
  if (!in_array($k, $order, true)) $order[] = $k;
}
$field_options = ['' => '— Select —'];
foreach ($fields as $f) {
  $field_options[$f['key']] = $f['label'] . ' (' . $f['key'] . ')';
}
?>

<h2 class="title">Styles for CPT: <code><?php echo esc_html($active_cpt); ?></code></h2>

<form method="get" style="margin-bottom:1em;">
  <input type="hidden" name="page" value="cphub" />
  <input type="hidden" name="tab" value="styles" />
  <label>Choose CPT:
    <select name="cpt" onchange="this.form.submit()">
      <?php foreach ($cpts as $slug): ?>
        <option value="<?php echo esc_attr($slug); ?>" <?php selected($slug === $active_cpt); ?>><?php echo esc_html($slug); ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <noscript><button class="button">Go</button></noscript>
  <span class="description">Version: <?php echo esc_html(substr($cfg['version'], 0, 7)); ?></span>
</form>

<form method="post" class="card" style="padding:1em; max-width:1100px;">
  <style>
    .cphub-acc{border:1px solid #e5e7eb;border-radius:6px;margin:16px 0;background:#fff}
    .cphub-acc>summary{cursor:pointer;padding:10px 12px;font-weight:600;list-style:none}
    .cphub-acc>summary::-webkit-details-marker{display:none}
    .cphub-acc[open]>.cphub-acc-body{padding:12px}
    .cphub-styles .form-table tbody{display:grid;grid-template-columns:repeat(2,minmax(240px,1fr));gap:16px}
    @media (max-width: 900px){.cphub-styles .form-table tbody{grid-template-columns:1fr}}
    .cphub-styles .form-table tr{display:block}
    .cphub-styles .form-table th{display:block;padding:0 0 6px;margin:0}
    .cphub-styles .form-table td{display:block;padding:0}
    .cphub-styles .form-table label{display:block;font-weight:600;margin:0 0 6px}
    .cphub-styles .form-table input[type=text],
    .cphub-styles .form-table input[type=number],
    .cphub-styles .form-table select{width:100%;max-width:100%}
    .cphub-group{border:1px solid #e5e7eb;border-radius:6px;background:#fff;padding:12px}
    .cphub-group h4{margin:0 0 8px}
  </style>
  <?php wp_nonce_field(CPT_Hub_Publisher::NONCE_ACTION); ?>
  <input type="hidden" name="cphub_action" value="styles_save" />
  <input type="hidden" name="cpt" value="<?php echo esc_attr($active_cpt); ?>" />

  <div class="cphub-top" style="display:flex;gap:40px;flex-wrap:wrap;align-items:flex-start;">
    <div style="flex:1 1 420px;min-width:320px;">
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
    </div>
    <div style="flex:1 1 420px;min-width:320px;">
      <h3>Layout Preset</h3>
      <table class="form-table">
        <tbody>
          <tr>
            <th scope="row"><label>Preset</label></th>
            <td>
              <label style="margin-right:1em;"><input type="radio" name="styles[layout_type]" value="list" <?php checked(($cfg['styles']['layout_type'] ?? 'list') === 'list'); ?>> List</label>
              <label><input type="radio" name="styles[layout_type]" value="grid" <?php checked(($cfg['styles']['layout_type'] ?? 'list') === 'grid'); ?>> Grid</label>
            </td>
          </tr>
          <tr class="cphub-grid-opts">
            <th scope="row"><label>Grid columns</label></th>
            <td><input type="number" min="1" max="6" name="styles[grid_cols]" value="<?php echo esc_attr($cfg['styles']['grid_cols'] ?? 3); ?>" /></td>
          </tr>
          <tr class="cphub-grid-opts">
            <th scope="row"><label>Tablet columns</label></th>
            <td><input type="number" min="1" max="6" name="styles[grid_cols_tab]" value="<?php echo esc_attr($cfg['styles']['grid_cols_tab'] ?? 2); ?>" /></td>
          </tr>
          <tr class="cphub-grid-opts">
            <th scope="row"><label>Mobile columns</label></th>
            <td><input type="number" min="1" max="6" name="styles[grid_cols_mob]" value="<?php echo esc_attr($cfg['styles']['grid_cols_mob'] ?? 1); ?>" /></td>
          </tr>
          <tr class="cphub-grid-opts">
            <th scope="row"><label>Grid gap (px)</label></th>
            <td><input type="number" min="0" name="styles[grid_gap]" value="<?php echo esc_attr($cfg['styles']['grid_gap']); ?>" placeholder="(inherit spacing)" /></td>
          </tr>
        </tbody>
      </table>

    </div>
  </div>

  <div style="display:block;gap:40px;flex-wrap:wrap;align-items:flex-start;">
    <div style="flex:1 1 420px;min-width:320px;">
      <details class="cphub-acc" open>
        <summary>Meta Field Mapping</summary>
        <div class="cphub-acc-body">
      <?php if ($has_meta): ?>
        <table class="form-table">
          <thead>
            <tr>
              <th>Meta Slot</th>
              <th>Field</th>
              <th>Placement</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach (['meta1', 'meta2', 'meta3'] as $mk): ?>
              <tr>
                <th scope="row"><?php echo esc_html($els[$mk]); ?></th>
                <td>
                  <select name="layout[meta_keys][<?php echo esc_attr($mk); ?>]">
                    <?php foreach ($field_options as $k => $label): ?>
                      <option value="<?php echo esc_attr($k); ?>" <?php selected(($meta_keys[$mk] ?? '') === $k); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td>
                  <?php $mw = $meta_wrap[$mk] ?? 'content'; ?>
                  <select name="layout[meta_wrap][<?php echo esc_attr($mk); ?>]">
                    <option value="content" <?php selected($mw==='content'); ?>>Content wrap</option>
                    <option value="thumb" <?php selected($mw==='thumb'); ?>>Thumb wrap</option>
                  </select>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="description">No custom fields defined for this CPT. Add fields in the Content Types tab to enable meta elements.</p>
      <?php endif; ?>
        </div>
      </details>
    </div>

    <div style="flex:1 1 520px;min-width:360px;" class="cphub-styles">
      <details class="cphub-acc" open>
        <summary>Styles</summary>
        <div class="cphub-acc-body">
      <table class="form-table">
        <tbody>
          <tr>
            <th scope="row"><label>Primary color</label></th>
            <td><input type="text" name="styles[primary]" value="<?php echo esc_attr($cfg['styles']['primary']); ?>" class="regular-text" placeholder="#0d6efd" /></td>
          </tr>
          <tr>
            <th scope="row"><label>Text color</label></th>
            <td><input type="text" name="styles[text]" value="<?php echo esc_attr($cfg['styles']['text']); ?>" class="regular-text" placeholder="#111111" /></td>
          </tr>
          <tr>
            <th scope="row"><label>Base font size</label></th>
            <td><input type="number" name="styles[font_size]" value="<?php echo esc_attr($cfg['styles']['font_size']); ?>" min="10" /> px</td>
          </tr>
          <tr>
            <th scope="row"><label>Spacing</label></th>
            <td><input type="number" name="styles[spacing]" value="<?php echo esc_attr($cfg['styles']['spacing']); ?>" min="0" /> px</td>
          </tr>
          <tr>
            <th scope="row"><label>Border radius</label></th>
            <td><input type="number" name="styles[radius]" value="<?php echo esc_attr($cfg['styles']['radius']); ?>" min="0" /> px</td>
          </tr>
        </tbody>
      </table>

      <h4 style="margin-top:1.5em;">Responsive Scaling</h4>
      <table class="form-table">
        <tbody>
          <tr>
            <th scope="row"><label>Tablet scale</label></th>
            <td>
              <input type="number" name="styles[scale_tab]" min="0.1" max="3" step="0.05" style="width:120px;" value="<?php echo esc_attr($cfg['styles']['scale_tab'] ?? 1); ?>" />
              <span class="description">Multiplies desktop sizes (e.g., 0.9)</span>
            </td>
          </tr>
          <tr>
            <th scope="row"><label>Mobile scale</label></th>
            <td>
              <input type="number" name="styles[scale_mob]" min="0.1" max="3" step="0.05" style="width:120px;" value="<?php echo esc_attr($cfg['styles']['scale_mob'] ?? 1); ?>" />
              <span class="description">Multiplies desktop sizes (e.g., 0.6)</span>
            </td>
          </tr>
        </tbody>
      </table>

      <div class="cphub-group" style="margin-top:12px;">
        <h4>Card</h4>
        <table class="form-table">
          <tbody>
          <tr>
            <th scope="row"><label>Background</label></th>
            <td><input type="text" name="styles[card_bg]" value="<?php echo esc_attr($cfg['styles']['card_bg']); ?>" class="regular-text" placeholder="#ffffff" /></td>
          </tr>
          <tr>
            <th scope="row"><label>Border color</label></th>
            <td><input type="text" name="styles[card_border]" value="<?php echo esc_attr($cfg['styles']['card_border']); ?>" class="regular-text" placeholder="#e5e7eb" /></td>
          </tr>
          <tr>
            <th scope="row"><label>Padding (px)</label></th>
            <td><input type="number" name="styles[card_padding]" value="<?php echo esc_attr($cfg['styles']['card_padding']); ?>" min="0" placeholder="(inherit spacing)" /></td>
          </tr>
          <tr>
            <th scope="row"><label>Vertical margin (px)</label></th>
            <td><input type="number" name="styles[card_margin_y]" value="<?php echo esc_attr($cfg['styles']['card_margin_y']); ?>" min="0" placeholder="(inherit spacing)" /></td>
          </tr>
          <tr>
            <th scope="row"><label>Shadow</label></th>
            <td><label><input type="checkbox" name="styles[card_shadow]" value="1" <?php checked(!empty($cfg['styles']['card_shadow'])); ?> /> Enable subtle shadow</label></td>
          </tr>
          <tr>
            <th scope="row"><label>Animations</label></th>
            <td><label><input type="checkbox" name="styles[anim_enable]" value="1" <?php checked(!empty($cfg['styles']['anim_enable'])); ?> /> Enable subtle animations (fade/slide in, button hover)</label></td>
          </tr>
          </tbody>
        </table>
      </div>

      <div class="cphub-group" style="margin-top:12px;">
        <h4>Animations</h4>
        <table class="form-table">
          <tbody>
            <tr>
              <th scope="row"><label>Entrance stagger</label></th>
              <td>
                <label style="display:block;margin-bottom:6px;"><input type="checkbox" name="styles[anim_stagger_enable]" value="1" <?php checked(!empty($cfg['styles']['anim_stagger_enable'])); ?> /> Enable staggered entrance</label>
                <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
                  <label>Duration (ms)
                    <input type="number" name="styles[anim_stagger_duration]" value="<?php echo esc_attr($cfg['styles']['anim_stagger_duration']); ?>" min="50" style="width:120px;" />
                  </label>
                  <label>Delay per item (ms)
                    <input type="number" name="styles[anim_stagger_delay_step]" value="<?php echo esc_attr($cfg['styles']['anim_stagger_delay_step']); ?>" min="0" style="width:120px;" />
                  </label>
                  <label>Distance (px)
                    <input type="number" name="styles[anim_stagger_offset]" value="<?php echo esc_attr($cfg['styles']['anim_stagger_offset']); ?>" min="0" style="width:120px;" />
                  </label>
                  <label>Easing
                    <input type="text" name="styles[anim_stagger_ease]" value="<?php echo esc_attr($cfg['styles']['anim_stagger_ease']); ?>" class="regular-text" placeholder="ease-out or cubic-bezier(...)" />
                  </label>
                </div>
              </td>
            </tr>
            <tr>
              <th scope="row"><label>Thumbnail hover reveal</label></th>
              <td>
                <label style="display:block;margin-bottom:6px;"><input type="checkbox" name="styles[hover_reveal_enable]" value="1" <?php checked(!empty($cfg['styles']['hover_reveal_enable'])); ?> /> Enable hover overlay sweep</label>
                <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
                  <label>Style
                    <?php $hr_style = $cfg['styles']['hover_reveal_style'] ?? 'solid'; ?>
                    <select name="styles[hover_reveal_style]">
                      <option value="solid" <?php selected($hr_style==='solid'); ?>>Solid overlay</option>
                      <option value="sheen" <?php selected($hr_style==='sheen'); ?>>Sheen gradient</option>
                    </select>
                  </label>
                  <label>Color
                    <input type="text" name="styles[hover_reveal_color]" value="<?php echo esc_attr($cfg['styles']['hover_reveal_color']); ?>" class="regular-text" placeholder="#ffffff" />
                  </label>
                  <label>Opacity
                    <input type="number" name="styles[hover_reveal_opacity]" value="<?php echo esc_attr($cfg['styles']['hover_reveal_opacity']); ?>" min="0" max="1" step="0.05" style="width:100px;" />
                  </label>
                  <label>Duration (ms)
                    <input type="number" name="styles[hover_reveal_duration]" value="<?php echo esc_attr($cfg['styles']['hover_reveal_duration']); ?>" min="0" style="width:120px;" />
                  </label>
                  <label>Easing
                    <input type="text" name="styles[hover_reveal_ease]" value="<?php echo esc_attr($cfg['styles']['hover_reveal_ease']); ?>" class="regular-text" placeholder="ease" />
                  </label>
                  <label>Angle (deg)
                    <input type="number" name="styles[hover_reveal_angle]" value="<?php echo esc_attr($cfg['styles']['hover_reveal_angle']); ?>" style="width:100px;" />
                  </label>
                  <label>Thickness
                    <input type="text" name="styles[hover_reveal_thickness]" value="<?php echo esc_attr($cfg['styles']['hover_reveal_thickness']); ?>" class="regular-text" placeholder="e.g. 20% or 24px" />
                  </label>
                  <label>Direction
                    <?php $dir = $cfg['styles']['hover_reveal_direction'] ?? 'tl-br'; ?>
                    <select name="styles[hover_reveal_direction]">
                      <option value="tl-br" <?php selected($dir==='tl-br'); ?>>Top-left → Bottom-right</option>
                      <option value="br-tl" <?php selected($dir==='br-tl'); ?>>Bottom-right → Top-left</option>
                    </select>
                  </label>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <?php if (!empty($enabled['title'])): ?>
        <div class="cphub-group" style="margin-top:12px;">
        <h4>Title</h4>
        <table class="form-table">
          <tbody>
            <tr>
              <th scope="row"><label>Title color</label></th>
              <td><input type="text" name="styles[title_color]" value="<?php echo esc_attr($cfg['styles']['title_color']); ?>" class="regular-text" placeholder="(inherit primary)" /></td>
            </tr>
            <tr>
              <th scope="row"><label>Title size (px)</label></th>
              <td><input type="number" name="styles[title_size]" value="<?php echo esc_attr($cfg['styles']['title_size']); ?>" min="10" placeholder="(base + 2)" /></td>
            </tr>
            <tr>
              <th scope="row"><label>Title weight</label></th>
              <td><input type="number" name="styles[title_weight]" value="<?php echo esc_attr($cfg['styles']['title_weight']); ?>" min="100" max="900" step="100" /></td>
            </tr>
            <tr>
              <th scope="row"><label>Title margin (px)</label></th>
              <td>
                Top <input type="number" name="styles[title_mt]" value="<?php echo esc_attr($cfg['styles']['title_mt']); ?>" min="0" style="width:90px;" />
                Bottom <input type="number" name="styles[title_mb]" value="<?php echo esc_attr($cfg['styles']['title_mb']); ?>" min="0" style="width:90px;" />
              </td>
            </tr>
            <tr>
              <th scope="row"><label>Title padding (px)</label></th>
              <td>
                V <input type="number" name="styles[title_pad_v]" value="<?php echo esc_attr($cfg['styles']['title_pad_v']); ?>" min="0" style="width:90px;" />
                H <input type="number" name="styles[title_pad_h]" value="<?php echo esc_attr($cfg['styles']['title_pad_h']); ?>" min="0" style="width:90px;" />
              </td>
            </tr>
            <tr>
              <th scope="row"><label>Title line-height</label></th>
              <td><input type="number" name="styles[title_lh]" value="<?php echo esc_attr($cfg['styles']['title_lh']); ?>" min="0.8" max="3" step="0.05"  placeholder="e.g. 1.2" /></td>
            </tr>
            <tr>
              <th scope="row"><label>Title align</label></th>
              <td>
                <?php $ta = $cfg['styles']['title_align'] ?? ''; ?>
                <select name="styles[title_align]">
                  <option value="">— Inherit —</option>
                  <option value="left" <?php selected($ta==='left'); ?>>Left</option>
                  <option value="center" <?php selected($ta==='center'); ?>>Center</option>
                  <option value="right" <?php selected($ta==='right'); ?>>Right</option>
                </select>
              </td>
            </tr>
            <tr>
              <th scope="row"><label>Title width</label></th>
              <td>
                <input type="text" name="styles[title_w]" value="<?php echo esc_attr($cfg['styles']['title_w']); ?>" placeholder="e.g. 100%, 320px" style="width:160px;" />
                <input type="text" name="styles[title_min_w]" value="<?php echo esc_attr($cfg['styles']['title_min_w']); ?>" placeholder="min-width" style="width:160px;" />
                <input type="text" name="styles[title_max_w]" value="<?php echo esc_attr($cfg['styles']['title_max_w']); ?>" placeholder="max-width" style="width:160px;" />
              </td>
            </tr>
          </tbody>
        </table>
        </div>
      <?php endif; ?>

      <?php if (!empty($enabled['excerpt'])): ?>
        <div class="cphub-group" style="margin-top:12px;">
        <h4>Excerpt</h4>
        <table class="form-table">
          <tbody>
            <tr>
              <th scope="row"><label>Excerpt color</label></th>
              <td><input type="text" name="styles[excerpt_color]" value="<?php echo esc_attr($cfg['styles']['excerpt_color']); ?>" class="regular-text" /></td>
            </tr>
            <tr>
              <th scope="row"><label>Excerpt size (px)</label></th>
              <td><input type="number" name="styles[excerpt_size]" value="<?php echo esc_attr($cfg['styles']['excerpt_size']); ?>" min="10" placeholder="(inherit base)" /></td>
            </tr>
            <tr>
              <th scope="row"><label>Excerpt margin (px)</label></th>
              <td>
                Top <input type="number" name="styles[excerpt_mt]" value="<?php echo esc_attr($cfg['styles']['excerpt_mt']); ?>" min="0" style="width:90px;" />
                Bottom <input type="number" name="styles[excerpt_mb]" value="<?php echo esc_attr($cfg['styles']['excerpt_mb']); ?>" min="0" style="width:90px;" />
              </td>
            </tr>
            <tr>
              <th scope="row"><label>Excerpt padding (px)</label></th>
              <td>
                V <input type="number" name="styles[excerpt_pad_v]" value="<?php echo esc_attr($cfg['styles']['excerpt_pad_v']); ?>" min="0" style="width:90px;" />
                H <input type="number" name="styles[excerpt_pad_h]" value="<?php echo esc_attr($cfg['styles']['excerpt_pad_h']); ?>" min="0" style="width:90px;" />
              </td>
            </tr>
            <tr>
              <th scope="row"><label>Excerpt line-height</label></th>
              <td><input type="number" name="styles[excerpt_lh]" value="<?php echo esc_attr($cfg['styles']['excerpt_lh']); ?>" min="0.8" max="3" step="0.05"  placeholder="e.g. 1.5" /></td>
            </tr>
            <tr>
              <th scope="row"><label>Excerpt align</label></th>
              <td>
                <?php $ea = $cfg['styles']['excerpt_align'] ?? ''; ?>
                <select name="styles[excerpt_align]">
                  <option value="">— Inherit —</option>
                  <option value="left" <?php selected($ea==='left'); ?>>Left</option>
                  <option value="center" <?php selected($ea==='center'); ?>>Center</option>
                  <option value="right" <?php selected($ea==='right'); ?>>Right</option>
                </select>
              </td>
            </tr>
            <tr>
              <th scope="row"><label>Excerpt width</label></th>
              <td>
                <input type="text" name="styles[excerpt_w]" value="<?php echo esc_attr($cfg['styles']['excerpt_w']); ?>" placeholder="e.g. 100%, 320px" style="width:160px;" />
                <input type="text" name="styles[excerpt_min_w]" value="<?php echo esc_attr($cfg['styles']['excerpt_min_w']); ?>" placeholder="min-width" style="width:160px;" />
                <input type="text" name="styles[excerpt_max_w]" value="<?php echo esc_attr($cfg['styles']['excerpt_max_w']); ?>" placeholder="max-width" style="width:160px;" />
              </td>
            </tr>
          </tbody>
        </table>
        </div>
      <?php endif; ?>

      <?php if (!empty($enabled['content'])): ?>
        <div class="cphub-group" style="margin-top:12px;">
        <h4>Content</h4>
        <table class="form-table">
          <tbody>
            <tr>
              <th scope="row"><label>Content color</label></th>
              <td><input type="text" name="styles[content_color]" value="<?php echo esc_attr($cfg['styles']['content_color']); ?>" class="regular-text" /></td>
            </tr>
            <tr>
              <th scope="row"><label>Content size (px)</label></th>
              <td><input type="number" name="styles[content_size]" value="<?php echo esc_attr($cfg['styles']['content_size']); ?>" min="10" placeholder="(inherit base)" /></td>
            </tr>
            <tr>
              <th scope="row"><label>Content margin (px)</label></th>
              <td>
                Top <input type="number" name="styles[content_mt]" value="<?php echo esc_attr($cfg['styles']['content_mt']); ?>" min="0" style="width:90px;" />
                Bottom <input type="number" name="styles[content_mb]" value="<?php echo esc_attr($cfg['styles']['content_mb']); ?>" min="0" style="width:90px;" />
              </td>
            </tr>
            <tr>
              <th scope="row"><label>Content padding (px)</label></th>
              <td>
                V <input type="number" name="styles[content_pad_v]" value="<?php echo esc_attr($cfg['styles']['content_pad_v']); ?>" min="0" style="width:90px;" />
                H <input type="number" name="styles[content_pad_h]" value="<?php echo esc_attr($cfg['styles']['content_pad_h']); ?>" min="0" style="width:90px;" />
              </td>
            </tr>
            <tr>
              <th scope="row"><label>Content line-height</label></th>
              <td><input type="number" name="styles[content_lh]" value="<?php echo esc_attr($cfg['styles']['content_lh']); ?>" min="0.8" max="3" step="0.05"  placeholder="e.g. 1.6" /></td>
            </tr>
            <tr>
              <th scope="row"><label>Content align</label></th>
              <td>
                <?php $ca = $cfg['styles']['content_align'] ?? ''; ?>
                <select name="styles[content_align]">
                  <option value="">— Inherit —</option>
                  <option value="left" <?php selected($ca==='left'); ?>>Left</option>
                  <option value="center" <?php selected($ca==='center'); ?>>Center</option>
                  <option value="right" <?php selected($ca==='right'); ?>>Right</option>
                </select>
              </td>
            </tr>
            <tr>
              <th scope="row"><label>Content width</label></th>
              <td>
                <input type="text" name="styles[content_w]" value="<?php echo esc_attr($cfg['styles']['content_w']); ?>" placeholder="e.g. 100%, 320px" style="width:160px;" />
                <input type="text" name="styles[content_min_w]" value="<?php echo esc_attr($cfg['styles']['content_min_w']); ?>" placeholder="min-width" style="width:160px;" />
                <input type="text" name="styles[content_max_w]" value="<?php echo esc_attr($cfg['styles']['content_max_w']); ?>" placeholder="max-width" style="width:160px;" />
              </td>
            </tr>
          </tbody>
        </table>
        </div>
      <?php endif; ?>

      <?php if (!empty($enabled['meta1']) || !empty($enabled['meta2']) || !empty($enabled['meta3'])): ?>
        <div class="cphub-group" style="margin-top:12px;">
        <h4>Meta</h4>
        <table class="form-table">
          <tbody>
            <tr>
              <th scope="row"><label>Meta color</label></th>
              <td><input type="text" name="styles[meta_color]" value="<?php echo esc_attr($cfg['styles']['meta_color']); ?>" class="regular-text" /></td>
            </tr>
            <tr>
              <th scope="row"><label>Meta size (px)</label></th>
              <td><input type="number" name="styles[meta_size]" value="<?php echo esc_attr($cfg['styles']['meta_size']); ?>" min="10" placeholder="(base - 2)" /></td>
            </tr>
            <tr>
              <th scope="row"><label>Meta margin (px)</label></th>
              <td>
                Top <input type="number" name="styles[meta_mt]" value="<?php echo esc_attr($cfg['styles']['meta_mt']); ?>" min="0" style="width:90px;" />
                Bottom <input type="number" name="styles[meta_mb]" value="<?php echo esc_attr($cfg['styles']['meta_mb']); ?>" min="0" style="width:90px;" />
              </td>
            </tr>
            <tr>
              <th scope="row"><label>Meta padding (px)</label></th>
              <td>
                V <input type="number" name="styles[meta_pad_v]" value="<?php echo esc_attr($cfg['styles']['meta_pad_v']); ?>" min="0" style="width:90px;" />
                H <input type="number" name="styles[meta_pad_h]" value="<?php echo esc_attr($cfg['styles']['meta_pad_h']); ?>" min="0" style="width:90px;" />
              </td>
            </tr>
            <tr>
              <th scope="row"><label>Meta line-height</label></th>
              <td><input type="number" name="styles[meta_lh]" value="<?php echo esc_attr($cfg['styles']['meta_lh']); ?>" min="0.8" max="3" step="0.05"  placeholder="e.g. 1.3" /></td>
            </tr>
            <tr>
              <th scope="row"><label>Meta align</label></th>
              <td>
                <?php $ma = $cfg['styles']['meta_align'] ?? ''; ?>
                <select name="styles[meta_align]">
                  <option value="">— Inherit —</option>
                  <option value="left" <?php selected($ma==='left'); ?>>Left</option>
                  <option value="center" <?php selected($ma==='center'); ?>>Center</option>
                  <option value="right" <?php selected($ma==='right'); ?>>Right</option>
                </select>
              </td>
            </tr>
            <tr>
              <th scope="row"><label>Meta width</label></th>
              <td>
                <input type="text" name="styles[meta_w]" value="<?php echo esc_attr($cfg['styles']['meta_w']); ?>" placeholder="e.g. 100%, 320px" style="width:160px;" />
                <input type="text" name="styles[meta_min_w]" value="<?php echo esc_attr($cfg['styles']['meta_min_w']); ?>" placeholder="min-width" style="width:160px;" />
                <input type="text" name="styles[meta_max_w]" value="<?php echo esc_attr($cfg['styles']['meta_max_w']); ?>" placeholder="max-width" style="width:160px;" />
              </td>
            </tr>
            <tr>
              <th scope="row"><label>Meta background</label></th>
              <td><input type="text" name="styles[meta_bg]" value="<?php echo esc_attr($cfg['styles']['meta_bg'] ?? ''); ?>" class="regular-text" placeholder="#fff, rgba(), var(--token)" /></td>
            </tr>
            <tr>
              <th scope="row"><label>Meta position + offsets</label></th>
              <td>
                <input type="text" name="styles[meta_pos]" value="<?php echo esc_attr($cfg['styles']['meta_pos'] ?? ''); ?>" placeholder="position (e.g. absolute)" style="width:180px;" />
                <input type="text" name="styles[meta_top]" value="<?php echo esc_attr($cfg['styles']['meta_top'] ?? ''); ?>" placeholder="top" style="width:120px;" />
                <input type="text" name="styles[meta_right]" value="<?php echo esc_attr($cfg['styles']['meta_right'] ?? ''); ?>" placeholder="right" style="width:120px;" />
                <input type="text" name="styles[meta_bottom]" value="<?php echo esc_attr($cfg['styles']['meta_bottom'] ?? ''); ?>" placeholder="bottom" style="width:120px;" />
                <input type="text" name="styles[meta_left]" value="<?php echo esc_attr($cfg['styles']['meta_left'] ?? ''); ?>" placeholder="left" style="width:120px;margin-top: 10px;" />
                <p class="description">Offsets accept any CSS (px, %, calc(), var()). If absolute, the nearest wrap is relative.</p>
              </td>
            </tr>
          </tbody>
        </table>
        </div>
      <?php endif; ?>

      <?php if (!empty($enabled['image'])): ?>
        <div class="cphub-group" style="margin-top:12px;">
        <h4>Image</h4>
        <table class="form-table">
          <tbody>
            <tr>
              <th scope="row"><label>Image radius (px)</label></th>
              <td><input type="number" name="styles[image_radius]" value="<?php echo esc_attr($cfg['styles']['image_radius']); ?>" min="0" placeholder="(inherit radius)" /></td>
            </tr>
            <tr>
              <th scope="row"><label>Image margin (px)</label></th>
              <td>
                Top <input type="number" name="styles[image_mt]" value="<?php echo esc_attr($cfg['styles']['image_mt']); ?>" min="0" style="width:90px;" />
                Bottom <input type="number" name="styles[image_mb]" value="<?php echo esc_attr($cfg['styles']['image_mb']); ?>" min="0" style="width:90px;" />
              </td>
            </tr>
            <tr>
              <th scope="row"><label>Image padding (px)</label></th>
              <td>
                V <input type="number" name="styles[image_pad_v]" value="<?php echo esc_attr($cfg['styles']['image_pad_v']); ?>" min="0" style="width:90px;" />
                H <input type="number" name="styles[image_pad_h]" value="<?php echo esc_attr($cfg['styles']['image_pad_h']); ?>" min="0" style="width:90px;" />
              </td>
            </tr>
            <tr>
              <th scope="row"><label>Image align</label></th>
              <td>
                <?php $ia = $cfg['styles']['image_align'] ?? ''; ?>
                <select name="styles[image_align]">
                  <option value="">— Inherit —</option>
                  <option value="left" <?php selected($ia==='left'); ?>>Left</option>
                  <option value="center" <?php selected($ia==='center'); ?>>Center</option>
                  <option value="right" <?php selected($ia==='right'); ?>>Right</option>
                </select>
              </td>
            </tr>
            <tr>
              <th scope="row"><label>Image width</label></th>
              <td>
                <input type="text" name="styles[image_w]" value="<?php echo esc_attr($cfg['styles']['image_w']); ?>" placeholder="e.g. 100%, 320px" style="width:160px;" />
                <input type="text" name="styles[image_min_w]" value="<?php echo esc_attr($cfg['styles']['image_min_w']); ?>" placeholder="min-width" style="width:160px;" />
                <input type="text" name="styles[image_max_w]" value="<?php echo esc_attr($cfg['styles']['image_max_w']); ?>" placeholder="max-width" style="width:160px;" />
              </td>
            </tr>
            <tr>
              <th scope="row"><label>Hover zoom</label></th>
              <td>
                <label style="margin-right:12px;"><input type="checkbox" name="styles[image_hover_scale_enable]" value="1" <?php checked(!empty($cfg['styles']['image_hover_scale_enable'])); ?> /> Enable image zoom on hover</label>
                <label>Scale
                  <input type="number" name="styles[image_hover_scale]" value="<?php echo esc_attr($cfg['styles']['image_hover_scale']); ?>" min="1" max="2" step="0.01" style="width:100px;" />
                </label>
                <label>Duration (ms)
                  <input type="number" name="styles[image_hover_duration]" value="<?php echo esc_attr($cfg['styles']['image_hover_duration']); ?>" min="0" style="width:120px;" />
                </label>
                <label>Easing
                  <input type="text" name="styles[image_hover_ease]" value="<?php echo esc_attr($cfg['styles']['image_hover_ease']); ?>" class="regular-text" placeholder="ease" />
                </label>
                <p class="description">Image smoothly scales within the thumbnail area; wrapper clips overflow.</p>
              </td>
            </tr>
          </tbody>
        </table>
        </div>
      <?php endif; ?>

      <?php if (!empty($enabled['button'])): ?>
        <div class="cphub-group" style="margin-top:12px;">
        <h4>Button</h4>
        <table class="form-table">
          <tbody>
            <tr>
              <th scope="row"><label>Button background</label></th>
              <td><input type="text" name="styles[button_bg]" value="<?php echo esc_attr($cfg['styles']['button_bg']); ?>" class="regular-text" placeholder="(inherit primary)" /></td>
            </tr>
            <tr>
              <th scope="row"><label>Button text color</label></th>
              <td><input type="text" name="styles[button_text]" value="<?php echo esc_attr($cfg['styles']['button_text']); ?>" class="regular-text" placeholder="#ffffff" /></td>
            </tr>
            <tr>
              <th scope="row"><label>Button radius (px)</label></th>
              <td><input type="number" name="styles[button_radius]" value="<?php echo esc_attr($cfg['styles']['button_radius']); ?>" min="0" placeholder="(inherit radius)" /></td>
            </tr>
            <tr>
              <th scope="row"><label>Button padding V (px)</label></th>
              <td><input type="number" name="styles[button_pad_v]" value="<?php echo esc_attr($cfg['styles']['button_pad_v']); ?>" min="0" /></td>
            </tr>
            <tr>
              <th scope="row"><label>Button padding H (px)</label></th>
              <td><input type="number" name="styles[button_pad_h]" value="<?php echo esc_attr($cfg['styles']['button_pad_h']); ?>" min="0" /></td>
            </tr>
            <tr>
              <th scope="row"><label>Stick to bottom</label></th>
              <td>
                <label><input type="checkbox" name="styles[button_stick_bottom]" value="1" <?php checked(!empty($cfg['styles']['button_stick_bottom'])); ?> /> Align button to bottom of the card</label>
                <p class="description">Makes the card a flex column and pushes the button to the bottom; button should be the last enabled element.</p>
              </td>
            </tr>
            <tr>
              <th scope="row"><label>Button margin (px)</label></th>
              <td>
                Top <input type="number" name="styles[button_mt]" value="<?php echo esc_attr($cfg['styles']['button_mt']); ?>" min="0" style="width:90px;" />
                Bottom <input type="number" name="styles[button_mb]" value="<?php echo esc_attr($cfg['styles']['button_mb']); ?>" min="0" style="width:90px;" />
              </td>
            </tr>
            <tr>
              <th scope="row"><label>Button align</label></th>
              <td>
                <?php $ba = $cfg['styles']['button_align'] ?? ''; ?>
                <select name="styles[button_align]">
                  <option value="">— Inherit —</option>
                  <option value="left" <?php selected($ba==='left'); ?>>Left</option>
                  <option value="center" <?php selected($ba==='center'); ?>>Center</option>
                  <option value="right" <?php selected($ba==='right'); ?>>Right</option>
                </select>
              </td>
            </tr>
            <tr>
              <th scope="row"><label>Button width</label></th>
              <td>
                <input type="text" name="styles[button_w]" value="<?php echo esc_attr($cfg['styles']['button_w']); ?>" placeholder="e.g. auto, 200px" style="width:160px;" />
                <input type="text" name="styles[button_min_w]" value="<?php echo esc_attr($cfg['styles']['button_min_w']); ?>" placeholder="min-width" style="width:160px;" />
                <input type="text" name="styles[button_max_w]" value="<?php echo esc_attr($cfg['styles']['button_max_w']); ?>" placeholder="max-width" style="width:160px;" />
              </td>
            </tr>
            <tr>
              <th scope="row"><label>Button full width</label></th>
              <td><label><input type="checkbox" name="styles[button_full]" value="1" <?php checked(!empty($cfg['styles']['button_full'])); ?> /> Make button 100% width</label></td>
            </tr>
            <tr>
              <th scope="row"><label>Button line-height</label></th>
              <td><input type="number" name="styles[button_lh]" value="<?php echo esc_attr($cfg['styles']['button_lh']); ?>" min="0.8" max="3" step="0.05"  placeholder="e.g. 1.0" /></td>
            </tr>
            <tr>
              <th scope="row"><label>Button shadow</label></th>
              <td>
                <label style="display:block;margin-bottom:6px;"><input type="checkbox" name="styles[button_shadow]" value="1" <?php checked(!empty($cfg['styles']['button_shadow'])); ?> /> Enable default shadow</label>
                <label>Custom (overrides default):
                  <input type="text" name="styles[button_shadow_css]" value="<?php echo esc_attr($cfg['styles']['button_shadow_css'] ?? ''); ?>" class="regular-text" placeholder="e.g. 0 4px 10px rgba(0,0,0,.15)" />
                </label>
              </td>
            </tr>
            <tr>
              <th scope="row"><label>Hover Ripple</label></th>
              <td>
                <label style="margin-right:12px;"><input type="checkbox" name="styles[button_ripple_enable]" value="1" <?php checked(!empty($cfg['styles']['button_ripple_enable'])); ?> /> Enable ripple effect</label>
                <label>Color <input type="text" name="styles[button_ripple_color]" value="<?php echo esc_attr($cfg['styles']['button_ripple_color']); ?>" class="regular-text" placeholder="(inherit button/bg)" /></label>
                <label>Opacity <input type="number" name="styles[button_ripple_opacity]" value="<?php echo esc_attr($cfg['styles']['button_ripple_opacity']); ?>" min="0" max="1" step="0.05" style="width:90px;" /></label>
                <label>Scale <input type="number" name="styles[button_ripple_scale]" value="<?php echo esc_attr($cfg['styles']['button_ripple_scale']); ?>" min="1" max="4" step="0.05" style="width:90px;" /></label>
                <label>Duration (ms) <input type="number" name="styles[button_ripple_duration]" value="<?php echo esc_attr($cfg['styles']['button_ripple_duration']); ?>" min="0" style="width:120px;" /></label>
                <label>Easing <input type="text" name="styles[button_ripple_ease]" value="<?php echo esc_attr($cfg['styles']['button_ripple_ease']); ?>" class="regular-text" placeholder="ease" /></label>
                <p class="description">Ripple expands from hover point (in preview) or center (on sites without JS).</p>
              </td>
            </tr>
          </tbody>
        </table>
        </div>
      <?php endif; ?>
        </div>
      </details>
    </div>
  </div>

  <div style="margin-top:16px;">
    <h3>Responsive Visibility</h3>
    <?php
      // Build responsive visibility defaults
      $enabled_tab = isset($cfg['layout']['enabled_tab']) && is_array($cfg['layout']['enabled_tab']) ? $cfg['layout']['enabled_tab'] : [];
      $enabled_mob = isset($cfg['layout']['enabled_mob']) && is_array($cfg['layout']['enabled_mob']) ? $cfg['layout']['enabled_mob'] : [];
      $meta_any = !empty($enabled['meta1']) || !empty($enabled['meta2']) || !empty($enabled['meta3']);
      if (!$enabled_tab) {
        $enabled_tab = [ 'title'=>!empty($enabled['title']), 'image'=>!empty($enabled['image']), 'excerpt'=>!empty($enabled['excerpt']), 'content'=>!empty($enabled['content']), 'meta'=>$meta_any, 'button'=>!empty($enabled['button']) ];
      }
      if (!$enabled_mob) { $enabled_mob = $enabled_tab; }
      $rows = [ 'title'=>'Title', 'image'=>'Image', 'excerpt'=>'Excerpt', 'content'=>'Content', 'meta'=>'Meta', 'button'=>'Button' ];
    ?>
    <table class="widefat" style="max-width:700px;">
      <thead>
        <tr>
          <th>Element</th>
          <th style="width:20%">Tablet</th>
          <th style="width:20%">Mobile</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $rk => $rl): ?>
        <tr>
          <td><?php echo esc_html($rl); ?></td>
          <td><label><input type="checkbox" name="layout[enabled_tab][<?php echo esc_attr($rk); ?>]" value="1" <?php checked(!empty($enabled_tab[$rk])); ?>> Show</label></td>
          <td><label><input type="checkbox" name="layout[enabled_mob][<?php echo esc_attr($rk); ?>]" value="1" <?php checked(!empty($enabled_mob[$rk])); ?>> Show</label></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <p><button class="button button-primary">Save Styles</button></p>
</form>

<h3>Preview</h3>
<p class="description">Preview shows up to 6 latest posts for this CPT. Saved styles are applied.</p>
<?php
// Build a preview using up to 6 latest posts
$q = new WP_Query(['post_type' => $active_cpt, 'post_status' => 'publish', 'posts_per_page' => 6]);
if ($q->have_posts()) {
  $items = [];
  while ($q->have_posts()) { $q->the_post(); $items[] = $this->map_post_to_feed_item(get_post()); }
  wp_reset_postdata();
  $styles_for_css = $cfg['styles'];
  // inject responsive vis maps from earlier
  $styles_for_css['__vis_tab'] = $enabled_tab;
  $styles_for_css['__vis_mob'] = $enabled_mob;
  $css = $this->build_styles_css($styles_for_css);
  echo '<style>' . $css . '</style>';
  $layout_type = ($cfg['styles']['layout_type'] ?? 'list') === 'grid' ? 'grid' : 'list';
  echo '<div class="cphub-' . esc_attr($layout_type) . '">';
  foreach ($items as $item) {
    $thumb_html = '';
    $content_html = '';
    foreach ($order as $k) {
      if (empty($allowed[$k]) || empty($enabled[$k])) continue;
      switch ($k) {
        case 'image':
          if (!empty($item['thumb'])) $thumb_html .= '<img class="cphub-img" src="' . esc_url($item['thumb']) . '" alt="" />';
          break;
        case 'title':
          $content_html .= '<h3 class="cphub-title"><a href="#">' . esc_html($item['title']) . '</a></h3>';
          break;
        case 'excerpt':
          $content_html .= '<div class="cphub-excerpt">' . wp_kses_post($item['excerpt']) . '</div>';
          break;
        case 'content':
          $content_html .= '<div class="cphub-content">' . wp_kses_post($item['content']) . '</div>';
          break;
        case 'meta1':
        case 'meta2':
        case 'meta3':
          $key = $meta_keys[$k] ?? '';
          if ($key && isset($item['meta'][$key])) {
            $place = $meta_wrap[$k] ?? 'content';
            $meta_html = '';
            $is_media = isset($field_index[$key]['type']) && $field_index[$key]['type'] === 'media';
            if ($is_media) {
              $url = $item['meta'][$key . '_url'] ?? '';
              $mime = $item['meta'][$key . '_mime'] ?? '';
              $media_type = $field_index[$key]['media_type'] ?? '';
              if ($url) {
                if ($media_type === 'image' || (is_string($mime) && strpos($mime, 'image/') === 0)) {
                  $meta_html = '<div class="cphub-meta"><img class="cphub-meta-media" src="' . esc_url($url) . '" alt="" /></div>';
                } else {
                  $meta_html = '<div class="cphub-meta"><a class="cphub-meta-file" href="' . esc_url($url) . '" target="_blank" rel="noopener">Download</a></div>';
                }
              } else {
                // Fallback to raw value (likely ID) if URL missing
                $meta_html = '<div class="cphub-meta">' . esc_html((string)$item['meta'][$key]) . '</div>';
              }
            } else {
              $meta_html = '<div class="cphub-meta">' . esc_html((string)$item['meta'][$key]) . '</div>';
            }
            if ($place === 'thumb') { $thumb_html .= $meta_html; } else { $content_html .= $meta_html; }
          }
          break;
        case 'button':
          if (!empty($cfg['styles']['button_ripple_enable'])) {
            $content_html .= '<a class="cphub-btn has-hover" href="#">'
              . '<span class="cphub-btn-inner">'
              .   '<span class="cphub-btn-base">'
              .     '<span class="cphub-btn-text">Read More</span>'
              .   '</span>'
              . '</span>'
              . '<span class="cphub-btn-hover"></span>'
              . '</a>';
          } else {
            $content_html .= '<a class="cphub-btn" href="#">Read More</a>';
          }
          break;
      }
    }
    echo '<div class="cphub-card">';
    echo '<div class="cphub-thumb-wrap">' . $thumb_html . '</div>';
    echo '<div class="cphub-content-wrap">' . $content_html . '</div>';
    echo '</div>';
  }
  echo '</div>';
} else {
  echo '<p><em>No posts found for this CPT.</em></p>';
}
?>



<script>
  jQuery(function($) {
    $('#cphub-style-order').sortable({
      handle: '.dashicons-move',
      placeholder: 'ui-state-highlight'
    });
    function toggleGridOpts(){
      var type = $('input[name="styles[layout_type]"]:checked').val();
      $('.cphub-grid-opts').toggle(type==='grid');
    }
    $(document).on('change','input[name="styles[layout_type]"]', toggleGridOpts);
    toggleGridOpts();
  });
</script>
