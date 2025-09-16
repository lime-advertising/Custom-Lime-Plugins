# Lime Remote Manager Operations Handbook

## 1. Purpose & Audience
- **Purpose:** Equip Lime controller administrators and on-call operators with procedures to maintain, monitor, and respond to events involving Lime Remote Manager (LRM).
- **Audience:** Platform operations, incident response, compliance auditors.

## 2. Environment Overview
- **Controller Site:** Hosts the LRM controller plugin, registry database tables, audit logs, and job orchestration.
- **Managed Sites:** WordPress single or multisite installs running the agent MU-plugin exposing REST endpoints and background workers.
- **Snapshot Storage:** Local `wp-content/uploads/lrm-snapshots/` directories with optional replication to cloud storage (S3/Wasabi).
- **Communication:** Signed HTTPS requests from controller to agent endpoints (`/wp-json/lrma/v1/*`).

## 3. Routine Operations
### 3.1 Daily/Weekly Tasks
- Review dashboard summary for degraded health states (Warning/Critical/Offline).
- Check Action Queue widget for long-running or stalled jobs.
- Confirm latest audit entries are processing; export weekly digest for compliance.
- Validate storage utilization (local and cloud) stays below 80% threshold.

### 3.2 Monthly Tasks
- Rotate secrets per schedule (documented below) and verify handshake post-rotation.
- Audit retention jobs ensuring snapshots/logs older than policy are pruned.
- Update LRM plugins to latest release after staging validation.

## 4. Action Procedures
Each operation includes prerequisites, steps, and verification.

### 4.1 Trigger Snapshot
- **Pre-checks:** Ensure site status is Healthy or Warning; verify sufficient disk space.
- **Steps:**
  1. Open site detail → `Snapshots` tab → `Create Snapshot`.
  2. Choose scope (Full/DB/Uploads), confirm retention note.
  3. Submit; note job ID returned.
- **Verification:**
  - Job status transitions to `completed` within expected SLA.
  - Snapshot listed with timestamp; check audit log entry.

### 4.2 Perform Rollback
- **Pre-checks:** Confirm target snapshot integrity; ensure recent backup copy preserved.
- **Steps:**
  1. Select snapshot → `Rollback`.
  2. Enter reason and confirm token `ROLLBACK`.
  3. Acknowledge downtime warning; start rollback.
- **Verification:**
  - Site returns to expected state (content, URLs, plugins).
  - Audit log records action with success state.
  - Inform stakeholders upon completion.

### 4.3 Change URL / Domain / Path
- **Pre-checks:** Ensure fresh snapshot (<24h). Validate new URL resolves.
- **Steps:**
  1. From site detail, open `Change URL` modal.
  2. Provide new `home`/`siteurl` (single) or domain/path (multisite `blog_id`).
  3. Enter confirmation token `CHANGE_URL` and acknowledgment text.
- **Verification:**
  - Controller receives success response; site accessible at new URL.
  - Audit log stores old vs new values.
  - Run quick front-end test (HTTP 200) and confirm login works.

### 4.4 Delete Subsite / Disable Site
- **Pre-checks:** Confirm stakeholder approval and recent snapshot.
- **Steps:**
  1. Navigate to target site/subsite entry.
  2. Choose `Delete` (subsite) or `Disable` (single site) and enter reason.
  3. Input confirmation token `DELETE_SITE` and optional ticket reference.
- **Verification:**
  - Subsite removed from list (multisite) or site status changes to Disabled.
  - Snapshot retained per policy; audit log captures request.

### 4.5 Refresh Health Data
- **Pre-checks:** None.
- **Steps:** Click `Refresh Health` in site header or `Bulk Refresh` on dashboard.
- **Verification:**
  - Timestamp updates; check for resolved warnings.

## 5. Incident Response Playbooks

### 5.1 Agent Unreachable
- **Symptoms:** Controller displays `Offline`, requests timeout, `LRM_HTTP_TIMEOUT` errors.
- **Actions:**
  1. Ping site (ICMP/HTTP) to rule out network outage.
  2. Verify firewall permits controller IP; check CDN/WAF logs.
  3. SSH into site; ensure MU-plugin file intact; run `wp cron event run --due-now` to process pending tasks.
  4. Check PHP error logs for fatal errors in `lime-remote-agent`.
  5. Escalate to site maintainer if hosting issue persists.

### 5.2 Snapshot Failure
- **Symptoms:** Job remains `failed`, error code `LRM_SNAPSHOT_ERROR`.
- **Actions:**
  1. Review job detail for error message (disk full, permission denied).
  2. Validate disk space (`df -h`); clean old snapshots if necessary.
  3. Re-run snapshot; monitor Action Scheduler queue for stuck hooks.
  4. If recurring, open incident and engage infrastructure team.

### 5.3 Rollback Failure
- **Symptoms:** Job `failed`, site still degraded.
- **Actions:**
  1. Verify snapshot archive integrity (`wp lrm snapshot verify <id>` if available).
  2. Check DB credentials and file permissions.
  3. Attempt manual restore using stored archive per SOP.
  4. Document timeline; escalate to senior engineer.

### 5.4 Authentication/Signature Errors
- **Symptoms:** Logs show `LRM_AUTH_FAILED`, `LRM_TIMESTAMP_SKEW`.
- **Actions:**
  1. Confirm controller and agent clocks synchronized (NTP).
  2. Rotate secret via agent (`wp lime-remote rotate-secret`) and update controller record.
  3. Review for unauthorized attempts and note in incident log.

### 5.5 Controller Outage
- **Symptoms:** Controller admin UI unreachable or errors; queue halted.
- **Actions:**
  1. Notify stakeholders; declare controller read-only mode.
  2. Restore from latest backup if required.
  3. Pull cached health data to continue monitoring.
  4. Upon recovery, audit logs for gaps and reconcile.

## 6. Audit & Compliance
- Export audit logs monthly via **Remote Manager → Logs → Export CSV**.
- Retain CSV exports per compliance retention (minimum 3 years).
- Review log entries for destructive actions; confirm approvals documented.
- Maintain change approval tickets linked to each high-risk operation.

## 7. Maintenance Tasks
### 7.1 Secret Rotation
- Schedule quarterly rotation per site.
- Steps:
1. On agent, rotate the secret via `wp lime-remote rotate-secret` **or** temporarily enable the admin UI by defining `LIMERM_AGENT_ADMIN_UI` as `true`, rotating from **Tools → Lime Remote Agent**, then disabling the constant again.
2. Update controller site record with new secret immediately.
3. Perform handshake test to confirm connectivity.
- Document rotation in change log (see Appendix).

### 7.2 Plugin Updates
- Test updates on staging controller/site pair.
- Apply updates during maintenance window; monitor logs for regressions.

### 7.3 Storage Pruning
- Verify pruning cron jobs succeed (audit `lrm_controller_log_prune`).
- Manually delete stale snapshots if storage > 80%.
- For cloud storage, ensure lifecycle policies mirror on-prem retention.

### 7.4 Scaling Operations
- When onboarding >10 sites simultaneously, stagger pairing to avoid rate limits.
- Monitor controller resource usage (CPU, DB connections) and scale hosting as needed.

## 8. Metrics & Reporting
- **KPIs:** Snapshot success rate, average rollback duration, number of offline sites, authentication failure count.
- **Dashboards:** Integrate with Grafana/Looker using exported StatsD metrics.
- **Alerts:** Configure thresholds (e.g., >3 consecutive failures triggers on-call page).
- **Monthly Report Template:** Summary of actions, incidents, storage utilization, compliance attestations.

## 9. Appendices
### 9.1 Error Code Reference
- `LRM_AUTH_FAILED` – Signature mismatch; resync clocks/secret.
- `LRM_CONFIRM_TOKEN_MISSING` – Confirmation token absent; retry with token.
- `LRM_JOB_TIMEOUT` – Background job exceeded time; investigate worker.
- `LRM_SNAPSHOT_REQUIRED` – Requested operation blocked; create snapshot.
- `LRM_RATE_LIMITED` – Too many requests; slow down automation.

### 9.2 Escalation Matrix
- **Level 1:** On-call controller admin (pager group `LRM-ONCALL`).
- **Level 2:** Platform engineer lead (contact: platform-lead@limeadvertising.example).
- **Level 3:** Director of Technology (contact: cto@limeadvertising.example).

### 9.3 Change Log Template
```
Change ID: LRM-YYYY-NNN
Date/Time (UTC):
Operator:
Sites Affected:
Action Summary:
Pre/Post Snapshot IDs:
Validation Results:
Related Ticket:
Notes:
```
