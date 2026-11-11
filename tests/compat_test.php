<?php
// Tests of the theme-compatibility logic added in 1.7.0: container class, block-theme placement.
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
$GLOBALS['hooks'] = array(); $GLOBALS['filters'] = array();
function add_action( $h, $cb, $p = 10, $a = 1 ) { $GLOBALS['hooks'][ $h ][] = $cb; }
function add_filter( $h, $cb, $p = 10, $a = 1 ) { $GLOBALS['hooks'][ $h ][] = $cb; $GLOBALS['filters'][ $h ][] = $cb; }
function remove_all_filters( $h ) { unset( $GLOBALS['filters'][ $h ] ); }
function apply_filters( $h, $v ) { foreach ( $GLOBALS['filters'][ $h ] ?? array() as $cb ) { $v = $cb( $v ); } return $v; }
function is_admin() { return false; }
function add_shortcode() {} function add_submenu_page() {} function register_post_type() {} function register_setting() {}
function sanitize_hex_color( $c ) { return preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', (string) $c ) ? $c : null; }
$GLOBALS['calls'] = array(); $GLOBALS['opts'] = array();
function hit( $what ) { $GLOBALS['calls'][ $what ] = 1 + ( $GLOBALS['calls'][ $what ] ?? 0 ); }
function calls( $what ) { return $GLOBALS['calls'][ $what ] ?? 0; }
function wp_add_inline_style() { hit( 'inline' ); } function wp_register_style() { hit( 'register_style' ); } function wp_register_script() { hit( 'register_script' ); }
function wp_enqueue_style() { hit( 'enqueue_style' ); } function wp_enqueue_script() { hit( 'enqueue_script' ); } function plugins_url( $p ) { return $p; }
function get_option( $k, $d = false ) { hit( 'get_option' ); return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function get_posts() { hit( 'get_posts' ); return $GLOBALS['items']; }
function is_home() { return $GLOBALS['is_home']; } function is_front_page() { return $GLOBALS['is_home']; } function is_paged() { return $GLOBALS['is_paged']; }
$GLOBALS['singular'] = false; $GLOBALS['post_text'] = '';
function is_singular() { return $GLOBALS['singular']; } function get_post() { return $GLOBALS['singular'] ? (object) array( 'post_content' => $GLOBALS['post_text'] ) : null; } function has_shortcode( $t, $tag ) { return false !== strpos( $t, '[' . $tag . ']' ); }
function wp_unique_id( $p = '' ) { static $i = 0; return $p . ( ++$i ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); } function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function wpautop( $s ) { return '<p>' . trim( $s ) . '</p>'; }
function absint( $v ) { return abs( (int) $v ); } function sanitize_text_field( $s ) { return $s; }
function wp_strip_all_tags( $s ) { return strip_tags( $s ); } function wp_trim_words( $s ) { return $s; } function get_post_field() { return ''; }
require __DIR__ . '/i18n_stubs.php';
class FakeTheme {
	public $d; function __construct( $d ) { $this->d = $d; }
	function get_template() { return $this->d['template']; } function get_stylesheet() { return $this->d['stylesheet']; }
	function get( $k ) { return $this->d[ $k ] ?? ''; } function parent() { return isset( $this->d['parent'] ) ? new FakeTheme( $this->d['parent'] ) : false; }
}
function wp_get_theme() { return new FakeTheme( $GLOBALS['theme'] ); }
require dirname( __DIR__ ) . '/page-turn-feedback.php';

$ok = true; $n = 0;
function check( $label, $cond, $extra = '' ) { global $ok, $n; $n++; echo ( $cond ? 'PASS ' : 'FAIL ' ) . $label . ( $cond ? '' : ' ' . $extra ) . "\n"; $ok = $ok && $cond; }
function theme( $t, $s, $name, $parent = null ) { $GLOBALS['theme'] = array( 'template' => $t, 'stylesheet' => $s, 'Name' => $name ) + ( $parent ? array( 'parent' => $parent ) : array() ); }
function div_class( $html ) { return preg_match( '/<section[^>]*>\s*<div( class="([^"]*)")?>/', $html, $m ) ? ( $m[2] ?? '' ) : null; }

$GLOBALS['items'] = getenv( 'PTFB_EMPTY' ) ? array() : array( (object) array( 'post_title' => 'Anna', 'post_content' => 'Bello' ), (object) array( 'post_title' => 'Luca', 'post_content' => 'Ottimo' ) );
$GLOBALS['is_home'] = true; $GLOBALS['is_paged'] = false;

// --- container class
theme( 'fascinate', 'fascinate', 'Fascinate' );
check( 'Fascinate: classe del tema fb-container', ptfb_default_container_class() === 'fb-container' );
theme( 'fascinate', 'fascinate-child', 'Fascinate Child', array( 'template' => 'fascinate', 'stylesheet' => 'fascinate', 'Name' => 'Fascinate' ) );
check( 'child theme di Fascinate: fb-container', ptfb_default_container_class() === 'fb-container' );
theme( 'mio-tema-figlio', 'mio-tema-figlio', 'Mio tema', array( 'template' => 'x', 'stylesheet' => 'x', 'Name' => 'Fascinate' ) );
check( 'genitore riconosciuto dal nome anche con cartella diversa', ptfb_default_container_class() === 'fb-container' );
theme( 'twentytwentyone', 'twentytwentyone', 'Twenty Twenty-One' );
check( 'altro tema: contenitore proprio ptfb__container', ptfb_default_container_class() === 'ptfb__container' );
theme( 'twentytwentyfive', 'twentytwentyfive', 'Twenty Twenty-Five' );
check( 'tema a blocchi: contenitore proprio', ptfb_default_container_class() === 'ptfb__container' );

// --- pages that do not show the card: no database query, no option read, no file loaded
$GLOBALS['is_home'] = false; $GLOBALS['singular'] = false;
ptfb_register_assets();
check( 'pagina qualsiasi (archivio, 404...): nessuna ricerca dei feedback, nessuna opzione letta, nessun file caricato', 0 === calls( 'get_posts' ) && 0 === calls( 'get_option' ) && 0 === calls( 'register_style' ) && 0 === calls( 'enqueue_style' ), $GLOBALS['calls'] );
$GLOBALS['singular'] = true; $GLOBALS['post_text'] = 'Un articolo qualsiasi';
ptfb_register_assets();
check( 'articolo o pagina senza shortcode: nessuna ricerca dei feedback, nessuna opzione letta, nessun file caricato', 0 === calls( 'get_posts' ) && 0 === calls( 'get_option' ) && 0 === calls( 'register_style' ) && 0 === calls( 'enqueue_style' ), $GLOBALS['calls'] );
$GLOBALS['is_home'] = true; $GLOBALS['singular'] = false;

if ( getenv( 'PTFB_EMPTY' ) ) {
	$GLOBALS['ptfb_printed'] = false;
	check( 'senza feedback pubblicati: footer intatto e niente markup', ptfb_auto_insert_block( '<footer>F</footer>', array( 'blockName' => 'core/template-part', 'attrs' => array( 'slug' => 'footer' ) ) ) === '<footer>F</footer>' && '' === ptfb_render() );
	ptfb_register_assets();
	check( 'home senza feedback pubblicati: nessun file caricato', 0 === calls( 'register_style' ) && 0 === calls( 'enqueue_style' ) && 0 === calls( 'enqueue_script' ), $GLOBALS['calls'] );
	echo $ok ? "TUTTO OK ($n controlli)\n" : "CI SONO FALLIMENTI\n"; exit( $ok ? 0 : 1 );
}
$GLOBALS['opts']['ptfb_style'] = ptfb_sanitize_style( array( 'bg' => '#112233' ) );
$html = ptfb_render();
check( 'card stampata fuori dall\'head (widget, template): i file vengono registrati e caricati lo stesso, con i colori scelti', 1 === calls( 'register_style' ) && 1 === calls( 'register_script' ) && 1 === calls( 'enqueue_style' ) && 1 === calls( 'enqueue_script' ) && 1 === calls( 'inline' ), $GLOBALS['calls'] );
ptfb_register_assets();
check( 'home: i file vanno nell\'head senza essere registrati due volte ne\' ripetere il CSS', 1 === calls( 'register_style' ) && 1 === calls( 'register_script' ) && 1 === calls( 'inline' ) && 2 === calls( 'enqueue_style' ) && 2 === calls( 'enqueue_script' ), $GLOBALS['calls'] );
$GLOBALS['is_home'] = false; $GLOBALS['singular'] = true; $GLOBALS['post_text'] = 'Ecco: [page_turn_feedback]';
ptfb_register_assets();
check( 'pagina con lo shortcode: i file vanno nell\'head', 3 === calls( 'enqueue_style' ) && 3 === calls( 'enqueue_script' ), $GLOBALS['calls'] );
$GLOBALS['is_home'] = true; $GLOBALS['singular'] = false;
check( 'markup: classe del contenitore nel div sotto la section', div_class( $html ) === 'ptfb__container', var_export( div_class( $html ), true ) );
theme( 'fascinate', 'fascinate', 'Fascinate' );
check( 'markup su Fascinate: <div class="fb-container"> come prima', div_class( ptfb_render() ) === 'fb-container' );
add_filter( 'ptfb_container_class', function () { return 'container wide'; } );
check( 'il filtro ptfb_container_class sostituisce il default', div_class( ptfb_render() ) === 'container wide' );
remove_all_filters( 'ptfb_container_class' ); add_filter( 'ptfb_container_class', function () { return ''; } );
check( 'il filtro puo\' togliere la classe', div_class( ptfb_render() ) === '' );
remove_all_filters( 'ptfb_container_class' );

// --- block themes: before the footer template part
$GLOBALS['ptfb_printed'] = false;
$footer = '<footer class="wp-block-template-part">FOOTER</footer>';
$out = ptfb_auto_insert_block( $footer, array( 'blockName' => 'core/template-part', 'attrs' => array( 'slug' => 'footer' ) ) );
check( 'tema a blocchi: la scheda e\' stampata PRIMA del footer', strpos( ltrim( $out ), '<section class="ptfb"' ) === 0 && substr( $out, -strlen( $footer ) ) === $footer );
check( 'tema a blocchi: segnato come stampato (niente doppioni)', ! empty( $GLOBALS['ptfb_printed'] ) );
$again = ptfb_auto_insert_block( $footer, array( 'blockName' => 'core/template-part', 'attrs' => array( 'slug' => 'footer' ) ) );
check( 'tema a blocchi: una seconda chiamata non duplica', $again === $footer );

$GLOBALS['ptfb_printed'] = false;
$out = ptfb_auto_insert_block( $footer, array( 'blockName' => 'core/template-part', 'attrs' => array( 'slug' => 'site-footer', 'area' => 'footer' ) ) );
check( 'riconosce il footer anche dall\'attributo area', strpos( ltrim( $out ), '<section class="ptfb"' ) === 0 );

$GLOBALS['ptfb_printed'] = false;
check( 'header: nessuna modifica', ptfb_auto_insert_block( 'HEADER', array( 'blockName' => 'core/template-part', 'attrs' => array( 'slug' => 'header' ) ) ) === 'HEADER' );
$GLOBALS['is_home'] = false;
check( 'fuori dalla home: nessuna modifica', ptfb_auto_insert_block( $footer, array( 'blockName' => 'core/template-part', 'attrs' => array( 'slug' => 'footer' ) ) ) === $footer );
$GLOBALS['is_home'] = true; $GLOBALS['is_paged'] = true;
check( 'pagina 2 della home: nessuna modifica', ptfb_auto_insert_block( $footer, array( 'blockName' => 'core/template-part', 'attrs' => array( 'slug' => 'footer' ) ) ) === $footer );
$GLOBALS['is_paged'] = false;
add_filter( 'ptfb_auto_insert', '__return_false_t' ); function __return_false_t() { return false; }
check( 'filtro ptfb_auto_insert=false: nessuna modifica', ptfb_auto_insert_block( $footer, array( 'blockName' => 'core/template-part', 'attrs' => array( 'slug' => 'footer' ) ) ) === $footer );
remove_all_filters( 'ptfb_auto_insert' );
check( 'hook registrati: solo il blocco template-part (temi a blocchi), nessun filtro su ogni blocco, e get_footer (temi classici)', in_array( 'ptfb_auto_insert_block', $GLOBALS['hooks']['render_block_core/template-part'] ?? array(), true ) && ! isset( $GLOBALS['hooks']['render_block'] ) && in_array( 'ptfb_auto_insert', $GLOBALS['hooks']['get_footer'] ?? array(), true ) );

echo $ok ? "TUTTO OK ($n controlli)\n" : "CI SONO FALLIMENTI\n";
exit( $ok ? 0 : 1 );
