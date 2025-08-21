=== Lime JSON Viewer ===
Contributors: limeadvertising
Tags: elementor, json, export, debug, tools
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

View and export the raw Elementor `_elementor_data` JSON for any post, page, or Elementor template from your WordPress admin.

== Description ==

Lime JSON Viewer adds a handy Tools screen that lets you select a post, page, or template and inspect the `_elementor_data` JSON in a readable, pretty‑printed format. You can copy the JSON, download it as a `.json` file, or open it via a nonce‑protected REST URL.

Key features:
- Tools page under Tools → Lime JSON Viewer
- Pretty‑printed JSON output with support for JSON strings and serialized arrays
- One‑click Copy, Download, and Open via REST actions
- Row action on post/page lists + admin‑bar shortcut on edit screens
- Capability and nonce checks for secure access

Works even if Elementor isn’t active, as long as the `_elementor_data` meta exists.

== Installation ==

1. Upload the `lime-json-viewer` folder to `/wp-content/plugins/` or install the ZIP via Plugins → Add New → Upload Plugin.
2. Activate the plugin through the “Plugins” menu in WordPress.
3. Go to Tools → Lime JSON Viewer to view Elementor JSON.

== Frequently Asked Questions ==

= Does this require Elementor? =
No. The plugin reads the `_elementor_data` meta if present. Elementor is only used to improve the “built with Elementor” indicator.

= Where is the JSON coming from? =
From the post meta key `_elementor_data`.

= Can I add custom post types to the selector? =
Yes, use the `eljv_post_types` filter:

```
add_filter( 'eljv_post_types', function( $types ) {
    $types[] = 'your_custom_type';
    return $types;
} );
```

== Screenshots ==
1. Tools screen showing selector, controls, and pretty‑printed JSON

== Changelog ==

= 1.0.1 =
Initial public version.

== Upgrade Notice ==

= 1.0.1 =
Initial release.

