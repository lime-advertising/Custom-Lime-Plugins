# CPT Hub Publisher/Consumer — Project Plan

## Vision
Single source of truth for reusable content on the corporate site (Publisher) distributed to 40+ location sites (Consumers), with consistent presentation and minimal effort to update across the network.

## Goals
- Centralize reusable content creation and updates on the Publisher.
- Allow location-specific content without changing corporate content.
- Ensure consistent UX via versioned style/script sets shipped from the Publisher.
- Provide reliable, cache-friendly sync with clear versioning and rollbacks.

## Scope
- Publisher plugin manages: CPT definitions, custom fields, taxonomies, feed/REST endpoints, and style sets per CPT.
- Consumer plugin manages: connection settings, fetching/caching content & assets, rendering via shortcodes/blocks/templates.

Out of scope (initially): editorial workflow/approvals, advanced permissions, analytics, search indexing across sites.

## Architecture Overview
- Publisher (Corporate)
  - Define CPTs, meta fields, and taxonomies (includes `location`).
  - Provide RSS and REST JSON endpoints for content.
  - Provide REST endpoint for per‑CPT style/script sets with versioning.
  - Optional secret key for gated access.
- Consumers (Locations)
  - Configure Publisher base URL, secret key, and `location` slug.
  - Enable CPTs to ingest; render via shortcodes/blocks/templates.
  - Cache content and assets locally with versioned invalidation; cron refresh; conditional GET (ETag/Last‑Modified).

## Location Strategy (Recommended)
- Add a `location` taxonomy on the Publisher with terms for each location plus a special `all-locations` term.
- When consuming, a query param `location=nyc` returns content tagged with `nyc` OR `all-locations`.
- Advantages: central targeting of reuse vs. per‑site data entry; easy to add more locations; simple rollout.

Alternative: Allow Consumers to add local-only items of the same CPT and merge during render (optional add-on after core flow).

## Style Sets Strategy
- Maintain a versioned per‑CPT style config on the Publisher.
- Expose an assets endpoint: `{ version, layout, layout_type, css }` where `css` is a stylesheet string generated from the saved config.
- Consumers fetch and cache CSS by version and inject/enqueue per CPT. Optional toggle to use Publisher styles vs. site theme.

## APIs & Contracts
- RSS: continue to expose `/feed/cphub` and per‑CPT `/feed/cphub/{slug}` including meta fields, media URLs, and taxonomy terms.
- REST (implemented):
  - Items: `/wp-json/cphub/v1/items?cpt=slides&location=nyc&n=20&paged=1&modified_since=YYYY-MM-DD`
  - Assets: `/wp-json/cphub/v1/assets?cpt=slides` → `{ version, layout, layout_type, css }`
  - Health: `/wp-json/cphub/v1/health` → status, base URLs, feed cache stats, styles versions
- Security: Optional `key` query param (shared or per-consumer); rotateable.
- Caching: ETag/Last-Modified for REST and RSS (implemented) with 304 handling; 5‑minute transients on Publisher; local caching on Consumer; conditional GETs. Page size `n` is capped at 100.
  - Items include `tax_terms` for the `location` taxonomy to aid debugging of location filtering.

## Performance & Reliability
- Cap page size (`n`) on Publisher to safe defaults (<= 100) — implemented.
- 5–15 minute caches with cache-busting on relevant CPT changes.
- Background cron refresh on Consumers; render from cache with graceful degradation if the network fails.

## Rollout Plan
1. Pilot with 1–2 locations.
2. Validate content coverage, performance, and styling.
3. Roll out to remaining sites in batches with monitoring.

## Risks & Mitigations
- Network failures: Consumers render from cache; retries with backoff.
- Style regressions: Versioned assets with rollback to a prior version.
- Data drift: Acceptance checks and health status in Consumer settings.
- Security: Per-site keys preferred for revocation; documented rotation.

## Open Decisions
- Asset delivery: reference vs. local caching (recommend local caching for resilience).
- REST format details and pagination envelope.
- Additional field types/validation requirements. Optional JS delivery for UI polish (currently CSS‑only animations; JS not shipped by default).

## Timeline (Suggested)
- Week 1: Publisher location targeting + style sets v1.
- Week 2: Consumer settings + ingestion/caching.
- Week 3: Consumer style integration + pilot rollout.
- Week 4: Local-only option, security polish, docs, broader rollout.

---

Document maintained in `docs/`. Update alongside implementation.
