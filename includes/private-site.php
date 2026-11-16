<?php
/**
 * Share links on a private WordPress.com site.
 *
 * A WordPress.com site set to Private turns away anyone who isn't on the site, before PressShot's
 * share page or link routes run: the page request gets the "You need to be logged in" screen and
 * REST gets a private_site 403. A share link is meant for people outside the site, so a site can
 * choose to let a live link (and only a live link) through:
 *
 * - the /s/{token} page, and
 * - the anonymous link routes under /pressshot/v1/links/{token}.
 *
 * This is off until the site turns it on (see pressshot_private_site_sharing_enabled()), because a
 * site set to Private has said nothing on it is public.
 *
 * What gets through is kept to the link itself:
 *
 * - The page gate is skipped only for a plain GET or HEAD of /s/{token}, with nothing in the query
 *   string but ?shot=. WordPress lets a query string add query variables to any URL, and everything
 *   after the gate would act on them: ?feed=comments-rss2 would print the site's comments and
 *   ?sitemap= would list its pages. A live link with anything extra is redirected to the plain URL.
 * - The page is drawn without the theme's header and footer, which would show the site's menu.
 * - The REST gate is never removed. The three link routes are answered ahead of it.
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

/**
 * Whether this site lets share links open while it is set to Private. Off by default. Turn it on with
 * the pressshot_share_on_private_site option (wp option update pressshot_share_on_private_site 1)
 * or the filter of the same name.
 */
function pressshot_private_site_sharing_enabled() {
	return (bool) apply_filters( 'pressshot_share_on_private_site', (bool) get_option( 'pressshot_share_on_private_site', false ) );
}

/** The priority a gate callback is hooked at, whichever way its name was spelled when it was added, or false. */
function pressshot_private_site_gate_priority( $hook, $callback ) {
	foreach ( array( $callback, ltrim( $callback, '\\' ) ) as $name ) {
		$priority = has_filter( $hook, $name );
		if ( false !== $priority ) {
			return $priority;
		}
	}
	return false;
}

/** Remove a gate callback, whichever way its name was spelled when it was added. True when one was removed. */
function pressshot_private_site_remove_gate( $hook, $callback, $priority ) {
	$removed = false;
	foreach ( array( $callback, ltrim( $callback, '\\' ) ) as $name ) {
		$removed = remove_filter( $hook, $name, $priority ) || $removed;
	}
	return $removed;
}

/**
 * What to do with a page request on a private site:
 *
 * - 'open':     a plain GET or HEAD of a live link's /s/{token} page. Skip the gate.
 * - 'redirect': a live link's page with extra query variables or query string. Send it to the plain URL.
 * - 'closed':   anything else. Leave the gate alone.
 *
 * The query isn't built yet at parse_request, so this reads the parsed request directly.
 */
function pressshot_private_site_share_request( $wp ) {
	if ( ! $wp instanceof WP || pressshot_share_rule() !== $wp->matched_rule || ! pressshot_private_site_sharing_enabled() ) {
		return 'closed';
	}
	$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) : 'GET'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Compared against a fixed list.
	if ( ! in_array( $method, array( 'GET', 'HEAD' ), true ) || ! empty( $_POST ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Only checks that nothing was posted.
		return 'closed';
	}
	$token = isset( $wp->query_vars['pressshot_share'] ) ? $wp->query_vars['pressshot_share'] : '';
	if ( ! pressshot_link_resolve( $token ) ) {
		return 'closed';
	}
	$extra_vars  = array_diff( array_keys( $wp->query_vars ), array( 'pressshot_share' ) );
	$extra_query = array_diff( array_keys( $_GET ), array( 'shot' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only the names are read.
	return $extra_vars || $extra_query ? 'redirect' : 'open';
}

/** A live link's plain page address: /s/{token}, keeping a valid ?shot=. */
function pressshot_private_site_plain_share_url( $token ) {
	$url  = pressshot_link_url( $token );
	$shot = pressshot_share_requested_shot();
	return $shot ? add_query_arg( 'shot', $shot, $url ) : $url;
}

/** Page requests: runs just before WordPress.com's gate and acts on pressshot_private_site_share_request(). */
function pressshot_private_site_open_share_page( $wp ) {
	if ( false === pressshot_private_site_gate_priority( 'parse_request', PRESSSHOT_PRIVATE_SITE_PAGE_GATE ) ) {
		return;
	}
	$state = pressshot_private_site_share_request( $wp );
	if ( 'redirect' === $state ) {
		nocache_headers();
		wp_safe_redirect( pressshot_private_site_plain_share_url( $wp->query_vars['pressshot_share'] ), 302, 'PressShot' );
		exit;
	}
	if ( 'open' === $state && pressshot_private_site_remove_gate( 'parse_request', PRESSSHOT_PRIVATE_SITE_PAGE_GATE, 100 ) ) {
		$GLOBALS['pressshot_private_site_opened'] = true;
	}
}
add_action( 'parse_request', 'pressshot_private_site_open_share_page', 99 );

/**
 * A share page let through the gate is drawn on its own, without the theme's header, footer, or
 * sidebars: on a private site those would show the menu and anything else the theme lists.
 */
add_filter(
	'template_include',
	function ( $template ) {
		return ! empty( $GLOBALS['pressshot_private_site_opened'] ) && pressshot_share_is_request()
			? __DIR__ . '/../templates/share-bare.php'
			: $template;
	},
	100
);

/**
 * REST: a read of a live link's routes is answered here, one step ahead of WordPress.com's gate,
 * which leaves an answer it is handed alone. The gate itself stays hooked, so nothing else
 * dispatched in the same PHP request gets through. Keyed on the matched handler, not the path.
 */
function pressshot_private_site_answer_link_route( $result, $request, $route = '', $handler = array() ) {
	if ( null !== $result || ! $request instanceof WP_REST_Request || ! in_array( $request->get_method(), array( 'GET', 'HEAD' ), true ) ) {
		return $result;
	}
	$callback = is_array( $handler ) && isset( $handler['callback'] ) ? $handler['callback'] : null;
	if ( ! in_array( $callback, array( 'pressshot_get_link', 'pressshot_get_link_media', 'pressshot_get_link_poster' ), true ) ) {
		return $result;
	}
	if ( false === pressshot_private_site_gate_priority( 'rest_dispatch_request', PRESSSHOT_PRIVATE_SITE_REST_GATE ) || ! pressshot_private_site_sharing_enabled() ) {
		return $result;
	}
	return pressshot_link_resolve( (string) $request['token'] ) ? call_user_func( $callback, $request ) : $result;
}
add_filter( 'rest_dispatch_request', 'pressshot_private_site_answer_link_route', 9, 4 );
