<?php
/**
 * Settings - Restrictions Manager Template
 *
 * Content restriction settings tab
 * Controls membership access control based on GHL tags
 *
 * @package    GHL_CRM_Integration
 * @subpackage GHL_CRM_Integration/templates/admin/partials/settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings_manager = \GHL_CRM\Core\SettingsManager::get_instance();
$settings = $settings_manager->get_settings_array();

// Get restriction settings with defaults
$restrictions_enabled = $settings['restrictions_enabled'] ?? true;
$default_redirect_url = $settings['restrictions_default_redirect'] ?? '';
$access_denied_title = $settings['restrictions_denied_title'] ?? __( 'Access Restricted', 'ghl-crm-integration' );
$access_denied_message = $settings['restrictions_denied_message'] ?? __( 'You do not have permission to view this content.', 'ghl-crm-integration' );
$login_message = $settings['restrictions_login_message'] ?? __( 'Please log in to access this content.', 'ghl-crm-integration' );
$archive_message = $settings['restrictions_archive_message'] ?? __( 'This content is restricted.', 'ghl-crm-integration' );
$show_login_link = $settings['restrictions_show_login_link'] ?? true;
$allow_admins = $settings['restrictions_allow_admins'] ?? true;
$hide_restricted_archives = $settings['restrictions_hide_archives'] ?? false;
$hide_from_rest_api = $settings['restrictions_hide_rest_api'] ?? false;
$allowed_tags = $settings['restrictions_allowed_tags'] ?? [];
?>

<div class="ghl-settings-wrapper">
	<?php wp_nonce_field( 'ghl_crm_settings_nonce', 'ghl_crm_nonce' ); ?>
	
	<!-- Master Toggle -->
	<div class="ghl-settings-section ghl-settings-card">
		<div class="ghl-settings-header">
			<h2>
				<span class="dashicons dashicons-lock"></span>
				<?php esc_html_e( 'Content Restrictions', 'ghl-crm-integration' ); ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Control content access based on GoHighLevel tags. Restrict pages, posts, products, and courses to users with specific tags.', 'ghl-crm-integration' ); ?>
			</p>
		</div>
		
		<hr>
		
		<!-- Master Toggle -->
		<div class="ghl-form-builder">
			<div class="ghl-form">
				<div class="ghl-form-item">
					<div class="ghl-form-item-content">
						<label class="ghl-checkbox <?php echo $restrictions_enabled ? 'is-checked' : ''; ?>">
							<input type="checkbox" 
								   class="ghl-checkbox-original"
								   id="restrictions_enabled" 
								   name="restrictions_enabled" 
								   value="1" 
								   <?php checked( $restrictions_enabled ); ?>>
							<span class="ghl-checkbox-input <?php echo $restrictions_enabled ? 'is-checked' : ''; ?>">
								<span class="ghl-checkbox-inner"></span>
							</span>
							<span class="ghl-checkbox-label">
								<?php esc_html_e( 'Enable content restrictions system', 'ghl-crm-integration' ); ?>
								<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Activates tag-based content protection. When enabled, pages/posts/courses/products with assigned tags will only be accessible to users who have matching tags in GoHighLevel.', 'ghl-crm-integration' ); ?>">?</span>
							</span>
						</label>
					</div>
					<p class="description" style="margin-left: 54px;">
						<?php esc_html_e( 'When disabled, all content will be accessible regardless of meta box settings.', 'ghl-crm-integration' ); ?>
					</p>
				</div>
			</div>
		</div>

		<hr>

		<!-- Redirect Settings -->
		<h3><?php esc_html_e( 'Redirect Settings', 'ghl-crm-integration' ); ?></h3>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="restrictions_default_redirect">
							<?php esc_html_e( 'Default Redirect URL', 'ghl-crm-integration' ); ?>
							<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Where to send users who try accessing restricted content. Can be a membership page, login page, or pricing page. Leave empty to show an access denied message instead of redirecting.', 'ghl-crm-integration' ); ?>">?</span>
						</label>
					</th>
					<td>
						<input type="url" 
							   id="restrictions_default_redirect" 
							   name="restrictions_default_redirect" 
							   value="<?php echo esc_url( $default_redirect_url ); ?>" 
							   class="regular-text"
						>
						<p class="description">
							<?php esc_html_e( 'Default URL to redirect users who don\'t have access. Can be overridden per page/post. Leave empty to show access denied message instead.', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>

		<hr>

		<!-- Access Denied Messages -->
		<h3><?php esc_html_e( 'Access Denied Messages', 'ghl-crm-integration' ); ?></h3>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="restrictions_denied_title">
							<?php esc_html_e( 'Access Denied Title', 'ghl-crm-integration' ); ?>
							<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'The main heading displayed on the access denied page when users try to view restricted content without permission.', 'ghl-crm-integration' ); ?>">?</span>
						</label>
					</th>
					<td>
						<input type="text" 
							   id="restrictions_denied_title" 
							   name="restrictions_denied_title" 
							   value="<?php echo esc_attr( $access_denied_title ); ?>" 
							   class="regular-text">
						<p class="description">
							<?php esc_html_e( 'Page title shown when access is denied (no redirect set).', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>

			<tr>
				<th scope="row">
					<label for="restrictions_denied_message">
						<?php esc_html_e( 'Access Denied Message', 'ghl-crm-integration' ); ?>
						<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Rich text message shown to logged-in users who lack the required tags. Supports bold, italic, links, and lists.', 'ghl-crm-integration' ); ?>">?</span>
					</label>
				</th>
				<td>
					<?php
					wp_editor(
						$access_denied_message,
						'restrictions_denied_message',
						[
							'textarea_name' => 'restrictions_denied_message',
							'textarea_rows' => 5,
							'media_buttons' => false,
							'teeny'         => true,
							'quicktags'     => false,
							'tinymce'       => [
								'branding' => false,
							],
						]
					);
					?>
					<p class="description">
						<?php esc_html_e( 'Message shown to logged-in users without required tags.', 'ghl-crm-integration' ); ?>
					</p>
				</td>
			</tr>				<tr>
					<th scope="row">
						<label for="restrictions_login_message">
							<?php esc_html_e( 'Login Required Message', 'ghl-crm-integration' ); ?>
							<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Message shown to visitors who are not logged in when they try to access restricted content.', 'ghl-crm-integration' ); ?>">?</span>
						</label>
					</th>
					<td>
						<textarea id="restrictions_login_message" 
								  name="restrictions_login_message" 
								  rows="3" 
								  class="large-text"><?php echo esc_textarea( $login_message ); ?></textarea>
						<p class="description">
							<?php esc_html_e( 'Message shown to logged-out users.', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<?php esc_html_e( 'Show Login Link', 'ghl-crm-integration' ); ?>
					</th>
					<td>
						<label class="ghl-checkbox <?php echo $show_login_link ? 'is-checked' : ''; ?>">
							<input type="checkbox" 
								   class="ghl-checkbox-original"
								   id="restrictions_show_login_link" 
								   name="restrictions_show_login_link" 
								   value="1" 
								   <?php checked( $show_login_link ); ?>>
							<span class="ghl-checkbox-input <?php echo $show_login_link ? 'is-checked' : ''; ?>">
								<span class="ghl-checkbox-inner"></span>
							</span>
							<span class="ghl-checkbox-label">
								<?php esc_html_e( 'Show login link to logged-out users', 'ghl-crm-integration' ); ?>
								<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Displays a clickable login link on access denied pages. After logging in, users return to the restricted page they tried to access.', 'ghl-crm-integration' ); ?>">?</span>
							</span>
						</label>
						<p class="description" style="margin-left: 54px; margin-top: 8px;">
							<?php esc_html_e( 'Adds a login link with return URL to the access denied page.', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>

		<hr>

		<!-- Archive & Excerpt Settings -->
		<h3><?php esc_html_e( 'Archive & Excerpt Settings', 'ghl-crm-integration' ); ?></h3>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="restrictions_archive_message">
							<?php esc_html_e( 'Archive Message', 'ghl-crm-integration' ); ?>
							<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Short message replacing restricted content in blog listings, category pages, and excerpts. Keep it brief.', 'ghl-crm-integration' ); ?>">?</span>
						</label>
					</th>
					<td>
						<input type="text" 
							   id="restrictions_archive_message" 
							   name="restrictions_archive_message" 
							   value="<?php echo esc_attr( $archive_message ); ?>" 
							   class="regular-text">
						<p class="description">
							<?php esc_html_e( 'Short message shown in place of content on archive pages and excerpts.', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<?php esc_html_e( 'Hide from Archives', 'ghl-crm-integration' ); ?>
					</th>
					<td>
						<label class="ghl-checkbox <?php echo $hide_restricted_archives ? 'is-checked' : ''; ?>">
							<input type="checkbox" 
								   class="ghl-checkbox-original"
								   id="restrictions_hide_archives" 
								   name="restrictions_hide_archives" 
								   value="1" 
								   <?php checked( $hide_restricted_archives ); ?>>
							<span class="ghl-checkbox-input <?php echo $hide_restricted_archives ? 'is-checked' : ''; ?>">
								<span class="ghl-checkbox-inner"></span>
							</span>
							<span class="ghl-checkbox-label">
								<?php esc_html_e( 'Completely hide restricted content from archive pages and search results', 'ghl-crm-integration' ); ?>
								<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Removes restricted items entirely from blog listings, category pages, and search results. When disabled, restricted items still appear but show the archive message instead of content.', 'ghl-crm-integration' ); ?>">?</span>
							</span>
						</label>
						<p class="description" style="margin-left: 54px; margin-top: 8px;">
							<?php esc_html_e( 'When enabled, restricted posts won\'t appear in listings at all. When disabled, they\'ll show with the archive message.', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<?php esc_html_e( 'Hide from REST API', 'ghl-crm-integration' ); ?>
					</th>
					<td>
						<label class="ghl-checkbox <?php echo $hide_from_rest_api ? 'is-checked' : ''; ?>">
							<input type="checkbox" 
								   class="ghl-checkbox-original"
								   id="restrictions_hide_rest_api" 
								   name="restrictions_hide_rest_api" 
								   value="1" 
								   <?php checked( $hide_from_rest_api ); ?>>
							<span class="ghl-checkbox-input <?php echo $hide_from_rest_api ? 'is-checked' : ''; ?>">
								<span class="ghl-checkbox-inner"></span>
							</span>
							<span class="ghl-checkbox-label">
								<?php esc_html_e( 'Hide restricted content from WordPress REST API responses', 'ghl-crm-integration' ); ?>
								<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Prevents restricted posts from appearing in REST API endpoints (/wp-json/wp/v2/posts). Useful for headless WordPress setups or third-party integrations.', 'ghl-crm-integration' ); ?>">?</span>
							</span>
						</label>
						<p class="description" style="margin-left: 54px; margin-top: 8px;">
							<?php esc_html_e( 'Prevents restricted posts from being exposed through the WordPress REST API (e.g., /wp-json/wp/v2/posts).', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>

		<hr>

		<!-- Admin & Override Settings -->
		<h3><?php esc_html_e( 'Override Settings', 'ghl-crm-integration' ); ?></h3>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<?php esc_html_e( 'Allow Administrators', 'ghl-crm-integration' ); ?>
					</th>
					<td>
						<label class="ghl-checkbox <?php echo $allow_admins ? 'is-checked' : ''; ?>">
							<input type="checkbox" 
								   class="ghl-checkbox-original"
								   id="restrictions_allow_admins" 
								   name="restrictions_allow_admins" 
								   value="1" 
								   <?php checked( $allow_admins ); ?>>
							<span class="ghl-checkbox-input <?php echo $allow_admins ? 'is-checked' : ''; ?>">
								<span class="ghl-checkbox-inner"></span>
							</span>
							<span class="ghl-checkbox-label">
								<?php esc_html_e( 'Allow administrators to bypass all restrictions', 'ghl-crm-integration' ); ?>
								<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Recommended: Allows admin users to view all restricted content regardless of their GoHighLevel tags, so they can manage and preview pages.', 'ghl-crm-integration' ); ?>">?</span>
							</span>
						</label>
						<p class="description" style="margin-left: 54px; margin-top: 8px;">
							<?php esc_html_e( 'Recommended: Allows admins to view all content for management purposes.', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="restrictions_allowed_tags">
							<?php esc_html_e( 'Additional Allowed Tags', 'ghl-crm-integration' ); ?>
							<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Global bypass tags: Users with any of these tags can access ALL restricted content, regardless of individual page tag requirements.', 'ghl-crm-integration' ); ?>">?</span>
						</label>
					</th>
					<td>
						<select id="restrictions_allowed_tags" 
								name="restrictions_allowed_tags[]" 
								multiple 
								class="ghl-tags-select"
								style="width: 100%; max-width: 500px;"
								data-placeholder="<?php esc_attr_e( 'Select tags that can bypass restrictions...', 'ghl-crm-integration' ); ?>">
						</select>
						<p class="description" style="margin-top: 8px;">
							<?php esc_html_e( 'Users with any of these tags can view all restricted content, regardless of individual page restrictions.', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>

		<hr>

		<!-- Save Button -->
		<button type="button" id="save-restrictions-settings" class="ghl-button ghl-button-primary ghl-save-settings-btn">
			<span class="ghl-button-text"><?php esc_html_e( 'Save Restrictions Settings', 'ghl-crm-integration' ); ?></span>
		</button>
	</div>

	<!-- Help Section -->
	<div class="ghl-help-box" style="margin-top: 30px;">
		<h3>
			<span class="dashicons dashicons-info"></span>
			<?php esc_html_e( 'How Content Restrictions Work', 'ghl-crm-integration' ); ?>
		</h3>
		<div class="ghl-help-content">
			<ol>
				<li>
					<strong><?php esc_html_e( 'Enable Restrictions:', 'ghl-crm-integration' ); ?></strong>
					<?php esc_html_e( 'Turn on the master toggle above to activate the restrictions system.', 'ghl-crm-integration' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Set Restrictions:', 'ghl-crm-integration' ); ?></strong>
					<?php esc_html_e( 'Edit any page, post, or product and use the "GHL Membership Restrictions" meta box to set tag requirements.', 'ghl-crm-integration' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'User Tags:', 'ghl-crm-integration' ); ?></strong>
					<?php esc_html_e( 'Tags are automatically synced from GoHighLevel when users are created or updated.', 'ghl-crm-integration' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Access Control:', 'ghl-crm-integration' ); ?></strong>
					<?php esc_html_e( 'When a user tries to access restricted content, their tags are checked against the requirements.', 'ghl-crm-integration' ); ?>
				</li>
			</ol>
			
			<p><strong><?php esc_html_e( 'Note:', 'ghl-crm-integration' ); ?></strong>
				<?php esc_html_e( 'These are global settings. Individual pages can override the redirect URL in their meta box settings.', 'ghl-crm-integration' ); ?>
			</p>
		</div>
	</div>
	
</div><!-- .ghl-settings-wrapper -->