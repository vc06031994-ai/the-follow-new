<?php
/**
 * Cohort custom post type.
 *
 * A "cohort" is a scheduled run of a program (LearnDash course) with a start
 * date, a facilitator, a weekly meeting schedule and a limited number of
 * seats. The WooCommerce program product stays the payment unit — a cohort
 * just records WHICH run of the program a student is buying into and how many
 * seats are left. Mirrors the tfp_financial_aid CPT pattern (admin-only UI,
 * not publicly queryable).
 */

if (!defined('ABSPATH')) exit;

add_action('init', function () {
    register_post_type('tfp_cohort', [
        'label'           => __('Cohorts', 'tfp-dashboard'),
        'labels'          => [
            'name'               => __('Cohorts', 'tfp-dashboard'),
            'singular_name'      => __('Cohort', 'tfp-dashboard'),
            'add_new_item'       => __('Add New Cohort', 'tfp-dashboard'),
            'edit_item'          => __('Edit Cohort', 'tfp-dashboard'),
            'new_item'           => __('New Cohort', 'tfp-dashboard'),
            'view_item'          => __('View Cohort', 'tfp-dashboard'),
            'search_items'       => __('Search Cohorts', 'tfp-dashboard'),
            'not_found'          => __('No cohorts found', 'tfp-dashboard'),
            'all_items'          => __('All Cohorts', 'tfp-dashboard'),
        ],
        'public'          => false,
        'show_ui'         => true,
        'show_in_menu'    => true,
        'menu_icon'       => 'dashicons-groups',
        'menu_position'   => 26,
        'supports'        => ['title'],
        'capability_type' => 'post',
        'map_meta_cap'    => true,
    ]);
});
