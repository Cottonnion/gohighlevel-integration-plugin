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

// Define available settings tabs
$settings_tabs = [
	'general' => [
		'label' => __( 'General', 'ghl-crm-integration' ),
		'icon'  => 'fa-solid fa-gear',
	],
	'restrictions-manager' => [
		'label' => __( 'Restrictions Manager', 'ghl-crm-integration' ),
		'icon'  => 'fa-solid fa-lock',
	],
	'rest-api' => [
		'label' => __( 'REST API', 'ghl-crm-integration' ),
		'icon'  => 'fa-solid fa-code',
	],
	'webhooks' => [
		'label' => __( 'Webhooks', 'ghl-crm-integration' ),
		'icon'  => 'fa-solid fa-link',
	],
	'notifications' => [
		'label' => __( 'Email Notifications', 'ghl-crm-integration' ),
		'icon'  => 'fa-solid fa-envelope',
	],
	'field-sync' => [
		'label' => __( 'Field Sync', 'ghl-crm-integration' ),
		'icon'  => 'fa-solid fa-arrows-rotate',
	],
	'role-tags' => [
		'label' => __( 'Role Based Tags', 'ghl-crm-integration' ),
		'icon'  => 'fa-solid fa-tags',
	],
	'advanced' => [
		'label' => __( 'Advanced', 'ghl-crm-integration' ),
		'icon'  => 'fa-solid fa-screwdriver-wrench',
	],
	'tools' => [
		'label' => __( 'Tools', 'ghl-crm-integration' ),
		'icon'  => 'fa-solid fa-toolbox',
	],
	'stats' => [
		'label' => __( 'System Status', 'ghl-crm-integration' ),
		'icon'  => 'fa-solid fa-circle-info',
	],
];

?>
<div class="wrap ghl-crm-settings">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	
	<div class="ghl-settings-with-sidebar">
		<!-- Settings Side Menu -->
		<nav class="ghl-settings-nav" id="ghl-settings-nav">
			<ul>
				<?php foreach ( $settings_tabs as $tab_key => $tab_data ) : ?>
					<li class="<?php echo $current_tab === $tab_key ? 'active' : ''; ?>" data-tab="<?php echo esc_attr( $tab_key ); ?>">
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
