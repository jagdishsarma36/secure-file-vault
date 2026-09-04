<?php
/**
 * Access control functions for Secure File Vault.
 *
 * Determines whether the current user may view, manage, or
 * interact with vault resources such as files, folders, notes,
 * sticky notes, passwords, and shares.
 */

/**
 * Whether the current user is allowed to use the vault at all. Defaults to
 * every logged-in role. A site owner can narrow this with:
 *   add_filter( 'wfv_allowed_roles', function() { return array( 'administrator', 'editor' ); } );
 */
function wfv_user_has_access() {
	if ( ! is_user_logged_in() ) {
		return false;
	}
	if ( current_user_can( 'manage_options' ) ) {
		return true; // admins always have access, and can see every user's files
	}
	$allowed_roles = apply_filters( 'wfv_allowed_roles', array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' ) );
	$user          = wp_get_current_user();
	return (bool) array_intersect( $allowed_roles, (array) $user->roles );
}

/** True if the current user may manage this file: owner, or a site admin. */
function wfv_user_can_manage_file( $file ) {
	if ( ! $file ) {
		return false;
	}
	if ( current_user_can( 'manage_options' ) ) {
		return true;
	}
	return is_user_logged_in() && (int) $file->uploaded_by === get_current_user_id();
}

/** True if the current user may manage this folder: owner, or a site admin. */
function wfv_user_can_manage_folder( $folder ) {
	if ( ! $folder ) {
		return false;
	}
	if ( current_user_can( 'manage_options' ) ) {
		return true;
	}
	return is_user_logged_in() && (int) $folder->created_by === get_current_user_id();
}

/** True if the current user created this folder share link (can revoke it). */
function wfv_user_can_manage_folder_share( $share ) {
	if ( ! $share ) {
		return false;
	}
	return is_user_logged_in() && (int) $share->created_by === get_current_user_id();
}

/** True if the current user is the recipient of this internal folder share. */
function wfv_user_is_folder_share_recipient( $share ) {
	if ( ! $share ) {
		return false;
	}
	return is_user_logged_in() && (int) $share->shared_with === get_current_user_id();
}

/**
 * Walk up a folder's ancestry and return the first active user-share that
 * was granted to $user_id (so a recipient can access any descendant). Returns
 * the share row (object) or null.
 */
function wfv_folder_share_for_user( $folder_id, $user_id = 0 ) {
	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}
	global $wpdb;
	$seen = array();
	$cur  = $folder_id;
	while ( $cur && ! isset( $seen[ (int) $cur ] ) ) {
		$seen[ (int) $cur ] = true;
		$share = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM " . wfv_folder_user_shares_table() . " WHERE folder_id = %d AND shared_with = %d AND revoked = 0",
				$cur,
				$user_id
			)
		);
		if ( $share ) {
			return $share;
		}
		$folder = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . wfv_folders_table() . " WHERE id = %d", $cur ) );
		$cur    = $folder && $folder->parent_id ? (int) $folder->parent_id : 0;
	}
	return null;
}

/**
 * True if the current user may read (browse + download) the given file, either
 * because they own it, are an admin, or the file lives inside a folder that has
 * been shared with them.
 */
function wfv_user_can_read_file( $file ) {
	if ( ! $file || ! is_user_logged_in() ) {
		return false;
	}
	$user_id = get_current_user_id();
	if ( current_user_can( 'manage_options' ) || (int) $file->uploaded_by === $user_id ) {
		return true;
	}
	return (bool) wfv_folder_share_for_user( $file->folder_id, $user_id );
}

/** True if the current user may manage this note: owner, or a site admin. */
function wfv_user_can_manage_note( $note ) {
	if ( ! $note ) {
		return false;
	}
	if ( current_user_can( 'manage_options' ) ) {
		return true;
	}
	return is_user_logged_in() && (int) $note->created_by === get_current_user_id();
}

/**
 * True if the current user may manage this sticky note. No admin bypass —
 * these are quick personal notes, strictly private to whoever created them.
 */
function wfv_user_can_manage_sticky_note( $note ) {
	if ( ! $note ) {
		return false;
	}
	return is_user_logged_in() && (int) $note->created_by === get_current_user_id();
}

/**
 * True if the current user may manage this password entry. Deliberately
 * has NO admin bypass (unlike files/notes) — these are secrets, so even a
 * site admin cannot read, edit, or delete another user's entries.
 */
function wfv_user_can_manage_password( $entry ) {
	if ( ! $entry ) {
		return false;
	}
	return is_user_logged_in() && (int) $entry->created_by === get_current_user_id();
}

/** True if the current user is the sender of this internal password share (can revoke it). */
function wfv_user_can_manage_user_share( $share ) {
	if ( ! $share ) {
		return false;
	}
	return is_user_logged_in() && (int) $share->shared_by === get_current_user_id();
}

/** True if the current user is the recipient of this internal password share (can view/remove it). */
function wfv_user_is_share_recipient( $share ) {
	if ( ! $share ) {
		return false;
	}
	return is_user_logged_in() && (int) $share->shared_with === get_current_user_id();
}

/** True if the current user created this public password link (can revoke it). */
function wfv_user_can_manage_link_share( $share ) {
	if ( ! $share ) {
		return false;
	}
	return is_user_logged_in() && (int) $share->created_by === get_current_user_id();
}
