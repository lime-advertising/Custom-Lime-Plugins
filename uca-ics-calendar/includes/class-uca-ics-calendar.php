<?php
if (! defined('ABSPATH')) exit;

class UCA_ICS_Calendar
{

    public function register(): void
    {
        add_shortcode('ics_calendar', [$this, 'shortcode']);
        add_action('init', [$this, 'maybe_refresh_cache']);
    }

    /**
     * Refresh cache if transient expired or missing.
     * Triggered on init and via cron hook.
     */
    public function maybe_refresh_cache(): void
    {
        // Determine feed set (from settings only on init)
        $feeds = uca_ics_collect_feeds('');
        if (empty($feeds)) {
            delete_transient(UCA_ICS_TRANSIENT);
            return;
        }
        $key = uca_ics_cache_key_for($feeds);
        $cached = get_transient($key);
        if (false !== $cached) return;
        $this->refresh_cache($feeds, $key);
    }

    /**
     * @param array|null $feeds Optional explicit feeds (label,url). If null, pull from settings.
     * @param string|null $key  Optional cache key to use (for consistency across calls).
     */
    public function refresh_cache(?array $feeds = null, ?string $key = null): void
    {
        if ($feeds === null) $feeds = uca_ics_collect_feeds('');
        if (empty($feeds)) {
            delete_transient(UCA_ICS_TRANSIENT);
            return;
        }
        if ($key === null) $key = uca_ics_cache_key_for($feeds);

        $timeout = 15;
        $all_events = [];
        $errors = [];

        foreach ($feeds as $feed) {
            $resp = wp_remote_get($feed['url'], [
                'timeout' => $timeout,
                'headers' => ['User-Agent' => 'UCA-ICS-Calendar/' . UCA_ICS_VER],
            ]);
            if (is_wp_error($resp)) {
                $errors[] = ($feed['label'] ? $feed['label'] . ': ' : '') . $resp->get_error_message();
                continue;
            }
            $code = wp_remote_retrieve_response_code($resp);
            $body = wp_remote_retrieve_body($resp);
            if ($code !== 200 || empty($body)) {
                $errors[] = ($feed['label'] ? $feed['label'] . ': ' : '') . "HTTP $code";
                continue;
            }
            $events = uca_ics_parse_events($body);
            // attach source label to each event
            foreach ($events as &$e) {
                $e['_source_label'] = $feed['label'] ?: '';
            }
            unset($e);
            $all_events = array_merge($all_events, $events);
        }

        // Dedupe by UID + DTSTART
        $uniq = [];
        $merged = [];
        foreach ($all_events as $e) {
            $uid = isset($e['uid']) ? $e['uid'] : '';
            $st  = isset($e['dtstart']['value']) ? $e['dtstart']['value'] : '';
            $k = $uid . '|' . $st;
            if (isset($uniq[$k])) continue;
            $uniq[$k] = true;
            $merged[] = $e;
        }

        // Sort by start ascending
        usort($merged, function ($a, $b) {
            $av = $a['dtstart']['value'] ?? '';
            $bv = $b['dtstart']['value'] ?? '';
            return strcmp($av, $bv);
        });

        $minutes = max(5, (int) uca_ics_get_option('cache_minutes', 360));
        set_transient($key, ['error' => $errors ? implode(' | ', $errors) : null, 'events' => $merged], $minutes * MINUTE_IN_SECONDS);

        // For back-compat (old key name) keep the most recent multi-cache available
        set_transient(UCA_ICS_TRANSIENT, ['error' => $errors ? implode(' | ', $errors) : null, 'events' => $merged], $minutes * MINUTE_IN_SECONDS);
    }


    public function shortcode($atts, $content = ''): string
    {
        $raw_atts = is_array($atts) ? $atts : [];
        $atts = shortcode_atts([
            'limit'    => 20,
            'showpast' => 'no',
            'title'    => 'Upcoming Events',
            'datefmt'  => 'M j, Y g:i a',
            'feeds'    => '', // optional override: CSV of Label|URL,URL,Label|URL
        ], $atts);

        wp_enqueue_style('uca-ics-frontend');
        // Apply styling overrides
        $opts = get_option(UCA_ICS_OPT, []);
        $inline = uca_ics_style_inline_css($opts);
        if ($inline) {
            wp_add_inline_style('uca-ics-frontend', $inline);
        }

        // Determine which feeds to use (shortcode overrides settings)
        $feeds = uca_ics_collect_feeds($atts['feeds']);
        if (empty($feeds)) {
            return '<div class="uca-ics-calendar"><p class="uca-ics-empty">' . esc_html__('No feeds configured.', 'uca-ics') . '</p></div>';
        }
        $key   = uca_ics_cache_key_for($feeds);
        $data  = get_transient($key);
        if (false === $data) {
            $this->refresh_cache($feeds, $key);
            $data = get_transient($key);
        }

        $error  = $data['error']  ?? null;
        $events = $data['events'] ?? [];

        // Filter past events unless showpast=yes
        $now_ics = gmdate('Ymd\THis\Z');
        if (strtolower($atts['showpast']) !== 'yes') {
            $events = array_filter($events, function ($e) use ($now_ics) {
                $start = $e['dtstart']['value'] ?? '';
                return strcmp($start, $now_ics) >= 0 || preg_match('/^\d{8}$/', $start);
            });
        }

        if (! empty($atts['limit'])) {
            $events = array_slice($events, 0, (int) $atts['limit']);
        }

        ob_start(); ?>
        <div class="uca-ics-calendar uca-ics--multi<?php echo ! empty($opts['style_compact']) ? ' uca-ics--compact' : ''; ?><?php echo (! empty($opts['style_view']) && $opts['style_view'] === 'grid') ? ' uca-ics--grid' : ''; ?><?php echo (! empty($opts['layout_preset']) && $opts['layout_preset'] === 'split_button') ? ' uca-ics--preset-split' : ''; ?>">
            <?php if (! empty($atts['title'])) : ?>
                <h3 class="uca-ics-title"><?php echo esc_html($atts['title']); ?></h3>
            <?php endif; ?>

            <?php if ($error) : ?>
                <div class="uca-ics-error"><?php echo esc_html($error); ?></div>
            <?php endif; ?>

            <?php if (empty($events)) : ?>
                <p class="uca-ics-empty"><?php esc_html_e('No events found.', 'uca-ics'); ?></p>
            <?php else : ?>
                <ul class="uca-ics-list">
                    <?php foreach ($events as $e) :
                        $start = $e['dtstart']['value'] ?? '';
                        $end   = $e['dtend']['value']   ?? '';
                        $summary = $e['summary'] ?? '';
                        $loc     = $e['location'] ?? '';
                        $desc    = $e['description'] ?? '';
                        $url     = $e['url'] ?? '';
                        $label   = $e['_source_label'] ?? '';

                        // Resolve date format: shortcode override or global setting
                        $opts_local = get_option(UCA_ICS_OPT, []);
                        $user_datefmt = isset($raw_atts['datefmt']) ? trim((string)$raw_atts['datefmt']) : '';
                        $fmt_choice = $user_datefmt !== '' ? $user_datefmt : ($opts_local['date_format_choice'] ?? 'site');
                        if ($fmt_choice === 'site') {
                            $fmt = trim(get_option('date_format') . ' ' . get_option('time_format'));
                        } elseif ($fmt_choice === 'custom') {
                            $fmt = (string)($opts_local['date_format_custom'] ?? 'M j, Y g:i a');
                        } else {
                            $fmt = (string)$fmt_choice;
                        }
                        $start_only = ! empty($opts_local['start_date_only']);
                        if ($start_only) {
                            // Date-only output; derive from selection
                            if ($fmt_choice === 'site') {
                                $fmt_start = get_option('date_format');
                            } elseif ($fmt_choice === 'custom') {
                                $fmt_start = $fmt;
                            } else {
                                // Strip time from known presets
                                $map_date_only = [
                                    'M j, Y g:i a' => 'M j, Y',
                                    'F j, Y g:i a' => 'F j, Y',
                                    'Y-m-d H:i'    => 'Y-m-d',
                                ];
                                $fmt_start = $map_date_only[$fmt_choice] ?? get_option('date_format');
                            }
                            $fmt_end = '';
                        } else {
                            $fmt_start = $fmt;
                            $fmt_end   = $fmt;
                        }

                        // Build parts for date rendering, respecting selected format tokens
                        $start_ts = (int) uca_ics_format_dt($start, 'U');
                        $end_ts   = $fmt_end ? (int) uca_ics_format_dt($end, 'U') : 0;
                        $start_fmt = uca_ics_format_dt($start, $fmt_start);
                        $end_fmt   = $fmt_end ? uca_ics_format_dt($end, $fmt_end) : '';

                        $fmt_detect = (string)$fmt_start;
                        $has_day   = (bool) preg_match('/(?<!\\\\)[dj]/', $fmt_detect);
                        $has_month = (bool) preg_match('/(?<!\\\\)[FMmn]/', $fmt_detect);
                        $has_year  = (bool) preg_match('/(?<!\\\\)[Yy]/', $fmt_detect);
                        $has_time  = (!$start_only) && (bool) preg_match('/(?<!\\\\)[gGhHis]/', $fmt_detect);

                        // Choose token-specific renderings
                        if ($start_ts) {
                            // Day
                            if (preg_match('/(?<!\\\\)d/', $fmt_detect)) {
                                $day = wp_date('d', $start_ts);
                            } else {
                                $day = wp_date('j', $start_ts);
                            }
                            // Month
                            if (preg_match('/(?<!\\\\)F/', $fmt_detect)) {
                                $month = wp_date('F', $start_ts);
                            } elseif (preg_match('/(?<!\\\\)M/', $fmt_detect)) {
                                $month = wp_date('M', $start_ts);
                            } elseif (preg_match('/(?<!\\\\)m/', $fmt_detect)) {
                                $month = wp_date('m', $start_ts);
                            } else {
                                $month = wp_date('n', $start_ts);
                            }
                            // Year
                            if (preg_match('/(?<!\\\\)y/', $fmt_detect)) {
                                $year = wp_date('y', $start_ts);
                            } else {
                                $year = wp_date('Y', $start_ts);
                            }
                            // Time
                            if ($has_time) {
                                $time_start = wp_date(get_option('time_format'), $start_ts);
                            } else {
                                $time_start = '';
                            }
                        } else {
                            $day = $month = $year = $time_start = '';
                        }
                        if ($end_ts && !$start_only) {
                            $time_end = wp_date(get_option('time_format'), $end_ts);
                        } else {
                            $time_end = '';
                        }
                        $opts_local = get_option(UCA_ICS_OPT, []);
                        $order_csv  = isset($opts_local['elements_order']) ? (string) $opts_local['elements_order'] : 'when,summary,location,desc';
                        $order      = array_values(array_filter(array_map('trim', explode(',', $order_csv))));
                        $allowed    = ['when','summary','location','desc'];
                        foreach ($allowed as $k) if (! in_array($k, $order, true)) $order[] = $k;
                        $show = [
                            'when'     => ! empty($opts_local['show_when']),
                            'summary'  => ! empty($opts_local['show_summary']),
                            'location' => ! empty($opts_local['show_location']),
                            'desc'     => ! empty($opts_local['show_desc']),
                            'badge'    => ! empty($opts_local['show_badge']),
                        ];
                    ?>
                        <li class="uca-ics-item">
                            <?php if (!empty($opts_local['layout_preset']) && $opts_local['layout_preset'] === 'split_button') : ?>
                                <div class="uca-ics-row">
                                    <div class="uca-ics-col-left">
                                        <?php if ($show['when']) : ?>
                                            <div class="uca-ics-when">
                                                <time class="uca-ics-start">
                                                    <?php if ($has_day && $day) : ?><span class="uca-ics-day"><?php echo esc_html($day); ?></span><?php endif; ?>
                                                    <?php if ($has_month && $month) : ?><span class="uca-ics-month"><?php echo esc_html($month); ?></span><?php endif; ?>
                                                    <?php if ($has_year && $year) : ?><span class="uca-ics-year"><?php echo esc_html($year); ?></span><?php endif; ?>
                                                    <?php if ($has_time && $time_start) : ?><span class="uca-ics-time"><?php echo esc_html($time_start); ?></span><?php endif; ?>
                                                </time>
                                                <?php if ($has_time && $time_end && $time_end !== $time_start) : ?>
                                                    <span class="uca-ics-sep"> – </span>
                                                    <time class="uca-ics-end"><?php echo esc_html($time_end); ?></time>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="uca-ics-col-right">
                                        <?php
                                        // Build right-column blocks (respect element visibility and order; 'when' stays on left)
                                        $blocks_right = [];
                                        $common = trim((string)($opts_local['button_url'] ?? ''));
                                        $link_href = $url ?: $common; // fall back to common URL when event URL missing

                                        if ($show['summary']) {
                                            ob_start(); ?>
                                            <div class="uca-ics-summary">
                                                <?php if ($link_href) : ?>
                                                    <a href="<?php echo esc_url($link_href); ?>" target="_blank" rel="noopener"><?php echo esc_html($summary); ?></a>
                                                <?php else : ?>
                                                    <?php echo esc_html($summary); ?>
                                                <?php endif; ?>
                                                <?php if ($label && $show['badge']) : ?>
                                                    <span class="uca-ics-badge" aria-label="<?php echo esc_attr($label); ?>"><?php echo esc_html($label); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <?php $blocks_right['summary'] = ob_get_clean();
                                        }
                                        if ($show['location'] && $loc) {
                                            $blocks_right['location'] = '<div class="uca-ics-location">' . esc_html($loc) . '</div>';
                                        }
                                        if ($show['desc'] && $desc) {
                                            $blocks_right['desc'] = '<div class="uca-ics-desc">' . esc_html($desc) . '</div>';
                                        }

                                        // Output per configured order, skipping 'when' (already on left)
                                        foreach ($order as $k) {
                                            if ($k === 'when') continue;
                                            if (! empty($blocks_right[$k])) echo $blocks_right[$k];
                                        }

                                        // Render CTA button
                                        $btn_href = '';
                                        if ($common !== '') {
                                            $btn_href = $common;
                                        } elseif ($url) {
                                            $btn_href = $url;
                                        }
                                        if ($btn_href) : ?>
                                            <a class="uca-ics-btn" href="<?php echo esc_url($btn_href); ?>" target="_blank" rel="noopener"><?php echo esc_html($opts_local['button_text'] ?? 'Open Calendar'); ?></a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php else : ?>
                                <?php
                                // Default layout: Prebuild blocks and output per order
                                $blocks = [];
                                if ($show['when']) {
                                    ob_start(); ?>
                                    <div class="uca-ics-when">
                                        <time class="uca-ics-start">
                                            <?php if ($has_day && $day) : ?><span class="uca-ics-day"><?php echo esc_html($day); ?></span><?php endif; ?>
                                            <?php if ($has_month && $month) : ?><span class="uca-ics-month"><?php echo esc_html($month); ?></span><?php endif; ?>
                                            <?php if ($has_year && $year) : ?><span class="uca-ics-year"><?php echo esc_html($year); ?></span><?php endif; ?>
                                            <?php if ($has_time && $time_start) : ?><span class="uca-ics-time"><?php echo esc_html($time_start); ?></span><?php endif; ?>
                                        </time>
                                        <?php if ($has_time && $time_end && $time_end !== $time_start) : ?>
                                            <span class="uca-ics-sep"> – </span>
                                            <time class="uca-ics-end"><?php echo esc_html($time_end); ?></time>
                                        <?php endif; ?>
                                    </div>
                                    <?php $blocks['when'] = ob_get_clean();
                                }
                                if ($show['summary']) {
                                    ob_start(); ?>
                                    <div class="uca-ics-summary">
                                        <?php if ($url) : ?>
                                            <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener"><?php echo esc_html($summary); ?></a>
                                        <?php else : ?>
                                            <?php echo esc_html($summary); ?>
                                        <?php endif; ?>
                                        <?php if ($label && $show['badge']) : ?>
                                            <span class="uca-ics-badge" aria-label="<?php echo esc_attr($label); ?>"><?php echo esc_html($label); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php $blocks['summary'] = ob_get_clean();
                                }
                                if ($show['location'] && $loc) {
                                    $blocks['location'] = '<div class="uca-ics-location">' . esc_html($loc) . '</div>';
                                }
                                if ($show['desc'] && $desc) {
                                    $blocks['desc'] = '<div class="uca-ics-desc">' . esc_html($desc) . '</div>';
                                }
                                foreach ($order as $k) {
                                    if (! empty($blocks[$k])) echo $blocks[$k];
                                }
                                ?>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
<?php
        return ob_get_clean();
    }
}
