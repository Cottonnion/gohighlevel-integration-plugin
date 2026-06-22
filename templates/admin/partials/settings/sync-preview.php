<?php
/**
 * Settings - Sync Preview Template
 *
 * Test sync preview tab content
 *
 * @package    GHL_CRM_Integration
 * @subpackage GHL_CRM_Integration/templates/admin/partials/settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings_manager       = \GHL_CRM\Core\SettingsManager::get_instance();
$sync_preview_available = apply_filters( 'ghl_crm_sync_preview_enabled', false );

// Get all WordPress users for the dropdown
$users = get_users(
	array(
		'orderby' => 'display_name',
		'order'   => 'ASC',
		'number'  => 9999, // Limit to 500 users for performance
	)
);
?>

<div class="ghl-settings-wrapper">
	<?php wp_nonce_field( 'ghl_crm_settings_nonce', 'ghl_crm_nonce' ); ?>
	
	<!-- Sync Preview Section -->
	<div class="ghl-settings-section ghl-settings-card">
		<div class="ghl-settings-header">
			<h2>
				<span class="dashicons dashicons-visibility"></span>
				<?php esc_html_e( 'Sync Preview (Test Mode)', 'syncly' ); ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Preview what would happen if you sync a user to GoHighLevel without actually performing the sync. This is a "dry-run" test to see field changes, detect conflicts, and validate data before committing.', 'syncly' ); ?>
			</p>
		</div>
		
		<hr>
		
		<?php if ( $sync_preview_available ) : ?>
		<div class="ghl-form-builder">
			<form id="ghl-sync-preview-form" class="ghl-form" method="post">
				
			<!-- User Identifier Input -->
			<div class="ghl-form-item">
				<div class="ghl-form-item-content ghl-form-item-content--column">
					<label for="user_identifier" class="ghl-form-label">
						<?php esc_html_e( 'Select WordPress User', 'syncly' ); ?>
						<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Choose the WordPress user you want to preview. The preview will show you exactly what would happen if you sync this user to GoHighLevel.', 'syncly' ); ?>">?</span>
					</label>
					<select 
						id="user_identifier" 
						name="user_identifier" 
						class="ghl-input ghl-select2" 
						style="width: 100%;"
						required
					>
						<option value=""><?php esc_html_e( 'Choose a user...', 'syncly' ); ?></option>
						<?php foreach ( $users as $user ) : ?>
							<option value="<?php echo esc_attr( $user->user_email ); ?>" 
								data-id="<?php echo esc_attr( $user->ID ); ?>"
								data-login="<?php echo esc_attr( $user->user_login ); ?>"
								data-roles="<?php echo esc_attr( implode( ', ', $user->roles ) ); ?>">
								<?php echo esc_html( $user->display_name ); ?> 
								(<?php echo esc_html( $user->user_email ); ?>)
								<?php if ( ! empty( $user->roles ) ) : ?>
									- <?php echo esc_html( implode( ', ', array_map( 'ucfirst', $user->roles ) ) ); ?>
								<?php endif; ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>				<!-- Preview Button -->
				<div class="ghl-form-item">
					<button type="submit" id="ghl-preview-sync-btn" class="ghl-button ghl-button-primary">
						<span class="dashicons dashicons-search"></span>
						<?php esc_html_e( 'Preview Sync', 'syncly' ); ?>
					</button>
				</div>

			</form>
		</div>

		<!-- Preview Results Container -->
		<div id="ghl-preview-results" style="display: none; margin-top: 30px;">
			<h3><?php esc_html_e( 'Preview Results', 'syncly' ); ?></h3>
			<div id="ghl-preview-content"></div>
		</div>

	</div>
		<?php else : ?>

		<div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 22px; margin-bottom: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
			<div style="display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 18px;">
				<div>
					<span style="display: inline-flex; padding: 3px 9px; border-radius: 999px; background: #eef2ff; border: 1px solid #c7d2fe; color: #3730a3; font-size: 11px; font-weight: 700; text-transform: uppercase;"><?php esc_html_e( 'Syncly Pro', 'syncly' ); ?></span>
					<h3 style="margin: 8px 0 6px; font-size: 20px; color: #1e293b;"><?php esc_html_e( 'Sync Preview', 'syncly' ); ?></h3>
					<p style="margin: 0; color: #64748b; max-width: 620px;"><?php esc_html_e( 'Run a dry preview before syncing. Compare WordPress and GoHighLevel values, identify conflicts, and validate mapped fields without modifying data.', 'syncly' ); ?></p>
				</div>
				<a href="<?php echo esc_url( apply_filters( 'ghl_crm_upgrade_url', 'https://highlevelsync.com/' ) ); ?>" class="ghl-button ghl-button-primary" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Learn More', 'syncly' ); ?></a>
			</div>

			<div aria-hidden="true" style="display: grid; gap: 18px; opacity: 0.86;">
				<div style="display: flex; align-items: center; gap: 12px; padding: 14px; background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 8px;">
					<span style="flex: 1; padding: 9px 11px; background: #fff; border: 1px solid #d1d5db; border-radius: 6px; color: #475569;">John Smith (john@example.com) - Subscriber</span>
					<span style="padding: 9px 13px; background: #1f2937; color: #fff; border-radius: 6px; font-weight: 600;"><?php esc_html_e( 'Preview Sync', 'syncly' ); ?></span>
				</div>

				<div>
					<h3 style="margin: 0 0 12px;"><?php esc_html_e( 'Preview Results', 'syncly' ); ?></h3>
					<div style="display: grid; grid-template-columns: repeat(4, minmax(120px, 1fr)); gap: 12px; margin-bottom: 18px;">
						<div style="text-align: center; padding: 16px; background: #f0fdf4; border-radius: 8px; border: 1px solid #bbf7d0;">
							<div style="font-size: 24px; font-weight: 700; color: #16a34a;">8</div>
							<div style="font-size: 11px; color: #64748b; margin-top: 2px;"><?php esc_html_e( 'Fields to Sync', 'syncly' ); ?></div>
						</div>
						<div style="text-align: center; padding: 16px; background: #eff6ff; border-radius: 8px; border: 1px solid #bfdbfe;">
							<div style="font-size: 24px; font-weight: 700; color: #2563eb;">3</div>
							<div style="font-size: 11px; color: #64748b; margin-top: 2px;"><?php esc_html_e( 'Updates', 'syncly' ); ?></div>
						</div>
						<div style="text-align: center; padding: 16px; background: #fefce8; border-radius: 8px; border: 1px solid #fde68a;">
							<div style="font-size: 24px; font-weight: 700; color: #ca8a04;">1</div>
							<div style="font-size: 11px; color: #64748b; margin-top: 2px;"><?php esc_html_e( 'Conflicts', 'syncly' ); ?></div>
						</div>
						<div style="text-align: center; padding: 16px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
							<div style="font-size: 24px; font-weight: 700; color: #64748b;">4</div>
							<div style="font-size: 11px; color: #64748b; margin-top: 2px;"><?php esc_html_e( 'Unchanged', 'syncly' ); ?></div>
						</div>
					</div>

					<div style="border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
						<table style="width: 100%; border-collapse: collapse; font-size: 13px;">
							<thead>
								<tr style="background: #f8fafc;">
									<th style="padding: 10px 14px; text-align: left; font-weight: 600; color: #475569; border-bottom: 1px solid #e2e8f0;"><?php esc_html_e( 'Field', 'syncly' ); ?></th>
									<th style="padding: 10px 14px; text-align: left; font-weight: 600; color: #475569; border-bottom: 1px solid #e2e8f0;"><?php esc_html_e( 'WordPress', 'syncly' ); ?></th>
									<th style="padding: 10px 14px; text-align: left; font-weight: 600; color: #475569; border-bottom: 1px solid #e2e8f0;"><?php esc_html_e( 'GoHighLevel', 'syncly' ); ?></th>
									<th style="padding: 10px 14px; text-align: center; font-weight: 600; color: #475569; border-bottom: 1px solid #e2e8f0;"><?php esc_html_e( 'Status', 'syncly' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<tr style="border-bottom: 1px solid #f1f5f9;">
									<td style="padding: 10px 14px; font-weight: 500;">first_name</td>
									<td style="padding: 10px 14px; color: #16a34a;">John</td>
									<td style="padding: 10px 14px; color: #64748b;">John</td>
									<td style="padding: 10px 14px; text-align: center;"><span style="background: #f0fdf4; color: #16a34a; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;"><?php esc_html_e( 'Match', 'syncly' ); ?></span></td>
								</tr>
								<tr style="border-bottom: 1px solid #f1f5f9;">
									<td style="padding: 10px 14px; font-weight: 500;">last_name</td>
									<td style="padding: 10px 14px; color: #16a34a;">Smith</td>
									<td style="padding: 10px 14px; color: #64748b;">Smithson</td>
									<td style="padding: 10px 14px; text-align: center;"><span style="background: #eff6ff; color: #2563eb; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;"><?php esc_html_e( 'Update', 'syncly' ); ?></span></td>
								</tr>
								<tr style="border-bottom: 1px solid #f1f5f9;">
									<td style="padding: 10px 14px; font-weight: 500;">email</td>
									<td style="padding: 10px 14px; color: #16a34a;">john@example.com</td>
									<td style="padding: 10px 14px; color: #64748b;">john@example.com</td>
									<td style="padding: 10px 14px; text-align: center;"><span style="background: #f0fdf4; color: #16a34a; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;"><?php esc_html_e( 'Match', 'syncly' ); ?></span></td>
								</tr>
								<tr style="border-bottom: 1px solid #f1f5f9;">
									<td style="padding: 10px 14px; font-weight: 500;">phone</td>
									<td style="padding: 10px 14px; color: #16a34a;">+1 (555) 123-4567</td>
									<td style="padding: 10px 14px; color: #64748b;">+15551234567</td>
									<td style="padding: 10px 14px; text-align: center;"><span style="background: #fefce8; color: #ca8a04; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;"><?php esc_html_e( 'Conflict', 'syncly' ); ?></span></td>
								</tr>
								<tr style="border-bottom: 1px solid #f1f5f9;">
									<td style="padding: 10px 14px; font-weight: 500;">company</td>
									<td style="padding: 10px 14px; color: #16a34a;">Acme Corp</td>
									<td style="padding: 10px 14px; color: #dc2626; font-style: italic;">—</td>
									<td style="padding: 10px 14px; text-align: center;"><span style="background: #eff6ff; color: #2563eb; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;"><?php esc_html_e( 'Update', 'syncly' ); ?></span></td>
								</tr>
								<tr style="border-bottom: 1px solid #f1f5f9;">
									<td style="padding: 10px 14px; font-weight: 500;">address</td>
									<td style="padding: 10px 14px; color: #16a34a;">123 Main St</td>
									<td style="padding: 10px 14px; color: #64748b;">123 Main St</td>
									<td style="padding: 10px 14px; text-align: center;"><span style="background: #f0fdf4; color: #16a34a; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;"><?php esc_html_e( 'Match', 'syncly' ); ?></span></td>
								</tr>
								<tr style="border-bottom: 1px solid #f1f5f9;">
									<td style="padding: 10px 14px; font-weight: 500;">tags</td>
									<td style="padding: 10px 14px; color: #16a34a;">member, vip</td>
									<td style="padding: 10px 14px; color: #64748b;">member</td>
									<td style="padding: 10px 14px; text-align: center;"><span style="background: #eff6ff; color: #2563eb; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;"><?php esc_html_e( 'Update', 'syncly' ); ?></span></td>
								</tr>
								<tr>
									<td style="padding: 10px 14px; font-weight: 500;">city</td>
									<td style="padding: 10px 14px; color: #16a34a;">New York</td>
									<td style="padding: 10px 14px; color: #64748b;">New York</td>
									<td style="padding: 10px 14px; text-align: center;"><span style="background: #f0fdf4; color: #16a34a; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;"><?php esc_html_e( 'Match', 'syncly' ); ?></span></td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>

		<?php endif; ?>

	<!-- Info Box -->
	<div class="ghl-info-box" style="background: #e7f3ff; border-left: 4px solid #2271b1; padding: 15px; margin-top: 20px;">
		<h4 style="margin-top: 0;">
			<span class="dashicons dashicons-info" style="color: #2271b1;"></span>
			<?php esc_html_e( 'About Sync Preview', 'syncly' ); ?>
		</h4>
		<ul style="margin: 10px 0 0 20px;">
			<li><?php esc_html_e( '✅ No data is modified - this is a read-only preview', 'syncly' ); ?></li>
			<li><?php esc_html_e( '✅ Shows exactly what fields will be synced', 'syncly' ); ?></li>
			<li><?php esc_html_e( '✅ Detects conflicts and validation errors', 'syncly' ); ?></li>
			<li><?php esc_html_e( '✅ See before/after values for all mapped fields', 'syncly' ); ?></li>
			<li><?php esc_html_e( '⚡ Perfect for testing field mappings and troubleshooting', 'syncly' ); ?></li>
		</ul>
	</div>

</div>