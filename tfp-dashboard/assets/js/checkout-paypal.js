(function ($) {
    'use strict';

    function renderPayPal(panel) {
        panel.innerHTML =
            '<div class="tfp-checkout-payment-complete">' +
                '<h4 class="tfp-checkout-payment-complete-title">Complete with PayPal</h4>' +
                '<div class="tfp-checkout-payment-info">You\'ll be securely redirected to PayPal to complete your purchase. Your order will be confirmed once payment is approved.</div>' +
                '<div id="tfp-paypal-button-container" class="tfp-paypal-btn-container" style="margin-top: 16px;"></div>' +
                '<div id="tfp-paypal-error" class="tfp-stripe-error" role="alert" style="display:none; margin-top: 15px;"></div>' +
            '</div>';

        var S = window.tfpPayPalSettings || {};
        var errorEl = document.getElementById('tfp-paypal-error');

        function setError(msg) {
            if (errorEl) {
                errorEl.textContent = msg;
                errorEl.style.display = msg ? 'block' : 'none';
            }
        }

        if (typeof paypal === 'undefined') {
            setError('PayPal failed to load. Please refresh and try again.');
            return;
        }

        paypal.Buttons({
            style: {
                layout: 'vertical',
                color:  'gold',
                shape:  'rect',
                label:  'paypal'
            },
            createOrder: function(data, actions) {
                return actions.order.create({
                    purchase_units: [{
                        amount: {
                            value: S.total || '0.00'
                        }
                    }]
                });
            },
            onApprove: function(data, actions) {
                // Show a loading state
                setError('Confirming order... Please wait.');
                
                return fetch(S.ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'tfp_checkout_paypal_capture_order',
                        order_id: data.orderID,
                        // Server verifies $_POST['tfp_checkout_nonce'] via
                        // tfp_checkout_verify_request(); the field name must match.
                        tfp_checkout_nonce: S.nonce
                    })
                })
                .then(function(res) { return res.json(); })
                .then(function(res) {
                    if (res && res.success && res.data && res.data.popup && typeof window.tfpOpenCourseOrderConfirmation === 'function') {
                        window.tfpOpenCourseOrderConfirmation(res.data);
                        return;
                    }
                    if (res && res.success && res.data && res.data.redirect) {
                        window.location.href = res.data.redirect;
                    } else {
                        throw new Error((res.data && res.data.message) || 'Could not complete PayPal payment.');
                    }
                })
                .catch(function(err) {
                    setError(err.message);
                });
            },
            onError: function(err) {
                setError('PayPal encountered an error. Please try again.');
                console.error('PayPal Error:', err);
            }
        }).render('#tfp-paypal-button-container');
    }

    window.tfpPayPalRenderMethod = renderPayPal;

})(jQuery);
