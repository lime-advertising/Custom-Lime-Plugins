# Elementor Template Sync Manager — Simple Guide

Audience: Non-technical site owners, editors, designers, account managers
Goal: Explain in plain language what this is and how to use it day-to-day.

## What This Is
- A safe way to keep Elementor templates (headers, footers, sections, popups, etc.) the same across many websites.
- You make changes once on a central “Publisher” site, and the other “Consumer” sites receive those changes automatically or when approved.
- Prevents duplicates and keeps things tidy. If something looks wrong, you can roll the change back.

## Key Ideas (in Plain Language)
- Publisher site: The “master” website where you edit templates in Elementor.
- Consumer site: Any website that receives templates from the Publisher site.
- Template: A reusable layout in Elementor (e.g., a header or call-to-action section).
- Deploy: Sending a saved template update from the Publisher to one or more Consumer sites.
- Rollback: Undoing the last change and restoring the previous version.

## What You Can Do
- Update templates once on the Publisher and re-use across many sites.
- Choose which sites get updates (all sites or just a few).
- Preview what will change before applying (dry-run/diff).
- Roll back quickly if something isn’t right.

## Before You Start
- Make sure Elementor is installed on your sites (Elementor Pro is optional; some advanced conditions require it).
- Ask your tech team to install the two plugins:
  - Publisher plugin on the master site.
  - Consumer plugin on every site that should receive templates.
- Your tech team will provide a “Publisher URL” and a “Site Token” for each Consumer site (used to enroll a site).

## Quick Setup (One-Time)
1) On each Consumer site: enroll with the Publisher
- Go to: WordPress Admin → Template Sync → Enroll.
- Paste the Publisher URL and your Site Token.
- Click Save. You should see a success message.

2) On the Publisher site: confirm your sites are listed
- Go to: WordPress Admin → Template Sync → Consumers.
- Check that each site shows as Active/Connected.

That’s it. Your sites are now connected.

## Everyday Workflow
Designers/Editors (on the Publisher site)
1) Edit the template in Elementor on the Publisher site and click Update.
2) Add a short note (changelog) describing what changed.
3) When ready, ask the admin (or use the Deploy screen) to send the update to selected sites.

Admins (on the Publisher site)
1) Go to: WordPress Admin → Template Sync → Deploy.
2) Pick the template and version to send.
3) Choose which sites should get it (all or selected).
4) Optional: Run a Dry-Run to see a quick preview.
5) Click Deploy to send.
6) Check results. If needed, use Rollback.

Site Owners (on Consumer sites)
- If your site is set to manual approval, you’ll see pending updates:
  - Go to: WordPress Admin → Template Sync → Updates.
  - Review the change summary and click Apply.
- If your site is set to auto-apply, updates install on a schedule (for example, hourly).

## Selective Rollouts
- You can send changes to all sites or just to certain sites (e.g., a specific client or region).
- This helps you test changes on a few sites before rolling out to everyone.

## Safety and Rollback
- Updates are tracked so you can restore the previous version quickly if something looks off.
- Rollback can be done from either the Publisher or the Consumer site’s History screen.

## Media and Images
- The system copies images referenced in a template to each Consumer site, so you’re not loading images from another website.
- If an image is missing or cannot be copied, you’ll get a warning so you can fix it.

## Display Conditions (Assignments)
- If enabled by your tech team, when a template is deployed the “where it shows” rules (display conditions) set on the Publisher can also be applied on each Consumer.
- Modes:
  - Replace: overwrite local assignments to match the Publisher.
  - Merge: keep local assignments and add any new ones from the Publisher.
  - Skip: do not change local assignments.
- Requirements: Elementor Pro on the Consumer sites to use Theme Builder conditions.

## Troubleshooting (Plain Language)
- I can’t enroll a site:
  - Check the Publisher URL and Site Token were typed correctly.
  - Make sure the Consumer plugin is active.
  - Ask IT to confirm the site is allowed and the token is current.
- I don’t see the new template on my Consumer site:
  - Check if your site needs manual approval (Updates screen).
  - Wait a few minutes (scheduled checks) or ask the admin to push again.
- The design looks different after an update:
  - Use Rollback to restore the previous version and contact the design team.
- Images aren’t showing:
  - The system copies images, but sometimes a link can’t be downloaded. Try the update again, or add the image manually.
- I rolled back, but I still see changes:
  - Clear your site’s cache (and any CDN cache) and refresh the page.

## Frequently Asked Questions
- Does this change page content?
  - No, it only updates Elementor templates. Your page content remains the same.
- Do I need Elementor Pro?
  - Not required. Some advanced template “display conditions” need Elementor Pro.
- Will this slow down my site?
  - No. Updates happen in the background. Finished templates work as usual.
- Can I choose which templates I receive?
  - Yes. Your admin can manage subscriptions and rollouts per site.

## Where To Click (Menus)
- Publisher: WordPress Admin → Template Sync → Templates / Deploy / Consumers
- Consumer: WordPress Admin → Template Sync → Enroll / Updates / History / Health

## Getting Help
- Internal: Contact your account manager or the web admin team.
- If you see repeated errors: copy the on-screen message and share it with support.

Note: Screens may evolve as features are added, but the general steps remain the same: edit on Publisher → choose sites → review → deploy → rollback if needed.
