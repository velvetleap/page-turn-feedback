<?php
// Prova della logica delle impostazioni, fuori da WordPress (funzioni WP minime simulate).
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
require __DIR__ . '/i18n_stubs.php';
$IT = ( 'it' === getenv( 'PTFB_LANG' ) );
function register_post_type( $n, $a ) { $GLOBALS['cpt'] = $a; }
function wp_enqueue_style( $h ) { $GLOBALS['enq']['style'][] = $h; }
function wp_enqueue_script( $h, $src = '', $deps = array(), $ver = false, $foot = false ) { $GLOBALS['enq']['script'][ $h ] = array( 'src' => $src, 'deps' => $deps, 'ver' => $ver, 'footer' => $foot ); }
function plugins_url( $p ) { return '../../' . $p; } // pages are written to tests/out/
function get_current_screen() { return isset( $GLOBALS['screen'] ) ? $GLOBALS['screen'] : null; }
$GLOBALS['opts'] = array();
function is_admin() { return true; }
function add_action() {}
function add_submenu_page() {}
function get_posts() { return isset( $GLOBALS['items'] ) ? $GLOBALS['items'] : array(); }
function wp_unique_id( $p = '' ) { static $i = 0; return $p . ( ++$i ); }
function wpautop( $s ) { return '<p>' . str_replace( "\n\n", '</p><p>', trim( $s ) ) . '</p>'; }
function esc_url( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function admin_url( $p = '' ) { return 'http://example.test/wp-admin/' . $p; }
function add_filter() {}
function add_shortcode() {}
function apply_filters( $h, $v ) { return ( 'ptfb_max_items' === $h && getenv( 'PTFB_MAX' ) ) ? (int) getenv( 'PTFB_MAX' ) : $v; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function absint( $v ) { return abs( (int) $v ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function current_user_can() { return true; }
function sanitize_key( $s ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $s ) ); }
function wp_unslash( $v ) { return $v; }
function wp_nonce_field( $a ) { echo '<input type="hidden" name="_wpnonce" value="x">'; }
function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }
function wp_trim_words( $s, $n = 55 ) { $w = preg_split( '/\s+/', trim( $s ) ); return count( $w ) > $n ? implode( ' ', array_slice( $w, 0, $n ) ) . '…' : trim( $s ); }
function settings_errors() {}
function settings_fields() {}
function submit_button() { echo '<input type="submit">'; }
function sanitize_hex_color( $c ) { return preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', (string) $c ) ? $c : null; }
function selected( $a, $b, $echo = true ) { $r = ( (string) $a === (string) $b ) ? " selected='selected'" : ''; if ( $echo ) { echo $r; } return $r; }

require dirname( __DIR__ ) . '/page-turn-feedback.php';
$GLOBALS['opts'] = ( getenv( 'LSA_OPTS' ) ? json_decode( getenv( 'LSA_OPTS' ), true ) : array() );

// Feedback of the test site (PTFB_ITEMS = how many; none by default = the preview shows example texts).
$names = array( 'Giulia R.', 'Marco', 'Autrice Alice DC', 'Sara & Chiara', 'Elisa' );
$GLOBALS['items'] = array();
for ( $i = 0; $i < (int) getenv( 'PTFB_ITEMS' ); $i++ ) {
	$GLOBALS['items'][] = (object) array( 'ID' => 10 + $i, 'post_title' => $names[ $i % 5 ], 'post_content' => 'Commento numero ' . ( $i + 1 ) . ' di prova, abbastanza lungo da occupare una riga intera della scheda.' );
}

// Which page: the settings (default) or the Order page.
$page = getenv( 'PTFB_PAGE' ) ?: 'settings';
$GLOBALS['ptfb_settings_hook'] = 'feedback_page_ptfb-settings';
$GLOBALS['ptfb_order_hook']    = 'feedback_page_ptfb-order';
ptfb_admin_assets( 'order' === $page ? 'feedback_page_ptfb-order' : 'feedback_page_ptfb-settings' );

echo '<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="../../assets/page-turn-feedback-admin.css"></head><body>';
if ( 'order' === $page ) {
	ptfb_order_page();
} else {
	ptfb_settings_page();
}
echo '<script>' . $GLOBALS['inline_scripts']['page-turn-feedback-admin']['before'][0] . '</script>';
echo '<script src="../../assets/page-turn-feedback-admin.js"></script></body></html>';
