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

// White label domain setting
$ghl_white_label_domain = $settings['ghl_white_label_domain'] ?? '';

// WordPress User sync settings
$enable_user_sync              = $settings['enable_user_sync'] ?? false;
$user_sync_actions             = $settings['user_sync_actions'] ?? [];
$delete_contact_on_user_delete = $settings['delete_contact_on_user_delete'] ?? false;
$user_register_tags            = $settings['user_register_tags'] ?? [];
?>

<div class="ghl-settings-wrapper">
	<?php wp_nonce_field( 'ghl_crm_settings_nonce', 'ghl_crm_nonce' ); ?>
	
	<!-- White Label Domain Section -->
	<div class="ghl-settings-section ghl-settings-card">
		<div class="ghl-settings-header">
			<h2>
				<span class="dashicons dashicons-admin-site"></span>
				<?php esc_html_e( 'White Label Domain', 'ghl-crm-integration' ); ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Configure your custom GoHighLevel white label domain if you are using one', 'ghl-crm-integration' ); ?>
			</p>
		</div>
		
		<hr>
		
		<div class="ghl-form-builder">
			<form class="ghl-form" method="post">
				<div class="ghl-form-item">
					<div class="ghl-form-item-content">
						<label for="ghl_white_label_domain" style="display: block; margin-bottom: 10px; font-weight: 600;">
							<?php esc_html_e( 'White Label Domain URL', 'ghl-crm-integration' ); ?>
							<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'If you have a custom branded domain in GoHighLevel (like app.yourbusiness.com instead of app.gohighlevel.com), enter it here. This ensures links to GHL records use your branded URL. Leave empty if you use the standard GoHighLevel domain.', 'ghl-crm-integration' ); ?>">?</span>
						</label>
						<input 
							type="text" 
							id="ghl_white_label_domain" 
							name="ghl_white_label_domain" 
							class="ghl-input" 
							value="<?php echo esc_attr( $ghl_white_label_domain ); ?>"
							placeholder="https://app.yourdomain.com"
							style="width: 100%; max-width: 500px;"
						>
						<p class="description" style="margin-top: 8px;">
							<?php esc_html_e( 'Enter your custom white label domain if you have one (e.g., https://app.yourdomain.com). Leave empty to use the default GoHighLevel domain (app.gohighlevel.com). This will be used for links to GHL records.', 'ghl-crm-integration' ); ?>
						</p>
					</div>
				</div>
			</form>
		</div>
	</div>
	
	<!-- Auto Sync User Data Section -->
	<div class="ghl-settings-section ghl-settings-card">
		<div class="ghl-settings-header">
			<h2>
				<span class="dashicons dashicons-update"></span>
				<?php esc_html_e( 'Auto Sync User Data and Contact Data', 'ghl-crm-integration' ); ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Automatically Sync your WP User Data and GoHighLevel Contact Data', 'ghl-crm-integration' ); ?>
			</p>
		</div>
		
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
								<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'When enabled, WordPress user profile changes (name, email, custom fields) automatically sync to their GoHighLevel contact record. This keeps data consistent across both platforms.', 'ghl-crm-integration' ); ?>">?</span>
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
								<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Warning: When a WordPress user is deleted, their GoHighLevel contact will also be permanently deleted. Disable this if you want to keep GHL contacts even after removing WordPress users (recommended for preserving customer history).', 'ghl-crm-integration' ); ?>">?</span>
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
							<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'These tags are automatically applied to contacts in GoHighLevel when someone creates a new account on your WordPress site. Use these tags to trigger welcome emails, onboarding workflows, or segment new users in GHL.', 'ghl-crm-integration' ); ?>">?</span>
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
	<button type="button" id="save-general-settings" class="ghl-button ghl-button-primary ghl-save-settings-btn">
		<span class="ghl-button-text"><?php esc_html_e( 'Save General Settings', 'ghl-crm-integration' ); ?></span>
	</button>

	<!-- Help Section -->
	<div class="ghl-help-box" style="margin-top: 30px;">
		<h3>
			<span class="dashicons dashicons-info"></span>
			<?php esc_html_e( 'How User Sync Works', 'ghl-crm-integration' ); ?>
		</h3>
		<div class="ghl-help-content">
			<ol>
				<li>
					<strong><?php esc_html_e( 'Enable User Sync:', 'ghl-crm-integration' ); ?></strong>
					<?php esc_html_e( 'Turn on the master toggle to activate automatic synchronization between WordPress users and GoHighLevel contacts.', 'ghl-crm-integration' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Choose Sync Events:', 'ghl-crm-integration' ); ?></strong>
					<?php esc_html_e( 'Select which WordPress events trigger contact creation in GoHighLevel (e.g., user registration, profile updates).', 'ghl-crm-integration' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Configure Default Tags:', 'ghl-crm-integration' ); ?></strong>
					<?php esc_html_e( 'When "Create on Registration" is enabled, you can assign default tags to automatically tag new contacts in GoHighLevel.', 'ghl-crm-integration' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Field Mapping:', 'ghl-crm-integration' ); ?></strong>
					<?php esc_html_e( 'Visit the Field Mapping tab to configure which WordPress user fields sync to which GoHighLevel contact fields.', 'ghl-crm-integration' ); ?>
				</li>
			</ol>
			
			<p><strong><?php esc_html_e( 'Note:', 'ghl-crm-integration' ); ?></strong>
				<?php esc_html_e( 'User data is synced in real-time when enabled. Ensure your GoHighLevel connection is active before enabling sync.', 'ghl-crm-integration' ); ?>
			</p>
		</div>
	</div>
	
</div>
