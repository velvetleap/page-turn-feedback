<?php
// Minimal WordPress stubs, just enough to run the plugin's render function outside WordPress.
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
require __DIR__ . '/i18n_stubs.php';
$GLOBALS['hooks'] = array();
function add_action( $h, $cb, $p = 10, $a = 1 ) { $GLOBALS['hooks'][ $h ][] = $cb; }
function add_filter( $h, $cb, $p = 10, $a = 1 ) { $GLOBALS['hooks'][ $h ][] = $cb; $GLOBALS['filters'][ $h ][] = $cb; }
function add_shortcode() {}
function add_submenu_page() {}
function register_post_type( $n, $a ) { $GLOBALS['cpt'] = $a; }
function register_setting() {}
function sanitize_hex_color( $c ) { return preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', (string) $c ) ? $c : null; }
function wp_add_inline_style() {}
function wp_register_style() {}
function wp_register_script() {}
function wp_enqueue_style() {}
function wp_enqueue_script() {}
function plugins_url( $p ) { return $p; }
function apply_filters( $h, $v ) { foreach ( $GLOBALS['filters'][ $h ] ?? array() as $cb ) { $v = $cb( $v ); } return $v; }
function get_option( $k, $d = false ) { return isset( $GLOBALS['opts'][ $k ] ) ? $GLOBALS['opts'][ $k ] : $d; }
function get_posts() { return $GLOBALS['items']; }
function is_home() { return true; }
function is_front_page() { return true; }
function is_paged() { return false; }
function is_singular() { return false; }
function get_post() { return null; }
function has_shortcode() { return false; }
function wp_unique_id( $p = '' ) { static $i = 0; return $p . ( ++$i ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function wp_kses_post( $s ) { return $s; }
function wpautop( $s ) { return '<p>' . str_replace( "\n\n", '</p><p>', trim( $s ) ) . '</p>'; }
function absint( $v ) { return abs( (int) $v ); }
function sanitize_text_field( $s ) { return $s; }
function wp_strip_all_tags( $s ) { return strip_tags( $s ); }
function wp_trim_words( $s ) { return $s; }
function get_post_field() { return ''; }
class FakeTheme {
	public $d;
	function __construct( $d ) { $this->d = $d; }
	function get_template() { return $this->d['template']; }
	function get_stylesheet() { return $this->d['stylesheet']; }
	function get( $k ) { return $this->d[ $k ] ?? ''; }
	function parent() { return isset( $this->d['parent'] ) ? new FakeTheme( $this->d['parent'] ) : false; }
}
function wp_get_theme() { return new FakeTheme( $GLOBALS['theme'] ); }
$GLOBALS['theme'] = json_decode( getenv( 'PTFB_THEME' ) ?: '{"template":"fascinate","stylesheet":"fascinate-child","Name":"Fascinate Child","parent":{"template":"fascinate","stylesheet":"fascinate","Name":"Fascinate"}}', true );

require dirname( __DIR__ ) . '/page-turn-feedback.php';

$n        = (int) ( $argv[1] ?? 5 );
$interval = (int) ( $argv[2] ?? 7 );
$GLOBALS['opts'] = ( getenv( 'LSA_OPTS' ) ? json_decode( getenv( 'LSA_OPTS' ), true ) : array() ) + array( 'ptfb_speed' => '5', 'ptfb_title' => 'Cosa dicono di me', 'ptfb_style' => ptfb_sanitize_style( json_decode( $argv[3] ?? '[]', true ) ) );

$names = array( 'Giulia R.', 'Marco', 'Autrice Alice DC', 'Sara & Chiara', 'Elisa', 'Veronica', 'Anna', 'Laura', 'Ava', 'Federica', 'Paola', 'Irene' );
$texts = array(
	'Martina ha letto il mio romanzo con una cura rara: le sue parole mi hanno fatto sentire capita.',
	"Recensione puntuale e sincera, mi ha aiutata a scegliere il prossimo libro da leggere.\n\nUn blog che consiglio a tutte le amiche appassionate di romance.",
	'Professionale, gentile e velocissima nelle risposte. Una collaborazione che rifarei subito, senza esitazioni, perche\' unisce competenza e passione vera per i libri, e questo si sente in ogni riga che scrive. Grazie davvero di cuore per il tempo dedicato.',
	'Le sue consulenze di lettura sono sempre azzeccate.',
);
if ( getenv( 'PTFB_LONG' ) ) {
	$texts = array( str_repeat( 'asss', 60 ), 'Guarda qui https://www.esempio.it/una/url/molto/molto/lunga/che/non/va/a/capo/mai/e/continua/ancora/per/un/bel/po/di/caratteri/senza/spazi', 'Un commento normale, breve e con gli spazi al posto giusto.', 'Un altro commento normale.' );
}
$items = array();
for ( $i = 0; $i < $n; $i++ ) {
	$items[] = (object) array( 'post_title' => $names[ $i % count( $names ) ], 'post_content' => $texts[ $i % count( $texts ) ] );
}
$GLOBALS['items'] = $items;

echo '<style>' . ptfb_inline_css() . '</style>';
echo preg_replace( '/data-interval="\d+"/', 'data-interval="' . $interval . '"', ptfb_render() );
