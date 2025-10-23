<?php
/**
 * Role Based Tags Settings Partial
 *
 * @package GHL_CRM_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get all WordPress roles
$wp_roles = wp_roles()->get_names();
?>

<div class="ghl-settings-role-tags">
	<h2><?php esc_html_e( 'Role Based Tags', 'ghl-crm-integration' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Automatically assign GoHighLevel tags based on WordPress user roles.', 'ghl-crm-integration' ); ?>
	</p>

	<form id="ghl-role-tags-settings-form" method="post">
		<?php wp_nonce_field( 'ghl_role_tags_settings', 'ghl_role_tags_nonce' ); ?>

		<div class="ghl-role-tag-mappings">
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width: 25%;"><?php esc_html_e( 'WordPress Role', 'ghl-crm-integration' ); ?></th>
						<th style="width: 35%;"><?php esc_html_e( 'GoHighLevel Tags (comma separated)', 'ghl-crm-integration' ); ?></th>
						<th style="width: 20%;"><?php esc_html_e( 'Auto-Apply', 'ghl-crm-integration' ); ?></th>
						<th style="width: 20%;"><?php esc_html_e( 'Remove on Role Change', 'ghl-crm-integration' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $wp_roles as $role_key => $role_name ) : ?>
						<tr>
							<td>
								<strong><?php echo esc_html( $role_name ); ?></strong>
								<input type="hidden" name="role_tags[<?php echo esc_attr( $role_key ); ?>][role]" value="<?php echo esc_attr( $role_key ); ?>" />
							</td>
							<td>
							// translators: %s: user role name (e.g., administrator, editor)
							<input 
								type="text" 
								name="role_tags[<?php echo esc_attr( $role_key ); ?>][tags]" 
								placeholder="<?php printf( esc_attr__( 'e.g., %s, member, active', 'ghl-crm-integration' ), esc_attr( strtolower( $role_name ) ) ); ?>" 
								class="large-text" 
							/>
							</td>
							<td>
								<input 
									type="checkbox" 
									name="role_tags[<?php echo esc_attr( $role_key ); ?>][auto_apply]" 
									value="1" 
									checked 
								/>
							</td>
							<td>
								<input 
									type="checkbox" 
									name="role_tags[<?php echo esc_attr( $role_key ); ?>][remove_on_change]" 
									value="1" 
								/>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<hr style="margin: 30px 0;">

		<h3><?php esc_html_e( 'Additional Tag Settings', 'ghl-crm-integration' ); ?></h3>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="global_tags">
							<?php esc_html_e( 'Global Tags', 'ghl-crm-integration' ); ?>
						</label>
					</th>
					<td>
						<input 
							type="text" 
							id="global_tags" 
							name="global_tags" 
							placeholder="wordpress, site-member" 
							class="large-text" 
						/>
						<p class="description">
							<?php esc_html_e( 'Comma-separated tags to apply to all synced contacts.', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="registration_source_tag">
							<?php esc_html_e( 'Registration Source Tag', 'ghl-crm-integration' ); ?>
						</label>
					</th>
					<td>
						<label>
							<input type="checkbox" id="registration_source_tag" name="registration_source_tag" value="1" />
							<?php esc_html_e( 'Add "wordpress-registration" tag on new user sync', 'ghl-crm-integration' ); ?>
						</label>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="woocommerce_customer_tag">
							<?php esc_html_e( 'WooCommerce Customer Tag', 'ghl-crm-integration' ); ?>
						</label>
					</th>
					<td>
						<label>
							<input type="checkbox" id="woocommerce_customer_tag" name="woocommerce_customer_tag" value="1" />
							<?php esc_html_e( 'Add "woocommerce-customer" tag for users with orders', 'ghl-crm-integration' ); ?>
						</label>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="sync_existing_tags">
							<?php esc_html_e( 'Preserve Existing Tags', 'ghl-crm-integration' ); ?>
						</label>
					</th>
					<td>
						<label>
							<input type="checkbox" id="sync_existing_tags" name="sync_existing_tags" value="1" checked />
							<?php esc_html_e( 'Keep existing GoHighLevel tags when syncing', 'ghl-crm-integration' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'When disabled, all existing tags will be replaced with role-based tags.', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="tag_prefix">
							<?php esc_html_e( 'Tag Prefix', 'ghl-crm-integration' ); ?>
						</label>
					</th>
					<td>
						<input 
							type="text" 
							id="tag_prefix" 
							name="tag_prefix" 
							placeholder="wp-" 
							class="regular-text" 
						/>
						<p class="description">
							<?php esc_html_e( 'Optional prefix to add to all WordPress-generated tags (e.g., "wp-subscriber").', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>

		<hr style="margin: 30px 0;">

		<h3><?php esc_html_e( 'Bulk Tag Operations', 'ghl-crm-integration' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'Apply or remove tags for all users with a specific role.', 'ghl-crm-integration' ); ?>
		</p>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="bulk_role_select">
							<?php esc_html_e( 'Select Role', 'ghl-crm-integration' ); ?>
						</label>
					</th>
					<td>
						<select id="bulk_role_select" class="regular-text">
							<option value=""><?php esc_html_e( '-- Select a role --', 'ghl-crm-integration' ); ?></option>
							<?php foreach ( $wp_roles as $role_key => $role_name ) : ?>
								<option value="<?php echo esc_attr( $role_key ); ?>">
									<?php echo esc_html( $role_name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="bulk_tags_input">
							<?php esc_html_e( 'Tags to Add/Remove', 'ghl-crm-integration' ); ?>
						</label>
					</th>
					<td>
						<input 
							type="text" 
							id="bulk_tags_input" 
							placeholder="tag1, tag2, tag3" 
							class="large-text" 
						/>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<?php esc_html_e( 'Actions', 'ghl-crm-integration' ); ?>
					</th>
					<td>
						<button type="button" class="button button-secondary" id="bulk-add-tags">
							<span class="dashicons dashicons-plus-alt"></span>
							<?php esc_html_e( 'Add Tags to Role', 'ghl-crm-integration' ); ?>
						</button>
						<button type="button" class="button button-secondary" id="bulk-remove-tags">
							<span class="dashicons dashicons-minus"></span>
							<?php esc_html_e( 'Remove Tags from Role', 'ghl-crm-integration' ); ?>
						</button>
						<p class="description">
							<?php esc_html_e( 'These operations will queue users for background processing.', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary">
				<?php esc_html_e( 'Save Role Tag Settings', 'ghl-crm-integration' ); ?>
			</button>
		</p>
	</form>
</div>
