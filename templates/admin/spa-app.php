<?php
/**
 * SPA Application Container Template
 *
 * This template provides the container for the Single Page Application.
 * All content is loaded dynamically via JavaScript and AJAX.
 *
 * @package    GHL_CRM_Integration
 * @subpackage GHL_CRM_Integration/templates/admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap ghl-crm-wrap">
	<!-- <h1 class="wp-heading-inline"><?php esc_html_e( 'GoHighLevel CRM Integration', 'ghl-crm-integration' ); ?></h1> -->
	<hr class="wp-header-end">

	<!-- Horizontal Header Navigation -->
	<div class="ghl-header-nav">
		<nav class="ghl-nav-tabs">
			<a href="#/" class="ghl-nav-tab" data-route="dashboard">
				<span class="dashicons dashicons-dashboard"></span>
				<span class="ghl-nav-label"><?php esc_html_e( 'Dashboard', 'ghl-crm-integration' ); ?></span>
			</a>
			<a href="#/settings" class="ghl-nav-tab" data-route="settings">
				<span class="dashicons dashicons-admin-settings"></span>
				<span class="ghl-nav-label"><?php esc_html_e( 'Settings', 'ghl-crm-integration' ); ?></span>
			</a>
			<a href="#/integrations" class="ghl-nav-tab" data-route="integrations">
				<span class="dashicons dashicons-admin-plugins"></span>
				<span class="ghl-nav-label"><?php esc_html_e( 'Integrations', 'ghl-crm-integration' ); ?></span>
			</a>
			<a href="#/field-mapping" class="ghl-nav-tab" data-route="field-mapping">
				<span class="dashicons dashicons-admin-generic"></span>
				<span class="ghl-nav-label"><?php esc_html_e( 'Field Mapping', 'ghl-crm-integration' ); ?></span>
			</a>
			<a href="#/sync-logs" class="ghl-nav-tab" data-route="sync-logs">
				<span class="dashicons dashicons-list-view"></span>
				<span class="ghl-nav-label"><?php esc_html_e( 'Sync Logs', 'ghl-crm-integration' ); ?></span>
			</a>
			<a href="#/forms" class="ghl-nav-tab" data-route="forms">
				<span class="dashicons dashicons-feedback"></span>
				<span class="ghl-nav-label"><?php esc_html_e( 'Forms', 'ghl-crm-integration' ); ?></span>
			</a>
			<a href="#/custom-objects" class="ghl-nav-tab" data-route="custom-objects">
				<span class="dashicons dashicons-block-default"></span>
				<span class="ghl-nav-label"><?php esc_html_e( 'Custom Objects', 'ghl-crm-integration' ); ?></span>
			</a>
		</nav>
	</div>

	<!-- SPA Application Container -->
	<div id="ghl-crm-app" class="ghl-spa-container">
		<div class="ghl-spa-loading">
			<div class="ghl-loading-spinner"></div>
			<p><?php esc_html_e( 'Loading...', 'ghl-crm-integration' ); ?></p>
		</div>
	</div>
</div>

<!-- Pass data to JavaScript -->
<script type="text/javascript">
	var ghlCrmSpaConfig = {
		ajaxUrl: <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,
		nonce: <?php echo wp_json_encode( wp_create_nonce( 'ghl_crm_spa_nonce' ) ); ?>,
		strings: {
			loading: <?php echo wp_json_encode( __( 'Loading...', 'ghl-crm-integration' ) ); ?>,
			error: <?php echo wp_json_encode( __( 'Error loading view. Please refresh the page.', 'ghl-crm-integration' ) ); ?>,
			notFound: <?php echo wp_json_encode( __( 'Page not found.', 'ghl-crm-integration' ) ); ?>
		},
		settings: {
			tabs: <?php echo wp_json_encode( \GHL_CRM\Core\MenuManager::get_valid_settings_tabs() ); ?>,
			routes: {
				dashboard: 'dashboard',
				settings: 'settings',
				integrations: 'integrations',
				fieldMapping: 'field-mapping',
				syncLogs: 'sync-logs'
			}
		}
	};
</script>
