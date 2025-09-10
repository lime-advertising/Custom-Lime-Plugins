# Changelog

All notable changes to this project will be documented in this file.

The format is inspired by Keep a Changelog, and this project adheres to Semantic Versioning where feasible.

## [0.2.0] – 2025-09-09

### Added
- DataTables-powered grid with horizontal scroll; Responsive child rows disabled to keep all cells editable.
- FixedColumns: freeze first 3 columns.
- Column visibility panel to show/hide core and ACF fields; persisted via `wcse/v1/settings` and `wcse_visible_fields` option.
- Tokenized Categories field with datalist suggestions (autocomplete) from `wp/v2/product_cat`.
- Dropdowns for Stock Status and Product Status to prevent typos.
- Automatic ACF field detection (text, textarea, email, url, number, true_false, select, radio) with inline editing.
- Numeric input handling: Quantity as integer; prices as floats; client- and server-side enforcement.
- Docs: updated dev handover and memory bank to reflect new features.

### Changed
- Use correct script handle `wp-api-fetch` and include as dependency.
- Return `stock_qty` as integer; save quantity as integer on server.
- Use delegated event listeners for robust editing after table redraws.

### Fixed
- Prevent recursive draw loop in DataTables column visibility (max call stack error).
- Validate `status` REST arg via enum to guard input.

## [0.1.0] – 2024-??-??
- Initial release with basic grid, REST endpoints, and core product fields.
