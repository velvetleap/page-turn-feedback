=== Page Turn Feedback ===
Tags: testimonials, feedback, quotes, reviews, slider
Requires at least: 5.0.3
Stable tag: 1.5.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Show feedback, testimonials and quotes one at a time on a card that turns like the page of a book.

== Description ==

Page Turn Feedback shows testimonials, reader feedback, reviews or quotes **one at a time on a card that turns like the page of a book**: the page folds from a dog-eared bottom-right corner and reveals the next one.

It is built for sites run by non-technical editors: adding, editing and removing a feedback is just "name + comment" in the WordPress admin.

**Note:** the plugin interface and the user guide (`LEGGIMI.txt`) are in Italian.

= Features =

* **"Feedback" menu** in the admin: name = title, comment = text. Publish, draft or trash like any post.
* **Page-turn effect** made of a real fold (clipped polygons and paper shading), not a 3D rotation. No libraries, no images.
* **Folded corner** in the bottom-right: click or tap to go to the next one, hover to enlarge it, drag it to turn the page by hand.
* **Press and hold** on the text to pause autoplay and drag left (next) or right (previous). Autoplay resumes on release.
* **Settings** (Feedback > Settings): section title, autoplay time (3, 5 or 10 s, custom 2-120 s, or off), page-turn duration, six colours, fonts for title, comment and name, alignment of comment and name.
* **Accessible**: keyboard support (Tab, Enter, arrow keys), carousel semantics with `aria-live`, focus indicator for keyboard users only, `prefers-reduced-motion` support (no autoplay, instant swap).
* **No dependencies and no external requests**: no CDN, no external fonts, no tracking.
* Uses the theme's heading and body fonts by default.

= Status =

Tested in Chromium only (desktop and phone viewport). Not yet tested across WordPress versions. Bug reports and pull requests are welcome on GitHub.

== Installation ==

1. Upload the `page-turn-feedback` folder to `/wp-content/plugins/`, or use Plugins > Add New > Upload Plugin.
2. Activate the plugin.
3. Go to **Feedback > Add feedback**. The section appears automatically at the bottom of the home page, above the footer.

To show it elsewhere, use the shortcode `[page_turn_feedback]`.

== Frequently Asked Questions ==

= How do I show it on another page? =

Insert the shortcode `[page_turn_feedback]`.

= How do I turn off the automatic placement on the home page? =

Add this to your child theme's `functions.php`:

`add_filter( 'ptfb_auto_insert', '__return_false' );`

= What happens to my feedback if I uninstall the plugin? =

The settings are deleted. The feedback posts stay in the database.

== Changelog ==

= 1.5.0 =
* The plugin is now called Page Turn Feedback (it was LSA Feedback Slider) and its author is Velvet Leap.
* All technical identifiers were renamed: post type `ptfb_feedback`, options `ptfb_*`, shortcode `[page_turn_feedback]`, filters `ptfb_container_class`, `ptfb_auto_insert` and `ptfb_max_items`, CSS prefix `ptfb`. Data saved by 1.4.0 is not migrated.

= 1.4.0 =
* The keyboard focus indicator on the folded corner is shown only to keyboard users.
* New settings: text alignment for the comment and for the author name.
* New setting: duration of the page-turn animation.
* Editor: recommendation to keep comments under 300 characters, with a live character counter.
