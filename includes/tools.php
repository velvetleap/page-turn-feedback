<?php
/**
 * Import and export of the feedback in a CSV file ("Import and export" page under the Feedback menu).
 *
 * Copyright (C) 2026 Mattia Scudellaro. GPL-2.0-or-later.
 *
 * - Export: one row per feedback with the columns name, comment, status, date, order.
 * - Import: reads the same columns, adds what is not there yet and never changes or deletes anything.
 * - XML for moving feedback between WordPress sites is already provided by WordPress itself
 *   (Tools > Export / Import): the page only points to it.
 *
 * The file is read and written by the functions below (no library, no remote request, no AJAX).
 *
 * @package page-turn-feedback
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Largest file accepted by the import (bytes). */
define( 'PTFB_CSV_MAX_BYTES', 1048576 );

function ptfb_add_tools_page() {
	$GLOBALS['ptfb_tools_hook'] = add_submenu_page(
		'edit.php?post_type=' . PTFB_CPT,
		__( 'Import and export', 'page-turn-feedback' ),
		__( 'Import and export', 'page-turn-feedback' ),
		ptfb_manage_capability(),
		'ptfb-tools',
		'ptfb_tools_page'
	);
}
add_action( 'admin_menu', 'ptfb_add_tools_page', 11 );

/* ------------------------------------------------------------------------
 * CSV: reading and writing (pure functions, easy to test)
 * --------------------------------------------------------------------- */

/* The columns of the file, in order. The names are the same in every language. */
function ptfb_csv_columns() {
	return array( 'name', 'comment', 'status', 'date', 'order' );
}

/* Characters that make a spreadsheet read a cell as a formula. */
function ptfb_csv_formula_chars() {
	return "=+-@\t\r";
}

/* A cell that starts like a formula gets a leading apostrophe, so Excel and Sheets show it as plain text. */
function ptfb_csv_cell_out( $value ) {
	$value = (string) $value;
	if ( '' !== $value && false !== strpos( ptfb_csv_formula_chars(), $value[0] ) ) {
		return "'" . $value;
	}
	return $value;
}

/* The opposite, when reading a file written by ptfb_csv_cell_out(). */
function ptfb_csv_cell_in( $value ) {
	$value = (string) $value;
	if ( strlen( $value ) > 1 && "'" === $value[0] && false !== strpos( ptfb_csv_formula_chars(), $value[1] ) ) {
		return substr( $value, 1 );
	}
	return $value;
}

/* One line of the file: every cell in quotes, quotes doubled, comma between cells. */
function ptfb_csv_line( $cells ) {
	$out = array();
	foreach ( $cells as $cell ) {
		$out[] = '"' . str_replace( '"', '""', (string) $cell ) . '"';
	}
	return implode( ',', $out ) . "\r\n";
}

/* The whole file (with the byte order mark that makes Excel open it as UTF-8). */
function ptfb_csv_build( $rows ) {
	$csv = "\xEF\xBB\xBF" . ptfb_csv_line( ptfb_csv_columns() );
	foreach ( $rows as $row ) {
		$cells = array();
		foreach ( ptfb_csv_columns() as $column ) {
			$cells[] = ptfb_csv_cell_out( isset( $row[ $column ] ) ? $row[ $column ] : '' );
		}
		$csv .= ptfb_csv_line( $cells );
	}
	return $csv;
}

/* Text of the file as UTF-8: removes the byte order mark; files saved by Excel in Windows (Windows-1252) are converted. */
function ptfb_csv_to_utf8( $text ) {
	$text = (string) $text;
	if ( 0 === strpos( $text, "\xEF\xBB\xBF" ) ) {
		$text = substr( $text, 3 );
	}
	if ( 1 === preg_match( '//u', $text ) ) {
		return $text;
	}
	if ( function_exists( 'mb_convert_encoding' ) ) {
		return mb_convert_encoding( $text, 'UTF-8', 'Windows-1252' );
	}
	if ( function_exists( 'iconv' ) ) {
		$converted = iconv( 'Windows-1252', 'UTF-8//IGNORE', $text );
		if ( false !== $converted ) {
			return $converted;
		}
	}
	return wp_check_invalid_utf8( $text, true );
}

/* Separator used by the file: comma, semicolon (Excel in Italy and many other countries) or tab, read from the first line. */
function ptfb_csv_detect_delimiter( $text ) {
	$counts = array(
		','  => 0,
		';'  => 0,
		"\t" => 0,
	);
	$quoted = false;
	$length = strlen( $text );
	for ( $i = 0; $i < $length; $i++ ) {
		$char = $text[ $i ];
		if ( '"' === $char ) {
			$quoted = ! $quoted;
		} elseif ( ! $quoted ) {
			if ( "\n" === $char || "\r" === $char ) {
				break;
			}
			if ( isset( $counts[ $char ] ) ) {
				++$counts[ $char ];
			}
		}
	}
	$best = ',';
	foreach ( $counts as $char => $count ) {
		if ( $count > $counts[ $best ] ) {
			$best = $char;
		}
	}
	return $best;
}

/*
 * Splits the text into rows of cells. Quoted cells may contain separators, quotes ("") and line breaks;
 * the line break can be \n, \r\n or \r; empty lines are skipped.
 */
function ptfb_csv_parse( $text, $delimiter = ',' ) {
	$rows   = array();
	$row    = array();
	$field  = '';
	$quoted = false;
	$length = strlen( $text );

	for ( $i = 0; $i < $length; $i++ ) {
		$char = $text[ $i ];
		if ( $quoted ) {
			if ( '"' !== $char ) {
				$field .= $char;
			} elseif ( $i + 1 < $length && '"' === $text[ $i + 1 ] ) {
				$field .= '"';
				++$i;
			} else {
				$quoted = false;
			}
			continue;
		}
		if ( '"' === $char && '' === $field ) {
			$quoted = true;
		} elseif ( $delimiter === $char ) {
			$row[] = $field;
			$field = '';
		} elseif ( "\n" === $char || "\r" === $char ) {
			if ( "\r" === $char && $i + 1 < $length && "\n" === $text[ $i + 1 ] ) {
				++$i;
			}
			$row[] = $field;
			$field = '';
			if ( ! ( 1 === count( $row ) && '' === trim( $row[0] ) ) ) {
				$rows[] = $row;
			}
			$row = array();
		} else {
			$field .= $char;
		}
	}
	if ( '' !== $field || $row ) {
		$row[]  = $field;
		$rows[] = $row;
	}
	return $rows;
}

/* Position of every known column in the first row of the file (names in English or Italian, any case). */
function ptfb_csv_header_map( $header ) {
	$aliases = array(
		'name'    => array( 'name', 'nome' ),
		'comment' => array( 'comment', 'commento', 'text', 'testo' ),
		'status'  => array( 'status', 'stato' ),
		'date'    => array( 'date', 'data' ),
		'order'   => array( 'order', 'ordine' ),
	);
	$map     = array();
	foreach ( $header as $index => $cell ) {
		$cell = strtolower( trim( (string) $cell ) );
		foreach ( $aliases as $key => $names ) {
			if ( ! isset( $map[ $key ] ) && in_array( $cell, $names, true ) ) {
				$map[ $key ] = $index;
			}
		}
	}
	return $map;
}

/* "publish" when empty or clearly published, "pending" for pending review, anything else is kept as a draft. */
function ptfb_csv_status( $value ) {
	$value = strtolower( trim( (string) $value ) );
	if ( '' === $value || in_array( $value, array( 'publish', 'published', 'pubblicato' ), true ) ) {
		return 'publish';
	}
	if ( in_array( $value, array( 'pending', 'in attesa' ), true ) ) {
		return 'pending';
	}
	return 'draft';
}

/* "2026-03-15" or "2026-03-15 18:30[:00]" as "Y-m-d H:i:s"; anything else (or a date that does not exist) gives an empty string. */
function ptfb_csv_date( $value ) {
	if ( 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?$/', trim( (string) $value ), $m ) ) {
		return '';
	}
	$hour   = isset( $m[4] ) ? (int) $m[4] : 0;
	$minute = isset( $m[5] ) ? (int) $m[5] : 0;
	$second = isset( $m[6] ) ? (int) $m[6] : 0;
	if ( ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) || $hour > 23 || $minute > 59 || $second > 59 ) {
		return '';
	}
	return sprintf( '%04d-%02d-%02d %02d:%02d:%02d', $m[1], $m[2], $m[3], $hour, $minute, $second );
}

/* Position: a whole number from 0 to 99999, otherwise 0 (= automatic order). */
function ptfb_csv_order( $value ) {
	$value = trim( (string) $value );
	return ( 1 === preg_match( '/^[0-9]+$/', $value ) ) ? min( 99999, (int) $value ) : 0;
}

/* Same name and same comment = same feedback (used to skip what is already there). */
function ptfb_csv_item_key( $name, $comment ) {
	$name = trim( html_entity_decode( (string) $name, ENT_QUOTES, 'UTF-8' ) ); // WordPress may store "&" as "&amp;" in a name.
	return md5( $name . "\n" . trim( str_replace( array( "\r\n", "\r" ), "\n", (string) $comment ) ) );
}

/* A row of the file as a feedback; null if there is no comment. Name and comment are cleaned like anything typed in the editor. */
function ptfb_csv_row_to_item( $row, $map ) {
	$cell = array();
	foreach ( $map as $key => $index ) {
		$cell[ $key ] = isset( $row[ $index ] ) ? ptfb_csv_cell_in( $row[ $index ] ) : '';
	}
	$comment = isset( $cell['comment'] ) ? trim( wp_kses_post( str_replace( array( "\r\n", "\r" ), "\n", $cell['comment'] ) ) ) : '';
	if ( '' === trim( wp_strip_all_tags( $comment ) ) ) {
		return null;
	}
	return array(
		'name'    => isset( $cell['name'] ) ? sanitize_text_field( $cell['name'] ) : '',
		'comment' => $comment,
		'status'  => ptfb_csv_status( isset( $cell['status'] ) ? $cell['status'] : '' ),
		'date'    => ptfb_csv_date( isset( $cell['date'] ) ? $cell['date'] : '' ),
		'order'   => ptfb_csv_order( isset( $cell['order'] ) ? $cell['order'] : '' ),
	);
}

/*
 * Reads the text of a file. Returns array( 'error' => '' | 'empty' | 'header' | 'toomany', 'items' => [...], 'invalid' => n ),
 * where "invalid" counts the rows without a comment.
 */
function ptfb_csv_read_items( $text ) {
	$result = array(
		'error'   => '',
		'items'   => array(),
		'invalid' => 0,
	);
	$text   = ptfb_csv_to_utf8( $text );
	$rows   = ptfb_csv_parse( $text, ptfb_csv_detect_delimiter( $text ) );
	if ( empty( $rows ) ) {
		$result['error'] = 'empty';
		return $result;
	}
	$map = ptfb_csv_header_map( array_shift( $rows ) );
	if ( ! isset( $map['comment'] ) ) {
		$result['error'] = 'header';
		return $result;
	}
	if ( count( $rows ) > (int) apply_filters( 'ptfb_import_max_rows', 500 ) ) {
		$result['error'] = 'toomany';
		return $result;
	}
	foreach ( $rows as $row ) {
		$item = ptfb_csv_row_to_item( $row, $map );
		if ( null === $item ) {
			++$result['invalid'];
		} else {
			$result['items'][] = $item;
		}
	}
	return $result;
}

/* ------------------------------------------------------------------------
 * Export and import (WordPress side)
 * --------------------------------------------------------------------- */

/* Rows to export: all feedback, in the order of the site. */
function ptfb_export_rows() {
	$rows  = array();
	$posts = get_posts(
		array(
			'post_type'      => PTFB_CPT,
			'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
			'posts_per_page' => -1,
			'orderby'        => ptfb_orderby(),
			'no_found_rows'  => true,
		)
	);
	foreach ( $posts as $post ) {
		$rows[] = array(
			'name'    => html_entity_decode( $post->post_title, ENT_QUOTES, 'UTF-8' ), // as the editor sees it ("&", not "&amp;").
			'comment' => $post->post_content,
			'status'  => $post->post_status,
			'date'    => $post->post_date,
			'order'   => (int) $post->menu_order,
		);
	}
	return $rows;
}

/* Example file: the header and one row, to show how the file must be written. */
function ptfb_sample_rows() {
	return array(
		array(
			'name'    => __( 'Mary Smith', 'page-turn-feedback' ),
			'comment' => __( 'A wonderful experience from start to finish. I recommend it to everyone.', 'page-turn-feedback' ),
			'status'  => 'publish',
			'date'    => '',
			'order'   => '',
		),
	);
}

function ptfb_handle_export_csv() {
	if ( ! current_user_can( ptfb_manage_capability() ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'page-turn-feedback' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'ptfb_export_csv' );

	$sample = isset( $_POST['ptfb_sample'] );
	$csv    = ptfb_csv_build( $sample ? ptfb_sample_rows() : ptfb_export_rows() );
	$file   = ( $sample ? 'feedback-example-' : 'feedback-' ) . gmdate( 'Y-m-d' ) . '.csv';

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $file . '"' );
	header( 'X-Content-Type-Options: nosniff' );
	echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a CSV file for download, not HTML.
	exit;
}
add_action( 'admin_post_ptfb_export_csv', 'ptfb_handle_export_csv' );

/* Keys of the feedback already on the site (any status except the Trash). */
function ptfb_existing_keys() {
	$keys  = array();
	$posts = get_posts(
		array(
			'post_type'      => PTFB_CPT,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
		)
	);
	foreach ( $posts as $post ) {
		$keys[ ptfb_csv_item_key( $post->post_title, $post->post_content ) ] = true;
	}
	return $keys;
}

/* Adds the feedback that are not there yet. Returns array( 'added' => n, 'duplicate' => n, 'invalid' => n ). */
function ptfb_import_items( $items ) {
	$count = array(
		'added'     => 0,
		'duplicate' => 0,
		'invalid'   => 0,
	);
	$keys  = ptfb_existing_keys();
	foreach ( $items as $item ) {
		$key = ptfb_csv_item_key( $item['name'], $item['comment'] );
		if ( isset( $keys[ $key ] ) ) {
			++$count['duplicate'];
			continue;
		}
		$post = array(
			'post_type'    => PTFB_CPT,
			// Same rule as the editor: who may not publish leaves the feedback waiting for review.
			'post_status'  => ( 'publish' === $item['status'] && ! current_user_can( 'publish_posts' ) ) ? 'pending' : $item['status'],
			'post_title'   => $item['name'],
			'post_content' => $item['comment'],
			'menu_order'   => $item['order'],
		);
		if ( '' !== $item['date'] ) {
			$post['post_date']     = $item['date'];
			$post['post_date_gmt'] = get_gmt_from_date( $item['date'] );
		}
		$id = wp_insert_post( wp_slash( $post ), true );
		if ( is_wp_error( $id ) || ! $id ) {
			++$count['invalid'];
			continue;
		}
		$keys[ $key ] = true;
		++$count['added'];
	}
	return $count;
}

function ptfb_tools_page_url( $args = array() ) {
	return add_query_arg( $args, admin_url( 'edit.php?post_type=' . PTFB_CPT . '&page=ptfb-tools' ) );
}

/* Checks the uploaded file and reads it. Returns array( 'error' => code ) or the result of ptfb_csv_read_items(). */
function ptfb_read_uploaded_csv() {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- the nonce was verified by ptfb_handle_import() before this function runs.
	if ( empty( $_FILES['ptfb_csv'] ) || ! is_array( $_FILES['ptfb_csv'] ) ) {
		return array( 'error' => 'nofile' );
	}
	$file = $_FILES['ptfb_csv']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- every part is checked below.
	// phpcs:enable WordPress.Security.NonceVerification.Missing
	$error = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
	if ( UPLOAD_ERR_NO_FILE === $error ) {
		return array( 'error' => 'nofile' );
	}
	if ( UPLOAD_ERR_INI_SIZE === $error || UPLOAD_ERR_FORM_SIZE === $error ) {
		return array( 'error' => 'toobig' );
	}
	$tmp  = isset( $file['tmp_name'] ) && is_string( $file['tmp_name'] ) ? $file['tmp_name'] : '';
	$name = isset( $file['name'] ) && is_string( $file['name'] ) ? sanitize_file_name( wp_unslash( $file['name'] ) ) : '';
	if ( UPLOAD_ERR_OK !== $error || '' === $tmp || ! is_uploaded_file( $tmp ) ) {
		return array( 'error' => 'upload' );
	}
	if ( ! in_array( strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ), array( 'csv', 'txt' ), true ) ) {
		return array( 'error' => 'type' );
	}
	if ( filesize( $tmp ) > PTFB_CSV_MAX_BYTES ) {
		return array( 'error' => 'toobig' );
	}
	$text = file_get_contents( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local temporary file of the upload, not a URL.
	if ( false === $text ) {
		return array( 'error' => 'upload' );
	}
	return ptfb_csv_read_items( $text );
}

function ptfb_handle_import_csv() {
	if ( ! current_user_can( ptfb_manage_capability() ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'page-turn-feedback' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'ptfb_import_csv' );

	$read = ptfb_read_uploaded_csv();
	if ( ! empty( $read['error'] ) ) {
		wp_safe_redirect( ptfb_tools_page_url( array( 'ptfb_error' => $read['error'] ) ) );
		exit;
	}
	$count = ptfb_import_items( $read['items'] );
	wp_safe_redirect(
		ptfb_tools_page_url(
			array(
				'ptfb_added'   => $count['added'],
				'ptfb_already' => $count['duplicate'],
				'ptfb_skipped' => $count['invalid'] + $read['invalid'],
			)
		)
	);
	exit;
}
add_action( 'admin_post_ptfb_import_csv', 'ptfb_handle_import_csv' );

/* Message for each error code of the import (the code comes from the address bar, so it is looked up, never printed). */
function ptfb_import_error_messages() {
	return array(
		'nofile'  => __( 'Choose a CSV file first.', 'page-turn-feedback' ),
		'upload'  => __( 'The file could not be uploaded. Please try again.', 'page-turn-feedback' ),
		'toobig'  => __( 'The file is too big (1 MB at most).', 'page-turn-feedback' ),
		'type'    => __( 'The file must be a CSV file (name ending in .csv).', 'page-turn-feedback' ),
		'empty'   => __( 'The file is empty.', 'page-turn-feedback' ),
		'header'  => __( 'The first row must contain the column names, and one of them must be “comment”. Download the example file to see how it is written.', 'page-turn-feedback' ),
		'toomany' => __( 'The file has too many rows. Split it into smaller files.', 'page-turn-feedback' ),
	);
}

function ptfb_tools_page() {
	if ( ! current_user_can( ptfb_manage_capability() ) ) {
		return;
	}
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- these values only choose which message to show; they are looked up or cast to numbers.
	$error   = isset( $_GET['ptfb_error'] ) ? sanitize_key( wp_unslash( $_GET['ptfb_error'] ) ) : '';
	$done    = isset( $_GET['ptfb_added'] );
	$added   = isset( $_GET['ptfb_added'] ) ? absint( wp_unslash( $_GET['ptfb_added'] ) ) : 0;
	$already = isset( $_GET['ptfb_already'] ) ? absint( wp_unslash( $_GET['ptfb_already'] ) ) : 0;
	$skipped = isset( $_GET['ptfb_skipped'] ) ? absint( wp_unslash( $_GET['ptfb_skipped'] ) ) : 0;
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
	$messages = ptfb_import_error_messages();
	$code     = array( 'code' => array() );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Import and export', 'page-turn-feedback' ); ?></h1>

		<?php if ( '' !== $error && isset( $messages[ $error ] ) ) : ?>
			<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $messages[ $error ] ); ?></p></div>
		<?php elseif ( $done ) : ?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: feedback added, 2: feedback skipped because already on the site, 3: rows skipped because they have no comment. */
							__( 'Import finished: %1$d added, %2$d skipped because they were already here, %3$d skipped because they had no comment.', 'page-turn-feedback' ),
							$added,
							$already,
							$skipped
						)
					);
					?>
				</p>
			</div>
		<?php endif; ?>

		<h2 class="title"><?php esc_html_e( 'Export to a CSV file', 'page-turn-feedback' ); ?></h2>
		<p><?php esc_html_e( 'Download all your feedback (published and drafts) in a CSV file. You can open it with Excel, Google Sheets or LibreOffice, keep it as a copy, or import it again here or on another site.', 'page-turn-feedback' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="ptfb_export_csv">
			<?php wp_nonce_field( 'ptfb_export_csv' ); ?>
			<p>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Download CSV file', 'page-turn-feedback' ); ?></button>
				<button type="submit" name="ptfb_sample" value="1" class="button"><?php esc_html_e( 'Download an example file', 'page-turn-feedback' ); ?></button>
			</p>
		</form>

		<h2 class="title"><?php esc_html_e( 'Import from a CSV file', 'page-turn-feedback' ); ?></h2>
		<p><?php esc_html_e( 'Add many feedback at once from a CSV file. The first row must contain the column names:', 'page-turn-feedback' ); ?></p>
		<ul class="ul-disc">
			<li><?php echo wp_kses( __( '<code>name</code>: who left the feedback', 'page-turn-feedback' ), $code ); ?></li>
			<li><?php echo wp_kses( __( '<code>comment</code>: the text (required)', 'page-turn-feedback' ), $code ); ?></li>
			<li><?php echo wp_kses( __( '<code>status</code>: <code>publish</code> to show it on the site, <code>draft</code> to keep it hidden (if the column is missing: publish)', 'page-turn-feedback' ), $code ); ?></li>
			<li><?php echo wp_kses( __( '<code>date</code>: for example <code>2026-03-15</code> (if missing: today)', 'page-turn-feedback' ), $code ); ?></li>
			<li><?php echo wp_kses( __( '<code>order</code>: place on the site, 1 comes first (if missing: automatic order)', 'page-turn-feedback' ), $code ); ?></li>
		</ul>
		<p class="description">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %d: maximum number of rows in a file. */
					__( 'Feedback that is already on the site (same name and same comment) is skipped, so importing the same file twice does no harm. Nothing is changed or deleted. At most %d rows and 1 MB per file; semicolons and commas are both fine.', 'page-turn-feedback' ),
					(int) apply_filters( 'ptfb_import_max_rows', 500 )
				)
			);
			?>
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
			<input type="hidden" name="action" value="ptfb_import_csv">
			<?php wp_nonce_field( 'ptfb_import_csv' ); ?>
			<p>
				<label for="ptfb_csv" class="screen-reader-text"><?php esc_html_e( 'CSV file', 'page-turn-feedback' ); ?></label>
				<input type="file" id="ptfb_csv" name="ptfb_csv" accept=".csv,.txt,text/csv">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Import', 'page-turn-feedback' ); ?></button>
			</p>
		</form>

		<h2 class="title"><?php esc_html_e( 'Move to another WordPress site (XML)', 'page-turn-feedback' ); ?></h2>
		<p>
			<?php esc_html_e( 'WordPress can already move content between sites with an XML file. On this site open Tools → Export and choose “Feedback”; on the other site open Tools → Import → WordPress (it asks you to install the free WordPress Importer plugin).', 'page-turn-feedback' ); ?>
			<a href="<?php echo esc_url( admin_url( 'export.php' ) ); ?>"><?php esc_html_e( 'Open Tools → Export', 'page-turn-feedback' ); ?></a>
		</p>
	</div>
	<?php
}
