<?php
// Prova della logica delle impostazioni, fuori da WordPress (funzioni WP minime simulate).
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
require __DIR__ . '/i18n_stubs.php';
$IT = ( 'it' === getenv( 'PTFB_LANG' ) );
function register_post_type( $n, $a ) { $GLOBALS['cpt'] = $a; }
function wp_enqueue_style( $h ) { $GLOBALS['enq']['style'][] = $h; }
function wp_enqueue_script( $h, $src = '', $deps = array(), $ver = false, $foot = false ) { $GLOBALS['enq']['script'][ $h ] = array( 'src' => $src, 'deps' => $deps, 'ver' => $ver, 'footer' => $foot ); }
function plugins_url( $p ) { return '../../' . $p; } // pages are written to tests/out/
function get_current_screen() { return $GLOBALS['screen']; }
$GLOBALS['opts'] = array();
function add_action() {}
function add_filter() {}
function add_shortcode() {}
function apply_filters( $h, $v ) { return $v; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function absint( $v ) { return abs( (int) $v ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function current_user_can() { return true; }
function settings_errors() {}
function settings_fields() {}
function submit_button() { echo '<input type="submit">'; }
function sanitize_hex_color( $c ) { return preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', (string) $c ) ? $c : null; }
function selected( $a, $b, $echo = true ) { $r = ( (string) $a === (string) $b ) ? " selected='selected'" : ''; if ( $echo ) { echo $r; } return $r; }

require dirname( __DIR__ ) . '/page-turn-feedback.php';
$GLOBALS['opts'] = array();
echo '<!doctype html><html><head><meta charset="utf-8"></head><body>';
ptfb_settings_page();
echo '<script>window.ptfbAdmin = ' . wp_json_encode( array(
	'limit'   => 300,
	'tooLong' => __( 'That is %d characters: better to shorten it a little.', 'page-turn-feedback' ),
	'count'   => __( 'Characters: %1$d / %2$d', 'page-turn-feedback' ),
) ) . ';</script>';
echo '<script src="../../assets/page-turn-feedback-admin.js"></script></body></html>';
