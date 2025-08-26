# CareerNest Plugin — Technical Plan (v1.0)

Author: Grok (Acting CTO)
Target: WordPress 6.0+, PHP 8.0+
Scope: Standalone plugin; no third‑party plugins or libraries.

## 1. Goals and Non‑Goals

- Goals
  - Standalone job portal plugin using only WordPress core APIs.
  - Secure, role‑aware flows for employers and applicants.
  - Efficient queries and scalable architecture for future additions.
  - Clean separation between admin (CPTs, meta, settings) and frontend (templates, forms, dashboards).
- Non‑Goals
  - No reliance on ACF or external frameworks.
  - No data deletion on deactivation (optional full uninstall via setting).

## 2. Deliverables and Milestones

- M1: Scaffold plugin, activation/deactivation, options, rewrite flush. ✅ **Completed**
- M2: CPTs & taxonomies with labels, supports, rewrite rules. ✅ **Completed**
- M3: Roles & capabilities, admin access controls, menu structure. ✅ **Completed**
- M4: Meta boxes, save handlers, sanitization & nonces. ✅ **Completed**
- M5: Pages routing, template loader, guest applications, applicant dashboard. ✅ **Completed**
- M6: Frontend job listing and single job display with filters and pagination.
- M7: Registration flows (employer/applicant), user creation, and CPT linkage.
- M8: Job application flow, `job_application` CPT, notifications.
- M9: Settings pages (API keys, email templates, general options) via Settings API.
- M10: Ownership restrictions, query filters, and admin columns.
- M11: Polishing, security hardening, tests, and documentation.

Each milestone has acceptance criteria in Section 12.

## 3. High‑Level Architecture

- Core
  - Main plugin bootstrap `careernest.php` hooks classes during `plugins_loaded`/`init`.
  - Namespaced/OOP classes grouped by domain: Admin, Frontend, Data (CPT/Tax), Security, Settings.
  - Activation/Deactivation handlers register roles, set options, create pages, and flush rewrites.
- Data Model
  - CPTs: `job_listing`, `employer`, `applicant`, `job_application`.
  - Taxonomies: `job_category` (hierarchical), `job_type` (non‑hierarchical).
  - Meta keys defined in Section 5.
- Frontend
  - Template loader via `template_include` and page slugs.
  - Logged‑in dashboards for employers and applicants; role checks via `current_user_can()`.
  - Form handling via POST endpoints on `init` with nonces and redirects.
- Admin
  - Menu pages grouped under top‑level “CareerNest”.
  - CPT list customizations (columns, filtering), meta boxes, and settings pages.

## 4. Activation, Deactivation, Uninstall

- Activation (`register_activation_hook()`)
  - Register CPTs/Taxonomies, then flush rewrite rules.
  - Add roles/capabilities (Section 7) and set default options.
  - Create required pages as `private`, set `_careernest_hidden` = `1` and `_wp_page_template` meta.
  - Store page IDs under `careernest_pages` option.
- Deactivation (`register_deactivation_hook()`)
  - Remove custom roles but keep data (posts, meta, options).
- Uninstall (optional, gated by setting)
  - If `careernest_options[delete_on_uninstall]` is true, delete CPT posts, tax terms, meta, and options.

## 5. CPTs, Taxonomies, Meta

- CPT: `job_listing`
  - public: true; supports: title, editor, thumbnail; rewrite: `jobs`.
  - Meta
    - `_employer_id` (int, post ID of employer)
    - `_job_location` (text)
    - `_remote_position` (bool)
    - `_opening_date` (Y-m-d)
    - `_closing_date` (Y-m-d)
    - `_salary_range` (text)
    - `_salary` (int|float)
    - `_apply_externally` (bool)
    - `_external_apply` (url|email)
    - `_posted_by` (int, user ID)
    - `_position_filled` (bool)
- CPT: `employer`
  - public: true; supports: title, thumbnail.
  - Meta
    - `_website` (url)
- CPT: `applicant`
  - public: true; supports: title, editor. **Updated: Made public for profile viewing**
  - Meta
    - `_user_id` (int, linked WP user)
    - `_professional_title` (text)
    - `_phone` (text)
    - `_location` (text)
    - `_place_id` (text, Google Maps)
    - `_latitude` (float)
    - `_longitude` (float)
    - `_right_to_work` (enum: foreign|australian)
    - `_work_types` (array: full_time, part_time, contract, etc.)
    - `_available_for_work` (bool)
    - `_skills` (array)
    - `_education` (array of objects: institution, certification, end_date, complete)
    - `_experience` (array of objects: company, title, start_date, end_date, current, description)
    - `_licenses` (array of objects: name, issuer, issue_date, expiry_date, credential_id)
    - `_links` (array of objects: label, url)
    - `_linkedin_url` (url)
    - `_resume_attachment_id` (int) [future]
- CPT: `job_application`

  - public: false; supports: title, editor.
  - Meta
    - `_job_id` (int, job_listing ID)
    - `_applicant_id` (int, applicant post ID)
    - `_status` (enum: pending|reviewed|accepted|rejected)

- Taxonomies for `job_listing`
  - `job_category` (hierarchical like categories)
  - `job_type` (non‑hierarchical like tags)

## 6. Required Pages (created on activation)

- jobs (Job Listings) → template `templates/template-jobs.php`
- employer-dashboard (private) → template `templates/template-employer-dashboard.php`
- applicant-dashboard (private) → template `templates/template-applicant-dashboard.php`
- register-employer → template `templates/template-register-employer.php`
- register-applicant → template `templates/template-register-applicant.php`
- apply-job → template `templates/template-apply-job.php`

- Page storage
  - Option: `careernest_pages` = array of page IDs keyed by slug.
  - Hidden pages
    - `post_status` = `private`
    - Meta `_careernest_hidden` = `1`
  - Hide in admin list
    - `pre_get_posts` to exclude `_careernest_hidden` for non‑administrators.

## 7. Roles and Capabilities

- AES Admin (`aes_admin`)
  - Custom caps: `manage_careernest`, `edit_jobs`, `edit_employers`, `edit_applicants`, `edit_job_applications`, `manage_settings`.
  - Inherit standard editor‑level caps as needed; exclude site‑wide admin caps like `manage_plugins`.
- Employer Team (`employer_team`)
  - Caps: `read`, `edit_own_jobs`, `view_applications` (custom mapped).
  - Ownership filtering via `pre_get_posts` using user meta `_employer_id`.
- Applicant (`applicant`)

  - Caps: `read`, `edit_own_profile`, `apply_to_jobs`.
  - Redirect from `/wp-admin` using `admin_init` if attempting to access admin.

- Capability mapping
  - Map meta caps in `map_meta_cap` for custom caps to enforce per‑post ownership.

## 8. Admin UI & Menus

- Top‑level: CareerNest (`manage_careernest`)

  - Job Listings
    - All Jobs, Add New, Job Categories, Job Types, Job Applications, Settings
  - Employers
    - All Employers, Add New, Settings
  - Applicants
    - All Applicants, Settings
  - General Settings

- Custom columns
  - Jobs: Employer, Type, Category, Opening/Closing dates, Status (filled)
  - Applications: Job, Applicant, Status, Submitted date

## 9. Meta Boxes & Saving

- Job Details
  - Employer dropdown (from `employer` CPT)
  - Location (Google Maps autocomplete)
  - Remote checkbox
  - Opening/Closing dates
  - Salary range and salary (admin‑only toggle)
  - Apply externally checkbox + External field (URL/email)
  - Posted by (admin‑only user selector with AJAX)
  - Position filled checkbox
- Employer Details
  - Company website (URL)
- Applicant Details (initial)

  - Resume upload (attachment) [future]

- Save strategy
  - `add_meta_box()` on `add_meta_boxes`.
  - Verify nonces, `current_user_can()` checks.
  - Sanitize using `sanitize_text_field`, `sanitize_email`, `esc_url_raw`, `intval`, `absint`, `wp_kses_post` as appropriate.

## 10. Frontend Templates & Flows

- Template loader ✅ **Implemented**

  - Hook `template_include` to load plugin templates based on page IDs from `careernest_pages` and CPT templates:
    - `templates/template-jobs.php`
    - `templates/single-job_listing.php`
    - `templates/single-employer.php`
    - `templates/single-applicant.php`
    - dashboards and forms templates under `templates/`.
  - Conditional asset loading based on page type detection.

- Job Listings

  - `WP_Query` with taxonomy filters (`job_category`, `job_type`), search, location string match, pagination.
  - Exclude filled jobs if `_position_filled` or `closing_date` passed.

- Single Job

  - Show meta fields; render Apply button
  - If `_apply_externally`, present external URL/email; else internal application form.

- Guest Application System ✅ **Implemented**

  - Complete job application form with guest functionality in `templates/template-apply-job.php`.
  - Automatic user account creation with sanitized data and role assignment.
  - Email notifications with password reset links using `wp_new_user_notification_email` filter.
  - File upload handling for resumes with validation (type, size, security).
  - Application linking system via `user_register` hook to connect guest applications to new accounts.

- Applicant Dashboard ✅ **Implemented**

  - Comprehensive dashboard with application tracking, statistics cards, and profile management.
  - In-place editing system with header-based controls ("View Public Profile", "Edit Profile", "Logout").
  - Dynamic repeater fields for Education, Work Experience, Licenses & Certifications, and Websites & Social Profiles.
  - Smart form logic (current job checkbox disables end date field).
  - Professional responsive design with mobile-first approach.
  - Form processing with array data handling and proper sanitization.

- Dashboards

  - Employer: ✅ **Completed** - job listings with statistics, application tracking, personal profile management, company information display.
  - Applicant: ✅ **Completed** - profile editor with comprehensive sections, application tracking, job recommendations.

- Registration

  - Employer/App forms create WP user with role; create linked CPT post and store relation (`_user_id` or employer mapping).

- Applications ✅ **Partially Implemented**
  - Guest application system creates `job_application` with links to job and user.
  - Email notifications via `wp_mail()` with password reset functionality.
  - Full internal application system pending M8.

## 11. Settings & Emails

- Settings (Settings API)

  - General: page assignment (read‑only overview), misc toggles, uninstall behavior.
  - API Keys: Google Maps API key (`careernest_options[maps_api_key]`).
  - Emails: editable templates for events: new application, application status change, new employer registration.

- Email templates
  - Placeholders: {site_name}, {job_title}, {applicant_name}, {employer_name}, {application_link}
  - Filters: `careernest_email_headers`, `careernest_email_subject`, `careernest_email_body`.

## 12. Security Model

- Nonces for all forms (`wp_nonce_field`/`check_admin_referer`).
- Capability checks with `current_user_can` for every action/data access.
- Validation and sanitization for all inputs and meta saves.
- Redirect low‑privilege users from `/wp-admin` (except profile), enforce ownership on queries.
- Avoid exposing private IDs in GET without nonces; use POST where possible.

## 13. Performance & Scalability

- Query performance
  - Use indexed meta queries where needed; prefer taxonomy queries when feasible.
  - Paginate results; lazy‑load heavy sections on dashboards.
- Asset loading
  - Enqueue Google Maps only on pages that need it; key from settings.
  - Scope admin scripts/styles to relevant screens via `admin_enqueue_scripts`.
- Caching
  - Leverage WP object cache; cache repeated dropdown data (employers) per request.

## 14. Extensibility (Hooks)

- Actions
  - `careernest_before_job_form`, `careernest_after_job_form`
  - `careernest_application_created` (passes IDs)
  - `careernest_employer_registered`, `careernest_applicant_registered`
- Filters
  - `careernest_jobs_query_args`
  - `careernest_email_subject`, `careernest_email_body`, `careernest_email_headers`
  - `careernest_template_paths`

## 15. Testing Strategy

- Unit tests with `WP_UnitTestCase`
  - CPT/Tax registration, roles/caps mapping
  - Meta saves with nonce and capability checks
  - Query filters for ownership
  - Page creation on activation; option storage
- Manual QA checklists per milestone (Section 16)

## 16. Acceptance Criteria per Milestone

- M1
  - Plugin activates without errors; options seeded; rewrites flushed.
  - Pages created with correct slugs, private status, `_careernest_hidden` set, IDs stored.
- M2
  - CPTs and taxonomies visible with expected supports, labels, and URLs.
- M3
  - Roles appear; capabilities enforced; admin menu visible to `aes_admin`.
- M4
  - Meta boxes render; values persist; sanitization verified; conditional fields toggle via JS.
- M5
  - Template loader picks plugin templates for created pages and single job CPT.
- M6
  - Job listings filter by category/type/search; pagination works; filled/closed jobs excluded.
- M7
  - Registration creates user + linked CPT; proper roles applied; login redirect to dashboards.
- M8
  - Submitting application creates `job_application`, links applicant+job, sends emails.
- M9
  - Settings save and validate; Maps key used to load Google API only on needed pages.
- M10
  - Employers see only their data; applicants limited to own profile/applications.
- M11
  - phpcs clean; docs updated; smoke tests pass.

## 17. Folder Structure (current implementation)

```
careernest/
  careernest.php ✅
  readme.txt
  /includes/
    class-plugin.php ✅ (enhanced with template routing & asset loading)
    class-activator.php ✅
    class-deactivator.php ✅
    /Admin/
      class-admin.php ✅
      class-admin-menus.php ✅
      class-admin-columns.php
      class-meta-boxes.php ✅
      class-settings.php ✅
      class-users.php ✅
    /Data/
      class-cpt.php ✅ (enhanced with applicant public visibility)
      class-taxonomies.php ✅
      class-roles.php ✅
    /Security/
      class-caps.php ✅
  /assets/
    /css/
      admin.css ✅
      applicant-dashboard.css ✅ (comprehensive responsive design)
    /js/
      admin.js ✅
      applicant-dashboard.js ✅ (interactive editing & repeater fields)
      maps.js ✅
  /templates/
    template-jobs.php ✅
    single-job_listing.php ✅
    single-employer.php ✅
    single-applicant.php ✅
    template-employer-dashboard.php ✅
    template-applicant-dashboard.php ✅ (comprehensive dashboard with in-place editing)
    template-register-employer.php ✅
    template-register-applicant.php ✅
    template-apply-job.php ✅ (guest application system)
  /docs/
    TECHNICAL_PLAN.md ✅
    PROGRESS.md ✅
    MILESTONES.md ✅
    QA_CHECKLIST.md ✅
```

**Key Implementation Notes:**

- Template routing system implemented in `includes/class-plugin.php` with `template_include` filter
- Comprehensive applicant dashboard with professional UI/UX design
- Guest application system with automatic account creation and email notifications
- Dynamic repeater fields for unlimited profile entries
- In-place editing system with header-based controls
- Responsive design with mobile-first approach
- All data structures use WordPress best practices with proper sanitization

## 18. Coding Standards & Conventions

- WordPress Coding Standards (PHPCS), PSR‑4‑like autoloading (simple require if no composer).
- Nonces everywhere; sanitize/escape consistently; prefix meta keys with `_careernest_` is optional but reserved.
- Function/class prefixes: `Careernest_` or `CareerNest\...` namespace.

## 19. Risks & Mitigations

- Role/cap complexity → Centralize in `Security\class-caps.php` and add tests.
- Template conflicts → Use explicit `template_include` mapping from stored page IDs.
- Google Maps API usage → Load conditionally; fail gracefully if no key.
- Data growth → Prefer taxonomy classification for common filters; add indexes if needed.

## 20. Next Steps

- Approve plan structure and milestones.
- Begin M1 implementation (bootstrap, activation logic, page creator, options seed).
