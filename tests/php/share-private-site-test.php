<?php
/**
 * On a private WordPress.com site, REST refuses cookie-only requests, so a share page viewed by a signed-in
 * teammate must give its media addresses a REST nonce. Signed-out visitors get plain token addresses.
 */
require __DIR__ . '/harness.php';

$author = $make_user( 'author' );
$shot   = $make_shot( $author );
wp_set_current_user( $author );
$link = pressshot_link_create( 'shot', $shot );
$url  = rest_url( 'pressshot/v1/links/' . $link['token'] . '/shots/' . $shot . '/media' );

$check( 'signed-in share media address carries a REST nonce', false !== strpos( pressshot_share_media_url( $url ), '_wpnonce=' ) );
$check( 'the nonce is a valid wp_rest nonce for this person', 1 === wp_verify_nonce( wp_parse_args( wp_parse_url( pressshot_share_media_url( $url ), PHP_URL_QUERY ) )['_wpnonce'], 'wp_rest' ) );
wp_set_current_user( 0 );
$check( 'signed-out share media address has no nonce', $url === pressshot_share_media_url( $url ) );
pressshot_link_revoke( 'shot', $shot );

/*
 * WordPress.com's Private setting gates page requests (parse_request, priority 100) and REST
 * (rest_dispatch_request, priority 10) for anyone not on the site. Once the site turns sharing on, a
 * live share link must get past both, signed out; nothing else may. Stand-ins named like
 * WordPress.com's callbacks play the gates here.
 */
if ( ! function_exists( 'Private_Site\\rest_dispatch_request' ) ) {
	eval( 'namespace Private_Site; function parse_request() {} function rest_dispatch_request( $result, $request, $route ) { return null !== $result ? $result : new \WP_Error( "private_site", "This site is private.", array( "status" => 403 ) ); }' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- Test stand-in for a host callback.
}
add_action( 'parse_request', '\Private_Site\parse_request', 100 );
add_filter( 'rest_dispatch_request', '\Private_Site\rest_dispatch_request', 10, 3 );
$rest_gate_on = function () {
	return 10 === has_filter( 'rest_dispatch_request', '\Private_Site\rest_dispatch_request' );
};
$page_gate_on = function () {
	return 100 === has_action( 'parse_request', '\Private_Site\parse_request' );
};
$sharing      = function ( $on ) {
	remove_all_filters( 'pressshot_share_on_private_site' );
	add_filter( 'pressshot_share_on_private_site', $on ? '__return_true' : '__return_false' );
};
$share_wp     = function ( $token, array $extra = array() ) {
	$wp               = new WP();
	$wp->matched_rule = pressshot_share_rule();
	$wp->query_vars   = array_merge( array( 'pressshot_share' => $token ), $extra );
	return $wp;
};
$saved_get    = $_GET;
$saved_post   = $_POST;
$saved_method = isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : null;
$_GET                      = array();
$_POST                     = array();
$_SERVER['REQUEST_METHOD'] = 'GET';

wp_set_current_user( $author );
$live = pressshot_link_create( 'shot', $shot );
wp_set_current_user( 0 );
$token = $live['token'];
$route = '/pressshot/v1/links/' . $token;

// Off until the site turns it on.
// Checked without this site's own saved choice, which is put back straight after.
$had_sharing_option   = false !== get_option( 'pressshot_share_on_private_site', false );
$saved_sharing_option = get_option( 'pressshot_share_on_private_site', null );
delete_option( 'pressshot_share_on_private_site' );
remove_all_filters( 'pressshot_share_on_private_site' );
$check( 'private site: sharing is off by default', false === pressshot_private_site_sharing_enabled() );
if ( $had_sharing_option ) {
	update_option( 'pressshot_share_on_private_site', $saved_sharing_option );
}
$sharing( false );
$check( 'private site, sharing off: a live link\'s route gets private_site 403', 403 === $call( 'GET', $route )->get_status() );
$check( 'private site, sharing off: a live link\'s page stays closed', 'closed' === pressshot_private_site_share_request( $share_wp( $token ) ) );
pressshot_private_site_open_share_page( $share_wp( $token ) );
$check( 'private site, sharing off: the page gate stays', $page_gate_on() );

// REST, with sharing on.
$sharing( true );
$check( 'private site: signed-out read of a live link answers 200', 200 === $call( 'GET', $route )->get_status() );
$check( 'private site: the REST gate stays hooked while a link answers', $rest_gate_on() );
$check( 'private site: a live link\'s media answers 200', 200 === $call( 'GET', $route . '/shots/' . $shot . '/media' )->get_status() );
$check( 'private site: other routes still get private_site 403', 403 === $call( 'GET', '/wp/v2/posts' )->get_status() );
$check( 'private site: an unknown token still gets private_site 403', 403 === $call( 'GET', '/pressshot/v1/links/' . str_repeat( 'a', 32 ) )->get_status() );
$check( 'private site: a write to a link route is not answered', $call( 'POST', $route )->get_status() >= 400 );
$check( 'private site: a route that doesn\'t exist under a live link is a 404', 404 === $call( 'GET', $route . '/nope' )->get_status() );
$check( 'private site: the REST gate is still hooked after that 404', $rest_gate_on() );
$check( 'private site: other routes still get 403 after that 404', 403 === $call( 'GET', '/wp/v2/posts' )->get_status() );

// Pages, with sharing on. Only a plain /s/{token} opens; anything riding along is sent to the plain URL.
$check( 'private site: a plain live link page opens', 'open' === pressshot_private_site_share_request( $share_wp( $token ) ) );
$check( 'private site: an unknown token stays closed', 'closed' === pressshot_private_site_share_request( $share_wp( str_repeat( 'b', 32 ) ) ) );
foreach ( array( 'feed' => 'comments-rss2', 'sitemap' => 'posts', 'p' => '1', 's' => 'x', 'withcomments' => '1' ) as $var => $value ) {
	$check( "private site: a live link with ?$var= is redirected, not opened", 'redirect' === pressshot_private_site_share_request( $share_wp( $token, array( $var => $value ) ) ) );
}
$_GET = array( 'utm_source' => 'chat' );
$check( 'private site: a live link with an unknown query string is redirected, not opened', 'redirect' === pressshot_private_site_share_request( $share_wp( $token ) ) );
$_GET = array( 'shot' => (string) $shot );
$check( 'private site: ?shot= alone still opens', 'open' === pressshot_private_site_share_request( $share_wp( $token ) ) );
$check( 'private site: the plain URL keeps ?shot=', add_query_arg( 'shot', $shot, pressshot_link_url( $token ) ) === pressshot_private_site_plain_share_url( $token ) );
$_GET = array( 'shot' => '1', 'feed' => 'rss2' );
$check( 'private site: the plain URL drops everything else', add_query_arg( 'shot', 1, pressshot_link_url( $token ) ) === pressshot_private_site_plain_share_url( $token ) );
$_GET                      = array();
$_SERVER['REQUEST_METHOD'] = 'POST';
$check( 'private site: a POST to a live link page stays closed', 'closed' === pressshot_private_site_share_request( $share_wp( $token ) ) );
$_SERVER['REQUEST_METHOD'] = 'GET';
$_POST                     = array( 'feed' => 'rss2' );
$check( 'private site: a posted body keeps a live link page closed', 'closed' === pressshot_private_site_share_request( $share_wp( $token ) ) );
$_POST = array();

$other_wp               = $share_wp( $token );
$other_wp->matched_rule = pressshot_shots_rule();
pressshot_private_site_open_share_page( $other_wp );
$check( 'private site: a share token on another URL keeps the page gate', $page_gate_on() );
pressshot_private_site_open_share_page( $share_wp( str_repeat( 'b', 32 ) ) );
$check( 'private site: an unknown token keeps the page gate', $page_gate_on() );
pressshot_private_site_open_share_page( $share_wp( $token ) );
$check( 'private site: a live link\'s /s/ page skips the page gate', ! $page_gate_on() );
$check( 'private site: an opened page is marked for the bare template', ! empty( $GLOBALS['pressshot_private_site_opened'] ) );
$bare = file_get_contents( dirname( __DIR__, 2 ) . '/templates/share-bare.php' );
$check( 'private site: the bare template has no theme header or footer', false === strpos( $bare, 'get_header' ) && false === strpos( $bare, 'get_footer' ) && false === strpos( $bare, 'template-part' ) );
unset( $GLOBALS['pressshot_private_site_opened'] );

wp_set_current_user( $author );
pressshot_link_revoke( 'shot', $shot );
wp_set_current_user( 0 );
add_action( 'parse_request', '\Private_Site\parse_request', 100 );
pressshot_private_site_open_share_page( $share_wp( $token ) );
$check( 'private site: a revoked link\'s page keeps the page gate', $page_gate_on() );
$check( 'private site: a revoked link\'s route gets private_site 403', 403 === $call( 'GET', $route )->get_status() );

// A site that isn't private has no gates, and nothing here steps in.
remove_action( 'parse_request', '\Private_Site\parse_request', 100 );
remove_filter( 'rest_dispatch_request', '\Private_Site\rest_dispatch_request', 10 );
$check( 'public site: the link route filter leaves the request alone', null === pressshot_private_site_answer_link_route( null, new WP_REST_Request( 'GET', $route ), $route, array( 'callback' => 'pressshot_get_link' ) ) );

remove_all_filters( 'pressshot_share_on_private_site' );
$_GET  = $saved_get;
$_POST = $saved_post;
if ( null === $saved_method ) {
	unset( $_SERVER['REQUEST_METHOD'] );
} else {
	$_SERVER['REQUEST_METHOD'] = $saved_method;
}
