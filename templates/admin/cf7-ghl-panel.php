<?php
/**
 * CF7 GHL CRM Panel Template
 *
 * Displays in CF7 form editor as a tab
 *
 * @package GHL_CRM_Integration
 *
 * @var int   $form_id    CF7 form ID
 * @var array $config     Form configuration
 * @var array $cf7_fields CF7 form fields
 * @var array $ghl_fields GHL fields (standard + custom)
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="ghl-crm-cf7-panel">
	<?php wp_nonce_field( 'ghl_crm_cf7_save', 'ghl_crm_cf7_nonce' ); ?>

	<!-- Enable Integration -->
	<div class="ghl-crm-section">
		<h3><?php esc_html_e( 'GoHighLevel Integration', 'ghl-crm-integration' ); ?></h3>
		
		<div class="ghl-form-item">
			<div class="ghl-form-item-content">
				<label class="ghl-checkbox <?php echo $config['enabled'] ? 'is-checked' : ''; ?>">
					<input type="checkbox" 
							class="ghl-checkbox-original"
							id="ghl_crm_enabled" 
							name="ghl_crm_enabled" 
							value="1" 
							<?php checked( $config['enabled'], true ); ?>
							>
					<span class="ghl-checkbox-input <?php echo $config['enabled'] ? 'is-checked' : ''; ?>">
						<span class="ghl-checkbox-inner"></span>
					</span>
					<span class="ghl-checkbox-label">
						<?php esc_html_e( 'Send form submissions to GoHighLevel', 'ghl-crm-integration' ); ?>
					</span>
				</label>
			</div>
		</div>

		<p class="description">
			<?php esc_html_e( 'When enabled, form submissions will create or update contacts in your GoHighLevel account.', 'ghl-crm-integration' ); ?>
		</p>
	</div>

	<!-- Settings Container (visible when enabled) -->
	<div id="ghl_crm_settings_container" style="<?php echo $config['enabled'] ? '' : 'display:none;'; ?>">
		
		<!-- Field Mapping -->
		<div class="ghl-crm-section">
			<h3><?php esc_html_e( 'Field Mapping', 'ghl-crm-integration' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Map your Contact Form 7 fields to GoHighLevel contact fields. At minimum, map an email field.', 'ghl-crm-integration' ); ?>
			</p>

			<div id="ghl_crm_email_notice" class="ghl-crm-status ghl-crm-status-disconnected" style="display:none;">
				<span class="dashicons dashicons-warning"></span>
				<?php esc_html_e( 'Email mapping is required. Please map at least one CF7 field to the "Email" GHL field — submissions without an email will be ignored.', 'ghl-crm-integration' ); ?>
			</div>

			<table class="ghl-crm-field-mapping widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'CF7 Field', 'ghl-crm-integration' ); ?></th>
						<th><?php esc_html_e( 'GoHighLevel Field', 'ghl-crm-integration' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! empty( $cf7_fields ) ) : ?>
						<?php foreach ( $cf7_fields as $field ) : ?>
							<tr>
								<td>
									<strong><?php echo esc_html( $field['name'] ); ?></strong>
									<span class="field-type">(<?php echo esc_html( $field['type'] ); ?>)</span>
								</td>
								<td>
									<select name="ghl_crm_field_mapping[<?php echo esc_attr( $field['name'] ); ?>]" 
											class="ghl-field-select"
											data-saved-value="<?php echo esc_attr( $config['field_mapping'][ $field['name'] ] ?? '' ); ?>">
										<option value=""><?php esc_html_e( '— Loading fields... —', 'ghl-crm-integration' ); ?></option>
									</select>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr>
							<td colspan="2">
								<em><?php esc_html_e( 'No form fields detected. Add form fields in the Form tab first.', 'ghl-crm-integration' ); ?></em>
							</td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<!-- Tags -->
		<div class="ghl-crm-section">
			<h3><?php esc_html_e( 'Contact Tags', 'ghl-crm-integration' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Select tags to apply to contacts created from this form.', 'ghl-crm-integration' ); ?>
			</p>

			<div class="ghl-form-item">
				<div class="ghl-form-item-content ghl-form-item-content--column">
					<select 
						id="ghl_crm_cf7_tags" 
						name="ghl_crm_tags[]" 
						multiple 
						class="ghl-tags-select"
						data-saved-tags='<?php echo esc_attr( wp_json_encode( $config['tags'] ) ); ?>'
						data-placeholder="<?php esc_attr_e( 'Select tags to apply on submission...', 'ghl-crm-integration' ); ?>">
						<option value=""><?php esc_html_e( 'Loading tags...', 'ghl-crm-integration' ); ?></option>
					</select>
				</div>
			</div>
		</div>

		<!-- Update Behavior -->
		<div class="ghl-crm-section">
			<h3><?php esc_html_e( 'Update Behavior', 'ghl-crm-integration' ); ?></h3>
			
			<div class="ghl-form-item">
				<div class="ghl-form-item-content">
					<label class="ghl-checkbox <?php echo $config['update_exists'] ? 'is-checked' : ''; ?>">
						<input type="checkbox" 
								class="ghl-checkbox-original"
							id="ghl_crm_update_exists" 
							name="ghl_crm_update_exists" 
							value="1" 
							<?php checked( $config['update_exists'], true ); ?>
							>
						<span class="ghl-checkbox-input <?php echo $config['update_exists'] ? 'is-checked' : ''; ?>">
							<span class="ghl-checkbox-inner"></span>
						</span>
						<span class="ghl-checkbox-label">
							<?php esc_html_e( 'Update existing contacts if email already exists', 'ghl-crm-integration' ); ?>
						</span>
					</label>
				</div>
			</div>

			<p class="description">
				<?php esc_html_e( 'When enabled, if a contact with the same email exists, their information will be updated. When disabled, duplicate submissions will be ignored.', 'ghl-crm-integration' ); ?>
			</p>
		</div>

		<!-- Connection Status -->
		<div class="ghl-crm-section">
			<h3><?php esc_html_e( 'Connection Status', 'ghl-crm-integration' ); ?></h3>
			<?php
			$settings    = \GHL_CRM\Core\SettingsManager::get_instance()->get_settings_array();
			$connected   = ! empty( $settings['location_id'] );
			$location_id = $settings['location_id'] ?? '';
			?>
			<?php if ( $connected ) : ?>
				<p class="ghl-crm-status ghl-crm-status-connected">
					<span class="dashicons dashicons-yes-alt"></span>
					<?php
					printf(
						/* translators: %s: Location ID */
						esc_html__( 'Connected to GoHighLevel (Location: %s)', 'ghl-crm-integration' ),
						esc_html( $location_id )
					);
					?>
				</p>
			<?php else : ?>
				<p class="ghl-crm-status ghl-crm-status-disconnected">
					<span class="dashicons dashicons-warning"></span>
					<?php esc_html_e( 'Not connected to GoHighLevel. ', 'ghl-crm-integration' ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=ghl-crm-admin' ) ); ?>">
						<?php esc_html_e( 'Connect now', 'ghl-crm-integration' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</div>

	</div>
</div>
