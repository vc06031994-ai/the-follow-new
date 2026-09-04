<?php
if (!defined('ABSPATH')) exit;

// Add Meta Box
add_action('add_meta_boxes', function () {
    add_meta_box(
        'tfp_financial_aid_details',
        __('Application Details & Approval', 'tfp-dashboard'),
        'tfp_financial_aid_meta_box_html',
        'tfp_financial_aid',
        'normal',
        'high'
    );
});

function tfp_financial_aid_meta_box_html($post) {
    wp_nonce_field('tfp_financial_aid_save_meta', 'tfp_financial_aid_meta_nonce');

    $fields = tfp_financial_aid_field_map();
    $status = get_post_meta($post->ID, '_tfp_status', true) ?: 'pending';
    $discount = get_post_meta($post->ID, '_tfp_discount_percentage', true) ?: '100';
    $generated_coupon = get_post_meta($post->ID, '_tfp_generated_coupon', true);

    // The discount is editable right up until the student enrolls (pays). Once
    // they have, the price is locked into their order, so we freeze the field.
    $student_id = (int) get_post_meta($post->ID, '_tfp_student_id', true);
    $locked     = tfp_financial_aid_student_enrolled($student_id);

    echo '<table class="form-table"><tbody>';
    
    // Display Applicant Info
    foreach ($fields as $key => $config) {
        $value = get_post_meta($post->ID, '_tfp_' . $key, true);
        echo '<tr>';
        echo '<th scope="row">' . esc_html($config['label']) . '</th>';
        echo '<td>' . esc_html($value !== '' ? $value : '—') . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';

    echo '<hr style="margin: 20px 0;">';
    echo '<h3>' . __('Approval Settings', 'tfp-dashboard') . '</h3>';

    echo '<table class="form-table"><tbody>';
    
    // Status
    echo '<tr>';
    echo '<th scope="row"><label for="tfp_status">' . __('Status', 'tfp-dashboard') . '</label></th>';
    echo '<td>';
    echo '<select name="tfp_status" id="tfp_status">';
    foreach (tfp_financial_aid_statuses() as $val => $label) {
        $selected = selected($status, $val, false);
        echo '<option value="' . esc_attr($val) . '" ' . $selected . '>' . esc_html($label) . '</option>';
    }
    echo '</select>';
    echo '</td>';
    echo '</tr>';

    // Discount Percentage
    echo '<tr>';
    echo '<th scope="row"><label for="tfp_discount_percentage">' . __('Discount Percentage (%)', 'tfp-dashboard') . '</label></th>';
    echo '<td>';
    echo '<input type="number" name="tfp_discount_percentage" id="tfp_discount_percentage" value="' . esc_attr($discount) . '" min="1" max="100" class="small-text"' . ($locked ? ' readonly' : '') . '> %';
    if ($locked) {
        echo '<p class="description" style="color:#a00;">' . __('This student has already enrolled — the discount is locked and can no longer be changed.', 'tfp-dashboard') . '</p>';
    } else {
        echo '<p class="description">' . __('This discount is applied automatically to the student\'s checkout once approved. You can change it any time until they enrol — the coupon updates instantly.', 'tfp-dashboard') . '</p>';
    }
    echo '</td>';
    echo '</tr>';

    if ($generated_coupon) {
        echo '<tr>';
        echo '<th scope="row">' . __('Generated Coupon', 'tfp-dashboard') . '</th>';
        echo '<td><strong>' . esc_html($generated_coupon) . '</strong></td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
}

// Save Meta Box Data
add_action('save_post_tfp_financial_aid', function ($post_id) {
    if (!isset($_POST['tfp_financial_aid_meta_nonce']) || !wp_verify_nonce($_POST['tfp_financial_aid_meta_nonce'], 'tfp_financial_aid_save_meta')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $old_status = get_post_meta($post_id, '_tfp_status', true);
    $old_discount = (int) get_post_meta($post_id, '_tfp_discount_percentage', true);
    $new_status = sanitize_text_field($_POST['tfp_status'] ?? 'pending');
    $discount_percentage = absint($_POST['tfp_discount_percentage'] ?? 100);

    $student_id = (int) get_post_meta($post_id, '_tfp_student_id', true);

    // Policy: the aid % is editable only until the student enrols (pays). After
    // that the amount is baked into their completed order, so we ignore any
    // posted change and keep the value that was already approved. The meta box
    // renders the field read-only in this state too — this is the server-side
    // guarantee behind it.
    $locked = tfp_financial_aid_student_enrolled($student_id);
    if ($locked && $old_discount) {
        $discount_percentage = $old_discount;
    }

    update_post_meta($post_id, '_tfp_status', $new_status);
    update_post_meta($post_id, '_tfp_discount_percentage', $discount_percentage);

    if ($old_status !== 'approved' && $new_status === 'approved') {
        // First approval: invite the student + generate the coupon.
        tfp_financial_aid_process_approval($post_id, $discount_percentage);
    } elseif ($new_status === 'approved' && !$locked && $discount_percentage !== $old_discount) {
        // Already approved and the admin changed the % before the student
        // enrolled: sync the existing coupon so the cart reflects the new
        // amount (not just the label). This is the fix for "changed 100% → 50%
        // but checkout still showed $0".
        $user = get_userdata($student_id);
        if ($user) {
            tfp_financial_aid_sync_coupon($post_id, $discount_percentage, $user);
        }
    } elseif ($old_status !== 'rejected' && $new_status === 'rejected') {
        tfp_financial_aid_process_rejection($post_id);
    }
});

/**
 * Has this student already enrolled (paid) into the program? Once enrolled, the
 * aid amount is locked — the price is settled in their completed order and can
 * no longer change. tfp_program_status is set to 'enrolled' by the cohort
 * enrolment hook (course-ajax.php) for every paid program order.
 */
function tfp_financial_aid_student_enrolled($student_id) {
    if (!$student_id) {
        return false;
    }
    return get_user_meta($student_id, 'tfp_program_status', true) === 'enrolled';
}

/**
 * Keep the WooCommerce coupon in sync with the approved discount percentage.
 *
 * Creates the FA coupon the first time, and on every later change UPDATES the
 * existing coupon's amount in place (same code stays valid; a cart that already
 * has it applied picks up the new amount on its next recalculation). Without
 * this, editing the discount % after approval only changed the label — the
 * underlying coupon kept its original amount, so a "100% → 50%" edit still
 * discounted 100% and the checkout total stayed $0.
 *
 * Returns the coupon code, or '' if WooCommerce is unavailable.
 */
function tfp_financial_aid_sync_coupon($post_id, $discount_percentage, $user) {
    if (!class_exists('WooCommerce') || !$user) {
        return '';
    }

    $discount_percentage = max(1, min(100, (int) $discount_percentage));
    $existing_code = get_post_meta($post_id, '_tfp_generated_coupon', true);

    if ($existing_code) {
        $coupon_id = wc_get_coupon_id_by_code($existing_code);
        if ($coupon_id) {
            // Update the existing coupon in place.
            $coupon = new WC_Coupon($coupon_id);
            $coupon->set_discount_type('percent');
            $coupon->set_amount($discount_percentage);
            $coupon->set_email_restrictions([$user->user_email]);
            $coupon->save();
            return $existing_code;
        }
        // Meta points at a coupon that no longer exists — fall through and mint a fresh one.
    }

    $coupon_code = 'FA-' . strtoupper(wp_generate_password(8, false, false));

    $coupon = new WC_Coupon();
    $coupon->set_code($coupon_code);
    $coupon->set_discount_type('percent');
    $coupon->set_amount($discount_percentage);
    $coupon->set_email_restrictions([$user->user_email]);
    $coupon->set_individual_use(true);
    $coupon->set_usage_limit(1);
    $coupon->save();

    update_post_meta($post_id, '_tfp_generated_coupon', $coupon_code);
    return $coupon_code;
}

function tfp_financial_aid_process_approval($post_id, $discount_percentage) {
    $student_id = get_post_meta($post_id, '_tfp_student_id', true);
    $user = get_userdata($student_id);
    $program_id = get_post_meta($post_id, '_tfp_program_id', true); // Currently not forcing course restriction to keep it simple, but could.

    if (!$user) return;

    // Financial aid approved → the student is now invited to enroll (unless
    // they've already paid + chosen a cohort, in which case leave them
    // enrolled). tfp_dashboard_get_program_state() surfaces this on Home.
    if (get_user_meta($student_id, 'tfp_program_status', true) !== 'enrolled') {
        update_user_meta($student_id, 'tfp_program_status', 'invited');
    }

    // Generate (or refresh) the discount coupon at the approved percentage.
    tfp_financial_aid_sync_coupon($post_id, $discount_percentage, $user);

    // Send Email
    $site_name = get_bloginfo('name');
    $subject = sprintf(__('[%s] Your Financial Aid Application is Approved!', 'tfp-dashboard'), $site_name);
    
    $body = sprintf(__("Hi %s,\n\nGreat news! Your financial aid application has been approved with a %d%% discount.\n\nWhen you proceed to checkout using this email address (%s), your discount will be automatically applied.\n\nThank you,\n%s", 'tfp-dashboard'), 
        $user->first_name ?: $user->display_name,
        $discount_percentage,
        $user->user_email,
        $site_name
    );

    wp_mail($user->user_email, $subject, $body);
}

function tfp_financial_aid_process_rejection($post_id) {
    $student_id = get_post_meta($post_id, '_tfp_student_id', true);
    $user = get_userdata($student_id);

    if (!$user) return;

    // Send Email
    $site_name = get_bloginfo('name');
    $subject = sprintf(__('[%s] Update on your Financial Aid Application', 'tfp-dashboard'), $site_name);
    
    $body = sprintf(__("Hi %s,\n\nThank you for applying for financial aid. Unfortunately, we are unable to approve your application at this time.\n\nIf you have any questions, please contact our support team.\n\nThank you,\n%s", 'tfp-dashboard'), 
        $user->first_name ?: $user->display_name,
        $site_name
    );

    wp_mail($user->user_email, $subject, $body);
}

// -----------------------------------------------------------------------------
// CSV Export Functionality
// -----------------------------------------------------------------------------

// 1. Add "Export CSV" button to the CPT list table
add_action('restrict_manage_posts', function ($post_type) {
    if ($post_type === 'tfp_financial_aid') {
        echo '<input type="submit" name="tfp_export_financial_aid_csv" id="tfp_export_financial_aid_csv" class="button button-primary" value="' . esc_attr__('Export to CSV', 'tfp-dashboard') . '">';
    }
});

// 2. Handle the CSV Generation
add_action('admin_init', function () {
    if (isset($_GET['tfp_export_financial_aid_csv']) && isset($_GET['post_type']) && $_GET['post_type'] === 'tfp_financial_aid') {
        
        // Ensure user has permission
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        // Get all fields map to build headers
        $fields = tfp_financial_aid_field_map();
        
        // Define CSV Headers
        $headers = ['Date Submitted', 'Status', 'Applicant Name', 'Email'];
        foreach ($fields as $key => $config) {
            $headers[] = $config['label'];
        }
        $headers[] = 'Discount %';
        $headers[] = 'Generated Coupon';

        // Set Headers for CSV Download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=financial-aid-applications-' . date('Y-m-d') . '.csv');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        
        // Add BOM to fix UTF-8 in Excel
        fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));
        
        fputcsv($output, $headers);

        // Fetch all financial aid posts
        $args = [
            'post_type'      => 'tfp_financial_aid',
            'posts_per_page' => -1,
            'post_status'    => 'any',
        ];
        $applications = get_posts($args);

        foreach ($applications as $app) {
            $student_id = get_post_meta($app->ID, '_tfp_student_id', true);
            $user = get_userdata($student_id);
            
            $status = get_post_meta($app->ID, '_tfp_status', true) ?: 'pending';
            $discount = get_post_meta($app->ID, '_tfp_discount_percentage', true);
            $coupon = get_post_meta($app->ID, '_tfp_generated_coupon', true);

            $row = [
                get_the_date('Y-m-d H:i:s', $app->ID),
                ucfirst($status),
                $user ? $user->display_name : 'Unknown',
                $user ? $user->user_email : 'Unknown',
            ];

            // Append mapped fields data
            foreach ($fields as $key => $config) {
                $value = get_post_meta($app->ID, '_tfp_' . $key, true);
                
                // Program ID ko Program Name me convert karein
                if ($key === 'program_id' && !empty($value)) {
                    $program_title = get_the_title($value);
                    if ($program_title) {
                        $value = $program_title;
                    }
                }
                
                $row[] = $value !== '' ? $value : '';
            }
            
            $row[] = $discount ? $discount . '%' : '';
            $row[] = $coupon ?: '';

            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }
});
