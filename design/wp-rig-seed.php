<?php
/**
 * Installs WordPress in the throw-away site made by design/wp-rig.sh and adds sample content:
 * a few posts and six invented feedback. Not part of the plugin zip.
 *
 * Usage: php design/wp-rig-seed.php <site-dir>
 */

if ( 'cli' !== PHP_SAPI || empty( $argv[1] ) ) {
	exit( "Usage: php design/wp-rig-seed.php <site-dir>\n" );
}
$site = rtrim( $argv[1], '/' );

$_SERVER['HTTP_HOST']       = 'localhost:8080';
$_SERVER['REQUEST_URI']     = '/';
$_SERVER['SERVER_NAME']     = 'localhost';
$_SERVER['REQUEST_METHOD']  = 'GET';

define( 'WP_INSTALLING', true );
require $site . '/wp-load.php';
require ABSPATH . 'wp-admin/includes/upgrade.php';

if ( ! is_blog_installed() ) {
	wp_install( 'The Reading Nook', 'admin', 'admin@example.com', true, '', 'password', 'en_US' );
}

update_option( 'active_plugins', array( 'velvet-leap-page-turn-feedback/velvet-leap-page-turn-feedback.php' ) );
update_option( 'blogdescription', 'Books, tea and quiet corners' );
switch_theme( 'twentytwentyfive' );
update_option( 'show_on_front', 'posts' );
update_option( 'posts_per_page', 10 );
update_option( 'permalink_structure', '/%postname%/' );

// Sample posts (the default "Hello world" post stays).
$posts = array(
	'Five novels for a rainy weekend'   => 'A pot of tea, a blanket and a book you cannot put down: these are the five novels I keep going back to when the weather turns.',
	'What I am reading this month'      => 'A slow, quiet month: two short story collections, one long classic and a thriller I finished in a single evening.',
	'A small bookshop worth the detour' => 'It has creaking floors, a cat on the counter and a shelf of second-hand paperbacks that always seems to hold the book I needed.',
);
foreach ( $posts as $title => $text ) {
	wp_insert_post(
		array(
			'post_title'   => $title,
			'post_content' => '<!-- wp:paragraph --><p>' . $text . '</p><!-- /wp:paragraph -->',
			'post_status'  => 'publish',
			'post_author'  => 1,
		)
	);
}

// Invented feedback (the plugin is active from the next load, so the content type is registered by hand here).
register_post_type( 'ptfb_feedback', array( 'public' => false ) );
$feedback = array(
	array( 'Anna', 'I found my last three favourite novels here. The reviews are warm, honest and never boring.' ),
	array( 'Marcus', 'It feels like talking to a friend who always knows the right book for the moment. Thank you!' ),
	array( 'Giulia', 'Clear, generous and full of surprises. I read every new post with my morning coffee.' ),
	array( 'Peter', 'Finally a reading list I can trust. I have already bought two of the books you suggested.' ),
	array( 'Sofia', 'Your recommendations got me reading again after years. I now keep a notebook just for your lists.' ),
	array( 'Daniel', 'Short, honest and always right on the mark. The first blog I check on a Sunday morning.' ),
);
foreach ( array_reverse( $feedback ) as $i => $item ) {
	wp_insert_post(
		array(
			'post_type'    => 'ptfb_feedback',
			'post_title'   => $item[0],
			'post_content' => $item[1],
			'post_status'  => 'publish',
			'post_date'    => gmdate( 'Y-m-d H:i:s', time() - ( 10 - $i ) * 3600 ),
		)
	);
}
flush_rewrite_rules();
echo "Seeded.\n";
