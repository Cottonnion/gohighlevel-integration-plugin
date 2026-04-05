<?php
/**
 * Template: Main Settings Page with Tabs
 *
 * Unified settings page with tabs for Settings, Integrations, and Field Mapping
 *
 * @package GHL_CRM_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get current tab
$current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'settings';

// Define tabs
$tabs = array(
	'settings'      => __( 'General Settings', 'ghl-crm-integration' ),
	'integrations'  => __( 'Integrations', 'ghl-crm-integration' ),
	'field-mapping' => __( 'Field Mapping', 'ghl-crm-integration' ),
);

// Get settings manager instance
$settings_manager = \GHL_CRM\Core\SettingsManager::get_instance();
$settings         = $settings_manager->get_settings_array();

// Get OAuth handler
$oauth_handler = new \GHL_CRM\API\OAuth\OAuthHandler();
$oauth_status  = $oauth_handler->get_connection_status();
$is_connected  = $oauth_status['connected'] || ! empty( $settings['api_token'] );

// Check for OAuth callback messages
$oauth_message = '';
$oauth_error   = '';
if ( isset( $_GET['oauth'] ) ) {
	if ( 'success' === $_GET['oauth'] ) {
		$oauth_message = __( 'Successfully connected to GoHighLevel!', 'ghl-crm-integration' );
	} elseif ( 'error' === $_GET['oauth'] && isset( $_GET['message'] ) ) {
		$oauth_error = sanitize_text_field( wp_unslash( $_GET['message'] ) );
	}
}

// Handle OAuth disconnect
if ( isset( $_POST['ghl_disconnect_oauth'] ) && check_admin_referer( 'ghl_disconnect_oauth', 'ghl_disconnect_nonce' ) ) {
	$oauth_handler->disconnect();
	wp_safe_redirect(
		add_query_arg(
			array(
				'page'  => 'ghl-crm-settings',
				'oauth' => 'disconnected',
			),
			admin_url( 'admin.php' )
		)
	);
	exit;
}

// Check for disconnect success message
if ( isset( $_GET['oauth'] ) && 'disconnected' === $_GET['oauth'] ) {
	$oauth_message = __( 'Successfully disconnected from GoHighLevel.', 'ghl-crm-integration' );
	// Refresh connection status
	$oauth_status = $oauth_handler->get_connection_status();
	$is_connected = $oauth_status['connected'] || ! empty( $settings['api_token'] );
}

?>
<div class="wrap ghl-crm-settings">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	
	<!-- Dynamic notification area -->
	<div id="ghl-settings-notice" style="display: none;"></div>
	
	<?php if ( $oauth_message ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html( $oauth_message ); ?></p>
		</div>
	<?php endif; ?>
	
	<?php if ( $oauth_error ) : ?>
		<div class="notice notice-error is-dismissible">
			<p><?php echo esc_html( $oauth_error ); ?></p>
		</div>
	<?php endif; ?>

	<?php
	/**
	 * Action hook: Display admin notices on settings page
	 *
	 * Use this hook to display custom notices from anywhere in the plugin.
	 *
	 * Example usage:
	 * add_action( 'ghl_crm_settings_notices', function() {
	 *     echo '<div class="notice notice-error is-dismissible"><p>Error message here</p></div>';
	 * });
	 *
	 * @since 1.0.0
	 */
	do_action( 'ghl_crm_settings_notices' );
	?>

	<?php if ( ! $is_connected && 'settings' !== $current_tab ) : ?>
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

	<!-- Tab Navigation -->
	<nav class="nav-tab-wrapper wp-clearfix" style="margin-bottom: 20px;">
		<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
			<a href="
			<?php
			echo esc_url(
				add_query_arg(
					array(
						'page' => 'ghl-crm-settings',
						'tab'  => $tab_key,
					),
					admin_url( 'admin.php' )
				)
			);
			?>
						" 
				class="nav-tab <?php echo $current_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html( $tab_label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<!-- Tab Content -->
	<div class="ghl-tab-content">
		<?php
		switch ( $current_tab ) {
			case 'settings':
				// Use existing settings template content (skip wrapping div and h1)
				// Variables needed by settings.php are already set above
				?>
				<?php
				// Include just the content part of settings template
				include GHL_CRM_PATH . 'templates/admin/settings.php';
				break;

			case 'integrations':
				// Include integrations tab
				include GHL_CRM_PATH . 'templates/admin/integrations.php';
				break;

			case 'field-mapping':
				// Include just the content part of field-mapping template
				include GHL_CRM_PATH . 'templates/admin/field-mapping.php';
				break;

			default:
				include GHL_CRM_PATH . 'templates/admin/settings.php';
				break;
		}
		?>
	</div>
</div>