<?php
if (!defined('ABSPATH')) exit;

class Lime_Schema_Renderer
{
    public function hooks(): void
    {
        add_action('wp_head', [$this, 'output_schema'], 30);
    }

    /** Build a preview payload for the homepage using current options. */
    public function build_preview_payload(array $opts): array
    {
        $graph = [];
        $site_url   = $this->stable_site_url($opts);
        $org_id     = rtrim($site_url, '/') . '#org';
        $website_id = rtrim($site_url, '/') . '#website';
        $webpage_id = rtrim($site_url, '/') . '#webpage';

        $graph[] = $this->build_organization_node($opts, $site_url, $org_id);
        $graph[] = $this->build_website_node($opts, $site_url, $org_id, $website_id);
        $graph[] = $this->build_webpage_node($opts, ['override_name' => $this->v($opts, 'org_name')], null, $website_id, $webpage_id, $site_url, true);
        $faq = $this->faq_main_entities($opts);
        if (!empty($faq)) {
            $graph[] = $this->clean([
                '@type' => 'FAQPage',
                '@id'   => rtrim($site_url, '/') . '#faq',
                'isPartOf' => $this->node_ref($webpage_id),
                'mainEntity' => $faq,
            ]);
        }
        $graph   = array_merge($graph, $this->build_localbusiness_nodes($opts, [], $site_url, $org_id));

        $graph = array_values(array_filter(array_map([$this, 'clean'], $graph)));
        return [
            '@context' => 'https://schema.org',
            '@graph'   => $graph,
        ];
    }
    private function faq_main_entities(array $opts): array
    {
        $faqs = isset($opts['faqs']) && is_array($opts['faqs']) ? $opts['faqs'] : [];
        return $this->faq_main_entities_from_array($faqs);
    }

    private function faq_main_entities_from_array(array $faqs): array
    {
        $out = [];
        foreach ($faqs as $faq) {
            $q = isset($faq['question']) ? trim((string)$faq['question']) : '';
            $a = isset($faq['answer']) ? trim((string)$faq['answer']) : '';
            if ($q && $a) {
                $out[] = [
                    '@type' => 'Question',
                    'name'  => $q,
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text'  => $a,
                    ],
                ];
            }
        }
        return $out;
    }

    /** Build recommended-field hints based on current options. */
    public function build_preview_issues(array $opts): array
    {
        $issues = [];

        // Organization
        if (!$this->v($opts, 'org_name')) $issues[] = __('Organization name is missing.', LIME_SCHEMA_TEXT_DOMAIN);
        if (!$this->v($opts, 'site_url')) $issues[] = __('Site URL (canonical) is missing.', LIME_SCHEMA_TEXT_DOMAIN);
        if (!$this->v($opts, 'logo'))     $issues[] = __('Logo URL is recommended for Organization.', LIME_SCHEMA_TEXT_DOMAIN);
        if (!$this->v($opts, 'description')) $issues[] = __('Short description is recommended for Organization.', LIME_SCHEMA_TEXT_DOMAIN);
        if (!$this->first_lang($this->v($opts, 'languages'))) $issues[] = __('Primary language (e.g., en-CA) is recommended.', LIME_SCHEMA_TEXT_DOMAIN);

        // Website
        if (!empty($opts['enable_website']) && !$this->v($opts, 'search_target')) {
            $issues[] = __('Search URL pattern is recommended when WebSite is enabled.', LIME_SCHEMA_TEXT_DOMAIN);
        }

        // Locations
        $locations = isset($opts['locations']) && is_array($opts['locations']) ? $opts['locations'] : [];
        if (empty($locations)) {
            $legacy = json_decode($this->v($opts, 'locations_json', '[]'), true);
            if (is_array($legacy)) $locations = $legacy;
        }
        if (empty($locations)) {
            $issues[] = __('Consider adding at least one Location for LocalBusiness.', LIME_SCHEMA_TEXT_DOMAIN);
        } else {
            foreach ($locations as $idx => $loc) {
                $label = $this->v($loc, 'name', sprintf(__('Location %d', LIME_SCHEMA_TEXT_DOMAIN), $idx + 1));
                $addr = isset($loc['address']) && is_array($loc['address']) ? $loc['address'] : [];
                $geo  = isset($loc['geo']) && is_array($loc['geo']) ? $loc['geo'] : [];
                if (!$this->v($loc, 'phone')) $issues[] = sprintf(__('Phone is recommended for %s.', LIME_SCHEMA_TEXT_DOMAIN), $label);
                if (!$this->v($addr, 'streetAddress') || !$this->v($addr, 'addressLocality') || !$this->v($addr, 'addressRegion') || !$this->v($addr, 'postalCode') || !$this->v($addr, 'addressCountry')) {
                    $issues[] = sprintf(__('Complete address (street, city, region, postal, country) is recommended for %s.', LIME_SCHEMA_TEXT_DOMAIN), $label);
                }
                $lat = isset($geo['latitude']) ? $geo['latitude'] : null;
                $lng = isset($geo['longitude']) ? $geo['longitude'] : null;
                if ($lat === null || $lng === null) {
                    $issues[] = sprintf(__('Geo coordinates are recommended for %s (or include a Google Map link to auto-fill).', LIME_SCHEMA_TEXT_DOMAIN), $label);
                }
            }
        }

        return $issues;
    }

    public function output_schema()
    {
        if (is_admin() || is_feed() || is_404()) return;

        $opts = get_option(LIME_SCHEMA_OPTION_KEY, []);
        $post = is_singular() ? get_queried_object() : null;
        $meta = $post ? get_post_meta($post->ID, LIME_SCHEMA_META_KEY, true) : [];
        $meta = is_array($meta) ? $meta : [];

        $graph = [];

        $site_url   = $this->stable_site_url($opts);
        $org_id     = rtrim($site_url, '/') . '#org';
        $website_id = rtrim($site_url, '/') . '#website';
        $webpage_id = ($post) ? get_permalink($post) . '#webpage' : rtrim($site_url, '/') . '#webpage';

        if ($this->should_include_org($meta, $post)) {
            $graph[] = $this->build_organization_node($opts, $site_url, $org_id);
        }

        $include_website = $this->should_include_website($opts, $meta, $post);
        if ($include_website) {
            $graph[] = $this->build_website_node($opts, $site_url, $org_id, $website_id);
        }

        $include_webpage = $this->should_include_webpage($meta, $post);
        if ($include_webpage) {
            $graph[] = $this->build_webpage_node($opts, $meta, $post, $website_id, $webpage_id, $site_url, $include_website);
        }

        // FAQ schema (optional, attaches to the current WebPage via a dedicated FAQPage node)
        if (!empty($meta['include_faq'])) {
            $faqs = (!empty($meta['use_custom_faq']) && !empty($meta['faqs']) && is_array($meta['faqs'])) ? $meta['faqs'] : (isset($opts['faqs']) ? $opts['faqs'] : []);
            $faq_entities = $this->faq_main_entities_from_array($faqs);
            if (!empty($faq_entities)) {
                $graph[] = $this->clean([
                    '@type' => 'FAQPage',
                    '@id'   => rtrim($site_url, '/') . '#faq',
                    'isPartOf' => $this->node_ref($webpage_id),
                    'mainEntity' => $faq_entities,
                ]);
            }
        }

        if ($this->should_include_locations($meta, $post)) {
            $graph = array_merge($graph, $this->build_localbusiness_nodes($opts, $meta, $site_url, $org_id));
        }

        $graph = array_values(array_filter(array_map([$this, 'clean'], $graph)));
        if (empty($graph)) return;

        $payload = [
            '@context' => 'https://schema.org',
            '@graph'   => $graph
        ];

        echo "\n" . '<!-- Lime Schema Boilerplate -->' . "\n";
        echo '<script type="application/ld+json">' . wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
    }

    private function stable_site_url(array $opts): string
    { return trailingslashit($this->v($opts, 'site_url', home_url('/'))); }

    private function should_include_org(array $meta, $post): bool
    { return !empty($meta['include_org']) || (is_front_page() && !empty($post)); }

    private function should_include_website(array $opts, array $meta, $post): bool
    { return !empty($opts['enable_website']) && (is_front_page() || !empty($meta['include_website'])); }

    private function should_include_webpage(array $meta, $post): bool
    { return !empty($meta['include_webpage']) || is_singular(); }

    private function should_include_locations(array $meta, $post): bool
    { return !empty($meta['include_loc']) || (is_front_page() && !empty($post)); }

    private function build_organization_node(array $opts, string $site_url, string $org_id): ?array
    {
        return $this->clean([
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

    private function build_website_node(array $opts, string $site_url, string $org_id, string $website_id): ?array
    {
        return $this->clean([
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

    private function build_webpage_node(array $opts, array $meta, $post, string $website_id, string $webpage_id, string $site_url, bool $include_website): ?array
    {
        return $this->clean([
            '@type' => 'WebPage',
            '@id'   => $webpage_id,
            'url'   => $post ? get_permalink($post) : $site_url,
            'name'  => $this->v($meta, 'override_name', $post ? get_the_title($post) : ''),
            'isPartOf' => $include_website ? $this->node_ref($website_id) : null,
            'primaryImageOfPage' => $this->v($meta, 'override_image') ? [
                '@type' => 'ImageObject',
                'url'   => $this->v($meta, 'override_image')
            ] : null,
            'inLanguage' => $this->first_lang($this->v($opts, 'languages'))
        ]);
    }

    private function build_localbusiness_nodes(array $opts, array $meta, string $site_url, string $org_id): array
    {
        $nodes = [];
        $allowed = Lime_Schema_Utils::allowed_lb_types();
        $lb_type_default_raw = $this->v($meta, 'override_lb_type', $this->v($opts, 'lb_type', 'LocalBusiness'));
        $lb_type_default = in_array($lb_type_default_raw, $allowed, true) ? $lb_type_default_raw : 'LocalBusiness';

        $locations = isset($opts['locations']) && is_array($opts['locations']) ? $opts['locations'] : [];
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
            ]];
        }

        foreach ($locations as $loc) {
            $slug = isset($loc['slug']) ? $loc['slug'] : sanitize_title($this->v($loc, 'name', 'location'));
            $loc_id = rtrim($site_url, '/') . '#loc-' . $slug;
            $nodes[] = $this->clean([
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
        return $nodes;
    }

    /* helpers */
    private function v($arr, $key, $default = '')
    { return isset($arr[$key]) && $arr[$key] !== '' ? $arr[$key] : $default; }
    private function csv_to_array($str)
    { $out = array_filter(array_map('trim', explode(',', (string)$str))); return $out ? array_values($out) : null; }
    private function first_lang($langs_csv)
    { $arr = $this->csv_to_array($langs_csv); return $arr ? $arr[0] : null; }
    private function node_ref($id)
    { return $id ? ['@id' => $id] : null; }
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
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $vv = $this->clean($v);
                if ($vv === null || $vv === '' || (is_array($vv) && empty($vv))) unset($value[$k]);
                else $value[$k] = $vv;
            }
            return empty($value) ? null : $value;
        }
        return $value;
    }
}
