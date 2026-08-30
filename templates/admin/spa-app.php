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
			 * @since 1.0.0
			 * @param array  $nav_tabs Array of navigation tabs with keys: route => array(label, icon, mode)
			 * @param string $ui_mode  Current display mode: 'simple' or 'advanced'
			 *
			 * @example
			 * add_filter( 'syncly_admin_nav_tabs', function( $tabs, $ui_mode ) {
			 *     // Add a tab that only shows in Advanced mode.
			 *     if ( 'advanced' === $ui_mode ) {
			 *         $tabs['automations'] = [
			 *             'label' => __( 'Automations', 'my-plugin' ),
			 *             'icon'  => 'dashicons-admin-generic',
			 *             'mode'  => 'advanced', // Optional: auto-hidden in Simple mode
			 *         ];
			 *     }
			 *     return $tabs;
			 * }, 10, 2 );
			 */
			$ui_mode    = \Syncly\Core\UiModeManager::get_mode();
			$nav_tabs   = apply_filters( 'syncly_admin_nav_tabs', $nav_tabs, $ui_mode );

			// Hide advanced-only views (e.g. Sync Logs) in simple mode.
			$nav_tabs = \Syncly\Core\UiModeManager::filter_nav_tabs( $nav_tabs );

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

		<!-- Simple / Advanced display mode toggle -->
		<div class="ghl-ui-mode-toggle">
			<span class="ghl-ui-mode-caption">
				<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
				<?php esc_html_e( 'View:', 'syncly' ); ?>
			</span>
			<div class="ghl-ui-mode-segment" role="group" aria-label="<?php esc_attr_e( 'Dashboard display mode', 'syncly' ); ?>">
				<button
					type="button"
					class="ghl-ui-mode-btn<?php echo \Syncly\Core\UiModeManager::MODE_SIMPLE === $ui_mode ? ' is-active' : ''; ?>"
					data-mode="<?php echo esc_attr( \Syncly\Core\UiModeManager::MODE_SIMPLE ); ?>"
					aria-pressed="<?php echo \Syncly\Core\UiModeManager::MODE_SIMPLE === $ui_mode ? 'true' : 'false'; ?>"
					title="<?php esc_attr_e( 'Show the essentials only', 'syncly' ); ?>"
				>
					<?php esc_html_e( 'Simple', 'syncly' ); ?>
				</button>
				<button
					type="button"
					class="ghl-ui-mode-btn<?php echo \Syncly\Core\UiModeManager::MODE_ADVANCED === $ui_mode ? ' is-active' : ''; ?>"
					data-mode="<?php echo esc_attr( \Syncly\Core\UiModeManager::MODE_ADVANCED ); ?>"
					aria-pressed="<?php echo \Syncly\Core\UiModeManager::MODE_ADVANCED === $ui_mode ? 'true' : 'false'; ?>"
					title="<?php esc_attr_e( 'Show all settings and sync logs', 'syncly' ); ?>"
				>
					<?php esc_html_e( 'Advanced', 'syncly' ); ?>
				</button>
			</div>
		</div>
	</div>

	<!-- Upgrade Notice (dismissible banner) -->
	<?php
	$admin_notices = \Syncly\Core\AdminNotices::get_instance();
	$admin_notices->render_upgrade_notice();
	?>

	<!-- Review Notice (dismissible banner) -->
	<?php $admin_notices->render_review_notice(); ?>

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
			'uiMode'   => [
				'mode'    => $ui_mode,
				'nonce'   => wp_create_nonce( 'syncly_ui_mode' ),
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'strings' => [
					'error' => __( 'Could not switch UI mode. Please refresh the page and try again.', 'syncly' ),
				],
			],
		]
	) . ';',
	'before'
);
?>