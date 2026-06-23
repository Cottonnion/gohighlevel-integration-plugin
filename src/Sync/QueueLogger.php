<?php
declare(strict_types=1);

namespace Syncly\Sync;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Queue Logger
 *
 * Handles logging of sync events to database
 * Multisite-aware with per-site tables
 *
 * @package    Syncly
 * @subpackage Sync
 */
class QueueLogger {
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
	 * Log sync event to database
	 *
	 * @param int         $item_id        Item ID (user ID, order ID, etc.)
	 * @param string      $action         Action
	 * @param string      $status         Status (success/error)
	 * @param string|null $contact_id     Contact ID / GHL Object ID
	 * @param array|null  $request_data   Request payload
	 * @param array|null  $response_data  Response data
	 * @param string|null $error_message  Error message
	 * @param float|null  $execution_time Execution time in seconds
	 * @param string      $sync_type      Sync type (user, order, wc_customer, contact, etc.)
	 * @return void
	 */
	public function log_event(
		int $item_id,
		string $action,
		string $status,
		?string $contact_id = null,
		?array $request_data = null,
		?array $response_data = null,
		?string $error_message = null,
		?float $execution_time = null,
		string $sync_type = 'user'
	): void {
		// Check if sync logging is enabled
		if ( ! \Syncly\Core\SettingsManager::is_sync_logging_enabled() ) {
			return;
		}

		// Use SyncLogger for consistent logging
		$sync_logger = SyncLogger::get_instance();

		// Prepare metadata
		$metadata = [];
		if ( ! empty( $request_data ) ) {
			$metadata['request'] = $this->redact_sensitive_fields( $request_data );
		}
		if ( ! empty( $response_data ) ) {
			$metadata['response'] = $this->redact_sensitive_fields( $response_data );
		}
		if ( null !== $execution_time ) {
			$metadata['execution_time'] = $execution_time;
		}

		// Prepare message
		$message = $error_message ?? sprintf( '%s %s', ucfirst( $sync_type ), $action );

		// Map status to SyncLogger format
		$log_status = ( 'error' === $status ) ? 'failed' : $status;

		// Log using SyncLogger
		$sync_logger->log(
			$sync_type,
			$item_id,
			$action,
			$log_status,
			$message,
			$metadata,
			$contact_id ?? ''
		);
	}

	/**
	 * Recursively redact sensitive keys from request/response payloads.
	 *
	 * @param array $payload Data to sanitize.
	 * @return array
	 */
	private function redact_sensitive_fields( array $payload ): array {
		$sensitive_keys = [
			'authorization',
			'access_token',
			'oauth_access_token',
			'oauth_refresh_token',
			'refresh_token',
			'api_token',
			'token',
			'secret',
			'client_secret',
		];

		foreach ( $payload as $key => $value ) {
			$normalized_key = strtolower( (string) $key );

			if ( is_array( $value ) ) {
				$payload[ $key ] = $this->redact_sensitive_fields( $value );
				continue;
			}

			if ( in_array( $normalized_key, $sensitive_keys, true ) ) {
				$payload[ $key ] = '[REDACTED]';
				continue;
			}

			// Redact authorization header values that include bearer tokens.
			if ( 'authorization' === $normalized_key && is_string( $value ) ) {
				$payload[ $key ] = '[REDACTED]';
			}
		}

		return $payload;
	}
}
