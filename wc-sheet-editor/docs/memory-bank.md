# 📓 Memory Bank – WooCommerce Sheet Editor

## What It Is

A custom WordPress plugin providing a **spreadsheet-style editor for WooCommerce products**.
Instead of import/export CSV files, managers edit products directly in an inline grid UI and save changes in batch via REST API.

## Key Features

* Admin menu: **Products → Sheet Editor**
* DataTables grid with horizontal scroll and FixedColumns (first 3 columns frozen)
* Inline editing for:

  * Name
  * SKU
  * Regular/Sale Price
  * Stock Status + Quantity
  * Product Status
  * Categories (comma-separated names)
  * ACF fields (auto-detected: text, textarea, email, url, number, true/false, select, radio)
* Pagination (50 products per page by default).
* REST API endpoint (`/wcse/v1/products`) to fetch & update products.
* Column visibility panel (persisted via `wcse/v1/settings`).
* Secure: requires `manage_woocommerce` capability + REST nonce.
* Assets (JS/CSS) bundled inside the plugin.
* Translation-ready (`wc-sheet-editor` text domain).
* WordPress-standard plugin structure (split into `includes/`, `assets/`, `languages/`).

## Why It Exists

* To **replace CSV import/export** workflow with direct in-dashboard product editing.
* To reduce errors from column mismatches.
* To make catalog maintenance faster and friendlier for non-technical staff.

## Limitations

* Only covers core product fields listed above.
* Variations and advanced bulk operations are not yet included.
* Complex ACF fields (checkbox multi, taxonomy multi, relationship, repeater/group) are not yet implemented.
* Uses DataTables (CDN). You can vendor assets locally if needed.
