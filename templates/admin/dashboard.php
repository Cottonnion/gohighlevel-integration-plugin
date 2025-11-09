<?php
/**
 * Dashboard Template
 *
 * @package GHL_CRM_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Debug Mode Toggle
 * 
 * Set to TRUE to show debug information including:
 * - Settings dump
 * - Queue manager status
 * - Scheduled action details
 * - Pending queue items
 * - Error logs
 * - API connection test
 * - Manual queue trigger button
 * 
 * Set to FALSE for production (clean dashboard)
 */
define( 'GHL_SHOW_DEBUG', true );

// Get OAuth handler and status
$oauth_handler = new \GHL_CRM\API\OAuth\OAuthHandler();
$oauth_status  = $oauth_handler->get_connection_status();
$settings      = \GHL_CRM\Core\SettingsManager::get_instance()->get_settings_array();
?>
<div class="ghl-crm-dashboard">
	<div class="ghl-card">
		<h2><?php esc_html_e( 'Connection Setup', 'ghl-crm-integration' ); ?></h2>
		
		<?php if ( $oauth_status['connected'] || ! empty( $settings['api_token'] ) ) : ?>
			<!-- Connected State -->
			<div class="ghl-connection-status">
			<?php if ( $oauth_status['connected'] ) : ?>
				<div class="ghl-status-connected">
					<span class="dashicons dashicons-yes-alt"></span>
					<strong><?php esc_html_e( 'Connected', 'ghl-crm-integration' ); ?></strong>
					<?php
					$location_name = $settings['location_name'] ?? 'GoHighLevel';
					if ( ! empty( $location_name ) && $location_name !== 'GoHighLevel' ) {
						printf(
							/* translators: %s: Location name */
							esc_html__( 'to %s', 'ghl-crm-integration' ),
							esc_html( $location_name )
						);
					}
					?>
					<br><small>
						<?php
						printf(
							/* translators: %s: Connection date */
							esc_html__( 'Connected: %s', 'ghl-crm-integration' ),
							esc_html( $oauth_status['connected_at'] ?? __( 'Unknown', 'ghl-crm-integration' ) )
						);
						?>
					</small>
					<br>
					<button type="button" class="button button-secondary" id="ghl-disconnect-btn" style="margin-top: 10px;">
						<?php esc_html_e( 'Disconnect Account', 'ghl-crm-integration' ); ?>
					</button>
				</div>

				<!-- Connection Details -->
				<div style="margin-top: 20px;">
					<h3><?php esc_html_e( 'Connection Details', 'ghl-crm-integration' ); ?></h3>
					<table class="widefat" style="margin-top: 10px;">
						<tbody>
							<tr>
								<td style="width: 30%; font-weight: 600;">
									<?php esc_html_e( 'Location ID:', 'ghl-crm-integration' ); ?>
								</td>
								<td><code><?php echo esc_html( $oauth_status['location_id'] ?: __( 'Not available', 'ghl-crm-integration' ) ); ?></code></td>
							</tr>
							<tr>
								<td style="font-weight: 600;">
									<?php esc_html_e( 'Location Name:', 'ghl-crm-integration' ); ?>
								</td>
								<td><?php echo esc_html( $location_name ); ?></td>
							</tr>
							<tr>
								<td style="font-weight: 600;">
									<?php esc_html_e( 'Token Status:', 'ghl-crm-integration' ); ?>
								</td>
								<td>
									<?php
									$expires_at = $oauth_status['expires_at'];
									if ( $expires_at && $expires_at > time() ) {
										$time_left = human_time_diff( time(), $expires_at );
										printf(
											/* translators: %s: Time remaining */
											esc_html__( 'Valid (expires in %s)', 'ghl-crm-integration' ),
											esc_html( $time_left )
										);
									} else {
										esc_html_e( 'Expired (will auto-refresh)', 'ghl-crm-integration' );
									}
									?>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			<?php elseif ( ! empty( $settings['api_token'] ) ) : ?>
				<!-- Manual API Key Connected -->
				<div class="ghl-status-connected">
					<span class="dashicons dashicons-yes-alt"></span>
					<strong><?php esc_html_e( 'Connected (API Key)', 'ghl-crm-integration' ); ?></strong>
					<p><?php esc_html_e( 'Using manual API key configuration.', 'ghl-crm-integration' ); ?></p>
					
					<div style="margin-top: 20px;">
						<h3><?php esc_html_e( 'Connection Details', 'ghl-crm-integration' ); ?></h3>
						<table class="widefat" style="margin-top: 10px;">
							<tbody>
								<tr>
									<td style="width: 30%; font-weight: 600;">
										<?php esc_html_e( 'Location ID:', 'ghl-crm-integration' ); ?>
									</td>
									<td><code><?php echo esc_html( $settings['location_id'] ?? __( 'Not set', 'ghl-crm-integration' ) ); ?></code></td>
								</tr>
								<tr>
									<td style="font-weight: 600;">
										<?php esc_html_e( 'API Token:', 'ghl-crm-integration' ); ?>
									</td>
									<td><code><?php echo esc_html( substr( $settings['api_token'], 0, 20 ) . '...' ); ?></code></td>
								</tr>
							</tbody>
						</table>
					</div>
					
					<div style="margin-top: 15px;">
						<button type="button" class="button button-secondary" id="ghl-disconnect-api-btn">
							<span class="dashicons dashicons-dismiss"></span>
							<?php esc_html_e( 'Disconnect API Key', 'ghl-crm-integration' ); ?>
						</button>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=ghl-crm-admin#/settings' ) ); ?>" class="button button-secondary">
							<?php esc_html_e( 'Update Settings', 'ghl-crm-integration' ); ?>
						</a>
					</div>
				</div>
			<?php endif; ?>
			</div>
			
		<?php else : ?>
			<!-- Not Connected - Show Connection Options -->
			<div class="ghl-connection-tabs">
				<!-- Tab Navigation -->
				<div class="ghl-tab-nav">
					<button type="button" class="ghl-tab-button active" data-tab="manual">
						<span class="dashicons dashicons-admin-network"></span>
						<?php esc_html_e( 'API Key (Recommended)', 'ghl-crm-integration' ); ?>
					</button>
					<button type="button" class="ghl-tab-button" data-tab="oauth">
						<span class="dashicons dashicons-cloud"></span>
						<?php esc_html_e( 'OAuth Connection', 'ghl-crm-integration' ); ?>
					</button>
				</div>

				<!-- Manual API Key Tab -->
				<div class="ghl-tab-content active" id="manual-tab">
					<div class="ghl-tab-inner">
						<h3><?php esc_html_e( 'Connect Using API Key', 'ghl-crm-integration' ); ?></h3>
						<p class="description">
							<?php esc_html_e( 'This is the recommended method. Use a GoHighLevel API key to connect your location. This method is more reliable and doesn\'t require OAuth app configuration.', 'ghl-crm-integration' ); ?>
						</p>

						<div class="ghl-info-box" style="background: #e7f3ff; border-left: 4px solid #2271b1; padding: 15px; margin: 20px 0;">
							<h4 style="margin-top: 0;">
								<span class="dashicons dashicons-info" style="color: #2271b1;"></span>
								<?php esc_html_e( 'How to Create a Private Integration:', 'ghl-crm-integration' ); ?>
							</h4>
							<ol style="margin: 10px 0 0 20px;">
								<li><?php esc_html_e( 'Log into your GoHighLevel sub-account', 'ghl-crm-integration' ); ?></li>
								<li><?php esc_html_e( 'Go to Settings → Integrations → Private Integrations', 'ghl-crm-integration' ); ?></li>
								<li><?php esc_html_e( 'Click "Create" to create a new integration', 'ghl-crm-integration' ); ?></li>
								<li><?php esc_html_e( 'Give it a name (e.g., "WordPress Plugin")', 'ghl-crm-integration' ); ?></li>
								<li><?php esc_html_e( 'Select the required scopes listed below', 'ghl-crm-integration' ); ?></li>
								<li><?php esc_html_e( 'Click "Create" and copy the generated API Key', 'ghl-crm-integration' ); ?></li>
								<li><?php esc_html_e( 'Get Location ID from Settings → Business Profile → Location ID', 'ghl-crm-integration' ); ?></li>
							</ol>
						</div>

						<!-- Required Scopes for API Key -->
						<div class="ghl-oauth-scopes" style="background: #fff4e6; padding: 20px; border-radius: 8px; margin: 20px 0; border: 1px solid #ffb84d;">
							<div style="display: flex; align-items: center; margin-bottom: 15px;">
								<div style="margin-right: 10px; border: 1px solid #ffb84d; padding: 8px; border-radius: 8px; background: #fff;">
									<span class="dashicons dashicons-warning" style="font-size: 32px; width: 32px; height: 32px; color: #f0a020;"></span>
								</div>
								<div>
									<h3 style="margin: 0; font-size: 16px; font-weight: 600; color: #b45309;">
										<?php esc_html_e( 'Required Scopes', 'ghl-crm-integration' ); ?>
									</h3>
									<p style="margin: 5px 0 0 0; font-size: 14px; color: #92400e; line-height: 1.5;">
										<?php esc_html_e( 'These scopes are necessary for the plugin to function properly. Make sure to select all of them when creating your private integration.', 'ghl-crm-integration' ); ?>
									</p>
								</div>
							</div>

							<!-- Scopes List -->
							<div style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 15px;">
								<?php
								$required_scopes = array(
									'contacts.readonly'    => __( 'View Contacts', 'ghl-crm-integration' ),
									'contacts.write'       => __( 'Edit Contacts', 'ghl-crm-integration' ),
									'contacts/tags.readonly' => __( 'View Tags', 'ghl-crm-integration' ),
									'contacts/tags.write'  => __( 'Edit Tags', 'ghl-crm-integration' ),
									'locations.readonly'   => __( 'View Locations', 'ghl-crm-integration' ),
									'locations/tasks.write' => __( 'Edit Location Tasks', 'ghl-crm-integration' ),
									'locations/customFields.readonly' => __( 'View Custom Fields', 'ghl-crm-integration' ),
									'locations/customFields.write' => __( 'Edit Custom Fields', 'ghl-crm-integration' ),
									'objects/schema.readonly' => __( 'View Objects Schema', 'ghl-crm-integration' ),
									'objects/schema.write' => __( 'Edit Objects Schema', 'ghl-crm-integration' ),
									'objects/records.readonly' => __( 'View Objects Record', 'ghl-crm-integration' ),
									'objects/records.write' => __( 'Edit Objects Record', 'ghl-crm-integration' ),
									'associations.readonly' => __( 'View Associations', 'ghl-crm-integration' ),
									'associations.write'   => __( 'Write Associations', 'ghl-crm-integration' ),
									'associations/relations.readonly' => __( 'View Associations Relation', 'ghl-crm-integration' ),
									'associations/relations.write' => __( 'Write Associations Relation', 'ghl-crm-integration' ),
									'forms.readonly'       => __( 'View Forms', 'ghl-crm-integration' ),
								);

								foreach ( $required_scopes as $scope => $label ) :
								?>
									<div style="display: inline-flex; align-items: center; background: #fef3c7; border: 1px solid #fbbf24; border-radius: 4px; padding: 6px 12px; font-size: 14px; color: #78350f;">
										<span><?php echo esc_html( $label ); ?></span>
									</div>
								<?php endforeach; ?>
							</div>
						</div>

						<form id="ghl-manual-connection-form" method="post" style="max-width: 600px;">
							<?php wp_nonce_field( 'ghl_crm_manual_connect', 'ghl_manual_connect_nonce' ); ?>
							
							<table class="form-table">
								<tr>
									<th scope="row">
										<label for="api_token">
											<?php esc_html_e( 'API Key', 'ghl-crm-integration' ); ?>
											<span class="required" style="color: #dc3232;">*</span>
										</label>
									</th>
									<td>
										<input 
											type="text" 
											id="api_token" 
											name="api_token" 
											class="regular-text code" 
											placeholder="<?php esc_attr_e( 'Enter your GoHighLevel API key', 'ghl-crm-integration' ); ?>"
											required
										/>
										<p class="description">
											<?php esc_html_e( 'Your location API key from GoHighLevel Settings', 'ghl-crm-integration' ); ?>
										</p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="location_id">
											<?php esc_html_e( 'Location ID', 'ghl-crm-integration' ); ?>
											<span class="required" style="color: #dc3232;">*</span>
										</label>
									</th>
									<td>
										<input 
											type="text" 
											id="location_id" 
											name="location_id" 
											class="regular-text code" 
											placeholder="<?php esc_attr_e( 'Enter your Location ID', 'ghl-crm-integration' ); ?>"
											required
										/>
										<p class="description">
											<?php esc_html_e( 'Found in the same page as your API key', 'ghl-crm-integration' ); ?>
										</p>
									</td>
								</tr>
							</table>

							<p class="submit">
								<button type="submit" class="button button-primary button-large">
									<span class="dashicons dashicons-yes-alt" style="margin-top: 3px;"></span>
									<?php esc_html_e( 'Connect Now', 'ghl-crm-integration' ); ?>
								</button>
							</p>
						</form>
					</div>
				</div>

				<!-- OAuth Tab -->
				<div class="ghl-tab-content" id="oauth-tab">
					<div class="ghl-tab-inner">
						<h3><?php esc_html_e( 'Connect Using OAuth', 'ghl-crm-integration' ); ?></h3>
						<p class="description">
							<?php esc_html_e( 'Use our OAuth app to connect multiple locations easily. This method is ideal for agencies managing multiple sub-accounts.', 'ghl-crm-integration' ); ?>
						</p>

						<div class="ghl-info-box" style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0;">
							<h4 style="margin-top: 0;">
								<span class="dashicons dashicons-lightbulb" style="color: #ffc107;"></span>
								<?php esc_html_e( 'About OAuth Connection:', 'ghl-crm-integration' ); ?>
							</h4>
							<ul style="margin: 10px 0 0 20px;">
								<li><?php esc_html_e( 'One-click connection to GoHighLevel', 'ghl-crm-integration' ); ?></li>
								<li><?php esc_html_e( 'Automatic token refresh (stays connected)', 'ghl-crm-integration' ); ?></li>
								<li><?php esc_html_e( 'Works across multiple locations', 'ghl-crm-integration' ); ?></li>
								<li><?php esc_html_e( 'More secure than manual API keys', 'ghl-crm-integration' ); ?></li>
							</ul>
						</div>

						<!-- Required Scopes Section -->
						<div class="ghl-oauth-scopes" style="background: #f9f9f9; padding: 20px; border-radius: 8px; margin: 20px 0; border: 1px solid #e0e0e6;">
							<div style="display: flex; align-items: center; margin-bottom: 15px;">
								<div style="margin-right: 10px; border: 1px solid #e0e0e6; padding: 8px; border-radius: 8px; background: #fff;">
									<span class="dashicons dashicons-info" style="font-size: 32px; width: 32px; height: 32px; color: #2271b1;"></span>
								</div>
								<div>
									<h3 style="margin: 0; font-size: 16px; font-weight: 600; color: #344054;">
										<?php esc_html_e( 'Required Scopes', 'ghl-crm-integration' ); ?>
									</h3>
									<p style="margin: 5px 0 0 0; font-size: 14px; color: #667085; line-height: 1.5;">
										<?php esc_html_e( 'These scopes are necessary for the plugin to function properly. When you click "Connect with GoHighLevel" below, you\'ll be asked to authorize these permissions for our app.', 'ghl-crm-integration' ); ?>
									</p>
								</div>
							</div>

							<!-- Scopes List -->
							<div style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 15px;">
								<?php
								$required_scopes = array(
									'contacts.readonly'    => __( 'View Contacts', 'ghl-crm-integration' ),
									'contacts.write'       => __( 'Edit Contacts', 'ghl-crm-integration' ),
									'contacts/tags.readonly' => __( 'View Tags', 'ghl-crm-integration' ),
									'contacts/tags.write'  => __( 'Edit Tags', 'ghl-crm-integration' ),
									'locations.readonly'   => __( 'View Locations', 'ghl-crm-integration' ),
									'locations/tasks.write' => __( 'Edit Location Tasks', 'ghl-crm-integration' ),
									'locations/customFields.readonly' => __( 'View Custom Fields', 'ghl-crm-integration' ),
									'locations/customFields.write' => __( 'Edit Custom Fields', 'ghl-crm-integration' ),
									'objects/schema.readonly' => __( 'View Objects Schema', 'ghl-crm-integration' ),
									'objects/schema.write' => __( 'Edit Objects Schema', 'ghl-crm-integration' ),
									'objects/records.readonly' => __( 'View Objects Record', 'ghl-crm-integration' ),
									'objects/records.write' => __( 'Edit Objects Record', 'ghl-crm-integration' ),
									'associations.readonly' => __( 'View Associations', 'ghl-crm-integration' ),
									'associations.write'   => __( 'Write Associations', 'ghl-crm-integration' ),
									'associations/relations.readonly' => __( 'View Associations Relation', 'ghl-crm-integration' ),
									'associations/relations.write' => __( 'Write Associations Relation', 'ghl-crm-integration' ),
									'forms.readonly'       => __( 'View Forms', 'ghl-crm-integration' ),
								);

								foreach ( $required_scopes as $scope => $label ) :
								?>
									<div style="display: inline-flex; align-items: center; background: #fafafc; border: 1px solid #e0e0e6; border-radius: 4px; padding: 6px 12px; font-size: 14px; color: #344054;">
										<span><?php echo esc_html( $label ); ?></span>
									</div>
								<?php endforeach; ?>
							</div>
						</div>

						<div style="text-align: center; padding: 20px;">
							<a target='_blank' href="<?php echo esc_url( $oauth_handler->get_authorization_url() ); ?>" class="button button-primary button-hero">
								<span class="dashicons dashicons-cloud" style="margin-top: 5px;"></span>
								<?php esc_html_e( 'Connect with GoHighLevel', 'ghl-crm-integration' ); ?>
							</a>
							<p class="description" style="margin-top: 15px;">
								<?php esc_html_e( 'You will be redirected to GoHighLevel to authorize this integration. After authorization, you\'ll be redirected back here.', 'ghl-crm-integration' ); ?>
							</p>
						</div>
					</div>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>