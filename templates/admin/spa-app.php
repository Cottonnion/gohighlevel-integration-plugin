<?php
/**
 * SPA Application Container Template
 *
 * This template provides the container for the Single Page Application.
 * All content is loaded dynamically via JavaScript and AJAX.
 *
 * @package    Syncly
 * @subpackage Syncly/templates/admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap syncly-wrap">
	<hr class="wp-header-end">

	<!-- Horizontal Header Navigation -->
	<div class="ghl-header-nav">
		<nav class="ghl-nav-tabs">
			<?php
			// Define default navigation tabs
			$nav_tabs = array(
				'dashboard'     => array(
					'label' => __( 'Dashboard', 'syncly' ),
					'icon'  => 'dashicons-dashboard',
				),
				'settings'      => array(
					'label' => __( 'Settings', 'syncly' ),
					'icon'  => 'dashicons-admin-settings',
				),
				'integrations'  => array(
					'label' => __( 'Integrations', 'syncly' ),
					'icon'  => 'dashicons-admin-plugins',
				),
				'field-mapping' => array(
					'label' => __( 'Field Mapping', 'syncly' ),
					'icon'  => 'dashicons-admin-generic',
				),
				'sync-logs'     => array(
					'label' => __( 'Sync Logs', 'syncly' ),
					'icon'  => 'dashicons-list-view',
				),
				'forms'         => array(
					'label' => __( 'Forms', 'syncly' ),
					'icon'  => 'dashicons-feedback',
				),
			);

			/**
			 * Filter the admin navigation tabs.
			 * Allows extensions (like Pro plugin) to add custom tabs.
			 *
			 * @param array $nav_tabs Array of navigation tabs with keys: route => array(label, icon)
			 */
			$nav_tabs = apply_filters( 'syncly_admin_nav_tabs', $nav_tabs );

			// Render navigation tabs
			foreach ( $nav_tabs as $route => $tab_data ) {
				$href = ( $route === 'dashboard' ) ? '#/' : '#/' . esc_attr( $route );
				printf(
					'<a href="%s" class="ghl-nav-tab" data-route="%s">
						<span class="dashicons %s"></span>
						<span class="ghl-nav-label">%s</span>
					</a>',
					esc_url( $href ),
					esc_attr( $route ),
					esc_attr( $tab_data['icon'] ),
					esc_html( $tab_data['label'] )
				);
			}
			?>
		</nav>
	</div>

	<!-- Upgrade Notice (dismissible banner) -->
	<?php
	$admin_notices = \Syncly\Core\AdminNotices::get_instance();
	$admin_notices->render_upgrade_notice();
	?>

	<!-- SPA Application Container -->
	<div id="syncly-app" class="ghl-spa-container">
		<div class="ghl-spa-loading">
			<div class="ghl-loading-spinner"></div>
			<p><?php esc_html_e( 'Loading...', 'syncly' ); ?></p>
		</div>
	</div>
</div>

<?php
wp_add_inline_script(
	'syncly-spa-js',
	'var synclySpaConfig = ' . wp_json_encode(
		[
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'syncly_spa_nonce' ),
			'strings'  => [
				'loading'  => __( 'Loading...', 'syncly' ),
				'error'    => __( 'Error loading view. Please refresh the page.', 'syncly' ),
				'notFound' => __( 'Page not found.', 'syncly' ),
			],
			'settings' => [
				'tabs'   => \Syncly\Core\MenuManager::get_valid_settings_tabs(),
				'routes' => [
					'dashboard'    => 'dashboard',
					'settings'     => 'settings',
					'integrations' => 'integrations',
					'fieldMapping' => 'field-mapping',
					'syncLogs'     => 'sync-logs',
				],
			],
		]
	) . ';',
	'before'
);
?>