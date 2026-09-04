(function () {
    'use strict';

    function getStripe() {
        var S = window.tfpStripeSettings || {};
        if (typeof window.Stripe === 'function' && S.publishableKey) {
            return window.Stripe(S.publishableKey);
        }
        return null;
    }

    // Google Pay configuration
    const baseRequest = {
        apiVersion: 2,
        apiVersionMinor: 0
    };

    const tokenizationSpecification = {
        type: 'PAYMENT_GATEWAY',
        parameters: {
            'gateway': 'stripe',
            'stripe:version': '2023-10-16', // Fallback version if not dynamically provided
            // 'stripe:publishableKey' is set dynamically in render
        }
    };

    const allowedCardNetworks = ["AMEX", "DISCOVER", "INTERAC", "JCB", "MASTERCARD", "VISA"];
    const allowedCardAuthMethods = ["PAN_ONLY", "CRYPTOGRAM_3DS"];

    const baseCardPaymentMethod = {
        type: 'CARD',
        parameters: {
            allowedAuthMethods: allowedCardAuthMethods,
            allowedCardNetworks: allowedCardNetworks
        }
    };

    const cardPaymentMethod = Object.assign(
        {},
        baseCardPaymentMethod,
        {
            tokenizationSpecification: tokenizationSpecification
        }
    );

    let paymentsClient = null;

    function getGooglePaymentsClient() {
        if (paymentsClient === null) {
            paymentsClient = new google.payments.api.PaymentsClient({
                // Set to 'TEST' for sandbox testing, 'PRODUCTION' for live
                environment: (window.tfpStripeSettings && window.tfpStripeSettings.testMode) ? 'TEST' : 'PRODUCTION'
            });
        }
        return paymentsClient;
    }

    function getGooglePaymentDataRequest(price) {
        const paymentDataRequest = Object.assign({}, baseRequest);
        paymentDataRequest.allowedPaymentMethods = [cardPaymentMethod];

        var S = window.tfpPayPalSettings || {}; // We can reuse the total from tfpPayPalSettings or tfpStripeSettings
        var amount = (S.total) ? parseFloat(S.total).toFixed(2).toString() : '0.00';
        var currency = (window.tfpStripeSettings && window.tfpStripeSettings.currency) ? window.tfpStripeSettings.currency.toUpperCase() : 'USD';

        paymentDataRequest.transactionInfo = {
            totalPriceStatus: 'FINAL',
            totalPrice: amount,
            currencyCode: currency,
            countryCode: 'US'
        };

        paymentDataRequest.merchantInfo = {
            merchantName: 'The Follow Project'
        };

        return paymentDataRequest;
    }

    function renderGooglePay(panel) {
        panel.innerHTML =
            '<div class="tfp-checkout-payment-complete">' +
            '<h4 class="tfp-checkout-payment-complete-title">Complete with Google Pay</h4>' +
            '<div class="tfp-checkout-payment-info">Complete your purchase quickly and securely using Google Pay.</div>' +
            '<div id="tfp-googlepay-button-container" class="tfp-googlepay-btn-container" style="margin-top: 16px;"></div>' +
            '<div id="tfp-googlepay-error" class="tfp-stripe-error" role="alert" style="display:none; margin-top: 15px;"></div>' +
            '</div>';

        var S = window.tfpStripeSettings || {};
        var errorEl = document.getElementById('tfp-googlepay-error');

        function setError(msg) {
            if (errorEl) {
                errorEl.textContent = msg;
                errorEl.style.display = msg ? 'block' : 'none';
            }
        }

        if (typeof google === 'undefined' || typeof google.payments === 'undefined') {
            setError('Google Pay failed to load. Please refresh and try again.');
            return;
        }

        // Dynamically set Stripe Publishable Key
        if (S.publishableKey) {
            tokenizationSpecification.parameters['stripe:publishableKey'] = S.publishableKey;
        }

        const paymentsClient = getGooglePaymentsClient();
        const isReadyToPayRequest = Object.assign({}, baseRequest);
        isReadyToPayRequest.allowedPaymentMethods = [baseCardPaymentMethod];

        // CRITICAL: Force existingPaymentMethodRequired to false so the button ALWAYS shows up
        // and prompts the user to add a card if they don't have one!
        isReadyToPayRequest.existingPaymentMethodRequired = false;

        paymentsClient.isReadyToPay(isReadyToPayRequest).then(function (response) {
            if (response.result) {
                addGooglePayButton(paymentsClient, errorEl);
            } else {
                setError('Google Pay is not available on this device or browser.');
            }
        }).catch(function (err) {
            console.error('isReadyToPay error', err);
            setError('Google Pay initialization failed.');
        });
    }

    function addGooglePayButton(paymentsClient, errorEl) {
        const button = paymentsClient.createButton({
            buttonColor: 'black',
            buttonType: 'pay',
            buttonSizeMode: 'fill',
            onClick: function () {
                onGooglePaymentButtonClicked(paymentsClient, errorEl);
            }
        });
        document.getElementById('tfp-googlepay-button-container').appendChild(button);
    }

    function onGooglePaymentButtonClicked(paymentsClient, errorEl) {
        const paymentDataRequest = getGooglePaymentDataRequest();

        paymentsClient.loadPaymentData(paymentDataRequest).then(function (paymentData) {
            processGooglePayment(paymentData, errorEl);
        }).catch(function (err) {
            if (err.statusCode === 'CANCELED') {
                return; // User closed the prompt
            }
            console.error('loadPaymentData error', err);
            if (errorEl) {
                errorEl.textContent = 'Payment cancelled or failed. Please try again.';
                errorEl.style.display = 'block';
            }
        });
    }

    function processGooglePayment(paymentData, errorEl) {
        // We received the Stripe token from Google Pay API
        const paymentToken = paymentData.paymentMethodData.tokenizationData.token;
        var tokenObj = null;

        try {
            tokenObj = JSON.parse(paymentToken);
        } catch (e) {
            if (errorEl) {
                errorEl.textContent = 'Invalid token received from Google Pay.';
                errorEl.style.display = 'block';
            }
            return;
        }

        var stripeToken = tokenObj.id;
        if (!stripeToken) {
            if (errorEl) {
                errorEl.textContent = 'Payment token not found.';
                errorEl.style.display = 'block';
            }
            return;
        }

        if (errorEl) {
            errorEl.textContent = 'Confirming order... Please wait.';
            errorEl.style.display = 'block';
            errorEl.style.color = '#151411';
        }

        // We need to create a PaymentIntent on the backend, then confirm it with Stripe client-side
        // Since we are replacing Stripe elements, we must mimic checkout-stripe.js flow
        var C = window.tfpCheckoutSettings || {};

        function createIntent() {
            var fd = new FormData();
            fd.append('action', 'tfp_stripe_create_intent');
            fd.append('tfp_checkout_nonce', C.nonce);
            return fetch(C.ajaxUrl, { method: 'POST', body: fd }).then(function (res) { return res.json(); });
        }

        var s = getStripe();
        if (!s) return;

        createIntent().then(function (res) {
            if (!res || !res.success || !res.clientSecret) {
                throw new Error((res && res.message) || 'Could not start the payment.');
            }

            var clientSecret = res.clientSecret;

            // Confirm the card payment using the raw token we got from Google Pay
            return s.confirmCardPayment(clientSecret, {
                payment_method: {
                    card: { token: stripeToken }
                }
            });
        }).then(function (result) {
            if (result.error) {
                throw new Error(result.error.message || 'Payment failed.');
            }
            if (result.paymentIntent && result.paymentIntent.status === 'succeeded') {
                if (errorEl) {
                    errorEl.textContent = 'Order confirmed! Redirecting...';
                }

                // Complete order in WooCommerce backend (mimicking checkout-stripe.js)
                var fd = new FormData();
                fd.append('action', 'tfp_stripe_complete_order');
                fd.append('tfp_checkout_nonce', C.nonce);
                fd.append('payment_intent_id', result.paymentIntent.id);

                return fetch(C.ajaxUrl, { method: 'POST', body: fd })
                    .then(function (r) { return r.json(); })
                    .then(function (orderRes) {
                        if (orderRes && orderRes.success && orderRes.popup && typeof window.tfpOpenCourseOrderConfirmation === 'function') {
                            window.tfpOpenCourseOrderConfirmation(orderRes);
                            return;
                        }
                        if (orderRes && orderRes.success && orderRes.redirect) {
                            window.location.href = orderRes.redirect;
                        } else {
                            throw new Error((orderRes && orderRes.message) || 'Order completion failed.');
                        }
                    });
            }
        }).catch(function (err) {
            if (errorEl) {
                errorEl.textContent = err.message || 'Payment failed. Please try again.';
                errorEl.style.display = 'block';
                errorEl.style.color = '#570506';
            }
        });
    }

    // Attach to the global hook
    window.tfpGooglePayRenderMethod = renderGooglePay;

})();
