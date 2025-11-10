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

$is_connected = $oauth_status['connected'] || ! empty( $settings['api_token'] );
?>
<div class="ghl-crm-dashboard">
	<?php if ( $is_connected ) : ?>
		<!-- Connected - Show Reports & Analytics -->
		<?php include plugin_dir_path( __FILE__ ) . 'reports.php'; ?>
		
	<?php else : ?>
		<!-- Not Connected - Show Connection Setup -->
		<div class="ghl-card">
			<h2><?php esc_html_e( 'Connect to GoHighLevel', 'ghl-crm-integration' ); ?></h2>
			<p class="description" style="margin-bottom: 20px;">
				<?php esc_html_e( 'Choose your preferred connection method to get started. Both methods are secure and fully supported.', 'ghl-crm-integration' ); ?>
			</p>
			
			<?php include plugin_dir_path( __FILE__ ) . 'connection-setup.php'; ?>
		</div>
	<?php endif; ?>
</div>