<?php
if (!defined('ABSPATH')) exit;

/**
 * WooCommerce My Account Page Customizations.
 * Rendered via shortcode [tfp_my_account] to avoid WooCommerce wrapper conflicts.
 */

add_shortcode('tfp_my_account', 'tfp_auth_render_custom_my_account_shortcode');

/**
 * Determine if the user is a Disciple (has a paid/active program) or just a Reader.
 */
function tfp_auth_is_disciple($user_id) {
    if (function_exists('tfp_billing_user_has_paid')) {
        return tfp_billing_user_has_paid($user_id);
    }
    
    // Fallback: If they have a program choice, assume Disciple for now
    $program_id = (int) get_user_meta($user_id, 'tfp_program_choice', true);
    return $program_id > 0;
}

/**
 * Render the unified dashboard shortcode.
 */
function tfp_auth_render_custom_my_account_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>' . esc_html__('Please log in to view your account.', 'tfp-authentication') . '</p>';
    }

    $user_id = get_current_user_id();
    $user = get_user_by('id', $user_id);
    if (!$user) return '';

    $is_disciple = tfp_auth_is_disciple($user_id);
    $badge_text = $is_disciple ? __('Disciple', 'tfp-authentication') : __('Reader', 'tfp-authentication');
    $badge_class = $is_disciple ? 'tfp-account-badge--disciple' : 'tfp-account-badge--reader';
    
    // Get user info
    $first_name = get_user_meta($user_id, 'first_name', true);
    $last_name = get_user_meta($user_id, 'last_name', true);
    $full_name = trim($first_name . ' ' . $last_name) ?: $user->display_name;
    
    // Personal extra fields
    $dob      = get_user_meta($user_id, 'tfp_date_of_birth', true);
    $timezone = get_user_meta($user_id, 'tfp_timezone', true);
    $address  = get_user_meta($user_id, 'tfp_address', true);

    // Shipping Info
    $shipping_first    = get_user_meta($user_id, 'shipping_first_name', true);
    $shipping_last     = get_user_meta($user_id, 'shipping_last_name', true);
    $shipping_name     = trim($shipping_first . ' ' . $shipping_last) ?: '—';
    $shipping_addr     = get_user_meta($user_id, 'shipping_address_1', true);
    $shipping_city     = get_user_meta($user_id, 'shipping_city', true);
    $shipping_state    = get_user_meta($user_id, 'shipping_state', true);
    $shipping_postcode = get_user_meta($user_id, 'shipping_postcode', true);
    $shipping_country  = get_user_meta($user_id, 'shipping_country', true);
    
    $registered = date('M Y', strtotime($user->user_registered));
    
    $customer = new WC_Customer($user_id);
    $order_count = $customer->get_order_count();
    
    $program_title = function_exists('tfp_get_current_program') ? tfp_get_current_program($user) : '';
    
    // Enqueue CSS
    wp_enqueue_style('tfp-auth-account', plugins_url('../../assets/css/account.css', __FILE__), [], TFP_AUTH_VERSION);

    ob_start();
    ?>
    <div class="tfp-account-wrapper">
        <h1 class="tfp-account-page-title"><?php printf(esc_html__('%s, Your Account', 'tfp-authentication'), esc_html(strtok($first_name ?: $user->display_name, ' '))); ?></h1>
        <p class="tfp-account-page-subtitle"><?php esc_html_e('Manage your orders, shipping address, and account details.', 'tfp-authentication'); ?></p>

        <div class="tfp-account-header-card">
            <div class="tfp-account-header-avatar">
                <?php 
                $custom_avatar_id = get_user_meta($user_id, 'tfp_custom_avatar_id', true);
                if ($custom_avatar_id && wp_attachment_is_image($custom_avatar_id)) {
                    echo wp_get_attachment_image($custom_avatar_id, [120, 120], false, ['class' => 'avatar avatar-120 photo']);
                } else {
                    echo get_avatar($user_id, 120);
                }
                ?>
            </div>
            <div class="tfp-account-header-info">
                <h2><?php echo esc_html($full_name); ?></h2>
                <div class="tfp-account-header-meta">
                    <span class="tfp-meta-item">
                        <svg xmlns="http://www.w3.org/2000/svg" width="17" height="14" viewBox="0 0 17 14" fill="none"><path d="M1.83333 1.83333L6.92303 5.67689L6.92473 5.6783C7.48987 6.09274 7.77261 6.30008 8.0823 6.38018C8.35602 6.45098 8.64376 6.45098 8.91748 6.38018C9.22744 6.30001 9.511 6.09206 10.0771 5.67689C10.0771 5.67689 13.3417 3.17162 15.1667 1.83333M1 10.0002V3.66683C1 2.73341 1 2.26635 1.18166 1.90983C1.34144 1.59623 1.59623 1.34144 1.90983 1.18166C2.26635 1 2.73341 1 3.66683 1H13.3335C14.2669 1 14.733 1 15.0895 1.18166C15.4031 1.34144 15.6587 1.59623 15.8185 1.90983C16 2.266 16 2.7325 16 3.66409V10.003C16 10.9346 16 11.4004 15.8185 11.7566C15.6587 12.0702 15.4031 12.3254 15.0895 12.4852C14.7333 12.6667 14.2675 12.6667 13.3359 12.6667H3.66409C2.73249 12.6667 2.266 12.6667 1.90983 12.4852C1.59623 12.3254 1.34144 12.0702 1.18166 11.7566C1 11.4 1 10.9336 1 10.0002Z" stroke="#151411" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <?php echo esc_html($user->user_email); ?>
                    </span>
                    <span class="tfp-meta-item">
                        <svg width="17" height="16" viewBox="0 0 17 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M4.84115 4.33333H3.82227C2.73925 4.33333 2.19819 4.33333 1.81299 4.55428C1.47496 4.74818 1.21512 5.05446 1.07944 5.41976C0.924917 5.83578 1.01386 6.36944 1.19169 7.43639L1.19206 7.4384L1.96984 12.1051C2.10178 12.8967 2.16818 13.2927 2.3657 13.5897C2.53982 13.8516 2.78423 14.0586 3.07113 14.1873C3.39659 14.3333 3.7977 14.3333 4.60026 14.3333H11.7489C12.5515 14.3333 12.9523 14.3333 13.2778 14.1873C13.5647 14.0586 13.8093 13.8515 13.9834 13.5897C14.1809 13.2927 14.247 12.8967 14.3789 12.1051L15.1567 7.4384L15.1574 7.43514C15.3351 6.36903 15.424 5.83562 15.2696 5.41976C15.1339 5.05446 14.8747 4.74818 14.5366 4.55428C14.1514 4.33333 13.6095 4.33333 12.5265 4.33333H11.5078M4.84115 4.33333H11.5078M4.84115 4.33333C4.84115 2.49238 6.33353 1 8.17448 1C10.0154 1 11.5078 2.49238 11.5078 4.33333" stroke="#151411" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <?php printf(esc_html(_n('%d order placed', '%d orders placed', $order_count, 'tfp-authentication')), $order_count); ?>
                    </span>
                    <span class="tfp-meta-item">
                        <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 17 17" fill="none">
                            <path d="M8.5 4.33333V8.5H12.6667M8.5 16C4.35786 16 1 12.6421 1 8.5C1 4.35786 4.35786 1 8.5 1C12.6421 1 16 4.35786 16 8.5C16 12.6421 12.6421 16 8.5 16Z" stroke="#151411" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <?php printf(esc_html__('Member since %s', 'tfp-authentication'), $registered); ?>
                    </span>
                </div>
                <div class="tfp-myaccount-badge <?php echo esc_attr($badge_class); ?>">
                    <?php echo esc_html($badge_text); ?>
                </div>
            </div>
        </div>

        <?php
        // Every logged-in student gets an entry point into the program
        // dashboard — not just paid Disciples. Only rendered when the dashboard
        // plugin/page actually resolves.
        $dashboard_home_url = function_exists('tfp_dashboard_get_url') ? tfp_dashboard_get_url('tfp-dashboard-home') : '';
        if (!empty($dashboard_home_url) && $dashboard_home_url !== '#') :
            $has_program  = (!empty($program_title) && $program_title !== 'No Program Selected');
            $banner_title = $has_program ? $program_title : __('Your Program Dashboard', 'tfp-authentication');
            $banner_sub   = $is_disciple
                ? __('Active Enrollment', 'tfp-authentication')
                : __('Continue your registration', 'tfp-authentication');
        ?>
            <div class="tfp-account-disciple-banner">
                <div class="tfp-adb-text">
                    <h5><?php echo esc_html($banner_title); ?></h5>
                    <span><?php echo esc_html($banner_sub); ?></span>
                </div>
                <a href="<?php echo esc_url($dashboard_home_url); ?>" class="tfp-adb-btn">
                    <?php esc_html_e('Enter Program Dashboard', 'tfp-authentication'); ?>
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="13" viewBox="0 0 28 13" fill="none">
                        <path d="M21.209 1.25L26.2504 6.30833L21.209 11.3667" stroke="currentColor" stroke-width="2.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M1.25 6.30859L26.1666 6.3086" stroke="currentColor" stroke-width="2.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </a>
            </div>
        <?php endif; ?>

        <div class="tfp-account-grid">
            
            <div class="tfp-account-column">
                <!-- Personal Information -->
                <div class="tfp-account-card">
                    <div class="tfp-ac-header">
                        <h5><?php esc_html_e('Personal Information', 'tfp-authentication'); ?></h5>
                        <a href="<?php echo esc_url(function_exists('tfp_dashboard_get_url') ? tfp_dashboard_get_url('tfp-dashboard-update-profile') : wc_get_account_endpoint_url('edit-account')); ?>" class="tfp-ac-edit-btn">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 18 18" fill="none">
                                <path d="M9 4.58605L1 12.5861V16.5861H17M1 16.5861L5 16.586L13 8.58604M9 4.58605L11.8686 1.7174L11.8704 1.7157C12.2652 1.32082 12.463 1.12303 12.691 1.04894C12.8919 0.983686 13.1082 0.983686 13.3091 1.04894C13.5369 1.12297 13.7345 1.32054 14.1288 1.71486L15.8686 3.45466C16.2646 3.85067 16.4627 4.04878 16.5369 4.2771C16.6022 4.47795 16.6021 4.69429 16.5369 4.89513C16.4628 5.1233 16.265 5.3211 15.8695 5.71655L15.8686 5.7174L13 8.58604M9 4.58605L13 8.58604" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg> Edit
                        </a>
                    </div>
                    <div class="tfp-ac-body">
                        <div class="tfp-ac-row"><span><?php esc_html_e('Full Name', 'tfp-authentication'); ?></span><p><?php echo esc_html($full_name); ?></p></div>
                        <div class="tfp-ac-row"><span><?php esc_html_e('Email', 'tfp-authentication'); ?></span><p><?php echo esc_html($user->user_email); ?></p></div>
                        <div class="tfp-ac-row"><span><?php esc_html_e('Phone Number', 'tfp-authentication'); ?></span><p><?php echo esc_html(get_user_meta($user_id, 'billing_phone', true) ?: '—'); ?></p></div>
                        <div class="tfp-ac-row"><span><?php esc_html_e('Date of Birth', 'tfp-authentication'); ?></span><p><?php echo esc_html($dob ?: '—'); ?></p></div>
                        <div class="tfp-ac-row"><span><?php esc_html_e('Address', 'tfp-authentication'); ?></span><p><?php echo esc_html($address ?: '—'); ?></p></div>
                        <div class="tfp-ac-row"><span><?php esc_html_e('Timezone', 'tfp-authentication'); ?></span><p><?php echo esc_html($timezone ?: '—'); ?></p></div>
                    </div>
                </div>

                <!-- Billing Information -->
                <div class="tfp-account-card">
                    <div class="tfp-ac-header">
                        <div>
                            <h5><?php esc_html_e('Billing Information', 'tfp-authentication'); ?></h5>
                            <p class="tfp-ac-sub"><?php esc_html_e('Your saved payment method from enrollment', 'tfp-authentication'); ?></p>
                        </div>
                        <a href="<?php echo esc_url(wc_get_account_endpoint_url('payment-details')); ?>" class="tfp-ac-edit-btn">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 18 18" fill="none">
                                <path d="M9 4.58605L1 12.5861V16.5861H17M1 16.5861L5 16.586L13 8.58604M9 4.58605L11.8686 1.7174L11.8704 1.7157C12.2652 1.32082 12.463 1.12303 12.691 1.04894C12.8919 0.983686 13.1082 0.983686 13.3091 1.04894C13.5369 1.12297 13.7345 1.32054 14.1288 1.71486L15.8686 3.45466C16.2646 3.85067 16.4627 4.04878 16.5369 4.2771C16.6022 4.47795 16.6021 4.69429 16.5369 4.89513C16.4628 5.1233 16.265 5.3211 15.8695 5.71655L15.8686 5.7174L13 8.58604M9 4.58605L13 8.58604" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg> Edit
                        </a>
                    </div>
                    <div class="tfp-ac-body">
                        <div class="tfp-ac-row"><span><?php esc_html_e('Card on File', 'tfp-authentication'); ?></span><p><?php esc_html_e('Manage in Edit', 'tfp-authentication'); ?></p></div>
                        <div class="tfp-ac-row"><span><?php esc_html_e('Billing Email', 'tfp-authentication'); ?></span><p><?php echo esc_html(get_user_meta($user_id, 'billing_email', true) ?: $user->user_email); ?></p></div>
                    </div>
                </div>
            </div>

            <div class="tfp-account-column">
                <!-- Shipping Address -->
                <div class="tfp-account-card">
                    <div class="tfp-ac-header">
                        <div>
                            <h5><?php esc_html_e('Shipping Address', 'tfp-authentication'); ?></h5>
                            <p class="tfp-ac-sub"><?php esc_html_e('Where your physical orders are sent', 'tfp-authentication'); ?></p>
                        </div>
                        <a href="<?php echo esc_url(function_exists('tfp_dashboard_get_url') ? tfp_dashboard_get_url('tfp-dashboard-update-profile') : wc_get_account_endpoint_url('edit-address') . 'shipping/'); ?>" class="tfp-ac-edit-btn">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 18 18" fill="none">
                                <path d="M9 4.58605L1 12.5861V16.5861H17M1 16.5861L5 16.586L13 8.58604M9 4.58605L11.8686 1.7174L11.8704 1.7157C12.2652 1.32082 12.463 1.12303 12.691 1.04894C12.8919 0.983686 13.1082 0.983686 13.3091 1.04894C13.5369 1.12297 13.7345 1.32054 14.1288 1.71486L15.8686 3.45466C16.2646 3.85067 16.4627 4.04878 16.5369 4.2771C16.6022 4.47795 16.6021 4.69429 16.5369 4.89513C16.4628 5.1233 16.265 5.3211 15.8695 5.71655L15.8686 5.7174L13 8.58604M9 4.58605L13 8.58604" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg> Edit
                        </a>
                    </div>
                    <div class="tfp-ac-body">
                        <div class="tfp-ac-row"><span><?php esc_html_e('Full Name', 'tfp-authentication'); ?></span><p><?php echo esc_html($shipping_name); ?></p></div>
                        <div class="tfp-ac-row"><span><?php esc_html_e('Address', 'tfp-authentication'); ?></span><p><?php echo esc_html($shipping_addr ?: '—'); ?></p></div>
                        <div class="tfp-ac-row"><span><?php esc_html_e('City', 'tfp-authentication'); ?></span><p><?php
                            $city_display = array_filter([$shipping_city, $shipping_state]);
                            echo esc_html($city_display ? implode(', ', $city_display) : '—');
                        ?></p></div>
                        <div class="tfp-ac-row"><span><?php esc_html_e('ZIP', 'tfp-authentication'); ?></span><p><?php echo esc_html($shipping_postcode ?: '—'); ?></p></div>
                        <div class="tfp-ac-row"><span><?php esc_html_e('Country', 'tfp-authentication'); ?></span><p><?php echo esc_html($shipping_country ?: '—'); ?></p></div>
                    </div>
                </div>
                
                <!-- Notification Preferences -->
                <div class="tfp-account-card">
                    <div class="tfp-ac-header">
                        <div>
                            <h5><?php esc_html_e('Notification Preferences', 'tfp-authentication'); ?></h5>
                            <p class="tfp-ac-sub"><?php esc_html_e('How we reach you outside the Shop', 'tfp-authentication'); ?></p>
                        </div>
                        <a href="#" class="tfp-ac-edit-btn tfp-edit-notifications-btn">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 18 18" fill="none">
                                <path d="M9 4.58605L1 12.5861V16.5861H17M1 16.5861L5 16.586L13 8.58604M9 4.58605L11.8686 1.7174L11.8704 1.7157C12.2652 1.32082 12.463 1.12303 12.691 1.04894C12.8919 0.983686 13.1082 0.983686 13.3091 1.04894C13.5369 1.12297 13.7345 1.32054 14.1288 1.71486L15.8686 3.45466C16.2646 3.85067 16.4627 4.04878 16.5369 4.2771C16.6022 4.47795 16.6021 4.69429 16.5369 4.89513C16.4628 5.1233 16.265 5.3211 15.8695 5.71655L15.8686 5.7174L13 8.58604M9 4.58605L13 8.58604" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg> <span>Edit</span>
                        </a>
                    </div>
                    <div class="tfp-ac-body">
                        <div class="tfp-ac-row tfp-ac-toggles"><span><?php esc_html_e('Order Updates', 'tfp-authentication'); ?></span>
                            <div class="tfp-checkboxes">
                                <label><input type="checkbox" class="tfp-notification-checkbox" name="order_updates_email" checked disabled> Email</label>
                            </div>
                        </div>
                        <div class="tfp-ac-row tfp-ac-toggles"><span><?php esc_html_e('Shipping Updates', 'tfp-authentication'); ?></span>
                            <div class="tfp-checkboxes">
                                <label><input type="checkbox" class="tfp-notification-checkbox" name="shipping_updates_email" checked disabled> Email</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Order History -->
            <div class="tfp-account-card tfp-ac-full">
                <div class="tfp-ac-header">
                    <h5><?php esc_html_e('Order History', 'tfp-authentication'); ?></h5>
                    <span class="tfp-ac-meta"><?php printf(esc_html(_n('%d order', '%d orders', $order_count, 'tfp-authentication')), $order_count); ?></span>
                </div>
                <div class="tfp-ac-body tfp-ac-orders-list">
                    <?php
                    $customer_orders = wc_get_orders([
                        'customer' => $user_id,
                        'limit' => 10,
                    ]);
                    
                    if (empty($customer_orders)) {
                        echo '<p>' . esc_html__('No orders found.', 'tfp-authentication') . '</p>';
                    } else {
                        foreach ($customer_orders as $order) {
                            $items = $order->get_items();
                            $first_item = reset($items);
                            $product_name = $first_item ? $first_item->get_name() : __('Order', 'tfp-authentication');
                            $product_id = $first_item ? $first_item->get_product_id() : 0;
                            $image = $product_id ? get_the_post_thumbnail_url($product_id, 'thumbnail') : '';
                            ?>
                            <div class="tfp-ac-order-item">
                                <?php if ($image) : ?>
                                    <img src="<?php echo esc_url($image); ?>" alt="" class="tfp-ac-order-img">
                                <?php else : ?>
                                    <div class="tfp-ac-order-img-placeholder"></div>
                                <?php endif; ?>
                                
                                <div class="tfp-ac-order-info">
                                    <h4><?php echo esc_html($product_name); ?></h4>
                                    <span class="tfp-ac-order-meta">
                                        <?php printf(esc_html__('Order #%s &middot; %s', 'tfp-authentication'), $order->get_order_number(), wc_format_datetime($order->get_date_created())); ?>
                                    </span>
                                </div>
                                
                                <div class="tfp-ac-order-status">
                                    <span class="tfp-status-badge tfp-status-<?php echo esc_attr($order->get_status()); ?>">
                                        <?php echo esc_html(wc_get_order_status_name($order->get_status())); ?>
                                    </span>
                                </div>
                                
                                <div class="tfp-ac-order-total">
                                    <?php echo wp_kses_post($order->get_formatted_order_total()); ?>
                                </div>
                                
                                <div class="tfp-ac-order-action">
                                    <button class="tfp-auth-btn tfp-auth-btn--primary tfp-view-order-btn" data-order-id="<?php echo esc_attr($order->get_id()); ?>">
                                        <?php esc_html_e('View', 'tfp-authentication'); ?>
                                    </button>
                                </div>
                            </div>
                            <?php
                        }
                    }
                    ?>
                </div>
            </div>
        </div>

        <!-- Order Slide-over Panel -->
        <div class="tfp-slide-over-backdrop" id="tfp-order-slide-backdrop"></div>
        <div class="tfp-order-slide-over" id="tfp-order-slide-over">
            <div class="tfp-slide-over-content" id="tfp-order-slide-content">
                <!-- Content will be injected here via AJAX -->
            </div>
            <div class="tfp-slide-over-loader" id="tfp-order-slide-loader" style="display: none;">
                <div class="tfp-spinner"></div>
            </div>
        </div>

        <?php if (!$is_disciple) : ?>
            <div class="tfp-account-reader-upsell">
                <div class="tfp-aru-text">
                    <h5><?php esc_html_e('Ready to go deeper?', 'tfp-authentication'); ?></h5>
                    <p><?php esc_html_e('Apply for the 58-Week Discipleship Journey &mdash; cohorts form quarterly.', 'tfp-authentication'); ?></p>
                </div>
                <a href="<?php echo esc_url(site_url('/select-program')); ?>" class="tfp-auth-btn tfp-auth-btn--primary">
                    <?php esc_html_e('Register Now', 'tfp-authentication'); ?>
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="13" viewBox="0 0 28 13" fill="none">
                        <path d="M21.209 1.25L26.2504 6.30833L21.209 11.3667" stroke="currentColor" stroke-width="2.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M1.25 6.30859L26.1666 6.3086" stroke="currentColor" stroke-width="2.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </a>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
