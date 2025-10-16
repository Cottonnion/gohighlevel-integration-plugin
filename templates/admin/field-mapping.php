<?php
/**
 * Template: Field Mapping Page (Dynamic)
 *
 * @package GHL_CRM_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get all registered contact fields (first_name, last_name, etc.)
$default_user_fields = array(
	'user_login'   => __( 'Username', 'ghl-crm-integration' ),
	'user_email'   => __( 'Email', 'ghl-crm-integration' ),
	'first_name'   => __( 'First Name', 'ghl-crm-integration' ),
	'last_name'    => __( 'Last Name', 'ghl-crm-integration' ),
	'display_name' => __( 'Display Name', 'ghl-crm-integration' ),
	'nickname'     => __( 'Nickname', 'ghl-crm-integration' ),
	'description'  => __( 'Description', 'ghl-crm-integration' ),
	'user_url'     => __( 'Website', 'ghl-crm-integration' ),
);

// Merge in contact methods (from plugins/themes)
$contact_methods = apply_filters( 'user_contactmethods', array() );

// Define fields that are irrelevant for contact sync (internal WordPress fields)
$excluded_fields = array(
	'rich_editing',
	'syntax_highlighting',
	'comment_shortcuts',
	'admin_color',
	'use_ssl',
	'show_admin_bar_front',
	'locale',
	'wp_capabilities',
	'wp_user_level',
	'dismissed_wp_pointers',
	'show_welcome_panel',
	'session_tokens',
	'wp_dashboard_quick_press_last_post_id',
	'wp_metaboxhidden_nav-menus',
	'closedpostboxes_dashboard',
	'metaboxhidden_dashboard',
	'bp_xprofile_visibility_levels',
	'wp_persisted_preferences',
	'community-invites-sent',
	'wp_user-settings',
	'wp_user-settings-time',
);

// Grab one sample user's meta keys to expose custom fields
$sample_user = get_users( array( 'number' => 1 ) );
$user_meta_fields = array();
if ( ! empty( $sample_user ) ) {
	$all_meta_keys = array_keys( get_user_meta( $sample_user[0]->ID ) );
	
	// Filter out excluded fields and internal WordPress fields
	foreach ( $all_meta_keys as $meta_key ) {
		// Skip if in excluded list
		if ( in_array( $meta_key, $excluded_fields, true ) ) {
			continue;
		}
		
		// Skip WordPress internal fields (starting with wp_ or _)
		if ( strpos( $meta_key, 'wp_' ) === 0 || strpos( $meta_key, '_' ) === 0 ) {
			continue;
		}
		
		// Add to our list with a nice label
		$user_meta_fields[ $meta_key ] = ucwords( str_replace( array( '_', '-' ), ' ', $meta_key ) );
	}
}

// Combine default fields with contact methods
$default_wp_fields = array_merge( $default_user_fields, $contact_methods );

// Keep custom fields separate for display
$custom_wp_fields = $user_meta_fields;

// Combine everything for count
$wp_fields = array_merge( $default_wp_fields, $custom_wp_fields );

// Debug output
print_r( $wp_fields );

// Grab one sample user’s meta keys to expose custom fields
$sample_user = get_users( array( 'number' => 1 ) );
$user_meta_fields = array();
if ( ! empty( $sample_user ) ) {
	$user_meta_fields = array_keys( get_user_meta( $sample_user[0]->ID ) );
}

// Combine everything
$wp_fields = array_merge( $default_user_fields, $contact_methods );
foreach ( $user_meta_fields as $meta_key ) {
	if ( ! isset( $wp_fields[ $meta_key ] ) ) {
		$wp_fields[ $meta_key ] = ucfirst( str_replace( '_', ' ', $meta_key ) );
	}
}

// GoHighLevel contact field list
$ghl_fields = array(
	''            => __( '— Do Not Sync —', 'ghl-crm-integration' ),
	'firstName'   => __( 'First Name', 'ghl-crm-integration' ),
	'lastName'    => __( 'Last Name', 'ghl-crm-integration' ),
	'name'        => __( 'Full Name', 'ghl-crm-integration' ),
	'email'       => __( 'Email', 'ghl-crm-integration' ),
	'phone'       => __( 'Phone', 'ghl-crm-integration' ),
	'address1'    => __( 'Address Line 1', 'ghl-crm-integration' ),
	'city'        => __( 'City', 'ghl-crm-integration' ),
	'state'       => __( 'State', 'ghl-crm-integration' ),
	'country'     => __( 'Country', 'ghl-crm-integration' ),
	'postalCode'  => __( 'Postal Code', 'ghl-crm-integration' ),
	'website'     => __( 'Website', 'ghl-crm-integration' ),
	'timezone'    => __( 'Timezone', 'ghl-crm-integration' ),
	'companyName' => __( 'Company Name', 'ghl-crm-integration' ),
	'source'      => __( 'Source', 'ghl-crm-integration' ),
	'dateOfBirth' => __( 'Date of Birth', 'ghl-crm-integration' ),
);

?>

<div class="wrap ghl-crm-field-mapping">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div class="notice notice-info">
		<p>
			<?php esc_html_e( 'Map WordPress user fields to GoHighLevel contact fields. Only mapped fields will be synchronized. Select "Do Not Sync" to exclude a field from synchronization.', 'ghl-crm-integration' ); ?>
		</p>
	</div>

	<form method="post" action="">
		<?php wp_nonce_field( 'ghl_crm_save_field_mapping', 'ghl_crm_mapping_nonce' ); ?>

		<h2><?php esc_html_e( 'User Contact Fields', 'ghl-crm-integration' ); ?></h2>
		<p class="description">
			<?php 
			printf(
				/* translators: %d: number of WordPress fields found */
				esc_html__( 'Found %d WordPress user fields available for mapping.', 'ghl-crm-integration' ),
				count( $wp_fields )
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
					<th><?php esc_html_e( 'WordPress Field', 'ghl-crm-integration' ); ?></th>
					<th><?php esc_html_e( 'GoHighLevel Field', 'ghl-crm-integration' ); ?></th>
					<th><?php esc_html_e( 'Sync Direction', 'ghl-crm-integration' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $default_wp_fields as $key => $label ) : ?>
					<tr>
						<td>
							<strong><?php echo esc_html( $label ); ?></strong><br>
							<code><?php echo esc_html( $key ); ?></code>
						</td>
						<td>
							<select name="ghl_field_<?php echo esc_attr( $key ); ?>" class="regular-text">
								<?php foreach ( $ghl_fields as $ghl_key => $ghl_label ) : ?>
									<option value="<?php echo esc_attr( $ghl_key ); ?>"><?php echo esc_html( $ghl_label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
						<td>
							<select name="sync_direction_<?php echo esc_attr( $key ); ?>">
								<option value="both"><?php esc_html_e( '↔ Both Ways', 'ghl-crm-integration' ); ?></option>
								<option value="to_ghl"><?php esc_html_e( '→ To GoHighLevel Only', 'ghl-crm-integration' ); ?></option>
								<option value="from_ghl"><?php esc_html_e( '← From GoHighLevel Only', 'ghl-crm-integration' ); ?></option>
							</select>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( ! empty( $custom_wp_fields ) ) : ?>
			<!-- Custom Fields -->
			<h3 style="margin-top: 30px;"><?php esc_html_e( 'Custom & Plugin Fields', 'ghl-crm-integration' ); ?></h3>
			<p class="description" style="margin-bottom: 15px;">
				<?php 
				printf(
					/* translators: %d: number of custom fields found */
					esc_html__( 'Custom user meta fields and fields added by plugins (%d fields found).', 'ghl-crm-integration' ),
					count( $custom_wp_fields )
				);
				?>
			</p>
			<table class="form-table widefat striped" role="presentation">
				<thead>
					<tr>
						<th><?php esc_html_e( 'WordPress Field', 'ghl-crm-integration' ); ?></th>
						<th><?php esc_html_e( 'GoHighLevel Field', 'ghl-crm-integration' ); ?></th>
						<th><?php esc_html_e( 'Sync Direction', 'ghl-crm-integration' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $custom_wp_fields as $key => $label ) : ?>
						<tr>
							<td>
								<strong><?php echo esc_html( $label ); ?></strong><br>
								<code><?php echo esc_html( $key ); ?></code>
							</td>
							<td>
								<select name="ghl_field_<?php echo esc_attr( $key ); ?>" class="regular-text">
									<?php foreach ( $ghl_fields as $ghl_key => $ghl_label ) : ?>
										<option value="<?php echo esc_attr( $ghl_key ); ?>"><?php echo esc_html( $ghl_label ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
							<td>
								<select name="sync_direction_<?php echo esc_attr( $key ); ?>">
									<option value="both"><?php esc_html_e( '↔ Both Ways', 'ghl-crm-integration' ); ?></option>
									<option value="to_ghl"><?php esc_html_e( '→ To GoHighLevel Only', 'ghl-crm-integration' ); ?></option>
									<option value="from_ghl"><?php esc_html_e( '← From GoHighLevel Only', 'ghl-crm-integration' ); ?></option>
								</select>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<div class="notice notice-warning inline" style="margin-top: 30px;">
				<p><?php esc_html_e( 'No custom fields found. Custom fields will appear here once they are added to user profiles.', 'ghl-crm-integration' ); ?></p>
			</div>
		<?php endif; ?>

		<?php submit_button( __( 'Save Field Mapping', 'ghl-crm-integration' ) ); ?>
	</form>
</div>
