<?php
/**
 * Template: Settings Page with Side Menu
 *
 * This template provides extensible settings tabs for developers.
 *
 * @package GHL_CRM_Integration
 *
 * @example Adding a custom settings tab:
 *
 * // Method 1: Using a callback function
 * add_filter( 'ghl_crm_settings_tabs', function( $tabs ) {
 *     $tabs['my_custom_tab'] = [
 *         'label'    => __( 'My Custom Tab', 'my-plugin' ),
 *         'icon'     => 'dashicons-admin-customizer',
 *         'callback' => 'my_custom_tab_callback',
 *         'requires_connection' => false,  // Optional: doesn't require GHL connection
 *         'capability' => 'edit_posts',    // Optional: custom capability requirement
 *     ];
 *     return $tabs;
 * });
 *
 * function my_custom_tab_callback( $current_tab, $tab_data, $settings ) {
 *     echo '<h2>My Custom Settings</h2>';
 *     echo '<p>Custom content here...</p>';
 * }
 *
 * // Method 2: Using a custom file
 * add_filter( 'ghl_crm_settings_tabs', function( $tabs ) {
 *     $tabs['my_file_tab'] = [
 *         'label' => __( 'My File Tab', 'my-plugin' ),
 *         'icon'  => 'dashicons-media-document',
 *         'file'  => plugin_dir_path( __FILE__ ) . 'my-custom-tab.php',
 *     ];
 *     return $tabs;
 * });
 *
 * @hook ghl_crm_settings_tabs         Filter to add custom settings tabs
 * @hook ghl_crm_before_settings_tab_content  Action fired before tab content
 * @hook ghl_crm_after_settings_tab_content   Action fired after tab content
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
	'general'              => [
		'label' => __( 'General', 'syncly' ),
		'icon'  => 'dashicons-admin-generic',
	],
	'restrictions-manager' => [
		'label' => __( 'Restrictions Manager', 'syncly' ),
		'icon'  => 'dashicons-lock',
	],
	'rest-api'             => [
		'label' => __( 'REST API', 'syncly' ),
		'icon'  => 'dashicons-editor-code',
	],
	'webhooks'             => [
		'label' => __( 'Webhooks', 'syncly' ),
		'icon'  => 'dashicons-admin-links',
	],
	'notifications'        => [
		'label' => __( 'Email Notifications', 'syncly' ),
		'icon'  => 'dashicons-email',
	],
	// 'sync-options' => [
	// 'label' => __( 'Sync Options', 'syncly' ),
	// 'icon'  => 'dashicons-update',
	// ],
	'role-tags'            => [
		'label' => __( 'Role-Based Tags', 'syncly' ),
		'icon'  => 'dashicons-tag',
	],
	'family-relationships' => [
		'label' => __( 'Family Relationships', 'syncly' ),
		'icon'  => 'dashicons-groups',
	],
	'sync-preview'         => [
		'label' => __( 'Sync Preview', 'syncly' ),
		'icon'  => 'dashicons-visibility',
	],
	'login-sync'           => [
		'label' => __( 'Login Sync', 'syncly' ),
		'icon'  => 'dashicons-shield-alt',
	],
	'personalization'      => [
		'label' => __( 'Personalization', 'syncly' ),
		'icon'  => 'dashicons-email-alt',
	],
	// 'conversations' => [
	// 'label' => __( 'Conversations', 'syncly' ),
	// 'icon'  => 'dashicons-format-chat',
	// ],
	'advanced'             => [
		'label' => __( 'Advanced', 'syncly' ),
		'icon'  => 'dashicons-admin-tools',
	],
	'tools'                => [
		'label' => __( 'Tools', 'syncly' ),
		'icon'  => 'dashicons-admin-settings',
	],
	'stats'                => [
		'label' => __( 'System Status', 'syncly' ),
		'icon'  => 'dashicons-info',
	],
];

/**
 * Allow developers to add custom settings tabs
 *
 * @since 1.0.0
 * @param array $settings_tabs Array of settings tabs
 * @param bool  $is_connected  Whether the plugin is connected to GoHighLevel
 * @param array $settings      Current plugin settings
 *
 * @example
 * add_filter( 'ghl_crm_settings_tabs', function( $tabs, $is_connected, $settings ) {
 *     $tabs['my_custom_tab'] = [
 *         'label'    => __( 'My Custom Tab', 'my-plugin' ),
 *         'icon'     => 'dashicons-admin-customizer',
 *         'callback' => 'my_custom_tab_callback', // Optional: custom callback function
 *         'file'     => '/path/to/my/custom/tab.php', // Optional: custom file path
 *         'requires_connection' => true, // Optional: whether tab requires GHL connection (default: true)
 *         'capability' => 'manage_options', // Optional: required capability (default: manage_options)
 *     ];
 *     return $tabs;
 * }, 10, 3 );
 */
$settings_tabs = apply_filters( 'ghl_crm_settings_tabs', $settings_tabs, $is_connected, $settings );

foreach ( $settings_tabs as $tab_key => $tab_data ) {
	if ( empty( $tab_data['pro'] ) ) {
		continue;
	}

	$feature_enabled = ! empty( $tab_data['pro_filter'] ) && apply_filters( $tab_data['pro_filter'], false );
	if ( ! $feature_enabled ) {
		unset( $settings_tabs[ $tab_key ] );
	}
}

if ( ! isset( $settings_tabs[ $current_tab ] ) ) {
	$current_tab = 'general';
}

?>
<div class="wrap ghl-crm-settings">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	
	<?php if ( ! $is_connected ) : ?>
		<?php if ( 'general' === $current_tab ) : ?>
			<div class="notice notice-info">
				<p>
					<strong><?php esc_html_e( 'Not Connected', 'syncly' ); ?></strong><br>
					<?php esc_html_e( 'Please configure your connection settings below. Other settings tabs will be available once connected.', 'syncly' ); ?>
				</p>
			</div>
		<?php else : ?>
			<div class="notice notice-warning">
				<p>
					<strong><?php esc_html_e( 'Not Connected', 'syncly' ); ?></strong><br>
					<?php
					printf(
						/* translators: %s: Link to dashboard page */
						esc_html__( 'Please connect to GoHighLevel in %s first.', 'syncly' ),
						sprintf(
							'<a href="%s">%s</a>',
							esc_url( admin_url( 'admin.php?page=ghl-crm-admin' ) ),
							esc_html__( 'Dashboard', 'syncly' )
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
					// Check if tab requires connection (default: true for non-general tabs)
					$requires_connection = $tab_data['requires_connection'] ?? ( 'general' !== $tab_key );

					// Check if user has required capability (default: manage_options)
					$required_capability = $tab_data['capability'] ?? 'manage_options';
					$has_capability      = current_user_can( $required_capability );

					// Disable tab if connection required but not connected, or user lacks capability
					$is_disabled = ( $requires_connection && ! $is_connected ) || ! $has_capability;

					$li_class = $current_tab === $tab_key ? 'active' : '';
					if ( $is_disabled ) {
						$li_class .= ' disabled';
					}

					$disabled_title = '';
					if ( ! $has_capability ) {
						$disabled_title = __( 'Insufficient permissions', 'syncly' );
					} elseif ( $requires_connection && ! $is_connected ) {
						$disabled_title = __( 'Connect to GoHighLevel first', 'syncly' );
					}
					?>
					<li class="<?php echo esc_attr( $li_class ); ?>" data-tab="<?php echo esc_attr( $tab_key ); ?>" <?php echo $is_disabled ? 'title="' . esc_attr( $disabled_title ) . '"' : ''; ?>>
						<span class="dashicons <?php echo esc_attr( $tab_data['icon'] ); ?>"></span>
						<span class="ghl-tab-label">
							<?php echo esc_html( $tab_data['label'] ); ?>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
		</nav>

		<!-- Mobile Menu Toggle Button -->
		<button type="button" class="ghl-settings-menu-toggle" id="ghl-menu-toggle" aria-label="<?php esc_attr_e( 'Toggle settings menu', 'syncly' ); ?>">
			<span class="dashicons dashicons-menu"></span>
			<span class="dashicons dashicons-no-alt"></span>
		</button>

		<!-- Settings Content Area -->
		<div class="ghl-settings-content">
			<?php
			// Check if current tab exists in settings tabs
			if ( ! isset( $settings_tabs[ $current_tab ] ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Settings tab not found.', 'syncly' ) . '</p></div>';
			} else {
				$tab_data = $settings_tabs[ $current_tab ];

				// Check if user has required capability
				$required_capability = $tab_data['capability'] ?? 'manage_options';
				if ( ! current_user_can( $required_capability ) ) {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'You do not have permission to access this settings tab.', 'syncly' ) . '</p></div>';
				} else {
					// Fire action before rendering tab content
					do_action( 'ghl_crm_before_settings_tab_content', $current_tab, $tab_data, $settings );

					// Check if tab has a custom callback
					if ( isset( $tab_data['callback'] ) && is_callable( $tab_data['callback'] ) ) {
						// Call custom callback function
						call_user_func( $tab_data['callback'], $current_tab, $tab_data, $settings );
					} elseif ( isset( $tab_data['file'] ) && file_exists( $tab_data['file'] ) ) {
						// Include custom file
						include $tab_data['file'];
					} else {
						// Default: try to include standard partial file
						$partial_file = GHL_CRM_PATH . 'templates/admin/partials/settings/' . $current_tab . '.php';
						if ( file_exists( $partial_file ) ) {
							include $partial_file;
						} else {
							echo '<div class="notice notice-error"><p>' . esc_html__( 'Settings tab content not found.', 'syncly' ) . '</p></div>';
						}
					}

					// Fire action after rendering tab content
					do_action( 'ghl_crm_after_settings_tab_content', $current_tab, $tab_data, $settings );
				}
			}
			?>
		</div>
	</div>
</div>