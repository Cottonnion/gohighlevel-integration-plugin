<?php
/**
 * Settings - Family Relationships Upgrade Preview.
 *
 * @package    GHL_CRM_Integration
 * @subpackage GHL_CRM_Integration/templates/admin/partials/settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$upgrade_url = apply_filters( 'ghl_crm_upgrade_url', 'https://highlevelsync.com/' );
?>

<div class="ghl-settings-wrapper">
	<div class="ghl-settings-section ghl-settings-card">
		<div style="display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 20px;">
			<div>
				<span style="display: inline-flex; padding: 3px 9px; border-radius: 999px; background: #eef2ff; border: 1px solid #c7d2fe; color: #3730a3; font-size: 11px; font-weight: 700; text-transform: uppercase;"><?php esc_html_e( 'Syncly Pro', 'syncly' ); ?></span>
				<h2 style="margin: 8px 0 6px; color: #1e293b;">
					<span class="dashicons dashicons-groups"></span>
					<?php esc_html_e( 'Family Relationships', 'syncly' ); ?>
				</h2>
				<p class="description" style="max-width: 680px;">
					<?php esc_html_e( 'Create parent-child account relationships where child users inherit membership access and GoHighLevel tags from a parent account.', 'syncly' ); ?>
				</p>
			</div>
			<a href="<?php echo esc_url( $upgrade_url ); ?>" class="ghl-button ghl-button-primary" target="_blank" rel="noopener noreferrer">
				<span class="dashicons dashicons-unlock"></span>
				<?php esc_html_e( 'Learn More', 'syncly' ); ?>
			</a>
		</div>

		<div aria-hidden="true" style="display: grid; grid-template-columns: minmax(260px, 1fr) minmax(260px, 1.2fr); gap: 20px; opacity: 0.88;">
			<div style="padding: 18px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
				<h3 style="margin: 0 0 14px; color: #1e293b;"><?php esc_html_e( 'Family Account Settings', 'syncly' ); ?></h3>
				<div style="display: grid; gap: 12px;">
					<div style="display: flex; justify-content: space-between; gap: 12px; padding: 11px 12px; background: #fff; border: 1px solid #e5e7eb; border-radius: 6px;"><span><?php esc_html_e( 'Parent account tag', 'syncly' ); ?></span><strong>family-parent</strong></div>
					<div style="display: flex; justify-content: space-between; gap: 12px; padding: 11px 12px; background: #fff; border: 1px solid #e5e7eb; border-radius: 6px;"><span><?php esc_html_e( 'Inherited access', 'syncly' ); ?></span><strong><?php esc_html_e( 'Enabled', 'syncly' ); ?></strong></div>
					<div style="display: flex; justify-content: space-between; gap: 12px; padding: 11px 12px; background: #fff; border: 1px solid #e5e7eb; border-radius: 6px;"><span><?php esc_html_e( 'BuddyBoss groups', 'syncly' ); ?></span><strong><?php esc_html_e( 'Auto-create', 'syncly' ); ?></strong></div>
				</div>
			</div>

			<div style="padding: 18px; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px;">
				<h3 style="margin: 0 0 14px; color: #1e293b;"><?php esc_html_e( 'Relationship Overview', 'syncly' ); ?></h3>
				<div style="display: grid; gap: 10px;">
					<div style="display: grid; grid-template-columns: 1fr auto; align-items: center; gap: 12px; padding: 12px; background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 6px;">
						<div><strong>Jordan Parent</strong><br><span style="color: #64748b; font-size: 12px;">jordan@example.com</span></div>
						<span style="padding: 4px 8px; background: #dcfce7; color: #166534; border-radius: 999px; font-size: 12px; font-weight: 700;"><?php esc_html_e( 'Parent', 'syncly' ); ?></span>
					</div>
					<div style="margin-left: 26px; display: grid; gap: 8px;">
						<div style="padding: 10px 12px; background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 6px;">Alex Child <span style="color: #64748b;">- inherits family-parent, membership-active</span></div>
						<div style="padding: 10px 12px; background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 6px;">Taylor Child <span style="color: #64748b;">- invitation accepted</span></div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
