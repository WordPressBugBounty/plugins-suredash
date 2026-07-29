<?php
/**
 * The template for restricted content area view.
 *
 * @see     https://developer.wordpress.org/themes/basics/template-hierarchy/
 * @package SureDash\Templates
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

use SureDashboard\Inc\Utils\Helper;
use SureDashboard\Inc\Utils\Labels;

if ( empty( $args ) ) {
	return;
}

$icon          = $args['icon'] ?? '';
$label         = $args['label'] ?? '';
$description   = $args['description'] ?? '';
$extra_content = $args['extra_content'] ?? '';

?>

<div class="portal-restricted-content portal-content sd-shadow">
	<?php Helper::get_library_icon( $icon, true, 'lg' ); ?>
	<h2> <?php Labels::get_label( $label, true ); ?> </h2>
	<div class="sd-flex-col sd-gap-8">
		<?php if ( ! empty( $description ) ) { ?>
			<p class="portal-restricted-content-notice"> <?php Labels::get_label( $description, true ); ?> </p>
		<?php } ?>
		<?php if ( ! empty( $extra_content ) ) { ?>
			<div class="sd-no-space"> <?php echo wp_kses_post( $extra_content ); ?> </div>
		<?php } ?>
	</div>
</div>

<?php
