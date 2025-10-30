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
$user_register_tags            = $settings['user_register_tags'] ?? [];
?>

<div class="ghl-settings-wrapper">
	
	<?php wp_nonce_field( 'ghl_crm_settings_nonce', 'ghl_crm_nonce' ); ?>
	
	<!-- Auto Sync User Data Section -->
	<div class="ghl-settings-section ghl-settings-card">
		<h2><?php esc_html_e( 'Auto Sync User Data and Contact Data', 'ghl-crm-integration' ); ?></h2>
		<p><?php esc_html_e( 'Automatically Sync your WP User Data and GoHighLevel Contact Data', 'ghl-crm-integration' ); ?></p>
		<hr>
		
		<div class="ghl-form-builder">
			<form class="ghl-form" method="post">
				
				<div class="ghl-form-item">
					<div class="ghl-form-item-content">
						<label class="ghl-checkbox <?php echo $enable_user_sync ? 'is-checked' : ''; ?>">
							<input type="checkbox" 
								   class="ghl-checkbox-original"
								   id="enable_user_sync" 
								   name="enable_user_sync" 
								   value="1" 
								   <?php checked( $enable_user_sync ); ?>
								   >
							<span class="ghl-checkbox-input <?php echo $enable_user_sync ? 'is-checked' : ''; ?>">
								<span class="ghl-checkbox-inner"></span>
							</span>
							<span class="ghl-checkbox-label">
								<?php esc_html_e( 'Enable Sync between WP User Data and GoHighLevel Contact Data', 'ghl-crm-integration' ); ?>
							</span>
						</label>
					</div>
				</div>
				
				<div class="ghl-form-item">
					<div class="ghl-form-item-content">
						<label class="ghl-checkbox <?php echo $delete_contact_on_user_delete ? 'is-checked' : ''; ?>">
							<input type="checkbox" 
								   class="ghl-checkbox-original"
								   id="delete_contact_on_user_delete" 
								   name="delete_contact_on_user_delete" 
								   value="1" 
								   <?php checked( $delete_contact_on_user_delete ); ?>
								   >
							<span class="ghl-checkbox-input <?php echo $delete_contact_on_user_delete ? 'is-checked' : ''; ?>">
								<span class="ghl-checkbox-inner"></span>
							</span>
							<span class="ghl-checkbox-label">
								<?php esc_html_e( 'Delete GoHighLevel contact on WP User delete', 'ghl-crm-integration' ); ?>
							</span>
						</label>
					</div>
				</div>
			</form>
		</div>
	</div>

	<!-- User Signup Settings Section -->
	<div class="ghl-settings-section ghl-settings-card">
		<h2><?php esc_html_e( 'User Signup Settings', 'ghl-crm-integration' ); ?></h2>
		<p><?php esc_html_e( 'Automatically add your new user signups as contacts in GoHighLevel', 'ghl-crm-integration' ); ?></p>
		<hr>
		
		<div class="ghl-form-builder">
			<form class="ghl-form" method="post">
				
				<div class="ghl-form-item">
					<div class="ghl-form-item-content">
						<label class="ghl-checkbox <?php echo in_array( 'user_register', $user_sync_actions, true ) ? 'is-checked' : ''; ?>">
							<input type="checkbox" 
								   class="ghl-checkbox-original"
								   id="enable_user_register"
								   name="user_sync_actions[]" 
								   value="user_register"
								   <?php checked( in_array( 'user_register', $user_sync_actions, true ) ); ?>
								   >
							<span class="ghl-checkbox-input <?php echo in_array( 'user_register', $user_sync_actions, true ) ? 'is-checked' : ''; ?>">
								<span class="ghl-checkbox-inner"></span>
							</span>
							<span class="ghl-checkbox-label">
								<?php esc_html_e( 'Enable Create new contacts in GoHighLevel when users register in WordPress', 'ghl-crm-integration' ); ?>
							</span>
						</label>
					</div>
				</div>
				
				<!-- Conditional Tags Dropdown -->
				<div class="ghl-form-item" id="user_register_tags_section" style="margin-left: 30px; <?php echo ! in_array( 'user_register', $user_sync_actions, true ) ? 'display: none;' : ''; ?>">
					<div class="ghl-form-item-content">
						<label style="display: block; margin-bottom: 10px; font-weight: 600;">
							<?php esc_html_e( 'Default Tags on User Registration', 'ghl-crm-integration' ); ?>
						</label>
						<select 
							id="user_register_tags" 
							name="user_register_tags[]" 
							multiple 
							class="ghl-tags-select"
							style="width: 100%; max-width: 500px;"
							data-saved-tags='<?php echo wp_json_encode( $user_register_tags ); ?>'
							data-placeholder="<?php esc_attr_e( 'Select tags to apply when user registers...', 'ghl-crm-integration' ); ?>">
							<option value=""><?php esc_html_e( 'Loading tags...', 'ghl-crm-integration' ); ?></option>
						</select>
						<p class="description" style="margin-top: 8px;">
							<?php esc_html_e( 'These tags will be automatically added to contacts when users register in WordPress.', 'ghl-crm-integration' ); ?>
						</p>
					</div>
				</div>
				
			</form>
		</div>
	</div>

	<!-- Save Button -->
	<button type="button" id="save-general-settings" class="ghl-button ghl-button-success ghl-button-medium">
		<span class="ghl-button-text"><?php esc_html_e( 'Save Settings', 'ghl-crm-integration' ); ?></span>
	</button>
	
</div>
