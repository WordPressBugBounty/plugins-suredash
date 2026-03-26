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
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SureDashboard\Inc\Utils\Helper;

$attributes = $attributes ?? [];

// Extract attributes with defaults.
$system_mode     = $attributes['systemPreferenceMode'] ?? false;
$default_palette = Helper::get_option( 'default_palette' );
$switcher_type   = $attributes['switcherType'] ?? 'icon_text';
$default_mode    = $system_mode ? 'system' : $default_palette;
$force_reload    = $attributes['forceReload'] ?? false;
$light_icon      = $attributes['lightIcon'] ?? 'sun';
$dark_icon       = $attributes['darkIcon'] ?? 'moon';
$icon_size       = $attributes['iconSize'] ?? 'md';
$light_text      = $attributes['lightText'] ?? __( 'Light', 'suredash' );
$dark_text       = $attributes['darkText'] ?? __( 'Dark', 'suredash' );

// Only proceed if active_palette is specifically 'light' or 'dark'.
if ( $default_palette !== 'light' && $default_palette !== 'dark' ) {
	if ( is_user_logged_in() ) {
		?>
			<span class="sd-font-14 sd-bg-warning sd-p-10"><?php echo esc_html__( 'Global palette missing — Select Light/Dark Mode.', 'suredash' ); ?> </span>
		<?php
	}
	return;
}

// Determine current state.
$is_dark_mode = ( $default_palette === 'dark' );

// Get cookie state if exists (for client-side persistence).
$cookie_state = isset( $_COOKIE['suredashColorSwitcherState'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['suredashColorSwitcherState'] ) ) : null;
if ( $cookie_state !== null ) {
	$is_dark_mode = ( $cookie_state === 'true' );
}

// Map icon names to match lucide icons in the Helper function.
$icon_mapping = [
	'sun'          => 'Sun',
	'moon'         => 'Moon',
	'lightbulb'    => 'Lightbulb',
	'lightbulboff' => 'LightbulbOff',
	'sunmoon'      => 'SunMoon',
	'sunrise'      => 'Sunrise',
	'sunset'       => 'Sunset',
];

$mapped_light_icon = $icon_mapping[ $light_icon ] ?? 'Sun';
$mapped_dark_icon  = $icon_mapping[ $dark_icon ] ?? 'Moon';

// Aria label for accessibility.
$aria_label_text = $is_dark_mode
	? sprintf( /* translators: %s: light mode text */ __( 'Switch to %s', 'suredash' ), $light_text )
	: sprintf( /* translators: %s: dark mode text */ __( 'Switch to %s', 'suredash' ), $dark_text );

// Build wrapper classes.
$wrapper_classes = [
	'suredash-color-switcher-wrapper',
	'switcher-type-' . $switcher_type,
	'icon-size-' . $icon_size,
];

if ( $is_dark_mode ) {
	$wrapper_classes[] = 'is-dark-mode';
}

// Data attributes for JavaScript.
$data_attributes = [
	'data-default-mode'  => $default_mode,
	'data-force-reload'  => $force_reload ? 'true' : 'false',
	'data-light-icon'    => $light_icon,
	'data-dark-icon'     => $dark_icon,
	'data-light-text'    => $light_text,
	'data-dark-text'     => $dark_text,
	'data-switcher-type' => $switcher_type,
];

$data_attr_string = '';
foreach ( $data_attributes as $key => $value ) {
	$data_attr_string .= ' ' . $key . '="' . esc_attr( $value ) . '"';
}

?>

<div class="<?php echo esc_attr( implode( ' ', $wrapper_classes ) ); ?>" <?php echo do_shortcode( sanitize_text_field( $data_attr_string ) ); ?>>
	<div class="suredash-color-switcher switcher-type-<?php echo esc_attr( $switcher_type ); ?>">
		<button
			aria-label="<?php echo esc_attr( $aria_label_text ); ?>"
			type="button"
			<?php echo do_shortcode( get_block_wrapper_attributes( [ 'class' => 'switcher-button portal-button button-primary' ] ) ); ?>
		>
			<?php if ( $switcher_type === 'icon' || $switcher_type === 'icon_text' ) { ?>
				<span class="icon-light <?php echo $is_dark_mode ? 'sd-display-none' : 'sd-inline-flex'; ?>">
					<?php Helper::get_library_icon( $mapped_light_icon, true, $icon_size ); ?>
				</span>
				<span class="icon-dark <?php echo $is_dark_mode ? 'sd-inline-flex' : 'sd-display-none'; ?>">
					<?php Helper::get_library_icon( $mapped_dark_icon, true, $icon_size ); ?>
				</span>
			<?php } ?>

			<?php if ( ( $switcher_type === 'text' || $switcher_type === 'icon_text' ) ) { ?>
				<span class="switcher-text text-light <?php echo $is_dark_mode ? 'sd-inline-flex' : 'sd-display-none'; ?>">
					<?php echo esc_html( $light_text ); ?>
				</span>
				<span class="switcher-text text-dark <?php echo $is_dark_mode ? 'sd-display-none' : 'sd-inline-flex'; ?>">
					<?php echo esc_html( $dark_text ); ?>
				</span>
			<?php } ?>
		</button>
	</div>
</div>
