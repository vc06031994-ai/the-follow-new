<?php
/**
 * Shared helpers for the TFP Dashboard plugin.
 * Wraps helpers already defined in the TFP Authentication plugin where
 * possible, with safe fallbacks if that plugin is inactive.
 */

if (!defined('ABSPATH')) exit;

function tfp_dashboard_user_name()
{
    if (function_exists('tfp_get_current_user_name')) {
        return tfp_get_current_user_name();
    }
    $user = wp_get_current_user();
    return $user && $user->exists() ? $user->display_name : '';
}

function tfp_dashboard_user_first_name()
{
    if (function_exists('tfp_get_current_user_first_name')) {
        return tfp_get_current_user_first_name();
    }
    $user = wp_get_current_user();
    return $user ? get_user_meta($user->ID, 'first_name', true) : '';
}

function tfp_dashboard_user_role_label()
{
    if (function_exists('tfp_get_current_user_role_label')) {
        return tfp_get_current_user_role_label();
    }
    $user = wp_get_current_user();
    return $user && !empty($user->roles) ? ucfirst($user->roles[0]) : '';
}

function tfp_dashboard_profile_completion()
{
    if (function_exists('tfp_get_profile_completion')) {
        return tfp_get_profile_completion();
    }
    return 0;
}

function tfp_dashboard_current_program()
{
    if (function_exists('tfp_get_current_program')) {
        return tfp_get_current_program();
    }
    return __('No Program Selected', 'tfp-dashboard');
}

/**
 * Resolve a student's program enrollment state for the Home program card and
 * the course checkout. Combines three signals:
 *   - payment      (tfp_billing_user_has_paid)
 *   - cohort choice (user meta tfp_cohort_choice, set on enrollment)
 *   - financial aid (their latest tfp_financial_aid application)
 *
 * Status is derived, not just read from meta, so it can't drift:
 *   enrolled   = has paid AND a cohort is recorded
 *   invited    = financial aid approved, OR meta explicitly says "invited"
 *   waitlisted = financial aid applied and still pending admin review
 *   unpaid     = the default for a registered student who hasn't applied for
 *                aid (or was rejected) and hasn't paid yet
 *
 * Returns an array consumed by tfp_dashboard_render_program_card() and the
 * checkout localize.
 */
function tfp_dashboard_get_program_state($user_id = null)
{
    $user_id = $user_id ?: get_current_user_id();

    $state = [
        'status'      => 'unpaid',
        'has_paid'    => false,
        'course_id'   => 0,
        'cohort_id'   => 0,
        'cohort'      => null,
        'fa_status'   => 'none',   // none | pending | approved | rejected
        'fa_discount' => 0,
    ];

    if (!$user_id) {
        return $state;
    }

    if (function_exists('tfp_ld_get_program_course_id')) {
        $state['course_id'] = (int) tfp_ld_get_program_course_id($user_id);
    }

    $state['has_paid'] = function_exists('tfp_billing_user_has_paid') && tfp_billing_user_has_paid($user_id);

    $cohort_id = (int) get_user_meta($user_id, 'tfp_cohort_choice', true);
    if ($cohort_id) {
        $state['cohort_id'] = $cohort_id;
        if (function_exists('tfp_cohort_get')) {
            $state['cohort'] = tfp_cohort_get($cohort_id);
        }
    }

    // Latest financial aid application for this student.
    $applications = get_posts([
        'post_type'      => 'tfp_financial_aid',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'fields'         => 'ids',
        'meta_query'     => [
            [
                'key'   => '_tfp_student_id',
                'value' => $user_id,
            ],
        ],
    ]);
    if (!empty($applications)) {
        $fa_id                = $applications[0];
        $state['fa_status']   = get_post_meta($fa_id, '_tfp_status', true) ?: 'pending';
        $state['fa_discount'] = (int) get_post_meta($fa_id, '_tfp_discount_percentage', true);
    }

    $stored = get_user_meta($user_id, 'tfp_program_status', true);

    if ($state['has_paid'] && $cohort_id) {
        $state['status'] = 'enrolled';
    } elseif ($state['fa_status'] === 'approved' || $stored === 'invited') {
        $state['status'] = 'invited';
    } elseif ($state['fa_status'] === 'pending') {
        // Applied for financial aid, awaiting admin approval.
        $state['status'] = 'waitlisted';
    } else {
        // Registered but hasn't paid and has no pending/approved aid
        // application (never applied, or was rejected).
        $state['status'] = 'unpaid';
    }

    return $state;
}

function tfp_dashboard_avatar($size = 40)
{
    $user_id = get_current_user_id();

    $custom_avatar_id = get_user_meta($user_id, 'tfp_custom_avatar_id', true);
    if ($custom_avatar_id && wp_attachment_is_image($custom_avatar_id)) {
        return wp_get_attachment_image($custom_avatar_id, [$size, $size], false, [
            'class' => 'tfp-dash-avatar-img',
            'alt'   => tfp_dashboard_user_name(),
        ]);
    }

    return get_avatar($user_id, $size, '', tfp_dashboard_user_name(), ['class' => 'tfp-dash-avatar-img']);
}

function tfp_dashboard_logout_url()
{
    return wp_logout_url(home_url());
}

/**
 * Avatar upload is a nice-to-have, not something most users will discover
 * (it's a separate click inside the Profile form). It shouldn't block the
 * Profile task card from ever reaching 100% just because no custom photo
 * was uploaded. This hooks the filter tfp-authentication's
 * tfp_get_profile_completion() already exposes — tfp-authentication itself
 * is never modified.
 */
add_filter('tfp_profile_completion_fields', function ($fields) {
    unset($fields['avatar']);
    return $fields;
});

/**
 * Is the given user a staff member (can see/manage all tickets)?
 * Administrators and Shop Managers are treated as staff. If a
 * "facilitator" role exists on the site, that role is staff too.
 */
function tfp_dashboard_user_is_staff($user_id = null)
{
    $user_id = $user_id ?: get_current_user_id();
    if (!$user_id) {
        return false;
    }

    if (user_can($user_id, 'manage_options') || user_can($user_id, 'manage_woocommerce')) {
        return true;
    }

    $user = get_userdata($user_id);
    if ($user && in_array('facilitator', (array) $user->roles, true)) {
        return true;
    }

    return apply_filters('tfp_dashboard_user_is_staff', false, $user_id);
}

/**
 * Render a small circular progress ring (SVG). Reused by Home task cards
 * and can be reused by future Grades/Mastery cards too.
 */
function tfp_dashboard_render_progress_ring($percent, $size = 72, $stroke = 6)
{
    $percent   = max(0, min(100, (int) $percent));
    $radius    = ($size - $stroke) / 2;
    $circumference = 2 * M_PI * $radius;
    $offset    = $circumference - ($percent / 100) * $circumference;
    $center    = $size / 2;

    ob_start();
    ?>
    <svg class="tfp-dash-ring" width="<?php echo esc_attr($size); ?>" height="<?php echo esc_attr($size); ?>" viewBox="0 0 <?php echo esc_attr($size); ?> <?php echo esc_attr($size); ?>">
        <circle
            class="tfp-dash-ring__track"
            cx="<?php echo esc_attr($center); ?>"
            cy="<?php echo esc_attr($center); ?>"
            r="<?php echo esc_attr($radius); ?>"
            stroke-width="<?php echo esc_attr($stroke); ?>"
            fill="none"
        />
        <circle
            class="tfp-dash-ring__progress"
            cx="<?php echo esc_attr($center); ?>"
            cy="<?php echo esc_attr($center); ?>"
            r="<?php echo esc_attr($radius); ?>"
            stroke-width="<?php echo esc_attr($stroke); ?>"
            fill="none"
            stroke-dasharray="<?php echo esc_attr($circumference); ?>"
            stroke-dashoffset="<?php echo esc_attr($offset); ?>"
            transform="rotate(-90 <?php echo esc_attr($center); ?> <?php echo esc_attr($center); ?>)"
        />
        <text x="50%" y="50%" class="tfp-dash-ring__label" text-anchor="middle" dominant-baseline="central">
            <?php echo esc_html($percent); ?>%
        </text>
    </svg>
    <?php
    return ob_get_clean();
}

/**
 * Consistent breadcrumb + page heading block used at the top of every
 * dashboard page.
 */
function tfp_dashboard_render_page_header($crumb, $title, $subtitle = '')
{
    ?>
    <div class="tfp-dash-pageheader">
        <button type="button" class="tfp-dash-pageheader__mobile-toggle" data-tfp-sidebar-toggle aria-label="<?php esc_attr_e('Open dashboard menu', 'tfp-dashboard'); ?>">
            <?php echo tfp_dashboard_icon('toggle'); ?>
        </button>
        <div class="tfp-dash-pageheader__crumb">
            <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Discipleship', 'tfp-dashboard'); ?></a>
            <span aria-hidden="true">/</span>
            <span><?php echo esc_html($crumb); ?></span>
        </div>
        <h2 class="tfp-dash-pageheader__title"><?php echo wp_kses_post($title); ?></h2>
        <?php if ($subtitle) : ?>
            <p class="tfp-dash-pageheader__subtitle"><?php echo esc_html($subtitle); ?></p>
        <?php endif; ?>
    </div>
    <?php
}
