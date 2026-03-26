<?php
/**
 * Settings - Family Relationships Teaser Template
 *
 * Shows a greyed-out preview of the Family Relationships Pro feature
 * with hardcoded demo data to show users what they unlock with Pro.
 *
 * @package    GHL_CRM_Integration
 * @subpackage GHL_CRM_Integration/templates/admin/partials/settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="ghl-settings-wrapper">

	<!-- Upgrade CTA Banner -->
	<div style="background: linear-gradient(135deg, #eef2ff, #e0e7ff); border: 2px solid #c7d2fe; padding: 30px; border-radius: 12px; text-align: center; margin-bottom: 24px; position: relative; overflow: hidden;">
		<div style="position: absolute; top: -20px; right: -20px; width: 100px; height: 100px; background: linear-gradient(135deg, #6366f1, #8b5cf6); border-radius: 50%; opacity: 0.1;"></div>
		<span class="dashicons dashicons-groups" style="font-size: 48px; width: 48px; height: 48px; color: #6366f1; margin-bottom: 16px;"></span>
		<h3 style="margin: 0 0 8px; font-size: 20px; font-weight: 700; color: #1e293b;">
			<?php esc_html_e( 'Family Relationships is a Pro Feature', 'ghl-crm-integration' ); ?>
			<span style="background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; font-size: 12px; padding: 3px 8px; border-radius: 4px; margin-left: 8px; font-weight: 700; vertical-align: middle;">PRO</span>
		</h3>
		<p style="margin: 0 0 20px; color: #64748b; font-size: 14px; max-width: 520px; margin-left: auto; margin-right: auto;">
			<?php esc_html_e( 'Enable parent-child account relationships where children automatically inherit their parent\'s membership access and GoHighLevel tags. Perfect for family plans and household memberships.', 'ghl-crm-integration' ); ?>
		</p>
		<a href="<?php echo esc_url( apply_filters( 'ghl_crm_upgrade_url', 'https://highlevelsync.com/upgrade-to-pro' ) ); ?>" target="_blank" class="ghl-button ghl-button-primary" style="text-decoration: none; background: linear-gradient(135deg, #6366f1, #8b5cf6); border: none; font-size: 14px; padding: 10px 24px;">
			<?php esc_html_e( 'Upgrade to Pro', 'ghl-crm-integration' ); ?>
		</a>
	</div>

	<!-- Greyed-out Feature Preview with hardcoded demo data -->
	<div style="position: relative; pointer-events: none; user-select: none;">
		<!-- Overlay -->
		<div style="position: absolute; inset: 0; background: rgba(255,255,255,0.55); z-index: 5; border-radius: 12px;"></div>

		<!-- Family Accounts Section (mirrors Pro template) -->
		<div class="ghl-settings-section ghl-settings-card ghl-family-section" style="opacity: 0.65;">
			<div class="ghl-settings-header">
				<h2>
					<span class="dashicons dashicons-groups"></span>
					<?php esc_html_e( 'Family Accounts (Parent-Child Relationships)', 'ghl-crm-integration' ); ?>
					<span class="ghl-pro-badge" style="background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; font-size: 10px; padding: 2px 6px; border-radius: 4px; margin-left: 6px; font-weight: 700;">PRO</span>
				</h2>
				<p class="description">
					<?php esc_html_e( 'Enable parent-child account relationships where children inherit membership access from their parent. Similar to Memberium\'s family accounts.', 'ghl-crm-integration' ); ?>
				</p>
			</div>

			<hr>

			<div class="ghl-form-builder">
				<form class="ghl-form" method="post" onsubmit="return false;">
					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row">
									<label>
										<?php esc_html_e( 'Enable Family Accounts', 'ghl-crm-integration' ); ?>
										<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Activate parent-child account relationships. Children will automatically inherit their parent\'s membership access and GHL tags.', 'ghl-crm-integration' ); ?>">?</span>
									</label>
								</th>
								<td>
									<label class="ghl-checkbox ghl-advanced-checkbox-label is-checked">
										<input type="checkbox" class="ghl-checkbox-original" checked disabled>
										<span class="ghl-checkbox-input is-checked">
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
									<label>
										<?php esc_html_e( 'Parent Account Tag', 'ghl-crm-integration' ); ?>
										<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Select which GHL tag identifies parent accounts. This tag will be applied to parent contacts in GoHighLevel for automation triggers.', 'ghl-crm-integration' ); ?>">?</span>
									</label>
								</th>
								<td>
									<select disabled style="min-width: 280px;">
										<option selected><?php esc_html_e( 'family-parent', 'ghl-crm-integration' ); ?></option>
									</select>
									<p class="description" style="margin-top: 8px;">
										<button type="button" class="ghl-button ghl-button-secondary" disabled>
											<span class="dashicons dashicons-update"></span>
											<?php esc_html_e( 'Refresh Tags', 'ghl-crm-integration' ); ?>
										</button>
									</p>
								</td>
							</tr>

							<tr>
								<th scope="row" colspan="2">
									<h4 style="margin: 20px 0 10px;">
										<span class="dashicons dashicons-groups"></span>
										<?php esc_html_e( 'BuddyBoss Group Integration', 'ghl-crm-integration' ); ?>
									</h4>
									<p class="description" style="margin-bottom: 15px;">
										<?php esc_html_e( 'Automatically create private BuddyBoss groups for each family. Children are added/removed from the group when linked/unlinked.', 'ghl-crm-integration' ); ?>
									</p>
									<label class="ghl-checkbox ghl-advanced-checkbox-label is-checked">
										<input type="checkbox" class="ghl-checkbox-original" checked disabled>
										<span class="ghl-checkbox-input is-checked">
											<span class="ghl-checkbox-inner"></span>
										</span>
										<span class="ghl-checkbox-label">
											<?php esc_html_e( 'Create BuddyBoss groups for families', 'ghl-crm-integration' ); ?>
										</span>
									</label>
								</th>
							</tr>
						</tbody>
					</table>

					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row">
									<label>
										<?php esc_html_e( 'Group Naming Pattern', 'ghl-crm-integration' ); ?>
										<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Customize the BuddyBoss group name using variables.', 'ghl-crm-integration' ); ?>">?</span>
									</label>
								</th>
								<td>
									<input type="text" value="{parent_name}'s Family" class="regular-text" disabled>
									<p class="description">
										<?php esc_html_e( 'Available variables:', 'ghl-crm-integration' ); ?><br>
										<code>{parent_name}</code> - <?php esc_html_e( 'Parent\'s display name', 'ghl-crm-integration' ); ?><br>
										<code>{parent_id}</code> - <?php esc_html_e( 'Parent\'s WordPress user ID', 'ghl-crm-integration' ); ?><br>
										<code>{parent_tag}</code> - <?php esc_html_e( 'Parent account tag from GHL', 'ghl-crm-integration' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label>
										<?php esc_html_e( 'Group Description Pattern', 'ghl-crm-integration' ); ?>
										<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Customize the group description with dynamic variables.', 'ghl-crm-integration' ); ?>">?</span>
									</label>
								</th>
								<td>
									<textarea rows="4" class="large-text" disabled>Private family group for {parent_name} and their family members.

This group is automatically managed by {plugin_name}.
Parent Account: {parent_name} (ID: {parent_id})
Created: {date_created}</textarea>
									<p class="description">
										<?php esc_html_e( 'Available variables:', 'ghl-crm-integration' ); ?><br>
										<code>{parent_name}</code> - <?php esc_html_e( 'Parent\'s display name', 'ghl-crm-integration' ); ?><br>
										<code>{parent_email}</code> - <?php esc_html_e( 'Parent\'s email address', 'ghl-crm-integration' ); ?><br>
										<code>{child_count}</code> - <?php esc_html_e( 'Number of children in the family', 'ghl-crm-integration' ); ?><br>
										<code>{plugin_name}</code> - <?php esc_html_e( 'Plugin name', 'ghl-crm-integration' ); ?><br>
										<code>{site_name}</code> - <?php esc_html_e( 'WordPress site name', 'ghl-crm-integration' ); ?><br>
										<code>{date_created}</code> - <?php esc_html_e( 'Current date when group is created', 'ghl-crm-integration' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<?php esc_html_e( 'Sync Existing Families', 'ghl-crm-integration' ); ?>
								</th>
								<td>
									<button type="button" class="ghl-button ghl-button-secondary" disabled>
										<span class="dashicons dashicons-groups"></span>
										<?php esc_html_e( 'Sync All Families to BuddyBoss', 'ghl-crm-integration' ); ?>
									</button>
									<p class="description">
										<?php esc_html_e( 'Create BuddyBoss groups for existing parent accounts and add their children as members.', 'ghl-crm-integration' ); ?>
									</p>
								</td>
							</tr>

							<!-- Demo Statistics -->
							<tr>
								<th scope="row">
									<?php esc_html_e( 'Current Statistics', 'ghl-crm-integration' ); ?>
								</th>
								<td>
									<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; max-width: 480px;">
										<div style="text-align: center; padding: 16px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
											<div style="font-size: 28px; font-weight: 700; color: #6366f1;">12</div>
											<div style="font-size: 12px; color: #64748b; margin-top: 4px;"><?php esc_html_e( 'Total Families', 'ghl-crm-integration' ); ?></div>
										</div>
										<div style="text-align: center; padding: 16px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
											<div style="font-size: 28px; font-weight: 700; color: #10b981;">12</div>
											<div style="font-size: 12px; color: #64748b; margin-top: 4px;"><?php esc_html_e( 'Parent Accounts', 'ghl-crm-integration' ); ?></div>
										</div>
										<div style="text-align: center; padding: 16px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
											<div style="font-size: 28px; font-weight: 700; color: #f59e0b;">34</div>
											<div style="font-size: 12px; color: #64748b; margin-top: 4px;"><?php esc_html_e( 'Child Accounts', 'ghl-crm-integration' ); ?></div>
										</div>
									</div>
								</td>
							</tr>
						</tbody>
					</table>
				</form>
			</div>

			<!-- Usage Example (mirrors Pro) -->
			<div class="ghl-card" style="margin-top: 20px; border: 1px solid #e2e8f0; border-radius: 8px;">
				<div style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0;">
					<h3 style="margin: 0; font-size: 15px;">
						<span class="dashicons dashicons-info" style="color: #6366f1;"></span>
						<?php esc_html_e( 'How to Use Family Accounts', 'ghl-crm-integration' ); ?>
					</h3>
				</div>
				<div style="padding: 16px 20px;">
					<p><?php esc_html_e( 'Family Accounts allow you to link users together in a parent-child hierarchy. Children must accept an invitation before being linked. Once linked, children automatically inherit their parent\'s GoHighLevel tags.', 'ghl-crm-integration' ); ?></p>

					<h4><?php esc_html_e( 'Shortcode', 'ghl-crm-integration' ); ?></h4>
					<div style="background: #1e293b; color: #e2e8f0; padding: 12px 16px; border-radius: 6px; font-family: monospace; font-size: 13px; margin-bottom: 16px;">
						<code style="color: #a5b4fc;">[ghl_family_manager]</code>
					</div>
					<p class="description">
						<?php esc_html_e( 'Place this shortcode on any page. Only parents and admins will see the management interface.', 'ghl-crm-integration' ); ?>
					</p>

					<h4><?php esc_html_e( 'Parent Features', 'ghl-crm-integration' ); ?></h4>
					<ul style="list-style: disc; margin-left: 20px; color: #475569;">
						<li><?php esc_html_e( 'Search for users by email or username', 'ghl-crm-integration' ); ?></li>
						<li><?php esc_html_e( 'Create new user accounts and send email invitations', 'ghl-crm-integration' ); ?></li>
						<li><?php esc_html_e( 'Invite existing users to become children', 'ghl-crm-integration' ); ?></li>
						<li><?php esc_html_e( 'View all linked children with their acceptance status', 'ghl-crm-integration' ); ?></li>
						<li><?php esc_html_e( 'Unlink children from the family', 'ghl-crm-integration' ); ?></li>
					</ul>

					<h4><?php esc_html_e( 'Invitation Flow', 'ghl-crm-integration' ); ?></h4>
					<ol style="margin-left: 20px; color: #475569;">
						<li><?php esc_html_e( 'Parent enters child\'s email address', 'ghl-crm-integration' ); ?></li>
						<li><?php esc_html_e( 'System creates account (if new) or sends invite (if existing)', 'ghl-crm-integration' ); ?></li>
						<li><?php esc_html_e( 'Child receives email with secure acceptance link', 'ghl-crm-integration' ); ?></li>
						<li><?php esc_html_e( 'Child clicks link to accept (auto-login included)', 'ghl-crm-integration' ); ?></li>
						<li><?php esc_html_e( 'Upon acceptance: relationship created, parent tags synced', 'ghl-crm-integration' ); ?></li>
					</ol>

					<h4><?php esc_html_e( 'Programmatic Usage', 'ghl-crm-integration' ); ?></h4>
					<div style="background: #1e293b; color: #e2e8f0; padding: 12px 16px; border-radius: 6px; font-family: monospace; font-size: 12px; line-height: 1.8; overflow-x: auto;">
						<span style="color: #64748b;">// Get FamilyManager instance</span><br>
						<span style="color: #a5b4fc;">$manager</span> = \GHL_CRM_Pro\FamilyManager::<span style="color: #fbbf24;">get_instance</span>();<br>
						<br>
						<span style="color: #64748b;">// Create new user and send invite</span><br>
						<span style="color: #a5b4fc;">$result</span> = <span style="color: #a5b4fc;">$manager</span>-&gt;<span style="color: #fbbf24;">create_and_invite</span>(<span style="color: #34d399;">'child@example.com'</span>, <span style="color: #fbbf24;">123</span>);<br>
						<br>
						<span style="color: #64748b;">// Invite existing user</span><br>
						<span style="color: #a5b4fc;">$result</span> = <span style="color: #a5b4fc;">$manager</span>-&gt;<span style="color: #fbbf24;">invite_existing_user</span>(<span style="color: #fbbf24;">456</span>, <span style="color: #fbbf24;">123</span>);<br>
						<br>
						<span style="color: #64748b;">// Unlink a child</span><br>
						<span style="color: #a5b4fc;">$result</span> = <span style="color: #a5b4fc;">$manager</span>-&gt;<span style="color: #fbbf24;">unlink_child</span>(<span style="color: #fbbf24;">123</span>, <span style="color: #fbbf24;">456</span>);
					</div>
				</div>
			</div>

			<hr>

			<!-- Save Button (disabled) -->
			<button type="button" class="ghl-button ghl-button-primary ghl-save-settings-btn" disabled style="opacity: 0.65;">
				<span class="ghl-button-text"><?php esc_html_e( 'Save Family Account Settings', 'ghl-crm-integration' ); ?></span>
			</button>
		</div>
	</div>

</div>
