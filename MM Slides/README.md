# MM Slides — Publisher & Consumer WordPress Plugins

MM Slides is a two‑plugin system that lets you centrally manage hero slides on a “publisher” WordPress site and automatically render them as a performant animated slider on one or more “consumer” WordPress sites.

- Publisher: defines the custom post type, fields, taxonomy, admin styling UI, and exposes a consumable RSS feed with slide data and global style variables.
- Consumer: fetches the remote feed on a schedule, caches it as options, enqueues slider assets, and renders the markup via a shortcode.


## Components

- `mm-slides-publisher/`
  - `mm-slides-publisher.php`: Registers CPT `mm_slide`, taxonomy `mm_location`, meta fields, “Settings” UI (tabbed), and the `feed/mm-slides` endpoint that exports slides and CSS variables.
  - `assets/mm-slider.js`, `assets/mm-slider.css`: Reference slider assets you can publish to a CDN and reference from consumer sites.

- `mm-slides-consumer/`
  - `mm-slides-consumer.php`: Settings page to enter the Publisher feed URL; fetches and caches slides; registers `[mm_remote_slider]` shortcode; enqueues slider assets (remote or local fallback); initializes the slider.
  - `assets/mm-slider.js`, `assets/mm-slider.css`: Local fallback slider assets (used when remote assets are not configured).

Versions at time of writing: Publisher v1.4.0, Consumer v1.2.0


## How It Works

1) On the Publisher site, you create “MM Slides” posts (title, description, featured image) and fill optional meta (subtitle, active flag, buttons). You can assign an optional `MM Locations` term to target different consumer sites.

2) The Publisher exposes `https://publisher.tld/feed/mm-slides` (optionally `?location=slug`) as a standard RSS feed with two custom namespaced nodes:
- `mm:vars`: JSON object of global style variables and settings (CSS custom properties and a few scalar flags like `mm-assets-base`).
- `mm:data`: Per‑slide JSON with subtitle, buttons, active flag, background image URL.

3) On the Consumer site, you paste the feed URL into Settings → “MM Remote Slides”. The Consumer fetches the feed hourly (and on demand), caches it in `wp_options`, and renders slides with the `[mm_remote_slider]` shortcode.


## Publisher Setup

1) Install and activate `mm-slides-publisher` on the master WordPress site.
- CPT: “MM Slides” (`mm_slide`) supports Title, Editor, Featured Image, Custom Fields.
- Taxonomy: “MM Locations” (`mm_location`) for targeting feeds per location/site.

2) Create slides under “MM Slides”. For each slide:
- Title: main headline.
- Content (Editor): rich description (HTML allowed; output is sanitized in the feed).
- Featured Image: used as the slide background.
- “Slide Details” box:
  - Subtitle: optional text shown above the title.
  - Active?: mark a slide as initially active. The first slide marked “Yes” appears active on load.
  - Button 1 Text/URL and Button 2 Text/URL.

3) Global Styles (tabbed Settings UI under MM Slides → Settings):
- General: overlay color, alignment, text alignment, slider height, content padding, animation preset, and optional remote asset settings.
- Title / Subtitle / Description: color, font family, size, line‑height, weight, letter‑spacing, margins, max‑width, plus optional tablet/mobile overrides.
- Button 1 and Button 2: background, text, border, radius, padding, box‑shadow and hover states, plus optional tablet/mobile padding overrides.

Notes:
- Fonts: the UI lets you select from common stacks or specify a custom stack. The plugin does not load webfonts for you — ensure the family is available on your site.
- Responsive: any “md/sm” fields in the UI are optional overrides for ≤1024px and ≤640px. If left blank, CSS falls back to the desktop value.
- Remote Assets: if you will host slider assets on a CDN, fill:
  - “Assets base URL (JS/CSS)” → e.g. `https://cdn.example.com/mm-slider`
  - “Assets version for cache‑busting” → appended as `?v=` to the URLs


## Publisher Feed

- Endpoint: `https://publisher.tld/feed/mm-slides`
- Optional query: `?location=<slug>` to filter slides by `mm_location` term.
- XML namespace: `xmlns:mm="https://example.com/mm"`

Channel exports a `mm:vars` CDATA node with JSON of global variables, for example:

```
{
  "mm-assets-base": "https://cdn.example.com/mm-slider",
  "mm-assets-ver": "1.0.3",
  "mm-anim": "reveal",
  "--mm-slider-height": "700px",
  "--mm-overlay": "rgba(0,0,0,0)",
  "--mm-title-size": "90px",
  "--mm-btn1-bg": "#ff6a00",
  ...
}
```

Each `<item>` includes a `mm:data` CDATA node with per‑slide JSON:

```
{
  "subtitle": "Your subtitle",
  "active": "yes|no",
  "btn1_text": "Learn more",
  "btn1_url": "https://...",
  "btn2_text": "More",
  "btn2_url": "https://...",
  "bg_image": "https://.../image.jpg"
}
```


## Consumer Setup

1) Install and activate `mm-slides-consumer` on the target WordPress site.

2) Go to Settings → “MM Remote Slides” and paste the Publisher feed URL, e.g.:
- `https://publisher.tld/feed/mm-slides?location=my-site`

3) Click “Save Changes”. Optionally click “Fetch Now” to immediately pull and cache slides.

4) Place the shortcode where you want the slider:

```
[mm_remote_slider]
```

Shortcode attributes:
- `autoplay`: `yes|no` (default `yes`).
- `speed`: slide interval in ms (default `9000`).
- `style`: wrapper style flag; used in the outer class `slider-<style>`. Common values include `full-width` or `full-screen` if your theme uses those.
- `debug`: `1` to render a debug note when no slides are cached.

Example:

```
[mm_remote_slider style="full-screen" autoplay="yes" speed="8000"]
```


## Assets Loading (Remote vs Local)

The Consumer will load slider JS/CSS in this order:

1) If the feed’s `mm:vars` contains `mm-assets-base`, the Consumer enqueues:
- JS: `<base>/mm-slider.js` (with `?v=<mm-assets-ver>` if provided)
- CSS: `<base>/mm-slider.css` (with `?v=<mm-assets-ver>` if provided)
- It also tries to dequeue/deregister any theme slider scripts to prevent duplicates.

2) If no remote base is provided, but the Consumer plugin has local assets at `assets/mm-slider.*`, it enqueues those and similarly disables theme slider scripts if detected.

3) If neither remote nor local plugin assets are available, the Consumer will try to reuse a theme‑registered slider script/style if it can find one with “slider” in the handle/src. If it cannot, it bails without initialization.

Dependency scripts auto‑registered on the Consumer:
- `gsap` (from jsDelivr), `jquery-touchswipe`, and `splitting` are registered if not already on the page.

Developer filter on the Consumer:
- `mm_use_remote_assets` (bool): override whether to prefer remote assets from the feed.


## Markup & Styling

The Consumer renders a single wrapper with synchronized backgrounds and content:

```
<div class="slider-<style>">
  <div class="master-slider" style="<inline CSS variables>" data-config='{"..."}'>
    <div class="bg-wrap">
      <div class="bg active" style="background-image:url(...)""></div>
      ...
      <div class="mm-overlay"></div>
    </div>
    <div class="content-wrap">
      <div class="slide active">
        <div class="sub-title">...</div>
        <h1 class="title">...</h1>
        <div class="desc">...</div>
        <div class="url-wrap">
          <div class="slide-url url1"><a class="master-button big" href="...">...</a></div>
          <div class="slide-url url2"><a class="master-link" href="...">...</a></div>
        </div>
      </div>
      ...
    </div>
    <div class="control-wrap">
      <div class="nav-arrow"><div class="arrow arrow-prev"></div><div class="arrow arrow-next"></div></div>
      <div class="nav-dots"></div>
    </div>
  </div>
</div>
```

Global styles are applied via inline CSS custom properties coming from the Publisher feed (`mm:vars`). The default stylesheet (`mm-slider.css`) consumes variables like:

- General: `--mm-slider-height`, `--mm-overlay`, `--mm-align-v`, `--mm-align-h`, `--mm-text-align`, `--mm-content-pad` (+ md/sm variants).
- Title: `--mm-title-color`, `--mm-title-font`, `--mm-title-size`, `--mm-title-line`, `--mm-title-weight`, `--mm-title-tracking`, `--mm-title-margin`, `--mm-title-max` (+ md/sm).
- Subtitle and Description: analogous variables under `--mm-sub-*` and `--mm-desc-*` (+ md/sm).
- Buttons:
  - Button 1 (primary): `--mm-btn1-bg`, `--mm-btn1-text`, `--mm-btn1-border`, `--mm-btn1-radius`, `--mm-btn1-pad`, `--mm-btn1-shadow` and their `-hover`/`-md`/`-sm` variants.
  - Button 2 (link/secondary): `--mm-btn2-*` parallel to Button 1.


## Animations & Behavior

- Animation preset: the feed variable `mm-anim` can be `reveal` (default) or `fade`, changing how backgrounds and content transition.
- Autoplay: shortcode `autoplay` and `speed` control playback. Hover pauses autoplay.
- Navigation: next/prev arrows, dots, and touch swipe (when `jquery.touchSwipe` is available).
- Ken Burns: defaults enabled in JS with mild zoom; controlled by internal config inside the script.


## Scheduling & Caching (Consumer)

- The Consumer schedules an hourly cron event (`mm_fetch_remote_slides`) at init to fetch the feed.
- Manual fetch: on the settings page, “Fetch Now” runs immediately.
- Cached option: `mm_remote_slides_cache` stores `updated` timestamp, `slides` array, and `vars` map.
- Feed URL option: `mm_remote_slides_feed`.


## Troubleshooting

- No slides appear:
  - Verify the Consumer settings Feed URL is correct and loads in a browser.
  - Ensure the Publisher has published `MM Slides` with Featured Images.
  - If using `?location=...`, confirm slides are assigned that `MM Locations` term.
  - If theme bundles its own slider, the Consumer tries to dequeue it; ensure no JS conflicts remain.

- Styles not applying:
  - Confirm the Publisher Settings have values saved and the feed’s `mm:vars` contains the expected CSS variables.
  - If using remote assets, confirm `mm-assets-base` and `mm-assets-ver` are present in the feed and reachable.

- Asset conflicts:
  - Use the `mm_use_remote_assets` filter on the Consumer to force local/remote choice.
  - Check console for 404s on `mm-slider.js`/`.css` or missing dependencies.


## Developer Notes

- Feed namespace: `mm` mapped to `https://example.com/mm` (informational).
- Consumer filter: `mm_use_remote_assets` to override using remote assets from `mm:vars`.
- Security:
  - Publisher sanitizes style settings and slide HTML is escaped in the feed inside CDATA.
  - Consumer sanitizes feed URL, strips unexpected characters from CSS variable values, and uses CDATA JSON parsing.
- Extending styles: you can ship the Publisher’s `assets/mm-slider.*` to your CDN and point `Assets base URL` at that directory; or bundle custom variants and use that URL instead.


## Quick Start Summary

1) Install Publisher on the master site. Create slides and set global styles. Copy the feed URL.
2) Install Consumer on the target site. Paste the feed URL in Settings → MM Remote Slides.
3) Add `[mm_remote_slider]` in a page/template. Optionally set `style`, `autoplay`, and `speed`.
4) If desired, host `mm-slider.js`/`.css` on a CDN and set “Assets base URL” and “Assets version” in Publisher settings so all consumers automatically load that build.

