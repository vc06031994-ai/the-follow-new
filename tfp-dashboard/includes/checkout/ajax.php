<?php
if (!defined('ABSPATH')) {
    exit;
}

function tfp_checkout_verify_request()
{
    if (!isset($_POST['tfp_checkout_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tfp_checkout_nonce'])), 'tfp_checkout_nonce')) {
        wp_send_json([
            'success' => false,
            'message' => __('Security check failed.', 'tfp-dashboard'),
        ], 403);
    }
}

function tfp_checkout_normalize_step($step)
{
    $allowed = array('cart', 'contact', 'delivery', 'payment');
    if (!in_array($step, $allowed, true)) {
        return 'cart';
    }

    return $step;
}

/**
 * Recalculate cart shipping + totals for the custom TFP checkout.
 *
 * WooCommerce zeroes out the shipping total whenever WC_Cart::show_shipping()
 * (which depends on WC_Cart::needs_shipping()) evaluates to false - e.g. if the
 * product is marked "Virtual" in WooCommerce, or the shipping-enabled setting
 * is off. Since this custom checkout always presents shipping methods to the
 * customer, we force WooCommerce to treat the cart as needing shipping while
 * totals are recalculated, so the chosen method's cost is never dropped.
 */
function tfp_checkout_recalculate_totals()
{
    add_filter('woocommerce_cart_needs_shipping', '__return_true');
    WC()->cart->calculate_shipping();
    WC()->cart->calculate_totals();
    remove_filter('woocommerce_cart_needs_shipping', '__return_true');
}

add_action('wp_ajax_tfp_checkout_update_quantity', 'tfp_checkout_ajax_update_quantity');
add_action('wp_ajax_nopriv_tfp_checkout_update_quantity', 'tfp_checkout_ajax_update_quantity');

function tfp_checkout_ajax_update_quantity()
{
    tfp_checkout_verify_request();

    if (!function_exists('WC') || !WC()->cart) {
        wp_send_json(['success' => false, 'message' => __('Cart not available.', 'tfp-dashboard')], 400);
    }

    $cart_key = isset($_POST['cart_key']) ? sanitize_text_field(wp_unslash($_POST['cart_key'])) : '';
    $delta = isset($_POST['delta']) ? intval($_POST['delta']) : 0;

    if (empty($cart_key)) {
        wp_send_json(['success' => false, 'message' => __('Cart item not found.', 'tfp-dashboard')], 400);
    }

    $cart = WC()->cart->get_cart();
    if (!isset($cart[$cart_key])) {
        wp_send_json(['success' => false, 'message' => __('Cart item not found.', 'tfp-dashboard')], 400);
    }

    $current_qty = absint($cart[$cart_key]['quantity']);
    $new_qty = $current_qty + $delta;

    if ($new_qty < 0) {
        $new_qty = 0;
    }

    if ($new_qty === 0) {
        WC()->cart->remove_cart_item($cart_key);
    } else {
        WC()->cart->set_quantity($cart_key, $new_qty, true);
    }

    tfp_checkout_recalculate_totals();

    ob_start();
    tfp_checkout_render_cart_step();
    $cart_html = ob_get_clean();

    ob_start();
    tfp_checkout_render_order_summary();
    $summary_html = ob_get_clean();

    wp_send_json([
        'success' => true,
        'summary' => tfp_checkout_get_summary_array(),
        'cart_html' => $cart_html,
        'summary_html' => $summary_html,
    ]);
}

add_action('wp_ajax_tfp_checkout_apply_coupon', 'tfp_checkout_ajax_apply_coupon');
add_action('wp_ajax_nopriv_tfp_checkout_apply_coupon', 'tfp_checkout_ajax_apply_coupon');

function tfp_checkout_ajax_apply_coupon()
{
    tfp_checkout_verify_request();

    if (!function_exists('WC') || !WC()->cart) {
        wp_send_json(['success' => false, 'message' => __('Cart not available.', 'tfp-dashboard')], 400);
    }

    $coupon = isset($_POST['coupon_code']) ? sanitize_text_field(wp_unslash($_POST['coupon_code'])) : '';
    if (empty($coupon)) {
        wp_send_json(['success' => false, 'message' => __('Please enter a coupon code.', 'tfp-dashboard')], 400);
    }

    $applied = WC()->cart->apply_coupon($coupon);
    if (is_wp_error($applied)) {
        wp_send_json(['success' => false, 'message' => $applied->get_error_message()], 400);
    }

    tfp_checkout_recalculate_totals();

    ob_start();
    tfp_checkout_render_cart_step();
    $cart_html = ob_get_clean();

    ob_start();
    tfp_checkout_render_order_summary();
    $summary_html = ob_get_clean();

    wp_send_json([
        'success' => true,
        'summary' => tfp_checkout_get_summary_array(),
        'coupon'  => $coupon,
        'cart_html' => $cart_html,
        'summary_html' => $summary_html,
    ]);
}

add_action('wp_ajax_tfp_checkout_save_contact_shipping', 'tfp_checkout_ajax_save_contact_shipping');
add_action('wp_ajax_nopriv_tfp_checkout_save_contact_shipping', 'tfp_checkout_ajax_save_contact_shipping');

function tfp_checkout_ajax_save_contact_shipping()
{
    tfp_checkout_verify_request();

    if (!function_exists('WC') || !WC()->customer) {
        wp_send_json(['success' => false, 'message' => __('Checkout customer not available.', 'tfp-dashboard')], 400);
    }

    $first_name = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '';
    $last_name = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '';
    $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
    $phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
    $street = isset($_POST['street']) ? sanitize_text_field(wp_unslash($_POST['street'])) : '';
    $apt = isset($_POST['apt']) ? sanitize_text_field(wp_unslash($_POST['apt'])) : '';
    $city = isset($_POST['city']) ? sanitize_text_field(wp_unslash($_POST['city'])) : '';
    $state = isset($_POST['state']) ? sanitize_text_field(wp_unslash($_POST['state'])) : '';
    $postcode = isset($_POST['postcode']) ? sanitize_text_field(wp_unslash($_POST['postcode'])) : '';

    if (empty($first_name) || empty($last_name) || empty($email) || empty($street) || empty($city) || empty($state) || empty($postcode)) {
        wp_send_json(['success' => false, 'message' => __('Please complete all required contact and shipping fields.', 'tfp-dashboard')], 400);
    }

    if (!is_email($email)) {
        wp_send_json(['success' => false, 'message' => __('Please enter a valid email address.', 'tfp-dashboard')], 400);
    }

    $customer = WC()->customer;
    $customer->set_billing_first_name($first_name);
    $customer->set_billing_last_name($last_name);
    $customer->set_billing_email($email);
    $customer->set_billing_phone($phone);
    $customer->set_billing_address_1($street);
    $customer->set_billing_address_2($apt);
    $customer->set_billing_city($city);
    $customer->set_billing_state($state);
    $customer->set_billing_postcode($postcode);
    $customer->set_billing_country('US');

    $customer->set_shipping_first_name($first_name);
    $customer->set_shipping_last_name($last_name);
    $customer->set_shipping_address_1($street);
    $customer->set_shipping_address_2($apt);
    $customer->set_shipping_city($city);
    $customer->set_shipping_state($state);
    $customer->set_shipping_postcode($postcode);
    $customer->set_shipping_country('US');
    $customer->save();

    if (is_user_logged_in()) {
        $user_id = get_current_user_id();
        update_user_meta($user_id, 'billing_phone', $phone);
        update_user_meta($user_id, 'shipping_first_name', $first_name);
        update_user_meta($user_id, 'shipping_last_name', $last_name);
        update_user_meta($user_id, 'shipping_address_1', $street);
        update_user_meta($user_id, 'shipping_address_2', $apt);
        update_user_meta($user_id, 'shipping_city', $city);
        update_user_meta($user_id, 'shipping_state', $state);
        update_user_meta($user_id, 'shipping_postcode', $postcode);
    }

    wp_send_json([
        'success' => true,
        'message' => __('Contact and shipping details saved.', 'tfp-dashboard'),
    ]);
}

add_action('wp_ajax_tfp_checkout_get_shipping_methods', 'tfp_checkout_ajax_get_shipping_methods');
add_action('wp_ajax_nopriv_tfp_checkout_get_shipping_methods', 'tfp_checkout_ajax_get_shipping_methods');

function tfp_checkout_ajax_get_shipping_methods()
{
    tfp_checkout_verify_request();

    if (!function_exists('WC') || !WC()->cart || WC()->cart->is_empty()) {
        wp_send_json(['success' => false, 'message' => __('Your cart is empty.', 'tfp-dashboard')], 400);
    }

    $customer = WC()->customer;
    if (empty($customer->get_shipping_city()) || empty($customer->get_shipping_state()) || empty($customer->get_shipping_postcode())) {
        wp_send_json(['success' => false, 'message' => __('Shipping address is required before delivery options are available.', 'tfp-dashboard')], 400);
    }

    $packages = WC()->cart->get_shipping_packages();
    $packages = WC()->shipping()->calculate_shipping($packages);

    $methods = array();
    foreach ($packages as $package) {
        if (!empty($package['rates'])) {
            foreach ($package['rates'] as $rate) {
                $estimate_text = __('Delivery available', 'tfp-dashboard');
                if (method_exists($rate, 'get_method_id') && method_exists($rate, 'get_instance_id')) {
                    $settings = get_option('woocommerce_' . $rate->get_method_id() . '_' . $rate->get_instance_id() . '_settings');
                    if (is_array($settings) && !empty($settings['delivery_description'])) {
                        $estimate_text = $settings['delivery_description'];
                    }
                }

                $methods[] = array(
                    'id' => $rate->id,
                    'label' => $rate->label,
                    'price' => wc_price($rate->cost),
                    'cost' => (float) $rate->cost,
                    'delivery_estimate' => apply_filters('tfp_checkout_shipping_estimate', $estimate_text, $rate),
                );
            }
        }
    }

    if (empty($methods)) {
        wp_send_json(['success' => false, 'message' => __('No shipping methods were returned for this address.', 'tfp-dashboard')], 400);
    }

    wp_send_json([
        'success' => true,
        'methods' => $methods,
    ]);
}

add_action('wp_ajax_tfp_checkout_select_shipping_method', 'tfp_checkout_ajax_select_shipping_method');
add_action('wp_ajax_nopriv_tfp_checkout_select_shipping_method', 'tfp_checkout_ajax_select_shipping_method');

function tfp_checkout_ajax_select_shipping_method()
{
    tfp_checkout_verify_request();

    if (!function_exists('WC') || !WC()->cart) {
        wp_send_json(['success' => false, 'message' => __('Cart not available.', 'tfp-dashboard')], 400);
    }

    $chosen_method = isset($_POST['shipping_method']) ? sanitize_text_field(wp_unslash($_POST['shipping_method'])) : '';
    if (empty($chosen_method)) {
        wp_send_json(['success' => false, 'message' => __('Please select a shipping method.', 'tfp-dashboard')], 400);
    }

    // Ensure the WooCommerce session is available before we persist the choice.
    if (!WC()->session) {
        wp_send_json(['success' => false, 'message' => __('Session not available.', 'tfp-dashboard')], 400);
    }

    // Calculate the live rates for every shipping package so we can (a) confirm
    // the chosen rate is actually offered and (b) prime the WooCommerce session
    // guards below with the current rate list.
    $packages   = WC()->cart->get_shipping_packages();
    $calculated = WC()->shipping()->calculate_shipping($packages);

    $chosen_shipping_methods   = (array) WC()->session->get('chosen_shipping_methods');
    $previous_shipping_methods = (array) WC()->session->get('previous_shipping_methods');
    $shipping_method_counts    = (array) WC()->session->get('shipping_method_counts');

    $method_offered = false;

    foreach ($calculated as $package_key => $package) {
        $rate_ids = !empty($package['rates']) ? array_keys($package['rates']) : array();

        if (in_array($chosen_method, $rate_ids, true)) {
            // This package offers the chosen method - select it.
            $chosen_shipping_methods[$package_key] = $chosen_method;
            $method_offered = true;
        } elseif (empty($chosen_shipping_methods[$package_key]) || !in_array($chosen_shipping_methods[$package_key], $rate_ids, true)) {
            // Package can't fulfil the chosen method - keep its first rate.
            $chosen_shipping_methods[$package_key] = isset($rate_ids[0]) ? $rate_ids[0] : '';
        }

        // WooCommerce resets the chosen method back to the package default
        // (the first/cheapest rate) whenever the available rates appear to have
        // "changed" or the cached rate count no longer matches - see
        // wc_get_chosen_shipping_method_for_package(). This custom checkout only
        // ever calculates shipping inside a forced recalculation, so those two
        // session guards are otherwise never primed and the customer's choice is
        // silently discarded on every recalculation. Priming them with the
        // current rate list keeps the selected method in place.
        $previous_shipping_methods[$package_key] = $rate_ids;
        $shipping_method_counts[$package_key]    = count($rate_ids);
    }

    if (!$method_offered) {
        wp_send_json(['success' => false, 'message' => __('The selected shipping method is no longer available.', 'tfp-dashboard')], 400);
    }

    WC()->session->set('chosen_shipping_methods', $chosen_shipping_methods);
    WC()->session->set('previous_shipping_methods', $previous_shipping_methods);
    WC()->session->set('shipping_method_counts', $shipping_method_counts);

    // Recalculate with the needs-shipping gate forced true so the chosen
    // method's cost is applied to both the shipping line and the grand total.
    tfp_checkout_recalculate_totals();

    ob_start();
    tfp_checkout_render_order_summary();
    $summary_html = ob_get_clean();

    wp_send_json([
        'success' => true,
        'summary' => tfp_checkout_get_summary_array(),
        'summary_html' => $summary_html,
    ]);
}

add_action('wp_ajax_tfp_checkout_get_payment_fields', 'tfp_checkout_ajax_get_payment_fields');
add_action('wp_ajax_nopriv_tfp_checkout_get_payment_fields', 'tfp_checkout_ajax_get_payment_fields');

function tfp_checkout_ajax_get_payment_fields()
{
    tfp_checkout_verify_request();

    $method = isset($_POST['payment_method']) ? sanitize_key(wp_unslash($_POST['payment_method'])) : '';
    if (empty($method)) {
        wp_send_json(['success' => false, 'message' => __('Payment method is required.', 'tfp-dashboard')], 400);
    }

    if (!function_exists('WC') || !WC()->payment_gateways) {
        wp_send_json(['success' => false, 'message' => __('Payment options are unavailable.', 'tfp-dashboard')], 400);
    }

    $gateway_id = $method;
    $available = WC()->payment_gateways()->get_available_payment_gateways();
    $gateway = isset($available[$gateway_id]) ? $available[$gateway_id] : null;

    if (!$gateway || !method_exists($gateway, 'payment_fields')) {
        wp_send_json(['success' => false, 'message' => __('This payment option is not available.', 'tfp-dashboard')], 400);
    }

    ob_start();
    $gateway->payment_fields();
    $html = ob_get_clean();

    wp_send_json([
        'success' => true,
        'html'    => $html,
    ]);
}

function tfp_checkout_get_summary_array()
{
    if (!function_exists('WC') || !WC()->cart) {
        return array();
    }

    $cart = WC()->cart;
    $items = array();

    foreach ($cart->get_cart() as $cart_key => $item) {
        $product = $item['data'];
        $items[] = array(
            'cart_key' => $cart_key,
            'name' => $product ? $product->get_name() : '',
            'quantity' => $item['quantity'],
            'subtotal' => wc_price($item['line_subtotal']),
        );
    }

    return array(
        'subtotal' => wc_price($cart->get_subtotal()),
        'shipping' => wc_price($cart->get_shipping_total()),
        'tax' => wc_price($cart->get_tax_totals() ? array_sum(wp_list_pluck($cart->get_tax_totals(), 'amount')) : 0),
        'total' => wc_price($cart->get_total('edit')),
        'items' => $items,
    );
}

add_action('wp_ajax_tfp_checkout_paypal_capture_order', 'tfp_checkout_ajax_paypal_capture_order');
add_action('wp_ajax_nopriv_tfp_checkout_paypal_capture_order', 'tfp_checkout_ajax_paypal_capture_order');

function tfp_checkout_ajax_paypal_capture_order()
{
    tfp_checkout_verify_request();

    if (!function_exists('WC') || !WC()->cart) {
        wp_send_json(['success' => false, 'message' => __('Cart not available.', 'tfp-dashboard')], 400);
    }

    $is_course_order = function_exists('tfp_course_cart_cohort_id') && (bool) tfp_course_cart_cohort_id();

    $paypal_order_id = isset($_POST['order_id']) ? sanitize_text_field(wp_unslash($_POST['order_id'])) : '';
    if (empty($paypal_order_id)) {
        wp_send_json(['success' => false, 'message' => __('Missing PayPal order ID.', 'tfp-dashboard')], 400);
    }

    // In a real integration, we'd verify the capture status with the PayPal API here.
    // For this custom integration, we proceed to create the WooCommerce order.

    $checkout = WC()->checkout();
    
    // Attempt to create the order using the session data
    try {
        $order_id = $checkout->create_order(array());
        
        if (is_wp_error($order_id)) {
            throw new Exception($order_id->get_error_message());
        }

        $order = wc_get_order($order_id);
        
        // Mark as paid
        $order->payment_complete($paypal_order_id);
        $order->add_order_note(sprintf(__('PayPal payment captured. Transaction ID: %s', 'tfp-dashboard'), $paypal_order_id));
        
        // Empty cart
        WC()->cart->empty_cart();
        
        wp_send_json([
            'success' => true,
            'popup' => $is_course_order,
            'order_id' => $order->get_id(),
            'order_key' => $order->get_order_key(),
            'data' => [
                'popup' => $is_course_order,
                'order_id' => $order->get_id(),
                'order_key' => $order->get_order_key(),
                'redirect' => $order->get_checkout_order_received_url()
            ]
        ]);
        
    } catch (Exception $e) {
        wp_send_json(['success' => false, 'message' => $e->getMessage()], 400);
    }
}
