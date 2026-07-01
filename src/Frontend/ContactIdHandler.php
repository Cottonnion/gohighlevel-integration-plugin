<?php
declare(strict_types=1);

namespace Syncly\Frontend;

use Syncly\Core\SettingsManager;
use Syncly\Sync\TagManager;

defined( 'ABSPATH' ) || exit;

/**
 * Contact ID Handler
 *
 * Handles signed campaign URL parameters sent via GoHighLevel email campaigns.
 * When a contact clicks a link containing their GHL contact ID, this class:
 *  - Reads and validates the contact ID from the URL
 *  - Persists the contact ID in a signed short-lived cookie so [syncly_user_meta] can
 *    personalize the page for non-logged-in visitors
 * @package    Syncly
 * @subpackage Syncly/Frontend
 */
class ContactIdHandler {

	/**
	 * Transient prefix for contact data cached for guest visitors.
	 *
	 * @var string
	 */
	const TRANSIENT_PREFIX = 'syncly_guest_contact_';

	/**
	 * Cookie name used to store signed guest contact IDs.
	 *
	 * @var string
	 */
	const COOKIE_NAME = 'syncly_visitor_contact';

	/**
	 * How long (seconds) to cache guest contact data in a transient.
	 *
	 * @var int
	 */
	const CACHE_TTL = 3600; // 1 hour

	/**
	 * How long (seconds) to keep guest contact cookie.
	 *
	 * @var int
	 */
	const COOKIE_TTL = 3600; // 1 hour

	/**
	 * Instance of this class.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Get class instance.
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
	 * Private constructor — registers hooks.
	 */
	private function __construct() {
		// Fire early enough to start the session and redirect if auto-login.
		add_action( 'template_redirect', [ $this, 'handle_contact_id_param' ], 1 );
	}

	/**
	 * Main handler: fires on every front-end request.
	 * Reads the signed contact ID from the URL, then either:
	 *  a) Auto-logs in the matched WP user (if enabled and token valid), or
	 *  b) Caches the contact data for guest personalization via [syncly_user_meta].
	 *
	 * @return void
	 */
	public function handle_contact_id_param(): void {
		$settings_manager = SettingsManager::get_instance();

		// Feature must be enabled by the admin.
		if ( empty( $settings_manager->get_setting( 'enable_ghl_cid' ) ) ) {
			return;
		}

		$contact_id = $this->get_request_contact_id();
		if ( '' === $contact_id ) {
			return;
		}
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( '[GHL CID] Query param detected. contact_id=' . $contact_id );

		// Basic format sanity — GHL contact IDs are alphanumeric strings.
		if ( ! preg_match( '/^[a-zA-Z0-9_\-]{10,50}$/', $contact_id ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[GHL CID] Invalid contact_id format. contact_id=' . $contact_id );
			$this->maybe_strip_sensitive_query_args();
			return;
		}

		// --- Guest personalization path (always runs if not already logged in) ---
		if ( ! is_user_logged_in() ) {
			if ( $this->has_valid_campaign_token( $settings_manager ) ) {
				$this->persist_guest_contact( $contact_id );
			}
		}

		$this->maybe_strip_sensitive_query_args();
	}

	/**
	 * Store the GHL contact ID for the current guest visitor so that
	 * [syncly_user_meta] can personalize pages without requiring login.
	 * Data is stored in a WordPress transient keyed by the contact ID.
	 *
	 * @param string $contact_id GHL contact ID.
	 * @return void
	 */
	private function persist_guest_contact( string $contact_id ): void {
		$signed_value = $this->build_signed_contact_cookie_value( $contact_id );

		if ( headers_sent() ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[GHL CID] Cannot persist guest contact: headers already sent.' );
			return;
		}

		$cookie_set = setcookie(
			self::COOKIE_NAME,
			$signed_value,
			[
				'expires'  => time() + self::COOKIE_TTL,
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			]
		);

		if ( $cookie_set ) {
			$_COOKIE[ self::COOKIE_NAME ] = $signed_value;
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[GHL CID] Guest contact persisted to cookie. contact_id=' . $contact_id );
		} else {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[GHL CID] Failed to set guest contact cookie.' );
		}
	}

	/**
	 * Find a WordPress user by their linked GHL contact ID.
	 *
	 * Delegates to TagManager::find_user_by_contact_id().
	 *
	 * @param string $contact_id GHL contact ID.
	 * @return int|null WordPress user ID or null if not found.
	 */
	public static function find_wp_user_by_contact_id( string $contact_id ): ?int {
		return TagManager::get_instance()->find_user_by_contact_id( $contact_id );
	}

	/**
	 * Retrieve the GHL contact ID for the current guest visitor from the session.
	 * Returns null if the visitor is logged in or has no contact ID in session.
	 *
	 * @return string|null Contact ID or null.
	 */
	public static function get_guest_contact_id(): ?string {
		if ( is_user_logged_in() ) {
			return null;
		}

		if ( empty( $_COOKIE[ self::COOKIE_NAME ] ) || ! is_string( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return null;
		}

		$parts = explode( '|', sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) ), 2 );
		if ( 2 !== count( $parts ) ) {
			return null;
		}

		$contact_id = $parts[0];
		$signature  = $parts[1];

		$expected_signature = hash_hmac( 'sha256', $contact_id, wp_salt( 'auth' ) );
		if ( ! hash_equals( $expected_signature, $signature ) ) {
			return null;
		}

		// Re-validate format before returning.
		if ( ! preg_match( '/^[a-zA-Z0-9_\-]{10,50}$/', $contact_id ) ) {
			return null;
		}

		return $contact_id;
	}

	/**
	 * Fetch and cache contact data from GHL for a guest visitor.
	 * Used by [syncly_user_meta] when there is no logged-in user.
	 *
	 * @param string $contact_id GHL contact ID.
	 * @return array Contact data array or empty array on failure.
	 */
	public static function get_guest_contact_data( string $contact_id ): array {
		$transient_key = self::TRANSIENT_PREFIX . md5( $contact_id );
		$cached        = get_transient( $transient_key );

		if ( is_array( $cached ) ) {
			// Re-normalize stale cache entries (handles old wrapped or non-normalized data).
			if ( isset( $cached['contact'] ) && is_array( $cached['contact'] ) ) {
				$cached = $cached['contact'];
			}
			if ( isset( $cached['firstName'] ) || isset( $cached['emailLowerCase'] ) ) {
				$cached = self::normalize_contact_keys( $cached );
				set_transient( $transient_key, $cached, self::CACHE_TTL );
			}
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[GHL CID] Guest contact data cache hit. contact_id=' . $contact_id . ', keys=' . implode( ',', array_keys( $cached ) ) );
			return $cached;
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( '[GHL CID] Fetching contact from API. contact_id=' . $contact_id );

		try {
			$client           = \Syncly\API\Client\Client::get_instance();
			$contact_resource = new \Syncly\API\Resources\ContactResource( $client );
			$contact_data     = $contact_resource->get( $contact_id );

			if ( ! is_array( $contact_data ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[GHL CID] API response is not an array. contact_id=' . $contact_id );
				return [];
			}

			// Some API versions return the payload as { contact: {...} }.
			if ( isset( $contact_data['contact'] ) && is_array( $contact_data['contact'] ) ) {
				$contact_data = $contact_data['contact'];
			}

			// Normalize GHL camelCase keys to WP-style snake_case so shortcodes work
			// the same way for guests as they do for logged-in users (WP user meta).
			$contact_data = self::normalize_contact_keys( $contact_data );

			$found_contact = isset( $contact_data['id'] ) && (string) $contact_data['id'] !== '';
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[GHL CID] Contact fetch result. contact_id=' . $contact_id . ', exists=' . ( $found_contact ? '1' : '0' ) . ', keys=' . implode( ',', array_keys( $contact_data ) ) );

			set_transient( $transient_key, $contact_data, self::CACHE_TTL );
			return $contact_data;

		} catch ( \Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[GHL CID] Contact fetch exception: ' . $e->getMessage() );
			return [];
		}
	}

	/**
	 * Normalize GHL camelCase payload keys to WP-style snake_case so shortcodes
	 * resolve the same keys for guests as for logged-in users (WP user meta).
	 *
	 * @param array $data Raw GHL contact payload.
	 * @return array
	 */
	private static function normalize_contact_keys( array $data ): array {
		$key_map = [
			'firstName'   => 'first_name',
			'lastName'    => 'last_name',
			'email'       => 'user_email',
			'phone'       => 'phone_number',
			'fullName'    => 'full_name',
			'companyName' => 'company',
			'website'     => 'website',
		];

		foreach ( $key_map as $ghl_key => $wp_key ) {
			if ( isset( $data[ $ghl_key ] ) && ! isset( $data[ $wp_key ] ) ) {
				$data[ $wp_key ] = $data[ $ghl_key ];
			}
		}

		return $data;
	}

	/**
	 * Verify the static campaign access token from the URL.
	 *
	 * @param SettingsManager $settings_manager Settings manager instance.
	 * @return bool
	 */
	private function has_valid_campaign_token( SettingsManager $settings_manager ): bool {
		$expected_token = trim( (string) $settings_manager->get_setting( 'ghl_cid_secret_key', '' ) );
		if ( '' === $expected_token ) {
			return false;
		}

		$token = $this->get_request_token();
		if ( '' === $token ) {
			return false;
		}

		return hash_equals( $expected_token, $token );
	}

	/**
	 * Get sanitized contact ID from supported campaign query args.
	 *
	 * @return string
	 */
	private function get_request_contact_id(): string {
		foreach ( [ 'syncly_cid', 'ghl_cid' ] as $query_arg ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public campaign links are authenticated with the campaign access token before state is persisted.
			if ( ! empty( $_GET[ $query_arg ] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public campaign links are authenticated with the campaign access token before state is persisted.
				return sanitize_text_field( wp_unslash( $_GET[ $query_arg ] ) );
			}
		}

		return '';
	}

	/**
	 * Get sanitized campaign access token from supported campaign query args.
	 *
	 * @return string
	 */
	private function get_request_token(): string {
		foreach ( [ 'syncly_token', 'ghl_token' ] as $query_arg ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is the campaign access token used to authenticate the public campaign request.
			if ( ! empty( $_GET[ $query_arg ] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is the campaign access token used to authenticate the public campaign request.
				return sanitize_text_field( wp_unslash( $_GET[ $query_arg ] ) );
			}
		}

		return '';
	}

	/**
	 * Build signed cookie value for guest contact persistence.
	 *
	 * @param string $contact_id GHL contact ID.
	 * @return string
	 */
	private function build_signed_contact_cookie_value( string $contact_id ): string {
		$signature = hash_hmac( 'sha256', $contact_id, wp_salt( 'auth' ) );
		return $contact_id . '|' . $signature;
	}

	/**
	 * Strip sensitive query args from the URL.
	 *
	 * @return void
	 */
	private function maybe_strip_sensitive_query_args(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Presence check only; request authentication happens before persistence.
		$has_sensitive_args = isset( $_GET['syncly_cid'] ) || isset( $_GET['syncly_token'] ) || isset( $_GET['ghl_cid'] ) || isset( $_GET['ghl_token'] );

		if ( ! $has_sensitive_args || is_admin() || wp_doing_ajax() || wp_is_json_request() || headers_sent() ) {
			return;
		}

		$clean_url = remove_query_arg( [ 'syncly_cid', 'syncly_token', 'ghl_cid', 'ghl_token' ] );
		wp_safe_redirect( $clean_url );
		exit;
	}
}
