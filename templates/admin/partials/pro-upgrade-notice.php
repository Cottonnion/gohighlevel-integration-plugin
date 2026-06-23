<?php
/**
 * Template: PRO Upgrade Notice
 *
 * Reusable template for displaying PRO feature upgrade notices
 *
 * @package Syncly
 *
 * Available variables:
 * @var string $title Feature title
 * @var string $notice_title Feature title
 * @var string $description Feature description
 * @var array  $features List of features (optional)
 * @var string $cta_text Call to action button text (optional)
 * @var string $cta_url Call to action URL (optional)
 * @var string $style Display style: 'banner' or 'box' (default: 'box')
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Set defaults
$notice_title = $notice_title ?? __( 'Syncly Pro', 'syncly' );
$description = $description ?? '';
$features    = $features ?? array();
$cta_text    = $cta_text ?? __( 'Explore PRO', 'syncly' );
$cta_url     = $cta_url ?? 'https://highlevelsync.com/';
$style       = $style ?? 'box';

?>

<?php if ( 'banner' === $style ) : ?>
	<!-- Banner Style -->
	<div class="ghl-pro-upgrade-notice ghl-pro-upgrade-notice--banner" style="margin: 20px 0; padding: 24px; background: var(--ghl-bg-secondary, #f9fafb); border: 1px solid var(--ghl-border-primary, #e5e7eb); border-radius: var(--ghl-radius-lg, 12px);">
		<div style="display: flex; align-items: flex-start; gap: 16px; flex-wrap: wrap;">
			<div style="flex-shrink: 0; width: 44px; height: 44px; border-radius: var(--ghl-radius-md, 8px); background: var(--ghl-primary-light, #f5f5ff); display: flex; align-items: center; justify-content: center;">
				<span class="dashicons dashicons-star-filled" style="font-size: 20px; width: 20px; height: 20px; color: var(--ghl-primary, #635bff);"></span>
			</div>
			<div style="flex: 1; min-width: 220px;">
				<span style="display: inline-flex; align-items: center; padding: 2px 10px; border-radius: var(--ghl-radius-full, 9999px); background: var(--ghl-primary-light, #f5f5ff); color: var(--ghl-primary, #635bff); font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 8px;">Syncly Pro</span>
				<h3 style="margin: 0 0 6px 0; font-size: 16px; font-weight: 600; color: var(--ghl-text-primary, #111827);">
					<?php echo esc_html( $notice_title ); ?>
				</h3>
				<?php if ( ! empty( $description ) ) : ?>
					<p style="margin: 0; font-size: 13px; color: var(--ghl-text-secondary, #6b7280); line-height: 1.6;">
						<?php echo esc_html( $description ); ?>
					</p>
				<?php endif; ?>

				<?php if ( ! empty( $features ) ) : ?>
					<ul style="margin: 14px 0 0; padding: 0; list-style: none; display: flex; flex-wrap: wrap; gap: 8px;">
						<?php foreach ( $features as $feature ) : ?>
							<li style="font-size: 12px; font-weight: 500; color: var(--ghl-text-primary, #111827); display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; background: #fff; border-radius: var(--ghl-radius-full, 9999px); border: 1px solid var(--ghl-border-primary, #e5e7eb);">
								<span class="dashicons dashicons-yes-alt" style="color: var(--ghl-primary, #635bff); font-size: 14px; width: 14px; height: 14px;"></span>
								<?php echo esc_html( $feature ); ?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
			<div style="flex-shrink: 0; align-self: center;">
				<a href="<?php echo esc_url( $cta_url ); ?>" target="_blank" rel="noopener noreferrer" class="ghl-button ghl-button-primary">
					<?php echo esc_html( $cta_text ); ?>
				</a>
			</div>
		</div>
	</div>

<?php else : ?>
	<!-- Box Style (Default) -->
	<div class="ghl-pro-upgrade-notice ghl-pro-upgrade-notice--box" style="margin: 24px 0; padding: 36px 32px; background: #fff; border: 1px solid var(--ghl-border-primary, #e5e7eb); border-radius: var(--ghl-radius-lg, 12px); box-shadow: var(--ghl-shadow-sm, 0 1px 3px rgba(0,0,0,0.08));">
		<div style="text-align: center; max-width: 560px; margin: 0 auto;">
			<div style="display: inline-flex; align-items: center; justify-content: center; width: 52px; height: 52px; background: var(--ghl-primary-light, #f5f5ff); border-radius: var(--ghl-radius-md, 8px); margin-bottom: 18px;">
				<span class="dashicons dashicons-star-filled" style="font-size: 24px; width: 24px; height: 24px; color: var(--ghl-primary, #635bff);"></span>
			</div>

			<span style="display: inline-flex; align-items: center; padding: 3px 11px; border-radius: var(--ghl-radius-full, 9999px); background: var(--ghl-primary-light, #f5f5ff); color: var(--ghl-primary, #635bff); font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px;">Syncly Pro</span>

			<h3 style="margin: 0 0 10px 0; font-size: 20px; font-weight: 600; color: var(--ghl-text-primary, #111827);">
				<?php echo esc_html( $notice_title ); ?>
			</h3>

			<?php if ( ! empty( $description ) ) : ?>
				<p style="margin: 0 0 22px 0; font-size: 14px; color: var(--ghl-text-secondary, #6b7280); line-height: 1.6;">
					<?php echo esc_html( $description ); ?>
				</p>
			<?php endif; ?>

			<?php if ( ! empty( $features ) ) : ?>
				<ul style="margin: 0 0 26px; padding: 0; list-style: none; text-align: left; display: inline-block; width: 100%;">
					<?php foreach ( $features as $feature ) : ?>
						<li style="margin-bottom: 8px; font-size: 13px; color: var(--ghl-text-primary, #111827); display: flex; align-items: center; gap: 10px; padding: 10px 14px; background: var(--ghl-bg-secondary, #f9fafb); border: 1px solid var(--ghl-border-primary, #e5e7eb); border-radius: var(--ghl-radius-base, 6px);">
							<span class="dashicons dashicons-yes-alt" style="color: var(--ghl-primary, #635bff); font-size: 16px; width: 16px; height: 16px; flex-shrink: 0;"></span>
							<span><?php echo esc_html( $feature ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<a href="<?php echo esc_url( $cta_url ); ?>" target="_blank" rel="noopener noreferrer" class="ghl-button ghl-button-primary ghl-button-large">
				<?php echo esc_html( $cta_text ); ?>
			</a>
		</div>
	</div>
<?php endif; ?>
