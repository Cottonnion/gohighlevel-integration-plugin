<?php
/**
 * WPForms GHL CRM Panel Template
 *
 * Displays in WPForms builder settings section
 *
 * @package Syncly
 *
 * @var int   $form_id        WPForms form ID
 * @var array $config         Form configuration
 * @var array $wpforms_fields WPForms form fields
 * @var array $settings       Syncly global settings
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="syncly-wpforms-panel">
	<!-- Enable Integration -->
	<div class="syncly-section">
		<h3><?php esc_html_e( 'Syncly', 'syncly' ); ?></h3>
		
		<div class="ghl-form-item">
			<div class="ghl-form-item-content">
				<label class="ghl-checkbox <?php echo $config['enabled'] ? 'is-checked' : ''; ?>">
					<input type="checkbox" 
							class="ghl-checkbox-original"
							id="syncly_wpforms_enabled" 
							name="settings[syncly][enabled]" 
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
	<div id="syncly_wpforms_settings_container" style="<?php echo $config['enabled'] ? '' : 'display:none;'; ?>">
		
		<!-- Field Mapping -->
		<div class="syncly-section">
			<h3><?php esc_html_e( 'Field Mapping', 'syncly' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Map your WPForms fields to GoHighLevel contact fields. At minimum, map an email field.', 'syncly' ); ?>
			</p>

			<div id="syncly_wpforms_email_notice" class="syncly-status syncly-status-disconnected" style="display:none;">
				<span class="dashicons dashicons-warning"></span>
				<?php esc_html_e( 'Email mapping is required. Please map at least one WPForms field to the "Email" GHL field — submissions without an email will be ignored.', 'syncly' ); ?>
			</div>

			<table class="syncly-field-mapping widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'WPForms Field', 'syncly' ); ?></th>
						<th><?php esc_html_e( 'GoHighLevel Field', 'syncly' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! empty( $wpforms_fields ) ) : ?>
						<?php foreach ( $wpforms_fields as $field ) : ?>
							<tr>
								<td>
									<strong><?php echo esc_html( $field['label'] ); ?></strong>
									<span class="field-type">(<?php echo esc_html( $field['type'] ); ?> #<?php echo esc_html( $field['id'] ); ?>)</span>
								</td>
								<td>
									<select name="settings[syncly][field_mapping][<?php echo esc_attr( $field['id'] ); ?>]" 
											class="ghl-select ghl-field-select"
											data-placeholder="<?php esc_attr_e( 'Select a GoHighLevel contact field…', 'syncly' ); ?>"
											data-saved-value="<?php echo esc_attr( $config['field_mapping'][ $field['id'] ] ?? '' ); ?>">
										<option value=""><?php esc_html_e( '— Loading fields... —', 'syncly' ); ?></option>
									</select>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr>
							<td colspan="2">
								<em><?php esc_html_e( 'No form fields detected. Add form fields in the builder first.', 'syncly' ); ?></em>
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
					<?php
					/*
					 * The select is intentionally unnamed. WPForms rebuilds the
					 * POST payload with wpforms_prepare_form_data(), which folds
					 * repeated `name[]` inputs into a single empty array key, so a
					 * multiple select can only ever save one value. The hidden
					 * field carries a comma-separated list instead.
					 */
					?>
					<input type="hidden"
						id="syncly_wpforms_tags_value"
						name="settings[syncly][tags]"
						value="<?php echo esc_attr( implode( ',', array_filter( (array) $config['tags'], 'strlen' ) ) ); ?>"
					>
					<select 
						id="syncly_wpforms_tags" 
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
							id="syncly_wpforms_update_exists" 
							name="settings[syncly][update_exists]" 
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
		<?php if ( \Syncly\Integrations\Forms\FormSettings::is_pro_active() ) : ?>
			<?php do_action( 'syncly_wpforms_panel_after', $form_id, $config, $form_data ); ?>
		<?php else : ?>
			<?php
			$notice_title = __( 'Unlock the WPForms automation toolkit', 'syncly' );
			$description  = __( 'Control when submissions sync and automate the next steps for each contact.', 'syncly' );
			$features     = [
				__( 'Conditional rules for form answers', 'syncly' ),
				__( 'Contact timeline notes', 'syncly' ),
				__( 'Custom source labels and delayed delivery', 'syncly' ),
				__( 'GoHighLevel workflow enrollment', 'syncly' ),
			];
			$cta_text = __( 'Explore WPForms Pro Features', 'syncly' );
			$cta_url  = apply_filters( 'syncly_upgrade_url', admin_url( 'admin.php?page=syncly-admin&settings_tab=upgrade' ) );
			$style    = 'box';
			include SYNCLY_PATH . 'templates/admin/partials/pro-upgrade-notice.php';
			?>
		<?php endif; ?>
	</div>
</div>
