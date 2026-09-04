<?php
/**
 * Admin-side preview handler.
 *
 * Allows an admin or file owner to view a file inline directly from the
 * admin dashboard without creating a public share link first.
 */

add_action( 'admin_post_wfv_preview', 'wfv_admin_preview_handler' );
function wfv_admin_preview_handler() {
	if ( ! is_user_logged_in() ) {
		wp_die( 'Not allowed.', 403 );
	}
	$file_id = isset( $_GET['file_id'] ) ? absint( $_GET['file_id'] ) : 0;
	check_admin_referer( 'wfv_preview_' . $file_id );

	global $wpdb;
	$file = $file_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . wfv_files_table() . " WHERE id = %d", $file_id ) ) : null;

	if ( ! wfv_user_can_manage_file( $file ) ) {
		wp_die( 'You do not have permission to view this file.', 403 );
	}

	$path = trailingslashit( wfv_private_dir_path() ) . $file->stored_name;
	if ( ! file_exists( $path ) ) {
		wp_die( 'File not found.', 404 );
	}

	while ( ob_get_level() ) {
		ob_end_clean();
	}

	header( 'X-Content-Type-Options: nosniff' );
	if ( in_array( $file->mime_type, wfv_previewable_types(), true ) ) {
		header( 'Content-Type: ' . $file->mime_type );
		header( 'Content-Disposition: inline; filename="' . rawurlencode( $file->original_name ) . '"' );
	} else {
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="' . rawurlencode( $file->original_name ) . '"' );
	}
	header( 'Content-Length: ' . filesize( $path ) );
	readfile( $path );
	exit;
}
