<?php
/**
 * Forms Management Template
 *
 * @package GHL_CRM_Integration
 */

defined( 'ABSPATH' ) || exit;

// Get settings
$settings_manager   = \GHL_CRM\Core\SettingsManager::get_instance();
$settings           = $settings_manager->get_settings_array();
$white_label_domain = $settings['ghl_white_label_domain'] ?? '';
?>

<div class="ghl-forms-container">
	<!-- Connection Check -->
	<?php
	$oauth_handler = new \GHL_CRM\API\OAuth\OAuthHandler();
	$oauth_status  = $oauth_handler->get_connection_status();
	$is_connected  = $oauth_status['connected'] || ! empty( $settings['api_token'] );
	
	if ( ! $is_connected ) :
		?>
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
		<?php
		return;
	endif;

	// Check scope access for Forms
	\GHL_CRM\Core\ScopeChecker::render_scope_notice( 'forms' );
	
	// Helpful Information Notice
	?>
	<div class="notice notice-info" style="margin: 20px 0; padding: 15px; border-left-color: #2271b1;">
		<h3 style="margin-top: 0;">
			<span class="dashicons dashicons-info" style="color: #2271b1;"></span>
			<?php esc_html_e( 'How to Use Forms', 'ghl-crm-integration' ); ?>
		</h3>
		<p>
			<?php esc_html_e( 'Embed your GoHighLevel forms anywhere on your WordPress site using shortcodes or the block editor.', 'ghl-crm-integration' ); ?>
		</p>
		<ul style="list-style: disc; margin-left: 20px;">
			<li>
				<strong><?php esc_html_e( 'Copy Shortcode:', 'ghl-crm-integration' ); ?></strong> 
				<?php esc_html_e( 'Click "Copy Shortcode" on any form below and paste it into any page, post, or widget.', 'ghl-crm-integration' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Use in Block Editor:', 'ghl-crm-integration' ); ?></strong> 
				<?php esc_html_e( 'Add a "Shortcode" block and paste the shortcode to embed the form.', 'ghl-crm-integration' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Use in Classic Editor:', 'ghl-crm-integration' ); ?></strong> 
				<?php esc_html_e( 'Simply paste the shortcode directly into your content.', 'ghl-crm-integration' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Use in PHP Templates:', 'ghl-crm-integration' ); ?></strong> 
				<?php esc_html_e( 'Use do_shortcode() function in your theme files.', 'ghl-crm-integration' ); ?>
			</li>
		</ul>
		<p style="margin-bottom: 0;">
			<strong><?php esc_html_e( 'Note:', 'ghl-crm-integration' ); ?></strong> 
			<?php esc_html_e( 'Form submissions go directly to GoHighLevel. You can manage submissions and automations in your GHL account.', 'ghl-crm-integration' ); ?>
		</p>
	</div>
	<?php
	
	// White Label Domain Notice
	if ( empty( $white_label_domain ) ) :
		?>
		<div class="ghl-info-banner">
			<span class="dashicons dashicons-info"></span>
			<div class="ghl-info-banner-content">
				<strong><?php esc_html_e( 'White Label Domain', 'ghl-crm-integration' ); ?></strong>
				<p>
					<?php
					printf(
						/* translators: %s: Link to settings page */
						esc_html__( 'Using a white label domain? Configure it in %s to ensure form embeds use your custom domain.', 'ghl-crm-integration' ),
						sprintf(
							'<a href="%s">%s</a>',
							esc_url( admin_url( 'admin.php?page=ghl-crm-admin&tab=settings#/settings' ) ),
							esc_html__( 'General Settings', 'ghl-crm-integration' )
						)
					);
					?>
				</p>
			</div>
		</div>
		<?php
	endif;
	?>

	<!-- Toolbar -->
	<div class="ghl-forms-toolbar">
		<button type="button" id="ghl-refresh-forms" class="ghl-button ghl-button-secondary">
			<span class="dashicons dashicons-update"></span>
			<?php esc_html_e( 'Refresh Forms', 'ghl-crm-integration' ); ?>
		</button>
	</div>

	<!-- Loading State -->
	<div id="ghl-forms-loading" class="ghl-loading-state" style="display: none;">
		<div class="spinner is-active"></div>
		<p><?php esc_html_e( 'Loading forms...', 'ghl-crm-integration' ); ?></p>
	</div>

	<!-- Error State -->
	<div id="ghl-forms-error" class="ghl-error-banner" style="display: none;">
		<span class="dashicons dashicons-warning"></span>
		<div class="ghl-error-banner-content">
			<strong><?php esc_html_e( 'Error', 'ghl-crm-integration' ); ?></strong>
			<p id="ghl-forms-error-message"></p>
		</div>
	</div>

	<!-- Forms Table -->
	<div id="ghl-forms-table-wrapper" class="ghl-forms-table-wrapper">
		<!-- Table will be loaded here via AJAX -->
	</div>
</div>
