# CareerNest Plugin — Progress Log

Important: This file is updated only when requested by the project owner. Do not modify status entries without explicit instruction.

## How This Log Works

- Milestones mirror the Technical Plan (docs/TECHNICAL_PLAN.md).
- After each successful update (upon approval), add a dated entry under the relevant milestone with:
  - What changed (high level)
  - Files touched
  - Verification steps performed
  - Any follow‑ups or blockers

## Milestones Checklist (initial)

- [x] M1: Scaffold plugin, activation/deactivation, options, rewrite flush
- [x] M2: CPTs & taxonomies
- [x] M3: Roles & capabilities; admin menus
- [ ] M4: Meta boxes; save handlers; sanitization & nonces
- [ ] M5: Activation page creation; template routing; page IDs stored
- [ ] M6: Frontend job listing and single job
- [ ] M7: Registration flows and CPT linkage
- [ ] M8: Applications and notifications
- [ ] M9: Settings (API keys, emails, general)
- [ ] M10: Ownership restrictions; query filters; admin columns
- [ ] M11: Polishing, security hardening, tests, docs

## Change Log

### 2025-08-22 — Milestone M1: Bootstrap & Activation

- Summary: Scaffolded plugin structure, activation/deactivation flow, seeded options, created required pages (public/private), stored page IDs, and hid managed pages from non-admins.
- Files: `careernest.php`, `includes/class-activator.php`, `includes/class-deactivator.php`, `includes/class-plugin.php`, `templates/template-jobs.php`, `templates/template-employer-dashboard.php`, `templates/template-applicant-dashboard.php`, `templates/template-register-employer.php`, `templates/template-register-applicant.php`, `templates/template-apply-job.php`
- Verification: Activated plugin without errors; confirmed pages created with correct slugs and visibility; verified `careernest_pages` option contains IDs; checked Pages list as non-admin hides managed pages.
- Notes: Template routing via `template_include` and CPTs/taxonomies to be added in Milestones M5 and M2 respectively.

### 2025-08-22 — Milestone M2: CPTs & Taxonomies

- Summary: Implemented core CPTs (`job_listing`, `employer`, `applicant`, `job_application`) and taxonomies (`job_category`, `job_type`); added admin icons; disabled Gutenberg and stripped block assets for these CPTs; ensured registration occurs before rewrite flush on activation.
- Files: `includes/Data/class-cpt.php`, `includes/Data/class-taxonomies.php`, `includes/class-plugin.php`, `includes/class-activator.php`, `careernest.php`
- Verification: Activated plugin; confirmed Jobs archive at `/jobs/` loads; CPT menus appear with icons; taxonomy metaboxes visible on Job edit screen; classic editor loads with meta boxes; block editor UI/assets absent on CPT edit screens.
- Notes: Blocks can be enabled per CPT in future by adjusting the `use_block_editor_for_post_type` filter and exposing meta via REST as needed.

### 2025-08-22 — Milestone M3: Roles, Capabilities, Admin Menus

- Summary: Added plugin roles (`aes_admin`, `employer_team`, `applicant`) with custom caps and ensured admin has plugin caps; introduced a unified “CareerNest” admin menu and hid default CPT menus; implemented admin redirects for applicants/employer team to their dashboards; added frontend dashboard access redirects; hid admin bar for applicant and employer team; made dashboards public.
- Files: `includes/Data/class-roles.php`, `includes/Admin/class-admin.php`, `includes/Admin/class-admin-menus.php`, `includes/Security/class-caps.php`, `includes/Data/class-cpt.php`, `includes/class-plugin.php`, `includes/class-activator.php`, `careernest.php`
- Verification: Confirmed roles exist and permissions behave as expected; CareerNest menu visible with submenus; CPT menus hidden elsewhere; applicants/employer team redirect from wp-admin to correct dashboard; accessing wrong dashboard redirects appropriately; admin bar hidden on frontend for both roles.
- Notes: Future milestone will refine ownership checks and granular capabilities via `map_meta_cap` and query filtering.

### 2025-08-22 — M3 Update: Admin UI Hierarchy + Dashboard Cards

- Summary: Added hierarchical section headers (with icons) to the CareerNest submenu and replaced the main page with overview cards for Jobs, Employers, Applicants, and Applications including counts and quick actions.
- Files: `includes/Admin/class-admin-menus.php`, `assets/css/admin.css`
- Verification: Submenu shows non-clickable, iconized section headers; landing page renders four responsive cards with counts matching `wp_count_posts` totals and “Manage” / “Add New” links.
- Notes: Colors and icons are easily adjustable; can extend cards with additional KPIs in future.

## Template for Future Entries

### YYYY‑MM‑DD — Milestone X: Short Title

- Summary: One or two sentences on what was completed.
- Files: `path/one.php`, `path/two.php`, ...
- Verification: List the key manual or automated checks run.
- Notes: Known limitations, follow‑ups, or decisions.
