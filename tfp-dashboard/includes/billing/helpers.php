<?php
/**
 * Billing helpers.
 *
 * Payment itself is processed entirely by WooCommerce's own cart +
 * checkout flow (native add-to-cart URL + `woocommerce_add_to_cart_redirect`
 * filter). This plugin never touches raw card data — whichever gateway is
 * configured in WooCommerce (Stripe, Razorpay, PayPal, etc.) just works.
 */

if (!defined('ABSPATH')) exit;

/**
 * Resolve the linked WooCommerce product for a LearnDash course ID.
 * The LearnDash WooCommerce add-on stores the mapping on the product post
 * via `_related_course`, so we look for that relationship here.
 */
function tfp_billing_get_product_id_for_course($course_id)
{
    $course_id = (int) $course_id;
    if (!$course_id) {
        return 0;
    }

    $product_id = 0;
    $product_ids = get_posts([
        'post_type'      => ['product', 'product_variation'],
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'orderby'        => 'ID',
        'order'          => 'ASC',
        'suppress_filters' => false,
    ]);

    foreach ($product_ids as $candidate_id) {
        $related_courses = get_post_meta($candidate_id, '_related_course', true);

        if (is_array($related_courses)) {
            $related_courses = array_map('intval', $related_courses);
            if (in_array($course_id, $related_courses, true)) {
                $product_id = (int) $candidate_id;
                break;
            }
        } elseif (!empty($related_courses) && (int) $related_courses === $course_id) {
            $product_id = (int) $candidate_id;
            break;
        }
    }

    return (int) apply_filters('tfp_billing_course_product_id', $product_id, $course_id);
}

/**
 * The WooCommerce product ID representing the user's chosen program.
 * Reuses the `tfp_program_choice` user meta already saved at registration,
 * but resolves it through the LearnDash course -> WooCommerce product link.
 *
 * Fallback: some users never go through our custom registration form at
 * all — they can buy a program product directly via WooCommerce's native
 * checkout (e.g. straight from the course/product page), which creates a
 * WordPress account but never sets `tfp_program_choice`. In that case we
 * look at their actual paid orders instead, find whichever purchased
 * product maps to a LearnDash course, and self-heal by backfilling
 * `tfp_program_choice` so the rest of the site (e.g. the
 * [tfp_current_program] shortcode) stays correct too.
 */
function tfp_billing_get_program_product_id($user_id = null)
{
    $user_id = $user_id ?: get_current_user_id();
    $course_id = (int) get_user_meta($user_id, 'tfp_program_choice', true);

    if ($course_id) {
        $product_id = tfp_billing_get_product_id_for_course($course_id);
        if ($product_id) {
            return $product_id;
        }
    }

    return tfp_billing_find_paid_program_product_id($user_id);
}

/**
 * Reverse lookup: does this user have a completed/processing order for
 * ANY product that maps to a LearnDash course via `_related_course`?
 * If so, backfill `tfp_program_choice` and return that product ID.
 */
function tfp_billing_find_paid_program_product_id($user_id)
{
    if (!$user_id || !function_exists('wc_get_orders')) {
        return 0;
    }

    $orders = wc_get_orders([
        'customer' => $user_id,
        'status'   => ['completed', 'processing'],
        'limit'    => -1,
    ]);

    foreach ($orders as $order) {
        foreach ($order->get_items() as $item) {
            if (!class_exists('WC_Order_Item_Product') || !($item instanceof WC_Order_Item_Product)) {
                continue;
            }

            $product_id = (int) $item->get_product_id();
            $related_course = get_post_meta($product_id, '_related_course', true);

            if (empty($related_course)) {
                continue;
            }

            $course_id = is_array($related_course) ? (int) reset($related_course) : (int) $related_course;

            if ($course_id && !get_user_meta($user_id, 'tfp_program_choice', true)) {
                update_user_meta($user_id, 'tfp_program_choice', $course_id);
            }

            return $product_id;
        }
    }

    return 0;
}

function tfp_billing_get_program_product($user_id = null)
{
    if (!function_exists('wc_get_product')) {
        return null;
    }
    $product_id = tfp_billing_get_program_product_id($user_id);
    return $product_id ? wc_get_product($product_id) : null;
}

/**
 * Has this user already paid for their program? Checks completed/processing
 * WooCommerce orders for a line item matching the program product.
 */
function tfp_billing_user_has_paid($user_id = null)
{
    $user_id = $user_id ?: get_current_user_id();
    $product_id = tfp_billing_get_program_product_id($user_id);

    if (!$product_id || !function_exists('wc_get_orders')) {
        return false;
    }

    $cache_key = 'tfp_has_paid_' . $user_id;
    $cached = wp_cache_get($cache_key, 'tfp_dashboard');
    if ($cached !== false) {
        return (bool) $cached;
    }

    $orders = wc_get_orders([
        'customer' => $user_id,
        'status'   => ['completed', 'processing'],
        'limit'    => -1,
    ]);

    $paid = false;
    foreach ($orders as $order) {
        foreach ($order->get_items() as $item) {
            $item_product_id = 0;
            if (class_exists('WC_Order_Item_Product') && $item instanceof WC_Order_Item_Product) {
                $item_product_id = (int) $item->get_product_id();
            }

            if ($item_product_id === $product_id) {
                $paid = true;
                break 2;
            }
        }
    }

    wp_cache_set($cache_key, $paid, 'tfp_dashboard', 60);
    return $paid;
}

/**
 * The most recent order for the user's program (for showing a receipt /
 * "paid on ..." message on the Billing page).
 */
function tfp_billing_get_program_order($user_id = null)
{
    $user_id = $user_id ?: get_current_user_id();
    $product_id = tfp_billing_get_program_product_id($user_id);

    if (!$product_id || !function_exists('wc_get_orders')) {
        return null;
    }

    $orders = wc_get_orders([
        'customer' => $user_id,
        'status'   => ['completed', 'processing'],
        'limit'    => -1,
        'orderby'  => 'date',
        'order'    => 'DESC',
    ]);

    foreach ($orders as $order) {
        foreach ($order->get_items() as $item) {
            $item_product_id = 0;
            if (class_exists('WC_Order_Item_Product') && $item instanceof WC_Order_Item_Product) {
                $item_product_id = (int) $item->get_product_id();
            }

            if ($item_product_id === $product_id) {
                return $order;
            }
        }
    }

    return null;
}

/**
 * Plain "Pay Now" URL — a normal WooCommerce add-to-cart link, no AJAX,
 * no custom session handling. Reliable and gateway-agnostic.
 */
function tfp_billing_pay_now_url($user_id = null)
{
    if (!function_exists('wc_get_cart_url')) {
        return '#';
    }

    $product_id = tfp_billing_get_program_product_id($user_id);
    if (!$product_id) {
        return '#';
    }

    return add_query_arg([
        'add-to-cart' => $product_id,
        'tfp_pay_now' => 1,
    ], wc_get_cart_url());
}

/**
 * Empty the cart right before WooCommerce adds the program product, so
 * repeat visits to "Pay Now" never stack duplicate line items.
 */
add_action('wp', function () {
    if (!empty($_GET['tfp_pay_now']) && !empty($_GET['add-to-cart']) && function_exists('WC') && WC()->cart) {
        WC()->cart->empty_cart();
    }
}, 5);

/**
 * Once WooCommerce has added the program product to the cart, send the
 * user straight to Checkout instead of the Cart page.
 */
add_filter('woocommerce_add_to_cart_redirect', function ($url) {
    if (!empty($_REQUEST['tfp_pay_now']) && function_exists('wc_get_checkout_url')) {
        return wc_get_checkout_url();
    }
    return $url;
});

/**
 * Does the user have a saved payment method on file? Uses WooCommerce's
 * own payment tokens (works with whatever gateway is configured that
 * supports tokenization — Stripe, Razorpay, etc.). Used for the "Set Up
 * Billing" task card, which stays relevant even after payment (users may
 * need to update their card later).
 */
function tfp_billing_user_has_saved_payment_method($user_id = null)
{
    $user_id = $user_id ?: get_current_user_id();

    if (!$user_id || !class_exists('WC_Payment_Tokens')) {
        return false;
    }

    $tokens = WC_Payment_Tokens::get_customer_tokens($user_id);
    return !empty($tokens);
}

/**
 * Summary of the user's default saved payment method, for display only
 * (Profile page's "Billing Information" panel). Never exposes full card
 * numbers — WooCommerce's payment tokens only ever store the brand,
 * last 4 digits, and expiry, never the full PAN.
 *
 * @return array|null ['brand' => 'Visa', 'last4' => '4242', 'expiry' => '09/2027'] or null if none saved.
 */
function tfp_billing_get_default_card_summary($user_id = null)
{
    $user_id = $user_id ?: get_current_user_id();

    if (!$user_id || !class_exists('WC_Payment_Tokens')) {
        return null;
    }

    $token = WC_Payment_Tokens::get_customer_default_token($user_id);

    if (!$token) {
        $tokens = WC_Payment_Tokens::get_customer_tokens($user_id);
        $token = !empty($tokens) ? reset($tokens) : null;
    }

    if (!$token || !method_exists($token, 'get_type') || 'CC' !== $token->get_type()) {
        return null;
    }

    return [
        'brand'  => method_exists($token, 'get_card_type') ? ucfirst($token->get_card_type()) : __('Card', 'tfp-dashboard'),
        'last4'  => method_exists($token, 'get_last4') ? $token->get_last4() : '',
        'expiry' => method_exists($token, 'get_expiry_month') && method_exists($token, 'get_expiry_year')
            ? sprintf('%02d / %s', (int) $token->get_expiry_month(), $token->get_expiry_year())
            : '',
    ];
}


/**
 * The billing email WooCommerce keeps on the customer record (separate
 * from their main account email — falls back to the account email if
 * no billing email was ever set).
 */
function tfp_billing_get_billing_email($user_id = null)
{
    $user_id = $user_id ?: get_current_user_id();
    $billing_email = get_user_meta($user_id, 'billing_email', true);

    if (!empty($billing_email)) {
        return $billing_email;
    }

    $user = get_userdata($user_id);
    return $user ? $user->user_email : '';
}
/**
 * Grades / Communication / Calendar unlock automatically once payment
 * is confirmed (matches the two Figma sidebar variants).
 */
add_filter('tfp_dashboard_nav_items', function ($items) {
    $user_id = get_current_user_id();
    if (!$user_id || tfp_billing_user_has_paid($user_id)) {
        return $items;
    }

    $allowed = ['home', 'documents', 'profile'];
    return array_values(array_filter($items, function ($item) use ($allowed) {
        return in_array($item['id'], $allowed, true);
    }));
});
