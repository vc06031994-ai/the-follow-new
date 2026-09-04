<?php
/**
 * Cohort admin meta box — schedule, seats, facilitator, course/product link
 * and an optional price override. Pattern mirrors financial-aid/admin.php.
 */

if (!defined('ABSPATH')) exit;

add_action('add_meta_boxes', function () {
    add_meta_box(
        'tfp_cohort_details',
        __('Cohort Details', 'tfp-dashboard'),
        'tfp_cohort_meta_box_html',
        'tfp_cohort',
        'normal',
        'high'
    );
});

function tfp_cohort_meta_box_html($post)
{
    wp_nonce_field('tfp_cohort_save_meta', 'tfp_cohort_meta_nonce');

    $course      = (int) get_post_meta($post->ID, '_related_course', true);
    $product     = (int) get_post_meta($post->ID, '_related_product', true);
    $start_date  = get_post_meta($post->ID, '_start_date', true);
    $schedule    = get_post_meta($post->ID, '_schedule_text', true);
    $facilitator = get_post_meta($post->ID, '_facilitator', true);
    $seats_total = get_post_meta($post->ID, '_seats_total', true);
    $price       = get_post_meta($post->ID, '_price', true);

    $courses = get_posts([
        'post_type'      => 'sfwd-courses',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ]);

    echo '<table class="form-table" role="presentation"><tbody>';

    // Related LearnDash course.
    echo '<tr>';
    echo '<th scope="row"><label for="tfp_cohort_course">' . esc_html__('Program (Course)', 'tfp-dashboard') . '</label></th>';
    echo '<td><select name="tfp_cohort_course" id="tfp_cohort_course">';
    echo '<option value="0">' . esc_html__('— Select a course —', 'tfp-dashboard') . '</option>';
    foreach ($courses as $c) {
        echo '<option value="' . esc_attr($c->ID) . '" ' . selected($course, $c->ID, false) . '>' . esc_html($c->post_title) . '</option>';
    }
    echo '</select>';
    echo '<p class="description">' . esc_html__('The LearnDash course this cohort runs. Course access is granted automatically on purchase via the linked WooCommerce product.', 'tfp-dashboard') . '</p>';
    echo '</td></tr>';

    // Optional product override.
    echo '<tr>';
    echo '<th scope="row"><label for="tfp_cohort_product">' . esc_html__('Product override (optional)', 'tfp-dashboard') . '</label></th>';
    echo '<td><input type="number" name="tfp_cohort_product" id="tfp_cohort_product" value="' . esc_attr($product) . '" class="small-text" min="0" step="1"> ';
    echo '<span class="description">' . esc_html__('WooCommerce product ID. Leave blank to use the product linked to the course.', 'tfp-dashboard') . '</span>';
    echo '</td></tr>';

    // Start date.
    echo '<tr>';
    echo '<th scope="row"><label for="tfp_cohort_start_date">' . esc_html__('Start date', 'tfp-dashboard') . '</label></th>';
    echo '<td><input type="date" name="tfp_cohort_start_date" id="tfp_cohort_start_date" value="' . esc_attr($start_date) . '"></td>';
    echo '</tr>';

    // Schedule text.
    echo '<tr>';
    echo '<th scope="row"><label for="tfp_cohort_schedule">' . esc_html__('Meeting schedule', 'tfp-dashboard') . '</label></th>';
    echo '<td><input type="text" name="tfp_cohort_schedule" id="tfp_cohort_schedule" class="regular-text" value="' . esc_attr($schedule) . '" placeholder="' . esc_attr__('Thursdays 6:00 PM PT', 'tfp-dashboard') . '"></td>';
    echo '</tr>';

    // Facilitator.
    echo '<tr>';
    echo '<th scope="row"><label for="tfp_cohort_facilitator">' . esc_html__('Facilitator', 'tfp-dashboard') . '</label></th>';
    echo '<td><input type="text" name="tfp_cohort_facilitator" id="tfp_cohort_facilitator" class="regular-text" value="' . esc_attr($facilitator) . '" placeholder="' . esc_attr__('Pastor Jane Doe', 'tfp-dashboard') . '"></td>';
    echo '</tr>';

    // Seats total.
    echo '<tr>';
    echo '<th scope="row"><label for="tfp_cohort_seats_total">' . esc_html__('Total seats', 'tfp-dashboard') . '</label></th>';
    echo '<td><input type="number" name="tfp_cohort_seats_total" id="tfp_cohort_seats_total" value="' . esc_attr($seats_total) . '" class="small-text" min="0" step="1"> ';
    echo '<span class="description">' . esc_html__('Capacity for this cohort. 0 = unlimited. Seats taken are counted automatically from paid enrollments.', 'tfp-dashboard') . '</span>';
    echo '</td></tr>';

    // Price override.
    echo '<tr>';
    echo '<th scope="row"><label for="tfp_cohort_price">' . esc_html__('Price override (optional)', 'tfp-dashboard') . '</label></th>';
    echo '<td><input type="number" name="tfp_cohort_price" id="tfp_cohort_price" value="' . esc_attr($price) . '" class="small-text" min="0" step="0.01"> ';
    echo '<span class="description">' . esc_html__('Leave blank to use the product price.', 'tfp-dashboard') . '</span>';
    echo '</td></tr>';

    echo '</tbody></table>';

    // A quick read-out of the live seat count for admins.
    if (function_exists('tfp_cohort_seats_taken')) {
        $taken = tfp_cohort_seats_taken($post->ID);
        $total = (int) $seats_total;
        echo '<p><strong>' . esc_html__('Seats taken (live):', 'tfp-dashboard') . '</strong> ' . esc_html($taken) . ($total > 0 ? ' / ' . esc_html($total) : '') . '</p>';
    }
}

add_action('save_post_tfp_cohort', function ($post_id) {
    if (!isset($_POST['tfp_cohort_meta_nonce']) || !wp_verify_nonce($_POST['tfp_cohort_meta_nonce'], 'tfp_cohort_save_meta')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    update_post_meta($post_id, '_related_course', absint($_POST['tfp_cohort_course'] ?? 0));

    $product = absint($_POST['tfp_cohort_product'] ?? 0);
    if ($product) {
        update_post_meta($post_id, '_related_product', $product);
    } else {
        delete_post_meta($post_id, '_related_product');
    }

    update_post_meta($post_id, '_start_date', sanitize_text_field($_POST['tfp_cohort_start_date'] ?? ''));
    update_post_meta($post_id, '_schedule_text', sanitize_text_field($_POST['tfp_cohort_schedule'] ?? ''));
    update_post_meta($post_id, '_facilitator', sanitize_text_field($_POST['tfp_cohort_facilitator'] ?? ''));
    update_post_meta($post_id, '_seats_total', absint($_POST['tfp_cohort_seats_total'] ?? 0));

    $price = $_POST['tfp_cohort_price'] ?? '';
    if ($price !== '' && is_numeric($price)) {
        update_post_meta($post_id, '_price', (float) $price);
    } else {
        delete_post_meta($post_id, '_price');
    }

    if (function_exists('tfp_cohort_flush_seats_cache')) {
        tfp_cohort_flush_seats_cache($post_id);
    }
});
