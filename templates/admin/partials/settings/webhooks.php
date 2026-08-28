<?php
/**
 * Webhooks Settings Partial - Manual Setup
 *
 * @package Syncly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get webhook handler
$webhook_handler    = \Syncly\API\Webhooks\WebhookHandler::get_instance();
$webhook_status     = $webhook_handler->get_webhook_status();
$setup_instructions = $webhook_handler->get_webhook_setup_instructions();
$webhook_secret     = $setup_instructions['webhook_secret'] ?? '';
$webhook_header     = strtoupper( $setup_instructions['webhook_header'] ?? 'X-GHL-TOKEN' );
$settings           = \Syncly\Core\SettingsManager::get_instance()->get_settings_array();
?>

<div class="ghl-settings-wrapper">
	<?php wp_nonce_field( 'syncly_settings_nonce', 'syncly_nonce' ); ?>

	<!-- Webhook Status Card -->
	<div class="ghl-settings-section ghl-settings-card">
		<div class="ghl-settings-header">
			<h2>
				<span class="dashicons dashicons-admin-network"></span>
				<?php esc_html_e( 'Webhook Status', 'syncly' ); ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Set up webhooks manually in your GoHighLevel account to receive real-time contact updates in WordPress.', 'syncly' ); ?>
			</p>
		</div>

		<hr>

		<div id="webhook-status-display" class="ghl-webhook-status">
			<?php if ( $webhook_status['status'] === 'active' ) : ?>
				<span class="ghl-status-badge ghl-status-badge--active">
					<span class="dashicons dashicons-yes-alt"></span>
					<?php esc_html_e( 'Active', 'syncly' ); ?>
				</span>
				<p class="description">
					<?php
					printf(
						/* translators: %d: Number of webhooks received in the last 24 hours */
						esc_html__( 'Webhook is receiving data. %d webhooks processed in the last 24 hours.', 'syncly' ),
						esc_html( $webhook_status['recent_webhooks_24h'] )
					);
					?>
				</p>
				<?php if ( $webhook_status['last_webhook_received'] ) : ?>
					<p class="description">
						<?php
						printf(
							/* translators: %s: Date and time of last webhook received */
							esc_html__( 'Last webhook received: %s', 'syncly' ),
							esc_html( $webhook_status['last_webhook_received'] )
						);
						?>
					</p>
				<?php endif; ?>
			<?php else : ?>
				<span class="ghl-status-badge ghl-status-badge--inactive">
					<span class="dashicons dashicons-warning"></span>
					<?php esc_html_e( 'Not Configured', 'syncly' ); ?>
				</span>
				<p class="description">
					<?php esc_html_e( 'No webhooks have been received recently. Follow the setup instructions below to configure webhooks in your GoHighLevel account.', 'syncly' ); ?>
				</p>
			<?php endif; ?>

			<p>
				<button type="button" class="ghl-button ghl-button-secondary" id="ghl-test-webhook">
					<span class="dashicons dashicons-admin-tools"></span>
					<?php esc_html_e( 'Test Webhook Endpoint', 'syncly' ); ?>
				</button>
				<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Sends a test request to verify your webhook endpoint is working correctly. This checks that your WordPress site can receive webhook data from GoHighLevel.', 'syncly' ); ?>">?</span>
			</p>
		</div>
	</div>

	<!-- Setup Instructions -->
	<div class="ghl-settings-section ghl-settings-card">
		<div class="ghl-settings-header">
			<h2>
				<span class="dashicons dashicons-welcome-learn-more"></span>
				<?php esc_html_e( 'Setup Instructions', 'syncly' ); ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Follow these four steps to connect a GoHighLevel workflow to your webhook endpoint.', 'syncly' ); ?>
			</p>
		</div>

		<hr>

		<div class="ghl-setup-steps">

			<!-- Step 1: Copy URL -->
			<div class="ghl-setup-step">
				<div class="ghl-step-header">
					<span class="ghl-step-number">1</span>
					<h4><?php esc_html_e( 'Copy Your Webhook URL', 'syncly' ); ?></h4>
				</div>
				<p class="description">
					<?php esc_html_e( 'Copy this URL to use in your GoHighLevel automation:', 'syncly' ); ?>
				</p>

				<div class="ghl-copy-row">
					<input
						type="text"
						id="webhook_url"
						value="<?php echo esc_url( $setup_instructions['webhook_url'] ); ?>"
						class="large-text code"
						readonly
					/>
					<button type="button" class="ghl-button ghl-button-secondary" id="copy-webhook-url">
						<span class="dashicons dashicons-clipboard"></span>
						<?php esc_html_e( 'Copy', 'syncly' ); ?>
					</button>
				</div>
			</div>

			<!-- Step 2: Add Security Header -->
			<div class="ghl-setup-step">
				<div class="ghl-step-header">
					<span class="ghl-step-number">2</span>
					<h4><?php esc_html_e( 'Add the Security Header', 'syncly' ); ?></h4>
				</div>
				<p class="description">
					<?php esc_html_e( 'Paste this header into your GoHighLevel outbound webhook action. Webhooks without this token will be rejected.', 'syncly' ); ?>
				</p>

				<div class="ghl-token-label">
					<?php esc_html_e( 'Header', 'syncly' ); ?>:
					<code id="webhook-secret-header-text"><?php echo esc_html( $webhook_header ); ?></code>
				</div>

				<div class="ghl-copy-row">
					<input
						type="text"
						id="webhook-secret-field"
						value="<?php echo esc_attr( $webhook_secret ); ?>"
						class="regular-text code"
						readonly
					/>
					<button type="button" class="ghl-button ghl-button-secondary" id="copy-webhook-secret">
						<span class="dashicons dashicons-clipboard"></span>
						<?php esc_html_e( 'Copy Token', 'syncly' ); ?>
					</button>
				</div>

				<p class="description">
					<?php esc_html_e( 'If you suspect unwanted traffic or need to rotate credentials, regenerate the token and update your GoHighLevel automation immediately.', 'syncly' ); ?>
				</p>
				<p>
					<button type="button" class="ghl-button ghl-button-secondary" id="regenerate-webhook-secret">
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e( 'Regenerate Token', 'syncly' ); ?>
					</button>
				</p>
			</div>

			<!-- Step 3: GoHighLevel Setup -->
			<div class="ghl-setup-step">
				<div class="ghl-step-header">
					<span class="ghl-step-number">3</span>
					<h4><?php esc_html_e( 'Create Automation in GoHighLevel', 'syncly' ); ?></h4>
				</div>

				<p class="description">
					<?php esc_html_e( 'Follow these steps in your GoHighLevel account:', 'syncly' ); ?>
				</p>
				<ol class="ghl-steps-list">
					<li><?php esc_html_e( 'Log into your GoHighLevel account', 'syncly' ); ?></li>
					<li><?php esc_html_e( 'Go to Automation → Workflows', 'syncly' ); ?></li>
					<li><?php esc_html_e( 'Create a new workflow (or edit existing)', 'syncly' ); ?></li>
					<li><?php esc_html_e( 'Set trigger: Contact Created, Contact Updated, Contact Deleted, or Contact Tag Updated (for tags added/changed/removed)', 'syncly' ); ?></li>
					<li><?php esc_html_e( 'Add action: Outbound Webhook', 'syncly' ); ?></li>
					<li><?php esc_html_e( 'Paste the webhook URL from step 1', 'syncly' ); ?></li>
					<li>
						<?php
						printf(
							/* translators: %s header name */
							esc_html__( 'Keep method as POST and add header %s with the token above.', 'syncly' ),
							esc_html( $webhook_header )
						);
						?>
					</li>
					<li><?php esc_html_e( 'Save and activate the workflow', 'syncly' ); ?></li>
				</ol>
			</div>

			<!-- Step 4: Test & Verify -->
			<div class="ghl-setup-step">
				<div class="ghl-step-header">
					<span class="ghl-step-number">4</span>
					<h4><?php esc_html_e( 'Test and Verify', 'syncly' ); ?></h4>
				</div>
				<p class="description">
					<?php esc_html_e( 'Open your GoHighLevel workflow, click "Test Workflow" (top right), send a test, then confirm it appears in Sync Logs.', 'syncly' ); ?>
				</p>
				<p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=syncly-admin#/sync-logs' ) ); ?>" class="ghl-button ghl-button-secondary">
						<span class="dashicons dashicons-list-view"></span>
						<?php esc_html_e( 'Open Sync Logs', 'syncly' ); ?>
					</a>
				</p>
			</div>

		</div>
	</div>

	<!-- Webhook Processing Settings -->
	<div class="ghl-settings-section ghl-settings-card">
		<div class="ghl-settings-header">
			<h2>
				<span class="dashicons dashicons-admin-generic"></span>
				<?php esc_html_e( 'Webhook Processing Settings', 'syncly' ); ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Configure how incoming webhooks are processed.', 'syncly' ); ?>
			</p>
		</div>

		<hr>

		<div class="ghl-form-builder">
			<form class="ghl-form" method="post">

				<div class="ghl-form-item">
					<div class="ghl-form-item-content ghl-form-item-content--column">
						<span class="ghl-form-label">
							<?php esc_html_e( 'Webhook Sync', 'syncly' ); ?>
						</span>
						<p style="margin: 0;">
							<span class="dashicons dashicons-yes-alt" style="color: var(--ghl-success);"></span>
							<strong><?php esc_html_e( 'GoHighLevel → WordPress', 'syncly' ); ?></strong>
						</p>
						<p class="description ghl-form-description">
							<?php esc_html_e( 'Webhooks sync contact data from GoHighLevel to WordPress automatically when contacts are created, updated, or deleted in GoHighLevel.', 'syncly' ); ?>
						</p>
					</div>
				</div>

				<div class="ghl-form-item">
					<div class="ghl-form-item-content ghl-form-item-content--column">
						<span class="ghl-form-label">
							<?php esc_html_e( 'Supported Events', 'syncly' ); ?>
						</span>
						<ul class="ghl-events-list">
							<?php foreach ( $setup_instructions['supported_events'] as $event => $description ) : ?>
								<li>
									<span class="dashicons dashicons-yes-alt"></span>
									<span><strong><?php echo esc_html( $event ); ?></strong> — <?php echo esc_html( $description ); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
						<p class="description ghl-form-description">
							<?php esc_html_e( 'These are the contact events that the webhook endpoint can process.', 'syncly' ); ?>
						</p>
					</div>
				</div>

				<div class="ghl-form-item">
					<div class="ghl-form-item-content">
						<label class="ghl-form-label" for="allow_user_deletion">
							<?php esc_html_e( 'Allow User Deletion', 'syncly' ); ?>
						</label>
						<label class="ghl-checkbox <?php echo ! empty( $settings['allow_user_deletion'] ) ? 'is-checked' : ''; ?>">
							<input type="checkbox"
									class="ghl-checkbox-original"
									id="allow_user_deletion"
									name="allow_user_deletion"
									value="1"
									<?php checked( $settings['allow_user_deletion'] ?? false ); ?>
									>
							<span class="ghl-checkbox-input <?php echo ! empty( $settings['allow_user_deletion'] ) ? 'is-checked' : ''; ?>">
								<span class="ghl-checkbox-inner"></span>
							</span>
							<span class="ghl-checkbox-label">
								<?php esc_html_e( 'Delete WordPress users when contacts are deleted in GoHighLevel', 'syncly' ); ?>
								<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'If disabled, users will be unlinked from GHL contacts but not deleted.', 'syncly' ); ?>">?</span>
							</span>
						</label>
					</div>
				</div>

				<div class="ghl-form-item-footer" style="margin-top: var(--ghl-spacing-xl);">
					<button type="button" class="ghl-button ghl-button-primary ghl-save-settings-btn">
						<span class="ghl-button-text"><?php esc_html_e( 'Save Webhook Settings', 'syncly' ); ?></span>
					</button>
				</div>

			</form>
		</div>
	</div>

	<!-- Help Section -->
	<div class="ghl-settings-section ghl-settings-card">
		<div class="ghl-help-box">
			<h3>
				<span class="dashicons dashicons-info-outline"></span>
				<?php esc_html_e( 'How It Works', 'syncly' ); ?>
			</h3>
			<div class="ghl-help-content">
				<p><strong><?php esc_html_e( 'Endpoint:', 'syncly' ); ?></strong>
					<?php esc_html_e( 'GoHighLevel sends a POST request to your webhook URL whenever a configured contact event fires. WordPress verifies the security header before processing.', 'syncly' ); ?>
				</p>
				<p><strong><?php esc_html_e( 'Security Header:', 'syncly' ); ?></strong>
					<?php esc_html_e( 'Every incoming webhook must include the header token shown above. Requests without a valid token are rejected immediately.', 'syncly' ); ?>
				</p>
				<p><strong><?php esc_html_e( 'Processing:', 'syncly' ); ?></strong>
					<?php esc_html_e( 'Contacts are matched to WordPress users by email or generated user IDs, then created, updated, or unlinked as needed. Optional tag-based automation can run on receipt.', 'syncly' ); ?>
				</p>
				<p><strong><?php esc_html_e( 'Testing:', 'syncly' ); ?></strong>
					<?php esc_html_e( 'Use the "Test Webhook Endpoint" button to confirm your site can receive webhook data, then use Sync Logs to review what arrives.', 'syncly' ); ?>
				</p>
			</div>
		</div>
	</div>

</div><!-- .ghl-settings-wrapper -->