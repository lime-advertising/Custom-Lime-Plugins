# CPT Hub Publisher & Consumer

## What
CPT Hub is a pair of WordPress plugins that let the corporate “Publisher” site define reusable custom post types, styles, and feeds, while “Consumer” location sites ingest that structured content, cache it locally, and render it with the same design system. The Publisher plugin lives on the master site and exposes RSS and REST endpoints alongside admin tools for governing CPTs, taxonomies, styles, and global CSS. The Consumer plugin installs on franchise/location sites and synchronises content, assets, and templates so each location can display centrally authored material without manual duplication.

## Why
Regional sites need to stay aligned with centrally produced campaigns, yet still honour local targeting such as geography-specific offers. Maintaining dozens of CPT definitions, taxonomies, Elementor templates, and styling rules by hand quickly drifts out of sync. CPT Hub solves that by:
- Giving the corporate team one control panel to create CPTs, fields, styles, and location taxonomy assignments.
- Shipping a predictable feed/REST contract that franchise sites can trust for automation.
- Allowing each consumer site to opt into the CPTs it needs, cache them safely, and keep serving content even when the Publisher is temporarily unreachable.
- Preserving brand consistency by delivering the same CSS/layout definitions that power the Publisher site itself.

## Features
- **Dynamic CPT Management (Publisher)**: Create/edit/delete CPT definitions, assign custom taxonomies, configure per-field metadata (text, select, media, WYSIWYG), and control rewrite slugs and archive behaviour.
- **Location Taxonomy Seeding**: Ships with pre-defined location terms, protects default slugs from accidental edits, and enforces a shared `location` vocabulary across all sites.
- **Per-CPT Styles Builder**: Visual admin for layout ordering, responsive toggles, meta slot mapping, animations, image behaviour, and style copying between CPTs—plus automatic CSS generation and versioning.
- **Feeds & REST Contract**: Clean RSS endpoint and REST routes for items, assets (layout + CSS + templates), global CSS, and health status with ETag/Last-Modified headers for cache friendliness.
- **Shortcodes & Rendering Helpers (Publisher & Consumer)**: `[cphub_list]`, `[cphub_item]`, `[cphub_location]`, and `[cphub_meta]` mirror the shared card UI on both sides; Elementor archive and single template hooks let designers reuse Elementor Theme Builder assets. The list shortcode now supports comma-separated CPT slugs (or a `cpts="foo,bar"` attribute) and `tax`/`terms="slug,slug"` filters to mix layouts across multiple CPTs while narrowing results by custom taxonomy.
- **Consumer Sync & Caching**: WP-Cron backed refresh every 10 minutes, manual refresh/clear/health actions, conditional GET usage, local CPT registration that honours Publisher rewrite slugs, and media sideloading for remote assets.
- **Global CSS Propagation**: Publisher-wide CSS is versioned and delivered to every Consumer so custom brand tweaks remain in lockstep.
- **Admin UX Enhancements**: Classic Editor enforcement for CPT Hub types, custom meta boxes to render field data, read-only panels on Consumer sites, and cleanup utilities for retired CPTs or stale content.

## What’s Left
- **Resilience Enhancements**: Implement exponential backoff with jitter for failed Consumer fetches, surface per-CPT import counters, and improve diagnostics in the Consumer UI.
- **Elementor Single Template Workflow**: Complete the Publisher-side meta box and rendering path that lets editors pick Elementor single templates per item, then document the workflow for operators.
- **Inline Media Rewriting**: Add optional logic on the Consumer to rewrite inline HTML images to locally sideloaded attachments, improving performance and preserving assets if the Publisher changes URLs.
- **File-Based CSS Caching**: Move optional CSS caching from the database to physical files under uploads with cache-busting query strings for efficient front-end delivery while keeping inline fallback.
- **Security & Rollout Tooling**: Provide per-site API key rotation guidance, optional rate limiting, request logging patterns, and rollout playbooks/monitoring checklists for multi-location deployments.
