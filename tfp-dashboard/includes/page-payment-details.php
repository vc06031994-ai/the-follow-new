<?php
if (!defined('ABSPATH')) exit;

function tfp_dashboard_render_payment_details_content()
{
    $user_id  = get_current_user_id();
    $name     = tfp_dashboard_user_name();
    $has_paid = tfp_billing_user_has_paid($user_id);

    $card = function_exists('tfp_billing_get_default_card_summary') ? tfp_billing_get_default_card_summary($user_id) : null;

    $add_payment_method_url = function_exists('wc_get_account_endpoint_url')
        ? wc_get_account_endpoint_url('add-payment-method')
        : '#';

    tfp_dashboard_render_page_header(
        __('Payment Details', 'tfp-dashboard'),
        sprintf(esc_html__('%s, update your billing information', 'tfp-dashboard'), esc_html(tfp_dashboard_user_first_name() ?: $name)),
        __('Save or update your payment method before you check out, and keep your account ready for future billing.', 'tfp-dashboard')
    );
    ?>
    <div>
        <div class="tfp-dash-billing-detail__intro">
            <h2 class="tfp-dash-section__hi"><?php printf(esc_html__('Hi, %s — Update payment method', 'tfp-dashboard'), esc_html($name)); ?></h2>
            <p class="tfp-dash-section__lead"><?php esc_html_e('Your card is added on a secure page provided by our payment processor — we never see or store your full card number.', 'tfp-dashboard'); ?></p>
        </div>

        <div class="tfp-dash-billing-detail__grid tfp-dash-billing-detail">
            <div class="tfp-dash-billing-detail__card-preview">
                <img src="<?php echo esc_url(TFP_DASH_URL . 'assets/images/credit.png'); ?>"
                     alt="Credit card"
                     class="tfp-dash-billing-card-visual tfp-dash-billing-card-visual--image">
            </div>

            <div class="tfp-dash-billing-detail__form">
                <?php if ($card) : ?>
                    <div class="tfp-profile-row">
                        <span><?php esc_html_e('Card on File', 'tfp-dashboard'); ?></span>
                        <strong><?php printf('%s ending in %s', esc_html($card['brand']), esc_html($card['last4'])); ?></strong>
                    </div>
                    <div class="tfp-profile-row">
                        <span><?php esc_html_e('Expiration', 'tfp-dashboard'); ?></span>
                        <strong><?php echo esc_html($card['expiry']); ?></strong>
                    </div>
                <?php else : ?>
                    <p class="tfp-dash-section__lead"><?php esc_html_e('No payment method saved yet.', 'tfp-dashboard'); ?></p>
                <?php endif; ?>

                <a href="<?php echo esc_url($add_payment_method_url); ?>" class="tfp-dash-btn tfp-dash-btn--primary" style="margin-top: 16px;">
                    <?php echo $card ? esc_html__('Update Payment Method', 'tfp-dashboard') : esc_html__('Add Payment Method', 'tfp-dashboard'); ?>
                </a>
            </div>
        </div>

        <div class="tfp-dash-billing-detail__next-step">
            <a href="<?php echo esc_url(tfp_dashboard_get_url('tfp-dashboard-home')); ?>" class="tfp-dash-btn tfp-dash-btn--outline">
                <?php esc_html_e('Back to Home', 'tfp-dashboard'); ?>
            </a>
            <?php if (!$has_paid) : ?>
                <a href="<?php echo esc_url(tfp_billing_pay_now_url($user_id)); ?>" class="tfp-dash-btn tfp-dash-btn--primary">
                    <?php esc_html_e('Pay Now', 'tfp-dashboard'); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * WooCommerce's native Add Payment Method / Payment Methods pages don't
 * know about our dashboard shell. Add a small link back so the round-trip
 * (Payment Details -> WooCommerce's secure form -> back) feels seamless.
 */
add_action('woocommerce_before_account_payment_methods', 'tfp_dashboard_render_back_to_dashboard_link');
add_action('woocommerce_before_edit_account_form', 'tfp_dashboard_render_back_to_dashboard_link');

function tfp_dashboard_render_back_to_dashboard_link()
{
    if (!function_exists('tfp_dashboard_get_url')) {
        return;
    }
    $url = tfp_dashboard_get_url('tfp-dashboard-payment-details');
    if (!$url || $url === '#') {
        return;
    }
    printf(
        '<p><a href="%s">&larr; %s</a></p>',
        esc_url($url),
        esc_html__('Back to Payment Details', 'tfp-dashboard')
    );
}
