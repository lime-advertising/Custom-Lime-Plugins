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
        <style>
        .uca-ics-tooltip{position:absolute;z-index:99999;background:#111827;color:#fff;padding:8px 10px;border-radius:6px;box-shadow:0 6px 18px rgba(0,0,0,.25);font-size:12px;line-height:1.4;max-width:280px;pointer-events:none;display:none}
        .uca-ics-tooltip .uca-ics-tip-title{font-weight:600;margin:0 0 4px}
        .uca-ics-tooltip .uca-ics-tip-location{color:#e5e7eb;margin:0 0 4px}
        .uca-ics-tooltip .uca-ics-tip-desc{white-space:normal}
        </style>
        <script>
        (function(){
            var evts = <?php echo wp_json_encode($events); ?>;
            var tipEl;
            function ensureTip(){ if (!tipEl){ tipEl = document.createElement('div'); tipEl.className='uca-ics-tooltip'; document.body.appendChild(tipEl);} return tipEl; }
            function esc(s){ return String(s||'').replace(/[&<>"']/g, function(c){return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c]);}); }
            function showTip(target, info){
                var ep = info.event.extendedProps||{};
                var html = '';
                if (info.event.title) html += '<div class="uca-ics-tip-title">'+esc(info.event.title)+'</div>';
                if (ep.location) html += '<div class="uca-ics-tip-location">'+esc(ep.location)+'</div>';
                if (ep.description) html += '<div class="uca-ics-tip-desc">'+esc(ep.description).replace(/\n/g,'<br>')+'</div>';
                var el = ensureTip();
                el.innerHTML = html || esc(info.event.title || '');
                el.style.display='block';
            }
            function hideTip(){ if (tipEl){ tipEl.style.display='none'; } }
            function positionTipByEvent(e){ if (!tipEl || tipEl.style.display==='none') return; var x=e.clientX+12, y=e.clientY+12; var rect=tipEl.getBoundingClientRect(); var vw=window.innerWidth, vh=window.innerHeight; if (x+rect.width>vw-8) x = vw-rect.width-8; if (y+rect.height>vh-8) y = vh-rect.height-8; tipEl.style.left = (window.pageXOffset + x)+'px'; tipEl.style.top=(window.pageYOffset + y)+'px'; }
            function boot(){
                if (!window.FullCalendar || !document.getElementById('<?php echo esc_js($container_id); ?>')) { setTimeout(boot, 50); return; }
                var el = document.getElementById('<?php echo esc_js($container_id); ?>');
                var calendar = new FullCalendar.Calendar(el, {
                    initialView: 'dayGridMonth',
                    headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' },
                    events: evts,
                    eventDidMount: function(info){
                        var target = info.el.querySelector('a, .fc-event-main') || info.el;
                        // Styled popover in admin preview
                        target.addEventListener('mouseenter', function(e){ showTip(target, info); positionTipByEvent(e); });
                        target.addEventListener('mousemove', positionTipByEvent);
                        target.addEventListener('mouseleave', hideTip);
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
        <?php if (in_array(strtolower((string)($atts['tooltip'] ?? $atts['tooltips'])), ['yes','true','1','on'], true)) : ?>
        <style>
        .uca-ics-tooltip{position:absolute;z-index:99999;background:#111827;color:#fff;padding:8px 10px;border-radius:6px;box-shadow:0 6px 18px rgba(0,0,0,.25);font-size:12px;line-height:1.4;max-width:280px;pointer-events:none;display:none}
        .uca-ics-tooltip .uca-ics-tip-title{font-weight:600;margin:0 0 4px}
        .uca-ics-tooltip .uca-ics-tip-location{color:#e5e7eb;margin:0 0 4px}
        .uca-ics-tooltip .uca-ics-tip-desc{white-space:normal}
        </style>
        <?php endif; ?>
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
            var tipEl;
            function ensureTip(){ if (!tipEl){ tipEl = document.createElement('div'); tipEl.className='uca-ics-tooltip'; document.body.appendChild(tipEl);} return tipEl; }
            function esc(s){ return String(s||'').replace(/[&<>"']/g, function(c){return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c]);}); }
            function showTip(target, info){
                var ep = info.event.extendedProps||{};
                var html = '';
                if (info.event.title) html += '<div class="uca-ics-tip-title">'+esc(info.event.title)+'</div>';
                if (ep.location) html += '<div class="uca-ics-tip-location">'+esc(ep.location)+'</div>';
                if (ep.description) html += '<div class="uca-ics-tip-desc">'+esc(ep.description).replace(/\n/g,'<br>')+'</div>';
                var el = ensureTip();
                el.innerHTML = html || esc(info.event.title || '');
                el.style.display='block';
            }
            function hideTip(){ if (tipEl){ tipEl.style.display='none'; } }
            function positionTipByEvent(e){ if (!tipEl || tipEl.style.display==='none') return; var x=e.clientX+12, y=e.clientY+12; var rect=tipEl.getBoundingClientRect(); var vw=window.innerWidth, vh=window.innerHeight; if (x+rect.width>vw-8) x = vw-rect.width-8; if (y+rect.height>vh-8) y = vh-rect.height-8; tipEl.style.left = (window.pageXOffset + x)+'px'; tipEl.style.top=(window.pageYOffset + y)+'px'; }
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
                            target.addEventListener('mouseenter', function(e){ showTip(target, info); positionTipByEvent(e); });
                            target.addEventListener('mousemove', positionTipByEvent);
                            target.addEventListener('mouseleave', hideTip);
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
            $title = isset($e['summary']) ? uca_ics_unescape_ics_text((string) $e['summary']) : '';
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
