<?php
if (!defined('ABSPATH')) exit;

function tfp_chat_check_request()
{
    if (!is_user_logged_in()) {
        wp_send_json(['success' => false, 'message' => __('You must be logged in.', 'tfp-dashboard')], 403);
    }

    if (!isset($_POST['tfp_chat_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tfp_chat_nonce'])), 'tfp_chat_nonce')) {
        wp_send_json(['success' => false, 'message' => __('Security check failed.', 'tfp-dashboard')], 403);
    }
}

function tfp_chat_format_message_row($row)
{
    $sender = get_userdata($row->sender_id);

    return [
        'id'          => (int) $row->id,
        'ticket_id'   => (int) $row->ticket_id,
        'sender_id'   => (int) $row->sender_id,
        'sender_name' => $sender ? $sender->display_name : __('Unknown', 'tfp-dashboard'),
        'is_mine'     => (int) $row->sender_id === get_current_user_id(),
        'is_staff'    => tfp_dashboard_user_is_staff($row->sender_id),
        'message'     => wp_kses_post($row->message),
        'created_at'  => mysql2date('M j, g:i A', $row->created_at),
        'timestamp'   => strtotime($row->created_at),
    ];
}

/**
 * Create a new ticket + its first message.
 */
add_action('wp_ajax_tfp_chat_create_ticket', function () {
    tfp_chat_check_request();

    $subject  = isset($_POST['subject']) ? sanitize_text_field(wp_unslash($_POST['subject'])) : '';
    $category = isset($_POST['category']) ? sanitize_key(wp_unslash($_POST['category'])) : '';
    $message  = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';

    if (empty($subject) || empty($message) || !array_key_exists($category, tfp_dashboard_ticket_categories())) {
        wp_send_json(['success' => false, 'message' => __('Please fill in all fields.', 'tfp-dashboard')], 400);
    }

    $ticket_id = wp_insert_post([
        'post_type'   => 'tfp_ticket',
        'post_title'  => $subject,
        'post_status' => 'publish',
    ], true);

    if (is_wp_error($ticket_id)) {
        wp_send_json(['success' => false, 'message' => __('Could not create ticket.', 'tfp-dashboard')], 500);
    }

    $user_id = get_current_user_id();
    update_post_meta($ticket_id, '_tfp_status', 'open');
    update_post_meta($ticket_id, '_tfp_category', $category);
    update_post_meta($ticket_id, '_tfp_student_id', $user_id);

    $message_id = tfp_chat_insert_message($ticket_id, $user_id, $message);

    wp_send_json([
        'success'   => true,
        'ticket_id' => $ticket_id,
        'message'   => tfp_chat_format_message_row(tfp_chat_get_last_message($ticket_id)),
    ]);
});

/**
 * Send a message on an existing ticket. Flips status: a student reply
 * re-opens the ticket for staff ("open"); a staff reply hands it back
 * to the student ("pending").
 */
add_action('wp_ajax_tfp_chat_send_message', function () {
    tfp_chat_check_request();

    $ticket_id = isset($_POST['ticket_id']) ? absint($_POST['ticket_id']) : 0;
    $message   = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';
    $user_id   = get_current_user_id();

    if (!$ticket_id || get_post_type($ticket_id) !== 'tfp_ticket') {
        wp_send_json(['success' => false, 'message' => __('Ticket not found.', 'tfp-dashboard')], 404);
    }

    if (!tfp_chat_user_can_access_ticket($ticket_id, $user_id)) {
        wp_send_json(['success' => false, 'message' => __('You cannot access this ticket.', 'tfp-dashboard')], 403);
    }

    if (empty($message)) {
        wp_send_json(['success' => false, 'message' => __('Message cannot be empty.', 'tfp-dashboard')], 400);
    }

    $new_id = tfp_chat_insert_message($ticket_id, $user_id, $message);

    $new_status = tfp_dashboard_user_is_staff($user_id) ? 'pending' : 'open';
    update_post_meta($ticket_id, '_tfp_status', $new_status);
    wp_update_post(['ID' => $ticket_id]); // bumps post_modified for sorting

    wp_send_json([
        'success' => true,
        'message' => tfp_chat_format_message_row(tfp_chat_get_last_message($ticket_id)),
        'status'  => $new_status,
    ]);
});

/**
 * Poll for messages newer than `after_id`. This is what makes the chat
 * feel real-time without needing a WebSocket server.
 */
add_action('wp_ajax_tfp_chat_get_messages', function () {
    tfp_chat_check_request();

    $ticket_id = isset($_POST['ticket_id']) ? absint($_POST['ticket_id']) : 0;
    $after_id  = isset($_POST['after_id']) ? absint($_POST['after_id']) : 0;
    $user_id   = get_current_user_id();

    if (!$ticket_id || get_post_type($ticket_id) !== 'tfp_ticket') {
        wp_send_json(['success' => false, 'message' => __('Ticket not found.', 'tfp-dashboard')], 404);
    }

    if (!tfp_chat_user_can_access_ticket($ticket_id, $user_id)) {
        wp_send_json(['success' => false, 'message' => __('You cannot access this ticket.', 'tfp-dashboard')], 403);
    }

    $rows = tfp_chat_get_messages($ticket_id, $after_id);

    wp_send_json([
        'success'  => true,
        'messages' => array_map('tfp_chat_format_message_row', $rows),
        'status'   => get_post_meta($ticket_id, '_tfp_status', true),
    ]);
});

/**
 * Mark a ticket resolved.
 */
add_action('wp_ajax_tfp_chat_mark_resolved', function () {
    tfp_chat_check_request();

    $ticket_id = isset($_POST['ticket_id']) ? absint($_POST['ticket_id']) : 0;
    $user_id   = get_current_user_id();

    if (!$ticket_id || get_post_type($ticket_id) !== 'tfp_ticket') {
        wp_send_json(['success' => false, 'message' => __('Ticket not found.', 'tfp-dashboard')], 404);
    }

    if (!tfp_chat_user_can_access_ticket($ticket_id, $user_id)) {
        wp_send_json(['success' => false, 'message' => __('You cannot access this ticket.', 'tfp-dashboard')], 403);
    }

    update_post_meta($ticket_id, '_tfp_status', 'resolved');

    wp_send_json(['success' => true]);
});
