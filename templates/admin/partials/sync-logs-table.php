<?php
/**
 * Template Partial: Sync Logs Table
 *
 * This partial is used for AJAX loading of sync logs.
 *
 * @package GHL_CRM_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Variables passed from AJAX handler: $logs, $page, $per_page, $offset, $log_count, $total_pages
?>

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
							<p class="ghl-logs-empty-text"><?php esc_html_e( 'No logs match your current filters.', 'ghl-crm-integration' ); ?></p>
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
				$current_page = $page;
				$range = 2;
				$start_page = max( 1, $current_page - $range );
				$end_page = min( $total_pages, $current_page + $range );

				// Previous button
				if ( $current_page > 1 ) {
					echo '<a href="#" class="ghl-pagination-link" data-page="' . ( $current_page - 1 ) . '">←</a>';
				}

				// First page
				if ( $start_page > 1 ) {
					echo '<a href="#" class="ghl-pagination-link" data-page="1">1</a>';
					if ( $start_page > 2 ) {
						echo '<span class="ghl-pagination-link disabled">...</span>';
					}
				}

				// Page numbers
				for ( $i = $start_page; $i <= $end_page; $i++ ) {
					$class = $i === $current_page ? 'ghl-pagination-link active' : 'ghl-pagination-link';
					echo '<a href="#" class="' . esc_attr( $class ) . '" data-page="' . $i . '">' . $i . '</a>';
				}

				// Last page
				if ( $end_page < $total_pages ) {
					if ( $end_page < $total_pages - 1 ) {
						echo '<span class="ghl-pagination-link disabled">...</span>';
					}
					echo '<a href="#" class="ghl-pagination-link" data-page="' . $total_pages . '">' . $total_pages . '</a>';
				}

				// Next button
				if ( $current_page < $total_pages ) {
					echo '<a href="#" class="ghl-pagination-link" data-page="' . ( $current_page + 1 ) . '">→</a>';
				}
				?>
			</div>
		</div>
	<?php endif; ?>
</div>
