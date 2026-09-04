<?php
/**
 * Course (program) checkout — server side.
 *
 * The Home-page modal wizard buys a PROGRAM (a LearnDash course sold as a
 * WooCommerce product) into a specific COHORT. It reuses the existing custom
 * checkout plumbing wholesale:
 *   - nonce / guard:      tfp_checkout_verify_request()           (checkout/ajax.php)
 *   - contact + address:  tfp_checkout_save_contact_shipping      (checkout/ajax.php)
 *   - shipping methods:   tfp_checkout_get/select_shipping_method (checkout/ajax.php)
 *   - FA / coupon:        tfp_checkout_apply_coupon               (checkout/ajax.php)
 *   - payment + order:    tfp_stripe_create_intent / complete     (checkout/stripe.php)
 *
 * This file only adds what is cohort-specific: listing cohorts, reserving a
 * seat (which loads the program product into the cart with the cohort tagged
 * on the line item), a per-cohort price override, and writing the enrollment
 * once payment succeeds.
 *
 * Seat holds are SOFT in v1: availability is checked when a seat is reserved,
 * not locked for the duration of checkout. We deliberately do NOT block inside
 * order creation — by the time tfp_stripe_complete_order() runs the card has
 * already been charged, so throwing there would charge a customer with no
 * order. A hard timed lock can be layered on later.
 */

if (!defined('ABSPATH')) exit;

/* -------------------------------------------------------------------------
 * Helpers
 * ---------------------------------------------------------------------- */

/**
 * The cohort id currently in the cart (from the program line item), falling
 * back to the session selection.
 */
function tfp_course_cart_cohort_id()
{
    if (!function_exists('WC') || !WC()->cart) {
        return 0;
    }

    foreach (WC()->cart->get_cart() as $item) {
        if (!empty($item['tfp_cohort_id'])) {
            return (int) $item['tfp_cohort_id'];
        }
    }

    if (WC()->session) {
        $sess = (int) WC()->session->get('tfp_selected_cohort');
        if ($sess) {
            return $sess;
        }
    }

    return 0;
}

/**
 * "Seats: 5 of 20" / "Seats available" (unlimited) / "Seats: 12 of 12 — Full".
 */
function tfp_course_seats_label($cohort)
{
    if (empty($cohort['seats_total'])) {
        return __('Seats available', 'tfp-dashboard');
    }
    if (!empty($cohort['is_full'])) {
        return sprintf(
            /* translators: 1: seats total */
            __('Seats: %1$d of %1$d — Full', 'tfp-dashboard'),
            (int) $cohort['seats_total']
        );
    }
    return sprintf(
        /* translators: 1: seats taken, 2: seats total */
        __('Seats: %1$d of %2$d', 'tfp-dashboard'),
        (int) $cohort['seats_taken'],
        (int) $cohort['seats_total']
    );
}

/* -------------------------------------------------------------------------
 * AJAX: list cohorts for a course
 * ---------------------------------------------------------------------- */

add_action('wp_ajax_tfp_course_get_cohorts', 'tfp_course_ajax_get_cohorts');

function tfp_course_ajax_get_cohorts()
{
    tfp_checkout_verify_request();

    $course_id = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;
    if (!$course_id && function_exists('tfp_ld_get_program_course_id')) {
        $course_id = (int) tfp_ld_get_program_course_id();
    }

    $rows = [];
    foreach (tfp_cohorts_for_course($course_id) as $post) {
        $data = tfp_cohort_get($post->ID);
        if (!$data) {
            continue;
        }
        $rows[] = [
            'id'          => $data['id'],
            'name'        => $data['name'],
            'start_label' => $data['start_label'],
            'schedule'    => $data['schedule'],
            'facilitator' => $data['facilitator'],
            'seats_total' => $data['seats_total'],
            'seats_taken' => $data['seats_taken'],
            'seats_label' => tfp_course_seats_label($data),
            'price_html'  => $data['price_html'],
            'is_full'     => (bool) $data['is_full'],
        ];
    }

    wp_send_json([
        'success'   => true,
        'course_id' => $course_id,
        'cohorts'   => $rows,
    ]);
}

/* -------------------------------------------------------------------------
 * AJAX: reserve a seat (load the program product into the cart)
 * ---------------------------------------------------------------------- */

add_action('wp_ajax_tfp_course_reserve_seat', 'tfp_course_ajax_reserve_seat');

function tfp_course_ajax_reserve_seat()
{
    tfp_checkout_verify_request();

    if (!is_user_logged_in()) {
        wp_send_json(['success' => false, 'message' => __('You must be logged in.', 'tfp-dashboard')], 403);
    }

    if (!function_exists('WC') || !WC()->cart) {
        wp_send_json(['success' => false, 'message' => __('Cart not available.', 'tfp-dashboard')], 400);
    }

    $cohort_id = isset($_POST['cohort_id']) ? absint($_POST['cohort_id']) : 0;
    $ack       = !empty($_POST['acknowledged']);

    $cohort = $cohort_id ? tfp_cohort_get($cohort_id) : null;
    if (!$cohort) {
        wp_send_json(['success' => false, 'message' => __('That cohort could not be found.', 'tfp-dashboard')], 400);
    }

    if (!$ack) {
        wp_send_json(['success' => false, 'message' => __('Please confirm you understand the cohort commitment before reserving a seat.', 'tfp-dashboard')], 400);
    }

    // Soft availability check at reserve time.
    if (!empty($cohort['is_full'])) {
        wp_send_json(['success' => false, 'message' => __('Sorry, this cohort just filled up. Please choose another cohort.', 'tfp-dashboard')], 409);
    }

    $product_id = (int) $cohort['product_id'];
    if (!$product_id) {
        wp_send_json(['success' => false, 'message' => __('This cohort has no program product configured. Please contact support.', 'tfp-dashboard')], 400);
    }

    // One program per cart: clear anything already there, then add this cohort.
    WC()->cart->empty_cart();
    $added = WC()->cart->add_to_cart($product_id, 1, 0, [], ['tfp_cohort_id' => $cohort_id]);

    if (!$added) {
        wp_send_json(['success' => false, 'message' => __('We could not reserve that seat. Please try again.', 'tfp-dashboard')], 400);
    }

    if (WC()->session) {
        WC()->session->set('tfp_selected_cohort', $cohort_id);
    }

    if (function_exists('tfp_checkout_recalculate_totals')) {
        tfp_checkout_recalculate_totals();
    }

    wp_send_json([
        'success' => true,
        'cohort'  => [
            'id'          => $cohort['id'],
            'name'        => $cohort['name'],
            'start_label' => $cohort['start_label'],
            'schedule'    => $cohort['schedule'],
            'facilitator' => $cohort['facilitator'],
            'price_html'  => $cohort['price_html'],
        ],
        'summary' => function_exists('tfp_checkout_get_summary_array') ? tfp_checkout_get_summary_array() : null,
    ]);
}

/* -------------------------------------------------------------------------
 * AJAX: save contact details (no shipping — a program is virtual)
 *
 * The book checkout's tfp_checkout_save_contact_shipping REQUIRES a full
 * shipping address, which a course does not collect. This course-specific
 * handler saves only the contact fields onto the WooCommerce customer so the
 * order (created later by the Stripe or PayPal path) has a billing name/email.
 * ---------------------------------------------------------------------- */

add_action('wp_ajax_tfp_course_save_contact', 'tfp_course_ajax_save_contact');

function tfp_course_ajax_save_contact()
{
    tfp_checkout_verify_request();

    if (!function_exists('WC') || !WC()->customer) {
        wp_send_json(['success' => false, 'message' => __('Checkout customer not available.', 'tfp-dashboard')], 400);
    }

    $first_name = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '';
    $last_name  = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '';
    $email      = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
    $phone      = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';

    if (empty($first_name) || empty($last_name) || empty($email)) {
        wp_send_json(['success' => false, 'message' => __('Please complete your name and email.', 'tfp-dashboard')], 400);
    }

    if (!is_email($email)) {
        wp_send_json(['success' => false, 'message' => __('Please enter a valid email address.', 'tfp-dashboard')], 400);
    }

    $customer = WC()->customer;
    $customer->set_billing_first_name($first_name);
    $customer->set_billing_last_name($last_name);
    $customer->set_billing_email($email);
    $customer->set_billing_phone($phone);
    $customer->set_billing_country($customer->get_billing_country() ? $customer->get_billing_country() : 'US');
    $customer->save();

    if (is_user_logged_in()) {
        update_user_meta(get_current_user_id(), 'billing_phone', $phone);
    }

    wp_send_json([
        'success' => true,
        'message' => __('Contact details saved.', 'tfp-dashboard'),
    ]);
}

/* -------------------------------------------------------------------------
 * AJAX: current cart total for the PayPal button
 *
 * The program product is only added to the cart when a seat is reserved, so
 * the total localized into tfpPayPalSettings at page load is stale ("0.00").
 * The Payment step calls this right before rendering the PayPal button so the
 * amount PayPal authorizes matches the live cart (incl. any FA discount). The
 * real order total is still built server-side from the cart at capture time.
 * ---------------------------------------------------------------------- */

add_action('wp_ajax_tfp_course_paypal_total', 'tfp_course_ajax_paypal_total');

function tfp_course_ajax_paypal_total()
{
    tfp_checkout_verify_request();

    if (!function_exists('WC') || !WC()->cart) {
        wp_send_json(['success' => false, 'message' => __('Cart not available.', 'tfp-dashboard')], 400);
    }

    if (function_exists('tfp_checkout_recalculate_totals')) {
        tfp_checkout_recalculate_totals();
    }

    wp_send_json([
        'success' => true,
        'total'   => wc_format_decimal((float) WC()->cart->get_total('edit'), 2),
    ]);
}

/* -------------------------------------------------------------------------
 * AJAX: complete a $0 enrollment (financial aid covers the full fee)
 *
 * When approved financial aid discounts the program to $0.00 there is nothing
 * to charge — Stripe rejects any amount under the minimum, and PayPal can't
 * authorize $0. This handler is the "free" path: it re-checks the total on the
 * SERVER (never trusts the browser), and only if the cohort cart is genuinely
 * $0 does it build the real WooCommerce order and mark it paid. That fires the
 * same cohort-stamp + enrollment hooks as every other method, so a fully-aided
 * student is enrolled exactly like a paying one — just without a charge.
 * ---------------------------------------------------------------------- */

add_action('wp_ajax_tfp_course_free_enroll', 'tfp_course_ajax_free_enroll');

function tfp_course_ajax_free_enroll()
{
    tfp_checkout_verify_request();

    if (!is_user_logged_in()) {
        wp_send_json(['success' => false, 'message' => __('You must be logged in.', 'tfp-dashboard')], 403);
    }

    if (!function_exists('WC') || !WC()->cart || WC()->cart->is_empty()) {
        wp_send_json(['success' => false, 'message' => __('Your cart is empty.', 'tfp-dashboard')], 400);
    }

    // The free path is only for program (cohort) carts.
    if (!tfp_course_cart_cohort_id()) {
        wp_send_json(['success' => false, 'message' => __('This is not a program order.', 'tfp-dashboard')], 400);
    }

    // SERVER-AUTHORITATIVE total check: the free path is valid ONLY when the
    // order is genuinely $0 (e.g. 100% financial aid). Recalculate first so the
    // FA coupon + our no-shipping filter are reflected, then require a zero total.
    if (function_exists('tfp_checkout_recalculate_totals')) {
        tfp_checkout_recalculate_totals();
    }
    $currency    = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD';
    $total       = (float) WC()->cart->get_total('edit');
    $total_minor = function_exists('tfp_stripe_to_minor_units')
        ? tfp_stripe_to_minor_units($total, $currency)
        : (int) round($total * 100);

    if ($total_minor >= 1) {
        wp_send_json(['success' => false, 'message' => __('This order requires payment. Please choose a payment method.', 'tfp-dashboard')], 400);
    }

    if (!defined('WOOCOMMERCE_CHECKOUT')) {
        define('WOOCOMMERCE_CHECKOUT', true);
    }

    $customer = WC()->customer;
    $data = [
        'customer_id'        => get_current_user_id(),
        'payment_method'     => 'tfp_free',
        'billing_first_name' => $customer->get_billing_first_name(),
        'billing_last_name'  => $customer->get_billing_last_name(),
        'billing_company'    => '',
        'billing_email'      => $customer->get_billing_email(),
        'billing_phone'      => $customer->get_billing_phone(),
        'billing_country'    => $customer->get_billing_country() ? $customer->get_billing_country() : 'US',
        'order_comments'     => '',
    ];

    $order = null;
    try {
        $order_id = WC()->checkout()->create_order($data);
        if (is_wp_error($order_id)) {
            wp_send_json(['success' => false, 'message' => $order_id->get_error_message()], 400);
        }
        $order = wc_get_order($order_id);
    } catch (Exception $e) {
        wp_send_json(['success' => false, 'message' => $e->getMessage()], 400);
    }

    if (!$order) {
        wp_send_json(['success' => false, 'message' => __('Your enrollment could not be completed. Please contact support.', 'tfp-dashboard')], 400);
    }

    $order->set_payment_method('tfp_free');
    $order->set_payment_method_title(__('Financial Aid (100% covered)', 'tfp-dashboard'));
    $order->add_order_note(__('Enrolled at no cost — financial aid covered the full program fee.', 'tfp-dashboard'));
    // A $0 order: payment_complete() moves it to processing/completed, firing the
    // cohort-stamp + tfp_course_enroll_from_order() enrollment hooks below.
    $order->payment_complete();
    $order->save();

    WC()->cart->empty_cart();

    wp_send_json([
        'success'  => true,
        'popup'    => true,
        'order_id' => $order->get_id(),
        'order_key' => $order->get_order_key(),
        'redirect' => $order->get_checkout_order_received_url(),
    ]);
}

/* -------------------------------------------------------------------------
 * Cart: a program is virtual — never charge shipping on a cohort cart
 *
 * The shared tfp_checkout_recalculate_totals() (checkout/ajax.php) deliberately
 * FORCES woocommerce_cart_needs_shipping = true (priority 10) because the book
 * checkout genuinely ships a physical product. Every course handler reuses that
 * function, so without this a cohort cart picks up the store's flat-rate
 * ("Standard shipping", e.g. $4.99) even though the product is marked Virtual —
 * the forced filter overrides the product's own needs_shipping() = false.
 *
 * We re-disable shipping at priority 99 (so it wins over the forced __return_true)
 * but ONLY when the cart holds a cohort line item; book carts are untouched. With
 * needs_shipping() false, WC_Cart::calculate_shipping() produces no packages and
 * the shipping total is 0 across every path (reserve, paypal-total, Stripe
 * intent/complete). This does not depend on the product's Virtual flag.
 * ---------------------------------------------------------------------- */

add_filter('woocommerce_cart_needs_shipping', function ($needs_shipping) {
    // Check the live cart line items directly (NOT tfp_course_cart_cohort_id(),
    // whose session fallback could misfire on a book cart if a course flow was
    // abandoned with the selection still in session). Shipping is about what is
    // physically in the cart right now.
    if (function_exists('WC') && WC()->cart) {
        foreach (WC()->cart->get_cart() as $item) {
            if (!empty($item['tfp_cohort_id'])) {
                return false;
            }
        }
    }
    return $needs_shipping;
}, 99);

/* -------------------------------------------------------------------------
 * Cart: per-cohort price override
 * ---------------------------------------------------------------------- */

add_action('woocommerce_before_calculate_totals', function ($cart) {
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }
    if (!$cart || !is_object($cart)) {
        return;
    }

    foreach ($cart->get_cart() as $item) {
        if (empty($item['tfp_cohort_id'])) {
            continue;
        }
        $cohort_id = (int) $item['tfp_cohort_id'];

        if (function_exists('tfp_cohort_has_price_override') && tfp_cohort_has_price_override($cohort_id)) {
            if (isset($item['data']) && is_object($item['data'])) {
                $item['data']->set_price((float) tfp_cohort_price($cohort_id));
            }
        }
    }
}, 20);

/* -------------------------------------------------------------------------
 * Order: stamp the cohort onto the order + line item at creation
 * ---------------------------------------------------------------------- */

add_action('woocommerce_checkout_create_order', function ($order, $data) {
    $cohort_id = tfp_course_cart_cohort_id();
    if ($cohort_id) {
        $order->update_meta_data('_tfp_cohort_id', $cohort_id);
    }
}, 10, 2);

add_action('woocommerce_checkout_create_order_line_item', function ($item, $cart_item_key, $values, $order) {
    if (empty($values['tfp_cohort_id'])) {
        return;
    }
    $cohort_id = (int) $values['tfp_cohort_id'];
    $item->add_meta_data('_tfp_cohort_id', $cohort_id, true);

    $name = get_the_title($cohort_id);
    if ($name) {
        // Human-readable, shows on the order + emails.
        $item->add_meta_data(__('Cohort', 'tfp-dashboard'), $name, true);
    }
}, 10, 4);

/* -------------------------------------------------------------------------
 * Enrollment: once payment is confirmed, record the cohort on the student
 * ---------------------------------------------------------------------- */

function tfp_course_enroll_from_order($order_id)
{
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }

    $user_id = $order->get_user_id();
    if (!$user_id) {
        return;
    }

    $cohort_id = (int) $order->get_meta('_tfp_cohort_id');
    if (!$cohort_id) {
        foreach ($order->get_items() as $item) {
            $line_cohort = (int) $item->get_meta('_tfp_cohort_id');
            if ($line_cohort) {
                $cohort_id = $line_cohort;
                break;
            }
        }
    }

    if (!$cohort_id) {
        return; // Not a cohort/program order — leave it alone.
    }

    update_user_meta($user_id, 'tfp_cohort_choice', $cohort_id);
    update_user_meta($user_id, 'tfp_program_status', 'enrolled');

    // Keep the rest of the dashboard's program resolution correct: if the user
    // never had tfp_program_choice set, seed it from the cohort's course.
    $course_id = (int) get_post_meta($cohort_id, '_related_course', true);
    if ($course_id && !get_user_meta($user_id, 'tfp_program_choice', true)) {
        update_user_meta($user_id, 'tfp_program_choice', $course_id);
    }

    if (function_exists('tfp_cohort_flush_seats_cache')) {
        tfp_cohort_flush_seats_cache($cohort_id);
    }
    wp_cache_delete('tfp_has_paid_' . $user_id, 'tfp_dashboard');

    if (function_exists('WC') && WC()->session) {
        WC()->session->set('tfp_selected_cohort', null);
    }
}
add_action('woocommerce_order_status_processing', 'tfp_course_enroll_from_order');
add_action('woocommerce_order_status_completed', 'tfp_course_enroll_from_order');
