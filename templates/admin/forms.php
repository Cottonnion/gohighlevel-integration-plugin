<?php
/**
 * Forms Management Template
 *
 * @package GHL_CRM_Integration
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="ghl-forms-container">
	<div class="ghl-page-header">
	</div>

	<!-- Connection Check -->
	<?php
	$settings_manager = \GHL_CRM\Core\SettingsManager::get_instance();
	$settings         = $settings_manager->get_settings_array();
	$oauth_handler    = new \GHL_CRM\API\OAuth\OAuthHandler();
	$oauth_status     = $oauth_handler->get_connection_status();
	$is_connected     = $oauth_status['connected'] || ! empty( $settings['api_token'] );
	
	if ( ! $is_connected ) :
		?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'Not Connected', 'ghl-crm-integration' ); ?></strong><br>
				<?php
				printf(
					/* translators: %s: Link to dashboard page */
					esc_html__( 'Please connect to GoHighLevel in %s first.', 'ghl-crm-integration' ),
					sprintf(
						'<a href="%s">%s</a>',
						esc_url( admin_url( 'admin.php?page=ghl-crm-admin' ) ),
						esc_html__( 'Dashboard', 'ghl-crm-integration' )
					)
				);
				?>
			</p>
		</div>
		<?php
		return;
	endif;

	// Check scope access for Forms
	\GHL_CRM\Core\ScopeChecker::render_scope_notice( 'forms' );
	?>

	<p class="description">
		<?php esc_html_e( 'Manage and embed GoHighLevel forms in your WordPress site.', 'ghl-crm-integration' ); ?>
	</p>

	<!-- Loading State -->
	<div id="ghl-forms-loading" class="ghl-loading-state" style="display: none;">
		<div class="spinner is-active"></div>
		<p><?php esc_html_e( 'Loading forms...', 'ghl-crm-integration' ); ?></p>
	</div>

	<!-- Error State -->
	<div id="ghl-forms-error" class="notice notice-error" style="display: none;">
		<p><strong><?php esc_html_e( 'Error:', 'ghl-crm-integration' ); ?></strong> <span id="ghl-forms-error-message"></span></p>
	</div>

	<!-- Refresh Button -->
	<div class="ghl-forms-toolbar">
		<button type="button" id="ghl-refresh-forms" class="button button-secondary">
			<span class="dashicons dashicons-update"></span>
			<?php esc_html_e( 'Refresh Forms', 'ghl-crm-integration' ); ?>
		</button>
	</div>

	<!-- Forms List -->
	<div id="ghl-forms-list" class="ghl-forms-list">
		<!-- Forms will be loaded here via AJAX -->
	</div>
</div>

<style>
.ghl-forms-container {
	max-width: 1200px;
	margin: 20px 0;
}

.ghl-page-header {
	margin-bottom: 20px;
}

.ghl-page-header h1 {
	margin-bottom: 10px;
}

.ghl-loading-state {
	text-align: center;
	padding: 40px;
}

.ghl-loading-state .spinner {
	float: none;
	margin: 0 auto 10px;
}

.ghl-forms-toolbar {
	margin-bottom: 20px;
}

.ghl-forms-list {
	background: #fff;
	border: 1px solid #ccd0d4;
	box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.ghl-form-item {
	padding: 20px;
	border-bottom: 1px solid #e0e0e0;
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.ghl-form-item:last-child {
	border-bottom: none;
}

.ghl-form-info {
	flex: 1;
}

.ghl-form-name {
	font-size: 16px;
	font-weight: 600;
	margin-bottom: 5px;
}

.ghl-form-meta {
	color: #666;
	font-size: 13px;
}

.ghl-form-actions {
	display: flex;
	gap: 10px;
}

.ghl-shortcode-box {
	background: #f0f0f1;
	border: 1px solid #c3c4c7;
	padding: 8px 12px;
	border-radius: 3px;
	font-family: monospace;
	font-size: 13px;
	cursor: pointer;
	user-select: all;
}

.ghl-shortcode-box:hover {
	background: #e8e8e9;
}

.ghl-forms-empty {
	text-align: center;
	padding: 60px 20px;
	color: #666;
}

.ghl-forms-empty .dashicons {
	font-size: 64px;
	width: 64px;
	height: 64px;
	color: #a7aaad;
	margin-bottom: 20px;
}
</style>

<script>
jQuery(document).ready(function($) {
	'use strict';

	const FormsManager = {
		init: function() {
			this.bindEvents();
			this.loadForms();
		},

		bindEvents: function() {
			$('#ghl-refresh-forms').on('click', () => this.loadForms());
			$(document).on('click', '.ghl-shortcode-box', this.copyShortcode);
		},

		loadForms: function() {
			$('#ghl-forms-loading').show();
			$('#ghl-forms-error').hide();
			$('#ghl-forms-list').empty();

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'ghl_crm_get_forms',
					nonce: '<?php echo esc_js( wp_create_nonce( 'ghl_crm_forms_nonce' ) ); ?>'
				},
				success: (response) => {
					$('#ghl-forms-loading').hide();
					
					if (response.success) {
						this.renderForms(response.data.forms || []);
					} else {
						this.showError(response.data?.message || 'Failed to load forms');
					}
				},
				error: (xhr, status, error) => {
					$('#ghl-forms-loading').hide();
					this.showError('AJAX error: ' + error);
				}
			});
		},

		renderForms: function(forms) {
			const $list = $('#ghl-forms-list');
			
			if (!forms || forms.length === 0) {
				$list.html(`
					<div class="ghl-forms-empty">
						<span class="dashicons dashicons-media-document"></span>
						<h3><?php esc_html_e( 'No Forms Found', 'ghl-crm-integration' ); ?></h3>
						<p><?php esc_html_e( 'Create forms in your GoHighLevel account to display them here.', 'ghl-crm-integration' ); ?></p>
					</div>
				`);
				return;
			}

			forms.forEach(form => {
				const formHtml = `
					<div class="ghl-form-item" data-form-id="${form.id}">
						<div class="ghl-form-info">
							<div class="ghl-form-name">${this.escapeHtml(form.name || 'Untitled Form')}</div>
							<div class="ghl-form-meta">
								<?php esc_html_e( 'ID:', 'ghl-crm-integration' ); ?> ${form.id}
								${form.submissions ? ` | <?php esc_html_e( 'Submissions:', 'ghl-crm-integration' ); ?> ${form.submissions}` : ''}
							</div>
						</div>
						<div class="ghl-form-actions">
							<div class="ghl-shortcode-box" title="<?php esc_attr_e( 'Click to copy', 'ghl-crm-integration' ); ?>">
								[ghl_form id="${form.id}"]
							</div>
							<button type="button" class="button button-secondary ghl-preview-form" data-form-id="${form.id}">
								<?php esc_html_e( 'Preview', 'ghl-crm-integration' ); ?>
							</button>
						</div>
					</div>
				`;
				$list.append(formHtml);
			});
		},

		showError: function(message) {
			$('#ghl-forms-error-message').text(message);
			$('#ghl-forms-error').show();
		},

		copyShortcode: function() {
			const $this = $(this);
			const text = $this.text();
			
			// Copy to clipboard
			navigator.clipboard.writeText(text).then(() => {
				const originalBg = $this.css('background-color');
				$this.css('background-color', '#46b450');
				
				setTimeout(() => {
					$this.css('background-color', originalBg);
				}, 500);
			});
		},

		escapeHtml: function(text) {
			const map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			};
			return text.replace(/[&<>"']/g, m => map[m]);
		}
	};

	FormsManager.init();
});
</script>
