<?php
/**
 * LearnDash Integration Template
 *
 * @package    GHL_CRM_Integration
 * @subpackage Templates/Admin/Partials/Integrations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Check if LearnDash is active
$is_learndash_active = defined( 'LEARNDASH_VERSION' );
$settings_manager    = \GHL_CRM\Core\SettingsManager::get_instance();
$settings            = $settings_manager->get_settings_array();

// Get LearnDash settings
$ld_enabled        = $settings['learndash_enabled'] ?? false;
$ld_enrolled_tags  = $settings['learndash_enrolled_tags'] ?? [];
$ld_completed_tags = $settings['learndash_completed_tags'] ?? [];
$ld_revoked_tags   = $settings['learndash_revoked_tags'] ?? [];

// Get connection status
$oauth_handler = new \GHL_CRM\API\OAuth\OAuthHandler();
$oauth_status  = $oauth_handler->get_connection_status();
$is_connected  = $oauth_status['connected'] || ! empty( $settings['api_token'] );
?>

<?php
// Show scope checker warnings at the top if connected
if ( $is_connected && $is_learndash_active ) :
	\GHL_CRM\Core\ScopeChecker::render_scope_notice( 'contacts' );
	\GHL_CRM\Core\ScopeChecker::render_scope_notice( 'tags' );
endif;
?>

<?php if ( ! $is_learndash_active ) : ?>
	<!-- LearnDash Not Active -->
	<div class="ghl-empty-state-card">
		<div class="ghl-empty-state">
			<div class="ghl-empty-state-icon">
				<span class="dashicons dashicons-welcome-learn-more" style="color: #9ca3af;"></span>
			</div>
			<h3><?php esc_html_e( 'LearnDash Not Detected', 'ghl-crm-integration' ); ?></h3>
			<p><?php esc_html_e( 'LearnDash must be installed and activated to use this integration.', 'ghl-crm-integration' ); ?></p>
			
			<div class="ghl-empty-state-actions">
				<button type="button" onclick="window.open('https://www.learndash.com/', '_blank')" class="ghl-button ghl-button-primary">
					<?php esc_html_e( 'Learn More', 'ghl-crm-integration' ); ?>
				</button>
			</div>
		</div>
	</div>
<?php else : ?>
	<!-- LearnDash Active -->
	<div class="ghl-settings-section ghl-settings-card">
		<div class="ghl-settings-header" style="display: flex; align-items: center; justify-content: space-between; padding: 24px; gap: 20px;">
			<div style="display: flex; align-items: center; gap: 16px; flex: 1;">
				<div class="ghl-integration-icon" style="
					background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
					width: 56px;
					height: 56px;
					border-radius: 12px;
					display: flex;
					align-items: center;
					justify-content: center;
					box-shadow: 0 4px 12px rgba(14, 165, 233, 0.2);
					flex-shrink: 0;
				">
					<span class="dashicons dashicons-welcome-learn-more" style="font-size: 28px; color: #fff;"></span>
				</div>
				<div style="flex: 1;">
					<h2 style="margin: 0 0 6px 0; font-size: 20px; font-weight: 600; color: #1e293b; line-height: 1.2;">
						<?php esc_html_e( 'LearnDash Integration', 'ghl-crm-integration' ); ?>
					</h2>
					<p class="description" style="margin: 0; font-size: 14px; color: #64748b; line-height: 1.5;">
						<?php esc_html_e( 'Sync course, lesson, topic, and quiz completions with GoHighLevel', 'ghl-crm-integration' ); ?>
					</p>
				</div>
			</div>
			<div style="display: flex; align-items: center; gap: 12px; flex-shrink: 0;">
				<label class="ghl-checkbox <?php echo $ld_enabled ? 'is-checked' : ''; ?>" style="margin: 0; display: flex; align-items: center; gap: 8px;">
					<input 
						type="checkbox" 
						class="ghl-checkbox-original"
						id="learndash_enabled" 
						name="learndash_enabled" 
						value="1"
						<?php checked( $ld_enabled, true ); ?>
					>
					<span class="ghl-checkbox-input <?php echo $ld_enabled ? 'is-checked' : ''; ?>">
						<span class="ghl-checkbox-inner"></span>
					</span>
					<span class="ghl-checkbox-label" style="font-weight: 500; color: #475569;">
						<?php echo $ld_enabled ? esc_html__( 'Enabled', 'ghl-crm-integration' ) : esc_html__( 'Disabled', 'ghl-crm-integration' ); ?>
					</span>
				</label>
			</div>
		</div>

		<div class="ghl-settings-body" id="learndash-settings-body" style="<?php echo ! $ld_enabled ? 'display: none;' : ''; ?>"  data-integration-section="learndash">
			<p>
				<?php
				printf(
					/* translators: %s: LearnDash version */
					esc_html__( 'LearnDash %s detected. Configure your integration settings below.', 'ghl-crm-integration' ),
					defined( 'LEARNDASH_VERSION' ) ? esc_html( LEARNDASH_VERSION ) : ''
				);
				?>
			</p>
			<hr>

			<!-- Per-Content Configuration Info -->
			<div class="ghl-info-banner" style="background: #eff6ff; border-left: 3px solid #3b82f6; padding: 16px; margin-bottom: 24px; border-radius: 4px;">
				<p style="margin: 0; color: #1e40af; font-size: 14px; line-height: 1.6;">
					<strong><?php esc_html_e( 'Per-Content Configuration:', 'ghl-crm-integration' ); ?></strong>
					<?php esc_html_e( 'Tag assignments are configured individually for each piece of LearnDash content. Edit any course, lesson, topic, or quiz to access the GoHighLevel completion tags settings in the content editor.', 'ghl-crm-integration' ); ?>
				</p>
			</div>
		</div>
	</div>
<?php endif; ?>
