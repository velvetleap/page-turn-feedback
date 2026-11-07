<?php
/**
 * Plugin Name: Page Turn Feedback
 * Plugin URI:  https://github.com/velvetleap/page-turn-feedback
 * Description: Adds a "Feedback" menu to WordPress and shows the feedback on a card that turns like the page of a book, at the bottom of the home page above the footer.
 * Version:     1.7.1
 * Requires at least: 5.0.3
 * Author:      Velvet Leap
 * Author URI:  https://github.com/velvetleap
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: page-turn-feedback
 * Domain Path: /languages
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

define( 'PTFB_VERSION', '1.7.1' );
define( 'PTFB_CPT', 'ptfb_feedback' );

/* ------------------------------------------------------------------------
 * 0. Translations. The source strings are in English; an Italian translation
 *    (it_IT) is bundled in the languages/ folder.
 * --------------------------------------------------------------------- */

function ptfb_load_textdomain() {
	load_plugin_textdomain( 'page-turn-feedback', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'ptfb_load_textdomain', 0 );

/* ------------------------------------------------------------------------
 * 1. "Feedback" content type (name = title, comment = text)
 * --------------------------------------------------------------------- */

function ptfb_register_cpt() {
	register_post_type(
		PTFB_CPT,
		array(
			'labels'              => array(
				'name'               => __( 'Feedback', 'page-turn-feedback' ),
				'singular_name'      => __( 'Feedback', 'page-turn-feedback' ),
				'menu_name'          => __( 'Feedback', 'page-turn-feedback' ),
				'all_items'          => __( 'All feedback', 'page-turn-feedback' ),
				'add_new'            => __( 'Add feedback', 'page-turn-feedback' ),
				'add_new_item'       => __( 'Add new feedback', 'page-turn-feedback' ),
				'edit_item'          => __( 'Edit feedback', 'page-turn-feedback' ),
				'new_item'           => __( 'New feedback', 'page-turn-feedback' ),
				'search_items'       => __( 'Search feedback', 'page-turn-feedback' ),
				'not_found'          => __( 'No feedback yet. Click “Add feedback” to add the first one.', 'page-turn-feedback' ),
				'not_found_in_trash' => __( 'No feedback in the Trash.', 'page-turn-feedback' ),
			),
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_rest'        => false, // classic editor: simpler for non-technical editors.
			'has_archive'         => false,
			'rewrite'             => false,
			'menu_position'       => 25,
			'menu_icon'           => 'dashicons-format-quote',
			'supports'            => array( 'title', 'editor' ),
		)
	);
}
add_action( 'init', 'ptfb_register_cpt' );

/* Help texts on the edit screen. */
function ptfb_title_placeholder( $text, $post ) {
	if ( $post && PTFB_CPT === $post->post_type ) {
		return __( 'Name of the person who left the feedback', 'page-turn-feedback' );
	}
	return $text;
}
add_filter( 'enter_title_here', 'ptfb_title_placeholder', 10, 2 );

/* Recommended comment length (characters): beyond that, the card becomes harder to read. */
define( 'PTFB_TIP_CHARS', 300 );

function ptfb_edit_help( $post ) {
	if ( PTFB_CPT !== $post->post_type ) {
		return;
	}
	$allowed = array(
		'strong' => array(),
		'em'     => array(),
	);
	?>
	<p style="margin:12px 0 4px;color:#50575e;">
		<?php echo wp_kses( __( 'Write the <strong>name</strong> above and the <strong>comment</strong> in the box below. Click <em>Publish</em> to show it on the site. To remove it from the site use <em>Move to Trash</em>.', 'page-turn-feedback' ), $allowed ); ?>
	</p>
	<div class="notice notice-info inline" style="margin:8px 0 12px;">
		<p>
			<?php
			echo wp_kses(
				sprintf(
					/* translators: %d: recommended maximum number of characters for a comment. */
					__( '<strong>Tip:</strong> write a short comment, <strong>no more than %d characters</strong>, so it stays easy to read on the card.', 'page-turn-feedback' ),
					(int) PTFB_TIP_CHARS
				),
				$allowed
			);
			?>
			<span id="ptfb-count" data-limit="<?php echo esc_attr( (int) PTFB_TIP_CHARS ); ?>" style="margin-left:6px;font-weight:600;white-space:nowrap;"></span>
		</p>
	</div>
	<?php
}
add_action( 'edit_form_after_title', 'ptfb_edit_help' );

/* List in the admin: Name, Comment, Date. */
function ptfb_admin_columns( $cols ) {
	return array(
		'cb'           => isset( $cols['cb'] ) ? $cols['cb'] : '',
		'title'        => __( 'Name', 'page-turn-feedback' ),
		'ptfb_comment' => __( 'Comment', 'page-turn-feedback' ),
		'date'         => __( 'Date', 'page-turn-feedback' ),
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
 * 2. Settings: title, sliding speed, colors and fonts
 * --------------------------------------------------------------------- */

/* Default section title (translatable, so it is a function and not a constant). */
function ptfb_default_title() {
	return __( 'What people say about me', 'page-turn-feedback' );
}

function ptfb_get_title() {
	return (string) get_option( 'ptfb_title', ptfb_default_title() );
}

/* ---- speed ---- */

/* Possible choices for the time between one feedback and the next. */
function ptfb_speed_choices() {
	return array( '3', '5', '10', 'custom', 'off' );
}

/*
 * Current choice: '3' | '5' | '10' | 'custom' | 'off'.
 * If nothing has been saved yet, it starts at 5 seconds.
 */
function ptfb_get_speed_choice() {
	$choice = get_option( 'ptfb_speed', null );
	if ( is_string( $choice ) && in_array( $choice, ptfb_speed_choices(), true ) ) {
		return $choice;
	}
	return '5';
}

/* Custom seconds (2 to 120). */
function ptfb_get_custom_seconds() {
	$value = get_option( 'ptfb_custom', null );
	if ( null === $value || false === $value || '' === $value ) {
		$value = 8;
	}
	return max( 2, min( 120, (int) $value ) );
}

/* Seconds between one feedback and the next; 0 = no automatic sliding. */
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
		return ptfb_get_custom_seconds(); // field not submitted: keep the current value.
	}
	return max( 2, min( 120, absint( $value ) ) );
}

/* ---- page-turn duration ---- */

/* Possible choices, in seconds (plus "custom"). */
function ptfb_anim_choices() {
	return array( '0.6', '1.1', '1.8', '2.6', 'custom' );
}

function ptfb_get_anim_choice() {
	$choice = get_option( 'ptfb_anim', null );
	return ( is_string( $choice ) && in_array( $choice, ptfb_anim_choices(), true ) ) ? $choice : '1.1';
}

/* Custom seconds (0.3 to 5). */
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
		return ptfb_get_anim_custom(); // field not submitted: keep the current value.
	}
	$value = (float) str_replace( ',', '.', (string) $value ); // a comma is accepted too.
	return max( 0.3, min( 5.0, round( $value, 1 ) ) );
}

/* Text alignment on the card. */
function ptfb_align_choices() {
	return array(
		'left'   => __( 'Left', 'page-turn-feedback' ),
		'center' => __( 'Center', 'page-turn-feedback' ),
		'right'  => __( 'Right', 'page-turn-feedback' ),
	);
}

/* Parts of the card whose font can be changed. */
function ptfb_font_roles() {
	return array(
		'title' => __( 'Section title', 'page-turn-feedback' ),
		'text'  => __( 'Comment', 'page-turn-feedback' ),
		'name'  => __( 'Author name', 'page-turn-feedback' ),
	);
}

/* Editable colors, with the default value (green and white). */
function ptfb_color_fields() {
	return array(
		'bg'     => array( __( 'Band behind the card', 'page-turn-feedback' ), '#a5cca7' ),
		'title'  => array( __( 'Section title', 'page-turn-feedback' ), '#ffffff' ),
		'card'   => array( __( 'Card', 'page-turn-feedback' ), '#ffffff' ),
		'text'   => array( __( 'Comment', 'page-turn-feedback' ), '#333333' ),
		'name'   => array( __( 'Author name', 'page-turn-feedback' ), '#222222' ),
		'accent' => array( __( 'Quotation marks, dash and folded corner', 'page-turn-feedback' ), '#a5cca7' ),
	);
}

/* Suggested font families; in addition to "theme", "heading", "body" and "custom". */
function ptfb_font_stacks() {
	return array(
		'georgia'  => array( __( 'Georgia (serif)', 'page-turn-feedback' ), 'Georgia, "Times New Roman", serif' ),
		'palatino' => array( __( 'Palatino (serif, book style)', 'page-turn-feedback' ), '"Palatino Linotype", Palatino, "Book Antiqua", serif' ),
		'times'    => array( 'Times New Roman', '"Times New Roman", Times, serif' ),
		'arial'    => array( 'Arial', 'Arial, "Helvetica Neue", Helvetica, sans-serif' ),
		'system'   => array( __( 'System font', 'page-turn-feedback' ), 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif' ),
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
	/* As currently shown on the site: comment centered, name on the left. */
	$defaults['align_text'] = 'center';
	$defaults['align_name'] = 'left';
	return $defaults;
}

/* Current style: saved settings on top of the defaults. */
function ptfb_get_style() {
	$defaults = ptfb_style_defaults();
	$saved    = get_option( 'ptfb_style', array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	return array_merge( $defaults, array_intersect_key( $saved, $defaults ) );
}

/* Hand-typed font name: harmless characters only (no ; { } ( ) < > \ / etc.). */
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
			$color = '#' . $color; // without the leading #.
		}
		$color       = sanitize_hex_color( $color );
		$out[ $key ] = $color ? strtolower( $color ) : $defaults[ $key ];
	}

	foreach ( array_keys( ptfb_font_roles() ) as $role ) {
		$choice                             = isset( $input[ 'font_' . $role ] ) ? (string) $input[ 'font_' . $role ] : 'theme';
		$out[ 'font_' . $role ]             = in_array( $choice, ptfb_font_choices(), true ) ? $choice : 'theme';
		$out[ 'font_' . $role . '_custom' ] = isset( $input[ 'font_' . $role . '_custom' ] ) ? ptfb_clean_font_name( $input[ 'font_' . $role . '_custom' ] ) : '';
	}

	foreach ( array( 'align_text', 'align_name' ) as $key ) {
		$choice      = isset( $input[ $key ] ) ? (string) $input[ $key ] : '';
		$out[ $key ] = array_key_exists( $choice, ptfb_align_choices() ) ? $choice : $defaults[ $key ];
	}

	return $out;
}

/* CSS font-family value for a role; empty string = keep the theme font. */
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

/* CSS to add to the plugin stylesheet: only what differs from the default. */
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

	/* Alignment: only if different from the default (which is already in the stylesheet). */
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
			'default'           => ptfb_default_title(),
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
		__( 'Feedback settings', 'page-turn-feedback' ),
		__( 'Settings', 'page-turn-feedback' ),
		'manage_options',
		'ptfb-settings',
		'ptfb_settings_page'
	);
}
add_action( 'admin_menu', 'ptfb_add_settings_page' );

/*
 * Admin script (assets/page-turn-feedback-admin.js), loaded only where it is needed:
 * - on the edit screen of a feedback: live character counter for the comment;
 * - on the settings page: color picker and show/hide of the "custom value" fields.
 */
function ptfb_admin_assets( $hook ) {
	$is_settings = ! empty( $GLOBALS['ptfb_settings_hook'] ) && $hook === $GLOBALS['ptfb_settings_hook'];
	$is_editor   = false;
	if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		$screen    = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$is_editor = $screen && PTFB_CPT === $screen->post_type;
	}
	if ( ! $is_settings && ! $is_editor ) {
		return;
	}

	$deps = array();
	if ( $is_settings ) {
		wp_enqueue_style( 'wp-color-picker' );
		$deps[] = 'wp-color-picker';
	}
	wp_enqueue_script( 'page-turn-feedback-admin', plugins_url( 'assets/page-turn-feedback-admin.js', __FILE__ ), $deps, PTFB_VERSION, true );

	$data = array(
		'limit'   => (int) PTFB_TIP_CHARS,
		/* translators: %d: number of characters typed so far. */
		'tooLong' => __( 'That is %d characters: better to shorten it a little.', 'page-turn-feedback' ),
		/* translators: 1: number of characters typed so far, 2: recommended maximum. */
		'count'   => __( 'Characters: %1$d / %2$d', 'page-turn-feedback' ),
	);
	wp_add_inline_script( 'page-turn-feedback-admin', 'var ptfbAdmin = ' . wp_json_encode( $data ) . ';', 'before' );
}
add_action( 'admin_enqueue_scripts', 'ptfb_admin_assets' );

function ptfb_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$choice = ptfb_get_speed_choice();
	$style  = ptfb_get_style();
	$labels = array(
		'3'      => __( 'Every 3 seconds', 'page-turn-feedback' ),
		'5'      => __( 'Every 5 seconds', 'page-turn-feedback' ),
		'10'     => __( 'Every 10 seconds', 'page-turn-feedback' ),
		'custom' => __( 'Custom number of seconds', 'page-turn-feedback' ),
		'off'    => __( 'No automatic sliding (by hand only)', 'page-turn-feedback' ),
	);

	$anim_choice = ptfb_get_anim_choice();
	$anim_labels = array(
		'0.6'    => __( 'Fast (0.6 seconds)', 'page-turn-feedback' ),
		'1.1'    => __( 'Normal (1.1 seconds) - default', 'page-turn-feedback' ),
		'1.8'    => __( 'Slow (1.8 seconds)', 'page-turn-feedback' ),
		'2.6'    => __( 'Very slow (2.6 seconds)', 'page-turn-feedback' ),
		'custom' => __( 'Custom duration', 'page-turn-feedback' ),
	);

	$font_options = array(
		'theme'   => __( 'Default (theme font)', 'page-turn-feedback' ),
		'heading' => __( 'Theme heading font', 'page-turn-feedback' ),
		'body'    => __( 'Theme body font', 'page-turn-feedback' ),
	);
	foreach ( ptfb_font_stacks() as $key => $stack ) {
		$font_options[ $key ] = $stack[0];
	}
	$font_options['custom'] = __( 'Other (type the font name)', 'page-turn-feedback' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Feedback settings', 'page-turn-feedback' ); ?></h1>
		<?php settings_errors(); ?>
		<form method="post" action="options.php">
			<?php settings_fields( 'ptfb' ); ?>

			<h2 class="title"><?php esc_html_e( 'Text and sliding', 'page-turn-feedback' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="ptfb_title"><?php esc_html_e( 'Section title', 'page-turn-feedback' ); ?></label></th>
					<td>
						<input type="text" id="ptfb_title" name="ptfb_title" class="regular-text" value="<?php echo esc_attr( ptfb_get_title() ); ?>">
						<p class="description">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: default section title. */
									__( 'Shown in the band above the card, in capital letters. The default text is “%s”; if you leave it empty the title is not shown.', 'page-turn-feedback' ),
									ptfb_default_title()
								)
							);
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ptfb_speed"><?php esc_html_e( 'Automatic sliding', 'page-turn-feedback' ); ?></label></th>
					<td>
						<select id="ptfb_speed" name="ptfb_speed">
							<?php foreach ( $labels as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>"<?php selected( $choice, $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<span id="ptfb_custom_wrap"<?php echo 'custom' === $choice ? '' : ' hidden'; ?>>
							&nbsp;<input type="number" id="ptfb_custom" name="ptfb_custom" min="2" max="120" step="1" class="small-text" value="<?php echo esc_attr( ptfb_get_custom_seconds() ); ?>"> <?php esc_html_e( 'seconds', 'page-turn-feedback' ); ?>
						</span>
						<p class="description">
							<?php esc_html_e( 'How long each feedback stays visible before the page turns by itself (custom: 2 to 120 seconds). For short sentences 3–5 seconds are enough; for long comments 10 or more is better.', 'page-turn-feedback' ); ?>
						</p>
						<p class="description">
							<?php esc_html_e( 'Visitors can press and hold on the text and drag, or tap the folded corner at the bottom right, to turn the page by hand: while they hold, the sliding stops and it restarts when they let go.', 'page-turn-feedback' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ptfb_anim"><?php esc_html_e( 'Page-turn duration', 'page-turn-feedback' ); ?></label></th>
					<td>
						<select id="ptfb_anim" name="ptfb_anim">
							<?php foreach ( $anim_labels as $value => $label ) : ?>
								<option value="<?php echo esc_attr( (string) $value ); ?>"<?php selected( $anim_choice, (string) $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<span id="ptfb_anim_wrap"<?php echo 'custom' === $anim_choice ? '' : ' hidden'; ?>>
							&nbsp;<input type="number" id="ptfb_anim_custom" name="ptfb_anim_custom" min="0.3" max="5" step="0.1" class="small-text" value="<?php echo esc_attr( number_format( ptfb_get_anim_custom(), 1, '.', '' ) ); ?>"> <?php esc_html_e( 'seconds', 'page-turn-feedback' ); ?>
						</span>
						<p class="description">
							<?php esc_html_e( 'How long the animation lasts when the page folds and goes from one comment to the next (custom: 0.3 to 5 seconds). The reading time chosen above restarts when the page turn is finished.', 'page-turn-feedback' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<h2 class="title"><?php esc_html_e( 'Colors', 'page-turn-feedback' ); ?></h2>
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
			<p class="description"><?php esc_html_e( 'The “Default” button next to each color restores the original green and white.', 'page-turn-feedback' ); ?></p>

			<h2 class="title"><?php esc_html_e( 'Fonts', 'page-turn-feedback' ); ?></h2>
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
								&nbsp;<input type="text" name="ptfb_style[font_<?php echo esc_attr( $role ); ?>_custom]" class="regular-text" placeholder="<?php esc_attr_e( 'for example: Poppins', 'page-turn-feedback' ); ?>" value="<?php echo esc_attr( $style[ 'font_' . $role . '_custom' ] ); ?>">
							</span>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
			<p class="description">
				<?php esc_html_e( '“Default” uses the theme fonts (title and name in the heading font, comment in the body font). With “Other” type the name of a font already loaded by the site: if it is not loaded, visitors will see a fallback font.', 'page-turn-feedback' ); ?>
			</p>

			<h2 class="title"><?php esc_html_e( 'Alignment', 'page-turn-feedback' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="ptfb_style_align_text"><?php esc_html_e( 'Comment', 'page-turn-feedback' ); ?></label></th>
					<td>
						<select id="ptfb_style_align_text" name="ptfb_style[align_text]">
							<?php foreach ( ptfb_align_choices() as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>"<?php selected( $style['align_text'], $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ptfb_style_align_name"><?php esc_html_e( 'Author name', 'page-turn-feedback' ); ?></label></th>
					<td>
						<select id="ptfb_style_align_name" name="ptfb_style[align_name]">
							<?php foreach ( ptfb_align_choices() as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>"<?php selected( $style['align_name'], $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php esc_html_e( 'The name is aligned inside the same column as the comment (to the right of the quotation mark). For a tidy look it is usually best to give the comment and the name the same alignment.', 'page-turn-feedback' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>
		<p class="description">
			<?php
			echo wp_kses(
				sprintf(
					/* translators: %s: the shortcode. */
					__( 'The feedback appears automatically at the bottom of the home page. To show it on another page too, add the shortcode %s.', 'page-turn-feedback' ),
					'<code>[page_turn_feedback]</code>'
				),
				array( 'code' => array() )
			);
			?>
		</p>
	</div>
	<?php
}

/* ------------------------------------------------------------------------
 * 3. Data and display
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

/* First page of the home (not pages 2, 3, ... of the post list). */
function ptfb_is_target() {
	return ( is_home() || is_front_page() ) && ! is_paged();
}

function ptfb_register_assets() {
	wp_register_style( 'page-turn-feedback', plugins_url( 'assets/page-turn-feedback.css', __FILE__ ), array(), PTFB_VERSION );
	wp_register_script( 'page-turn-feedback', plugins_url( 'assets/page-turn-feedback.js', __FILE__ ), array(), PTFB_VERSION, true );

	/* Colors and fonts chosen in the settings (if different from the default). */
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

/*
 * Default class of the container that holds the card.
 *
 * On the Fascinate theme (and its child themes) it is the theme's own "fb-container", the same
 * as the footer one, so the section takes the width of the 3 columns and has the same side margins.
 * On any other theme that class does not exist, so the plugin uses its own centered and padded
 * container (ptfb__container). The ptfb_container_class filter can replace it.
 */
function ptfb_default_container_class() {
	$theme  = wp_get_theme();
	$names  = array( $theme->get_template(), $theme->get_stylesheet(), $theme->get( 'Name' ) );
	$parent = $theme->parent();
	if ( $parent ) {
		$names[] = $parent->get( 'Name' );
	}
	foreach ( $names as $name ) {
		if ( false !== stripos( (string) $name, 'fascinate' ) ) {
			return 'fb-container';
		}
	}
	return 'ptfb__container';
}

/* Prints the section (nothing if there are no feedback). Every value is escaped where it is printed. */
function ptfb_output() {
	$items = ptfb_get_items();
	if ( empty( $items ) ) {
		return;
	}

	$GLOBALS['ptfb_printed'] = true;
	wp_enqueue_style( 'page-turn-feedback' );
	wp_enqueue_script( 'page-turn-feedback' );

	$uid      = wp_unique_id( 'ptfb-' );
	$total    = count( $items );
	$interval = ptfb_get_interval() * 1000;
	$title    = trim( ptfb_get_title() );
	$label    = '' !== $title ? $title : __( 'Feedback', 'page-turn-feedback' );

	/* Container class: the theme's own one if it is known, otherwise the plugin's (see below). */
	$container = trim( (string) apply_filters( 'ptfb_container_class', ptfb_default_container_class() ) );

	?>
	<section class="ptfb" id="<?php echo esc_attr( $uid ); ?>" aria-roledescription="carousel" aria-label="<?php echo esc_attr( $label ); ?>" data-interval="<?php echo esc_attr( $interval ); ?>" data-turn="<?php echo esc_attr( ptfb_get_anim_ms() ); ?>">
		<div<?php echo '' !== $container ? ' class="' . esc_attr( $container ) . '"' : ''; ?>>
			<div class="ptfb__inner">
				<?php if ( '' !== $title ) : ?>
					<h2 class="ptfb__title"><?php echo esc_html( $title ); ?></h2>
				<?php endif; ?>

				<div class="ptfb__stage">
					<?php foreach ( $items as $i => $item ) : ?>
						<figure class="ptfb__slide<?php echo 0 === $i ? ' is-current' : ''; ?>" role="group" aria-roledescription="slide" aria-label="<?php echo esc_attr( sprintf( /* translators: 1: number of the current feedback, 2: total number of feedback. */ __( '%1$d of %2$d', 'page-turn-feedback' ), $i + 1, $total ) ); ?>"<?php echo 0 === $i ? '' : ' aria-hidden="true"'; ?>>
							<div class="ptfb__body">
								<span class="ptfb__mark" aria-hidden="true">&ldquo;</span>
								<blockquote class="ptfb__quote">
									<?php echo wp_kses_post( wpautop( $item->post_content ) ); ?>
								</blockquote>
								<?php if ( '' !== trim( $item->post_title ) ) : ?>
									<p class="ptfb__author"><?php echo esc_html( $item->post_title ); ?></p>
								<?php endif; ?>
							</div>
						</figure>
					<?php endforeach; ?>

					<?php if ( $total > 1 ) : ?>
						<?php /* "Previous": invisible until it gets keyboard focus. */ ?>
						<div class="ptfb__sr">
							<button type="button" class="ptfb__sr-btn ptfb__sr-prev"><?php esc_html_e( 'Previous feedback', 'page-turn-feedback' ); ?></button>
						</div>
						<?php /* Folded corner at the bottom right: tap or click to turn the page. */ ?>
						<button type="button" class="ptfb__corner" aria-label="<?php esc_attr_e( 'Next feedback', 'page-turn-feedback' ); ?>"></button>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</section>
	<?php
}

/* Returns the HTML of the section as a string (empty string if there are no feedback). */
function ptfb_render() {
	ob_start();
	ptfb_output();
	return ob_get_clean();
}

/* Automatic placement: right before the footer, on the home page only. */
function ptfb_auto_insert() {
	if ( ! empty( $GLOBALS['ptfb_printed'] ) || ! ptfb_is_target() ) {
		return;
	}
	if ( ! apply_filters( 'ptfb_auto_insert', true ) ) {
		return;
	}
	ptfb_output();
}
add_action( 'get_footer', 'ptfb_auto_insert' );

/*
 * Automatic placement on block themes: they never call get_footer(), so the section is printed
 * right before the footer template part instead (same conditions and same filter as above).
 */
function ptfb_auto_insert_block( $content, $block ) {
	if ( empty( $block['blockName'] ) || 'core/template-part' !== $block['blockName'] ) {
		return $content;
	}
	$attrs     = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
	$is_footer = ( isset( $attrs['area'] ) && 'footer' === $attrs['area'] ) || ( isset( $attrs['slug'] ) && 'footer' === $attrs['slug'] );
	if ( ! $is_footer || ! empty( $GLOBALS['ptfb_printed'] ) || ! ptfb_is_target() ) {
		return $content;
	}
	if ( ! apply_filters( 'ptfb_auto_insert', true ) ) {
		return $content;
	}
	return ptfb_render() . $content;
}
add_filter( 'render_block', 'ptfb_auto_insert_block', 10, 2 );

/* Manual alternative: [page_turn_feedback] */
function ptfb_shortcode() {
	return ptfb_render();
}
add_shortcode( 'page_turn_feedback', 'ptfb_shortcode' );
