<?php
/**
 * Email HTML Wrapper Template
 *
 * Wraps email content fragments in a complete HTML document so email clients
 * that require DOCTYPE / <html> / <body> render the message correctly.
 *
 * Override: copy to your-theme/suredash/email-templates/wrapper.php
 *
 * Available variables:
 *   $email_body_content — The rendered inner template HTML.
 *
 * @package SureDash
 * @since 1.8.1
 */

defined( 'ABSPATH' ) || exit;

$site_name = get_bloginfo( 'name' );
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( $site_name ); ?></title>
</head>
<body style="margin:0; padding:0; background-color:#f9fafb; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif; color:#1f2937; line-height:1.6; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%;">
	<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f9fafb;">
		<tr>
			<td align="center" style="padding:24px 16px;">
				<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px; width:100%; background-color:#ffffff; border-radius:8px; border:1px solid #e5e7eb;">
					<tr>
						<td style="padding:32px 24px;">
							<?php echo $email_body_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already escaped in inner templates. ?>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>
