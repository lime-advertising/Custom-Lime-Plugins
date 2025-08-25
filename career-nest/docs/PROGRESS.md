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
- [x] M4: Meta boxes; save handlers; sanitization & nonces
- [x] M5: Pages routing, template loader, guest applications, applicant dashboard
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

### 2025-08-23 — Milestone M4: Meta Boxes & Saving

- Summary: Completed meta boxes and save handlers across CPTs with nonce/cap checks/sanitization; enhanced Applicant Location with Google Maps metadata (place_id/lat/lng) plus validated save and a "View on map" link.
- Files: `includes/Admin/class-meta-boxes.php`, `includes/Admin/class-admin.php`, `assets/js/admin.js`, `assets/js/maps.js`
- Verification: On Applicant edit, selecting a place (with API key configured) populates hidden fields; after Save, the map link opens the correct Google Maps location; invalid lat/lng are discarded; general meta fields for Job/Employer/Application save and persist as expected; admin-only fields enforced.
- Notes: Option to update the map link live in JS remains open; current behavior updates on save.

### 2025-08-23 — M4 Update: Employer & Job Map Picker + Autocomplete

- Summary: Extended Google Maps integration to Employer and Job Location fields. Added pick-on-map modals (with reverse geocoding), hidden metadata fields (place_id/lat/lng), and "View on map" links across Applicant/Employer/Job. Enqueued Maps on their edit screens only when API key is present.
- Files: `includes/Admin/class-meta-boxes.php`, `includes/Admin/class-admin.php`, `assets/js/maps.js`, `assets/css/admin.css`
- Verification: For Employer and Job edits, autocomplete populates hidden fields; pick-on-map sets address and coordinates; save validates ranges and persists meta; "View on map" opens correct location.
- Notes: Modal styling is lightweight; can refine accessibility and live-update of the View link if desired.

### 2025-08-25 — Milestone M5: Pages Routing & Template Loader

- Summary: Implemented comprehensive template routing system with `template_include` filter for all CareerNest pages and CPTs; created template loader that handles page detection and asset loading; ensured proper template hierarchy and fallbacks.
- Files: `includes/class-plugin.php`, `templates/template-jobs.php`, `templates/template-employer-dashboard.php`, `templates/template-applicant-dashboard.php`, `templates/template-register-employer.php`, `templates/template-register-applicant.php`, `templates/template-apply-job.php`, `templates/single-job_listing.php`, `templates/single-employer.php`, `templates/single-applicant.php`
- Verification: All CareerNest pages load with correct templates; CPT single pages display properly; template hierarchy respected; no 404 errors on CareerNest URLs.
- Notes: Template system is extensible and can easily accommodate additional page types or custom templates.

### 2025-08-25 — M5 Update: Guest Job Application System

- Summary: Implemented complete guest application system allowing non-registered users to apply for jobs with automatic account creation; added email notifications with password reset links; integrated file upload handling for resumes with validation; created application linking system that connects guest applications to newly created user accounts.
- Files: `templates/template-apply-job.php`, `includes/class-plugin.php`
- Verification: Guest users can apply for jobs without registration; automatic user account creation with email notifications; file uploads work with proper validation; applications properly linked to user accounts; password reset emails sent successfully.
- Notes: System handles edge cases like existing email addresses and provides proper error messaging; file upload security includes type and size validation.

### 2025-08-25 — M5 Update: Comprehensive Applicant Dashboard

- Summary: Created full-featured applicant dashboard with application tracking, statistics, profile management, and job recommendations; implemented frontend profile editing with toggle functionality; added external asset management with conditional loading for dashboard-specific CSS/JS.
- Files: `templates/template-applicant-dashboard.php`, `assets/css/applicant-dashboard.css`, `assets/js/applicant-dashboard.js`, `includes/class-plugin.php`
- Verification: Dashboard displays user applications with status tracking; profile editing works with form validation; statistics cards show accurate counts; job recommendations appear based on user profile; responsive design works on mobile devices.
- Notes: Dashboard provides comprehensive overview of applicant's job search activity with professional UI/UX design.

### 2025-08-25 — M5 Update: Enhanced Profile Sections & Frontend Editing

- Summary: Added comprehensive profile sections (Personal Summary, Work Experience, Education, Licenses & Certifications, Websites & Social Profiles) with full display and editing capabilities; implemented dynamic repeater fields with add/remove functionality; created in-place editing system with header-based controls; added public profile viewing capability.
- Files: `templates/template-applicant-dashboard.php`, `assets/css/applicant-dashboard.css`, `assets/js/applicant-dashboard.js`
- Verification: All profile sections display correctly with proper data formatting; edit mode replaces display sections with comprehensive forms; repeater fields allow unlimited entries with proper indexing; current job checkbox disables end date field; public profile button opens in new tab; form validation and data persistence work correctly.
- Notes: System provides professional-grade profile management with intuitive UX; all data structures are properly sanitized and stored as post meta arrays; responsive design ensures mobile compatibility.

#### Detailed Feature Implementation:

**Profile Display Sections:**

- Personal Summary: Rich text display from post content with proper formatting
- Work Experience: Shows up to 5 positions with company, title, date range, and descriptions
- Education: Displays up to 5 qualifications with institution, degree, completion date, and status
- Licenses & Certifications: Shows up to 5 certifications with issuer, expiry dates, and credential IDs
- Skills: Tag-based display showing up to 15 skills with overflow indicator
- Websites & Social Profiles: LinkedIn integration plus custom links with labels

**Frontend Editing System:**

- Header-based edit controls with "View Public Profile", "Edit Profile", and "Logout" buttons
- In-place editing that replaces display sections with comprehensive forms
- Dynamic repeater fields for Education, Work Experience, Licenses, and Links
- Smart form logic (current job checkbox disables end date field)
- Form validation with required field checking and data sanitization
- Success/error messaging with auto-hide functionality

**Technical Architecture:**

- Template routing via `template_include` filter with proper page detection
- External asset management with conditional loading based on page type
- Comprehensive form processing with array data handling
- JavaScript-based UI interactions with proper event management
- Responsive CSS design with mobile-first approach
- Data persistence using WordPress post meta with structured arrays

**User Experience Enhancements:**

- Professional dashboard layout with statistics cards and application tracking
- Seamless transition between view and edit modes
- Intuitive repeater field management with add/remove buttons
- Visual feedback for form states and validation
- Mobile-optimized interface with responsive design
- Public profile access for employer viewing

## Template for Future Entries

### YYYY‑MM‑DD — Milestone X: Short Title

- Summary: One or two sentences on what was completed.
- Files: `path/one.php`, `path/two.php`, ...
- Verification: List the key manual or automated checks run.
- Notes: Known limitations, follow‑ups, or decisions.
