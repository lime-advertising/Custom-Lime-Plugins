<?php

function render_update_review_ids_page()
{
    if (!function_exists('get_field') || !function_exists('update_field')) {
        echo '<div class="notice notice-error"><p>ACF is not active.</p></div>';
        return;
    }

    $log = [];

    if (isset($_POST['update_hd_ids'])) {
        $args = [
            'post_type' => 'product',
            'posts_per_page' => -1,
            'fields' => 'ids', // more efficient
        ];

        $query = new WP_Query($args);
        $updated = 0;

        if ($query->have_posts()) {
            foreach ($query->posts as $post_id) {
                $show_reviews = get_field('show_reviews', $post_id);
                $title = get_the_title($post_id);
                $link = get_field('the_home_depot', $post_id);
                $matched_id = null;
                $status = '—';

                if (!$show_reviews) {
                    $status = '❌ Reviews disabled';
                } elseif (!$link) {
                    $status = 'Missing Home Depot link';
                } elseif (preg_match('/(\\d{7,})$/', $link, $matches)) {
                    $matched_id = $matches[1];
                    update_field('hd_product_id', $matched_id, $post_id);
                    $status = '✅ Updated';
                    $updated++;
                } else {
                    $status = '⚠️ No valid ID in link';
                }

                $log[] = [
                    'title' => $title,
                    'show_reviews' => $show_reviews ? '✔️' : '✖️',
                    'link' => $link ?: '—',
                    'product_id' => $matched_id ?: '—',
                    'status' => $status
                ];
            }

            echo '<div class="notice notice-success"><p>' . $updated . ' products updated successfully.</p></div>';
        } else {
            echo '<div class="notice notice-warning"><p>No products found.</p></div>';
        }
    }

?>
    <div class="wrap">
        <h1>Update Home Depot Product Review IDs</h1>
        <form method="post">
            <p>This will scan all products and only update ones where <strong>show_reviews</strong> is enabled and <strong>the_home_depot</strong> contains a valid ID.</p>
            <p><input type="submit" name="update_hd_ids" value="Update Product Review IDs" class="button button-primary" /></p>
        </form>

        <?php if (!empty($log)) : ?>
            <h2>Execution Log</h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Show Reviews?</th>
                        <th>Home Depot Link</th>
                        <th>Extracted ID</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($log as $entry) : ?>
                        <tr>
                            <td><?= esc_html($entry['title']) ?></td>
                            <td><?= esc_html($entry['show_reviews']) ?></td>
                            <td><small><?= esc_html($entry['link']) ?></small></td>
                            <td><?= esc_html($entry['product_id']) ?></td>
                            <td><?= esc_html($entry['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
<?php
}
