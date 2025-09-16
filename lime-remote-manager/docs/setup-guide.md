# Lime Remote Manager Setup Guide

## 1. Introduction
- **Purpose:** Provide administrators with a repeatable process for deploying Lime Remote Manager (LRM) across the controller site and managed WordPress installations.
- **Audience:** DevOps engineers, WordPress admins, and onboarding specialists responsible for preparing environments and pairing sites.
- **Prerequisites:** Familiarity with WordPress administration, command-line access to managed sites, ability to modify firewall rules.

## 2. Terminology & Roles
- **Controller:** Central WordPress instance running the LRM controller plugin.
- **Agent:** Must-use plugin deployed to a managed WordPress installation.
- **Controller Admin:** Lime operator with `manage_options` on the controller site.
- **Site Maintainer:** Local admin on client WordPress site (no UI access to LRM).

## 3. System Requirements
### 3.1 Controller Environment
- WordPress 6.4 or higher, PHP 8.1+, HTTPS enabled.
- Outbound HTTP(S) allowed to all managed sites (ports 80/443).
- Database user with permissions to create custom tables.
- WP-CLI optional but recommended for diagnostics.

### 3.2 Managed Site Environment
- WordPress 6.4+ (single or multisite), PHP 8.1+.
- Access to deploy files into `wp-content/mu-plugins/`.
- WP-CLI access for secret retrieval and diagnostics.
- In multisite, ensure `wpmu_delete_blog` and `wp_update_site` functions are not restricted.

### 3.3 Network & Security Prerequisites
- Synchronize server clocks via NTP (timestamp tolerance ±300 seconds).
- Allow inbound requests from controller IP to `/wp-json/lrma/v1/*` routes.
- Optional: configure mutual TLS or Basic Auth at reverse proxy if policy requires.

## 4. Installation Workflow
### 4.1 Prepare Controller
1. Backup the controller site database.
2. Ensure WordPress and PHP meet minimum versions.
3. Verify cron and Action Scheduler are operational.

### 4.2 Install Controller Plugin
1. Upload the `lime-remote-controller` plugin via WP admin or deploy via Git/Composer.
2. Activate the plugin. The activation wizard will:
   - Check PHP/WordPress versions.
   - Create required database tables (`wp_lrm_sites`, `wp_lrm_logs`, etc.).
   - Schedule cron jobs for log pruning and job polling.
3. Complete wizard prompts:
   - Enter default snapshot retention (e.g., 30 days).
   - Configure outbound request timeout and retry policy.
   - Provide controller IP allowlist comments if needed.

### 4.3 Deploy Agent MU-Plugin
1. Place `lime-remote-agent` directory under `wp-content/mu-plugins/` on the managed site.
2. Verify the main file `lime-remote-agent.php` autoloads without errors.
3. Run `wp plugin list` to confirm the MU-plugin is visible as `true` under `Must Use` column.
4. Retrieve the generated shared secret either via `wp lime-remote secret` or by temporarily defining `LIMERM_AGENT_ADMIN_UI` as `true` in `wp-config.php` and visiting **Tools → Lime Remote Agent**. Store the secret securely and remove/disable the constant after use.
5. (Optional) Run `wp lime-remote health-check` to validate prerequisites.

### 4.4 Network Adjustments
1. Add controller IP(s) to any firewalls or WAF allowlists on the managed site.
2. Confirm the controller can reach `https://client-site/wp-json/lrma/v1/info` (expect authentication error until paired).
3. If using reverse proxies/CDN, ensure REST namespace `/wp-json/lrma/v1` bypasses caching.

## 5. Pairing Sites with Controller
1. Sign in to the controller WordPress admin as a controller admin.
2. Navigate to **Remote Manager → Sites → Add Site**.
3. Provide site details:
   - Display name and tags.
   - Base URL (protocol + domain).
   - Shared secret retrieved from the agent.
   - Optional IP restrictions and snapshot retention overrides.
4. Initiate pairing. The controller performs a signed handshake:
   - Calls `GET /info` to detect site type and health status.
   - Validates HMAC signature and timestamp.
   - Stores site metadata on success.
5. Confirm the site appears on the dashboard with status **Healthy**.
6. For multisite installations, open site detail to ensure subsites enumerate correctly.

### 5.1 Troubleshooting Pairing
- **AUTH_FAILED:** Verify the shared secret and server time synchronization.
- **TIMEOUT:** Confirm firewall permissions and DNS resolution.
- **CAPABILITY_DENIED:** Ensure the agent can authenticate with required capability (`manage_network` for multisite).
- **EMPTY SUBSITES LIST:** Switch to `Network Admin → Sites` to confirm multisite configuration; run `wp site list` as fallback.

## 6. Post-Install Tasks
- Schedule secret rotation cadence (e.g., quarterly) and document storage location.
- Configure IP allowlists both on agent servers and controller outbound firewall.
- Verify Action Scheduler queue contains snapshot/rollback hooks and is processing.
- Enable optional offsite snapshot storage (S3/Wasabi) via controller settings.
- Set up monitoring alerts (StatsD/Sentry) following internal policy.

## 7. Validation Checklist
- [ ] Controller dashboard displays new site with accurate status.
- [ ] Manual `GET /wp-json/lrma/v1/info` (signed via controller) succeeds.
- [ ] Test snapshot completes (observe job ID, ensure stored in `lrm-snapshots`).
- [ ] Perform non-destructive action (e.g., health refresh) and confirm audit log entry.
- [ ] For multisite, confirm subsite list matches `wp site list` output.
- [ ] Verify cron events scheduled (`lrm_agent_snapshot_prune`, `lrm_controller_log_prune`).

## 8. Appendix
### 8.1 Quick Reference Commands
- `wp lime-remote secret` – display current shared secret.
- `wp lime-remote rotate-secret` – generate and output new secret.
- `wp lime-remote health-check` – validate prerequisites on agent host.
- `wp cron event list | grep lrm` – ensure scheduled tasks exist.

### 8.2 Support Contacts
- **LRM Platform Team:** platform@limeadvertising.example
- **Emergency Hotline:** +1-555-LIME-OPS (24/7)
- **Documentation Hub:** https://docs.limeadvertising.example/lrm
