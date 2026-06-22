<?php
/**
 * Role Based Tags Settings Partial
 *
 * @package GHL_CRM_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings_manager = \GHL_CRM\Core\SettingsManager::get_instance();
$settings         = $settings_manager->get_settings_array();

// Get all WordPress roles
$wp_roles_list = wp_roles()->get_names();

// Load saved role tag mappings (location-specific)
$role_tags       = $settings_manager->get_location_role_tags();
$global_tags_raw = $settings_manager->get_location_global_tags();
// Convert array to comma-separated string for display, or keep as is if string
$global_tags            = is_array( $global_tags_raw ) ? implode( ',', $global_tags_raw ) : $global_tags_raw;
$global_tags_pro_active = (bool) apply_filters( 'ghl_crm_global_tags_enabled', false );
$upgrade_url            = apply_filters( 'ghl_crm_upgrade_url', 'https://highlevelsync.com/' );
?>

<div class="ghl-settings-wrapper">
	<?php wp_nonce_field( 'ghl_crm_settings_nonce', 'ghl_crm_nonce' ); ?>
	
	<!-- Role Tags Section -->
	<div class="ghl-settings-section ghl-settings-card">
		<div class="ghl-settings-header">
			<h2>
				<span class="dashicons dashicons-admin-users"></span>
				<?php esc_html_e( 'Role Based Tags', 'syncly' ); ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Automatically assign GoHighLevel tags based on WordPress user roles. Tags will be added when users are assigned a role and can be removed when they lose that role.', 'syncly' ); ?>
			</p>
		</div>
		
		<hr>
		
		<div class="ghl-form-builder">
			<form class="ghl-form" method="post">

		<div class="ghl-table-info">
			<span class="dashicons dashicons-info"></span>
			<p><?php esc_html_e( 'Swipe horizontally to view all columns. Configure tags for each WordPress role by selecting from existing GHL tags or creating new ones.', 'syncly' ); ?></p>
		</div>

		<div class="ghl-role-tag-mappings">
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width: 25%;"><?php esc_html_e( 'WordPress Role', 'syncly' ); ?></th>
						<th style="width: 35%;">
							<?php esc_html_e( 'GoHighLevel Tags', 'syncly' ); ?>
							<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Enter one or more tags to apply to users with this role. Tags are synced to their GoHighLevel contact record. You can select existing tags from the dropdown below.', 'syncly' ); ?>">?</span>
						</th>
						<th style="width: 20%;">
							<?php esc_html_e( 'Auto-Apply', 'syncly' ); ?>
							<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'When enabled, these tags are automatically applied when a user is assigned this role. Disable if you want to manually trigger tag assignment via bulk actions instead.', 'syncly' ); ?>">?</span>
						</th>
						<th style="width: 20%;">
							<?php esc_html_e( 'Remove on Role Change', 'syncly' ); ?>
							<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'When enabled, these tags are removed from the contact when the user loses this role. Useful for access-based tags. Keep disabled if you want to preserve role history in tags.', 'syncly' ); ?>">?</span>
						</th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $wp_roles_list as $role_key => $role_name ) :
						$role_data = $role_tags[ $role_key ] ?? [];
						$tags_raw  = $role_data['tags'] ?? '';
						// Convert to string if array for compatibility
						$tags_value       = is_array( $tags_raw ) ? implode( ',', $tags_raw ) : $tags_raw;
						$auto_apply       = $role_data['auto_apply'] ?? true;
						$remove_on_change = $role_data['remove_on_change'] ?? false;
						?>
						<tr>
							<td>
								<strong><?php echo esc_html( $role_name ); ?></strong>
								<input type="hidden" name="role_tags[<?php echo esc_attr( $role_key ); ?>][role]" value="<?php echo esc_attr( $role_key ); ?>" />
							</td>
							<td>
							<select 
								name="role_tags[<?php echo esc_attr( $role_key ); ?>][tags][]" 
								multiple
								class="ghl-role-tags-select"
								style="width: 100%;"
								data-placeholder="
								<?php
									/* translators: %s: user role name (e.g., administrator, editor) */
									printf( esc_attr__( 'e.g., %s, member, active', 'syncly' ), esc_attr( strtolower( $role_name ) ) );
								?>
								">
								<?php
								if ( ! empty( $tags_value ) ) {
									$tags_array = array_map( 'trim', explode( ',', $tags_value ) );
									foreach ( $tags_array as $tag_name ) {
										if ( ! empty( $tag_name ) ) {
											?>
											<option value="<?php echo esc_attr( $tag_name ); ?>" selected="selected">
												<?php echo esc_html( $tag_name ); ?>
											</option>
											<?php
										}
									}
								}
								?>
							</select>
							</td>
							<td style="text-align: center;">
								<input 
									type="checkbox" 
									name="role_tags[<?php echo esc_attr( $role_key ); ?>][auto_apply]" 
									value="1" 
									<?php checked( $auto_apply ); ?>
								/>
							</td>
							<td style="text-align: center;">
								<input 
									type="checkbox" 
									name="role_tags[<?php echo esc_attr( $role_key ); ?>][remove_on_change]" 
									value="1" 
									<?php checked( $remove_on_change ); ?>
								/>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<hr style="margin: 30px 0;">

		<h3>
			<?php esc_html_e( 'Additional Tag Settings', 'syncly' ); ?>
		</h3>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label<?php echo $global_tags_pro_active ? ' for="global_tags"' : ''; ?>>
							<?php esc_html_e( 'Global Tags', 'syncly' ); ?>
							<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'These tags are applied to EVERY contact synced from WordPress to GoHighLevel, regardless of their role.', 'syncly' ); ?>">?</span>
						</label>
					</th>
					<td>
						<?php if ( $global_tags_pro_active ) : ?>
							<select 
								id="global_tags" 
								name="global_tags[]" 
								multiple 
								class="ghl-role-tags-select"
								style="width: 100%; max-width: 600px;"
								data-placeholder="<?php esc_attr_e( 'Select or type tags to apply to all synced contacts...', 'syncly' ); ?>">
								<?php
								if ( ! empty( $global_tags ) ) {
									$global_tags_array = array_map( 'trim', explode( ',', $global_tags ) );
									foreach ( $global_tags_array as $tag_name ) {
										if ( ! empty( $tag_name ) ) {
											?>
											<option value="<?php echo esc_attr( $tag_name ); ?>" selected="selected">
												<?php echo esc_html( $tag_name ); ?>
											</option>
											<?php
										}
									}
								}
								?>
							</select>
							<p class="description" style="margin-top: 8px;">
								<?php esc_html_e( 'Tags to apply to all synced contacts regardless of role.', 'syncly' ); ?>
							</p>
						<?php else : ?>
							<div style="max-width: 620px; padding: 16px; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.04);">
								<div style="display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 12px;">
									<div>
										<span style="display: inline-flex; padding: 2px 8px; border-radius: 999px; background: #eef2ff; border: 1px solid #c7d2fe; color: #3730a3; font-size: 11px; font-weight: 700; text-transform: uppercase;"><?php esc_html_e( 'Syncly Pro', 'syncly' ); ?></span>
										<p style="margin: 8px 0 0; color: #475569;"><?php esc_html_e( 'Apply location-wide tags to every synced contact without configuring each role separately.', 'syncly' ); ?></p>
									</div>
											<a href="<?php echo esc_url( $upgrade_url ); ?>" class="ghl-button ghl-button-secondary" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Learn More', 'syncly' ); ?></a>
								</div>
								<div aria-hidden="true" style="display: flex; flex-wrap: wrap; gap: 8px; padding: 12px; background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 6px;">
									<span style="padding: 5px 9px; border-radius: 999px; background: #e0f2fe; color: #075985; font-size: 12px; font-weight: 600;">wp-contact</span>
									<span style="padding: 5px 9px; border-radius: 999px; background: #dcfce7; color: #166534; font-size: 12px; font-weight: 600;">site-member</span>
									<span style="padding: 5px 9px; border-radius: 999px; background: #fef3c7; color: #92400e; font-size: 12px; font-weight: 600;">newsletter</span>
								</div>
							</div>
						<?php endif; ?>
					</td>
				</tr>

			</tbody>
		</table>

		<hr style="margin: 30px 0;">

		<div class="ghl-bulk-operations-section">
			<h3><?php esc_html_e( 'Bulk Tag Operations', 'syncly' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'Apply or remove tags for all users with a specific role.', 'syncly' ); ?>
		</p>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="bulk_role_select">
							<?php esc_html_e( 'Select Role', 'syncly' ); ?>
						</label>
					</th>
					<td>
						<select id="bulk_role_select" class="regular-text ghl-select">
							<option value=""><?php esc_html_e( '-- Select a Role --', 'syncly' ); ?></option>
							<?php foreach ( $wp_roles_list as $role_key => $role_name ) : ?>
								<option value="<?php echo esc_attr( $role_key ); ?>">
									<?php echo esc_html( $role_name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php esc_html_e( 'Select the user role to perform bulk operations on.', 'syncly' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="bulk_tags_input">
							<?php esc_html_e( 'Tags to Add/Remove', 'syncly' ); ?>
						</label>
					</th>
					<td>
						<select 
							id="bulk_tags_input" 
							multiple
							class="ghl-role-tags-select"
							style="width: 100%; max-width: 600px;"
							data-placeholder="<?php esc_attr_e( 'Select or type tags...', 'syncly' ); ?>">
						</select>
						<p class="description">
							<?php esc_html_e( 'Select existing tags or type new ones to add/remove.', 'syncly' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<?php esc_html_e( 'Actions', 'syncly' ); ?>
					</th>
					<td>
						<button type="button" class="ghl-button ghl-button-secondary" id="bulk-add-tags">
							<span class="dashicons dashicons-plus-alt"></span>
							<?php esc_html_e( 'Add Tags to Role', 'syncly' ); ?>
						</button>
						<button type="button" class="ghl-button ghl-button-secondary" id="bulk-remove-tags" style="margin-left: 10px;">
							<span class="dashicons dashicons-minus"></span>
							<?php esc_html_e( 'Remove Tags from Role', 'syncly' ); ?>
						</button>
						<p class="description" style="margin-top: 10px;">
							<?php esc_html_e( 'These operations will queue all users with the selected role for background processing.', 'syncly' ); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>
		</div><!-- .ghl-bulk-operations-section -->

				<div class="ghl-form-item">
					<div class="ghl-form-item-footer" style="margin-top: 30px;">
						<button type="button" class="ghl-button ghl-button-primary ghl-save-settings-btn">
							<?php esc_html_e( 'Save Role Tag Settings', 'syncly' ); ?>
						</button>
					</div>
				</div>
			</form>
		</div>
	</div>

	<!-- Help Section -->
	<div class="ghl-settings-section ghl-settings-card" style="margin-top: 20px;">
		<div class="ghl-help-box">
			<h3>
				<span class="dashicons dashicons-info-outline"></span>
				<?php esc_html_e( 'How It Works', 'syncly' ); ?>
			</h3>
			<div class="ghl-help-content">
				<p><strong><?php esc_html_e( 'Role Tags:', 'syncly' ); ?></strong> 
					<?php esc_html_e( 'Use Select2 dropdowns to choose or create tags for each role. You can search existing tags from GHL or type new ones.', 'syncly' ); ?>
				</p>
				<p><strong><?php esc_html_e( 'Auto-Apply:', 'syncly' ); ?></strong> 
					<?php esc_html_e( 'When enabled, tags will be automatically added to the user\'s GHL contact when they are assigned this role.', 'syncly' ); ?>
				</p>
				<p><strong><?php esc_html_e( 'Remove on Role Change:', 'syncly' ); ?></strong> 
					<?php esc_html_e( 'When enabled, tags will be removed from the user\'s GHL contact when they lose this role.', 'syncly' ); ?>
				</p>
				<p><strong><?php esc_html_e( 'Global Tags:', 'syncly' ); ?></strong> 
					<?php esc_html_e( 'These tags will be applied to ALL users when they sync with GoHighLevel, regardless of their role.', 'syncly' ); ?>
				</p>
				<p><strong><?php esc_html_e( 'Tag Prefix:', 'syncly' ); ?></strong> 
					<?php esc_html_e( 'Automatically prepends text to all WordPress-generated tags for easy identification (e.g., "wp-subscriber", "wp-administrator").', 'syncly' ); ?>
				</p>
				<p><strong><?php esc_html_e( 'Bulk Operations:', 'syncly' ); ?></strong> 
					<?php esc_html_e( 'Use bulk operations to add or remove tags for all existing users with a specific role. Users will be queued for background processing.', 'syncly' ); ?>
				</p>
			</div>
		</div>
	</div>

</div><!-- .ghl-settings-wrapper -->