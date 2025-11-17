<?php
/**
 * Family Manager Shortcode Template
 *
 * @package    GHL_CRM_Integration
 * @subpackage Templates
 */

defined( 'ABSPATH' ) || exit;

$user_id = get_current_user_id();
$is_admin = current_user_can( 'manage_options' );
?>

<div class="ghl-family-manager" data-user-id="<?php echo esc_attr( $user_id ); ?>">
	<div class="ghl-family-header">
		<h2><?php esc_html_e( 'Family Account Manager', 'ghl-crm-integration' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Manage your family members and their access to your account.', 'ghl-crm-integration' ); ?>
		</p>
	</div>

	<?php if ( $is_admin ) : ?>
	<div class="ghl-family-admin-selector" style="margin-bottom: 24px; padding: 16px; background: #f0f9ff; border-radius: 8px;">
		<label for="ghl-parent-selector">
			<strong><?php esc_html_e( 'Admin: Select Parent Account', 'ghl-crm-integration' ); ?></strong>
		</label>
		<select id="ghl-parent-selector" style="width: 100%; max-width: 400px; margin-top: 8px;">
			<option value="<?php echo esc_attr( $user_id ); ?>"><?php esc_html_e( 'Your Account', 'ghl-crm-integration' ); ?></option>
			<!-- Populated via AJAX -->
		</select>
	</div>
	<?php endif; ?>

	<!-- Content -->
	<div class="ghl-family-content">
		<!-- Invite Form Section -->
		<div class="ghl-family-section ghl-invite-section" style="margin-bottom: 32px;">
			<h3><?php esc_html_e( 'Invite New Child', 'ghl-crm-integration' ); ?></h3>
			<p class="description" style="margin-bottom: 20px;">
				<?php esc_html_e( 'Enter an email address. If the user exists, they will be linked to your account. If not, a new account will be created and an invitation email will be sent.', 'ghl-crm-integration' ); ?>
			</p>
			<form id="ghl-link-child-form" style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
				<div class="form-field" style="flex: 1; min-width: 250px; margin-bottom: 0;">
					<input type="email" id="child-identifier" name="child_identifier" required placeholder="Enter email address" style="width: 100%;">
				</div>
				<button type="submit" class="button button-primary" style="white-space: nowrap;">
					<?php esc_html_e( 'Search & Invite', 'ghl-crm-integration' ); ?>
				</button>
			</form>
		</div>

		<!-- Children List -->
		<div class="ghl-family-section">
			<h3><?php esc_html_e( 'Linked Children', 'ghl-crm-integration' ); ?></h3>
			<div class="ghl-family-loading">
				<span class="spinner is-active"></span>
				<?php esc_html_e( 'Loading children...', 'ghl-crm-integration' ); ?>
			</div>
			
			<!-- Table wrapper for responsive design -->
			<div class="ghl-table-responsive">
				<div class="ghl-family-children-list"></div>
			</div>
		</div>
	</div>
</div>
