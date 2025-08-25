# CareerNest — QA Verification Checklist

Audience: QA / developer doing manual verification. Covers M1–M4.

## Prerequisites
- WordPress 6.0+; PHP 8.0+.
- CareerNest plugin installed and activated.
- Test users created: Administrator, AES Admin, Employer Team, Applicant.
- Permalinks set (Settings → Permalinks) to a non-default structure for archive testing.

## M1 — Bootstrap & Activation
- Activate plugin without fatal errors or notices.
- Pages created (Pages → All Pages): jobs, employer-dashboard, applicant-dashboard, register-employer, register-applicant, apply-job.
  - Each page has the correct slug and assigned template meta (not visible in UI, but confirm via Quick Edit or DB if needed).
- Managed pages hidden from non-admins (Pages list) if `_careernest_hidden` is set on them.
- `careernest_pages` option exists (Tools → Site Health → Info → WordPress constants, or via `wp option get careernest_pages`).
- Visiting `/jobs/` should not 404 after activation (rewrite flush done).

## M2 — CPTs & Taxonomies
- Admin sidebar shows CPTs under CareerNest menu only (no separate top-level menus for CPTs).
- CPTs present: Jobs, Employers, Applicants, Applications.
  - Jobs archive accessible at `/jobs/`.
- Taxonomies on Jobs: Job Categories (hierarchical), Job Types (non-hierarchical).
  - Metaboxes visible on Job edit screen.
- Block editor disabled on all four CPT edit screens; classic editor loads (or no content box for Jobs if removed in M4).
- Block editor assets not enqueued on these edit screens (developer tools: no `wp-block-editor` or `wp-edit-post` requests).

## M3 — Roles, Menus, Redirects, Admin Bar
- Roles exist in Users → Add New: AES Admin, Employer Team Member, Applicant.
- AES Admin permissions
  - Can manage posts/pages, media, comments, categories/tags.
  - Cannot manage plugins or themes (Appearance/Plugins screens hidden/denied).
  - Can access CareerNest menu and all submenus.
- CareerNest admin menu
  - Has section headers (Jobs, Employers, Applicants, Settings) styled as non-clickable with icons.
  - Submenus: All Jobs, Add New Job, Job Categories, Job Types, Applications, All Employers, All Applicants, Settings.
- Admin landing page (CareerNest) shows 4 cards (Jobs, Employers, Applicants, Applications) with counts and Manage/Add New.
- Redirects
  - Applicant visiting `/wp-admin` redirects to Applicant Dashboard; employer team redirects to Employer Dashboard.
  - Visiting wrong dashboard redirects to correct one based on role.
  - Non-logged-in users visiting a dashboard are redirected to login (with redirect back).
- Admin bar
  - Hidden on frontend for Applicant and Employer Team users.

## M4 — Meta Boxes & Saving
- Job edit screen
  - No block editor; content editor removed (title + featured image + meta boxes only).
  - Job Details metabox shows:
    - Employer dropdown (lists Employers CPT posts).
    - Job Location with Google Maps autocomplete + “Pick on map” button + “View on map” link; Remote checkbox.
    - Opening/Closing date fields.
    - Salary Range (text).
    - Apply Externally checkbox toggles External Apply field (URL/email) via JS.
    - Admin-only: Salary (number), Posted By (users dropdown), Position Filled (checkbox).
  - Save behavior
    - Values persist after Save/Update.
    - External Apply accepts a valid URL or email; invalid input is discarded.
    - Location metadata validation: `_job_location_lat` within [-90, 90], `_job_location_lng` within [-180, 180]; invalid values are not saved; `_job_location_place_id` sanitized.
    - “View on map” opens the correct location (lat/lng preferred, else address).
    - Admin-only fields only save for users with manage_options.
- Employer edit screen
  - Employer Details metabox:
    - Company Website (URL) saves and persists; invalid URLs are cleared.
    - Location with Google Maps autocomplete + “Pick on map” button + “View on map” link.
  - Save behavior
    - Location text persists; `_location_lat` and `_location_lng` validated to ranges; `_location_place_id` sanitized.
    - “View on map” opens the correct location after save.
- Applicant edit screen
  - Applicant Details metabox shows Linked WP User ID (read-only for non-admins; admins can link Applicants to WP users).
  - Location with Google Maps autocomplete + “Pick on map” button + “View on map” link.
  - Save behavior: `_location_lat`/`_location_lng` validated to ranges; `_location_place_id` sanitized; text persists.
- Security
  - Saving without nonce fails silently (no data change).
  - Autosave does not overwrite meta.
- Users without `edit_post` cannot save changes.

### M4 — Google Maps Integration QA
- Prerequisite: Set Google Maps API key in CareerNest Settings (General → Google Maps API Key).
- Enqueue checks on edit screens:
  - On Applicant/Employer/Job edit, verify Google Maps JS (`maps.googleapis.com/maps/api/js?libraries=places`) and `assets/js/maps.js` load only when an API key is configured.
- Autocomplete behavior:
  - Selecting a suggested place updates the Location text and populates hidden fields (`*_place_id`, `*_lat`, `*_lng`).
  - Manually editing the Location text clears the hidden fields.
- Pick on map modal:
  - Clicking “Pick on map” opens a modal with a map; clicking places and dragging moves the marker.
  - “Use this location” reverse geocodes the marker and fills the Location text + hidden fields; modal closes.
  - “Cancel” or close button dismisses without changes.
- Save & View:
  - Saving persists validated lat/lng and place_id; invalid/non-numeric values are discarded.
  - “View on map” opens the correct location (lat/lng if present, otherwise address).

## Regression & Smoke Checks
- General admin pages load without PHP notices/warnings.
- CareerNest pages (front-end templates) still render basic placeholders.
- Deactivate plugin: no data deleted; custom roles removed; re-activate restores roles.

## Known Follow-ups (Future Milestones)
- M5: Template loader for created pages and `single-job_listing.php` routing.
- M6: Job listing filters/pagination and single job rendering.
- Ownership enforcement: map_meta_cap refinements and query filtering.
  
- Admin columns and Settings UI.

## Tips
- Use a browser profile with devtools open to watch for unexpected assets on edit screens.
- Test role scenarios in separate browsers/incognito to avoid session/cap cache confusion.
- For options/meta inspection, WP-CLI can help: `wp option get careernest_pages`.

