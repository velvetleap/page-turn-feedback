=== Velvet Leap Page Turn Feedback ===
Contributors: matthewskud
Tags: testimonials, feedback, quotes, reviews, slider
Requires at least: 5.1
Tested up to: 7.1
Stable tag: 1.9.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Show feedback, testimonials and quotes one at a time on a card that turns like the page of a book.

== Description ==

Velvet Leap Page Turn Feedback shows testimonials, reader feedback, reviews or quotes **one at a time on a card that turns like the page of a book**. The page folds from a dog-eared corner at the bottom right and reveals the next one. It is a real fold, drawn with clipped shapes and paper shading: no 3D tricks, no images, no libraries.

It is made for sites run by people who are not developers. Adding, editing or removing a feedback is just "name + comment", like writing a post, and the card appears by itself at the bottom of your home page, above the footer.

= A page that really turns =

* **Folded corner** at the bottom right: click or tap it to go to the next feedback, hover to make it bigger, or drag it to turn the page by hand.
* **Press and hold** on the text to pause the sliding, then drag left for the next feedback or right for the previous one. The sliding starts again when you let go.
* **Automatic sliding** every 3, 5 or 10 seconds, or any time from 2 to 120 seconds, or switched off.
* **Page-turn speed** from quick (0.6 s) to slow (2.6 s), or any value from 0.3 to 5 seconds.
* **No dots, numbers or arrows** on the card to get in the way: just the card and its corner.

= Easy for the person who edits the site =

* **A "Feedback" menu** in the dashboard: the title is the name, the text is the comment. Publish it, keep it as a draft or move it to the trash, like any post.
* **A friendly tip** in the editor to keep each comment under 300 characters, with a live character counter.
* **Order by hand** (Feedback > Order): drag the feedback, or use the arrows, to choose the order in which they are shown.
* **Import and export** (Feedback > Import and export): a CSV file that opens in Excel, Numbers or Google Sheets. It is a quick way to add many feedback at once, and a handy backup. For an XML file use WordPress's own Tools > Export and Import.

= Make it look like your site =

* **Live preview** on the settings page: the real card, updated as you change a setting, with the page turning by itself.
* **Five ready-made styles** (Paper, Night, Ocean, Sunset, Minimal): one click fills in colours, fonts and alignment, and you can still adjust everything before you save.
* **Six colours**: the band behind the card, the section title, the card, the comment, the author name and the accent (quotation marks, dash and folded corner).
* **Fonts** for the title, the comment and the author name: the heading or body font of your theme (the default, so the card matches your site), a classic family such as Georgia or Palatino, or any font name you type.
* **Alignment** of the comment and of the author name: left, centre or right.
* **Section title** that you can edit, or leave empty to hide it. The default is "What people say about me".

= Light, accessible and private =

* **Light.** On a page that does not show the card, the plugin does not query the database, does not read its settings and does not load any file. The parts for the dashboard are loaded only on dashboard pages. The card itself needs no jQuery and no other library.
* **Accessible.** It works with the keyboard (Tab, Enter and the arrow keys), uses carousel semantics with `aria-live` for screen readers, shows a focus ring for keyboard users, and respects `prefers-reduced-motion` (no automatic sliding, instant change).
* **Private.** No external requests, no CDN, no external fonts, no cookies and no tracking.
* **Careful with long words.** A very long word or web address wraps inside the card instead of breaking the layout.

= Works with classic and block themes =

The card is shown automatically before the footer of your home page (the blog page or the static front page, but not page 2 and following), in its own centred container with side margins. It was tried with eight default WordPress themes, classic and block (see Status below). To show it anywhere else, use the shortcode `[page_turn_feedback]`, for example in a Shortcode block or in the shortcode element of your page builder.

= For developers =

Three filters, all prefixed `ptfb`: `ptfb_auto_insert` (return false to turn the automatic placement off), `ptfb_container_class` (the class of the container the card sits in) and `ptfb_max_items` (how many feedback are shown, 30 by default). The source code is on GitHub and bug reports and pull requests are welcome.

= Languages =

The plugin is written in English and is translated on translate.wordpress.org: WordPress downloads the translation of your language by itself when it is available. A complete Italian translation (it_IT) is kept in the GitHub repository. The extended user guide (`LEGGIMI.txt`) is in Italian.

= Status =

Tested on WordPress 7.1.4 with Twenty Twenty-One, Twenty Twenty, Twenty Nineteen, Twenty Seventeen, Twenty Twenty-Two, Twenty Twenty-Three, Twenty Twenty-Four and Twenty Twenty-Five, and running on a live site with the Fascinate theme. The front end has been tested in Chromium only (desktop and phone viewport); Safari and Firefox still need checking. Older WordPress versions are not tested.

== Source code ==

The full source code is public on GitHub: https://github.com/velvetleap/page-turn-feedback

== Installation ==

1. Upload the `velvet-leap-page-turn-feedback` folder to `/wp-content/plugins/`, or use Plugins > Add New > Upload Plugin, or search for "Velvet Leap Page Turn Feedback" in Plugins > Add New.
2. Activate the plugin.
3. Go to **Feedback > Add new feedback**, write a name and a comment, and publish. The section appears automatically at the bottom of the home page, above the footer.
4. Optional: go to **Feedback > Settings** to choose a ready-made style, the colours, the fonts and the speed.

To show the card on another page, use the shortcode `[page_turn_feedback]`.

== Frequently Asked Questions ==

= The section does not appear on my home page. What should I check? =

1. At least one feedback must be **published** (not a draft).
2. Automatic placement is only on the home page (the blog page or the static front page), not on page 2 and following.
3. The theme needs a footer: classic themes through `get_footer()`, block themes through the footer template part. If your theme has neither, or you removed it, use the shortcode `[page_turn_feedback]` (in a block theme, add a Shortcode block).
4. Some plugins or snippets can turn the automatic placement off with the `ptfb_auto_insert` filter.

= How do I add, edit or remove a feedback? =

Go to **Feedback > Add new feedback**. Write the name of the person in the title and the comment in the text box, then click **Publish**. To edit one, open it from **Feedback > All feedback**. To take one off the site, click **Move to Trash** (or switch it back to draft to keep it for later).

= How long can a comment be? =

There is no hard limit, but short comments read best on the card. The editor suggests 300 characters at most and counts them as you type. Very long words and web addresses wrap inside the card.

= How do I show it on another page? =

Insert the shortcode `[page_turn_feedback]`, for example in a Shortcode block, or in the shortcode or text element of your page builder.

= How do I turn off the automatic placement on the home page? =

Add this to your child theme's `functions.php`:

`add_filter( 'ptfb_auto_insert', '__return_false' );`

= Can I stop the automatic sliding? =

Yes. In **Feedback > Settings** set "Automatic sliding" to "No automatic sliding (by hand only)". Visitors can still turn the page with the folded corner, by dragging, or with the keyboard. Visitors who have asked their device to reduce motion never get automatic sliding and see an instant change instead of the fold.

= Can I change the title, the colours or the fonts? =

Yes, everything is in **Feedback > Settings**, with a live preview next to the form. You can edit the section title (or leave it empty to hide it), choose six colours, a font for the title, the comment and the name, and the alignment of the text. By default the fonts of your theme are used, so the card matches the rest of the site.

= Can I change the look with one click? =

Yes. On **Feedback > Settings** choose one of the five ready-made styles: it fills in the colours, fonts and alignment, and the preview next to the form shows the result. Nothing changes on the site until you press **Save Changes**, and every detail can still be adjusted. The preview uses a plain font wherever "Default (theme font)" is chosen, because your theme is not loaded there: on the site the theme font is used.

= How do I choose the order of the feedback? =

Go to **Feedback > Order**, drag the rows (or use the arrows) and press **Save order**. The site shows the feedback from top to bottom. Until you save an order, the newest feedback comes first. A feedback added after you saved the order is shown first until you save the order again. "Back to automatic order" restores newest-first.

= How many feedback does the card show? =

The first 30. A developer can change that number with the `ptfb_max_items` filter.

= How do I import or export my feedback? =

Go to **Feedback > Import and export**. The CSV file has one feedback per row with the columns `name`, `comment` and, optionally, `status` (publish or draft), `date` (2026-11-30) and `order`. Only `comment` is required. You can download an example file from that page. Commas and semicolons both work, as does a file saved by Excel. Feedback already on the site (same name and same comment) is skipped, so importing the same file twice does no harm. For an XML file (to move the feedback to another WordPress site) use WordPress's own Tools > Export, choosing "Feedback", and Tools > Import > WordPress on the other site.

= Can I add a photo, a star rating or a link to a feedback? =

No. The card shows the comment and the name only, on purpose: it keeps the plugin simple for the person who edits the site, and light for the visitors.

= Does it slow my site down? =

No. Pages that do not show the card cost nothing: the plugin does not ask the database anything and loads no file there. On the page that shows the card it loads one small stylesheet and one small script, asks the database for the feedback once, and needs no jQuery or other library.

= Does it use cookies, track visitors or contact other websites? =

No. It sets no cookies, collects no data, and makes no requests to other websites: no CDN, no external fonts.

= Does it work with my theme and my page builder? =

It is built for classic and block themes. If the placement before the footer does not suit your theme, turn it off (see above) and use the shortcode `[page_turn_feedback]` where you want the card, in the shortcode element of any page builder. The plugin has special support for the Fascinate theme, whose own container it uses so that the card looks like the rest of the page.

= I installed an earlier version (called Page Turn Feedback) by hand. How do I move to this one? =

Deactivate the old plugin, then install and activate this one. Your feedback and your settings are kept, because nothing the plugin stores was renamed. Do not delete the old plugin until you have checked the site: deleting it removes the settings (colours, speed), although the feedback stay. If the settings are back to the defaults after that, enter them again in Feedback > Settings.

= Can I translate it into my language? =

Yes. Every string uses the `velvet-leap-page-turn-feedback` text domain, and translations are made on translate.wordpress.org (https://translate.wordpress.org/projects/wp-plugins/velvet-leap-page-turn-feedback/): once one is approved for your language, WordPress installs it with the plugin updates. The `.pot` template and an Italian translation (it_IT) are also in the GitHub repository.

= What happens to my feedback if I uninstall the plugin? =

The settings are deleted. The feedback posts stay in the database.

== Screenshots ==

1. The card on the home page: the page folds from the bottom-right corner and reveals the next feedback (animated).
2. The card on a site with a block theme (Twenty Twenty-Five), shown automatically above the footer.
3. The settings page, with ready-made styles, a live preview and every option on one screen.
4. The five ready-made styles, plus the original look. One click fills in colours, fonts and alignment.
5. Adding a feedback: a name, a comment and a friendly tip to keep it short.
6. Choosing the order by hand: drag the rows or use the arrows.
7. Import and export: a CSV file that opens in Excel, Numbers or Google Sheets.

== Upgrade Notice ==

= 1.9.1 =
A richer plugin page with screenshots and a longer FAQ. No change to how the plugin works.

== Changelog ==

= 1.9.1 =
* A richer plugin page: longer description, a longer FAQ and seven screenshots (the card turning, the card on a block theme, the settings page, the ready-made styles, adding a feedback, the order page, import and export).
* The readme now says how to move from an earlier hand-installed version, and that the first feedback is added with "Add new feedback" (it said "Add feedback").
* No change to the plugin's code, markup, strings or settings.

= 1.9.0 =
* New name and address, as asked by the WordPress.org plugin review: the plugin is now called Velvet Leap Page Turn Feedback and its folder is `velvet-leap-page-turn-feedback`. If you installed an earlier version by hand, deactivate it before activating this one (both cannot be active together). The feedback and the settings are kept: nothing stored by the plugin was renamed.
* The plugin now says it needs WordPress 5.1 or newer (the oldest version that has every function it uses).
* Translation files are no longer inside the plugin package: translations come from translate.wordpress.org, so the plugin no longer calls load_plugin_textdomain().

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
