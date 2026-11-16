<?php
/**
 * Share links on a private WordPress.com site.
 *
 * A WordPress.com site set to Private turns away anyone who isn't on the site, before PressShot's
 * share page or link routes run: the page request gets the "You need to be logged in" screen and
 * REST gets a private_site 403. A share link is meant for people outside the site, so for a live
 * link (and only a live link) PressShot steps around those two gates for that one request:
 *
 * - the /s/{token} page, and
 * - the anonymous link routes under /pressshot/v1/links/{token}.
 *
 * An unknown, revoked, or expired token still meets the site's gate, and everything else on the site
 * stays private. WordPress.com still shows the site name as "Private Site" to visitors.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** WordPress.com's page gate (parse_request, priority 100) and REST gate (rest_dispatch_request, priority 10). */
const PRESSSHOT_PRIVATE_SITE_PAGE_GATE = '\Private_Site\parse_request';
const PRESSSHOT_PRIVATE_SITE_REST_GATE = '\Private_Site\rest_dispatch_request';

/** Remove a gate callback, whichever way its name was spelled when it was added. True when one was removed. */
function pressshot_private_site_remove_gate( $hook, $callback, $priority ) {
	$removed = false;
	foreach ( array( $callback, ltrim( $callback, '\\' ) ) as $name ) {
		$removed = remove_filter( $hook, $name, $priority ) || $removed;
	}
	return $removed;
}

/** The token in a /pressshot/v1/links/{token} route, or ''. */
function pressshot_private_site_link_route_token( $route ) {
	return is_string( $route ) && preg_match( '#^/pressshot/v1/links/([^/]+)(?:/|$)#', $route, $m ) ? $m[1] : '';
}

/**
 * Page requests: a /s/{token} request for a live link skips the private-site screen. Runs just before
 * WordPress.com's gate. The query isn't built yet, so this reads the parsed request directly.
 */
function pressshot_private_site_open_share_page( $wp ) {
	if ( ! $wp instanceof WP || pressshot_share_rule() !== $wp->matched_rule ) {
		return;
	}
	$token = isset( $wp->query_vars['pressshot_share'] ) ? $wp->query_vars['pressshot_share'] : '';
	if ( pressshot_link_resolve( $token ) ) {
		pressshot_private_site_remove_gate( 'parse_request', PRESSSHOT_PRIVATE_SITE_PAGE_GATE, 100 );
	}
}
add_action( 'parse_request', 'pressshot_private_site_open_share_page', 99 );

/**
 * REST: a read of a live link's routes skips the private_site 403. The gate is put back once the
 * request is answered, so nothing else dispatched in the same PHP request gets through.
 */
function pressshot_private_site_open_link_routes( $result, $server, $request ) {
	if ( null !== $result || ! $request instanceof WP_REST_Request || ! in_array( $request->get_method(), array( 'GET', 'HEAD' ), true ) ) {
		return $result;
	}
	if ( pressshot_link_resolve( pressshot_private_site_link_route_token( $request->get_route() ) )
		&& pressshot_private_site_remove_gate( 'rest_dispatch_request', PRESSSHOT_PRIVATE_SITE_REST_GATE, 10 ) ) {
		add_filter( 'rest_request_after_callbacks', 'pressshot_private_site_close_link_routes', 10, 1 );
	}
	return $result;
}
add_filter( 'rest_pre_dispatch', 'pressshot_private_site_open_link_routes', 10, 3 );

/** Put WordPress.com's REST gate back after a link route has answered. */
function pressshot_private_site_close_link_routes( $response ) {
	remove_filter( 'rest_request_after_callbacks', 'pressshot_private_site_close_link_routes', 10 );
	add_filter( 'rest_dispatch_request', PRESSSHOT_PRIVATE_SITE_REST_GATE, 10, 3 );
	return $response;
}
