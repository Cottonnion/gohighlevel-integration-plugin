/**
 * LearnDash Group Meta Box tag selectors
 */
(function($) {
	'use strict';

	$(document).ready(function() {
		if (typeof $.fn.select2 === 'undefined' || typeof ghlLearnDashGroupMetaBox === 'undefined') {
			return;
		}

		initializeSelect($('#ghl_ld_group_auto_enroll'));
		initializeSelect($('#ghl_ld_group_tags'));
	});

	function initializeSelect($select) {
		if (!$select.length) {
			return;
		}

		const savedTags = $select.data('saved-tags') || [];
		const context = $select.data('context') || 'auto-enroll';
		const placeholder = $select.data('placeholder') || getPlaceholder(context);

		$select.select2({
			placeholder,
			allowClear: true,
			closeOnSelect: false,
			width: '100%',
			scrollAfterSelect: false,
			ajax: {
				url: ghlLearnDashGroupMetaBox.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				delay: 200,
				data: function(params) {
					return {
						action: ghlLearnDashGroupMetaBox.action,
						nonce: ghlLearnDashGroupMetaBox.nonce,
						search: params.term || ''
					};
				},
				processResults: function(response) {
					if (!response.success || !response.data || !response.data.tags) {
						return { results: [] };
					}

					const items = response.data.tags.map(function(tag) {
						if (typeof tag === 'object' && tag !== null) {
							const label = String(tag.name || tag.id || '');
							return {
								id: label,
								text: label
							};
						}

						const value = String(tag || '');
						return {
							id: value,
							text: value
						};
					});

					return { results: items };
				},
				cache: true
			},
			minimumInputLength: 0
		});

		$select.on('select2:select select2:unselect', function() {
			const instance = $(this).data('select2');
			if (instance && instance.$dropdown) {
				const $results = instance.$dropdown.find('.select2-results__options');
				const scrollPos = $results.scrollTop();
				setTimeout(function() {
					$results.scrollTop(scrollPos);
				}, 1);
			}
		});

		if (Array.isArray(savedTags) && savedTags.length) {
			savedTags.forEach(function(tag) {
				const option = new Option(tag, tag, true, true);
				$select.append(option);
			});
			$select.trigger('change');
		} else {
			$select.empty();
		}
	}

	function getPlaceholder(context) {
		const i18n = ghlLearnDashGroupMetaBox.i18n || {};

		if (context === 'membership' && i18n.membershipPlaceholder) {
			return i18n.membershipPlaceholder;
		}

		return i18n.autoEnrollPlaceholder || ghlLearnDashGroupMetaBox.i18n.loading;
	}
})(jQuery);
