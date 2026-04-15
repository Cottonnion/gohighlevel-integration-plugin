<?php
/**
 * Webhooks Settings Partial - Manual Setup
 *
 * @package GHL_CRM_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get webhook handler
$webhook_handler    = \GHL_CRM\API\Webhooks\WebhookHandler::get_instance();
$webhook_status     = $webhook_handler->get_webhook_status();
$setup_instructions = $webhook_handler->get_webhook_setup_instructions();
$webhook_secret     = $setup_instructions['webhook_secret'] ?? '';
$webhook_header     = strtoupper( $setup_instructions['webhook_header'] ?? 'X-GHL-TOKEN' );
$settings           = \GHL_CRM\Core\SettingsManager::get_instance()->get_settings_array();
?>

<div class="ghl-settings-webhooks">
	<h2><?php esc_html_e( 'Webhook Setup', 'ghl-crm-integration' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Set up webhooks manually in your GoHighLevel account to receive real-time contact updates in WordPress.', 'ghl-crm-integration' ); ?>
	</p>

	<!-- Webhook Status Card -->
	<div class="ghl-card" style="margin: 20px 0; padding: 20px; background: #f9f9f9; border-left: 4px solid <?php echo $webhook_status['status'] === 'active' ? '#46b450' : '#dba617'; ?>;">
		<h3><?php esc_html_e( 'Current Status', 'ghl-crm-integration' ); ?></h3>
		
		<div id="webhook-status-display">
			<?php if ( $webhook_status['status'] === 'active' ) : ?>
				<p>
					<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
					<strong style="color: #46b450;"><?php esc_html_e( 'Active', 'ghl-crm-integration' ); ?></strong>
				</p>
				<p class="description">
				<?php
				printf(
					/* translators: %d: Number of webhooks received in the last 24 hours */
					esc_html__( 'Webhook is receiving data. %d webhooks processed in the last 24 hours.', 'ghl-crm-integration' ),
					esc_html( $webhook_status['recent_webhooks_24h'] )
				);
				?>
				</p>
				<?php if ( $webhook_status['last_webhook_received'] ) : ?>
					<p class="description">
					<?php
					printf(
						/* translators: %s: Date and time of last webhook received */
						esc_html__( 'Last webhook received: %s', 'ghl-crm-integration' ),
						esc_html( $webhook_status['last_webhook_received'] )
					);
					?>
					</p>
				<?php endif; ?>
			<?php else : ?>
				<p>
					<span class="dashicons dashicons-warning" style="color: #dba617;"></span>
					<strong style="color: #dba617;"><?php esc_html_e( 'Not Configured', 'ghl-crm-integration' ); ?></strong>
				</p>
				<p class="description">
					<?php esc_html_e( 'No webhooks have been received recently. Follow the setup instructions below to configure webhooks in your GoHighLevel account.', 'ghl-crm-integration' ); ?>
				</p>
			<?php endif; ?>

			<p>
				<button type="button" class="ghl-button ghl-button-secondary" id="ghl-test-webhook">
					<span class="dashicons dashicons-admin-tools"></span>
					<?php esc_html_e( 'Test Webhook Endpoint', 'ghl-crm-integration' ); ?>
				</button>
				<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Sends a test request to verify your webhook endpoint is working correctly. This checks that your WordPress site can receive webhook data from GoHighLevel.', 'ghl-crm-integration' ); ?>">?</span>
			</p>
		</div>
	</div>

	<!-- Setup Instructions -->
	<div class="ghl-webhook-setup" style="margin: 20px 0;">
		<h3><?php esc_html_e( 'Setup Instructions', 'ghl-crm-integration' ); ?></h3>
		
		<!-- Step 1: Copy URL -->
		<div class="ghl-setup-step" style="margin: 15px 0; padding: 15px; background: #fff; border: 1px solid #ddd;">
			<h4 style="margin-top: 0;">
				<span class="ghl-step-number" style="background: #7e3bd0; color: white; padding: 5px 10px; border-radius: 50%; margin-right: 10px;">1</span>
				<?php esc_html_e( 'Copy Your Webhook URL', 'ghl-crm-integration' ); ?>
			</h4>
			<p><?php esc_html_e( 'Copy this URL to use in your GoHighLevel automation:', 'ghl-crm-integration' ); ?></p>
			
			<div style="display: flex; align-items: center; gap: 10px; margin: 10px 0;">
				<input 
					type="text" 
					id="webhook_url" 
					value="<?php echo esc_url( $setup_instructions['webhook_url'] ); ?>" 
					class="large-text code" 
					readonly
					style="flex: 1;"
				/>
				<button type="button" class="ghl-button ghl-button-secondary" id="copy-webhook-url">
					<span class="dashicons dashicons-clipboard"></span>
					<?php esc_html_e( 'Copy', 'ghl-crm-integration' ); ?>
				</button>
			</div>
		</div>

		<!-- Step 2: Add Security Header -->
		<div class="ghl-setup-step" style="margin: 15px 0; padding: 15px; background: #fff; border: 1px solid #ddd;">
			<h4 style="margin-top: 0;">
				<span class="ghl-step-number" style="background: #7e3bd0; color: white; padding: 5px 10px; border-radius: 50%; margin-right: 10px;">2</span>
				<?php esc_html_e( 'Add the Security Header', 'ghl-crm-integration' ); ?>
			</h4>
			<p><?php esc_html_e( 'Paste this header into your GoHighLevel outbound webhook action. Webhooks without this token will be rejected.', 'ghl-crm-integration' ); ?></p>

			<div style="display: grid; grid-template-columns: 1fr auto auto; gap: 10px; align-items: center; margin: 10px 0;">
				<label style="margin: 0;">
					<strong><?php esc_html_e( 'Header', 'ghl-crm-integration' ); ?>:</strong> <span id="webhook-secret-header-text"><?php echo esc_html( $webhook_header ); ?></span>
				</label>
				<input
					type="text"
					id="webhook-secret-field"
					value="<?php echo esc_attr( $webhook_secret ); ?>"
					class="regular-text code"
					readonly
					style="width: 100%;"
				/>
				<button type="button" class="ghl-button ghl-button-secondary" id="copy-webhook-secret">
					<span class="dashicons dashicons-clipboard"></span>
					<?php esc_html_e( 'Copy Token', 'ghl-crm-integration' ); ?>
				</button>
			</div>

			<p class="description" style="margin: 10px 0;">
				<?php esc_html_e( 'If you suspect unwanted traffic or need to rotate credentials, regenerate the token and update your GoHighLevel automation immediately.', 'ghl-crm-integration' ); ?>
			</p>
			<p>
				<button type="button" class="ghl-button ghl-button-secondary" id="regenerate-webhook-secret">
					<span class="dashicons dashicons-update"></span>
					<?php esc_html_e( 'Regenerate Token', 'ghl-crm-integration' ); ?>
				</button>
			</p>
		</div>

		<!-- Step 3: GoHighLevel Setup -->
		<div class="ghl-setup-step" style="margin: 15px 0; padding: 15px; background: #fff; border: 1px solid #ddd;">
			<h4 style="margin-top: 0;">
				<span class="ghl-step-number" style="background: #7e3bd0; color: white; padding: 5px 10px; border-radius: 50%; margin-right: 10px;">3</span>
				<?php esc_html_e( 'Create Automation in GoHighLevel', 'ghl-crm-integration' ); ?>
			</h4>
			
			<p><?php esc_html_e( 'Follow these steps in your GoHighLevel account:', 'ghl-crm-integration' ); ?></p>
			<ol style="margin-left: 20px;">
				<li><?php esc_html_e( 'Log into your GoHighLevel account', 'ghl-crm-integration' ); ?></li>
				<li><?php esc_html_e( 'Go to Automation → Workflows', 'ghl-crm-integration' ); ?></li>
				<li><?php esc_html_e( 'Create a new workflow (or edit existing)', 'ghl-crm-integration' ); ?></li>
				<li><?php esc_html_e( 'Set trigger: Contact Created, Contact Updated, Contact Deleted, or Contact Tag Updated (for tags added/changed/removed)', 'ghl-crm-integration' ); ?></li>
				<li><?php esc_html_e( 'Add action: Outbound Webhook', 'ghl-crm-integration' ); ?></li>
				<li><?php esc_html_e( 'Paste the webhook URL from step 1', 'ghl-crm-integration' ); ?></li>
				<li><?php printf( /* translators: %s header name */ esc_html__( 'Keep method as POST and add header %s with the token above.', 'ghl-crm-integration' ), esc_html( $webhook_header ) ); ?></li>
				<li><?php esc_html_e( 'Save and activate the workflow', 'ghl-crm-integration' ); ?></li>
			</ol>
		</div>

		<!-- Step 4: Test & Verify -->
		<div class="ghl-setup-step" style="margin: 15px 0; padding: 15px; background: #fff; border: 1px solid #ddd;">
			<h4 style="margin-top: 0;">
				<span class="ghl-step-number" style="background: #7e3bd0; color: white; padding: 5px 10px; border-radius: 50%; margin-right: 10px;">4</span>
				<?php esc_html_e( 'Test and Verify', 'ghl-crm-integration' ); ?>
			</h4>
			<p><?php esc_html_e( 'Open your GoHighLevel workflow, click “Test Workflow” (top right), send a test, then confirm it appears in Sync Logs.', 'ghl-crm-integration' ); ?></p>
			<p class="description" style="font-size: 12px; color: #666;">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ghl-crm-admin#/sync-logs' ) ); ?>" class="ghl-button ghl-button-secondary"><?php esc_html_e( 'Open Sync Logs', 'ghl-crm-integration' ); ?></a>
			</p>
		</div>
	</div>

	<!-- Webhook Settings Form -->
	<form id="ghl-webhooks-settings-form" method="post">
		<?php wp_nonce_field( 'ghl_webhooks_settings', 'ghl_webhooks_nonce' ); ?>

		<h3><?php esc_html_e( 'Webhook Processing Settings', 'ghl-crm-integration' ); ?></h3>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<?php esc_html_e( 'Webhook Sync', 'ghl-crm-integration' ); ?>
					</th>
					<td>
						<p>
							<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
							<strong><?php esc_html_e( 'GoHighLevel → WordPress', 'ghl-crm-integration' ); ?></strong>
						</p>
						<p class="description">
							<?php esc_html_e( 'Webhooks sync contact data from GoHighLevel to WordPress automatically when contacts are created, updated, or deleted in GoHighLevel.', 'ghl-crm-integration' ); ?>
						</p>
						<input type="hidden" name="sync_direction" value="ghl_to_wp" />
					</td>
				</tr>

				<tr>
					<th scope="row">
						<?php esc_html_e( 'Supported Events', 'ghl-crm-integration' ); ?>
					</th>
					<td>
						<ul style="margin: 0; list-style: none;">
							<?php foreach ( $setup_instructions['supported_events'] as $event => $description ) : ?>
								<li style="margin: 5px 0;">
									<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
									<strong><?php echo esc_html( $event ); ?></strong> - <?php echo esc_html( $description ); ?>
								</li>
							<?php endforeach; ?>
						</ul>
						<p class="description">
							<?php esc_html_e( 'These are the contact events that the webhook endpoint can process.', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<?php esc_html_e( 'Allow User Deletion', 'ghl-crm-integration' ); ?>
					</th>
					<td>
						<div class="ghl-form-item">
							<div class="ghl-form-item-content">
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
										<?php esc_html_e( 'Delete WordPress users when contacts are deleted in GoHighLevel', 'ghl-crm-integration' ); ?>
										<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'If disabled, users will be unlinked from GHL contacts but not deleted.', 'ghl-crm-integration' ); ?>">?</span>
									</span>
								</label>
							</div>
						</div>
					</td>
				</tr>
			</tbody>
		</table>

		<p class="submit">
			<button type="submit" class="ghl-button ghl-button-primary ghl-save-settings-btn">
				<?php esc_html_e( 'Save Webhook Settings', 'ghl-crm-integration' ); ?>
			</button>
		</p>
	</form>

</div>

<script>
jQuery(document).ready(function($) {
	// Copy webhook URL
	$('#copy-webhook-url').on('click', function() {
		const urlField = document.getElementById('webhook_url');
		urlField.select();
		urlField.setSelectionRange(0, 99999); // For mobile devices
		
		try {
			document.execCommand('copy');
			$(this).html('<span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Copied!', 'ghl-crm-integration' ); ?>');
			setTimeout(() => {
				$(this).html('<span class="dashicons dashicons-clipboard"></span> <?php esc_html_e( 'Copy', 'ghl-crm-integration' ); ?>');
			}, 2000);
		} catch (err) {
			alert('<?php esc_html_e( 'Could not copy URL. Please copy manually.', 'ghl-crm-integration' ); ?>');
		}
	});

	// Copy webhook secret
	$('#copy-webhook-secret').on('click', function() {
		const secretField = document.getElementById('webhook-secret-field');
		secretField.select();
		secretField.setSelectionRange(0, 99999);

		try {
			document.execCommand('copy');
			$(this).html('<span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Copied!', 'ghl-crm-integration' ); ?>');
			setTimeout(() => {
				$(this).html('<span class="dashicons dashicons-clipboard"></span> <?php esc_html_e( 'Copy Token', 'ghl-crm-integration' ); ?>');
			}, 2000);
		} catch (err) {
			alert('<?php esc_html_e( 'Could not copy token. Please copy manually.', 'ghl-crm-integration' ); ?>');
		}
	});

	// Regenerate webhook secret
	$('#regenerate-webhook-secret').on('click', function() {
		if ( !confirm('<?php esc_html_e( 'Regenerate the token? You must update your GoHighLevel automation immediately after rotating it.', 'ghl-crm-integration' ); ?>') ) {
			return;
		}

		const $btn = $(this);
		const originalText = $btn.html();
		$btn.prop('disabled', true).html('<span class="dashicons dashicons-update ghl-spin"></span> <?php esc_html_e( 'Rotating...', 'ghl-crm-integration' ); ?>');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'ghl_crm_regenerate_webhook_secret',
				nonce: '<?php echo esc_js( wp_create_nonce( 'ghl_crm_admin' ) ); ?>'
			},
			success: function(response) {
				if (response.success && response.data) {
					$('#webhook-secret-field').val(response.data.webhook_secret);
					$('#webhook-secret-header-text').text(response.data.header || '<?php echo esc_js( $webhook_header ); ?>');
					alert('✓ ' + (response.data.message || '<?php esc_html_e( 'Token regenerated. Update your GoHighLevel automation.', 'ghl-crm-integration' ); ?>'));
				} else {
					alert('✗ ' + (response.data && response.data.message ? response.data.message : '<?php esc_html_e( 'Could not regenerate token.', 'ghl-crm-integration' ); ?>'));
				}
				$btn.prop('disabled', false).html(originalText);
			},
			error: function(xhr, status, error) {
				alert('✗ <?php esc_html_e( 'Token regeneration failed. Please try again.', 'ghl-crm-integration' ); ?>');
				$btn.prop('disabled', false).html(originalText);
			}
		});
	});

	// Test webhook endpoint
	$('#ghl-test-webhook').on('click', function() {
		const $btn = $(this);
		const originalText = $btn.html();
		
		const requestData = {
			action: 'ghl_crm_test_webhook',
			nonce: '<?php echo esc_js( wp_create_nonce( 'ghl_crm_admin' ) ); ?>'
		};
		
		$btn.prop('disabled', true).html('<span class="dashicons dashicons-update ghl-spin"></span> <?php esc_html_e( 'Testing...', 'ghl-crm-integration' ); ?>');
		
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: requestData,
			success: function(response) {
				if (response.success) {
					alert('✓ <?php esc_html_e( 'Test successful! Webhook endpoint is working correctly.', 'ghl-crm-integration' ); ?>');
				} else {
					alert('✗ <?php esc_html_e( 'Test failed:', 'ghl-crm-integration' ); ?> ' + (response.data ? response.data.message : 'Unknown error'));
				}
				$btn.prop('disabled', false).html(originalText);
			},
			error: function(xhr, status, error) {
				alert('✗ <?php esc_html_e( 'Test failed. Please check your server configuration.', 'ghl-crm-integration' ); ?>\n\nError: ' + error + '\nResponse: ' + xhr.responseText);
				$btn.prop('disabled', false).html(originalText);
			}
		});
	});
});

// Copy JSON template function
function copyJsonTemplate(templateId) {
	const textarea = document.getElementById(templateId + '-template');
	textarea.select();
	textarea.setSelectionRange(0, 99999); // For mobile devices
	
	try {
		document.execCommand('copy');
		
		// Find the button and update text
		const button = event.target.closest('button');
		const originalText = button.innerHTML;
		button.innerHTML = '<span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Copied!', 'ghl-crm-integration' ); ?>';
		
		setTimeout(() => {
			button.innerHTML = originalText;
		}, 2000);
	} catch (err) {
		alert('<?php esc_html_e( 'Could not copy template. Please copy manually.', 'ghl-crm-integration' ); ?>');
	}
}
</script>

<style>
.ghl-spin {
	animation: ghl-spin 1s linear infinite;
}
@keyframes ghl-spin {
	from { transform: rotate(0deg); }
	to { transform: rotate(360deg); }
}

.ghl-step-number {
	display: inline-block;
	width: 30px;
	height: 30px;
	line-height: 30px;
	text-align: center;
	border-radius: 50%;
	font-weight: bold;
}

.ghl-setup-step {
	border-radius: 5px;
	transition: box-shadow 0.3s ease;
}

.ghl-setup-step:hover {
	box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.ghl-card {
	border-radius: 5px;
	box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
</style>