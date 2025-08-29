<?php
if (!defined('ABSPATH')) exit;
get_header();
$tpl_id = isset($GLOBALS['cphub_el_archive_tpl_id']) ? intval($GLOBALS['cphub_el_archive_tpl_id']) : 0;
if ($tpl_id > 0 && class_exists('Elementor\\Plugin')) {
    echo do_shortcode('[elementor-template id="' . $tpl_id . '"]');
} else {
    echo '<div class="wrap"><div class="container"><p>No archive template configured.</p></div></div>';
}
get_footer();
?>
