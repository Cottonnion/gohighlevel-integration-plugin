<?php
/**
 * Field Sync Settings Partial
 *
 * @package GHL_CRM_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="ghl-settings-field-sync">
	<h2><?php esc_html_e( 'Field Sync Settings', 'ghl-crm-integration' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Configure how WordPress user fields sync with GoHighLevel contacts.', 'ghl-crm-integration' ); ?>
	</p>

	<form id="ghl-field-sync-settings-form" method="post">
		<?php wp_nonce_field( 'ghl_field_sync_settings', 'ghl_field_sync_nonce' ); ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="sync_direction">
							<?php esc_html_e( 'Sync Direction', 'ghl-crm-integration' ); ?>
						</label>
					</th>
					<td>
						<select id="sync_direction" name="sync_direction" class="regular-text">
							<option value="wp_to_ghl"><?php esc_html_e( 'WordPress to GoHighLevel (One-way)', 'ghl-crm-integration' ); ?></option>
							<option value="ghl_to_wp"><?php esc_html_e( 'GoHighLevel to WordPress (One-way)', 'ghl-crm-integration' ); ?></option>
							<option value="bidirectional"><?php esc_html_e( 'Bidirectional (Two-way)', 'ghl-crm-integration' ); ?></option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Choose how data should sync between platforms.', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="conflict_resolution">
							<?php esc_html_e( 'Conflict Resolution', 'ghl-crm-integration' ); ?>
						</label>
					</th>
					<td>
						<select id="conflict_resolution" name="conflict_resolution" class="regular-text">
							<option value="wp_wins"><?php esc_html_e( 'WordPress Data Wins', 'ghl-crm-integration' ); ?></option>
							<option value="ghl_wins"><?php esc_html_e( 'GoHighLevel Data Wins', 'ghl-crm-integration' ); ?></option>
							<option value="newest_wins"><?php esc_html_e( 'Newest Data Wins', 'ghl-crm-integration' ); ?></option>
							<option value="manual"><?php esc_html_e( 'Manual Review Required', 'ghl-crm-integration' ); ?></option>
						</select>
						<p class="description">
							<?php esc_html_e( 'How to handle conflicts when both sides have different data.', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<?php esc_html_e( 'Auto-Sync Triggers', 'ghl-crm-integration' ); ?>
					</th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><?php esc_html_e( 'Select when to auto-sync', 'ghl-crm-integration' ); ?></legend>
							<label>
								<input type="checkbox" name="sync_triggers[]" value="user_register" checked />
								<?php esc_html_e( 'On User Registration', 'ghl-crm-integration' ); ?>
							</label><br>
							<label>
								<input type="checkbox" name="sync_triggers[]" value="profile_update" checked />
								<?php esc_html_e( 'On Profile Update', 'ghl-crm-integration' ); ?>
							</label><br>
							<label>
								<input type="checkbox" name="sync_triggers[]" value="role_change" />
								<?php esc_html_e( 'On Role Change', 'ghl-crm-integration' ); ?>
							</label><br>
							<label>
								<input type="checkbox" name="sync_triggers[]" value="order_complete" />
								<?php esc_html_e( 'On WooCommerce Order Complete', 'ghl-crm-integration' ); ?>
							</label><br>
							<label>
								<input type="checkbox" name="sync_triggers[]" value="membership_change" />
								<?php esc_html_e( 'On Membership Change', 'ghl-crm-integration' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="batch_sync_size">
							<?php esc_html_e( 'Batch Sync Size', 'ghl-crm-integration' ); ?>
						</label>
					</th>
					<td>
						<input 
							type="number" 
							id="batch_sync_size" 
							name="batch_sync_size" 
							value="50" 
							min="10" 
							max="500" 
							step="10" 
							class="small-text"
						/>
						<p class="description">
							<?php esc_html_e( 'Number of users to sync per batch. Lower numbers reduce server load.', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="sync_user_roles">
							<?php esc_html_e( 'Sync User Roles', 'ghl-crm-integration' ); ?>
						</label>
					</th>
					<td>
						<select id="sync_user_roles" name="sync_user_roles[]" multiple class="regular-text" style="height: 150px;">
							<?php
							$roles = wp_roles()->get_names();
							foreach ( $roles as $role_key => $role_name ) :
							?>
								<option value="<?php echo esc_attr( $role_key ); ?>" selected>
									<?php echo esc_html( $role_name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php esc_html_e( 'Select which user roles to include in sync. Hold Ctrl/Cmd to select multiple.', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="exclude_empty_fields">
							<?php esc_html_e( 'Exclude Empty Fields', 'ghl-crm-integration' ); ?>
						</label>
					</th>
					<td>
						<label>
							<input type="checkbox" id="exclude_empty_fields" name="exclude_empty_fields" value="1" />
							<?php esc_html_e( 'Don\'t sync fields with empty values', 'ghl-crm-integration' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'When enabled, empty fields won\'t overwrite existing data in GoHighLevel.', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="sync_meta_fields">
							<?php esc_html_e( 'Sync User Meta Fields', 'ghl-crm-integration' ); ?>
						</label>
					</th>
					<td>
						<textarea 
							id="sync_meta_fields" 
							name="sync_meta_fields" 
							rows="5" 
							class="large-text code"
							placeholder="billing_address&#10;shipping_address&#10;phone_number"
						></textarea>
						<p class="description">
							<?php esc_html_e( 'One user meta key per line. These will be synced as custom fields.', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>

		<div class="ghl-sync-actions" style="margin-top: 20px; padding: 15px; background: #f9f9f9; border-left: 4px solid #7e3bd0;">
			<h3><?php esc_html_e( 'Manual Sync Actions', 'ghl-crm-integration' ); ?></h3>
			<p>
				<button type="button" class="button button-secondary" id="sync-all-users">
					<span class="dashicons dashicons-update"></span>
					<?php esc_html_e( 'Sync All Users Now', 'ghl-crm-integration' ); ?>
				</button>
				<button type="button" class="button button-secondary" id="sync-new-users">
					<span class="dashicons dashicons-plus-alt"></span>
					<?php esc_html_e( 'Sync New Users Only', 'ghl-crm-integration' ); ?>
				</button>
				<button type="button" class="button button-secondary" id="sync-updated-users">
					<span class="dashicons dashicons-update-alt"></span>
					<?php esc_html_e( 'Sync Updated Users', 'ghl-crm-integration' ); ?>
				</button>
			</p>
			<p class="description">
				<?php esc_html_e( 'These actions will queue users for background sync processing.', 'ghl-crm-integration' ); ?>
			</p>
		</div>

		<p class="submit">
			<button type="submit" class="button button-primary">
				<?php esc_html_e( 'Save Field Sync Settings', 'ghl-crm-integration' ); ?>
			</button>
		</p>
	</form>
</div>
