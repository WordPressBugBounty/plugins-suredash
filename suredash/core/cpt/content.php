<?php
/**
 * Community Content CPT
 *
 * Current usecase for this CPT is "Lesson".
 *
 * @package SureDash
 * @since 1.0.0
 */

namespace SureDashboard\Core\CPT;

use SureDashboard\Inc\Traits\Get_Instance;
use SureDashboard\Inc\Traits\Post_Type;

defined( 'ABSPATH' ) || exit;

/**
 * Account Sub-Content CPT
 *
 * @since 1.0.0
 */
class Content {
	use Post_Type;
	use Get_Instance;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function __construct() {
		$this->post_type = SUREDASHBOARD_SUB_CONTENT_POST_TYPE;

		$this->post_type_labels = apply_filters(
			'suredashboard_portal_community_content_cpt_labels',
			[
				'name'               => esc_html_x( 'Community Content', 'content general name', 'suredash' ),
				'singular_name'      => esc_html_x( 'Community Content', 'content singular name', 'suredash' ),
				'search_items'       => esc_html__( 'Search Community Content', 'suredash' ),
				'all_items'          => esc_html__( 'Community Content', 'suredash' ),
				'edit_item'          => esc_html__( 'Edit Community Content', 'suredash' ),
				'view_item'          => esc_html__( 'View Community Content', 'suredash' ),
				'add_new'            => esc_html__( 'Add New', 'suredash' ),
				'update_item'        => esc_html__( 'Update Community Content', 'suredash' ),
				'add_new_item'       => esc_html__( 'Add New', 'suredash' ),
				'new_item_name'      => esc_html__( 'New Community Content Name', 'suredash' ),
				'not_found'          => esc_html__( 'No Community Content found.', 'suredash' ),
				'not_found_in_trash' => esc_html__( 'No Community Content found.', 'suredash' ),
			]
		);
		$this->post_type_args   = apply_filters(
			'suredashboard_portal_community_content_cpt_args',
			[
				'labels'              => $this->post_type_labels,
				'public'              => true,
				'show_ui'             => true,
				'show_in_menu'        => false,
				'query_var'           => true,
				'can_export'          => true,
				'show_in_admin_bar'   => true,
				'show_in_nav_menus'   => true,
				'exclude_from_search' => true,
				'has_archive'         => true,
				'show_in_rest'        => true,
				'rewrite'             => apply_filters(
					'suredash_community_content_rewrite_rules',
					[
						'slug'       => apply_filters( 'suredash_community_content_slug', $this->post_type ),
						'with_front' => false,
					]
				),
				'map_meta_cap'        => true,
				'supports'            => [ 'title', 'editor', 'thumbnail', 'slug', 'comments', 'excerpt', 'author', 'custom-fields' ],
				'capability_type'     => $this->post_type,
			]
		);

		add_filter( 'post_type_link', [ $this, 'custom_content_permalink' ], 10, 4 );
	}

	/**
	 * Function to load the admin area actions.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function create_cpt_n_taxonomy(): void {
		$this->init_register_post_type();
		$this->init_belonging_posts_filter();
	}

	/**
	 * Create the posts filter for the sub content on edit page.
	 *
	 * This will be based on meta key "belong_to_course".
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init_belonging_posts_filter(): void {
		add_action( 'restrict_manage_posts', [ $this, 'add_belongs_to_filter' ] );
		add_action( 'pre_get_posts', [ $this, 'filter_lessons_by_course' ] );

		// Add Course column to the sub content list table.
		add_filter(
			'manage_' . $this->post_type . '_posts_columns',
			static function ( $columns ) {
				unset( $columns['date'] );
				$columns['belong_to_course'] = esc_html__( 'Course', 'suredash' );
				$columns['date']             = esc_html__( 'Date', 'suredash' );
				return $columns;
			}
		);
		add_action( 'manage_' . $this->post_type . '_posts_custom_column', [ $this, 'add_course_name_to_list_table' ], 10, 2 );
	}

	/**
	 * Add the course name to the sub content list table.
	 *
	 * @since 1.0.0
	 * @param array<string> $column The column name.
	 * @param int           $post_id The post ID.
	 * @return void
	 */
	public function add_course_name_to_list_table( $column, $post_id ): void {
		if ( $column === 'belong_to_course' ) {
			$course_id = get_post_meta( $post_id, 'belong_to_course', true );
			if ( ! empty( $course_id ) ) {
				echo esc_html( get_the_title( $course_id ) );
			} else {
				echo esc_html__( 'No Course', 'suredash' );
			}
		}
	}

	/**
	 * Add the belonging posts filter to the sub content on edit page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_belongs_to_filter(): void {
		global $typenow;
		if ( $typenow === $this->post_type ) {
			global $wpdb;

			$course_ids = $wpdb->get_col( // phpcs:ignore -- Not require in admin area.
				$wpdb->prepare(
					"SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
					'belong_to_course'
				)
			);

			if ( ! empty( $course_ids ) ) {
				echo '<select name="belong_to_course">';
				echo '<option value="">' . esc_html__( 'All Courses', 'suredash' ) . '</option>';
				foreach ( $course_ids as $course_id ) {
					echo '<option value="' . esc_attr( $course_id ) . '">' . esc_html( get_the_title( $course_id ) ) . '</option>';
				}
				echo '</select>';
			}
		}
	}

	/**
	 * Filter the lessons by course.
	 *
	 * @since 1.0.0
	 * @param \WP_Query $query The query object.
	 * @return void
	 */
	public function filter_lessons_by_course( $query ): void {
		global $pagenow;

		if ( $pagenow === 'edit.php' && isset( $_GET['belong_to_course'] ) && ! empty( $_GET['belong_to_course'] ) && $this->post_type === $query->query['post_type'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$meta_query = [
				[
					'key'     => 'belong_to_course', // Meta key storing the course ID.
                    'value' => sanitize_text_field($_GET['belong_to_course']), // phpcs:ignore -- Not require in admin area.
					'compare' => '=',
				],
			];
			if ( $query->is_main_query() ) {
				$query->set( 'meta_query', $meta_query );
			}
		}
	}

	/**
	 * Custom permalink for the community content.
	 *
	 * @since 1.0.0
	 * @param string   $permalink The permalink.
	 * @param \WP_Post $post The post object.
	 * @param string   $leave_name The leave name.
	 * @param bool     $sample Whether this is a sample permalink.
	 *
	 * @return string
	 */
	public function custom_content_permalink( $permalink, $post, $leave_name, $sample ) {
		if ( $this->post_type !== $post->post_type ) {
			return $permalink;
		}

		$content_type = sd_get_post_meta( $post->ID, 'content_type', true );
		$content_type = ! empty( $content_type ) ? $content_type : 'lesson';
		$type_slug    = suredash_get_community_content_slug( $content_type );

		if ( in_array( $content_type, suredash_all_content_types(), true ) ) {
			return str_replace( '/' . SUREDASHBOARD_SUB_CONTENT_POST_TYPE . '/', '/' . $type_slug . '/', $permalink );
		}

		return $permalink;
	}
}
