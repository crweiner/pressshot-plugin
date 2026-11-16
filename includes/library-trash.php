<?php
/**
 * Lets a PressShot Library move shots to WordPress's Trash and restore them.
 *
 * WordPress only shows a Trash for media when MEDIA_TRASH is set in wp-config.php, which a plugin
 * can't do. The Trash itself works without it: a trashed attachment keeps its file, can be restored,
 * and is removed for good by WordPress's own daily cleanup after EMPTY_TRASH_DAYS (30 by default).
 * This file adds the routes a Library needs to use that: trash and restore. It stores nothing of its own.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The attachment must be a shot, and the caller must have Library access and be allowed to delete it. */
function pressshot_trash_permission( WP_REST_Request $request ) {
	$post = get_post( (int) $request['id'] );
	if ( ! $post || 'attachment' !== $post->post_type || ! pressshot_is_shot( $post->ID ) ) {
		return new WP_Error( 'pressshot_not_found', __( 'Shot not found.', 'pressshot' ), array( 'status' => 404 ) );
	}
	$access = pressshot_library_permission();
	if ( is_wp_error( $access ) ) {
		return $access;
	}
	if ( ! current_user_can( 'delete_post', $post->ID ) ) {
		return new WP_Error( 'pressshot_forbidden', __( 'You can\'t change this shot.', 'pressshot' ), array( 'status' => 403 ) );
	}
	return true;
}

/** Reads the stored status. get_post_status() reports an attachment's parent's status, not its own. */
function pressshot_is_trashed( $id ) {
	$post = get_post( $id );
	return $post && 'trash' === $post->post_status;
}

function pressshot_trash_state( $id ) {
	return array(
		'id'      => (int) $id,
		'trashed' => pressshot_is_trashed( $id ),
	);
}

/** Moves a shot to Trash. Asking twice is fine; the second call changes nothing. */
function pressshot_trash_shot( WP_REST_Request $request ) {
	$id = (int) $request['id'];
	if ( ! EMPTY_TRASH_DAYS ) {
		// With the Trash switched off, WordPress would delete the file for good. Refuse instead.
		return new WP_Error( 'pressshot_trash_off', __( 'Trash is switched off on this site.', 'pressshot' ), array( 'status' => 409 ) );
	}
	if ( ! pressshot_is_trashed( $id ) && ! wp_trash_post( $id ) ) {
		return new WP_Error( 'pressshot_trash_failed', __( 'The shot couldn\'t be moved to Trash.', 'pressshot' ), array( 'status' => 500 ) );
	}
	return rest_ensure_response( pressshot_trash_state( $id ) );
}

/** Takes a shot back out of Trash. */
function pressshot_restore_shot( WP_REST_Request $request ) {
	$id = (int) $request['id'];
	if ( pressshot_is_trashed( $id ) && ! wp_untrash_post( $id ) ) {
		return new WP_Error( 'pressshot_restore_failed', __( 'The shot couldn\'t be restored.', 'pressshot' ), array( 'status' => 500 ) );
	}
	return rest_ensure_response( pressshot_trash_state( $id ) );
}

add_action(
	'rest_api_init',
	function () {
		foreach ( array( 'trash' => 'pressshot_trash_shot', 'restore' => 'pressshot_restore_shot' ) as $action => $callback ) {
			register_rest_route(
				'pressshot/v1',
				'/captures/(?P<id>[1-9][0-9]*)/' . $action,
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => $callback,
					'permission_callback' => 'pressshot_trash_permission',
				)
			);
		}
	}
);
