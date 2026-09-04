(function ($) {
    'use strict';

    $(document).ready(function () {
        // Auto-open Login Popup if there were login errors
        function autoOpenPopup() {
            if (typeof tfpAuthSettings !== 'undefined' && tfpAuthSettings.autoOpenLoginPopup && tfpAuthSettings.loginPopupID) {
                var loginId = parseInt(tfpAuthSettings.loginPopupID, 10);
                if (typeof elementorProFrontend !== 'undefined' && elementorProFrontend.modules && elementorProFrontend.modules.popup) {
                    elementorProFrontend.modules.popup.showPopup({ id: loginId });
                }
            }
        }

        // Run auto-open on load, checking if Elementor Pro Frontend is ready
        if (typeof elementorProFrontend !== 'undefined' && elementorProFrontend.modules && elementorProFrontend.modules.popup) {
            autoOpenPopup();
        } else {
            $(window).on('elementor/frontend/init', function () {
                autoOpenPopup();
            });
            // Fallback in case init already occurred
            setTimeout(autoOpenPopup, 800);
        }

        // Password Visibility Toggle
        $(document).on('click', '.tfp-password-toggle', function () {
            var $toggle = $(this);
            var $wrapper = $toggle.closest('.tfp-password-wrapper');
            var $input = $wrapper.find('.tfp-input');
            var $eyeClosed = $toggle.find('.eye-icon-closed');
            var $eyeOpen = $toggle.find('.eye-icon-open');

            if ($input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $eyeClosed.addClass('tfp-hidden');
                $eyeOpen.removeClass('tfp-hidden');
            } else {
                $input.attr('type', 'password');
                $eyeClosed.removeClass('tfp-hidden');
                $eyeOpen.addClass('tfp-hidden');
            }
        });

        // Switch from Login popup to Register popup
        $(document).on('click', '.js-tfp-switch-to-register', function (e) {
            e.preventDefault();

            if (typeof tfpAuthSettings !== 'undefined' && tfpAuthSettings.loginPopupID && tfpAuthSettings.registerPopupID) {
                var loginId = parseInt(tfpAuthSettings.loginPopupID, 10);
                var registerId = parseInt(tfpAuthSettings.registerPopupID, 10);

                if (typeof elementorProFrontend !== 'undefined' && elementorProFrontend.modules && elementorProFrontend.modules.popup) {
                    // Close current login popup
                    elementorProFrontend.modules.popup.closePopup({ id: loginId }, e);
                    // Open register popup after a small delay to avoid Elementor modal queue locks
                    setTimeout(function () {
                        elementorProFrontend.modules.popup.showPopup({ id: registerId });
                    }, 100);
                } else {
                    console.warn('Elementor Pro Popup API is not available.');
                }
            }
        });

        function ensureNoticeContainer($form) {
            var $noticeWrap = $form.find('.tfp-form-notices').first();

            if ($noticeWrap.length) {
                if ($noticeWrap.parent().is($form) || !$noticeWrap.parent().length) {
                    $noticeWrap.insertBefore($form);
                }
                return $noticeWrap;
            }

            $noticeWrap = $('<div class="tfp-form-notices" aria-live="polite"></div>');
            $noticeWrap.insertBefore($form);
            return $noticeWrap;
        }

        function showThankYouMessage($form, $noticeWrap) {
            $form.hide();
            $form.find('input, select, button').prop('disabled', true);
            $noticeWrap.html(   '<div class="tfp-success-message" role="status">' +
                    '<h3>Thank You for Registering!</h3>' +
                    '<p>We\'re excited to have you on board and appreciate your interest. Please check your inbox for a confirmation email, and don\'t forget to  check your spam or junk folder in case it ended up there</p>' +
                    '<p>While you wait, stay connected with us for the latest updates, news, and exclusive content by following our social media channels.</p>' +
                    '<button type="button" class="tfp-btn tfp-btn-primary tfp-btn-lg tfp-w-100 tfp-thankyou-login-btn js-tfp-thankyou-login">Login</button>' +
                '</div>');
        }

             // Thank-you screen: close register popup and open login popup
        $(document).on('click', '.js-tfp-thankyou-login', function (e) {
            e.preventDefault();

            if (typeof tfpAuthSettings !== 'undefined' && tfpAuthSettings.loginPopupID && tfpAuthSettings.registerPopupID) {
                var loginId = parseInt(tfpAuthSettings.loginPopupID, 10);
                var registerId = parseInt(tfpAuthSettings.registerPopupID, 10);

                if (typeof elementorProFrontend !== 'undefined' && elementorProFrontend.modules && elementorProFrontend.modules.popup) {
                    elementorProFrontend.modules.popup.closePopup({ id: registerId }, e);
                    setTimeout(function () {
                        elementorProFrontend.modules.popup.showPopup({ id: loginId });
                    }, 100);
                } else {
                    console.warn('Elementor Pro Popup API is not available.');
                }
            }
        });

        // Register form submission with loading state and popup transitions
        $(document).on('submit', '.tfp-register-form', function (e) {
            e.preventDefault();

            var $form = $(this);
            var $button = $form.find('button[type="submit"]');
            var $noticeWrap = ensureNoticeContainer($form);
            var originalHtml = $button.data('original-html') || $button.html();
            var passwordField = $form.find('input[name="password"]');
            var shouldClearPassword = false;

            $button.data('original-html', originalHtml);
            $button.prop('disabled', true).addClass('is-loading');
            $button.html('<span class="tfp-btn-spinner" aria-hidden="true"></span><span class="tfp-btn-label">Creating Account...</span>');
            $noticeWrap.empty();

            $.ajax({
                type: 'POST',
                url: typeof tfpAuthSettings !== 'undefined' && tfpAuthSettings.ajaxUrl ? tfpAuthSettings.ajaxUrl : window.ajaxurl,
                data: $form.serialize() + '&action=tfp_register&tfp_register_submit=1',
             
                dataType: 'json',
                success: function (response) {
                    if (response && response.success) {
                        shouldClearPassword = true;
                        // The original behavior is to keep registration and login as
                        // separate steps: show the thank-you state and let the user
                        // log in afterward, instead of redirecting immediately.
                        showThankYouMessage($form, $noticeWrap);
                        return;
                    }

                    var notices = [];
                    if (response && response.data && response.data.errors && response.data.errors.length) {
                        notices = response.data.errors;
                    } else if (response && response.errors && response.errors.length) {
                        notices = response.errors;
                    } else {
                        notices = ['We could not create your account right now. Please try again.'];
                    }

                    var $list = $('<ul class="woocommerce-error" role="alert"></ul>');
                    $.each(notices, function (_, message) {
                        $('<li></li>').text(message).appendTo($list);
                    });
                    $noticeWrap.html($list);
                },
                error: function (xhr) {
                    console.log('AJAX ERROR RESPONSE:', xhr);
                    console.log(xhr.responseText);
                    var fallbackMessage = 'We could not create your account right now. Please try again.';
                    var serverMessage = '';

                    if (xhr && xhr.responseText) {
                        serverMessage = xhr.responseText;
                    }

                    if (serverMessage) {
                        fallbackMessage = serverMessage;
                    }

                    $noticeWrap.html('<ul class="woocommerce-error" role="alert"><li>' + fallbackMessage + '</li></ul>');
                },
                complete: function () {
                    $button.removeClass('is-loading').prop('disabled', false);
                    $button.html(originalHtml);

                    if (shouldClearPassword) {
                        passwordField.val('');
                    }
                }
            });
        });
    });
})(jQuery);
