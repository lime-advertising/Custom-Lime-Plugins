<?php
if (! defined('ABSPATH')) exit;

class UCA_ICS_Admin
{

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this, 'settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public function menu(): void
    {
        add_options_page(
            __('ICS Calendar', 'uca-ics'),
            __('ICS Calendar', 'uca-ics'),
            'manage_options',
            'uca-ics',
            [$this, 'render']
        );
    }

    public function settings(): void
    {
        register_setting('uca_ics_group', UCA_ICS_OPT, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitize'],
            'default'           => [
                'feed_url'      => '',
                'cache_minutes' => 360,
                'feeds_list'    => [],
                'style_compact' => 0,
                'style_accent_color' => '',
                'style_badge_bg' => '',
                'style_border_color' => '',
                'style_bg_color' => '',
                'style_item_border_color' => '',
                'style_badge_border' => '',
                'style_title_color' => '',
                'style_item_bg' => '',
                'style_when_color' => '',
                'style_when_weight' => '',
                'style_when_size' => '',
                'style_title_weight' => '',
                'style_title_size' => '',
                'style_badge_color' => '',
                'style_badge_size' => '',
                'style_desc_color' => '',
                'style_desc_size' => '',
                'style_location_color' => '',
                'style_location_size' => '',
                'style_link_weight' => '',
                'style_link_decoration' => '',
                'style_view' => 'list',
                'style_cols_desktop' => 1,
                'style_cols_tablet' => 1,
                'style_cols_mobile' => 1,
                'style_ar_tablet' => '1',
                'style_ar_mobile' => '1',
                'style_card_padding' => '',
                'style_card_margin' => '',
                'style_list_gap' => '',
                'style_item_padding' => '',
                'style_item_margin' => '',
                'style_when_margin' => '',
                'style_when_padding' => '',
                'style_title_margin' => '',
                'style_title_padding' => '',
                'style_badge_padding' => '',
                'style_badge_margin' => '',
                'style_desc_margin' => '',
                'style_desc_padding' => '',
                'style_location_margin' => '',
                'style_location_padding' => '',
                // Element visibility and ordering
                'elements_order' => 'when,summary,location,desc',
                'show_when'     => 1,
                'show_summary'  => 1,
                'show_location' => 1,
                'show_desc'     => 1,
                'show_badge'    => 1,
                'style_custom_css' => '',
            ],
        ]);

        add_settings_section(
            'uca_ics_main',
            __('ICS Feed Settings', 'uca-ics'),
            function () {
                echo '<p>' . esc_html__('Paste a public .ics URL (Outlook/Google/Apple). The plugin will cache and display events via shortcode.', 'uca-ics') . '</p>';
            },
            'uca-ics'
        );

        add_settings_field(
            'feeds_list',
            __('ICS Feeds', 'uca-ics'),
            [$this, 'field_feeds_list'],
            'uca-ics',
            'uca_ics_main'
        );


        add_settings_field(
            'cache_minutes',
            __('Cache (minutes)', 'uca-ics'),
            [$this, 'field_cache_minutes'],
            'uca-ics',
            'uca_ics_main'
        );

        // Styling tab sections and fields
        // We render Styling tab manually, so no need to register fields for it here
        add_settings_field(
            'style_custom_css',
            __('Custom CSS', 'uca-ics'),
            [$this, 'field_style_custom_css'],
            'uca-ics-style',
            'uca_ics_style'
        );
    }

    public function field_feeds_list(): void
    {
        $opts = get_option(UCA_ICS_OPT, []);
        $rows = [];
        if (! empty($opts['feeds_list']) && is_array($opts['feeds_list'])) {
            $rows = $opts['feeds_list'];
        } else {
            // Back-compat: seed from textarea if present
            $seed = isset($opts['feeds_multi']) ? (string) $opts['feeds_multi'] : '';
            if ($seed !== '') {
                foreach (preg_split("/\r\n|\n|\r/", $seed) as $line) {
                    $line = trim($line);
                    if ($line === '') continue;
                    if (strpos($line, '|') !== false) {
                        list($label, $url) = array_map('trim', explode('|', $line, 2));
                        $rows[] = ['label' => $label, 'url' => $url];
                    } else {
                        $rows[] = ['label' => '', 'url' => $line];
                    }
                }
            }
        }

        if (empty($rows)) {
            $rows = [['label' => '', 'url' => '']];
        }

        $opt_name = esc_attr(UCA_ICS_OPT);
        echo '<table class="widefat fixed striped" id="uca-ics-feeds-table">';
        echo '<thead><tr><th style="width:25%">' . esc_html__('Label', 'uca-ics') . '</th><th>' . esc_html__('URL', 'uca-ics') . '</th><th style="width:180px">' . esc_html__('Actions', 'uca-ics') . '</th></tr></thead>';
        echo '<tbody>';
        foreach ($rows as $i => $r) {
            $label = isset($r['label']) ? $r['label'] : '';
            $url   = isset($r['url']) ? $r['url'] : '';
            $enabled = array_key_exists('enabled', (array) $r) ? (bool) $r['enabled'] : true;
            printf(
                '<tr class="uca-ics-feed-row">'
                    . '<td><input type="text" name="%1$s[feeds_list][%2$d][label]" value="%3$s" class="regular-text" /></td>'
                    . '<td><input type="url"  name="%1$s[feeds_list][%2$d][url]"   value="%4$s" class="regular-text code" placeholder="https://.../calendar.ics" /></td>'
                    . '<td class="uca-ics-actions"><label><input type="checkbox" name="%1$s[feeds_list][%2$d][enabled]" value="1" %6$s /> %7$s</label> '
                    . '<button type="button" class="button link-delete uca-ics-row-remove">%5$s</button></td>'
                    . '</tr>',
                $opt_name,
                (int) $i,
                esc_attr($label),
                esc_attr($url),
                esc_html__('Remove', 'uca-ics'),
                checked(true, $enabled, false),
                esc_html__('Enabled', 'uca-ics')
            );
        }
        echo '</tbody>';
        echo '</table>';
        echo '<p><button type="button" class="button button-secondary" id="uca-ics-row-add">' . esc_html__('Add Feed', 'uca-ics') . '</button></p>';

        // Row template for JS cloning
        $tmpl = sprintf(
            '<script type="text/html" id="tmpl-uca-ics-row">'
                . '<tr class="uca-ics-feed-row">'
                . '<td><input type="text" name="%1$s[feeds_list][__index__][label]" value="" class="regular-text" /></td>'
                . '<td><input type="url"  name="%1$s[feeds_list][__index__][url]"   value="" class="regular-text code" placeholder="https://.../calendar.ics" /></td>'
                . '<td class="uca-ics-actions"><label><input type="checkbox" name="%1$s[feeds_list][__index__][enabled]" value="1" checked /> %3$s</label> '
                . '<button type="button" class="button link-delete uca-ics-row-remove">%2$s</button></td>'
                . '</tr>'
                . '</script>',
            $opt_name,
            esc_html__('Remove', 'uca-ics'),
            esc_html__('Enabled', 'uca-ics')
        );
        echo $tmpl; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

        echo '<p class="description">' . esc_html__('Add one or more public .ics feed URLs. Labels must be unique and both Label and URL are required.', 'uca-ics') . '</p>';
    }

    public function sanitize($input): array
    {
        // Start from existing options so saving one tab doesn't wipe others
        $existing = get_option(UCA_ICS_OPT, []);
        $out = is_array($existing) ? $existing : [];
        // keep old keys for backward-compat
        if (array_key_exists('feed_url', $input)) {
            $out['feed_url'] = esc_url_raw(trim((string) $input['feed_url']));
        }
        if (array_key_exists('cache_minutes', $input)) {
            $out['cache_minutes'] = max(5, (int) $input['cache_minutes']);
        } elseif (! isset($out['cache_minutes'])) {
            $out['cache_minutes'] = 360;
        }
        // New structured list
        $feeds_changed = false;
        if (! isset($out['feeds_list']) || ! is_array($out['feeds_list'])) {
            $out['feeds_list'] = [];
        }
        $seen_labels = [];
        $missing_count = 0;
        $dupe_labels = [];

        if (array_key_exists('feeds_list', $input) && is_array($input['feeds_list'])) {
            $out['feeds_list'] = [];
            foreach ($input['feeds_list'] as $row) {
                $label   = isset($row['label']) ? sanitize_text_field((string) $row['label']) : '';
                $url     = isset($row['url']) ? esc_url_raw(trim((string) $row['url'])) : '';
                $enabled = ! empty($row['enabled']) ? 1 : 0;
                if ($label === '' || $url === '') {
                    $missing_count++;
                    continue; // both required
                }
                $lk = strtolower($label);
                if (isset($seen_labels[$lk])) {
                    $dupe_labels[$lk] = $label; // keep last seen original casing
                    continue; // skip duplicates, prefer first occurrence
                }
                $seen_labels[$lk] = $label;
                $out['feeds_list'][] = ['label' => $label, 'url' => $url, 'enabled' => $enabled];
            }
            $feeds_changed = true;
        }

        // Back-compat: accept textarea if present
        if (isset($input['feeds_multi']) && is_string($input['feeds_multi']) && trim($input['feeds_multi']) !== '') {
            $lines = preg_split("/\r\n|\n|\r/", trim($input['feeds_multi']));
            $parsed_list = [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') continue;
                if (strpos($line, '|') !== false) {
                    list($label, $url) = array_map('trim', explode('|', $line, 2));
                    $label = sanitize_text_field($label);
                    $url   = esc_url_raw($url);
                } else {
                    $label = '';
                    $url   = esc_url_raw($line);
                }
                if ($label === '' || ! $url) {
                    $missing_count++;
                } else {
                    $lk = strtolower($label);
                    if (! isset($seen_labels[$lk])) {
                        $seen_labels[$lk] = $label;
                        $parsed_list[] = ['label' => $label, 'url' => $url, 'enabled' => 1];
                    } else {
                        $dupe_labels[$lk] = $label;
                    }
                }
            }
            if ($parsed_list) {
                $out['feeds_list'] = $parsed_list;
                $feeds_changed = true;
            }
        }

        // Styling options
        if (array_key_exists('style_compact', $input)) {
            $out['style_compact'] = ! empty($input['style_compact']) ? 1 : 0;
        }
        if (array_key_exists('style_view', $input)) {
            $view = $input['style_view'] === 'grid' ? 'grid' : 'list';
            $out['style_view'] = $view;
        }
        // Per-breakpoint grid columns
        foreach (['desktop' => 'style_cols_desktop', 'tablet' => 'style_cols_tablet', 'mobile' => 'style_cols_mobile'] as $k => $key) {
            if (array_key_exists($key, $input)) {
                $out[$key] = max(1, min(6, (int) $input[$key]));
            }
        }
        // Fallback from old single grid cols
        if (array_key_exists('style_grid_cols', $input) && empty($input['style_cols_desktop'])) {
            $out['style_cols_desktop'] = max(1, min(6, (int) $input['style_grid_cols']));
        }
        // Aspect ratio scales
        foreach (['style_ar_tablet', 'style_ar_mobile'] as $key) {
            if (array_key_exists($key, $input)) {
                $val = (float) $input[$key];
                if ($val < 0.5) $val = 0.5;
                if ($val > 2.0) $val = 2.0;
                $out[$key] = (string) $val;
            }
        }
        if (array_key_exists('style_accent_color', $input)) {
            $out['style_accent_color'] = '';
            if (! empty($input['style_accent_color'])) {
                $c = function_exists('uca_ics_sanitize_css_color') ? uca_ics_sanitize_css_color((string) $input['style_accent_color']) : sanitize_text_field((string) $input['style_accent_color']);
                if ($c) $out['style_accent_color'] = $c;
            }
        }
        if (array_key_exists('style_badge_bg', $input)) {
            $out['style_badge_bg'] = '';
            if (! empty($input['style_badge_bg'])) {
                $c = function_exists('uca_ics_sanitize_css_color') ? uca_ics_sanitize_css_color((string) $input['style_badge_bg']) : sanitize_text_field((string) $input['style_badge_bg']);
                if ($c) $out['style_badge_bg'] = $c;
            }
        }
        if (array_key_exists('style_badge_border', $input)) {
            $out['style_badge_border'] = '';
            if (! empty($input['style_badge_border'])) {
                $c = function_exists('uca_ics_sanitize_css_color') ? uca_ics_sanitize_css_color((string) $input['style_badge_border']) : sanitize_text_field((string) $input['style_badge_border']);
                if ($c) $out['style_badge_border'] = $c;
            }
        }
        if (array_key_exists('style_border_color', $input)) {
            $out['style_border_color'] = '';
            if (! empty($input['style_border_color'])) {
                $c = function_exists('uca_ics_sanitize_css_color') ? uca_ics_sanitize_css_color((string) $input['style_border_color']) : sanitize_text_field((string) $input['style_border_color']);
                if ($c) $out['style_border_color'] = $c;
            }
        }
        if (array_key_exists('style_bg_color', $input)) {
            $out['style_bg_color'] = '';
            if (! empty($input['style_bg_color'])) {
                $c = function_exists('uca_ics_sanitize_css_color') ? uca_ics_sanitize_css_color((string) $input['style_bg_color']) : sanitize_text_field((string) $input['style_bg_color']);
                if ($c) $out['style_bg_color'] = $c;
            }
        }
        if (array_key_exists('style_item_border_color', $input)) {
            $out['style_item_border_color'] = '';
            if (! empty($input['style_item_border_color'])) {
                $c = function_exists('uca_ics_sanitize_css_color') ? uca_ics_sanitize_css_color((string) $input['style_item_border_color']) : sanitize_text_field((string) $input['style_item_border_color']);
                if ($c) $out['style_item_border_color'] = $c;
            }
        }
        if (array_key_exists('style_title_color', $input)) {
            $out['style_title_color'] = '';
            if (! empty($input['style_title_color'])) {
                $c = function_exists('uca_ics_sanitize_css_color') ? uca_ics_sanitize_css_color((string) $input['style_title_color']) : sanitize_text_field((string) $input['style_title_color']);
                if ($c) $out['style_title_color'] = $c;
            }
        }
        foreach (
            [
                'style_item_bg',
                'style_when_color',
                'style_badge_color',
                'style_desc_color',
                'style_location_color',
            ] as $color_key
        ) {
            if (array_key_exists($color_key, $input)) {
                $out[$color_key] = '';
                if (! empty($input[$color_key])) {
                    $c = function_exists('uca_ics_sanitize_css_color') ? uca_ics_sanitize_css_color((string) $input[$color_key]) : sanitize_text_field((string) $input[$color_key]);
                    if ($c) $out[$color_key] = $c;
                }
            }
        }
        foreach (
            [
                'style_when_weight',
                'style_when_size',
                'style_title_weight',
                'style_title_size',
                'style_badge_size',
                'style_desc_size',
                'style_location_size',
                'style_link_weight',
                'style_link_decoration',
            ] as $text_key
        ) {
            if (array_key_exists($text_key, $input)) {
                $out[$text_key] = sanitize_text_field((string) ($input[$text_key] ?? ''));
            }
        }
        if (array_key_exists('style_custom_css', $input)) {
            $out['style_custom_css'] = isset($input['style_custom_css']) ? (string) $input['style_custom_css'] : '';
        }

        // Elements visibility and order
        if (array_key_exists('elements_order', $input)) {
            $raw = (string) $input['elements_order'];
            $parts = array_filter(array_map('trim', explode(',', $raw)));
            $allowed = ['when','summary','location','desc'];
            $filtered = [];
            foreach ($parts as $p) if (in_array($p, $allowed, true) && ! in_array($p, $filtered, true)) $filtered[] = $p;
            foreach ($allowed as $p) if (! in_array($p, $filtered, true)) $filtered[] = $p; // append missing to end
            $out['elements_order'] = implode(',', $filtered);
        }
        foreach (['when','summary','location','desc','badge'] as $k) {
            $key = 'show_' . $k;
            if (array_key_exists($key, $input)) {
                $out[$key] = ! empty($input[$key]) ? 1 : 0;
            }
        }

        // Surface validation notices on settings screen
        if ($missing_count > 0) {
            add_settings_error(
                UCA_ICS_OPT,
                'uca_ics_missing_fields',
                sprintf(_n('%d feed row was skipped because Label and URL are required.', '%d feed rows were skipped because Label and URL are required.', $missing_count, 'uca-ics'), $missing_count),
                'error'
            );
        }
        if (! empty($dupe_labels)) {
            $list = implode(', ', array_values(array_unique($dupe_labels)));
            add_settings_error(
                UCA_ICS_OPT,
                'uca_ics_duplicate_labels',
                sprintf(esc_html__('Duplicate labels skipped: %s. Labels must be unique.', 'uca-ics'), esc_html($list)),
                'error'
            );
        }

        if ($feeds_changed || array_key_exists('cache_minutes', $input)) {
            (new UCA_ICS_Calendar())->refresh_cache(); // refresh only when feeds or cache window changed
        }
        return $out;
    }


    public function field_feed_url(): void
    {
        $opts = get_option(UCA_ICS_OPT, []);
        $val  = isset($opts['feed_url']) ? $opts['feed_url'] : '';
        printf(
            '<input type="url" name="%1$s[feed_url]" value="%2$s" class="regular-text code" placeholder="https://.../calendar.ics" />',
            esc_attr(UCA_ICS_OPT),
            esc_attr($val)
        );
        echo '<p class="description">' . esc_html__('Example: Outlook/Office365 .ics link or Google Calendar public .ics URL.', 'uca-ics') . '</p>';
    }

    public function field_cache_minutes(): void
    {
        $opts = get_option(UCA_ICS_OPT, []);
        $val  = isset($opts['cache_minutes']) ? (int) $opts['cache_minutes'] : 360;
        printf(
            '<input type="number" min="5" step="1" name="%1$s[cache_minutes]" value="%2$d" class="small-text" />',
            esc_attr(UCA_ICS_OPT),
            $val
        );
        echo '<p class="description">' . esc_html__('How long to cache the feed before refreshing.', 'uca-ics') . '</p>';
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) return; ?>
        <div class="wrap">
            <h1><?php esc_html_e('ICS Calendar', 'uca-ics'); ?></h1>
            <?php settings_errors(); ?>
            <?php
            $active = isset($_GET['tab']) ? sanitize_key((string) $_GET['tab']) : 'general';
            $base   = admin_url('options-general.php?page=uca-ics');
            ?>
            <h2 class="nav-tab-wrapper">
                <a href="<?php echo esc_url(add_query_arg('tab', 'general', $base)); ?>" class="nav-tab <?php echo $active === 'general' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('General Settings', 'uca-ics'); ?></a>
                <a href="<?php echo esc_url(add_query_arg('tab', 'fields', $base)); ?>" class="nav-tab <?php echo $active === 'fields' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Feed Details', 'uca-ics'); ?></a>
                <a href="<?php echo esc_url(add_query_arg('tab', 'style', $base)); ?>" class="nav-tab <?php echo $active === 'style' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Styling', 'uca-ics'); ?></a>
            </h2>

            <?php if ($active === 'general') : ?>
                <form action="options.php" method="post">
                    <?php
                    settings_fields('uca_ics_group');
                    do_settings_sections('uca-ics');
                    submit_button(__('Save Changes', 'uca-ics'));
                    ?>
                </form>
                <hr>
                <h2><?php esc_html_e('Usage', 'uca-ics'); ?></h2>
                <p><code>[ics_calendar]</code></p>
                <p><?php esc_html_e('Attributes:', 'uca-ics'); ?> <code>limit</code>, <code>showpast</code> (yes|no), <code>title</code>, <code>datefmt</code>, <code>feeds</code></p>
                <p><?php esc_html_e('Examples:', 'uca-ics'); ?></p>
                <pre>[ics_calendar title="All Events"]</pre>
                <pre>[ics_calendar feeds="General|https://.../general.ics,Music|https://.../music.ics,Cafeteria|https://.../cafe.ics" limit="30"]</pre>
                <p class="description"><?php esc_html_e('If the shortcode provides "feeds", it overrides the saved list.', 'uca-ics'); ?></p>
            <?php elseif ($active === 'fields') : ?>
                <h2><?php esc_html_e('Feed Details', 'uca-ics'); ?></h2>
                <p><?php esc_html_e('These are the ICS VEVENT fields parsed and used by the plugin:', 'uca-ics'); ?></p>
                <ul class="ul-disc">
                    <li><code>DTSTART</code> — <?php esc_html_e('Start date/time. UTC with Z is converted to site timezone. Also supports local naive datetime and date-only for all-day.', 'uca-ics'); ?></li>
                    <li><code>DTEND</code> — <?php esc_html_e('End date/time with the same formats as DTSTART.', 'uca-ics'); ?></li>
                    <li><code>SUMMARY</code> — <?php esc_html_e('Event title.', 'uca-ics'); ?></li>
                    <li><code>DESCRIPTION</code> — <?php esc_html_e('Event description text.', 'uca-ics'); ?></li>
                    <li><code>LOCATION</code> — <?php esc_html_e('Location or venue.', 'uca-ics'); ?></li>
                    <li><code>URL</code> — <?php esc_html_e('Optional link for the event. The title becomes a link if present.', 'uca-ics'); ?></li>
                    <li><code>UID</code> — <?php esc_html_e('Stable event identifier used for de-duplication (paired with DTSTART).', 'uca-ics'); ?></li>
                </ul>
                <p><?php esc_html_e('Notes:', 'uca-ics'); ?></p>
                <ul class="ul-disc">
                    <li><?php esc_html_e('Line folding is handled per RFC 5545.', 'uca-ics'); ?></li>
                    <li><?php esc_html_e('Advanced recurrence rules (RRULE/EXDATE) are not expanded in v1.', 'uca-ics'); ?></li>
                    <li><?php esc_html_e('TZID parameter parsing is not implemented; Zulu (Z) times are converted from UTC to your site timezone.', 'uca-ics'); ?></li>
                    <li><?php esc_html_e('When aggregating multiple feeds, each event displays a badge with the source label.', 'uca-ics'); ?></li>
                </ul>
            <?php else : ?>
                <?php $opts = get_option(UCA_ICS_OPT, []); ?>
                <form action="options.php" method="post" id="uca-ics-style-form">
                    <?php settings_fields('uca_ics_group'); ?>

                    <details class="general_styles" open>
                        <summary><strong><?php esc_html_e('General', 'uca-ics'); ?></strong></summary>
                        <p>
                            <label><input type="hidden" name="<?php echo esc_attr(UCA_ICS_OPT); ?>[style_compact]" value="0" />
                                <input type="checkbox" name="<?php echo esc_attr(UCA_ICS_OPT); ?>[style_compact]" value="1" <?php checked(!empty($opts['style_compact'])); ?> /> <?php esc_html_e('Compact layout', 'uca-ics'); ?></label>
                        </p>
                        <p>
                            <label><?php esc_html_e('View', 'uca-ics'); ?>:
                                <select name="<?php echo esc_attr(UCA_ICS_OPT); ?>[style_view]" id="uca-ics-style-view">
                                    <?php $v = $opts['style_view'] ?? 'list'; ?>
                                    <option value="list" <?php selected($v, 'list'); ?>><?php esc_html_e('List', 'uca-ics'); ?></option>
                                    <option value="grid" <?php selected($v, 'grid'); ?>><?php esc_html_e('Grid', 'uca-ics'); ?></option>
                                </select>
                            </label>
                        </p>
                        <p>
                            <strong><?php esc_html_e('Grid Columns', 'uca-ics'); ?></strong><br>
                            <label><?php esc_html_e('Desktop', 'uca-ics'); ?>: <input type="number" min="1" max="6" step="1" name="<?php echo esc_attr(UCA_ICS_OPT); ?>[style_cols_desktop]" value="<?php echo isset($opts['style_cols_desktop']) ? (int) $opts['style_cols_desktop'] : 1; ?>" class="small-text uca-ics-cols-desktop" /></label>
                            <label style="margin-left:12px;">&nbsp;<?php esc_html_e('Tablet', 'uca-ics'); ?>: <input type="number" min="1" max="6" step="1" name="<?php echo esc_attr(UCA_ICS_OPT); ?>[style_cols_tablet]" value="<?php echo isset($opts['style_cols_tablet']) ? (int) $opts['style_cols_tablet'] : 1; ?>" class="small-text uca-ics-cols-tablet" /></label>
                            <label style="margin-left:12px;">&nbsp;<?php esc_html_e('Mobile', 'uca-ics'); ?>: <input type="number" min="1" max="6" step="1" name="<?php echo esc_attr(UCA_ICS_OPT); ?>[style_cols_mobile]" value="<?php echo isset($opts['style_cols_mobile']) ? (int) $opts['style_cols_mobile'] : 1; ?>" class="small-text uca-ics-cols-mobile" /></label>
                        </p>
                        <p>
                            <strong><?php esc_html_e('Aspect Ratio Scale', 'uca-ics'); ?></strong><br>
                            <label><?php esc_html_e('Tablet scale', 'uca-ics'); ?>: <input type="number" step="0.05" min="0.5" max="2" name="<?php echo esc_attr(UCA_ICS_OPT); ?>[style_ar_tablet]" value="<?php echo esc_attr($opts['style_ar_tablet'] ?? '1'); ?>" class="small-text uca-ics-ar-tablet" /></label>
                            <label style="margin-left:12px;">&nbsp;<?php esc_html_e('Mobile scale', 'uca-ics'); ?>: <input type="number" step="0.05" min="0.5" max="2" name="<?php echo esc_attr(UCA_ICS_OPT); ?>[style_ar_mobile]" value="<?php echo esc_attr($opts['style_ar_mobile'] ?? '1'); ?>" class="small-text uca-ics-ar-mobile" /></label>
                            <span class="description" style="margin-left:8px;"><?php esc_html_e('Scales font sizes and spacing on smaller screens (0.5–2).', 'uca-ics'); ?></span>
                        </p>
                        <p>
                            <label><?php esc_html_e('Accent (links)', 'uca-ics'); ?>: <input type="text" class="uca-ics-color" name="<?php echo esc_attr(UCA_ICS_OPT); ?>[style_accent_color]" value="<?php echo esc_attr($opts['style_accent_color'] ?? ''); ?>" /></label>
                        </p>
                    </details>

                    <details>
                        <summary><strong><?php esc_html_e('Card', 'uca-ics'); ?></strong></summary>
                        <p><label><?php esc_html_e('Background', 'uca-ics'); ?>: <input type="text" class="uca-ics-color" name="<?php echo esc_attr(UCA_ICS_OPT); ?>[style_bg_color]" value="<?php echo esc_attr($opts['style_bg_color'] ?? ''); ?>" /></label></p>
                        <p><label><?php esc_html_e('Border', 'uca-ics'); ?>: <input type="text" class="uca-ics-color" name="<?php echo esc_attr(UCA_ICS_OPT); ?>[style_border_color]" value="<?php echo esc_attr($opts['style_border_color'] ?? ''); ?>" /></label></p>
                        <p><label><?php esc_html_e('Padding', 'uca-ics'); ?>: <input type="text" placeholder="16px" name="<?php echo esc_attr(UCA_ICS_OPT); ?>[style_card_padding]" value="<?php echo esc_attr($opts['style_card_padding'] ?? ''); ?>" /></label></p>
                        <p><label><?php esc_html_e('Margin', 'uca-ics'); ?>: <input type="text" placeholder="0" name="<?php echo esc_attr(UCA_ICS_OPT); ?>[style_card_margin]" value="<?php echo esc_attr($opts['style_card_margin'] ?? ''); ?>" /></label></p>
                        <p><label><?php esc_html_e('Title color', 'uca-ics'); ?>: <input type="text" class="uca-ics-color" name="<?php echo esc_attr(UCA_ICS_OPT); ?>[style_title_color]" value="<?php echo esc_attr($opts['style_title_color'] ?? ''); ?>" /></label></p>
                        <p><label><?php esc_html_e('Title size', 'uca-ics'); ?>: <input type="text" placeholder="1.25rem" name="<?php echo esc_attr(UCA_ICS_OPT); ?>[style_title_size]" value="<?php echo esc_attr($opts['style_title_size'] ?? ''); ?>" /></label></p>
                        <p><label><?php esc_html_e('Title weight', 'uca-ics'); ?>:
                                <select name="<?php echo esc_attr(UCA_ICS_OPT); ?>[style_title_weight]">
                                    <?php $w = $opts['style_title_weight'] ?? '';
                                    foreach (['', 'normal', '500', '600', '700', 'bold'] as $opt): ?>
                                        <option value="<?php echo esc_attr($opt); ?>" <?php selected($w, $opt); ?>><?php echo $opt === '' ? esc_html__('Default', 'uca-ics') : esc_html($opt); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label></p>
                        <p><label><?php esc_html_e('Title margin', 'uca-ics'); ?>: <input type="text" placeholder="0 0 12px" name="<?php echo esc_attr(UCA_ICS_OPT); ?>[style_title_margin]" value="<?php echo esc_attr($opts['style_title_margin'] ?? ''); ?>" /></label></p>
                        <p><label><?php esc_html_e('Title padding', 'uca-ics'); ?>: <input type="text" placeholder="0" name="<?php echo esc_attr(UCA_ICS_OPT); ?>[style_title_padding]" value="<?php echo esc_attr($opts['style_title_padding'] ?? ''); ?>" /></label></p>
                    </details>

                    <details>
                        <summary><strong><?php esc_html_e('Event Item', 'uca-ics'); ?></strong></summary>
                        <p><label><?php esc_html_e('Background', 'uca-ics'); ?>: <input type="text" class="uca-ics-color" name="<?php echo esc_attr(UCA_ICS_OPT); ?>[style_item_bg]" value="<?php echo esc_attr($opts['style_item_bg'] ?? ''); ?>" /></label></p>
                        <p><label><?php esc_html_e('Border', 'uca-ics'); ?>: <input type="text" class="uca-ics-color" name="<?php echo esc_attr(UCA_ICS_OPT); ?>[style_item_border_color]" value="<?php echo esc_attr($opts['style_item_border_color'] ?? ''); ?>" /></label></p>
                        <p><label><?php esc_html_e('Padding', 'uca-ics'); ?>: <input type="text" placeholder="12px" name="<?php echo esc_attr(UCA_ICS_OPT); ?>[style_item_padding]" value="<?php echo esc_attr($opts['style_item_padding'] ?? ''); ?>" /></label></p>
                        <p><label><?php esc_html_e('Margin', 'uca-ics'); ?>: <input type="text" placeholder="0" name="<?php echo esc_attr(UCA_ICS_OPT); ?>[style_item_margin]" value="<?php echo esc_attr($opts['style_item_margin'] ?? ''); ?>" /></label></p>
                        <p><label><?php esc_html_e('Event spacing (gap)', 'uca-ics'); ?>: <input type="text" placeholder="12px" name="<?php echo esc_attr(UCA_ICS_OPT); ?>[style_list_gap]" value="<?php echo esc_attr($opts['style_list_gap'] ?? ''); ?>" /></label></p>
                    </details>

                    <details>
                        <summary><strong><?php esc_html_e('Date/Time', 'uca-ics'); ?></strong></summary>
                        <p><label><?php esc_html_e('Color', 'uca-ics'); ?>: <input type="text" class="uca-ics-color" name="<?php echo esc_attr(UCA_ICS_OPT); ?>[style_when_color]" value="<?php echo esc_attr($opts['style_when_color'] ?? ''); ?>" /></label></p>
                        <p><label><?php esc_html_e('Font size', 'uca-ics'); ?>: <input type="text" placeholder="inherit" name="<?php echo esc_attr(UCA_ICS_OPT); ?>[style_when_size]" value="<?php echo esc_attr($opts['style_when_size'] ?? ''); ?>" /></label></p>
                        <p><label><?php esc_html_e('Font weight', 'uca-ics'); ?>:
                                <select name="<?php echo esc_attr(UCA_ICS_OPT); ?>[style_when_weight]">
                                    <?php $w = $opts['style_when_weight'] ?? '';
                                    foreach (['', 'normal', '500', '600', '700', 'bold'] as $opt): ?>
                                        <option value="<?php echo esc_attr($opt); ?>" <?php selected($w, $opt); ?>><?php echo $opt === '' ? esc_html__('Default', 'uca-ics') : esc_html($opt); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label></p>
                        <p><label><?php esc_html_e('Margin', 'uca-ics'); ?>: <input type="text" placeholder="0 0 4px" name="<?php echo esc_attr(UCA_ICS_OPT); ?>[style_when_margin]" value="<?php echo esc_attr($opts['style_when_margin'] ?? ''); ?>" /></label></p>
                        <p><label><?php esc_html_e('Padding', 'uca-ics'); ?>: <input type="text" placeholder="0" name="<?php echo esc_attr(UCA_ICS_OPT); ?>[style_when_padding]" value="<?php echo esc_attr($opts['style_when_padding'] ?? ''); ?>" /></label></p>
                    </details>

                    <details>
                        <summary><strong><?php esc_html_e('Summary Link', 'uca-ics'); ?></strong></summary>
                        <p><label><?php esc_html_e('Link weight', 'uca-ics'); ?>:
                                <select name="<?php echo esc_attr(UCA_ICS_OPT); ?>[style_link_weight]">
                                    <?php $w = $opts['style_link_weight'] ?? '';
                                    foreach (['', 'normal', '500', '600', '700', 'bold'] as $opt): ?>
                                        <option value="<?php echo esc_attr($opt); ?>" <?php selected($w, $opt); ?>><?php echo $opt === '' ? esc_html__('Default', 'uca-ics') : esc_html($opt); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label></p>
                        <p><label><?php esc_html_e('Link decoration', 'uca-ics'); ?>:
                                <select name="<?php echo esc_attr(UCA_ICS_OPT); ?>[style_link_decoration]">
                                    <?php $d = $opts['style_link_decoration'] ?? '';
                                    foreach (['', 'none', 'underline'] as $opt): ?>
                                        <option value="<?php echo esc_attr($opt); ?>" <?php selected($d, $opt); ?>><?php echo $opt === '' ? esc_html__('Default', 'uca-ics') : esc_html($opt); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label></p>
                    </details>

                    <details>
                        <summary><strong><?php esc_html_e('Elements', 'uca-ics'); ?></strong></summary>
                        <?php
                        $order_csv = isset($opts['elements_order']) ? (string) $opts['elements_order'] : 'when,summary,location,desc';
                        $order = array_values(array_filter(array_map('trim', explode(',', $order_csv))));
                        $allowed = ['when','summary','location','desc'];
                        // ensure all allowed are present at least once
                        foreach ($allowed as $k) if (! in_array($k, $order, true)) $order[] = $k;
                        $labels = [
                            'when'     => __('Date/Time', 'uca-ics'),
                            'summary'  => __('Summary', 'uca-ics'),
                            'location' => __('Location', 'uca-ics'),
                            'desc'     => __('Description', 'uca-ics'),
                        ];
                        ?>
                        <input type="hidden" name="<?php echo esc_attr(UCA_ICS_OPT); ?>[elements_order]" id="uca-ics-elements-order" value="<?php echo esc_attr(implode(',', $order)); ?>" />
                        <ul id="uca-ics-elements-list" class="uca-ics-elements">
                            <?php foreach ($order as $key): if (! isset($labels[$key])) continue; ?>
                                <li class="uca-ics-el" data-key="<?php echo esc_attr($key); ?>">
                                    <span class="uca-ics-el-handle" aria-hidden="true">⋮⋮</span>
                                    <label>
                                        <input type="checkbox" name="<?php echo esc_attr(UCA_ICS_OPT); ?>[show_<?php echo esc_attr($key); ?>]" value="1" <?php checked(! empty($opts['show_' . $key])); ?> />
                                        <?php echo esc_html($labels[$key]); ?>
                                    </label>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <p>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(UCA_ICS_OPT); ?>[show_badge]" value="1" <?php checked(! empty($opts['show_badge'])); ?> />
                                <?php esc_html_e('Show source label badge (for multi-feed)', 'uca-ics'); ?>
                            </label>
                        </p>
                        <p class="description"><?php esc_html_e('Drag to reorder elements. Uncheck to hide an element.', 'uca-ics'); ?></p>
                    </details>

                    <details>
                        <summary><strong><?php esc_html_e('Badge', 'uca-ics'); ?></strong></summary>
                        <p><label><?php esc_html_e('Background', 'uca-ics'); ?>: <input type="text" class="uca-ics-color" name="<?php echo esc_attr(UCA_ICS_OPT); ?>[style_badge_bg]" value="<?php echo esc_attr($opts['style_badge_bg'] ?? ''); ?>" /></label></p>
                        <p><label><?php esc_html_e('Border', 'uca-ics'); ?>: <input type="text" class="uca-ics-color" name="<?php echo esc_attr(UCA_ICS_OPT); ?>[style_badge_border]" value="<?php echo esc_attr($opts['style_badge_border'] ?? ''); ?>" /></label></p>
                        <p><label><?php esc_html_e('Text color', 'uca-ics'); ?>: <input type="text" class="uca-ics-color" name="<?php echo esc_attr(UCA_ICS_OPT); ?>[style_badge_color]" value="<?php echo esc_attr($opts['style_badge_color'] ?? ''); ?>" /></label></p>
                        <p><label><?php esc_html_e('Font size', 'uca-ics'); ?>: <input type="text" placeholder="0.75rem" name="<?php echo esc_attr(UCA_ICS_OPT); ?>[style_badge_size]" value="<?php echo esc_attr($opts['style_badge_size'] ?? ''); ?>" /></label></p>
                        <p><label><?php esc_html_e('Padding', 'uca-ics'); ?>: <input type="text" placeholder="2px 8px" name="<?php echo esc_attr(UCA_ICS_OPT); ?>[style_badge_padding]" value="<?php echo esc_attr($opts['style_badge_padding'] ?? ''); ?>" /></label></p>
                        <p><label><?php esc_html_e('Margin', 'uca-ics'); ?>: <input type="text" placeholder="0 0 0 0.5rem" name="<?php echo esc_attr(UCA_ICS_OPT); ?>[style_badge_margin]" value="<?php echo esc_attr($opts['style_badge_margin'] ?? ''); ?>" /></label></p>
                    </details>

                    <details>
                        <summary><strong><?php esc_html_e('Description', 'uca-ics'); ?></strong></summary>
                        <p><label><?php esc_html_e('Color', 'uca-ics'); ?>: <input type="text" class="uca-ics-color" name="<?php echo esc_attr(UCA_ICS_OPT); ?>[style_desc_color]" value="<?php echo esc_attr($opts['style_desc_color'] ?? ''); ?>" /></label></p>
                        <p><label><?php esc_html_e('Font size', 'uca-ics'); ?>: <input type="text" placeholder="0.92rem" name="<?php echo esc_attr(UCA_ICS_OPT); ?>[style_desc_size]" value="<?php echo esc_attr($opts['style_desc_size'] ?? ''); ?>" /></label></p>
                        <p><label><?php esc_html_e('Margin', 'uca-ics'); ?>: <input type="text" placeholder="6px 0 0" name="<?php echo esc_attr(UCA_ICS_OPT); ?>[style_desc_margin]" value="<?php echo esc_attr($opts['style_desc_margin'] ?? ''); ?>" /></label></p>
                        <p><label><?php esc_html_e('Padding', 'uca-ics'); ?>: <input type="text" placeholder="0" name="<?php echo esc_attr(UCA_ICS_OPT); ?>[style_desc_padding]" value="<?php echo esc_attr($opts['style_desc_padding'] ?? ''); ?>" /></label></p>
                    </details>

                    <details>
                        <summary><strong><?php esc_html_e('Location', 'uca-ics'); ?></strong></summary>
                        <p><label><?php esc_html_e('Color', 'uca-ics'); ?>: <input type="text" class="uca-ics-color" name="<?php echo esc_attr(UCA_ICS_OPT); ?>[style_location_color]" value="<?php echo esc_attr($opts['style_location_color'] ?? ''); ?>" /></label></p>
                        <p><label><?php esc_html_e('Font size', 'uca-ics'); ?>: <input type="text" placeholder="0.95rem" name="<?php echo esc_attr(UCA_ICS_OPT); ?>[style_location_size]" value="<?php echo esc_attr($opts['style_location_size'] ?? ''); ?>" /></label></p>
                        <p><label><?php esc_html_e('Margin', 'uca-ics'); ?>: <input type="text" placeholder="2px 0 0" name="<?php echo esc_attr(UCA_ICS_OPT); ?>[style_location_margin]" value="<?php echo esc_attr($opts['style_location_margin'] ?? ''); ?>" /></label></p>
                        <p><label><?php esc_html_e('Padding', 'uca-ics'); ?>: <input type="text" placeholder="0" name="<?php echo esc_attr(UCA_ICS_OPT); ?>[style_location_padding]" value="<?php echo esc_attr($opts['style_location_padding'] ?? ''); ?>" /></label></p>
                    </details>

                    <?php submit_button(__('Save Changes', 'uca-ics')); ?>
                </form>
                <hr>
                <h2><?php esc_html_e('Preview', 'uca-ics'); ?></h2>
                <div id="uca-ics-style-preview-wrapper">
                    <div class="uca-ics-calendar uca-ics--multi<?php echo ! empty($opts['style_compact']) ? ' uca-ics--compact' : ''; ?>" id="uca-ics-style-preview">
                        <h3 class="uca-ics-title"><?php esc_html_e('Upcoming Events', 'uca-ics'); ?></h3>
                        <ul class="uca-ics-list">
                            <li class="uca-ics-item">
                                <div class="uca-ics-when"><time class="uca-ics-start">Jan 12, 2025 10:00 am</time> <span class="uca-ics-sep"> – </span> <time class="uca-ics-end">Jan 12, 2025 11:00 am</time></div>
                                <div class="uca-ics-main">
                                    <div class="uca-ics-summary"><a href="#">Sample Event Title</a> <span class="uca-ics-badge">Music</span></div>
                                    <div class="uca-ics-location">Main Hall</div>
                                    <div class="uca-ics-desc">Short event description goes here.</div>
                                </div>
                            </li>
                            <li class="uca-ics-item">
                                <div class="uca-ics-when"><time class="uca-ics-start">Jan 15, 2025 9:00 am</time></div>
                                <div class="uca-ics-main">
                                    <div class="uca-ics-summary">Another Event <span class="uca-ics-badge">General</span></div>
                                    <div class="uca-ics-location">Room 201</div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
        </div>
<?php
    }

    public function field_style_compact(): void
    {
        $opts = get_option(UCA_ICS_OPT, []);
        $val  = ! empty($opts['style_compact']);
        printf(
            '<input type="hidden" name="%1$s[style_compact]" value="0" />'
                . '<label><input type="checkbox" name="%1$s[style_compact]" value="1" %2$s /> %3$s</label>',
            esc_attr(UCA_ICS_OPT),
            checked(true, $val, false),
            esc_html__('Reduce spacing and tighten layout.', 'uca-ics')
        );
    }

    public function field_style_accent_color(): void
    {
        $opts = get_option(UCA_ICS_OPT, []);
        $val  = isset($opts['style_accent_color']) ? (string) $opts['style_accent_color'] : '';
        printf(
            '<input type="color" name="%1$s[style_accent_color]" value="%2$s" /> <span class="description">%3$s</span>',
            esc_attr(UCA_ICS_OPT),
            esc_attr($val),
            esc_html__('Used for links and accents.', 'uca-ics')
        );
    }

    public function field_style_badge_bg(): void
    {
        $opts = get_option(UCA_ICS_OPT, []);
        $val  = isset($opts['style_badge_bg']) ? (string) $opts['style_badge_bg'] : '';
        printf(
            '<input type="color" name="%1$s[style_badge_bg]" value="%2$s" /> <span class="description">%3$s</span>',
            esc_attr(UCA_ICS_OPT),
            esc_attr($val),
            esc_html__('Background color for the source badge.', 'uca-ics')
        );
    }

    public function field_style_custom_css(): void
    {
        $opts = get_option(UCA_ICS_OPT, []);
        $val  = isset($opts['style_custom_css']) ? (string) $opts['style_custom_css'] : '';
        printf(
            '<textarea name="%1$s[style_custom_css]" rows="6" class="large-text code" placeholder="/* Additional CSS applied to the calendar markup */">%2$s</textarea>',
            esc_attr(UCA_ICS_OPT),
            esc_textarea($val)
        );
    }

    public function field_style_badge_border(): void
    {
        $opts = get_option(UCA_ICS_OPT, []);
        $val  = isset($opts['style_badge_border']) ? (string) $opts['style_badge_border'] : '';
        printf(
            '<input type="color" name="%1$s[style_badge_border]" value="%2$s" /> <span class="description">%3$s</span>',
            esc_attr(UCA_ICS_OPT),
            esc_attr($val),
            esc_html__('Border color for the source badge.', 'uca-ics')
        );
    }

    public function field_style_border_color(): void
    {
        $opts = get_option(UCA_ICS_OPT, []);
        $val  = isset($opts['style_border_color']) ? (string) $opts['style_border_color'] : '';
        printf(
            '<input type="color" name="%1$s[style_border_color]" value="%2$s" /> <span class="description">%3$s</span>',
            esc_attr(UCA_ICS_OPT),
            esc_attr($val),
            esc_html__('Border color of the outer card.', 'uca-ics')
        );
    }

    public function field_style_bg_color(): void
    {
        $opts = get_option(UCA_ICS_OPT, []);
        $val  = isset($opts['style_bg_color']) ? (string) $opts['style_bg_color'] : '';
        printf(
            '<input type="color" name="%1$s[style_bg_color]" value="%2$s" /> <span class="description">%3$s</span>',
            esc_attr(UCA_ICS_OPT),
            esc_attr($val),
            esc_html__('Background color of the outer card.', 'uca-ics')
        );
    }

    public function field_style_item_border_color(): void
    {
        $opts = get_option(UCA_ICS_OPT, []);
        $val  = isset($opts['style_item_border_color']) ? (string) $opts['style_item_border_color'] : '';
        printf(
            '<input type="color" name="%1$s[style_item_border_color]" value="%2$s" /> <span class="description">%3$s</span>',
            esc_attr(UCA_ICS_OPT),
            esc_attr($val),
            esc_html__('Border color of each event item.', 'uca-ics')
        );
    }

    public function field_style_title_color(): void
    {
        $opts = get_option(UCA_ICS_OPT, []);
        $val  = isset($opts['style_title_color']) ? (string) $opts['style_title_color'] : '';
        printf(
            '<input type="color" name="%1$s[style_title_color]" value="%2$s" /> <span class="description">%3$s</span>',
            esc_attr(UCA_ICS_OPT),
            esc_attr($val),
            esc_html__('Color of the calendar title heading.', 'uca-ics')
        );
    }

    public function enqueue($hook): void
    {
        // Only load on our settings page
        if ($hook !== 'settings_page_uca-ics') return;
        wp_enqueue_script(
            'uca-ics-admin',
            UCA_ICS_URL . 'assets/js/admin.js',
            ['jquery', 'wp-color-picker', 'jquery-ui-sortable'],
            UCA_ICS_VER,
            true
        );
        wp_enqueue_style(
            'uca-ics-admin',
            UCA_ICS_URL . 'assets/css/admin.css',
            [],
            UCA_ICS_VER,
            'all'
        );
        wp_enqueue_style('wp-color-picker');
        // Load frontend styles so the Styling preview renders correctly
        wp_enqueue_style(
            'uca-ics-frontend',
            UCA_ICS_URL . 'assets/css/frontend.css',
            [],
            UCA_ICS_VER
        );
        // Apply saved styling as inline CSS for initial preview state
        $opts = get_option(UCA_ICS_OPT, []);
        $inline = function_exists('uca_ics_style_inline_css') ? uca_ics_style_inline_css($opts) : '';
        if ($inline) {
            wp_add_inline_style('uca-ics-frontend', $inline);
        }
    }
}
