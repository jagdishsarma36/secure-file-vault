<?php
/**
 * Secure File Vault – Core Infrastructure
 *
 * Schema installation, database helpers, password encryption,
 * color palettes, design system CSS, shared render helpers,
 * import functionality, and admin action routing.
 *
 * @package SecureFileVault
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ------------------------------------------------------------------
 * Schema (activation for fresh installs + versioned upgrade for
 * existing installs that just replace the plugin files)
 * ------------------------------------------------------------------
 */
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
	$sticky_table          = $wpdb->prefix . 'wfv_sticky_notes';
	$folder_shares_table   = $wpdb->prefix . 'wfv_folder_shares';
	$folder_user_shares_table = $wpdb->prefix . 'wfv_folder_user_shares';

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
	) {$charset_collate};
CREATE TABLE {$sticky_table} (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	content TEXT NOT NULL,
	priority VARCHAR(10) NOT NULL DEFAULT 'medium',
	color VARCHAR(20) NOT NULL DEFAULT '',
	pinned TINYINT(1) NOT NULL DEFAULT 0,
	sort_order INT NOT NULL DEFAULT 0,
	created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
	created_at DATETIME NOT NULL,
	updated_at DATETIME NOT NULL,
	PRIMARY KEY  (id),
	KEY created_by (created_by)
) {$charset_collate};
CREATE TABLE {$folder_shares_table} (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	folder_id BIGINT UNSIGNED NOT NULL,
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
	KEY folder_id (folder_id)
) {$charset_collate};
CREATE TABLE {$folder_user_shares_table} (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	folder_id BIGINT UNSIGNED NOT NULL,
	shared_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
	shared_with BIGINT UNSIGNED NOT NULL DEFAULT 0,
	revoked TINYINT(1) NOT NULL DEFAULT 0,
	created_at DATETIME NOT NULL,
	PRIMARY KEY  (id),
	KEY folder_id (folder_id),
	KEY shared_with (shared_with)
) {$charset_collate};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
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
function wfv_sticky_notes_table() {
	global $wpdb;
	return $wpdb->prefix . 'wfv_sticky_notes';
}
function wfv_folder_shares_table() {
	global $wpdb;
	return $wpdb->prefix . 'wfv_folder_shares';
}
function wfv_folder_user_shares_table() {
	global $wpdb;
	return $wpdb->prefix . 'wfv_folder_user_shares';
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
		.wfv-import-link{ display:block; text-align:center; margin:0 14px 14px; font-size:12px; color:var(--wfv-slate); text-decoration:none; cursor:pointer; }
		.wfv-import-link:hover{ color:var(--wfv-primary); }
		.wfv-import-modal-body p{ font-size:12.5px; color:var(--wfv-slate); line-height:1.6; }
		.wfv-import-formats{ display:flex; flex-wrap:wrap; gap:6px; margin:10px 0 16px; }
		.wfv-import-formats span{ font-size:11px; padding:3px 10px; border-radius:99px; background:var(--wfv-primary-light); color:var(--wfv-primary-dark); font-weight:600; }
		.wfv-import-warning{ background:#fffbea; border:1px solid #fbe38a; border-radius:8px; padding:10px 14px; font-size:12px; color:#7a5b00; margin-bottom:16px; }
		.wfv-import-drop{ border:2px dashed var(--wfv-border); border-radius:10px; padding:26px 16px; text-align:center; cursor:pointer; background:var(--wfv-bg); margin-bottom:16px; }
		.wfv-import-drop:hover{ border-color:var(--wfv-primary); }
		.wfv-import-filename{ font-weight:600; color:var(--wfv-primary); margin-top:6px; font-size:12.5px; }
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

/** The small "Import" trigger link that sits under the sidebar's Add button on both Notes and Passwords. */
function wfv_render_import_trigger() {
	?>
	<a href="#" class="wfv-import-link" id="wfv-import-trigger">⬆ Import from CSV</a>
	<?php
}

/**
 * The Import modal itself, shared by both pages. $ctx_view is 'notes' or
 * 'passwords' — it just controls which page you're sent back to after
 * import (the backend auto-detects the file format and routes each row
 * to the right place regardless of which page you imported from).
 */
function wfv_render_import_modal( $ctx_view ) {
	?>
	<div class="wfv-modal" id="wfv-import-modal" style="display:none;">
		<div class="wfv-modal-backdrop" id="wfv-import-modal-backdrop"></div>
		<div class="wfv-modal-content" style="max-width:480px;">
			<button type="button" class="wfv-modal-close" id="wfv-import-modal-close">&times;</button>
			<h2>Import from CSV</h2>
			<div class="wfv-import-modal-body">
				<p>Supports exports from:</p>
				<div class="wfv-import-formats">
					<span>LastPass</span>
					<span>Google Password Manager</span>
					<span>Bitwarden</span>
					<span>Generic CSV</span>
				</div>
				<p>Bitwarden and LastPass exports can contain both logins and secure notes in one file — each row is automatically sorted into <strong>Passwords</strong> or <strong>Notes</strong>. A generic CSV needs columns like <code>title, username, password, url, notes, tags</code> (or just <code>title, content, tags</code> for notes only).</p>
				<div class="wfv-import-warning">⚠️ Export files contain plaintext passwords. After importing, delete the original file from your computer.</div>

				<form method="post" enctype="multipart/form-data" id="wfv-import-form">
					<?php wp_nonce_field( 'wfv_import_csv', 'wfv_import_nonce' ); ?>
					<input type="hidden" name="wfv_action" value="import_csv">
					<input type="hidden" name="ctx_view" value="<?php echo esc_attr( $ctx_view ); ?>">
					<div class="wfv-import-drop" id="wfv-import-drop">
						<div>📄 Click to choose a CSV file</div>
						<div class="wfv-import-filename" id="wfv-import-filename"></div>
						<input type="file" name="import_file" id="wfv-import-file-input" accept=".csv,text/csv" style="display:none;" required>
					</div>
					<button type="submit" class="button button-primary button-hero" id="wfv-import-submit" style="width:100%;" disabled>Import</button>
				</form>
			</div>
		</div>
	</div>
	<script>
	(function(){
		var trigger  = document.getElementById('wfv-import-trigger');
		var modal    = document.getElementById('wfv-import-modal');
		var drop     = document.getElementById('wfv-import-drop');
		var fileInput = document.getElementById('wfv-import-file-input');
		var filenameEl = document.getElementById('wfv-import-filename');
		var submitBtn = document.getElementById('wfv-import-submit');

		if ( ! trigger ) { return; }
		trigger.addEventListener('click', function(e){ e.preventDefault(); modal.style.display = 'flex'; });
		function closeImportModal(){ modal.style.display = 'none'; }
		document.getElementById('wfv-import-modal-close').addEventListener('click', closeImportModal);
		document.getElementById('wfv-import-modal-backdrop').addEventListener('click', closeImportModal);

		drop.addEventListener('click', function(){ fileInput.click(); });
		fileInput.addEventListener('change', function(){
			if ( fileInput.files.length ) {
				filenameEl.textContent = fileInput.files[0].name;
				submitBtn.disabled = false;
			}
		});
		[ 'dragenter', 'dragover' ].forEach(function(evt){
			drop.addEventListener(evt, function(e){ e.preventDefault(); drop.style.borderColor = 'var(--wfv-primary)'; });
		});
		[ 'dragleave', 'drop' ].forEach(function(evt){
			drop.addEventListener(evt, function(e){ e.preventDefault(); drop.style.borderColor = ''; });
		});
		drop.addEventListener('drop', function(e){
			if ( e.dataTransfer.files.length ) {
				fileInput.files = e.dataTransfer.files;
				filenameEl.textContent = e.dataTransfer.files[0].name;
				submitBtn.disabled = false;
			}
		});
	})();
	</script>
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

function wfv_generate_unique_token() {
	global $wpdb;
	do {
		$token  = bin2hex( random_bytes( 20 ) ); // 40-char, unique per share
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM " . wfv_shares_table() . " WHERE token = %s", $token ) );
	} while ( $exists );
	return $token;
}

function wfv_parse_tags( $raw ) {
	$raw  = (string) $raw;
	$tags = array_filter( array_map( 'trim', explode( ',', $raw ) ) );
	$tags = array_map( 'sanitize_text_field', $tags );
	$tags = array_values( array_unique( $tags ) );
	$tags = array_slice( $tags, 0, 15 ); // keep it sane
	return implode( ', ', $tags );
}

/**
 * Redirect back to wherever the user was (same folder / starred view),
 * carrying a notice message. $open_file_id optionally re-expands a file's
 * share panel after creating/revoking a link.
 */
 function wfv_redirect_with_notice( $type, $message, $open_file_id = 0, $open_password_id = 0, $folder_id = 0 ) {
	$ctx_folder_id = isset( $_POST['ctx_folder'] ) ? absint( $_POST['ctx_folder'] ) : 0;
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
		$target_folder = $folder_id ? $folder_id : $ctx_folder_id;
		if ( $target_folder ) {
			$query['folder'] = $target_folder;
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
		case 'create_folder_share':
			wfv_process_create_folder_share();
			break;
		case 'revoke_folder_share':
			wfv_process_revoke_folder_share();
			break;
		case 'share_folder_to_user':
			wfv_process_share_folder_to_user();
			break;
		case 'revoke_folder_user_share':
			wfv_process_revoke_folder_user_share();
			break;
		case 'remove_folder_share':
			wfv_process_remove_folder_share();
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
		case 'import_csv':
			wfv_process_import_csv();
			break;
	}
}
