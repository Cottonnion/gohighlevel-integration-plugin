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
						<label class="ghl-checkbox ghl-advanced-checkbox-label <?php echo ! empty( $settings['enable_sync_logging'] ) ? 'is-checked' : ''; ?>">
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
						<p class="description ghl-description-spacing">
							<?php esc_html_e( 'When enabled, sync events and queue operations will be logged to wp_ghl_sync_log and wp_ghl_sync_queue tables. This provides detailed tracking but may impact performance on high-traffic sites. Disable to reduce database writes.', 'ghl-crm-integration' ); ?>
						</p>
						<?php if ( ! empty( $settings['enable_sync_logging'] ) ) : ?>
							<div class="ghl-logging-status-active">
								<p>
									<strong>✓ <?php esc_html_e( 'Active:', 'ghl-crm-integration' ); ?></strong>
									<?php esc_html_e( 'Logging is enabled. You can view logs in the Sync Logs section.', 'ghl-crm-integration' ); ?>
								</p>
							</div>
						<?php else : ?>
							<div class="ghl-logging-status-disabled">
								<p>
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
	<div class="ghl-settings-section ghl-settings-card ghl-family-section">
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
						<label class="ghl-checkbox ghl-advanced-checkbox-label <?php echo ! empty( $settings['enable_family_accounts'] ) ? 'is-checked' : ''; ?>">
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
								class="ghl-select2-tags ghl-family-tag-select" 
								data-saved-tag="<?php echo esc_attr( $settings['family_parent_tag'] ?? '' ); ?>">
							<option value=""><?php esc_html_e( 'Loading tags...', 'ghl-crm-integration' ); ?></option>
						</select>
						<p class="description ghl-description-spacing">
							<br>
							<button type="button" id="refresh-family-tags" class="ghl-button ghl-button-secondary ghl-refresh-tags-btn">
								<span class="dashicons dashicons-update"></span>
								<?php esc_html_e( 'Refresh Tags', 'ghl-crm-integration' ); ?>
							</button>
						</p>
			</td>
		</tr>

		<?php
		// Check if BuddyBoss is active and integration is enabled
		$is_buddyboss_active  = function_exists( 'bp_is_active' ) && bp_is_active( 'groups' );
		$buddyboss_enabled    = ! empty( $settings['buddyboss_groups_enabled'] );
		?>

		<?php if ( $is_buddyboss_active && $buddyboss_enabled ) : ?>
		<tr>
			<th scope="row">
				<label for="family_buddyboss_groups">
					<?php esc_html_e( 'BuddyBoss Group Integration', 'ghl-crm-integration' ); ?>
					<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Automatically create private BuddyBoss groups for each family. Children are added/removed from the group when linked/unlinked.', 'ghl-crm-integration' ); ?>">?</span>
				</label>
			</th>
			<td>
				<label class="ghl-checkbox ghl-advanced-checkbox-label <?php echo ! empty( $settings['family_buddyboss_groups'] ) ? 'is-checked' : ''; ?>">
					<input 
						type="checkbox" 
						class="ghl-checkbox-original"
						id="family_buddyboss_groups" 
						name="family_buddyboss_groups" 
						value="1"
						<?php checked( ! empty( $settings['family_buddyboss_groups'] ), true ); ?>
					>
					<span class="ghl-checkbox-input <?php echo ! empty( $settings['family_buddyboss_groups'] ) ? 'is-checked' : ''; ?>">
						<span class="ghl-checkbox-inner"></span>
					</span>
					<span class="ghl-checkbox-label">
						<?php esc_html_e( 'Create BuddyBoss groups for families', 'ghl-crm-integration' ); ?>
					</span>
				</label>
			</td>
		</tr>				<tr>
					<th scope="row">
						<label for="family_buddyboss_group_name">
							<?php esc_html_e( 'Group Naming Pattern', 'ghl-crm-integration' ); ?>
							<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Customize the BuddyBoss group name using variables. Available: {parent_name}, {parent_id}, {parent_tag}', 'ghl-crm-integration' ); ?>">?</span>
						</label>
					</th>
					<td>
						<input type="text" 
							   id="family_buddyboss_group_name" 
							   name="family_buddyboss_group_name" 
							   value="<?php echo esc_attr( $settings['family_buddyboss_group_name'] ?? "{parent_name}'s Family" ); ?>" 
					   class="regular-text"
					   placeholder="{parent_name}'s Family">
						<p class="description ghl-description-spacing">
							<?php esc_html_e( 'Available variables:', 'ghl-crm-integration' ); ?><br>
							<code>{parent_name}</code> - <?php esc_html_e( 'Parent\'s display name', 'ghl-crm-integration' ); ?><br>
							<code>{parent_id}</code> - <?php esc_html_e( 'Parent\'s WordPress user ID', 'ghl-crm-integration' ); ?><br>
							<code>{parent_tag}</code> - <?php esc_html_e( 'Parent account tag from GHL', 'ghl-crm-integration' ); ?>
							<br><br>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="family_buddyboss_group_description">
							<?php esc_html_e( 'Group Description Pattern', 'ghl-crm-integration' ); ?>
							<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Customize the group description with dynamic variables and include useful information about the family.', 'ghl-crm-integration' ); ?>">?</span>
						</label>
					</th>
					<td>
						<textarea 
							id="family_buddyboss_group_description" 
							name="family_buddyboss_group_description" 
							rows="4" 
							class="large-text"
							placeholder="Private family group for {parent_name} and their family members. Managed by {plugin_name}."
						><?php echo esc_textarea( $settings['family_buddyboss_group_description'] ?? "Private family group for {parent_name} and their family members.\n\nThis group is automatically managed by {plugin_name}.\nParent Account: {parent_name} (ID: {parent_id})\nCreated: {date_created}" ); ?></textarea>
						<p class="description ghl-description-spacing">
							<?php esc_html_e( 'Available variables:', 'ghl-crm-integration' ); ?><br>
							<code>{parent_name}</code> - <?php esc_html_e( 'Parent\'s display name', 'ghl-crm-integration' ); ?><br>
							<code>{parent_email}</code> - <?php esc_html_e( 'Parent\'s email address', 'ghl-crm-integration' ); ?><br>
							<code>{parent_id}</code> - <?php esc_html_e( 'Parent\'s WordPress user ID', 'ghl-crm-integration' ); ?><br>
							<code>{parent_tag}</code> - <?php esc_html_e( 'Parent account tag from GHL', 'ghl-crm-integration' ); ?><br>
							<code>{child_count}</code> - <?php esc_html_e( 'Number of children in the family', 'ghl-crm-integration' ); ?><br>
							<code>{plugin_name}</code> - <?php esc_html_e( 'Plugin name (GoHighLevel CRM Integration)', 'ghl-crm-integration' ); ?><br>
							<code>{site_name}</code> - <?php esc_html_e( 'WordPress site name', 'ghl-crm-integration' ); ?><br>
							<code>{site_url}</code> - <?php esc_html_e( 'WordPress site URL', 'ghl-crm-integration' ); ?><br>
							<code>{date_created}</code> - <?php esc_html_e( 'Current date when group is created', 'ghl-crm-integration' ); ?>
							<br><br>
						</p>
					</td>
				</tr>

				<?php if ( ! empty( $settings['family_buddyboss_groups'] ) ) : ?>
				<tr>
					<th scope="row">
						<?php esc_html_e( 'Sync Existing Families', 'ghl-crm-integration' ); ?>
					</th>
					<td>
						<button type="button" id="sync-families-to-buddyboss" class="ghl-button ghl-button-primary">
							<span class="dashicons dashicons-groups"></span>
							<?php esc_html_e( 'Sync All Families to BuddyBoss', 'ghl-crm-integration' ); ?>
						</button>
						<p class="description ghl-description-spacing">
							<?php esc_html_e( 'Create BuddyBoss groups for existing parent accounts and add their children as members. This is useful when enabling BuddyBoss integration for the first time.', 'ghl-crm-integration' ); ?>
						</p>
						<div id="sync-families-progress" class="ghl-sync-families-progress">
							<div id="sync-families-status"></div>
							<div id="sync-families-details" class="ghl-sync-families-details"></div>
						</div>
					</td>
				</tr>
				<?php endif; ?>

				<?php else : ?>
				<tr>
					<th scope="row">
						<?php esc_html_e( 'BuddyBoss Integration', 'ghl-crm-integration' ); ?>
					</th>
					<td>
						<div class="notice notice-warning inline ghl-buddyboss-notice">
							<p>
								<?php if ( ! $is_buddyboss_active ) : ?>
									<strong><?php esc_html_e( 'BuddyBoss Platform Required', 'ghl-crm-integration' ); ?></strong><br>
									<?php esc_html_e( 'Install and activate the BuddyBoss Platform plugin with Groups component enabled.', 'ghl-crm-integration' ); ?>
								<?php else : ?>
									<strong><?php esc_html_e( 'BuddyBoss Integration Disabled', 'ghl-crm-integration' ); ?></strong><br>
									<?php
									printf(
										/* translators: %s: Link to integrations page */
										esc_html__( 'Please enable BuddyBoss integration in the %s page first.', 'ghl-crm-integration' ),
										sprintf(
											'<a href="%s">%s</a>',
											esc_url( admin_url( 'admin.php?page=ghl-crm-integrations&tab=buddyboss' ) ),
											esc_html__( 'Integrations', 'ghl-crm-integration' )
										)
									);
									?>
								<?php endif; ?>
							</p>
						</div>
					</td>
				</tr>
				<?php endif; ?>

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
						<div class="ghl-family-stats-grid">
							<div class="ghl-family-stat-total">
								<div class="ghl-stat-value"><?php echo esc_html( $stats['total_families'] ); ?></div>
								<div class="ghl-stat-label"><?php esc_html_e( 'Total Families', 'ghl-crm-integration' ); ?></div>
							</div>
							<div class="ghl-family-stat-parents">
								<div class="ghl-stat-value"><?php echo esc_html( $stats['total_parents'] ); ?></div>
								<div class="ghl-stat-label"><?php esc_html_e( 'Parent Accounts', 'ghl-crm-integration' ); ?></div>
							</div>
							<div class="ghl-family-stat-children">
								<div class="ghl-stat-value"><?php echo esc_html( $stats['total_children'] ); ?></div>
								<div class="ghl-stat-label"><?php esc_html_e( 'Child Accounts', 'ghl-crm-integration' ); ?></div>
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
		<div class="ghl-card ghl-family-usage-card">
			<div class="ghl-card-header">
				<h3>
					<span class="dashicons dashicons-info"></span>
					<?php esc_html_e( 'How to Use Family Accounts', 'ghl-crm-integration' ); ?>
				</h3>
			</div>
			<div class="ghl-card-body">
				<p><?php esc_html_e( 'Family Accounts allow you to link users together in a parent-child hierarchy. Children must accept an invitation before being linked. Once linked, children automatically inherit their parent\'s GoHighLevel tags.', 'ghl-crm-integration' ); ?></p>
				<button type="button" id="ghl-toggle-family-docs" class="ghl-toggle-button" data-target="ghl-family-docs-wrapper" data-label-show="<?php esc_attr_e( 'Show usage examples', 'ghl-crm-integration' ); ?>" data-label-hide="<?php esc_attr_e( 'Hide usage examples', 'ghl-crm-integration' ); ?>" aria-expanded="false">
					<span class="dashicons dashicons-arrow-right"></span>
					<span class="ghl-toggle-button__label"><?php esc_html_e( 'Show usage examples', 'ghl-crm-integration' ); ?></span>
				</button>
			</div>
		</div>

		<div id="ghl-family-docs-wrapper" class="ghl-collapsible ghl-is-collapsed" data-collapsible>
			<div class="ghl-card ghl-family-docs-card">
				<div class="ghl-card-body">
				<h4><?php esc_html_e( '1. Add Shortcode to Your Site', 'ghl-crm-integration' ); ?></h4>
				<p><?php esc_html_e( 'Use this shortcode to let parents manage their children:', 'ghl-crm-integration' ); ?></p>
				<div class="ghl-shortcode-example">
					<code>[ghl_family_manager]</code>
				</div>
				<p class="description">
					<?php esc_html_e( 'Place this shortcode on any page, BuddyBoss profile tab, or dashboard widget. Only parents and admins will see the management interface.', 'ghl-crm-integration' ); ?>
				</p>

				<h4 class="ghl-docs-heading"><?php esc_html_e( '2. Parent Features', 'ghl-crm-integration' ); ?></h4>
				<ul class="ghl-docs-list">
					<li><?php esc_html_e( 'Search for users by email or username', 'ghl-crm-integration' ); ?></li>
					<li><?php esc_html_e( 'Create new user accounts and send email invitations', 'ghl-crm-integration' ); ?></li>
					<li><?php esc_html_e( 'Invite existing users to become children', 'ghl-crm-integration' ); ?></li>
					<li><?php esc_html_e( 'View all linked children with their acceptance status', 'ghl-crm-integration' ); ?></li>
					<li><?php esc_html_e( 'Unlink children from the family', 'ghl-crm-integration' ); ?></li>
				</ul>

				<h4 class="ghl-docs-heading"><?php esc_html_e( '3. Invitation Flow', 'ghl-crm-integration' ); ?></h4>
				<p><?php esc_html_e( 'The invitation system requires acceptance to protect user privacy:', 'ghl-crm-integration' ); ?></p>
				<ol class="ghl-docs-list">
					<li><?php esc_html_e( 'Parent enters child\'s email address', 'ghl-crm-integration' ); ?></li>
					<li><?php esc_html_e( 'System creates account (if new) or sends invite (if existing)', 'ghl-crm-integration' ); ?></li>
					<li><?php esc_html_e( 'Child receives email with secure acceptance link', 'ghl-crm-integration' ); ?></li>
					<li><?php esc_html_e( 'Child clicks link to accept (auto-login included)', 'ghl-crm-integration' ); ?></li>
					<li><?php esc_html_e( 'Upon acceptance: relationship created, parent tags synced, BuddyBoss group membership granted', 'ghl-crm-integration' ); ?></li>
				</ol>
				<p class="description ghl-docs-description">
					<strong><?php esc_html_e( 'Important:', 'ghl-crm-integration' ); ?></strong>
					<?php esc_html_e( 'Children remain in "pending" status until they accept. No relationship is created and no tags are synced until acceptance.', 'ghl-crm-integration' ); ?>
				</p>

				<h4 class="ghl-docs-heading"><?php esc_html_e( '4. Invitation Email Details', 'ghl-crm-integration' ); ?></h4>
				<ul class="ghl-docs-list">
					<li><?php esc_html_e( 'Secure token-based acceptance links (64 characters)', 'ghl-crm-integration' ); ?></li>
					<li><?php esc_html_e( 'Auto-login on acceptance (no password needed)', 'ghl-crm-integration' ); ?></li>
					<li><?php esc_html_e( '7-day expiration for security', 'ghl-crm-integration' ); ?></li>
					<li><?php esc_html_e( 'One-time use tokens (deleted after acceptance)', 'ghl-crm-integration' ); ?></li>
				</ul>
				<p class="description ghl-docs-description">
					<strong><?php esc_html_e( 'Example acceptance URL:', 'ghl-crm-integration' ); ?></strong><br>
					<code class="ghl-invite-url-example">
						<?php echo esc_url( home_url( '/?action=ghl_accept_invite&token=abc123...&uid=456' ) ); ?>
					</code>
				</p>

				<h4 class="ghl-docs-heading"><?php esc_html_e( '5. What Happens After Acceptance', 'ghl-crm-integration' ); ?></h4>
				<ul class="ghl-docs-list">
					<li><?php esc_html_e( 'Family relationship is created in database', 'ghl-crm-integration' ); ?></li>
					<li><?php esc_html_e( 'Parent\'s GoHighLevel tags are synced to child', 'ghl-crm-integration' ); ?></li>
					<li><?php esc_html_e( 'Child is added to parent\'s BuddyBoss group (if enabled)', 'ghl-crm-integration' ); ?></li>
					<li><?php esc_html_e( 'Child inherits parent\'s membership access', 'ghl-crm-integration' ); ?></li>
					<li><?php esc_html_e( 'User status changes from "pending" to "active"', 'ghl-crm-integration' ); ?></li>
				</ul>

				<h4 class="ghl-docs-heading"><?php esc_html_e( '6. Programmatic Usage', 'ghl-crm-integration' ); ?></h4>
				<p><?php esc_html_e( 'Developers can use the FamilyManager class:', 'ghl-crm-integration' ); ?></p>
				<div class="ghl-code-example">
					<div class="ghl-code-comment">// Get FamilyManager instance</div>
					<div>$manager = \GHL_CRM\Core\FamilyManager::get_instance();</div>
					<br>
					<div class="ghl-code-comment">// Create new user and send invite</div>
					<div>$result = $manager-&gt;create_and_invite(<span class="ghl-code-number">'child@example.com'</span>, <span class="ghl-code-number">123</span>); <span class="ghl-code-comment">// email, parent_id</span></div>
					<br>
					<div class="ghl-code-comment">// Invite existing user</div>
					<div>$result = $manager-&gt;invite_existing_user(<span class="ghl-code-number">456</span>, <span class="ghl-code-number">123</span>); <span class="ghl-code-comment">// user_id, parent_id</span></div>
					<br>
					<div class="ghl-code-comment">// Unlink a child</div>
					<div>$result = $manager-&gt;unlink_child(<span class="ghl-code-number">123</span>, <span class="ghl-code-number">456</span>); <span class="ghl-code-comment">// parent_id, child_id</span></div>
					<br>
					<div class="ghl-code-comment">// Access repository for queries</div>
					<div>$repo = \GHL_CRM\Database\FamilyRelationshipsRepository::get_instance();</div>
					<div>$children = $repo-&gt;get_children(<span class="ghl-code-number">123</span>); <span class="ghl-code-comment">// Returns array of child IDs</span></div>
					<div>$parent_id = $repo-&gt;get_parent(<span class="ghl-code-number">456</span>); <span class="ghl-code-comment">// Returns parent ID or null</span></div>
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
