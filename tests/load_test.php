<?php
// Which files the plugin loads. On a normal dashboard page the admin files are loaded; on admin-ajax.php
// (PTFB_AJAX=1), which also serves visitors for any plugin, they are not. Outside the dashboard they never are.
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
require __DIR__ . '/i18n_stubs.php';
$GLOBALS['mode'] = getenv( 'PTFB_AJAX' ) ? 'ajax' : ( getenv( 'PTFB_FRONT' ) ? 'front' : 'admin' );
function is_admin() { return 'front' !== $GLOBALS['mode']; }
function wp_doing_ajax() { return 'ajax' === $GLOBALS['mode']; }
function add_action() {} function add_filter() {} function add_shortcode() {}
require dirname( __DIR__ ) . '/page-turn-feedback.php';

$loaded = function_exists( 'ptfb_style_presets' ) && function_exists( 'ptfb_apply_order' ) && function_exists( 'ptfb_csv_build' );
$none   = ! function_exists( 'ptfb_style_presets' ) && ! function_exists( 'ptfb_apply_order' ) && ! function_exists( 'ptfb_csv_build' );
$ok     = 'admin' === $GLOBALS['mode'] ? $loaded : $none;
echo ( $ok ? 'PASS ' : 'FAIL ' ) . 'mode ' . $GLOBALS['mode'] . ': admin files ' . ( 'admin' === $GLOBALS['mode'] ? 'loaded' : 'not loaded' ) . "\n";
exit( $ok ? 0 : 1 );
