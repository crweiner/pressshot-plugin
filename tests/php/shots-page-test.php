<?php
/**
 * Integration checks for the /shots page and Library block, matched-rule gating on both pages,
 * and the VideoPress guid fallback. Run with bin/test-php.sh. It creates and removes only its own
 * users and attachments.
 */
require __DIR__ . '/harness.php';

global $wp;
$handle = 'pressshot-library-view-script';
$rule   = '^shots/?$';
$author = $make_user( 'author' );
$reader = $make_user( 'subscriber' );
$editor = $make_user( 'editor' );
$mount  = function () use ( $handle ) {
	pressshot_library_reset_rendered();
	wp_dequeue_script( $handle );
	return do_blocks( '<!-- wp:pressshot/library /-->' );
};

// Rewrite rule, path filter, template.
pressshot_share_activate();
$rules = get_option( 'rewrite_rules' );
$check( 'activation flushes the /shots rule', isset( $rules[ $rule ] ) && 'index.php?pressshot_shots=1' === $rules[ $rule ] );
$check( 'the shots query var is public', in_array( 'pressshot_shots', apply_filters( 'query_vars', array() ), true ) );
$check( 'the default path is shots', 'shots' === pressshot_shots_path() );
add_filter( 'pressshot_shots_path', $path_filter = function () {
	return '/library/';
} );
$check( 'the path is filterable and trimmed', 'library' === pressshot_shots_path() );
remove_filter( 'pressshot_shots_path', $path_filter );
// The plugin's own registered template, not the resolved one (the active theme may override it).
$template = WP_Block_Templates_Registry::get_instance()->get_registered( 'pressshot//shots' );
$template_content = $template ? (string) $template->content : '';
$check( 'the shots block and template are registered', WP_Block_Type_Registry::get_instance()->is_registered( 'pressshot/library' ) && $template && false !== strpos( $template_content, '<!-- wp:pressshot/library {"align":"full"} /-->' ) );
$check( 'the shots template holds the Library in a full-width group', 1 === preg_match( '#<!-- wp:group \\{[^}]*"align":"full"#', $template_content ) );
$check( 'the Library block supports full alignment', in_array( 'full', (array) ( WP_Block_Type_Registry::get_instance()->get_registered( 'pressshot/library' )->supports['align'] ?? array() ), true ) );

// Matched-rule gating for /shots.
$wp->matched_rule = 'something-else/?$';
set_query_var( 'pressshot_shots', '1' );
$check( '?pressshot_shots=1 on another URL is not the Library', ! pressshot_shots_is_request() );
$wp->matched_rule = $rule;
$check( 'the /shots rule is the Library', pressshot_shots_is_request() );
set_query_var( 'pressshot_shots', '' );
$wp->matched_rule = '';

// Signed out: auth_redirect to login, returning to /shots/.
$_SERVER['HTTP_HOST']   = 'example.test';
$_SERVER['REQUEST_URI'] = '/shots/';
$wp->matched_rule       = $rule;
set_query_var( 'pressshot_shots', '1' );
wp_set_current_user( 0 );
$location = null;
add_filter(
	'wp_redirect',
	$redirect_filter = function ( $to ) use ( &$location ) {
		$location = $to;
		throw new RuntimeException( 'redirected' );
	},
	0 // Before WP-CLI's own wp_redirect handler, which would print a warning.
);
try {
	pressshot_shots_gate();
} catch ( RuntimeException $e ) {
	// Expected: auth_redirect() redirects, then exits.
}
remove_filter( 'wp_redirect', $redirect_filter );
parse_str( (string) wp_parse_url( (string) $location, PHP_URL_QUERY ), $query );
$check( 'signed out is sent to wp-login.php', is_string( $location ) && false !== strpos( $location, 'wp-login.php?redirect_to=' ) );
$check( 'the login redirect returns to /shots/', isset( $query['redirect_to'] ) && '/shots/' === substr( $query['redirect_to'], -7 ) );
$wp->matched_rule = 'other/?$';
$location         = null;
pressshot_shots_gate();
$check( 'the gate does nothing on other URLs', null === $location );
set_query_var( 'pressshot_shots', '' );
$wp->matched_rule = '';

// Block output by role.
wp_set_current_user( $reader );
$html = $mount();
$check( 'a subscriber gets the no-access text', false !== strpos( $html, "You don't have access to the Library on this site." ) || false !== strpos( $html, 'You don&#039;t have access to the Library on this site.' ) );
$check( 'a subscriber gets no mount div', false === strpos( $html, 'id="pressshot-library"' ) );
$check( 'a subscriber gets no app script', ! wp_script_is( $handle, 'enqueued' ) );
wp_set_current_user( $author );
$html = $mount();
$check( 'an author gets the mount div with the shots path', 1 === preg_match( '#<div id="pressshot-library" data-shots-path="/shots"[^>]*></div>#', $html ) );
$check( 'the mount names the current user', 1 === preg_match( '#data-user-id="' . $author . '"#', $html ) );
$check( 'the mount carries a REST nonce for media URLs', 1 === preg_match( '#data-rest-nonce="[0-9a-f]{10}"#', $html ) );
$authors = preg_match( '#data-authors="([^"]*)"#', $html, $m ) ? json_decode( html_entity_decode( $m[1], ENT_QUOTES ), true ) : null;
$ids     = is_array( $authors ) ? array_map( 'intval', wp_list_pluck( $authors, 'id' ) ) : array();
$check( 'the mount lists people who can upload, the author included', in_array( $author, $ids, true ) && isset( $authors[0]['name'] ) );
$check( 'the mount leaves out people who cannot upload', ! in_array( $reader, $ids, true ) );
$check( 'the mount carries the current user\'s avatar URL', 1 === preg_match( '#data-avatar-url="([^"]+)"#', $html, $m ) && html_entity_decode( $m[1] ) === get_avatar_url( $author, array( 'size' => 48 ) ) );
$check( 'an author can\'t share folders', 1 === preg_match( '#data-can-share-folders="0"#', $html ) );
$check( 'an author gets the app script', wp_script_is( $handle, 'enqueued' ) );
$deps = wp_scripts()->registered[ $handle ]->deps ?? array();
$check( 'the app script is registered with its asset dependencies', ! array_diff( array( 'wp-element', 'wp-i18n', 'wp-components', 'wp-theme' ), $deps ) );
$check( 'every dependency in build/library.asset.php is registered on this site', array() === pressshot_library_missing_dependencies() && count( pressshot_library_dependencies() ) > 3 );
wp_dequeue_script( $handle );
add_filter( 'pressshot_library_dependencies', $fake_dep = function ( $list ) {
	$list[] = 'pressshot-no-such-handle';
	return $list;
} );
$html = $mount();
$check( 'an unregistered dependency renders the newer-WordPress message', false !== strpos( $html, 'The Library needs a newer version of WordPress.' ) );
$check( 'an unregistered dependency renders no mount div and no script', false === strpos( $html, 'id="pressshot-library"' ) && ! wp_script_is( $handle, 'enqueued' ) );
remove_filter( 'pressshot_library_dependencies', $fake_dep );
add_filter( 'pressshot_library_asset_file', $no_build = function () {
	return __DIR__ . '/no-such-build/library.asset.php';
} );
$check( 'a missing build/library.asset.php counts as a missing dependency', array( 'build/library.asset.php' ) === pressshot_library_missing_dependencies() );
$html = $mount();
$check( 'a missing build renders the reinstall message, no mount div, and no script', false !== strpos( $html, 'Reinstall the plugin.' ) && false === strpos( $html, 'id="pressshot-library"' ) && ! wp_script_is( $handle, 'enqueued' ) );
remove_filter( 'pressshot_library_asset_file', $no_build );
wp_set_current_user( $editor );
$check( 'an editor can share folders', 1 === preg_match( '#data-can-share-folders="1"#', $mount() ) );
wp_set_current_user( $author );
wp_dequeue_script( $handle );

// Matched-rule gating for share pages.
$token = wp_generate_password( 32, false );
set_query_var( 'pressshot_share', $token );
$wp->matched_rule = 'other/?$';
$check( '?pressshot_share= on another URL is not a share request', ! pressshot_share_is_request() );
$check( 'it keeps canonical redirects', 'http://example.test/x/' === apply_filters( 'redirect_canonical', 'http://example.test/x/' ) );
$check( 'it does not blank the main query', null === apply_filters( 'posts_pre_query', null, new class() extends WP_Query {
	public function is_main_query() {
		return true;
	}
} ) );
$wp->matched_rule = '^s/([A-Za-z0-9]{32})/?$';
$check( 'the /s/{token} rule is a share request', pressshot_share_is_request() );
$check( 'share requests skip canonical redirects', false === apply_filters( 'redirect_canonical', 'http://example.test/x/' ) );
set_query_var( 'pressshot_share', '' );
$wp->matched_rule = '';

// VideoPress guid fallback.
$vp = $make_shot( $author, 'video/mp4' );
$check( 'no guid anywhere is null', null === pressshot_videopress_guid( $vp ) );
update_post_meta( $vp, 'videopress_guid', 'MetaGuid1' );
$check( 'the meta guid wins', 'MetaGuid1' === pressshot_videopress_guid( $vp ) );
delete_post_meta( $vp, 'videopress_guid' );
wp_update_attachment_metadata( $vp, array( 'videopress' => array( 'guid' => 'AttGuid22' ) ) );
$check( 'the attachment metadata guid is the fallback', 'AttGuid22' === pressshot_videopress_guid( $vp ) );
wp_set_current_user( $author );
$check( 'the Library DTO uses the fallback', 'AttGuid22' === pressshot_shot_dto( $vp )['videopressGuid'] );
$public = pressshot_public_shot( $vp, wp_generate_password( 32, false ) );
$check( 'the public shot uses the fallback', 'AttGuid22' === $public['videopressGuid'] && null === $public['mediaUrl'] );
