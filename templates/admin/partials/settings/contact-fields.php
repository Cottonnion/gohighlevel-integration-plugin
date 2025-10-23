<?php
/**
 * Custom Contact Fields Settings Partial
 *
 * @package GHL_CRM_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="ghl-settings-contact-fields">
	<h2><?php esc_html_e( 'Custom Contact Fields', 'ghl-crm-integration' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Map WordPress user fields and metadata to GoHighLevel custom contact fields.', 'ghl-crm-integration' ); ?>
	</p>

	<form id="ghl-contact-fields-settings-form" method="post">
		<?php wp_nonce_field( 'ghl_contact_fields_settings', 'ghl_contact_fields_nonce' ); ?>

		<div class="ghl-field-mappings">
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width: 30%;"><?php esc_html_e( 'WordPress Field', 'ghl-crm-integration' ); ?></th>
						<th style="width: 30%;"><?php esc_html_e( 'GoHighLevel Field', 'ghl-crm-integration' ); ?></th>
						<th style="width: 20%;"><?php esc_html_e( 'Field Type', 'ghl-crm-integration' ); ?></th>
						<th style="width: 15%;"><?php esc_html_e( 'Required', 'ghl-crm-integration' ); ?></th>
						<th style="width: 5%;"><?php esc_html_e( 'Action', 'ghl-crm-integration' ); ?></th>
					</tr>
				</thead>
				<tbody id="field-mapping-rows">
					<!-- Standard Fields -->
					<tr>
						<td>
							<input type="text" value="user_email" class="regular-text" readonly />
						</td>
						<td>
							<input type="text" value="email" class="regular-text" readonly />
						</td>
						<td>
							<select disabled>
								<option value="email">Email</option>
							</select>
						</td>
						<td>
							<input type="checkbox" checked disabled />
						</td>
						<td>
							<span class="dashicons dashicons-lock" title="<?php esc_attr_e( 'Default field', 'ghl-crm-integration' ); ?>"></span>
						</td>
					</tr>
					<tr>
						<td>
							<input type="text" value="first_name" class="regular-text" readonly />
						</td>
						<td>
							<input type="text" value="firstName" class="regular-text" readonly />
						</td>
						<td>
							<select disabled>
								<option value="text">Text</option>
							</select>
						</td>
						<td>
							<input type="checkbox" disabled />
						</td>
						<td>
							<span class="dashicons dashicons-lock" title="<?php esc_attr_e( 'Default field', 'ghl-crm-integration' ); ?>"></span>
						</td>
					</tr>
					<tr>
						<td>
							<input type="text" value="last_name" class="regular-text" readonly />
						</td>
						<td>
							<input type="text" value="lastName" class="regular-text" readonly />
						</td>
						<td>
							<select disabled>
								<option value="text">Text</option>
							</select>
						</td>
						<td>
							<input type="checkbox" disabled />
						</td>
						<td>
							<span class="dashicons dashicons-lock" title="<?php esc_attr_e( 'Default field', 'ghl-crm-integration' ); ?>"></span>
						</td>
					</tr>
					
					<!-- Custom Field Example -->
					<tr class="custom-field-row">
						<td>
							<input 
								type="text" 
								name="field_mappings[0][wp_field]" 
								placeholder="billing_phone" 
								class="regular-text" 
							/>
						</td>
						<td>
							<input 
								type="text" 
								name="field_mappings[0][ghl_field]" 
								placeholder="phone" 
								class="regular-text" 
							/>
						</td>
						<td>
							<select name="field_mappings[0][field_type]">
								<option value="text"><?php esc_html_e( 'Text', 'ghl-crm-integration' ); ?></option>
								<option value="email"><?php esc_html_e( 'Email', 'ghl-crm-integration' ); ?></option>
								<option value="phone"><?php esc_html_e( 'Phone', 'ghl-crm-integration' ); ?></option>
								<option value="number"><?php esc_html_e( 'Number', 'ghl-crm-integration' ); ?></option>
								<option value="date"><?php esc_html_e( 'Date', 'ghl-crm-integration' ); ?></option>
								<option value="url"><?php esc_html_e( 'URL', 'ghl-crm-integration' ); ?></option>
							</select>
						</td>
						<td>
							<input type="checkbox" name="field_mappings[0][required]" value="1" />
						</td>
						<td>
							<button type="button" class="button button-small remove-field-mapping" title="<?php esc_attr_e( 'Remove', 'ghl-crm-integration' ); ?>">
								<span class="dashicons dashicons-trash"></span>
							</button>
						</td>
					</tr>
				</tbody>
			</table>

			<p style="margin-top: 10px;">
				<button type="button" class="button button-secondary" id="add-field-mapping">
					<span class="dashicons dashicons-plus-alt"></span>
					<?php esc_html_e( 'Add Custom Field Mapping', 'ghl-crm-integration' ); ?>
				</button>
			</p>
		</div>

		<hr style="margin: 30px 0;">

		<h3><?php esc_html_e( 'Field Transformation Rules', 'ghl-crm-integration' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'Apply transformations to field values before syncing.', 'ghl-crm-integration' ); ?>
		</p>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="phone_format">
							<?php esc_html_e( 'Phone Number Format', 'ghl-crm-integration' ); ?>
						</label>
					</th>
					<td>
						<select id="phone_format" name="phone_format" class="regular-text">
							<option value="e164"><?php esc_html_e( 'E.164 Format (+1234567890)', 'ghl-crm-integration' ); ?></option>
							<option value="national"><?php esc_html_e( 'National Format (123-456-7890)', 'ghl-crm-integration' ); ?></option>
							<option value="international"><?php esc_html_e( 'International Format (+1 123 456 7890)', 'ghl-crm-integration' ); ?></option>
							<option value="none"><?php esc_html_e( 'No Formatting', 'ghl-crm-integration' ); ?></option>
						</select>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="date_format">
							<?php esc_html_e( 'Date Format', 'ghl-crm-integration' ); ?>
						</label>
					</th>
					<td>
						<select id="date_format" name="date_format" class="regular-text">
							<option value="Y-m-d"><?php esc_html_e( 'YYYY-MM-DD', 'ghl-crm-integration' ); ?></option>
							<option value="m/d/Y"><?php esc_html_e( 'MM/DD/YYYY', 'ghl-crm-integration' ); ?></option>
							<option value="d/m/Y"><?php esc_html_e( 'DD/MM/YYYY', 'ghl-crm-integration' ); ?></option>
							<option value="timestamp"><?php esc_html_e( 'Unix Timestamp', 'ghl-crm-integration' ); ?></option>
						</select>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="name_capitalization">
							<?php esc_html_e( 'Name Capitalization', 'ghl-crm-integration' ); ?>
						</label>
					</th>
					<td>
						<select id="name_capitalization" name="name_capitalization" class="regular-text">
							<option value="none"><?php esc_html_e( 'No Change', 'ghl-crm-integration' ); ?></option>
							<option value="ucfirst"><?php esc_html_e( 'Capitalize First Letter', 'ghl-crm-integration' ); ?></option>
							<option value="ucwords"><?php esc_html_e( 'Capitalize Each Word', 'ghl-crm-integration' ); ?></option>
							<option value="uppercase"><?php esc_html_e( 'UPPERCASE', 'ghl-crm-integration' ); ?></option>
							<option value="lowercase"><?php esc_html_e( 'lowercase', 'ghl-crm-integration' ); ?></option>
						</select>
					</td>
				</tr>
			</tbody>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary">
				<?php esc_html_e( 'Save Custom Fields', 'ghl-crm-integration' ); ?>
			</button>
		</p>
	</form>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
	var fieldIndex = 1;
	
	// Add new field mapping row
	$('#add-field-mapping').on('click', function() {
		var newRow = `
			<tr class="custom-field-row">
				<td>
					<input type="text" name="field_mappings[${fieldIndex}][wp_field]" placeholder="user_meta_key" class="regular-text" />
				</td>
				<td>
					<input type="text" name="field_mappings[${fieldIndex}][ghl_field]" placeholder="ghl_field_name" class="regular-text" />
				</td>
				<td>
					<select name="field_mappings[${fieldIndex}][field_type]">
						<option value="text"><?php esc_html_e( 'Text', 'ghl-crm-integration' ); ?></option>
						<option value="email"><?php esc_html_e( 'Email', 'ghl-crm-integration' ); ?></option>
						<option value="phone"><?php esc_html_e( 'Phone', 'ghl-crm-integration' ); ?></option>
						<option value="number"><?php esc_html_e( 'Number', 'ghl-crm-integration' ); ?></option>
						<option value="date"><?php esc_html_e( 'Date', 'ghl-crm-integration' ); ?></option>
						<option value="url"><?php esc_html_e( 'URL', 'ghl-crm-integration' ); ?></option>
					</select>
				</td>
				<td>
					<input type="checkbox" name="field_mappings[${fieldIndex}][required]" value="1" />
				</td>
				<td>
					<button type="button" class="button button-small remove-field-mapping">
						<span class="dashicons dashicons-trash"></span>
					</button>
				</td>
			</tr>
		`;
		$('#field-mapping-rows').append(newRow);
		fieldIndex++;
	});
	
	// Remove field mapping row
	$(document).on('click', '.remove-field-mapping', function() {
		$(this).closest('tr').remove();
	});
});
</script>
