<?php
/**
 * ------------------------------------------------------------------
 * Secure File Vault — Frontend Share & Download Handlers
 *
 * Extracted from the main plugin file. Contains all frontend-facing
 * share/download handler functions: the file share download handler,
 * standalone HTML page helpers (password/deny pages), and the public
 * password link share page.
 *
 * Functions:
 *   - wfv_handle_download()             — serves a shared file download.
 *   - wfv_password_page()               — password page for file shares.
 *   - wfv_deny_page()                   — deny/error page for shares.
 *   - wfv_handle_password_link_share()  — public password link share page.
 * ------------------------------------------------------------------
 */

/**
 * ------------------------------------------------------------------
 * File share download handler — a share link looks like:
 * https://yoursite.com/?wfv_token=xxxxxxxx
 * Handles downloads (and inline previews) of files shared publicly.
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

/**
 * ------------------------------------------------------------------
 * Password gate helpers — standalone HTML pages rendered by the
 * frontend share handler when access is restricted.
 * ------------------------------------------------------------------
 */
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
 * Folder share frontend — a share link looks like:
 * https://yoursite.com/?wfv_foldertoken=xxxxxxxx
 *
 * Renders a read-only browse page for the shared folder (files and
 * subfolders). Downloading a file keeps the share context
 * (?wfv_foldertoken=...&wfv_fshare=1&file=ID) so that the folder
 * share's download limit, password gate and expiry are all honoured.
 * ------------------------------------------------------------------
 */
add_action( 'template_redirect', 'wfv_handle_folder_share' );
function wfv_handle_folder_share() {
	if ( empty( $_GET['wfv_foldertoken'] ) ) {
		return;
	}
	global $wpdb;

	$token = preg_replace( '/[^a-f0-9]/', '', sanitize_text_field( wp_unslash( $_GET['wfv_foldertoken'] ) ) );
	$share = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . wfv_folder_shares_table() . " WHERE token = %s", $token ) );

	nocache_headers();

	if ( ! $share ) {
		wfv_deny_page( 'This link is invalid.' );
	}
	if ( (int) $share->revoked === 1 ) {
		wfv_deny_page( 'This link has been revoked by the folder owner.' );
	}
	if ( $share->expires_at && strtotime( $share->expires_at ) < time() ) {
		wfv_deny_page( 'This link has expired.' );
	}
	if ( $share->max_downloads && (int) $share->download_count >= (int) $share->max_downloads ) {
		wfv_deny_page( 'This link has reached its download limit.' );
	}

	// Password gate (shared by both the browse page and any single-file download).
	if ( ! empty( $share->password_hash ) ) {
		$cookie_name = 'wfv_fulock_' . md5( $share->token . wp_salt() );
		$supplied    = isset( $_POST['wfv_password'] ) ? (string) $_POST['wfv_password'] : '';
		$ok          = $supplied !== '' && wp_check_password( $supplied, $share->password_hash );

		if ( $ok ) {
			// Remember this browser unlocked the folder for a short window so
			// subsequent download / sub-folder GETs don't re-prompt.
			wfv_set_folder_unlock_cookie( $cookie_name, 12 * HOUR_IN_SECONDS );
		} elseif ( isset( $_COOKIE[ $cookie_name ] ) && '1' === (string) $_COOKIE[ $cookie_name ] ) {
			$ok = true; // already unlocked earlier in this session
		}

		if ( ! $ok ) {
			$error = isset( $_POST['wfv_password'] ) ? 'Incorrect password.' : '';
			wfv_set_folder_unlock_cookie( $cookie_name, -3600 ); // clear any stale lock
			wfv_folder_password_page( $token, $error );
		}
	}

	// A file download within this share context.
	if ( ! empty( $_GET['wfv_fshare'] ) && isset( $_GET['file'] ) ) {
		wfv_stream_shared_folder_file( $share, absint( $_GET['file'] ) );
	}

	// Otherwise render the browse page.
	wfv_folder_share_page( $share );
	exit;
}

/**
 * Set/clear the short-lived HttpOnly cookie used to remember that this browser
 * has unlocked a password-protected folder share link.
 */
function wfv_set_folder_unlock_cookie( $name, $ttl ) {
	if ( headers_sent() ) {
		return;
	}
	$expire = $ttl < 0 ? time() - 3600 : time() + $ttl;
	setcookie( $name, '1', $expire, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
}

/**
 * Password gate specifically for folder shares (mirrors wfv_password_page
 * but with folder wording).
 */
function wfv_folder_password_page( $token, $error = '' ) {
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
			<h1>🔒 This folder is password protected</h1>
			<?php if ( $error ) : ?><div class="err"><?php echo esc_html( $error ); ?></div><?php endif; ?>
			<form method="post">
				<input type="password" name="wfv_password" placeholder="Enter password" autofocus required>
				<button type="submit">Unlock folder</button>
			</form>
		</div>
	</body>
	</html>
	<?php
	exit;
}

/**
 * Stream a single file out of a folder share, honouring the share context.
 * Reports the download against the folder share's count and serves the file
 * exactly like a regular file share (inline preview or forced download).
 */
function wfv_stream_shared_folder_file( $share, $file_id ) {
	global $wpdb;

	$allowed = wfv_folder_share_accessible_ids( $share->folder_id );
	if ( ! in_array( (int) $file_id, $allowed, true ) ) {
		wfv_deny_page( 'The requested file is not part of this shared folder.' );
	}

	$file = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . wfv_files_table() . " WHERE id = %d", $file_id ) );
	if ( ! $file ) {
		wfv_deny_page( 'The requested file is no longer available.' );
	}

	$path = trailingslashit( wfv_private_dir_path() ) . $file->stored_name;
	if ( ! file_exists( $path ) ) {
		wfv_deny_page( 'The requested file is no longer available.' );
	}

	// Count this download against the folder share.
	if ( $share->max_downloads ) {
		$wpdb->query( $wpdb->prepare( "UPDATE " . wfv_folder_shares_table() . " SET download_count = download_count + 1 WHERE id = %d", $share->id ) );
	}

	while ( ob_get_level() ) {
		ob_end_clean();
	}

	$want_download = isset( $_GET['dl'] );
	$can_preview   = in_array( $file->mime_type, wfv_previewable_types(), true ) && ! $want_download;

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

/**
 * Return the set of folder ids in the share's subtree (the shared folder and
 * every descendant), so that access to any contained resource can be checked.
 */
function wfv_folder_share_subtree_ids( $folder_id ) {
	global $wpdb;
	$ids   = array( (int) $folder_id );
	$queue = array( (int) $folder_id );
	while ( $queue ) {
		$parent = array_shift( $queue );
		$kids   = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM " . wfv_folders_table() . " WHERE parent_id = %d", $parent ) );
		foreach ( $kids as $kid ) {
			$ids[]   = (int) $kid;
			$queue[] = (int) $kid;
		}
	}
	return $ids;
}

/** Return the set of file ids inside the share's subtree. */
function wfv_folder_share_accessible_ids( $folder_id ) {
	global $wpdb;
	$folders = wfv_folder_share_subtree_ids( $folder_id );
	if ( empty( $folders ) ) {
		return array();
	}
	$placeholders = implode( ',', array_fill( 0, count( $folders ), '%d' ) );
	$file_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM " . wfv_files_table() . " WHERE folder_id IN ({$placeholders})", $folders ) );
	return array_map( 'intval', $file_ids );
}

/**
 * Render the public read-only browse page for a folder share: a list of the
 * shared folder's files and subfolders, with in-page navigation.
 */
function wfv_folder_share_page( $share ) {
	global $wpdb;

	$folder_id = isset( $_GET['wfv_dir'] ) ? absint( $_GET['wfv_dir'] ) : (int) $share->folder_id;
	$allowed   = wfv_folder_share_subtree_ids( $share->folder_id );
	if ( ! in_array( $folder_id, $allowed, true ) ) {
		$folder_id = (int) $share->folder_id;
	}

	$folder      = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . wfv_folders_table() . " WHERE id = %d", $folder_id ) );
	$folder_name = $folder ? $folder->name : 'Shared folder';

	// Breadcrumb back up to the shared root.
	$crumbs = array();
	$cur    = $folder;
	while ( $cur && (int) $cur->id !== (int) $share->folder_id ) {
		array_unshift( $crumbs, $cur );
		$cur = $cur->parent_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . wfv_folders_table() . " WHERE id = %d", $cur->parent_id ) ) : null;
	}
	$root_folder = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . wfv_folders_table() . " WHERE id = %d", $share->folder_id ) );
	array_unshift( $crumbs, $root_folder );

	$subfolders = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . wfv_folders_table() . " WHERE parent_id = %d ORDER BY name ASC", $folder_id ) );
	$files      = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . wfv_files_table() . " WHERE folder_id = %d ORDER BY original_name ASC", $folder_id ) );

	$base        = remove_query_arg( array( 'wfv_dir', 'wfv_fshare', 'file' ) );
	$root_url    = add_query_arg( array( 'wfv_foldertoken' => $share->token ), home_url( '/' ) );
	$max_dl_note = $share->max_downloads ? ' · ' . (int) $share->download_count . ' of ' . (int) $share->max_downloads . ' downloads used' : '';

	status_header( 200 );
	?>
	<!DOCTYPE html>
	<html>
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<title><?php echo esc_html( $folder_name ); ?> — Shared folder</title>
		<style>
			:root{ --wfv-brand:#4f46e5; --wfv-brand-dark:#3730a3; --wfv-slate:#475569; --wfv-border:#e2e8f0; --wfv-bg:#f8fafc; }
			*{ box-sizing:border-box; }
			body{ font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif; background:#eef1f6; margin:0; padding:32px 16px; color:#0f172a; }
			.wrap{ max-width:760px; margin:0 auto; background:#fff; border:1px solid var(--wfv-border); border-radius:16px; box-shadow:0 1px 3px rgba(15,23,42,.06), 0 12px 32px rgba(15,23,42,.08); overflow:hidden; }
			.head{ padding:22px 26px; border-bottom:1px solid var(--wfv-border); background:linear-gradient(135deg,#fff,var(--wfv-bg)); }
			.head h1{ margin:0; font-size:19px; }
			.head p{ margin:6px 0 0; color:var(--wfv-slate); font-size:13px; }
			.crumbs{ padding:10px 26px; border-bottom:1px solid var(--wfv-border); font-size:12.5px; color:var(--wfv-slate); background:#fbfcfe; }
			.crumbs a{ color:var(--wfv-brand); text-decoration:none; }
			.crumbs a:hover{ text-decoration:underline; }
			.list{ padding:8px 0; }
			.item{ display:flex; align-items:center; gap:12px; padding:12px 26px; border-bottom:1px solid #f1f5f9; text-decoration:none; color:inherit; }
			.item:hover{ background:#f8fafc; }
			.item .ic{ font-size:20px; width:28px; text-align:center; }
			.item .nm{ flex:1; font-weight:500; font-size:14px; }
			.item .meta{ color:#94a3b8; font-size:12px; }
			.dl{ color:var(--wfv-brand); font-weight:600; font-size:13px; }
			.empty{ padding:34px 26px; text-align:center; color:#94a3b8; font-size:14px; }
			.foot{ padding:14px 26px; text-align:center; color:#94a3b8; font-size:11.5px; }
		</style>
	</head>
	<body>
		<div class="wrap">
			<div class="head">
				<h1>📁 <?php echo esc_html( $folder_name ); ?></h1>
				<p>A folder was shared with you<?php echo $share->label ? ' — ' . esc_html( $share->label ) : ''; ?><?php echo esc_html( $max_dl_note ); ?>.</p>
			</div>
			<div class="crumbs">
				<a href="<?php echo esc_url( $root_url ); ?>"><?php echo esc_html( $root_folder ? $root_folder->name : 'Shared folder' ); ?></a>
				<?php foreach ( $crumbs as $c ) : ?>
					<?php if ( $c && (int) $c->id !== (int) $share->folder_id ) : ?>
						/ <?php if ( (int) $c->id !== $folder_id ) : ?><a href="<?php echo esc_url( add_query_arg( 'wfv_dir', $c->id, $base ) ); ?>"><?php echo esc_html( $c->name ); ?></a><?php else : ?><strong><?php echo esc_html( $c->name ); ?></strong><?php endif; ?>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
			<div class="list">
				<?php if ( empty( $subfolders ) && empty( $files ) ) : ?>
					<div class="empty">This folder is empty.</div>
				<?php endif; ?>
				<?php foreach ( $subfolders as $sub ) : ?>
					<a class="item" href="<?php echo esc_url( add_query_arg( 'wfv_dir', $sub->id, $base ) ); ?>">
						<span class="ic">📁</span>
						<span class="nm"><?php echo esc_html( $sub->name ); ?></span>
						<span class="meta">Folder</span>
					</a>
				<?php endforeach; ?>
				<?php foreach ( $files as $file ) :
					$dl_url = add_query_arg( array( 'wfv_fshare' => '1', 'file' => $file->id ), $base );
					$size   = $file->file_size ? size_format( $file->file_size ) : '';
					?>
					<a class="item" href="<?php echo esc_url( $dl_url ); ?>">
						<span class="ic">📄</span>
						<span class="nm"><?php echo esc_html( $file->original_name ); ?></span>
						<?php if ( $size ) : ?><span class="meta"><?php echo esc_html( $size ); ?></span><?php endif; ?>
						<span class="dl">Download ↓</span>
					</a>
				<?php endforeach; ?>
			</div>
			<div class="foot">Shared privately via a Secure File Vault folder link.</div>
		</div>
	</body>
	</html>
	<?php
	exit;
}