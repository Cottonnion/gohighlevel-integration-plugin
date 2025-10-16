<?php
declare(strict_types=1);

namespace GHL_CRM\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings Manager class
 *
 * Handles settings storage and retrieval via AJAX.
 *
 * @package    GHL_CRM_Integration
 * @subpackage GHL_CRM_Integration/Core
 */
class SettingsManager {
	/**
	 * Instance of this class
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

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
	 * Connection verification option name
	 *
	 * @var string
	 */
	private const VERIFICATION_OPTION_NAME = 'ghl_crm_connection_verified';

	/**
	 * Get class instance | singleton pattern
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
	 * Private constructor to prevent direct creation
	 */
	private function __construct() {
		// Constructor is empty, hooks are initialized via init()
	}

	/**
	 * Initialize hooks
	 *
	 * @return void
	 */
	public function init(): void {
		// Register AJAX handlers
		add_action( 'wp_ajax_ghl_crm_save_settings', [ $this, 'save_settings' ] );
		add_action( 'wp_ajax_ghl_crm_get_settings', [ $this, 'get_settings' ] );
		add_action( 'wp_ajax_ghl_crm_test_connection', [ $this, 'test_connection' ] );
	}

	/**
	 * Save settings via AJAX
	 *
	 * @return void
	 */
	public function save_settings(): void {
		// Verify nonce
		check_ajax_referer( 'ghl_crm_settings_nonce', 'nonce' );

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [
				'message' => __( 'You do not have permission to save settings.', 'ghl-crm-integration' ),
			], 403 );
		}

		// Get and sanitize POST data
		$api_token     = isset( $_POST['api_token'] ) ? sanitize_text_field( wp_unslash( $_POST['api_token'] ) ) : '';
		$location_id   = isset( $_POST['location_id'] ) ? sanitize_text_field( wp_unslash( $_POST['location_id'] ) ) : '';
		$api_version   = isset( $_POST['api_version'] ) ? sanitize_text_field( wp_unslash( $_POST['api_version'] ) ) : '2021-07-28';

		// Check if API credentials changed
		$current_settings    = $this->get_settings_array();
		$credentials_changed = ( $api_token !== $current_settings['api_token'] ) || 
		                       ( $location_id !== $current_settings['location_id'] );

		// User sync settings
		$enable_user_sync = isset( $_POST['enable_user_sync'] ) && filter_var( $_POST['enable_user_sync'], FILTER_VALIDATE_BOOLEAN );
		$user_sync_actions = isset( $_POST['user_sync_actions'] ) && is_array( $_POST['user_sync_actions'] ) 
			? array_map( 'sanitize_text_field', wp_unslash( $_POST['user_sync_actions'] ) ) 
			: [];
		$delete_contact_on_user_delete = isset( $_POST['delete_contact_on_user_delete'] ) && filter_var( $_POST['delete_contact_on_user_delete'], FILTER_VALIDATE_BOOLEAN );
		
		// User field mapping
		$user_field_mapping = isset( $_POST['user_field_mapping'] ) && is_array( $_POST['user_field_mapping'] ) 
			? array_map( 'sanitize_text_field', wp_unslash( $_POST['user_field_mapping'] ) ) 
			: [];

		// Validate required fields
		if ( empty( $api_token ) || empty( $location_id ) ) {
			wp_send_json_error( [
				'message' => __( 'API Token and Location ID are required.', 'ghl-crm-integration' ),
			], 400 );
		}

		// Prepare settings array
		$settings = [
			'api_token'                     => $api_token,
			'location_id'                   => $location_id,
			'api_version'                   => $api_version,
			'enable_user_sync'              => $enable_user_sync,
			'user_sync_actions'             => $user_sync_actions,
			'delete_contact_on_user_delete' => $delete_contact_on_user_delete,
			'user_field_mapping'            => $user_field_mapping,
			'updated_at'                    => current_time( 'mysql' ),
			'site_id'                       => get_current_blog_id(),
		];

		// Save settings (multisite aware)
		$saved = $this->save_site_settings( $settings );

		// If credentials changed, invalidate verification
		if ( $credentials_changed && $saved ) {
			$this->mark_connection_unverified();
		}

		if ( $saved ) {
			$response_data = [
				'message'  => __( 'Settings saved successfully!', 'ghl-crm-integration' ),
				'settings' => $this->get_settings_array(),
			];

			// Add warning if credentials changed
			if ( $credentials_changed ) {
				$response_data['warning'] = __( 'API credentials changed. Please test your connection to verify.', 'ghl-crm-integration' );
			}

			wp_send_json_success( $response_data );
		} else {
			wp_send_json_error( [
				'message' => __( 'Failed to save settings. Please try again.', 'ghl-crm-integration' ),
			], 500 );
		}
	}

	/**
	 * Get settings via AJAX
	 *
	 * @return void
	 */
	public function get_settings(): void {
		// Verify nonce
		check_ajax_referer( 'ghl_crm_settings_nonce', 'nonce' );

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [
				'message' => __( 'You do not have permission to view settings.', 'ghl-crm-integration' ),
			], 403 );
		}

		wp_send_json_success( [
			'settings' => $this->get_settings_array(),
		] );
	}

	/**
	 * Test API connection
	 *
	 * @return void
	 */
	public function test_connection(): void {
		// Verify nonce
		check_ajax_referer( 'ghl_crm_settings_nonce', 'nonce' );

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [
				'message' => __( 'You do not have permission to test connection.', 'ghl-crm-integration' ),
			], 403 );
		}

		// Get settings
		$settings = $this->get_settings_array();

		if ( empty( $settings['api_token'] ) || empty( $settings['location_id'] ) ) {
			wp_send_json_error( [
				'message' => __( 'Please save your API credentials first.', 'ghl-crm-integration' ),
			], 400 );
		}

		// Test the connection
		$api_url = 'https://services.leadconnectorhq.com/locations/' . $settings['location_id'];
		
		$response = wp_remote_get( $api_url, [
			'headers' => [
				'Authorization' => 'Bearer ' . $settings['api_token'],
				'Version'       => $settings['api_version'],
				'Content-Type'  => 'application/json',
			],
			'timeout' => 15,
		] );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( [
				'message' => sprintf(
					/* translators: %s: Error message */
					__( 'Connection failed: %s', 'ghl-crm-integration' ),
					$response->get_error_message()
				),
			], 500 );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status_code === 200 ) {
			// Mark connection as verified
			$this->mark_connection_verified();

			wp_send_json_success( [
				'message'      => __( 'Connection successful! Your API credentials are working.', 'ghl-crm-integration' ),
				'location_name' => isset( $body['location']['name'] ) ? $body['location']['name'] : '',
				'status_code'  => $status_code,
			] );
		} else {
			// Mark connection as not verified
			$this->mark_connection_unverified();

			wp_send_json_error( [
				'message' => sprintf(
					/* translators: %d: HTTP status code */
					__( 'Connection failed with status code: %d', 'ghl-crm-integration' ),
					$status_code
				),
				'details'     => $body,
				'status_code' => $status_code,
			], $status_code );
		}
	}

	/**
	 * Get settings as array (multisite aware)
	 *
	 * @param int|null $site_id Optional. Site ID for multisite. Defaults to current site.
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
		return wp_parse_args( $settings, [
			'api_token'                     => '',
			'location_id'                   => '',
			'api_version'                   => '2021-07-28',
			'enable_user_sync'              => false,
			'user_sync_actions'             => [],
			'delete_contact_on_user_delete' => false,
			'user_field_mapping'            => [],
			'updated_at'                    => '',
			'site_id'                       => get_current_blog_id(),
		] );
	}

	/**
	 * Save site settings (multisite aware)
	 *
	 * @param array    $settings Settings array.
	 * @param int|null $site_id  Optional. Site ID for multisite.
	 * @return bool
	 */
	private function save_site_settings( array $settings, ?int $site_id = null ): bool {
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

		return wp_parse_args( $settings, [
			'enable_network_wide' => false,
			'default_api_version' => '2021-07-28',
		] );
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
	private function mark_connection_verified( ?int $site_id = null ): bool {
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
	private function mark_connection_unverified( ?int $site_id = null ): bool {
		if ( is_multisite() && null !== $site_id && $site_id !== get_current_blog_id() ) {
			switch_to_blog( $site_id );
			$deleted = delete_option( self::VERIFICATION_OPTION_NAME );
			restore_current_blog();
			return $deleted;
		}

		return delete_option( self::VERIFICATION_OPTION_NAME );
	}

	/**
	 * Get all sites' settings (for network admin)
	 *
	 * @return array
	 */
	public function get_all_sites_settings(): array {
		if ( ! is_multisite() ) {
			return [ get_current_blog_id() => $this->get_settings_array() ];
		}

		$sites = get_sites( [
			'number' => 999,
		] );

		$all_settings = [];

		foreach ( $sites as $site ) {
			$all_settings[ $site->blog_id ] = $this->get_settings_array( (int) $site->blog_id );
		}

		return $all_settings;
	}

	/**
	 * Prevent cloning
	 */
	private function __clone() {}

	/**
	 * Prevent unserializing
	 *
	 * @throws \Exception When attempting to unserialize.
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton' );
	}
}
