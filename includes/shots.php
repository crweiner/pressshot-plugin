<?php
/**
 * What makes an attachment a shot, who may use the Library, and the register route the
 * PressShot app calls after it uploads a capture.
 *
 * A shot is an attachment with meta _pressshot_capture = 1. Only shots appear in the Library.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PRESSSHOT_SHOT_META   = '_pressshot_capture';
const PRESSSHOT_SOURCE_META = '_pressshot_source';

/** True when the id is an attachment that was registered as a shot. */
function pressshot_is_shot( $id ) {
	$id   = (int) $id;
	$post = $id > 0 ? get_post( $id ) : null;
	return $post && 'attachment' === $post->post_type && 1 === (int) get_post_meta( $id, PRESSSHOT_SHOT_META, true );
}

/** The apps allowed to register shots. Sites can add more with the pressshot_shot_sources filter. */
function pressshot_shot_sources() {
	$sources = apply_filters( 'pressshot_shot_sources', array( 'pressshot' ) );
	return array_values( array_filter( array_map( 'strval', (array) $sources ), 'strlen' ) );
}

/** Library access: signed in and allowed to upload. Every Library route checks this per request. */
function pressshot_can_use_library() {
	return is_user_logged_in() && current_user_can( 'upload_files' );
}

/** Permission callback for the Library routes. */
function pressshot_library_permission() {
	if ( pressshot_can_use_library() ) {
		return true;
	}
	return new WP_Error(
		'pressshot_forbidden',
		__( 'You don\'t have access to the Library on this site.', 'pressshot' ),
		array( 'status' => is_user_logged_in() ? 403 : 401 )
	);
}

/** How long after VideoPress creates a video its uploader may still claim it, in seconds. */
const PRESSSHOT_VIDEOPRESS_CLAIM_WINDOW = 900;

/**
 * VideoPress creates an uploaded video's attachment in the name of the site's connection account,
 * not the person who recorded it. That person may claim it once: a VideoPress video that isn't a shot
 * yet, created in the last 15 minutes, whose VideoPress ID matches the one the upload gave their app.
 */
function pressshot_can_claim_videopress( WP_Post $post, $guid ) {
	if ( ! is_string( $guid ) || '' === $guid || 'video/videopress' !== $post->post_mime_type || pressshot_is_shot( $post->ID ) ) {
		return false;
	}
	$known = (string) pressshot_videopress_guid( $post->ID );
	if ( '' === $known || ! hash_equals( $known, $guid ) ) {
		return false;
	}
	$age = time() - (int) strtotime( $post->post_date_gmt . ' UTC' );
	return $age >= -60 && $age <= PRESSSHOT_VIDEOPRESS_CLAIM_WINDOW;
}

/** The author of a live attachment may register it, and so may the uploader of a fresh VideoPress video. */
function pressshot_register_permission( WP_REST_Request $request ) {
	$post = get_post( (int) $request['id'] );
	if ( ! $post || 'attachment' !== $post->post_type || 'trash' === $post->post_status ) {
		return new WP_Error( 'pressshot_not_found', __( 'Shot not found.', 'pressshot' ), array( 'status' => 404 ) );
	}
	if ( ! is_user_logged_in() ) {
		return new WP_Error( 'pressshot_forbidden', __( 'You don\'t have access to the Library on this site.', 'pressshot' ), array( 'status' => 401 ) );
	}
	$owner = (int) $post->post_author === get_current_user_id();
	if ( ! current_user_can( 'upload_files' ) || ! ( $owner || pressshot_can_claim_videopress( $post, $request['videopressGuid'] ) ) ) {
		return new WP_Error( 'pressshot_forbidden', __( 'You can\'t change this shot.', 'pressshot' ), array( 'status' => 403 ) );
	}
	return true;
}

/** Marks an attachment as a shot. Registering twice is fine. */
function pressshot_register_shot( WP_REST_Request $request ) {
	$id     = (int) $request['id'];
	$source = (string) $request['source'];
	if ( ! in_array( $source, pressshot_shot_sources(), true ) ) {
		return new WP_Error( 'pressshot_unknown_source', __( 'That source isn\'t allowed to add shots.', 'pressshot' ), array( 'status' => 400 ) );
	}
	// A claimed VideoPress video (see pressshot_register_permission) becomes the uploader's.
	if ( (int) get_post_field( 'post_author', $id ) !== get_current_user_id() ) {
		wp_update_post( array( 'ID' => $id, 'post_author' => get_current_user_id() ) );
	}
	update_post_meta( $id, PRESSSHOT_SHOT_META, 1 );
	update_post_meta( $id, PRESSSHOT_SOURCE_META, $source );
	$link = pressshot_link_for( 'shot', $id );
	$link = $link ? $link : pressshot_link_create( 'shot', $id );
	if ( is_wp_error( $link ) ) {
		return $link;
	}
	return rest_ensure_response(
		array(
			'id'       => $id,
			'source'   => $source,
			'shareUrl' => $link['url'],
		)
	);
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'pressshot/v1',
			'/captures/(?P<id>[1-9][0-9]*)/register',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'pressshot_register_shot',
				'permission_callback' => 'pressshot_register_permission',
				'args'                => array(
					'source'         => array(
						'type'     => 'string',
						'required' => true,
					),
					'videopressGuid' => array(
						'type'    => 'string',
						'pattern' => '^[A-Za-z0-9]{8}$',
					),
				),
			)
		);
	}
);
