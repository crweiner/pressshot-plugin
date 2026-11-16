<?php
/**
 * Shared harness for the PHP integration tests. Each test starts with:
 *   require __DIR__ . '/harness.php';
 * and runs through Studio WP-CLI (see bin/test-php.sh). It prints a PASS or FAIL line per
 * check, and on shutdown deletes only the users and attachments (and files) it created.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once ABSPATH . 'wp-admin/includes/user.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$GLOBALS['pressshot_test_made'] = array(
	'users'       => array(),
	'attachments' => array(),
);

$check = function ( $name, $ok ) {
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $name . "\n";
};

$call = function ( $method, $path, $params = array(), $headers = array() ) {
	$request = new WP_REST_Request( $method, $path );
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}
	foreach ( $headers as $key => $value ) {
		$request->set_header( $key, $value );
	}
	return rest_get_server()->dispatch( $request );
};

$make_user = function ( $role ) {
	$id = wp_insert_user(
		array(
			'user_login' => 'pstest_' . $role . '_' . wp_generate_password( 8, false ),
			'user_pass'  => wp_generate_password(),
			'role'       => $role,
		)
	);
	if ( is_wp_error( $id ) ) {
		throw new RuntimeException( 'Could not create test user: ' . $id->get_error_message() );
	}
	$GLOBALS['pressshot_test_made']['users'][] = $id;
	return $id;
};

$make_shot = function ( $author, $mime = 'image/png' ) {
	if ( 'video/mp4' === $mime ) {
		$name  = 'pstest-' . wp_generate_password( 8, false ) . '.mp4';
		$bytes = "\0\0\0\x18ftypmp42\0\0\0\0mp42isom";
	} else {
		$name  = 'pstest-' . wp_generate_password( 8, false ) . '.png';
		$bytes = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=' );
	}
	$upload = wp_upload_bits( $name, null, $bytes );
	if ( ! empty( $upload['error'] ) ) {
		throw new RuntimeException( 'Could not write test file: ' . $upload['error'] );
	}
	$id = wp_insert_attachment(
		array(
			'post_mime_type' => $mime,
			'post_title'     => 'Test shot',
			'post_status'    => 'inherit',
			'post_author'    => $author,
		),
		$upload['file']
	);
	update_post_meta( $id, '_pressshot_capture', 1 );
	$GLOBALS['pressshot_test_made']['attachments'][] = $id;
	return $id;
};

register_shutdown_function(
	function () {
		wp_set_current_user( 0 );
		foreach ( $GLOBALS['pressshot_test_made']['attachments'] as $id ) {
			wp_delete_attachment( $id, true );
		}
		foreach ( $GLOBALS['pressshot_test_made']['users'] as $id ) {
			wp_delete_user( $id );
		}
	}
);
