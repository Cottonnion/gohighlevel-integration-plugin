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
$wc_convert_order_statuses = $settings['wc_convert_order_statuses'] ?? [];

// Opportunities settings
$wc_opportunities_enabled         = $settings['wc_opportunities_enabled'] ?? false;
$wc_opportunities_pipeline        = $settings['wc_opportunities_pipeline'] ?? '';
$wc_opportunities_stage_abandoned = $settings['wc_opportunities_stage_abandoned'] ?? '';
$wc_opportunities_stage_pending   = $settings['wc_opportunities_stage_pending'] ?? '';
$wc_opportunities_stage_processing = $settings['wc_opportunities_stage_processing'] ?? '';
$wc_opportunities_stage_completed = $settings['wc_opportunities_stage_completed'] ?? '';
$wc_opportunities_stage_cancelled = $settings['wc_opportunities_stage_cancelled'] ?? '';
$wc_opportunities_stage_won       = $settings['wc_opportunities_stage_won'] ?? '';
$wc_opportunities_filter_type     = $settings['wc_opportunities_filter_type'] ?? 'all'; // all, products, categories, min_value
$wc_opportunities_products        = $settings['wc_opportunities_products'] ?? [];
$wc_opportunities_categories      = $settings['wc_opportunities_categories'] ?? [];
$wc_opportunities_min_value       = $settings['wc_opportunities_min_value'] ?? 0;

// Get all WooCommerce order statuses
$order_statuses = [];
$product_categories = [];
if ( $is_woocommerce_active ) {
	$order_statuses = wc_get_order_statuses();
	
	// Get WooCommerce product categories for filter
	$categories = get_terms([
		'taxonomy'   => 'product_cat',
		'hide_empty' => false,
	]);
	if ( ! is_wp_error( $categories ) ) {
		foreach ( $categories as $category ) {
			$product_categories[ $category->term_id ] = $category->name;
		}
	}
}

// Get connection status to check if we should show scope warnings
$oauth_handler = new \GHL_CRM\API\OAuth\OAuthHandler();
$oauth_status  = $oauth_handler->get_connection_status();
$is_connected  = $oauth_status['connected'] || ! empty( $settings['api_token'] );
?>

<?php
// Show scope checker warnings at the top if connected
if ( $is_connected && $is_woocommerce_active ) :
	// Check required scopes for WooCommerce integration
	\GHL_CRM\Core\ScopeChecker::render_scope_notice( 'contacts' );
	\GHL_CRM\Core\ScopeChecker::render_scope_notice( 'tags' );
	\GHL_CRM\Core\ScopeChecker::render_scope_notice( 'opportunities' );
endif;
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
				<label class="ghl-checkbox <?php echo $wc_enabled ? 'is-checked' : ''; ?>" style="margin: 0; display: flex; align-items: center; gap: 8px;">
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
					defined( 'WC_VERSION' ) ? absint( WC_VERSION ) : ''
				);
				?>
			</p>
			<hr>
			
			<div class="ghl-form-builder">
				<form class="ghl-form" method="post">
					
					<!-- Auto-convert Lead to Customer -->
					<div class=>
						<div class="ghl-form-item-content">
							<label class="ghl-checkbox <?php echo $wc_convert_lead_enabled ? 'is-checked' : ''; ?>" style="display: flex; align-items: center; gap: 6px;">
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
					<div class= id="wc-customer-tag-field" style="margin-left: 30px; <?php echo ! $wc_convert_lead_enabled ? 'display: none;' : ''; ?>">
						<div class="ghl-form-item-content">
							<div style="margin-bottom: 20px;">
								<label style="display: flex; align-items: center; gap: 6px; margin-bottom: 8px; font-weight: 600; font-size: 14px;">
									<span><?php esc_html_e( 'Customer Tags', 'ghl-crm-integration' ); ?></span>
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
					
					<!-- Order Status Trigger -->
					<div class= id="wc-convert-order-status-field" style="margin-left: 30px; <?php echo ! $wc_convert_lead_enabled ? 'display: none;' : ''; ?>">
						<div class="ghl-form-item-content">
							<div style="margin-bottom: 20px;">
								<label style="display: flex; align-items: center; gap: 6px; margin-bottom: 8px; font-weight: 600; font-size: 14px;">
									<span><?php esc_html_e( 'Convert on Order Status', 'ghl-crm-integration' ); ?></span>
									<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Select which order statuses should trigger the conversion. Leave empty to convert immediately on purchase regardless of status. If you select specific statuses, conversion will only happen when the order reaches one of those statuses.', 'ghl-crm-integration' ); ?>">?</span>
								</label>
								<select 
									id="wc_convert_order_statuses" 
									name="wc_convert_order_statuses[]" 
									multiple 
									class="ghl-order-status-select"
									style="width: 500px; max-width: 100%;"
									data-placeholder="<?php esc_attr_e( 'Leave empty to convert on any order, or select specific statuses...', 'ghl-crm-integration' ); ?>">
									<?php foreach ( $order_statuses as $status_key => $status_label ) : ?>
										<option value="<?php echo esc_attr( $status_key ); ?>" 
											<?php echo in_array( $status_key, (array) $wc_convert_order_statuses ) ? 'selected' : ''; ?>>
											<?php echo esc_html( $status_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description" style="margin-top: 8px; font-size: 13px; color: #666;">
									<?php esc_html_e( 'Examples: "Processing" for immediate conversion, or "Completed" to wait until order fulfillment.', 'ghl-crm-integration' ); ?>
								</p>
							</div>
						</div>
					</div>
					
					<!-- Abandoned Cart Tracking -->
					<div class=>
						<div class="ghl-form-item-content">
							<label class="ghl-checkbox <?php echo $wc_abandoned_cart_enabled ? 'is-checked' : ''; ?>" style="display: flex; align-items: center; gap: 6px;">
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
						<div class=>
							<div class="ghl-form-item-content">
								<div style="margin-bottom: 20px;">
									<label style="display: flex; align-items: center; gap: 6px; margin-bottom: 8px; font-weight: 600; font-size: 14px;">
										<span><?php esc_html_e( 'Consider Abandoned After (minutes)', 'ghl-crm-integration' ); ?></span>
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
						<div class=>
							<div class="ghl-form-item-content">
								<div style="margin-bottom: 20px;">
									<label style="display: flex; align-items: center; gap: 6px; margin-bottom: 8px; font-weight: 600; font-size: 14px;">
										<span><?php esc_html_e( 'Abandoned Cart Tags', 'ghl-crm-integration' ); ?></span>
										<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'These tags will be automatically applied to contacts when they leave items in their cart without completing the purchase. You can use these tags to trigger recovery workflows in GoHighLevel.', 'ghl-crm-integration' ); ?>">?</span>
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

	<!-- Opportunities Feature Section (HIDDEN - WORK IN PROGRESS) -->
	<?php if ( false ) : // Temporarily hidden ?>
	<div class="ghl-settings-section ghl-settings-card" style="margin-top: 24px;">
		<div class="ghl-settings-header" style="padding: 24px; border-bottom: 1px solid #e5e7eb;">
			<div style="display: flex; align-items: center; gap: 16px;">
				<div class="ghl-integration-icon" style="
					background: linear-gradient(135deg, #10b981 0%, #059669 100%);
					width: 48px;
					height: 48px;
					border-radius: 10px;
					display: flex;
					align-items: center;
					justify-content: center;
					box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
					flex-shrink: 0;
				">
					<span class="dashicons dashicons-chart-line" style="font-size: 24px; color: #fff;"></span>
				</div>
				<div style="flex: 1;">
					<h3 style="margin: 0 0 4px 0; font-size: 18px; font-weight: 600; color: #1e293b;">
						<?php esc_html_e( 'Opportunities (Sales Pipeline)', 'ghl-crm-integration' ); ?>
					</h3>
					<p class="description" style="margin: 0; font-size: 14px; color: #64748b;">
						<?php esc_html_e( 'Track abandoned carts and orders as opportunities in your GoHighLevel sales pipeline', 'ghl-crm-integration' ); ?>
					</p>
				</div>
			</div>
		</div>

		<div class="ghl-settings-body" style="padding: 24px;">
			<?php
			// Check if opportunities scope is available (already checked at top, but re-check to be safe)
			$scope_check = \GHL_CRM\Core\ScopeChecker::check_scope( 'opportunities' );
			$has_opportunities_access = $scope_check['has_access'] ?? false;

			if ( $has_opportunities_access ) :
				// Opportunities settings form
				?>
				<!-- Opportunities Settings Form -->
				<div class="ghl-form-builder">
					<form class="ghl-form" method="post">
						
						<!-- Enable Opportunities -->
						<div class=>
							<div class="ghl-form-item-content">
								<label class="ghl-checkbox <?php echo $wc_opportunities_enabled ? 'is-checked' : ''; ?>" style="display: flex; align-items: center; gap: 6px;">
									<input 
										type="checkbox" 
										class="ghl-checkbox-original"
										id="wc_opportunities_enabled" 
										name="wc_opportunities_enabled" 
										value="1"
										<?php checked( $wc_opportunities_enabled, true ); ?>
									>
									<span class="ghl-checkbox-input <?php echo $wc_opportunities_enabled ? 'is-checked' : ''; ?>">
										<span class="ghl-checkbox-inner"></span>
									</span>
									<span class="ghl-checkbox-label">
										<?php esc_html_e( 'Create Opportunities in Sales Pipeline', 'ghl-crm-integration' ); ?>
									</span>
									<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Automatically create and update opportunities in your GoHighLevel sales pipeline when carts are abandoned or orders are placed. This gives you visual pipeline tracking of your sales process.', 'ghl-crm-integration' ); ?>">?</span>
								</label>
							</div>
						</div>

						<!-- Opportunities Settings Container -->
						<div id="wc-opportunities-settings" style="margin-left: 30px; <?php echo ! $wc_opportunities_enabled ? 'display: none;' : ''; ?>">
							
							<!-- Pipeline Selection -->
							<div class=>
								<div class="ghl-form-item-content">
									<div style="margin-bottom: 20px;">
										<label style="display: flex; align-items: center; gap: 6px; margin-bottom: 8px; font-weight: 600; font-size: 14px;">
											<span><?php esc_html_e( 'Select Pipeline', 'ghl-crm-integration' ); ?></span>
											<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Choose which GoHighLevel pipeline to use for tracking WooCommerce opportunities. Opportunities will be created in this pipeline and moved through stages based on order status.', 'ghl-crm-integration' ); ?>">?</span>
										</label>
										<select 
											id="wc_opportunities_pipeline" 
											name="wc_opportunities_pipeline" 
											class="ghl-pipeline-select"
											style="width: 500px; max-width: 100%;"
											data-saved-value="<?php echo esc_attr( $wc_opportunities_pipeline ); ?>"
											data-placeholder="<?php esc_attr_e( 'Select a pipeline...', 'ghl-crm-integration' ); ?>">
											<option value=""><?php esc_html_e( 'Loading pipelines...', 'ghl-crm-integration' ); ?></option>
										</select>
										<p class="description" style="margin-top: 8px; font-size: 13px; color: #666;">
											<?php esc_html_e( 'Pipelines are loaded from your GoHighLevel account. If you don\'t see your pipeline, make sure it exists in GHL.', 'ghl-crm-integration' ); ?>
										</p>
									</div>
								</div>
							</div>

							<!-- Stage Mapping Section -->
							<div id="wc-opportunities-stage-mapping" style="<?php echo empty( $wc_opportunities_pipeline ) ? 'display: none;' : ''; ?>">
								<div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 16px 20px; border-radius: 6px; margin-bottom: 20px;">
									<h4 style="margin: 0 0 8px 0; font-size: 14px; font-weight: 600; color: #1e293b;">
										<?php esc_html_e( '📊 Stage Mapping', 'ghl-crm-integration' ); ?>
									</h4>
									<p style="margin: 0; font-size: 13px; color: #64748b; line-height: 1.5;">
										<?php esc_html_e( 'Map WooCommerce events to your pipeline stages. Opportunities will automatically move through these stages as the cart/order status changes.', 'ghl-crm-integration' ); ?>
									</p>
								</div>

								<!-- Abandoned Cart Stage -->
								<div class=>
									<div class="ghl-form-item-content">
										<div style="margin-bottom: 16px;">
											<label style="display: flex; align-items: center; gap: 6px; margin-bottom: 8px; font-weight: 500; font-size: 13px;">
												<span>🛒 <?php esc_html_e( 'Abandoned Cart Stage', 'ghl-crm-integration' ); ?></span>
												<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Stage for when a cart is abandoned. The opportunity starts here.', 'ghl-crm-integration' ); ?>">?</span>
											</label>
											<select 
												id="wc_opportunities_stage_abandoned" 
												name="wc_opportunities_stage_abandoned" 
												class="ghl-stage-select"
												data-pipeline-id="<?php echo esc_attr( $wc_opportunities_pipeline ); ?>"
												data-saved-value="<?php echo esc_attr( $wc_opportunities_stage_abandoned ); ?>"
												style="width: 400px; max-width: 100%;">
												<option value=""><?php esc_html_e( 'Select stage...', 'ghl-crm-integration' ); ?></option>
											</select>
										</div>
									</div>
								</div>

								<!-- Pending Payment Stage -->
								<div class=>
									<div class="ghl-form-item-content">
										<div style="margin-bottom: 16px;">
											<label style="display: flex; align-items: center; gap: 6px; margin-bottom: 8px; font-weight: 500; font-size: 13px;">
												<span>⏳ <?php esc_html_e( 'Pending Payment Stage', 'ghl-crm-integration' ); ?></span>
												<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Stage for orders awaiting payment (e.g., bank transfer pending).', 'ghl-crm-integration' ); ?>">?</span>
											</label>
											<select 
												id="wc_opportunities_stage_pending" 
												name="wc_opportunities_stage_pending" 
												class="ghl-stage-select"
												data-pipeline-id="<?php echo esc_attr( $wc_opportunities_pipeline ); ?>"
												data-saved-value="<?php echo esc_attr( $wc_opportunities_stage_pending ); ?>"
												style="width: 400px; max-width: 100%;">
												<option value=""><?php esc_html_e( 'Select stage...', 'ghl-crm-integration' ); ?></option>
											</select>
										</div>
									</div>
								</div>

								<!-- Processing Stage -->
								<div class=>
									<div class="ghl-form-item-content">
										<div style="margin-bottom: 16px;">
											<label style="display: flex; align-items: center; gap: 6px; margin-bottom: 8px; font-weight: 500; font-size: 13px;">
												<span>⚙️ <?php esc_html_e( 'Processing Stage', 'ghl-crm-integration' ); ?></span>
												<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Stage for orders being prepared/packed for shipment.', 'ghl-crm-integration' ); ?>">?</span>
											</label>
											<select 
												id="wc_opportunities_stage_processing" 
												name="wc_opportunities_stage_processing" 
												class="ghl-stage-select"
												data-pipeline-id="<?php echo esc_attr( $wc_opportunities_pipeline ); ?>"
												data-saved-value="<?php echo esc_attr( $wc_opportunities_stage_processing ); ?>"
												style="width: 400px; max-width: 100%;">
												<option value=""><?php esc_html_e( 'Select stage...', 'ghl-crm-integration' ); ?></option>
											</select>
										</div>
									</div>
								</div>

								<!-- Completed Stage -->
								<div class=>
									<div class="ghl-form-item-content">
										<div style="margin-bottom: 16px;">
											<label style="display: flex; align-items: center; gap: 6px; margin-bottom: 8px; font-weight: 500; font-size: 13px;">
												<span>✅ <?php esc_html_e( 'Completed Stage (Won)', 'ghl-crm-integration' ); ?></span>
												<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Stage for completed orders. Opportunity will be marked as WON and moved here.', 'ghl-crm-integration' ); ?>">?</span>
											</label>
											<select 
												id="wc_opportunities_stage_completed" 
												name="wc_opportunities_stage_completed" 
												class="ghl-stage-select"
												data-pipeline-id="<?php echo esc_attr( $wc_opportunities_pipeline ); ?>"
												data-saved-value="<?php echo esc_attr( $wc_opportunities_stage_completed ); ?>"
												style="width: 400px; max-width: 100%;">
												<option value=""><?php esc_html_e( 'Select stage...', 'ghl-crm-integration' ); ?></option>
											</select>
										</div>
									</div>
								</div>

								<!-- Cancelled/Failed Stage -->
								<div class=>
									<div class="ghl-form-item-content">
										<div style="margin-bottom: 16px;">
											<label style="display: flex; align-items: center; gap: 6px; margin-bottom: 8px; font-weight: 500; font-size: 13px;">
												<span>❌ <?php esc_html_e( 'Cancelled/Failed Stage (Lost)', 'ghl-crm-integration' ); ?></span>
												<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Stage for cancelled, refunded, or failed orders. Opportunity will be marked as LOST.', 'ghl-crm-integration' ); ?>">?</span>
											</label>
											<select 
												id="wc_opportunities_stage_cancelled" 
												name="wc_opportunities_stage_cancelled" 
												class="ghl-stage-select"
												data-pipeline-id="<?php echo esc_attr( $wc_opportunities_pipeline ); ?>"
												data-saved-value="<?php echo esc_attr( $wc_opportunities_stage_cancelled ); ?>"
												style="width: 400px; max-width: 100%;">
												<option value=""><?php esc_html_e( 'Select stage...', 'ghl-crm-integration' ); ?></option>
											</select>
										</div>
									</div>
								</div>
							</div>

							<!-- Product Filter Section -->
							<div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 16px 20px; border-radius: 6px; margin: 24px 0;">
								<h4 style="margin: 0 0 12px 0; font-size: 14px; font-weight: 600; color: #92400e;">
									🎯 <?php esc_html_e( 'Product Filtering', 'ghl-crm-integration' ); ?>
								</h4>
								
								<!-- Filter Type Selection -->
								<div style="margin-bottom: 16px;">
									<label style="display: block; margin-bottom: 8px; font-weight: 500; font-size: 13px; color: #78350f;">
										<?php esc_html_e( 'When should opportunities be created?', 'ghl-crm-integration' ); ?>
									</label>
									<select 
										id="wc_opportunities_filter_type" 
										name="wc_opportunities_filter_type" 
										style="width: 100%; max-width: 500px; padding: 8px 12px; border: 1px solid #fbbf24; border-radius: 4px;">
										<option value="all" <?php selected( $wc_opportunities_filter_type, 'all' ); ?>>
											<?php esc_html_e( '✨ All Products (Default)', 'ghl-crm-integration' ); ?>
										</option>
										<option value="products" <?php selected( $wc_opportunities_filter_type, 'products' ); ?>>
											<?php esc_html_e( '📦 Specific Products Only', 'ghl-crm-integration' ); ?>
										</option>
										<option value="categories" <?php selected( $wc_opportunities_filter_type, 'categories' ); ?>>
											<?php esc_html_e( '📁 Specific Categories Only', 'ghl-crm-integration' ); ?>
										</option>
										<option value="min_value" <?php selected( $wc_opportunities_filter_type, 'min_value' ); ?>>
											<?php esc_html_e( '💰 Orders Above Minimum Value', 'ghl-crm-integration' ); ?>
										</option>
									</select>
									<p class="description" style="margin-top: 8px; font-size: 12px; color: #92400e;">
										<strong>💡 <?php esc_html_e( 'Tip:', 'ghl-crm-integration' ); ?></strong>
										<?php esc_html_e( 'If you leave it on "All Products" with no filters, opportunities will be created for every purchase. Use filters to focus on high-value or specific product opportunities.', 'ghl-crm-integration' ); ?>
									</p>
								</div>

								<!-- Specific Products Filter -->
								<div id="wc-opportunities-products-filter" style="margin-bottom: 16px; <?php echo $wc_opportunities_filter_type !== 'products' ? 'display: none;' : ''; ?>">
									<label style="display: flex; align-items: center; gap: 6px; margin-bottom: 8px; font-weight: 500; font-size: 13px; color: #78350f;">
										<span><?php esc_html_e( 'Select Products', 'ghl-crm-integration' ); ?></span>
										<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Opportunities will only be created when cart/order contains at least one of these products.', 'ghl-crm-integration' ); ?>">?</span>
									</label>
									<select 
										id="wc_opportunities_products" 
										name="wc_opportunities_products[]" 
										multiple 
										class="ghl-products-select"
										style="width: 100%; max-width: 500px;"
										data-placeholder="<?php esc_attr_e( 'Search and select products...', 'ghl-crm-integration' ); ?>">
										<!-- Products loaded via AJAX with Select2 -->
									</select>
								</div>

								<!-- Category Filter -->
								<div id="wc-opportunities-categories-filter" style="margin-bottom: 16px; <?php echo $wc_opportunities_filter_type !== 'categories' ? 'display: none;' : ''; ?>">
									<label style="display: flex; align-items: center; gap: 6px; margin-bottom: 8px; font-weight: 500; font-size: 13px; color: #78350f;">
										<span><?php esc_html_e( 'Select Categories', 'ghl-crm-integration' ); ?></span>
										<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Opportunities will only be created when cart/order contains products from these categories.', 'ghl-crm-integration' ); ?>">?</span>
									</label>
									<select 
										id="wc_opportunities_categories" 
										name="wc_opportunities_categories[]" 
										multiple 
										class="ghl-categories-select"
										style="width: 100%; max-width: 500px;"
										data-placeholder="<?php esc_attr_e( 'Select categories...', 'ghl-crm-integration' ); ?>">
										<?php foreach ( $product_categories as $cat_id => $cat_name ) : ?>
											<option value="<?php echo esc_attr( $cat_id ); ?>" 
												<?php echo in_array( $cat_id, (array) $wc_opportunities_categories ) ? 'selected' : ''; ?>>
												<?php echo esc_html( $cat_name ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</div>

								<!-- Minimum Value Filter -->
								<div id="wc-opportunities-minvalue-filter" style="<?php echo $wc_opportunities_filter_type !== 'min_value' ? 'display: none;' : ''; ?>">
									<label style="display: flex; align-items: center; gap: 6px; margin-bottom: 8px; font-weight: 500; font-size: 13px; color: #78350f;">
										<span><?php esc_html_e( 'Minimum Cart/Order Value', 'ghl-crm-integration' ); ?></span>
										<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Opportunities will only be created when cart/order total is equal to or exceeds this amount (excluding tax and shipping).', 'ghl-crm-integration' ); ?>">?</span>
									</label>
									<div style="display: flex; align-items: center; gap: 8px;">
										<span style="font-size: 18px; color: #78350f;"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
										<input 
											type="number" 
											id="wc_opportunities_min_value" 
											name="wc_opportunities_min_value" 
											class="ghl-input"
											value="<?php echo esc_attr( $wc_opportunities_min_value ); ?>"
											min="0"
											step="0.01"
											placeholder="0.00"
											style="width: 150px;"
										>
									</div>
									<p class="description" style="margin-top: 8px; font-size: 12px; color: #92400e;">
										<?php esc_html_e( 'Example: Set to 100 to only track carts/orders worth $100 or more.', 'ghl-crm-integration' ); ?>
									</p>
								</div>
							</div>

						</div>

					</form>
				</div>
			<?php else : ?>
				<!-- No Opportunities Access -->
				<div style="text-align: center; padding: 60px 20px;">
					<span class="dashicons dashicons-lock" style="font-size: 64px; color: #cbd5e1; display: block; margin-bottom: 16px;"></span>
					<h3 style="margin: 0 0 8px 0; font-size: 16px; font-weight: 600; color: #475569;">
						<?php esc_html_e( 'Opportunities Access Required', 'ghl-crm-integration' ); ?>
					</h3>
					<p style="margin: 0; font-size: 14px; color: #64748b; max-width: 500px; margin: 0 auto;">
						<?php esc_html_e( 'To use the Opportunities feature, please add the required scopes to your GoHighLevel connection. See the notice above for instructions.', 'ghl-crm-integration' ); ?>
					</p>
				</div>
			<?php endif; ?>
		</div>
	</div>
	<?php endif; // End temporarily hidden opportunities section ?>
<?php endif; ?>
