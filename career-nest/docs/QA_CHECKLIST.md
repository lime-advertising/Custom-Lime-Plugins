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
    - Job Location (text) + Remote checkbox.
    - Opening/Closing date fields.
    - Salary Range (text).
    - Apply Externally checkbox toggles External Apply field (URL/email) via JS.
    - Admin-only: Salary (number), Posted By (users dropdown), Position Filled (checkbox).
  - Save behavior
    - Values persist after Save/Update.
    - External Apply accepts a valid URL or email; invalid input is discarded.
    - Admin-only fields only save for users with manage_options.
- Employer edit screen
  - Employer Details metabox with Company Website (URL) saves and persists; invalid URLs are cleared.
- Applicant edit screen
  - Applicant Details metabox shows Linked WP User ID (read-only).
- Security
  - Saving without nonce fails silently (no data change).
  - Autosave does not overwrite meta.
  - Users without `edit_post` cannot save changes.

## Regression & Smoke Checks
- General admin pages load without PHP notices/warnings.
- CareerNest pages (front-end templates) still render basic placeholders.
- Deactivate plugin: no data deleted; custom roles removed; re-activate restores roles.

## Known Follow-ups (Future Milestones)
- M5: Template loader for created pages and `single-job_listing.php` routing.
- M6: Job listing filters/pagination and single job rendering.
- Ownership enforcement: map_meta_cap refinements and query filtering.
- Google Maps autocomplete for Job Location (conditional enqueue with API key).
- Admin columns and Settings UI.

## Tips
- Use a browser profile with devtools open to watch for unexpected assets on edit screens.
- Test role scenarios in separate browsers/incognito to avoid session/cap cache confusion.
- For options/meta inspection, WP-CLI can help: `wp option get careernest_pages`.

