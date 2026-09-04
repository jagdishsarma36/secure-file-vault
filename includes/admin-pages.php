<?php
/**
 * Admin page render functions extracted from secure-file-vault.php.
 *
 * Contains the three main admin UI pages (Files, Notes, Passwords) and
 * the vault lock screen helper. Each function embeds its own inline CSS
 * and JS so the pages are fully self-contained.
 */

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

	// Folders that were shared TO this user by someone else ("Shared with me").
	$shared_with_me = array();
	$shared_root_ids = array();
	$me_shares = $is_admin
		? array()
		: $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . wfv_folder_user_shares_table() . " WHERE shared_with = %d AND revoked = 0 ORDER BY created_at DESC", $user_id ) );
	foreach ( $me_shares as $_ms ) {
		$_sf = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . wfv_folders_table() . " WHERE id = %d", $_ms->folder_id ) );
		if ( $_sf && (int) $_sf->created_by !== (int) $user_id ) {
			$_ms->_folder = $_sf;
			$shared_with_me[] = $_ms;
			$shared_root_ids[] = (int) $_sf->id;
			$folders_by_id[ (int) $_sf->id ] = $_sf;
		}
	}
	// Expand each shared root to its full subtree for descendant browsing.
	$shared_browse_ids = array();
	foreach ( $shared_root_ids as $_rid ) {
		foreach ( wfv_folder_share_subtree_ids( $_rid ) as $_sid ) {
			$shared_browse_ids[ (int) $_sid ] = true;
			if ( ! isset( $folders_by_id[ (int) $_sid ] ) ) {
				$fobj = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . wfv_folders_table() . " WHERE id = %d", $_sid ) );
				if ( $fobj ) {
					$folders_by_id[ (int) $_sid ] = $fobj;
				}
			}
		}
	}

	$managed_folder_ids = array();
	foreach ( $all_folders as $f ) {
		$managed_folder_ids[ (int) $f->id ] = true;
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
	$wfv_shared_browse = ! $is_admin && $current_folder_id && isset( $shared_browse_ids[ (int) $current_folder_id ] ) && ! isset( $managed_folder_ids[ (int) $current_folder_id ] );
	if ( $viewing_starred ) {
		$owner_files_sql   = $is_admin ? '1=1' : $wpdb->prepare( 'uploaded_by = %d', $user_id );
		$files             = $wpdb->get_results( "SELECT * FROM " . wfv_files_table() . " WHERE starred = 1 AND ({$owner_files_sql}) ORDER BY created_at DESC" );
		$owner_folders_sql = $is_admin ? '1=1' : $wpdb->prepare( 'created_by = %d', $user_id );
		$subfolders        = $wpdb->get_results( "SELECT * FROM " . wfv_folders_table() . " WHERE starred = 1 AND ({$owner_folders_sql}) ORDER BY name ASC" );
	} elseif ( $wfv_shared_browse ) {
		// Read-only browse of a folder shared to this user (show everyone's contents).
		$files      = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . wfv_files_table() . " WHERE folder_id = %d ORDER BY created_at DESC", $current_folder_id ) );
		$subfolders = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . wfv_folders_table() . " WHERE parent_id = %d ORDER BY name ASC", $current_folder_id ) );
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

	// Folder share data for the folders the current user manages (public links + user shares).
	$folder_ids = wp_list_pluck( $all_folders, 'id' );
	$folder_shares_by_folder       = array();
	$folder_user_shares_by_folder  = array();
	if ( $folder_ids ) {
		$ph = implode( ',', array_fill( 0, count( $folder_ids ), '%d' ) );
		foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . wfv_folder_shares_table() . " WHERE folder_id IN ($ph) ORDER BY created_at DESC", ...$folder_ids ) ) as $_fs ) {
			$folder_shares_by_folder[ (int) $_fs->folder_id ][] = $_fs;
		}
		foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . wfv_folder_user_shares_table() . " WHERE folder_id IN ($ph) ORDER BY created_at DESC", ...$folder_ids ) ) as $_fu ) {
			$folder_user_shares_by_folder[ (int) $_fu->folder_id ][] = $_fu;
		}
	}

	// Folders shared TO the current user by someone else ("Shared with me").
	// (Computed above, near the folder queries, so the shared browse context is ready.)

	$shareable_users = get_users( array( 'exclude' => array( $user_id ), 'orderby' => 'display_name', 'fields' => array( 'ID', 'display_name' ) ) );

	$base_admin_url = admin_url( 'admin.php?page=wfv-vault' );
	?>
	<div class="wrap wfv-wrap wfv-app">
		<?php wfv_design_system_css(); ?>
		<?php wfv_render_top_nav( 'files' ); ?>
		<?php /* Styles enqueued via admin-files.css */ ?>

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

		<?php if ( ! $viewing_starred && ! $wfv_shared_browse ) : ?>
		<div class="wfv-card">
			<h2>Upload a file<?php echo $current_folder_id && isset( $folders_by_id[ $current_folder_id ] ) ? ' to "' . esc_html( $folders_by_id[ $current_folder_id ]->name ) . '"' : ''; ?></h2>
			<form method="post" enctype="multipart/form-data" id="wfv-upload-form">
				<?php wp_nonce_field( 'wfv_upload', 'wfv_upload_nonce' ); wfv_ctx_fields( $current_folder_id, $viewing_starred ); ?>
				<input type="hidden" name="wfv_action" value="upload">
				<input type="hidden" name="folder_id" value="<?php echo (int) $current_folder_id; ?>">
				<div class="wfv-dropzone" id="wfv-dropzone">
					<span class="wfv-dz-icon">⬆️</span>
					<div>Drag &amp; drop files here, or click to browse (multi-select supported)</div>
					<div class="wfv-dz-file" id="wfv-dz-filename"></div>
					<input type="file" name="wfv_file[]" id="wfv-file-input" style="display:none;" multiple>
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

		<?php if ( $wfv_shared_browse ) : ?>
		<div class="wfv-card wfv-shared-banner">
			<strong>🔗 Shared folder (read-only)</strong> — you can browse and download files, but changes are made by the folder owner.
		</div>
		<?php endif; ?>

		<?php if ( ! $viewing_starred && ! $wfv_shared_browse ) : ?>
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
							<?php if ( ! $wfv_shared_browse ) : ?>
							<div class="wfv-folder-actions">
								<form method="post" style="display:inline;">
									<?php wp_nonce_field( 'wfv_toggle_star_folder', 'wfv_star_folder_nonce' ); wfv_ctx_fields( $current_folder_id, $viewing_starred ); ?>
									<input type="hidden" name="wfv_action" value="toggle_star_folder">
									<input type="hidden" name="folder_id" value="<?php echo (int) $folder->id; ?>">
									<button type="submit" class="wfv-star-btn" title="<?php echo $folder->starred ? 'Unstar' : 'Star'; ?>"><?php echo $folder->starred ? '★' : '☆'; ?></button>
								</form>
								<a href="#" class="wfv-folder-edit-toggle" data-folder="<?php echo (int) $folder->id; ?>">Edit</a>
								<a href="#" class="wfv-folder-share-toggle" data-folder="<?php echo (int) $folder->id; ?>">Share</a>
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
							<div class="wfv-folder-share-panel" id="wfv-folder-share-<?php echo (int) $folder->id; ?>" style="display:none;">
								<?php
								$folder_links = isset( $folder_shares_by_folder[ (int) $folder->id ] ) ? $folder_shares_by_folder[ (int) $folder->id ] : array();
								$folder_users = isset( $folder_user_shares_by_folder[ (int) $folder->id ] ) ? $folder_user_shares_by_folder[ (int) $folder->id ] : array();
								?>
								<h4>Public share link</h4>
								<form method="post">
									<?php wp_nonce_field( 'wfv_create_folder_share', 'wfv_folder_share_nonce' ); wfv_ctx_fields( $current_folder_id, $viewing_starred ); ?>
									<input type="hidden" name="wfv_action" value="create_folder_share">
									<input type="hidden" name="folder_id" value="<?php echo (int) $folder->id; ?>">
									<div class="wfv-folder-share-row">
										<input type="text" name="label" placeholder="Label (for you)" style="flex:1">
									</div>
									<div class="wfv-folder-share-row">
										<input type="text" name="password" placeholder="Password (optional)" style="flex:1">
										<input type="number" name="expiry_days" min="0" placeholder="Expiry (days)" class="wfv-num">
										<input type="number" name="max_downloads" min="0" placeholder="Max dls" class="wfv-num">
									</div>
									<button type="submit" class="button button-primary button-small">Generate link</button>
								</form>
								<?php if ( empty( $folder_links ) ) : ?>
									<p class="wfv-folder-share-muted">No public links yet.</p>
								<?php else : ?>
									<ul class="wfv-folder-shares-list">
										<?php foreach ( $folder_links as $_fl ) :
											$flink = add_query_arg( 'wfv_foldertoken', $_fl->token, $home_url );
											list( $f_status_label, $f_status_key ) = wfv_share_status( $_fl );
											?>
											<li>
												<span class="wfv-folder-share-label"><?php echo esc_html( $_fl->label ? $_fl->label : 'Unlabelled' ); ?></span>
												<input type="text" class="wfv-link-input" readonly value="<?php echo esc_url( $flink ); ?>" onclick="this.select();">
												<button type="button" class="button button-small wfv-copy-btn" onclick="wfvCopyLink(this)">Copy</button>
												<span class="wfv-badge wfv-badge-<?php echo esc_attr( $f_status_key ); ?>"><?php echo esc_html( $f_status_label ); ?></span>
												<?php echo (int) $_fl->download_count . ( $_fl->max_downloads ? ' / ' . (int) $_fl->max_downloads : '' ); ?> downloads
												<?php if ( ! $_fl->revoked ) : ?>
													<form method="post" style="display:inline" onsubmit="return confirm('Revoke this link? It will stop working immediately.');">
														<?php wp_nonce_field( 'wfv_revoke_folder_share', 'wfv_revoke_folder_share_nonce' ); wfv_ctx_fields( $current_folder_id, $viewing_starred ); ?>
														<input type="hidden" name="wfv_action" value="revoke_folder_share">
														<input type="hidden" name="share_id" value="<?php echo (int) $_fl->id; ?>">
														<button type="submit" class="button button-small">Revoke</button>
													</form>
												<?php endif; ?>
											</li>
										<?php endforeach; ?>
									</ul>
								<?php endif; ?>

								<h4>Share with a user</h4>
								<form method="post">
									<?php wp_nonce_field( 'wfv_share_folder_to_user', 'wfv_share_folder_user_nonce' ); wfv_ctx_fields( $current_folder_id, $viewing_starred ); ?>
									<input type="hidden" name="wfv_action" value="share_folder_to_user">
									<input type="hidden" name="folder_id" value="<?php echo (int) $folder->id; ?>">
									<div class="wfv-folder-share-row">
										<select name="share_user_id" style="flex:1">
											<option value="">— Select a user —</option>
											<?php foreach ( $shareable_users as $_u ) : ?>
												<option value="<?php echo (int) $_u->ID; ?>"><?php echo esc_html( $_u->display_name ); ?></option>
											<?php endforeach; ?>
										</select>
										<button type="submit" class="button button-small">Share folder</button>
									</div>
								</form>
								<?php if ( empty( $folder_users ) ) : ?>
									<p class="wfv-folder-share-muted">Not shared with any users yet.</p>
								<?php else : ?>
									<ul class="wfv-folder-shares-list">
										<?php foreach ( $folder_users as $_fu ) :
											$_user = get_userdata( $_fu->shared_with );
											?>
											<li>
												<span class="wfv-folder-share-label">👤 <?php echo esc_html( $_user ? $_user->display_name : 'Unknown user' ); ?></span>
												<span class="wfv-badge wfv-badge-<?php echo $_fu->revoked ? 'revoked' : 'active'; ?>"><?php echo $_fu->revoked ? 'Revoked' : 'Active'; ?></span>
												<?php if ( ! $_fu->revoked ) : ?>
													<form method="post" style="display:inline" onsubmit="return confirm('Stop sharing this folder with this user?');">
														<?php wp_nonce_field( 'wfv_revoke_folder_user_share', 'wfv_revoke_folder_user_nonce' ); wfv_ctx_fields( $current_folder_id, $viewing_starred ); ?>
														<input type="hidden" name="wfv_action" value="revoke_folder_user_share">
														<input type="hidden" name="share_id" value="<?php echo (int) $_fu->id; ?>">
														<button type="submit" class="button button-small">Revoke</button>
													</form>
												<?php endif; ?>
											</li>
										<?php endforeach; ?>
									</ul>
								<?php endif; ?>
							</div>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( ! $is_admin && ! $current_folder_id && ! $viewing_starred && ! empty( $shared_with_me ) ) : ?>
				<div class="wfv-card">
					<h2>🔗 Shared with me</h2>
					<div class="wfv-folder-grid">
						<?php foreach ( $shared_with_me as $_sw ) :
							$swf = $_sw->_folder;
							$sw_owner = get_userdata( $swf->created_by );
							?>
							<div class="wfv-folder-card">
								<a class="wfv-folder-open" href="<?php echo esc_url( add_query_arg( 'folder', $swf->id, $base_admin_url ) ); ?>">
									<?php echo wfv_folder_icon_svg( $swf->color, 34 ); ?>
									<span class="wfv-folder-name"><?php echo esc_html( $swf->name ); ?></span>
								</a>
								<div class="wfv-folder-meta">shared by <?php echo esc_html( $sw_owner ? $sw_owner->display_name : '—' ); ?></div>
								<div class="wfv-folder-actions">
									<form method="post" onsubmit="return confirm('Remove this shared folder from your list? It will no longer appear here until re-shared.');">
										<?php wp_nonce_field( 'wfv_remove_folder_share', 'wfv_remove_folder_share_nonce' ); wfv_ctx_fields( 0, false ); ?>
										<input type="hidden" name="wfv_action" value="remove_folder_share">
										<input type="hidden" name="share_id" value="<?php echo (int) $_sw->id; ?>">
										<button type="submit" class="button button-small">Remove</button>
									</form>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( empty( $files ) && empty( $subfolders ) ) : ?>
				<div class="wfv-empty"><?php echo $viewing_starred ? 'Nothing starred yet.' : ( $wfv_shared_browse ? 'This folder has no files or subfolders.' : 'This folder is empty — upload a file or create a subfolder.' ); ?></div>
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
						$shared_dl_url = wp_nonce_url( admin_url( 'admin-post.php?action=wfv_shared_folder_download&file_id=' . $file->id ), 'wfv_shared_folder_download_' . $file->id );
						?>
						<tr class="wfv-file-row" data-search="<?php echo esc_attr( $search_blob ); ?>" data-file-id="<?php echo (int) $file->id; ?>">
							<td data-label="Star">
								<?php if ( $wfv_shared_browse ) : ?>
									<a class="wfv-file-dl-link" href="<?php echo esc_url( $shared_dl_url ); ?>">⬇</a>
								<?php else : ?>
								<form method="post" style="display:inline;">
									<?php wp_nonce_field( 'wfv_toggle_star_file', 'wfv_star_nonce' ); wfv_ctx_fields( $current_folder_id, $viewing_starred ); ?>
									<input type="hidden" name="wfv_action" value="toggle_star_file">
									<input type="hidden" name="file_id" value="<?php echo (int) $file->id; ?>">
									<button type="submit" class="wfv-file-star-btn" title="<?php echo $file->starred ? 'Unstar' : 'Star'; ?>"><?php echo $file->starred ? '★' : '☆'; ?></button>
								</form>
								<?php endif; ?>
							</td>
							<td class="wfv-file-name" data-label="File"><span class="wfv-file-icon"><?php echo wfv_file_icon( $file->mime_type, $file->original_name ); ?></span><?php echo esc_html( $file->original_name ); ?></td>
							<?php if ( $is_admin ) : ?><td data-label="Uploaded by"><?php echo esc_html( $uploader_name ? $uploader_name : '—' ); ?></td><?php endif; ?>
							<td data-label="Size"><?php echo esc_html( size_format( $file->file_size ) ); ?></td>
							<td data-label="Uploaded"><?php echo esc_html( mysql2date( 'Y-m-d H:i', $file->created_at ) ); ?></td>
							<td data-label="Active shares"><?php echo $wfv_shared_browse ? '—' : (int) $active_count; ?></td>
							<td data-label="Actions">
								<?php if ( $wfv_shared_browse ) : ?>
									<div class="wfv-actions-cell">
										<a class="button button-primary" href="<?php echo esc_url( $shared_dl_url ); ?>">⬇ Download</a>
									</div>
								<?php else : ?>
								<div class="wfv-actions-cell">
									<a href="#" class="button wfv-toggle-shares" data-file="<?php echo (int) $file->id; ?>">Shares</a>
									<?php if ( $can_preview ) : ?>
										<button type="button" class="button wfv-preview-btn" data-url="<?php echo esc_url( $preview_url ); ?>" data-mime="<?php echo esc_attr( $file->mime_type ); ?>">👁 Preview</button>
									<?php endif; ?>
									<a class="button" href="<?php echo esc_url( $shared_dl_url ); ?>">⬇</a>
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
								<?php endif; ?>
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

	<?php /* Scripts enqueued via admin-files.js */ ?>
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
		<?php /* Styles enqueued via admin-notes.css */ ?>

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
				<?php wfv_render_import_trigger(); ?>
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

	<?php wfv_render_import_modal( 'notes' ); ?>

	<div class="wfv-toast" id="wfv-note-toast"></div>
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
		<?php /* Styles enqueued via admin-passwords.css */ ?>
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
		<?php /* Styles enqueued via admin-passwords.css */ ?>

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
				<?php wfv_render_import_trigger(); ?>
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

	<?php wfv_render_import_modal( 'passwords' ); ?>

	<div class="wfv-toast" id="wfv-pw-toast"></div>
	<?php
}
