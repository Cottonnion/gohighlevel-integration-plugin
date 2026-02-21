<?php
/**
 * Template: PRO Upgrade Notice
 *
 * Reusable template for displaying PRO feature upgrade notices
 *
 * @package GHL_CRM_Integration
 * 
 * Available variables:
 * @var string $title Feature title
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
$title       = $title ?? __( 'GoHighLevel PRO', 'ghl-crm-integration' );
$description = $description ?? '';
$features    = $features ?? array();
$cta_text    = $cta_text ?? __( 'Explore PRO', 'ghl-crm-integration' );
$cta_url     = $cta_url ?? 'https://highlevelsync.com/ghl-crm-pro';
$style       = $style ?? 'box';

?>

<?php if ( 'banner' === $style ) : ?>
	<!-- Banner Style -->
	<div class="ghl-pro-upgrade-notice ghl-pro-upgrade-notice--banner" style="margin: 20px 0; padding: 22px 26px; background: #fff; border: 1px solid #e2e8f0; border-left: 4px solid #1f2937; box-shadow: 0 2px 10px rgba(0,0,0,0.06); border-radius: 6px;">
		<div style="display: flex; align-items: center; gap: 16px;">
			<div style="flex-shrink: 0;">
				<span class="dashicons dashicons-star-filled" style="font-size: 30px; width: 30px; height: 30px; color: #1f2937;"></span>
			</div>
			<div style="flex: 1;">
				<div style="text-transform: uppercase; letter-spacing: 0.08em; font-size: 11px; color: #475569; margin-bottom: 6px; font-weight: 600;">GoHighLevel CRM</div>
				<h3 style="margin: 0 0 8px 0; font-size: 17px; font-weight: 700; color: #0f172a;">
					<?php echo esc_html( $title ); ?>
				</h3>
				<?php if ( ! empty( $description ) ) : ?>
					<p style="margin: 0 0 10px 0; font-size: 14px; color: #475569; line-height: 1.6;">
						<?php echo esc_html( $description ); ?>
					</p>
				<?php endif; ?>
				
				<?php if ( ! empty( $features ) ) : ?>
					<ul style="margin: 12px 0; padding: 0; list-style: none; display: flex; flex-wrap: wrap; gap: 12px 14px;">
						<?php foreach ( $features as $feature ) : ?>
							<li style="font-size: 13px; color: #1f2937; display: inline-flex; align-items: center; gap: 8px; padding: 6px 10px; background: #f8fafc; border-radius: 4px; border: 1px solid #e2e8f0;">
								<span class="dashicons dashicons-yes-alt" style="color: #0f766e; font-size: 16px; width: 16px; height: 16px;"></span>
								<?php echo esc_html( $feature ); ?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
			<div style="flex-shrink: 0;">
				<a href="<?php echo esc_url( $cta_url ); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary" style="background: #1f2937; border-color: #1f2937; text-shadow: none; box-shadow: none; padding: 10px 18px; height: auto; font-size: 14px; font-weight: 600;">
					<?php echo esc_html( $cta_text ); ?>
				</a>
			</div>
		</div>
	</div>

<?php else : ?>
	<!-- Box Style (Default) -->
	<div class="ghl-pro-upgrade-notice ghl-pro-upgrade-notice--box" style="margin: 24px 0; padding: 32px; background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; box-shadow: 0 4px 14px rgba(0,0,0,0.05);">
		<div style="text-align: center; max-width: 760px; margin: 0 auto;">
			<div style="display: inline-flex; align-items: center; justify-content: center; width: 56px; height: 56px; background: #e2e8f0; border-radius: 10px; margin-bottom: 16px;">
				<span class="dashicons dashicons-star-filled" style="font-size: 26px; width: 26px; height: 26px; color: #1f2937;"></span>
			</div>

			<div style="text-transform: uppercase; letter-spacing: 0.08em; font-size: 11px; color: #475569; margin-bottom: 8px; font-weight: 600;">GoHighLevel CRM</div>
			<h3 style="margin: 0 0 10px 0; font-size: 21px; font-weight: 700; color: #0f172a;">
				<?php echo esc_html( $title ); ?>
			</h3>

			<?php if ( ! empty( $description ) ) : ?>
				<p style="margin: 0 0 20px 0; font-size: 15px; color: #1f2937; line-height: 1.6;">
					<?php echo esc_html( $description ); ?>
				</p>
			<?php endif; ?>

			<?php if ( ! empty( $features ) ) : ?>
				<ul style="margin: 22px 0; padding: 0; list-style: none; text-align: left; display: inline-block;">
					<?php foreach ( $features as $feature ) : ?>
						<li style="margin-bottom: 10px; font-size: 14px; color: #111827; display: flex; align-items: flex-start; gap: 10px; padding: 10px 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
							<span class="dashicons dashicons-yes-alt" style="color: #0f766e; font-size: 18px; width: 18px; height: 18px; flex-shrink: 0; margin-top: 2px;"></span>
							<span><?php echo esc_html( $feature ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<div style="margin-top: 22px;">
				<a href="<?php echo esc_url( $cta_url ); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary button-hero" style="background: #1f2937; border-color: #1f2937; text-shadow: none; box-shadow: none; padding: 12px 28px; height: auto; font-size: 15px; font-weight: 600;">
					<?php echo esc_html( $cta_text ); ?>
				</a>
			</div>
		</div>
	</div>
<?php endif; ?>