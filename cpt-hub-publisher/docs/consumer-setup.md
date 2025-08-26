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
3. Save settings, then click “Test Connection” to verify all endpoints.

## Rendering Content
- List of items (shortcode):
  - `[cphub_list cpt="slides" n="10" paged="1" location="nyc"]`
  - Attributes: `cpt` (required), `n`, `paged`, `location` (overrides default), `template` (optional)
- Single item (shortcode):
  - `[cphub_item cpt="slides" id="123"]`
- Block editor: use the “CPT Hub List” or “CPT Hub Item” blocks (if enabled by the plugin) and configure via sidebar controls.

Fields and meta from the Publisher are mapped 1:1 and available to templates. Media fields expose `_id`, `_url`, `_mime` companions.

## Styles & Scripts
- The Consumer fetches a per‑CPT manifest from the Publisher: `{ version, css[], js[] }` and enqueues assets.
- Toggle: “Use Publisher Styles” in settings. When enabled, remote CSS/JS is cached and loaded by version.
- You can override styling by disabling Publisher styles and applying local theme CSS.

## Caching & Sync
- Content is cached locally with conditional GET (ETag/Last‑Modified) and refreshed by WP‑Cron (e.g., every 5–15 minutes).
- Manual refresh: click “Refresh Now” in settings.
- If the Publisher is unreachable, cached data continues to render; errors appear in the health status panel.

## Location Targeting
- By default, the Consumer requests content for its configured `location` plus `all-locations` from the Publisher.
- Override per view by passing `location` in shortcodes/blocks.

## Troubleshooting
- 403/Forbidden: verify the Secret Key matches the Publisher.
- Empty results: confirm the CPT exists on the Publisher, and that items are tagged with your `location` or `all-locations`.
- Styles not applied: ensure “Use Publisher Styles” is enabled, and check that the manifest returns URLs.
- Media not visible: confirm media fields on the Publisher are set; in feeds they appear as meta with `_url` and `_mime` keys.

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
