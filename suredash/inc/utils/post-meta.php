<?php
/**
 * Portals PostMeta Initialize.
 *
 * @package SureDash
 */

namespace SureDashboard\Inc\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class PostMeta.
 *
 * @since 1.0.0
 */
class PostMeta {
	/**
	 * Get Post Dataset.
	 *
	 * @since 1.0.0
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_post_dataset() {
		return apply_filters(
			'suredashboard_post_meta_dataset',
			[
				'post_title'              => [
					'default' => '',
					'type'    => 'string',
				],
				'item_emoji'              => [
					'default' => 'Link',
					'type'    => 'string',
				],
				'integration'             => [
					'default' => 'none',
					'type'    => 'string',
				],
				'post_content_type'       => [
					'default' => 'excerpt',
					'type'    => 'string',
				],
				'image_url'               => [
					'default' => '',
					'type'    => 'string',
				],
				'banner_url'              => [
					'default' => '',
					'type'    => 'string',
				],
				'course_thumbnail_url'    => [
					'default' => '',
					'type'    => 'string',
				],
				'space_description'       => [
					'default' => '',
					'type'    => 'string',
				],
				'image_id'                => [
					'default' => '',
					'type'    => 'string',
				],
				'link_url'                => [
					'default' => '',
					'type'    => 'string',
				],
				'link_target'             => [
					'default' => '_blank',
					'type'    => 'string',
				],
				'layout'                  => [
					'default' => 'global',
					'type'    => 'string',
				],
				'layout_style'            => [
					'default' => 'global',
					'type'    => 'string',
				],
				'wp_post'                 => [
					'default' => [
						'label' => '',
						'value' => '',
					],
					'type'    => 'array',
				],
				'pp_render_type'          => [
					'default' => 'single',
					'type'    => 'string',
				],
				'pp_media'                => [
					'default' => [],
					'type'    => 'array',
				],
				'pp_media_list'           => [
					'default' => [],
					'type'    => 'array',
				],
				'pp_media_sections'       => [
					'default' => 1,
					'type'    => 'integer',
				],
				'pp_course_section_loop'  => [
					'default' => [
						[
							'section_title'  => __( 'Untitled Section', 'suredash' ),
							'section_medias' => [],
						],
					],
					'type'    => 'array',
				],
				'comments'                => [
					'default' => false,
					'type'    => 'boolean',
				],
				'space_status'            => [
					'default' => 'publish',
					'type'    => 'string',
				],

				// Following metadata for accepting custom topic post.
				'custom_post_title'       => [
					'default' => '',
					'type'    => 'string',
				],
				'custom_post_content'     => [
					'default' => '',
					'type'    => 'html',
				],
				'custom_post_tax_id'      => [
					'default' => '',
					'type'    => 'integer',
				],
				'custom_post_cover_image' => [
					'default' => '',
					'type'    => 'url',
				],
				'custom_post_embed_media' => [
					'default' => '',
					'type'    => 'url',
				],
				'post_render_type'        => [
					'default' => 'blank',
					'type'    => 'string',
				],
				'single_post_id'          => [
					'default' => '',
					'type'    => 'string',
				],
				'allow_members_to_post'   => [
					'default' => false,
					'type'    => 'boolean',
				],
				'feed_group_id'           => [
					'default' => 0,
					'type'    => 'integer',
				],
				'pinned_posts'            => [
					'default' => [],
					'type'    => 'array',
				],
				'hidden_space'            => [
					'default' => false,
					'type'    => 'boolean',
				],
				'hide_from_search'        => [
					'default' => false,
					'type'    => 'boolean',
				],

				'content_layout_style'    => [
					'default' => 'list',
					'type'    => 'string',
				],
				'show_like_button'        => [
					'default' => true,
					'type'    => 'boolean',
				],
				'show_share_button'       => [
					'default' => true,
					'type'    => 'boolean',
				],
				'default_list_view'       => [
					'default' => false,
					'type'    => 'boolean',
				],
				'space_sidebar_widgets'   => [
					'default' => [
						'global_sidebar_enabled' => true,
						'widgets'                => [],
					],
					'type'    => 'array',
				],
				'visibility_scope'        => [
					'default' => [],
					'type'    => 'array',
				],
			]
		);
	}

	/**
	 * Get Post Meta type.
	 *
	 * @since 1.0.0
	 * @param string $meta_key Meta key.
	 * @return string Meta type.
	 */
	public static function get_post_meta_type( $meta_key ) {
		$dataset = self::get_post_dataset();

		if ( ! empty( $dataset[ $meta_key ]['type'] ) ) {
			return $dataset[ $meta_key ]['type'];
		}

		return 'string';
	}

	/**
	 * Get Post Meta Default Value.
	 *
	 * @since 1.0.0
	 * @param string $meta_key Meta key.
	 * @return mixed
	 */
	public static function get_post_meta_default_value( $meta_key ) {
		$dataset = self::get_post_dataset();

		if ( isset( $dataset[ $meta_key ]['default'] ) ) {
			return $dataset[ $meta_key ]['default'];
		}

		return '';
	}

	/**
	 * Get Post Meta Value.
	 *
	 * @since 1.0.0
	 * @param int    $post_id Post ID.
	 * @param string $meta_key Meta key.
	 * @return mixed
	 */
	public static function get_post_meta_value( $post_id, $meta_key ) {
		if ( $meta_key === 'post_title' ) {
			$meta_value = get_the_title( $post_id );
		} elseif ( $meta_key === 'comments' ) {
			$meta_value = comments_open( $post_id ) && sd_get_post_meta( $post_id, $meta_key, true ) ? true : false;
		} elseif ( $meta_key === 'space_status' ) {
			$meta_value = get_post_status( $post_id );
		} elseif ( $meta_key === 'pp_course_section_loop' ) {
			$meta_value = sd_get_post_meta( $post_id, $meta_key, true );
			if ( is_array( $meta_value ) ) {
				$meta_value = self::refresh_course_lesson_titles( $meta_value );
			}
		} else {
			if ( metadata_exists( 'post', $post_id, $meta_key ) ) {
				$meta_value = sd_get_post_meta( $post_id, $meta_key, true );
			} else {
				$meta_value = null;
			}
		}

		$dataset = self::get_post_dataset();

		if ( $meta_value === null ) {
			$meta_value = self::get_post_meta_default_value( $meta_key );
		} elseif ( empty( $meta_value ) && isset( $dataset[ $meta_key ]['type'] ) && $dataset[ $meta_key ]['type'] === 'boolean' ) {
			$meta_value = false;
		}

		return apply_filters( 'suredashboard_post_meta_value', $meta_value, $post_id, $meta_key );
	}

	/**
	 * Check if a space has sidebar widgets enabled (either space-level or global).
	 *
	 * @since 1.6.0
	 * @param int $post_id The post/space ID to check.
	 * @return bool True if sidebar widgets should be displayed, false otherwise.
	 */
	public static function space_has_sidebar_widgets( $post_id ): bool {
		// Only check for portal space post type.
		$post_type = get_post_type( $post_id );
		if ( $post_type !== SUREDASHBOARD_POST_TYPE ) {
			return false;
		}

		// Check if space is restricted/protected.
		if ( function_exists( 'suredash_is_post_protected' ) && suredash_is_post_protected( $post_id ) ) {
			return false;
		}

		// Get space sidebar widgets from meta.
		$sidebar_widgets = sd_get_post_meta( $post_id, 'space_sidebar_widgets', true );

		// Check if space has its own widgets.
		$has_space_widgets = ! empty( $sidebar_widgets ) && ! empty( $sidebar_widgets['widgets'] );

		// Check if global sidebar is enabled for this space (defaults to true if not set).
		$global_sidebar_enabled = isset( $sidebar_widgets['global_sidebar_enabled'] )
			? (bool) $sidebar_widgets['global_sidebar_enabled']
			: true;

		// Get global widgets from settings.
		$global_widgets_configured = Helper::get_option( 'global_sidebar_widgets', [] );
		$has_global_widgets        = ! empty( $global_widgets_configured ) && is_array( $global_widgets_configured );

		// Has sidebar if: space has widgets OR (global sidebar is enabled AND global widgets are configured).
		return $has_space_widgets || ( $global_sidebar_enabled && $has_global_widgets );
	}

	/**
	 * Get All Post Meta with values.
	 *
	 * @param int $post_id Post ID.
	 * @since 1.0.0
	 * @return array<string, mixed>
	 */
	public static function get_all_post_meta_values( $post_id ) {
		$dataset = self::get_post_dataset();

		$meta_values = [];

		foreach ( $dataset as $meta_key => $meta_data ) {
			$meta_values[ $meta_key ] = self::get_post_meta_value( $post_id, $meta_key );
		}

		return $meta_values;
	}

	/**
	 * Get All Post Meta with default values.
	 *
	 * @since 1.0.0
	 * @return array<string, mixed>
	 */
	public static function get_all_post_meta_default_values() {
		$dataset = self::get_post_dataset();

		$meta_values = [];

		foreach ( $dataset as $meta_key => $meta_data ) {
			$meta_values[ $meta_key ] = self::get_post_meta_default_value( $meta_key );
		}

		return $meta_values;
	}

	/**
	 * Data sanitizer for AJAX post meta.
	 *
	 * @access public
	 *
	 * @param mixed $dataset from AJAX.
	 * @since 1.0.0
	 * @return mixed Sanitized data.
	 */
	public static function sanitize_data( $dataset ) {
		$output = '';

		if ( is_array( $dataset ) ) {
			$output = [];

			foreach ( $dataset as $key => $value ) {
				$datatype = self::get_post_meta_type( $key );

				switch ( $datatype ) {
					case 'html':
						$allowed_html = array_merge(
							wp_kses_allowed_html( 'post' ),
							[
								'iframe' => [
									'src'             => true,
									'width'           => true,
									'height'          => true,
									'frameborder'     => true,
									'allowfullscreen' => true,
									'allow'           => true,
									'loading'         => true,
									'referrerpolicy'  => true,
									'title'           => true,
									'name'            => true,
									'id'              => true,
									'class'           => true,
									'style'           => true,
								],
							]
						);

						$output[ $key ] = wp_kses( $value, $allowed_html );
						break;

					case 'array':
						$output[ $key ] = is_array( $value ) ? suredash_clean_data( $value ) : [];
						break;

					case 'boolean':
						$output[ $key ] = filter_var( $value, FILTER_VALIDATE_BOOLEAN );
						break;

					case 'integer':
						$output[ $key ] = absint( $value );
						break;

					default:
					case 'string':
						$output[ $key ] = sanitize_text_field( $value );
						break;
				}
			}
		} else {
			$output = sanitize_text_field( $dataset );
		}

		return $output;
	}

	/**
	 * Refresh lesson titles in course section loop.
	 *
	 * @param array<int, array<string, mixed> > $section_loop Course section loop data.
	 * @return array<int, array<string, mixed> > Updated section loop with current lesson titles.
	 * @since 1.3.2
	 */
	private static function refresh_course_lesson_titles( $section_loop ) {
		if ( ! is_array( $section_loop ) ) {
			return $section_loop;
		}

		foreach ( $section_loop as $section_index => $section ) {
			if ( isset( $section['section_medias'] ) && is_array( $section['section_medias'] ) ) {
				foreach ( $section['section_medias'] as $media_index => $media ) {
					if ( isset( $media['value'] ) ) {
						$lesson_id = absint( $media['value'] );
						if ( $lesson_id > 0 ) {
							$current_title = get_the_title( $lesson_id );
							if ( $current_title ) {
								$section_loop[ $section_index ]['section_medias'][ $media_index ]['label'] = $current_title;
							}
							$post_status = get_post_status( $lesson_id );
							if ( $post_status ) {
								$section_loop[ $section_index ]['section_medias'][ $media_index ]['post_status'] = $post_status;
							} else {
								$section_loop[ $section_index ]['section_medias'][ $media_index ]['post_status'] = false;
							}
						}
					}
				}
			}
		}

		return $section_loop;
	}
}
