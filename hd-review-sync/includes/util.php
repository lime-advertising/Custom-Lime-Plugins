<?php
function hd_extract_product_id($url) {
    if (preg_match('/\/(\d{10})$/', $url, $matches)) {
        return $matches[1];
    }
    return false;
}
