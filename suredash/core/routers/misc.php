<?php
/**
 * Misc Router Initialize.
 *
 * @package SureDash
 */

namespace SureDashboard\Core\Routers;

use SureDashboard\Core\Models\Controller;
use SureDashboard\Core\Notifier\Base as Notifier_Base;
use SureDashboard\Inc\Traits\Get_Instance;
use SureDashboard\Inc\Traits\Rest_Errors;
use SureDashboard\Inc\Utils\Activity_Tracker;
use SureDashboard\Inc\Utils\Helper;
use SureDashboard\Inc\Utils\Labels;
use SureDashboard\Inc\Utils\PostMeta;
use SureDashboard\Inc\Utils\Sanitizer;
use SureDashboard\Inc\Utils\Uploader;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Misc Router.
 */
class Misc {
	use Get_Instance;
	use Rest_Errors;

	// Default dimensions for uploaded images (width, height) in pixels.
	private const DEFAULT_IMAGE_DIMENSIONS = [ 1000, 1000 ];

	/**
	 * Handler to get topic submitted.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @since 0.0.1
	 * @return void
	 */
	public function submit_post( $request ): void {

		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$submitted_data = ! empty( $_POST['formData'] ) ? json_decode( wp_unslash( $_POST['formData'] ), true ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Data is sanitized in the Sanitizer::sanitize_meta_data() method.

		if ( empty( $submitted_data ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'default' ) ] );
		}

		$comment_data    = stripslashes($submitted_data['custom_post_content']); // phpcs:ignore -- Data is sanitized in the wp_kses_post() method.
		$current_user_id = get_current_user_id();

		// Process uploaded images using common method.
		$image_result    = $this->process_uploaded_images( $comment_data, $current_user_id, 'custom_post_cover_image' );
		$comment_data    = $image_result['content'];
		$cover_image_url = $image_result['cover_image_url'];
		$uploaded_images = $image_result['uploaded_images'];

		$submitted_data['custom_post_content'] = $comment_data;

		// Before sanitization - prepare visibility_scope as array of strings.
		// Values are prefixed with 'user-' or 'ag-' (e.g., 'user-123', 'ag-45').
		if ( isset( $submitted_data['visibility_scope'] ) ) {
			// Convert to array if it's a comma-separated string.
			if ( ! is_array( $submitted_data['visibility_scope'] ) ) {
				$submitted_data['visibility_scope'] = explode( ',', $submitted_data['visibility_scope'] );
			}

			// Sanitize each value but keep the prefixes intact.
			$submitted_data['visibility_scope'] = array_values(
				array_filter(
					array_map( 'sanitize_text_field', $submitted_data['visibility_scope'] )
				)
			);
		}

		$submitted_data = Sanitizer::sanitize_meta_data( $submitted_data, 'metadata' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Data is sanitized in the Sanitizer::sanitize_meta_data() method.

		$post_name     = '';
		$filtered_data = [];
		$category_id   = 0;

		// Cover media and embed media.
		$embed_url = '';

		foreach ( $submitted_data as $key => $value ) {
			if ( empty( $value ) ) {
				continue;
			}

			switch ( $key ) {
				case 'custom_post_title':
					$post_name                   = sanitize_title( $value );
					$filtered_data['post_title'] = strval( $value );
					break;

				case 'custom_post_content':
					$filtered_data['post_content'] = do_shortcode( $value );
					break;

				case 'custom_post_tax_id':
					$category_id = absint( $value );
					break;

				case 'custom_post_embed_media':
					$embed_url = esc_url_raw( $value );
					break;

				case 'visibility_scope':
					$filtered_data['visibility_scope'] = $value; // Already JSON-encoded from line 68.
					break;
			}
		}

		$filtered_data['post_author'] = $current_user_id;

		$other_defaults = [
			'post_name'   => $post_name,
			'post_type'   => SUREDASHBOARD_FEED_POST_TYPE,
			'post_status' => apply_filters( 'suredash_inserting_default_post_status', 'publish' ),
		];

		// Now its time to create a post with defaults & filtered data.
		$post_id = sd_wp_insert_post( array_merge( $other_defaults, $filtered_data ) );

		if ( is_wp_error( $post_id ) ) {
			foreach ( $uploaded_images as $image ) {
				// Delete the uploaded image.
				$upload_dir  = wp_upload_dir();
				$upload_path = $upload_dir['basedir'] . '/suredashboard/' . $current_user_id . '/assets/';
				$upload_url  = $upload_dir['baseurl'] . '/suredashboard/' . $current_user_id . '/assets/';
				$image_path  = str_replace( $upload_url, $upload_path, $image );

				/** This action is documented in inc/compatibility/comment.php */
				do_action( 'suredash_before_file_delete', $image_path, $image );
				unlink($image_path); // phpcs:ignore -- This is a safe operation.
			}
			wp_send_json_error( [ 'message' => $post_id->get_error_message() ] );
		}

		do_action( 'suredash_after_post_submit', $post_id, $filtered_data );

		// Check if any user mentioned in the post content, having data-portal_mentioned_user="12" attribute.
		if ( ! empty( $filtered_data['post_content'] ) ) {
			$filtered_data['post_content'] = preg_replace_callback(
				'/data-portal_mentioned_user="(\d+)"/',
				// @phpstan-ignore-next-line
				static function( $matches ) use ( $post_id, $filtered_data ): void {

					$tagged_id       = absint( $matches[1] );
					$current_user_id = get_current_user_id();
					$topic_id        = absint( $post_id );

					// Don't notify if user not in visibility scope.
					// Passing visibility_scope to function as meta is yet to be saved.
					$push_notification = Helper::is_post_visible_to_user(
						[
							'post_id'          => $topic_id,
							'user_id'          => $tagged_id,
							'visibility_scope' => $filtered_data['visibility_scope'] ?? null,
						]
					);

					if ( method_exists( Notifier_Base::get_instance(), 'dispatch_user_notification' ) && $push_notification ) {
						// Dispatch mentioning notification.
						Notifier_Base::get_instance()->dispatch_user_notification(
							'suredashboard_user_mentioned',
							[
								'mentioned_user' => $tagged_id,
								'topic_id'       => $topic_id,
								'caller'         => $current_user_id,
							]
						);
					}
				},
				$filtered_data['post_content']
			);
		}

		// Update post meta for cover image.
		if ( ! empty( $cover_image_url ) ) {
			sd_update_post_meta( $post_id, 'custom_post_cover_image', $cover_image_url );
		}
		// Update embed media link.
		if ( ! empty( $embed_url ) ) {
			sd_update_post_meta( $post_id, 'custom_post_embed_media', $embed_url );
		}
		if ( ! empty( $filtered_data['visibility_scope'] ) ) {
			sd_update_post_meta( $post_id, 'visibility_scope', $filtered_data['visibility_scope'] );
		}

		// Add post in a space if space-selection is set, case: full screen mobile app.
		$space_id = absint( $submitted_data['custom_post_space_selection'] ?? 0 );
		if ( $space_id ) {
			$category_id = absint( PostMeta::get_post_meta_value( $space_id, 'feed_group_id' ) );
		}

		// Instead of using 'tax_input' used 'wp_set_post_terms', because 'tax_input' requires 'assign_terms' access to the taxonomy.
		if ( $category_id ) {
			wp_set_post_terms( $post_id, [ $category_id ], SUREDASHBOARD_FEED_TAXONOMY );
		}

		if ( method_exists( Notifier_Base::get_instance(), 'dispatch_admin_notification' ) ) {
			// Dispatch notification.
			Notifier_Base::get_instance()->dispatch_admin_notification( 'suredashboard_user_submitted_topic', [ 'topic_id' => $post_id ] );
		}

		wp_send_json_success( [ 'message' => Labels::get_label( 'post_submitted_successfully' ) ] );
	}

	/**
	 * Handler to load more posts.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @since 0.0.1
	 * @return void
	 */
	public function load_more_posts( $request ): void {

		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		ob_start();

		$base_id     = ! empty( $_POST['base_id'] ) ? absint( $_POST['base_id'] ) : 0;
		$taxonomy    = ! empty( $_POST['taxonomy'] ) ? sanitize_text_field( wp_unslash( $_POST['taxonomy'] ) ) : SUREDASHBOARD_FEED_TAXONOMY;
		$post_type   = ! empty( $_POST['post_type'] ) ? sanitize_text_field( wp_unslash( $_POST['post_type'] ) ) : SUREDASHBOARD_FEED_POST_TYPE;
		$category_id = ! empty( $_POST['category'] ) ? absint( $_POST['category'] ) : 0;
		$paged       = ! empty( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
		$user_id     = ! empty( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$view_type   = ! empty( $_POST['view_type'] ) ? sanitize_text_field( wp_unslash( $_POST['view_type'] ) ) : 'grid';
		$feeds_sort  = ! empty( $_POST['feeds_sort'] ) ? sanitize_text_field( wp_unslash( $_POST['feeds_sort'] ) ) : 'date_desc';
		$space_id    = ! empty( $_POST['space_id'] ) ? absint( $_POST['space_id'] ) : 0;

		// Parse sort parameters using helper.
		$sort_params = Helper::parse_feeds_sort_params( $feeds_sort );
		$order_by    = $sort_params['order_by'];
		$order       = $sort_params['order'];
		$meta_key    = $sort_params['meta_key'];

		$queried_page = ! empty( $_POST['sub_queried_page'] ) ? sanitize_text_field( wp_unslash( $_POST['sub_queried_page'] ) ) : '';

		// Determine if excerpt content should be enforced.
		$enforce_excerpt_content = false;
		if ( $queried_page === 'user-view' ) {
			// Always enforce excerpt for user-view page.
			$enforce_excerpt_content = true;
		} elseif ( $queried_page === 'feeds' ) {
			// For feeds page, check the feeds_content_type setting.
			$feeds_content_type      = Helper::get_option( 'feeds_content_type' );
			$enforce_excerpt_content = empty( $feeds_content_type ) || $feeds_content_type === 'excerpt';
		} elseif ( $queried_page === 'discussion-space' || ! empty( $space_id ) ) {
			// For discussion spaces, enforce excerpt content.
			$enforce_excerpt_content = false;
		}

		$pinned_posts = Helper::get_pinned_posts( $base_id );

		if ( $user_id ) {
			$result = Controller::get_user_query_data(
				'Feeds',
				apply_filters(
					'suredashboard_user_queried_post_args',
					[
						'post_types'     => [ $post_type ],
						'user_id'        => $user_id,
						'posts_per_page' => Helper::get_option( 'feeds_per_page', 5 ),
						'paged'          => $paged,
					]
				)
			);
		} else {
			$query_args = [
				'category_id'    => $category_id,
				'post_type'      => $post_type,
				'paged'          => $paged,
				'taxonomy'       => $taxonomy,
				'posts_per_page' => Helper::get_option( 'feeds_per_page', 5 ),
				'order_by'       => $order_by,
				'order'          => $order,
			];

			// Add meta_key if needed.
			if ( ! empty( $meta_key ) ) {
				$query_args['meta_key'] = $meta_key;
			}

			$result = Controller::get_query_data( 'Feeds', $query_args );
		}

		add_filter( 'suredash_skip_restricted_post', '__return_true' );

		if ( ! empty( $result ) ) {
			$is_first_page = $paged === 1;

			// Check if we should render in list view.
			if ( $view_type === 'list' ) {
				// Prepare items for list view.
				$list_items = [];

				// Render pinned posts first on page 1 (view toggle / sort replaces entire container).
				if ( $is_first_page && ! empty( $pinned_posts ) ) {
					foreach ( $pinned_posts as $pinned_post_id ) {
						if ( sd_post_exists( $pinned_post_id ) ) {
							$post_link   = get_permalink( $pinned_post_id );
							$author_id   = get_post_field( 'post_author', $pinned_post_id );
							$author_name = suredash_get_user_display_name( (int) $author_id );
							$post_date   = suredash_get_relative_time( $pinned_post_id, false, $queried_page === 'feeds' );
							$description = sprintf(
								/* translators: %1$s: author name, %2$s: post date */
								__( '%1$s • %2$s', 'suredash' ),
								$author_name,
								$post_date
							);

							$list_items[] = [
								'id'                 => $pinned_post_id,
								'title'              => sd_get_post_field( $pinned_post_id ),
								'description'        => $description,
								'link'               => $post_link,
								'user_initials_icon' => true,
								'user_id'            => $author_id,
								'enable_likes'       => true,
								'enable_comments'    => true,
								'is_pinned'          => true,
								'options'            => [
									[
										'icon'    => 'ChevronRight',
										'link'    => $post_link,
										'js-hook' => '',
										'title'   => __( 'View', 'suredash' ),
									],
								],
							];
						}
					}
				}

				foreach ( $result as $post ) {
					if ( empty( $post['ID'] ) ) {
						continue;
					}

					// Skip pinned posts from regular list to avoid duplicates.
					if ( in_array( absint( $post['ID'] ), $pinned_posts, true ) ) {
						continue;
					}

					$post_id   = absint( $post['ID'] );
					$post_link = get_permalink( $post_id );

					// Build description: author name and relative date.
					$author_id   = get_post_field( 'post_author', $post_id );
					$author_name = suredash_get_user_display_name( (int) $author_id );
					$post_date   = suredash_get_relative_time( $post_id, false, $queried_page === 'feeds' );
					$description = sprintf(
						/* translators: %1$s: author name, %2$s: post date */
						__( '%1$s • %2$s', 'suredash' ),
						$author_name,
						$post_date
					);

					$list_items[] = [
						'id'                 => $post_id,
						'title'              => sd_get_post_field( $post_id ),
						'description'        => $description,
						'link'               => $post_link,
						'user_initials_icon' => true,
						'user_id'            => $author_id,
						'enable_likes'       => true,
						'enable_comments'    => true,
						'options'            => [
							[
								'icon'    => 'ChevronRight',
								'link'    => $post_link,
								'js-hook' => '',
								'title'   => __( 'View', 'suredash' ),
							],
						],
					];
				}

				// Render list items.
				foreach ( $list_items as $item ) {
					suredash_render_list_item( $item );
				}
			} else {
				// Grid view - render pinned posts first on page 1.
				if ( $is_first_page && ! empty( $pinned_posts ) ) {
					foreach ( $pinned_posts as $pinned_post_id ) {
						if ( sd_post_exists( $pinned_post_id ) ) {
							$pinned_post = (array) sd_get_post( $pinned_post_id );
							Helper::render_post( $pinned_post, $base_id, true, $queried_page === 'feeds' );
						}
					}
				}

				foreach ( $result as $post ) {

					if ( empty( $post['ID'] ) ) {
						continue;
					}

					// Skip pinned posts from regular list to avoid duplicates.
					if ( in_array( absint( $post['ID'] ), $pinned_posts, true ) ) {
						continue;
					}

					/**
					 * Enforce excerpt content type for user-view & feeds page.
					 * Case: query_var reset after load more which fails the suredash_get_sub_queried_page call.
					 *
					 * @since 1.0.0
					 */
					if ( $enforce_excerpt_content ) {
						add_filter( 'suredash_post_enforce_excerpt_content', '__return_true' );
					}

					// Use the Helper function to render the post.
					Helper::render_post( $post, $base_id, false, $queried_page === 'feeds' );

					/**
					 * Remove the filter after rendering the post.
					 *
					 * @since 1.0.0
					 */
					if ( $enforce_excerpt_content ) {
						remove_filter( 'suredash_post_enforce_excerpt_content', '__return_true' );
					}
				}
			}
		}

		remove_filter( 'suredash_skip_restricted_post', '__return_true' );

		wp_reset_postdata();

		$content = ob_get_clean();

		wp_send_json_success( [ 'content' => $content ] );
	}

	/**
	 * Load more comments. This function will be triggered when the infinite loader is in the viewport.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @since 1.3.2
	 * @return void
	 */
	public function load_more_comments( $request ): void {

		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$user_id = ! empty( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$paged   = ! empty( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;

		if ( ! $user_id ) {
			wp_send_json_error( [ 'message' => 'User ID is required' ] );
		}

		$no_of_comments = 8;
		$offset         = ( $paged - 1 ) * $no_of_comments;

		ob_start();
		Helper::suredash_user_comments(
			$user_id,
			[
				'offset'             => $offset,
				'show_empty_message' => false,
				'number'             => $no_of_comments,
			]
		);
		$content = ob_get_clean();

		wp_send_json_success( [ 'content' => $content ] );
	}

	/**
	 * Bookmark an item.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @since 0.0.1
	 * @return void
	 */
	public function bookmark_item( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$user_id   = get_current_user_id();
		$item_id   = ! empty( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;
		$item_type = ! empty( $_POST['item_type'] ) ? sanitize_text_field( wp_unslash( $_POST['item_type'] ) ) : SUREDASHBOARD_POST_TYPE;

		if ( ! $item_id ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'default' ) ] );
		}

		$status           = 'none';
		$bookmarked_items = suredash_get_all_bookmarked_items();
		$bookmarked_items = ! empty( $bookmarked_items ) ? $bookmarked_items : [];

		if ( isset( $bookmarked_items[ $item_id ] ) ) {
			$status = 'un-bookmarked';
			unset( $bookmarked_items[ $item_id ] ); // Un-bookmark the item.
		} else {
			$status                       = 'bookmarked';
			$bookmarked_items[ $item_id ] = $item_type; // Bookmark the item.
		}

		do_action( 'suredash_item_bookmark', $item_id, $item_type, $status, $user_id );
		sd_update_user_meta( $user_id, 'portal_bookmarked_items', $bookmarked_items );

		wp_send_json_success( [ 'status' => $status ] );
	}

	/**
	 * Handler to update user profile.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @since 0.0.1
	 * @return void
	 */
	public function update_user_profile( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$first_name         = ! empty( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$last_name          = ! empty( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
		$display_name       = ! empty( $_POST['display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['display_name'] ) ) : '';
		$user_url           = ! empty( $_POST['user_url'] ) ? esc_url_raw( wp_unslash( $_POST['user_url'] ) ) : '';
		$description        = ! empty( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
		$user_banner_image  = ! empty( $_POST['user_banner_image'] ) ? sanitize_text_field( wp_unslash( $_POST['user_banner_image'] ) ) : '';
		$user_profile_photo = ! empty( $_FILES['user_profile_photo']['name'] ) ? sanitize_text_field( wp_unslash( $_FILES['user_profile_photo']['name'] ) ) : '';

		$isset_cover_image   = isset( $_FILES['user_banner_image'] ) && ! empty( $_FILES['user_banner_image']['name'] ) ? true : false;
		$sanitized_file_data = $isset_cover_image ? Sanitizer::sanitize_meta_data( $_FILES['user_banner_image'], 'array' ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Data is sanitized in the Sanitizer::sanitize_meta_data() method.

		$isset_profile_image    = isset( $_FILES['user_profile_photo'] ) && ! empty( $_FILES['user_profile_photo']['name'] ) ? true : false;
		$sanitized_profile_data = $isset_profile_image ? Sanitizer::sanitize_meta_data( $_FILES['user_profile_photo'], 'array' ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Data is sanitized in the Sanitizer::sanitize_meta_data() method.

		$user_id = get_current_user_id();

		$user_args = [
			'ID'           => $user_id,
			'first_name'   => $first_name,
			'last_name'    => $last_name,
			'display_name' => $display_name,
			'description'  => $description,
			'user_url'     => $user_url,
		];

		$result = sd_wp_update_user( $user_args );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result );
		}

		// Update cover image link.
		$cover_url = $isset_cover_image && method_exists( Uploader::get_instance(), 'process_media' ) ? Uploader::get_instance()->process_media( $user_banner_image, $sanitized_file_data, $user_id ) : '';
		if ( is_string( $cover_url ) && ! empty( $cover_url ) ) {
			// Delete previous cover image before updating with new one.
			$this->delete_user_media_file( $user_id, 'user_banner_image' );

			sd_update_user_meta( $user_id, 'user_banner_image', $cover_url );
		}

		// Update profile image link.
		$profile_url = $isset_profile_image && method_exists( Uploader::get_instance(), 'process_media' ) ? Uploader::get_instance()->process_media( $user_profile_photo, $sanitized_profile_data, $user_id ) : '';
		if ( is_string( $profile_url ) && ! empty( $profile_url ) ) {
			// Delete previous profile photo before updating with new one.
			$this->delete_user_media_file( $user_id, 'user_profile_photo' );

			sd_update_user_meta( $user_id, 'user_profile_photo', $profile_url );
		}

		// Handle profile photo removal.
		$remove_profile_photo = ! empty( $_POST['remove_profile_photo'] ) ? sanitize_text_field( wp_unslash( $_POST['remove_profile_photo'] ) ) : '';
		if ( $remove_profile_photo === '1' ) {
			// Delete the physical file from upload directory.
			$this->delete_user_media_file( $user_id, 'user_profile_photo' );
			sd_update_user_meta( $user_id, 'user_profile_photo', '' );
		}

		// Handle cover image removal.
		$remove_cover_image = ! empty( $_POST['remove_banner_image'] ) ? sanitize_text_field( wp_unslash( $_POST['remove_banner_image'] ) ) : '';
		if ( $remove_cover_image === '1' ) {
			// Delete the physical file from upload directory.
			$this->delete_user_media_file( $user_id, 'user_banner_image' );
			sd_update_user_meta( $user_id, 'user_banner_image', '' );
		}

		// Update password if provided.
		if ( ! empty( $_POST['new_password'] ) ) {
			wp_set_password( $_POST['new_password'], $user_id ); // phpcs:ignore -- Data is sanitized in the wp_set_password() method.
		}

		// Compatibility for 'external' profile fields.
		$external_fields = suredash_get_user_profile_fields();
		if ( ! empty( $external_fields ) ) {
			foreach ( $external_fields as $field_key => $field_data ) {
				if ( isset( $field_data['external'] ) && boolval( $field_data['external'] ) ) {
					$field_value = ! empty( $_POST[ $field_key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field_key ] ) ) : '';
					sd_update_user_meta( $user_id, $field_key, $field_value );
				}
			}
		}

		do_action( 'suredash_after_user_profile_update', $user_id );

		// Handle notification settings.
		$notification_fields = [
			'enable_all_email_notifications',
			'enable_admin_email',
			'email_on_post_replies',
			'email_on_comment_replies',
			'email_on_mention',
			'enable_all_portal_notifications',
			'enable_admin_portal_notification',
			'portal_notification_on_post_replies',
			'portal_notification_on_comment_replies',
			'portal_notification_on_mention',
		];
		foreach ( $notification_fields as $field ) {
			$field_value = ! empty( $_POST[ $field ] ) ? '1' : '0';
			sd_update_user_meta( $user_id, $field, $field_value );
		}

		// Social links.
		$portal_social_links = suredash_get_user_profile_social_links();
		if ( ! empty( $portal_social_links ) ) {
			$user_social_links = sd_get_user_meta( $user_id, 'portal_social_links', true );
			$user_social_links = is_array( $user_social_links ) && ! empty( $user_social_links ) ? $user_social_links : [];
			foreach ( $portal_social_links as $handle => $link_data ) {
				if ( $handle === 'mail' ) {
					$social_link = ! empty( $_POST[ $handle ] ) ? sanitize_email( $_POST[ $handle ] ) : '';
				} else {
					$social_link = ! empty( $_POST[ $handle ] ) ? esc_url_raw( $_POST[ $handle ] ) : '';
				}
				$user_social_links[ $handle ] = $social_link;
			}
			sd_update_user_meta( $user_id, 'portal_social_links', $user_social_links );
		}

		wp_send_json_success(
			[
				'message' => Labels::get_label( 'profile_updated' ),
			]
		);
	}

	/**
	 * Handler to send test email notification.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @since 1.5.0
	 * @return void
	 */
	public function send_test_email( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( [ 'message' => __( 'User not logged in.', 'suredash' ) ] );
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			wp_send_json_error( [ 'message' => __( 'User not found.', 'suredash' ) ] );
		}

		$user_email = $user->user_email;
		$user_name  = suredash_get_user_display_name( $user_id );

		// Get site name for email.
		$site_name = get_bloginfo( 'name' );

		// Get request body - use get_json_params() for JSON body.
		$body     = $request->get_json_params();
		$template = [];

		if ( is_array( $body ) ) {
			$template = isset( $body['template'] ) && is_array( $body['template'] ) ? $body['template'] : [];
		}

		// Prepare email subject from template or default.
		$subject = ! empty( $template['subject'] ) ? (string) wp_strip_all_tags( $template['subject'] ) : sprintf(
			/* translators: %s: Site name */
			__( 'Test Email from %s', 'suredash' ),
			$site_name
		);

		// Prepare email body from template or default.
		if ( ! empty( $template['body'] ) && is_string( $template['body'] ) ) {
			$message = $template['body'];

			// Replace common placeholders with test data.
			$message = str_replace( '{{USER_NAME}}', $user_name, $message );
			$message = str_replace( '{{USER_EMAIL}}', $user_email, $message );
			$message = str_replace( '{{SITE_NAME}}', $site_name, $message );
			$message = str_replace( '{{SITE_URL}}', get_site_url(), $message );
		} else {
			$message = sprintf(
				/* translators: %1$s: User name, %2$s: Site name */
				__(
					'Hi %1$s,
					This is a test email from %2$s to verify your email notification settings are working correctly.
					If you received this email, your notification settings are configured properly!
					--- This email was sent from %2$s',
					'suredash'
				),
				$user_name,
				$site_name
			);
		}

		// Set email headers.
		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $site_name . ' <' . get_option( 'admin_email' ) . '>',
		];

		// Send the email.
		$sent = wp_mail( $user_email, $subject, $message, $headers );

		if ( $sent ) {
			wp_send_json_success(
				[
					'message' => __( 'Test email sent successfully! Please check your inbox.', 'suredash' ),
					'email'   => $user_email,
				]
			);
		} else {
			wp_send_json_error(
				[
					'message' => __( 'Failed to send test email. Please check your email configuration.', 'suredash' ),
				]
			);
		}
	}

	/**
	 * Handler to update user notification settings.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @since 1.5.0
	 * @return void
	 */
	public function update_notification_settings( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$user_id = get_current_user_id();

		// Sanitize notification settings.
		$receive_all_notifications = ! empty( $_POST['receive_all_notifications'] ) ? sanitize_text_field( wp_unslash( $_POST['receive_all_notifications'] ) ) : '0';
		$receive_admin_mails       = ! empty( $_POST['receive_admin_mails'] ) ? sanitize_text_field( wp_unslash( $_POST['receive_admin_mails'] ) ) : '0';
		$reply_to_my_post          = ! empty( $_POST['reply_to_my_post'] ) ? sanitize_text_field( wp_unslash( $_POST['reply_to_my_post'] ) ) : '0';
		$reply_to_my_comment       = ! empty( $_POST['reply_to_my_comment'] ) ? sanitize_text_field( wp_unslash( $_POST['reply_to_my_comment'] ) ) : '0';
		$mention_notifications     = ! empty( $_POST['mention_notifications'] ) ? sanitize_text_field( wp_unslash( $_POST['mention_notifications'] ) ) : '0';

		// Prepare notification settings array.
		$notification_settings = [
			'receive_all_notifications' => $receive_all_notifications,
			'receive_admin_mails'       => $receive_admin_mails,
			'reply_to_my_post'          => $reply_to_my_post,
			'reply_to_my_comment'       => $reply_to_my_comment,
			'mention_notifications'     => $mention_notifications,
		];

		// Save to user meta.
		$result = sd_update_user_meta( $user_id, 'suredash_notification_settings', $notification_settings );

		if ( $result === false ) {
			wp_send_json_error( [ 'message' => __( 'Failed to update notification settings.', 'suredash' ) ] );
		}

		wp_send_json_success(
			[
				'message'  => __( 'Notification settings updated successfully!', 'suredash' ),
				'settings' => $notification_settings,
			]
		);
	}

	/**
	 * Handler to get user notification settings.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @since 1.5.0
	 * @return void
	 */
	public function get_notification_settings( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$user_id = get_current_user_id();

		// Get saved notification settings.
		$notification_settings = sd_get_user_meta( $user_id, 'suredash_notification_settings', true );

		// Default settings if none exist.
		if ( empty( $notification_settings ) || ! is_array( $notification_settings ) ) {
			$notification_settings = [
				'receive_all_notifications' => '1', // Default to enabled.
				'receive_admin_mails'       => '1',
				'reply_to_my_post'          => '1',
				'reply_to_my_comment'       => '1',
				'mention_notifications'     => '1',
			];
		}

		wp_send_json_success(
			[
				'settings' => $notification_settings,
			]
		);
	}

	/**
	 * React with a post.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @since 0.0.1
	 * @return void
	 */
	public function entity_reaction( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$entity          = ! empty( $_POST['entity'] ) ? sanitize_text_field( wp_unslash( $_POST['entity'] ) ) : 'post';
		$entity_id       = ! empty( $_POST['entity_id'] ) ? absint( $_POST['entity_id'] ) : 0;
		$current_user_id = get_current_user_id();
		$is_comment_ent  = $entity === 'comment';

		if ( ! $entity_id ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'default' ) ] );
		}

		// Gather the response data.
		$response = [];

		// Update the post/comment meta for likes.
		if ( $is_comment_ent ) {
			$entity_reactions = get_comment_meta( $entity_id, 'portal_comment_likes', true );
		} else {
			$entity_reactions = sd_get_post_meta( $entity_id, 'portal_post_likes', true );
		}
		$entity_reactions = is_array( $entity_reactions ) ? $entity_reactions : [];

		if ( in_array( $current_user_id, $entity_reactions, true ) ) {
			$like_status      = 'unliked';
			$entity_reactions = array_diff( $entity_reactions, [ $current_user_id ] );
		} else {
			$like_status        = 'liked';
			$entity_reactions[] = $current_user_id;

			$comment = get_comment( $entity_id );
			$author  = $is_comment_ent && is_object( $comment ) ? $comment->user_id : get_post_field( 'post_author', $entity_id );

			// Dispatch like notification only when liking (not on unlike) && Don't notify if user likes their own post.
			if ( $current_user_id !== absint( $author ) && method_exists( Notifier_Base::get_instance(), 'dispatch_common_notification' ) ) {
				Notifier_Base::get_instance()->dispatch_common_notification(
					'suredashboard_entity_like',
					[
						'caller'    => $current_user_id,
						'entity'    => $entity,
						'entity_id' => $entity_id,
						'author'    => $author,
						'count'     => count( $entity_reactions ),
					]
				);
			}
		}

		if ( $is_comment_ent ) {
			update_comment_meta( $entity_id, 'portal_comment_likes', $entity_reactions );
		} else {
			sd_update_post_meta( $entity_id, 'portal_post_likes', $entity_reactions );
		}

		// Update the post ID to user liked meta data.
		if ( $is_comment_ent ) {
			$user_liked_entities = sd_get_user_meta( $current_user_id, 'portal_user_liked_comments', true );
		} else {
			$user_liked_entities = sd_get_user_meta( $current_user_id, 'portal_user_liked_posts', true );
		}
		$user_liked_entities = is_array( $user_liked_entities ) ? $user_liked_entities : [];

		if ( $like_status === 'liked' ) {
			$user_liked_entities[] = $entity_id;
		} else {
			$user_liked_entities = array_diff( $user_liked_entities, [ $entity_id ] );
		}

		if ( $is_comment_ent ) {
			sd_update_user_meta( $current_user_id, 'portal_user_liked_comments', $user_liked_entities );
		} else {
			sd_update_user_meta( $current_user_id, 'portal_user_liked_posts', $user_liked_entities );
		}

		$response['like_status']          = $like_status;
		$response['like_count']           = count( $entity_reactions );
		$response['reacted_by_user_id']   = $current_user_id;
		$response['reacted_by_user_name'] = get_the_author_meta( 'display_name', $current_user_id );

		// Get list of users who liked the comment.
		$user_list                   = suredash_get_thread_liked_users( $entity_id );
		$response['tooltip_content'] = $user_list['tooltip_content'];

		$entity_type = $is_comment_ent ? 'comment' : 'post';
		do_action( 'suredash_entity_like_reaction', $entity_id, $entity_type, $like_status, $current_user_id );
		wp_send_json_success( $response );
	}

	/**
	 * Search the users based on the search query.
	 * Usecase: User mentions.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @since 0.0.1
	 * @return void
	 */
	public function search_user( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$search = ! empty( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		$users  = new \WP_User_Query(
			[
				'search'         => '*' . esc_attr( $search ) . '*',
				'fields'         => [ 'ID', 'display_name' ],
				'search_columns' => [ 'display_name', 'user_email' ],
				'orderby'        => 'display_name',
				'count_total'    => false,
			]
		);

		$users    = $users->get_results();
		$response = [];

		if ( empty( $users ) ) {
			wp_send_json_success( $response );
		}

		foreach ( $users as $user ) {
			$response[] = [
				'id'           => ! empty( $user->ID ) ? $user->ID : '',
				'display_name' => suredash_get_user_display_name( $user->ID ),
				'avatar'       => ! empty( $user->ID ) ? suredash_get_user_avatar( intval( $user->ID ), false, 24 ) : '',
			];
		}

		wp_send_json_success( $response );
	}

	/**
	 * Get the post reactor data: like/comment
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @since 0.0.1
	 * @return void
	 */
	public function post_reactor_data( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$post_id    = ! empty( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$react_type = ! empty( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : 'like';
		$data_only  = ! empty( $_POST['data_only'] ) ? filter_var( $_POST['data_only'], FILTER_VALIDATE_BOOLEAN ) : false;

		if ( ! $post_id ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'default' ) ] );
		}

		// Handle data_only request for visibility scope.
		if ( $react_type === 'visibility' && current_user_can( 'administrator' ) && $data_only ) {
			$visibility_data = $this->get_visibility_scope_data( $post_id );
			wp_send_json_success( $visibility_data );
		}

		ob_start();

		if ( $react_type === 'like' ) {
			suredash_likes_list_markup( $post_id );
		} elseif ( $react_type === 'comment' ) {
			suredash_comments_markup( $post_id, true, [], 'sd-mt-20 sd-w-full', 'modal' );
		} elseif ( $react_type === 'visibility' && current_user_can( 'administrator' ) ) {
			suredash_visibility_scope_list_markup( $post_id );
		}

		$markup = ob_get_clean();

		wp_send_json_success( [ 'content' => $markup ] );
	}

	/**
	 * Handles the submission of a new comment.
	 *
	 * This function checks if the required data is present and if the user is logged in. If not, it returns an error.
	 * If the data is valid, it creates a new comment using the provided data and generates HTML for the new comment.
	 * The HTML is then sent back to the client as a JSON response.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return void
	 * @since 0.0.1
	 */
	public function submit_comment( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		// Check if required data exists and if the user is logged in.
		if ( empty( $_POST['comment'] ) || empty( $_POST['comment_post_ID'] ) || ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'default' ) ] );
		}

		$comment_data    = stripslashes( $_POST['comment'] ); // phpcs:ignore -- Data is sanitized in the wp_kses_post() method.
		$current_user_id = get_current_user_id();

		// Process uploaded images using common method.
		$image_result    = $this->process_uploaded_images( $comment_data, $current_user_id );
		$comment_data    = $image_result['content'];
		$uploaded_images = $image_result['uploaded_images'];

		// Process iframe placeholders using common method.
		$filtered_comment = $this->process_iframe_placeholders( $comment_data );

		// Collapse 3+ consecutive <br> tags to 2 (max one empty line).
		$filtered_comment = (string) preg_replace_callback(
			'/(<br\s*\/?>[\s]*){3,}/i',
			static function (): string {
				return '<br><br>';
			},
			$filtered_comment
		);

		$comment_parent_id = isset( $_POST['comment_parent'] ) ? absint( $_POST['comment_parent'] ) : 0;
		$depth             = isset( $_POST['depth'] ) ? absint( $_POST['depth'] ) : 0;

		$comment_id = \wp_new_comment( // Prepare comment data.
			[
				'comment_post_ID'      => absint( $_POST['comment_post_ID'] ),
				'comment_author'       => suredash_get_user_display_name(),
				'comment_author_email' => wp_get_current_user()->user_email,
				'comment_content'      => $filtered_comment,
				'comment_type'         => '',
				'comment_author_url'   => '',
				'user_id'              => $current_user_id,
				'comment_parent'       => $comment_parent_id,
			]
		);

		if ( is_wp_error( $comment_id ) ) {
			foreach ( $uploaded_images as $image ) {
				// Delete the uploaded image.
				$upload_dir  = wp_upload_dir();
				$upload_path = $upload_dir['basedir'] . '/suredashboard/' . $current_user_id . '/assets/';
				$upload_url  = $upload_dir['baseurl'] . '/suredashboard/' . $current_user_id . '/assets/';
				$image_path  = str_replace( $upload_url, $upload_path, $image );

				/** This action is documented in inc/compatibility/comment.php */
				do_action( 'suredash_before_file_delete', $image_path, $image );
				unlink( $image_path ); // phpcs:ignore -- This is a safe operation.
			}
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'default' ) ] );
		}

		// Generate HTML for the new comment.
		$comment = \get_comment( absint( $comment_id ) );

		ob_start();
		wp_list_comments(
			[
				'callback' => 'suredash_comments_list_callback',
				'style'    => 'ol',
				'depth'    => $depth + 1,
			],
			[ $comment ] // @phpstan-ignore-line
		);
		$comment_html = ob_get_clean();

		// If a comment is replying to another comment, then dispatch a notification to the parent comment author.
		if ( $comment_parent_id !== 0 ) {
			// Get the comment object.
			$parent_comment = get_comment( $comment_parent_id );

			// Check if the comment exists and has a user ID.
			if ( $parent_comment && $parent_comment->user_id ) {
				// Get the user object.
				$user = get_user_by( 'ID', $parent_comment->user_id );

				// Check if the user exists.
				if ( $user ) {
					// Access user information.
					$parent_comment_user_id = $user->ID;

					if ( method_exists( Notifier_Base::get_instance(), 'dispatch_common_notification' ) ) {
						// Dispatch mentioning notification.
						Notifier_Base::get_instance()->dispatch_common_notification(
							'suredashboard_comment_reply',
							[
								'mentioned_user' => $parent_comment_user_id,
								'comment_id'     => $comment_id,
								'caller'         => $current_user_id,
							]
						);
					}
				}
			}
		}

		// Check if the comment contains any user mentions.
		$filtered_comment = preg_replace_callback(
			'/data-portal_mentioned_user="(\d+)"/',
			// @phpstan-ignore-next-line
			static function( $matches ) use ( $comment_id, $current_user_id ): void {

				$tagged_id  = absint( $matches[1] );
				$comment_id = absint( $comment_id );

				if ( method_exists( Notifier_Base::get_instance(), 'dispatch_user_notification' ) ) {
					// Dispatch mentioning notification.
					Notifier_Base::get_instance()->dispatch_user_notification(
						'suredashboard_user_mentioned',
						[
							'mentioned_user' => $tagged_id,
							'comment_id'     => $comment_id,
							'caller'         => $current_user_id,
						]
					);
				}
			},
			$filtered_comment
		);

		// Dispatch comment submitted notification to the topic author.
		if ( absint( $_POST['comment_post_ID'] ) ) {
			if ( method_exists( Notifier_Base::get_instance(), 'dispatch_common_notification' ) ) {
				Notifier_Base::get_instance()->dispatch_common_notification(
					'suredashboard_topic_comment',
					[
						'comment_id'   => $comment_id,
						'caller'       => $current_user_id,
						'topic_id'     => absint( $_POST['comment_post_ID'] ),
						'topic_author' => get_post_field( 'post_author', absint( $_POST['comment_post_ID'] ) ),
					]
				);
			}
		}

		do_action( 'suredash_after_comment_submit', $comment_id, $current_user_id );
		wp_send_json_success( [ 'comment_html' => $comment_html ] );
	}

	/**
	 * Handler to edit an existing comment.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @since 1.2.0
	 * @return void
	 */
	public function edit_comment( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		// Check if required data exists and if the user is logged in.
		if ( empty( $_POST['comment_id'] ) || empty( $_POST['comment_content'] ) || ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'default' ) ] );
		}

		$comment_id      = absint( $_POST['comment_id'] );
		$comment_content = stripslashes( $_POST['comment_content'] ); // phpcs:ignore -- Data is sanitized in the wp_kses_post() method.
		$current_user_id = get_current_user_id();

		// Get the existing comment.
		$comment = get_comment( $comment_id );
		if ( ! $comment ) {
			wp_send_json_error( [ 'message' => __( 'Comment not found.', 'suredash' ) ] );
		}

		// Check if the current user is the author of the comment.
		if ( absint( $comment->user_id ) !== $current_user_id ) {
			wp_send_json_error( [ 'message' => __( 'You are not authorized to edit this comment.', 'suredash' ) ] );
		}

		// Process uploaded images using common method.
		$image_result    = $this->process_uploaded_images( $comment_content, $current_user_id );
		$comment_content = $image_result['content'];

		// Process iframe placeholders using common method.
		$filtered_comment = $this->process_iframe_placeholders( $comment_content );

		// Collapse 3+ consecutive <br> tags to 2 (max one empty line).
		$filtered_comment = (string) preg_replace_callback(
			'/(<br\s*\/?>[\s]*){3,}/i',
			static function (): string {
				return '<br><br>';
			},
			$filtered_comment
		);

		// Update the comment.
		$result = wp_update_comment(
			[
				'comment_ID'      => $comment_id,
				'comment_content' => $filtered_comment,
			]
		);

		if ( ! $result ) {
			wp_send_json_error( [ 'message' => __( 'Failed to update comment.', 'suredash' ) ] );
		}

		update_comment_meta( $comment_id, 'suredash_comment_edited', current_time( 'mysql' ) );

		$updated_comment = get_comment( $comment_id );

		do_action( 'suredash_after_comment_edited', $comment_id, $current_user_id );
		wp_send_json_success(
			[
				'message' => __( 'Comment Updated Successfully!', 'suredash' ),
				'content' => $updated_comment->comment_content ?? '',
			]
		);
	}

	/**
	 * Delete a comment.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @since 1.2.0
	 * @return void
	 */
	public function delete_comment( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$comment_id = $request->get_param( 'id' );

		if ( empty( $comment_id ) || ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'default' ) ] );
		}

		$comment_id      = absint( $comment_id );
		$current_user_id = get_current_user_id();

		// Get the existing comment.
		$comment = get_comment( $comment_id );
		if ( ! $comment ) {
			wp_send_json_error( [ 'message' => __( 'Comment not found.', 'suredash' ) ] );
		}

		// Check if the current user is the author of the comment or a Portal Manager.
		$comment_author    = absint( $comment->user_id );
		$is_comment_author = $comment_author === $current_user_id;
		$is_portal_manager = function_exists( 'suredash_is_user_manager' ) && suredash_is_user_manager( $current_user_id );

		if ( ! $is_comment_author && ! $is_portal_manager ) {
			wp_send_json_error( [ 'message' => __( 'You are not authorized to delete this comment.', 'suredash' ) ] );
		}

		// Delete the comment (move to trash).
		$result = wp_delete_comment( $comment_id, true ); // false = move to trash, true = permanently delete.

		if ( ! $result ) {
			wp_send_json_error( [ 'message' => __( 'Failed to delete comment.', 'suredash' ) ] );
		}

		do_action( 'suredash_after_comment_deleted', $comment_id, $comment_author );
		wp_send_json_success( [ 'message' => __( 'Comment deleted successfully!', 'suredash' ) ] );
	}

	/**
	 * Edit a post.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @since 1.4.0
	 * @return void
	 */
	public function edit_post( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		// Check if required data exists and if the user is logged in.
		if ( empty( $_POST['post_id'] ) || empty( $_POST['post_title'] ) || empty( $_POST['post_content'] ) || ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'default' ) ] );
		}

		$post_id         = absint( $_POST['post_id'] );
		$post_title      = sanitize_text_field( $_POST['post_title'] );
		$post_content    = stripslashes( $_POST['post_content'] ); // phpcs:ignore -- Data is sanitized in the wp_kses_post() method.
		$current_user_id = get_current_user_id();

		// Handle visibility scope.
		$visibility_scope = [];
		if ( isset( $_POST['edit_post_access_groups'] ) ) {
			// Convert to array if it's a comma-separated string.
			if ( ! is_array( $_POST['edit_post_access_groups'] ) ) {    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below with array_map()
				$visibility_scope = explode( ',', $_POST['edit_post_access_groups'] );  // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below with array_map()
			} else {
				$visibility_scope = $_POST['edit_post_access_groups']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below with array_map()
			}

			// Sanitize each value but keep the prefixes intact.
			$visibility_scope = array_values(
				array_filter(
					array_map( 'sanitize_text_field', $visibility_scope )
				)
			);
		}

		// Get the existing post.
		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error( [ 'message' => __( 'Post not found.', 'suredash' ) ] );
		}
		$is_post_author    = absint( $post->post_author ) === $current_user_id;
		$is_portal_manager = function_exists( 'suredash_is_user_manager' ) && suredash_is_user_manager( $current_user_id );

		// Check if the current user is the author of the post.
		if ( ! $is_post_author && ! $is_portal_manager ) {
			wp_send_json_error( [ 'message' => __( 'You are not authorized to edit this post.', 'suredash' ) ] );
		}

		// Process uploaded images using common method (including cover image).
		$image_result    = $this->process_uploaded_images( $post_content, $current_user_id, 'edit_post_cover_image' );
		$post_content    = $image_result['content'];
		$cover_image_url = $image_result['cover_image_url'];

		// Handle cover image removal.
		$remove_cover_image = ! empty( $_POST['remove_cover_image'] ) && $_POST['remove_cover_image'] === '1';
		if ( $remove_cover_image ) {
			// Delete the existing cover image file.
			$existing_cover_image = sd_get_post_meta( $post_id, 'custom_post_cover_image', true );
			if ( ! empty( $existing_cover_image ) ) {
				$this->delete_cover_image_file( $existing_cover_image );
			}
			// Remove the cover image meta.
			delete_post_meta( $post_id, 'custom_post_cover_image' );
			$cover_image_url = ''; // Ensure we return empty URL.
		} elseif ( ! empty( $cover_image_url ) ) {
			// Update post meta for new cover image.
			update_post_meta( $post_id, 'custom_post_cover_image', $cover_image_url );
		}

		// Process iframe placeholders using common method.
		$filtered_title   = sanitize_text_field( $post_title );
		$filtered_content = $this->process_iframe_placeholders( $post_content );

		// Update the post.
		$result = wp_update_post(
			[
				'ID'           => $post_id,
				'post_title'   => $filtered_title,
				'post_content' => $filtered_content,
			],
			true
		);

		if ( is_wp_error( $result ) || ! $result ) {
			wp_send_json_error( [ 'message' => __( 'Failed to update post.', 'suredash' ) ] );
		}

		update_post_meta( $post_id, 'suredash_post_edited', current_time( 'mysql' ) );

		// Update visibility scope if provided.
		if ( ! empty( $visibility_scope ) ) {
			sd_update_post_meta( $post_id, 'visibility_scope', $visibility_scope );
		} else {
			// If no visibility scope provided, remove the meta (defaults to public).
			delete_post_meta( $post_id, 'visibility_scope' );
		}

		// Get the updated cover image URL if it was changed.
		$updated_cover_image_url = ! empty( $cover_image_url ) ? $cover_image_url : sd_get_post_meta( $post_id, 'custom_post_cover_image', true );

		do_action( 'suredash_after_post_edited', $post_id, $current_user_id );
		wp_send_json_success(
			[
				'message'         => __( 'Post Updated Successfully!', 'suredash' ),
				'post'            => get_post( $post_id ),
				'cover_image_url' => $updated_cover_image_url,
			]
		);
	}

	/**
	 * Delete a post.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @since 1.4.0
	 * @return void
	 */
	public function delete_post( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$post_id = $request->get_param( 'id' );

		if ( empty( $post_id ) || ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'default' ) ] );
		}

		$post_id         = absint( $post_id );
		$current_user_id = get_current_user_id();

		// Get the existing post.
		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error( [ 'message' => __( 'Post not found.', 'suredash' ) ] );
		}

		if ( $post->post_type !== SUREDASHBOARD_FEED_POST_TYPE ) {
			wp_send_json_error( [ 'message' => __( 'Invalid Post.', 'suredash' ) ] );
		}

		// Check if the current user is the author of the post or a Portal Manager.
		$post_author       = absint( $post->post_author );
		$is_post_author    = $post_author === $current_user_id;
		$is_portal_manager = function_exists( 'suredash_is_user_manager' ) && suredash_is_user_manager( $current_user_id );

		if ( ! $is_post_author && ! $is_portal_manager ) {
			wp_send_json_error( [ 'message' => __( 'You are not authorized to delete this post.', 'suredash' ) ] );
		}

		// Delete the post.
		$result = wp_delete_post( $post_id, true ); // false = move to trash, true = permanently delete.

		if ( ! $result ) {
			wp_send_json_error( [ 'message' => __( 'Failed to delete post.', 'suredash' ) ] );
		}

		do_action( 'suredash_after_post_deleted', $post_id, $post_author );
		wp_send_json_success( [ 'message' => __( 'Post deleted successfully!', 'suredash' ) ] );
	}

	/**
	 * Get post data for editing.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return void
	 * @since 1.4.0
	 */
	public function get_post( $request ): void {
		$post_id = absint( $request['id'] );

		if ( ! $post_id ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'invalid_post_id' ) ] );
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'post_not_found' ) ] );
		}

		// Check if user can edit this post.
		$current_user_id   = get_current_user_id();
		$post_author_id    = absint( $post->post_author );
		$is_admin          = current_user_can( 'manage_options' );
		$is_post_author    = $current_user_id === $post_author_id;
		$is_portal_manager = function_exists( 'suredash_is_user_manager' ) && suredash_is_user_manager( $current_user_id );

		if ( ! $is_post_author && ! $is_admin && ! $is_portal_manager ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'permission_denied' ) ] );
		}

		// Get the cover image URL.
		$cover_image_url = sd_get_post_meta( $post_id, 'custom_post_cover_image', true );

		// Get the visibility scope.
		$visibility_scope = sd_get_post_meta( $post_id, 'visibility_scope', true );

		// Return the post data.
		wp_send_json_success(
			[
				'id'               => $post->ID,
				'title'            => $post->post_title,
				'content'          => $post->post_content,
				'status'           => $post->post_status,
				'cover_image_url'  => $cover_image_url,
				'visibility_scope' => $visibility_scope,
			]
		);
	}

	/**
	 * Get unread counts for all spaces for current user.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return void
	 * @since 1.6.0
	 */
	public function get_unread_counts( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$user_id = get_current_user_id();
		if ( empty( $user_id ) ) {
			wp_send_json_error( [ 'message' => __( 'User not logged in.', 'suredash' ) ] );
		}

		$tracker = Activity_Tracker::get_instance();
		$counts  = $tracker->get_all_unread_counts( $user_id );

		wp_send_json_success(
			[
				'counts' => $counts,
			]
		);
	}

	/**
	 * Mark a space as read for current user.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return void
	 * @since 1.6.0
	 */
	public function mark_space_read( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$user_id  = get_current_user_id();
		$space_id = ! empty( $_POST['space_id'] ) ? absint( $_POST['space_id'] ) : 0;

		if ( empty( $user_id ) ) {
			wp_send_json_error( [ 'message' => __( 'User not logged in.', 'suredash' ) ] );
		}

		if ( empty( $space_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Space ID is required.', 'suredash' ) ] );
		}

		$tracker = Activity_Tracker::get_instance();
		$result  = $tracker->mark_space_as_read( $user_id, $space_id );

		if ( $result ) {
			wp_send_json_success(
				[
					'message' => __( 'Space marked as read.', 'suredash' ),
				]
			);
		} else {
			wp_send_json_error( [ 'message' => __( 'Failed to mark space as read.', 'suredash' ) ] );
		}
	}

	/**
	 * Get visibility scope data in lightweight format.
	 * Only returns users since groups are always available on frontend.
	 *
	 * @param int $post_id The post ID.
	 * @return array{users: array<int, array{id: int, name: string, avatar_markup: string}>} Array containing users data.
	 * @since 1.3.0
	 */
	private function get_visibility_scope_data( $post_id ): array {
		$visibility_scope = get_post_meta( $post_id, 'visibility_scope', true );

		if ( empty( $visibility_scope ) || ! is_array( $visibility_scope ) ) {
			return [ 'users' => [] ];
		}

		$users = [];

		foreach ( $visibility_scope as $item ) {
			if ( strpos( $item, 'user-' ) === 0 ) {
				$user_id   = (int) str_replace( 'user-', '', $item );
				$user_data = get_userdata( $user_id );

				if ( $user_data ) {
					$users[] = [
						'id'            => $user_id,
						'name'          => $user_data->display_name,
						'avatar_markup' => suredash_get_user_avatar( $user_id, false, 32 ),
					];
				}
			}
			// Skip groups - frontend already has all groups available.
		}

		return [ 'users' => $users ];
	}

	/**
	 * Common method to handle uploaded images in content.
	 *
	 * @param string $content The content to process.
	 * @param int    $current_user_id The current user ID.
	 * @param string $cover_image_key Optional key for cover image (e.g., 'custom_post_cover_image').
	 * @return array<string, mixed> Array containing processed content and cover image URL.
	 * @since 1.4.0
	 */
	private function process_uploaded_images( string $content, int $current_user_id, string $cover_image_key = '' ): array {
		$dimensions        = apply_filters( 'suredash_comment_image_dimensions', self::DEFAULT_IMAGE_DIMENSIONS );
		$max_file_size     = apply_filters( 'suredash_comment_max_file_size', Helper::get_option( 'user_upload_limit' ) * 1024 * 1024 );
		$allowed_types     = [ 'image/gif', 'image/png', 'image/jpeg', 'image/jpg', 'image/webp' ];
		$uploaded_images   = [];
		$cover_image_url   = '';
		$processed_content = $content;

		foreach ( $_FILES as $key => $file ) {
			if ( $file['error'] === UPLOAD_ERR_OK ) {
				$file['name'] = uniqid( 'image_' ) . '.' . pathinfo( $file['name'], PATHINFO_EXTENSION );

				$this->validate_uploaded_image( $file, $allowed_types, $dimensions, $max_file_size );

				if ( method_exists( Uploader::get_instance(), 'process_media' ) ) {
					$uploaded_url = Uploader::get_instance()->process_media(
						$file['name'],
						$file,
						$current_user_id,
						'assets'
					);

					if ( ! empty( $uploaded_url ) ) {
						$uploaded_images[] = $uploaded_url;

						// Handle inline images with data-image-index.
						if ( preg_match( '/image_(\d+)/', $key, $matches ) ) {
							$image_index       = $matches[1];
							$processed_content = preg_replace_callback(
								'/<img([^>]*?)src=""([^>]*?)data-image-index="' . $image_index . '"([^>]*?)>/',
								static function( $matches ) use ( $uploaded_url, $image_index ) {
									return '<img' . $matches[1] . 'src="' . esc_url( $uploaded_url ) . '"' . $matches[2] . 'data-image-index="' . $image_index . '"' . $matches[3] . '>';
								},
								strval( $processed_content )
							);
						}

						// Handle cover image.
						if ( ! empty( $cover_image_key ) && $key === $cover_image_key ) {
							$cover_image_url = $uploaded_url;
						}
					}
				}
			}
		}

		return [
			'content'         => $processed_content,
			'cover_image_url' => $cover_image_url,
			'uploaded_images' => $uploaded_images,
		];
	}

	/**
	 * Common method to handle iframe placeholders in content.
	 *
	 * @param string $content The content to process.
	 * @return string The processed content with iframe placeholders replaced.
	 * @since 1.4.0
	 */
	private function process_iframe_placeholders( string $content ): string {
		$iframe_placeholders = [];

		$processed_content = preg_replace_callback(
			'/<iframe[^>]*src=["\']([^"\']+)["\'][^>]*><\/iframe>/i',
			function ( $matches ) use ( &$iframe_placeholders ) {
				$src = $matches[1];

				// Validate the iframe src URL.
				if ( $this->validate_iframe_src( $src ) ) {
					$placeholder                         = '[iframe-placeholder-' . count( $iframe_placeholders ) . ']';
					$iframe_placeholders[ $placeholder ] = $matches[0]; // Store the original iframe tag.
					return $placeholder; // Replace iframe with placeholder.
				}

				return '';
			},
			strval( $content )
		);

		$filtered_content = wp_kses_post( strval( $processed_content ) );

		// Replace placeholders with original iframe tags.
		foreach ( $iframe_placeholders as $placeholder => $iframe_tag ) {
			$filtered_content = str_replace( $placeholder, $iframe_tag, $filtered_content );
		}

		return $filtered_content;
	}

	/**
	 * Delete user uploaded media file from filesystem.
	 *
	 * @param int    $user_id    User ID.
	 * @param string $meta_key   Meta key for the media URL.
	 * @return bool True if file was deleted, false otherwise.
	 * @since 1.3.2
	 */
	private function delete_user_media_file( $user_id, $meta_key ) {
		$media_url = sd_get_user_meta( $user_id, $meta_key, true );
		if ( empty( $media_url ) ) {
			return false;
		}

		$upload_dir = wp_upload_dir();
		$file_path  = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $media_url );

		if ( file_exists( $file_path ) ) {
			/** This action is documented in inc/compatibility/comment.php */
			do_action( 'suredash_before_file_delete', $file_path, $media_url );
			wp_delete_file( $file_path );
			return true;
		}

		return false;
	}

	/**
	 * Validate iframe src URL to ensure it is from YouTube or Vimeo.
	 *
	 * @param string $url The iframe src URL.
	 * @return bool True if the URL is valid, false otherwise.
	 */
	private function validate_iframe_src( $url ) {
		$parsed_url = wp_parse_url( $url );

		// Check if the URL has a valid host.
		if ( ! isset( $parsed_url['host'] ) ) {
			return false;
		}

		// List of allowed domains.
		$allowed_domains = [
			'youtube.com',
			'www.youtube.com',
			'youtu.be',
			'vimeo.com',
			'www.vimeo.com',
			'player.vimeo.com',
		];

		// Check if the host matches any of the allowed domains.
		return in_array( $parsed_url['host'], $allowed_domains, true );
	}

	/**
	 * Validate the uploaded image.
	 *
	 * @param array<mixed> $file The uploaded file.
	 * @param array<mixed> $allowed_types The allowed file types.
	 * @param array<mixed> $dimensions The allowed dimensions.
	 * @param int          $max_file_size The maximum file size.
	 *
	 * @return mixed
	 * @since 0.0.6
	 */
	private function validate_uploaded_image( $file, $allowed_types, $dimensions, $max_file_size ) {
		if ( ! in_array( $file['type'], $allowed_types ) ) {
			wp_send_json_error( __( 'Invalid file type. Only GIF, PNG, JPEG, JPG, and WEBP are allowed.', 'suredash' ) );
		}

		$image_info = getimagesize( $file['tmp_name'] );
		if ( $image_info === false ) {
			wp_send_json_error( __( 'Uploaded file is not a valid image.', 'suredash' ) );
		}

		if ( $file['size'] > $max_file_size ) {
			wp_send_json_error( __( 'File size larger than permissible.', 'suredash' ) );
		}

		return null;
	}

	/**
	 * Delete cover image file from filesystem.
	 *
	 * @param string $image_url The image URL to delete.
	 * @return bool True if file was deleted, false otherwise.
	 * @since 1.4.0
	 */
	private function delete_cover_image_file( $image_url ) {
		if ( empty( $image_url ) ) {
			return false;
		}

		$upload_dir = wp_upload_dir();
		$file_path  = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $image_url );

		if ( file_exists( $file_path ) ) {
			/** This action is documented in inc/compatibility/comment.php */
			do_action( 'suredash_before_file_delete', $file_path, $image_url );
			wp_delete_file( $file_path );
			return true;
		}

		return false;
	}
}
