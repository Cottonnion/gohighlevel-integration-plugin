<?php
/**
 * BuddyBoss Groups Integration Settings
 *
 * Settings tab for configuring BuddyBoss Groups sync with GoHighLevel Custom Objects
 *
 * @package    GHL_CRM_Integration
 * @subpackage Integrations/BuddyBoss
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get current settings
$settings = $settings ?? \GHL_CRM\Core\SettingsManager::get_instance()->get_settings_array();
$is_buddyboss_active = function_exists( 'bp_is_active' ) && bp_is_active( 'groups' );
?>

<?php if ( ! $is_buddyboss_active ) : ?>
	<!-- BuddyBoss Not Active -->
	<div class="ghl-empty-state-card">
		<div class="ghl-empty-state">
			<div class="ghl-empty-state-icon">
				<span class="dashicons dashicons-groups" style="color: #9ca3af;"></span>
			</div>
			<h3><?php esc_html_e( 'BuddyBoss Not Detected', 'ghl-crm-integration' ); ?></h3>
			<p><?php esc_html_e( 'BuddyBoss Platform must be installed and activated with Groups component enabled to use this integration.', 'ghl-crm-integration' ); ?></p>
			
			<div class="ghl-empty-state-actions">
				<?php if ( current_user_can( 'install_plugins' ) ) : ?>
					<button type="button" onclick="window.open('https://www.buddyboss.com/pricing', '_blank')" class="ghl-button ghl-button-primary">
						<?php esc_html_e( 'Install BuddyBoss', 'ghl-crm-integration' ); ?>
					</button>
				<?php endif; ?>
				<button type="button" onclick="window.open('https://www.buddyboss.com/', '_blank')" class="ghl-button ghl-button-secondary">
					<?php esc_html_e( 'Learn More', 'ghl-crm-integration' ); ?>
				</button>
			</div>
		</div>
	</div>
<?php else : ?>
	<!-- BuddyBoss Active -->
	<div class="ghl-settings-section ghl-settings-card">
		<?php
		$missing_contact_strategy = $settings['buddyboss_missing_contact_strategy'] ?? 'skip';
		$default_group_type       = $settings['buddyboss_default_group_type'] ?? '';
		$group_type_suggestions   = function_exists( 'bp_groups_get_group_types' ) ? bp_groups_get_group_types( [], 'objects' ) : [];

		if ( ! is_array( $group_type_suggestions ) ) {
			$group_type_suggestions = [];
		}
		?>
		<div class="ghl-settings-header" style="display: flex; align-items: center; justify-content: space-between; padding: 24px; gap: 20px;">
			<div style="display: flex; align-items: center; gap: 16px; flex: 1;">
				<div class="ghl-integration-icon" style="
					background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
					width: 56px;
					height: 56px;
					border-radius: 12px;
					display: flex;
					align-items: center;
					justify-content: center;
					box-shadow: 0 4px 12px rgba(249, 115, 22, 0.2);
					flex-shrink: 0;
				">
					<span class="dashicons dashicons-groups" style="font-size: 28px; color: #fff;"></span>
				</div>
				<div style="flex: 1;">
					<h2 style="margin: 0 0 6px 0; font-size: 20px; font-weight: 600; color: #1e293b; line-height: 1.2;">
						<?php esc_html_e( 'BuddyBoss Groups Integration', 'ghl-crm-integration' ); ?>
					</h2>
					<p class="description" style="margin: 0; font-size: 14px; color: #64748b; line-height: 1.5;">
						<?php esc_html_e( 'Sync BuddyBoss Groups to GoHighLevel Custom Objects and link members automatically', 'ghl-crm-integration' ); ?>
					</p>
				</div>
			</div>
			<div style="display: flex; align-items: center; gap: 12px; flex-shrink: 0;">
				<?php $buddyboss_enabled = ! empty( $settings['buddyboss_groups_enabled'] ); ?>
				<label class="ghl-checkbox <?php echo $buddyboss_enabled ? 'is-checked' : ''; ?>" style="margin: 0; display: flex; align-items: center; gap: 8px;">
					<input 
						type="checkbox" 
						class="ghl-checkbox-original"
						id="buddyboss_groups_enabled" 
						name="buddyboss_groups_enabled" 
						value="1"
						<?php checked( $buddyboss_enabled, true ); ?>
					>
					<span class="ghl-checkbox-input <?php echo $buddyboss_enabled ? 'is-checked' : ''; ?>">
						<span class="ghl-checkbox-inner"></span>
					</span>
					<span class="ghl-checkbox-label" style="font-weight: 500; color: #475569;">
						<?php echo $buddyboss_enabled ? esc_html__( 'Enabled', 'ghl-crm-integration' ) : esc_html__( 'Disabled', 'ghl-crm-integration' ); ?>
					</span>
				</label>
			</div>
		</div>

		<div class="ghl-settings-body" id="buddyboss-settings-body" style="<?php echo ! $buddyboss_enabled ? 'display: none;' : ''; ?>">
			<p>
				<?php
				if ( defined( 'BP_PLATFORM_VERSION' ) ) {
					printf(
						/* translators: %s: BuddyBoss version */
						esc_html__( 'BuddyBoss Platform %s detected. Configure your integration settings below.', 'ghl-crm-integration' ),
						BP_PLATFORM_VERSION
					);
				} else {
					esc_html_e( 'BuddyBoss Groups detected. Configure your integration settings below.', 'ghl-crm-integration' );
				}
				?>
			</p>
			<hr>

		<!-- Auto-Creation Settings -->
		<div class="ghl-form-section" style="margin-bottom: 24px;">
			<h3 style="margin: 0 0 16px; font-size: 18px; font-weight: 600; color: #1e293b;">
				<?php esc_html_e( 'Custom Object Management', 'ghl-crm-integration' ); ?>
			</h3>
			
			<div class="ghl-info-box" style="margin-bottom: 20px;">
				<span class="dashicons dashicons-info"></span>
				<div>
					<h4><?php esc_html_e( 'Automatic Custom Object Creation', 'ghl-crm-integration' ); ?></h4>
					<p><?php esc_html_e( 'Custom Objects will be automatically created in GoHighLevel when new group types are added to BuddyBoss. Each group type gets its own Custom Object (e.g., "Schools", "Communities", "Classrooms").', 'ghl-crm-integration' ); ?></p>
				</div>
			</div>

			<div class="ghl-form-row" style="margin-top: 16px;">
				<label class="ghl-toggle">
					<input type="checkbox" name="buddyboss_auto_delete_custom_objects" value="1" <?php checked( ! empty( $settings['buddyboss_auto_delete_custom_objects'] ) ); ?>>
					<span class="ghl-toggle-slider"></span>
					<span class="ghl-toggle-label">
						<?php esc_html_e( 'Auto-delete Custom Objects when group types are removed', 'ghl-crm-integration' ); ?>
					</span>
				</label>
				<p class="description" style="margin-top: 8px;">
					<?php esc_html_e( 'When disabled, Custom Objects will remain in GoHighLevel even if the corresponding group type is removed from BuddyBoss. This preserves historical data.', 'ghl-crm-integration' ); ?>
				</p>
			</div>
		</div>

		<!-- Member Association Sync -->
		<div class="ghl-form-section" style="margin-bottom: 24px;">
			<h3 style="margin: 0 0 16px; font-size: 18px; font-weight: 600; color: #1e293b;">
				<?php esc_html_e( 'Member Association Sync', 'ghl-crm-integration' ); ?>
			</h3>
		
			<div class="ghl-info-box" style="margin-bottom: 16px;">
				<span class="dashicons dashicons-admin-users"></span>
				<div>
					<h4><?php esc_html_e( 'Automatic Member Linking', 'ghl-crm-integration' ); ?></h4>
					<p><?php esc_html_e( 'Members, organizers, admins, and moderators are linked to their BuddyBoss groups using GoHighLevel custom object associations. Contact custom fields are no longer required.', 'ghl-crm-integration' ); ?></p>
				</div>
			</div>

			<p class="description" style="margin-bottom: 12px;">
				<?php esc_html_e( 'When a user joins or leaves a BuddyBoss group, the plugin queues a background job to add or remove the GoHighLevel association. Group visibility rules still apply, and contacts must already exist in GoHighLevel to be linked.', 'ghl-crm-integration' ); ?>
			</p>

			<ul style="margin: 0 0 1em 1.25em; list-style: disc; color: #475569;">
				<li><?php esc_html_e( 'Association retries are logged so you can resolve missing contacts or permissions.', 'ghl-crm-integration' ); ?></li>
				<li><?php esc_html_e( 'Custom object records include group name, description, type, and URLs for fast lookup inside GoHighLevel.', 'ghl-crm-integration' ); ?></li>
				<li><?php esc_html_e( 'Use the bulk actions below to create objects for every group type and sync existing groups on demand.', 'ghl-crm-integration' ); ?></li>
			</ul>
		</div>

		<!-- Association Behavior Controls -->
		<div class="ghl-form-section">
			<h3><?php esc_html_e( 'Association Behavior', 'ghl-crm-integration' ); ?></h3>
			<p class="description" style="margin-bottom: 16px; color: #475569;">
				<?php esc_html_e( 'Fine-tune how the plugin handles BuddyBoss groups that are missing metadata and members who have not yet been synced to GoHighLevel.', 'ghl-crm-integration' ); ?>
			</p>
			<div style="display: grid; gap: 20px; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));">
				<div style="border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px; background: #f8fafc;">
					<h4 style="margin: 0 0 10px; color: #1e293b; font-size: 16px; font-weight: 600;">
						<?php esc_html_e( 'Missing Contacts', 'ghl-crm-integration' ); ?>
					</h4>
					<p style="margin: 0 0 14px; color: #475569; font-size: 14px; line-height: 1.5;">
						<?php esc_html_e( 'Choose whether the plugin should automatically create GoHighLevel contacts when a group member is missing one during association.', 'ghl-crm-integration' ); ?>
					</p>
					<div style="display: flex; flex-direction: column; gap: 8px;">
						<label style="display: flex; align-items: center; gap: 8px; font-size: 14px; color: #1e293b;">
							<input type="radio" name="buddyboss_missing_contact_strategy" value="create" <?php checked( 'create', $missing_contact_strategy ); ?>>
							<span><?php esc_html_e( 'Create missing contacts automatically (recommended for fully managed communities)', 'ghl-crm-integration' ); ?></span>
						</label>
						<label style="display: flex; align-items: center; gap: 8px; font-size: 14px; color: #1e293b;">
							<input type="radio" name="buddyboss_missing_contact_strategy" value="skip" <?php checked( 'skip', $missing_contact_strategy ); ?>>
							<span><?php esc_html_e( 'Skip association when contact is missing (manual review required)', 'ghl-crm-integration' ); ?></span>
						</label>
					</div>
				</div>
				<div style="border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px; background: #f8fafc;">
					<h4 style="margin: 0 0 10px; color: #1e293b; font-size: 16px; font-weight: 600;">
						<?php esc_html_e( 'Default Group Type', 'ghl-crm-integration' ); ?>
					</h4>
					<p style="margin: 0 0 14px; color: #475569; font-size: 14px; line-height: 1.5;">
						<?php esc_html_e( 'Provide a fallback BuddyBoss group type slug for groups that have no type assigned. Leave blank to skip those groups entirely.', 'ghl-crm-integration' ); ?>
					</p>
					<label for="buddyboss_default_group_type" class="screen-reader-text">
						<?php esc_html_e( 'Default BuddyBoss group type', 'ghl-crm-integration' ); ?>
					</label>
					<select 
						id="buddyboss_default_group_type" 
						name="buddyboss_default_group_type" 
						class="ghl-group-type-select"
						style="width: 100%;">
						<option value=""><?php esc_html_e( 'Skip groups without a type', 'ghl-crm-integration' ); ?></option>
						<?php if ( ! empty( $group_type_suggestions ) ) : ?>
							<?php foreach ( $group_type_suggestions as $slug => $type_obj ) : ?>
								<?php
								$label = '';
								if ( is_object( $type_obj ) ) {
									$labels = isset( $type_obj->labels ) && is_array( $type_obj->labels ) ? $type_obj->labels : [];
									$label  = $labels['name'] ?? $labels['singular_name'] ?? $type_obj->name ?? ucfirst( str_replace( '_', ' ', $slug ) );
								} elseif ( is_string( $type_obj ) ) {
									$label = $type_obj;
								} else {
									$label = ucfirst( str_replace( '_', ' ', (string) $slug ) );
								}
								?>
								<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $default_group_type, $slug ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						<?php endif; ?>
					</select>
				</div>
			</div>
		</div>

		<!-- Sync Options -->
		<div class="ghl-form-section">
			<h3><?php esc_html_e( 'Sync Options', 'ghl-crm-integration' ); ?></h3>
			
			<div class="ghl-checkbox-group" style="display: flex; flex-direction: column; gap: 12px;">
				<label class="ghl-checkbox <?php echo ! empty( $settings['buddyboss_sync_private_groups'] ) ? 'is-checked' : ''; ?>" style="display: flex; align-items: center; gap: 8px; margin: 0;">
					<input 
						type="checkbox" 
						class="ghl-checkbox-original"
						id="buddyboss_sync_private_groups"
						name="buddyboss_sync_private_groups" 
						value="1" 
						<?php checked( ! empty( $settings['buddyboss_sync_private_groups'] ) ); ?>
					>
					<span class="ghl-checkbox-input <?php echo ! empty( $settings['buddyboss_sync_private_groups'] ) ? 'is-checked' : ''; ?>">
						<span class="ghl-checkbox-inner"></span>
					</span>
					<span class="ghl-checkbox-label">
						<?php esc_html_e( 'Sync private groups', 'ghl-crm-integration' ); ?>
					</span>
				</label>
				
				<label class="ghl-checkbox <?php echo ! empty( $settings['buddyboss_sync_hidden_groups'] ) ? 'is-checked' : ''; ?>" style="display: flex; align-items: center; gap: 8px; margin: 0;">
					<input 
						type="checkbox" 
						class="ghl-checkbox-original"
						id="buddyboss_sync_hidden_groups"
						name="buddyboss_sync_hidden_groups" 
						value="1" 
						<?php checked( ! empty( $settings['buddyboss_sync_hidden_groups'] ) ); ?>
					>
					<span class="ghl-checkbox-input <?php echo ! empty( $settings['buddyboss_sync_hidden_groups'] ) ? 'is-checked' : ''; ?>">
						<span class="ghl-checkbox-inner"></span>
					</span>
					<span class="ghl-checkbox-label">
						<?php esc_html_e( 'Sync hidden groups', 'ghl-crm-integration' ); ?>
					</span>
				</label>
				
				<label class="ghl-checkbox <?php echo ! empty( $settings['buddyboss_real_time_sync'] ) ? 'is-checked' : ''; ?>" style="display: flex; align-items: center; gap: 8px; margin: 0;">
					<input 
						type="checkbox" 
						class="ghl-checkbox-original"
						id="buddyboss_real_time_sync"
						name="buddyboss_real_time_sync" 
						value="1" 
						<?php checked( ! empty( $settings['buddyboss_real_time_sync'] ) ); ?>
					>
					<span class="ghl-checkbox-input <?php echo ! empty( $settings['buddyboss_real_time_sync'] ) ? 'is-checked' : ''; ?>">
						<span class="ghl-checkbox-inner"></span>
					</span>
					<span class="ghl-checkbox-label">
						<?php esc_html_e( 'Enable real-time sync (recommended)', 'ghl-crm-integration' ); ?>
					</span>
				</label>
				
				<label class="ghl-checkbox <?php echo ! empty( $settings['buddyboss_log_sync_operations'] ) ? 'is-checked' : ''; ?>" style="display: flex; align-items: center; gap: 8px; margin: 0;">
					<input 
						type="checkbox" 
						class="ghl-checkbox-original"
						id="buddyboss_log_sync_operations"
						name="buddyboss_log_sync_operations" 
						value="1" 
						<?php checked( ! empty( $settings['buddyboss_log_sync_operations'] ) ); ?>
					>
					<span class="ghl-checkbox-input <?php echo ! empty( $settings['buddyboss_log_sync_operations'] ) ? 'is-checked' : ''; ?>">
						<span class="ghl-checkbox-inner"></span>
					</span>
					<span class="ghl-checkbox-label">
						<?php esc_html_e( 'Log all sync operations', 'ghl-crm-integration' ); ?>
					</span>
				</label>
			</div>
		</div>

		<!-- Bulk Sync Actions -->
		<div class="ghl-form-section">
			<h3><?php esc_html_e( 'Bulk Sync Actions', 'ghl-crm-integration' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Use these actions to perform one-time bulk synchronization of existing data.', 'ghl-crm-integration' ); ?>
			</p>
			
			<div class="ghl-bulk-actions">
				<button type="button" class="ghl-button ghl-button-secondary" id="ghl-sync-group-types">
					<span class="dashicons dashicons-admin-customizer"></span>
					<?php esc_html_e( 'Create Custom Objects for Group Types', 'ghl-crm-integration' ); ?>
				</button>
				<button type="button" class="ghl-button ghl-button-secondary" id="ghl-sync-all-groups">
					<span class="dashicons dashicons-groups"></span>
					<?php esc_html_e( 'Sync All Existing Groups', 'ghl-crm-integration' ); ?>
				</button>
				<span class="spinner"></span>
			</div>
		</div>
		</div><!-- .ghl-settings-body -->
	</div><!-- .ghl-settings-card -->

<script>
jQuery(document).ready(function($) {
	// Handle enable/disable toggle for main integration
	$('#buddyboss_groups_enabled').on('change', function() {
		const $checkbox = $(this);
		const $label = $checkbox.closest('.ghl-checkbox');
		const $labelText = $label.find('.ghl-checkbox-label');
		const $settingsBody = $('#buddyboss-settings-body');
		const isChecked = $checkbox.is(':checked');
		
		// Toggle UI state
		if (isChecked) {
			$label.addClass('is-checked');
			$label.find('.ghl-checkbox-input').addClass('is-checked');
			$labelText.text('<?php echo esc_js( __( 'Enabled', 'ghl-crm-integration' ) ); ?>');
			$settingsBody.slideDown(300);
		} else {
			$label.removeClass('is-checked');
			$label.find('.ghl-checkbox-input').removeClass('is-checked');
			$labelText.text('<?php echo esc_js( __( 'Disabled', 'ghl-crm-integration' ) ); ?>');
			$settingsBody.slideUp(300);
		}
	});

	// Handle all ghl-checkbox toggles
	$('.ghl-checkbox input[type="checkbox"]').on('change', function() {
		const $checkbox = $(this);
		const $label = $checkbox.closest('.ghl-checkbox');
		const isChecked = $checkbox.is(':checked');
		
		if (isChecked) {
			$label.addClass('is-checked');
			$label.find('.ghl-checkbox-input').addClass('is-checked');
		} else {
			$label.removeClass('is-checked');
			$label.find('.ghl-checkbox-input').removeClass('is-checked');
		}
	});

	// Handle bulk sync actions
	$('.ghl-bulk-actions button').on('click', function() {
		const $button = $(this);
		const $spinner = $('.ghl-bulk-actions .spinner');
		const syncType = $button.attr('id').replace('ghl-sync-', '').replace(/-/g, '_');
		
		$spinner.addClass('is-active');
		$button.prop('disabled', true);
		
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'ghl_buddyboss_bulk_sync',
				nonce: '<?php echo esc_js( wp_create_nonce( 'ghl_buddyboss_bulk_sync' ) ); ?>',
				sync_type: syncType
			},
			success: function(response) {
				if (response.success) {
					alert(response.message);
				} else {
					alert('Error: ' + response.message);
				}
			},
			error: function() {
				alert('Failed to start bulk sync');
			},
			complete: function() {
				$spinner.removeClass('is-active');
				$button.prop('disabled', false);
			}
		});
	});
});
</script>
<?php endif; ?>
