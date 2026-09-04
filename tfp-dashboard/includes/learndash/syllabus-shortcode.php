<?php

if (!defined('ABSPATH')) exit;

/**
 * Shortcode: [tfp_program_syllabus]
 * Renders a dynamic accordion based on LearnDash Course Sections and Lessons.
 */
add_shortcode('tfp_program_syllabus', function($atts) {
    $product_id = get_the_ID();
    
    // Enqueue styles and scripts directly in the shortcode so they load on any page
    wp_enqueue_style('tfp-syllabus', TFP_DASH_URL . 'assets/css/syllabus.css', [], TFP_DASH_VERSION);
    wp_enqueue_script('tfp-syllabus', TFP_DASH_URL . 'assets/js/syllabus.js', [], TFP_DASH_VERSION, true);

    // Debugging check (Only for admins if needed)
    $is_admin = current_user_can('manage_options');

    if (!$product_id || get_post_type($product_id) !== 'product') {
        if (isset($_GET['test_product_id'])) {
            $product_id = (int)$_GET['test_product_id'];
        } else {
            return '';
        }
    }

    // 1. Get the Course ID linked to this Product
    $course_id = 0;
    $related_courses = get_post_meta($product_id, '_related_course', true);
    
    if (is_array($related_courses)) {
        $course_id = (int) reset($related_courses);
    } elseif (!empty($related_courses)) {
        $course_id = (int) $related_courses;
    }

    if (!$course_id) {
        return $is_admin ? '<p>Debug: No LearnDash Course linked to this Product ID ' . $product_id . '</p>' : '';
    }

    // 2. Fetch Lessons using standard LearnDash function
    $lessons = learndash_get_lesson_list($course_id, ['num' => -1]);
    
    if (empty($lessons)) {
        return '<p>' . esc_html__('Course content coming soon.', 'tfp-dashboard') . '</p>';
    }

    // 3. Get Sections (LearnDash 3.0+)
    $course_sections = [];
    if (function_exists('learndash_30_get_course_sections')) {
        $course_sections = learndash_30_get_course_sections($course_id);
    }

    // 4. Group Lessons by Sections
    $grouped_content = [];
    $current_section = __('Course Syllabus', 'tfp-dashboard');

    // If there are no sections, fallback to default title
    if (empty($course_sections)) {
        $grouped_content[$current_section] = $lessons;
    } else {
        $grouped_content[$current_section] = [];
        
        foreach ($lessons as $lesson) {
            // Does a new section start AT this lesson?
            if (isset($course_sections[$lesson->ID])) {
                $section_obj = $course_sections[$lesson->ID];
                $current_section = isset($section_obj->post_title) ? $section_obj->post_title : $current_section;
                
                if (!isset($grouped_content[$current_section])) {
                    $grouped_content[$current_section] = [];
                }
            }
            
            $grouped_content[$current_section][] = $lesson;
        }
        
        // Clean up empty default section if it wasn't used
        if (empty($grouped_content[__('Course Syllabus', 'tfp-dashboard')])) {
            unset($grouped_content[__('Course Syllabus', 'tfp-dashboard')]);
        }
    }

    ob_start();
    ?>
    <div class="tfp-syllabus-accordion">
        <?php
        $i = 0;
        foreach ($grouped_content as $section_title => $section_lessons) :
            if (empty($section_lessons)) continue;
            $i++;
            // Only the first one is active by default
            $active_class = ($i === 1) ? ' is-active' : '';
            ?>
            <div class="tfp-syllabus-item<?php echo $active_class; ?>">
                <div class="tfp-syllabus-header">
                    <span class="tfp-syllabus-title"><?php echo esc_html($section_title); ?></span>
                    <span class="tfp-syllabus-icon">
                        <svg width="12" height="8" viewBox="0 0 12 8" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M1 1.5L6 6.5L11 1.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                </div>
                <div class="tfp-syllabus-body">
                    <div class="tfp-syllabus-content">
                        <ul class="tfp-syllabus-lessons-list">
                            <?php foreach ($section_lessons as $lesson) : ?>
                                <li><?php echo esc_html($lesson->post_title); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
});
