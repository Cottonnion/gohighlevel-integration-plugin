<?php
/**
 * Gravity Forms GHL CRM Panel Template
 *
 * Free features only: enable the integration, map contact fields, and choose
 * whether existing contacts should be updated.
 *
 * @package Syncly
 *
 * @var array $config    Form configuration
 * @var array $gf_fields GF form fields (id, label, type)
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="syncly-gf-panel">
	<?php wp_nonce_field( 'syncly_gf_save', 'syncly_gf_nonce' ); ?>

	<div class="syncly-section">
		<h3><?php esc_html_e( 'GoHighLevel Integration', 'syncly' ); ?></h3>
		<div class="ghl-form-item">
			<div class="ghl-form-item-content">
				<label class="ghl-checkbox <?php echo $config['enabled'] ? 'is-checked' : ''; ?>">
					<input type="checkbox" class="ghl-checkbox-original" id="syncly_gf_enabled" name="syncly_gf_enabled" value="1" <?php checked( $config['enabled'], true ); ?>>
					<span class="ghl-checkbox-input <?php echo $config['enabled'] ? 'is-checked' : ''; ?>"><span class="ghl-checkbox-inner"></span></span>
					<span class="ghl-checkbox-label"><?php esc_html_e( 'Send form submissions to GoHighLevel', 'syncly' ); ?></span>
				</label>
			</div>
		</div>
		<p class="description"><?php esc_html_e( 'When enabled, submissions from this form will create or update contacts in your GoHighLevel account.', 'syncly' ); ?></p>
	</div>

	<div id="syncly_gf_settings_container" style="<?php echo $config['enabled'] ? '' : 'display:none;'; ?>">
		<div class="syncly-section">
			<h3><?php esc_html_e( 'Contact Field Mapping', 'syncly' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Map the form fields that should become contact details in GoHighLevel. An email mapping is required.', 'syncly' ); ?></p>
			<div id="syncly_gf_email_notice" class="syncly-status syncly-status-disconnected" style="display:none;">
				<span class="dashicons dashicons-warning"></span>
				<?php esc_html_e( 'Map one form field to the Email contact field before enabling submissions.', 'syncly' ); ?>
			</div>
			<table class="syncly-field-mapping widefat striped">
				<thead><tr><th><?php esc_html_e( 'Gravity Forms Field', 'syncly' ); ?></th><th><?php esc_html_e( 'GoHighLevel Contact Field', 'syncly' ); ?></th></tr></thead>
				<tbody>
					<?php if ( ! empty( $gf_fields ) ) : ?>
						<?php foreach ( $gf_fields as $field ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $field['label'] ); ?></strong> <span class="field-type">(<?php echo esc_html( $field['type'] ); ?> #<?php echo esc_html( $field['id'] ); ?>)</span></td>
								<td><select name="syncly_gf_field_mapping[<?php echo esc_attr( $field['id'] ); ?>]" class="ghl-select ghl-field-select" data-placeholder="<?php esc_attr_e( 'Select a GoHighLevel contact field…', 'syncly' ); ?>" data-saved-value="<?php echo esc_attr( $config['field_mapping'][ $field['id'] ] ?? '' ); ?>"><option value=""><?php esc_html_e( '— Loading fields... —', 'syncly' ); ?></option></select></td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr><td colspan="2"><em><?php esc_html_e( 'No mappable fields detected. Add form fields first.', 'syncly' ); ?></em></td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<div class="syncly-section">
			<h3><?php esc_html_e( 'Existing Contacts', 'syncly' ); ?></h3>
			<label class="ghl-checkbox <?php echo $config['update_exists'] ? 'is-checked' : ''; ?>">
				<input type="checkbox" class="ghl-checkbox-original" id="syncly_gf_update_exists" name="syncly_gf_update_exists" value="1" <?php checked( $config['update_exists'], true ); ?>>
				<span class="ghl-checkbox-input <?php echo $config['update_exists'] ? 'is-checked' : ''; ?>"><span class="ghl-checkbox-inner"></span></span>
				<span class="ghl-checkbox-label"><strong><?php esc_html_e( 'Update an existing contact when the email already exists', 'syncly' ); ?></strong></span>
			</label>
			<p class="description"><?php esc_html_e( 'Turn this off to skip duplicate submissions instead of updating the contact.', 'syncly' ); ?></p>
		</div>
	</div>

	<?php if ( ! \Syncly\Integrations\Forms\FormSettings::is_pro_active() ) : ?>
		<?php
		$notice_title = __( 'Unlock the Gravity Forms automation toolkit', 'syncly' );
		$description  = __( 'Go beyond basic contact mapping with routing, scoring, follow-up actions, and replayable submission automation.', 'syncly' );
		$features     = [
			__( 'Tags, notes, and delayed delivery', 'syncly' ),
			__( 'Conditional rules for form answers', 'syncly' ),
			__( 'Lead routing and opportunity mapping', 'syncly' ),
			__( 'Submission history and replay', 'syncly' ),
		];
		$cta_text = __( 'Explore Gravity Forms Pro Features', 'syncly' );
		$cta_url  = apply_filters( 'syncly_upgrade_url', admin_url( 'admin.php?page=syncly-admin&settings_tab=upgrade' ) );
		$style    = 'box';
		include SYNCLY_PATH . 'templates/admin/partials/pro-upgrade-notice.php';
		?>
	<?php endif; ?>
</div>
