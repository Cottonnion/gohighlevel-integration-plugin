<?php
/**
 * Template: Sync Logs Page
 *
 * @package GHL_CRM_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Assets are enqueued via AssetsManager

// Check connection status
$settings_manager = \GHL_CRM\Core\SettingsManager::get_instance();
$settings         = $settings_manager->get_settings_array();
$oauth_handler    = new \GHL_CRM\API\OAuth\OAuthHandler();
$oauth_status     = $oauth_handler->get_connection_status();
$is_connected     = $oauth_status['connected'] || ! empty( $settings['api_token'] );

// Check if logging is enabled
$is_logging_enabled = \GHL_CRM\Core\SettingsManager::is_sync_logging_enabled();

// Get current page
$current_page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
$per_page = 20;
$offset = ( $current_page - 1 ) * $per_page;

// Get logs for site ID 1
$sync_logger = \GHL_CRM\Sync\SyncLogger::get_instance();
$logs = $sync_logger->get_logs( [
	'limit'   => $per_page,
	'offset'  => $offset,
	'site_id' => get_current_blog_id(),
] );

// Count pending queue and total logs
global $wpdb;
$site_id = get_current_blog_id();
$queue_count = $wpdb->get_var( $wpdb->prepare(
	"SELECT COUNT(*) FROM {$wpdb->prefix}ghl_crm_sync_queue WHERE status = %s AND site_id = %d",
	'pending',
	$site_id
) );
$log_count = $wpdb->get_var( $wpdb->prepare(
	"SELECT COUNT(*) FROM {$wpdb->prefix}ghl_sync_log WHERE site_id = %d",
	$site_id
) );
$total_pages = ceil( $log_count / $per_page );
?>

<div class="wrap ghl-crm-sync-logs">


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

	<!-- Status Info -->
	<div style="margin-bottom: 20px; padding: 12px 16px; background: #f0f0f1; border-left: 4px solid <?php echo $is_logging_enabled ? '#46b450' : '#dc3232'; ?>; font-size: 13px;">
		<strong><?php esc_html_e( 'Logging:', 'ghl-crm-integration' ); ?></strong> 
		<?php if ( $is_logging_enabled ) : ?>
			<span style="color: #46b450;"><?php esc_html_e( 'Enabled', 'ghl-crm-integration' ); ?></span>
		<?php else : ?>
			<span style="color: #dc3232;"><?php esc_html_e( 'Disabled', 'ghl-crm-integration' ); ?></span>
			<span style="color: #666;"> — 
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ghl-crm-admin&tab=advanced#advanced' ) ); ?>"><?php esc_html_e( 'Enable in Settings', 'ghl-crm-integration' ); ?></a>
			</span>
		<?php endif; ?>
		<span style="margin-left: 20px; color: #666;">
			<?php printf( esc_html__( 'Total Logs: %s', 'ghl-crm-integration' ), '<strong>' . number_format( $log_count ) . '</strong>' ); ?>
		</span>
		<?php if ( $queue_count > 0 ) : ?>
			<span style="margin-left: 20px; color: #f0b849;">
				<?php printf( esc_html__( 'Queue: %s pending', 'ghl-crm-integration' ), '<strong>' . number_format( $queue_count ) . '</strong>' ); ?>
			</span>
		<?php endif; ?>
	</div>

	<!-- Filters and Actions -->
	<div class="ghl-logs-filters">
		<div class="ghl-filters-row">
			<div class="ghl-filter-group">
				<label for="ghl-filter-status"><?php esc_html_e( 'Filter by Status', 'ghl-crm-integration' ); ?></label>
				<select id="ghl-filter-status">
					<option value=""><?php esc_html_e( 'All Statuses', 'ghl-crm-integration' ); ?></option>
					<option value="success"><?php esc_html_e( 'Success', 'ghl-crm-integration' ); ?></option>
					<option value="error"><?php esc_html_e( 'Error', 'ghl-crm-integration' ); ?></option>
					<option value="pending"><?php esc_html_e( 'Pending', 'ghl-crm-integration' ); ?></option>
				</select>
			</div>

			<div class="ghl-filter-group">
				<label for="ghl-search-logs"><?php esc_html_e( 'Search Logs', 'ghl-crm-integration' ); ?></label>
				<input type="text" id="ghl-search-logs" placeholder="<?php esc_attr_e( 'Search by action, message...', 'ghl-crm-integration' ); ?>">
			</div>

			<div class="ghl-actions-group">
				<?php if ( $queue_count > 0 ) : ?>
					<button type="button" id="ghl-process-queue" class="ghl-button ghl-button-primary">
						<span class="dashicons dashicons-update"></span>
						<span class="ghl-button-text"><?php esc_html_e( 'Process Queue', 'ghl-crm-integration' ); ?></span>
					</button>
				<?php endif; ?>
				<button type="button" id="ghl-delete-logs" class="ghl-button ghl-button-secondary">
					<span class="dashicons dashicons-trash"></span>
					<span class="ghl-button-text"><?php esc_html_e( 'Delete Old Logs', 'ghl-crm-integration' ); ?></span>
				</button>
				<button type="button" id="ghl-clear-all-logs" class="ghl-button ghl-button-danger">
					<span class="dashicons dashicons-warning"></span>
					<span class="ghl-button-text"><?php esc_html_e( 'Clear All', 'ghl-crm-integration' ); ?></span>
				</button>
			</div>
		</div>
	</div>

	<!-- Logs Table -->
	<div id="ghl-logs-table-container">
		<div class="ghl-logs-table-wrapper">
			<table class="ghl-logs-table">
				<thead>
					<tr>
						<th style="width: 180px;"><?php esc_html_e( 'Date', 'ghl-crm-integration' ); ?></th>
						<th style="width: 150px;"><?php esc_html_e( 'Type', 'ghl-crm-integration' ); ?></th>
						<th><?php esc_html_e( 'Action', 'ghl-crm-integration' ); ?></th>
						<th style="width: 120px;"><?php esc_html_e( 'Status', 'ghl-crm-integration' ); ?></th>
						<th style="width: 140px;"><?php esc_html_e( 'Details', 'ghl-crm-integration' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! empty( $logs ) ) : ?>
						<?php foreach ( $logs as $log ) : ?>
							<?php
							$details_json = wp_json_encode( [
								'sync_type'      => $log['sync_type'] ?? '',
								'item_id'        => $log['item_id'] ?? '',
								'action'         => $log['action'] ?? '',
								'status'         => $log['status'] ?? '',
								'message'        => $log['message'] ?? '',
								'ghl_id'         => $log['ghl_id'] ?? '',
								'metadata'       => $log['metadata'] ?? null,
								'created_at'     => $log['created_at'] ?? '',
							], JSON_PRETTY_PRINT );
							?>
							<tr>
								<td>
									<span class="ghl-log-date"><?php echo esc_html( $log['created_at'] ); ?></span>
								</td>
								<td>
									<span class="ghl-log-type">
										<span class="dashicons dashicons-admin-users"></span>
										<?php echo esc_html( ucfirst( $log['sync_type'] ?? 'unknown' ) ); ?>
									</span>
								</td>
								<td>
									<span class="ghl-log-action"><?php echo esc_html( $log['action'] ?? 'N/A' ); ?></span>
								</td>
								<td>
									<span class="ghl-log-status <?php echo esc_attr( strtolower( $log['status'] ?? 'unknown' ) ); ?>">
										<?php echo esc_html( $log['status'] ?? 'Unknown' ); ?>
									</span>
								</td>
								<td>
									<button type="button" class="ghl-button ghl-button-small ghl-button-secondary ghl-view-details" data-details="<?php echo esc_attr( $details_json ); ?>">
										<span class="dashicons dashicons-visibility"></span>
										<?php esc_html_e( 'View', 'ghl-crm-integration' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr>
							<td colspan="5">
								<div class="ghl-logs-empty">
									<div class="ghl-logs-empty-icon">
										<span class="dashicons dashicons-database-view"></span>
									</div>
									<h3 class="ghl-logs-empty-title"><?php esc_html_e( 'No Logs Found', 'ghl-crm-integration' ); ?></h3>
									<p class="ghl-logs-empty-text"><?php esc_html_e( 'Sync events will appear here once your integration starts processing data.', 'ghl-crm-integration' ); ?></p>
								</div>
							</td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ( $total_pages > 1 ) : ?>
				<div class="ghl-logs-pagination">
					<div class="ghl-pagination-info">
						<?php
						$start = $offset + 1;
						$end = min( $offset + $per_page, $log_count );
						printf(
							/* translators: 1: Start number, 2: End number, 3: Total count */
							esc_html__( 'Showing %1$d-%2$d of %3$d logs', 'ghl-crm-integration' ),
							$start,
							$end,
							$log_count
						);
						?>
					</div>
					<div class="ghl-pagination-links">
						<?php
						$range = 2;
						$start_page = max( 1, $current_page - $range );
						$end_page = min( $total_pages, $current_page + $range );

						// Previous button
						if ( $current_page > 1 ) {
							echo '<a href="' . esc_url( add_query_arg( 'paged', $current_page - 1 ) ) . '" class="ghl-pagination-link" data-page="' . ( $current_page - 1 ) . '">←</a>';
						}

						// First page
						if ( $start_page > 1 ) {
							echo '<a href="' . esc_url( add_query_arg( 'paged', 1 ) ) . '" class="ghl-pagination-link" data-page="1">1</a>';
							if ( $start_page > 2 ) {
								echo '<span class="ghl-pagination-link disabled">...</span>';
							}
						}

						// Page numbers
						for ( $i = $start_page; $i <= $end_page; $i++ ) {
							$class = $i === $current_page ? 'ghl-pagination-link active' : 'ghl-pagination-link';
							echo '<a href="' . esc_url( add_query_arg( 'paged', $i ) ) . '" class="' . $class . '" data-page="' . $i . '">' . $i . '</a>';
						}

						// Last page
						if ( $end_page < $total_pages ) {
							if ( $end_page < $total_pages - 1 ) {
								echo '<span class="ghl-pagination-link disabled">...</span>';
							}
							echo '<a href="' . esc_url( add_query_arg( 'paged', $total_pages ) ) . '" class="ghl-pagination-link" data-page="' . $total_pages . '">' . $total_pages . '</a>';
						}

						// Next button
						if ( $current_page < $total_pages ) {
							echo '<a href="' . esc_url( add_query_arg( 'paged', $current_page + 1 ) ) . '" class="ghl-pagination-link" data-page="' . ( $current_page + 1 ) . '">→</a>';
						}
						?>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>

<!-- Details Modal -->
<div id="ghl-details-modal">
	<div class="ghl-modal-content">
		<div class="ghl-modal-header">
			<h2 class="ghl-modal-title">
				<span class="dashicons dashicons-info"></span>
				<?php esc_html_e( 'Sync Log Details', 'ghl-crm-integration' ); ?>
			</h2>
			<button type="button" id="ghl-close-modal" class="ghl-modal-close" aria-label="<?php esc_attr_e( 'Close', 'ghl-crm-integration' ); ?>">
				&times;
			</button>
		</div>
		<div class="ghl-modal-body">
			<pre id="ghl-details-content" class="ghl-modal-details"></pre>
		</div>
	</div>
</div>
