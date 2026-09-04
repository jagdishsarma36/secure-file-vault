<?php
/**
 * Sticky Notes — helper functions, AJAX endpoints, and shortcode.
 *
 * @package SecureFileVault
 */

/**
 * ------------------------------------------------------------------
 * Sticky note helper functions
 * ------------------------------------------------------------------
 */

function wfv_sticky_note_to_array( $n ) {
	return array(
		'id'         => (int) $n->id,
		'content'    => (string) $n->content,
		'priority'   => (string) $n->priority,
		'color'      => (string) $n->color,
		'pinned'     => (bool) $n->pinned,
		'sort_order' => (int) $n->sort_order,
		'updated_at' => mysql2date( 'c', $n->updated_at ),
	);
}

function wfv_sanitize_sticky_priority( $priority ) {
	$priority = sanitize_key( (string) $priority );
	return in_array( $priority, array( 'low', 'medium', 'high' ), true ) ? $priority : 'medium';
}

function wfv_sanitize_sticky_color( $color ) {
	$color = trim( (string) $color );
	if ( '' === $color ) {
		return '';
	}
	if ( preg_match( '/^#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?$/', $color ) ) {
		return strtolower( $color );
	}
	return '';
}

/**
 * Small, deliberately restricted formatting set for sticky notes — enough
 * for quick emphasis (bold/italic/underline/strike), simple lists, links,
 * and code blocks, nothing that could carry a script or layout-breaking
 * markup. The client-side composer mirrors this exact allow-list (see the
 * shortcode's JS sanitizeStickyHtml()) so localStorage-only notes get the
 * same protection even though they never pass through this server-side pass.
 */
function wfv_sticky_allowed_tags() {
	return array(
		'b'      => array(),
		'strong' => array(),
		'i'      => array(),
		'em'     => array(),
		'u'      => array(),
		's'      => array(),
		'strike' => array(),
		'br'     => array(),
		'p'      => array(),
		'pre'    => array(),
		'code'   => array(),
		'span'   => array( 'style' => true ),
		'font'   => array( 'color' => true ),
		'ul'     => array(),
		'ol'     => array(),
		'li'     => array(),
		'a'      => array(
			'href'   => true,
			'target' => true,
			'rel'    => true,
		),
	);
}

function wfv_sanitize_sticky_content( $html ) {
	// The composer already keeps content inside the allow-list, but this is
	// defense-in-depth for direct API calls. Browser contenteditable emits
	// block wrappers like <div> which wp_kses would otherwise strip and
	// silently join lines together — convert them to <br> separators first so
	// visual line breaks survive the round-trip.
	$html = preg_replace(
		array(
			'~<\s*(?:div|section|article|aside|header|footer|nav|main|figure|table|tr|td|th|address|form|fieldset|dl|dt|dd|blockquote|h[1-6])\b[^>]*>~i',
			'~<\s*/\s*(?:div|section|article|aside|header|footer|nav|main|figure|table|tr|td|th|address|form|fieldset|dl|dt|dd|blockquote|h[1-6])\s*>~i',
		),
		array( '<br>', '' ),
		(string) $html
	);
	$html = wp_kses( (string) $html, wfv_sticky_allowed_tags() );
	// Force safe attributes on any link rather than trusting whatever the editor produced.
	$html = preg_replace_callback(
		'/<a\s+[^>]*href="([^"]*)"[^>]*>/i',
		function ( $m ) {
			$href = esc_url( $m[1] );
			return '<a href="' . $href . '" target="_blank" rel="noopener noreferrer">';
		},
		$html
	);
	return trim( $html );
}

/**
 * ------------------------------------------------------------------
 * Sticky note AJAX endpoints
 * ------------------------------------------------------------------
 */

add_action( 'wp_ajax_wfv_sticky_list', 'wfv_ajax_sticky_list' );
function wfv_ajax_sticky_list() {
	if ( ! wfv_user_has_access() ) {
		wp_send_json_error( 'no_access', 403 );
	}
	check_ajax_referer( 'wfv_sticky_notes', 'nonce' );

	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . wfv_sticky_notes_table() . " WHERE created_by = %d ORDER BY pinned DESC, sort_order ASC, id DESC", get_current_user_id() ) );

	wp_send_json_success( array_map( 'wfv_sticky_note_to_array', $rows ) );
}

add_action( 'wp_ajax_wfv_sticky_save', 'wfv_ajax_sticky_save' );
function wfv_ajax_sticky_save() {
	if ( ! wfv_user_has_access() ) {
		wp_send_json_error( 'no_access', 403 );
	}
	check_ajax_referer( 'wfv_sticky_notes', 'nonce' );

	global $wpdb;
	$id       = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
	$content  = isset( $_POST['content'] ) ? wfv_sanitize_sticky_content( wp_unslash( $_POST['content'] ) ) : '';
	$priority = wfv_sanitize_sticky_priority( isset( $_POST['priority'] ) ? $_POST['priority'] : 'medium' );
	$color    = wfv_sanitize_sticky_color( isset( $_POST['color'] ) ? $_POST['color'] : '' );

	if ( '' === trim( wp_strip_all_tags( $content ) ) ) {
		wp_send_json_error( 'empty_content', 400 );
	}

	if ( $id ) {
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . wfv_sticky_notes_table() . " WHERE id = %d", $id ) );
		if ( ! wfv_user_can_manage_sticky_note( $existing ) ) {
			wp_send_json_error( 'forbidden', 403 );
		}
		$wpdb->update(
			wfv_sticky_notes_table(),
			array(
				'content'    => $content,
				'priority'   => $priority,
				'color'      => $color,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
	} else {
		$wpdb->insert(
			wfv_sticky_notes_table(),
			array(
				'content'    => $content,
				'priority'   => $priority,
				'color'      => $color,
				'pinned'     => 0,
				'sort_order' => 0,
				'created_by' => get_current_user_id(),
				'created_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s' )
		);
		$id = (int) $wpdb->insert_id;
	}

	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . wfv_sticky_notes_table() . " WHERE id = %d", $id ) );
	wp_send_json_success( wfv_sticky_note_to_array( $row ) );
}

add_action( 'wp_ajax_wfv_sticky_delete', 'wfv_ajax_sticky_delete' );
function wfv_ajax_sticky_delete() {
	if ( ! wfv_user_has_access() ) {
		wp_send_json_error( 'no_access', 403 );
	}
	check_ajax_referer( 'wfv_sticky_notes', 'nonce' );

	global $wpdb;
	$id  = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
	$row = $id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . wfv_sticky_notes_table() . " WHERE id = %d", $id ) ) : null;

	if ( ! wfv_user_can_manage_sticky_note( $row ) ) {
		wp_send_json_error( 'forbidden', 403 );
	}
	$wpdb->delete( wfv_sticky_notes_table(), array( 'id' => $id ), array( '%d' ) );
	wp_send_json_success();
}

add_action( 'wp_ajax_wfv_sticky_toggle_pin', 'wfv_ajax_sticky_toggle_pin' );
function wfv_ajax_sticky_toggle_pin() {
	if ( ! wfv_user_has_access() ) {
		wp_send_json_error( 'no_access', 403 );
	}
	check_ajax_referer( 'wfv_sticky_notes', 'nonce' );

	global $wpdb;
	$id  = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
	$row = $id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . wfv_sticky_notes_table() . " WHERE id = %d", $id ) ) : null;

	if ( ! wfv_user_can_manage_sticky_note( $row ) ) {
		wp_send_json_error( 'forbidden', 403 );
	}
	$wpdb->update( wfv_sticky_notes_table(), array( 'pinned' => $row->pinned ? 0 : 1 ), array( 'id' => $id ), array( '%d' ), array( '%d' ) );

	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . wfv_sticky_notes_table() . " WHERE id = %d", $id ) );
	wp_send_json_success( wfv_sticky_note_to_array( $row ) );
}

/**
 * Persist a user-defined manual order for sticky notes (drag-and-drop).
 * Accepts an ordered array of note ids; each matching note owned by the
 * current user gets a step=10 sort_order so ties preserve insertion order.
 */
add_action( 'wp_ajax_wfv_sticky_reorder', 'wfv_ajax_sticky_reorder' );
function wfv_ajax_sticky_reorder() {
	if ( ! wfv_user_has_access() ) {
		wp_send_json_error( 'no_access', 403 );
	}
	check_ajax_referer( 'wfv_sticky_notes', 'nonce' );

	$order = isset( $_POST['order'] ) ? (array) wp_unslash( $_POST['order'] ) : array();
	$order = array_values( array_filter( array_map( 'absint', $order ) ) );

	$user_id = get_current_user_id();
	global $wpdb;
	$table = wfv_sticky_notes_table();
	$step  = 10;
	$i     = 1;

	foreach ( $order as $note_id ) {
		if ( ! $note_id ) {
			continue;
		}
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $note_id ) );
		if ( ! $row || (int) $row->created_by !== $user_id ) {
			continue;
		}
		$wpdb->update( $table, array( 'sort_order' => $step * $i ), array( 'id' => $note_id ), array( '%d' ), array( '%d' ) );
		$i++;
	}

	wp_send_json_success();
}

/**
 * ------------------------------------------------------------------
 * Sticky Notes shortcode
 * ------------------------------------------------------------------
 */

add_shortcode( 'wfv_sticky_notes', 'wfv_sticky_notes_shortcode' );
function wfv_sticky_notes_shortcode( $atts ) {
	static $instance = 0;
	$instance++;

	$atts = shortcode_atts( array( 'height' => '480' ), $atts, 'wfv_sticky_notes' );
	$uid    = 'wfv-sn-' . $instance . '-' . wp_rand( 1000, 9999 );
	$height = max( 200, absint( $atts['height'] ) );

	$logged_in = is_user_logged_in();
	$nonce     = $logged_in ? wp_create_nonce( 'wfv_sticky_notes' ) : '';

	static $assets_done = false;
	ob_start();

	if ( ! $assets_done ) :
		$assets_done = true;
		wp_enqueue_style( 'wfv-sticky-notes', WFV_URL . 'assets/css/sticky-notes.css', array(), WFV_VERSION );
		wp_enqueue_script( 'wfv-sticky-notes', WFV_URL . 'assets/js/sticky-notes.js', array(), WFV_VERSION, true );
	endif;

	?>
	<div id="<?php echo esc_attr( $uid ); ?>" class="wfv-sn" data-wfv-config="<?php echo esc_attr( wp_json_encode( array(
		'uid'      => $uid,
		'loggedIn' => $logged_in,
		'ajaxurl'  => admin_url( 'admin-ajax.php' ),
		'nonce'    => $nonce,
		'height'   => $height,
	) ) ); ?>">
		<?php /* Styles enqueued via sticky-notes.css */ ?>

		<div class="wfv-sn-header">
			<div class="wfv-sn-title">
				📌 Sticky Notes
				<?php if ( $logged_in ) : ?>
					<span class="wfv-sn-storage-badge wfv-sn-on">Saved to your account</span>
				<?php else : ?>
					<span class="wfv-sn-storage-badge wfv-sn-off">Saved in this browser only</span>
				<?php endif; ?>
			</div>
			<div class="wfv-sn-filters">
				<button type="button" data-filter="all" class="wfv-sn-active">All</button>
				<button type="button" data-filter="pinned">📌 Pinned</button>
				<button type="button" data-filter="high">High</button>
				<button type="button" data-filter="medium">Medium</button>
				<button type="button" data-filter="low">Low</button>
			</div>
			<button type="button" class="wfv-sn-add" id="<?php echo esc_attr( $uid ); ?>-add">+ Add note</button>
		</div>

		<div class="wfv-sn-body">
			<div class="wfv-sn-composer" id="<?php echo esc_attr( $uid ); ?>-composer">
				<div class="wfv-sn-composer-toolbar">
					<button type="button" data-cmd="bold" title="Bold"><?php echo wfv_h5e_icon( 'bold' ); ?></button>
					<button type="button" data-cmd="italic" title="Italic"><?php echo wfv_h5e_icon( 'italic' ); ?></button>
					<button type="button" data-cmd="underline" title="Underline"><?php echo wfv_h5e_icon( 'underline' ); ?></button>
<button type="button" data-cmd="strikeThrough" title="Strikethrough"><?php echo wfv_h5e_icon( 'strike' ); ?></button>
				<span class="wfv-sn-sep"></span>
				<input type="color" class="wfv-sn-text-color" value="#0f172a" title="Text color">
				<input type="color" class="wfv-sn-hl-color" value="#FEF7E0" title="Highlight color">
				<span class="wfv-sn-sep"></span>
				<button type="button" data-cmd="insertUnorderedList" title="Bullet list"><?php echo wfv_h5e_icon( 'list-bullet' ); ?></button>
<button type="button" data-cmd="insertOrderedList" title="Numbered list"><?php echo wfv_h5e_icon( 'list-number' ); ?></button>
				<span class="wfv-sn-sep"></span>
				<button type="button" data-act="code" title="Code block"><?php echo wfv_h5e_icon( 'code' ); ?></button>
				<span class="wfv-sn-sep"></span>
				<button type="button" data-act="link" title="Insert link"><?php echo wfv_h5e_icon( 'link' ); ?></button>
					<button type="button" data-cmd="removeFormat" title="Clear formatting"><?php echo wfv_h5e_icon( 'eraser' ); ?></button>
				</div>
				<div class="wfv-sn-editable" contenteditable="true" data-placeholder="Write a quick note…"></div>
				<div class="wfv-sn-color-row">
					<span class="wfv-sn-color-row-label">Color</span>
					<button type="button" class="wfv-sn-swatch wfv-sn-default wfv-sn-active" data-color="" title="Default (priority color)"></button>
					<button type="button" class="wfv-sn-swatch" data-color="#FEF7E0" title="Yellow" style="background:#FEF7E0"></button>
					<button type="button" class="wfv-sn-swatch" data-color="#E6F4EA" title="Green" style="background:#E6F4EA"></button>
					<button type="button" class="wfv-sn-swatch" data-color="#E8F0FE" title="Blue" style="background:#E8F0FE"></button>
					<button type="button" class="wfv-sn-swatch" data-color="#FCE4EC" title="Pink" style="background:#FCE4EC"></button>
					<button type="button" class="wfv-sn-swatch" data-color="#F3E8FD" title="Purple" style="background:#F3E8FD"></button>
					<button type="button" class="wfv-sn-swatch" data-color="#FFF3E0" title="Orange" style="background:#FFF3E0"></button>
					<button type="button" class="wfv-sn-swatch" data-color="#FCE8E6" title="Red" style="background:#FCE8E6"></button>
					<button type="button" class="wfv-sn-swatch" data-color="#E0F2F1" title="Teal" style="background:#E0F2F1"></button>
					<button type="button" class="wfv-sn-swatch" data-color="#F1F3F4" title="Gray" style="background:#F1F3F4"></button>
					<button type="button" class="wfv-sn-swatch" data-color="#EFEBE9" title="Brown" style="background:#EFEBE9"></button>
					<input type="color" class="wfv-sn-color-picker" value="#FEF7E0" title="Custom color">
				</div>
				<div class="wfv-sn-composer-footer">
					<div class="wfv-sn-priority-choice">
						<label class="wfv-sn-priority-low"><input type="radio" name="<?php echo esc_attr( $uid ); ?>-priority" value="low"><span>Low</span></label>
						<label class="wfv-sn-priority-medium"><input type="radio" name="<?php echo esc_attr( $uid ); ?>-priority" value="medium" checked><span>Medium</span></label>
						<label class="wfv-sn-priority-high"><input type="radio" name="<?php echo esc_attr( $uid ); ?>-priority" value="high"><span>High</span></label>
					</div>
					<div class="wfv-sn-composer-actions">
						<button type="button" data-act="cancel">Cancel</button>
						<button type="button" data-act="save" class="wfv-sn-save">Save note</button>
					</div>
				</div>
			</div>
			<div class="wfv-sn-grid" id="<?php echo esc_attr( $uid ); ?>-grid"></div>
			<div class="wfv-sn-empty" id="<?php echo esc_attr( $uid ); ?>-empty" style="display:none;">No notes yet — click "+ Add note" to write your first one.</div>
		</div>
	</div>

	<?php /* Scripts enqueued via sticky-notes.js */ ?>
	<?php
	return ob_get_clean();
}
