<?php
if (!defined('ABSPATH')) exit;

/**
 * Quiz AJAX handlers (Week player → Quiz tab).
 *
 * All handlers mirror the homework-ajax.php pattern: logged-in check, the
 * shared tfp_week_nonce, the quiz step must be unlocked, and any grading /
 * completion decision is made on the SERVER — the browser is never trusted.
 */

/**
 * Shared guard for the quiz handlers.
 */
function tfp_quiz_check_request($lesson_id)
{
    if (!is_user_logged_in()) {
        wp_send_json(['success' => false, 'message' => __('You must be logged in.', 'tfp-dashboard')], 403);
    }

    if (!isset($_POST['tfp_week_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tfp_week_nonce'])), 'tfp_week_nonce')) {
        wp_send_json(['success' => false, 'message' => __('Security check failed.', 'tfp-dashboard')], 403);
    }

    if (!$lesson_id || !tfp_ld_get_week($lesson_id)) {
        wp_send_json(['success' => false, 'message' => __('Week not found.', 'tfp-dashboard')], 404);
    }

    // The quiz is a gated step: it only unlocks after homework is submitted.
    $progress = tfp_ld_get_week_progress(get_current_user_id(), $lesson_id);
    if (!tfp_ld_is_step_unlocked($progress, 'quiz')) {
        wp_send_json(['success' => false, 'message' => __('This step is locked.', 'tfp-dashboard')], 403);
    }

    return get_current_user_id();
}

/**
 * Save a single quiz answer (auto-save on option change).
 */
add_action('wp_ajax_tfp_week_save_quiz_answer', function () {
    $lesson_id  = isset($_POST['lesson_id']) ? absint($_POST['lesson_id']) : 0;
    $user_id    = tfp_quiz_check_request($lesson_id);

    $question_id = isset($_POST['question_id']) ? sanitize_text_field(wp_unslash($_POST['question_id'])) : '';
    if ($question_id === '') {
        wp_send_json(['success' => false, 'message' => __('Question ID missing.', 'tfp-dashboard')], 400);
    }

    // Only allow saving when the quiz has not already been graded.
    if (tfp_week_get_quiz_result($user_id, $lesson_id)) {
        wp_send_json(['success' => false, 'message' => __('This quiz has already been submitted.', 'tfp-dashboard')], 400);
    }

    // Validate the question actually exists on this lesson.
    $found = null;
    foreach (tfp_week_get_quiz_questions($lesson_id, false) as $q) {
        if (isset($q['id']) && $q['id'] === $question_id) {
            $found = $q;
            break;
        }
    }
    if (!$found) {
        wp_send_json(['success' => false, 'message' => __('Question not found.', 'tfp-dashboard')], 404);
    }

    $answer = [];
    if (isset($_POST['selected_index']) && $_POST['selected_index'] !== '') {
        $answer['selected_index'] = absint($_POST['selected_index']);
    }
    $answer['type'] = isset($found['type']) ? $found['type'] : 'multiple_choice';

    $answers = tfp_week_get_quiz_answers($user_id, $lesson_id);
    $answers[$question_id] = $answer;
    update_user_meta($user_id, 'tfp_week_quiz_answers_' . $lesson_id, $answers);

    wp_send_json([
        'success'  => true,
        'progress' => tfp_week_quiz_progress($user_id, $lesson_id),
    ]);
});

/**
 * Submit the quiz for grading. Grades server-side, stores the result, and
 * (only on a pass) marks the `quiz` step complete so the Test tab unlocks.
 */
add_action('wp_ajax_tfp_week_submit_quiz', function () {
    $lesson_id = isset($_POST['lesson_id']) ? absint($_POST['lesson_id']) : 0;
    $user_id   = tfp_quiz_check_request($lesson_id);

    // Already graded? Re-sending the result is idempotent (double-submit safe).
    $existing = tfp_week_get_quiz_result($user_id, $lesson_id);
    if ($existing) {
        wp_send_json([
            'success'        => true,
            'passed'         => !empty($existing['passed']),
            'score'          => (int) $existing['score'],
            'total'          => (int) $existing['total'],
            'correct'        => (int) $existing['correct'],
            'pass_percentage'=> tfp_week_get_quiz_pass_percentage($lesson_id),
            'graded'         => true,
        ]);
    }

    if (!tfp_week_is_quiz_fully_answered($user_id, $lesson_id)) {
        wp_send_json(['success' => false, 'message' => __('Not all questions are answered.', 'tfp-dashboard')], 400);
    }

    $answers = tfp_week_get_quiz_answers($user_id, $lesson_id);
    $grade   = tfp_week_grade_quiz($user_id, $lesson_id, $answers);

    $grade['graded_at'] = current_time('mysql');
    $grade['attempt']   = tfp_week_get_quiz_attempts($user_id, $lesson_id) + 1;
    update_user_meta($user_id, 'tfp_week_quiz_result_' . $lesson_id, $grade);
    update_user_meta($user_id, 'tfp_week_quiz_attempts_' . $lesson_id, $grade['attempt']);

    // A passing quiz completes the gated `quiz` step (unlocks the Test tab).
    if (!empty($grade['passed'])) {
        tfp_ld_mark_step_complete($user_id, $lesson_id, 'quiz');
    }

    wp_send_json([
        'success'         => true,
        'passed'          => $grade['passed'],
        'score'           => $grade['score'],
        'total'           => $grade['total'],
        'correct'         => $grade['correct'],
        'pass_percentage' => tfp_week_get_quiz_pass_percentage($lesson_id),
        'graded'          => true,
    ]);
});

/**
 * Retake a failed quiz: clear answers + result so the student starts fresh.
 * Refuses once the quiz has been passed OR the attempt limit is reached.
 */
add_action('wp_ajax_tfp_week_reset_quiz', function () {
    $lesson_id = isset($_POST['lesson_id']) ? absint($_POST['lesson_id']) : 0;
    $user_id   = tfp_quiz_check_request($lesson_id);

    $progress = tfp_ld_get_week_progress($user_id, $lesson_id);
    if (!empty($progress['quiz'])) {
        wp_send_json(['success' => false, 'message' => __('You have already passed this quiz.', 'tfp-dashboard')], 400);
    }

    if (!tfp_week_can_retake_quiz($user_id, $lesson_id)) {
        wp_send_json(['success' => false, 'message' => __('You have no quiz attempts remaining.', 'tfp-dashboard')], 400);
    }

    tfp_week_clear_quiz($user_id, $lesson_id);

    wp_send_json(['success' => true]);
});
