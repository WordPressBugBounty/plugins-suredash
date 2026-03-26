<?php
/**
 * PHP file to use when rendering the block type on the server to show on the front end.
 *
 * The following variables are exposed to the file:
 *     $attributes (array): The block attributes.
 *     $content (string): The block default content.
 *     $block (WP_Block): The block instance.
 *
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 * @package suredash
 * @since 0.0.6
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SureDashboard\Core\Assets;
use SureDashboard\Inc\Utils\Helper;
use SureDashboard\Inc\Utils\Labels;

$content   = '';
$sub_query = suredash_get_sub_queried_page();

switch ( true ) {
	case is_front_page() && Helper::get_option( 'portal_as_homepage' ):
		if ( suredash_is_sub_queried_page() ) {
			$content = do_shortcode( '[portal_home_content type="' . $sub_query . '"]' );
		} else {
			$content = do_shortcode( '[portal_home_content]' );
		}
		break;

	case suredash_is_sub_queried_page():
		$content = do_shortcode( '[portal_home_content type="' . $sub_query . '"]' );
		break;

	case is_singular( SUREDASHBOARD_SUB_CONTENT_POST_TYPE ):
		$endpoint = suredash_content_post();
		$content  = do_shortcode( '[portal_single_endpoint_content get_only_content="true" endpoint="' . $endpoint . '"]' );
		break;

	case suredash_portal():
		if ( is_singular( SUREDASHBOARD_POST_TYPE ) ) {
			$content = do_shortcode( '[portal_single_content skip_header="true"]' );
		} else {
			$content = do_shortcode( '[portal_home_content]' );
		}
		break;

	case is_tax( SUREDASHBOARD_FEED_TAXONOMY ):
	case is_post_type_archive( SUREDASHBOARD_FEED_POST_TYPE ):
		$content = do_shortcode( '[portal_archive_content skip_header="true"]' );
		break;

	case is_post_type_archive( SUREDASHBOARD_SUB_CONTENT_POST_TYPE ):
		$content = '';
		break;

	case is_singular( SUREDASHBOARD_FEED_POST_TYPE ):
		$content = do_shortcode( '[portal_single_content skip_header="true"]' );
		break;
}

if ( empty( $content ) ) {
	if ( ! is_user_logged_in() && suredash_is_sub_queried_page() ) {
		$template_args = [
			'404_heading'    => Labels::get_label( 'user_needs_login' ),
			'not_found_text' => '',
		];
	} else {
		$template_args = [
			'not_found_text' => Labels::get_label( 'notify_message_error_occurred' ),
		];
	}

	$content = suredash_get_template_part(
		'parts',
		'404',
		$template_args,
		true
	);
}

// Check if sidebar widgets should be displayed.
$space_id        = get_queried_object_id();
$sidebar_widgets = sd_get_post_meta( $space_id, 'space_sidebar_widgets', true );

// Check if space has widgets.
$has_space_widgets = ! empty( $sidebar_widgets ) && ! empty( $sidebar_widgets['widgets'] );

// Check if global sidebar is enabled (defaults to true if not set).
$global_sidebar_enabled = isset( $sidebar_widgets['global_sidebar_enabled'] )
	? (bool) $sidebar_widgets['global_sidebar_enabled']
	: true;

// Get global widgets from settings.
$global_widgets_configured = Helper::get_option( 'global_sidebar_widgets', [] );
$has_global_widgets        = ! empty( $global_widgets_configured ) && is_array( $global_widgets_configured );

// Show sidebar only on portal spaces (not user profile, bookmarks, home page, etc.).
$is_portal_space = is_singular( SUREDASHBOARD_POST_TYPE );
$is_home_page    = suredash_is_home() || ( is_front_page() && Helper::get_option( 'portal_as_homepage' ) );

// Check if space is restricted (SureMembers or other protection).
$is_protected = function_exists( 'suredash_is_post_protected' ) && suredash_is_post_protected( $space_id );

// Show sidebar if:
// 1. On a portal space (excludes user profile, bookmarks, lessons, events, resources), AND.
// 2. Not on home page, AND.
// 3. Space is not restricted/protected, AND.
// 4. (Space has widgets OR (Global sidebar is enabled AND global widgets are configured)).
$has_widgets = $is_portal_space && ! $is_home_page && ! $is_protected && ( $has_space_widgets || ( $global_sidebar_enabled && $has_global_widgets ) );

// Get space's integration type and layout settings.
$space_type = '';
$layout     = '';
if ( is_singular( SUREDASHBOARD_POST_TYPE ) ) {
	$space_type   = sd_get_post_meta( $space_id, 'integration', true );
	$layout       = sd_get_post_meta( $space_id, 'layout', true );
	$layout_style = Assets::get_space_layout_style( $space_type, $space_id );
} else {
	$layout_style = '';
}

// Get layout details using space-specific settings (no longer forcing full_width when widgets are present).
$layout_details = Helper::get_layout_details( $layout, $layout_style );
$layout_class   = $layout_details['layout'] ?? '';
$layout_class   = 'portal-layout-' . esc_attr( $layout_class );
$layout_class   = suredash_is_post_by_block_editor() ? $layout_class . ' portal-content' : $layout_class;

// For course spaces, keep full width even with sidebar.
$is_course_space = ( $space_type === 'course' );

// Add has-sidebar class when widgets are present.
$main_classes = $has_widgets ? $layout_class . ' has-sidebar' : $layout_class;

// Add course space class if needed.
if ( $is_course_space && $has_widgets ) {
	$main_classes .= ' portal-course-with-sidebar';
}

// Add single post space class if needed (only for unboxed style).
$layout_style = $layout_details['style'] ?? 'boxed';
if ( $space_type === 'single_post' && $has_widgets && $layout_style === 'unboxed' ) {
	$main_classes .= ' portal-single-post-with-sidebar';
}

?>
	<div id="portal-main-content" <?php echo do_shortcode( get_block_wrapper_attributes( [ 'class' => $main_classes ] ) ); ?>>
		<?php if ( $has_widgets ) { ?>
			<?php $sidebar_content = do_shortcode( '[portal_sidebar_widgets]' ); ?>
			<div class="portal-sidebar-layout">
				<div class="portal-sidebar-content">
					<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
				<?php echo $sidebar_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		<?php } else { ?>
			<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php } ?>
	</div>
<?php
