<?php
if (!defined('ABSPATH')) exit;

add_action('init', function () {
    register_post_type('tfp_ticket', [
        'label'           => __('Support Tickets', 'tfp-dashboard'),
        'labels'          => [
            'name'          => __('Support Tickets', 'tfp-dashboard'),
            'singular_name' => __('Support Ticket', 'tfp-dashboard'),
        ],
        'public'          => false,
        'show_ui'         => true,
        'show_in_menu'    => true,
        'menu_icon'       => 'dashicons-format-chat',
        'supports'        => ['title'],
        'capability_type' => 'post',
        'map_meta_cap'    => true,
    ]);
});

/**
 * Ticket status constants (stored in _tfp_status meta):
 *   open      -> waiting on staff/support
 *   pending   -> staff replied, waiting on the student
 *   resolved  -> closed
 */
function tfp_dashboard_ticket_statuses()
{
    return [
        'open'     => __('Open', 'tfp-dashboard'),
        'pending'  => __('Pending Reply', 'tfp-dashboard'),
        'resolved' => __('Resolved', 'tfp-dashboard'),
    ];
}

function tfp_dashboard_ticket_categories()
{
    return [
        'access'    => __('Access Issue', 'tfp-dashboard'),
        'technical' => __('Technical', 'tfp-dashboard'),
        'billing'   => __('Billing', 'tfp-dashboard'),
    ];
}
