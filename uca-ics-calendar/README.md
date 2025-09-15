# UCA ICS Calendar

Subscribe to public .ics feeds (Outlook/Office 365, Google Calendar, Apple Calendar, etc.) and display upcoming events on your WordPress site via a simple shortcode.

- Version: 1.0.0
- Author: Rohan T George
- License: GPL-2.0-or-later
- Text Domain: `uca-ics`

## Why

- Simple feed aggregation: Combine multiple public iCalendar feeds into a single list.
- Performance-conscious: Uses WordPress transients to cache parsed events and optional WP-Cron to refresh in the background.
- No heavy dependencies: Lightweight parser handling the most common VEVENT fields.
- Easy to theme: Minimal, unopinionated frontend markup and a small CSS file.

## What

This plugin adds a shortcode, `[ics_calendar]`, that outputs a list of upcoming events pulled from one or more public `.ics` URLs. You can configure feeds in Settings, or override them per-shortcode. Events are fetched and cached to reduce network calls, deduplicated by `UID + DTSTART`, sorted by start time, and displayed with basic styling.

## How It Works

### High-level Flow

1. Admin saves one or more `.ics` URLs (optionally labeled) in Settings → ICS Calendar.
2. On the frontend, the shortcode collects the configured feeds (or uses the ones provided in the shortcode attribute), then loads a cached set of parsed events.
3. If the cache is stale/missing, feeds are fetched with the WordPress HTTP API, parsed, merged, deduplicated, sorted, and cached for the configured number of minutes.
4. The shortcode renders a list, optionally filtering out past events and limiting the number shown.

### Key Components

- `uca-ics-calendar.php`
  - Defines plugin constants and boots the plugin.
  - Registers frontend CSS and sets up a WP-Cron hook (`uca_ics_refresh_hook`) for periodic refresh.
  - On activation, schedules an hourly tick; on deactivation, unschedules and clears cache.

- `includes/class-uca-ics-calendar.php`
  - Registers the `[ics_calendar]` shortcode and an `init` hook to refresh cache when needed.
  - `refresh_cache()`: Fetches configured feeds via `wp_remote_get`, parses them, attaches feed labels, deduplicates by `UID + DTSTART`, sorts by start time, then saves to a transient keyed by the feed set.
  - `shortcode()`: Determines which feeds to use, ensures cache exists, filters/limits results, formats datetimes, and renders markup.

- `includes/class-uca-ics-admin.php`
  - Adds Settings → ICS Calendar page.
  - Settings include a multiline “ICS Feeds” field (one per line, optional `Label|URL` format) and cache duration (minutes).
  - Sanitizes and saves options; triggers a cache refresh on save.

- `includes/helpers.php`
  - `uca_ics_parse_events()`: Lightweight ICS parser for VEVENTS. Handles line folding, reads these common fields: `DTSTART`, `DTEND`, `SUMMARY`, `LOCATION`, `DESCRIPTION`, `URL`, `UID`.
  - `uca_ics_format_dt()`: Converts ICS datetime strings to the site timezone and formats them with `wp_date()`.
  - `uca_ics_collect_feeds()`: Collects feeds from shortcode attributes or saved settings, supports optional labels and de-duplicates by URL.
  - `uca_ics_cache_key_for()`: Produces a stable transient key for a set of feed URLs.

- `assets/css/frontend.css`
  - Minimal styles for the event list and badges when aggregating multiple feeds.

### Caching & WP‑Cron

- Cache storage: Transients keyed by the feed set (MD5 of URLs). Also stores a back-compat transient at `uca_ics_cache`.
- Cache duration: Controlled by “Cache (minutes)” in Settings (minimum 5). Default 360 minutes.
- Background refresh: An hourly WP-Cron tick runs `uca_ics_refresh_hook`, which calls `maybe_refresh_cache()` and refreshes the cache only if stale.
- Manual refresh: Saving settings triggers a refresh. Loading a page with the shortcode also refreshes if the transient is missing/expired.

## Installation

1. Copy this folder to `wp-content/plugins/uca-ics-calendar`.
2. Activate “UCA ICS Calendar” in WordPress → Plugins.
3. Go to Settings → ICS Calendar and add your feed URLs (one per line). Optionally prefix with a label like `Music|https://example.com/music.ics`.
4. Place the `[ics_calendar]` shortcode in a post/page or template.

## Shortcode

Shortcode: `[ics_calendar]`

Attributes:
- `limit`: Maximum events to display. Default `20`.
- `showpast`: Include past events. `yes` or `no`. Default `no`.
- `title`: Section title above the list. Default `Upcoming Events`.
- `datefmt`: PHP-like format string for `wp_date()`. Default `M j, Y g:i a`.
- `feeds`: Optional override for the saved feeds. Comma-separated list where each item is either a URL or `Label|URL`.

Examples:

- Basic:
  
  ```text
  [ics_calendar]
  ```

- Custom title and limit:
  
  ```text
  [ics_calendar title="All Events" limit="30"]
  ```

- Multiple labeled feeds (overrides saved settings):
  
  ```text
  [ics_calendar feeds="General|https://example.com/general.ics,Music|https://example.com/music.ics,Cafeteria|https://example.com/cafe.ics"]
  ```

Notes:
- When multiple feeds are used, each event shows a small badge with the source label (if provided).
- Date-only events (YYYYMMDD) are considered all-day and are not filtered out as “past” by the `showpast=no` filter.

## Markup & Styling

- Container: `.uca-ics-calendar`
- Title: `.uca-ics-title`
- Error: `.uca-ics-error`
- Empty state: `.uca-ics-empty`
- List: `.uca-ics-list` → `.uca-ics-item`
- Datetime: `.uca-ics-when` → `.uca-ics-start`, `.uca-ics-end`
- Summary: `.uca-ics-summary` (link when `URL` is present)
- Location: `.uca-ics-location`
- Description: `.uca-ics-desc`
- Source badge (multi-feed): `.uca-ics-badge` inside `.uca-ics--multi`

You can override or extend styles by dequeuing the plugin CSS and enqueuing your own, or by adding custom CSS to your theme.

## Limitations

- Recurrence: Advanced `RRULE`/`EXDATE` are not expanded/handled in v1. Only explicit VEVENTs are displayed.
- Timezones: `DTSTART/DTEND` with `Z` are converted from UTC to site timezone. Naive local times and date-only values are supported. `TZID` parameter parsing is not implemented.
- ICS fields: Only the common fields listed above are parsed. Others are ignored.
- Filtering: Date-only (all-day) events are always shown when `showpast=no` because their time-of-day is not known.

## Security & Performance

- Uses `wp_remote_get()` with a 15s timeout and a specific User-Agent.
- Escapes output and sanitizes settings.
- Caches merged results to minimize network calls and parsing overhead.

## Development

Project structure:

```
uca-ics-calendar.php
includes/
  ├─ class-uca-ics-calendar.php
  ├─ class-uca-ics-admin.php
  └─ helpers.php
assets/
  └─ css/frontend.css
```

- Shortcode entry point: `includes/class-uca-ics-calendar.php`
- ICS parsing helpers: `includes/helpers.php`
- Admin UI: `includes/class-uca-ics-admin.php`

## Internationalization

- Text domain: `uca-ics`
- Strings are wrapped with the appropriate WordPress translation functions.

## License

GPL-2.0-or-later. See the plugin header for details.

## Changelog

- 1.0.0 — Initial release.

