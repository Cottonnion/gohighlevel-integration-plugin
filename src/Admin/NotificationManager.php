<?php
/**
 * Notification Manager
 *
 * Enterprise-grade notification system for GHL CRM Integration.
 * Handles email alerts, throttling, daily summaries, and notification templates.
 *
 * @package    GHL_CRM_Integration
 * @subpackage Core
 * @since      1.0.0
 */

declare(strict_types=1);

namespace GHL_CRM\Admin;

use GHL_CRM\Core\SettingsManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NotificationManager
 *
 * Centralized notification system with throttling, templating, and scheduling.
 *
 * @since 1.0.0
 */
class NotificationManager {
	/**
	 * Singleton instance
	 *
	 * @var NotificationManager|null
	 */
	private static ?NotificationManager $instance = null;

	/**
	 * Settings manager instance
	 *
	 * @var SettingsManager
	 */
	private SettingsManager $settings_manager;

	/**
	 * Notification types with their settings keys
	 *
	 * @var array<string, string>
	 */
	private const NOTIFICATION_TYPES = [
		'connection_lost'  => 'notify_connection_lost',
		'sync_errors'      => 'notify_sync_errors',
		'queue_backlog'    => 'notify_queue_backlog',
		'rate_limit'       => 'notify_rate_limit',
		'daily_limit'      => 'notify_rate_limit',
		'webhook_failures' => 'notify_webhook_failures',
		'daily_summary'    => 'notify_daily_summary',
	];

	/**
	 * Throttle transient prefix
	 *
	 * @var string
	 */
	private const THROTTLE_PREFIX = 'ghl_notify_throttle_';

	/**
	 * Get singleton instance
	 *
	 * @return NotificationManager
	 */
	public static function get_instance(): NotificationManager {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->settings_manager = SettingsManager::get_instance();
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		// Schedule daily summary if enabled
		add_action( 'init', [ $this, 'schedule_daily_summary' ] );
		add_action( 'ghl_crm_daily_summary', [ $this, 'send_daily_summary' ] );

		// AJAX handler for test notification
		add_action( 'wp_ajax_ghl_send_test_notification', [ $this, 'handle_test_notification' ] );
		add_action( 'wp_ajax_ghl_send_test_daily_summary', [ $this, 'handle_test_daily_summary' ] );
	}

	/**
	 * Send a notification email
	 *
	 * @param string $type    Notification type (see NOTIFICATION_TYPES)
	 * @param string $subject Email subject
	 * @param string $message Email message (HTML or plain text)
	 * @param array  $context Additional context data for throttling
	 * @return bool True if email sent, false otherwise
	 */
	public function send( string $type, string $subject, string $message, array $context = [] ): bool {
		// Validate notification type
		if ( ! isset( self::NOTIFICATION_TYPES[ $type ] ) ) {
			return false;
		}

		// Check if this notification type is enabled
		$setting_key = self::NOTIFICATION_TYPES[ $type ];
		if ( ! $this->settings_manager->get_setting( $setting_key, false ) ) {
			return false; // Notification disabled in settings
		}

		// Check throttling
		if ( $this->is_throttled( $type, $context ) ) {
			return false; // Still in throttle period
		}

		// Get notification email
		$to = $this->get_notification_email();

		// Build email content
		$email_content = $this->build_email_template( $subject, $message, $type, $context );

		// Send email
		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];
		$sent    = wp_mail( $to, $subject, $email_content, $headers );

		if ( $sent ) {
			// Set throttle
			$this->set_throttle( $type, $context );
		}

		return $sent;
	}

	/**
	 * Send connection lost alert
	 *
	 * @param string $reason Reason for connection loss
	 * @return bool
	 */
	public function send_connection_lost( string $reason ): bool {
		$subject = __( '[CRITICAL] GoHighLevel Connection Lost', 'ghl-crm-integration' );

		$message = sprintf(
			'<h2 style="color: #dc3545;">%s</h2>
			<p>%s</p>
			<p><strong>%s:</strong> %s</p>
			<p><strong>%s:</strong> %s</p>
			<p>%s</p>
			<p><a href="%s" style="display: inline-block; padding: 10px 20px; background: #0073aa; color: white; text-decoration: none; border-radius: 3px;">%s</a></p>',
			esc_html__( 'GoHighLevel Connection Lost', 'ghl-crm-integration' ),
			esc_html__( 'Your WordPress site has lost connection to GoHighLevel. All syncing operations have stopped.', 'ghl-crm-integration' ),
			esc_html__( 'Reason', 'ghl-crm-integration' ),
			esc_html( $reason ),
			esc_html__( 'Action Required', 'ghl-crm-integration' ),
			esc_html__( 'Reconnect to GoHighLevel immediately to resume syncing', 'ghl-crm-integration' ),
			esc_html__( 'This is a critical issue that requires immediate attention. No data will sync until the connection is restored.', 'ghl-crm-integration' ),
			esc_url( admin_url( 'admin.php?page=ghl-crm-admin' ) ),
			esc_html__( 'Reconnect Now', 'ghl-crm-integration' )
		);

		return $this->send( 'connection_lost', $subject, $message, [ 'reason' => $reason ] );
	}

	/**
	 * Send sync error alert
	 *
	 * @param string $sync_type Type of sync that failed
	 * @param string $error     Error message
	 * @param array  $metadata  Additional error metadata
	 * @return bool
	 */
	public function send_sync_error( string $sync_type, string $error, array $metadata = [] ): bool {
		$subject = sprintf(
			/* translators: %s: Sync type (e.g., WooCommerce Order, User) */
			__( '[ERROR] %s Sync Failed', 'ghl-crm-integration' ),
			$sync_type
		);

		$metadata_html = '';
		if ( ! empty( $metadata ) ) {
			$metadata_html = '<h3>' . esc_html__( 'Error Details:', 'ghl-crm-integration' ) . '</h3><ul>';
			foreach ( $metadata as $key => $value ) {
				$metadata_html .= sprintf(
					'<li><strong>%s:</strong> %s</li>',
					esc_html( ucwords( str_replace( '_', ' ', $key ) ) ),
					esc_html( is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) )
				);
			}
			$metadata_html .= '</ul>';
		}

		$message = sprintf(
			'<h2 style="color: #d63638;">%s</h2>
			<p>%s</p>
			<p><strong>%s:</strong> %s</p>
			<p><strong>%s:</strong> %s</p>
			%s
			<p><a href="%s" style="display: inline-block; padding: 10px 20px; background: #0073aa; color: white; text-decoration: none; border-radius: 3px;">%s</a></p>',
			/* translators: %s: Sync type label. */
			esc_html( sprintf( __( '%s Sync Failed', 'ghl-crm-integration' ), $sync_type ) ),
			esc_html__( 'A sync operation failed. The data will be retried automatically.', 'ghl-crm-integration' ),
			esc_html__( 'Sync Type', 'ghl-crm-integration' ),
			esc_html( $sync_type ),
			esc_html__( 'Error', 'ghl-crm-integration' ),
			esc_html( $error ),
			$metadata_html,
			esc_url( admin_url( 'admin.php?page=ghl-crm-admin&tab=logs' ) ),
			esc_html__( 'View Logs', 'ghl-crm-integration' )
		);

		return $this->send(
			'sync_errors',
			$subject,
			$message,
			[
				'sync_type' => $sync_type,
				'error'     => $error,
			]
		);
	}

	/**
	 * Send queue backlog alert
	 *
	 * @param int $queue_count Number of items in queue
	 * @return bool
	 */
	public function send_queue_backlog( int $queue_count ): bool {
		$subject = sprintf(
			/* translators: %d: Number of items in queue */
			__( '[WARNING] Sync Queue Backlog: %d Items Pending', 'ghl-crm-integration' ),
			$queue_count
		);

		$message = sprintf(
			'<h2 style="color: #f0ad4e;">%s</h2>
			<p>%s</p>
			<p><strong>%s:</strong> %s</p>
			<p><strong>%s:</strong></p>
			<ul>
				<li>%s</li>
				<li>%s</li>
				<li>%s</li>
				<li>%s</li>
			</ul>
			<p><a href="%s" style="display: inline-block; padding: 10px 20px; background: #0073aa; color: white; text-decoration: none; border-radius: 3px;">%s</a></p>',
			esc_html__( 'Sync Queue Backlog Warning', 'ghl-crm-integration' ),
			esc_html__( 'Your sync queue has a large number of pending items. This may indicate processing issues or heavy traffic.', 'ghl-crm-integration' ),
			esc_html__( 'Pending Items', 'ghl-crm-integration' ),
			number_format_i18n( $queue_count ),
			esc_html__( 'Possible Causes', 'ghl-crm-integration' ),
			esc_html__( 'API rate limiting is slowing down processing', 'ghl-crm-integration' ),
			esc_html__( 'Heavy traffic or bulk operations in progress', 'ghl-crm-integration' ),
			esc_html__( 'Server resources constrained (slow WP-Cron)', 'ghl-crm-integration' ),
			esc_html__( 'GoHighLevel API experiencing slowness', 'ghl-crm-integration' ),
			esc_url( admin_url( 'admin.php?page=ghl-crm-admin&tab=queue' ) ),
			esc_html__( 'View Queue', 'ghl-crm-integration' )
		);

		return $this->send( 'queue_backlog', $subject, $message, [ 'queue_count' => $queue_count ] );
	}

	/**
	 * Send rate limit alert
	 *
	 * @param int $retry_after Seconds until rate limit resets
	 * @return bool
	 */
	public function send_rate_limit( int $retry_after ): bool {
		$subject = __( '[WARNING] GoHighLevel API Rate Limit Exceeded', 'ghl-crm-integration' );

		$message = sprintf(
			'<h2 style="color: #f0ad4e;">%s</h2>
			<p>%s</p>
			<p><strong>%s:</strong> %s</p>
			<p>%s</p>
			<ul>
				<li>%s</li>
				<li>%s</li>
				<li>%s</li>
			</ul>',
			esc_html__( 'API Rate Limit Exceeded', 'ghl-crm-integration' ),
			esc_html__( 'Your site has hit the GoHighLevel API rate limit. Syncing will automatically resume when the limit resets.', 'ghl-crm-integration' ),
			esc_html__( 'Rate limit resets in', 'ghl-crm-integration' ),
			esc_html( human_time_diff( time(), time() + $retry_after ) ),
			esc_html__( 'To reduce rate limiting:', 'ghl-crm-integration' ),
			esc_html__( 'Increase sync intervals in settings', 'ghl-crm-integration' ),
			esc_html__( 'Disable real-time syncing for less critical data', 'ghl-crm-integration' ),
			esc_html__( 'Consider upgrading your GoHighLevel plan for higher limits', 'ghl-crm-integration' )
		);

		return $this->send( 'rate_limit', $subject, $message, [ 'retry_after' => $retry_after ] );
	}

	/**
	 * Send daily API limit reached alert.
	 *
	 * Notifies admin that the GHL 200,000 request/day limit has been hit.
	 * Queue processing is paused automatically and will resume at midnight UTC.
	 *
	 * Uses the 'daily_limit' notification type (shares the rate_limit setting toggle)
	 * with its own throttle key so it sends at most once per day.
	 *
	 * @param int $daily_count Number of API requests made today.
	 * @param int $pending     Number of queue items still pending.
	 * @return bool
	 */
	public function send_daily_limit_reached( int $daily_count, int $pending = 0 ): bool {
		$resets_at = gmdate( 'Y-m-d H:i:s', strtotime( 'tomorrow midnight' ) );

		$subject = __( '[CRITICAL] GoHighLevel Daily API Limit Reached (200,000 Requests)', 'ghl-crm-integration' );

		$message = sprintf(
			'<h2 style="color: #d63638;">%s</h2>
			<p>%s</p>
			<table style="border-collapse: collapse; width: 100%%; max-width: 400px;">
				<tr><td style="padding: 6px 12px; border: 1px solid #ddd;"><strong>%s</strong></td><td style="padding: 6px 12px; border: 1px solid #ddd;">%s</td></tr>
				<tr><td style="padding: 6px 12px; border: 1px solid #ddd;"><strong>%s</strong></td><td style="padding: 6px 12px; border: 1px solid #ddd;">%s</td></tr>
				<tr><td style="padding: 6px 12px; border: 1px solid #ddd;"><strong>%s</strong></td><td style="padding: 6px 12px; border: 1px solid #ddd;">%s UTC</td></tr>
			</table>
			<p>%s</p>
			<ul>
				<li>%s</li>
				<li>%s</li>
				<li>%s</li>
			</ul>
			<p><a href="%s" style="display: inline-block; padding: 10px 20px; background: #0073aa; color: white; text-decoration: none; border-radius: 3px;">%s</a></p>',
			esc_html__( 'Daily API Limit Reached', 'ghl-crm-integration' ),
			esc_html__( 'Your site has reached the GoHighLevel daily limit of 200,000 API requests. Queue processing is paused and will automatically resume when the limit resets.', 'ghl-crm-integration' ),
			esc_html__( 'Requests today', 'ghl-crm-integration' ),
			number_format_i18n( $daily_count ),
			esc_html__( 'Items still pending', 'ghl-crm-integration' ),
			number_format_i18n( $pending ),
			esc_html__( 'Resets at', 'ghl-crm-integration' ),
			esc_html( $resets_at ),
			esc_html__( 'What you can do:', 'ghl-crm-integration' ),
			esc_html__( 'No action needed — processing resumes automatically at midnight UTC', 'ghl-crm-integration' ),
			esc_html__( 'Reduce batch size in settings to spread requests more evenly', 'ghl-crm-integration' ),
			esc_html__( 'Review which integrations are generating the most sync traffic', 'ghl-crm-integration' ),
			esc_url( admin_url( 'admin.php?page=ghl-crm-admin&tab=queue' ) ),
			esc_html__( 'View Queue Status', 'ghl-crm-integration' )
		);

		return $this->send(
			'daily_limit',
			$subject,
			$message,
			[
				'daily_count' => $daily_count,
				'pending'     => $pending,
			]
		);
	}

	/**
	 * Send webhook failure alert
	 *
	 * @param string $webhook_type Type of webhook that failed
	 * @param string $error        Error message
	 * @return bool
	 */
	public function send_webhook_failure( string $webhook_type, string $error ): bool {
		$subject = sprintf(
			/* translators: %s: Webhook type */
			__( '[ERROR] %s Webhook Failed', 'ghl-crm-integration' ),
			$webhook_type
		);

		$message = sprintf(
			'<h2 style="color: #d63638;">%s</h2>
			<p>%s</p>
			<p><strong>%s:</strong> %s</p>
			<p><strong>%s:</strong> %s</p>
			<p>%s</p>
			<p><a href="%s" style="display: inline-block; padding: 10px 20px; background: #0073aa; color: white; text-decoration: none; border-radius: 3px;">%s</a></p>',
			/* translators: %s: Webhook type label. */
			esc_html( sprintf( __( '%s Webhook Failed', 'ghl-crm-integration' ), $webhook_type ) ),
			esc_html__( 'A webhook from GoHighLevel failed to process. Updates from GHL may not be reflected on your site.', 'ghl-crm-integration' ),
			esc_html__( 'Webhook Type', 'ghl-crm-integration' ),
			esc_html( $webhook_type ),
			esc_html__( 'Error', 'ghl-crm-integration' ),
			esc_html( $error ),
			esc_html__( 'Check your webhook endpoint configuration and verify it\'s accessible from GoHighLevel servers.', 'ghl-crm-integration' ),
			esc_url( admin_url( 'admin.php?page=ghl-crm-admin&tab=logs' ) ),
			esc_html__( 'View Webhook Logs', 'ghl-crm-integration' )
		);

		return $this->send(
			'webhook_failures',
			$subject,
			$message,
			[
				'webhook_type' => $webhook_type,
				'error'        => $error,
			]
		);
	}

	/**
	 * Send daily summary email
	 *
	 * @param bool $force Force send even if disabled in settings (for testing)
	 * @return bool
	 */
	public function send_daily_summary( bool $force = false ): bool {
		// Get stats for the last 24 hours
		$stats = $this->get_daily_stats();

		$subject = sprintf(
			/* translators: %s: Date */
			__( 'GoHighLevel Daily Summary - %s', 'ghl-crm-integration' ),
			wp_date( $this->settings_manager->get_option( 'date_format' ) )
		);

		$message = sprintf(
			'<h2 style="color: #0073aa;">%s</h2>
			<p>%s</p>
			
			<h3>%s</h3>
			<table style="width: 100%%; border-collapse: collapse; margin: 20px 0;">
				<tr style="background: #f0f0f1;">
					<td style="padding: 10px; border: 1px solid #ddd; width: 50%%;"><strong>%s</strong></td>
					<td style="padding: 10px; border: 1px solid #ddd; width: 50%%;">%s</td>
				</tr>
				<tr>
					<td style="padding: 10px; border: 1px solid #ddd;"><strong>%s</strong></td>
					<td style="padding: 10px; border: 1px solid #ddd;">%s</td>
				</tr>
				<tr style="background: #f0f0f1;">
					<td style="padding: 10px; border: 1px solid #ddd;"><strong>%s</strong></td>
					<td style="padding: 10px; border: 1px solid #ddd; color: %s;">%s</td>
				</tr>
				<tr>
					<td style="padding: 10px; border: 1px solid #ddd;"><strong>%s</strong></td>
					<td style="padding: 10px; border: 1px solid #ddd; color: %s;">%s%%</td>
				</tr>
			</table>
			
			<h3>%s</h3>
			<table style="width: 100%%; border-collapse: collapse; margin: 20px 0;">
				<tr style="background: #f0f0f1;">
					<td style="padding: 10px; border: 1px solid #ddd; width: 50%%;"><strong>%s</strong></td>
					<td style="padding: 10px; border: 1px solid #ddd; width: 50%%;">%s</td>
				</tr>
				<tr>
					<td style="padding: 10px; border: 1px solid #ddd;"><strong>%s</strong></td>
					<td style="padding: 10px; border: 1px solid #ddd;">%s</td>
				</tr>
				<tr style="background: #f0f0f1;">
					<td style="padding: 10px; border: 1px solid #ddd;"><strong>%s</strong></td>
					<td style="padding: 10px; border: 1px solid #ddd;">%s</td>
				</tr>
				<tr>
					<td style="padding: 10px; border: 1px solid #ddd;"><strong>%s</strong></td>
					<td style="padding: 10px; border: 1px solid #ddd;">%s</td>
				</tr>
			</table>
			
			<h3>%s</h3>
			<table style="width: 100%%; border-collapse: collapse; margin: 20px 0;">
				<tr style="background: #f0f0f1;">
					<td style="padding: 10px; border: 1px solid #ddd; width: 50%%;"><strong>%s</strong></td>
					<td style="padding: 10px; border: 1px solid #ddd; width: 50%%;">%s</td>
				</tr>
				<tr>
					<td style="padding: 10px; border: 1px solid #ddd;"><strong>%s</strong></td>
					<td style="padding: 10px; border: 1px solid #ddd;">%s</td>
				</tr>
			</table>
			
			<p style="margin-top: 20px;"><a href="%s" style="display: inline-block; padding: 10px 20px; background: #0073aa; color: white; text-decoration: none; border-radius: 3px;">%s</a></p>',
			esc_html__( 'Daily Summary Report', 'ghl-crm-integration' ),
			/* translators: %s: Local time when the summary period ends. */
			esc_html( sprintf( __( 'Here\'s what happened with your GoHighLevel integration in the last 24 hours (ending %s).', 'ghl-crm-integration' ), wp_date( 'g:i A' ) ) ),
			esc_html__( 'Sync Statistics', 'ghl-crm-integration' ),
			esc_html__( 'Total Syncs', 'ghl-crm-integration' ),
			number_format_i18n( $stats['total_syncs'] ),
			esc_html__( 'Successful', 'ghl-crm-integration' ),
			number_format_i18n( $stats['successful_syncs'] ),
			esc_html__( 'Failed', 'ghl-crm-integration' ),
			$stats['failed_syncs'] > 0 ? '#d63638' : '#46b450',
			number_format_i18n( $stats['failed_syncs'] ),
			esc_html__( 'Success Rate', 'ghl-crm-integration' ),
			$stats['success_rate'] >= 95 ? '#46b450' : ( $stats['success_rate'] >= 80 ? '#f0ad4e' : '#d63638' ),
			number_format_i18n( $stats['success_rate'], 1 ),
			esc_html__( 'Activity Breakdown', 'ghl-crm-integration' ),
			esc_html__( 'Users Synced', 'ghl-crm-integration' ),
			number_format_i18n( $stats['users_synced'] ),
			esc_html__( 'Orders Processed', 'ghl-crm-integration' ),
			number_format_i18n( $stats['orders_synced'] ),
			esc_html__( 'LearnDash Events', 'ghl-crm-integration' ),
			number_format_i18n( $stats['learndash_synced'] ),
			esc_html__( 'BuddyBoss Events', 'ghl-crm-integration' ),
			number_format_i18n( $stats['buddyboss_synced'] ),
			esc_html__( 'Queue Status', 'ghl-crm-integration' ),
			esc_html__( 'Pending Items', 'ghl-crm-integration' ),
			number_format_i18n( $stats['queue_pending'] ),
			esc_html__( 'Webhooks Received', 'ghl-crm-integration' ),
			number_format_i18n( $stats['webhooks_received'] ),
			esc_url( admin_url( 'admin.php?page=ghl-crm-admin' ) ),
			esc_html__( 'View Dashboard', 'ghl-crm-integration' )
		);

		return $this->send( 'daily_summary', $subject, $message );
	}

	/**
	 * Get daily statistics
	 *
	 * @return array<string, mixed>
	 */
	private function get_daily_stats(): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ghl_sync_log';
		$yesterday  = gmdate( 'Y-m-d H:i:s', strtotime( '-24 hours' ) );

		// Total syncs
		$total_syncs = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE created_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$yesterday
			)
		);

		// Successful syncs (status = 'success' not 'completed')
		$successful_syncs = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE status = 'success' AND created_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$yesterday
			)
		);

		// Failed syncs
		$failed_syncs = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE status = 'failed' AND created_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$yesterday
			)
		);

		// Success rate
		$success_rate = $total_syncs > 0 ? ( $successful_syncs / $total_syncs ) * 100 : 0;

		// Activity breakdown
		$users_synced = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE sync_type = 'user' AND created_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$yesterday
			)
		);

		$orders_synced = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE sync_type LIKE %s AND created_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'woocommerce%',
				$yesterday
			)
		);

		// LearnDash syncs: course, lesson, topic, quiz
		$learndash_synced = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE sync_type IN ('course', 'lesson', 'topic', 'quiz') AND created_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$yesterday
			)
		);

		$buddyboss_synced = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE sync_type LIKE %s AND created_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'buddyboss%',
				$yesterday
			)
		);

		// Queue status
		$queue_pending = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE status = 'pending'" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			)
		);

		// Webhooks (if webhook log table exists)
		$webhook_table     = $wpdb->prefix . 'ghl_webhook_log';
		$webhooks_received = 0;
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $webhook_table ) ) === $webhook_table ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$webhooks_received = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$webhook_table} WHERE created_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$yesterday
				)
			);
		}

		// Top errors
		$top_errors = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT message, COUNT(*) as count FROM {$table_name} WHERE status = 'failed' AND created_at >= %s GROUP BY message ORDER BY count DESC LIMIT 5", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$yesterday
			),
			ARRAY_A
		);

		return [
			'total_syncs'       => $total_syncs,
			'successful_syncs'  => $successful_syncs,
			'failed_syncs'      => $failed_syncs,
			'success_rate'      => $success_rate,
			'users_synced'      => $users_synced,
			'orders_synced'     => $orders_synced,
			'learndash_synced'  => $learndash_synced,
			'buddyboss_synced'  => $buddyboss_synced,
			'queue_pending'     => $queue_pending,
			'webhooks_received' => $webhooks_received,
			'top_errors'        => $top_errors,
		];
	}

	/**
	 * Format top errors for email
	 *
	 * @param array $errors Top errors array
	 * @return string
	 */
	private function format_top_errors( array $errors ): string {
		if ( empty( $errors ) ) {
			return '';
		}

		$html  = '<h3>' . esc_html__( 'Top Errors (Last 24 Hours)', 'ghl-crm-integration' ) . '</h3>';
		$html .= '<ol>';

		foreach ( $errors as $error ) {
			$html .= sprintf(
				'<li><strong>%s</strong> (%s occurrences)</li>',
				esc_html( $error['message'] ),
				number_format_i18n( (int) $error['count'] )
			);
		}

		$html .= '</ol>';

		return $html;
	}

	/**
	 * Build email template
	 *
	 * @param string $subject Email subject
	 * @param string $message Email message body
	 * @param string $type    Notification type
	 * @param array  $context Additional context
	 * @return string
	 */
	private function build_email_template( string $subject, string $message, string $type, array $context ): string {
		$site_name = get_bloginfo( 'name' );
		$site_url  = get_site_url();

		$template = sprintf(
			'<!DOCTYPE html>
			<html>
			<head>
				<meta charset="UTF-8">
				<meta name="viewport" content="width=device-width, initial-scale=1.0">
				<title>%s</title>
			</head>
			<body style="margin: 0; padding: 0; font-size: 14px; line-height: 1.6; color: #333;">
				<table width="100%%" cellpadding="0" cellspacing="0" style="background-color: #f4f5f7; padding: 20px;">
					<tr>
						<td align="center">
							<table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
								<!-- Header -->
								<tr>
									<td style="background: linear-gradient(135deg, #0073aa 0%%, #005a87 100%%); padding: 30px; text-align: center;">
										<h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: 600;">%s</h1>
										<p style="margin: 8px 0 0 0; color: rgba(255,255,255,0.9); font-size: 14px;">%s</p>
									</td>
								</tr>
								
								<!-- Content -->
								<tr>
									<td style="padding: 40px 30px;">
										%s
									</td>
								</tr>
								
								<!-- Footer -->
								<tr>
									<td style="background-color: #f9fafb; padding: 20px 30px; border-top: 1px solid #e5e7eb; text-align: center;">
										<p style="margin: 0 0 10px 0; font-size: 12px; color: #6b7280;">
											%s
										</p>
										<p style="margin: 0; font-size: 12px; color: #9ca3af;">
											%s
										</p>
									</td>
								</tr>
							</table>
						</td>
					</tr>
				</table>
			</body>
			</html>',
			esc_html( $subject ),
			esc_html( $site_name ),
			esc_html__( 'GoHighLevel CRM Integration', 'ghl-crm-integration' ),
			$message, // Already escaped in calling methods
			sprintf(
				/* translators: %s: Site name */
				esc_html__( 'This notification was sent from %s', 'ghl-crm-integration' ),
				'<a href="' . esc_url( $site_url ) . '" style="color: #0073aa; text-decoration: none;">' . esc_html( $site_name ) . '</a>'
			),
			esc_html__( 'You are receiving this because you enabled GoHighLevel notifications in your WordPress admin.', 'ghl-crm-integration' )
		);

		return $template;
	}

	/**
	 * Check if notification is throttled
	 *
	 * @param string $type    Notification type
	 * @param array  $context Context for unique throttle key
	 * @return bool
	 */
	private function is_throttled( string $type, array $context ): bool {
		$throttle_duration = (int) $this->settings_manager->get_setting( 'notification_throttle', 3600 );

		// No throttling if set to 0
		if ( 0 === $throttle_duration ) {
			return false;
		}

		// Generate unique throttle key
		$throttle_key = $this->get_throttle_key( $type, $context );

		// Check if throttle exists
		return false !== get_transient( $throttle_key );
	}

	/**
	 * Set throttle for notification
	 *
	 * @param string $type    Notification type
	 * @param array  $context Context for unique throttle key
	 * @return void
	 */
	private function set_throttle( string $type, array $context ): void {
		$throttle_duration = (int) $this->settings_manager->get_setting( 'notification_throttle', 3600 );

		if ( 0 === $throttle_duration ) {
			return;
		}

		$throttle_key = $this->get_throttle_key( $type, $context );
		set_transient( $throttle_key, time(), $throttle_duration );
	}

	/**
	 * Generate throttle key
	 *
	 * @param string $type    Notification type
	 * @param array  $context Context data
	 * @return string
	 */
	private function get_throttle_key( string $type, array $context ): string {
		// Create unique key based on type and key context fields
		$key_parts = [ $type ];

		// Add relevant context to make throttling more specific
		if ( isset( $context['sync_type'] ) ) {
			$key_parts[] = $context['sync_type'];
		}
		if ( isset( $context['webhook_type'] ) ) {
			$key_parts[] = $context['webhook_type'];
		}

		return self::THROTTLE_PREFIX . md5( implode( '_', $key_parts ) );
	}

	/**
	 * Get notification email address
	 *
	 * @return string
	 */
	private function get_notification_email(): string {
		return $this->settings_manager->get_setting( 'notification_email', $this->settings_manager->get_option( 'admin_email' ) );
	}

	/**
	 * Schedule daily summary
	 *
	 * @return void
	 */
	public function schedule_daily_summary(): void {
		// Check if daily summary is enabled
		if ( ! $this->settings_manager->get_setting( 'notify_daily_summary', false ) ) {
			// Unschedule if disabled
			$this->unschedule_daily_summary();
			return;
		}

		// Get configured time
		$summary_time          = $this->settings_manager->get_setting( 'daily_summary_time', '09:00' );
		list( $hour, $minute ) = explode( ':', $summary_time );

		// Calculate next run time
		$now      = current_time( 'timestamp' );
		$next_run = strtotime( "today {$hour}:{$minute}" );

		// If time has passed today, schedule for tomorrow
		if ( $next_run < $now ) {
			$next_run = strtotime( "tomorrow {$hour}:{$minute}" );
		}

		// Try Action Scheduler first (if available and initialized)
		if ( function_exists( 'as_next_scheduled_action' ) && class_exists( 'ActionScheduler' ) && \ActionScheduler::is_initialized() ) {
			// Check if already scheduled with Action Scheduler
			if ( ! as_next_scheduled_action( 'ghl_crm_daily_summary' ) ) {
				as_schedule_recurring_action( $next_run, DAY_IN_SECONDS, 'ghl_crm_daily_summary', [], 'ghl-crm' );
			}
		} elseif ( ! wp_next_scheduled( 'ghl_crm_daily_summary' ) ) {
			// Fallback to WP-Cron.
			wp_schedule_event( $next_run, 'daily', 'ghl_crm_daily_summary' );
		}
	}

	/**
	 * Unschedule daily summary
	 *
	 * @return void
	 */
	private function unschedule_daily_summary(): void {
		// Unschedule from Action Scheduler if available and initialized
		if ( function_exists( 'as_unschedule_all_actions' ) && class_exists( 'ActionScheduler' ) && \ActionScheduler::is_initialized() ) {
			as_unschedule_all_actions( 'ghl_crm_daily_summary', [], 'ghl-crm' );
		}

		// Unschedule from WP-Cron
		$timestamp = wp_next_scheduled( 'ghl_crm_daily_summary' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'ghl_crm_daily_summary' );
		}
	}

	/**
	 * Handle test notification AJAX request
	 *
	 * @return void
	 */
	public function handle_test_notification(): void {
		// Verify nonce
		check_ajax_referer( 'ghl_crm_settings_nonce', 'nonce' );

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions', 'ghl-crm-integration' ) ] );
		}

		$subject = __( 'Test Notification - GoHighLevel CRM Integration', 'ghl-crm-integration' );

		$message = sprintf(
			'<h2 style="color: #46b450;">%s</h2>
			<p>%s</p>
			<p>%s</p>
			<ul>
				<li><strong>%s:</strong> %s</li>
				<li><strong>%s:</strong> %s</li>
				<li><strong>%s:</strong> %s</li>
			</ul>
			<p style="margin-top: 20px; padding: 15px; background: #f0f6fc; border-left: 4px solid #0073aa; border-radius: 3px;">
				<strong>%s:</strong> %s
			</p>',
			esc_html__( 'Test Notification Successful!', 'ghl-crm-integration' ),
			esc_html__( 'This is a test notification from your GoHighLevel CRM Integration plugin.', 'ghl-crm-integration' ),
			esc_html__( 'If you received this email, your notification system is working correctly. Critical alerts will be sent to this address.', 'ghl-crm-integration' ),
			esc_html__( 'Sent To', 'ghl-crm-integration' ),
			esc_html( $this->get_notification_email() ),
			esc_html__( 'Sent At', 'ghl-crm-integration' ),
			esc_html( wp_date( $this->settings_manager->get_option( 'date_format' ) . ' ' . $this->settings_manager->get_option( 'time_format' ) ) ),
			esc_html__( 'From Site', 'ghl-crm-integration' ),
			esc_html( get_bloginfo( 'name' ) ),
			esc_html__( 'Note', 'ghl-crm-integration' ),
			esc_html__( 'If you didn\'t receive this email, check your spam folder and verify the email address in your notification settings.', 'ghl-crm-integration' )
		);

		// Force send test notification (bypass settings check)
		$to            = $this->get_notification_email();
		$email_content = $this->build_email_template( $subject, $message, 'test', [] );
		$headers       = [ 'Content-Type: text/html; charset=UTF-8' ];
		$sent          = wp_mail( $to, $subject, $email_content, $headers );

		if ( $sent ) {
			wp_send_json_success(
				[
					'message' => sprintf(
						/* translators: %s: Email address */
						__( 'Test notification sent to %s. Check your inbox (and spam folder).', 'ghl-crm-integration' ),
						$to
					),
				]
			);
		} else {
			wp_send_json_error(
				[
					'message' => __( 'Failed to send test notification. Check your WordPress email configuration.', 'ghl-crm-integration' ),
				]
			);
		}
	}
}
