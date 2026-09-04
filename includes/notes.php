<?php
/**
 * Notes logic — CRUD handlers, drag-and-drop reorder, and the
 * full admin-page render for the "My Notes" screen.
 *
 * @package SecureFileVault
 */

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
