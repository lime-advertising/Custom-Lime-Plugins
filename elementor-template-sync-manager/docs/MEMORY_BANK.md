# Elementor Template Sync Manager — Memory Bank

Version: 2025-08-29
Owner: Lime Advertising (Product)
Scope: Publisher + Consumer WordPress plugins that sync Elementor templates

## Project Snapshot
- Objective: Single source of truth for Elementor templates on a Publisher site with selective sync to multiple Consumer sites without creating duplicates; supports push and pull, rollback, and audit.
- Non-goals: General content deploys, design tokens manager, modifying Elementor editor.
- Status (MVP scaffold): Core plugin structure completed for Publisher and Consumer with shared libs; REST endpoints stubbed; DB schemas created; consumer apply/update implemented; media copy stub; rollback snapshots; basic HMAC.

## Architecture (High-Level)
- Publisher (Core): Registry of templates + versions, REST API (templates, updates, deploy), consumer directory, deploy orchestration (worker TODO).
- Consumer (Agent): Enrollment, webhook to receive push, scheduled pull (stub), apply artifact to existing Elementor template (no duplicates), media remap, rollback, mapping table.
- Shared Lib: HMAC sign/verify, artifact JSON validator.

## Canonical Identity & Data Model
- Primary key: `global_template_id` (UUID v4, immutable).
- Secondary: `slug`, `type` (header/footer/section/single/archive/popup).
- Artifact payload keys: `global_template_id`, `version`, `name`, `slug`, `type`, `_elementor_data`, `style_settings?`, `display_conditions?`, `dependencies?`, `changelog?`, `created_at`, `updated_at`, `checksum` (sha256 of canonical JSON without `checksum`).
- Consumer mapping: `global_template_id` → local `post_id` (+ `installed_version`, `last_checksum`, `last_sync_at`, `status`).

## APIs (MVP)
- Publisher (HMAC protected):
  - `GET /wp-json/etsm/v1/templates` — list.
  - `GET /wp-json/etsm/v1/templates/{global_template_id}` — latest artifact.
  - `GET /wp-json/etsm/v1/updates` — updates feed (stub).
  - `POST /wp-json/etsm/v1/deploy` — create deployment record and schedule worker (stub).
- Consumer:
  - `POST /wp-json/etsm/v1/webhook/deploy` — receive push `{ artifact_url, dry_run? }` (fetches artifact and apply/diff).
  - `POST /wp-json/etsm/v1/register` — enroll: save Publisher URL + token.
- HMAC headers: `X-ETSM-Token`, `X-ETSM-Timestamp`, `X-ETSM-Nonce`, `X-ETSM-Signature`.

## Security (MVP and Target)
- MVP: Shared secret equals site token (Consumer) with token hash stored on Publisher for lookup; 5-minute timestamp skew window. Improve to per-site secret distinct from token, hashed storage, rotation, revocation; nonce store for replay prevention; rate limiting.
- Capability: `manage_template_sync` for admin UIs and deploy actions.
- Transport: HTTPS required.

## Workflows
- Push (Publisher→Consumer): Deploy endpoint queues a job (MVP worker TODO). Consumer webhook fetches artifact and applies. Dry-run returns diff (checksum-based).
- Pull (Consumer cron): Hook present; feed not implemented.
- Rollback: Snapshot pre-apply per template; restore last snapshot.
- Media: Default copy strategy; remaps URLs within `_elementor_data`.

## Current Implementation Pointers
- Publisher
  - Bootstrap: `publisher/elementor-template-sync-publisher.php`
  - Plugin: `publisher/includes/class-plugin.php`
  - DB: `publisher/includes/class-db.php`
  - REST: `publisher/includes/class-rest-controller.php`
  - Templates: `publisher/includes/class-templates.php`
  - Deployments: `publisher/includes/class-deployments.php`
  - Admin: `publisher/includes/class-admin.php`
- Consumer
  - Bootstrap: `consumer/elementor-template-sync-consumer.php`
  - Plugin: `consumer/includes/class-plugin.php`
  - DB: `consumer/includes/class-db.php`
  - REST: `consumer/includes/class-rest-controller.php`
  - Auth: `consumer/includes/class-auth.php`
  - Sync: `consumer/includes/class-sync.php`
  - Media: `consumer/includes/class-media.php`
  - Rollback: `consumer/includes/class-rollback.php`
  - Admin: `consumer/includes/class-admin.php`
- Shared
  - HMAC: `shared/includes/class-hmac.php`
  - JSON Validator: `shared/includes/class-json.php`

## Decisions & Conventions
- No duplicates: Match by `global_template_id` using `etsm_map`; fallback to `_etsm_global_template_id` post meta to seed mapping.
- Elementor data: Stored in `_elementor_data` meta; minimal `post_content`.
- Versioning: Semantic `version` string stored in `_etsm_version` meta and mapping table.
- Checksum: Required; validator recomputes checksum for consistency.
- Schedules: Using WP-Cron placeholders; to be replaced by Action Scheduler.

## Open Questions
- Should `style_settings` and `display_conditions` be enforced on Consumer without Elementor Pro? Fallback behavior?
- Media strategy per-template or per-group policy? Hybrid toggles?
- Drift policy defaults (override vs skip) and admin approval gates?
- Groups/tags data model for Consumers (JSON tags vs normalized tables)?

## Backlog (Prioritized)
1) Implement deployment worker on Publisher (iterate targets, sign, push, per-target results, retries/backoff). Use Action Scheduler.
2) Implement Consumer pull: call Publisher `/updates`, apply per policy (auto/manual), with maintenance windows.
3) React admin UIs: Publisher registry/detail/diff/deploy; Consumer subscriptions/pending/diff/history/rollback.
4) Security hardening: per-site secret separate from token, rotation, nonce store, IP throttling, capability review, input validation.
5) Media robustness: attachment ID mapping, preflight checks, missing media warnings, timeouts and retries.
6) Observability: structured logs, audit trail, correlation IDs, health checks, admin diagnostics, Slack/email alerts.
7) JSON schema + compatibility checks (min Elementor version, dependencies).
8) Testing: unit (HMAC, JSON), integration (apply/rollback), E2E across Dockerized WP, load test with 100 Consumers.

## Risks & Mitigations
- Schema drift in Elementor: pin versions, add adapters per version.
- Network instability: retries with jitter, dead-letter queue, idempotency keys.
- Local edits: detect drift via checksum; policy to override/hold; admin diff display.
- Security: signed requests, replay prevention, least privilege.

## How to Update This Memory Bank
- Keep entries concise and dated when making non-trivial decisions.
- Add ADR-style notes under a “Decisions” subheading with context and rationale when needed.
- Update “Current Implementation Pointers” after any file moves or major refactors.
- Revise “Backlog” as features land; keep it prioritized.

