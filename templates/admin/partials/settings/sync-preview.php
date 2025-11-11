<?php
/**
 * Settings - Sync Preview Template
 *
 * Test sync preview tab content
 *
 * @package    GHL_CRM_Integration
 * @subpackage GHL_CRM_Integration/templates/admin/partials/settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings_manager = \GHL_CRM\Core\SettingsManager::get_instance();

// Get all WordPress users for the dropdown
$users = get_users( array(
	'orderby' => 'display_name',
	'order'   => 'ASC',
	'number'  => 500, // Limit to 500 users for performance
) );
?>

<div class="ghl-settings-wrapper">
	<?php wp_nonce_field( 'ghl_crm_settings_nonce', 'ghl_crm_nonce' ); ?>
	
	<!-- Sync Preview Section -->
	<div class="ghl-settings-section ghl-settings-card">
		<div class="ghl-settings-header">
			<h2>
				<span class="dashicons dashicons-visibility"></span>
				<?php esc_html_e( 'Sync Preview (Test Mode)', 'ghl-crm-integration' ); ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Preview what would happen if you sync a user to GoHighLevel without actually performing the sync. This is a "dry-run" test to see field changes, detect conflicts, and validate data before committing.', 'ghl-crm-integration' ); ?>
			</p>
		</div>
		
		<hr>
		
		<div class="ghl-form-builder">
			<form id="ghl-sync-preview-form" class="ghl-form" method="post">
				
			<!-- User Identifier Input -->
			<div class="ghl-form-item">
				<div class="ghl-form-item-content ghl-form-item-content--column">
					<label for="user_identifier" class="ghl-form-label">
						<?php esc_html_e( 'Select WordPress User', 'ghl-crm-integration' ); ?>
						<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Choose the WordPress user you want to preview. The preview will show you exactly what would happen if you sync this user to GoHighLevel.', 'ghl-crm-integration' ); ?>">?</span>
					</label>
					<select 
						id="user_identifier" 
						name="user_identifier" 
						class="ghl-input ghl-select2" 
						style="width: 100%;"
						required
					>
						<option value=""><?php esc_html_e( 'Choose a user...', 'ghl-crm-integration' ); ?></option>
						<?php foreach ( $users as $user ) : ?>
							<option value="<?php echo esc_attr( $user->user_email ); ?>" 
								data-id="<?php echo esc_attr( $user->ID ); ?>"
								data-login="<?php echo esc_attr( $user->user_login ); ?>"
								data-roles="<?php echo esc_attr( implode( ', ', $user->roles ) ); ?>">
								<?php echo esc_html( $user->display_name ); ?> 
								(<?php echo esc_html( $user->user_email ); ?>)
								<?php if ( ! empty( $user->roles ) ) : ?>
									- <?php echo esc_html( implode( ', ', array_map( 'ucfirst', $user->roles ) ) ); ?>
								<?php endif; ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>				<!-- Preview Button -->
				<div class="ghl-form-item">
					<button type="submit" id="ghl-preview-sync-btn" class="ghl-button ghl-button-primary">
						<span class="dashicons dashicons-search"></span>
						<?php esc_html_e( 'Preview Sync', 'ghl-crm-integration' ); ?>
					</button>
				</div>

			</form>
		</div>

		<!-- Preview Results Container -->
		<div id="ghl-preview-results" style="display: none; margin-top: 30px;">
			<h3><?php esc_html_e( 'Preview Results', 'ghl-crm-integration' ); ?></h3>
			<div id="ghl-preview-content"></div>
		</div>

	</div>

	<!-- Info Box -->
	<div class="ghl-info-box" style="background: #e7f3ff; border-left: 4px solid #2271b1; padding: 15px; margin-top: 20px;">
		<h4 style="margin-top: 0;">
			<span class="dashicons dashicons-info" style="color: #2271b1;"></span>
			<?php esc_html_e( 'About Sync Preview', 'ghl-crm-integration' ); ?>
		</h4>
		<ul style="margin: 10px 0 0 20px;">
			<li><?php esc_html_e( '✅ No data is modified - this is a read-only preview', 'ghl-crm-integration' ); ?></li>
			<li><?php esc_html_e( '✅ Shows exactly what fields will be synced', 'ghl-crm-integration' ); ?></li>
			<li><?php esc_html_e( '✅ Detects conflicts and validation errors', 'ghl-crm-integration' ); ?></li>
			<li><?php esc_html_e( '✅ See before/after values for all mapped fields', 'ghl-crm-integration' ); ?></li>
			<li><?php esc_html_e( '⚡ Perfect for testing field mappings and troubleshooting', 'ghl-crm-integration' ); ?></li>
		</ul>
	</div>

</div>