<?php
if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('tfp_order_number', function ($atts) {
    $atts = shortcode_atts([
        'order_id' => 0,
        'order_key' => '',
    ], $atts, 'tfp_order_number');

    $order_id = absint($atts['order_id']);
    $order_key = sanitize_text_field($atts['order_key']);
    $order = $order_id ? wc_get_order($order_id) : false;

    if (!$order || $order->get_order_key() !== $order_key) {
        return '';
    }

    return '<span class="tfp-order-number">' . esc_html($order->get_order_number()) . '</span>';
});

add_shortcode('tfp_order_details', function ($atts) {
    $atts = shortcode_atts([
        'order_id' => isset($_GET['order_id']) ? absint($_GET['order_id']) : 0,
        'order_key' => isset($_GET['order_key']) ? sanitize_text_field(wp_unslash($_GET['order_key'])) : '',
    ], $atts, 'tfp_order_details');

    // Enqueue the specific stylesheet for this shortcode
    wp_enqueue_style('tfp-order-details', TFP_DASH_URL . 'assets/css/order-details.css', [], TFP_DASH_VERSION);

    if (!$atts['order_id'] || !$atts['order_key']) {
        return '<p>No order specified.</p>';
    }

    $order_id  = absint($atts['order_id']);
    $order_key = sanitize_text_field($atts['order_key']);

    $order = wc_get_order($order_id);

    if (!$order || $order->get_order_key() !== $order_key) {
        return '<p>Invalid order or permission denied.</p>';
    }

    ob_start();
    ?>
    <div class="tfp-order-details-wrapper">
        <div class="tfp-order-details-header">
            <h3>Order Details</h3>
        </div>

        <div class="tfp-order-items-list">
            <?php foreach ($order->get_items() as $item_id => $item) : 
                $product = $item->get_product();
                $image_url = '';
                if ($product) {
                    $image_id = $product->get_image_id();
                    $image_url = wp_get_attachment_image_url($image_id, 'thumbnail');
                }
                
                $short_desc = '';
                if ($product && $product->get_short_description()) {
                    $short_desc = wp_trim_words(wp_kses_post($product->get_short_description()), 15, '...');
                }
                
                $qty = $item->get_quantity();
                
                // Get formatted meta data
                $meta_data = $item->get_formatted_meta_data('');
                $meta_strings = [];
                if (!empty($meta_data)) {
                    foreach ($meta_data as $meta_id => $meta) {
                        $meta_strings[] = wp_kses_post(strip_tags($meta->display_value)); // E.g., "Physical Book"
                    }
                }
                
                $meta_html = '';
                if (!empty($meta_strings)) {
                    $meta_html = implode(' / ', $meta_strings) . ' · Qty: ' . $qty;
                } else {
                    $meta_html = 'Physical Book · Qty: ' . $qty;
                }
            ?>
                <div class="tfp-order-item">
                    <div class="tfp-order-item-image">
                        <?php if ($image_url) : ?>
                            <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($item->get_name()); ?>" />
                        <?php else : ?>
                            <div class="tfp-order-item-placeholder"></div>
                        <?php endif; ?>
                    </div>
                    <div class="tfp-order-item-info">
                        <h4><?php echo esc_html($item->get_name()); ?></h4>
                        <?php if ($short_desc) : ?>
                            <div class="tfp-order-item-desc"><?php echo strip_tags($short_desc); ?></div>
                        <?php endif; ?>
                        <p class="tfp-order-item-meta"><?php echo wp_kses_post($meta_html); ?></p>
                    </div>
                    <div class="tfp-order-item-price">
                        <?php echo wc_price($order->get_item_total($item, false, true)); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="tfp-order-meta-box">
            <div class="tfp-order-meta-item">
                <span class="tfp-order-meta-label">Order Number</span>
                <span class="tfp-order-meta-value"><?php echo esc_html($order->get_order_number()); ?></span>
            </div>
            <div class="tfp-order-meta-item">
                <span class="tfp-order-meta-label">Date</span>
                <span class="tfp-order-meta-value"><?php echo esc_html(wc_format_datetime($order->get_date_created(), 'F j, Y')); ?></span>
            </div>
        </div>

        <div class="tfp-order-totals">
            <div class="tfp-order-total-row">
                <span>Subtotal</span>
                <span><?php echo wc_price($order->get_subtotal()); ?></span>
            </div>
            <div class="tfp-order-total-row">
                <span>Shipping</span>
                <span><?php echo wc_price($order->get_total_shipping()); ?></span>
            </div>
            <div class="tfp-order-total-row">
                <span>Tax</span>
                <span><?php echo wc_price($order->get_total_tax()); ?></span>
            </div>
            <div class="tfp-order-total-row tfp-order-grand-total">
                <span>Order Total</span>
                <span><?php echo wc_price($order->get_total()); ?></span>
            </div>
        </div>

        <div class="tfp-order-footer-note">
            Please Note: Because each item is made to order, all sales are final. We cannot accept returns or exchanges for change of mind, incorrect size selection, or other buyer-related reasons once processing begins.If your order arrives damaged, defective, or misprinted, please contact us within 30 days of delivery so we can review the issue and help make it right.
        </div>
    </div>
    <?php
    return ob_get_clean();
});
