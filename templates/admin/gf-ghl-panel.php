<?php
/**
 * Gravity Forms GHL CRM Panel Template
 *
 * Rendered inside the Gravity Forms form-settings shell (GHL CRM tab).
 *
 * @package Syncly
 *
 * @var int   $form_id   GF form ID
 * @var array $config    Form configuration
 * @var array $gf_fields GF form fields (id, label, type)
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="syncly-gf-panel">
	<?php wp_nonce_field( 'syncly_gf_save', 'syncly_gf_nonce' ); ?>

	<!-- Enable Integration -->
	<div class="syncly-section">
		<h3><?php esc_html_e( 'GoHighLevel Integration', 'syncly' ); ?></h3>

		<div class="ghl-form-item">
			<div class="ghl-form-item-content">
				<label class="ghl-checkbox <?php echo $config['enabled'] ? 'is-checked' : ''; ?>">
					<input type="checkbox"
							class="ghl-checkbox-original"
							id="syncly_gf_enabled"
							name="syncly_gf_enabled"
							value="1"
							<?php checked( $config['enabled'], true ); ?>
							>
					<span class="ghl-checkbox-input <?php echo $config['enabled'] ? 'is-checked' : ''; ?>">
						<span class="ghl-checkbox-inner"></span>
					</span>
					<span class="ghl-checkbox-label">
						<?php esc_html_e( 'Send form submissions to GoHighLevel', 'syncly' ); ?>
					</span>
				</label>
			</div>
		</div>

		<p class="description">
			<?php esc_html_e( 'When enabled, submissions from this form will create or update contacts in your GoHighLevel account.', 'syncly' ); ?>
		</p>
	</div>

	<!-- Settings Container (visible when enabled) -->
	<div id="syncly_gf_settings_container" style="<?php echo $config['enabled'] ? '' : 'display:none;'; ?>">

		<!-- Field Mapping -->
		<div class="syncly-section">
			<h3><?php esc_html_e( 'Field Mapping', 'syncly' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Map your Gravity Forms fields to GoHighLevel contact fields. At minimum, map an email field.', 'syncly' ); ?>
			</p>

			<div id="syncly_gf_email_notice" class="syncly-status syncly-status-disconnected" style="display:none;">
				<span class="dashicons dashicons-warning"></span>
				<?php esc_html_e( 'Email mapping is required. Please map at least one Gravity Forms field to the "Email" GHL field — submissions without an email will be ignored.', 'syncly' ); ?>
			</div>

			<table class="syncly-field-mapping widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Gravity Forms Field', 'syncly' ); ?></th>
						<th><?php esc_html_e( 'GoHighLevel Field', 'syncly' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! empty( $gf_fields ) ) : ?>
						<?php foreach ( $gf_fields as $field ) : ?>
							<tr>
								<td>
									<strong><?php echo esc_html( $field['label'] ); ?></strong>
									<span class="field-type">(<?php echo esc_html( $field['type'] ); ?> #<?php echo esc_html( $field['id'] ); ?>)</span>
								</td>
								<td>
									<select name="syncly_gf_field_mapping[<?php echo esc_attr( $field['id'] ); ?>]"
											class="ghl-field-select"
											data-saved-value="<?php echo esc_attr( $config['field_mapping'][ $field['id'] ] ?? '' ); ?>">
										<option value=""><?php esc_html_e( '— Loading fields... —', 'syncly' ); ?></option>
									</select>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr>
							<td colspan="2">
								<em><?php esc_html_e( 'No mappable fields detected. Add form fields to this form first.', 'syncly' ); ?></em>
							</td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<!-- Tags -->
		<div class="syncly-section">
			<h3><?php esc_html_e( 'Contact Tags', 'syncly' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Select tags to apply to contacts created from this form.', 'syncly' ); ?>
			</p>

			<div class="ghl-form-item">
				<div class="ghl-form-item-content ghl-form-item-content--column">
					<select
						id="syncly_gf_tags"
						name="syncly_gf_tags[]"
						multiple
						class="ghl-tags-select"
						data-saved-tags='<?php echo esc_attr( wp_json_encode( $config['tags'] ) ); ?>'
						data-placeholder="<?php esc_attr_e( 'Select tags to apply on submission...', 'syncly' ); ?>">
						<option value=""><?php esc_html_e( 'Loading tags...', 'syncly' ); ?></option>
					</select>
				</div>
			</div>
		</div>

		<!-- Submission behavior -->
		<div class="syncly-section">
			<h3><?php esc_html_e( 'Submission Behavior', 'syncly' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Control how this form is identified and when a queued submission is sent.', 'syncly' ); ?>
			</p>

			<div class="syncly-gf-settings-grid">
				<div class="syncly-gf-setting">
					<label for="syncly_gf_source_name"><strong><?php esc_html_e( 'Contact source', 'syncly' ); ?></strong></label>
					<input type="text" id="syncly_gf_source_name" name="syncly_gf_source_name" class="regular-text" value="<?php echo esc_attr( $config['source_name'] ); ?>">
					<p class="description"><?php esc_html_e( 'Saved on the GHL contact. Use {form_title} to insert this form’s name.', 'syncly' ); ?></p>
				</div>

				<div class="syncly-gf-setting">
					<label for="syncly_gf_sync_delay"><strong><?php esc_html_e( 'Send delay', 'syncly' ); ?></strong></label>
					<select id="syncly_gf_sync_delay" name="syncly_gf_sync_delay">
						<?php foreach ( [ 0 => __( 'Send as soon as the queue runs', 'syncly' ), 5 => __( 'Wait 5 minutes', 'syncly' ), 15 => __( 'Wait 15 minutes', 'syncly' ), 30 => __( 'Wait 30 minutes', 'syncly' ), 60 => __( 'Wait 1 hour', 'syncly' ) ] as $minutes => $label ) : ?>
							<option value="<?php echo esc_attr( $minutes ); ?>" <?php selected( (int) $config['sync_delay'], $minutes ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Useful when you want to give a team time to review or enrich a submission before it reaches GHL.', 'syncly' ); ?></p>
				</div>
			</div>

			<label class="ghl-checkbox <?php echo $config['skip_spam'] ? 'is-checked' : ''; ?>">
				<input type="checkbox" class="ghl-checkbox-original" name="syncly_gf_skip_spam" value="1" <?php checked( $config['skip_spam'], true ); ?>>
				<span class="ghl-checkbox-input <?php echo $config['skip_spam'] ? 'is-checked' : ''; ?>"><span class="ghl-checkbox-inner"></span></span>
				<span class="ghl-checkbox-label"><strong><?php esc_html_e( 'Do not sync entries marked as spam', 'syncly' ); ?></strong></span>
			</label>
		</div>

		<!-- Contact timeline note -->
		<div class="syncly-section">
			<h3><?php esc_html_e( 'Contact Timeline Note', 'syncly' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Copy one form field into a note on the matching GHL contact after the contact sync completes.', 'syncly' ); ?>
			</p>
			<label class="ghl-checkbox <?php echo $config['note_enabled'] ? 'is-checked' : ''; ?>">
				<input type="checkbox" class="ghl-checkbox-original" name="syncly_gf_note_enabled" value="1" <?php checked( $config['note_enabled'], true ); ?>>
				<span class="ghl-checkbox-input <?php echo $config['note_enabled'] ? 'is-checked' : ''; ?>"><span class="ghl-checkbox-inner"></span></span>
				<span class="ghl-checkbox-label"><strong><?php esc_html_e( 'Add a note to the contact timeline', 'syncly' ); ?></strong></span>
			</label>
			<div class="syncly-gf-setting syncly-gf-note-field">
				<label for="syncly_gf_note_field"><?php esc_html_e( 'Note content field', 'syncly' ); ?></label>
				<select id="syncly_gf_note_field" name="syncly_gf_note_field">
					<option value=""><?php esc_html_e( '— Select a form field —', 'syncly' ); ?></option>
					<?php foreach ( $gf_fields as $field ) : ?>
						<option value="<?php echo esc_attr( $field['id'] ); ?>" <?php selected( $config['note_field'], $field['id'] ); ?>><?php echo esc_html( $field['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>

		<!-- Update Behavior -->
		<div class="syncly-section">
			<h3><?php esc_html_e( 'Update Behavior', 'syncly' ); ?></h3>

			<div class="ghl-form-item">
				<div class="ghl-form-item-content">
					<label class="ghl-checkbox <?php echo $config['update_exists'] ? 'is-checked' : ''; ?>">
						<input type="checkbox"
								class="ghl-checkbox-original"
								id="syncly_gf_update_exists"
								name="syncly_gf_update_exists"
								value="1"
								<?php checked( $config['update_exists'], true ); ?>
								>
						<span class="ghl-checkbox-input <?php echo $config['update_exists'] ? 'is-checked' : ''; ?>">
							<span class="ghl-checkbox-inner"></span>
						</span>
						<span class="ghl-checkbox-label">
							<?php esc_html_e( 'Update existing contacts if email already exists', 'syncly' ); ?>
						</span>
					</label>
				</div>
			</div>

			<p class="description">
				<?php esc_html_e( 'When enabled, if a contact with the same email exists, their information will be updated. When disabled, duplicate submissions will be ignored.', 'syncly' ); ?>
			</p>
		</div>

		<!-- Submission history -->
		<div class="syncly-section syncly-gf-history">
			<h3><?php esc_html_e( 'Recent Submission Syncs', 'syncly' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'See the latest queued submissions and resend a contact sync when needed.', 'syncly' ); ?>
			</p>
			<?php if ( ! empty( $history ) ) : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Entry', 'syncly' ); ?></th>
							<th><?php esc_html_e( 'Email', 'syncly' ); ?></th>
							<th><?php esc_html_e( 'Status', 'syncly' ); ?></th>
							<th><?php esc_html_e( 'Queued', 'syncly' ); ?></th>
							<th><?php esc_html_e( 'Action', 'syncly' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $history as $record ) : ?>
							<tr>
								<td>#<?php echo esc_html( $record['entry_id'] ); ?></td>
								<td><?php echo esc_html( $record['email'] ); ?></td>
								<td><span class="syncly-gf-status syncly-gf-status-<?php echo esc_attr( $record['status'] ); ?>"><?php echo esc_html( ucwords( str_replace( '_', ' ', $record['status'] ) ) ); ?></span></td>
								<td><?php echo esc_html( $record['created'] ); ?></td>
								<td>
									<?php if ( in_array( $record['status'], [ 'failed', 'completed' ], true ) && 'gf_submission' === $record['action'] ) : ?>
										<button type="submit" name="syncly_gf_resend" value="<?php echo esc_attr( $record['queue_id'] ); ?>" class="button button-secondary">
											<?php esc_html_e( 'Resend', 'syncly' ); ?>
										</button>
									<?php else : ?>
										<span class="description"><?php esc_html_e( 'Queued', 'syncly' ); ?></span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'No submissions have been queued for this form yet.', 'syncly' ); ?></p>
			<?php endif; ?>
			<?php wp_nonce_field( 'syncly_gf_resend', 'syncly_gf_resend_nonce' ); ?>
		</div>
	</div>

	<?php if ( ! \Syncly\Integrations\Forms\FormSettings::is_pro_active() ) : ?>
		<?php
		$notice_title = __( 'Turn Gravity Forms submissions into complete GHL automations', 'syncly' );
		$description  = __( 'Syncly Pro adds the routing and follow-up tools needed to turn a form submission into a complete sales process.', 'syncly' );
		$features     = [
			__( 'Multi-action submission workflows', 'syncly' ),
			__( 'Lead routing and round-robin assignment', 'syncly' ),
			__( 'Pipeline, stage, and opportunity value mapping', 'syncly' ),
			__( 'Conversation history and follow-up actions', 'syncly' ),
			__( 'Lead scoring and submission replay', 'syncly' ),
		];
		$cta_text = __( 'Explore Gravity Forms Pro Features', 'syncly' );
		$cta_url  = 'https://synclyforgohighlevel.com/shop';
		$style    = 'box';

		include SYNCLY_PATH . 'templates/admin/partials/pro-upgrade-notice.php';
		?>
	<?php endif; ?>
</div>
