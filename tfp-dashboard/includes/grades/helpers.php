<?php
if (!defined('ABSPATH')) exit;

/**
 * Facilitator-entered per-week letter grades.
 *
 * A facilitator/admin records a letter grade (A–F) for a student on a given
 * week (LearnDash lesson) via the TFP Grades admin screen. It is stored per
 * user + lesson in user meta `tfp_week_grade_{lesson_id}`. The student's
 * overall grade is the average of every graded week, mapped back to a letter.
 */

/**
 * Letter => grade-point scale.
 */
function tfp_grade_scale()
{
    return ['A' => 4, 'B' => 3, 'C' => 2, 'D' => 1, 'F' => 0];
}

/**
 * The valid letter grades, in order (A first).
 */
function tfp_grade_letters()
{
    return array_keys(tfp_grade_scale());
}

/**
 * A student's letter grade for one week ('' if not graded / invalid).
 */
function tfp_grade_get_week($user_id, $lesson_id)
{
    $letter = get_user_meta((int) $user_id, 'tfp_week_grade_' . (int) $lesson_id, true);
    $scale  = tfp_grade_scale();
    return isset($scale[$letter]) ? $letter : '';
}

/**
 * Set (or clear, with '') a student's letter grade for one week. Invalid
 * letters are ignored. Returns true on a successful set/clear.
 */
function tfp_grade_set_week($user_id, $lesson_id, $letter)
{
    $key = 'tfp_week_grade_' . (int) $lesson_id;

    if ($letter === '' || $letter === null) {
        delete_user_meta((int) $user_id, $key);
        return true;
    }

    $letter = strtoupper(trim($letter));
    $scale  = tfp_grade_scale();
    if (!isset($scale[$letter])) {
        return false;
    }

    update_user_meta((int) $user_id, $key, $letter);
    return true;
}

/**
 * A student's overall letter grade across a course: the average of every graded
 * week, rounded to the nearest grade point and mapped back to a letter. Returns
 * '' when no weeks are graded yet.
 */
function tfp_grade_overall($user_id, $course_id)
{
    if (!$course_id || !function_exists('tfp_ld_get_weeks')) {
        return '';
    }

    $scale  = tfp_grade_scale();
    $points = [];

    foreach (tfp_ld_get_weeks($course_id) as $week) {
        $letter = tfp_grade_get_week($user_id, $week->ID);
        if ($letter !== '') {
            $points[] = $scale[$letter];
        }
    }

    if (empty($points)) {
        return '';
    }

    $rounded = (int) round(array_sum($points) / count($points));
    $rounded = max(0, min(4, $rounded));

    $by_point = array_flip($scale); // 4 => A, 3 => B, ...
    return isset($by_point[$rounded]) ? $by_point[$rounded] : '';
}
