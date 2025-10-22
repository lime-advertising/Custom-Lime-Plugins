=== ACF Field Path Shortcode ===
Contributors: lime
Tags: acf, shortcode, custom fields, repeater, group
Requires at least: 5.5
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Display ACF values using a slash-delimited path (e.g., group/subfield), with mailto and money formatting.

== Description ==

Use [myacf] to pull values from ACF including nested group fields and repeater/flexible content.

== Usage ==

- Basic: `[myacf field_path="performance_group/column_heading"]`
- From options page: `[myacf field_path="company/email" post_id="options" mailto="true"]`
- Format money: `[myacf field_path="pricing/amount" format="money"]`
- Combine repeater rows with custom delimiter: `[myacf field_path="features/title" delimiter=" | "]`

== Changelog ==
= 1.0.0 =
* Initial release.

