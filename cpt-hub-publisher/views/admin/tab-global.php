<?php
// Global CSS tab — allows sitewide CSS string to be delivered to Consumers.
$opt = get_option(CPT_Hub_Publisher::OPTION_GLOBAL_CSS, ['css'=>'','version'=>'','modified'=>0]);
$css = (string)($opt['css'] ?? '');
$ver = (string)($opt['version'] ?? '');
$mod = !empty($opt['modified']) ? date('Y-m-d H:i', (int)$opt['modified']) : '—';
$endpoint = esc_url( rest_url('cphub/v1/global') );
?>

<h2 class="title">Global CSS</h2>
<p class="description">CSS entered here is delivered to all Consumers and enqueued across the whole site (in addition to any per‑CPT styles). It also enqueues site‑wide on this Publisher.</p>

<form method="post" class="card" style="padding:1em; max-width:1000px;">
  <?php wp_nonce_field(CPT_Hub_Publisher::NONCE_ACTION); ?>
  <input type="hidden" name="cphub_action" value="global_save" />
  <table class="form-table" role="presentation">
    <tr>
      <th scope="row"><label for="cphub_global_css">CSS</label></th>
      <td>
        <textarea id="cphub_global_css" name="css" rows="12" class="large-text code" placeholder="/* sitewide CSS here */"><?php echo esc_textarea($css); ?></textarea>
        <p class="description">Keep it minimal and namespaced to avoid conflicts. Example: <code>.cphub-card .my-class { ... }</code></p>
      </td>
    </tr>
    <tr>
      <th scope="row">Version</th>
      <td><code><?php echo esc_html($ver ? substr($ver,0,10) : '—'); ?></code> <span class="description">Last modified: <?php echo esc_html($mod); ?></span></td>
    </tr>
    <tr>
      <th scope="row">REST endpoint</th>
      <td><code><?php echo $endpoint; ?></code></td>
    </tr>
  </table>
  <p><button class="button button-primary">Save Global CSS</button></p>
</form>
