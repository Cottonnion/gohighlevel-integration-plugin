<?php
/**
 * Settings - Advanced Template
 *
 * Advanced settings tab content
 *
 * @package    GHL_CRM_Integration
 * @subpackage GHL_CRM_Integration/templates/admin/partials/settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings_manager = \GHL_CRM\Core\SettingsManager::get_instance();
$settings = $settings_manager->get_settings_array();
?>

<div class="ghl-settings-wrapper">
	<?php wp_nonce_field( 'ghl_crm_settings_nonce', 'ghl_crm_nonce' ); ?>
	
	<!-- Performance & Caching Section -->
	<div class="ghl-settings-section ghl-settings-card">
		<div class="ghl-settings-header">
			<h2>
				<span class="dashicons dashicons-performance"></span>
				<?php esc_html_e( 'Performance & Caching', 'ghl-crm-integration' ); ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Configure caching, batch processing, and data retention to optimize plugin performance.', 'ghl-crm-integration' ); ?>
			</p>
		</div>
		
		<hr>
		
		<div class="ghl-form-builder">
			<form class="ghl-form" method="post">
				<table class="form-table" role="presentation">
					<tbody>
				<tr>
					<th scope="row">
						<label for="cache_duration">
							<?php esc_html_e( 'Cache Duration', 'ghl-crm-integration' ); ?>
							<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'How long to store API responses in memory before fetching fresh data. Longer caching reduces API calls but may show stale data. Set to 0 to disable.', 'ghl-crm-integration' ); ?>">?</span>
						</label>
					</th>
					<td>
						<input type="number" 
							   id="cache_duration" 
							   name="cache_duration" 
							   value="<?php echo esc_attr( $settings['cache_duration'] ?? 3600 ); ?>" 
							   min="0"
							   max="86400"
							   class="small-text">
						<span><?php esc_html_e( 'seconds', 'ghl-crm-integration' ); ?></span>
						<p class="description">
							<?php esc_html_e( 'How long to cache API responses. Set to 0 to disable caching.', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label for="batch_size">
							<?php esc_html_e( 'Batch Size', 'ghl-crm-integration' ); ?>
							<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'How many items to process at once during bulk sync operations. Higher values = faster sync but more server load. Lower values = slower but safer for shared hosting.', 'ghl-crm-integration' ); ?>">?</span>
						</label>
					</th>
					<td>
						<input type="number" 
							   id="batch_size" 
							   name="batch_size" 
							   value="<?php echo esc_attr( $settings['batch_size'] ?? 50 ); ?>" 
							   min="1"
							   max="500"
							   class="small-text">
						<span><?php esc_html_e( 'items', 'ghl-crm-integration' ); ?></span>
						<p class="description">
							<?php esc_html_e( 'Number of items to process in each batch during sync.', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label for="log_retention_days">
							<?php esc_html_e( 'Log Retention Period', 'ghl-crm-integration' ); ?>
							<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'How long to keep historical sync logs and completed queue items before automatic deletion. Older logs are permanently removed to save database space.', 'ghl-crm-integration' ); ?>">?</span>
						</label>
					</th>
					<td>
						<input type="number" 
							   id="log_retention_days" 
							   name="log_retention_days" 
							   value="<?php echo esc_attr( $settings['log_retention_days'] ?? 30 ); ?>" 
							   min="1"
							   max="365"
							   class="small-text">
						<span><?php esc_html_e( 'days', 'ghl-crm-integration' ); ?></span>
						<p class="description">
							<?php esc_html_e( 'Number of days to keep sync logs and completed queue items before automatic cleanup.', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label for="enable_sync_logging">
							<?php esc_html_e( 'Sync & Queue Logging', 'ghl-crm-integration' ); ?>
							<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Records all sync operations and queue activities to the database for troubleshooting. Disable only if you need to minimize database writes or have confirmed everything works correctly.', 'ghl-crm-integration' ); ?>">?</span>
						</label>
					</th>
					<td>
						<label class="ghl-checkbox <?php echo ! empty( $settings['enable_sync_logging'] ) ? 'is-checked' : ''; ?>" style="display: flex; align-items: center; gap: 6px;">
							<input 
								type="checkbox" 
								class="ghl-checkbox-original"
								id="enable_sync_logging" 
								name="enable_sync_logging" 
								value="1"
								<?php checked( ! empty( $settings['enable_sync_logging'] ), true ); ?>
							>
							<span class="ghl-checkbox-input <?php echo ! empty( $settings['enable_sync_logging'] ) ? 'is-checked' : ''; ?>">
								<span class="ghl-checkbox-inner"></span>
							</span>
							<span class="ghl-checkbox-label">
								<?php esc_html_e( 'Enable logging to database tables', 'ghl-crm-integration' ); ?>
							</span>
						</label>
						<p class="description" style="margin-top: 8px;">
							<?php esc_html_e( 'When enabled, sync events and queue operations will be logged to wp_ghl_sync_log and wp_ghl_sync_queue tables. This provides detailed tracking but may impact performance on high-traffic sites. Disable to reduce database writes.', 'ghl-crm-integration' ); ?>
						</p>
						<?php if ( ! empty( $settings['enable_sync_logging'] ) ) : ?>
							<div style="margin-top: 12px; padding: 12px; background: #ecfdf5; border-left: 4px solid #10b981; border-radius: 4px;">
								<p style="margin: 0; font-size: 13px; color: #065f46;">
									<strong>✓ <?php esc_html_e( 'Active:', 'ghl-crm-integration' ); ?></strong>
									<?php esc_html_e( 'Logging is enabled. You can view logs in the Sync Logs section.', 'ghl-crm-integration' ); ?>
								</p>
							</div>
						<?php else : ?>
							<div style="margin-top: 12px; padding: 12px; background: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 4px;">
								<p style="margin: 0; font-size: 13px; color: #92400e;">
									<strong>⚠️ <?php esc_html_e( 'Disabled:', 'ghl-crm-integration' ); ?></strong>
									<?php esc_html_e( 'Logging is currently disabled. No sync events or queue operations will be recorded to the database.', 'ghl-crm-integration' ); ?>
								</p>
							</div>
						<?php endif; ?>
					</td>
				</tr>
					</tbody>
				</table>
			</form>
		</div>

		<hr>

		<!-- Save Button -->
		<button type="button" id="save-advanced-settings" class="ghl-button ghl-button-primary ghl-save-settings-btn">
			<span class="ghl-button-text"><?php esc_html_e( 'Save Advanced Settings', 'ghl-crm-integration' ); ?></span>
		</button>
	</div>

	<!-- Family Accounts Section -->
	<div class="ghl-settings-section ghl-settings-card" style="margin-top: 24px;">
		<div class="ghl-settings-header">
			<h2>
				<span class="dashicons dashicons-groups"></span>
				<?php esc_html_e( 'Family Accounts (Parent-Child Relationships)', 'ghl-crm-integration' ); ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Enable parent-child account relationships where children inherit membership access from their parent. Similar to Memberium\'s family accounts.', 'ghl-crm-integration' ); ?>
			</p>
		</div>
		
		<hr>
		
		<div class="ghl-form-builder">
			<form class="ghl-form" method="post">
				<table class="form-table" role="presentation">
					<tbody>
				<tr>
					<th scope="row">
						<label for="enable_family_accounts">
							<?php esc_html_e( 'Enable Family Accounts', 'ghl-crm-integration' ); ?>
							<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Activate parent-child account relationships. Children will automatically inherit their parent\'s membership access and GHL tags.', 'ghl-crm-integration' ); ?>">?</span>
						</label>
					</th>
					<td>
						<label class="ghl-checkbox <?php echo ! empty( $settings['enable_family_accounts'] ) ? 'is-checked' : ''; ?>" style="display: flex; align-items: center; gap: 6px;">
							<input 
								type="checkbox" 
								class="ghl-checkbox-original"
								id="enable_family_accounts" 
								name="enable_family_accounts" 
								value="1"
								<?php checked( ! empty( $settings['enable_family_accounts'] ), true ); ?>
							>
							<span class="ghl-checkbox-input <?php echo ! empty( $settings['enable_family_accounts'] ) ? 'is-checked' : ''; ?>">
								<span class="ghl-checkbox-inner"></span>
							</span>
							<span class="ghl-checkbox-label">
								<?php esc_html_e( 'Enable parent-child relationships', 'ghl-crm-integration' ); ?>
							</span>
						</label>
						<p class="description" style="margin-top: 8px;">
							<?php esc_html_e( 'When enabled, you can link child accounts to parent accounts. Children will inherit membership permissions and tags from their parent.', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label for="family_parent_tag">
							<?php esc_html_e( 'Parent Account Tag', 'ghl-crm-integration' ); ?>
							<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Select which GHL tag identifies parent accounts. This tag will be applied to parent contacts in GoHighLevel for automation triggers.', 'ghl-crm-integration' ); ?>">?</span>
						</label>
					</th>
					<td>
						<select id="family_parent_tag" 
								name="family_parent_tag" 
								class="ghl-select2-tags" 
								style="min-width: 300px;"
								data-saved-tag="<?php echo esc_attr( $settings['family_parent_tag'] ?? '' ); ?>">
							<option value=""><?php esc_html_e( 'Loading tags...', 'ghl-crm-integration' ); ?></option>
						</select>
						<p class="description" style="margin-top: 8px;">
							<?php esc_html_e( 'Choose an existing GHL tag to mark parent accounts. We recommend creating a dedicated tag like "Parent Account" or "Family Lead" in GoHighLevel first.', 'ghl-crm-integration' ); ?>
							<br>
							<button type="button" id="refresh-family-tags" class="ghl-button ghl-button-secondary" style="margin-top: 8px;">
								<span class="dashicons dashicons-update"></span>
								<?php esc_html_e( 'Refresh Tags', 'ghl-crm-integration' ); ?>
							</button>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="family_child_tag_pattern">
							<?php esc_html_e( 'Child Tag Pattern', 'ghl-crm-integration' ); ?>
							<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'The tag pattern applied to child accounts in GHL. Use {parent_id} as a placeholder for the parent\'s WordPress user ID.', 'ghl-crm-integration' ); ?>">?</span>
						</label>
					</th>
					<td>
						<input type="text" 
							   id="family_child_tag_pattern" 
							   name="family_child_tag_pattern" 
							   value="<?php echo esc_attr( $settings['family_child_tag_pattern'] ?? 'family-{parent_id}' ); ?>" 
							   class="regular-text"
							   placeholder="family-{parent_id}">
						<p class="description" style="margin-top: 8px;">
							<?php esc_html_e( 'Tag pattern for child accounts. Use {parent_id} to include the parent\'s WordPress user ID. Example: "family-{parent_id}" becomes "family-123" for parent user ID 123.', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="family_sync_mode">
							<?php esc_html_e( 'Tag Sync Mode', 'ghl-crm-integration' ); ?>
							<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'How the plugin handles conflicts when relationship tags are manually changed in GoHighLevel.', 'ghl-crm-integration' ); ?>">?</span>
						</label>
					</th>
					<td>
						<select id="family_sync_mode" name="family_sync_mode" style="min-width: 300px;">
							<option value="wp_authority" <?php selected( $settings['family_sync_mode'] ?? 'wp_authority', 'wp_authority' ); ?>>
								<?php esc_html_e( 'WordPress Authority (Auto-resync tags)', 'ghl-crm-integration' ); ?>
							</option>
							<option value="alert_only" <?php selected( $settings['family_sync_mode'] ?? 'wp_authority', 'alert_only' ); ?>>
								<?php esc_html_e( 'Alert Only (Log conflicts, don\'t auto-fix)', 'ghl-crm-integration' ); ?>
							</option>
							<option value="manual" <?php selected( $settings['family_sync_mode'] ?? 'wp_authority', 'manual' ); ?>>
								<?php esc_html_e( 'Manual Resolution (Admin reviews all conflicts)', 'ghl-crm-integration' ); ?>
							</option>
						</select>
						<p class="description" style="margin-top: 8px;">
							<strong><?php esc_html_e( 'WordPress Authority:', 'ghl-crm-integration' ); ?></strong> <?php esc_html_e( 'WordPress overwrites GHL tags automatically.', 'ghl-crm-integration' ); ?><br>
							<strong><?php esc_html_e( 'Alert Only:', 'ghl-crm-integration' ); ?></strong> <?php esc_html_e( 'Log discrepancies but don\'t modify GHL tags.', 'ghl-crm-integration' ); ?><br>
							<strong><?php esc_html_e( 'Manual Resolution:', 'ghl-crm-integration' ); ?></strong> <?php esc_html_e( 'Admin must manually approve each conflict resolution.', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>

				<?php
				// Get family account statistics
				$family_repo = \GHL_CRM\Database\FamilyRelationshipsRepository::get_instance();
				$stats = $family_repo->get_statistics();
				?>

				<?php if ( ! empty( $settings['enable_family_accounts'] ) && $stats['total_relationships'] > 0 ) : ?>
				<tr>
					<th scope="row">
						<?php esc_html_e( 'Current Statistics', 'ghl-crm-integration' ); ?>
					</th>
					<td>
						<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-top: 8px;">
							<div style="padding: 16px; background: #f0f9ff; border-left: 4px solid #3b82f6; border-radius: 4px;">
								<div style="font-size: 24px; font-weight: 700; color: #1e40af;"><?php echo esc_html( $stats['total_families'] ); ?></div>
								<div style="font-size: 13px; color: #1e3a8a; margin-top: 4px;"><?php esc_html_e( 'Total Families', 'ghl-crm-integration' ); ?></div>
							</div>
							<div style="padding: 16px; background: #ecfdf5; border-left: 4px solid #10b981; border-radius: 4px;">
								<div style="font-size: 24px; font-weight: 700; color: #065f46;"><?php echo esc_html( $stats['total_parents'] ); ?></div>
								<div style="font-size: 13px; color: #064e3b; margin-top: 4px;"><?php esc_html_e( 'Parent Accounts', 'ghl-crm-integration' ); ?></div>
							</div>
							<div style="padding: 16px; background: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 4px;">
								<div style="font-size: 24px; font-weight: 700; color: #92400e;"><?php echo esc_html( $stats['total_children'] ); ?></div>
								<div style="font-size: 13px; color: #78350f; margin-top: 4px;"><?php esc_html_e( 'Child Accounts', 'ghl-crm-integration' ); ?></div>
							</div>
						</div>
					</td>
				</tr>
				<?php endif; ?>
					</tbody>
				</table>
			</form>
		</div>

		<?php if ( ! empty( $settings['enable_family_accounts'] ) ) : ?>
		<!-- Usage Example -->
		<div class="ghl-card" style="margin-top: 24px;">
			<div class="ghl-card-header">
				<h3>
					<span class="dashicons dashicons-info"></span>
					<?php esc_html_e( 'How to Use Family Accounts', 'ghl-crm-integration' ); ?>
				</h3>
			</div>
			<div class="ghl-card-body">
				<p><?php esc_html_e( 'Family Accounts allow you to link users together in a parent-child hierarchy. When a parent user updates their tags in GoHighLevel, those tags are automatically applied to all their linked children.', 'ghl-crm-integration' ); ?></p>
				<button type="button" id="ghl-toggle-family-docs" class="ghl-toggle-button" data-target="ghl-family-docs-wrapper" data-label-show="<?php esc_attr_e( 'Show usage examples', 'ghl-crm-integration' ); ?>" data-label-hide="<?php esc_attr_e( 'Hide usage examples', 'ghl-crm-integration' ); ?>" aria-expanded="false">
					<span class="dashicons dashicons-arrow-right"></span>
					<span class="ghl-toggle-button__label"><?php esc_html_e( 'Show usage examples', 'ghl-crm-integration' ); ?></span>
				</button>
			</div>
		</div>

		<div id="ghl-family-docs-wrapper" class="ghl-collapsible ghl-is-collapsed" data-collapsible>
			<div class="ghl-card" style="margin-top: 12px;">
				<div class="ghl-card-body">
				<h4><?php esc_html_e( '1. Add Shortcode to Your Site', 'ghl-crm-integration' ); ?></h4>
				<p><?php esc_html_e( 'Use this shortcode to let parents manage their children:', 'ghl-crm-integration' ); ?></p>
				<div style="background: #f8f9fa; padding: 16px; border-radius: 4px; border: 1px solid #dee2e6; margin: 12px 0;">
					<code style="font-size: 14px; color: #d63384;">[ghl_family_manager]</code>
				</div>
				<p class="description">
					<?php esc_html_e( 'Place this shortcode on any page, BuddyBoss profile tab, or dashboard widget. Only parents and admins will see the management interface.', 'ghl-crm-integration' ); ?>
				</p>

				<h4 style="margin-top: 24px;"><?php esc_html_e( '2. Parent Features', 'ghl-crm-integration' ); ?></h4>
				<ul style="margin-left: 20px; list-style: disc;">
					<li><?php esc_html_e( 'View all linked children', 'ghl-crm-integration' ); ?></li>
					<li><?php esc_html_e( 'Generate invite links for children to self-register', 'ghl-crm-integration' ); ?></li>
					<li><?php esc_html_e( 'Manually link existing users as children', 'ghl-crm-integration' ); ?></li>
					<li><?php esc_html_e( 'Unlink children from their account', 'ghl-crm-integration' ); ?></li>
					<li><?php esc_html_e( 'View inherited memberships and permissions', 'ghl-crm-integration' ); ?></li>
				</ul>

				<h4 style="margin-top: 24px;"><?php esc_html_e( '3. Invite Link System', 'ghl-crm-integration' ); ?></h4>
				<p>
					<?php esc_html_e( 'Parents can generate unique invite links that:', 'ghl-crm-integration' ); ?>
				</p>
				<ul style="margin-left: 20px; list-style: disc;">
					<li><?php esc_html_e( 'Automatically link new registrations to the parent', 'ghl-crm-integration' ); ?></li>
					<li><?php esc_html_e( 'Apply parent\'s tag in GoHighLevel', 'ghl-crm-integration' ); ?></li>
					<li><?php esc_html_e( 'Inherit parent\'s membership permissions', 'ghl-crm-integration' ); ?></li>
					<li><?php esc_html_e( 'Expire after configurable time period or usage limit', 'ghl-crm-integration' ); ?></li>
				</ul>
				<p class="description" style="margin-top: 12px;">
					<strong><?php esc_html_e( 'Example invite URL:', 'ghl-crm-integration' ); ?></strong><br>
					<code style="background: #f8f9fa; padding: 4px 8px; border-radius: 3px; font-size: 12px;">
						<?php echo esc_url( home_url( '/register/?family_invite=abc123def456' ) ); ?>
					</code>
				</p>

				<h4 style="margin-top: 24px;"><?php esc_html_e( '4. Programmatic Usage', 'ghl-crm-integration' ); ?></h4>
				<p><?php esc_html_e( 'Developers can manage relationships via PHP:', 'ghl-crm-integration' ); ?></p>
				<div style="background: #282c34; color: #abb2bf; padding: 16px; border-radius: 4px; font-family: monospace; font-size: 13px; line-height: 1.6; margin: 12px 0; overflow-x: auto;">
					<div style="color: #7f848e;">// Get repository instance</div>
					<div>$repo = \GHL_CRM\Database\FamilyRelationshipsRepository::get_instance();</div>
					<br>
					<div style="color: #7f848e;">// Link child to parent</div>
					<div>$relationship_id = $repo-&gt;create_relationship(<span style="color: #98c379;">123</span>, <span style="color: #98c379;">456</span>); <span style="color: #7f848e;">// parent_id, child_id</span></div>
					<br>
					<div style="color: #7f848e;">// Get parent of a child</div>
					<div>$parent_id = $repo-&gt;get_parent(<span style="color: #98c379;">456</span>);</div>
					<br>
					<div style="color: #7f848e;">// Get all children of a parent</div>
					<div>$children = $repo-&gt;get_children(<span style="color: #98c379;">123</span>);</div>
					<br>
					<div style="color: #7f848e;">// Check if user is a parent</div>
					<div>$is_parent = $repo-&gt;is_parent(<span style="color: #98c379;">123</span>);</div>
				</div>
				</div>
			</div>
		</div>
		<?php endif; ?>

		<hr>

		<!-- Save Button -->
		<button type="button" id="save-family-settings" class="ghl-button ghl-button-primary ghl-save-settings-btn">
			<span class="ghl-button-text"><?php esc_html_e( 'Save Family Account Settings', 'ghl-crm-integration' ); ?></span>
		</button>
	</div>
</div>
