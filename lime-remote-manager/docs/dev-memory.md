# Dev Session Notes

## Context Snapshot (16 Sep 2025)
- Agent MU-plugin and Controller plugin scaffolds in place.
- Agents expose `/wp-json/lrma/v1/info` and `/wp-json/lrma/v1/snapshot` with HMAC validation. Snapshot endpoint now queues real jobs that dump DB and uploads into `wp-content/uploads/lrm-snapshots/{blog_id}/snapshot.zip`.
- Controller dashboard lists sites, stores metadata, supports per-site detail view.
- Snapshot trigger works end-to-end (agent returns accepted placeholder; actual backups not yet implemented).
- Secret management UI is guarded by `LIMERM_AGENT_ADMIN_UI` constant.

## Potential Next Steps
1. Build rollback endpoint and controller UI.
2. Wire Change URL workflow with confirmations.
3. Add audit logging table and admin log viewer.
4. Automated polling/cron to refresh site status regularly.

## Open Questions
- Snapshot persistence format: DB exports + uploads, storage location, retention.
- Rollback safety checks and confirmations.
- Multisite handling specifics for snapshots/URL changes.
