=== Lime Schema Boilerplate ===
Contributors: lime
Tags: schema, json-ld, seo, faq, localbusiness
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lean JSON‑LD schema: Organization, WebSite, WebPage, LocalBusiness, FAQs, optional Article.

== Description ==

Lime Schema outputs clean JSON-LD in the document head using a single `@graph` with stable `@id`s. It includes:

- Organization, WebSite (with SearchAction), WebPage (per-page overrides)
- LocalBusiness locations (repeater) with address, geo, hours, areas served, services
- FAQs (site-wide + per-page custom)
- Optional BreadcrumbList via a theme filter
- Optional Article schema for posts (author, publisher, dates, image)
- Admin Preview tab with copy, recommendations, and Rich Results Test link

Built for correctness (removes empty fields) and compatibility (can auto-disable core nodes when Yoast/Rank Math is active).

Smart defaults and safety:

- Cleans out empty properties recursively for valid, compact JSON-LD
- Stable `@id` anchors (e.g., `#org`, `#website`, `#webpage`, `#faq`, `#article`)
- Auto-disables Organization/WebSite/WebPage/Article when Yoast or Rank Math is detected (can be overridden)
- Nonce-protected admin AJAX preview and proper sanitization/escaping throughout

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install via the Plugins screen.
2. Activate the plugin.
3. Go to Settings → Lime Schema to configure Organization, WebSite, Locations, FAQs, and Preview.
4. On individual pages or posts, use the "Lime Schema (Page-specific)" meta box to include nodes and set overrides.

== Frequently Asked Questions ==

= How do Breadcrumbs work? =

Provide crumbs from your theme or a breadcrumb plugin using the filter:

```
add_filter( 'lime_schema_breadcrumbs', function( $crumbs, $post ) {
    return [
        [ 'name' => 'Home', 'item' => home_url( '/' ) ],
        [ 'name' => 'Blog', 'item' => home_url( '/blog/' ) ],
        [ 'name' => get_the_title( $post ), 'item' => get_permalink( $post ) ],
    ];
}, 10, 2 );
```

= Can I add custom nodes? =

Yes. Use the `lime_schema_graph` or `lime_schema_payload` filters to modify the graph before output.

= Will this conflict with Yoast or Rank Math? =

To avoid duplicate core nodes, the plugin can automatically disable Organization, WebSite, WebPage, and Article when Yoast or Rank Math is detected. You can change this in Settings → Lime Schema → Website (uncheck “Automatically disable…”), or disable individual nodes.

= Can I add FAQs per page? =

Yes. Add global FAQs in Settings → FAQs. On a specific page, check “Include FAQ” and (optionally) “Use custom FAQs on this page” to override with per-page Q&A.

= How do I preview and validate the JSON-LD? =

Open Settings → Preview and click Refresh. Use the “Copy” button or “Validate in Rich Results Test” to open Google’s tool. The preview uses unsaved form values.

== Screenshots ==

1. Settings with tabs for Organization, Website, Locations, FAQs, and Preview
2. Page meta box with per-page includes and overrides

== Filters & Actions ==

- `lime_schema_breadcrumbs( $crumbs, $post )` — supply an array of breadcrumbs: `[ [ 'name': 'Home', 'item': 'https://example.com/' ], ... ]`
- `lime_schema_graph( $graph, $opts, $meta, $post )` — modify the final `@graph` array
- `lime_schema_payload( $payload, $opts, $meta, $post )` — alter the full payload (including `@context`)
- `lime_schema_pre_output( $bool, $payload, $opts, $meta, $post )` — return false to short-circuit output
- `lime_schema_allowed_lb_types` — filter the allowed LocalBusiness subtypes used in the UI

== Compatibility ==

- Yoast SEO and Rank Math: core nodes can be auto-disabled to prevent duplication. LocalBusiness, FAQ, and Breadcrumbs remain available.
- Themes/Breadcrumb plugins: integrate via the `lime_schema_breadcrumbs` filter.

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
Initial release.
