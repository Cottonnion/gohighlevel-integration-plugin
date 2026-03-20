<?php
/**
 * Scope Checker
 *
 * Checks if the connected API token has access to required scopes
 *
 * @package GHL_CRM_Integration
 */

namespace GHL_CRM\API;

use GHL_CRM\Core\SettingsManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class ScopeChecker
 */
class ScopeChecker {

	/**
	 * Cached scope check results
	 *
	 * @var array
	 */
	private static $scope_cache = array();

	/**
	 * Required scopes for each feature
	 *
	 * @var array
	 */
	private static $feature_scopes = array(
		'contacts'       => array( 'contacts.readonly', 'contacts.write' ),
		'tags'           => array( 'contacts/tags.readonly', 'contacts/tags.write' ),
		'custom_fields'  => array( 'locations/customFields.readonly', 'locations/customFields.write' ),
		'custom_objects' => array( 'objects/schema.readonly', 'objects/records.readonly', 'objects/records.write' ),
		'associations'   => array( 'associations.readonly', 'associations.write', 'associations/relations.readonly', 'associations/relations.write' ),
		'forms'          => array( 'forms.readonly' ),
		'locations'      => array( 'locations.readonly' ),
		'tasks'          => array( 'locations/tasks.write' ),
		'opportunities'  => array( 'opportunities.readonly', 'opportunities.write' ),
	);

	/**
	 * Check if a specific scope is available
	 *
	 * Always makes a fresh API call to get real-time scope status.
	 * No caching to ensure users see immediate results after updating permissions.
	 *
	 * @param string $scope_name Scope name (e.g., 'contacts', 'tags', 'custom_objects').
	 * @return array Array with 'has_access' boolean and 'message' string.
	 */
	public static function check_scope( $scope_name ) {
		// Default result
		$result = array(
			'has_access' => false,
			'message'    => __( 'Unable to verify scope access', 'ghl-crm-integration' ),
			'checked_at' => current_time( 'mysql' ),
		);

		$has_access = false;

		try {
			// Get Client instance
			$client = \GHL_CRM\API\Client\Client::get_instance();

			// Prevent scope checks from triggering OAuth refresh/disconnect cascades
			$client->set_skip_oauth_refresh( true );

			$endpoint = self::get_test_endpoint( $scope_name );

			// Debug logging
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {

			}

			// Call the API - endpoint is an array with 'path' and 'params'
			if ( $endpoint && isset( $endpoint['path'] ) ) {
				// Make the API call - if it succeeds without exception, we have access
				$response = $client->get( $endpoint['path'], $endpoint['params'] ?? array() );
				// If we get here without an exception, access is granted
				$has_access        = true;
				$result['message'] = __( 'Access granted', 'ghl-crm-integration' );

				// Debug logging for success
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {

				}
			} else {
				$result['message'] = __( 'Invalid endpoint configuration', 'ghl-crm-integration' );

				// Debug logging for invalid endpoint
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {

				}
			}
		} catch ( \Exception $e ) {
			$error_message = $e->getMessage();

			// Check if the error message contains the scope unauthorized message
			if ( strpos( $error_message, 'not authorized for this scope' ) !== false ) {
				$has_access        = false;
				$result['message'] = $error_message;
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {

				}
			} elseif ( method_exists( $e, 'get_status_code' ) ) {
				// Check status code from ApiException
				$status_code = $e->get_status_code();
				if ( in_array( $status_code, [ 401, 403, 404 ], true ) ) {
					$has_access        = false;
					$result['message'] = $error_message;
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {

					}
				}
			} elseif ( method_exists( $e, 'get_response_body' ) ) {
				// Check response body for status code
				$response_body = $e->get_response_body();
				if ( isset( $response_body['statusCode'] ) && in_array( $response_body['statusCode'], [ 401, 403, 404 ], true ) ) {
					$has_access        = false;
					$result['message'] = $error_message;
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {

					}
				}
			}
		}

		// Restore normal OAuth refresh behavior
		try {
			$client = \GHL_CRM\API\Client\Client::get_instance();
			$client->set_skip_oauth_refresh( false );
		} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Client may not be available, safe to ignore.
		}

		// Update result with access status
		$result['has_access'] = $has_access;

		return $result;
	}

	/**
	 * Check multiple scopes at once
	 *
	 * @param array $scope_names Array of scope names.
	 * @return array Associative array of scope_name => result.
	 */
	public static function check_multiple_scopes( $scope_names ) {
		$results = array();
		foreach ( $scope_names as $scope_name ) {
			$results[ $scope_name ] = self::check_scope( $scope_name );
		}
		return $results;
	}

	/**
	 * Get test endpoint for a scope
	 *
	 * @param string $scope_name Scope name.
	 * @return array|null Array with 'path' and 'params', or null if unknown.
	 */
	private static function get_test_endpoint( $scope_name ) {
		$settings    = SettingsManager::get_instance()->get_settings_array();
		$location_id = $settings['location_id'] ?? '';

		$endpoints = array(
			'contacts'       => array(
				'path'   => 'contacts/',
				'params' => array(
					'locationId' => $location_id,
					'limit'      => 1,
				),
			),
			'tags'           => array(
				'path'   => 'locations/' . $location_id . '/tags',
				'params' => array( 'limit' => 1 ),
			),
			'custom_fields'  => array(
				'path'   => 'locations/' . $location_id . '/customFields',
				'params' => array(),
			),
			'custom_objects' => array(
				'path'   => 'objects/',
				'params' => array(
					'locationId' => $location_id,
				),
			),
			'associations'   => array(
				'path'   => 'objects/',
				'params' => array(
					'locationId' => $location_id,
				),
			),
			'forms'          => array(
				'path'   => 'forms/',
				'params' => array(
					'locationId' => $location_id,
					'limit'      => 1,
				),
			),
			'locations'      => array(
				'path'   => 'locations/' . $location_id,
				'params' => array(),
			),
			'tasks'          => array(
				'path'   => 'locations/' . $location_id . '/tasks',
				'params' => array( 'limit' => 1 ),
			),
			'opportunities'  => array(
				'path'   => 'opportunities/pipelines',
				'params' => array(
					'locationId' => $location_id,
				),
			),
			// 'conversations'  => array(
			// 'path'   => 'conversations/search',
			// 'params' => array(
			// 'locationId' => $location_id,
			// ),
			// ), // TODO: Requires per-sub-account Marketplace App install.
		);

		return $endpoints[ $scope_name ] ?? null;
	}

	/**
	 * Clear scope cache
	 *
	 * @param string|null $scope_name Optional scope name to clear, or null for all.
	 * @return void
	 */
	public static function clear_cache( $scope_name = null ) {
		if ( $scope_name ) {
			$cache_key = 'ghl_scope_check_' . $scope_name;
			delete_transient( $cache_key );
			unset( self::$scope_cache[ $cache_key ] );
		} else {
			// Clear all scope caches
			foreach ( array_keys( self::$feature_scopes ) as $scope ) {
				$cache_key = 'ghl_scope_check_' . $scope;
				delete_transient( $cache_key );
				unset( self::$scope_cache[ $cache_key ] );
			}
		}
	}

	/**
	 * Get all feature scopes
	 *
	 * @return array
	 */
	public static function get_feature_scopes() {
		return self::$feature_scopes;
	}

	/**
	 * Get scopes required for a feature
	 *
	 * @param string $feature_name Feature name.
	 * @return array|null Array of scope names, or null if feature not found.
	 */
	public static function get_required_scopes( $feature_name ) {
		return self::$feature_scopes[ $feature_name ] ?? null;
	}

	/**
	 * Render a scope warning notice
	 *
	 * Always performs a fresh API check to show real-time permission status.
	 *
	 * @param string $feature_name Feature name (e.g., 'contacts', 'custom_objects').
	 * @return void
	 */
	public static function render_scope_notice( $feature_name ) {
		$result = self::check_scope( $feature_name );

		if ( ! $result['has_access'] ) {
			$scopes_required = self::get_required_scopes( $feature_name );
			$scopes_list     = $scopes_required ? implode( ', ', $scopes_required ) : __( 'Unknown', 'ghl-crm-integration' );

			?>
			<div class="notice notice-error" style="border-left-color: #dc3232;">
				<p>
					<strong><?php esc_html_e( 'Missing Permissions', 'ghl-crm-integration' ); ?></strong><br>
					<?php
					printf(
						/* translators: %s: Error message from API */
						esc_html__( 'Error: %s', 'ghl-crm-integration' ),
						esc_html( $result['message'] )
					);
					?>
				</p>
				<p>
					<strong><?php esc_html_e( 'Required Scopes:', 'ghl-crm-integration' ); ?></strong>
					<code><?php echo esc_html( $scopes_list ); ?></code>
				</p>
				<p>
					<?php esc_html_e( 'Please reconnect your GoHighLevel account with the required permissions, or contact your administrator to update the API key/OAuth app scopes.', 'ghl-crm-integration' ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=ghl-crm-admin' ) ); ?>" class="button button-small">
						<?php esc_html_e( 'Go to Connection Settings', 'ghl-crm-integration' ); ?>
					</a>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Render an inline scope warning badge
	 *
	 * Always performs a fresh API check to show real-time permission status.
	 *
	 * @param string $feature_name Feature name.
	 * @return void
	 */
	public static function render_scope_badge( $feature_name ) {
		$result = self::check_scope( $feature_name );

		if ( $result['has_access'] ) {
			?>
			<span class="ghl-scope-badge ghl-scope-granted" style="display: inline-flex; align-items: center; background: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 3px; padding: 2px 8px; font-size: 12px; margin-left: 8px;">
				<span class="dashicons dashicons-yes-alt" style="font-size: 14px; width: 14px; height: 14px; margin-right: 4px;"></span>
				<?php esc_html_e( 'Access Granted', 'ghl-crm-integration' ); ?>
			</span>
			<?php
		} else {
			?>
			<span class="ghl-scope-badge ghl-scope-denied" style="display: inline-flex; align-items: center; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 3px; padding: 2px 8px; font-size: 12px; margin-left: 8px;" title="<?php echo esc_attr( $result['message'] ); ?>">
				<span class="dashicons dashicons-dismiss" style="font-size: 14px; width: 14px; height: 14px; margin-right: 4px;"></span>
				<?php esc_html_e( 'No Access', 'ghl-crm-integration' ); ?>
			</span>
			<?php
		}
	}
}
