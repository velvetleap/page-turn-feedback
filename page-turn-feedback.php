<?php
/**
 * Plugin Name: Page Turn Feedback
 * Plugin URI:  https://github.com/velvetleap/page-turn-feedback
 * Description: Aggiunge la voce "Feedback" nel menu di WordPress e mostra i feedback in una scheda che gira pagina con una piega da libro, in fondo alla home sopra il footer.
 * Version:     1.5.0
 * Requires at least: 5.0.3
 * Author:      Velvet Leap
 * Author URI:  https://github.com/velvetleap
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: page-turn-feedback
 */

/*
 * Copyright (C) 2026 Mattia Scudellaro
 *
 * This program is free software; you can redistribute it and/or modify it under the terms
 * of the GNU General Public License as published by the Free Software Foundation; either
 * version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PTFB_VERSION', '1.5.0' );
define( 'PTFB_CPT', 'ptfb_feedback' );

/* ------------------------------------------------------------------------
 * 1. Tipo di contenuto "Feedback" (nome = titolo, commento = testo)
 * --------------------------------------------------------------------- */

function ptfb_register_cpt() {
	register_post_type(
		PTFB_CPT,
		array(
			'labels'              => array(
				'name'               => 'Feedback',
				'singular_name'      => 'Feedback',
				'menu_name'          => 'Feedback',
				'all_items'          => 'Tutti i feedback',
				'add_new'            => 'Aggiungi feedback',
				'add_new_item'       => 'Aggiungi un nuovo feedback',
				'edit_item'          => 'Modifica feedback',
				'new_item'           => 'Nuovo feedback',
				'search_items'       => 'Cerca feedback',
				'not_found'          => 'Nessun feedback ancora. Clicca su "Aggiungi feedback" per inserire il primo.',
				'not_found_in_trash' => 'Nessun feedback nel cestino.',
			),
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_rest'        => false, // editor classico: più semplice per chi non è pratico.
			'has_archive'         => false,
			'rewrite'             => false,
			'menu_position'       => 25,
			'menu_icon'           => 'dashicons-format-quote',
			'supports'            => array( 'title', 'editor' ),
		)
	);
}
add_action( 'init', 'ptfb_register_cpt' );

/* Testi guida nella schermata di modifica. */
function ptfb_title_placeholder( $text, $post ) {
	if ( $post && PTFB_CPT === $post->post_type ) {
		return 'Nome di chi ha lasciato il feedback';
	}
	return $text;
}
add_filter( 'enter_title_here', 'ptfb_title_placeholder', 10, 2 );

/* Lunghezza consigliata del commento (caratteri): oltre, la scheda diventa meno leggibile. */
define( 'PTFB_TIP_CHARS', 300 );

function ptfb_edit_help( $post ) {
	if ( PTFB_CPT !== $post->post_type ) {
		return;
	}
	?>
	<p style="margin:12px 0 4px;color:#50575e;">Scrivi qui sopra il <strong>nome</strong> e nel riquadro sotto il <strong>commento</strong>.
		Premi <em>Pubblica</em> per mostrarlo sul sito. Per toglierlo dal sito usa <em>Sposta nel cestino</em>.</p>
	<div class="notice notice-info inline" style="margin:8px 0 12px;">
		<p>
			<strong>Consiglio:</strong> scrivi un commento breve, <strong>non più di <?php echo (int) PTFB_TIP_CHARS; ?> caratteri</strong>,
			così nella scheda del sito resta ben leggibile.
			<span id="ptfb-count" style="margin-left:6px;font-weight:600;white-space:nowrap;"></span>
		</p>
	</div>
	<script>
	(function () {
		var out = document.getElementById('ptfb-count');
		if (!out) { return; }
		var LIMIT = <?php echo (int) PTFB_TIP_CHARS; ?>;

		/* Testo del commento senza formattazione, sia nell'editor visuale sia in quello "Testo". */
		function plain() {
			var t = '';
			var ed = window.tinymce && window.tinymce.get ? window.tinymce.get('content') : null;
			if (ed && !ed.isHidden()) {
				t = ed.getContent({ format: 'text' });
			} else {
				var ta = document.getElementById('content');
				t = ta ? ta.value.replace(/<[^>]*>/g, ' ') : '';
			}
			return t.replace(/&nbsp;/g, ' ').replace(/\s+/g, ' ').trim();
		}

		function update() {
			var n = plain().length;
			if (n > LIMIT) {
				out.textContent = 'Ora sono ' + n + ' caratteri: meglio accorciare un po\'.';
				out.style.color = '#b32d2e';
			} else {
				out.textContent = 'Caratteri: ' + n + ' / ' + LIMIT;
				out.style.color = n > LIMIT * 0.85 ? '#996800' : '#50575e';
			}
		}

		update();
		setInterval(update, 500);
	})();
	</script>
	<?php
}
add_action( 'edit_form_after_title', 'ptfb_edit_help' );

/* Elenco in bacheca: Nome, Commento, Data. */
function ptfb_admin_columns( $cols ) {
	return array(
		'cb'          => isset( $cols['cb'] ) ? $cols['cb'] : '',
		'title'       => 'Nome',
		'ptfb_comment' => 'Commento',
		'date'        => 'Data',
	);
}
add_filter( 'manage_' . PTFB_CPT . '_posts_columns', 'ptfb_admin_columns' );

function ptfb_admin_column_content( $column, $post_id ) {
	if ( 'ptfb_comment' === $column ) {
		echo esc_html( wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ), 25 ) );
	}
}
add_action( 'manage_' . PTFB_CPT . '_posts_custom_column', 'ptfb_admin_column_content', 10, 2 );

/* ------------------------------------------------------------------------
 * 2. Impostazioni: titolo, velocità di scorrimento, colori e font
 * --------------------------------------------------------------------- */

define( 'PTFB_DEFAULT_TITLE', 'Cosa dicono di me' );

function ptfb_get_title() {
	return (string) get_option( 'ptfb_title', PTFB_DEFAULT_TITLE );
}

/* ---- velocità ---- */

/* Scelte possibili per il tempo tra un feedback e il successivo. */
function ptfb_speed_choices() {
	return array( '3', '5', '10', 'custom', 'off' );
}

/*
 * Scelta corrente: '3' | '5' | '10' | 'custom' | 'off'.
 * Se non è ancora stata salvata, si parte da 5 secondi.
 */
function ptfb_get_speed_choice() {
	$choice = get_option( 'ptfb_speed', null );
	if ( is_string( $choice ) && in_array( $choice, ptfb_speed_choices(), true ) ) {
		return $choice;
	}
	return '5';
}

/* Secondi personalizzati (da 2 a 120). */
function ptfb_get_custom_seconds() {
	$value = get_option( 'ptfb_custom', null );
	if ( null === $value || false === $value || '' === $value ) {
		$value = 8;
	}
	return max( 2, min( 120, (int) $value ) );
}

/* Secondi tra un feedback e il successivo; 0 = nessuno scorrimento automatico. */
function ptfb_get_interval() {
	$choice = ptfb_get_speed_choice();
	if ( 'off' === $choice ) {
		return 0;
	}
	if ( 'custom' === $choice ) {
		return ptfb_get_custom_seconds();
	}
	return (int) $choice;
}

function ptfb_sanitize_speed( $value ) {
	$value = is_string( $value ) ? $value : '';
	return in_array( $value, ptfb_speed_choices(), true ) ? $value : '5';
}

function ptfb_sanitize_custom( $value ) {
	if ( null === $value || '' === $value ) {
		return ptfb_get_custom_seconds(); // campo non inviato: si tiene il valore attuale.
	}
	return max( 2, min( 120, absint( $value ) ) );
}

/* ---- colori e font ---- */

/* ---- durata del giro pagina ---- */

/* Scelte possibili, in secondi (più "custom"). */
function ptfb_anim_choices() {
	return array( '0.6', '1.1', '1.8', '2.6', 'custom' );
}

function ptfb_get_anim_choice() {
	$choice = get_option( 'ptfb_anim', null );
	return ( is_string( $choice ) && in_array( $choice, ptfb_anim_choices(), true ) ) ? $choice : '1.1';
}

/* Secondi personalizzati (da 0,3 a 5). */
function ptfb_get_anim_custom() {
	$value = get_option( 'ptfb_anim_custom', null );
	if ( null === $value || false === $value || '' === $value ) {
		return 1.5;
	}
	return max( 0.3, min( 5.0, round( (float) $value, 1 ) ) );
}

function ptfb_get_anim_seconds() {
	$choice = ptfb_get_anim_choice();
	return 'custom' === $choice ? ptfb_get_anim_custom() : (float) $choice;
}

function ptfb_get_anim_ms() {
	return (int) round( ptfb_get_anim_seconds() * 1000 );
}

function ptfb_sanitize_anim( $value ) {
	$value = is_string( $value ) ? $value : '';
	return in_array( $value, ptfb_anim_choices(), true ) ? $value : '1.1';
}

function ptfb_sanitize_anim_custom( $value ) {
	if ( null === $value || '' === $value ) {
		return ptfb_get_anim_custom(); // campo non inviato: si tiene il valore attuale.
	}
	$value = (float) str_replace( ',', '.', (string) $value ); // accetta anche la virgola.
	return max( 0.3, min( 5.0, round( $value, 1 ) ) );
}

/* Allineamento del testo nella scheda. */
function ptfb_align_choices() {
	return array(
		'left'   => 'A sinistra',
		'center' => 'Al centro',
		'right'  => 'A destra',
	);
}

/* Parti della scheda a cui si può cambiare il font. */
function ptfb_font_roles() {
	return array(
		'title' => 'Titolo della sezione',
		'text'  => 'Commento',
		'name'  => 'Nome di chi ha scritto',
	);
}

/* Colori modificabili, con il valore predefinito (verde e bianco del sito). */
function ptfb_color_fields() {
	return array(
		'bg'     => array( 'Fascia dietro la scheda', '#a5cca7' ),
		'title'  => array( 'Titolo della sezione', '#ffffff' ),
		'card'   => array( 'Scheda', '#ffffff' ),
		'text'   => array( 'Commento', '#333333' ),
		'name'   => array( 'Nome di chi ha scritto', '#222222' ),
		'accent' => array( 'Virgolette, trattino e angolo piegato', '#a5cca7' ),
	);
}

/* Famiglie di caratteri proposte; oltre a "theme", "heading", "body" e "custom". */
function ptfb_font_stacks() {
	return array(
		'georgia'  => array( 'Georgia (con grazie)', 'Georgia, "Times New Roman", serif' ),
		'palatino' => array( 'Palatino (con grazie, da libro)', '"Palatino Linotype", Palatino, "Book Antiqua", serif' ),
		'times'    => array( 'Times New Roman', '"Times New Roman", Times, serif' ),
		'arial'    => array( 'Arial', 'Arial, "Helvetica Neue", Helvetica, sans-serif' ),
		'system'   => array( 'Font di sistema', 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif' ),
	);
}

function ptfb_font_choices() {
	return array_merge( array( 'theme', 'heading', 'body' ), array_keys( ptfb_font_stacks() ), array( 'custom' ) );
}

function ptfb_style_defaults() {
	$defaults = array();
	foreach ( ptfb_color_fields() as $key => $field ) {
		$defaults[ $key ] = $field[1];
	}
	foreach ( array_keys( ptfb_font_roles() ) as $role ) {
		$defaults[ 'font_' . $role ]             = 'theme';
		$defaults[ 'font_' . $role . '_custom' ] = '';
	}
	/* Come si vede ora sul sito: commento al centro, nome a sinistra. */
	$defaults['align_text'] = 'center';
	$defaults['align_name'] = 'left';
	return $defaults;
}

/* Stile attuale: impostazioni salvate sopra i valori predefiniti. */
function ptfb_get_style() {
	$defaults = ptfb_style_defaults();
	$saved    = get_option( 'ptfb_style', array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	return array_merge( $defaults, array_intersect_key( $saved, $defaults ) );
}

/* Nome di un font scritto a mano: solo caratteri innocui (niente ; { } ( ) < > \ / ecc.). */
function ptfb_clean_font_name( $value ) {
	$value = is_string( $value ) ? $value : '';
	$value = preg_replace( '/[^A-Za-z0-9 ,\'"_-]/', '', $value );
	return substr( trim( $value ), 0, 120 );
}

function ptfb_sanitize_style( $input ) {
	$defaults = ptfb_style_defaults();
	$input    = is_array( $input ) ? $input : array();
	$out      = $defaults;

	foreach ( array_keys( ptfb_color_fields() ) as $key ) {
		$color = isset( $input[ $key ] ) ? trim( (string) $input[ $key ] ) : '';
		if ( preg_match( '/^[0-9a-f]{3}([0-9a-f]{3})?$/i', $color ) ) {
			$color = '#' . $color; // senza il # iniziale.
		}
		$color        = sanitize_hex_color( $color );
		$out[ $key ] = $color ? strtolower( $color ) : $defaults[ $key ];
	}

	foreach ( array_keys( ptfb_font_roles() ) as $role ) {
		$choice                         = isset( $input[ 'font_' . $role ] ) ? (string) $input[ 'font_' . $role ] : 'theme';
		$out[ 'font_' . $role ]             = in_array( $choice, ptfb_font_choices(), true ) ? $choice : 'theme';
		$out[ 'font_' . $role . '_custom' ] = isset( $input[ 'font_' . $role . '_custom' ] ) ? ptfb_clean_font_name( $input[ 'font_' . $role . '_custom' ] ) : '';
	}

	foreach ( array( 'align_text', 'align_name' ) as $key ) {
		$choice      = isset( $input[ $key ] ) ? (string) $input[ $key ] : '';
		$out[ $key ] = array_key_exists( $choice, ptfb_align_choices() ) ? $choice : $defaults[ $key ];
	}

	return $out;
}

/* Valore CSS di font-family per un ruolo; stringa vuota = si lascia quello del tema. */
function ptfb_font_family( $style, $role ) {
	$choice = $style[ 'font_' . $role ];
	$stacks = ptfb_font_stacks();

	if ( 'heading' === $choice ) {
		return 'var(--ptfb-heading-font, inherit)';
	}
	if ( 'body' === $choice ) {
		return 'var(--ptfb-body-font, inherit)';
	}
	if ( isset( $stacks[ $choice ] ) ) {
		return $stacks[ $choice ][1];
	}
	if ( 'custom' === $choice && '' !== $style[ 'font_' . $role . '_custom' ] ) {
		return $style[ 'font_' . $role . '_custom' ];
	}
	return '';
}

/* CSS da aggiungere al foglio di stile del plugin: solo ciò che è diverso dal predefinito. */
function ptfb_inline_css() {
	$style    = ptfb_get_style();
	$defaults = ptfb_style_defaults();
	$vars     = array();

	foreach ( array_keys( ptfb_color_fields() ) as $key ) {
		if ( $style[ $key ] !== $defaults[ $key ] ) {
			$vars[] = '--ptfb-' . $key . ':' . $style[ $key ];
		}
	}
	$css = $vars ? 'section.ptfb{' . implode( ';', $vars ) . '}' : '';

	$targets = array(
		'title' => '.ptfb__title',
		'text'  => '.ptfb__quote',
		'name'  => '.ptfb__author',
	);
	foreach ( $targets as $role => $selector ) {
		$family = ptfb_font_family( $style, $role );
		if ( '' !== $family ) {
			$css .= 'section.ptfb ' . $selector . '{font-family:' . $family . '}';
		}
	}

	/* Allineamento: solo se diverso da quello predefinito (che è già nel foglio di stile). */
	if ( $style['align_text'] !== $defaults['align_text'] ) {
		$css .= 'section.ptfb .ptfb__quote,section.ptfb .ptfb__quote p{text-align:' . $style['align_text'] . '}';
	}
	if ( $style['align_name'] !== $defaults['align_name'] ) {
		$css .= 'section.ptfb .ptfb__author{text-align:' . $style['align_name'] . '}';
	}
	return $css;
}

function ptfb_register_settings() {
	register_setting(
		'ptfb',
		'ptfb_title',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => PTFB_DEFAULT_TITLE,
		)
	);
	register_setting(
		'ptfb',
		'ptfb_speed',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'ptfb_sanitize_speed',
		)
	);
	register_setting(
		'ptfb',
		'ptfb_custom',
		array(
			'type'              => 'integer',
			'sanitize_callback' => 'ptfb_sanitize_custom',
		)
	);
	register_setting(
		'ptfb',
		'ptfb_anim',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'ptfb_sanitize_anim',
		)
	);
	register_setting(
		'ptfb',
		'ptfb_anim_custom',
		array(
			'type'              => 'number',
			'sanitize_callback' => 'ptfb_sanitize_anim_custom',
		)
	);
	register_setting(
		'ptfb',
		'ptfb_style',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'ptfb_sanitize_style',
		)
	);
}
add_action( 'admin_init', 'ptfb_register_settings' );

function ptfb_add_settings_page() {
	$GLOBALS['ptfb_settings_hook'] = add_submenu_page(
		'edit.php?post_type=' . PTFB_CPT,
		'Impostazioni feedback',
		'Impostazioni',
		'manage_options',
		'ptfb-settings',
		'ptfb_settings_page'
	);
}
add_action( 'admin_menu', 'ptfb_add_settings_page' );

/* Selettore colori di WordPress, solo nella pagina delle impostazioni. */
function ptfb_admin_assets( $hook ) {
	if ( empty( $GLOBALS['ptfb_settings_hook'] ) || $hook !== $GLOBALS['ptfb_settings_hook'] ) {
		return;
	}
	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_script( 'wp-color-picker' );
	wp_add_inline_script( 'wp-color-picker', 'jQuery(function($){$(".ptfb-color").wpColorPicker();});' );
}
add_action( 'admin_enqueue_scripts', 'ptfb_admin_assets' );

function ptfb_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$choice = ptfb_get_speed_choice();
	$style  = ptfb_get_style();
	$labels = array(
		'3'      => 'Ogni 3 secondi',
		'5'      => 'Ogni 5 secondi',
		'10'     => 'Ogni 10 secondi',
		'custom' => 'Secondi personalizzati',
		'off'    => 'Nessuno scorrimento automatico (solo a mano)',
	);

	$anim_choice = ptfb_get_anim_choice();
	$anim_labels = array(
		'0.6'    => 'Veloce (0,6 secondi)',
		'1.1'    => 'Normale (1,1 secondi) - predefinita',
		'1.8'    => 'Lenta (1,8 secondi)',
		'2.6'    => 'Molto lenta (2,6 secondi)',
		'custom' => 'Durata personalizzata',
	);

	$font_options = array(
		'theme'   => 'Predefinito (font del tema)',
		'heading' => 'Font dei titoli del tema',
		'body'    => 'Font dei testi del tema',
	);
	foreach ( ptfb_font_stacks() as $key => $stack ) {
		$font_options[ $key ] = $stack[0];
	}
	$font_options['custom'] = 'Altro (scrivi il nome del font)';
	?>
	<div class="wrap">
		<h1>Impostazioni feedback</h1>
		<?php settings_errors(); ?>
		<form method="post" action="options.php">
			<?php settings_fields( 'ptfb' ); ?>

			<h2 class="title">Testo e scorrimento</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="ptfb_title">Titolo della sezione</label></th>
					<td>
						<input type="text" id="ptfb_title" name="ptfb_title" class="regular-text" value="<?php echo esc_attr( ptfb_get_title() ); ?>">
						<p class="description">
							Compare nella fascia verde sopra la scheda, in maiuscolo. Il testo predefinito è &ldquo;<?php echo esc_html( PTFB_DEFAULT_TITLE ); ?>&rdquo;; se lo lasci vuoto il titolo non viene mostrato.
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ptfb_speed">Scorrimento automatico</label></th>
					<td>
						<select id="ptfb_speed" name="ptfb_speed">
							<?php foreach ( $labels as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>"<?php selected( $choice, $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<span id="ptfb_custom_wrap"<?php echo 'custom' === $choice ? '' : ' hidden'; ?>>
							&nbsp;<input type="number" id="ptfb_custom" name="ptfb_custom" min="2" max="120" step="1" class="small-text" value="<?php echo esc_attr( ptfb_get_custom_seconds() ); ?>"> secondi
						</span>
						<p class="description">
							Tempo per cui ogni feedback resta visibile prima di girare pagina da solo (personalizzati: da 2 a 120 secondi).
							Per frasi brevi bastano 3&ndash;5 secondi; per commenti lunghi conviene 10 o più.
						</p>
						<p class="description">
							Chi visita il sito può tenere premuto sul testo e trascinare, oppure toccare l'angolo piegato in basso a destra, per girare pagina a mano:
							tenendo premuto lo scorrimento si ferma e riparte quando rilascia.
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ptfb_anim">Durata del giro pagina</label></th>
					<td>
						<select id="ptfb_anim" name="ptfb_anim">
							<?php foreach ( $anim_labels as $value => $label ) : ?>
								<option value="<?php echo esc_attr( (string) $value ); ?>"<?php selected( $anim_choice, (string) $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<span id="ptfb_anim_wrap"<?php echo 'custom' === $anim_choice ? '' : ' hidden'; ?>>
							&nbsp;<input type="number" id="ptfb_anim_custom" name="ptfb_anim_custom" min="0.3" max="5" step="0.1" class="small-text" value="<?php echo esc_attr( number_format( ptfb_get_anim_custom(), 1, '.', '' ) ); ?>"> secondi
						</span>
						<p class="description">
							Quanto dura l'animazione della pagina che si piega e passa da un commento al successivo (personalizzata: da 0,3 a 5 secondi).
							Il tempo di lettura scelto sopra riparte quando il giro pagina e' finito.
						</p>
					</td>
				</tr>
			</table>

			<h2 class="title">Colori</h2>
			<table class="form-table" role="presentation">
				<?php foreach ( ptfb_color_fields() as $key => $field ) : ?>
					<tr>
						<th scope="row"><label for="ptfb_style_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field[0] ); ?></label></th>
						<td>
							<input type="text" id="ptfb_style_<?php echo esc_attr( $key ); ?>" name="ptfb_style[<?php echo esc_attr( $key ); ?>]" class="ptfb-color" value="<?php echo esc_attr( $style[ $key ] ); ?>" data-default-color="<?php echo esc_attr( $field[1] ); ?>">
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
			<p class="description">Il pulsante &ldquo;Predefinito&rdquo; accanto a ogni colore riporta il verde e il bianco del sito.</p>

			<h2 class="title">Font</h2>
			<table class="form-table" role="presentation">
				<?php foreach ( ptfb_font_roles() as $role => $label ) : ?>
					<?php $current = $style[ 'font_' . $role ]; ?>
					<tr>
						<th scope="row"><label for="ptfb_style_font_<?php echo esc_attr( $role ); ?>"><?php echo esc_html( $label ); ?></label></th>
						<td>
							<select id="ptfb_style_font_<?php echo esc_attr( $role ); ?>" name="ptfb_style[font_<?php echo esc_attr( $role ); ?>]" class="ptfb-font-select" data-target="ptfb_font_wrap_<?php echo esc_attr( $role ); ?>">
								<?php foreach ( $font_options as $value => $option_label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>"<?php selected( $current, $value ); ?>><?php echo esc_html( $option_label ); ?></option>
								<?php endforeach; ?>
							</select>
							<span id="ptfb_font_wrap_<?php echo esc_attr( $role ); ?>"<?php echo 'custom' === $current ? '' : ' hidden'; ?>>
								&nbsp;<input type="text" name="ptfb_style[font_<?php echo esc_attr( $role ); ?>_custom]" class="regular-text" placeholder="per esempio: Poppins" value="<?php echo esc_attr( $style[ 'font_' . $role . '_custom' ] ); ?>">
							</span>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
			<p class="description">
				&ldquo;Predefinito&rdquo; usa i font del tema (titoli e nome nel font dei titoli, commento nel font dei testi).
				Con &ldquo;Altro&rdquo; scrivi il nome di un font già caricato dal sito: se non è caricato, chi visita il sito vedrà un font di riserva.
			</p>

			<h2 class="title">Allineamento</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="ptfb_style_align_text">Commento</label></th>
					<td>
						<select id="ptfb_style_align_text" name="ptfb_style[align_text]">
							<?php foreach ( ptfb_align_choices() as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>"<?php selected( $style['align_text'], $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ptfb_style_align_name">Nome di chi ha scritto</label></th>
					<td>
						<select id="ptfb_style_align_name" name="ptfb_style[align_name]">
							<?php foreach ( ptfb_align_choices() as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>"<?php selected( $style['align_name'], $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							Il nome si allinea dentro la stessa colonna del commento (a destra della virgoletta verde).
							Per un effetto ordinato di solito conviene dare lo stesso allineamento a commento e nome.
						</p>
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>
		<p class="description">I feedback compaiono automaticamente in fondo alla home. Per mostrarli anche in un'altra pagina inserisci lo shortcode <code>[page_turn_feedback]</code>.</p>
	</div>
	<script>
	(function () {
		var select = document.getElementById('ptfb_speed');
		var wrap = document.getElementById('ptfb_custom_wrap');
		if (select && wrap) {
			select.addEventListener('change', function () {
				wrap.hidden = select.value !== 'custom';
				if (select.value === 'custom') { document.getElementById('ptfb_custom').focus(); }
			});
		}
		var anim = document.getElementById('ptfb_anim');
		var animWrap = document.getElementById('ptfb_anim_wrap');
		if (anim && animWrap) {
			anim.addEventListener('change', function () {
				animWrap.hidden = anim.value !== 'custom';
				if (anim.value === 'custom') { document.getElementById('ptfb_anim_custom').focus(); }
			});
		}
		Array.prototype.forEach.call(document.querySelectorAll('.ptfb-font-select'), function (sel) {
			var box = document.getElementById(sel.getAttribute('data-target'));
			if (!box) { return; }
			sel.addEventListener('change', function () {
				box.hidden = sel.value !== 'custom';
				if (sel.value === 'custom') { box.querySelector('input').focus(); }
			});
		});
	})();
	</script>
	<?php
}

/* ------------------------------------------------------------------------
 * 3. Dati e visualizzazione
 * --------------------------------------------------------------------- */

function ptfb_get_items() {
	static $items = null;
	if ( null === $items ) {
		$items = get_posts(
			array(
				'post_type'      => PTFB_CPT,
				'post_status'    => 'publish',
				'posts_per_page' => (int) apply_filters( 'ptfb_max_items', 30 ),
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);
	}
	return $items;
}

/* Prima pagina della home (non le pagine 2, 3, ... dell'elenco articoli). */
function ptfb_is_target() {
	return ( is_home() || is_front_page() ) && ! is_paged();
}

function ptfb_register_assets() {
	wp_register_style( 'page-turn-feedback', plugins_url( 'assets/page-turn-feedback.css', __FILE__ ), array(), PTFB_VERSION );
	wp_register_script( 'page-turn-feedback', plugins_url( 'assets/page-turn-feedback.js', __FILE__ ), array(), PTFB_VERSION, true );

	/* Colori e font scelti nelle impostazioni (se diversi dal predefinito). */
	$inline = ptfb_inline_css();
	if ( '' !== $inline ) {
		wp_add_inline_style( 'page-turn-feedback', $inline );
	}

	$needed = false;
	if ( ptfb_get_items() ) {
		if ( ptfb_is_target() ) {
			$needed = true;
		} elseif ( is_singular() ) {
			$post   = get_post();
			$needed = $post && has_shortcode( $post->post_content, 'page_turn_feedback' );
		}
	}
	if ( $needed ) {
		wp_enqueue_style( 'page-turn-feedback' );
		wp_enqueue_script( 'page-turn-feedback' );
	}
}
add_action( 'wp_enqueue_scripts', 'ptfb_register_assets' );

/* Restituisce l'HTML della sezione (stringa vuota se non ci sono feedback). */
function ptfb_render() {
	$items = ptfb_get_items();
	if ( empty( $items ) ) {
		return '';
	}

	$GLOBALS['ptfb_printed'] = true;
	wp_enqueue_style( 'page-turn-feedback' );
	wp_enqueue_script( 'page-turn-feedback' );

	$uid      = wp_unique_id( 'ptfb-' );
	$total    = count( $items );
	$interval = ptfb_get_interval() * 1000;
	$title    = trim( ptfb_get_title() );
	$label    = '' !== $title ? $title : 'Feedback';

	/*
	 * Contenitore del tema (Fascinate): è lo stesso del footer, quindi la sezione
	 * occupa la larghezza delle 3 colonne e ha gli stessi margini laterali.
	 */
	$container = trim( (string) apply_filters( 'ptfb_container_class', 'fb-container' ) );

	ob_start();
	?>
	<section class="ptfb" id="<?php echo esc_attr( $uid ); ?>" aria-roledescription="carousel" aria-label="<?php echo esc_attr( $label ); ?>" data-interval="<?php echo esc_attr( $interval ); ?>" data-turn="<?php echo esc_attr( ptfb_get_anim_ms() ); ?>">
		<div<?php echo '' !== $container ? ' class="' . esc_attr( $container ) . '"' : ''; ?>>
			<div class="ptfb__inner">
				<?php if ( '' !== $title ) : ?>
					<h2 class="ptfb__title"><?php echo esc_html( $title ); ?></h2>
				<?php endif; ?>

				<div class="ptfb__stage">
					<?php foreach ( $items as $i => $item ) : ?>
						<figure class="ptfb__slide<?php echo 0 === $i ? ' is-current' : ''; ?>" role="group" aria-roledescription="slide" aria-label="<?php echo esc_attr( sprintf( '%d di %d', $i + 1, $total ) ); ?>"<?php echo 0 === $i ? '' : ' aria-hidden="true"'; ?>>
							<div class="ptfb__body">
								<span class="ptfb__mark" aria-hidden="true">&ldquo;</span>
								<blockquote class="ptfb__quote">
									<?php echo wpautop( wp_kses_post( $item->post_content ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</blockquote>
								<?php if ( '' !== trim( $item->post_title ) ) : ?>
									<p class="ptfb__author"><?php echo esc_html( $item->post_title ); ?></p>
								<?php endif; ?>
							</div>
						</figure>
					<?php endforeach; ?>

					<?php if ( $total > 1 ) : ?>
						<?php /* "Precedente": invisibile finché non riceve il focus da tastiera. */ ?>
						<div class="ptfb__sr">
							<button type="button" class="ptfb__sr-btn ptfb__sr-prev">Feedback precedente</button>
						</div>
						<?php /* Angolo piegato in basso a destra: tocca o clicca per girare pagina. */ ?>
						<button type="button" class="ptfb__corner" aria-label="Feedback successivo"></button>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</section>
	<?php
	return ob_get_clean();
}

/* Inserimento automatico: subito prima del footer, solo nella home. */
function ptfb_auto_insert() {
	if ( ! empty( $GLOBALS['ptfb_printed'] ) || ! ptfb_is_target() ) {
		return;
	}
	if ( ! apply_filters( 'ptfb_auto_insert', true ) ) {
		return;
	}
	echo ptfb_render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
add_action( 'get_footer', 'ptfb_auto_insert' );

/* Alternativa manuale: [page_turn_feedback] */
function ptfb_shortcode() {
	return ptfb_render();
}
add_shortcode( 'page_turn_feedback', 'ptfb_shortcode' );
