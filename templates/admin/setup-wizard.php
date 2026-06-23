<?php
/**
 * Setup Wizard Template
 *
 * @package Syncly
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$settings_manager = \Syncly\Core\SettingsManager::get_instance();
$settings         = $settings_manager->get_settings_array();
$is_connected     = ! empty( $settings['oauth_access_token'] ) || ! empty( $settings['api_token'] );

// Check which plugins are available
$is_woocommerce_active = class_exists( 'WooCommerce' );
$is_buddyboss_active   = function_exists( 'bp_is_active' ) && bp_is_active( 'groups' );
$is_learndash_active   = defined( 'LEARNDASH_VERSION' );

// Check whether companion integration handlers are active.
$is_pro_version = (bool) apply_filters( 'syncly_is_pro_active', false );

// Determine which integrations to show
$show_woocommerce = $is_pro_version && $is_woocommerce_active;
// BuddyBoss: Available in free version if plugin is installed
$show_buddyboss = $is_buddyboss_active;
$show_learndash = $is_pro_version && $is_learndash_active;

// Count available integrations
$available_integrations = 0;
if ( $show_woocommerce ) {
	++$available_integrations;
}
if ( $show_buddyboss ) {
	++$available_integrations;
}
if ( $show_learndash ) {
	++$available_integrations;
}

// Get current settings for pre-population
$enable_user_sync              = $settings['enable_user_sync'] ?? false;
$user_sync_actions             = $settings['user_sync_actions'] ?? [];
$user_register_enabled         = in_array( 'user_register', $user_sync_actions, true );
$user_register_tags            = $settings['user_register_tags'] ?? [];
$wc_enabled                    = $settings['wc_enabled'] ?? false;
$buddyboss_enabled             = $settings['buddyboss_enabled'] ?? false;
$learndash_enabled             = $settings['learndash_enabled'] ?? false;
$delete_contact_on_user_delete = $settings['delete_contact_on_user_delete'] ?? false;
$enable_sync_logging           = $settings['enable_sync_logging'] ?? false;
$enable_role_tags              = ! empty( $settings['role_tags'] ) && is_array( $settings['role_tags'] );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php esc_html_e( 'GoHighLevel CRM Setup', 'syncly' ); ?></title>
		<?php // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WordPress admin hook. ?>
		<?php do_action( 'admin_print_styles' ); ?>
		<?php // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WordPress admin hook. ?>
		<?php do_action( 'admin_print_scripts' ); ?>
		<?php // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WordPress admin hook. ?>
		<?php do_action( 'admin_head' ); ?>
</head>
<body class="ghl-setup-wizard-body">
<div class="ghl-setup-wizard">
	<!-- Header -->
	<div class="ghl-setup-header">
		<div class="ghl-setup-logo">
			<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor">
				<path d="M12 2L2 7l10 5 10-5-10-5z"></path>
				<path d="M2 17l10 5 10-5M2 12l10 5 10-5"></path>
			</svg>
			<h1><?php esc_html_e( 'GoHighLevel CRM', 'syncly' ); ?></h1>
		</div>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=syncly-admin' ) ); ?>" class="ghl-setup-exit">
			<?php esc_html_e( 'Exit Setup', 'syncly' ); ?>
		</a>
	</div>
	<!-- Progress Steps -->
	<div class="ghl-setup-steps">
		<div class="ghl-setup-step active" data-step="1">
			<span class="ghl-step-number">1</span>
			<span class="ghl-step-label"><?php esc_html_e( 'Welcome', 'syncly' ); ?></span>
		</div>
		<div class="ghl-setup-step" data-step="2">
			<span class="ghl-step-number">2</span>
			<span class="ghl-step-label"><?php esc_html_e( 'Connect', 'syncly' ); ?></span>
		</div>
		<div class="ghl-setup-step" data-step="3">
			<span class="ghl-step-number">3</span>
			<span class="ghl-step-label"><?php esc_html_e( 'User Sync', 'syncly' ); ?></span>
		</div>
		<div class="ghl-setup-step" data-step="4">
			<span class="ghl-step-number">4</span>
			<span class="ghl-step-label"><?php esc_html_e( 'Integrations', 'syncly' ); ?></span>
		</div>
		<div class="ghl-setup-step" data-step="5">
			<span class="ghl-step-number">5</span>
			<span class="ghl-step-label"><?php esc_html_e( 'Advanced', 'syncly' ); ?></span>
		</div>
		<div class="ghl-setup-step" data-step="6">
			<span class="ghl-step-number">6</span>
			<span class="ghl-step-label"><?php esc_html_e( 'Complete', 'syncly' ); ?></span>
		</div>
	</div>
	<!-- Content Container -->
	<div class="ghl-setup-content">
		
		<!-- Step 1: Welcome -->
		<div class="ghl-wizard-panel active" data-step="1">
			<div class="ghl-wizard-panel-content">
				<div class="ghl-wizard-icon">
					<svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor">
						<path d="M12 2L2 7l10 5 10-5-10-5z"></path>
						<path d="M2 17l10 5 10-5M2 12l10 5 10-5"></path>
					</svg>
				</div>
				<h2><?php esc_html_e( 'Welcome to GoHighLevel CRM', 'syncly' ); ?></h2>
				<p class="ghl-wizard-description">
					<?php esc_html_e( 'Let\'s get your WordPress site connected to GoHighLevel in just a few simple steps. This should only take about 2 minutes.', 'syncly' ); ?>
				</p>
				
				<div class="ghl-wizard-features">
					<div class="ghl-feature-item">
						<span class="dashicons dashicons-yes-alt"></span>
						<span><?php esc_html_e( 'Sync WordPress users with GoHighLevel contacts', 'syncly' ); ?></span>
					</div>
					<div class="ghl-feature-item">
						<span class="dashicons dashicons-yes-alt"></span>
						<span><?php esc_html_e( 'Integrate with WooCommerce, LearnDash, and more', 'syncly' ); ?></span>
					</div>
					<div class="ghl-feature-item">
						<span class="dashicons dashicons-yes-alt"></span>
						<span><?php esc_html_e( 'Automate your marketing workflows', 'syncly' ); ?></span>
					</div>
				</div>
			</div>
			
			<div class="ghl-wizard-actions">
				<button class="ghl-button ghl-button-primary ghl-button-large ghl-wizard-next">
					<?php esc_html_e( 'Get Started', 'syncly' ); ?>
					<span class="dashicons dashicons-arrow-right-alt2"></span>
				</button>
			</div>
		</div>
		
		<!-- Step 2: Connection -->
		<div class="ghl-wizard-panel" data-step="2">
			<div class="ghl-wizard-panel-content">
				<?php if ( $is_connected ) : ?>
					<!-- Already Connected -->
					<h2><?php esc_html_e( 'Connection Status', 'syncly' ); ?></h2>
					<p class="ghl-wizard-description">
						<?php esc_html_e( 'Your site is successfully connected to GoHighLevel.', 'syncly' ); ?>
					</p>
					
					<div class="ghl-connection-status-card">
						<div class="ghl-connection-status-header">
							<div class="ghl-status-indicator">
								<span class="dashicons dashicons-yes-alt"></span>
								<div>
									<strong><?php esc_html_e( 'Connected', 'syncly' ); ?></strong>
									<span class="ghl-connection-type">
										<?php
										if ( ! empty( $settings['oauth_access_token'] ) ) {
											esc_html_e( 'OAuth Connection', 'syncly' );
										} else {
											esc_html_e( 'API Key Connection', 'syncly' );
										}
										?>
									</span>
								</div>
							</div>
							<?php if ( ! empty( $settings['location_id'] ) ) : ?>
								<div class="ghl-location-id">
									<small><?php esc_html_e( 'Location ID:', 'syncly' ); ?></small>
									<code><?php echo esc_html( $settings['location_id'] ); ?></code>
								</div>
							<?php endif; ?>
						</div>
						
						<!-- Collapsible Change Connection Section -->
						<div class="ghl-change-connection-wrapper">
							<button type="button" class="ghl-collapse-trigger" id="ghl-wizard-change-connection">
								<span class="dashicons dashicons-admin-network"></span>
								<?php esc_html_e( 'Change Connection', 'syncly' ); ?>
								<span class="dashicons dashicons-arrow-down-alt2 ghl-collapse-icon"></span>
							</button>
							
							<div class="ghl-collapse-content" id="ghl-wizard-connection-options" style="display: none;">
								<?php
								// Get OAuth handler
								$oauth_handler = new \Syncly\API\OAuth\OAuthHandler();

								// Include the connection setup template
								include plugin_dir_path( __FILE__ ) . 'connection-setup.php';
								?>
							</div>
						</div>
					</div>
				<?php else : ?>
					<!-- Not Connected -->
					<h2><?php esc_html_e( 'Connect to GoHighLevel', 'syncly' ); ?></h2>
					<p class="ghl-wizard-description">
						<?php esc_html_e( 'Choose your preferred connection method to get started.', 'syncly' ); ?>
					</p>
					
					<?php
					// Get OAuth handler
					$oauth_handler = new \Syncly\API\OAuth\OAuthHandler();

					// Include the connection setup template
					include plugin_dir_path( __FILE__ ) . 'connection-setup.php';
					?>
				<?php endif; ?>
			</div>
			
			<div class="ghl-wizard-actions">
				<button class="ghl-button ghl-button-secondary ghl-wizard-prev">
					<span class="dashicons dashicons-arrow-left-alt2"></span>
					<?php esc_html_e( 'Back', 'syncly' ); ?>
				</button>
				<?php if ( $is_connected ) : ?>
					<button class="ghl-button ghl-button-primary ghl-wizard-next">
						<?php esc_html_e( 'Continue', 'syncly' ); ?>
						<span class="dashicons dashicons-arrow-right-alt2"></span>
					</button>
				<?php else : ?>
					<p class="description" style="margin: 0; color: #6b7280;">
						<?php esc_html_e( 'Connect to GoHighLevel to continue', 'syncly' ); ?>
					</p>
				<?php endif; ?>
			</div>
		</div>
		
		<!-- Step 3: User Sync -->
		<div class="ghl-wizard-panel" data-step="3">
			<div class="ghl-wizard-panel-content">
				<h2><?php esc_html_e( 'WordPress User Sync', 'syncly' ); ?></h2>
				<p class="ghl-wizard-description">
					<?php esc_html_e( 'Automatically sync your WordPress users with GoHighLevel contacts to keep your data in sync.', 'syncly' ); ?>
				</p>
				
				<div class="ghl-settings-group">
					<label class="ghl-setting-row">
						<div class="ghl-setting-info">
							<strong><?php esc_html_e( 'Enable User Sync', 'syncly' ); ?></strong>
							<span class="ghl-setting-desc"><?php esc_html_e( 'Sync user profile changes to GoHighLevel automatically', 'syncly' ); ?></span>
						</div>
						<div class="ghl-toggle-switch">
							<input type="checkbox" id="wizard_enable_user_sync" <?php checked( $enable_user_sync ); ?>>
							<span class="ghl-toggle-slider"></span>
						</div>
					</label>
					
					<label class="ghl-setting-row">
						<div class="ghl-setting-info">
							<strong><?php esc_html_e( 'Sync New Registrations', 'syncly' ); ?></strong>
							<span class="ghl-setting-desc"><?php esc_html_e( 'Create GoHighLevel contacts when users register', 'syncly' ); ?></span>
						</div>
						<div class="ghl-toggle-switch">
							<input type="checkbox" id="wizard_user_register" <?php checked( $user_register_enabled ); ?>>
							<span class="ghl-toggle-slider"></span>
						</div>
					</label>
					
					<div class="ghl-setting-row ghl-setting-row--full ghl-setting-row--nested" id="wizard_user_register_tags_section" <?php echo ! $user_register_enabled ? 'style="display: none;"' : ''; ?>>
						<div class="ghl-setting-info">
							<strong><?php esc_html_e( 'Default Tags on Registration', 'syncly' ); ?></strong>
							<span class="ghl-setting-desc"><?php esc_html_e( 'Automatically apply these tags when new users register (optional)', 'syncly' ); ?></span>
						</div>
						<div class="ghl-tags-input-wrapper">
							<select 
								id="wizard_user_register_tags" 
								name="wizard_user_register_tags[]" 
								multiple 
								class="ghl-tags-select"
								data-saved-tags='<?php echo esc_attr( wp_json_encode( $user_register_tags ) ); ?>'
								data-placeholder="<?php esc_attr_e( 'Select tags to apply when user registers...', 'syncly' ); ?>">
								<option value=""><?php esc_html_e( 'Loading tags...', 'syncly' ); ?></option>
							</select>
							<p class="ghl-help-text"><?php esc_html_e( 'These tags will be automatically added to contacts when users register in WordPress.', 'syncly' ); ?></p>
						</div>
					</div>
				</div>
			</div>
			
			<div class="ghl-wizard-actions">
				<button class="ghl-button ghl-button-secondary ghl-wizard-prev">
					<span class="dashicons dashicons-arrow-left-alt2"></span>
					<?php esc_html_e( 'Back', 'syncly' ); ?>
				</button>
				<button class="ghl-button ghl-button-primary ghl-wizard-next">
					<?php esc_html_e( 'Continue', 'syncly' ); ?>
					<span class="dashicons dashicons-arrow-right-alt2"></span>
				</button>
			</div>
		</div>
		<!-- Step 4: Integrations -->
		<div class="ghl-wizard-panel" data-step="4">
			<div class="ghl-wizard-panel-content">
				<h2><?php esc_html_e( 'Enable Integrations', 'syncly' ); ?></h2>
				<p class="ghl-wizard-description">
					<?php esc_html_e( 'Select which plugins you want to integrate with GoHighLevel.', 'syncly' ); ?>
				</p>
				
				<?php if ( $available_integrations > 0 ) : ?>
					<div class="ghl-integrations-list">
						<?php if ( $show_woocommerce ) : ?>
							<label class="ghl-integration-item <?php echo ! $is_woocommerce_active ? 'ghl-integration-item--disabled' : ''; ?>">
								<input 
									type="checkbox" 
									id="wizard_woocommerce" 
									<?php checked( $wc_enabled ); ?>
									<?php disabled( ! $is_woocommerce_active ); ?>
								>
								<div class="ghl-integration-content">
									<div class="ghl-integration-icon">
										<span class="dashicons dashicons-cart"></span>
									</div>
									<div class="ghl-integration-info">
										<strong>
											<?php esc_html_e( 'WooCommerce', 'syncly' ); ?>
										</strong>
										<span>
											<?php
											if ( ! $is_woocommerce_active ) {
												esc_html_e( 'WooCommerce not installed', 'syncly' );
											} else {
												esc_html_e( 'Sync orders and customer data', 'syncly' );
											}
											?>
										</span>
									</div>
								</div>
							</label>
						<?php endif; ?>
						
						<?php if ( $show_buddyboss ) : ?>
							<label class="ghl-integration-item <?php echo ! $is_buddyboss_active ? 'ghl-integration-item--disabled' : ''; ?>">
								<input 
									type="checkbox" 
									id="wizard_buddyboss" 
									<?php checked( $buddyboss_enabled ); ?>
									<?php disabled( ! $is_buddyboss_active ); ?>
								>
								<div class="ghl-integration-content">
									<div class="ghl-integration-icon">
										<span class="dashicons dashicons-groups"></span>
									</div>
									<div class="ghl-integration-info">
										<strong><?php esc_html_e( 'BuddyBoss', 'syncly' ); ?></strong>
										<span>
											<?php
											if ( ! $is_buddyboss_active ) {
												esc_html_e( 'BuddyBoss not installed', 'syncly' );
											} else {
												esc_html_e( 'Sync community members and activity', 'syncly' );
											}
											?>
										</span>
									</div>
								</div>
							</label>
						<?php endif; ?>
						
						<?php if ( $show_learndash ) : ?>
							<label class="ghl-integration-item <?php echo ! $is_learndash_active ? 'ghl-integration-item--disabled' : ''; ?>">
								<input 
									type="checkbox" 
									id="wizard_learndash" 
									<?php checked( $learndash_enabled ); ?>
									<?php disabled( ! $is_learndash_active ); ?>
								>
								<div class="ghl-integration-content">
									<div class="ghl-integration-icon">
										<span class="dashicons dashicons-welcome-learn-more"></span>
									</div>
									<div class="ghl-integration-info">
										<strong>
											<?php esc_html_e( 'LearnDash', 'syncly' ); ?>
										</strong>
										<span>
											<?php
											if ( ! $is_learndash_active ) {
												esc_html_e( 'LearnDash not installed', 'syncly' );
											} else {
												esc_html_e( 'Sync course enrollments and progress', 'syncly' );
											}
											?>
										</span>
									</div>
								</div>
							</label>
						<?php endif; ?>
					</div>
				<?php else : ?>
					<!-- No integrations available -->
					<div class="ghl-no-integrations-message">
						<div class="ghl-empty-state">
							<span class="dashicons dashicons-admin-plugins"></span>
							<h3><?php esc_html_e( 'No Integration Plugins Found', 'syncly' ); ?></h3>
							<p><?php esc_html_e( 'Install BuddyBoss to enable the available community integration.', 'syncly' ); ?></p>
						</div>
					</div>
				<?php endif; ?>
			</div>
			
			<div class="ghl-wizard-actions">
				<button class="ghl-button ghl-button-secondary ghl-wizard-prev">
					<span class="dashicons dashicons-arrow-left-alt2"></span>
					<?php esc_html_e( 'Back', 'syncly' ); ?>
				</button>
				<button class="ghl-button ghl-button-primary ghl-wizard-next">
					<?php esc_html_e( 'Continue', 'syncly' ); ?>
					<span class="dashicons dashicons-arrow-right-alt2"></span>
				</button>
			</div>
		</div>
		<!-- Step 5: Advanced Settings -->
		<div class="ghl-wizard-panel" data-step="5">
			<div class="ghl-wizard-panel-content">
				<h2><?php esc_html_e( 'Advanced Settings', 'syncly' ); ?></h2>
				<p class="ghl-wizard-description">
					<?php esc_html_e( 'Configure advanced options to fine-tune your integration.', 'syncly' ); ?>
				</p>
				
				<div class="ghl-settings-group">
					<label class="ghl-setting-row">
						<div class="ghl-setting-info">
							<strong><?php esc_html_e( 'Delete Contact on User Delete', 'syncly' ); ?></strong>
							<span class="ghl-setting-desc"><?php esc_html_e( 'Remove contacts from GoHighLevel when WordPress users are deleted', 'syncly' ); ?></span>
						</div>
						<div class="ghl-toggle-switch">
							<input type="checkbox" id="wizard_delete_contact_on_user_delete" <?php checked( $delete_contact_on_user_delete ); ?>>
							<span class="ghl-toggle-slider"></span>
						</div>
					</label>
					
					<label class="ghl-setting-row">
						<div class="ghl-setting-info">
							<strong><?php esc_html_e( 'Enable Sync Logging', 'syncly' ); ?></strong>
							<span class="ghl-setting-desc"><?php esc_html_e( 'Track all sync activities for debugging and monitoring', 'syncly' ); ?></span>
						</div>
						<div class="ghl-toggle-switch">
							<input type="checkbox" id="wizard_enable_sync_logging" <?php checked( $enable_sync_logging ); ?>>
							<span class="ghl-toggle-slider"></span>
						</div>
					</label>
					
					<label class="ghl-setting-row">
						<div class="ghl-setting-info">
							<strong><?php esc_html_e( 'Enable Role-Based Tags', 'syncly' ); ?></strong>
							<span class="ghl-setting-desc"><?php esc_html_e( 'Automatically tag contacts based on WordPress user roles', 'syncly' ); ?></span>
						</div>
						<div class="ghl-toggle-switch">
							<input type="checkbox" id="wizard_enable_role_tags" <?php checked( $enable_role_tags ); ?>>
							<span class="ghl-toggle-slider"></span>
						</div>
					</label>
				</div>
				
				<div class="ghl-wizard-note">
					<span class="dashicons dashicons-info"></span>
					<div>
						<strong><?php esc_html_e( 'Note:', 'syncly' ); ?></strong>
						<p><?php esc_html_e( 'You can configure additional advanced settings like field mapping, webhooks, and custom objects from the main settings page after completing this wizard.', 'syncly' ); ?></p>
					</div>
				</div>
			</div>
			
			<div class="ghl-wizard-actions">
				<button class="ghl-button ghl-button-secondary ghl-wizard-prev">
					<span class="dashicons dashicons-arrow-left-alt2"></span>
					<?php esc_html_e( 'Back', 'syncly' ); ?>
				</button>
				<button class="ghl-button ghl-button-primary ghl-wizard-next">
					<?php esc_html_e( 'Continue', 'syncly' ); ?>
					<span class="dashicons dashicons-arrow-right-alt2"></span>
				</button>
			</div>
		</div>
		<!-- Step 6: Complete -->
		<div class="ghl-wizard-panel" data-step="6">
			<div class="ghl-wizard-panel-content">
				<div class="ghl-wizard-icon ghl-wizard-success">
					<svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor">
						<circle cx="12" cy="12" r="10"></circle>
						<path d="M9 12l2 2 4-4"></path>
					</svg>
				</div>
				<h2><?php esc_html_e( 'All Set!', 'syncly' ); ?></h2>
				<p class="ghl-wizard-description">
					<?php esc_html_e( 'Your GoHighLevel CRM integration is configured and ready to use.', 'syncly' ); ?>
				</p>
				
				<div class="ghl-next-steps">
					<h3><?php esc_html_e( 'What\'s Next?', 'syncly' ); ?></h3>
					<ul>
						<li>
							<span class="dashicons dashicons-arrow-right-alt"></span>
							<?php esc_html_e( 'Configure field mapping to customize data sync', 'syncly' ); ?>
						</li>
						<li>
							<span class="dashicons dashicons-arrow-right-alt"></span>
							<?php esc_html_e( 'Set up webhooks for real-time updates', 'syncly' ); ?>
						</li>
						<li>
							<span class="dashicons dashicons-arrow-right-alt"></span>
							<?php esc_html_e( 'Explore advanced sync options and automations', 'syncly' ); ?>
						</li>
					</ul>
				</div>
			</div>
			
			<div class="ghl-wizard-actions">
				<button class="ghl-button ghl-button-primary ghl-button-large ghl-wizard-finish">
					<?php esc_html_e( 'Go to Dashboard', 'syncly' ); ?>
					<span class="dashicons dashicons-arrow-right-alt2"></span>
				</button>
			</div>
		</div>
	</div>
</div>
<?php // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WordPress admin hook. ?>
<?php do_action( 'admin_footer' ); ?>
<?php // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WordPress admin hook. ?>
<?php do_action( 'admin_print_footer_scripts' ); ?>
</body>
</html>