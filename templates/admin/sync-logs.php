<?php
/**
 * Template: Sync Logs Page
 *
 * @package GHL_CRM_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get logs for site ID 1, limit 100
$sync_logger = \GHL_CRM\Sync\SyncLogger::get_instance();
$logs = $sync_logger->get_logs( [
	'limit'   => 100,
	'site_id' => 1,
] );

// Count pending queue and total logs for site 1
global $wpdb;
$queue_count = $wpdb->get_var( $wpdb->prepare(
	"SELECT COUNT(*) FROM {$wpdb->prefix}ghl_crm_sync_queue WHERE status = %s AND site_id = %d",
	'pending',
	1
) );
$log_count = $wpdb->get_var( $wpdb->prepare(
	"SELECT COUNT(*) FROM {$wpdb->prefix}ghl_sync_log WHERE site_id = %d",
	1
) );
?>

<div class="wrap ghl-crm-sync-logs">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<?php if ( $queue_count > 0 ) : ?>
		<div class="notice notice-warning">
			<p>
				<strong><?php echo esc_html( $queue_count ); ?></strong> items in queue pending sync.
				<button type="button" class="button button-primary" onclick="location.href='<?php echo esc_url( admin_url( 'admin.php?page=ghl-crm-sync-logs&trigger_queue=1' ) ); ?>'">
					Process Queue Now
				</button>
			</p>
		</div>
	<?php endif; ?>

	<div class="notice notice-info">
		<p>
			<?php esc_html_e( 'View synchronization history, monitor sync status, and troubleshoot errors.', 'ghl-crm-integration' ); ?>
			<br><small>Total logs: <strong><?php echo esc_html( $log_count ); ?></strong></small>
		</p>
	</div>

	<div class="ghl-crm-logs-container">
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th>Date</th>
					<th>Type</th>
					<th>Action</th>
					<th>Status</th>
					<th>Message / Details</th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! empty( $logs ) ) : ?>
					<?php $user_created = 0; ?>
					<?php foreach ( $logs as $log ) : ?>
						<?php
						if ( $log['action'] === 'user_register' ) {
							$user_created++;
							if ( $user_created > 100 ) break;
						}
						$message = ! empty( $log['error_message'] ) ? $log['error_message'] : json_encode( $log['response_data'] );
						?>
						<tr>
							<td><?php echo esc_html( $log['created_at'] ); ?></td>
							<td><?php echo esc_html( $log['action'] ); ?></td>
							<td><?php echo esc_html( $log['action'] ); ?></td>
							<td><?php echo esc_html( $log['status'] ); ?></td>
							<td><pre><?php echo esc_html( $message ); ?></pre></td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr><td colspan="5"><?php esc_html_e( 'No logs found.', 'ghl-crm-integration' ); ?></td></tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>
