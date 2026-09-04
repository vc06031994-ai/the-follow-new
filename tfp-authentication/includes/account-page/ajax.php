<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_tfp_get_order_details', 'tfp_ajax_get_order_details');
function tfp_ajax_get_order_details() {
    check_ajax_referer('tfp_account_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    if (!$order_id) {
        wp_send_json_error(['message' => 'Invalid order ID']);
    }

    $order = wc_get_order($order_id);
    if (!$order || $order->get_customer_id() !== get_current_user_id()) {
        wp_send_json_error(['message' => 'Order not found or access denied.']);
    }

    // Prepare data
    $items = $order->get_items();
    $first_item = reset($items);
    $product_name = $first_item ? $first_item->get_name() : __('Order', 'tfp-authentication');
    $product_id = $first_item ? $first_item->get_product_id() : 0;
    
    // Get product type categories/tags if possible
    $type = 'Book';
    if ($product_id) {
        $product = wc_get_product($product_id);
        if ($product) {
            if ($product->is_virtual() || $product->is_downloadable()) {
                $type = 'Digital';
            }
        }
    }

    $image_url = $product_id ? get_the_post_thumbnail_url($product_id, 'thumbnail') : '';
    
    $order_number = $order->get_order_number();
    $status_name = wc_get_order_status_name($order->get_status());
    $order_date = wc_format_datetime($order->get_date_created(), 'M j, Y');

    // Shipping address
    $shipping_first_name = $order->get_shipping_first_name();
    $shipping_last_name = $order->get_shipping_last_name();
    $shipping_name = trim($shipping_first_name . ' ' . $shipping_last_name);
    if (!$shipping_name) {
        $shipping_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
    }

    $address_1 = $order->get_shipping_address_1() ?: $order->get_billing_address_1();
    $city = $order->get_shipping_city() ?: $order->get_billing_city();
    $state = $order->get_shipping_state() ?: $order->get_billing_state();
    $postcode = $order->get_shipping_postcode() ?: $order->get_billing_postcode();
    
    $city_state_zip = trim(implode(', ', array_filter([$city, $state])));
    if ($postcode) {
        $city_state_zip .= ($city_state_zip ? ' ' : '') . $postcode;
    }

    // Totals
    $subtotal = wp_kses_post($order->get_subtotal_to_display());
    $shipping_total = $order->get_shipping_total() > 0 ? wp_strip_all_tags(wc_price($order->get_shipping_total())) : 'Free';
    $total = wp_kses_post($order->get_formatted_order_total());

    ob_start();
    ?>
    <div class="tfp-so-header-top">
        <div class="tfp-so-header-content">
            <h6 class="tfp-so-title"><?php echo esc_html($product_name); ?></h6>
            <div class="tfp-so-order-meta">
                Order #<?php echo esc_html($order_number); ?>
            </div>
        </div>
        <button class="tfp-so-close" aria-label="Close">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        </button>
    </div>
    
    
    <div class="tfp-so-status-row">
        <span class="tfp-status-badge tfp-status-<?php echo esc_attr($order->get_status()); ?>">
            <?php echo esc_html($status_name); ?>
        </span>
        <span class="tfp-so-date">Order Placed <?php echo esc_html($order_date); ?></span>
    </div>

    <div class="tfp-so-box tfp-so-product-box">
        <?php if ($image_url): ?>
            <img src="<?php echo esc_url($image_url); ?>" alt="" class="tfp-so-product-img">
        <?php else: ?>
            <div class="tfp-so-product-img-placeholder"></div>
        <?php endif; ?>
        <div class="tfp-so-product-info">
            <h4><?php echo esc_html($product_name); ?></h4>
            <div class="tfp-ac-order-meta">
                Order #<?php echo esc_html($order_number); ?> &middot; <?php echo esc_html($order_date); ?> &middot; <?php echo esc_html($type); ?>
            </div>
            <div class="tfp-so-product-price">
                <?php echo wp_kses_post(wc_price($first_item ? $first_item->get_total() : $order->get_total())); ?>
            </div>
        </div>
    </div>

    <?php if ($type !== 'Digital'): ?>
        <div class="tfp-so-box">
            <h6 class="tfp-so-box-title">Shipping & Tracking</h6>
            <div class="tfp-so-row">
                <span>Status</span>
                <p><?php echo esc_html($status_name); ?></p>
            </div>
            <div class="tfp-so-row">
                <span>Estimated Delivery</span>
                <p>Processing</p>
            </div>
            <div class="tfp-so-row">
                <span>Tracking Number</span>
                <p>Not available yet</p>
            </div>
            <div class="tfp-so-btn-wrap">
                <a href="#" class="tfp-so-btn-full">Track Package 
                    <svg width="28" height="13" viewBox="0 0 28 13" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M21.209 1.25L26.2504 6.30833L21.209 11.3667" stroke="currentColor" stroke-width="2.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M1.25 6.30859L26.1666 6.3086" stroke="currentColor" stroke-width="2.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                </a>
            </div>
        </div>

        <div class="tfp-so-box">
            <h6 class="tfp-so-box-title">Shipped To</h6>
            <div class="tfp-so-row-simple">
                <p><?php echo esc_html($shipping_name); ?></p>
            </div>
            <div class="tfp-so-row-simple">
                <p><?php echo esc_html($address_1 ?: '—'); ?></p>
            </div>
            <div class="tfp-so-row-simple">
                <p><?php echo esc_html($city_state_zip ?: '—'); ?></p>
            </div>
        </div>
    <?php endif; ?>

    <div class="tfp-so-box">
        <h6 class="tfp-so-box-title">Order Summary</h6>
        <div class="tfp-so-row">
            <span>Subtotal</span>
            <p><?php echo wp_kses_post($subtotal); ?></p>
        </div>
        <div class="tfp-so-row">
            <span>Shipping</span>
            <p><?php echo wp_kses_post($shipping_total); ?></p>
        </div>
        <div class="tfp-so-row tfp-so-total-row">
            <span>Total</span>
            <p><?php echo wp_kses_post($total); ?></p>
        </div>
    </div>

    <div class="tfp-so-footer">
        <span class="tfp-so-footer-text">All Sale Final</span>
        <a href="#" class="tfp-auth-btn tfp-auth-btn--primary">Need Help ?</a>
    </div>
    <?php
    $html = ob_get_clean();

    wp_send_json_success(['html' => $html]);
}
