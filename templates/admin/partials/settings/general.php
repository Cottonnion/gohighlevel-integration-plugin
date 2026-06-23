<?php
/**
 * Settings - General Template
 *
 * General settings tab content
 *
 * @package    Syncly
 * @subpackage Syncly/templates/admin/partials/settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings_manager = \Syncly\Core\SettingsManager::get_instance();
$settings         = $settings_manager->get_settings_array();

// White label domain setting
$ghl_white_label_domain = $settings['ghl_white_label_domain'] ?? '';

// WordPress User sync settings
$enable_user_sync              = $settings['enable_user_sync'] ?? false;
$user_sync_actions             = $settings['user_sync_actions'] ?? [];
$delete_contact_on_user_delete = $settings['delete_contact_on_user_delete'] ?? false;
$user_register_tags            = $settings_manager->get_location_register_tags();
?>

<div class="ghl-settings-wrapper">
	<?php wp_nonce_field( 'syncly_settings_nonce', 'syncly_nonce' ); ?>
	
	<!-- White Label Domain Section -->
	<div class="ghl-settings-section ghl-settings-card">
		<div class="ghl-settings-header">
			<h2>
				<span class="dashicons dashicons-admin-site"></span>
				<?php esc_html_e( 'White Label Domain', 'syncly' ); ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Configure your custom GoHighLevel white label domain if you are using one', 'syncly' ); ?>
			</p>
		</div>
		
		<hr>
		
		<div class="ghl-form-builder">
			<form class="ghl-form" method="post">
				<div class="ghl-form-item">
					<div class="ghl-form-item-content ghl-form-item-content--column">
						<label for="ghl_white_label_domain" class="ghl-form-label">
							<?php esc_html_e( 'White Label Domain URL', 'syncly' ); ?>
							<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'If you have a custom branded domain in GoHighLevel (like app.yourbusiness.com instead of app.gohighlevel.com), enter it here. This ensures links to GHL records use your branded URL. Leave empty if you use the standard GoHighLevel domain.', 'syncly' ); ?>">?</span>
						</label>
						<input 
							type="text" 
							id="ghl_white_label_domain" 
							name="ghl_white_label_domain" 
							class="ghl-input ghl-input--wide" 
							value="<?php echo esc_attr( $ghl_white_label_domain ); ?>"
							placeholder="https://app.yourdomain.com"
						>
						<p class="description ghl-form-description">
							<?php esc_html_e( 'Enter your custom white label domain if you have one (e.g., https://app.yourdomain.com). Leave empty to use the default GoHighLevel domain (app.gohighlevel.com). This will be used for links to GHL records.', 'syncly' ); ?>
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
				<?php esc_html_e( 'Auto Sync User Data and Contact Data', 'syncly' ); ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Automatically Sync your WP User Data and GoHighLevel Contact Data', 'syncly' ); ?>
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
								<?php esc_html_e( 'Enable Sync between WP User Data and GoHighLevel Contact Data', 'syncly' ); ?>
								<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'When enabled, WordPress user profile changes (name, email, custom fields) automatically sync to their GoHighLevel contact record. This keeps data consistent across both platforms.', 'syncly' ); ?>">?</span>
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
								<?php esc_html_e( 'Delete GoHighLevel contact on WP User delete', 'syncly' ); ?>
								<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Warning: When a WordPress user is deleted, their GoHighLevel contact will also be permanently deleted. Disable this if you want to keep GHL contacts even after removing WordPress users (recommended for preserving customer history).', 'syncly' ); ?>">?</span>
							</span>
						</label>
					</div>
				</div>
			</form>
		</div>
	</div>

	<!-- User Signup Settings Section -->
	<div class="ghl-settings-section ghl-settings-card">
		<h2><?php esc_html_e( 'User Signup Settings', 'syncly' ); ?></h2>
		<p><?php esc_html_e( 'Automatically add your new user signups as contacts in GoHighLevel', 'syncly' ); ?></p>
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
								<?php esc_html_e( 'Enable Create new contacts in GoHighLevel when users register in WordPress', 'syncly' ); ?>
							</span>
						</label>
					</div>
				</div>
				
				<!-- Conditional Tags Dropdown -->
				<div class="ghl-form-item ghl-form-item--nested" id="user_register_tags_section" <?php echo ! in_array( 'user_register', $user_sync_actions, true ) ? 'style="display: none;"' : ''; ?>>
					<div class="ghl-form-item-content ghl-form-item-content--column">
						<label for="user_register_tags" class="ghl-form-label">
							<?php esc_html_e( 'Default Tags on User Registration', 'syncly' ); ?>
							<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'These tags are automatically applied to contacts in GoHighLevel when someone creates a new account on your WordPress site. Use these tags to trigger welcome emails, onboarding workflows, or segment new users in GHL.', 'syncly' ); ?>">?</span>
						</label>
						<select 
							id="user_register_tags" 
							name="user_register_tags[]" 
							multiple 
							class="ghl-tags-select"
							data-saved-tags='<?php echo esc_attr( wp_json_encode( $user_register_tags ) ); ?>'
							data-placeholder="<?php esc_attr_e( 'Select tags to apply when user registers...', 'syncly' ); ?>">
							<option value=""><?php esc_html_e( 'Loading tags...', 'syncly' ); ?></option>
						</select>
						<p class="description ghl-form-description">
							<?php esc_html_e( 'These tags will be automatically added to contacts when users register in WordPress.', 'syncly' ); ?>
						</p>
					</div>
				</div>
				
			</form>
		</div>
	</div>

		<!-- Setup Wizard Section -->
	<div class="ghl-settings-section ghl-settings-card" style="margin-bottom:32px;">
		<div class="ghl-settings-header">
			<h2>
				<span class="dashicons dashicons-welcome-learn-more"></span>
				<?php esc_html_e( 'Setup Wizard', 'syncly' ); ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Quickly configure the most important plugin settings with the guided setup wizard.', 'syncly' ); ?>
			</p>
		</div>
		<hr>
		<div style="padding: 12px 0;">
			<button type="button" class="ghl-button ghl-button-secondary" onclick="window.location.href='admin.php?page=syncly-setup-wizard'">
				<span class="dashicons dashicons-welcome-learn-more"></span>
				<?php esc_html_e( 'Launch Setup Wizard', 'syncly' ); ?>
			</button>
		</div>
	</div>

	<!-- Save Button -->
	<button type="button" id="save-general-settings" class="ghl-button ghl-button-primary ghl-save-settings-btn">
		<span class="ghl-button-text"><?php esc_html_e( 'Save General Settings', 'syncly' ); ?></span>
	</button>

	<!-- Help Section -->
	<div class="ghl-help-box ghl-help-box--spaced">
		<h3>
			<span class="dashicons dashicons-info"></span>
			<?php esc_html_e( 'How User Sync Works', 'syncly' ); ?>
		</h3>
		<div class="ghl-help-content">
			<ol>
				<li>
					<strong><?php esc_html_e( 'Enable User Sync:', 'syncly' ); ?></strong>
					<?php esc_html_e( 'Turn on the master toggle to activate automatic synchronization between WordPress users and GoHighLevel contacts.', 'syncly' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Choose Sync Events:', 'syncly' ); ?></strong>
					<?php esc_html_e( 'Select which WordPress events trigger contact creation in GoHighLevel (e.g., user registration, profile updates).', 'syncly' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Configure Default Tags:', 'syncly' ); ?></strong>
					<?php esc_html_e( 'When "Create on Registration" is enabled, you can assign default tags to automatically tag new contacts in GoHighLevel.', 'syncly' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Field Mapping:', 'syncly' ); ?></strong>
					<?php esc_html_e( 'Visit the Field Mapping tab to configure which WordPress user fields sync to which GoHighLevel contact fields.', 'syncly' ); ?>
				</li>
			</ol>
			
			<p><strong><?php esc_html_e( 'Note:', 'syncly' ); ?></strong>
				<?php esc_html_e( 'User data is synced in real-time when enabled. Ensure your GoHighLevel connection is active before enabling sync.', 'syncly' ); ?>
			</p>
		</div>
	</div>
	
</div>