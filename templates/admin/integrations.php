<?php
/**
 * Integrations Settings Template
 *
 * @package    GHL_CRM_Integration
 * @subpackage Templates/Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get current settings
$settings_manager = \GHL_CRM\Core\SettingsManager::get_instance();
$settings         = $settings_manager->get_settings_array();

// Check connection status
$oauth_handler = new \GHL_CRM\API\OAuth\OAuthHandler();
$oauth_status  = $oauth_handler->get_connection_status();
$is_connected  = $oauth_status['connected'] || ! empty( $settings['api_token'] );
?>

<div class="wrap ghl-crm-wrap">
	<h1 class="ghl-page-title">
		<?php echo esc_html( get_admin_page_title() ); ?>
	</h1>

	<?php if ( ! $is_connected ) : ?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'Not Connected', 'ghl-crm-integration' ); ?></strong><br>
				<?php
				printf(
					/* translators: %s: Link to dashboard page */
					esc_html__( 'Please connect to GoHighLevel in %s first.', 'ghl-crm-integration' ),
					sprintf(
						'<a href="%s">%s</a>',
						esc_url( admin_url( 'admin.php?page=ghl-crm-admin' ) ),
						esc_html__( 'Dashboard', 'ghl-crm-integration' )
					)
				);
				?>
			</p>
		</div>
		<?php return; ?>
	<?php endif; ?>

	<?php
	// Check scope access for integrations
	\GHL_CRM\Core\ScopeChecker::render_scope_notice( 'contacts' );
	\GHL_CRM\Core\ScopeChecker::render_scope_notice( 'tags' );
	?>

	<p class="ghl-page-description">
		<?php esc_html_e( 'Configure third-party integrations with WooCommerce, BuddyBoss, and LearnDash. Basic WordPress user sync is managed in General Settings.', 'ghl-crm-integration' ); ?>
	</p>

	<!-- Success/Error Messages -->
	<div id="ghl-integrations-messages"></div>

	<!-- Tabs Navigation -->
	<div class="ghl-tabs-nav">
		<button class="ghl-tab-button active" data-tab="woocommerce" disabled>
			<span class="dashicons dashicons-cart"></span>
			<?php esc_html_e( 'WooCommerce', 'ghl-crm-integration' ); ?>
			<span class="ghl-badge ghl-badge-secondary"><?php esc_html_e( 'Soon', 'ghl-crm-integration' ); ?></span>
		</button>
		<button class="ghl-tab-button" data-tab="buddyboss">
			<span class="dashicons dashicons-groups"></span>
			<?php esc_html_e( 'BuddyBoss', 'ghl-crm-integration' ); ?>
		</button>
		<button class="ghl-tab-button" data-tab="learndash" disabled>
			<span class="dashicons dashicons-welcome-learn-more"></span>
			<?php esc_html_e( 'LearnDash', 'ghl-crm-integration' ); ?>
			<span class="ghl-badge ghl-badge-secondary"><?php esc_html_e( 'Soon', 'ghl-crm-integration' ); ?></span>
		</button>
	</div>

	<!-- Tabs Content -->
	<div class="ghl-tabs-content">
		<!-- Tab: WooCommerce (Coming Soon) -->
		<div class="ghl-tab-panel active" data-tab="woocommerce">
			<div class="ghl-card ghl-coming-soon-card">
				<div class="ghl-coming-soon-content">
					<div class="ghl-coming-soon-icon">
						<span class="dashicons dashicons-cart"></span>
					</div>
					<h2><?php esc_html_e( 'WooCommerce Integration', 'ghl-crm-integration' ); ?></h2>
					<p><?php esc_html_e( 'Sync WooCommerce orders, customers, and products with GoHighLevel.', 'ghl-crm-integration' ); ?></p>
					<span class="ghl-badge ghl-badge-large ghl-badge-secondary"><?php esc_html_e( 'Coming Soon', 'ghl-crm-integration' ); ?></span>
				</div>
			</div>
		</div>

		<!-- Tab: BuddyBoss -->
		<div class="ghl-tab-panel" data-tab="buddyboss">
			<div class="ghl-card">
				<div class="ghl-card-header">
					<div class="ghl-card-header-left">
						<div class="ghl-integration-icon">
							<span class="dashicons dashicons-groups"></span>
						</div>
						<div>
							<h2><?php esc_html_e( 'BuddyBoss Groups ↔ GoHighLevel Companies', 'ghl-crm-integration' ); ?></h2>
							<p><?php esc_html_e( 'Create BuddyBoss groups from GoHighLevel companies and sync member data', 'ghl-crm-integration' ); ?></p>
						</div>
					</div>
					<div class="ghl-card-header-right">
						<label class="ghl-toggle-switch">
							<input 
								type="checkbox" 
								id="enable_buddyboss_sync" 
								name="enable_buddyboss_sync"
								disabled
							>
							<span class="ghl-toggle-slider"></span>
						</label>
						<span class="ghl-toggle-label">
							<?php esc_html_e( 'Coming Soon', 'ghl-crm-integration' ); ?>
						</span>
					</div>
				</div>

				<div class="ghl-card-body">
					<div class="ghl-settings-section">
						<h3><?php esc_html_e( 'Test Tab Switching', 'ghl-crm-integration' ); ?></h3>
						<p class="description">
							<?php esc_html_e( 'This tab is enabled for testing purposes. The actual BuddyBoss integration features are coming soon.', 'ghl-crm-integration' ); ?>
						</p>

						<div class="ghl-info-box" style="background: #f0fdf4; border-color: #86efac;">
							<span class="dashicons dashicons-yes-alt" style="color: #22c55e;"></span>
							<div>
								<strong style="color: #166534;"><?php esc_html_e( 'Tab Navigation Working!', 'ghl-crm-integration' ); ?></strong>
								<p style="color: #166534; margin: 4px 0 0 0;">
									<?php esc_html_e( 'You can switch between tabs and see the content change. This confirms the tab system is functioning correctly.', 'ghl-crm-integration' ); ?>
								</p>
							</div>
						</div>
					</div>

					<div class="ghl-settings-section">
						<h3><?php esc_html_e( 'Planned Features', 'ghl-crm-integration' ); ?></h3>
						<ul style="list-style: disc; padding-left: 20px; color: var(--ghl-text-secondary);">
							<li><?php esc_html_e( 'Create BuddyBoss groups from GHL companies', 'ghl-crm-integration' ); ?></li>
							<li><?php esc_html_e( 'Sync group members with company contacts', 'ghl-crm-integration' ); ?></li>
							<li><?php esc_html_e( 'Map custom fields between platforms', 'ghl-crm-integration' ); ?></li>
							<li><?php esc_html_e( 'Automatic group creation on company events', 'ghl-crm-integration' ); ?></li>
						</ul>
					</div>

					<div class="ghl-settings-section">
						<div class="ghl-info-box">
							<span class="dashicons dashicons-info"></span>
							<div>
								<strong><?php esc_html_e( 'Development in Progress', 'ghl-crm-integration' ); ?></strong>
								<p><?php esc_html_e( 'BuddyBoss integration will be added in a future update. Focus is currently on WordPress Users sync.', 'ghl-crm-integration' ); ?></p>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Tab: LearnDash (Coming Soon) -->
		<div class="ghl-tab-panel" data-tab="learndash">
			<div class="ghl-card ghl-coming-soon-card">
				<div class="ghl-coming-soon-content">
					<div class="ghl-coming-soon-icon">
						<span class="dashicons dashicons-welcome-learn-more"></span>
					</div>
					<h2><?php esc_html_e( 'LearnDash Integration', 'ghl-crm-integration' ); ?></h2>
					<p><?php esc_html_e( 'Sync course enrollments, progress, and completions with GoHighLevel.', 'ghl-crm-integration' ); ?></p>
					<span class="ghl-badge ghl-badge-large ghl-badge-secondary"><?php esc_html_e( 'Coming Soon', 'ghl-crm-integration' ); ?></span>
				</div>
			</div>
		</div>
	</div>

	<!-- Save Button -->
	<div class="ghl-form-actions">
		<button type="button" id="save-integrations-settings" class="button button-primary button-large">
			<span class="button-text"><?php esc_html_e( 'Save Integration Settings', 'ghl-crm-integration' ); ?></span>
			<span class="spinner"></span>
		</button>
	</div>
</div>