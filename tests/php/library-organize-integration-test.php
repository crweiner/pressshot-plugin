<?php
/**
 * Integration checks for Library folders and tags. Run with bin/test-php.sh.
 * It creates and removes only its own users, terms, and attachment.
 */
require __DIR__ . '/harness.php';
$author = $make_user( 'author' );
$editor = $make_user( 'editor' );
$reader = $make_user( 'subscriber' );
$id     = $make_shot( $author );
$media  = '/wp/v2/media/' . $id;

wp_set_current_user( 0 );
$check( 'signed-out visitors can\'t create a folder', in_array( $call( 'POST', '/wp/v2/pressshot-folders', array( 'name' => 'Nope' ) )->get_status(), array( 401, 403 ), true ) );
wp_set_current_user( $reader );
$check( 'subscribers can\'t create a folder', 403 === $call( 'POST', '/wp/v2/pressshot-folders', array( 'name' => 'Nope' ) )->get_status() );

wp_set_current_user( $editor );
$a = $call( 'POST', '/wp/v2/pressshot-folders', array( 'name' => 'Client & feedback ' . wp_generate_password( 4, false ) ) );
$b = $call( 'POST', '/wp/v2/pressshot-folders', array( 'name' => 'Launch ' . wp_generate_password( 4, false ) ) );
$t = $call( 'POST', '/wp/v2/pressshot-tags', array( 'name' => 'Design ' . wp_generate_password( 4, false ) ) );
$u = $call( 'POST', '/wp/v2/pressshot-tags', array( 'name' => 'Bug ' . wp_generate_password( 4, false ) ) );
$check( 'an editor creates folders and tags', 201 === $a->get_status() && 201 === $b->get_status() && 201 === $t->get_status() && 201 === $u->get_status() );
$fa = $a->get_data()['id']; $fb = $b->get_data()['id']; $ta = $t->get_data()['id']; $tb = $u->get_data()['id'];
$again = $call( 'POST', '/wp/v2/pressshot-folders', array( 'name' => $b->get_data()['name'] ) );
$check( 'a second folder with the same name is refused and names the first', 400 === $again->get_status() && $fb === ( $again->get_data()['data']['term_id'] ?? 0 ) );
$listed = wp_list_pluck( $call( 'GET', '/wp/v2/pressshot-folders', array( 'per_page' => 100, 'hide_empty' => false ) )->get_data(), 'id' );
$check( 'folders list includes empty ones', in_array( $fa, $listed, true ) && in_array( $fb, $listed, true ) );

$filed = $call( 'POST', $media, array( 'pressshot-folders' => array( $fa ), 'pressshot-tags' => array( $ta, $tb ) ) );
$check( 'an editor files another author\'s shot', 200 === $filed->get_status() && array( $fa ) === $filed->get_data()['pressshot-folders'] && 2 === count( $filed->get_data()['pressshot-tags'] ) );
$read = $call( 'GET', $media, array( 'context' => 'edit' ) )->get_data();
$check( 'the shot reports its folder and tags', array( $fa ) === $read['pressshot-folders'] && ! array_diff( array( $ta, $tb ), $read['pressshot-tags'] ) );
$two = $call( 'POST', $media, array( 'pressshot-folders' => array( $fa, $fb ) ) );
$check( 'two folders at once collapse to one', 1 === count( wp_get_object_terms( $id, 'pressshot_folder' ) ) );
$moved = $call( 'POST', $media, array( 'pressshot-folders' => array( $fb ) ) );
$check( 'moving to another folder replaces the first', array( $fb ) === $moved->get_data()['pressshot-folders'] );
$check( 'tags are untouched by a folder move', 2 === count( wp_get_object_terms( $id, 'pressshot_tag' ) ) );
$in = wp_list_pluck( $call( 'GET', '/wp/v2/media', array( 'pressshot-folders' => array( $fb ), 'per_page' => 100 ) )->get_data(), 'id' );
$check( 'media can be listed by folder', in_array( $id, $in, true ) );
$renamed = $call( 'POST', '/wp/v2/pressshot-folders/' . $fb, array( 'name' => 'Renamed ' . wp_generate_password( 4, false ) ) );
$check( 'a folder can be renamed', 200 === $renamed->get_status() && 0 === strpos( $renamed->get_data()['name'], 'Renamed' ) );

// shotCount: shots in a folder or tag that aren't in Trash. Core's count would include the
// trashed shot and the plain attachment, so the field must not be it.
$c1     = $make_shot( $author );
$c2     = $make_shot( $author, 'video/mp4' );
$c3     = $make_shot( $author );
$plain  = $make_shot( $author );
delete_post_meta( $plain, '_pressshot_capture' );
foreach ( array( $c1, $c2, $c3, $plain ) as $shot ) {
	wp_set_object_terms( $shot, array( $fa ), 'pressshot_folder' );
	wp_set_object_terms( $shot, array( $ta ), 'pressshot_tag' );
}
wp_trash_post( $c3 );
$count_of = function ( $base, $term ) use ( $call ) {
	$response = $call( 'GET', '/wp/v2/' . $base, array( 'per_page' => 100, 'hide_empty' => false, '_fields' => 'id,shotCount' ) );
	if ( 200 !== $response->get_status() ) {
		return 'not listed';
	}
	foreach ( $response->get_data() as $row ) {
		if ( (int) $row['id'] === (int) $term ) {
			return $row['shotCount'] ?? 'missing';
		}
	}
	return 'not listed';
};
$check( 'a folder\'s shotCount counts its shots, not trashed ones or other media', 2 === $count_of( 'pressshot-folders', $fa ) );
// The first shot still carries this tag, so it's that one plus $c1 and $c2.
$check( 'a tag\'s shotCount counts its shots, not trashed ones or other media', 3 === $count_of( 'pressshot-tags', $ta ) );
wp_untrash_post( $c3 );
$check( 'shotCount goes back up when a shot comes out of Trash', 3 === $count_of( 'pressshot-folders', $fa ) );
wp_set_object_terms( $c1, array(), 'pressshot_folder' );
$check( 'shotCount follows a shot moving out of a folder', 2 === $count_of( 'pressshot-folders', $fa ) );
$single = $call( 'GET', '/wp/v2/pressshot-folders/' . $fa )->get_data();
$check( 'a single folder reports shotCount too', 2 === ( $single['shotCount'] ?? null ) );
foreach ( array( $c1, $c2, $c3, $plain ) as $shot ) {
	wp_set_object_terms( $shot, array(), 'pressshot_folder' );
	wp_set_object_terms( $shot, array(), 'pressshot_tag' );
}
$check( 'a folder with no shots left has shotCount 0', 0 === $count_of( 'pressshot-folders', $fa ) );

wp_set_current_user( $reader );
$check( 'people without Library access get no shotCount', in_array( $count_of( 'pressshot-folders', $fa ), array( 'missing', 'not listed' ), true ) );
$check( 'subscribers can\'t file a shot', 403 === $call( 'POST', $media, array( 'pressshot-folders' => array( $fa ) ) )->get_status() );
$check( 'subscribers can\'t delete a folder', 403 === $call( 'DELETE', '/wp/v2/pressshot-folders/' . $fb, array( 'force' => true ) )->get_status() );

wp_set_current_user( $author );
$check( 'an author (who can upload) can create a tag', 201 === ( $extra = $call( 'POST', '/wp/v2/pressshot-tags', array( 'name' => 'Mine ' . wp_generate_password( 4, false ) ) ) )->get_status() );

wp_set_current_user( $editor );
$gone = $call( 'DELETE', '/wp/v2/pressshot-folders/' . $fb, array( 'force' => true ) );
$check( 'deleting a folder succeeds', 200 === $gone->get_status() );
$check( 'the shot is still there, now unfiled', 'attachment' === get_post_type( $id ) && array() === wp_get_object_terms( $id, 'pressshot_folder' ) );
$call( 'POST', $media, array( 'pressshot-tags' => array() ) );
$check( 'tags can be cleared', array() === wp_get_object_terms( $id, 'pressshot_tag' ) );
$check( 'folders and tags stay out of public queries', ! get_taxonomy( 'pressshot_folder' )->public && ! get_taxonomy( 'pressshot_tag' )->publicly_queryable );

wp_set_current_user( 1 );
foreach ( array( $fa => 'pressshot_folder', $ta => 'pressshot_tag', $tb => 'pressshot_tag', $extra->get_data()['id'] => 'pressshot_tag' ) as $term => $taxonomy ) {
	wp_delete_term( $term, $taxonomy );
}
