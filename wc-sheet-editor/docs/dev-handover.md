# 🛠 Dev Handover Document – WooCommerce Sheet Editor

## Folder Layout

```
wc-sheet-editor/
├─ wc-sheet-editor.php   (main bootstrapper)
├─ uninstall.php         (cleanup placeholder)
├─ includes/
│  ├─ Plugin.php         (singleton loader, hooks)
│  ├─ Admin_Page.php     (submenu + UI renderer + asset loader)
│  ├─ Rest_Controller.php (GET/POST REST routes + settings)
│  └─ Helpers.php
├─ assets/
│  ├─ js/admin.js        (grid rendering, editing, saving, column visibility)
│  └─ css/admin.css      (basic styles)
├─ languages/            (translation .pot/.po files)
└─ readme.txt            (WP-org style description)
```

## How It Works

1. **Bootstrap (`wc-sheet-editor.php`)**

   - Loads text domain.
   - Checks WooCommerce is active.
   - Instantiates `WCSE\Plugin`.

2. **Admin UI (`Admin_Page.php`)**

   - Adds submenu under Products.
   - Enqueues `admin.js` and `admin.css`.
   - Passes REST root, nonce, and i18n strings to JS.

3. **REST API (`Rest_Controller.php`)**

   - `GET /wcse/v1/products`: queries products, returns paged list of product rows and detected ACF fields.
   - `POST /wcse/v1/products`: batch updates multiple products and ACF fields.
   - `GET|POST /wcse/v1/settings`: read/save column visibility (`visible_fields` option).
   - Uses WooCommerce CRUD (`wc_get_product`, `$product->set_*()`, `$product->save()`).

4. **JS Grid (`assets/js/admin.js`)**

   - DataTables powered table (Responsive disabled, FixedColumns for first 3 columns, horizontal scroll).
   - Loads products with pagination, renders editable cells, tracks dirty rows in a `Map`.
   - Dropdowns for status and stock status; tokenized categories with suggestions.
   - Automatic ACF detection (text, textarea, email, url, number, true_false, select, radio) and inline editing.
   - Columns panel to toggle visible fields (core and ACF) persisted via REST `/settings`.
   - Delegated event listeners ensure editing works after table redraws.

5. **CSS (`assets/css/admin.css`)**

   - Table styles, min-widths per column, FixedColumns tweaks, tokenized categories, dirty row highlighting.

## Security

- Capability: `manage_woocommerce`.
- Nonce: `wp_rest`.
- Sanitization: `sanitize_text_field`, `wp_kses_post`, numeric checks.

## Settings

- Option key: `wcse_visible_fields` (array|null). Null means show all columns.
- REST endpoints: `GET/POST wcse/v1/settings`.
- Frontend initializes `visibleFields` via `wp_localize_script`.

## Extend/Customize

- Add columns: extend `Rest_Controller::get_products()` and `update_products()`.
- Add ACF/meta support: allowlist extra keys.
- Swap grid engine: replace table in `admin.js` with Tabulator/AG Grid.
- Add bulk actions: percentage price changes, search/replace, fill-down.
- Add variation rows: expand variable products or a separate “Variations” tab.
- Add complex ACF fields: implement UI + server handling for checkbox/multi-select, taxonomy, relationship, groups/repeaters.
- Vendor front-end libs locally instead of CDN (DataTables core, Responsive, FixedColumns).

## Deployment

1. Drop folder into `wp-content/plugins/`.
2. Activate in WP Admin.
3. Navigate to **Products → Sheet Editor**.

## Troubleshooting

- **WooCommerce not active** → admin notice shown.
- **Nonce expired** → reload page (fresh nonce).
- **JS fetch error** → check REST API enabled and permalinks.
- **Categories not saving** → ensure category names exist; plugin auto-creates if missing.
- **Columns not hiding headers** → ensure DataTables assets load; the code uses DataTables column visibility API.
- **ACF not editable** → ensure “Responsive child rows” are disabled (they are by default now) and ACF plugin is active.
