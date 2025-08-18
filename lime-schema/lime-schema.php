<?php

/**
 * Plugin Name: Lime Schema Boilerplate
 * Description: Admin UI + per-page controls to output clean JSON-LD that hides empty fields and follows best practices (@graph with stable @id).
 * Version: 1.0.0
 * Author: Lime
 */

if (!defined('ABSPATH')) exit;

final class Lime_Schema_Boilerplate
{
    const OPTION_KEY = 'lime_schema_options';
    const NONCE_KEY  = 'lime_schema_nonce';
    const META_KEY   = '_lime_schema_meta';

    public function __construct()
    {
        add_action('admin_init',        [$this, 'register_settings']);
        add_action('admin_menu',        [$this, 'add_settings_page']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('add_meta_boxes',    [$this, 'add_meta_box']);
        add_action('save_post',         [$this, 'save_post_meta'], 10, 2);
        add_action('wp_head',           [$this, 'output_schema'], 30);
    }

    public function admin_assets($hook)
    {
        if ($hook !== 'settings_page_' . self::OPTION_KEY) return;

        // Minimal styles for tabs & repeater
        $css = '
        .ls-tabs{margin-top:12px}
        .ls-tab-nav{display:flex;gap:8px;border-bottom:1px solid #ddd;margin-bottom:12px}
        .ls-tab-nav a{padding:8px 12px;border:1px solid #ddd;border-bottom:none;background:#f6f7f7;text-decoration:none;border-radius:4px 4px 0 0;color:#1d2327}
        .ls-tab-nav a.active{background:#fff;font-weight:600}
        .ls-tab{display:none;background:#fff;border:1px solid #ddd;border-radius:0 4px 4px 4px;padding:16px}
        .ls-tab.active{display:block}
        .ls-loc{border:1px solid #e2e4e7;padding:12px;border-radius:6px;margin-bottom:12px;background:#fafafa}
        .ls-loc h4{margin-top:0}
        .ls-row{display:flex;gap:8px}
        .ls-row > div{flex:1; margin-bottom: 14px;}
        .ls-row label { width: 100%; display: block; }
        .ls-row label input[type="text"], .ls-row label input[type="email"], .ls-row label input[type="url"], .ls-row label textarea { width: 100%; }
        .ls-actions{margin-top:8px}
        .button-link-delete{color:#b32d2e}
        ';
        wp_register_style('lime-schema-admin', false);
        wp_add_inline_style('lime-schema-admin', $css);
        wp_enqueue_style('lime-schema-admin');

        // Vanilla JS tabs + repeater
        $js = '
        document.addEventListener("DOMContentLoaded",function(){
            // Tabs
            document.querySelectorAll(".ls-tab-nav a").forEach(function(btn){
                btn.addEventListener("click",function(e){
                e.preventDefault();
                var target=this.getAttribute("data-target");
                document.querySelectorAll(".ls-tab-nav a").forEach(b=>b.classList.remove("active"));
                document.querySelectorAll(".ls-tab").forEach(t=>t.classList.remove("active"));
                this.classList.add("active");
                document.querySelector(target).classList.add("active");
                history.replaceState(null,"", "#"+target.replace("#",""));
                });
            });
            // Deep link
            if(location.hash && document.querySelector(location.hash)){
                document.querySelector(".ls-tab-nav a.active")?.classList.remove("active");
                document.querySelector(".ls-tab.active")?.classList.remove("active");
                document.querySelector(\'.ls-tab-nav a[data-target="\'+location.hash+\'"]\')?.classList.add("active");
                document.querySelector(location.hash)?.classList.add("active");
            }

            // Repeater
            const wrap=document.getElementById("ls-locations-wrap");
            if(!wrap) return;
            const addBtn=document.getElementById("ls-add-location");
            const proto=document.getElementById("ls-location-proto").content;

            // ---- Auto-fill Geo from Google Maps URL in "hasMap" ----
            function parseLatLngFromUrl(u) {
                try {
                    const url = new URL(u);

                    // 1) /@LAT,LNG,zoom   e.g. .../place/.../@43.642566,-79.387057,17z/
                    const atMatch = url.pathname.match(/@(-?\d+(\.\d+)?),(-?\d+(\.\d+)?)/);
                    if (atMatch) return { lat: parseFloat(atMatch[1]), lng: parseFloat(atMatch[3]) };

                    // 2) !3dLAT!4dLNG   e.g. ...!3d43.642566!4d-79.387057
                    const bangMatch = url.href.match(/!3d(-?\d+(\.\d+)?)!4d(-?\d+(\.\d+)?)/);
                    if (bangMatch) return { lat: parseFloat(bangMatch[1]), lng: parseFloat(bangMatch[3]) };

                    // 3) q=LAT,LNG   e.g. ...?q=43.642566,-79.387057
                    const q = url.searchParams.get("q");
                    if (q && q.match(/-?\d+(\.\d+)?\s*,\s*-?\d+(\.\d+)?/)) {
                    const [lat, lng] = q.split(",").map(s => parseFloat(s.trim()));
                    return { lat, lng };
                    }

                    // 4) ll=LAT,LNG (older pattern)
                    const ll = url.searchParams.get("ll");
                    if (ll && ll.match(/-?\d+(\.\d+)?\s*,\s*-?\d+(\.\d+)?/)) {
                    const [lat, lng] = ll.split(",").map(s => parseFloat(s.trim()));
                    return { lat, lng };
                    }
                } catch (e) {}
                return null;
                }

                // delegate on paste/blur/change of any hasMap input
                document.addEventListener("input", handleHasMapEvent, true);
                document.addEventListener("change", handleHasMapEvent, true);
                document.addEventListener("blur", handleHasMapEvent, true);

                function handleHasMapEvent(e) {
                const el = e.target;
                if (!el.matches(`input[data-name="hasMap"]`)) return;

                const val = (el.value || "").trim();
                if (!val) return;

                const coords = parseLatLngFromUrl(val);
                if (!coords) return;

                // find sibling Geo inputs inside the same .ls-loc block
                const block = el.closest(".ls-loc");
                if (!block) return;
                const latInput = block.querySelector(`input[data-name="geo[latitude]"]`);
                const lngInput = block.querySelector(`input[data-name="geo[longitude]"]`);
                if (latInput) latInput.value = coords.lat;
                if (lngInput) lngInput.value = coords.lng;
            }


            function renumber(){
                function bracketize(key){
                    if (key.includes("[")) {
                    return key
                        .replace(/\]/g, "")
                        .split("[")
                        .map(s => s.trim())
                        .filter(Boolean)
                        .map(s => `[${s}]`)
                        .join("");
                    }
                    return `[${key}]`;
                }

                wrap.querySelectorAll(".ls-loc").forEach(function(block,i){
                    block.querySelectorAll("[data-name]").forEach(function(input){
                    const key = input.getAttribute("data-name");
                    input.name = "' . self::OPTION_KEY . '[locations][" + i + "]" + bracketize(key);
                    });
                });
            }



            addBtn.addEventListener("click",function(e){
                e.preventDefault();
                const node=document.importNode(proto,true);
                wrap.appendChild(node);
                renumber();
            });

            wrap.addEventListener("click",function(e){
                if(e.target.classList.contains("ls-remove")){
                e.preventDefault();
                const blk=e.target.closest(".ls-loc");
                blk.parentNode.removeChild(blk);
                renumber();
                }
            });

            // initial renumber in case of server-rendered entries
            renumber();
        });
        ';
        wp_register_script('lime-schema-admin', false);
        wp_add_inline_script('lime-schema-admin', $js);
        wp_enqueue_script('lime-schema-admin');
    }

    /* ---------------------------
     * Settings (Organization-level)
     * --------------------------- */
    public function register_settings()
    {
        register_setting(self::OPTION_KEY, self::OPTION_KEY, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitize_options'],
            'default'           => [
                'org_name'       => '',
                'site_url'       => '',
                'description'    => '',
                'logo'           => '',
                'images'         => '',
                'founding_date'  => '',
                'founder'        => '',
                'phone'          => '',
                'email'          => '',
                'languages'      => 'en-CA',
                'sameas'         => '',
                // Website/SearchAction
                'enable_website' => 1,
                'search_target'  => '',
                // Default LocalBusiness subtype
                'lb_type'        => 'LocalBusiness',
                // NEW: array of locations (repeater)
                'locations'      => [],
                // OLD (back-compat): JSON textarea (will be migrated automatically)
                'locations_json' => '[]',
            ]
        ]);

        add_settings_section('lime_schema_tabs', '', '__return_false', self::OPTION_KEY);

        // We'll render all fields ourselves inside the tabbed UI in render_settings_page().
    }


    public function render_field($args)
    {
        $opts = get_option(self::OPTION_KEY, []);
        $key  = esc_attr($args['key']);
        $type = $args['type'];
        $val  = isset($opts[$key]) ? $opts[$key] : '';

        if ($type === 'checkbox') {
            echo '<label><input type="checkbox" name="' . self::OPTION_KEY . '[' . $key . ']" value="1" ' . checked($val, 1, false) . '> Enable</label>';
            return;
        }

        if ($type === 'textarea') {
            echo '<textarea class="large-text" rows="3" name="' . self::OPTION_KEY . '[' . $key . ']">' . esc_textarea($val) . '</textarea>';
            return;
        }

        $input_type = in_array($type, ['text', 'url', 'email']) ? $type : 'text';
        echo '<input type="' . $input_type . '" class="regular-text" name="' . self::OPTION_KEY . '[' . $key . ']" value="' . esc_attr($val) . '">';
    }

    public function sanitize_options($raw)
    {
        $out = [];
        // simple scalars
        foreach (['org_name', 'site_url', 'description', 'logo', 'images', 'founding_date', 'founder', 'phone', 'email', 'languages', 'sameas', 'search_target', 'lb_type', 'locations_json'] as $k) {
            $out[$k] = isset($raw[$k]) ? wp_kses_post($raw[$k]) : '';
        }
        $out['enable_website'] = !empty($raw['enable_website']) ? 1 : 0;

        $allowed = $this->allowed_lb_types();
        $out['lb_type'] = in_array($out['lb_type'], $allowed, true) ? $out['lb_type'] : 'LocalBusiness';


        // locations repeater
        $out['locations'] = [];
        if (!empty($raw['locations']) && is_array($raw['locations'])) {
            foreach ($raw['locations'] as $loc) {
                $L = [];
                $L['name']  = isset($loc['name']) ? sanitize_text_field($loc['name']) : '';
                $L['slug']  = isset($loc['slug']) ? sanitize_title($loc['slug']) : '';
                if (!$L['slug'] || strpos($L['slug'], 'http') === 0) {
                    $L['slug'] = sanitize_title($L['name'] ?: 'location');
                }
                $L['phone'] = isset($loc['phone']) ? sanitize_text_field($loc['phone']) : '';
                $L['email'] = isset($loc['email']) ? sanitize_email($loc['email']) : '';
                $L['priceRange'] = isset($loc['priceRange']) ? sanitize_text_field($loc['priceRange']) : '';

                // CSV helpers
                $img_csv   = isset($loc['image_csv']) ? sanitize_text_field($loc['image_csv']) : '';
                $areas_csv = isset($loc['areas_csv']) ? sanitize_text_field($loc['areas_csv']) : '';
                $L['image'] = $img_csv ? array_values(array_filter(array_map('trim', explode(',', $img_csv)))) : [];
                $L['areaServed'] = $areas_csv ? array_values(array_filter(array_map('trim', explode(',', $areas_csv)))) : [];

                // address
                $addr = isset($loc['address']) && is_array($loc['address']) ? $loc['address'] : [];
                $L['address'] = [
                    'streetAddress'   => isset($addr['streetAddress']) ? sanitize_text_field($addr['streetAddress']) : '',
                    'addressLocality' => isset($addr['addressLocality']) ? sanitize_text_field($addr['addressLocality']) : '',
                    'addressRegion'   => isset($addr['addressRegion']) ? sanitize_text_field($addr['addressRegion']) : '',
                    'postalCode'      => isset($addr['postalCode']) ? sanitize_text_field($addr['postalCode']) : '',
                    'addressCountry'  => isset($addr['addressCountry']) ? sanitize_text_field($addr['addressCountry']) : ''
                ];

                // geo
                $geo = isset($loc['geo']) && is_array($loc['geo']) ? $loc['geo'] : [];
                $lat = isset($geo['latitude'])  ? floatval($geo['latitude'])  : null;
                $lng = isset($geo['longitude']) ? floatval($geo['longitude']) : null;
                $L['geo'] = [];
                if ($lat !== null) $L['geo']['latitude'] = $lat;
                if ($lng !== null) $L['geo']['longitude'] = $lng;

                $L['hasMap'] = isset($loc['hasMap']) ? esc_url_raw($loc['hasMap']) : '';

                // services (one per line)
                $services_text = isset($loc['services_text']) ? trim(wp_kses_post($loc['services_text'])) : '';
                if ($services_text !== '') {
                    $L['services'] = [];
                    foreach (preg_split('/\r\n|\r|\n/', $services_text) as $line) {
                        $name = trim($line);
                        if ($name !== '') $L['services'][] = ['serviceType' => $name];
                    }
                }

                // Clean empty bits
                $L = $this->clean($L);
                if ($L) $out['locations'][] = $L;
            }
        }

        // Legacy JSON validation (kept for migration/back-compat)
        $decoded = json_decode($out['locations_json'], true);
        if (json_last_error() !== JSON_ERROR_NONE) $out['locations_json'] = '[]';

        return $out;
    }


    public function add_settings_page()
    {
        add_options_page('Lime Schema', 'Lime Schema', 'manage_options', self::OPTION_KEY, [$this, 'render_settings_page']);
    }

    public function render_settings_page()
    {
        $opts = get_option(self::OPTION_KEY, []);
        $opts = is_array($opts) ? $opts : [];

        // Back-compat: migrate JSON -> array one time if locations empty
        if (empty($opts['locations']) && !empty($opts['locations_json'])) {
            $tmp = json_decode($opts['locations_json'], true);
            if (is_array($tmp)) $opts['locations'] = $tmp;
        }

        echo '<div class="wrap"><h1>Lime Schema</h1><form method="post" action="options.php">';
        settings_fields(self::OPTION_KEY);

        echo '<div class="ls-tabs">';
        // TAB NAV
        echo '<div class="ls-tab-nav">
              <a href="#" class="active" data-target="#ls-org">Organization</a>
              <a href="#" data-target="#ls-website">Website</a>
              <a href="#" data-target="#ls-locations">Locations</a>
            </div>';

        // ORG TAB
        echo '<div id="ls-org" class="ls-tab active">';
        $this->field_text('org_name', 'Business Legal Name', $opts);
        $this->field_url('site_url', 'Site URL (canonical)', $opts);
        $this->field_textarea('description', 'Short Description (1–2 sentences)', $opts);
        $this->field_url('logo', 'Logo URL (PNG/SVG)', $opts);
        $this->field_textarea('images', 'Brand Image URLs (comma-separated)', $opts);
        $this->field_text('founding_date', 'Founding Date (YYYY-MM-DD)', $opts);
        $this->field_text('founder', 'Founder Name', $opts);
        $this->field_text('phone', 'Primary Phone (+1-xxx-xxx-xxxx)', $opts);
        $this->field_email('email', 'Contact Email', $opts);
        $this->field_text('languages', 'Languages (comma-separated, e.g., en-CA, fr-CA)', $opts);
        $this->field_textarea('sameas', 'Official Profiles (comma-separated URLs)', $opts);
        $this->field_lb_select(
            'lb_type',
            'Default LocalBusiness subtype',
            $opts,
            'We use a vetted list of schema.org LocalBusiness subtypes to prevent typos and invalid values. Pick the closest match; search engines ignore unknown types. 
            <br/> 
            Tip: Choose the most specific category that describes your business. If none fit, select "ProfessionalService" for service providers or "LocalBusiness" for a general category.'
        );
        echo '</div>';

        // WEBSITE TAB
        echo '<div id="ls-website" class="ls-tab">';
        $this->field_checkbox('enable_website', 'Output WebSite/SearchAction?', $opts);
        $this->field_url('search_target', 'Search URL pattern (e.g., https://example.com/?s={search_term_string})', $opts);
        echo '</div>';

        // LOCATIONS TAB (REPEATER)
        echo '<div id="ls-locations" class="ls-tab">';
        echo '<p>Add one block per physical location. Leave fields blank if unknown—empties are removed automatically.</p>';
        echo '<div id="ls-locations-wrap">';
        $locs = isset($opts['locations']) && is_array($opts['locations']) ? $opts['locations'] : [];
        foreach ($locs as $loc) {
            $this->render_location_block($loc);
        }
        echo '</div>';
        echo '<p class="ls-actions"><a href="#" id="ls-add-location" class="button">+ Add Location</a></p>';

        // PROTOTYPE (hidden template for cloning)
        echo '<template id="ls-location-proto">';
        $this->render_location_block([], true);
        echo '</template>';

        // (Hidden) legacy field retained for back-compat; you can remove after migration.
        echo '<input type="hidden" name="' . self::OPTION_KEY . '[locations_json]" value="' . esc_attr($this->v($opts, 'locations_json', '[]')) . '">';
        echo '</div>';

        echo '</div>'; // .ls-tabs

        submit_button();
        echo '</form></div>';
    }


    /* ---------------------------
     * Per-page Meta Box (overrides & node selection)
     * --------------------------- */
    public function add_meta_box()
    {
        $screens = get_post_types(['public' => true], 'names');
        foreach ($screens as $pt) {
            add_meta_box('lime_schema_box', 'Lime Schema (Page-specific)', [$this, 'render_meta_box'], $pt, 'side', 'default');
        }
    }

    public function render_meta_box($post)
    {
        wp_nonce_field(self::NONCE_KEY, self::NONCE_KEY);
        $meta = get_post_meta($post->ID, self::META_KEY, true);
        $meta = is_array($meta) ? $meta : [];

        $fields = [
            'include_org'      => ['Include Organization node on this page?', 'checkbox'],
            'include_loc'      => ['Include LocalBusiness node(s)?', 'checkbox'],
            'include_webpage'  => ['Include WebPage node?', 'checkbox'],
            'include_website'  => ['Include WebSite node?', 'checkbox'],
            'override_lb_type' => ['LocalBusiness subtype override', 'select'], // ← note type
            'override_name'    => ['Override Name (this page)', 'text'],
            'override_desc'    => ['Override Description (this page)', 'textarea'],
            'override_image'   => ['Primary Image URL (this page)', 'url'],
        ];

        foreach ($fields as $key => $info) {
            $val = isset($meta[$key]) ? $meta[$key] : '';
            echo '<p style="margin:8px 0">';
            echo '<label><strong>' . esc_html($info[0]) . '</strong></label><br>';

            if ($info[1] === 'checkbox') {
                echo '<label><input type="checkbox" name="' . self::META_KEY . '[' . $key . ']" value="1" ' . checked($val, 1, false) . '> Yes</label>';
            } elseif ($info[1] === 'textarea') {
                echo '<textarea class="widefat" rows="2" name="' . self::META_KEY . '[' . $key . ']">' . esc_textarea($val) . '</textarea>';
            } elseif ($key === 'override_lb_type') {
                // 🔽 Use the dropdown instead of a text input
                $this->meta_lb_select('override_lb_type', $val);
                echo '<br><span class="description">Why this matters: schema.org types must be valid. '
                    . 'Choosing from this list prevents typos and ensures your LocalBusiness node is eligible for rich results.</span>';
            } else {
                echo '<input type="text" class="widefat" name="' . self::META_KEY . '[' . $key . ']" value="' . esc_attr($val) . '">';
            }

            echo '</p>';
        }

        echo '<p><em>Tip:</em> On the homepage, include Organization + WebSite + WebPage. '
            . 'On other pages, WebPage plus (optionally) LocalBusiness is typical.</p>';
    }


    public function save_post_meta($post_id, $post)
    {
        if (!isset($_POST[self::NONCE_KEY]) || !wp_verify_nonce($_POST[self::NONCE_KEY], self::NONCE_KEY)) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $raw = isset($_POST[self::META_KEY]) ? (array)$_POST[self::META_KEY] : [];
        $clean = [];
        foreach ($raw as $k => $v) {
            $clean[$k] = is_string($v) ? sanitize_text_field($v) : (int)!empty($v);
        }
        update_post_meta($post_id, self::META_KEY, $clean);
    }

    /* ---------------------------
     * Output JSON-LD in <head>
     * --------------------------- */
    public function output_schema()
    {
        if (is_admin() || is_feed() || is_404()) return;

        $opts = get_option(self::OPTION_KEY, []);
        $post = is_singular() ? get_queried_object() : null;
        $meta = $post ? get_post_meta($post->ID, self::META_KEY, true) : [];
        $meta = is_array($meta) ? $meta : [];

        // Build @graph from selected nodes
        $graph = [];

        $site_url   = $this->v($opts, 'site_url', home_url('/'));
        $org_id     = rtrim($site_url, '/') . '#org';
        $website_id = rtrim($site_url, '/') . '#website';
        $webpage_id = ($post) ? get_permalink($post) . '#webpage' : rtrim($site_url, '/') . '#webpage';

        // Organization node (optional per page)
        if (!empty($meta['include_org']) || (is_front_page() && $post)) {
            $graph[] = $this->clean([
                '@type'        => 'Organization',
                '@id'          => $org_id,
                'name'         => $this->v($opts, 'org_name'),
                'url'          => $site_url,
                'description'  => $this->v($opts, 'description'),
                'logo'         => $this->v($opts, 'logo'),
                'image'        => $this->csv_to_array($this->v($opts, 'images')),
                'foundingDate' => $this->v($opts, 'founding_date'),
                'founder'      => $this->v($opts, 'founder') ? ['@type' => 'Person', 'name' => $this->v($opts, 'founder')] : null,
                'sameAs'       => $this->csv_to_array($this->v($opts, 'sameas')),
                'contactPoint' => $this->build_contact_points($opts)
            ]);
        }

        // WebSite node (SearchAction)
        $include_website = (!empty($opts['enable_website']) && (is_front_page() || !empty($meta['include_website'])));
        if ($include_website) {
            $graph[] = $this->clean([
                '@type' => 'WebSite',
                '@id'   => $website_id,
                'url'   => $site_url,
                'name'  => $this->v($opts, 'org_name'),
                'publisher' => $this->node_ref($org_id),
                'inLanguage' => $this->first_lang($this->v($opts, 'languages')),
                'potentialAction' => $this->v($opts, 'search_target') ? [
                    '@type' => 'SearchAction',
                    'target' => $this->v($opts, 'search_target'),
                    'query-input' => 'required name=search_term_string'
                ] : null
            ]);
        }

        // WebPage node (per page)
        $include_webpage = (!empty($meta['include_webpage']) || is_singular());
        if ($include_webpage) {
            $graph[] = $this->clean([
                '@type' => 'WebPage',
                '@id'   => $webpage_id,
                'url'   => $post ? get_permalink($post) : $site_url,
                'name'  => $this->v($meta, 'override_name', get_the_title($post)),
                'isPartOf' => $include_website ? $this->node_ref($website_id) : null,
                'primaryImageOfPage' => $this->v($meta, 'override_image') ? [
                    '@type' => 'ImageObject',
                    'url'   => $this->v($meta, 'override_image')
                ] : null,
                'inLanguage' => $this->first_lang($this->v($opts, 'languages'))
            ]);
        }

        // LocalBusiness node(s) (global locations, used when selected)
        $include_loc = !empty($meta['include_loc']) || (is_front_page() && $post);
        if ($include_loc) {

            $allowed = $this->allowed_lb_types();
            $lb_type_default_raw = $this->v($meta, 'override_lb_type', $this->v($opts, 'lb_type', 'LocalBusiness'));
            $lb_type_default = in_array($lb_type_default_raw, $allowed, true) ? $lb_type_default_raw : 'LocalBusiness';

            $locations = isset($opts['locations']) && is_array($opts['locations']) ? $opts['locations'] : [];
            // Back-compat: if still empty, try old JSON
            if (empty($locations)) {
                $locations = json_decode($this->v($opts, 'locations_json', '[]'), true);
            }


            if (!is_array($locations) || empty($locations)) {
                $locations = [[
                    'name'  => $this->v($opts, 'org_name', 'Primary Location'),
                    'slug'  => 'primary',
                    'phone' => $this->v($opts, 'phone'),
                    'email' => $this->v($opts, 'email'),
                    'image' => $this->csv_to_array($this->v($opts, 'images'))
                    // address/geo optional; the cleaner will strip empties
                ]];
            }

            if (is_array($locations)) {
                foreach ($locations as $loc) {
                    $slug = isset($loc['slug']) ? $loc['slug'] : sanitize_title($this->v($loc, 'name', 'location'));
                    $loc_id = rtrim($site_url, '/') . '#loc-' . $slug;
                    $graph[] = $this->clean([
                        '@type' => $lb_type_default,
                        '@id'   => $loc_id,
                        'name'  => $this->v($loc, 'name'),
                        'url'   => $site_url,
                        'image' => $this->v($loc, 'image'),
                        'telephone' => $this->v($loc, 'phone'),
                        'email'     => $this->v($loc, 'email'),
                        'priceRange' => $this->v($loc, 'priceRange'),
                        'address'   => $this->address_node($loc),
                        'geo'       => $this->geo_node($loc),
                        'hasMap'    => $this->v($loc, 'hasMap'),
                        'openingHoursSpecification' => $this->hours_nodes($loc),
                        'areaServed' => $this->areas_nodes($loc),
                        'knowsAbout' => $this->services_strings($loc),
                        'parentOrganization' => $this->node_ref($org_id)
                    ]);
                }
            }
        }

        // Optional: add your own custom nodes here if needed based on page templates, etc.

        // Remove null/empty recursively and bail if graph empty
        $graph = array_values(array_filter(array_map([$this, 'clean'], $graph)));
        if (empty($graph)) return;

        $payload = [
            '@context' => 'https://schema.org',
            '@graph'   => $graph
        ];

        echo "\n" . '<!-- Lime Schema Boilerplate -->' . "\n";
        echo '<script type="application/ld+json">' . wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
    }

    /* ---------------------------
     * Helpers (node builders + cleaners)
     * --------------------------- */
    /** Return an allowed list of LocalBusiness subtypes (filterable). */
    private function allowed_lb_types(): array
    {
        $types = [
            // Core
            'LocalBusiness',
            'ProfessionalService',
            'Store',
            'LodgingBusiness',
            'FoodEstablishment',
            'MedicalBusiness',
            'HomeAndConstructionBusiness',
            'AutomotiveBusiness',
            'HealthAndBeautyBusiness',
            'FinancialService',
            'EntertainmentBusiness',
            // Common concrete subtypes
            'AccountingService',
            'Attorney',
            'Notary',
            'TravelAgency',
            'Hotel',
            'Motel',
            'Resort',
            'BedAndBreakfast',
            'Hostel',
            'Campground',
            'Restaurant',
            'FastFoodRestaurant',
            'CafeOrCoffeeShop',
            'BarOrPub',
            'Brewery',
            'Winery',
            'Distillery',
            'Dentist',
            'MedicalClinic',
            'Pharmacy',
            'VeterinaryCare',
            'Electrician',
            'GeneralContractor',
            'HVACBusiness',
            'HousePainter',
            'Locksmith',
            'MovingCompany',
            'PestControl',
            'Plumber',
            'RoofingContractor',
            'AutoRepair',
            'AutoDealer',
            'AutoPartsStore',
            'AutoBodyShop',
            'AutoWash',
            'GasStation',
            'MotorcycleDealer',
            'MotorcycleRepair',
            'BeautySalon',
            'HairSalon',
            'DaySpa',
            'NailSalon',
            'TattooParlor',
            'HealthClub',
            'BankOrCreditUnion',
            'AutomatedTeller',
            'InsuranceAgency',
            'ArtGallery',
            'AmusementPark',
            'Casino',
            'ComedyClub',
            'MovieTheater',
            'NightClub',
            'Bakery',
            'GroceryStore',
            'ConvenienceStore',
            'DepartmentStore',
            'ClothingStore',
            'ShoeStore',
            'JewelryStore',
            'ToyStore',
            'SportingGoodsStore',
            'FurnitureStore',
            'HomeGoodsStore',
            'HardwareStore',
            'GardenStore',
            'PetStore',
            'ElectronicsStore',
            'ComputerStore',
            'MobilePhoneStore',
            'BookStore',
            'MusicStore',
            'OfficeEquipmentStore',
            'OutletStore',
            'PawnShop',
            'TireShop',
            'Florist',
            'BicycleStore'
        ];
        return apply_filters('lime_schema_allowed_lb_types', $types);
    }


    private function v($arr, $key, $default = '')
    {
        return isset($arr[$key]) && $arr[$key] !== '' ? $arr[$key] : $default;
    }

    private function csv_to_array($str)
    {
        $out = array_filter(array_map('trim', explode(',', (string)$str)));
        return $out ? array_values($out) : null;
    }

    private function first_lang($langs_csv)
    {
        $arr = $this->csv_to_array($langs_csv);
        return $arr ? $arr[0] : null;
    }

    private function node_ref($id)
    {
        return $id ? ['@id' => $id] : null;
    }

    private function build_contact_points($opts)
    {
        $phone = $this->v($opts, 'phone');
        $email = $this->v($opts, 'email');
        if (!$phone && !$email) return null;
        $cp = [
            '@type' => 'ContactPoint',
            'contactType' => 'customer service',
            'telephone'   => $phone ?: null,
            'email'       => $email ?: null,
            'areaServed'  => $this->csv_to_array($this->v($opts, 'languages')) ? 'CA' : null,
            'availableLanguage' => $this->csv_to_array($this->v($opts, 'languages'))
        ];
        return $this->clean($cp) ? [$this->clean($cp)] : null;
    }

    private function address_node($loc)
    {
        $addr = $this->v($loc, 'address', []);
        if (!$addr) return null;
        $node = [
            '@type' => 'PostalAddress',
            'streetAddress'   => $this->v($addr, 'streetAddress'),
            'addressLocality' => $this->v($addr, 'addressLocality'),
            'addressRegion'   => $this->v($addr, 'addressRegion'),
            'postalCode'      => $this->v($addr, 'postalCode'),
            'addressCountry'  => $this->v($addr, 'addressCountry')
        ];
        return $this->clean($node);
    }

    private function geo_node($loc)
    {
        $geo = $this->v($loc, 'geo', []);
        if (!is_array($geo)) return null;
        $lat = isset($geo['latitude']) ? floatval($geo['latitude']) : null;
        $lng = isset($geo['longitude']) ? floatval($geo['longitude']) : null;
        if (!$lat && !$lng) return null;
        return $this->clean(['@type' => 'GeoCoordinates', 'latitude' => $lat, 'longitude' => $lng]);
    }

    private function hours_nodes($loc)
    {
        $hours = $this->v($loc, 'openingHours', []);
        if (!is_array($hours) || empty($hours)) return null;
        $out = [];
        foreach ($hours as $h) {
            $out[] = $this->clean([
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => isset($h['days']) ? array_values($h['days']) : null,
                'opens'     => $this->v($h, 'opens'),
                'closes'    => $this->v($h, 'closes')
            ]);
        }
        $out = array_values(array_filter($out));
        return $out ?: null;
    }

    private function services_strings($loc)
    {
        $list = [];
        if (!empty($loc['services']) && is_array($loc['services'])) {
            foreach ($loc['services'] as $s) {
                $name = isset($s['serviceType']) ? trim((string)$s['serviceType']) : '';
                if ($name !== '') $list[] = $name;
            }
        }
        return $list ?: null;
    }

    private function areas_nodes($loc)
    {
        $areas = $this->v($loc, 'areaServed', []);
        if (!is_array($areas) || empty($areas)) return null;
        $out = [];
        foreach ($areas as $name) {
            $name = trim((string)$name);
            if (!$name) continue;
            $out[] = ['@type' => 'City', 'name' => $name];
        }
        return $out ?: null;
    }

    private function clean($value)
    {
        // Recursively remove null/empty values and empty arrays/objects
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $vv = $this->clean($v);
                if ($vv === null || $vv === '' || (is_array($vv) && empty($vv))) {
                    unset($value[$k]);
                } else {
                    $value[$k] = $vv;
                }
            }
            return empty($value) ? null : $value;
        }
        return $value;
    }

    private function meta_lb_select($name, $value)
    {
        $options = $this->allowed_lb_types();
        echo "<select class='widefat' name='" . self::META_KEY . "[$name]'>";
        echo "<option value=''>— Use default —</option>";
        foreach ($options as $t) {
            echo "<option value='" . esc_attr($t) . "'" . selected($value, $t, false) . ">$t</option>";
        }
        echo "</select>";
        echo "<span class='description'>Optional: override the default subtype for this page only.</span>";
    }

    private function field_text($key, $label, $opts)
    {
        $v = esc_attr($this->v($opts, $key));
        echo "<p><label><strong>$label</strong><br><input type='text' class='regular-text' name='" . self::OPTION_KEY . "[$key]' value='$v'></label></p>";
    }
    private function field_email($key, $label, $opts)
    {
        $v = esc_attr($this->v($opts, $key));
        echo "<p><label><strong>$label</strong><br><input type='email' class='regular-text' name='" . self::OPTION_KEY . "[$key]' value='$v'></label></p>";
    }
    private function field_url($key, $label, $opts)
    {
        $v = esc_attr($this->v($opts, $key));
        echo "<p><label><strong>$label</strong><br><input type='url' class='regular-text' name='" . self::OPTION_KEY . "[$key]' value='$v'></label></p>";
    }
    private function field_textarea($key, $label, $opts)
    {
        $v = esc_textarea($this->v($opts, $key));
        echo "<p><label><strong>$label</strong><br><textarea rows='3' class='large-text' name='" . self::OPTION_KEY . "[$key]'>$v</textarea></label></p>";
    }
    private function field_checkbox($key, $label, $opts)
    {
        $v = !empty($opts[$key]) ? 1 : 0;
        echo "<p><label><input type='checkbox' name='" . self::OPTION_KEY . "[$key]' value='1' " . checked($v, 1, false) . "> <strong>$label</strong></label></p>";
    }

    private function render_location_block($loc = [], $is_proto = false)
    {
        $wrap_start = $is_proto ? '<div class="ls-loc">' : '<div class="ls-loc">';
        echo $wrap_start;
        echo '<h4>Location</h4>';
        // Row 1
        echo '<div class="ls-row">';
        echo '<div><label><strong>Name</strong><br><input type="text" data-name="name" value="' . esc_attr($this->v($loc, 'name')) . '"></label></div>';
        echo '<div><label><strong>Slug</strong><br><input type="text" data-name="slug" value="' . esc_attr($this->v($loc, 'slug')) . '"></label></div>';
        echo '</div>';
        // Row 2
        echo '<div class="ls-row">';
        echo '<div><label><strong>Phone</strong><br><input type="text" data-name="phone" value="' . esc_attr($this->v($loc, 'phone')) . '"></label></div>';
        echo '<div><label><strong>Email</strong><br><input type="email" data-name="email" value="' . esc_attr($this->v($loc, 'email')) . '"></label></div>';
        echo '<div><label><strong>Price Range</strong><br><input type="text" data-name="priceRange" value="' . esc_attr($this->v($loc, 'priceRange')) . '"></label></div>';
        echo '</div>';
        // Images, Map
        echo '<div class="ls-row">';
        echo '<div><label><strong>Image URLs (comma-separated)</strong><br><input type="text" data-name="image_csv" value="' . esc_attr(is_array($this->v($loc, 'image')) ? implode(', ', $loc['image']) : $this->v($loc, 'image_csv')) . '"></label></div>';
        echo '<div><label><strong>Google Map Link</strong><br><input type="url" data-name="hasMap" value="' . esc_attr($this->v($loc, 'hasMap')) . '"></label></div>';
        echo '</div>';
        // Address
        $addr = $loc['address'] ?? [];
        if (!is_array($addr)) {
            $addr = [];
        }
        echo '<fieldset style="border:1px dashed #ccc;padding:8px;margin-top:8px"><legend><strong>Address</strong></legend>';
        echo '<div class="ls-row"><div><label>Street<br><input type="text" data-name="address[streetAddress]" value="' . esc_attr($this->v($addr, 'streetAddress')) . '"></label></div>
            <div><label>City<br><input type="text" data-name="address[addressLocality]" value="' . esc_attr($this->v($addr, 'addressLocality')) . '"></label></div>
            <div><label>Region/State<br><input type="text" data-name="address[addressRegion]" value="' . esc_attr($this->v($addr, 'addressRegion')) . '"></label></div></div>';
        echo '<div class="ls-row"><div><label>Postal/ZIP<br><input type="text" data-name="address[postalCode]" value="' . esc_attr($this->v($addr, 'postalCode')) . '"></label></div>
            <div><label>Country (code)<br><input type="text" data-name="address[addressCountry]" value="' . esc_attr($this->v($addr, 'addressCountry')) . '"></label></div></div>';
        echo '</fieldset>';
        // Geo
        $geo = $loc['geo'] ?? [];
        if (!is_array($geo)) {
            $geo = [];
        }
        echo '<fieldset style="border:1px dashed #ccc;padding:8px;margin-top:8px"><legend><strong>Geo</strong></legend>';
        echo '<div class="ls-row"><div><label>Latitude<br><input type="text" data-name="geo[latitude]" value="' . esc_attr($this->v($geo, 'latitude')) . '"></label></div>
            <div><label>Longitude<br><input type="text" data-name="geo[longitude]" value="' . esc_attr($this->v($geo, 'longitude')) . '"></label></div></div>';
        echo '</fieldset>';
        // Service + Areas (simple)
        echo '<div class="ls-row"><div><label><strong>Areas Served (comma-separated)</strong><br><input type="text" data-name="areas_csv" value="' . esc_attr(isset($loc['areaServed']) && is_array($loc['areaServed']) ? implode(", ", $loc['areaServed']) : $this->v($loc, 'areas_csv')) . '"></label></div></div>';
        echo '<div class="ls-row"><div><label><strong>Services (one per line)</strong><br><textarea rows="3" data-name="services_text">' . esc_textarea($this->services_to_text($loc)) . '</textarea></label></div></div>';

        echo '<p class="ls-actions"><a href="#" class="button-link-delete ls-remove">Remove location</a></p>';
        echo '</div>';
    }

    private function services_to_text($loc)
    {
        if (!empty($loc['services']) && is_array($loc['services'])) {
            $names = [];
            foreach ($loc['services'] as $s) {
                if (!empty($s['serviceType'])) $names[] = $s['serviceType'];
            }
            return implode("\n", $names);
        }
        return $this->v($loc, 'services_text', '');
    }

    private function field_lb_select($key, $label, $opts, $help = '')
    {
        $current = $this->v($opts, $key, 'LocalBusiness');
        $options = $this->allowed_lb_types();
        echo "<p><label><strong>{$label}</strong><br>";
        echo "<select name='" . self::OPTION_KEY . "[$key]' class='regular-text'>";
        foreach ($options as $t) {
            echo "<option value='" . esc_attr($t) . "'" . selected($current, $t, false) . ">$t</option>";
        }
        echo "</select></label>";
        if ($help) {
            echo "<br><span class='description'>{$help}</span>";
        }
        echo "</p>";
    }
}

new Lime_Schema_Boilerplate();
