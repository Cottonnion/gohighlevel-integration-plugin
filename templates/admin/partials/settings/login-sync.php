<?php
/**
 * Settings - Login Sync Template
 *
 * Login Sync section: custom field IDs, conditional tags, inactivity detection,
 * and tag-based login redirects. Pro-gated with the same pattern as rest-api.php.
 *
 * Expected in scope: $settings (array from SettingsManager::get_settings_array()).
 *
 * @package    GHL_CRM_Integration
 * @subpackage GHL_CRM_Integration/templates/admin/partials/settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$login_sync_active         = apply_filters( 'ghl_crm_login_sync_enabled', false );
$login_last_login_field_id = $settings['login_last_login_field_id'] ?? '';
$login_count_field_id      = $settings['login_count_field_id'] ?? '';

// Load GHL custom fields — same pattern as field-mapping.php.
// $settings_manager is always in scope (set in settings.php before including this partial).
$ghl_custom_fields = [];
try {
	$_fields_data = isset( $settings_manager )
		? $settings_manager->get_ghl_fields_cached()
		: \GHL_CRM\Core\Settings\MetadataService::get_instance()->get_ghl_fields_cached();
	foreach ( $_fields_data['fields'] ?? [] as $_key => $_label ) {
		if ( strpos( $_key, 'custom.' ) === 0 ) {
			// Strip 'custom.' prefix → raw GHL field ID, and remove the ' (Custom)' display suffix.
			$ghl_custom_fields[ substr( $_key, 7 ) ] = str_replace( ' (Custom)', '', $_label );
		}
	}
} catch ( \Throwable $_ ) {
	// Silently ignore — dropdowns will be empty on API failure.
}

// Load GHL tags server-side (cached transient, no API call when warm).
$ghl_tags = [];
try {
	$ghl_tags = \GHL_CRM\Sync\TagManager::get_instance()->get_tags();
} catch ( \Throwable $_ ) {
	// Silently ignore — selects will be empty.
}

$login_first_login_tags = $settings['login_first_login_tags'] ?? [];
$login_every_login_tags = $settings['login_every_login_tags'] ?? [];
$login_milestone_tags   = $settings['login_milestone_tags'] ?? [];
$login_inactivity_days  = (int) ( $settings['login_inactivity_days'] ?? 0 );
$login_inactivity_tags  = $settings['login_inactivity_tags'] ?? [];
$login_active_tags      = $settings['login_active_tags'] ?? [];
$login_tag_redirects    = $settings['login_tag_redirects'] ?? [];

// Build a list of all public pages / CPT entries for the redirect URL picker.
$redirect_url_options = [];
$public_post_types    = get_post_types( [ 'public' => true ], 'objects' );
foreach ( $public_post_types as $pt ) {
	// Skip attachments — not useful as redirect targets.
	if ( 'attachment' === $pt->name ) {
		continue;
	}

	$redirect_posts = get_posts(
		[
			'post_type'      => $pt->name,
			'post_status'    => 'publish',
			'posts_per_page' => 100,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		]
	);

	foreach ( $redirect_posts as $p ) {
		$redirect_url_options[] = [
			'url'   => get_permalink( $p ),
			'title' => $p->post_title,
			'type'  => $pt->labels->singular_name,
		];
	}
}
?>

<div class="ghl-settings-wrapper">
<?php wp_nonce_field( 'ghl_crm_settings_nonce', 'ghl_crm_nonce' ); ?>

<?php if ( ! $login_sync_active ) : ?>
	<div class="ghl-settings-section ghl-settings-card">
		<div style="display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 20px;">
			<div>
				<span style="display: inline-flex; padding: 3px 9px; border-radius: 999px; background: #eef2ff; border: 1px solid #c7d2fe; color: #3730a3; font-size: 11px; font-weight: 700; text-transform: uppercase;"><?php esc_html_e( 'Syncly Pro', 'syncly' ); ?></span>
				<h2 style="margin: 8px 0 6px; color: #1e293b;"><span class="dashicons dashicons-shield-alt"></span> <?php esc_html_e( 'Login Sync', 'syncly' ); ?></h2>
				<p class="description" style="max-width: 680px;"><?php esc_html_e( 'Sync login activity to GoHighLevel custom fields, apply tags based on login behavior, track inactivity, and redirect users based on their GoHighLevel tags.', 'syncly' ); ?></p>
			</div>
			<a href="<?php echo esc_url( apply_filters( 'ghl_crm_upgrade_url', 'https://highlevelsync.com/' ) ); ?>" class="ghl-button ghl-button-primary" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Learn More', 'syncly' ); ?></a>
		</div>

		<div aria-hidden="true" style="display: grid; gap: 16px; opacity: 0.88;">
			<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px;">
				<div style="padding: 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;"><strong><?php esc_html_e( 'Last Login Field', 'syncly' ); ?></strong><div style="margin-top: 8px; padding: 9px 10px; background: #fff; border: 1px solid #d1d5db; border-radius: 6px; color: #475569;">Last Login Date</div></div>
				<div style="padding: 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;"><strong><?php esc_html_e( 'Login Count Field', 'syncly' ); ?></strong><div style="margin-top: 8px; padding: 9px 10px; background: #fff; border: 1px solid #d1d5db; border-radius: 6px; color: #475569;">Login Count</div></div>
			</div>
			<div style="padding: 16px; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px;">
				<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 10px;">
					<span style="padding: 10px 12px; background: #eef2ff; border-radius: 6px; color: #3730a3; font-weight: 600;"><?php esc_html_e( 'First login tag', 'syncly' ); ?></span>
					<span style="padding: 10px 12px; background: #ecfdf5; border-radius: 6px; color: #065f46; font-weight: 600;"><?php esc_html_e( 'Milestone tags', 'syncly' ); ?></span>
					<span style="padding: 10px 12px; background: #fef3c7; border-radius: 6px; color: #92400e; font-weight: 600;"><?php esc_html_e( 'Inactivity tags', 'syncly' ); ?></span>
					<span style="padding: 10px 12px; background: #f1f5f9; border-radius: 6px; color: #334155; font-weight: 600;"><?php esc_html_e( 'Tag-based redirects', 'syncly' ); ?></span>
				</div>
			</div>
		</div>
	</div>
</div>
	<?php return; ?>
<?php endif; ?>

<!-- Login Sync Section -->
<div class="ghl-settings-section ghl-settings-card">

	<div class="ghl-settings-header">
		<h2>
			<span class="dashicons dashicons-shield-alt"></span>
			<?php esc_html_e( 'Login Sync', 'syncly' ); ?>
		</h2>
		<p class="description">
			<?php esc_html_e( 'Sync last login date and login count to GHL custom fields, apply tags on specific login conditions, set inactivity tags, and redirect users after login based on their GHL tags.', 'syncly' ); ?>
		</p>
	</div>

	<hr>

	<div class="ghl-form-builder">

			<!-- ── Custom Field IDs ──────────────────────────── -->
			<div class="ghl-form-item">
				<div class="ghl-form-item-content ghl-form-item-content--column">
					<label class="ghl-form-label">
						<?php esc_html_e( 'GHL Custom Fields', 'syncly' ); ?>
						<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Select which GHL custom fields should receive the last login date and login count values. Custom fields are loaded from your connected GHL location. Leave blank to skip syncing that value.', 'syncly' ); ?>">?</span>
					</label>
					<div style="display:flex;gap:16px;flex-wrap:wrap;">
						<div style="flex:1;min-width:220px;">
							<label for="login_last_login_field_id" style="font-size:12px;color:#666;display:block;margin-bottom:4px;">
								<?php esc_html_e( 'Last Login Field', 'syncly' ); ?>
							</label>
							<select id="login_last_login_field_id" name="login_last_login_field_id"
								class="ghl-input ghl-input--wide ghl-custom-field-select"
								data-placeholder="<?php esc_attr_e( 'Select custom field…', 'syncly' ); ?>"
								style="width:100%;">
								<option value="">— <?php esc_html_e( 'Select custom field', 'syncly' ); ?> —</option>
								<?php foreach ( $ghl_custom_fields as $ghl_fid => $ghl_flabel ) : ?>
									<option value="<?php echo esc_attr( $ghl_fid ); ?>" <?php selected( $login_last_login_field_id, $ghl_fid ); ?>>
										<?php echo esc_html( $ghl_flabel ); ?>
									</option>
								<?php endforeach; ?>
								<?php if ( ! empty( $login_last_login_field_id ) && ! isset( $ghl_custom_fields[ $login_last_login_field_id ] ) ) : ?>
									<option value="<?php echo esc_attr( $login_last_login_field_id ); ?>" selected>
										<?php echo esc_html( $login_last_login_field_id ); ?>
									</option>
								<?php endif; ?>
							</select>
						</div>
						<div style="flex:1;min-width:220px;">
							<label for="login_count_field_id" style="font-size:12px;color:#666;display:block;margin-bottom:4px;">
								<?php esc_html_e( 'Login Count Field', 'syncly' ); ?>
							</label>
							<select id="login_count_field_id" name="login_count_field_id"
								class="ghl-input ghl-input--wide ghl-custom-field-select"
								data-placeholder="<?php esc_attr_e( 'Select custom field…', 'syncly' ); ?>"
								style="width:100%;">
								<option value="">— <?php esc_html_e( 'Select custom field', 'syncly' ); ?> —</option>
								<?php foreach ( $ghl_custom_fields as $ghl_fid => $ghl_flabel ) : ?>
									<option value="<?php echo esc_attr( $ghl_fid ); ?>" <?php selected( $login_count_field_id, $ghl_fid ); ?>>
										<?php echo esc_html( $ghl_flabel ); ?>
									</option>
								<?php endforeach; ?>
								<?php if ( ! empty( $login_count_field_id ) && ! isset( $ghl_custom_fields[ $login_count_field_id ] ) ) : ?>
									<option value="<?php echo esc_attr( $login_count_field_id ); ?>" selected>
										<?php echo esc_html( $login_count_field_id ); ?>
									</option>
								<?php endif; ?>
							</select>
						</div>
					</div>
					<p class="description ghl-form-description">
						<?php
						if ( empty( $ghl_custom_fields ) ) {
							esc_html_e( 'No custom fields found — make sure your GHL connection is active and custom fields are created in GHL › Settings › Custom Fields.', 'syncly' );
						} else {
							esc_html_e( 'Select the GHL custom field to receive each value. Synced on every login (once per hour per user).', 'syncly' );
						}
						?>
					</p>
				</div>
			</div>

			<hr style="margin:20px 0;">

			<!-- ── First Login Tags ─────────────────────────── -->
			<div class="ghl-form-item">
				<div class="ghl-form-item-content ghl-form-item-content--column">
					<label for="login_first_login_tags" class="ghl-form-label">
						<?php esc_html_e( 'First Login Tags', 'syncly' ); ?>
						<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'These tags are applied to the GHL contact only on the very first login (login count = 1).', 'syncly' ); ?>">?</span>
					</label>
					<select id="login_first_login_tags" name="login_first_login_tags[]" multiple
						class="ghl-tags-select"
						data-saved-tags='<?php echo esc_attr( wp_json_encode( $login_first_login_tags ) ); ?>'
						data-placeholder="<?php esc_attr_e( 'Select tags to apply on first login...', 'syncly' ); ?>">
						<option value=""><?php esc_html_e( 'Loading tags...', 'syncly' ); ?></option>
					</select>
				</div>
			</div>

			<!-- ── Every Login Tags ─────────────────────────── -->
			<div class="ghl-form-item">
				<div class="ghl-form-item-content ghl-form-item-content--column">
					<label for="login_every_login_tags" class="ghl-form-label">
						<?php esc_html_e( 'Every Login Tags', 'syncly' ); ?>
						<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'These tags are applied to the GHL contact on every login.', 'syncly' ); ?>">?</span>
					</label>
					<select id="login_every_login_tags" name="login_every_login_tags[]" multiple
						class="ghl-tags-select"
						data-saved-tags='<?php echo esc_attr( wp_json_encode( $login_every_login_tags ) ); ?>'
						data-placeholder="<?php esc_attr_e( 'Select tags to apply on every login...', 'syncly' ); ?>">
						<option value=""><?php esc_html_e( 'Loading tags...', 'syncly' ); ?></option>
					</select>
				</div>
			</div>

			<hr style="margin:20px 0;">

			<!-- ── Inactivity ────────────────────────────────── -->
			<div class="ghl-form-item">
				<div class="ghl-form-item-content ghl-form-item-content--column">
					<label for="login_inactivity_days" class="ghl-form-label">
						<?php esc_html_e( 'Inactivity Threshold (days)', 'syncly' ); ?>
						<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Users who have not logged in for this many days are considered inactive. Set to 0 to disable inactivity detection. The check runs once per day via Action Scheduler.', 'syncly' ); ?>">?</span>
					</label>
					<input type="number" id="login_inactivity_days" name="login_inactivity_days"
						class="ghl-input" min="0" style="width:120px;"
						value="<?php echo esc_attr( (string) $login_inactivity_days ); ?>"
						placeholder="e.g. 30">
					<p class="description ghl-form-description"><?php esc_html_e( 'Set to 0 to disable. Runs daily via Action Scheduler — no page-load loops.', 'syncly' ); ?></p>
				</div>
			</div>

			<div class="ghl-form-item">
				<div class="ghl-form-item-content ghl-form-item-content--column">
					<label for="login_inactivity_tags" class="ghl-form-label">
						<?php esc_html_e( 'Inactivity Tags', 'syncly' ); ?>
						<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Tags applied when a user exceeds the inactivity threshold. Removed when they log back in.', 'syncly' ); ?>">?</span>
					</label>
					<select id="login_inactivity_tags" name="login_inactivity_tags[]" multiple
						class="ghl-tags-select"
						data-saved-tags='<?php echo esc_attr( wp_json_encode( $login_inactivity_tags ) ); ?>'
						data-placeholder="<?php esc_attr_e( 'Select tags for inactive users...', 'syncly' ); ?>">
						<option value=""><?php esc_html_e( 'Loading tags...', 'syncly' ); ?></option>
					</select>
				</div>
			</div>

			<hr style="margin:20px 0;">

			<!-- ── Tag-Based Login Redirects ─────────────────── -->
			<div class="ghl-form-item">
				<div class="ghl-form-item-content ghl-form-item-content--column">
					<label class="ghl-form-label">
						<?php esc_html_e( 'Tag-Based Login Redirects', 'syncly' ); ?>
						<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'After login, the first matching tag rule redirects the user to the configured URL. Rules are checked top-to-bottom. No extra API call — uses cached tag data.', 'syncly' ); ?>">?</span>
					</label>
					<div id="ghl-login-redirects">
						<?php
						foreach ( $login_tag_redirects as $idx => $rule ) :
							$tag_id = (string) ( $rule['tag_id'] ?? '' );
							$url    = (string) ( $rule['url'] ?? '' );
							?>
							<div class="ghl-login-redirect-row" style="display:flex;gap:10px;align-items:flex-start;margin-bottom:8px;">
								<div style="flex:1;">
									<label style="font-size:12px;color:#666;display:block;margin-bottom:3px;"><?php esc_html_e( 'If user has tag', 'syncly' ); ?></label>
									<select name="login_tag_redirects[<?php echo esc_attr( (string) $idx ); ?>][tag_id]" class="ghl-tags-select" style="width:100%;"
										data-saved-tags='<?php echo esc_attr( wp_json_encode( array( $tag_id ) ) ); ?>'
										data-placeholder="<?php esc_attr_e( 'Select tag...', 'syncly' ); ?>">
										<option value=""><?php esc_html_e( 'Loading tags...', 'syncly' ); ?></option>
									</select>
								</div>
								<div style="flex:1;">
									<label style="font-size:12px;color:#666;display:block;margin-bottom:3px;"><?php esc_html_e( 'Redirect to URL', 'syncly' ); ?></label>
									<input type="url" name="login_tag_redirects[<?php echo esc_attr( (string) $idx ); ?>][url]" value="<?php echo esc_attr( $url ); ?>" class="ghl-input ghl-input--wide" placeholder="https://example.com/dashboard">
								</div>
								<div style="padding-top:22px;">
									<button type="button" class="ghl-login-redirect-remove" style="background:none;border:1px solid #e03e2d;color:#e03e2d;border-radius:4px;padding:4px 8px;cursor:pointer;">✕</button>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
					<button type="button" id="ghl-add-redirect" class="ghl-button ghl-button-secondary" style="margin-top:6px;font-size:13px;">
						+ <?php esc_html_e( 'Add Redirect Rule', 'syncly' ); ?>
					</button>
					<p class="description ghl-form-description"><?php esc_html_e( 'Rules are evaluated top-to-bottom. The first matching tag wins.', 'syncly' ); ?></p>
				</div>
			</div>

	</div><!-- /.ghl-form-builder -->

	<div class="ghl-form-item" style="margin-top: 24px;">
		<button type="button" id="save-login-sync-settings" class="ghl-button ghl-button-primary ghl-save-settings-btn">
			<span class="ghl-button-text"><?php esc_html_e( 'Save Login Sync Settings', 'syncly' ); ?></span>
		</button>
	</div>

</div><!-- /.ghl-settings-section Login Sync -->

<?php if ( ! $login_sync_active ) : ?>
	</div><!-- /opacity wrapper -->
</div><!-- /pointer-events wrapper -->
<?php endif; ?>

</div><!-- /.ghl-settings-wrapper -->

<?php if ( $login_sync_active ) : ?>
<?php ob_start(); ?>
(function(){
	'use strict';
	var redirectIdx  = <?php echo esc_js( (string) count( $login_tag_redirects ) ); ?>;

	// All public pages / CPT entries for the redirect URL select2.
	var ghlRedirectPages = <?php echo wp_json_encode( $redirect_url_options ); ?>;

	// Tags pre-loaded server-side — no AJAX needed.
	// Get tags from the same localized data that settings.js uses.
	// Falls back to server-side tags if ghl_crm_settings_js_data isn't available.
	var ghlLoginTags = (typeof ghl_crm_settings_js_data !== 'undefined' && ghl_crm_settings_js_data.tags)
		? ghl_crm_settings_js_data.tags
		: 
		<?php
		echo wp_json_encode(
			array_map(
				static function ( array $t ): array {
					return [
						'id'   => (string) ( $t['id'] ?? '' ),
						'name' => (string) ( $t['name'] ?? '' ),
					];
				},
				$ghl_tags
			)
		);
		?>
		;

	/**
	 * Build an <option> string list from ghlLoginTags using tag NAMES as values
	 * (matching the pattern used by general.php / settings.js).
	 * @param {string[]} [savedTags] Array of saved tag names to pre-select.
	 */
	function buildTagOptions( savedTags ) {
		savedTags = savedTags || [];
		var html = '';
		ghlLoginTags.forEach(function(t){
			var tagName = String(t.name || t.id || '');
			if (!tagName) return;
			var sel = savedTags.indexOf(tagName) !== -1;
			html += '<option value="' + tagName.replace(/"/g,'&quot;') + '"' + (sel ? ' selected' : '') + '>'
					+ tagName.replace(/</g,'&lt;').replace(/>/g,'&gt;')
					+ '</option>';
		});
		// Add any saved tags that aren't in the API list (custom/typed tags)
		savedTags.forEach(function(tag){
			if (tag && html.indexOf('value="' + tag.replace(/"/g,'&quot;') + '"') === -1) {
				html += '<option value="' + tag.replace(/"/g,'&quot;') + '" selected>'
						+ tag.replace(/</g,'&lt;').replace(/>/g,'&gt;')
						+ '</option>';
			}
		});
		return html;
	}

	/**
	 * Populate a tag <select> from ghlLoginTags and init select2.
	 * Reads data-saved-tags to pre-select saved values.
	 */
	function initTagSelect( select ) {
		var $select = jQuery(select);
		var savedTags = $select.data('saved-tags') || [];
		if (typeof savedTags === 'string') {
			try { savedTags = JSON.parse(savedTags); } catch(e) { savedTags = []; }
		}

		// Populate options using tag names
		$select.empty();
		$select.html( buildTagOptions( savedTags ) );

		// Pre-select saved values
		if (savedTags.length > 0) {
			$select.val(savedTags);
		}

		if ( jQuery.fn.select2 ) {
			$select.select2({
				tags          : true,
				tokenSeparators: [','],
				placeholder   : select.getAttribute('data-placeholder') || 'Select tags...',
				allowClear    : true,
				width         : '100%',
				closeOnSelect : false,
				scrollAfterSelect: false
			});
		}
	}

	/**
	 * Build <option> HTML for redirect URL selects.
	 * @param {string} [savedUrl] The currently saved URL to pre-select.
	 */
	function buildUrlOptions( savedUrl ) {
		savedUrl = savedUrl || '';
		var html = '';
		var foundSaved = false;

		ghlRedirectPages.forEach(function(p){
			var sel = (savedUrl && savedUrl === p.url);
			if (sel) foundSaved = true;
			html += '<option value="' + p.url.replace(/"/g,'&quot;') + '"' + (sel ? ' selected' : '') + '>'
					+ p.title.replace(/</g,'&lt;').replace(/>/g,'&gt;')
					+ ' (' + p.type.replace(/</g,'&lt;').replace(/>/g,'&gt;') + ')'
					+ '</option>';
		});

		// If saved URL is a custom value not in any CPT, add it as selected.
		if (savedUrl && !foundSaved) {
			html = '<option value="' + savedUrl.replace(/"/g,'&quot;') + '" selected>'
					+ savedUrl.replace(/</g,'&lt;').replace(/>/g,'&gt;')
					+ '</option>' + html;
		}

		return html;
	}

	/**
	 * Initialise a redirect URL select with select2 (tags: true for custom URLs).
	 */
	function initUrlSelect( select ) {
		var $select  = jQuery(select);
		var savedUrl = $select.data('saved-url') || '';

		$select.empty();
		$select.html( buildUrlOptions( savedUrl ) );

		if (savedUrl) {
			$select.val(savedUrl);
		}

		if ( jQuery.fn.select2 ) {
			$select.select2({
				tags            : true,
				placeholder     : select.getAttribute('data-placeholder') || 'Select page or type a URL...',
				allowClear      : true,
				width           : '100%',
				createTag       : function(params) {
					var term = jQuery.trim(params.term);
					if (term === '') return null;
					return { id: term, text: term + ' (custom URL)' };
				}
			});
		}
	}

	// Init custom-field selects (options already rendered server-side).
	document.querySelectorAll('.ghl-custom-field-select').forEach(function(sel){
		if (window.jQuery && jQuery.fn.select2) {
			jQuery(sel).select2({
				placeholder : sel.getAttribute('data-placeholder') || 'Select custom field…',
				allowClear  : true,
				width       : '100%'
			});
		}
	});

	// Init all tag selects on this tab.
	document.querySelectorAll(
		'#login_first_login_tags, #login_every_login_tags, #login_inactivity_tags, #login_active_tags,'
		+ ' #ghl-login-redirects .ghl-tags-select'
	).forEach(initTagSelect);

	// Init all redirect URL selects on this tab.
	document.querySelectorAll('#ghl-login-redirects .ghl-redirect-url-select').forEach(initUrlSelect);

	// Add Redirect row.
	document.getElementById('ghl-add-redirect').addEventListener('click', function(){
		var idx = redirectIdx++;
		var row = document.createElement('div');
		row.className = 'ghl-login-redirect-row';
		row.style.cssText = 'display:flex;gap:10px;align-items:flex-start;margin-bottom:8px;';
		row.innerHTML = '<div style="flex:1;"><label style="font-size:12px;color:#666;display:block;margin-bottom:3px;">If user has tag</label>'
			+ '<select name="login_tag_redirects['+idx+'][tag_id]" class="ghl-tags-select" style="width:100%;" data-placeholder="Select tag...">'
			+ buildTagOptions( [] )
			+ '</select></div>'
			+ '<div style="flex:1;"><label style="font-size:12px;color:#666;display:block;margin-bottom:3px;">Redirect to URL</label>'
			+ '<select name="login_tag_redirects['+idx+'][url]" class="ghl-redirect-url-select" style="width:100%;" data-placeholder="Select page or type a URL..." data-saved-url="">'
			+ buildUrlOptions( '' )
			+ '</select></div>'
			+ '<div style="padding-top:22px;"><button type="button" class="ghl-login-redirect-remove" style="background:none;border:1px solid #e03e2d;color:#e03e2d;border-radius:4px;padding:4px 8px;cursor:pointer;">✕</button></div>';
		document.getElementById('ghl-login-redirects').appendChild(row);
		initTagSelect( row.querySelector('.ghl-tags-select') );
		initUrlSelect( row.querySelector('.ghl-redirect-url-select') );
		row.querySelector('.ghl-login-redirect-remove').addEventListener('click', function(){ row.remove(); });
	});

	// Remove existing redirect rows.
	document.querySelectorAll('.ghl-login-redirect-remove').forEach(function(btn){
		btn.addEventListener('click', function(){ btn.closest('.ghl-login-redirect-row').remove(); });
	});
})();
<?php wp_add_inline_script( 'ghl-crm-settings-js', ob_get_clean() ); ?>
<?php endif; ?>
