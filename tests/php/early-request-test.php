<?php
/**
 * Hosts can call nocache_headers() before the main query exists (WordPress.com's Atomic loader does it
 * during plugins_loaded). The plugin's request checks must not fatal there.
 */
require __DIR__ . '/harness.php';

$saved = $GLOBALS['wp_query'] ?? null;
unset( $GLOBALS['wp_query'] );
$ok = true;
try {
	$headers = wp_get_nocache_headers();
	$share   = pressshot_share_is_request();
	$shots   = pressshot_shots_is_request();
} catch ( Throwable $e ) {
	$ok = false;
}
$GLOBALS['wp_query'] = $saved;
$check( 'nocache headers before the main query exists do not fatal', $ok && is_array( $headers ) );
$check( 'no share or /shots request is detected before the main query exists', $ok && false === $share && false === $shots );
