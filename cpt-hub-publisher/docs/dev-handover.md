# CPT Hub — Dev Handover / Progress Log

> Purpose: Quick reference for what’s implemented, what’s pending, and how to proceed next time context resets.

## High‑Level Summary (Now)
- Publisher
  - Dynamic CPTs + custom fields (Text, Textarea, Number, URL, Select, Media, WYSIWYG)
  - Global `location` taxonomy; seeded terms; union filtering
  - RSS + REST for items; REST assets (layout + CSS); Health endpoint; Global CSS endpoint
  - Styles builder per CPT: layout, element toggles/order, responsive, animations, hover, image/button options; versioned CSS builder
  - WYSIWYG meta: TinyMCE (formatselect, styleselect, forecolor), safe HTML, classes for brand colors; sanitized and autop’d
  - Publisher shortcodes: `[cphub_list]`, `[cphub_item]` render local posts with same card UI
  - Global CSS admin tab; enqueued sitewide on Publisher and shipped to Consumers
  - Classic editor enforced for CPT Hub types
- Consumer
  - Settings (Publisher URL, secret key, location, enabled CPTs, use styles)
  - Cron refresh every 10 minutes (self‑heals schedule); cron status panel; SiteGround server cron doc
  - Ingestion via REST with conditional GET; caches items + assets
  - Local Content: registers local CPTs; imports remote items; sideloads media; renders local links/assets
  - Renderer honors layout; HTML meta rendering via `layout.meta_html` (safe HTML + paragraphs); shortcodes `[cphub_list]`, `[cphub_item]`
  - Read‑only meta box: clean key→value; media previews; hides helper keys
  - Auto enqueue Publisher CSS on shortcode render and local CPT singles/archives
  - Global CSS: fetched, cached, enqueued sitewide on Consumers
  - Location shortcode `[cphub_location]`

## Key Endpoints & Contracts
- Publisher REST
  - `GET /wp-json/cphub/v1/items?cpt=&n=&paged=&modified_since=&location=&key`
  - `GET /wp-json/cphub/v1/assets?cpt=&key` → `{ version, layout, layout_type, css }`
    - `layout.meta_html`: `{ meta1: bool, meta2: bool, meta3: bool }`
  - `GET /wp-json/cphub/v1/global?key` → `{ version, css }`
  - `GET /wp-json/cphub/v1/health`
- Caching headers: ETag + Last‑Modified on all significant endpoints; Consumers use If‑None‑Match/If‑Modified‑Since

## Notable Implementation Details
- WYSIWYG meta
  - Saved via `sanitize_wysiwyg_html`: keep safe tags; span class + `color:` only; `wpautop` to ensure paragraphs
  - Consumer autop + kses on render for HTML slots
  - TinyMCE: Paragraph/H2–H4; Formats (brand classes) + color picker (forecolor)
- Global CSS
  - Version = md5(css); endpoint has conditional GET; Consumers cache and enqueue first
- Local Content
  - Upsert posts; featured image; meta; sideload meta media; mapping via `_cphub_remote_id`
  - Publisher CSS classes/markup used on Consumer renderer
- Cron
  - Publisher: N/A
  - Consumer: self‑healing schedule + status panel; SiteGround cron doc added
- Publisher shortcodes
  - Uses `get_styles_config` + `build_styles_css` + `map_post_to_feed_item`, then shared card rendering logic

## Docs Added/Updated
- `docs/plan.md`, `docs/milestones.md` — current status + next steps; WYSIWYG + Global CSS + Publisher shortcodes
- `docs/consumer-setup.md` — Local Content, cron setup (SiteGround), read‑only meta panel, WYSIWYG rendering, Global CSS, `[cphub_location]`
- `docs/cron-siteground.md` — server cron setup
- `docs/publisher-guide.md` — field types, styles, shortcodes, global CSS, REST
- `docs/elementor-single-templates.md` — plan for per‑item Elementor template selection

## Pending / Next Steps
1) Stability/Resilience
   - Exponential backoff + jitter on repeated fetch failures (Consumer)
   - Diagnostics: per‑CPT import counts + timestamps in UI; quick retry link
2) Content
   - Inline content image rewriting to local attachments (Consumer, Local Content)
   - Local‑only merge with remote in renderer + markers (Consumer)
3) Assets
   - File‑based CSS cache in uploads (optional) with URL enqueue; keep inline fallback
4) Security & Governance
   - Per‑site keys + rotation workflow; optional rate limiting guidance; optional request logging
5) Editor/UX
   - WYSIWYG: optional custom color palette tied to CPT styles; more classes (Accent/Muted)
   - Block versions of shortcodes (Publisher/Consumer)
6) Rollout
   - Pilot playbook, batch rollout, monitoring checklist

## Upcoming Feature Plan (Elementor Single Templates — Publisher)
See `docs/elementor-single-templates.md`.
- UI: sidebar meta box per eligible CPT item; choose template
- Save: `_cphub_el_template`
- Render: `the_content` replacement with Elementor frontend API
- Eligibility: CPT has_archive = true, Elementor active
- No API changes; Consumers unaffected

## Known Gotchas
- WYSIWYG + security: we allow only `color:` inline style; other styles stripped
- Elementor template query specifics vary by Elementor version; implement robust discovery
- Cron on low‑traffic sites requires server cron; covered in docs

## Quick “How to Continue” Checklist
1. Implement Elementor per‑item template (Publisher)
   - Add template discovery util; add meta box; save meta; filter `the_content`
   - Doc: add usage notes to publisher guide
2. Add Consumer backoff + counters
   - Track consecutive failures per endpoint; show counters; backoff with jitter up to a cap
3. Optional: inline image rewriting
   - Parse content HTML; sideload images; swap src to local URL; cache mapping
4. Optional: file‑based CSS
   - Write assets/global css to uploads; enqueue by URL; version querystring

## Verification Cheatsheet
- Publisher shortcodes render cards with per‑CPT CSS
- WYSIWYG meta: paragraphs in RSS/REST/Publisher/Consumer; colors persist
- Global CSS enqueues on both Publisher and Consumers
- Consumer cron status shows last run; server cron configured (SiteGround)
- `[cphub_location]` prints friendly label

