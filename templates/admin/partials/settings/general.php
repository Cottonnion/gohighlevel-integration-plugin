<?php
/**
 * Settings - General Template
 *
 * General settings tab content
 *
 * @package    GHL_CRM_Integration
 * @subpackage GHL_CRM_Integration/templates/admin/partials/settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings_manager = \GHL_CRM\Core\SettingsManager::get_instance();
$settings = $settings_manager->get_settings_array();

// WordPress User sync settings
$enable_user_sync              = $settings['enable_user_sync'] ?? false;
$user_sync_actions             = $settings['user_sync_actions'] ?? [];
$delete_contact_on_user_delete = $settings['delete_contact_on_user_delete'] ?? false;
?>

<div class="ghl-settings-section">
	<h2><?php esc_html_e( 'General Settings', 'ghl-crm-integration' ); ?></h2>
	
	<form id="ghl-general-settings-form" method="post">
		<?php wp_nonce_field( 'ghl_crm_general_settings', 'ghl_general_nonce' ); ?>
		
		<!-- WordPress Users Sync Section -->
		<h3><?php esc_html_e( 'WordPress Users Synchronization', 'ghl-crm-integration' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'Configure how WordPress users sync with GoHighLevel contacts.', 'ghl-crm-integration' ); ?>
		</p>
		
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="enable_user_sync">
							<?php esc_html_e( 'User Sync', 'ghl-crm-integration' ); ?>
						</label>
					</th>
					<td>
						<label for="enable_user_sync">
							<input type="checkbox" 
								   id="enable_user_sync" 
								   name="enable_user_sync" 
								   value="1" 
								   <?php checked( $enable_user_sync ); ?>>
							<?php esc_html_e( 'Enable WordPress Users ↔ GoHighLevel Contacts sync', 'ghl-crm-integration' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Automatically sync WordPress users with GoHighLevel contacts when enabled.', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<?php esc_html_e( 'Sync Triggers', 'ghl-crm-integration' ); ?>
					</th>
					<td>
						<fieldset>
							<legend class="screen-reader-text">
								<span><?php esc_html_e( 'Choose which WordPress user events should trigger synchronization', 'ghl-crm-integration' ); ?></span>
							</legend>
							<p class="description" style="margin-bottom: 12px;">
								<?php esc_html_e( 'Choose which WordPress user events should automatically trigger synchronization with GoHighLevel.', 'ghl-crm-integration' ); ?>
							</p>
							
							<label>
								<input type="checkbox" 
									   name="user_sync_actions[]" 
									   value="user_register"
									   <?php checked( in_array( 'user_register', $user_sync_actions, true ) ); ?>>
								<strong><?php esc_html_e( 'User Registration', 'ghl-crm-integration' ); ?></strong><br>
								<span class="description"><?php esc_html_e( 'Create a new contact in GoHighLevel when someone registers on your WordPress site.', 'ghl-crm-integration' ); ?></span>
							</label><br><br>
							
							<label>
								<input type="checkbox" 
									   name="user_sync_actions[]" 
									   value="profile_update"
									   <?php checked( in_array( 'profile_update', $user_sync_actions, true ) ); ?>>
								<strong><?php esc_html_e( 'Profile Update', 'ghl-crm-integration' ); ?></strong><br>
								<span class="description"><?php esc_html_e( 'Update contact information when users modify their WordPress profile.', 'ghl-crm-integration' ); ?></span>
							</label><br><br>
							
							<label>
								<input type="checkbox" 
									   name="user_sync_actions[]" 
									   value="delete_user"
									   <?php checked( in_array( 'delete_user', $user_sync_actions, true ) ); ?>>
								<strong><?php esc_html_e( 'User Deletion', 'ghl-crm-integration' ); ?></strong><br>
								<span class="description"><?php esc_html_e( 'Manage the GoHighLevel contact when a WordPress user account is deleted.', 'ghl-crm-integration' ); ?></span>
							</label><br><br>
							
							<label>
								<input type="checkbox" 
									   name="user_sync_actions[]" 
									   value="set_user_role"
									   <?php checked( in_array( 'set_user_role', $user_sync_actions, true ) ); ?>>
								<strong><?php esc_html_e( 'Role Change', 'ghl-crm-integration' ); ?></strong><br>
								<span class="description"><?php esc_html_e( 'Update contact tags when a user\'s role changes.', 'ghl-crm-integration' ); ?></span>
							</label><br><br>
							
							<label>
								<input type="checkbox" 
									   name="user_sync_actions[]" 
									   value="user_login"
									   <?php checked( in_array( 'user_login', $user_sync_actions, true ) ); ?>>
								<strong><?php esc_html_e( 'User Login', 'ghl-crm-integration' ); ?></strong><br>
								<span class="description"><?php esc_html_e( 'Add a timestamped note to contact record when users log in.', 'ghl-crm-integration' ); ?></span>
							</label>
						</fieldset>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label for="delete_contact_on_user_delete">
							<?php esc_html_e( 'Deletion Behavior', 'ghl-crm-integration' ); ?>
						</label>
					</th>
					<td>
						<label for="delete_contact_on_user_delete">
							<input type="checkbox" 
								   id="delete_contact_on_user_delete" 
								   name="delete_contact_on_user_delete" 
								   value="1" 
								   <?php checked( $delete_contact_on_user_delete ); ?>>
							<?php esc_html_e( 'Permanently delete GoHighLevel contact when WordPress user is deleted', 'ghl-crm-integration' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'If unchecked, the contact will be preserved and tagged as "wp-user-deleted" instead of being permanently removed.', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>
		
		<div style="background: #f0f6fc; border: 1px solid #c3dcf3; border-radius: 4px; padding: 12px; margin: 16px 0;">
			<p style="margin: 0;">
				<strong><?php esc_html_e( 'Need to sync custom fields?', 'ghl-crm-integration' ); ?></strong><br>
				<?php esc_html_e( 'Visit the Field Mapping page to map additional WordPress user meta fields to custom fields in GoHighLevel.', 'ghl-crm-integration' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ghl-crm-admin#contact-fields' ) ); ?>" style="margin-left: 8px;">
					<?php esc_html_e( 'Configure Field Mapping', 'ghl-crm-integration' ); ?>
				</a>
			</p>
		</div>
		
		<table class="form-table" role="presentation" style="display: none;">
			<tbody>
		
		<p class="submit">
			<button type="submit" class="button button-primary">
				<?php esc_html_e( 'Save General Settings', 'ghl-crm-integration' ); ?>
			</button>
		</p>
	</form>
</div>
