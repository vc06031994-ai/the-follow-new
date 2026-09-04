<?php
if (!defined('ABSPATH')) exit;

add_action('init', function () {
    register_post_type('tfp_financial_aid', [
        'label'           => __('Financial Aid Applications', 'tfp-dashboard'),
        'labels'          => [
            'name'          => __('Financial Aid Applications', 'tfp-dashboard'),
            'singular_name' => __('Financial Aid Application', 'tfp-dashboard'),
        ],
        'public'          => false,
        'show_ui'         => true,
        'show_in_menu'    => true,
        'menu_icon'       => 'dashicons-heart',
        'supports'        => ['title'],
        'capability_type' => 'post',
        'map_meta_cap'    => true,
    ]);
});

function tfp_financial_aid_statuses()
{
    return [
        'pending'  => __('Pending Review', 'tfp-dashboard'),
        'approved' => __('Approved', 'tfp-dashboard'),
        'rejected' => __('Not Approved', 'tfp-dashboard'),
    ];
}
