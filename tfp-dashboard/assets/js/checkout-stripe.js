/**
 * Stripe front-end for the custom TFP checkout "Payment" step.
 *
 * checkout.js calls window.tfpStripeRenderMethod(method, panelEl) whenever a
 * payment method card is chosen. This file renders the right UI into that panel:
 *   - credit    -> Stripe Card Element + "Pay with Card" button
 *   - applepay  -> Payment Request Button (Apple Pay)
 *   - googlepay -> Payment Request Button (Google Pay)
 *   - paypal    -> short note (PayPal is handled by WooCommerce's own plugin)
 *
 * Flow: create/refresh a PaymentIntent -> confirm on the client with Stripe ->
 * ask the server to verify the intent and create the WooCommerce order ->
 * redirect to the order-received (thank-you) page.
 */
(function () {
    var S = window.tfpStripeSettings || {};
    var C = window.tfpCheckoutSettings || {};

    if (!S.isConfigured || !S.publishableKey) {
        return; // checkout.js keeps its non-Stripe fallback UI.
    }

    var stripe = null;
    var busy = false;

    function getStripe() {
        if (!stripe && typeof window.Stripe === 'function') {
            stripe = window.Stripe(S.publishableKey);
        }
        return stripe;
    }

    function ajax(action, extra) {
        var fd = new FormData();
        fd.append('action', action);
        fd.append('tfp_checkout_nonce', C.nonce || S.nonce || '');
        if (extra) {
            Object.keys(extra).forEach(function (key) {
                fd.append(key, extra[key]);
            });
        }
        return fetch(C.ajaxUrl || S.ajaxUrl, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json();
        });
    }

    function createIntent() {
        return ajax('tfp_stripe_create_intent', {});
    }

    function completeOrder(intentId) {
        return ajax('tfp_stripe_complete_order', { payment_intent_id: intentId }).then(function (res) {
            if (res && res.success && res.popup && typeof window.tfpOpenCourseOrderConfirmation === 'function') {
                window.tfpOpenCourseOrderConfirmation(res);
                return;
            }
            if (res && res.success && res.redirect) {
                window.location.href = res.redirect;
                return;
            }
            throw new Error((res && res.message) || 'We could not confirm your order. Please contact support.');
        });
    }

    function setError(message) {
        var el = document.getElementById('tfp-stripe-error');
        if (el) {
            el.textContent = message || '';
        }
    }

    function fieldValue(id) {
        var el = document.getElementById(id);
        return el ? String(el.value || '').trim() : '';
    }

    function buildBillingDetails() {
        var details = {};
        var name = [fieldValue('tfp_checkout_first_name'), fieldValue('tfp_checkout_last_name')]
            .filter(Boolean).join(' ').trim();
        if (name) { details.name = name; }

        var email = fieldValue('tfp_checkout_email');
        if (email) { details.email = email; }

        var phone = fieldValue('tfp_checkout_phone');
        if (phone) { details.phone = phone; }

        details.address = {
            line1: fieldValue('tfp_checkout_street'),
            line2: fieldValue('tfp_checkout_apt'),
            city: fieldValue('tfp_checkout_city'),
            state: fieldValue('tfp_checkout_state'),
            postal_code: fieldValue('tfp_checkout_postcode'),
            country: S.country || 'US'
        };
        return details;
    }

    /* ---- Credit / Debit card ---- */
    function renderCard(panel) {
        panel.innerHTML =
            '<div class="tfp-checkout-payment-complete tfp-checkout-card-form">' +
            '<h4 class="tfp-checkout-card-form-title">Enter Card Details</h4>' +
            '<div class="tfp-stripe-field">' +
            '<label for="tfp-stripe-card-number">Card Number</label>' +
            '<div id="tfp-stripe-card-number" class="tfp-stripe-card-element"></div>' +
            '</div>' +
            '<div class="tfp-stripe-field-row">' +
            '<div class="tfp-stripe-field">' +
            '<label for="tfp-stripe-card-expiry">Expiration Date</label>' +
            '<div id="tfp-stripe-card-expiry" class="tfp-stripe-card-element"></div>' +
            '</div>' +
            '<div class="tfp-stripe-field">' +
            '<label for="tfp-stripe-card-cvc">Security Code (CVV)</label>' +
            '<div id="tfp-stripe-card-cvc" class="tfp-stripe-card-element"></div>' +
            '</div>' +
            '</div>' +
            '<div class="tfp-stripe-field">' +
            '<label for="tfp-stripe-card-name">Name on Card</label>' +
            '<input type="text" id="tfp-stripe-card-name" class="tfp-stripe-text-input" placeholder="Jane Doe" />' +
            '</div>' +
            '<div id="tfp-stripe-error" class="tfp-stripe-error" role="alert"></div>' +
            '<div class="tfp-checkout-payment-info">Your payment is processed securely by Stripe' + (S.testMode ? ' (test mode)' : '') + '. We never see or store your card number.</div>' +
            '</div>';

        var s = getStripe();
        if (!s) {
            setError('Unable to load the payment form. Please refresh and try again.');
            return;
        }

        var isMobile = window.innerWidth <= 767;
        var elementStyle = {
            base: {
                fontSize: isMobile ? '16px' : '14px', // Match checkout.css
                fontWeight: '500', // Match checkout.css
                color: '#505050', // Match checkout.css
                fontFamily: 'inherit, "Eudoxus Sans", sans-serif',
                '::placeholder': { color: '#9C9CA4' },
                lineHeight: '26px'
            },
            invalid: { color: '#570506', iconColor: '#570506' }
        };

        var elements = s.elements();
        var cardNumber = elements.create('cardNumber', { style: elementStyle, placeholder: '1234 5678 9012 3456' });
        var cardExpiry = elements.create('cardExpiry', { style: elementStyle });
        var cardCvc = elements.create('cardCvc', { style: elementStyle, placeholder: 'CVC' });

        cardNumber.mount('#tfp-stripe-card-number');
        cardExpiry.mount('#tfp-stripe-card-expiry');
        cardCvc.mount('#tfp-stripe-card-cvc');

        [cardNumber, cardExpiry, cardCvc].forEach(function (el) {
            el.on('change', function (event) {
                setError(event.error ? event.error.message : '');
            });
        });

        // Remove old bound event listener if exists by replacing the element
        var oldBtn = document.getElementById('tfp-global-place-order');
        if (!oldBtn) return;
        var payBtn = oldBtn.cloneNode(true);
        oldBtn.parentNode.replaceChild(payBtn, oldBtn);

        payBtn.addEventListener('click', function () {
            if (busy) { return; }
            busy = true;
            var originalText = payBtn.innerHTML;
            payBtn.disabled = true;
            payBtn.innerHTML = 'Processing…';
            setError('');

            createIntent().then(function (res) {
                if (!res || !res.success || !res.clientSecret) {
                    throw new Error((res && res.message) || 'Could not start the payment.');
                }
                var billing = buildBillingDetails();
                var nameField = fieldValue('tfp-stripe-card-name');
                if (nameField) { billing.name = nameField; }
                return s.confirmCardPayment(res.clientSecret, {
                    payment_method: {
                        card: cardNumber,
                        billing_details: billing
                    }
                });
            }).then(function (result) {
                if (result.error) {
                    throw new Error(result.error.message || 'Your payment could not be completed.');
                }
                if (result.paymentIntent && result.paymentIntent.status === 'succeeded') {
                    payBtn.innerHTML = 'Confirming order…';
                    return completeOrder(result.paymentIntent.id);
                }
                throw new Error('Your payment could not be completed.');
            }).catch(function (err) {
                setError(err.message || 'Payment failed. Please try again.');
                busy = false;
                payBtn.disabled = false;
                payBtn.innerHTML = originalText;
            });
        });
    }

    /* ---- Apple Pay / Google Pay via the Payment Request Button ---- */
    function renderWallet(panel, wallet) {
        var label = wallet === 'apple' ? 'Apple Pay' : 'Google Pay';
        panel.innerHTML =
            '<div class="tfp-checkout-payment-complete">' +
            '<h4 class="tfp-checkout-payment-complete-title">Complete with ' + label + '</h4>' +
            '<div class="tfp-checkout-payment-info">Complete your purchase quickly and securely using ' + label + '.</div>' +
            '<div id="tfp-stripe-payment-request-button" class="tfp-stripe-prb"></div>' +
            '<div id="tfp-stripe-wallet-fallback" class="tfp-stripe-wallet-fallback" style="display:none;"></div>' +
            '<div id="tfp-stripe-error" class="tfp-stripe-error" role="alert"></div>' +
            '</div>';

        var s = getStripe();
        if (!s) {
            setError('Unable to load the payment form. Please refresh and try again.');
            return;
        }

        // We need the order amount up front to build the wallet sheet, so create
        // the PaymentIntent first and reuse its client secret to confirm.
        createIntent().then(function (res) {
            if (!res || !res.success || !res.clientSecret) {
                throw new Error((res && res.message) || 'Could not start the payment.');
            }

            var clientSecret = res.clientSecret;
            var paymentRequest = s.paymentRequest({
                country: S.country || 'US',
                currency: (res.currency || S.currency || 'usd').toLowerCase(),
                total: { label: 'Order total', amount: res.amount },
                requestPayerName: true,
                requestPayerEmail: true
            });

            var prButton = s.elements().create('paymentRequestButton', {
                paymentRequest: paymentRequest,
                style: { paymentRequestButton: { type: 'default', theme: 'dark', height: '48px' } },
                wallets: {
                    applePay: wallet === 'apple' ? 'auto' : 'never',
                    googlePay: wallet === 'googlepay' ? 'auto' : 'never',
                    link: 'never' // Disable Stripe Link completely
                }
            });

            paymentRequest.canMakePayment().then(function (result) {
                var isAvailable = false;
                if (result) {
                    if (wallet === 'apple' && result.applePay) {
                        isAvailable = true;
                    } else if (wallet === 'googlepay' && result.googlePay) {
                        isAvailable = true;
                    }
                }

                if (isAvailable) {
                    prButton.mount('#tfp-stripe-payment-request-button');
                } else {
                    var fallback = document.getElementById('tfp-stripe-wallet-fallback');
                    if (fallback) {
                        fallback.style.display = 'block';
                        fallback.textContent = label + ' is not available on this device or browser. Please choose Credit/Debit card instead.';
                    }
                }
            });

            paymentRequest.on('paymentmethod', function (event) {
                s.confirmCardPayment(clientSecret, { payment_method: event.paymentMethod.id }, { handleActions: false })
                    .then(function (confirmResult) {
                        if (confirmResult.error) {
                            event.complete('fail');
                            setError(confirmResult.error.message || 'Your payment could not be completed.');
                            return;
                        }
                        event.complete('success');

                        var pi = confirmResult.paymentIntent;
                        if (pi && pi.status === 'requires_action') {
                            s.confirmCardPayment(clientSecret).then(function (again) {
                                if (again.error) {
                                    setError(again.error.message || 'Your payment could not be completed.');
                                    return;
                                }
                                completeOrder(again.paymentIntent.id).catch(function (e) { setError(e.message); });
                            });
                        } else if (pi && pi.status === 'succeeded') {
                            completeOrder(pi.id).catch(function (e) { setError(e.message); });
                        } else {
                            setError('Your payment could not be completed.');
                        }
                    });
            });
        }).catch(function (err) {
            setError(err.message || 'Could not start the payment.');
        });
    }

    /* ---- PayPal placeholder (handled separately by WooCommerce's plugin) ---- */
    function renderPaypalNote(panel) {
        panel.innerHTML =
            '<div class="tfp-checkout-payment-complete">' +
            '<h4 class="tfp-checkout-payment-complete-title">Complete with PayPal</h4>' +
            '<div class="tfp-checkout-payment-info">PayPal isn’t available in this step yet. Please choose Credit/Debit card, Apple Pay, or Google Pay.</div>' +
            '</div>';
    }

    /* ---- Entry point used by checkout.js ---- */
    window.tfpStripeRenderMethod = function (methodName, panel) {
        if (!panel) { return; }
        busy = false;

        if (methodName === 'applepay') {
            renderWallet(panel, 'apple');
        } else if (methodName === 'googlepay') {
            renderWallet(panel, 'google');
        } else if (methodName === 'paypal') {
            renderPaypalNote(panel);
        } else {
            renderCard(panel);
        }
    };
})();
