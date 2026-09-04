<?php
/**
 * Admin menu registration and asset enqueueing for Secure File Vault.
 *
 * @package SecureFileVault
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the top-level menu and submenu pages.
 */
add_action( 'admin_menu', 'wfv_admin_menu' );
function wfv_admin_menu() {
	add_menu_page(
		'Secure Vault',
		'Secure Vault',
		'read',
		'wfv-vault',
		'wfv_render_admin_page',
		'dashicons-shield',
		26
	);
	add_submenu_page(
		'wfv-vault',
		'Files',
		'📁 Files',
		'read',
		'wfv-vault',
		'wfv_render_admin_page'
	);
	add_submenu_page(
		'wfv-vault',
		'My Notes',
		'📝 Notes',
		'read',
		'wfv-notes',
		'wfv_render_notes_page'
	);
	add_submenu_page(
		'wfv-vault',
		'Passwords',
		'🔑 Passwords',
		'read',
		'wfv-passwords',
		'wfv_render_passwords_page'
	);
}

/**
 * Enqueue the WordPress editor (TinyMCE + Quicktags) on the Notes page only.
 */
add_action( 'admin_enqueue_scripts', 'wfv_maybe_enqueue_editor' );
function wfv_maybe_enqueue_editor() {
	if ( isset( $_GET['page'] ) && 'wfv-notes' === $_GET['page'] ) {
		wp_enqueue_editor();
	}
}

/**
 * Enqueue admin stylesheets for Secure File Vault pages.
 */
add_action( 'admin_enqueue_scripts', 'wfv_enqueue_admin_styles' );
function wfv_enqueue_admin_styles( $hook ) {
	$slugs = array( 'toplevel_page_wfv-vault', 'secure-vault_page_wfv-notes', 'secure-vault_page_wfv-passwords' );
	if ( ! in_array( $hook, $slugs, true ) ) {
		return;
	}

	wp_enqueue_style(
		'wfv-design-system',
		WFV_URL . 'assets/css/design-system.css',
		array(),
		WFV_VERSION
	);

	if ( 'toplevel_page_wfv-vault' === $hook ) {
		wp_enqueue_style(
			'wfv-admin-files',
			WFV_URL . 'assets/css/admin-files.css',
			array( 'wfv-design-system' ),
			WFV_VERSION
		);
	}

	if ( 'secure-vault_page_wfv-notes' === $hook ) {
		wp_enqueue_style(
			'wfv-admin-notes',
			WFV_URL . 'assets/css/admin-notes.css',
			array( 'wfv-design-system' ),
			WFV_VERSION
		);
	}

	if ( 'secure-vault_page_wfv-passwords' === $hook ) {
		wp_enqueue_style(
			'wfv-admin-passwords',
			WFV_URL . 'assets/css/admin-passwords.css',
			array( 'wfv-design-system' ),
			WFV_VERSION
		);
	}
}

/**
 * Build the notes_data map for the Notes admin page JS (id => metadata needed
 * to instantiate the detail pane without another round-trip). Rich HTML content
 * is included safely here because wp_localize_script JSON-encodes it, avoiding
 * the </script> breakage that inline echoing could cause.
 */
function wfv_notes_js_data() {
	global $wpdb;
	$user_id = get_current_user_id();
	$data    = array();
	$notes   = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . wfv_notes_table() . " WHERE created_by = %d ORDER BY pinned DESC, sort_order ASC, id ASC", $user_id ) );
	foreach ( $notes as $n ) {
		$data[ (int) $n->id ] = array(
			'title'   => (string) $n->title,
			'content' => (string) $n->content,
			'tags'    => (string) $n->tags,
			'color'   => (string) $n->color,
			'pinned'  => (int) $n->pinned,
			'updated' => mysql2date( 'M j, Y', $n->updated_at ),
		);
	}
	return $data;
}

/**
 * Build the entry_data and shared_data maps for the Passwords admin page JS.
 * Only non-secret metadata is bundled; the actual password is fetched on demand
 * over AJAX.
 */
function wfv_passwords_js_data() {
	global $wpdb;
	$user_id = get_current_user_id();

	$entries = array();
	$my = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . wfv_passwords_table() . " WHERE created_by = %d ORDER BY starred DESC, title ASC", $user_id ) );
	foreach ( $my as $e ) {
		$entries[ (int) $e->id ] = array(
			'title'    => (string) $e->title,
			'username' => (string) $e->username,
			'url'      => (string) $e->url,
			'tags'     => (string) $e->tags,
			'color'    => (string) $e->color,
			'starred'  => (int) $e->starred,
			'updated'  => mysql2date( 'M j, Y', $e->updated_at ),
		);
	}

	$shared = array();
	$shares = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . wfv_password_user_shares_table() . " WHERE shared_with = %d AND revoked = 0 ORDER BY created_at DESC", $user_id ) );
	foreach ( $shares as $s ) {
		$sharer                 = get_userdata( $s->shared_by );
		$shared[ (int) $s->id ] = array(
			'title'     => (string) $s->title,
			'username'  => (string) $s->username,
			'url'       => (string) $s->url,
			'color'     => (string) $s->color,
			'shared_by' => $sharer ? $sharer->display_name : 'Unknown',
		);
	}

	return array( 'entries' => $entries, 'shared' => $shared );
}

/**
 * Enqueue admin JavaScripts for Secure File Vault pages.
 */
add_action( 'admin_enqueue_scripts', 'wfv_enqueue_admin_scripts' );
function wfv_enqueue_admin_scripts( $hook ) {
	$slugs = array( 'toplevel_page_wfv-vault', 'secure-vault_page_wfv-notes', 'secure-vault_page_wfv-passwords' );
	if ( ! in_array( $hook, $slugs, true ) ) {
		return;
	}

	if ( 'toplevel_page_wfv-vault' === $hook ) {
		wp_enqueue_script(
			'wfv-admin-files',
			WFV_URL . 'assets/js/admin-files.js',
			array( 'jquery' ),
			WFV_VERSION,
			true
		);
		wp_localize_script(
			'wfv-admin-files',
			'wfvFilesData',
			array(
				'ajaxurl'    => admin_url( 'admin-ajax.php' ),
				'uploadNonce' => wp_create_nonce( 'wfv_upload' ),
			)
		);
	}

	if ( 'secure-vault_page_wfv-notes' === $hook ) {
		wp_enqueue_script(
			'wfv-admin-notes',
			WFV_URL . 'assets/js/admin-notes.js',
			array( 'jquery' ),
			WFV_VERSION,
			true
		);
		wp_localize_script(
			'wfv-admin-notes',
			'wfvAdminNotes',
			array(
				'ajaxurl'      => admin_url( 'admin-ajax.php' ),
				'reorder_nonce' => wp_create_nonce( 'wfv_reorder_notes' ),
				'colors'       => wfv_colors(),
				'notes_data'   => wfv_notes_js_data(),
			)
		);
	}

	if ( 'secure-vault_page_wfv-passwords' === $hook ) {
		$pw_js = wfv_passwords_js_data();
		wp_enqueue_script(
			'wfv-admin-passwords',
			WFV_URL . 'assets/js/admin-passwords.js',
			array( 'jquery' ),
			WFV_VERSION,
			true
		);
		wp_localize_script(
			'wfv-admin-passwords',
			'wfvAdminPasswords',
			array(
				'ajaxurl'               => admin_url( 'admin-ajax.php' ),
				'get_pw_nonce'          => wp_create_nonce( 'wfv_get_password' ),
				'get_shares_nonce'      => wp_create_nonce( 'wfv_get_password_shares' ),
				'get_shared_nonce'      => wp_create_nonce( 'wfv_get_shared_password' ),
				'share_user_nonce'      => wp_create_nonce( 'wfv_share_password_to_user' ),
				'revoke_user_nonce'     => wp_create_nonce( 'wfv_revoke_user_share' ),
				'remove_shared_nonce'   => wp_create_nonce( 'wfv_remove_shared_with_me' ),
				'create_link_nonce'     => wp_create_nonce( 'wfv_create_password_link' ),
				'pwlink_revoke_nonce'   => wp_create_nonce( 'wfv_revoke_password_link' ),
				'colors'                => wfv_colors(),
				'entry_data'            => $pw_js['entries'],
				'shared_data'           => $pw_js['shared'],
				'open_password_id'      => isset( $_GET['wfv_open_password'] ) ? absint( $_GET['wfv_open_password'] ) : 0,
			)
		);
	}
}
