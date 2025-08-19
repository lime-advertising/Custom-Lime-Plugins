=== MM Slides Consumer ===
Contributors: limeadvertising
Tags: slides, slider, rss, shortcode, remote content
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Pulls remote slides via RSS and renders Simple Slider–compatible markup via a shortcode.

== Description ==

MM Slides Consumer fetches slide items from a remote WordPress site (or any RSS feed with compatible fields) and caches them locally. It then renders Simple Slider–compatible markup using a shortcode, so you can display the remote slider on any page.

Features:
- Specify a remote RSS feed URL.
- Hourly background fetch with a manual “Fetch Now” option.
- Simple shortcode output: `[mm_remote_slider]`.

== Installation ==

1. Upload the plugin folder to your `/wp-content/plugins/` directory or install via the WordPress admin.
2. Activate the plugin through the ‘Plugins’ screen in WordPress.
3. Go to `Settings → MM Remote Slides` and enter the remote Feed URL.
4. Place the `[mm_remote_slider]` shortcode in the desired page or template.

== Frequently Asked Questions ==

= How do I force an immediate refresh? =
Use the “Fetch Now” button in `Settings → MM Remote Slides`.

= What markup does it output? =
It outputs markup intended for Simple Slider integration. You can style it as needed.

== Screenshots ==
1. Settings screen for entering the remote feed URL.

== Changelog ==

= 1.2.0 =
- Add license metadata and general cleanup.

= 1.1.0 =
- Stability improvements for remote requests and caching.

= 1.0.0 =
- Initial release.

== Upgrade Notice ==

= 1.2.0 =
Adds license metadata and refinements. Update recommended.

