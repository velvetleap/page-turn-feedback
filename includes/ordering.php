<?php
/**
 * Manual order of the feedback ("Order" page under the Feedback menu).
 *
 * Copyright (C) 2026 Mattia Scudellaro. GPL-2.0-or-later.
 *
 * The position of every feedback is stored in the standard WordPress field "menu_order" of the post,
 * so no new option or table is needed. The site shows the feedback by menu_order (lowest first) and,
 * for equal positions, newest first. Feedback that has never been placed by hand has position 0, so a
 * site that never uses this page keeps showing the newest feedback first, exactly as before.
 *
 * @package page-turn-feedback
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Capability needed to change the order, and to export and import (it concerns everyone's feedback). */
function ptfb_manage_capability() {
	return 'edit_others_posts';
}

/* Most feedback listed on the Order page. */
function ptfb_order_list_max() {
	return 300;
}

/* The order used by the site and by the Order page: by hand first, then newest first. */
function ptfb_orderby() {
	return array(
		'menu_order' => 'ASC',
		'date'       => 'DESC',
	);
}

function ptfb_add_order_page() {
	$GLOBALS['ptfb_order_hook'] = add_submenu_page(
		'edit.php?post_type=' . PTFB_CPT,
		__( 'Order of the feedback', 'page-turn-feedback' ),
		__( 'Order', 'page-turn-feedback' ),
		ptfb_manage_capability(),
		'ptfb-order',
		'ptfb_order_page'
	);
}
add_action( 'admin_menu', 'ptfb_add_order_page', 11 );

/* The IDs sent by the form: whole numbers, no zero, no repetitions, in the order received. */
function ptfb_clean_order_ids( $raw ) {
	if ( ! is_array( $raw ) ) {
		return array();
	}
	$ids = array();
	foreach ( $raw as $value ) {
		if ( ! is_scalar( $value ) ) {
			continue;
		}
		$id = absint( $value );
		if ( $id > 0 && ! in_array( $id, $ids, true ) ) {
			$ids[] = $id;
		}
	}
	return $ids;
}

/* Published feedback in the order of the site. */
function ptfb_get_ordered_feedback() {
	return get_posts(
		array(
			'post_type'      => PTFB_CPT,
			'post_status'    => 'publish',
			'posts_per_page' => ptfb_order_list_max(),
			'orderby'        => ptfb_orderby(),
			'no_found_rows'  => true,
		)
	);
}

/*
 * Saves the positions 1, 2, 3, ... in the order given. Only feedback that exists and changes position
 * is written. Returns how many were changed.
 */
function ptfb_apply_order( $ids ) {
	$changed  = 0;
	$position = 0;
	foreach ( $ids as $id ) {
		$post = get_post( $id );
		if ( ! $post || PTFB_CPT !== $post->post_type ) {
			continue;
		}
		++$position;
		if ( (int) $post->menu_order !== $position ) {
			wp_update_post(
				array(
					'ID'         => (int) $id,
					'menu_order' => $position,
				)
			);
			++$changed;
		}
	}
	return $changed;
}

/* Back to the automatic order: every position to 0 (newest first). Returns how many were changed. */
function ptfb_reset_order() {
	$changed = 0;
	$posts   = get_posts(
		array(
			'post_type'      => PTFB_CPT,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
		)
	);
	foreach ( $posts as $post ) {
		if ( 0 !== (int) $post->menu_order ) {
			wp_update_post(
				array(
					'ID'         => (int) $post->ID,
					'menu_order' => 0,
				)
			);
			++$changed;
		}
	}
	return $changed;
}

function ptfb_order_page_url( $args = array() ) {
	return add_query_arg( $args, admin_url( 'edit.php?post_type=' . PTFB_CPT . '&page=ptfb-order' ) );
}

function ptfb_handle_save_order() {
	if ( ! current_user_can( ptfb_manage_capability() ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'page-turn-feedback' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'ptfb_save_order' );

	if ( isset( $_POST['ptfb_reset'] ) ) {
		ptfb_reset_order();
		$notice = 'reset';
	} else {
		$raw = isset( $_POST['ptfb_order'] ) ? map_deep( wp_unslash( $_POST['ptfb_order'] ), 'sanitize_text_field' ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized by map_deep(); the IDs are cleaned again in ptfb_clean_order_ids().
		ptfb_apply_order( ptfb_clean_order_ids( $raw ) );
		$notice = 'saved';
	}
	wp_safe_redirect( ptfb_order_page_url( array( 'ptfb_notice' => $notice ) ) );
	exit;
}
add_action( 'admin_post_ptfb_save_order', 'ptfb_handle_save_order' );

function ptfb_order_page() {
	if ( ! current_user_can( ptfb_manage_capability() ) ) {
		return;
	}
	$items  = ptfb_get_ordered_feedback();
	$max    = (int) apply_filters( 'ptfb_max_items', 30 );
	$notice = isset( $_GET['ptfb_notice'] ) ? sanitize_key( wp_unslash( $_GET['ptfb_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only chooses which message to show.
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Order of the feedback', 'page-turn-feedback' ); ?></h1>

		<?php if ( 'saved' === $notice ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Order saved. The site now shows the feedback in this order.', 'page-turn-feedback' ); ?></p></div>
		<?php elseif ( 'reset' === $notice ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Automatic order restored: the newest feedback comes first.', 'page-turn-feedback' ); ?></p></div>
		<?php endif; ?>

		<?php if ( empty( $items ) ) : ?>
			<p>
				<?php esc_html_e( 'There is no published feedback yet.', 'page-turn-feedback' ); ?>
				<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . PTFB_CPT ) ); ?>"><?php esc_html_e( 'Add feedback', 'page-turn-feedback' ); ?></a>
			</p>
		<?php else : ?>
			<p class="description">
				<?php esc_html_e( 'The site shows the feedback from top to bottom. Drag a row (or use the arrows) to change its place, then click “Save order”. Until you save, nothing changes on the site.', 'page-turn-feedback' ); ?>
			</p>
			<p class="description">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: number of feedback shown on the site. */
						__( 'The site shows the first %d. A new feedback goes to the top until you save the order again.', 'page-turn-feedback' ),
						$max
					)
				);
				?>
			</p>

			<form method="post" id="ptfb-order-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ptfb_save_order">
				<?php wp_nonce_field( 'ptfb_save_order' ); ?>

				<ol id="ptfb-order-list" class="ptfb-order" role="list" data-max="<?php echo esc_attr( $max ); ?>">
					<?php foreach ( $items as $i => $item ) : ?>
						<?php
						$name = trim( $item->post_title );
						if ( '' === $name ) {
							$name = __( '(no name)', 'page-turn-feedback' );
						}
						$text = wp_trim_words( wp_strip_all_tags( $item->post_content ), 18 );
						?>
						<li class="ptfb-order__row<?php echo $i >= $max ? ' ptfb-order__row--beyond' : ''; ?>">
							<input type="hidden" name="ptfb_order[]" value="<?php echo esc_attr( $item->ID ); ?>">
							<span class="ptfb-order__handle dashicons dashicons-menu" aria-hidden="true"></span>
							<span class="ptfb-order__main">
								<strong class="ptfb-order__name"><?php echo esc_html( $name ); ?></strong>
								<span class="ptfb-order__text"><?php echo esc_html( $text ); ?></span>
								<span class="ptfb-order__tag"><?php esc_html_e( 'Not shown on the site', 'page-turn-feedback' ); ?></span>
							</span>
							<span class="ptfb-order__buttons">
								<button type="button" class="button ptfb-order__btn ptfb-order__up" data-move="up" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: name of the person who left the feedback. */ __( 'Move “%s” up', 'page-turn-feedback' ), $name ) ); ?>">&uarr;</button>
								<button type="button" class="button ptfb-order__btn ptfb-order__down" data-move="down" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: name of the person who left the feedback. */ __( 'Move “%s” down', 'page-turn-feedback' ), $name ) ); ?>">&darr;</button>
							</span>
						</li>
					<?php endforeach; ?>
				</ol>

				<p class="submit">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save order', 'page-turn-feedback' ); ?></button>
					<button type="submit" name="ptfb_reset" value="1" class="button"><?php esc_html_e( 'Back to automatic order (newest first)', 'page-turn-feedback' ); ?></button>
				</p>
			</form>
		<?php endif; ?>
	</div>
	<?php
}
