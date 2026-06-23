<?php
declare(strict_types=1);

namespace Syncly\Core\Settings;

use Syncly\Core\SettingsManager;
use Syncly\Sync\TagManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maintenance Handler
 *
 * Handles cache clearing, settings reset, and manual queue trigger AJAX endpoints.
 * Extracted from SettingsManager to reduce file size and improve cohesion.
 *
 * @package    Syncly
 * @subpackage Syncly/Core/Settings
 */
class MaintenanceHandler {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** Private constructor. */
	private function __construct() {}

	/**
	 * Register AJAX hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_ajax_syncly_clear_cache', [ $this, 'clear_cache' ] );
		add_action( 'wp_ajax_syncly_reset_settings', [ $this, 'reset_settings' ] );
		add_action( 'wp_ajax_syncly_manual_queue_trigger', [ $this, 'manual_queue_trigger' ] );
	}

	/**
	 * AJAX handler: Clear all plugin caches.
	 *
	 * @return void
	 */
	public function clear_cache(): void {
		check_ajax_referer( 'syncly_settings_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You do not have permission to clear cache.', 'syncly' ),
				],
				403
			);
		}

		global $wpdb;
		$site_id = get_current_blog_id();

		// Delete contact cache transients.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Removing plugin transient rows directly from options table.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} 
				WHERE option_name LIKE %s 
				OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_ghl_contact_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_ghl_contact_' ) . '%'
			)
		);

		// Delete rate limit transients.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Removing plugin transient rows directly from options table.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} 
				WHERE option_name LIKE %s 
				OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_ghl_rate_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_ghl_rate_' ) . '%'
			)
		);

		// Delete tags cache.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Removing plugin transient rows directly from options table.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} 
				WHERE (option_name LIKE %s OR option_name LIKE %s)
				AND option_name LIKE %s",
				$wpdb->esc_like( '_transient_ghl_tags_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_ghl_tags_' ) . '%',
				'%' . $wpdb->esc_like( '_site_' . (string) $site_id )
			)
		);

		// Clear object cache if available.
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}

		wp_send_json_success(
			[
				'message' => __( 'Cache cleared successfully!', 'syncly' ),
			]
		);
	}

	/**
	 * AJAX handler: Reset settings to defaults.
	 *
	 * Preserves OAuth connection and manual API connection credentials.
	 *
	 * @return void
	 */
	public function reset_settings(): void {
		check_ajax_referer( 'syncly_settings_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You do not have permission to reset settings.', 'syncly' ),
				],
				403
			);
		}

		$settings_manager = SettingsManager::get_instance();
		$current_settings = $settings_manager->get_settings_array();

		// Preserve ALL connection-related settings (OAuth + Manual API).
		$preserved_credentials = [
			'oauth_access_token'  => $current_settings['oauth_access_token'] ?? '',
			'oauth_refresh_token' => $current_settings['oauth_refresh_token'] ?? '',
			'oauth_expires_at'    => $current_settings['oauth_expires_at'] ?? '',
			'oauth_token_type'    => $current_settings['oauth_token_type'] ?? '',
			'oauth_location_id'   => $current_settings['oauth_location_id'] ?? '',
			'oauth_company_id'    => $current_settings['oauth_company_id'] ?? '',
			'oauth_user_type'     => $current_settings['oauth_user_type'] ?? '',
			'oauth_connected_at'  => $current_settings['oauth_connected_at'] ?? '',
			'api_token'           => $current_settings['api_token'] ?? '',
			'location_id'         => $current_settings['location_id'] ?? '',
			'api_version'         => $current_settings['api_version'] ?? '2021-07-28',
		];

		$default_settings = [
			'cache_duration'                => 3600,
			'batch_size'                    => 50,
			'log_retention_days'            => 30,
			'enable_user_sync'              => false,
			'user_sync_actions'             => [],
			'delete_contact_on_user_delete' => false,
			'user_field_mapping'            => [],
			'restrictions_enabled'          => true,
			'updated_at'                    => current_time( 'mysql' ),
			'site_id'                       => get_current_blog_id(),
		];

		$settings = array_merge( $default_settings, $preserved_credentials );

		// Clear all tag keys (legacy and location-specific) on reset.
		$location_id = $settings['location_id'] ?? $settings['oauth_location_id'] ?? '';

		unset( $settings['role_tags'], $settings['global_tags'], $settings['user_register_tags'] );

		if ( ! empty( $location_id ) ) {
			$settings[ "role_tags_{$location_id}" ]          = [];
			$settings[ "global_tags_{$location_id}" ]        = [];
			$settings[ "user_register_tags_{$location_id}" ] = [];
		}

		$saved = $settings_manager->save_site_settings( $settings );

		if ( $saved ) {
			wp_send_json_success(
				[
					'message'  => __( 'Settings reset to defaults successfully! Your API connection has been preserved.', 'syncly' ),
					'settings' => $settings,
				]
			);
		} else {
			wp_send_json_error(
				[
					'message' => __( 'Failed to reset settings. Please try again.', 'syncly' ),
				],
				500
			);
		}
	}

	/**
	 * AJAX handler: Manually trigger queue processing.
	 *
	 * @return void
	 */
	public function manual_queue_trigger(): void {
		check_ajax_referer( 'syncly_manual_queue', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You do not have permission to perform this action.', 'syncly' ),
				],
				403
			);
		}

		try {
			$queue_manager = \Syncly\Sync\QueueManager::get_instance();

			global $wpdb;
			$table_name      = $wpdb->prefix . 'ghl_sync_queue';
			$current_site_id = get_current_blog_id();

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Checking queue size before manual run against plugin-managed table.
			$pending_before = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table_name} WHERE status = 'pending' AND site_id = %d",
					$current_site_id
				)
			);

			$queue_manager->process_queue();

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Checking queue size after manual run against plugin-managed table.
			$pending_after = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table_name} WHERE status = 'pending' AND site_id = %d",
					$current_site_id
				)
			);

			$processed = $pending_before - $pending_after;

			wp_send_json_success(
				[
					'message'   => sprintf(
						/* translators: %d: number of items processed */
						__( 'Queue processed successfully. Processed %d items.', 'syncly' ),
						$processed
					),
					'processed' => $processed,
					'remaining' => $pending_after,
					'before'    => $pending_before,
				]
			);

		} catch ( \Exception $e ) {
			wp_send_json_error(
				[
					'message' => sprintf(
						/* translators: %s: error message */
						__( 'Failed to process queue: %s', 'syncly' ),
						$e->getMessage()
					),
				],
				500
			);
		} catch ( \Error $err ) {
			wp_send_json_error(
				[
					'message' => sprintf(
						/* translators: %s: error message */
						__( 'A fatal error occurred while processing the queue: %s', 'syncly' ),
						$err->getMessage()
					),
				],
				500
			);
		} catch ( \Throwable $throwable ) {
			wp_send_json_error(
				[
					'message' => sprintf(
						/* translators: %s: error message */
						__( 'An unexpected error occurred while processing the queue: %s', 'syncly' ),
						$throwable->getMessage()
					),
				],
				500
			);
		}
	}

	/** Prevent cloning. */
	private function __clone() {}

	/**
	 * Prevent unserializing.
	 *
	 * @throws \Exception When attempting to unserialize.
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton' );
	}
}
