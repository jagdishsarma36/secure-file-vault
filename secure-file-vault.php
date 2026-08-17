<?php
/**
 * Plugin Name: Secure File Vault
 * Plugin URI: https://github.com/jagdishsarma36/secure-file-vault
 * Description: Private file storage inside WordPress with Drive-style folders, colors, starring, and per-recipient share links, a LastPass-style Notes and Password Manager (searchable sidebar + detail pane, full-width rich-text editing, master-password vault lock, and sharing to other WP users or via public links) — all under one unified "Secure Vault" menu with a shared modern design system.
 * Version: 2.1.0
 * Author: Jagdish Sarma
 * Author URI: https://github.com/jagdishsarma36
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wfv
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Update URI: https://github.com/jagdishsarma36/secure-file-vault
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WFV_VERSION', '2.1.0' );
define( 'WFV_PRIVATE_DIRNAME', 'wfv-private' );
define( 'WFV_FILE', __FILE__ );
define( 'WFV_DIR', plugin_dir_path( __FILE__ ) );

// GitHub auto-updates (checks https://github.com/jagdishsarma36/secure-file-vault for new releases).
// To disable: comment out the line below, or simply delete updater.php.
require_once WFV_DIR . 'updater.php';

/**
 * ------------------------------------------------------------------
 * Schema (activation for fresh installs + versioned upgrade for
 * existing installs that just replace the plugin files)
 * ------------------------------------------------------------------
 */
register_activation_hook( WFV_FILE, 'wfv_activate' );
function wfv_activate() {
	wfv_install_schema();
	wfv_prepare_private_dir();
	update_option( 'wfv_db_version', WFV_VERSION );
}

add_action( 'plugins_loaded', 'wfv_maybe_upgrade' );
function wfv_maybe_upgrade() {
	if ( get_option( 'wfv_db_version' ) !== WFV_VERSION ) {
		wfv_install_schema();
		wfv_prepare_private_dir();
		update_option( 'wfv_db_version', WFV_VERSION );
	}
}

function wfv_install_schema() {
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();

	$files_table   = $wpdb->prefix . 'wfv_files';
	$shares_table  = $wpdb->prefix . 'wfv_shares';
	$folders_table         = $wpdb->prefix . 'wfv_folders';
	$notes_table           = $wpdb->prefix . 'wfv_notes';
	$passwords_table       = $wpdb->prefix . 'wfv_passwords';
	$pw_user_shares_table  = $wpdb->prefix . 'wfv_password_user_shares';
	$pw_link_shares_table  = $wpdb->prefix . 'wfv_password_link_shares';

	$sql = "CREATE TABLE {$files_table} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		original_name VARCHAR(255) NOT NULL,
		stored_name VARCHAR(255) NOT NULL,
		mime_type VARCHAR(100) NOT NULL,
		file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
		uploaded_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
		folder_id BIGINT UNSIGNED DEFAULT NULL,
		starred TINYINT(1) NOT NULL DEFAULT 0,
		created_at DATETIME NOT NULL,
		PRIMARY KEY  (id),
		KEY folder_id (folder_id)
	) {$charset_collate};

	CREATE TABLE {$shares_table} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		file_id BIGINT UNSIGNED NOT NULL,
		token VARCHAR(64) NOT NULL,
		label VARCHAR(191) DEFAULT NULL,
		password_hash VARCHAR(255) DEFAULT NULL,
		expires_at DATETIME DEFAULT NULL,
		max_downloads INT UNSIGNED DEFAULT NULL,
		download_count INT UNSIGNED NOT NULL DEFAULT 0,
		revoked TINYINT(1) NOT NULL DEFAULT 0,
		created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
		created_at DATETIME NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY token (token),
		KEY file_id (file_id)
	) {$charset_collate};

	CREATE TABLE {$folders_table} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		name VARCHAR(191) NOT NULL,
		color VARCHAR(20) NOT NULL DEFAULT 'blue',
		parent_id BIGINT UNSIGNED DEFAULT NULL,
		starred TINYINT(1) NOT NULL DEFAULT 0,
		created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
		created_at DATETIME NOT NULL,
		PRIMARY KEY  (id),
		KEY parent_id (parent_id)
	) {$charset_collate};

	CREATE TABLE {$notes_table} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		title VARCHAR(191) DEFAULT NULL,
		content TEXT,
		color VARCHAR(20) NOT NULL DEFAULT 'yellow',
		tags VARCHAR(255) NOT NULL DEFAULT '',
		pinned TINYINT(1) NOT NULL DEFAULT 0,
		sort_order INT NOT NULL DEFAULT 0,
		created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
		created_at DATETIME NOT NULL,
		updated_at DATETIME NOT NULL,
		PRIMARY KEY  (id),
		KEY created_by (created_by)
	) {$charset_collate};

	CREATE TABLE {$passwords_table} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		title VARCHAR(191) NOT NULL,
		username VARCHAR(191) DEFAULT '',
		password_encrypted TEXT,
		url VARCHAR(500) DEFAULT '',
		notes_encrypted TEXT,
		tags VARCHAR(255) NOT NULL DEFAULT '',
		color VARCHAR(20) NOT NULL DEFAULT 'gray',
		starred TINYINT(1) NOT NULL DEFAULT 0,
		created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
		created_at DATETIME NOT NULL,
		updated_at DATETIME NOT NULL,
		PRIMARY KEY  (id),
		KEY created_by (created_by)
	) {$charset_collate};

	CREATE TABLE {$pw_user_shares_table} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		source_password_id BIGINT UNSIGNED DEFAULT NULL,
		title VARCHAR(191) NOT NULL,
		username VARCHAR(191) DEFAULT '',
		password_encrypted TEXT,
		url VARCHAR(500) DEFAULT '',
		notes_encrypted TEXT,
		color VARCHAR(20) NOT NULL DEFAULT 'gray',
		shared_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
		shared_with BIGINT UNSIGNED NOT NULL DEFAULT 0,
		revoked TINYINT(1) NOT NULL DEFAULT 0,
		created_at DATETIME NOT NULL,
		PRIMARY KEY  (id),
		KEY shared_with (shared_with),
		KEY shared_by (shared_by)
	) {$charset_collate};

	CREATE TABLE {$pw_link_shares_table} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		source_password_id BIGINT UNSIGNED DEFAULT NULL,
		token VARCHAR(64) NOT NULL,
		label VARCHAR(191) DEFAULT NULL,
		title VARCHAR(191) NOT NULL,
		username VARCHAR(191) DEFAULT '',
		password_encrypted TEXT,
		url VARCHAR(500) DEFAULT '',
		notes_encrypted TEXT,
		access_password_hash VARCHAR(255) DEFAULT NULL,
		expires_at DATETIME DEFAULT NULL,
		max_views INT UNSIGNED DEFAULT NULL,
		view_count INT UNSIGNED NOT NULL DEFAULT 0,
		revoked TINYINT(1) NOT NULL DEFAULT 0,
		created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
		created_at DATETIME NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY token (token),
		KEY created_by (created_by)
	) {$charset_collate};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}

register_deactivation_hook( WFV_FILE, 'wfv_deactivate' );
function wfv_deactivate() {
	// Intentionally left blank. Files, DB tables, folders and shares are
	// kept so nothing breaks if the plugin is re-activated later.
}

/**
 * Creates the private upload directory (outside normal media library
 * browsing) and locks it down at the webserver level as a defense-in-depth
 * measure. Actual access control happens in PHP via the token check in
 * wfv_handle_download(), so this directory does not need to be guessed —
 * even without the .htaccess rule below, filenames inside it are randomized
 * and unlisted.
 */
function wfv_prepare_private_dir() {
	$dir = wfv_private_dir_path();
	if ( ! file_exists( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	// Apache: block all direct access. (Nginx installs should add an
	// equivalent "location" block denying /wp-content/uploads/wfv-private/.)
	$htaccess = $dir . '/.htaccess';
	if ( ! file_exists( $htaccess ) ) {
		file_put_contents( $htaccess, "Order deny,allow\nDeny from all\n" );
	}
	$index = $dir . '/index.html';
	if ( ! file_exists( $index ) ) {
		file_put_contents( $index, '' );
	}
}

function wfv_private_dir_path() {
	$uploads = wp_upload_dir();
	return trailingslashit( $uploads['basedir'] ) . WFV_PRIVATE_DIRNAME;
}

/**
 * ------------------------------------------------------------------
 * DB helpers
 * ------------------------------------------------------------------
 */
function wfv_files_table() {
	global $wpdb;
	return $wpdb->prefix . 'wfv_files';
}
function wfv_shares_table() {
	global $wpdb;
	return $wpdb->prefix . 'wfv_shares';
}
function wfv_folders_table() {
	global $wpdb;
	return $wpdb->prefix . 'wfv_folders';
}
function wfv_notes_table() {
	global $wpdb;
	return $wpdb->prefix . 'wfv_notes';
}
function wfv_passwords_table() {
	global $wpdb;
	return $wpdb->prefix . 'wfv_passwords';
}
function wfv_password_user_shares_table() {
	global $wpdb;
	return $wpdb->prefix . 'wfv_password_user_shares';
}
function wfv_password_link_shares_table() {
	global $wpdb;
	return $wpdb->prefix . 'wfv_password_link_shares';
}

/**
 * ------------------------------------------------------------------
 * Password manager encryption
 *
 * Secrets are encrypted with AES-256-CBC using a key derived from
 * WordPress's own AUTH_KEY / SECURE_AUTH_SALT constants (which live
 * in wp-config.php, not the database). This means a database-only
 * leak (backup, SQL injection, etc.) does not expose plaintext
 * passwords on its own — the attacker would also need wp-config.php.
 * It is not a zero-knowledge system: anyone with full server access
 * (both the DB and wp-config.php) could still decrypt, same as any
 * server-side password manager without a separate master password.
 * ------------------------------------------------------------------
 */
/**
 * ------------------------------------------------------------------
 * Password manager encryption
 *
 * By default, secrets are encrypted with AES-256-CBC using a key
 * derived from WordPress's own AUTH_KEY / SECURE_AUTH_SALT constants
 * (which live in wp-config.php, not the database). This means a
 * database-only leak (backup, SQL injection, etc.) does not expose
 * plaintext passwords on its own.
 *
 * A user can optionally set a MASTER PASSWORD (from their profile
 * page). Once set, their vault is re-encrypted with a key derived
 * from that master password instead — a key that is never stored
 * anywhere. The vault must then be "unlocked" each session by
 * entering the master password; the derived key is held only in a
 * short-lived server-side cache (tied to an httponly cookie) while
 * unlocked, and forgotten again on lock/expiry. Anthropic-style
 * honesty: this is stronger than the site-key default (nothing can
 * be decrypted without the master password, even with DB + wp-config
 * access, once the unlock session ends) but is still not a
 * zero-knowledge system while the vault is actively unlocked.
 * ------------------------------------------------------------------
 */
function wfv_pm_key() {
	$material  = defined( 'AUTH_KEY' ) && AUTH_KEY ? AUTH_KEY : 'wfv-fallback-' . DB_NAME;
	$material .= defined( 'SECURE_AUTH_SALT' ) && SECURE_AUTH_SALT ? SECURE_AUTH_SALT : '';
	$material .= defined( 'SECURE_AUTH_KEY' ) && SECURE_AUTH_KEY ? SECURE_AUTH_KEY : '';
	return hash( 'sha256', $material, true ); // 32 raw bytes for AES-256
}

function wfv_pm_encrypt_raw( $plaintext, $key ) {
	if ( '' === (string) $plaintext ) {
		return '';
	}
	if ( ! function_exists( 'openssl_encrypt' ) ) {
		return 'plain:' . base64_encode( $plaintext ); // extremely unlikely fallback; openssl ships with PHP by default
	}
	$iv         = openssl_random_pseudo_bytes( 16 );
	$ciphertext = openssl_encrypt( $plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
	if ( false === $ciphertext ) {
		return '';
	}
	return base64_encode( $iv . $ciphertext );
}

function wfv_pm_decrypt_raw( $encoded, $key ) {
	$encoded = (string) $encoded;
	if ( '' === $encoded ) {
		return '';
	}
	if ( 0 === strpos( $encoded, 'plain:' ) ) {
		return base64_decode( substr( $encoded, 6 ) );
	}
	if ( ! function_exists( 'openssl_decrypt' ) ) {
		return '';
	}
	$raw = base64_decode( $encoded );
	if ( false === $raw || strlen( $raw ) < 17 ) {
		return '';
	}
	$iv         = substr( $raw, 0, 16 );
	$ciphertext = substr( $raw, 16 );
	$plaintext  = openssl_decrypt( $ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
	return false === $plaintext ? '' : $plaintext;
}

// Legacy/no-master-password convenience wrappers (site-key based).
function wfv_pm_encrypt( $plaintext ) {
	return wfv_pm_encrypt_raw( $plaintext, wfv_pm_key() );
}
function wfv_pm_decrypt( $encoded ) {
	return wfv_pm_decrypt_raw( $encoded, wfv_pm_key() );
}

/** The fixed string used to verify a master password attempt without ever storing the password itself. */
function wfv_pm_verifier_marker() {
	return 'WFV-VAULT-OK';
}

function wfv_pm_derive_key( $master_password, $salt_raw ) {
	return hash_pbkdf2( 'sha256', (string) $master_password, $salt_raw, 100000, 32, true );
}

function wfv_pm_master_enabled( $user_id = 0 ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	return '1' === get_user_meta( $user_id, 'wfv_master_enabled', true );
}

/** The active encryption key for the current user: their unlocked master key if they have one, otherwise the site-wide key. Returns null if a master password is set but the vault is currently locked. */
function wfv_pm_active_key() {
	if ( ! wfv_pm_master_enabled() ) {
		return wfv_pm_key();
	}
	return wfv_pm_get_session_key();
}

function wfv_pm_cookie_name() {
	return 'wfv_vault_unlock_' . COOKIEHASH;
}

function wfv_pm_start_session( $key ) {
	$token = bin2hex( random_bytes( 32 ) );
	set_transient( 'wfv_vlk_' . $token, base64_encode( $key ) . '|' . get_current_user_id(), 20 * MINUTE_IN_SECONDS );
	setcookie( wfv_pm_cookie_name(), $token, time() + ( 20 * MINUTE_IN_SECONDS ), COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
	$_COOKIE[ wfv_pm_cookie_name() ] = $token; // so it's usable immediately within this same request
}

function wfv_pm_get_session_key() {
	if ( empty( $_COOKIE[ wfv_pm_cookie_name() ] ) ) {
		return null;
	}
	$token = sanitize_text_field( wp_unslash( $_COOKIE[ wfv_pm_cookie_name() ] ) );
	$value = get_transient( 'wfv_vlk_' . $token );
	if ( ! $value || false === strpos( $value, '|' ) ) {
		return null;
	}
	list( $b64key, $uid ) = explode( '|', $value, 2 );
	if ( (int) $uid !== get_current_user_id() ) {
		return null;
	}
	set_transient( 'wfv_vlk_' . $token, $value, 20 * MINUTE_IN_SECONDS ); // sliding expiry while active
	return base64_decode( $b64key );
}

function wfv_pm_lock_vault() {
	if ( ! empty( $_COOKIE[ wfv_pm_cookie_name() ] ) ) {
		$token = sanitize_text_field( wp_unslash( $_COOKIE[ wfv_pm_cookie_name() ] ) );
		delete_transient( 'wfv_vlk_' . $token );
		setcookie( wfv_pm_cookie_name(), '', time() - HOUR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
		unset( $_COOKIE[ wfv_pm_cookie_name() ] );
	}
}

/** Re-encrypts every password entry belonging to $user_id from $old_key to $new_key. */
function wfv_pm_reencrypt_vault( $user_id, $old_key, $new_key ) {
	global $wpdb;
	$entries = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . wfv_passwords_table() . " WHERE created_by = %d", $user_id ) );
	foreach ( $entries as $e ) {
		$plain_password = wfv_pm_decrypt_raw( $e->password_encrypted, $old_key );
		$plain_notes     = wfv_pm_decrypt_raw( $e->notes_encrypted, $old_key );
		$wpdb->update(
			wfv_passwords_table(),
			array(
				'password_encrypted' => wfv_pm_encrypt_raw( $plain_password, $new_key ),
				'notes_encrypted'    => wfv_pm_encrypt_raw( $plain_notes, $new_key ),
			),
			array( 'id' => $e->id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}
}

/**
 * Drive-style folder color palette.
 */
function wfv_colors() {
	return array(
		'blue'   => '#4285F4',
		'green'  => '#33B679',
		'red'    => '#E53935',
		'yellow' => '#F4B400',
		'orange' => '#FF8F00',
		'purple' => '#8E24AA',
		'pink'   => '#D81B60',
		'teal'   => '#00897B',
		'gray'   => '#757575',
		'brown'  => '#6D4C41',
	);
}

/** Softer pastel tints of the same palette, used for note card backgrounds. */
function wfv_note_bg_color( $color_key ) {
	$tints = array(
		'blue'   => '#E8F0FE',
		'green'  => '#E6F4EA',
		'red'    => '#FCE8E6',
		'yellow' => '#FEF7E0',
		'orange' => '#FFF3E0',
		'purple' => '#F3E8FD',
		'pink'   => '#FCE4EC',
		'teal'   => '#E0F2F1',
		'gray'   => '#F1F3F4',
		'brown'  => '#EFEBE9',
	);
	return isset( $tints[ $color_key ] ) ? $tints[ $color_key ] : $tints['yellow'];
}

function wfv_folder_icon_svg( $color_key, $size = 40 ) {
	$colors = wfv_colors();
	$hex    = isset( $colors[ $color_key ] ) ? $colors[ $color_key ] : $colors['blue'];
	return sprintf(
		'<svg viewBox="0 0 24 24" width="%1$d" height="%1$d" fill="%2$s" aria-hidden="true"><path d="M10 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>',
		(int) $size,
		esc_attr( $hex )
	);
}

/**
 * ------------------------------------------------------------------
 * Shared design system: CSS custom properties + light global polish,
 * reused by all three sub-pages (Files, Notes, Passwords) so they
 * share one consistent, modern visual language. Call once near the
 * top of each page's markup.
 * ------------------------------------------------------------------
 */
function wfv_design_system_css() {
	?>
	<style>
		.wfv-app{
			--wfv-primary:#4f46e5;
			--wfv-primary-dark:#3730a3;
			--wfv-primary-light:#eef2ff;
			--wfv-ink:#0f172a;
			--wfv-slate:#475569;
			--wfv-muted:#94a3b8;
			--wfv-border:#e2e8f0;
			--wfv-bg:#f8fafc;
			--wfv-radius:12px;
			--wfv-radius-sm:8px;
			--wfv-shadow:0 1px 2px rgba(15,23,42,.04), 0 1px 3px rgba(15,23,42,.03);
			--wfv-shadow-lg:0 1px 3px rgba(15,23,42,.06), 0 12px 32px rgba(15,23,42,.08);
			font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
			-webkit-font-smoothing:antialiased;
		}
		.wfv-app h1{ letter-spacing:-.01em; }
		.wfv-app .wfv-card, .wfv-app .wfv-stat-card{ box-shadow:var(--wfv-shadow) !important; border-color:var(--wfv-border) !important; border-radius:var(--wfv-radius) !important; }
		.wfv-app table.wfv-table{ border-radius:var(--wfv-radius) !important; border-color:var(--wfv-border) !important; box-shadow:var(--wfv-shadow); }
		.wfv-app .button-primary,
		.wfv-app button.button-primary,
		.wfv-app input[type=submit].button-primary{
			background:var(--wfv-primary) !important;
			border-color:var(--wfv-primary) !important;
			box-shadow:none !important;
			text-shadow:none !important;
			border-radius:var(--wfv-radius-sm) !important;
			font-weight:600 !important;
		}
		.wfv-app .button-primary:hover,
		.wfv-app button.button-primary:hover{
			background:var(--wfv-primary-dark) !important;
			border-color:var(--wfv-primary-dark) !important;
		}
		.wfv-app .button{ border-radius:var(--wfv-radius-sm) !important; }
		.wfv-app input[type=text],
		.wfv-app input[type=password],
		.wfv-app input[type=number],
		.wfv-app input[type=url],
		.wfv-app textarea,
		.wfv-app select{
			border-radius:var(--wfv-radius-sm) !important;
			border-color:var(--wfv-border) !important;
		}
		.wfv-app input[type=text]:focus,
		.wfv-app input[type=password]:focus,
		.wfv-app textarea:focus,
		.wfv-app select:focus{
			border-color:var(--wfv-primary) !important;
			box-shadow:0 0 0 2px var(--wfv-primary-light) !important;
		}

		/* Top navigation */
		.wfv-topnav{ display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:14px; margin:0 0 22px; padding-bottom:16px; border-bottom:1px solid var(--wfv-border); }
		.wfv-topnav-brand{ display:flex; align-items:center; gap:10px; font-weight:700; font-size:16px; color:var(--wfv-ink); }
		.wfv-topnav-brand .wfv-brand-icon{ width:32px; height:32px; border-radius:9px; background:linear-gradient(135deg,var(--wfv-primary),var(--wfv-primary-dark)); display:flex; align-items:center; justify-content:center; font-size:15px; box-shadow:0 4px 10px rgba(79,70,229,.3); }
		.wfv-topnav-tabs{ display:flex; gap:4px; background:var(--wfv-bg); border:1px solid var(--wfv-border); border-radius:999px; padding:4px; }
		.wfv-topnav-tabs a{ text-decoration:none; font-size:13px; font-weight:600; color:var(--wfv-slate); padding:7px 16px; border-radius:999px; transition:.15s; }
		.wfv-topnav-tabs a:hover{ color:var(--wfv-ink); }
		.wfv-topnav-tabs a.wfv-tab-active{ background:#fff; color:var(--wfv-primary); box-shadow:0 1px 3px rgba(15,23,42,.08); }
		@media (max-width: 782px) {
			.wfv-topnav{ flex-direction:column; align-items:stretch; }
			.wfv-topnav-tabs{ justify-content:space-between; }
			.wfv-topnav-tabs a{ flex:1; text-align:center; padding:8px 8px; }
		}

		/* Shared LastPass-style split shell: sidebar item list + detail pane.
		   Used by both the Notes and Passwords pages. */
		.wfv-split{ display:flex; border:1px solid var(--wfv-border); border-radius:var(--wfv-radius); overflow:hidden; box-shadow:var(--wfv-shadow); background:#fff; min-height:560px; }
		.wfv-split-sidebar{ width:320px; flex-shrink:0; border-right:1px solid var(--wfv-border); display:flex; flex-direction:column; background:var(--wfv-bg); }
		.wfv-split-search{ padding:14px 14px 8px; position:relative; }
		.wfv-split-search input{ width:100%; box-sizing:border-box; padding:8px 12px 8px 30px; border-radius:8px; border:1px solid var(--wfv-border); background:#fff; font-size:13px; }
		.wfv-split-search:before{ content:"🔍"; position:absolute; left:24px; top:22px; font-size:12px; opacity:.55; }
		.wfv-split-tabs{ display:flex; gap:4px; padding:0 14px 10px; }
		.wfv-split-tabs button{ flex:1; background:#fff; border:1px solid var(--wfv-border); padding:6px 8px; font-size:11.5px; font-weight:600; border-radius:7px; cursor:pointer; color:var(--wfv-slate); }
		.wfv-split-tabs button.wfv-tab-active{ background:var(--wfv-primary); border-color:var(--wfv-primary); color:#fff; }
		.wfv-split-sort{ padding:0 14px 10px; }
		.wfv-split-sort select{ width:100%; box-sizing:border-box; border-radius:7px; border:1px solid var(--wfv-border); padding:6px 8px; font-size:11.5px; background:#fff; color:var(--wfv-slate); }
		.wfv-split-tagrow{ display:flex; flex-wrap:wrap; gap:5px; padding:0 14px 10px; }
		.wfv-split-tagrow button{ font-size:11px; padding:3px 9px; border-radius:99px; border:1px solid var(--wfv-border); background:#fff; cursor:pointer; color:var(--wfv-slate); }
		.wfv-split-tagrow button.wfv-tag-active{ background:var(--wfv-primary); border-color:var(--wfv-primary); color:#fff; }
		.wfv-split-add{ margin:0 14px 12px; background:var(--wfv-primary); color:#fff; border:0; border-radius:8px; padding:9px; font-weight:600; font-size:13px; cursor:pointer; }
		.wfv-split-add:hover{ background:var(--wfv-primary-dark); }
		.wfv-split-list{ flex:1; overflow-y:auto; padding:0 8px 12px; }
		.wfv-split-item{ display:flex; align-items:center; gap:10px; padding:9px 8px; border-radius:8px; cursor:pointer; }
		.wfv-split-item[draggable="true"]{ cursor:grab; }
		.wfv-split-item.wfv-dragging{ opacity:.4; }
		.wfv-split-item:hover{ background:#eef0f4; }
		.wfv-split-item.wfv-item-active{ background:#fff; box-shadow:0 1px 3px rgba(15,23,42,.08); }
		.wfv-item-icon{ width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700; font-size:13px; flex-shrink:0; }
		.wfv-item-text{ flex:1; min-width:0; }
		.wfv-item-text strong{ display:block; font-size:13px; color:var(--wfv-ink); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
		.wfv-item-text span{ display:block; font-size:11.5px; color:var(--wfv-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
		.wfv-item-star{ font-size:12px; color:#f4b400; flex-shrink:0; }
		.wfv-split-empty-list{ padding:24px 16px; text-align:center; color:var(--wfv-muted); font-size:12.5px; }

		.wfv-split-detail{ flex:1; padding:32px 40px; overflow-y:auto; }
		.wfv-detail-empty{ height:100%; display:flex; flex-direction:column; align-items:center; justify-content:center; color:var(--wfv-muted); text-align:center; padding-top:80px; }
		.wfv-detail-empty .icon{ font-size:40px; margin-bottom:12px; opacity:.6; }
		.wfv-detail-header{ display:flex; align-items:flex-start; gap:14px; margin-bottom:26px; }
		.wfv-detail-icon{ width:44px; height:44px; border-radius:10px; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700; font-size:17px; flex-shrink:0; }
		.wfv-detail-header h2{ margin:0; font-size:19px; }
		.wfv-detail-sub{ font-size:12px; color:var(--wfv-muted); margin-top:2px; }
		.wfv-detail-header-actions{ margin-left:auto; display:flex; align-items:center; gap:8px; }
		.wfv-field-label{ display:block; font-size:11px; text-transform:uppercase; letter-spacing:.03em; color:var(--wfv-muted); margin:16px 0 4px; }
		.wfv-field-label:first-of-type{ margin-top:0; }
		.wfv-detail-input{ width:100%; max-width:640px; box-sizing:border-box; padding:9px 11px; border-radius:8px; border:1px solid var(--wfv-border); font-size:14px; }
		.wfv-detail-row{ display:flex; align-items:center; gap:8px; max-width:640px; }
		.wfv-detail-row input{ flex:1; }
		.wfv-pw-strength{ height:5px; border-radius:3px; background:#e2e8f0; margin-top:6px; max-width:440px; overflow:hidden; }
		.wfv-pw-strength-bar{ height:100%; width:0%; background:#d63638; transition:width .2s, background .2s; }
		.wfv-pw-strength-label{ font-size:11px; color:var(--wfv-muted); margin-top:4px; }
		.wfv-detail-footer{ display:flex; justify-content:space-between; align-items:center; margin-top:26px; max-width:640px; flex-wrap:wrap; gap:10px; }
		.wfv-note-tags{ display:flex; flex-wrap:wrap; gap:4px; margin-top:6px; }
		.wfv-tag-pill{ font-size:10px; padding:2px 8px; border-radius:99px; background:rgba(0,0,0,.06); color:#3c434a; }
		.wfv-detail-readonly-row{ display:flex; align-items:center; gap:8px; max-width:440px; background:var(--wfv-bg); border:1px solid var(--wfv-border); border-radius:8px; padding:9px 12px; margin-top:6px; }
		.wfv-detail-readonly-row span{ flex:1; font-family:Consolas,Monaco,monospace; font-size:13px; word-break:break-all; }
		.wfv-icon-btn{ background:none; border:0; cursor:pointer; font-size:14px; padding:2px 4px; opacity:.7; }
		.wfv-icon-btn:hover{ opacity:1; }

		.wfv-modal{ position:fixed; inset:0; z-index:100000; display:flex; align-items:flex-start; justify-content:center; padding:3vh 16px; overflow-y:auto; }
		.wfv-modal-backdrop{ position:fixed; inset:0; background:rgba(0,0,0,.65); }
		.wfv-modal-content{ position:relative; background:#fff; border-radius:14px; width:100%; max-width:560px; padding:24px 28px 20px; box-shadow:var(--wfv-shadow-lg); }
		.wfv-modal-close{ position:absolute; top:10px; right:14px; background:none; border:0; font-size:24px; cursor:pointer; line-height:1; color:#646970; }
		.wfv-color-swatches{ display:flex; gap:6px; flex-wrap:wrap; }
		.wfv-swatch{ width:20px; height:20px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; cursor:pointer; border:2px solid transparent; }
		.wfv-swatch input{ opacity:0; width:1px; height:1px; }
		.wfv-swatch:has(input:checked){ border-color:#1d2327; }
		.wfv-toast{ position:fixed; bottom:24px; right:24px; background:var(--wfv-ink); color:#fff; padding:10px 18px; border-radius:8px; font-size:13px; opacity:0; transform:translateY(8px); transition:.2s; pointer-events:none; z-index:100001; }
		.wfv-toast.wfv-show{ opacity:1; transform:translateY(0); }
		@media (max-width: 900px) {
			.wfv-split{ flex-direction:column; min-height:0; }
			.wfv-split-sidebar{ width:100%; border-right:0; border-bottom:1px solid var(--wfv-border); max-height:340px; }
			.wfv-split-detail{ padding:24px 20px; }
		}
	</style>
	<?php
}

/** Renders the shared top nav (brand + Files/Notes/Passwords tabs). $active is 'files' | 'notes' | 'passwords'. */
function wfv_render_top_nav( $active ) {
	?>
	<div class="wfv-topnav">
		<div class="wfv-topnav-brand"><span class="wfv-brand-icon">🛡️</span> Secure Vault</div>
		<div class="wfv-topnav-tabs">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wfv-vault' ) ); ?>" class="<?php echo 'files' === $active ? 'wfv-tab-active' : ''; ?>">📁 Files</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wfv-notes' ) ); ?>" class="<?php echo 'notes' === $active ? 'wfv-tab-active' : ''; ?>">📝 Notes</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wfv-passwords' ) ); ?>" class="<?php echo 'passwords' === $active ? 'wfv-tab-active' : ''; ?>">🔑 Passwords</a>
		</div>
	</div>
	<?php
}

function wfv_file_icon( $mime, $name ) {
	$ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
	if ( strpos( (string) $mime, 'image/' ) === 0 ) {
		return '🖼️';
	}
	if ( 'application/pdf' === $mime || 'pdf' === $ext ) {
		return '📕';
	}
	if ( in_array( $ext, array( 'zip', 'rar', '7z', 'tar', 'gz' ), true ) ) {
		return '🗜️';
	}
	if ( in_array( $ext, array( 'doc', 'docx' ), true ) ) {
		return '📄';
	}
	if ( in_array( $ext, array( 'xls', 'xlsx', 'csv' ), true ) ) {
		return '📊';
	}
	if ( in_array( $ext, array( 'ppt', 'pptx' ), true ) ) {
		return '📽️';
	}
	if ( in_array( $ext, array( 'mp3', 'wav', 'ogg' ), true ) ) {
		return '🎵';
	}
	if ( in_array( $ext, array( 'mp4', 'mov', 'avi', 'webm' ), true ) ) {
		return '🎬';
	}
	return '📁';
}

function wfv_share_status( $s ) {
	if ( $s->revoked ) {
		return array( 'Revoked', 'revoked' );
	}
	if ( $s->expires_at && strtotime( $s->expires_at ) < time() ) {
		return array( 'Expired', 'expired' );
	}
	if ( $s->max_downloads && $s->download_count >= $s->max_downloads ) {
		return array( 'Limit reached', 'limit' );
	}
	return array( 'Active', 'active' );
}

/** Types that are safe to render inline (browser preview) rather than force-download. */
function wfv_previewable_types() {
	return array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'application/pdf' );
}

/**
 * ------------------------------------------------------------------
 * Access control
 * ------------------------------------------------------------------
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

/**
 * ------------------------------------------------------------------
 * Admin menu + action routing
 * ------------------------------------------------------------------
 */
add_action( 'admin_menu', 'wfv_admin_menu' );
function wfv_admin_menu() {
	add_menu_page(
		'Secure Vault',
		'Secure Vault',
		'read', // any logged-in user; each sub-page enforces its own visibility rules
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

add_action( 'admin_enqueue_scripts', 'wfv_maybe_enqueue_editor' );
function wfv_maybe_enqueue_editor() {
	if ( isset( $_GET['page'] ) && 'wfv-notes' === $_GET['page'] ) {
		wp_enqueue_editor(); // native WordPress TinyMCE + Quicktags, no external library
	}
}

/**
 * ------------------------------------------------------------------
 * Master password — set up / changed from the user's own profile page.
 * ------------------------------------------------------------------
 */
function wfv_master_msg_key( $user_id ) {
	return 'wfv_master_pw_msg_' . $user_id;
}

add_action( 'show_user_profile', 'wfv_render_master_password_profile_fields' );
function wfv_render_master_password_profile_fields( $user ) {
	if ( ! wfv_user_has_access() ) {
		return;
	}
	$enabled    = wfv_pm_master_enabled( $user->ID );
	$changed_at = get_user_meta( $user->ID, 'wfv_master_changed_at', true );
	$flash      = get_transient( wfv_master_msg_key( $user->ID ) );
	if ( $flash ) {
		delete_transient( wfv_master_msg_key( $user->ID ) );
	}
	?>
	<div id="wfv-master-pw-section">
		<style>
			#wfv-master-pw-section{ --wfv-brand:#4f46e5; --wfv-brand-dark:#3730a3; --wfv-slate:#475569; --wfv-bg:#f8fafc; --wfv-border:#e2e8f0; margin-top:10px; }
			#wfv-master-pw-section h2{ display:flex; align-items:center; gap:8px; font-size:18px; }
			.wfv-mp-card{ background:linear-gradient(180deg,#ffffff,var(--wfv-bg)); border:1px solid var(--wfv-border); border-radius:14px; padding:26px 28px; max-width:640px; box-shadow:0 1px 3px rgba(15,23,42,.06), 0 6px 20px rgba(15,23,42,.04); }
			.wfv-mp-badge{ display:inline-flex; align-items:center; gap:6px; font-size:12px; font-weight:600; padding:4px 12px; border-radius:99px; margin-bottom:14px; }
			.wfv-mp-badge.wfv-mp-on{ background:#ecfdf3; color:#027a48; }
			.wfv-mp-badge.wfv-mp-off{ background:#fff4e5; color:#b8590a; }
			.wfv-mp-desc{ color:var(--wfv-slate); font-size:13.5px; line-height:1.6; margin-bottom:18px; max-width:520px; }
			.wfv-mp-warning{ background:#fffbea; border:1px solid #fbe38a; border-radius:8px; padding:10px 14px; font-size:12.5px; color:#7a5b00; margin-bottom:18px; }
			.wfv-mp-flash{ border-radius:8px; padding:10px 14px; font-size:13px; margin-bottom:18px; }
			.wfv-mp-flash.success{ background:#ecfdf3; color:#027a48; border:1px solid #a6f4c5; }
			.wfv-mp-flash.error{ background:#fef3f2; color:#b42318; border:1px solid #fecdca; }
			.wfv-mp-field{ margin-bottom:14px; max-width:360px; }
			.wfv-mp-field label{ display:block; font-size:12px; font-weight:600; color:#334155; margin-bottom:5px; }
			.wfv-mp-field input{ width:100%; box-sizing:border-box; padding:9px 11px; border:1px solid var(--wfv-border); border-radius:8px; font-size:14px; }
			.wfv-mp-field input:focus{ outline:2px solid var(--wfv-brand); outline-offset:1px; border-color:var(--wfv-brand); }
			.wfv-mp-submit{ background:var(--wfv-brand); border-color:var(--wfv-brand); color:#fff; border-radius:8px; padding:8px 18px; font-weight:600; }
			.wfv-mp-submit:hover{ background:var(--wfv-brand-dark); border-color:var(--wfv-brand-dark); color:#fff; }
			.wfv-mp-meta{ font-size:11.5px; color:#94a3b8; margin-top:10px; }
		</style>
		<h2>🔑 Vault Master Password</h2>
		<div class="wfv-mp-card">
			<?php if ( $flash ) : ?>
				<div class="wfv-mp-flash <?php echo esc_attr( $flash['type'] ); ?>"><?php echo esc_html( $flash['text'] ); ?></div>
			<?php endif; ?>

			<?php if ( $enabled ) : ?>
				<span class="wfv-mp-badge wfv-mp-on">🔒 Protected</span>
				<p class="wfv-mp-desc">Your saved passwords in the <strong>Passwords</strong> vault are encrypted with a key derived from this master password — it's never stored anywhere, and it's separate from your WordPress login password. Enter your current one to change it.</p>
			<?php else : ?>
				<span class="wfv-mp-badge wfv-mp-off">⚠ Not set up</span>
				<p class="wfv-mp-desc">Add a master password to encrypt your <strong>Passwords</strong> vault with a key that only you know — stronger than the site-wide default encryption, since nothing can be decrypted (even with server/database access) without it.</p>
				<div class="wfv-mp-warning">⚠️ There is no recovery if you forget this. It is not tied to your WordPress account password and site admins cannot reset it for you without wiping your saved entries.</div>
			<?php endif; ?>

			<?php wp_nonce_field( 'wfv_master_password_profile', 'wfv_master_nonce' ); ?>

			<?php if ( $enabled ) : ?>
				<div class="wfv-mp-field">
					<label for="wfv_current_master_password">Current master password</label>
					<input type="password" name="wfv_current_master_password" id="wfv_current_master_password" autocomplete="off">
				</div>
			<?php endif; ?>
			<div class="wfv-mp-field">
				<label for="wfv_new_master_password"><?php echo $enabled ? 'New master password' : 'Master password'; ?></label>
				<input type="password" name="wfv_new_master_password" id="wfv_new_master_password" autocomplete="new-password" minlength="8">
			</div>
			<div class="wfv-mp-field">
				<label for="wfv_confirm_master_password">Confirm <?php echo $enabled ? 'new ' : ''; ?>master password</label>
				<input type="password" name="wfv_confirm_master_password" id="wfv_confirm_master_password" autocomplete="new-password" minlength="8">
			</div>
			<p style="font-size:12px; color:#94a3b8;">At least 8 characters. This is saved along with the rest of your profile using the button below.</p>
			<?php if ( $changed_at ) : ?>
				<p class="wfv-mp-meta">Last changed <?php echo esc_html( mysql2date( 'M j, Y g:ia', $changed_at ) ); ?>.</p>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

add_action( 'personal_options_update', 'wfv_save_master_password_profile_fields' );
function wfv_save_master_password_profile_fields( $user_id ) {
	$user_id = absint( $user_id );
	if ( $user_id !== get_current_user_id() ) {
		return; // extra safety; this hook only fires for one's own profile anyway
	}
	if ( ! isset( $_POST['wfv_master_nonce'] ) || ! wp_verify_nonce( $_POST['wfv_master_nonce'], 'wfv_master_password_profile' ) ) {
		return;
	}
	if ( ! wfv_user_has_access() ) {
		return;
	}

	$new_pw = isset( $_POST['wfv_new_master_password'] ) ? (string) $_POST['wfv_new_master_password'] : '';
	if ( '' === $new_pw ) {
		return; // section left untouched — profile save continues normally
	}
	$confirm = isset( $_POST['wfv_confirm_master_password'] ) ? (string) $_POST['wfv_confirm_master_password'] : '';

	if ( strlen( $new_pw ) < 8 ) {
		set_transient( wfv_master_msg_key( $user_id ), array( 'type' => 'error', 'text' => 'Master password must be at least 8 characters — nothing was changed.' ), 60 );
		return;
	}
	if ( $new_pw !== $confirm ) {
		set_transient( wfv_master_msg_key( $user_id ), array( 'type' => 'error', 'text' => 'The two master password fields did not match — nothing was changed.' ), 60 );
		return;
	}

	if ( ! wfv_pm_master_enabled( $user_id ) ) {
		// First-time setup: migrate this user's vault from the site-wide key to a new master key.
		$salt = random_bytes( 16 );
		$key  = wfv_pm_derive_key( $new_pw, $salt );
		wfv_pm_reencrypt_vault( $user_id, wfv_pm_key(), $key );
		update_user_meta( $user_id, 'wfv_master_salt', base64_encode( $salt ) );
		update_user_meta( $user_id, 'wfv_master_verifier', wfv_pm_encrypt_raw( wfv_pm_verifier_marker(), $key ) );
		update_user_meta( $user_id, 'wfv_master_enabled', '1' );
		update_user_meta( $user_id, 'wfv_master_changed_at', current_time( 'mysql' ) );
		wfv_pm_start_session( $key );
		set_transient( wfv_master_msg_key( $user_id ), array( 'type' => 'success', 'text' => 'Master password set up — your vault is now encrypted with it and unlocked for this session.' ), 60 );
		return;
	}

	// Changing an existing master password requires proving the current one first.
	$current  = isset( $_POST['wfv_current_master_password'] ) ? (string) $_POST['wfv_current_master_password'] : '';
	$old_salt = base64_decode( get_user_meta( $user_id, 'wfv_master_salt', true ) );
	$old_key  = wfv_pm_derive_key( $current, $old_salt );
	$old_verifier = get_user_meta( $user_id, 'wfv_master_verifier', true );

	if ( wfv_pm_decrypt_raw( $old_verifier, $old_key ) !== wfv_pm_verifier_marker() ) {
		set_transient( wfv_master_msg_key( $user_id ), array( 'type' => 'error', 'text' => 'Your current master password was incorrect — nothing was changed.' ), 60 );
		return;
	}

	$new_salt = random_bytes( 16 );
	$new_key  = wfv_pm_derive_key( $new_pw, $new_salt );
	wfv_pm_reencrypt_vault( $user_id, $old_key, $new_key );
	update_user_meta( $user_id, 'wfv_master_salt', base64_encode( $new_salt ) );
	update_user_meta( $user_id, 'wfv_master_verifier', wfv_pm_encrypt_raw( wfv_pm_verifier_marker(), $new_key ) );
	update_user_meta( $user_id, 'wfv_master_changed_at', current_time( 'mysql' ) );
	wfv_pm_lock_vault();
	wfv_pm_start_session( $new_key );
	set_transient( wfv_master_msg_key( $user_id ), array( 'type' => 'success', 'text' => 'Master password changed and your vault re-encrypted with it.' ), 60 );
}

add_action( 'admin_init', 'wfv_handle_admin_actions' );
function wfv_handle_admin_actions() {
	if ( ! is_admin() || ! wfv_user_has_access() ) {
		return;
	}
	if ( empty( $_POST['wfv_action'] ) ) {
		return;
	}

	$action = sanitize_key( $_POST['wfv_action'] );

	switch ( $action ) {
		case 'upload':
			wfv_process_upload();
			break;
		case 'create_share':
			wfv_process_create_share();
			break;
		case 'revoke_share':
			wfv_process_revoke_share();
			break;
		case 'delete_file':
			wfv_process_delete_file();
			break;
		case 'create_folder':
			wfv_process_create_folder();
			break;
		case 'rename_folder':
			wfv_process_rename_folder();
			break;
		case 'delete_folder':
			wfv_process_delete_folder();
			break;
		case 'move_file':
			wfv_process_move_file();
			break;
		case 'toggle_star_file':
			wfv_process_toggle_star_file();
			break;
		case 'toggle_star_folder':
			wfv_process_toggle_star_folder();
			break;
		case 'create_note':
			wfv_process_create_note();
			break;
		case 'update_note':
			wfv_process_update_note();
			break;
		case 'delete_note':
			wfv_process_delete_note();
			break;
		case 'toggle_pin_note':
			wfv_process_toggle_pin_note();
			break;
		case 'create_password':
			wfv_process_create_password();
			break;
		case 'update_password':
			wfv_process_update_password();
			break;
		case 'delete_password':
			wfv_process_delete_password();
			break;
		case 'toggle_star_password':
			wfv_process_toggle_star_password();
			break;
		case 'unlock_vault':
			wfv_process_unlock_vault();
			break;
		case 'lock_vault':
			wfv_process_lock_vault();
			break;
		case 'share_password_to_user':
			wfv_process_share_password_to_user();
			break;
		case 'revoke_user_share':
			wfv_process_revoke_user_share();
			break;
		case 'remove_shared_with_me':
			wfv_process_remove_shared_with_me();
			break;
		case 'create_password_link':
			wfv_process_create_password_link();
			break;
		case 'revoke_password_link':
			wfv_process_revoke_password_link();
			break;
	}
}

/**
 * Redirect back to wherever the user was (same folder / starred view),
 * carrying a notice message. $open_file_id optionally re-expands a file's
 * share panel after creating/revoking a link.
 */
function wfv_redirect_with_notice( $type, $message, $open_file_id = 0, $open_password_id = 0 ) {
	$folder_id = isset( $_POST['ctx_folder'] ) ? absint( $_POST['ctx_folder'] ) : 0;
	$starred   = isset( $_POST['ctx_starred'] ) && '1' === (string) $_POST['ctx_starred'];
	$view      = isset( $_POST['ctx_view'] ) ? sanitize_key( $_POST['ctx_view'] ) : 'files';

	if ( 'notes' === $view ) {
		$query = array(
			'page'       => 'wfv-notes',
			'wfv_notice' => $type,
			'wfv_msg'    => rawurlencode( $message ),
		);
	} elseif ( 'passwords' === $view ) {
		$query = array(
			'page'       => 'wfv-passwords',
			'wfv_notice' => $type,
			'wfv_msg'    => rawurlencode( $message ),
		);
		if ( $open_password_id ) {
			$query['wfv_open_password'] = $open_password_id;
		}
	} else {
		$query = array(
			'page'       => 'wfv-vault',
			'wfv_notice' => $type,
			'wfv_msg'    => rawurlencode( $message ),
		);
		if ( $folder_id ) {
			$query['folder'] = $folder_id;
		}
		if ( $starred ) {
			$query['starred'] = 1;
		}
		if ( $open_file_id ) {
			$query['wfv_open_file'] = $open_file_id;
		}
	}

	$url = add_query_arg( $query, admin_url( 'admin.php' ) );
	wp_safe_redirect( $url );
	exit;
}

/**
 * ------------------------------------------------------------------
 * File actions
 * ------------------------------------------------------------------
 */
function wfv_process_upload() {
	check_admin_referer( 'wfv_upload', 'wfv_upload_nonce' );

	if ( empty( $_FILES['wfv_file'] ) || ! isset( $_FILES['wfv_file']['error'] ) || UPLOAD_ERR_OK !== $_FILES['wfv_file']['error'] ) {
		wfv_redirect_with_notice( 'error', 'Upload failed. Please try again.' );
	}

	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/file.php';

	wfv_prepare_private_dir();
	$target_dir = wfv_private_dir_path();

	// Only upload into a folder the current user actually owns (or any
	// folder, if they're an admin) — otherwise fall back to root.
	$folder_id = isset( $_POST['folder_id'] ) ? absint( $_POST['folder_id'] ) : 0;
	if ( $folder_id ) {
		$folder = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . wfv_folders_table() . " WHERE id = %d", $folder_id ) );
		if ( ! wfv_user_can_manage_folder( $folder ) ) {
			$folder_id = 0;
		}
	}

	// Redirect WordPress's upload handler to our private directory just
	// for this one call.
	$override_dir = function ( $dirs ) use ( $target_dir ) {
		$dirs['path']   = $target_dir;
		$dirs['url']    = 'about:blank'; // never expose a direct public URL
		$dirs['subdir'] = '';
		return $dirs;
	};
	add_filter( 'upload_dir', $override_dir );

	$original_name = sanitize_file_name( $_FILES['wfv_file']['name'] );
	$ext           = strtolower( pathinfo( $original_name, PATHINFO_EXTENSION ) );
	$stored_name   = wp_generate_password( 20, false, false ) . '_' . time() . ( $ext ? '.' . $ext : '' );

	// Rename the incoming file so it lands under our randomized name.
	$_FILES['wfv_file']['name'] = $stored_name;

	$result = wp_handle_upload(
		$_FILES['wfv_file'],
		array(
			'test_form' => false,
			'action'    => 'wfv_upload_action',
		)
	);

	remove_filter( 'upload_dir', $override_dir );

	if ( isset( $result['error'] ) ) {
		wfv_redirect_with_notice( 'error', 'Upload failed: ' . $result['error'] );
	}

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

	wfv_redirect_with_notice( 'success', 'File uploaded.' );
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
 * Personal notes (freeform, not tied to any file)
 * ------------------------------------------------------------------
 */
function wfv_parse_tags( $raw ) {
	$raw  = (string) $raw;
	$tags = array_filter( array_map( 'trim', explode( ',', $raw ) ) );
	$tags = array_map( 'sanitize_text_field', $tags );
	$tags = array_values( array_unique( $tags ) );
	$tags = array_slice( $tags, 0, 15 ); // keep it sane
	return implode( ', ', $tags );
}

function wfv_process_create_note() {
	check_admin_referer( 'wfv_create_note', 'wfv_note_nonce' );
	global $wpdb;

	$title   = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
	$content = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '';
	$tags    = isset( $_POST['tags'] ) ? wfv_parse_tags( wp_unslash( $_POST['tags'] ) ) : '';
	$colors  = wfv_colors();
	$color   = isset( $_POST['color'] ) ? sanitize_key( $_POST['color'] ) : 'yellow';
	if ( ! array_key_exists( $color, $colors ) ) {
		$color = 'yellow';
	}

	if ( '' === $title && '' === $content ) {
		wfv_redirect_with_notice( 'error', 'A note needs a title or some content.' );
	}

	$user_id       = get_current_user_id();
	$max_order     = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(sort_order) FROM " . wfv_notes_table() . " WHERE created_by = %d", $user_id ) );
	$next_order    = ( is_null( $max_order ) ? 0 : (int) $max_order ) + 10;

	$wpdb->insert(
		wfv_notes_table(),
		array(
			'title'      => $title,
			'content'    => $content,
			'color'      => $color,
			'tags'       => $tags,
			'pinned'     => 0,
			'sort_order' => $next_order,
			'created_by' => $user_id,
			'created_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
		),
		array( '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s' )
	);

	wfv_redirect_with_notice( 'success', 'Note added.' );
}

function wfv_process_update_note() {
	check_admin_referer( 'wfv_update_note', 'wfv_note_update_nonce' );
	global $wpdb;

	$note_id = isset( $_POST['note_id'] ) ? absint( $_POST['note_id'] ) : 0;
	$note    = $note_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . wfv_notes_table() . " WHERE id = %d", $note_id ) ) : null;

	if ( ! wfv_user_can_manage_note( $note ) ) {
		wfv_redirect_with_notice( 'error', 'You do not have permission to edit this note.' );
	}

	$colors  = wfv_colors();
	$title   = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : $note->title;
	$content = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : $note->content;
	$tags    = isset( $_POST['tags'] ) ? wfv_parse_tags( wp_unslash( $_POST['tags'] ) ) : $note->tags;
	$color   = isset( $_POST['color'] ) ? sanitize_key( $_POST['color'] ) : $note->color;
	if ( ! array_key_exists( $color, $colors ) ) {
		$color = $note->color;
	}

	$wpdb->update(
		wfv_notes_table(),
		array(
			'title'      => $title,
			'content'    => $content,
			'color'      => $color,
			'tags'       => $tags,
			'updated_at' => current_time( 'mysql' ),
		),
		array( 'id' => $note_id ),
		array( '%s', '%s', '%s', '%s', '%s' ),
		array( '%d' )
	);

	wfv_redirect_with_notice( 'success', 'Note updated.' );
}

function wfv_process_delete_note() {
	check_admin_referer( 'wfv_delete_note', 'wfv_note_delete_nonce' );
	global $wpdb;

	$note_id = isset( $_POST['note_id'] ) ? absint( $_POST['note_id'] ) : 0;
	$note    = $note_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . wfv_notes_table() . " WHERE id = %d", $note_id ) ) : null;

	if ( ! wfv_user_can_manage_note( $note ) ) {
		wfv_redirect_with_notice( 'error', 'You do not have permission to delete this note.' );
	}
	$wpdb->delete( wfv_notes_table(), array( 'id' => $note_id ), array( '%d' ) );
	wfv_redirect_with_notice( 'success', 'Note deleted.' );
}

function wfv_process_toggle_pin_note() {
	check_admin_referer( 'wfv_toggle_pin_note', 'wfv_pin_nonce' );
	global $wpdb;

	$note_id = isset( $_POST['note_id'] ) ? absint( $_POST['note_id'] ) : 0;
	$note    = $note_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . wfv_notes_table() . " WHERE id = %d", $note_id ) ) : null;

	if ( ! wfv_user_can_manage_note( $note ) ) {
		wfv_redirect_with_notice( 'error', 'You do not have permission to pin this note.' );
	}
	$wpdb->update( wfv_notes_table(), array( 'pinned' => $note->pinned ? 0 : 1 ), array( 'id' => $note_id ), array( '%d' ), array( '%d' ) );
	wfv_redirect_with_notice( 'success', $note->pinned ? 'Unpinned.' : 'Pinned.' );
}

/**
 * Drag-and-drop reordering, saved via a small AJAX call so the page
 * doesn't have to reload after every drop.
 */
add_action( 'wp_ajax_wfv_reorder_notes', 'wfv_ajax_reorder_notes' );
function wfv_ajax_reorder_notes() {
	if ( ! wfv_user_has_access() ) {
		wp_send_json_error( 'no_access', 403 );
	}
	check_ajax_referer( 'wfv_reorder_notes', 'nonce' );

	global $wpdb;
	$order = isset( $_POST['order'] ) ? (array) $_POST['order'] : array();
	$step  = 10;

	foreach ( $order as $note_id ) {
		$note_id = absint( $note_id );
		if ( ! $note_id ) {
			continue;
		}
		$note = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . wfv_notes_table() . " WHERE id = %d", $note_id ) );
		if ( wfv_user_can_manage_note( $note ) ) {
			$wpdb->update( wfv_notes_table(), array( 'sort_order' => $step ), array( 'id' => $note_id ), array( '%d' ), array( '%d' ) );
		}
		$step += 10;
	}

	wp_send_json_success();
}

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

function wfv_generate_unique_token() {
	global $wpdb;
	do {
		$token  = bin2hex( random_bytes( 20 ) ); // 40-char, unique per share
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM " . wfv_shares_table() . " WHERE token = %s", $token ) );
	} while ( $exists );
	return $token;
}

/**
 * ------------------------------------------------------------------
 * Front-end share URL handling
 * A share link looks like: https://yoursite.com/?wfv_token=xxxxxxxx
 * No rewrite rules needed, so it works with any permalink setting.
 * ------------------------------------------------------------------
 */
add_action( 'template_redirect', 'wfv_handle_download' );
function wfv_handle_download() {
	if ( empty( $_GET['wfv_token'] ) ) {
		return;
	}
	global $wpdb;

	$token = preg_replace( '/[^a-f0-9]/', '', sanitize_text_field( wp_unslash( $_GET['wfv_token'] ) ) );

	$share = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT s.*, f.original_name, f.stored_name, f.mime_type, f.file_size
			 FROM " . wfv_shares_table() . " s
			 INNER JOIN " . wfv_files_table() . " f ON f.id = s.file_id
			 WHERE s.token = %s",
			$token
		)
	);

	nocache_headers();

	if ( ! $share ) {
		wfv_deny_page( 'This link is invalid.' );
	}
	if ( (int) $share->revoked === 1 ) {
		wfv_deny_page( 'This link has been revoked by the file owner.' );
	}
	if ( $share->expires_at && strtotime( $share->expires_at ) < time() ) {
		wfv_deny_page( 'This link has expired.' );
	}
	if ( $share->max_downloads && (int) $share->download_count >= (int) $share->max_downloads ) {
		wfv_deny_page( 'This link has reached its download limit.' );
	}

	if ( ! empty( $share->password_hash ) ) {
		$supplied = isset( $_POST['wfv_password'] ) ? (string) $_POST['wfv_password'] : '';
		$ok       = $supplied !== '' && wp_check_password( $supplied, $share->password_hash );
		if ( ! $ok ) {
			$error = isset( $_POST['wfv_password'] ) ? 'Incorrect password.' : '';
			wfv_password_page( $token, $error );
		}
	}

	$path = trailingslashit( wfv_private_dir_path() ) . $share->stored_name;
	if ( ! file_exists( $path ) ) {
		wfv_deny_page( 'The requested file is no longer available.' );
	}

	// Passed all checks: count it and stream the file.
	$wpdb->query( $wpdb->prepare( "UPDATE " . wfv_shares_table() . " SET download_count = download_count + 1 WHERE id = %d", $share->id ) );

	while ( ob_get_level() ) {
		ob_end_clean();
	}

	// Only a small, known-safe set of types are ever shown inline — this
	// avoids the risk of a browser executing an uploaded HTML/SVG file.
	$want_download = isset( $_GET['dl'] ); // append &dl=1 to a link to force a download instead
	$can_preview   = in_array( $share->mime_type, wfv_previewable_types(), true ) && ! $want_download;

	header( 'X-Content-Type-Options: nosniff' );
	if ( $can_preview ) {
		header( 'Content-Type: ' . $share->mime_type );
		header( 'Content-Disposition: inline; filename="' . rawurlencode( $share->original_name ) . '"' );
	} else {
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="' . rawurlencode( $share->original_name ) . '"' );
	}
	header( 'Content-Length: ' . filesize( $path ) );
	readfile( $path );
	exit;
}

function wfv_password_page( $token, $error = '' ) {
	status_header( 200 );
	?>
	<!DOCTYPE html>
	<html>
	<head><meta charset="utf-8"><title>Password required</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<style>
		body{font-family:-apple-system,Segoe UI,Arial,sans-serif;background:#f0f0f1;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}
		.box{background:#fff;padding:32px;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.1);max-width:360px;width:100%;}
		h1{font-size:18px;margin:0 0 16px;}
		input[type=password]{width:100%;padding:8px;box-sizing:border-box;margin-bottom:12px;border:1px solid #ccd0d4;border-radius:4px;}
		button{background:#2271b1;color:#fff;border:0;padding:8px 16px;border-radius:4px;cursor:pointer;width:100%;}
		.err{color:#d63638;font-size:13px;margin-bottom:12px;}
	</style>
	</head>
	<body>
		<div class="box">
			<h1>🔒 This file is password protected</h1>
			<?php if ( $error ) : ?><div class="err"><?php echo esc_html( $error ); ?></div><?php endif; ?>
			<form method="post">
				<input type="password" name="wfv_password" placeholder="Enter password" autofocus required>
				<button type="submit">Unlock &amp; Download</button>
			</form>
		</div>
	</body>
	</html>
	<?php
	exit;
}

function wfv_deny_page( $message ) {
	status_header( 403 );
	?>
	<!DOCTYPE html>
	<html>
	<head><meta charset="utf-8"><title>Link unavailable</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<style>
		body{font-family:-apple-system,Segoe UI,Arial,sans-serif;background:#f0f0f1;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}
		.box{background:#fff;padding:32px;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.1);max-width:360px;width:100%;text-align:center;}
	</style>
	</head>
	<body><div class="box">🚫 <?php echo esc_html( $message ); ?></div></body>
	</html>
	<?php
	exit;
}

/**
 * ------------------------------------------------------------------
 * Public password link share — a share link looks like:
 * https://yoursite.com/?wfv_pwtoken=xxxxxxxx
 * No login required; the token is the access control (plus an optional
 * access password). Mirrors the file-share link flow, but the reveal
 * itself happens via a small AJAX call so the plaintext password is
 * never embedded directly in this page's HTML.
 * ------------------------------------------------------------------
 */
add_action( 'template_redirect', 'wfv_handle_password_link_share' );
function wfv_handle_password_link_share() {
	if ( empty( $_GET['wfv_pwtoken'] ) ) {
		return;
	}
	global $wpdb;

	$token = preg_replace( '/[^a-f0-9]/', '', sanitize_text_field( wp_unslash( $_GET['wfv_pwtoken'] ) ) );
	$share = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . wfv_password_link_shares_table() . " WHERE token = %s", $token ) );

	nocache_headers();

	if ( ! $share ) {
		wfv_deny_page( 'This link is invalid.' );
	}
	if ( $share->revoked ) {
		wfv_deny_page( 'This link has been revoked by the owner.' );
	}
	if ( $share->expires_at && strtotime( $share->expires_at ) < time() ) {
		wfv_deny_page( 'This link has expired.' );
	}
	if ( $share->max_views && (int) $share->view_count >= (int) $share->max_views ) {
		wfv_deny_page( 'This link has reached its view limit.' );
	}

	$needs_password = ! empty( $share->access_password_hash );
	$ajax_nonce     = wp_create_nonce( 'wfv_reveal_password_link' );
	status_header( 200 );
	?>
	<!DOCTYPE html>
	<html>
	<head>
		<meta charset="utf-8">
		<title><?php echo esc_html( $share->title ); ?> — Shared password</title>
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<style>
			:root{ --wfv-brand:#4f46e5; --wfv-brand-dark:#3730a3; --wfv-slate:#475569; }
			body{ font-family:-apple-system,Segoe UI,Arial,sans-serif; background:#f8fafc; display:flex; align-items:center; justify-content:center; min-height:100vh; margin:0; padding:20px; box-sizing:border-box; }
			.wfv-card{ background:#fff; border:1px solid #e2e8f0; border-radius:16px; padding:34px 30px; max-width:420px; width:100%; box-shadow:0 1px 3px rgba(15,23,42,.06), 0 12px 32px rgba(15,23,42,.08); }
			.wfv-card .icon{ width:48px; height:48px; border-radius:50%; background:linear-gradient(135deg,var(--wfv-brand),var(--wfv-brand-dark)); display:flex; align-items:center; justify-content:center; font-size:20px; margin-bottom:14px; }
			.wfv-card h1{ font-size:18px; margin:0 0 4px; color:#0f172a; }
			.wfv-card .sub{ color:var(--wfv-slate); font-size:13px; margin:0 0 22px; }
			.field{ margin-bottom:16px; }
			.field label{ display:block; font-size:11px; text-transform:uppercase; letter-spacing:.03em; color:#94a3b8; margin-bottom:4px; }
			.field-row{ display:flex; align-items:center; gap:8px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:9px 12px; }
			.field-row span{ flex:1; font-family:Consolas,Monaco,monospace; font-size:13.5px; word-break:break-all; }
			.field-row button{ background:none; border:0; cursor:pointer; font-size:14px; opacity:.7; }
			.field-row button:hover{ opacity:1; }
			.gate{ margin-bottom:16px; }
			.gate input{ width:100%; box-sizing:border-box; padding:9px 11px; border:1px solid #e2e8f0; border-radius:8px; font-size:14px; margin-bottom:8px; }
			.btn{ width:100%; background:var(--wfv-brand); border:0; color:#fff; border-radius:8px; padding:10px; font-weight:600; font-size:14px; cursor:pointer; }
			.btn:hover{ background:var(--wfv-brand-dark); }
			.err{ background:#fef3f2; border:1px solid #fecdca; color:#b42318; border-radius:8px; padding:9px 12px; font-size:12.5px; margin-bottom:14px; display:none; }
			.foot{ font-size:11px; color:#94a3b8; margin-top:18px; text-align:center; }
			.notes-box{ background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:10px 12px; font-size:12.5px; color:#334155; white-space:pre-wrap; }
		</style>
	</head>
	<body>
		<div class="wfv-card">
			<div class="icon">🔑</div>
			<h1><?php echo esc_html( $share->title ); ?></h1>
			<p class="sub">A password was shared with you<?php echo $share->label ? ' — ' . esc_html( $share->label ) : ''; ?>.</p>

			<div class="err" id="wfv-pwshare-error"></div>

			<div id="wfv-pwshare-gate" class="gate" style="<?php echo $needs_password ? '' : 'display:none;'; ?>">
				<label for="wfv-pwshare-access">This link is protected — enter the access password</label>
				<input type="password" id="wfv-pwshare-access" autocomplete="off">
				<button type="button" class="btn" id="wfv-pwshare-unlock-btn">View password</button>
			</div>

			<div id="wfv-pwshare-content" style="<?php echo $needs_password ? 'display:none;' : ''; ?>">
				<?php if ( $share->username ) : ?>
					<div class="field">
						<label>Username</label>
						<div class="field-row"><span><?php echo esc_html( $share->username ); ?></span><button type="button" id="wfv-pwshare-copy-user" title="Copy">📋</button></div>
					</div>
				<?php endif; ?>
				<div class="field">
					<label>Password</label>
					<div class="field-row">
						<span id="wfv-pwshare-pw-display">••••••••</span>
						<button type="button" id="wfv-pwshare-reveal" title="Reveal">👁</button>
						<button type="button" id="wfv-pwshare-copy-pw" title="Copy">📋</button>
					</div>
				</div>
				<?php if ( $share->url ) : ?>
					<div class="field">
						<label>Website</label>
						<div class="field-row"><span><a href="<?php echo esc_url( $share->url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $share->url ); ?></a></span></div>
					</div>
				<?php endif; ?>
				<div class="field" id="wfv-pwshare-notes-wrap" style="display:none;">
					<label>Notes</label>
					<div class="notes-box" id="wfv-pwshare-notes"></div>
				</div>
			</div>

			<p class="foot">Shared privately via a Secure File Vault password link. This page does not require an account.</p>
		</div>

		<script>
		(function(){
			var token = <?php echo wp_json_encode( $token ); ?>;
			var nonce = <?php echo wp_json_encode( $ajax_nonce ); ?>;
			var ajaxurl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			var needsPassword = <?php echo $needs_password ? 'true' : 'false'; ?>;
			var revealed = null;

			var errorBox = document.getElementById('wfv-pwshare-error');
			var gate = document.getElementById('wfv-pwshare-gate');
			var content = document.getElementById('wfv-pwshare-content');
			var pwDisplay = document.getElementById('wfv-pwshare-pw-display');

			function showError(text){
				errorBox.textContent = text;
				errorBox.style.display = 'block';
			}

			function fetchSecret(accessPassword, cb){
				var params = new URLSearchParams();
				params.append('action', 'wfv_reveal_password_link');
				params.append('nonce', nonce);
				params.append('token', token);
				if ( accessPassword ) { params.append('access_password', accessPassword); }
				fetch(ajaxurl, { method: 'POST', body: params })
					.then(function(r){ return r.json(); })
					.then(function(res){ cb(res); })
					.catch(function(){ cb({ success:false }); });
			}

			function copyText(text){
				if ( navigator.clipboard && navigator.clipboard.writeText ) {
					navigator.clipboard.writeText(text);
				} else {
					var t = document.createElement('textarea');
					t.value = text; document.body.appendChild(t); t.select();
					document.execCommand('copy'); document.body.removeChild(t);
				}
			}

			function onRevealed(data){
				revealed = data;
				gate.style.display = 'none';
				content.style.display = '';
				if ( data.notes ) {
					document.getElementById('wfv-pwshare-notes-wrap').style.display = '';
					document.getElementById('wfv-pwshare-notes').textContent = data.notes;
				}
			}

			if ( needsPassword ) {
				document.getElementById('wfv-pwshare-unlock-btn').addEventListener('click', function(){
					var val = document.getElementById('wfv-pwshare-access').value;
					fetchSecret(val, function(res){
						if ( res.success ) {
							onRevealed(res.data);
							pwDisplay.textContent = res.data.password;
							setTimeout(function(){ pwDisplay.textContent = '••••••••'; }, 6000);
						} else { showError('Incorrect access password.'); }
					});
				});
			}

			document.getElementById('wfv-pwshare-reveal').addEventListener('click', function(){
				if ( revealed ) {
					pwDisplay.textContent = revealed.password;
					setTimeout(function(){ pwDisplay.textContent = '••••••••'; }, 6000);
					return;
				}
				fetchSecret(null, function(res){
					if ( res.success ) {
						onRevealed(res.data);
						pwDisplay.textContent = res.data.password;
						setTimeout(function(){ pwDisplay.textContent = '••••••••'; }, 6000);
					} else {
						showError('This link is no longer available.');
					}
				});
			});
			document.getElementById('wfv-pwshare-copy-pw').addEventListener('click', function(){
				if ( revealed ) { copyText(revealed.password); return; }
				fetchSecret(null, function(res){
					if ( res.success ) { onRevealed(res.data); copyText(res.data.password); }
					else { showError('This link is no longer available.'); }
				});
			});
			var copyUserBtn = document.getElementById('wfv-pwshare-copy-user');
			if ( copyUserBtn ) {
				copyUserBtn.addEventListener('click', function(){
					copyText( <?php echo wp_json_encode( (string) $share->username ); ?> );
				});
			}
		})();
		</script>
	</body>
	</html>
	<?php
	exit;
}

/**
 * ------------------------------------------------------------------
 * Admin-side preview (owner/admin viewing their own file inline,
 * without needing to create a public share link first)
 * ------------------------------------------------------------------
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

/**
 * ------------------------------------------------------------------
 * Small render helpers
 * ------------------------------------------------------------------
 */
function wfv_ctx_fields( $current_folder_id, $viewing_starred, $view = 'files' ) {
	printf(
		'<input type="hidden" name="ctx_folder" value="%d"><input type="hidden" name="ctx_starred" value="%d"><input type="hidden" name="ctx_view" value="%s">',
		(int) $current_folder_id,
		$viewing_starred ? 1 : 0,
		esc_attr( $view )
	);
}

function wfv_color_swatches( $selected_color ) {
	foreach ( wfv_colors() as $key => $hex ) {
		printf(
			'<label class="wfv-swatch" style="background:%1$s;" title="%2$s"><input type="radio" name="color" value="%2$s" %3$s></label>',
			esc_attr( $hex ),
			esc_attr( $key ),
			checked( $selected_color, $key, false )
		);
	}
}

function wfv_print_folder_options( $all_folders, $selected_id, $parent_id = 0, $depth = 0 ) {
	foreach ( $all_folders as $f ) {
		$f_parent = $f->parent_id ? (int) $f->parent_id : 0;
		if ( $f_parent !== (int) $parent_id ) {
			continue;
		}
		printf(
			'<option value="%d" %s>%s%s</option>',
			(int) $f->id,
			selected( (int) $selected_id, (int) $f->id, false ),
			str_repeat( '&nbsp;&nbsp;', $depth ),
			esc_html( $f->name )
		);
		wfv_print_folder_options( $all_folders, $selected_id, $f->id, $depth + 1 );
	}
}

function wfv_build_breadcrumb( $folder_id, $folders_by_id ) {
	$chain = array();
	$guard = 0;
	while ( $folder_id && isset( $folders_by_id[ $folder_id ] ) && $guard < 50 ) {
		$chain[]   = $folders_by_id[ $folder_id ];
		$folder_id = $folders_by_id[ $folder_id ]->parent_id;
		$guard++;
	}
	return array_reverse( $chain );
}

/**
 * ------------------------------------------------------------------
 * Admin UI
 * ------------------------------------------------------------------
 */
function wfv_render_admin_page() {
	if ( ! wfv_user_has_access() ) {
		echo '<div class="wrap"><h1>File Vault</h1><p>You do not have access to the file vault.</p></div>';
		return;
	}
	global $wpdb;

	$is_admin = current_user_can( 'manage_options' );
	$user_id  = get_current_user_id();

	$notice = isset( $_GET['wfv_notice'] ) ? sanitize_key( $_GET['wfv_notice'] ) : '';
	$msg    = isset( $_GET['wfv_msg'] ) ? sanitize_text_field( rawurldecode( $_GET['wfv_msg'] ) ) : '';
	$open_file = isset( $_GET['wfv_open_file'] ) ? absint( $_GET['wfv_open_file'] ) : 0;

	$viewing_starred   = isset( $_GET['starred'] ) && '1' === (string) $_GET['starred'];
	$current_folder_id = isset( $_GET['folder'] ) ? absint( $_GET['folder'] ) : 0;

	// All folders visible to this user (needed for breadcrumb + move dropdown + grid).
	if ( $is_admin ) {
		$all_folders = $wpdb->get_results( "SELECT * FROM " . wfv_folders_table() . " ORDER BY name ASC" );
	} else {
		$all_folders = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . wfv_folders_table() . " WHERE created_by = %d ORDER BY name ASC", $user_id ) );
	}
	$folders_by_id = array();
	foreach ( $all_folders as $f ) {
		$folders_by_id[ (int) $f->id ] = $f;
	}
	if ( $current_folder_id && ! isset( $folders_by_id[ $current_folder_id ] ) ) {
		$current_folder_id = 0; // not visible to this user — fall back to root
	}

	// Vault-wide stats (independent of which folder we're looking at).
	$stats_files = $is_admin
		? $wpdb->get_results( "SELECT id, file_size FROM " . wfv_files_table() )
		: $wpdb->get_results( $wpdb->prepare( "SELECT id, file_size FROM " . wfv_files_table() . " WHERE uploaded_by = %d", $user_id ) );
	$total_storage = array_sum( wp_list_pluck( $stats_files, 'file_size' ) );
	$file_ids      = wp_list_pluck( $stats_files, 'id' );
	$total_shares  = 0;
	$active_shares = 0;
	$total_downloads = 0;
	if ( ! empty( $file_ids ) ) {
		$placeholders = implode( ',', array_fill( 0, count( $file_ids ), '%d' ) );
		$all_shares   = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . wfv_shares_table() . " WHERE file_id IN ($placeholders)", ...$file_ids ) );
		foreach ( $all_shares as $s ) {
			$total_shares++;
			$total_downloads += (int) $s->download_count;
			list( , $status_key ) = wfv_share_status( $s );
			if ( 'active' === $status_key ) {
				$active_shares++;
			}
		}
	}

	// Current view: subfolders + files.
	if ( $viewing_starred ) {
		$owner_files_sql   = $is_admin ? '1=1' : $wpdb->prepare( 'uploaded_by = %d', $user_id );
		$files             = $wpdb->get_results( "SELECT * FROM " . wfv_files_table() . " WHERE starred = 1 AND ({$owner_files_sql}) ORDER BY created_at DESC" );
		$owner_folders_sql = $is_admin ? '1=1' : $wpdb->prepare( 'created_by = %d', $user_id );
		$subfolders        = $wpdb->get_results( "SELECT * FROM " . wfv_folders_table() . " WHERE starred = 1 AND ({$owner_folders_sql}) ORDER BY name ASC" );
	} else {
		if ( $is_admin ) {
			$files = $current_folder_id
				? $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . wfv_files_table() . " WHERE folder_id = %d ORDER BY created_at DESC", $current_folder_id ) )
				: $wpdb->get_results( "SELECT * FROM " . wfv_files_table() . " WHERE folder_id IS NULL ORDER BY created_at DESC" );
		} else {
			$files = $current_folder_id
				? $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . wfv_files_table() . " WHERE folder_id = %d AND uploaded_by = %d ORDER BY created_at DESC", $current_folder_id, $user_id ) )
				: $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . wfv_files_table() . " WHERE folder_id IS NULL AND uploaded_by = %d ORDER BY created_at DESC", $user_id ) );
		}
		$subfolders = array_values(
			array_filter(
				$all_folders,
				function ( $f ) use ( $current_folder_id ) {
					$f_parent = $f->parent_id ? (int) $f->parent_id : 0;
					return $f_parent === (int) $current_folder_id;
				}
			)
		);
	}

	$breadcrumb  = wfv_build_breadcrumb( $current_folder_id, $folders_by_id );
	$home_url    = home_url( '/' );
	$previewable = wfv_previewable_types();

	// Per-file share data + active-count, for the current view only.
	$shares_by_file = array();
	foreach ( $files as $file ) {
		$shares_by_file[ $file->id ] = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . wfv_shares_table() . " WHERE file_id = %d ORDER BY created_at DESC", $file->id ) );
	}

	$base_admin_url = admin_url( 'admin.php?page=wfv-vault' );
	?>
	<div class="wrap wfv-wrap wfv-app">
		<?php wfv_design_system_css(); ?>
		<?php wfv_render_top_nav( 'files' ); ?>
		<style>
			.wfv-wrap{ max-width:1200px; }
			.wfv-wrap h1{ font-weight:600; }
			.wfv-sub{ color:#646970; margin-top:-8px; margin-bottom:24px; }
			.wfv-stats{ display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:16px; margin-bottom:28px; }
			.wfv-stat-card{ background:#fff; border:1px solid #e2e4e7; border-radius:10px; padding:18px 20px; box-shadow:0 1px 2px rgba(0,0,0,.03); }
			.wfv-stat-card .wfv-stat-value{ font-size:26px; font-weight:600; color:#1d2327; line-height:1.2; }
			.wfv-stat-card .wfv-stat-label{ font-size:12px; text-transform:uppercase; letter-spacing:.04em; color:#8c8f94; margin-top:4px; }
			.wfv-card{ background:#fff; border:1px solid #e2e4e7; border-radius:10px; padding:24px; margin-bottom:24px; box-shadow:0 1px 2px rgba(0,0,0,.03); }
			.wfv-card h2{ margin-top:0; font-size:15px; text-transform:uppercase; letter-spacing:.03em; color:#3c434a; }
			.wfv-dropzone{ border:2px dashed #c3c4c7; border-radius:10px; padding:36px 20px; text-align:center; cursor:pointer; transition:.15s; background:#fafafa; }
			.wfv-dropzone.wfv-drag{ border-color:#2271b1; background:#f0f6fb; }
			.wfv-dropzone .wfv-dz-icon{ font-size:32px; display:block; margin-bottom:8px; }
			.wfv-dropzone .wfv-dz-file{ font-weight:600; color:#2271b1; margin-top:8px; }
			.wfv-toolbar{ display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:10px; }
			.wfv-breadcrumb{ font-size:14px; }
			.wfv-breadcrumb a{ text-decoration:none; }
			.wfv-toolbar-right{ display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
			.wfv-search{ position:relative; }
			.wfv-search input{ padding:6px 12px 6px 30px; border-radius:6px; border:1px solid #c3c4c7; width:240px; }
			.wfv-search:before{ content:"🔍"; position:absolute; left:9px; top:6px; font-size:13px; opacity:.6; }
			.wfv-starred-link{ text-decoration:none; font-size:13px; padding:6px 10px; border:1px solid #c3c4c7; border-radius:6px; background:#fff; }
			.wfv-starred-link.wfv-active{ background:#fff7e0; border-color:#f4b400; color:#9a6b00; }
			.wfv-folder-grid{ display:grid; grid-template-columns:repeat(auto-fill,minmax(190px,1fr)); gap:14px; margin-bottom:22px; }
			.wfv-folder-card{ border:1px solid #e2e4e7; border-radius:10px; padding:12px 14px; background:#fbfbfc; position:relative; }
			.wfv-folder-open{ display:flex; align-items:center; gap:10px; text-decoration:none; color:#1d2327; font-weight:600; }
			.wfv-folder-name{ overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
			.wfv-folder-actions{ display:flex; justify-content:space-between; align-items:center; margin-top:8px; }
			.wfv-star-btn{ background:none; border:0; cursor:pointer; font-size:16px; color:#f4b400; padding:0; line-height:1; }
			.wfv-folder-edit-toggle{ font-size:12px; text-decoration:none; }
			.wfv-folder-edit-panel{ margin-top:10px; padding-top:10px; border-top:1px dashed #dcdcde; }
			.wfv-folder-edit-panel input[type=text]{ width:100%; margin-bottom:8px; }
			.wfv-color-swatches{ display:flex; gap:6px; margin-bottom:8px; flex-wrap:wrap; }
			.wfv-swatch{ width:20px; height:20px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; cursor:pointer; border:2px solid transparent; }
			.wfv-swatch input{ opacity:0; width:1px; height:1px; }
			.wfv-swatch:has(input:checked){ border-color:#1d2327; }
			table.wfv-table{ border-collapse:collapse; width:100%; background:#fff; border:1px solid #e2e4e7; border-radius:10px; overflow:hidden; }
			table.wfv-table th{ text-align:left; background:#f6f7f7; font-size:12px; text-transform:uppercase; letter-spacing:.03em; color:#646970; padding:10px 14px; border-bottom:1px solid #e2e4e7; }
			table.wfv-table td{ padding:12px 14px; border-bottom:1px solid #f0f0f1; vertical-align:middle; font-size:13px; }
			table.wfv-table tr.wfv-file-row:hover{ background:#fafbfc; }
			.wfv-file-name{ font-weight:600; color:#1d2327; }
			.wfv-file-icon{ margin-right:6px; }
			.wfv-shares-row td{ background:#fbfbfc; }
			.wfv-badge{ display:inline-block; padding:2px 10px; border-radius:99px; font-size:11px; font-weight:600; }
			.wfv-badge-active{ background:#edfaef; color:#1a7f37; }
			.wfv-badge-revoked{ background:#fde8e8; color:#c1272d; }
			.wfv-badge-expired{ background:#f0f0f1; color:#646970; }
			.wfv-badge-limit{ background:#fff4e5; color:#b8590a; }
			.wfv-link-input{ font-family:Consolas,Monaco,monospace; font-size:12px; padding:5px 8px; border-radius:5px; border:1px solid #dcdcde; width:230px; background:#fbfbfb; }
			.wfv-copy-btn{ margin-left:4px; }
			.wfv-mini-link{ font-size:11px; margin-left:6px; }
			.wfv-toast{ position:fixed; bottom:24px; right:24px; background:#1d2327; color:#fff; padding:10px 18px; border-radius:6px; font-size:13px; opacity:0; transform:translateY(8px); transition:.2s; pointer-events:none; z-index:9999; }
			.wfv-toast.wfv-show{ opacity:1; transform:translateY(0); }
			.wfv-empty{ text-align:center; padding:40px; color:#8c8f94; }
			.wfv-file-star-btn{ background:none; border:0; cursor:pointer; font-size:15px; color:#f4b400; }
			.wfv-move-select{ font-size:12px; padding:3px; }
			.wfv-actions-cell{ display:flex; flex-wrap:wrap; gap:6px; align-items:center; }
			.wfv-modal{ position:fixed; inset:0; z-index:100000; display:flex; align-items:center; justify-content:center; }
			.wfv-modal-backdrop{ position:absolute; inset:0; background:rgba(0,0,0,.65); }
			.wfv-modal-content{ position:relative; background:#fff; border-radius:10px; max-width:90vw; max-height:90vh; overflow:auto; padding:16px; }
			.wfv-modal-close{ position:absolute; top:6px; right:10px; background:none; border:0; font-size:22px; cursor:pointer; line-height:1; }
			.wfv-modal-content img{ max-width:100%; max-height:80vh; display:block; }
			.wfv-modal-content embed{ width:80vw; height:80vh; }

			/* ---------- Mobile responsive (matches WP admin's own breakpoint) ---------- */
			@media (max-width: 782px) {
				.wfv-wrap{ padding-right:0; }
				.wfv-stats{ grid-template-columns:repeat(2,1fr); gap:10px; }
				.wfv-stat-card{ padding:12px 14px; }
				.wfv-stat-card .wfv-stat-value{ font-size:20px; }
				.wfv-card{ padding:16px; }
				.wfv-toolbar{ flex-direction:column; align-items:stretch; }
				.wfv-toolbar-right{ width:100%; }
				.wfv-search{ width:100%; }
				.wfv-search input{ width:100%; box-sizing:border-box; }
				.wfv-starred-link, #wfv-new-folder-btn{ flex:1; text-align:center; }
				.wfv-folder-grid{ grid-template-columns:repeat(auto-fill,minmax(130px,1fr)); gap:10px; }
				.wfv-dropzone{ padding:24px 12px; }

				/* Reflow the file & shares tables into stacked cards. */
				table.wfv-table thead{ display:none; }
				table.wfv-table, table.wfv-table tbody, table.wfv-table tr, table.wfv-table td{ display:block; width:100%; box-sizing:border-box; }
				table.wfv-table{ border:none; }
				table.wfv-table tr.wfv-file-row{ border:1px solid #e2e4e7; border-radius:8px; margin-bottom:12px; padding:8px 10px; }
				table.wfv-table tr.wfv-shares-row{ border:1px solid #e2e4e7; border-radius:8px; margin-bottom:12px; }
				table.wfv-table td{ border-bottom:0; padding:6px 4px; }
				table.wfv-table td[data-label]:not([data-label=""])::before{
					content:attr(data-label);
					display:block;
					font-size:10px;
					font-weight:700;
					text-transform:uppercase;
					letter-spacing:.03em;
					color:#8c8f94;
					margin-bottom:2px;
				}
				.wfv-actions-cell{ flex-direction:column; align-items:stretch; }
				.wfv-actions-cell .button{ text-align:center; }
				.wfv-link-input{ width:100%; box-sizing:border-box; }
				.wfv-copy-btn{ margin-left:0; margin-top:6px; width:100%; }
				table.wfv-table .wfv-table{ margin-top:8px; }
				.wfv-modal-content{ max-width:96vw; max-height:92vh; padding:12px; }
				.wfv-modal-content embed{ width:88vw; height:70vh; }
			}
			@media (max-width: 480px) {
				.wfv-stats{ grid-template-columns:1fr 1fr; }
			}
		</style>

		<h1>🔐 File Vault</h1>
		<p class="wfv-sub">Store files privately in folders, and hand out unique, revocable share links — never the same URL twice. Looking for your notes? They now live under <a href="<?php echo esc_url( admin_url( 'admin.php?page=wfv-notes' ) ); ?>">📝 My Notes</a> in the sidebar.</p>

		<?php if ( $notice && $msg ) : ?>
			<div class="notice notice-<?php echo esc_attr( $notice ); ?> is-dismissible"><p><?php echo esc_html( $msg ); ?></p></div>
		<?php endif; ?>

		<div class="wfv-stats">
			<div class="wfv-stat-card"><div class="wfv-stat-value"><?php echo count( $stats_files ); ?></div><div class="wfv-stat-label"><?php echo $is_admin ? 'Total files (all users)' : 'Your files'; ?></div></div>
			<div class="wfv-stat-card"><div class="wfv-stat-value"><?php echo esc_html( size_format( $total_storage ) ); ?></div><div class="wfv-stat-label">Storage used</div></div>
			<div class="wfv-stat-card"><div class="wfv-stat-value"><?php echo count( $all_folders ); ?></div><div class="wfv-stat-label">Folders</div></div>
			<div class="wfv-stat-card"><div class="wfv-stat-value"><?php echo (int) $active_shares; ?> / <?php echo (int) $total_shares; ?></div><div class="wfv-stat-label">Active share links</div></div>
			<div class="wfv-stat-card"><div class="wfv-stat-value"><?php echo (int) $total_downloads; ?></div><div class="wfv-stat-label">Total downloads/views</div></div>
		</div>

		<?php if ( ! $viewing_starred ) : ?>
		<div class="wfv-card">
			<h2>Upload a file<?php echo $current_folder_id && isset( $folders_by_id[ $current_folder_id ] ) ? ' to "' . esc_html( $folders_by_id[ $current_folder_id ]->name ) . '"' : ''; ?></h2>
			<form method="post" enctype="multipart/form-data" id="wfv-upload-form">
				<?php wp_nonce_field( 'wfv_upload', 'wfv_upload_nonce' ); wfv_ctx_fields( $current_folder_id, $viewing_starred ); ?>
				<input type="hidden" name="wfv_action" value="upload">
				<input type="hidden" name="folder_id" value="<?php echo (int) $current_folder_id; ?>">
				<div class="wfv-dropzone" id="wfv-dropzone">
					<span class="wfv-dz-icon">⬆️</span>
					<div>Drag &amp; drop a file here, or click to browse</div>
					<div class="wfv-dz-file" id="wfv-dz-filename"></div>
					<input type="file" name="wfv_file" id="wfv-file-input" style="display:none;" required>
				</div>
				<p><button type="submit" class="button button-primary button-hero" id="wfv-upload-btn" disabled>Upload file</button></p>
			</form>
		</div>
		<?php endif; ?>

		<div class="wfv-card">
			<div class="wfv-toolbar">
				<div class="wfv-breadcrumb">
					<?php if ( $viewing_starred ) : ?>
						⭐ <strong>Starred</strong>
					<?php else : ?>
						<a href="<?php echo esc_url( $base_admin_url ); ?>">🏠 Home</a>
						<?php foreach ( $breadcrumb as $bc ) : ?>
							/ <a href="<?php echo esc_url( add_query_arg( 'folder', $bc->id, $base_admin_url ) ); ?>"><?php echo esc_html( $bc->name ); ?></a>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
				<div class="wfv-toolbar-right">
					<div class="wfv-search">
						<input type="text" id="wfv-search-input" placeholder="Search by filename<?php echo $is_admin ? ' or uploader' : ''; ?>…">
					</div>
					<?php if ( $viewing_starred ) : ?>
						<a class="wfv-starred-link wfv-active" href="<?php echo esc_url( $base_admin_url ); ?>">← Back to files</a>
					<?php else : ?>
						<a class="wfv-starred-link" href="<?php echo esc_url( add_query_arg( 'starred', 1, $base_admin_url ) ); ?>">⭐ Starred</a>
						<button type="button" class="button" id="wfv-new-folder-btn">+ New folder</button>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( ! $viewing_starred ) : ?>
			<div id="wfv-new-folder-form" style="display:none; margin-bottom:20px; border:1px dashed #c3c4c7; border-radius:8px; padding:14px;">
				<form method="post">
					<?php wp_nonce_field( 'wfv_create_folder', 'wfv_folder_nonce' ); wfv_ctx_fields( $current_folder_id, $viewing_starred ); ?>
					<input type="hidden" name="wfv_action" value="create_folder">
					<input type="hidden" name="parent_id" value="<?php echo (int) $current_folder_id; ?>">
					<input type="text" name="name" placeholder="Folder name" required style="margin-bottom:8px;">
					<div class="wfv-color-swatches"><?php wfv_color_swatches( 'blue' ); ?></div>
					<button type="submit" class="button button-primary">Create folder</button>
				</form>
			</div>
			<?php endif; ?>

			<?php if ( ! empty( $subfolders ) ) : ?>
				<div class="wfv-folder-grid">
					<?php foreach ( $subfolders as $folder ) : ?>
						<div class="wfv-folder-card">
							<a class="wfv-folder-open" href="<?php echo esc_url( add_query_arg( 'folder', $folder->id, $base_admin_url ) ); ?>">
								<?php echo wfv_folder_icon_svg( $folder->color, 34 ); ?>
								<span class="wfv-folder-name"><?php echo esc_html( $folder->name ); ?></span>
							</a>
							<div class="wfv-folder-actions">
								<form method="post" style="display:inline;">
									<?php wp_nonce_field( 'wfv_toggle_star_folder', 'wfv_star_folder_nonce' ); wfv_ctx_fields( $current_folder_id, $viewing_starred ); ?>
									<input type="hidden" name="wfv_action" value="toggle_star_folder">
									<input type="hidden" name="folder_id" value="<?php echo (int) $folder->id; ?>">
									<button type="submit" class="wfv-star-btn" title="<?php echo $folder->starred ? 'Unstar' : 'Star'; ?>"><?php echo $folder->starred ? '★' : '☆'; ?></button>
								</form>
								<a href="#" class="wfv-folder-edit-toggle" data-folder="<?php echo (int) $folder->id; ?>">Edit</a>
							</div>
							<div class="wfv-folder-edit-panel" id="wfv-folder-edit-<?php echo (int) $folder->id; ?>" style="display:none;">
								<form method="post">
									<?php wp_nonce_field( 'wfv_rename_folder', 'wfv_rename_nonce' ); wfv_ctx_fields( $current_folder_id, $viewing_starred ); ?>
									<input type="hidden" name="wfv_action" value="rename_folder">
									<input type="hidden" name="folder_id" value="<?php echo (int) $folder->id; ?>">
									<input type="text" name="name" value="<?php echo esc_attr( $folder->name ); ?>" required>
									<div class="wfv-color-swatches"><?php wfv_color_swatches( $folder->color ); ?></div>
									<button type="submit" class="button button-primary button-small">Save</button>
								</form>
								<form method="post" onsubmit="return confirm('Delete this folder? Its contents will move up one level.');" style="margin-top:6px;">
									<?php wp_nonce_field( 'wfv_delete_folder', 'wfv_delete_folder_nonce' ); wfv_ctx_fields( $current_folder_id, $viewing_starred ); ?>
									<input type="hidden" name="wfv_action" value="delete_folder">
									<input type="hidden" name="folder_id" value="<?php echo (int) $folder->id; ?>">
									<button type="submit" class="button button-small">Delete folder</button>
								</form>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( empty( $files ) && empty( $subfolders ) ) : ?>
				<div class="wfv-empty"><?php echo $viewing_starred ? 'Nothing starred yet.' : 'This folder is empty — upload a file or create a subfolder.'; ?></div>
			<?php elseif ( ! empty( $files ) ) : ?>
				<table class="wfv-table">
					<thead>
						<tr>
							<th></th>
							<th>File</th>
							<?php if ( $is_admin ) : ?><th>Uploaded by</th><?php endif; ?>
							<th>Size</th>
							<th>Uploaded</th>
							<th>Active shares</th>
							<th style="width:260px;">Actions</th>
						</tr>
					</thead>
					<tbody id="wfv-file-tbody">
					<?php foreach ( $files as $file ) :
						$shares       = $shares_by_file[ $file->id ];
						$active_count = 0;
						foreach ( $shares as $s ) {
							list( , $status_key ) = wfv_share_status( $s );
							if ( 'active' === $status_key ) {
								$active_count++;
							}
						}
						$uploader_name = '';
						if ( $is_admin ) {
							$uploader      = get_userdata( $file->uploaded_by );
							$uploader_name = $uploader ? $uploader->display_name : '';
						}
						$search_blob  = strtolower( $file->original_name . ' ' . $uploader_name );
						$can_preview  = in_array( $file->mime_type, $previewable, true );
						$preview_url  = wp_nonce_url( admin_url( 'admin-post.php?action=wfv_preview&file_id=' . $file->id ), 'wfv_preview_' . $file->id );
						?>
						<tr class="wfv-file-row" data-search="<?php echo esc_attr( $search_blob ); ?>" data-file-id="<?php echo (int) $file->id; ?>">
							<td data-label="Star">
								<form method="post" style="display:inline;">
									<?php wp_nonce_field( 'wfv_toggle_star_file', 'wfv_star_nonce' ); wfv_ctx_fields( $current_folder_id, $viewing_starred ); ?>
									<input type="hidden" name="wfv_action" value="toggle_star_file">
									<input type="hidden" name="file_id" value="<?php echo (int) $file->id; ?>">
									<button type="submit" class="wfv-file-star-btn" title="<?php echo $file->starred ? 'Unstar' : 'Star'; ?>"><?php echo $file->starred ? '★' : '☆'; ?></button>
								</form>
							</td>
							<td class="wfv-file-name" data-label="File"><span class="wfv-file-icon"><?php echo wfv_file_icon( $file->mime_type, $file->original_name ); ?></span><?php echo esc_html( $file->original_name ); ?></td>
							<?php if ( $is_admin ) : ?><td data-label="Uploaded by"><?php echo esc_html( $uploader_name ? $uploader_name : '—' ); ?></td><?php endif; ?>
							<td data-label="Size"><?php echo esc_html( size_format( $file->file_size ) ); ?></td>
							<td data-label="Uploaded"><?php echo esc_html( mysql2date( 'Y-m-d H:i', $file->created_at ) ); ?></td>
							<td data-label="Active shares"><?php echo (int) $active_count; ?></td>
							<td data-label="Actions">
								<div class="wfv-actions-cell">
									<a href="#" class="button wfv-toggle-shares" data-file="<?php echo (int) $file->id; ?>">Shares</a>
									<?php if ( $can_preview ) : ?>
										<button type="button" class="button wfv-preview-btn" data-url="<?php echo esc_url( $preview_url ); ?>" data-mime="<?php echo esc_attr( $file->mime_type ); ?>">👁 Preview</button>
									<?php endif; ?>
									<form method="post" style="display:inline;">
										<?php wp_nonce_field( 'wfv_move_file', 'wfv_move_nonce' ); wfv_ctx_fields( $current_folder_id, $viewing_starred ); ?>
										<input type="hidden" name="wfv_action" value="move_file">
										<input type="hidden" name="file_id" value="<?php echo (int) $file->id; ?>">
										<select name="folder_id" class="wfv-move-select" onchange="this.form.submit()">
											<option value="0" <?php selected( (int) $file->folder_id, 0 ); ?>>📁 Root</option>
											<?php wfv_print_folder_options( $all_folders, (int) $file->folder_id ); ?>
										</select>
									</form>
									<form method="post" style="display:inline" onsubmit="return confirm('Delete this file and all its share links? This cannot be undone.');">
										<?php wp_nonce_field( 'wfv_delete_file', 'wfv_delete_nonce' ); wfv_ctx_fields( $current_folder_id, $viewing_starred ); ?>
										<input type="hidden" name="wfv_action" value="delete_file">
										<input type="hidden" name="file_id" value="<?php echo (int) $file->id; ?>">
										<button type="submit" class="button">Delete</button>
									</form>
								</div>
							</td>
						</tr>
						<tr class="wfv-shares-row" data-file-id="<?php echo (int) $file->id; ?>" id="wfv-shares-<?php echo (int) $file->id; ?>" style="<?php echo ( $open_file === (int) $file->id ) ? '' : 'display:none;'; ?>">
							<td colspan="<?php echo $is_admin ? '7' : '6'; ?>">
								<h4>Create a new share link</h4>
								<form method="post" style="margin-bottom:1.2em;">
									<?php wp_nonce_field( 'wfv_create_share', 'wfv_share_nonce' ); wfv_ctx_fields( $current_folder_id, $viewing_starred ); ?>
									<input type="hidden" name="wfv_action" value="create_share">
									<input type="hidden" name="file_id" value="<?php echo (int) $file->id; ?>">
									<table class="form-table">
										<tr>
											<th>Label (for you)</th>
											<td><input type="text" name="label" placeholder="e.g. For Priya"></td>
										</tr>
										<tr>
											<th>Password (optional)</th>
											<td><input type="text" name="password" placeholder="Leave blank for none"></td>
										</tr>
										<tr>
											<th>Expires after (days)</th>
											<td><input type="number" name="expiry_days" min="0" placeholder="0 = never"></td>
										</tr>
										<tr>
											<th>Max downloads</th>
											<td><input type="number" name="max_downloads" min="0" placeholder="0 = unlimited"></td>
										</tr>
									</table>
									<button type="submit" class="button button-primary">Generate link</button>
								</form>

								<h4>Existing share links</h4>
								<?php if ( empty( $shares ) ) : ?>
									<p>No share links yet for this file.</p>
								<?php else : ?>
									<table class="wfv-table">
										<thead>
											<tr>
												<th>Label</th>
												<th>Link</th>
												<th>Password</th>
												<th>Expires</th>
												<th>Downloads</th>
												<th>Status</th>
												<th></th>
											</tr>
										</thead>
										<tbody>
										<?php foreach ( $shares as $s ) :
											$link = add_query_arg( 'wfv_token', $s->token, $home_url );
											list( $status_label, $status_key ) = wfv_share_status( $s );
											?>
											<tr>
												<td data-label="Label"><?php echo esc_html( $s->label ? $s->label : '—' ); ?></td>
												<td data-label="Link">
													<input type="text" class="wfv-link-input" readonly value="<?php echo esc_url( $link ); ?>" onclick="this.select();">
													<button type="button" class="button wfv-copy-btn" onclick="wfvCopyLink(this)">Copy</button>
													<?php if ( $can_preview ) : ?>
														<br><a href="<?php echo esc_url( add_query_arg( 'dl', '1', $link ) ); ?>" class="wfv-mini-link">force download instead of preview</a>
													<?php endif; ?>
												</td>
												<td data-label="Password"><?php echo $s->password_hash ? 'Yes' : 'No'; ?></td>
												<td data-label="Expires"><?php echo $s->expires_at ? esc_html( mysql2date( 'Y-m-d', $s->expires_at ) ) : 'Never'; ?></td>
												<td data-label="Downloads"><?php echo (int) $s->download_count . ( $s->max_downloads ? ' / ' . (int) $s->max_downloads : '' ); ?></td>
												<td data-label="Status"><span class="wfv-badge wfv-badge-<?php echo esc_attr( $status_key ); ?>"><?php echo esc_html( $status_label ); ?></span></td>
												<td data-label="">
													<?php if ( ! $s->revoked ) : ?>
													<form method="post" style="display:inline" onsubmit="return confirm('Revoke this link? It will stop working immediately.');">
														<?php wp_nonce_field( 'wfv_revoke_share', 'wfv_revoke_nonce' ); wfv_ctx_fields( $current_folder_id, $viewing_starred ); ?>
														<input type="hidden" name="wfv_action" value="revoke_share">
														<input type="hidden" name="share_id" value="<?php echo (int) $s->id; ?>">
														<input type="hidden" name="file_id" value="<?php echo (int) $file->id; ?>">
														<button type="submit" class="button">Revoke</button>
													</form>
													<?php endif; ?>
												</td>
											</tr>
										<?php endforeach; ?>
										</tbody>
									</table>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</div>

	<div class="wfv-toast" id="wfv-toast"></div>

	<div class="wfv-modal" id="wfv-modal" style="display:none;">
		<div class="wfv-modal-backdrop" id="wfv-modal-backdrop"></div>
		<div class="wfv-modal-content">
			<button type="button" class="wfv-modal-close" id="wfv-modal-close">&times;</button>
			<div id="wfv-modal-body"></div>
		</div>
	</div>

	<script>
	(function(){
		// Accordion toggle for the shares sub-row.
		document.querySelectorAll('.wfv-toggle-shares').forEach(function(btn){
			btn.addEventListener('click', function(e){
				e.preventDefault();
				var row = document.getElementById('wfv-shares-' + btn.dataset.file);
				row.style.display = (row.style.display === 'none') ? '' : 'none';
			});
		});

		// Folder edit panel toggle.
		document.querySelectorAll('.wfv-folder-edit-toggle').forEach(function(btn){
			btn.addEventListener('click', function(e){
				e.preventDefault();
				var panel = document.getElementById('wfv-folder-edit-' + btn.dataset.folder);
				panel.style.display = (panel.style.display === 'none') ? '' : 'none';
			});
		});

		// New folder form toggle.
		var newFolderBtn = document.getElementById('wfv-new-folder-btn');
		if ( newFolderBtn ) {
			newFolderBtn.addEventListener('click', function(){
				var form = document.getElementById('wfv-new-folder-form');
				form.style.display = (form.style.display === 'none') ? '' : 'none';
			});
		}

		// Live filename/uploader search.
		var searchInput = document.getElementById('wfv-search-input');
		if ( searchInput ) {
			searchInput.addEventListener('input', function(){
				var q = this.value.trim().toLowerCase();
				document.querySelectorAll('.wfv-file-row').forEach(function(row){
					var match = row.dataset.search.indexOf(q) !== -1;
					row.style.display = match ? '' : 'none';
					var sharesRow = document.getElementById('wfv-shares-' + row.dataset.fileId);
					if ( sharesRow && ! match ) {
						sharesRow.style.display = 'none';
					}
				});
			});
		}

		// Drag & drop upload zone.
		var dz = document.getElementById('wfv-dropzone');
		var fileInput = document.getElementById('wfv-file-input');
		var fileLabel = document.getElementById('wfv-dz-filename');
		var uploadBtn = document.getElementById('wfv-upload-btn');
		if ( dz && fileInput ) {
			dz.addEventListener('click', function(){ fileInput.click(); });
			fileInput.addEventListener('change', function(){
				if ( fileInput.files.length ) {
					fileLabel.textContent = fileInput.files[0].name;
					uploadBtn.disabled = false;
				}
			});
			[ 'dragenter', 'dragover' ].forEach(function(evt){
				dz.addEventListener(evt, function(e){ e.preventDefault(); dz.classList.add('wfv-drag'); });
			});
			[ 'dragleave', 'drop' ].forEach(function(evt){
				dz.addEventListener(evt, function(e){ e.preventDefault(); dz.classList.remove('wfv-drag'); });
			});
			dz.addEventListener('drop', function(e){
				if ( e.dataTransfer.files.length ) {
					fileInput.files = e.dataTransfer.files;
					fileLabel.textContent = e.dataTransfer.files[0].name;
					uploadBtn.disabled = false;
				}
			});
		}

		// Admin preview modal.
		document.querySelectorAll('.wfv-preview-btn').forEach(function(btn){
			btn.addEventListener('click', function(){
				var url = btn.dataset.url, mime = btn.dataset.mime;
				var body = document.getElementById('wfv-modal-body');
				body.innerHTML = '';
				if ( mime.indexOf('image/') === 0 ) {
					var img = document.createElement('img');
					img.src = url;
					body.appendChild(img);
				} else if ( mime === 'application/pdf' ) {
					var embed = document.createElement('embed');
					embed.src = url;
					embed.type = 'application/pdf';
					body.appendChild(embed);
				}
				document.getElementById('wfv-modal').style.display = 'flex';
			});
		});
		function wfvCloseModal(){
			document.getElementById('wfv-modal').style.display = 'none';
			document.getElementById('wfv-modal-body').innerHTML = '';
		}
		document.getElementById('wfv-modal-close').addEventListener('click', wfvCloseModal);
		document.getElementById('wfv-modal-backdrop').addEventListener('click', wfvCloseModal);
	})();

	function wfvCopyLink(btn){
		var input = btn.previousElementSibling;
		input.select();
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText(input.value);
		} else {
			document.execCommand('copy');
		}
		var toast = document.getElementById('wfv-toast');
		toast.textContent = 'Link copied to clipboard';
		toast.classList.add('wfv-show');
		setTimeout(function(){ toast.classList.remove('wfv-show'); }, 1800);
	}
	</script>
	<?php
}

/**
 * ------------------------------------------------------------------
 * My Notes — a separate top-level menu, entirely private to each
 * author. Even site admins do not see other users' notes here.
 * Supports tags, several sort modes, drag-and-drop manual order, and
 * a full-width rich-text (TinyMCE) editor for writing/editing.
 * ------------------------------------------------------------------
 */
function wfv_render_notes_page() {
	if ( ! wfv_user_has_access() ) {
		echo '<div class="wrap"><h1>My Notes</h1><p>You do not have access to notes.</p></div>';
		return;
	}
	global $wpdb;
	$user_id = get_current_user_id();

	$notice = isset( $_GET['wfv_notice'] ) ? sanitize_key( $_GET['wfv_notice'] ) : '';
	$msg    = isset( $_GET['wfv_msg'] ) ? sanitize_text_field( rawurldecode( $_GET['wfv_msg'] ) ) : '';

	$valid_sorts = array( 'custom', 'newest', 'oldest', 'title_asc', 'title_desc' );
	$sort        = isset( $_GET['sort'] ) ? sanitize_key( $_GET['sort'] ) : 'custom';
	if ( ! in_array( $sort, $valid_sorts, true ) ) {
		$sort = 'custom';
	}
	switch ( $sort ) {
		case 'newest':
			$order_sql = 'pinned DESC, updated_at DESC';
			break;
		case 'oldest':
			$order_sql = 'pinned DESC, updated_at ASC';
			break;
		case 'title_asc':
			$order_sql = 'pinned DESC, title ASC';
			break;
		case 'title_desc':
			$order_sql = 'pinned DESC, title DESC';
			break;
		default:
			$order_sql = 'pinned DESC, sort_order ASC, id ASC';
			break;
	}

	$notes = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . wfv_notes_table() . " WHERE created_by = %d ORDER BY {$order_sql}", $user_id ) );

	$pinned_count = 0;
	$all_tags     = array();
	$notes_data   = array(); // id => {title, content, tags, color, updated} for the JS detail pane
	foreach ( $notes as $n ) {
		if ( $n->pinned ) {
			$pinned_count++;
		}
		if ( $n->tags ) {
			foreach ( array_map( 'trim', explode( ',', $n->tags ) ) as $t ) {
				if ( '' !== $t ) {
					$all_tags[ $t ] = true;
				}
			}
		}
		$notes_data[ $n->id ] = array(
			'title'   => (string) $n->title,
			'content' => (string) $n->content,
			'tags'    => (string) $n->tags,
			'color'   => (string) $n->color,
			'pinned'  => (int) $n->pinned,
			'updated' => mysql2date( 'M j, Y', $n->updated_at ),
		);
	}
	$all_tags = array_keys( $all_tags );
	sort( $all_tags, SORT_STRING | SORT_FLAG_CASE );

	$colors        = wfv_colors();
	$reorder_nonce = wp_create_nonce( 'wfv_reorder_notes' );
	?>
	<div class="wrap wfv-wrap wfv-notes-wrap wfv-app">
		<?php wfv_design_system_css(); ?>
		<?php wfv_render_top_nav( 'notes' ); ?>
		<style>
			.wfv-notes-wrap{ max-width:1280px; }
		</style>

		<h1>📝 My Notes</h1>
		<p class="wfv-sub" style="margin-bottom:16px;">A private notebook just for you — not visible to anyone else, including site admins.</p>

		<?php if ( $notice && $msg ) : ?>
			<div class="notice notice-<?php echo esc_attr( $notice ); ?> is-dismissible"><p><?php echo esc_html( $msg ); ?></p></div>
		<?php endif; ?>

		<div class="wfv-split">
			<div class="wfv-split-sidebar">
				<div class="wfv-split-search"><input type="text" id="wfv-notes-search" placeholder="Search notes or tags…"></div>
				<div class="wfv-split-tabs">
					<button type="button" class="wfv-tab-active" data-list-tab="all">All Notes (<?php echo count( $notes ); ?>)</button>
					<button type="button" data-list-tab="pinned">📌 Pinned (<?php echo (int) $pinned_count; ?>)</button>
				</div>
				<div class="wfv-split-sort">
					<form method="get" id="wfv-notes-sort-form">
						<input type="hidden" name="page" value="wfv-notes">
						<select name="sort" onchange="this.form.submit()">
							<option value="custom" <?php selected( $sort, 'custom' ); ?>>Custom order (drag to sort)</option>
							<option value="newest" <?php selected( $sort, 'newest' ); ?>>Newest first</option>
							<option value="oldest" <?php selected( $sort, 'oldest' ); ?>>Oldest first</option>
							<option value="title_asc" <?php selected( $sort, 'title_asc' ); ?>>Title A–Z</option>
							<option value="title_desc" <?php selected( $sort, 'title_desc' ); ?>>Title Z–A</option>
						</select>
					</form>
				</div>
				<?php if ( ! empty( $all_tags ) ) : ?>
					<div class="wfv-split-tagrow" id="wfv-notes-tag-filters">
						<?php foreach ( $all_tags as $tag ) : ?>
							<button type="button" data-tag="<?php echo esc_attr( sanitize_title( $tag ) ); ?>"><?php echo esc_html( $tag ); ?></button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<button type="button" class="wfv-split-add" id="wfv-add-note-trigger">+ New note</button>
				<div class="wfv-split-list" id="wfv-notes-list">
					<?php if ( empty( $notes ) ) : ?>
						<div class="wfv-split-empty-list">No notes yet — click above to write your first one.</div>
					<?php endif; ?>
					<?php foreach ( $notes as $n ) :
						$hex         = isset( $colors[ $n->color ] ) ? $colors[ $n->color ] : $colors['yellow'];
						$tag_slugs   = $n->tags ? implode( ' ', array_map( 'sanitize_title', array_map( 'trim', explode( ',', $n->tags ) ) ) ) : '';
						$snippet     = wp_trim_words( wp_strip_all_tags( $n->content ), 8, '…' );
						$search_blob = strtolower( $n->title . ' ' . wp_strip_all_tags( $n->content ) . ' ' . $n->tags );
						?>
						<div class="wfv-split-item" data-id="<?php echo (int) $n->id; ?>" data-pinned="<?php echo (int) $n->pinned; ?>" data-search="<?php echo esc_attr( $search_blob ); ?>" data-tags="<?php echo esc_attr( $tag_slugs ); ?>" draggable="<?php echo 'custom' === $sort ? 'true' : 'false'; ?>">
							<span class="wfv-item-icon" style="background:<?php echo esc_attr( $hex ); ?>;"><?php echo esc_html( strtoupper( substr( $n->title ? $n->title : '?', 0, 1 ) ) ); ?></span>
							<div class="wfv-item-text"><strong><?php echo esc_html( $n->title ? $n->title : '(untitled)' ); ?></strong><span><?php echo esc_html( $snippet ); ?></span></div>
							<?php if ( $n->pinned ) : ?><span class="wfv-item-star">📌</span><?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="wfv-split-detail">
				<div class="wfv-detail-empty" id="wfv-note-detail-empty">
					<div class="icon">📝</div>
					<p>Select a note on the left, or write a new one.</p>
				</div>

				<div id="wfv-note-detail-content" style="display:none;">
					<div class="wfv-detail-header">
						<span class="wfv-detail-icon" id="wfv-note-detail-icon"></span>
						<div>
							<h2 id="wfv-note-detail-title-text">Title</h2>
							<div class="wfv-detail-sub" id="wfv-note-detail-updated"></div>
						</div>
						<div class="wfv-detail-header-actions">
							<form method="post" id="wfv-note-pin-form" style="margin:0;">
								<?php wp_nonce_field( 'wfv_toggle_pin_note', 'wfv_pin_nonce' ); ?>
								<input type="hidden" name="wfv_action" value="toggle_pin_note">
								<input type="hidden" name="ctx_view" value="notes">
								<input type="hidden" name="note_id" id="wfv-note-pin-id" value="">
								<button type="submit" class="wfv-icon-btn" id="wfv-note-pin-btn" style="font-size:18px;" title="Pin">📌</button>
							</form>
						</div>
					</div>

					<form id="wfv-note-detail-form" method="post">
						<?php wp_nonce_field( 'wfv_create_note', 'wfv_note_nonce' ); ?>
						<?php wp_nonce_field( 'wfv_update_note', 'wfv_note_update_nonce' ); ?>
						<input type="hidden" name="ctx_view" value="notes">
						<input type="hidden" name="wfv_action" id="wfv-note-form-action" value="create_note">
						<input type="hidden" name="note_id" id="wfv-note-form-id" value="">

						<label class="wfv-field-label" for="wfv-note-title-field">Title</label>
						<input type="text" name="title" id="wfv-note-title-field" class="wfv-detail-input" placeholder="Untitled note">

						<label class="wfv-field-label" for="wfv_editor">Content</label>
						<textarea name="content" id="wfv_editor" rows="12" style="max-width:640px;"></textarea>

						<label class="wfv-field-label" for="wfv-note-tags-field">Tags</label>
						<input type="text" name="tags" id="wfv-note-tags-field" class="wfv-detail-input" placeholder="Tags, comma separated (e.g. work, ideas)">

						<label class="wfv-field-label">Color</label>
						<div class="wfv-color-swatches" id="wfv-note-color-swatches"><?php wfv_color_swatches( 'yellow' ); ?></div>

						<div class="wfv-detail-footer">
							<button type="button" class="button" id="wfv-note-delete-btn" style="color:#c1272d;">Delete</button>
							<button type="submit" class="button button-primary">Save note</button>
						</div>
					</form>
				</div>

				<!-- Hidden delete form, submitted via JS after confirm() -->
				<form method="post" id="wfv-note-delete-form" style="display:none;">
					<?php wp_nonce_field( 'wfv_delete_note', 'wfv_note_delete_nonce' ); ?>
					<input type="hidden" name="wfv_action" value="delete_note">
					<input type="hidden" name="ctx_view" value="notes">
					<input type="hidden" name="note_id" id="wfv-note-delete-id" value="">
				</form>
			</div>
		</div>
	</div>

	<div class="wfv-toast" id="wfv-note-toast"></div>

	<script>
	(function(){
		var wfvNotesData    = <?php echo wp_json_encode( $notes_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS ); ?>;
		var wfvColors       = <?php echo wp_json_encode( $colors ); ?>;
		var wfvReorderNonce = <?php echo wp_json_encode( $reorder_nonce ); ?>;
		var editorReady     = false;

		var emptyState = document.getElementById('wfv-note-detail-empty');
		var content    = document.getElementById('wfv-note-detail-content');
		var toast      = document.getElementById('wfv-note-toast');
		var form       = document.getElementById('wfv-note-detail-form');
		var actionInput = document.getElementById('wfv-note-form-action');
		var idInput      = document.getElementById('wfv-note-form-id');
		var titleInput   = document.getElementById('wfv-note-title-field');
		var tagsInput    = document.getElementById('wfv-note-tags-field');
		var editorTextarea = document.getElementById('wfv_editor');
		var pinBtn  = document.getElementById('wfv-note-pin-btn');
		var pinIdField = document.getElementById('wfv-note-pin-id');
		var deleteBtn = document.getElementById('wfv-note-delete-btn');

		function showToast(text){
			toast.textContent = text;
			toast.classList.add('wfv-show');
			setTimeout(function(){ toast.classList.remove('wfv-show'); }, 1800);
		}
		function initials(title){ return (title || '?').substr(0,1).toUpperCase(); }
		function setColor(colorKey){
			document.querySelectorAll('#wfv-note-color-swatches input[type=radio]').forEach(function(input){
				input.checked = ( input.value === colorKey );
			});
		}
		function setActiveListItem(id){
			document.querySelectorAll('.wfv-split-item').forEach(function(el){
				el.classList.toggle('wfv-item-active', el.dataset.id === String(id));
			});
		}

		function initEditor( html ){
			if ( editorReady && window.wp && wp.editor ) {
				// Removing the old editor instance flushes its (old) content back
				// into the textarea first — so we must set the new value AFTER
				// this, not before, or it gets immediately overwritten again.
				wp.editor.remove( 'wfv_editor' );
				editorReady = false;
			}
			editorTextarea.value = html || '';
			if ( window.wp && wp.editor ) {
				wp.editor.initialize( 'wfv_editor', {
					tinymce: {
						wpautop: true,
						menubar: false,
						branding: false,
						statusbar: false,
						height: 300,
						plugins: 'lists,link,textcolor,paste,wordpress,wplink,charmap,hr',
						toolbar1: 'formatselect,bold,italic,underline,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,forecolor,removeformat,undo,redo'
					},
					quicktags: { buttons: 'strong,em,link,ul,ol,li,close' },
					mediaButtons: false
				} );
				editorReady = true;
			}
		}
		function syncEditor(){
			if ( window.tinymce && tinymce.get( 'wfv_editor' ) ) { tinymce.triggerSave(); }
		}

		function showEmptyState(){
			emptyState.style.display = '';
			content.style.display = 'none';
			if ( editorReady ) { wp.editor.remove( 'wfv_editor' ); editorReady = false; }
		}
		function openCreateNote(){
			emptyState.style.display = 'none';
			content.style.display = '';
			setActiveListItem(null);

			document.getElementById('wfv-note-detail-title-text').textContent = 'New note';
			document.getElementById('wfv-note-detail-updated').textContent = '';
			var icon = document.getElementById('wfv-note-detail-icon');
			icon.textContent = '＋';
			icon.style.background = '#94a3b8';
			actionInput.value = 'create_note';
			idInput.value = '';
			titleInput.value = '';
			tagsInput.value = '';
			setColor('yellow');
			pinBtn.style.display = 'none';
			deleteBtn.style.display = 'none';
			initEditor('');
			titleInput.focus();
		}
		function openNote(id){
			var data = wfvNotesData[id];
			if ( ! data ) { return; }
			emptyState.style.display = 'none';
			content.style.display = '';
			setActiveListItem(id);

			document.getElementById('wfv-note-detail-title-text').textContent = data.title || '(untitled)';
			document.getElementById('wfv-note-detail-updated').textContent = 'Updated ' + data.updated;
			var icon = document.getElementById('wfv-note-detail-icon');
			icon.textContent = initials(data.title);
			icon.style.background = wfvColors[data.color] || wfvColors.yellow;

			actionInput.value = 'update_note';
			idInput.value = id;
			titleInput.value = data.title;
			tagsInput.value = data.tags;
			setColor(data.color);

			pinBtn.style.display = '';
			pinBtn.style.opacity = data.pinned ? '1' : '.4';
			pinBtn.title = data.pinned ? 'Unpin' : 'Pin';
			pinIdField.value = id;
			deleteBtn.style.display = '';
			deleteBtn.dataset.id = id;

			initEditor( data.content );
		}

		document.getElementById('wfv-add-note-trigger').addEventListener('click', openCreateNote);
		document.querySelectorAll('.wfv-split-item').forEach(function(el){
			el.addEventListener('click', function(){ openNote( el.dataset.id ); });
		});
		deleteBtn.addEventListener('click', function(){
			if ( ! deleteBtn.dataset.id ) { return; }
			if ( ! confirm('Delete this note? This cannot be undone.') ) { return; }
			document.getElementById('wfv-note-delete-id').value = deleteBtn.dataset.id;
			document.getElementById('wfv-note-delete-form').submit();
		});
		form.addEventListener('submit', syncEditor);

		// Combined search + pinned/all + tag filter.
		var activeTag = null;
		var activeListTab = 'all';
		function applyFilters(){
			var q = (document.getElementById('wfv-notes-search') || {}).value || '';
			q = q.trim().toLowerCase();
			document.querySelectorAll('.wfv-split-item').forEach(function(el){
				var textMatch = ! q || el.dataset.search.indexOf(q) !== -1;
				var tagList = ' ' + el.dataset.tags + ' ';
				var tagMatch = ! activeTag || tagList.indexOf(' ' + activeTag + ' ') !== -1;
				var pinMatch = ( activeListTab === 'all' ) || el.dataset.pinned === '1';
				el.style.display = ( textMatch && tagMatch && pinMatch ) ? '' : 'none';
			});
		}
		var searchInput = document.getElementById('wfv-notes-search');
		if ( searchInput ) { searchInput.addEventListener('input', applyFilters); }
		document.querySelectorAll('.wfv-split-tabs [data-list-tab]').forEach(function(btn){
			btn.addEventListener('click', function(){
				document.querySelectorAll('.wfv-split-tabs [data-list-tab]').forEach(function(b){ b.classList.remove('wfv-tab-active'); });
				btn.classList.add('wfv-tab-active');
				activeListTab = btn.dataset.listTab;
				applyFilters();
			});
		});
		document.querySelectorAll('#wfv-notes-tag-filters button').forEach(function(chip){
			chip.addEventListener('click', function(){
				var tag = chip.dataset.tag;
				if ( activeTag === tag ) {
					activeTag = null;
					chip.classList.remove('wfv-tag-active');
				} else {
					document.querySelectorAll('#wfv-notes-tag-filters button').forEach(function(c){ c.classList.remove('wfv-tag-active'); });
					activeTag = tag;
					chip.classList.add('wfv-tag-active');
				}
				applyFilters();
			});
		});

		// Drag-and-drop manual reordering (swap-on-drop), only in Custom order mode.
		var list = document.getElementById('wfv-notes-list');
		var dragSrc = null;
		list.querySelectorAll('.wfv-split-item[draggable="true"]').forEach(function(item){
			item.addEventListener('dragstart', function(e){
				dragSrc = item;
				item.classList.add('wfv-dragging');
				e.stopPropagation();
			});
			item.addEventListener('dragend', function(){ item.classList.remove('wfv-dragging'); dragSrc = null; });
			item.addEventListener('dragover', function(e){ e.preventDefault(); });
			item.addEventListener('drop', function(e){
				e.preventDefault();
				e.stopPropagation();
				if ( ! dragSrc || dragSrc === item ) { return; }
				var srcNext = dragSrc.nextSibling;
				var tgtNext = item.nextSibling;
				list.insertBefore(dragSrc, tgtNext);
				list.insertBefore(item, srcNext);
				saveNoteOrder();
			});
		});
		function saveNoteOrder(){
			var ids = Array.prototype.map.call( list.querySelectorAll('.wfv-split-item'), function(c){ return c.dataset.id; } );
			var params = new URLSearchParams();
			params.append('action', 'wfv_reorder_notes');
			params.append('nonce', wfvReorderNonce);
			ids.forEach(function(id){ params.append('order[]', id); });
			fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: params }).catch(function(){});
		}
	})();
	</script>
	<?php
}


/**
 * ------------------------------------------------------------------
 * Password Manager — a separate top-level menu. Entries are AES-256
 * encrypted at rest and strictly private to their own author; there
 * is no admin override anywhere in this module. Plaintext secrets
 * are never embedded in the page — they're fetched on demand via a
 * small AJAX call only when you Reveal, Copy, or open Edit.
 * ------------------------------------------------------------------
 */
/**
 * Full-screen "unlock your vault" gate — shown instead of the password
 * list whenever the user has a master password set up but hasn't
 * unlocked it yet this session.
 */
function wfv_render_vault_lock_screen() {
	$notice = isset( $_GET['wfv_notice'] ) ? sanitize_key( $_GET['wfv_notice'] ) : '';
	$msg    = isset( $_GET['wfv_msg'] ) ? sanitize_text_field( rawurldecode( $_GET['wfv_msg'] ) ) : '';
	?>
	<div class="wrap wfv-lock-wrap wfv-app">
		<?php wfv_design_system_css(); ?>
		<?php wfv_render_top_nav( 'passwords' ); ?>
		<style>
			.wfv-lock-wrap{ max-width:1200px; }
			#wfv-lock-screen{ --wfv-brand:#4f46e5; --wfv-brand-dark:#3730a3; --wfv-slate:#475569; min-height:60vh; display:flex; align-items:center; justify-content:center; padding:40px 16px; }
			.wfv-lock-card{ background:#fff; border:1px solid #e2e8f0; border-radius:16px; padding:40px 36px; max-width:400px; width:100%; text-align:center; box-shadow:0 1px 3px rgba(15,23,42,.06), 0 12px 32px rgba(15,23,42,.08); }
			.wfv-lock-icon{ width:56px; height:56px; border-radius:50%; background:linear-gradient(135deg,var(--wfv-brand),var(--wfv-brand-dark)); display:flex; align-items:center; justify-content:center; font-size:24px; margin:0 auto 18px; box-shadow:0 8px 20px rgba(79,70,229,.35); }
			.wfv-lock-card h1{ font-size:20px; margin:0 0 6px; color:#0f172a; }
			.wfv-lock-card p.wfv-lock-sub{ color:var(--wfv-slate); font-size:13.5px; margin:0 0 22px; line-height:1.5; }
			.wfv-lock-field{ text-align:left; margin-bottom:16px; }
			.wfv-lock-field label{ display:block; font-size:12px; font-weight:600; color:#334155; margin-bottom:5px; }
			.wfv-lock-field input{ width:100%; box-sizing:border-box; padding:11px 12px; border:1px solid #e2e8f0; border-radius:9px; font-size:14px; }
			.wfv-lock-field input:focus{ outline:2px solid var(--wfv-brand); outline-offset:1px; border-color:var(--wfv-brand); }
			.wfv-lock-submit{ width:100%; background:var(--wfv-brand); border-color:var(--wfv-brand); color:#fff; border-radius:9px; padding:11px; font-weight:600; font-size:14px; }
			.wfv-lock-submit:hover{ background:var(--wfv-brand-dark); border-color:var(--wfv-brand-dark); color:#fff; }
			.wfv-lock-error{ background:#fef3f2; border:1px solid #fecdca; color:#b42318; border-radius:8px; padding:10px 14px; font-size:12.5px; margin-bottom:18px; text-align:left; }
			.wfv-lock-foot{ font-size:11.5px; color:#94a3b8; margin-top:18px; line-height:1.5; }
		</style>
		<div id="wfv-lock-screen">
			<div class="wfv-lock-card">
				<div class="wfv-lock-icon">🔒</div>
				<h1>Vault locked</h1>
				<p class="wfv-lock-sub">Enter your master password to unlock your encrypted passwords for this session.</p>

				<?php if ( 'error' === $notice && $msg ) : ?>
					<div class="wfv-lock-error"><?php echo esc_html( $msg ); ?></div>
				<?php endif; ?>

				<form method="post">
					<?php wp_nonce_field( 'wfv_unlock_vault', 'wfv_unlock_nonce' ); ?>
					<input type="hidden" name="wfv_action" value="unlock_vault">
					<input type="hidden" name="ctx_view" value="passwords">
					<div class="wfv-lock-field">
						<label for="wfv-unlock-input">Master password</label>
						<input type="password" name="master_password" id="wfv-unlock-input" autocomplete="current-password" autofocus required>
					</div>
					<button type="submit" class="button wfv-lock-submit">Unlock vault</button>
				</form>
				<p class="wfv-lock-foot">This unlocks your vault for about 20 minutes of activity, then locks again automatically. Forgot it? There's no recovery — see your <a href="<?php echo esc_url( admin_url( 'profile.php#wfv-master-pw-section' ) ); ?>">profile page</a> for details.</p>
			</div>
		</div>
	</div>
	<?php
}


/**
 * ------------------------------------------------------------------
 * Passwords — LastPass-style split view: a searchable item list on
 * the left, full details (and inline editing) on the right. Nothing
 * about the backend actions changed here, only how they're presented:
 * one persistent edit form lives in the detail pane and gets repopulated
 * by JS whenever a different item is selected, using the same nonces
 * and action names as before.
 * ------------------------------------------------------------------
 */
function wfv_render_passwords_page() {
	if ( ! wfv_user_has_access() ) {
		echo '<div class="wrap"><h1>Passwords</h1><p>You do not have access to the password manager.</p></div>';
		return;
	}
	global $wpdb;
	$user_id = get_current_user_id();

	$master_on = wfv_pm_master_enabled( $user_id );
	if ( $master_on && null === wfv_pm_get_session_key() ) {
		wfv_render_vault_lock_screen();
		return;
	}

	$notice = isset( $_GET['wfv_notice'] ) ? sanitize_key( $_GET['wfv_notice'] ) : '';
	$msg    = isset( $_GET['wfv_msg'] ) ? sanitize_text_field( rawurldecode( $_GET['wfv_msg'] ) ) : '';

	$entries = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . wfv_passwords_table() . " WHERE created_by = %d ORDER BY starred DESC, title ASC", $user_id ) );

	$all_tags   = array();
	$entry_data = array(); // id => non-secret fields, for instant detail-pane prefill
	foreach ( $entries as $e ) {
		if ( $e->tags ) {
			foreach ( array_map( 'trim', explode( ',', $e->tags ) ) as $t ) {
				if ( '' !== $t ) {
					$all_tags[ $t ] = true;
				}
			}
		}
		$entry_data[ $e->id ] = array(
			'title'    => (string) $e->title,
			'username' => (string) $e->username,
			'url'      => (string) $e->url,
			'tags'     => (string) $e->tags,
			'color'    => (string) $e->color,
			'starred'  => (int) $e->starred,
			'updated'  => mysql2date( 'M j, Y', $e->updated_at ),
		);
	}
	$all_tags = array_keys( $all_tags );
	sort( $all_tags, SORT_STRING | SORT_FLAG_CASE );

	$shared_with_me = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . wfv_password_user_shares_table() . " WHERE shared_with = %d AND revoked = 0 ORDER BY created_at DESC", $user_id ) );
	$shared_data    = array();
	foreach ( $shared_with_me as $s ) {
		$sharer = get_userdata( $s->shared_by );
		$shared_data[ $s->id ] = array(
			'title'      => (string) $s->title,
			'username'   => (string) $s->username,
			'url'        => (string) $s->url,
			'color'      => (string) $s->color,
			'shared_by'  => $sharer ? $sharer->display_name : 'Unknown',
		);
	}

	$open_password_id    = isset( $_GET['wfv_open_password'] ) ? absint( $_GET['wfv_open_password'] ) : 0;
	$colors               = wfv_colors();
	$get_pw_nonce         = wp_create_nonce( 'wfv_get_password' );
	$get_shares_nonce     = wp_create_nonce( 'wfv_get_password_shares' );
	$get_shared_nonce     = wp_create_nonce( 'wfv_get_shared_password' );
	$share_user_nonce     = wp_create_nonce( 'wfv_share_password_to_user' );
	$revoke_user_nonce    = wp_create_nonce( 'wfv_revoke_user_share' );
	$remove_shared_nonce  = wp_create_nonce( 'wfv_remove_shared_with_me' );
	$pwlink_nonce         = wp_create_nonce( 'wfv_create_password_link' );
	$pwlink_revoke_nonce  = wp_create_nonce( 'wfv_revoke_password_link' );

	$shareable_users = get_users( array( 'exclude' => array( $user_id ), 'orderby' => 'display_name', 'fields' => array( 'ID', 'display_name' ) ) );
	?>
	<div class="wrap wfv-wrap wfv-pw-wrap wfv-app">
		<?php wfv_design_system_css(); ?>
		<?php wfv_render_top_nav( 'passwords' ); ?>
		<style>
			.wfv-pw-wrap{ max-width:1280px; }
			.wfv-pw-notice{ background:#fff8e5; border:1px solid #f0d789; border-radius:8px; padding:10px 14px; font-size:12.5px; color:#5a4a00; margin-bottom:16px; }

			/* Split layout */
			.wfv-split{ display:flex; border:1px solid var(--wfv-border); border-radius:var(--wfv-radius); overflow:hidden; box-shadow:var(--wfv-shadow); background:#fff; min-height:560px; }
			.wfv-split-sidebar{ width:320px; flex-shrink:0; border-right:1px solid var(--wfv-border); display:flex; flex-direction:column; background:var(--wfv-bg); }
			.wfv-split-search{ padding:14px 14px 8px; }
			.wfv-split-search input{ width:100%; box-sizing:border-box; padding:8px 12px 8px 30px; border-radius:8px; border:1px solid var(--wfv-border); background:#fff; font-size:13px; }
			.wfv-split-search{ position:relative; }
			.wfv-split-search:before{ content:"🔍"; position:absolute; left:24px; top:22px; font-size:12px; opacity:.55; }
			.wfv-split-tabs{ display:flex; gap:4px; padding:0 14px 10px; }
			.wfv-split-tabs button{ flex:1; background:#fff; border:1px solid var(--wfv-border); padding:6px 8px; font-size:11.5px; font-weight:600; border-radius:7px; cursor:pointer; color:var(--wfv-slate); }
			.wfv-split-tabs button.wfv-tab-active{ background:var(--wfv-primary); border-color:var(--wfv-primary); color:#fff; }
			.wfv-split-tagrow{ display:flex; flex-wrap:wrap; gap:5px; padding:0 14px 10px; }
			.wfv-split-tagrow button{ font-size:11px; padding:3px 9px; border-radius:99px; border:1px solid var(--wfv-border); background:#fff; cursor:pointer; color:var(--wfv-slate); }
			.wfv-split-tagrow button.wfv-tag-active{ background:var(--wfv-primary); border-color:var(--wfv-primary); color:#fff; }
			.wfv-split-add{ margin:0 14px 12px; background:var(--wfv-primary); color:#fff; border:0; border-radius:8px; padding:9px; font-weight:600; font-size:13px; cursor:pointer; }
			.wfv-split-add:hover{ background:var(--wfv-primary-dark); }
			.wfv-split-list{ flex:1; overflow-y:auto; padding:0 8px 12px; }
			.wfv-split-item{ display:flex; align-items:center; gap:10px; padding:9px 8px; border-radius:8px; cursor:pointer; }
			.wfv-split-item:hover{ background:#eef0f4; }
			.wfv-split-item.wfv-item-active{ background:#fff; box-shadow:0 1px 3px rgba(15,23,42,.08); }
			.wfv-item-icon{ width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700; font-size:13px; flex-shrink:0; }
			.wfv-item-text{ flex:1; min-width:0; }
			.wfv-item-text strong{ display:block; font-size:13px; color:var(--wfv-ink); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
			.wfv-item-text span{ display:block; font-size:11.5px; color:var(--wfv-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
			.wfv-item-star{ font-size:12px; color:#f4b400; flex-shrink:0; }
			.wfv-split-empty-list{ padding:24px 16px; text-align:center; color:var(--wfv-muted); font-size:12.5px; }

			.wfv-split-detail{ flex:1; padding:32px 40px; overflow-y:auto; }
			.wfv-detail-empty{ height:100%; display:flex; flex-direction:column; align-items:center; justify-content:center; color:var(--wfv-muted); text-align:center; padding-top:80px; }
			.wfv-detail-empty .icon{ font-size:40px; margin-bottom:12px; opacity:.6; }
			.wfv-detail-header{ display:flex; align-items:flex-start; gap:14px; margin-bottom:26px; }
			.wfv-detail-icon{ width:44px; height:44px; border-radius:10px; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700; font-size:17px; flex-shrink:0; }
			.wfv-detail-header h2{ margin:0; font-size:19px; }
			.wfv-detail-sub{ font-size:12px; color:var(--wfv-muted); margin-top:2px; }
			.wfv-detail-header-actions{ margin-left:auto; display:flex; align-items:center; gap:8px; }
			.wfv-field-label{ display:block; font-size:11px; text-transform:uppercase; letter-spacing:.03em; color:var(--wfv-muted); margin:16px 0 4px; }
			.wfv-field-label:first-of-type{ margin-top:0; }
			.wfv-detail-input{ width:100%; max-width:440px; box-sizing:border-box; padding:9px 11px; border-radius:8px; border:1px solid var(--wfv-border); font-size:14px; }
			.wfv-detail-row{ display:flex; align-items:center; gap:8px; max-width:440px; }
			.wfv-detail-row input{ flex:1; }
			.wfv-pw-strength{ height:5px; border-radius:3px; background:#e2e8f0; margin-top:6px; max-width:440px; overflow:hidden; }
			.wfv-pw-strength-bar{ height:100%; width:0%; background:#d63638; transition:width .2s, background .2s; }
			.wfv-pw-strength-label{ font-size:11px; color:var(--wfv-muted); margin-top:4px; }
			.wfv-detail-footer{ display:flex; justify-content:space-between; align-items:center; margin-top:26px; max-width:440px; flex-wrap:wrap; gap:10px; }
			.wfv-note-tags{ display:flex; flex-wrap:wrap; gap:4px; margin-top:6px; }
			.wfv-tag-pill{ font-size:10px; padding:2px 8px; border-radius:99px; background:rgba(0,0,0,.06); color:#3c434a; }
			.wfv-detail-readonly-row{ display:flex; align-items:center; gap:8px; max-width:440px; background:var(--wfv-bg); border:1px solid var(--wfv-border); border-radius:8px; padding:9px 12px; margin-top:6px; }
			.wfv-detail-readonly-row span{ flex:1; font-family:Consolas,Monaco,monospace; font-size:13px; word-break:break-all; }
			.wfv-icon-btn{ background:none; border:0; cursor:pointer; font-size:14px; padding:2px 4px; opacity:.7; }
			.wfv-icon-btn:hover{ opacity:1; }

			.wfv-modal{ position:fixed; inset:0; z-index:100000; display:flex; align-items:flex-start; justify-content:center; padding:3vh 16px; overflow-y:auto; }
			.wfv-modal-backdrop{ position:fixed; inset:0; background:rgba(0,0,0,.65); }
			.wfv-modal-content{ position:relative; background:#fff; border-radius:14px; width:100%; max-width:560px; padding:24px 28px 20px; box-shadow:var(--wfv-shadow-lg); }
			.wfv-modal-close{ position:absolute; top:10px; right:14px; background:none; border:0; font-size:24px; cursor:pointer; line-height:1; color:#646970; }
			.wfv-color-swatches{ display:flex; gap:6px; flex-wrap:wrap; }
			.wfv-swatch{ width:20px; height:20px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; cursor:pointer; border:2px solid transparent; }
			.wfv-swatch input{ opacity:0; width:1px; height:1px; }
			.wfv-swatch:has(input:checked){ border-color:#1d2327; }
			.wfv-toast{ position:fixed; bottom:24px; right:24px; background:var(--wfv-ink); color:#fff; padding:10px 18px; border-radius:8px; font-size:13px; opacity:0; transform:translateY(8px); transition:.2s; pointer-events:none; z-index:100001; }
			.wfv-toast.wfv-show{ opacity:1; transform:translateY(0); }

			@media (max-width: 900px) {
				.wfv-split{ flex-direction:column; min-height:0; }
				.wfv-split-sidebar{ width:100%; border-right:0; border-bottom:1px solid var(--wfv-border); max-height:340px; }
				.wfv-split-detail{ padding:24px 20px; }
			}
		</style>

		<h1>🔑 Passwords</h1>

		<?php if ( $master_on ) : ?>
			<div class="wfv-pw-notice" style="background:#ecfdf3;border-color:#a6f4c5;color:#027a48;display:flex;justify-content:space-between;align-items:center;">
				<span>🔓 Vault unlocked with your master password for this session.</span>
				<form method="post" style="margin:0;">
					<?php wp_nonce_field( 'wfv_lock_vault', 'wfv_lock_nonce' ); ?>
					<input type="hidden" name="wfv_action" value="lock_vault">
					<input type="hidden" name="ctx_view" value="passwords">
					<button type="submit" class="button button-small">Lock now</button>
				</form>
			</div>
		<?php else : ?>
			<div class="wfv-pw-notice">🔒 Encrypted at rest (AES-256). For stronger protection, <a href="<?php echo esc_url( admin_url( 'profile.php#wfv-master-pw-section' ) ); ?>">set up a master password</a>. Plaintext values are only ever fetched when you click Reveal, Copy, or select an item.</div>
		<?php endif; ?>

		<?php if ( $notice && $msg ) : ?>
			<div class="notice notice-<?php echo esc_attr( $notice ); ?> is-dismissible"><p><?php echo esc_html( $msg ); ?></p></div>
		<?php endif; ?>

		<div class="wfv-split">
			<div class="wfv-split-sidebar">
				<div class="wfv-split-search"><input type="text" id="wfv-pw-search" placeholder="Search…"></div>
				<div class="wfv-split-tabs">
					<button type="button" class="wfv-tab-active" data-list-tab="mine">My Vault (<?php echo count( $entries ); ?>)</button>
					<button type="button" data-list-tab="shared">Shared with me (<?php echo count( $shared_with_me ); ?>)</button>
				</div>
				<?php if ( ! empty( $all_tags ) ) : ?>
					<div class="wfv-split-tagrow" id="wfv-pw-tag-filters">
						<?php foreach ( $all_tags as $tag ) : ?>
							<button type="button" data-tag="<?php echo esc_attr( sanitize_title( $tag ) ); ?>"><?php echo esc_html( $tag ); ?></button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<button type="button" class="wfv-split-add" id="wfv-add-pw-trigger">+ Add password</button>
				<div class="wfv-split-list" id="wfv-pw-list">
					<?php if ( empty( $entries ) ) : ?>
						<div class="wfv-split-empty-list" data-kind="mine">No passwords saved yet.</div>
					<?php endif; ?>
					<?php foreach ( $entries as $e ) :
						$hex         = isset( $colors[ $e->color ] ) ? $colors[ $e->color ] : $colors['gray'];
						$tag_slugs   = $e->tags ? implode( ' ', array_map( 'sanitize_title', array_map( 'trim', explode( ',', $e->tags ) ) ) ) : '';
						$search_blob = strtolower( $e->title . ' ' . $e->username . ' ' . $e->url . ' ' . $e->tags );
						?>
						<div class="wfv-split-item" data-kind="mine" data-id="<?php echo (int) $e->id; ?>" data-search="<?php echo esc_attr( $search_blob ); ?>" data-tags="<?php echo esc_attr( $tag_slugs ); ?>">
							<span class="wfv-item-icon" style="background:<?php echo esc_attr( $hex ); ?>;"><?php echo esc_html( strtoupper( substr( $e->title, 0, 1 ) ) ); ?></span>
							<div class="wfv-item-text"><strong><?php echo esc_html( $e->title ); ?></strong><span><?php echo esc_html( $e->username ?: ( $e->url ?: '—' ) ); ?></span></div>
							<?php if ( $e->starred ) : ?><span class="wfv-item-star">★</span><?php endif; ?>
						</div>
					<?php endforeach; ?>
					<?php if ( empty( $shared_with_me ) ) : ?>
						<div class="wfv-split-empty-list" data-kind="shared" style="display:none;">Nothing has been shared with you yet.</div>
					<?php endif; ?>
					<?php foreach ( $shared_with_me as $s ) :
						$hex         = isset( $colors[ $s->color ] ) ? $colors[ $s->color ] : $colors['gray'];
						$search_blob = strtolower( $s->title . ' ' . $s->username . ' ' . $s->url );
						?>
						<div class="wfv-split-item" data-kind="shared" data-id="<?php echo (int) $s->id; ?>" data-search="<?php echo esc_attr( $search_blob ); ?>" data-tags="" style="display:none;">
							<span class="wfv-item-icon" style="background:<?php echo esc_attr( $hex ); ?>;"><?php echo esc_html( strtoupper( substr( $s->title, 0, 1 ) ) ); ?></span>
							<div class="wfv-item-text"><strong><?php echo esc_html( $s->title ); ?></strong><span><?php echo esc_html( $s->username ?: '—' ); ?></span></div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="wfv-split-detail">
				<div class="wfv-detail-empty" id="wfv-pw-detail-empty">
					<div class="icon">🔑</div>
					<p>Select a password on the left, or add a new one.</p>
				</div>

				<!-- Editable detail (My Vault) -->
				<div id="wfv-pw-detail-content" style="display:none;">
					<div class="wfv-detail-header">
						<span class="wfv-detail-icon" id="wfv-pw-detail-icon"></span>
						<div>
							<h2 id="wfv-pw-detail-title-text">Title</h2>
							<div class="wfv-detail-sub" id="wfv-pw-detail-updated"></div>
						</div>
						<div class="wfv-detail-header-actions">
							<form method="post" id="wfv-pw-star-form" style="margin:0;">
								<?php wp_nonce_field( 'wfv_toggle_star_password', 'wfv_pw_star_nonce' ); ?>
								<input type="hidden" name="wfv_action" value="toggle_star_password">
								<input type="hidden" name="ctx_view" value="passwords">
								<input type="hidden" name="password_id" id="wfv-pw-star-id" value="">
								<button type="submit" class="wfv-icon-btn" id="wfv-pw-star-btn" style="font-size:18px;" title="Star">☆</button>
							</form>
							<button type="button" class="button" id="wfv-pw-share-btn">Share</button>
						</div>
					</div>

					<form id="wfv-pw-detail-form" method="post">
						<?php wp_nonce_field( 'wfv_create_password', 'wfv_pw_nonce' ); ?>
						<?php wp_nonce_field( 'wfv_update_password', 'wfv_pw_update_nonce' ); ?>
						<input type="hidden" name="ctx_view" value="passwords">
						<input type="hidden" name="wfv_action" id="wfv-pw-form-action" value="create_password">
						<input type="hidden" name="password_id" id="wfv-pw-form-id" value="">

						<label class="wfv-field-label" for="wfv-pw-title-field">Title / Service</label>
						<input type="text" name="title" id="wfv-pw-title-field" class="wfv-detail-input" required>

						<label class="wfv-field-label" for="wfv-pw-username-field">Username / Email</label>
						<div class="wfv-detail-row"><input type="text" name="username" id="wfv-pw-username-field" class="wfv-detail-input" autocomplete="off"><button type="button" class="wfv-icon-btn" id="wfv-pw-copy-username" title="Copy">📋</button></div>

						<label class="wfv-field-label" for="wfv-pw-password-field">Password</label>
						<div class="wfv-detail-row">
							<input type="password" name="password" id="wfv-pw-password-field" class="wfv-detail-input" autocomplete="new-password">
							<button type="button" class="wfv-icon-btn" id="wfv-pw-toggle-visibility" title="Show/hide">👁</button>
							<button type="button" class="button" id="wfv-pw-generate">Generate</button>
						</div>
						<div class="wfv-pw-strength"><div class="wfv-pw-strength-bar" id="wfv-pw-strength-bar"></div></div>
						<div class="wfv-pw-strength-label" id="wfv-pw-strength-label"></div>

						<label class="wfv-field-label" for="wfv-pw-url-field">Website URL</label>
						<input type="text" name="url" id="wfv-pw-url-field" class="wfv-detail-input" placeholder="https://…">

						<label class="wfv-field-label" for="wfv-pw-notes-field">Notes</label>
						<textarea name="notes" id="wfv-pw-notes-field" class="wfv-detail-input" rows="3" placeholder="Recovery codes, security questions, etc."></textarea>

						<label class="wfv-field-label" for="wfv-pw-tags-field">Tags</label>
						<input type="text" name="tags" id="wfv-pw-tags-field" class="wfv-detail-input" placeholder="Tags, comma separated">

						<label class="wfv-field-label">Color</label>
						<div class="wfv-color-swatches" id="wfv-pw-color-swatches"><?php wfv_color_swatches( 'gray' ); ?></div>

						<div class="wfv-detail-footer">
							<button type="button" class="button" id="wfv-pw-delete-btn" style="color:#c1272d;">Delete</button>
							<button type="submit" class="button button-primary">Save changes</button>
						</div>
					</form>
				</div>

				<!-- Read-only detail (Shared with me) -->
				<div id="wfv-pw-detail-shared" style="display:none;">
					<div class="wfv-detail-header">
						<span class="wfv-detail-icon" id="wfv-shared-detail-icon"></span>
						<div>
							<h2 id="wfv-shared-detail-title">Title</h2>
							<div class="wfv-detail-sub" id="wfv-shared-detail-by"></div>
						</div>
					</div>
					<label class="wfv-field-label">Username</label>
					<div class="wfv-detail-readonly-row"><span id="wfv-shared-username"></span><button type="button" class="wfv-icon-btn" id="wfv-shared-copy-username" title="Copy">📋</button></div>
					<label class="wfv-field-label">Password</label>
					<div class="wfv-detail-readonly-row"><span id="wfv-shared-pw-display">••••••••</span><button type="button" class="wfv-icon-btn" id="wfv-shared-reveal" title="Reveal">👁</button><button type="button" class="wfv-icon-btn" id="wfv-shared-copy-pw" title="Copy">📋</button></div>
					<div id="wfv-shared-url-wrap" style="display:none;">
						<label class="wfv-field-label">Website</label>
						<div class="wfv-detail-readonly-row"><span id="wfv-shared-url"></span></div>
					</div>
					<div class="wfv-detail-footer">
						<form method="post" id="wfv-shared-remove-form" onsubmit="return confirm('Remove this from your shared passwords? The owner keeps their copy.');">
							<?php wp_nonce_field( 'wfv_remove_shared_with_me', 'wfv_remove_shared_nonce' ); ?>
							<input type="hidden" name="wfv_action" value="remove_shared_with_me">
							<input type="hidden" name="ctx_view" value="passwords">
							<input type="hidden" name="share_id" id="wfv-shared-remove-id" value="">
							<button type="submit" class="button" style="color:#c1272d;">Remove</button>
						</form>
					</div>
				</div>

				<!-- Hidden delete form, submitted via JS after confirm() -->
				<form method="post" id="wfv-pw-delete-form" style="display:none;">
					<?php wp_nonce_field( 'wfv_delete_password', 'wfv_pw_delete_nonce' ); ?>
					<input type="hidden" name="wfv_action" value="delete_password">
					<input type="hidden" name="ctx_view" value="passwords">
					<input type="hidden" name="password_id" id="wfv-pw-delete-id" value="">
				</form>
			</div>
		</div>
	</div>

	<!-- Share modal -->
	<div class="wfv-modal" id="wfv-pw-share-modal" style="display:none;">
		<div class="wfv-modal-backdrop" id="wfv-pw-share-modal-backdrop"></div>
		<div class="wfv-modal-content" style="max-width:640px;">
			<button type="button" class="wfv-modal-close" id="wfv-pw-share-modal-close">&times;</button>
			<h2 id="wfv-pw-share-heading">Share "&hellip;"</h2>

			<h3 style="font-size:13px;text-transform:uppercase;letter-spacing:.03em;color:#8c8f94;margin:18px 0 8px;">Share with a teammate</h3>
			<form id="wfv-pw-share-user-form" method="post" style="display:flex;gap:8px;margin-bottom:10px;">
				<?php wp_nonce_field( 'wfv_share_password_to_user', 'wfv_share_user_nonce' ); ?>
				<input type="hidden" name="wfv_action" value="share_password_to_user">
				<input type="hidden" name="ctx_view" value="passwords">
				<input type="hidden" name="password_id" id="wfv-pw-share-password-id" value="">
				<select name="target_user_id" id="wfv-pw-share-user-select" style="flex:1;" required>
					<option value="">Choose a person&hellip;</option>
					<?php foreach ( $shareable_users as $u ) : ?>
						<option value="<?php echo (int) $u->ID; ?>"><?php echo esc_html( $u->display_name ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="button button-primary">Share</button>
			</form>
			<div id="wfv-pw-share-user-list" style="margin-bottom:20px;font-size:13px;color:#646970;">Loading&hellip;</div>

			<h3 style="font-size:13px;text-transform:uppercase;letter-spacing:.03em;color:#8c8f94;margin:18px 0 8px;">Share via link</h3>
			<form method="post" id="wfv-pw-link-form">
				<?php wp_nonce_field( 'wfv_create_password_link', 'wfv_pwlink_nonce' ); ?>
				<input type="hidden" name="wfv_action" value="create_password_link">
				<input type="hidden" name="ctx_view" value="passwords">
				<input type="hidden" name="password_id" id="wfv-pw-link-password-id" value="">
				<table class="form-table">
					<tr><th>Label</th><td><input type="text" name="label" placeholder="e.g. For the contractor"></td></tr>
					<tr><th>Access password (optional)</th><td><input type="text" name="access_password" placeholder="Leave blank for none"></td></tr>
					<tr><th>Expires after (days)</th><td><input type="number" name="expiry_days" min="0" placeholder="0 = never"></td></tr>
					<tr><th>Max views</th><td><input type="number" name="max_views" min="0" placeholder="0 = unlimited"></td></tr>
				</table>
				<button type="submit" class="button button-primary">Generate link</button>
			</form>
			<div id="wfv-pw-link-list" style="margin-top:14px;font-size:13px;color:#646970;">Loading&hellip;</div>
		</div>
	</div>

	<div class="wfv-toast" id="wfv-pw-toast"></div>

	<script>
	(function(){
		var wfvGetPwNonce      = <?php echo wp_json_encode( $get_pw_nonce ); ?>;
		var wfvEntryData       = <?php echo wp_json_encode( $entry_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS ); ?>;
		var wfvSharedData      = <?php echo wp_json_encode( $shared_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS ); ?>;
		var wfvGetSharesNonce  = <?php echo wp_json_encode( $get_shares_nonce ); ?>;
		var wfvGetSharedNonce  = <?php echo wp_json_encode( $get_shared_nonce ); ?>;
		var wfvOpenPasswordId  = <?php echo (int) $open_password_id; ?>;
		var wfvRevokeUserNonce   = <?php echo wp_json_encode( $revoke_user_nonce ); ?>;
		var wfvPwlinkRevokeNonce = <?php echo wp_json_encode( $pwlink_revoke_nonce ); ?>;
		var wfvColors = <?php echo wp_json_encode( $colors ); ?>;

		var emptyState  = document.getElementById('wfv-pw-detail-empty');
		var mineDetail  = document.getElementById('wfv-pw-detail-content');
		var sharedDetail = document.getElementById('wfv-pw-detail-shared');
		var toast       = document.getElementById('wfv-pw-toast');
		var currentKind = null; // 'mine' | 'shared' | null

		function showToast(text){
			toast.textContent = text;
			toast.classList.add('wfv-show');
			setTimeout(function(){ toast.classList.remove('wfv-show'); }, 1800);
		}
		function copyText(text){
			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText(text);
			} else {
				var t = document.createElement('textarea');
				t.value = text; document.body.appendChild(t); t.select();
				document.execCommand('copy'); document.body.removeChild(t);
			}
		}
		function initials(title){ return (title || '?').substr(0,1).toUpperCase(); }
		function setActiveListItem(id, kind){
			document.querySelectorAll('.wfv-split-item').forEach(function(el){
				el.classList.toggle('wfv-item-active', el.dataset.id === String(id) && el.dataset.kind === kind);
			});
		}

		// ---------------- Fields (My Vault detail form) ----------------
		var pwInput = document.getElementById('wfv-pw-password-field');

		function setColor(colorKey){
			document.querySelectorAll('#wfv-pw-color-swatches input[type=radio]').forEach(function(input){
				input.checked = ( input.value === colorKey );
			});
		}
		function strengthCheck(){
			var val = pwInput.value;
			var score = 0;
			if ( val.length >= 8 ) score++;
			if ( val.length >= 14 ) score++;
			if ( /[a-z]/.test(val) && /[A-Z]/.test(val) ) score++;
			if ( /[0-9]/.test(val) ) score++;
			if ( /[^A-Za-z0-9]/.test(val) ) score++;
			var pct = val.length ? Math.min(100, (score / 5) * 100) : 0;
			var bar = document.getElementById('wfv-pw-strength-bar');
			var label = document.getElementById('wfv-pw-strength-label');
			bar.style.width = pct + '%';
			var text = '', color = '#d63638';
			if ( ! val.length ) { text = ''; }
			else if ( score <= 1 ) { text = 'Weak'; color = '#d63638'; }
			else if ( score <= 2 ) { text = 'Fair'; color = '#dba617'; }
			else if ( score <= 3 ) { text = 'Good'; color = '#2271b1'; }
			else { text = 'Strong'; color = '#1a7f37'; }
			bar.style.background = color;
			label.textContent = text;
		}
		pwInput.addEventListener('input', strengthCheck);
		document.getElementById('wfv-pw-toggle-visibility').addEventListener('click', function(){
			pwInput.type = ( pwInput.type === 'password' ) ? 'text' : 'password';
		});
		document.getElementById('wfv-pw-generate').addEventListener('click', function(){
			var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*()-_=+';
			var arr = new Uint32Array(18);
			window.crypto.getRandomValues(arr);
			var out = '';
			for ( var i = 0; i < 18; i++ ) { out += chars[ arr[i] % chars.length ]; }
			pwInput.value = out;
			pwInput.type = 'text';
			strengthCheck();
		});
		document.getElementById('wfv-pw-copy-username').addEventListener('click', function(){
			copyText( document.getElementById('wfv-pw-username-field').value );
			showToast('Username copied');
		});

		function showEmptyState(){
			emptyState.style.display = '';
			mineDetail.style.display = 'none';
			sharedDetail.style.display = 'none';
			currentKind = null;
		}
		function openCreateItem(){
			emptyState.style.display = 'none';
			sharedDetail.style.display = 'none';
			mineDetail.style.display = '';
			currentKind = 'mine';
			setActiveListItem(null, 'mine');

			document.getElementById('wfv-pw-detail-title-text').textContent = 'New password';
			document.getElementById('wfv-pw-detail-updated').textContent = '';
			document.getElementById('wfv-pw-detail-icon').textContent = '＋';
			document.getElementById('wfv-pw-detail-icon').style.background = '#94a3b8';
			document.getElementById('wfv-pw-form-action').value = 'create_password';
			document.getElementById('wfv-pw-form-id').value = '';
			document.getElementById('wfv-pw-title-field').value = '';
			document.getElementById('wfv-pw-username-field').value = '';
			document.getElementById('wfv-pw-url-field').value = '';
			document.getElementById('wfv-pw-notes-field').value = '';
			document.getElementById('wfv-pw-tags-field').value = '';
			pwInput.value = ''; pwInput.type = 'password';
			setColor('gray');
			strengthCheck();
			document.getElementById('wfv-pw-star-btn').style.display = 'none';
			document.getElementById('wfv-pw-share-btn').style.display = 'none';
			document.getElementById('wfv-pw-delete-btn').style.display = 'none';
			document.getElementById('wfv-pw-title-field').focus();
		}
		function openMineItem(id){
			var data = wfvEntryData[id];
			if ( ! data ) { return; }
			emptyState.style.display = 'none';
			sharedDetail.style.display = 'none';
			mineDetail.style.display = '';
			currentKind = 'mine';
			setActiveListItem(id, 'mine');

			document.getElementById('wfv-pw-detail-title-text').textContent = data.title;
			document.getElementById('wfv-pw-detail-updated').textContent = 'Updated ' + data.updated;
			var icon = document.getElementById('wfv-pw-detail-icon');
			icon.textContent = initials(data.title);
			icon.style.background = wfvColors[data.color] || wfvColors.gray;

			document.getElementById('wfv-pw-form-action').value = 'update_password';
			document.getElementById('wfv-pw-form-id').value = id;
			document.getElementById('wfv-pw-title-field').value = data.title;
			document.getElementById('wfv-pw-username-field').value = data.username;
			document.getElementById('wfv-pw-url-field').value = data.url;
			document.getElementById('wfv-pw-tags-field').value = data.tags;
			pwInput.value = ''; pwInput.type = 'password';
			document.getElementById('wfv-pw-notes-field').value = '';
			setColor(data.color);
			strengthCheck();

			var starBtn = document.getElementById('wfv-pw-star-btn');
			starBtn.style.display = '';
			starBtn.textContent = data.starred ? '★' : '☆';
			starBtn.style.color = data.starred ? '#f4b400' : '';
			document.getElementById('wfv-pw-star-id').value = id;
			document.getElementById('wfv-pw-share-btn').style.display = '';
			document.getElementById('wfv-pw-delete-btn').style.display = '';

			// Fetch the actual password + notes on demand (never preloaded).
			var params = new URLSearchParams();
			params.append('action', 'wfv_get_password');
			params.append('nonce', wfvGetPwNonce);
			params.append('id', id);
			fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: params })
				.then(function(r){ return r.json(); })
				.then(function(res){
					if ( res && res.success && document.getElementById('wfv-pw-form-id').value === String(id) ) {
						pwInput.value = res.data.password || '';
						document.getElementById('wfv-pw-notes-field').value = res.data.notes || '';
						strengthCheck();
					}
				})
				.catch(function(){});
		}
		function openSharedItem(id){
			var data = wfvSharedData[id];
			if ( ! data ) { return; }
			emptyState.style.display = 'none';
			mineDetail.style.display = 'none';
			sharedDetail.style.display = '';
			currentKind = 'shared';
			setActiveListItem(id, 'shared');

			document.getElementById('wfv-shared-detail-title').textContent = data.title;
			document.getElementById('wfv-shared-detail-by').textContent = 'Shared by ' + data.shared_by;
			var icon = document.getElementById('wfv-shared-detail-icon');
			icon.textContent = initials(data.title);
			icon.style.background = wfvColors[data.color] || wfvColors.gray;
			document.getElementById('wfv-shared-username').textContent = data.username || '—';
			document.getElementById('wfv-shared-pw-display').textContent = '••••••••';
			if ( data.url ) {
				document.getElementById('wfv-shared-url-wrap').style.display = '';
				document.getElementById('wfv-shared-url').textContent = data.url;
			} else {
				document.getElementById('wfv-shared-url-wrap').style.display = 'none';
			}
			document.getElementById('wfv-shared-remove-id').value = id;
			document.getElementById('wfv-shared-copy-username').onclick = function(){ copyText(data.username); showToast('Username copied'); };
		}

		document.getElementById('wfv-pw-delete-btn').addEventListener('click', function(){
			if ( ! confirm('Delete this password entry? This cannot be undone.') ) { return; }
			document.getElementById('wfv-pw-delete-id').value = document.getElementById('wfv-pw-form-id').value;
			document.getElementById('wfv-pw-delete-form').submit();
		});
		document.getElementById('wfv-add-pw-trigger').addEventListener('click', openCreateItem);

		document.querySelectorAll('.wfv-split-item').forEach(function(item){
			item.addEventListener('click', function(){
				var id = item.dataset.id, kind = item.dataset.kind;
				if ( 'shared' === kind ) { openSharedItem(id); } else { openMineItem(id); }
			});
		});

		if ( wfvOpenPasswordId && wfvEntryData[wfvOpenPasswordId] ) {
			openMineItem(wfvOpenPasswordId);
		}

		// ---------------- Shared with me: reveal / copy ----------------
		function fetchSharedPassword(id, cb){
			var params = new URLSearchParams();
			params.append('action', 'wfv_get_shared_password');
			params.append('nonce', wfvGetSharedNonce);
			params.append('id', id);
			fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: params })
				.then(function(r){ return r.json(); })
				.then(function(res){ cb( res && res.success ? res.data.password : '' ); })
				.catch(function(){ cb(''); });
		}
		document.getElementById('wfv-shared-reveal').addEventListener('click', function(){
			var id = document.getElementById('wfv-shared-remove-id').value;
			var display = document.getElementById('wfv-shared-pw-display');
			fetchSharedPassword(id, function(pw){
				if ( ! pw ) { return; }
				display.textContent = pw;
				setTimeout(function(){ display.textContent = '••••••••'; }, 6000);
			});
		});
		document.getElementById('wfv-shared-copy-pw').addEventListener('click', function(){
			var id = document.getElementById('wfv-shared-remove-id').value;
			fetchSharedPassword(id, function(pw){
				if ( ! pw ) { return; }
				copyText(pw);
				showToast('Password copied');
			});
		});

		// ---------------- Sidebar: search + tag filter + tabs ----------------
		var activeTag = null;
		var activeListTab = 'mine';
		function applyFilters(){
			var q = (document.getElementById('wfv-pw-search') || {}).value || '';
			q = q.trim().toLowerCase();
			document.querySelectorAll('.wfv-split-item').forEach(function(row){
				if ( row.dataset.kind !== activeListTab ) { row.style.display = 'none'; return; }
				var textMatch = ! q || row.dataset.search.indexOf(q) !== -1;
				var tagList = ' ' + row.dataset.tags + ' ';
				var tagMatch = ! activeTag || tagList.indexOf(' ' + activeTag + ' ') !== -1;
				row.style.display = ( textMatch && tagMatch ) ? '' : 'none';
			});
			document.querySelectorAll('.wfv-split-empty-list').forEach(function(el){
				el.style.display = ( el.dataset.kind === activeListTab ) ? '' : 'none';
			});
		}
		var searchInput = document.getElementById('wfv-pw-search');
		if ( searchInput ) { searchInput.addEventListener('input', applyFilters); }
		document.querySelectorAll('#wfv-pw-tag-filters button').forEach(function(chip){
			chip.addEventListener('click', function(){
				var tag = chip.dataset.tag;
				if ( activeTag === tag ) {
					activeTag = null;
					chip.classList.remove('wfv-tag-active');
				} else {
					document.querySelectorAll('#wfv-pw-tag-filters button').forEach(function(c){ c.classList.remove('wfv-tag-active'); });
					activeTag = tag;
					chip.classList.add('wfv-tag-active');
				}
				applyFilters();
			});
		});
		document.querySelectorAll('[data-list-tab]').forEach(function(btn){
			btn.addEventListener('click', function(){
				document.querySelectorAll('[data-list-tab]').forEach(function(b){ b.classList.remove('wfv-tab-active'); });
				btn.classList.add('wfv-tab-active');
				activeListTab = btn.dataset.listTab;
				showEmptyState();
				applyFilters();
			});
		});
		applyFilters();

		// ---------------- Share modal ----------------
		var shareModal   = document.getElementById('wfv-pw-share-modal');
		var shareHeading = document.getElementById('wfv-pw-share-heading');
		var shareUserIdField = document.getElementById('wfv-pw-share-password-id');
		var linkPwIdField     = document.getElementById('wfv-pw-link-password-id');
		var userListBox  = document.getElementById('wfv-pw-share-user-list');
		var linkListBox  = document.getElementById('wfv-pw-link-list');

		function escHtml(s){
			var d = document.createElement('div');
			d.textContent = s;
			return d.innerHTML;
		}
		function loadShareLists(id){
			userListBox.textContent = 'Loading…';
			linkListBox.textContent = 'Loading…';
			var params = new URLSearchParams();
			params.append('action', 'wfv_get_password_shares');
			params.append('nonce', wfvGetSharesNonce);
			params.append('id', id);
			fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: params })
				.then(function(r){ return r.json(); })
				.then(function(res){
					if ( ! res || ! res.success ) {
						userListBox.textContent = 'Could not load shares.';
						linkListBox.textContent = '';
						return;
					}
					renderUserShares(res.data.user_shares);
					renderLinkShares(res.data.link_shares);
				})
				.catch(function(){ userListBox.textContent = 'Could not load shares.'; });
		}
		function renderUserShares(list){
			if ( ! list.length ) { userListBox.innerHTML = '<em>Not shared with anyone yet.</em>'; return; }
			var html = '';
			list.forEach(function(s){
				html += '<div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid #f0f0f1;">' +
					'<span>' + escHtml(s.name) + '</span>' +
					'<form method="post" style="margin:0;" onsubmit="return confirm(\'Revoke this share?\');">' +
					'<input type="hidden" name="wfv_action" value="revoke_user_share">' +
					'<input type="hidden" name="ctx_view" value="passwords">' +
					'<input type="hidden" name="share_id" value="' + s.id + '">' +
					'<input type="hidden" name="wfv_revoke_user_share_nonce" value="' + wfvRevokeUserNonce + '">' +
					'<button type="submit" class="button-link" style="color:#c1272d;font-size:12px;">Revoke</button>' +
					'</form></div>';
			});
			userListBox.innerHTML = html;
		}
		function renderLinkShares(list){
			if ( ! list.length ) { linkListBox.innerHTML = '<em>No share links yet.</em>'; return; }
			var html = '';
			list.forEach(function(s){
				var statusColor = { active: '#1a7f37', revoked: '#c1272d', expired: '#646970', limit: '#b8590a' }[s.status] || '#646970';
				html += '<div style="border:1px solid #e2e4e7;border-radius:8px;padding:8px 10px;margin-bottom:8px;">' +
					'<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">' +
					'<strong style="font-size:12px;">' + escHtml(s.label || 'Untitled link') + '</strong>' +
					'<span style="font-size:11px;color:' + statusColor + ';font-weight:600;text-transform:capitalize;">' + s.status + '</span>' +
					'</div>' +
					'<input type="text" readonly value="' + escHtml(s.url) + '" style="width:100%;box-sizing:border-box;margin-bottom:6px;font-family:Consolas,Monaco,monospace;font-size:12px;padding:5px 8px;border-radius:5px;border:1px solid #dcdcde;" onclick="this.select();">' +
					'<div style="font-size:11px;color:#8c8f94;margin-bottom:6px;">' + (s.has_password ? '🔒 Password protected · ' : '') + 'Views: ' + s.views + (s.expires ? ' · Expires ' + s.expires : '') + '</div>' +
					( s.status !== 'revoked' ?
						'<form method="post" style="margin:0;" onsubmit="return confirm(\'Revoke this link?\');">' +
						'<input type="hidden" name="wfv_action" value="revoke_password_link">' +
						'<input type="hidden" name="ctx_view" value="passwords">' +
						'<input type="hidden" name="link_id" value="' + s.id + '">' +
						'<input type="hidden" name="wfv_pwlink_revoke_nonce" value="' + wfvPwlinkRevokeNonce + '">' +
						'<button type="submit" class="button button-small">Revoke</button>' +
						'</form>' : '' ) +
					'</div>';
			});
			linkListBox.innerHTML = html;
		}
		document.getElementById('wfv-pw-share-btn').addEventListener('click', function(){
			var id = document.getElementById('wfv-pw-form-id').value;
			if ( ! id ) { return; }
			shareHeading.textContent = 'Share "' + (wfvEntryData[id] ? wfvEntryData[id].title : '') + '"';
			shareUserIdField.value = id;
			linkPwIdField.value = id;
			shareModal.style.display = 'flex';
			loadShareLists(id);
		});
		function closeShareModal(){ shareModal.style.display = 'none'; }
		document.getElementById('wfv-pw-share-modal-close').addEventListener('click', closeShareModal);
		document.getElementById('wfv-pw-share-modal-backdrop').addEventListener('click', closeShareModal);
	})();
	</script>
	<?php
}
