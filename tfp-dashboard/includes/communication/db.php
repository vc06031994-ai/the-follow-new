<?php
if (!defined('ABSPATH')) exit;

function tfp_dashboard_messages_table()
{
    global $wpdb;
    return $wpdb->prefix . 'tfp_ticket_messages';
}

/**
 * Insert a chat message and return its new row id.
 */
function tfp_chat_insert_message($ticket_id, $sender_id, $message)
{
    global $wpdb;

    $wpdb->insert(
        tfp_dashboard_messages_table(),
        [
            'ticket_id'  => (int) $ticket_id,
            'sender_id'  => (int) $sender_id,
            'message'    => wp_kses_post($message),
            'created_at' => current_time('mysql'),
        ],
        ['%d', '%d', '%s', '%s']
    );

    return (int) $wpdb->insert_id;
}

/**
 * Get messages for a ticket, optionally only those after a given id
 * (used for polling: "give me anything new since message #42").
 */
function tfp_chat_get_messages($ticket_id, $after_id = 0)
{
    global $wpdb;
    $table = tfp_dashboard_messages_table();

    if ($after_id > 0) {
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE ticket_id = %d AND id > %d ORDER BY id ASC",
            $ticket_id,
            $after_id
        ));
    } else {
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE ticket_id = %d ORDER BY id ASC",
            $ticket_id
        ));
    }

    return $rows ?: [];
}

function tfp_chat_get_last_message($ticket_id)
{
    global $wpdb;
    $table = tfp_dashboard_messages_table();

    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table} WHERE ticket_id = %d ORDER BY id DESC LIMIT 1",
        $ticket_id
    ));
}

/**
 * Can the given user view/reply to this ticket?
 * Staff can access every ticket. Students can only access their own.
 */
function tfp_chat_user_can_access_ticket($ticket_id, $user_id)
{
    if (tfp_dashboard_user_is_staff($user_id)) {
        return true;
    }

    $owner_id = (int) get_post_meta($ticket_id, '_tfp_student_id', true);
    return $owner_id === (int) $user_id;
}

/**
 * Fetch the list of tickets visible to a user (their own, or all if staff),
 * newest activity first.
 */
function tfp_dashboard_get_visible_tickets($user_id, $status_filter = '')
{
    $args = [
        'post_type'      => 'tfp_ticket',
        'post_status'    => 'publish',
        'posts_per_page' => 50,
        'orderby'        => 'modified',
        'order'          => 'DESC',
    ];

    if (!tfp_dashboard_user_is_staff($user_id)) {
        $args['meta_query'] = [
            [
                'key'   => '_tfp_student_id',
                'value' => $user_id,
            ],
        ];
    }

    if ($status_filter && array_key_exists($status_filter, tfp_dashboard_ticket_statuses())) {
        $args['meta_query'][] = [
            'key'   => '_tfp_status',
            'value' => $status_filter,
        ];
    }

    return get_posts($args);
}

function tfp_dashboard_count_tickets_by_status($user_id, $status)
{
    $tickets = tfp_dashboard_get_visible_tickets($user_id, $status);
    return count($tickets);
}
