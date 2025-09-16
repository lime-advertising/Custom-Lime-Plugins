# Lime Remote Manager Product Specification

- **Project Name:** Lime Remote Manager
- **Version:** 0.1
- **Author:** Lime Advertising Inc.
- **Date:** September 2025

---

## 1. Overview

Lime Remote Manager (LRM) enables Lime Advertising to orchestrate and maintain a fleet of distributed WordPress installs from a single control plane. The platform consists of:

1. **Agent MU-Plugin** – deployed to every managed WordPress installation (single or multisite) under Lime’s care. The agent registers as a must-use plugin to guarantee execution and provide a secured remote API surface.
2. **Controller Plugin** – installed on Lime’s centralized WordPress instance. The controller offers dashboards, automation, and audit logging for all connected sites.

The system delivers centralized visibility, coordinates remote actions such as URL changes and rollbacks, and ensures any destructive operations are safeguarded by snapshots and confirmations.

---

## 2. Objectives & Success Criteria

- Provide a secure, authenticated control layer for every managed site.
- Support both single-site and multisite WordPress topologies with feature parity.
- Enforce safety nets (snapshots, confirmations, audit logs) before destructive or high-risk operations.
- Centralize operational telemetry, enabling proactive monitoring and rapid incident response.

**Success Metrics**
- 100% of destructive actions must be preceded by a completed snapshot and confirmation.
- 95% of rollback requests complete within 15 minutes for sites < 10 GB.
- Onboarding a new site (agent install + controller registration) must take < 5 minutes.
- Authentication success rate ≥ 99% for authorized requests; all unauthorized attempts logged.

---

## 3. Functional Requirements

### 3.1 Agent MU-Plugin

- **Location & Autoloading:** Lives in `wp-content/mu-plugins/lime-remote-agent.php` and loads automatically.
- **Secret Generation:** Generates a 256-bit shared secret on activation; exposes retrieval via WP-CLI (`wp lime-remote secret`).
- **Authentication:** All requests validated via HMAC-SHA256 signature (`X-LRM-Signature`) and timestamp header (`X-LRM-Timestamp`, ±300s skew). Replays prevented with nonce cache.
- **Capability Enforcement:**
  - Single-site endpoints require `manage_options`.
  - Multisite-specific endpoints require `manage_network`.
- **REST API (`lrma/v1` namespace):**
  - `GET /info` → Returns site type, WordPress version, plugin/theme lists, health telemetry, latest snapshot metadata.
  - `POST /sites` (multisite) → Lists subsites with metadata (`blog_id`, domain, path, status, snapshot info).
  - `POST /change-url` → Updates the site or subsite URLs. Payload includes `target`, optional `blog_id`, URL fields, and `confirm_token = "CHANGE_URL"`.
  - `POST /delete-site` → Removes a subsite (`blog_id` required) or disables a single-site. Requires `confirm_token = "DELETE_SITE"` and reason string.
  - `POST /snapshot` → Initiates database/uploads snapshot for a site or subsite; returns asynchronous job ID.
  - `POST /rollback` → Restores prior snapshot based on provided `snapshot_id` and optional `blog_id`. Requires `confirm_token = "ROLLBACK"`.
- **Snapshot Engine:**
  - Stores archives under `wp-content/uploads/lrm-snapshots/{blog_id}/{timestamp}/`.
  - Tracks metadata in custom table `wp_lrm_snapshots`.
  - Uses Action Scheduler (or WP-Cron fallback) for background processing and chunked exports.
- **Health Monitoring:** Integrates WordPress Site Health plus custom checks (disk usage, cron activity) and surfaces results via `/info`.
- **Audit Hooks:** Logs every agent request + outcome locally until retrieved by the controller.

### 3.2 Controller Plugin

- **Admin Experience:** Adds "Remote Manager" menu with Dashboard, Site Detail, Logs, Settings pages. React-based UI components served through WordPress admin.
- **Site Registry:** Stores managed site records in `wp_lrm_sites` (`id`, `name`, `base_url`, `secret`, `site_type`, `status`, `last_seen`, `config`). Secrets encrypted before storage.
- **Secure Communication:**
  - `wp_remote_post` with signed payloads mirroring agent auth requirements.
  - Configurable IP allowlist per site and retry policies for transient errors.
- **Dashboard Capabilities:**
  - Display aggregated site cards showing status, type, last heartbeat, outstanding tasks.
  - Multisite detail view lists subsites, enabling per-subsite actions.
  - Filtering, search, and pagination for >50 sites.
- **Remote Actions:**
  - Trigger snapshots, rollbacks, URL/domain/path updates, deletions (with confirm flows), and health refreshes.
  - Each destructive action prompts a double confirmation modal, requires confirm token entry, and optionally sends verification email.
- **Audit Logging:** Persists to `wp_lrm_logs` (action, site ID, subsite ID, user ID, timestamp, payload diff, result). Supports CSV export by range/action/site.
- **Notifications:**
  - Visual admin notices for success/failure.
  - Optional email digests for failed actions or health degradations.

---

## 4. Site-Type Behaviour

### 4.1 Single-Site Installations

- Available actions: snapshot (DB/uploads), change URL (`home`, `siteurl`), rollback latest snapshot, delete/disable site.
- Deletion results in site disabled state; reactivation requires controller command or manual intervention.

### 4.2 Multisite Networks

- `GET /info` distinguishes network context and lists network health.
- `POST /sites` enumerates subsites with metadata.
- Prepare snapshots per `blog_id` using `switch_to_blog` to target specific tables/uploads.
- Allow domain/path updates per subsite through `wp_update_site` and `update_blog_option`.
- Deletion restricted to non-primary subsites (`blog_id != 1`).
- Rollback scoped to target `blog_id` snapshots.

---

## 5. Non-Functional Requirements

- **Performance:** Async snapshot/rollback jobs to avoid HTTP timeouts; streaming exports for large databases; target < 2 min orchestration latency for lightweight operations.
- **Compatibility:** WordPress 6.4+, PHP 8.1+ baseline. Tested with multisite networks up to 200 subsites.
- **Scalability:** Efficient pagination, caching responses for expensive calls (e.g., cache `/info` for 60 seconds). Support >50 registered sites.
- **Security:**
  - Enforce signed, timestamped requests with replay protection.
  - Secrets rotated periodically via controller UI and agent CLI (`wp lime-remote rotate-secret`).
  - Agent endpoints unlinked from admin menus and callable only via REST.
  - IP allowlisting and rate limiting (60 req/min default, filters allow overrides).
- **Reliability:** Retries with exponential backoff for failed remote calls; manual retry buttons in UI. Action queue persists state in database to survive restarts.
- **Localization & Accessibility:** All UI strings translation-ready. React components follow WCAG AA contrast and keyboard navigation patterns.
- **Logging & Retention:** Snapshot and audit logs retained 365 days with scheduled pruning; offsite archival optional.

---

## 6. Technical Architecture

### 6.1 High-Level Flow

1. Controller initiates signed request to agent endpoint.
2. Agent validates signature, capability, confirm token (when required), and timestamp.
3. Agent processes action synchronously (lightweight tasks) or enqueues background job.
4. Agent responds with status (`accepted`, `in_progress`, `completed`, `failed`) and job metadata.
5. Controller updates UI via internal REST API and stores audit record.
6. Background jobs report completion/failure; controller polls status or receives webhook-like notifications in future iterations.

### 6.2 Data Stores

- **Controller DB:** `wp_lrm_sites`, `wp_lrm_logs`, `wp_lrm_jobs` tables; optionally `wp_lrm_settings` for configuration.
- **Agent DB:** `wp_lrm_snapshots`, `wp_lrm_jobs`, local audit buffer table.
- **Snapshot Storage:** Local file system with optional offsite replication (S3/Wasabi) configured via controller.

### 6.3 Security Model

- Shared secrets per site; stored encrypted.
- HMAC over: `HTTP_METHOD + "\n" + REQUEST_PATH + "\n" + X-LRM-Timestamp + "\n" + BODY`.
- Timestamp tolerance set to ±300s (configurable). Nonce + signature combos cached for 10 minutes to block replays.
- Optional mutual TLS or Basic auth at web server layer (documented in setup guide).

### 6.4 Background Processing

- Agent leverages Action Scheduler or custom cron events for snapshot/rollback tasks.
- Controller polls job status via `GET /info` or dedicated endpoint (`GET /jobs/{id}` future enhancement).
- Jobs include progress metadata (percent complete, bytes processed) for UI feedback.

---

## 7. User Experience

- **Dashboard:** Grid of sites with status badges (Healthy, Warning, Critical, Offline). Clicking card opens detail panel.
- **Site Detail:** Tabs for Overview, Subsites (if applicable), Snapshots, Logs. Each action button opens confirmation modal with snapshot requirement summary.
- **Workflow Protections:**
  - Mandatory reason field for deletions.
  - Confirmation token input (user retypes `DELETE` or `CHANGE`) to proceed with destructive actions.
  - Snapshot freshness indicator warns if older than configurable threshold.
- **Audit Logs:** Filterable table (date range, user, action type, result) with export button.
- **Notifications:** Inline success/error toasts; optional daily digest email summarizing failures and pending actions.

---

## 8. Deployment & Operations

- **Agent Deployment:**
  - Install MU-plugin via SFTP/Git/Composer; plugin auto-generates secret.
  - Run `wp lime-remote secret` to retrieve pairing token.
  - Ensure REST API publicly accessible; configure firewall/IP rules to allow controller IPs.
- **Controller Deployment:**
  - Standard plugin install on central WordPress.
  - Activation wizard checks PHP/WordPress versions, creates DB tables, configures cron jobs, and tests outbound HTTP.
- **Onboarding Workflow:**
  1. Deploy agent and obtain secret.
  2. In controller UI, add site (name, base URL, secret, optional tags).
  3. Controller performs handshake (ping `/info`, validate signature).
  4. Upon success, site appears in dashboard.
- **Monitoring:** Integrate with StatsD for action duration metrics; forward PHP errors to Sentry/Logstash.
- **Backup Strategy:** Local snapshots are retained per retention policy (default 30 days). Optional configuration to copy snapshots to S3/Wasabi asynchronously.

---

## 9. Risks & Mitigations

| Risk | Impact | Mitigation |
| ---- | ------ | ---------- |
| Snapshot timeout on large sites | Failed safety net before destructive action | Chunked exports, async jobs, configurable timeouts, operator alerts |
| Incorrect URL change causing outage | Site inaccessible post-change | Enforce pre-change snapshots, validation checks, quick rollback button |
| Credential/secret exposure | Unauthorized access to remote controls | Encrypted secret storage, rotation support, IP allowlists, audit monitoring |
| Controller downtime | Remote operations unavailable | Cache last-known health data, read-only mode, documented manual rollback process |
| Data growth (snapshots/logs) | Storage exhaustion | Configurable retention and pruning, optional offsite archival |

---

## 10. Deliverables

1. Agent MU-plugin source with unit tests for authentication and endpoint logic.
2. Controller plugin source with integration tests for registry, action execution, and logging.
3. Setup guide covering deployment, pairing, firewall considerations, and secret rotation.
4. Operations handbook detailing backup retention, rollback SOPs, and incident response.
5. API reference appendix documenting request/response schemas, headers, and error codes.

---

## 11. Future Enhancements

- Remote plugin/theme updates and core upgrades from controller.
- Integrated uptime/security monitoring with alerting.
- Bulk operations (e.g., change URL across multiple sites simultaneously).
- Webhook notifications for job status, failures, and health changes.
- Multi-factor confirmation (e.g., email OTP) for destructive actions.
- Remote WP-CLI command execution with scoped permissions.

---

## 12. Appendices

### 12.1 Error Codes

| Code | Description | Recommended Action |
| ---- | ----------- | ------------------ |
| `LRM_AUTH_FAILED` | Signature or timestamp invalid | Verify secret, time sync, and request signing logic |
| `LRM_CAPABILITY_DENIED` | Current user lacks required capability | Ensure agent-side capability checks pass (manage_options/network) |
| `LRM_CONFIRM_TOKEN_MISSING` | Required confirm token absent or incorrect | Reissue request with appropriate token (e.g., `DELETE_SITE`) |
| `LRM_SNAPSHOT_REQUIRED` | Operation blocked due to missing fresh snapshot | Trigger snapshot before retrying destructive action |
| `LRM_JOB_TIMEOUT` | Background task exceeded allotted time | Investigate server resources; consider adjusting timeout |

### 12.2 Configuration Defaults

- Snapshot retention: 30 days
- Log retention: 365 days
- Request timestamp tolerance: 300 seconds
- Rate limit: 60 requests/minute per site
- Health cache TTL: 60 seconds

