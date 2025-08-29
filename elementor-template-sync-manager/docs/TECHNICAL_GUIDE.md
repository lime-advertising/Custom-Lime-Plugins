# Elementor Template Sync Manager — Technical Guide

Audience: CTO, Tech Lead, Senior WordPress Developers
Runtime: WordPress 6.2+, PHP 8.1+, Elementor 3.18+
Scope: Publisher + Consumer plugins with shared utilities and REST-based sync

## 1) Architecture Overview
- Roles:
  - Publisher: Source of truth for Elementor templates; exposes Registry/Artifact APIs; orchestrates deployments.
  - Consumer: Receives updates (push webhook or scheduled pull); applies artifacts to local Elementor templates.
- Transport: WordPress REST API over HTTPS with HMAC-signed requests.
- Identity: `global_template_id` (UUID v4) immutable across environments; mapping to local `post_id` on Consumers.
- Storage:
  - Publisher tables: `etsm_templates`, `etsm_template_versions`, `etsm_consumers`, `etsm_deployments`.
  - Consumer tables: `etsm_map`, `etsm_history`, `etsm_jobs`.
- Shared libs: HMAC signing/verification; artifact validation/checksum.

Repo pointers:
- Publisher bootstrap: `publisher/elementor-template-sync-publisher.php:1`
- Consumer bootstrap: `consumer/elementor-template-sync-consumer.php:1`
- Shared HMAC: `shared/includes/class-hmac.php:1`
- Shared JSON validator: `shared/includes/class-json.php:1`

## 2) Data Model & DB Schemas
- Publisher (`publisher/includes/class-db.php:1`):
  - `wp_etsm_templates`
    - `global_template_id` CHAR(36) UNIQUE
    - `slug` UNIQUE, `type`, `name`, `created_at`, `updated_at`
  - `wp_etsm_template_versions`
    - `template_id` FK → `etsm_templates.id`
    - `version` VARCHAR(32), `artifact_json` LONGTEXT, `checksum` CHAR(64), `created_at`
  - `wp_etsm_consumers`
    - `site_name`, `site_url` UNIQUE, `token_hash` CHAR(64), `tags`, `status`, `last_seen_at`, timestamps
  - `wp_etsm_deployments`
    - `template_id`, `version`, `targets` JSON, `status`, `results` JSON, timestamps
- Consumer (`consumer/includes/class-db.php:1`):
  - `wp_etsm_map`
    - `global_template_id` UNIQUE, `post_id`, `installed_version`, `status`, `last_sync_at`, `last_checksum`
  - `wp_etsm_history`
    - `global_template_id`, `version`, `snapshot_json`, `created_at`
  - `wp_etsm_jobs`
    - generic queue placeholder (`job_type`, `payload_json`, `status`, `attempts`, `last_error`)

## 3) Artifact Schema
- Required keys: `global_template_id`, `version`, `name`, `slug`, `type`, `_elementor_data`, `checksum`.
- Optional: `style_settings`, `display_conditions`, `dependencies`, `changelog`, `created_at`, `updated_at`.
- Checksum: SHA-256 of canonical JSON excluding the `checksum` field. See validator at `shared/includes/class-json.php:1`.

Canonical checksum recompute (pseudo-PHP):
```php
$copy = $artifact; unset($copy['checksum']);
$calc = hash('sha256', wp_json_encode($copy));
```

## 4) REST API Surface
- Namespace: `etsm/v1`.

Publisher (`publisher/includes/class-rest-controller.php:1`)
- `GET /wp-json/etsm/v1/templates` (HMAC): list rows in `etsm_templates` (filter `type`).
- `GET /wp-json/etsm/v1/templates/{global_template_id}` (HMAC): latest artifact JSON.
- `GET /wp-json/etsm/v1/updates` (HMAC): updates feed (stub).
- `POST /wp-json/etsm/v1/deploy` (cap `manage_template_sync`): create deployment and schedule worker stub.

Consumer (`consumer/includes/class-rest-controller.php:1`)
- `POST /wp-json/etsm/v1/webhook/deploy` (HMAC): body `{ artifact_url, dry_run? }`. Fetches artifact, returns diff or applies.
- `POST /wp-json/etsm/v1/register` (open): body `{ publisher_url, token }`. Saves options for enrollment.

## 5) Authentication & Signing (HMAC)
- Headers (case-insensitive):
  - `X-ETSM-Token`: site token (MVP) — will evolve to an ID + separate secret.
  - `X-ETSM-Timestamp`: UNIX seconds (±300s skew allowed).
  - `X-ETSM-Nonce`: unique ID per request (MVP not persisted; add nonce store later).
  - `X-ETSM-Signature`: Base64(HMAC-SHA256(canonical, secret)).
- Canonical string (`shared/includes/class-hmac.php:1`):
```
METHOD\nPATH\nTIMESTAMP\nNONCE\nSHA256(BODY)
```
- Signature function:
```php
$bodySha = hash('sha256', $body ?? '');
$canonical = implode("\n", [strtoupper($method), $path, $timestamp, $nonce, $bodySha]);
$signature = base64_encode(hash_hmac('sha256', $canonical, $secret, true));
```

Example (cURL → Consumer webhook):
```bash
TS=$(date +%s); NON=$(uuidgen); BODY='{"artifact_url":"https://pub.example.com/artifacts/abc.json"}'
CANON_PAYLOAD=$(printf %s "$BODY" | openssl dgst -sha256 -hex | awk '{print $2}')
CANON=$(printf "POST\n/wp-json/etsm/v1/webhook/deploy\n%s\n%s\n%s" "$TS" "$NON" "$CANON_PAYLOAD")
SIG=$(printf %s "$CANON" | openssl dgst -sha256 -hmac "$SECRET" -binary | openssl base64 -A)

curl -X POST "$CONSUMER/wp-json/etsm/v1/webhook/deploy" \
  -H "Content-Type: application/json" \
  -H "X-ETSM-Token: $TOKEN" \
  -H "X-ETSM-Timestamp: $TS" \
  -H "X-ETSM-Nonce: $NON" \
  -H "X-ETSM-Signature: $SIG" \
  -d "$BODY"
```
Notes:
- The `PATH` must match WP REST route string exactly (leading slash included).
- For Publisher-protected endpoints, adjust path and host accordingly.

## 6) Sync Application (Consumer)
- Entrypoints:
  - Webhook: `webhook_deploy()` → fetch artifact → dry-run or `Sync::apply_artifact()`.
  - Pull: `etsm_consumer_cron_pull` → implement updates feed polling.
- No-duplicate logic (`consumer/includes/class-sync.php:1`):
  - Lookup `etsm_map` by `global_template_id`. If missing, search for existing `elementor_library` with `_etsm_global_template_id` meta; seed mapping.
- Apply steps:
  1) Validate artifact via `Shared\JSON::validate_artifact()`.
  2) Media remap: `Media::remap_media()` traverses `_elementor_data` and sideloads URLs; replace with local URLs.
  3) Snapshot pre-change: `Rollback::snapshot_before_apply()` stores post + meta.
  4) Upsert post (`post_type=elementor_library`), set meta: `_elementor_data`, `_elementor_template_type`, `_etsm_*` markers.
  5) Upsert mapping row (`etsm_map`) with version, checksum, last_sync_at.
- Elementor specifics:
  - `_elementor_data` stored JSON-encoded in post meta. Use `wp_slash(wp_json_encode($data))` to avoid serialization issues.
  - For display conditions (Pro), store associated meta (future work).

## 7) Media Strategy
- Default: copy strategy. Any `url` within `_elementor_data` is sideloaded with `media_handle_sideload()` and replaced with local attachment URL.
- Edge cases:
  - Timeouts/download failures → return partial remap (leave original URL) and log warning.
  - Attachment IDs embedded in `_elementor_data`: future enhancement to map by ID/URL pairs.
  - Large images: consider async media prefetch or queued background tasks.

## 8) Rollback Strategy
- Snapshot composition (`consumer/includes/class-rollback.php:1`):
  - `snapshot_json` stores serialized `get_post()` and `get_post_meta()` result arrays.
- Restore:
  - Reinsert post fields; replace meta set; invalidate caches if present.
- Notes:
  - Current snapshot is “last version only”; add multiple snapshots or version pinning per artifact if needed.

## 9) Deployment Orchestration (Publisher)
- Current state: `POST /deploy` inserts a row in `etsm_deployments` and schedules `etsm_process_deployment` (placeholder).
- Target implementation:
  - Use Action Scheduler to enqueue per-target jobs with bounded concurrency.
  - For each Consumer target:
    - Generate signed webhook call with `artifact_url` (or inline artifact for small payloads).
    - Retry policy: exponential backoff with jitter; cap attempts; record per-target status in `results` JSON.
  - Audit: store correlation ID, per-target timings, final status.

## 10) Security Hardening Plan
- Separate credentials: token used as identifier; store `secret` distinct from token, hashed at rest.
- Replay defense: persist nonces per token for a TTL window; reject re-use; allow small clock skew.
- Capability model: custom caps `manage_template_sync`; minimize exposure of Publisher endpoints to consumer-only auth.
- IP throttling/rate limiting: transient counters per token/IP.
- Input validation: sanitize all route params and body fields; strict schema validation for artifacts.
- HTTPS enforcement and CORS policy (lockdown to known origins if exposing browser-based flows).

## 11) Observability & Logging
- Structured logs (JSON) with correlation IDs:
  - Deployment ID, target site, global_template_id, version, outcome, latency, error class.
- Surfaces:
  - Admin diagnostics pages for last N events.
  - Health endpoints (internal admin-only) with queue depth, last heartbeat per Consumer.
- Alerts: hook email/Slack on failure thresholds.

## 12) Error Handling & Retries
- Error classes: auth, network, validation, conflict/drift, media.
- Use `WP_Error` with codes prefixed `etsm_*` and HTTP status mapping.
- Retries:
  - Network and 5xx → retry with backoff.
  - Validation/auth → no retry; flag as failed.

## 13) Performance & Scalability
- Payload optimization: artifacts are JSON only; omit media blobs.
- Compression: Enable HTTP compression; consider artifact URLs behind CDN with ETag.
- Batching: Queue deployments per 10 sites; stagger starts to avoid thundering herd.
- Caching: ETag/If-None-Match on artifact endpoints.

## 14) Multisite & Standalone
- Works in both. Table prefixes use `$wpdb->prefix`.
- For multisite, scope options and scheduled events per site (`switch_to_blog()` if orchestrating centrally).

## 15) Configuration & Options
- Consumer options: `etsm_publisher_url`, `etsm_site_token`.
- Policies (future): auto-apply vs manual, maintenance windows, conflict resolution.

## 16) Admin UI (React) Roadmap
- Publisher: Registry table, Template detail with JSON diff, Deploy wizard, Consumers list with tags/groups.
- Consumer: Enrollment form, Subscriptions, Pending updates with diff/approve, History with rollback, Health.
- Implementation:
  - Use WP Scripts or Vite; enqueue built assets; localize data (REST base, nonce, caps).

## 17) Testing Strategy
- Unit: HMAC canonical/verify; JSON checksum/validation; media remap unit with mocked downloads.
- Integration: Apply artifact E2E in WP test env; rollback restore.
- E2E: Docker compose: Publisher + N Consumers; deploy flows; failure injection.
- Load: 100+ Consumers with 5MB artifacts (JSON); measure median propagation and error rate.

## 18) Coding Standards & Conventions
- PHP 8.1: strict types where beneficial; typed properties in new classes.
- Namespaces under `LimeAds\ETSM\{Publisher|Consumer|Shared}`.
- Do not store secrets in code; use options with hashed values.
- REST responses via `rest_ensure_response()`; errors via `WP_Error`.

## 19) Example Flows
- Fetch artifact as Consumer (pull):
```http
GET /wp-json/etsm/v1/templates/{global_template_id}
Headers: HMAC headers as above
```
- Push deploy (Publisher → Consumer): `POST /wp-json/etsm/v1/webhook/deploy` with signed headers and `{artifact_url}`.

## 20) Roadmap Notes
- Replace WP-Cron placeholders with Action Scheduler for reliable async and retries.
- Add JSON Schema for artifacts; reject non-conformant payloads early.
- Add WP-CLI commands: `etsm deploy ...`, `etsm consumer:pull`, `etsm debug:logs`.

## 21) Security Checklist (Dev)
- Validate all input; escape output in admin.
- HMAC: match exact route path; protect against path normalization mismatches.
- Store `token_hash` and `secret_hash`; rotate and revoke safely.
- Persist nonces for replay prevention.
- Log and alert on repeated failures per token/IP.

---
For questions or changes, update `docs/MEMORY_BANK.md:1` and keep this guide in sync.
