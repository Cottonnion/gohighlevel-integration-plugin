/**
 * LearnDash Content Meta Box tag selectors (lessons, topics, quizzes)
 */
(function($) {
	'use strict';

	let thresholdIndex = 0;

	$(document).ready(function() {
		if (typeof $.fn.select2 === 'undefined' || typeof ghlLearnDashMetaBox === 'undefined') {
			return;
		}

		// Initialize the completion tags select based on content type
		const contentType = ghlLearnDashMetaBox.contentType || 'lesson';
		const selectId = '#ghl_ld_' + contentType + '_completed_tags';
		
		initializeSelect($(selectId));

		// Initialize quiz score threshold functionality
		if (contentType === 'quiz') {
			initializeScoreThresholds();
		}
	});

	/**
	 * Initialize score threshold UI for quizzes
	 */
	function initializeScoreThresholds() {
		// Initialize existing threshold selects
		$('.ghl-threshold-tags-select').each(function() {
			initializeSelect($(this));
		});

		// Set initial index based on existing rows
		thresholdIndex = $('.ghl-threshold-row').length;

		// Add new threshold row
		$(document).on('click', '#ghl-add-score-threshold', function(e) {
			e.preventDefault();
			addThresholdRow();
		});

		// Remove threshold row
		$(document).on('click', '.ghl-remove-threshold', function(e) {
			e.preventDefault();
			$(this).closest('.ghl-threshold-row').fadeOut(200, function() {
				$(this).remove();
			});
		});
	}

	/**
	 * Add a new score threshold row
	 */
	function addThresholdRow() {
		const $container = $('#ghl-quiz-score-thresholds');
		const newIndex = thresholdIndex++;

		const rowHtml = `
			<div class="ghl-threshold-row" data-index="${newIndex}">
				<div class="ghl-threshold-inputs">
					<div class="ghl-threshold-score-range">
						<label>
							<span>${ghlLearnDashMetaBox.i18n?.minScore || 'Min %'}</span>
							<input
								type="number"
								name="ghl_quiz_thresholds[${newIndex}][min_score]"
								class="ghl-threshold-min"
								min="0"
								max="100"
								step="1"
								placeholder="0"
							/>
						</label>
						<span class="ghl-threshold-separator">–</span>
						<label>
							<span>${ghlLearnDashMetaBox.i18n?.maxScore || 'Max %'}</span>
							<input
								type="number"
								name="ghl_quiz_thresholds[${newIndex}][max_score]"
								class="ghl-threshold-max"
								min="0"
								max="100"
								step="1"
								placeholder="100"
							/>
						</label>
					</div>
					<div class="ghl-threshold-tags">
						<select
							name="ghl_quiz_thresholds[${newIndex}][tags][]"
							class="ghl-tags-select ghl-threshold-tags-select"
							multiple
							data-context="quiz-threshold-${newIndex}"
							data-saved-tags="[]"
							data-placeholder="${ghlLearnDashMetaBox.i18n?.thresholdPlaceholder || 'Select tags for this score range…'}"
						>
							<option value="">${ghlLearnDashMetaBox.i18n?.loading || 'Loading tags…'}</option>
						</select>
					</div>
				</div>
				<button type="button" class="button button-link-delete ghl-remove-threshold" title="${ghlLearnDashMetaBox.i18n?.removeRange || 'Remove this range'}">
					<span class="dashicons dashicons-no-alt"></span>
				</button>
			</div>
		`;

		const $newRow = $(rowHtml);
		$container.append($newRow);

		// Initialize Select2 for the new tags select
		const $newSelect = $newRow.find('.ghl-threshold-tags-select');
		initializeSelect($newSelect);

		// Animate in
		$newRow.hide().fadeIn(200);
	}

	function initializeSelect($select) {
		if (!$select.length) {
			return;
		}

		const savedTags = $select.data('saved-tags') || [];
		const context = $select.data('context') || 'completion';
		const placeholder = $select.data('placeholder') || getPlaceholder(context);

		$select.select2({
			placeholder,
			allowClear: true,
			closeOnSelect: false,
			width: '100%',
			scrollAfterSelect: false,
			ajax: {
				url: ghlLearnDashMetaBox.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				delay: 200,
				data: function(params) {
					return {
						action: ghlLearnDashMetaBox.action,
						nonce: ghlLearnDashMetaBox.nonce,
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
		if (ghlLearnDashMetaBox.i18n && ghlLearnDashMetaBox.i18n.completePlaceholder) {
			return ghlLearnDashMetaBox.i18n.completePlaceholder;
		}

		return 'Select tags to apply after completion…';
	}
})(jQuery);
