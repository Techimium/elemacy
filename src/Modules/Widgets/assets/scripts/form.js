(function ($) {
    'use strict';

    const FormHandler = function ($scope, $) {
        const $form = $scope.find('.elemacy-form');
        const $messageContainer = $scope.find('.elemacy-message-container');
        const $submitButton = $form.find('button[type="submit"]');

        const clearFieldErrors = function () {
            $form.find('.elemacy-field-error').remove();
            $form.find('.elemacy-field-invalid').removeClass('elemacy-field-invalid');
            $form.find('[aria-invalid]').removeAttr('aria-invalid');
        };

        // Server-side validation errors arrive keyed by field index; each message
        // is already HTML-escaped by the server (same trust level as data.message).
        const showFieldErrors = function (errors) {
            Object.keys(errors).forEach(function (index) {
                const $input = $form
                    .find('[name="form_field[' + index + ']"], [name="form_field[' + index + '][]"]')
                    .first();

                if (!$input.length) {
                    return;
                }

                const $fieldGroup = $input.closest('.elemacy-field-group');

                $fieldGroup.addClass('elemacy-field-invalid');
                $input.attr('aria-invalid', 'true');
                $('<span class="elemacy-field-error" role="alert"></span>')
                    .html(errors[index])
                    .appendTo($fieldGroup);
            });
        };

        $form.on('submit', function (e) {
            e.preventDefault();

            clearFieldErrors();
            $messageContainer.fadeOut(200, function() {
                $(this).empty().show();
            });
            $submitButton.attr('disabled', 'disabled').addClass('loading');

            const formData = new FormData(this);

            // Add action for AJAX router if not already there
            if (!formData.has('action')) {
                formData.append('action', 'elemacy_widgets_form_submit');
            }

            $.ajax({
                url: elemacy.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    $submitButton.removeAttr('disabled').removeClass('loading');

                    if (response.success) {
                        $messageContainer.html('<div class="elemacy-message elemacy-message-success">' + (response.data.message || 'Form submitted successfully!') + '</div>');
                        $form[0].reset();
                    } else {
                        const errorMessage = response.data.message || 'An error occurred. Please try again.';
                        $messageContainer.html('<div class="elemacy-message elemacy-message-danger">' + errorMessage + '</div>');

                        if (response.data.errors && typeof response.data.errors === 'object') {
                            showFieldErrors(response.data.errors);
                        }
                    }
                },
                error: function (xhr) {
                    $submitButton.removeAttr('disabled').removeClass('loading');
                    const data = xhr.responseJSON && xhr.responseJSON.data;
                    const serverMessage = data && data.message;
                    $messageContainer.html('<div class="elemacy-message elemacy-message-danger">' + (serverMessage || 'Server error. Please try again later.') + '</div>');

                    if (data && data.errors && typeof data.errors === 'object') {
                        showFieldErrors(data.errors);
                    }
                }
            });
        });
    };

    $(window).on('elementor/frontend/init', function () {
        elementorFrontend.hooks.addAction('frontend/element_ready/elemacy-form.default', FormHandler);
    });

})(jQuery);
