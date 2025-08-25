# CPT Hub — Milestones Checklist

Only update this checklist after the stakeholder says “done”.

## 1) Publisher: Location Targeting
- [x] Add `location` taxonomy; seed 40 terms + `all-locations`.
- [x] Filter feeds/REST by `location`, returning union of `all-locations` + the requested location.
- [x] Admin UI to manage locations (optional basic CRUD).
- [x] Acceptance: Correct union in responses; caching and performance verified.

## 2) Publisher: Style Sets v1
- [ ] Per‑CPT style set fields (CSS/JS upload or editors) + version hash.
- [ ] REST manifest endpoint returning `{ version, css[], js[] }`.
- [ ] Version bump on change; cache headers (ETag/Last‑Modified).
- [ ] Acceptance: Consumer observes new version within 5 minutes or on demand.

## 3) Consumer Plugin: Core Settings
- [ ] Settings for publisher base URL, secret key, `location` slug.
- [ ] Per‑CPT enable/disable toggles.
- [ ] Health check endpoint test + status display.
- [ ] Acceptance: Connectivity validated; credentials stored securely.

## 4) Consumer: Content Ingestion + Caching
- [ ] Fetch JSON for enabled CPTs; cache locally with conditional GET.
- [ ] Background cron refresh; backoff on failures.
- [ ] Shortcode/block renderers for lists and single items, mapping fields.
- [ ] Acceptance: Pages render from cache; recover gracefully on network errors.

## 5) Consumer: Style Sets Integration
- [ ] Fetch asset manifest; cache by version; enqueue CSS/JS per CPT.
- [ ] Toggle: use publisher styles or local theme.
- [ ] Prevent duplicate enqueues and ensure dependency order.
- [ ] Acceptance: Visual consistency; updates apply on version change.

## 6) Local‑Only Content (Optional add‑on)
- [ ] Local CPTs mirroring publisher structures.
- [ ] Merge remote + local items in renderers (by date or configurable).
- [ ] Clear labeling or markers for local-only items.
- [ ] Acceptance: Local content appears without affecting publisher data.

## 7) Governance & Security
- [ ] Per‑site keys (preferred) or shared key; rotation workflow.
- [ ] Rate limiting guidance; basic request logging (optional).
- [ ] Acceptance: Key rotation does not disrupt consumers; docs provided.
  
Status: Feed-level shared secret supported; per-site key workflow pending.

## 8) Docs & Rollout
- [ ] Admin guides for publisher and consumers.
- [ ] Migration plan from manual updates; pilot rollout to 1–2 sites.
- [ ] Batch rollout plan for remaining locations; monitoring checklist.
- [ ] Acceptance: Pilot signed off; playbook approved for scale‑out.

Status: Plan + milestones + consumer setup docs added; in-app docs tab covers feeds and REST. Publisher setup + rollout playbook pending.
