# CPT Hub — Animations Plan

This document captures the proposed approach to add optional animations to Publisher‑delivered card UIs. It summarizes feasibility, data model additions, CSS generation, and UI wiring so we can implement incrementally.

## Overview
Two animation features are requested:
- Entrance animation: Cards fade/slide in one by one (staggered).
- Thumbnail hover reveal: A diagonal sweep overlay on the image when hovering the card.

Both are implemented as CSS‑only (no JS required). Entrance uses nth‑child delays; hover reveal is a ::before overlay. Additional polish options (cursor‑origin tracking or in‑view triggering) are deferred.

## 1) Entrance Animation (stagger)

Feasibility
- CSS‑only stagger on first paint: High; uses nth‑child delay rules.
- In‑view stagger (animate when scrolled into viewport): Add a tiny IO helper. Recommended for polish.

Schema additions (per‑CPT styles)
- `anim_stagger_enable: bool`
- `anim_stagger_duration: number (ms)`
- `anim_stagger_delay_step: number (ms)` — per‑card incremental delay
- `anim_stagger_offset: number (px)` — translateY distance
- `anim_stagger_ease: string` — e.g., `ease-out`, `cubic-bezier(...)`

CSS builder
- Emit base keyframes, e.g.:
  ```css
  @keyframes cphubStaggerIn {
    0% { opacity: 0; transform: translateY(var(--cphub-offset, 8px)); }
    100% { opacity: 1; transform: none; }
  }
  ```
- Apply to cards when enabled:
  ```css
  .cphub-card { opacity:0; transform: translateY(8px);
    animation: cphubStaggerIn var(--cphub-dur, 400ms) var(--cphub-ease, ease-out) forwards; }
  .cphub-list .cphub-card:nth-child(1) { animation-delay: 0ms }
  .cphub-list .cphub-card:nth-child(2) { animation-delay: 80ms }
  /* repeat using configured delay step */
  .cphub-grid > .cphub-card:nth-child(1) { animation-delay: 0ms }
  /* etc */
  @media (prefers-reduced-motion: reduce){ .cphub-card { animation: none } }
  ```
- Use configured duration, delay step, offset, and easing. For lists with unknown length, emit delays for first N (e.g., 12) items.

Optional IO (deferred)
- We may later add a tiny IntersectionObserver to animate cards when scrolled into view. Not shipped by default; current delivery is CSS‑only for resilience.

Styles tab UI
- Card → Animations:
  - [ ] Enable entrance stagger
  - Duration (ms)
  - Delay per item (ms)
  - Distance (px)
  - Easing (select/text)

## 2) Thumbnail Hover Reveal (diagonal sweep)

Feasibility
- CSS‑only using a ::before overlay with transforms and transitions.

CSS model
- Ensure `.cphub-thumb-wrap { position: relative; overflow: hidden; }`.
- Solid overlay (implemented):
  ```css
  .cphub-thumb-wrap::before { content:''; position:absolute; inset:-20%; pointer-events:none;
    background: rgba(255,255,255,.15); transform: translateX(-120%) rotate(20deg);
    transition: transform .4s ease; }
  .cphub-card:hover .cphub-thumb-wrap::before { transform: translateX(120%) rotate(20deg); }
  @media (prefers-reduced-motion: reduce){ .cphub-thumb-wrap::before { transition: none } }
  ```
- Sheen gradient (implemented):
  ```css
  .cphub-thumb-wrap::before { content:''; position:absolute; top:0; left:0; width:20%; height:100%; pointer-events:none;
    background: linear-gradient(to right, rgba(255,255,255,0) 0%, rgba(255,255,255,.15) 100%);
    transform: translateX(-120%) skewX(20deg); transition: transform .4s ease; }
  .cphub-card:hover .cphub-thumb-wrap::before { transform: translateX(120%) skewX(20deg); }
  ```

Schema additions (per‑CPT styles)
- `hover_reveal_enable: bool`
- `hover_reveal_color: string (CSS color)`
- `hover_reveal_opacity: number (0–1)`
- `hover_reveal_duration: number (ms)`
- `hover_reveal_ease: string`
- `hover_reveal_angle: number (deg)`
- `hover_reveal_thickness: string (px/%)` — overlay width/height margin beyond bounds
- `hover_reveal_direction: 'tl-br' | 'br-tl'` — direction across the image

CSS builder
- When enabled, emit ::before with configured color/opacity, thickness (via inset), angle (rotate), duration/easing, and direction (flip translate start/end).

Styles tab UI
- Animations (or Image):
  - [ ] Enable thumbnail hover reveal
  - Color, Opacity, Duration (ms), Easing, Angle (deg), Thickness, Direction

## Implementation Outline
Implemented
1) Styles schema + save handlers extended (stagger, hover reveal Solid/Sheen, image hover zoom, button ripple, stick‑to‑bottom).
2) CSS builder generates:
   - Entrance stagger keyframes + nth‑child delays (configurable offset, duration, easing).
   - Hover reveal Solid/Sheen with configurable color/opacity/angle/thickness/duration/ease.
   - Image hover zoom with scale/duration/ease.
   - Button ripple (CSS background ripple; center‑origin by default).
3) Styles UI exposes options under Animations, Image, and Button sections; preview renders with saved CSS.

## Sample CSS Sketch (excerpt)
```css
/* Stagger */
@keyframes cphubStaggerIn { from { opacity:0; transform: translateY(8px) } to { opacity:1; transform:none } }
.cphub-list .cphub-card { opacity:0; transform: translateY(8px); animation: cphubStaggerIn 400ms ease-out forwards }
.cphub-list .cphub-card:nth-child(1){animation-delay:0ms}
.cphub-list .cphub-card:nth-child(2){animation-delay:80ms}
/* ... */

/* Hover reveal */
.cphub-thumb-wrap{ position:relative; overflow:hidden }
.cphub-thumb-wrap::before{ content:''; position:absolute; inset:-20%; background:rgba(255,255,255,.15);
  transform:translateX(-120%) rotate(20deg); transition: transform .4s ease }
.cphub-card:hover .cphub-thumb-wrap::before{ transform:translateX(120%) rotate(20deg) }

@media (prefers-reduced-motion: reduce){
  .cphub-list .cphub-card{ animation:none }
  .cphub-thumb-wrap::before{ transition:none }
}
```

## Open Questions / Next Steps
- Cursor‑origin ripple for buttons (JS) vs. CSS‑only center origin.
- Optional in‑view animation trigger (IO) vs. first‑paint stagger.
- Preset animation bundles (fade/slide/scale) vs. tunable controls only.
