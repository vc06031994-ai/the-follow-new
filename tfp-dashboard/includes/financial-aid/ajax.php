<?php
if (!defined('ABSPATH')) exit;

/**
 * Fields captured on the Financial Assistance application form.
 * Kept as one map so the form, the sanitizer, and the email templates
 * all stay in sync — add a row here to add a new field everywhere.
 */
function tfp_financial_aid_field_map()
{
    return [
        'first_name'          => ['label' => __('First Name', 'tfp-dashboard'), 'type' => 'text', 'required' => true],
        'last_name'           => ['label' => __('Last Name', 'tfp-dashboard'), 'type' => 'text', 'required' => true],
        'phone'               => ['label' => __('Phone Number', 'tfp-dashboard'), 'type' => 'text', 'required' => true],
        'address'             => ['label' => __('Address/Location', 'tfp-dashboard'), 'type' => 'text', 'required' => false],
        'country'             => ['label' => __('Country', 'tfp-dashboard'), 'type' => 'text', 'required' => false],
        'program_id'          => ['label' => __('Program', 'tfp-dashboard'), 'type' => 'int', 'required' => true],
        'already_registered'  => ['label' => __('Already Registered?', 'tfp-dashboard'), 'type' => 'yesno', 'required' => true],
        'aid_type'            => ['label' => __('Full or Partial Aid?', 'tfp-dashboard'), 'type' => 'text', 'required' => true],
        'contribution_amount' => ['label' => __('Contribution Amount', 'tfp-dashboard'), 'type' => 'text', 'required' => false],
        'financial_situation' => ['label' => __('Current Financial Situation', 'tfp-dashboard'), 'type' => 'textarea', 'required' => true],
        'has_job'             => ['label' => __('Currently Employed?', 'tfp-dashboard'), 'type' => 'yesno', 'required' => true],
        'job_details'         => ['label' => __('Job Details', 'tfp-dashboard'), 'type' => 'text', 'required' => false],
        'household_size'      => ['label' => __('Household Size', 'tfp-dashboard'), 'type' => 'text', 'required' => false],
        'supporting_others'   => ['label' => __('Supporting Others Financially?', 'tfp-dashboard'), 'type' => 'yesno', 'required' => true],
        'received_aid_before' => ['label' => __('Received Aid Before?', 'tfp-dashboard'), 'type' => 'yesno', 'required' => true],
        'referral_code'       => ['label' => __('Referral Code', 'tfp-dashboard'), 'type' => 'text', 'required' => false],
        'additional_comments' => ['label' => __('Additional Comments', 'tfp-dashboard'), 'type' => 'textarea', 'required' => false],
    ];
}

function tfp_financial_aid_sanitize_field($key, $type)
{
    if (!isset($_POST[$key])) {
        return '';
    }
    $raw = wp_unslash($_POST[$key]);

    switch ($type) {
        case 'int':
            return absint($raw);
        case 'textarea':
            return sanitize_textarea_field($raw);
        case 'yesno':
            $raw = sanitize_text_field($raw);
            return in_array($raw, ['yes', 'no'], true) ? $raw : '';
        default:
            return sanitize_text_field($raw);
    }
}

add_action('wp_ajax_tfp_financial_aid_submit', function () {
    if (!is_user_logged_in()) {
        wp_send_json(['success' => false, 'message' => __('You must be logged in.', 'tfp-dashboard')], 403);
    }

    if (!isset($_POST['tfp_financial_aid_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tfp_financial_aid_nonce'])), 'tfp_financial_aid_nonce')) {
        wp_send_json(['success' => false, 'message' => __('Security check failed.', 'tfp-dashboard')], 403);
    }

    $user    = wp_get_current_user();
    $fields  = tfp_financial_aid_field_map();
    $values  = [];
    $missing = [];

    foreach ($fields as $key => $config) {
        $value = tfp_financial_aid_sanitize_field($key, $config['type']);
        if ($config['required'] && $value === '') {
            $missing[] = $config['label'];
        }
        $values[$key] = $value;
    }

    if (!empty($missing)) {
        wp_send_json([
            'success' => false,
            'message' => sprintf(
                /* translators: list of missing field labels */
                __('Please fill in: %s', 'tfp-dashboard'),
                implode(', ', $missing)
            ),
        ], 400);
    }

    $post_title = sprintf('%s %s — %s', $values['first_name'], $values['last_name'], date_i18n('Y-m-d'));

    $application_id = wp_insert_post([
        'post_type'   => 'tfp_financial_aid',
        'post_title'  => $post_title,
        'post_status' => 'publish',
    ], true);

    if (is_wp_error($application_id)) {
        wp_send_json(['success' => false, 'message' => __('Could not submit application. Please try again.', 'tfp-dashboard')], 500);
    }

    update_post_meta($application_id, '_tfp_student_id', $user->ID);
    update_post_meta($application_id, '_tfp_status', 'pending');

    foreach ($values as $key => $value) {
        update_post_meta($application_id, '_tfp_' . $key, $value);
    }

    // Optional recommendation letter upload.
    if (!empty($_FILES['recommendation_letter']['name'])) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachment_id = media_handle_upload('recommendation_letter', $application_id);
        if (!is_wp_error($attachment_id)) {
            update_post_meta($application_id, '_tfp_recommendation_letter_id', $attachment_id);
        }
    }

    tfp_financial_aid_send_notifications($application_id, $user, $values);

    wp_send_json(['success' => true, 'application_id' => $application_id]);
});

/**
 * Emails: one to the admin (full application details), one to the
 * applicant (confirmation only — no sensitive data repeated back).
 */
function tfp_financial_aid_send_notifications($application_id, $user, $values)
{
    $site_name = get_bloginfo('name');

    // --- Admin email -----------------------------------------------------
    $admin_email = get_option('admin_email');
    $admin_subject = sprintf(__('[%s] New Financial Aid Application', 'tfp-dashboard'), $site_name);

    $admin_body = sprintf(__('A new financial assistance application was submitted by %s (%s).', 'tfp-dashboard'), $user->display_name, $user->user_email) . "\n\n";

    foreach (tfp_financial_aid_field_map() as $key => $config) {
        $admin_body .= $config['label'] . ': ' . ($values[$key] !== '' ? $values[$key] : '—') . "\n";
    }

    $admin_body .= "\n" . sprintf(__('Review it in wp-admin: %s', 'tfp-dashboard'), admin_url('post.php?post=' . $application_id . '&action=edit'));

    wp_mail($admin_email, $admin_subject, $admin_body);

    // --- Applicant confirmation email ------------------------------------
    $user_subject = sprintf(__('[%s] We received your Financial Aid application', 'tfp-dashboard'), $site_name);
    $user_body = sprintf(
        __("Hi %s,\n\nThank you for your application. Our team will review your request and email you with next steps within 3–5 business days. Please keep an eye on your inbox (and spam folder).\n\n— %s", 'tfp-dashboard'),
        $user->first_name ?: $user->display_name,
        $site_name
    );

    wp_mail($user->user_email, $user_subject, $user_body);
}
