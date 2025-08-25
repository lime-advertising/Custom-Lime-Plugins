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
    <li><code>n</code>: items per page (default from settings).</li>
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
    <li><strong>Params:</strong> <code>cpt</code>, <code>n</code>, <code>paged</code>, <code>modified_since</code>, <code>location</code>, <code>key</code> (if configured)</li>
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
    <li>Feeds are cached per query (5 minutes) and auto‑bust on content changes.</li>
    <li>Use <code>modified_since</code> for incremental consumption.</li>
    <li>Consider capping <code>n</code> to a sensible maximum in production.</li>
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
