<?php
/**
 * Renders the share view for the current /s/{token} request.
 *
 * @package PressShot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

echo pressshot_share_render( pressshot_share_token(), $attributes, $content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside pressshot_share_render().
