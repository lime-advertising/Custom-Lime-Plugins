<?php
if (! defined('ABSPATH')) exit;

class UCA_ICS_Calendar_View
{
    const CDN = 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.19/index.global.min.js';

    public function register(): void
    {
        // Admin tab hooks (nav + content)
        add_action('uca_ics_tabs_nav', [$this, 'render_tab_link'], 10, 2);
        add_action('uca_ics_tab_calendar', [$this, 'render_tab_content']);
        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue']);

        // Frontend shortcode
        add_shortcode('ics_calendar_view', [$this, 'shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'maybe_register_assets']);
    }

    public function render_tab_link($active, $base): void
    {
        $url = add_query_arg('tab', 'calendar', $base);
        printf(
            '<a href="%s" class="nav-tab %s">%s</a>',
            esc_url($url),
            $active === 'calendar' ? 'nav-tab-active' : '',
            esc_html__('Calendar View', 'uca-ics')
        );
    }

    public function admin_enqueue($hook): void
    {
        if ($hook !== 'settings_page_uca-ics') return;
        $active = isset($_GET['tab']) ? sanitize_key((string) $_GET['tab']) : 'general';
        if ($active !== 'calendar') return;
        // Load FullCalendar (global build)
        wp_enqueue_script('uca-ics-fullcalendar', self::CDN, [], UCA_ICS_VER, true);
    }

    public function render_tab_content(): void
    {
        // Ensure events are available from current settings
        $feeds = uca_ics_collect_feeds('');
        $events = $this->get_events($feeds);
        $container_id = 'uca-ics-fc-admin';
        ?>
        <h2><?php esc_html_e('Calendar View Preview', 'uca-ics'); ?></h2>
        <p class="description"><?php esc_html_e('This is a preview of your configured feeds in a calendar layout. Use the [ics_calendar_view] shortcode to embed on the frontend.', 'uca-ics'); ?></p>
        <div id="<?php echo esc_attr($container_id); ?>" style="min-height:520px;border:1px solid #dcdcde;border-radius:6px;padding:8px;background:#fff;"></div>
        <script>
        (function(){
            var evts = <?php echo wp_json_encode($events); ?>;
            function boot(){
                if (!window.FullCalendar || !document.getElementById('<?php echo esc_js($container_id); ?>')) { setTimeout(boot, 50); return; }
                var el = document.getElementById('<?php echo esc_js($container_id); ?>');
                var calendar = new FullCalendar.Calendar(el, {
                    initialView: 'dayGridMonth',
                    headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' },
                    events: evts,
                    eventDidMount: function(info){
                        // Always show browser tooltips in admin preview
                        var ep = info.event.extendedProps || {};
                        var parts = [];
                        if (ep.location) parts.push(ep.location);
                        if (ep.description) parts.push(ep.description);
                        var target = info.el.querySelector('a, .fc-event-main') || info.el;
                        if (parts.length) target.setAttribute('title', parts.join('\n'));
                        // Debug log on hover so we can verify JS is firing
                        try {
                            target.addEventListener('mouseenter', function(){
                                if (window.console && console.log) console.log('[UCA ICS] Hover (admin preview):', info.event.title || '(no title)');
                            });
                        } catch(e){}
                    },
                    height: 'auto'
                });
                calendar.render();
            }
            boot();
        })();
        </script>
        <hr>
        <h3><?php esc_html_e('Shortcode & Options', 'uca-ics'); ?></h3>
        <p><?php esc_html_e('Use this shortcode to display the calendar on the frontend:', 'uca-ics'); ?></p>
        <pre><code>[ics_calendar_view]</code></pre>
        <p><strong><?php esc_html_e('Attributes', 'uca-ics'); ?>:</strong></p>
        <ul class="ul-disc">
            <li><code>feeds</code> — <?php esc_html_e('Optional override list of feeds. Accepts URLs or Label|URL pairs, comma-separated. Matches the same format used by the list view.', 'uca-ics'); ?></li>
            <li><code>height</code> — <?php esc_html_e('Container min-height (CSS value). Default: 600px', 'uca-ics'); ?></li>
            <li><code>view</code> — <?php esc_html_e('Initial view. Options: dayGridMonth (default), timeGridWeek, timeGridDay', 'uca-ics'); ?></li>
            <li><code>tooltips</code> — <?php esc_html_e('Show description/location on hover (yes|no). Alias: tooltip', 'uca-ics'); ?></li>
        </ul>
        <p><strong><?php esc_html_e('Examples', 'uca-ics'); ?>:</strong></p>
        <pre>[ics_calendar_view view="timeGridWeek" height="700px" tooltips="yes"]</pre>
        <pre>[ics_calendar_view feeds="General|https://example.com/general.ics,https://example.com/other.ics"]</pre>
        <?php
    }

    public function maybe_register_assets(): void
    {
        // Register handle so shortcode can enqueue it when used
        if (! wp_script_is('uca-ics-fullcalendar', 'registered')) {
            wp_register_script('uca-ics-fullcalendar', self::CDN, [], UCA_ICS_VER, true);
        }
    }

    public function shortcode($atts): string
    {
        $atts = shortcode_atts([
            'feeds'  => '', // optional override
            'height' => '600px',
            'view'   => 'dayGridMonth', // initial view
            'tooltips' => 'no', // yes|no
            // Accept alias "tooltip" as well
            'tooltip'  => null,
        ], $atts);

        $feeds = uca_ics_collect_feeds($atts['feeds']);
        if (empty($feeds)) return '';
        $events = $this->get_events($feeds);

        // Ensure assets
        wp_enqueue_script('uca-ics-fullcalendar');

        $id = 'uca-ics-fc-' . wp_generate_uuid4();
        ob_start(); ?>
        <div id="<?php echo esc_attr($id); ?>" class="uca-ics-fc" style="min-height:<?php echo esc_attr($atts['height']); ?>;"></div>
        <script>
        (function(){
            var evts = <?php echo wp_json_encode($events); ?>;
            var initialView = <?php echo wp_json_encode((string) $atts['view']); ?>;
            var enableTooltips = <?php
                $tt = $atts['tooltips'];
                if ($atts['tooltip'] !== null) $tt = $atts['tooltip'];
                $tt_norm = strtolower((string) $tt);
                $enabled = in_array($tt_norm, ['yes','true','1','on'], true);
                echo wp_json_encode($enabled);
            ?>;
            function boot(){
                if (!window.FullCalendar || !document.getElementById('<?php echo esc_js($id); ?>')) { setTimeout(boot, 50); return; }
                var el = document.getElementById('<?php echo esc_js($id); ?>');
                var calendar = new FullCalendar.Calendar(el, {
                    initialView: initialView,
                    headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' },
                    events: evts,
                    eventDidMount: function(info){
                        var target = info.el.querySelector('a, .fc-event-main') || info.el;
                        if (enableTooltips) {
                            // Debug log on hover for frontend when tooltips enabled
                            try {
                                target.addEventListener('mouseenter', function(){
                                    if (window.console && console.log) console.log('[UCA ICS] Hover (frontend):', info.event.title || '(no title)');
                                });
                            } catch(e){}
                            var ep = info.event.extendedProps || {};
                            var parts = [];
                            if (ep.location) parts.push(ep.location);
                            if (ep.description) parts.push(ep.description);
                            if (parts.length) {
                                target.setAttribute('title', parts.join('\n'));
                            }
                        }
                    },
                    height: 'auto'
                });
                calendar.render();
            }
            boot();
        })();
        </script>
        <?php return ob_get_clean();
    }

    private function get_events(array $feeds): array
    {
        if (empty($feeds)) return [];
        $key   = uca_ics_cache_key_for($feeds);
        $data  = get_transient($key);
        if ($data === false) {
            // Build a one-off calendar to refresh cache using existing class
            if (class_exists('UCA_ICS_Calendar')) {
                (new UCA_ICS_Calendar())->refresh_cache($feeds, $key);
            }
            $data = get_transient($key);
        }
        $events = is_array($data) && ! empty($data['events']) ? (array) $data['events'] : [];
        $out = [];
        foreach ($events as $e) {
            $title = isset($e['summary']) ? (string) $e['summary'] : '';
            $start = isset($e['dtstart']['value']) ? (string) $e['dtstart']['value'] : '';
            $end   = isset($e['dtend']['value'])   ? (string) $e['dtend']['value']   : '';
            $url   = isset($e['url']) ? (string) $e['url'] : '';
            $desc  = isset($e['description']) ? uca_ics_unescape_ics_text((string) $e['description']) : '';
            $loc   = isset($e['location']) ? uca_ics_unescape_ics_text((string) $e['location']) : '';
            $source = isset($e['_source_label']) ? (string) $e['_source_label'] : '';
            $is_all_day = (bool) preg_match('/^\d{8}$/', $start);
            $start_iso = $this->ics_to_iso($start);
            $end_iso   = $this->ics_to_iso($end);
            if ($is_all_day && $start_iso && ! $end_iso) {
                // For all-day without explicit end, set end = start +1 day (exclusive)
                $ts = DateTime::createFromFormat('Y-m-d', $start_iso, wp_timezone());
                if ($ts) {
                    $ts->modify('+1 day');
                    $end_iso = $ts->format('Y-m-d');
                }
            }
            if (! $start_iso) continue;
            $item = [
                'title'  => $title !== '' ? $title : __('(No title)', 'uca-ics'),
                'start'  => $start_iso,
                'allDay' => $is_all_day,
            ];
            if ($end_iso) $item['end'] = $end_iso;
            if ($url) $item['url'] = esc_url($url);
            $item['extendedProps'] = [
                'description' => $desc,
                'location'    => $loc,
                'source'      => $source,
            ];
            $out[] = $item;
        }
        return $out;
    }

    private function ics_to_iso(string $v): string
    {
        $v = trim($v);
        if ($v === '') return '';
        if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $v, $m)) {
            return $m[1] . '-' . $m[2] . '-' . $m[3];
        }
        if (preg_match('/^(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})(\d{2})Z$/', $v, $m)) {
            return sprintf('%s-%s-%sT%s:%s:%sZ', $m[1], $m[2], $m[3], $m[4], $m[5], $m[6]);
        }
        if (preg_match('/^(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})(\d{2})$/', $v, $m)) {
            return sprintf('%s-%s-%sT%s:%s:%s', $m[1], $m[2], $m[3], $m[4], $m[5], $m[6]);
        }
        return '';
    }
}
