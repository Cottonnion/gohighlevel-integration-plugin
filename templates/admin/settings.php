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
if ( isset( $_GET['settings_tab'] ) ) {
	$current_tab = sanitize_text_field( $_GET['settings_tab'] );
} elseif ( isset( $_POST['settings_tab'] ) ) {
	$current_tab = sanitize_text_field( $_POST['settings_tab'] );
}

// Define available settings tabs
$settings_tabs = [
	'general' => [
		'label' => __( 'General', 'ghl-crm-integration' ),
		'icon'  => 'dashicons-admin-generic',
	],
	'api' => [
		'label' => __( 'API Configuration', 'ghl-crm-integration' ),
		'icon'  => 'dashicons-admin-network',
	],
	'rest-api' => [
		'label' => __( 'REST API', 'ghl-crm-integration' ),
		'icon'  => 'dashicons-rest-api',
	],
	'webhooks' => [
		'label' => __( 'Webhooks', 'ghl-crm-integration' ),
		'icon'  => 'dashicons-randomize',
	],
	'notifications' => [
		'label' => __( 'Email Notifications', 'ghl-crm-integration' ),
		'icon'  => 'dashicons-email-alt',
	],
	'field-sync' => [
		'label' => __( 'Field Sync', 'ghl-crm-integration' ),
		'icon'  => 'dashicons-update',
	],
	'contact-fields' => [
		'label' => __( 'Custom Contact Fields', 'ghl-crm-integration' ),
		'icon'  => 'dashicons-id',
	],
	'role-tags' => [
		'label' => __( 'Role Based Tags', 'ghl-crm-integration' ),
		'icon'  => 'dashicons-tag',
	],
	'advanced' => [
		'label' => __( 'Advanced', 'ghl-crm-integration' ),
		'icon'  => 'dashicons-admin-tools',
	],
];
?>
<div class="wrap ghl-crm-settings">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	
	<div class="ghl-settings-with-sidebar">
		<!-- Settings Side Menu -->
		<nav class="ghl-settings-nav">
			<ul>
				<?php foreach ( $settings_tabs as $tab_key => $tab_data ) : ?>
					<li class="<?php echo $current_tab === $tab_key ? 'active' : ''; ?>">
						<a href="#<?php echo esc_attr( $tab_key ); ?>" data-tab="<?php echo esc_attr( $tab_key ); ?>">
							<span class="dashicons <?php echo esc_attr( $tab_data['icon'] ); ?>"></span>
							<?php echo esc_html( $tab_data['label'] ); ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</nav>

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
