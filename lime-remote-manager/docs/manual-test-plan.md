# Lime Remote Manager Manual Test Plan

## 1. Purpose & Scope
- **Purpose:** Define the manual validation process for Lime Remote Manager (LRM) before each release or environment promotion.
- **Scope:** Covers controller plugin, agent MU-plugin, integration flows between them, and operational safeguards. Automated tests complement but do not replace these manual checks.
- **Out of Scope:** Load/stress testing, third-party plugin compatibility beyond documented dependencies.

## 2. Test Phases
1. **Environment Qualification:** Verify prerequisites, access, and configuration for controller and managed sites.
2. **Installation & Pairing:** Validate setup workflow and confirm sites register correctly.
3. **Functional Scenarios:** Exercise routine operations (snapshots, rollbacks, URL changes, health refresh) on single-site and multisite environments.
4. **Safety & Security:** Confirm destructive action safeguards, audit logging, and authentication controls.
5. **Regression Sweep:** Reconfirm critical user journeys from previous incidents or resolved bugs.

## 3. Test Environment
- **Controller:** Staging WordPress instance mirroring production configuration (PHP 8.1+, WP 6.4+, HTTPS). Sample admin account with `manage_options`.
- **Managed Sites:**
  - One single-site WordPress staging environment.
  - One multisite network with ≥3 subsites (including primary `blog_id=1`).
- **Network:** Controller able to reach agent endpoints over HTTPS; adjustable firewalls to simulate blocking scenarios.
- **Test Data:** Sample posts/pages, media uploads (~500 MB) to validate snapshot coverage. Dummy subsite domains.

## 4. Roles & Responsibilities
- **Test Lead:** Coordinates schedule, assigns testers, consolidates results.
- **Controller Tester:** Executes UI-based scenarios and audit checks.
- **Agent Tester:** Runs WP-CLI commands, inspects logs, manipulates server state.
- **Observer/Scribe:** Captures evidence (screenshots, logs) and records pass/fail status.

## 5. Entry & Exit Criteria
- **Entry:**
  - Build deployed to staging environments.
  - Release notes and change list reviewed.
  - Test data reset and secrets rotated for the cycle.
- **Exit:**
  - All critical and high-severity test cases passed or have approved workarounds.
  - Defects logged in tracker with reproduction steps and priority.
  - Test report delivered to stakeholders with sign-off.

## 6. Tools & Resources
- WP Admin access to controller and agent sites.
- WP-CLI (controller & agent hosts).
- HTTP client (Insomnia/Postman) for signing and sending test requests.
- Time sync verification tool (`chronyc`, `ntpstat`).
- Issue tracker project (e.g., Jira LRM board).

## 7. Test Data Preparation
- Create controller admin user `lrm_tester` with strong password.
- On multisite, configure subsites: `stage-alpha`, `stage-beta`, `stage-gamma` with distinct domains/paths.
- Populate content: 50 posts, 20 media files per site.
- Record baseline health status and latest snapshot IDs prior to testing.

## 8. Test Schedule (Suggested)
| Phase | Duration | Owner |
| ----- | -------- | ----- |
| Environment qualification | Day 1 AM | Agent Tester |
| Installation & pairing | Day 1 PM | Controller Tester |
| Functional scenarios | Day 2 | Controller Tester, Agent Tester |
| Safety & security | Day 3 AM | Security liaison |
| Regression sweep & sign-off | Day 3 PM | Test Lead |

## 9. Test Cases

### 9.1 Environment Qualification
| ID | Title | Precondition | Steps | Expected Result |
| -- | ----- | ------------ | ----- | ---------------- |
| EQ-01 | Verify controller prerequisites | Controller staging deployed | Check PHP/WP versions, HTTPS, DB permissions via WP admin | All prerequisites meet spec; document versions |
| EQ-02 | Validate agent prerequisites | Agent staging servers reachable | Confirm WP version, PHP version, MU-plugin folder write access | Agent meets baseline requirements |
| EQ-03 | Clock synchronization | Servers powered on | Run `ntpstat` or equivalent on each host | Offset < 2s; otherwise log defect |

### 9.2 Installation & Pairing
| ID | Title | Precondition | Steps | Expected Result |
| -- | ----- | ------------ | ----- | ---------------- |
| IP-01 | Install controller plugin | Controller prerequisites verified | Upload plugin, activate, complete wizard | Activation succeeds; tables created; cron jobs scheduled |
| IP-02 | Deploy agent MU-plugin | Agent prerequisites verified | Copy MU-plugin, ensure autoload, run `wp lime-remote secret` | Secret generated; no fatal errors in logs |
| IP-03 | Pair single-site | IP-01/IP-02 complete | Add site via controller UI using shared secret | Site appears Healthy; audit log entry created |
| IP-04 | Pair multisite | Multisite agent installed | Add multisite; open detail page | Subsites list displayed; primary site flagged as protected |

### 9.3 Functional Operations (Single Site)
| ID | Title | Precondition | Steps | Expected Result |
| -- | ----- | ------------ | ----- | ---------------- |
| FO-S-01 | Trigger snapshot | Single-site paired | Create full snapshot via UI | Job completes; archive stored; audit log entry |
| FO-S-02 | Change site URL | Snapshot <24h old | Update home/siteurl to new staging domain, confirm token | Site accessible at new URL; old URL redirects if configured |
| FO-S-03 | Rollback snapshot | Existing snapshot available | Initiate rollback to prior snapshot | Content reverts; audit log records action |
| FO-S-04 | Disable site | Stakeholder approval documented | Initiate delete/disable flow with reason | Site status = Disabled; front-end shows maintenance page |

### 9.4 Functional Operations (Multisite)
| ID | Title | Precondition | Steps | Expected Result |
| -- | ----- | ------------ | ----- | ---------------- |
| FO-M-01 | Snapshot subsite | Multisite paired | Trigger snapshot for `stage-beta` | Subsite snapshot stored under correct blog ID |
| FO-M-02 | Change subsite domain/path | Snapshot fresh | Update domain/path for `stage-beta` | Subsite accessible at new domain/path; network admin reflects change |
| FO-M-03 | Delete subsite | Approval captured | Delete `stage-gamma` subsite | Subsite removed from list; primary site untouched |
| FO-M-04 | Protect primary site | Attempt to delete blog_id=1 | Execute delete flow on primary subsite | Operation blocked with informative error |

### 9.5 Safety & Security
| ID | Title | Precondition | Steps | Expected Result |
| -- | ----- | ------------ | ----- | ---------------- |
| SS-01 | Confirm token enforcement | Any site paired | Attempt destructive action without token | Request rejected with `LRM_CONFIRM_TOKEN_MISSING` |
| SS-02 | Snapshot requirement | No fresh snapshot | Attempt URL change; do not create snapshot | Operation blocked with `LRM_SNAPSHOT_REQUIRED` |
| SS-03 | Invalid signature rejection | Access to HTTP client | Send request with altered signature | Agent returns `401` and logs attempt |
| SS-04 | Timestamp drift handling | Adjust test client clock | Send request with timestamp outside 5 min window | Request rejected with timestamp error |

### 9.6 Audit & Logging
| ID | Title | Precondition | Steps | Expected Result |
| -- | ----- | ------------ | ----- | ---------------- |
| AL-01 | Audit log completeness | Actions executed | Review `Remote Manager → Logs` | Each action recorded with user, timestamp, payload diff |
| AL-02 | Log export | Logs available | Export CSV for date range | CSV downloads, contains expected records |
| AL-03 | Snapshot metadata | Snapshots created | Inspect `wp_lrm_snapshots` table | Entries correspond to UI snapshots |

### 9.7 Negative & Regression Cases
| ID | Title | Precondition | Steps | Expected Result |
| -- | ----- | ------------ | ----- | ---------------- |
| NR-01 | Rate limit handling | Set low rate limit for testing | Trigger > limit requests rapidly | Controller surfaces rate limit message; retries back off |
| NR-02 | Secret rotation | Existing site paired | Rotate secret on agent, update controller | Old secret rejected; new secret accepted |
| NR-03 | Action queue recovery | Pause Action Scheduler | Attempt snapshot; resume scheduler | Job resumes and completes after scheduler restarts |

## 10. Defect Management
- Log defects in project tracker with prefix `LRM-MANUAL`.
- Include: environment, test case ID, reproduction steps, expected vs actual, screenshots/logs.
- Assign severity per SLA (Critical, High, Medium, Low).

## 11. Reporting & Sign-Off
- Daily standup during testing window to review progress and blockers.
- Final report includes summary table of executed cases, pass/fail counts, outstanding defects, risk assessment.
- Sign-off required from Test Lead, Product Owner, and Platform Engineering representative before release promotion.

## 12. Appendix
- **Reference Docs:** Product Spec, Setup Guide, Operations Handbook (latest versions in `docs/`).
- **Contact List:** Same as Operations Handbook Appendix 9.2.
- **Revision History:**
  - v0.1 (Sep 2025) – Initial manual test plan drafted.

