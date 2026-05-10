<?php
/**
 * Settings - Personalization Template
 *
 * Personalization settings tab content
 *
 * @package    GHL_CRM_Integration
 * @subpackage GHL_CRM_Integration/templates/admin/partials/settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings_manager = \GHL_CRM\Core\SettingsManager::get_instance();
$settings         = $settings_manager->get_settings_array();
?>

<div class="ghl-settings-wrapper">
	<?php wp_nonce_field( 'ghl_crm_settings_nonce', 'ghl_crm_nonce' ); ?>

	<div class="ghl-settings-section ghl-settings-card">
		<div class="ghl-settings-header">
			<h2>
				<span class="dashicons dashicons-email-alt"></span>
				<?php esc_html_e( 'Email Campaign Personalization (?ghl_cid=)', 'ghl-crm-integration' ); ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'When GoHighLevel sends an email campaign, append {{contact.id}} to links so visitors arriving from those emails can see personalized content even without logging in. Use [ghl_user_meta] shortcodes normally; the plugin resolves them from the contact\'s GHL data.', 'ghl-crm-integration' ); ?>
			</p>
			<p class="description" style="margin-top: 6px;">
				<?php
				echo wp_kses(
					sprintf(
						__( '<strong>Simple personalization:</strong> <code>https://%s/page?ghl_cid={{contact.id}}</code>', 'ghl-crm-integration' ),
						esc_html( home_url() )
					),
					[
						'strong' => [],
						'code'   => [],
					]
				);
				?>
			</p>
		</div>

		<hr>

		<div class="ghl-form-builder">
			<form class="ghl-form" method="post">
				<table class="form-table" role="presentation">
					<tbody>

						<tr>
							<th scope="row">
								<label for="enable_ghl_cid">
									<?php esc_html_e( 'Enable ?ghl_cid= Parameter', 'ghl-crm-integration' ); ?>
									<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'When enabled, the plugin reads the ghl_cid query parameter from the URL and uses it to personalize [ghl_user_meta] shortcodes for non-logged-in visitors arriving from GHL email campaigns.', 'ghl-crm-integration' ); ?>">?</span>
								</label>
							</th>
							<td>
								<label class="ghl-checkbox ghl-advanced-checkbox-label <?php echo ! empty( $settings['enable_ghl_cid'] ) ? 'is-checked' : ''; ?>">
									<input
										type="checkbox"
										class="ghl-checkbox-original"
										id="enable_ghl_cid"
										name="enable_ghl_cid"
										value="1"
										<?php checked( ! empty( $settings['enable_ghl_cid'] ), true ); ?>
									>
									<span class="ghl-checkbox-input <?php echo ! empty( $settings['enable_ghl_cid'] ) ? 'is-checked' : ''; ?>">
										<span class="ghl-checkbox-inner"></span>
									</span>
									<span class="ghl-checkbox-label">
										<?php esc_html_e( 'Personalize pages for visitors from GHL email links', 'ghl-crm-integration' ); ?>
									</span>
								</label>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="ghl-cid-link-template">
									<?php esc_html_e( 'Copy Link Template', 'ghl-crm-integration' ); ?>
								</label>
							</th>
							<td>
								<input
									type="text"
									id="ghl-cid-link-template"
									readonly
									class="regular-text"
									value="<?php echo esc_url( home_url( '/page?ghl_cid={{contact.id}}' ) ); ?>"
								>
								<button type="button" class="ghl-button ghl-button-secondary" id="ghl-copy-cid-template" style="margin-left: 8px; vertical-align: middle;">
									<?php esc_html_e( 'Copy', 'ghl-crm-integration' ); ?>
								</button>
								<p class="description ghl-description-spacing">
										<?php esc_html_e( 'Copy this template and use it in your GHL email campaigns.', 'ghl-crm-integration' ); ?>
								</p>
							</td>
						</tr>

					</tbody>
				</table>
			</form>
		</div>

		<hr>

		<button type="button" class="ghl-button ghl-button-primary ghl-save-settings-btn">
			<span class="ghl-button-text"><?php esc_html_e( 'Save Personalization Settings', 'ghl-crm-integration' ); ?></span>
		</button>
	</div>
</div>

<script>
(function() {
	var copyButton = document.getElementById('ghl-copy-cid-template');
	var templateInput = document.getElementById('ghl-cid-link-template');

	if (!copyButton || !templateInput) {
		return;
	}

	copyButton.addEventListener('click', function() {
		templateInput.select();
		templateInput.setSelectionRange(0, 99999);

		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(templateInput.value);
		} else {
			document.execCommand('copy');
		}

		copyButton.textContent = 'Copied';
		setTimeout(function() {
			copyButton.textContent = 'Copy';
		}, 1500);
	});
})();
</script>
