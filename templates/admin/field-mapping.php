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

// Get all unique meta keys from all users (cached to avoid repeated uncached direct queries)
global $wpdb;
$meta_keys_cache_group = 'ghl_crm_field_mapping';
$meta_keys_cache_key   = 'user_meta_keys_' . get_current_blog_id();

$all_meta_keys = wp_cache_get( $meta_keys_cache_key, $meta_keys_cache_group );

if ( false === $all_meta_keys ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Querying core usermeta table for distinct keys is necessary and cached immediately after.
	$all_meta_keys = $wpdb->get_col( "SELECT DISTINCT meta_key FROM {$wpdb->usermeta} ORDER BY meta_key" );
	wp_cache_set( $meta_keys_cache_key, $all_meta_keys, $meta_keys_cache_group, 5 * MINUTE_IN_SECONDS );
}

$custom_user_fields = array();
$woocommerce_fields = array();

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

	// Skip dynamic BuddyBoss profile slug hashes that flood the selector.
	if ( strpos( $meta_key, 'bb_profile_slug_' ) === 0 ) {
		continue;
	}
	
	// Skip our own plugin fields (starting with ghl_ or _ghl_)
	if ( strpos( $meta_key, 'ghl_' ) === 0 || strpos( $meta_key, '_ghl_' ) === 0 ) {
		continue;
	}
	
	// Categorize WooCommerce fields
	if ( class_exists( 'WooCommerce' ) && ( 
		strpos( $meta_key, 'billing_' ) === 0 || 
		strpos( $meta_key, 'shipping_' ) === 0 ||
		strpos( $meta_key, 'wc_' ) === 0
	) ) {
		$woocommerce_fields[ $meta_key ] = ucwords( str_replace( array( '_', '-' ), ' ', $meta_key ) );
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
					
					// Add field with group context and field type
					$field_type_label = '';
					if ( ! empty( $field->type ) ) {
						$type_labels = array(
							'textbox'        => 'Text',
							'textarea'       => 'Textarea',
							'number'         => 'Number',
							'datebox'        => 'Date',
							'selectbox'      => 'Dropdown',
							'multiselectbox' => 'Multi-Select',
							'radio'          => 'Radio',
							'checkbox'       => 'Checkbox',
							'url'            => 'URL',
							'telephone'      => 'Phone',
						);
						$field_type_label = isset( $type_labels[ $field->type ] ) ? ' [' . $type_labels[ $field->type ] . ']' : '';
					}
					
					$buddyboss_fields[ $field_key ] = sprintf(
						'%s%s (%s)',
						$field->name,
						$field_type_label,
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
asort( $woocommerce_fields );

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

	<!-- Helpful Information Notice -->
	<div class="notice notice-info" style="margin: 20px 0; padding: 15px; border-left-color: #2271b1;">
		<h3 style="margin-top: 0;">
			<span class="dashicons dashicons-info" style="color: #2271b1;"></span>
			<?php esc_html_e( 'About Field Mapping', 'ghl-crm-integration' ); ?>
		</h3>
		<p>
			<?php esc_html_e( 'Field mapping connects WordPress user data with GoHighLevel contact fields. When users are created, updated, or synced, data flows between the systems based on your mappings.', 'ghl-crm-integration' ); ?>
		</p>
		<ul style="list-style: disc; margin-left: 20px;">
			<li>
				<strong><?php esc_html_e( 'WordPress Field:', 'ghl-crm-integration' ); ?></strong> 
				<?php esc_html_e( 'The source field from WordPress (user profile, WooCommerce, BuddyBoss, etc.)', 'ghl-crm-integration' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'GoHighLevel Field:', 'ghl-crm-integration' ); ?></strong> 
				<?php esc_html_e( 'The destination field in your GHL contact record. Select "— Do Not Sync —" to skip syncing this field.', 'ghl-crm-integration' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Sync Direction:', 'ghl-crm-integration' ); ?></strong>
				<ul style="list-style: circle; margin-left: 20px; margin-top: 5px;">
					<li><strong>↔ Both Ways:</strong> <?php esc_html_e( 'Data syncs in both directions (WordPress ⇄ GHL)', 'ghl-crm-integration' ); ?></li>
					<li><strong>→ To GoHighLevel Only:</strong> <?php esc_html_e( 'Data only flows from WordPress to GHL', 'ghl-crm-integration' ); ?></li>
					<li><strong>← From GoHighLevel Only:</strong> <?php esc_html_e( 'Data only flows from GHL to WordPress', 'ghl-crm-integration' ); ?></li>
				</ul>
			</li>
		</ul>
		<p style="margin-bottom: 0;">
			<strong><?php esc_html_e( 'Tip:', 'ghl-crm-integration' ); ?></strong> 
			<?php esc_html_e( 'Use "— Do Not Sync —" for fields you want to keep separate between systems, or for sensitive data that shouldn\'t be shared.', 'ghl-crm-integration' ); ?>
		</p>
	</div>

	<div style="margin: 20px 0; display: flex; gap: 12px; align-items: center;">
		<button type="button" id="ghl-load-custom-fields" class="ghl-button ghl-button-primary" data-nonce="<?php echo esc_attr( wp_create_nonce( 'ghl_crm_field_mapping_nonce' ) ); ?>">
			<span class="dashicons dashicons-update" style="margin-top: 3px;"></span>
			<?php esc_html_e( 'Reload Fields from GoHighLevel', 'ghl-crm-integration' ); ?>
		</button>
		<button type="button" id="ghl-auto-suggest-mappings" class="ghl-button ghl-button-secondary">
			<span class="dashicons dashicons-lightbulb" style="margin-top: 3px;"></span>
			<?php esc_html_e( 'Auto-Suggest Mappings', 'ghl-crm-integration' ); ?>
		</button>
	</div>

	<form id="ghl-field-mapping-form" method="post" action="">
		<?php wp_nonce_field( 'ghl_crm_field_mapping', 'ghl_crm_mapping_nonce' ); ?>
		
		<!-- Default WordPress Fields -->
		<div class="ghl-field-section-header">
			<h3><?php esc_html_e( 'Default WordPress Fields', 'ghl-crm-integration' ); ?></h3>
			<p class="description">
				<?php 
				printf(
					/* translators: %d: number of default fields */
					esc_html__( 'Standard WordPress user fields (%d fields).', 'ghl-crm-integration' ),
					count( $default_wp_fields )
				);
				?>
			</p>
		</div>
		
		<table class="ghl-table" role="presentation">
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
					// Set default mapping only for required email field
					$default_mappings = [
						'user_email'  => 'email',
					];
					
					// Check if this field has been explicitly saved by user
					$is_explicitly_saved = isset( $saved_mappings[ $key ] );
					
					// Use saved value if exists, otherwise use default mapping if available
					if ( isset( $saved_mappings[ $key ]['ghl_field'] ) ) {
						$saved_ghl_field = $saved_mappings[ $key ]['ghl_field'];
					} elseif ( isset( $default_mappings[ $key ] ) ) {
						$saved_ghl_field = $default_mappings[ $key ];
					} else {
						$saved_ghl_field = '';
					}
					
					// Email field direction should always be 'both' (locked)
					if ( $key === 'user_email' ) {
						$saved_direction = 'both';
					} else {
						$saved_direction = isset( $saved_mappings[ $key ]['direction'] ) ? $saved_mappings[ $key ]['direction'] : 'both';
					}
					
					// Email field should be disabled (required by GHL)
					$is_email_field = ( $key === 'user_email' );
				?>
					<tr class="ghl-field-row<?php echo $is_email_field ? ' ghl-required-field' : ''; ?>" data-section="default" data-key="<?php echo esc_attr( $key ); ?>" data-label="<?php echo esc_attr( wp_strip_all_tags( $label ) ); ?>" data-search="<?php echo esc_attr( strtolower( wp_strip_all_tags( $label ) . ' ' . $key ) ); ?>" data-explicitly-saved="<?php echo $is_explicitly_saved ? '1' : '0'; ?>">
						<td>
							<strong><?php echo esc_html( $label ); ?></strong>
							<span class="ghl-field-badge ghl-field-badge--default"><?php esc_html_e( 'Core', 'ghl-crm-integration' ); ?></span><br>
							<code><?php echo esc_html( $key ); ?></code>
							<?php if ( $is_email_field ) : ?>
								<br><span class="ghl-required-indicator"><?php esc_html_e( '* Required field', 'ghl-crm-integration' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<select name="ghl_field_<?php echo esc_attr( $key ); ?>" class="ghl-select" data-saved-value="<?php echo esc_attr( $saved_ghl_field ); ?>" <?php echo $is_email_field ? 'disabled' : ''; ?>>
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
							<select name="sync_direction_<?php echo esc_attr( $key ); ?>" class="ghl-select" <?php echo $is_email_field ? 'disabled' : ''; ?>>
								<option value="both" <?php selected( $saved_direction, 'both' ); ?>><?php esc_html_e( '↔ Both Ways', 'ghl-crm-integration' ); ?></option>
								<option value="to_ghl" <?php selected( $saved_direction, 'to_ghl' ); ?>><?php esc_html_e( '→ To GoHighLevel Only', 'ghl-crm-integration' ); ?></option>
								<option value="from_ghl" <?php selected( $saved_direction, 'from_ghl' ); ?>><?php esc_html_e( '← From GoHighLevel Only', 'ghl-crm-integration' ); ?></option>
							</select>
							<?php if ( $is_email_field ) : ?>
								<!-- Hidden input to ensure email sync direction is submitted as 'both' -->
								<input type="hidden" name="sync_direction_<?php echo esc_attr( $key ); ?>" value="both">
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( ! empty( $buddyboss_fields ) ) : ?>
			<!-- BuddyBoss Profile Fields -->
			<div class="ghl-field-section-header">
				<h3><?php esc_html_e( 'BuddyBoss Profile Fields', 'ghl-crm-integration' ); ?></h3>
				<p class="description">
					<?php 
					printf(
						/* translators: %d: number of BuddyBoss fields found */
						esc_html__( 'Custom profile fields from BuddyBoss (%d fields found).', 'ghl-crm-integration' ),
						count( $buddyboss_fields )
					);
					?>
				</p>
			</div>
			
			<table class="ghl-table" role="presentation">
				<thead>
					<tr>
						<th style="width: 30%;"><?php esc_html_e( 'BuddyBoss Field', 'ghl-crm-integration' ); ?></th>
						<th style="width: 35%;"><?php esc_html_e( 'GoHighLevel Field', 'ghl-crm-integration' ); ?></th>
						<th style="width: 35%;"><?php esc_html_e( 'Sync Direction', 'ghl-crm-integration' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $buddyboss_fields as $key => $label ) : 
						$is_explicitly_saved = isset( $saved_mappings[ $key ] );
						$saved_ghl_field = isset( $saved_mappings[ $key ]['ghl_field'] ) ? $saved_mappings[ $key ]['ghl_field'] : '';
						$saved_direction = isset( $saved_mappings[ $key ]['direction'] ) ? $saved_mappings[ $key ]['direction'] : 'both';
					?>
						<tr class="ghl-field-row" data-section="buddyboss" data-key="<?php echo esc_attr( $key ); ?>" data-label="<?php echo esc_attr( wp_strip_all_tags( $label ) ); ?>" data-search="<?php echo esc_attr( strtolower( wp_strip_all_tags( $label ) . ' ' . $key ) ); ?>" data-explicitly-saved="<?php echo $is_explicitly_saved ? '1' : '0'; ?>">
							<td>
								<strong><?php echo esc_html( $label ); ?></strong>
								<span class="ghl-field-badge ghl-field-badge--buddyboss"><?php esc_html_e( 'BuddyBoss', 'ghl-crm-integration' ); ?></span><br>
								<code><?php echo esc_html( $key ); ?></code>
							</td>
							<td>
								<select name="ghl_field_<?php echo esc_attr( $key ); ?>" class="ghl-select" data-saved-value="<?php echo esc_attr( $saved_ghl_field ); ?>">
									<?php foreach ( $ghl_fields as $ghl_key => $ghl_label ) : ?>
										<option value="<?php echo esc_attr( $ghl_key ); ?>" <?php selected( $saved_ghl_field, $ghl_key ); ?>>
											<?php echo esc_html( $ghl_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
							<td>
								<select name="sync_direction_<?php echo esc_attr( $key ); ?>" class="ghl-select">
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

		<?php if ( ! empty( $woocommerce_fields ) ) : ?>
			<!-- WooCommerce Fields -->
			<div class="ghl-field-section-header">
				<h3><?php esc_html_e( 'WooCommerce Customer Fields', 'ghl-crm-integration' ); ?></h3>
				<p class="description">
					<?php 
					printf(
						/* translators: %d: number of WooCommerce fields found */
						esc_html__( 'Billing and shipping fields from WooCommerce (%d fields found).', 'ghl-crm-integration' ),
						count( $woocommerce_fields )
					);
					?>
				</p>
			</div>
			
			<table class="ghl-table" role="presentation">
				<thead>
					<tr>
						<th style="width: 30%;"><?php esc_html_e( 'WooCommerce Field', 'ghl-crm-integration' ); ?></th>
						<th style="width: 35%;"><?php esc_html_e( 'GoHighLevel Field', 'ghl-crm-integration' ); ?></th>
						<th style="width: 35%;"><?php esc_html_e( 'Sync Direction', 'ghl-crm-integration' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $woocommerce_fields as $key => $label ) : 
						$is_explicitly_saved = isset( $saved_mappings[ $key ] );
						$saved_ghl_field = isset( $saved_mappings[ $key ]['ghl_field'] ) ? $saved_mappings[ $key ]['ghl_field'] : '';
						$saved_direction = isset( $saved_mappings[ $key ]['direction'] ) ? $saved_mappings[ $key ]['direction'] : 'both';
					?>
						<tr class="ghl-field-row" data-section="woocommerce" data-key="<?php echo esc_attr( $key ); ?>" data-label="<?php echo esc_attr( wp_strip_all_tags( $label ) ); ?>" data-search="<?php echo esc_attr( strtolower( wp_strip_all_tags( $label ) . ' ' . $key ) ); ?>" data-explicitly-saved="<?php echo $is_explicitly_saved ? '1' : '0'; ?>">
							<td>
								<strong><?php echo esc_html( $label ); ?></strong>
								<span class="ghl-field-badge ghl-field-badge--woocommerce"><?php esc_html_e( 'WooCommerce', 'ghl-crm-integration' ); ?></span><br>
								<code><?php echo esc_html( $key ); ?></code>
							</td>
							<td>
								<select name="ghl_field_<?php echo esc_attr( $key ); ?>" class="ghl-select" data-saved-value="<?php echo esc_attr( $saved_ghl_field ); ?>">
									<?php foreach ( $ghl_fields as $ghl_key => $ghl_label ) : ?>
										<option value="<?php echo esc_attr( $ghl_key ); ?>" <?php selected( $saved_ghl_field, $ghl_key ); ?>>
											<?php echo esc_html( $ghl_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
							<td>
								<select name="sync_direction_<?php echo esc_attr( $key ); ?>" class="ghl-select">
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
			<div class="ghl-field-section-header">
				<h3><?php esc_html_e( 'Custom & Plugin Fields', 'ghl-crm-integration' ); ?></h3>
				<p class="description">
					<?php 
					printf(
						/* translators: %d: number of custom fields found */
						esc_html__( 'Custom user meta fields and fields added by other plugins or themes (%d fields found).', 'ghl-crm-integration' ),
						count( $custom_user_fields )
					);
					?>
				</p>
				<button type="button" id="ghl-toggle-custom-fields" class="ghl-toggle-button" data-target="ghl-custom-fields-wrapper" data-label-show="<?php esc_attr_e( 'Show custom fields', 'ghl-crm-integration' ); ?>" data-label-hide="<?php esc_attr_e( 'Hide custom fields', 'ghl-crm-integration' ); ?>" aria-expanded="false">
					<span class="dashicons dashicons-arrow-right"></span>
					<span class="ghl-toggle-button__label"><?php esc_html_e( 'Show custom fields', 'ghl-crm-integration' ); ?></span>
				</button>
			</div>

			<div id="ghl-custom-fields-wrapper" class="ghl-collapsible ghl-is-collapsed" data-collapsible>
			<table class="ghl-table" role="presentation">
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
						<tr class="ghl-field-row" data-section="custom" data-key="<?php echo esc_attr( $key ); ?>" data-label="<?php echo esc_attr( wp_strip_all_tags( $label ) ); ?>" data-search="<?php echo esc_attr( strtolower( wp_strip_all_tags( $label ) . ' ' . $key ) ); ?>">
							<td>
								<strong><?php echo esc_html( $label ); ?></strong>
								<span class="ghl-field-badge ghl-field-badge--custom"><?php esc_html_e( 'Custom', 'ghl-crm-integration' ); ?></span><br>
								<code><?php echo esc_html( $key ); ?></code>
							</td>
							<td>
								<select name="ghl_field_<?php echo esc_attr( $key ); ?>" class="ghl-select" data-saved-value="<?php echo esc_attr( $saved_ghl_field ); ?>">
									<?php foreach ( $ghl_fields as $ghl_key => $ghl_label ) : ?>
										<option value="<?php echo esc_attr( $ghl_key ); ?>" <?php selected( $saved_ghl_field, $ghl_key ); ?>>
											<?php echo esc_html( $ghl_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
							<td>
								<select name="sync_direction_<?php echo esc_attr( $key ); ?>" class="ghl-select">
									<option value="both" <?php selected( $saved_direction, 'both' ); ?>><?php esc_html_e( '↔ Both Ways', 'ghl-crm-integration' ); ?></option>
									<option value="to_ghl" <?php selected( $saved_direction, 'to_ghl' ); ?>><?php esc_html_e( '→ To GoHighLevel Only', 'ghl-crm-integration' ); ?></option>
									<option value="from_ghl" <?php selected( $saved_direction, 'from_ghl' ); ?>><?php esc_html_e( '← From GoHighLevel Only', 'ghl-crm-integration' ); ?></option>
								</select>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			</div>
		<?php endif; ?>

		<?php if ( empty( $buddyboss_fields ) && empty( $custom_user_fields ) && empty( $woocommerce_fields ) ) : ?>
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
</style>
