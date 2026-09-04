<?php
/**
 * Course (program) checkout — front-end markup.
 *
 * Renders two hidden modals into the dashboard Home footer:
 *   1. #tfp-cohorts-modal          — Browse Cohorts / Reserve a seat
 *   2. #tfp-course-checkout-modal  — Contact + Payment wizard
 *
 * The wizard deliberately reuses the book checkout's markup contract so the
 * shared Stripe layer (assets/js/checkout-stripe.js) works verbatim:
 *   - the contact field IDs (tfp_checkout_first_name … tfp_checkout_postcode)
 *     are the exact IDs checkout-stripe.js reads for billing details;
 *   - the payment step contains #tfp-checkout-payment-panel and the single
 *     #tfp-global-place-order button that renderCard() clones in place.
 *
 * Only the step engine differs: course-checkout.js drives these modals, not
 * checkout.js (which is not loaded on Home).
 */

if (!defined('ABSPATH')) exit;

/**
 * Output both modals. Called from tfp_dashboard_render_home_content() for any
 * student who has not yet enrolled.
 */
function tfp_course_render_modals($state = null)
{
    tfp_course_render_cohorts_modal($state);
    tfp_course_render_checkout_modal($state);
}

/* -------------------------------------------------------------------------
 * Modal 1 — Browse Cohorts
 * ---------------------------------------------------------------------- */

function tfp_course_render_cohorts_modal($state = null)
{
    ?>
    <div class="tfp-dash-modal tfp-course-modal" id="tfp-cohorts-modal" hidden aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="tfp-cohorts-modal-title">
        <div class="tfp-dash-modal__backdrop" data-tfp-close></div>
        <div class="tfp-dash-modal__box tfp-dash-modal__box--wide">
            <button type="button" class="tfp-modal-close" data-tfp-close aria-label="<?php esc_attr_e('Close', 'tfp-dashboard'); ?>">&times;</button>

            <div class="tfp-course-modal__head">
                <h6 id="tfp-cohorts-modal-title"><?php esc_html_e('Browse Cohorts', 'tfp-dashboard'); ?></h6>
                <p class="tfp-course-modal__lead"><?php esc_html_e('Register and process payment immediately. Cohort changes are allowed within 24 hours of enrollment if space is available.', 'tfp-dashboard'); ?></p>
            </div>

            <div class="tfp-cohorts-list" id="tfp-cohorts-list" aria-live="polite">
                <p class="tfp-cohorts-loading"><?php esc_html_e('Loading cohorts…', 'tfp-dashboard'); ?></p>
            </div>

            <label class="tfp-cohort-ack" for="tfp-cohort-ack">
                <input type="checkbox" id="tfp-cohort-ack" />
                <span class="tfp-cohort-ack__control" aria-hidden="true"></span>
                <span class="tfp-cohort-ack__text">
                    <span class="tfp-cohort-ack__line"><?php esc_html_e('I agree and understand that this program is non-refundable.', 'tfp-dashboard'); ?> <span class="tfp-cohort-ack__terms"><?php esc_html_e('(Terms & Conditions)', 'tfp-dashboard'); ?></span></span>
                    <span class="tfp-cohort-ack__note"><?php esc_html_e('Acknowledged — seat selection and cohort changes are subject to availability.', 'tfp-dashboard'); ?></span>
                </span>
            </label>

            <div class="tfp-course-modal__foot">
                  <button type="button" class="tfp-save-btn tfp-course-btn--icon" id="tfp-cohorts-continue" disabled>
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
<path d="M7 21H6.19691C5.07899 21 4.5192 21 4.0918 20.7822C3.71547 20.5905 3.40973 20.2837 3.21799 19.9074C3 19.4796 3 18.9203 3 17.8002V6.2002C3 5.08009 3 4.51962 3.21799 4.0918C3.40973 3.71547 3.71547 3.40973 4.0918 3.21799C4.51962 3 5.08009 3 6.2002 3H15.075C15.5998 3 15.8625 3 16.1073 3.06287C16.3242 3.11858 16.5303 3.21 16.7168 3.33398C16.9242 3.47181 17.0969 3.66374 17.4377 4.04241L20.1929 7.10378C20.487 7.43055 20.6366 7.59674 20.7432 7.78595C20.8393 7.95652 20.9095 8.14 20.9521 8.33105C21 8.54521 21 8.77072 21 9.21955V17.8031C21 18.921 21 19.4806 20.7822 19.908C20.5905 20.2843 20.2837 20.5905 19.9074 20.7822C19.48 21 18.921 21 17.8031 21L17 21.0002L7 21ZM17 21.0002V17.1969C17 16.079 17 15.5192 16.7822 15.0918C16.5905 14.7155 16.2837 14.4097 15.9074 14.218C15.4796 14 14.9203 14 13.8002 14H10.2002C9.08009 14 8.51962 14 8.0918 14.218C7.71547 14.4097 7.40973 14.7155 7.21799 15.0918C7 15.5196 7 16.0801 7 17.2002V21M15 7H9" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
</svg>
                    <?php esc_html_e('Save', 'tfp-dashboard'); ?>
                </button>
                <button type="button" class="tfp-cancel-btn tfp-course-btn--icon" data-tfp-close>
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
<path d="M3.75 3.75L16.25 16.25M10 19C5.02944 19 1 14.9706 1 10C1 5.02944 5.02944 1 10 1C14.9706 1 19 5.02944 19 10C19 14.9706 14.9706 19 10 19Z" stroke="#151411" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
</svg>
                    <?php esc_html_e('Cancel', 'tfp-dashboard'); ?>
                </button>
              
            </div>
        </div>
    </div>
    <?php
}

/**
 * A single cohort row. Rendered on the server so the markup is identical
 * whether it comes from PHP or (in future) an AJAX refresh; today the list
 * is built client-side from tfp_course_get_cohorts, but this template
 * documents the exact shape course-checkout.js produces.
 */

/* -------------------------------------------------------------------------
 * Modal 2 — Contact + Payment wizard
 * ---------------------------------------------------------------------- */

function tfp_course_render_checkout_modal($state = null)
{
    $data = function_exists('tfp_checkout_customer_defaults') ? tfp_checkout_customer_defaults() : [
        'first_name' => '', 'last_name' => '', 'email' => '', 'phone' => '',
        'street' => '', 'apt' => '', 'city' => '', 'state' => '', 'postcode' => '',
    ];

    $steps = [
        'contact' => __('Contact', 'tfp-dashboard'),
        'payment' => __('Payment', 'tfp-dashboard'),
    ];
    $step_titles = [
        'contact' => __('Contact Information', 'tfp-dashboard'),
        'payment' => __('Payment Details', 'tfp-dashboard'),
    ];
    $step_subtitles = [
        'contact' => __('Please provide your contact information.', 'tfp-dashboard'),
        'payment' => __('Choose your payment method and complete your enrollment.', 'tfp-dashboard'),
    ];

    // Approved-aid gate (mirrors tfp_course_render_summary): approved students
    // get their FA coupon auto-applied, so the manual code entry is hidden.
    $fa_approved = $state && !empty($state['fa_status']) && $state['fa_status'] === 'approved';
    $fa_discount = $state && !empty($state['fa_discount']) ? (int) $state['fa_discount'] : 0;

    // Program product image + name for the Order-Summary review view. The image
    // is program-level (identical for every cohort) so it is rendered server-side
    // once here; the cohort-specific bits (name, price) are filled by JS.
    $product_img  = '';
    $product_name = __('The Follow Program', 'tfp-dashboard');
    if (function_exists('tfp_ld_get_program_course_id') && function_exists('wc_get_product')) {
        $review_course_id  = (int) tfp_ld_get_program_course_id();
        $review_product_id = ($review_course_id && function_exists('tfp_billing_get_product_id_for_course'))
            ? (int) tfp_billing_get_product_id_for_course($review_course_id)
            : 0;
        $review_product = $review_product_id ? wc_get_product($review_product_id) : null;
        if ($review_product) {
            $product_name = $review_product->get_name();
            $product_img  = $review_product->get_image('woocommerce_thumbnail');
        }
    }
    ?>
    <div class="tfp-dash-modal tfp-course-modal" id="tfp-course-checkout-modal" hidden aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="tfp-course-checkout-title">
        <div class="tfp-dash-modal__backdrop" data-tfp-close></div>
        <div class="tfp-dash-modal__box tfp-dash-modal__box--wide tfp-dash-modal__box--checkout">
            <button type="button" class="tfp-modal-close" data-tfp-close aria-label="<?php esc_attr_e('Close', 'tfp-dashboard'); ?>">&times;</button>

            <?php // View A — Order Summary review (shown first after a seat is reserved). ?>
            <div class="tfp-course-review" data-course-view="review">
                <div class="tfp-course-review__grid">
                    <div class="tfp-course-review__left">
                        <div class="tfp-course-review__left-header">
                            <h3 class="tfp-course-review__left-title"><?php esc_html_e('Selected Cohort', 'tfp-dashboard'); ?></h3>
                            <p class="tfp-course-review__left-description"><?php esc_html_e('Review your selected program cohort before continuing to payment.', 'tfp-dashboard'); ?></p>
                        </div>
                        <div class="tfp-review-cohort" id="tfp-review-cohort">
                            <p class="tfp-review-cohort__empty"><?php esc_html_e('No cohort selected yet.', 'tfp-dashboard'); ?></p>
                        </div>
                        <button type="button" class="tfp-review-change tfp-cancel-btn" data-tfp-open-cohorts>
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
<path d="M3.75 3.75L16.25 16.25M10 19C5.02944 19 1 14.9706 1 10C1 5.02944 5.02944 1 10 1C14.9706 1 19 5.02944 19 10C19 14.9706 14.9706 19 10 19Z" stroke="#151411" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
</svg>
                            <?php esc_html_e('Change Seat', 'tfp-dashboard'); ?>
                        </button>
                    </div>

                    <aside class="tfp-checkout-order-summary tfp-course-review__summary">
                        <h5><?php esc_html_e('Order Summary', 'tfp-dashboard'); ?></h5>

                        <div class="tfp-checkout-cart-item">
                            <div class="tfp-checkout-cart-image"><?php echo $product_img; // WC-escaped <img> ?></div>
                            <div class="tfp-review-product__copy">
                                <div class="tfp-checkout-cart-name"><?php echo esc_html($product_name); ?></div>
                                <div class="tfp-checkout-cart-meta"><?php esc_html_e('Online Course · Qty: 1', 'tfp-dashboard'); ?></div>
                            </div>
                            <div class="tfp-checkout-cart-price" id="tfp-review-line-price">&mdash;</div>
                        </div>

                        <div class="tfp-checkout-cart-totals tfp-review-totals">
                            <div class="tfp-checkout-totals-row">
                                <span><?php esc_html_e('Subtotal', 'tfp-dashboard'); ?></span>
                                <strong id="tfp-review-subtotal">&mdash;</strong>
                            </div>
                            <div class="tfp-checkout-totals-row tfp-course-fa-row" id="tfp-review-fa-line" <?php echo $fa_approved ? '' : 'style="display:none;"'; ?>>
                                <span><?php esc_html_e('Financial Aid', 'tfp-dashboard'); ?><?php echo $fa_discount ? ' (' . esc_html($fa_discount) . '% ' . esc_html__('off', 'tfp-dashboard') . ')' : ''; ?></span>
                                <strong id="tfp-review-discount">&mdash;</strong>
                            </div>
                            <div class="tfp-checkout-totals-row">
                                <span><?php esc_html_e('Tax', 'tfp-dashboard'); ?></span>
                                <strong id="tfp-review-tax">&mdash;</strong>
                            </div>
                            <div class="tfp-checkout-totals-row tfp-checkout-totals-final">
                                <span><?php esc_html_e('Total Due', 'tfp-dashboard'); ?></span>
                                <strong id="tfp-review-total">&mdash;</strong>
                            </div>
                        </div>

                        <button type="button" class="tfp-dash-btn tfp-dash-btn--primary tfp-course-review-continue">
                            <?php esc_html_e('Continue to Payment', 'tfp-dashboard'); ?>
                            <svg width="7" height="10" viewBox="0 0 5 8" fill="none" aria-hidden="true"><path d="M0.06 0.94L3.113 4L0.06 7.06L1 8L5 4L1 0L0.06 0.94Z" fill="currentColor"/></svg>
                        </button>
                    </aside>
                </div>
            </div>

            <?php // View B — Contact + Payment wizard (revealed by "Continue to Payment"). ?>
            <div class="tfp-checkout-page-wrap tfp-course-checkout" data-course-view="wizard" style="display:none;">

                <div class="tfp-checkout-step-tabs" aria-label="<?php esc_attr_e('Checkout progress', 'tfp-dashboard'); ?>">
                    <?php $first = true; foreach ($steps as $name => $label) : ?>
                        <a href="#" class="tfp-checkout-step-tab<?php echo $first ? ' is-active' : ' is-disabled'; ?>"
                           data-step-link="<?php echo esc_attr($name); ?>"
                           data-step-title="<?php echo esc_attr($step_titles[$name]); ?>"
                           data-step-subtitle="<?php echo esc_attr($step_subtitles[$name]); ?>">
                            <?php echo esc_html($label); ?>
                        </a>
                    <?php $first = false; endforeach; ?>
                </div>

                <div class="tfp-checkout-title-header">
                    <h1 class="tfp-checkout-page-title" id="tfp-course-checkout-title"><?php echo esc_html($step_titles['contact']); ?></h1>
                    <p class="tfp-checkout-subtitle"><?php echo esc_html($step_subtitles['contact']); ?></p>
                </div>

                <div class="tfp-checkout-layout">
                    <div class="tfp-checkout-main-column">
                        <?php
                        tfp_course_render_contact_panel($data);
                        tfp_course_render_payment_panel();
                        ?>
                    </div>
                    <div class="tfp-checkout-sidebar-column">
                        <?php tfp_course_render_summary($state); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Contact panel. Field IDs mirror render.php exactly — the shared Stripe layer
 * reads them for billing details. A program (course) is not a physical product,
 * so there is NO shipping address section here.
 */
function tfp_course_render_contact_panel($data)
{
    ?>
    <div class="tfp-checkout-panel tfp-contact-panel" data-step="contact" style="display:flex; flex-direction:column; gap:24px;">
        <div class="tfp-checkout-step-shell" style="display:block;">
            <div class="tfp-course-step-header"><?php esc_html_e('Step 1 of 2', 'tfp-dashboard'); ?></div>
            <div class="tfp-course-step-subheader"><?php esc_html_e('Contact Information', 'tfp-dashboard'); ?></div>

            <div class="tfp-checkout-section-block">
                <div class="tfp-checkout-form-row">
                    <div class="tfp-checkout-field">
                        <label for="tfp_checkout_first_name"><?php esc_html_e('First Name', 'tfp-dashboard'); ?></label>
                        <input id="tfp_checkout_first_name" type="text" name="first_name" value="<?php echo esc_attr($data['first_name']); ?>" />
                    </div>
                    <div class="tfp-checkout-field">
                        <label for="tfp_checkout_last_name"><?php esc_html_e('Last Name', 'tfp-dashboard'); ?></label>
                        <input id="tfp_checkout_last_name" type="text" name="last_name" value="<?php echo esc_attr($data['last_name']); ?>" />
                    </div>
                </div>
                <div class="tfp-checkout-form-row">
                    <div class="tfp-checkout-field tfp-checkout-field--full">
                        <label for="tfp_checkout_email"><?php esc_html_e('Email Address', 'tfp-dashboard'); ?></label>
                        <input id="tfp_checkout_email" type="email" name="email" value="<?php echo esc_attr($data['email']); ?>" />
                    </div>
                </div>
                <div class="tfp-checkout-form-row">
                    <div class="tfp-checkout-field tfp-checkout-field--full">
                        <label for="tfp_checkout_phone"><?php esc_html_e('Phone Number', 'tfp-dashboard'); ?></label>
                        <input id="tfp_checkout_phone" type="tel" name="phone" value="<?php echo esc_attr($data['phone']); ?>" />
                    </div>
                </div>
            </div>

            <div class="tfp-course-checkout-error" id="tfp-course-contact-error" role="alert"></div>

            <div class="tfp-checkout-actions tfp-checkout-actions--split">
                <button type="button" class="tfp-dash-btn tfp-dash--secondary" data-tfp-open-cohorts>
                    <svg xmlns="http://www.w3.org/2000/svg" width="5" height="8" viewBox="0 0 5 8" fill="none">
                    <path d="M4.93994 0.94L1.88661 4L4.93994 7.06L3.99994 8L-5.88258e-05 4L3.99994 -4.10887e-08L4.93994 0.94Z" fill="currentColor"></path>
                </svg>
                    <?php esc_html_e('Back to Cohort', 'tfp-dashboard'); ?>
                </button>
                <button type="button" class="tfp-dash-btn tfp-dash-btn--primary tfp-course-continue-payment">
                    <?php esc_html_e('Continue to Payment', 'tfp-dashboard'); ?>
                </button>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Payment panel. Mirrors render.php's method grid + payment panel + the single
 * #tfp-global-place-order button that checkout-stripe.js clones in place.
 * Methods: Credit/Debit + Apple Pay + Google Pay (shared Stripe layer) and
 * PayPal (shared checkout-paypal.js) — identical to the book checkout.
 */
function tfp_course_render_payment_panel()
{
    ?>
    <div class="tfp-checkout-panel tfp-checkout-step-shell" data-step="payment" style="display:none;">
        <div class="tfp-course-step-header"><?php esc_html_e('Step 2 of 2', 'tfp-dashboard'); ?></div>
        <div class="tfp-course-step-subheader"><?php esc_html_e('Payment Details', 'tfp-dashboard'); ?></div>
        <h6 class="tfp-course-pay-heading"><?php esc_html_e('Choose a Payment Method', 'tfp-dashboard'); ?></h6>

        <div class="tfp-checkout-method-grid">
            <button type="button" class="tfp-checkout-method-card is-selected" data-method="credit">
            <span class="tfp-checkout-method-cards">
                   <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 48 48" fill="none">
<path d="M24 0C37.2548 0 48 10.7452 48 24C48 37.2548 37.2548 48 24 48C10.7452 48 0 37.2548 0 24C0 10.7452 10.7452 0 24 0ZM28.4287 20.083C26.133 20.0831 24.5178 21.3133 24.5049 23.0742C24.4886 24.3775 25.6578 25.1047 26.5381 25.5371C27.4424 25.9808 27.7466 26.2654 27.7432 26.6621C27.7363 27.2693 27.0214 27.5374 26.3525 27.5479C25.1858 27.5661 24.5072 27.2306 23.9678 26.9766L23.5469 28.959C24.088 29.2104 25.0907 29.4299 26.1299 29.4395C28.5688 29.4394 30.1642 28.2256 30.1729 26.3438C30.1823 23.9556 26.8969 23.8233 26.9189 22.7559C26.9267 22.4322 27.2328 22.0869 27.9043 21.999C28.2367 21.9547 29.1545 21.9205 30.1943 22.4033L30.6025 20.4844C30.0433 20.279 29.3237 20.083 28.4287 20.083ZM9.64062 20.5107C10.4518 20.6882 11.3732 20.975 11.9316 21.2812C12.2734 21.4683 12.3712 21.6316 12.4834 22.0762L14.3359 29.2988H16.79L20.5537 20.248H18.1143L15.6963 26.4082L14.7188 21.1709C14.604 20.5862 14.1506 20.248 13.6475 20.248H9.69531L9.64062 20.5107ZM19.6289 29.2988H21.9512L23.8721 20.248H21.5498L19.6289 29.2988ZM34.5244 20.248C34.0792 20.2482 33.7035 20.5103 33.5361 20.9121L30.0527 29.2988H32.4893L32.9746 27.9473H35.9531L36.2344 29.2988H38.3828L36.5078 20.248H34.5244Z" fill="#00666E"></path>
<path d="M35.569 26.0898L33.6426 26.0898L34.8656 22.6914L35.569 26.0898Z" fill="#00666E"></path>
</svg>
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 48 48" fill="none">
<path d="M24 0C37.2548 0 48 10.7452 48 24C48 37.2548 37.2548 48 24 48C10.7452 48 0 37.2548 0 24C0 10.7452 10.7452 0 24 0ZM29.4824 15.2021C27.4118 15.2021 25.5059 15.9111 23.9941 17.0996C22.4824 15.9111 20.5765 15.2021 18.5059 15.2021C13.5926 15.2024 9.60961 19.1927 9.60938 24.1152C9.60938 29.038 13.5925 33.029 18.5059 33.0293C20.5765 33.0293 22.4824 32.3204 23.9941 31.1318C25.5059 32.3204 27.4118 33.0293 29.4824 33.0293C34.3959 33.0291 38.3789 29.038 38.3789 24.1152C38.3787 19.1926 34.3957 15.2024 29.4824 15.2021Z" fill="#00666E"></path>
<path d="M23.9943 17.0977C21.9185 18.7295 20.5859 21.2652 20.5859 24.1138C20.5859 26.9624 21.9185 29.5 23.9943 31.1319C26.0702 29.5 27.4027 26.9624 27.4027 24.1138C27.4027 21.2652 26.0702 18.7295 23.9943 17.0977V17.0977Z" fill="#00666E"></path>
</svg>
                </span>
                <span><?php esc_html_e('Credit/Debit', 'tfp-dashboard'); ?></span>
            </button>
            <button type="button" class="tfp-checkout-method-card" data-method="paypal">
                <span class="tfp-checkout-method-icon tfp-checkout-method-icon--paypal">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="24" viewBox="0 0 20 24" fill="none">
<path d="M4.55691 19.8273L4.428 20.6448L4.1088 22.6712C4.0552 23.0136 4.3192 23.3224 4.6648 23.3224H8.5696C9.032 23.3224 9.4248 22.9864 9.4976 22.5304L9.536 22.332L10.2712 17.6664L10.3184 17.4104C10.3904 16.9528 10.784 16.6168 11.2464 16.6168H11.8304C15.6136 16.6168 18.5752 15.0808 19.4408 10.636C19.8024 8.7792 19.6152 7.2288 18.6584 6.1384C18.3688 5.8096 18.0096 5.5368 17.5896 5.3144C17.5672 5.4576 17.5416 5.604 17.5128 5.7544C17.4856 5.89417 17.4565 6.03142 17.4257 6.16619C17.3514 6.1197 17.2732 6.07472 17.1912 6.0312L16.7704 5.7928V5.252L16.78 5.1904C16.9128 4.3464 16.9104 3.6544 16.7744 3.0752C16.6448 2.5232 16.3768 2.0216 15.956 1.5416C15.0584 0.5184 13.3392 0 10.848 0H3.344C3.2832 0 3.2248 0.0216 3.1792 0.0608C3.1336 0.1 3.1024 0.1552 3.0928 0.2144L0 19.8248H4.4504L4.55691 19.8273Z" fill="white"/>
<path d="M16.9992 5.3552C16.848 5.3112 16.692 5.2712 16.532 5.2352C16.3712 5.2 16.2064 5.1688 16.0368 5.1416C15.4432 5.0456 14.7928 5 14.096 5H8.2144C8.0696 5 7.932 5.0328 7.8088 5.092C7.5376 5.2224 7.336 5.4792 7.2872 5.7936L6.036 13.7184L6 13.9496C6.0824 13.428 6.528 13.044 7.0568 13.044H9.2584C13.5824 13.044 16.968 11.288 17.9576 6.208C17.9872 6.0576 18.012 5.9112 18.0344 5.768C17.784 5.6352 17.5128 5.5216 17.2208 5.4248C17.1488 5.4008 17.0744 5.3776 16.9992 5.3552V5.3552Z" fill="#00666E"/>
</svg>
                </span>
                <span><?php esc_html_e('PayPal', 'tfp-dashboard'); ?></span>
            </button>
            <button type="button" class="tfp-checkout-method-card" data-method="applepay">
                <span class="tfp-checkout-method-icon tfp-checkout-method-icon--apple">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="29" viewBox="0 0 24 29" fill="none">
<path d="M15.9074 4.56146C16.8238 3.37856 17.5184 1.70662 17.2672 0C15.7695 0.104083 14.0189 1.0623 12.9974 2.3113C12.0663 3.44299 11.301 5.12648 11.5999 6.76042C13.2372 6.81163 14.927 5.83028 15.9074 4.56146Z" fill="#ffffff"></path>
<path d="M24 20.6122C19.2343 18.7998 18.4707 12.0245 23.1871 9.4241C21.7485 7.62 19.727 6.57422 17.8187 6.57422C15.2979 6.57422 14.2321 7.78026 12.4815 7.78026C10.6767 7.78026 9.30546 6.57752 7.12624 6.57752C4.98479 6.57752 2.70539 7.88599 1.26024 10.1229C-0.771178 13.2735 -0.424671 19.1963 2.86961 24.2418C4.04708 26.0476 5.62032 28.0764 7.67802 28.0945C9.50909 28.1127 10.0264 26.9199 12.5078 26.9067C14.9892 26.8918 15.4605 28.1094 17.2883 28.0896C19.3476 28.0731 21.0079 25.8246 22.1854 24.0188C23.0295 22.7252 23.3448 22.0726 24 20.6122Z" fill="#ffffff"></path>
</svg>
                </span>
                <span><?php esc_html_e('Apple Pay', 'tfp-dashboard'); ?></span>
            </button>
            <button type="button" class="tfp-checkout-method-card" data-method="googlepay">
                <span class="tfp-checkout-method-icon tfp-checkout-method-icon--google">
                    <svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 21 21" fill="none">
<path d="M10.3847 4.02133C12.3348 4.02133 13.6502 4.86489 14.4002 5.56978L17.3311 2.704C15.531 1.02844 13.1886 0 10.3847 0C6.323 0 2.81518 2.33422 1.10742 5.73156L4.46524 8.34311C5.30757 5.83556 7.63843 4.02133 10.3847 4.02133Z" fill="white"></path>
<path d="M20.3544 10.6301C20.3544 9.77502 20.2851 9.15102 20.1351 8.50391H10.3848V12.3635H16.1081C15.9927 13.3226 15.3696 14.767 13.9849 15.7377L17.2619 18.2799C19.2235 16.4657 20.3544 13.7964 20.3544 10.6301Z" fill="white"></path>
<path d="M4.47709 12.4558C4.25785 11.8087 4.13092 11.1154 4.13092 10.3989C4.13092 9.68247 4.25785 8.98914 4.46555 8.34202L1.10773 5.73047C0.403861 7.14025 0 8.72336 0 10.3989C0 12.0745 0.403861 13.6576 1.10773 15.0674L4.47709 12.4558Z" fill="white"></path>
<path d="M10.3849 20.8001C13.1888 20.8001 15.5427 19.8757 17.262 18.281L13.985 15.7388C13.108 16.3513 11.9311 16.7788 10.3849 16.7788C7.63861 16.7788 5.30775 14.9646 4.47695 12.457L1.11914 15.0686C2.82689 18.4659 6.32318 20.8001 10.3849 20.8001Z" fill="white"></path>
</svg>
                </span>
                <span><?php esc_html_e('Google Pay', 'tfp-dashboard'); ?></span>
            </button>
        </div>

        <div class="tfp-checkout-payment-panel" id="tfp-checkout-payment-panel"></div>

        <div class="tfp-checkout-actions tfp-checkout-actions--split">
            <a href="#" class="tfp-checkout-link-button tfp-course-back-contact">
                <svg xmlns="http://www.w3.org/2000/svg" width="5" height="8" viewBox="0 0 5 8" fill="none" aria-hidden="true"><path d="M4.93994 0.94L1.88661 4L4.93994 7.06L3.99994 8L-5.88258e-05 4L3.99994 -4.10887e-08L4.93994 0.94Z" fill="currentColor"></path></svg>
                <?php esc_html_e('Back to Contact', 'tfp-dashboard'); ?>
            </a>
            <button type="button" id="tfp-global-place-order" class="tfp-dash-btn tfp-dash-btn--primary tfp-checkout-place-order">
                <?php esc_html_e('Place Order', 'tfp-dashboard'); ?>
            </button>
        </div>
    </div>
    <?php
}

/**
 * Order summary sidebar. Totals are filled in by course-checkout.js from the
 * tfp_checkout_get_summary_array() payload returned by reserve/shipping AJAX.
 */
function tfp_course_render_summary($state = null)
{
    $fa_approved = $state && !empty($state['fa_status']) && $state['fa_status'] === 'approved';
    $fa_discount = $state && !empty($state['fa_discount']) ? (int) $state['fa_discount'] : 0;
    $product_img = '';
    $product_name = __('The Follow Program', 'tfp-dashboard');
    if (function_exists('tfp_ld_get_program_course_id') && function_exists('wc_get_product')) {
        $summary_course_id = (int) tfp_ld_get_program_course_id();
        $summary_product_id = ($summary_course_id && function_exists('tfp_billing_get_product_id_for_course'))
            ? (int) tfp_billing_get_product_id_for_course($summary_course_id)
            : 0;
        $summary_product = $summary_product_id ? wc_get_product($summary_product_id) : null;
        if ($summary_product) {
            $product_name = $summary_product->get_name();
            $product_img = $summary_product->get_image('woocommerce_thumbnail');
        }
    }
    ?>
    <aside class="tfp-checkout-order-summary tfp-course-summary tfp-course-review__summary">
        <h5><?php esc_html_e('Order Summary', 'tfp-dashboard'); ?></h5>

        <div class="tfp-checkout-cart-item" id="tfp-course-summary-items">
            <div class="tfp-checkout-cart-image"><?php echo $product_img; // WC-escaped <img> ?></div>
            <div class="tfp-review-product__copy">
                <div class="tfp-checkout-cart-name"><?php echo esc_html($product_name); ?></div>
                <div class="tfp-checkout-cart-meta"><?php esc_html_e('Online Course · Qty: 1', 'tfp-dashboard'); ?></div>
            </div>
            <div class="tfp-review-product__price" id="tfp-course-summary-price">&mdash;</div>
        </div>

        <?php
        // The sidebar keeps only the applied-FA total line (#tfp-course-fa-line).
        ?>

        <div class="tfp-checkout-cart-totals">
            <div class="tfp-checkout-totals-row">
                <span><?php esc_html_e('Subtotal', 'tfp-dashboard'); ?></span>
                <strong id="tfp-course-sum-subtotal">&mdash;</strong>
            </div>
            <div class="tfp-checkout-totals-row tfp-course-fa-row" id="tfp-course-fa-line" <?php echo $fa_approved ? '' : 'style="display:none;"'; ?>>
                <span><?php esc_html_e('Financial Aid', 'tfp-dashboard'); ?><?php echo $fa_discount ? ' (' . esc_html($fa_discount) . '% ' . esc_html__('off', 'tfp-dashboard') . ')' : ''; ?></span>
                <strong id="tfp-course-sum-discount">&mdash;</strong>
            </div>
            <div class="tfp-checkout-totals-row">
                <span><?php esc_html_e('Tax', 'tfp-dashboard'); ?></span>
                <strong id="tfp-course-sum-tax">&mdash;</strong>
            </div>
            <div class="tfp-checkout-totals-row tfp-checkout-totals-final">
                <span><?php esc_html_e('Estimated Total', 'tfp-dashboard'); ?></span>
                <strong id="tfp-course-sum-total">&mdash;</strong>
            </div>
        </div>
    </aside>
    <?php
}
