<?php
declare(strict_types=1);

namespace GHL_CRM\Sync;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contact Cache
 *
 * Handles caching of GHL contact data using WordPress transients
 * Reduces API calls by caching contact lookups
 * Cache duration is configurable via settings
 *
 * @package    GHL_CRM_Integration
 * @subpackage Sync
 */
class ContactCache {
	/**
	 * Default cache TTL (15 minutes)
	 */
	private const DEFAULT_CACHE_TTL = 15 * MINUTE_IN_SECONDS;

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
	 * Private constructor
	 */
	private function __construct() {
		// Intentionally empty
	}

	/**
	 * Get cache TTL from settings
	 *
	 * @return int Cache duration in seconds
	 */
	private function get_cache_ttl(): int {
		$settings_manager = \GHL_CRM\Core\SettingsManager::get_instance();
		$cache_duration   = absint( $settings_manager->get_setting( 'cache_duration', self::DEFAULT_CACHE_TTL ) );

		// If set to 0, disable caching
		return max( 0, min( 86400, $cache_duration ) ); // Clamp between 0-86400 (24 hours)
	}

	/**
	 * Get cached contact by email
	 *
	 * @param string $email Email address
	 * @return array|null Contact data or null if not cached
	 */
	public function get( string $email ): ?array {
		if ( empty( $email ) ) {
			return null;
		}

		$cache_key = $this->get_cache_key( $email );
		$cached    = get_transient( $cache_key );

		return $cached ? $cached : null;
	}

	/**
	 * Cache contact data
	 *
	 * @param string $email   Email address
	 * @param array  $contact Contact data
	 * @return bool Success status
	 */
	public function set( string $email, array $contact ): bool {
		if ( empty( $email ) || empty( $contact ) ) {
			return false;
		}

		$cache_ttl = $this->get_cache_ttl();

		// If caching is disabled (0), don't cache
		if ( 0 === $cache_ttl ) {
			return true; // Return true but don't cache
		}

		$cache_key = $this->get_cache_key( $email );
		return set_transient( $cache_key, $contact, $cache_ttl );
	}

	/**
	 * Delete cached contact
	 *
	 * @param string $email Email address
	 * @return bool Success status
	 */
	public function delete( string $email ): bool {
		if ( empty( $email ) ) {
			return false;
		}

		$cache_key = $this->get_cache_key( $email );
		return delete_transient( $cache_key );
	}

	/**
	 * Clear all contact cache
	 *
	 * @return void
	 */
	public function clear_all(): void {
		global $wpdb;

		// Delete all transients matching our pattern
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Purging custom transients from options table; this runs rarely and targets only our keys.
		$wpdb->query(
			"DELETE FROM {$wpdb->options} 
			WHERE option_name LIKE '_transient_ghl_contact_%' 
			OR option_name LIKE '_transient_timeout_ghl_contact_%'"
		);
	}

	/**
	 * Get cache key for email
	 *
	 * @param string $email Email address
	 * @return string Cache key
	 */
	private function get_cache_key( string $email ): string {
		return 'ghl_contact_' . md5( strtolower( $email ) );
	}
}
