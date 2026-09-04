<?php
if (!defined('ABSPATH')) {
    exit;
}

function tfp_course_render_order_confirmation_modal()
{
    $profile_url = function_exists('tfp_dashboard_get_url')
        ? tfp_dashboard_get_url('tfp-dashboard-profile')
        : '#';
    ?>
    <div class="tfp-order-confirmation-modal" id="tfp-order-confirmation-modal" hidden aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="tfp-order-confirmation-title">
        <div class="tfp-order-confirmation-modal__backdrop" data-tfp-order-confirmation-close></div>
        <div class="tfp-order-confirmation-modal__box">
            <button type="button" class="tfp-modal-close" data-tfp-order-confirmation-close aria-label="<?php esc_attr_e('Close', 'tfp-dashboard'); ?>">&times;</button>
            <div class="tfp-order-confirmation-modal__hero">
                <div class="tfp-order-confirmation-modal__check" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="19" height="14" viewBox="0 0 19 14" fill="none">
<path d="M1 6.6569L6.65685 12.3138L17.9694 1" stroke="#151411" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
</svg>
                </div>
                <div class="tfp-order-confirmation-modal__eyebrow"><?php esc_html_e('Order Confirmed', 'tfp-dashboard'); ?></div>
                <h1 id="tfp-order-confirmation-title"><?php esc_html_e('Order confirmed — one more step', 'tfp-dashboard'); ?></h1>
                <p><?php esc_html_e(" We’ve emailed your order confirmation. Your Dashboard is ready but will remain limited until you finish your profile and set up communication. Please complete your profile (location, phone, pronouns, short bio) and connect to our communication channel so you’ll receive weekly meeting notices and course updates.", 'tfp-dashboard'); ?></p>
                <div class="tfp-order-confirmation-modal__number" id="tfp-order-confirmation-number"></div>
            </div>
            <div class="tfp-order-confirmation-modal__details" id="tfp-order-confirmation-details">
                <p class="tfp-order-confirmation-modal__loading"><?php esc_html_e('Loading order details...', 'tfp-dashboard'); ?></p>
            </div>
            <a class="tfp-order-confirmation-modal__profile-button tfp-dash-btn tfp-dash-btn--primary" href="<?php echo esc_url($profile_url); ?>">
                <?php esc_html_e('Complete profile & connect', 'tfp-dashboard'); ?>
            </a>
        </div>
    </div>
    <?php
}

add_action('wp_ajax_tfp_render_order_confirmation', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => __('You must be logged in.', 'tfp-dashboard')], 403);
    }

    check_ajax_referer('tfp_checkout_nonce', 'nonce');

    $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
    $order_key = isset($_POST['order_key']) ? sanitize_text_field(wp_unslash($_POST['order_key'])) : '';
    $order = $order_id ? wc_get_order($order_id) : false;

    if (!$order || $order->get_order_key() !== $order_key || (int) $order->get_customer_id() !== get_current_user_id()) {
        wp_send_json_error(['message' => __('Invalid order or permission denied.', 'tfp-dashboard')], 403);
    }

    $_GET['order_id'] = $order_id;
    $_GET['order_key'] = $order_key;

    wp_send_json_success([
        'number' => do_shortcode('[tfp_order_number order_id="' . $order_id . '" order_key="' . esc_attr($order_key) . '"]'),
        'html' => do_shortcode('[tfp_order_details order_id="' . $order_id . '" order_key="' . esc_attr($order_key) . '"]'),
    ]);
});
