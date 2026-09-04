<?php
if (!defined('ABSPATH')) exit;

/**
 * Get homework questions for a specific lesson.
 * 
 * @param int $lesson_id
 * @param bool $for_display If true, strips the 'correct_index' from multiple_choice questions.
 * @return array
 */
function tfp_week_get_homework_questions($lesson_id, $for_display = true)
{
    $json = get_post_meta($lesson_id, 'tfp_week_homework_questions', true);
    if (empty($json)) {
        return [];
    }

    $questions = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($questions)) {
        return [];
    }

    if ($for_display) {
        foreach ($questions as &$q) {
            if (isset($q['type']) && $q['type'] === 'multiple_choice') {
                unset($q['correct_index']);
            }
        }
    }

    return $questions;
}

/**
 * Get stored homework answers for a user and lesson.
 * 
 * @param int $user_id
 * @param int $lesson_id
 * @return array Associative array keyed by question ID.
 */
function tfp_week_get_homework_answers($user_id, $lesson_id)
{
    $meta_key = 'tfp_week_homework_answers_' . $lesson_id;
    $answers = get_user_meta($user_id, $meta_key, true);
    return is_array($answers) ? $answers : [];
}

/**
 * Check if a specific question is answered.
 * 
 * @param array $question The question object from JSON.
 * @param array $answer The stored answer for this question.
 * @return bool
 */
function tfp_week_is_question_answered($question, $answer)
{
    if (empty($question['type']) || empty($answer)) {
        return false;
    }

    $type = $question['type'];

    if ($type === 'multiple_choice') {
        return isset($answer['selected_index']) && $answer['selected_index'] !== '';
    }

    if ($type === 'written') {
        return !empty($answer['text']) && trim($answer['text']) !== '';
    }

    if ($type === 'both') {
        return isset($answer['yes_no']) && $answer['yes_no'] !== '' && !empty($answer['text']) && trim($answer['text']) !== '';
    }

    return false;
}

/**
 * Save a single homework answer.
 * 
 * @param int $user_id
 * @param int $lesson_id
 * @param string $question_id
 * @param array $answer
 * @return bool True on success, false if question doesn't exist.
 */
function tfp_week_save_homework_answer($user_id, $lesson_id, $question_id, $answer)
{
    $questions = tfp_week_get_homework_questions($lesson_id, false);
    
    // Validate question exists
    $found_q = null;
    foreach ($questions as $q) {
        if (isset($q['id']) && $q['id'] === $question_id) {
            $found_q = $q;
            break;
        }
    }

    if (!$found_q) {
        return false;
    }

    // Ensure answer type matches question type for data integrity
    $answer['type'] = $found_q['type'];

    $answers = tfp_week_get_homework_answers($user_id, $lesson_id);
    $answers[$question_id] = $answer;

    $meta_key = 'tfp_week_homework_answers_' . $lesson_id;
    update_user_meta($user_id, $meta_key, $answers);

    return true;
}

/**
 * Get homework progress count.
 * 
 * @param int $user_id
 * @param int $lesson_id
 * @return array ['completed' => int, 'total' => int]
 */
function tfp_week_homework_progress($user_id, $lesson_id)
{
    $questions = tfp_week_get_homework_questions($lesson_id, false);
    $answers = tfp_week_get_homework_answers($user_id, $lesson_id);

    $completed = 0;
    foreach ($questions as $q) {
        if (isset($q['id']) && isset($answers[$q['id']])) {
            if (tfp_week_is_question_answered($q, $answers[$q['id']])) {
                $completed++;
            }
        }
    }

    return [
        'completed' => $completed,
        'total'     => count($questions)
    ];
}

/**
 * Check if all homework questions are answered.
 * 
 * @param int $user_id
 * @param int $lesson_id
 * @return bool
 */
function tfp_week_is_homework_fully_answered($user_id, $lesson_id)
{
    $progress = tfp_week_homework_progress($user_id, $lesson_id);
    return $progress['total'] > 0 && $progress['completed'] === $progress['total'];
}
