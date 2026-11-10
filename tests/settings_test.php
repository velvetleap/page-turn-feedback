<?php
// Prova della logica delle impostazioni, fuori da WordPress (funzioni WP minime simulate).
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
require __DIR__ . '/i18n_stubs.php';
$IT = ( 'it' === getenv( 'PTFB_LANG' ) );
function register_post_type( $n, $a ) { $GLOBALS['cpt'] = $a; }
function wp_enqueue_style( $h ) { $GLOBALS['enq']['style'][] = $h; }
function wp_enqueue_script( $h, $src = '', $deps = array(), $ver = false, $foot = false ) { $GLOBALS['enq']['script'][ $h ] = array( 'src' => $src, 'deps' => $deps, 'ver' => $ver, 'footer' => $foot ); }
function plugins_url( $p ) { return $p; }
function get_current_screen() { return $GLOBALS['screen']; }
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

$ok = true;
function check( $label, $cond, $extra = '' ) { global $ok; echo ( $cond ? 'PASS ' : 'FAIL ' ) . $label . ' ' . $extra . "\n"; $ok = $ok && $cond; }
function with( $opts ) { $GLOBALS['opts'] = $opts; }

with( array() );
check( 'nuova installazione: 5 secondi', ptfb_get_interval() === 5 && ptfb_get_speed_choice() === '5' );

with( array( 'ptfb_speed' => '3' ) );
check( 'scelta 3 -> 3 s', ptfb_get_interval() === 3 );
with( array( 'ptfb_speed' => '10' ) );
check( 'scelta 10 -> 10 s', ptfb_get_interval() === 10 );
with( array( 'ptfb_speed' => 'custom', 'ptfb_custom' => 17 ) );
check( 'personalizzato 17 -> 17 s', ptfb_get_interval() === 17 );
with( array( 'ptfb_speed' => 'custom', 'ptfb_custom' => 999 ) );
check( 'personalizzato 999 -> limitato a 120', ptfb_get_interval() === 120 );
with( array( 'ptfb_speed' => 'custom', 'ptfb_custom' => 0 ) );
check( 'personalizzato 0 -> limitato a 2', ptfb_get_interval() === 2 );
with( array( 'ptfb_speed' => 'off' ) );
check( 'nessuno scorrimento -> 0', ptfb_get_interval() === 0 );
with( array( 'ptfb_speed' => 'boh' ) );
check( 'valore sconosciuto -> si ricade sul predefinito (5)', ptfb_get_interval() === 5 );

// sanificazione
check( 'sanifica velocita valida', ptfb_sanitize_speed( 'custom' ) === 'custom' );
check( 'sanifica velocita non valida', ptfb_sanitize_speed( '<script>' ) === '5' );
check( 'sanifica secondi 45', ptfb_sanitize_custom( '45' ) === 45 );
check( 'sanifica secondi 1 -> 2', ptfb_sanitize_custom( '1' ) === 2 );
check( 'sanifica secondi -30 -> 30', ptfb_sanitize_custom( '-30' ) === 30 );
with( array( 'ptfb_custom' => 22 ) );
check( 'campo secondi non inviato: resta 22', ptfb_sanitize_custom( null ) === 22 );

// pagina impostazioni
with( array( 'ptfb_speed' => 'custom', 'ptfb_custom' => 12 ) );
ob_start(); ptfb_settings_page(); $html = ob_get_clean();
check( 'pagina: opzione "custom" selezionata', strpos( $html, "value=\"custom\" selected='selected'" ) !== false );
check( 'pagina: campo secondi visibile e a 12', strpos( $html, 'value="12"' ) !== false && strpos( $html, 'id="ptfb_custom_wrap" hidden' ) === false );
with( array( 'ptfb_speed' => '10' ) );
ob_start(); ptfb_settings_page(); $html = ob_get_clean();
check( 'pagina: con 10 s il campo personalizzato e\' nascosto', strpos( $html, 'id="ptfb_custom_wrap" hidden' ) !== false );
check( 'pagina: 5 opzioni nel menu velocita (+ font)', preg_match_all( '/<select id="ptfb_speed".*?<\/select>/s', $html, $m ) && substr_count( $m[0][0], '<option' ) === 5 );

// ---------------------------------------------------------------- colori e font (1.3.0)
with( array() );
$d = ptfb_get_style();
check( 'predefiniti: verde #a5cca7 e bianco', $d['bg'] === '#a5cca7' && $d['title'] === '#ffffff' && $d['card'] === '#ffffff' && $d['accent'] === '#a5cca7' );
check( 'predefiniti: tutti i font "theme"', $d['font_title'] === 'theme' && $d['font_text'] === 'theme' && $d['font_name'] === 'theme' );
check( 'predefiniti: nessun CSS aggiunto', ptfb_inline_css() === '' );
check( 'titolo predefinito (tradotto)', ptfb_get_title() === ptfb_default_title() && ptfb_default_title() === ( $IT ? 'Cosa dicono di me' : 'What people say about me' ) );

$s = ptfb_sanitize_style( array( 'bg' => '#FF0000', 'title' => '000', 'card' => 'rosso', 'text' => '#12', 'name' => '#abc', 'accent' => 'url(x)' ) );
check( 'colore valido maiuscolo -> minuscolo', $s['bg'] === '#ff0000' );
check( 'colore senza # -> accettato', $s['title'] === '#000' );
check( 'colore non valido -> predefinito', $s['card'] === '#ffffff' && $s['text'] === '#333333' && $s['accent'] === '#a5cca7' );
check( 'colore breve #abc ok', $s['name'] === '#abc' );

$s = ptfb_sanitize_style( array( 'font_title' => 'palatino', 'font_text' => 'custom', 'font_text_custom' => 'Poppins; } body{display:none} /*', 'font_name' => 'hack' ) );
check( 'font valido accettato', $s['font_title'] === 'palatino' );
check( 'font sconosciuto -> theme', $s['font_name'] === 'theme' );
check( 'nome font a mano ripulito da ; { } ( ) / *', strpos( $s['font_text_custom'], ';' ) === false && strpos( $s['font_text_custom'], '{' ) === false && strpos( $s['font_text_custom'], '/' ) === false && strpos( $s['font_text_custom'], '*' ) === false, $s['font_text_custom'] );
check( 'sanifica un array vuoto / non array', ptfb_sanitize_style( 'x' ) === ptfb_style_defaults() );

with( array( 'ptfb_style' => ptfb_sanitize_style( array( 'bg' => '#223344', 'title' => '#ffeeaa', 'font_title' => 'palatino', 'font_text' => 'custom', 'font_text_custom' => 'Poppins', 'font_name' => 'heading' ) ) ) );
$css = ptfb_inline_css();
check( 'CSS: variabili solo per i colori cambiati', strpos( $css, '--ptfb-bg:#223344' ) !== false && strpos( $css, '--ptfb-title:#ffeeaa' ) !== false && strpos( $css, '--ptfb-card' ) === false );
check( 'CSS: font titolo Palatino', strpos( $css, '.ptfb__title{font-family:"Palatino Linotype"' ) !== false );
check( 'CSS: font commento personalizzato', strpos( $css, '.ptfb__quote{font-family:Poppins}' ) !== false );
check( 'CSS: font nome = font dei titoli del tema', strpos( $css, '.ptfb__author{font-family:var(--ptfb-heading-font, inherit)}' ) !== false );

with( array( 'ptfb_style' => array( 'font_text' => 'custom', 'font_text_custom' => '' ) ) );
check( 'font "altro" vuoto -> si lascia il font del tema', strpos( ptfb_inline_css(), '.ptfb__quote' ) === false );

with( array( 'ptfb_style' => 'danneggiato' ) );
check( 'opzione danneggiata -> predefiniti', ptfb_get_style() === ptfb_style_defaults() );

// pagina impostazioni
with( array( 'ptfb_style' => ptfb_sanitize_style( array( 'bg' => '#223344', 'font_text' => 'custom', 'font_text_custom' => 'Poppins' ) ) ) );
ob_start(); ptfb_settings_page(); $html = ob_get_clean();
check( 'pagina: 6 selettori colore', substr_count( $html, 'class="ptfb-color"' ) === 6 );
check( 'pagina: colore salvato mostrato', strpos( $html, 'value="#223344"' ) !== false );
check( 'pagina: colore predefinito per il pulsante "Predefinito"', substr_count( $html, 'data-default-color="#a5cca7"' ) === 2 );
check( 'pagina: 3 menu font', substr_count( $html, 'class="ptfb-font-select"' ) === 3 );
check( 'pagina: campo "altro" del commento visibile con Poppins', strpos( $html, 'id="ptfb_font_wrap_text">' ) !== false && strpos( $html, 'value="Poppins"' ) !== false );
check( 'pagina: campo "altro" del titolo nascosto', strpos( $html, 'id="ptfb_font_wrap_title" hidden' ) !== false );
check( 'pagina: titolo con il testo predefinito citato', strpos( $html, ptfb_default_title() ) !== false );
check( 'pagina: campi con name ptfb_style[...]', strpos( $html, 'name="ptfb_style[bg]"' ) !== false && strpos( $html, 'name="ptfb_style[font_title]"' ) !== false );
// il javascript inline della pagina impostazioni
check( 'pagina: niente variabili PHP non risolte', strpos( $html, '<?' ) === false );

// ---------------------------------------------------------------- 1.4.0: durata giro pagina, allineamenti, consiglio 300 caratteri
with( array() );
check( 'durata predefinita: 1,1 s = 1100 ms', ptfb_get_anim_choice() === '1.1' && ptfb_get_anim_ms() === 1100 );
with( array( 'ptfb_anim' => '0.6' ) );
check( 'durata veloce: 600 ms', ptfb_get_anim_ms() === 600 );
with( array( 'ptfb_anim' => '1.8' ) );
check( 'durata lenta: 1800 ms', ptfb_get_anim_ms() === 1800 );
with( array( 'ptfb_anim' => '2.6' ) );
check( 'durata molto lenta: 2600 ms', ptfb_get_anim_ms() === 2600 );
with( array( 'ptfb_anim' => 'custom', 'ptfb_anim_custom' => 3.4 ) );
check( 'durata personalizzata 3,4 s = 3400 ms', ptfb_get_anim_ms() === 3400 );
with( array( 'ptfb_anim' => 'custom', 'ptfb_anim_custom' => 99 ) );
check( 'durata personalizzata limitata a 5 s', ptfb_get_anim_ms() === 5000 );
with( array( 'ptfb_anim' => 'custom', 'ptfb_anim_custom' => 0 ) );
check( 'durata personalizzata limitata a 0,3 s', ptfb_get_anim_ms() === 300 );
with( array( 'ptfb_anim' => 'custom' ) );
check( 'personalizzata senza valore salvato: 1,5 s', ptfb_get_anim_ms() === 1500 );
with( array( 'ptfb_anim' => 'boh' ) );
check( 'durata sconosciuta -> predefinita', ptfb_get_anim_ms() === 1100 );
check( 'sanifica durata valida/non valida', ptfb_sanitize_anim( '1.8' ) === '1.8' && ptfb_sanitize_anim( '<x>' ) === '1.1' && ptfb_sanitize_anim( null ) === '1.1' );
check( 'sanifica secondi con la virgola "2,5" -> 2.5', ptfb_sanitize_anim_custom( '2,5' ) === 2.5 );
check( 'sanifica secondi 0.04 -> 0.3 e 12 -> 5', ptfb_sanitize_anim_custom( '0.04' ) === 0.3 && ptfb_sanitize_anim_custom( '12' ) === 5.0 );
with( array( 'ptfb_anim_custom' => 2.2 ) );
check( 'campo durata non inviato: resta 2,2', ptfb_sanitize_anim_custom( null ) === 2.2 );

with( array() );
$d = ptfb_get_style();
check( 'allineamenti predefiniti: commento al centro, nome a sinistra', $d['align_text'] === 'center' && $d['align_name'] === 'left' );
check( 'allineamenti predefiniti: nessun CSS aggiunto', ptfb_inline_css() === '' );
$s = ptfb_sanitize_style( array( 'align_text' => 'right', 'align_name' => 'center' ) );
check( 'sanifica allineamenti validi', $s['align_text'] === 'right' && $s['align_name'] === 'center' );
$s = ptfb_sanitize_style( array( 'align_text' => 'justify', 'align_name' => '<b>' ) );
check( 'allineamenti non validi -> predefiniti', $s['align_text'] === 'center' && $s['align_name'] === 'left' );
with( array( 'ptfb_style' => ptfb_sanitize_style( array( 'align_name' => 'center', 'align_text' => 'left' ) ) ) );
$css = ptfb_inline_css();
check( 'CSS: nome centrato', strpos( $css, 'section.ptfb .ptfb__author{text-align:center}' ) !== false );
check( 'CSS: commento a sinistra (diverso dal predefinito)', strpos( $css, '.ptfb__quote p{text-align:left}' ) !== false );
with( array( 'ptfb_style' => array( 'bg' => '#123456' ) ) ); // vecchio salvataggio senza allineamenti
check( 'vecchio salvataggio: allineamenti presi dai predefiniti', ptfb_get_style()['align_name'] === 'left' && ptfb_get_style()['align_text'] === 'center' );

with( array( 'ptfb_anim' => 'custom', 'ptfb_anim_custom' => 2.4, 'ptfb_style' => ptfb_sanitize_style( array( 'align_name' => 'right' ) ) ) );
ob_start(); ptfb_settings_page(); $html = ob_get_clean();
check( 'pagina: menu durata con 5 opzioni e "personalizzata" selezionata', preg_match( '/<select id="ptfb_anim".*?<\/select>/s', $html, $m ) && substr_count( $m[0], '<option' ) === 5 && strpos( $m[0], "value=\"custom\" selected='selected'" ) !== false );
check( 'pagina: campo secondi durata visibile a 2.4', strpos( $html, 'id="ptfb_anim_wrap">' ) !== false && strpos( $html, 'name="ptfb_anim_custom"' ) !== false && strpos( $html, 'value="2.4"' ) !== false );
check( 'pagina: durata a 0,3-5 con passo 0,1', strpos( $html, 'min="0.3" max="5" step="0.1"' ) !== false );
check( 'pagina: due menu di allineamento, nome = destra', strpos( $html, 'name="ptfb_style[align_text]"' ) !== false && preg_match( '/name="ptfb_style\[align_name\]".*?<\/select>/s', $html, $m2 ) && strpos( $m2[0], "value=\"right\" selected='selected'" ) !== false );
check( 'pagina: tre voci di allineamento per ogni menu', preg_match( '/name="ptfb_style\[align_text\]".*?<\/select>/s', $html, $m3 ) && substr_count( $m3[0], '<option' ) === 3 );
with( array() );
ob_start(); ptfb_settings_page(); $html = ob_get_clean();
check( 'pagina: con durata predefinita il campo secondi e\' nascosto', strpos( $html, 'id="ptfb_anim_wrap" hidden' ) !== false );
check( 'pagina: durata predefinita selezionata (1,1)', strpos( $html, "value=\"1.1\" selected='selected'" ) !== false );

// consiglio nel modulo del feedback (ora senza script inline: il contatore sta in assets/page-turn-feedback-admin.js)
ob_start(); ptfb_edit_help( (object) array( 'post_type' => 'ptfb_feedback' ) ); $tip = ob_get_clean();
check( 'editor: consiglio dei 300 caratteri presente', strpos( $tip, $IT ? '300 caratteri' : '300 characters' ) !== false && strpos( $tip, $IT ? 'Consiglio' : 'Tip' ) !== false );
check( 'editor: contatore live presente con data-limit 300', strpos( $tip, 'id="ptfb-count"' ) !== false && strpos( $tip, 'data-limit="300"' ) !== false );
check( 'editor: nessuno script inline', strpos( $tip, '<script' ) === false );
check( 'editor: il consiglio e\' solo un avviso (nessun blocco al salvataggio)', strpos( $tip, 'preventDefault' ) === false && strpos( $tip, 'maxlength' ) === false );
check( 'editor: grassetto e corsivo ammessi (wp_kses)', strpos( $tip, '<strong>' ) !== false && strpos( $tip, '<em>' ) !== false );
ob_start(); ptfb_edit_help( (object) array( 'post_type' => 'post' ) ); $other = ob_get_clean();
check( 'editor: nessun consiglio negli altri tipi di contenuto', $other === '' );

// pagina impostazioni: nessuno script inline, shortcode con <code>
with( array() );
ob_start(); ptfb_settings_page(); $html = ob_get_clean();
check( 'pagina: nessuno script inline', strpos( $html, '<script' ) === false );
check( 'pagina: shortcode nel paragrafo finale dentro <code>', strpos( $html, '<code>[page_turn_feedback]</code>' ) !== false );
check( 'pagina: segnaposto del font in lingua', strpos( $html, 'placeholder="' . ( $IT ? 'per esempio: Poppins' : 'for example: Poppins' ) . '"' ) !== false );

// caricamento dello script admin: solo dove serve, con le dipendenze giuste
$GLOBALS['ptfb_settings_hook'] = 'feedback_page_ptfb-settings';
$GLOBALS['enq'] = array(); $GLOBALS['inline_scripts'] = array(); $GLOBALS['screen'] = (object) array( 'post_type' => 'ptfb_feedback' );
ptfb_admin_assets( 'post.php' );
check( 'admin: nella modifica di un feedback lo script e\' caricato senza dipendenze, nel footer', isset( $GLOBALS['enq']['script']['page-turn-feedback-admin'] ) && array() === $GLOBALS['enq']['script']['page-turn-feedback-admin']['deps'] && true === $GLOBALS['enq']['script']['page-turn-feedback-admin']['footer'] && empty( $GLOBALS['enq']['style'] ) );
check( 'admin: versione = PTFB_VERSION e file in assets/', $GLOBALS['enq']['script']['page-turn-feedback-admin']['ver'] === PTFB_VERSION && $GLOBALS['enq']['script']['page-turn-feedback-admin']['src'] === 'assets/page-turn-feedback-admin.js' );
$boot = $GLOBALS['inline_scripts']['page-turn-feedback-admin']['before'][0] ?? '';
check( 'admin: dati ptfbAdmin (limite 300 e stringhe tradotte)', strpos( $boot, 'var ptfbAdmin = {' ) === 0 && strpos( $boot, '"limit":300' ) !== false && strpos( $boot, $IT ? 'Caratteri: %1$d' : 'Characters: %1$d' ) !== false );
$GLOBALS['enq'] = array(); $GLOBALS['screen'] = (object) array( 'post_type' => 'post' );
ptfb_admin_assets( 'post.php' );
check( 'admin: la modifica di un articolo normale non carica nulla', empty( $GLOBALS['enq'] ) );
ptfb_admin_assets( 'edit.php' );
check( 'admin: altre schermate non caricano nulla', empty( $GLOBALS['enq'] ) );
ptfb_admin_assets( 'feedback_page_ptfb-settings' );
check( 'admin: nelle impostazioni lo script dipende dal selettore colori', isset( $GLOBALS['enq']['script']['page-turn-feedback-admin'] ) && array( 'wp-color-picker' ) === $GLOBALS['enq']['script']['page-turn-feedback-admin']['deps'] && in_array( 'wp-color-picker', $GLOBALS['enq']['style'], true ) );

// etichette del tipo di contenuto
ptfb_register_cpt();
$L = $GLOBALS['cpt']['labels'];
check( 'tipo di contenuto: etichette tradotte', $L['add_new'] === ( $IT ? 'Aggiungi feedback' : 'Add feedback' ) && $L['all_items'] === ( $IT ? 'Tutti i feedback' : 'All feedback' ) && $L['not_found_in_trash'] === ( $IT ? 'Nessun feedback nel cestino.' : 'No feedback in the Trash.' ) );
check( 'colonne elenco tradotte', ptfb_admin_columns( array( 'cb' => 'x' ) )['ptfb_comment'] === ( $IT ? 'Commento' : 'Comment' ) );

// completezza della traduzione: ogni stringa semplice della pagina impostazioni e' nella lingua scelta, senza resti dell'altra
$itmap = ptfb_test_read_mo( dirname( __DIR__ ) . '/languages/page-turn-feedback-it_IT.mo' );
with( array( 'ptfb_anim' => 'custom', 'ptfb_speed' => 'custom' ) );
ob_start(); ptfb_settings_page(); $full = ob_get_clean();
$leaks = array(); $seen = 0;
foreach ( $itmap as $en => $it ) {
	if ( $en === $it || strlen( $en ) < 6 || preg_match( '/[%<]/', $en ) ) { continue; }
	$must = $IT ? $it : $en; $must_not = $IT ? $en : $it;
	$has = function ( $text ) use ( $full ) { return 1 === preg_match( '/(?<![\p{L}])' . preg_quote( htmlspecialchars( $text, ENT_QUOTES ), '/' ) . '(?![\p{L}])/u', $full ); };
	if ( $has( $must ) ) { $seen++; }
	if ( $has( $must_not ) ) { $leaks[] = $must_not; }
}
check( 'traduzione: nessun testo dell\'altra lingua nella pagina impostazioni', empty( $leaks ), implode( ' | ', $leaks ) );
check( 'traduzione: almeno 25 stringhe della lingua scelta trovate nella pagina', $seen >= 25, "trovate=$seen" );

echo $ok ? "TUTTO OK\n" : "CI SONO FALLIMENTI\n";
exit( $ok ? 0 : 1 );
