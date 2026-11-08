<?php
// Minimal i18n stubs for running the plugin outside WordPress.
// PTFB_LANG=it loads the bundled Italian translation straight from languages/page-turn-feedback-it_IT.mo,
// so the tests also prove that the compiled file is valid and in step with the code.

/* Reads a .mo file into an array: original string => translation (first form only). */
function ptfb_test_read_mo( $file ) {
	$data = file_get_contents( $file );
	$magic = unpack( 'V', substr( $data, 0, 4 ) )[1];
	if ( 0x950412de !== $magic ) {
		throw new RuntimeException( 'Not a little-endian .mo file: ' . $file );
	}
	$h = unpack( 'Vrev/Vcount/Voffo/Vofft', substr( $data, 4, 16 ) );
	$out = array();
	for ( $i = 0; $i < $h['count']; $i++ ) {
		$o = unpack( 'Vlen/Voff', substr( $data, $h['offo'] + 8 * $i, 8 ) );
		$t = unpack( 'Vlen/Voff', substr( $data, $h['offt'] + 8 * $i, 8 ) );
		$orig  = substr( $data, $o['off'], $o['len'] );
		$trans = substr( $data, $t['off'], $t['len'] );
		if ( '' === $orig || false !== strpos( $orig, "\0" ) || false !== strpos( $orig, "\4" ) ) {
			continue; // header, plurals and contexts are not used by this plugin.
		}
		$out[ $orig ] = $trans;
	}
	return $out;
}

$GLOBALS['tr'] = ( 'it' === getenv( 'PTFB_LANG' ) ) ? ptfb_test_read_mo( dirname( __DIR__ ) . '/languages/page-turn-feedback-it_IT.mo' ) : array();
if ( ! function_exists( '__' ) ) {
	function __( $t, $d = '' ) { return ( isset( $GLOBALS['tr'][ $t ] ) && '' !== $GLOBALS['tr'][ $t ] ) ? $GLOBALS['tr'][ $t ] : $t; }
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $t, $d = '' ) { return htmlspecialchars( __( $t ), ENT_QUOTES ); }
	function esc_attr__( $t, $d = '' ) { return htmlspecialchars( __( $t ), ENT_QUOTES ); }
	function esc_html_e( $t, $d = '' ) { echo htmlspecialchars( __( $t ), ENT_QUOTES ); }
	function esc_attr_e( $t, $d = '' ) { echo htmlspecialchars( __( $t ), ENT_QUOTES ); }
}
if ( ! function_exists( 'wp_kses' ) ) {
	function wp_kses( $s, $allowed ) { return strip_tags( $s, '<' . implode( '><', array_keys( $allowed ) ) . '>' ); }
}
if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $s ) { return $s; }
}
if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( $f ) { return basename( dirname( $f ) ) . '/' . basename( $f ); }
}
if ( ! function_exists( 'load_plugin_textdomain' ) ) {
	function load_plugin_textdomain() { return true; }
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $v ) { return json_encode( $v, JSON_UNESCAPED_UNICODE ); }
}
if ( ! function_exists( 'wp_add_inline_script' ) ) {
	function wp_add_inline_script( $h, $code, $pos = 'after' ) { $GLOBALS['inline_scripts'][ $h ][ $pos ][] = $code; return true; }
}
