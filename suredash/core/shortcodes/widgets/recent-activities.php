<?php
/**
 * Recent Activities Widget Renderer.
 *
 * @package SureDash
 * @since 1.6.0
 */

namespace SureDashboard\Core\Shortcodes\Widgets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Recent Activities Widget Class.
 */
class Recent_Activities {
	/**
	 * Render the Recent Activities widget.
	 *
	 * @param int          $space_id Space ID.
	 * @param array<mixed> $settings Widget settings.
	 * @return void
	 * @since 1.6.0
	 */
	public static function render( $space_id, $settings = [] ): void {
		// Don't show Recent Activity widget if discussions are private.
		if ( function_exists( 'suredash_is_private_discussion_area' ) && suredash_is_private_discussion_area( $space_id ) ) {
			return;
		}

		// Get space integration type to determine what to show.
		$integration_type = get_post_meta( $space_id, 'integration', true );

		// Resolve widget title (custom or default).
		$title = ! empty( $settings['customTitle'] )
			? $settings['customTitle']
			: __( 'Recent Activities', 'suredash' );

		// Render based on space integration type.
		self::render_space_activities( $space_id, $integration_type, $title );
	}

	/**
	 * Format time difference for display.
	 *
	 * @param string $date_string Date string to format.
	 * @return string Formatted time difference.
	 * @since 1.6.0
	 */
	private static function format_time_ago( $date_string ): string {
		$timestamp = strtotime( $date_string );
		$time_diff = human_time_diff(
			$timestamp ? $timestamp : time(),
			time()
		);
		return sprintf(
			/* translators: %s: time ago */
			esc_html__( '%s ago', 'suredash' ),
			esc_html( $time_diff )
		);
	}

	/**
	 * Render latest comment for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 * @since 1.6.0
	 */
	private static function render_latest_comment( $post_id ): void {
		$comments = get_comments(
			[
				'post_id' => $post_id,
				'status'  => 'approve',
				'number'  => 1,
				'orderby' => 'comment_date',
				'order'   => 'DESC',
			]
		);

		if ( empty( $comments ) || ! is_array( $comments ) ) {
			?>
			<div class="portal-widget-empty">
				<p><?php esc_html_e( 'No recent comments yet.', 'suredash' ); ?></p>
			</div>
			<?php
			return;
		}

		$comment = $comments[0];

		// Type guard: ensure we have a WP_Comment object.
		if ( ! $comment instanceof \WP_Comment ) {
			return;
		}
		?>
		<div class="portal-widget-recent-activities">
			<div class="portal-widget-activity-item">
			<div class="portal-widget-activity-avatar">
				<?php echo wp_kses_post( suredash_get_user_avatar( (int) $comment->user_id, true, 32 ) ); ?>
			</div>
			<div class="portal-widget-activity-content">
				<div class="portal-widget-activity">
					<a class="portal-widget-activity-author" href="<?php echo esc_url( suredash_get_user_view_link( (int) $comment->user_id ) ); ?>">
						<?php echo esc_html( $comment->comment_author ); ?>
					</a>
					<?php echo esc_html__( ' commented on: ', 'suredash' ); ?>
					<?php $comment_permalink = get_permalink( (int) $comment->comment_post_ID ); ?>
					<a class="" href="<?php echo esc_url( $comment_permalink ? $comment_permalink : '' ); ?>">
						<?php echo esc_html( '"' . get_the_title( (int) $comment->comment_post_ID ) . '"' ); ?>
					</a>
				</div>
				<div class="portal-widget-activity-time">
					<?php echo self::format_time_ago( $comment->comment_date ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render space activities based on space integration type.
	 *
	 * @param int    $space_id         Space ID.
	 * @param string $integration_type Space integration type.
	 * @param string $title            Widget title.
	 * @return void
	 * @since 1.6.0
	 */
	private static function render_space_activities( $space_id, $integration_type, $title = '' ): void {
		if ( empty( $title ) ) {
			$title = __( 'Recent Activities', 'suredash' );
		}
		// Buffer the activities content to check if there's any data.
		ob_start();

		switch ( $integration_type ) {
			case 'single_post':
				self::render_latest_comment( $space_id );
				break;

			case 'posts_discussion':
				// For posts_discussion: show latest post and latest comment from the category.
				self::render_latest_post( $space_id );
				self::render_latest_space_comment( $space_id );
				break;

			case 'course':
				// For course: show latest comment from any lesson inside that course.
				self::render_latest_course_comment( $space_id );
				break;

			case 'resource_library':
				// For resource_library: show latest resource and latest comment.
				self::render_latest_resource( $space_id );
				self::render_latest_resource_comment( $space_id );
				break;

			case 'events':
				// For events: show latest event and latest comment.
				self::render_latest_event( $space_id );
				self::render_latest_event_comment( $space_id );
				break;

			case 'collection':
				// For collection: show latest space added to the collection.
				self::render_latest_collection_space( $space_id );
				break;

			default:
				// For other types: show latest post and comment if feed_group_id exists.
				$feed_group_id = absint( get_post_meta( $space_id, 'feed_group_id', true ) );
				if ( $feed_group_id ) {
					self::render_latest_post( $space_id );
					self::render_latest_space_comment( $space_id );
				}
				break;
		}
		$activities_content = ob_get_clean();

		// If there's no activity content, show empty message.
		if ( empty( trim( (string) $activities_content ) ) ) {
			?>
			<div class="portal-widget-recent-activities">
				<span class="portal-widget-section-title">
					<?php echo esc_html( $title ); ?>
				</span>
				<div class="portal-widget-empty">
					<p><?php esc_html_e( 'No recent activities.', 'suredash' ); ?></p>
				</div>
			</div>
			<?php
			return;
		}

		// Display the container with activities.
		?>
		<div class="portal-widget-recent-activities">
			<span class="portal-widget-section-title">
				<?php echo esc_html( $title ); ?>
			</span>
			<div class="portal-widget-activities">
				<?php echo $activities_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render latest post from space.
	 *
	 * @param int $space_id Space ID.
	 * @return void
	 * @since 1.6.0
	 */
	private static function render_latest_post( $space_id ): void {
		// Get the category (feed_group_id) associated with this space.
		$feed_group_id = absint( get_post_meta( $space_id, 'feed_group_id', true ) );

		if ( ! $feed_group_id ) {
			return;
		}

		// Query posts by category, not by space_id meta.
		$posts = get_posts(
			[
				'post_type'      => SUREDASHBOARD_FEED_POST_TYPE,
				'posts_per_page' => 1,
				'post_status'    => 'publish',
				'orderby'        => 'date',
				'order'          => 'DESC',
				'tax_query'      => [
					[
						'taxonomy' => SUREDASHBOARD_FEED_TAXONOMY,
						'field'    => 'term_id',
						'terms'    => $feed_group_id,
					],
				],
			]
		);

		if ( empty( $posts ) ) {
			return;
		}

		$post = $posts[0];
		?>
		<div class="portal-widget-activity-item">
			<div class="portal-widget-activity-avatar sd-pt-4">
				<?php echo wp_kses_post( suredash_get_user_avatar( (int) $post->post_author, true, 32 ) ); ?>
			</div>
			<div class="portal-widget-activity-content">
				<div class="portal-widget-activity">
					<?php $user_id = absint( $post->post_author ); ?>
					<a class="portal-widget-activity-author" href="<?php echo esc_url( suredash_get_user_view_link( $user_id ) ); ?>">
						<?php echo esc_html( get_the_author_meta( 'display_name', $user_id ) ); ?>
					</a>
					<?php echo esc_html__( ' posted: ', 'suredash' ); ?>
					<?php $post_permalink = get_permalink( $post->ID ); ?>
					<a class="" href="<?php echo esc_url( $post_permalink ? $post_permalink : '' ); ?>">
						<?php echo esc_html( '"' . get_the_title( $post->ID ) . '"' ); ?>
					</a>
				</div>
				<div class="portal-widget-activity-time">
					<?php echo self::format_time_ago( $post->post_date ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render latest comment from space.
	 *
	 * @param int $space_id Space ID.
	 * @return void
	 * @since 1.6.0
	 */
	private static function render_latest_space_comment( $space_id ): void {
		// Get the category (feed_group_id) associated with this space.
		$feed_group_id = absint( get_post_meta( $space_id, 'feed_group_id', true ) );

		if ( ! $feed_group_id ) {
			return;
		}

		// Get posts from this category.
		$post_ids = get_posts(
			[
				'post_type'      => SUREDASHBOARD_FEED_POST_TYPE,
				'posts_per_page' => 100,
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'tax_query'      => [
					[
						'taxonomy' => SUREDASHBOARD_FEED_TAXONOMY,
						'field'    => 'term_id',
						'terms'    => $feed_group_id,
					],
				],
			]
		);

		if ( empty( $post_ids ) ) {
			return;
		}

		$comments = get_comments(
			[
				'post__in' => $post_ids,
				'status'   => 'approve',
				'number'   => 1,
				'orderby'  => 'comment_date',
				'order'    => 'DESC',
			]
		);

		if ( empty( $comments ) || ! is_array( $comments ) ) {
			return;
		}

		$comment = $comments[0];

		// Type guard: ensure we have a WP_Comment object.
		if ( ! $comment instanceof \WP_Comment ) {
			return;
		}
		?>
		<div class="portal-widget-activity-item">
			<div class="portal-widget-activity-avatar">
				<?php echo wp_kses_post( suredash_get_user_avatar( (int) $comment->user_id, true, 32 ) ); ?>
			</div>
			<div class="portal-widget-activity-content">
				<div class="portal-widget-activity">
					<a class="portal-widget-activity-author" href="<?php echo esc_url( suredash_get_user_view_link( (int) $comment->user_id ) ); ?>">
						<?php echo esc_html( $comment->comment_author ); ?>
					</a>
					<?php echo esc_html__( ' commented on: ', 'suredash' ); ?>
					<?php $comment_permalink = get_permalink( (int) $comment->comment_post_ID ); ?>
					<a class="" href="<?php echo esc_url( $comment_permalink ? $comment_permalink : '' ); ?>">
						<?php echo esc_html( '"' . get_the_title( (int) $comment->comment_post_ID ) . '"' ); ?>
					</a>
				</div>
				<div class="portal-widget-activity-time">
					<?php echo self::format_time_ago( $comment->comment_date ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render latest event from space.
	 *
	 * @param int $space_id Space ID.
	 * @return void
	 * @since 1.6.0
	 */
	private static function render_latest_event( $space_id ): void {
		// Get event IDs from space meta.
		$event_ids = get_post_meta( $space_id, 'event_ids', true );

		if ( empty( $event_ids ) || ! is_array( $event_ids ) ) {
			return;
		}

		// Get the latest event.
		$events = get_posts(
			[
				'post_type'      => SUREDASHBOARD_SUB_CONTENT_POST_TYPE,
				'posts_per_page' => 1,
				'post_status'    => 'publish',
				'post__in'       => $event_ids,
				'orderby'        => 'date',
				'order'          => 'DESC',
			]
		);

		if ( empty( $events ) ) {
			return;
		}

		$event = $events[0];
		?>
		<div class="portal-widget-activity-item">
			<div class="portal-widget-activity-avatar sd-pt-4">
				<?php echo wp_kses_post( suredash_get_user_avatar( (int) $event->post_author, true, 32 ) ); ?>
			</div>
			<div class="portal-widget-activity-content">
				<div class="portal-widget-activity-title">
					<?php $event_permalink = get_permalink( $event->ID ); ?>
					<a href="<?php echo esc_url( $event_permalink ? $event_permalink : '' ); ?>">
						<?php echo esc_html( get_the_title( $event->ID ) ); ?>
					</a>
				</div>
				<div class="portal-widget-activity-time">
					<?php echo self::format_time_ago( $event->post_date ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render latest resource from space.
	 *
	 * @param int $space_id Space ID.
	 * @return void
	 * @since 1.6.0
	 */
	private static function render_latest_resource( $space_id ): void {
		// Get resource IDs from space meta.
		$resource_ids = get_post_meta( $space_id, 'resource_ids', true );

		if ( empty( $resource_ids ) || ! is_array( $resource_ids ) ) {
			return;
		}

		// Get the latest resource.
		$resources = get_posts(
			[
				'post_type'      => SUREDASHBOARD_SUB_CONTENT_POST_TYPE,
				'posts_per_page' => 1,
				'post_status'    => 'publish',
				'post__in'       => $resource_ids,
				'orderby'        => 'date',
				'order'          => 'DESC',
			]
		);

		if ( empty( $resources ) ) {
			return;
		}

		$resource = $resources[0];
		?>
		<div class="portal-widget-activity-item">
			<div class="portal-widget-activity-avatar">
				<?php echo wp_kses_post( suredash_get_user_avatar( (int) $resource->post_author, true, 32 ) ); ?>
			</div>
			<div class="portal-widget-activity-content">
				<div class="portal-widget-activity">
					<?php $user_id = absint( $resource->post_author ); ?>
					<a class="portal-widget-activity-author" href="<?php echo esc_url( suredash_get_user_view_link( $user_id ) ); ?>">
						<?php echo esc_html( get_the_author_meta( 'display_name', $user_id ) ); ?>
					</a>
					<?php echo esc_html__( ' posted: ', 'suredash' ); ?>
					<?php $resource_permalink = get_permalink( $resource->ID ); ?>
					<a href="<?php echo esc_url( $resource_permalink ? $resource_permalink : '' ); ?>">
						<?php echo esc_html( get_the_title( $resource->ID ) ); ?>
					</a>
				</div>
				<div class="portal-widget-activity-time">
					<?php echo self::format_time_ago( $resource->post_date ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render latest comment from resource posts.
	 *
	 * @param int $space_id Space ID.
	 * @return void
	 * @since 1.6.0
	 */
	private static function render_latest_resource_comment( $space_id ): void {
		// Get resource IDs from space meta.
		$resource_ids = get_post_meta( $space_id, 'resource_ids', true );

		if ( empty( $resource_ids ) || ! is_array( $resource_ids ) ) {
			return;
		}

		// Get latest comment from resources.
		$comments = get_comments(
			[
				'post__in' => $resource_ids,
				'status'   => 'approve',
				'number'   => 1,
				'orderby'  => 'comment_date',
				'order'    => 'DESC',
			]
		);

		if ( empty( $comments ) || ! is_array( $comments ) ) {
			return;
		}

		$comment = $comments[0];

		// Type guard: ensure we have a WP_Comment object.
		if ( ! $comment instanceof \WP_Comment ) {
			return;
		}
		?>
		<div class="portal-widget-activity-item">
			<div class="portal-widget-activity-avatar">
				<?php echo wp_kses_post( suredash_get_user_avatar( (int) $comment->user_id, true, 32 ) ); ?>
			</div>
			<div class="portal-widget-activity-content">
				<div class="portal-widget-activity">
					<a class="portal-widget-activity-author" href="<?php echo esc_url( suredash_get_user_view_link( (int) $comment->user_id ) ); ?>">
						<?php echo esc_html( $comment->comment_author ); ?>
					</a>
					<?php echo esc_html__( ' commented on: ', 'suredash' ); ?>
					<?php $comment_permalink = get_permalink( (int) $comment->comment_post_ID ); ?>
					<a class="" href="<?php echo esc_url( $comment_permalink ? $comment_permalink : '' ); ?>">
						<?php echo esc_html( '"' . get_the_title( (int) $comment->comment_post_ID ) . '"' ); ?>
					</a>
				</div>
				<div class="portal-widget-activity-time">
					<?php echo self::format_time_ago( $comment->comment_date ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render latest comment from event posts.
	 *
	 * @param int $space_id Space ID.
	 * @return void
	 * @since 1.6.0
	 */
	private static function render_latest_event_comment( $space_id ): void {
		// Get event IDs from space meta.
		$event_ids = get_post_meta( $space_id, 'event_ids', true );

		if ( empty( $event_ids ) || ! is_array( $event_ids ) ) {
			return;
		}

		// Get latest comment from events.
		$comments = get_comments(
			[
				'post__in' => $event_ids,
				'status'   => 'approve',
				'number'   => 1,
				'orderby'  => 'comment_date',
				'order'    => 'DESC',
			]
		);

		if ( empty( $comments ) || ! is_array( $comments ) ) {
			return;
		}

		$comment = $comments[0];

		// Type guard: ensure we have a WP_Comment object.
		if ( ! $comment instanceof \WP_Comment ) {
			return;
		}
		?>
		<div class="portal-widget-activity-item">
			<div class="portal-widget-activity-avatar">
				<?php echo wp_kses_post( suredash_get_user_avatar( (int) $comment->user_id, true, 32 ) ); ?>
			</div>
			<div class="portal-widget-activity-content">
				<div class="portal-widget-activity">
					<a class="portal-widget-activity-author" href="<?php echo esc_url( suredash_get_user_view_link( (int) $comment->user_id ) ); ?>">
						<?php echo esc_html( $comment->comment_author ); ?>
					</a>
					<?php echo esc_html__( ' commented on: ', 'suredash' ); ?>
					<?php $comment_permalink = get_permalink( (int) $comment->comment_post_ID ); ?>
					<a class="" href="<?php echo esc_url( $comment_permalink ? $comment_permalink : '' ); ?>">
						<?php echo esc_html( '"' . get_the_title( (int) $comment->comment_post_ID ) . '"' ); ?>
					</a>
				</div>
				<div class="portal-widget-activity-time">
					<?php echo self::format_time_ago( $comment->comment_date ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render latest comment from course lessons.
	 *
	 * @param int $space_id Space ID.
	 * @return void
	 * @since 1.6.0
	 */
	private static function render_latest_course_comment( $space_id ): void {
		// Get course loop from space meta.
		$course_loop = get_post_meta( $space_id, 'pp_course_section_loop', true );

		if ( empty( $course_loop ) || ! is_array( $course_loop ) ) {
			return;
		}

		// Get all lesson IDs from course loop.
		$lesson_ids = suredash_get_lesson_ids_from_course_loop( $course_loop );

		if ( empty( $lesson_ids ) ) {
			return;
		}

		// Get latest comment from lessons.
		$comments = get_comments(
			[
				'post__in' => $lesson_ids,
				'status'   => 'approve',
				'number'   => 1,
				'orderby'  => 'comment_date',
				'order'    => 'DESC',
			]
		);

		if ( empty( $comments ) || ! is_array( $comments ) ) {
			return;
		}

		$comment = $comments[0];

		// Type guard: ensure we have a WP_Comment object.
		if ( ! $comment instanceof \WP_Comment ) {
			return;
		}
		?>
		<div class="portal-widget-activity-item">
			<div class="portal-widget-activity-avatar">
				<?php echo wp_kses_post( suredash_get_user_avatar( (int) $comment->user_id, true, 32 ) ); ?>
			</div>
			<div class="portal-widget-activity-content">
				<div class="portal-widget-activity">
					<a class="portal-widget-activity-author" href="<?php echo esc_url( suredash_get_user_view_link( (int) $comment->user_id ) ); ?>">
						<?php echo esc_html( $comment->comment_author ); ?>
					</a>
					<?php echo esc_html__( ' commented on: ', 'suredash' ); ?>
					<?php $comment_permalink = get_permalink( (int) $comment->comment_post_ID ); ?>
					<a class="" href="<?php echo esc_url( $comment_permalink ? $comment_permalink : '' ); ?>">
						<?php echo esc_html( '"' . get_the_title( (int) $comment->comment_post_ID ) . '"' ); ?>
					</a>
				</div>
				<div class="portal-widget-activity-time">
					<?php echo self::format_time_ago( $comment->comment_date ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render latest space added to collection.
	 *
	 * @param int $space_id Space ID.
	 * @return void
	 * @since 1.6.0
	 */
	private static function render_latest_collection_space( $space_id ): void {
		// Get collection space IDs from space meta.
		$collection_spaces = get_post_meta( $space_id, 'collection_spaces', true );

		if ( empty( $collection_spaces ) || ! is_array( $collection_spaces ) ) {
			return;
		}

		// Get the latest space from collection.
		$spaces = get_posts(
			[
				'post_type'      => SUREDASHBOARD_POST_TYPE,
				'posts_per_page' => 1,
				'post_status'    => 'publish',
				'post__in'       => $collection_spaces,
				'orderby'        => 'date',
				'order'          => 'DESC',
			]
		);

		if ( empty( $spaces ) ) {
			return;
		}

		$space = $spaces[0];
		?>
		<div class="portal-widget-activity-item">
			<div class="portal-widget-activity-avatar">
				<?php echo wp_kses_post( suredash_get_user_avatar( (int) $space->post_author, true, 32 ) ); ?>
			</div>
			<div class="portal-widget-activity-content">
				<div class="portal-widget-activity-title">
					<?php $space_permalink = get_permalink( $space->ID ); ?>
					<a href="<?php echo esc_url( $space_permalink ? $space_permalink : '' ); ?>">
						<?php echo esc_html( get_the_title( $space->ID ) ); ?>
					</a>
				</div>
				<div class="portal-widget-activity-time">
					<?php echo self::format_time_ago( $space->post_date ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			</div>
		</div>
		<?php
	}
}
