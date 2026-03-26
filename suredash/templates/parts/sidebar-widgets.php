<?php
/**
 * The template for displaying space sidebar widgets.
 *
 * @package SureDash\Templates
 * @version 1.3.2
 */

defined( 'ABSPATH' ) || exit;

// Get current space ID.
$space_id = get_queried_object_id();

// Get space sidebar widgets from meta.
$sidebar_widgets = sd_get_post_meta( $space_id, 'space_sidebar_widgets', true );

// Widgets that should only appear once (first occurrence wins).
$unique_widgets = [ 'about-space', 'recent-activities' ];

// Check if global sidebar is enabled for this space.
$global_sidebar_enabled = $sidebar_widgets['global_sidebar_enabled'] ?? true;

// Helper to sort widgets by order.
$sort_by_order = static function ( $a, $b ) {
	return ( $a['order'] ?? 0 ) <=> ( $b['order'] ?? 0 );
};

// Collect global widgets first (sorted by order).
$global_widgets = [];
if ( $global_sidebar_enabled ) {
	$global_widgets = \SureDashboard\Inc\Utils\Helper::get_option( 'global_sidebar_widgets', [] );
	if ( ! empty( $global_widgets ) && is_array( $global_widgets ) ) {
		usort( $global_widgets, $sort_by_order );
	} else {
		$global_widgets = [];
	}
}

// Collect space-level widgets (sorted by order).
$space_widgets = [];
if ( ! empty( $sidebar_widgets['widgets'] ) && is_array( $sidebar_widgets['widgets'] ) ) {
	$space_widgets = $sidebar_widgets['widgets'];
	usort( $space_widgets, $sort_by_order );
}

// Merge: global first, then space-level.
$all_widgets = array_merge( $global_widgets, $space_widgets );

// Remove duplicate unique widgets (keep first occurrence).
$seen_unique = [];
$widgets     = [];

foreach ( $all_widgets as $widget ) {
	$slug = (string) ( $widget['slug'] ?? '' );

	// Skip if this unique widget was already added.
	if ( in_array( $slug, $unique_widgets, true ) && in_array( $slug, $seen_unique, true ) ) {
		continue;
	}

	// Track unique widgets we've seen.
	if ( in_array( $slug, $unique_widgets, true ) ) {
		$seen_unique[] = $slug;
	}

	$widgets[] = $widget;
}

// Check if we have any widgets to display.
if ( empty( $widgets ) ) {
	return;
}
?>

<aside class="portal-aside-right portal-sidebar-widgets">
	<div class="portal-sidebar-widgets-inner">
		<?php
		foreach ( $widgets as $widget ) {
			$widget_id    = $widget['id'] ?? '';
			$widget_slug  = $widget['slug'] ?? '';
			$widget_label = $widget['label'] ?? '';
			$widget_order = $widget['order'] ?? 0;

			// Render widget container.
			?>
			<div class="portal-widget sd-border sd-radius-12 sd-bg-content portal-widget-<?php echo esc_attr( $widget_slug ); ?>" data-widget-id="<?php echo esc_attr( $widget_id ); ?>" data-widget-order="<?php echo esc_attr( $widget_order ); ?>">
				<div class="portal-widget-header">
					<h4 class="portal-widget-title"><?php echo esc_html( $widget_label ); ?></h4>
				</div>
				<div class="portal-widget-content">
					<?php
					// Load the widget renderer.
					require_once SUREDASHBOARD_DIR . 'core/shortcodes/widgets/class-widget-renderer.php';

					// Render the widget.
					\SureDashboard\Core\Shortcodes\Widgets\Widget_Renderer::render(
						$widget_slug,
						$widget,
						$space_id
					);
					?>
				</div>
			</div>
			<?php
		}
		?>
	</div>
</aside>
