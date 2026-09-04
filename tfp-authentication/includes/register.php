<?php
if (!defined('ABSPATH'))
    exit;

function tfp_auth_get_program_options()
{
    $args = [
        'post_type' => 'sfwd-courses',
        'post_status' => 'publish',
        'orderby' => ['menu_order' => 'ASC', 'title' => 'ASC'],
        'posts_per_page' => -1,
        'fields' => 'ids',
    ];

    $course_ids = get_posts($args);
    if (!is_array($course_ids)) {
        return [];
    }

    return $course_ids;
}

function tfp_auth_add_notice($message, $type = 'error')
{
    if (function_exists('wc_add_notice')) {
        wc_add_notice($message, $type);
    }
}

function tfp_auth_clear_registration_auth_state()
{
    if (function_exists('wp_clear_auth_cookie')) {
        wp_clear_auth_cookie();
    }

    if (function_exists('wp_set_current_user')) {
        wp_set_current_user(0);
    }
}

/**
 * Keep registration flow in the intended UX: do not auto-login a newly created
 * user. The old flow was register -> thank-you/login popup -> manual login ->
 * dashboard. Returning early here preserves that flow and prevents an immediate
 * redirect to the dashboard on successful signup.
 */
function tfp_auth_login_new_user($uid)
{
    return false;
}

/**
 * Legacy redirect target retained for compatibility; registration should no
 * longer auto-send the user to the dashboard without a login step.
 */
function tfp_auth_registration_redirect_url()
{
    return '';
}

function tfp_auth_process_registration($posted_values = [], $add_notices = true)
{
    $posted_values = is_array($posted_values) ? $posted_values : [];

    $first = isset($posted_values['first_name']) ? sanitize_text_field(wp_unslash($posted_values['first_name'])) : '';
    $last = isset($posted_values['last_name']) ? sanitize_text_field(wp_unslash($posted_values['last_name'])) : '';
    $email = isset($posted_values['email']) ? sanitize_email(wp_unslash($posted_values['email'])) : '';
    $password = isset($posted_values['password']) ? wp_unslash($posted_values['password']) : '';
    $phone = isset($posted_values['billing_phone']) ? sanitize_text_field(wp_unslash($posted_values['billing_phone'])) : '';
    $program_choice = isset($posted_values['tfp_program_choice']) ? intval($posted_values['tfp_program_choice']) : 0;
    $referral_code = isset($posted_values['tfp_referral_code']) ? sanitize_text_field(wp_unslash($posted_values['tfp_referral_code'])) : '';
    $newsletter = !empty($posted_values['tfp_newsletter']) ? 'yes' : 'no';
    $errors = [];

    if ($first === '') {
        $errors[] = __('First name is required.', 'tfp-authentication');
    }
    if ($last === '') {
        $errors[] = __('Last name is required.', 'tfp-authentication');
    }
    if ($email === '') {
        $errors[] = __('Email is required.', 'tfp-authentication');
    } elseif (!is_email($email)) {
        $errors[] = __('Please enter a valid email address.', 'tfp-authentication');
    }
    if ($password === '') {
        $errors[] = __('Password is required.', 'tfp-authentication');
    } elseif (strlen($password) < 8) {
        $errors[] = __('Password must contain at least 8 characters.', 'tfp-authentication');
    }
    if ($phone === '') {
        $errors[] = __('Phone number is required.', 'tfp-authentication');
    }
    if ($program_choice <= 0 || !get_post($program_choice) || get_post_status($program_choice) !== 'publish') {
        $errors[] = __('Please select a program.', 'tfp-authentication');
    }

    if (!empty($errors)) {
        if ($add_notices) {
            foreach ($errors as $error) {
                tfp_auth_add_notice($error);
            }
        }
        return ['success' => false, 'errors' => $errors];
    }

    if (email_exists($email)) {
        $message = __('This email address is already registered. Please log in to continue.', 'tfp-authentication');
        if ($add_notices) {
            tfp_auth_add_notice($message);
        }
        return ['success' => false, 'errors' => [$message]];
    }

    $base = sanitize_user(strtolower($first . '.' . $last), true);
    if (empty($base)) {
        $base = sanitize_user(current(explode('@', $email)), true);
    }
    $username = $base;
    $i = 1;
    while (username_exists($username)) {
        $username = $base . $i;
        $i++;
    }

    $uid = wc_create_new_customer($email, $username, $password);

    if (is_wp_error($uid)) {
        $message = $uid->get_error_message();
        if ($add_notices) {
            tfp_auth_add_notice($message);
        }
        return ['success' => false, 'errors' => [$message]];
    }

    wp_update_user([
        'ID' => $uid,
        'first_name' => $first,
        'last_name' => $last,
        'display_name' => trim($first . ' ' . $last),
        'nickname' => trim($first . ' ' . $last),
    ]);

    update_user_meta($uid, 'first_name', $first);
    update_user_meta($uid, 'last_name', $last);
    update_user_meta($uid, 'billing_phone', $phone);
    update_user_meta($uid, 'tfp_program_choice', $program_choice);
    update_user_meta($uid, 'tfp_referral_code', $referral_code);
    update_user_meta($uid, 'tfp_newsletter', $newsletter);

    // A freshly-registered student starts "unpaid". From the dashboard Home
    // they can either choose a cohort and pay, or apply for financial aid
    // (which moves them to waitlisted → invited once admin approves). The
    // status is derived by tfp_dashboard_get_program_state().
    update_user_meta($uid, 'tfp_program_status', 'unpaid');

    // Do not auto-login the user after registration. Keep them logged out so the
    // intended flow remains: register -> thank-you message -> login popup ->
    // dashboard after successful authentication.
    return [
        'success'  => true,
        'message'  => __('Account created successfully.', 'tfp-authentication'),
        'redirect' => '',
        'user_id'  => $uid,
    ];
}

function tfp_auth_handle_register_ajax()
{
    $is_register_request = !empty($_POST['tfp_register_submit']) || (isset($_POST['action']) && 'tfp_register' === sanitize_text_field(wp_unslash($_POST['action'])));

    if (!$is_register_request) {
        wp_send_json(['success' => false, 'errors' => [__('Registration request is invalid.', 'tfp-authentication')]], 400);
    }

    if (!isset($_POST['tfp_register_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tfp_register_nonce'])), 'tfp-register-action')) {
        tfp_auth_add_notice(__('Security verification failed.', 'tfp-authentication'));
        wp_send_json(['success' => false, 'errors' => [__('Security verification failed.', 'tfp-authentication')]], 200);
    }

    if (function_exists('wc_clear_notices')) {
        wc_clear_notices();
    }

    $result = [
        'success' => false,
        'errors' => [__('We could not create your account right now. Please try again.', 'tfp-authentication')],
    ];

    try {
        $result = tfp_auth_process_registration($_POST, true);
    } catch (Throwable $e) {
        $message = $e->getMessage();
        if (function_exists('error_log')) {
            error_log('TFP registration error: ' . $message);
        }
        if (function_exists('wc_add_notice')) {
            wc_add_notice($message, 'error');
        }
        $result = [
            'success' => false,
            'errors' => [empty($message) ? __('We could not create your account right now. Please try again.', 'tfp-authentication') : $message],
        ];
    }

    if (!empty($result['success'])) {
        wp_send_json([
            'success'  => true,
            'message'  => $result['message'],
            'redirect' => isset($result['redirect']) ? $result['redirect'] : '',
        ], 200);
    }

    wp_send_json(['success' => false, 'errors' => $result['errors']], 200);
}

add_action('wp_ajax_tfp_register', 'tfp_auth_handle_register_ajax');
add_action('wp_ajax_nopriv_tfp_register', 'tfp_auth_handle_register_ajax');

function tfp_auth_render_register_form($posted_values = [])
{
    if (is_user_logged_in())
        return '<p>You are already logged in.</p>';
    if (get_option('woocommerce_enable_myaccount_registration') !== 'yes')
        return '<p>Registration disabled.</p>';

    $posted_values = is_array($posted_values) ? $posted_values : [];
    $first = isset($posted_values['first_name']) ? sanitize_text_field(wp_unslash($posted_values['first_name'])) : '';
    $last = isset($posted_values['last_name']) ? sanitize_text_field(wp_unslash($posted_values['last_name'])) : '';
    $email = isset($posted_values['email']) ? sanitize_email(wp_unslash($posted_values['email'])) : '';
    $phone = isset($posted_values['billing_phone']) ? sanitize_text_field(wp_unslash($posted_values['billing_phone'])) : '';
    $program_choice = isset($posted_values['tfp_program_choice']) ? sanitize_text_field(wp_unslash($posted_values['tfp_program_choice'])) : '';
    $referral_code = isset($posted_values['tfp_referral_code']) ? sanitize_text_field(wp_unslash($posted_values['tfp_referral_code'])) : '';
    $newsletter = isset($posted_values['tfp_newsletter']) ? 'yes' : 'no';

    tfp_auth_enqueue_styles();
    ob_start();
    if (function_exists('woocommerce_output_all_notices')) {
        woocommerce_output_all_notices();
    }
    ?>
    <div class="tfp-register-form-container">
        <form method="post" class="woocommerce-form woocommerce-form-register register tfp-register-form" novalidate>
            <div class="tfp-form-notices" aria-live="polite"></div>
            <p class="tfp-auth-register-field tfp-form-group"><label class="tfp-label" for="tfp_first_name">First Name <span
                        class="tfp-required"></span></label><input class="tfp-input" type="text" id="tfp_first_name"
                    name="first_name" value="<?php echo esc_attr($first); ?>"></p>
            <p class="tfp-auth-register-field tfp-form-group"><label class="tfp-label" for="tfp_last_name">Last Name <span
                        class="tfp-required"></span></label><input class="tfp-input" type="text" id="tfp_last_name"
                    name="last_name" value="<?php echo esc_attr($last); ?>"></p>
            <p class="tfp-auth-register-field tfp-form-group"><label class="tfp-label" for="tfp_email">Email <span
                        class="tfp-required"></span></label><input class="tfp-input" type="email" id="tfp_email"
                    name="email" value="<?php echo esc_attr($email); ?>"></p>
            <p class="tfp-auth-register-field tfp-form-group"><label class="tfp-label" for="tfp_password">Password <span
                        class="tfp-required"></span></label><input class="tfp-input" type="password" id="tfp_password"
                    name="password"></p>
            <p class="tfp-auth-register-field tfp-form-group"><label class="tfp-label" for="tfp_billing_phone">Phone Number
                    <span class="tfp-required"></span></label><input class="tfp-input" type="tel" id="tfp_billing_phone"
                    name="billing_phone" value="<?php echo esc_attr($phone); ?>"></p>
            <p class="tfp-auth-register-field tfp-form-group"><label class="tfp-label" for="tfp_program_choice">Program
                    Choice <span class="tfp-required"></span></label>
                <select class="tfp-select" id="tfp_program_choice" name="tfp_program_choice">
                    <option value="">Select a program</option>
                    <?php foreach (tfp_auth_get_program_options() as $course_id): ?>
                        <?php $course = get_post($course_id);
                        if (!$course)
                            continue; ?>
                        <option value="<?php echo esc_attr($course_id); ?>" <?php selected($program_choice, $course_id); ?>>
                            <?php echo esc_html(get_the_title($course_id)); ?></option>
                    <?php endforeach; ?>
                </select>
            </p>
            <p class="tfp-auth-register-field tfp-form-group"><label class="tfp-label" for="tfp_referral_code">Referral
                    Code</label><input class="tfp-input" type="text" id="tfp_referral_code" name="tfp_referral_code"
                    value="<?php echo esc_attr($referral_code); ?>"></p>
            <p class="tfp-auth-register-field"><label class="tfp-checkbox"><input type="checkbox" name="tfp_newsletter"
                        value="yes" <?php checked($newsletter, 'yes'); ?>><span class="tfp-checkbox-label">I agree to receive
                        emails from The Follow Project. I can unsubscribe anytime.</span></label></p>
            <?php wp_nonce_field('tfp-register-action', 'tfp_register_nonce'); ?>
            <p><button type="submit" name="tfp_register_submit" value="1"
                    class="tfp-btn tfp-btn-primary tfp-btn-lg tfp-w-100 tfp-register-submit">Register</button></p>
            <p class="tfp-footer-note">By proceeding ahead you agree to <span class="tfp-footer-link">Terms &amp;
                    Conditions</span> and <span class="tfp-footer-link">Privacy Policy</span></p>
        </form>
    </div>
    <?php return ob_get_clean();
}

add_shortcode('tfp_register_form', function ($atts = []) {
    $posted_values = [];
    if (!empty($_POST['tfp_register_submit'])) {
        $posted_values = [
            'first_name' => isset($_POST['first_name']) ? wp_unslash($_POST['first_name']) : '',
            'last_name' => isset($_POST['last_name']) ? wp_unslash($_POST['last_name']) : '',
            'email' => isset($_POST['email']) ? wp_unslash($_POST['email']) : '',
            'billing_phone' => isset($_POST['billing_phone']) ? wp_unslash($_POST['billing_phone']) : '',
            'tfp_program_choice' => isset($_POST['tfp_program_choice']) ? intval($_POST['tfp_program_choice']) : '',
            'tfp_referral_code' => isset($_POST['tfp_referral_code']) ? wp_unslash($_POST['tfp_referral_code']) : '',
            'tfp_newsletter' => !empty($_POST['tfp_newsletter']) ? 'yes' : 'no',
        ];
    }
    return tfp_auth_render_register_form($posted_values);
});

add_action('init', function () {

    if (defined('DOING_AJAX') && DOING_AJAX) {
        return;
    }

    if (empty($_POST['tfp_register_submit']))
        return;

    if (!isset($_POST['tfp_register_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tfp_register_nonce'])), 'tfp-register-action')) {
        tfp_auth_add_notice(__('Security verification failed.', 'tfp-authentication'));
        return;
    }

    if (function_exists('wc_clear_notices')) {
        wc_clear_notices();
    }

    $processed = tfp_auth_process_registration($_POST, true);
    if (!empty($processed['success'])) {
        // No-JS fallback: keep the user logged out and let the existing thank-you
        // / login popup flow handle the next step.
        return;
    }

});

