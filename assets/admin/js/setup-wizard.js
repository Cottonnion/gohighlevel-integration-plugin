(function($) {
    'use strict';

    const GHLWizard = {
        currentStep: 1,
        totalSteps: 6,
        settings: {},

        init: function() {
            // Load existing settings from localized data
            if (ghl_crm_setup_wizard_js_data.settings) {
                this.settings = {
                    enable_user_sync: ghl_crm_setup_wizard_js_data.settings.enable_user_sync,
                    user_register: ghl_crm_setup_wizard_js_data.settings.user_register,
                    user_register_tags: ghl_crm_setup_wizard_js_data.settings.user_register_tags || [],
                    woocommerce: ghl_crm_setup_wizard_js_data.settings.woocommerce,
                    buddyboss: ghl_crm_setup_wizard_js_data.settings.buddyboss,
                    learndash: ghl_crm_setup_wizard_js_data.settings.learndash,
                    delete_contact_on_user_delete: ghl_crm_setup_wizard_js_data.settings.delete_contact_on_user_delete,
                    enable_sync_logging: ghl_crm_setup_wizard_js_data.settings.enable_sync_logging,
                    enable_role_tags: ghl_crm_setup_wizard_js_data.settings.enable_role_tags
                };
            }
            
            this.initTagsSelect2();
            this.bindEvents();
            this.updateStepIndicators();
        },

        initTagsSelect2: function() {
            // Initialize Select2 for tags input
            const $tagsSelect = $('#wizard_user_register_tags');
            const $userRegisterCheckbox = $('#wizard_user_register');
            const $tagsSection = $('#wizard_user_register_tags_section');
            
            if ($tagsSelect.length === 0) {
                return;
            }
            
            // Toggle tags section when checkbox changes
            $userRegisterCheckbox.on('change', function() {
                if ($(this).is(':checked')) {
                    $tagsSection.slideDown(300);
                    // Load tags if not already loaded
                    if ($tagsSelect.find('option').length <= 1) {
                        GHLWizard.loadTags();
                    }
                } else {
                    $tagsSection.slideUp(300);
                }
            });
            
            // Load tags on init if checkbox is already checked
            if ($userRegisterCheckbox.is(':checked')) {
                this.loadTags();
            }
        },

        loadTags: function() {
            const $tagsSelect = $('#wizard_user_register_tags');
            const savedTags = ghl_crm_setup_wizard_js_data.settings.user_register_tags || [];
            
            // Show loading state
            $tagsSelect.html('<option value="">Loading tags...</option>').prop('disabled', true);
            
            $.ajax({
                url: ghl_crm_setup_wizard_js_data.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ghl_crm_get_tags',
                    nonce: ghl_crm_setup_wizard_js_data.nonce
                },
                success: (response) => {
                    if (response.success && response.data.tags) {
                        const tags = response.data.tags;
                        $tagsSelect.empty();
                        
                        if (tags.length === 0) {
                            $tagsSelect.append('<option value="">No tags found in your GoHighLevel location</option>');
                        } else {
                            // Add each tag as an option
                            tags.forEach((tag) => {
                                const tagValue = tag.name || tag;
                                const tagLabel = tag.name || tag;
                                const isSelected = savedTags.includes(tagValue);
                                $tagsSelect.append(
                                    $('<option></option>')
                                        .attr('value', tagValue)
                                        .text(tagLabel)
                                        .prop('selected', isSelected)
                                );
                            });
                        }
                        
                        $tagsSelect.prop('disabled', false);
                        
                        // Initialize Select2 if available
                        if (typeof $.fn.select2 !== 'undefined') {
                            $tagsSelect.select2({
                                tags: true,
                                tokenSeparators: [','],
                                placeholder: 'Select tags to apply on user registration',
                                allowClear: true,
                                width: '100%',
                                closeOnSelect: false,
                                scrollAfterSelect: false
                            });
                        }
                    } else {
                        $tagsSelect.html('<option value="">Failed to load tags</option>');
                    }
                },
                error: (xhr, status, error) => {
                    $tagsSelect.html('<option value="">Error loading tags</option>').prop('disabled', false);
                }
            });
        },

        bindEvents: function() {
            $('.ghl-wizard-next').on('click', () => this.nextStep());
            $('.ghl-wizard-prev').on('click', () => this.prevStep());
            $('.ghl-wizard-finish').on('click', () => this.finish());
            
            // Connection tab switching
            $('.ghl-tab-button').on('click', function() {
                const tab = $(this).data('tab');
                $('.ghl-tab-button').removeClass('active');
                $(this).addClass('active');
                $('.ghl-tab-content').removeClass('active');
                $('#' + tab + '-tab').addClass('active');
            });
            
            // Change Connection collapse toggle
            $(document).on('click', '#ghl-wizard-change-connection', function() {
                const $trigger = $(this);
                const $content = $('#ghl-wizard-connection-options');
                
                $trigger.toggleClass('active');
                $content.slideToggle(300);
            });
            
            // Manual connection form
            this.initManualConnectionForm();
        },

        initManualConnectionForm: function() {
            const self = this;
            $('#ghl-manual-connection-form').on('submit', function(e) {
                e.preventDefault();
                
                const $form = $(this);
                const $submitBtn = $form.find('button[type="submit"]');
                const originalText = $submitBtn.html();
                
                // Disable button and show loading
                $submitBtn.prop('disabled', true).html(
                    '<span class="dashicons dashicons-update-alt" style="animation: rotation 1s infinite linear; margin-top: 3px;"></span> Connecting...'
                );
                
                // Get values
                const apiToken = $('#api_token').val().trim();
                const locationId = $('#location_id').val().trim();
                const nonce = $form.find('[name="ghl_manual_connect_nonce"]').val();
                
                // Build FormData
                const formData = new FormData();
                formData.append('action', 'ghl_crm_manual_connect');
                formData.append('ghl_manual_connect_nonce', nonce);
                formData.append('api_token', apiToken);
                formData.append('location_id', locationId);
                
                $.ajax({
                    url: ghl_crm_setup_wizard_js_data.ajaxUrl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            // Show success message
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Connected!',
                                    text: response.data.message || 'Successfully connected to GoHighLevel',
                                    confirmButtonColor: '#4F46E5'
                                }).then(() => {
                                    // Reload page to update connection status
                                    window.location.reload();
                                });
                            } else {
                                alert(response.data.message || 'Successfully connected!');
                                window.location.reload();
                            }
                        } else {
                            self.showError(response.data?.message || 'Connection failed. Please try again.');
                            $submitBtn.prop('disabled', false).html(originalText);
                        }
                    },
                    error: function(xhr, status, error) {
                        self.showError('Connection error: ' + error);
                        $submitBtn.prop('disabled', false).html(originalText);
                    }
                });
            });
        },

        nextStep: function() {
            // Check if on connection step and not connected
            if (this.currentStep === 2 && !this.isConnected()) {
                this.showError('Please connect to GoHighLevel first');
                return;
            }
            
            this.collectCurrentStepData();
            
            if (this.currentStep < this.totalSteps) {
                this.currentStep++;
                this.showStep(this.currentStep);
            }
        },

        prevStep: function() {
            if (this.currentStep > 1) {
                this.currentStep--;
                this.showStep(this.currentStep);
            }
        },

        showStep: function(step) {
            // Update panels
            $('.ghl-wizard-panel').removeClass('active');
            $(`.ghl-wizard-panel[data-step="${step}"]`).addClass('active');
            
            // Update step indicators
            this.updateStepIndicators();
            
            // Scroll to top
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },

        updateStepIndicators: function() {
            $('.ghl-setup-step').each((index, el) => {
                const stepNum = index + 1;
                if (stepNum < this.currentStep) {
                    $(el).addClass('completed').removeClass('active');
                } else if (stepNum === this.currentStep) {
                    $(el).addClass('active').removeClass('completed');
                } else {
                    $(el).removeClass('active completed');
                }
            });
        },

        collectCurrentStepData: function() {
            switch(this.currentStep) {
                case 3:
                    this.settings.enable_user_sync = $('#wizard_enable_user_sync').is(':checked');
                    this.settings.user_register = $('#wizard_user_register').is(':checked');
                    this.settings.user_register_tags = $('#wizard_user_register_tags').val() || [];
                    break;
                case 4:
                    this.settings.woocommerce = $('#wizard_woocommerce').is(':checked');
                    this.settings.buddyboss = $('#wizard_buddyboss').is(':checked');
                    this.settings.learndash = $('#wizard_learndash').is(':checked');
                    break;
                case 5:
                    this.settings.delete_contact_on_user_delete = $('#wizard_delete_contact_on_user_delete').is(':checked');
                    this.settings.enable_sync_logging = $('#wizard_enable_sync_logging').is(':checked');
                    this.settings.enable_role_tags = $('#wizard_enable_role_tags').is(':checked');
                    break;
            }
        },

        isConnected: function() {
            return ghl_crm_setup_wizard_js_data.isConnected === '1';
        },

        finish: function() {
            const button = $('.ghl-wizard-finish');
            const originalText = button.html();
            button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Saving...');

            $.ajax({
                url: ghl_crm_setup_wizard_js_data.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'ghl_crm_save_wizard_settings',
                    nonce: ghl_crm_setup_wizard_js_data.nonce,
                    settings: this.settings
                },
                success: (response) => {
                    if (response.success) {
                        window.location.href = ghl_crm_setup_wizard_js_data.dashboardUrl;
                    } else {
                        this.showError(response.data.message || 'Failed to save settings');
                        button.prop('disabled', false).html(originalText);
                    }
                },
                error: () => {
                    this.showError('Failed to save settings. Please try again.');
                    button.prop('disabled', false).html(originalText);
                }
            });
        },

        showError: function(message) {
            // Use SweetAlert2 if available
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Oops...',
                    text: message,
                    confirmButtonColor: '#4F46E5'
                });
            } else {
                alert(message);
            }
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        GHLWizard.init();
    });

    // Add spinning animation for loading states
    const style = document.createElement('style');
    style.textContent = `
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .spin { animation: spin 1s linear infinite; }
    `;
    document.head.appendChild(style);

})(jQuery);