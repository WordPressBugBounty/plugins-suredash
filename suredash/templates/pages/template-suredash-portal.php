<?php
/**
 * SureDash Portal Template
 *
 * @package SureDash
 */

defined( 'ABSPATH' ) || exit;

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
	<head>
		<meta charset="<?php bloginfo( 'charset' ); ?>" />
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<?php do_action( 'suredash_enqueue_scripts' ); ?>
		<?php wp_head(); ?>
	</head>

	<body <?php body_class(); ?>>
		<?php
			wp_body_open();
			$template_part = get_block_template( 'suredash/suredash//portal', 'wp_template_part' );
			$content       = suredash_get_the_block_template_html( $template_part->content ?? '' );
			echo $content; // phpcs:ignore WordPress.Security.EscapeOutput
			wp_footer();
		?>
	</body>
</html>
