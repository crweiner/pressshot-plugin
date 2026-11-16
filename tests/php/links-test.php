<?php
/**
 * Integration checks for share links. Run with bin/test-php.sh.
 * It creates and removes only its own users, shots, folder terms, and link records.
 */
require __DIR__ . '/harness.php';

$GLOBALS['pressshot_test_terms'] = array();
register_shutdown_function(
	function () {
		foreach ( $GLOBALS['pressshot_test_made']['attachments'] as $id ) {
			pressshot_link_revoke( 'shot', $id );
		}
		foreach ( $GLOBALS['pressshot_test_terms'] as $term_id ) {
			pressshot_link_revoke( 'folder', $term_id );
			if ( term_exists( $term_id, 'pressshot_folder' ) ) {
				wp_delete_term( $term_id, 'pressshot_folder' );
			}
		}
	}
);

$author_a = $make_user( 'author' );
$author_b = $make_user( 'author' );
$editor   = $make_user( 'editor' );
$tag      = wp_generate_password( 5, false );
$folder   = function ( $name, $parent = 0 ) use ( $call, $tag ) {
	$response = $call( 'POST', '/wp/v2/pressshot-folders', array( 'name' => $name . ' ' . $tag, 'parent' => $parent ) );
	if ( 201 !== $response->get_status() ) {
		throw new RuntimeException( 'Could not create folder: ' . wp_json_encode( $response->get_data() ) );
	}
	$GLOBALS['pressshot_test_terms'][] = $response->get_data()['id'];
	return $response->get_data()['id'];
};
$file     = function ( $shot, $folder_id ) {
	wp_set_object_terms( $shot, array( $folder_id ), 'pressshot_folder' );
};
$emit     = function ( $response ) {
	ob_start();
	$response->emit( 'GET' );
	return ob_get_clean();
};
$png      = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=' );
$body     = function ( $response ) {
	return wp_json_encode( $response->get_data() );
};

$shot_a = $make_shot( $author_a );
$shot_b = $make_shot( $author_b );

// Create, idempotence, permissions.
wp_set_current_user( $author_a );
$first  = $call( 'POST', "/pressshot/v1/shots/$shot_a/link" );
$token  = $first->get_data()['token'] ?? '';
$check( 'author creates a link on their own shot', 200 === $first->get_status() && 1 === preg_match( '/^[A-Za-z0-9]{32}$/', $token ) );
$check( 'link url ends in /s/{token}', substr( $first->get_data()['url'], -( 3 + 32 ) ) === '/s/' . $token );
$again = $call( 'POST', "/pressshot/v1/shots/$shot_a/link" );
$check( 'creating again returns the same token', $again->get_data()['token'] === $token );
$check( 'author can\'t link another author\'s shot', 403 === $call( 'POST', "/pressshot/v1/shots/$shot_b/link" )->get_status() );
wp_set_current_user( $editor );
$f_top = $folder( 'Top' );
wp_set_current_user( $author_a );
$check( 'author can\'t link a folder (403)', 403 === $call( 'POST', "/pressshot/v1/folders/$f_top/link" )->get_status() );
wp_set_current_user( 0 );
$check( 'signed-out visitors can\'t create or revoke links', 401 === $call( 'POST', "/pressshot/v1/shots/$shot_a/link" )->get_status() && 401 === $call( 'DELETE', "/pressshot/v1/shots/$shot_a/link" )->get_status() );

// DTO link and register shareUrl.
wp_set_current_user( $author_a );
$dto = $call( 'GET', "/pressshot/v1/shots/$shot_a" )->get_data();
$check( 'DTO link carries the token for Library users', ( $dto['link']['token'] ?? '' ) === $token );
$no_link = $call( 'GET', "/pressshot/v1/shots/$shot_b" );
$check( 'DTO link is null when the shot has none', null === $no_link->get_data()['link'] );
$shot_c = $make_shot( $author_a );
$reg    = $call( 'POST', "/pressshot/v1/captures/$shot_c/register", array( 'source' => 'pressshot' ) )->get_data();
$check( 'register returns a shareUrl ending in /s/{token}', 1 === preg_match( '#/s/[A-Za-z0-9]{32}$#', (string) $reg['shareUrl'] ) );
$reg2 = $call( 'POST', "/pressshot/v1/captures/$shot_c/register", array( 'source' => 'pressshot' ) )->get_data();
$check( 'registering again keeps the same link', $reg2['shareUrl'] === $reg['shareUrl'] );

// Anonymous reads.
wp_set_current_user( 0 );
$view = $call( 'GET', "/pressshot/v1/links/$token" );
$data = $view->get_data();
$check( 'signed-out visitor reads the shot', 200 === $view->get_status() && 'shot' === $data['kind'] && $shot_a === $data['shots'][0]['id'] && array() === $data['folders'] );
$check( 'PublicShot media URLs point at the token route', false !== strpos( $data['shots'][0]['mediaUrl'], "/links/$token/shots/$shot_a/media" ) && false !== strpos( $data['shots'][0]['downloadUrl'], 'download=1' ) );
$check( 'PublicShot exposes no author or token field', ! isset( $data['shots'][0]['author'] ) && ! isset( $data['shots'][0]['token'] ) );
$media = $call( 'GET', "/pressshot/v1/links/$token/shots/$shot_a/media" );
$check( 'signed-out visitor gets the media bytes', 200 === $media->get_status() && $emit( $media ) === $png );
$check( 'media keeps the private headers', 'private, no-store, no-transform' === $media->get_headers()['Cache-Control'] && 'nosniff' === $media->get_headers()['X-Content-Type-Options'] );
$dl = $call( 'GET', "/pressshot/v1/links/$token/shots/$shot_a/media", array( 'download' => '1' ) );
$check( 'download=1 adds Content-Disposition', isset( $dl->get_headers()['Content-Disposition'] ) );
$check( 'size=medium still serves', 200 === $call( 'GET', "/pressshot/v1/links/$token/shots/$shot_a/media", array( 'size' => 'medium' ) )->get_status() );
$other = $call( 'GET', "/pressshot/v1/links/$token/shots/$shot_b/media" );
$check( 'a shot link doesn\'t cover another shot', 404 === $other->get_status() );

// One 404 body for every failure.
$random   = wp_generate_password( 32, false );
$unknown  = $call( 'GET', "/pressshot/v1/links/$random" );
$malform  = $call( 'GET', '/pressshot/v1/links/short' );
$check( 'unknown token is 404 pressshot_link_unavailable', 404 === $unknown->get_status() && 'pressshot_link_unavailable' === $unknown->get_data()['code'] );
$check( 'malformed token has the same body', $body( $malform ) === $body( $unknown ) && 404 === $malform->get_status() );
$check( 'media 404 has the same body', $body( $other ) === $body( $unknown ) );

// Expiry.
wp_set_current_user( $author_a );
$check( 'past expiresAt is 400', 400 === $call( 'POST', "/pressshot/v1/shots/$shot_a/link", array( 'expiresAt' => gmdate( 'c', time() - 3600 ) ) )->get_status() );
$check( 'garbage expiresAt is 400', 400 === $call( 'POST', "/pressshot/v1/shots/$shot_a/link", array( 'expiresAt' => 'not a date at all' ) )->get_status() );
$future = $call( 'POST', "/pressshot/v1/shots/$shot_a/link", array( 'expiresAt' => gmdate( 'c', time() + 3600 ) ) );
$check( 'future expiresAt is stored and keeps the token', 200 === $future->get_status() && $future->get_data()['token'] === $token && null !== $future->get_data()['expiresAt'] );
$cleared = $call( 'POST', "/pressshot/v1/shots/$shot_a/link", array( 'expiresAt' => null ) );
$check( 'null expiresAt means never', null === $cleared->get_data()['expiresAt'] );
update_post_meta( $cleared->get_data()['id'], '_pressshot_link_expires', time() - 1 );
wp_set_current_user( 0 );
$expired = $call( 'GET', "/pressshot/v1/links/$token" );
$check( 'an expired link is the same 404', $body( $expired ) === $body( $unknown ) && 404 === $expired->get_status() );
$check( 'an expired link\'s media is the same 404', $body( $call( 'GET', "/pressshot/v1/links/$token/shots/$shot_a/media" ) ) === $body( $unknown ) );

// Revoke.
wp_set_current_user( $author_a );
$check( 'author can\'t revoke another author\'s link', 403 === $call( 'DELETE', "/pressshot/v1/shots/$shot_b/link" )->get_status() );
$call( 'POST', "/pressshot/v1/shots/$shot_a/link" );
$check( 'revoke is 200', 200 === $call( 'DELETE', "/pressshot/v1/shots/$shot_a/link" )->get_status() );
wp_set_current_user( 0 );
$check( 'a revoked link is the same 404', $body( $call( 'GET', "/pressshot/v1/links/$token" ) ) === $body( $unknown ) );
wp_set_current_user( $author_a );
$new = $call( 'POST', "/pressshot/v1/shots/$shot_a/link" )->get_data()['token'];
$check( 'a new link has a different token', $new !== $token );
$check( 'the old token stays dead', null === pressshot_link_resolve( $token ) && null !== pressshot_link_resolve( $new ) );

// Trash then restore.
wp_trash_post( $shot_a );
wp_untrash_post( $shot_a );
wp_set_current_user( 0 );
$check( 'trash then restore leaves the old token dead', 404 === $call( 'GET', "/pressshot/v1/links/$new" )->get_status() && null === pressshot_link_for( 'shot', $shot_a ) );
// A trashed shot with a link record (made directly) doesn't resolve.
$direct = pressshot_link_create( 'shot', $shot_b );
wp_set_current_user( 0 );
$resolved_before = null !== pressshot_link_resolve( $direct['token'] );
global $wpdb;
$wpdb->update( $wpdb->posts, array( 'post_status' => 'trash' ), array( 'ID' => $shot_b ) );
clean_post_cache( $shot_b );
$check( 'a trashed shot target doesn\'t resolve', $resolved_before && null === pressshot_link_resolve( $direct['token'] ) );
$wpdb->update( $wpdb->posts, array( 'post_status' => 'inherit' ), array( 'ID' => $shot_b ) );
clean_post_cache( $shot_b );
pressshot_link_revoke( 'shot', $shot_b );

// Folder links.
wp_set_current_user( $editor );
$f_sub   = $folder( 'Sub', $f_top );
$f_other = $folder( 'Other' );
$in_top  = $make_shot( $author_a );
$in_sub  = $make_shot( $author_b );
$file( $in_top, $f_top );
$file( $in_sub, $f_sub );
$flink   = $call( 'POST', "/pressshot/v1/folders/$f_top/link" );
$ftoken  = $flink->get_data()['token'] ?? '';
$check( 'editor creates a folder link', 200 === $flink->get_status() && 'folder' === $flink->get_data()['kind'] );
$read = $call( 'GET', "/pressshot/v1/folders/$f_top/link" );
$check( 'editor reads the folder\'s link', 200 === $read->get_status() && $ftoken === ( $read->get_data()['link']['token'] ?? '' ) );
$check( 'reading a folder with no link gives null', array( 'link' => null ) === $call( 'GET', "/pressshot/v1/folders/$f_sub/link" )->get_data() );
wp_set_current_user( $author_a );
$check( 'author can\'t read a folder link (403)', 403 === $call( 'GET', "/pressshot/v1/folders/$f_top/link" )->get_status() );
wp_set_current_user( $editor );
wp_set_current_user( 0 );
$fview = $call( 'GET', "/pressshot/v1/links/$ftoken" )->get_data();
$ids   = wp_list_pluck( $fview['shots'], 'id' );
sort( $ids );
$expected = array( $in_top, $in_sub );
sort( $expected );
$check( 'folder link lists shots in the folder and subfolders', 'folder' === $fview['kind'] && $ids === $expected );
$names = wp_list_pluck( $fview['folders'], 'name' );
$check( 'folder link lists the folder and its descendants', 2 === count( $fview['folders'] ) && (int) $fview['folders'][1]['parent'] === $f_top && in_array( 'Sub ' . $tag, $names, true ) );
$check( 'folder link serves a subfolder shot', 200 === $call( 'GET', "/pressshot/v1/links/$ftoken/shots/$in_sub/media" )->get_status() );
$check( 'folder link doesn\'t serve an unfiled shot', 404 === $call( 'GET', "/pressshot/v1/links/$ftoken/shots/$shot_b/media" )->get_status() );
wp_set_current_user( $editor );
$file( $in_sub, $f_other );
wp_set_current_user( 0 );
$moved = $call( 'GET', "/pressshot/v1/links/$ftoken/shots/$in_sub/media" );
$check( 'after moving to an unrelated folder the folder token is 404', 404 === $moved->get_status() && $body( $moved ) === $body( $unknown ) );
$check( 'the moved shot leaves the folder list', array( $in_top ) === wp_list_pluck( $call( 'GET', "/pressshot/v1/links/$ftoken" )->get_data()['shots'], 'id' ) );
wp_trash_post( $in_top );
$check( 'a trashed shot is no longer covered', 404 === $call( 'GET', "/pressshot/v1/links/$ftoken/shots/$in_top/media" )->get_status() && array() === $call( 'GET', "/pressshot/v1/links/$ftoken" )->get_data()['shots'] );
wp_untrash_post( $in_top );

// VideoPress-only shots expose no local media.
wp_set_current_user( $editor );
$video = $make_shot( $author_a, 'video/mp4' );
update_post_meta( $video, 'videopress_guid', 'AbCdEf12' );
$file( $video, $f_top );
wp_set_current_user( 0 );
$vp = null;
foreach ( $call( 'GET', "/pressshot/v1/links/$ftoken" )->get_data()['shots'] as $item ) {
	if ( $item['id'] === $video ) {
		$vp = $item;
	}
}
$check( 'VideoPress shot has a guid and no media urls', $vp && 'AbCdEf12' === $vp['videopressGuid'] && null === $vp['mediaUrl'] && null === $vp['downloadUrl'] );
$check( 'VideoPress media route is the same 404', $body( $call( 'GET', "/pressshot/v1/links/$ftoken/shots/$video/media" ) ) === $body( $unknown ) );

// Deleting the folder revokes its link; a missing folder doesn't resolve.
wp_set_current_user( $editor );
$f_gone = $folder( 'Gone' );
$gl     = $call( 'POST', "/pressshot/v1/folders/$f_gone/link" )->get_data();
$call( 'POST', "/pressshot/v1/folders/$f_gone/delete", array( 'mode' => 'move', 'confirm' => 'DELETE' ) );
$check( 'deleting a folder revokes its link', null === pressshot_link_resolve( $gl['token'] ) && null === pressshot_link_for( 'folder', $f_gone ) );
$check( 'author can\'t revoke a folder link', ( function () use ( $call, $author_a, $f_top ) {
	wp_set_current_user( $author_a );
	return 403 === $call( 'DELETE', "/pressshot/v1/folders/$f_top/link" )->get_status();
} )() );
wp_set_current_user( $editor );
$check( 'editor revokes a folder link', 200 === $call( 'DELETE', "/pressshot/v1/folders/$f_top/link" )->get_status() && null === pressshot_link_resolve( $ftoken ) );

// A folder link also shares the shots under it; the DTO says so (name only, no token).
wp_set_current_user( $editor );
$f_parent = $folder( 'Q&A parent' );
$f_child  = $folder( 'Child', $f_parent );
$nested   = $make_shot( $author_a );
$loose    = $make_shot( $author_a );
$file( $nested, $f_child );
$dto_of = function ( $id ) use ( $call ) {
	return $call( 'GET', "/pressshot/v1/shots/$id" )->get_data();
};
$d = $dto_of( $nested );
$check( 'dto: no folder link means folderLinked is false', false === $d['folderLinked'] && null === $d['folderLinkName'] );
$plink = $call( 'POST', "/pressshot/v1/folders/$f_parent/link" )->get_data();
wp_set_current_user( $author_a );
$d = $dto_of( $nested );
$check( 'dto: an ancestor folder link sets folderLinked and names that folder', true === $d['folderLinked'] && get_term( $f_parent )->name === $d['folderLinkName'] );
$check( 'dto: folder link state carries no token', false === strpos( wp_json_encode( array( $d['folderLinked'], $d['folderLinkName'] ) ), $plink['token'] ) );
$check( 'dto: an unfiled shot is not folder-linked', false === $dto_of( $loose )['folderLinked'] );
$listed = array();
foreach ( $call( 'GET', '/pressshot/v1/shots', array( 'folder' => $f_child ) )->get_data()['items'] as $item ) {
	$listed[ $item['id'] ] = $item;
}
$check( 'list: folder link state matches the single DTO', isset( $listed[ $nested ] ) && true === $listed[ $nested ]['folderLinked'] );
update_post_meta( $plink['id'], '_pressshot_link_expires', time() - 60 );
$check( 'dto: an expired folder link doesn\'t count', false === $dto_of( $nested )['folderLinked'] );
wp_set_current_user( $editor );
$call( 'DELETE', "/pressshot/v1/folders/$f_parent/link" );
wp_set_current_user( $author_a );
$check( 'dto: a revoked folder link doesn\'t count', false === $dto_of( $nested )['folderLinked'] );

// A page of shots costs about the same number of queries however many shots it holds.
$f_count = $folder( 'Counted' );
$fill    = function ( $n ) use ( $make_shot, $author_a, $file, $f_count ) {
	for ( $i = 0; $i < $n; $i++ ) {
		$id = $make_shot( $author_a );
		$file( $id, $f_count );
		pressshot_link_create( 'shot', $id );
	}
};
$queries = function ( $run ) {
	global $wpdb;
	wp_cache_flush();
	$before = $wpdb->num_queries;
	$run();
	return $wpdb->num_queries - $before;
};
$list_folder = function () use ( $call, $f_count ) {
	$call( 'GET', '/pressshot/v1/shots', array( 'folder' => $f_count, 'perPage' => 100 ) );
};
wp_set_current_user( $editor );
$count_link = $call( 'POST', "/pressshot/v1/folders/$f_count/link" )->get_data();
$view_folder = function () use ( $count_link ) {
	pressshot_link_view( pressshot_link_resolve( $count_link['token'] ) );
};
wp_set_current_user( $author_a );
$fill( 3 );
$small_list = $queries( $list_folder );
$small_view = $queries( $view_folder );
$fill( 9 );
$big_list = $queries( $list_folder );
$big_view = $queries( $view_folder );
$check( "list: 12 shots take no more queries than 3 ($small_list then $big_list)", $big_list <= $small_list + 2 );
$check( "folder link view: 12 shots take no more queries than 3 ($small_view then $big_view)", $big_view <= $small_view + 2 );

// A link record that can't be saved is a WP_Error and a 500, not an exception.
$unlinked = $make_shot( $author_a );
add_filter( 'wp_insert_post_empty_content', '__return_true' );
$failed = pressshot_link_create( 'shot', $unlinked );
$rest   = $call( 'POST', "/pressshot/v1/shots/$unlinked/link" );
remove_filter( 'wp_insert_post_empty_content', '__return_true' );
$check( 'link_create returns a WP_Error when the record can\'t be saved', is_wp_error( $failed ) && 500 === $failed->get_error_data()['status'] );
$check( 'the link route answers 500 with a message', 500 === $rest->get_status() && '' !== (string) $rest->get_data()['message'] );
