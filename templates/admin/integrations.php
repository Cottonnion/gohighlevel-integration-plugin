<?php
/**
 * Integrations Settings Template
 *
 * @package    GHL_CRM_Integration
 * @subpackage Templates/Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get current settings
$settings_manager = \GHL_CRM\Core\SettingsManager::get_instance();
$settings         = $settings_manager->get_settings_array();

// Check connection status
$oauth_handler = new \GHL_CRM\API\OAuth\OAuthHandler();
$oauth_status  = $oauth_handler->get_connection_status();
$is_connected  = $oauth_status['connected'] || ! empty( $settings['api_token'] );
?>

<div class="wrap ghl-crm-wrap">
	<h1 class="ghl-page-title">
		<?php echo esc_html( get_admin_page_title() ); ?>
	</h1>

	<?php if ( ! $is_connected ) : ?>
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

	<?php
	// Check scope access for integrations
	\GHL_CRM\Core\ScopeChecker::render_scope_notice( 'contacts' );
	\GHL_CRM\Core\ScopeChecker::render_scope_notice( 'tags' );
	?>

	<!-- Helpful Information Notice -->
	<div class="notice notice-info" style="margin: 20px 0; padding: 15px; border-left-color: #2271b1;">
		<h3 style="margin-top: 0;">
			<span class="dashicons dashicons-info" style="color: #2271b1;"></span>
			<?php esc_html_e( 'About Integration Settings', 'ghl-crm-integration' ); ?>
		</h3>
		<p>
			<?php esc_html_e( 'Control how WordPress integrates with GoHighLevel for WooCommerce, BuddyBoss, and other platforms.', 'ghl-crm-integration' ); ?>
		</p>
		<ul style="list-style: disc; margin-left: 20px;">
			<li>
				<strong><?php esc_html_e( 'Master Toggle:', 'ghl-crm-integration' ); ?></strong> 
				<?php esc_html_e( 'Each integration has a master toggle at the top. Disabling it immediately stops ALL sync activities for that integration.', 'ghl-crm-integration' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Individual Settings:', 'ghl-crm-integration' ); ?></strong> 
				<?php esc_html_e( 'Fine-tune which events trigger syncs (orders, groups, courses, etc.) and configure tags for automation.', 'ghl-crm-integration' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Tags:', 'ghl-crm-integration' ); ?></strong> 
				<?php esc_html_e( 'Assign tags to contacts based on WordPress events. These tags can trigger workflows in GoHighLevel.', 'ghl-crm-integration' ); ?>
			</li>
		</ul>
		<p style="margin-bottom: 0;">
			<strong><?php esc_html_e( 'Important:', 'ghl-crm-integration' ); ?></strong> 
			<?php esc_html_e( 'When you disable a master toggle, all related sync operations stop immediately. Existing data remains in GoHighLevel but no new syncs will occur until you re-enable it.', 'ghl-crm-integration' ); ?>
		</p>
	</div>

	<!-- Success/Error Messages -->
	<div id="ghl-integrations-messages"></div>

	   <!-- Tabs Navigation -->
	   <div class="ghl-tabs-nav">
		   <?php
		   $default_tabs = [
			   [
				   'id'    => 'buddyboss',
				   'icon'  => 'groups',
				   'label' => __( 'BuddyBoss', 'ghl-crm-integration' ),
				   'active' => true,
				   'order'  => 10,
			   ],
			   [
				   'id'    => 'learndash',
				   'icon'  => 'welcome-learn-more',
				   'label' => __( 'LearnDash', 'ghl-crm-integration' ),
				   'active' => false,
				   'order'  => 20,
			   ],
		   ];
		   $integration_tabs = apply_filters( 'ghl_crm_integration_tabs', $default_tabs );
		   // Sort by 'order' if present
		   usort($integration_tabs, function($a, $b) {
			   return ($a['order'] ?? 100) <=> ($b['order'] ?? 100);
		   });
		   foreach ( $integration_tabs as $tab ) :
			   $active_class = ! empty( $tab['active'] ) ? 'active' : '';
			   ?>
			   <button class="ghl-tab-button <?php echo esc_attr( $active_class ); ?>" data-tab="<?php echo esc_attr( $tab['id'] ); ?>">
				   <span class="dashicons dashicons-<?php echo esc_attr( $tab['icon'] ); ?>"></span>
				   <?php echo esc_html( $tab['label'] ); ?>
			   </button>
		   <?php endforeach; ?>
	   </div>

	   <!-- Tabs Content -->
	   <div class="ghl-tabs-content">
		   <?php
		   $default_panels = [
			   [
				   'id'      => 'buddyboss',
				   'active'  => true,
				   'order'   => 10,
				   'content' => function() {
					   $buddyboss_template = GHL_CRM_PATH . 'templates/admin/partials/integrations/buddyboss-groups.php';
					   if ( file_exists( $buddyboss_template ) ) {
						   include $buddyboss_template;
					   } else {
						   echo '<div class="notice notice-error"><p>' . esc_html__( 'BuddyBoss settings template not found.', 'ghl-crm-integration' ) . '</p></div>';
					   }
				   },
			   ],
			   [
				   'id'      => 'learndash',
				   'active'  => false,
				   'order'   => 20,
				   'content' => function() {
					   require GHL_CRM_PATH . 'templates/admin/partials/integrations/learndash.php';
				   },
			   ],
		   ];
		   $integration_panels = apply_filters( 'ghl_crm_integration_panels', $default_panels );
		   // Sort by 'order' if present
		   usort($integration_panels, function($a, $b) {
			   return ($a['order'] ?? 100) <=> ($b['order'] ?? 100);
		   });
		   foreach ( $integration_panels as $panel ) :
			   $active_class = ! empty( $panel['active'] ) ? 'active' : '';
			   ?>
			   <div class="ghl-tab-panel <?php echo esc_attr( $active_class ); ?>" data-tab="<?php echo esc_attr( $panel['id'] ); ?>">
				   <?php if ( is_callable( $panel['content'] ) ) {
					   call_user_func( $panel['content'] );
				   } else {
					   echo $panel['content'];
				   } ?>
			   </div>
		   <?php endforeach; ?>
	   </div>

	<!-- Save Button -->
	<div class="ghl-form-actions">
		<button type="button" id="save-integrations-settings" class="ghl-button ghl-button-primary button-large">
			<span class="button-text"><?php esc_html_e( 'Save Integration Settings', 'ghl-crm-integration' ); ?></span>
			<span class="spinner"></span>
		</button>
	</div>
</div>