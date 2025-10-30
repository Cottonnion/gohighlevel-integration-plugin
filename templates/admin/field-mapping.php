<?php
/**
 * Template: Field Mapping Page (Dynamic)
 *
 * @package GHL_CRM_Integration
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// WordPress base user fields (stored in wp_users table)
$base_user_fields = array(
	'user_login'      => __( 'Username', 'ghl-crm-integration' ),
	'user_email'      => __( 'Email', 'ghl-crm-integration' ),
	'display_name'    => __( 'Display Name', 'ghl-crm-integration' ),
	'user_url'        => __( 'Website', 'ghl-crm-integration' ),
	'user_registered' => __( 'Registration Date', 'ghl-crm-integration' ),
);

// WordPress default user meta fields (standard across all WP installs)
$default_user_meta = array(
	'first_name'  => __( 'First Name', 'ghl-crm-integration' ),
	'last_name'   => __( 'Last Name', 'ghl-crm-integration' ),
	'nickname'    => __( 'Nickname', 'ghl-crm-integration' ),
	'description' => __( 'Biographical Info', 'ghl-crm-integration' ),
);

// Get contact methods (phone, address, etc. from plugins/themes)
$contact_methods = apply_filters( 'user_contactmethods', array() );

// Combine base fields and default meta for "Default WordPress Fields" section
$default_wp_fields = array_merge( $base_user_fields, $default_user_meta, $contact_methods );

// Define fields to exclude from custom fields (internal WordPress/plugin management fields)
$excluded_meta_keys = array(
	// WordPress core admin preferences
	'rich_editing',
	'syntax_highlighting',
	'comment_shortcuts',
	'admin_color',
	'use_ssl',
	'show_admin_bar_front',
	'show_welcome_panel',
	'locale',
	
	// WordPress capabilities and permissions
	'wp_capabilities',
	'wp_user_level',
	
	// WordPress admin UI state
	'dismissed_wp_pointers',
	'closedpostboxes_dashboard',
	'metaboxhidden_dashboard',
	'wp_dashboard_quick_press_last_post_id',
	'wp_metaboxhidden_nav-menus',
	'wp_persisted_preferences',
	
	// Security/session data
	'session_tokens',
	
	// BuddyPress/BuddyBoss internal fields
	'bp_xprofile_visibility_levels',
	'last_activity',
	
	// Other internal fields
	'community-invites-sent',
	'wp_user-settings',
	'wp_user-settings-time',
);

// Get all unique meta keys from all users
global $wpdb;
$all_meta_keys = $wpdb->get_col( 
	"SELECT DISTINCT meta_key FROM {$wpdb->usermeta} ORDER BY meta_key" 
);

$custom_user_fields = array();

// Filter meta keys to find custom fields
foreach ( $all_meta_keys as $meta_key ) {
	// Skip if it's a default WordPress meta field
	if ( isset( $default_user_meta[ $meta_key ] ) ) {
		continue;
	}
	
	// Skip if in excluded list
	if ( in_array( $meta_key, $excluded_meta_keys, true ) ) {
		continue;
	}
	
	// Skip WordPress internal fields (starting with wp_ or _)
	if ( strpos( $meta_key, 'wp_' ) === 0 || strpos( $meta_key, '_' ) === 0 ) {
		continue;
	}
	
	// Skip BuddyPress internal fields (starting with bp_)
	if ( strpos( $meta_key, 'bp_' ) === 0 ) {
		continue;
	}
	
	// Add to custom fields list with a formatted label
	$custom_user_fields[ $meta_key ] = ucwords( str_replace( array( '_', '-' ), ' ', $meta_key ) );
}

// Get BuddyBoss/BuddyPress XProfile fields if active
$buddyboss_fields = array();
if ( function_exists( 'bp_is_active' ) && bp_is_active( 'xprofile' ) ) {
	// Get all profile field groups with their fields
	$profile_groups = BP_XProfile_Group::get( array(
		'fetch_fields' => true,
	) );
	
	if ( ! empty( $profile_groups ) ) {
		foreach ( $profile_groups as $group ) {
			if ( ! empty( $group->fields ) ) {
				foreach ( $group->fields as $field ) {
					// Create a unique key for the field
					$field_key = 'xprofile_' . $field->id;
					
					// Skip the "Name" field (ID 1) as it's usually handled by first_name/last_name
					if ( $field->id == 1 ) {
						continue;
					}
					
					// Add field with group context
					$buddyboss_fields[ $field_key ] = sprintf(
						'%s (%s)',
						$field->name,
						$group->name
					);
				}
			}
		}
	}
}

// Sort custom fields alphabetically
asort( $custom_user_fields );
asort( $buddyboss_fields );

// GoHighLevel contact field list (placeholder - will be loaded dynamically via AJAX on page load)
// This is just a fallback in case AJAX fails
$ghl_fields = array(
	'' => __( '— Loading fields... —', 'ghl-crm-integration' ),
);

// Calculate total fields
$total_fields = count( $default_wp_fields ) + count( $custom_user_fields ) + count( $buddyboss_fields );

// Get current field mappings
$settings_manager = \GHL_CRM\Core\SettingsManager::get_instance();
$settings         = $settings_manager->get_settings_array();
$saved_mappings   = $settings['user_field_mapping'] ?? [];
?>
<div class="wrap ghl-crm-field-mapping">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	
	<!-- Message Area for AJAX responses -->
	<div id="ghl-field-mapping-messages"></div>
	
	<div class="notice notice-info">
		<p>
			<?php esc_html_e( 'Map WordPress user fields to GoHighLevel contact fields. Only mapped fields will be synchronized. Select "Do Not Sync" to exclude a field from synchronization.', 'ghl-crm-integration' ); ?>
		</p>
	</div>

	<div style="margin: 20px 0;">
		<button type="button" id="ghl-load-custom-fields" class="button button-secondary">
			<span class="dashicons dashicons-update" style="margin-top: 3px;"></span>
			<?php esc_html_e( 'Reload Fields from GoHighLevel', 'ghl-crm-integration' ); ?>
		</button>
		<span id="ghl-custom-fields-status" style="margin-left: 10px;">
			<span style="color: #666;">
				<span class="dashicons dashicons-update-alt" style="animation: rotation 2s infinite linear; margin-top: 3px;"></span>
				<?php esc_html_e( 'Loading fields...', 'ghl-crm-integration' ); ?>
			</span>
		</span>
	</div>

	<form id="ghl-field-mapping-form" method="post" action="">
		<?php wp_nonce_field( 'ghl_crm_field_mapping', 'ghl_crm_mapping_nonce' ); ?>
		
		<h2><?php esc_html_e( 'User Contact Fields', 'ghl-crm-integration' ); ?></h2>
		<p class="description">
			<?php 
			printf(
				/* translators: %d: number of WordPress fields found */
				esc_html__( 'Found %d WordPress user fields available for mapping.', 'ghl-crm-integration' ),
				esc_html( $total_fields )
			);
			?>
		</p>

		<!-- Default WordPress Fields -->
		<h3><?php esc_html_e( 'Default WordPress Fields', 'ghl-crm-integration' ); ?></h3>
		<p class="description" style="margin-bottom: 15px;">
			<?php esc_html_e( 'Standard WordPress user profile fields available on all sites.', 'ghl-crm-integration' ); ?>
		</p>
		
		<table class="form-table widefat striped" role="presentation">
			<thead>
				<tr>
					<th style="width: 30%;"><?php esc_html_e( 'WordPress Field', 'ghl-crm-integration' ); ?></th>
					<th style="width: 35%;"><?php esc_html_e( 'GoHighLevel Field', 'ghl-crm-integration' ); ?></th>
					<th style="width: 35%;"><?php esc_html_e( 'Sync Direction', 'ghl-crm-integration' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $default_wp_fields as $key => $label ) : 
					$saved_ghl_field = isset( $saved_mappings[ $key ]['ghl_field'] ) ? $saved_mappings[ $key ]['ghl_field'] : '';
					$saved_direction = isset( $saved_mappings[ $key ]['direction'] ) ? $saved_mappings[ $key ]['direction'] : 'both';
				?>
					<tr>
						<td>
							<strong><?php echo esc_html( $label ); ?></strong><br>
							<code style="color: #666;"><?php echo esc_html( $key ); ?></code>
						</td>
						<td>
							<select name="ghl_field_<?php echo esc_attr( $key ); ?>" class="regular-text">
								<?php foreach ( $ghl_fields as $ghl_key => $ghl_label ) : ?>
									<option value="<?php echo esc_attr( $ghl_key ); ?>" <?php selected( $saved_ghl_field, $ghl_key ); ?>>
										<?php echo esc_html( $ghl_label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
						<td>
							<select name="sync_direction_<?php echo esc_attr( $key ); ?>">
								<option value="both" <?php selected( $saved_direction, 'both' ); ?>><?php esc_html_e( '↔ Both Ways', 'ghl-crm-integration' ); ?></option>
								<option value="to_ghl" <?php selected( $saved_direction, 'to_ghl' ); ?>><?php esc_html_e( '→ To GoHighLevel Only', 'ghl-crm-integration' ); ?></option>
								<option value="from_ghl" <?php selected( $saved_direction, 'from_ghl' ); ?>><?php esc_html_e( '← From GoHighLevel Only', 'ghl-crm-integration' ); ?></option>
							</select>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( ! empty( $buddyboss_fields ) ) : ?>
			<!-- BuddyBoss Profile Fields -->
			<h3 style="margin-top: 30px;"><?php esc_html_e( 'BuddyBoss Profile Fields', 'ghl-crm-integration' ); ?></h3>
			<p class="description" style="margin-bottom: 15px;">
				<?php 
				printf(
					/* translators: %d: number of BuddyBoss fields found */
					esc_html__( 'Custom profile fields from BuddyBoss (%d fields found).', 'ghl-crm-integration' ),
					count( $buddyboss_fields )
				);
				?>
			</p>
			
			<table class="form-table widefat striped" role="presentation">
				<thead>
					<tr>
						<th style="width: 30%;"><?php esc_html_e( 'BuddyBoss Field', 'ghl-crm-integration' ); ?></th>
						<th style="width: 35%;"><?php esc_html_e( 'GoHighLevel Field', 'ghl-crm-integration' ); ?></th>
						<th style="width: 35%;"><?php esc_html_e( 'Sync Direction', 'ghl-crm-integration' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $buddyboss_fields as $key => $label ) : 
						$saved_ghl_field = isset( $saved_mappings[ $key ]['ghl_field'] ) ? $saved_mappings[ $key ]['ghl_field'] : '';
						$saved_direction = isset( $saved_mappings[ $key ]['direction'] ) ? $saved_mappings[ $key ]['direction'] : 'both';
					?>
						<tr>
							<td>
								<strong><?php echo esc_html( $label ); ?></strong><br>
								<code style="color: #666;"><?php echo esc_html( $key ); ?></code>
							</td>
							<td>
								<select name="ghl_field_<?php echo esc_attr( $key ); ?>" class="regular-text">
									<?php foreach ( $ghl_fields as $ghl_key => $ghl_label ) : ?>
										<option value="<?php echo esc_attr( $ghl_key ); ?>" <?php selected( $saved_ghl_field, $ghl_key ); ?>>
											<?php echo esc_html( $ghl_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
							<td>
								<select name="sync_direction_<?php echo esc_attr( $key ); ?>">
									<option value="both" <?php selected( $saved_direction, 'both' ); ?>><?php esc_html_e( '↔ Both Ways', 'ghl-crm-integration' ); ?></option>
									<option value="to_ghl" <?php selected( $saved_direction, 'to_ghl' ); ?>><?php esc_html_e( '→ To GoHighLevel Only', 'ghl-crm-integration' ); ?></option>
									<option value="from_ghl" <?php selected( $saved_direction, 'from_ghl' ); ?>><?php esc_html_e( '← From GoHighLevel Only', 'ghl-crm-integration' ); ?></option>
								</select>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<?php if ( ! empty( $custom_user_fields ) ) : ?>
			<!-- Custom Fields -->
			<h3 style="margin-top: 30px;"><?php esc_html_e( 'Custom & Plugin Fields', 'ghl-crm-integration' ); ?></h3>
			<p class="description" style="margin-bottom: 15px;">
				<?php 
				printf(
					/* translators: %d: number of custom fields found */
					esc_html__( 'Custom user meta fields and fields added by other plugins or themes (%d fields found).', 'ghl-crm-integration' ),
					count( $custom_user_fields )
				);
				?>
			</p>
			
			<table class="form-table widefat striped" role="presentation">
				<thead>
					<tr>
						<th style="width: 30%;"><?php esc_html_e( 'WordPress Field', 'ghl-crm-integration' ); ?></th>
						<th style="width: 35%;"><?php esc_html_e( 'GoHighLevel Field', 'ghl-crm-integration' ); ?></th>
						<th style="width: 35%;"><?php esc_html_e( 'Sync Direction', 'ghl-crm-integration' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $custom_user_fields as $key => $label ) : 
						$saved_ghl_field = isset( $saved_mappings[ $key ]['ghl_field'] ) ? $saved_mappings[ $key ]['ghl_field'] : '';
						$saved_direction = isset( $saved_mappings[ $key ]['direction'] ) ? $saved_mappings[ $key ]['direction'] : 'both';
					?>
						<tr>
							<td>
								<strong><?php echo esc_html( $label ); ?></strong><br>
								<code style="color: #666;"><?php echo esc_html( $key ); ?></code>
							</td>
							<td>
								<select name="ghl_field_<?php echo esc_attr( $key ); ?>" class="regular-text">
									<?php foreach ( $ghl_fields as $ghl_key => $ghl_label ) : ?>
										<option value="<?php echo esc_attr( $ghl_key ); ?>" <?php selected( $saved_ghl_field, $ghl_key ); ?>>
											<?php echo esc_html( $ghl_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
							<td>
								<select name="sync_direction_<?php echo esc_attr( $key ); ?>">
									<option value="both" <?php selected( $saved_direction, 'both' ); ?>><?php esc_html_e( '↔ Both Ways', 'ghl-crm-integration' ); ?></option>
									<option value="to_ghl" <?php selected( $saved_direction, 'to_ghl' ); ?>><?php esc_html_e( '→ To GoHighLevel Only', 'ghl-crm-integration' ); ?></option>
									<option value="from_ghl" <?php selected( $saved_direction, 'from_ghl' ); ?>><?php esc_html_e( '← From GoHighLevel Only', 'ghl-crm-integration' ); ?></option>
								</select>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<?php if ( empty( $buddyboss_fields ) && empty( $custom_user_fields ) ) : ?>
			<div class="notice notice-warning inline" style="margin-top: 30px;">
				<p><?php esc_html_e( 'No custom fields found. Custom fields will appear here once they are added to user profiles by plugins or themes.', 'ghl-crm-integration' ); ?></p>
			</div>
		<?php endif; ?>

		<?php submit_button( __( 'Save Field Mapping', 'ghl-crm-integration' ) ); ?>
	</form>
</div>

<script>
jQuery(document).ready(function($) {
	// Function to load GHL fields
	function loadGHLFields(isInitialLoad) {
		const $button = $('#ghl-load-custom-fields');
		const $status = $('#ghl-custom-fields-status');
		const $icon = $button.find('.dashicons');
		
		// Show loading state
		$button.prop('disabled', true);
		$icon.removeClass('dashicons-update').addClass('dashicons-update-alt').css('animation', 'rotation 2s infinite linear');
		
		if (!isInitialLoad) {
			$status.html('<span style="color: #999;"><span class="dashicons dashicons-update-alt" style="animation: rotation 2s infinite linear; margin-top: 3px;"></span> Loading fields...</span>');
		}
		
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'ghl_crm_get_custom_fields',
				nonce: '<?php echo wp_create_nonce( 'ghl_crm_field_mapping_nonce' ); ?>'
			},
			success: function(response) {
				$button.prop('disabled', false);
				$icon.removeClass('dashicons-update-alt').addClass('dashicons-update').css('animation', '');
				
				if (response.success && response.data.fields) {
					// Update all GHL field dropdowns
					const fields = response.data.fields;
					let fieldCount = Object.keys(fields).length;
					
					$('select[name^="ghl_field_"]').each(function() {
						const $select = $(this);
						const currentValue = $select.val();
						
						// Clear existing options
						$select.empty();
						
						// Add all fields (including custom fields)
						$.each(fields, function(key, label) {
							const $option = $('<option></option>')
								.attr('value', key)
								.text(label);
							
							// Restore previous selection if it exists
							if (key === currentValue) {
								$option.attr('selected', 'selected');
							}
							
							$select.append($option);
						});
					});
					
					// Show success message
					const customCount = response.data.count || 0;
					let message = '✅ Loaded ' + fieldCount + ' fields';
					if (customCount > 0) {
						message += ' (including ' + customCount + ' custom fields)';
					}
					$status.html('<span style="color: #46b450;">' + message + '</span>');
					
					// Show notice at top only on manual reload
					if (!isInitialLoad) {
						$('#ghl-field-mapping-messages').html(
							'<div class="notice notice-success is-dismissible"><p>' + message + '</p></div>'
						);
					}
					
					setTimeout(function() {
						$status.fadeOut();
					}, 5000);
					
				} else {
					$status.html('<span style="color: #dc3232;">⚠ Failed to load fields</span>');
					if (response.data && response.data.error) {
						console.error('GHL Field Load Error:', response.data.error);
					}
				}
			},
			error: function(xhr, status, error) {
				$button.prop('disabled', false);
				$icon.removeClass('dashicons-update-alt').addClass('dashicons-update').css('animation', '');
				$status.html('<span style="color: #dc3232;">⚠ Error: ' + error + '</span>');
			}
		});
	}
	
	// Auto-load fields on page load
	loadGHLFields(true);
	
	// Handle manual reload button click
	$('#ghl-load-custom-fields').on('click', function() {
		loadGHLFields(false);
	});
});

// Add rotation animation
const style = document.createElement('style');
style.textContent = `
	@keyframes rotation {
		from { transform: rotate(0deg); }
		to { transform: rotate(359deg); }
	}
`;
document.head.appendChild(style);
</script>