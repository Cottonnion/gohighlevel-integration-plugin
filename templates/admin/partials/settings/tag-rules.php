<?php
/**
 * Tag Rules Template
 *
 * A plain-English, site-wide index of every automation tied to a GoHighLevel
 * tag — who/what a tag applies to, and what happens on the site when a
 * contact has it — gathered from Role-Based Tags, Content Restrictions,
 * Elementor, Gutenberg, and Contact Form 7, plus (when Pro is active)
 * WooCommerce Memberships/Subscriptions/Abandoned Cart, LearnDash, Login
 * Sync, Conditional Menus, and Family Accounts.
 *
 * @package    Syncly
 * @subpackage Syncly/templates/admin/partials/settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tag_rules_provider = \Syncly\Core\TagRules\TagRulesProvider::get_instance();
$rules               = $tag_rules_provider->get_rules();
$safety_warnings     = $tag_rules_provider->get_safety_warnings();

// Group flat rule rows by tag name for display.
$grouped = [];
foreach ( $rules as $rule ) {
	$tag_name = $rule['tag'];
	if ( '' === $tag_name ) {
		continue;
	}
	$grouped[ $tag_name ][] = $rule;
}
ksort( $grouped, SORT_NATURAL | SORT_FLAG_CASE );

// Tags with a hazard rule (currently: restriction-bypass), for the group-header badge.
$hazard_tags = [];
foreach ( $rules as $rule ) {
	if ( ! empty( $rule['hazard'] ) && '' !== $rule['tag'] ) {
		$hazard_tags[ strtolower( $rule['tag'] ) ] = true;
	}
}
?>

<div class="ghl-settings-wrapper">
	<?php wp_nonce_field( 'syncly_settings_nonce', 'syncly_nonce' ); ?>

	<div class="ghl-settings-section ghl-settings-card">
		<div class="ghl-settings-header">
			<h2>
				<span class="dashicons dashicons-networking"></span>
				<?php esc_html_e( 'Tag Rules', 'syncly' ); ?>
			</h2>
			<p class="description">
				<?php
				esc_html_e(
					'Every automation configured anywhere on this site that is tied to a GoHighLevel tag, in one place. Role-Based Tags, page/post restrictions, Elementor and Gutenberg conditions, Contact Form 7 forms, and (with Pro) WooCommerce, LearnDash, Login Sync, Conditional Menus, and Family Accounts are normally configured on separate screens — this page answers "what happens when a contact has tag X?" without hunting through each of them.',
					'syncly'
				);
				?>
			</p>
		</div>

		<?php if ( ! empty( $safety_warnings ) ) : ?>
			<div class="syncly-tag-safety-warnings">
				<h4>
					<span class="dashicons dashicons-warning"></span>
					<?php esc_html_e( 'Safety Warnings', 'syncly' ); ?>
				</h4>
				<ul>
					<?php foreach ( $safety_warnings as $warning ) : ?>
						<li class="<?php echo 'high' === $warning['severity'] ? 'syncly-tag-safety-warning--high' : ''; ?>">
							<?php echo esc_html( $warning['message'] ); ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<hr>

		<?php if ( empty( $grouped ) ) : ?>
			<div class="syncly-tag-rules-empty">
				<span class="dashicons dashicons-info-outline"></span>
				<p>
					<?php
					esc_html_e(
						'No tag-based automations are configured yet. Set up tags under Role-Based Tags, on a page/post\'s restriction settings, or (with Pro) on a WooCommerce product/plan or LearnDash course/group, and they will show up here automatically.',
						'syncly'
					);
					?>
				</p>
			</div>
		<?php else : ?>

			<div class="syncly-tag-rules-toolbar">
				<input
					type="text"
					id="syncly-tag-rules-search"
					class="ghl-input syncly-tag-rules-search"
					placeholder="<?php esc_attr_e( 'Search by tag name or effect…', 'syncly' ); ?>"
					autocomplete="off"
				/>
				<span id="syncly-tag-rules-count" class="syncly-tag-rules-count"></span>
			</div>

			<div class="syncly-tag-rules-list" id="syncly-tag-rules-list">
				<?php foreach ( $grouped as $tag_name => $tag_rules ) : ?>
					<div class="syncly-tag-rules-group" data-tag="<?php echo esc_attr( strtolower( $tag_name ) ); ?>">
						<div class="syncly-tag-rules-group-header">
							<span class="dashicons dashicons-tag"></span>
							<strong><?php echo esc_html( $tag_name ); ?></strong>
							<span class="syncly-tag-rules-group-count">
								<?php
								printf(
									/* translators: %d: number of rules for this tag. */
									esc_html( _n( '%d rule', '%d rules', count( $tag_rules ), 'syncly' ) ),
									count( $tag_rules )
								);
								?>
							</span>
							<?php if ( isset( $hazard_tags[ strtolower( $tag_name ) ] ) ) : ?>
								<span class="syncly-tag-hazard-badge">
									<span class="dashicons dashicons-warning"></span>
									<?php esc_html_e( 'Bypasses restrictions', 'syncly' ); ?>
								</span>
							<?php endif; ?>
						</div>
						<table class="ghl-table syncly-tag-rules-table">
							<tbody>
								<?php foreach ( $tag_rules as $rule ) : ?>
									<?php
										$badge_class = \Syncly\Core\TagRules\TagRulesProvider::get_badge_class( $rule['source'] );
										$direction   = 'produces' === $rule['direction']
										? __( 'Contact gets this tag when…', 'syncly' )
										: __( 'When a contact has this tag…', 'syncly' );
										$row_class   = 'syncly-tag-rule-row' . ( ! empty( $rule['hazard'] ) ? ' syncly-tag-rule-row--hazard' : '' );
									?>
									<tr class="<?php echo esc_attr( $row_class ); ?>" data-search="<?php echo esc_attr( strtolower( $tag_name . ' ' . $rule['action'] . ' ' . $rule['source_label'] ) ); ?>">
										<td class="syncly-tag-rule-direction">
											<span class="syncly-tag-rule-direction-label"><?php echo esc_html( $direction ); ?></span>
										</td>
										<td>
											<?php echo esc_html( $rule['action'] ); ?>
											<div>
												<span class="ghl-field-badge <?php echo esc_attr( $badge_class ); ?>">
													<?php echo esc_html( $rule['source_label'] ); ?>
												</span>
											</div>
										</td>
										<td class="syncly-tag-rule-edit">
											<?php if ( ! empty( $rule['edit_url'] ) ) : ?>
												<a href="<?php echo esc_url( $rule['edit_url'] ); ?>" class="ghl-button ghl-button-secondary">
													<?php esc_html_e( 'Edit', 'syncly' ); ?>
												</a>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endforeach; ?>
			</div>

			<p class="syncly-tag-rules-no-results" id="syncly-tag-rules-no-results" style="display: none;">
				<?php esc_html_e( 'No rules match your search.', 'syncly' ); ?>
			</p>

		<?php endif; ?>
	</div>
</div>
