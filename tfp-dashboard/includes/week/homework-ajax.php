<?php
if (!defined('ABSPATH')) exit;

/**
 * Handle saving a single homework answer.
 */
add_action('wp_ajax_tfp_week_save_homework_answer', function () {
    if (!is_user_logged_in()) {
        wp_send_json(['success' => false, 'message' => __('You must be logged in.', 'tfp-dashboard')], 403);
    }

    if (!isset($_POST['tfp_week_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tfp_week_nonce'])), 'tfp_week_nonce')) {
        wp_send_json(['success' => false, 'message' => __('Security check failed.', 'tfp-dashboard')], 403);
    }

    $lesson_id = isset($_POST['lesson_id']) ? absint($_POST['lesson_id']) : 0;
    $question_id = isset($_POST['question_id']) ? sanitize_text_field(wp_unslash($_POST['question_id'])) : '';
    $user_id = get_current_user_id();

    if (!$lesson_id || !tfp_ld_get_week($lesson_id)) {
        wp_send_json(['success' => false, 'message' => __('Week not found.', 'tfp-dashboard')], 404);
    }

    if (!$question_id) {
        wp_send_json(['success' => false, 'message' => __('Question ID missing.', 'tfp-dashboard')], 400);
    }

    // Ensure step is actually unlocked
    $progress = tfp_ld_get_week_progress($user_id, $lesson_id);
    if (!tfp_ld_is_step_unlocked($progress, 'homework')) {
        wp_send_json(['success' => false, 'message' => __('This step is locked.', 'tfp-dashboard')], 403);
    }

    // Prepare answer payload based on input
    $answer = [];
    if (isset($_POST['selected_index']) && $_POST['selected_index'] !== '') {
        $answer['selected_index'] = absint($_POST['selected_index']);
    }
    if (isset($_POST['text'])) {
        $answer['text'] = sanitize_textarea_field(wp_unslash($_POST['text']));
    }
    if (isset($_POST['yes_no'])) {
        $answer['yes_no'] = sanitize_text_field(wp_unslash($_POST['yes_no']));
    }

    // Save
    $saved = tfp_week_save_homework_answer($user_id, $lesson_id, $question_id, $answer);

    if (!$saved) {
        wp_send_json(['success' => false, 'message' => __('Failed to save answer.', 'tfp-dashboard')], 400);
    }

    wp_send_json([
        'success' => true,
        'progress' => tfp_week_homework_progress($user_id, $lesson_id),
    ]);
});

/**
 * Handle submitting the entire homework to unlock Quiz.
 */
add_action('wp_ajax_tfp_week_submit_homework', function () {
    if (!is_user_logged_in()) {
        wp_send_json(['success' => false, 'message' => __('You must be logged in.', 'tfp-dashboard')], 403);
    }

    if (!isset($_POST['tfp_week_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tfp_week_nonce'])), 'tfp_week_nonce')) {
        wp_send_json(['success' => false, 'message' => __('Security check failed.', 'tfp-dashboard')], 403);
    }

    $lesson_id = isset($_POST['lesson_id']) ? absint($_POST['lesson_id']) : 0;
    $user_id = get_current_user_id();

    if (!$lesson_id || !tfp_ld_get_week($lesson_id)) {
        wp_send_json(['success' => false, 'message' => __('Week not found.', 'tfp-dashboard')], 404);
    }

    // Ensure step is actually unlocked
    $progress = tfp_ld_get_week_progress($user_id, $lesson_id);
    if (!tfp_ld_is_step_unlocked($progress, 'homework')) {
        wp_send_json(['success' => false, 'message' => __('This step is locked.', 'tfp-dashboard')], 403);
    }

    // Verify all questions are answered server-side
    if (!tfp_week_is_homework_fully_answered($user_id, $lesson_id)) {
        wp_send_json(['success' => false, 'message' => __('Not all questions are answered.', 'tfp-dashboard')], 400);
    }

    tfp_ld_mark_step_complete($user_id, $lesson_id, 'homework');

    wp_send_json([
        'success' => true,
        'progress' => tfp_ld_get_week_progress($user_id, $lesson_id),
    ]);
});
