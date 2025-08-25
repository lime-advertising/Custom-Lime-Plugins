<?php
// Variables available: $feed, $example_url, $example_query
?>

<h2 class="title">Feed Settings</h2>
<form method="post" action="options.php" class="card" style="padding:1em;max-width:900px;">
    <?php settings_fields(CPT_Hub_Publisher::OPTION_FEED === CPT_Hub_Publisher::OPTION_FEED ? 'cphub_feed' : 'cphub_feed'); ?>
    <?php $fs = get_option(CPT_Hub_Publisher::OPTION_FEED, []); ?>
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row"><label for="items_per_feed">Items per feed</label></th>
            <td><input id="items_per_feed" name="<?php echo esc_attr(CPT_Hub_Publisher::OPTION_FEED); ?>[items_per_feed]" type="number" min="1" value="<?php echo esc_attr($fs['items_per_feed'] ?? 20); ?>"> <span class="description">Default limit (override with <code>&n=NN</code> in URL)</span></td>
        </tr>
        <tr>
            <th scope="row"><label for="secret_key">Optional secret key</label></th>
            <td>
                <input id="secret_key" name="<?php echo esc_attr(CPT_Hub_Publisher::OPTION_FEED); ?>[secret_key]" type="text" class="regular-text" value="<?php echo esc_attr($fs['secret_key'] ?? ''); ?>">
                <p class="description">If set, consumers must include <code>&key=YOUR_KEY</code> (or pretty URL <code>/feed/cphub/&lt;cpt&gt;?key=...</code>).</p>
            </td>
        </tr>
    </table>
    <p><button class="button button-primary">Save Feed Settings</button></p>
</form>

<h2 class="title" style="margin-top:2em;">How to Consume</h2>
<p>Each CPT has its own feed:
    <br>Pretty URL: <code><?php echo esc_html($example_url); ?></code>
    <br>Query URL: <code><?php echo esc_html($example_query); ?></code>
    <br>Add <code>&n=50</code> to change page size. Paginate with <code>&paged=2</code>. Filter by update date with <code>&modified_since=2025-01-01</code> (YYYY-MM-DD).
    <br>Target a location with <code>&location=your-location</code> (includes items tagged that location or <code>all-locations</code>).
</p>
