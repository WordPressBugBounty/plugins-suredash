<?php
/**
 * Shared modal shell for SureMembers locked-item actions (message / page-post).
 *
 * @package SureDash\Templates
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="portal-locked-modal" id="sd-locked-modal" role="dialog" aria-modal="true" aria-labelledby="sd-locked-modal-title" hidden>
	<div class="portal-locked-modal-overlay" data-sd-modal-close="1"></div>
	<div class="portal-locked-modal-dialog" role="document">
		<button type="button" class="portal-locked-modal-close portal-button button-ghost" aria-label="<?php esc_attr_e( 'Close', 'suredash' ); ?>" data-sd-modal-close="1">
			<?php \SureDashboard\Inc\Utils\Helper::get_library_icon( 'X', true ); ?>
		</button>
		<h2 class="portal-locked-modal-title" id="sd-locked-modal-title"></h2>
		<div class="portal-locked-modal-body"></div>
		<div class="portal-locked-modal-actions"></div>
	</div>
</div>
