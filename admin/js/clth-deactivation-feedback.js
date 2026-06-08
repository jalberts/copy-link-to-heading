/**
 * Deactivation Feedback for Copy Link to Heading
 */
(function ($) {
    'use strict';

    let deactivationUrl = '';

    $(document).ready(function () {

        // Intercept deactivation link focusing specifically on our plugin slug
        $(document).on('click', 'tr[data-slug="copy-link-to-heading"] .deactivate a, a[href*="action=deactivate"][href*="copy-link-to-heading"]', function (e) {
            e.preventDefault();
            deactivationUrl = $(this).attr('href');
            showFeedbackModal();
        });

        // Handle form submission
        $(document).on('submit', '#clth-deactivate-feedback-dialog-form', function (e) {
            e.preventDefault();

            const $form = $(this);
            const $submitBtn = $form.find('button[type="submit"]');
            const $errorDiv = $('.clth-api-error');

            // Clear previous errors
            $errorDiv.text('');

            // Check if reason is selected
            const selectedReason = $form.find('input[name="reason_key"]:checked').val();
            if (!selectedReason) {
                $errorDiv.text(clth_deactivation_data.i18n.select_reason);
                return;
            }

            // Ensure contact details are supplied if consent checkbox is checked
            const wantsContact = $form.find('input[name="contact_consent"]').is(':checked');
            const contactEmail = $form.find('input[name="contact_email"]').val();
            if (wantsContact && !contactEmail) {
                $errorDiv.text(clth_deactivation_data.i18n.provide_email);
                $form.find('input[name="contact_email"]').focus();
                return;
            }

            // Show loading state
            $submitBtn.prop('disabled', true).text(clth_deactivation_data.i18n.submitting);

            // Prepare action and nonce
            const formData = $form.serializeArray();
            formData.push({ name: 'action', value: 'clth_deactivate_feedback' });
            formData.push({ name: '_wpnonce', value: clth_deactivation_data.nonce });

            // Submit form data
            $.ajax({
                url: clth_deactivation_data.ajaxurl,
                type: 'POST',
                data: $.param(formData),
                success: function (response) {
                    if (response.success) {
                        $submitBtn.text(clth_deactivation_data.i18n.thank_you);
                        setTimeout(function () {
                            proceedWithDeactivation();
                        }, 1000);
                    } else {
                        $errorDiv.text(response.data || clth_deactivation_data.i18n.error);
                        $submitBtn.prop('disabled', false).text(clth_deactivation_data.i18n.submit_btn);
                    }
                },
                error: function (xhr) {
                    $errorDiv.text(clth_deactivation_data.i18n.network_error);
                    $submitBtn.prop('disabled', false).text(clth_deactivation_data.i18n.submit_btn);
                }
            });
        });

        // Handle skip button
        $(document).on('click', '.clth_skip_and_deactivate', function (e) {
            e.preventDefault();
            proceedWithDeactivation();
        });

        // Handle radio button changes for conditional textareas
        $(document).on('change', 'input[name="reason_key"]', function () {
            const selectedValue = $(this).val();

            // Hide all textarea fields first
            $('.clth-feedback-text').hide();

            // Show relevant textarea if it exists
            const $textarea = $('textarea[name="reason_' + selectedValue + '"]');
            if ($textarea.length) {
                $textarea.show().focus();
            }
        });

        // Handle the contact checkbox toggling the email input
        $(document).on('change', '#clth-contact-consent', function () {
            const $container = $('#clth-email-field-container');
            if ($(this).is(':checked')) {
                $container.show();
            } else {
                $container.hide();
            }
        });

        // Close modal when clicking outside
        $(document).on('click', '.clth-deactivate-feedback-dialog-wrapper', function (e) {
            if (e.target === this) {
                closeFeedbackModal();
            }
        });

        // Close button click
        $(document).on('click', '.clth-close-btn', function (e) {
            e.preventDefault();
            closeFeedbackModal();
        });

        // Close on escape key
        $(document).on('keydown', function (e) {
            if (e.keyCode === 27 && $('.clth-deactivate-feedback-dialog-wrapper').hasClass('show')) {
                closeFeedbackModal();
            }
        });
    });

    function closeFeedbackModal() {
        $('#clth-deactivate-feedback-dialog-wrapper').removeClass('show');
    }

    function showFeedbackModal() {
        const $modal = $('#clth-deactivate-feedback-dialog-wrapper');

        if ($modal.length === 0) {
            console.error('Modal element not found in DOM');
            proceedWithDeactivation();
            return;
        }

        $modal.addClass('show');
    }

    function proceedWithDeactivation() {
        $('#clth-deactivate-feedback-dialog-wrapper').removeClass('show');

        if (deactivationUrl) {
            window.location.href = deactivationUrl;
        } else {
            console.error('No deactivation URL found');
            alert('Deactivation URL not found. Please deactivate manually.');
        }
    }

})(jQuery);