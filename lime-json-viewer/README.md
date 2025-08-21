# Lime JSON Viewer

Contributors: limeadvertising
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

View and export the raw Elementor `_elementor_data` JSON for any post, page, or Elementor template from your WordPress admin.

## Overview
- Adds a Tools page under `Tools → Lime JSON Viewer` to select a post and inspect its `_elementor_data` JSON.
- Pretty‑prints the JSON for readability and supports both raw JSON strings and serialized arrays.
- One‑click actions: Copy to clipboard, Download as `.json`, or open the REST response in a new tab.
- Quick access links: list‑table row action and an admin‑bar item on post edit screens.
- Secured with capability checks (`edit_posts` / `edit_post`) and nonces.

## Requirements
- WordPress 6.0+
- PHP 7.4+
- Optional: Elementor (not strictly required to view the meta if present)

## Installation
1. Copy this folder into `wp-content/plugins/lime-json-viewer`.
2. In wp‑admin, go to `Plugins` and activate “Lime JSON Viewer”.

Alternatively, zip the folder and upload the ZIP in `Plugins → Add New → Upload Plugin`.

## Usage
- Tools page: `Tools → Lime JSON Viewer`
  - Choose a Post/Page/Template from the dropdown and click “View JSON”.
  - Controls:
    - `Copy`: copies the JSON to your clipboard.
    - `Download .json`: forces a file download with the pretty‑printed JSON.
    - `Open via REST`: opens a nonce‑signed REST URL in a new tab.
- List table action: From Posts/Pages (and templates), use “View Elementor JSON”.
- Post edit screen: An admin‑bar menu item opens the viewer for the current post.

## What It Reads
- Meta key: `_elementor_data`.
- Input formats supported:
  - JSON string
  - Serialized array (will be normalized)

The plugin normalizes to an array (when possible) and pretty‑prints with `JSON_UNESCAPED_SLASHES`.

## Security
- Tools page and actions require `edit_posts` (and `edit_post` for a specific post).
- Download and REST are nonce‑protected with the `eljv_nonce` key.

## REST API
- Namespace: `eljv/v1`
- Endpoint: `GET /wp-json/eljv/v1/json/{id}?_wpnonce={nonce}`
  - Permissions: current user must be able to `edit_post({id})`.
  - Nonce: `_wpnonce` must verify against `eljv_nonce`.
  - Example response:
    ```json
    {
      "post_id": 123,
      "meta_key": "_elementor_data",
      "data": [ /* normalized array */ ],
      "is_elementor": true
    }
    ```

Tip: Use the “Open via REST” button on the Tools page to get a valid URL with a fresh nonce.

## Extensibility
- Filter: `eljv_post_types` — control which post types appear in the selector.
  ```php
  add_filter('eljv_post_types', function ($types) {
      $types[] = 'your_custom_type';
      return $types;
  });
  ```

## Notes
- Works even if Elementor isn’t active, as long as `_elementor_data` exists. Elementor presence is only used to improve the “built with Elementor” check.
- Admin assets: a small inline stylesheet and an inline script that uses the native Clipboard API for the copy button (no bundled library).

## Known Issues
- None currently.

## Changelog
- 1.0.1 — Initial public version in this repository.

## License
GPL‑2.0‑or‑later. See `License URI` in the plugin header.
