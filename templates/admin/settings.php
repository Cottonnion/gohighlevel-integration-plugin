<?php
/**
 * Template: Settings Page with Side Menu
 *
 * @package GHL_CRM_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get current settings tab from query param or default to 'general'
// Check both $_GET and $_POST for AJAX compatibility
$current_tab = 'general';
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Tab selection via URL parameter doesn't require nonce
if ( isset( $_GET['settings_tab'] ) ) {
	$current_tab = sanitize_text_field( wp_unslash( $_GET['settings_tab'] ) );
}
// phpcs:enable WordPress.Security.NonceVerification.Recommended

// For POST requests (AJAX), verify nonce
if ( isset( $_POST['settings_tab'] ) && check_ajax_referer( 'ghl_crm_settings_nonce', 'nonce', false ) ) {
	$current_tab = sanitize_text_field( wp_unslash( $_POST['settings_tab'] ) );
}

// Check connection status
$settings_manager = \GHL_CRM\Core\SettingsManager::get_instance();
$settings         = $settings_manager->get_settings_array();
$oauth_handler    = new \GHL_CRM\API\OAuth\OAuthHandler();
$oauth_status     = $oauth_handler->get_connection_status();
$is_connected     = $oauth_status['connected'] || ! empty( $settings['api_token'] );

// Define available settings tabs
$settings_tabs = [
	'general' => [
		'label' => __( 'General', 'ghl-crm-integration' ),
		'icon'  => 'dashicons-admin-generic',
	],
	'restrictions-manager' => [
		'label' => __( 'Restrictions Manager', 'ghl-crm-integration' ),
		'icon'  => 'dashicons-lock',
	],
	'rest-api' => [
		'label' => __( 'REST API', 'ghl-crm-integration' ),
		'icon'  => 'dashicons-editor-code',
	],
	'webhooks' => [
		'label' => __( 'Webhooks', 'ghl-crm-integration' ),
		'icon'  => 'dashicons-admin-links',
	],
	'notifications' => [
		'label' => __( 'Email Notifications', 'ghl-crm-integration' ),
		'icon'  => 'dashicons-email',
	],
	'field-sync' => [
		'label' => __( 'Field Sync', 'ghl-crm-integration' ),
		'icon'  => 'dashicons-update',
	],
	'role-tags' => [
		'label' => __( 'Role-Based Tags', 'ghl-crm-integration' ),
		'icon'  => 'dashicons-tag',
	],
	'advanced' => [
		'label' => __( 'Advanced', 'ghl-crm-integration' ),
		'icon'  => 'dashicons-admin-tools',
	],
	'tools' => [
		'label' => __( 'Tools', 'ghl-crm-integration' ),
		'icon'  => 'dashicons-admin-settings',
	],
	'stats' => [
		'label' => __( 'System Status', 'ghl-crm-integration' ),
		'icon'  => 'dashicons-info',
	],
];

?>
<div class="wrap ghl-crm-settings">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	
	<?php if ( ! $is_connected ) : ?>
		<?php if ( 'general' === $current_tab ) : ?>
			<div class="notice notice-info">
				<p>
					<strong><?php esc_html_e( 'Not Connected', 'ghl-crm-integration' ); ?></strong><br>
					<?php esc_html_e( 'Please configure your connection settings below. Other settings tabs will be available once connected.', 'ghl-crm-integration' ); ?>
				</p>
			</div>
		<?php else : ?>
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
			<?php return; ?>
		<?php endif; ?>
	<?php endif; ?>
	
	<div class="ghl-settings-with-sidebar">
		<!-- Settings Side Menu -->
		<nav class="ghl-settings-nav" id="ghl-settings-nav">
			<ul>
				<?php foreach ( $settings_tabs as $tab_key => $tab_data ) : ?>
					<?php 
					// Disable non-general tabs when not connected
					$is_disabled = ! $is_connected && 'general' !== $tab_key;
					$li_class = $current_tab === $tab_key ? 'active' : '';
					if ( $is_disabled ) {
						$li_class .= ' disabled';
					}
					?>
					<li class="<?php echo esc_attr( $li_class ); ?>" data-tab="<?php echo esc_attr( $tab_key ); ?>" <?php echo $is_disabled ? 'title="' . esc_attr__( 'Connect to GoHighLevel first', 'ghl-crm-integration' ) . '"' : ''; ?>>
						<span class="dashicons <?php echo esc_attr( $tab_data['icon'] ); ?>"></span>
						<span class="ghl-tab-label"><?php echo esc_html( $tab_data['label'] ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</nav>

		<!-- Mobile Menu Toggle Button -->
		<button type="button" class="ghl-settings-menu-toggle" id="ghl-menu-toggle" aria-label="<?php esc_attr_e( 'Toggle settings menu', 'ghl-crm-integration' ); ?>">
			<span class="dashicons dashicons-menu"></span>
			<span class="dashicons dashicons-no-alt"></span>
		</button>

		<!-- Settings Content Area -->
		<div class="ghl-settings-content">
			<?php
			$partial_file = GHL_CRM_PATH . 'templates/admin/partials/settings/' . $current_tab . '.php';
			if ( file_exists( $partial_file ) ) {
				include $partial_file;
			} else {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Settings tab not found.', 'ghl-crm-integration' ) . '</p></div>';
			}
			?>
		</div>
	</div>
</div>
