# Elementor Template Sync Manager (ETSM)

Centralized management and safe synchronization of Elementor templates from a single Publisher site to many Consumer sites, without creating duplicates. Includes push (webhook/API) and pull (cron) modes, rollback, and auditability.

## Features
- Single source of truth for Elementor templates
- Selective deployment to all or chosen sites
- No-duplicate updates via immutable `global_template_id`
- Dry-run diffs and one-click rollback
- Push via webhook or scheduled pull
- Basic HMAC auth (extensible to stronger model)
- Optional sync of display conditions (Elementor Pro) with replace/merge/skip policy

## Repo Structure
- `publisher/` — Publisher plugin (registry, deploy orchestration)
- `consumer/` — Consumer plugin (webhook, pull, apply, rollback)
- `shared/` — Shared utilities (HMAC, JSON validation)
- `docs/` — Memory Bank, User Guide, Technical Guide

Key entrypoints:
- `publisher/elementor-template-sync-publisher.php:1`
- `consumer/elementor-template-sync-consumer.php:1`
- `shared/includes/class-hmac.php:1`
- `shared/includes/class-json.php:1`

## Requirements
- WordPress 6.2+
- PHP 8.1+
- Elementor 3.18+ (Elementor Pro optional; needed for advanced conditions)

## Install
1) Copy `publisher/` to the Publisher site `wp-content/plugins/` and activate.
2) Copy `consumer/` to each Consumer site `wp-content/plugins/` and activate.

## Quick Start
- On each Consumer site → Admin → Template Sync → Enroll: enter Publisher URL + Site Token.
- On the Publisher site → Admin → Template Sync → Consumers: confirm sites are connected.
- Edit template on Publisher, then deploy to selected sites.

## API Summary
Namespace: `etsm/v1` (WordPress REST API)
- Publisher (HMAC):
  - `GET /wp-json/etsm/v1/templates`
  - `GET /wp-json/etsm/v1/templates/{global_template_id}`
  - `GET /wp-json/etsm/v1/updates`
  - `POST /wp-json/etsm/v1/deploy` (admin-only)
- Consumer:
  - `POST /wp-json/etsm/v1/webhook/deploy` (HMAC)
  - `POST /wp-json/etsm/v1/register`

HMAC headers: `X-ETSM-Token`, `X-ETSM-Timestamp`, `X-ETSM-Nonce`, `X-ETSM-Signature`

## Security (MVP)
- HMAC with 5-minute timestamp window
- Token used as shared secret (will be replaced by per-site secret + rotation)
- Capability `manage_template_sync` protects admin actions

## Development
- DB schemas created on activation (Publisher: templates, versions, consumers, deployments; Consumer: map, history, jobs)
- Deployment worker stubbed; replace with Action Scheduler for retries/concurrency
- Media copy/remap implemented as a simple URL sideload in `_elementor_data`

## Documentation
- User guide (non-technical): `docs/USER_GUIDE.md:1`
- Technical guide (developers): `docs/TECHNICAL_GUIDE.md:1`
- Memory bank (decisions/backlog): `docs/MEMORY_BANK.md:1`

## Roadmap (Short)
- Implement deployment worker with retries and audit trail
- Implement pull updates feed and policies
- React admin UIs (registry, diffs, deploy, history, health)
- Security hardening: separate hashed secrets, nonce store, rate limiting
 - Condition sync enhancements: validation, per-site policy overrides

## Notes
- This repository scaffolds core flows and contracts; some features are placeholders pending wiring (queues, UI, advanced security).
