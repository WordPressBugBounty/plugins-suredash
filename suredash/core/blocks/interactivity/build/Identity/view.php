<?php
/**
 * PHP file to use when rendering the block type on the server to show on the front end.
 *
 * The following variables are exposed to the file:
 *     $attributes (array): The block attributes.
 *     $content (string): The block default content.
 *     $block (WP_Block): The block instance.
 *
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 * @package suredash
 * @since 0.0.6
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SureDashboard\Inc\Utils\Helper;

$endpoint = suredash_content_post(); // Hiding identity block on endpoint.
if ( $endpoint ) {
	return;
}

$attributes      = $attributes ?? [];
$elements        = $attributes['elements'] ?? 'both';
$resp_aside_menu = boolval( $attributes['responsivesidenavigation'] ) ? true : false;
$portal_name     = Helper::get_option( 'portal_name' );
$logo_url        = $attributes['logo'] ?? '';
$logo_dark_url   = $attributes['logoDark'] ?? '';
$logo_url        = ! empty( $logo_url ) ? sprintf( '<img src="%s" alt="%s" class="portal-logo portal-logo-light sd-border-none sd-outline-none">', esc_url( $logo_url ), esc_attr( $portal_name ) ) : '';
$logo_dark_url   = ! empty( $logo_dark_url ) ? sprintf( '<img src="%s" alt="%s" class="portal-logo portal-logo-dark sd-border-none sd-outline-none" style="display:none;">', esc_url( $logo_dark_url ), esc_attr( $portal_name ) ) : '';
$home_link       = Helper::get_option( 'portal_as_homepage' ) ? home_url() : '/' . suredash_get_community_slug() . '/';
$home_link       = esc_url( apply_filters( 'suredash_identity_block_url', $home_link ) );
$stacked_class   = boolval( $attributes['stacked'] ) ? 'portal-logo-title-stacked' : '';

// Generate unique ID for this block instance to avoid CSS conflicts.
$unique_id = 'suredash-identity-' . wp_generate_uuid4();

if ( $resp_aside_menu ) {
	echo do_shortcode( '[portal_responsive_navigation]' );
}

?>
	<div
	<?php
	echo do_shortcode(
		get_block_wrapper_attributes(
			[
				'class' => 'portal-branding-section portal-content sd-flex sd-gap-8 sd-items-center sd-justify-between',
				'id'    => $unique_id,
			]
		)
	);
	?>
	>
		<?php
			printf(
				'<style class="suredash-identity-block-css">
					#%s .portal-site-identity img {
						max-width:%s !important;
					}
					body.dark-mode #%s .portal-logo-light {
						display: none !important;
					}
					body.dark-mode #%s .portal-logo-dark {
						display: block !important;
					}
				</style>',
				esc_attr( $unique_id ),
				esc_attr( $attributes['width'] ?? '120px' ),
				esc_attr( $unique_id ),
				esc_attr( $unique_id )
			);
			?>

		<h2 class="portal-banner-heading sd-no-space">
			<a href="<?php echo esc_url( $home_link ); ?>" class="portal-site-identity <?php echo esc_attr( $stacked_class ); ?>">
				<?php
				switch ( $elements ) {
					case 'logo':
						echo do_shortcode( $logo_url );
						echo do_shortcode( $logo_dark_url );
						break;
					case 'title':
						echo '<span class="sd-flex sd-flex-wrap sd-wrap">' . esc_html( $portal_name ) . '</span>';
						break;
					default:
						echo do_shortcode( $logo_url );
						echo do_shortcode( $logo_dark_url );
						echo '<span class="sd-flex sd-flex-wrap sd-wrap">' . esc_html( $portal_name ) . '</span>';
						break;
				}
				?>
			</a>
		</h2>
	</div>
<?php
