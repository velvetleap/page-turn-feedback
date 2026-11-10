# Changelog

## 1.8.0 — 2026-11-10
- **Order by hand:** new page *Feedback → Order* (`includes/ordering.php`). Drag and drop (jQuery UI Sortable, bundled with WordPress) and up/down buttons for keyboard and touch; the list is sent with a normal form to `admin-post.php` (nonce, `edit_others_posts`), no AJAX. Positions are stored in the standard `menu_order` field (1, 2, 3…); `ptfb_get_items()` now sorts by `menu_order ASC, date DESC`, so a site that never used the page sees exactly the same order as before (all positions are 0), and a new feedback (position 0) comes first until the order is saved again. "Back to automatic order" sets every position to 0. Rows beyond the `ptfb_max_items` limit are marked as not shown.
- **CSV import and export:** new page *Feedback → Import and export* (`includes/tools.php`). Columns `name, comment, status, date, order` (UTF-8 with BOM, CRLF). Import reads comma, semicolon or tab separated files, UTF-8 or Windows-1252, quoted fields with line breaks and doubled quotes (own small parser, no `fgetcsv`), header names in English or Italian, at most 500 rows and 1 MB, only the temporary upload is read. Comments go through `wp_kses_post`, names through `sanitize_text_field`; unknown statuses become drafts; invalid dates are ignored; rows already present (same name and comment) are skipped, so a file can be imported twice. Cells starting with `=`, `+`, `-`, `@` get a leading apostrophe on export (and lose it on import) so spreadsheets do not run them as formulas. An example file can be downloaded. XML is left to WordPress's *Tools → Export / Import* (the post type is listed there and `menu_order` is exported).
- **Ready-made styles:** five presets (Paper, Night, Ocean, Sunset, Minimal; `includes/presets.php`) that fill in the colour, font and alignment fields of the settings form from a button row. Nothing is saved until *Save Changes*; nothing about presets is stored. Text contrast is at least 9:1 on the card and the title at least 5:1 on the band (checked by `tests/admin_pages_test.php`).
- **Live preview:** the settings page shows the real card (same markup from `ptfb_output()` in preview mode, same stylesheet and script) in a frame next to the form. Colours, fonts and alignment are applied without reloading, so the page keeps turning; title, sliding time and page-turn duration reload the frame. The CSS the script builds is checked against `ptfb_inline_css()` in `tests/admin_features_test.py`, which compares what the preview and the site draw for every ready-made style.
- `ptfb_output()` and `ptfb_render()` take optional `$items` and `$preview` arguments; the front-end output is unchanged.
- The admin-only files are loaded only in the dashboard. `bin/i18n.py` rebuilds `.pot`, `.po` and `.mo` from all PHP files.
- No identifier was renamed, no option was added or removed, front-end markup and CSS are unchanged: data saved by earlier versions is kept.

## 1.7.1 — 2026-11-07
- **Long words and addresses:** a comment or a name with no spaces (for example a long web address) now breaks onto the next line inside the card. Before, the card's grid column grew to the width of the unbroken word, the text ran past the card, was cut off by its rounded edge and pushed the name out of place (clearly visible with centered text). The card column now has a fixed width (`minmax(0, 1fr)`), the pages can shrink (`min-width: 0`), and the comment, the name and the section title use `overflow-wrap: anywhere` with `word-break: break-word` as a fallback for older Safari.
- Checked in Chromium with the text and name aligned left, center and right, at 1280, 1000, 768, 600, 390 and 320 px: no overflow.
- No identifier was renamed and no string changed.

## 1.7.0 — 2026-11-07
- **Block themes:** the automatic placement now works on them too. Block themes never call `get_footer()`, so the section is printed through the `render_block` filter right before the footer template part (same conditions and same `ptfb_auto_insert` filter as before). Previously the assets were loaded but nothing was shown (found testing on WordPress Playground with Twenty Twenty-Five).
- **Container:** the default container class is `fb-container` only on Fascinate (and its child themes); on every other theme the card is wrapped in the plugin's own `ptfb__container` (centered, `max-width: 72rem`, side padding), so it no longer touches the screen edges. The `ptfb_container_class` filter still overrides it. Markup on Fascinate is byte-identical to 1.6.0.
- **Folded corner:** `background`, `border` and `box-shadow` of the corner button are now `!important`; themes such as Twenty Twenty-One paint every `button:not(:hover)` with a more specific rule and the corner showed as a dark square.
- `readme.txt`: troubleshooting FAQ for "the section does not appear", tested themes listed.
- No identifier was renamed and no string changed: data saved by earlier versions is kept and the Italian translation is unaffected.

## 1.6.0 — 2026-11-06
- The plugin is now translatable: all strings are in English with the `page-turn-feedback` text domain, and an Italian translation (it_IT, `.po` and `.mo`) plus a `.pot` template are bundled in `languages/`. The default section title is "What people say about me" ("Cosa dicono di me" in Italian). A title saved in the settings is kept as it is.
- The two inline `<script>` blocks (live character counter in the editor, show/hide of the "custom" fields and color picker on the settings page) are now `assets/page-turn-feedback-admin.js`, enqueued only on the feedback edit screen and on the settings page. The counter strings are passed from PHP.
- Output escaping: the section is now printed by `ptfb_output()` with every value escaped where it is printed; `ptfb_render()` (used by the shortcode) wraps it in an output buffer. No more `phpcs:ignore` comments.
- Code comments in PHP, JS and CSS are now in English.
- `readme.txt`: *Tested up to* 7.1.
- No identifier was renamed: data saved by 1.5.0 is kept.

## 1.5.0 — 2026-11-04
- Renamed from *LSA Feedback Slider* to **Page Turn Feedback**. The plugin author is now Velvet Leap; the copyright is unchanged (Mattia Scudellaro).
- All technical identifiers were renamed, so a site running 1.4.0 must note its feedback and settings, remove the old plugin and enter them again (data saved by 1.4.0 is not migrated):
  - folder and main file: `page-turn-feedback`
  - post type `lsa_feedback` → `ptfb_feedback`; options `lsa_fb_*` → `ptfb_*`
  - shortcode `[lsa_feedback]` → `[page_turn_feedback]`
  - filters `lsa_fb_container_class`, `lsa_fb_auto_insert`, `lsa_fb_max_items` → `ptfb_container_class`, `ptfb_auto_insert`, `ptfb_max_items`
  - CSS prefix `lsa-fb` → `ptfb` (classes and `--ptfb-*` variables)
- Added `readme.txt` with search tags: testimonials, feedback, quotes, reviews, slider.
- Removed the upgrade fallback from the 1.1 interval option: it is not needed with the new identifiers.

## 1.4.0 — 2026-10-29
- The keyboard focus indicator on the folded corner is now shown only to keyboard users (no outline after a mouse click or a tap).
- New settings: text alignment for the comment and for the author name (left / center / right).
- New setting: duration of the page-turn animation (0.6 s, 1.1 s default, 1.8 s, 2.6 s or custom 0.3–5 s).
- Editor: recommendation to keep comments under 300 characters, with a live character counter.

## 1.3.0 — 2026-10-28
- The 3D rotation is replaced by a real paper fold: the page folds from the bottom-right corner across the card and reveals the next one.
- Folded corner in the bottom-right: click / tap to turn the page, hover to enlarge it, drag it to turn by hand.
- New settings: colours (6 colour pickers) and fonts (title, comment, author name).

## 1.0 – 1.2 — October 2026
- Initial releases: "Feedback" post type (name = title, comment = text), one card at a time, automatic advance with selectable time, press-and-hold to pause and drag to the previous/next comment, keyboard support, `prefers-reduced-motion` support, `[lsa_feedback]` shortcode, automatic placement above the footer on the home page.
