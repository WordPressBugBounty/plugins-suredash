<?php
/**
 * Central renderer for SureMembers locked-item cards.
 *
 * Each layout mirrors its unlocked counterpart in `markup.php`
 * (`suredash_render_list_item` / `_stacked_list_item` / `_card_grid_item`) EXACTLY — same
 * wrapper, same thumbnail/avatar, same `suredash_render_item_title_description()` — so a
 * locked item is visually identical to an unlocked one. The only differences:
 *   - the wrapper link points to "#" and carries the data-sd-* lock attributes,
 *   - a small "Locked" pill sits with the title,
 *   - the footer/options are replaced by a "Locked" status,
 *   - (grid only) the cover is blurred + dimmed with a lock badge.
 *
 * @package SureDash
 */

use SureDashboard\Inc\Utils\Helper;

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'suredash_locked_data_attrs' ) ) {
	/**
	 * Build the data-sd-* attribute string the front-end click handler reads.
	 *
	 * @param array<string, mixed> $lock Lock action payload from suredash_sm_item_action().
	 * @param int                  $id   Item ID (used for the page-post fetch).
	 * @return string Escaped HTML attribute string.
	 * @since 1.10.0
	 */
	function suredash_locked_data_attrs( $lock, $id ): string {
		$attrs = [
			'data-sd-locked'    => '1',
			'data-sd-action'    => (string) ( $lock['action'] ?? 'message' ),
			'data-sd-redirect'  => (string) ( $lock['redirect_url'] ?? '' ),
			'data-sd-page-item' => (string) absint( $id ),
			'data-sd-heading'   => (string) ( $lock['heading'] ?? '' ),
			'data-sd-message'   => (string) ( $lock['message'] ?? '' ),
			'data-sd-button'    => (string) ( $lock['button'] ?? '' ),
			'data-sd-login'     => ! empty( $lock['login'] ) ? '1' : '',
		];

		$out = '';
		foreach ( $attrs as $key => $value ) {
			$out .= ' ' . $key . '="' . esc_attr( $value ) . '"';
		}
		return $out;
	}
}

if ( ! function_exists( 'suredash_locked_opens_quick_view' ) ) {
	/**
	 * Whether a locked item should behave like its UNLOCKED counterpart on click (open its native
	 * detail view) instead of the bare restriction popup. Used for the "In content" toggle of the
	 * message/preview action.
	 *
	 * When this is true the card keeps its frost + lock overlay but renders the same wrapper attributes
	 * as a normal card (real link + data-post_id + data-js-hook, no data-sd-locked), so each content
	 * type's own open mechanism takes over: discussions and resources open their real quick-view (whose
	 * body is swapped for the restriction template), and events fall through to their single page (which
	 * renders the restriction in place). When it is false the locked item opens the bare popup.
	 *
	 * Applies to every layout — events and resources render as grid/stacked cards, not just list.
	 *
	 * @param array<string, mixed> $lock Lock action payload from suredash_sm_item_action().
	 * @return bool
	 * @since 1.10.0
	 */
	function suredash_locked_opens_quick_view( $lock ): bool {
		return ( $lock['action'] ?? '' ) === 'message'
			&& ! empty( $lock['in_content'] );
	}
}

if ( ! function_exists( 'suredash_quick_view_trigger_attrs' ) ) {
	/**
	 * Build the quick-view trigger data attributes (same shape as the normal list item in markup.php)
	 * so the existing archive.js `openQuickView` handler opens the post's real quick-view popup.
	 *
	 * Deliberately omits `data-sd-locked` — that attribute is what makes sm-locked.js intercept the
	 * click and show the bare restriction popup. Without it, the click falls through to openQuickView.
	 *
	 * @param array<string, mixed> $args Item data (same shape the normal renderers receive).
	 * @return string Escaped HTML attribute string.
	 * @since 1.10.0
	 */
	function suredash_quick_view_trigger_attrs( $args ): string {
		$attrs = [
			'data-post_id'     => (string) absint( $args['id'] ?? 0 ),
			'data-integration' => (string) ( $args['integration'] ?? '' ),
			'data-comments'    => '1',
			// Carry the item's open hook so type-specific triggers still fire — e.g. resources use
			// data-js-hook="view-resource" to open their quick-view.
			'data-js-hook'     => (string) ( $args['link_js_hook'] ?? '' ),
		];

		$out = '';
		foreach ( $attrs as $key => $value ) {
			$out .= ' ' . $key . '="' . esc_attr( $value ) . '"';
		}
		return $out;
	}
}

if ( ! function_exists( 'suredash_locked_overlay' ) ) {
	/**
	 * Centered frosted-layer overlay: a single rounded container with a padlock icon inside.
	 *
	 * Used by the full-frost layouts (list/grid/stacked locked cards and the locked single post),
	 * where the whole container is blurred via CSS and this padlock is the one sharp element.
	 * The solid dark circle behind the padlock is styled via CSS — see `.portal-locked-lock svg`.
	 *
	 * @param string $icon Library icon to render — 'Lock' (default), or 'Clock' for dripping content.
	 * @return string
	 * @since 1.10.0
	 */
	function suredash_locked_overlay( $icon = 'Lock' ): string {
		ob_start();
		?>
		<div class="portal-locked-overlay" aria-hidden="true">
			<span class="portal-locked-lock"><?php Helper::get_library_icon( $icon, true ); ?></span>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}

if ( ! function_exists( 'suredash_locked_footer_actions' ) ) {
	/**
	 * Static, non-interactive "shell" footer for locked cards: dummy like + comment icons.
	 *
	 * Locked cards never expose real reactions or info badges (lesson count, progress) — the whole
	 * card is the lock trigger — so these are decorative placeholders matching the unlocked footer.
	 *
	 * @return string
	 * @since 1.10.0
	 */
	function suredash_locked_footer_actions(): string {
		ob_start();
		?>
		<div class="portal-locked-actions sd-flex sd-items-center sd-gap-4" aria-hidden="true">
			<span class="portal-button button-ghost sd-p-8 sd-radius-9999">
				<?php Helper::get_library_icon( 'Heart', true ); ?>
			</span>
			<span class="portal-button button-ghost sd-p-8 sd-radius-9999">
				<?php Helper::get_library_icon( 'MessageCircle', true ); ?>
			</span>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}

if ( ! function_exists( 'suredash_render_locked_card' ) ) {
	/**
	 * Render the locked variant of a listing item, mirroring its unlocked markup.
	 *
	 * @param array<string, mixed> $args   Item data (same shape the normal renderers receive).
	 * @param string               $layout One of 'list' | 'stacked' | 'grid'.
	 * @return void
	 * @since 1.10.0
	 */
	function suredash_render_locked_card( $args, $layout = 'list' ): void {
		$id = absint( $args['id'] ?? 0 );
		if ( $id <= 0 || empty( $args['title'] ) ) {
			return;
		}

		$lock  = ! empty( $args['lock_action'] ) ? $args['lock_action'] : ( function_exists( 'suredash_sm_item_action' ) ? suredash_sm_item_action( $id ) : [] );
		$attrs = suredash_locked_data_attrs( $lock, $id );

		// "In content" on (message action) → the locked card behaves like its unlocked counterpart on
		// click (opens the item's native detail view, which renders the restriction), so we emit the
		// normal wrapper attributes + a real link instead of the bare-popup lock attributes. Off →
		// the bare restriction popup. Applies to every layout (events/resources render as grid/stacked).
		$opens_quick_view = suredash_locked_opens_quick_view( $lock );
		$wrapper_attrs    = $opens_quick_view ? suredash_quick_view_trigger_attrs( $args ) : $attrs;
		$wrapper_href     = $opens_quick_view && ! empty( $args['link'] ) ? (string) $args['link'] : '#';
		$overlay_icon     = ! empty( $lock['drip'] ) ? 'Clock' : 'Lock';

		// Thumbnail markup, identical to the normal renderers.
		$thumbnail_html = isset( $args['thumbnail_label'] )
			? Helper::get_space_featured_image( $id, true, (string) ( $args['color'] ?? 'blue' ), (string) ( $args['avatar'] ?? '' ), (string) ( $args['thumbnail_label'] ?? '' ) )
			: Helper::get_space_featured_image( $id, true, (string) ( $args['color'] ?? 'blue' ), (string) ( $args['avatar'] ?? '' ) );

		switch ( $layout ) {
			case 'grid':
				ob_start();
				?>
				<a href="<?php echo esc_url( $wrapper_href ); ?>" class="portal-card-wrapper sd-color-inherit portal-locked-card is-locked"<?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped ?>>
					<div class="portal-grid-item-content portal-home-grid-item-content-minimal sd-border sd-hover-shadow-2xl portal-locked-frost" id="portal-post-<?php echo esc_attr( (string) $id ); ?>">
						<div class="portal-card-thumbnail">
							<?php echo do_shortcode( $thumbnail_html ); ?>
						</div>
						<div class="sd-flex-col sd-card-main-container">
							<div class="sd-flex-col sd-gap-4 sd-p-16 sd-card-content-container sd-relative">
								<?php
								// Title only — the centered overlay (below) carries the lock cue.
								suredash_render_item_title_description(
									$args,
									[
										'title'       => $args['title'],
										'description' => $args['description'] ?? '',
										'link'        => '',
										'layout_type' => 'card',
									]
								);
								?>
							</div>
						</div>
						<div class="sd-px-16 sd-py-12 sd-flex sd-justify-between sd-items-center sd-border-t sd-mt-auto">
							<?php echo suredash_locked_footer_actions(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- internal markup ?>
						</div>
						<?php echo suredash_locked_overlay( $overlay_icon ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- internal markup ?>
					</div>
				</a>
				<?php
				echo do_shortcode( (string) ob_get_clean() );
				break;

			case 'stacked':
				ob_start();
				?>
				<a href="<?php echo esc_url( $wrapper_href ); ?>" class="portal-stacked-wrapper portal-locked-card is-locked"<?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped ?>>
					<div class="portal-stacked-list-item sd-flex sd-border sd-radius-12 sd-hover-shadow-xl sd-transition portal-locked-frost" id="portal-post-<?php echo esc_attr( (string) $id ); ?>">
						<div class="portal-stacked-thumbnail">
							<?php echo do_shortcode( $thumbnail_html ); ?>
						</div>
						<div class="portal-stacked-list-content sd-flex-col sd-justify-between">
							<div class="sd-flex-col sd-gap-4 sd-p-20 sd-card-content-container">
								<?php
								// Title only — the centered overlay (below) carries the lock cue.
								suredash_render_item_title_description(
									$args,
									[
										'title'       => $args['title'],
										'description' => $args['description'] ?? '',
										'link'        => '',
										'layout_type' => 'stacked',
									]
								);
								?>
							</div>
						</div>
						<?php echo suredash_locked_overlay( $overlay_icon ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- internal markup ?>
					</div>
				</a>
				<?php
				echo do_shortcode( (string) ob_get_clean() );
				break;

			case 'list':
			default:
				ob_start();
				?>
				<a href="<?php echo esc_url( $wrapper_href ); ?>" class="portal-list-wrapper portal-locked-card is-locked"<?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped ?>>
					<div class="portal-list-item portal-store-list-post portal-content sd-flex sd-items-center sd-gap-16 sd-m-0 sd-p-16 sd-radius-8 sd-hover-shadow-md portal-locked-frost" id="portal-post-<?php echo esc_attr( (string) $id ); ?>">
						<?php if ( ! empty( $args['avatar'] ) ) { ?>
							<div class="portal-list-item-avatar sd-shrink-0">
								<?php if ( ( $args['avatar_type'] ?? '' ) === 'image' ) { ?>
									<img src="<?php echo esc_url( $args['avatar'] ); ?>" alt="<?php echo esc_attr( $args['title'] ); ?>" class="portal-list-item-avatar-img sd-radius-8"/>
								<?php } else { ?>
									<div class="portal-list-item-avatar-icon sd-radius-9999 sd-flex sd-items-center sd-justify-center sd-stroke-dark">
										<?php Helper::get_library_icon( (string) $args['avatar'], true, 'md' ); ?>
									</div>
								<?php } ?>
							</div>
							<?php
						} elseif ( ! empty( $args['user_initials_icon'] ) && function_exists( 'suredash_get_user_avatar' ) ) {
							suredash_get_user_avatar( $args['user_id'] ?? 0 );
						}
						?>
						<div class="portal-list-item-content sd-flex-1 sd-min-w-0">
							<?php
							// Title only — the centered overlay carries the lock cue, same as every other layout.
							suredash_render_item_title_description(
								$args,
								[
									'title'       => $args['title'],
									'description' => $args['description'] ?? '',
									'link'        => '',
									'layout_type' => 'list',
								]
							);
							?>
						</div>
						<?php suredash_render_pricing_badge( $args, false ); // Solid pill at the row's end — locked rows have no action button to slot before. ?>
						<?php echo suredash_locked_overlay( $overlay_icon ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- internal markup ?>
					</div>
				</a>
				<?php
				echo do_shortcode( (string) ob_get_clean() );
				break;
		}
	}
}
