<?php
declare(strict_types=1);

namespace GHL_CRM\Core\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings Repository
 *
 * Handles saving and retrieving settings from the database
 *
 * @package    GHL_CRM_Integration
 * @subpackage Core\Settings
 */
class SettingsRepository {
	/**
	 * Settings option name (per-site)
	 *
	 * @var string
	 */
	private const OPTION_NAME = 'ghl_crm_settings';

	/**
	 * Network-wide settings option name
	 *
	 * @var string
	 */
	private const NETWORK_OPTION_NAME = 'ghl_crm_network_settings';

	/**
	 * Verification option name
	 *
	 * @var string
	 */
	private const VERIFICATION_OPTION_NAME = 'ghl_crm_connection_verified';

	/**
	 * Singleton instance
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Get instance
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {}

	/**
	 * Get settings array (multisite aware)
	 *
	 * @param int|null $site_id Optional. Site ID for multisite.
	 * @return array
	 */
	public function get_settings_array( ?int $site_id = null ): array {
		if ( is_multisite() && null !== $site_id && $site_id !== get_current_blog_id() ) {
			switch_to_blog( $site_id );
			$settings = get_option( self::OPTION_NAME, [] );
			restore_current_blog();
		} else {
			$settings = get_option( self::OPTION_NAME, [] );
		}

		// Return with defaults
		return wp_parse_args(
			$settings,
			[
				'api_token'                     => '',
				'location_id'                   => '',
				'api_version'                   => '2021-07-28',
				'oauth_access_token'            => '',
				'oauth_refresh_token'           => '',
				'oauth_expires_at'              => 0,
				'oauth_connected_at'            => '',
				'enable_user_sync'              => false,
				'user_sync_actions'             => [],
				'delete_contact_on_user_delete' => false,
				'user_field_mapping'            => [],
				'updated_at'                    => '',
				'site_id'                       => get_current_blog_id(),
			]
		);
	}

	/**
	 * Save site settings (multisite aware)
	 *
	 * @param array    $settings Settings array.
	 * @param int|null $site_id  Optional. Site ID for multisite.
	 * @return bool
	 */
	public function save_site_settings( array $settings, ?int $site_id = null ): bool {
		if ( is_multisite() && null !== $site_id && $site_id !== get_current_blog_id() ) {
			switch_to_blog( $site_id );
			$saved = update_option( self::OPTION_NAME, $settings, true );
			restore_current_blog();
			return $saved;
		}

		return update_option( self::OPTION_NAME, $settings, true );
	}

	/**
	 * Get network-wide settings
	 *
	 * @return array
	 */
	public function get_network_settings(): array {
		if ( ! is_multisite() ) {
			return [];
		}

		$settings = get_site_option( self::NETWORK_OPTION_NAME, [] );

		return wp_parse_args(
			$settings,
			[
				'enable_network_wide' => false,
				'default_api_version' => '2021-07-28',
			]
		);
	}

	/**
	 * Save network-wide settings
	 *
	 * @param array $settings Network settings array.
	 * @return bool
	 */
	public function save_network_settings( array $settings ): bool {
		if ( ! is_multisite() ) {
			return false;
		}

		return update_site_option( self::NETWORK_OPTION_NAME, $settings );
	}

	/**
	 * Get a single setting value
	 *
	 * @param string   $key     Setting key.
	 * @param mixed    $default Default value if key doesn't exist.
	 * @param int|null $site_id Optional. Site ID for multisite.
	 * @return mixed
	 */
	public function get_setting( string $key, $default = '', ?int $site_id = null ) {
		$settings = $this->get_settings_array( $site_id );
		return $settings[ $key ] ?? $default;
	}

	/**
	 * Update a single setting value
	 *
	 * @param string   $key     Setting key.
	 * @param mixed    $value   Setting value.
	 * @param int|null $site_id Optional. Site ID for multisite.
	 * @return bool True on success, false on failure.
	 */
	public function update_setting( string $key, $value, ?int $site_id = null ): bool {
		// Get current settings
		$settings = $this->get_settings_array( $site_id );

		// Update the specific key
		$settings[ $key ] = $value;

		// Save back to database
		return $this->save_site_settings( $settings, $site_id );
	}

	/**
	 * Delete a single setting value
	 *
	 * @param string   $key     Setting key.
	 * @param int|null $site_id Optional. Site ID for multisite.
	 * @return bool True on success, false on failure.
	 */
	public function delete_setting( string $key, ?int $site_id = null ): bool {
		// Get current settings
		$settings = $this->get_settings_array( $site_id );

		// Check if key exists
		if ( ! isset( $settings[ $key ] ) ) {
			return false; // Key doesn't exist, nothing to delete
		}

		// Remove the specific key
		unset( $settings[ $key ] );

		// Save back to database
		return $this->save_site_settings( $settings, $site_id );
	}

	/**
	 * Get option (multisite-aware wrapper)
	 *
	 * @param string   $option_name Option name
	 * @param mixed    $default     Default value
	 * @param int|null $site_id     Optional. Site ID for multisite.
	 * @return mixed Option value
	 */
	public function get_option( string $option_name, $default = false, ?int $site_id = null ) {
		if ( is_multisite() && null !== $site_id && $site_id !== get_current_blog_id() ) {
			switch_to_blog( $site_id );
			$value = get_option( $option_name, $default );
			restore_current_blog();
			return $value;
		}

		return get_option( $option_name, $default );
	}

	/**
	 * Update option (multisite-aware wrapper)
	 *
	 * @param string   $option_name Option name
	 * @param mixed    $value       Option value
	 * @param int|null $site_id     Optional. Site ID for multisite.
	 * @return bool True on success, false on failure
	 */
	public function update_option( string $option_name, $value, ?int $site_id = null ): bool {
		if ( is_multisite() && null !== $site_id && $site_id !== get_current_blog_id() ) {
			switch_to_blog( $site_id );
			$result = update_option( $option_name, $value );
			restore_current_blog();
			return $result;
		}

		return update_option( $option_name, $value );
	}

	/**
	 * Check if multisite is enabled
	 *
	 * @return bool
	 */
	public function is_multisite(): bool {
		return is_multisite();
	}

	/**
	 * Check if API connection is verified
	 *
	 * @param int|null $site_id Optional. Site ID for multisite.
	 * @return bool
	 */
	public function is_connection_verified( ?int $site_id = null ): bool {
		// Get current settings
		$settings = $this->get_settings_array( $site_id );

		// Check if basic credentials exist
		if ( empty( $settings['api_token'] ) || empty( $settings['location_id'] ) ) {
			return false;
		}

		// Check verification status
		if ( is_multisite() && null !== $site_id && $site_id !== get_current_blog_id() ) {
			switch_to_blog( $site_id );
			$verified = get_option( self::VERIFICATION_OPTION_NAME, false );
			restore_current_blog();
			return (bool) $verified;
		}

		return (bool) get_option( self::VERIFICATION_OPTION_NAME, false );
	}

	/**
	 * Mark connection as verified
	 *
	 * @param int|null $site_id Optional. Site ID for multisite.
	 * @return bool
	 */
	public function mark_connection_verified( ?int $site_id = null ): bool {
		$verification_data = [
			'verified'    => true,
			'verified_at' => current_time( 'mysql' ),
			'site_id'     => $site_id ?? get_current_blog_id(),
		];

		if ( is_multisite() && null !== $site_id && $site_id !== get_current_blog_id() ) {
			switch_to_blog( $site_id );
			$saved = update_option( self::VERIFICATION_OPTION_NAME, $verification_data, true );
			restore_current_blog();
			return $saved;
		}

		return update_option( self::VERIFICATION_OPTION_NAME, $verification_data, true );
	}

	/**
	 * Mark connection as not verified
	 *
	 * @param int|null $site_id Optional. Site ID for multisite.
	 * @return bool
	 */
	public function mark_connection_unverified( ?int $site_id = null ): bool {
		if ( is_multisite() && null !== $site_id && $site_id !== get_current_blog_id() ) {
			switch_to_blog( $site_id );
			$deleted = delete_option( self::VERIFICATION_OPTION_NAME );
			restore_current_blog();
			return $deleted;
		}

		return delete_option( self::VERIFICATION_OPTION_NAME );
	}
}
