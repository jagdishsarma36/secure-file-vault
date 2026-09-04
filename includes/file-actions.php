<?php
/**
 * Secure File Vault – File and folder action handlers.
 *
 * Processes uploads, share-link management, file operations,
 * and folder CRUD actions triggered from the admin dashboard.
 *
 * @package SecureFileVault
 */

function wfv_process_upload() {
	check_admin_referer( 'wfv_upload', 'wfv_upload_nonce' );

	if ( empty( $_FILES['wfv_file'] ) ) {
		wfv_redirect_with_notice( 'error', 'Upload failed. Please try again.' );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';

	global $wpdb;

	// Only upload into a folder the current user actually owns (or any
	// folder, if they're an admin) — otherwise fall back to root.
	$folder_id = isset( $_POST['folder_id'] ) ? absint( $_POST['folder_id'] ) : 0;
	if ( $folder_id ) {
		$folder = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . wfv_folders_table() . " WHERE id = %d", $folder_id ) );
		if ( ! wfv_user_can_manage_folder( $folder ) ) {
			$folder_id = 0;
		}
	}

	wfv_prepare_private_dir();

	// Normalize the incoming files into a flat list (handles both a single
	// "name" and the multi-upload "name[]" array shapes).
	$files = array();
	if ( isset( $_FILES['wfv_file']['name'] ) ) {
		if ( is_array( $_FILES['wfv_file']['name'] ) ) {
			$count = count( $_FILES['wfv_file']['name'] );
			for ( $i = 0; $i < $count; $i++ ) {
				if ( UPLOAD_ERR_NO_FILE === intval( $_FILES['wfv_file']['error'][ $i ] ) ) {
					continue;
				}
				$files[] = array(
					'name'     => $_FILES['wfv_file']['name'][ $i ],
					'type'     => $_FILES['wfv_file']['type'][ $i ],
					'tmp_name' => $_FILES['wfv_file']['tmp_name'][ $i ],
					'error'    => intval( $_FILES['wfv_file']['error'][ $i ] ),
					'size'     => intval( $_FILES['wfv_file']['size'][ $i ] ),
				);
			}
		} else {
			$files[] = array(
				'name'     => $_FILES['wfv_file']['name'],
				'type'     => $_FILES['wfv_file']['type'],
				'tmp_name' => $_FILES['wfv_file']['tmp_name'],
				'error'    => intval( $_FILES['wfv_file']['error'] ),
				'size'     => intval( $_FILES['wfv_file']['size'] ),
			);
		}
	}

	if ( empty( $files ) ) {
		wfv_redirect_with_notice( 'error', 'Upload failed. No file was selected.' );
	}

	$uploaded = 0;
	$failed   = 0;
	foreach ( $files as $file ) {
		$result = wfv_store_uploaded_file( $file, $folder_id );
		if ( $result['ok'] ) {
			$uploaded++;
		} else {
			$failed++;
		}
	}

	if ( 0 === $uploaded && 0 === $failed ) {
		wfv_redirect_with_notice( 'error', 'Upload failed. Please try again.' );
	}

	if ( $failed && $uploaded ) {
		wfv_redirect_with_notice( 'warning', sprintf( '%d of %d files uploaded. %d failed.', $uploaded, count( $files ), $failed ) );
	}

	if ( 0 === $uploaded && $failed ) {
		wfv_redirect_with_notice( 'error', sprintf( 'Upload failed: %d file(s) could not be uploaded.', $failed ) );
	}

	wfv_redirect_with_notice( 'success', sprintf( '%d file%s uploaded.', $uploaded, 1 === $uploaded ? '' : 's' ) );
}

/**
 * Move a single uploaded file into the private directory and record it in the
 * database. Returns array( 'ok' => bool, 'id' => int, 'message' => string ).
 * Used by both the synchronous (no-JS fallback) and the AJAX upload paths so
 * the two never diverge.
 */
function wfv_store_uploaded_file( $file, $folder_id ) {
	if ( empty( $file['tmp_name'] ) || UPLOAD_ERR_OK !== intval( $file['error'] ) ) {
		return array( 'ok' => false, 'id' => 0, 'message' => 'Upload failed.' );
	}

	if ( ! function_exists( 'wp_handle_upload' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}

	wfv_prepare_private_dir();
	$target_dir = wfv_private_dir_path();

	$original_name = sanitize_file_name( $file['name'] );
	$ext           = strtolower( pathinfo( $original_name, PATHINFO_EXTENSION ) );
	$stored_name   = wp_generate_password( 20, false, false ) . '_' . time() . '_' . wp_rand( 100, 999 ) . ( $ext ? '.' . $ext : '' );

	// Redirect WordPress's upload handler to our private directory for this call.
	$override_dir = function ( $dirs ) use ( $target_dir ) {
		$dirs['path']   = $target_dir;
		$dirs['url']    = 'about:blank'; // never expose a direct public URL
		$dirs['subdir'] = '';
		return $dirs;
	};
	add_filter( 'upload_dir', $override_dir );

	$single_file = array(
		'name'     => $stored_name,
		'type'     => isset( $file['type'] ) ? $file['type'] : 'application/octet-stream',
		'tmp_name' => $file['tmp_name'],
		'error'    => intval( $file['error'] ),
		'size'     => intval( $file['size'] ),
	);

	$result = wp_handle_upload(
		$single_file,
		array(
			'test_form' => false,
			'action'    => 'wfv_upload_action',
		)
	);

	remove_filter( 'upload_dir', $override_dir );

	if ( isset( $result['error'] ) ) {
		return array( 'ok' => false, 'id' => 0, 'message' => $result['error'] );
	}

	global $wpdb;
	$wpdb->insert(
		wfv_files_table(),
		array(
			'original_name' => $original_name,
			'stored_name'   => basename( $result['file'] ),
			'mime_type'     => isset( $result['type'] ) ? $result['type'] : 'application/octet-stream',
			'file_size'     => filesize( $result['file'] ),
			'uploaded_by'   => get_current_user_id(),
			'folder_id'     => $folder_id ? $folder_id : null,
			'starred'       => 0,
			'created_at'    => current_time( 'mysql' ),
		),
		array( '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s' )
	);

	return array( 'ok' => true, 'id' => (int) $wpdb->insert_id, 'message' => $original_name );
}

/**
 * AJAX upload endpoint: handles a single file per request. The front end sends
 * files one at a time, so a large batch is never a single long-running request
 * (which is what caused upload timeouts).
 */
add_action( 'wp_ajax_wfv_upload_ajax', 'wfv_ajax_upload_handler' );
function wfv_ajax_upload_handler() {
	check_ajax_referer( 'wfv_upload', 'wfv_upload_nonce' );

	if ( empty( $_FILES['wfv_file'] ) ) {
		wp_send_json_error( array( 'message' => 'No file received.' ) );
	}
	if ( ! wfv_user_has_access() ) {
		wp_send_json_error( array( 'message' => 'You do not have permission to upload.' ) );
	}

	global $wpdb;
	$folder_id = isset( $_POST['folder_id'] ) ? absint( $_POST['folder_id'] ) : 0;
	if ( $folder_id ) {
		$folder = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . wfv_folders_table() . " WHERE id = %d", $folder_id ) );
		if ( ! wfv_user_can_manage_folder( $folder ) ) {
			$folder_id = 0;
		}
	}

	$file = array(
		'name'     => isset( $_FILES['wfv_file']['name'] ) ? $_FILES['wfv_file']['name'] : '',
		'type'     => isset( $_FILES['wfv_file']['type'] ) ? $_FILES['wfv_file']['type'] : 'application/octet-stream',
		'tmp_name' => isset( $_FILES['wfv_file']['tmp_name'] ) ? $_FILES['wfv_file']['tmp_name'] : '',
		'error'    => isset( $_FILES['wfv_file']['error'] ) ? intval( $_FILES['wfv_file']['error'] ) : 0,
		'size'     => isset( $_FILES['wfv_file']['size'] ) ? intval( $_FILES['wfv_file']['size'] ) : 0,
	);

	$result = wfv_store_uploaded_file( $file, $folder_id );

	if ( $result['ok'] ) {
		wp_send_json_success( array( 'message' => $result['message'], 'id' => $result['id'] ) );
	}
	wp_send_json_error( array( 'message' => $result['message'] ) );
}

function wfv_process_create_share() {
	check_admin_referer( 'wfv_create_share', 'wfv_share_nonce' );

	global $wpdb;
	$file_id = isset( $_POST['file_id'] ) ? absint( $_POST['file_id'] ) : 0;
	$file    = $file_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . wfv_files_table() . " WHERE id = %d", $file_id ) ) : null;

	if ( ! $file ) {
		wfv_redirect_with_notice( 'error', 'File not found.' );
	}
	if ( ! wfv_user_can_manage_file( $file ) ) {
		wfv_redirect_with_notice( 'error', 'You do not have permission to share this file.' );
	}

	$label       = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';
	$password    = isset( $_POST['password'] ) ? (string) $_POST['password'] : '';
	$expiry_days = isset( $_POST['expiry_days'] ) ? absint( $_POST['expiry_days'] ) : 0;
	$max_dl      = isset( $_POST['max_downloads'] ) ? absint( $_POST['max_downloads'] ) : 0;

	$token = wfv_generate_unique_token();

	$expires_at = null;
	if ( $expiry_days > 0 ) {
		$expires_at = gmdate( 'Y-m-d H:i:s', time() + ( $expiry_days * DAY_IN_SECONDS ) );
	}

	$wpdb->insert(
		wfv_shares_table(),
		array(
			'file_id'        => $file_id,
			'token'          => $token,
			'label'          => $label,
			'password_hash'  => '' !== $password ? wp_hash_password( $password ) : null,
			'expires_at'     => $expires_at,
			'max_downloads'  => $max_dl > 0 ? $max_dl : null,
			'download_count' => 0,
			'revoked'        => 0,
			'created_by'     => get_current_user_id(),
			'created_at'     => current_time( 'mysql' ),
		),
		array( '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s' )
	);

	wfv_redirect_with_notice( 'success', 'Share link created.', $file_id );
}

function wfv_process_revoke_share() {
	check_admin_referer( 'wfv_revoke_share', 'wfv_revoke_nonce' );
	global $wpdb;
	$share_id = isset( $_POST['share_id'] ) ? absint( $_POST['share_id'] ) : 0;
	$file_id  = isset( $_POST['file_id'] ) ? absint( $_POST['file_id'] ) : 0;
	$file     = $file_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . wfv_files_table() . " WHERE id = %d", $file_id ) ) : null;

	if ( ! wfv_user_can_manage_file( $file ) ) {
		wfv_redirect_with_notice( 'error', 'You do not have permission to revoke this link.' );
	}
	if ( $share_id ) {
		$wpdb->update( wfv_shares_table(), array( 'revoked' => 1 ), array( 'id' => $share_id, 'file_id' => $file_id ), array( '%d' ), array( '%d', '%d' ) );
	}
	wfv_redirect_with_notice( 'success', 'Share link revoked.', $file_id );
}

function wfv_process_delete_file() {
	check_admin_referer( 'wfv_delete_file', 'wfv_delete_nonce' );
	global $wpdb;
	$file_id = isset( $_POST['file_id'] ) ? absint( $_POST['file_id'] ) : 0;
	$file    = $file_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . wfv_files_table() . " WHERE id = %d", $file_id ) ) : null;

	if ( ! wfv_user_can_manage_file( $file ) ) {
		wfv_redirect_with_notice( 'error', 'You do not have permission to delete this file.' );
	}

	if ( $file ) {
		$path = trailingslashit( wfv_private_dir_path() ) . $file->stored_name;
		if ( file_exists( $path ) ) {
			@unlink( $path );
		}
		$wpdb->delete( wfv_shares_table(), array( 'file_id' => $file_id ), array( '%d' ) );
		$wpdb->delete( wfv_files_table(), array( 'id' => $file_id ), array( '%d' ) );
	}
	wfv_redirect_with_notice( 'success', 'File deleted.' );
}

function wfv_process_move_file() {
	check_admin_referer( 'wfv_move_file', 'wfv_move_nonce' );
	global $wpdb;
	$file_id = isset( $_POST['file_id'] ) ? absint( $_POST['file_id'] ) : 0;
	$file    = $file_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . wfv_files_table() . " WHERE id = %d", $file_id ) ) : null;

	if ( ! wfv_user_can_manage_file( $file ) ) {
		wfv_redirect_with_notice( 'error', 'You do not have permission to move this file.' );
	}

	$folder_id = isset( $_POST['folder_id'] ) ? absint( $_POST['folder_id'] ) : 0;
	if ( $folder_id ) {
		$folder = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . wfv_folders_table() . " WHERE id = %d", $folder_id ) );
		if ( ! wfv_user_can_manage_folder( $folder ) ) {
			wfv_redirect_with_notice( 'error', 'You cannot move a file into that folder.' );
		}
	}

	$wpdb->update( wfv_files_table(), array( 'folder_id' => $folder_id ? $folder_id : null ), array( 'id' => $file_id ), array( '%d' ), array( '%d' ) );
	wfv_redirect_with_notice( 'success', 'File moved.' );
}

function wfv_process_toggle_star_file() {
	check_admin_referer( 'wfv_toggle_star_file', 'wfv_star_nonce' );
	global $wpdb;
	$file_id = isset( $_POST['file_id'] ) ? absint( $_POST['file_id'] ) : 0;
	$file    = $file_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . wfv_files_table() . " WHERE id = %d", $file_id ) ) : null;

	if ( ! wfv_user_can_manage_file( $file ) ) {
		wfv_redirect_with_notice( 'error', 'You do not have permission to star this file.' );
	}
	$wpdb->update( wfv_files_table(), array( 'starred' => $file->starred ? 0 : 1 ), array( 'id' => $file_id ), array( '%d' ), array( '%d' ) );
	wfv_redirect_with_notice( 'success', $file->starred ? 'Removed from starred.' : 'Added to starred.' );
}

/**
 * ------------------------------------------------------------------
 * Folder actions
 * ------------------------------------------------------------------
 */
function wfv_process_create_folder() {
	check_admin_referer( 'wfv_create_folder', 'wfv_folder_nonce' );
	global $wpdb;

	$name   = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	$colors = wfv_colors();
	$color  = isset( $_POST['color'] ) ? sanitize_key( $_POST['color'] ) : 'blue';
	if ( ! array_key_exists( $color, $colors ) ) {
		$color = 'blue';
	}
	$parent_id = isset( $_POST['parent_id'] ) ? absint( $_POST['parent_id'] ) : 0;

	if ( '' === $name ) {
		wfv_redirect_with_notice( 'error', 'Folder name is required.' );
	}

	if ( $parent_id ) {
		$parent = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . wfv_folders_table() . " WHERE id = %d", $parent_id ) );
		if ( ! wfv_user_can_manage_folder( $parent ) ) {
			$parent_id = 0;
		}
	}

	$wpdb->insert(
		wfv_folders_table(),
		array(
			'name'       => $name,
			'color'      => $color,
			'parent_id'  => $parent_id ? $parent_id : null,
			'starred'    => 0,
			'created_by' => get_current_user_id(),
			'created_at' => current_time( 'mysql' ),
		),
		array( '%s', '%s', '%d', '%d', '%d', '%s' )
	);

	wfv_redirect_with_notice( 'success', 'Folder created.' );
}

function wfv_process_rename_folder() {
	check_admin_referer( 'wfv_rename_folder', 'wfv_rename_nonce' );
	global $wpdb;

	$folder_id = isset( $_POST['folder_id'] ) ? absint( $_POST['folder_id'] ) : 0;
	$folder    = $folder_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . wfv_folders_table() . " WHERE id = %d", $folder_id ) ) : null;

	if ( ! wfv_user_can_manage_folder( $folder ) ) {
		wfv_redirect_with_notice( 'error', 'You do not have permission to edit this folder.' );
	}

	$colors = wfv_colors();
	$name   = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	$color  = isset( $_POST['color'] ) ? sanitize_key( $_POST['color'] ) : $folder->color;
	if ( ! array_key_exists( $color, $colors ) ) {
		$color = $folder->color;
	}

	$wpdb->update(
		wfv_folders_table(),
		array(
			'name'  => '' !== $name ? $name : $folder->name,
			'color' => $color,
		),
		array( 'id' => $folder_id ),
		array( '%s', '%s' ),
		array( '%d' )
	);

	wfv_redirect_with_notice( 'success', 'Folder updated.' );
}

function wfv_process_delete_folder() {
	check_admin_referer( 'wfv_delete_folder', 'wfv_delete_folder_nonce' );
	global $wpdb;

	$folder_id = isset( $_POST['folder_id'] ) ? absint( $_POST['folder_id'] ) : 0;
	$folder    = $folder_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . wfv_folders_table() . " WHERE id = %d", $folder_id ) ) : null;

	if ( ! wfv_user_can_manage_folder( $folder ) ) {
		wfv_redirect_with_notice( 'error', 'You do not have permission to delete this folder.' );
	}

	$parent_id = $folder->parent_id ? (int) $folder->parent_id : null;

	// Move contents up one level rather than deleting them.
	$wpdb->update( wfv_folders_table(), array( 'parent_id' => $parent_id ), array( 'parent_id' => $folder_id ), array( '%d' ), array( '%d' ) );
	$wpdb->update( wfv_files_table(), array( 'folder_id' => $parent_id ), array( 'folder_id' => $folder_id ), array( '%d' ), array( '%d' ) );
	// Clean up any folder shares (both public links and user shares).
	$wpdb->delete( wfv_folder_shares_table(), array( 'folder_id' => $folder_id ), array( '%d' ) );
	$wpdb->delete( wfv_folder_user_shares_table(), array( 'folder_id' => $folder_id ), array( '%d' ) );
	$wpdb->delete( wfv_folders_table(), array( 'id' => $folder_id ), array( '%d' ) );

	wfv_redirect_with_notice( 'success', 'Folder deleted; its contents moved up a level.' );
}

function wfv_process_toggle_star_folder() {
	check_admin_referer( 'wfv_toggle_star_folder', 'wfv_star_folder_nonce' );
	global $wpdb;
	$folder_id = isset( $_POST['folder_id'] ) ? absint( $_POST['folder_id'] ) : 0;
	$folder    = $folder_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . wfv_folders_table() . " WHERE id = %d", $folder_id ) ) : null;

	if ( ! wfv_user_can_manage_folder( $folder ) ) {
		wfv_redirect_with_notice( 'error', 'You do not have permission to star this folder.' );
	}
	$wpdb->update( wfv_folders_table(), array( 'starred' => $folder->starred ? 0 : 1 ), array( 'id' => $folder_id ), array( '%d' ), array( '%d' ) );
	wfv_redirect_with_notice( 'success', $folder->starred ? 'Removed from starred.' : 'Added to starred.' );
}

/**
 * ------------------------------------------------------------------
 * Folder share handlers
 *
 * Two ways to share a folder:
 *   - A public, revocable link (wfv_folder_shares) that lets a recipient
 *     browse a read-only snapshot of the folder's files and subfolders.
 *   - Sharing to a specific WordPress user (wfv_folder_user_shares);
 *     the recipient sees the live folder under "Shared with me".
 * ------------------------------------------------------------------
 */

/** Create a public share link for a folder. */
function wfv_process_create_folder_share() {
	check_admin_referer( 'wfv_create_folder_share', 'wfv_folder_share_nonce' );

	global $wpdb;
	$folder_id = isset( $_POST['folder_id'] ) ? absint( $_POST['folder_id'] ) : 0;
	$folder    = $folder_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . wfv_folders_table() . " WHERE id = %d", $folder_id ) ) : null;

	if ( ! $folder || ! wfv_user_can_manage_folder( $folder ) ) {
		wfv_redirect_with_notice( 'error', 'You do not have permission to share this folder.' );
	}

	$label       = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';
	$password    = isset( $_POST['password'] ) ? (string) $_POST['password'] : '';
	$expiry_days = isset( $_POST['expiry_days'] ) ? absint( $_POST['expiry_days'] ) : 0;
	$max_dl      = isset( $_POST['max_downloads'] ) ? absint( $_POST['max_downloads'] ) : 0;

	$token = wfv_generate_unique_token();

	$expires_at = null;
	if ( $expiry_days > 0 ) {
		$expires_at = gmdate( 'Y-m-d H:i:s', time() + ( $expiry_days * DAY_IN_SECONDS ) );
	}

	$wpdb->insert(
		wfv_folder_shares_table(),
		array(
			'folder_id'       => $folder_id,
			'token'           => $token,
			'label'           => $label,
			'password_hash'   => '' !== $password ? wp_hash_password( $password ) : null,
			'expires_at'      => $expires_at,
			'max_downloads'   => $max_dl > 0 ? $max_dl : null,
			'download_count'  => 0,
			'revoked'         => 0,
			'created_by'      => get_current_user_id(),
			'created_at'      => current_time( 'mysql' ),
		),
		array( '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s' )
	);

	wfv_redirect_with_notice( 'success', 'Folder share link created.' );
}

/** Revoke a public folder share link. */
function wfv_process_revoke_folder_share() {
	check_admin_referer( 'wfv_revoke_folder_share', 'wfv_revoke_folder_share_nonce' );
	global $wpdb;

	$share_id  = isset( $_POST['share_id'] ) ? absint( $_POST['share_id'] ) : 0;
	$folder_id = isset( $_POST['folder_id'] ) ? absint( $_POST['folder_id'] ) : 0;
	$share     = $share_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . wfv_folder_shares_table() . " WHERE id = %d", $share_id ) ) : null;

	if ( ! $share || ! wfv_user_can_manage_folder_share( $share ) ) {
		wfv_redirect_with_notice( 'error', 'You do not have permission to revoke this link.' );
	}

	$wpdb->update( wfv_folder_shares_table(), array( 'revoked' => 1 ), array( 'id' => $share_id ), array( '%d' ), array( '%d' ) );
	wfv_redirect_with_notice( 'success', 'Folder share link revoked.' );
}

/** Share a folder with a specific WordPress user. */
function wfv_process_share_folder_to_user() {
	check_admin_referer( 'wfv_share_folder_to_user', 'wfv_share_folder_user_nonce' );

	global $wpdb;
	$folder_id = isset( $_POST['folder_id'] ) ? absint( $_POST['folder_id'] ) : 0;
	$folder    = $folder_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . wfv_folders_table() . " WHERE id = %d", $folder_id ) ) : null;
	$user_id   = isset( $_POST['share_user_id'] ) ? absint( $_POST['share_user_id'] ) : 0;

	if ( ! $folder || ! wfv_user_can_manage_folder( $folder ) ) {
		wfv_redirect_with_notice( 'error', 'You do not have permission to share this folder.' );
	}
	if ( ! $user_id || ! get_userdata( $user_id ) ) {
		wfv_redirect_with_notice( 'error', 'Please choose a valid user.' );
	}
	if ( (int) $user_id === (int) get_current_user_id() ) {
		wfv_redirect_with_notice( 'error', 'You cannot share a folder with yourself.' );
	}

	$existing = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT id FROM " . wfv_folder_user_shares_table() . " WHERE folder_id = %d AND shared_with = %d AND revoked = 0",
			$folder_id,
			$user_id
		)
	);

	if ( ! $existing ) {
		$wpdb->insert(
			wfv_folder_user_shares_table(),
			array(
				'folder_id'   => $folder_id,
				'shared_by'   => get_current_user_id(),
				'shared_with' => $user_id,
				'revoked'     => 0,
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%d', '%s' )
		);
	}

	wfv_redirect_with_notice( 'success', 'Folder shared with the chosen user.' );
}

/** Revoke a folder share granted to a specific user. */
function wfv_process_revoke_folder_user_share() {
	check_admin_referer( 'wfv_revoke_folder_user_share', 'wfv_revoke_folder_user_nonce' );
	global $wpdb;

	$share_id  = isset( $_POST['share_id'] ) ? absint( $_POST['share_id'] ) : 0;
	$folder_id = isset( $_POST['folder_id'] ) ? absint( $_POST['folder_id'] ) : 0;
	$share     = $share_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . wfv_folder_user_shares_table() . " WHERE id = %d", $share_id ) ) : null;

	if ( ! $share || ! wfv_user_can_manage_folder_share( $share ) ) {
		wfv_redirect_with_notice( 'error', 'You do not have permission to revoke this share.' );
	}

	$wpdb->update( wfv_folder_user_shares_table(), array( 'revoked' => 1 ), array( 'id' => $share_id ), array( '%d' ), array( '%d' ) );
	wfv_redirect_with_notice( 'success', 'Folder share revoked.' );
}

/** A recipient removes a folder that was shared with them. */
function wfv_process_remove_folder_share() {
	check_admin_referer( 'wfv_remove_folder_share', 'wfv_remove_folder_share_nonce' );
	global $wpdb;

	$share_id = isset( $_POST['share_id'] ) ? absint( $_POST['share_id'] ) : 0;
	$share    = $share_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . wfv_folder_user_shares_table() . " WHERE id = %d", $share_id ) ) : null;

	if ( ! $share || ! wfv_user_is_folder_share_recipient( $share ) ) {
		wfv_redirect_with_notice( 'error', 'You do not have permission to remove this folder.' );
	}

	$wpdb->delete( wfv_folder_user_shares_table(), array( 'id' => $share_id ), array( '%d' ) );
	wfv_redirect_with_notice( 'success', 'Folder removed from your list.' );
}

/**
 * Stream a file to a user it was shared with (via folder user-sharing).
 * Accessed through admin-post with a nonce so only logged-in recipients can
 * download from a folder that was shared to them.
 */
add_action( 'admin_post_wfv_shared_folder_download', 'wfv_shared_folder_download', 1 );
function wfv_shared_folder_download() {
	$file_id = isset( $_GET['file_id'] ) ? absint( $_GET['file_id'] ) : 0;
	check_admin_referer( 'wfv_shared_folder_download_' . $file_id );

	global $wpdb;
	$file = $file_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . wfv_files_table() . " WHERE id = %d", $file_id ) ) : null;
	if ( ! $file || ! wfv_user_can_read_file( $file ) ) {
		wp_die( 'You do not have permission to download this file.', 'Access denied', array( 'response' => 403 ) );
	}

	$path = trailingslashit( wfv_private_dir_path() ) . $file->stored_name;
	if ( ! file_exists( $path ) ) {
		wp_die( 'The file is no longer available.', 'Not found', array( 'response' => 404 ) );
	}

	while ( ob_get_level() ) {
		ob_end_clean();
	}

	$want_download  = true;
	$can_preview    = in_array( $file->mime_type, wfv_previewable_types(), true ) && ! $want_download;

	header( 'X-Content-Type-Options: nosniff' );
	if ( $can_preview ) {
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
