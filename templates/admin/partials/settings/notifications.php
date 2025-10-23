<?php
/**
 * Settings - Notifications Template
 *
 * Notification settings tab content
 *
 * @package    GHL_CRM_Integration
 * @subpackage GHL_CRM_Integration/templates/admin/partials/settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings_manager = \GHL_CRM\Core\SettingsManager::get_instance();
$settings = $settings_manager->get_settings_array();
?>

<div class="ghl-settings-section">
	<h2><?php esc_html_e( 'Notification Settings', 'ghl-crm-integration' ); ?></h2>
	
	<form id="ghl-notification-settings-form" method="post">
		<?php wp_nonce_field( 'ghl_crm_notification_settings', 'ghl_notification_nonce' ); ?>
		
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="admin_email">
							<?php esc_html_e( 'Admin Email', 'ghl-crm-integration' ); ?>
						</label>
					</th>
					<td>
						<input type="email" 
							   id="admin_email" 
							   name="admin_email" 
							   value="<?php echo esc_attr( $settings['admin_email'] ?? get_option( 'admin_email' ) ); ?>" 
							   class="regular-text">
						<p class="description">
							<?php esc_html_e( 'Email address for sync notifications and error alerts.', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<?php esc_html_e( 'Email Notifications', 'ghl-crm-integration' ); ?>
					</th>
					<td>
						<fieldset>
							<label for="notify_sync_complete">
								<input type="checkbox" 
									   id="notify_sync_complete" 
									   name="notify_sync_complete" 
									   value="1" 
									   <?php checked( $settings['notify_sync_complete'] ?? false ); ?>>
								<?php esc_html_e( 'Notify when sync completes', 'ghl-crm-integration' ); ?>
							</label>
							<br>
							<label for="notify_sync_errors">
								<input type="checkbox" 
									   id="notify_sync_errors" 
									   name="notify_sync_errors" 
									   value="1" 
									   <?php checked( $settings['notify_sync_errors'] ?? true ); ?>>
								<?php esc_html_e( 'Notify on sync errors', 'ghl-crm-integration' ); ?>
							</label>
							<br>
							<label for="notify_connection_lost">
								<input type="checkbox" 
									   id="notify_connection_lost" 
									   name="notify_connection_lost" 
									   value="1" 
									   <?php checked( $settings['notify_connection_lost'] ?? true ); ?>>
								<?php esc_html_e( 'Notify when connection is lost', 'ghl-crm-integration' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
			</tbody>
		</table>
		
		<p class="submit">
			<button type="submit" class="button button-primary">
				<?php esc_html_e( 'Save Notification Settings', 'ghl-crm-integration' ); ?>
			</button>
		</p>
	</form>
</div>
