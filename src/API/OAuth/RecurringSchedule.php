<?php
/**
 * Canonical per-site recurring jobs.
 *
 * @package Syncly
 */

declare(strict_types=1);
namespace Syncly\API\OAuth;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Serialize migration and creation; keep completed Action Scheduler history. */
final class RecurringSchedule {
	/**
	 * Migrate and create one canonical pending recurring job.
	 *
	 * @param string $hook Job hook.
	 * @param int    $interval Frequency in seconds.
	 * @param string $cron_interval Registered cron interval.
	 * @param bool   $enabled Whether to create the job.
	 * @return void
	 */
	public static function ensure( string $hook, int $interval, string $cron_interval, bool $enabled = true ): void {
		$lock = new OptionMutex( 'syncly_schedule_lock_' . $hook );
		if ( ! $lock->acquire() ) {
			return;
		}
		try {
			$version_key = 'syncly_process_queue' === $hook ? 'syncly_queue_schedule_version' : 'syncly_oauth_schedule_version';
			$as_ready    = function_exists( 'as_has_scheduled_action' ) && class_exists( 'ActionScheduler' ) && \ActionScheduler::is_initialized();
			if ( $as_ready ) {
				if ( (int) get_option( $version_key, 0 ) < 3 ) {
					$actions = as_get_scheduled_actions(
						array(
							'hook'     => $hook,
							'group'    => 'syncly',
							'status'   => 'pending',
							'per_page' => -1,
						)
					);
					foreach ( $actions as $id => $action ) {
						if ( $action->get_schedule()->is_recurring() ) {
							\ActionScheduler::store()->cancel_action( $id );
						}
					}
					update_option( $version_key, 3, false );
				}
				wp_clear_scheduled_hook( $hook );
				if ( $enabled && ! as_has_scheduled_action( $hook, array(), 'syncly' ) ) {
					as_schedule_recurring_action( time() + $interval, $interval, $hook, array(), 'syncly', true );
				}
			} elseif ( $enabled ) {
				if ( (int) get_option( $version_key . '_cron', 0 ) < 3 ) {
					wp_clear_scheduled_hook( $hook );
					update_option( $version_key . '_cron', 3, false );
				}
				if ( ! wp_next_scheduled( $hook, array() ) ) {
					wp_schedule_event( time() + $interval, $cron_interval, $hook, array() );
				}
			}
		} finally {
			$lock->release();
		}
	}
}
