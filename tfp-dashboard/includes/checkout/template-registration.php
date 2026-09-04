<?php
if (!defined('ABSPATH')) {
    exit;
}

function tfp_checkout_template_map()
{
    return [
        'tfp-checkout-wizard' => [
            'label' => __('TFP Checkout Wizard', 'tfp-dashboard'),
            'file' => 'templates/checkout/template-checkout.php',
        ],
    ];
}

add_filter('theme_page_templates', function ($templates) {
    foreach (tfp_checkout_template_map() as $slug => $data) {
        $templates[$slug] = $data['label'];
    }

    return $templates;
});

add_filter('template_include', function ($template) {
    if (!is_page()) {
        return $template;
    }

    $slug = get_page_template_slug(get_the_ID());
    $map = tfp_checkout_template_map();

    if (isset($map[$slug])) {
        $file = TFP_DASH_PATH . $map[$slug]['file'];
        if (file_exists($file)) {
            return $file;
        }
    }

    return $template;
});

function tfp_checkout_is_template_page()
{
    if (!is_page()) {
        return false;
    }

    $slug = get_page_template_slug(get_the_ID());

    return array_key_exists($slug, tfp_checkout_template_map());
}

function tfp_checkout_get_url()
{
    static $cache = [];

    if (isset($cache['tfp-checkout-wizard'])) {
        return $cache['tfp-checkout-wizard'];
    }

    $pages = get_posts([
        'post_type'      => 'page',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'meta_key'       => '_wp_page_template',
        'meta_value'     => 'tfp-checkout-wizard',
        'fields'         => 'ids',
    ]);

    $url = !empty($pages) ? get_permalink($pages[0]) : wc_get_cart_url();
    $cache['tfp-checkout-wizard'] = $url;

    return $url;
}
