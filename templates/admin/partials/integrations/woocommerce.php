<?php
/**
 * WooCommerce Integration Template
 *
 * @package    GHL_CRM_Integration
 * @subpackage Templates/Admin/Partials/Integrations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Check if WooCommerce is active
$is_woocommerce_active = class_exists( 'WooCommerce' );
$settings_manager      = \GHL_CRM\Core\SettingsManager::get_instance();
$settings              = $settings_manager->get_settings_array();

// Get WooCommerce settings
$wc_enabled                = $settings['wc_enabled'] ?? false;
$wc_abandoned_cart_enabled = $settings['wc_abandoned_cart_enabled'] ?? false;
$wc_abandoned_cart_time    = $settings['wc_abandoned_cart_time'] ?? 60;
$wc_abandoned_cart_tag     = $settings['wc_abandoned_cart_tag'] ?? '';
$wc_convert_lead_enabled   = $settings['wc_convert_lead_enabled'] ?? false;
$wc_customer_tag           = $settings['wc_customer_tag'] ?? '';
?>

<?php if ( ! $is_woocommerce_active ) : ?>
	<!-- WooCommerce Not Active -->
	<div class="ghl-empty-state-card">
		<div class="ghl-empty-state">
			<div class="ghl-empty-state-icon">
				<span class="dashicons dashicons-cart" style="color: #9ca3af;"></span>
			</div>
			<h3><?php esc_html_e( 'WooCommerce Not Detected', 'ghl-crm-integration' ); ?></h3>
			<p><?php esc_html_e( 'WooCommerce must be installed and activated to use this integration.', 'ghl-crm-integration' ); ?></p>
			
			<div class="ghl-empty-state-actions">
				<?php if ( current_user_can( 'install_plugins' ) ) : ?>
					<button type="button" onclick="window.location.href='<?php echo esc_url( admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' ) ); ?>'" class="ghl-button ghl-button-primary">
						<?php esc_html_e( 'Install WooCommerce', 'ghl-crm-integration' ); ?>
					</button>
				<?php endif; ?>
				<button type="button" onclick="window.open('https://woocommerce.com/', '_blank')" class="ghl-button ghl-button-secondary">
					<?php esc_html_e( 'Learn More', 'ghl-crm-integration' ); ?>
				</button>
			</div>
		</div>
	</div>
<?php else : ?>
	<!-- WooCommerce Active -->
	<div class="ghl-settings-section ghl-settings-card">
		<div class="ghl-settings-header" style="display: flex; align-items: center; justify-content: space-between; padding: 24px; gap: 20px;">
			<div style="display: flex; align-items: center; gap: 16px; flex: 1;">
				<div class="ghl-integration-icon" style="
					background: linear-gradient(135deg, #96588a 0%, #7a4571 100%);
					width: 56px;
					height: 56px;
					border-radius: 12px;
					display: flex;
					align-items: center;
					justify-content: center;
					box-shadow: 0 4px 12px rgba(150, 88, 138, 0.2);
					flex-shrink: 0;
				">
					<span class="dashicons dashicons-cart" style="font-size: 28px; color: #fff;"></span>
				</div>
				<div style="flex: 1;">
					<h2 style="margin: 0 0 6px 0; font-size: 20px; font-weight: 600; color: #1e293b; line-height: 1.2;">
						<?php esc_html_e( 'WooCommerce Integration', 'ghl-crm-integration' ); ?>
					</h2>
					<p class="description" style="margin: 0; font-size: 14px; color: #64748b; line-height: 1.5;">
						<?php esc_html_e( 'Sync WooCommerce customers and track cart abandonment with GoHighLevel', 'ghl-crm-integration' ); ?>
					</p>
				</div>
			</div>
			<div style="display: flex; align-items: center; gap: 12px; flex-shrink: 0;">
				<label class="ghl-checkbox <?php echo $wc_enabled ? 'is-checked' : ''; ?>" style="margin: 0;">
					<input 
						type="checkbox" 
						class="ghl-checkbox-original"
						id="wc_enabled" 
						name="wc_enabled" 
						value="1"
						<?php checked( $wc_enabled, true ); ?>
					>
					<span class="ghl-checkbox-input <?php echo $wc_enabled ? 'is-checked' : ''; ?>">
						<span class="ghl-checkbox-inner"></span>
					</span>
					<span class="ghl-checkbox-label" style="font-weight: 500; color: #475569;">
						<?php echo $wc_enabled ? esc_html__( 'Enabled', 'ghl-crm-integration' ) : esc_html__( 'Disabled', 'ghl-crm-integration' ); ?>
					</span>
				</label>
			</div>
		</div>

		<div class="ghl-settings-body" id="wc-settings-body" style="<?php echo ! $wc_enabled ? 'display: none;' : ''; ?>">
			<p>
				<?php
				printf(
					/* translators: %s: WooCommerce version */
					esc_html__( 'WooCommerce %s detected. Configure your integration settings below.', 'ghl-crm-integration' ),
					defined( 'WC_VERSION' ) ? WC_VERSION : ''
				);
				?>
			</p>
			<hr>
			
			<div class="ghl-form-builder">
				<form class="ghl-form" method="post">
					
					<!-- Auto-convert Lead to Customer -->
					<div class="ghl-form-item">
						<div class="ghl-form-item-content">
							<label class="ghl-checkbox <?php echo $wc_convert_lead_enabled ? 'is-checked' : ''; ?>">
								<input 
									type="checkbox" 
									class="ghl-checkbox-original"
									id="wc_convert_lead_enabled" 
									name="wc_convert_lead_enabled" 
									value="1"
									<?php checked( $wc_convert_lead_enabled, true ); ?>
								>
								<span class="ghl-checkbox-input <?php echo $wc_convert_lead_enabled ? 'is-checked' : ''; ?>">
									<span class="ghl-checkbox-inner"></span>
								</span>
								<span class="ghl-checkbox-label">
									<?php esc_html_e( 'Auto-convert Lead to Customer', 'ghl-crm-integration' ); ?>
								</span>
								<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Automatically converts leads to customers in GoHighLevel when they complete their first purchase in WooCommerce. This helps maintain accurate customer status across your systems.', 'ghl-crm-integration' ); ?>">?</span>
							</label>
						</div>
					</div>
					
					<!-- Customer Tags -->
					<div class="ghl-form-item" id="wc-customer-tag-field" style="margin-left: 30px; <?php echo ! $wc_convert_lead_enabled ? 'display: none;' : ''; ?>">
						<div class="ghl-form-item-content">
							<div style="margin-bottom: 20px;">
								<label style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px;">
									<?php esc_html_e( 'Customer Tags', 'ghl-crm-integration' ); ?>
									<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'These tags will be automatically applied to contacts in GoHighLevel when they complete their first purchase and are converted from lead to customer status.', 'ghl-crm-integration' ); ?>">?</span>
								</label>
								<select 
									id="wc_customer_tag" 
									name="wc_customer_tag[]" 
									multiple 
									class="ghl-tags-select"
									style="width: 500px; max-width: 100%;"
									data-saved-tags='<?php echo wp_json_encode( is_array( $wc_customer_tag ) ? $wc_customer_tag : ( ! empty( $wc_customer_tag ) ? array( $wc_customer_tag ) : array() ) ); ?>'
									data-placeholder="<?php esc_attr_e( 'Select tags to apply when lead becomes customer...', 'ghl-crm-integration' ); ?>">
									<option value=""><?php esc_html_e( 'Loading tags...', 'ghl-crm-integration' ); ?></option>
								</select>
							</div>
						</div>
					</div>
					
					<!-- Abandoned Cart Tracking -->
					<div class="ghl-form-item">
						<div class="ghl-form-item-content">
							<label class="ghl-checkbox <?php echo $wc_abandoned_cart_enabled ? 'is-checked' : ''; ?>">
								<input 
									type="checkbox" 
									class="ghl-checkbox-original"
									id="wc_abandoned_cart_enabled" 
									name="wc_abandoned_cart_enabled" 
									value="1"
									<?php checked( $wc_abandoned_cart_enabled, true ); ?>
								>
								<span class="ghl-checkbox-input <?php echo $wc_abandoned_cart_enabled ? 'is-checked' : ''; ?>">
									<span class="ghl-checkbox-inner"></span>
								</span>
								<span class="ghl-checkbox-label">
									<?php esc_html_e( 'Abandoned Cart Tracking', 'ghl-crm-integration' ); ?>
								</span>
								<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Tracks incomplete checkouts and automatically applies tags to contacts when they leave items in their cart without completing the purchase. Perfect for triggering follow-up campaigns.', 'ghl-crm-integration' ); ?>">?</span>
							</label>
						</div>
					</div>
					
					<!-- Abandoned Cart Settings -->
					<div id="wc-abandoned-cart-settings" style="margin-left: 30px; <?php echo ! $wc_abandoned_cart_enabled ? 'display: none;' : ''; ?>">
						
						<!-- Abandoned Cart Time -->
						<div class="ghl-form-item">
							<div class="ghl-form-item-content">
								<div style="margin-bottom: 20px;">
									<label style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px;">
										<?php esc_html_e( 'Consider Abandoned After (minutes)', 'ghl-crm-integration' ); ?>
										<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'How long to wait before considering a cart abandoned. Must be between 15 and 1440 minutes (24 hours). Recommended: 60 minutes for optimal follow-up timing.', 'ghl-crm-integration' ); ?>">?</span>
									</label>
									<input 
										type="number" 
										id="wc_abandoned_cart_time" 
										name="wc_abandoned_cart_time" 
										class="ghl-input"
										value="<?php echo esc_attr( $wc_abandoned_cart_time ); ?>"
										min="15"
										max="1440"
										placeholder="60"
										style="width: 200px;"
									>
								</div>
							</div>
						</div>
						
						<!-- Abandoned Cart Tags -->
						<div class="ghl-form-item">
							<div class="ghl-form-item-content">
								<div style="margin-bottom: 20px;">
									<label style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px;">
										<?php esc_html_e( 'Abandoned Cart Tags', 'ghl-crm-integration' ); ?>
										<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'These tags will be automatically applied to contacts when they leave items in their cart without completing the purchase. Use these tags to trigger recovery workflows in GoHighLevel.', 'ghl-crm-integration' ); ?>">?</span>
									</label>
									<select 
										id="wc_abandoned_cart_tag" 
										name="wc_abandoned_cart_tag[]" 
										multiple 
										class="ghl-tags-select"
										style="width: 500px; max-width: 100%;"
										data-saved-tags='<?php echo wp_json_encode( is_array( $wc_abandoned_cart_tag ) ? $wc_abandoned_cart_tag : ( ! empty( $wc_abandoned_cart_tag ) ? array( $wc_abandoned_cart_tag ) : array() ) ); ?>'
										data-placeholder="<?php esc_attr_e( 'Select tags to apply for abandoned carts...', 'ghl-crm-integration' ); ?>">
										<option value=""><?php esc_html_e( 'Loading tags...', 'ghl-crm-integration' ); ?></option>
									</select>
								</div>
							</div>
						</div>
						
					</div>
					
				</form>
			</div>
		</div>
	</div>
<?php endif; ?>
