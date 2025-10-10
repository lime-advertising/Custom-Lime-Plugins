# mgd-filters Plugin Plan

## Project Goals
- Deliver a WordPress plugin that registers a shortcode for rendering filter controls and post listings for any chosen post type.
- Ensure compliance with WordPress coding standards, security best practices, and plugin boilerplate structure.
- Provide flexible display options: built-in card layout or a user-specified Breakdance global block.

## High-Level Architecture
- **Core Plugin Loader (`mgd-filters.php`)**
  - Define plugin metadata, constants, autoloading, and bootstrap.
  - Register activation/deactivation hooks and load text domain.
- **Includes**
  - `class-loader.php`: Simple autoloader or `spl_autoload_register` callback for namespaced classes.
  - `class-plugin.php`: Main orchestrator for hooks, shortcode registration, assets, and settings.
  - `class-shortcode.php`: Handles shortcode attributes, rendering logic, enqueueing assets, templating.
  - `class-query-service.php`: Builds WP_Query objects based on selected taxonomies and request data; ensures sanitization.
  - `class-assets.php`: Manages registering/enqueueing scripts and styles, localizing defaults.
  - `class-settings.php`: Admin UI for configuring defaults (available post types, layout defaults, Breakdance block selection).
  - `rest/class-rest-controller.php`: Optional REST endpoint for AJAX filtering; namespaced routes.
  - `templates/`: Default card layout with partials (`filters.php`, `results.php`, `card.php`).
- **Assets**
  - `assets/js/filters.js`: Handles dynamic filter requests, updates results with REST/AJAX responses, manages UI state.
  - `assets/css/filters.css`: Baseline styling for filters, cards, responsive layout.

## Shortcode Overview
- Tag: `[mgd_filters]`
- Attributes:
  - `post_type`: targeted CPT; default configurable via settings.
  - `taxonomies`: comma-separated list or `auto` to detect all public taxonomies for the post type.
  - `layout`: `card` (default) or `breakdance`.
  - `breakdance_id`: ID/slug for the Breakdance global block.
  - `filters`: control type per taxonomy (e.g., `category:checkbox,tags:dropdown`).
  - `posts_per_page`, `orderby`, `order`, `show_pagination`.
- Shortcode flow:
  1. Parse + sanitize attributes.
  2. Determine available taxonomies and render filter controls (checkbox/dropdown).
  3. On submission or JS interaction, collect filter selections, request filtered posts (via REST or standard form submission).
  4. Render results using selected layout; fallback template for empty result.

## Filtering Behavior
- Support both non-JS fallback and enhanced AJAX filtering.
- For checkbox filters, allow multi-term selection; dropdowns support single select by default with optional multi-select via JS.
- Respect taxonomy term hierarchy when rendering if applicable.
- Ensure queries use `tax_query` with validated term IDs/slugs; protect against malicious input.

## Admin Settings
- Options page under Settings » MGD Filters.
- Settings fields (stored via `register_setting`):
  - Default post type, default layout, pagination defaults.
  - Enable/disable AJAX mode.
  - Allowed taxonomies per post type.
  - Breakdance integration toggle + selection.
- Use `Settings API` for form rendering; sanitize callbacks for each field.

## Security & Standards
- Escape output with `esc_html`, `esc_attr`, `wp_kses_post`.
- Sanitize inputs via `sanitize_text_field`, `absint`, `array_map`.
- Use nonces for AJAX submissions; REST endpoints require capability checks (`current_user_can`).
- Follow WordPress coding standards (spacing, naming, translations via `__()`).
- Internationalization: load text domain `mgd-filters` from `/languages`.

## Data Flow (AJAX Mode)
1. Frontend enqueues `filters.js` with localized config and nonce.
2. User changes filter → JS sends `POST /wp-json/mgd-filters/v1/query`.
3. REST controller validates nonce/capabilities, sanitizes payload, builds query through `Query_Service`.
4. Response returns rendered templates or structured data; JS updates DOM.

## Breakdance Integration
- Check if Breakdance plugin active (`class_exists('Breakdance\\Init')`).
- Provide selector for global blocks (likely stored as custom post type).
- Rendering via Breakdance API or `do_shortcode` for provided global block ID.
- Graceful fallback to card layout if Breakdance not available.

## Development Roadmap
### Milestone 1 — Admin Foundations
- [ ] Scaffold plugin structure and bootstrap loader.
- [ ] Implement settings manager with defaults (post type, layout).
- [ ] Build tabbed admin UI (General, Documentation) with Settings API hooks.
- [ ] Add sanitization/validation for options and activation defaults.
- [ ] Draft documentation content for shortcode usage.
- [ ] QA: verify settings save/restore, translation hooks, capability checks.

### Milestone 2 — Layout Customization
- [ ] Add “Card Layout” admin tab for configuring default card presentation.
- [ ] Provide per-element visibility toggles (image, title, meta, excerpt, button).
- [ ] Implement drag-and-drop reordering of enabled elements.
- [ ] Add styling controls: width/max-width, height, padding, margin, border, background, color, font options.
- [ ] Persist settings and expose structured defaults to templates/frontend.
- [ ] QA: confirm saved configuration reflects in rendered card template and respects fallbacks.

### Milestone 2.5 — Admin Preview Enhancements
- [ ] Integrate live preview section within admin to display card layout with current settings.
- [ ] Add toggle between grid and slider views using Swiper (detect if Swiper assets already registered).
- [ ] Ensure preview responds to element visibility, ordering, and styling controls in real time.
- [ ] Provide sample content/placeholders for preview without hitting live data.
- [ ] QA: confirm preview assets do not leak to frontend, and Swiper loads only when needed.

### Milestone 3 — Frontend Filters
- [ ] Shortcode scaffolding with attribute sanitization and template loading.
- [ ] Taxonomy discovery + filter rendering (checkbox/dropdown).
- [ ] Query service integration with pagination + non-JS form handling.
- [ ] Default card template & Breakdance block support (including dropdown listing available Breakdance global blocks when selected).
- [ ] REST endpoint + AJAX workflow for dynamic filtering.
- [ ] Frontend assets (CSS/JS) with loading states and error handling.
- [ ] QA: multi-term filtering, pagination, Breakdance fallback, URL preservation.

### Milestone 4 — Polish & Release Prep
- [ ] Internationalization review & language files.
- [ ] README + inline documentation updates.
- [ ] Optional automated tests (PHPUnit/WP-CLI) for query/service layers.
- [ ] Final QA across themes, caching scenarios, and accessibility checks.

## Testing Strategy
- Use WP-CLI + PHPUnit (optional) to cover query service and REST controller.
- Add integration tests for shortcode output under different attribute scenarios.
- Include smoke tests/manual checklist for verifying:
  - No-JS form submit filtering.
  - AJAX filtering and pagination.
  - Breakdance layout rendering.
  - Settings persistence and validation.

## Changelog

### Completed
- Milestone 1: Admin foundations (plugin bootstrap, settings manager, tabbed UI, documentation tab, sanitization defaults).
- Milestone 2: Layout customization tab with sortable elements, per-element visibility toggles, and styling controls; supporting admin assets.

### In Progress / Upcoming
- Milestone 2.5: Admin preview (grid/slider switch, Swiper integration, live settings preview).
- Milestone 3: Frontend shortcode, query service, templates, AJAX/REST workflow, Breakdance integration.
- Milestone 4: Polish, i18n, documentation, optional tests, final QA.
