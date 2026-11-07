=== Page Turn Feedback ===
Contributors: matthewskud
Tags: testimonials, feedback, quotes, reviews, slider
Requires at least: 5.0.3
Tested up to: 7.1
Stable tag: 1.7.1
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

= What happens to my feedback if I uninstall the plugin? =

The settings are deleted. The feedback posts stay in the database.

== Changelog ==

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
