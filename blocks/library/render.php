<?php
/**
 * Renders the Library mount point.
 *
 * @package PressShot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

echo pressshot_library_render( $attributes, $content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside pressshot_library_render().
