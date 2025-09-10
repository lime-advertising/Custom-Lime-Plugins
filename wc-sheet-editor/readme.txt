=== WooCommerce Sheet Editor ===
Contributors: lime
Tags: woocommerce, products, bulk edit, spreadsheet, admin
Requires at least: 6.2
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Spreadsheet-like grid to edit WooCommerce products directly in WP Admin.

== Description ==
Edit product fields (name, SKU, regular/sale price, stock, status, categories) in a fast grid. Save changes in batch via a secure REST endpoint.

== Installation ==
1. Upload `wc-sheet-editor` to `/wp-content/plugins/`.
2. Activate in **Plugins**.
3. Go to **Products → Sheet Editor**.

== Frequently Asked Questions ==
= Who can use it? =
Admins with the `manage_woocommerce` capability.

== Changelog ==
= 0.2.0 =
* DataTables grid with horizontal scroll, FixedColumns (first 3 frozen).
* Column visibility panel; persisted via `wcse/v1/settings`.
* Tokenized Categories with suggestions from `wp/v2/product_cat`.
* Dropdowns for stock status and product status.
* Automatic ACF detection (text, textarea, email, url, number, true/false, select, radio) with inline editing.
* Quantity enforced as integer; prices as floats; stricter REST validation.
* Fixed recursive draw loop causing stack overflow.

= 0.1.0 =
* Initial release.
