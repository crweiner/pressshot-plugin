<?php
/**
 * Integration checks for the Library Trash routes. Run with bin/test-php.sh.
 * It creates and removes only its own users and attachment.
 */
require __DIR__ . '/harness.php';
$status = function ( $id ) {
	clean_post_cache( $id );
	return get_post( $id )->post_status;
};
$editor = $make_user( 'editor' );
$reader = $make_user( 'subscriber' );
$id     = $make_shot( 1 );
$file   = get_attached_file( $id );
$base   = '/pressshot/v1/captures/' . $id;

wp_set_current_user( 0 );
$check( 'signed-out trash is refused', in_array( $call( 'POST', $base . '/trash' )->get_status(), array( 401, 403 ), true ) );
wp_set_current_user( $reader );
$check( 'subscriber trash is refused', 403 === $call( 'POST', $base . '/trash' )->get_status() );
$no_upload = $make_user( 'author' );
get_userdata( $no_upload )->add_cap( 'upload_files', false );
$own = $make_shot( $no_upload );
wp_set_current_user( $no_upload );
$check( 'a user who can delete the shot but lacks upload_files is refused', current_user_can( 'delete_post', $own ) && ! current_user_can( 'upload_files' ) && 403 === $call( 'POST', '/pressshot/v1/captures/' . $own . '/trash' )->get_status() && 403 === $call( 'POST', '/pressshot/v1/captures/' . $own . '/restore' )->get_status() && 'inherit' === $status( $own ) );
$check( 'shot untouched after refusals', 'inherit' === $status( $id ) );
wp_set_current_user( $editor );
$check( 'unknown shot is 404', 404 === $call( 'POST', '/pressshot/v1/captures/99999999/trash' )->get_status() );
$unmarked = $make_shot( 1 );
delete_post_meta( $unmarked, '_pressshot_capture' );
$check( 'trashing an attachment that isn\'t a shot is 404', 404 === $call( 'POST', '/pressshot/v1/captures/' . $unmarked . '/trash' )->get_status() && 'inherit' === $status( $unmarked ) );
$post_id = wp_insert_post( array( 'post_title' => 'not media', 'post_status' => 'publish' ) );
$check( 'a post that is not media is 404', 404 === $call( 'POST', '/pressshot/v1/captures/' . $post_id . '/trash' )->get_status() );
$check( 'GET is not a route', 404 === $call( 'GET', $base . '/trash' )->get_status() );

$response = $call( 'POST', $base . '/trash' );
$check( 'editor trashes another author\'s shot', 200 === $response->get_status() && true === $response->get_data()['trashed'] );
$check( 'status is trash', 'trash' === $status( $id ) );
$check( 'file stays on disk', file_exists( $file ) );
$check( 'trash time recorded for the 30-day cleanup', (int) get_post_meta( $id, '_wp_trash_meta_time', true ) > 0 );
$check( 'trashing twice is fine', 200 === $call( 'POST', $base . '/trash' )->get_status() );
$normal = $call( 'GET', '/wp/v2/media', array( 'per_page' => 100 ) )->get_data();
$check( 'gone from the normal media list', ! in_array( $id, wp_list_pluck( $normal, 'id' ), true ) );
$trashed = $call( 'GET', '/wp/v2/media', array( 'status' => 'trash', 'per_page' => 100, 'context' => 'edit' ) );
$check( 'listed with status=trash', 200 === $trashed->get_status() && in_array( $id, wp_list_pluck( $trashed->get_data(), 'id' ), true ) );
wp_set_current_user( $reader );
$check( 'subscriber restore is refused', 403 === $call( 'POST', $base . '/restore' )->get_status() );
wp_set_current_user( $editor );
$response = $call( 'POST', $base . '/restore' );
$check( 'editor restores', 200 === $response->get_status() && false === $response->get_data()['trashed'] );
$check( 'status is inherit again', 'inherit' === $status( $id ) );
$check( 'restoring twice is fine', 200 === $call( 'POST', $base . '/restore' )->get_status() );

// The cleanup WordPress runs daily: a shot trashed long ago is removed for good, file included.
$call( 'POST', $base . '/trash' );
update_post_meta( $id, '_wp_trash_meta_time', time() - ( EMPTY_TRASH_DAYS + 1 ) * DAY_IN_SECONDS );
wp_scheduled_delete();
clean_post_cache( $id );
$check( 'old trash is removed by WordPress cleanup', null === get_post( $id ) && ! file_exists( $file ) );

wp_set_current_user( 1 );
wp_delete_post( $post_id, true );
