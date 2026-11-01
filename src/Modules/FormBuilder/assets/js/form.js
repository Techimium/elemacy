/**
 * Elemacy form runtime. One delegated listener handles every Elemacy form on
 * the page, including forms inserted later (popups, AJAX-loaded content), so
 * nothing needs initializing per element.
 *
 * Events, dispatched on the form element and bubbling:
 * - elemacy-form:submit  before sending; preventDefault() cancels the request.
 * - elemacy-form:success with detail { form, response } (the response data).
 * - elemacy-form:error   with detail { form, response } (null on network failure).
 *
 * Server messages are plain text and are always inserted with textContent.
 */
(function () {
    'use strict';

    var config = function () {
        var value = window.elemacyFormsConfig || {};

        value.i18n = value.i18n || {};

        return value;
    };

    var dispatch = function (form, name, response, cancelable) {
        var event = new CustomEvent(name, {
            bubbles: true,
            cancelable: !!cancelable,
            detail: { form: form, response: response }
        });

        return form.dispatchEvent(event);
    };

    var messages = function (form) {
        return form.querySelector('.elemacy-form-messages');
    };

    var showMessage = function (form, text, kind) {
        var area = messages(form);

        if (!area) {
            return;
        }

        var message = document.createElement('div');

        message.className = 'elemacy-form-message elemacy-form-message-' + kind;
        message.textContent = text;
        area.appendChild(message);
    };

    var clear = function (form) {
        var area = messages(form);

        if (area) {
            area.textContent = '';
        }

        form.querySelectorAll('.elemacy-form-field-invalid').forEach(function (field) {
            field.classList.remove('elemacy-form-field-invalid');
        });
        form.querySelectorAll('[aria-invalid]').forEach(function (control) {
            control.removeAttribute('aria-invalid');
        });
        form.querySelectorAll('.elemacy-form-error').forEach(function (slot) {
            slot.textContent = '';
            slot.hidden = true;
        });
    };

    var fieldWrapper = function (form, id) {
        var wrappers = form.querySelectorAll('[data-field-id]');

        for (var i = 0; i < wrappers.length; i++) {
            if (wrappers[i].getAttribute('data-field-id') === id) {
                return wrappers[i];
            }
        }

        return null;
    };

    /** Marks fields invalid; returns the first control to focus, if any. */
    var showFieldErrors = function (form, errors) {
        var first = null;

        Object.keys(errors).forEach(function (id) {
            var wrapper = fieldWrapper(form, id);
            var slot = wrapper && wrapper.querySelector('.elemacy-form-error');

            if (!slot) {
                return;
            }

            wrapper.classList.add('elemacy-form-field-invalid');
            slot.textContent = String(errors[id]);
            slot.hidden = false;
            wrapper.querySelectorAll('input, select, textarea').forEach(function (control) {
                control.setAttribute('aria-invalid', 'true');
                first = first || control;
            });
        });

        return first;
    };

    /** A required checkbox group needs one ticked box; the browser cannot check that. */
    var requiredGroupErrors = function (form) {
        var errors = {};

        form.querySelectorAll('fieldset[data-required="true"]').forEach(function (group) {
            var boxes = group.querySelectorAll('input[type="checkbox"]');
            var wrapper = group.closest('[data-field-id]');

            if (!boxes.length || !wrapper) {
                return;
            }

            for (var i = 0; i < boxes.length; i++) {
                if (boxes[i].checked) {
                    return;
                }
            }

            errors[wrapper.getAttribute('data-field-id')] = config().i18n.required || '';
        });

        return errors;
    };

    var finish = function (form, button) {
        form.removeAttribute('data-elemacy-sending');
        form.classList.remove('elemacy-form-sending');

        if (button) {
            button.disabled = false;
        }
    };

    var fail = function (form, data) {
        var errors = data && data.errors && typeof data.errors === 'object' ? data.errors : {};
        var text = data && typeof data.message === 'string' && data.message ? data.message : config().i18n.error;

        showMessage(form, typeof errors.form === 'string' ? errors.form : text, 'error');

        var first = showFieldErrors(form, errors);

        if (first) {
            first.focus();
        }

        dispatch(form, 'elemacy-form:error', data || null);
    };

    document.addEventListener('submit', function (event) {
        var form = event.target;

        if (!form || !form.matches || !form.matches('form[data-elemacy-form]')) {
            return;
        }

        event.preventDefault();

        if (form.hasAttribute('data-elemacy-sending')) {
            return;
        }

        clear(form);

        var groupErrors = requiredGroupErrors(form);

        if (Object.keys(groupErrors).length) {
            var first = showFieldErrors(form, groupErrors);

            if (first) {
                first.focus();
            }

            return;
        }

        if (!dispatch(form, 'elemacy-form:submit', null, true)) {
            return;
        }

        var settings = config();
        var button = form.querySelector('button[type="submit"]');
        var body = new FormData(form);

        if (settings.nonce) {
            body.set('_wpnonce', settings.nonce);
        }

        form.setAttribute('data-elemacy-sending', '');
        form.classList.add('elemacy-form-sending');

        if (button) {
            button.disabled = true;
        }

        fetch(settings.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (response) {
                return response.json().catch(function () {
                    return null;
                });
            })
            .then(function (json) {
                finish(form, button);

                var data = json && json.data && typeof json.data === 'object' ? json.data : null;

                if (json && json.success && data) {
                    showMessage(form, data.message || '', 'success');
                    form.reset();
                    dispatch(form, 'elemacy-form:success', data);

                    return;
                }

                fail(form, data || (json && typeof json.data === 'string' ? { message: json.data } : null));
            })
            .catch(function () {
                finish(form, button);
                showMessage(form, config().i18n.network, 'error');
                dispatch(form, 'elemacy-form:error', null);
            });
    });
})();
