<?php
if (!defined('ABSPATH')) exit;

/**
 * Program pacing helpers — weekly due-dates + overdue detection.
 *
 * A cohort's `_start_date` anchors the schedule: the week at 0-based index $i
 * is "due" at the end of week ($i + 1) — i.e. start + ($i + 1) * 7 days. A week
 * is OVERDUE when its due date has passed AND the student hasn't submitted its
 * homework. Weeks with no homework configured are never overdue (there is
 * nothing to submit, so they'd otherwise be flagged forever).
 *
 * When the enrolled cohort has no start date, pacing disables gracefully: no
 * due dates, no overdue weeks, and the calendar "current week" falls back to
 * the completion-based current week used elsewhere.
 */

/**
 * The enrolled cohort's start timestamp, or 0 if unavailable.
 */
function tfp_program_start_ts($state)
{
    if (empty($state['cohort']) || empty($state['cohort']['start_date'])) {
        return 0;
    }
    $ts = strtotime($state['cohort']['start_date']);
    return $ts ?: 0;
}

/**
 * Due timestamp for the week at the given 0-based index: the end of that week
 * relative to the cohort start (start + ($index + 1) weeks). 0 when no start.
 */
function tfp_program_week_due_ts($start_ts, $index)
{
    if ($start_ts <= 0) {
        return 0;
    }
    return $start_ts + (($index + 1) * WEEK_IN_SECONDS);
}

/**
 * The 1-based calendar week the cohort is currently in, clamped to [1, total].
 * Returns 0 when there's no start date or no weeks (caller falls back).
 */
function tfp_program_calendar_week($start_ts, $total)
{
    if ($start_ts <= 0 || $total <= 0) {
        return 0;
    }
    $now  = current_time('timestamp');
    $week = (int) floor(($now - $start_ts) / WEEK_IN_SECONDS) + 1;
    return max(1, min($total, $week));
}

/**
 * Weeks whose homework is overdue: due date passed AND homework not submitted.
 * Only weeks that actually have homework questions can be overdue. Kept in
 * course order (ascending due date — most overdue first). Empty array when the
 * cohort has no start date.
 *
 * @return array[] each: ['week' => WP_Post, 'index' => int, 'due_ts' => int]
 */
function tfp_program_overdue_weeks($user_id, $course_id, $state)
{
    $start_ts = tfp_program_start_ts($state);
    if ($start_ts <= 0 || !$course_id || !function_exists('tfp_ld_get_weeks')) {
        return [];
    }

    $now     = current_time('timestamp');
    $weeks   = tfp_ld_get_weeks($course_id);
    $overdue = [];

    foreach ($weeks as $index => $week) {
        $due_ts = tfp_program_week_due_ts($start_ts, $index);
        if ($due_ts <= 0 || $due_ts >= $now) {
            continue; // not due yet
        }

        // Only weeks with homework configured can be "overdue homework" — a
        // week with no questions can never be submitted, so skip it.
        if (function_exists('tfp_week_get_homework_questions')) {
            $questions = tfp_week_get_homework_questions($week->ID, true);
            if (empty($questions)) {
                continue;
            }
        }

        $progress = tfp_ld_get_week_progress($user_id, $week->ID);
        if (!empty($progress['homework'])) {
            continue; // already submitted
        }

        $overdue[] = [
            'week'   => $week,
            'index'  => $index,
            'due_ts' => $due_ts,
        ];
    }

    return $overdue;
}
