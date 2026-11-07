<?php
/**
 * Copyright (C) 2026 Mattia Scudellaro. GPL-2.0-or-later.
 *
 * When the plugin is deleted only its settings are removed.
 * The feedback posts stay in the database.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'ptfb_title' );
delete_option( 'ptfb_speed' );
delete_option( 'ptfb_custom' );
delete_option( 'ptfb_anim' );
delete_option( 'ptfb_anim_custom' );
delete_option( 'ptfb_style' );
