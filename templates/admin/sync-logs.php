<?php
/**
 * Template: Sync Logs Page
 *
 * @package Syncly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Check connection status
$settings_manager = \Syncly\Core\SettingsManager::get_instance();
$settings         = $settings_manager->get_settings_array();
$oauth_handler    = new \Syncly\API\OAuth\OAuthHandler();
$oauth_status     = $oauth_handler->get_connection_status();
$is_connected     = $oauth_status['connected'] || ! empty( $settings['api_token'] );

// Check if logging is enabled
$is_logging_enabled = \Syncly\Core\SettingsManager::is_sync_logging_enabled();
$is_pro_active      = (bool) apply_filters( 'syncly_is_pro_active', false );

// Get per-page preference from user meta (default 20)
$current_user_id = get_current_user_id();
$logs_per_page   = (int) get_user_meta( $current_user_id, 'ghl_sync_logs_per_page', true );
if ( ! $logs_per_page || $logs_per_page < 1 ) {
	$logs_per_page = 20;
}

// Get current page from request (pagination only affects view state)
$raw_page     = filter_input( INPUT_GET, 'paged', FILTER_SANITIZE_NUMBER_INT );
$current_page = $raw_page ? max( 1, (int) $raw_page ) : 1;
$offset       = ( $current_page - 1 ) * $logs_per_page;

// Get logs for site ID 1
$sync_logger = \Syncly\Sync\SyncLogger::get_instance();
$logs        = $sync_logger->get_logs(
	[
		'limit'   => $logs_per_page,
		'offset'  => $offset,
		'site_id' => get_current_blog_id(),
	]
);
// Count pending queue and total logs
global $wpdb;
$site_id = get_current_blog_id();
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Counts rely on custom plugin tables and are safe without additional caching.
$queue_count = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->prefix}ghl_sync_queue WHERE status = %s AND site_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		'pending',
		$site_id
	)
);
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Counts rely on custom plugin tables and are safe without additional caching.
$log_count   = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->prefix}ghl_sync_log WHERE site_id = %d",
		$site_id
	)
);
$total_pages = ceil( $log_count / $logs_per_page );
?>

<div class="wrap syncly-sync-logs">


	<?php if ( ! $is_connected ) : ?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'Not Connected', 'syncly' ); ?></strong><br>
				<?php
				printf(
					/* translators: %s: Link to dashboard page */
					esc_html__( 'Please connect to GoHighLevel in %s first.', 'syncly' ),
					sprintf(
						'<a href="%s">%s</a>',
						esc_url( admin_url( 'admin.php?page=syncly-admin' ) ),
						esc_html__( 'Dashboard', 'syncly' )
					)
				);
				?>
			</p>
		</div>
		<?php return; ?>
	<?php endif; ?>

	<!-- Status Info -->
	<div style="margin-bottom: 20px; padding: 12px 16px; background: #f0f0f1; border-left: 4px solid <?php echo $is_logging_enabled ? '#46b450' : '#dc3232'; ?>; font-size: 13px;">
		<strong><?php esc_html_e( 'Logging:', 'syncly' ); ?></strong> 
		<?php if ( $is_logging_enabled ) : ?>
			<span style="color: #46b450;"><?php esc_html_e( 'Enabled', 'syncly' ); ?></span>
		<?php else : ?>
			<span style="color: #dc3232;"><?php esc_html_e( 'Disabled', 'syncly' ); ?></span>
			<span style="color: #666;"> — 
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=syncly-admin&tab=advanced#advanced' ) ); ?>"><?php esc_html_e( 'Enable in Settings', 'syncly' ); ?></a>
			</span>
		<?php endif; ?>
		<span style="margin-left: 20px; color: #666;">
			<?php
			/* translators: %s: formatted number of log entries */
			printf( esc_html__( 'Total Logs: %s', 'syncly' ), '<strong>' . number_format( $log_count ) . '</strong>' );
			?>
		</span>
		<?php if ( $queue_count > 0 ) : ?>
			<span style="margin-left: 20px; color: #f0b849;">
				<?php
				/* translators: %s: formatted number of queue items */
				printf( esc_html__( 'Queue: %s pending', 'syncly' ), '<strong>' . number_format( $queue_count ) . '</strong>' );
				?>
			</span>
		<?php endif; ?>
	</div>

	<!-- Filters and Actions -->
	<div class="ghl-logs-filters">
		<div class="ghl-filters-row">
			<div class="ghl-filter-group">
				<label for="ghl-filter-status"><?php esc_html_e( 'Filter by Status', 'syncly' ); ?></label>
				<select id="ghl-filter-status">
					<option value=""><?php esc_html_e( 'All Statuses', 'syncly' ); ?></option>
					<option value="success"><?php esc_html_e( 'Success', 'syncly' ); ?></option>
					<option value="failed"><?php esc_html_e( 'Error', 'syncly' ); ?></option>
					<option value="pending"><?php esc_html_e( 'Pending', 'syncly' ); ?></option>
				</select>
			</div>

			<div class="ghl-filter-group">
				<label for="ghl-per-page"><?php esc_html_e( 'Per Page', 'syncly' ); ?></label>
				<select id="ghl-per-page">
					<option value="10" <?php selected( $logs_per_page, 10 ); ?>>10</option>
					<option value="20" <?php selected( $logs_per_page, 20 ); ?>>20</option>
					<option value="50" <?php selected( $logs_per_page, 50 ); ?>>50</option>
					<option value="100" <?php selected( $logs_per_page, 100 ); ?>>100</option>
					<option value="200" <?php selected( $logs_per_page, 200 ); ?>>200</option>
				</select>
			</div>

			<div class="ghl-filter-group">
				<label for="ghl-search-logs"><?php esc_html_e( 'Search Logs', 'syncly' ); ?></label>
				<input type="text" id="ghl-search-logs" placeholder="<?php esc_attr_e( 'Search by action, message...', 'syncly' ); ?>">
			</div>
			<div class="ghl-actions-group">
				<button type="button" id="ghl-delete-logs" class="ghl-button ghl-button-secondary">
					<span class="dashicons dashicons-trash"></span>
					<span class="ghl-button-text"><?php esc_html_e( 'Delete Old Logs', 'syncly' ); ?></span>
				</button>
				<button type="button" id="ghl-clear-all-logs" class="ghl-button ghl-button-danger">
					<span class="dashicons dashicons-warning"></span>
					<span class="ghl-button-text"><?php esc_html_e( 'Clear All', 'syncly' ); ?></span>
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
						<th style="width: 180px;"><?php esc_html_e( 'Date', 'syncly' ); ?></th>
						<th style="width: 100px;"><?php esc_html_e( 'Type', 'syncly' ); ?></th>
						<th style="width: 80px;"><?php esc_html_e( 'Item ID', 'syncly' ); ?></th>
						<th><?php esc_html_e( 'Action', 'syncly' ); ?></th>
						<th style="width: 100px;"><?php esc_html_e( 'Status', 'syncly' ); ?></th>
						<th style="width: 120px;"><?php esc_html_e( 'Details', 'syncly' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! empty( $logs ) ) : ?>
						<?php foreach ( $logs as $log ) : ?>
							<?php
							// Parse metadata if it's a JSON string
							$metadata = $log['metadata'] ?? null;
							if ( is_string( $metadata ) ) {
								$metadata = json_decode( $metadata, true );
							}

							$details_json = wp_json_encode(
								[
									'sync_type'  => $log['sync_type'] ?? '',
									'item_id'    => $log['item_id'] ?? '',
									'action'     => $log['action'] ?? '',
									'status'     => $log['status'] ?? '',
									'message'    => $log['message'] ?? '',
									'ghl_id'     => $log['ghl_id'] ?? '',
									'metadata'   => $metadata,
									'created_at' => $log['created_at'] ?? '',
								],
								JSON_PRETTY_PRINT
							);
							?>
							<tr>
								<td>
									<span class="ghl-log-date"><?php echo esc_html( $log['created_at'] ); ?></span>
								</td>
								<td>
									<span class="ghl-log-type">
										<?php
										$sync_type = $log['sync_type'] ?? 'unknown';
										$icon      = 'admin-users';
										if ( 'wc_customer' === $sync_type || 'order' === $sync_type ) {
											$icon = 'cart';
										} elseif ( 'contact' === $sync_type ) {
											$icon = 'id';
										}
										?>
										<span class="dashicons dashicons-<?php echo esc_attr( $icon ); ?>"></span>
										<?php echo esc_html( ucfirst( str_replace( '_', ' ', $sync_type ) ) ); ?>
									</span>
								</td>
								<td>
									<code style="font-size: 12px;"><?php echo esc_html( $log['item_id'] ?? 'N/A' ); ?></code>
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
									<?php
									$details_button = apply_filters( 'syncly_sync_log_details_button', '', $log, $details_json );

									if ( $is_pro_active && ! empty( $details_button ) ) {
										echo wp_kses_post( $details_button );
									} else {
										?>
										<button type="button" class="ghl-button ghl-button-small ghl-button-secondary ghl-view-details ghl-preview-mode">
											<span class="dashicons dashicons-lock"></span>
											<?php esc_html_e( 'Learn More', 'syncly' ); ?>
										</button>
										<?php
									}
									?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr>
							<td colspan="6">
								<div class="ghl-logs-empty">
									<div class="ghl-logs-empty-icon">
										<span class="dashicons dashicons-database-view"></span>
									</div>
									<h3 class="ghl-logs-empty-title"><?php esc_html_e( 'No Logs Found', 'syncly' ); ?></h3>
									<p class="ghl-logs-empty-text"><?php esc_html_e( 'Sync events will appear here once your integration starts processing data.', 'syncly' ); ?></p>
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
						$end   = min( $offset + $logs_per_page, $log_count );
						printf(
							/* translators: 1: Start number, 2: End number, 3: Total count */
							esc_html__( 'Showing %1$d-%2$d of %3$d logs', 'syncly' ),
							absint( $start ),
							absint( $end ),
							absint( $log_count )
						);
						?>
					</div>
					<div class="ghl-pagination-links">
						<?php
						$range      = 2;
						$start_page = max( 1, $current_page - $range );
						$end_page   = min( $total_pages, $current_page + $range );

						// Previous button
						if ( $current_page > 1 ) {
							$prev_page = $current_page - 1;
							echo '<a href="' . esc_url( add_query_arg( 'paged', $prev_page ) ) . '" class="ghl-pagination-link" data-page="' . esc_attr( (string) $prev_page ) . '">←</a>';
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
							echo '<a href="' . esc_url( add_query_arg( 'paged', $i ) ) . '" class="' . esc_attr( $class ) . '" data-page="' . esc_attr( (string) $i ) . '">' . esc_html( (string) $i ) . '</a>';
						}

						// Last page
						if ( $end_page < $total_pages ) {
							if ( $end_page < $total_pages - 1 ) {
								echo '<span class="ghl-pagination-link disabled">...</span>';
							}
							echo '<a href="' . esc_url( add_query_arg( 'paged', $total_pages ) ) . '" class="ghl-pagination-link" data-page="' . esc_attr( (string) $total_pages ) . '">' . esc_html( (string) $total_pages ) . '</a>';
						}

						// Next button
						if ( $current_page < $total_pages ) {
							$next_page = $current_page + 1;
							echo '<a href="' . esc_url( add_query_arg( 'paged', $next_page ) ) . '" class="ghl-pagination-link" data-page="' . esc_attr( (string) $next_page ) . '">→</a>';
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
				<?php esc_html_e( 'Sync Log Details', 'syncly' ); ?>
			</h2>
			<button type="button" id="ghl-close-modal" class="ghl-modal-close" aria-label="<?php esc_attr_e( 'Close', 'syncly' ); ?>">
				&times;
			</button>
		</div>
		<div class="ghl-modal-body">
			<div id="ghl-details-content" class="ghl-modal-details"></div>
		</div>
	</div>
</div>

<?php
/**
 * Allow extensions to render additional modal content or override
 * Hook: syncly_sync_logs_after_content
 */
do_action( 'syncly_sync_logs_after_content' );
?>