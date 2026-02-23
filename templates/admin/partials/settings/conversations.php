<?php
/**
 * Conversations Settings Tab
 *
 * Shows supported form plugins that can sync submissions to GHL conversations.
 * Loaded as a settings tab partial via handle_settings_tab_request.
 *
 * @package GHL_CRM_Integration
 */

defined( 'ABSPATH' ) || exit;

// Settings are inherited from the parent settings.php context.
if ( ! isset( $settings ) ) {
	$settings_manager = \GHL_CRM\Core\SettingsManager::get_instance();
	$settings         = $settings_manager->get_settings_array();
}

// Get enabled plugins setting.
$enabled_plugins = $settings['conversations_enabled_plugins'] ?? [];
if ( ! is_array( $enabled_plugins ) ) {
	$enabled_plugins = [];
}

// Define supported form plugins with detection.
$form_plugins = [
	'cf7'           => [
		'name'        => __( 'Contact Form 7', 'ghl-crm-integration' ),
		'slug'        => 'contact-form-7/wp-contact-form-7.php',
		'detect'      => function_exists( 'wpcf7' ),
		'icon'        => 'dashicons-email',
		'description' => __( 'Sync Contact Form 7 submissions to GHL conversations. Each form submission creates a message thread with the matched contact.', 'ghl-crm-integration' ),
		'docs_url'    => 'https://contactform7.com/',
	],
	'gravity_forms' => [
		'name'        => __( 'Gravity Forms', 'ghl-crm-integration' ),
		'slug'        => 'gravityforms/gravityforms.php',
		'detect'      => class_exists( 'GFForms' ),
		'icon'        => 'dashicons-feedback',
		'description' => __( 'Sync Gravity Forms entries to GHL conversations. Advanced form data including conditional logic fields are captured.', 'ghl-crm-integration' ),
		'docs_url'    => 'https://www.gravityforms.com/',
	],
	'wpforms'       => [
		'name'        => __( 'WPForms', 'ghl-crm-integration' ),
		'slug'        => 'wpforms-lite/wpforms.php',
		'detect'      => function_exists( 'wpforms' ),
		'icon'        => 'dashicons-list-view',
		'description' => __( 'Sync WPForms submissions to GHL conversations. Works with both Lite and Pro versions.', 'ghl-crm-integration' ),
		'docs_url'    => 'https://wpforms.com/',
	],
	'ninja_forms'   => [
		'name'        => __( 'Ninja Forms', 'ghl-crm-integration' ),
		'slug'        => 'ninja-forms/ninja-forms.php',
		'detect'      => class_exists( 'Ninja_Forms' ),
		'icon'        => 'dashicons-forms',
		'description' => __( 'Sync Ninja Forms submissions to GHL conversations. Supports multi-step forms and calculated fields.', 'ghl-crm-integration' ),
		'docs_url'    => 'https://ninjaforms.com/',
	],
	'elementor'     => [
		'name'        => __( 'Elementor Forms', 'ghl-crm-integration' ),
		'slug'        => 'elementor-pro/elementor-pro.php',
		'detect'      => defined( 'ELEMENTOR_PRO_VERSION' ),
		'icon'        => 'dashicons-welcome-widgets-menus',
		'description' => __( 'Sync Elementor Pro form submissions to GHL conversations. Requires Elementor Pro with the Forms widget.', 'ghl-crm-integration' ),
		'docs_url'    => 'https://elementor.com/',
	],
	'fluent_forms'  => [
		'name'        => __( 'Fluent Forms', 'ghl-crm-integration' ),
		'slug'        => 'fluentform/fluentform.php',
		'detect'      => defined( 'FLUENTFORM' ),
		'icon'        => 'dashicons-editor-table',
		'description' => __( 'Sync Fluent Forms submissions to GHL conversations. Lightweight and fast form processing.', 'ghl-crm-integration' ),
		'docs_url'    => 'https://fluentforms.com/',
	],
];
?>

<div class="ghl-conversations-container">
	<!-- Header -->
	<div class="notice notice-info" style="margin: 20px 0; padding: 15px; border-left-color: #2271b1;">
		<h3 style="margin-top: 0;">
			<span class="dashicons dashicons-format-chat" style="color: #2271b1;"></span>
			<?php esc_html_e( 'Form → Conversation Sync', 'ghl-crm-integration' ); ?>
		</h3>
		<p>
			<?php esc_html_e( 'Automatically sync form submissions from your WordPress form plugins to GoHighLevel conversations. When a visitor submits a form, a conversation message is created for the matched GHL contact.', 'ghl-crm-integration' ); ?>
		</p>
		<ul style="list-style: disc; margin-left: 20px;">
			<li><strong><?php esc_html_e( 'Contact Matching:', 'ghl-crm-integration' ); ?></strong> <?php esc_html_e( 'The email in the form submission is used to find the GHL contact.', 'ghl-crm-integration' ); ?></li>
			<li><strong><?php esc_html_e( 'Queue System:', 'ghl-crm-integration' ); ?></strong> <?php esc_html_e( 'Submissions are processed via the background queue with rate limiting and retries.', 'ghl-crm-integration' ); ?></li>
			<li><strong><?php esc_html_e( 'Custom Channel:', 'ghl-crm-integration' ); ?></strong> <?php esc_html_e( 'Messages appear in GHL under a "Custom" channel type for easy identification.', 'ghl-crm-integration' ); ?></li>
		</ul>
	</div>

	<!-- Form Plugins Grid -->
	<div class="ghl-conversations-plugins" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(400px, 1fr)); gap: 20px; margin: 20px 0;">
		<?php foreach ( $form_plugins as $plugin_key => $plugin ) : ?>
			<?php
			$is_installed = $plugin['detect'];
			$is_enabled   = in_array( $plugin_key, $enabled_plugins, true );
			$card_class   = 'ghl-plugin-card';
			if ( $is_enabled && $is_installed ) {
				$card_class .= ' ghl-plugin-active';
			}
			if ( ! $is_installed ) {
				$card_class .= ' ghl-plugin-not-installed';
			}
			?>
			<div class="<?php echo esc_attr( $card_class ); ?>" style="
				border: 1px solid <?php echo $is_enabled && $is_installed ? '#00a32a' : '#c3c4c7'; ?>;
				border-radius: 8px;
				padding: 20px;
				background: <?php echo $is_enabled && $is_installed ? '#f0fdf4' : ( $is_installed ? '#fff' : '#f6f7f7' ); ?>;
				position: relative;
				transition: border-color 0.2s;
			">
				<!-- Status Badge -->
				<?php if ( ! $is_installed ) : ?>
					<span style="
						position: absolute;
						top: 12px;
						right: 12px;
						background: #dba617;
						color: #fff;
						padding: 2px 10px;
						border-radius: 12px;
						font-size: 11px;
						font-weight: 600;
					">
						<?php esc_html_e( 'Not Installed', 'ghl-crm-integration' ); ?>
					</span>
				<?php elseif ( $is_enabled ) : ?>
					<span style="
						position: absolute;
						top: 12px;
						right: 12px;
						background: #00a32a;
						color: #fff;
						padding: 2px 10px;
						border-radius: 12px;
						font-size: 11px;
						font-weight: 600;
					">
						<?php esc_html_e( 'Active', 'ghl-crm-integration' ); ?>
					</span>
				<?php endif; ?>

				<!-- Plugin Header -->
				<div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
					<span class="dashicons <?php echo esc_attr( $plugin['icon'] ); ?>" style="
						font-size: 28px;
						width: 28px;
						height: 28px;
						color: <?php echo $is_installed ? '#2271b1' : '#a7aaad'; ?>;
					"></span>
					<h3 style="margin: 0; font-size: 16px; color: <?php echo $is_installed ? '#1d2327' : '#a7aaad'; ?>;">
						<?php echo esc_html( $plugin['name'] ); ?>
					</h3>
				</div>

				<!-- Description -->
				<p style="color: #50575e; font-size: 13px; margin: 0 0 16px 0; line-height: 1.5;">
					<?php echo esc_html( $plugin['description'] ); ?>
				</p>

				<!-- Toggle -->
				<?php if ( $is_installed ) : ?>
					<label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
						<input
							type="checkbox"
							name="conversations_enabled_plugins[]"
							value="<?php echo esc_attr( $plugin_key ); ?>"
							class="ghl-conversations-toggle"
							data-plugin="<?php echo esc_attr( $plugin_key ); ?>"
							<?php checked( $is_enabled ); ?>
						/>
						<span style="font-size: 13px; font-weight: 500;">
							<?php esc_html_e( 'Enable conversation sync', 'ghl-crm-integration' ); ?>
						</span>
					</label>
				<?php else : ?>
					<p style="color: #a7aaad; font-size: 12px; font-style: italic; margin: 0;">
						<?php
						printf(
							/* translators: %s: Plugin documentation URL */
							esc_html__( 'Install and activate this plugin to enable sync. %s', 'ghl-crm-integration' ),
							sprintf(
								'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
								esc_url( $plugin['docs_url'] ),
								esc_html__( 'Learn more →', 'ghl-crm-integration' )
							)
						);
						?>
					</p>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>

	<!-- Save Button -->
	<div style="margin: 20px 0; padding: 20px 0; border-top: 1px solid #c3c4c7;">
		<button type="button" id="ghl-save-conversations-settings" class="button button-primary" style="min-width: 150px; height: 36px;">
			<span class="dashicons dashicons-saved" style="margin-top: 3px;"></span>
			<?php esc_html_e( 'Save Settings', 'ghl-crm-integration' ); ?>
		</button>
		<span id="ghl-conversations-save-status" style="margin-left: 10px; display: none;"></span>
	</div>
</div>

<script>
(function($) {
	'use strict';

	$('#ghl-save-conversations-settings').on('click', function() {
		var $btn = $(this);
		var $status = $('#ghl-conversations-save-status');
		var enabledPlugins = [];

		$('.ghl-conversations-toggle:checked').each(function() {
			enabledPlugins.push($(this).data('plugin'));
		});

		$btn.prop('disabled', true).text('<?php echo esc_js( __( 'Saving...', 'ghl-crm-integration' ) ); ?>');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'ghl_crm_save_conversations_settings',
				nonce: '<?php echo esc_js( wp_create_nonce( 'ghl_crm_conversations_settings' ) ); ?>',
				enabled_plugins: enabledPlugins
			},
			success: function(response) {
				if (response.success) {
					$status.html('<span style="color: #00a32a;">✓ ' + response.data.message + '</span>').show();
				} else {
					$status.html('<span style="color: #d63638;">✗ ' + (response.data.message || '<?php echo esc_js( __( 'Save failed', 'ghl-crm-integration' ) ); ?>') + '</span>').show();
				}
			},
			error: function() {
				$status.html('<span style="color: #d63638;">✗ <?php echo esc_js( __( 'Network error', 'ghl-crm-integration' ) ); ?></span>').show();
			},
			complete: function() {
				$btn.prop('disabled', false).html('<span class="dashicons dashicons-saved" style="margin-top: 3px;"></span> <?php echo esc_js( __( 'Save Settings', 'ghl-crm-integration' ) ); ?>');
				setTimeout(function() { $status.fadeOut(); }, 4000);
			}
		});
	});
})(jQuery);
</script>
