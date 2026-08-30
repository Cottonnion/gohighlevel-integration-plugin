(function ($) {
    'use strict';

    const GHLWizard = {
        currentStep: 1,
        totalSteps: 7,
        settings: {},

        init: function () {
            // Load existing settings from localized data
            if (syncly_setup_wizard_js_data.settings) {
                this.settings = {
                    ui_mode: syncly_setup_wizard_js_data.settings.ui_mode || 'simple',
                    enable_user_sync: syncly_setup_wizard_js_data.settings.enable_user_sync,
                    user_register: syncly_setup_wizard_js_data.settings.user_register,
                    user_register_tags: syncly_setup_wizard_js_data.settings.user_register_tags || [],
                    woocommerce: syncly_setup_wizard_js_data.settings.woocommerce,
                    buddyboss: syncly_setup_wizard_js_data.settings.buddyboss,
                    learndash: syncly_setup_wizard_js_data.settings.learndash,
                    delete_contact_on_user_delete: syncly_setup_wizard_js_data.settings.delete_contact_on_user_delete,
                    enable_sync_logging: syncly_setup_wizard_js_data.settings.enable_sync_logging,
                    enable_telemetry_reporting: syncly_setup_wizard_js_data.settings.enable_telemetry_reporting
                };
            }

            this.initTagsSelect2();
            this.bindEvents();
            this.updateStepIndicators();

            // Jump straight to a step when the URL asks for one (used when the
            // OAuth flow bounces the user back to the wizard from the Connect step).
            const urlStep = parseInt(new URLSearchParams(window.location.search).get('step'), 10);
            if (urlStep >= 1 && urlStep <= this.totalSteps && urlStep !== this.currentStep) {
                this.currentStep = urlStep;
                this.showStep(this.currentStep);
            }
        },

        initTagsSelect2: function () {
            // Initialize Select2 for tags input
            const $tagsSelect = $('#wizard_user_register_tags');
            const $userRegisterCheckbox = $('#wizard_user_register');
            const $tagsSection = $('#wizard_user_register_tags_section');

            if ($tagsSelect.length === 0) {
                return;
            }

            // Toggle tags section when checkbox changes
            $userRegisterCheckbox.on('change', function () {
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

        loadTags: function () {
            const $tagsSelect = $('#wizard_user_register_tags');
            const savedTags = syncly_setup_wizard_js_data.settings.user_register_tags || [];
            var tags = syncly_setup_wizard_js_data.tags || [];

            $tagsSelect.empty();

            if (tags.length === 0) {
                $tagsSelect.append('<option value="">No tags found</option>');
            } else {
                tags.forEach((tag) => {
                    const tagValue = String(tag.name || tag.id || '');
                    if (!tagValue) {
                        return;
                    }
                    const isSelected = savedTags.includes(tagValue);
                    $tagsSelect.append(
                        $('<option></option>')
                            .attr('value', tagValue)
                            .text(tagValue)
                            .prop('selected', isSelected)
                    );
                });
            }

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
        },

        bindEvents: function () {
            $('.ghl-wizard-next').on('click', () => this.nextStep());
            $('.ghl-wizard-prev').on('click', () => this.prevStep());
            $('.ghl-wizard-finish').on('click', () => this.finish());

            // Connection tab switching
            $('.ghl-tab-button').on('click', function () {
                const tab = $(this).data('tab');
                $('.ghl-tab-button').removeClass('active');
                $(this).addClass('active');
                $('.ghl-tab-content').removeClass('active');
                $('#' + tab + '-tab').addClass('active');
            });

            // Change Connection collapse toggle
            $(document).on('click', '#ghl-wizard-change-connection', function () {
                const $trigger = $(this);
                const $content = $('#ghl-wizard-connection-options');

                $trigger.toggleClass('active');
                $content.slideToggle(300);
            });

            // View mode selector cards
            $('.ghl-mode-card').on('click', function () {
                const $card = $(this);
                $card.find('input[type="radio"]').prop('checked', true);
                $('.ghl-mode-card').removeClass('selected');
                $card.addClass('selected');
            });

        },

        nextStep: function () {
            // Connection is the last configuration step; it is never required to
            // move through the wizard — the user can connect later from the dashboard.
            this.collectCurrentStepData();

            if (this.currentStep < this.totalSteps) {
                this.currentStep++;
                this.showStep(this.currentStep);
            }
        },

        prevStep: function () {
            if (this.currentStep > 1) {
                this.currentStep--;
                this.showStep(this.currentStep);
            }
        },

        showStep: function (step) {
            // Update panels
            $('.ghl-wizard-panel').removeClass('active');
            $(`.ghl-wizard-panel[data-step="${step}"]`).addClass('active');

            // Update step indicators
            this.updateStepIndicators();

            // Scroll to top
            window.scrollTo({ top: 0, behavior: 'smooth' });

            if (step === this.totalSteps) {
                this.celebrate();
            }
        },

        updateStepIndicators: function () {
            $('.ghl-setup-step').each((index, el) => {
                const stepNum = index + 1;
                const $number = $(el).find('.ghl-step-number');

                if (stepNum < this.currentStep) {
                    $(el).addClass('completed').removeClass('active');
                    if (!$number.data('ghl-checked')) {
                        $number.data('ghl-checked', true).html('<span class="dashicons dashicons-yes-alt"></span>');
                    }
                } else if (stepNum === this.currentStep) {
                    $(el).addClass('active').removeClass('completed');
                    $number.data('ghl-checked', false).html(stepNum);
                } else {
                    $(el).removeClass('active completed');
                    $number.data('ghl-checked', false).html(stepNum);
                }
            });
        },

        collectCurrentStepData: function () {
            switch (this.currentStep) {
                case 2:
                    this.settings.ui_mode = $('input[name="wizard_ui_mode"]:checked').val() || 'simple';
                    break;
                case 4:
                    this.settings.enable_user_sync = $('#wizard_enable_user_sync').is(':checked');
                    this.settings.user_register = $('#wizard_user_register').is(':checked');
                    this.settings.user_register_tags = $('#wizard_user_register_tags').val() || [];
                    break;
                case 5:
                    this.settings.woocommerce = $('#wizard_woocommerce').is(':checked');
                    this.settings.buddyboss = $('#wizard_buddyboss').is(':checked');
                    this.settings.learndash = $('#wizard_learndash').is(':checked');
                    break;
                case 6:
                    this.settings.delete_contact_on_user_delete = $('#wizard_delete_contact_on_user_delete').is(':checked');
                    this.settings.enable_sync_logging = $('#wizard_enable_sync_logging').is(':checked');
                    this.settings.enable_telemetry_reporting = $('#wizard_enable_telemetry_reporting').is(':checked');
                    break;
            }
        },

        isConnected: function () {
            return syncly_setup_wizard_js_data.isConnected === '1';
        },

        finish: function () {
            const button = $('.ghl-wizard-finish');
            const originalText = button.html();
            button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Saving...');

            $.ajax({
                url: syncly_setup_wizard_js_data.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'syncly_save_wizard_settings',
                    nonce: syncly_setup_wizard_js_data.nonce,
                    settings: this.settings
                },
                success: (response) => {
                    if (response.success) {
                        this.celebrate();
                        setTimeout(() => {
                            window.location.href = syncly_setup_wizard_js_data.dashboardUrl;
                        }, 1100);
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

        showError: function (message) {
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
        },

        celebrate: function () {
            const canvas = document.getElementById('ghl-confetti-canvas');
            if (!canvas || canvas.__ghlRunning) {
                return;
            }
            canvas.__ghlRunning = true;

            const ctx = canvas.getContext('2d');
            const dpr = window.devicePixelRatio || 1;
            const resize = () => {
                canvas.width = window.innerWidth * dpr;
                canvas.height = window.innerHeight * dpr;
                canvas.style.width = window.innerWidth + 'px';
                canvas.style.height = window.innerHeight + 'px';
                ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            };
            resize();
            canvas.style.display = 'block';

            const colors = ['#635bff', '#10b981', '#f59e0b', '#ef4444', '#3b82f6', '#ec4899'];
            const count = 220;
            const particles = Array.from({ length: count }, (_, i) => {
                // First wave falls from the top; remaining particles burst from bottom corners like cannons.
                const fromCorner = i % 3 !== 0;
                const originX = fromCorner ? (i % 2 === 0 ? 0 : window.innerWidth) : Math.random() * window.innerWidth;
                const originY = fromCorner ? window.innerHeight * 0.85 : -20 - Math.random() * window.innerHeight * 0.3;
                return {
                    x: originX,
                    y: originY,
                    size: 6 + Math.random() * 6,
                    color: colors[Math.floor(Math.random() * colors.length)],
                    speedY: fromCorner ? -(6 + Math.random() * 6) : 2 + Math.random() * 3,
                    speedX: fromCorner ? (originX === 0 ? 1 : -1) * (2 + Math.random() * 4) : (Math.random() - 0.5) * 2,
                    gravity: fromCorner ? 0.25 : 0,
                    rotation: Math.random() * 360,
                    rotationSpeed: (Math.random() - 0.5) * 10
                };
            });

            const duration = 3200;
            const start = performance.now();

            function frame(now) {
                const elapsed = now - start;
                ctx.clearRect(0, 0, window.innerWidth, window.innerHeight);

                particles.forEach((p) => {
                    p.speedY += p.gravity;
                    p.x += p.speedX;
                    p.y += p.speedY;
                    p.rotation += p.rotationSpeed;

                    ctx.save();
                    ctx.translate(p.x, p.y);
                    ctx.rotate((p.rotation * Math.PI) / 180);
                    ctx.fillStyle = p.color;
                    ctx.fillRect(-p.size / 2, -p.size / 4, p.size, p.size / 2);
                    ctx.restore();
                });

                if (elapsed < duration) {
                    requestAnimationFrame(frame);
                } else {
                    ctx.clearRect(0, 0, window.innerWidth, window.innerHeight);
                    canvas.style.display = 'none';
                    canvas.__ghlRunning = false;
                }
            }

            requestAnimationFrame(frame);
        }
    };

    // Initialize when document is ready
    $(document).ready(function () {
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