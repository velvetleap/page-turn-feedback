"""Writes the Playground blueprint behind the "Live Preview" button of the WordPress.org plugin page:
.wordpress-org/blueprints/blueprint.json (it ends up in the Subversion folder assets/blueprints/).

It opens a throw-away WordPress in the visitor's browser (WordPress Playground), installs this plugin from the
WordPress.org directory, adds six invented feedback and a front page with the card and a few lines of help.
Nothing is saved: the site is gone when the visitor closes the tab.

Usage: python3 design/blueprint.py .wordpress-org
"""
import json
import os
import sys

# Same invented feedback as design/wp-rig-seed.php (newest first).
FEEDBACK = [
    ("Anna", "I found my last three favourite novels here. The reviews are warm, honest and never boring."),
    ("Marcus", "It feels like talking to a friend who always knows the right book for the moment. Thank you!"),
    ("Giulia", "Clear, generous and full of surprises. I read every new post with my morning coffee."),
    ("Peter", "Finally a reading list I can trust. I have already bought two of the books you suggested."),
    ("Sofia", "Your recommendations got me reading again after years. I now keep a notebook just for your lists."),
    ("Daniel", "Short, honest and always right on the mark. The first blog I check on a Sunday morning."),
]

PAGE = (
    '<!-- wp:paragraph --><p>The card below is Velvet Leap Page Turn Feedback. Click the folded corner, '
    'or press and drag the card, to turn the page by hand.</p><!-- /wp:paragraph -->'
    '<!-- wp:group {"align":"wide","layout":{"type":"default"}} --><div class="wp-block-group alignwide">'
    '<!-- wp:shortcode -->[page_turn_feedback]<!-- /wp:shortcode --></div><!-- /wp:group -->'
    '<!-- wp:paragraph --><p>To add, edit or remove a feedback, open <strong>Feedback</strong> in the dashboard '
    '(you are logged in as an administrator of this test site). '
    'Nothing is saved: the site disappears when you close the tab.</p><!-- /wp:paragraph -->'
)


def php():
    lines = [
        "<?php",
        "require '/wordpress/wp-load.php';",
        "$feedback = " + "array(" + ", ".join("array(%s, %s)" % (json.dumps(n), json.dumps(t)) for n, t in FEEDBACK) + ");",
        # The oldest is created first so that the first one in the list is the newest, shown first.
        "foreach ( array_reverse( $feedback ) as $i => $item ) {",
        "\twp_insert_post( array( 'post_type' => 'ptfb_feedback', 'post_title' => $item[0], 'post_content' => $item[1],",
        "\t\t'post_status' => 'publish', 'post_date' => gmdate( 'Y-m-d H:i:s', time() - ( 10 - $i ) * 3600 ) ) );",
        "}",
        "$page = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'Live preview', 'post_name' => 'live-preview',",
        "\t'post_status' => 'publish', 'post_content' => " + json.dumps(PAGE) + " ) );",
        "update_option( 'show_on_front', 'page' );",
        "update_option( 'page_on_front', $page );",
    ]
    return "\n".join(lines) + "\n"


def blueprint():
    return {
        "$schema": "https://playground.wordpress.net/blueprint-schema.json",
        "meta": {
            "title": "Velvet Leap Page Turn Feedback: live preview",
            "description": "Try the card that turns like the page of a book, with six invented feedback.",
            "author": "velvetleap",
        },
        "landingPage": "/",
        "preferredVersions": {"php": "8.2", "wp": "latest"},
        "steps": [
            {"step": "login", "username": "admin", "password": "password"},
            {
                "step": "installPlugin",
                "pluginData": {"resource": "wordpress.org/plugins", "slug": "velvet-leap-page-turn-feedback"},
                "options": {"activate": True},
            },
            {"step": "setSiteOptions", "options": {"blogname": "The Reading Nook", "blogdescription": "Books, tea and quiet corners"}},
            {"step": "runPHP", "code": php()},
        ],
    }


if __name__ == "__main__":
    out = os.path.join(sys.argv[1], "blueprints")
    os.makedirs(out, exist_ok=True)
    with open(os.path.join(out, "blueprint.json"), "w", encoding="utf-8") as f:
        json.dump(blueprint(), f, indent=2, ensure_ascii=False)
        f.write("\n")
    print(os.path.join(out, "blueprint.json"))
