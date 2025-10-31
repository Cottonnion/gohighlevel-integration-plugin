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
 *
 * @package    GHL_CRM_Integration
 * @subpackage Sync
 */
class ContactCache {
	/**
	 * Cache TTL (15 minutes)
	 */
	private const CACHE_TTL = 15 * MINUTE_IN_SECONDS;

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

		$cache_key = $this->get_cache_key( $email );
		return set_transient( $cache_key, $contact, self::CACHE_TTL );
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
