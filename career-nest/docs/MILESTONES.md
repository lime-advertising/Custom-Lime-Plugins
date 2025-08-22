# CareerNest — Milestones Log

Purpose: Track each milestone with clear What/Why/How notes for durable project knowledge. Update this file after completing each milestone.

Conventions
- Keep entries concise and factual; link files/paths.
- Use past tense for completed work; present/future for planned follow‑ups.
- Avoid duplication with `docs/PROGRESS.md`; this file captures the narrative of changes, not every micro‑step.

---

## Milestone M1 — Bootstrap & Activation
Date: 2025‑08‑22
Owner: Grok
Status: Completed

- What
  - Scaffolded plugin structure and runtime bootstrap.
  - Implemented activation/deactivation routines and seeded default options.
  - Programmatically created required frontend pages and stored IDs.
  - Hid managed pages from Pages list for non‑admins.
  - Added placeholder templates for key pages.

- Why
  - Establish a stable foundation for subsequent milestones (CPTs, roles, templates).
  - Ensure required pages exist consistently across environments without manual setup.
  - Keep admin UI uncluttered by hiding system‑managed pages.
  - Prepare routing for future template loading logic.

- How
  - Files
    - `careernest.php`: plugin header, constants, activation/deactivation hooks, bootstrap.
    - `includes/class-activator.php`: creates pages, seeds `careernest_options`, stores `careernest_pages`, flushes rewrites.
    - `includes/class-deactivator.php`: flushes rewrites on deactivation.
    - `includes/class-plugin.php`: `pre_get_posts` filter to hide pages with `_careernest_hidden = 1` for users without `manage_options`.
    - `templates/…`: placeholder files for jobs, dashboards, registration, and apply.
  - Key APIs
    - Activation: `register_activation_hook`, `wp_insert_post`, `update_post_meta`, `update_option`, `flush_rewrite_rules`.
    - Admin filter: `pre_get_posts` with `meta_query` to exclude hidden pages.
  - Data
    - Options: `careernest_options` (defaults: `delete_on_uninstall`, `maps_api_key`), `careernest_pages` (IDs per slug).
    - Page meta: `_careernest_hidden = 1`, `_wp_page_template = <template>.php`.
  - Verification
    - Plugin activates without errors; pages created with expected slugs and visibility (private/public).
    - `careernest_pages` contains created IDs; pages hidden from non‑admin Pages list.

- Notes
  - Template loading via `template_include` and CPT‑specific single templates will be implemented in M5/M2.

---

## Milestone M2 — CPTs & Taxonomies
Date: 2025‑08‑22
Owner: Grok
Status: Completed

- What
  - Registered CPTs: `job_listing` (public, archive), `employer` (public profiles), `applicant` (private), `job_application` (private).
  - Registered taxonomies on jobs: `job_category` (hierarchical) and `job_type` (non‑hierarchical), both REST‑enabled.
  - Added admin `menu_icon` for each CPT for better UX.
  - Disabled Gutenberg for all four CPTs and stripped block assets on their edit screens to stabilize meta box UX and reduce payload.
  - Ensured CPT/Tax registration runs during activation before `flush_rewrite_rules()`.

- Why
  - Establish the core data model in line with the technical plan to support listings, organizations, profiles, and applications.
  - Improve admin usability (icons) and keep the editing experience stable and lightweight while we build meta boxes and flows.

- How
  - Files
    - `includes/Data/class-cpt.php`: CPT definitions with labels, supports, archive/rewrite, and `menu_icon`.
    - `includes/Data/class-taxonomies.php`: Taxonomy definitions with REST and rewrite slugs.
    - `includes/class-plugin.php`: Registers CPTs/Tax on `init`; disables block editor; dequeues block assets on edit screens.
    - `includes/class-activator.php`: Calls CPT/Tax register methods during activation prior to `flush_rewrite_rules()`.
    - `careernest.php`: Requires the new Data classes.
  - Key APIs
    - `register_post_type`, `register_taxonomy`, `add_action('init', ...)`, `register_activation_hook`, `flush_rewrite_rules`.
    - `use_block_editor_for_post_type` filter; `admin_enqueue_scripts` to dequeue block assets.
  - Verification
    - CPTs visible in admin; jobs accessible at `/jobs/` without 404 after activation.
    - Taxonomy metaboxes present on Job edit screens; terms assignable.
    - Classic editor loads; no Gutenberg UI/scripts on CPT edit screens; meta boxes render normally.

- Notes
  - When we introduce blocks later, we’ll re‑enable block editor per CPT and expose relevant meta via `register_post_meta(..., ['show_in_rest' => true])`.

---

## Milestone M3 — Roles & Capabilities; Admin Menus
Date: 2025‑08‑22
Owner: Grok
Status: Completed

- What
  - Added roles and caps: `aes_admin`, `employer_team`, `applicant`; granted plugin caps to administrators.
  - Created top‑level “CareerNest” admin menu with submenus (Jobs, Add New, Categories, Types, Applications, Employers, Applicants, Settings).
  - Hid default CPT menus by setting `show_in_menu => false` and routing via CareerNest menu.
  - Redirected applicants and employer team members from wp‑admin to their respective dashboards (profile page allowed).
  - Added frontend dashboard redirects (applicant/employer) and hid the admin bar for both applicants and employer team members.
  - Ensured dashboards are public pages and added runtime `ensure_caps()` for role capability drift.

- Why
  - Provide least‑privilege access and a clear, unified admin navigation for plugin entities.
  - Keep low‑privilege users out of wp‑admin while giving them a dedicated dashboard experience.

- How
  - Files
    - `includes/Data/class-roles.php`: add/remove roles; `ensure_caps()`; added `read_private_pages` earlier, then moved dashboards to public and kept ensure.
    - `includes/Admin/class-admin-menus.php`: top‑level CareerNest menu and submenus.
    - `includes/Admin/class-admin.php`: admin redirects for applicants and employer team.
    - `includes/Security/class-caps.php`: initial `map_meta_cap` stubs for custom caps.
    - `includes/Data/class-cpt.php`: set `show_in_menu` to false for CPTs so they appear under CareerNest.
    - `includes/class-plugin.php`: hide admin bar for applicants and employer team; frontend dashboard access redirects.
    - `includes/class-activator.php`: dashboards created as public pages.
    - `careernest.php`: wires Admin and Security hooks; calls `Roles::ensure_caps()` on load.
  - Key APIs
    - `add_role`, `remove_role`, `map_meta_cap`, `add_menu_page`, `add_submenu_page`, `admin_init`, `template_redirect`, `show_admin_bar` filter.
  - Verification
    - Roles listed in user creation; administrators and AES Admin see CareerNest menu and submenus.
    - CPTs do not show separate menus; appear under CareerNest.
    - Visiting wp‑admin as applicant/employer team redirects to the correct dashboard; profile still accessible.
    - Visiting the wrong dashboard as a logged‑in user redirects to the correct one; non‑logged‑in users get login prompt.
    - Admin bar hidden on frontend for applicant and employer team users.

- Notes
  - Ownership enforcement will be deepened in a future milestone with meta relations and refined `map_meta_cap` rules.

### Update — 2025‑08‑22: Admin UI Hierarchy + Dashboard Cards

- What
  - Grouped CareerNest submenu with non‑clickable section headers (Jobs, Employers, Applicants, Settings) and added icons to those headers.
  - Replaced bare welcome text with a dashboard of cards for Jobs, Employers, Applicants, and Applications, each showing counts and quick actions.
- Why
  - Improve discoverability and navigation within the CareerNest admin area.
  - Provide at‑a‑glance status and faster access to common actions.
- How
  - Files
    - `includes/Admin/class-admin-menus.php`: added dummy submenu section items; implemented overview cards in `render_welcome()`.
    - `assets/css/admin.css`: styled submenu section headers (with dashicon markers) and card UI (grid, hover, accents).
  - Verification
    - CareerNest submenu shows labeled, iconized section headers; headers are non‑clickable.
    - CareerNest landing page shows four cards with counts and Manage/Add New links; layout responds to viewport width.

---

## Milestone M4 — Meta Boxes & Saving
Status: Pending

- What: Meta boxes for Job, Employer, Applicant; nonce and sanitization; conditional UI via JS.
- Why: Capture structured data securely.
- How: `add_meta_box`, `save_post`, sanitizers, enqueued admin JS.

---

## Milestone M5 — Pages Routing & Template Loader
Status: Pending

- What: `template_include` loader for created pages and `single-job_listing.php`.
- Why: Ensure plugin templates render regardless of theme.
- How: Map stored page IDs to plugin templates; provide filters for overrides.

---

## Milestone M6 — Job Listings & Single View
Status: Pending

- What: Frontend listings with filters/pagination; single job details; apply button logic.
- Why: Core browsing experience for applicants.
- How: `WP_Query` with tax/meta filters; template parts; exclude filled/closed jobs.

---

## Milestone M7 — Registration Flows
Status: Pending

- What: Employer and Applicant registration forms; user creation; CPT linkage.
- Why: Onboard users with appropriate roles and profiles.
- How: Nonced POST handlers on `init`; `wp_create_user`; meta relations.

---

## Milestone M8 — Applications & Notifications
Status: Pending

- What: Internal application form; `job_application` creation; email notifications.
- Why: Enable applying and communication loops.
- How: Form processing; `wp_insert_post`; `wp_mail` with templates/placeholders.

---

## Milestone M9 — Settings
Status: Pending

- What: Settings pages for API keys, email templates, general options.
- Why: Configurability without code changes.
- How: Settings API (`register_setting`, sections, fields); validation/sanitization callbacks.

---

## Milestone M10 — Ownership & Admin Columns
Status: Pending

- What: Query filters to restrict data by owner; custom admin columns.
- Why: Data isolation and admin clarity.
- How: `pre_get_posts` ownership filters; `manage_*_posts_columns` and `manage_*_posts_custom_column`.

---

## Milestone M11 — Hardening, Tests, Docs
Status: Pending

- What: Security passes, PHPCS, unit tests, and documentation updates.
- Why: Stability and maintainability.
- How: WP_UnitTestCase suites; PHPCS; docs alignment.
