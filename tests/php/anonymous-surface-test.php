<?php
/**
 * What a signed-out visitor can reach: core's media and taxonomy routes, attachment pages, oEmbed,
 * share pages, and /shots. Run with bin/test-php.sh. It creates and removes only its own users,
 * attachments, folder and tag terms, and link records.
 */
require __DIR__ . '/harness.php';

global $wp;
$GLOBALS['pressshot_test_terms'] = array();
register_shutdown_function(
	function () {
		foreach ( $GLOBALS['pressshot_test_made']['attachments'] as $id ) {
			pressshot_link_revoke( 'shot', $id );
		}
		foreach ( $GLOBALS['pressshot_test_terms'] as $term ) {
			if ( term_exists( $term[0], $term[1] ) ) {
				wp_delete_term( $term[0], $term[1] );
			}
		}
	}
);

$author = $make_user( 'author' );
$reader = $make_user( 'subscriber' );
$shot   = $make_shot( $author );
$plain  = $make_shot( $author );
delete_post_meta( $plain, '_pressshot_capture' );
$folder = wp_insert_term( 'Hidden folder ' . wp_generate_password( 5, false ), 'pressshot_folder' );
$tag    = wp_insert_term( 'Hidden tag ' . wp_generate_password( 5, false ), 'pressshot_tag' );
$GLOBALS['pressshot_test_terms'][] = array( $folder['term_id'], 'pressshot_folder' );
$GLOBALS['pressshot_test_terms'][] = array( $tag['term_id'], 'pressshot_tag' );
wp_set_object_terms( $shot, array( $folder['term_id'] ), 'pressshot_folder' );
wp_set_object_terms( $shot, array( $tag['term_id'] ), 'pressshot_tag' );

$media_ids = function () use ( $call ) {
	return array_map( 'intval', wp_list_pluck( $call( 'GET', '/wp/v2/media', array( 'per_page' => 100, 'include' => $GLOBALS['pressshot_test_made']['attachments'] ) )->get_data(), 'id' ) );
};

// Core media routes.
wp_set_current_user( 0 );
$ids = $media_ids();
$check( 'signed out, /wp/v2/media lists no shot', ! in_array( $shot, $ids, true ) );
$check( 'signed out, /wp/v2/media still lists ordinary attachments', in_array( $plain, $ids, true ) );
$single = $call( 'GET', '/wp/v2/media/' . $shot );
$check( 'signed out, /wp/v2/media/{shot} is a 404', 404 === $single->get_status() );
$missing = $call( 'GET', '/wp/v2/media/999999999' );
$check( 'the shot 404 matches a missing attachment', $single->get_data()['code'] === $missing->get_data()['code'] );
$check( 'signed out, /wp/v2/media/{ordinary attachment} still works', 200 === $call( 'GET', '/wp/v2/media/' . $plain )->get_status() );
$kept = pressshot_hide_shots_from_media_query( array( 'meta_query' => array( array( 'key' => 'other', 'value' => 'x' ) ) ) );
$check( 'an existing meta query is kept alongside the shot exclusion', 'AND' === $kept['meta_query']['relation'] && 'other' === $kept['meta_query'][0][0]['key'] && 'OR' === $kept['meta_query'][1]['relation'] );
wp_set_current_user( $reader );
$check( 'a subscriber gets no shot from /wp/v2/media either', ! in_array( $shot, $media_ids(), true ) && 404 === $call( 'GET', '/wp/v2/media/' . $shot )->get_status() );
wp_set_current_user( $author );
$check( 'an author still sees shots in /wp/v2/media', in_array( $shot, $media_ids(), true ) && 200 === $call( 'GET', '/wp/v2/media/' . $shot )->get_status() );

// Folder and tag routes.
$routes = array(
	'/wp/v2/pressshot-folders',
	'/wp/v2/pressshot-folders/' . $folder['term_id'],
	'/wp/v2/pressshot-tags',
	'/wp/v2/pressshot-tags/' . $tag['term_id'],
);
wp_set_current_user( 0 );
foreach ( $routes as $route ) {
	$response = $call( 'GET', $route );
	$check( "signed out, GET $route is a 401 that names no folder or tag", 401 === $response->get_status() && false === strpos( wp_json_encode( $response->get_data() ), 'Hidden' ) );
}
wp_set_current_user( $reader );
foreach ( $routes as $route ) {
	$check( "a subscriber gets 403 from GET $route", 403 === $call( 'GET', $route )->get_status() );
}
wp_set_current_user( $author );
$check( 'an author still sees folders', in_array( $folder['term_id'], wp_list_pluck( $call( 'GET', '/wp/v2/pressshot-folders', array( 'per_page' => 100, 'include' => array( $folder['term_id'] ) ) )->get_data(), 'id' ), true ) );
$check( 'an author still sees tags', 200 === $call( 'GET', '/wp/v2/pressshot-tags/' . $tag['term_id'] )->get_status() );

// Core matches routes case-insensitively (and ?rest_route= reaches the same router), so casing can't slip past the guards.
wp_set_current_user( 0 );
foreach ( array( '/wp/v2/MEDIA/', '/wp/v2/Media/', '/WP/V2/media/' ) as $base ) {
	$check( "signed out, GET {$base}{shot} is a 404", 404 === $call( 'GET', $base . $shot )->get_status() );
}
$check( 'signed out, GET /WP/v2/Media/{ordinary attachment} still works', 200 === $call( 'GET', '/WP/v2/Media/' . $plain )->get_status() );
$variants = array(
	'/wp/v2/PRESSSHOT-FOLDERS',
	'/WP/v2/pressshot-folders',
	'/wp/v2/PressShot-Folders',
	'/wp/v2/PressShot-Folders/' . $folder['term_id'],
	'/wp/v2/PRESSSHOT-TAGS',
	'/Wp/V2/Pressshot-Tags/' . $tag['term_id'],
);
foreach ( $variants as $route ) {
	$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', $route ) );
	$check( "signed out, GET $route is a 401", 401 === $response->get_status() );
}
$check( 'signed out, HEAD /wp/v2/PressShot-Folders is a 401', 401 === $call( 'HEAD', '/wp/v2/PressShot-Folders' )->get_status() );
$check( 'signed out, core categories are still readable', 200 === $call( 'GET', '/wp/v2/categories' )->get_status() );
wp_set_current_user( $author );
$check( 'an author reads mixed-case folder and media routes', 200 === $call( 'GET', '/wp/v2/PressShot-Folders' )->get_status() && 200 === $call( 'GET', '/wp/v2/MEDIA/' . $shot )->get_status() );

// Attachment pages and oEmbed.
$statuses = array();
add_filter(
	'status_header',
	function ( $header, $code ) use ( &$statuses ) {
		$statuses[] = $code;
		return $header;
	},
	10,
	2
);
$visit = function ( $id ) use ( &$statuses ) {
	$statuses = array();
	unset( $GLOBALS['pressshot_hid_attachment_page'] );
	$GLOBALS['wp_query']->query( array( 'attachment_id' => $id ) );
	$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
	pressshot_hide_shot_attachment_page();
	return is_404() && in_array( 404, $statuses, true );
};
wp_set_current_user( 0 );
$check( 'signed out, a shot\'s attachment page is a 404', $visit( $shot ) );
$check( 'the hidden attachment page is not redirected to its permalink or file', false === apply_filters( 'redirect_canonical', 'http://example.test/?attachment_id=' . $shot ) );
$check( 'signed out, an ordinary attachment page is untouched', ! $visit( $plain ) && is_attachment() );
$check( 'other pages keep canonical redirects', 'http://example.test/x/' === apply_filters( 'redirect_canonical', 'http://example.test/x/' ) );
$check( 'signed out, oEmbed finds no shot', 0 === apply_filters( 'oembed_request_post_id', $shot, '' ) && $plain === apply_filters( 'oembed_request_post_id', $plain, '' ) );
wp_set_current_user( $author );
$check( 'an author can open a shot\'s attachment page', ! $visit( $shot ) && is_attachment() );
unset( $GLOBALS['pressshot_hid_attachment_page'] );
$GLOBALS['wp_query']->init();
// Core's 404 branch would redirect ?p=, ?page_id=, and ?attachment_id= to the permalink, revealing a shot's slug.
$_SERVER['HTTP_HOST'] = (string) wp_parse_url( home_url(), PHP_URL_HOST ) . ( wp_parse_url( home_url(), PHP_URL_PORT ) ? ':' . wp_parse_url( home_url(), PHP_URL_PORT ) : '' );
$canonical = function ( $var, $id ) {
	unset( $GLOBALS['pressshot_hid_attachment_page'] );
	$GLOBALS['wp_query']->query( array( $var => $id ) );
	$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
	$GLOBALS['wp_query']->set_404();
	$GLOBALS['wp']->query_vars = array( $var => $id );
	return redirect_canonical( home_url( '/?' . $var . '=' . $id ), false );
};
wp_set_current_user( 0 );
foreach ( array( 'p', 'page_id' ) as $var ) {
	$to = $canonical( $var, $shot );
	$check( "signed out, ?$var={shot} is not redirected", empty( $to ) );
}
$control = $canonical( 'p', $plain );
$check( 'signed out, ?p={ordinary attachment} still redirects to its permalink (control)', is_string( $control ) && false !== strpos( $control, (string) get_post_field( 'post_name', $plain ) ) );
$GLOBALS['wp_query']->init();
$GLOBALS['wp']->query_vars = array();
$GLOBALS['wp_query']->query( array( 'name' => get_post_field( 'post_name', $shot ) ) );
$GLOBALS['wp_query']->set_404();
$check( 'signed out, a 404 for a shot\'s slug is not redirected', false === apply_filters( 'redirect_canonical', 'http://example.test/somewhere/' ) );
$GLOBALS['wp_query']->init();


// Share pages: a working link opens, every dead one is the same 404.
wp_set_current_user( 0 );
$render = function ( $token ) use ( &$statuses ) {
	$statuses = array();
	set_query_var( 'pressshot_share', $token );
	unset( $_GET['shot'] );
	$html = do_blocks( '<!-- wp:pressshot/share-view /-->' );
	set_query_var( 'pressshot_share', '' );
	return array( $html, in_array( 404, $statuses, true ) ? 404 : 200 );
};
$gone  = 'This link is no longer available.';
$live  = pressshot_link_create( 'shot', $shot );
$check( 'signed out, /s/ with a valid token is a 200', 200 === $render( $live['token'] )[1] );
pressshot_link_revoke( 'shot', $shot );
list( $html, $code ) = $render( $live['token'] );
$check( 'a revoked link is a 404', 404 === $code && false !== strpos( $html, $gone ) );
$expired = pressshot_link_create( 'shot', $shot );
update_post_meta( $expired['id'], '_pressshot_link_expires', time() - 60 );
list( $html, $code ) = $render( $expired['token'] );
$check( 'an expired link is a 404', 404 === $code && false !== strpos( $html, $gone ) );
pressshot_link_revoke( 'shot', $shot );
$trashed = pressshot_link_create( 'shot', $shot );
wp_trash_post( $shot );
list( $html, $code ) = $render( $trashed['token'] );
$check( 'a trashed shot\'s link is a 404', 404 === $code && false !== strpos( $html, $gone ) );
wp_untrash_post( $shot );

// /shots sends signed-out visitors to log in.
$_SERVER['HTTP_HOST']   = 'example.test';
$_SERVER['REQUEST_URI'] = '/shots/';
$wp->matched_rule       = pressshot_shots_rule();
set_query_var( 'pressshot_shots', '1' );
$location = null;
add_filter(
	'wp_redirect',
	$redirect_filter = function ( $to ) use ( &$location ) {
		$location = $to;
		throw new RuntimeException( 'redirected' );
	},
	0
);
try {
	pressshot_shots_gate();
} catch ( RuntimeException $e ) {
	// Expected: auth_redirect() redirects, then exits.
}
remove_filter( 'wp_redirect', $redirect_filter );
$check( 'signed out, /shots redirects to log in', is_string( $location ) && false !== strpos( $location, 'wp-login.php?redirect_to=' ) );
set_query_var( 'pressshot_shots', '' );
$wp->matched_rule = '';
