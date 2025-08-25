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

Date: 2025‑08‑23
Owner: Grok
Status: Completed

- What

  - Implemented meta boxes and save handlers for Job, Employer, Applicant, and Application CPTs with nonces, capability checks, and sanitization.
  - Location UX: Added Google Maps autocomplete + pick-on-map for Applicant, Employer, and Job Location fields, including hidden metadata (place_id/lat/lng), validation, and a "View on map" helper.
  - Conditional admin UI via JS for salary mode toggle, resume pickers, repeaters (education/experience/licences), skills pills, and employer team selection via AJAX.

- Why

  - Capture structured, validated data for listings and profiles; improve editorial UX with dynamic controls.

- How

  - `add_meta_box` for all CPTs; save on `save_post` with nonce checks, autosave guards, and `current_user_can('edit_post')`.
  - Location fields (Applicant/Employer/Job): hidden inputs populated via Maps autocomplete OR pick-on-map modal with reverse geocoding; server-side validation for lat [-90,90] and lng [-180,180]; safe storage of place_id.
  - Admin JS enhances UX (media pickers, toggles, repeaters, map picker); Google Maps Places enqueued only on relevant screens and only when API key present.

- Files

  - `includes/Admin/class-meta-boxes.php`: render + save handlers; added hidden fields and "View/Pick on map" UI for Applicant/Employer/Job locations; comprehensive sanitization and validation.
  - `includes/Admin/class-admin.php`: conditional enqueue for Maps on Applicant/Employer/Job screens; media and sortable as needed; AJAX for employer team.
  - `assets/js/admin.js`: UI behaviors (toggles, repeaters, media pickers, AJAX).
  - `assets/js/maps.js`: generalized to bind autocomplete and pick-on-map (with reverse geocoding) for Applicant/Employer/Job.
  - `assets/css/admin.css`: modal styles for map picker.

- Verification

  - Editing Applicant/Employer/Job shows Location with hidden metadata; with a valid API key, autocomplete populates fields; "Pick on map" allows selecting via map and reverse geocodes address; "View on map" opens the expected location.
  - Invalid/non-numeric lat/lng are discarded on save; place_id sanitized; location text persists.
  - Job/Employer/Application meta fields save and persist; admin-only fields respect capabilities.

- Notes
  - Optional enhancement: live-update the map link client-side on place selection; current implementation updates after save.

---

## Milestone M5 — Pages Routing & Template Loader

Date: 2025‑08‑25
Owner: Cline
Status: Completed

- What

  - Implemented comprehensive template routing system with `template_include` filter for all CareerNest pages and CPTs.
  - Created complete guest job application system with automatic account creation and email notifications.
  - Built full-featured applicant dashboard with application tracking, statistics, profile management, and comprehensive frontend editing.
  - Added dynamic repeater fields for Education, Work Experience, Licenses & Certifications, and Websites & Social Profiles.
  - Implemented in-place editing system with header-based controls and public profile viewing.

- Why

  - Ensure plugin templates render consistently regardless of active theme.
  - Provide seamless user experience for job applications without requiring registration.
  - Create professional applicant dashboard that rivals commercial job platforms.
  - Enable comprehensive profile management with modern UX patterns.

- How

  - Template Routing

    - `includes/class-plugin.php`: Added `template_include` filter with page detection logic and CPT template mapping.
    - Created template hierarchy: page templates → CPT single templates → fallbacks.
    - Implemented conditional asset loading based on page type detection.

  - Guest Application System

    - `templates/template-apply-job.php`: Complete job application form with guest functionality.
    - Automatic user account creation with sanitized data and role assignment.
    - Email notifications with password reset links using `wp_new_user_notification_email` filter.
    - File upload handling for resumes with validation (type, size, security).
    - Application linking system via `user_register` hook to connect guest applications to new accounts.

  - Applicant Dashboard

    - `templates/template-applicant-dashboard.php`: Comprehensive dashboard with application tracking, statistics cards, profile sections, and in-place editing.
    - `assets/css/applicant-dashboard.css`: Professional responsive design with mobile-first approach.
    - `assets/js/applicant-dashboard.js`: Interactive functionality for editing, repeater fields, and form validation.

  - Profile Management System
    - Dynamic repeater fields for unlimited Education, Work Experience, Licenses, and Links entries.
    - Smart form logic (current job checkbox disables end date field).
    - In-place editing that replaces display sections with comprehensive forms.
    - Header-based controls with "View Public Profile", "Edit Profile", and "Logout" buttons.
    - Form processing with array data handling and proper sanitization.

- Files

  - Core: `includes/class-plugin.php`
  - Templates: `templates/template-apply-job.php`, `templates/template-applicant-dashboard.php`, `templates/single-job_listing.php`, `templates/single-employer.php`, `templates/single-applicant.php`
  - Assets: `assets/css/applicant-dashboard.css`, `assets/js/applicant-dashboard.js`

- Verification

  - All CareerNest pages load with correct templates; CPT single pages display properly.
  - Guest users can apply for jobs without registration; automatic account creation works.
  - Email notifications sent with password reset links; file uploads validated and stored.
  - Applications properly linked to user accounts via guest application system.
  - Applicant dashboard displays applications, statistics, and profile sections correctly.
  - In-place editing toggles between display and edit modes seamlessly.
  - Repeater fields allow unlimited entries with proper add/remove functionality.
  - Form validation and data persistence work correctly across all profile sections.
  - Public profile button opens applicant profile in new tab.
  - Responsive design works on mobile devices.

- Notes
  - System provides enterprise-level functionality with professional UX design.
  - All data structures use WordPress best practices with proper sanitization and validation.
  - Template system is extensible for future page types and customizations.
  - Guest application system handles edge cases and provides comprehensive error messaging.

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
