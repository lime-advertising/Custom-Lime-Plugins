# Publisher Guide — CPT Hub

This guide summarizes how to configure and use the Publisher plugin, including field types, styles, shortcodes, and global CSS.

## Content Types
- Create and edit CPTs in CPT Hub → Content Types.
- Classic Editor is enforced for CPT Hub post types to keep a consistent editing experience across sites.
- Supported custom meta field types:
  - Text, Textarea, Number, URL, Select, Media, WYSIWYG.
  - Media fields add companions in the JSON feed: `{key}_id`, `{key}_url`, `{key}_mime`.
  - WYSIWYG: compact editor with Paragraph/H2/H3/H4, Styles dropdown (brand classes), and a color picker. Saved HTML is sanitized; only `color:` inline style is allowed.

## Styles
- Configure per‑CPT Styles (layout, order/enabled, meta mapping, presets, typography, spacing, animations) in CPT Hub → Styles.
- Map Meta1–Meta3 to your custom field keys, and choose placement (thumb/content).
- WYSIWYG meta mapping: styles payload marks HTML slots via `layout.meta_html`, and Consumers render with safe HTML + paragraphs.
- Image controls: width/min/max width, height, align, radius, padding/margin, hover zoom. Object fit (cover/contain/fill/none/scale‑down) is supported for cropping behavior.
- Copy styles: Use “Copy styles from” to clone all layout + style settings from another CPT.

## Shortcodes (Publisher site)
Render CPT content locally using the same card UI as Consumers.
- List: `[cphub_list cpt="your_cpt" n="10" paged="1" location="optional-slug"]`
- Single: `[cphub_item cpt="your_cpt" id="123"]`
- Styles are generated and enqueued automatically from the saved config.

Meta utility shortcode:
- `[cphub_meta key="meta_slug" id="optional-post-id" size="full|medium|…" width="…" height="…" object_fit="cover|contain|…" link="…"]`
  - Auto-detects the field type from CPT settings.
  - Renders images (respects width/height/object-fit), links for files/URLs, and safe HTML for WYSIWYG.

Location label:
- `[cphub_location]` prints a friendly location name.
  - If used on a CPT item, it auto-detects the first `location` term (excluding `all-locations`).
  - You can force a specific slug: `[cphub_location slug="merrymaidsottawa"]`.
  - Optional `[cphub_location fallback="Your Location"]` if no term is found.

## Global CSS
- CPT Hub → Global CSS: enter sitewide CSS that applies across all pages.
- Delivered to Consumers via `/wp-json/cphub/v1/global` and enqueued there.
- Also enqueued on the Publisher front end.
- Keep rules namespaced where possible (e.g., `.cphub-card .my-class { … }`).

## Elementor Single Templates (Publisher)
- Allows choosing a per‑item Elementor Single template for CPTs created by CPT Hub that have archives enabled.
- Edit any eligible item and use the sidebar meta box “Single Template (Elementor)” to select a template.
- Only appears if Elementor is active and Single templates exist in Templates → Theme Builder.
- Rendering replaces the item’s single view content with the selected template using Elementor’s frontend renderer.
- If Elementor is inactive or the template is missing/unpublished, the normal content is shown.
- This feature does not affect REST/feeds or Consumers.

Publisher‑driven linking for Consumers
- When a template is selected per item, the Publisher also saves public meta used by Consumers:
  - `cphub_el_template_key`: the template slug (Elementor Library post_name)
  - `cphub_el_template_title`: the template title (fallback)
- These meta are included in REST items and copied to Consumer posts on sync. Consumers then auto‑resolve a local Elementor template by slug (or title fallback) and render it on single views.
- Keep template slugs consistent across sites when exporting/importing to ensure reliable matching (avoid duplicate names that create `-2` suffixes).

## Elementor Archive Templates (Publisher)
- In Content Types → Edit, select an “Archive Template (Elementor)” to render on the CPT archive.
- Public URL slug controls both single and archive base. When “Has Archive” is enabled, the archive base follows the Public URL slug.
- The assets endpoint includes an archive template mapping `{ slug, title }`. Consumers use this to auto‑link their local template by slug (then title) at runtime.
- After changing Public URL slug or archive settings, visit Settings → Permalinks and click Save to refresh rewrite rules.

## Public URL Slug
- Each CPT can define a long, public‑facing permalink base (no 20‑character limit). The internal CPT key must still be ≤ 20 characters.
- Singles and archives use the Public URL slug; Consumers receive and apply it for local CPT registration.

## Feeds & REST
- Feeds: `/feed/cphub` and `/feed/cphub/{cpt}` support `n`, `paged`, `modified_since`, `location`, `key`.
- REST: `/wp-json/cphub/v1/items`, `/wp-json/cphub/v1/assets`, `/wp-json/cphub/v1/health`, and `/wp-json/cphub/v1/global`.
- All REST endpoints include ETag/Last‑Modified for conditional GET (304 when unchanged).

## Tips
- Prefer public meta keys (no leading underscore) so they appear in feeds/REST.
- Use the `location` taxonomy to target content; Consumers can request their slug plus `all-locations`.
- Use WYSIWYG for paragraph/heading content in meta slots; keep complex layouts in the main editor if needed.
