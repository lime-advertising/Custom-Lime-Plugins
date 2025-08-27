# Publisher — Per‑Item Elementor Single Templates (Plan)

## Overview
Let authors choose an Elementor Single template per item (for CPTs with archive enabled) and render that template on the item’s single page on the Publisher site. No API changes; Consumers are unaffected and do not receive or use templates.

## Goals
- Per‑item dropdown to select an Elementor Single template.
- Render selected template on that item’s single view on the Publisher.
- Fail gracefully if Elementor is inactive or the template is missing.
- Keep Consumers decoupled (no template delivery via REST).

## Scope
- Publisher only: UI + rendering for CPT items when `has_archive = true` in the CPT definition.
- Consumer unchanged; templates are not shipped over the wire.
- Authors will manually import/maintain Elementor templates on relevant sites if needed in future.

## Requirements
- Elementor (Pro) active on the Publisher (for Single templates and frontend rendering API).
- Templates created in Templates → Theme Builder (Elementor) as Single templates.

## Data Model
- Post meta: `_cphub_el_template` (int) — selected Elementor template ID for the post.
- Optional future: per‑CPT default `_cphub_el_template_default` (option) — fallback template when not overridden on an item.

## Template Discovery
- Query `elementor_library` posts that represent “Single” templates.
- Heuristics to filter Singles (support both styles Elementor uses):
  - Taxonomy: `elementor_library_type` terms containing `single` (preferred), or
  - Post meta `_elementor_template_type` = `single` (fallback), or
  - Shortcode content includes `[elementor-template]` (last resort, not ideal).
- Only published templates; order by title ASC.
- Label entries as “Title (ID:123)” to reduce ambiguity.

## Authoring UI
- Add a meta box in the sidebar of the post edit screen (for eligible CPTs):
  - Title: “Single Template (Elementor)”
  - Control: Select dropdown
    - “— Use default —” (no override)
    - List of discovered Single templates (Title + ID)
  - Helper text: “Applies to this item’s single page. Requires Elementor Single templates to be available on this site.”
- Gate the meta box by:
  - CPT owned by CPT Hub AND `has_archive = true` in its definition.
  - Elementor active; hide the UI if not.

## Saving
- On `save_post` for eligible CPTs:
  - Verify nonce + capability.
  - Accept an integer template ID; if invalid or empty, delete meta to revert to default.

## Rendering
- Scope: Single views for eligible CPTs on the Publisher (main query, `is_singular()` of that CPT).
- Hook: Prefer `the_content` filter to replace post content when a template is selected. Alternative: `template_include` for full override.
- Logic:
  1. Read `_cphub_el_template`.
  2. If empty, optionally read per‑CPT default (future enhancement).
  3. If a valid template ID exists and Elementor frontend is available, render via:
     - `\Elementor\Plugin::instance()->frontend->get_builder_content_for_display( $template_id );`
  4. Return rendered HTML (optionally wrapped in a div with a scoped class).
  5. Fallbacks: If Elementor inactive or template missing/unpublished, return original post content.
- Dependencies: Elementor handles enqueueing its own CSS/JS for template parts.

## Eligibility Rules
- Only apply UI + rendering for CPTs created in CPT Hub with `has_archive = true`.
- If this gating becomes too strict, we can allow an admin setting to always enable per‑item templates.

## Edge Cases & Fallbacks
- Elementor plugin inactive: hide selector; render post content.
- Template deleted/unpublished: detect invalid ID; render post content.
- Performance: Rely on page cache. Rendering uses Elementor’s frontend renderer; avoid extra DB calls beyond the template query.

## Acceptance Criteria
- CPT with archive enabled shows a “Single Template (Elementor)” dropdown on item edit screen.
- Selecting a template and updating causes the item’s single page to render that template.
- Clearing the selection reverts to the normal content.
- No impact on REST payloads or Consumers.

## Implementation Outline
1. Discovery: Utility to return a list of Elementor Single templates (ID → Title).
2. UI: Sidebar meta box (eligible CPTs, Elementor active), nonce, select options.
3. Save: Persist `_cphub_el_template` meta (int), delete when empty.
4. Render: Filter `the_content` on eligible single views; render via Elementor if selected; else default content.
5. Docs: Update `docs/publisher-guide.md` with a short “Per‑Item Elementor Template” section.
6. Tests: Manual matrix with/without Elementor active; template present/missing; multiple CPTs.

## Future Enhancements (Not in this phase)
- Per‑CPT default template, used when item has no override.
- Archive template selection per CPT (Elementor “Archive” type) for archive pages.
- Centralize templates on Publisher only and explore propagation strategy.
- Admin setting to restrict available templates list to a tag or category.

