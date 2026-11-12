<?php
/**
 * Ready-made styles (presets) for the settings page.
 *
 * Copyright (C) 2026 Mattia Scudellaro. GPL-2.0-or-later.
 *
 * A preset is only a starting point: choosing one fills in the colors, fonts and alignment fields of the
 * settings form (nothing is saved until "Save Changes" is pressed), and every field can still be changed.
 * Nothing about presets is stored in the database.
 *
 * @package velvet-leap-page-turn-feedback
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * The five ready-made styles. Every text color has a contrast of at least 9:1 on the card and the title
 * at least 5:1 on the band behind it (checked in tests/settings_test.php).
 * Fonts use the built-in choices of ptfb_font_stacks(), so no font has to be loaded.
 */
function ptfb_style_presets() {
	return array(
		'paper'   => array(
			'label' => __( 'Paper', 'velvet-leap-page-turn-feedback' ),
			'style' => array(
				'bg'         => '#efe4cf',
				'title'      => '#4a3624',
				'card'       => '#fffdf7',
				'text'       => '#3b3027',
				'name'       => '#2b2119',
				'accent'     => '#a8683a',
				'font_title' => 'georgia',
				'font_text'  => 'georgia',
				'font_name'  => 'georgia',
				'align_text' => 'left',
				'align_name' => 'left',
			),
		),
		'night'   => array(
			'label' => __( 'Night', 'velvet-leap-page-turn-feedback' ),
			'style' => array(
				'bg'         => '#161c2b',
				'title'      => '#f1f4fa',
				'card'       => '#262f45',
				'text'       => '#e8ecf6',
				'name'       => '#ffffff',
				'accent'     => '#f2b84b',
				'font_title' => 'georgia',
				'font_text'  => 'system',
				'font_name'  => 'system',
				'align_text' => 'center',
				'align_name' => 'center',
			),
		),
		'ocean'   => array(
			'label' => __( 'Ocean', 'velvet-leap-page-turn-feedback' ),
			'style' => array(
				'bg'         => '#1b6a86',
				'title'      => '#ffffff',
				'card'       => '#ffffff',
				'text'       => '#22333b',
				'name'       => '#0e4a61',
				'accent'     => '#1b6a86',
				'font_title' => 'system',
				'font_text'  => 'system',
				'font_name'  => 'system',
				'align_text' => 'left',
				'align_name' => 'left',
			),
		),
		'sunset'  => array(
			'label' => __( 'Sunset', 'velvet-leap-page-turn-feedback' ),
			'style' => array(
				'bg'         => '#b8432b',
				'title'      => '#ffffff',
				'card'       => '#fff6ee',
				'text'       => '#4a2a21',
				'name'       => '#35190f',
				'accent'     => '#b8432b',
				'font_title' => 'palatino',
				'font_text'  => 'palatino',
				'font_name'  => 'palatino',
				'align_text' => 'center',
				'align_name' => 'center',
			),
		),
		'minimal' => array(
			'label' => __( 'Minimal', 'velvet-leap-page-turn-feedback' ),
			'style' => array(
				'bg'         => '#ececec',
				'title'      => '#111111',
				'card'       => '#ffffff',
				'text'       => '#222222',
				'name'       => '#000000',
				'accent'     => '#c62828',
				'font_title' => 'arial',
				'font_text'  => 'arial',
				'font_name'  => 'arial',
				'align_text' => 'left',
				'align_name' => 'left',
			),
		),
	);
}

/*
 * A preset as a complete style (every field of the settings form), ready to be applied by the
 * settings page script. The three hand-typed font fields are always empty.
 */
function ptfb_preset_as_style( $preset ) {
	$style = array_merge( ptfb_style_defaults(), $preset['style'] );
	foreach ( array_keys( ptfb_font_roles() ) as $role ) {
		$style[ 'font_' . $role . '_custom' ] = '';
	}
	return ptfb_sanitize_style( $style );
}

/* Invented feedback for the preview (while there is none yet) and for the example CSV file. */
function ptfb_sample_feedback() {
	return array(
		array(
			'name'    => __( 'Mary Smith', 'velvet-leap-page-turn-feedback' ),
			'comment' => __( 'A wonderful experience from start to finish. I would recommend it to anyone.', 'velvet-leap-page-turn-feedback' ),
		),
		array(
			'name'    => __( 'John Brown', 'velvet-leap-page-turn-feedback' ),
			'comment' => __( 'Careful, kind and always on time. A pleasure to work with.', 'velvet-leap-page-turn-feedback' ),
		),
	);
}
