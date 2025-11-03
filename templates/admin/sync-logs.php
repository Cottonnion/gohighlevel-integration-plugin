<?php
/**
 * Template: Sync Logs Page
 *
 * @package GHL_CRM_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Check connection status
$settings_manager = \GHL_CRM\Core\SettingsManager::get_instance();
$settings         = $settings_manager->get_settings_array();
$oauth_handler    = new \GHL_CRM\API\OAuth\OAuthHandler();
$oauth_status     = $oauth_handler->get_connection_status();
$is_connected     = $oauth_status['connected'] || ! empty( $settings['api_token'] );

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

	<?php if ( ! $is_connected ) : ?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'Not Connected', 'ghl-crm-integration' ); ?></strong><br>
				<?php
				printf(
					/* translators: %s: Link to dashboard page */
					esc_html__( 'Please connect to GoHighLevel in %s first.', 'ghl-crm-integration' ),
					sprintf(
						'<a href="%s">%s</a>',
						esc_url( admin_url( 'admin.php?page=ghl-crm-admin' ) ),
						esc_html__( 'Dashboard', 'ghl-crm-integration' )
					)
				);
				?>
			</p>
		</div>
		<?php return; ?>
	<?php endif; ?>

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
						$details_json = json_encode( [
							'action' => $log['action'],
							'status' => $log['status'],
							'error_message' => $log['error_message'] ?? null,
							'response_data' => $log['response_data'] ?? null,
							'item_type' => $log['item_type'] ?? null,
							'item_id' => $log['item_id'] ?? null,
							'created_at' => $log['created_at'],
						], JSON_PRETTY_PRINT );
						?>
						<tr>
							<td><?php echo esc_html( $log['created_at'] ); ?></td>
							<td><?php echo esc_html( $log['action'] ); ?></td>
							<td><?php echo esc_html( $log['action'] ); ?></td>
							<td><?php echo esc_html( $log['status'] ); ?></td>
							<td>
								<button type="button" class="button button-small ghl-view-details" data-details="<?php echo esc_attr( $details_json ); ?>">
									View Details
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr><td colspan="5"><?php esc_html_e( 'No logs found.', 'ghl-crm-integration' ); ?></td></tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<!-- Details Modal -->
<div id="ghl-details-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:9999; overflow:auto;">
	<div style="position:relative; max-width:800px; margin:50px auto; background:#fff; border-radius:8px; box-shadow:0 4px 20px rgba(0,0,0,0.3);">
		<div style="padding:20px; border-bottom:1px solid #ddd; display:flex; justify-content:space-between; align-items:center;">
			<h2 style="margin:0;">Sync Log Details</h2>
			<button type="button" id="ghl-close-modal" style="background:transparent; border:none; font-size:24px; cursor:pointer; color:#666;">&times;</button>
		</div>
		<div style="padding:20px;">
			<pre id="ghl-details-content" style="background:#f5f5f5; padding:15px; border-radius:4px; overflow:auto; max-height:500px; font-size:13px; line-height:1.5;"></pre>
		</div>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	var modal = $('#ghl-details-modal');
	var closeBtn = $('#ghl-close-modal');
	var content = $('#ghl-details-content');

	// Use event delegation for dynamically loaded buttons
	$(document).on('click', '.ghl-view-details', function(e) {
		e.preventDefault();
		var details = $(this).attr('data-details');
		try {
			var parsed = JSON.parse(details);
			content.text(JSON.stringify(parsed, null, 2));
		} catch (err) {
			content.text(details);
		}
		modal.show();
	});

	closeBtn.on('click', function() {
		modal.hide();
	});

	modal.on('click', function(e) {
		if (e.target === modal[0]) {
			modal.hide();
		}
	});

	$(document).on('keydown', function(e) {
		if (e.key === 'Escape' && modal.is(':visible')) {
			modal.hide();
		}
	});
});
</script>
