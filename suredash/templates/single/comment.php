<?php
/**
 * The template for displaying archive portals view.
 *
 * This template can be overridden by copying it to yourTheme/suredashboard/single/comment.php.
 *
 * @see     https://developer.wordpress.org/themes/basics/template-hierarchy/
 * @package SureDash\Templates
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

use SureDashboard\Inc\Utils\Labels;

$default_args = [
	'author_id'       => 0,
	'post_id'         => 0,
	'post_title'      => '',
	'comment_content' => '',
];

if ( ! isset( $args ) ) {
	$args = [];
}

$args = wp_parse_args( $args, $default_args );

if ( ! $args['post_id'] || ! $args['author_id'] ) {
	return;
}

$author_id       = $args['author_id'];
$post_title      = $args['post_title'];
$p_id            = absint( $args['post_id'] );
$author_id       = absint( $author_id );
$user_exists     = ! empty( $author_id ) && get_userdata( $author_id );
$user_view       = $user_exists ? suredash_get_user_view_link( $author_id ) : '';
$comment_content = $args['comment_content'];

do_action( 'suredashboard_single_comment_template', $p_id );

?>
	<div id="portal-post-<?php echo esc_attr( (string) $p_id ); ?>" class="portal-store-list-post sd-p-container sd-box-shadow portal-content">
		<section class="portal-store-post-header">
			<div class="portal-store-post-author-data sd-items-start">
				<div class="portal-store-post-author-wrap sd-w-full sd-flex sd-items-start">
					<?php suredash_get_user_avatar( $author_id ); ?>

					<div class="portal-comment-content-wrap">
						<div class="portal-post-author sd-gap-4 sd-flex sd-items-center">
							<?php if ( $user_exists && ! empty( $user_view ) ) { ?>
								<a href="<?php echo esc_url( $user_view ); ?>" class="sd-mobile-flex-wrap"> <span class="portal-store-post-author"><?php echo esc_html( suredash_get_author_name( get_the_author_meta( 'display_name', $author_id ) ) ); ?></span> </a>
							<?php } else { ?>
								<span class="sd-mobile-flex-wrap"> <span class="portal-store-post-author"><?php echo esc_html( suredash_get_author_name( get_the_author_meta( 'display_name', $author_id ) ) ); ?></span> </span>
							<?php } ?>

							<?php echo esc_html( Labels::get_label( 'commented_on' ) ) . ' '; ?>

							<a class="portal-user-view-comment-title" href="<?php echo esc_url( (string) get_the_permalink( $p_id ) ); ?>">
								<?php echo esc_html( $post_title ); ?>
							</a>
						</div>
						<div class="portal-user-comment-content">
							<?php echo wp_kses_post( $comment_content ); ?>
						</div>
					</div>
				</div>
			</div>
		</section>
	</div>
<?php
