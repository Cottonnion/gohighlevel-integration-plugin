<?php
/**
 * CF7 GHL CRM Panel Template
 *
 * Displays in CF7 form editor as a tab
 *
 * @package Syncly
 *
 * @var int   $form_id    CF7 form ID
 * @var array $config     Form configuration
 * @var array $cf7_fields CF7 form fields
 * @var array $ghl_fields GHL fields (standard + custom)
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="syncly-cf7-panel">
	<?php wp_nonce_field( 'syncly_cf7_save', 'syncly_cf7_nonce' ); ?>

	<!-- Enable Integration -->
	<div class="syncly-section">
		<h3><?php esc_html_e( 'GoHighLevel Integration', 'syncly' ); ?></h3>
		
		<div class="ghl-form-item">
			<div class="ghl-form-item-content">
				<label class="ghl-checkbox <?php echo $config['enabled'] ? 'is-checked' : ''; ?>">
					<input type="checkbox" 
							class="ghl-checkbox-original"
							id="syncly_enabled" 
							name="syncly_enabled" 
							value="1" 
							<?php checked( $config['enabled'], true ); ?>
							>
					<span class="ghl-checkbox-input <?php echo $config['enabled'] ? 'is-checked' : ''; ?>">
						<span class="ghl-checkbox-inner"></span>
					</span>
					<span class="ghl-checkbox-label">
						<?php esc_html_e( 'Send form submissions to GoHighLevel', 'syncly' ); ?>
					</span>
				</label>
			</div>
		</div>

		<p class="description">
			<?php esc_html_e( 'When enabled, form submissions will create or update contacts in your GoHighLevel account.', 'syncly' ); ?>
		</p>
	</div>

	<!-- Settings Container (visible when enabled) -->
	<div id="syncly_settings_container" style="<?php echo $config['enabled'] ? '' : 'display:none;'; ?>">
		
		<!-- Field Mapping -->
		<div class="syncly-section">
			<h3><?php esc_html_e( 'Field Mapping', 'syncly' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Map your Contact Form 7 fields to GoHighLevel contact fields. At minimum, map an email field.', 'syncly' ); ?>
			</p>

			<div id="syncly_email_notice" class="syncly-status syncly-status-disconnected" style="display:none;">
				<span class="dashicons dashicons-warning"></span>
				<?php esc_html_e( 'Email mapping is required. Please map at least one CF7 field to the "Email" GHL field — submissions without an email will be ignored.', 'syncly' ); ?>
			</div>

			<table class="syncly-field-mapping widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'CF7 Field', 'syncly' ); ?></th>
						<th><?php esc_html_e( 'GoHighLevel Field', 'syncly' ); ?></th>
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
									<select name="syncly_field_mapping[<?php echo esc_attr( $field['name'] ); ?>]" 
											class="ghl-field-select"
											data-saved-value="<?php echo esc_attr( $config['field_mapping'][ $field['name'] ] ?? '' ); ?>">
										<option value=""><?php esc_html_e( '— Loading fields... —', 'syncly' ); ?></option>
									</select>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr>
							<td colspan="2">
								<em><?php esc_html_e( 'No form fields detected. Add form fields in the Form tab first.', 'syncly' ); ?></em>
							</td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<!-- Tags -->
		<div class="syncly-section">
			<h3><?php esc_html_e( 'Contact Tags', 'syncly' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Select tags to apply to contacts created from this form.', 'syncly' ); ?>
			</p>

			<div class="ghl-form-item">
				<div class="ghl-form-item-content ghl-form-item-content--column">
					<select 
						id="syncly_cf7_tags" 
						name="syncly_tags[]" 
						multiple 
						class="ghl-tags-select"
						data-saved-tags='<?php echo esc_attr( wp_json_encode( $config['tags'] ) ); ?>'
						data-placeholder="<?php esc_attr_e( 'Select tags to apply on submission...', 'syncly' ); ?>">
						<option value=""><?php esc_html_e( 'Loading tags...', 'syncly' ); ?></option>
					</select>
				</div>
			</div>
		</div>

		<!-- Update Behavior -->
		<div class="syncly-section">
			<h3><?php esc_html_e( 'Update Behavior', 'syncly' ); ?></h3>
			
			<div class="ghl-form-item">
				<div class="ghl-form-item-content">
					<label class="ghl-checkbox <?php echo $config['update_exists'] ? 'is-checked' : ''; ?>">
						<input type="checkbox" 
								class="ghl-checkbox-original"
							id="syncly_update_exists" 
							name="syncly_update_exists" 
							value="1" 
							<?php checked( $config['update_exists'], true ); ?>
							>
						<span class="ghl-checkbox-input <?php echo $config['update_exists'] ? 'is-checked' : ''; ?>">
							<span class="ghl-checkbox-inner"></span>
						</span>
						<span class="ghl-checkbox-label">
							<?php esc_html_e( 'Update existing contacts if email already exists', 'syncly' ); ?>
						</span>
					</label>
				</div>
			</div>

			<p class="description">
				<?php esc_html_e( 'When enabled, if a contact with the same email exists, their information will be updated. When disabled, duplicate submissions will be ignored.', 'syncly' ); ?>
			</p>
		</div>

		<!-- Connection Status -->
		<div class="syncly-section">
			<h3><?php esc_html_e( 'Connection Status', 'syncly' ); ?></h3>
			<?php
			$settings    = \Syncly\Core\SettingsManager::get_instance()->get_settings_array();
			$connected   = ! empty( $settings['location_id'] );
			$location_id = $settings['location_id'] ?? '';
			?>
			<?php if ( $connected ) : ?>
				<p class="syncly-status syncly-status-connected">
					<span class="dashicons dashicons-yes-alt"></span>
					<?php
					printf(
						/* translators: %s: Location ID */
						esc_html__( 'Connected to GoHighLevel (Location: %s)', 'syncly' ),
						esc_html( $location_id )
					);
					?>
				</p>
			<?php else : ?>
				<p class="syncly-status syncly-status-disconnected">
					<span class="dashicons dashicons-warning"></span>
					<?php esc_html_e( 'Not connected to GoHighLevel. ', 'syncly' ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=syncly-admin' ) ); ?>">
						<?php esc_html_e( 'Connect now', 'syncly' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</div>

	</div>
</div>
