<?php
/**
 * Registers our dashboard page templates so an admin can create normal
 * WordPress Pages (Home, Communication, ...) and pick a template from
 * Page Attributes — no hardcoded URLs anywhere in this plugin.
 */

if (!defined('ABSPATH')) exit;

/**
 * Map of template slug => [ label shown in Page Attributes, template file ].
 * Add a new row here whenever a new dashboard page is built
 * (Grades, Mastery, Documents, Calendar, Profile...).
 */
function tfp_dashboard_template_map()
{
    return [
        'tfp-dashboard-home'          => [
            'label' => __('TFP Dashboard — Home', 'tfp-dashboard'),
            'file'  => 'templates/template-home.php',
        ],
        'tfp-dashboard-communication'  => [
            'label' => __('TFP Dashboard — Communication', 'tfp-dashboard'),
            'file'  => 'templates/template-communication.php',
        ],
        'tfp-dashboard-payment-details' => [
            'label' => __('TFP Dashboard — Payment Details', 'tfp-dashboard'),
            'file'  => 'templates/template-payment-details.php',
        ],
        'tfp-dashboard-financial-aid'  => [
            'label' => __('TFP Dashboard — Financial Aid', 'tfp-dashboard'),
            'file'  => 'templates/template-financial-aid.php',
        ],
        'tfp-dashboard-profile'        => [
            'label' => __('TFP Dashboard — Profile', 'tfp-dashboard'),
            'file'  => 'templates/template-profile.php',
        ],
        'tfp-dashboard-update-profile' => [
            'label' => __('TFP Dashboard — Update Profile', 'tfp-dashboard'),
            'file'  => 'templates/template-update-profile.php',
        ],
        'tfp-dashboard-week'           => [
            'label' => __('TFP Dashboard — Week', 'tfp-dashboard'),
            'file'  => 'templates/template-week.php',
        ],
        'tfp-dashboard-program'        => [
            'label' => __('TFP Dashboard — Program', 'tfp-dashboard'),
            'file'  => 'templates/template-program.php',
        ],

    ];
}

add_filter('theme_page_templates', function ($templates) {
    foreach (tfp_dashboard_template_map() as $slug => $data) {
        $templates[$slug] = $data['label'];
    }
    return $templates;
});

add_filter('template_include', function ($template) {
    if (!is_page()) {
        return $template;
    }

    $slug = get_page_template_slug(get_the_ID());
    $map  = tfp_dashboard_template_map();

    if (isset($map[$slug])) {
        $file = TFP_DASH_PATH . $map[$slug]['file'];
        if (file_exists($file)) {
            return $file;
        }
    }

    return $template;
});

/**
 * Is the currently-viewed request one of our dashboard templates?
 */
function tfp_dashboard_is_dashboard_page()
{
    if (!is_page()) {
        return false;
    }
    $slug = get_page_template_slug(get_the_ID());
    return array_key_exists($slug, tfp_dashboard_template_map());
}

function tfp_dashboard_current_template_slug()
{
    if (!is_page()) {
        return '';
    }
    return get_page_template_slug(get_the_ID());
}

/**
 * Resolve the URL of whichever Page has a given dashboard template
 * assigned. Used by the sidebar nav so links always point at whatever
 * page the admin actually created/renamed — never hardcoded.
 */
function tfp_dashboard_get_url($template_slug)
{
    static $cache = [];

    if (isset($cache[$template_slug])) {
        return $cache[$template_slug];
    }

    $pages = get_posts([
        'post_type'      => 'page',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'meta_key'       => '_wp_page_template',
        'meta_value'     => $template_slug,
        'fields'         => 'ids',
    ]);

    $url = !empty($pages) ? get_permalink($pages[0]) : '#';
    $cache[$template_slug] = $url;

    return $url;
}

/**
 * Guard: redirect guests away from any dashboard template straight to
 * the homepage. Call this at the top of every template file.
 */
function tfp_dashboard_require_login()
{
    if (!is_user_logged_in()) {
        wp_safe_redirect(home_url('/'));
        exit;
    }
}
