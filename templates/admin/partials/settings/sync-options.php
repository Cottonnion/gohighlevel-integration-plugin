<?php
/**
 * Settings - Sync Options Template
 *
 * Sync options tab content for note synchronization and performance settings
 *
 * @package    GHL_CRM_Integration
 * @subpackage GHL_CRM_Integration/templates/admin/partials/settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings_manager = \GHL_CRM\Core\SettingsManager::get_instance();
$settings         = $settings_manager->get_settings_array();

// Note sync settings
$note_sync_direction = $settings['note_sync_direction'] ?? 'to_ghl';
?>

<div class="ghl-settings-wrapper">
	<?php wp_nonce_field( 'ghl_crm_settings_nonce', 'ghl_crm_nonce' ); ?>
	
	<!-- Note Synchronization Section -->
	<div class="ghl-settings-section ghl-settings-card">
		<div class="ghl-settings-header">
			<h2>
				<span class="dashicons dashicons-edit"></span>
				<?php esc_html_e( 'Note Synchronization', 'ghl-crm-integration' ); ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Automatically sync notes and comments between WordPress and GoHighLevel contact notes', 'ghl-crm-integration' ); ?>
			</p>
		</div>
		
		<hr>
		
		<div class="ghl-form-builder">
			<form class="ghl-form" method="post">
				
				<div class="ghl-form-item">
					<div class="ghl-form-item-content ghl-form-item-content--column">
						<label for="note_sync_direction" class="ghl-form-label">
							<?php esc_html_e( 'Sync Direction', 'ghl-crm-integration' ); ?>
							<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Choose how notes should sync between WordPress and GoHighLevel. Select the direction that matches your workflow.', 'ghl-crm-integration' ); ?>">?</span>
						</label>
						<select 
							id="note_sync_direction" 
							name="note_sync_direction" 
							class="ghl-select"
						>
							<option value="to_ghl" <?php selected( $note_sync_direction, 'to_ghl' ); ?>>
								<?php esc_html_e( '→ To GoHighLevel Only', 'ghl-crm-integration' ); ?>
							</option>
							<option value="from_ghl" <?php selected( $note_sync_direction, 'from_ghl' ); ?>>
								<?php esc_html_e( '← From GoHighLevel Only', 'ghl-crm-integration' ); ?>
							</option>
							<option value="both" <?php selected( $note_sync_direction, 'both' ); ?>>
								<?php esc_html_e( '↔ Both Ways', 'ghl-crm-integration' ); ?>
							</option>
							<option value="disabled" <?php selected( $note_sync_direction, 'disabled' ); ?>>
								<?php esc_html_e( 'Disabled', 'ghl-crm-integration' ); ?>
							</option>
						</select>
						<p class="description ghl-form-description">
							<?php esc_html_e( 'Choose how notes should sync between platforms. Notes will be stored in user meta (ghl_crm_contact_notes) and displayed in user profiles. Set to Disabled to stop all note synchronization.', 'ghl-crm-integration' ); ?>
						</p>
					</div>
				</div>

			</form>
		</div>
	</div>

	<!-- Save Button -->
	<button type="button" id="save-sync-options" class="ghl-button ghl-button-primary ghl-save-settings-btn">
		<span class="ghl-button-text"><?php esc_html_e( 'Save Sync Options', 'ghl-crm-integration' ); ?></span>
	</button>

</div>