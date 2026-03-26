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

$settings_manager = \GHL_CRM\Core\SettingsManager::get_instance();
$sync_preview_available = apply_filters( 'ghl_crm_sync_preview_enabled', false );

// Get all WordPress users for the dropdown
$users = get_users( array(
	'orderby' => 'display_name',
	'order'   => 'ASC',
	'number'  => 9999, // Limit to 500 users for performance
) );
?>

<div class="ghl-settings-wrapper">
	<?php wp_nonce_field( 'ghl_crm_settings_nonce', 'ghl_crm_nonce' ); ?>
	
	<!-- Sync Preview Section -->
	<div class="ghl-settings-section ghl-settings-card">
		<div class="ghl-settings-header">
			<h2>
				<span class="dashicons dashicons-visibility"></span>
				<?php esc_html_e( 'Sync Preview (Test Mode)', 'ghl-crm-integration' ); ?>
				<?php if ( ! $sync_preview_available ) : ?>
					<span style="background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; font-size: 12px; padding: 3px 8px; border-radius: 4px; margin-left: 8px; font-weight: 700; vertical-align: middle;">PRO</span>
				<?php endif; ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Preview what would happen if you sync a user to GoHighLevel without actually performing the sync. This is a "dry-run" test to see field changes, detect conflicts, and validate data before committing.', 'ghl-crm-integration' ); ?>
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
						<?php esc_html_e( 'Select WordPress User', 'ghl-crm-integration' ); ?>
						<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Choose the WordPress user you want to preview. The preview will show you exactly what would happen if you sync this user to GoHighLevel.', 'ghl-crm-integration' ); ?>">?</span>
					</label>
					<select 
						id="user_identifier" 
						name="user_identifier" 
						class="ghl-input ghl-select2" 
						style="width: 100%;"
						required
					>
						<option value=""><?php esc_html_e( 'Choose a user...', 'ghl-crm-integration' ); ?></option>
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
						<?php esc_html_e( 'Preview Sync', 'ghl-crm-integration' ); ?>
					</button>
				</div>

			</form>
		</div>

		<!-- Preview Results Container -->
		<div id="ghl-preview-results" style="display: none; margin-top: 30px;">
			<h3><?php esc_html_e( 'Preview Results', 'ghl-crm-integration' ); ?></h3>
			<div id="ghl-preview-content"></div>
		</div>

	</div>
		<?php else : ?>

		<!-- Upgrade CTA Banner -->
		<div style="background: linear-gradient(135deg, #eef2ff, #e0e7ff); border: 2px solid #c7d2fe; padding: 30px; border-radius: 12px; text-align: center; margin-bottom: 24px; position: relative; overflow: hidden;">
			<div style="position: absolute; top: -20px; right: -20px; width: 100px; height: 100px; background: linear-gradient(135deg, #6366f1, #8b5cf6); border-radius: 50%; opacity: 0.1;"></div>
			<span class="dashicons dashicons-visibility" style="font-size: 48px; width: 48px; height: 48px; color: #6366f1; margin-bottom: 16px;"></span>
			<h3 style="margin: 0 0 8px; font-size: 20px; font-weight: 700; color: #1e293b;">
				<?php esc_html_e( 'Sync Preview is a Pro Feature', 'ghl-crm-integration' ); ?>
				<span style="background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; font-size: 12px; padding: 3px 8px; border-radius: 4px; margin-left: 8px; font-weight: 700; vertical-align: middle;">PRO</span>
			</h3>
			<p style="margin: 0 0 20px; color: #64748b; font-size: 14px; max-width: 520px; margin-left: auto; margin-right: auto;">
				<?php esc_html_e( 'Preview field-by-field comparisons, detect conflicts, and validate data before committing to a sync. See exactly what will happen with a dry-run test.', 'ghl-crm-integration' ); ?>
			</p>
			<a href="<?php echo esc_url( apply_filters( 'ghl_crm_upgrade_url', 'https://highlevelsync.com/upgrade-to-pro' ) ); ?>" target="_blank" class="ghl-button ghl-button-primary" style="text-decoration: none; background: linear-gradient(135deg, #6366f1, #8b5cf6); border: none; font-size: 14px; padding: 10px 24px;">
				<?php esc_html_e( 'Upgrade to Pro', 'ghl-crm-integration' ); ?>
			</a>
		</div>

		<!-- Greyed-out Preview -->
		<div style="position: relative; pointer-events: none; user-select: none;">
			<div style="position: absolute; inset: 0; background: rgba(255,255,255,0.55); z-index: 5; border-radius: 12px;"></div>

			<div style="opacity: 0.65;">
				<!-- Simulated user select form -->
				<div class="ghl-form-builder">
					<form class="ghl-form" onsubmit="return false;">
						<div class="ghl-form-item">
							<div class="ghl-form-item-content ghl-form-item-content--column">
								<label class="ghl-form-label">
									<?php esc_html_e( 'Select WordPress User', 'ghl-crm-integration' ); ?>
									<span class="ghl-tooltip-icon">?</span>
								</label>
								<select disabled style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px;">
									<option>John Smith (john@example.com) - Subscriber</option>
								</select>
							</div>
						</div>
						<div class="ghl-form-item">
							<button type="button" class="ghl-button ghl-button-primary" disabled>
								<span class="dashicons dashicons-search"></span>
								<?php esc_html_e( 'Preview Sync', 'ghl-crm-integration' ); ?>
							</button>
						</div>
					</form>
				</div>

				<!-- Simulated Preview Results -->
				<div style="margin-top: 30px;">
					<h3><?php esc_html_e( 'Preview Results', 'ghl-crm-integration' ); ?></h3>

					<!-- Summary cards -->
					<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 24px;">
						<div style="text-align: center; padding: 16px; background: #f0fdf4; border-radius: 8px; border: 1px solid #bbf7d0;">
							<div style="font-size: 24px; font-weight: 700; color: #16a34a;">8</div>
							<div style="font-size: 11px; color: #64748b; margin-top: 2px;"><?php esc_html_e( 'Fields to Sync', 'ghl-crm-integration' ); ?></div>
						</div>
						<div style="text-align: center; padding: 16px; background: #eff6ff; border-radius: 8px; border: 1px solid #bfdbfe;">
							<div style="font-size: 24px; font-weight: 700; color: #2563eb;">3</div>
							<div style="font-size: 11px; color: #64748b; margin-top: 2px;"><?php esc_html_e( 'Updates', 'ghl-crm-integration' ); ?></div>
						</div>
						<div style="text-align: center; padding: 16px; background: #fefce8; border-radius: 8px; border: 1px solid #fde68a;">
							<div style="font-size: 24px; font-weight: 700; color: #ca8a04;">1</div>
							<div style="font-size: 11px; color: #64748b; margin-top: 2px;"><?php esc_html_e( 'Conflicts', 'ghl-crm-integration' ); ?></div>
						</div>
						<div style="text-align: center; padding: 16px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
							<div style="font-size: 24px; font-weight: 700; color: #64748b;">4</div>
							<div style="font-size: 11px; color: #64748b; margin-top: 2px;"><?php esc_html_e( 'Unchanged', 'ghl-crm-integration' ); ?></div>
						</div>
					</div>

					<!-- Simulated field comparison table -->
					<div style="border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
						<table style="width: 100%; border-collapse: collapse; font-size: 13px;">
							<thead>
								<tr style="background: #f8fafc;">
									<th style="padding: 10px 14px; text-align: left; font-weight: 600; color: #475569; border-bottom: 1px solid #e2e8f0;"><?php esc_html_e( 'Field', 'ghl-crm-integration' ); ?></th>
									<th style="padding: 10px 14px; text-align: left; font-weight: 600; color: #475569; border-bottom: 1px solid #e2e8f0;"><?php esc_html_e( 'WordPress', 'ghl-crm-integration' ); ?></th>
									<th style="padding: 10px 14px; text-align: left; font-weight: 600; color: #475569; border-bottom: 1px solid #e2e8f0;"><?php esc_html_e( 'GoHighLevel', 'ghl-crm-integration' ); ?></th>
									<th style="padding: 10px 14px; text-align: center; font-weight: 600; color: #475569; border-bottom: 1px solid #e2e8f0;"><?php esc_html_e( 'Status', 'ghl-crm-integration' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<tr style="border-bottom: 1px solid #f1f5f9;">
									<td style="padding: 10px 14px; font-weight: 500;">first_name</td>
									<td style="padding: 10px 14px; color: #16a34a;">John</td>
									<td style="padding: 10px 14px; color: #64748b;">John</td>
									<td style="padding: 10px 14px; text-align: center;"><span style="background: #f0fdf4; color: #16a34a; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;"><?php esc_html_e( 'Match', 'ghl-crm-integration' ); ?></span></td>
								</tr>
								<tr style="border-bottom: 1px solid #f1f5f9;">
									<td style="padding: 10px 14px; font-weight: 500;">last_name</td>
									<td style="padding: 10px 14px; color: #16a34a;">Smith</td>
									<td style="padding: 10px 14px; color: #64748b;">Smithson</td>
									<td style="padding: 10px 14px; text-align: center;"><span style="background: #eff6ff; color: #2563eb; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;"><?php esc_html_e( 'Update', 'ghl-crm-integration' ); ?></span></td>
								</tr>
								<tr style="border-bottom: 1px solid #f1f5f9;">
									<td style="padding: 10px 14px; font-weight: 500;">email</td>
									<td style="padding: 10px 14px; color: #16a34a;">john@example.com</td>
									<td style="padding: 10px 14px; color: #64748b;">john@example.com</td>
									<td style="padding: 10px 14px; text-align: center;"><span style="background: #f0fdf4; color: #16a34a; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;"><?php esc_html_e( 'Match', 'ghl-crm-integration' ); ?></span></td>
								</tr>
								<tr style="border-bottom: 1px solid #f1f5f9;">
									<td style="padding: 10px 14px; font-weight: 500;">phone</td>
									<td style="padding: 10px 14px; color: #16a34a;">+1 (555) 123-4567</td>
									<td style="padding: 10px 14px; color: #64748b;">+15551234567</td>
									<td style="padding: 10px 14px; text-align: center;"><span style="background: #fefce8; color: #ca8a04; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;"><?php esc_html_e( 'Conflict', 'ghl-crm-integration' ); ?></span></td>
								</tr>
								<tr style="border-bottom: 1px solid #f1f5f9;">
									<td style="padding: 10px 14px; font-weight: 500;">company</td>
									<td style="padding: 10px 14px; color: #16a34a;">Acme Corp</td>
									<td style="padding: 10px 14px; color: #dc2626; font-style: italic;">—</td>
									<td style="padding: 10px 14px; text-align: center;"><span style="background: #eff6ff; color: #2563eb; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;"><?php esc_html_e( 'Update', 'ghl-crm-integration' ); ?></span></td>
								</tr>
								<tr style="border-bottom: 1px solid #f1f5f9;">
									<td style="padding: 10px 14px; font-weight: 500;">address</td>
									<td style="padding: 10px 14px; color: #16a34a;">123 Main St</td>
									<td style="padding: 10px 14px; color: #64748b;">123 Main St</td>
									<td style="padding: 10px 14px; text-align: center;"><span style="background: #f0fdf4; color: #16a34a; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;"><?php esc_html_e( 'Match', 'ghl-crm-integration' ); ?></span></td>
								</tr>
								<tr style="border-bottom: 1px solid #f1f5f9;">
									<td style="padding: 10px 14px; font-weight: 500;">tags</td>
									<td style="padding: 10px 14px; color: #16a34a;">member, vip</td>
									<td style="padding: 10px 14px; color: #64748b;">member</td>
									<td style="padding: 10px 14px; text-align: center;"><span style="background: #eff6ff; color: #2563eb; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;"><?php esc_html_e( 'Update', 'ghl-crm-integration' ); ?></span></td>
								</tr>
								<tr>
									<td style="padding: 10px 14px; font-weight: 500;">city</td>
									<td style="padding: 10px 14px; color: #16a34a;">New York</td>
									<td style="padding: 10px 14px; color: #64748b;">New York</td>
									<td style="padding: 10px 14px; text-align: center;"><span style="background: #f0fdf4; color: #16a34a; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;"><?php esc_html_e( 'Match', 'ghl-crm-integration' ); ?></span></td>
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
			<?php esc_html_e( 'About Sync Preview', 'ghl-crm-integration' ); ?>
		</h4>
		<ul style="margin: 10px 0 0 20px;">
			<li><?php esc_html_e( '✅ No data is modified - this is a read-only preview', 'ghl-crm-integration' ); ?></li>
			<li><?php esc_html_e( '✅ Shows exactly what fields will be synced', 'ghl-crm-integration' ); ?></li>
			<li><?php esc_html_e( '✅ Detects conflicts and validation errors', 'ghl-crm-integration' ); ?></li>
			<li><?php esc_html_e( '✅ See before/after values for all mapped fields', 'ghl-crm-integration' ); ?></li>
			<li><?php esc_html_e( '⚡ Perfect for testing field mappings and troubleshooting', 'ghl-crm-integration' ); ?></li>
		</ul>
	</div>

</div>