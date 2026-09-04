<?php
/**
 * TFP Dashboard Shortcodes
 *
 * Backend data-provider shortcodes for an Elementor-built User Dashboard.
 * This file does NOT touch login.php, register.php, account-menu.php, AJAX,
 * or any existing CSS. It only reads data those systems already save.
 *
 * Every shortcode here returns an empty string for logged-out visitors —
 * no errors, no redirects.
 */

if (!defined('ABSPATH')) exit;

/* =========================================================================
 * HELPER FUNCTIONS
 * Reused by every shortcode below. Keep all data-fetching logic here so
 * future features (Billing, Orders, LearnDash, Certificates, Groups,
 * Notifications, Avatar Upload) can reuse the same helpers.
 * ========================================================================= */

/**
 * Get the current logged-in WP_User, or null if logged out.
 */
function tfp_get_current_user_or_null()
{
    if (!is_user_logged_in()) {
        return null;
    }
    $user = wp_get_current_user();
    if (!$user || !$user->exists()) {
        return null;
    }
    return $user;
}

/**
 * Resolve the best available display name.
 * Priority: Display Name -> First Name + Last Name -> Username.
 * Never returns the email address.
 */
function tfp_get_current_user_name($user = null)
{
    $user = $user ?: tfp_get_current_user_or_null();
    if (!$user) {
        return '';
    }

    if (!empty($user->display_name)) {
        return $user->display_name;
    }

    $first = trim((string) get_user_meta($user->ID, 'first_name', true));
    $last  = trim((string) get_user_meta($user->ID, 'last_name', true));
    $full  = trim($first . ' ' . $last);
    if (!empty($full)) {
        return $full;
    }

    return $user->user_login;
}

/**
 * First name only (empty string if not set).
 */
function tfp_get_current_user_first_name($user = null)
{
    $user = $user ?: tfp_get_current_user_or_null();
    if (!$user) {
        return '';
    }
    return trim((string) get_user_meta($user->ID, 'first_name', true));
}

/**
 * Last name only (empty string if not set).
 */
function tfp_get_current_user_last_name($user = null)
{
    $user = $user ?: tfp_get_current_user_or_null();
    if (!$user) {
        return '';
    }
    return trim((string) get_user_meta($user->ID, 'last_name', true));
}

/**
 * Human-readable primary role label for the current user
 * (e.g. Student, Administrator, Facilitator, Customer).
 */
function tfp_get_current_user_role_label($user = null)
{
    $user = $user ?: tfp_get_current_user_or_null();
    if (!$user || empty($user->roles)) {
        return '';
    }

    $role_slug = $user->roles[0];

    global $wp_roles;
    if (!isset($wp_roles) || !$wp_roles) {
        $wp_roles = wp_roles();
    }

    if (isset($wp_roles->role_names[$role_slug])) {
        return translate_user_role($wp_roles->role_names[$role_slug]);
    }

    return ucwords(str_replace(['-', '_'], ' ', $role_slug));
}

/**
 * The program (course) the user selected at registration.
 * Reads the `tfp_program_choice` user meta saved by the registration
 * system (a post/course ID) and resolves it to its title.
 */
function tfp_get_current_program($user = null)
{
    $user = $user ?: tfp_get_current_user_or_null();
    if (!$user) {
        return '';
    }

    $program_id = (int) get_user_meta($user->ID, 'tfp_program_choice', true);
    if ($program_id <= 0) {
        return __('No Program Selected', 'tfp-authentication');
    }

    $title = get_the_title($program_id);
    if (empty($title)) {
        return __('No Program Selected', 'tfp-authentication');
    }

    return $title;
}

/**
 * Profile completion percentage (0-100), calculated dynamically.
 * Checks: First Name, Last Name, Email, Phone, Program Choice, Avatar.
 * Referral Code is intentionally ignored (optional field).
 */
function tfp_get_profile_completion($user = null)
{
    $user = $user ?: tfp_get_current_user_or_null();
    if (!$user) {
        return 0;
    }

    $fields = [
        'first_name' => trim((string) get_user_meta($user->ID, 'first_name', true)),
        'last_name'  => trim((string) get_user_meta($user->ID, 'last_name', true)),
        'email'      => trim((string) $user->user_email),
        'phone'      => trim((string) get_user_meta($user->ID, 'billing_phone', true)),
        'program'    => (int) get_user_meta($user->ID, 'tfp_program_choice', true),
        'avatar'     => trim((string) get_user_meta($user->ID, 'tfp_custom_avatar_id', true)), // future support
    ];

    /**
     * Filter which fields count toward profile completion, and let future
     * features (e.g. avatar upload) plug their own checks in without
     * touching this function.
     */
    $fields = apply_filters('tfp_profile_completion_fields', $fields, $user);

    $total     = count($fields);
    $completed = 0;

    foreach ($fields as $key => $value) {
        if ($key === 'program') {
            if ((int) $value > 0) {
                $completed++;
            }
            continue;
        }
        if (!empty($value)) {
            $completed++;
        }
    }

    if ($total <= 0) {
        return 0;
    }

    return (int) round(($completed / $total) * 100);
}

/**
 * Time-of-day greeting label (Good Morning / Afternoon / Evening) based
 * on the server's current time.
 */
function tfp_get_time_of_day_greeting()
{
    $hour = (int) current_time('G');

    if ($hour < 12) {
        return __('Good Morning', 'tfp-authentication');
    }
    if ($hour < 17) {
        return __('Good Afternoon', 'tfp-authentication');
    }
    return __('Good Evening', 'tfp-authentication');
}

/* =========================================================================
 * SHORTCODES
 * Every shortcode returns '' for logged-out visitors. No errors, no
 * redirects, no notices.
 * ========================================================================= */

/**
 * [tfp_user_name]
 */
add_shortcode('tfp_user_name', function () {
    $user = tfp_get_current_user_or_null();
    if (!$user) return '';
    return esc_html(tfp_get_current_user_name($user));
});

/**
 * [tfp_user_first_name]
 */
add_shortcode('tfp_user_first_name', function () {
    $user = tfp_get_current_user_or_null();
    if (!$user) return '';
    return esc_html(tfp_get_current_user_first_name($user));
});

/**
 * [tfp_user_last_name]
 */
add_shortcode('tfp_user_last_name', function () {
    $user = tfp_get_current_user_or_null();
    if (!$user) return '';
    return esc_html(tfp_get_current_user_last_name($user));
});

/**
 * [tfp_user_avatar size="80"]
 */
add_shortcode('tfp_user_avatar', function ($atts) {
    $user = tfp_get_current_user_or_null();
    if (!$user) return '';

    $atts = shortcode_atts([
        'size' => 80,
    ], $atts, 'tfp_user_avatar');

    $size = absint($atts['size']);
    if ($size <= 0) {
        $size = 80;
    }

    return get_avatar($user->ID, $size, '', tfp_get_current_user_name($user), [
        'class' => 'tfp-dash-avatar',
    ]);
});

/**
 * [tfp_user_role]
 */
add_shortcode('tfp_user_role', function () {
    $user = tfp_get_current_user_or_null();
    if (!$user) return '';
    return esc_html(tfp_get_current_user_role_label($user));
});

/**
 * [tfp_logout_button text="Logout" class="my-class"]
 */
add_shortcode('tfp_logout_button', function ($atts) {
    if (!is_user_logged_in()) return '';

    $atts = shortcode_atts([
        'text'  => __('Logout', 'tfp-authentication'),
        'class' => '',
    ], $atts, 'tfp_logout_button');

    $classes = trim('tfp-dash-logout-btn ' . sanitize_html_class($atts['class']));

    return sprintf(
        '<a href="%1$s" class="%2$s">%3$s</a>',
        esc_url(wp_logout_url(home_url())),
        esc_attr($classes),
        esc_html($atts['text'])
    );
});

/**
 * [tfp_profile_completion]
 * Returns only the numeric percentage (e.g. 75), no % sign — matches spec.
 */
add_shortcode('tfp_profile_completion', function () {
    $user = tfp_get_current_user_or_null();
    if (!$user) return '';
    return (string) tfp_get_profile_completion($user);
});

/**
 * [tfp_profile_progress]
 * Visual progress bar. Styling lives entirely in dashboard-shortcodes.css.
 */
add_shortcode('tfp_profile_progress', function () {
    $user = tfp_get_current_user_or_null();
    if (!$user) return '';

    $percent = tfp_get_profile_completion($user);

    ob_start();
    ?>
    <div class="tfp-dash-progress" role="progressbar" aria-valuenow="<?php echo esc_attr($percent); ?>" aria-valuemin="0" aria-valuemax="100">
        <div class="tfp-dash-progress__bar" data-tfp-progress="<?php echo esc_attr($percent); ?>"></div>
    </div>
    <?php
    return ob_get_clean();
});

/**
 * [tfp_current_program]
 */
add_shortcode('tfp_current_program', function () {
    $user = tfp_get_current_user_or_null();
    if (!$user) return '';
    return esc_html(tfp_get_current_program($user));
});

/**
 * [tfp_dashboard_greeting]
 * "Good Morning, John" / "Good Afternoon, John" / "Good Evening, John"
 */
add_shortcode('tfp_dashboard_greeting', function () {
    $user = tfp_get_current_user_or_null();
    if (!$user) return '';

    $name = tfp_get_current_user_first_name($user);
    if (empty($name)) {
        $name = tfp_get_current_user_name($user);
    }

    return esc_html(sprintf(
        '%s, %s',
        tfp_get_time_of_day_greeting(),
        $name
    ));
});

/**
 * [tfp_user_email]
 */
add_shortcode('tfp_user_email', function () {
    $user = tfp_get_current_user_or_null();
    if (!$user) return '';
    return esc_html($user->user_email);
});

/**
 * [tfp_user_phone]
 */
add_shortcode('tfp_user_phone', function () {
    $user = tfp_get_current_user_or_null();
    if (!$user) return '';
    return esc_html((string) get_user_meta($user->ID, 'billing_phone', true));
});

/**
 * [tfp_user_id]
 */
add_shortcode('tfp_user_id', function () {
    $user = tfp_get_current_user_or_null();
    if (!$user) return '';
    return esc_html((string) $user->ID);
});

/**
 * [tfp_profile_button text="Go To Profile" class="my-class"]
 * Links to the WooCommerce My Account -> Edit Account page.
 */
add_shortcode('tfp_profile_button', function ($atts) {
    if (!is_user_logged_in()) return '';

    $atts = shortcode_atts([
        'text'  => __('Go To Profile', 'tfp-authentication'),
        'class' => '',
    ], $atts, 'tfp_profile_button');

    $url = function_exists('wc_get_account_endpoint_url')
        ? wc_get_account_endpoint_url('edit-account')
        : admin_url('profile.php');

    $classes = trim('tfp-dash-profile-btn ' . sanitize_html_class($atts['class']));

    return sprintf(
        '<a href="%1$s" class="%2$s">%3$s</a>',
        esc_url($url),
        esc_attr($classes),
        esc_html($atts['text'])
    );
});

/**
 * [tfp_dashboard_completion_card]
 * Complete HTML block: name, completion %, and a profile button.
 * Meant to later replace multiple separate Elementor widgets.
 */
add_shortcode('tfp_dashboard_completion_card', function () {
    $user = tfp_get_current_user_or_null();
    if (!$user) return '';

    $name    = tfp_get_current_user_name($user);
    $percent = tfp_get_profile_completion($user);
    $url     = function_exists('wc_get_account_endpoint_url')
        ? wc_get_account_endpoint_url('edit-account')
        : admin_url('profile.php');

    ob_start();
    ?>
    <div class="tfp-dash-card">
        <div class="tfp-dash-card__header">
            <span class="tfp-dash-card__name"><?php echo esc_html($name); ?></span>
            <span class="tfp-dash-card__percent"><?php echo esc_html($percent); ?>%</span>
        </div>
        <div class="tfp-dash-progress" role="progressbar" aria-valuenow="<?php echo esc_attr($percent); ?>" aria-valuemin="0" aria-valuemax="100">
            <div class="tfp-dash-progress__bar" data-tfp-progress="<?php echo esc_attr($percent); ?>"></div>
        </div>
        <a href="<?php echo esc_url($url); ?>" class="tfp-dash-profile-btn"><?php esc_html_e('Go To Profile', 'tfp-authentication'); ?></a>
    </div>
    <?php
    return ob_get_clean();
});

/* =========================================================================
 * ASSETS
 * Dashboard shortcode styles live in their own stylesheet — completely
 * separate from login/register/account-menu CSS.
 * ========================================================================= */

add_action('wp_enqueue_scripts', function () {
    if (!function_exists('wp_register_style')) return;

    wp_register_style(
        'tfp-dashboard-shortcodes',
        plugins_url('assets/css/dashboard-shortcodes.css', TFP_AUTH_PATH . 'tfp-authentication.php'),
        [],
        defined('TFP_AUTH_VERSION') ? TFP_AUTH_VERSION : null
    );
});

add_action('wp_enqueue_scripts', function () {
    if (!function_exists('wp_enqueue_style')) return;

    // Loaded on every front-end page since dashboard shortcodes are
    // typically placed inside Elementor templates rather than post_content,
    // which has_shortcode() cannot reliably detect.
    wp_enqueue_style('tfp-dashboard-shortcodes');
});
