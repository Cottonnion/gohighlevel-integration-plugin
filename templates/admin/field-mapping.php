<?php
/**
 * Template: Field Mapping Page (Dynamic)
 *
 * @package GHL_CRM_Integration
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Check connection status
$settings_manager = \GHL_CRM\Core\SettingsManager::get_instance();
$settings         = $settings_manager->get_settings_array();
$oauth_handler    = new \GHL_CRM\API\OAuth\OAuthHandler();
$oauth_status     = $oauth_handler->get_connection_status();
$is_connected     = $oauth_status['connected'] || ! empty( $settings['api_token'] );

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
$saved_mappings = $settings['user_field_mapping'] ?? [];
?>
<div class="wrap ghl-crm-field-mapping">
	
	<?php if ( ! $is_connected ) : ?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'Not Connected', 'ghl-crm-integration' ); ?></strong><br>
				<?php
				printf(
					/* translators: %s: Link to dashboard page */
					esc_html__( 'Please connect to GoHighLevel in %s first.', 'ghl-crm-integration' ),
					sprintf(
						'<a href="%s">%s</a>',
						esc_url( admin_url( 'admin.php?page=ghl-crm-admin' ) ),
						esc_html__( 'Dashboard', 'ghl-crm-integration' )
					)
				);
				?>
			</p>
		</div>
		<?php return; ?>
	<?php endif; ?>
	
	<?php
	// Check scope access for Contacts and Custom Fields
	\GHL_CRM\Core\ScopeChecker::render_scope_notice( 'contacts' );
	\GHL_CRM\Core\ScopeChecker::render_scope_notice( 'custom_fields' );
	?>

	<!-- Message Area for AJAX responses -->
	<div id="ghl-field-mapping-messages"></div>

	<div style="margin: 20px 0;">
		<button type="button" id="ghl-load-custom-fields" class="ghl-button ghl-button-primary" data-nonce="<?php echo esc_attr( wp_create_nonce( 'ghl_crm_field_mapping_nonce' ) ); ?>">
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
		

		
		<table class="form-table widefat striped" role="presentation">
			<thead>
				<tr>
					<th style="width: 30%;"><?php esc_html_e( 'WordPress Field', 'ghl-crm-integration' ); ?></th>
					<th style="width: 35%;"><?php esc_html_e( 'GoHighLevel Field', 'ghl-crm-integration' ); ?></th>
					<th style="width: 35%;"><?php esc_html_e( 'Sync Direction', 'ghl-crm-integration' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php 
				foreach ( $default_wp_fields as $key => $label ) : 
					// Set default mappings if not configured yet
					$default_mappings = [
						'user_email'  => 'email',
						'first_name'  => 'firstName',
						'last_name'   => 'lastName',
					];
					
					// Use saved value if exists, otherwise use default mapping if available
					if ( isset( $saved_mappings[ $key ]['ghl_field'] ) ) {
						$saved_ghl_field = $saved_mappings[ $key ]['ghl_field'];
					} elseif ( isset( $default_mappings[ $key ] ) ) {
						$saved_ghl_field = $default_mappings[ $key ];
					} else {
						$saved_ghl_field = '';
					}
					
					$saved_direction = isset( $saved_mappings[ $key ]['direction'] ) ? $saved_mappings[ $key ]['direction'] : 'both';
					
					// Email field should be disabled (required by GHL)
					$is_email_field = ( $key === 'user_email' );
				?>
					<tr>
						<td>
							<strong><?php echo esc_html( $label ); ?></strong><br>
							<code style="color: #666;"><?php echo esc_html( $key ); ?></code>
							<?php if ( $is_email_field ) : ?>
								<br><span style="color: #d63638; font-size: 11px;"><?php esc_html_e( '* Required field', 'ghl-crm-integration' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<select name="ghl_field_<?php echo esc_attr( $key ); ?>" class="regular-text" data-saved-value="<?php echo esc_attr( $saved_ghl_field ); ?>" <?php echo $is_email_field ? 'disabled' : ''; ?>>
								<?php foreach ( $ghl_fields as $ghl_key => $ghl_label ) : ?>
									<option value="<?php echo esc_attr( $ghl_key ); ?>" <?php selected( $saved_ghl_field, $ghl_key ); ?>>
										<?php echo esc_html( $ghl_label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<?php if ( $is_email_field ) : ?>
								<!-- Hidden input to ensure email mapping is submitted even though select is disabled -->
								<input type="hidden" name="ghl_field_<?php echo esc_attr( $key ); ?>" value="email">
							<?php endif; ?>
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
								<select name="ghl_field_<?php echo esc_attr( $key ); ?>" class="regular-text" data-saved-value="<?php echo esc_attr( $saved_ghl_field ); ?>">
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
								<select name="ghl_field_<?php echo esc_attr( $key ); ?>" class="regular-text" data-saved-value="<?php echo esc_attr( $saved_ghl_field ); ?>">
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

<style>
	/* Rotation animation for loading spinner */
	@keyframes rotation {
		from { transform: rotate(0deg); }
		to { transform: rotate(359deg); }
	}
	
	/* Style for disabled email field */
	select[name="ghl_field_user_email"]:disabled {
		background-color: #f0f0f1;
		color: #2c3338;
		cursor: not-allowed;
		opacity: 0.7;
	}
	
	/* Highlight mapped fields with light green background */
	.form-table tr.ghl-mapped-field {
		background-color: #c3e6cb !important;
		transition: background-color 0.3s ease;
	}
</style>