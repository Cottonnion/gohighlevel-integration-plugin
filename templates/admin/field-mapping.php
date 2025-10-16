<?php
/**
 * Template: Field Mapping Page
 *
 * @package GHL_CRM_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap ghl-crm-field-mapping">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	
	<div class="notice notice-info">
		<p>
			<?php esc_html_e( 'Map WordPress fields to GoHighLevel CRM fields for seamless data synchronization.', 'ghl-crm-integration' ); ?>
		</p>
	</div>

	<div class="ghl-crm-mapping-container">
		<h2><?php esc_html_e( 'Contact Field Mapping', 'ghl-crm-integration' ); ?></h2>
		
		<form method="post" action="" class="ghl-crm-mapping-form">
			<?php wp_nonce_field( 'ghl_crm_save_field_mapping', 'ghl_crm_mapping_nonce' ); ?>
			
			<table class="form-table" role="presentation">
				<thead>
					<tr>
						<th><?php esc_html_e( 'WordPress Field', 'ghl-crm-integration' ); ?></th>
						<th><?php esc_html_e( 'GoHighLevel Field', 'ghl-crm-integration' ); ?></th>
						<th><?php esc_html_e( 'Sync Direction', 'ghl-crm-integration' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td>
							<strong><?php esc_html_e( 'First Name', 'ghl-crm-integration' ); ?></strong>
							<br><code>first_name</code>
						</td>
						<td>
							<select name="ghl_field_first_name" class="regular-text">
								<option value="firstName"><?php esc_html_e( 'firstName', 'ghl-crm-integration' ); ?></option>
								<option value="name"><?php esc_html_e( 'name', 'ghl-crm-integration' ); ?></option>
							</select>
						</td>
						<td>
							<select name="sync_direction_first_name">
								<option value="both"><?php esc_html_e( '↔ Both Ways', 'ghl-crm-integration' ); ?></option>
								<option value="to_ghl"><?php esc_html_e( '→ To GHL Only', 'ghl-crm-integration' ); ?></option>
								<option value="from_ghl"><?php esc_html_e( '← From GHL Only', 'ghl-crm-integration' ); ?></option>
							</select>
						</td>
					</tr>
					
					<tr>
						<td>
							<strong><?php esc_html_e( 'Last Name', 'ghl-crm-integration' ); ?></strong>
							<br><code>last_name</code>
						</td>
						<td>
							<select name="ghl_field_last_name" class="regular-text">
								<option value="lastName"><?php esc_html_e( 'lastName', 'ghl-crm-integration' ); ?></option>
							</select>
						</td>
						<td>
							<select name="sync_direction_last_name">
								<option value="both"><?php esc_html_e( '↔ Both Ways', 'ghl-crm-integration' ); ?></option>
								<option value="to_ghl"><?php esc_html_e( '→ To GHL Only', 'ghl-crm-integration' ); ?></option>
								<option value="from_ghl"><?php esc_html_e( '← From GHL Only', 'ghl-crm-integration' ); ?></option>
							</select>
						</td>
					</tr>
					
					<tr>
						<td>
							<strong><?php esc_html_e( 'Email', 'ghl-crm-integration' ); ?></strong>
							<br><code>user_email</code>
						</td>
						<td>
							<select name="ghl_field_email" class="regular-text">
								<option value="email"><?php esc_html_e( 'email', 'ghl-crm-integration' ); ?></option>
							</select>
						</td>
						<td>
							<select name="sync_direction_email">
								<option value="both"><?php esc_html_e( '↔ Both Ways', 'ghl-crm-integration' ); ?></option>
								<option value="to_ghl"><?php esc_html_e( '→ To GHL Only', 'ghl-crm-integration' ); ?></option>
								<option value="from_ghl"><?php esc_html_e( '← From GHL Only', 'ghl-crm-integration' ); ?></option>
							</select>
						</td>
					</tr>
					
					<tr>
						<td>
							<strong><?php esc_html_e( 'Phone', 'ghl-crm-integration' ); ?></strong>
							<br><code>billing_phone</code>
						</td>
						<td>
							<select name="ghl_field_phone" class="regular-text">
								<option value="phone"><?php esc_html_e( 'phone', 'ghl-crm-integration' ); ?></option>
							</select>
						</td>
						<td>
							<select name="sync_direction_phone">
								<option value="both"><?php esc_html_e( '↔ Both Ways', 'ghl-crm-integration' ); ?></option>
								<option value="to_ghl"><?php esc_html_e( '→ To GHL Only', 'ghl-crm-integration' ); ?></option>
								<option value="from_ghl"><?php esc_html_e( '← From GHL Only', 'ghl-crm-integration' ); ?></option>
							</select>
						</td>
					</tr>
				</tbody>
			</table>

			<?php submit_button( __( 'Save Field Mapping', 'ghl-crm-integration' ) ); ?>
		</form>

		<hr />

		<div class="ghl-crm-mapping-help">
			<h3><?php esc_html_e( 'Custom Field Mapping', 'ghl-crm-integration' ); ?></h3>
			<p><?php esc_html_e( 'Custom field mapping feature coming soon. You will be able to map custom WordPress fields to GoHighLevel custom fields.', 'ghl-crm-integration' ); ?></p>
		</div>
	</div>
</div>
