<?php
/**
 * Integration checks for nested folders and folder delete. Run with bin/test-php.sh.
 * It creates and removes only its own users, shots, and folder terms.
 */
require __DIR__ . '/harness.php';

$GLOBALS['pressshot_test_terms'] = array();
register_shutdown_function(
	function () {
		foreach ( $GLOBALS['pressshot_test_terms'] as $term_id ) {
			if ( term_exists( $term_id, 'pressshot_folder' ) ) {
				wp_delete_term( $term_id, 'pressshot_folder' );
			}
		}
	}
);

$author   = $make_user( 'author' );
$editor   = $make_user( 'editor' );
$reader   = $make_user( 'subscriber' );
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
$folders_of = function ( $shot ) {
	return array_map( 'intval', wp_get_object_terms( $shot, 'pressshot_folder', array( 'fields' => 'ids' ) ) );
};
$delete   = function ( $id, $body ) use ( $call ) {
	return $call( 'POST', '/pressshot/v1/folders/' . $id . '/delete', $body );
};
$exists   = function ( $id ) {
	return (bool) get_term( $id, 'pressshot_folder' ) && ! is_wp_error( get_term( $id, 'pressshot_folder' ) );
};

wp_set_current_user( $editor );
$parent = $folder( 'Parent' );
$child  = $folder( 'Child', $parent );
$check( 'a folder accepts a parent', (int) get_term( $child, 'pressshot_folder' )->parent === $parent );

// Sign-in and role gates.
wp_set_current_user( 0 );
$check( 'signed-out visitors can\'t delete a folder', in_array( $delete( $parent, array( 'mode' => 'move', 'confirm' => 'DELETE' ) )->get_status(), array( 401, 403 ), true ) );
wp_set_current_user( $reader );
$check( 'subscribers can\'t delete a folder', 403 === $delete( $parent, array( 'mode' => 'move', 'confirm' => 'DELETE' ) )->get_status() );

// Confirmation.
wp_set_current_user( $editor );
$none = $delete( $parent, array( 'mode' => 'move' ) );
$wrong = $delete( $parent, array( 'mode' => 'move', 'confirm' => 'delete' ) );
$check( 'delete without confirm is 400', 400 === $none->get_status() && 'pressshot_confirm' === $none->get_data()['code'] && 400 === $wrong->get_status() );
$check( 'a refused delete changes nothing', $exists( $parent ) && $exists( $child ) );

// Move into its own subtree.
$s1 = $make_shot( $author );
$s2 = $make_shot( $author );
$file( $s1, $parent );
$file( $s2, $child );
foreach ( array( $parent, $child, 999999 ) as $bad ) {
	$r = $delete( $parent, array( 'mode' => 'move', 'target' => $bad, 'confirm' => 'DELETE' ) );
	$check( 'move to ' . $bad . ' is refused', 400 === $r->get_status() && 'pressshot_bad_target' === $r->get_data()['code'] );
}
$check( 'a refused move changes nothing', $exists( $parent ) && (int) get_term( $child, 'pressshot_folder' )->parent === $parent && array( $parent ) === $folders_of( $s1 ) && array( $child ) === $folders_of( $s2 ) );

// Move to 0.
$r = $delete( $parent, array( 'mode' => 'move', 'target' => 0, 'confirm' => 'DELETE' ) );
$check( 'move to All shots succeeds and reports it', 200 === $r->get_status() && array( $parent ) === $r->get_data()['deleted'] && 1 === $r->get_data()['moved'] && 0 === $r->get_data()['trashed'] );
$check( 'the folder is gone', ! $exists( $parent ) );
$check( 'child folders become top-level', $exists( $child ) && 0 === (int) get_term( $child, 'pressshot_folder' )->parent );
$check( 'the folder\'s own shots are unfiled; the child keeps its shots', array() === $folders_of( $s1 ) && array( $child ) === $folders_of( $s2 ) );

// Move to another folder, tree intact.
$a  = $folder( 'A' );
$b  = $folder( 'B' );
$a1 = $folder( 'A one', $a );
$a2 = $folder( 'A two', $a1 );
$sa = $make_shot( $author );
$sb = $make_shot( $author );
$sc = $make_shot( $author );
$file( $sa, $a );
$file( $sb, $a1 );
$file( $sc, $a2 );
$r = $delete( $a, array( 'mode' => 'move', 'target' => $b, 'confirm' => 'DELETE' ) );
$check( 'move into another folder succeeds', 200 === $r->get_status() && 1 === $r->get_data()['moved'] );
$check( 'the subfolder now sits under the target and keeps its own children', (int) get_term( $a1, 'pressshot_folder' )->parent === $b && (int) get_term( $a2, 'pressshot_folder' )->parent === $a1 );
$check( 'the folder\'s shot lands in the target; subfolder shots stay put', array( $b ) === $folders_of( $sa ) && array( $a1 ) === $folders_of( $sb ) && array( $a2 ) === $folders_of( $sc ) );

// Everything: an Author with a teammate's shot in the tree.
$e   = $folder( 'E' );
$e1  = $folder( 'E one', $e );
$mine = $make_shot( $author );
$theirs = $make_shot( $editor );
$file( $mine, $e );
$file( $theirs, $e1 );
wp_set_current_user( $author );
$r = $delete( $e, array( 'mode' => 'everything', 'confirm' => 'DELETE' ) );
$check( 'everything over a teammate\'s shot is 403 with a count', 403 === $r->get_status() && 'pressshot_folder_not_yours' === $r->get_data()['code'] && 1 === $r->get_data()['data']['count'] );
$check( 'a refused everything changes nothing', 'inherit' === get_post( $mine )->post_status && 'inherit' === get_post( $theirs )->post_status && $exists( $e ) && $exists( $e1 ) && array( $e ) === $folders_of( $mine ) && array( $e1 ) === $folders_of( $theirs ) );

// Everything as an Editor, then restore.
wp_set_current_user( $editor );
$r = $delete( $e, array( 'mode' => 'everything', 'confirm' => 'DELETE' ) );
$check( 'an editor deletes everything in the tree', 200 === $r->get_status() && 2 === $r->get_data()['trashed'] && array( $e1, $e ) === $r->get_data()['deleted'] );
$check( 'all its shots are trashed', 'trash' === get_post( $mine )->post_status && 'trash' === get_post( $theirs )->post_status );
$check( 'all its folder terms are gone', ! $exists( $e ) && ! $exists( $e1 ) );
$restored = $call( 'POST', '/pressshot/v1/captures/' . $mine . '/restore' );
$check( 'a restored shot comes back unfiled', 200 === $restored->get_status() && array() === $folders_of( $mine ) );

// Unknown folder, bad mode.
$check( 'an unknown folder is 404', 404 === $delete( 999999, array( 'mode' => 'move', 'confirm' => 'DELETE' ) )->get_status() );
$f = $folder( 'F' );
$check( 'an unknown mode is 400', 400 === $delete( $f, array( 'mode' => 'nope', 'confirm' => 'DELETE' ) )->get_status() && $exists( $f ) );

// Name clash at the destination: refused up front, nothing changed.
$src  = $folder( 'Src' );
$dst  = $folder( 'Dst' );
$ssc  = $folder( 'Screenshots', $src );
$dsc  = $folder( 'Screenshots', $dst );
$ss   = $make_shot( $author );
$sd   = $make_shot( $author );
$file( $ss, $src );
$file( $sd, $ssc );
$r = $delete( $src, array( 'mode' => 'move', 'target' => $dst, 'confirm' => 'DELETE' ) );
$check( 'a name clash at the target is 409', 409 === $r->get_status() && 'pressshot_folder_name_taken' === $r->get_data()['code'] );
$check( 'a name clash changes nothing', $exists( $src ) && (int) get_term( $ssc, 'pressshot_folder' )->parent === $src && (int) get_term( $dsc, 'pressshot_folder' )->parent === $dst && array( $src ) === $folders_of( $ss ) && array( $ssc ) === $folders_of( $sd ) );
$r = $delete( $src, array( 'mode' => 'move', 'target' => 0, 'confirm' => 'DELETE' ) );
$check( 'the same move to a clash-free target works', 200 === $r->get_status() && 0 === (int) get_term( $ssc, 'pressshot_folder' )->parent );
