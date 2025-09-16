<?php
if (! defined('ABSPATH')) exit;

/**
 * Basic ICS parser for VEVENTS.
 * - Handles line folding
 * - Reads DTSTART, DTEND, SUMMARY, LOCATION, DESCRIPTION, URL, UID
 * - Ignores most advanced RRULE/EXDATE (keep simple for v1)
 */
function uca_ics_parse_events($ics_raw)
{
    $lines = preg_split("/\r\n|\n|\r/", trim($ics_raw));
    if (! $lines) return [];

    // Unfold lines (continuations start with space or tab)
    $unfolded = [];
    foreach ($lines as $line) {
        if (isset($unfolded[count($unfolded) - 1]) && (isset($line[0]) && ($line[0] === ' ' || $line[0] === "\t"))) {
            $unfolded[count($unfolded) - 1] .= substr($line, 1);
        } else {
            $unfolded[] = $line;
        }
    }

    $events = [];
    $in_event = false;
    $current = [];

    foreach ($unfolded as $line) {
        if (strtoupper($line) === 'BEGIN:VEVENT') {
            $in_event = true;
            $current  = [];
            continue;
        }
        if (strtoupper($line) === 'END:VEVENT') {
            if (! empty($current)) $events[] = $current;
            $in_event = false;
            $current  = [];
            continue;
        }
        if (! $in_event) continue;

        // Split "KEY;PARAMS:VALUE"
        $parts = explode(':', $line, 2);
        if (count($parts) < 2) continue;

        list($raw_key, $value) = $parts;
        $key_parts = explode(';', $raw_key);
        $prop = strtoupper($key_parts[0]);
        $params = array_slice($key_parts, 1);

        // Basic props
        switch ($prop) {
            case 'DTSTART':
            case 'DTEND':
                $current[strtolower($prop)] = [
                    'value'  => trim($value),
                    'params' => $params,
                ];
                break;
            case 'SUMMARY':
            case 'LOCATION':
            case 'DESCRIPTION':
            case 'URL':
            case 'UID':
                $current[strtolower($prop)] = trim($value);
                break;
            default:
                // ignore others for now
                break;
        }
    }
    return $events;
}

/**
 * Convert ICS datetime string to WordPress-local DateTime and formatted text.
 */
function uca_ics_format_dt($ics_dt, $format = 'M j, Y g:i a')
{
    if (! $ics_dt) return '';
    // Recognize forms: 20250911T120000Z, 20250911T120000, 20250911
    $tz = wp_timezone(); // site timezone
    $dt = null;

    // Remove any TZID param markers if passed in value
    $v = trim($ics_dt);

    // Zulu/UTC with Z
    if (preg_match('/^\d{8}T\d{6}Z$/', $v)) {
        $dt = DateTime::createFromFormat('Ymd\THis\Z', $v, new DateTimeZone('UTC'));
        if ($dt) $dt->setTimezone($tz);
    }
    // Local naive datetime
    if (! $dt && preg_match('/^\d{8}T\d{6}$/', $v)) {
        $dt = DateTime::createFromFormat('Ymd\THis', $v, $tz);
    }
    // Date only
    if (! $dt && preg_match('/^\d{8}$/', $v)) {
        $dt = DateTime::createFromFormat('Ymd', $v, $tz);
    }

    return $dt ? wp_date($format, $dt->getTimestamp()) : esc_html($v);
}

/**
 * Safely get plugin option with default.
 */
function uca_ics_get_option($key, $default = '')
{
    $opts = get_option(UCA_ICS_OPT, []);
    return isset($opts[$key]) ? $opts[$key] : $default;
}

/**
 * Build inline CSS from styling options.
 */
function uca_ics_style_inline_css(array $opts): string
{
    $vars = [];
    if (! empty($opts['style_accent_color'])) {
        $c = sanitize_hex_color($opts['style_accent_color']);
        if ($c) {
            $vars[] = "--uca-ics-link: {$c};";
        }
    }
    if (! empty($opts['style_badge_bg'])) {
        $c = sanitize_hex_color($opts['style_badge_bg']);
        if ($c) {
            $vars[] = "--uca-ics-badge-bg: {$c};";
            // derive border slightly darker if not set; keep default border var
        }
    }
    if (! empty($opts['style_border_color'])) {
        $c = sanitize_hex_color($opts['style_border_color']);
        if ($c) $vars[] = "--uca-ics-border: {$c};";
    }
    if (! empty($opts['style_bg_color'])) {
        $c = sanitize_hex_color($opts['style_bg_color']);
        if ($c) $vars[] = "--uca-ics-bg: {$c};";
    }
    if (! empty($opts['style_item_border_color'])) {
        $c = sanitize_hex_color($opts['style_item_border_color']);
        if ($c) $vars[] = "--uca-ics-item-border: {$c};";
    }
    if (! empty($opts['style_badge_border'])) {
        $c = sanitize_hex_color($opts['style_badge_border']);
        if ($c) $vars[] = "--uca-ics-badge-border: {$c};";
    }
    // Element-specific border colors
    if (! empty($opts['style_when_border_color'])) {
        $c = sanitize_hex_color($opts['style_when_border_color']);
        if ($c) $vars[] = "--uca-ics-when-border-color: {$c};";
    }
    if (! empty($opts['style_link_border_color'])) {
        $c = sanitize_hex_color($opts['style_link_border_color']);
        if ($c) $vars[] = "--uca-ics-link-border-color: {$c};";
    }
    if (! empty($opts['style_location_border_color'])) {
        $c = sanitize_hex_color($opts['style_location_border_color']);
        if ($c) $vars[] = "--uca-ics-location-border-color: {$c};";
    }
    if (! empty($opts['style_desc_border_color'])) {
        $c = sanitize_hex_color($opts['style_desc_border_color']);
        if ($c) $vars[] = "--uca-ics-desc-border-color: {$c};";
    }
    // Button colors
    if (! empty($opts['style_btn_bg'])) {
        $c = sanitize_hex_color($opts['style_btn_bg']);
        if ($c) $vars[] = "--uca-ics-btn-bg: {$c};";
    }
    if (! empty($opts['style_btn_color'])) {
        $c = sanitize_hex_color($opts['style_btn_color']);
        if ($c) $vars[] = "--uca-ics-btn-color: {$c};";
    }
    if (! empty($opts['style_btn_border_color'])) {
        $c = sanitize_hex_color($opts['style_btn_border_color']);
        if ($c) $vars[] = "--uca-ics-btn-border-color: {$c};";
    }
    if (! empty($opts['style_title_color'])) {
        $c = sanitize_hex_color($opts['style_title_color']);
        if ($c) $vars[] = "--uca-ics-title: {$c};";
    }
    if (! empty($opts['style_item_bg'])) {
        $c = sanitize_hex_color($opts['style_item_bg']);
        if ($c) $vars[] = "--uca-ics-item-bg: {$c};";
    }
    if (! empty($opts['style_when_color'])) {
        $c = sanitize_hex_color($opts['style_when_color']);
        if ($c) $vars[] = "--uca-ics-when-color: {$c};";
    }
    if (! empty($opts['style_when_weight'])) {
        $w = sanitize_text_field((string) $opts['style_when_weight']);
        $vars[] = "--uca-ics-when-weight: {$w};";
    }
    if (! empty($opts['style_when_size'])) {
        $s = sanitize_text_field((string) $opts['style_when_size']);
        $vars[] = "--uca-ics-when-size: {$s};";
    }
    // Date parts
    if (! empty($opts['style_day_color'])) {
        $c = sanitize_hex_color($opts['style_day_color']); if ($c) $vars[] = "--uca-ics-day-color: {$c};";
    }
    if (! empty($opts['style_day_size']))  { $vars[] = "--uca-ics-day-size: "  . sanitize_text_field((string)$opts['style_day_size'])  . ';'; }
    if (! empty($opts['style_day_weight'])){ $vars[] = "--uca-ics-day-weight: ". sanitize_text_field((string)$opts['style_day_weight']). ';'; }
    if (! empty($opts['style_month_color'])) {
        $c = sanitize_hex_color($opts['style_month_color']); if ($c) $vars[] = "--uca-ics-month-color: {$c};";
    }
    if (! empty($opts['style_month_size']))  { $vars[] = "--uca-ics-month-size: "  . sanitize_text_field((string)$opts['style_month_size'])  . ';'; }
    if (! empty($opts['style_month_weight'])){ $vars[] = "--uca-ics-month-weight: ". sanitize_text_field((string)$opts['style_month_weight']). ';'; }
    if (! empty($opts['style_year_color'])) {
        $c = sanitize_hex_color($opts['style_year_color']); if ($c) $vars[] = "--uca-ics-year-color: {$c};";
    }
    if (! empty($opts['style_year_size']))  { $vars[] = "--uca-ics-year-size: "  . sanitize_text_field((string)$opts['style_year_size'])  . ';'; }
    if (! empty($opts['style_year_weight'])){ $vars[] = "--uca-ics-year-weight: ". sanitize_text_field((string)$opts['style_year_weight']). ';'; }
    if (! empty($opts['style_title_weight'])) {
        $w = sanitize_text_field((string) $opts['style_title_weight']);
        $vars[] = "--uca-ics-title-weight: {$w};";
    }
    if (! empty($opts['style_title_size'])) {
        $s = sanitize_text_field((string) $opts['style_title_size']);
        $vars[] = "--uca-ics-title-size: {$s};";
    }
    if (! empty($opts['style_title_align'])) {
        $a = sanitize_text_field((string) $opts['style_title_align']);
        $vars[] = "--uca-ics-title-align: {$a};";
    }
    if (! empty($opts['style_badge_color'])) {
        $c = sanitize_hex_color($opts['style_badge_color']);
        if ($c) $vars[] = "--uca-ics-badge-color: {$c};";
    }
    if (! empty($opts['style_badge_size'])) {
        $s = sanitize_text_field((string) $opts['style_badge_size']);
        $vars[] = "--uca-ics-badge-size: {$s};";
    }
    if (! empty($opts['style_desc_color'])) {
        $c = sanitize_hex_color($opts['style_desc_color']);
        if ($c) $vars[] = "--uca-ics-desc: {$c};";
    }
    if (! empty($opts['style_desc_size'])) {
        $s = sanitize_text_field((string) $opts['style_desc_size']);
        $vars[] = "--uca-ics-desc-size: {$s};";
    }
    if (! empty($opts['style_desc_align'])) {
        $a = sanitize_text_field((string) $opts['style_desc_align']);
        $vars[] = "--uca-ics-desc-align: {$a};";
    }
    if (! empty($opts['style_location_color'])) {
        $c = sanitize_hex_color($opts['style_location_color']);
        if ($c) $vars[] = "--uca-ics-location: {$c};";
    }
    if (! empty($opts['style_location_size'])) {
        $s = sanitize_text_field((string) $opts['style_location_size']);
        $vars[] = "--uca-ics-location-size: {$s};"; // not used yet but available
    }
    if (! empty($opts['style_location_align'])) {
        $a = sanitize_text_field((string) $opts['style_location_align']);
        $vars[] = "--uca-ics-location-align: {$a};";
    }
    if (! empty($opts['style_link_weight'])) {
        $w = sanitize_text_field((string) $opts['style_link_weight']);
        $vars[] = "--uca-ics-link-weight: {$w};";
    }
    if (! empty($opts['style_link_decoration'])) {
        $d = sanitize_text_field((string) $opts['style_link_decoration']);
        $vars[] = "--uca-ics-link-decoration: {$d};";
    }
    if (! empty($opts['style_link_size'])) {
        $s = sanitize_text_field((string) $opts['style_link_size']);
        $vars[] = "--uca-ics-link-size: {$s};";
    }
    if (! empty($opts['style_link_style'])) {
        $ls = sanitize_text_field((string) $opts['style_link_style']);
        $vars[] = "--uca-ics-link-style: {$ls};";
    }
    if (! empty($opts['style_link_transform'])) {
        $lt = sanitize_text_field((string) $opts['style_link_transform']);
        $vars[] = "--uca-ics-link-transform: {$lt};";
    }
    if (! empty($opts['style_link_letterspacing'])) {
        $ls2 = sanitize_text_field((string) $opts['style_link_letterspacing']);
        $vars[] = "--uca-ics-link-letterspacing: {$ls2};";
    }
    if (! empty($opts['style_link_align'])) {
        $a = sanitize_text_field((string) $opts['style_link_align']);
        $vars[] = "--uca-ics-link-align: {$a};";
    }
    if (! empty($opts['style_when_align'])) {
        $a = sanitize_text_field((string) $opts['style_when_align']);
        $vars[] = "--uca-ics-when-align: {$a};";
    }
    // Layout: list/grid columns per breakpoint
    $is_grid = (! empty($opts['style_view']) && $opts['style_view'] === 'grid');
    $cols_desktop = $is_grid ? max(1, (int) ($opts['style_cols_desktop'] ?? 1)) : 1;
    $cols_tablet  = $is_grid ? max(1, (int) ($opts['style_cols_tablet'] ?? $cols_desktop)) : 1;
    $cols_mobile  = $is_grid ? max(1, (int) ($opts['style_cols_mobile'] ?? 1)) : 1;
    $vars[] = "--uca-ics-cols: {$cols_desktop};";
    $vars[] = "--uca-ics-cols-tablet: {$cols_tablet};";
    $vars[] = "--uca-ics-cols-mobile: {$cols_mobile};";
    // Scale factors for tablet/mobile
    $scale_tab = (float) ($opts['style_ar_tablet'] ?? 1);
    $scale_mob = (float) ($opts['style_ar_mobile'] ?? 1);
    if ($scale_tab < 0.5) $scale_tab = 0.5;
    if ($scale_tab > 2.0) $scale_tab = 2.0;
    if ($scale_mob < 0.5) $scale_mob = 0.5;
    if ($scale_mob > 2.0) $scale_mob = 2.0;
    $vars[] = "--uca-ics-scale-tablet: {$scale_tab};";
    $vars[] = "--uca-ics-scale-mobile: {$scale_mob};";
    // Shorthand text-based variables (padding/margins/gaps/sizes + border widths/radii)
    $map_text = [
        'style_card_padding'      => '--uca-ics-card-padding',
        'style_card_margin'       => '--uca-ics-card-margin',
        'style_list_gap'          => '--uca-ics-list-gap',
        'style_item_padding'      => '--uca-ics-item-padding',
        'style_item_margin'       => '--uca-ics-item-margin',
        'style_when_margin'       => '--uca-ics-when-margin',
        'style_when_padding'      => '--uca-ics-when-padding',
        'style_title_margin'      => '--uca-ics-title-margin',
        'style_title_padding'     => '--uca-ics-title-padding',
        'style_badge_padding'     => '--uca-ics-badge-padding',
        'style_badge_margin'      => '--uca-ics-badge-margin',
        'style_desc_margin'       => '--uca-ics-desc-margin',
        'style_desc_padding'      => '--uca-ics-desc-padding',
        'style_location_margin'   => '--uca-ics-location-margin',
        'style_location_padding'  => '--uca-ics-location-padding',
        'style_card_border_width' => '--uca-ics-card-border-width',
        'style_card_radius'       => '--uca-ics-card-radius',
        'style_item_border_width' => '--uca-ics-item-border-width',
        'style_item_radius'       => '--uca-ics-item-radius',
        'style_when_border_width' => '--uca-ics-when-border-width',
        'style_when_radius'       => '--uca-ics-when-radius',
        'style_link_border_width' => '--uca-ics-link-border-width',
        'style_link_radius'       => '--uca-ics-link-radius',
        'style_location_border_width' => '--uca-ics-location-border-width',
        'style_location_radius'       => '--uca-ics-location-radius',
        'style_desc_border_width' => '--uca-ics-desc-border-width',
        'style_desc_radius'       => '--uca-ics-desc-radius',
        'style_badge_border_width'=> '--uca-ics-badge-border-width',
        'style_badge_radius'      => '--uca-ics-badge-radius',
    ];
    foreach ($map_text as $opt_key => $var_name) {
        if (! empty($opts[$opt_key])) {
            $v = sanitize_text_field((string) $opts[$opt_key]);
            $vars[] = $var_name . ': ' . $v . ';';
        }
    }
    // Button text-based vars
    if (! empty($opts['style_btn_padding'])) {
        $vars[] = '--uca-ics-btn-padding: ' . sanitize_text_field((string)$opts['style_btn_padding']) . ';';
    }
    if (! empty($opts['style_btn_size'])) {
        $vars[] = '--uca-ics-btn-size: ' . sanitize_text_field((string)$opts['style_btn_size']) . ';';
    }
    if (! empty($opts['style_btn_weight'])) {
        $vars[] = '--uca-ics-btn-weight: ' . sanitize_text_field((string)$opts['style_btn_weight']) . ';';
    }
    if (! empty($opts['style_btn_border_width'])) {
        $vars[] = '--uca-ics-btn-border-width: ' . sanitize_text_field((string)$opts['style_btn_border_width']) . ';';
    }
    if (! empty($opts['style_btn_radius'])) {
        $vars[] = '--uca-ics-btn-radius: ' . sanitize_text_field((string)$opts['style_btn_radius']) . ';';
    }

    // Build CSS once after all variables are collected
    $css = '';
    if ($vars) {
        $css .= '.uca-ics-calendar{' . implode('', $vars) . "}\n";
    }

    if (! empty($opts['style_custom_css'])) {
        $css .= "\n" . (string) $opts['style_custom_css'] . "\n";
    }
    return $css;
}

/**
 * Sanitize a CSS color value. Allows hex (#rgb/#rrggbb) and rgb()/rgba().
 */
function uca_ics_sanitize_css_color($val): string
{
    $val = trim((string) $val);
    if ($val === '') return '';
    $hex = sanitize_hex_color($val);
    if ($hex) return $hex;
    // rgb(a) pattern: rgb(255, 0, 0) or rgba(255,0,0,0.5)
    if (preg_match('/^rgba?\(\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*(,\s*(0|0?\.[0-9]+|1(\.0+)?))?\s*\)$/i', $val)) {
        return $val;
    }
    return '';
}

/**
 * Parse feeds from:
 *  - shortcode attribute feeds="Label|URL,URL,Music|URL"
 *  - or settings textarea (one per line)
 * Returns array of ['label' => string, 'url' => string]
 */
function uca_ics_collect_feeds($shortcode_feeds_attr = ''): array
{
    $pairs = [];

    $push = function ($label, $url) use (&$pairs) {
        $url = esc_url_raw(trim($url));
        if (! $url) return;
        $pairs[] = [
            'label' => $label !== '' ? sanitize_text_field($label) : '',
            'url'   => $url,
        ];
    };

    if ($shortcode_feeds_attr) {
        // Allow passing labels (to match configured feeds) or Label|URL or plain URL.
        $opts = get_option(UCA_ICS_OPT, []);
        $map  = [];
        if (! empty($opts['feeds_list']) && is_array($opts['feeds_list'])) {
            foreach ($opts['feeds_list'] as $row) {
                $label = isset($row['label']) ? (string) $row['label'] : '';
                $url   = isset($row['url']) ? (string) $row['url'] : '';
                if ($label === '' || $url === '') continue;
                $map[strtolower($label)] = $url; // labels are unique
            }
        }

        foreach (explode(',', $shortcode_feeds_attr) as $token) {
            $token = trim($token);
            if ($token === '') continue;
            if (strpos($token, '|') !== false) {
                list($label, $url) = array_map('trim', explode('|', $token, 2));
                $push($label, $url);
            } else {
                // If token looks like a URL, use as-is; otherwise interpret as label
                if (preg_match('#^https?://#i', $token)) {
                    $push('', $token);
                } else {
                    $lk = strtolower($token);
                    if (isset($map[$lk])) {
                        $push($token, $map[$lk]);
                    }
                }
            }
        }
    } else {
        $opts = get_option(UCA_ICS_OPT, []);

        // Preferred: structured repeater list
        if (! empty($opts['feeds_list']) && is_array($opts['feeds_list'])) {
            foreach ($opts['feeds_list'] as $row) {
                $label   = isset($row['label']) ? (string) $row['label'] : '';
                $url     = isset($row['url']) ? (string) $row['url'] : '';
                $enabled = isset($row['enabled']) ? (bool) $row['enabled'] : true; // default to enabled
                if (! $enabled) continue;
                $push($label, $url);
            }
        } else {
            // Back-compat: multiline textarea or single feed_url
            $raw  = (string) ($opts['feeds_multi'] ?? '');
            if (! $raw && ! empty($opts['feed_url'])) {
                // Back-compat: single feed_url
                $push('', $opts['feed_url']);
            } else {
                foreach (preg_split("/\r\n|\n|\r/", $raw) as $line) {
                    $line = trim($line);
                    if ($line === '') continue;
                    if (strpos($line, '|') !== false) {
                        list($label, $url) = array_map('trim', explode('|', $line, 2));
                        $push($label, $url);
                    } else {
                        $push('', $line);
                    }
                }
            }
        }
    }

    // uniq by URL
    $seen = [];
    $out  = [];
    foreach ($pairs as $p) {
        if (isset($seen[$p['url']])) continue;
        $seen[$p['url']] = true;
        $out[] = $p;
    }
    return $out;
}

/** Create a stable cache key for a set of feeds. */
function uca_ics_cache_key_for(array $feeds): string
{
    $urls = array_map(fn($f) => $f['url'], $feeds);
    sort($urls, SORT_STRING);
    return UCA_ICS_TRANSIENT . '_' . md5(implode('|', $urls));
}
