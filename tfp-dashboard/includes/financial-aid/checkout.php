<?php
if (!defined('ABSPATH')) exit;

/**
 * Check if the current user has an approved financial aid coupon
 * and automatically apply it to their cart.
 */
add_action('woocommerce_before_calculate_totals', function ($cart) {
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }

    if (!is_user_logged_in()) {
        return;
    }

    // Only run this when on the frontend, like cart or checkout
    if (did_action('woocommerce_before_calculate_totals') >= 2) {
        return;
    }

    $user_id = get_current_user_id();

    // Find if the user has an approved financial aid application
    $args = [
        'post_type'      => 'tfp_financial_aid',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'meta_query'     => [
            'relation' => 'AND',
            [
                'key'   => '_tfp_student_id',
                'value' => $user_id,
            ],
            [
                'key'   => '_tfp_status',
                'value' => 'approved',
            ],
        ],
    ];

    $applications = get_posts($args);

    if (empty($applications)) {
        return;
    }

    $post_id = $applications[0]->ID;
    $coupon_code = get_post_meta($post_id, '_tfp_generated_coupon', true);

    if (empty($coupon_code)) {
        return;
    }

    // Check if the coupon exists and is valid
    $coupon = new WC_Coupon($coupon_code);
    if (!$coupon->is_valid()) {
        return;
    }

    // Apply the coupon if it's not already applied
    if (!$cart->has_discount($coupon_code)) {
        $cart->add_discount($coupon_code);
        // Optionally, print a notice that financial aid was applied
        if (!wc_has_notice(__('Your Financial Aid discount has been automatically applied.', 'tfp-dashboard'))) {
            wc_add_notice(__('Your Financial Aid discount has been automatically applied.', 'tfp-dashboard'), 'success');
        }
    }
});

/**
 * Hide the coupon field on the checkout if they have a financial aid coupon applied
 * to prevent them from removing it or messing with it.
 */
add_action('woocommerce_before_checkout_form', function () {
    if (!is_user_logged_in()) {
        return;
    }
    
    $applied_coupons = WC()->cart->get_applied_coupons();
    if (empty($applied_coupons)) {
        return;
    }

    $user_id = get_current_user_id();

    $args = [
        'post_type'      => 'tfp_financial_aid',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'meta_query'     => [
            'relation' => 'AND',
            [
                'key'   => '_tfp_student_id',
                'value' => $user_id,
            ],
            [
                'key'   => '_tfp_status',
                'value' => 'approved',
            ],
        ],
    ];

    $applications = get_posts($args);
    if (empty($applications)) {
        return;
    }

    $post_id = $applications[0]->ID;
    $coupon_code = get_post_meta($post_id, '_tfp_generated_coupon', true);

    if (!empty($coupon_code) && in_array(strtolower($coupon_code), array_map('strtolower', $applied_coupons))) {
        // Output CSS to hide the coupon form and the remove link in the order review
        echo '<style>
            .woocommerce-form-coupon-toggle,
            form.woocommerce-form-coupon,
            .woocommerce-remove-coupon {
                display: none !important;
            }
        </style>';
    }
}, 5);
