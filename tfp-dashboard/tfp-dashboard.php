<?php
/**
 * Plugin Name: TFP Dashboard
 * Description: Custom-coded student dashboard (Home, Grades, Communication, Documents, Calendar, Profile) for The Follow Project. Reuses helper functions from the TFP Authentication plugin. Not built with Elementor — fully custom templates for app-like behaviour and pixel-perfect design control.
 * Version: 1.2.1
 * Author: The Follow Project
 * Text Domain: tfp-dashboard
 */

if (!defined('ABSPATH')) exit;

define('TFP_DASH_VERSION', '1.2.1');
define('TFP_DASH_PATH', plugin_dir_path(__FILE__));
define('TFP_DASH_URL', plugin_dir_url(__FILE__));

/**
 * Activation: create the chat messages table.
 */
register_activation_hook(__FILE__, function () {
    global $wpdb;
    $table           = $wpdb->prefix . 'tfp_ticket_messages';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        ticket_id BIGINT UNSIGNED NOT NULL,
        sender_id BIGINT UNSIGNED NOT NULL,
        message TEXT NOT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY ticket_id (ticket_id),
        KEY created_at (created_at)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
});

/**
 * Core includes.
 */
require_once TFP_DASH_PATH . 'includes/helpers.php';
require_once TFP_DASH_PATH . 'includes/templates.php';
require_once TFP_DASH_PATH . 'includes/shell.php';
require_once TFP_DASH_PATH . 'includes/icons.php';
require_once TFP_DASH_PATH . 'includes/page-home.php';
require_once TFP_DASH_PATH . 'includes/communication/cpt-tickets.php';
require_once TFP_DASH_PATH . 'includes/communication/db.php';
require_once TFP_DASH_PATH . 'includes/communication/ajax.php';
require_once TFP_DASH_PATH . 'includes/communication/page-communication.php';
require_once TFP_DASH_PATH . 'includes/billing/helpers.php';
require_once TFP_DASH_PATH . 'includes/page-payment-details.php';
require_once TFP_DASH_PATH . 'includes/financial-aid/cpt.php';
require_once TFP_DASH_PATH . 'includes/financial-aid/ajax.php';
require_once TFP_DASH_PATH . 'includes/financial-aid/admin.php';
require_once TFP_DASH_PATH . 'includes/financial-aid/checkout.php';
require_once TFP_DASH_PATH . 'includes/page-financial-aid.php';
require_once TFP_DASH_PATH . 'includes/cohorts/cpt.php';
require_once TFP_DASH_PATH . 'includes/cohorts/helpers.php';
require_once TFP_DASH_PATH . 'includes/cohorts/admin.php';
require_once TFP_DASH_PATH . 'includes/profile/ajax.php';
require_once TFP_DASH_PATH . 'includes/page-profile.php';
require_once TFP_DASH_PATH . 'includes/page-update-profile.php';
require_once TFP_DASH_PATH . 'includes/checkout/template-registration.php';
require_once TFP_DASH_PATH . 'includes/checkout/render.php';
require_once TFP_DASH_PATH . 'includes/checkout/ajax.php';
require_once TFP_DASH_PATH . 'includes/checkout/stripe.php';
require_once TFP_DASH_PATH . 'includes/checkout/course-render.php';
require_once TFP_DASH_PATH . 'includes/checkout/course-ajax.php';
require_once TFP_DASH_PATH . 'includes/learndash/helpers.php';
require_once TFP_DASH_PATH . 'includes/learndash/admin-meta-box.php';
require_once TFP_DASH_PATH . 'includes/week/cpt-readings.php';
require_once TFP_DASH_PATH . 'includes/week/ajax.php';
require_once TFP_DASH_PATH . 'includes/week/homework-helpers.php';
require_once TFP_DASH_PATH . 'includes/week/homework-ajax.php';
require_once TFP_DASH_PATH . 'includes/week/quiz-helpers.php';
require_once TFP_DASH_PATH . 'includes/week/quiz-ajax.php';
require_once TFP_DASH_PATH . 'includes/week/quiz-render.php';
require_once TFP_DASH_PATH . 'includes/page-week.php';
require_once TFP_DASH_PATH . 'includes/program/helpers.php';
require_once TFP_DASH_PATH . 'includes/grades/helpers.php';
require_once TFP_DASH_PATH . 'includes/grades/admin.php';
require_once TFP_DASH_PATH . 'includes/page-program.php';

/**
 * Dashboard pages are logged-in, per-user, dynamic content — they must
 * never be served from a page cache (WP Rocket, LiteSpeed Cache, etc.).
 * DONOTCACHEPAGE is a widely-respected constant across most WordPress
 * caching plugins, including WP Rocket.
 */
add_action('template_redirect', function () {
    if (function_exists('tfp_dashboard_is_dashboard_page') && tfp_dashboard_is_dashboard_page()) {
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
    }
}, 1);

/**
 * Assets. Dashboard pages use their own custom templates (no theme
 * header/footer), so we enqueue everything unconditionally on those
 * templates only, via the templates.php logic (see is_tfp_dashboard_page()).
 */
add_action('wp_enqueue_scripts', function () {
    if (function_exists('tfp_checkout_is_template_page') && tfp_checkout_is_template_page()) {
        wp_enqueue_style('tfp-dashboard-core', TFP_DASH_URL . 'assets/css/dashboard.css', [], TFP_DASH_VERSION);
        wp_enqueue_style('tfp-checkout', TFP_DASH_URL . 'assets/css/checkout.css', ['tfp-dashboard-core'], TFP_DASH_VERSION);
        wp_enqueue_script('tfp-checkout', TFP_DASH_URL . 'assets/js/checkout.js', [], TFP_DASH_VERSION, true);
        wp_localize_script('tfp-checkout', 'tfpCheckoutSettings', [
            'ajaxUrl'     => admin_url('admin-ajax.php'),
            'nonce'       => wp_create_nonce('tfp_checkout_nonce'),
            'defaultStep' => 'cart',
            'checkoutUrl' => function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : home_url('/'),
        ]);

        // Real payment processing inside the custom checkout (Stripe: card +
        // Apple Pay + Google Pay). Only load when keys are configured; otherwise
        // checkout.js keeps its non-Stripe fallback UI.
        if (function_exists('tfp_stripe_is_configured') && tfp_stripe_is_configured()) {
            wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', [], null, true);
            wp_enqueue_script('tfp-checkout-stripe', TFP_DASH_URL . 'assets/js/checkout-stripe.js', ['tfp-checkout', 'stripe-js'], TFP_DASH_VERSION, true);
            wp_localize_script('tfp-checkout-stripe', 'tfpStripeSettings', tfp_stripe_get_frontend_settings());
        }

        // PayPal integration
        wp_enqueue_script('paypal-sdk', 'https://www.paypal.com/sdk/js?client-id=sb&currency=USD&components=buttons', [], null, true);
        wp_enqueue_script('tfp-checkout-paypal', TFP_DASH_URL . 'assets/js/checkout-paypal.js', ['tfp-checkout', 'paypal-sdk'], TFP_DASH_VERSION, true);
        
        // Google Pay native integration
        wp_enqueue_script('google-pay-sdk', 'https://pay.google.com/gp/p/js/pay.js', [], null, true);
        wp_enqueue_script('tfp-checkout-googlepay', TFP_DASH_URL . 'assets/js/checkout-googlepay.js', ['tfp-checkout', 'google-pay-sdk', 'stripe-js'], TFP_DASH_VERSION, true);

        $paypal_total = '0.00';
        if (function_exists('WC') && WC()->cart) {
            $paypal_total = WC()->cart->get_total('edit');
        }

        wp_localize_script('tfp-checkout-paypal', 'tfpPayPalSettings', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('tfp_checkout_nonce'),
            'total'   => $paypal_total
        ]);
    }

    if (!function_exists('tfp_dashboard_is_dashboard_page') || !tfp_dashboard_is_dashboard_page()) {
        return;
    }

    wp_enqueue_style('tfp-dashboard-core', TFP_DASH_URL . 'assets/css/dashboard.css', [], TFP_DASH_VERSION);
    wp_enqueue_script('tfp-dashboard-core', TFP_DASH_URL . 'assets/js/dashboard.js', [], TFP_DASH_VERSION, true);

    $template = tfp_dashboard_current_template_slug();

    if ($template === 'tfp-dashboard-home') {
        // The Home page hosts the program (course) checkout as a modal wizard.
        // It reuses the book checkout's stylesheet + the shared Stripe payment
        // layer, but drives the steps with its own self-contained engine
        // (course-checkout.js) — checkout.js is intentionally NOT loaded here.
        wp_enqueue_style('tfp-checkout', TFP_DASH_URL . 'assets/css/checkout.css', ['tfp-dashboard-core'], TFP_DASH_VERSION);
        wp_enqueue_style('tfp-course-checkout', TFP_DASH_URL . 'assets/css/course-checkout.css', ['tfp-checkout'], TFP_DASH_VERSION);
        wp_enqueue_script('tfp-course-checkout', TFP_DASH_URL . 'assets/js/course-checkout.js', [], TFP_DASH_VERSION, true);
        wp_enqueue_style('tfp-order-details', TFP_DASH_URL . 'assets/css/order-details.css', [], TFP_DASH_VERSION);
        wp_enqueue_style('tfp-order-confirmation', TFP_DASH_URL . 'assets/css/order-confirmation.css', ['tfp-order-details'], TFP_DASH_VERSION);

        $course_id  = function_exists('tfp_ld_get_program_course_id') ? (int) tfp_ld_get_program_course_id() : 0;
        $product_id = ($course_id && function_exists('tfp_billing_get_product_id_for_course'))
            ? (int) tfp_billing_get_product_id_for_course($course_id)
            : 0;
        $state = function_exists('tfp_dashboard_get_program_state') ? tfp_dashboard_get_program_state() : [];

        wp_localize_script('tfp-course-checkout', 'tfpCourseCheckout', [
            'ajaxUrl'    => admin_url('admin-ajax.php'),
            'nonce'      => wp_create_nonce('tfp_checkout_nonce'),
            'courseId'   => $course_id,
            'productId'  => $product_id,
            'faApproved' => !empty($state['fa_status']) && $state['fa_status'] === 'approved',
            'faDiscount' => isset($state['fa_discount']) ? (int) $state['fa_discount'] : 0,
        ]);

        // Real card / Apple Pay / Google Pay processing via the shared Stripe
        // layer. checkout-stripe.js exposes window.tfpStripeRenderMethod(), which
        // course-checkout.js calls on the Payment step. Only load when keys are
        // configured; otherwise the Payment step shows a "not available yet" note.
        if (function_exists('tfp_stripe_is_configured') && tfp_stripe_is_configured()) {
            wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', [], null, true);
            wp_enqueue_script('tfp-checkout-stripe', TFP_DASH_URL . 'assets/js/checkout-stripe.js', ['tfp-course-checkout', 'stripe-js'], TFP_DASH_VERSION, true);
            wp_localize_script('tfp-checkout-stripe', 'tfpStripeSettings', tfp_stripe_get_frontend_settings());
            wp_localize_script('tfp-checkout-stripe', 'tfpCheckoutSettings', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('tfp_checkout_nonce'),
            ]);

            // Native Google Pay (same as the book checkout): the Google Pay SDK
            // renders a real "Pay with G Pay" button that tokenizes through the
            // Stripe gateway. This is why the book checkout shows a working
            // button — course-checkout.js routes 'googlepay' to
            // window.tfpGooglePayRenderMethod (checkout-googlepay.js) instead of
            // the Stripe Payment Request Button, which needs a device wallet and
            // often won't render on staging. Depends on Stripe (tokenization) so
            // it lives inside the configured block.
            wp_enqueue_script('google-pay-sdk', 'https://pay.google.com/gp/p/js/pay.js', [], null, true);
            wp_enqueue_script('tfp-checkout-googlepay', TFP_DASH_URL . 'assets/js/checkout-googlepay.js', ['tfp-course-checkout', 'google-pay-sdk', 'stripe-js'], TFP_DASH_VERSION, true);
        }

        // PayPal (shared checkout-paypal.js), offered alongside Stripe on the
        // course Payment step. checkout.js is not loaded on Home, so this
        // depends on tfp-course-checkout (+ jquery for the IIFE) instead of
        // tfp-checkout. The button amount is refreshed from the live cart via
        // tfp_course_paypal_total right before it renders; the real order is
        // built server-side at capture time, so the localized total is only a
        // starting value. Loads regardless of Stripe config — PayPal is
        // independent of the Stripe keys.
        wp_enqueue_script('paypal-sdk', 'https://www.paypal.com/sdk/js?client-id=sb&currency=USD&components=buttons', [], null, true);
        wp_enqueue_script('tfp-checkout-paypal', TFP_DASH_URL . 'assets/js/checkout-paypal.js', ['jquery', 'tfp-course-checkout', 'paypal-sdk'], TFP_DASH_VERSION, true);

        $course_paypal_total = '0.00';
        if (function_exists('WC') && WC()->cart) {
            $course_paypal_total = wc_format_decimal((float) WC()->cart->get_total('edit'), 2);
        }
        wp_localize_script('tfp-checkout-paypal', 'tfpPayPalSettings', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('tfp_checkout_nonce'),
            'total'   => $course_paypal_total,
        ]);
    }

    if ($template === 'tfp-dashboard-communication') {
        wp_enqueue_style('tfp-dashboard-communication', TFP_DASH_URL . 'assets/css/communication.css', ['tfp-dashboard-core'], TFP_DASH_VERSION);
        wp_enqueue_script('tfp-dashboard-communication', TFP_DASH_URL . 'assets/js/communication.js', [], TFP_DASH_VERSION, true);

        wp_localize_script('tfp-dashboard-communication', 'tfpChatSettings', [
            'ajaxUrl'     => admin_url('admin-ajax.php'),
            'nonce'       => wp_create_nonce('tfp_chat_nonce'),
            'currentUser' => get_current_user_id(),
            'pollInterval'=> 4000,
        ]);
    }

    if (in_array($template, ['tfp-dashboard-payment-details', 'tfp-dashboard-financial-aid', 'tfp-dashboard-profile', 'tfp-dashboard-update-profile'], true)) {
        wp_enqueue_style('tfp-dashboard-forms', TFP_DASH_URL . 'assets/css/forms.css', ['tfp-dashboard-core'], TFP_DASH_VERSION);
    }

    if ($template === 'tfp-dashboard-financial-aid') {
        wp_enqueue_script('tfp-dashboard-financial-aid', TFP_DASH_URL . 'assets/js/financial-aid.js', [], TFP_DASH_VERSION, true);
        wp_localize_script('tfp-dashboard-financial-aid', 'tfpFinancialAidSettings', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('tfp_financial_aid_nonce'),
        ]);
    }

    if ($template === 'tfp-dashboard-profile' || $template === 'tfp-dashboard-update-profile') {
        wp_enqueue_script('tfp-dashboard-profile', TFP_DASH_URL . 'assets/js/profile.js', [], TFP_DASH_VERSION, true);
        wp_localize_script('tfp-dashboard-profile', 'tfpProfileSettings', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('tfp_profile_nonce'),
        ]);
    }

    if ($template === 'tfp-dashboard-week') {
        wp_enqueue_style('tfp-dashboard-forms', TFP_DASH_URL . 'assets/css/forms.css', ['tfp-dashboard-core'], TFP_DASH_VERSION);
        wp_enqueue_style('tfp-dashboard-week', TFP_DASH_URL . 'assets/css/week.css', ['tfp-dashboard-core', 'tfp-dashboard-forms'], TFP_DASH_VERSION);
        wp_enqueue_style('tfp-dashboard-week-quiz', TFP_DASH_URL . 'assets/css/week-quiz.css', ['tfp-dashboard-week'], TFP_DASH_VERSION);
        wp_enqueue_script('tfp-dashboard-week', TFP_DASH_URL . 'assets/js/week.js', [], TFP_DASH_VERSION, true);
        wp_enqueue_script('tfp-dashboard-week-homework', TFP_DASH_URL . 'assets/js/week-homework.js', ['tfp-dashboard-week'], TFP_DASH_VERSION, true);
        wp_enqueue_script('tfp-dashboard-week-quiz', TFP_DASH_URL . 'assets/js/week-quiz.js', ['tfp-dashboard-week'], TFP_DASH_VERSION, true);
        wp_localize_script('tfp-dashboard-week', 'tfpWeekSettings', [
            'ajaxUrl'        => admin_url('admin-ajax.php'),
            'nonce'          => wp_create_nonce('tfp_week_nonce'),
            // Quiz tab UI strings (kept here so week-quiz.js stays translation-ready).
            'networkError'   => __('A network error occurred.', 'tfp-dashboard'),
            'submittingText' => __('Submitting...', 'tfp-dashboard'),
            'submitError'    => __('Error submitting quiz.', 'tfp-dashboard'),
        ]);
    }

    if ($template === 'tfp-dashboard-program') {
        // Dedicated "Continue Program" overview page: stat tiles, journey,
        // current lesson, PAST DUE + Pending Tasks. Only the genuinely new
        // components need CSS; journey/panel/button styles ship in dashboard.css.
        wp_enqueue_style('tfp-dashboard-program', TFP_DASH_URL . 'assets/css/program.css', ['tfp-dashboard-core'], TFP_DASH_VERSION);
    }


});

// Include Order Details Shortcode
require_once TFP_DASH_PATH . 'includes/shortcodes/order-details.php';
require_once TFP_DASH_PATH . 'includes/checkout/order-confirmation.php';
