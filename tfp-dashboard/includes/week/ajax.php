<?php
if (!defined('ABSPATH')) exit;

add_action('wp_ajax_tfp_week_mark_step_complete', function () {
    if (!is_user_logged_in()) {
        wp_send_json(['success' => false, 'message' => __('You must be logged in.', 'tfp-dashboard')], 403);
    }

    if (!isset($_POST['tfp_week_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tfp_week_nonce'])), 'tfp_week_nonce')) {
        wp_send_json(['success' => false, 'message' => __('Security check failed.', 'tfp-dashboard')], 403);
    }

    $lesson_id = isset($_POST['lesson_id']) ? absint($_POST['lesson_id']) : 0;
    $step      = isset($_POST['step']) ? sanitize_key(wp_unslash($_POST['step'])) : '';
    $user_id   = get_current_user_id();

    if (!$lesson_id || !tfp_ld_get_week($lesson_id)) {
        wp_send_json(['success' => false, 'message' => __('Week not found.', 'tfp-dashboard')], 404);
    }

    if (!in_array($step, tfp_ld_week_steps(), true)) {
        wp_send_json(['success' => false, 'message' => __('Invalid step.', 'tfp-dashboard')], 400);
    }

    // Only the video step can be self-marked-complete purely by watching;
    // everything else requires its own future submission flow (reading
    // checklist, homework form, quiz, test). This handler currently only
    // supports 'video' — trying to mark any other step here is rejected
    // rather than silently allowed, so gating can't be bypassed from the
    // browser console.
    if ($step !== 'video') {
        wp_send_json(['success' => false, 'message' => __('This step cannot be completed this way yet.', 'tfp-dashboard')], 400);
    }

    // Must actually be unlocked (defensive — video is always unlocked,
    // but keep the check for consistency/future steps).
    $progress = tfp_ld_get_week_progress($user_id, $lesson_id);
    if (!tfp_ld_is_step_unlocked($progress, $step)) {
        wp_send_json(['success' => false, 'message' => __('This step is locked.', 'tfp-dashboard')], 403);
    }

    tfp_ld_mark_step_complete($user_id, $lesson_id, $step);

    wp_send_json([
        'success'  => true,
        'progress' => tfp_ld_get_week_progress($user_id, $lesson_id),
    ]);
});

add_action('wp_ajax_tfp_week_mark_reading_complete', function () {
    if (!is_user_logged_in()) {
        wp_send_json(['success' => false, 'message' => __('You must be logged in.', 'tfp-dashboard')], 403);
    }

    if (!isset($_POST['tfp_week_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tfp_week_nonce'])), 'tfp_week_nonce')) {
        wp_send_json(['success' => false, 'message' => __('Security check failed.', 'tfp-dashboard')], 403);
    }

    $lesson_id = isset($_POST['lesson_id']) ? absint($_POST['lesson_id']) : 0;
    $reading_id = isset($_POST['reading_id']) ? absint($_POST['reading_id']) : 0;
    $is_last = isset($_POST['is_last']) && $_POST['is_last'] === '1';
    $user_id   = get_current_user_id();

    if (!$lesson_id || !tfp_ld_get_week($lesson_id)) {
        wp_send_json(['success' => false, 'message' => __('Week not found.', 'tfp-dashboard')], 404);
    }

    $meta_key = 'tfp_reading_progress_' . $lesson_id;
    $completed_readings = get_user_meta($user_id, $meta_key, true);
    $completed_readings = is_array($completed_readings) ? $completed_readings : [];
    
    if (!in_array($reading_id, $completed_readings)) {
        $completed_readings[] = $reading_id;
        update_user_meta($user_id, $meta_key, $completed_readings);
    }

    if ($is_last) {
        tfp_ld_mark_step_complete($user_id, $lesson_id, 'reading');
    }

    // Get total readings to return counts
    $readings = get_posts([
        'post_type'      => 'tfp_reading',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'meta_query'     => [
            [
                'key'   => '_tfp_lesson_id',
                'value' => $lesson_id,
            ]
        ]
    ]);
    
    $valid_completed = array_intersect(wp_list_pluck($readings, 'ID'), $completed_readings);
    $completed_count = count($valid_completed);

    wp_send_json([
        'success'  => true,
        'data'     => [
            'completed_count' => $completed_count,
            'total'           => count($readings),
        ]
    ]);
});

