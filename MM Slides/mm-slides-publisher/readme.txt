=== MM Slides Publisher ===
Contributors: limeadvertising
Tags: slider, rss, remote, shortcode, gsap
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Central slide manager + RSS feed for remote consumption (with tabbed global style settings).

== Description ==

MM Slides Publisher lets you manage hero slides in one WordPress site and expose them via a specialized RSS feed for remote consumption by other WordPress sites (using the companion “MM Slides Consumer” plugin).

Key features:
- Custom Post Type: `MM Slides` (`mm_slide`) with Title, Editor (description), Featured Image (background), and custom meta (subtitle, active flag, 2 buttons).
- Taxonomy: `MM Locations` (`mm_location`) to filter the feed per location/site.
- Feed endpoint: `/feed/mm-slides` (optionally `?location=<slug>`). Includes two namespaced JSON CDATA blocks:
  - `mm:vars` – global CSS variables and settings from the admin “Settings” UI.
  - `mm:data` – per‑slide JSON (subtitle, buttons, background URL, active).
- Admin “Settings” UI: Tabbed panel to set global typography, spacing, overlay, button styles, and animation preset, including optional tablet/mobile overrides.
- Remote asset hints: Optionally define a base URL and version for slider JS/CSS so consumer sites can load a shared build from a CDN.

Works best with the companion plugin “MM Slides Consumer”, which fetches this feed and renders a slider via a shortcode.

== Installation ==

1. Upload the `mm-slides-publisher` folder to the `/wp-content/plugins/` directory, or install via the Plugins screen.
2. Activate the plugin through the ‘Plugins’ screen in WordPress.
3. Create slides under “MM Slides”. Use Featured Image as the slide background and the Editor for description text. In the “Slide Details” box fill optional Subtitle, Active flag, and two button labels/URLs.
4. (Optional) Assign a `MM Locations` term to target a specific consumer site; then use `?location=<slug>` in the feed URL.
5. Configure global styles under MM Slides → Settings. Values are exported as CSS custom properties in the feed.
6. Provide the feed URL (e.g. `https://example.com/feed/mm-slides?location=site-a`) to your consumer sites.

== Frequently Asked Questions ==

= Where is the feed? =
At `/feed/mm-slides`. You can filter slides per location with `?location=<slug>`.

= What do consumer sites need? =
Install and configure the “MM Slides Consumer” plugin on the remote site and paste the feed URL into its settings. Then place the `[mm_remote_slider]` shortcode.

= Does the Publisher load front‑end slider scripts? =
No. The Publisher only manages content and exposes the feed. Consumer sites enqueue the slider JS/CSS, optionally loading from a shared CDN if `mm-assets-base` is provided in the feed.

= Can I set different styles for tablet and mobile? =
Yes. Most size/margin/padding fields have optional tablet/mobile overrides. If left blank, values fall back to the desktop setting.

== Screenshots ==
1. MM Slides list view (custom post type)
2. Slide edit screen with “Slide Details” meta box
3. Tabbed global style settings with responsive inputs

== Changelog ==

= 1.4.0 =
Initial public release: CPT, taxonomy, tabbed settings UI, and `mm-slides` feed exporting `mm:vars` and per‑slide `mm:data`.

== Upgrade Notice ==

= 1.4.0 =
First release of the Publisher plugin. Install the Consumer plugin on remote sites to render the slider.

