<?php
/**
 * Auto Login Manager
 *
 * Handles secure one-time login links for user impersonation
 *
 * @package GHL_CRM_Integration
 */

namespace GHL_CRM\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class AutoLoginManager
 */
class AutoLoginManager {

	/**
	 * Token expiry time (15 minutes)
	 */
	const TOKEN_EXPIRY = 900;

	/**
	 * Singleton instance
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
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
	 * Private constructor
	 */
	private function __construct() {
		// Hook into WordPress init to check for auto-login token
		add_action( 'init', [ $this, 'check_auto_login' ], 1 );
	}

	/**
	 * Generate a secure one-time login token for a user.
	 *
	 * @param int $user_id User ID to generate token for.
	 * @return array Token data with 'token' and 'login_url'.
	 * @throws \Exception If token generation fails.
	 */
	public function generate_token( int $user_id ): array {
		// Verify user exists.
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			throw new \Exception( esc_html__( 'User not found', 'ghl-crm-integration' ) );
		}

		// Generate cryptographically secure random token.
		$token      = bin2hex( random_bytes( 32 ) ); // 64 character hex string.
		$token_hash = hash( 'sha256', $token );

		// Set expiry (15 minutes from now).
		$expires_at = time() + ( 15 * MINUTE_IN_SECONDS );

		// Store token data in user meta.
		$token_data = [
			'token_hash' => $token_hash,
			'expires_at' => $expires_at,
			'created_at' => time(),
			'used'       => false,
			'created_by' => get_current_user_id(),
		];

		update_user_meta( $user_id, '_ghl_autologin_token', $token_data );

		// Build login URL.
		$login_url = add_query_arg(
			[
				'ghl_autologin' => $token,
				'user_id'       => $user_id,
			],
			home_url()
		);

		return [
			'token'     => $token,
			'login_url' => $login_url,
			'expires'   => $expires_at,
		];
	}   /**
		 * Check for auto-login request and process it.
		 * Hooked to 'init'.
		 *
		 * @return void
		 */
	public function check_auto_login(): void {
		// Retrieve auto-login request parameters.
		$token_param   = filter_input( INPUT_GET, 'ghl_autologin', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$user_id_param = filter_input( INPUT_GET, 'user_id', FILTER_SANITIZE_NUMBER_INT );

		// Check if auto-login token is present.
		if ( empty( $token_param ) || empty( $user_id_param ) ) {
			return;
		}

		// If user is already logged in, log them out first.
		if ( is_user_logged_in() ) {
			wp_logout();
		}

		$token   = sanitize_text_field( $token_param );
		$user_id = (int) $user_id_param;

		// Validate and login.
		$result = $this->validate_and_login( $token, $user_id );

		if ( $result['success'] ) {
			// Redirect to admin dashboard or profile.
			wp_safe_redirect( admin_url() );
			exit;
		} else {
			// Show error message.
			wp_die(
				esc_html( $result['message'] ),
				esc_html__( 'Login Failed', 'ghl-crm-integration' ),
				[ 'response' => 403 ]
			);
		}
	}

	/**
	 * Validate token and log user in.
	 *
	 * @param string $token   Plain text token from URL.
	 * @param int    $user_id User ID from URL.
	 * @return array Result with 'success' and 'message'.
	 */
	private function validate_and_login( string $token, int $user_id ): array {
		$token_hash = hash( 'sha256', $token );

		// Get token data from user meta.
		$token_data = get_user_meta( $user_id, '_ghl_autologin_token', true );

		if ( ! $token_data || ! is_array( $token_data ) ) {

			return [
				'success' => false,
				'message' => __( 'Invalid or expired login link', 'ghl-crm-integration' ),
			];
		}

		// Verify token hash matches.
		if ( ! isset( $token_data['token_hash'] ) || hash_equals( $token_data['token_hash'], $token_hash ) === false ) {

			return [
				'success' => false,
				'message' => __( 'Invalid or expired login link', 'ghl-crm-integration' ),
			];
		}

		// Check if already used.
		if ( ! empty( $token_data['used'] ) ) {
			return [
				'success' => false,
				'message' => __( 'Login link has already been used', 'ghl-crm-integration' ),
			];
		}

		// Check expiry.
		if ( ! isset( $token_data['expires_at'] ) || $token_data['expires_at'] < time() ) {
			return [
				'success' => false,
				'message' => __( 'Login link has expired', 'ghl-crm-integration' ),
			];
		}

		// Get user.
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return [
				'success' => false,
				'message' => __( 'User not found', 'ghl-crm-integration' ),
			];
		}

		// Mark token as used.
		$token_data['used']    = true;
		$token_data['used_at'] = time();
		update_user_meta( $user_id, '_ghl_autologin_token', $token_data );

		// Log user in.
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, false );
		do_action( 'wp_login', $user->user_login, $user );

		return [
			'success' => true,
			'message' => __( 'Login successful', 'ghl-crm-integration' ),
			'user_id' => $user_id,
		];
	}

	/**
	 * Cleanup expired tokens from user meta.
	 * Should be called via cron job.
	 *
	 * @return int Number of tokens cleaned up.
	 */
	public static function cleanup_expired(): int {
		global $wpdb;

		// Get all user IDs with autologin tokens.
		$cache_group = 'ghl_crm_autologin';
		$cache_key   = 'autologin_token_user_ids_' . get_current_blog_id();
		$user_ids    = wp_cache_get( $cache_key, $cache_group );

		if ( false === $user_ids ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Querying core user meta table by specific key is necessary and cached immediately afterwards.
			$user_ids = $wpdb->get_col(
				"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = '_ghl_autologin_token'"
			);
			wp_cache_set( $cache_key, $user_ids, $cache_group, 5 * MINUTE_IN_SECONDS );
		}

		$cleaned      = 0;
		$current_time = time();

		foreach ( $user_ids as $user_id ) {
			$token_data = get_user_meta( $user_id, '_ghl_autologin_token', true );

			if ( ! is_array( $token_data ) ) {
				continue;
			}

			// Remove if expired or already used.
			if ( ! empty( $token_data['used'] ) ||
				( isset( $token_data['expires_at'] ) && $token_data['expires_at'] < $current_time )
			) {
				delete_user_meta( $user_id, '_ghl_autologin_token' );
				++$cleaned;
			}
		}

		return $cleaned;
	}
}