# CPT Hub — Milestones Checklist

Only update this checklist after the stakeholder says “done”.

## 1) Publisher: Location Targeting
- [x] Add `location` taxonomy; seed 40 terms + `all-locations`.
- [x] Filter feeds/REST by `location`, returning union of `all-locations` + the requested location.
- [x] Admin UI to manage locations (optional basic CRUD).
- [x] Acceptance: Correct union in responses; caching and performance verified.

## 2) Publisher: Style Sets v1
- [x] Styles tab: element order (drag), enable toggles, and meta field mapping.
- [x] Per‑element styles and card styles (colors, sizes, margin/padding, width/min/max, alignment, radius, shadow).
- [x] Versioned style config (hash) with 5‑min cache and busting on change.
- [x] REST assets endpoint returns `{ version, layout, css }` with `Cache-Control`.
- [x] Add ETag/Last‑Modified headers to REST responses.
- [ ] Optional JS assets support (manifest `js[]`).
- Acceptance: Preview reflects saved styles; Consumers can fetch version + CSS. ETag/JS pending.

## 2.1) Layout Presets + Responsive
- [x] List/Grid presets with grid gap and columns (desktop).
- [x] Responsive grid columns for tablet/mobile.
- [x] Responsive element visibility (tablet/mobile toggles).
- [x] Responsive scale factors (tablet/mobile) that scale base sizes, paddings, radii.
- [x] Preview renders up to 6 cards to visualize grid.

## 3) Consumer Plugin: Core Settings
- [x] Settings for publisher base URL, secret key, `location` slug.
- [x] Per‑CPT enable/disable toggles (CSV + toggles; merges both).
- [x] Health check endpoint test + status display in UI (Check Health + summary).
- [x] Clear cache button; cache table with diagnostics (status/error).
- [x] Acceptance: Connectivity validated; credentials stored securely.

## 4) Consumer: Content Ingestion + Caching
- [x] Fetch JSON for enabled CPTs; cache locally with conditional GET (ETag/Last‑Modified).
- [x] Background cron refresh (every 10 minutes).
- [x] Diagnostics: last HTTP status and error captured per CPT (items/assets).
- [ ] Backoff on repeated failures.
- [x] Shortcodes: `[cphub_list]`, `[cphub_item]` render from cache.
- [x] Renderer honors Publisher layout (order/enabled/meta mapping), image, button overlay.
- [x] Acceptance: Pages render from cache; CSS enqueued per CPT version at render time.

## 5) Consumer: Style Sets Integration
- [x] Fetch asset manifest; cache by version + layout; enqueue CSS per CPT.
- [x] Toggle: use Publisher styles.
- [x] Prevent duplicate enqueues and ensure proper timing (enqueue at render).
- [x] Acceptance: Visual consistency; updates apply on version change.

## 6) Local‑Only Content (Optional add‑on)
- [x] Local CPTs mirroring publisher structures.
- [ ] Merge remote + local items in renderers (by date or configurable).
- [ ] Clear labeling or markers for local-only items.
- [ ] Acceptance: Local content appears without affecting publisher data.

## 7) Governance & Security
- [ ] Per‑site keys (preferred) or shared key; rotation workflow.
- [ ] Rate limiting guidance; basic request logging (optional).
- [ ] Acceptance: Key rotation does not disrupt consumers; docs provided.
  
Status: Feed-level shared secret supported; per-site key workflow pending.

## 8) Docs & Rollout
- [x] Admin guides for publisher and consumers (updated).
- [ ] Migration plan from manual updates; pilot rollout to 1–2 sites.
- [ ] Batch rollout plan for remaining locations; monitoring checklist.
- [ ] Acceptance: Pilot signed off; playbook approved for scale‑out.

Status: Publisher REST/RSS caching (ETag/Last-Modified) implemented; Health endpoint added. Consumer settings + ingestion + renderer + styles integrated; Local Content imports remote items into local CPTs with media sideloading; pending consumer error backoff, local+remote merge logic, markers for local-only items, and rollout playbook.
