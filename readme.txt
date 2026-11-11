=== Page Turn Feedback ===
Contributors: matthewskud
Tags: testimonials, feedback, quotes, reviews, slider
Requires at least: 5.0.3
Tested up to: 7.1
Stable tag: 1.8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Show feedback, testimonials and quotes one at a time on a card that turns like the page of a book.

== Description ==

Page Turn Feedback shows testimonials, reader feedback, reviews or quotes **one at a time on a card that turns like the page of a book**: the page folds from a dog-eared bottom-right corner and reveals the next one.

It is built for sites run by non-technical editors: adding, editing and removing a feedback is just "name + comment" in the WordPress admin.

**Languages:** the plugin is written in English and includes a complete Italian translation (it_IT), which is used automatically when the site language is Italian. The extended user guide (`LEGGIMI.txt`) is in Italian.

= Features =

* **"Feedback" menu** in the admin: name = title, comment = text. Publish, draft or trash like any post.
* **Page-turn effect** made of a real fold (clipped polygons and paper shading), not a 3D rotation. No libraries, no images.
* **Folded corner** in the bottom-right: click or tap to go to the next one, hover to enlarge it, drag it to turn the page by hand.
* **Press and hold** on the text to pause autoplay and drag left (next) or right (previous). Autoplay resumes on release.
* **Settings** (Feedback > Settings): section title, autoplay time (3, 5 or 10 s, custom 2-120 s, or off), page-turn duration, six colours, fonts for title, comment and name, alignment of comment and name.
* **Live preview** on the settings page: the real card, updated as you change a setting, with the page turning by itself.
* **Five ready-made styles** (Paper, Night, Ocean, Sunset, Minimal): one click fills in colours, fonts and alignment, which you can still adjust before saving.
* **Order by hand** (Feedback > Order): drag the feedback, or use the arrows, to choose the order they are shown in.
* **Import and export** (Feedback > Import and export): CSV file that opens in Excel, Numbers or Google Sheets, also useful as a backup. For an XML file use WordPress's own Tools > Export and Import.
* **Accessible**: keyboard support (Tab, Enter, arrow keys), carousel semantics with `aria-live`, focus indicator for keyboard users only, `prefers-reduced-motion` support (no autoplay, instant swap).
* **No dependencies and no external requests**: no CDN, no external fonts, no tracking.
* Uses the theme's heading and body fonts by default.
* **Works with classic and block themes**: it is shown automatically before the footer, in its own centered container with side margins.

= Status =

Tested on WordPress 7.1.4 with Twenty Twenty-One, Twenty Twenty, Twenty Nineteen, Twenty Seventeen, Twenty Twenty-Two, Twenty Twenty-Three, Twenty Twenty-Four and Twenty Twenty-Five, and running on a live site with the Fascinate theme. The front end has been tested in Chromium only (desktop and phone viewport); Safari and Firefox still need checking. Older WordPress versions are not tested. Bug reports and pull requests are welcome on GitHub.

== Source code ==

The full source code is public on GitHub: https://github.com/velvetleap/page-turn-feedback

== Installation ==

1. Upload the `page-turn-feedback` folder to `/wp-content/plugins/`, or use Plugins > Add New > Upload Plugin.
2. Activate the plugin.
3. Go to **Feedback > Add feedback** and publish at least one. The section appears automatically at the bottom of the home page, above the footer.

To show it elsewhere, use the shortcode `[page_turn_feedback]`.

== Frequently Asked Questions ==

= The section does not appear on my home page. What should I check? =

1. At least one feedback must be **published** (not a draft).
2. Automatic placement is only on the home page (the blog page or the static front page), not on page 2 and following.
3. The theme needs a footer: classic themes through `get_footer()`, block themes through the footer template part. If your theme has neither, or you removed it, use the shortcode `[page_turn_feedback]` (in a block theme, add a Shortcode block).
4. Some plugins or snippets can turn the automatic placement off with the `ptfb_auto_insert` filter.

= How do I show it on another page? =

Insert the shortcode `[page_turn_feedback]`.

= How do I turn off the automatic placement on the home page? =

Add this to your child theme's `functions.php`:

`add_filter( 'ptfb_auto_insert', '__return_false' );`

= Can I translate it into my language? =

Yes. Every string uses the `page-turn-feedback` text domain and the `languages/page-turn-feedback.pot` template is included. An Italian translation (it_IT) is bundled.

= How do I choose the order of the feedback? =

Go to **Feedback > Order**, drag the rows (or use the arrows) and press **Save order**. The site shows the feedback from top to bottom. Until you save an order, the newest feedback comes first, as before. A feedback added after you saved the order is shown first until you save the order again. "Back to automatic order" restores newest-first.

= How do I import or export my feedback? =

Go to **Feedback > Import and export**. The CSV file has one feedback per row with the columns `name`, `comment` and, optionally, `status` (publish or draft), `date` (2026-11-30) and `order`. Only `comment` is required. You can download an example file from that page. Commas and semicolons both work, as does a file saved by Excel. Feedback already on the site (same name and same comment) is skipped, so importing the same file twice does no harm. For an XML file (to move the feedback to another WordPress site) use WordPress's own Tools > Export, choosing "Feedback", and Tools > Import > WordPress on the other site.

= Can I change the look with one click? =

Yes. On **Feedback > Settings** choose one of the five ready-made styles: it fills in the colours, fonts and alignment, and the preview next to the form shows the result. Nothing changes on the site until you press **Save Changes**, and every detail can still be adjusted. The preview uses a plain font wherever "Default (theme font)" is chosen, because your theme is not loaded there: on the site the theme font is used.

= What happens to my feedback if I uninstall the plugin? =

The settings are deleted. The feedback posts stay in the database.

== Changelog ==

= 1.8.1 =
* Lighter: on pages that do not show the card (every page except the home page and the pages that use the shortcode) the plugin no longer asks the database for the feedback and loads nothing. On the home page one query fewer.
* Lighter: the dashboard-only parts of the plugin are no longer loaded on admin-ajax.php requests, which also serve visitors for other plugins.
* Block themes: the automatic placement now runs only for the footer template part, not for every block of the page.
* Safer: a font name typed by hand with a quote that is never closed no longer breaks the CSS that follows it; moving, resetting and importing feedback also respect the user's own permissions (custom roles).
* No change to the identifiers, to the front-end markup, to the strings or to the settings saved by earlier versions.

= 1.8.0 =
* New: Feedback > Order, to put the feedback in the order you like by dragging them (or with the arrows). The order is kept in the standard "menu order" field of each feedback; until you save an order the newest feedback is shown first, as before.
* New: Feedback > Import and export. CSV export and import (Excel, Numbers and Google Sheets compatible, semicolons and Excel's encoding understood, already-present feedback skipped, spreadsheet formulas neutralised). XML export and import are done with WordPress's own Tools > Export / Import, where "Feedback" is listed.
* New: live preview on the settings page, next to the form.
* New: five ready-made styles (Paper, Night, Ocean, Sunset, Minimal) that fill in colours, fonts and alignment, with strong text contrast. Nothing is saved until you press Save Changes.
* No change to the identifiers, to the front-end markup or to the settings saved by earlier versions. The new admin pages use no AJAX and no remote requests.

= 1.7.1 =
* Very long words and web addresses (a comment or a name with no spaces) now break onto the next line inside the card. Before, the text could run past the card, be cut off at its edge and push the name out of place.
* No change to the identifiers or to the strings, so settings, feedback and the Italian translation are unaffected.

= 1.7.0 =
* Block themes (Twenty Twenty-Five and similar): the section is now shown automatically, right before the footer. Before, only classic themes showed it.
* On every theme other than Fascinate the card now sits in its own centered container with side margins (before, it touched the edges of the screen).
* The folded corner is no longer painted by the button styles of the theme (it appeared as a dark square on some themes).
* No change to the identifiers, so settings and feedback saved by earlier versions are kept.

= 1.6.0 =
* The plugin is now translatable: all strings are in English and an Italian translation (it_IT) is included. The default section title is "What people say about me" ("Cosa dicono di me" in Italian).
* The admin scripts (character counter in the editor, settings page) are now a separate file, `assets/page-turn-feedback-admin.js`, loaded only on the feedback edit screen and on the settings page.
* Every value is escaped where it is printed.
* Code comments are now in English.
* No change to the identifiers, so settings and feedback saved by 1.5.0 are kept.

= 1.5.0 =
* The plugin is now called Page Turn Feedback (it was LSA Feedback Slider) and its author is Velvet Leap.
* All technical identifiers were renamed: post type `ptfb_feedback`, options `ptfb_*`, shortcode `[page_turn_feedback]`, filters `ptfb_container_class`, `ptfb_auto_insert` and `ptfb_max_items`, CSS prefix `ptfb`. Data saved by 1.4.0 is not migrated.

= 1.4.0 =
* The keyboard focus indicator on the folded corner is shown only to keyboard users.
* New settings: text alignment for the comment and for the author name.
* New setting: duration of the page-turn animation.
* Editor: recommendation to keep comments under 300 characters, with a live character counter.
