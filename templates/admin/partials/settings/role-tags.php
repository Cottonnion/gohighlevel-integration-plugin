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
$settings = $settings_manager->get_settings_array();

// Get all WordPress roles
$wp_roles = wp_roles()->get_names();

// Load saved role tag mappings
$role_tags = $settings['role_tags'] ?? [];
$global_tags_raw = $settings['global_tags'] ?? [];
// Convert array to comma-separated string for display, or keep as is if string
$global_tags = is_array( $global_tags_raw ) ? implode( ',', $global_tags_raw ) : $global_tags_raw;
$sync_existing_tags = $settings['sync_existing_tags'] ?? true;
$tag_prefix = $settings['tag_prefix'] ?? '';
?>

<div class="ghl-settings-wrapper">
	<?php wp_nonce_field( 'ghl_crm_settings_nonce', 'ghl_crm_nonce' ); ?>
	
	<!-- Role Tags Section -->
	<div class="ghl-settings-section ghl-settings-card">
		<div class="ghl-settings-header">
			<h2>
				<span class="dashicons dashicons-admin-users"></span>
				<?php esc_html_e( 'Role Based Tags', 'ghl-crm-integration' ); ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Automatically assign GoHighLevel tags based on WordPress user roles. Tags will be added when users are assigned a role and can be removed when they lose that role.', 'ghl-crm-integration' ); ?>
			</p>
		</div>
		
		<hr>
		
		<div class="ghl-form-builder">
			<form class="ghl-form" method="post">

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
					<?php foreach ( $wp_roles as $role_key => $role_name ) : 
						$role_data = $role_tags[ $role_key ] ?? [];
						$tags_raw = $role_data['tags'] ?? '';
						// Convert to string if array for compatibility
						$tags_value = is_array( $tags_raw ) ? implode( ',', $tags_raw ) : $tags_raw;
						$auto_apply = $role_data['auto_apply'] ?? true;
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
								data-placeholder="<?php 
									/* translators: %s: user role name (e.g., administrator, editor) */
									printf( esc_attr__( 'e.g., %s, member, active', 'ghl-crm-integration' ), esc_attr( strtolower( $role_name ) ) ); 
								?>">
								<?php
								if ( ! empty( $tags_value ) ) {
									$tags_array = array_map( 'trim', explode( ',', $tags_value ) );
									foreach ( $tags_array as $tag ) {
										if ( ! empty( $tag ) ) {
											?>
											<option value="<?php echo esc_attr( $tag ); ?>" selected="selected">
												<?php echo esc_html( $tag ); ?>
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
						<select 
							id="global_tags" 
							name="global_tags[]" 
							multiple 
							class="ghl-role-tags-select"
							style="width: 100%; max-width: 600px;"
							data-placeholder="<?php esc_attr_e( 'Select or type tags to apply to all synced contacts...', 'ghl-crm-integration' ); ?>">
							<?php
							if ( ! empty( $global_tags ) ) {
								$global_tags_array = array_map( 'trim', explode( ',', $global_tags ) );
								foreach ( $global_tags_array as $tag ) {
									if ( ! empty( $tag ) ) {
										?>
										<option value="<?php echo esc_attr( $tag ); ?>" selected="selected">
											<?php echo esc_html( $tag ); ?>
										</option>
										<?php
									}
								}
							}
							?>
						</select>
						<p class="description" style="margin-top: 8px;">
							<?php esc_html_e( 'Tags to apply to all synced contacts regardless of role.', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="sync_existing_tags">
							<?php esc_html_e( 'Preserve Existing Tags', 'ghl-crm-integration' ); ?>
						</label>
					</th>
					<td>
						<label class="ghl-checkbox <?php echo $sync_existing_tags ? 'is-checked' : ''; ?>">
							<input type="checkbox" 
								   class="ghl-checkbox-original"
								   id="sync_existing_tags" 
								   name="sync_existing_tags" 
								   value="1"
								   <?php checked( $sync_existing_tags ); ?>>
							<span class="ghl-checkbox-input <?php echo $sync_existing_tags ? 'is-checked' : ''; ?>">
								<span class="ghl-checkbox-inner"></span>
							</span>
							<span class="ghl-checkbox-label">
								<?php esc_html_e( 'Keep existing GoHighLevel tags when syncing', 'ghl-crm-integration' ); ?>
							</span>
						</label>
						<p class="description" style="margin-left: 40px; margin-top: 8px;">
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
							value="<?php echo esc_attr( $tag_prefix ); ?>"
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
							<option value=""><?php esc_html_e( '-- Select a Role --', 'ghl-crm-integration' ); ?></option>
							<?php foreach ( $wp_roles as $role_key => $role_name ) : ?>
								<option value="<?php echo esc_attr( $role_key ); ?>">
									<?php echo esc_html( $role_name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php esc_html_e( 'Select the user role to perform bulk operations on.', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="bulk_tags_input">
							<?php esc_html_e( 'Tags to Add/Remove', 'ghl-crm-integration' ); ?>
						</label>
					</th>
					<td>
						<select 
							id="bulk_tags_input" 
							multiple
							class="ghl-role-tags-select"
							style="width: 100%; max-width: 600px;"
							data-placeholder="<?php esc_attr_e( 'Select or type tags...', 'ghl-crm-integration' ); ?>">
						</select>
						<p class="description">
							<?php esc_html_e( 'Select existing tags or type new ones to add/remove.', 'ghl-crm-integration' ); ?>
						</p>
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
						<button type="button" class="button button-secondary" id="bulk-remove-tags" style="margin-left: 10px;">
							<span class="dashicons dashicons-minus"></span>
							<?php esc_html_e( 'Remove Tags from Role', 'ghl-crm-integration' ); ?>
						</button>
						<p class="description" style="margin-top: 10px;">
							<?php esc_html_e( 'These operations will queue all users with the selected role for background processing.', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>

				<div class="ghl-form-item">
					<div class="ghl-form-item-footer" style="margin-top: 30px;">
						<button type="button" class="ghl-button ghl-button-primary ghl-save-settings-btn">
							<?php esc_html_e( 'Save Role Tag Settings', 'ghl-crm-integration' ); ?>
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
				<?php esc_html_e( 'How It Works', 'ghl-crm-integration' ); ?>
			</h3>
			<div class="ghl-help-content">
				<p><strong><?php esc_html_e( 'Role Tags:', 'ghl-crm-integration' ); ?></strong> 
					<?php esc_html_e( 'Use Select2 dropdowns to choose or create tags for each role. You can search existing tags from GHL or type new ones.', 'ghl-crm-integration' ); ?>
				</p>
				<p><strong><?php esc_html_e( 'Auto-Apply:', 'ghl-crm-integration' ); ?></strong> 
					<?php esc_html_e( 'When enabled, tags will be automatically added to the user\'s GHL contact when they are assigned this role.', 'ghl-crm-integration' ); ?>
				</p>
				<p><strong><?php esc_html_e( 'Remove on Role Change:', 'ghl-crm-integration' ); ?></strong> 
					<?php esc_html_e( 'When enabled, tags will be removed from the user\'s GHL contact when they lose this role.', 'ghl-crm-integration' ); ?>
				</p>
				<p><strong><?php esc_html_e( 'Global Tags:', 'ghl-crm-integration' ); ?></strong> 
					<?php esc_html_e( 'These tags will be applied to ALL users when they sync with GoHighLevel, regardless of their role.', 'ghl-crm-integration' ); ?>
				</p>
				<p><strong><?php esc_html_e( 'Tag Prefix:', 'ghl-crm-integration' ); ?></strong> 
					<?php esc_html_e( 'Automatically prepends text to all WordPress-generated tags for easy identification (e.g., "wp-subscriber", "wp-administrator").', 'ghl-crm-integration' ); ?>
				</p>
				<p><strong><?php esc_html_e( 'Bulk Operations:', 'ghl-crm-integration' ); ?></strong> 
					<?php esc_html_e( 'Use bulk operations to add or remove tags for all existing users with a specific role. Users will be queued for background processing.', 'ghl-crm-integration' ); ?>
				</p>
			</div>
		</div>
	</div>

</div><!-- .ghl-settings-wrapper -->

<style>
.ghl-settings-role-tags .wp-list-table th {
	font-weight: 600;
}
.ghl-settings-role-tags .wp-list-table td {
	vertical-align: middle;
}
.ghl-help-box {
	background: #f0f6fc;
	padding: 20px;
	border-radius: 4px;
	border-left: 4px solid #2271b1;
}
.ghl-help-box h3 {
	margin-top: 0;
	color: #2271b1;
	display: flex;
	align-items: center;
	gap: 8px;
}
.ghl-help-box .dashicons {
	font-size: 20px;
	width: 20px;
	height: 20px;
}
.ghl-help-content p {
	margin: 10px 0;
}
.ghl-help-content p:first-child {
	margin-top: 0;
}
.ghl-help-content p:last-child {
	margin-bottom: 0;
}
</style>

<script type="text/javascript">
(function($) {
	'use strict';

	// Initialize Select2 for all role tag fields
	$(document).ready(function() {
		if (typeof $.fn.select2 !== 'undefined') {
			$('.ghl-role-tags-select').select2({
				tags: true,
				tokenSeparators: [','],
				allowClear: true,
				width: '100%',
				ajax: {
					url: ajaxurl,
					type: 'POST',
					dataType: 'json',
					delay: 250,
					data: function(params) {
						return {
							action: 'ghl_crm_get_tags',
							nonce: $('#ghl_crm_nonce').val(),
							search: params.term || ''
						};
					},
					processResults: function(response) {
						if (response.success && response.data && response.data.tags) {
							return {
								results: response.data.tags.map(function(tag) {
									// Handle both object format {id, name} and string format
									if (typeof tag === 'object' && tag !== null) {
										return {
											id: String(tag.name || tag.id || ''),
											text: String(tag.name || tag.id || '')
										};
									}
									// Fallback for string format
									return {
										id: String(tag || ''),
										text: String(tag || '')
									};
								})
							};
						}
						return { results: [] };
					},
					cache: true
				},
				minimumInputLength: 0,
				createTag: function(params) {
					var term = $.trim(params.term);
					if (term === '') {
						return null;
					}
					return {
						id: term,
						text: term,
						newTag: true
					};
				}
			});
		}
	});

	// Bulk add tags
	$('#bulk-add-tags').on('click', function() {
		const role = $('#bulk_role_select').val();
		const tagsArray = $('#bulk_tags_input').val();
		
		if (!role || !tagsArray || tagsArray.length === 0) {
			alert('<?php esc_html_e( 'Please select a role and enter tags.', 'ghl-crm-integration' ); ?>');
			return;
		}

		if (!confirm('<?php esc_html_e( 'This will queue all users with the selected role for tag addition. Continue?', 'ghl-crm-integration' ); ?>')) {
			return;
		}

		const $button = $(this);
		const originalText = $button.html();
		$button.prop('disabled', true).html('<span class="dashicons dashicons-update ghl-spin"></span> <?php esc_html_e( 'Processing...', 'ghl-crm-integration' ); ?>');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'ghl_crm_bulk_add_role_tags',
				nonce: $('#ghl_crm_nonce').val(),
				role: role,
				tags: tagsArray.join(',')
			},
			success: function(response) {
				if (response.success) {
					alert('✓ ' + (response.data.message || '<?php esc_html_e( 'Users queued successfully!', 'ghl-crm-integration' ); ?>'));
					$('#bulk_tags_input').val(null).trigger('change');
				} else {
					alert('✗ ' + (response.data.message || '<?php esc_html_e( 'Failed to queue users.', 'ghl-crm-integration' ); ?>'));
				}
			},
			error: function(xhr, status, error) {
				console.error('AJAX Error:', {xhr, status, error});
				alert('✗ <?php esc_html_e( 'An error occurred. Please try again.', 'ghl-crm-integration' ); ?>\n\nError: ' + error);
			},
			complete: function() {
				$button.prop('disabled', false).html(originalText);
			}
		});
	});

	// Bulk remove tags
	$('#bulk-remove-tags').on('click', function() {
		const role = $('#bulk_role_select').val();
		const tagsArray = $('#bulk_tags_input').val();
		
		if (!role || !tagsArray || tagsArray.length === 0) {
			alert('<?php esc_html_e( 'Please select a role and enter tags.', 'ghl-crm-integration' ); ?>');
			return;
		}

		if (!confirm('<?php esc_html_e( 'This will queue all users with the selected role for tag removal. Continue?', 'ghl-crm-integration' ); ?>')) {
			return;
		}

		const $button = $(this);
		const originalText = $button.html();
		$button.prop('disabled', true).html('<span class="dashicons dashicons-update ghl-spin"></span> <?php esc_html_e( 'Processing...', 'ghl-crm-integration' ); ?>');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'ghl_crm_bulk_remove_role_tags',
				nonce: $('#ghl_crm_nonce').val(),
				role: role,
				tags: tagsArray.join(',')
			},
			success: function(response) {
				if (response.success) {
					alert('✓ ' + (response.data.message || '<?php esc_html_e( 'Users queued successfully!', 'ghl-crm-integration' ); ?>'));
					$('#bulk_tags_input').val(null).trigger('change');
				} else {
					alert('✗ ' + (response.data.message || '<?php esc_html_e( 'Failed to queue users.', 'ghl-crm-integration' ); ?>'));
				}
			},
			error: function(xhr, status, error) {
				console.error('AJAX Error:', {xhr, status, error});
				alert('✗ <?php esc_html_e( 'An error occurred. Please try again.', 'ghl-crm-integration' ); ?>\n\nError: ' + error);
			},
			complete: function() {
				$button.prop('disabled', false).html(originalText);
			}
		});
	});

})(jQuery);
</script>
