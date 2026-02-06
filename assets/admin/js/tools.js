/**
 * Tools Page JavaScript
 *
 * Handles bulk sync all users functionality
 * Other tool operations (cache, reset, export, import, health check) are handled by settings.js
 *
 * @package GHL_CRM_Integration
 */

(function ($) {
	'use strict';

	/**
	 * Bulk Sync Users Handler
	 */
	const BulkSyncHandler = {
		isRunning: false,
		totalQueued: 0,
		totalFailed: 0,

		/**
		 * Initialize bulk sync
		 */
		init() {
			$('#bulk-sync-users-btn').on('click', () => this.start());
		},

		/**
		 * Start bulk sync process
		 */
		start() {
			if (this.isRunning) {
				return;
			}

			// Show confirmation directly
			Swal.fire({
				title: 'Sync All Users?',
				html: '<p>This will queue all WordPress users for synchronization to GoHighLevel.</p><p style="color: #666; font-size: 0.9em;">Processing happens in batches of 50 users. Time required depends on total user count. You can monitor progress in real-time.</p>',
				icon: 'question',
				showCancelButton: true,
				confirmButtonText: 'Yes, Sync All Users',
				cancelButtonText: 'Cancel',
				confirmButtonColor: '#3085d6',
				cancelButtonColor: '#d33',
			}).then((result) => {
				if (result.isConfirmed) {
					this.totalQueued = 0;
					this.totalFailed = 0;
					this.processBatch(0);
				}
			});
		},

		/**
		 * Process a batch of users
		 *
		 * @param {number} batch - Batch number to process
		 */
		processBatch(batch) {
			this.isRunning = true;

			// Show progress UI
			$('#bulk-sync-progress').show();
			$('#bulk-sync-users-btn').prop('disabled', true);

			$.ajax({
				url: ghl_crm_tools_js_data.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ghl_crm_bulk_sync_users',
					nonce: ghl_crm_tools_js_data.nonce,
					batch: batch,
				},
				success: (response) => {
					if (response.success) {
						const data = response.data;

						// Update totals
						this.totalQueued += data.queued || 0;
						this.totalFailed += data.failed || 0;

						// Update progress bar
						const percentage = (data.processed / data.total) * 100;
						$('#bulk-sync-progress-bar').css('width', percentage + '%');

						// Update progress text
						$('#bulk-sync-progress-text').html(
							`<strong>${data.processed}</strong> of <strong>${data.total}</strong> users processed<br>` +
							`<span style="color: #46b450;">✓ ${this.totalQueued} queued</span> | ` +
							`<span style="color: ${this.totalFailed > 0 ? '#dc3232' : '#666'};">${this.totalFailed > 0 ? '✗' : ''} ${this.totalFailed} failed</span>`
						);

						// Continue with next batch if needed
						if (data.has_more) {
							this.processBatch(data.next_batch);
						} else {
							this.complete();
						}
					} else {
						this.error(response.data?.message || 'An error occurred');
					}
				},
				error: (xhr, status, error) => {
					this.error('Network error occurred. Please try again.');
				},
			});
		},

		/**
		 * Handle completion
		 */
		complete() {
			this.isRunning = false;
			$('#bulk-sync-users-btn').prop('disabled', false);

			// Hide progress after a delay
			setTimeout(() => {
				$('#bulk-sync-progress').fadeOut();
			}, 3000);

			// Show success message
			let message = `Successfully queued <strong>${this.totalQueued}</strong> users for synchronization!`;
			
			if (this.totalFailed > 0) {
				message += `<br><br><span style="color: #dc3232;">${this.totalFailed} users could not be queued.</span>`;
			}

			message += `<br><br><small>Synchronization will happen in the background. You can monitor progress in the Sync Logs tab.</small>`;

			Swal.fire({
				title: 'Bulk Sync Complete!',
				html: message,
				icon: this.totalFailed > 0 ? 'warning' : 'success',
				confirmButtonText: 'OK',
			});

			// Reset counters
			this.totalQueued = 0;
			this.totalFailed = 0;
		},

		/**
		 * Handle error
		 *
		 * @param {string} message - Error message
		 */
		error(message) {
			this.isRunning = false;
			$('#bulk-sync-users-btn').prop('disabled', false);
			$('#bulk-sync-progress').hide();

			Swal.fire({
				title: 'Error',
				text: message,
				icon: 'error',
				confirmButtonText: 'OK',
			});

			// Reset counters
			this.totalQueued = 0;
			this.totalFailed = 0;
		},
	};

	/**
	 * Initialize bulk sync handler
	 * Called from settings-menu.js when tools tab is loaded
	 */
	function initToolsHandlers() {
		BulkSyncHandler.init();
	}

	// Export for use in settings-menu.js
	window.initToolsHandlers = initToolsHandlers;

})(jQuery);
