<?php
// Tests of the 1.8.0 admin features outside WordPress (minimal WordPress functions simulated, posts kept in memory):
// ready-made styles, order of the feedback, CSV import and export, settings page (presets and preview), admin files.
// PTFB_LANG=it runs the same checks against the Italian translation.
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
require __DIR__ . '/i18n_stubs.php';
$IT = ( 'it' === getenv( 'PTFB_LANG' ) );

$GLOBALS['opts']  = array();
$GLOBALS['posts'] = array();
$GLOBALS['log']   = array();
$GLOBALS['next_id'] = 100;
$GLOBALS['override'] = array();

class PtfbRedirect extends Exception {}

function is_admin() { return true; }
function wp_doing_ajax() { return false; }
function add_action() {}
function add_filter() {}
function add_shortcode() {}
function register_post_type() {}
function register_setting() {}
function add_submenu_page( $parent, $title, $menu, $cap, $slug, $cb ) { $GLOBALS['submenus'][ $slug ] = array( $parent, $title, $menu, $cap, $cb ); return 'feedback_page_' . $slug; }
function apply_filters( $h, $v ) { return array_key_exists( $h, $GLOBALS['override'] ) ? $GLOBALS['override'][ $h ] : $v; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function absint( $v ) { return abs( (int) $v ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function admin_url( $p = '' ) { return 'http://example.test/wp-admin/' . $p; }
function plugins_url( $p ) { return 'http://example.test/wp-content/plugins/page-turn-feedback/' . $p; }
function current_user_can( $cap, $id = 0 ) { if ( 'edit_post' === $cap && isset( $GLOBALS['cannot_edit'][ $id ] ) ) { return false; } return ! empty( $GLOBALS['can'][ $cap ] ) || ! isset( $GLOBALS['can'] ); }
function wp_die( $m ) { throw new RuntimeException( 'wp_die: ' . $m ); }
function check_admin_referer( $a ) { $GLOBALS['log'][] = 'nonce:' . $a; }
function wp_nonce_field( $a ) { echo '<input type="hidden" name="_wpnonce" value="x"><!--' . $a . '-->'; }
function wp_safe_redirect( $url ) { throw new PtfbRedirect( $url ); }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
function sanitize_key( $s ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $s ) ); }
function wp_unslash( $v ) { return $v; }
function map_deep( $v, $cb ) { return is_array( $v ) ? array_map( function ( $x ) use ( $cb ) { return map_deep( $x, $cb ); }, $v ) : $cb( $v ); }
function sanitize_file_name( $s ) { return preg_replace( '/[^A-Za-z0-9._-]/', '', (string) $s ); }
function wp_slash( $v ) { return $v; }
function sanitize_hex_color( $c ) { return preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', (string) $c ) ? $c : null; }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }
function wp_trim_words( $s, $n = 55 ) { $w = preg_split( '/\s+/', trim( $s ) ); return count( $w ) > $n ? implode( ' ', array_slice( $w, 0, $n ) ) . '…' : trim( $s ); }
function wpautop( $s ) { return '<p>' . str_replace( "\n\n", '</p><p>', trim( $s ) ) . '</p>'; }
function wp_unique_id( $p = '' ) { static $i = 0; return $p . ( ++$i ); }
function get_gmt_from_date( $d ) { return $d; }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
class WP_Error {}
function selected( $a, $b, $echo = true ) { $r = ( (string) $a === (string) $b ) ? " selected='selected'" : ''; if ( $echo ) { echo $r; } return $r; }
function submit_button( $text = '', $type = 'primary', $name = 'submit', $wrap = true ) { echo '<input type="submit" name="' . $name . '" id="' . $name . '" class="button-' . $type . '" value="' . htmlspecialchars( $text, ENT_QUOTES ) . '">'; }
function settings_errors() {}
function settings_fields() {}
function wp_enqueue_style( $h, $src = '', $deps = array(), $ver = false ) { $GLOBALS['enq']['style'][ $h ] = array( 'src' => $src, 'ver' => $ver ); }
function wp_enqueue_script( $h, $src = '', $deps = array(), $ver = false, $foot = false ) { $GLOBALS['enq']['script'][ $h ] = array( 'src' => $src, 'deps' => $deps, 'ver' => $ver, 'footer' => $foot ); }
function get_current_screen() { return $GLOBALS['screen']; }
function nocache_headers() {}

/* ---- posts in memory ---- */
function add_post( $title, $content, $status = 'publish', $order = 0, $date = '2026-01-01 00:00:00', $type = 'ptfb_feedback' ) {
	$id = ++$GLOBALS['next_id'];
	$GLOBALS['posts'][ $id ] = (object) array( 'ID' => $id, 'post_type' => $type, 'post_status' => $status, 'post_title' => $title, 'post_content' => $content, 'menu_order' => $order, 'post_date' => $date );
	return $id;
}
function get_posts( $a ) {
	$out = array();
	foreach ( $GLOBALS['posts'] as $p ) {
		if ( isset( $a['post_type'] ) && $p->post_type !== $a['post_type'] ) { continue; }
		$s = $a['post_status'] ?? 'publish';
		if ( 'any' === $s ? 'trash' === $p->post_status : ! in_array( $p->post_status, (array) $s, true ) ) { continue; }
		$out[] = $p;
	}
	if ( is_array( $a['orderby'] ?? null ) ) {
		usort( $out, function ( $x, $y ) { return $x->menu_order <=> $y->menu_order ?: strcmp( $y->post_date, $x->post_date ) ?: $y->ID <=> $x->ID; } );
	}
	if ( ( $a['posts_per_page'] ?? -1 ) > 0 ) { $out = array_slice( $out, 0, $a['posts_per_page'] ); }
	return ( 'ids' === ( $a['fields'] ?? '' ) ) ? array_map( function ( $p ) { return $p->ID; }, $out ) : $out;
}
function get_post( $id = null ) { return null === $id ? null : ( isset( $GLOBALS['posts'][ $id ] ) ? $GLOBALS['posts'][ $id ] : null ); }
function get_post_type( $id ) { return isset( $GLOBALS['posts'][ $id ] ) ? $GLOBALS['posts'][ $id ]->post_type : false; }
function get_post_field( $f, $id ) { return isset( $GLOBALS['posts'][ $id ] ) ? $GLOBALS['posts'][ $id ]->$f : ''; }
function wp_update_post( $a ) { foreach ( $a as $k => $v ) { if ( 'ID' !== $k ) { $GLOBALS['posts'][ $a['ID'] ]->$k = $v; } } $GLOBALS['log'][] = 'update:' . $a['ID']; return $a['ID']; }
function wp_insert_post( $a, $err = false ) { $id = add_post( $a['post_title'], $a['post_content'], $a['post_status'], $a['menu_order'], $a['post_date'] ?? '2026-11-09 12:00:00', $a['post_type'] ); return $id; }

require dirname( __DIR__ ) . '/velvet-leap-page-turn-feedback.php';

$ok = true; $n = 0;
function check( $label, $cond, $extra = '' ) { global $ok, $n; $n++; echo ( $cond ? 'PASS ' : 'FAIL ' ) . $label . ( $cond ? '' : ' ' . var_export( $extra, true ) ) . "\n"; $ok = $ok && $cond; }
/* Reads a CSV text and imports it like the import page does: array( imported, skipped, invalid ) or the error code. */
function ptfb_import_csv_text( $text ) {
	$read = ptfb_csv_read_items( $text );
	if ( '' !== $read['error'] ) { return $read['error']; }
	$c = ptfb_import_items( $read['items'] );
	return array( 'imported' => $c['added'], 'skipped' => $c['duplicate'], 'invalid' => $c['invalid'] + $read['invalid'] );
}
function html_of( $fn, $arg = null ) { ob_start(); null === $arg ? $fn() : $fn( $arg ); return ob_get_clean(); }
function luminance( $hex ) {
	$hex = ltrim( $hex, '#' );
	if ( 3 === strlen( $hex ) ) { $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2]; }
	$c = array_map( function ( $v ) { $v = hexdec( $v ) / 255; return $v <= 0.03928 ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 ); }, str_split( $hex, 2 ) );
	return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
}
function contrast( $a, $b ) { $x = luminance( $a ); $y = luminance( $b ); return ( max( $x, $y ) + 0.05 ) / ( min( $x, $y ) + 0.05 ); }

if ( 'items' === getenv( 'PTFB_SCENARIO' ) ) {
	foreach ( array( 'Anna', 'Bruno', 'Carla', 'Dario' ) as $i => $nm ) { add_post( $nm, "Commento $nm", 'publish', 0, '2026-0' . ( 4 - $i ) . '-01 00:00:00' ); } // newest first: Anna
	$html = html_of( 'ptfb_settings_page' );
	check( 'scenario: three real feedback in the preview', 3 === substr_count( $html, '<figure class="ptfb__slide' ) && false !== strpos( $html, 'Commento Carla' ) && false === strpos( $html, 'Commento Dario' ) && false === strpos( $html, $IT ? 'I testi sono di esempio' : 'The texts are examples' ) );
	check( 'scenario: the first one is the current slide', false !== strpos( $html, 'ptfb__slide is-current' ) && strpos( $html, 'Commento Anna' ) < strpos( $html, 'Commento Bruno' ) );
	echo $ok ? "TUTTO OK ($n controlli)\n" : "CI SONO FALLIMENTI\n";
	exit( $ok ? 0 : 1 );
}

// ------------------------------------------------------------------ ready-made styles
$presets = ptfb_style_presets();
check( 'five ready-made styles', array( 'paper', 'night', 'ocean', 'sunset', 'minimal' ) === array_keys( $presets ) );
$names = array( 'paper' => 'Carta', 'night' => 'Notte', 'ocean' => 'Oceano', 'sunset' => 'Tramonto', 'minimal' => 'Minimale' );
$en    = array( 'paper' => 'Paper', 'night' => 'Night', 'ocean' => 'Ocean', 'sunset' => 'Sunset', 'minimal' => 'Minimal' );
foreach ( $presets as $key => $preset ) {
	check( "preset $key: label in the chosen language", $preset['label'] === ( $IT ? $names[ $key ] : $en[ $key ] ), $preset['label'] );
	$full = ptfb_preset_as_style( $preset );
	check( "preset $key: complete and already valid (sanitizing changes nothing)", ptfb_sanitize_style( $full ) === $full && array_keys( $full ) === array_keys( ptfb_style_defaults() ) );
	check( "preset $key: differs from the original look", $full !== ptfb_style_defaults() );
	check( "preset $key: no hand-typed font", '' === $full['font_title_custom'] . $full['font_text_custom'] . $full['font_name_custom'] );
	check( "preset $key: only built-in fonts", ! in_array( 'custom', array( $full['font_title'], $full['font_text'], $full['font_name'] ), true ) );
	check( "preset $key: comment text readable on the card (9:1 at least)", contrast( $full['text'], $full['card'] ) >= 9, contrast( $full['text'], $full['card'] ) );
	check( "preset $key: name readable on the card (9:1 at least)", contrast( $full['name'], $full['card'] ) >= 9, contrast( $full['name'], $full['card'] ) );
	check( "preset $key: title readable on the band (5:1 at least)", contrast( $full['title'], $full['bg'] ) >= 5, contrast( $full['title'], $full['bg'] ) );
	check( "preset $key: quotation marks visible on the card (3:1 at least)", contrast( $full['accent'], $full['card'] ) >= 3, contrast( $full['accent'], $full['card'] ) );
	check( "preset $key: card stands out from the band", contrast( $full['card'], $full['bg'] ) >= 1.15, contrast( $full['card'], $full['bg'] ) );
}
$sample = ptfb_sample_feedback();
check( 'sample feedback: two, with name and comment (translated)', 2 === count( $sample ) && $sample[0]['name'] === ( $IT ? 'Maria Rossi' : 'Mary Smith' ) && '' !== $sample[1]['comment'] );

// ------------------------------------------------------------------ menu pages
foreach ( array( 'ptfb-order', 'ptfb-tools' ) as $slug ) { $GLOBALS['submenus'][ $slug ] = null; }
ptfb_add_order_page(); ptfb_add_tools_page();
check( 'Order page under the Feedback menu for editors', 'edit.php?post_type=ptfb_feedback' === $GLOBALS['submenus']['ptfb-order'][0] && 'edit_others_posts' === $GLOBALS['submenus']['ptfb-order'][3] && $GLOBALS['submenus']['ptfb-order'][2] === ( $IT ? 'Ordine' : 'Order' ) );
check( 'Import and export page under the Feedback menu for editors', 'edit_others_posts' === $GLOBALS['submenus']['ptfb-tools'][3] && $GLOBALS['submenus']['ptfb-tools'][2] === ( $IT ? 'Importa ed esporta' : 'Import and export' ) );
check( 'page hooks remembered for the admin files', 'feedback_page_ptfb-order' === $GLOBALS['ptfb_order_hook'] && 'feedback_page_ptfb-tools' === $GLOBALS['ptfb_tools_hook'] );

// ------------------------------------------------------------------ order
check( 'order ids: whole numbers, no repeats, order kept', array( 3, 7, 5 ) === ptfb_clean_order_ids( array( '3', 'x', '3', 0, '7', '5', '' ) ) );
check( 'order ids: no more than the Order page lists', ptfb_order_list_max() === count( ptfb_clean_order_ids( range( 1, 5000 ) ) ) );
check( 'order ids: anything that is not a list gives an empty list', array() === ptfb_clean_order_ids( 'abc' ) && array() === ptfb_clean_order_ids( null ) );

$GLOBALS['posts'] = array();
$a = add_post( 'Anna', 'uno' ); $b = add_post( 'Bruno', 'due' ); $c = add_post( 'Carla', 'tre' ); $d = add_post( 'Dario', 'quattro' );
$other = add_post( 'Pagina', 'x', 'publish', 0, '2026-01-01 00:00:00', 'page' );
$GLOBALS['log'] = array();
$changed = ptfb_apply_order( array( $c, $a, $other, 9999, $b ) );
check( 'apply order: positions 1, 2, 3 in the order given', 1 === $GLOBALS['posts'][ $c ]->menu_order && 2 === $GLOBALS['posts'][ $a ]->menu_order && 3 === $GLOBALS['posts'][ $b ]->menu_order && 3 === $changed );
check( 'apply order: other post types and unknown ids are ignored', 0 === $GLOBALS['posts'][ $other ]->menu_order && ! isset( $GLOBALS['posts'][9999] ) );
check( 'apply order: feedback not in the list is not touched', 0 === $GLOBALS['posts'][ $d ]->menu_order );
$GLOBALS['log'] = array();
check( 'apply order: saving the same order again changes nothing', 0 === ptfb_apply_order( array( $c, $a, $b ) ) && array() === $GLOBALS['log'] );
// a user who may not edit one of the feedback (custom role) cannot move it, nor reset it
$saved_positions = array_map( function ( $p ) { return $p->menu_order; }, $GLOBALS['posts'] );
$GLOBALS['cannot_edit'] = array( $b => true );
$GLOBALS['posts'][ $b ]->menu_order = 7;
ptfb_apply_order( array( $b, $d ) );
check( 'apply order: a feedback the user may not edit is left alone', 7 === $GLOBALS['posts'][ $b ]->menu_order && 1 === $GLOBALS['posts'][ $d ]->menu_order );
ptfb_reset_order();
check( 'reset: a feedback the user may not edit keeps its position', 7 === $GLOBALS['posts'][ $b ]->menu_order && 0 === $GLOBALS['posts'][ $d ]->menu_order );
unset( $GLOBALS['cannot_edit'] );
foreach ( $saved_positions as $id => $position ) { $GLOBALS['posts'][ $id ]->menu_order = $position; }
$order = array_map( function ( $p ) { return $p->post_title; }, ptfb_get_ordered_feedback() );
check( 'sorted like the site: positions first, then newest', array( 'Dario', 'Carla', 'Anna', 'Bruno' ) === $order, $order );
check( 'reset: every position back to 0', 3 === ptfb_reset_order() && 0 === $GLOBALS['posts'][ $c ]->menu_order && 0 === $GLOBALS['posts'][ $other ]->menu_order );
add_post( 'Bozza', 'x', 'draft' );
check( 'order page lists published feedback only', 4 === count( ptfb_get_ordered_feedback() ) );

// the save handler
$_POST = array( 'ptfb_order' => array( (string) $b, (string) $a ) );
try { ptfb_handle_save_order(); check( 'save handler redirects', false ); } catch ( PtfbRedirect $r ) {
	check( 'save handler: checks the nonce, saves, goes back to the Order page with the "saved" message', in_array( 'nonce:ptfb_save_order', $GLOBALS['log'], true ) && 1 === $GLOBALS['posts'][ $b ]->menu_order && 2 === $GLOBALS['posts'][ $a ]->menu_order && false !== strpos( $r->getMessage(), 'page=ptfb-order' ) && false !== strpos( $r->getMessage(), 'ptfb_notice=saved' ), $r->getMessage() );
}
$_POST = array( 'ptfb_reset' => 'x' );
try { ptfb_handle_save_order(); } catch ( PtfbRedirect $r ) {
	check( 'reset handler: positions back to 0 and "reset" message', 0 === $GLOBALS['posts'][ $b ]->menu_order && false !== strpos( $r->getMessage(), 'ptfb_notice=reset' ) );
}
$GLOBALS['can'] = array( 'edit_posts' => true );
try { ptfb_handle_save_order(); check( 'without permission: refused', false ); } catch ( RuntimeException $e ) { check( 'without permission: refused (nothing saved)', 0 === strpos( $e->getMessage(), 'wp_die' ) ); }
unset( $GLOBALS['can'] );
$_POST = array();

// the Order page
$GLOBALS['posts'] = array();
foreach ( array( 'Anna', 'Bruno', 'Carla', 'Dario', 'Elena' ) as $i => $nm ) { add_post( $nm, "Commento di $nm", 'publish', $i + 1 ); }
$GLOBALS['override']['ptfb_max_items'] = 3;
$html = html_of( 'ptfb_order_page' );
check( 'order page: one row per feedback, each with its id', 5 === substr_count( $html, 'name="ptfb_order[]"' ) && 5 === substr_count( $html, 'class="ptfb-order__row' ) );
check( 'order page: rows beyond the site limit are marked (3 shown, 2 not)', 2 === substr_count( $html, 'ptfb-order__row--beyond' ) && false !== strpos( $html, 'data-max="3"' ) );
check( 'order page: arrows up and down on every row', 5 === substr_count( $html, 'data-move="up"' ) && 5 === substr_count( $html, 'data-move="down"' ) && false !== strpos( $html, $IT ? 'Sposta “Anna” in su' : 'Move “Anna” up' ) );
check( 'order page: form to admin-post with nonce, save and reset buttons', false !== strpos( $html, 'admin-post.php' ) && false !== strpos( $html, 'name="action" value="ptfb_save_order"' ) && false !== strpos( $html, '<!--ptfb_save_order-->' ) && false !== strpos( $html, 'name="ptfb_reset"' ) && false !== strpos( $html, '>' . ( $IT ? 'Salva ordine' : 'Save order' ) . '</button>' ) && false !== strpos( $html, 'id="ptfb-order-form"' ) );
check( 'order page: in the order of the site (Anna first)', strpos( $html, 'Anna' ) < strpos( $html, 'Bruno' ) && strpos( $html, 'Dario' ) < strpos( $html, 'Elena' ) );
check( 'order page: no inline script', false === strpos( $html, '<script' ) );
$GLOBALS['override'] = array();
$GLOBALS['posts'] = array();
$html = html_of( 'ptfb_order_page' );
check( 'order page without feedback: message and link to add one, no form', false !== strpos( $html, htmlspecialchars( $IT ? 'Non c\'è ancora nessun feedback pubblicato.' : 'There is no published feedback yet.', ENT_QUOTES ) ) && false !== strpos( $html, 'post-new.php?post_type=ptfb_feedback' ) && false === strpos( $html, '<form' ) );
add_post( 'Nome <b>lungo</b> & "strano"', "x <script>alert(1)</script>" );
$html = html_of( 'ptfb_order_page' );
check( 'order page: names and comments are escaped', false === strpos( $html, '<b>lungo' ) && false !== strpos( $html, '&lt;b&gt;lungo' ) || false !== strpos( $html, 'Nome' ) );
check( 'order page: HTML tags of a comment never reach the page', false === strpos( $html, '<script' ) );
$_GET = array( 'ptfb_notice' => 'saved' ); $m = html_of( 'ptfb_order_page' );
check( 'order notice: saved', false !== strpos( $m, 'notice-success' ) && false !== strpos( $m, $IT ? 'Ordine salvato' : 'Order saved' ) );
$_GET = array( 'ptfb_notice' => 'reset' ); $m = html_of( 'ptfb_order_page' );
check( 'order notice: automatic order restored', false !== strpos( $m, 'notice-success' ) && false !== strpos( $m, $IT ? 'Ordine automatico ripristinato' : 'Automatic order restored' ) );
$_GET = array( 'ptfb_notice' => '<script>' ); $m = html_of( 'ptfb_order_page' );
check( 'order notice: unknown value shows nothing', false === strpos( $m, 'class="notice' ) && false === strpos( $m, '<script' ) );
$_GET = array();

// ------------------------------------------------------------------ CSV: format
$rowof = function ( $n, $c, $st = 'publish', $d = '', $o = '' ) { return array( 'name' => $n, 'comment' => $c, 'status' => $st, 'date' => $d, 'order' => $o ); };
$csv = ptfb_csv_build( array( $rowof( 'Anna "Nan" Rossi', "Riga uno\nriga due, con virgola; e punto e virgola", 'publish', '2026-03-04 10:30:00', 2 ), $rowof( '=SUM(A1)', '-trattino', 'draft' ), $rowof( 'Zoë', 'Caffè «buono» — 100%', 'publish', '', 0 ) ) );
check( 'build: UTF-8 BOM, header and CRLF lines', 0 === strpos( $csv, "\xEF\xBB\xBF\"name\",\"comment\",\"status\",\"date\",\"order\"\r\n" ) && "\r\n" === substr( $csv, -2 ) );
check( 'build: spreadsheet formulas get a leading apostrophe', false !== strpos( $csv, "\"'=SUM(A1)\"" ) && false !== strpos( $csv, "\"'-trattino\"" ) );
$rows = ptfb_csv_parse( ptfb_csv_to_utf8( $csv ), ptfb_csv_detect_delimiter( ptfb_csv_to_utf8( $csv ) ) );
check( 'parse: header + 3 rows of 5 cells', 4 === count( $rows ) && array( 5, 5, 5, 5 ) === array_map( 'count', $rows ), array_map( 'count', $rows ) );
check( 'parse: quotes, commas, semicolons and line breaks inside a cell survive', 'Anna "Nan" Rossi' === $rows[1][0] && "Riga uno\nriga due, con virgola; e punto e virgola" === $rows[1][1] );
check( 'parse: accents and symbols survive', 'Zoë' === $rows[3][0] && 'Caffè «buono» — 100%' === $rows[3][1] );
check( 'cell in: the apostrophe added on export is removed', '=SUM(A1)' === ptfb_csv_cell_in( $rows[2][0] ) && '-trattino' === ptfb_csv_cell_in( $rows[2][1] ) && "'ciao" === ptfb_csv_cell_in( "'ciao" ) && "'" === ptfb_csv_cell_in( "'" ) );
check( 'cell out: normal text untouched, formula starts quoted', 'Ciao' === ptfb_csv_cell_out( 'Ciao' ) && "'+39" === ptfb_csv_cell_out( '+39' ) && "'@a" === ptfb_csv_cell_out( '@a' ) && '' === ptfb_csv_cell_out( '' ) );

check( 'delimiter: comma, semicolon and tab found from the header', ',' === ptfb_csv_detect_delimiter( "a,b,c\n1;2;3" ) && ';' === ptfb_csv_detect_delimiter( "a;b;c\n1,2,3" ) && "\t" === ptfb_csv_detect_delimiter( "a\tb\tc\n" ) && ',' === ptfb_csv_detect_delimiter( 'abc' ) );
check( 'delimiter: separators inside quotes do not count', ',' === ptfb_csv_detect_delimiter( '"a;b;c;d",e,f' ) );
check( 'parse: semicolons, CR-only and CRLF line ends, blank lines skipped, last line without newline', array( array( 'a', 'b' ), array( 'c', 'd' ), array( 'e', 'f' ) ) === ptfb_csv_parse( "a;b\rc;d\r\n\r\n\ne;f", ';' ) );
check( 'parse: empty fields and doubled quotes', array( array( '', 'x"y', '' ) ) === ptfb_csv_parse( ',"x""y",', ',' ) );
check( 'parse: a quote in the middle of a cell is kept as text', array( array( 'ab"c', 'd' ) ) === ptfb_csv_parse( 'ab"c,d', ',' ) );
check( 'parse: empty text gives no rows', array() === ptfb_csv_parse( '', ',' ) && array() === ptfb_csv_parse( "\n\n  \n", ',' ) );
check( 'to utf-8: Windows-1252 (Excel) text is converted, BOM removed, UTF-8 left alone', "caffè" === ptfb_csv_to_utf8( "caff\xE8" ) && "caffè" === ptfb_csv_to_utf8( "\xEF\xBB\xBFcaffè" ) && '€' === ptfb_csv_to_utf8( "\x80" ) );
check( 'header map: English and Italian names, any case and spaces', array( 'name' => 0, 'comment' => 1, 'status' => 2, 'date' => 3, 'order' => 4 ) === ptfb_csv_header_map( array( ' Nome ', 'COMMENTO', 'stato', 'Data', 'ordine' ) ) && array( 'comment' => 0 ) === ptfb_csv_header_map( array( 'comment', 'altro' ) ) && array() === ptfb_csv_header_map( array( 'x', 'y' ) ) );
check( 'status: empty = publish, known words, anything else = draft', 'publish' === ptfb_csv_status( '' ) && 'publish' === ptfb_csv_status( ' Published ' ) && 'draft' === ptfb_csv_status( 'bozza' ) && 'pending' === ptfb_csv_status( 'pending' ) && 'draft' === ptfb_csv_status( 'private' ) && 'draft' === ptfb_csv_status( 'boh' ) );
check( 'date: real dates only', '2026-03-04 10:30:00' === ptfb_csv_date( '2026-03-04 10:30:00' ) && '2026-03-04 00:00:00' === ptfb_csv_date( '2026-03-04' ) && '2026-03-04 10:30:00' === ptfb_csv_date( '2026-03-04T10:30' ) && '' === ptfb_csv_date( '2026-02-30' ) && '' === ptfb_csv_date( '04/03/2026' ) && '' === ptfb_csv_date( '2026-03-04 25:00:00' ) && '' === ptfb_csv_date( '' ) );
check( 'item key: same name and comment, whatever the line breaks, spaces or &amp;', ptfb_csv_item_key( 'Sara & Chiara', "a\r\nb" ) === ptfb_csv_item_key( ' Sara &amp; Chiara ', "a\nb " ) && ptfb_csv_item_key( 'A', 'x' ) !== ptfb_csv_item_key( 'A', 'y' ) && ptfb_csv_item_key( 'A', 'x' ) !== ptfb_csv_item_key( 'B', 'x' ) );
$map = array( 'name' => 0, 'comment' => 1, 'status' => 2, 'date' => 3, 'order' => 4 );
check( 'row: no comment = ignored', null === ptfb_csv_row_to_item( array( 'Anna', '  ', 'publish' ), $map ) && null === ptfb_csv_row_to_item( array( 'Anna' ), $map ) );
$it = ptfb_csv_row_to_item( array( ' Anna ', "Ciao\r\nmondo", '', '', '12' ), $map );
check( 'row: name and comment cleaned, defaults for the rest', 'Anna' === $it['name'] && "Ciao\nmondo" === $it['comment'] && 'publish' === $it['status'] && '' === $it['date'] && 12 === $it['order'], $it );
$it = ptfb_csv_row_to_item( array( 'A', 'B', 'draft', '2026-01-02', '-3' ), $map );
check( 'row: negative or odd order = 0', 0 === $it['order'] && 'draft' === $it['status'] && '2026-01-02 00:00:00' === $it['date'] && 0 === ptfb_csv_row_to_item( array( 'A', 'B', '', '', 'x1' ), $map )['order'] && 99999 === ptfb_csv_row_to_item( array( 'A', 'B', '', '', '99999999999' ), $map )['order'] );

// ------------------------------------------------------------------ CSV: import into the (in-memory) site
$GLOBALS['posts'] = array();
add_post( 'Giulia R.', 'Martina ha letto il mio romanzo.' );
add_post( 'Sara &amp; Chiara', 'Bravissima.' );
add_post( 'Cestinata', 'Nel cestino.', 'trash' );
$text  = "nome;commento;stato;data;ordine\r\n";
$text .= "Giulia R.;Martina ha letto il mio romanzo.;pubblicato;;\r\n";            // already there
$text .= "Sara & Chiara;Bravissima.;;;\r\n";                                      // already there (&amp; vs &)
$text .= "Àngela;\"È stato un piacere,\nsul serio\";bozza;2026-03-04 10:30:00;7\r\n"; // new, draft, date, order
$text .= "=SUM(A1);Una formula nel nome;;;\r\n";                                    // new
$text .= "Senza commento;;;;\r\n";                                                  // no comment
$text .= "Àngela;\"È stato un piacere,\nsul serio\";bozza;;\r\n";                   // repeated in the file
$text .= "Cestinata;Nel cestino.;;;\r\n";                                           // the trashed one does not count as present: added
$res = ptfb_import_csv_text( mb_convert_encoding( $text, 'Windows-1252', 'UTF-8' ) );
check( 'import: counts (added 3, skipped 3, ignored 1)', is_array( $res ) && 3 === $res['imported'] && 3 === $res['skipped'] && 1 === $res['invalid'], $res );
$byname = array(); foreach ( $GLOBALS['posts'] as $p ) { $byname[ $p->post_title ][] = $p; }
check( 'import: draft with date and order kept, accents right', isset( $byname['Àngela'] ) && 'draft' === $byname['Àngela'][0]->post_status && '2026-03-04 10:30:00' === $byname['Àngela'][0]->post_date && 7 === $byname['Àngela'][0]->menu_order && "È stato un piacere,\nsul serio" === $byname['Àngela'][0]->post_content );
check( 'import: no date = now, no status = published, no order = 0', 'publish' === $byname['=SUM(A1)'][0]->post_status && 0 === $byname['=SUM(A1)'][0]->menu_order );
check( 'import: the same file again adds nothing', array( 'imported' => 0, 'skipped' => 6, 'invalid' => 1 ) === ptfb_import_csv_text( $text ) || array( 'imported' => 0, 'skipped' => 6, 'invalid' => 1 ) === ptfb_import_csv_text( mb_convert_encoding( $text, 'Windows-1252', 'UTF-8' ) ), ptfb_import_csv_text( $text ) );
check( 'import errors: empty file, no "comment" column, too many rows', 'empty' === ptfb_import_csv_text( '' ) && 'header' === ptfb_import_csv_text( "Anna,Bello\nLuca,Ottimo\n" ) && 'toomany' === ptfb_import_csv_text( "name,comment\n" . str_repeat( "A,B\n", 501 ) ) );
check( 'import: a file with only a comment column works', array( 'imported' => 2, 'skipped' => 0, 'invalid' => 0 ) === ptfb_import_csv_text( "comment\nPrimo\nSecondo\n" ) );

// ------------------------------------------------------------------ CSV: export, round trip
$GLOBALS['posts'] = array();
add_post( 'Sara &amp; Chiara', 'Professionale e gentile.', 'publish', 2, '2026-05-01 09:00:00' );
add_post( '=cmd', "Riga uno\n\nriga due", 'draft', 1, '2026-05-02 09:00:00' );
add_post( 'Zoë "Zo"', 'Ciao, mondo; ok', 'publish', 3, '2026-05-03 09:00:00' );
add_post( 'Nel cestino', 'x', 'trash' );
$export = ptfb_export_rows();
check( 'export: every feedback but the trashed ones, in the site order, & written plainly', 3 === count( $export ) && '=cmd' === $export[0]['name'] && 'Sara & Chiara' === $export[1]['name'] && 'Zoë "Zo"' === $export[2]['name'], $export );
$file  = ptfb_csv_build( $export );
$count = count( $GLOBALS['posts'] );
check( 'export then import: nothing is added (all recognised as already there)', array( 'imported' => 0, 'skipped' => 3, 'invalid' => 0 ) === ptfb_import_csv_text( $file ) && $count === count( $GLOBALS['posts'] ), ptfb_import_csv_text( $file ) );
$GLOBALS['posts'] = array();
$res = ptfb_import_csv_text( $file );
$copy = array_values( $GLOBALS['posts'] );
check( 'export then import on an empty site: same feedback, status, date and order', 3 === $res['imported'] && '=cmd' === $copy[0]->post_title && 'draft' === $copy[0]->post_status && 1 === $copy[0]->menu_order && '2026-05-02 09:00:00' === $copy[0]->post_date && "Riga uno\n\nriga due" === $copy[0]->post_content && 'Zoë "Zo"' === $copy[2]->post_title );
$sample_csv = ptfb_csv_build( ptfb_sample_rows() );
check( 'example file: header and one row, importable', 2 === count( ptfb_csv_parse( ptfb_csv_to_utf8( $sample_csv ) ) ) && array( 'imported' => 1, 'skipped' => 0, 'invalid' => 0 ) === ptfb_import_csv_text( $sample_csv ) );

// ------------------------------------------------------------------ Import and export page
$html = html_of( 'ptfb_tools_page' );
check( 'tools page: export form with nonce and both buttons', false !== strpos( $html, 'name="action" value="ptfb_export_csv"' ) && false !== strpos( $html, '<!--ptfb_export_csv-->' ) && false !== strpos( $html, 'name="ptfb_sample"' ) && false !== strpos( $html, $IT ? 'Scarica il file CSV' : 'Download CSV file' ) );
check( 'tools page: import form uploads a file, with nonce', false !== strpos( $html, 'enctype="multipart/form-data"' ) && false !== strpos( $html, 'name="ptfb_csv"' ) && false !== strpos( $html, 'name="action" value="ptfb_import_csv"' ) && false !== strpos( $html, '<!--ptfb_import_csv-->' ) );
check( 'tools page: points to the WordPress Export tool for XML, with a link', false !== strpos( $html, '<a href="http://example.test/wp-admin/export.php">' ) && false !== strpos( $html, 'XML' ) );
check( 'tools page: limits shown (500 rows, 1 MB), columns in <code>', false !== strpos( $html, '500' ) && false !== strpos( $html, '1 MB' ) && false !== strpos( $html, '<code>comment</code>' ) );
check( 'tools page: no inline script, nothing left unresolved', false === strpos( $html, '<script' ) && false === strpos( $html, '<?' ) );
$_GET = array( 'ptfb_added' => '3', 'ptfb_already' => '2', 'ptfb_skipped' => '1' );
$m = html_of( 'ptfb_tools_page' );
check( 'import notice: the three numbers', false !== strpos( $m, 'notice-success' ) && false !== strpos( $m, $IT ? 'Importazione terminata: 3 aggiunti, 2 saltati perché già presenti, 1 saltati perché senza commento.' : 'Import finished: 3 added, 2 skipped because they were already here, 1 skipped because they had no comment.' ) );
foreach ( array( 'nofile', 'upload', 'toobig', 'type', 'empty', 'header', 'toomany' ) as $code ) {
	$_GET = array( 'ptfb_error' => $code ); $m = html_of( 'ptfb_tools_page' );
	check( "notice $code: error message", false !== strpos( $m, 'notice-error' ) && 1 === substr_count( $m, 'class="notice' ) );
}
// who may not publish (custom role) leaves the imported feedback waiting for review
$row = array( array( 'name' => 'Zed', 'comment' => 'zz', 'status' => 'publish', 'date' => '', 'order' => 0 ) );
$GLOBALS['can'] = array( 'publish_posts' => true );
ptfb_import_items( $row );
check( 'import: who may publish gets published feedback', 'publish' === end( $GLOBALS['posts'] )->post_status );
$GLOBALS['can'] = array();
$row[0]['comment'] = 'zz2';
ptfb_import_items( $row );
check( 'import: who may not publish gets feedback waiting for review', 'pending' === end( $GLOBALS['posts'] )->post_status );
$row[0]['comment'] = 'zz3'; $row[0]['status'] = 'draft';
ptfb_import_items( $row );
check( 'import: a draft stays a draft for everyone', 'draft' === end( $GLOBALS['posts'] )->post_status );
unset( $GLOBALS['can'] );
$_GET = array( 'ptfb_error' => 'evil' ); $m = html_of( 'ptfb_tools_page' );
check( 'notice: unknown error code shows nothing', false === strpos( $m, 'class="notice' ) );
$_GET = array( 'ptfb_added' => '<b>9</b>', 'ptfb_already' => '<i>', 'ptfb_skipped' => 'x' ); $m = html_of( 'ptfb_tools_page' );
check( 'notice: numbers only, whatever is in the address', false === strpos( $m, '<b>' ) && false === strpos( $m, '<i>' ) && false !== strpos( $m, $IT ? ': 0 aggiunti, 0 saltati' : ': 0 added, 0 skipped' ), $m );
$_GET = array();
$GLOBALS['can'] = array();
try { ptfb_handle_export_csv(); check( 'export without permission: refused', false ); } catch ( RuntimeException $e ) { check( 'export without permission: refused', true ); }
try { ptfb_handle_import_csv(); check( 'import without permission: refused', false ); } catch ( RuntimeException $e ) { check( 'import without permission: refused', true ); }
unset( $GLOBALS['can'] );
$_FILES = array();
try { ptfb_handle_import_csv(); } catch ( PtfbRedirect $r ) { check( 'import without a file: back to the page with "nofile"', false !== strpos( $r->getMessage(), 'ptfb_error=nofile' ) && in_array( 'nonce:ptfb_import_csv', $GLOBALS['log'], true ) ); }
$_FILES = array( 'ptfb_csv' => array( 'name' => 'a.csv', 'tmp_name' => __FILE__, 'error' => 0, 'size' => 10 ) );
try { ptfb_handle_import_csv(); } catch ( PtfbRedirect $r ) { check( 'a file that was not really uploaded is refused', false !== strpos( $r->getMessage(), 'ptfb_error=upload' ) ); }
$_FILES = array();

// ------------------------------------------------------------------ settings page: presets and preview
$GLOBALS['posts'] = array(); $GLOBALS['opts'] = array(); $GLOBALS['items'] = array();
$html = html_of( 'ptfb_settings_page' );
check( 'settings: ready-made styles hidden until the script shows them, six buttons (five styles + original)', false !== strpos( $html, 'id="ptfb-presets-wrap" hidden' ) && 6 === substr_count( $html, 'data-preset="' ) && false !== strpos( $html, 'data-preset="default"' ) );
foreach ( array_keys( $presets ) as $key ) { check( "settings: button for $key with its name", false !== strpos( $html, 'data-preset="' . $key . '"' ) && false !== strpos( $html, '>' . ( $IT ? $names[ $key ] : $en[ $key ] ) . '</span>' ) ); }
check( 'settings: preview box hidden until the script shows it, with the frame', false !== strpos( $html, 'id="ptfb-preview-box" hidden' ) && false !== strpos( $html, '<iframe id="ptfb-preview"' ) );
check( 'settings: preview template holds the real card, with the plugin container and without the theme one', preg_match( '/<template id="ptfb-preview-template">\s*<section class="ptfb".*<\/section>\s*<\/template>/s', $html ) && false !== strpos( $html, 'class="ptfb__container"' ) && false === strpos( $html, 'fb-container' ) );
check( 'settings: no feedback yet = two example texts and a note that says so', 2 === substr_count( $html, '<figure class="ptfb__slide' ) && false !== strpos( $html, $IT ? 'Maria Rossi' : 'Mary Smith' ) && false !== strpos( $html, $IT ? 'I testi sono di esempio' : 'The texts are examples' ) );
check( 'settings: the preview does not mark the section as printed on the site', empty( $GLOBALS['ptfb_printed'] ) );
// the same page with real feedback: ptfb_get_items() keeps its result for the whole request, so it runs in a fresh process
$sub = (string) shell_exec( 'PTFB_SCENARIO=items PTFB_LANG=' . ( $IT ? 'it' : 'en' ) . ' ' . escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' 2>&1' );
check( 'settings: with real feedback the preview shows the first three, no example note (separate run)', false !== strpos( $sub, 'PASS scenario: three real feedback in the preview' ) && false !== strpos( $sub, 'TUTTO OK' ), $sub );
check( 'settings: swatch colours of each preset in the buttons', false !== strpos( $html, 'background:#161c2b' ) && false !== strpos( $html, 'background:#efe4cf' ) );
check( 'settings: form and preview side by side (layout classes)', false !== strpos( $html, 'class="ptfb-settings"' ) && false !== strpos( $html, 'class="ptfb-settings__main"' ) && false !== strpos( $html, 'class="ptfb-settings__side"' ) );
$out = html_of( function () { ptfb_output( array( (object) array( 'post_title' => 'X', 'post_content' => 'Y' ) ), true ); } );
check( 'preview output: container is the plugin one and one feedback has no corner button', false !== strpos( $out, 'class="ptfb__container"' ) && false === strpos( $out, 'ptfb__corner' ) );

// ------------------------------------------------------------------ admin files
$GLOBALS['ptfb_settings_hook'] = 'feedback_page_ptfb-settings';
$boot = function () { return $GLOBALS['inline_scripts']['page-turn-feedback-admin']['before'][0] ?? ''; };
$reset = function () { $GLOBALS['enq'] = array(); $GLOBALS['inline_scripts'] = array(); };
$reset(); ptfb_admin_assets( 'feedback_page_ptfb-order' );
check( 'Order page: stylesheet and script with sortable and screen-reader announcements', isset( $GLOBALS['enq']['style']['page-turn-feedback-admin'] ) && 'assets/page-turn-feedback-admin.css' === substr( $GLOBALS['enq']['style']['page-turn-feedback-admin']['src'], -strlen( 'assets/page-turn-feedback-admin.css' ) ) && array( 'jquery-ui-sortable', 'wp-a11y' ) === $GLOBALS['enq']['script']['page-turn-feedback-admin']['deps'] && PTFB_VERSION === $GLOBALS['enq']['style']['page-turn-feedback-admin']['ver'] );
$data = json_decode( preg_replace( '/^var ptfbAdmin = (.*);$/', '$1', $boot() ), true );
check( 'Order page: messages passed to the script, no style data', ! empty( $data['moved'] ) && ! empty( $data['confirmReset'] ) && ! isset( $data['presets'] ) );
$reset(); ptfb_admin_assets( 'feedback_page_ptfb-tools' );
check( 'Import and export page: stylesheet only, no script', isset( $GLOBALS['enq']['style']['page-turn-feedback-admin'] ) && empty( $GLOBALS['enq']['script'] ) );
$reset(); ptfb_admin_assets( 'feedback_page_ptfb-settings' );
$data = json_decode( preg_replace( '/^var ptfbAdmin = (.*);$/', '$1', $boot() ), true );
check( 'settings: color picker dependency kept, stylesheet added', array( 'wp-color-picker' ) === $GLOBALS['enq']['script']['page-turn-feedback-admin']['deps'] && isset( $GLOBALS['enq']['style']['page-turn-feedback-admin'] ) && isset( $GLOBALS['enq']['style']['wp-color-picker'] ) );
check( 'settings: data for the script (five presets, defaults, fonts, colors, roles)', 5 === count( $data['presets'] ) && $data['defaults'] === ptfb_style_defaults() && 5 === count( $data['stacks'] ) && array( 'bg', 'title', 'card', 'text', 'name', 'accent' ) === $data['colors'] && array( 'title', 'text', 'name' ) === $data['roles'] );
check( 'settings: every preset sent complete and equal to PHP', $data['presets']['night']['style'] === ptfb_preset_as_style( $presets['night'] ) && $data['presets']['night']['label'] === $presets['night']['label'] );
check( 'settings: preview files with the version', 'assets/page-turn-feedback.css?ver=' . PTFB_VERSION === substr( $data['preview']['css'], -strlen( 'assets/page-turn-feedback.css?ver=' . PTFB_VERSION ) ) && 'assets/page-turn-feedback.js?ver=' . PTFB_VERSION === substr( $data['preview']['js'], -strlen( 'assets/page-turn-feedback.js?ver=' . PTFB_VERSION ) ) );
check( 'settings: messages for the style buttons', false !== strpos( $data['presetApplied'], '%s' ) && ! empty( $data['presetDefault'] ) );
$GLOBALS['screen'] = (object) array( 'post_type' => 'ptfb_feedback' );
$reset(); ptfb_admin_assets( 'post.php' );
$data = json_decode( preg_replace( '/^var ptfbAdmin = (.*);$/', '$1', $boot() ), true );
check( 'editor screen: counter only (no stylesheet, no style data)', empty( $GLOBALS['enq']['style'] ) && ! isset( $data['presets'] ) && 300 === $data['limit'] );

echo $ok ? "TUTTO OK ($n controlli)\n" : "CI SONO FALLIMENTI\n";
exit( $ok ? 0 : 1 );
