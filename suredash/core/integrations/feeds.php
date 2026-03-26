<?php
/**
 * Feeds Integration.
 *
 * @package SureDash
 */

namespace SureDashboard\Core\Integrations;

use SureDashboard\Admin\Menu;
use SureDashboard\Core\Models\Controller;
use SureDashboard\Inc\Traits\Get_Instance;
use SureDashboard\Inc\Utils\Helper;
use SureDashboard\Inc\Utils\Labels;
use SureDashboard\Inc\Utils\PostMeta;

defined( 'ABSPATH' ) || exit;

/**
 * Feeds Integration.
 *
 * @since 1.0.0
 */
class Feeds extends Base {
	use Get_Instance;

	/**
	 * Current Processing Space ID.
	 *
	 * @var int
	 */
	public $active_space_id = 0;

	/**
	 * Current Processing Taxonomy.
	 *
	 * @var int
	 */
	public $active_space_tax_id = 0;

	/**
	 * Set status if footer post creation loaded.
	 *
	 * @var bool
	 */
	private $footer_post_creation_loaded = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->name        = 'Feeds';
		$this->slug        = 'store-categories';
		$this->description = __( 'Feeds Integration', 'suredash' );
		$this->is_active   = true;
		parent::__construct( $this->name, $this->slug, $this->description, $this->is_active );

		if ( ! $this->is_active ) {
			return;
		}

		add_action( 'suredash_footer', [ $this, 'add_post_creation_modal' ] );
		add_action( 'suredash_footer', [ $this, 'add_post_edit_modal' ] );
	}

	/**
	 * Get Query for Content Groups.
	 *
	 * @param int    $category_id Category ID.
	 * @param string $order_by Order By.
	 * @param string $order Order.
	 * @param string $meta_key Meta Key.
	 * @return array<mixed>
	 * @since 0.0.1
	 */
	public function get_query( $category_id, $order_by = 'post_date', $order = 'DESC', $meta_key = '' ) {
		$query_args = [
			'category_id'    => $category_id,
			'post_type'      => SUREDASHBOARD_FEED_POST_TYPE,
			'taxonomy'       => SUREDASHBOARD_FEED_TAXONOMY,
			'posts_per_page' => Helper::get_option( 'feeds_per_page', 5 ),
			'order_by'       => $order_by,
			'order'          => $order,
		];

		// Add meta_key if needed for meta-based sorting.
		if ( ! empty( $meta_key ) ) {
			$query_args['meta_key'] = $meta_key;
		}

		return Controller::get_query_data( 'Feeds', $query_args );
	}

	/**
	 * Get content for archive content categories.
	 *
	 * @return string|false
	 * @since 0.0.1
	 */
	public function get_archive_content() {
		ob_start();

		$feed_group_id = get_queried_object_id();

		if ( is_post_type_archive( SUREDASHBOARD_FEED_POST_TYPE ) ) {
			$query_posts = sd_get_posts(
				[
					'post_type'      => [ SUREDASHBOARD_FEED_POST_TYPE ],
					'posts_per_page' => Helper::get_option( 'feeds_per_page', 5 ),
					'post_status'    => 'publish',
				]
			);
		} else {
			$query_posts = $this->get_query( $feed_group_id );
		}

		if ( ! empty( $query_posts ) && is_array( $query_posts ) ) {
			foreach ( $query_posts as $post ) {
				$post_id = absint( $post['ID'] );

				// Ensure the post object is valid.
				if ( empty( $post_id ) ) {
					continue;
				}

				// Render the post.
				Helper::render_post( $post );
			}
		}

		wp_reset_postdata(); // Reset post data.

		Helper::get_archive_pagination_markup( $feed_group_id );

		return ob_get_clean();
	}

	/**
	 * Add Post Creation Modal.
	 *
	 * @return void
	 * @since 0.0.1
	 */
	public function add_post_creation_modal(): void {
		if ( ! suredash_is_pro_active() ) {
			return;
		}
		if ( $this->footer_post_creation_loaded ) {
			return;
		}
		if ( ! suredash_frontend() ) {
			return;
		}

		$can_create_post   = suredash_community_accessible( is_user_logged_in() );
		$discussion_spaces = Menu::get_instance()->get_all_spaces_for_dropdown( 'posts_discussion' );

		?>
			<div id="portal-post-creation-modal" class="portal-modal portal-content">
				<div class="portal-modal-content sd-shadow-lg">
					<div class="portal-modal-header">
						<h2 class="sd-no-space"><?php Labels::get_label( 'write_a_post', true ); ?></h2>
						<div class="portal-post-creation-supports sd-gap-8">
							<?php if ( $can_create_post ) { ?>
								<?php
									/**
									 * Fires to render user access control selector in post creation modal.
									 *
									 * @since 1.6.0
									 *
									 * @param string $context The context where this is being rendered ('create').
									 */
									do_action( 'suredash_post_access_control_selector', 'create' );
								?>
								<a href="#" class="portal-linked-content-field sd-text-color tooltip-trigger" data-tooltip-description="<?php echo esc_attr__( 'Cover Image', 'suredash' ); ?>" data-tooltip-position="bottom" data-section="portal-featured-image-field"> <?php Helper::get_library_icon( 'Image', true, 'md' ); ?> </a>
								<a href="#" class="portal-linked-content-field sd-text-color tooltip-trigger" data-tooltip-description="<?php echo esc_attr__( 'Cover Video', 'suredash' ); ?>" data-tooltip-position="bottom" data-section="portal-embed-field"> <?php Helper::get_library_icon( 'Film', true, 'md' ); ?> </a>
								<span class="portal-vertical-divider"></span>
							<?php } ?>
							<a href="#" class="portal-modal-close sd-text-color tooltip-trigger" data-tooltip-description="<?php echo esc_attr__( 'Close', 'suredash' ); ?>" data-tooltip-position="bottom"> <?php Helper::get_library_icon( 'X', true, 'md' ); ?> </a>
						</div>
					</div>

					<?php if ( $can_create_post ) { ?>
						<div class="portal-modal-body">
							<?php suredash_image_uploader_field( __( 'Cover Image', 'suredash' ), 'custom_post_cover_image', false, true ); ?>

							<div class="portal-custom-topic-field portal-extended-linked-field portal-hidden-field portal-embed-field">
								<label for="custom_post_embed_media">
								<?php
								esc_html_e( 'Cover Embed Media', 'suredash' );

								echo sprintf(
									'<p class="portal-help-description">%s%s&nbsp;%s%s</p>',
									'(',
									esc_html__( 'See supported embeds', 'suredash' ),
									'<a href="' . esc_url( 'https://wordpress.org/documentation/article/embeds/#list-of-sites-you-can-embed-from' ) . '" target="_blank">' . esc_html__( 'here', 'suredash' ) . '</a>',
									')'
								);
								?>
								</label>
								<input type="text" id="custom_post_embed_media" name="custom_post_embed_media" class="portal_topic_input portal_feed_input sd-force-bg-transparent" placeholder="<?php echo esc_attr__( 'Please enter the URL of the media you want to embed', 'suredash' ); ?>" />
							</div>

							<div class="portal-custom-topic-field">
								<input type="text" id="custom_post_title" name="custom_post_title" class="portal_topic_input post_creation_title sd-force-font-28 sd-force-font-medium sd-force-p-0 sd-force-border-none sd-force-shadow-none sd-force-bg-transparent sd-heading-title" autocomplete="off" placeholder="<?php echo esc_attr__( 'Enter a title', 'suredash' ); ?>" />

								<textarea id="custom_post_content" name="custom_post_content" class="portal_topic_input post_creation_content"></textarea>
							</div>

							<input type="hidden" id="custom_post_tax_id" name="custom_post_tax_id" class="portal_feed_input" value="<?php echo esc_attr( (string) $this->active_space_tax_id ); ?>" />
						</div>

						<div class="portal-modal-header portal-posting-in-selection-wrap">
							<select id="portal-posting-in-selection" name="custom_post_space_selection" class="portal_topic_input portal_feed_input sd-w-full">
								<option value=""><?php esc_html_e( 'Choose a space to post in', 'suredash' ); ?></option>
								<?php
								if ( ! empty( $discussion_spaces ) ) {
									foreach ( $discussion_spaces as $space ) {
										$space_id   = absint( $space['value'] );
										$space_name = esc_html( $space['label'] );

										if ( suredash_is_post_protected( $space_id ) ) {
											continue;
										}
										if ( ! suredash_is_user_manager() && ! boolval( PostMeta::get_post_meta_value( $space_id, 'allow_members_to_post' ) ) ) {
											continue;
										}

										?>
										<option value="<?php echo esc_attr( strval( $space_id ) ); ?>"><?php echo esc_html( $space_name ); ?></option>
										<?php
									}
								}
								?>
							</select>
						</div>

						<div class="portal-modal-footer">
							<div class="portal-post-creation-actions">
								<button id="portal-post-creation-submit" class="portal-button button-primary"><?php Labels::get_label( 'submit_button', true ); ?></button>
							</div>
						</div>
						<?php
					} else {
						suredash_get_template_part(
							'parts',
							'404',
							[
								'404_heading'    => Labels::get_label( 'user_needs_login' ),
								'not_found_text' => '',
							]
						);
					}
					?>
				</div>
				<div class="portal-modal-backdrop"></div>
			</div>

		<?php

		do_action( 'suredashboard_after_post_creation_modal', $this->active_space_id );

		$this->footer_post_creation_loaded = true;
	}

	/**
	 * Add Post Edit Modal.
	 *
	 * @return void
	 * @since 1.4.0
	 */
	public function add_post_edit_modal(): void {
		if ( ! suredash_frontend() ) {
			return;
		}

		// Ensure modal is only added once per page load.
		static $modal_added = false;
		if ( $modal_added ) {
			return;
		}
		$modal_added = true;

		?>
		<div id="portal-thread-edit-modal" class="portal-modal portal-content" style="display: none;">
			<div class="portal-modal-content sd-shadow-lg">
				<div class="portal-modal-header">
					<h2 class="sd-no-space"><?php esc_html_e( 'Edit Post', 'suredash' ); ?></h2>

					<div class="portal-post-creation-supports sd-flex sd-items-center sd-gap-12">
						<?php
						/**
						 * Fires to render user access control selector in post edit modal.
						 *
						 * @since 1.6.0
						 *
						 * @param string $context The context where this is being rendered ('edit').
						 */
						do_action( 'suredash_post_access_control_selector', 'edit' );
						?>
						<a href="#" class="portal-linked-content-field sd-help-text tooltip-trigger" data-tooltip-description="<?php echo esc_attr__( 'Cover Image', 'suredash' ); ?>" data-tooltip-position="bottom" data-section="portal-featured-image-field"> <?php Helper::get_library_icon( 'Image', true, 'md' ); ?> </a>
						<a href="#" class="portal-linked-content-field sd-help-text tooltip-trigger" data-tooltip-description="<?php echo esc_attr__( 'Cover Media Embed', 'suredash' ); ?>" data-tooltip-position="bottom" data-section="portal-embed-field"> <?php Helper::get_library_icon( 'SquarePlay', true, 'md' ); ?> </a>
					</div>
				</div>
				<div class="portal-modal-body">
					<?php suredash_image_uploader_field( __( 'Cover Image', 'suredash' ), 'edit_post_cover_image', false, true ); ?>

					<div class="portal-custom-topic-field portal-extended-linked-field portal-hidden-field portal-embed-field">
						<label for="edit_post_embed_media">
							<?php
							esc_html_e( 'Cover Embed Media', 'suredash' );

							echo sprintf(
								'<p class="portal-help-description">%s%s&nbsp;%s%s</p>',
								'(',
								esc_html__( 'See supported embeds', 'suredash' ),
								'<a href="' . esc_url( 'https://wordpress.org/documentation/article/embeds/#list-of-sites-you-can-embed-from' ) . '" target="_blank">' . esc_html__( 'here', 'suredash' ) . '</a>',
								')'
							);
							?>
						</label>
						<input type="text" id="edit_post_embed_media" name="edit_post_embed_media" class="portal_topic_input portal_feed_input sd-force-bg-transparent" placeholder="<?php echo esc_attr__( 'Please enter the URL of the media you want to embed', 'suredash' ); ?>" />
					</div>

					<div class="portal-custom-topic-field">
						<input type="text" id="edit_post_title" name="edit_post_title" class="portal_topic_input post_creation_title sd-force-font-28 sd-force-font-medium sd-force-p-0 sd-force-border-none sd-force-shadow-none sd-force-bg-transparent sd-heading-title" autocomplete="off" placeholder="<?php echo esc_attr__( 'Enter a title', 'suredash' ); ?>" />

						<textarea id="edit_post_content" name="edit_post_content" class="portal_topic_input post_creation_content"></textarea>
					</div>

					<input type="hidden" id="edit_post_id" name="edit_post_id" value="" />
					<input type="hidden" id="edit_post_tax_id" name="edit_post_tax_id" class="portal_feed_input" value="<?php echo esc_attr( (string) $this->active_space_tax_id ); ?>" />
				</div>
				<div class="portal-modal-footer">
					<div class="portal-post-creation-actions">
						<button class="portal-button button-secondary portal-modal-close"><?php Labels::get_label( 'close_button', true ); ?></button>
						<button id="portal-post-edit-submit" class="portal-button button-primary"><?php Labels::get_label( 'save', true ); ?></button>
					</div>
				</div>
			</div>

			<div class="portal-modal-backdrop"></div>
		</div>
		<?php
	}

	/**
	 * Get top sub-header section where we ask user to create a post.
	 *
	 * @return void
	 * @since 0.0.1
	 */
	public function get_post_creation_section(): void {
		if ( ! suredash_is_pro_active() ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			?>
			<div class="comment-modal-login-notice sd-w-full sd-flex sd-items-center sd-justify-center sd-radius-6 sd-px-20 sd-py-10">
				<?php Helper::get_login_notice( 'comment' ); ?>
			</div>
			<?php
			return;
		}
		?>

		<div
			id="portal-write-a-post"
			class="portal-store-list-post sd-p-container portal-content"
			tabindex="0"
			role="button"
			aria-label="<?php echo esc_attr( __( 'Write a new post', 'suredash' ) ); ?>"
			onkeypress="if(event.key === 'Enter') { this.click(); }">
			<div class="portal-write-a-post-header">
				<?php suredash_get_user_avatar( get_current_user_id() ); ?>
				<span class="portal-write-a-post-heading sd-font-16 sd-p-12 sd-radius-8 sd-border">
					<?php Labels::get_label( 'start_writing_post', true ); ?>
				</span>
			</div>
		</div>

		<?php
		add_action( 'suredash_footer', [ $this, 'add_post_creation_modal' ] );
	}

	/**
	 * Get item single content.
	 *
	 * @param int $base_id Post ID.
	 * @return string|false
	 * @since 0.0.1
	 */
	public function get_integration_content( $base_id ) {
		ob_start();

		// If the discussion area is private, we don't show the content.
		if ( suredash_is_private_discussion_area( $base_id ) ) {
			suredash_get_template_part(
				'parts',
				'404',
				[
					'404_heading'    => __( 'Private Discussion Area', 'suredash' ),
					'not_found_text' => __( 'This discussion space is private. Please log in to view the content.', 'suredash' ),
				]
			);
			return ob_get_clean();
		}

		$feed_group_id         = absint( PostMeta::get_post_meta_value( $base_id, 'feed_group_id' ) );
		$allow_members_to_post = boolval( PostMeta::get_post_meta_value( $base_id, 'allow_members_to_post' ) );

		if ( $feed_group_id && $allow_members_to_post ) {
			$this->active_space_id     = $base_id;
			$this->active_space_tax_id = $feed_group_id;
			$this->get_post_creation_section();
		}

		if ( $feed_group_id ) {
			do_action( 'suredashboard_before_wp_single_content_load', $feed_group_id );

			// Get validated sort preference using helper.
			// Skip passing default sort preference as settings not present on admin dashboard.
			$feeds_sort = Helper::get_feeds_sort_preference();

			// Parse sort parameters using helper.
			$sort_params = Helper::parse_feeds_sort_params( $feeds_sort );
			$order_by    = $sort_params['order_by'];
			$order       = $sort_params['order'];
			$meta_key    = $sort_params['meta_key'];

			// Get validated view preference using helper.
			// Space default is the fallback; user cookie/request preference takes priority.
			$space_default_list_view = PostMeta::get_post_meta_value( $base_id, 'default_list_view' );
			$space_default_view      = $space_default_list_view ? 'list' : 'grid';
			$initial_view            = Helper::get_feeds_view_preference( $space_default_view );

			$pinned_posts = Helper::get_pinned_posts( $base_id );
			$query_posts  = $this->get_query( $feed_group_id, $order_by, $order, $meta_key );

			// Render controls (sort + view toggle).
			if ( ! empty( $query_posts ) && is_array( $query_posts ) ) {
				Helper::render_feeds_controls(
					$feeds_sort,
					$initial_view,
					$base_id,
					$feed_group_id,
					SUREDASHBOARD_FEED_POST_TYPE,
					SUREDASHBOARD_FEED_TAXONOMY,
					$base_id,
					get_current_user_id(),
					'sd-pt-0'
				);
			}

			add_filter( 'suredash_skip_restricted_post', '__return_true' );

			?>
			<!-- Posts Container with view mode tracking -->
			<div class="portal-feeds-posts-container" data-view-mode="<?php echo esc_attr( $initial_view ); ?>">
			<?php

			if ( ! empty( $query_posts ) && is_array( $query_posts ) ) {
				/**
				 * Performance optimization: Prime caches to prevent N+1 queries.
				 * This fetches all post meta and user meta in 2 queries instead of 5-10 per post.
				 *
				 * @since 1.6.3
				 */
				$all_posts_to_render = $query_posts;

				// Add pinned posts to the priming list.
				if ( ! empty( $pinned_posts ) ) {
					foreach ( $pinned_posts as $pinned_post_id ) {
						if ( sd_post_exists( $pinned_post_id ) ) {
							$all_posts_to_render[] = (array) sd_get_post( $pinned_post_id );
						}
					}
				}

				$post_ids   = array_filter( wp_list_pluck( $all_posts_to_render, 'ID' ) );
				$author_ids = array_filter( array_unique( wp_list_pluck( $all_posts_to_render, 'post_author' ) ) );

				if ( ! empty( $post_ids ) ) {
					// Prime post meta cache (bookmarks, content type, edited time, etc.).
					update_meta_cache( 'post', $post_ids );
				}

				if ( ! empty( $author_ids ) ) {
					// Prime user meta cache (headlines, badges, display names, etc.).
					update_meta_cache( 'user', $author_ids );
				}

				// Only show discussion space name on the main feeds page, not inside a specific discussion space.
				$is_feeds_page = suredash_get_sub_queried_page() === 'feeds';

				// Check if we should render in list view.
				if ( $initial_view === 'list' ) {
					// List view rendering - prepare list items.
					$list_items = [];

					// Add pinned posts first.
					if ( ! empty( $pinned_posts ) ) {
						foreach ( $pinned_posts as $pinned_post_id ) {
							if ( sd_post_exists( $pinned_post_id ) ) {
								$post_link   = get_permalink( $pinned_post_id );
								$author_id   = get_post_field( 'post_author', $pinned_post_id );
								$author_name = suredash_get_user_display_name( (int) $author_id );
								$post_date   = suredash_get_relative_time( $pinned_post_id, false, $is_feeds_page );
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

					// Add regular posts.
					foreach ( $query_posts as $post ) {
						$post_id = absint( $post['ID'] );

						// Skip if already rendered as pinned.
						if ( in_array( $post_id, $pinned_posts, true ) ) {
							continue;
						}

						$post_link   = get_permalink( $post_id );
						$author_id   = get_post_field( 'post_author', $post_id );
						$author_name = suredash_get_user_display_name( (int) $author_id );
						$post_date   = suredash_get_relative_time( $post_id, false, $is_feeds_page );
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
					// Grid view rendering.

					// Render pinned posts.
					if ( ! empty( $pinned_posts ) ) {
						foreach ( $pinned_posts as $pinned_post_id ) {
							if ( sd_post_exists( $pinned_post_id ) ) {
								$pinned_post = (array) sd_get_post( $pinned_post_id );
								Helper::render_post( $pinned_post, $base_id, true, $is_feeds_page );
							}
						}
					}

					// Render regular posts.
					foreach ( $query_posts as $post ) {
						$post_id        = absint( $post['ID'] );
						$is_pinned_post = in_array( $post_id, $pinned_posts, true );

						if ( $is_pinned_post ) {
							continue;
						}

						Helper::render_post( $post, $base_id, $is_pinned_post, $is_feeds_page );
					}
				}
			} else {
				suredash_get_template_part( 'parts', '404' );
			}

			?>
			</div>
			<?php

			// Add infinite scroll trigger only when there are posts to avoid redundant "no more posts" message.
			if ( ! empty( $query_posts ) && is_array( $query_posts ) ) {
				?>
				<div
					class="portal-infinite-trigger"
					data-post_type="<?php echo esc_attr( SUREDASHBOARD_FEED_POST_TYPE ); ?>"
					data-taxonomy="<?php echo esc_attr( SUREDASHBOARD_FEED_TAXONOMY ); ?>"
					data-category="<?php echo esc_attr( (string) $feed_group_id ); ?>"
					data-base_id="<?php echo esc_attr( (string) $base_id ); ?>"
					data-space_id="<?php echo esc_attr( (string) $base_id ); ?>"
				></div>
				<?php
			}

			remove_filter( 'suredash_skip_restricted_post', '__return_true' );

			wp_reset_postdata(); // Reset post data.

			do_action( 'suredashboard_after_wp_single_content_load', $feed_group_id );
		} else {
			suredash_get_template_part( 'parts', '404' );
		}

		return ob_get_clean();
	}
}
