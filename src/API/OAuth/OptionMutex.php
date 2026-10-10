<?php
/**
 * Database-backed OAuth and scheduling lease.
 *
 * @package Syncly
 */

declare(strict_types=1);
namespace Syncly\API\OAuth;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Owns and releases only its own lease, including after an expired lease is replaced. */
final class OptionMutex {
	/**
	 * Per-site option name.
	 *
	 * @var string
	 */
	private string $key;
	/**
	 * Unique owner and lease expiry.
	 *
	 * @var string
	 */
	private string $value;

	/**
	 * Create a lease.
	 *
	 * @param string $key Per-site option name.
	 */
	public function __construct( string $key ) {
		$this->key   = $key;
		$this->value = '';
	}

	/**
	 * Reserve a new lease or replace an expired lease with a compare-and-swap.
	 *
	 * @param int $ttl Lease duration in seconds.
	 * @return bool
	 */
	public function acquire( int $ttl = 120 ): bool {
		global $wpdb;
		$value = wp_generate_uuid4() . ':' . ( time() + $ttl );
		if ( add_option( $this->key, $value, '', false ) ) {
			$this->value = $value;
			return true;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Lease ownership must bypass potentially stale caches.
		$current = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $this->key ) );
		if ( null === $current ) {
			// Another worker released the lease while this request retained its cache.
			// Retry the atomic insertion after discarding that stale cached value.
			wp_cache_delete( $this->key, 'options' );
			if ( add_option( $this->key, $value, '', false ) ) {
				$this->value = $value;
				return true;
			}
			return false;
		}
		$legacy = maybe_unserialize( $current );
		$expiry = is_array( $legacy ) ? (int) ( $legacy['created'] ?? time() ) + $ttl : (int) substr( (string) $current, strrpos( (string) $current, ':' ) + 1 );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Compare-and-swap prevents two expired-lease claimants winning.
		if ( $expiry > 0 && $expiry < time() && 1 === $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND BINARY option_value = BINARY %s", $value, $this->key, $current ) ) ) {
			wp_cache_delete( $this->key, 'options' );
			$this->value = $value;
			return true;
		}
		return false;
	}

	/**
	 * Release only this owner's lease.
	 *
	 * @return void
	 */
	public function release(): void {
		global $wpdb;
		if ( '' !== $this->value ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Delete is conditional on this owner's exact lease value.
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND BINARY option_value = BINARY %s", $this->key, $this->value ) );
			wp_cache_delete( $this->key, 'options' );
			$this->value = '';
		}
	}
}
