<?php
/**
 * Custom Objects Page
 *
 * Template for displaying and managing GoHighLevel Custom Objects
 *
 * @package    GHL_CRM_Integration
 * @subpackage Templates/Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use GHL_CRM\API\Resources\CustomObjectResource;
use GHL_CRM\API\Client\Client;

// Get connection status
$oauth_handler = new \GHL_CRM\API\OAuth\OAuthHandler();
$oauth_status  = $oauth_handler->get_connection_status();
$settings      = \GHL_CRM\Core\SettingsManager::get_instance()->get_settings_array();
$is_connected  = $oauth_status['connected'] || ! empty( $settings['api_token'] );
?>

<div class="ghl-custom-objects-wrapper">
	<div class="ghl-custom-objects-header">
	</div>

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
	// Check scope access for Custom Objects
	\GHL_CRM\Core\ScopeChecker::render_scope_notice( 'custom_objects' );
	\GHL_CRM\Core\ScopeChecker::render_scope_notice( 'associations' );
	?>

	<p class="description">
		<?php esc_html_e( 'View and manage Custom Objects from your GoHighLevel account. Custom Objects allow you to store structured data beyond standard contacts.', 'ghl-crm-integration' ); ?>
	</p>
	
	<?php if ( $is_connected ) : ?>
		
		<div class="ghl-custom-objects-controls">
			<button type="button" class="button button-primary" id="ghl-refresh-schemas">
				<span class="dashicons dashicons-update"></span>
				<?php esc_html_e( 'Refresh Schemas', 'ghl-crm-integration' ); ?>
			</button>
			<button type="button" class="button" id="ghl-view-mappings">
				<span class="dashicons dashicons-admin-settings"></span>
				<?php esc_html_e( 'View Mappings', 'ghl-crm-integration' ); ?>
			</button>
			<span class="spinner"></span>
		</div>

		<!-- Mappings Section (Hidden by default) -->
		<div id="ghl-mappings-section" class="ghl-mappings-section" style="display: none;">
			<div class="ghl-mappings-header">
				<h2><?php esc_html_e( 'Custom Object Mappings', 'ghl-crm-integration' ); ?></h2>
				<button type="button" class="button button-primary" id="ghl-create-mapping">
					<span class="dashicons dashicons-plus"></span>
					<?php esc_html_e( 'Create Mapping', 'ghl-crm-integration' ); ?>
				</button>
			</div>
			<div id="ghl-mappings-list" class="ghl-mappings-list">
				<div class="ghl-loading-placeholder">
					<span class="spinner is-active"></span>
					<p><?php esc_html_e( 'Loading Mappings...', 'ghl-crm-integration' ); ?></p>
				</div>
			</div>
		</div>

		<div id="ghl-schemas-container" class="ghl-schemas-container">
			<div class="ghl-loading-placeholder">
				<span class="spinner is-active"></span>
				<p><?php esc_html_e( 'Loading Custom Object Schemas...', 'ghl-crm-integration' ); ?></p>
			</div>
		</div>

		<div id="ghl-schema-details-modal" class="ghl-modal" style="display: none;">
			<div class="ghl-modal-overlay"></div>
			<div class="ghl-modal-content">
				<div class="ghl-modal-header">
					<h2 id="ghl-modal-title"></h2>
					<button type="button" class="ghl-modal-close">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				</div>
				<div class="ghl-modal-body" id="ghl-modal-body"></div>
			</div>
		</div>

		<!-- Mapping Configuration Modal -->
		<div id="ghl-mapping-modal" class="ghl-modal ghl-mapping-modal" style="display: none;">
			<div class="ghl-modal-overlay"></div>
			<div class="ghl-modal-content ghl-mapping-modal-content">
				<div class="ghl-modal-header">
					<h2><?php esc_html_e( 'Configure Custom Object Mapping', 'ghl-crm-integration' ); ?></h2>
					<button type="button" class="ghl-modal-close">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				</div>
				<div class="ghl-modal-body">
					<form id="ghl-mapping-form">
						<input type="hidden" id="mapping-id" name="mapping_id" value="">
						
						<!-- Basic Settings -->
						<div class="ghl-form-section">
							<h3><?php esc_html_e( 'Basic Settings', 'ghl-crm-integration' ); ?></h3>
							
							<div class="ghl-form-row">
								<label for="mapping-name">
									<?php esc_html_e( 'Mapping Name', 'ghl-crm-integration' ); ?>
									<span class="required">*</span>
								</label>
								<input type="text" id="mapping-name" name="mapping_name" class="regular-text" required placeholder="e.g., Product Inventory Sync">
							</div>

							<div class="ghl-form-row">
								<label for="wp-post-type">
									<?php esc_html_e( 'WordPress Post Type', 'ghl-crm-integration' ); ?>
									<span class="required">*</span>
								</label>
								<select id="wp-post-type" name="wp_post_type" required>
									<option value=""><?php esc_html_e( 'Select Post Type...', 'ghl-crm-integration' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'Select the WordPress Custom Post Type to sync.', 'ghl-crm-integration' ); ?></p>
							</div>

							<div class="ghl-form-row">
								<label for="ghl-object">
									<?php esc_html_e( 'GHL Custom Object', 'ghl-crm-integration' ); ?>
									<span class="required">*</span>
								</label>
								<select id="ghl-object" name="ghl_object" required>
									<option value=""><?php esc_html_e( 'Select Custom Object...', 'ghl-crm-integration' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'Select the GHL Custom Object to sync to (System objects excluded).', 'ghl-crm-integration' ); ?></p>
							</div>

							<div class="ghl-form-row">
								<label>
									<?php esc_html_e( 'Status', 'ghl-crm-integration' ); ?>
								</label>
								<label class="ghl-toggle">
									<input type="checkbox" id="mapping-active" name="mapping_active" checked>
									<span class="ghl-toggle-slider"></span>
									<span class="ghl-toggle-label"><?php esc_html_e( 'Active', 'ghl-crm-integration' ); ?></span>
								</label>
							</div>
						</div>

						<!-- Sync Triggers -->
						<div class="ghl-form-section">
							<h3><?php esc_html_e( 'Sync Triggers', 'ghl-crm-integration' ); ?></h3>
							<div class="ghl-checkbox-group">
								<label>
									<input type="checkbox" name="triggers[]" value="publish" checked>
									<?php esc_html_e( 'On Post Publish', 'ghl-crm-integration' ); ?>
								</label>
								<label>
									<input type="checkbox" name="triggers[]" value="update" checked>
									<?php esc_html_e( 'On Post Update', 'ghl-crm-integration' ); ?>
								</label>
								<label>
									<input type="checkbox" name="triggers[]" value="trash">
									<?php esc_html_e( 'On Post Delete (delete from GHL)', 'ghl-crm-integration' ); ?>
								</label>
							</div>
						</div>

						<!-- Contact Association -->
						<div class="ghl-form-section">
							<h3><?php esc_html_e( 'Contact Association', 'ghl-crm-integration' ); ?></h3>
							
							<div class="ghl-form-row">
								<label for="contact-source">
									<?php esc_html_e( 'Link to GHL Contact using', 'ghl-crm-integration' ); ?>
									<span class="required">*</span>
								</label>
								<select id="contact-source" name="contact_source" required>
									<option value="post_author"><?php esc_html_e( 'Post Author (linked contact)', 'ghl-crm-integration' ); ?></option>
									<option value="post_meta"><?php esc_html_e( 'Post Meta Field', 'ghl-crm-integration' ); ?></option>
									<option value="acf"><?php esc_html_e( 'ACF Field', 'ghl-crm-integration' ); ?></option>
								</select>
							</div>

							<div class="ghl-form-row" id="contact-field-row" style="display: none;">
								<label for="contact-field">
									<?php esc_html_e( 'Field Name', 'ghl-crm-integration' ); ?>
								</label>
								<input type="text" id="contact-field" name="contact_field" class="regular-text" placeholder="e.g., _customer_user or contact_email">
							</div>

							<div class="ghl-form-row">
								<label for="contact-not-found">
									<?php esc_html_e( 'If contact not found', 'ghl-crm-integration' ); ?>
								</label>
								<select id="contact-not-found" name="contact_not_found">
									<option value="skip"><?php esc_html_e( 'Skip sync', 'ghl-crm-integration' ); ?></option>
									<option value="create"><?php esc_html_e( 'Create new contact', 'ghl-crm-integration' ); ?></option>
									<option value="log"><?php esc_html_e( 'Log error only', 'ghl-crm-integration' ); ?></option>
								</select>
							</div>
						</div>

						<!-- Field Mapping -->
						<div class="ghl-form-section">
							<h3><?php esc_html_e( 'Field Mapping', 'ghl-crm-integration' ); ?></h3>
							<p class="description"><?php esc_html_e( 'Map WordPress fields to GHL Custom Object properties.', 'ghl-crm-integration' ); ?></p>
							
							<table class="ghl-mapping-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'WordPress Field', 'ghl-crm-integration' ); ?></th>
										<th width="50"></th>
										<th><?php esc_html_e( 'GHL Property', 'ghl-crm-integration' ); ?></th>
										<th><?php esc_html_e( 'Transform', 'ghl-crm-integration' ); ?></th>
										<th width="50"></th>
									</tr>
								</thead>
								<tbody id="field-mappings-body">
									<!-- Dynamic rows will be added here -->
								</tbody>
							</table>
							<button type="button" class="button" id="add-field-mapping">
								<span class="dashicons dashicons-plus"></span>
								<?php esc_html_e( 'Add Field Mapping', 'ghl-crm-integration' ); ?>
							</button>
						</div>

						<!-- Advanced Options -->
						<div class="ghl-form-section">
							<h3><?php esc_html_e( 'Advanced Options', 'ghl-crm-integration' ); ?></h3>
							<div class="ghl-checkbox-group">
								<label>
									<input type="checkbox" name="enable_batch_sync" value="1">
									<?php esc_html_e( 'Enable batch sync for existing posts', 'ghl-crm-integration' ); ?>
								</label>
								<label>
									<input type="checkbox" name="log_sync_operations" value="1" checked>
									<?php esc_html_e( 'Log all sync operations', 'ghl-crm-integration' ); ?>
								</label>
							</div>
						</div>

						<div class="ghl-form-actions">
							<button type="submit" class="button button-primary">
								<span class="dashicons dashicons-yes"></span>
								<?php esc_html_e( 'Save Mapping', 'ghl-crm-integration' ); ?>
							</button>
							<button type="button" class="button ghl-modal-close">
								<?php esc_html_e( 'Cancel', 'ghl-crm-integration' ); ?>
							</button>
							<span class="spinner"></span>
						</div>
					</form>
				</div>
			</div>
		</div>

	<?php endif; ?>
</div>

