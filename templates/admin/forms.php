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
	<!-- Page Header -->
	<div class="ghl-page-header">
		<h1>
			<span class="dashicons dashicons-media-document"></span>
			<?php esc_html_e( 'Forms Management', 'ghl-crm-integration' ); ?>
		</h1>
		<p class="description">
			<?php esc_html_e( 'Manage and embed GoHighLevel forms in your WordPress site using shortcodes.', 'ghl-crm-integration' ); ?>
		</p>
	</div>

	<!-- Connection Check -->
	<?php
	$oauth_handler = new \GHL_CRM\API\OAuth\OAuthHandler();
	$oauth_status  = $oauth_handler->get_connection_status();
	$is_connected  = $oauth_status['connected'] || ! empty( $settings['api_token'] );
	
	if ( ! $is_connected ) :
		?>
		<div class="ghl-settings-card" style="border-left: 4px solid #d63638;">
			<h2>
				<span class="dashicons dashicons-warning"></span>
				<?php esc_html_e( 'Not Connected', 'ghl-crm-integration' ); ?>
			</h2>
			<p>
				<?php
				printf(
					/* translators: %s: Link to dashboard page */
					esc_html__( 'Please connect to GoHighLevel in the %s to access your forms.', 'ghl-crm-integration' ),
					sprintf(
						'<a href="%s"><strong>%s</strong></a>',
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
