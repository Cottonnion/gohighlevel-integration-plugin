<?php
/**
 * Settings - Upgrade to Pro
 *
 * Centralized list of features available in Syncly Pro but not in the free
 * plugin. This is the single place free users are pointed to from contextual
 * notices elsewhere in the plugin; it contains no mockups or disabled
 * controls, only descriptions, benefits, and a link to learn more.
 *
 * @package    Syncly
 * @subpackage Syncly/templates/admin/partials/settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$upgrade_url = apply_filters( 'syncly_upgrade_url', 'https://highlevelsync.com/' );

$pro_features = [
	[
		'icon'        => 'dashicons-editor-code',
		'title'       => __( 'Public REST API', 'syncly' ),
		'description' => __( 'Secure endpoints for external systems to create contacts, trigger syncs, check status, and receive webhook events.', 'syncly' ),
	],
	[
		'icon'        => 'dashicons-visibility',
		'title'       => __( 'Sync Preview, supercharged', 'syncly' ),
		'description' => __( 'Richer dry-run previews with full field-by-field comparisons and conflict detection for safer sync validation.', 'syncly' ),
	],
	[
		'icon'        => 'dashicons-groups',
		'title'       => __( 'Family Relationships', 'syncly' ),
		'description' => __( 'Parent-child account relationships where child users inherit membership access and GoHighLevel tags from a parent account.', 'syncly' ),
	],
	[
		'icon'        => 'dashicons-shield-alt',
		'title'       => __( 'Login Sync', 'syncly' ),
		'description' => __( 'Sync login activity to GoHighLevel custom fields, apply tags based on login behavior, track inactivity, and redirect users by tag.', 'syncly' ),
	],
	[
		'icon'        => 'dashicons-lock',
		'title'       => __( 'Advanced Content Restrictions', 'syncly' ),
		'description' => __( 'Extend the built-in restriction tools with advanced inheritance, reporting, and automation workflows.', 'syncly' ),
	],
	[
		'icon'        => 'dashicons-tag',
		'title'       => __( 'Global Tags', 'syncly' ),
		'description' => __( 'Apply location-wide tags to every synced contact without configuring each role separately.', 'syncly' ),
	],
	[
		'icon'        => 'dashicons-randomize',
		'title'       => __( 'Extended Field Mapping', 'syncly' ),
		'description' => __( 'Map WooCommerce, BuddyBoss, LearnDash, and custom user fields, plus AI-assisted auto-suggested mappings.', 'syncly' ),
	],
	[
		'icon'        => 'dashicons-chart-bar',
		'title'       => __( 'Sync Analytics Dashboard', 'syncly' ),
		'description' => __( 'Track sync volume, success rates, activity trends, and export performance reports.', 'syncly' ),
	],
	[
		'icon'        => 'dashicons-cart',
		'title'       => __( 'WooCommerce Add-on', 'syncly' ),
		'description' => __( 'Tag contacts on product purchase, track abandoned carts, sync opportunities, and convert customers automatically.', 'syncly' ),
	],
	[
		'icon'        => 'dashicons-welcome-learn-more',
		'title'       => __( 'LearnDash Add-on', 'syncly' ),
		'description' => __( 'Sync course enrollment, progress, and group membership with GoHighLevel tags.', 'syncly' ),
	],
	[
		'icon'        => 'dashicons-buddicons-buddypress-logo',
		'title'       => __( 'BuddyBoss Family Accounts', 'syncly' ),
		'description' => __( 'Auto-create and manage BuddyBoss groups for linked family accounts.', 'syncly' ),
	],
	[
		'icon'        => 'dashicons-database',
		'title'       => __( 'Custom Objects', 'syncly' ),
		'description' => __( 'Sync GoHighLevel custom objects with WordPress custom post types and WooCommerce products.', 'syncly' ),
	],
	[
		'icon'        => 'dashicons-art',
		'title'       => __( 'Elementor Conditions', 'syncly' ),
		'description' => __( 'Show or hide Elementor widgets and sections based on a visitor\'s GoHighLevel tags.', 'syncly' ),
	],
];
?>

<div class="ghl-settings-wrapper">
	<div class="ghl-settings-section ghl-settings-card" style="border: none; box-shadow: none; background: transparent; padding: 0;">

		<!-- Hero -->
		<div style="text-align: center; padding: 48px 32px 40px; background: linear-gradient(180deg, var(--ghl-primary-light, #f5f5ff) 0%, #fff 100%); border: 1px solid var(--ghl-border-primary, #e5e7eb); border-radius: var(--ghl-radius-lg, 12px) var(--ghl-radius-lg, 12px) 0 0;">
			<div style="display: inline-flex; align-items: center; justify-content: center; width: 56px; height: 56px; background: #fff; border: 1px solid var(--ghl-border-primary, #e5e7eb); border-radius: var(--ghl-radius-md, 8px); margin-bottom: 18px; box-shadow: var(--ghl-shadow-sm, 0 1px 3px rgba(0,0,0,0.08));">
				<span class="dashicons dashicons-star-filled" style="font-size: 26px; width: 26px; height: 26px; color: var(--ghl-primary, #635bff);"></span>
			</div>
			<h2 style="margin: 0 0 10px; font-size: 24px; font-weight: 600; color: var(--ghl-text-primary, #111827);">
				<?php esc_html_e( 'Upgrade to Syncly Pro', 'syncly' ); ?>
			</h2>
			<p class="description" style="max-width: 520px; margin: 0 auto 24px; font-size: 14px; color: var(--ghl-text-secondary, #6b7280); line-height: 1.6;">
				<?php esc_html_e( 'Syncly is fully functional on its own. Syncly Pro adds the features below for stores, membership sites, and teams that need more.', 'syncly' ); ?>
			</p>
			<a href="<?php echo esc_url( $upgrade_url ); ?>" class="ghl-button ghl-button-primary ghl-button-large" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'View Pricing', 'syncly' ); ?>
			</a>
		</div>

		<!-- Feature grid -->
		<div style="padding: 32px; background: #fff; border: 1px solid var(--ghl-border-primary, #e5e7eb); border-top: none; border-radius: 0 0 var(--ghl-radius-lg, 12px) var(--ghl-radius-lg, 12px);">
			<div class="ghl-pro-feature-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
				<?php foreach ( $pro_features as $feature ) : ?>
					<div class="ghl-pro-feature-card" style="padding: 18px; background: var(--ghl-bg-secondary, #f9fafb); border: 1px solid var(--ghl-border-primary, #e5e7eb); border-radius: var(--ghl-radius-md, 8px); transition: var(--ghl-transition-base, all 0.15s ease);">
						<div style="display: flex; align-items: flex-start; gap: 12px;">
							<div style="flex-shrink: 0; width: 32px; height: 32px; border-radius: var(--ghl-radius-base, 6px); background: var(--ghl-primary-light, #f5f5ff); display: flex; align-items: center; justify-content: center;">
								<span class="dashicons <?php echo esc_attr( $feature['icon'] ); ?>" style="color: var(--ghl-primary, #635bff); font-size: 16px; width: 16px; height: 16px;"></span>
							</div>
							<div>
								<strong style="display: block; font-size: 13px; color: var(--ghl-text-primary, #111827); margin-bottom: 4px;"><?php echo esc_html( $feature['title'] ); ?></strong>
								<p class="description" style="margin: 0; font-size: 12px; color: var(--ghl-text-secondary, #6b7280); line-height: 1.5;"><?php echo esc_html( $feature['description'] ); ?></p>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<div style="text-align: center; margin-top: 32px; padding-top: 28px; border-top: 1px solid var(--ghl-border-primary, #e5e7eb);">
				<a href="<?php echo esc_url( $upgrade_url ); ?>" class="ghl-button ghl-button-primary ghl-button-large" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'See Pricing & Upgrade', 'syncly' ); ?>
				</a>
			</div>
		</div>
	</div>
</div>

<?php ob_start(); ?>
.ghl-pro-feature-card:hover {
	border-color: var(--ghl-primary, #635bff);
	box-shadow: var(--ghl-shadow-sm, 0 1px 3px rgba(0,0,0,0.08));
}
<?php wp_add_inline_style( 'syncly-settings-css', ob_get_clean() ); ?>
