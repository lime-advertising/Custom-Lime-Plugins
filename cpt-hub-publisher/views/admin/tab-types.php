<?php
// Variables available from parent scope: $types, $feed_base, $edit_slug, $edit_def, $taxes
?>

<h2 class="title">Your Content Types</h2>
<table class="widefat striped">
    <thead>
        <tr>
            <th>Slug</th>
            <th>Label</th>
            <th>Supports</th>
            <th>Archive</th>
            <th>Feed</th>
            <th style="width:120px">Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!$types): ?>
            <tr>
                <td colspan="6">No custom post types yet. Add one below.</td>
            </tr>
            <?php else: foreach ($types as $slug => $def): ?>
                <tr>
                    <td><code><?php echo esc_html($slug); ?></code></td>
                    <td><?php echo esc_html($def['label']); ?></td>
                    <td><?php echo esc_html(implode(', ', $def['supports'])); ?></td>
                    <td><?php echo !empty($def['has_archive']) ? 'Yes' : 'No'; ?></td>
                    <td>
                        <div>Pretty: <a href="<?php echo esc_url(trailingslashit($feed_base) . $slug); ?>" target="_blank">/feed/cphub/<?php echo esc_html($slug); ?></a></div>
                        <div>Query: <a href="<?php echo esc_url(add_query_arg(['feed' => 'cphub', 'cpt' => $slug], home_url('/'))); ?>" target="_blank">?feed=cphub&amp;cpt=<?php echo esc_html($slug); ?></a></div>
                    </td>
                    <td>
                        <a class="button" href="<?php echo esc_url(add_query_arg(['page' => 'cphub', 'tab' => 'types', 'edit' => $slug], admin_url('admin.php'))); ?>">Edit</a>
                        <form method="post" style="display:inline">
                            <?php wp_nonce_field(CPT_Hub_Publisher::NONCE_ACTION); ?>
                            <input type="hidden" name="cphub_action" value="delete">
                            <input type="hidden" name="slug" value="<?php echo esc_attr($slug); ?>">
                            <button class="button button-link-delete" onclick="return confirm('Delete this CPT? Posts will remain registered under their type until you remove them manually.');">Delete</button>
                        </form>
                    </td>
                </tr>
        <?php endforeach;
        endif; ?>
    </tbody>
    </table>

<?php if ($edit_def): ?>
<h2 class="title" style="margin-top:2em;">Edit Type</h2>
<form method="post" class="card" style="padding:1em;max-width:100%;">
    <?php wp_nonce_field(CPT_Hub_Publisher::NONCE_ACTION); ?>
    <input type="hidden" name="cphub_action" value="update">
    <input type="hidden" name="slug" value="<?php echo esc_attr($edit_slug); ?>">
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row">Slug</th>
            <td><code><?php echo esc_html($edit_slug); ?></code> <span class="description">Slug cannot be changed here.</span></td>
        </tr>
        <tr>
            <th scope="row"><label for="cphub_label_edit">Label</label></th>
            <td><input id="cphub_label_edit" name="label" type="text" class="regular-text" value="<?php echo esc_attr($edit_def['label']); ?>" required></td>
        </tr>
        <tr>
            <th scope="row">Supports</th>
            <td>
                <?php $opts = ['title' => 'Title', 'editor' => 'Editor', 'excerpt' => 'Excerpt', 'thumbnail' => 'Featured Image', 'custom-fields' => 'Custom Fields'];
                foreach ($opts as $key => $lab): ?>
                    <label style="margin-right:1em;"><input type="checkbox" name="supports[]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, (array)$edit_def['supports'], true)); ?>> <?php echo esc_html($lab); ?></label>
                <?php endforeach; ?>
            </td>
        </tr>
        <tr>
            <th scope="row">Has Archive</th>
            <td><label><input type="checkbox" name="has_archive" value="1" <?php checked(!empty($edit_def['has_archive'])); ?>> Enable archive</label></td>
        </tr>
        <tr>
            <th scope="row">Assigned Taxonomies</th>
            <td>
                <?php if (!$taxes) { echo '<em>No custom taxonomies yet. Add some in the Taxonomies tab.</em>'; }
                foreach ($taxes as $t_slug => $t_def): ?>
                    <label style="margin-right:1em; display:inline-block;">
                        <input type="checkbox" name="taxonomies[]" value="<?php echo esc_attr($t_slug); ?>" <?php checked(in_array($t_slug, (array)($edit_def['taxonomies'] ?? []), true)); ?>>
                        <?php echo esc_html($t_def['label']); ?> <code>(<?php echo esc_html($t_slug); ?>)</code>
                    </label>
                <?php endforeach; ?>
            </td>
        </tr>
        <tr>
            <th scope="row">Custom Meta Fields</th>
            <td>
                <p class="description">Define fields stored as public post meta and exposed in the feed. Avoid leading underscores in keys.</p>
                
                <table class="widefat" style="max-width:860px;">
                    <thead>
                        <tr>
                            <th style="width:20%;padding-left:12px;">Key</th>
                            <th style="width:30%;padding-left:12px;">Label</th>
                            <th style="width:20%;padding-left:12px;">Type</th>
                            <th class="cphub-col-extra" style="padding-left:12px;">Field Options</th>
                        </tr>
                    </thead>
                    <tbody id="cphub-fields-rows">
                        <?php $fields = isset($edit_def['fields']) && is_array($edit_def['fields']) ? $edit_def['fields'] : []; ?>
                        <?php if (!$fields) $fields = []; ?>
                        <?php foreach ($fields as $f): ?>
                            <tr>
                                <td><input type="text" name="fields[key][]" value="<?php echo esc_attr($f['key']); ?>" placeholder="e.g. price" /></td>
                                <td><input type="text" name="fields[label][]" value="<?php echo esc_attr($f['label']); ?>" placeholder="e.g. Price" /></td>
                                <td>
                                    <select name="fields[type][]">
                                        <?php $typesel = $f['type'] ?? 'text';
                                        foreach (['text'=>'Text','textarea'=>'Textarea','number'=>'Number','url'=>'URL','select'=>'Select','media'=>'Media','wysiwyg'=>'WYSIWYG'] as $tk=>$tv): ?>
                                            <option value="<?php echo esc_attr($tk); ?>" <?php selected($typesel === $tk); ?>><?php echo esc_html($tv); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td class="cphub-field-extra">
                                    <input type="text" class="cphub-extra-select" name="fields[options][]" value="<?php echo esc_attr(isset($f['options']) ? implode(', ', (array)$f['options']) : ''); ?>" placeholder="Red, Green, Blue" />
                                    <?php $mt = isset($f['media_type']) ? $f['media_type'] : 'file'; ?>
                                    <select class="cphub-extra-media" name="fields[media_type][]">
                                        <option value="file" <?php selected($mt==='file'); ?>>Any File</option>
                                        <option value="image" <?php selected($mt==='image'); ?>>Image Only</option>
                                    </select>
                                    <button type="button" class="button button-link-delete cphub-remove-field" style="float:right;">Remove</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <!-- blank row template -->
                        <tr class="cphub-field-template" style="display:none;">
                            <td><input type="text" name="fields[key][]" placeholder="e.g. price" /></td>
                            <td><input type="text" name="fields[label][]" placeholder="e.g. Price" /></td>
                            <td>
                                <select name="fields[type][]">
                                    <option value="text">Text</option>
                                    <option value="textarea">Textarea</option>
                                    <option value="number">Number</option>
                                    <option value="url">URL</option>
                                    <option value="select">Select</option>
                                    <option value="media">Media</option>
                                    <option value="wysiwyg">WYSIWYG</option>
                                </select>
                            </td>
                            <td class="cphub-field-extra">
                                <input type="text" class="cphub-extra-select" name="fields[options][]" placeholder="Red, Green, Blue" />
                                <select class="cphub-extra-media" name="fields[media_type][]">
                                    <option value="file">Any File</option>
                                    <option value="image">Image Only</option>
                                </select>
                                <button type="button" class="button button-link-delete cphub-remove-field" style="float:right;">Remove</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p><button type="button" class="button" id="cphub-add-field">Add field</button></p>
                <script>
                    (function(){
                        var btn = document.getElementById('cphub-add-field');
                        if (!btn) return;
                        function wireRow(row){
                            var sel = row.querySelector('select[name="fields[type][]"]');
                            var extraCell = row.querySelector('.cphub-field-extra');
                            if (!sel || !extraCell) return;
                            var optInput = extraCell.querySelector('.cphub-extra-select');
                            var mediaSelect = extraCell.querySelector('.cphub-extra-media');
                            var removeBtn = extraCell.querySelector('.cphub-remove-field');
                            function apply(){
                                // Keep the Field Options cell visible at all times so Remove stays visible.
                                extraCell.style.display = '';
                                if (sel.value === 'select') {
                                    if (optInput) { optInput.style.display=''; optInput.disabled=false; }
                                    if (mediaSelect) { mediaSelect.style.display='none'; mediaSelect.disabled=true; }
                                } else if (sel.value === 'media') {
                                    if (optInput) { optInput.style.display='none'; optInput.disabled=true; }
                                    if (mediaSelect) { mediaSelect.style.display=''; mediaSelect.disabled=false; }
                                } else {
                                    if (optInput) { optInput.style.display='none'; optInput.disabled=true; }
                                    if (mediaSelect) { mediaSelect.style.display='none'; mediaSelect.disabled=true; }
                                }
                            }
                            sel.addEventListener('change', apply);
                            apply();
                            if (removeBtn) {
                                removeBtn.addEventListener('click', function(){
                                    if (confirm('Remove this field?')) {
                                        row.parentNode.removeChild(row);
                                    }
                                });
                            }
                        }
                        document.querySelectorAll('#cphub-fields-rows > tr:not(.cphub-field-template)')
                            .forEach(function(r){ wireRow(r); });
                        btn.addEventListener('click', function(){
                            var tbody = document.getElementById('cphub-fields-rows');
                            var tpl = tbody.querySelector('.cphub-field-template');
                            if (!tpl) return;
                            var clone = tpl.cloneNode(true);
                            clone.style.display = '';
                            clone.classList.remove('cphub-field-template');
                            tbody.appendChild(clone);
                            wireRow(clone);
                        });
                    })();
                </script>
            </td>
        </tr>
    </table>
    <p>
        <button class="button button-primary">Save Changes</button>
        <a class="button button-link" href="<?php echo esc_url(remove_query_arg('edit')); ?>">Cancel</a>
    </p>
</form>
<?php endif; ?>

<h2 class="title" style="margin-top:2em;">Add New Type</h2>
<form method="post" class="card" style="padding:1em;max-width:100%;">
    <?php wp_nonce_field(CPT_Hub_Publisher::NONCE_ACTION); ?>
    <input type="hidden" name="cphub_action" value="add">
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row"><label for="cphub_slug">Slug</label></th>
            <td><input id="cphub_slug" name="slug" type="text" class="regular-text" placeholder="e.g. slides, recipes, dealers" required></td>
        </tr>
        <tr>
            <th scope="row"><label for="cphub_label">Label</label></th>
            <td><input id="cphub_label" name="label" type="text" class="regular-text" placeholder="Plural label shown in admin" required></td>
        </tr>
        <tr>
            <th scope="row">Supports</th>
            <td>
                <?php
                $opts = ['title' => 'Title', 'editor' => 'Editor', 'excerpt' => 'Excerpt', 'thumbnail' => 'Featured Image', 'custom-fields' => 'Custom Fields'];
                foreach ($opts as $key => $lab): ?>
                    <label style="margin-right:1em;"><input type="checkbox" name="supports[]" value="<?php echo esc_attr($key); ?>"> <?php echo esc_html($lab); ?></label>
                <?php endforeach; ?>
            </td>
        </tr>
        <tr>
            <th scope="row">Has Archive</th>
            <td><label><input type="checkbox" name="has_archive" value="1"> Enable archive</label></td>
        </tr>
    </table>
    <p><button class="button button-primary">Add Type</button></p>
</form>
