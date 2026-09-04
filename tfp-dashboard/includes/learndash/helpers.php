<?php
if (!defined('ABSPATH')) exit;

/**
 * The LearnDash course ID for the user's enrolled program. Reuses the
 * same tfp_program_choice -> course resolution already established in
 * includes/billing/helpers.php (with its own guest-checkout fallback).
 */
function tfp_ld_get_program_course_id($user_id = null)
{
    $user_id = $user_id ?: get_current_user_id();
    $course_id = (int) get_user_meta($user_id, 'tfp_program_choice', true);

    if (!$course_id && function_exists('tfp_billing_find_paid_program_product_id')) {
        // tfp_billing_find_paid_program_product_id() backfills
        // tfp_program_choice as a side effect when it finds a paid order,
        // so re-read the meta after calling it.
        tfp_billing_find_paid_program_product_id($user_id);
        $course_id = (int) get_user_meta($user_id, 'tfp_program_choice', true);
    }

    return apply_filters('tfp_ld_program_course_id', $course_id, $user_id);
}

/**
 * The ordered list of "weeks" (LearnDash Lessons) in a course.
 * Returns an array of WP_Post objects in course order.
 */
function tfp_ld_get_weeks($course_id)
{
    static $cache = [];

    if (!$course_id || !function_exists('learndash_course_get_steps_by_type')) {
        return [];
    }

    if (isset($cache[$course_id])) {
        return $cache[$course_id];
    }

    $lesson_ids = learndash_course_get_steps_by_type($course_id, 'sfwd-lessons');
    $weeks = [];

    foreach ($lesson_ids as $lesson_id) {
        $post = get_post($lesson_id);
        if ($post) {
            $weeks[] = $post;
        }
    }

    $cache[$course_id] = $weeks;
    return $weeks;
}

function tfp_ld_get_week($lesson_id)
{
    $post = get_post($lesson_id);
    return ($post && $post->post_type === 'sfwd-lessons') ? $post : null;
}

/**
 * The sub-steps within a single week, in required order. "meeting" is
 * intentionally NOT gated — it can be joined any time during the week,
 * matching the client's spec ("Live Call can be joined anytime during
 * the week, but it only appears in the Live Call tab").
 */
function tfp_ld_week_steps()
{
    return ['video', 'reading', 'homework', 'quiz', 'test'];
}

/**
 * Per-week sub-step completion, stored independently of LearnDash's own
 * (coarser) lesson-complete flag. Returns e.g.
 * ['video' => true, 'reading' => false, 'homework' => false, ...].
 */
function tfp_ld_get_week_progress($user_id, $lesson_id)
{
    $stored = get_user_meta($user_id, 'tfp_week_progress_' . $lesson_id, true);
    $stored = is_array($stored) ? $stored : [];

    $progress = [];
    foreach (tfp_ld_week_steps() as $step) {
        $progress[$step] = !empty($stored[$step]);
    }

    return $progress;
}

/**
 * Is a given step unlocked for this user? Sequential: each step unlocks
 * once the previous one is complete. The first step (video) is always
 * unlocked. "meeting" is always unlocked (not part of the gated sequence).
 */
function tfp_ld_is_step_unlocked($progress, $step)
{
    if ($step === 'meeting') {
        return true;
    }

    $steps = tfp_ld_week_steps();
    $index = array_search($step, $steps, true);

    if ($index === false || $index === 0) {
        return true;
    }

    $previous_step = $steps[$index - 1];
    return !empty($progress[$previous_step]);
}

/**
 * Mark a sub-step complete for a user/week. When the final step ("test")
 * is completed, also marks the LearnDash lesson itself complete so
 * LearnDash's own course-progress reporting (and anything else reading
 * it, like a future Grades page) stays in sync.
 */
function tfp_ld_mark_step_complete($user_id, $lesson_id, $step)
{
    if (!in_array($step, tfp_ld_week_steps(), true)) {
        return false;
    }

    $meta_key = 'tfp_week_progress_' . $lesson_id;
    $stored = get_user_meta($user_id, $meta_key, true);
    $stored = is_array($stored) ? $stored : [];
    $stored[$step] = true;
    update_user_meta($user_id, $meta_key, $stored);

    $steps = tfp_ld_week_steps();
    if ($step === end($steps) && function_exists('learndash_process_mark_complete')) {
        $course_id = learndash_get_course_id($lesson_id);
        learndash_process_mark_complete($user_id, $lesson_id, false, $course_id);
    }

    return true;
}

/**
 * Is an entire week complete? (All gated steps done — "meeting" doesn't
 * count since it's optional/ungated.)
 */
function tfp_ld_is_week_complete($user_id, $lesson_id)
{
    $progress = tfp_ld_get_week_progress($user_id, $lesson_id);
    foreach (tfp_ld_week_steps() as $step) {
        if (empty($progress[$step])) {
            return false;
        }
    }
    return true;
}

/**
 * The user's "current" week: the first week that isn't fully complete.
 * Returns null if every week is complete (or there are no weeks).
 */
function tfp_ld_get_current_week($user_id, $course_id)
{
    foreach (tfp_ld_get_weeks($course_id) as $week) {
        if (!tfp_ld_is_week_complete($user_id, $week->ID)) {
            return $week;
        }
    }
    return null;
}

/**
 * Simple counts used on the Home page: how many weeks complete, out of
 * how many total.
 */
function tfp_ld_get_journey_progress($user_id, $course_id)
{
    $weeks = tfp_ld_get_weeks($course_id);
    $completed = 0;

    foreach ($weeks as $week) {
        if (tfp_ld_is_week_complete($user_id, $week->ID)) {
            $completed++;
        }
    }

    return [
        'completed' => $completed,
        'total'     => count($weeks),
    ];
}
