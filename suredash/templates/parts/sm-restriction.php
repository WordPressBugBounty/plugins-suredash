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

if ( empty( $args ) ) {
	return;
}

$icon            = $args['icon'] ?? '';
$heading         = $args['heading'] ?? '';
$preview_content = $args['preview_content'] ?? '';
$preview_button  = $args['preview_button'] ?? '';
$redirect_url    = $args['redirect_url'] ?? '';
$preview_content = $args['preview_content'] ?? '';
$enable_login    = $args['enable_login'] ?? false;

?>

<div class="portal-restricted-content portal-content sd-shadow">
	<?php Helper::get_library_icon( $icon, true, 'lg' ); ?>
	<h2> <?php echo esc_html( $heading ); ?> </h2>
	<div class="sd-flex-col sd-gap-8">
		<p class="portal-restricted-content-notice sd-no-space"> <?php echo esc_html( $preview_content ); ?> </p>
		<?php
		if ( ( $preview_button && $redirect_url ) || $enable_login ) {
			?>
					<div class="sd-flex sd-justify-center sd-mt-16">
					<?php if ( $preview_button && $redirect_url ) { ?>
						<a href="<?php echo esc_url( $redirect_url ); ?>" class="portal-button button-primary" aria-heading="<?php echo esc_attr( $preview_button ); ?>"><?php echo esc_html( $preview_button ); ?></a>
					<?php } ?>
					<?php
					if ( $enable_login && is_callable( [ '\SureMembers\Inc\Restricted', 'get_login_cta' ] ) ) {
						echo do_shortcode( \SureMembers\Inc\Restricted::get_login_cta( 'portal-button button-secondary' ) ); // @phpstan-ignore-line
					}
					?>
					</div>
				<?php
		}
		?>
	</div>
</div>

<?php
