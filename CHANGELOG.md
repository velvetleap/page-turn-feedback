# Changelog

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
