<?php
/**
 * The Library page at /shots (the path is filterable with pressshot_shots_path).
 *
 * Signed-in people with upload_files get the Library app, mounted by the pressshot/library block
 * inside the active block theme's header and footer (template pressshot//shots; a theme can
 * override it with its own templates/shots.html). Signed-out visitors are sent to log in and come
 * back here. The rewrite rule is flushed by the shared version check in share-pages.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The Library path without slashes, default "shots". */
function pressshot_shots_path() {
	$path = trim( (string) apply_filters( 'pressshot_shots_path', 'shots' ), '/' );
	return '' === $path ? 'shots' : $path;
}

function pressshot_shots_rule() {
	return '^' . preg_quote( pressshot_shots_path(), '#' ) . '/?$';
}

function pressshot_shots_add_rewrite_rule() {
	add_rewrite_rule( pressshot_shots_rule(), 'index.php?pressshot_shots=1', 'top' );
}
add_action( 'init', 'pressshot_shots_add_rewrite_rule' );

add_filter(
	'query_vars',
	function ( $vars ) {
		$vars[] = 'pressshot_shots';
		return $vars;
	}
);

add_action(
	'init',
	function () {
		register_block_type( __DIR__ . '/../blocks/library' );
		if ( function_exists( 'register_block_template' ) ) {
			register_block_template(
				'pressshot//shots',
				array(
					'title'       => __( 'Library', 'pressshot' ),
					'description' => __( 'The PressShot Library page.', 'pressshot' ),
					'content'     => (string) file_get_contents( __DIR__ . '/../templates/shots.html' ),
				)
			);
		}
	}
);

/** True only when the request came through the /shots rewrite rule (a stray ?pressshot_shots=1 elsewhere doesn't count). */
function pressshot_shots_is_request() {
	if ( ! isset( $GLOBALS['wp_query'] ) || ! ( $GLOBALS['wp_query'] instanceof WP_Query ) ) {
		return false;
	}
	return '' !== (string) get_query_var( 'pressshot_shots' ) && pressshot_request_matched_rule( pressshot_shots_rule() );
}

/** Signed-out visitors log in first and return here. */
function pressshot_shots_gate() {
	global $wp_query;
	if ( ! pressshot_shots_is_request() ) {
		return;
	}
	if ( ! is_user_logged_in() ) {
		auth_redirect();
	}
	$wp_query->is_home = false;
	$wp_query->is_404  = false;
	nocache_headers();
}
add_action( 'template_redirect', 'pressshot_shots_gate', 1 );

// The Library is its own request: not the blog, not a 404.
add_filter(
	'pre_handle_404',
	function ( $handled ) {
		return pressshot_shots_is_request() ? true : $handled;
	}
);

add_filter(
	'posts_pre_query',
	function ( $posts, $query ) {
		return $query->is_main_query() && pressshot_shots_is_request() ? array() : $posts;
	},
	10,
	2
);

add_filter(
	'template_include',
	function ( $template ) {
		if ( ! pressshot_shots_is_request() ) {
			return $template;
		}
		if ( wp_is_block_theme() ) {
			$block_template = locate_block_template( '', 'shots', array( 'shots' ) );
			return $block_template ? $block_template : $template;
		}
		return __DIR__ . '/../templates/shots-classic.php';
	},
	99
);

// The Library is private: never indexed, wherever the block is placed.
add_filter(
	'wp_robots',
	function ( $robots ) {
		if ( pressshot_shots_is_request() || pressshot_library_is_page() ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
		}
		return $robots;
	}
);

add_filter(
	'body_class',
	function ( $classes ) {
		if ( pressshot_library_is_page() ) {
			$classes[] = 'has-pressshot-library';
			// Only restyle WordPress components when the theme's own palette defines the colors.
			$slugs = array();
			$theme = wp_get_global_settings( array( 'color', 'palette', 'theme' ) );
			if ( is_array( $theme ) ) {
				foreach ( $theme as $color ) {
					if ( is_array( $color ) && isset( $color['slug'] ) ) {
						$slugs[] = $color['slug'];
					}
				}
			}
			if ( in_array( 'base', $slugs, true ) && in_array( 'contrast', $slugs, true ) ) {
				$classes[] = 'has-pressshot-theme-colors';
			}
			if ( in_array( 'accent', $slugs, true ) ) {
				$classes[] = 'has-pressshot-accent';
			}
		}
		return $classes;
	}
);

add_filter(
	'document_title_parts',
	function ( $parts ) {
		if ( pressshot_shots_is_request() ) {
			$parts['title'] = __( 'Library', 'pressshot' );
		}
		return $parts;
	}
);

/** The Library path as the browser sees it (it includes the site's subdirectory, if any). */
function pressshot_shots_url_path() {
	return (string) wp_parse_url( home_url( '/' . pressshot_shots_path() ), PHP_URL_PATH );
}

/** The built app's asset file. Filterable for tests. */
function pressshot_library_asset_file() {
	return (string) apply_filters( 'pressshot_library_asset_file', __DIR__ . '/../build/library.asset.php' );
}

/** The WordPress script handles the built app needs (from build/library.asset.php). Filterable for tests. */
function pressshot_library_dependencies() {
	$file  = pressshot_library_asset_file();
	$asset = is_readable( $file ) ? include $file : array();
	$deps  = isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] ) ? $asset['dependencies'] : array();
	return (array) apply_filters( 'pressshot_library_dependencies', $deps );
}

/**
 * Dependencies the running WordPress doesn't register. WordPress silently skips a script with a
 * missing dependency. A missing build (no build/library.asset.php) counts as missing too, so the
 * page shows a message instead of an empty mount.
 */
function pressshot_library_missing_dependencies() {
	if ( ! is_readable( pressshot_library_asset_file() ) ) {
		return array( 'build/library.asset.php' );
	}
	$scripts = wp_scripts();
	return array_values(
		array_filter(
			pressshot_library_dependencies(),
			function ( $handle ) use ( $scripts ) {
				return ! $scripts->query( $handle, 'registered' );
			}
		)
	);
}

/** True when the app can load on this WordPress. */
function pressshot_library_can_load() {
	return ! pressshot_library_missing_dependencies();
}

/** True once the Library block has rendered in this request. */
function pressshot_library_rendered() {
	return ! empty( $GLOBALS['pressshot_library_rendered'] );
}

/** Forget that the block rendered or was spotted early (tests render several times in one request). */
function pressshot_library_reset_rendered() {
	$GLOBALS['pressshot_library_rendered'] = false;
	$GLOBALS['pressshot_library_marked']   = false;
}

/** True when this request is a Library page: the block rendered, or the page's content holds it. */
function pressshot_library_is_page() {
	return pressshot_library_rendered() || ! empty( $GLOBALS['pressshot_library_marked'] );
}

/** The URL the visitor asked for, so signing in brings them back here (correct on subdirectory installs). */
function pressshot_library_request_url() {
	$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
	$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Escaped with esc_url_raw below.
	if ( '' === $host ) {
		return home_url( '/' );
	}
	return esc_url_raw( set_url_scheme( 'http://' . $host . $uri ) );
}

// Before the theme prints its head and body class: spot the block in the page's content so a
// classic theme still gets the privacy headers, noindex, and body class.
add_action(
	'template_redirect',
	function () {
		if ( is_singular() && has_block( 'pressshot/library', get_queried_object() ) ) {
			$GLOBALS['pressshot_library_marked'] = true;
			nocache_headers();
		}
	},
	1
);

/**
 * The block's markup: the mount div for people who can use the Library, a sign-in prompt for
 * signed-out visitors, otherwise a plain message. Only the first block in a request renders, and
 * the page it lands on is kept private (no caching, no indexing).
 *
 * @param array  $attributes Block attributes (appLayout).
 * @param string $content    Rendered inner blocks, shown in the app's sidebar when the app layout is on.
 */
function pressshot_library_render( $attributes = array(), $content = '' ) {
	if ( pressshot_library_rendered() ) {
		return '';
	}
	$GLOBALS['pressshot_library_rendered'] = true;
	if ( ! headers_sent() ) {
		nocache_headers();
	}
	if ( ! is_user_logged_in() ) {
		return sprintf(
			'<div %s><p>%s</p><p><a class="wp-element-button" href="%s">%s</a></p></div>',
			get_block_wrapper_attributes( array( 'class' => 'is-message' ) ),
			esc_html__( 'Sign in to see the Library.', 'pressshot' ),
			esc_url( wp_login_url( pressshot_library_request_url() ) ),
			esc_html__( 'Sign in', 'pressshot' )
		);
	}
	if ( ! pressshot_can_use_library() ) {
		return sprintf(
			'<div %s><p>%s</p></div>',
			get_block_wrapper_attributes( array( 'class' => 'is-message' ) ),
			esc_html__( "You don't have access to the Library on this site.", 'pressshot' )
		);
	}
	if ( ! pressshot_library_can_load() ) {
		return sprintf(
			'<div %s><p>%s</p></div>',
			get_block_wrapper_attributes( array( 'class' => 'is-message' ) ),
			is_readable( pressshot_library_asset_file() )
				? esc_html__( 'The Library needs a newer version of WordPress.', 'pressshot' )
				: esc_html__( 'The Library\'s app files are missing. Reinstall the plugin.', 'pressshot' )
		);
	}
	$app_layout = ! empty( $attributes['appLayout'] );
	$wrapper    = get_block_wrapper_attributes( $app_layout ? array( 'class' => 'is-app-layout' ) : array() );
	// Inner blocks wait in a hidden slot; the app moves them into its sidebar.
	$extras = '' !== trim( (string) $content )
		? '<div class="pressshot-library__extras" hidden>' . $content . '</div>'
		: '';
	return sprintf(
		'<div %s><div id="pressshot-library" data-shots-path="%s" data-user-id="%d" data-avatar-url="%s" data-rest-nonce="%s" data-authors="%s" data-can-share-folders="%d" data-app-layout="%d" data-site-name="%s" data-site-icon="%s" data-home-url="%s" data-logout-url="%s" data-user-name="%s" data-profile-url="%s"></div>%s</div>',
		$wrapper,
		esc_attr( pressshot_shots_url_path() ),
		get_current_user_id(),
		// The sidebar shows it for My shots; empty when avatars are off, and the app falls back to an icon.
		esc_url( (string) get_avatar_url( get_current_user_id(), array( 'size' => 48 ) ) ),
		esc_attr( wp_create_nonce( 'wp_rest' ) ),
		esc_attr( wp_json_encode( pressshot_library_authors() ) ),
		// Folder links need edit_others_posts (Editors and Admins); the app hides Share folder link otherwise.
		current_user_can( 'edit_others_posts' ) ? 1 : 0,
		$app_layout ? 1 : 0,
		esc_attr( get_bloginfo( 'name' ) ),
		esc_url( (string) get_site_icon_url( 64 ) ),
		esc_url( home_url( '/' ) ),
		esc_url( wp_logout_url( home_url( '/' ) ) ),
		esc_attr( wp_get_current_user()->display_name ),
		esc_url( get_edit_profile_url() ),
		$extras // Inner block output, already rendered and escaped by core.
	);
}

/**
 * People who can add shots, for the Library's person filter: everyone with upload_files.
 *
 * @return array<int, array{id: int, name: string}>
 */
function pressshot_library_authors() {
	$users = get_users(
		array(
			'capability' => 'upload_files',
			'fields'     => array( 'ID', 'display_name' ),
			'orderby'    => 'display_name',
		)
	);
	return array_map(
		function ( $user ) {
			return array(
				'id'   => (int) $user->ID,
				'name' => $user->display_name,
			);
		},
		$users
	);
}

// Core enqueues a block's viewScript whether or not it rendered the app; drop it for people without access or when WordPress is too old.
add_filter(
	'render_block_pressshot/library',
	function ( $content ) {
		if ( ! pressshot_can_use_library() || ! pressshot_library_can_load() ) {
			wp_dequeue_script( 'pressshot-library-view-script' );
		}
		return $content;
	}
);
