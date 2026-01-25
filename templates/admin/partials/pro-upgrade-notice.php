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
$title       = $title ?? __( 'PRO Feature', 'ghl-crm-integration' );
$description = $description ?? '';
$features    = $features ?? array();
$cta_text    = $cta_text ?? __( 'Upgrade to PRO', 'ghl-crm-integration' );
$cta_url     = $cta_url ?? 'https://yahyadev.online/ghl-crm-pro';
$style       = $style ?? 'box';

?>

<?php if ( 'banner' === $style ) : ?>
	<!-- Banner Style -->
	<div class="ghl-pro-upgrade-notice ghl-pro-upgrade-notice--banner" style="margin: 20px 0; padding: 24px 28px; background: #fff; border-left: 4px solid #6366f1; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-radius: 4px;">
		<div style="display: flex; align-items: center; gap: 20px;">
			<div style="flex-shrink: 0;">
				<span class="dashicons dashicons-star-filled" style="font-size: 32px; width: 32px; height: 32px; color: #6366f1;"></span>
			</div>
			<div style="flex: 1;">
				<h3 style="margin: 0 0 8px 0; font-size: 16px; font-weight: 600; color: #1e293b;">
					<?php echo esc_html( $title ); ?>
				</h3>
				<?php if ( ! empty( $description ) ) : ?>
					<p style="margin: 0 0 12px 0; font-size: 14px; color: #64748b; line-height: 1.5;">
						<?php echo esc_html( $description ); ?>
					</p>
				<?php endif; ?>
				
				<?php if ( ! empty( $features ) ) : ?>
					<ul style="margin: 12px 0; padding: 0; list-style: none; display: flex; flex-wrap: wrap; gap: 16px;">
						<?php foreach ( $features as $feature ) : ?>
							<li style="font-size: 13px; color: #475569; display: flex; align-items: center; gap: 6px;">
								<span class="dashicons dashicons-yes-alt" style="color: #10b981; font-size: 16px; width: 16px; height: 16px;"></span>
								<?php echo esc_html( $feature ); ?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
			<div style="flex-shrink: 0;">
				<a href="<?php echo esc_url( $cta_url ); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary" style="background: #6366f1; border-color: #6366f1; text-shadow: none; box-shadow: none; padding: 8px 20px; height: auto; font-size: 14px;">
					<?php echo esc_html( $cta_text ); ?> →
				</a>
			</div>
		</div>
	</div>

<?php else : ?>
	<!-- Box Style (Default) -->
	<div class="ghl-pro-upgrade-notice ghl-pro-upgrade-notice--box" style="margin: 20px 0; padding: 32px; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
		<div style="text-align: center; max-width: 700px; margin: 0 auto;">
			<div style="display: inline-flex; align-items: center; justify-content: center; width: 56px; height: 56px; background: #eff6ff; border-radius: 50%; margin-bottom: 20px;">
				<span class="dashicons dashicons-star-filled" style="font-size: 28px; width: 28px; height: 28px; color: #6366f1;"></span>
			</div>
			
			<h3 style="margin: 0 0 12px 0; font-size: 20px; font-weight: 600; color: #1e293b;">
				<?php echo esc_html( $title ); ?>
			</h3>
			
			<?php if ( ! empty( $description ) ) : ?>
				<p style="margin: 0 0 24px 0; font-size: 15px; color: #64748b; line-height: 1.6;">
					<?php echo esc_html( $description ); ?>
				</p>
			<?php endif; ?>
			
			<?php if ( ! empty( $features ) ) : ?>
				<ul style="margin: 24px 0; padding: 0; list-style: none; text-align: left; display: inline-block;">
					<?php foreach ( $features as $feature ) : ?>
						<li style="margin-bottom: 12px; font-size: 14px; color: #475569; display: flex; align-items: flex-start; gap: 10px;">
							<span class="dashicons dashicons-yes-alt" style="color: #10b981; font-size: 18px; width: 18px; height: 18px; flex-shrink: 0; margin-top: 2px;"></span>
							<span><?php echo esc_html( $feature ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			
			<div style="margin-top: 28px;">
				<a href="<?php echo esc_url( $cta_url ); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary button-hero" style="background: #6366f1; border-color: #6366f1; text-shadow: none; box-shadow: 0 2px 4px rgba(99, 102, 241, 0.2); padding: 12px 32px; height: auto; font-size: 15px; font-weight: 500;">
					<?php echo esc_html( $cta_text ); ?> →
				</a>
			</div>
		</div>
	</div>
<?php endif; ?>