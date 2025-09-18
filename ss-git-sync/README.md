# SS Git Sync Suite

## Overview
This repository contains two complementary WordPress plugins that keep Smart Slider 3 projects version-controlled in Git and synchronised across multiple sites.

- `ss-git-sync-master/`: install on the content authoring site (“master”). It exports selected Smart Slider projects to `.ss3` files, commits the changes, and pushes them to a Git repository.
- `ss-git-sync-secondary/`: install on every downstream site (“secondary”). It pulls the same Git repository, deletes the previous slider instance, imports the new `.ss3` file, and stores the Smart Slider ID so updates continue to overwrite the same project.

Each plugin is self-contained—just copy the folder into `wp-content/plugins/` and activate it like any other WordPress plugin.

## Prerequisites
- WordPress 6.0+ running PHP 8.0 or newer.
- Smart Slider 3 installed and active on all sites.
- Git CLI available to the web user (the user that runs PHP/WordPress).
- SSH credentials (or HTTPS PAT) configured so the web user can push/pull the target repository.

## Directory Layout
```
ss-git-sync/
├─ ss-git-sync-master/          # master plugin
│  ├─ includes/                 # helpers, logger, git wrapper
│  ├─ src/                      # admin UI + exporter logic
│  └─ ss-git-sync-master.php    # bootstrap
├─ ss-git-sync-secondary/       # secondary plugin
│  ├─ includes/                 # helpers, logger, git wrapper
│  ├─ src/                      # admin UI + importer + cron
│  └─ ss-git-sync-secondary.php # bootstrap
├─ docs/                        # supplemental documentation
└─ README.md                    # this file
```

## Installation
1. Zip each plugin folder individually (`ss-git-sync-master`, `ss-git-sync-secondary`).
2. Upload the appropriate ZIP to the target site via WordPress **Plugins → Add New → Upload Plugin**.
3. Activate **SS Git Sync (Master)** on the master site only; activate **SS Git Sync (Secondary)** on every downstream site.
4. Ensure the `exports/` directory inside each plugin is writable by the web server user.

## Configuring the Master Plugin
Navigate to **Settings → SS Git Sync (Master)**:

1. **Repository URL** – SSH URL is recommended (e.g. `git@github.com:org/slider-sync.git`).
2. **Branch** – defaults to `main`; change if you track a different branch.
3. **Exports Directory** – defaults to the plugin’s `exports/` folder.
4. **Authentication** – choose between SSH deploy keys (recommended) or HTTPS + Personal Access Token. When HTTPS is selected, the token is stored encrypted; leave the field blank on subsequent saves to keep the existing token or tick “Clear stored token” to remove it.
5. **Project Map** – add one row per Smart Slider project using the slider alias and the desired `.ss3` filename (e.g. `homepage_hero` → `homepage_hero.ss3`).
6. Click **Save Settings**.
7. Press **Export & Push Now** to export all mapped sliders, commit the `.ss3` files, and push to the configured repository.

## Configuring the Secondary Plugin
On each downstream site, go to **Settings → SS Git Sync (Secondary)**:

1. Set **Repository URL** and **Branch** to match the master.
2. Optionally adjust the **Exports Directory** (defaults to the plugin folder).
3. Choose the authentication method (SSH deploy key or HTTPS token). HTTPS tokens are encrypted and never displayed after saving.
4. Pick a **Cron Frequency** (e.g. hourly). This schedules the importer via `ssgss_cron_sync`.
5. Recreate the same **Project Map** (alias → `.ss3` filename) used on the master site.
6. Click **Save Settings**.
7. Press **Pull & Import Now** to clone/refresh the repo, delete any existing slider with that alias, import the new `.ss3`, and cache the Smart Slider ID.

## Typical Workflow
1. Edit a Smart Slider project on the master site.
2. Run **Export & Push Now** (or let your release process call it).
3. On each secondary site, run **Pull & Import Now** (or rely on the cron event). The importer removes the previous slider and imports the new `.ss3` so no duplicates appear.
4. Repeat as needed—the importer always overwrites the same slider ID from the cached mapping.

## Cron Automation (Secondary)
- The secondary plugin schedules the `ssgss_cron_sync` hook using the selected frequency. Ensure WordPress cron is active (`DISABLE_WP_CRON` should not be true) or set up a real server cron hitting
  ```bash
  wp cron event run ssgss_cron_sync
  ```
  at the desired cadence.

## Logs & Troubleshooting
- All Git/export/import activity is appended to `wp-content/ss-git-sync.log`. Check this file first when diagnosing failures.
- If a slider isn’t updating, confirm the alias in the project map matches the Smart Slider alias exactly and that the `.ss3` file exists under the plugin’s `exports/` folder on the secondary site.
- “Cannot fast-forward to multiple branches” usually means two pulls overlapped; simply trigger another pull once.
- A missing `.ss3` file on the secondary site often indicates the project map wasn’t saved; re-enter the alias/filename and hit **Save Settings** again.

## Development Notes
- Shared utilities are duplicated inside each plugin so they can be distributed independently.
- All PHP files pass `php -l`; there are no external dependencies or composer autoloaders.
- Documentation under `docs/` provides deeper background, operations runbooks, and troubleshooting tips.

## License
This code base is provided by Lime Advertising. Use it according to your project’s licensing requirements.
