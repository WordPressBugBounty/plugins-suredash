<?php
/**
 * Markup functions.
 *
 * @package SureDash
 * @since 0.0.1
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SureDashboard\Inc\Utils\Helper;
use SureDashboard\Inc\Utils\Labels;

/**
 * Get image uploader field.
 *
 * @param string $title The field title.
 * @param string $option The option name.
 * @param bool   $only_input_field Whether to show only the input field.
 * @param bool   $hidden_at_first Whether the field should be hidden at first.
 * @return void
 *
 * @since 0.0.1
 */
function suredash_image_uploader_field( $title, $option, $only_input_field = false, $hidden_at_first = false ): void {
	$image_supports = apply_filters( 'suredashboard_topic_image_supports', '.jpg, .jpeg, .gif, .png, .webp' );
	$extra_class    = $hidden_at_first ? 'portal-hidden-field' : '';
	// Use profile_upload_limit for profile picture and banner image, otherwise use user_upload_limit.
	$is_profile_upload = in_array( $option, [ 'user_profile_photo', 'user_banner_image' ], true );
	$max_upload_size   = $is_profile_upload ? Helper::get_option( 'profile_upload_limit' ) : Helper::get_option( 'user_upload_limit' );

	if ( $only_input_field ) {
		?>
		<span class="suredash-upload-block profile-pic-uploader">
			<input class="suredash-upload-size" value="<?php echo esc_attr( $max_upload_size ); ?>" type="hidden" />
			<input class="suredash-input-upload portal_feed_input sd-pointer" name="<?php echo esc_attr( $option ); ?>" type="file" aria-required="false" accept="<?php echo esc_attr( $image_supports ); ?>">
			<div class="suredash-error-wrap sd-font-12">
				<div class="suredash-error-message" data-error-msg="<?php echo esc_attr__( 'This field is required.', 'suredash' ); ?>"></div>
			</div>
		</span>
		<?php
		return;
	}

	?>
	<div class="portal-custom-topic-field portal-extended-linked-field portal-featured-image-field <?php echo esc_attr( $extra_class ); ?>">
		<?php if ( ! empty( $title ) ) { ?>
			<label for="<?php echo esc_attr( $option ); ?>"><?php echo esc_html( $title ); ?></label>
		<?php } ?>

		<div class="suredash-upload-block">
			<div class="suredash-block-wrap sd-bg-custom sd-relative sd-transition sd-text-center sd-flex sd-items-center sd-justify-center sd-flex-col sd-gap-12 sd-border-dashed sd-hover-border-custom sd-focus-border-custom sd-font-16 sd-font-normal sd-px-16 sd-py-24 sd-radius-6 sd-outline-0"
				style="--sd-bg-custom: hsl( from #1e1e1e h s l / 0.02 ); --sd-hover-border-custom: var(--portal-accent-color); --sd-focus-border-custom: var(--portal-accent-color);">
				<span class="suredash-upload-icon">
					<?php Helper::get_library_icon( 'Upload', true, 'lg' ); ?>
				</span>

				<div class="suredash-upload-wrap sd-help-text">
					<input class="suredash-upload-size" value="<?php echo esc_attr( $max_upload_size ); ?>" type="hidden">
					<label class="suredash-classic-upload-label sd-flex sd-flex-col" for="<?php echo esc_attr( $option ); ?>">
						<?php
						esc_html_e( 'Click to upload or drag and drop', 'suredash' );
						echo sprintf(
							'<p class="portal-help-description">%s %s</p>',
							esc_html__( 'Allowed file formats:', 'suredash' ),
							esc_attr( $image_supports )
						);
						?>
						<input class="suredash-input-upload portal_feed_input sd-pointer" name="<?php echo esc_attr( $option ); ?>" type="file" aria-required="false" accept="<?php echo esc_attr( $image_supports ); ?>">
					</label>
				</div>
			</div>

			<div class="suredash-upload-data"></div>

			<div class="suredash-error-wrap sd-font-12">
				<div class="suredash-error-message" data-error-msg="<?php echo esc_attr__( 'This field is required.', 'suredash' ); ?>"></div>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Get post likes list markup.
 *
 * @param int $post_id Post ID.
 * @return void
 * @since 0.0.1
 */
function suredash_likes_list_markup( $post_id ): void {
	$likes_count = sd_get_post_meta( $post_id, 'portal_post_likes', true );
	$likes_count = is_array( $likes_count ) ? $likes_count : [];

	ob_start();

	if ( count( $likes_count ) ) {
		?>
		<div class="portal-likes-list">
			<?php
			foreach ( $likes_count as $like_user_id ) {
				if ( $like_user_id ) {
					$user_view         = suredash_get_user_view_link( $like_user_id );
					$user_display_name = suredash_get_user_display_name( $like_user_id );
					?>
					<a href="<?php echo esc_url( $user_view ); ?>" target="_blank" class="portal-likes-list-item sd-text-color">
						<?php echo wp_kses_post( (string) suredash_get_user_avatar( $like_user_id ) ); ?>
						<span class="like-user"><?php echo esc_html( $user_display_name ); ?></span>
					</a>
					<?php
				}
			}
			?>
		</div>
		<?php
	}

	echo do_shortcode( (string) ob_get_clean() );
}

/**
 * Get visibility scope list markup for modal display.
 *
 * @param int $post_id Post ID.
 * @return void
 * @since 1.6.0
 */
function suredash_visibility_scope_list_markup( $post_id ): void {
	$visibility_scope = get_post_meta( $post_id, 'visibility_scope', true );
	$visibility_scope = is_array( $visibility_scope ) ? $visibility_scope : [];
	$access_groups    = Helper::get_suremembers_access_groups();

	ob_start();

	if ( count( $visibility_scope ) ) {
		?>
		<div class="portal-visibility-scope-list">
			<?php
			foreach ( $visibility_scope as $scope_item ) {
				if ( empty( $scope_item ) ) {
					continue;
				}

				// Check if it's a user (starts with 'user-') or access group (starts with 'ag-').
				if ( strpos( $scope_item, 'user-' ) === 0 ) {
					// Handle user.
					$user_id = (int) str_replace( 'user-', '', $scope_item );
					$user    = get_user_by( 'ID', $user_id );

					if ( $user ) {
						$user_view = home_url( "/portal/user-view/{$user_id}/" );
						?>
						<a href="<?php echo esc_url( $user_view ); ?>" target="_blank" class="portal-visibility-scope-item sd-text-color">
							<?php echo wp_kses_post( (string) suredash_get_user_avatar( $user_id ) ); ?>
							<span class="scope-user-name"><?php echo esc_html( $user->display_name ); ?></span>
							<span class="scope-item-type sd-text-gray-500"><?php echo esc_html__( 'Member', 'suredash' ); ?></span>
						</a>
						<?php
					}
				} elseif ( strpos( $scope_item, 'ag-' ) === 0 && suredash_is_suremembers_active() ) {
					// Handle access group (only if SureMembers is active).
					$group_id = (int) str_replace( 'ag-', '', $scope_item );

					// Get group name using the same method as assets.php.
					$group_name = '';

					if ( ! empty( $access_groups ) && is_array( $access_groups ) ) {
						foreach ( $access_groups as $group ) {
							if ( isset( $group['value'] ) && $group['value'] === $group_id ) {
								$group_name = $group['label'] ?? '';
								break;
							}
						}
					}

					// Final fallback: use generic name.
					if ( empty( $group_name ) ) {
						$group_name = 'Access Group ' . $group_id;
					}

					if ( ! empty( $group_name ) ) {
						?>
						<div class="portal-visibility-scope-item">
							<div class="portal-avatar-initials portal-avatar-40 sd-bg-gray-700 sd-color-light">
								<?php
								// Generate initials from group name.
								$words    = explode( ' ', $group_name );
								$initials = '';
								foreach ( array_slice( $words, 0, 2 ) as $word ) {
									$initials .= strtoupper( substr( $word, 0, 1 ) );
								}
								echo esc_html( $initials );
								?>
							</div>
							<span class="scope-group-name"><?php echo esc_html( $group_name ); ?></span>
							<span class="scope-item-type sd-text-gray-500"><?php echo esc_html__( 'Access Group', 'suredash' ); ?></span>
						</div>
						<?php
					}
				}
			}
			?>
		</div>
		<?php
	} else {
		?>
		<div class="portal-visibility-scope-empty sd-text-center sd-py-20">
			<p class="sd-text-gray-500"><?php esc_html_e( 'This post is visible to all members.', 'suredash' ); ?></p>
		</div>
		<?php
	}

	echo do_shortcode( (string) ob_get_clean() );
}

/**
 * Get Likes Wrapper.
 *
 * @param int $post_id Post ID.
 * @return void
 * @since 0.0.1
 */
function suredash_quick_view_likes_wrapper( $post_id ): void {
	?>
	<div class="portal-post-qv-reaction-wrap">
		<div class="portal-likes-area portal-hidden-field">
			<?php suredash_likes_list_markup( $post_id ); ?>
		</div>
	<?php
}

/**
 * Get Comments List.
 *
 * @param int          $post_id Post ID.
 * @param string       $order Order of comments.
 * @param array<mixed> $params Array of parameters.
 * @return void
 * @since 0.0.1
 */
function suredash_get_comments_list( $post_id, $order = 'ASC', $params = null ): void {
	?>
		<ol class="portal-comment-list sd-no-space">
			<?php
			if ( $params === null || empty( $params ) ) {
				$params = [
					[
						'post_id' => $post_id,
						'order'   => $order,
						'parent'  => 0, // Only get top-level comments.

					],
				];
			}

			$all_comments = []; // Initialize an empty array to store all comments.
			foreach ( $params as $param ) {
				$comments = get_comments( $param );
				if ( is_array( $comments ) ) {
					$existing_comment_ids = array_column( $all_comments, 'comment_ID' );
					foreach ( $comments as $comment ) {
						if ( isset( $comment->comment_ID ) && ! in_array( $comment->comment_ID, $existing_comment_ids ) ) {
							$height_threshold         = apply_filters( 'suredash_image_preview_height_threshold', 200 );
							$comment->comment_content = Helper::process_image_previews( $comment->comment_content, $height_threshold );
							$all_comments[]           = $comment;
						}
					}
				}
			}

			wp_list_comments(
				[
					'callback' => 'suredash_comments_list_callback',
					'style'    => 'ol',
				],
				$all_comments
			);
			?>
		</ol>

	<?php
}

/**
 * Comments Markup.
 *
 * @param int          $post_id Post ID.
 * @param bool         $comment_box Whether to show the comment box.
 * @param array<mixed> $params Array of parameters.
 * @param string       $comment_form_class Comment form class.
 * @param string       $comment_box_id_suffix Comment box ID suffix.
 * @return void
 * @since 0.0.1
 */
function suredash_comments_markup( $post_id, $comment_box = false, $params = null, $comment_form_class = '', $comment_box_id_suffix = '' ): void {
	ob_start();

	?>
		<div class="portal-content">
			<?php
			do_action( 'suredashboard_single_comments_before' );
			suredash_get_comments_list( $post_id, 'ASC', $params );
			?>

			<?php
			if ( is_user_logged_in() ) {
				do_action( 'suredashboard_single_comments_before_form' );

				if ( $comment_box ) {
					suredash_comment_box_markup( $post_id, false, $comment_form_class, $comment_box_id_suffix );
				}
				suredash_comment_box_markup( $post_id, true, $comment_form_class );

				do_action( 'suredashboard_single_comments_after_form' );

				do_action( 'suredashboard_single_comments_after' );
			}
			?>
		</div>
	<?php

	echo do_shortcode( (string) ob_get_clean() );
}

/**
 * Get user avatar.
 *
 * @param int  $user_id User ID.
 * @param bool $echo Whether to echo or return the avatar.
 * @param int  $size Avatar size.
 * @param bool $add_data_wrapper Whether to add data wrapper for JS fallback.
 * @param int  $space_id Space ID (optional, for generating initials if no avatar).
 * @return string Avatar markup.
 */
function suredash_get_user_avatar( $user_id, $echo = true, $size = 40, $add_data_wrapper = false, $space_id = 0 ): string {
	$profile_photo = sd_get_user_meta( $user_id, 'user_profile_photo', true );
	$size_class    = 'portal-avatar-' . $size;
	$user          = get_user_by( 'ID', $user_id );

	// Generate initials data for data attributes (always needed for JS fallback).
	$initials_arr  = suredash_get_user_initials( $user_id, $space_id );
	$initial_count = strlen( $initials_arr['initials'] );
	$user_name     = suredash_get_user_display_name( $user_id );

	if ( ! empty( $profile_photo ) ) {
		$user_display_name = sd_get_user_meta( absint( $user_id ), 'display_name', true );
		$alt_text          = ! empty( $user_display_name ) ? $user_display_name . ' profile photo' : 'User profile photo';

		$markup = wp_kses_post( apply_filters( 'suredash_user_avatar_markup', '<img class="portal-user-avatar ' . esc_attr( $size_class ) . '" src="' . esc_url( $profile_photo ) . '" alt="' . esc_attr( $alt_text ) . '" />' ) );
	} else {
		// No profile photo - show initials avatar.
		$alt_text = $user ? suredash_get_user_display_name( $user_id ) . ' avatar' : 'User avatar';

		// Determine font size class based on avatar size.
		$font_class = $initial_count === 3 ? 'sd-font-14' : 'sd-font-16'; // Default for medium sizes.
		if ( $size <= 24 ) {
			$font_class = 'sd-font-12';
		} elseif ( $size <= 32 ) {
			$font_class = 'sd-font-14';
		}

		$markup = sprintf(
			'<div class="portal-user-avatar portal-avatar-initials %s %s %s" style="line-height: 1;" title="%s">%s</div>',
			esc_attr( $size_class ),
			esc_attr( $initials_arr['color'] ),
			esc_attr( $font_class ),
			esc_attr( $alt_text ),
			esc_html( $initials_arr['initials'] )
		);
		$markup = apply_filters( 'suredash_user_avatar_markup', $markup );
	}

	// Add data wrapper for profile pages that need JS fallback.
	if ( $add_data_wrapper ) {
		$markup = sprintf(
			'<div class="portal-avatar-data-wrapper" data-initials="%s" data-color="%s" data-user-name="%s">%s</div>',
			esc_attr( $initials_arr['initials'] ),
			esc_attr( $initials_arr['color'] ),
			esc_attr( $user_name ),
			$markup
		);
	}

	if ( $echo ) {
		echo do_shortcode( $markup );
		return '';
	}

	return $markup;
}

/**
 * Comment Box Markup.
 *
 * @param int    $post_id Post ID.
 * @param bool   $hidden Whether the comment box should be hidden.
 * @param string $comment_form_class Comment form class.
 * @param string $comment_box_id_suffix Comment box ID suffix.
 * @return void
 * @since 0.0.6
 */
function suredash_comment_box_markup( $post_id, $hidden = false, $comment_form_class = '', $comment_box_id_suffix = '' ): void {
	$comment_box_final_id = $comment_box_id_suffix ? 'jodit-comment-' . $comment_box_id_suffix . '-' . $post_id : 'jodit-comment-' . $post_id;
	ob_start();
	?>
		<div class="sd-flex sd-justify-between sd-items-start sd-gap-8 comment-markup sd-display-none <?php echo esc_attr( $hidden ? ' hidden-comment-markup ' : ' ' ); ?> <?php echo esc_attr( $comment_form_class ); ?>
	" id="inline-comment-box">
			<?php suredash_get_user_avatar( get_current_user_id(), true, 32 ); ?>

			<form action="" method="post" class="jodit-comment-box-wrapper sd-flex sd-flex-col sd-flex-1 sd-justify-center sd-w-full" id="postcommentform">
				<!-- Required fields -->
				<input type="hidden" name="comment_post_ID" value="<?php echo esc_attr( strval( $post_id ) ); ?>" />
				<input type="hidden" name="comment_parent" value="0" />

				<!-- The actual comment input box -->
				<textarea
					class="<?php echo esc_attr( $hidden ? 'hidden-jodit-comment' : 'jodit-comment' ); ?>"
					name="comment"
					id="<?php echo esc_attr( $comment_box_final_id ); ?>"
					autocomplete="off"></textarea>

				<button type="submit" class="post-comment-box-submit" aria-label="<?php esc_attr_e( 'Submit Comment', 'suredash' ); ?>">
					<?php
						Helper::get_library_icon( 'SendHorizontal', true, 'sm' );
						Helper::get_library_icon( 'LoaderCircle', true, 'sm', 'sd-display-none' );
					?>
				</button>
			</form>
			</form>
		</div>

	<?php
	echo do_shortcode( (string) ob_get_clean() );
}

/**
 * Render item title and description block (shared by list, card, and stacked items).
 *
 * @param array<string,mixed> $item_data Array of item data for rendering.
 * @param array<string,mixed> $args {
 *     Array of arguments for rendering title and description.
 *
 *     @type string $title       Required. Item title.
 *     @type string $description Optional. Item description.
 *     @type string $link        Optional. Link URL.
 *     @type string $title_class Optional. CSS classes for title.
 *     @type string $desc_class  Optional. CSS classes for description.
 *     @type string $layout_type Optional. 'list', 'card', or 'stacked'. Default 'list'.
 * }
 * @return void
 * @since 1.2.0
 */
function suredash_render_item_title_description( $item_data = [], $args = [] ): void {
	$defaults = [
		'title'            => '',
		'description'      => '',
		'link'             => '',
		'title_class'      => 'sd-force-font-16 sd-force-line-20 sd-force-font-semibold',
		'desc_class'       => 'sd-mt-4 sd-m-0 sd-line-clamp-1 sd-font-14 sd-force-line-20 sd-font-normal sd-text-secondary',
		'event_desc_class' => 'sd-mt-4 sd-m-0 sd-font-14 sd-force-line-20 sd-font-normal sd-text-secondary sd-flex sd-flex-wrap',
		'layout_type'      => 'list',
	];

	$args = wp_parse_args( $args, $defaults );

	// Bail if no title provided.
	if ( empty( $args['title'] ) ) {
		return;
	}

	// For card/stacked layouts with arrow & Regular title for list layout in else part.
	if ( ! empty( $args['link'] ) && $args['link'] !== '#' ) {
		?>
		<a href="<?php echo esc_url( $args['link'] ); ?>" data-js-hook="<?php echo esc_attr( $item_data['link_js_hook'] ?? '' ); ?>" data-post_id="<?php echo esc_attr( $item_data['id'] ); ?>" data-integration="<?php echo esc_attr( $item_data['integration'] ?? '' ); ?>">
	<?php } ?>
		<h2 class="sd-m-0 sd-heading-title <?php echo esc_attr( $args['title_class'] ); ?> sd-truncate">
			<?php echo esc_html( $args['title'] ); ?>
		</h2>
	<?php if ( ! empty( $args['link'] ) && $args['link'] !== '#' ) { ?>
		</a>
		<?php
	}

	// Show integration and one of: progress, lesson, or post_count, with priority: private > lesson > post_count.
	// Do not show as badge, just plain text values.
	if (
		! empty( $item_data['integration'] ) ||
		isset( $item_data['lesson'] ) ||
		isset( $item_data['post_count'] ) ||
		isset( $item_data['private'] )
	) {
		?>
		<div class="sd-flex sd-items-center sd-gap-4">
			<?php
			// Priority 1: Private (show only "Private" text if set).
			$labels = [];

			// Show integration.
			if ( ! empty( $item_data['integration'] ) && ! ( isset( $item_data['hide_integration_label'] ) && $item_data['hide_integration_label'] === true ) ) {
				// Map internal integration values to user-friendly labels.
				$integration_label = isset( $item_data['integration'] ) && is_array( $item_data['integration'] ) ? $item_data['integration'][0] : strval( $item_data['integration'] );
				switch ( $integration_label ) {
					case 'posts_discussion':
						$integration_label = __( 'Discussion', 'suredash' );
						break;
					case 'single_post':
						$integration_label = __( 'Post', 'suredash' );
						break;
					case 'course':
						$integration_label = suredash_is_pro_active() && class_exists( '\SureDashboardPro\Inc\Utils\Labels' ) ? \SureDashboardPro\Inc\Utils\Labels::get_label( 'course_singular_text' ) : __( 'Course', 'suredash' );
						break;
					case 'resource_library':
						$integration_label = __( 'Resource Library', 'suredash' );
						break;
					default:
						$integration_label = ucwords( str_replace( '_', ' ', $integration_label ) );
						break;
				}

				$labels[] = '<span class="sd-font-14 sd-line-20 sd-font-normal sd-color-text-tertiary sd-capitalize">' . esc_html( $integration_label ) . '</span>';
			}

			if ( ! empty( $item_data['private'] ) && $item_data['private'] === true ) {
				$labels[] = '<span class="sd-font-14 sd-line-20 sd-font-normal sd-color-text-tertiary sd-capitalize">' . esc_html__( 'Private', 'suredash' ) . '</span>';
			} elseif ( isset( $item_data['lesson'] ) && $item_data['lesson'] !== 0 ) {
				$lesson_count = intval( $item_data['lesson'] );
				$lesson_label = sprintf( _n( ' lesson', ' lessons', $lesson_count, 'suredash' ) );
				$labels[]     = '<span class="sd-font-14 sd-line-20 sd-font-normal sd-color-text-tertiary">' . esc_html( $lesson_count . $lesson_label ) . '</span>';
			} elseif ( isset( $item_data['post_count'] ) && $item_data['post_count'] !== 0 ) {
				$post_count = intval( $item_data['post_count'] );
				$post_label = sprintf( _n( ' thread', ' threads', $post_count, 'suredash' ) );
				$labels[]   = '<span class="sd-font-14 sd-line-20 sd-font-normal sd-color-text-tertiary">' . esc_html( $post_count . $post_label ) . '</span>';
			}

			// Output labels with centered dot separator if more than one.
			if ( ! empty( $labels ) && $args['layout_type'] === 'list' && $item_data['integration'] !== 'events' ) {
				echo wp_kses_post( implode( '<span class="sd-color-text-tertiary" aria-hidden="true">&middot;</span>', $labels ) );
			}
			?>
		</div>
		<?php
	}

	if ( $args['layout_type'] === 'list' && empty( $args['description'] ) ) {
		return;
	}

	$is_event_description = isset( $item_data['integration'] ) && $item_data['integration'] === 'events';
	?>
		<p class="<?php echo esc_attr( $is_event_description ? $args['event_desc_class'] : $args['desc_class'] ); ?>">
			<?php
			if ( ! empty( $args['description'] ) ) {
				echo esc_html( $args['description'] );
			} else {
				echo '<span class="sd-flex sd-items-center sd-gap-4">';
				if ( ! empty( $labels ) ) {
					echo wp_kses_post( $labels[0] );
				}
				echo '</span>';
			}
			?>
		</p>
	<?php
}

/**
 * Display the appropriate badge based on item type and state.
 *
 * @param array<string,mixed> $args Item arguments containing badge data.
 * @return void
 * @since 1.2.0
 */
function suredash_display_item_badge( $args ): void {
	// Priority 1: Private badge.
	if ( ! empty( $args['private'] ) && $args['private'] === true ) {
		Helper::show_badge( 'neutral', 'lock', __( 'Private', 'suredash' ), 'sm' );
		return;
	}

	// Priority 2: Space-type specific badges.
	if ( isset( $args['integration'] ) ) {
		switch ( $args['integration'] ) {
			case 'posts_discussion':
				if ( isset( $args['post_count'] ) && $args['post_count'] !== 0 ) {
					$post_count = intval( $args['post_count'] );
					$post_label = sprintf( _n( ' thread', ' threads', $post_count, 'suredash' ) );
					Helper::show_badge( 'neutral', '', $post_count . $post_label, 'sm', '', [], true );
				}
				break;

			case 'course':
				if ( isset( $args['progress'] ) && (int) $args['progress'] !== 0 ) {
					if ( $args['progress'] < 100 ) {
						Helper::show_badge( 'primary', '', $args['progress'] . '%', 'sm', 'collection-course-progress', [ 'progress' => $args['progress'] . '%' ], true );
					} else {
						Helper::show_badge( 'success', '', '100%', 'sm', '', [], true );
					}
				} elseif ( isset( $args['lesson'] ) && $args['lesson'] !== 0 ) {
					// Show lesson count for courses without progress.
					$lesson_count = intval( $args['lesson'] );
					$lesson_label = sprintf( _n( ' lesson', ' lessons', $lesson_count, 'suredash' ) );
					Helper::show_badge( 'neutral', '', $lesson_count . $lesson_label, 'sm', '', [], true );
				}
				break;

			default:
				break;
		}
	}
}

/**
 * Render item badges and options (shared by list, card, and stacked items).
 *
 * @param array<string,mixed> $args Item arguments containing badges and options data.
 * @param array<string,mixed> $config Configuration for styling.
 * @return void
 * @since 1.2.0
 */
function suredash_render_item_badges_and_options( $args, $config = [] ): void {
	$defaults = [
		'container_class'       => 'sd-w-full sd-flex sd-justify-between sd-items-center sd-gap-4',
		'options_wrapper_class' => 'portal-list-item-options sd-flex sd-items-center sd-ml-auto sd-shrink-0 sd-gap-4',
		// if private is true, add class sd-text-color-disabled to a and icon.
		'button_class'          => 'portal-button button-ghost ',
		'icon_size'             => 'sm',
		'icon_color'            => '',
		'layout_type'           => 'list',
		'visit_link_url'        => '',
		'visit_link_icon'       => 'ArrowRight',
		'preview'               => false,
	];

	$config  = array_merge( $defaults, $config );
	$post_id = absint( $args['id'] ?? $config['post_id'] ?? 0 );

	$is_list_layout = ( $config['layout_type'] ?? '' ) === 'list';

	// Show pinned badge for non-list layouts (grid view etc).
	if ( ! empty( $args['is_pinned'] ) && ! $is_list_layout ) {
		Helper::show_badge( 'neutral', 'Pin', Labels::get_label( 'pinned_post' ), 'sm', 'sd-color-text-tertiary' );
	}

	suredash_display_item_badge( $args );

	// Likes enabled check.
	$space_id = Helper::get_space_id_for_post( $post_id ) ? Helper::get_space_id_for_post( $post_id ) : 0;

	// For resources, use direct space_id meta to avoid global $post context issues.
	if ( isset( $args['integration'] ) && $args['integration'] === 'resource_library' ) {
		$direct_space_id = sd_get_post_meta( $post_id, 'space_id', true );
		if ( $direct_space_id ) {
			$space_id = absint( $direct_space_id );
		}
	}

	// Check like button setting with backward compatibility for show_like_share.
	$likes_enabled = true;
	if ( $space_id ) {
		if ( metadata_exists( 'post', $space_id, 'show_like_button' ) ) {
			$likes_enabled = (bool) sd_get_post_meta( $space_id, 'show_like_button', true );
		} elseif ( metadata_exists( 'post', $space_id, 'show_like_share' ) ) {
			$likes_enabled = (bool) sd_get_post_meta( $space_id, 'show_like_share', true );
		}
	}

	if ( $is_list_layout ) {
		echo '<div class="portal-list-reactions sd-flex sd-items-center sd-gap-4 sd-shrink-0">';
		// Show pinned badge inside reactions wrapper for list view.
		if ( ! empty( $args['is_pinned'] ) ) {
			Helper::show_badge( 'neutral', 'Pin', Labels::get_label( 'pinned_post' ), 'sm', 'sd-color-text-tertiary' );
		}
	}

	// Display like button if enabled.
	if ( ! empty( $args['force_enable_like'] ) || ( ! empty( $args['enable_likes'] ) && $likes_enabled && is_user_logged_in() ) ) {
		$current_user_id  = get_current_user_id();
		$user_liked_posts = sd_get_user_meta( $current_user_id, 'portal_user_liked_posts', true );
		$user_liked_posts = is_array( $user_liked_posts ) ? $user_liked_posts : [];
		$is_user_liked    = in_array( $post_id, $user_liked_posts, true );

		$likes_count = sd_get_post_meta( $post_id, 'portal_post_likes', true );
		$likes_count = is_array( $likes_count ) ? count( $likes_count ) : 0;

		?>
		<div class="sd-flex sd-gap-4">
			<button class="sd-post-reaction portal-button button-ghost sd-active-shadow-none sd-p-8 sd-radius-9999 " data-state="<?php echo esc_attr( $is_user_liked ? 'liked' : 'unliked' ); ?>" data-post_id="<?php echo esc_attr( (string) $post_id ); ?>" title="<?php echo esc_attr__( 'Like', 'suredash' ); ?>" role="button" aria-label="<?php echo esc_attr__( 'Like', 'suredash' ); ?>" data-reaction_type="like">
				<?php Helper::get_library_icon( 'Heart', true ); ?>
			</button>
			<span class="portal-button-likes-count sd-pr-2 sd-font-14 sd-line-16 sd-font-semibold" data-count="<?php echo esc_attr( (string) $likes_count ); ?>" data-type="like" data-post_id="<?php echo esc_attr( (string) $post_id ); ?>" style="pointer-events: none; cursor: default;">
				<span class="counter"><?php echo esc_html( (string) $likes_count ); ?></span>
			</span>
		</div>
	<?php } ?>

	<?php
	// Display comment button if comments are open.
	$comments_open = sd_get_post_field( $post_id, 'comment_status' ) === 'open';

	if ( ! empty( $args['enable_comments'] ) && $comments_open ) {
		$permalink      = (string) get_permalink( $post_id );
		$comments_count = get_comments_number( $post_id );
		?>
		<div class="sd-flex sd-gap-4 portal-comment-qv-trigger" data-post_id="<?php echo esc_attr( (string) $post_id ); ?>" data-href="<?php echo esc_url( $permalink ); ?>" data-focus_comment="1" data-comments="1">
			<button class="sd-post-reaction portal-button button-ghost sd-active-shadow-none sd-p-8 sd-radius-9999" data-post_id="<?php echo esc_attr( (string) $post_id ); ?>" title="<?php echo esc_attr__( 'Comment', 'suredash' ); ?>" role="button" aria-label="<?php echo esc_attr__( 'Comment', 'suredash' ); ?>" data-reaction_type="comment" data-type="comment">
				<?php Helper::get_library_icon( 'MessageCircle', true ); ?>
			</button>
			<span class="portal-comments-count sd-pr-2 sd-font-14 sd-line-16 sd-font-semibold" data-count="<?php echo esc_attr( (string) $comments_count ); ?>" data-type="comment" data-post_id="<?php echo esc_attr( (string) $post_id ); ?>">
				<span class="counter"><?php echo esc_html( (string) $comments_count ); ?></span>
			</span>
		</div>
	<?php } ?>

	<?php if ( $is_list_layout ) { ?>
		</div>
	<?php } ?>

	<?php
		$some_content_is_there = ( ! empty( $args['show_visit_link'] ) && ! empty( $args['visit_link_url'] ) ) || ( ! empty( $args['bookmark'] ) && is_user_logged_in() ) ? true : false;
	if ( ! $some_content_is_there ) {
		return;
	}
	?>

	<div class="<?php echo esc_attr( $config['options_wrapper_class'] ); ?>">
		<?php
		if ( ! empty( $args['show_visit_link'] ) && ! empty( $args['visit_link_url'] ) ) {
			$label = $args['visit_link_text'] ?? '';
			$label = mb_strlen( $label ) > 20 ? mb_substr( $label, 0, 20 ) . '…' : $label;

				// Display as clickable button.
			?>
				<button class="portal-button link-button <?php echo ( $args['space_type'] ?? '' ) === 'events' ? 'button-secondary' : 'button-ghost'; ?> sd-force-px-12" data-href="<?php echo esc_url( $args['visit_link_url'] ); ?>" data-target="<?php echo esc_attr( $args['visit_link_target'] ?? '_blank' ); ?>" title="<?php echo esc_attr( $label ); ?>" data-post_id="<?php echo esc_attr( (string) $post_id ); ?>" aria-label="<?php echo esc_attr__( 'Visit', 'suredash' ); ?>" data-integration="<?php echo esc_attr( $args['integration'] ); ?>"
				<?php
				// Add all dataset attributes.
				if ( ! empty( $args['visit_link_dataset'] ) && is_array( $args['visit_link_dataset'] ) ) {
					foreach ( $args['visit_link_dataset'] as $key => $value ) {
						echo ' data-' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
					}
				}
				?>
				>
					<?php
						echo esc_html( $label );
					if ( ( $args['space_type'] ?? '' ) !== 'events' ) {
						$icon_to_use = ! empty( $args['visit_link_icon'] ) ? $args['visit_link_icon'] : $config['visit_link_icon'];
						Helper::get_library_icon( $icon_to_use, true, 'sm', '', [], true );
					}
					?>
				</button>
				<?php
		}

		// Display bookmark button if enabled.
		if ( ! empty( $args['bookmark'] ) && is_user_logged_in() ) {
			$bookmarked    = suredash_is_item_bookmarked( $post_id );
			$bookmarked    = $bookmarked ? 'bookmarked' : '';
			$bookmark_type = '';
			if ( $args['integration'] === 'resource_library' ) {
				$bookmark_type = 'resource';
			}
			?>
				<button class="portal-post-bookmark-trigger portal-button button-ghost sd-flex sd-items-center <?php echo esc_attr( $bookmarked ); ?>" data-item_id="<?php echo esc_attr( (string) $post_id ); ?>" data-item_type="<?php echo esc_attr( $bookmark_type ); ?>" aria-label="<?php echo $bookmarked ? esc_attr__( 'Remove bookmark', 'suredash' ) : esc_attr__( 'Add bookmark', 'suredash' ); ?>" aria-pressed="<?php echo $bookmarked ? 'true' : 'false'; ?>" title="<?php esc_attr_e( 'Bookmark', 'suredash' ); ?>">
					<?php Helper::get_library_icon( 'Bookmark', true ); ?>
				</button>
			<?php
		}
		?>
	</div>
	<?php
}

/**
 * Render list item markup.
 *
 * @param array<string,mixed> $args {
 *     Array of arguments for rendering the list item.
 *
 *     @type string $title       Required. Item title.
 *     @type string $description Optional. Item description/subtitle.
 *     @type string $avatar      Optional. Avatar image URL or icon name.
 *     @type string $avatar_type Optional. 'image' or 'icon'. Default 'icon'.
 *     @type string $link        Optional. Item link URL.
 *     @type string $meta_text   Optional. Meta information (e.g., view count).
 *     @type array  $options     Optional. Array of option items for right side.
 * }
 * @since 1.2.0
 * @return void
 */
function suredash_render_list_item( $args = [] ): void {
	$defaults = [
		'id'          => 0,
		'title'       => '',
		'description' => '',
		'avatar'      => '',
		'avatar_type' => 'icon',
		'link'        => '',
		'private'     => null,
		'lesson'      => null,
		'progress'    => null,
		'options'     => [
			[
				'icon'    => 'chevronRight',
				'link'    => '#',
				'js-hook' => '',
				'title'   => __( 'Options', 'suredash' ),
			],
		],
	];

	$args = wp_parse_args( $args, $defaults );

	// Bail if no title provided.
	if ( empty( $args['title'] ) ) {
		return;
	}

	$wrapper_link = ! empty( $args['link'] ) ? $args['link'] : ( ! empty( $args['visit_link_url'] ) ? $args['visit_link_url'] : '#' );

	ob_start();
	?>
		<!-- Single wrapper link for entire list item -->
		<a href="<?php echo esc_url( $wrapper_link ); ?>" class="portal-list-wrapper" data-js-hook="<?php echo esc_attr( $args['link_js_hook'] ?? '' ); ?>" data-post_id="<?php echo esc_attr( $args['id'] ); ?>" data-integration="<?php echo esc_attr( $args['integration'] ?? '' ); ?>">
			<div class="portal-list-item portal-store-list-post portal-content sd-flex sd-items-center sd-gap-16 sd-m-0 sd-p-16 sd-radius-8 sd-hover-shadow-md"
			id="portal-post-<?php echo esc_attr( $args['id'] ); ?>"
			>
				<?php if ( ! empty( $args['avatar'] ) ) { ?>
					<div class="portal-list-item-avatar sd-shrink-0">
						<?php if ( $args['avatar_type'] === 'image' ) { ?>
							<img src="<?php echo esc_url( $args['avatar'] ); ?>" alt="<?php echo esc_attr( $args['title'] ); ?>" class="portal-list-item-avatar-img sd-radius-8"/>
						<?php } else { ?>
							<div class="portal-list-item-avatar-icon sd-radius-9999 sd-flex sd-items-center sd-justify-center sd-stroke-dark">
								<?php if ( ! empty( $args['private'] ) && $args['private'] === true ) { ?>
									<?php Helper::get_library_icon( 'Lock', true, 'md' ); ?>
									<?php
								} elseif ( ! empty( $args['avatar'] ) ) {
									Helper::get_library_icon( $args['avatar'], true, 'md' );
								}
								?>
							</div>
						<?php } ?>
					</div>
					<?php
				} elseif ( ! empty( $args['user_initials_icon'] ) ) {
					suredash_get_user_avatar( $args['user_id'] );
				}
				?>

				<div class="portal-list-item-content sd-flex-1 sd-min-w-0">
					<?php
						suredash_render_item_title_description(
							$args,
							[
								'title'       => $args['title'],
								'description' => $args['description'],
								'link'        => '', // Remove link from title as wrapper handles it.
								'layout_type' => 'list',
								'link_target' => ! empty( $args['link_target'] ) ? $args['link_target'] : '_self',
							]
						);
					?>
				</div>
				<?php
				suredash_render_item_badges_and_options(
					$args,
					[
						'layout_type' => 'list',
					]
				);
				?>
			</div>
		</a>
	<?php
	$output = ob_get_clean();
	echo $output !== false ? do_shortcode( $output ) : '';
}

/**
 * Render complete list with multiple items.
 *
 * @param array<int,array<string,mixed>> $items Array of item data for the list.
 * @param array<string,mixed>            $list_args {
 *     Optional. Array of arguments for the list container.
 *
 *     @type string $title       Optional. List title/heading.
 *     @type string $class       Optional. Additional CSS classes for the list container.
 *     @type bool   $show_title  Optional. Whether to show the title. Default true.
 * }
 * @since 1.2.0
 * @return void
 */
function suredash_render_list( $items = [], $list_args = [] ): void {
	if ( empty( $items ) || ! is_array( $items ) ) {
		return;
	}

	$list_defaults = [
		'title'           => '',
		'container_class' => '',
		'show_title'      => true,
	];

	$list_args = wp_parse_args( $list_args, $list_defaults );

	ob_start();
	?>
		<div class="portal-list-container sd-flex-col sd-gap-24 <?php echo esc_attr( $list_args['container_class'] ); ?>">
			<?php if ( $list_args['show_title'] && ! empty( $list_args['title'] ) ) { ?>
				<h3 class="portal-list-title sd-m-0 sd-mb-16 sd-font-18 sd-font-semibold">
					<?php echo esc_html( $list_args['title'] ); ?>
				</h3>
			<?php } ?>

			<div class="portal-list-items sd-my-32 sd-radius-12 sd-overflow-hidden">
				<?php
				foreach ( $items as $index => $item ) {
					$item_classes = 'portal-list-item portal-content sd-flex sd-items-center sd-gap-16 sd-m-0 sd-p-16 sd-radius-0 sd-border-none sd-border-b sd-hover-shadow-md';

					ob_start();
					suredash_render_list_item( $item );
					$item_output = ob_get_clean();
					if ( $item_output === false ) {
						$item_output = '';
					}
					// Assign background color classes to avatar icon backgrounds in a loop.
					if ( ! empty( $item['avatar'] ) && ( empty( $item['avatar_type'] ) || $item['avatar_type'] !== 'image' ) && ! empty( $item_output ) ) {
						$avatar_bg_classes       = [
							'sd-bg-red-100',
							'sd-bg-orange-100',
							'sd-bg-lime-100',
							'sd-bg-green-100',
							'sd-bg-teal-100',
							'sd-bg-indigo-100',
						];
						$avatar_bg_private_class = 'sd-bg-gray-100';
						// Find the avatar icon bg div and inject a color class based on index.
						$color_class = '';
						if ( ! empty( $item['private'] ) && $item['private'] === true ) {
							$color_class = $avatar_bg_private_class;
						} else {
							$color_class = $avatar_bg_classes[ $index % count( $avatar_bg_classes ) ];
						}
						$item_output = preg_replace_callback(
							'/<div class="portal-list-item-avatar-icon([^"]*)"/',
							static function( $matches ) use ( $color_class ) {
								return '<div class="portal-list-item-avatar-icon' . $matches[1] . ' ' . esc_attr( $color_class ) . '"';
							},
							$item_output,
							1
						);
					}
					// Modify the item output to remove individual styling and add group styling.
					if ( ! empty( $item_output ) ) {
						$item_output = str_replace(
							'class="portal-list-item portal-store-list-post portal-content sd-flex sd-items-center sd-gap-16 sd-m-0 sd-p-16 sd-radius-8 sd-hover-shadow-md"',
							'class="' . esc_attr( $item_classes ) . '"',
							$item_output
						);
					}

					echo do_shortcode( strval( $item_output ) );
				}
				?>
			</div>
		</div>
	<?php
	echo do_shortcode( (string) ob_get_clean() );
}

/**
 * Render card grid item.
 *
 * @param array<string,mixed> $args {
 *     Array of arguments for rendering the card item.
 *
 *     @type string $title       Required. Card title.
 *     @type string $description Optional. Card description/subtitle.
 *     @type string $avatar      Optional. Avatar icon name.
 *     @type string $link        Optional. Card link URL.
 *     @type string $meta_text   Optional. Meta information (e.g., view count).
 *     @type string $color       Optional. Background color (red, orange, green, teal, purple, blue).
 *     @type array  $options     Optional. Array of option items for card actions.
 *     @type string $button_text Optional. Text for horizontal button. If provided, replaces badges/options.
 *     @type string $button_icon Optional. Icon for horizontal button. Default 'ArrowRight'.
 * }
 * @since 1.2.0
 * @return void
 */
function suredash_render_card_grid_item( $args = [] ): void {
	$defaults = [
		'id'          => 0,
		'title'       => '',
		'description' => '',
		'avatar'      => '',
		'link'        => '',
		'meta_text'   => '',
		'color'       => 'blue',
		'options'     => [],
		'button_text' => '',
		'button_icon' => 'ArrowRight',
	];

	$args = wp_parse_args( $args, $defaults );

	// Bail if no title provided.
	if ( empty( $args['title'] ) ) {
		return;
	}

	if ( isset( $args['thumbnail_label'] ) ) {
		$thumbnail_html = Helper::get_space_featured_image( $args['id'], true, $args['color'], $args['avatar'], $args['thumbnail_label'] ?? '' );
	} else {
		$thumbnail_html = Helper::get_space_featured_image( $args['id'], true, $args['color'], $args['avatar'] );
	}

	$wrapper_link = ! empty( $args['link'] ) ? $args['link'] : ( ! empty( $args['visit_link_url'] ) ? $args['visit_link_url'] : '#' );

	ob_start();
	?>
		<!-- Single wrapper link for entire card -->
		<a href="<?php echo esc_url( $wrapper_link ); ?>" class="portal-card-wrapper sd-color-inherit" data-js-hook="<?php echo esc_attr( $args['link_js_hook'] ?? '' ); ?>" data-post_id="<?php echo esc_attr( $args['id'] ); ?>" data-integration="<?php echo esc_attr( $args['integration'] ?? '' ); ?>">
			<div class="portal-grid-item-content portal-home-grid-item-content-minimal sd-border sd-hover-shadow-2xl" id="portal-post-<?php echo esc_attr( $args['id'] ); ?>">
				<!-- Thumbnail (no longer wrapped in separate link) -->
				<div class="portal-card-thumbnail">
					<?php echo do_shortcode( $thumbnail_html ); ?>
				</div>

				<div class="sd-flex-col sd-card-main-container">
					<div class="sd-flex-col sd-gap-4 sd-p-16 sd-card-content-container sd-relative">
						<?php
						suredash_render_item_title_description(
							$args,
							[
								'title'       => $args['title'],
								'description' => $args['description'],
								'link'        => '', // Remove link from title as wrapper handles it.
								'layout_type' => 'card',
							]
						);
						?>
					</div>
				</div>

				<?php if ( is_user_logged_in() || $args['space_type'] !== 'resource_library' ) { ?>
				<div class="sd-px-16 sd-py-12 sd-flex sd-justify-between sd-items-center sd-border-t sd-mt-auto">
					<?php
						suredash_render_item_badges_and_options(
							$args,
							[
								'layout_type' => 'card',
							]
						);
					?>
				</div>
				<?php } ?>
			</div>
		</a>
	<?php
	echo do_shortcode( (string) ob_get_clean() );
}

/**
 * Render complete card grid.
 *
 * @param array<int,array<string,mixed>> $items Array of card data for the grid.
 * @param array<string,mixed>            $grid_args {
 *     Optional. Array of arguments for the grid container.
 *
 *     @type string $title       Optional. Grid title/heading.
 *     @type string $class       Optional. Additional CSS classes for the grid container.
 *     @type string $style       Optional. Inline styles for the grid container.
 *     @type bool   $show_title  Optional. Whether to show the title. Default true.
 *     @type int    $columns     Optional. Number of columns (2, 3, 4). Default 3.
 * }
 * @since 1.2.0
 * @return void
 */
function suredash_render_card_grid( $items = [], $grid_args = [] ): void {
	if ( empty( $items ) || ! is_array( $items ) ) {
		return;
	}

	$grid_defaults = [
		'title'           => '',
		'container_class' => '',
		'grid_class'      => '',
		'style'           => '',
		'show_title'      => true,
		'columns'         => 3,
	];

	$grid_args = wp_parse_args( $grid_args, $grid_defaults );

	// Define color cycle.
	$colors = [ 'red', 'orange', 'lime', 'teal', 'green', 'indigo' ];

	ob_start();
	?>
		<div class="portal-card-grid-container sd-w-full sd-flex-col sd-gap-24 sd-mx-auto sd-items-start <?php echo esc_attr( $grid_args['container_class'] ); ?>" <?php echo ! empty( $grid_args['style'] ) ? 'style="' . esc_attr( $grid_args['style'] ) . '"' : ''; ?>>
			<?php if ( $grid_args['show_title'] && ! empty( $grid_args['title'] ) ) { ?>
				<h3 class="portal-card-grid-title sd-m-0 sd-mb-20 sd-font-18 sd-font-semibold sd-text-center">
					<?php echo esc_html( $grid_args['title'] ); ?>
				</h3>
			<?php } ?>
			<div class="portal-cards-grid sd-w-full <?php echo esc_attr( $grid_args['grid_class'] ); ?>">
				<?php
				foreach ( $items as $index => $item ) {
					// Assign color from cycle.
					$color = $colors[ $index % count( $colors ) ];

					// Merge color into item args.
					$item['color'] = $item['color'] ?? $color;

					suredash_render_card_grid_item( $item );
				}
				?>
			</div>
		</div>
	<?php
	echo do_shortcode( (string) ob_get_clean() );
}

/**
 * Render stacked list item (hybrid of card and list layout).
 *
 * @param array<string,mixed> $args {
 *     Array of arguments for rendering the stacked list item.
 *
 *     @type string $title           Required. Item title.
 *     @type string $subtitle        Optional. Item subtitle (e.g., 'Course').
 *     @type string $description     Optional. Item description.
 *     @type string $avatar          Optional. Avatar icon name.
 *     @type string $avatar_type     Optional. 'image' or 'icon'. Default 'icon'.
 *     @type string $link            Optional. Item link URL.
 *     @type string $link_text       Optional. Link button text. Default 'Continue'.
 *     @type string $meta_text       Optional. Meta information (e.g., '50% complete').
 *     @type string $meta_text2      Optional. Secondary meta (e.g., '8 lessons').
 *     @type string $color           Optional. Background color for avatar.
 *     @type bool   $private         Optional. Whether item is private.
 *     @type array  $options         Optional. Array of option items.
 * }
 * @since 1.2.0
 * @return void
 */
function suredash_render_stacked_list_item( $args = [] ): void {
	$defaults = [
		'title'       => '',
		'subtitle'    => '',
		'description' => '',
		'avatar'      => '',
		'avatar_type' => 'icon',
		'link'        => '',
		'meta_text'   => '',
		'meta_text2'  => '',
		'color'       => 'blue',
		'private'     => false,
		'options'     => [],
	];

	$args = wp_parse_args( $args, $defaults );

	// Bail if no title provided.
	if ( empty( $args['title'] ) ) {
		return;
	}

	if ( isset( $args['thumbnail_label'] ) ) {
		$thumbnail_html = Helper::get_space_featured_image( $args['id'], true, $args['color'], $args['avatar'], $args['thumbnail_label'] ?? '' );
	} else {
		$thumbnail_html = Helper::get_space_featured_image( $args['id'], true, $args['color'], $args['avatar'] );
	}

	$wrapper_link = ! empty( $args['link'] ) ? $args['link'] : ( ! empty( $args['visit_link_url'] ) ? $args['visit_link_url'] : '#' );

	ob_start();
	?>
		<!-- Single wrapper link for entire stacked list item -->
		<a href="<?php echo esc_url( $wrapper_link ); ?>" class="portal-stacked-wrapper" data-js-hook="<?php echo esc_attr( $args['link_js_hook'] ?? '' ); ?>" data-post_id="<?php echo esc_attr( $args['id'] ); ?>" data-integration="<?php echo esc_attr( $args['integration'] ?? '' ); ?>">
			<div class="portal-stacked-list-item sd-flex sd-border sd-radius-12 sd-hover-shadow-xl sd-transition" id="portal-post-<?php echo esc_attr( $args['id'] ); ?>">

				<!-- Thumbnail (no longer wrapped in separate link) -->
				<div class="portal-stacked-thumbnail">
					<?php echo do_shortcode( $thumbnail_html ); ?>
				</div>

				<div class="portal-stacked-list-content sd-flex-col sd-justify-between">
					<div class="sd-flex-col sd-gap-4 sd-p-20 sd-card-content-container">
						<?php
							suredash_render_item_title_description(
								$args,
								[
									'title'       => $args['title'],
									'description' => $args['description'],
									'link'        => '', // Remove link from title as wrapper handles it.
									'layout_type' => 'stacked',
								]
							);
						?>
					</div>
					<div class="sd-p-12 sd-flex sd-flex-wrap sd-justify-between sd-items-center sd-border-t">
						<?php
						suredash_render_item_badges_and_options(
							$args,
							[
								'layout_type' => 'stacked',
							]
						);
						?>
					</div>
				</div>
			</div>
		</a>
	<?php
	echo do_shortcode( (string) ob_get_clean() );
}

/**
 * Render complete stacked list.
 *
 * @param array<int,array<string,mixed>> $items Array of item data for the stacked list.
 * @param array<string,mixed>            $list_args {
 *     Optional. Array of arguments for the list container.
 *
 *     @type string $title       Optional. List title/heading.
 *     @type string $class       Optional. Additional CSS classes.
 *     @type bool   $show_title  Optional. Whether to show the title. Default true.
 * }
 * @since 1.2.0
 * @return void
 */
function suredash_render_stacked_list( $items = [], $list_args = [] ): void {
	if ( empty( $items ) || ! is_array( $items ) ) {
		return;
	}

	$list_defaults = [
		'title'           => '',
		'container_class' => '',
		'show_title'      => true,
	];

	$list_args = wp_parse_args( $list_args, $list_defaults );

	// Define color cycle.
	$colors = [ 'red', 'orange', 'lime', 'teal', 'green', 'indigo' ];

	ob_start();
	?>
		<div class="portal-stacked-list-container sd-flex-col sd-gap-24 <?php echo esc_attr( $list_args['container_class'] ); ?>">
			<?php if ( $list_args['show_title'] && ! empty( $list_args['title'] ) ) { ?>
				<h3 class="portal-stacked-list-title sd-m-0 sd-mb-20 sd-font-18 sd-font-semibold">
					<?php echo esc_html( $list_args['title'] ); ?>
				</h3>
			<?php } ?>

			<div class="portal-stacked-list-items sd-flex sd-flex-col sd-gap-24">
				<?php
				foreach ( $items as $index => $item ) {
					// Assign color from cycle if not provided.
					if ( empty( $item['color'] ) ) {
						$item['color'] = $colors[ $index % count( $colors ) ];
					}

					suredash_render_stacked_list_item( $item );
				}
				?>
			</div>
		</div>
	<?php
	echo do_shortcode( (string) ob_get_clean() );
}

/**
 * Returns CSS styles for icon and icon background colors.
 *
 * @param array<string, mixed> $context Block context passed to Social Link.
 * @return string Inline CSS styles for link's icon and background colors.
 * @since 1.5.0
 */
function suredash_dynamic_icon_get_color_styles( $context ) {
	$styles = [];

	if ( array_key_exists( 'iconColorValue', $context ) ) {
		$styles[] = 'color: ' . $context['iconColorValue'] . '; ';
	}

	if ( array_key_exists( 'iconBackgroundColorValue', $context ) ) {
		$styles[] = 'background-color: ' . $context['iconBackgroundColorValue'] . '; ';
	}

	return implode( '', $styles );
}

/**
 * Returns CSS classes for icon and icon background colors.
 *
 * @param array<string, mixed> $context Block context passed to Social Link.
 * @return string CSS classes for link's icon and background colors.
 * @since 1.5.0
 */
function suredash_dynamic_icon_get_color_classes( $context ) {
	$classes = [];

	if ( array_key_exists( 'iconColor', $context ) ) {
		$classes[] = 'has-' . $context['iconColor'] . '-color';
	}

	if ( array_key_exists( 'iconBackgroundColor', $context ) ) {
		$classes[] = 'has-' . $context['iconBackgroundColor'] . '-background-color';
	}

	return ' ' . implode( ' ', $classes );
}

/**
 * Get user badges.
 *
 * @param int $user_id User ID.
 * @param int $badges_limit Badges limit.
 *
 * @return void
 * @since 1.5.0
 */
function suredash_get_user_badges( $user_id = 0, $badges_limit = -1 ): void {
	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}

	$portal_badges = Helper::get_option( 'user_badges' );
	$user_badges   = sd_get_user_meta( $user_id, 'portal_badges', true );

	if ( ! empty( $portal_badges ) && ! empty( $user_badges ) ) {
		$user_total_badges = count( $user_badges );
		$badge_count       = 0;
		$badge_ids         = array_column( $portal_badges, 'id' );
		$badge_names       = array_column( $portal_badges, 'name', 'id' );
		$icons             = array_column( $portal_badges, 'icon', 'id' );
		$colors            = array_column( $portal_badges, 'color', 'id' );
		$backgrounds       = array_column( $portal_badges, 'background', 'id' );
		$icons_visibility  = array_column( $portal_badges, 'icon_visibility', 'id' );

		$first_badge_data = [];

		echo '<div class="sd-flex sd-items-center sd-flex-wrap sd-gap-4 portal-user-badges-wrap portal-hide-on-responsive">';

		foreach ( $user_badges as $badge ) {
			$badge_id = $badge['id'] ?? '';

			if ( ! in_array( $badge_id, $badge_ids ) ) {
				continue;
			}

			$badge_name = $badge_names[ $badge_id ] ?? __( 'Member', 'suredash' );
			$skip_icon  = ! absint( $icons_visibility[ $badge_id ] ?? '1' ) ? true : false;
			$badge_icon = ! $skip_icon ? esc_attr( $icons[ $badge_id ] ?? '' ) : '';

			if ( empty( $first_badge_data ) ) {
				$first_badge_data = [
					'badge_id'   => $badge_id,
					'badge_name' => $badge_name,
					'badge_icon' => $badge_icon,
					'skip_icon'  => $skip_icon,
				];
			}

			Helper::show_badge(
				'custom',
				$badge_icon,
				esc_attr( $badge_name ),
				'xs',
				'user-badge',
				[
					'color'            => $colors[ $badge_id ] ?? '',
					'background-color' => $backgrounds[ $badge_id ] ?? '',
				],
				$skip_icon
			);

			++$badge_count;
			$remained_count       = $user_total_badges - $badge_count;
			$extra_badges_content = suredash_get_user_extra_badges( $user_id, $badges_limit );
			if ( $badges_limit >= 0 && $badge_count >= $badges_limit && $remained_count ) {
				echo '<span class="sd-font-10 sd-radius-9999 sd-border sd-text-color sd-line-1 sd-p-4 tooltip-trigger user-badge" data-tooltip-description="' . esc_attr( $extra_badges_content ) . '" data-tooltip-position="right">+' . esc_attr( strval( $remained_count ) ) . '</span>';
				break;
			}
		}

		echo '</div>';

		if ( empty( $first_badge_data ) ) {
			return;
		}

		echo '<div class="sd-flex sd-items-center sd-flex-wrap sd-gap-4 portal-user-badges-wrap portal-hide-on-desktop">';

		Helper::show_badge(
			'custom',
			$first_badge_data['badge_icon'],
			esc_attr( $first_badge_data['badge_name'] ),
			'xs',
			'user-badge',
			[
				'color'            => $colors[ $first_badge_data['badge_id'] ] ?? '',
				'background-color' => $backgrounds[ $first_badge_data['badge_id'] ] ?? '',
			],
			$first_badge_data['skip_icon']
		);

		$badge_count          = 1;
		$remained_count       = $user_total_badges - $badge_count;
		$extra_badges_content = suredash_get_user_extra_badges( $user_id, $badge_count );
		if ( $remained_count ) {
			echo '<span class="sd-font-10 sd-radius-9999 sd-border sd-text-color sd-line-1 sd-p-4 tooltip-trigger user-badge" data-tooltip-description="' . esc_attr( $extra_badges_content ) . '" data-tooltip-position="right">+' . esc_attr( strval( $remained_count ) ) . '</span>';
		}

		echo '</div>';
	}
}
