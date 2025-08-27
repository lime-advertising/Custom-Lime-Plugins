# Consumer Plugin — Setup Guide

This guide explains how to connect a location site (Consumer) to the corporate Publisher, fetch content for selected CPTs, and render it with consistent styles.

## Prerequisites
- WordPress 6.0+ and PHP 8.0+
- URL of the Publisher site and (optionally) a secret key
- The location slug that matches a term in the Publisher’s `location` taxonomy (e.g. `nyc`, `london`)

## Install & Configure
1. Install the Consumer plugin on the location site.
2. Go to `Settings → CPT Hub Consumer` and configure:
   - Publisher Base URL: `https://corporate.example.com`
   - Secret Key: provided by the Publisher admin (if required)
   - Location Slug: matches your site’s term (or leave empty to consume only global/all-locations)
   - Enable the CPTs you want to display locally
   - Use Publisher Styles: enable to enqueue CSS shipped from Publisher
   - Local Content: enable to create local CPTs for the selected slugs, import posts on sync, and sideload media so permalinks and assets resolve locally
3. Save settings. Use “Open Health” to view the Publisher’s health JSON, or “Check Health” on this page to fetch a summary (status, base URLs, and known CPTs).

## Rendering Content
- List of items (shortcode):
  - `[cphub_list cpt="slides" n="10" paged="1"]`
  - Attributes: `cpt` (required), `n`, `paged` (pagination against local cache)
- Single item (shortcode):
  - `[cphub_item cpt="slides" id="123"]`
- Renderer honors Publisher layout per CPT:
  - Order and enabled flags for elements (`image`, `title`, `excerpt`, `content`, `meta1..3`, `button`)
  - Meta mapping and placement (thumb/content)
  - Button overlay markup if the Publisher CSS includes `.cphub-btn.has-hover`
- Grid vs List: Consumer uses `layout_type` (or detects from CSS) to choose `.cphub-grid` or `.cphub-list` wrappers.

Fields and meta from the Publisher are mapped 1:1. For media fields, the feed includes companions:
- `{key}_id`, `{key}_url`, `{key}_mime`
  - If `mime` starts with `image/`, Consumer renders an `<img>`; else a download link.

For media meta fields:
- If the field is configured as Image, render `<img src="{meta.key_url}" alt="" />` when available; otherwise fall back to a link.
- For non‑image media, render `<a href="{meta.key_url}">Download</a>` (and consider using `{meta.key_mime}` for icons/labels).

## Styles & Scripts
- The Consumer fetches a per‑CPT assets payload from the Publisher: `{ version, layout, layout_type, css }`.
- Toggle: “Use Publisher Styles” in settings. When enabled, the remote CSS string is cached by version and enqueued per CPT.
- Auto‑enqueue: Styles are enqueued when rendering shortcodes and also automatically on local CPT single and archive views.
- The `layout` object includes:
  - `order` and `enabled` maps for element sequencing/visibility
  - `meta_keys` mapping for Meta1–Meta3
  - `meta_wrap` placement per meta slot: `thumb` or `content`
  - responsive visibility maps (`enabled_tab`, `enabled_mob`)
- Markup conventions expected by Publisher CSS:
  - Card container: `<div class="cphub-card">`
  - Thumb/content wrappers: `<div class="cphub-thumb-wrap"></div>` and `<div class="cphub-content-wrap"></div>`
  - Element classes: `.cphub-img`, `.cphub-title`, `.cphub-excerpt`, `.cphub-content`, `.cphub-meta`, `.cphub-btn`
- You can override styling by disabling Publisher styles and applying local theme CSS.

Animations implemented (CSS‑only):
- Entrance stagger for cards (optional). No JS required.
- Thumbnail hover reveal with Solid or Sheen style. No JS required.
- Image hover zoom (optional).
- Button ripple (CSS background ripple; center‑origin by default). Optional overlay markup is supported for more complex effects, but is not required.

Button alignment:
- Optional “Stick to bottom” setting makes cards flex columns and pushes `.cphub-btn` to the bottom of the card for consistent alignment across a row.

## Caching & Sync
- Content is cached locally with conditional GET (ETag/Last-Modified) and refreshed by WP‑Cron (every 10 minutes). Requests cap `n` at 100 items.
- Manual refresh: click “Refresh Now” in settings. Use “Clear Cache” to wipe local caches.
- If the Publisher is unreachable, cached data continues to render; recent HTTP status/error are shown in the cache table.
- When “Local Content” is enabled, sync also upserts posts into local CPTs and sideloads media. Shortcodes automatically link to local permalinks and use local thumbnails when available.

### Using server cron on SiteGround
If your host disables WP‑Cron or you prefer predictable scheduling, set up a server cron:
- Disable WP‑Cron: set `define('DISABLE_WP_CRON', true);` in `wp-config.php`.
- Add a cron job in Site Tools → Devs → Cron Jobs to call either:
  - `curl -sS https://your-site.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1` (every 5–10 minutes), or
  - `/usr/local/bin/php -q /home/customer/www/your-site.com/public_html/wp-cron.php >/dev/null 2>&1` (preferred).

See `docs/cron-siteground.md` for step‑by‑step instructions.

## Location Targeting
- By default, the Consumer requests content for its configured `location` plus `all-locations` from the Publisher.
- Override per view by passing `location` in shortcodes/blocks.

## Troubleshooting
- 403/Forbidden: verify the Secret Key matches the Publisher.
- Empty results: confirm the CPT exists on the Publisher, and that items are tagged with your `location` or `all-locations`.
- Styles not applied: ensure “Use Publisher Styles” is enabled, and check that the manifest returns URLs.
- Media not visible: confirm media fields on the Publisher are set; in feeds they appear as meta with `_url` and `_mime` keys.
 - Local permalinks not used: ensure “Local Content” is enabled and run “Refresh Now”. The plugin maps remote IDs to local posts via `_cphub_remote_id` meta.

## Security & Governance
- Prefer per‑site keys; rotate keys periodically on the Publisher and update Consumers.
- Limit roles that can change Consumer settings.

## Migration Checklist
1. Create or verify CPTs and fields on Publisher; tag content with `all-locations` or specific locations.
2. Install Consumer plugin on a pilot location; configure URL, key, location, and CPTs.
3. Validate rendering and styles; adjust templates if needed.
4. Roll out to the remaining locations in batches.

---

For deeper integration (custom templates, REST-only consumption, or advanced caching), coordinate with the Publisher team to align contracts and versioning.
