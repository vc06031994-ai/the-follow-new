<?php
if (!defined('ABSPATH')) exit;

function tfp_profile_check_request()
{
    if (!is_user_logged_in()) {
        wp_send_json(['success' => false, 'message' => __('You must be logged in.', 'tfp-dashboard')], 403);
    }

    if (!isset($_POST['tfp_profile_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tfp_profile_nonce'])), 'tfp_profile_nonce')) {
        wp_send_json(['success' => false, 'message' => __('Security check failed.', 'tfp-dashboard')], 403);
    }
}

/**
 * Handles both profile sections. A hidden `section` field tells us which
 * one was submitted, so editing Connection Details doesn't require
 * re-submitting (or accidentally blanking) Personal Information fields,
 * and vice versa.
 */
add_action('wp_ajax_tfp_profile_update', function () {
    tfp_profile_check_request();

    $user_id = get_current_user_id();
    $section = isset($_POST['section']) ? sanitize_key(wp_unslash($_POST['section'])) : 'personal';

    if ($section === 'connection') {
        tfp_profile_save_connection_details($user_id);
        return;
    }

    tfp_profile_save_personal_information($user_id);
});

function tfp_profile_save_personal_information($user_id)
{
    $first_name = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '';
    $last_name  = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '';
    $email      = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
    $phone      = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
    $address    = isset($_POST['address']) ? sanitize_text_field(wp_unslash($_POST['address'])) : '';
    $dob        = isset($_POST['date_of_birth']) ? sanitize_text_field(wp_unslash($_POST['date_of_birth'])) : '';
    $timezone   = isset($_POST['timezone']) ? sanitize_text_field(wp_unslash($_POST['timezone'])) : '';
    // Shipping / location fields
    $shipping_city     = isset($_POST['shipping_city']) ? sanitize_text_field(wp_unslash($_POST['shipping_city'])) : '';
    $shipping_postcode = isset($_POST['shipping_postcode']) ? sanitize_text_field(wp_unslash($_POST['shipping_postcode'])) : '';
    $shipping_country  = isset($_POST['shipping_country']) ? sanitize_text_field(wp_unslash($_POST['shipping_country'])) : '';

    if (empty($first_name) || empty($last_name)) {
        wp_send_json(['success' => false, 'message' => __('First and last name are required.', 'tfp-dashboard')], 400);
    }

    if (!empty($email) && !is_email($email)) {
        wp_send_json(['success' => false, 'message' => __('Please enter a valid email address.', 'tfp-dashboard')], 400);
    }

    $update_args = [
        'ID'         => $user_id,
        'first_name' => $first_name,
        'last_name'  => $last_name,
    ];
    if (!empty($email)) {
        $update_args['user_email'] = $email;
    }

    $result = wp_update_user($update_args);
    if (is_wp_error($result)) {
        wp_send_json(['success' => false, 'message' => $result->get_error_message()], 400);
    }

    update_user_meta($user_id, 'billing_phone', $phone);
    update_user_meta($user_id, 'tfp_address', $address);
    update_user_meta($user_id, 'tfp_date_of_birth', $dob);
    update_user_meta($user_id, 'tfp_timezone', $timezone);

    // Sync address + shipping fields to WooCommerce meta so they
    // appear in both the My Account dashboard AND the checkout form.
    if ($address) {
        update_user_meta($user_id, 'shipping_address_1', $address);
        update_user_meta($user_id, 'billing_address_1', $address);
    }
    if ($shipping_city) {
        update_user_meta($user_id, 'shipping_city', $shipping_city);
        update_user_meta($user_id, 'billing_city', $shipping_city);
    }
    if ($shipping_postcode) {
        update_user_meta($user_id, 'shipping_postcode', $shipping_postcode);
        update_user_meta($user_id, 'billing_postcode', $shipping_postcode);
    }
    if ($shipping_country) {
        update_user_meta($user_id, 'shipping_country', $shipping_country);
        update_user_meta($user_id, 'billing_country', $shipping_country);
    }
    // Keep shipping first/last name in sync with profile name.
    update_user_meta($user_id, 'shipping_first_name', $first_name);
    update_user_meta($user_id, 'shipping_last_name', $last_name);
    update_user_meta($user_id, 'billing_first_name', $first_name);
    update_user_meta($user_id, 'billing_last_name', $last_name);

    // Optional fields — only present on the single-form Update Profile
    // page (includes/page-update-profile.php). The section-scoped Profile
    // page sends Discord username separately via section=connection, so
    // these are simply absent there and nothing changes for that flow.
    if (isset($_POST['discord_username'])) {
        update_user_meta($user_id, 'tfp_discord_username', sanitize_text_field(wp_unslash($_POST['discord_username'])));
    }
    if (isset($_POST['email_optin'])) {
        update_user_meta($user_id, 'tfp_email_optin', !empty($_POST['email_optin']) ? 1 : 0);
    }

    wp_send_json([
        'success' => true,
        'message' => __('Your profile has been updated.', 'tfp-dashboard'),
        'values'  => [
            'full_name'        => trim($first_name . ' ' . $last_name),
            'email'            => !empty($email) ? $email : get_userdata($user_id)->user_email,
            'phone'            => $phone,
            'dob'              => $dob,
            'address'          => $address,
            'timezone'         => $timezone,
            'shipping_city'    => $shipping_city,
            'shipping_postcode' => $shipping_postcode,
            'shipping_country' => $shipping_country,
        ],
    ]);
}

function tfp_profile_save_connection_details($user_id)
{
    $discord = isset($_POST['discord_username']) ? sanitize_text_field(wp_unslash($_POST['discord_username'])) : '';

    $allowed_contact = ['email', 'discord', 'phone'];
    $preferred_contact = isset($_POST['preferred_contact']) && is_array($_POST['preferred_contact'])
        ? array_values(array_intersect($allowed_contact, array_map('sanitize_key', wp_unslash($_POST['preferred_contact']))))
        : [];

    $allowed_notifications = ['payments', 'facilitator_alerts', 'class_reminders', 'communication_tickets'];
    $notification_settings = isset($_POST['notification_settings']) && is_array($_POST['notification_settings'])
        ? array_values(array_intersect($allowed_notifications, array_map('sanitize_key', wp_unslash($_POST['notification_settings']))))
        : [];

    update_user_meta($user_id, 'tfp_discord_username', $discord);
    update_user_meta($user_id, 'tfp_preferred_contact', $preferred_contact);
    update_user_meta($user_id, 'tfp_notification_settings', $notification_settings);

    wp_send_json([
        'success' => true,
        'message' => __('Your connection details have been updated.', 'tfp-dashboard'),
        'values'  => [
            'discord_username'       => $discord,
            'preferred_contact'      => $preferred_contact,
            'notification_settings'  => $notification_settings,
        ],
    ]);
}

/**
 * Custom avatar upload. Stores the attachment ID in user meta;
 * tfp_dashboard_avatar() (in helpers.php) prefers this over get_avatar()
 * once it's set.
 */
add_action('wp_ajax_tfp_profile_upload_avatar', function () {
    tfp_profile_check_request();

    if (empty($_FILES['avatar']['name'])) {
        wp_send_json(['success' => false, 'message' => __('No file uploaded.', 'tfp-dashboard')], 400);
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $user_id = get_current_user_id();
    $attachment_id = media_handle_upload('avatar', 0);

    if (is_wp_error($attachment_id)) {
        wp_send_json(['success' => false, 'message' => $attachment_id->get_error_message()], 400);
    }

    update_user_meta($user_id, 'tfp_custom_avatar_id', $attachment_id);

    wp_send_json([
        'success'    => true,
        'avatar_url' => wp_get_attachment_image_url($attachment_id, 'thumbnail'),
    ]);
});
