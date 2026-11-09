# Page Turn Feedback

A WordPress plugin that shows feedback / testimonials / quotes **one at a time on a card that turns like the page of a book**: the page folds from a dog-eared bottom-right corner and reveals the next one.

Built for a book blog, with a non-technical editor in mind: adding, editing and removing a feedback is just "name + comment" in the WordPress admin.

> **Status:** 1.8.0. Tested on WordPress 7.1.4 with Twenty Twenty-One, Twenty Twenty, Twenty Nineteen, Twenty Seventeen and the block themes Twenty Twenty-Two to Twenty Twenty-Five, and running on a live site with the Fascinate theme. The front end has been tested in Chromium only (desktop and phone viewport); Safari and Firefox still need checking. Older WordPress versions are not tested. Bug reports and pull requests are welcome.

**Keywords:** testimonials, feedback, quotes, reviews, slider, page turn, book.

The interface is in **English** and a complete **Italian translation** (it_IT) is included, used automatically when the site language is Italian. The extended user guide ([`LEGGIMI.txt`](LEGGIMI.txt)) is in Italian. / L'interfaccia è in inglese con traduzione italiana inclusa; la guida estesa è in italiano.

## Features

- **"Feedback" menu** in the admin: name = title, comment = text. Publish / draft / trash like any post.
- **Page-turn effect** made of a real fold (clipped polygons + paper shading), not a 3D rotation. No libraries, no images.
- **Folded corner** in the bottom-right: click or tap to go next, hover to enlarge, drag to turn by hand.
- **Press and hold** on the text to pause autoplay and drag left (next) / right (previous); autoplay resumes on release.
- **Settings** (Feedback → Settings): section title, autoplay time (3 / 5 / 10 s, custom 2–120 s, or off), page-turn duration, six colours, fonts for title / comment / name, alignment of comment and name.
- **Live preview** next to the settings form (the real card, same markup, stylesheet and script) and **five ready-made styles** (Paper, Night, Ocean, Sunset, Minimal) that fill in colours, fonts and alignment.
- **Order by hand** (Feedback → Order): drag and drop or arrow buttons; stored in `menu_order`, so nothing to migrate.
- **CSV import and export** (Feedback → Import and export); XML through WordPress's own Tools → Export / Import.
- **Accessible**: keyboard (Tab, Enter, arrow keys), carousel semantics with `aria-live`, focus indicator for keyboard users only, `prefers-reduced-motion` support (no autoplay, instant swap).
- **Zero dependencies and no external requests** (no CDN, no fonts, no tracking).
- Uses the theme's heading and body fonts by default; colours default to a green `#a5cca7` band and white card.

## Installation

1. Download the plugin zip (see *Releases*), or zip the repository content in a folder named `page-turn-feedback`.
2. WordPress admin → Plugins → Add New → Upload Plugin → Activate.
3. Menu **Feedback → Add feedback** (publish at least one). The section appears automatically at the bottom of the home page, above the footer: on classic themes through `get_footer()`, on block themes right before the footer template part. On any theme you can use the shortcode instead.

To show it elsewhere, use the shortcode `[page_turn_feedback]`.

## Developer notes

| What | Name |
| --- | --- |
| Post type | `ptfb_feedback` |
| Options | `ptfb_title`, `ptfb_speed`, `ptfb_custom`, `ptfb_anim`, `ptfb_anim_custom`, `ptfb_style` |
| Shortcode | `[page_turn_feedback]` |
| Filter | `ptfb_container_class` (default: `fb-container` on the Fascinate theme and its child themes, otherwise the plugin's own centered container `ptfb__container`; return `''` for no class) |
| Filter | `ptfb_auto_insert` (return `false` to disable automatic placement on the home page) |
| Filter | `ptfb_max_items` (default `30`) |
| CSS variables | `--ptfb-bg`, `--ptfb-title`, `--ptfb-card`, `--ptfb-text`, `--ptfb-name`, `--ptfb-accent`, `--ptfb-focus`, `--ptfb-radius` |

The fold geometry lives in `assets/page-turn-feedback.js`: the bottom-right corner is pulled to a point P; the crease is the perpendicular bisector of the corner and P; the part of the page beyond the crease is reflected over it.

Uninstalling the plugin deletes its options; the feedback posts stay in the database.

## Translations

All strings use the text domain `page-turn-feedback`. [`languages/page-turn-feedback.pot`](languages/page-turn-feedback.pot) is the template, and the Italian translation lives in `languages/page-turn-feedback-it_IT.po` (compiled to `.mo`). To add a language, copy the `.pot`, translate it (for example with Poedit) and save it as `page-turn-feedback-<locale>.po` / `.mo` in the same folder.

## Development

Changes go through a pull request. Every pull request and every change to `main` is checked automatically by GitHub Actions (`.github/workflows/ci.yml`):

- PHP syntax, the WordPress coding standards (PHPCS, including security and escaping) and a check that the version number is the same in the plugin header, `PTFB_VERSION`, `readme.txt`, `CHANGELOG.md`, `LEGGIMI.txt` and the translation files (`bin/check-versions.sh`);
- Plugin Check (the same tool as the WordPress.org review), run on the exact folder that will be published;
- the browser and logic tests in `tests/` (`bash tests/run.sh`; needs PHP, Node and Python with Playwright and Chromium).

`bin/build.sh` builds the plugin folder and zip (`build/page-turn-feedback.zip`) from the tracked files, leaving out what `.distignore` lists. Publishing a GitHub release tagged `vX.Y.Z` builds the zip again and attaches it to the release (`.github/workflows/release.yml`). Working notes for contributors, human or AI, are in [`CLAUDE.md`](CLAUDE.md).

## Known limitations / ideas

- Tested in Chromium only: Safari (WebKit) and Firefox need checking.
- One list and one style for the whole site, by design (no groups, extra fields or per-instance options).
- Declares WordPress 5.0.3+ (it uses `wp_unique_id`) but has only been run on WordPress 7.1.4; older versions are untested.

## License

Plugin by **Velvet Leap**.

Copyright (c) 2026 Mattia Scudellaro.

Licensed under the GPL-2.0-or-later. See [`LICENSE`](LICENSE).
