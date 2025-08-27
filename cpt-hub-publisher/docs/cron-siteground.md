# Server Cron Setup — SiteGround

This guide shows how to run WordPress cron from your SiteGround server instead of relying on WP‑Cron.

## Why use server cron?
- Predictable schedule independent of site traffic or caches.
- Lower overhead (can run via CLI PHP).
- Recommended for production reliability.

## Step 1 — Disable WP‑Cron in WordPress
Edit `wp-config.php` and set:

```
define('DISABLE_WP_CRON', true);
```

If this line is missing or set to `false`, add/adjust it above the line that says “That’s all, stop editing!”. This prevents duplicate runs (server cron + WP‑Cron).

## Step 2 — Decide how to call cron
Choose one of the following.

- HTTP (simplest): requests your site’s `wp-cron.php` URL
  - Command: `curl -sS https://staging2.limea87.sg-host.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1`
- CLI PHP (preferred): executes cron via PHP directly, avoiding HTTP
  - Command: `/usr/local/bin/php -q /home/customer/www/your-site.com/public_html/wp-cron.php >/dev/null 2>&1`

Notes:
- Replace `your-site.com` with your domain. On SiteGround, the default document root path is typically `/home/customer/www/your-site.com/public_html/`.
- If SiteGround offers a PHP dropdown in Cron Jobs, use that PHP binary path in the command.

## Step 3 — Add the cron job in SiteGround
1. Login to SiteGround → Site Tools → Devs → Cron Jobs.
2. Click “Create New Cron Job”.
3. Schedule: every 5 or 10 minutes (e.g., `*/10 * * * *`).
4. Command: paste one of the commands from Step 2.
5. Save.

Example (HTTP):
```
*/10 * * * * curl -sS https://your-site.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1
```

Example (CLI):
```
*/10 * * * * /usr/local/bin/php -q /home/customer/www/your-site.com/public_html/wp-cron.php >/dev/null 2>&1
```

## Step 4 — Verify it runs
- In WordPress → Settings → CPT Hub Consumer, check the “Cron status” card:
  - WP‑Cron status: “disabled” (as intended, since you set `DISABLE_WP_CRON` to true)
  - Next scheduled: shows a timestamp
  - Last run: updates within one interval after the server cron fires
- You can also click the “Run”/“Execute” action in SiteGround’s Cron Jobs list to trigger immediately.

## Tips & Troubleshooting
- If using HTTP method and you have Basic Auth or maintenance mode enabled, allowlist `wp-cron.php` requests.
- If the HTTP command fails silently, try `wget -q -O - ...` instead of `curl`.
- If the CLI path is different on your server, check the Cron Jobs page for your available PHP path (e.g., `/usr/local/bin/php81`).
- Keep the interval at 5–10 minutes to balance freshness and load.
- The Consumer plugin’s Cache Status table should show updated times/statuses for items and assets shortly after the cron runs.

## Safe defaults for this project
- Interval: every 10 minutes.
- Method: CLI PHP when available; HTTP is acceptable if CLI path is unknown.
- WP‑Cron: `DISABLE_WP_CRON` set to `true` in `wp-config.php`.

