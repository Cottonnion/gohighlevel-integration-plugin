<?php
declare(strict_types=1);

namespace Syncly\Sync;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rate Limiter
 *
 * Handles GHL API rate limiting (burst and daily limits)
 * Tracks limits by location ID across multisite installations
 *
 * @package    Syncly
 * @subpackage Sync
 */
class RateLimiter {
	/**
	 * GHL API Rate Limits (per location)
	 */
	private const RATE_LIMIT_BURST        = 100; // Max requests per 10 seconds
	private const RATE_LIMIT_BURST_WINDOW = 10; // Seconds
	private const RATE_LIMIT_DAILY        = 200000; // Max requests per day

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
	 * Check if rate limits allow processing
	 *
	 * @param string|null $location_id GHL location ID
	 * @return bool True if under limits, false if exceeded
	 */
	public function check_limits( ?string $location_id = null ): bool {
		if ( empty( $location_id ) ) {

			return true;
		}

		// Check burst limit
		if ( ! $this->check_burst_limit( $location_id ) ) {
			return false;
		}

		// Check daily limit
		if ( ! $this->check_daily_limit( $location_id ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Check burst limit (100 requests per 10 seconds)
	 *
	 * @param string $location_id GHL location ID
	 * @return bool True if under limit
	 */
	private function check_burst_limit( string $location_id ): bool {
		$burst_key   = $this->get_burst_key( $location_id );
		$burst_data  = get_site_transient( $burst_key );
		$burst_count = $burst_data ? (int) $burst_data : 0;

		if ( $burst_count >= self::RATE_LIMIT_BURST ) {
			return false;
		}

		return true;
	}

	/**
	 * Check daily limit (200,000 requests per day)
	 *
	 * @param string $location_id GHL location ID
	 * @return bool True if under limit
	 */
	private function check_daily_limit( string $location_id ): bool {
		$daily_key   = $this->get_daily_key( $location_id );
		$daily_data  = get_site_transient( $daily_key );
		$daily_count = $daily_data ? (int) $daily_data : 0;

		if ( $daily_count >= self::RATE_LIMIT_DAILY ) {
			return false;
		}

		return true;
	}

	/**
	 * Track API request (increment counters)
	 *
	 * @param string|null $location_id GHL location ID
	 * @return void
	 */
	public function track_request( ?string $location_id = null ): void {
		if ( empty( $location_id ) ) {
			return;
		}

		$this->track_burst_request( $location_id );
		$this->track_daily_request( $location_id );
	}

	/**
	 * Track burst request
	 *
	 * @param string $location_id GHL location ID
	 * @return void
	 */
	private function track_burst_request( string $location_id ): void {
		$burst_key   = $this->get_burst_key( $location_id );
		$burst_data  = get_site_transient( $burst_key );
		$burst_count = $burst_data ? (int) $burst_data : 0;

		set_site_transient( $burst_key, $burst_count + 1, self::RATE_LIMIT_BURST_WINDOW );
	}

	/**
	 * Track daily request
	 *
	 * @param string $location_id GHL location ID
	 * @return void
	 */
	private function track_daily_request( string $location_id ): void {
		$daily_key   = $this->get_daily_key( $location_id );
		$daily_data  = get_site_transient( $daily_key );
		$daily_count = $daily_data ? (int) $daily_data : 0;

		// Set expiry to end of day
		$end_of_day = strtotime( 'tomorrow midnight' ) - time();
		set_site_transient( $daily_key, $daily_count + 1, $end_of_day );
	}

	/**
	 * Get rate limit status
	 *
	 * @param string|null $location_id GHL location ID
	 * @return array Rate limit statistics
	 */
	public function get_status( ?string $location_id = null ): array {
		if ( empty( $location_id ) ) {
			return $this->get_empty_status();
		}

		// Get burst status
		$burst_key       = $this->get_burst_key( $location_id );
		$burst_count     = (int) get_site_transient( $burst_key );
		$burst_remaining = max( 0, self::RATE_LIMIT_BURST - $burst_count );
		$burst_percent   = ( $burst_count / self::RATE_LIMIT_BURST ) * 100;

		// Get daily status
		$daily_key       = $this->get_daily_key( $location_id );
		$daily_count     = (int) get_site_transient( $daily_key );
		$daily_remaining = max( 0, self::RATE_LIMIT_DAILY - $daily_count );
		$daily_percent   = ( $daily_count / self::RATE_LIMIT_DAILY ) * 100;

		return [
			'burst'               => [
				'limit'     => self::RATE_LIMIT_BURST,
				'used'      => $burst_count,
				'remaining' => $burst_remaining,
				'percent'   => round( $burst_percent, 2 ),
				'window'    => self::RATE_LIMIT_BURST_WINDOW . ' seconds',
			],
			'daily'               => [
				'limit'     => self::RATE_LIMIT_DAILY,
				'used'      => $daily_count,
				'remaining' => $daily_remaining,
				'percent'   => round( $daily_percent, 2 ),
				'resets_at' => gmdate( 'Y-m-d H:i:s', strtotime( 'tomorrow midnight' ) ),
			],
			'throttled'           => $burst_count >= self::RATE_LIMIT_BURST || $daily_count >= self::RATE_LIMIT_DAILY,
			'location_id'         => $location_id,
			'shared_across_sites' => is_multisite(),
		];
	}

	/**
	 * Get empty status (when no location ID)
	 *
	 * @return array
	 */
	private function get_empty_status(): array {
		return [
			'burst'               => [
				'limit'     => self::RATE_LIMIT_BURST,
				'used'      => 0,
				'remaining' => self::RATE_LIMIT_BURST,
				'percent'   => 0,
				'window'    => self::RATE_LIMIT_BURST_WINDOW . ' seconds',
			],
			'daily'               => [
				'limit'     => self::RATE_LIMIT_DAILY,
				'used'      => 0,
				'remaining' => self::RATE_LIMIT_DAILY,
				'percent'   => 0,
				'resets_at' => gmdate( 'Y-m-d H:i:s', strtotime( 'tomorrow midnight' ) ),
			],
			'throttled'           => false,
			'location_id'         => null,
			'shared_across_sites' => false,
		];
	}

	/**
	 * Check if the daily limit has been reached for a location.
	 *
	 * @param string|null $location_id GHL location ID.
	 * @return bool True if daily limit reached (200,000 requests).
	 */
	public function is_daily_limit_reached( ?string $location_id = null ): bool {
		if ( empty( $location_id ) ) {
			return false;
		}

		return ! $this->check_daily_limit( $location_id );
	}

	/**
	 * Get the current daily request count for a location.
	 *
	 * @param string|null $location_id GHL location ID.
	 * @return int Requests made today.
	 */
	public function get_daily_count( ?string $location_id = null ): int {
		if ( empty( $location_id ) ) {
			return 0;
		}

		$daily_key = $this->get_daily_key( $location_id );
		return (int) get_site_transient( $daily_key );
	}

	/**
	 * Check if exception is a rate limit error
	 *
	 * @param \Exception $e Exception to check
	 * @return bool True if rate limit error
	 */
	public function is_rate_limit_error( \Exception $e ): bool {
		$message = strtolower( $e->getMessage() );

		return strpos( $message, 'rate limit' ) !== false
			|| strpos( $message, 'too many requests' ) !== false
			|| strpos( $message, '429' ) !== false
			|| ( $e instanceof \Syncly\API\Exceptions\RateLimitException );
	}

	/**
	 * Get burst cache key
	 *
	 * @param string $location_id GHL location ID
	 * @return string
	 */
	private function get_burst_key( string $location_id ): string {
		return 'syncly_rate_burst_' . md5( $location_id );
	}

	/**
	 * Get daily cache key.
	 *
	 * @param string $location_id GHL location ID.
	 * @return string
	 */
	private function get_daily_key( string $location_id ): string {
		return 'syncly_rate_daily_' . md5( $location_id ) . '_' . gmdate( 'Y-m-d' );
	}
}
