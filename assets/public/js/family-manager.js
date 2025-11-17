/**
 * Family Manager Frontend JavaScript
 *
 * @package GHL_CRM_Integration
 */

(function($) {
	'use strict';

	const familyManager = {
		currentParentId: null,
		isAdmin: false,
		i18n: {},
		ajaxUrl: '',
		nonce: '',

		init: function(config) {
			// Use config parameter or global localized config
			config = config || window.ghlFamilyManagerConfig || {};

			// Set configuration
			this.currentParentId = config.currentParentId;
			this.isAdmin = config.isAdmin;
			this.i18n = config.i18n || {};
			this.ajaxUrl = config.ajaxUrl || window.ghlCrmSettings?.ajaxUrl || '';
			this.nonce = config.nonce || window.ghlCrmSettings?.nonce || '';

			// Initialize
			this.bindEvents();
			
			// Load parent list for admins
			if (this.isAdmin) {
				this.loadAllParents();
			}
			
			this.loadChildren();
		},

		bindEvents: function() {
			// Link child form
			$('#ghl-link-child-form').on('submit', function(e) {
				e.preventDefault();
				familyManager.linkChild();
			});

			// Delegate for dynamic buttons
			$(document).on('click', '.ghl-unlink-child', function() {
				const childId = $(this).data('child-id');
				familyManager.unlinkChild(childId);
			});
			
			// Admin parent selector
			$('#ghl-parent-selector').on('change', function() {
				familyManager.currentParentId = $(this).val();
				familyManager.loadChildren();
			});
		},
		
		loadAllParents: function() {
			const familyManager = this;
			
			$.ajax({
				url: this.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ghl_crm_get_all_parents',
					nonce: this.nonce
				},
				success: function(response) {
					if (response.success && response.data.parents) {
						const $selector = $('#ghl-parent-selector');
						const currentUserId = $selector.find('option:first').val();
						
						// Clear existing options except first
						$selector.find('option:not(:first)').remove();
						
						// Add all parents
						response.data.parents.forEach(function(parent) {
							// Don't duplicate current user
							if (parent.id != currentUserId) {
								$selector.append(
									$('<option></option>')
										.val(parent.id)
										.text(parent.name + ' (' + parent.email + ')')
								);
							}
						});
					}
				}
			});
		},

		loadChildren: function() {
			$('.ghl-family-loading').show();
			$('.ghl-family-children-list').empty();

			$.ajax({
				url: this.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ghl_crm_get_children',
					nonce: this.nonce,
					parent_id: this.currentParentId
				},
				success: function(response) {
					$('.ghl-family-loading').hide();
					if (response.success) {
						familyManager.renderChildren(response.data.children);
					}
				}
			});
		},

		renderChildren: function(children) {
			const familyManager = this;
			const $list = $('.ghl-family-children-list');
			
			if (children.length === 0) {
				$list.html('<div class="ghl-no-children"><p>' + this.i18n.noChildren + '</p></div>');
				return;
			}

			// Build table HTML
			let tableHTML = `
				<table class="ghl-family-children-table">
					<thead>
						<tr>
							<th>Name</th>
							<th>Email</th>
							<th>Linked Date</th>
							<th>Status</th>
							<th>Actions</th>
						</tr>
					</thead>
					<tbody>
			`;

			children.forEach(function(child) {
				const statusClass = child.status === 'invited' ? 'invited' : 'active';
				const statusText = child.status === 'invited' ? 'Pending' : 'Active';
				const linkedDate = child.linked_date ? new Date(child.linked_date.replace(/-/g, '/')).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '-';
				
				tableHTML += `
					<tr data-child-id="${child.id}">
						<td class="ghl-child-name">${child.name}</td>
						<td class="ghl-child-email">${child.email}</td>
						<td class="ghl-child-date">${linkedDate}</td>
						<td>
							<span class="ghl-badge ${statusClass}">${statusText}</span>
						</td>
						<td>
							<a href="#" class="ghl-action-link ghl-unlink-child" data-child-id="${child.id}">
								${familyManager.i18n.unlink}
							</a>
						</td>
					</tr>
				`;
			});

			tableHTML += `
					</tbody>
				</table>
			`;

			$list.html(tableHTML);
		},

		linkChild: function() {
			const identifier = $('#child-identifier').val().trim();
			
			if (!identifier) {
				Swal.fire({
					icon: 'error',
					title: this.i18n.error || 'Error',
					text: this.i18n.emptyIdentifier || 'Please enter an email or username.'
				});
				return;
			}

			// Show loading
			Swal.fire({
				title: this.i18n.searching || 'Searching...',
				text: this.i18n.searchingText || 'Looking up user information',
				allowOutsideClick: false,
				allowEscapeKey: false,
				didOpen: () => {
					Swal.showLoading();
				}
			});

			// First, search for the user
			$.ajax({
				url: this.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ghl_crm_search_user',
					nonce: this.nonce,
					identifier: identifier,
					parent_id: this.currentParentId
				},
				success: function(response) {
					if (response.success) {
						familyManager.handleSearchResult(response.data, identifier);
					} else {
						Swal.fire({
							icon: 'error',
							title: familyManager.i18n.error || 'Error',
							text: response.data.message
						});
					}
				},
				error: function() {
					Swal.fire({
						icon: 'error',
						title: familyManager.i18n.error || 'Error',
						text: familyManager.i18n.ajaxError || 'An error occurred. Please try again.'
					});
				}
			});
		},

		handleSearchResult: function(result, identifier) {
			// Invalid identifier
			if (result.status === 'invalid') {
				Swal.fire({
					icon: 'error',
					title: this.i18n.error || 'Error',
					text: result.message,
					confirmButtonText: this.i18n.ok || 'OK'
				});
				return;
			}

			// User already this parent's child
			if (result.status === 'already_child') {
				Swal.fire({
					icon: 'info',
					title: this.i18n.alreadyLinked || 'Already Linked',
					text: result.message,
					confirmButtonText: this.i18n.ok || 'OK'
				});
				return;
			}

			// User has another parent
			if (result.status === 'has_parent') {
				Swal.fire({
					icon: 'warning',
					title: this.i18n.cannotLink || 'Cannot Link',
					text: result.message,
					confirmButtonText: this.i18n.ok || 'OK'
				});
				return;
			}

			// User doesn't exist - offer to create and invite
			if (result.status === 'not_found') {
				Swal.fire({
					icon: 'question',
					title: this.i18n.userNotFound || 'User Not Found',
					html: result.message,
					showCancelButton: true,
					confirmButtonText: result.confirm_text,
					cancelButtonText: this.i18n.cancel || 'Cancel',
					confirmButtonColor: '#3085d6',
					cancelButtonColor: '#d33'
				}).then((confirmResult) => {
					if (confirmResult.isConfirmed) {
						familyManager.sendInvite(identifier, null);
					}
				});
				return;
			}

			// User exists and available - confirm invite
			if (result.status === 'available') {
				Swal.fire({
					icon: 'question',
					title: this.i18n.confirmInvite || 'Send Invitation',
					html: result.message,
					showCancelButton: true,
					confirmButtonText: result.confirm_text,
					cancelButtonText: this.i18n.cancel || 'Cancel',
					confirmButtonColor: '#3085d6',
					cancelButtonColor: '#d33'
				}).then((confirmResult) => {
					if (confirmResult.isConfirmed) {
						familyManager.sendInvite(identifier, result.user.id);
					}
				});
			}
		},

		sendInvite: function(identifier, userId) {
			// Show loading
			Swal.fire({
				title: this.i18n.sendingInvite || 'Sending Invitation...',
				text: this.i18n.sendingInviteText || 'Creating account and sending email',
				allowOutsideClick: false,
				allowEscapeKey: false,
				didOpen: () => {
					Swal.showLoading();
				}
			});

			$.ajax({
				url: this.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ghl_crm_link_child',
					nonce: this.nonce,
					child_identifier: identifier,
					user_id: userId,
					parent_id: this.currentParentId
				},
				success: function(response) {
					if (response.success) {
						Swal.fire({
							icon: 'success',
							title: familyManager.i18n.success || 'Success!',
							html: response.data.message + '<br><br>' + (familyManager.i18n.inviteSentMessage || 'An invitation email has been sent.'),
							timer: 3000
						}).then(() => {
							$('#child-identifier').val('');
							familyManager.loadChildren();
						});
					} else {
						Swal.fire({
							icon: 'error',
							title: familyManager.i18n.error || 'Error',
							text: response.data.message
						});
					}
				},
				error: function() {
					Swal.fire({
						icon: 'error',
						title: familyManager.i18n.error || 'Error',
						text: familyManager.i18n.ajaxError || 'An error occurred. Please try again.'
					});
				}
			});
		},

		unlinkChild: function(childId) {
			Swal.fire({
				icon: 'warning',
				title: this.i18n.confirmUnlinkTitle || 'Unlink Child?',
				text: this.i18n.confirmUnlink,
				showCancelButton: true,
				confirmButtonText: this.i18n.yesUnlink || 'Yes, Unlink',
				cancelButtonText: this.i18n.cancel || 'Cancel',
				confirmButtonColor: '#d33',
				cancelButtonColor: '#3085d6'
			}).then((result) => {
				if (result.isConfirmed) {
					Swal.fire({
						title: this.i18n.unlinking || 'Unlinking...',
						allowOutsideClick: false,
						allowEscapeKey: false,
						didOpen: () => {
							Swal.showLoading();
						}
					});

					$.ajax({
						url: familyManager.ajaxUrl,
						type: 'POST',
						data: {
							action: 'ghl_crm_unlink_child',
							nonce: familyManager.nonce,
							child_id: childId,
							parent_id: familyManager.currentParentId
						},
						success: function(response) {
							if (response.success) {
								Swal.fire({
									icon: 'success',
									title: familyManager.i18n.success || 'Success!',
									text: familyManager.i18n.childUnlinked || 'Child unlinked successfully',
									timer: 2000
								}).then(() => {
									familyManager.loadChildren();
								});
							} else {
								Swal.fire({
									icon: 'error',
									title: familyManager.i18n.error || 'Error',
									text: response.data.message
								});
							}
						}
					});
				}
			});
		}
	};

	// Expose to global scope
	window.GHL_FamilyManager = familyManager;

	// Auto-initialize when document is ready if config exists
	$(document).ready(function() {
		if (typeof window.ghlFamilyManagerConfig !== 'undefined') {
			familyManager.init(window.ghlFamilyManagerConfig);
		}
	});

})(jQuery);
