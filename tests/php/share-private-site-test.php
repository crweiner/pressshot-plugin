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
 * (rest_dispatch_request, priority 10) for anyone not on the site. A live share link must get past both,
 * signed out; nothing else may. Stand-ins named like WordPress.com's callbacks play the gates here.
 */
if ( ! function_exists( 'Private_Site\\rest_dispatch_request' ) ) {
	eval( 'namespace Private_Site; function parse_request() {} function rest_dispatch_request( $result, $request, $route ) { return null !== $result ? $result : new \WP_Error( "private_site", "This site is private.", array( "status" => 403 ) ); }' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- Test stand-in for a host callback.
}
add_action( 'parse_request', '\Private_Site\parse_request', 100 );
add_filter( 'rest_dispatch_request', '\Private_Site\rest_dispatch_request', 10, 3 );

wp_set_current_user( $author );
$live = pressshot_link_create( 'shot', $shot );
wp_set_current_user( 0 );
$token = $live['token'];

$response = $call( 'GET', '/pressshot/v1/links/' . $token );
$check( 'private site: signed-out read of a live link answers 200', 200 === $response->get_status() );
$check( 'private site: the REST gate is back after the link answers', 10 === has_filter( 'rest_dispatch_request', '\Private_Site\rest_dispatch_request' ) );
$check( 'private site: other routes still get private_site 403', 403 === $call( 'GET', '/wp/v2/posts' )->get_status() );
$check( 'private site: an unknown token still gets private_site 403', 403 === $call( 'GET', '/pressshot/v1/links/' . str_repeat( 'a', 32 ) )->get_status() );
$check( 'private site: a write to a link route is not answered', $call( 'POST', '/pressshot/v1/links/' . $token )->get_status() >= 400 );
$check( 'private site: a write leaves the REST gate in place', 10 === has_filter( 'rest_dispatch_request', '\Private_Site\rest_dispatch_request' ) );

$share_wp                                = new WP();
$share_wp->matched_rule                  = pressshot_share_rule();
$share_wp->query_vars['pressshot_share'] = str_repeat( 'b', 32 );
pressshot_private_site_open_share_page( $share_wp );
$check( 'private site: an unknown token keeps the page gate', 100 === has_action( 'parse_request', '\Private_Site\parse_request' ) );
$other_wp                                = new WP();
$other_wp->matched_rule                  = pressshot_shots_rule();
$other_wp->query_vars['pressshot_share'] = $token;
pressshot_private_site_open_share_page( $other_wp );
$check( 'private site: a share token on another URL keeps the page gate', 100 === has_action( 'parse_request', '\Private_Site\parse_request' ) );
$share_wp->query_vars['pressshot_share'] = $token;
pressshot_private_site_open_share_page( $share_wp );
$check( 'private site: a live link\'s /s/ page skips the page gate', false === has_action( 'parse_request', '\Private_Site\parse_request' ) );

wp_set_current_user( $author );
pressshot_link_revoke( 'shot', $shot );
wp_set_current_user( 0 );
add_action( 'parse_request', '\Private_Site\parse_request', 100 );
pressshot_private_site_open_share_page( $share_wp );
$check( 'private site: a revoked link\'s page keeps the page gate', 100 === has_action( 'parse_request', '\Private_Site\parse_request' ) );
$check( 'private site: a revoked link\'s route gets private_site 403', 403 === $call( 'GET', '/pressshot/v1/links/' . $token )->get_status() );

remove_action( 'parse_request', '\Private_Site\parse_request', 100 );
remove_filter( 'rest_dispatch_request', '\Private_Site\rest_dispatch_request', 10 );
