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
	 * Meta key holding the URLs of files uploaded for a post or comment.
	 *
	 * @since 1.12.4
	 */
	public const UPLOADED_MEDIA_META_KEY = '_suredash_uploaded_media';

	/**
	 * Constructor
	 *
	 * @since 0.0.6
	 * @return void
	 */
	public function __construct() {
		add_filter( 'comment_notification_recipients', [ $this, 'disable_wp_comment_emails' ], 10, 2 );
		add_filter( 'comment_moderation_recipients', [ $this, 'disable_wp_comment_emails' ], 10, 2 );

		// Run before WordPress removes the meta, so the upload record can still be read.
		add_action(
			'delete_comment',
			function ( $comment_id, $comment ): void {
				if ( $comment_id && $comment && (int) $comment->user_id > 0 ) {
					$this->delete_related_media( $comment->comment_content, (int) $comment->user_id, $this->get_uploaded_media( 'comment', (int) $comment_id ) );
				}
			},
			10,
			2
		);

		add_action(
			'before_delete_post',
			function ( $post_id, $post ): void {
				if ( $post_id && $post && $post->post_type === SUREDASHBOARD_FEED_POST_TYPE ) {
					$this->delete_related_media( $post->post_content, (int) $post->post_author, $this->get_uploaded_media( 'post', (int) $post_id ) );
				}
			},
			10,
			2
		);

		// Comments saved outside SureDash (core form, REST) get an empty record, so their delete never parses the content.
		add_action(
			'wp_insert_comment',
			static function ( $comment_id, $comment ): void {
				if ( (int) $comment->user_id > 0 && strpos( (string) $comment->comment_content, '<img' ) !== false ) {
					add_comment_meta( (int) $comment_id, self::UPLOADED_MEDIA_META_KEY, [], true );
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
	 * Record the files uploaded for a post or comment.
	 *
	 * Only these files are deleted with the post or comment. The meta is always
	 * written, even when empty, so new content never falls back to parsing.
	 *
	 * @param string        $meta_type        'post' or 'comment'.
	 * @param int           $object_id        The post or comment ID.
	 * @param array<string> $urls             Upload URLs to add.
	 * @param string        $previous_content Optional. Content before an edit, used to seed the record of content saved before uploads were tracked.
	 * @since 1.12.4
	 * @return void
	 */
	public function track_uploaded_media( string $meta_type, int $object_id, array $urls, string $previous_content = '' ): void {
		// Content saved before uploads were tracked: keep its existing images in the record.
		$tracked = $this->get_uploaded_media( $meta_type, $object_id ) ?? $this->get_content_images( $previous_content );
		update_metadata( $meta_type, $object_id, self::UPLOADED_MEDIA_META_KEY, array_values( array_unique( array_merge( $tracked, $urls ) ) ) );
	}

	/**
	 * Delete media files associated with content.
	 *
	 * Deletes the files recorded at upload time; content saved before 1.12.4 has no
	 * record and falls back to its `<img>` URLs. Each URL goes through
	 * {@see Helper::get_safe_uploads_path()} and is skipped unless it is inside the
	 * author's own folder (uploads/suredashboard/<author_id>/).
	 *
	 * @param string             $content        The content containing media URLs.
	 * @param int                $author_id      The ID of the user who authored the content. Nothing is deleted when 0.
	 * @param array<string>|null $uploaded_media Recorded upload URLs, or null to fall back to the content's images.
	 * @since 0.0.6
	 * @return void
	 */
	public function delete_related_media( $content, int $author_id = 0, ?array $uploaded_media = null ): void {
		if ( $author_id <= 0 ) {
			return;
		}

		// Content saved before uploads were tracked has no record, so fall back to its images.
		if ( $uploaded_media === null ) {
			$uploaded_media = $this->get_content_images( $content );
		}

		if ( empty( $uploaded_media ) ) {
			return;
		}

		$allowed_extensions = [ 'gif', 'png', 'jpg', 'jpeg', 'webp' ];

		foreach ( $uploaded_media as $image_url ) {
			$image_path = Helper::get_safe_uploads_path( (string) $image_url, $allowed_extensions, $author_id );
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

	/**
	 * Get the recorded upload URLs of a post or comment.
	 *
	 * @param string $meta_type 'post' or 'comment'.
	 * @param int    $object_id The post or comment ID.
	 * @since 1.12.4
	 * @return array<string>|null Recorded upload URLs, or null when the content predates upload tracking.
	 */
	private function get_uploaded_media( string $meta_type, int $object_id ): ?array {
		$uploaded_media = get_metadata_raw( $meta_type, $object_id, self::UPLOADED_MEDIA_META_KEY, true );

		return $uploaded_media === null ? null : (array) $uploaded_media;
	}

	/**
	 * Get the image URLs found in content.
	 *
	 * @param string $content The content to parse.
	 * @since 1.12.4
	 * @return array<string> Image URLs found in the content.
	 */
	private function get_content_images( $content ): array {
		preg_match_all( '/<img[^>]+src="([^">]+)"/', (string) $content, $matched_images );
		return $matched_images[1];
	}
}
