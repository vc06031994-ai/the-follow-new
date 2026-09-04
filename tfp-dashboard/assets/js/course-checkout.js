/**
 * Program (course) checkout — modal wizard controller.
 *
 * Drives the two Home-page modals rendered by course-render.php:
 *   #tfp-cohorts-modal          — browse cohorts + reserve a seat
 *   #tfp-course-checkout-modal  — Contact -> Payment
 *
 * A program is NOT a physical product, so there is no shipping step, no
 * shipping address, and no shipping totals — only contact details are saved.
 *
 * Server contract (all nonce-guarded by tfp_checkout_verify_request):
 *   tfp_course_get_cohorts   -> { success, cohorts:[…] }
 *   tfp_course_reserve_seat  -> { success, cohort, summary }
 *   tfp_course_save_contact  -> { success }
 *   tfp_course_paypal_total  -> { success, total }
 *   tfp_checkout_apply_coupon-> { success, summary }
 *
 * The Payment step is handed to the SHARED payment layers verbatim:
 *   - card / Apple Pay / Google Pay -> window.tfpStripeRenderMethod(method, panel)
 *   - PayPal                        -> window.tfpPayPalRenderMethod(panel)
 * both of which read the tfp_checkout_* field IDs / clone #tfp-global-place-order.
 */
(function () {
    var CC = window.tfpCourseCheckout || {};

    document.addEventListener('DOMContentLoaded', function () {
        var cohortsModal  = document.getElementById('tfp-cohorts-modal');
        var checkoutModal = document.getElementById('tfp-course-checkout-modal');
        if (!cohortsModal && !checkoutModal) {
            return;
        }

        var cohortsLoaded  = false;
        var selectedCohort = null;

        /* ---- tiny helpers ---- */

        function ajax(action, extra) {
            var fd = new FormData();
            fd.append('action', action);
            fd.append('tfp_checkout_nonce', CC.nonce || '');
            if (extra) {
                Object.keys(extra).forEach(function (k) { fd.append(k, extra[k]); });
            }
            return fetch(CC.ajaxUrl, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            }).then(function (r) { return r.json(); });
        }

        function esc(str) {
            var d = document.createElement('div');
            d.textContent = (str === null || str === undefined) ? '' : String(str);
            return d.innerHTML;
        }

        function val(id) {
            var el = document.getElementById(id);
            return el ? el.value : '';
        }

        function setHTML(id, html) {
            var el = document.getElementById(id);
            if (el) { el.innerHTML = html; }
        }

        function stripeReady() {
            return !!(window.tfpStripeSettings && window.tfpStripeSettings.isConfigured) &&
                typeof window.tfpStripeRenderMethod === 'function';
        }

        /* ---- modal open / close ---- */

        function openModal(modal) {
            if (!modal) { return; }
            modal.hidden = false;
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('tfp-course-modal-open');
        }

        function closeModal(modal) {
            if (!modal) { return; }
            modal.hidden = true;
            modal.setAttribute('aria-hidden', 'true');
            if (!document.querySelector('.tfp-dash-modal:not([hidden])')) {
                document.body.classList.remove('tfp-course-modal-open');
            }
        }

        window.tfpOpenCourseOrderConfirmation = function (response) {
            var modal = document.getElementById('tfp-order-confirmation-modal');
            var details = document.getElementById('tfp-order-confirmation-details');
            var number = document.getElementById('tfp-order-confirmation-number');
            if (!modal || !response || !response.order_id || !response.order_key) {
                if (response && response.redirect) { window.location.href = response.redirect; }
                return false;
            }

            closeModal(checkoutModal);
            modal.hidden = false;
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('tfp-course-modal-open');
            if (details) { details.innerHTML = '<p class="tfp-order-confirmation-modal__loading">Loading order details...</p>'; }
            if (number) { number.textContent = ''; }

            var fd = new FormData();
            fd.append('action', 'tfp_render_order_confirmation');
            fd.append('nonce', CC.nonce || '');
            fd.append('order_id', response.order_id);
            fd.append('order_key', response.order_key);
            fetch(CC.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (result) { return result.json(); })
                .then(function (result) {
                    if (!result || !result.success || !result.data) {
                        throw new Error('Could not load order details.');
                    }
                    if (number) { number.innerHTML = 'Order: ' + result.data.number; }
                    if (details) { details.innerHTML = result.data.html; }
                })
                .catch(function () {
                    if (details) { details.innerHTML = '<p class="tfp-order-confirmation-modal__loading">Could not load order details.</p>'; }
                });
            return true;
        };

        var confirmationOrderId = new URLSearchParams(window.location.search).get('order_id');
        var confirmationOrderKey = new URLSearchParams(window.location.search).get('order_key');
        if (confirmationOrderId && confirmationOrderKey) {
            window.tfpOpenCourseOrderConfirmation({
                order_id: confirmationOrderId,
                order_key: confirmationOrderKey
            });
        }

        // The checkout modal has two top-level views: the Order-Summary review
        // (View A, shown first after a seat is reserved) and the Contact/Payment
        // wizard (View B). Toggle between them without touching the tested
        // payment plumbing inside View B.
        function showCourseView(view) {
            if (!checkoutModal) { return; }
            checkoutModal.querySelectorAll('[data-course-view]').forEach(function (el) {
                el.style.display = (el.getAttribute('data-course-view') === view) ? '' : 'none';
            });
        }

        /* ---- cohort list ---- */

        function loadCohorts(force) {
            var list = document.getElementById('tfp-cohorts-list');
            if (!list) { return; }
            if (cohortsLoaded && !force) { return; }

            list.innerHTML = '<p class="tfp-cohorts-loading">' + esc('Loading cohorts…') + '</p>';

            ajax('tfp_course_get_cohorts', { course_id: CC.courseId || 0 }).then(function (res) {
                if (!res || !res.success) {
                    list.innerHTML = '<p class="tfp-cohorts-error">' + esc((res && res.message) || 'Could not load cohorts.') + '</p>';
                    return;
                }
                renderCohorts(res.cohorts || []);
                cohortsLoaded = true;
            }).catch(function () {
                list.innerHTML = '<p class="tfp-cohorts-error">' + esc('Could not load cohorts. Please try again.') + '</p>';
            });
        }

        function renderCohorts(cohorts) {
            var list = document.getElementById('tfp-cohorts-list');
            if (!list) { return; }

            if (!cohorts.length) {
                list.innerHTML = '<p class="tfp-cohorts-empty">' + esc('No cohorts are open right now. Please check back soon.') + '</p>';
                return;
            }

            var html = '';
            cohorts.forEach(function (c) {
                var meta = [
                    c.start_label ? 'Starts ' + c.start_label : '',
                    c.schedule,
                    c.facilitator ? 'Instructor: ' + c.facilitator : ''
                ].filter(Boolean).map(esc).join(' &bull; ');
                var isSelected = selectedCohort && String(selectedCohort.id) === String(c.id);

                html += '<div class="tfp-cohort-row' + (c.is_full ? ' is-full' : '') + (isSelected ? ' is-selected' : '') + '" data-cohort-id="' + esc(c.id) + '">';
                html += '<div class="tfp-cohort-row__main">';
                html += '<div class="tfp-cohort-row__name">' + esc(c.name) + '</div>';
                if (meta) { html += '<div class="tfp-cohort-row__meta">' + meta + '</div>'; }
                html += '<div class="tfp-cohort-row__seats' + (c.is_full ? ' is-full' : '') + '">' + esc(c.seats_label) + '</div>';
                html += '</div>';
                html += '<div class="tfp-cohort-row__aside">';
                if (c.price_html) { html += '<div class="tfp-cohort-row__price">' + c.price_html + '</div>'; }
                if (c.is_full) {
                    html += '<button type="button" class="tfp-dash-btn tfp-dash-btn--primary" disabled>' + esc('Full') + '</button>';
                } else if (isSelected) {
                    html += '<button type="button" class="tfp-dash-btn tfp-dash-btn--primary tfp-cohort-reserve is-selected" data-cohort-id="' + esc(c.id) + '">' + esc('Seat Selected') + '</button>';
                } else {
                    html += '<button type="button" class="tfp-dash-btn tfp-dash-btn--primary tfp-cohort-reserve" data-cohort-id="' + esc(c.id) + '">' + esc('Reserve seat') + '</button>';
                }
                html += '</div></div>';
            });
            list.innerHTML = html;
        }

        function markSelected(cohortId) {

    var list = document.getElementById('tfp-cohorts-list');

    if (!list) {
        return;
    }

    list.querySelectorAll('.tfp-cohort-row').forEach(function (row) {

        var isThis = row.getAttribute('data-cohort-id') === String(cohortId);

        row.classList.toggle('is-selected', isThis);

        var btn = row.querySelector('.tfp-cohort-reserve');

        if (!btn) {
            return;
        }

        if (isThis) {

            btn.textContent = 'Seat Selected';

            // Keep primary button
            btn.classList.add('tfp-dash-btn--primary');

            // Remove outline if it was previously added
            btn.classList.remove('tfp-dash-btn--outline');

            // Keep selected class if you need it for styling
            btn.classList.add('is-selected');

        } else {

            btn.textContent = 'Reserve seat';

            btn.classList.add('tfp-dash-btn--primary');
            btn.classList.remove('tfp-dash-btn--outline', 'is-selected');

        }

    });
}
        function reserveSeat(cohortId) {
            var ackWrap = document.querySelector('.tfp-cohort-ack');
            var ack = document.getElementById('tfp-cohort-ack');
            if (!ack || !ack.checked) {
                if (ackWrap) { ackWrap.classList.add('tfp-cohort-ack--error'); }
                return;
            }
            if (ackWrap) { ackWrap.classList.remove('tfp-cohort-ack--error'); }

            ajax('tfp_course_reserve_seat', { cohort_id: cohortId, acknowledged: 1 }).then(function (res) {
                if (!res || !res.success) {
                    showCohortError((res && res.message) || 'We could not reserve that seat.');
                    // A "just filled up" error means the list is stale — refresh it.
                    loadCohorts(true);
                    return;
                }
                selectedCohort = res.cohort;
                markSelected(cohortId);
                var cont = document.getElementById('tfp-cohorts-continue');
                if (cont) { cont.disabled = false; }
                if (res.summary) { renderSummary(res.summary); }
                renderSummaryCohort(res.cohort);
            }).catch(function () {
                showCohortError('Something went wrong reserving your seat. Please try again.');
            });
        }

        function showCohortError(msg) {
            var list = document.getElementById('tfp-cohorts-list');
            if (!list) { return; }
            var existing = list.querySelector('.tfp-cohorts-inline-error');
            if (existing) { existing.remove(); }
            var el = document.createElement('p');
            el.className = 'tfp-cohorts-inline-error';
            el.textContent = msg;
            list.insertBefore(el, list.firstChild);
        }

        /* ---- order summary ---- */

        function renderSummary(s) {
            if (!s) { return; }
            // Totals appear twice: the Order-Summary review view (View A) and the
            // running sidebar during Contact/Payment (View B). Update both mirrors;
            // setHTML() no-ops when an id is absent.
            if (s.subtotal) { setHTML('tfp-course-sum-subtotal', s.subtotal); setHTML('tfp-review-subtotal', s.subtotal); }
            if (s.tax)      { setHTML('tfp-course-sum-tax', s.tax);           setHTML('tfp-review-tax', s.tax); }
            if (s.total)    { setHTML('tfp-course-sum-total', s.total);       setHTML('tfp-review-total', s.total); }

            // Financial aid is auto-applied server-side; the discounted figure is
            // already reflected in the total. Surface the line as a confirmation
            // when the student has approved aid — in both mirrors.
            if (CC.faApproved) {
                var faLine = document.getElementById('tfp-course-fa-line');
                if (faLine) { faLine.style.display = ''; setHTML('tfp-course-sum-discount', 'Applied'); }
                var reviewFa = document.getElementById('tfp-review-fa-line');
                if (reviewFa) { reviewFa.style.display = ''; setHTML('tfp-review-discount', 'Applied'); }
            }
        }

        function renderSummaryCohort(c) {
            if (!c) { return; }
            var sub  = c.schedule ? c.schedule : (c.start_label || '');
            var meta = c.facilitator ? ('Instructor: ' + c.facilitator) : '';

            // Keep the running sidebar item as product details; only its price
            // changes when a cohort is selected.
            setHTML('tfp-course-summary-price', c.price_html || '');

            // Selected-cohort card in the Order-Summary review view (View A).
            var reviewCard = document.getElementById('tfp-review-cohort');
            if (reviewCard) {
                reviewCard.className = 'tfp-cohort-row is-selected';
                reviewCard.innerHTML =
                    '<div class="tfp-cohort-row__main">' +
                    '<div class="tfp-cohort-row__name">' + esc(c.name) + '</div>' +
                    (sub ? '<div class="tfp-cohort-row__meta">' + esc(sub) + '</div>' : '') +
                    (meta ? '<div class="tfp-cohort-row__meta">' + esc(meta) + '</div>' : '') +
                    '</div>' +
                    '<div class="tfp-cohort-row__aside">' +
                    '<div class="tfp-dash-btn tfp-dash-btn--primary">' + esc('Seat Selected') + '</div>' +
                    (c.price_html ? '<div class="tfp-cohort-row__price">' + c.price_html + '</div>' : '') +
                    '</div>';
            }
            setHTML('tfp-review-line-price', c.price_html || '');
        }

        /* ---- step engine ---- */

        function setActiveStep(stepName) {
            if (!checkoutModal) { return; }

            checkoutModal.querySelectorAll('.tfp-checkout-panel').forEach(function (panel) {
                var s = panel.getAttribute('data-step');
                if (!s) { return; }
                if (s === stepName) {
                    panel.style.display = panel.classList.contains('tfp-contact-panel') ? 'flex' : 'block';
                } else {
                    panel.style.display = 'none';
                }
            });

            checkoutModal.querySelectorAll('.tfp-checkout-step-tab').forEach(function (tab) {
                var active = tab.getAttribute('data-step-link') === stepName;
                tab.classList.toggle('is-active', active);
                if (active) {
                    var title = tab.getAttribute('data-step-title');
                    var sub   = tab.getAttribute('data-step-subtitle');
                    var te = checkoutModal.querySelector('.tfp-checkout-page-title');
                    var se = checkoutModal.querySelector('.tfp-checkout-subtitle');
                    if (te && title) { te.textContent = title; }
                    if (se && sub)   { se.textContent = sub; }
                }
            });

            if (stepName === 'payment') {
                enterPaymentStep();
            }
        }

        function enablePaymentTab() {
            var tab = checkoutModal.querySelector('.tfp-checkout-step-tab[data-step-link="payment"]');
            if (tab) { tab.classList.remove('is-disabled'); }
        }

        function continueToPayment(btn) {
            var errEl = document.getElementById('tfp-course-contact-error');
            if (errEl) { errEl.textContent = ''; }

            var payload = {
                first_name: val('tfp_checkout_first_name'),
                last_name:  val('tfp_checkout_last_name'),
                email:      val('tfp_checkout_email'),
                phone:      val('tfp_checkout_phone')
            };

            var original = btn ? btn.innerHTML : '';
            if (btn) { btn.disabled = true; btn.innerHTML = 'Saving…'; }

            function restore() {
                if (btn) { btn.disabled = false; btn.innerHTML = original; }
            }

            // A program is virtual — no shipping address, no shipping method to
            // prime. Save the contact details, then straight on to Payment.
            ajax('tfp_course_save_contact', payload).then(function (res) {
                if (!res || !res.success) {
                    if (errEl) { errEl.textContent = (res && res.message) || 'Please complete all required fields.'; }
                    restore();
                    return;
                }
                restore();
                enablePaymentTab();
                setActiveStep('payment');
            }).catch(function () {
                if (errEl) { errEl.textContent = 'Something went wrong. Please try again.'; }
                restore();
            });
        }

        /* ---- payment step ---- */

        // Keep the wallet amount in sync with the live cart total (the program
        // product is only added to the cart when a seat is reserved, so the
        // total localized at page load is stale). Both PayPal and Google Pay
        // read window.tfpPayPalSettings.total, so this feeds both. Resolves
        // regardless of outcome.
        function refreshCartTotal() {
            return ajax('tfp_course_paypal_total', {}).then(function (res) {
                if (res && res.success && res.total !== undefined && res.total !== null) {
                    window.tfpPayPalSettings = window.tfpPayPalSettings || {};
                    window.tfpPayPalSettings.total = String(res.total);
                    return parseFloat(res.total);
                }
                return null;
            }).catch(function () { return null; /* keep whatever total we already have */ });
        }

        function loadPaymentMethod(method) {
            var panel = document.getElementById('tfp-checkout-payment-panel');
            if (!panel) { return; }

            // The footer "Place Order" button is the pay button for card only;
            // wallets and PayPal render their own button inside the panel.
            var placeBtn = document.getElementById('tfp-global-place-order');
            if (placeBtn) {
                var wrap = placeBtn.closest('.tfp-checkout-actions');
                if (wrap) { wrap.classList.toggle('hide-place-order', method !== 'credit'); }
            }

            // PayPal is independent of Stripe — render it whether or not Stripe
            // keys are configured, using the shared checkout-paypal.js layer.
            if (method === 'paypal') {
                if (typeof window.tfpPayPalRenderMethod === 'function') {
                    refreshCartTotal().then(function () {
                        window.tfpPayPalRenderMethod(panel);
                    });
                } else {
                    panel.innerHTML =
                        '<div class="tfp-checkout-payment-complete">' +
                        '<h4 class="tfp-checkout-payment-complete-title">' + esc('PayPal is not available yet') + '</h4>' +
                        '<div class="tfp-checkout-payment-info">' + esc('Please choose Credit/Debit card, or contact support to complete your enrollment.') + '</div>' +
                        '</div>';
                }
                return;
            }

            // Google Pay uses the native Google Pay SDK button (the shared
            // checkout-googlepay.js layer, window.tfpGooglePayRenderMethod) —
            // identical to the book checkout. It tokenizes through Stripe, so it
            // is only available when Stripe keys are configured. This is NOT the
            // Stripe Payment Request Button (which needs a device wallet and
            // usually won't render on staging).
            if (method === 'googlepay') {
                if (typeof window.tfpGooglePayRenderMethod === 'function') {
                    refreshCartTotal().then(function () {
                        window.tfpGooglePayRenderMethod(panel);
                    });
                    return;
                }
                // No native layer (Stripe not configured) — fall through to the
                // Stripe/placeholder handling below.
            }

            if (stripeReady()) {
                window.tfpStripeRenderMethod(method, panel);
                return;
            }

            // Stripe not configured yet — friendly placeholder.
            panel.innerHTML =
                '<div class="tfp-checkout-payment-complete">' +
                '<h4 class="tfp-checkout-payment-complete-title">' + esc('Payment is not available yet') + '</h4>' +
                '<div class="tfp-checkout-payment-info">' + esc('Online payment is being set up. Please check back shortly or contact support to complete your enrollment.') + '</div>' +
                '</div>';
        }

        // Entering the Payment step: when approved financial aid covers the full
        // fee the live cart total is $0, which no gateway can charge (Stripe
        // rejects sub-minimum amounts; PayPal can't authorize $0). In that case
        // swap the payment-method chooser for a single "Complete Enrollment"
        // action wired to the server-side free path. Otherwise behave normally.
        function enterPaymentStep() {
            var grid    = checkoutModal.querySelector('.tfp-checkout-method-grid');
            var heading = checkoutModal.querySelector('.tfp-course-pay-heading');

            refreshCartTotal().then(function (total) {
                // total === null means we couldn't read it — fall back to the
                // normal paid flow (the server guards both paths regardless).
                if (total !== null && total <= 0) {
                    if (grid)    { grid.style.display = 'none'; }
                    if (heading) { heading.style.display = 'none'; }
                    renderFreeEnrollment();
                } else {
                    if (grid)    { grid.style.display = ''; }
                    if (heading) { heading.style.display = ''; }
                    loadPaymentMethod('credit');
                }
            });
        }

        function renderFreeEnrollment() {
            var panel = document.getElementById('tfp-checkout-payment-panel');
            if (!panel) { return; }

            // No charge — hide the card "Place Order" button; this panel carries
            // its own action.
            var placeBtn = document.getElementById('tfp-global-place-order');
            if (placeBtn) {
                var wrap = placeBtn.closest('.tfp-checkout-actions');
                if (wrap) { wrap.classList.add('hide-place-order'); }
            }

            panel.innerHTML =
                '<div class="tfp-checkout-payment-complete">' +
                '<h4 class="tfp-checkout-payment-complete-title">' + esc('No payment required') + '</h4>' +
                '<div class="tfp-checkout-payment-info">' + esc('Your financial aid covers the full cost of this program. Click below to complete your enrollment — no payment is needed.') + '</div>' +
                '<button type="button" class="tfp-dash-btn tfp-dash-btn--primary tfp-course-free-enroll" style="margin-top:16px;">' + esc('Complete Enrollment') + '</button>' +
                '<div class="tfp-course-checkout-error" id="tfp-course-free-error" role="alert"></div>' +
                '</div>';
        }

        function completeFreeEnrollment(btn) {
            var errEl = document.getElementById('tfp-course-free-error');
            if (errEl) { errEl.textContent = ''; }

            var original = btn ? btn.innerHTML : '';
            if (btn) { btn.disabled = true; btn.innerHTML = 'Enrolling…'; }

            ajax('tfp_course_free_enroll', {}).then(function (res) {
                if (!res || !res.success) {
                    if (errEl) { errEl.textContent = (res && res.message) || 'Could not complete enrollment. Please try again.'; }
                    if (btn) { btn.disabled = false; btn.innerHTML = original; }
                    return;
                }
                // Leave the button disabled so a stalled confirmation cannot double-submit.
                if (res.popup) {
                    window.tfpOpenCourseOrderConfirmation(res);
                    return;
                }
                window.location.href = res.redirect || '/';
            }).catch(function () {
                if (errEl) { errEl.textContent = 'Something went wrong. Please try again.'; }
                if (btn) { btn.disabled = false; btn.innerHTML = original; }
            });
        }

        /* ---- financial-aid code ---- */

        function applyFaCode() {
            var input = document.querySelector('.tfp-course-fa-input');
            var msg = document.getElementById('tfp-course-fa-message');
            var code = input ? input.value.trim() : '';
            if (msg) { msg.textContent = ''; msg.className = 'tfp-course-fa-message'; }
            if (!code) {
                if (msg) { msg.textContent = 'Please enter a code.'; msg.classList.add('is-error'); }
                return;
            }
            ajax('tfp_checkout_apply_coupon', { coupon_code: code }).then(function (res) {
                if (!res || !res.success) {
                    if (msg) { msg.textContent = (res && res.message) || 'That code could not be applied.'; msg.classList.add('is-error'); }
                    return;
                }
                if (res.summary) { renderSummary(res.summary); }
                if (msg) { msg.textContent = 'Financial aid code applied.'; msg.classList.add('is-success'); }
            }).catch(function () {
                if (msg) { msg.textContent = 'Something went wrong applying that code.'; msg.classList.add('is-error'); }
            });
        }

        /* ---- event wiring ---- */

        document.addEventListener('click', function (event) {
            // Open cohorts (program card button or "Back to Cohort").
            var openCohorts = event.target.closest('[data-tfp-open-cohorts]');
            if (openCohorts) {
                event.preventDefault();
                if (checkoutModal && !checkoutModal.hidden) { closeModal(checkoutModal); }
                openModal(cohortsModal);
                loadCohorts(false);
                return;
            }

            // Close any modal.
            var closer = event.target.closest('[data-tfp-close]');
            if (closer) {
                event.preventDefault();
                closeModal(closer.closest('.tfp-dash-modal'));
                return;
            }

            var confirmationCloser = event.target.closest('[data-tfp-order-confirmation-close]');
            if (confirmationCloser) {
                event.preventDefault();
                var confirmationModal = document.getElementById('tfp-order-confirmation-modal');
                if (confirmationModal) {
                    confirmationModal.hidden = true;
                    confirmationModal.setAttribute('aria-hidden', 'true');
                    document.body.classList.remove('tfp-course-modal-open');
                    window.location.reload();
                }
                return;
            }

            // Reserve a seat.
            var reserve = event.target.closest('.tfp-cohort-reserve');
            if (reserve && !reserve.classList.contains('is-selected')) {
                event.preventDefault();
                reserveSeat(reserve.getAttribute('data-cohort-id'));
                return;
            }

            // "Save" the cohort selection -> open the checkout modal on the
            // Order-Summary review view (View A). Payment is still one step away.
            var cohortsContinue = event.target.closest('#tfp-cohorts-continue');
            if (cohortsContinue) {
                event.preventDefault();
                if (cohortsContinue.disabled) { return; }
                closeModal(cohortsModal);
                openModal(checkoutModal);
                showCourseView('review');
                return;
            }

            // Review "Continue to Payment" -> reveal the Contact/Payment wizard.
            var reviewContinue = event.target.closest('.tfp-course-review-continue');
            if (reviewContinue) {
                event.preventDefault();
                showCourseView('wizard');
                setActiveStep('contact');
                return;
            }

            // Contact -> payment.
            var continueBtn = event.target.closest('.tfp-course-continue-payment');
            if (continueBtn) {
                event.preventDefault();
                continueToPayment(continueBtn);
                return;
            }

            // Payment -> back to contact.
            var backContact = event.target.closest('.tfp-course-back-contact');
            if (backContact) {
                event.preventDefault();
                setActiveStep('contact');
                return;
            }

            // Free ($0 financial aid) enrollment — no gateway.
            var freeEnroll = event.target.closest('.tfp-course-free-enroll');
            if (freeEnroll) {
                event.preventDefault();
                completeFreeEnrollment(freeEnroll);
                return;
            }

            // Payment method switch.
            var methodCard = event.target.closest('.tfp-checkout-method-card');
            if (methodCard && checkoutModal && checkoutModal.contains(methodCard)) {
                checkoutModal.querySelectorAll('.tfp-checkout-method-card').forEach(function (el) {
                    el.classList.toggle('is-selected', el === methodCard);
                });
                loadPaymentMethod(methodCard.getAttribute('data-method'));
                return;
            }

            // Step tab navigation (ignore disabled tabs).
            var tab = event.target.closest('.tfp-checkout-step-tab');
            if (tab && checkoutModal && checkoutModal.contains(tab)) {
                event.preventDefault();
                if (tab.classList.contains('is-disabled')) { return; }
                setActiveStep(tab.getAttribute('data-step-link'));
                return;
            }

            // Financial-aid code toggle.
            var faToggle = event.target.closest('.tfp-course-fa-toggle');
            if (faToggle) {
                event.preventDefault();
                var faPanel = document.querySelector('.tfp-course-fa-panel');
                if (faPanel) { faPanel.style.display = faPanel.style.display === 'none' ? 'flex' : 'none'; }
                return;
            }

            // Financial-aid code apply.
            var faApply = event.target.closest('.tfp-course-fa-apply');
            if (faApply) {
                event.preventDefault();
                applyFaCode();
                return;
            }

            // Cancel registration (confirm -> logout).
            var cancelReg = event.target.closest('[data-tfp-cancel-registration]');
            if (cancelReg) {
                event.preventDefault();
                var ok = window.confirm('Cancel your registration and sign out? You can register again anytime.');
                if (ok) {
                    var url = cancelReg.getAttribute('data-logout-url');
                    window.location.href = url || '/wp-login.php?action=logout';
                }
                return;
            }
        });

        // Clear the acknowledgement error as soon as the box is ticked.
        document.addEventListener('change', function (event) {
            if (event.target && event.target.id === 'tfp-cohort-ack' && event.target.checked) {
                var ackWrap = document.querySelector('.tfp-cohort-ack');
                if (ackWrap) { ackWrap.classList.remove('tfp-cohort-ack--error'); }
            }
        });

        // Close on Escape.
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                var open = document.querySelector('.tfp-dash-modal:not([hidden])');
                if (open) { closeModal(open); }
            }
        });
    });
})();
