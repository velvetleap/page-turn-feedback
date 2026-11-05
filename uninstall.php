<?php
/**
 * Copyright (C) 2026 Mattia Scudellaro. GPL-2.0-or-later.
 *
 * Alla cancellazione del plugin vengono rimosse solo le impostazioni.
 * I feedback inseriti restano nel database.
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
