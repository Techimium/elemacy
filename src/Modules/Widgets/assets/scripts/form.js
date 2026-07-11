(function ($) {
    'use strict';

    const FormHandler = function ($scope, $) {
        const $form = $scope.find('.elemacy-form');
        const $messageContainer = $scope.find('.elemacy-message-container');
        const $submitButton = $form.find('button[type="submit"]');

        $form.on('submit', function (e) {
            e.preventDefault();

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
                    }
                },
                error: function (xhr) {
                    $submitButton.removeAttr('disabled').removeClass('loading');
                    const serverMessage = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message;
                    $messageContainer.html('<div class="elemacy-message elemacy-message-danger">' + (serverMessage || 'Server error. Please try again later.') + '</div>');
                }
            });
        });
    };

    $(window).on('elementor/frontend/init', function () {
        elementorFrontend.hooks.addAction('frontend/element_ready/elemacy-form.default', FormHandler);
    });

})(jQuery);
