# Page Turn Feedback

A WordPress plugin that shows feedback / testimonials / quotes **one at a time on a card that turns like the page of a book**: the page folds from a dog-eared bottom-right corner and reveals the next one.

Built for a book blog, with a non-technical editor in mind: adding, editing and removing a feedback is just "name + comment" in the WordPress admin.

> **Status:** 1.5.0, first public release. Tested in Chromium only (desktop and phone viewport). The admin screens have not yet been tested across WordPress versions. Bug reports and pull requests are welcome.

**Keywords:** testimonials, feedback, quotes, reviews, slider, page turn, book.

The plugin interface and the user guide ([`LEGGIMI.txt`](LEGGIMI.txt)) are in **Italian**. Un'interfaccia e una guida in italiano sono incluse.

## Features

- **"Feedback" menu** in the admin: name = title, comment = text. Publish / draft / trash like any post.
- **Page-turn effect** made of a real fold (clipped polygons + paper shading), not a 3D rotation. No libraries, no images.
- **Folded corner** in the bottom-right: click or tap to go next, hover to enlarge, drag to turn by hand.
- **Press and hold** on the text to pause autoplay and drag left (next) / right (previous); autoplay resumes on release.
- **Settings** (Feedback → Settings): section title, autoplay time (3 / 5 / 10 s, custom 2–120 s, or off), page-turn duration, six colours, fonts for title / comment / name, alignment of comment and name.
- **Accessible**: keyboard (Tab, Enter, arrow keys), carousel semantics with `aria-live`, focus indicator for keyboard users only, `prefers-reduced-motion` support (no autoplay, instant swap).
- **Zero dependencies and no external requests** (no CDN, no fonts, no tracking).
- Uses the theme's heading and body fonts by default; colours default to a green `#a5cca7` band and white card.

## Installation

1. Download the plugin zip (see *Releases*), or zip the repository content in a folder named `page-turn-feedback`.
2. WordPress admin → Plugins → Add New → Upload Plugin → Activate.
3. Menu **Feedback → Add feedback**. The section appears automatically at the bottom of the home page, above the footer.

To show it elsewhere, use the shortcode `[page_turn_feedback]`.

## Developer notes

| What | Name |
| --- | --- |
| Post type | `ptfb_feedback` |
| Options | `ptfb_title`, `ptfb_speed`, `ptfb_custom`, `ptfb_anim`, `ptfb_anim_custom`, `ptfb_style` |
| Shortcode | `[page_turn_feedback]` |
| Filter | `ptfb_container_class` (default `fb-container`, the Fascinate theme container) |
| Filter | `ptfb_auto_insert` (return `false` to disable automatic placement on the home page) |
| Filter | `ptfb_max_items` (default `30`) |
| CSS variables | `--ptfb-bg`, `--ptfb-title`, `--ptfb-card`, `--ptfb-text`, `--ptfb-name`, `--ptfb-accent`, `--ptfb-focus`, `--ptfb-radius` |

The fold geometry lives in `assets/page-turn-feedback.js`: the bottom-right corner is pulled to a point P; the crease is the perpendicular bisector of the corner and P; the part of the page beyond the crease is reflected over it.

Uninstalling the plugin deletes its options; the feedback posts stay in the database.

## Known limitations / ideas

- Tested in Chromium only: Safari (WebKit) and Firefox need checking.
- Single global list and single global style (no groups or per-instance options yet).
- Strings are hard-coded in Italian (not yet translation-ready).
- Not yet tested against specific WordPress / PHP versions (uses `wp_unique_id`, WordPress 5.0.3+).
- Ideas: groups/categories, image or book cover per card, front-end submission form with moderation, block editor block.

## License

Plugin by **Velvet Leap**.

Copyright (c) 2026 Mattia Scudellaro.

Licensed under the GPL-2.0-or-later. See [`LICENSE`](LICENSE).
