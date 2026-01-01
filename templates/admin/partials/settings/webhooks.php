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
$webhook_handler = \GHL_CRM\API\Webhooks\WebhookHandler::get_instance();
$webhook_status  = $webhook_handler->get_webhook_status();
$setup_instructions = $webhook_handler->get_webhook_setup_instructions();
$settings        = \GHL_CRM\Core\SettingsManager::get_instance()->get_settings_array();
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

		<!-- Step 2: GoHighLevel Setup -->
		<div class="ghl-setup-step" style="margin: 15px 0; padding: 15px; background: #fff; border: 1px solid #ddd;">
			<h4 style="margin-top: 0;">
				<span class="ghl-step-number" style="background: #7e3bd0; color: white; padding: 5px 10px; border-radius: 50%; margin-right: 10px;">2</span>
				<?php esc_html_e( 'Create Automation in GoHighLevel', 'ghl-crm-integration' ); ?>
			</h4>
			
			<p><?php esc_html_e( 'Follow these steps in your GoHighLevel account:', 'ghl-crm-integration' ); ?></p>
			<ol style="margin-left: 20px;">
				<li><?php esc_html_e( 'Log into your GoHighLevel account', 'ghl-crm-integration' ); ?></li>
				<li><?php esc_html_e( 'Go to Automation → Workflows', 'ghl-crm-integration' ); ?></li>
				<li><?php esc_html_e( 'Create a new workflow (or edit existing)', 'ghl-crm-integration' ); ?></li>
				<li><?php esc_html_e( 'Set trigger: Contact Created, Contact Updated, or Contact Deleted', 'ghl-crm-integration' ); ?></li>
				<li><?php esc_html_e( 'Add action: Outbound Webhook', 'ghl-crm-integration' ); ?></li>
				<li><?php esc_html_e( 'Paste the webhook URL from step 1', 'ghl-crm-integration' ); ?></li>
				<li><?php esc_html_e( 'Keep method as POST (GoHighLevel sends JSON by default; no extra header needed)', 'ghl-crm-integration' ); ?></li>
				<li><?php esc_html_e( 'Use the JSON templates below for the body', 'ghl-crm-integration' ); ?></li>
				<li><?php esc_html_e( 'Save and activate the workflow', 'ghl-crm-integration' ); ?></li>
			</ol>
		</div>

		<!-- Step 3: JSON Templates -->
		<div class="ghl-setup-step" style="margin: 15px 0; padding: 15px; background: #fff; border: 1px solid #ddd;">
			<h4 style="margin-top: 0;">
				<span class="ghl-step-number" style="background: #7e3bd0; color: white; padding: 5px 10px; border-radius: 50%; margin-right: 10px;">3</span>
				<?php esc_html_e( 'JSON Body Templates', 'ghl-crm-integration' ); ?>
			</h4>
			
			<p><?php esc_html_e( 'Use these templates for the webhook body in your GoHighLevel automation:', 'ghl-crm-integration' ); ?></p>
			<p class="description" style="font-size: 12px; color: #666;">
				<?php esc_html_e( 'We accept GoHighLevel’s native flat payloads and the templated JSON below. If a sub-account uses a different payload shape, match it to these templates so the plugin can normalize it.', 'ghl-crm-integration' ); ?>
			</p>
			
			<!-- Contact Created/Updated Template -->
			<div style="margin: 15px 0;">
				<h5 style="margin-bottom: 5px;">
					<?php esc_html_e( 'For Contact Created/Updated:', 'ghl-crm-integration' ); ?>
					<button type="button" class="ghl-button button-small" onclick="copyJsonTemplate('contact-create')">
						<span class="dashicons dashicons-clipboard"></span> <?php esc_html_e( 'Copy', 'ghl-crm-integration' ); ?>
					</button>
				</h5>
				<textarea 
					id="contact-create-template" 
					readonly 
					style="width: 100%; height: 200px; font-family: monospace; font-size: 12px; background: #f9f9f9; border: 1px solid #ddd; padding: 10px;"
				><?php echo esc_textarea( json_encode( $setup_instructions['payload_examples']['contact_created'], JSON_PRETTY_PRINT ) ); ?></textarea>
				<p class="description" style="font-size: 12px; color: #666;">
					<?php esc_html_e( 'Change "ContactCreate" to "ContactUpdate" for update events.', 'ghl-crm-integration' ); ?>
				</p>
			</div>

			<!-- Contact Deleted Template -->
			<div style="margin: 15px 0;">
				<h5 style="margin-bottom: 5px;">
					<?php esc_html_e( 'For Contact Deleted:', 'ghl-crm-integration' ); ?>
					<button type="button" class="button button-small" onclick="copyJsonTemplate('contact-delete')">
						<span class="dashicons dashicons-clipboard"></span> <?php esc_html_e( 'Copy', 'ghl-crm-integration' ); ?>
					</button>
				</h5>
				<textarea 
					id="contact-delete-template" 
					readonly 
					style="width: 100%; height: 120px; font-family: monospace; font-size: 12px; background: #f9f9f9; border: 1px solid #ddd; padding: 10px;"
				><?php echo esc_textarea( json_encode( $setup_instructions['payload_examples']['contact_deleted'], JSON_PRETTY_PRINT ) ); ?></textarea>
			</div>
		</div>

		<!-- Step 4: Test & Verify -->
		<div class="ghl-setup-step" style="margin: 15px 0; padding: 15px; background: #fff; border: 1px solid #ddd;">
			<h4 style="margin-top: 0;">
				<span class="ghl-step-number" style="background: #7e3bd0; color: white; padding: 5px 10px; border-radius: 50%; margin-right: 10px;">4</span>
				<?php esc_html_e( 'Test and Verify', 'ghl-crm-integration' ); ?>
			</h4>
			<p><?php esc_html_e( 'Open your GoHighLevel workflow, click “Test Workflow” (top right), send a test, then confirm it appears in Sync Logs.', 'ghl-crm-integration' ); ?></p>
			<p class="description" style="font-size: 12px; color: #666;">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ghl-crm-settings&tab=sync-logs' ) ); ?>" class="button button-secondary"><?php esc_html_e( 'Open Sync Logs', 'ghl-crm-integration' ); ?></a>
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
						<label for="allow_user_deletion">
							<?php esc_html_e( 'Allow User Deletion', 'ghl-crm-integration' ); ?>
						</label>
					</th>
					<td>
						<label>
							<input type="checkbox" id="allow_user_deletion" name="allow_user_deletion" value="1" 
								<?php checked( $settings['allow_user_deletion'] ?? false ); ?> />
							<?php esc_html_e( 'Delete WordPress users when contacts are deleted in GoHighLevel', 'ghl-crm-integration' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'If disabled, users will be unlinked from GHL contacts but not deleted.', 'ghl-crm-integration' ); ?>
						</p>
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

	// Test webhook endpoint
	$('#ghl-test-webhook').on('click', function() {
		const $btn = $(this);
		const originalText = $btn.html();
		
		const requestData = {
			action: 'ghl_crm_test_webhook',
			nonce: '<?php echo esc_js( wp_create_nonce( 'ghl_crm_admin' ) ); ?>'
		};
		
		console.log('🔍 DEBUG: Test Webhook Button Clicked');
		console.log('AJAX URL:', ajaxurl);
		console.log('Request Data:', requestData);
		
		$btn.prop('disabled', true).html('<span class="dashicons dashicons-update ghl-spin"></span> <?php esc_html_e( 'Testing...', 'ghl-crm-integration' ); ?>');
		
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: requestData,
			success: function(response) {
				console.log('✅ AJAX Success Response:', response);
				if (response.success) {
					alert('✓ <?php esc_html_e( 'Test successful! Webhook endpoint is working correctly.', 'ghl-crm-integration' ); ?>');
				} else {
					alert('✗ <?php esc_html_e( 'Test failed:', 'ghl-crm-integration' ); ?> ' + (response.data ? response.data.message : 'Unknown error'));
				}
				$btn.prop('disabled', false).html(originalText);
			},
			error: function(xhr, status, error) {
				console.error('❌ AJAX Error:', {xhr, status, error});
				console.error('Response Text:', xhr.responseText);
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
