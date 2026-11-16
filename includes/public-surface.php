<?php
/**
 * Keeps shots, folders, and tags out of WordPress's own public surfaces.
 *
 * Shots are ordinary attachments and folders and tags are ordinary taxonomies, so without this
 * core would list them to anyone: the media REST routes, the taxonomy REST routes, attachment
 * pages, and oEmbed. For anyone without Library access (pressshot_can_use_library()), shots
 * don't exist on those surfaces, and the folder and tag routes refuse to answer.
 *
 * This hides shots from WordPress's listings. It doesn't protect the uploaded file itself, which
 * still lives at its normal uploads address.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Leaves shots out of GET /wp/v2/media for people without Library access, keeping any meta query already there. */
function pressshot_hide_shots_from_media_query( $args ) {
	if ( pressshot_can_use_library() ) {
		return $args;
	}
	$not_a_shot = array(
		'relation' => 'OR',
		array(
			'key'     => PRESSSHOT_SHOT_META,
			'compare' => 'NOT EXISTS',
		),
		array(
			'key'     => PRESSSHOT_SHOT_META,
			'value'   => '1',
			'compare' => '!=',
		),
	);
	$args['meta_query'] = empty( $args['meta_query'] )
		? array( $not_a_shot )
		: array(
			'relation' => 'AND',
			$args['meta_query'],
			$not_a_shot,
		);
	return $args;
}
add_filter( 'rest_attachment_query', 'pressshot_hide_shots_from_media_query' );

/** The controller object behind a matched REST handler, or null. */
function pressshot_handler_controller( $handler ) {
	$callback = is_array( $handler ) && isset( $handler['callback'] ) ? $handler['callback'] : null;
	return is_array( $callback ) && isset( $callback[0] ) && is_object( $callback[0] ) ? $callback[0] : null;
}

/** The taxonomy a terms controller serves (its property is protected, so read it from inside the class). */
function pressshot_terms_controller_taxonomy( WP_REST_Terms_Controller $controller ) {
	$read = function () {
		return $this->taxonomy;
	};
	return (string) Closure::bind( $read, $controller, WP_REST_Terms_Controller::class )();
}

/**
 * Before core runs a route: reading a shot through the media controller is the same 404 as a
 * missing attachment, and reading through the folder or tag controller needs Library access.
 *
 * This keys off the handler the server matched, not the path, because core matches routes
 * case-insensitively and also accepts ?rest_route=, so /wp/v2/MEDIA/{id} reaches the same code.
 */
function pressshot_guard_core_routes( $response, $handler, WP_REST_Request $request ) {
	if ( is_wp_error( $response ) || pressshot_can_use_library() ) {
		return $response;
	}
	// Writes are left to core, which already refuses them without the right capability.
	if ( ! in_array( $request->get_method(), array( 'GET', 'HEAD' ), true ) ) {
		return $response;
	}
	$controller = pressshot_handler_controller( $handler );
	if ( $controller instanceof WP_REST_Attachments_Controller ) {
		$url = $request->get_url_params();
		$id  = isset( $url['id'] ) ? $url['id'] : null;
		if ( is_scalar( $id ) && pressshot_is_shot( (int) $id ) ) {
			return new WP_Error( 'rest_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 404 ) ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- Core's own message, so a shot looks like a missing attachment.
		}
	}
	if ( $controller instanceof WP_REST_Terms_Controller && in_array( pressshot_terms_controller_taxonomy( $controller ), array( 'pressshot_folder', 'pressshot_tag' ), true ) ) {
		return pressshot_library_permission();
	}
	return $response;
}
add_filter( 'rest_request_before_callbacks', 'pressshot_guard_core_routes', 10, 3 );

/** True when this front-end request is a shot's attachment page and the visitor can't use the Library. */
function pressshot_is_hidden_attachment_page() {
	return is_attachment() && pressshot_is_shot( get_queried_object_id() ) && ! pressshot_can_use_library();
}

/** A shot's attachment page (?attachment_id= or its permalink) is a 404 for anyone without Library access. */
function pressshot_hide_shot_attachment_page() {
	global $wp_query;
	if ( ! pressshot_is_hidden_attachment_page() ) {
		return;
	}
	$GLOBALS['pressshot_hid_attachment_page'] = true;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
}
// Before redirect_canonical (priority 10), which would otherwise redirect to the permalink or the file.
add_action( 'template_redirect', 'pressshot_hide_shot_attachment_page', 1 );

/**
 * True when a canonical redirect on this request would lead a non-Library visitor to a shot. Core's
 * 404 branch sends ?p=, ?page_id=, and ?attachment_id= for any post to its permalink, which would
 * reveal a shot's slug; a name or pagename that is a shot's path counts too.
 */
function pressshot_redirect_would_reveal_shot() {
	if ( ! empty( $GLOBALS['pressshot_hid_attachment_page'] ) ) {
		return true;
	}
	if ( ! is_404() || pressshot_can_use_library() ) {
		return false;
	}
	foreach ( array( 'p', 'page_id', 'attachment_id' ) as $var ) {
		$id = get_query_var( $var );
		if ( is_scalar( $id ) && (int) $id > 0 && pressshot_is_shot( (int) $id ) ) {
			return true;
		}
	}
	foreach ( array( 'name', 'pagename', 'attachment' ) as $var ) {
		$slug = get_query_var( $var );
		$post = is_string( $slug ) && '' !== $slug ? get_page_by_path( $slug, OBJECT, 'attachment' ) : null;
		if ( $post && pressshot_is_shot( $post->ID ) ) {
			return true;
		}
	}
	return false;
}

// A hidden shot's 404 stays put: no redirect to its permalink or file.
add_filter(
	'redirect_canonical',
	function ( $redirect ) {
		return pressshot_redirect_would_reveal_shot() ? false : $redirect;
	}
);

/** oEmbed for a shot's attachment page finds nothing, so it can't reveal the title or file. */
add_filter(
	'oembed_request_post_id',
	function ( $post_id ) {
		return $post_id && pressshot_is_shot( $post_id ) && ! pressshot_can_use_library() ? 0 : $post_id;
	}
);
