<?php
/**
 * The template for displaying archive portals view.
 *
 * This template can be overridden by copying it to yourTheme/suredashboard/comments.php.
 *
 * @see     https://developer.wordpress.org/themes/basics/template-hierarchy/
 * @package SureDash\Templates
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

use SureDashboard\Inc\Utils\Helper;

if ( ! is_user_logged_in() ) {
	return;
}

$current_post_id = absint( ! empty( $post_id ) ? $post_id : get_the_ID() );
$in_qv           = $in_qv ?? false;
$p_comments      = boolval( $comments ?? true );
$comments_wrap   = $in_qv ? 'portal-qv-reaction-wrapper' : '';
$comments_wrap   = $p_comments ? $comments_wrap : $comments_wrap . ' portal-comments-disabled';

if ( ! $current_post_id ) {
	return;
}

?>

<?php
if ( $in_qv ) {
	ob_start();
	Helper::render_post_reaction( $current_post_id, '', false );
	$reaction_html = (string) ob_get_clean();
	$has_reactions = ! empty( trim( $reaction_html ) );

	if ( ! $p_comments ) {
		// Only render wrapper if there's reaction content to show.
		if ( $has_reactions ) {
			?>
			<div id="portal-comment" class="portal-comments-wrapper portal-container portal-content <?php echo esc_attr( $comments_wrap ); ?>">
				<?php echo $reaction_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output from Helper::render_post_reaction is already escaped. ?>
			</div>
			<?php
		}
		return;
	}
	?>
	<div id="portal-comment" class="portal-comments-wrapper portal-container portal-content <?php echo esc_attr( $comments_wrap ); ?>">
		<?php
		if ( $has_reactions ) {
			echo $reaction_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output from Helper::render_post_reaction is already escaped.
		}
		?>
	<?php
} else {
	// Every non quick view caller of this template is a single item page: feed post,
	// lesson, event or resource. All of them show the full comment list, so the inline
	// 2-comment preview is turned off here. Feed cards keep that preview and render
	// through templates/single/post.php instead.
	ob_start();
	Helper::render_post_reaction( $current_post_id, '', true, false, false );
	$reaction_html = (string) ob_get_clean();
	$has_reactions = ! empty( trim( $reaction_html ) );

	// With no reactions and comments closed there is nothing to show, and the wrapper's
	// top border would render as a stray line under the content.
	if ( ! $has_reactions && ! comments_open( $current_post_id ) ) {
		return;
	}
	?>
	<div id="portal-comment" class="portal-comments-wrapper portal-container portal-content <?php echo esc_attr( $comments_wrap ); ?>">
		<?php
		if ( $has_reactions ) {
			echo $reaction_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output from Helper::render_post_reaction is already escaped.
		}
		?>
	<?php
}

do_action( 'suredashboard_single_comments_before' );
?>

	<div class="portal-comments-inner-wrap portal-content">
		<div class="portal-comments-area">
			<?php
			$comments_open = comments_open( $current_post_id );
			if ( $comments_open ) {
				suredash_comments_markup( $current_post_id, true, null, '', 'qv' );
			}
			?>
		</div>
	</div>
</div>
