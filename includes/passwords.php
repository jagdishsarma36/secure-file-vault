<?php
/**
 * Secure File Vault – Password manager functions.
 *
 * Password CRUD, vault lock/unlock, password sharing, and AJAX handlers.
 *
 * @package SecureFileVault
 */

/**
 * ------------------------------------------------------------------
 * Password manager actions
 * ------------------------------------------------------------------
 */
function wfv_process_create_password() {
	check_admin_referer( 'wfv_create_password', 'wfv_pw_nonce' );
	global $wpdb;

	$key = wfv_pm_active_key();
	if ( null === $key ) {
		wfv_redirect_with_notice( 'error', 'Your vault is locked — unlock it with your master password first.' );
	}

	$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
	if ( '' === $title ) {
		wfv_redirect_with_notice( 'error', 'A title / service name is required.' );
	}

	$username = isset( $_POST['username'] ) ? sanitize_text_field( wp_unslash( $_POST['username'] ) ) : '';
	$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
	$url      = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
	$notes    = isset( $_POST['notes'] ) ? (string) wp_unslash( $_POST['notes'] ) : '';
	$tags     = isset( $_POST['tags'] ) ? wfv_parse_tags( wp_unslash( $_POST['tags'] ) ) : '';
	$colors   = wfv_colors();
	$color    = isset( $_POST['color'] ) ? sanitize_key( $_POST['color'] ) : 'gray';
	if ( ! array_key_exists( $color, $colors ) ) {
		$color = 'gray';
	}

	$wpdb->insert(
		wfv_passwords_table(),
		array(
			'title'              => $title,
			'username'           => $username,
			'password_encrypted' => wfv_pm_encrypt_raw( $password, $key ),
			'url'                => $url,
			'notes_encrypted'    => wfv_pm_encrypt_raw( sanitize_textarea_field( $notes ), $key ),
			'tags'               => $tags,
			'color'              => $color,
			'starred'            => 0,
			'created_by'         => get_current_user_id(),
			'created_at'         => current_time( 'mysql' ),
			'updated_at'         => current_time( 'mysql' ),
		),
		array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
	);

	wfv_redirect_with_notice( 'success', 'Password saved.' );
}

function wfv_process_update_password() {
	check_admin_referer( 'wfv_update_password', 'wfv_pw_update_nonce' );
	global $wpdb;

	$id    = isset( $_POST['password_id'] ) ? absint( $_POST['password_id'] ) : 0;
	$entry = $id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . wfv_passwords_table() . " WHERE id = %d", $id ) ) : null;

	if ( ! wfv_user_can_manage_password( $entry ) ) {
		wfv_redirect_with_notice( 'error', 'You do not have permission to edit this entry.' );
	}

	$key = wfv_pm_active_key();
	if ( null === $key ) {
		wfv_redirect_with_notice( 'error', 'Your vault is locked — unlock it with your master password first.' );
	}

	$colors   = wfv_colors();
	$title    = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : $entry->title;
	$username = isset( $_POST['username'] ) ? sanitize_text_field( wp_unslash( $_POST['username'] ) ) : $entry->username;
	$url      = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : $entry->url;
	$tags     = isset( $_POST['tags'] ) ? wfv_parse_tags( wp_unslash( $_POST['tags'] ) ) : $entry->tags;
	$color    = isset( $_POST['color'] ) ? sanitize_key( $_POST['color'] ) : $entry->color;
	if ( ! array_key_exists( $color, $colors ) ) {
		$color = $entry->color;
	}

	// Password/notes: only re-encrypt if the field was actually submitted
	// (the edit modal fetches and re-populates the current value, so a
	// normal save always sends it; this guard just avoids wiping it out
	// if a future caller omits the field).
	$password_encrypted = $entry->password_encrypted;
	if ( isset( $_POST['password'] ) ) {
		$password_encrypted = wfv_pm_encrypt_raw( (string) wp_unslash( $_POST['password'] ), $key );
	}
	$notes_encrypted = $entry->notes_encrypted;
	if ( isset( $_POST['notes'] ) ) {
		$notes_encrypted = wfv_pm_encrypt_raw( sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ), $key );
	}

	$wpdb->update(
		wfv_passwords_table(),
		array(
			'title'              => $title,
			'username'           => $username,
			'password_encrypted' => $password_encrypted,
			'url'                => $url,
			'notes_encrypted'    => $notes_encrypted,
			'tags'               => $tags,
			'color'              => $color,
			'updated_at'         => current_time( 'mysql' ),
		),
		array( 'id' => $id ),
		array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
		array( '%d' )
	);

	wfv_redirect_with_notice( 'success', 'Password updated.' );
}

function wfv_process_delete_password() {
	check_admin_referer( 'wfv_delete_password', 'wfv_pw_delete_nonce' );
	global $wpdb;
	$id    = isset( $_POST['password_id'] ) ? absint( $_POST['password_id'] ) : 0;
	$entry = $id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . wfv_passwords_table() . " WHERE id = %d", $id ) ) : null;

	if ( ! wfv_user_can_manage_password( $entry ) ) {
		wfv_redirect_with_notice( 'error', 'You do not have permission to delete this entry.' );
	}
	$wpdb->delete( wfv_passwords_table(), array( 'id' => $id ), array( '%d' ) );
	wfv_redirect_with_notice( 'success', 'Password entry deleted.' );
}

function wfv_process_toggle_star_password() {
	check_admin_referer( 'wfv_toggle_star_password', 'wfv_pw_star_nonce' );
	global $wpdb;
	$id    = isset( $_POST['password_id'] ) ? absint( $_POST['password_id'] ) : 0;
	$entry = $id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . wfv_passwords_table() . " WHERE id = %d", $id ) ) : null;

	if ( ! wfv_user_can_manage_password( $entry ) ) {
		wfv_redirect_with_notice( 'error', 'You do not have permission to star this entry.' );
	}
	$wpdb->update( wfv_passwords_table(), array( 'starred' => $entry->starred ? 0 : 1 ), array( 'id' => $id ), array( '%d' ), array( '%d' ) );
	wfv_redirect_with_notice( 'success', $entry->starred ? 'Removed from starred.' : 'Added to starred.' );
}

function wfv_process_unlock_vault() {
	check_admin_referer( 'wfv_unlock_vault', 'wfv_unlock_nonce' );

	if ( ! wfv_pm_master_enabled() ) {
		wfv_redirect_with_notice( 'error', 'No master password is set up yet.' );
	}

	$attempt = isset( $_POST['master_password'] ) ? (string) wp_unslash( $_POST['master_password'] ) : '';
	$salt    = base64_decode( get_user_meta( get_current_user_id(), 'wfv_master_salt', true ) );
	$key     = wfv_pm_derive_key( $attempt, $salt );
	$verifier = get_user_meta( get_current_user_id(), 'wfv_master_verifier', true );

	if ( wfv_pm_decrypt_raw( $verifier, $key ) !== wfv_pm_verifier_marker() ) {
		wfv_redirect_with_notice( 'error', 'Incorrect master password.' );
	}

	wfv_pm_start_session( $key );
	wfv_redirect_with_notice( 'success', 'Vault unlocked.' );
}

function wfv_process_lock_vault() {
	check_admin_referer( 'wfv_lock_vault', 'wfv_lock_nonce' );
	wfv_pm_lock_vault();
	wfv_redirect_with_notice( 'success', 'Vault locked.' );
}

/**
 * ------------------------------------------------------------------
 * Password sharing
 *
 * Shared copies (to a WP user, or via a public link) are re-encrypted
 * with the site-wide key rather than the owner's personal master key,
 * because a share must remain readable by someone who does not know
 * the owner's master password. This is the same protection tier as
 * the plugin's default (no-master-password) encryption — genuinely
 * encrypted at rest, but one tier below a locked master-password
 * vault. Creating a share requires the owner's vault to be unlocked
 * (if they use a master password) since decrypting the source entry
 * is the first step.
 * ------------------------------------------------------------------
 */
function wfv_process_share_password_to_user() {
	check_admin_referer( 'wfv_share_password_to_user', 'wfv_share_user_nonce' );
	global $wpdb;

	$id     = isset( $_POST['password_id'] ) ? absint( $_POST['password_id'] ) : 0;
	$entry  = $id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . wfv_passwords_table() . " WHERE id = %d", $id ) ) : null;
	$target = isset( $_POST['target_user_id'] ) ? absint( $_POST['target_user_id'] ) : 0;

	if ( ! wfv_user_can_manage_password( $entry ) ) {
		wfv_redirect_with_notice( 'error', 'You do not have permission to share this entry.' );
	}
	if ( ! $target || ! get_userdata( $target ) || $target === get_current_user_id() ) {
		wfv_redirect_with_notice( 'error', 'Choose a valid person to share with.' );
	}

	$key = wfv_pm_active_key();
	if ( null === $key ) {
		wfv_redirect_with_notice( 'error', 'Your vault is locked — unlock it with your master password first.' );
	}

	$site_key = wfv_pm_key();
	$plain_pw    = wfv_pm_decrypt_raw( $entry->password_encrypted, $key );
	$plain_notes = wfv_pm_decrypt_raw( $entry->notes_encrypted, $key );

	$wpdb->insert(
		wfv_password_user_shares_table(),
		array(
			'source_password_id' => $entry->id,
			'title'               => $entry->title,
			'username'            => $entry->username,
			'password_encrypted'  => wfv_pm_encrypt_raw( $plain_pw, $site_key ),
			'url'                 => $entry->url,
			'notes_encrypted'     => wfv_pm_encrypt_raw( $plain_notes, $site_key ),
			'color'               => $entry->color,
			'shared_by'           => get_current_user_id(),
			'shared_with'         => $target,
			'revoked'             => 0,
			'created_at'          => current_time( 'mysql' ),
		),
		array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s' )
	);

	wfv_redirect_with_notice( 'success', 'Shared with ' . get_userdata( $target )->display_name . '.', 0, $id );
}

function wfv_process_revoke_user_share() {
	check_admin_referer( 'wfv_revoke_user_share', 'wfv_revoke_user_share_nonce' );
	global $wpdb;
	$share_id = isset( $_POST['share_id'] ) ? absint( $_POST['share_id'] ) : 0;
	$share    = $share_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . wfv_password_user_shares_table() . " WHERE id = %d", $share_id ) ) : null;

	if ( ! wfv_user_can_manage_user_share( $share ) ) {
		wfv_redirect_with_notice( 'error', 'You do not have permission to revoke this share.' );
	}
	$wpdb->update( wfv_password_user_shares_table(), array( 'revoked' => 1 ), array( 'id' => $share_id ), array( '%d' ), array( '%d' ) );
	wfv_redirect_with_notice( 'success', 'Share revoked.', 0, (int) $share->source_password_id );
}

function wfv_process_remove_shared_with_me() {
	check_admin_referer( 'wfv_remove_shared_with_me', 'wfv_remove_shared_nonce' );
	global $wpdb;
	$share_id = isset( $_POST['share_id'] ) ? absint( $_POST['share_id'] ) : 0;
	$share    = $share_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . wfv_password_user_shares_table() . " WHERE id = %d", $share_id ) ) : null;

	if ( ! wfv_user_is_share_recipient( $share ) ) {
		wfv_redirect_with_notice( 'error', 'You do not have permission to remove this share.' );
	}
	$wpdb->delete( wfv_password_user_shares_table(), array( 'id' => $share_id ), array( '%d' ) );
	wfv_redirect_with_notice( 'success', 'Removed from your shared passwords.' );
}

function wfv_process_create_password_link() {
	check_admin_referer( 'wfv_create_password_link', 'wfv_pwlink_nonce' );
	global $wpdb;

	$id    = isset( $_POST['password_id'] ) ? absint( $_POST['password_id'] ) : 0;
	$entry = $id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . wfv_passwords_table() . " WHERE id = %d", $id ) ) : null;

	if ( ! wfv_user_can_manage_password( $entry ) ) {
		wfv_redirect_with_notice( 'error', 'You do not have permission to share this entry.' );
	}

	$key = wfv_pm_active_key();
	if ( null === $key ) {
		wfv_redirect_with_notice( 'error', 'Your vault is locked — unlock it with your master password first.' );
	}

	$label         = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';
	$access_pw     = isset( $_POST['access_password'] ) ? (string) $_POST['access_password'] : '';
	$expiry_days   = isset( $_POST['expiry_days'] ) ? absint( $_POST['expiry_days'] ) : 0;
	$max_views     = isset( $_POST['max_views'] ) ? absint( $_POST['max_views'] ) : 0;

	$token = wfv_generate_unique_password_link_token();
	$expires_at = $expiry_days > 0 ? gmdate( 'Y-m-d H:i:s', time() + ( $expiry_days * DAY_IN_SECONDS ) ) : null;

	$site_key    = wfv_pm_key();
	$plain_pw    = wfv_pm_decrypt_raw( $entry->password_encrypted, $key );
	$plain_notes = wfv_pm_decrypt_raw( $entry->notes_encrypted, $key );

	$wpdb->insert(
		wfv_password_link_shares_table(),
		array(
			'source_password_id'   => $entry->id,
			'token'                 => $token,
			'label'                 => $label,
			'title'                 => $entry->title,
			'username'              => $entry->username,
			'password_encrypted'    => wfv_pm_encrypt_raw( $plain_pw, $site_key ),
			'url'                   => $entry->url,
			'notes_encrypted'       => wfv_pm_encrypt_raw( $plain_notes, $site_key ),
			'access_password_hash'  => '' !== $access_pw ? wp_hash_password( $access_pw ) : null,
			'expires_at'            => $expires_at,
			'max_views'             => $max_views > 0 ? $max_views : null,
			'view_count'            => 0,
			'revoked'               => 0,
			'created_by'            => get_current_user_id(),
			'created_at'            => current_time( 'mysql' ),
		),
		array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s' )
	);

	wfv_redirect_with_notice( 'success', 'Share link created.', 0, $id );
}

function wfv_process_revoke_password_link() {
	check_admin_referer( 'wfv_revoke_password_link', 'wfv_pwlink_revoke_nonce' );
	global $wpdb;
	$share_id = isset( $_POST['link_id'] ) ? absint( $_POST['link_id'] ) : 0;
	$share    = $share_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . wfv_password_link_shares_table() . " WHERE id = %d", $share_id ) ) : null;

	if ( ! wfv_user_can_manage_link_share( $share ) ) {
		wfv_redirect_with_notice( 'error', 'You do not have permission to revoke this link.' );
	}
	$wpdb->update( wfv_password_link_shares_table(), array( 'revoked' => 1 ), array( 'id' => $share_id ), array( '%d' ), array( '%d' ) );
	wfv_redirect_with_notice( 'success', 'Link revoked.', 0, (int) $share->source_password_id );
}

function wfv_generate_unique_password_link_token() {
	global $wpdb;
	do {
		$token  = bin2hex( random_bytes( 20 ) );
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM " . wfv_password_link_shares_table() . " WHERE token = %s", $token ) );
	} while ( $exists );
	return $token;
}

/**
 * On-demand decrypt, used by "Reveal", "Copy", and the edit modal.
 * Plaintext secrets are deliberately never embedded in the page source —
 * this is the only path that returns them, and only to the entry's owner.
 */
add_action( 'wp_ajax_wfv_get_password', 'wfv_ajax_get_password' );
function wfv_ajax_get_password() {
	if ( ! wfv_user_has_access() ) {
		wp_send_json_error( 'no_access', 403 );
	}
	check_ajax_referer( 'wfv_get_password', 'nonce' );

	global $wpdb;
	$id    = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
	$entry = $id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . wfv_passwords_table() . " WHERE id = %d", $id ) ) : null;

	if ( ! wfv_user_can_manage_password( $entry ) ) {
		wp_send_json_error( 'forbidden', 403 );
	}

	$key = wfv_pm_active_key();
	if ( null === $key ) {
		wp_send_json_error( 'locked', 423 );
	}

	wp_send_json_success(
		array(
			'password' => wfv_pm_decrypt_raw( $entry->password_encrypted, $key ),
			'notes'    => wfv_pm_decrypt_raw( $entry->notes_encrypted, $key ),
		)
	);
}

/** Metadata (no secrets) for the Share modal: who it's shared with, and any active links. Owner only. */
add_action( 'wp_ajax_wfv_get_password_shares', 'wfv_ajax_get_password_shares' );
function wfv_ajax_get_password_shares() {
	if ( ! wfv_user_has_access() ) {
		wp_send_json_error( 'no_access', 403 );
	}
	check_ajax_referer( 'wfv_get_password_shares', 'nonce' );

	global $wpdb;
	$id    = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
	$entry = $id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . wfv_passwords_table() . " WHERE id = %d", $id ) ) : null;

	if ( ! wfv_user_can_manage_password( $entry ) ) {
		wp_send_json_error( 'forbidden', 403 );
	}

	$user_shares_raw = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . wfv_password_user_shares_table() . " WHERE source_password_id = %d AND revoked = 0 ORDER BY created_at DESC", $id ) );
	$user_shares = array();
	foreach ( $user_shares_raw as $s ) {
		$u = get_userdata( $s->shared_with );
		$user_shares[] = array(
			'id'   => (int) $s->id,
			'name' => $u ? $u->display_name : 'Unknown user',
		);
	}

	$link_shares_raw = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . wfv_password_link_shares_table() . " WHERE source_password_id = %d ORDER BY created_at DESC", $id ) );
	$link_shares = array();
	foreach ( $link_shares_raw as $s ) {
		$status = 'active';
		if ( $s->revoked ) {
			$status = 'revoked';
		} elseif ( $s->expires_at && strtotime( $s->expires_at ) < time() ) {
			$status = 'expired';
		} elseif ( $s->max_views && $s->view_count >= $s->max_views ) {
			$status = 'limit';
		}
		$link_shares[] = array(
			'id'         => (int) $s->id,
			'label'      => $s->label,
			'url'        => add_query_arg( 'wfv_pwtoken', $s->token, home_url( '/' ) ),
			'has_password' => ! empty( $s->access_password_hash ),
			'expires'    => $s->expires_at ? mysql2date( 'Y-m-d', $s->expires_at ) : '',
			'views'      => (int) $s->view_count . ( $s->max_views ? ' / ' . (int) $s->max_views : '' ),
			'status'     => $status,
		);
	}

	wp_send_json_success(
		array(
			'user_shares' => $user_shares,
			'link_shares' => $link_shares,
		)
	);
}

/** Decrypt for a "shared with me" entry — site-key encrypted, so this works even if the recipient's own vault is locked. */
add_action( 'wp_ajax_wfv_get_shared_password', 'wfv_ajax_get_shared_password' );
function wfv_ajax_get_shared_password() {
	if ( ! wfv_user_has_access() ) {
		wp_send_json_error( 'no_access', 403 );
	}
	check_ajax_referer( 'wfv_get_shared_password', 'nonce' );

	global $wpdb;
	$id    = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
	$share = $id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . wfv_password_user_shares_table() . " WHERE id = %d", $id ) ) : null;

	if ( ! wfv_user_is_share_recipient( $share ) || $share->revoked ) {
		wp_send_json_error( 'forbidden', 403 );
	}

	wp_send_json_success(
		array(
			'password' => wfv_pm_decrypt_raw( $share->password_encrypted, wfv_pm_key() ),
			'notes'    => wfv_pm_decrypt_raw( $share->notes_encrypted, wfv_pm_key() ),
		)
	);
}

/**
 * Decrypt for a public link share. No login required — the unguessable
 * token IS the access control, plus an optional access password. Works
 * for anonymous visitors (nopriv) and logged-in ones alike.
 */
add_action( 'wp_ajax_nopriv_wfv_reveal_password_link', 'wfv_ajax_reveal_password_link' );
add_action( 'wp_ajax_wfv_reveal_password_link', 'wfv_ajax_reveal_password_link' );
function wfv_ajax_reveal_password_link() {
	global $wpdb;
	$token = isset( $_POST['token'] ) ? preg_replace( '/[^a-f0-9]/', '', sanitize_text_field( wp_unslash( $_POST['token'] ) ) ) : '';
	$share = $token ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . wfv_password_link_shares_table() . " WHERE token = %s", $token ) ) : null;

	if ( ! $share ) {
		wp_send_json_error( 'invalid', 404 );
	}
	if ( $share->revoked ) {
		wp_send_json_error( 'revoked', 410 );
	}
	if ( $share->expires_at && strtotime( $share->expires_at ) < time() ) {
		wp_send_json_error( 'expired', 410 );
	}
	if ( $share->max_views && (int) $share->view_count >= (int) $share->max_views ) {
		wp_send_json_error( 'limit', 410 );
	}
	if ( ! empty( $share->access_password_hash ) ) {
		$attempt = isset( $_POST['access_password'] ) ? (string) wp_unslash( $_POST['access_password'] ) : '';
		if ( '' === $attempt || ! wp_check_password( $attempt, $share->access_password_hash ) ) {
			wp_send_json_error( 'wrong_password', 403 );
		}
	}

	$wpdb->query( $wpdb->prepare( "UPDATE " . wfv_password_link_shares_table() . " SET view_count = view_count + 1 WHERE id = %d", $share->id ) );

	wp_send_json_success(
		array(
			'title'    => $share->title,
			'username' => $share->username,
			'url'      => $share->url,
			'password' => wfv_pm_decrypt_raw( $share->password_encrypted, wfv_pm_key() ),
			'notes'    => wfv_pm_decrypt_raw( $share->notes_encrypted, wfv_pm_key() ),
		)
	);
}
