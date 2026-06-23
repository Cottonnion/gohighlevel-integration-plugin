/**
 * GHL Form Block
 * 
 * Gutenberg block for embedding GoHighLevel forms
 */

(function () {
	"use strict";

	/* Check if WordPress APIs are available */
	if (!window.wp || !window.wp.blocks || !window.wp.element) {
		return;
	}

	var el = window.wp.element.createElement;
	var Fragment = window.wp.element.Fragment;
	var useState = window.wp.element.useState;
	var useEffect = window.wp.element.useEffect;
	var blocks = window.wp.blocks;
	var blockEditor = window.wp.blockEditor || window.wp.editor;
	var components = window.wp.components;
	var i18n = window.wp.i18n;
	var __ = i18n.__;
	var apiFetch = window.wp.apiFetch;

	blocks.registerBlockType('syncly/form', {
		apiVersion: 2,
		title: __('GoHighLevel Form', 'syncly'),
		description: __('Embed a GoHighLevel form to capture leads directly on your site. Form settings can be configured in the Forms sub menu', 'syncly'),
		icon: 'forms',
		category: 'syncly',
		example: {
			attributes: {
				formId: 'preview',
				width: '100%',
				height: '500px'
			}
		},
		attributes: {
			formId: {
				type: 'string',
				default: ''
			},
			width: {
				type: 'string',
				default: '100%'
			},
			height: {
				type: 'string',
				default: 'auto'
			}
		},

		edit: function (props) {
			var attrs = props.attributes;
			var setAttrs = props.setAttributes;
			var isSelected = props.isSelected;

			var InspectorControls = blockEditor.InspectorControls;
			var useBlockProps = blockEditor.useBlockProps || (window.wp && window.wp.blockEditor && window.wp.blockEditor.useBlockProps);
			var PanelBody = components.PanelBody;
			var SelectControl = components.SelectControl;
			var TextControl = components.TextControl;
			var Placeholder = components.Placeholder;
			var Spinner = components.Spinner;
			var blockProps = useBlockProps ? useBlockProps() : {};

			var formsState = useState([{ label: __('Select a form...', 'syncly'), value: '' }]);
			var forms = formsState[0];
			var setForms = formsState[1];

			var loadingState = useState(true);
			var loading = loadingState[0];
			var setLoading = loadingState[1];

			var connectedState = useState(false);
			var connected = connectedState[0];
			var setConnected = connectedState[1];

			// Load forms function
			function loadForms() {
				setLoading(true);
				apiFetch({
					path: '/syncly/v1/forms',
				}).then(function (response) {
					var formOptions = [
						{ label: __('Select a form...', 'syncly'), value: '' }
					];

					if (response.forms && response.forms.length > 0) {
						response.forms.forEach(function (form) {
							formOptions.push({
								label: form.name || form.id,
								value: form.id,
							});
						});
					}

					setForms(formOptions);
					setLoading(false);
				}).catch(function () {
					setForms([{ label: __('Select a form...', 'syncly'), value: '' }]);
					setLoading(false);
				});
			}

			// Load forms on mount
			useEffect(function () {
				apiFetch({
					path: '/syncly/v1/connection/status',
				}).then(function (response) {
					setConnected(response.connected || false);
					if (response.connected) {
						loadForms();
					} else {
						setLoading(false);
					}
				}).catch(function () {
					setConnected(false);
					setLoading(false);
				});
			}, []);

			// Not connected placeholder
			if (!connected) {
				return el(Placeholder, {
					icon: 'forms',
					label: __('GoHighLevel Form', 'syncly'),
					instructions: __('Please connect to GoHighLevel in plugin settings to use forms.', 'syncly')
				},
					el('a', {
						href: '/wp-admin/admin.php?page=syncly-settings',
						className: 'button button-primary'
					}, __('Go to Settings', 'syncly'))
				);
			}

			// Loading state
			if (loading) {
				return el(Placeholder, {
					icon: 'forms',
					label: __('GoHighLevel Form', 'syncly')
				}, el(Spinner));
			}

			var inspector = el(InspectorControls, null,
				el(PanelBody, { title: __('Form Settings', 'syncly'), initialOpen: true },
					el(SelectControl, {
						label: __('Select Form', 'syncly'),
						value: attrs.formId,
						options: forms,
						onChange: function (v) { setAttrs({ formId: v }); }
					}),
					el(TextControl, {
						label: __('Width', 'syncly'),
						value: attrs.width,
						onChange: function (v) { setAttrs({ width: v }); },
						help: __('e.g., 100%, 600px', 'syncly')
					}),
					el(TextControl, {
						label: __('Height', 'syncly'),
						value: attrs.height,
						onChange: function (v) { setAttrs({ height: v }); },
						help: __('e.g., auto, 800px', 'syncly')
					})
				)
			);

			// No form selected
			if (!attrs.formId) {
				return el(Fragment, null,
					inspector,
					el(Placeholder, {
						icon: 'forms',
						label: __('GoHighLevel Form', 'syncly'),
						instructions: __('Select a form from the sidebar to get started.', 'syncly')
					})
				);
			}

			// Form selected - get form details
			var selectedForm = forms.find(function (f) { return f.value === attrs.formId; });
			var formName = selectedForm ? selectedForm.label : attrs.formId;

			// Get location ID and form URL from settings
			var ghlSettings = window.synclySettings || {};
			var locationId = ghlSettings.locationId || '';
			var formUrl = '';
			
			if (locationId && attrs.formId && attrs.formId !== 'preview') {
				formUrl = 'https://api.leadconnectorhq.com/widget/form/' + attrs.formId;
			}

			// Form iframe container
			var formContainer = el('div', Object.assign({}, blockProps, {
				className: (blockProps.className || '') + ' ghl-form-block-container',
				style: { 
					position: 'relative',
					width: attrs.width || '100%',
					minHeight: attrs.height === 'auto' ? '500px' : attrs.height,
					border: isSelected ? '2px solid #007cba' : '1px solid #ddd',
					borderRadius: '4px',
					overflow: 'hidden',
					background: '#fff'
				}
			}),
				// Form title bar
				el('div', {
					style: {
						padding: '12px 16px',
						background: '#f8f9fa',
						borderBottom: '1px solid #ddd',
						display: 'flex',
						alignItems: 'center',
						gap: '8px'
					}
				},
					el('span', { style: { fontSize: '18px' } }, '📋'),
					el('strong', null, formName),
					el('span', { 
						style: { 
							marginLeft: 'auto',
							fontSize: '11px',
							color: '#666',
							background: '#e3f2fd',
							padding: '4px 8px',
							borderRadius: '3px'
						}
					}, __('LIVE PREVIEW', 'syncly'))
				),
				// Form iframe wrapper with click overlay
				formUrl ? el('div', {
					style: {
						position: 'relative',
						width: '100%',
						height: attrs.height === 'auto' ? '500px' : attrs.height
					}
				},
					el('iframe', {
						src: formUrl,
						style: {
							width: '100%',
							height: '100%',
							border: 'none',
							display: 'block'
						},
						frameBorder: '0',
						allowFullScreen: true
					}),
					// Click overlay to allow block selection when not selected
					!isSelected && el('div', {
						style: {
							position: 'absolute',
							top: 0,
							left: 0,
							right: 0,
							bottom: 0,
							cursor: 'pointer',
							background: 'transparent'
						},
						onClick: function (e) {
							e.stopPropagation();
						}
					})
				) : el('div', {
					style: {
						padding: '40px 20px',
						textAlign: 'center',
						color: '#666',
						background: '#f9f9f9',
						minHeight: '200px'
					}
				},
					el('p', null, __('Form preview will be displayed here', 'syncly')),
					el('small', null, __('The actual form will load on the frontend', 'syncly'))
				)
			);

			return el(Fragment, null, inspector, formContainer);
		},

		save: function () {
			return null;
		}
	});

})();
