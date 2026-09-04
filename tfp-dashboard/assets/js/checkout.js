document.addEventListener('DOMContentLoaded', function () {
    var body = document.body;
    if (!body || !document.querySelector('.tfp-checkout-page-wrap')) {
        return;
    }

    var currentStep = document.querySelector('.tfp-checkout-panel[data-step="' + (window.tfpCheckoutSettings && window.tfpCheckoutSettings.defaultStep ? window.tfpCheckoutSettings.defaultStep : 'cart') + '"]');
    var stepLinks = document.querySelectorAll('.tfp-checkout-step-tab');
    var paymentMethodButtons = document.querySelectorAll('.tfp-checkout-method-card');
    var paymentPanel = document.getElementById('tfp-checkout-payment-panel');
    var stripeActive = !!(window.tfpStripeSettings && window.tfpStripeSettings.isConfigured);

    // When Stripe drives the payment step, the in-panel "Pay with ..." button is
    // the single call to action, so hide the footer "Place Order" button (which
    // otherwise just redirects to WooCommerce's default checkout page).
    if (stripeActive) {
        document.querySelectorAll('.tfp-checkout-panel[data-step="payment"] .tfp-checkout-place-order').forEach(function (btn) {
            btn.style.display = 'none';
        });
    }

    function isStepVisible(stepName) {
        var shell = document.querySelector('.tfp-checkout-panel[data-step="' + stepName + '"]');
        if (!shell) {
            return false;
        }
        return window.getComputedStyle(shell).display !== 'none';
    }

    function setActiveStep(stepName) {
        document.querySelectorAll('.tfp-checkout-panel').forEach(function (section) {
            var stepAttr = section.getAttribute('data-step');
            if (stepAttr) {
                var isVisible = stepAttr === stepName;
                if (isVisible) {
                    section.style.display = section.classList.contains('tfp-contact-panel') ? 'flex' : 'block';
                } else {
                    section.style.display = 'none';
                }
            }
        });

        stepLinks.forEach(function (link) {
            var isActive = link.getAttribute('data-step-link') === stepName;
            link.classList.toggle('is-active', isActive);

            if (isActive) {
                var title = link.getAttribute('data-step-title');
                var subtitle = link.getAttribute('data-step-subtitle');
                var titleEl = document.querySelector('.tfp-checkout-title-header .tfp-checkout-page-title');
                var subtitleEl = document.querySelector('.tfp-checkout-title-header .tfp-checkout-subtitle');
                if (titleEl && title) titleEl.textContent = title;
                if (subtitleEl && subtitle) subtitleEl.textContent = subtitle;
            }
        });

        if (stepName === 'delivery' && isStepVisible('delivery')) {
            fetchShippingMethods();
        }

        if (stepName === 'payment' && isStepVisible('payment')) {
            loadPaymentMethod('credit');
        }
    }

    function updateCartDOM(data) {
        if (data.cart_html) {
            var oldCart = document.querySelector('.tfp-checkout-panel[data-step="cart"]');
            if (oldCart) {
                oldCart.outerHTML = data.cart_html;
            }
        }
        if (data.summary_html) {
            var oldSummary = document.querySelector('.tfp-checkout-order-summary');
            if (oldSummary) {
                oldSummary.outerHTML = data.summary_html;
            }
        }
        setActiveStep('cart');
    }

    stepLinks.forEach(function (link) {
        link.addEventListener('click', function (event) {
            if (link.classList.contains('is-disabled')) {
                event.preventDefault();
                return;
            }
        });
    });

    if (currentStep) {
        setActiveStep(currentStep.getAttribute('data-step'));
    }

    document.addEventListener('click', function (event) {
        var target = event.target.closest('.tfp-checkout-step-next');
        if (target) {
            event.preventDefault();
            var current = target.closest('.tfp-checkout-step-shell');
            if (!current) {
                return;
            }
            var step = current.getAttribute('data-step');
            var nextStep = step === 'cart' ? 'contact' : step === 'contact' ? 'delivery' : 'payment';
            setActiveStep(nextStep);
            var nextLink = document.querySelector('.tfp-checkout-step-tab[data-step-link="' + nextStep + '"]');
            if (nextLink) {
                var href = nextLink.getAttribute('href');
                if (href) {
                    window.history.pushState({}, '', href);
                }
            }
        }

        var backLink = event.target.closest('.tfp-checkout-link-button');
        if (backLink && backLink.getAttribute('href')) {
            var href = backLink.getAttribute('href');
            var stepName = new URL(href, window.location.href).searchParams.get('tfp_checkout_step');
            if (stepName) {
                event.preventDefault();
                window.history.pushState({}, '', href);
                setActiveStep(stepName);
            }
        }

        var qtyButton = event.target.closest('.tfp-checkout-qty-button');
        if (qtyButton) {
            event.preventDefault();
            var delta = qtyButton.getAttribute('data-action') === 'increment' ? 1 : -1;
            var cartKey = qtyButton.getAttribute('data-cart-key');
            var formData = new FormData();
            formData.append('action', 'tfp_checkout_update_quantity');
            formData.append('tfp_checkout_nonce', window.tfpCheckoutSettings.nonce);
            formData.append('cart_key', cartKey);
            formData.append('delta', String(delta));

            fetch(window.tfpCheckoutSettings.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            }).then(function (response) { return response.json(); }).then(function (data) {
                if (!data.success) {
                    alert(data.message || 'Unable to update quantity.');
                    return;
                }
                updateCartDOM(data);
            });
        }

        var removeItem = event.target.closest('.tfp-checkout-remove-item');
        if (removeItem) {
            event.preventDefault();
            var cartKey = removeItem.getAttribute('data-cart-key');
            var formData = new FormData();
            formData.append('action', 'tfp_checkout_update_quantity');
            formData.append('tfp_checkout_nonce', window.tfpCheckoutSettings.nonce);
            formData.append('cart_key', cartKey);
            formData.append('delta', '-999');

            fetch(window.tfpCheckoutSettings.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            }).then(function (response) { return response.json(); }).then(function (data) {
                if (!data.success) {
                    alert(data.message || 'Unable to remove item.');
                    return;
                }
                updateCartDOM(data);
            });
        }

        var promoToggle = event.target.closest('.tfp-checkout-toggle-promo');
        if (promoToggle) {
            event.preventDefault();
            var panel = document.querySelector('.tfp-checkout-promo-panel');
            if (panel) {
                panel.style.display = panel.style.display === 'none' ? 'flex' : 'none';
            }
        }

        var couponButton = event.target.closest('.tfp-checkout-coupon-submit');
        if (couponButton) {
            event.preventDefault();
            var input = document.querySelector('.tfp-checkout-coupon-input');
            var code = input ? input.value.trim() : '';
            if (!code) {
                alert('Please enter a coupon code.');
                return;
            }

            var formData = new FormData();
            formData.append('action', 'tfp_checkout_apply_coupon');
            formData.append('tfp_checkout_nonce', window.tfpCheckoutSettings.nonce);
            formData.append('coupon_code', code);

            fetch(window.tfpCheckoutSettings.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            }).then(function (response) { return response.json(); }).then(function (data) {
                if (!data.success) {
                    alert(data.message || 'Unable to apply coupon.');
                    return;
                }
                updateCartDOM(data);
            });
        }

        var saveContactButton = event.target.closest('.tfp-checkout-save-contact');
        if (saveContactButton) {
            event.preventDefault();
            var formData = new FormData();
            formData.append('action', 'tfp_checkout_save_contact_shipping');
            formData.append('tfp_checkout_nonce', window.tfpCheckoutSettings.nonce);
            formData.append('first_name', document.getElementById('tfp_checkout_first_name').value);
            formData.append('last_name', document.getElementById('tfp_checkout_last_name').value);
            formData.append('email', document.getElementById('tfp_checkout_email').value);
            formData.append('phone', document.getElementById('tfp_checkout_phone').value);
            formData.append('street', document.getElementById('tfp_checkout_street').value);
            formData.append('apt', document.getElementById('tfp_checkout_apt').value);
            formData.append('city', document.getElementById('tfp_checkout_city').value);
            formData.append('state', document.getElementById('tfp_checkout_state').value);
            formData.append('postcode', document.getElementById('tfp_checkout_postcode').value);

            fetch(window.tfpCheckoutSettings.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            }).then(function (response) { return response.json(); }).then(function (data) {
                if (!data.success) {
                    alert(data.message || 'Please complete all required fields.');
                    return;
                }
                setActiveStep('delivery');
                fetchShippingMethods();
            });
        }

        var saveShippingButton = event.target.closest('.tfp-checkout-save-shipping');
        if (saveShippingButton) {
            event.preventDefault();
            var selected = document.querySelector('input[name="tfp_checkout_shipping_method"]:checked');
            if (!selected) {
                alert('Please select a shipping method.');
                return;
            }

            var formData = new FormData();
            formData.append('action', 'tfp_checkout_select_shipping_method');
            formData.append('tfp_checkout_nonce', window.tfpCheckoutSettings.nonce);
            formData.append('shipping_method', selected.value);

            fetch(window.tfpCheckoutSettings.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            }).then(function (response) { return response.json(); }).then(function (data) {
                if (!data.success) {
                    alert(data.message || 'Unable to save shipping method.');
                    return;
                }
                setActiveStep('payment');
                loadPaymentMethod('credit');
            });
        }

        var placeOrder = event.target.closest('.tfp-checkout-place-order');
        if (placeOrder) {
            event.preventDefault();
            // Stripe handles payment via its own in-panel button; don't redirect.
            if (stripeActive) {
                return;
            }
            if (window.tfpCheckoutSettings && window.tfpCheckoutSettings.checkoutUrl) {
                window.location.href = window.tfpCheckoutSettings.checkoutUrl;
            }
        }
    });

    document.addEventListener('change', function (event) {
        var target = event.target;
        if (target && target.name === 'tfp_checkout_shipping_method') {
            console.log('Shipping method radio changed to:', target.value);

            var formData = new FormData();
            formData.append('action', 'tfp_checkout_select_shipping_method');
            formData.append('tfp_checkout_nonce', window.tfpCheckoutSettings.nonce);
            formData.append('shipping_method', target.value);

            console.log('Sending AJAX request for shipping method:', target.value);

            fetch(window.tfpCheckoutSettings.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            }).then(function (response) {
                console.log('AJAX Response status:', response.status);
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            }).then(function (data) {
                console.log('Shipping method selected - Full Response:', data);
                console.log('Success:', data.success);
                console.log('Has summary_html:', !!data.summary_html);

                if (!data.success) {
                    console.error('Shipping selection failed:', data.message);
                    alert(data.message || 'Failed to select shipping method');
                    return;
                }

                if (!data.summary_html) {
                    console.error('No summary_html in response');
                    console.log('Response keys:', Object.keys(data));
                    return;
                }

                var oldSummary = document.querySelector('.tfp-checkout-order-summary');
                if (oldSummary) {
                    console.log('Found order summary element, updating content');
                    console.log('New HTML length:', data.summary_html.length);

                    // Create a temporary container to parse the HTML
                    var temp = document.createElement('div');
                    temp.innerHTML = data.summary_html;
                    var newSummary = temp.querySelector('.tfp-checkout-order-summary');

                    if (newSummary) {
                        console.log('Parsed new summary element successfully');
                        oldSummary.parentNode.replaceChild(newSummary, oldSummary);
                        console.log('Order summary updated successfully');
                    } else {
                        console.error('Could not parse summary from HTML');
                        console.log('HTML received:', data.summary_html.substring(0, 200));
                    }
                } else {
                    console.error('Order summary element not found in DOM');
                }
            }).catch(function (error) {
                console.error('Error selecting shipping method:', error);
                console.error('Error stack:', error.stack);
                alert('An error occurred while selecting the shipping method. Please try again.');
            });
        }
    });

    function fetchShippingMethods() {
        var formData = new FormData();
        formData.append('action', 'tfp_checkout_get_shipping_methods');
        formData.append('tfp_checkout_nonce', window.tfpCheckoutSettings.nonce);

        fetch(window.tfpCheckoutSettings.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.json();
        }).then(function (data) {
            if (!data.success) {
                console.error('Failed to fetch shipping methods:', data.message);
                alert(data.message || 'Shipping methods are unavailable.');
                return;
            }

            if (!data.methods || data.methods.length === 0) {
                console.error('No shipping methods returned');
                alert('No shipping methods are available for your location.');
                return;
            }

            var list = document.getElementById('tfp-checkout-shipping-method-list');
            if (!list) {
                console.error('Shipping method list container not found');
                return;
            }

            list.innerHTML = '';
            var isFirst = true;
            data.methods.forEach(function (method) {
                var wrapper = document.createElement('label');
                wrapper.className = 'tfp-checkout-shipping-option';
                wrapper.innerHTML = '<input type="radio" name="tfp_checkout_shipping_method" value="' + method.id + '" ' + (isFirst ? 'checked' : '') + ' />' +
                    '<span class="tfp-checkout-shipping-option-copy"><strong>' + method.label + '</strong><small>' + method.delivery_estimate + '</small></span>' +
                    '<span class="tfp-checkout-shipping-option-price">' + method.price + '</span>';
                list.appendChild(wrapper);
                isFirst = false;
            });

            console.log('Shipping methods loaded, triggering change event on first method');
            var checkedRadio = list.querySelector('input[name="tfp_checkout_shipping_method"]:checked');
            if (checkedRadio) {
                checkedRadio.dispatchEvent(new Event('change', { bubbles: true }));
            } else {
                console.error('No checked radio button found after rendering methods');
            }
        }).catch(function (error) {
            console.error('Error fetching shipping methods:', error);
            alert('An error occurred while loading shipping methods. Please refresh the page and try again.');
        });
    }

    var tfpPaymentMethods = {
        credit: {
            title: 'Complete with Credit / Debit Card',
            desc: 'You will be redirected to our secure payment page to enter your card details and complete your purchase.',
            button: 'Pay with Card',
            variant: 'card'
        },
        paypal: {
            title: 'Complete with PayPal',
            desc: 'You will be securely redirected to PayPal to complete your purchase. Your order will be confirmed once payment is approved.',
            button: 'Pay with PayPal',
            variant: 'paypal'
        },
        applepay: {
            title: 'Complete with Apple Pay',
            desc: 'Complete your purchase quickly and securely using Face ID, Touch ID, or your device passcode.',
            button: 'Pay with Apple Pay',
            variant: 'apple'
        },
        googlepay: {
            title: 'Complete with Google Pay',
            desc: 'Complete your purchase securely using your saved Google Pay payment method.',
            button: 'Pay with Google Pay',
            variant: 'google'
        }
    };

    function loadPaymentMethod(methodName) {
        if (!paymentPanel) {
            return;
        }

        // Inject dynamic style if not present
        if (!document.getElementById('tfp-checkout-dynamic-styles')) {
            var style = document.createElement('style');
            style.id = 'tfp-checkout-dynamic-styles';
            style.innerHTML = '.tfp-checkout-actions.hide-place-order #tfp-global-place-order { display: none !important; }';
            document.head.appendChild(style);
        }

        var globalPlaceOrderBtn = document.getElementById('tfp-global-place-order');
        if (globalPlaceOrderBtn) {
            var actionWrapper = globalPlaceOrderBtn.closest('.tfp-checkout-actions');
            if (actionWrapper) {
                if (methodName === 'credit') {
                    actionWrapper.classList.remove('hide-place-order');
                } else {
                    actionWrapper.classList.add('hide-place-order');
                }
            }
        }

        // Real payment processing (card / Apple Pay) is handled by
        // checkout-stripe.js when Stripe keys are configured.
        if (stripeActive && typeof window.tfpStripeRenderMethod === 'function' && methodName !== 'paypal' && methodName !== 'googlepay') {
            window.tfpStripeRenderMethod(methodName, paymentPanel);
            return;
        }

        // PayPal specific processing
        if (methodName === 'paypal' && typeof window.tfpPayPalRenderMethod === 'function') {
            window.tfpPayPalRenderMethod(paymentPanel);
            return;
        }

        // Google Pay specific processing
        if (methodName === 'googlepay' && typeof window.tfpGooglePayRenderMethod === 'function') {
            window.tfpGooglePayRenderMethod(paymentPanel);
            return;
        }

        var config = tfpPaymentMethods[methodName] || tfpPaymentMethods.credit;

        // Fallback if Stripe/PayPal is inactive
        paymentPanel.innerHTML =
            '<div class="tfp-checkout-payment-complete">' +
            '<h4 class="tfp-checkout-payment-complete-title">' + config.title + '</h4>' +
            '<div class="tfp-checkout-payment-info">' + config.desc + '</div>' +
            (methodName !== 'credit' ? '<button type="button" class="tfp-checkout-pay-btn tfp-checkout-pay-btn--' + config.variant + ' tfp-checkout-place-order">' + config.button + '</button>' : '') +
            '</div>';
    }

    paymentMethodButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            paymentMethodButtons.forEach(function (el) {
                el.classList.toggle('is-selected', el === button);
            });
            loadPaymentMethod(button.getAttribute('data-method'));
        });
    });

    if (isStepVisible('delivery')) {
        fetchShippingMethods();
    }

    if (isStepVisible('payment')) {
        loadPaymentMethod('credit');
    }
});
