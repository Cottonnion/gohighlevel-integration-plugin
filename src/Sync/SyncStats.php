<?php
declare(strict_types=1);

namespace Syncly\Sync;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sync statistics storage utility.
 *
 * Persists aggregate counts for successful and failed sync events per site.
 *
 * @package Syncly
 */
class SyncStats {
	/**
	 * Option key for per-site statistics.
	 */
	private const OPTION_KEY = 'syncly_sync_stats';

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Get singleton instance.
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Record a sync event for the current site.
	 *
	 * @param string $status Sync status (success|failed|pending|skipped).
	 * @param string $sync_type Domain of the sync event.
	 * @param string $timestamp MySQL datetime for the event (defaults to now).
	 */
	public function record_event( string $status, string $sync_type = '', string $timestamp = '' ): void {
		$status = strtolower( $status );

		if ( ! in_array( $status, [ 'success', 'failed' ], true ) ) {
			return; // Only aggregate success/failed counts for now.
		}

		$blog_id   = get_current_blog_id();
		$stats     = $this->get_site_stats( $blog_id );
		$timestamp = $timestamp ?: current_time( 'mysql' );

		$key           = ( 'success' === $status ) ? 'success_total' : 'failed_total';
		$stats[ $key ] = isset( $stats[ $key ] ) ? ( (int) $stats[ $key ] + 1 ) : 1;

		if ( 'success' === $status ) {
			$stats['last_success_at'] = $timestamp;
		} else {
			$stats['last_failure_at'] = $timestamp;
		}

		$stats['last_event_at'] = $timestamp;

		if ( ! empty( $sync_type ) ) {
			$stats['last_sync_type'] = sanitize_text_field( $sync_type );
		}

		$this->persist_site_stats( $stats, $blog_id );
	}

	/**
	 * Retrieve statistics for a site.
	 *
	 * @param int|null $blog_id Blog ID (defaults to current site).
	 * @return array<string, mixed>
	 */
	public function get_site_stats( ?int $blog_id = null ): array {
		$blog_id = $blog_id ?? get_current_blog_id();

		if ( is_multisite() ) {
			$stored = get_blog_option( $blog_id, self::OPTION_KEY, [] );
		} else {
			$stored = get_option( self::OPTION_KEY, [] );
		}

		if ( ! is_array( $stored ) ) {
			$stored = [];
		}

		$defaults = [
			'success_total'   => 0,
			'failed_total'    => 0,
			'last_success_at' => '',
			'last_failure_at' => '',
			'last_event_at'   => '',
			'last_sync_type'  => '',
		];

		return array_merge( $defaults, $stored );
	}

	/**
	 * Replace stored stats with provided payload (merged with defaults).
	 *
	 * @param array<string, mixed> $stats   Stats payload to persist.
	 * @param int|null             $blog_id Blog ID (defaults to current site).
	 */
	public function replace_site_stats( array $stats, ?int $blog_id = null ): void {
		$current = $this->get_site_stats( $blog_id );
		$payload = array_merge( $current, $stats );

		$this->persist_site_stats( $payload, $blog_id );
	}

	/**
	 * Persist statistics for a site.
	 *
	 * @param array<string, mixed> $stats   Stats payload.
	 * @param int|null             $blog_id Blog ID.
	 */
	private function persist_site_stats( array $stats, ?int $blog_id = null ): void {
		$blog_id = $blog_id ?? get_current_blog_id();

		if ( is_multisite() ) {
			update_blog_option( $blog_id, self::OPTION_KEY, $stats );
			return;
		}

		update_option( self::OPTION_KEY, $stats );
	}
}
