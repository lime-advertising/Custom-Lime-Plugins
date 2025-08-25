<?php
// Variables available: $taxes, $tax_edit_slug, $tax_edit_def
?>

<h2 class="title">Custom Taxonomies</h2>
<table class="widefat striped" style="max-width:900px;">
    <thead>
        <tr>
            <th>Slug</th>
            <th>Label</th>
            <th>Hierarchical</th>
            <th style="width:180px">Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!$taxes): ?>
            <tr><td colspan="4">No taxonomies yet. Add one below.</td></tr>
        <?php else: foreach ($taxes as $t_slug => $t_def): ?>
            <tr>
                <td><code><?php echo esc_html($t_slug); ?></code></td>
                <td><?php echo esc_html($t_def['label']); ?></td>
                <td><?php echo !empty($t_def['hierarchical']) ? 'Yes' : 'No'; ?></td>
                <td>
                    <a class="button" href="<?php echo esc_url(add_query_arg(['page' => 'cphub', 'tab' => 'tax', 'tax_edit' => $t_slug], admin_url('admin.php'))); ?>">Edit</a>
                    <form method="post" style="display:inline">
                        <?php wp_nonce_field(CPT_Hub_Publisher::NONCE_ACTION); ?>
                        <input type="hidden" name="cphub_action" value="tax_delete">
                        <input type="hidden" name="slug" value="<?php echo esc_attr($t_slug); ?>">
                        <button class="button button-link-delete" onclick="return confirm('Delete this taxonomy? Terms will remain in the database but may become orphaned.');">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; endif; ?>
    </tbody>
</table>

<?php if ($tax_edit_def): ?>
<h3 class="title" style="margin-top:1em;">Edit Taxonomy</h3>
<form method="post" class="card" style="padding:1em;max-width:900px;">
    <?php wp_nonce_field(CPT_Hub_Publisher::NONCE_ACTION); ?>
    <input type="hidden" name="cphub_action" value="tax_update">
    <input type="hidden" name="slug" value="<?php echo esc_attr($tax_edit_slug); ?>">
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row">Slug</th>
            <td><code><?php echo esc_html($tax_edit_slug); ?></code> <span class="description">Slug cannot be changed here.</span></td>
        </tr>
        <tr>
            <th scope="row"><label for="cphub_tax_label_edit">Label</label></th>
            <td><input id="cphub_tax_label_edit" name="label" type="text" class="regular-text" value="<?php echo esc_attr($tax_edit_def['label']); ?>" required></td>
        </tr>
        <tr>
            <th scope="row">Hierarchical</th>
            <td><label><input type="checkbox" name="hierarchical" value="1" <?php checked(!empty($tax_edit_def['hierarchical'])); ?>> Category-like (unchecked = tag-like)</label></td>
        </tr>
    </table>
    <p>
        <button class="button button-primary">Save Taxonomy</button>
        <a class="button button-link" href="<?php echo esc_url(remove_query_arg('tax_edit')); ?>">Cancel</a>
    </p>
    </form>
<?php endif; ?>

<h3 class="title" style="margin-top:1em;">Add Taxonomy</h3>
<form method="post" class="card" style="padding:1em;max-width:900px;">
    <?php wp_nonce_field(CPT_Hub_Publisher::NONCE_ACTION); ?>
    <input type="hidden" name="cphub_action" value="tax_add">
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row"><label for="cphub_tax_slug">Slug</label></th>
            <td><input id="cphub_tax_slug" name="slug" type="text" class="regular-text" placeholder="e.g. brand, region" required></td>
        </tr>
        <tr>
            <th scope="row"><label for="cphub_tax_label">Label</label></th>
            <td><input id="cphub_tax_label" name="label" type="text" class="regular-text" placeholder="Plural label shown in admin" required></td>
        </tr>
        <tr>
            <th scope="row">Hierarchical</th>
            <td><label><input type="checkbox" name="hierarchical" value="1"> Category-like (unchecked = tag-like)</label></td>
        </tr>
    </table>
    <p><button class="button button-primary">Add Taxonomy</button></p>
</form>

<h2 class="title" style="margin-top:2em;">Locations</h2>
<p class="description">Manage URL metadata for each <code>location</code> term. Slugs are used by Consumers to filter content.</p>
<table class="widefat striped" style="max-width:1000px;">
  <thead>
    <tr>
      <th style="width:20%">Name</th>
      <th style="width:18%">Slug</th>
      <th>URL</th>
      <th style="width:160px">Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php
    $loc_terms = get_terms([
      'taxonomy'   => 'location',
      'hide_empty' => false,
    ]);
    if (is_wp_error($loc_terms) || !$loc_terms) {
        echo '<tr><td colspan="4">No location terms found.</td></tr>';
    } else {
        foreach ($loc_terms as $t) {
            $url = get_term_meta($t->term_id, 'cphub_location_url', true);
            ?>
            <tr>
              <td><?php echo esc_html($t->name); ?></td>
              <td><code><?php echo esc_html($t->slug); ?></code></td>
              <td>
                <form method="post">
                  <?php wp_nonce_field(CPT_Hub_Publisher::NONCE_ACTION); ?>
                  <input type="hidden" name="cphub_action" value="loc_update">
                  <input type="hidden" name="term_id" value="<?php echo (int)$t->term_id; ?>">
                  <input type="url" name="url" class="regular-text" style="width:100%;max-width:520px;" value="<?php echo esc_attr($url); ?>" placeholder="https://example.com/" />
              </td>
              <td>
                  <button class="button button-primary" type="submit">Save URL</button>
                  <?php if ($url): ?>
                  <a class="button" target="_blank" href="<?php echo esc_url($url); ?>">Visit</a>
                  <?php endif; ?>
                </form>
              </td>
            </tr>
            <?php
        }
    }
    ?>
  </tbody>
</table>
