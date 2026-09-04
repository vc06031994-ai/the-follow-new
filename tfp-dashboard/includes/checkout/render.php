<?php
if (!defined('ABSPATH')) {
    exit;
}

function tfp_checkout_current_step()
{
    $step = isset($_GET['tfp_checkout_step']) ? sanitize_key(wp_unslash($_GET['tfp_checkout_step'])) : 'cart';
    $valid = array('cart', 'contact', 'delivery', 'payment');

    return in_array($step, $valid, true) ? $step : 'cart';
}

function tfp_checkout_step_is_allowed($step)
{
    if (!function_exists('WC') || !WC()->cart) {
        return false;
    }

    if ($step === 'cart') {
        return true;
    }

    if (WC()->cart->is_empty()) {
        return false;
    }

    if ($step === 'contact') {
        return true;
    }

    if ($step === 'delivery') {
        $customer = WC()->customer;
        return !empty($customer->get_shipping_city()) && !empty($customer->get_shipping_state()) && !empty($customer->get_shipping_postcode());
    }

    if ($step === 'payment') {
        $chosen = WC()->session->get('chosen_shipping_methods');
        $customer = WC()->customer;
        return !empty($chosen) && !empty($customer->get_shipping_city()) && !empty($customer->get_shipping_state()) && !empty($customer->get_shipping_postcode());
    }

    return true;
}

function tfp_checkout_customer_defaults()
{
    $defaults = [
        'first_name' => '',
        'last_name' => '',
        'email' => '',
        'phone' => '',
        'street' => '',
        'apt' => '',
        'city' => '',
        'state' => '',
        'postcode' => '',
    ];

    if (!function_exists('WC') || !WC()->customer) {
        return $defaults;
    }

    $customer = WC()->customer;
    $user_id = get_current_user_id();

    $defaults['first_name'] = $customer->get_billing_first_name();
    $defaults['last_name'] = $customer->get_billing_last_name();
    $defaults['email'] = $customer->get_billing_email();
    $defaults['phone'] = $customer->get_billing_phone();
    $defaults['street'] = $customer->get_shipping_address_1() ? $customer->get_shipping_address_1() : $customer->get_billing_address_1();
    $defaults['apt'] = $customer->get_shipping_address_2() ? $customer->get_shipping_address_2() : $customer->get_billing_address_2();
    $defaults['city'] = $customer->get_shipping_city() ? $customer->get_shipping_city() : $customer->get_billing_city();
    $defaults['state'] = $customer->get_shipping_state() ? $customer->get_shipping_state() : $customer->get_billing_state();
    $defaults['postcode'] = $customer->get_shipping_postcode() ? $customer->get_shipping_postcode() : $customer->get_billing_postcode();

    if (!empty($user_id) && empty($defaults['email'])) {
        $user = get_userdata($user_id);
        if ($user) {
            $defaults['email'] = $user->user_email;
        }
    }

    if (empty($defaults['first_name']) && !empty($user_id)) {
        $defaults['first_name'] = get_user_meta($user_id, 'first_name', true);
    }

    if (empty($defaults['last_name']) && !empty($user_id)) {
        $defaults['last_name'] = get_user_meta($user_id, 'last_name', true);
    }

    if (empty($defaults['phone']) && !empty($user_id)) {
        $defaults['phone'] = get_user_meta($user_id, 'billing_phone', true);
    }

    if (empty($defaults['street']) && !empty($user_id)) {
        $defaults['street'] = get_user_meta($user_id, 'tfp_address', true);
    }

    return $defaults;
}

function tfp_checkout_get_cart_items()
{
    if (!function_exists('WC') || !WC()->cart) {
        return array();
    }

    $items = array();
    foreach (WC()->cart->get_cart() as $key => $item) {
        $product = wc_get_product($item['product_id']);
        $items[] = array(
            'cart_key' => $key,
            'product' => $product,
            'name' => $product ? $product->get_name() : '',
            'subtitle' => $product && $product->get_short_description() ? wp_strip_all_tags($product->get_short_description()) : __('Book', 'tfp-dashboard'),
            'quantity' => $item['quantity'],
            'price' => wc_price($item['line_total']),
            'image' => $product && has_post_thumbnail($product->get_id()) ? get_the_post_thumbnail_url($product->get_id(), 'thumbnail') : '',
        );
    }

    return $items;
}

function tfp_checkout_get_order_summary_data()
{
    if (!function_exists('WC') || !WC()->cart) {
        return array(
            'subtotal' => wc_price(0),
            'shipping' => wc_price(0),
            'tax' => wc_price(0),
            'total' => wc_price(0),
            'items' => array(),
        );
    }

    $cart = WC()->cart;
    $tax_total = 0;
    $tax_totals = $cart->get_tax_totals();
    if (is_array($tax_totals)) {
        foreach ($tax_totals as $tax) {
            $tax_total += floatval($tax->amount);
        }
    }

    return array(
        'subtotal' => wc_price($cart->get_subtotal()),
        'shipping' => wc_price($cart->get_shipping_total()),
        'tax' => wc_price($tax_total),
        'total' => wc_price($cart->get_total('edit')),
        'items' => tfp_checkout_get_cart_items(),
    );
}

function tfp_checkout_render_order_summary()
{
    $summary = tfp_checkout_get_order_summary_data();
    ?>
    <aside class="tfp-checkout-order-summary">
        <h3><?php echo esc_html__('Order Summary', 'tfp-dashboard'); ?></h3>
        <div class="tfp-checkout-cart-list">
            <?php foreach ($summary['items'] as $item) : ?>
                <div class="tfp-checkout-cart-item">
                    <div class="tfp-checkout-cart-image">
                        <?php if (!empty($item['image'])) : ?>
                            <img src="<?php echo esc_url($item['image']); ?>" alt="<?php echo esc_attr($item['name']); ?>" />
                        <?php else : ?>
                            <span class="tfp-checkout-cart-image-placeholder"></span>
                        <?php endif; ?>
                    </div>
                    <div class="tfp-checkout-cart-copy">
                        <div class="tfp-checkout-cart-name"><?php echo esc_html($item['name']); ?></div>
                        <div class="tfp-checkout-cart-subtitle"><?php echo esc_html($item['subtitle']); ?></div>
                        <div class="tfp-checkout-cart-meta">
                            <?php echo esc_html__('Physical Book', 'tfp-dashboard'); ?> &middot; <?php echo esc_html__('Qty:', 'tfp-dashboard'); ?> <?php echo esc_html($item['quantity']); ?>
                        </div>
                    </div>
                    <div class="tfp-checkout-cart-actions">
                        <div class="tfp-checkout-cart-price"><?php echo wp_kses_post($item['price']); ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="tfp-checkout-promo-wrapper">
            <a href="#" class="tfp-checkout-toggle-promo"><?php echo esc_html__('Add Promo Code', 'tfp-dashboard'); ?> +</a>
            <div class="tfp-checkout-promo-panel" style="display:none;">
                <input type="text" name="coupon_code" class="tfp-checkout-coupon-input" placeholder="<?php echo esc_attr__('Enter code', 'tfp-dashboard'); ?>" />
                <button type="button" class="tfp-checkout-coupon-submit tfp-dash-btn tfp-dash-btn--primary"><?php echo esc_html__('Apply', 'tfp-dashboard'); ?></button>
            </div>
        </div>

        <div class="tfp-checkout-cart-totals">
            <div class="tfp-checkout-totals-row">
                <span><?php echo esc_html__('Subtotal', 'tfp-dashboard'); ?></span>
                <strong><?php echo wp_kses_post($summary['subtotal']); ?></strong>
            </div>
            <div class="tfp-checkout-totals-row">
                <span><?php echo esc_html__('Shipping', 'tfp-dashboard'); ?></span>
                <strong><?php echo wp_kses_post($summary['shipping']); ?></strong>
            </div>
            <div class="tfp-checkout-totals-row">
                <span><?php echo esc_html__('Tax', 'tfp-dashboard'); ?></span>
                <strong><?php echo wp_kses_post($summary['tax']); ?></strong>
            </div>
            <div class="tfp-checkout-totals-row tfp-checkout-totals-final">
                <span><?php echo esc_html__('Estimated Total', 'tfp-dashboard'); ?></span>
                <strong><?php echo wp_kses_post($summary['total']); ?></strong>
            </div>
        </div>

        <div class="tfp-checkout-alert"><?php echo esc_html__('📦Allow 3-5 business days for production before shipping.', 'tfp-dashboard'); ?></div>
        <a href="<?php echo esc_url(wc_get_cart_url()); ?>" class="tfp-checkout-edit-cart"><?php echo esc_html__('Edit Cart', 'tfp-dashboard'); ?></a>
    </aside>
    <?php
}

function tfp_checkout_render_cart_step()
{
    $items = tfp_checkout_get_cart_items();
    ?>
    <div class="tfp-checkout-panel tfp-checkout-step-shell" data-step="cart">
        <h3 class="tfp-checkout-h3"><?php echo esc_html__('Your Cart', 'tfp-dashboard'); ?></h3>
        <p class="tfp-checkout-subtitle-2"><?php echo esc_html__('Review Your Cart, Edit Your Order'); ?></p>

        <div class="tfp-checkout-cart-list">
            <?php foreach ($items as $item) : ?>
                <div class="tfp-checkout-cart-item" data-cart-key="<?php echo esc_attr($item['cart_key']); ?>">
                    <div class="tfp-checkout-cart-image">
                        <?php if (!empty($item['image'])) : ?>
                            <img src="<?php echo esc_url($item['image']); ?>" alt="<?php echo esc_attr($item['name']); ?>" />
                        <?php else : ?>
                            <span class="tfp-checkout-cart-image-placeholder"></span>
                        <?php endif; ?>
                    </div>
                    <div class="tfp-checkout-cart-copy">
                        <div class="tfp-checkout-cart-name"><?php echo esc_html($item['name']); ?></div>
                        <div class="tfp-checkout-cart-subtitle"><?php echo esc_html($item['subtitle']); ?></div>
                        <div class="tfp-checkout-cart-meta">
                            <?php echo esc_html__('Physical Book', 'tfp-dashboard'); ?> &middot; <?php echo esc_html__('Qty:', 'tfp-dashboard'); ?> <?php echo esc_html($item['quantity']); ?>
                        </div>
                    </div>
                    <div class="tfp-checkout-cart-actions">
                        <div class="tfp-checkout-cart-price"><?php echo wp_kses_post($item['price']); ?></div>
                        <div class="tfp-checkout-quantity-controls" data-cart-key="<?php echo esc_attr($item['cart_key']); ?>">
                            <a href="#" class="tfp-checkout-qty-button" data-action="decrement" data-cart-key="<?php echo esc_attr($item['cart_key']); ?>">-</a>
                            <span class="tfp-checkout-qty-value"><?php echo esc_html($item['quantity']); ?></span>
                            <a href="#" class="tfp-checkout-qty-button" data-action="increment" data-cart-key="<?php echo esc_attr($item['cart_key']); ?>">+</a>
                        </div>
                        <a href="#" class="tfp-checkout-remove-item" data-cart-key="<?php echo esc_attr($item['cart_key']); ?>"><?php echo esc_html__('Remove', 'tfp-dashboard'); ?></a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="tfp-checkout-promo-wrapper">
            <a href="#" class="tfp-checkout-toggle-promo"><?php echo esc_html__('Add Promo Code', 'tfp-dashboard'); ?> +</a>
            <div class="tfp-checkout-promo-panel" style="display:none;">
                <input type="text" name="coupon_code" class="tfp-checkout-coupon-input" placeholder="<?php echo esc_attr__('Enter code', 'tfp-dashboard'); ?>" />
                <button type="button" class="tfp-checkout-coupon-submit tfp-dash-btn tfp-dash-btn--primary"><?php echo esc_html__('Apply', 'tfp-dashboard'); ?></button>
            </div>
        </div>

        <?php $summary = tfp_checkout_get_order_summary_data(); ?>
        <div class="tfp-checkout-cart-totals">
            <div class="tfp-checkout-totals-row">
                <span><?php echo esc_html__('Subtotal', 'tfp-dashboard'); ?></span>
                <strong><?php echo wp_kses_post($summary['subtotal']); ?></strong>
            </div>
            <div class="tfp-checkout-totals-row">
                <span><?php echo esc_html__('Shipping', 'tfp-dashboard'); ?></span>
                <strong><?php echo wp_kses_post($summary['shipping']); ?></strong>
            </div>
            <div class="tfp-checkout-totals-row">
                <span><?php echo esc_html__('Tax', 'tfp-dashboard'); ?></span>
                <strong><?php echo wp_kses_post($summary['tax']); ?></strong>
            </div>
            <div class="tfp-checkout-totals-row tfp-checkout-totals-final">
                <span><?php echo esc_html__('Estimated Total', 'tfp-dashboard'); ?></span>
                <strong><?php echo wp_kses_post($summary['total']); ?></strong>
            </div>
        </div>

        <div class="tfp-checkout-alert"><?php echo esc_html__('📦These are print-on-demand books. Please allow 3-5 business days for production before your order ships.', 'tfp-dashboard'); ?></div>

        <div class="tfp-checkout-actions tfp-checkout-actions--split">
            <a href="<?php echo esc_url(home_url('/shop/')); ?>" class="tfp-dash--secondary tfp-dash-btn">
                <svg xmlns="http://www.w3.org/2000/svg" width="5" height="8" viewBox="0 0 5 8" fill="none">
                    <path d="M4.93994 0.94L1.88661 4L4.93994 7.06L3.99994 8L-5.88258e-05 4L3.99994 -4.10887e-08L4.93994 0.94Z" fill="currentColor" />
                </svg>
                <?php echo esc_html__('Continue Shopping', 'tfp-dashboard'); ?>

            </a>
            <a href="<?php echo esc_url(add_query_arg('tfp_checkout_step', 'contact', get_permalink(get_the_ID()))); ?>" class=" tfp-dash-btn tfp-dash-btn--primary tfp-checkout-step-next"><?php echo esc_html__('Proceed to Checkout', 'tfp-dashboard'); ?>
                <svg xmlns="http://www.w3.org/2000/svg" width="5" height="8" viewBox="0 0 5 8" fill="none">
                    <path d="M-4.10887e-08 7.06L3.05333 4L-3.08602e-07 0.94L0.94 -4.10887e-08L4.94 4L0.94 8L-4.10887e-08 7.06Z" fill="currentColor" />
                </svg>
            </a>
        </div>
    </div>
    <?php
}

function tfp_checkout_render_contact_step()
{
    $data = tfp_checkout_customer_defaults();
    ?>
    <div class="tfp-checkout-panel tfp-contact-panel" data-step="contact" style="display:none; flex-direction:column; gap:24px;">
        <div class="tfp-checkout-step-shell" style="display:block;">
            <div class="tfp-checkout-step-header" style="color:#847230; font-size:18px; font-weight:700; font-family:var(--tfp-dash-font); margin-bottom: 4px; line-height:130%"><?php echo esc_html__('Step 1 of 3', 'tfp-dashboard'); ?></div>
            <div class="tfp-checkout-step-subheader" style="color:#151411; font-weight:600; font-size: 13px; line-height:130%"><?php echo esc_html__('Contact Information', 'tfp-dashboard'); ?></div>

            <div class="tfp-checkout-section-block">
                <div class="tfp-checkout-form-row">
                    <div class="tfp-checkout-field">
                        <label for="tfp_checkout_first_name"><?php echo esc_html__('First Name', 'tfp-dashboard'); ?></label>
                        <input id="tfp_checkout_first_name" type="text" name="first_name" value="<?php echo esc_attr($data['first_name']); ?>" />
                    </div>
                    <div class="tfp-checkout-field">
                        <label for="tfp_checkout_last_name"><?php echo esc_html__('Last Name', 'tfp-dashboard'); ?></label>
                        <input id="tfp_checkout_last_name" type="text" name="last_name" value="<?php echo esc_attr($data['last_name']); ?>" />
                    </div>
                </div>
                <div class="tfp-checkout-form-row">
                    <div class="tfp-checkout-field tfp-checkout-field--full">
                        <label for="tfp_checkout_email"><?php echo esc_html__('Email Address', 'tfp-dashboard'); ?></label>
                        <input id="tfp_checkout_email" type="email" name="email" value="<?php echo esc_attr($data['email']); ?>" />
                    </div>
                </div>
                <div class="tfp-checkout-form-row">
                    <div class="tfp-checkout-field tfp-checkout-field--full">
                        <label for="tfp_checkout_phone"><?php echo esc_html__('Phone Number', 'tfp-dashboard'); ?></label>
                        <input id="tfp_checkout_phone" type="tel" name="phone" value="<?php echo esc_attr($data['phone']); ?>" />
                    </div>
                </div>
            </div>
        </div>

        <div class="tfp-checkout-step-shell" style="display:block;">
            <div class="tfp-checkout-step-header" style="color:#847230; font-size:18px; font-weight:700; font-family:var(--tfp-dash-font); margin-bottom: 4px; line-height:130%"><?php echo esc_html__('Shipping Address', 'tfp-dashboard'); ?></div>
            <div class="tfp-checkout-step-subheader" style="color:#151411; font-weight:600; font-size: 13px; line-height:130%"><?php echo esc_html__('Where Should We Ship?', 'tfp-dashboard'); ?></div>
            
            <div class="tfp-checkout-section-block">
                <div class="tfp-checkout-form-row">
                    <div class="tfp-checkout-field tfp-checkout-field--full">
                        <label for="tfp_checkout_street"><?php echo esc_html__('Street Address', 'tfp-dashboard'); ?></label>
                        <input id="tfp_checkout_street" type="text" name="street" value="<?php echo esc_attr($data['street']); ?>" />
                    </div>
                </div>
                <div class="tfp-checkout-form-row">
                    <div class="tfp-checkout-field tfp-checkout-field--full">
                        <label for="tfp_checkout_apt"><?php echo esc_html__('Apt / Suite', 'tfp-dashboard'); ?></label>
                        <input id="tfp_checkout_apt" type="text" name="apt" value="<?php echo esc_attr($data['apt']); ?>" />
                    </div>
                </div>
                <div class="tfp-checkout-form-row">
                    <div class="tfp-checkout-field">
                        <label for="tfp_checkout_city"><?php echo esc_html__('City', 'tfp-dashboard'); ?></label>
                        <input id="tfp_checkout_city" type="text" name="city" value="<?php echo esc_attr($data['city']); ?>" />
                    </div>
                    <div class="tfp-checkout-field">
                        <label for="tfp_checkout_state"><?php echo esc_html__('State', 'tfp-dashboard'); ?></label>
                        <input id="tfp_checkout_state" type="text" name="state" value="<?php echo esc_attr($data['state']); ?>" />
                    </div>
                    <div class="tfp-checkout-field">
                        <label for="tfp_checkout_postcode"><?php echo esc_html__('Zip Code', 'tfp-dashboard'); ?></label>
                        <input id="tfp_checkout_postcode" type="text" name="postcode" value="<?php echo esc_attr($data['postcode']); ?>" />
                    </div>
                </div>
            </div>

            <div class="tfp-checkout-actions tfp-checkout-actions--split">
                <a href="<?php echo esc_url(home_url('/shop/')); ?>" class="tfp-dash-btn tfp-dash--secondary">
                <svg xmlns="http://www.w3.org/2000/svg" width="5" height="8" viewBox="0 0 5 8" fill="none">
                        <path d="M4.93994 0.94L1.88661 4L4.93994 7.06L3.99994 8L-5.88258e-05 4L3.99994 -4.10887e-08L4.93994 0.94Z" fill="currentColor"></path>
                    </svg>    
                <?php echo esc_html__('Continue Shopping', 'tfp-dashboard'); ?>
            </a>
                <button type="button" class="tfp-dash-btn tfp-dash-btn--primary tfp-checkout-save-contact"><?php echo esc_html__('Continue to Delivery', 'tfp-dashboard'); ?>
            <svg xmlns="http://www.w3.org/2000/svg" width="5" height="8" viewBox="0 0 5 8" fill="none">
                        <path d="M-4.10887e-08 7.06L3.05333 4L-3.08602e-07 0.94L0.94 -4.10887e-08L4.94 4L0.94 8L-4.10887e-08 7.06Z" fill="currentColor"></path>
                    </svg>
            </button>
            </div>
        </div>
    </div>
    <?php
}

function tfp_checkout_render_delivery_step()
{
    ?>
    <div class="tfp-checkout-panel tfp-checkout-step-shell" data-step="delivery" style="display:none;">
        <div class="tfp-checkout-step-header" style="color:#847230; font-size:18px; font-weight:700; font-family:var(--tfp-dash-font); margin-bottom: 4px; line-height:130%"><?php echo esc_html__('Step 2 of 3', 'tfp-dashboard'); ?></div>
        <div class="tfp-checkout-step-subheader tfp-delivery-devider" style="color:#151411; font-weight:600; font-size: 13px; line-height:130%"><?php echo esc_html__('Delivery Options', 'tfp-dashboard'); ?></div>
        <p class="tfp-delivery-note">Books and merch are printed on demand. Final sale. Please allow <strong>3-5 business days</strong> for production. Shipping times begin after production is complete.</p>

        <div class="tfp-checkout-delivery-list" id="tfp-checkout-shipping-method-list"></div>

        <div class="tfp-checkout-notes">
            
            <p><?php echo esc_html__('📬 If your order contains items from multiple publishers, they may arrive in separate packages at different times.', 'tfp-dashboard'); ?></p>
        </div>

        <div class="tfp-checkout-actions tfp-checkout-actions--split">
            <a href="<?php echo esc_url(add_query_arg('tfp_checkout_step', 'contact', get_permalink(get_the_ID()))); ?>" class="tfp-dash-btn tfp-dash--secondary">
            <svg xmlns="http://www.w3.org/2000/svg" width="5" height="8" viewBox="0 0 5 8" fill="none">
                    <path d="M4.93994 0.94L1.88661 4L4.93994 7.06L3.99994 8L-5.88258e-05 4L3.99994 -4.10887e-08L4.93994 0.94Z" fill="currentColor"></path>
                </svg>    
            <?php echo esc_html__('Back to Contact', 'tfp-dashboard'); ?></a>
            <button type="button" class="tfp-dash-btn tfp-dash-btn--primary tfp-checkout-save-shipping"><?php echo esc_html__('Continue to Payment', 'tfp-dashboard'); ?><svg xmlns="http://www.w3.org/2000/svg" width="5" height="8" viewBox="0 0 5 8" fill="none">
                    <path d="M-4.10887e-08 7.06L3.05333 4L-3.08602e-07 0.94L0.94 -4.10887e-08L4.94 4L0.94 8L-4.10887e-08 7.06Z" fill="currentColor"></path>
                </svg></button>
        </div>
    </div>
    <?php
}

function tfp_checkout_render_payment_step()
{
    $selected = 'credit';
    ?>
    <div class="tfp-checkout-panel tfp-checkout-step-shell" data-step="payment" style="display:none;">
        <div class="tfp-checkout-step-header" style="color:#847230; font-size:18px; font-weight:700; font-family:var(--tfp-dash-font); margin-bottom: 4px; line-height:130%"><?php echo esc_html__('Step 3 of 3', 'tfp-dashboard'); ?></div>
        <div class="tfp-checkout-step-subheader tfp-delivery-devider" style="color:#151411; font-weight:600; font-size: 13px; line-height:130%"><?php echo esc_html__('Payment Details', 'tfp-dashboard'); ?></div>
        <h6>Choose a Payment Method</h6>

        <div class="tfp-checkout-method-grid">
            <button type="button" class="tfp-checkout-method-card is-selected" data-method="credit">
                <span class="tfp-checkout-method-cards">
                   <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 48 48" fill="none">
<path d="M24 0C37.2548 0 48 10.7452 48 24C48 37.2548 37.2548 48 24 48C10.7452 48 0 37.2548 0 24C0 10.7452 10.7452 0 24 0ZM28.4287 20.083C26.133 20.0831 24.5178 21.3133 24.5049 23.0742C24.4886 24.3775 25.6578 25.1047 26.5381 25.5371C27.4424 25.9808 27.7466 26.2654 27.7432 26.6621C27.7363 27.2693 27.0214 27.5374 26.3525 27.5479C25.1858 27.5661 24.5072 27.2306 23.9678 26.9766L23.5469 28.959C24.088 29.2104 25.0907 29.4299 26.1299 29.4395C28.5688 29.4394 30.1642 28.2256 30.1729 26.3438C30.1823 23.9556 26.8969 23.8233 26.9189 22.7559C26.9267 22.4322 27.2328 22.0869 27.9043 21.999C28.2367 21.9547 29.1545 21.9205 30.1943 22.4033L30.6025 20.4844C30.0433 20.279 29.3237 20.083 28.4287 20.083ZM9.64062 20.5107C10.4518 20.6882 11.3732 20.975 11.9316 21.2812C12.2734 21.4683 12.3712 21.6316 12.4834 22.0762L14.3359 29.2988H16.79L20.5537 20.248H18.1143L15.6963 26.4082L14.7188 21.1709C14.604 20.5862 14.1506 20.248 13.6475 20.248H9.69531L9.64062 20.5107ZM19.6289 29.2988H21.9512L23.8721 20.248H21.5498L19.6289 29.2988ZM34.5244 20.248C34.0792 20.2482 33.7035 20.5103 33.5361 20.9121L30.0527 29.2988H32.4893L32.9746 27.9473H35.9531L36.2344 29.2988H38.3828L36.5078 20.248H34.5244Z" fill="#00666E"/>
<path d="M35.569 26.0898L33.6426 26.0898L34.8656 22.6914L35.569 26.0898Z" fill="#00666E"/>
</svg>
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 48 48" fill="none">
<path d="M24 0C37.2548 0 48 10.7452 48 24C48 37.2548 37.2548 48 24 48C10.7452 48 0 37.2548 0 24C0 10.7452 10.7452 0 24 0ZM29.4824 15.2021C27.4118 15.2021 25.5059 15.9111 23.9941 17.0996C22.4824 15.9111 20.5765 15.2021 18.5059 15.2021C13.5926 15.2024 9.60961 19.1927 9.60938 24.1152C9.60938 29.038 13.5925 33.029 18.5059 33.0293C20.5765 33.0293 22.4824 32.3204 23.9941 31.1318C25.5059 32.3204 27.4118 33.0293 29.4824 33.0293C34.3959 33.0291 38.3789 29.038 38.3789 24.1152C38.3787 19.1926 34.3957 15.2024 29.4824 15.2021Z" fill="#00666E"/>
<path d="M23.9943 17.0977C21.9185 18.7295 20.5859 21.2652 20.5859 24.1138C20.5859 26.9624 21.9185 29.5 23.9943 31.1319C26.0702 29.5 27.4027 26.9624 27.4027 24.1138C27.4027 21.2652 26.0702 18.7295 23.9943 17.0977V17.0977Z" fill="#00666E"/>
</svg>
                </span>
                <span><?php echo esc_html__('Credit/Debit', 'tfp-dashboard'); ?></span>
            </button>
            <button type="button" class="tfp-checkout-method-card" data-method="paypal">
                <span class="tfp-checkout-method-icon tfp-checkout-method-icon--paypal">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="24" viewBox="0 0 20 24" fill="none">
<path d="M4.55691 19.8273L4.428 20.6448L4.1088 22.6712C4.0552 23.0136 4.3192 23.3224 4.6648 23.3224H8.5696C9.032 23.3224 9.4248 22.9864 9.4976 22.5304L9.536 22.332L10.2712 17.6664L10.3184 17.4104C10.3904 16.9528 10.784 16.6168 11.2464 16.6168H11.8304C15.6136 16.6168 18.5752 15.0808 19.4408 10.636C19.8024 8.7792 19.6152 7.2288 18.6584 6.1384C18.3688 5.8096 18.0096 5.5368 17.5896 5.3144C17.5672 5.4576 17.5416 5.604 17.5128 5.7544C17.4856 5.89417 17.4565 6.03142 17.4257 6.16619C17.3514 6.1197 17.2732 6.07472 17.1912 6.0312L16.7704 5.7928V5.252L16.78 5.1904C16.9128 4.3464 16.9104 3.6544 16.7744 3.0752C16.6448 2.5232 16.3768 2.0216 15.956 1.5416C15.0584 0.5184 13.3392 0 10.848 0H3.344C3.2832 0 3.2248 0.0216 3.1792 0.0608C3.1336 0.1 3.1024 0.1552 3.0928 0.2144L0 19.8248H4.4504L4.55691 19.8273Z" fill="white"/>
<path d="M16.9992 5.3552C16.848 5.3112 16.692 5.2712 16.532 5.2352C16.3712 5.2 16.2064 5.1688 16.0368 5.1416C15.4432 5.0456 14.7928 5 14.096 5H8.2144C8.0696 5 7.932 5.0328 7.8088 5.092C7.5376 5.2224 7.336 5.4792 7.2872 5.7936L6.036 13.7184L6 13.9496C6.0824 13.428 6.528 13.044 7.0568 13.044H9.2584C13.5824 13.044 16.968 11.288 17.9576 6.208C17.9872 6.0576 18.012 5.9112 18.0344 5.768C17.784 5.6352 17.5128 5.5216 17.2208 5.4248C17.1488 5.4008 17.0744 5.3776 16.9992 5.3552V5.3552Z" fill="#00666E"/>
</svg>
                </span>
                <span><?php echo esc_html__('PayPal', 'tfp-dashboard'); ?></span>
            </button>
            <button type="button" class="tfp-checkout-method-card" data-method="applepay">
                <span class="tfp-checkout-method-icon tfp-checkout-method-icon--apple">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="29" viewBox="0 0 24 29" fill="none">
<path d="M15.9074 4.56146C16.8238 3.37856 17.5184 1.70662 17.2672 0C15.7695 0.104083 14.0189 1.0623 12.9974 2.3113C12.0663 3.44299 11.301 5.12648 11.5999 6.76042C13.2372 6.81163 14.927 5.83028 15.9074 4.56146Z" fill="#ffffff"/>
<path d="M24 20.6122C19.2343 18.7998 18.4707 12.0245 23.1871 9.4241C21.7485 7.62 19.727 6.57422 17.8187 6.57422C15.2979 6.57422 14.2321 7.78026 12.4815 7.78026C10.6767 7.78026 9.30546 6.57752 7.12624 6.57752C4.98479 6.57752 2.70539 7.88599 1.26024 10.1229C-0.771178 13.2735 -0.424671 19.1963 2.86961 24.2418C4.04708 26.0476 5.62032 28.0764 7.67802 28.0945C9.50909 28.1127 10.0264 26.9199 12.5078 26.9067C14.9892 26.8918 15.4605 28.1094 17.2883 28.0896C19.3476 28.0731 21.0079 25.8246 22.1854 24.0188C23.0295 22.7252 23.3448 22.0726 24 20.6122Z" fill="#ffffff"/>
</svg>
                </span>
                <span><?php echo esc_html__('Apple Pay', 'tfp-dashboard'); ?></span>
            </button>
            <button type="button" class="tfp-checkout-method-card" data-method="googlepay">
                <span class="tfp-checkout-method-icon tfp-checkout-method-icon--google">
                    <svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 21 21" fill="none">
<path d="M10.3847 4.02133C12.3348 4.02133 13.6502 4.86489 14.4002 5.56978L17.3311 2.704C15.531 1.02844 13.1886 0 10.3847 0C6.323 0 2.81518 2.33422 1.10742 5.73156L4.46524 8.34311C5.30757 5.83556 7.63843 4.02133 10.3847 4.02133Z" fill="white"/>
<path d="M20.3544 10.6301C20.3544 9.77502 20.2851 9.15102 20.1351 8.50391H10.3848V12.3635H16.1081C15.9927 13.3226 15.3696 14.767 13.9849 15.7377L17.2619 18.2799C19.2235 16.4657 20.3544 13.7964 20.3544 10.6301Z" fill="white"/>
<path d="M4.47709 12.4558C4.25785 11.8087 4.13092 11.1154 4.13092 10.3989C4.13092 9.68247 4.25785 8.98914 4.46555 8.34202L1.10773 5.73047C0.403861 7.14025 0 8.72336 0 10.3989C0 12.0745 0.403861 13.6576 1.10773 15.0674L4.47709 12.4558Z" fill="white"/>
<path d="M10.3849 20.8001C13.1888 20.8001 15.5427 19.8757 17.262 18.281L13.985 15.7388C13.108 16.3513 11.9311 16.7788 10.3849 16.7788C7.63861 16.7788 5.30775 14.9646 4.47695 12.457L1.11914 15.0686C2.82689 18.4659 6.32318 20.8001 10.3849 20.8001Z" fill="white"/>
</svg>
                </span>
                <span><?php echo esc_html__('Google Pay', 'tfp-dashboard'); ?></span>
            </button>
        </div>

        <div class="tfp-checkout-payment-panel" id="tfp-checkout-payment-panel"></div>
        

        <div class="tfp-checkout-actions tfp-checkout-actions--split">
            <a href="<?php echo esc_url(add_query_arg('tfp_checkout_step', 'delivery', get_permalink(get_the_ID()))); ?>" class="tfp-checkout-link-button">
                <svg xmlns="http://www.w3.org/2000/svg" width="5" height="8" viewBox="0 0 5 8" fill="none">
                    <path d="M4.93994 0.94L1.88661 4L4.93994 7.06L3.99994 8L-5.88258e-05 4L3.99994 -4.10887e-08L4.93994 0.94Z" fill="currentColor"></path>
                </svg>
                <?php echo esc_html__('Back to Delivery', 'tfp-dashboard'); ?>
            </a>
            <button type="button" id="tfp-global-place-order" class="tfp-dash-btn tfp-dash-btn--primary tfp-checkout-place-order"><?php echo esc_html__('Place Order', 'tfp-dashboard'); ?><svg xmlns="http://www.w3.org/2000/svg" width="5" height="8" viewBox="0 0 5 8" fill="none">
                    <path d="M-4.10887e-08 7.06L3.05333 4L-3.08602e-07 0.94L0.94 -4.10887e-08L4.94 4L0.94 8L-4.10887e-08 7.06Z" fill="currentColor"></path>
                </svg></button>
        </div>
    </div>
    <?php
}

function tfp_checkout_render_wizard()
{
    if (!function_exists('WC') || !WC()->cart) {
        echo '<div class="tfp-checkout-empty-state">' . esc_html__('WooCommerce must be active to use this checkout flow.', 'tfp-dashboard') . '</div>';
        return;
    }

    $step = tfp_checkout_current_step();
    if (!tfp_checkout_step_is_allowed($step)) {
        wp_safe_redirect(add_query_arg('tfp_checkout_step', 'cart', get_permalink(get_the_ID())));
        exit;
    }

    $steps = [
        'cart' => __('Cart', 'tfp-dashboard'),
        'contact' => __('Contact & Shipping', 'tfp-dashboard'),
        'delivery' => __('Delivery', 'tfp-dashboard'),
        'payment' => __('Payment', 'tfp-dashboard'),
    ];

    $step_titles = [
        'cart' => __('Your Cart', 'tfp-dashboard'),
        'contact' => __('Contact Information', 'tfp-dashboard'),
        'delivery' => __('Delivery Options', 'tfp-dashboard'),
        'payment' => __('Payment Details', 'tfp-dashboard'),
    ];

    $step_subtitles = [
        'cart' => __('Confirm the contents of your shopping cart. All sales are final.', 'tfp-dashboard'),
        'contact' => __('Please provide your contact and shipping information.', 'tfp-dashboard'),
        'delivery' => __('Select your preferred delivery method.', 'tfp-dashboard'),
        'payment' => __('Choose your payment method and complete your order.', 'tfp-dashboard'),
    ];
    ?>
    <div class="tfp-checkout-page-wrap">
        <div class="tfp-checkout-step-tabs" aria-label="Checkout progress">
            <?php foreach ($steps as $name => $label) : ?>
                <?php $is_active = $step === $name; ?>
                <?php $is_valid = tfp_checkout_step_is_allowed($name); ?>
                <a href="<?php echo esc_url(add_query_arg('tfp_checkout_step', $name, get_permalink(get_the_ID()))); ?>" class="tfp-checkout-step-tab<?php echo $is_active ? ' is-active' : ''; ?><?php echo $is_valid ? '' : ' is-disabled'; ?>" data-step-link="<?php echo esc_attr($name); ?>" data-step-title="<?php echo esc_attr($step_titles[$name]); ?>" data-step-subtitle="<?php echo esc_attr($step_subtitles[$name]); ?>">
                    <?php echo esc_html($label); ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="tfp-checkout-title-header">
            <h1 class="tfp-checkout-page-title"><?php echo esc_html($step_titles[$step]); ?></h1>
            <p class="tfp-checkout-subtitle"><?php echo esc_html($step_subtitles[$step]); ?></p>
        </div>

        <div class="tfp-checkout-layout">
            <div class="tfp-checkout-main-column">
                <?php tfp_checkout_render_cart_step(); ?>
                <?php tfp_checkout_render_contact_step(); ?>
                <?php tfp_checkout_render_delivery_step(); ?>
                <?php tfp_checkout_render_payment_step(); ?>
            </div>
            <div class="tfp-checkout-sidebar-column">
                <?php tfp_checkout_render_order_summary(); ?>
            </div>
        </div>
    </div>
    <?php
}
