<?php
/**
 * On a private WordPress.com site, REST refuses cookie-only requests, so a share page viewed by a signed-in
 * teammate must give its media addresses a REST nonce. Signed-out visitors get plain token addresses.
 */
require __DIR__ . '/harness.php';

$author = $make_user( 'author' );
$shot   = $make_shot( $author );
wp_set_current_user( $author );
$link = pressshot_link_create( 'shot', $shot );
$url  = rest_url( 'pressshot/v1/links/' . $link['token'] . '/shots/' . $shot . '/media' );

$check( 'signed-in share media address carries a REST nonce', false !== strpos( pressshot_share_media_url( $url ), '_wpnonce=' ) );
$check( 'the nonce is a valid wp_rest nonce for this person', 1 === wp_verify_nonce( wp_parse_args( wp_parse_url( pressshot_share_media_url( $url ), PHP_URL_QUERY ) )['_wpnonce'], 'wp_rest' ) );
wp_set_current_user( 0 );
$check( 'signed-out share media address has no nonce', $url === pressshot_share_media_url( $url ) );
pressshot_link_revoke( 'shot', $shot );
