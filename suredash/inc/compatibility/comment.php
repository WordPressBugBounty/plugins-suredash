<?php
/**
 * Comment Management Stuff
 *
 * This class will holds the Comment related handling.
 *
 * @package SureDash
 * @since 0.0.6
 */

namespace SureDashboard\Inc\Compatibility;

use SureDashboard\Inc\Traits\Get_Instance;
use SureDashboard\Inc\Utils\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Comment & Post Handler
 *
 * @since 0.0.6
 */
class Comment {
	use Get_Instance;

	/**
	 * Constructor
	 *
	 * @since 0.0.6
	 * @return void
	 */
	public function __construct() {
		add_filter( 'comment_notification_recipients', [ $this, 'disable_wp_comment_emails' ], 10, 2 );
		add_filter( 'comment_moderation_recipients', [ $this, 'disable_wp_comment_emails' ], 10, 2 );

		add_action(
			'deleted_comment',
			function ( $comment_id, $comment ): void {
				if ( $comment_id && $comment ) {
					$this->delete_related_media( $comment->comment_content );
				}
			},
			10,
			2
		);

		add_action(
			'deleted_post',
			function ( $post_id, $post ): void {
				if ( $post_id && $post && $post->post_type === SUREDASHBOARD_FEED_POST_TYPE ) {
					$this->delete_related_media( $post->post_content );
				}
			},
			10,
			2
		);
	}

	/**
	 * Disable default WordPress comment notification emails for SureDash post types.
	 *
	 * SureDash has its own notification system, so WordPress default comment
	 * emails should not be sent for community posts, content, or portal spaces.
	 *
	 * @param array<string> $emails     Array of email addresses to notify.
	 * @param int           $comment_id The comment ID.
	 *
	 * @since 1.7.3
	 * @return array<string> Filtered array of email addresses.
	 */
	public function disable_wp_comment_emails( array $emails, $comment_id ): array {
		$comment = get_comment( $comment_id );

		if ( ! $comment || ! $comment->comment_post_ID ) {
			return $emails;
		}

		$post_type = get_post_type( (int) $comment->comment_post_ID );
		/**
		 * Filter the post types for which WordPress default comment emails are disabled.
		 *
		 * @since 1.7.3
		 *
		 * @param array<string> $post_types Array of post type slugs.
		 */
		$sd_cpt_types = apply_filters( 'suredash_disable_comment_email_post_types', [ SUREDASHBOARD_POST_TYPE, SUREDASHBOARD_FEED_POST_TYPE, SUREDASHBOARD_SUB_CONTENT_POST_TYPE ] );

		if ( in_array( $post_type, $sd_cpt_types, true ) ) {
			return [];
		}

		return $emails;
	}

	/**
	 * Delete media files associated with content.
	 *
	 * Each `<img>` URL is resolved through {@see Helper::get_safe_uploads_path()},
	 * which validates the path is strictly inside the WordPress uploads directory.
	 * URLs containing path-traversal sequences (e.g. `../`) or pointing outside
	 * uploads are silently skipped.
	 *
	 * @param string $content The content containing media URLs.
	 * @since 0.0.6
	 * @return void
	 */
	public function delete_related_media( $content ): void {
		preg_match_all( '/<img[^>]+src="([^">]+)"/', (string) $content, $matched_images );

		if ( empty( $matched_images[1] ) ) {
			return;
		}

		$allowed_extensions = [ 'gif', 'png', 'jpg', 'jpeg', 'webp' ];

		foreach ( $matched_images[1] as $image_url ) {
			$image_path = Helper::get_safe_uploads_path( (string) $image_url, $allowed_extensions );
			if ( $image_path === null ) {
				continue;
			}

			/**
			 * Fires before a SureDash-managed file is deleted.
			 *
			 * This hook allows developers to perform cleanup on associated resources,
			 * such as removing the file from remote storage (e.g., S3, Cloudflare R2)
			 * or deleting the corresponding WordPress attachment post.
			 *
			 * @since 1.6.3
			 *
			 * @param string $image_path The absolute server path of the file being deleted.
			 * @param string $image_url  The URL of the file being deleted.
			 */
			do_action( 'suredash_before_file_delete', $image_path, $image_url );
			wp_delete_file( $image_path );
		}
	}
}
