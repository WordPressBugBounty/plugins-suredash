<?php
/**
 * Portals Docs Navigation Shortcode Initialize.
 *
 * @package SureDash
 */

namespace SureDashboard\Core\Shortcodes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use SureDashboard\Core\Models\Controller;
use SureDashboard\Inc\Traits\Get_Instance;
use SureDashboard\Inc\Traits\Shortcode;
use SureDashboard\Inc\Utils\Helper;
use SureDashboard\Inc\Utils\Labels;
use SureDashboard\Inc\Utils\PostMeta;

/**
 * Class Navigation Shortcode.
 */
class Navigation {
	use Shortcode;
	use Get_Instance;

	/**
	 * Register_shortcode_event.
	 *
	 * @return void
	 */
	public function register_shortcode_event(): void {
		$this->add_shortcode( 'navigation' );
	}

	/**
	 * Display portal navigation.
	 *
	 * @param array<mixed> $atts Array of attributes.
	 * @since 1.0.0
	 * @return string|false
	 */
	public function render_navigation( $atts ) {
		$atts = apply_filters(
			'suredash_navigation_attributes',
			shortcode_atts(
				[
					'show_only_navigation' => false,
				],
				$atts
			)
		);

		ob_start();

		$this->process_side_navigation_query( $atts );

		return ob_get_clean();
	}

	/**
	 * Get the global portal query.
	 *
	 * @param array<mixed> $attr shortcode attributes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function process_side_navigation_query( $attr ): void {
		$show_only_navigation = boolval( $attr['show_only_navigation'] ) ? true : false;

		$results = Controller::get_query_data( 'Navigation' );

		$space_groups = array_reduce(
			$results,
			static function( $carry, $item ) {
				if ( is_array( $item ) && is_array( $carry ) ) {
					$carry[ $item['space_group_position'] ][] = $item;
				}
				return $carry;
			},
			[]
		);

		if ( ! $show_only_navigation ) {
			suredash_get_template_part( 'parts', 'identity' );
		}

		if ( ! empty( $space_groups ) ) {
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

			// Prime post meta for every space in a single query before the
			// per-space visibility filter runs below. `suredash_is_space_hidden()`
			// reads space meta (e.g. `hidden_space`, and the SureMembers
			// `sm_space_ruleset` / `sm_space_membership_rule` keys in Pro) for
			// each space; without priming, the first read for each space lazily
			// fires its own meta query. Batching collapses ~N queries into one.
			$space_ids_to_prime = [];
			foreach ( $space_groups as $space_group ) {
				foreach ( $space_group as $space_item ) {
					if ( ! empty( $space_item['ID'] ) ) {
						$space_ids_to_prime[] = (int) $space_item['ID'];
					}
				}
			}
			if ( ! empty( $space_ids_to_prime ) ) {
				update_meta_cache( 'post', array_values( array_unique( $space_ids_to_prime ) ) );
			}

			$sub_query          = suredash_get_sub_queried_page();
			$is_feeds           = $sub_query === 'feeds';
			$is_portal_home     = suredash_is_home();
			$current_space_id   = get_the_ID();
			$active_portal_page = Helper::get_active_portal_page_target();
			// On sub-query routes (e.g. /portal/bookmarks/, /portal/members/),
			// `get_the_ID()` returns whichever portal post WP fell back to —
			// often the home_page setting or the first post in a default
			// query — which has nothing to do with what's being rendered.
			// Don't trust it for active-state matching: the post-ID equality
			// check is only meaningful for singular portal CPT pages.
			$is_sub_query_route     = $sub_query !== '';
			$collapsible_navigation = apply_filters( 'suredashboard_enable_collapsible_navigation', false );
			?>

			<div class="portal-aside-list-wrapper">
				<div class="portal-aside-group-wrap <?php echo esc_attr( $collapsible_navigation ? 'pfd-collapsible-enabled' : '' ); ?>">
					<?php
					$show_feed = Helper::get_option( 'enable_feeds' );
					if ( $show_feed ) {
						$feed_url   = get_home_url() . '/' . suredash_get_community_slug() . '/feeds';
						$feeds_icon = Helper::get_option( 'feeds_icon', 'Newspaper' );
						?>
							<a href="<?php echo esc_url( $feed_url ); ?>" class="portal-aside-feed portal-aside-group-body portal-feeds <?php echo esc_attr( $is_feeds ? 'active' : '' ); ?>">
								<?php Helper::get_library_icon( apply_filters( 'suredash_feeds_icon', $feeds_icon ), true, 'sm', 'portal-feeds-icon' ); ?>
								<span class="portal-aside-feed-text"> <?php Labels::get_label( 'feeds_label', true ); ?> </span>
							</a>
						<?php
					}
					foreach ( $space_groups as $space_group ) {
						// Single pass: collect renderable spaces; skip group if none are visible.
						$visible_spaces = array_filter(
							$space_group,
							static function ( $space_item ) {
								$post_id = (int) $space_item['ID'];
								return $post_id && $space_item['post_status'] === 'publish' && ! suredash_is_space_hidden( $post_id );
							}
						);
						if ( empty( $visible_spaces ) ) {
							continue;
						}

						$is_hide_label    = boolval( $space_group[0]['hide_label'] ?? false );
						$space_term_id    = absint( $space_group[0]['term_id'] ?? '' );
						$space_group_name = $space_group[0]['name'] ?? '';
						?>
						<div class="portal-aside-group <?php echo esc_attr( $is_hide_label ? 'pinned-group' : '' ); ?>" data-id="<?php echo esc_attr( (string) $space_term_id ); ?>">
							<?php if ( ! $is_hide_label ) { ?>
								<div class="portal-aside-group-header">
									<span class="portal-aside-group-title-link sd-no-space"> <h5 class="portal-aside-group-title"><?php echo esc_html( $space_group_name ); ?></h5> </span>
									<button
										class="sd-aside-group-toggle portal-button button-ghost"
										aria-label="
										<?php
										/* translators: %s: Space group name */
										printf( esc_attr__( 'Toggle %s group', 'suredash' ), esc_html( $space_group_name ) );
										?>
										"
										aria-expanded="true"
										aria-controls="group-content-<?php echo esc_attr( (string) $space_term_id ); ?>">
										<?php Helper::get_library_icon( 'ChevronDown', true, 'md' ); ?>
									</button>
								</div>
							<?php } ?>

							<div id="group-content-<?php echo esc_attr( (string) $space_term_id ); ?>" class="portal-aside-group-body">
								<ul role="list" class="portal-aside-group-list sd-no-space">
									<?php
									foreach ( $visible_spaces as $space_item ) {
										$post_id = (int) $space_item['ID'];

										$content_type   = $space_item['integration'] ?? '';
										$layout         = $space_item['layout'] ?? '';
										$featured_image = $space_item['image_url'] ?? '';
										$icon           = $space_item['item_emoji'] ?? 'Link';
										$link_target    = $space_item['link_target'] ?? '';
										$link_attr      = $content_type === 'link' ? 'target="' . esc_attr( $link_target ) . '"' : '';

										if ( $content_type === 'link' ) {
											$link = $space_item['link_url'];
										} elseif ( $content_type === 'portal_page' ) {
											$link = Helper::get_portal_page_url( (string) ( $space_item['portal_page_target'] ?? '' ) );
										} else {
											$link = get_permalink( $post_id );
										}

										// Portal Page spaces don't carry the visited post's ID, so the
										// post-ID equality check below would never fire — match on the
										// resolved target instead so the sidebar highlights correctly.
										$is_active_portal_page = $content_type === 'portal_page'
											&& ! empty( $space_item['portal_page_target'] )
											&& $space_item['portal_page_target'] === $active_portal_page;
										// `portal-active-sub-link` survives `highlightActiveLink()` in
										// single.js — that helper strips and re-adds `active` based on
										// `data-post_id`, but it always re-applies `active` to links with
										// this class. Use it to keep Portal Page sidebar items lit up
										// because they don't have a post-ID-based current page.
										$active_class = ! $is_portal_home && ! $is_sub_query_route && $post_id === $current_space_id ? ' active' : '';
										if ( $is_active_portal_page ) {
											$active_class .= ' active portal-active-sub-link';
										}

										do_action( 'suredash_before_aside_navigation_item', $post_id );

										if ( suredash_is_private_discussion_area( $post_id ) ) {
											$icon = 'Lock';
										}
										$icon = apply_filters( 'suredash_aside_navigation_space_icon_' . $post_id, Helper::get_library_icon( $icon, false, 'md' ), $post_id );

										// Get forum/space ID for unread count.
										// For 'posts_discussion' or 'category' integration types, check the feed_group_id meta.
										$forum_id     = 0;
										$unread_count = 0;

										if ( in_array( $content_type, [ 'posts_discussion', 'category' ], true ) ) {
											$forum_id = absint( PostMeta::get_post_meta_value( $post_id, 'feed_group_id' ) );

											// Get unread count for this space.
											if ( $forum_id && is_user_logged_in() ) {
												$unread_count = suredash_get_space_unread_count( get_current_user_id(), $forum_id );
											}
										}

										// Get additional accessibility attributes for the navigation item.
										$link_attributes = [
											'class'        => 'portal-aside-group-link' . $active_class,
											'data-post_id' => $post_id,
											'data-space_type' => $content_type,
											'data-layout_type' => $layout,
											'data-featured_image' => ! empty( $featured_image ),
											'data-forum_id' => $forum_id,
											'href'         => esc_url( $link ),
										];

										// Apply custom attributes filter for accessibility.
										$link_attributes = apply_filters( 'suredash_navigation_item_attributes_' . $post_id, $link_attributes );

										// Build attributes string.
										$attributes_str = '';
										foreach ( $link_attributes as $attr => $value ) {
											$attributes_str .= ' ' . $attr . '="' . esc_attr( $value ) . '"';
										}

										// Build unread badge HTML.
										$unread_badge = '';
										if ( $unread_count > 0 ) {
											$unread_badge = '<span class="sd-unread-badge" data-forum-id="' . esc_attr( (string) $forum_id ) . '">' . esc_html( (string) $unread_count ) . '</span>';
										}

										echo do_shortcode( '<li class="sd-no-space"> <a ' . $link_attr . $attributes_str . '>' . $icon . '<span class="portal-aside-item-title">' . esc_html( $space_item['post_title'] ) . '</span>' . $unread_badge . '</a> </li>' );

										do_action( 'suredash_after_aside_navigation_item', $post_id );
									}
									?>
								</ul>
							</div>
						</div>
						<?php
					}
					?>
				</div>
			</div>
			<?php
		} else {
			?>
			<div class="sd-font-14 sd-color-text-tertiary sd-line-20 sd-flex sd-gap-6 sd-mt-30">
				<span class="sd-mt-2">
					<?php Helper::get_library_icon( 'list' ); ?>
				</span>
				<span>
					<?php esc_html_e( 'This community\'s portal spaces will be listed here.', 'suredash' ); ?>
				</span>
			</div>
			<?php

		}

		if ( ! $show_only_navigation ) {
			echo do_shortcode( '[portal_user_profile]' );
		}
	}
}
