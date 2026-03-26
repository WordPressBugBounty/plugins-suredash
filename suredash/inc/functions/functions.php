<?php
/**
 * Plugin functions.
 *
 * @package SureDash
 * @since 0.0.1
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SureDashboard\Inc\Compatibility\Plugin;
use SureDashboard\Inc\Services\Query;
use SureDashboard\Inc\Services\Router;
use SureDashboard\Inc\Utils\Helper;
use SureDashboard\Inc\Utils\Labels;
use SureDashboard\Inc\Utils\PostMeta;

/**
 * Check if pro version is active.
 *
 * @return bool
 * @since 0.0.1
 */
function suredash_is_pro_active() {
	return defined( 'SUREDASH_PRO_VER' );
}

/**
 * Check if SureMembers (Core or Pro) is active.
 *
 * @return bool
 *
 * @since 1.7.0
 */
function suredash_is_suremembers_active() {
	return defined( 'SUREMEMBERS_VER' ) || defined( 'SUREMEMBERS_CORE_VER' );
}

/**
 * Clean variables using sanitize_text_field.
 *
 * @param mixed $var Data to sanitize.
 * @return mixed
 *
 * @since 0.0.1
 */
function suredash_clean_data( $var ) {
	if ( is_array( $var ) ) {
		return array_map( 'suredash_clean_data', $var );
	}
	return is_scalar( $var ) ? sanitize_text_field( (string) $var ) : $var;
}

/**
 * Get template part implementation for Portals.
 *
 * @param string       $slug Template slug.
 * @param string       $name Template name.
 * @param array<mixed> $args Template passing data.
 * @param bool         $return Flag for return with ob_start.
 *
 * @return string Return html file.
 * @since 0.0.1
 */
function suredash_get_template_part( $slug, $name = '', $args = [], $return = false ) {
	// Make template variables available instead of using extract().
	foreach ( $args as $key => $value ) {
		$$key = $value;
	}

	$template = '';

	// Maybe in yourtheme/suredash/slug-name.php and yourtheme/suredash/slug.php.
	$template_path = ! empty( $name ) ? "{$slug}/{$name}.php" : "{$slug}.php";
	$template      = locate_template( [ 'suredash/' . $template_path ] );

	/**
	 * Change template directory path filter.
	 *
	 * @since 0.0.1
	 */
	$template_path = apply_filters( 'suredash_set_template_path', untrailingslashit( SUREDASHBOARD_DIR ) . '/templates', $template, $args );

	// Get default slug-name.php.
	if ( ! $template && $name && file_exists( $template_path . "/{$slug}/{$name}.php" ) ) {
		$template = $template_path . "/{$slug}/{$name}.php";
	}

	if ( ! $template && ! $name && file_exists( $template_path . "/{$slug}.php" ) ) {
		$template = $template_path . "/{$slug}.php";
	}

	if ( $template ) {
		if ( $return ) {
			ob_start();
			require $template;
			return (string) ob_get_clean();
		}
		require $template;
		return '';
	}

	return '';
}

/**
 * Get template part implementation for restricted content.
 *
 * @param int          $post_id Post ID.
 * @param string       $slug Template slug.
 * @param string       $name Template slug.
 * @param array<mixed> $args Template passing data.
 * @param bool         $return Flag for return with ob_start.
 *
 * @return mixed
 * @since 1.0.0
 */
function suredash_get_restricted_template_part( $post_id, $slug, $name = '', $args = [], $return = false ) {
	$skip_check = ! empty( $args['skip_restriction_check'] );

	if ( ! $skip_check ) {
		$restriction_details = Helper::maybe_third_party_restricted( $post_id );
		$may_restricted      = $restriction_details['status'] ?? false;
		$restriction_content = $restriction_details['content'] ?? false;

		if ( $may_restricted && $restriction_content ) {
			echo do_shortcode( $restriction_content );
			return;
		}
	}

	return suredash_get_template_part(
		$slug,
		$name,
		[
			'icon'        => 'Lock',
			'label'       => 'restricted_content',
			'description' => 'restricted_content_description',
		],
		$return
	);
}

/**
 * Foreground Color
 *
 * @param string $hex Color code in HEX format.
 * @return string      Return foreground color depend on input HEX color.
 */
function suredash_get_foreground_color( $hex ) {
	$hex = apply_filters( 'suredashboard_before_foreground_color_generation', $hex );

	// bail early if color's not set.
	if ( $hex === 'transparent' || $hex === 'false' || $hex === '#' || empty( $hex ) ) {
		return 'transparent';
	}

	// Get clean hex code.
	$hex = str_replace( '#', '', $hex );

	if ( strlen( $hex ) === 3 ) {
		$hex = str_repeat( substr( $hex, 0, 1 ), 2 ) . str_repeat( substr( $hex, 1, 1 ), 2 ) . str_repeat( substr( $hex, 2, 1 ), 2 );
	}

	if ( strpos( $hex, 'rgba' ) !== false ) {
		$rgba = preg_replace_callback(
			'/[^0-9,]/',
			static function() {
				return '';
			},
			$hex
		);
		$rgba = explode( ',', is_string( $rgba ) ? $rgba : '' );

		$hex = sprintf( '#%02x%02x%02x', $rgba[0], $rgba[1], $rgba[2] );
	}

	// Return if non hex.
	if ( function_exists( 'ctype_xdigit' ) && is_callable( 'ctype_xdigit' ) ) {
		if ( ! ctype_xdigit( $hex ) ) {
			return $hex;
		}
	} else {
		if ( ! preg_match( '/^[a-f0-9]{2,}$/i', $hex ) ) {
			return $hex;
		}
	}

	// Get r, g & b codes from hex code.
	$r   = hexdec( substr( $hex, 0, 2 ) );
	$g   = hexdec( substr( $hex, 2, 2 ) );
	$b   = hexdec( substr( $hex, 4, 2 ) );
	$hex = ( ( $r * 299 ) + ( $g * 587 ) + ( $b * 114 ) ) / 1000;

	return 128 <= $hex ? '#000000' : '#ffffff';
}

/**
 * Trim CSS
 *
 * @param string $css CSS content to trim.
 * @return string
 *
 * @since 0.0.1
 */
function suredash_trim_css( $css = '' ) {
	// Trim white space for faster page loading.
	if ( is_string( $css ) && ! empty( $css ) ) {
		$css = preg_replace_callback(
			'!/\*[^*]*\*+([^/][^*]*\*+)*/!',
			static function() {
				return '';
			},
			$css
		);
		$css = str_replace( [ "\r\n", "\r", "\n", "\t", '  ', '    ', '    ' ], '', (string) $css );
		$css = str_replace( ', ', ',', (string) $css );
	}

	return $css;
}

/**
 * Clean text - Remove HTML tags.
 *
 * @param string $content Text to clean.
 * @return string
 *
 * @since 0.0.1
 */
function suredash_clean_text( $content ) {
	$content = html_entity_decode( $content );
	$content = str_replace( [ '</p>', '</div>', '</li>' ], ' ', $content );
	$content = str_replace( [ '<br>', '<br/>', '<br />' ], ' ', $content );
	return wp_strip_all_tags( $content );
}

/**
 * Parse CSS
 *
 * @param array<mixed> $css_output Array of CSS.
 * @param mixed        $min_media Min Media breakpoint.
 * @param mixed        $max_media Max Media breakpoint.
 * @return string             Generated CSS.
 *
 * @since 0.0.1
 */
function suredash_parse_css( $css_output = [], $min_media = '', $max_media = '' ) {
	$parse_css = '';
	if ( is_array( $css_output ) && count( $css_output ) > 0 ) {
		foreach ( $css_output as $selector => $properties ) {
			if ( $properties === null ) {
				break;
			}

			if ( ! count( $properties ) ) {
				continue;
			}

			$temp_parse_css   = $selector . '{';
			$properties_added = 0;

			foreach ( $properties as $property => $value ) {
				if ( $value === '' && $value !== 0 ) {
					continue;
				}

				$properties_added++;
				$temp_parse_css .= $property . ':' . $value . ';';
			}

			$temp_parse_css .= '}';

			if ( $properties_added > 0 ) {
				$parse_css .= $temp_parse_css;
			}
		}

		if ( $parse_css !== '' && ( $min_media !== '' || $max_media !== '' ) ) {
			$media_css       = '@media ';
			$min_media_css   = '';
			$max_media_css   = '';
			$media_separator = '';

			if ( $min_media !== '' ) {
				$min_media_css = '(min-width:' . $min_media . 'px)';
			}
			if ( $max_media !== '' ) {
				$max_media_css = '(max-width:' . $max_media . 'px)';
			}
			if ( $min_media !== '' && $max_media !== '' ) {
				$media_separator = ' and ';
			}

			return $media_css . $min_media_css . $media_separator . $max_media_css . '{' . $parse_css . '}';
		}
	}

	return $parse_css;
}

/**
 * Get all internal sub queries.
 *
 * @return array<int, string>
 * @since 0.0.1
 */
function suredash_sub_queries() {
	return apply_filters(
		'suredashboard_portal_sub_queries',
		[
			'user-profile',
			'bookmarks',
			'user-view',
			'feeds',
			'screen',
			'leaderboard',
		]
	);
}

/**
 * Validate if current page is of Portal's sub queried page.
 *
 * @return bool Return endpoint if true else false.
 * @since 0.0.1
 * @package SureDash
 */
function suredash_is_sub_queried_page() {
	$portal_sub_query = get_query_var( 'portal_subpage' );

	return $portal_sub_query && in_array( $portal_sub_query, suredash_sub_queries(), true );
}

/**
 * Get current sub queried page.
 *
 * @return string
 * @since 0.0.1
 */
function suredash_get_sub_queried_page() {
	$portal_sub_query = '';

	if ( suredash_is_sub_queried_page() ) {
		return get_query_var( 'portal_subpage' );
	}

	return $portal_sub_query;
}

/**
 * Validate if current page is of Portal's home.
 *
 * @since 0.0.1
 * @package SureDash
 *
 * @return bool
 */
function suredash_is_home() {
	return is_post_type_archive( SUREDASHBOARD_POST_TYPE ) && ! suredash_is_sub_queried_page() && ! is_tax( SUREDASHBOARD_TAXONOMY ) && ! is_singular( SUREDASHBOARD_POST_TYPE );
}

/**
 * Validate if current page is of Portals type.
 *
 * @package SureDash
 * @since 0.0.1
 *
 * @return bool
 */
function suredash_portal() {
	if ( is_tax( SUREDASHBOARD_TAXONOMY ) || suredash_is_home() || is_singular( SUREDASHBOARD_POST_TYPE ) || suredash_is_sub_queried_page() ) {
		return true;
	}

	return false;
}

/**
 * Validate if current page is of SureDash CPT.
 *
 * @package SureDash
 * @since 0.0.1
 *
 * @return bool
 */
function suredash_cpt() {
	if ( is_singular( SUREDASHBOARD_FEED_POST_TYPE ) || is_post_type_archive( SUREDASHBOARD_FEED_POST_TYPE ) || is_tax( SUREDASHBOARD_FEED_TAXONOMY ) || is_singular( SUREDASHBOARD_SUB_CONTENT_POST_TYPE ) ) {
		return true;
	}

	return false;
}

/**
 * Check if current page is a SureDash login or register page.
 *
 * @package SureDash
 * @since 1.4.0
 *
 * @return bool
 */
function suredash_is_auth_page() {
	if ( ! is_singular( 'page' ) ) {
		return false;
	}

	$current_page_id = get_queried_object_id();

	$login_page    = Helper::get_option( 'login_page' );
	$login_page_id = is_array( $login_page ) && ! empty( $login_page['value'] ) ? absint( $login_page['value'] ) : 0;

	$register_page    = Helper::get_option( 'register_page' );
	$register_page_id = is_array( $register_page ) && ! empty( $register_page['value'] ) ? absint( $register_page['value'] ) : 0;

	return ( $current_page_id === $login_page_id ) || ( $current_page_id === $register_page_id );
}

/**
 * Check if current page has SureDash blocks (excluding login and register blocks).
 *
 * @since 1.6.0
 * @return bool
 */
function has_suredash_blocks() {
	global $post;

	if ( ! $post instanceof \WP_Post ) {
		return false;
	}

	$content = $post->post_content;

	// Return false if page only has login/register blocks.
	if ( preg_match( '/<!-- wp:suredash\/(login|register)/', $content ) ) {
		return false;
	}

	// Check for any other SureDash blocks.
	if ( ! empty( $content ) && strpos( $content, '<!-- wp:suredash/' ) !== false ) {
		return true;
	}

	/**
	 * Filter to override the automatic block detection.
	 *
	 * @param bool     $has_blocks Whether SureDash blocks are present.
	 * @param \WP_Post $post       The current post object.
	 * @since 1.6.0
	 */
	return apply_filters( 'suredash_should_load_portal_assets', false, $post );
}

/**
 * Check if current page is using portal-container template.
 *
 * @return bool
 * @since 1.6.0
 */
function suredash_is_portal_container_template() {
	if ( wp_is_block_theme() ) {
		global $_wp_current_template_id;
		return isset( $_wp_current_template_id ) && $_wp_current_template_id === 'suredash/suredash//portal-container';
	}

	// Check for classic/hybrid themes.
	$page_template = get_page_template_slug();
	return $page_template === 'templates/pages/template-portal-container.php';
}

/**
 * Validate if current page is of SureDash frontend.
 *
 * @package SureDash
 * @since 0.0.1
 *
 * @return bool
 */
function suredash_frontend() {
	if ( suredash_portal() || suredash_cpt() || suredash_screen() || ( defined( '__BREAKDANCE_DIR__' ) && suredash_is_auth_page() ) || ( is_front_page() && Helper::get_option( 'portal_as_homepage' ) ) ) {
		return true;
	}

	// Check if page/template has SureDash blocks.
	if ( has_suredash_blocks() ) {
		return true;
	}

	// Check if using portal-container template.
	if ( suredash_is_portal_container_template() ) {
		return true;
	}

	return false;
}

/**
 * Note: Fallback function as is_suredash_frontend() is deprecated.
 * Maintain only 2-3 updates.
 *
 * @package SureDash
 * @since 0.0.1
 *
 * @return bool
 */
function is_suredash_frontend() {
	return suredash_frontend();
}

/**
 * Validate if current page is showcasing only content.
 *
 * @return bool Return true if content is being showcased.
 * @since 0.0.2
 * @package SureDash
 */
function suredash_simply_content() {
	return ! empty( $_GET['simply_content'] ) && absint( $_GET['simply_content'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Handled by absint().
}

/**
 * Validate if current page is showcasing only content.
 *
 * @return bool Return true if content is being showcased.
 * @since 1.0.0
 * @package SureDash
 */
function suredash_show_content_only() {
	return ! empty( $_GET['show_content_only'] ) && absint( $_GET['show_content_only'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Handled by absint().
}

/**
 * Get content post slug dynamically, introduced for better flexibility.
 *
 * @param string $type Type of content post.
 * @return string content post extended slug.
 * @since 1.5.0
 */
function suredash_get_community_content_slug( $type = 'lesson' ) {
	return apply_filters( "suredash_community_content_post_{$type}_slug", $type );
}

/**
 * Get all end points.
 *
 * @param bool $get_frontend_slugs To get frontend slugs or just handles.
 * @return array<int, string>
 * @since 1.0.0
 */
function suredash_all_content_types( $get_frontend_slugs = false ) {
	if ( $get_frontend_slugs ) {
		return apply_filters(
			'suredashboard_all_endpoints',
			[
				suredash_get_community_content_slug( 'lesson' ),
				suredash_get_community_content_slug( 'resource' ),
				suredash_get_community_content_slug( 'event' ),
			]
		);
	}

	return apply_filters(
		'suredashboard_all_endpoints',
		[
			'lesson',
			'resource',
			'event',
		]
	);
}

/**
 * Validate if current page is of Portal's endpoint.
 *
 * @return string|bool Return endpoint if true else false.
 * @since 0.0.1
 * @package SureDash
 */
function suredash_content_post() {
	if ( is_singular( SUREDASHBOARD_SUB_CONTENT_POST_TYPE ) ) {
		$post_id      = suredash_get_post_id();
		$content_type = sd_get_post_meta( $post_id, 'content_type', true );

		switch ( $content_type ) {
			case 'resource':
				$type = 'resource';
				break;
			case 'event':
				$type = 'event';
				break;
			case 'lesson':
			default:
				$type = 'lesson';
				break;

		}

		return $type;
	}

	return false;
}

/**
 * Get Portal's endpoint data.
 *
 * @param string $endpoint Endpoint.
 * @param mixed  $dataset  Dataset.
 * @param int    $base_id Base ID.
 *
 * @return array<mixed>
 * @since 0.0.1
 */
function suredash_endpoint_data( $endpoint = '', $dataset = '', $base_id = 0 ) {
	$args = [];

	if ( ! empty( $endpoint ) && $dataset && $base_id ) {
		$args = [ 'endpoint' => $endpoint ];

		switch ( $endpoint ) {
			case 'lesson':
				$course_loop         = PostMeta::get_post_meta_value( $base_id, 'pp_course_section_loop' );
				$args['media']       = $dataset;
				$args['course_loop'] = $course_loop;
				break;
			case 'resource':
				$args['media']         = $dataset;
				$args['resource_loop'] = PostMeta::get_post_meta_value( $base_id, 'resource_ids' );
				break;
			case 'event':
				$args['media']      = $dataset;
				$args['space_id']   = $base_id;
				$args['event_loop'] = PostMeta::get_post_meta_value( $base_id, 'event_ids' );
				break;
		}
	}

	return apply_filters( 'suredashboard_' . $endpoint . '_endpoint_data', $args, $endpoint, $base_id );
}

/**
 * Update comments mention links.
 *
 * @param string          $comment_text Text of the comment.
 * @param WP_Comment|null $comment      The comment object. Null if not found.
 * @param array<mixed>    $args         An array of arguments.
 *
 * @return string Comment text with mention links.
 * @since 0.0.1
 */
function suredash_update_mention_links( $comment_text, $comment, $args ) {
	return suredash_dynamic_content_support( $comment_text );
}

/**
 * Get shorthand time format for a comment.
 *
 * @param int $comment_id The comment ID.
 * @return string The formatted time (e.g., 2h, 1d, 1w).
 */
function suredash_get_shorthand_comment_time( $comment_id ) {
	$comment_time      = get_comment_date( 'Y-m-d H:i:s', $comment_id ); // Get the comment time.
	$comment_timestamp = strtotime( $comment_time );
	$current_timestamp = suredash_get_timestamp();

	$time_difference = $current_timestamp - $comment_timestamp;
	$shorthand_time  = '';

	switch ( true ) {
		case $time_difference < MINUTE_IN_SECONDS:
			// Less than a minute ago.
			$shorthand_time = esc_html__( 'Just now', 'suredash' );
			break;

		case $time_difference < HOUR_IN_SECONDS:
			// Less than an hour ago.
			$minutes        = round( $time_difference / MINUTE_IN_SECONDS );
			$shorthand_time = $minutes . 'm';
			break;

		case $time_difference < DAY_IN_SECONDS:
			// Less than a day ago.
			$hours          = round( $time_difference / HOUR_IN_SECONDS );
			$shorthand_time = $hours . 'h';
			break;

		case $time_difference < WEEK_IN_SECONDS:
			// Less than a week ago.
			$days           = round( $time_difference / DAY_IN_SECONDS );
			$shorthand_time = $days . 'd';
			break;

		default:
			// More than a week ago.
			$weeks          = round( $time_difference / WEEK_IN_SECONDS );
			$shorthand_time = $weeks . 'w';
			break;
	}

	return $shorthand_time;
}

/**
 * Get the author name of a comment.
 *
 * @param string $author_name The author name.
 * @param int    $user_id The user ID.
 * @return string The author name.
 */
function suredash_get_author_name( $author_name, $user_id = 0 ) {
	// Check if $author_name is in mail format, if yes then get the user name.
	if ( $user_id && is_email( $author_name ) ) {
		$author_name = get_the_author_meta( 'display_name', $user_id );
	}

	// If the author name is still in mail format, then set take the name before the @.
	if ( is_email( $author_name ) ) {
		$fallback_name = $author_name;
		$author_name   = explode( '@', $author_name );
		$author_name   = ! empty( $author_name[0] ) ? $author_name[0] : $fallback_name;
	}

	return $author_name;
}

/**
 * Template for comments and pingbacks.
 *
 * To override this walker in a child theme without modifying the comments template
 * simply create your own suredash_comments_list_callback(), and that function will be used instead.
 *
 * Used as a callback by wp_list_comments() for displaying the comments.
 *
 * @param \WP_Comment  $comment Comment object.
 * @param array<mixed> $args Comment arguments.
 * @param int          $depth Depth of the comment.
 * @return mixed          Comment markup.
 */
function suredash_comments_list_callback( $comment, $args, $depth ) {
	if ( ! $comment instanceof \WP_Comment ) {
		return;
	}

	$GLOBALS['comment'] = $comment; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	$depth              = $args['depth'] ?? $depth;
	$reply_link_class   = $args['reply_link_class'] ?? 'portal-reply-link';

	switch ( $comment->comment_type ) {
		case 'pingback':
		case 'trackback':
			?>
			<li <?php comment_class(); ?> id="comment-<?php comment_ID(); ?>" data-depth="<?php echo esc_attr( $depth ); ?>">
				<p><?php esc_html_e( 'Pingback:', 'suredash' ); ?><?php comment_author_link(); ?><?php edit_comment_link( __( '(Edit)', 'suredash' ), '<span class="edit-link">', '</span>' ); ?></p>
			</li>
			<?php
			break;

		default:
			$comment_id = absint( ! empty( $comment->comment_ID ) ? $comment->comment_ID : 0 );
			$author_id  = absint( ! empty( $comment->user_id ) ? $comment->user_id : 0 );
			if ( ! $comment_id || ! $author_id ) {
				return;
			}
			$time = suredash_get_shorthand_comment_time( $comment_id );
			/* translators: 1: date, 2: time */
			$title_time = esc_html( sprintf( __( '%1$s at %2$s', 'suredash' ), get_comment_date(), get_comment_time() ) );

			// Get child comments.
			$child_comments  = get_comments(
				[
					'parent' => $comment_id,
					'order'  => 'ASC',
				]
			);
			$comment_author  = $comment->comment_author;
			$comment_user_id = $comment->user_id;
			$comment_user_id = absint( $comment_user_id );
			$user_exists     = ! empty( $comment_user_id ) && get_userdata( $comment_user_id );
			$user_view       = $user_exists ? suredash_get_user_view_link( $comment_user_id ) : '';
			?>
			<li <?php comment_class( ( $depth > 0 ? 'portal-comment-threaded' : 'portal-comment-root' ) . ' sd-pt-4' ); ?> id="li-comment-<?php comment_ID(); ?>" data-depth="<?php echo esc_attr( $depth ); ?>">
				<?php $comment_author = suredash_get_author_name( $comment_author, (int) $comment_user_id ); ?>
				<div class="portal-thread-gutter">
					<?php suredash_get_user_avatar( $author_id, true, 32 ); ?>
					<?php if ( ! empty( $child_comments ) ) { ?>
						<div class="portal-thread-line"></div>
					<?php } ?>
				</div>
				<div class="portal-thread-content">
				<article id="comment-<?php comment_ID(); ?>" class="portal-comment" data-comment-depth="<?php echo esc_attr( $depth ); ?>">
						<div class="portal-comment-section-wrap">
							<div class="portal-comment-section">

								<div class="portal-comment-bubble-header sd-flex sd-items-center sd-justify-between">
									<div class="sd-no-space sd-flex sd-items-center sd-gap-8">
										<?php if ( $user_exists && ! empty( $user_view ) ) { ?>
											<a href="<?php echo esc_url( $user_view ); ?>" class="portal-user-commenter" aria-label="comment by <?php echo esc_html( $comment_author ); ?>"> <?php echo esc_html( $comment_author ); ?> </a>
										<?php } else { ?>
											<span class="portal-user-commenter" aria-label="comment by <?php echo esc_html( $comment_author ); ?>"> <?php echo esc_html( $comment_author ); ?> </span>
										<?php } ?>
										<?php
										do_action( 'suredash_before_user_badges', $author_id );
										suredash_get_user_badges( $author_id, 2 );
										?>
									</div>

									<div class="portal-comment-actions sd-ml-auto">
										<button class="portal-comment-menu-trigger portal-button button-ghost sd-p-4 sd-hover-bg-secondary" aria-label="<?php esc_attr_e( 'Comment actions', 'suredash' ); ?>" data-comment-id="<?php echo esc_attr( strval( $comment_id ) ); ?>">
											<?php Helper::get_library_icon( 'Ellipsis' ); ?>
										</button>
										<div class="portal-comment-dropdown portal-content portal-thread-dropdown" data-comment-id="<?php echo esc_attr( (string) $comment_id ); ?>" style="display: none;">
											<?php
											$current_user_id   = get_current_user_id();
											$is_comment_author = $author_id === $current_user_id;
											$is_portal_manager = function_exists( 'suredash_is_user_manager' ) && suredash_is_user_manager( $current_user_id );
											?>
											<?php if ( $is_comment_author ) { ?>
												<button class="portal-thread-edit" data-comment-id="<?php echo esc_attr( (string) $comment_id ); ?>">
													<?php esc_html_e( 'Edit', 'suredash' ); ?>
												</button>
											<?php } ?>
											<?php if ( $is_comment_author || $is_portal_manager ) { ?>
												<button class="portal-thread-delete" data-comment-id="<?php echo esc_attr( (string) $comment_id ); ?>">
													<?php esc_html_e( 'Delete', 'suredash' ); ?>
												</button>
											<?php } ?>
											<button class="portal-thread-copy-url" data-comment-id="<?php echo esc_attr( (string) $comment_id ); ?>" data-post-url="<?php echo esc_url( (string) get_permalink( absint( $comment->comment_post_ID ) ) ); ?>">
												<?php esc_html_e( 'Copy URL', 'suredash' ); ?>
											</button>
										</div>
									</div>
								</div>

								<section class="portal-comment-content comment">
									<?php
										add_filter( 'comment_text', 'suredash_update_mention_links', 10, 3 );
										comment_text();
										remove_filter( 'comment_text', 'suredash_update_mention_links' );
									?>
									<?php if ( property_exists( $comment, 'comment_approved' ) && $comment->comment_approved === '0' ) { ?>
										<em class="comment-awaiting-moderation" aria-label="<?php echo esc_attr__( 'Your comment is awaiting moderation.', 'suredash' ); ?>">
											<?php echo esc_html__( 'Your comment is awaiting moderation.', 'suredash' ); ?>
										</em>
									<?php } ?>
								</section>
							</div>

							<section class="portal-comment-meta portal-row portal-comment-author vcard">
								<div class="portal-comment-reactions-wrap">
									<span class="timendate sd-flex">
										<time datetime="<?php echo esc_attr( get_comment_time( 'c' ) ); ?>" title="<?php echo esc_attr( $title_time ); ?>" aria-label="<?php echo esc_attr( $time ); ?> ago">
											<?php echo esc_attr( $time ); ?>
										</time>
									</span>
									<?php
										$edited_time = get_comment_meta( $comment_id, 'suredash_comment_edited', true );
									if ( ! empty( $edited_time ) ) {
										?>
											<span class="portal-comment-edited sd-font-12">(<?php echo esc_attr( __( 'Edited', 'suredash' ) ); ?>)</span>
											<?php
									}
									?>

									<?php
										$user_liked_comments = sd_get_user_meta( get_current_user_id(), 'portal_user_liked_comments', true );
										$user_liked_comments = ! empty( $user_liked_comments ) ? $user_liked_comments : [];
										$is_user_liked       = in_array( absint( $comment_id ), $user_liked_comments, true );
										$like_text           = $is_user_liked ? Labels::get_label( 'liked', false ) : Labels::get_label( 'like', false );
									?>

									<button class="sd-comment-like-reaction portal-button button-ghost sd-force-p-0" data-entity="comment" data-comment_id="<?php echo esc_attr( (string) $comment_id ); ?>">
										<?php echo esc_html( $like_text ); ?>
									</button>

									<?php
									if ( $depth < 5 ) {
										$reply_link = comment_reply_link(
											array_merge(
												$args,
												[
													'reply_text' => __( 'Reply', 'suredash' ),
													'add_below' => 'comment',
													'depth'  => $depth,
													'before' => '<span class="sd-comment-like-reaction ' . esc_attr( $reply_link_class ) . '">',
													'after'  => '</span>',
												]
											)
										);

										if ( $reply_link !== null ) {
											echo wp_kses_post( $reply_link );
										}
									}

									$user_list = suredash_get_thread_liked_users( $comment_id );

									$comment_likes = (string) $user_list['thread_likes'];
									echo '<span
									class="tooltip-trigger sd-comment-like-count sd-flex sd-p-0 sd-font-14 sd-transition-fast sd-color-custom sd-font-semibold sd-gap-4 sd-pointer"
									data-tooltip-description="' . esc_attr( (string) $user_list['tooltip_content'] ) . '"
									data-count="' . esc_attr( $comment_likes ) . '"> <span class="counter">' . esc_html( $comment_likes ) . '</span>';
									Helper::get_library_icon( 'Heart', true );
									echo '</span>';
									?>
								</div>
							</section>
						</div>

				</article>

				<?php if ( is_array( $child_comments ) && ! empty( $child_comments ) ) { ?>
					<div class="portal-replies-wrapper">
						<div class="portal-view-replies-btn sd-border-none" data-comment-id="<?php echo esc_attr( (string) $comment_id ); ?>">

							<?php
								// Check if there are child comments.

								// Get the latest child comment.
								$latest_child_comment = end( $child_comments );

								// Skip if the child comment is not an object.
							if ( is_object( $latest_child_comment ) ) {
								// Get the name of the latest child commenter.
								$latest_child_commenter_name = $latest_child_comment->comment_author;

								echo '<span class="latest-replier-avatar">';
								suredash_get_user_avatar( absint( $latest_child_comment->user_id ), true, 24 );
								echo '</span>';

								// Display the name of the latest child commenter.
								echo '<span class="latest-replier">' . esc_html( $latest_child_commenter_name ) . ' ' . esc_html( Labels::get_label( 'replied' ) ) . '</span>';
							}
							?>
							<span>
								·
							</span>
							<span class="replies-count">
								<?php echo esc_html( (string) count( $child_comments ) ) . ' ' . esc_html( Labels::get_label( 'replies' ) ); ?>
							</span>
							</div>
						<ol class="children comment-replies" style="display: none;">
							<?php
							foreach ( $child_comments as $child_comment ) {
								// Skip if the child comment is not an object.
								if ( ! is_object( $child_comment ) ) {
									continue;
								}
								// Save current comment.
								$tmp_comment = $GLOBALS['comment'];
								// Call recursively with the child comment.
								suredash_comments_list_callback( $child_comment, $args, $depth + 1 );
								// Restore the previous comment context.
								$GLOBALS['comment'] = $tmp_comment; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
							}
							?>
						</ol>
					</div>
				<?php } ?>
				</div><!-- .portal-thread-content -->
			</li>
			<?php
			break;
	}
}

/**
 * Get the all liked users as per thread.
 *
 * @param int    $thread_id The thread ID.
 * @param string $thread_type The thread type, Post|Comment.
 *
 * @return array<string, string|int>
 */
function suredash_get_thread_liked_users( $thread_id, $thread_type = 'comment' ) {
	if ( $thread_type === 'comment' ) {
		$likes = get_comment_meta( $thread_id, 'portal_comment_likes', true );
	} else {
		$likes = get_post_meta( $thread_id, 'portal_post_likes', true );
	}

	$likes       = ! empty( $likes ) ? $likes : [];
	$likes_users = array_values( $likes );

	$likes_count     = 0;
	$tooltip_content = '';

	if ( is_array( $likes_users ) && ! empty( $likes_users ) ) {
		$users             = [];
		$max_users_to_show = apply_filters( 'suredash_max_number_of_thread_likers', 5 );

		$total_likes = count( $likes_users );
		$loop_limit  = $total_likes > $max_users_to_show ? $max_users_to_show : $total_likes; // Precompute the loop limit.
		// Iterate only up to some threshold.
		for ( $i = 0; $i < $loop_limit; $i++ ) {
			$user_id = $likes_users[ $i ];
			$user    = get_user_by( 'id', $user_id );
			if ( $user && ! empty( $user->display_name ) ) {
				$users[] = suredash_get_user_display_name( $user_id );
			}
		}

		// Calculate the remaining count of users who liked the thread.
		$remaining_count = count( $likes_users ) - $max_users_to_show;

		// Prepare tooltip content.
		$tooltip_content = implode( "\n", $users );
		if ( $remaining_count > 0 ) {
			$tooltip_content .= ' and ' . $remaining_count . ' more.';
		}

		// Total likes count.
		$likes_count = (string) count( $likes_users );
	}

	return [
		'tooltip_content' => $tooltip_content,
		'thread_likes'    => $likes_count,
	];
}

/**
 * Get all bookmarked items.
 *
 * @return array<mixed>
 * @since 0.0.1
 */
function suredash_get_all_bookmarked_items() {
	$user_id          = get_current_user_id();
	$bookmarked_items = sd_get_user_meta( $user_id, 'portal_bookmarked_items', true );
	$bookmarked_items = ! empty( $bookmarked_items ) ? $bookmarked_items : [];

	return apply_filters( 'suredashboard_bookmarked_items', $bookmarked_items, $user_id );
}

/**
 * Check if passed item is bookmarked or not.
 *
 * @param int $item_id Item ID.
 * @return bool
 * @since 0.0.1
 */
function suredash_is_item_bookmarked( $item_id ) {
	$bookmarked_items = suredash_get_all_bookmarked_items();
	return isset( $bookmarked_items[ $item_id ] ) ? true : false;
}

/**
 * Get endpoint indicator.
 *
 * @param string $endpoint Endpoint.
 * @param int    $base_id Flag.
 * @param int    $item_id Item ID.
 * @return string
 * @since 0.0.1
 */
function suredash_get_endpoint_indicator( $endpoint, $base_id, $item_id ) {
	$endpoint_indicator = '';

	switch ( $endpoint ) {
		case 'lesson':
			$all_completed_lessons = is_callable( 'suredash_get_all_completed_lessons' ) ? suredash_get_all_completed_lessons( $base_id ) : [];
			$endpoint_indicator    = in_array( $item_id, $all_completed_lessons, true ) ? 'completed' : '';
			break;

		default:
			break;
	}

	return $endpoint_indicator;
}

/**
 * Get unread posts count for a space.
 *
 * @param int $user_id User ID. If empty, uses current user.
 * @param int $space_id Space ID (forum term_id).
 * @return int Number of unread posts.
 * @since 1.6.0
 */
function suredash_get_space_unread_count( $user_id = 0, $space_id = 0 ) {
	if ( empty( $user_id ) ) {
		$user_id = get_current_user_id();
	}

	if ( empty( $user_id ) || empty( $space_id ) ) {
		return 0;
	}

	$tracker = \SureDashboard\Inc\Utils\Activity_Tracker::get_instance();
	return $tracker->get_unread_posts_count( $user_id, $space_id );
}

/**
 * Checks whether the post content have internal block or have shortcode.
 *
 * @param string $tag Shortcode tag to check.
 * @return bool
 */
function suredash_check_block_presence( $tag = '' ) {
	global $post;

	if ( ! $post ) {
		return false;
	}

	$presence = false;

	if ( is_singular() && is_a( $post, 'WP_Post' ) ) {
		$presence = has_block( $tag, $post->post_content ) || has_shortcode( $post->post_content, $tag );
	}

	return apply_filters( 'suredashboard_check_block_presence', $presence, $tag );
}

/**
 * Check whether the active theme is Astra.
 *
 * @return bool
 * @since 0.0.1
 */
function suredash_is_on_astra_theme() {
	return defined( 'ASTRA_THEME_VERSION' ) && is_callable( 'astra_get_option' );
}

/**
 * Get Lesson Oriented Data.
 *
 * Data like: all_lessons_count, next_lesson_id, previous_lesson_id.
 *
 * @param int          $current_lesson_id Current Lesson ID.
 * @param array<mixed> $course_loop Course Loop.
 * @since 1.0.0
 * @return array<mixed> $lesson_data
 */
function suredash_get_lesson_oriented_data( $current_lesson_id, $course_loop ) {
	$previous_lesson_id = 0;
	$next_lesson_id     = 0;
	$lessons_dataset    = [];

	foreach ( $course_loop as $section ) {
		if ( ! empty( $section['section_medias'] ) ) {
			foreach ( $section['section_medias'] as $media ) {
				$lesson_id = absint( ! empty( $media['value'] ) ? $media['value'] : 0 );
				// Only include published lessons.
				if ( $lesson_id && get_post_status( $lesson_id ) === 'publish' ) {
					$lessons_dataset[] = $media;
				}
			}
		}
	}

	if ( ! empty( $lessons_dataset ) ) {
		foreach ( $lessons_dataset as $index => $lesson ) {
			if ( isset( $lesson['value'] ) && absint( $lesson['value'] ) === $current_lesson_id ) {
				// Get previous lesson ID.
				$previous_lesson_id = absint( ! empty( $lessons_dataset[ $index - 1 ]['value'] ) ? $lessons_dataset[ $index - 1 ]['value'] : 0 );

				// Get next lesson ID.
				$next_lesson_id = absint( ! empty( $lessons_dataset[ $index + 1 ]['value'] ) ? $lessons_dataset[ $index + 1 ]['value'] : 0 );
			}
		}
	}

		return [
			'all_lessons_count'  => count( $lessons_dataset ),
			'previous_lesson_id' => $previous_lesson_id,
			'next_lesson_id'     => $next_lesson_id,
		];
}

/**
 * Get all lesson IDs from the course loop.
 *
 * @param array<mixed> $course_loop Course loop.
 * @return array<int> Lesson IDs.
 */
function suredash_get_lesson_ids_from_course_loop( $course_loop ) {
		$lesson_ids = [];

	foreach ( $course_loop as $section ) {
		if ( empty( $section['section_medias'] ) || ! is_array( $section['section_medias'] ) ) {
			continue;
		}

		foreach ( $section['section_medias'] as $lesson ) {
			if ( isset( $lesson['value'] ) ) {
				$lesson_id = absint( $lesson['value'] );
				// Only include published lessons.
				if ( $lesson_id && get_post_status( $lesson_id ) === 'publish' ) {
					$lesson_ids[] = $lesson_id;
				}
			}
		}
	}

		return $lesson_ids;
}

/**
 * Grant capabilities to the user based on the selected roles.
 *
 * @param int $user_id The user ID.
 * @return void
 * @since 0.0.1
 */
function suredash_grant_capabilities_to_user( $user_id ): void {
	// Get the roles selected in the admin panel.
	$selected_roles = Helper::get_option( 'user_capability' );

	// Bail if no roles are selected.
	if ( ! is_array( $selected_roles ) || empty( $selected_roles ) ) {
		return;
	}

	// Get the user object.
	$user = new \WP_User( $user_id );

	// Iterate over the selected roles.
	foreach ( $selected_roles as $role => $role_data ) {
		$role_slug = $role_data['id'] ?? 0;

		// Get the role object and its capabilities.
		$role = get_role( $role_slug );

		if ( ! empty( $role ) && is_array( $role->capabilities ) ) {
			// Grant each capability to the user.
			foreach ( $role->capabilities as $cap => $grant ) {
				if ( $grant ) {
					$user->add_cap( $cap );
				}
			}
		}
	}
}

/**
 * Get the timestamp .
 *
 * @return int
 * @since 0.0.1
 */
function suredash_get_timestamp() {
	return current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
}

/**
 * Check if the user is a manager.
 *
 * @param int $user_id The user ID.
 * @return bool
 * @since 0.0.1
 */
function suredash_is_user_manager( $user_id = 0 ) {
	$status = false;
	if ( ! is_user_logged_in() ) {
		return $status;
	}

	if ( $user_id ) {
		$current_user_id = absint( $user_id );
	} else {
		$current_user_id = get_current_user_id();
	}

	return user_can( $current_user_id, SUREDASHBOARD_CAPABILITY );
}

/**
 * Check if the post is restricted.
 *
 * @param int $post_id Post ID.
 *
 * @since 1.0.0
 * @return array<string, mixed>
 */
function suredash_get_post_backend_restriction( int $post_id ): array {
	$status_data = [
		'status'      => false,
		'redirection' => '',
		'title'       => '',
	];

	if ( is_admin() ) {
		return apply_filters(
			'suredash_post_backend_restriction_details',
			$status_data,
			$post_id
		);
	}

	// If current user is manager, then consider the post is for admin's view.
	if ( suredash_is_user_manager() ) {
		return $status_data;
	}

	return apply_filters(
		'suredash_post_backend_restriction_details',
		$status_data,
		$post_id
	);
}

/**
 * Get the feeds page URL.
 *
 * @return string
 * @since 1.0.0
 */
function suredash_get_feed_page_url() {
	$url = home_url( '/' . suredash_get_community_slug() . '/feeds/' );

	/**
	 * Filter the URL for the feeds page.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url Feeds page URL.
	 */
	return apply_filters( 'suredash_feed_page_url', $url );
}

/**
 * Get default restriction values.
 *
 * @return array<string, mixed>
 * @since 1.0.0
 */
function suredash_restriction_defaults() {
	return apply_filters(
		'suredashboard_restriction_defaults',
		[
			'status'  => false,
			'content' => '',
		]
	);
}

/**
 * Check if the post is private.
 *
 * @param int  $post_id The post ID.
 * @param bool $get_only_status Flag to get only status.
 * @return bool
 * @since 1.0.0
 */
function suredash_is_post_protected( $post_id, $get_only_status = true ) {
	$restriction  = suredash_restriction_defaults();
	$is_protected = $restriction['status'] ?? false;

	if ( suredash_is_user_manager() ) {
		return $is_protected;
	}

	// Check if the post is restricted.
	$restriction  = Helper::maybe_third_party_restricted( $post_id );
	$is_protected = $restriction['status'] ?? false;
	if ( $is_protected ) {
		return apply_filters( 'suredash_post_protection', $get_only_status ? $is_protected : $restriction, $post_id );
	}

	// Check if the post's space is restricted.
	$post_type = strval( sd_get_post_field( $post_id, 'post_type' ) );
	if ( $post_type === SUREDASHBOARD_FEED_POST_TYPE ) {
		$space_id = absint( sd_get_space_id_by_post( $post_id ) );
		if ( $space_id ) {
			$restriction  = Helper::maybe_third_party_restricted( $space_id );
			$is_protected = $restriction['status'] ?? false;
			if ( $is_protected ) {
				return apply_filters( 'suredash_post_protection', $get_only_status ? $is_protected : $restriction, $post_id );
			}
		}
	}

	// Check if the post is private in the pro version.
	if ( function_exists( 'suredash_pro_is_post_protected' ) ) {
		$restriction  = suredash_pro_is_post_protected( $post_id );
		$is_protected = $restriction['status'] ?? false;
		if ( $is_protected ) {
			return apply_filters( 'suredash_post_protection', $get_only_status ? $is_protected : $restriction, $post_id );
		}
	}

	// Checking visibility scope for the post.
	$visible_to_user = Helper::is_post_visible_to_user(
		[
			'post_id' => $post_id,
		]
	);
	if ( ! $visible_to_user ) {
		$is_protected = true;
		return apply_filters( 'suredash_post_protection', $get_only_status ? $is_protected : $restriction, $post_id );
	}

	// Fallback to the default behavior.
	return apply_filters( 'suredash_post_protection', $get_only_status ? $is_protected : $restriction, $post_id );
}

/**
 * Get premium course integration content.
 *
 * @param int $post_id Post ID.
 * @return string
 * @since 0.0.1
 */
function suredash_course_integration_content( $post_id ) {
	$content = '';

	if ( function_exists( 'suredash_pro_course_integration_content' ) ) {
		$content = suredash_pro_course_integration_content( $post_id );
	}

	return $content;
}

/**
 * Get premium course's lesson view content.
 *
 * @param string       $endpoint      Endpoint.
 * @param array<mixed> $endpoint_data Endpoint data.
 * @param array<mixed> $atts          Attributes.
 * @return void
 * @since 0.0.1
 */
function suredash_lesson_view_content( $endpoint, $endpoint_data, $atts = [] ): void {
	if ( function_exists( 'suredash_pro_lesson_view_content' ) ) {
		suredash_pro_lesson_view_content( $endpoint, $endpoint_data, $atts );
	}
}

/**
 * Display event view content.
 *
 * @param string       $endpoint      Endpoint.
 * @param array<mixed> $endpoint_data Endpoint data.
 * @param array<mixed> $atts          Attributes.
 * @return void
 * @since 1.4.0
 */
function suredash_event_view_content( $endpoint, $endpoint_data, $atts = [] ): void {
	if ( function_exists( 'suredash_pro_event_view_content' ) ) {
		suredash_pro_event_view_content( $endpoint, $endpoint_data, $atts );
	} else {
		// Fallback for free version - display basic event content.
		echo do_shortcode( '[portal_single_content skip_header="true"]' );
	}
}

/**
 * Get premium course's progress bar.
 *
 * @param array<mixed> $lesson_data   Lesson data.
 * @param array<mixed> $lessons_completed The lessons completed.
 * @return string HTML markup for the progress bar.
 * @since 0.0.6
 */
function suredash_get_course_progress_bar( $lesson_data, $lessons_completed ) {
	if ( function_exists( 'suredash_pro_get_course_progress' ) ) {
		return suredash_pro_get_course_progress( $lesson_data, $lessons_completed );
	}

	return '';
}

/**
 * Get portal user's profile link.
 *
 * @param int $user_id User ID.
 * @return string
 */
function suredash_get_user_view_link( $user_id ) {
	return home_url( '/' . suredash_get_community_slug() . '/user-view/' . $user_id . '/' );
}

/**
 * Return the caller detail for showcasing in notification.
 *
 * @param int $caller Caller ID.
 * @return string
 */
function suredash_get_notifier_caller( $caller ) {
	if ( empty( $caller ) ) {
		return __( 'Unknown', 'suredash' );
	}

	$caller_name = suredash_get_user_display_name( $caller );
	$user_view   = suredash_get_user_view_link( $caller );

	return '<a href="' . esc_url( $user_view ) . '"><strong>' . esc_html( $caller_name ) . '</strong></a>';
}

/**
 * Check https status.
 *
 * @since  0.0.6
 * @return bool
 */
function suredash_site_is_https() {
	return strstr( get_option( 'home' ), 'https:' ) !== false;
}

/**
 * Get the referer post if available.
 *
 * @return array<mixed>
 * @since 0.0.5
 */
function suredash_get_referer_post() {
	if ( isset( $_SERVER['REQUEST_URI'] ) ) {
		$referer    = esc_url_raw( $_SERVER['REQUEST_URI'] );
		$parsed_url = wp_parse_url( $referer );

		// Strip the slug and extract the post ID.
		if ( isset( $parsed_url['path'] ) ) {
			$path     = trim( $parsed_url['path'], '/' );
			$segments = explode( '/', $path );
			$slug     = end( $segments );

			// Fetch post dynamically using sd_query().
			$post = sd_query()
				->select( 'ID, post_type, post_title' )
				->from( 'posts' )
				->where( 'post_name', $slug )
				->where( 'post_status', 'publish' )
				->limit( 1 )
				->get( ARRAY_A );

			return $post[0] ?? [];
		}
	}

	return [];
}

/**
 * Get the login page URL.
 *
 * @return string
 * @since 0.0.4
 */
function suredash_get_login_page_url() {
	$login_page    = Helper::get_option( 'login_page' );
	$login_page_id = is_array( $login_page ) && ! empty( $login_page['value'] ) ? $login_page['value'] : 0;

	return apply_filters( 'suredashboard_login_redirection', $login_page_id ? get_permalink( $login_page_id ) : wp_login_url() );
}

/**
 * Get the ORM query instance.
 *
 * @return Query
 */
function sd_query() {
	return Query::init(); // @phpstan-ignore-line
}

/**
 * Get the Router instance.
 *
 * @return Router
 */
function sd_route() {
	return Router::get_instance();
}

/**
 * Returns the markup for the current template.
 *
 * @access private
 * @since 0.0.6
 *
 * @param  string $template_content The template content.
 * @global string   $_wp_current_template_content
 * @global WP_Embed $wp_embed
 *
 * @return string Block template markup.
 */
function suredash_get_the_block_template_html( $template_content ) {
	global $wp_embed;

	if ( ! $template_content ) {
		return is_user_logged_in() ? '<h1>' . esc_html__( 'No matching template found.', 'suredash' ) . '</h1>' : '';
	}

	$content = $wp_embed->run_shortcode( $template_content );
	$content = $wp_embed->autoembed( $content );
	$content = do_blocks( $content );
	$content = wptexturize( $content );
	$content = convert_smilies( $content );
	$content = shortcode_unautop( $content );
	$content = wp_filter_content_tags( $content, 'template' );
	$content = do_shortcode( $content );
	$content = str_replace( ']]>', ']]&gt;', $content );

	// Wrap block template in .wp-site-blocks to allow for specific descendant styles.
	// (e.g. `.wp-site-blocks > *`).
	return '<div class="wp-site-blocks portal-container">' . $content . '</div>';
}

/**
 * Get the portal_menu ID.
 *
 * @return int
 * @since 0.0.6
 */
function suredash_get_portal_menu_id() {
	if ( ! has_nav_menu( 'portal_menu' ) ) {
		return 1;
	}

	$menu_locations = get_nav_menu_locations();
	return $menu_locations['portal_menu'] ?? 1;
}

/**
 * Make content dynamic.
 *
 * Use cases:
 * {site_url} => Site URL.
 * {portal_slug} => suredash_get_community_slug().
 * %7Bportal_slug%7D => suredash_get_community_slug().
 *
 * @param string $content content.
 * @since 1.0.0
 * @return string
 */
function suredash_dynamic_content_support( $content ) {
	$site_url       = esc_url( site_url() );
	$portal_slug    = suredash_get_community_slug();
	$user_id        = get_current_user_id();
	$user_view_link = 'user-view/' . $user_id;

	return str_replace(
		[ '{site_url}', '{portal_slug}', '%7Bportal_slug%7D', '{portal_view_profile}', '%7Bportal_view_profile%7D' ],
		[ $site_url, $portal_slug, $portal_slug, $user_view_link, $user_view_link ],
		$content
	);
}

/**
 * Get the community slug.
 *
 * This function is used to get the community slug for the SureDash portal.
 * It can be filtered using the 'suredash_portal_slug' filter.
 *
 * @return string The community slug.
 * @since 1.0.0
 */
function suredash_get_community_slug() {
	return apply_filters(
		'suredash_portal_slug',
		SUREDASHBOARD_SLUG
	);
}

/**
 * Detect the page builder used for a post.
 *
 * @param int $post_id Post ID.
 * @return string The page builder name or 'block-editor' if no page builder is detected.
 * @since 1.0.0
 */
function suredash_detect_page_builder( $post_id ) {
	$post = sd_get_post( $post_id );

	// Check if Elementor is active.
	if ( class_exists( '\Elementor\Plugin' ) ) {
		$document = \Elementor\Plugin::$instance->documents->get( $post_id ); // phpcs:ignore PHPCompatibility.LanguageConstructs.NewLanguageConstructs.t_ns_separatorFound
		if ( $document ) {
			$deprecated_handle = $document->is_built_with_elementor();
		} else {
			$deprecated_handle = false;
		}
		if ( ( defined( 'ELEMENTOR_VERSION' ) && version_compare( ELEMENTOR_VERSION, '1.5.0', '<' ) && \Elementor\Plugin::$instance->db->get_edit_mode( $post_id ) === 'builder' ) || $deprecated_handle ) { // phpcs:ignore PHPCompatibility.LanguageConstructs.NewLanguageConstructs.t_ns_separatorFound
			return 'elementor';
		}
	}

	// Check if Thrive is active.
	if ( defined( 'TVE_VERSION' ) && sd_get_post_meta( $post_id, 'tcb_editor_enabled', true ) ) {
		return 'thrive';
	}

	// Check if Beaver Builder is active.
	if ( class_exists( 'FLBuilderModel' ) && apply_filters( 'fl_builder_do_render_content', true, \FLBuilderModel::get_post_id() ) && sd_get_post_meta( $post_id, '_fl_builder_enabled', true ) ) {
		return 'beaver-builder';
	}

	// Check if WP-Bakery is active.
	$vc_active = sd_get_post_meta( $post_id, '_wpb_vc_js_status', true );
	if ( class_exists( 'Vc_Manager' ) && ( $vc_active === 'true' || has_shortcode( $post->post_content ?? '', 'vc_row' ) ) ) {
		return 'wpbakery';
	}

	// Check if Divi is active.
	if ( function_exists( 'et_pb_is_pagebuilder_used' ) && \et_pb_is_pagebuilder_used( $post_id ) ) {
		return 'divi';
	}

	// Check if Brizy is active.
	if ( class_exists( 'Brizy_Editor_Post' ) && class_exists( 'Brizy_Editor' ) ) {
		$brizy_post_types = \Brizy_Editor::get()->supported_post_types();
		$post_type        = $post->post_type ?? '';

		if ( in_array( $post_type, $brizy_post_types, true ) ) {
			if ( \Brizy_Editor_Post::get( $post_id )->uses_editor() ) {
				return 'brizy';
			}
		}
	}

	// Check if Bricks is active.
	if ( defined( 'BRICKS_DB_PAGE_CONTENT' ) && class_exists( '\Bricks\Helpers' ) ) {
		$bricks_data = get_post_meta( $post_id, BRICKS_DB_PAGE_CONTENT, true );
		$editor_mode = \Bricks\Helpers::get_editor_mode( $post_id );
		if ( $editor_mode === 'bricks' && $bricks_data ) {
			return 'bricks';
		}
	}

	// Check if Breakdance is active.
	if ( function_exists( '\Breakdance\Data\get_tree' ) ) {
		$breakdance_data = sd_get_post_meta( $post_id, '_breakdance_data', true );
		if ( $breakdance_data && \Breakdance\Data\get_tree( $post_id ) ) {
			return 'breakdance';
		}
	}

	// Check Spectra compatibility.
	$spectra_content = get_post_field( 'post_content', $post_id );
	if ( defined( 'UAGB_VER' ) && class_exists( 'UAGB_Post_Assets' ) && is_string( $spectra_content ) && strpos( $spectra_content, '<!-- wp:uagb/' ) !== false ) {
		return 'spectra';
	}

	// Fallback to block editor.
	return 'block-editor';
}

/**
 * Get the excerpt of a post.
 *
 * @param int $post_id Post ID.
 * @param int $length Length of the excerpt (word count).
 * @return string The excerpt of the post.
 * @since 1.0.0
 */
function suredash_get_excerpt( $post_id, $length = 20 ) {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return '';
	}

	$length           = Helper::suredash_excerpt_length( $length, $post_id );
	$preserve_html    = Helper::get_option( 'preserve_excerpt_html' );
	$is_suredash_post = sd_get_post_field( $post_id, 'post_type' ) === SUREDASHBOARD_FEED_POST_TYPE;

	// Get content and remove shortcodes.
	$text = strip_shortcodes( $post->post_content );

	// Apply the_content filter to process blocks (e.g., convert block markup to HTML).
	$text = apply_filters( 'the_content', $text );

	// Handle HTML based on preserve setting.
	if ( $preserve_html && $is_suredash_post ) {
		// Remove inline style tags that some block plugins (like Kadence) add.
		$text = preg_replace_callback(
			'/<style[^>]*>.*?<\/style>/is',
			static function() {
				return '';
			},
			$text
		);
		$text = preg_replace_callback(
			'/<script[^>]*>.*?<\/script>/is',
			static function() {
				return '';
			},
			$text
		);
		$text = is_string( $text ) ? $text : '';
		$text = suredash_excerpt_with_html( $text, $length );
	} else {
		// Strip all HTML and return plain text excerpt.
		$text = wp_trim_words( wp_strip_all_tags( $text ), $length, '' );
	}

	return apply_filters( 'suredash_get_excerpt', $text, $post_id );
}

/**
 * Create an excerpt with HTML tags preserved (similar to Advanced Excerpt plugin).
 *
 * This function doesn't add ellipsis - that's handled by the template to keep
 * the "Read More" link on the same line.
 *
 * @param string $text The text to excerpt.
 * @param int    $length The word count for the excerpt.
 * @return string The excerpted text with HTML preserved.
 * @since 1.5.0
 */
function suredash_excerpt_with_html( $text, $length ) {
	$tokens = [];
	$out    = '';
	$w      = 0;

	// Divide the string into tokens; HTML tags, or words, followed by any whitespace.
	preg_match_all( '/(<[^>]+>|[^<>\s]+)\s*/u', $text, $tokens );

	foreach ( $tokens[0] as $t ) {
		// If we've reached the word limit, stop.
		if ( $w >= $length ) {
			break;
		}

		if ( $t[0] !== '<' ) {
			// Token is not a tag - it's a word.
			$w++;
		}

		// Append the token to output.
		$out .= $t;
	}

	// Balance HTML tags to ensure valid markup.
	$out = force_balance_tags( $out );

	// Return trimmed output without ellipsis.
	// The template will add ellipsis and "Read More" link inline.
	return trim( $out );
}

/**
 * Get user profile fields.
 *
 * @return array<string, array<string, mixed>>
 * @since 1.0.0
 */
function suredash_get_user_profile_fields() {
	$user_id = get_current_user_id();
	$user    = get_userdata( $user_id );

	$display_name = suredash_get_user_display_name( $user_id );
	$description  = ! empty( $user->description ) ? $user->description : '';
	$headline     = sd_get_user_meta( $user_id, 'headline', true );
	$user_website = ! empty( $user->user_url ) ? $user->user_url : '';

	return apply_filters(
		'suredash_user_profile_fields',
		[
			'display_name' => [
				'label'       => Labels::get_label( 'display_name' ),
				'placeholder' => __( 'Enter', 'suredash' ) . ' ' . Labels::get_label( 'display_name' ),
				'value'       => ! empty( $display_name ) ? $display_name : '',
				'type'        => 'input',
			],
			'user_url'     => [
				'label'       => Labels::get_label( 'website' ),
				'placeholder' => __( 'Enter', 'suredash' ) . ' ' . Labels::get_label( 'website' ),
				'value'       => ! empty( $user_website ) ? $user_website : '',
				'type'        => 'input',
			],
			'headline'     => [
				'label'       => Labels::get_label( 'headline' ),
				'placeholder' => __( 'Enter', 'suredash' ) . ' ' . Labels::get_label( 'headline' ),
				'value'       => ! empty( $headline ) ? $headline : '',
				'type'        => 'input',
				'external'    => true,
			],
			'description'  => [
				'label'       => Labels::get_label( 'bio' ),
				'placeholder' => __( 'Write a short bio about yourself...', 'suredash' ),
				'value'       => ! empty( $description ) ? $description : '',
				'type'        => 'textarea',
			],
		]
	);
}

/**
 * Check if the discussion area is private.
 *
 * @param int $space_id Space ID.
 * @since 1.0.0
 * @return bool
 */
function suredash_is_private_discussion_area( $space_id ) {
	if ( function_exists( 'suredash_pro_is_private_discussion_area' ) ) {
		return suredash_pro_is_private_discussion_area( $space_id );
	}

	return false;
}

/**
 * Get the post ID from the request or current post.
 *
 * @return int The post ID.
 * @since 1.0.1
 */
function suredash_get_post_id() {
	if ( absint( $_GET['post_id'] ?? 0 ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Handled with absint().
		$post_id = absint( $_GET['post_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Handled with absint().
	} else {
		$post_id = absint( get_the_ID() );
	}

	return $post_id;
}

/**
 * Check if the post is created using the block editor.
 *
 * @param int $post_id Post ID.
 * @return bool
 * @since 1.0.2
 */
function suredash_is_post_by_block_editor( $post_id = 0 ) {
	if ( ! $post_id ) {
		$post_id = suredash_get_post_id();
	}

	if ( is_singular( SUREDASHBOARD_POST_TYPE ) ) {
		$integration = sd_get_post_meta( $post_id, 'integration', true );
		if ( $integration === 'single_post' ) {
			$render_type = PostMeta::get_post_meta_value( $post_id, 'post_render_type' );
			if ( $render_type === 'wordpress' ) {
				$remote_post_data = PostMeta::get_post_meta_value( $post_id, 'wp_post' );
				$post_id          = absint( is_array( $remote_post_data ) && ! empty( $remote_post_data['value'] ) ? $remote_post_data['value'] : 0 );
			}
		}
	}

	$page_builder = suredash_detect_page_builder( $post_id );
	return $page_builder === 'block-editor';
}

/**
 * Check if the space is hidden.
 *
 * @param int $space_id Space ID.
 * @return bool True if the space is hidden, false otherwise.
 * @since 1.1.0
 */
function suredash_is_space_hidden( $space_id ) {
	// Check if the function exists to avoid fatal errors.
	if ( function_exists( 'suredash_pro_is_space_hidden' ) ) {
		return suredash_pro_is_space_hidden( $space_id );
	}

	// If the space is not hidden, return false.
	return false;
}

/**
 * Get premium collection space integration content.
 *
 * @param int $post_id Post ID.
 * @return string
 * @since 1.2.0
 */
function suredash_collection_space_integration_content( $post_id ) {
	$content = '';

	if ( function_exists( 'suredash_pro_get_collection_space_content' ) ) {
		$content = suredash_pro_get_collection_space_content( $post_id );
	}

	return $content;
}

/**
 * Get premium event space integration content.
 *
 * @param int $post_id Post ID.
 * @return string
 * @since 1.4.0
 */
function suredash_event_space_integration_content( $post_id ) {
	$content = '';

	if ( function_exists( 'suredash_pro_get_event_space_content' ) ) {
		$content = suredash_pro_get_event_space_content( $post_id );
	}

	return $content;
}

/**
 * Get space description from various sources.
 *
 * @since 1.2.0
 * @param array<string,mixed> $space_post Space post data.
 * @return string Space description.
 */
function suredash_get_space_description( $space_post ): string {
	if ( ! isset( $space_post['ID'] ) ) {
		return '';
	}

	$space_id          = absint( $space_post['ID'] );
	$space_description = PostMeta::get_post_meta_value( $space_id, 'space_description' );

	// First, check for custom space description in metadata.
	if ( ! empty( $space_description ) && is_string( $space_description ) ) {
		return $space_description;
	}

	// Second, try to use the suredash_get_excerpt function.
	if ( function_exists( 'suredash_get_excerpt' ) ) {
		$excerpt = wp_strip_all_tags( suredash_get_excerpt( $space_id, 30 ) );
		if ( ! empty( $excerpt ) ) {
			return $excerpt;
		}
	}

	// Third, use post excerpt if available.
	if ( ! empty( $space_post['post_excerpt'] ) && is_string( $space_post['post_excerpt'] ) ) {
		return wp_trim_words( $space_post['post_excerpt'], 30, '...' );
	}

	// Finally, fallback to content if no excerpt or description.
	$content = isset( $space_post['post_content'] ) && is_string( $space_post['post_content'] ) ? $space_post['post_content'] : '';
	return wp_trim_words( strip_shortcodes( $content ), 30, '...' );
}

/**
 * Render post content with support for Presto Player blocks.
 *
 * This function processes the post content, checking for Presto Player blocks
 * and rendering them accordingly. If no Presto Player blocks are found, it
 * falls back to the default block rendering.
 *
 * @param string $post_content The post content to render.
 * @return string The rendered post content.
 * @since 1.2.0
 */
function suredash_render_post_content( $post_content ) {
	if ( has_blocks( $post_content ) ) {
		$output = '';
		$blocks = parse_blocks( $post_content );
		foreach ( $blocks as $block ) {
			// Process 'presto-player' blocks if found.
			if ( strpos( strval( $block['blockName'] ), 'presto-player' ) !== false ) {
				$output .= Plugin::render_presto_player_block( $block );
			} else {
				$output .= render_block( $block );
			}
		}
		// Output the processed blocks.
		return $output;
	}

	// For full content, use wpautop as before.
	return wpautop( $post_content );
}

/**
 * Get resource library view content.
 *
 * @param string       $endpoint      Endpoint.
 * @param array<mixed> $endpoint_data Endpoint data.
 * @param array<mixed> $atts          Attributes.
 * @return void
 * @since 1.3.0
 */
function suredash_resource_view_content( $endpoint, $endpoint_data, $atts = [] ): void {
	if ( function_exists( 'suredash_pro_resource_view_content' ) ) {
		suredash_pro_resource_view_content( $endpoint, $endpoint_data, $atts );
	} else {
		// Fallback to standard single content if pro is not available.
		echo do_shortcode( '[portal_single_content skip_header="false"]' );
	}
}

/**
 * Get the color palette names for the dashboard.
 *
 * @return array<string>
 * @since 1.3.0
 */
function suredash_get_color_palette_names() {
	return apply_filters(
		'suredash_color_palette_names',
		[
			__( 'Branding', 'suredash' ),
			__( 'Links', 'suredash' ),
			__( 'Headings', 'suredash' ),
			__( 'Text', 'suredash' ),
			__( 'Content Background', 'suredash' ),
			__( 'Portal Background', 'suredash' ),
			__( 'Alternate Background', 'suredash' ),
			__( 'Subtle Background', 'suredash' ),
			__( 'Other Supporting', 'suredash' ),
		]
	);
}

/**
 * Get the color palette for the dashboard.
 *
 * @return array<string, mixed>
 * @since 1.3.0
 */
function suredash_get_color_palette_defaults() {
	return apply_filters(
		'suredash_color_palette_defaults',
		[
			'light' => [
				'#2563EB',
				'#2563EB',
				'#191b1F',
				'#19283A',
				'#FFFFFF',
				'#F7F9FA',
				'#F1F3F5',
				'#E4E7EB',
				'#545861',
			],
			'dark'  => [
				'#FFFFFF',
				'#60A5FA',
				'#FFFFFF',
				'#E7F6FF',
				'#2A2E32',
				'#191B1F',
				'#42464D',
				'#42464D',
				'#E4E7EB',
			],
		]
	);
}

/**
 * Get the active color palette colors.
 *
 * @return array<string>
 * @since 1.3.0
 */
function suredash_get_active_palette_colors() {
	$palette_colors = [ '#046bd2', '#045cb4', '#1e293b', '#334155', '#F0F5FA', '#FFFFFF', '#D1D5DB', '#111111' ];

	// Astra theme compatibility.
	if ( suredash_is_on_astra_theme() ) {
		$theme_colors   = astra_get_option( 'global-color-palette' );
		$palette_colors = ! empty( $theme_colors['palette'] ) ? $theme_colors['palette'] : $palette_colors;
	}

	$global_palette = Helper::get_option( 'color_palette' );
	$active_palette = Helper::get_option( 'default_palette' );

	// Only proceed if active_palette is specifically 'light' or 'dark' (cookie compatibility) - Check for cookie state to determine actual active palette (same logic as view.php).
	if ( $active_palette === 'light' || $active_palette === 'dark' ) {
		$is_dark_mode   = suredash_is_dark_mode_active();
		$active_palette = $is_dark_mode ? 'dark' : 'light';

		// Override for color_mode query parameter.
		$color_query_mode = ! empty( $_GET['color_mode'] ) ? sanitize_text_field( $_GET['color_mode'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Color mode query parameter.
		if ( ! empty( $color_query_mode ) ) {
			$active_palette = $color_query_mode;
		}

		// Set light mode default if post editor is open.
		global $pagenow;
		if ( is_admin() ) {
			if ( $pagenow === 'post-new.php' || $pagenow === 'post.php' ) {
				$active_palette = 'light';
			}
		}

		// Only for site editor -- Light mode.
		if ( $pagenow === 'site-editor.php' ) {
			$active_palette = 'light';
		}

		// If no cookie, use the global setting as-is.
		$defaults       = suredash_get_color_palette_defaults();
		$palette_colors = ! empty( $global_palette[ $active_palette ] ) ? $global_palette[ $active_palette ] : $defaults[ $active_palette ];
		$palette_colors = array_map( 'sanitize_hex_color', $palette_colors );
	}

	return apply_filters( 'suredashboard_settings_color_preset', $palette_colors );
}

/**
 * Get the formatted color palette for the editor.
 *
 * @return array<string, string>
 * @since 1.3.0
 */
function suredash_editor_formatted_palette_colors(): array {
	$palette_colors = suredash_get_active_palette_colors();
	$color_names    = suredash_get_color_palette_names();

	$formatted_colors = [];

	foreach ( $palette_colors as $index => $color ) {
		$formatted_colors[] = [
			'id'    => 'suredash-color-' . $index,
			'name'  => $color_names[ $index ],
			'slug'  => 'portal-global-color-' . ( $index + 1 ),
			'color' => 'var(--portal-global-color-' . ( $index + 1 ) . ', ' . $color . ')',
		];
	}

	return apply_filters( 'suredash_editor_color_palette', $formatted_colors );
}

/**
 * Check if dark mode is active.
 *
 * @return bool
 * @since 1.3.0
 */
function suredash_is_dark_mode_active() {
	$is_dark_mode   = false;
	$active_palette = Helper::get_option( 'default_palette' );

	if ( $active_palette === 'light' || $active_palette === 'dark' ) {
		$is_dark_mode = $active_palette === 'dark';
		if ( ! empty( $_COOKIE['suredashColorSwitcherExists'] ) && sanitize_text_field( $_COOKIE['suredashColorSwitcherExists'] ) === 'true' ) {
			$cookie_state = isset( $_COOKIE['suredashColorSwitcherState'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['suredashColorSwitcherState'] ) ) : null;
			$is_dark_mode = $cookie_state !== null && $cookie_state === 'true' ? true : false;
		}
	}

	return $is_dark_mode;
}

/**
 * Format minutes to different time formats.
 *
 * This is a utility function that can be used by SureDash Pro and other extensions.
 *
 * @param int|string $minutes Duration in minutes.
 * @param string     $format  Format type: 'time' for HH:MM:SS, 'human' for human-readable string.
 * @return string Formatted duration string.
 * @since 1.3.0
 *
 * Examples:
 * suredash_format_minutes_to_time( 120 )         // Returns "02:00:00"
 * suredash_format_minutes_to_time( 120, 'time' ) // Returns "02:00:00"
 * suredash_format_minutes_to_time( 120, 'human' ) // Returns "2 hrs"
 * suredash_format_minutes_to_time( 150, 'human' ) // Returns "2 hrs 30 mins"
 * suredash_format_minutes_to_time( 45, 'human' )  // Returns "45 mins"
 */
function suredash_format_minutes_to_time( $minutes, $format = 'time' ) {
	if ( empty( $minutes ) || $minutes <= 0 ) {
		return $format === 'time' ? '00:00:00' : '0 mins';
	}

	$minutes = absint( $minutes );

	if ( $format === 'human' ) {
		if ( $minutes >= 60 ) {
			$hours             = floor( $minutes / 60 );
			$remaining_minutes = $minutes % 60;

			if ( $remaining_minutes > 0 ) {
				/* translators: %1$d: hours, %2$d: minutes */
				return sprintf( _n( '%1$d hr %2$d min', '%1$d hrs %2$d mins', (int) $hours, 'suredash' ), $hours, $remaining_minutes );
			}

			/* translators: %d: hours */
			return sprintf( _n( '%d hr', '%d hrs', (int) $hours, 'suredash' ), $hours );
		}

		/* translators: %d: minutes */
		return sprintf( _n( '%d min', '%d mins', (int) $minutes, 'suredash' ), $minutes );
	}

	// Default 'time' format HH:MM:SS.
	$hours             = floor( $minutes / 60 );
	$remaining_minutes = $minutes % 60;
	$seconds           = 0;

	return sprintf( '%02d:%02d:%02d', $hours, $remaining_minutes, $seconds );
}

/**
 * Get premium resource space integration content.
 *
 * @param int $post_id Post ID.
 * @return string
 * @since 1.3.0
 */
function suredash_resource_space_integration_content( $post_id ) {
	$content = '';

	if ( function_exists( 'suredash_pro_resource_space_integration_content' ) ) {
		$content = suredash_pro_resource_space_integration_content( $post_id );
	}

	return $content;
}

/**
 * As per the passed post ID get the post date & return a relative time.
 *
 * Example: 2 hours ago, 1 day ago, 1 week ago, 1 month ago, 1 year ago etc.
 *
 * @param int  $post_id Post ID.
 * @param bool $echo Whether to echo the output or return it. Default true (echo).
 * @param bool $show_discussion_space Whether to append discussion space name. Default false.
 * @return string Empty string when echoing, or the time difference string when not echoing.
 * @since 1.3.1
 */
function suredash_get_relative_time( $post_id, $echo = true, $show_discussion_space = false ): string {
	$post_date_gmt_field = sd_get_post_field( $post_id, 'post_date_gmt' );
	$post_date_field     = sd_get_post_field( $post_id, 'post_date' );

	// Use post_date_gmt for accurate timezone handling, fallback to post_date.
	$post_date_gmt = ! empty( $post_date_gmt_field ) && $post_date_gmt_field !== '0000-00-00 00:00:00'
		? $post_date_gmt_field
		: $post_date_field;

	// Convert to timestamp using WordPress's current timezone.
	$post_timestamp = absint( strtotime( get_date_from_gmt( $post_date_gmt ) ) );
	$current_time   = absint( suredash_get_timestamp() );

	// Calculate time difference.
	$time_diff_seconds = $current_time - $post_timestamp;

	// Handle very recent posts (less than 1 minute).
	if ( $time_diff_seconds < 60 ) {
		$time_diff = __( 'Just now', 'suredash' );
	} else {
		$time_diff = sprintf(
			/* translators: %s: Human-readable time difference (e.g., "5 days", "2 hours") */
			__( '%s ago', 'suredash' ),
			human_time_diff( $post_timestamp, $current_time )
		);
	}

	// Append discussion space name if requested.
	if ( $show_discussion_space ) {
		$post_type = sd_get_post_field( $post_id, 'post_type' );

		// For forum posts: use sd_get_space_id_by_post().
		if ( $post_type === SUREDASHBOARD_FEED_POST_TYPE ) { // 'community-post'
			$discussion_space_id = absint( sd_get_space_id_by_post( $post_id ) );
		} else {
			// For discussion space comments: use post_parent.
			$discussion_space_id = absint( sd_get_post_field( $post_id, 'post_parent' ) );
		}

		if ( $discussion_space_id > 0 ) {
			$discussion_space_name = get_the_title( (int) $discussion_space_id );
			if ( ! empty( $discussion_space_name ) ) {
				$time_diff .= ' ' . sprintf(
					/* translators: %s: Discussion space name */
					__( 'in %s', 'suredash' ),
					$discussion_space_name
				);
			}
		}
	}

	$title_time = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $post_timestamp );
	if ( $echo ) {
		?>
		<time datetime="<?php echo esc_attr( $post_timestamp ? gmdate( 'c', $post_timestamp ) : '' ); ?>" title="<?php echo esc_attr( strval( $title_time ) ); ?>" aria-label="<?php echo esc_attr( strval( $time_diff ) ); ?>" class="portal-store-post-publish-date sd-font-12 sd-line-16">
			<?php echo esc_html( strval( $time_diff ) ); ?>
		</time>
		<?php
		return '';
	}
	return strval( $time_diff );
}

/**
 * Check if a value has unit or not.
 *
 * Case: Block editor block setting values.
 *
 * @param string $value The value to check.
 *
 * @return string
 * @since 1.4.0
 */
function suredash_get_default_value_with_unit( $value ) {
	if ( ! preg_match( '/[a-zA-Z]+$/', strval( $value ) ) ) {
		$value .= 'px';
	}
	return $value;
}

/**
 * Generate user initials from name.
 *
 * @param int $user_id User ID.
 * @param int $space_id Space ID (optional).
 * @return array<string,string> Array with 'initials' and 'color' keys.
 * @since 1.4.0
 */
function suredash_get_user_initials( $user_id, $space_id = 0 ) {
	$user = get_user_by( 'ID', $user_id );

	if ( ! $user ) {
		if ( $space_id ) {
			$space_title        = get_the_title( $space_id );
			$space_name_initial = ! empty( $space_title ) ? $space_title[0] : 'S';
			return [
				'initials' => strtoupper( $space_name_initial ),
				'color'    => suredash_get_user_avatar_color( 1 ),
			];
		}

		return [
			'initials' => 'G',
			'color'    => suredash_get_user_avatar_color( 1 ),
		];
	}

	$display_name = suredash_get_user_display_name( $user_id );
	$first_name   = get_user_meta( $user_id, 'first_name', true );
	$last_name    = get_user_meta( $user_id, 'last_name', true );

	// Prioritize display name.
	if ( ! empty( $display_name ) ) {
		$name_count = mb_strlen( $display_name );
		$name_parts = explode( ' ', trim( $display_name ) );
		$name_parts = array_filter( $name_parts ); // Remove empty elements.

		if ( count( $name_parts ) > 1 ) {
			$initials = mb_substr( $name_parts[0], 0, 1 );
			if ( isset( $name_parts[1] ) ) {
				$initials .= mb_substr( $name_parts[1], 0, 1 );
			}
			if ( isset( $name_parts[2] ) ) {
				$initials .= mb_substr( $name_parts[2], 0, 1 );
			}
			return [
				'initials' => strtoupper( $initials ),
				'color'    => suredash_get_user_avatar_color( $name_count ),
			];
		}

		return [
			'initials' => strtoupper( mb_substr( $display_name, 0, 1 ) ),
			'color'    => suredash_get_user_avatar_color( $name_count ),
		];
	}

	// Fallback to first/last name if display name is empty or same as username.
	if ( ! empty( $first_name ) && ! empty( $last_name ) ) {
		$name_count = mb_strlen( $first_name ) + mb_strlen( $last_name );
		return [
			'initials' => strtoupper( mb_substr( $first_name, 0, 1 ) . mb_substr( $last_name, 0, 1 ) ),
			'color'    => suredash_get_user_avatar_color( $name_count ),
		];
	}

	if ( ! empty( $first_name ) ) {
		$name_count = mb_strlen( $first_name );
		return [
			'initials' => strtoupper( mb_substr( $first_name, 0, 1 ) ),
			'color'    => suredash_get_user_avatar_color( $name_count ),
		];
	}

	$name_count = mb_strlen( $user->user_login );
	return [
		'initials' => strtoupper( mb_substr( $user->user_login, 0, 1 ) ),
		'color'    => suredash_get_user_avatar_color( $name_count ),
	];
}

/**
 * Generate unique background color class for user avatar.
 *
 * @param int $string_count String character count for color generation.
 * @return string CSS background color class.
 * @since 1.4.0
 */
function suredash_get_user_avatar_color( $string_count ) {
	$color_classes = [
		'sd-bg-red-300',
		'sd-bg-orange-300',
		'sd-bg-yellow-300',
		'sd-bg-lime-300',
		'sd-bg-teal-300',
		'sd-bg-sky-300',
		'sd-bg-violet-300',
		'sd-bg-fuchsia-300',
	];

	$color_index = $string_count % count( $color_classes );
	return $color_classes[ $color_index ];
}

/**
 * Get the user's extra badges.
 *
 * @param int $user_id The user ID.
 * @param int $after_count The number of badges to show after the threshold. Default 0.
 *
 * @return string
 * @since 1.5.0
 */
function suredash_get_user_extra_badges( $user_id, $after_count = 0 ) {
	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}

	if ( ! $user_id ) {
		return '';
	}

	$consider      = [];
	$portal_badges = Helper::get_option( 'user_badges' );
	$user_badges   = sd_get_user_meta( $user_id, 'portal_badges', true );
	$names         = array_column( $portal_badges, 'name', 'id' );

	if ( ! empty( $portal_badges ) && ! empty( $user_badges ) ) {
		foreach ( $user_badges as $index => $badge ) {
			$badge_name = $badge['name'] ?? '';

			if ( ! in_array( $badge_name, $names ) ) {
				continue;
			}

			if ( $index < $after_count ) {
				continue;
			}

			$consider[] = $badge['name'];
		}
	}

	return ! empty( $consider ) ? implode( "\n", $consider ) : '';
}

/**
 * Generate a 5 digit unique ID.
 *
 * @return string
 * @since 1.5.0
 */
function suredash_get_unique_id() {
	return 'sd_' . substr( md5( wp_unique_id( 'sd_' ) ), 0, 7 );
}

/**
 * Get all end points.
 *
 * @return array<string>
 * @since 1.5.0
 */
function suredash_all_screen_types() {
	return apply_filters(
		'suredashboard_all_screens',
		[
			'notification',
		]
	);
}

/**
 * Check if current screen is view.
 *
 * Case: Separate notification, profile pages.
 *
 * @return bool
 * @since 1.5.0
 */
function suredash_screen() {
	$screen = get_query_var( 'screen_id' ) ? sanitize_text_field( get_query_var( 'screen_id' ) ) : '';

	if ( empty( $screen ) ) {
		return false;
	}

	$all_screens = suredash_all_screen_types();

	if ( ! in_array( $screen, $all_screens, true ) ) {
		return false;
	}

	return true;
}

/**
 * Check whether community accessible or not, based on certain conditions.
 *
 * @param mixed $strict_allowance_check Whether to check strictly or not.
 * @return bool
 * @since 1.5.0
 */
function suredash_community_accessible( $strict_allowance_check = null ) {
	if ( ! is_null( $strict_allowance_check ) ) {
		if ( $strict_allowance_check ) {
			return apply_filters( 'suredash_community_accessible', true );
		}

		return apply_filters( 'suredash_community_accessible', false );
	}

	if ( is_user_logged_in() ) {
		return apply_filters( 'suredash_community_accessible', true );
	}

	$private_community = Helper::get_option( 'hidden_community' );
	if ( $private_community ) {
		return apply_filters( 'suredash_community_accessible', false );
	}

	return apply_filters( 'suredash_community_accessible', true );
}

/**
 * Get user profile social links.
 *
 * @param int $user_id User Id.
 * @return array<string, array<string, string>>
 * @since 1.5.0
 */
function suredash_get_user_profile_social_links( $user_id = 0 ) {
	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}

	$user       = get_userdata( $user_id );
	$user_email = ! empty( $user->user_email ) ? $user->user_email : '';

	$social_links = sd_get_user_meta( $user_id, 'portal_social_links', true );

	return apply_filters(
		'suredash_user_profile_social_links',
		[
			'mail'      => [
				'label'       => 'E-mail',
				'value'       => $social_links['mail'] ?? $user_email,
				'placeholder' => 'username@example.com',
				'icon'        => 'Mail',
			],
			'facebook'  => [
				'label'       => 'Facebook',
				'value'       => $social_links['facebook'] ?? '',
				'placeholder' => 'https://facebook.com/username',
				'icon'        => 'Facebook',
			],
			'x'         => [
				'label'       => 'X',
				'value'       => $social_links['x'] ?? '',
				'placeholder' => 'https://x.com/username',
				'icon'        => 'X',
			],
			'instagram' => [
				'label'       => 'Instagram',
				'value'       => $social_links['instagram'] ?? '',
				'placeholder' => 'https://instagram.com/username',
				'icon'        => 'Instagram',
			],
			'linkedin'  => [
				'label'       => 'LinkedIn',
				'value'       => $social_links['linkedin'] ?? '',
				'placeholder' => 'https://linkedin.com/username',
				'icon'        => 'Linkedin',
			],
		],
		$social_links
	);
}

/**
 * Get portal user's display name.
 *
 * @param int $user_id User ID.
 * @return string
 * @since 1.5.2
 */
function suredash_get_user_display_name( $user_id = 0 ) {
	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}
	if ( ! $user_id ) {
		return __( 'Guest User', 'suredash' );
	}
	$user = get_user_by( 'ID', $user_id );
	return is_object( $user ) ? $user->display_name : __( 'Anonymous', 'suredash' );
}

/**
 * Get count of published spaces by integration type.
 *
 * @param string $integration_type The integration type to count.
 * @return int Number of published spaces with this integration type.
 * @since 1.5.4
 */
function suredash_get_space_count_by_integration( $integration_type ) {
	$args = [
		'post_type'      => SUREDASHBOARD_POST_TYPE,
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			[
				'key'   => 'integration',
				'value' => $integration_type,
			],
		],
	];

	$posts = get_posts( $args );

	return count( $posts );
}

/**
 * Recursively replace suredash/content block with core/post-content for classic themes.
 *
 * @param array<int|string, mixed> $blocks Array of parsed blocks.
 * @return array<int|string, mixed> Modified blocks.
 * @since 1.6.0
 */
function suredash_replace_content_with_post_content( $blocks ) {
	foreach ( $blocks as &$block ) {
		// Found the content block - replace it with a marker.
		if ( $block['blockName'] === 'suredash/content' ) {
			// Use special markers for opening and closing portal-main-content wrapper.
			$block = [
				'blockName'    => null,
				'attrs'        => [],
				'innerHTML'    => '<!--SUREDASH_MAIN_CONTENT_START--><!--SUREDASH_POST_CONTENT_PLACEHOLDER--><!--SUREDASH_MAIN_CONTENT_END-->',
				'innerContent' => [ '<!--SUREDASH_MAIN_CONTENT_START--><!--SUREDASH_POST_CONTENT_PLACEHOLDER--><!--SUREDASH_MAIN_CONTENT_END-->' ],
				'innerBlocks'  => [],
			];
		}

		// Process nested blocks recursively.
		if ( ! empty( $block['innerBlocks'] ) ) {
			$block['innerBlocks'] = suredash_replace_content_with_post_content( $block['innerBlocks'] );
		}
	}

	return $blocks;
}
