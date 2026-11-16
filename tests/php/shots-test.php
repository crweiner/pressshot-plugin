<?php
/**
 * Integration checks for the shot marker, the register route, and the shots API.
 * Run with bin/test-php.sh. It creates and removes only its own users, terms, and attachments.
 */
require __DIR__ . '/harness.php';
$a      = $make_user( 'author' );
$b      = $make_user( 'author' );
$editor = $make_user( 'editor' );
$reader = $make_user( 'subscriber' );

$make_plain = function ( $author ) use ( $make_shot ) {
	$id = $make_shot( $author );
	delete_post_meta( $id, '_pressshot_capture' );
	return $id;
};
$ids_of = function ( $response ) {
	return wp_list_pluck( $response->get_data()['items'], 'id' );
};

// Register.
$plain = $make_plain( $a );
wp_set_current_user( $b );
$check( 'register: another author is refused', 403 === $call( 'POST', "/pressshot/v1/captures/$plain/register", array( 'source' => 'pressshot' ) )->get_status() );
$check( 'register: refusal leaves the attachment unmarked', ! pressshot_is_shot( $plain ) );
wp_set_current_user( 0 );
$check( 'register: signed out is refused', in_array( $call( 'POST', "/pressshot/v1/captures/$plain/register", array( 'source' => 'pressshot' ) )->get_status(), array( 401, 403 ), true ) );
wp_set_current_user( $a );
$r = $call( 'POST', "/pressshot/v1/captures/$plain/register", array( 'source' => 'pressshot' ) );
$check( 'register: author registers their own', 200 === $r->get_status() && $plain === $r->get_data()['id'] && 'pressshot' === $r->get_data()['source'] && 1 === preg_match( '#/s/[A-Za-z0-9]{32}$#', (string) $r->get_data()['shareUrl'] ) );
$check( 'register: meta is set', pressshot_is_shot( $plain ) && 'pressshot' === get_post_meta( $plain, '_pressshot_source', true ) );
$check( 'register: twice is fine', 200 === $call( 'POST', "/pressshot/v1/captures/$plain/register", array( 'source' => 'pressshot' ) )->get_status() );
$e = $call( 'POST', "/pressshot/v1/captures/$plain/register", array( 'source' => 'example-app' ) );
$check( 'register: unknown source is 400', 400 === $e->get_status() && 'pressshot_unknown_source' === $e->get_data()['code'] );
$add_example = function ( $s ) {
	$s[] = 'example-app';
	return $s;
};
add_filter( 'pressshot_shot_sources', $add_example );
$check( 'register: a filtered-in source is accepted', 200 === $call( 'POST', "/pressshot/v1/captures/$plain/register", array( 'source' => 'example-app' ) )->get_status() );
remove_filter( 'pressshot_shot_sources', $add_example );
$check( 'register: unknown attachment is 404', 404 === $call( 'POST', '/pressshot/v1/captures/99999999/register', array( 'source' => 'pressshot' ) )->get_status() );
$trashed_plain = $make_plain( $a );
wp_trash_post( $trashed_plain );
$check( 'register: a trashed attachment is refused', in_array( $call( 'POST', "/pressshot/v1/captures/$trashed_plain/register", array( 'source' => 'pressshot' ) )->get_status(), array( 403, 404 ), true ) );
wp_set_current_user( $reader );
$check( 'register: a subscriber is refused', 403 === $call( 'POST', "/pressshot/v1/captures/$plain/register", array( 'source' => 'pressshot' ) )->get_status() );

// List.
$shot_a  = $make_shot( $a );
$shot_b  = $make_shot( $b );
$video_b = $make_shot( $b, 'video/mp4' );
$unmark  = $make_plain( $b );
$gone    = $make_shot( $b );
wp_trash_post( $gone );

wp_set_current_user( 0 );
$check( 'list: signed out is refused', in_array( $call( 'GET', '/pressshot/v1/shots' )->get_status(), array( 401, 403 ), true ) );
wp_set_current_user( $reader );
$check( 'list: a subscriber is refused', 403 === $call( 'GET', '/pressshot/v1/shots' )->get_status() );
wp_set_current_user( $a );
$list = $call( 'GET', '/pressshot/v1/shots', array( 'perPage' => 100 ) );
$ids  = $ids_of( $list );
$check( 'list: author A sees B\'s shot', 200 === $list->get_status() && in_array( $shot_b, $ids, true ) && in_array( $shot_a, $ids, true ) );
$check( 'list: an unmarked attachment never appears', ! in_array( $unmark, $ids, true ) );
$check( 'list: trashed shots are left out by default', ! in_array( $gone, $ids, true ) );
$check( 'list: reports total and totalPages', $list->get_data()['total'] >= 3 && $list->get_data()['totalPages'] >= 1 );
$rec = $ids_of( $call( 'GET', '/pressshot/v1/shots', array( 'type' => 'recording', 'perPage' => 100 ) ) );
$check( 'list: type=recording returns only video', in_array( $video_b, $rec, true ) && ! in_array( $shot_a, $rec, true ) && ! array_filter( $rec, function ( $i ) { return 0 !== strpos( get_post_mime_type( $i ), 'video/' ); } ) );
$shots_only = $ids_of( $call( 'GET', '/pressshot/v1/shots', array( 'type' => 'screenshot', 'perPage' => 100 ) ) );
$check( 'list: type=screenshot leaves out video', in_array( $shot_a, $shots_only, true ) && ! in_array( $video_b, $shots_only, true ) );
$check( 'list: perPage=500 is rejected', 400 === $call( 'GET', '/pressshot/v1/shots', array( 'perPage' => 500 ) )->get_status() );
$check( 'list: page=0 is rejected', 400 === $call( 'GET', '/pressshot/v1/shots', array( 'page' => 0 ) )->get_status() );
$check( 'list: a bad status is rejected', 400 === $call( 'GET', '/pressshot/v1/shots', array( 'status' => 'all' ) )->get_status() );
$tr = $ids_of( $call( 'GET', '/pressshot/v1/shots', array( 'status' => 'trash', 'perPage' => 100 ) ) );
$check( 'list: status=trash lists only trashed shots', in_array( $gone, $tr, true ) && ! in_array( $shot_a, $tr, true ) );
$by_author = $ids_of( $call( 'GET', '/pressshot/v1/shots', array( 'author' => $a, 'perPage' => 100 ) ) );
$check( 'list: author filter', in_array( $shot_a, $by_author, true ) && ! in_array( $shot_b, $by_author, true ) );
wp_update_post( array( 'ID' => $shot_a, 'post_title' => 'Zebra ' . $shot_a ) );
$found = $ids_of( $call( 'GET', '/pressshot/v1/shots', array( 'search' => 'Zebra ' . $shot_a ) ) );
$check( 'list: search finds by title', array( $shot_a ) === $found );
$ordered = $ids_of( $call( 'GET', '/pressshot/v1/shots', array( 'orderby' => 'title', 'order' => 'asc', 'perPage' => 100 ) ) );
$check( 'list: orderby title asc puts Zebra after Test', array_search( $shot_b, $ordered, true ) < array_search( $shot_a, $ordered, true ) );
$paged = $call( 'GET', '/pressshot/v1/shots', array( 'perPage' => 1, 'page' => 2 ) );
$check( 'list: paging', 200 === $paged->get_status() && 1 === count( $paged->get_data()['items'] ) && $paged->get_data()['totalPages'] >= 3 );

// DTO.
$one = $call( 'GET', "/pressshot/v1/shots/$shot_b" );
$d   = $one->get_data();
$check( 'dto: has the agreed keys', 200 === $one->get_status() && ! array_diff( array( 'id', 'title', 'kind', 'mime', 'createdAt', 'author', 'folderId', 'tagIds', 'width', 'height', 'sizeBytes', 'videopressGuid', 'mediaUrl', 'downloadUrl', 'canEdit', 'canDelete', 'trashed', 'link' ), array_keys( $d ) ) );
$check( 'dto: values', 'screenshot' === $d['kind'] && 'image/png' === $d['mime'] && $b === $d['author']['id'] && get_userdata( $b )->display_name === $d['author']['name'] && 0 === $d['folderId'] && array() === $d['tagIds'] && false === $d['trashed'] && null === $d['link'] );
$check( 'dto: createdAt is ISO 8601 UTC', 1 === preg_match( '/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/', $d['createdAt'] ) );
$check( 'dto: media urls point at the media route', false !== strpos( $d['mediaUrl'], "/pressshot/v1/shots/$shot_b/media" ) && false !== strpos( $d['downloadUrl'], 'download=1' ) );
$check( 'dto: sizeBytes is the file size', filesize( get_attached_file( $shot_b ) ) === $d['sizeBytes'] );
$check( 'dto: A can\'t edit or delete B\'s shot', false === $d['canEdit'] && false === $d['canDelete'] );
$check( 'dto: A can edit and delete their own', true === $call( 'GET', "/pressshot/v1/shots/$shot_a" )->get_data()['canEdit'] );
$check( 'dto: an unmarked attachment is 404', 404 === $call( 'GET', "/pressshot/v1/shots/$unmark" )->get_status() );
wp_set_current_user( $editor );
$d = $call( 'GET', "/pressshot/v1/shots/$shot_b" )->get_data();
$check( 'dto: the editor can edit and delete', true === $d['canEdit'] && true === $d['canDelete'] );

// Media.
wp_set_current_user( $a );
$m = $call( 'GET', "/pressshot/v1/shots/$shot_b/media" );
$h = $m->get_headers();
$check( 'media: A gets B\'s image', 200 === $m->get_status() && 'image/png' === $h['Content-Type'] && (string) filesize( get_attached_file( $shot_b ) ) === $h['Content-Length'] );
$check( 'media: security headers', 'private, no-store, no-transform' === $h['Cache-Control'] && 'nosniff' === $h['X-Content-Type-Options'] && 'no-referrer' === $h['Referrer-Policy'] );
$check( 'media: download=1 sends an attachment', 0 === strpos( $call( 'GET', "/pressshot/v1/shots/$shot_b/media", array( 'download' => '1' ) )->get_headers()['Content-Disposition'] ?? '', 'attachment' ) );
$disposition = function ( $id ) use ( $call ) {
	return (string) ( $call( 'GET', "/pressshot/v1/shots/$id/media", array( 'download' => '1' ) )->get_headers()['Content-Disposition'] ?? '' );
};
$check( 'media: a PNG download keeps its extension', 1 === preg_match( '#^attachment; filename="[^"/]+\\.png"$#', $disposition( $shot_b ) ) );
$check( 'media: an MP4 download keeps its extension', 1 === preg_match( '#^attachment; filename="[^"/]+\\.mp4"$#', $disposition( $video_b ) ) );
$check( 'media: a trashed shot still streams', 200 === $call( 'GET', "/pressshot/v1/shots/$gone/media" )->get_status() );
$check( 'media: an unmarked attachment is 404', 404 === $call( 'GET', "/pressshot/v1/shots/$unmark/media" )->get_status() );
wp_set_current_user( $reader );
$check( 'media: a subscriber is refused', 403 === $call( 'GET', "/pressshot/v1/shots/$shot_b/media" )->get_status() );
wp_set_current_user( $a );

$missing = $make_shot( $b );
unlink( get_attached_file( $missing ) );
$check( 'media: a deleted file is 404', 404 === $call( 'GET', "/pressshot/v1/shots/$missing/media" )->get_status() );

$vp = $make_shot( $b, 'video/mp4' );
unlink( get_attached_file( $vp ) );
update_post_meta( $vp, 'videopress_guid', 'AbCdEf12' );
$vd = $call( 'GET', "/pressshot/v1/shots/$vp" )->get_data();
$check( 'media: VideoPress-only video is 404', 404 === $call( 'GET', "/pressshot/v1/shots/$vp/media" )->get_status() );
$check( 'media: VideoPress-only DTO has no mediaUrl but a guid', null === $vd['mediaUrl'] && null === $vd['downloadUrl'] && 'AbCdEf12' === $vd['videopressGuid'] );
$vp_local = $make_shot( $b, 'video/mp4' );
update_post_meta( $vp_local, 'videopress_guid', 'ZyXwVu98' );
$check( 'media: a VideoPress video is never streamed locally, even with a file', 404 === $call( 'GET', "/pressshot/v1/shots/$vp_local/media" )->get_status() );

$pdf = $make_shot( $b, 'application/pdf' );
$check( 'media: a PDF is 404', 404 === $call( 'GET', "/pressshot/v1/shots/$pdf/media" )->get_status() );

$outside_path = WP_CONTENT_DIR . '/pressshot-outside-test.png';
file_put_contents( $outside_path, 'x' );
$esc = $make_shot( $b );
$esc_original = get_post_meta( $esc, '_wp_attached_file', true );
update_post_meta( $esc, '_wp_attached_file', '../pressshot-outside-test.png' );
$check( 'media: a file outside uploads is 404', file_exists( $outside_path ) && 404 === $call( 'GET', "/pressshot/v1/shots/$esc/media" )->get_status() );
update_post_meta( $esc, '_wp_attached_file', $esc_original );
unlink( $outside_path );

// Medium size.
$full   = $make_shot( $b );
$dir    = dirname( get_attached_file( $full ) );
$med    = 'pstest-medium-' . wp_generate_password( 6, false ) . '.png';
$med_ab = $dir . '/' . $med;
file_put_contents( $med_ab, str_repeat( 'm', 40 ) . 'END' );
wp_update_attachment_metadata( $full, array( 'width' => 1, 'height' => 1, 'sizes' => array( 'medium' => array( 'file' => $med, 'width' => 1, 'height' => 1, 'mime-type' => 'image/png' ) ) ) );
$h = $call( 'GET', "/pressshot/v1/shots/$full/media", array( 'size' => 'medium' ) )->get_headers();
$check( 'medium: serves the medium file when it exists', '43' === $h['Content-Length'] );
$h = $call( 'GET', "/pressshot/v1/shots/$full/media" )->get_headers();
$check( 'medium: no size serves the original', (string) filesize( get_attached_file( $full ) ) === $h['Content-Length'] );
$check( 'medium: DTO reports width and height from metadata', 1 === $call( 'GET', "/pressshot/v1/shots/$full" )->get_data()['width'] );
unlink( $med_ab );
$h = $call( 'GET', "/pressshot/v1/shots/$full/media", array( 'size' => 'medium' ) )->get_headers();
$check( 'medium: falls back to the original when the file is gone', (string) filesize( get_attached_file( $full ) ) === $h['Content-Length'] );
wp_update_attachment_metadata( $full, array( 'sizes' => array( 'medium' => array( 'file' => '../../pressshot-outside-test.png', 'mime-type' => 'image/png' ) ) ) );
$h = $call( 'GET', "/pressshot/v1/shots/$full/media", array( 'size' => 'medium' ) )->get_headers();
$check( 'medium: a path in the metadata can\'t leave the directory', (string) filesize( get_attached_file( $full ) ) === $h['Content-Length'] );
$check( 'medium: an unknown size is rejected', 400 === $call( 'GET', "/pressshot/v1/shots/$full/media", array( 'size' => 'huge' ) )->get_status() );

// Organize.
wp_set_current_user( $editor );
$f1 = wp_insert_term( 'Folder one ' . wp_generate_password( 4, false ), 'pressshot_folder' )['term_id'];
$f2 = wp_insert_term( 'Folder two ' . wp_generate_password( 4, false ), 'pressshot_folder' )['term_id'];
$t1 = wp_insert_term( 'Tag one ' . wp_generate_password( 4, false ), 'pressshot_tag' )['term_id'];
$t2 = wp_insert_term( 'Tag two ' . wp_generate_password( 4, false ), 'pressshot_tag' )['term_id'];
wp_set_current_user( $a );
$o = $call( 'POST', '/pressshot/v1/shots/organize', array( 'ids' => array( $shot_b ), 'folder' => $f1, 'addTags' => array( $t1, $t2 ) ) );
$check( 'organize: A files B\'s shot', 200 === $o->get_status() && $f1 === $o->get_data()['items'][0]['folderId'] && 2 === count( $o->get_data()['items'][0]['tagIds'] ) );
$in_folder = $ids_of( $call( 'GET', '/pressshot/v1/shots', array( 'folder' => $f1, 'perPage' => 100 ) ) );
$check( 'list: folder filter', array( $shot_b ) === $in_folder );
wp_set_current_user( $editor );
$f1_child = wp_insert_term( 'Folder one child ' . wp_generate_password( 4, false ), 'pressshot_folder', array( 'parent' => $f1 ) )['term_id'];
wp_set_current_user( $a );
$call( 'POST', '/pressshot/v1/shots/organize', array( 'ids' => array( $shot_a ), 'folder' => $f1_child ) );
$check( 'list: a folder shows only shots filed directly in it, not in its subfolders', array( $shot_b ) === $ids_of( $call( 'GET', '/pressshot/v1/shots', array( 'folder' => $f1, 'perPage' => 100 ) ) ) );
$check( 'list: a subfolder shows its own shots', array( $shot_a ) === $ids_of( $call( 'GET', '/pressshot/v1/shots', array( 'folder' => $f1_child, 'perPage' => 100 ) ) ) );
$call( 'POST', '/pressshot/v1/shots/organize', array( 'ids' => array( $shot_a ), 'folder' => 0 ) );
$unfiled = $ids_of( $call( 'GET', '/pressshot/v1/shots', array( 'folder' => 0, 'perPage' => 100 ) ) );
$check( 'list: folder=0 is unfiled only', in_array( $shot_a, $unfiled, true ) && ! in_array( $shot_b, $unfiled, true ) );
$tagged = $ids_of( $call( 'GET', '/pressshot/v1/shots', array( 'tag' => $t2, 'perPage' => 100 ) ) );
$check( 'list: tag filter', array( $shot_b ) === $tagged );
$o = $call( 'POST', '/pressshot/v1/shots/organize', array( 'ids' => array( $shot_b ), 'removeTags' => array( $t1 ) ) );
$check( 'organize: removeTags', array( $t2 ) === $o->get_data()['items'][0]['tagIds'] && $f1 === $o->get_data()['items'][0]['folderId'] );
$call( 'POST', '/pressshot/v1/shots/organize', array( 'ids' => array( $shot_b ), 'folder' => $f1 ) );
$o = $call( 'POST', '/pressshot/v1/shots/organize', array( 'ids' => array( $shot_b ), 'folder' => $f2 ) );
$check( 'organize: two folders in a row leave exactly one', $f2 === $o->get_data()['items'][0]['folderId'] && 1 === count( wp_get_object_terms( $shot_b, 'pressshot_folder' ) ) );
$bad = $call( 'POST', '/pressshot/v1/shots/organize', array( 'ids' => array( $shot_b, $unmark ), 'folder' => $f1 ) );
$check( 'organize: a non-shot id is 400 and nothing changes', 400 === $bad->get_status() && $f2 === $call( 'GET', "/pressshot/v1/shots/$shot_b" )->get_data()['folderId'] );
$bad = $call( 'POST', '/pressshot/v1/shots/organize', array( 'ids' => array( $shot_b ), 'folder' => 99999999 ) );
$check( 'organize: an unknown folder is 400', 400 === $bad->get_status() );
$bad = $call( 'POST', '/pressshot/v1/shots/organize', array( 'ids' => array( $shot_b ), 'addTags' => array( $t1, 99999999 ) ) );
$check( 'organize: an unknown tag is 400 and nothing changes', 400 === $bad->get_status() && array( $t2 ) === $call( 'GET', "/pressshot/v1/shots/$shot_b" )->get_data()['tagIds'] );
$bad = $call( 'POST', '/pressshot/v1/shots/organize', array( 'ids' => array( $shot_b ), 'folder' => $t1 ) );
$check( 'organize: a tag id is not a folder', 400 === $bad->get_status() );
$check( 'organize: empty ids is 400', 400 === $call( 'POST', '/pressshot/v1/shots/organize', array( 'ids' => array() ) )->get_status() );
$check( 'organize: 101 ids is 400', 400 === $call( 'POST', '/pressshot/v1/shots/organize', array( 'ids' => range( 1, 101 ) ) )->get_status() );
$o = $call( 'POST', '/pressshot/v1/shots/organize', array( 'ids' => array( $shot_b ), 'folder' => 0 ) );
$check( 'organize: folder 0 unfiles', 200 === $o->get_status() && 0 === $o->get_data()['items'][0]['folderId'] );
wp_set_current_user( $reader );
$check( 'organize: a subscriber is refused', 403 === $call( 'POST', '/pressshot/v1/shots/organize', array( 'ids' => array( $shot_b ), 'folder' => $f1 ) )->get_status() );
wp_set_current_user( 0 );
$check( 'organize: signed out is refused', in_array( $call( 'POST', '/pressshot/v1/shots/organize', array( 'ids' => array( $shot_b ) ) )->get_status(), array( 401, 403 ), true ) );

wp_set_current_user( 1 );
foreach ( array( $f1_child => 'pressshot_folder', $f1 => 'pressshot_folder', $f2 => 'pressshot_folder', $t1 => 'pressshot_tag', $t2 => 'pressshot_tag' ) as $term => $taxonomy ) {
	wp_delete_term( $term, $taxonomy );
}
