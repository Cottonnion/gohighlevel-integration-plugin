<?php
/**
 * Template: Field Mapping Page (Dynamic)
 *
 * @package Syncly
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Check connection status
$settings_manager = \Syncly\Core\SettingsManager::get_instance();
$settings         = $settings_manager->get_settings_array();
$oauth_handler    = new \Syncly\API\OAuth\OAuthHandler();
$oauth_status     = $oauth_handler->get_connection_status();
$is_connected     = $oauth_status['connected'];

// WordPress base user fields (stored in wp_users table)
$base_user_fields = array(
	'user_login'      => __( 'Username', 'syncly' ),
	'user_email'      => __( 'Email', 'syncly' ),
	'display_name'    => __( 'Display Name', 'syncly' ),
	'user_url'        => __( 'Website', 'syncly' ),
	'user_registered' => __( 'Registration Date', 'syncly' ),
);

// WordPress default user meta fields (standard across all WP installs)
$default_user_meta = array(
	'first_name'  => __( 'First Name', 'syncly' ),
	'last_name'   => __( 'Last Name', 'syncly' ),
	'nickname'    => __( 'Nickname', 'syncly' ),
	'description' => __( 'Biographical Info', 'syncly' ),
);

// Get contact methods (phone, address, etc. from plugins/themes).
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WordPress hook.
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
$meta_keys_cache_group = 'syncly_field_mapping';
$meta_keys_cache_key   = 'user_meta_keys_' . get_current_blog_id();

$all_meta_keys = wp_cache_get( $meta_keys_cache_key, $meta_keys_cache_group );

if ( false === $all_meta_keys ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Querying core usermeta table for distinct keys is necessary and cached immediately after.
	$all_meta_keys = $wpdb->get_col( "SELECT DISTINCT meta_key FROM {$wpdb->usermeta} ORDER BY meta_key" );
	wp_cache_set( $meta_keys_cache_key, $all_meta_keys, $meta_keys_cache_group, 5 * MINUTE_IN_SECONDS );
}

// Initialize empty arrays for custom fields (PRO features)
$custom_user_fields = array();
$woocommerce_fields = array();
$buddyboss_fields   = array();
$learndash_fields   = array();
$is_pro_active      = (bool) apply_filters( 'syncly_is_pro_active', false );

/**
 * Filter: Allow PRO plugin to add custom user meta fields
 *
 * @param array $custom_user_fields Custom user meta fields (key => label)
 * @param array $all_meta_keys All meta keys from database
 * @param array $default_user_meta Default WordPress user meta fields
 * @param array $excluded_meta_keys Excluded meta keys
 */
$custom_user_fields = apply_filters( 'syncly_field_mapping_custom_fields', $custom_user_fields, $all_meta_keys, $default_user_meta, $excluded_meta_keys );

/**
 * Filter: Allow PRO plugin to add WooCommerce fields
 *
 * @param array $woocommerce_fields WooCommerce billing/shipping fields (key => label)
 * @param array $all_meta_keys All meta keys from database
 */
$woocommerce_fields = apply_filters( 'syncly_field_mapping_woocommerce_fields', $woocommerce_fields, $all_meta_keys );

/**
 * Filter: Allow PRO plugin to add BuddyBoss/BuddyPress XProfile fields
 *
 * @param array $buddyboss_fields BuddyBoss XProfile fields (key => label)
 */
$buddyboss_fields = apply_filters( 'syncly_field_mapping_buddyboss_fields', $buddyboss_fields );

/**
 * Filter: Allow PRO plugin to add LearnDash course progress fields
 *
 * @param array $learndash_fields LearnDash progress fields (key => label)
 */
$learndash_fields = apply_filters( 'syncly_field_mapping_learndash_fields', $learndash_fields );

// Sort custom fields alphabetically
asort( $custom_user_fields );
asort( $buddyboss_fields );
asort( $woocommerce_fields );

// Handle refresh_fields query param — force-refresh the transient cache.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only cache refresh, no state change.
$refresh_fields = isset( $_GET['refresh_fields'] ) && '1' === sanitize_key( wp_unslash( $_GET['refresh_fields'] ) );

if ( $refresh_fields ) {
	$ghl_data = $settings_manager->get_syncly_fields_cached( true );
} else {
	$ghl_data = $settings_manager->get_syncly_fields_cached();
}

$ghl_fields      = $ghl_data['fields'];
$ghl_field_types = $ghl_data['fieldTypes'];

// Replace " (Custom)" labels with the field type when available.
foreach ( $ghl_fields as $ghl_key => &$ghl_label ) {
	if ( ! empty( $ghl_field_types[ $ghl_key ] ) ) {
		$ghl_label = str_replace( ' (Custom)', ' (' . $ghl_field_types[ $ghl_key ] . ')', $ghl_label );
	}
}
unset( $ghl_label );

// Calculate total fields
$total_fields = count( $default_wp_fields ) + count( $custom_user_fields ) + count( $buddyboss_fields ) + count( $learndash_fields );

// Get current field mappings
$saved_mappings = $settings['user_field_mapping'] ?? [];
?>
<div class="wrap syncly-field-mapping">
	
	<?php if ( ! $is_connected ) : ?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'Not Connected', 'syncly' ); ?></strong><br>
				<?php
				printf(
					/* translators: %s: Link to dashboard page */
					esc_html__( 'Please connect to GoHighLevel in %s first.', 'syncly' ),
					sprintf(
						'<a href="%s">%s</a>',
						esc_url( admin_url( 'admin.php?page=syncly-admin' ) ),
						esc_html__( 'Dashboard', 'syncly' )
					)
				);
				?>
			</p>
		</div>
		<?php return; ?>
	<?php endif; ?>

	<?php
	// Check scope access for Contacts and Custom Fields
	\Syncly\API\ScopeChecker::render_scope_notice( 'contacts' );
	\Syncly\API\ScopeChecker::render_scope_notice( 'custom_fields' );
	?>

	<!-- Helpful Information Notice -->
	<div class="notice notice-info" style="margin: 20px 0; padding: 15px; border-left-color: #2271b1;">
		<h3 style="margin-top: 0;">
			<span class="dashicons dashicons-info" style="color: #2271b1;"></span>
			<?php esc_html_e( 'About Field Mapping', 'syncly' ); ?>
		</h3>
		<p>
			<?php esc_html_e( 'Field mapping connects WordPress user data with GoHighLevel contact fields. When users are created, updated, or synced, data flows between the systems based on your mappings.', 'syncly' ); ?>
		</p>
		<ul style="list-style: disc; margin-left: 20px;">
			<li>
				<strong><?php esc_html_e( 'WordPress Field:', 'syncly' ); ?></strong> 
				<?php esc_html_e( 'The source field from WordPress (user profile, WooCommerce, BuddyBoss, etc.)', 'syncly' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'GoHighLevel Field:', 'syncly' ); ?></strong> 
				<?php esc_html_e( 'The destination field in your GHL contact record. Select "— Do Not Sync —" to skip syncing this field.', 'syncly' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Sync Direction:', 'syncly' ); ?></strong>
				<ul style="list-style: circle; margin-left: 20px; margin-top: 5px;">
					<li><strong>↔ Both Ways:</strong> <?php esc_html_e( 'Data syncs in both directions (WordPress ⇄ GHL)', 'syncly' ); ?></li>
					<li><strong>→ To GoHighLevel Only:</strong> <?php esc_html_e( 'Data only flows from WordPress to GHL', 'syncly' ); ?></li>
					<li><strong>← From GoHighLevel Only:</strong> <?php esc_html_e( 'Data only flows from GHL to WordPress', 'syncly' ); ?></li>
				</ul>
			</li>
		</ul>
		<p style="margin-bottom: 0;">
			<strong><?php esc_html_e( 'Tip:', 'syncly' ); ?></strong> 
			<?php esc_html_e( 'Use "— Do Not Sync —" for fields you want to keep separate between systems, or for sensitive data that shouldn\'t be shared.', 'syncly' ); ?>
		</p>
	</div>

	<div style="margin: 20px 0; display: flex; gap: 12px; align-items: center;">
		<?php
		// Build reload URL — use explicit admin page URL since this template may be
		// loaded via AJAX (where REQUEST_URI would be admin-ajax.php).
		$reload_url = add_query_arg( 'refresh_fields', '1', admin_url( 'admin.php?page=syncly-admin' ) ) . '#/field-mapping';
		?>
		<a href="<?php echo esc_url( $reload_url ); ?>" class="ghl-button ghl-button-primary" style="text-decoration: none;">
			<span class="dashicons dashicons-update" style="margin-top: 3px;"></span>
			<?php esc_html_e( 'Reload Fields from GoHighLevel', 'syncly' ); ?>
		</a>
		<?php do_action( 'syncly_render_field_mapping_actions' ); ?>
	</div>

	<?php if ( ! $is_pro_active ) : ?>
		<div class="notice notice-info" style="margin: 0 0 15px;">
			<p>
				<?php esc_html_e( 'AI-Assisted Field Suggestions are available in Syncly Pro. They analyze unmapped WordPress fields and suggest likely GoHighLevel field matches.', 'syncly' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( $refresh_fields ) : ?>
		<div class="notice notice-success is-dismissible" style="margin: 0 0 15px;">
			<p>
				<?php
				printf(
					/* translators: %d: number of custom fields loaded */
					esc_html__( 'Fields refreshed from GoHighLevel. %d custom fields loaded.', 'syncly' ),
					(int) $ghl_data['count']
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<form id="ghl-field-mapping-form" method="post" action="">
		<?php wp_nonce_field( 'syncly_field_mapping', 'syncly_mapping_nonce' ); ?>
		
		<!-- Default WordPress Fields -->
		<div class="ghl-field-section-header">
			<h3><?php esc_html_e( 'Default WordPress Fields', 'syncly' ); ?></h3>
			<p class="description">
				<?php
				printf(
					/* translators: %d: number of default fields */
					esc_html__( 'Standard WordPress user fields (%d fields).', 'syncly' ),
					count( $default_wp_fields )
				);
				?>
			</p>
		</div>
		
		<table class="ghl-table" role="presentation">
			<thead>
				<tr>
					<th style="width: 30%;"><?php esc_html_e( 'WordPress Field', 'syncly' ); ?></th>
					<th style="width: 35%;"><?php esc_html_e( 'GoHighLevel Field', 'syncly' ); ?></th>
					<th style="width: 35%;"><?php esc_html_e( 'Sync Direction', 'syncly' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $default_wp_fields as $key => $label ) :
					// Set default mapping only for required email field
					$default_mappings = [
						'user_email' => 'email',
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
							<span class="ghl-field-badge ghl-field-badge--default"><?php esc_html_e( 'Core', 'syncly' ); ?></span><br>
							<code><?php echo esc_html( $key ); ?></code>
							<?php if ( $is_email_field ) : ?>
								<br><span class="ghl-required-indicator"><?php esc_html_e( '* Required field', 'syncly' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $is_email_field ) : ?>
								<div class="ghl-lazy-select ghl-lazy-select--disabled" data-name="ghl_field_<?php echo esc_attr( $key ); ?>" data-value="email">
									<span class="ghl-lazy-select__text"><?php echo esc_html( $ghl_fields['email'] ?? 'Email' ); ?></span>
									<span class="ghl-lazy-select__arrow">&#9662;</span>
								</div>
								<input type="hidden" name="ghl_field_<?php echo esc_attr( $key ); ?>" value="email">
							<?php else : ?>
								<div class="ghl-lazy-select" data-name="ghl_field_<?php echo esc_attr( $key ); ?>" data-value="<?php echo esc_attr( $saved_ghl_field ); ?>">
									<span class="ghl-lazy-select__text"><?php echo esc_html( ! empty( $saved_ghl_field ) && isset( $ghl_fields[ $saved_ghl_field ] ) ? $ghl_fields[ $saved_ghl_field ] : '— Do Not Sync —' ); ?></span>
									<span class="ghl-lazy-select__arrow">&#9662;</span>
								</div>
								<input type="hidden" name="ghl_field_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $saved_ghl_field ); ?>">
							<?php endif; ?>
						</td>
						<td>
							<select name="sync_direction_<?php echo esc_attr( $key ); ?>" class="ghl-select" <?php echo $is_email_field ? 'disabled' : ''; ?>>
								<option value="both" <?php selected( $saved_direction, 'both' ); ?>><?php esc_html_e( '↔ Both Ways', 'syncly' ); ?></option>
								<option value="to_ghl" <?php selected( $saved_direction, 'to_ghl' ); ?>><?php esc_html_e( '→ To GoHighLevel Only', 'syncly' ); ?></option>
								<option value="from_ghl" <?php selected( $saved_direction, 'from_ghl' ); ?>><?php esc_html_e( '← From GoHighLevel Only', 'syncly' ); ?></option>
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
				<h3><?php esc_html_e( 'BuddyBoss Profile Fields', 'syncly' ); ?></h3>
				<p class="description">
					<?php
					printf(
						/* translators: %d: number of BuddyBoss fields found */
						esc_html__( 'Custom profile fields from BuddyBoss (%d fields found).', 'syncly' ),
						count( $buddyboss_fields )
					);
					?>
				</p>
			</div>
			
			<table class="ghl-table" role="presentation">
				<thead>
					<tr>
						<th style="width: 30%;"><?php esc_html_e( 'BuddyBoss Field', 'syncly' ); ?></th>
						<th style="width: 35%;"><?php esc_html_e( 'GoHighLevel Field', 'syncly' ); ?></th>
						<th style="width: 35%;"><?php esc_html_e( 'Sync Direction', 'syncly' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $buddyboss_fields as $key => $label ) :
						$is_explicitly_saved = isset( $saved_mappings[ $key ] );
						$saved_ghl_field     = isset( $saved_mappings[ $key ]['ghl_field'] ) ? $saved_mappings[ $key ]['ghl_field'] : '';
						$saved_direction     = isset( $saved_mappings[ $key ]['direction'] ) ? $saved_mappings[ $key ]['direction'] : 'both';
						?>
						<tr class="ghl-field-row" data-section="buddyboss" data-key="<?php echo esc_attr( $key ); ?>" data-label="<?php echo esc_attr( wp_strip_all_tags( $label ) ); ?>" data-search="<?php echo esc_attr( strtolower( wp_strip_all_tags( $label ) . ' ' . $key ) ); ?>" data-explicitly-saved="<?php echo $is_explicitly_saved ? '1' : '0'; ?>">
							<td>
								<strong><?php echo esc_html( $label ); ?></strong>
								<span class="ghl-field-badge ghl-field-badge--buddyboss"><?php esc_html_e( 'BuddyBoss', 'syncly' ); ?></span><br>
								<code><?php echo esc_html( $key ); ?></code>
							</td>
							<td>
								<div class="ghl-lazy-select" data-name="ghl_field_<?php echo esc_attr( $key ); ?>" data-value="<?php echo esc_attr( $saved_ghl_field ); ?>">
									<span class="ghl-lazy-select__text"><?php echo esc_html( ! empty( $saved_ghl_field ) && isset( $ghl_fields[ $saved_ghl_field ] ) ? $ghl_fields[ $saved_ghl_field ] : '— Do Not Sync —' ); ?></span>
									<span class="ghl-lazy-select__arrow">&#9662;</span>
								</div>
								<input type="hidden" name="ghl_field_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $saved_ghl_field ); ?>">
							</td>
							<td>
								<select name="sync_direction_<?php echo esc_attr( $key ); ?>" class="ghl-select">
									<option value="both" <?php selected( $saved_direction, 'both' ); ?>><?php esc_html_e( '↔ Both Ways', 'syncly' ); ?></option>
									<option value="to_ghl" <?php selected( $saved_direction, 'to_ghl' ); ?>><?php esc_html_e( '→ To GoHighLevel Only', 'syncly' ); ?></option>
									<option value="from_ghl" <?php selected( $saved_direction, 'from_ghl' ); ?>><?php esc_html_e( '← From GoHighLevel Only', 'syncly' ); ?></option>
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
				<h3><?php esc_html_e( 'WooCommerce Customer Fields', 'syncly' ); ?></h3>
				<p class="description">
					<?php
					printf(
						/* translators: %d: number of WooCommerce fields found */
						esc_html__( 'Billing and shipping fields from WooCommerce (%d fields found).', 'syncly' ),
						count( $woocommerce_fields )
					);
					?>
				</p>
			</div>
			
			<table class="ghl-table" role="presentation">
				<thead>
					<tr>
						<th style="width: 30%;"><?php esc_html_e( 'WooCommerce Field', 'syncly' ); ?></th>
						<th style="width: 35%;"><?php esc_html_e( 'GoHighLevel Field', 'syncly' ); ?></th>
						<th style="width: 35%;"><?php esc_html_e( 'Sync Direction', 'syncly' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $woocommerce_fields as $key => $label ) :
						$is_explicitly_saved = isset( $saved_mappings[ $key ] );
						$saved_ghl_field     = isset( $saved_mappings[ $key ]['ghl_field'] ) ? $saved_mappings[ $key ]['ghl_field'] : '';
						$saved_direction     = isset( $saved_mappings[ $key ]['direction'] ) ? $saved_mappings[ $key ]['direction'] : 'both';
						?>
						<tr class="ghl-field-row" data-section="woocommerce" data-key="<?php echo esc_attr( $key ); ?>" data-label="<?php echo esc_attr( wp_strip_all_tags( $label ) ); ?>" data-search="<?php echo esc_attr( strtolower( wp_strip_all_tags( $label ) . ' ' . $key ) ); ?>" data-explicitly-saved="<?php echo $is_explicitly_saved ? '1' : '0'; ?>">
							<td>
								<strong><?php echo esc_html( $label ); ?></strong>
								<span class="ghl-field-badge ghl-field-badge--woocommerce"><?php esc_html_e( 'WooCommerce', 'syncly' ); ?></span><br>
								<code><?php echo esc_html( $key ); ?></code>
							</td>
							<td>
								<div class="ghl-lazy-select" data-name="ghl_field_<?php echo esc_attr( $key ); ?>" data-value="<?php echo esc_attr( $saved_ghl_field ); ?>">
									<span class="ghl-lazy-select__text"><?php echo esc_html( ! empty( $saved_ghl_field ) && isset( $ghl_fields[ $saved_ghl_field ] ) ? $ghl_fields[ $saved_ghl_field ] : '— Do Not Sync —' ); ?></span>
									<span class="ghl-lazy-select__arrow">&#9662;</span>
								</div>
								<input type="hidden" name="ghl_field_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $saved_ghl_field ); ?>">
							</td>
							<td>
								<select name="sync_direction_<?php echo esc_attr( $key ); ?>" class="ghl-select">
									<option value="both" <?php selected( $saved_direction, 'both' ); ?>><?php esc_html_e( '↔ Both Ways', 'syncly' ); ?></option>
									<option value="to_ghl" <?php selected( $saved_direction, 'to_ghl' ); ?>><?php esc_html_e( '→ To GoHighLevel Only', 'syncly' ); ?></option>
									<option value="from_ghl" <?php selected( $saved_direction, 'from_ghl' ); ?>><?php esc_html_e( '← From GoHighLevel Only', 'syncly' ); ?></option>
								</select>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<?php if ( ! empty( $learndash_fields ) ) : ?>
			<!-- LearnDash Course Progress Fields -->
			<div class="ghl-field-section-header">
				<h3><?php esc_html_e( 'LearnDash Course Progress', 'syncly' ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'Map LearnDash course progress data (percentage, status, completed steps, total steps) to GoHighLevel custom fields. These fields sync automatically on lesson, topic, and course completion.', 'syncly' ); ?>
				</p>
			</div>

			<table class="ghl-table" role="presentation">
				<thead>
					<tr>
						<th style="width: 30%;"><?php esc_html_e( 'LearnDash Field', 'syncly' ); ?></th>
						<th style="width: 35%;"><?php esc_html_e( 'GoHighLevel Field', 'syncly' ); ?></th>
						<th style="width: 35%;"><?php esc_html_e( 'Sync Direction', 'syncly' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $learndash_fields as $key => $label ) :
						$is_explicitly_saved = isset( $saved_mappings[ $key ] );
						$saved_ghl_field     = isset( $saved_mappings[ $key ]['ghl_field'] ) ? $saved_mappings[ $key ]['ghl_field'] : '';
						$saved_direction     = isset( $saved_mappings[ $key ]['direction'] ) ? $saved_mappings[ $key ]['direction'] : 'to_ghl';
						?>
						<tr class="ghl-field-row" data-section="learndash" data-key="<?php echo esc_attr( $key ); ?>" data-label="<?php echo esc_attr( wp_strip_all_tags( $label ) ); ?>" data-search="<?php echo esc_attr( strtolower( wp_strip_all_tags( $label ) . ' ' . $key ) ); ?>" data-explicitly-saved="<?php echo $is_explicitly_saved ? '1' : '0'; ?>">
							<td>
								<strong><?php echo esc_html( $label ); ?></strong>
								<span class="ghl-field-badge ghl-field-badge--learndash"><?php esc_html_e( 'LearnDash', 'syncly' ); ?></span><br>
								<code><?php echo esc_html( $key ); ?></code>
							</td>
							<td>
								<div class="ghl-lazy-select" data-name="ghl_field_<?php echo esc_attr( $key ); ?>" data-value="<?php echo esc_attr( $saved_ghl_field ); ?>">
									<span class="ghl-lazy-select__text"><?php echo esc_html( ! empty( $saved_ghl_field ) && isset( $ghl_fields[ $saved_ghl_field ] ) ? $ghl_fields[ $saved_ghl_field ] : '— Do Not Sync —' ); ?></span>
									<span class="ghl-lazy-select__arrow">&#9662;</span>
								</div>
								<input type="hidden" name="ghl_field_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $saved_ghl_field ); ?>">
							</td>
							<td>
								<select name="sync_direction_<?php echo esc_attr( $key ); ?>" class="ghl-select">
									<option value="both" <?php selected( $saved_direction, 'both' ); ?>><?php esc_html_e( '↔ Both Ways', 'syncly' ); ?></option>
									<option value="to_ghl" <?php selected( $saved_direction, 'to_ghl' ); ?>><?php esc_html_e( '→ To GoHighLevel Only', 'syncly' ); ?></option>
									<option value="from_ghl" <?php selected( $saved_direction, 'from_ghl' ); ?>><?php esc_html_e( '← From GoHighLevel Only', 'syncly' ); ?></option>
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
				<h3><?php esc_html_e( 'Custom & Plugin Fields', 'syncly' ); ?></h3>
				<p class="description">
					<?php
					printf(
						/* translators: %d: number of custom fields found */
						esc_html__( 'Custom user meta fields and fields added by other plugins or themes (%d fields found).', 'syncly' ),
						count( $custom_user_fields )
					);
					?>
				</p>
				<button type="button" id="ghl-toggle-custom-fields" class="ghl-toggle-button" data-target="ghl-custom-fields-wrapper" data-label-show="<?php esc_attr_e( 'Show custom fields', 'syncly' ); ?>" data-label-hide="<?php esc_attr_e( 'Hide custom fields', 'syncly' ); ?>" aria-expanded="false">
					<span class="dashicons dashicons-arrow-right"></span>
					<span class="ghl-toggle-button__label"><?php esc_html_e( 'Show custom fields', 'syncly' ); ?></span>
				</button>
			</div>

			<div id="ghl-custom-fields-wrapper" class="ghl-collapsible ghl-is-collapsed" data-collapsible>
			<table class="ghl-table" role="presentation">
				<thead>
					<tr>
						<th style="width: 30%;"><?php esc_html_e( 'WordPress Field', 'syncly' ); ?></th>
						<th style="width: 35%;"><?php esc_html_e( 'GoHighLevel Field', 'syncly' ); ?></th>
						<th style="width: 35%;"><?php esc_html_e( 'Sync Direction', 'syncly' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $custom_user_fields as $key => $label ) :
						$saved_ghl_field = isset( $saved_mappings[ $key ]['ghl_field'] ) ? $saved_mappings[ $key ]['ghl_field'] : '';
						$saved_direction = isset( $saved_mappings[ $key ]['direction'] ) ? $saved_mappings[ $key ]['direction'] : 'both';
						?>
						<tr class="ghl-field-row" data-section="custom" data-key="<?php echo esc_attr( $key ); ?>" data-label="<?php echo esc_attr( wp_strip_all_tags( $label ) ); ?>" data-search="<?php echo esc_attr( strtolower( wp_strip_all_tags( $label ) . ' ' . $key ) ); ?>">
							<td>
								<strong><?php echo esc_html( $label ); ?></strong>
								<span class="ghl-field-badge ghl-field-badge--custom"><?php esc_html_e( 'Custom', 'syncly' ); ?></span><br>
								<code><?php echo esc_html( $key ); ?></code>
							</td>
							<td>
								<div class="ghl-lazy-select" data-name="ghl_field_<?php echo esc_attr( $key ); ?>" data-value="<?php echo esc_attr( $saved_ghl_field ); ?>">
									<span class="ghl-lazy-select__text"><?php echo esc_html( ! empty( $saved_ghl_field ) && isset( $ghl_fields[ $saved_ghl_field ] ) ? $ghl_fields[ $saved_ghl_field ] : '— Do Not Sync —' ); ?></span>
									<span class="ghl-lazy-select__arrow">&#9662;</span>
								</div>
								<input type="hidden" name="ghl_field_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $saved_ghl_field ); ?>">
							</td>
							<td>
								<select name="sync_direction_<?php echo esc_attr( $key ); ?>" class="ghl-select">
									<option value="both" <?php selected( $saved_direction, 'both' ); ?>><?php esc_html_e( '↔ Both Ways', 'syncly' ); ?></option>
									<option value="to_ghl" <?php selected( $saved_direction, 'to_ghl' ); ?>><?php esc_html_e( '→ To GoHighLevel Only', 'syncly' ); ?></option>
									<option value="from_ghl" <?php selected( $saved_direction, 'from_ghl' ); ?>><?php esc_html_e( '← From GoHighLevel Only', 'syncly' ); ?></option>
								</select>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			</div>
		<?php endif; ?>

		<?php if ( ! $is_pro_active && empty( $buddyboss_fields ) && empty( $custom_user_fields ) && empty( $woocommerce_fields ) && empty( $learndash_fields ) ) : ?>
			<?php
			$notice_title = __( 'Extended Field Mapping', 'syncly' );
			$description  = __( 'Map WooCommerce, BuddyBoss, LearnDash, and custom user fields with Syncly Pro.', 'syncly' );
			$features      = [
				__( 'WooCommerce order and billing fields', 'syncly' ),
				__( 'BuddyBoss profile fields', 'syncly' ),
				__( 'LearnDash course and progress fields', 'syncly' ),
				__( 'Custom user meta fields', 'syncly' ),
			];
			$cta_text = __( 'Learn More', 'syncly' );
			$cta_url  = apply_filters( 'syncly_upgrade_url', 'https://highlevelsync.com/' );
			$style    = 'banner';
			include SYNCLY_PATH . 'templates/admin/partials/pro-upgrade-notice.php';
			?>
		<?php endif; ?>

			<p class="submit">
				<button type="submit" name="submit" id="submit" class="ghl-button ghl-button-primary">
					<?php esc_html_e( 'Save Field Mapping', 'syncly' ); ?>
				</button>
			</p>
	</form>
	<script type="application/json" id="ghl-field-mapping-data">
		<?php echo wp_json_encode( [ 'fields' => $ghl_fields, 'savedMappings' => $saved_mappings ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded for application/json script. ?>
	</script>
</div>

<?php
wp_add_inline_script(
	'syncly-field-mapping-js',
	'window.Syncly_FIELDS = ' . wp_json_encode( $ghl_fields ) . '; window.Syncly_SAVED_MAPPINGS = ' . wp_json_encode( $saved_mappings ) . ';',
	'before'
);
wp_add_inline_style(
	'syncly-field-mapping-css',
	'@keyframes rotation { from { transform: rotate(0deg); } to { transform: rotate(359deg); } }'
);
?>