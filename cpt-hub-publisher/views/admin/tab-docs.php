<?php
// Documentation tab — explains how the plugin works and how to use it.
?>

<div class="card" style="padding:1em; max-width:1000px;">
  <h2>Overview</h2>
  <p>CPT Hub lets you define Custom Post Types (CPTs) on this Publisher site, add custom meta fields (including media), and expose content via a structured RSS feed. Location sites (Consumers) fetch and render that content, optionally using Publisher-provided styles and scripts.</p>

  <h2>How It Works</h2>
  <ul>
    <li><strong>CPTs:</strong> Create and manage CPTs in the <em>Content Types</em> tab. Each CPT can have its own fields and assigned taxonomies.</li>
    <li><strong>Fields:</strong> Add per‑CPT fields (text, textarea, number, URL, select, media). Values save to public post meta and appear in the feed.</li>
    <li><strong>Taxonomies:</strong> Use the <em>Taxonomies</em> tab to add custom taxonomies and assign them to CPTs. A global <code>location</code> taxonomy is always available.</li>
    <li><strong>Feeds:</strong> Every CPT has an RSS feed endpoint. Consumers request the feed with filters (page size, page, date, location).</li>
    <li><strong>Locations:</strong> Tag content with specific location slugs and/or <code>all-locations</code>. Consumers request a location to receive the union of location + all-locations.</li>
  </ul>

  <h2>Styles Tab</h2>
  <p>Each CPT has its own style configuration and preview.</p>
  <ul>
    <li><strong>Elements:</strong> Drag to reorder; toggle to show/hide. Map up to three Meta elements to your custom fields.</li>
    <li><strong>Presets:</strong> Choose List or Grid. For Grid, set desktop columns and gap, plus tablet/mobile column counts.</li>
    <li><strong>Per‑element styles:</strong> Color, font size, line‑height, margin/padding, width/min/max, alignment, and border radius (image/button). Buttons also support full‑width and custom box‑shadow.</li>
    <li><strong>Card styles:</strong> Background, border, padding, vertical margin, radius, optional shadow.</li>
    <li><strong>Meta styling:</strong> Choose per‑meta placement (Thumb wrap or Content wrap). Set background, free‑form <em>position</em> and <em>top/right/bottom/left</em> offsets (supports raw CSS including calc()/var()).</li>
    <li><strong>Responsive:</strong> Tablet/mobile “Show” toggles per element and scale factors (e.g., tablet 0.9, mobile 0.6).</li>
    <li><strong>Animations:</strong> Entrance stagger, thumbnail hover reveal (Solid/Sheen), image hover zoom, and optional button ripple. A “Stick to bottom” option can align the button at the bottom of the card.</li>
    <li><strong>Preview:</strong> Renders up to 6 latest posts; the card is grouped into <code>.cphub-thumb-wrap</code> (image + meta set to Thumb) and <code>.cphub-content-wrap</code> (title, text, button + meta set to Content).</li>
  </ul>

  <h2>Assets Endpoint</h2>
  <?php $rest_assets = esc_url( rest_url( 'cphub/v1/assets' ) ); ?>
  <p>Consumers can fetch the current style config for a CPT.</p>
  <ul>
    <li><strong>Endpoint:</strong> <code><?php echo $rest_assets; ?>?cpt=<?php echo esc_html( $example_cpt ?: 'your_cpt_slug' ); ?></code></li>
    <li><strong>Returns:</strong> <code>{ version, layout, css }</code> with caching headers (ETag/Last-Modified).</li>
    <li><strong>Layout keys:</strong> <code>order</code>, <code>enabled</code>, <code>meta_keys</code>, <code>meta_wrap</code> (thumb/content placement), and responsive visibility maps.</li>
    <li><strong>CSS conventions:</strong> Cards use <code>.cphub-card</code> with <code>.cphub-thumb-wrap</code> and <code>.cphub-content-wrap</code> children. Elements include <code>.cphub-img</code>, <code>.cphub-title</code>, <code>.cphub-excerpt</code>, <code>.cphub-content</code>, <code>.cphub-meta</code>, <code>.cphub-btn</code>.</li>
  </ul>

  <h2>Creating Content</h2>
  <ol>
    <li>Define a CPT in the <em>Content Types</em> tab: set label, supports, and optional fields.</li>
    <li>Assign custom taxonomies to the CPT as needed (e.g., brand, region).</li>
    <li>Edit posts of that CPT; fill out custom fields. For media fields, select an attachment from the Media Library.</li>
    <li>Set <strong>Locations</strong> on the post: choose one or more location terms or use <code>all-locations</code> for global content.</li>
  </ol>

  <h2>Feed Endpoints</h2>
  <p>Each CPT has its own feed. Examples below use a sample CPT and values from this site.</p>
  <ul>
    <li><strong>Pretty:</strong> <code><?php echo esc_html( esc_url( trailingslashit($feed_base) . ($example_cpt ?: 'your_cpt_slug') ) ); ?></code></li>
    <li><strong>Query:</strong> <code><?php echo esc_html( esc_url( add_query_arg(['feed' => 'cphub', 'cpt' => $example_cpt ?: 'your_cpt_slug'], home_url('/')) ) ); ?></code></li>
  </ul>
  <p><strong>Parameters:</strong></p>
  <ul>
    <li><code>cpt</code>: CPT slug (when using the query URL).</li>
    <li><code>n</code>: items per page (default from settings; capped at 100).</li>
    <li><code>paged</code>: page number (1‑based).</li>
    <li><code>modified_since</code>: YYYY‑MM‑DD, returns items modified after this date.</li>
    <li><code>location</code>: a location slug; returns items tagged with that slug OR <code>all-locations</code>.</li>
    <li><code>key</code>: optional secret key (if configured) to authorize access.</li>
  </ul>

  <h2>Feed Item Structure</h2>
  <p>For each item the feed includes title, link, publication and modified dates, the full content, and extras:</p>
  <ul>
    <li><strong>content:encoded</strong>: Full HTML content.</li>
    <li><strong>media:content</strong>: Featured image URL (if set).</li>
    <li><strong>cphub:meta</strong>: All public custom fields as key/value entries.</li>
    <li><strong>Media fields:</strong> Additional meta keys with <code>_id</code>, <code>_url</code>, and <code>_mime</code>.</li>
    <li><strong>cphub:term</strong>: Custom taxonomy terms with attributes <code>tax</code>, <code>slug</code>, and <code>id</code>.</li>
  </ul>

  <h2>REST API</h2>
  <p>A JSON endpoint is available for easier consumption by location sites. It mirrors the feed filters and returns items with the same fields.</p>
  <?php $rest_items = esc_url( rest_url( 'cphub/v1/items' ) ); ?>
  <ul>
    <li><strong>Endpoint:</strong> <code><?php echo $rest_items; ?></code></li>
    <li><strong>Params:</strong> <code>cpt</code>, <code>n</code> (capped 100), <code>paged</code>, <code>modified_since</code>, <code>location</code>, <code>key</code> (if configured)</li>
  </ul>
  <p><strong>Examples:</strong></p>
  <ul>
    <li>All CPTs: <code><?php echo $rest_items; ?>?n=20</code></li>
    <li>Specific CPT: <code><?php echo $rest_items; ?>?cpt=<?php echo esc_html( $example_cpt ?: 'your_cpt_slug' ); ?>&n=10&paged=2</code></li>
    <li>Modified since: <code><?php echo $rest_items; ?>?modified_since=2025-01-01</code></li>
    <li>Location filter (union with all-locations): <code><?php echo $rest_items; ?>?cpt=<?php echo esc_html( $example_cpt ?: 'your_cpt_slug' ); ?>&location=your-location</code></li>
  </ul>
  <p><strong>curl:</strong></p>
  <pre style="white-space:pre-wrap;">
curl -s '<?php echo $rest_items; ?>?cpt=<?php echo esc_html( $example_cpt ?: 'your_cpt_slug' ); ?>&n=10&paged=1&location=your-location<?php echo ! empty( get_option( CPT_Hub_Publisher::OPTION_FEED, [] )['secret_key'] ) ? '&key=YOUR_KEY' : ''; ?>' \
  -H 'Accept: application/json'
  </pre>

  <h2>Security</h2>
  <ul>
    <li>Optional secret key requirement via <code>&key=YOUR_KEY</code>.</li>
    <li>Admin actions protected by capability checks and nonces.</li>
  </ul>

  <h2>Performance & Caching</h2>
  <ul>
    <li>Feeds and REST are cached per query (5 minutes) and auto‑bust on content changes.</li>
    <li>REST includes <strong>ETag</strong>/<strong>Last-Modified</strong> headers and honors conditional requests (304).</li>
    <li>Use <code>modified_since</code> for incremental consumption; <code>n</code> is capped at 100.</li>
  </ul>

  <h2>Health</h2>
  <?php $rest_health = esc_url( rest_url( 'cphub/v1/health' ) ); ?>
  <p>Use the health endpoint to quickly verify feed/REST availability, cache status, and current styles versions.</p>
  <ul>
    <li><strong>Endpoint:</strong> <code><?php echo $rest_health; ?></code></li>
    <li><strong>Returns:</strong> time, feed base + example URL, cache entries and TTL, REST base + example URLs, and per‑CPT style <code>{ version, modified }</code>.</li>
    <li><strong>Headers parity:</strong> RSS and REST implement <em>ETag</em> and <em>Last‑Modified</em>; conditional requests (If‑None‑Match / If‑Modified‑Since) return 304 when unchanged.</li>
    <li><strong>Tip:</strong> In your browser dev tools or with <code>curl -i</code>, confirm <code>ETag</code> and <code>Last-Modified</code> appear, then repeat with those headers to see a 304.</li>
  </ul>

  <h2>Consumer Integration</h2>
  <p>Consumers configure Publisher URL, secret key (optional), and a <strong>location</strong> slug. They fetch the feed (or a JSON endpoint when available) and render lists/single views. See <em>docs/consumer-setup.md</em> in the plugin for a full setup guide.</p>

  <h2>Tips</h2>
  <ul>
    <li>Keep custom field keys public (no leading underscores) so they appear in feeds.</li>
    <li>Use the <code>location</code> taxonomy to decide which content each site receives.</li>
    <li>For media fields set to images, the consumer can use the <code>_url</code> to render thumbnails or full images.</li>
  </ul>
</div>
