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

## Shortcodes (Publisher site)
Render CPT content locally using the same card UI as Consumers.
- List: `[cphub_list cpt="your_cpt" n="10" paged="1" location="optional-slug"]`
- Single: `[cphub_item cpt="your_cpt" id="123"]`
- Styles are generated and enqueued automatically from the saved config.

## Global CSS
- CPT Hub → Global CSS: enter sitewide CSS that applies across all pages.
- Delivered to Consumers via `/wp-json/cphub/v1/global` and enqueued there.
- Also enqueued on the Publisher front end.
- Keep rules namespaced where possible (e.g., `.cphub-card .my-class { … }`).

## Feeds & REST
- Feeds: `/feed/cphub` and `/feed/cphub/{cpt}` support `n`, `paged`, `modified_since`, `location`, `key`.
- REST: `/wp-json/cphub/v1/items`, `/wp-json/cphub/v1/assets`, `/wp-json/cphub/v1/health`, and `/wp-json/cphub/v1/global`.
- All REST endpoints include ETag/Last‑Modified for conditional GET (304 when unchanged).

## Tips
- Prefer public meta keys (no leading underscore) so they appear in feeds/REST.
- Use the `location` taxonomy to target content; Consumers can request their slug plus `all-locations`.
- Use WYSIWYG for paragraph/heading content in meta slots; keep complex layouts in the main editor if needed.
