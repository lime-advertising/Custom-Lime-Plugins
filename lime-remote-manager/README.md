# Lime Remote Manager

This repository contains the Lime Remote Manager platform, comprising:

- **Agent MU-plugin** (`agent/`) deployed to managed WordPress sites.
- **Controller plugin** (`controller/`) installed in Lime's central WordPress instance.
- **Documentation** (`docs/`) describing product requirements, setup, operations, and testing.

## Getting Started

1. Clone this repository alongside your WordPress development environments.
2. Symlink or copy the `agent/` directory into the target site's `wp-content/mu-plugins/` directory (rename to `lime-remote-agent` if needed).
3. Symlink or copy the `controller/` directory into your controller WordPress instance under `wp-content/plugins/lime-remote-controller/`.
4. Activate the controller plugin via the WordPress admin or `wp plugin activate lime-remote-controller`.
5. Visit **Remote Manager** in the WordPress admin to confirm the placeholder dashboard renders.
6. To expose the agent admin screen (for secret retrieval/rotation) define `LIMERM_AGENT_ADMIN_UI` as `true` in `wp-config.php`, then visit **Tools → Lime Remote Agent** (or **Network Admin → Settings → Lime Remote Agent** on multisite). Remove or set the constant to `false` afterwards to hide the screen.

The agent plugin auto-initialises once placed in the `mu-plugins` directory and exposes the signed `GET /wp-json/lrma/v1/info` endpoint.

## Snapshot Processing

- The controller’s “Trigger Snapshot” action queues a background job via the agent.
- Jobs dump the relevant database tables, bundle uploads, and archive everything to `wp-content/uploads/lrm-snapshots/{blog_id}/snapshot.zip`.
- Snapshot metadata is recorded in the `wp_lrm_snapshots` table (per managed site); monitor disk usage to ensure adequate space.

## Development Notes

- PHP 8.1+ and WordPress 6.4+ are required.
- Run WordPress coding standards (PHPCS) before commits once tooling is added.
- Use the manual test plan (`docs/manual-test-plan.md`) for release validation until automated suites are introduced.
- Additional CLI utilities (secret rotation, health check) will be added under WP-CLI commands in future milestones.
