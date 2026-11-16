<?php
/**
 * Integration checks for the Library block placed on any page: sign-in prompt, one render per
 * request, and the privacy hooks. Run with bin/test-php.sh.
 */
require __DIR__ . '/harness.php';

$author = $make_user( 'author' );
$reader = $make_user( 'subscriber' );
$mount  = function ( $markup = '<!-- wp:pressshot/library /-->' ) {
	pressshot_library_reset_rendered();
	wp_dequeue_script( 'pressshot-library-view-script' );
	return do_blocks( $markup );
};

// Signed out.
wp_set_current_user( 0 );
pressshot_library_reset_rendered();
$check( 'rendered flag is false before a render', false === pressshot_library_rendered() );
$html  = $mount();
$login = wp_login_url( pressshot_library_request_url() );
$check( 'signed out: shows the sign-in prompt', false !== strpos( $html, 'Sign in to see the Library.' ) );
$check( 'signed out: links to login and back to this page', false !== strpos( $html, 'href="' . esc_url( $login ) . '"' ) );
$check( 'signed out: no app mount', false === strpos( $html, 'id="pressshot-library"' ) );
$check( 'signed out: the prompt is marked as a message, so it gets an inset', false !== strpos( $html, 'is-message' ) );
$check( 'rendering marks the request', true === pressshot_library_rendered() );
$robots = apply_filters( 'wp_robots', array() );
$check( 'rendered page is noindex', ! empty( $robots['noindex'] ) && ! empty( $robots['nofollow'] ) );
$check( 'body class is added', in_array( 'has-pressshot-library', get_body_class(), true ) );

// Not rendered: nothing changes.
pressshot_library_reset_rendered();
$robots = apply_filters( 'wp_robots', array() );
$check( 'no render: robots untouched', empty( $robots['noindex'] ) );
$check( 'no render: no body class', ! in_array( 'has-pressshot-library', get_body_class(), true ) );

// Signed in.
wp_set_current_user( $reader );
$check( 'signed in without upload_files: no-access message', false !== strpos( $mount(), esc_html( "You don't have access to the Library on this site." ) ) );
$check( 'signed in without upload_files: the message gets an inset', false !== strpos( $mount(), 'is-message' ) );
wp_set_current_user( $author );
$check( 'author: mounts the app', false !== strpos( $mount(), 'id="pressshot-library"' ) );
$check( 'author: the app is not marked as a message', false === strpos( $mount(), 'is-message' ) );

// Second block.
$two = $mount( '<!-- wp:pressshot/library /--><!-- wp:pressshot/library /-->' );
$check( 'second block renders nothing', 1 === substr_count( $two, 'id="pressshot-library"' ) );
wp_set_current_user( 0 );
$two = $mount( '<!-- wp:pressshot/library /--><!-- wp:pressshot/library /-->' );
$check( 'second block renders at most one prompt', 1 === substr_count( $two, 'Sign in to see the Library.' ) );

// Early detection from the page's content, without rendering the block.
$with    = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'PS with', 'post_content' => '<!-- wp:pressshot/library /-->' ) );
$without = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'PS without', 'post_content' => '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->' ) );
$run_early = function ( $id ) {
	pressshot_library_reset_rendered();
	query_posts( array( 'page_id' => $id, 'post_type' => 'page' ) );
	do_action( 'template_redirect' );
};
$run_early( $with );
$robots = apply_filters( 'wp_robots', array() );
$check( 'page with the block: noindex before render', ! empty( $robots['noindex'] ) && ! empty( $robots['nofollow'] ) && ! pressshot_library_rendered() );
$check( 'page with the block: body class before render', in_array( 'has-pressshot-library', get_body_class(), true ) );
$run_early( $without );
$robots = apply_filters( 'wp_robots', array() );
$check( 'page without the block: no noindex', empty( $robots['noindex'] ) );
$check( 'page without the block: no body class', ! in_array( 'has-pressshot-library', get_body_class(), true ) );
wp_reset_query();
wp_delete_post( $with, true );
wp_delete_post( $without, true );
pressshot_library_reset_rendered();

// Theme color classes follow the active theme's own palette.
$palette_filter = function ( $slugs ) {
	return function ( $data ) use ( $slugs ) {
		$colors = array();
		foreach ( $slugs as $slug ) {
			$colors[] = array( 'slug' => $slug, 'name' => $slug, 'color' => '#123456' );
		}
		return $data->update_with( array( 'version' => 3, 'settings' => array( 'color' => array( 'palette' => $colors ) ) ) );
	};
};
$reset_json = function () {
	WP_Theme_JSON_Resolver::clean_cached_data();
	wp_cache_delete( 'wp_get_global_settings_custom', 'theme_json' );
	wp_cache_delete( 'wp_get_global_settings_theme', 'theme_json' );
};
$classes_for = function ( $slugs ) use ( $palette_filter, $reset_json ) {
	$f = $palette_filter( $slugs );
	add_filter( 'wp_theme_json_data_theme', $f );
	$reset_json();
	$mount_page = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'PS colors', 'post_content' => '<!-- wp:pressshot/library /-->' ) );
	pressshot_library_reset_rendered();
	query_posts( array( 'page_id' => $mount_page, 'post_type' => 'page' ) );
	do_action( 'template_redirect' );
	$out = get_body_class();
	wp_reset_query();
	wp_delete_post( $mount_page, true );
	remove_filter( 'wp_theme_json_data_theme', $f );
	$reset_json();
	return $out;
};
// Known palette (base and contrast only) injected through the theme.json filter, whatever theme is active.
$known_filter = $palette_filter( array( 'base', 'contrast' ) );
add_filter( 'wp_theme_json_data_theme', $known_filter );
$reset_json();
$known_slugs = array_column( (array) wp_get_global_settings( array( 'color', 'palette', 'theme' ) ), 'slug' );
remove_filter( 'wp_theme_json_data_theme', $known_filter );
$reset_json();
$check( 'a palette with base and contrast but no accent is read through the theme.json filter', in_array( 'base', $known_slugs, true ) && in_array( 'contrast', $known_slugs, true ) && ! in_array( 'accent', $known_slugs, true ) );
$with_all = $classes_for( array( 'base', 'contrast', 'accent' ) );
$check( 'palette with base, contrast, accent: theme-colors class', in_array( 'has-pressshot-theme-colors', $with_all, true ) );
$check( 'palette with base, contrast, accent: accent class', in_array( 'has-pressshot-accent', $with_all, true ) );
$no_accent = $classes_for( array( 'base', 'contrast' ) );
$check( 'palette without accent: theme-colors only', in_array( 'has-pressshot-theme-colors', $no_accent, true ) && ! in_array( 'has-pressshot-accent', $no_accent, true ) );
$no_contrast = $classes_for( array( 'base', 'accent' ) );
$check( 'palette missing contrast: no theme-colors class', ! in_array( 'has-pressshot-theme-colors', $no_contrast, true ) && in_array( 'has-pressshot-accent', $no_contrast, true ) );
$bare = $classes_for( array( 'accent-1' ) );
$check( 'palette with neither: no color classes', ! in_array( 'has-pressshot-theme-colors', $bare, true ) && ! in_array( 'has-pressshot-accent', $bare, true ) );
pressshot_library_reset_rendered();
$check( 'not a Library page: no color classes', ! in_array( 'has-pressshot-theme-colors', get_body_class(), true ) );

// Login return URL follows the real request, including a subdirectory.
$old_host = $_SERVER['HTTP_HOST'] ?? null;
$old_uri  = $_SERVER['REQUEST_URI'] ?? null;
$_SERVER['HTTP_HOST']   = 'example.com';
$_SERVER['REQUEST_URI'] = '/blog/library/?x=1';
wp_set_current_user( 0 );
$html = $mount();
preg_match( '#href="([^"]+)"#', $html, $m );
parse_str( (string) wp_parse_url( html_entity_decode( $m[1] ?? '' ), PHP_URL_QUERY ), $q );
$check( 'login return URL equals the request URL', isset( $q['redirect_to'] ) && 1 === preg_match( '#^https?://example\.com/blog/library/\?x=1$#', $q['redirect_to'] ) );
$_SERVER['HTTP_HOST'] = $old_host;
$_SERVER['REQUEST_URI'] = $old_uri;
if ( null === $old_host ) {
	unset( $_SERVER['HTTP_HOST'] );
}
if ( null === $old_uri ) {
	unset( $_SERVER['REQUEST_URI'] );
}

// Headers already sent (always true in the CLI).
$threw = false;
try {
	$out = $mount();
} catch ( Throwable $e ) {
	$threw = true;
}
$check( 'headers already sent: render still returns the prompt', ! $threw && false !== strpos( $out, 'Sign in to see the Library.' ) );

pressshot_library_reset_rendered();

// App layout and site data.
wp_set_current_user( $author );
$html = $mount();
$check( 'app layout off by default: no is-app-layout', false === strpos( $html, 'is-app-layout' ) );
$check( 'app layout off by default: data-app-layout is 0', false !== strpos( $html, 'data-app-layout="0"' ) );
$html = $mount( '<!-- wp:pressshot/library {"appLayout":true} /-->' );
$check( 'app layout on: wrapper has is-app-layout', false !== strpos( $html, 'is-app-layout' ) );
$check( 'app layout on: data-app-layout is 1', false !== strpos( $html, 'data-app-layout="1"' ) );
$check( 'site name', false !== strpos( $html, 'data-site-name="' . esc_attr( get_bloginfo( 'name' ) ) . '"' ) );
$check( 'home url', false !== strpos( $html, 'data-home-url="' . esc_attr( home_url( '/' ) ) . '"' ) );
$check( 'logout url', 1 === preg_match( '#data-logout-url="[^"]*action=logout#', $html ) );
$check( 'profile url', false !== strpos( $html, 'data-profile-url="' . esc_url( get_edit_profile_url() ) . '"' ) );
$check( 'user name', false !== strpos( $html, 'data-user-name="' . esc_attr( wp_get_current_user()->display_name ) . '"' ) );
$check( 'site icon', false !== strpos( $html, 'data-site-icon="' . esc_url( get_site_icon_url( 64 ) ) . '"' ) );

// Inner blocks go in the extras slot.
$html = $mount( '<!-- wp:pressshot/library {"appLayout":true} --><!-- wp:paragraph --><p>Extra</p><!-- /wp:paragraph --><!-- /wp:pressshot/library -->' );
$check( 'inner blocks go in the extras slot', 1 === preg_match( '#<div class="pressshot-library__extras" hidden>.*<p[^>]*>Extra</p>.*</div>#s', $html ) );
$html = $mount();
$check( 'no inner blocks: no extras slot', false === strpos( $html, 'pressshot-library__extras' ) );
wp_set_current_user( 0 );
$html = $mount( '<!-- wp:pressshot/library {"appLayout":true} --><!-- wp:paragraph --><p>Extra</p><!-- /wp:paragraph --><!-- /wp:pressshot/library -->' );
$check( 'signed out: inner blocks are not shown', false === strpos( $html, 'Extra' ) && false !== strpos( $html, 'Sign in to see the Library.' ) );

pressshot_library_reset_rendered();
