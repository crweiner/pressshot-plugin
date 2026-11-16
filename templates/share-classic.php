<?php
/**
 * Share page for themes that aren't block themes. Block themes use templates/share.html.
 *
 * @package PressShot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Render first so the block's status, cache headers, and styles are in place before output starts.
$pressshot_share_html = do_blocks( '<!-- wp:pressshot/share-view /-->' );
get_header();
echo '<main id="primary" class="site-main">' . $pressshot_share_html . '</main>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block output, escaped when built.
get_footer();
