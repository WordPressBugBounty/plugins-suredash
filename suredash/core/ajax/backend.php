<?php
/**
 * Backend AJAX.
 *
 * @package SureDash
 * @since 1.0.0
 */

namespace SureDashboard\Core\Ajax;

use SureDashboard\Inc\Traits\Ajax;
use SureDashboard\Inc\Traits\Get_Instance;
use SureDashboard\Inc\Utils\Sanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * This class setup all admin AJAX action
 *
 * @class Ajax
 */
class Backend {
	use Ajax;
	use Get_Instance;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->ajax_events = [
			'update_a_space',
			'update_a_space_group',
		];

		$this->initiate_ajax_events();
		$this->create_ajax_nonces();
	}

	/**
	 * Update a item with dataset forwarded.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function update_a_space(): void {
		if ( ! check_ajax_referer( 'portal_update_a_space', 'security', false ) ) {
			wp_send_json_error( [ 'message' => $this->get_ajax_event_error( 'nonce' ) ] );
		}

		// Check if user is a Portal Manager.
		if ( ! suredash_is_user_manager() ) {
			wp_send_json_error( [ 'message' => $this->get_ajax_event_error( 'permission' ) ] );
		}

		$post_id   = ! empty( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$post_data = ! empty( $_POST['formData'] ) ? Sanitizer::sanitize_meta_data( json_decode( wp_unslash( $_POST['formData'] ), true ), 'metadata' ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Data is sanitized in the Sanitizer::sanitize_meta_data() method.
		if ( empty( $post_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid item ID.', 'suredash' ) ] );
		}
		if ( $post_id !== 0 && is_array( $post_data ) ) {
			// Get old space data before updating for comparison.
			$old_space_data  = [
				'integration'            => sd_get_post_meta( $post_id, 'integration', true ),
				'pp_course_section_loop' => sd_get_post_meta( $post_id, 'pp_course_section_loop', true ),
				'resource_ids'           => sd_get_post_meta( $post_id, 'resource_ids', true ),
				'event_ids'              => sd_get_post_meta( $post_id, 'event_ids', true ),
			];
			$deleted_lessons = [];
			if ( isset( $post_data['deletedLessons'] ) && is_array( $post_data['deletedLessons'] ) ) {
				$deleted_lessons = array_map( 'absint', $post_data['deletedLessons'] );
				unset( $post_data['deletedLessons'] );
			}
			foreach ( $post_data as $key => $value ) {
				// Skipping post status as its managed with space_status.
				if ( $key === 'post_status' ) {
					continue;
				}
				switch ( $key ) {
					case 'post_title':
						$post_name = sanitize_title( (string) $value );
						wp_update_post(
							[
								'ID'         => $post_id,
								'post_title' => $value,
								'post_name'  => $post_name,
							]
						);
						break;
					case 'space_status':
						wp_update_post(
							[
								'ID'          => $post_id,
								'post_status' => $value,
							]
						);
						break;
					case 'comments':
						update_post_meta( $post_id, (string) $key, $value );
						$comments_status = boolval( $value ) ? 'open' : 'closed';
						wp_update_post(
							[
								'ID'             => $post_id,
								'comment_status' => $comments_status,
							]
						);
						break;
					case 'image_url':
						update_post_meta( $post_id, (string) $key, $value );
						if ( ! empty( $value ) ) {
							$attachment_id = attachment_url_to_postid( (string) $value );
							update_post_meta( $post_id, '_thumbnail_id', $attachment_id );
						} else {
							// Clear the thumbnail ID when image_url is cleared.
							delete_post_meta( $post_id, '_thumbnail_id' );
						}
						break;
					case 'banner_url':
						update_post_meta( $post_id, (string) $key, $value );
						$attachment_id = attachment_url_to_postid( (string) $value );
						if ( $attachment_id ) {
							update_post_meta( $post_id, '_banner_id', $attachment_id );
						}
						break;
					case 'wp_post':
						if ( ! empty( $value['value'] ) ) {
							update_post_meta(
								$post_id,
								'wp_post',
								[
									'value' => $value['value'],
									'label' => get_the_title( $value['value'] ) ?? '',
								]
							);
						}
						break;
					default:
						// Block writes to WordPress-internal meta keys (prefixed with _) to prevent abuse.
						if ( strpos( (string) $key, '_' ) === 0 || strpos( (string) $key, 'wp_' ) === 0 ) {
							break;
						}
						update_post_meta( $post_id, (string) $key, $value );
						break;
				}
			}

			// Remove any lessons the user marked for deletion.
			if ( ! empty( $deleted_lessons ) ) {
				foreach ( $deleted_lessons as $lesson_id ) {
					if ( get_post_type( $lesson_id ) === SUREDASHBOARD_SUB_CONTENT_POST_TYPE ) {
						\wp_delete_post( $lesson_id, true );
					}
				}
			}

			// Get new space data after updates for comparison.
			$new_space_data = [
				'integration'            => sd_get_post_meta( $post_id, 'integration', true ),
				'pp_course_section_loop' => sd_get_post_meta( $post_id, 'pp_course_section_loop', true ),
				'resource_ids'           => sd_get_post_meta( $post_id, 'resource_ids', true ),
				'event_ids'              => sd_get_post_meta( $post_id, 'event_ids', true ),
			];

			do_action( 'suredash_space_data_updated', $post_id, $old_space_data, $new_space_data );

			/**
			 * Fires after a space is saved/created.
			 *
			 * @since 1.4.0
			 * @param int   $post_id    The space ID.
			 * @param array $post_data The space data.
			 */
			do_action( 'suredash_space_saved', $post_id, $post_data );

			wp_send_json_success(
				[
					'message' => $this->get_ajax_event_error( 'success' ),
				]
			);
		}
	}

	/**
	 * Update a group with dataset forwarded.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function update_a_space_group(): void {
		if ( ! check_ajax_referer( 'portal_update_a_space_group', 'security', false ) ) {
			wp_send_json_error( [ 'message' => $this->get_ajax_event_error( 'nonce' ) ] );
		}

		// Check if user is a Portal Manager.
		if ( ! suredash_is_user_manager() ) {
			wp_send_json_error( [ 'message' => $this->get_ajax_event_error( 'permission' ) ] );
		}

		$term_id   = ! empty( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
		$term_data = ! empty( $_POST['formData'] ) ? Sanitizer::sanitize_meta_data( json_decode( wp_unslash( $_POST['formData'] ), true ), 'metadata' ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Data is sanitized in the Sanitizer::sanitize_meta_data() method.
		if ( empty( $term_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid group ID.', 'suredash' ) ] );
		}
		if ( $term_id !== 0 && is_array( $term_data ) ) {
			foreach ( $term_data as $key => $value ) {
				switch ( $key ) {
					case 'term_name':
						$term_slug = sanitize_title( (string) $value );
						wp_update_term(
							$term_id,
							SUREDASHBOARD_TAXONOMY,
							[
								'name' => $value,
								'slug' => $term_slug,
							]
						);
						break;
					case 'term_description':
						wp_update_term(
							$term_id,
							SUREDASHBOARD_TAXONOMY,
							[
								'description' => (string) $value,
							]
						);
						break;
					default:
						update_term_meta( $term_id, (string) $key, $value );
						break;
				}
			}

			wp_send_json_success(
				[
					'message' => $this->get_ajax_event_error( 'success' ),
				]
			);
		}
	}
}
