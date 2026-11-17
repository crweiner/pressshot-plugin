<?php
/**
 * Share page on a site set to Private: the shared view on its own, with the site's styles but
 * without the theme's header, footer, or sidebars, which would show the site's menu to a visitor
 * who was only given a link. See includes/private-site.php.
 *
 * @package PressShot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Render first so the block's status, cache headers, and styles are in place before output starts.
$pressshot_share_html = do_blocks( '<!-- wp:pressshot/share-view /-->' );
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div class="wp-site-blocks"><main id="primary" class="site-main"><?php echo $pressshot_share_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block output, escaped when built. ?></main></div>
<?php wp_footer(); ?>
</body>
</html>
