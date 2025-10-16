<?php
/**
 * Template: Sync Logs Page
 *
 * @package GHL_CRM_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap ghl-crm-sync-logs">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	
	<div class="notice notice-info">
		<p>
			<?php esc_html_e( 'View synchronization history, monitor sync status, and troubleshoot errors.', 'ghl-crm-integration' ); ?>
		</p>
	</div>

	<div class="ghl-crm-logs-container">
		<div class="tablenav top">
			<div class="alignleft actions">
				<select name="sync_type" id="sync-type-filter">
					<option value=""><?php esc_html_e( 'All Sync Types', 'ghl-crm-integration' ); ?></option>
					<option value="contacts"><?php esc_html_e( 'Contacts', 'ghl-crm-integration' ); ?></option>
					<option value="orders"><?php esc_html_e( 'Orders', 'ghl-crm-integration' ); ?></option>
					<option value="users"><?php esc_html_e( 'Users', 'ghl-crm-integration' ); ?></option>
					<option value="groups"><?php esc_html_e( 'Groups', 'ghl-crm-integration' ); ?></option>
				</select>
				
				<select name="sync_status" id="sync-status-filter">
					<option value=""><?php esc_html_e( 'All Statuses', 'ghl-crm-integration' ); ?></option>
					<option value="success"><?php esc_html_e( 'Success', 'ghl-crm-integration' ); ?></option>
					<option value="failed"><?php esc_html_e( 'Failed', 'ghl-crm-integration' ); ?></option>
					<option value="pending"><?php esc_html_e( 'Pending', 'ghl-crm-integration' ); ?></option>
				</select>
				
				<button type="button" class="button" id="filter-logs">
					<?php esc_html_e( 'Filter', 'ghl-crm-integration' ); ?>
				</button>
				
				<button type="button" class="button" id="clear-logs">
					<?php esc_html_e( 'Clear Logs', 'ghl-crm-integration' ); ?>
				</button>
			</div>
		</div>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th scope="col" class="column-date"><?php esc_html_e( 'Date', 'ghl-crm-integration' ); ?></th>
					<th scope="col" class="column-type"><?php esc_html_e( 'Sync Type', 'ghl-crm-integration' ); ?></th>
					<th scope="col" class="column-status"><?php esc_html_e( 'Status', 'ghl-crm-integration' ); ?></th>
					<th scope="col" class="column-message"><?php esc_html_e( 'Message', 'ghl-crm-integration' ); ?></th>
					<th scope="col" class="column-actions"><?php esc_html_e( 'Actions', 'ghl-crm-integration' ); ?></th>
				</tr>
			</thead>
			<tbody id="sync-logs-table-body">
				<tr class="no-items">
					<td colspan="5" class="colspanchange">
						<?php esc_html_e( 'No sync logs found. Logs will appear here once synchronization starts.', 'ghl-crm-integration' ); ?>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
</div>
