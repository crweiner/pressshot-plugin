<?php
/**
 * Checks that retired routes and fields are gone and the shared streaming function is kept.
 * Run with bin/test-php.sh.
 */
require __DIR__ . '/harness.php';

$routes = rest_get_server()->get_routes();
foreach ( array(
	'/pressshot/v1/library/captures',
	'/pressshot/v1/images/(?P<id>[1-9][0-9]*)',
	'/pressshot/v1/captures/(?P<id>[1-9][0-9]*)/sharing',
	'/pressshot/v1/captures/(?P<id>[1-9][0-9]*)/outside-link',
) as $route ) {
	$check( 'route is gone: ' . $route, ! isset( $routes[ $route ] ) );
}
$check( 'pressshot_folder is not a post type', ! post_type_exists( 'pressshot_folder' ) );

$admin = $make_user( 'administrator' );
$id    = $make_shot( $admin );
wp_set_current_user( $admin );
$response = $call( 'GET', '/wp/v2/media/' . $id );
$data     = $response->get_data();
$check( 'media response is OK', 200 === $response->get_status() );
$check( 'media response has no pressshot_library', ! array_key_exists( 'pressshot_library', $data ) );
$check( 'media response has no pressshot_outside_link', ! array_key_exists( 'pressshot_outside_link', $data ) );
$check( 'pressshot_stream_file exists', function_exists( 'pressshot_stream_file' ) );

if ( function_exists( 'pressshot_stream_file' ) ) {
	$stream = pressshot_stream_file( $id, new WP_REST_Request( 'GET', '/' ) );
	$check( 'streams a local shot', $stream instanceof PressShot_Media_Response && 200 === $stream->get_status() );
	unset( $stream );
	$ranged = new WP_REST_Request( 'GET', '/' );
	$ranged->set_header( 'range', 'bytes=0-9' );
	$partial = pressshot_stream_file( $id, $ranged );
	$check( 'honors a byte range', $partial instanceof PressShot_Media_Response && 206 === $partial->get_status() );
	unset( $partial );
	$bad = new WP_REST_Request( 'GET', '/' );
	$bad->set_header( 'range', 'bytes=999999-' );
	$bad_response = pressshot_stream_file( $id, $bad );
	$check( 'bad range is 416', is_wp_error( $bad_response ) && 416 === $bad_response->get_error_data()['status'] );
	$download = new WP_REST_Request( 'GET', '/' );
	$download->set_param( 'download', '1' );
	$attached = pressshot_stream_file( $id, $download );
	$check( 'download=1 adds Content-Disposition', $attached instanceof PressShot_Media_Response && isset( $attached->get_headers()['Content-Disposition'] ) );
	unset( $attached );
	wp_set_current_user( 0 );
	$anon = pressshot_stream_file( $id, new WP_REST_Request( 'GET', '/' ) );
	$check( 'does not check the caller', $anon instanceof PressShot_Media_Response );
	unset( $anon );
	$video = $make_shot( $admin, 'video/mp4' );
	$local = pressshot_stream_file( $video, new WP_REST_Request( 'GET', '/' ) );
	$check( 'streams a local video', $local instanceof PressShot_Media_Response );
	unset( $local );
	update_post_meta( $video, 'videopress_guid', 'abcd1234' );
	$vp = pressshot_stream_file( $video, new WP_REST_Request( 'GET', '/' ) );
	$check( 'VideoPress shot is 404', is_wp_error( $vp ) && 'pressshot_not_found' === $vp->get_error_code() && 404 === $vp->get_error_data()['status'] );
	wp_delete_file( get_attached_file( $id ) );
	$gone = pressshot_stream_file( $id, new WP_REST_Request( 'GET', '/' ) );
	$check( 'missing file is 404', is_wp_error( $gone ) && 'pressshot_not_found' === $gone->get_error_code() );
	$check( 'bad id is 404', is_wp_error( pressshot_stream_file( 99999999, new WP_REST_Request( 'GET', '/' ) ) ) );
}
