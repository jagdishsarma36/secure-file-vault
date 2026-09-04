<?php
/**
 * CSV import logic for Secure File Vault.
 *
 * Detects common password-manager CSV formats (Bitwarden, LastPass, Google),
 * normalizes rows into a common shape, and inserts them into the vault.
 */

function wfv_detect_csv_format( $header ) {
	if ( in_array( 'login_uri', $header, true ) || in_array( 'login_password', $header, true ) ) {
		return 'bitwarden';
	}
	if ( in_array( 'extra', $header, true ) && in_array( 'grouping', $header, true ) ) {
		return 'lastpass';
	}
	if ( in_array( 'password', $header, true ) && in_array( 'note', $header, true ) && ! in_array( 'notes', $header, true ) ) {
		return 'google'; // Google Password Manager: name,url,username,password,note
	}
	if ( ! in_array( 'password', $header, true ) && in_array( 'content', $header, true ) ) {
		return 'generic_notes'; // a plain notes-only CSV: title,content,tags
	}
	return 'generic'; // title,username,password,url,notes,tags (flexible column names)
}

function wfv_normalize_import_row( $r, $format ) {
	$get = function ( $keys ) use ( $r ) {
		foreach ( (array) $keys as $k ) {
			if ( isset( $r[ $k ] ) && '' !== trim( (string) $r[ $k ] ) ) {
				return trim( (string) $r[ $k ] );
			}
		}
		return '';
	};

	switch ( $format ) {
		case 'bitwarden':
			$is_note = 'note' === strtolower( $get( 'type' ) );
			return array(
				'kind'     => $is_note ? 'note' : 'password',
				'title'    => $get( 'name' ),
				'username' => $get( 'login_username' ),
				'password' => $get( 'login_password' ),
				'url'      => $get( 'login_uri' ),
				'notes'    => $get( 'notes' ),
				'tags'     => $get( 'folder' ),
				'starred'  => in_array( strtolower( $get( 'favorite' ) ), array( '1', 'true' ), true ),
			);

		case 'lastpass':
			$url     = $get( 'url' );
			$is_note = ( 'http://sn' === $url ); // LastPass's sentinel URL for Secure Notes
			return array(
				'kind'     => $is_note ? 'note' : 'password',
				'title'    => $get( 'name' ),
				'username' => $get( 'username' ),
				'password' => $get( 'password' ),
				'url'      => $is_note ? '' : $url,
				'notes'    => $get( 'extra' ),
				'tags'     => $get( 'grouping' ),
				'starred'  => '1' === $get( 'fav' ),
			);

		case 'google':
			return array(
				'kind'     => 'password',
				'title'    => $get( 'name' ),
				'username' => $get( 'username' ),
				'password' => $get( 'password' ),
				'url'      => $get( 'url' ),
				'notes'    => $get( 'note' ),
				'tags'     => '',
				'starred'  => false,
			);

		case 'generic_notes':
			return array(
				'kind'     => 'note',
				'title'    => $get( array( 'title', 'name' ) ),
				'username' => '',
				'password' => '',
				'url'      => '',
				'notes'    => $get( array( 'content', 'notes', 'note' ) ),
				'tags'     => $get( array( 'tags', 'folder', 'category' ) ),
				'starred'  => false,
			);

		default: // generic password CSV
			return array(
				'kind'     => 'password',
				'title'    => $get( array( 'title', 'name' ) ),
				'username' => $get( array( 'username', 'user', 'email' ) ),
				'password' => $get( 'password' ),
				'url'      => $get( array( 'url', 'website', 'site', 'login_uri' ) ),
				'notes'    => $get( array( 'notes', 'note', 'extra' ) ),
				'tags'     => $get( array( 'tags', 'folder', 'grouping', 'category' ) ),
				'starred'  => false,
			);
	}
}

/** Reads an uploaded CSV file (by its temp path) and returns an array of normalized rows. */
function wfv_parse_import_csv_file( $path ) {
	$handle = @fopen( $path, 'r' );
	if ( ! $handle ) {
		return array();
	}

	$header = fgetcsv( $handle );
	if ( ! $header ) {
		fclose( $handle );
		return array();
	}
	// Strip a UTF-8 BOM if present on the first header cell, then normalize.
	$header[0] = preg_replace( '/^\xEF\xBB\xBF/', '', $header[0] );
	$header    = array_map(
		function ( $h ) {
			return strtolower( trim( (string) $h ) );
		},
		$header
	);

	$format = wfv_detect_csv_format( $header );

	$rows  = array();
	$count = 0;
	while ( false !== ( $data = fgetcsv( $handle ) ) && $count < 2000 ) { // sane upper bound
		if ( 1 === count( $data ) && '' === trim( (string) $data[0] ) ) {
			continue; // skip blank lines
		}
		$assoc = array();
		foreach ( $header as $i => $col_name ) {
			$assoc[ $col_name ] = isset( $data[ $i ] ) ? $data[ $i ] : '';
		}
		$rows[] = wfv_normalize_import_row( $assoc, $format );
		$count++;
	}
	fclose( $handle );
	return $rows;
}

function wfv_process_import_csv() {
	check_admin_referer( 'wfv_import_csv', 'wfv_import_nonce' );

	if ( empty( $_FILES['import_file'] ) || ! isset( $_FILES['import_file']['error'] ) || UPLOAD_ERR_OK !== $_FILES['import_file']['error'] ) {
		wfv_redirect_with_notice( 'error', 'Please choose a CSV file to import.' );
	}
	if ( (int) $_FILES['import_file']['size'] > 5 * MB_IN_BYTES ) {
		wfv_redirect_with_notice( 'error', 'That file is larger than 5MB — please split it or contact support.' );
	}

	$rows = wfv_parse_import_csv_file( $_FILES['import_file']['tmp_name'] );
	if ( empty( $rows ) ) {
		wfv_redirect_with_notice( 'error', 'No rows could be read from that file. Make sure it is a CSV export from LastPass, Google Password Manager, or Bitwarden, or matches: title,username,password,url,notes,tags' );
	}

	global $wpdb;
	$user_id = get_current_user_id();
	$key     = wfv_pm_active_key(); // null if a master password is set but the vault is locked

	$pw_count   = 0;
	$note_count = 0;
	$skipped    = 0;
	$vault_locked_skips = 0;

	foreach ( $rows as $row ) {
		if ( 'note' === $row['kind'] ) {
			if ( '' === $row['title'] && '' === $row['notes'] ) {
				$skipped++;
				continue;
			}
			$wpdb->insert(
				wfv_notes_table(),
				array(
					'title'      => sanitize_text_field( $row['title'] ),
					'content'    => wp_kses_post( $row['notes'] ),
					'color'      => 'yellow',
					'tags'       => wfv_parse_tags( $row['tags'] ),
					'pinned'     => 0,
					'sort_order' => 0,
					'created_by' => $user_id,
					'created_at' => current_time( 'mysql' ),
					'updated_at' => current_time( 'mysql' ),
				),
				array( '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s' )
			);
			$note_count++;
			continue;
		}

		// Password row.
		if ( '' === $row['title'] ) {
			$skipped++;
			continue;
		}
		if ( null === $key ) {
			$skipped++;
			$vault_locked_skips++;
			continue;
		}
		$wpdb->insert(
			wfv_passwords_table(),
			array(
				'title'              => sanitize_text_field( $row['title'] ),
				'username'           => sanitize_text_field( $row['username'] ),
				'password_encrypted' => wfv_pm_encrypt_raw( $row['password'], $key ),
				'url'                => esc_url_raw( $row['url'] ),
				'notes_encrypted'    => wfv_pm_encrypt_raw( sanitize_textarea_field( $row['notes'] ), $key ),
				'tags'               => wfv_parse_tags( $row['tags'] ),
				'color'              => 'gray',
				'starred'            => $row['starred'] ? 1 : 0,
				'created_by'         => $user_id,
				'created_at'         => current_time( 'mysql' ),
				'updated_at'         => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
		);
		$pw_count++;
	}

	$msg = sprintf( 'Imported %d password%s and %d note%s.', $pw_count, 1 === $pw_count ? '' : 's', $note_count, 1 === $note_count ? '' : 's' );
	if ( $skipped ) {
		$msg .= sprintf( ' Skipped %d row%s', $skipped, 1 === $skipped ? '' : 's' );
		$msg .= $vault_locked_skips ? ' (unlock your vault to import passwords).' : ' (missing a title).';
	}
	$msg .= ' For your security, please delete the original export file from your computer now.';

	wfv_redirect_with_notice( 'success', $msg );
}
