<?php
if (!defined('ABSPATH')) exit;

/**
 * "TFP Grades" admin screen — facilitators/admins record a per-week letter
 * grade (A–F) for each enrolled student. Grades feed the Overall Grade tile on
 * the student's Program page (see tfp_grade_overall()).
 *
 * Access follows tfp_dashboard_user_is_staff() (admins, shop managers,
 * facilitators). Rather than hard-code one WP role's capabilities into
 * add_menu_page(), we grant a custom `tfp_manage_grades` capability to staff via
 * user_has_cap and gate both the menu and the save on it.
 */

/**
 * Map the custom `tfp_manage_grades` cap onto tfp_dashboard_user_is_staff().
 */
add_filter('user_has_cap', function ($allcaps, $caps, $args, $user) {
    $wants = in_array('tfp_manage_grades', (array) $caps, true)
        || (isset($args[0]) && $args[0] === 'tfp_manage_grades');

    if ($wants && $user instanceof WP_User && function_exists('tfp_dashboard_user_is_staff') && tfp_dashboard_user_is_staff($user->ID)) {
        $allcaps['tfp_manage_grades'] = true;
    }

    return $allcaps;
}, 10, 4);

add_action('admin_menu', function () {
    add_menu_page(
        __('TFP Grades', 'tfp-dashboard'),
        __('TFP Grades', 'tfp-dashboard'),
        'tfp_manage_grades',
        'tfp-grades',
        'tfp_grades_render_admin_page',
        'dashicons-welcome-learn-more',
        26
    );
});

/**
 * Handle the grade-save POST, then redirect back to the same course+week view.
 */
add_action('admin_init', function () {
    if (empty($_POST['tfp_grades_save'])) {
        return;
    }
    if (!current_user_can('tfp_manage_grades')) {
        wp_die(__('You are not allowed to manage grades.', 'tfp-dashboard'));
    }
    if (!isset($_POST['tfp_grades_nonce']) || !wp_verify_nonce($_POST['tfp_grades_nonce'], 'tfp_grades_save')) {
        wp_die(__('Security check failed.', 'tfp-dashboard'));
    }

    $course_id = absint($_POST['course_id'] ?? 0);
    $lesson_id = absint($_POST['lesson_id'] ?? 0);
    $grades    = (isset($_POST['grade']) && is_array($_POST['grade'])) ? $_POST['grade'] : [];

    if ($lesson_id && function_exists('tfp_grade_set_week')) {
        foreach ($grades as $student_id => $letter) {
            tfp_grade_set_week(absint($student_id), $lesson_id, sanitize_text_field($letter));
        }
    }

    wp_safe_redirect(add_query_arg([
        'page'      => 'tfp-grades',
        'course_id' => $course_id,
        'lesson_id' => $lesson_id,
        'saved'     => 1,
    ], admin_url('admin.php')));
    exit;
});

function tfp_grades_render_admin_page()
{
    if (!current_user_can('tfp_manage_grades')) {
        wp_die(__('You are not allowed to manage grades.', 'tfp-dashboard'));
    }

    $courses = get_posts([
        'post_type'      => 'sfwd-courses',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ]);

    $course_id = absint($_GET['course_id'] ?? 0);
    $lesson_id = absint($_GET['lesson_id'] ?? 0);
    $weeks     = ($course_id && function_exists('tfp_ld_get_weeks')) ? tfp_ld_get_weeks($course_id) : [];

    echo '<div class="wrap"><h1>' . esc_html__('TFP Grades', 'tfp-dashboard') . '</h1>';
    echo '<p>' . esc_html__('Record a per-week letter grade for each enrolled student. Their overall grade is the average of every graded week.', 'tfp-dashboard') . '</p>';

    if (!empty($_GET['saved'])) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Grades saved.', 'tfp-dashboard') . '</p></div>';
    }

    // Course + week selectors (GET, so views are shareable/bookmarkable).
    echo '<form method="get" style="margin:16px 0;">';
    echo '<input type="hidden" name="page" value="tfp-grades">';

    echo '<label style="margin-right:12px;"><strong>' . esc_html__('Course', 'tfp-dashboard') . '</strong> ';
    echo '<select name="course_id" onchange="this.form.submit()">';
    echo '<option value="0">' . esc_html__('— Select a course —', 'tfp-dashboard') . '</option>';
    foreach ($courses as $c) {
        printf('<option value="%d" %s>%s</option>', $c->ID, selected($course_id, $c->ID, false), esc_html($c->post_title));
    }
    echo '</select></label>';

    if ($course_id && !empty($weeks)) {
        echo '<label style="margin-right:12px;"><strong>' . esc_html__('Week', 'tfp-dashboard') . '</strong> ';
        echo '<select name="lesson_id" onchange="this.form.submit()">';
        echo '<option value="0">' . esc_html__('— Select a week —', 'tfp-dashboard') . '</option>';
        foreach ($weeks as $i => $w) {
            printf(
                '<option value="%d" %s>%s</option>',
                $w->ID,
                selected($lesson_id, $w->ID, false),
                esc_html(sprintf(__('Week %1$d — %2$s', 'tfp-dashboard'), $i + 1, $w->post_title))
            );
        }
        echo '</select></label>';
    }
    echo '</form>';

    if (!$course_id) {
        echo '<p>' . esc_html__('Choose a course to begin grading.', 'tfp-dashboard') . '</p></div>';
        return;
    }
    if (empty($weeks)) {
        echo '<p>' . esc_html__('This course has no weekly lessons yet.', 'tfp-dashboard') . '</p></div>';
        return;
    }
    if (!$lesson_id) {
        echo '<p>' . esc_html__('Choose a week to enter grades for its students.', 'tfp-dashboard') . '</p></div>';
        return;
    }

    // Students who chose this course as their program.
    $students = get_users([
        'meta_key'   => 'tfp_program_choice',
        'meta_value' => $course_id,
        'orderby'    => 'display_name',
        'order'      => 'ASC',
    ]);

    if (empty($students)) {
        echo '<p>' . esc_html__('No students are enrolled in this course yet.', 'tfp-dashboard') . '</p></div>';
        return;
    }

    $letters = function_exists('tfp_grade_letters') ? tfp_grade_letters() : ['A', 'B', 'C', 'D', 'F'];

    echo '<form method="post">';
    wp_nonce_field('tfp_grades_save', 'tfp_grades_nonce');
    echo '<input type="hidden" name="course_id" value="' . esc_attr($course_id) . '">';
    echo '<input type="hidden" name="lesson_id" value="' . esc_attr($lesson_id) . '">';

    echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
    echo '<th>' . esc_html__('Student', 'tfp-dashboard') . '</th>';
    echo '<th>' . esc_html__('Email', 'tfp-dashboard') . '</th>';
    echo '<th>' . esc_html__('Homework', 'tfp-dashboard') . '</th>';
    echo '<th>' . esc_html__('Overall', 'tfp-dashboard') . '</th>';
    echo '<th>' . esc_html__('Week Grade', 'tfp-dashboard') . '</th>';
    echo '</tr></thead><tbody>';

    foreach ($students as $student) {
        $progress = function_exists('tfp_ld_get_week_progress') ? tfp_ld_get_week_progress($student->ID, $lesson_id) : [];
        $hw       = !empty($progress['homework']) ? __('Submitted', 'tfp-dashboard') : __('Not submitted', 'tfp-dashboard');
        $current  = function_exists('tfp_grade_get_week') ? tfp_grade_get_week($student->ID, $lesson_id) : '';
        $overall  = function_exists('tfp_grade_overall') ? tfp_grade_overall($student->ID, $course_id) : '';

        echo '<tr>';
        echo '<td><strong>' . esc_html($student->display_name) . '</strong></td>';
        echo '<td>' . esc_html($student->user_email) . '</td>';
        echo '<td>' . esc_html($hw) . '</td>';
        echo '<td>' . esc_html($overall !== '' ? $overall : '—') . '</td>';
        echo '<td><select name="grade[' . esc_attr($student->ID) . ']">';
        echo '<option value="">' . esc_html__('— Not graded —', 'tfp-dashboard') . '</option>';
        foreach ($letters as $letter) {
            printf('<option value="%s" %s>%s</option>', esc_attr($letter), selected($current, $letter, false), esc_html($letter));
        }
        echo '</select></td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '<p><button type="submit" name="tfp_grades_save" value="1" class="button button-primary">' . esc_html__('Save Grades', 'tfp-dashboard') . '</button></p>';
    echo '</form>';

    echo '</div>';
}
