<?php
// Prints the CSS the plugin adds to the front end (ptfb_inline_css) for a style given as JSON (argument 1).
// Used by admin_features_test.py to check that the live preview in the settings page draws the same card as the site.
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
require __DIR__ . '/i18n_stubs.php';
$GLOBALS['opts'] = array();
function is_admin() { return false; }
function add_action() {} function add_filter() {} function add_shortcode() {} function register_post_type() {} function register_setting() {} function add_submenu_page() {}
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function sanitize_hex_color( $c ) { return preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', (string) $c ) ? $c : null; }
require dirname( __DIR__ ) . '/page-turn-feedback.php';
$GLOBALS['opts']['ptfb_style'] = ptfb_sanitize_style( json_decode( $argv[1], true ) );
echo ptfb_inline_css();
