<?php
/**
 * Portals Docs HomeContent Shortcode Initialize.
 *
 * @package SureDash
 */

namespace SureDashboard\Core\Shortcodes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use SureDashboard\Core\Integrations\SinglePost;
use SureDashboard\Core\Models\Controller;
use SureDashboard\Inc\Traits\Get_Instance;
use SureDashboard\Inc\Traits\Shortcode;
use SureDashboard\Inc\Utils\Helper;
use SureDashboard\Inc\Utils\Labels;
use SureDashboard\Inc\Utils\PostMeta;

/**
 * Class HomeContent Shortcode.
 */
class HomeContent {
	use Shortcode;
	use Get_Instance;

	/**
	 * Register_shortcode_event.
	 *
	 * @return void
	 */
	public function register_shortcode_event(): void {
		$this->add_shortcode( 'home_content' );
	}

	/**
	 * Display content.
	 *
	 * @param array<mixed> $atts Array of attributes.
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function render_home_content( $atts ) {
		$defaults = [
			'type' => 'home',
		];

		$atts = shortcode_atts( $defaults, $atts );

		$type = $atts['type'];

		// Initialize content as empty for unknown types (allows pro features to handle via filter).
		$content = '';

		switch ( $type ) {
			case 'home':
				$content = $this->get_home_content();
				break;

			case 'bookmarks':
				$content = $this->get_bookmarks_content();
				break;

			case 'user-profile':
				$content = $this->get_user_profile_content();
				break;

			case 'user-view':
				$content = $this->get_user_view_content();
				break;

			case 'feeds':
				$content = $this->get_feeds_posts();
				break;

			case 'screen':
				$content = $this->get_screen_content();
				break;
		}

		return apply_filters( 'suredash_home_content', $content, $type );
	}

	/**
	 * Get item markup for home content loop.
	 *
	 * @param int  $post_id Post ID.
	 * @param bool $skip_excerpt Skip excerpt.
	 * @since 1.0.0
	 * @return void
	 */
	public function get_item_markup( $post_id, $skip_excerpt = false ): void {
		$post_title   = PostMeta::get_post_meta_value( $post_id, 'post_title' );
		$post_link    = (string) get_permalink( $post_id );
		$post_excerpt = wp_trim_words( wp_strip_all_tags( suredash_get_excerpt( $post_id, 12 ) ) );

		$thumbnail_html = Helper::get_space_featured_image( $post_id );

		ob_start();
		?>
		<div class="portal-home-post-item-content">
			<a href="<?php echo esc_url( $post_link ); ?>" data-id="<?php echo esc_attr( (string) $post_id ); ?>" class="portal-home-post-item">
				<?php echo do_shortcode( $thumbnail_html ); ?>
				<?php echo esc_html( $post_title ); ?>
			</a>
			<?php if ( ! $skip_excerpt && ! empty( $post_excerpt ) ) { ?>
				<p class="sd-no-space"><?php echo wp_kses_post( $post_excerpt ); ?></p>
			<?php } ?>
		</div>
		<?php
		echo do_shortcode( (string) ob_get_clean() );
	}

	/**
	 * Get grid markup for content display.
	 *
	 * @param array<mixed> $items Array of items to display in grid.
	 * @param string       $title Optional title for the grid section.
	 * @param bool         $show_title Whether to show the title.
	 * @param string       $content_type Optional content type for button text customization.
	 * @param string       $layout_type Whether to use narrow layout.
	 * @param string       $card_type Type of card to display (always minimal now).
	 * @since 1.0.0
	 * @return string The generated HTML markup
	 */
	public function get_grid_markup( $items, $title = '', $show_title = true, $content_type = '', $layout_type = 'wide', $card_type = 'minimal' ): string {
		if ( empty( $items ) ) {
			return '';
		}

		ob_start();

		?>
		<div class="<?php echo esc_attr( $layout_type ) . '-grid-row'; ?> sd-flex-col sd-gap-16">
			<?php if ( $show_title && ! empty( $title ) ) { ?>
				<h3 class="portal-home-post-title sd-responsive-text-center sd-no-space"><?php echo esc_html( $title ); ?></h3>
			<?php } ?>

			<section class="portal-grid-row sd-responsive-justify-center sd-flex sd-gap-24 sd-flex-wrap">
				<?php
				$counter = 0;
				foreach ( $items as $item ) {
					$colors = [ 'red', 'orange', 'lime', 'teal', 'green', 'indigo' ];
					$color  = $colors[ $counter % 6 ];

					$post_id     = $item['id'] ?? $item;
					$space_icon  = $item['space_icon'] ?? '';
					$integration = $item['integration'] ?? '';

					// Determine button text based on content type or integration.
					$button_text = '';
					$button_icon = 'ArrowRight';

					if ( ! empty( $content_type ) ) {
						// Use content_type parameter if provided.
						switch ( $content_type ) {
							case 'lesson':
								$button_text = __( 'Visit Lesson', 'suredash' );
								$space_icon  = 'GraduationCap';
								break;
							case 'portal':
								$button_text = __( 'Visit Post', 'suredash' );
								$space_icon  = 'newspaper';
								break;
							case 'post':
								$button_text = __( 'Read Post', 'suredash' );
								break;
							default:
								$button_text = __( 'Visit Space', 'suredash' );
						}
					} else {
						// Otherwise use integration type.
						switch ( $integration ) {
							case 'course':
								$button_text = __( 'Resume Course', 'suredash' );
								break;
							case 'single_post':
								$button_text = __( 'Read Post', 'suredash' );
								break;
							case 'posts_discussion':
								$button_text = __( 'View Discussion', 'suredash' );
								break;
							default:
								$button_text = __( 'Visit Space', 'suredash' );
						}
					}

					$this->get_minimal_card_markup(
						[
							'post_id'           => $post_id,
							'skip_excerpt'      => true,
							'placeholder_color' => $color,
							'space_icon'        => $space_icon,
							'button_text'       => $button_text,
							'button_icon'       => $button_icon,
						]
					);

					$counter++;
				}
				?>
			</section>
		</div>
		<?php

		return do_shortcode( strval( ob_get_clean() ) );
	}

	/**
	 * Get minimal card markup for home content.
	 *
	 * @param array<string,mixed> $args {
	 *     Array of arguments for rendering the minimal card.
	 *
	 *     @type int    $post_id           Required. Post ID.
	 *     @type bool   $skip_excerpt      Optional. Skip excerpt. Default true.
	 *     @type string $placeholder_color Optional. Placeholder color. Default 'blue'.
	 *     @type string $space_icon        Optional. Space icon. Default ''.
	 *     @type string $button_text       Optional. Custom button text. Default ''.
	 *     @type string $button_icon       Optional. Custom button icon. Default 'ArrowRight'.
	 * }
	 * @since 1.0.0
	 */
	public function get_minimal_card_markup( $args = [] ): void {
		$defaults = [
			'post_id'           => 0,
			'skip_excerpt'      => true,
			'placeholder_color' => 'blue',
			'space_icon'        => '',
			'button_icon'       => 'ArrowRight',
		];

		$args = wp_parse_args( $args, $defaults );

		// Extract variables for easier use.
		$post_id           = $args['post_id'];
		$skip_excerpt      = $args['skip_excerpt'];
		$placeholder_color = $args['placeholder_color'];
		$space_icon        = $args['space_icon'];
		$button_icon       = $args['button_icon'];

		// Bail if no post ID provided.
		if ( empty( $post_id ) ) {
			return;
		}

		$post_title       = PostMeta::get_post_meta_value( $post_id, 'post_title' );
		$space_type       = PostMeta::get_post_meta_value( $post_id, 'integration' );
		$post_description = PostMeta::get_post_meta_value( $post_id, 'space_description' );
		$space_link       = (string) get_permalink( $post_id );
		$space_target     = '_self';

		if ( $space_type === 'link' ) {
			$space_link   = PostMeta::get_post_meta_value( $post_id, 'link_url' );
			$space_target = PostMeta::get_post_meta_value( $post_id, 'link_target' );
		}

		$post_excerpt    = wp_trim_words( wp_strip_all_tags( suredash_get_excerpt( $post_id, 12 ) ) );
		$thumbnail_html  = Helper::get_space_featured_image( $post_id, true, $placeholder_color, $space_icon );
		$has_description = ! empty( $post_description ) ? 'has-description' : '';

		ob_start();
		?>
		<div class="portal-grid-row-container">
			<a class="portal-home-grid-item-content portal-home-grid-item-content-minimal sd-border sd-hover-shadow-2xl <?php echo esc_attr( $has_description ); ?>"
				href="<?php echo esc_url( $space_link ); ?>"
				target="<?php echo esc_attr( $space_target ); ?>"
				data-id="<?php echo esc_attr( (string) $post_id ); ?>">
				<?php echo do_shortcode( $thumbnail_html ); ?>
				<div class="sd-flex-col sd-p-20 sd-gap-16 sd-card-main-container">
					<div class="sd-flex-col sd-gap-4 sd-card-content-container sd-relative sd-text-color">
						<div class="sd-flex sd-justify-between sd-items-center">
							<div class="sd-font-16 sd-line-24 sd-font-semibold sd-line-clamp-1 sd-flex sd-w-full">
								<span class="sd-flex-1"><?php echo esc_html( $post_title ); ?></span>
							</div>
							<?php Helper::get_library_icon( $button_icon, true, 'sm', 'grid-card-arrow' ); ?>
						</div>
						<?php if ( ! empty( $post_description ) ) { ?>
							<p class="sd-m-0 sd-line-clamp-2 sd-card-description">
								<?php echo wp_kses_post( $post_description ); ?>
							</p>
						<?php } ?>
					</div>
					<?php if ( ! $skip_excerpt && ! empty( $post_excerpt ) ) { ?>
						<p class="sd-no-space"><?php echo wp_kses_post( $post_excerpt ); ?></p>
					<?php } ?>
				</div>
			</a>
		</div>
		<?php

		echo do_shortcode( strval( ob_get_clean() ) );
	}

	/**
	 * Get bookmarks content.
	 *
	 * @since 1.0.0
	 * @return string|false
	 */
	public function get_bookmarks_content() {
		$bookmarked_items = suredash_get_all_bookmarked_items();

		ob_start();

		if ( empty( $bookmarked_items ) ) {
			suredash_get_template_part(
				'parts',
				'404',
				[
					'not_found_text' => Labels::get_label( 'bookmark_not_found_text' ),
				]
			);
			return apply_filters( 'suredash_bookmark_content', ob_get_clean() );
		}

		// Capture all 'lesson' & 'portal' keys separately in an array.
		$lesson_items    = [];
		$portal_items    = [];
		$resources_items = [];
		$events_items    = [];
		$misc_items      = [];

		foreach ( $bookmarked_items as $id => $type ) {
			// Get post data for each bookmarked item.
			if ( ! sd_is_post_publish( $id ) ) {
				continue;
			}

			// Prepare card data with button option.
			$card_data = [
				'id'              => $id,
				'title'           => sd_get_post_field( $id ),
				'description'     => wp_strip_all_tags( suredash_get_excerpt( $id ) ),
				'show_visit_link' => true,
				'visit_link_url'  => get_permalink( $id ),
				'avatar'          => '', // Will use featured image.
			];

			// Add type-specific data and button text.
			if ( $type === 'lesson' ) {
				$card_data['avatar']          = 'GraduationCap';
				$card_data['visit_link_text'] = __( 'Visit Lesson', 'suredash' );
				$card_data['button_icon']     = 'ArrowRight';
				$lesson_items[]               = $card_data;
			} elseif ( $type === 'portal' ) {
				$card_data['avatar']          = 'newspaper';
				$card_data['visit_link_text'] = __( 'Visit Post', 'suredash' );
				$card_data['button_icon']     = 'ArrowRight';
				$portal_items[]               = $card_data;
			} elseif ( $type === 'resource' ) {
				$resource_type                = sd_get_post_meta( $id, 'resource_type', true );
				$resource_type                = ! empty( $resource_type ) ? $resource_type : 'upload';
				$card_data['avatar']          = $resource_type === 'upload' ? 'Folder' : 'ExternalLink';
				$card_data['show_visit_link'] = false;
				$card_data['enable_likes']    = true;
				$card_data['enable_comments'] = true;
				$card_data['bookmark']        = true;
				$card_data['link_js_hook']    = 'view-resource'; // For opening resource modal.
				$card_data['integration']     = 'resource_library'; // For showing resource-specific header in quick view modal.
				$resources_items[]            = $card_data;
			} elseif ( $type === 'event' ) {
				$card_data['avatar']          = 'Calendar';
				$card_data['visit_link_text'] = __( 'View Event', 'suredash' );
				$card_data['button_icon']     = 'ArrowRight';
				$events_items[]               = $card_data;
			} else {
				$card_data['avatar']          = 'FileText';
				$card_data['visit_link_text'] = __( 'Read Post', 'suredash' );
				$card_data['button_icon']     = 'ArrowRight';
				$misc_items[]                 = $card_data;
			}
		}

		?>
		<div class="portal-content-area portal-content portal-home-grid wide-grid-row sd-justify-self-center sd-flex-col sd-gap-32 sd-items-center">
			<?php
			// Display 'lesson' items using new card grid.
			if ( ! empty( $lesson_items ) ) {
				suredash_render_card_grid(
					$lesson_items,
					[
						'title'           => Labels::get_label( 'lesson_plural_text' ),
						'show_title'      => true,
						'columns'         => 3,
						'container_class' => 'portal-bookmarks-grid sd-responsive-justify-center sd-responsive-items-center',
					]
				);
			}

			// Display 'portal' items using new card grid.
			if ( ! empty( $portal_items ) ) {
				suredash_render_card_grid(
					$portal_items,
					[
						'title'           => Labels::get_label( 'portal_plural_text' ),
						'show_title'      => true,
						'columns'         => 3,
						'container_class' => 'portal-bookmarks-grid sd-responsive-justify-center sd-responsive-items-center',
					]
				);
			}

			// Display 'resource' items using new card grid.
			if ( ! empty( $resources_items ) ) {
				suredash_render_card_grid(
					$resources_items,
					[
						'title'           => Labels::get_label( 'resource_plural_text' ),
						'show_title'      => true,
						'columns'         => 3,
						'container_class' => 'portal-bookmarks-grid sd-responsive-justify-center sd-responsive-items-center',
					]
				);
			}

			// Display 'resource' items using new card grid.
			if ( ! empty( $events_items ) ) {
				suredash_render_card_grid(
					$events_items,
					[
						'title'           => Labels::get_label( 'event_plural_text' ),
						'show_title'      => true,
						'columns'         => 3,
						'container_class' => 'portal-bookmarks-grid',
					]
				);
			}

			// Display 'misc' items using new card grid.
			if ( ! empty( $misc_items ) ) {
				suredash_render_card_grid(
					$misc_items,
					[
						'title'           => Labels::get_label( 'misc_items_text' ),
						'show_title'      => true,
						'columns'         => 3,
						'container_class' => 'portal-bookmarks-grid sd-responsive-justify-center sd-responsive-items-center',
					]
				);
			}
			?>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Get home content.
	 *
	 * @since 1.0.0
	 * @return string|false
	 */
	public function get_home_content() {
		$home_page = Helper::get_option( 'home_page', 'default' );

		// Legacy support for previously selected page ID in alpha versions.
		if ( is_array( $home_page ) ) {
			$home_page_id = ! empty( $home_page['value'] ) ? absint( $home_page['value'] ) : 0;
			if ( $home_page_id ) {
				ob_start();
				if ( method_exists( SinglePost::get_instance(), 'get_integration_content' ) ) {
					echo '<div id="portal-post-' . esc_attr( (string) $home_page_id ) . '" class="portal-content-area sd-box-shadow portal-content-type-wordpress ' . ( suredash_is_post_by_block_editor( $home_page_id ) ? 'entry-content' : '' ) . '">';
					echo do_shortcode( apply_filters( 'the_content', SinglePost::get_instance()->get_integration_content( $home_page_id, true ), $home_page_id ) );
					echo '</div>';
				}
				return ob_get_clean();
			}
		}

		$results      = Controller::get_query_data(
			'Navigation',
		);
		$space_groups = array_reduce(
			$results,
			static function ( $carry, $item ) {
				if ( is_array( $item ) && is_array( $carry ) ) {
					$carry[ $item['space_group_position'] ][] = $item;
				}
				return $carry;
			},
			[]
		);

		ksort( $space_groups );

		foreach ( $space_groups as &$group ) {
			$id_sequence = array_unique( explode( ',', strval( $group[0]['space_position'] ) ) );
			usort(
				$group,
				static function ( $a, $b ) use ( $id_sequence ) {
					$a_index = array_search( $a['ID'], $id_sequence );
					$b_index = array_search( $b['ID'], $id_sequence );
					return $a_index - $b_index;
				}
			);
		}

		unset( $group );

		ob_start();

		if ( empty( $space_groups ) ) {
			$template_args = [
				'404_heading'    => Labels::get_label( 'empty_portal_welcome_heading' ),
				'not_found_text' => Labels::get_label( 'empty_portal_welcome_message' ),
				'empty_portal'   => true,
				'display_cta'    => false,
			];
			suredash_get_template_part( 'parts', '404', $template_args );
			return ob_get_clean();
		}

		?>
		<div class="portal-content-area portal-content portal-home-grid sd-flex-col sd-gap-16 sd-items-center">
			<?php
			foreach ( $space_groups as $space_group ) {
				$space_group_name  = $space_group[0]['name'] ?? '';
				$show_group_header = false;
				$spaces_to_proceed = [];
				$counter           = 0;

				// Check if home grid spaces is set for this group.
				$home_grid_spaces = [];
				if ( ! empty( $space_group[0]['homegrid_spaces'] ) ) {
					// Attempt to unserialize the homegrid_spaces data.
					$home_grid_spaces_raw = maybe_unserialize( $space_group[0]['homegrid_spaces'] );

					// If it's an array, use it directly.
					if ( is_array( $home_grid_spaces_raw ) ) {
						$home_grid_spaces = $home_grid_spaces_raw;
					}
				}

				if ( ! empty( $home_grid_spaces ) && is_array( $home_grid_spaces ) ) {
					// Create a map of all spaces in this group for quick lookup.
					$space_map = [];
					foreach ( $space_group as $space_item ) {
						if ( $space_item['post_status'] !== 'publish' ) {
							continue;
						}
						$space_map[ absint( $space_item['ID'] ) ] = $space_item;
					}

					foreach ( $home_grid_spaces as $space_data ) {
						$space_id = null;
						if ( is_array( $space_data ) && isset( $space_data['id'] ) ) {
							$space_id = absint( $space_data['id'] );
						} elseif ( is_scalar( $space_data ) ) {
							$space_id = absint( $space_data );
						}

						if ( suredash_is_space_hidden( absint( $space_id ) ) ) {
							continue;
						}

						// Only proceed if we have a valid ID and it exists in our space map.
						if ( $space_id && isset( $space_map[ $space_id ] ) ) {
							$space_item          = $space_map[ $space_id ];
							$show_group_header   = $space_item['hide_label'] === '0' ? true : false;
							$spaces_to_proceed[] = [
								'id'          => absint( $space_item['ID'] ),
								'space_icon'  => $space_item['item_emoji'] ?? '',
								'index'       => $counter + 1,
								'integration' => $space_item['integration'] ?? '',
							];
							$counter++;
							if ( $counter >= 3 ) {
								break;
							}
						}
					}
				} else {
					// Use the default behavior if no home grid spaces are specified.
					foreach ( $space_group as $space_item ) {
						if ( $space_item['post_status'] !== 'publish' ) {
							continue;
						}
						if ( isset( $space_item['integration'] ) && $space_item['integration'] === 'link' ) {
							continue;
						}

						if ( suredash_is_space_hidden( absint( $space_item['ID'] ) ) ) {
							continue;
						}
						$show_group_header   = $space_item['hide_label'] === '0' ? true : false;
						$spaces_to_proceed[] = [
							'id'          => absint( $space_item['ID'] ),
							'space_icon'  => $space_item['item_emoji'] ?? '',
							'index'       => $counter + 1,
							'integration' => $space_item['integration'] ?? '',
						];
						$counter++;
						if ( $counter >= 3 ) {
							break;
						}
					}
				}

				// Use the grid markup function to display the spaces.
				echo do_shortcode( $this->get_grid_markup( $spaces_to_proceed, $space_group_name, $show_group_header, '', 'narrow' ) );
			}
			?>
		</div>
		</div>
		<?php

		wp_reset_postdata();

		return ob_get_clean();
	}

	/**
	 * Show Feeds.
	 *
	 * @since 1.0.0
	 * @return string|false
	 */
	public function get_feeds_posts() {
		// Get validated sort preference using helper.
		$admin_default_sort = 'date_desc';
		$feeds_sort         = Helper::get_feeds_sort_preference( $admin_default_sort );

		// Parse sort parameters using helper.
		$sort_params = Helper::parse_feeds_sort_params( $feeds_sort );
		$order_by    = $sort_params['order_by'];
		$order       = $sort_params['order'];
		$meta_key    = $sort_params['meta_key'];

		// Build query args for Controller.
		$query_args = [
			'category_id'    => 0,
			'post_type'      => SUREDASHBOARD_FEED_POST_TYPE,
			'taxonomy'       => SUREDASHBOARD_FEED_TAXONOMY,
			'posts_per_page' => Helper::get_option( 'feeds_per_page', 5 ),
			'paged'          => 1,
			'order_by'       => $order_by,
			'order'          => $order,
		];

		// Add meta_key if needed.
		if ( ! empty( $meta_key ) ) {
			$query_args['meta_key'] = $meta_key;
		}

		$feeds_posts = Controller::get_query_data( 'Feeds', $query_args );

		// Get validated view preference using helper.
		$admin_default_view = Helper::get_option( 'feeds_default_view', 'grid' );
		$initial_view       = Helper::get_feeds_view_preference( $admin_default_view );

		ob_start();
		?>
		<div class="portal-content-area portal-content-type-posts_discussion portal-feed-posts">
			<?php

			if ( ! empty( $feeds_posts ) ) {
				Helper::render_feeds_controls(
					$feeds_sort,
					$initial_view,
					0,
					0,
					SUREDASHBOARD_FEED_POST_TYPE,
					SUREDASHBOARD_FEED_TAXONOMY,
					0,
					get_current_user_id()
				);
			}

			if ( empty( $feeds_posts ) ) {
				suredash_get_template_part( 'parts', '404' );
			}

			add_filter( 'suredash_skip_restricted_post', '__return_true' );

			?>
			<!-- Posts Container with view mode tracking -->
			<div class="portal-feeds-posts-container" data-view-mode="<?php echo esc_attr( $initial_view ); ?>">
				<?php
				if ( ! empty( $feeds_posts ) ) {
					// Render based on initial view setting.
					if ( $initial_view === 'list' ) {
						// List view rendering.
						$list_items = [];
						foreach ( $feeds_posts as $post ) {
							$post_id   = absint( $post['ID'] );
							$post_link = get_permalink( $post_id );

							// Build description: excerpt if available, or author name and date.
							$author_id   = get_post_field( 'post_author', $post_id );
							$author_name = suredash_get_user_display_name( (int) $author_id );
							$post_date   = suredash_get_relative_time( $post_id, false, true );
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
						foreach ( $feeds_posts as $post ) {
							Helper::render_post( $post, 0, false, true );
						}
					}
				}
				?>
			</div>
			<?php

			remove_filter( 'suredash_skip_restricted_post', '__return_true' );

			?>
		</div>
		<?php

		wp_reset_postdata();

		$pagination_markup = sprintf(
			'<div class="portal-pagination-loader">
				<div class="portal-pagination-loader-1"></div>
				<div class="portal-pagination-loader-2"></div>
				<div class="portal-pagination-loader-3"></div>
			</div>
			<div class="portal-infinite-trigger" data-post_type="%s" data-taxonomy="%s"></div>',
			SUREDASHBOARD_FEED_POST_TYPE,
			SUREDASHBOARD_FEED_TAXONOMY
		);

		echo wp_kses_post( $pagination_markup );

		return ob_get_clean();
	}

	/**
	 * Update user profile content.
	 *
	 * @since 1.0.0
	 * @return string|false
	 */
	public function get_user_profile_content() {
		if ( ! is_user_logged_in() ) {
			return '';
		}

		$user_id = get_current_user_id();

		$first_name_placeholder = __( 'Enter', 'suredash' ) . ' ' . Labels::get_label( 'first_name' );
		$last_name_placeholder  = __( 'Enter', 'suredash' ) . ' ' . Labels::get_label( 'last_name' );

		$first_name  = sd_get_user_meta( $user_id, 'first_name', true );
		$last_name   = sd_get_user_meta( $user_id, 'last_name', true );
		$cover_image = sd_get_user_meta( $user_id, 'user_banner_image', true );

		$first_name_value = ! empty( $first_name ) ? $first_name : '';
		$last_name_value  = ! empty( $last_name ) ? $last_name : '';

		$user_profile_fields = suredash_get_user_profile_fields();
		$portal_social_links = suredash_get_user_profile_social_links();

		$user_password_fields = [
			'new_password'         => [
				'label'       => Labels::get_label( 'new_password' ),
				'placeholder' => __( 'Create a new password', 'suredash' ),
				'value'       => '',
				'type'        => 'password',
			],
			'confirm_new_password' => [
				'label'       => Labels::get_label( 'confirm_new_password' ),
				'placeholder' => __( 'Re-enter your new password', 'suredash' ),
				'value'       => '',
				'type'        => 'password',
			],
		];

		// Get notification settings.
		$enable_all_email_notifications = sd_get_user_meta( $user_id, 'enable_all_email_notifications', true );
		$enable_admin_email             = sd_get_user_meta( $user_id, 'enable_admin_email', true );
		$email_on_post_replies          = sd_get_user_meta( $user_id, 'email_on_post_replies', true );
		$email_on_comment_replies       = sd_get_user_meta( $user_id, 'email_on_comment_replies', true );
		$email_on_mention               = sd_get_user_meta( $user_id, 'email_on_mention', true );

		// Get portal notification settings.
		$enable_all_portal_notifications        = sd_get_user_meta( $user_id, 'enable_all_portal_notifications', true );
		$enable_admin_portal_notification       = sd_get_user_meta( $user_id, 'enable_admin_portal_notification', true );
		$portal_notification_on_post_replies    = sd_get_user_meta( $user_id, 'portal_notification_on_post_replies', true );
		$portal_notification_on_comment_replies = sd_get_user_meta( $user_id, 'portal_notification_on_comment_replies', true );
		$portal_notification_on_mention         = sd_get_user_meta( $user_id, 'portal_notification_on_mention', true );

		// Default to enabled if not set.
		$enable_all_email_notifications = $enable_all_email_notifications !== '' ? $enable_all_email_notifications : '1';
		$enable_admin_email             = $enable_admin_email !== '' ? $enable_admin_email : '1';
		$email_on_post_replies          = $email_on_post_replies !== '' ? $email_on_post_replies : '1';
		$email_on_comment_replies       = $email_on_comment_replies !== '' ? $email_on_comment_replies : '1';
		$email_on_mention               = $email_on_mention !== '' ? $email_on_mention : '1';

		// Default portal notifications to enabled if not set.
		$enable_all_portal_notifications        = $enable_all_portal_notifications !== '' ? $enable_all_portal_notifications : '1';
		$enable_admin_portal_notification       = $enable_admin_portal_notification !== '' ? $enable_admin_portal_notification : '1';
		$portal_notification_on_post_replies    = $portal_notification_on_post_replies !== '' ? $portal_notification_on_post_replies : '1';
		$portal_notification_on_comment_replies = $portal_notification_on_comment_replies !== '' ? $portal_notification_on_comment_replies : '1';
		$portal_notification_on_mention         = $portal_notification_on_mention !== '' ? $portal_notification_on_mention : '1';

		$bg_prop    = ! empty( $cover_image ) ? '--portal-user-profile-banner: url(' . esc_url( $cover_image ) . ');' : '';
		$active_tab = ! empty( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( 'tab' ) ) : 'profile'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Frontend user profile view with proper sanitization.

		$profile_label      = Labels::get_label( 'profile' );
		$socials_label      = Labels::get_label( 'socials' );
		$password_label     = Labels::get_label( 'password' );
		$notification_label = __( 'Notifications', 'suredash' );

		$is_pro_active = suredash_is_pro_active();

		ob_start();
		?>
		<div class="portal-user-profile-main portal-content">
			<div class="portal-user-profile-editor-header">
				<div class="portal-user-profile-tabs-wrapper">
					<span class="portal-user-profile-tab <?php echo esc_attr( $active_tab === 'profile' ? 'active' : '' ); ?>" data-tab="profile">
						<span class="sd-flex" title="<?php echo esc_attr( $profile_label ); ?>">
						<?php
						Helper::get_library_icon(
							'UserRoundPen',
							true,
							'sm',
							'tooltip-trigger',
							[
								'tooltip-description' => $profile_label,
								'tooltip-position'    => 'bottom',
							]
						);
						?>
						</span>
						<span class="portal-user-profile-tab-name"> <?php echo esc_html( $profile_label ); ?> </span>
					</span>
					<span class="portal-user-profile-tab <?php echo esc_attr( $active_tab === 'socials' ? 'active' : '' ); ?>" data-tab="socials">
						<span class="sd-flex" title="<?php echo esc_attr( $socials_label ); ?>">
						<?php
						Helper::get_library_icon(
							'Hash',
							true,
							'sm',
							'tooltip-trigger',
							[
								'tooltip-description' => $socials_label,
								'tooltip-position'    => 'bottom',
							]
						);
						?>
						</span>
						<span class="portal-user-profile-tab-name"> <?php echo esc_html( $socials_label ); ?> </span>
					</span>
					<span class="portal-user-profile-tab <?php echo esc_attr( $active_tab === 'password' ? 'active' : '' ); ?>" data-tab="password">
						<span class="sd-flex" title="<?php echo esc_attr( $password_label ); ?>">
						<?php
						Helper::get_library_icon(
							'RotateCcwKey',
							true,
							'sm',
							'tooltip-trigger',
							[
								'tooltip-description' => $password_label,
								'tooltip-position'    => 'bottom',
							]
						);
						?>
						</span>
						<span class="portal-user-profile-tab-name"> <?php echo esc_html( $password_label ); ?> </span>
					</span>
					<span class="portal-user-profile-tab <?php echo esc_attr( $active_tab === 'notifications' ? 'active' : '' ); ?>" data-tab="notifications">
						<span class="sd-flex" title="<?php echo esc_attr( $notification_label ); ?>">
						<?php
						Helper::get_library_icon(
							'Bell',
							true,
							'sm',
							'tooltip-trigger',
							[
								'tooltip-description' => esc_attr( $notification_label ),
								'tooltip-position'    => 'bottom',
							]
						);
						?>
						</span>
						<span class="portal-user-profile-tab-name"> <?php echo esc_html( $notification_label ); ?> </span>
					</span>
				</div>

				<button class="portal-button button-primary portal-user-profile-editor-save">
					<?php
						Labels::get_label( 'save', true );
						Helper::get_library_icon( 'LoaderCircle', true, 'sm', 'sd-display-none' );
					?>
				</button>
			</div>

			<div class="portal-user-profile-editor-wrap portal-content-area sd-box-shadow">
				<div class="portal-user-view-inner-content <?php echo esc_attr( $active_tab === 'profile' ? 'active' : '' ); ?>" data-tab="profile">
					<div class="portal-user-profile-editor-avatar">
						<label for="profile-photo"> <?php Labels::get_label( 'profile_photo', true ); ?> </label>
						<div class="portal-user-profile-gravatar-setup">
							<?php suredash_get_user_avatar( $user_id, true, 40, true ); ?>
							<div class="portal-user-profile-photo-upload">
								<button class="portal-button button-secondary sd-pointer"> <?php echo esc_html__( 'Upload', 'suredash' ); ?> </button>
								<?php
								$user_profile_photo  = sd_get_user_meta( $user_id, 'user_profile_photo', true );
								$remove_button_class = ! empty( $user_profile_photo ) ? '' : ' hidden';
								?>
								<button class="portal-button button-ghost sd-pointer portal-user-profile-photo-remove<?php echo esc_attr( $remove_button_class ); ?>"> <?php echo esc_html__( 'Remove', 'suredash' ); ?> </button>
								<?php suredash_image_uploader_field( '', 'user_profile_photo', true ); ?>
							</div>
						</div>
					</div>

					<div class="portal-user-profile-editor-fields">
						<div class="portal-user-profile-cover-banner">
							<div class="sd-flex sd-justify-between sd-items-center">
								<label for="profile-photo"> <?php esc_html_e( 'Cover Image', 'suredash' ); ?> </label>
		<?php
								$remove_cover_button_class = ! empty( $cover_image ) ? '' : ' sd-hidden';
		?>
								<button type="button" class="portal-button button-ghost sd-pointer sd-p-0 sd-opacity-75 portal-user-cover-image-remove tooltip-trigger<?php echo esc_attr( $remove_cover_button_class ); ?>" data-tooltip-description="<?php echo esc_attr__( 'Reset', 'suredash' ); ?>" data-tooltip-position="top">
									<?php Helper::get_library_icon( 'RotateCcw' ); ?>
								</button>
							</div>
							<div class="portal-user-profile-cover-image-field" style="<?php echo esc_attr( $bg_prop ); ?>">
								<?php suredash_image_uploader_field( '', 'user_banner_image' ); ?>
							</div>
						</div>
					</div>

					<div class="portal-user-profile-editor-fields">
						<div class="portal-name-field sd-mobile-flex-col">
							<div class="portal-fname-wrap">
								<label for="first_name"> <?php Labels::get_label( 'first_name', true ); ?> </label>
								<input type="text" name="first_name" id="first_name" value="<?php echo esc_attr( $first_name_value ); ?>" placeholder="<?php echo esc_attr( $first_name_placeholder ); ?>">
							</div>

							<div class="portal-lname-wrap">
								<label for="last_name"> <?php Labels::get_label( 'last_name', true ); ?> </label>
								<input type="text" name="last_name" id="last_name" value="<?php echo esc_attr( $last_name_value ); ?>" placeholder="<?php echo esc_attr( $last_name_placeholder ); ?>">
							</div>
						</div>
					</div>

					<div class="portal-user-profile-editor-fields">
						<?php
						foreach ( $user_profile_fields as $field => $field_data ) {
							?>
							<div class="portal-user-profile-editor-field">
								<label for="<?php echo esc_attr( $field ); ?>"> <?php echo esc_html( $field_data['label'] ); ?> </label>
								<?php
								if ( $field_data['type'] === 'textarea' ) {
									?>
									<textarea name="<?php echo esc_attr( $field ); ?>" id="<?php echo esc_attr( $field ); ?>" placeholder="<?php echo esc_attr( $field_data['placeholder'] ); ?>" rows="4"><?php echo esc_html( $field_data['value'] ); ?></textarea>
									<?php
								} else {
									$input_type = $field_data['type'] === 'password' ? 'password' : 'text';
									?>
									<input type="<?php echo esc_attr( $input_type ); ?>" name="<?php echo esc_attr( $field ); ?>" id="<?php echo esc_attr( $field ); ?>" value="<?php echo esc_attr( $field_data['value'] ); ?>" placeholder="<?php echo esc_attr( $field_data['placeholder'] ); ?>">
									<?php
								}
								?>
							</div>
						<?php } ?>
					</div>
				</div>

				<div class="portal-user-view-inner-content <?php echo esc_attr( $active_tab === 'socials' ? 'active' : '' ); ?>" data-tab="socials">
					<div class="portal-user-profile-editor-fields">
						<?php
						foreach ( $portal_social_links as $field => $field_data ) {
							?>
							<div class="portal-user-profile-editor-field">
								<label for="<?php echo esc_attr( $field ); ?>"> <?php echo esc_html( $field_data['label'] ); ?> </label>
								<input type="text" name="<?php echo esc_attr( $field ); ?>" id="<?php echo esc_attr( $field ); ?>" value="<?php echo esc_attr( $field_data['value'] ); ?>" placeholder="<?php echo esc_attr( $field_data['placeholder'] ); ?>" />
							</div>
							<?php
						}
						?>
					</div>
				</div>

				<div class="portal-user-view-inner-content <?php echo esc_attr( $active_tab === 'password' ? 'active' : '' ); ?>" data-tab="password">
					<div class="portal-user-profile-editor-fields">
						<?php
						foreach ( $user_password_fields as $field => $field_data ) {
							if ( ! is_array( $field_data ) ) {
								continue;
							}
							?>
							<div class="portal-user-profile-editor-field">
								<label for="<?php echo esc_attr( $field ); ?>"> <?php echo esc_html( $field_data['label'] ); ?> </label>
								<?php
								if ( $field_data['type'] === 'textarea' ) { // @phpstan-ignore-line
									?>
									<textarea name="<?php echo esc_attr( $field ); ?>" id="<?php echo esc_attr( $field ); ?>" placeholder="<?php echo esc_attr( $field_data['placeholder'] ); ?>" rows="4"><?php echo esc_html( $field_data['value'] ); ?></textarea>
									<?php
								} else {
									$input_type = $field_data['type'] === 'password' ? 'password' : 'text'; // @phpstan-ignore-line
									?>
									<input type="<?php echo esc_attr( $input_type ); ?>" name="<?php echo esc_attr( $field ); ?>" id="<?php echo esc_attr( $field ); ?>" value="<?php echo esc_attr( $field_data['value'] ); ?>" placeholder="<?php echo esc_attr( $field_data['placeholder'] ); ?>" autocomplete="new-password">
									<?php
								}
								?>
							</div>
							<?php
						}
						?>
					</div>
				</div>

				<div class="portal-user-view-inner-content <?php echo esc_attr( $active_tab === 'notifications' ? 'active' : '' ); ?>" data-tab="notifications">
					<!-- Notification Header -->
					<div class="portal-notification-header sd-flex sd-justify-between sd-items-center">
						<span class="portal-notification-title"><?php echo esc_html__( 'Notifications', 'suredash' ); ?></span>
						<div class="portal-notification-columns sd-flex sd-gap-24">
							<?php if ( $is_pro_active ) { ?>
								<span class="portal-notification-title"><?php echo esc_html__( 'Email', 'suredash' ); ?></span>
							<?php } ?>
							<span class="portal-notification-title"><?php echo esc_html__( 'Portal', 'suredash' ); ?></span>
						</div>
					</div>
					<div class="portal-user-profile-editor-fields">
						<!-- Receive All Notifications -->
						<div class="portal-notification-item sd-flex sd-justify-between sd-items-center">
							<label class="portal-notification-label"><?php echo esc_html__( 'Receive all notifications', 'suredash' ); ?></label>
							<div class="portal-notification-toggles sd-flex sd-gap-24">
								<?php if ( $is_pro_active ) { ?>
									<label class="portal-notification-toggle sd-relative">
										<input type="checkbox" name="enable_all_email_notifications" class="portal-notification-checkbox sd-absolute sd-opacity-0"<?php checked( $enable_all_email_notifications, '1' ); ?>>
										<span class="portal-notification-slider sd-block sd-bg-gray-300 sd-rounded-full sd-transition-all"></span>
									</label>
								<?php } ?>
								<label class="portal-notification-toggle sd-relative">
									<input type="checkbox" name="enable_all_portal_notifications" class="portal-notification-checkbox sd-absolute sd-opacity-0"<?php checked( $enable_all_portal_notifications, '1' ); ?>>
									<span class="portal-notification-slider sd-block sd-bg-gray-300 sd-rounded-full sd-transition-all"></span>
								</label>
							</div>
						</div>

						<!-- Receive Admin Updates -->
						<div class="portal-notification-item sd-flex sd-justify-between sd-items-center">
							<label class="portal-notification-label"><?php echo esc_html__( 'Receive admin notifications', 'suredash' ); ?></label>
							<div class="portal-notification-toggles sd-flex sd-gap-24">
								<?php if ( $is_pro_active ) { ?>
									<label class="portal-notification-toggle sd-relative">
										<input type="checkbox" name="enable_admin_email" class="portal-notification-checkbox sd-absolute sd-opacity-0"<?php checked( $enable_admin_email, '1' ); ?>>
										<span class="portal-notification-slider sd-block sd-bg-gray-300 sd-rounded-full sd-transition-all"></span>
									</label>
								<?php } ?>
								<label class="portal-notification-toggle sd-relative">
									<input type="checkbox" name="enable_admin_portal_notification" class="portal-notification-checkbox sd-absolute sd-opacity-0"<?php checked( $enable_admin_portal_notification, '1' ); ?>>
									<span class="portal-notification-slider sd-block sd-bg-gray-300 sd-rounded-full sd-transition-all"></span>
								</label>
							</div>
						</div>

						<!-- Reply to My Post -->
						<div class="portal-notification-item sd-flex sd-justify-between sd-items-center">
							<label class="portal-notification-label"><?php echo esc_html__( 'When someone replies to my post', 'suredash' ); ?></label>
							<div class="portal-notification-toggles sd-flex sd-gap-24">
								<?php if ( $is_pro_active ) { ?>
									<label class="portal-notification-toggle sd-relative">
										<input type="checkbox" name="email_on_post_replies" class="portal-notification-checkbox sd-absolute sd-opacity-0"<?php checked( $email_on_post_replies, '1' ); ?>>
										<span class="portal-notification-slider sd-block sd-bg-gray-300 sd-rounded-full sd-transition-all"></span>
									</label>
								<?php } ?>
								<label class="portal-notification-toggle sd-relative">
									<input type="checkbox" name="portal_notification_on_post_replies" class="portal-notification-checkbox sd-absolute sd-opacity-0"<?php checked( $portal_notification_on_post_replies, '1' ); ?>>
									<span class="portal-notification-slider sd-block sd-bg-gray-300 sd-rounded-full sd-transition-all"></span>
								</label>
							</div>
						</div>

						<!-- Reply to My Comment -->
						<div class="portal-notification-item sd-flex sd-justify-between sd-items-center">
							<label class="portal-notification-label"><?php echo esc_html__( 'When someone replies to my comment', 'suredash' ); ?></label>
							<div class="portal-notification-toggles sd-flex sd-gap-24">
								<?php if ( $is_pro_active ) { ?>
									<label class="portal-notification-toggle sd-relative">
										<input type="checkbox" name="email_on_comment_replies" class="portal-notification-checkbox sd-absolute sd-opacity-0"<?php checked( $email_on_comment_replies, '1' ); ?>>
										<span class="portal-notification-slider sd-block sd-bg-gray-300 sd-rounded-full sd-transition-all"></span>
									</label>
								<?php } ?>
								<label class="portal-notification-toggle sd-relative">
									<input type="checkbox" name="portal_notification_on_comment_replies" class="portal-notification-checkbox sd-absolute sd-opacity-0"<?php checked( $portal_notification_on_comment_replies, '1' ); ?>>
									<span class="portal-notification-slider sd-block sd-bg-gray-300 sd-rounded-full sd-transition-all"></span>
								</label>
							</div>
						</div>

						<!-- Mention Notifications -->
						<div class="portal-notification-item sd-flex sd-justify-between sd-items-center">
							<label class="portal-notification-label"><?php echo esc_html__( 'When I\'m mentioned', 'suredash' ); ?></label>
							<div class="portal-notification-toggles sd-flex sd-gap-24">
								<?php if ( $is_pro_active ) { ?>
									<label class="portal-notification-toggle sd-relative">
										<input type="checkbox" name="email_on_mention" class="portal-notification-checkbox sd-absolute sd-opacity-0"<?php checked( $email_on_mention, '1' ); ?>>
										<span class="portal-notification-slider sd-block sd-bg-gray-300 sd-rounded-full sd-transition-all"></span>
									</label>
								<?php } ?>
								<label class="portal-notification-toggle sd-relative">
									<input type="checkbox" name="portal_notification_on_mention" class="portal-notification-checkbox sd-absolute sd-opacity-0"<?php checked( $portal_notification_on_mention, '1' ); ?>>
									<span class="portal-notification-slider sd-block sd-bg-gray-300 sd-rounded-full sd-transition-all"></span>
								</label>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Get user view content.
	 *
	 * @since 1.0.0
	 * @return string|false
	 */
	public function get_user_view_content() {
		if ( ! is_user_logged_in() ) {
			return '';
		}

		$user_id = get_query_var( 'user_id' ) ? absint( get_query_var( 'user_id' ) ) : get_current_user_id();

		$user = get_userdata( $user_id );

		$first_name   = sd_get_user_meta( $user_id, 'first_name', true );
		$last_name    = sd_get_user_meta( $user_id, 'last_name', true );
		$display_name = suredash_get_user_display_name( $user_id );
		$description  = ! empty( $user->description ) ? $user->description : '';

		$website     = ! empty( $user->user_url ) ? $user->user_url : '';
		$cover_image = sd_get_user_meta( $user_id, 'user_banner_image', true );

		if ( empty( $cover_image ) ) {
			$cover_image = Helper::get_banner_placeholder_image();
		}

		$posts_label    = Labels::get_label( 'posts' );
		$comments_label = Labels::get_label( 'comments' );
		$about_label    = Labels::get_label( 'about' );

		ob_start();

		?>
		<div class="portal-user-profile-user-view-wrap portal-content portal-content-area">
			<div class="portal-user-profile-user-details-wrap">
				<div class="portal-user-view-header sd-mt-30 sd-mb-custom" style="--sd-mb-custom: 24px;">
					<img src="<?php echo esc_url( $cover_image ); ?>" alt="<?php echo esc_attr( $first_name ); ?>">
					<div class="portal-user-view-overlay">
						<div class="portal-user-profile-editor-avatar portal-user-view-details">
							<?php suredash_get_user_avatar( $user_id, true, 96 ); ?>
							<div class="portal-user-intro-details">
								<div class="portal-user-name-wrapper">
									<div class="sd-flex sd-items-center sd-gap-8 portal-user-details-inner-wrap">
										<span class="sd-no-space sd-font-18 sd-font-semibold">
											<span class="portal-user-view-fname"> <?php echo esc_html( $first_name ); ?> </span>
											<span class="portal-user-view-lname"> <?php echo esc_html( $last_name ); ?> </span>
											<?php
											if ( empty( $first_name ) && empty( $last_name ) ) {
												echo '<span class="portal-user-view-name">' . esc_html( $display_name ) . '</span>';
											}
											?>
										</span>
										<?php
										do_action( 'suredash_before_user_badges', $user_id );
										suredash_get_user_badges( $user_id, 4 );
										?>
									</div>
									<div class="portal-thread-details">
										<?php
											$user_headline = sd_get_user_meta( $user_id, 'headline', true );
										if ( ! empty( $user_headline ) ) {
											?>
												<span class="sd-font-14"><?php echo esc_html( strval( $user_headline ) ); ?></span><span class="sd-hide-mobile">|</span>
												<?php
										}

										$register_date     = ! empty( $user->user_registered ) ? $user->user_registered : '';
										$member_since_time = strtotime( $register_date );
										if ( ! empty( $member_since_time ) ) {
											?>
													<span class="sd-font-14 sd-hide-mobile">
													<?php
														Helper::get_library_icon( 'Calendar', true, 'sm', 'sd-mr-4' );
														Labels::get_label( 'member_since', true );
														echo ' ' . esc_attr( date_i18n( 'F Y', $member_since_time ) );
													?>
													</span>
												<?php
										}
										?>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>

				<div class="sd-flex sd-items-center sd-justify-between sd-user-view-tab-links">
					<div class="portal-user-profile-tabs-wrapper">
						<span class="portal-user-profile-tab active" data-tab="posts">
							<span class="sd-flex" title="<?php echo esc_attr( $posts_label ); ?>">
							<?php
							Helper::get_library_icon(
								'Newspaper',
								true,
								'sm',
								'tooltip-trigger',
								[
									'tooltip-description' => $posts_label,
									'tooltip-position'    => 'bottom',
								]
							);
							?>
							</span>
							<span class="portal-user-profile-tab-name"> <?php echo esc_html( $posts_label ); ?> </span>
						</span>
						<span class="portal-user-profile-tab" data-tab="comments">
							<span class="sd-flex" title="<?php echo esc_attr( $comments_label ); ?>">
							<?php
							Helper::get_library_icon(
								'MessageCircle',
								true,
								'sm',
								'tooltip-trigger',
								[
									'tooltip-description' => $comments_label,
									'tooltip-position'    => 'bottom',
								]
							);
							?>
							</span>
							<span class="portal-user-profile-tab-name"> <?php echo esc_html( $comments_label ); ?> </span>
						</span>
						<span class="portal-user-profile-tab" data-tab="about">
							<span class="sd-flex" title="<?php echo esc_attr( $about_label ); ?>">
							<?php
							Helper::get_library_icon(
								'Contact',
								true,
								'sm',
								'tooltip-trigger',
								[
									'tooltip-description' => $about_label,
									'tooltip-position'    => 'bottom',
								]
							);
							?>
							</span>
							<span class="portal-user-profile-tab-name"> <?php echo esc_html( $about_label ); ?> </span>
						</span>
					</div>

					<span class="portal-user-view-socials">
						<?php
						if ( ! empty( $website ) ) {
							?>
								<a href="<?php echo esc_url( $website ); ?>" target="_blank">
								<?php Helper::get_library_icon( 'Globe', true, 'sm', 'sd-text-color' ); ?>
								</a>
							<?php
						}

						$user_social_links = suredash_get_user_profile_social_links( $user_id );
						if ( ! empty( $user_social_links ) ) {
							foreach ( $user_social_links as $handle => $link_data ) {
								if ( empty( $link_data['value'] ) ) {
									continue;
								}

								$social_link = $link_data['value'];
								if ( $handle === 'mail' ) {
									?>
										<a href="mailto:<?php echo esc_attr( $social_link ); ?>" target="_blank">
										<?php Helper::get_library_icon( $link_data['icon'] ?? 'Link', true, 'sm', 'sd-text-color' ); ?>
										</a>
									<?php
								} else {
									?>
										<a href="<?php echo esc_url( $social_link ); ?>" target="_blank">
										<?php Helper::get_library_icon( $link_data['icon'], true, 'sm', 'sd-text-color' ); ?>
										</a>
									<?php
								}
							}
						}
						?>
					</span>
				</div>

				<div class="portal-user-view-inner-content sd-mt-24 active" data-tab="posts">
					<?php
					$show_user_posts = apply_filters( 'suredash_user_view_show_posts', true, $user_id );
					if ( $show_user_posts ) {
						Helper::suredash_user_posts( $user_id );

						$pagination_markup = sprintf(
							'
								<div class="portal-pagination-loader">
									<div class="portal-pagination-loader-1"></div>
									<div class="portal-pagination-loader-2"></div>
									<div class="portal-pagination-loader-3"></div>
								</div>
								<div class="portal-infinite-trigger" data-post_type="%s" data-user_id="%s"></div>
							',
							SUREDASHBOARD_FEED_POST_TYPE,
							$user_id
						);

						echo wp_kses_post( $pagination_markup );
					} else {
						suredash_get_restricted_template_part(
							0,
							'parts',
							'restricted',
							[
								'icon'                   => 'Lock',
								'label'                  => 'restricted_content',
								'description'            => 'restricted_content_description',
								'skip_restriction_check' => true,
							]
						);
					}
					?>
				</div>

				<div class="portal-user-view-inner-content sd-mt-24" data-tab="comments">
					<?php
					$show_user_comments = apply_filters( 'suredash_user_view_show_comments', true, $user_id );
					if ( $show_user_comments ) {
						Helper::suredash_user_comments( $user_id );

						$comments_pagination_markup = sprintf(
							'<div class="portal-pagination-loader">
								<div class="portal-pagination-loader-1"></div>
								<div class="portal-pagination-loader-2"></div>
								<div class="portal-pagination-loader-3"></div>
							</div>
							<div class="portal-infinite-trigger-comments" data-user_id="%s"></div>',
							$user_id
						);

						echo wp_kses_post( $comments_pagination_markup );
					} else {
						suredash_get_restricted_template_part(
							0,
							'parts',
							'restricted',
							[
								'icon'                   => 'Lock',
								'label'                  => 'restricted_content',
								'description'            => 'restricted_content_description',
								'skip_restriction_check' => true,
							]
						);
					}
					?>
				</div>

				<div class="portal-user-view-inner-content sd-mt-24" data-tab="about">
					<?php
					if ( empty( $description ) ) {
						suredash_get_template_part(
							'parts',
							'404',
							[
								'not_found_text' => '',
								'display_cta'    => false,
							]
						);
					} else {
						?>
							<div class="portal-user-view-bio-wrapper portal-content sd-box-shadow sd-radius-12 sd-border sd-p-container">
							<?php echo do_shortcode( wpautop( $description ) ); ?>
							</div>
						<?php
					}
					?>
				</div>
			</div>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Show screen content as per the screen type.
	 *
	 * @since 1.5.0
	 * @return string|bool
	 */
	public function get_screen_content() {
		$screen = get_query_var( 'screen_id' ) ? sanitize_text_field( get_query_var( 'screen_id' ) ) : '';

		if ( ! is_user_logged_in() ) {
			return false;
		}

		if ( empty( $screen ) ) {
			suredash_get_template_part( 'parts', '404' );
			return false;
		}

		if ( ! suredash_screen() ) {
			suredash_get_template_part( 'parts', '404' );
			return false;
		}

		ob_start();

		switch ( $screen ) {
			case 'notification':
				echo '<section class="portal-content sd-p-20"> <div class="sd-border sd-shadow-sm sd-bg-content sd-notification-list">';
				Notification::get_instance()->get_user_notification_list( false );
				echo '</div></section>';
				break;
			default:
				suredash_get_template_part( 'parts', '404' );
				break;
		}

		return ob_get_clean();
	}

}
