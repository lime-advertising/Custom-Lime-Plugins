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
        <div class="uca-ics-calendar uca-ics--multi<?php echo ! empty($opts['style_compact']) ? ' uca-ics--compact' : ''; ?><?php echo (! empty($opts['style_view']) && $opts['style_view'] === 'grid') ? ' uca-ics--grid' : ''; ?>">
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

                        $start_fmt = uca_ics_format_dt($start, $atts['datefmt']);
                        $end_fmt   = uca_ics_format_dt($end,   $atts['datefmt']);
                    ?>
                        <li class="uca-ics-item">
                            <div class="uca-ics-when">
                                <time class="uca-ics-start"><?php echo esc_html($start_fmt); ?></time>
                                <?php if ($end_fmt && $end_fmt !== $start_fmt) : ?>
                                    <span class="uca-ics-sep"> – </span>
                                    <time class="uca-ics-end"><?php echo esc_html($end_fmt); ?></time>
                                <?php endif; ?>
                            </div>
                            <div class="uca-ics-main">
                                <div class="uca-ics-summary">
                                    <?php if ($url) : ?>
                                        <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener"><?php echo esc_html($summary); ?></a>
                                    <?php else : ?>
                                        <?php echo esc_html($summary); ?>
                                    <?php endif; ?>
                                    <?php if ($label) : ?>
                                        <span class="uca-ics-badge" aria-label="<?php echo esc_attr($label); ?>"><?php echo esc_html($label); ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($loc) : ?>
                                    <div class="uca-ics-location"><?php echo esc_html($loc); ?></div>
                                <?php endif; ?>
                                <?php if ($desc) : ?>
                                    <div class="uca-ics-desc"><?php echo esc_html($desc); ?></div>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
<?php
        return ob_get_clean();
    }
}
