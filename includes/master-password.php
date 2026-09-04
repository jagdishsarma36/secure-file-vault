<?php
/**
 * Master password profile fields — renders the vault master password
 * section on the WordPress user profile page and handles saving.
 */

function wfv_master_msg_key( $user_id ) {
	return 'wfv_master_msg_' . (int) $user_id;
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
