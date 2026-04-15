<?php
/**
 * Post Router Initialize.
 *
 * @package SureDash
 */

namespace SureDashboard\Core\Routers;

use SureDashboard\Core\Models\Controller;
use SureDashboard\Core\Notifier\Base as Notifier_Base;
use SureDashboard\Core\Shortcodes\Content_Header;
use SureDashboard\Core\Shortcodes\Navigation;
use SureDashboard\Inc\Traits\Get_Instance;
use SureDashboard\Inc\Traits\Rest_Errors;
use SureDashboard\Inc\Utils\Helper;
use SureDashboard\Inc\Utils\PostMeta;
use SureDashboard\Inc\Utils\Sanitizer;
use SureDashboard\Inc\Utils\Settings;
use SureDashboard\Inc\Utils\TermMeta;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Post Router.
 */
class Backend {
	use Get_Instance;
	use Rest_Errors;

	/**
	 * Handler to update docs position.
	 *
	 * @param \WP_REST_Request $request  Request object.
	 *
	 * @since 0.0.2
	 * @return void
	 */
	public function update_item_order_by_group( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$term_id        = ! empty( $_POST['list_term_id'] ) ? absint( $_POST['list_term_id'] ) : 0;
		$reordered_data = ! empty( $_POST['items_ordering_data'] ) ? implode( ',', filter_var_array( json_decode( wp_unslash( $_POST['items_ordering_data'] ), true ), FILTER_SANITIZE_NUMBER_INT ) ) : ''; // phpcs:ignore -- Data is sanitized in the filter_var_array() method.

		if ( ! $term_id ) {
			wp_send_json_error( __( 'Invalid term ID.', 'suredash' ) );
		}

		if ( update_term_meta( $term_id, '_link_order', $reordered_data ) ) {
			$this->clear_first_space_option();
			wp_send_json_success( __( 'Successfully updated.', 'suredash' ) );
		}

		wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'default' ) ] );
	}

	/**
	 * Handler to update taxonomy order.
	 *
	 * @param \WP_REST_Request $request  Request object.
	 *
	 * @since 0.0.2
	 * @return void
	 */
	public function update_group_order( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$base_index = ! empty( $_POST['base_index'] ) ? filter_var( wp_unslash( $_POST['base_index'] ), FILTER_SANITIZE_NUMBER_INT ) : 0;

		if ( ! empty( $_POST['taxonomy_ordering_data'] ) ) {
			$decoded_data = json_decode( wp_unslash( $_POST['taxonomy_ordering_data'] ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Data is sanitized further down the line with array_map.

			if ( is_array( $decoded_data ) ) {
				$taxonomy_ordering_data = array_map(
					static function( $item ) {
						return [
							'term_id' => isset( $item['term_id'] ) ? intval( $item['term_id'] ) : 0,
							'order'   => isset( $item['order'] ) ? intval( $item['order'] ) : 0,
						];
					},
					$decoded_data
				);

				foreach ( $taxonomy_ordering_data as $order_data ) {
					/**
					 * In order to account for how WordPress displays parent categories across various pages, we need to ensure that we check whether the parent category's position needs to be adjusted. If the current position of the category is lower than the base index (meaning it shouldn't appear on this page), then there's no need to update it.
					 */
					if ( $base_index > 0 ) {
						$current_position = get_term_meta( $order_data['term_id'], 'group_tax_position', true );
						if ( (int) intval( $current_position ) < (int) $base_index ) {
							continue;
						}
					}

					if ( ! empty( $order_data['term_id'] ) ) {
						update_term_meta( $order_data['term_id'], 'group_tax_position', (int) $order_data['order'] + (int) $base_index );
					}
				}

				do_action( 'portal_taxonomy_order_updated', $taxonomy_ordering_data, $base_index );
			}
		}

		$this->clear_first_space_option();
		wp_send_json_success();
	}

	/**
	 * Update the first space option.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function clear_first_space_option(): void {
		$portal_settings                = Settings::get_suredash_settings();
		$portal_settings['first_space'] = 0;
		Settings::update_suredash_settings( $portal_settings );
	}

	/**
	 * Check if a space with the same name and integration type already exists.
	 *
	 * @since 1.2.0
	 * @param string $space_name Space name.
	 * @param string $integration_type Integration type.
	 * @return bool True if duplicate exists, false otherwise.
	 */
	public function check_duplicate_space( $space_name, $integration_type ) {
		$args = [
			'post_type'      => SUREDASHBOARD_POST_TYPE,
			'post_status'    => [ 'publish', 'draft', 'private' ],
			'title'          => $space_name,
			'posts_per_page' => 1,
			'meta_query'     => [
				[
					'key'     => 'integration',
					'value'   => $integration_type,
					'compare' => '=',
				],
			],
		];

		$query = new \WP_Query( $args );
		return $query->have_posts();
	}

	/**
	 * API endpoint to check for duplicate space name and type.
	 *
	 * @since 1.2.0
	 * @param \WP_REST_Request $request Request object.
	 * @return void
	 */
	public function check_duplicate_space_endpoint( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$space_name       = ! empty( $_POST['space_name'] ) ? sanitize_text_field( wp_unslash( $_POST['space_name'] ) ) : '';
		$integration_type = ! empty( $_POST['integration_type'] ) ? sanitize_text_field( wp_unslash( $_POST['integration_type'] ) ) : '';

		if ( empty( $space_name ) || empty( $integration_type ) ) {
			wp_send_json_error( [ 'message' => __( 'Space name and integration type are required.', 'suredash' ) ] );
		}

		$is_duplicate = $this->check_duplicate_space( $space_name, $integration_type );

		wp_send_json_success(
			[
				'is_duplicate' => $is_duplicate,
				'message'      => $is_duplicate ? __( 'A space with this name and type already exists.', 'suredash' ) : __( 'Space name and type combination is available.', 'suredash' ),
			]
		);
	}

	/**
	 * Create a new category.
	 *
	 * @since 0.0.2
	 * @param string     $category_name Category name.
	 * @param int        $hide_label    Hide label.
	 * @param array<int> $homegrid_spaces Homegrid spaces.
	 * @return int
	 */
	public function create_portal_group( $category_name, $hide_label = 0, $homegrid_spaces = [] ) {
		$term = term_exists( $category_name, SUREDASHBOARD_TAXONOMY );

		if ( is_array( $term ) ) {
			return $term['term_id'];
		}

		$term = \wp_insert_term( $category_name, SUREDASHBOARD_TAXONOMY );

		if ( ! is_wp_error( $term ) ) {
			\update_term_meta( $term['term_id'], 'homegrid_spaces', $homegrid_spaces );
			\update_term_meta( $term['term_id'], 'hide_label', $hide_label );
			return $term['term_id'];
		}

		return 0;
	}

	/**
	 * Update a category.
	 *
	 * @since 0.0.2
	 * @param int        $term_id       Term ID.
	 * @param string     $category_name Category name.
	 * @param int        $hide_label    Hide label.
	 * @param array<int> $homegrid_spaces Homegrid spaces.
	 * @return int
	 */
	public function update_portal_group( $term_id, $category_name, $hide_label = 0, $homegrid_spaces = [] ) {

		$term = \wp_update_term(
			$term_id,
			SUREDASHBOARD_TAXONOMY,
			[
				'name' => $category_name,
				'slug' => sanitize_title( (string) $category_name ),
			]
		);

		if ( ! is_wp_error( $term ) ) {
			// Update homegrid spaces if provided.
			\update_term_meta( $term['term_id'], 'homegrid_spaces', $homegrid_spaces );
			\update_term_meta( $term['term_id'], 'hide_label', $hide_label );
			return $term['term_id'];
		}

		return 0;
	}

	/**
	 * Create a doc with category selected.
	 *
	 * @param \WP_REST_Request $request  Request object.
	 *
	 * @since 0.0.2
	 * @return void
	 */
	public function create_space( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$space_data = ! empty( $_POST['formData'] ) ? Sanitizer::sanitize_meta_data( json_decode( wp_unslash( $_POST['formData'] ), true ), 'metadata' ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Data is sanitized in the Sanitizer::sanitize_meta_data() method.

		// Check if trying to create a collection space without Pro.
		if ( ! empty( $space_data['integration'] ) && $space_data['integration'] === 'collection' && ! suredash_is_pro_active() ) {
			wp_send_json_error( [ 'message' => __( 'Collection spaces are a premium feature. Please upgrade to SureDash Pro to create collection spaces.', 'suredash' ) ] );
		}

		$term_id   = 0;
		$post_attr = [
			'post_title'  => __( 'Untitled', 'suredash' ),
			'post_type'   => SUREDASHBOARD_POST_TYPE,
			'post_status' => 'draft',
			'post_author' => get_current_user_id(),
		];

		if ( is_array( $space_data ) && ! empty( $space_data['category'] ) ) {
			$value = $space_data['category'];

			if ( $value === 'create' ) {
				$custom_category_name = ! empty( $space_data['custom_category_title'] ) ? $space_data['custom_category_title'] : __( 'Untitled', 'suredash' );
				$term_id              = $this->create_portal_group( $custom_category_name );
			} else {
				$term_id = absint( $value );
			}

			if ( ! $term_id ) {
				wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'default' ) ] );
			}
		}

		if ( is_array( $space_data ) && ! empty( $space_data['item_title'] ) ) {
			$post_attr['post_title'] = strval( $space_data['item_title'] );
			$post_name               = sanitize_title( $post_attr['post_title'] );
			$post_attr['post_name']  = strval( $post_name );
		}

		if ( is_array( $space_data ) && ( ! empty( $space_data['space_status'] ) ) ) {
			$post_attr['post_status'] = strval( $space_data['space_status'] );
		}

		// Check for duplicate space name and integration type only for posts_discussion.
		if ( is_array( $space_data ) && ! empty( $space_data['item_title'] ) && ! empty( $space_data['integration'] ) && $space_data['integration'] === 'posts_discussion' ) {
			$space_name       = $post_attr['post_title'];
			$integration_type = $space_data['integration'];

			if ( $this->check_duplicate_space( $space_name, $integration_type ) ) {
				wp_send_json_error( [ 'message' => __( 'A space with this name and type already exists.', 'suredash' ) ] );
			}
		}

		if ( $term_id ) {
			$item_id = sd_wp_insert_post(
				$post_attr,
			);

			if ( is_wp_error( $item_id ) ) {
				wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'default' ) ] );
			}

			// Instead of using 'tax_input' used 'wp_set_post_terms', because 'tax_input' requires 'assign_terms' access to the taxonomy.
			wp_set_post_terms( $item_id, [ absint( $term_id ) ], SUREDASHBOARD_TAXONOMY );

			if ( $item_id ) {
				$this->update_link_order_term( $item_id, $term_id );

				// Set defaults for new separate like/share options for resource_library spaces.
				if ( ! empty( $space_data['integration'] ) && $space_data['integration'] === 'resource_library' ) {
					if ( ! isset( $space_data['show_like_button'] ) ) {
						$space_data['show_like_button'] = false;
					}
					if ( ! isset( $space_data['show_share_button'] ) ) {
						$space_data['show_share_button'] = false;
					}
				}

				foreach ( $space_data as $key => $value ) {
					if ( $key === 'category' || $key === 'item_title' || $key === 'space_status' ) {
						continue;
					}

					if ( $key === 'adding_from_collection' && $value ) {
						$existing_linked_spaces   = sd_get_post_meta( $value, 'collection_space_ids', true );
						$existing_linked_spaces[] = (string) $item_id;
						sd_update_post_meta( $value, 'collection_space_ids', $existing_linked_spaces );
						continue;
					}

					sd_update_post_meta( $item_id, $key, $value );
				}

				// If integration of the space is posts_discussion then create a category for the SUREDASHBOARD_FEED_TAXONOMY with title as the space title, to be used in the feed.
				if ( ! empty( $space_data['integration'] ) && $space_data['integration'] === 'posts_discussion' ) {
					$category_name = $post_attr['post_title'];
					$feed_group_id = $this->create_forum_category( $item_id, $category_name );
					sd_update_post_meta( $item_id, 'feed_group_id', $feed_group_id );
				}

				if ( method_exists( Notifier_Base::get_instance(), 'dispatch_common_notification' ) ) {
					// Dispatch notification for a new space.
					Notifier_Base::get_instance()->dispatch_common_notification( 'suredashboard_new_space', [ 'space_id' => $item_id ] );
				}

				$post_meta = PostMeta::get_all_post_meta_values( $item_id );
				$meta_set  = [
					'post_id'          => $item_id,
					'permalink'        => get_permalink( $item_id, ),
					'post_status'      => get_post_status( $item_id, ),
					'edit_post_link'   => get_edit_post_link( $item_id, '' ),
					'delete_post_link' => get_delete_post_link( $item_id, ),
					'is_restricted'    => suredash_get_post_backend_restriction( $item_id ),
				];

				$post_meta = array_merge( $post_meta, $meta_set );

				wp_send_json_success(
					[
						'space_id'      => $item_id,
						'message'       => $this->get_rest_event_error( 'success' ),
						'edit_doc_link' => get_edit_post_link( $item_id, '' ),
						'meta'          => $post_meta,
					]
				);
			}
		}

		wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'default' ) ] );
	}

	/**
	 * Create a SD group.
	 *
	 * @param int    $space_id      Space ID.
	 * @param string $category_name Category name.
	 *
	 * @since 1.0.0
	 * @return int
	 */
	public function create_forum_category( $space_id, $category_name ) {
		$term = term_exists( $category_name, SUREDASHBOARD_FEED_TAXONOMY );

		if ( is_array( $term ) ) {
			update_term_meta( absint( $term['term_id'] ), 'space_id', $space_id );
			return absint( $term['term_id'] );
		}

		$prefix = apply_filters( 'suredash_posts_forum_prefix', __( 'Forum:', 'suredash' ) );
		$prefix = ! empty( $prefix ) ? $prefix . ' ' : '';

		$term = \wp_insert_term(
			$prefix . $category_name,
			SUREDASHBOARD_FEED_TAXONOMY,
			[
				'description' => sprintf(
					/* translators: %s: space name */
					__( 'This forum group is created for the space: %s', 'suredash' ),
					$category_name
				),
			]
		);

		if ( ! is_wp_error( $term ) ) {
			update_term_meta( absint( $term['term_id'] ), 'space_id', $space_id );
			return absint( $term['term_id'] );
		}

		return 0;
	}

	/**
	 * Update a space.
	 *
	 * @param int $item_id  Item ID.
	 * @param int $term_id  Term ID.
	 *
	 * @since 0.0.5
	 * @return void
	 */
	public function update_link_order_term( $item_id, $term_id ): void {
		$link_order    = get_term_meta( $term_id, '_link_order', true );
		$string_doc_id = (string) $item_id;
		$link_order    = ! empty( $link_order ) ? $link_order . ',' . $string_doc_id : $string_doc_id;
		update_term_meta( $term_id, '_link_order', $link_order );
	}

	/**
	 * Delete a sub-content.
	 *
	 * Usecase: Lesson creation in a course.
	 *
	 * @param \WP_REST_Request $request  Request object.
	 *
	 * @since 0.0.2
	 * @return void
	 */
	public function delete_community_content( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$community_content_data = ! empty( $_POST['subContentData'] ) ? Sanitizer::sanitize_meta_data( json_decode( wp_unslash( $_POST['subContentData'] ), true ), 'metadata' ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Data is sanitized in the Sanitizer::sanitize_meta_data() method.

		$post_id = is_array( $community_content_data ) && ! empty( $community_content_data['post_id'] ) ? absint( $community_content_data['post_id'] ) : 0;

		if ( ! $post_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid post ID.', 'suredash' ) ] );
		}

		$post_type = get_post_type( $post_id );

		if ( $post_type !== SUREDASHBOARD_SUB_CONTENT_POST_TYPE ) {
			wp_send_json_error( [ 'message' => __( 'Invalid post type.', 'suredash' ) ] );
		}

		$deleted = \wp_delete_post( $post_id, true );

		if ( $deleted ) {
			do_action( 'suredash_community_content_deleted', $post_id );
			wp_send_json_success(
				[
					'message' => __( 'Successfully deleted.', 'suredash' ),
				]
			);
		}

		wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'default' ) ] );
	}

	/**
	 * Delete a space.
	 *
	 * Usecase: Lesson creation in a course.
	 *
	 * @param \WP_REST_Request $request  Request object.
	 *
	 * @since 0.0.2
	 * @return void
	 */
	public function delete_space( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$post_id = ! empty( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! $post_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid post ID.', 'suredash' ) ] );
		}

		$post_type = get_post_type( $post_id );

		if ( $post_type !== SUREDASHBOARD_POST_TYPE ) {
			wp_send_json_error( [ 'message' => __( 'Invalid post type.', 'suredash' ) ] );
		}

		$integration = sd_get_post_meta( $post_id, 'integration', true );

		// If space is of posts_discussion type, delete all posts in the forum.
		if ( $integration === 'posts_discussion' ) {
			$forum = absint( sd_get_post_meta( $post_id, 'feed_group_id', true ) );
			if ( $forum ) {
				$forum_posts = sd_get_posts(
					[
						'post_type'      => [ SUREDASHBOARD_FEED_POST_TYPE ],
						'posts_per_page' => -1,
						'tax_query'      => [
							[
								'taxonomy' => SUREDASHBOARD_FEED_TAXONOMY,
								'field'    => 'term_id',
								'terms'    => $forum,
							],
						],
						'select'         => 'ID, post_type',
					]
				);

				if ( ! empty( $forum_posts ) ) {
					foreach ( $forum_posts as $post ) {
						if ( isset( $post['post_type'] ) && $post['post_type'] !== SUREDASHBOARD_FEED_POST_TYPE ) {
							continue;
						}
						\wp_delete_post( $post['ID'] ?? 0, true );
					}
				}

				\wp_delete_term( $forum, SUREDASHBOARD_FEED_TAXONOMY );
			}
		}

		// If space is of course type, delete all lessons.
		if ( $integration === 'course' ) {
			$course_sections = PostMeta::get_post_meta_value( $post_id, 'pp_course_section_loop' );
			$all_lessons_id  = [];
			if ( ! empty( $course_sections ) ) {
				foreach ( $course_sections as $section ) {
					foreach ( $section['section_medias'] as $lesson ) {
						$all_lessons_id[] = $lesson['value'];
					}
				}

				if ( ! empty( $all_lessons_id ) ) {
					foreach ( $all_lessons_id as $lesson_id ) {
						$lesson_post_type = get_post_type( $lesson_id );
						if ( $lesson_post_type !== SUREDASHBOARD_SUB_CONTENT_POST_TYPE ) {
							continue;
						}
						\wp_delete_post( $lesson_id, true );
					}
				}
			}
		}

		$deleted = \wp_delete_post( $post_id, true );

		if ( $deleted ) {
			/**
			 * Fires after a space is saved/created.
			 *
			 * @since 1.4.0
			 * @param int $post_id The space ID.
			 */
			do_action( 'suredash_space_deleted', $post_id );

			wp_send_json_success(
				[
					'message' => __( 'Successfully deleted.', 'suredash' ),
				]
			);
		}

		wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'default' ) ] );
	}

	/**
	 * Create a space group.
	 *
	 * @param \WP_REST_Request $request  Request object.
	 *
	 * @since 0.0.2
	 * @return void
	 */
	public function create_space_group( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$custom_category_name = ! empty( $_POST['category_name'] ) ? sanitize_text_field( wp_unslash( $_POST['category_name'] ) ) : '';
		$homegrid_spaces      = ! empty( $_POST['homegrid_spaces'] ) ? Sanitizer::sanitize_json_data(
			$_POST['homegrid_spaces'], // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Data is sanitized in the Sanitizer::sanitize_json_data() method.
			[
				'id'   => 'integer',
				'name' => 'string',
			],
			false,
			[ 'id' ]
		) : [];

		$hide_label = ! empty( $_POST['hide_label'] ) ? sanitize_text_field( wp_unslash( $_POST['hide_label'] ) ) : '';
		$hide_label = $hide_label === 'true' ? 1 : 0;

		$space_group = $this->create_portal_group( $custom_category_name, $hide_label, $homegrid_spaces );
		$space_group = absint( $space_group );

		$group_meta = TermMeta::get_all_group_meta_values( $space_group );
		$term_meta  = [
			'term_id'          => $space_group,
			'isCategory'       => true,
			'edit_term_link'   => get_edit_term_link( $space_group, SUREDASHBOARD_TAXONOMY ),
			'view_term_link'   => get_term_link( $space_group, SUREDASHBOARD_TAXONOMY ),
			'query_posts'      => [],
			'posts_count'      => 0,
			'delete_term_link' => str_replace( '&amp;', '&', admin_url( wp_nonce_url( 'edit-tags.php?action=delete&taxonomy=' . SUREDASHBOARD_TAXONOMY . "&tag_ID={$space_group}", 'delete-tag_' . $space_group ) ) ),
		];

		$group_meta = array_merge( $group_meta, $term_meta );

		if ( $space_group ) {
			wp_send_json_success(
				[
					'space_group_id' => $space_group,
					'message'        => $this->get_rest_event_error( 'success' ),
					'meta'           => $group_meta,
				]
			);
		}

		wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'default' ) ] );
	}

	/**
	 * Update a space group.
	 *
	 * @param \WP_REST_Request $request  Request object.
	 *
	 * @since 0.0.2
	 * @return void
	 */
	public function update_space_group( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$term_id         = ! empty( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
		$term_name       = ! empty( $_POST['category_name'] ) ? sanitize_text_field( wp_unslash( $_POST['category_name'] ) ) : '';
		$homegrid_spaces = ! empty( $_POST['homegrid_spaces'] ) ? Sanitizer::sanitize_json_data(
			$_POST['homegrid_spaces'], // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Data is sanitized in the Sanitizer::sanitize_json_data() method.
			[
				'id'   => 'integer',
				'name' => 'string',
			],
			true
		) : [];

		$hide_label = ! empty( $_POST['hide_label'] ) ? sanitize_text_field( wp_unslash( $_POST['hide_label'] ) ) : '';
		$hide_label = $hide_label === 'true' ? 1 : 0;
		if ( empty( $term_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid group ID.', 'suredash' ) ] );
		}

		$space_group = $this->update_portal_group( $term_id, $term_name, $hide_label, $homegrid_spaces );
		$space_group = absint( $space_group );

		if ( $space_group ) {
			wp_send_json_success(
				[
					'space_group_id' => $space_group,
					'message'        => $this->get_rest_event_error( 'success' ),
				]
			);
		}

		wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'default' ) ] );
	}

	/**
	 * Delete a space group.
	 *
	 * @param \WP_REST_Request $request  Request object.
	 *
	 * @since 0.0.2
	 * @return void
	 */
	public function delete_space_group( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$term_id = ! empty( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : '';

		if ( empty( $term_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid group ID.', 'suredash' ) ] );
		}

		$deleted = wp_delete_term( $term_id, SUREDASHBOARD_TAXONOMY );

		if ( $deleted ) {
			wp_send_json_success( [ 'message' => __( 'Successfully deleted.', 'suredash' ) ] );
		}

		wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'default' ) ] );
	}

	/**
	 * Get the list of posts for selection.
	 *
	 * @param \WP_REST_Request $request  Request object.
	 *
	 * @since 0.0.2
	 * @return void
	 */
	public function get_posts_list( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$skip_type_check = false;
		$search_string   = ! empty( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
		$post_type       = ! empty( $_POST['post_type'] ) ? sanitize_text_field( wp_unslash( $_POST['post_type'] ) ) : '';
		$per_page        = ! empty( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : null;
		$category_id     = ! empty( $_POST['category_id'] ) ? absint( $_POST['category_id'] ) : 0;
		$taxonomy        = ! empty( $_POST['taxonomy'] ) ? sanitize_text_field( wp_unslash( $_POST['taxonomy'] ) ) : '';
		$order_by        = ! empty( $_POST['orderBy'] ) && sanitize_text_field( $_POST['orderBy'] ) === 'latest' ? true : false;
		$content_type    = ! empty( $_POST['content_type'] ) ? sanitize_text_field( wp_unslash( $_POST['content_type'] ) ) : '';
		$data            = [];
		$result          = [];

		if ( ! empty( $post_type ) ) {
			$post_types = [ $post_type => $post_type ];
		} else {
			$skip_type_check = true;

			$args = [
				'public'   => true,
				'_builtin' => false,
			];

			$output     = 'names'; // names or objects, note names is the default.
			$operator   = 'and'; // also supports 'or' if needed.
			$post_types = get_post_types( $args, $output, $operator );

			$post_types['Posts'] = 'post';
			$post_types['Pages'] = 'page';

			// Remove the portal post type from the list.
			unset( $post_types[ SUREDASHBOARD_POST_TYPE ] );

			// Remove the WordPress default wp_block post type to avoid PHP DB fetching errors.
			unset( $post_types['wp_block'] );
		}

		foreach ( $post_types as $key => $post_type ) {
			// Skip some post types.
			$skip_post_types = apply_filters(
				'suredash_skip_post_types',
				[
					'attachment',
					'product',
					'sc_product',
					'revision',
					'nav_menu_item',
					'astra-advanced-hook',
					'astra_adv_header',
					'elementor_library',
					'brizy_template',
					'course',
					'courses',
					'lesson',
					'llms_membership',
					'tutor_quiz',
					'tutor_assignments',
					'testimonial',
					'frm_display',
					'mec_esb',
					'mec-events',
					'sfwd-assignment',
					'sfwd-essays',
					'sfwd-transactions',
					'sfwd-certificates',
					'sfwd-quiz',
					'e-landing-page',
					'sureforms_form',
					'shop_coupon',
				]
			);
			if ( $skip_type_check && in_array( $post_type, $skip_post_types, true ) ) {
				continue;
			}

			$data = [];

			// Use Helper's static 'search_only_titles' callback to search only in post titles.
			add_filter( 'posts_search', [ Helper::class, 'search_only_titles' ], 10, 2 );

			$query_args = [
				's'              => $search_string,
				'post_type'      => $post_type,
				'posts_per_page' => $per_page,
				'is_tax_query'   => $category_id ? true : false,
				'taxonomy'       => $taxonomy,
				'category'       => $category_id,
			];

			// Filter by integration type if specified (e.g., filter only events).
			if ( ! empty( $content_type ) ) {
				$query_args['meta_query'] = [
					[
						'key'     => 'content_type',
						'value'   => $content_type,
						'compare' => '=',
					],
				];
			}

			$posts = Controller::get_query_post_data( 'Backend_Feeds', $query_args );

			if ( is_array( $posts ) && ! empty( $posts ) ) {

				if ( $order_by ) {
					usort(
						$posts,
						static function( $a, $b ) {
							return strtotime( $b['post_date'] ) - strtotime( $a['post_date'] );
						}
					);
				}

				foreach ( $posts as $post ) {
					$post_id = (int) $post['ID'];

					// Skip WooCommerce checkout, shop, cart, and my account pages.
					if ( $post_type === 'page' && function_exists( 'wc_get_page_id' ) ) {
						$woo_pages = [
							wc_get_page_id( 'checkout' ),
							wc_get_page_id( 'shop' ),
							wc_get_page_id( 'cart' ),
							wc_get_page_id( 'myaccount' ),
						];

						if ( in_array( $post_id, $woo_pages, true ) ) {
							continue;
						}
					}

					$title = $post['post_title'];

					// Check if the post has a parent and append its title.
					if ( isset( $post['post_parent'] ) && ! empty( $post['post_parent'] ) ) {
						$parent_title = get_the_title( $post['post_parent'] );
						$title       .= " ({$parent_title})";
					}

					$id = $post['ID'];

					$item = [
						'label' => $title,
						'value' => $id,
					];

					// For community posts, include content type.
					if ( $post_type === SUREDASHBOARD_SUB_CONTENT_POST_TYPE ) {
						$content_type_meta = sd_get_post_meta( $post_id, 'content_type', true );
						if ( ! empty( $content_type_meta ) ) {
							$item['content_type'] = $content_type_meta;
						}
					}

					$data[] = $item;
				}
			}

			if ( is_array( $data ) && ! empty( $data ) ) {
				$result[] = [
					'label'   => $key,
					'options' => $data,
				];
			}
		}

		// Sort groups: Posts, Pages, Community content, then others.
		usort(
			$result,
			static function( $a, $b ) {
				$order = [
					'Posts'                             => 1,
					'Pages'                             => 2,
					SUREDASHBOARD_SUB_CONTENT_POST_TYPE => 3,
				];
				$a_pos = $order[ $a['label'] ] ?? 999;
				$b_pos = $order[ $b['label'] ] ?? 999;
				return $a_pos - $b_pos;
			}
		);

		wp_reset_postdata();

		// return the result in json.
		wp_send_json_success( $result );
	}

	/**
	 * Get the list of community posts for selection.
	 *
	 * @param \WP_REST_Request $request  Request object.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function get_community_posts_list( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$category = ! empty( $_POST['category_id'] ) ? absint( $_POST['category_id'] ) : 0;

		$posts = Helper::get_community_posts(
			[
				'category' => $category,
			]
		);

		wp_send_json_success( $posts );
	}

	/**
	 * AJAX Handler to update groups position.
	 *
	 * @param \WP_REST_Request $request  Request object.
	 *
	 * @since 0.0.2
	 */
	public function update_group_term( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$object_id    = absint( ! empty( $_POST['object_id'] ) ? $_POST['object_id'] : 0 );
		$term_id      = absint( ! empty( $_POST['list_term_id'] ) ? $_POST['list_term_id'] : 0 );
		$prev_term_id = absint( ! empty( $_POST['prev_term_id'] ) ? $_POST['prev_term_id'] : 0 );

		if ( ! $term_id || ! $object_id ) {
			wp_send_json_error( __( 'Invalid object or term ID.', 'suredash' ) );
		}

		global $wpdb;

		if ( $prev_term_id ) {
			wp_remove_object_terms( $object_id, $prev_term_id, 'portal_group' );
		}

		$terms_added = wp_set_object_terms( $object_id, $term_id, 'portal_group' );

		if ( ! is_wp_error( $terms_added ) ) {
			wp_send_json_success( __( 'Successfully updated.', 'suredash' ) );
		}

		wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'default' ) ] );
	}

	/**
	 * Get the post meta data.
	 *
	 * @param \WP_REST_Request $request  Request object.
	 *
	 * @since 0.0.2
	 * @return void
	 */
	public function get_post_meta( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : '';

		if ( empty( $post_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid post ID.', 'suredash' ) ] );
		}

		$post_meta = PostMeta::get_all_post_meta_values( $post_id );
		$meta_set  = [
			'post_id'          => $post_id,
			'permalink'        => get_permalink( $post_id ),
			'post_status'      => get_post_status( $post_id ),
			'edit_post_link'   => get_edit_post_link( $post_id, '' ),
			'delete_post_link' => get_delete_post_link( $post_id ),
			'is_restricted'    => suredash_get_post_backend_restriction( $post_id ),
		];

		$post_meta = array_merge( $post_meta, $meta_set );

		$meta_set = [
			'post_id'          => $post_id,
			'permalink'        => get_permalink( $post_id, ),
			'post_status'      => get_post_status( $post_id, ),
			'edit_post_link'   => get_edit_post_link( $post_id, '' ),
			'delete_post_link' => get_delete_post_link( $post_id, ),
			'is_restricted'    => suredash_get_post_backend_restriction( $post_id ),
		];

		if ( ! empty( $post_meta['single_post_id'] ) ) {
			$post_meta['wp_post'] = [
				'value' => $post_meta['single_post_id'],
				'label' => get_the_title( $post_meta['single_post_id'] ),
			];
		}

		if ( $post_meta['integration'] === 'single_post' ) {
			$post_meta['comments'] = get_post_field( 'comment_status', $post_meta['post_id'] ) === 'open' ? true : false;
		}

		if ( suredash_is_pro_active() && is_array( $post_meta['pp_course_section_loop'] ) && ! empty( $post_meta['pp_course_section_loop'] ) ) {
			foreach ( $post_meta['pp_course_section_loop'] as $key => $section ) {
				if ( ! empty( $section['section_medias'] ) ) {
					foreach ( $section['section_medias'] as $key2 => $media ) {
						$post_meta['pp_course_section_loop'][ $key ]['section_medias'][ $key2 ]['comment_status']  = sd_get_post_field( $media['value'], 'comment_status' );
						$post_meta['pp_course_section_loop'][ $key ]['section_medias'][ $key2 ]['post_status']     = sd_get_post_field( $media['value'], 'post_status' );
						$post_meta['pp_course_section_loop'][ $key ]['section_medias'][ $key2 ]['lesson_duration'] = sd_get_post_meta( absint( $media['value'] ), 'lesson_duration', true );
					}
				}
			}
		}

		$post_meta = array_merge( $post_meta, $meta_set );

		wp_send_json_success( $post_meta );
	}

	/**
	 * Get the post content.
	 *
	 * @param \WP_REST_Request $request  Request object.
	 *
	 * @since 0.0.2
	 * @return void
	 */
	public function get_post_content( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : '';

		if ( empty( $post_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid post ID.', 'suredash' ) ] );
		}

		$post_data = Helper::get_post_content( $post_id, 'full_content' );
		wp_send_json_success( $post_data );
	}

	/**
	 * Get the group meta data.
	 *
	 * @param \WP_REST_Request $request  Request object.
	 *
	 * @since 0.0.2
	 * @return void
	 */
	public function get_group_meta( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$term_id = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : '';

		if ( empty( $term_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid post ID.', 'suredash' ) ] );
		}

		$term_meta = TermMeta::get_all_group_meta_values( $term_id );

		wp_send_json_success( $term_meta );
	}

	/**
	 * Get the list of internal categories.
	 *
	 * @param \WP_REST_Request $request  Request object.
	 *
	 * @since 0.0.2
	 * @return void
	 */
	public function get_internal_categories_list( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$search_string = ! empty( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
		$post_type     = ! empty( $_POST['post_type'] ) ? sanitize_text_field( wp_unslash( $_POST['post_type'] ) ) : '';
		$data          = [];
		$result        = [];
		$taxonomy      = SUREDASHBOARD_FEED_TAXONOMY;

		$terms = get_terms(
			[
				'taxonomy'   => $taxonomy,
				'orderby'    => 'count',
				'hide_empty' => false,
				'name__like' => $search_string,
			]
		);

		if ( ! is_array( $terms ) || empty( $terms ) ) {
			wp_send_json_error( [ 'message' => __( 'No store categories found.', 'suredash' ) ] );
		}

		$label = $post_type === 'content' ? __( 'Content Groups', 'suredash' ) : __( 'Topic Forums', 'suredash' );

		foreach ( $terms as $term ) {
			$data[] = [
				'label' => $term->name,
				'value' => $term->term_id,
			];
		}

		if ( is_array( $data ) ) {
			$result[] = [
				'label'   => $label,
				'options' => $data,
			];
		}

		wp_reset_postdata();

		// return the result in json.
		wp_send_json_success( $result );
	}

	/**
	 * Get the list of particular internal category's posts.
	 *
	 * @param \WP_REST_Request $request  Request object.
	 *
	 * @since 0.0.2
	 * @return void
	 */
	public function get_internal_category_posts( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$category      = ! empty( $_POST['category'] ) ? absint( $_POST['category'] ) : '';
		$search_string = ! empty( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
		$post_type     = ! empty( $_POST['post_type'] ) ? sanitize_text_field( wp_unslash( $_POST['post_type'] ) ) : '';
		$data          = [];
		$result        = [];

		$taxonomy  = SUREDASHBOARD_FEED_TAXONOMY;
		$post_type = SUREDASHBOARD_FEED_POST_TYPE;

		if ( empty( $category ) ) {
			wp_send_json_error( [ 'message' => __( 'Category Invalid.', 'suredash' ) ] );
		}

		$term   = get_term( $category, $taxonomy );
		$prefix = $post_type === 'content' ? __( 'Content Group -', 'suredash' ) : __( 'Topic Forum -', 'suredash' ); // @phpstan-ignore-line
		$label  = $prefix . ' ' . is_object( $term ) ? $term->name : ''; // @phpstan-ignore-line

		// Use Helper's static 'search_only_titles' callback to search only in post titles.
		add_filter( 'posts_search', [ Helper::class, 'search_only_titles' ], 10, 2 );

		$posts = Controller::get_query_post_data(
			'Backend_Feeds',
			[
				's'              => $search_string,
				'post_type'      => $post_type,
				'posts_per_page' => null,
				'is_tax_query'   => true,
				'taxonomy'       => $taxonomy,
				'category'       => $category,
			]
		);

		if ( is_array( $posts ) && ! empty( $posts ) ) {
			foreach ( $posts as $post ) {
				$title = $post['post_title'];

				// Check if the post has a parent and append its title.
				if ( isset( $post['post_parent'] ) && ! empty( $post['post_parent'] ) ) {
					$parent_title = get_the_title( $post['post_parent'] );
					$title       .= " ({$parent_title})";
				}

				$id = $post['ID'];

				$data[] = [
					'label' => $title,
					'value' => $id,
				];
			}
		}

		if ( is_array( $data ) && ! empty( $data ) ) {
			$result[] = [
				'label'   => $label,
				'options' => $data,
			];
		}

		wp_reset_postdata();

		// return the result in json.
		wp_send_json_success( $result );
	}

	/**
	 * Save the admin settings.
	 *
	 * @param \WP_REST_Request $request  Request object.
	 *
	 * @since 0.0.2
	 * @return void
	 */
	public function save_settings( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$settings = ! empty( $_POST['settings'] ) ? Sanitizer::sanitize_settings_data( json_decode( wp_unslash( $_POST['settings'] ), true ) ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Data is sanitized in the Sanitizer::sanitize_settings_data() method.

		$settings = $this->update_bsf_analytics_settings( $settings );

		if ( ! is_array( $settings ) && empty( $settings ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid settings data.', 'suredash' ) ] );
		}

		if ( is_array( $settings ) ) {
			Settings::update_suredash_settings( $settings );
		}

		wp_send_json_success( [ 'message' => __( 'Settings saved successfully.', 'suredash' ) ] );
	}

	/**
	 * Content Action.
	 *
	 * @param \WP_REST_Request $request  Request object.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function content_action( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$action    = ! empty( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '';
		$post_id   = ! empty( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$post_type = ! empty( $_POST['post_type'] ) ? sanitize_text_field( wp_unslash( $_POST['post_type'] ) ) : '';

		if ( $post_type !== SUREDASHBOARD_FEED_POST_TYPE ) {
			wp_send_json_error( [ 'message' => __( 'Invalid post type.', 'suredash' ) ] );
		}

		if ( empty( $action ) || empty( $post_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid action or post ID.', 'suredash' ) ] );
		}

		$post = sd_get_post( $post_id );

		if ( empty( $post ) || ! is_object( $post ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid post ID.', 'suredash' ) ] );
		}

		$result          = $this->handle_content_action( $action, $post_id, $post, $post_type );
		$updated         = $result['updated'] ?? false;
		$success_message = $result['success_message'] ?? '';
		$error_message   = $result['error_message'] ?? __( 'Failed to update.', 'suredash' );

		if ( $updated ) {

			$response = [
				'message' => $success_message,
			];

			if ( $action === 'duplicate' ) {
				$group = get_the_terms( $updated, SUREDASHBOARD_FEED_TAXONOMY );

				if ( is_array( $group ) ) {
					$group = $group[0];
				}

				$response['post'] = [
					'id'       => $updated,
					'name'     => html_entity_decode( get_the_title( $updated ) ),
					'author'   => [
						'id'   => get_post_field( 'post_author', $updated ),
						'name' => get_the_author_meta( 'display_name', (int) get_post_field( 'post_author', $updated ) ),
					],
					'view_url' => get_permalink( $updated ),
					'edit_url' => get_edit_post_link( $updated, '' ),
					'group'    => [
						'id'   => $group->term_id ?? 0,
						'name' => $group->name ?? '',
					],
					'status'   => get_post_status( $updated ),
				];
			}

			wp_send_json_success( $response );
		}

		wp_send_json_error( [ 'message' => $error_message ] );
	}

	/**
	 * Handle the content action.
	 *
	 * @param string $action   Action to perform.
	 * @param int    $post_id  Post ID.
	 * @param object $post     Post object.
	 * @param string $post_type Post type.
	 *
	 * @since 1.0.0
	 * @return array<string, mixed>
	 */
	public function handle_content_action( $action, $post_id, $post, $post_type ) {

		$success_message = '';
		$error_message   = '';

		switch ( $action ) {
			case 'publish':
				$success_message = __( 'Successfully published.', 'suredash' );
				$error_message   = __( 'Failed to publish.', 'suredash' );
				$updated         = \wp_update_post(
					[
						'ID'          => $post_id,
						'post_status' => 'publish',
					]
				);
				break;
			case 'draft':
				$success_message = __( 'Successfully drafted.', 'suredash' );
				$error_message   = __( 'Failed to draft.', 'suredash' );
				$updated         = \wp_update_post(
					[
						'ID'          => $post_id,
						'post_status' => 'draft',
					]
				);
				break;
			case 'trash':
				$success_message = __( 'Successfully trashed.', 'suredash' );
				$error_message   = __( 'Failed to trash.', 'suredash' );
				$updated         = \wp_trash_post( $post_id );
				break;
			case 'restore':
				$success_message = __( 'Successfully restored.', 'suredash' );
				$error_message   = __( 'Failed to restore.', 'suredash' );
				$updated         = \wp_untrash_post( $post_id );    // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
				break;
			case 'delete':
				$success_message = __( 'Successfully deleted.', 'suredash' );
				$error_message   = __( 'Failed to delete.', 'suredash' );
				$updated         = \wp_delete_post( $post_id, true );
				break;
			case 'duplicate':
				$success_message = __( 'Successfully duplicated.', 'suredash' );
				$error_message   = __( 'Failed to duplicate.', 'suredash' );
				$updated         = \wp_insert_post(
					[
						/* translators: %s: original post title */
						'post_title'     => isset( $post->post_title ) ? sprintf( __( '%s - Copy', 'suredash' ), $post->post_title ) : '',
						'post_type'      => $post_type,
						'post_status'    => 'draft',
						'post_author'    => get_current_user_id(),
						'post_content'   => $post->post_content ?? '',
						'post_excerpt'   => $post->post_excerpt ?? '',
						'comment_status' => $post->comment_status ?? 'open',
						'ping_status'    => $post->ping_status ?? 'open',
						'page_template'  => get_post_meta( $post_id, '_wp_page_template', true ),
					]
				);

				$taxonomies = get_object_taxonomies( $post->post_type ?? '' );
				foreach ( $taxonomies as $taxonomy ) {
					$terms = wp_get_object_terms( $post_id, $taxonomy, [ 'fields' => 'slugs' ] );
					if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
						wp_set_object_terms( $updated, $terms, $taxonomy );
					}
				}

				// Copy featured image.
				$thumbnail_id = get_post_thumbnail_id( $post_id );
				if ( $thumbnail_id ) {
					set_post_thumbnail( $updated, $thumbnail_id );
				}

				// Copy Media Embeds and other post meta.
				$post_meta = get_post_meta( $post_id );
				if ( ! empty( $post_meta ) ) {
					foreach ( $post_meta as $meta_key => $meta_values ) {
						// Skip _wp_page_template as it's already handled.
						if ( $meta_key === '_wp_page_template' || $meta_key === '_thumbnail_id' ) {
							continue;
						}

						foreach ( $meta_values as $meta_value ) {
							add_post_meta( $updated, $meta_key, maybe_unserialize( $meta_value ) );
						}
					}
				}

				break;
			default:
				$updated = false;

		}

		return [
			'updated'         => $updated,
			'success_message' => $success_message,
			'error_message'   => $error_message,
		];
	}

	/**
	 * Update the BSF Analytics settings.
	 *
	 * @param array<string, mixed> $settings Settings data.
	 *
	 * @since 0.0.6
	 * @return array<string, mixed>
	 */
	public function update_bsf_analytics_settings( $settings ) {
		if ( ! is_array( $settings ) || empty( $settings ) ) {
			return $settings;
		}

		$usage_tracking = $settings['usage_tracking'] ? 'yes' : 'no';
		update_option( 'suredash_usage_optin', $usage_tracking );

		return $settings;
	}

	/**
	 * Content Bulk Action.
	 *
	 * @param \WP_REST_Request $request  Request object.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function content_bulk_action( $request ): void {

		$nonce = (string) $request->get_header( 'X-WP-Nonce' );

		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$action   = ! empty( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '';
		$post_ids = ! empty( $_POST['post_ids'] ) ? array_map( 'absint', (array) json_decode( wp_unslash( $_POST['post_ids'] ), true ) ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( empty( $action ) || empty( $post_ids ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid action or post IDs.', 'suredash' ) ] );
		}

		foreach ( $post_ids as $post_id ) {
			$post = sd_post_exists( $post_id );

			if ( ! $post ) {
				continue;
			}

			switch ( $action ) {
				case 'publish':
					wp_update_post(
						[
							'ID'          => $post_id,
							'post_status' => 'publish',
						]
					);
					break;
				case 'draft':
					wp_update_post(
						[
							'ID'          => $post_id,
							'post_status' => 'draft',
						]
					);
					break;
				case 'trash':
					wp_trash_post( $post_id );
					break;
				case 'restore':
					wp_untrash_post( $post_id ); // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
					break;
				case 'delete':
					wp_delete_post( $post_id, true );
					break;
				default:
					break;
			}
		}

		wp_send_json_success( [ 'message' => __( 'Bulk action completed successfully.', 'suredash' ) ] );
	}

	/**
	 * Get dashboard data.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function get_dashboard_data( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$dashboard_data                        = [];
		$dashboard_data['dashboard-data']      = $this->get_member_chart_data();
		$dashboard_data['posts-chart-data']    = $this->get_posts_chart_data();
		$dashboard_data['comments-chart-data'] = $this->get_comments_chart_data();
		$dashboard_data['top_contributors']    = $this->get_top_contributors();
		$dashboard_data['engagement_overview'] = $this->get_engagement_overview();
		$dashboard_data['top_performing']      = $this->get_top_performing_content();

		wp_send_json_success( $dashboard_data );
	}

	/**
	 * Get member stats.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function get_member_stats( $request ): void {

		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$start_date = $request->get_param( 'start_date' );
		$end_date   = $request->get_param( 'end_date' );

		$member_stats = $this->get_member_chart_data( $start_date, $end_date );

		wp_send_json_success( $member_stats );
	}

	/**
	 * Get chart data.
	 *
	 * @param string|null $start_date Start date.
	 * @param string|null $end_date   End date.
	 *
	 * @since 1.0.0
	 * @return array<string, mixed>
	 */
	public function get_member_chart_data( $start_date = null, $end_date = null ) {
		global $wpdb;

		$current_date = new \DateTime();

		// Handle default date range: last 30 days.
		if ( ! $start_date && ! $end_date ) {
			$end_date   = clone $current_date;
			$start_date = clone $current_date;
			$start_date->modify( '-30 days' );
		} else {
			$start_date = $start_date ? new \DateTime( $start_date ) : ( clone $current_date )->modify( '-30 days' );
			$end_date   = $end_date ? new \DateTime( $end_date ) : clone $current_date;
		}

		// Format datetime range.
		$start_date_formatted = $start_date->format( 'Y-m-d 00:00:00' );
		$end_date_formatted   = $end_date->format( 'Y-m-d 23:59:59' );

		// Get daily new member counts (only SureDash Users).
		// phpcs:disable WordPressVIPMinimum.Variables.RestrictedVariables.user_meta__wpdb__users
		$capabilities_key = $wpdb->get_blog_prefix() . 'capabilities';
		$results          = $wpdb->get_results(
			$wpdb->prepare(
				"
				SELECT
					DATE(u.user_registered) AS registered_date,
					COUNT(u.ID) AS new_members
				FROM {$wpdb->users} u
				JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
				WHERE u.user_registered BETWEEN %s AND %s
				AND um.meta_key = %s
				AND um.meta_value LIKE %s
				GROUP BY DATE(u.user_registered)
				ORDER BY DATE(u.user_registered) ASC
				",
				$start_date_formatted,
				$end_date_formatted,
				$capabilities_key,
				'%"suredash_user"%'
			),
			ARRAY_A
		);

		// Total members before start date (only SureDash Users).
		$total_members = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(u.ID) FROM {$wpdb->users} u
				JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
				WHERE u.user_registered < %s
				AND um.meta_key = %s
				AND um.meta_value LIKE %s", // phpcs:ignore
				$start_date_formatted,
				$capabilities_key,
				'%"suredash_user"%'
			)
		);
		// phpcs:enable WordPressVIPMinimum.Variables.RestrictedVariables.user_meta__wpdb__users

		$chart_data = [];
		$daily_map  = [];

		foreach ( $results as $row ) {
			$daily_map[ $row['registered_date'] ] = (int) $row['new_members'];
		}

		$period = new \DatePeriod(
			$start_date,
			new \DateInterval( 'P1D' ),
			$end_date->modify( '+1 day' )
		);

		foreach ( $period as $date ) {
			$date_str       = $date->format( 'Y-m-d' );
			$new_members    = $daily_map[ $date_str ] ?? 0;
			$total_members += $new_members;

			if ( $total_members === 0 && $new_members === 0 ) {
				continue;
			}

			$chart_data[] = [
				'date'          => $date_str,
				'new_members'   => $new_members,
				'total_members' => $total_members,
			];
		}

		return [
			'chart_data'        => $chart_data,
			'total_members'     => $total_members,
			'total_new_members' => array_sum( array_column( $chart_data, 'new_members' ) ),
		];
	}

	/**
	 * Get top contributors.
	 *
	 * @since 1.0.0
	 * @return array<int, array<string, bool|int|string>>
	 */
	public function get_top_contributors() {

		// Get all admin user IDs.
		$admin_ids = get_users(
			[
				'role'   => 'Administrator',
				'fields' => 'ID',
			]
		);

		$post_types = get_post_types(
			[
				'public' => true,
			],
			'names'
		);

		$contributions = [];
		$post_data     = [];
		// Get latest 5 posts from any post type excluding admin authors.
		$latest_posts = sd_get_posts(
			[
				'post_type'      => array_keys( $post_types ),
				'posts_per_page' => 5,
				'post_status'    => 'publish',
				'orderby'        => 'post_date',
				'order'          => 'DESC',
				'author__not_in' => $admin_ids,
				'fields'         => 'all',
				'select'         => '*',
			]
		);

		foreach ( $latest_posts as $post ) {
			$user = get_userdata( absint( $post['post_author'] ?? 0 ) );
			if ( ! $user ) {
				continue;
			}

			$post_data[] = [
				'user_id'          => $user->ID,
				'name'             => suredash_get_user_display_name( $user->ID ),
				'email'            => $user->user_email,
				'type'             => 'post',
				'title_or_content' => $post['post_title'] ?? '',
				'date'             => $post['post_date'] ?? '',
				'post_permalink'   => isset( $post['ID'] ) ? get_permalink( absint( $post['ID'] ) ) : '',
			];

			if ( count( array_filter( $contributions, static fn( $c ) => $c['type'] === 'post' ) ) >= 4 ) {
				break;
			}
		}

		$post_data = array_slice( $post_data, 0, 4 );

		// Get latest 6 comments excluding admin users.
		$comment_data    = [];
		$latest_comments = get_comments(
			[
				'number'    => 15,
				'status'    => 'approve',
				'orderby'   => 'comment_date_gmt',
				'order'     => 'DESC',
				'post_type' => array_keys( $post_types ),
			]
		);

		if ( is_array( $latest_comments ) && ! empty( $latest_comments ) ) {
			foreach ( $latest_comments as $comment ) {

				if ( ! is_object( $comment ) ) {
					continue;
				}

				$user = get_userdata( absint( $comment->user_id ) );
				if ( ! $user ) {
					continue;
				}

				if ( in_array( $comment->user_id, $admin_ids, true ) ) {
					continue;
				}

				$post = sd_get_post( absint( $comment->comment_post_ID ) );

				$comment_data[] = [
					'user_id'          => $user->ID,
					'name'             => suredash_get_user_display_name( $user->ID ),
					'email'            => $user->user_email,
					'type'             => 'comment',
					'comment'          => wp_trim_words( $comment->comment_content, 10 ),
					'title_or_content' => isset( $post->ID ) ? get_the_title( absint( $post->ID ) ) : '',
					'date'             => $comment->comment_date,
					'comment_link'     => get_comment_link( $comment ),
				];

				if ( count( array_filter( $comment_data, static fn( $c ) => $c['type'] === 'comment' ) ) >= 4 ) {
					break;
				}
			}
		}

		$comment_data = array_slice( $comment_data, 0, 4 );

		$contributions = array_merge( $post_data, $comment_data );

		// Sort all by most recent date.
		usort(
			$contributions,
			static function( $a, $b ) {
				return strtotime( $b['date'] ) - strtotime( $a['date'] );
			}
		);

		// Limit to 5 most recent activities.
		return array_slice( $contributions, 0, 5 );
	}

	/**
	 * Get engagement overview stats.
	 *
	 * @since 1.6.0
	 * @return array<string, int>
	 */
	public function get_engagement_overview() {
		global $wpdb;

		// Get total members count (all-time).
		$total_members = (int) $wpdb->get_var(
			"SELECT COUNT(ID) FROM {$wpdb->users}"
		);

		if ( ! empty( $wpdb->last_error ) ) {
			$total_members = 0;
		}

		// Get total posts count (community-post type only).
		$total_posts = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s",
				SUREDASHBOARD_FEED_POST_TYPE,
				'publish'
			)
		);

		if ( ! empty( $wpdb->last_error ) ) {
			$total_posts = 0;
		}

		// Get total comments count (approved only, for specific post types).
		$total_comments = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(c.comment_ID)
				FROM {$wpdb->comments} c
				INNER JOIN {$wpdb->posts} p ON c.comment_post_ID = p.ID
				WHERE c.comment_approved = '1'
				AND p.post_type IN (%s, %s, %s)",
				SUREDASHBOARD_SUB_CONTENT_POST_TYPE,
				SUREDASHBOARD_FEED_POST_TYPE,
				SUREDASHBOARD_SLUG
			)
		);

		if ( ! empty( $wpdb->last_error ) ) {
			$total_comments = 0;
		}

		// Get total reactions (post likes + comment likes).
		// Post likes are stored in post_meta as 'portal_post_likes' (serialized array of user IDs).
		$post_likes_meta = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
				'portal_post_likes'
			),
			ARRAY_A
		);

		if ( ! empty( $wpdb->last_error ) ) {
			$post_likes_meta = [];
		}

		$total_post_likes = 0;
		if ( is_array( $post_likes_meta ) ) {
			foreach ( $post_likes_meta as $meta ) {
				if ( ! empty( $meta['meta_value'] ) ) {
					$likes = maybe_unserialize( $meta['meta_value'] );
					if ( is_array( $likes ) ) {
						$total_post_likes += count( $likes );
					}
				}
			}
		}

		// Comment likes are stored in comment_meta as 'portal_comment_likes' (serialized array of user IDs).
		$comment_likes_meta = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->commentmeta} WHERE meta_key = %s",
				'portal_comment_likes'
			),
			ARRAY_A
		);

		if ( ! empty( $wpdb->last_error ) ) {
			$comment_likes_meta = [];
		}

		$total_comment_likes = 0;
		if ( is_array( $comment_likes_meta ) ) {
			foreach ( $comment_likes_meta as $meta ) {
				if ( ! empty( $meta['meta_value'] ) ) {
					$likes = maybe_unserialize( $meta['meta_value'] );
					if ( is_array( $likes ) ) {
						$total_comment_likes += count( $likes );
					}
				}
			}
		}

		$total_reactions = $total_post_likes + $total_comment_likes;

		return [
			'total_members'   => $total_members,
			'total_posts'     => $total_posts,
			'total_comments'  => $total_comments,
			'total_reactions' => $total_reactions,
		];
	}

	/**
	 * Get top performing content.
	 *
	 * @since 1.6.0
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public function get_top_performing_content() {
		global $wpdb;

		// Get all posts with likes metadata - fetch without sorting to leverage indexes.
		$liked_posts_query = $wpdb->prepare(
			"SELECT p.ID, p.post_title, pm.meta_value as likes
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_type = %s
			AND p.post_status = %s
			AND pm.meta_key = %s",
			SUREDASHBOARD_FEED_POST_TYPE,
			'publish',
			'portal_post_likes'
		);

		$liked_posts_results = $wpdb->get_results( $liked_posts_query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( ! empty( $wpdb->last_error ) ) {
			$liked_posts_results = [];
		}

		// Process and sort in PHP for better performance.
		$posts_with_like_counts = [];
		if ( is_array( $liked_posts_results ) ) {
			foreach ( $liked_posts_results as $post ) {
				$likes      = maybe_unserialize( $post['likes'] );
				$like_count = is_array( $likes ) ? count( $likes ) : 0;

				// Only include posts with at least 1 like.
				if ( $like_count > 0 ) {
					$posts_with_like_counts[] = [
						'id'         => (int) $post['ID'],
						'title'      => $post['post_title'],
						'like_count' => $like_count,
						'permalink'  => get_permalink( (int) $post['ID'] ),
					];
				}
			}
		}

		// Sort by like count in descending order and get top 5.
		usort(
			$posts_with_like_counts,
			static function( $a, $b ) {
				return $b['like_count'] - $a['like_count'];
			}
		);

		$most_liked_posts = array_slice( $posts_with_like_counts, 0, 5 );

		// Get top 5 most commented posts - this query can use indexes effectively.
		$most_commented_query = $wpdb->prepare(
			"SELECT p.ID, p.post_title, COUNT(c.comment_ID) as comment_count
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->comments} c ON p.ID = c.comment_post_ID
			WHERE p.post_type = %s
			AND p.post_status = %s
			AND c.comment_approved = '1'
			GROUP BY p.ID
			ORDER BY comment_count DESC
			LIMIT 5",
			SUREDASHBOARD_FEED_POST_TYPE,
			'publish'
		);

		$most_commented_results = $wpdb->get_results( $most_commented_query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( ! empty( $wpdb->last_error ) ) {
			$most_commented_results = [];
		}

		$most_commented_posts = [];
		if ( is_array( $most_commented_results ) ) {
			foreach ( $most_commented_results as $post ) {
				$most_commented_posts[] = [
					'id'            => (int) $post['ID'],
					'title'         => $post['post_title'],
					'comment_count' => (int) $post['comment_count'],
					'permalink'     => get_permalink( (int) $post['ID'] ),
				];
			}
		}

		return [
			'most_liked'     => $most_liked_posts,
			'most_commented' => $most_commented_posts,
		];
	}

	/**
	 * Get posts stats with chart data.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @since 1.6.0
	 * @return void
	 */
	public function get_posts_stats( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$start_date = $request->get_param( 'start_date' );
		$end_date   = $request->get_param( 'end_date' );

		$posts_stats = $this->get_posts_chart_data( $start_date, $end_date );

		wp_send_json_success( $posts_stats );
	}

	/**
	 * Get comments stats with chart data.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @since 1.6.0
	 * @return void
	 */
	public function get_comments_stats( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$start_date = $request->get_param( 'start_date' );
		$end_date   = $request->get_param( 'end_date' );

		$comments_stats = $this->get_comments_chart_data( $start_date, $end_date );

		wp_send_json_success( $comments_stats );
	}

	/**
	 * Get posts chart data for a given date range.
	 *
	 * @param string|null $start_date Start date.
	 * @param string|null $end_date   End date.
	 *
	 * @since 1.6.0
	 * @return array<string, mixed>
	 */
	public function get_posts_chart_data( $start_date = null, $end_date = null ) {
		global $wpdb;

		$current_date = new \DateTime();

		// Handle default date range: last 30 days.
		if ( ! $start_date && ! $end_date ) {
			$end_date   = clone $current_date;
			$start_date = clone $current_date;
			$start_date->modify( '-30 days' );
		} else {
			$start_date = $start_date ? new \DateTime( $start_date ) : ( clone $current_date )->modify( '-30 days' );
			$end_date   = $end_date ? new \DateTime( $end_date ) : clone $current_date;
		}

		// Format datetime range.
		$start_date_formatted = $start_date->format( 'Y-m-d 00:00:00' );
		$end_date_formatted   = $end_date->format( 'Y-m-d 23:59:59' );

		// Get daily posts count.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					DATE(post_date) AS created_date,
					COUNT(ID) AS posts_count
				FROM {$wpdb->posts}
				WHERE post_type = %s
				AND post_status = %s
				AND post_date BETWEEN %s AND %s
				GROUP BY DATE(post_date)
				ORDER BY DATE(post_date) ASC",
				SUREDASHBOARD_FEED_POST_TYPE,
				'publish',
				$start_date_formatted,
				$end_date_formatted
			),
			ARRAY_A
		);

		$chart_data = [];
		$daily_map  = [];

		foreach ( $results as $row ) {
			$daily_map[ $row['created_date'] ] = (int) $row['posts_count'];
		}

		$period = new \DatePeriod(
			$start_date,
			new \DateInterval( 'P1D' ),
			$end_date->modify( '+1 day' )
		);

		$total_posts = 0;
		foreach ( $period as $date ) {
			$date_str     = $date->format( 'Y-m-d' );
			$posts_count  = $daily_map[ $date_str ] ?? 0;
			$total_posts += $posts_count;

			$chart_data[] = [
				'date'        => $date_str,
				'posts_count' => $posts_count,
			];
		}

		return [
			'chart_data'  => $chart_data,
			'total_posts' => $total_posts,
		];
	}

	/**
	 * Get comments chart data for a given date range.
	 *
	 * @param string|null $start_date Start date.
	 * @param string|null $end_date   End date.
	 *
	 * @since 1.6.0
	 * @return array<string, mixed>
	 */
	public function get_comments_chart_data( $start_date = null, $end_date = null ) {
		global $wpdb;

		$current_date = new \DateTime();

		// Handle default date range: last 30 days.
		if ( ! $start_date && ! $end_date ) {
			$end_date   = clone $current_date;
			$start_date = clone $current_date;
			$start_date->modify( '-30 days' );
		} else {
			$start_date = $start_date ? new \DateTime( $start_date ) : ( clone $current_date )->modify( '-30 days' );
			$end_date   = $end_date ? new \DateTime( $end_date ) : clone $current_date;
		}

		// Format datetime range.
		$start_date_formatted = $start_date->format( 'Y-m-d 00:00:00' );
		$end_date_formatted   = $end_date->format( 'Y-m-d 23:59:59' );

		// Get daily comments count (approved only) for specific post types.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					DATE(c.comment_date) AS created_date,
					COUNT(c.comment_ID) AS comments_count
				FROM {$wpdb->comments} c
				INNER JOIN {$wpdb->posts} p ON c.comment_post_ID = p.ID
				WHERE c.comment_approved = '1'
				AND c.comment_date BETWEEN %s AND %s
				AND p.post_type IN ('community-content', 'community-post', 'portal')
				GROUP BY DATE(c.comment_date)
				ORDER BY DATE(c.comment_date) ASC",
				$start_date_formatted,
				$end_date_formatted
			),
			ARRAY_A
		);

		$chart_data = [];
		$daily_map  = [];

		foreach ( $results as $row ) {
			$daily_map[ $row['created_date'] ] = (int) $row['comments_count'];
		}

		$period = new \DatePeriod(
			$start_date,
			new \DateInterval( 'P1D' ),
			$end_date->modify( '+1 day' )
		);

		$total_comments = 0;
		foreach ( $period as $date ) {
			$date_str        = $date->format( 'Y-m-d' );
			$comments_count  = $daily_map[ $date_str ] ?? 0;
			$total_comments += $comments_count;

			$chart_data[] = [
				'date'           => $date_str,
				'comments_count' => $comments_count,
			];
		}

		return [
			'chart_data'     => $chart_data,
			'total_comments' => $total_comments,
		];
	}

	/**
	 * Update comment status for the post.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function update_comment_status( $request ): void {

		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$post_id        = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : '';
		$comment_status = isset( $_POST['comment_status'] ) ? sanitize_text_field( $_POST['comment_status'] ) : 'closed';

		if ( empty( $post_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid post ID.', 'suredash' ) ] );
		}

		$post_type = get_post_type( $post_id );

		if ( ! in_array( $post_type, [ SUREDASHBOARD_FEED_POST_TYPE, SUREDASHBOARD_SUB_CONTENT_POST_TYPE ], true ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid post type.', 'suredash' ) ] );
		}

		if ( $comment_status === 'closed' ) {
			wp_update_post(
				[
					'ID'             => $post_id,
					'comment_status' => 'closed',
				]
			);
			wp_send_json_success( [ 'message' => __( 'Comments status updated to closed.', 'suredash' ) ] );
		} else {
			wp_update_post(
				[
					'ID'             => $post_id,
					'comment_status' => 'open',
				]
			);
			wp_send_json_success( [ 'message' => __( 'Comments status updated to open.', 'suredash' ) ] );
		}
	}

	/**
	 * Bulk update comment status for posts.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function update_comment_status_bulk( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$post_ids       = isset( $_POST['post_ids'] ) ? json_decode( wp_unslash( $_POST['post_ids'] ), true ) : []; //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$comment_status = isset( $_POST['comment_status'] ) ? sanitize_text_field( wp_unslash( $_POST['comment_status'] ) ) : 'closed';

		if ( empty( $post_ids ) || ! is_array( $post_ids ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid post IDs.', 'suredash' ) ] );
		}

		foreach ( $post_ids as $post_id ) {
			$post_id = absint( $post_id );

			if ( ! sd_post_exists( $post_id ) ) {
				continue;
			}

			$post_type = get_post_type( $post_id );

			if ( $post_type !== SUREDASHBOARD_SUB_CONTENT_POST_TYPE ) {
				continue;
			}

			wp_update_post(
				[
					'ID'             => $post_id,
					'comment_status' => $comment_status,
				]
			);
		}

		wp_send_json_success( [ 'message' => __( 'Comments status updated.', 'suredash' ) ] );
	}

	/**
	 * Get users with pagination, filtering and sorting.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_users( $request ) {
		global $wpdb;

		$per_page = $request->get_param( 'per_page' ) ? (int) $request->get_param( 'per_page' ) : 10;
		$page     = $request->get_param( 'page' ) ? (int) $request->get_param( 'page' ) : 1;
		$search   = $request->get_param( 'search' ) ? sanitize_text_field( $request->get_param( 'search' ) ) : '';
		$order_by = $request->get_param( 'order_by' ) ? sanitize_text_field( $request->get_param( 'order_by' ) ) : 'display_name';
		$order    = $request->get_param( 'order' ) ? sanitize_text_field( $request->get_param( 'order' ) ) : 'ASC';

		// Handle roles parameter - can be comma-separated string or array.
		// Differentiate between no parameter (default to suredash_user) and empty parameter (all roles).
		$roles_param = $request->get_param( 'roles' );
		$roles       = null;

		if ( is_string( $roles_param ) && $roles_param !== '' ) {
			$roles = array_map( 'sanitize_text_field', explode( ',', $roles_param ) );
		} elseif ( is_array( $roles_param ) && ! empty( $roles_param ) ) {
			$roles = array_map( 'sanitize_text_field', $roles_param );
		} else {
			// No roles parameter - default to suredash_user.
			$roles = [ 'suredash_user' ];
		}

		// Validate order_by field to prevent SQL injection.
		$allowed_order_fields = [ 'display_name', 'user_email', 'user_registered', 'ID' ];
		if ( ! in_array( $order_by, $allowed_order_fields ) ) {
			$order_by = 'display_name';
		}

		// Validate order direction.
		if ( ! in_array( strtoupper( $order ), [ 'ASC', 'DESC' ] ) ) {
			$order = 'ASC';
		}

		// Direct SQL query approach for better reliability.
		$capabilities_key = $wpdb->prefix . 'capabilities';

		// Build role conditions for the query.
		// If roles is empty array, don't filter by role (fetch all users except admins).
		$role_where = '';
		if ( ! empty( $roles ) ) {
			$role_conditions = [];
			foreach ( $roles as $role ) {
				$role              = sanitize_text_field( $role );
				$role_conditions[] = $wpdb->prepare( 'um.meta_value LIKE %s', '%"' . $role . '"%' );
			}
			$role_where = 'AND (' . implode( ' OR ', $role_conditions ) . ')';
		}

		// Count total users with the selected roles.
		$total_query = "SELECT COUNT(DISTINCT u.ID)
			FROM {$wpdb->users} u
			JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
			WHERE um.meta_key = '{$capabilities_key}'
			{$role_where}";

		//phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! empty( $search ) ) {
			$search_like = '%' . $wpdb->esc_like( $search ) . '%';
			$total_query = $wpdb->prepare(
				"SELECT COUNT(DISTINCT u.ID)
				FROM {$wpdb->users} u
				JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
				WHERE um.meta_key = %s
				{$role_where}
				AND (
					u.display_name LIKE %s
					OR u.user_email LIKE %s
				)",
				$capabilities_key,
				$search_like,
				$search_like
			);
		}

		$total_users = (int) $wpdb->get_var( $total_query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The query is prepared above.
		if ( $total_users < 0 ) {
			$total_users = 0; // Ensure total users is not negative.
		}

		if ( $total_users === 0 ) {
			return new \WP_REST_Response(
				[
					'success' => true,
					'data'    => [
						'users' => [],
						'total' => 0,
						'pages' => 0,
					],
				],
				200
			);
		}

		$offset = ( $page - 1 ) * $per_page;

		$user_query = $wpdb->prepare(
			"SELECT DISTINCT u.ID, u.user_login, u.user_email, u.display_name, u.user_registered,
			(SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_author = u.ID AND post_type = %s AND post_status IN ('publish', 'draft', 'pending')) AS posts_count,
			(SELECT COUNT(*) FROM {$wpdb->comments} WHERE user_id = u.ID AND comment_approved = '1') AS comments_count
			FROM {$wpdb->users} u
			JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
			WHERE um.meta_key = %s
			{$role_where}",
			SUREDASHBOARD_FEED_POST_TYPE,
			$capabilities_key
		);
		if ( ! empty( $search ) ) {
			$search_like = '%' . $wpdb->esc_like( $search ) . '%';
			$user_query  = $wpdb->prepare(
				"SELECT DISTINCT u.ID, u.user_login, u.user_email, u.display_name, u.user_registered,
				(SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_author = u.ID AND post_type = %s AND post_status IN ('publish', 'draft', 'pending')) AS posts_count,
				(SELECT COUNT(*) FROM {$wpdb->comments} WHERE user_id = u.ID AND comment_approved = '1') AS comments_count
				FROM {$wpdb->users} u
				JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
				WHERE um.meta_key = %s
				{$role_where}
				AND (
					u.display_name LIKE %s
					OR u.user_email LIKE %s
				)",
				SUREDASHBOARD_FEED_POST_TYPE,
				$capabilities_key,
				$search_like,
				$search_like
			);
		}
		//phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$user_query .= " ORDER BY u.{$order_by} {$order}";

		$user_query .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $per_page, $offset );

		$users = $wpdb->get_results( $user_query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The query is prepared above.

		$formatted_users = [];
		if ( ! empty( $users ) ) {
			foreach ( $users as $user ) {
				$user_data = get_userdata( $user->ID );

				if ( $user_data ) {
					$formatted_users[] = [
						'id'                 => (int) $user->ID,
						'name'               => suredash_get_user_display_name( $user->ID ),
						'email'              => $user->user_email,
						'slug'               => $user->user_login,
						'registered_date'    => $user->user_registered,
						'user_banner_image'  => sd_get_user_meta( $user->ID, 'user_banner_image', true ),
						'banner_placeholder' => Helper::get_banner_placeholder_image(),
						'avatar_markup'      => suredash_get_user_avatar( $user->ID, false ),
						'roles'              => $user_data->roles,
						'postsCount'         => (int) $user->posts_count,
						'commentsCount'      => (int) $user->comments_count,
						'badges'             => sd_get_user_meta( $user->ID, 'portal_badges', true ) ? sd_get_user_meta( $user->ID, 'portal_badges', true ) : [],
					];
				}
			}
		}

		$max_pages = ceil( $total_users / $per_page );

		return new \WP_REST_Response(
			[
				'success' => true,
				'data'    => [
					'users' => $formatted_users,
					'total' => (int) $total_users,
					'pages' => $max_pages,
				],
			],
			200
		);
	}

	/**
	 * Get SureMembers access groups.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 * @since 1.6.0
	 */
	public function get_access_groups( $request ) {
		// Get access groups using Helper function.
		$all_access_groups = [];

		if ( class_exists( 'SureDashboard\Inc\Utils\Helper' ) && method_exists( 'SureDashboard\Inc\Utils\Helper', 'get_suremembers_access_groups' ) ) {
			$all_access_groups = Helper::get_suremembers_access_groups();
		}

		// If no access groups found, check if SureMembers is active.
		if ( empty( $all_access_groups ) && ! suredash_is_suremembers_active() ) {
			return new \WP_Error(
				'suremembers_not_active',
				__( 'SureMembers plugin is not active.', 'suredash' ),
				[ 'status' => 404 ]
			);
		}

		return new \WP_REST_Response(
			[
				'success' => true,
				'data'    => [
					'access_groups' => $all_access_groups,
				],
			],
			200
		);
	}

	/**
	 * Get a single user with detailed information.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_user( $request ) {
		$user_id = (int) $request->get_param( 'id' );

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return new \WP_REST_Response(
				[
					'success' => false,
				],
				400
			);
		}

		global $wpdb;

		$posts_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_author = %d AND post_type = %s AND post_status IN ('publish', 'draft', 'pending')",
				$user_id,
				SUREDASHBOARD_FEED_POST_TYPE
			)
		);

		$comments_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->comments} WHERE user_id = %d AND comment_approved = '1'",
				$user_id
			)
		);

		$user_badges = sd_get_user_meta( $user_id, 'portal_badges', true );
		$user_badges = ! empty( $user_badges ) ? $user_badges : [];

		$user_data = [
			'id'                 => $user_id,
			'name'               => suredash_get_user_display_name( $user_id ),
			'username'           => $user->user_login,
			'email'              => $user->user_email,
			'slug'               => $user->user_login,
			'registered_date'    => $user->user_registered,
			'user_banner_image'  => sd_get_user_meta( $user_id, 'user_banner_image', true ),
			'banner_placeholder' => Helper::get_banner_placeholder_image(),
			'avatar_markup'      => suredash_get_user_avatar( $user_id, false ),
			'roles'              => $user->roles,
			'postsCount'         => (int) $posts_count,
			'commentsCount'      => (int) $comments_count,
			'badges'             => $user_badges,
		];

		return new \WP_REST_Response(
			[
				'success' => true,
				'data'    => $user_data,
			],
			200
		);
	}

	/**
	 * Get user posts.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_user_posts( $request ) {
		$user_id  = (int) $request->get_param( 'id' );
		$per_page = $request->get_param( 'per_page' ) ? (int) $request->get_param( 'per_page' ) : 5;

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return new \WP_REST_Response(
				[
					'success' => false,
				],
				400
			);
		}

		$args = [
			'author'         => $user_id,
			'post_type'      => SUREDASHBOARD_FEED_POST_TYPE,
			'post_status'    => [ 'publish', 'draft', 'pending' ],
			'posts_per_page' => $per_page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];

		$query = new \WP_Query( $args );
		$posts = [];

		foreach ( $query->posts as $post ) {
			if ( ! is_object( $post ) ) {
				continue;
			}
			$posts[] = [
				'id'     => $post->ID,
				'title'  => [
					'rendered' => $post->post_title ? $post->post_title : __( '(no title)', 'suredash' ),
				],
				'date'   => $post->post_date,
				'status' => $post->post_status,
				'link'   => get_permalink( $post->ID ),
			];
		}

		return new \WP_REST_Response(
			[
				'success' => true,
				'data'    => $posts,
			],
			200
		);
	}

	/**
	 * Get user comments.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_user_comments( $request ) {
		$user_id  = (int) $request->get_param( 'id' );
		$per_page = $request->get_param( 'per_page' ) ? (int) $request->get_param( 'per_page' ) : 5;

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return new \WP_REST_Response(
				[
					'success' => false,
				],
				400
			);
		}

		$args = [
			'user_id' => $user_id,
			'status'  => 'approve',
			'number'  => $per_page,
			'orderby' => 'comment_date',
			'order'   => 'DESC',
		];

		$comments_query     = new \WP_Comment_Query();
		$comments           = $comments_query->query( $args );
		$formatted_comments = [];

		if ( ! is_array( $comments ) ) {
			return new \WP_REST_Response(
				[
					'success' => true,
					'data'    => [],
				],
				200
			);
		}

		foreach ( $comments as $comment ) {
			$post       = get_post( $comment->comment_post_ID );
			$post_title = $post ? $post->post_title : __( 'Unknown post', 'suredash' );

			$formatted_comments[] = [
				'id'         => $comment->comment_ID,
				'post'       => $comment->comment_post_ID,
				'post_title' => $post_title,
				'date'       => $comment->comment_date,
				'content'    => [
					'rendered' => $comment->comment_content,
				],
				'link'       => get_comment_link( $comment ),
			];
		}

		return new \WP_REST_Response(
			[
				'success' => true,
				'data'    => $formatted_comments,
			],
			200
		);
	}

	/**
	 * Get icon library.
	 *
	 * @return \WP_REST_Response|\WP_Error Icon library data with caching headers.
	 *
	 * @since 1.1.0
	 */
	public function get_icon_library() {
		$icons = Helper::get_icon_library();

		if ( empty( $icons ) ) {
			return new \WP_Error( 'no_icons', __( 'No icons found.', 'suredash' ), [ 'status' => 404 ] );
		}

		$response = rest_ensure_response( $icons );

		// Add caching headers - cache for 1 hour since icons rarely change.
		$response->header( 'Cache-Control', 'public, max-age=3600' );
		$response->header( 'ETag', '"' . md5( (string) wp_json_encode( $icons ) ) . '"' );

		return $response;
	}

	/**
	 * Get the emoji library for the icon picker.
	 *
	 * @return \WP_REST_Response|\WP_Error Response with emojis or error.
	 * @since 1.5.0
	 */
	public function get_emoji_library() {
		$emojis = Helper::get_emoji_library();

		if ( empty( $emojis ) ) {
			return new \WP_Error( 'no_emojis', __( 'No emojis found.', 'suredash' ), [ 'status' => 404 ] );
		}

		$response = rest_ensure_response( $emojis );

		// Add caching headers - cache for 1 hour since emojis rarely change.
		$response->header( 'Cache-Control', 'public, max-age=3600' );
		$response->header( 'ETag', '"' . md5( (string) wp_json_encode( $emojis ) ) . '"' );

		return $response;
	}

	/**
	 * Create a new post and associate with space.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return array<string, mixed>
	 * @since 1.3.2
	 */
	public function create_post_for_space( $request ): array {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			return [
				'success' => false,
				'data'    => [ 'message' => $this->get_rest_event_error( 'nonce' ) ],
			];
		}

		$post_title       = sanitize_text_field( $_POST['post_title'] ?? '' );
		$post_status      = sanitize_text_field( $_POST['post_status'] ?? 'publish' );
		$space_id         = absint( $_POST['space_id'] ?? 0 );
		$belong_to_course = absint( $_POST['belong_to_course'] ?? 0 );
		$forum_category   = absint( $_POST['forum_category'] ?? 0 );
		$context          = sanitize_text_field( $_POST['context'] ?? '' );
		$space_type       = sanitize_text_field( $_POST['space_type'] ?? '' );

		if ( empty( $post_title ) ) {
			return [
				'success' => false,
				'data'    => [ 'message' => __( 'Post title is required.', 'suredash' ) ],
			];
		}

		if ( ! in_array( $post_status, [ 'publish', 'draft' ], true ) ) {
			$post_status = 'publish';
		}

		$post_type = SUREDASHBOARD_SUB_CONTENT_POST_TYPE;
		if ( $forum_category > 0 ) {
			$post_type = SUREDASHBOARD_FEED_POST_TYPE;
		}

		$comment_status = 'open';
		if ( $context === 'resource_library' || $space_type === 'resource_library' || $space_type === 'events' || $context === 'events' ) {
			$comment_status = 'closed';
		}

		$post_data = [
			'post_title'     => $post_title,
			'post_status'    => $post_status,
			'post_type'      => $post_type,
			'post_author'    => get_current_user_id(),
			'post_content'   => '',
			'comment_status' => $comment_status,
		];

		$post_id = wp_insert_post( $post_data );

		if ( ! $post_id ) {
			return [
				'success' => false,
				'data'    => [ 'message' => $this->get_rest_event_error( 'default' ) ],
			];
		}

		// Save post meta based on context and parameters.
		if ( $belong_to_course > 0 ) {
			update_post_meta( $post_id, 'belong_to_course', $belong_to_course );
			update_post_meta( $post_id, 'content_type', 'lesson' );
		}

		if ( $forum_category > 0 ) {
			// For forum posts - set taxonomy terms.
			wp_set_post_terms( $post_id, [ $forum_category ], SUREDASHBOARD_FEED_TAXONOMY );
		}

		if ( $context === 'resource_library' || $space_type === 'resource_library' ) {
			update_post_meta( $post_id, 'content_type', 'resource' );
		}

		if ( $context === 'events' || $space_type === 'events' ) {
			update_post_meta( $post_id, 'content_type', 'event' );
		}

		if ( $space_id > 0 ) {
			$space = get_post( $space_id );
			if ( $space && $space->post_type === SUREDASHBOARD_POST_TYPE ) {
				update_post_meta( $post_id, 'space_id', $space_id );
			}
		}

		// Save content-specific meta fields from creation flow.
		$this->save_content_meta_fields( $post_id, $_POST );

		// Prepare response data with saved meta for immediate display.
		$response_data = [
			'id'         => $post_id,
			'post_id'    => $post_id,
			'title'      => $post_title,
			'post_title' => $post_title,
			'status'     => $post_status,
			'edit_url'   => admin_url( "post.php?post={$post_id}&action=edit" ),
			'space_id'   => $space_id,
		];

		do_action( 'suredash_after_creating_post_content_for_space', $post_id, $space_id, $post_type, $space_type );

		return [
			'success' => true,
			'data'    => $response_data,
		];
	}

	/**
	 * Update content settings via REST API
	 *
	 * @param \WP_REST_Request $request REST API request object.
	 * @return array<string, mixed> Response data.
	 * @since 1.5.0
	 */
	public function update_content_settings( $request ): array {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			return [
				'success' => false,
				'data'    => [ 'message' => $this->get_rest_event_error( 'nonce' ) ],
			];
		}

		$content_id = absint( $_POST['content_id'] ?? 0 );

		if ( ! $content_id ) {
			return [
				'success' => false,
				'data'    => [ 'message' => __( 'Content ID is required.', 'suredash' ) ],
			];
		}

		// Verify post exists.
		$post = get_post( $content_id );
		if ( ! $post ) {
			return [
				'success' => false,
				'data'    => [ 'message' => __( 'Content not found.', 'suredash' ) ],
			];
		}

		// Update post title and status if provided.
		$post_title  = sanitize_text_field( $_POST['post_title'] ?? '' );
		$post_status = sanitize_text_field( $_POST['post_status'] ?? '' );

		$update_data = [ 'ID' => $content_id ];

		if ( ! empty( $post_title ) ) {
			$update_data['post_title'] = $post_title;
		}

		if ( ! empty( $post_status ) && in_array( $post_status, [ 'publish', 'draft' ], true ) ) {
			$update_data['post_status'] = $post_status;
		}

		// Save meta fields BEFORE updating post status.
		// This ensures that if we're publishing a draft, the meta updates happen.
		// while the post is still in draft status, preventing duplicate email triggers.
		$this->save_content_meta_fields( $content_id, $_POST );

		// Update post title and status.
		if ( count( $update_data ) > 1 ) {
			wp_update_post( $update_data );
		}

		$response_data = [
			'message' => __( 'Settings updated successfully.', 'suredash' ),
		];

		/**
		 * Filter the update-content-settings response data.
		 *
		 * @param array $response_data Response data array.
		 * @param int   $content_id    The content post ID.
		 */
		$response_data = apply_filters( 'suredash_update_content_settings_response', $response_data, $content_id );

		return [
			'success' => true,
			'data'    => $response_data,
		];
	}

	/**
	 * Save content-specific meta fields
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $data    POST data array.
	 * @return void
	 * @since 1.5.0
	 */
	private function save_content_meta_fields( int $post_id, array $data ): void {
		// Lesson meta fields.
		if ( isset( $data['lesson_duration'] ) ) {
			update_post_meta( $post_id, 'lesson_duration', sanitize_text_field( $data['lesson_duration'] ) );
		}

		// Resource meta fields.
		if ( isset( $data['resource_type'] ) ) {
			update_post_meta( $post_id, 'resource_type', sanitize_text_field( $data['resource_type'] ) );
		}
		if ( isset( $data['attachment_id'] ) ) {
			update_post_meta( $post_id, 'attachment_id', absint( $data['attachment_id'] ) );
		}
		if ( isset( $data['attachment_name'] ) ) {
			update_post_meta( $post_id, 'attachment_name', sanitize_text_field( $data['attachment_name'] ) );
		}
		if ( isset( $data['external_url'] ) ) {
			update_post_meta( $post_id, 'external_url', esc_url_raw( $data['external_url'] ) );
		}

		// Event meta fields.
		if ( isset( $data['event_date'] ) ) {
			update_post_meta( $post_id, 'event_date', sanitize_text_field( $data['event_date'] ) );
		}
		if ( isset( $data['event_start_time'] ) ) {
			update_post_meta( $post_id, 'event_start_time', sanitize_text_field( $data['event_start_time'] ) );
		}
		if ( isset( $data['event_duration'] ) ) {
			update_post_meta( $post_id, 'event_duration', sanitize_text_field( $data['event_duration'] ) );
		}
		if ( isset( $data['event_timezone'] ) ) {
			update_post_meta( $post_id, 'event_timezone', sanitize_text_field( $data['event_timezone'] ) );
		}
		if ( isset( $data['rsvp_link'] ) ) {
			update_post_meta( $post_id, 'rsvp_link', esc_url_raw( $data['rsvp_link'] ) );
		}
		if ( isset( $data['event_joining_link'] ) ) {
			update_post_meta( $post_id, 'event_joining_link', esc_url_raw( $data['event_joining_link'] ) );
		}
		if ( isset( $data['recorded_video_link'] ) ) {
			update_post_meta( $post_id, 'recorded_video_link', esc_url_raw( $data['recorded_video_link'] ) );
		}
		if ( isset( $data['visibility_scope'] ) ) {
			// Follow same pattern as misc.php - convert comma-separated string to sanitized array.
			$visibility_scope = $data['visibility_scope'];
			if ( ! is_array( $visibility_scope ) ) {
				$visibility_scope = explode( ',', $visibility_scope );
			}
			// Sanitize each value but keep the prefixes intact.
			$visibility_scope = array_values(
				array_filter(
					array_map( 'sanitize_text_field', $visibility_scope )
				)
			);
			update_post_meta( $post_id, 'visibility_scope', $visibility_scope );
		}
		if ( isset( $data['custom_post_cover_image'] ) ) {
			update_post_meta( $post_id, 'custom_post_cover_image', sanitize_text_field( $data['custom_post_cover_image'] ) );
		}
		if ( isset( $data['custom_post_embed_media'] ) ) {
			update_post_meta( $post_id, 'custom_post_embed_media', sanitize_text_field( $data['custom_post_embed_media'] ) );
		}
	}

}
