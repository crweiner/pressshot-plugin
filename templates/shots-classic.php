<?php
/**
 * Library page for themes that aren't block themes. Block themes use templates/shots.html.
 *
 * @package PressShot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Render first so the block's script is enqueued before the footer prints.
$pressshot_library_html = do_blocks( '<!-- wp:pressshot/library /-->' );
get_header();
echo '<main id="primary" class="site-main">' . $pressshot_library_html . '</main>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block output, escaped when built.
get_footer();
