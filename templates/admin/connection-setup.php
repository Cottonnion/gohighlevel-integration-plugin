<?php
/**
 * Connection Setup Template
 * Handles OAuth connection setup
 *
 * @package Syncly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get OAuth handler (expected to be passed from parent or instantiated here)
$oauth_handler = $oauth_handler ?? new \Syncly\API\OAuth\OAuthHandler();
?>

<!-- Connection Setup -->
<div class="ghl-connection-tabs">
	<!-- Tab Navigation -->
	<div class="ghl-tab-nav">
		<button type="button" class="ghl-tab-button active" data-tab="oauth">
			<span class="dashicons dashicons-cloud"></span>
			<?php esc_html_e( 'OAuth Connection (Recommended)', 'syncly' ); ?>
		</button>
	</div>

	<!-- OAuth Tab (Recommended) -->
	<div class="ghl-tab-content active" id="oauth-tab">
		<div class="ghl-tab-inner">
			<h3><?php esc_html_e( 'Connect Using OAuth', 'syncly' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Use our OAuth app to connect multiple locations easily. This method is ideal for agencies managing multiple sub-accounts and is more secure than API keys.', 'syncly' ); ?>
			</p>

			<div class="ghl-info-box" style="background: #eff6ff; border-left: 4px solid #3b82f6; padding: 15px; margin: 20px 0;">
				<h4 style="margin-top: 0;">
					<span class="dashicons dashicons-lightbulb" style="color: #3b82f6;"></span>
					<?php esc_html_e( 'Why OAuth is Recommended:', 'syncly' ); ?>
				</h4>
				<ul style="margin: 10px 0 0 20px;">
					<li><?php esc_html_e( 'One-click connection to GoHighLevel', 'syncly' ); ?></li>
					<li><?php esc_html_e( 'Automatic token refresh (stays connected)', 'syncly' ); ?></li>
					<li><?php esc_html_e( 'Works across multiple locations', 'syncly' ); ?></li>
					<li><?php esc_html_e( 'Secure, standards-based authorization flow', 'syncly' ); ?></li>
					<li><?php esc_html_e( 'No need to manually create integrations', 'syncly' ); ?></li>
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
							<?php esc_html_e( 'Required Scopes', 'syncly' ); ?>
						</h3>
						<p style="margin: 5px 0 0 0; font-size: 14px; color: #667085; line-height: 1.5;">
							<?php esc_html_e( 'These scopes are necessary for the plugin to function properly. When you click "Connect with GoHighLevel" below, you\'ll be asked to authorize these permissions for our app.', 'syncly' ); ?>
						</p>
					</div>
				</div>

				<!-- Scopes List -->
				<div style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 15px;">
					<?php
					$required_scopes = array(
						'contacts.readonly'               => __( 'View Contacts', 'syncly' ),
						'contacts.write'                  => __( 'Edit Contacts', 'syncly' ),
						'contacts/tags.readonly'          => __( 'View Tags', 'syncly' ),
						'contacts/tags.write'             => __( 'Edit Tags', 'syncly' ),
						'locations.readonly'              => __( 'View Locations', 'syncly' ),
						'locations/customFields.readonly' => __( 'View Custom Fields', 'syncly' ),
						'locations/customFields.write'    => __( 'Edit Custom Fields', 'syncly' ),
						'objects/schema.readonly'         => __( 'View Objects Schema', 'syncly' ),
						'objects/schema.write'            => __( 'Edit Objects Schema', 'syncly' ),
						'objects/records.readonly'        => __( 'View Objects Record', 'syncly' ),
						'objects/records.write'           => __( 'Edit Objects Record', 'syncly' ),
						'associations.readonly'           => __( 'View Associations', 'syncly' ),
						'associations.write'              => __( 'Write Associations', 'syncly' ),
						'associations/relations.readonly' => __( 'View Associations Relation', 'syncly' ),
						'associations/relations.write'    => __( 'Write Associations Relation', 'syncly' ),
						'forms.readonly'                  => __( 'View Forms', 'syncly' ),
						'conversations.readonly'          => __( 'View Conversations', 'syncly' ),
						'conversations.write'             => __( 'Create/Update Conversations', 'syncly' ),
						'conversations/message.readonly'  => __( 'View Messages', 'syncly' ),
						'conversations/message.write'     => __( 'Send Messages', 'syncly' ),
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
				<a target='_blank' href="<?php echo esc_url( $oauth_handler->get_authorization_url() ); ?>" class="ghl-button ghl-button-primary" style="padding: 14px 36px; font-size: 16px; display: inline-flex; align-items: center; gap: 10px; text-decoration: none;">
					<span class="dashicons dashicons-cloud" style="margin-top: 5px;"></span>
					<?php esc_html_e( 'Connect with GoHighLevel', 'syncly' ); ?>
				</a>
				<p class="description" style="margin-top: 15px;">
					<?php esc_html_e( 'You will be redirected to GoHighLevel to authorize this integration. After authorization, you\'ll be redirected back here.', 'syncly' ); ?>
				</p>
			</div>
		</div>
	</div>

</div>

</div>
