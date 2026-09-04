<?php
if (!defined('ABSPATH')) exit;

function tfp_dashboard_week_tab_labels()
{
    return [
        'video'    => __('Video', 'tfp-dashboard'),
        'reading'  => __('Reading', 'tfp-dashboard'),
        'homework' => __('Homework', 'tfp-dashboard'),
        'quiz'     => __('Quiz', 'tfp-dashboard'),
        'test'     => __('Test', 'tfp-dashboard'),
        'meeting'  => __('Meeting', 'tfp-dashboard'),
    ];
}

function tfp_dashboard_render_week_content()
{
    $user_id   = get_current_user_id();
    $lesson_id = isset($_GET['lesson_id']) ? absint($_GET['lesson_id']) : 0;
    $home_url  = function_exists('tfp_dashboard_get_url') ? tfp_dashboard_get_url('tfp-dashboard-home') : '#';

    $week = $lesson_id ? tfp_ld_get_week($lesson_id) : null;

    // No week specified, or it doesn't belong to the user's enrolled
    // course — fall back to their current week rather than showing an
    // empty/broken page.
    if (!$week) {
        $course_id = tfp_ld_get_program_course_id($user_id);
        $week = $course_id ? tfp_ld_get_current_week($user_id, $course_id) : null;
    }

    // Top bar: Course Dashboard button + user profile (matches sidebar footer)
    ?>
    <div class="tfp-week__topbar">
        <div class="tfp-week__topbar-left">
            <a href="<?php echo esc_url(home_url('/')); ?>" class="tfp-week__logo-link">
                <img src="<?php echo esc_url(TFP_DASH_URL . 'assets/images/logo-expanded.svg'); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>" class="tfp-week__logo">
            </a>
        </div>
        <div class="tfp-week__topbar-right">
            <div class="tfp-dash-sidebar__profile">
                <span class="tfp-dash-sidebar__avatar"><?php echo tfp_dashboard_avatar(36); ?></span>
                <span class="tfp-dash-sidebar__name"><?php echo esc_html(tfp_dashboard_user_name()); ?></span>
            </div>
            <a href="<?php echo esc_url($home_url); ?>" class="tfp-dash-btn--primary tfp-week__course-btn"><?php echo tfp_dashboard_icon('chevron-left'); ?> <?php esc_html_e('Back to Course Dashboard', 'tfp-dashboard'); ?></a>
        </div>
    </div>
    <?php

    // Render page header with no breadcrumb (breadcrumbs removed on week view)
    tfp_dashboard_render_page_header(
        '',
        $week ? esc_html($week->post_title) : __('Class', 'tfp-dashboard'),
        ''
    );

    if (!$week) {
        ?>
        <div class="tfp-dash-panel tfp-week__empty">
            <p><?php esc_html_e("We couldn't find a class week for your program yet. Please check back soon.", 'tfp-dashboard'); ?></p>
            <a href="<?php echo esc_url($home_url); ?>" class="tfp-dash-btn tfp-reded-btn"><?php esc_html_e('Back to Dashboard', 'tfp-dashboard'); ?></a>
        </div>
        <?php
        return;
    }

    $lesson_id = $week->ID;
    $progress  = tfp_ld_get_week_progress($user_id, $lesson_id);
    $labels    = tfp_dashboard_week_tab_labels();
    $active    = isset($_GET['tab']) && array_key_exists(sanitize_key($_GET['tab']), $labels)
        ? sanitize_key($_GET['tab'])
        : 'video';

    // Keep locked-step requests at the nearest available step in the sequence.
    if (!tfp_ld_is_step_unlocked($progress, $active)) {
        $steps = tfp_ld_week_steps();
        $requested_index = array_search($active, $steps, true);
        $active = ($requested_index !== false && $requested_index > 0)
            ? $steps[$requested_index - 1]
            : 'video';
    }
    ?>
    <div class="tfp-week">
        <div class="tfp-week__tabs" role="tablist">
            <?php foreach ($labels as $step => $label) :
                $unlocked = tfp_ld_is_step_unlocked($progress, $step);
                $is_active = $step === $active;
                $tab_url = add_query_arg(['lesson_id' => $lesson_id, 'tab' => $step]);
                ?>
                <?php if ($unlocked) : ?>
                    <a
                        href="<?php echo esc_url($tab_url); ?>"
                        class="tfp-week__tab<?php echo $is_active ? ' is-active' : ''; ?><?php echo !empty($progress[$step]) ? ' is-complete' : ''; ?>"
                        role="tab"
                        aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
                    >
                        <?php echo esc_html($label); ?>
                        <?php if (!empty($progress[$step])) : ?><span class="tfp-week__tab-check">&#10003;</span><?php endif; ?>
                    </a>
                <?php else : ?>
                    <span class="tfp-week__tab tfp-week__tab--locked" aria-disabled="true">
                        <?php echo tfp_dashboard_icon('lock'); ?>
                        <?php echo esc_html($label); ?>
                    </span>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <div class="tfp-week__panel">
            <?php
            switch ($active) {
                case 'video':
                    tfp_dashboard_render_week_video_tab($week, $progress);
                    break;
                case 'reading':
                    tfp_dashboard_render_week_reading_tab($week, $progress);
                    break;
                case 'meeting':
                    tfp_dashboard_render_week_meeting_tab($week);
                    break;
                case 'homework':
                    tfp_dashboard_render_week_homework_tab($week, get_current_user_id());
                    break;
                default:
                    tfp_dashboard_render_week_placeholder_tab($active, $labels[$active]);
                    break;
            }
            ?>
        </div>
    </div>
    <?php
}

function tfp_dashboard_render_week_video_tab($week, $progress)
{
    $video_url = get_post_meta($week->ID, 'tfp_week_video_url', true);
    $is_complete = !empty($progress['video']);
    ?>
    <div class="tfp-week__video-wrap">
        <?php if ($video_url) : ?>
            <video
                class="tfp-week__video"
                controls
                data-tfp-week-video
                data-lesson-id="<?php echo esc_attr($week->ID); ?>"
                <?php echo $is_complete ? 'data-already-complete="1"' : ''; ?>
            >
                <source src="<?php echo esc_url($video_url); ?>">
            </video>
        <?php else : ?>
            <div class="tfp-week__video-missing">
                <p><?php esc_html_e('No video has been added for this week yet.', 'tfp-dashboard'); ?></p>
            </div>
        <?php endif; ?>
    </div>

    <div class="tfp-week__video-footer">
        <a href="?lesson_id=<?php echo esc_attr($week->ID); ?>&tab=reading" class="tfp-dash-btn tfp-dash-btn--primary tfp-week__video-next-btn" <?php echo !$is_complete ? 'style="opacity:0.45; pointer-events:none;" disabled' : ''; ?>><?php esc_html_e('Continue to Reading', 'tfp-dashboard'); ?> <svg xmlns="http://www.w3.org/2000/svg" width="12" height="10" viewBox="0 0 12 10" fill="none">
            <path d="M1 5H11M7 9L11 5L7 1" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg></a>
    </div>
    <?php
}

function tfp_dashboard_render_week_meeting_tab($week)
{
    $date       = get_post_meta($week->ID, 'tfp_week_meeting_date', true);
    $time       = get_post_meta($week->ID, 'tfp_week_meeting_time', true);
    $facilitator = get_post_meta($week->ID, 'tfp_week_facilitator_name', true);
    $discord_url = apply_filters('tfp_dashboard_discord_url', '#');
    ?>
    <div class="tfp-week__meeting">
        <div class="tfp-profile-row"><span><?php esc_html_e('Date', 'tfp-dashboard'); ?></span><strong><?php echo esc_html($date ?: '—'); ?></strong></div>
        <div class="tfp-profile-row"><span><?php esc_html_e('Time', 'tfp-dashboard'); ?></span><strong><?php echo esc_html($time ?: '—'); ?></strong></div>
        <div class="tfp-profile-row"><span><?php esc_html_e('Facilitator', 'tfp-dashboard'); ?></span><strong><?php echo esc_html($facilitator ?: '—'); ?></strong></div>

        <a href="<?php echo esc_url($discord_url); ?>" target="_blank" rel="noopener" class="tfp-dash-btn tfp-dash-btn--primary tfp-week__meeting-cta">
            <?php esc_html_e('Join Discord', 'tfp-dashboard'); ?>
        </a>
    </div>
    <?php
}

/**
 * Reading / Homework / Quiz / Test tabs are built in a follow-up session.
 * Once unlocked, this placeholder marks that the step is coming soon
 * rather than showing a broken empty tab.
 */
function tfp_dashboard_render_week_placeholder_tab($step, $label)
{
    ?>
    <div class="tfp-week__placeholder">
        <p><?php printf(esc_html__('%s content for this week is coming soon.', 'tfp-dashboard'), esc_html($label)); ?></p>
    </div>
    <?php
}

function tfp_dashboard_render_week_reading_tab($week, $progress)
{
    // Query readings for this lesson, newest first so last-created appears first
    $readings = get_posts([
        'post_type'      => 'tfp_reading',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'meta_query'     => [
            [
                'key'   => '_tfp_lesson_id',
                'value' => $week->ID,
            ]
        ],
        'orderby'        => 'date',
        'order'          => 'ASC'
    ]);

    if (empty($readings)) {
        tfp_dashboard_render_week_placeholder_tab('reading', __('Reading', 'tfp-dashboard'));
        return;
    }

    $user_id = get_current_user_id();
    $completed_readings = get_user_meta($user_id, 'tfp_reading_progress_' . $week->ID, true);
    $completed_readings = is_array($completed_readings) ? $completed_readings : [];
    $total = count($readings);
    
    // Validate completed count against existing reading IDs
    $valid_completed = array_intersect(wp_list_pluck($readings, 'ID'), $completed_readings);
    $completed_count = count($valid_completed);
    $all_completed = ($completed_count === $total);

    ?>
    <div class="tfp-reading-layout" data-lesson-id="<?php echo esc_attr($week->ID); ?>">
        <div class="tfp-reading-sidebar">
            <h4 class="tfp-reading-progress-title">
                <?php printf(esc_html__('Reading Progress — %d of %d Completed', 'tfp-dashboard'), $completed_count, $total); ?>
            </h4>
            <div class="tfp-reading-list">
                <?php foreach ($readings as $index => $reading) : 
                    $is_completed = in_array($reading->ID, $completed_readings);
                    $status_text = $is_completed ? __('Completed', 'tfp-dashboard') : __('Not Started', 'tfp-dashboard');
                    $btn_text = $is_completed ? __('Review Reading', 'tfp-dashboard') : __('Launch Reading', 'tfp-dashboard');
                ?>
                <div class="tfp-reading-item <?php echo $is_completed ? 'is-completed' : ''; ?>" data-reading-id="<?php echo esc_attr($reading->ID); ?>">
                    <div class="tfp-reading-item-info">
                        <div class="tfp-reading-item-title"><?php echo esc_html($reading->post_title); ?></div>
                        <div class="tfp-reading-item-status">Status: <span><?php echo esc_html($status_text); ?></span></div>
                    </div>
                    <button class="tfp-dash-btn tfp-dash-btn--sm <?php echo $is_completed ? 'tfp-reded-btn' : 'tfp-dash-btn--primary'; ?> tfp-reading-btn">
                        <?php echo esc_html($btn_text); ?>
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
           
        </div>
        
        <div class="tfp-reading-content-area">
            <?php foreach ($readings as $index => $reading) : 
                $is_completed = in_array($reading->ID, $completed_readings);
            ?>
            <div class="tfp-reading-content-panel" data-content-id="<?php echo esc_attr($reading->ID); ?>">
                <h3 class="tfp-reading-content-title"><?php printf(esc_html__('Currently Reading: %s', 'tfp-dashboard'), esc_html($reading->post_title)); ?></h3>
                <div class="tfp-reading-content-body">
                    <?php echo apply_filters('the_content', $reading->post_content); ?>
                </div>
                <div class="tfp-reading-content-actions">
                    <?php if ($index > 0) : ?>
                        <button class="tfp-dash-btn tfp-dash-btn--primary tfp-reading-prev" data-target="<?php echo esc_attr($readings[$index-1]->ID); ?>"><?php esc_html_e('Previous Reading', 'tfp-dashboard'); ?></button>
                    <?php endif; ?>
                    <button class="tfp-dash-btn  tfp-reading-mark-complete" data-reading-id="<?php echo esc_attr($reading->ID); ?>" data-is-last="<?php echo ($index === $total - 1) ? '1' : '0'; ?>" data-next="<?php echo ($index < $total - 1) ? esc_attr($readings[$index+1]->ID) : ''; ?>">
                        <?php echo ($index === $total - 1) ? esc_html__('Complete & Continue to Homework', 'tfp-dashboard') : esc_html__('Continue Reading', 'tfp-dashboard'); ?>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
            
            <div class="tfp-reading-content-panel tfp-reading-all-done <?php echo $all_completed ? 'is-active' : ''; ?>">
                <h3 class="tfp-reading-content-title"><?php esc_html_e('Reading Complete', 'tfp-dashboard'); ?></h3>
                <div class="tfp-reading-content-body">
                    <p><em><?php esc_html_e("You've completed this passage. Great job stay consistent in your study.", 'tfp-dashboard'); ?></em></p>
                </div>
            </div>
            
            <!-- Default empty panel shown before a reading is launched -->
            <div class="tfp-reading-content-panel tfp-reading-default <?php echo !$all_completed ? 'is-active' : ''; ?>" data-content-id="default">
                <h3 class="tfp-reading-content-title"><?php esc_html_e('Select a Reading to Begin', 'tfp-dashboard'); ?></h3>
                <div class="tfp-reading-content-body">
                    <p><?php esc_html_e('Choose a passage from the list to start your reading for this section. Your progress will be saved automatically.', 'tfp-dashboard'); ?></p>
                    <?php if (!empty($readings)) : ?>
                        <p><button class="tfp-dash-btn tfp-dash-btn--primary tfp-reading-start-first" data-first-reading="<?php echo esc_attr($readings[0]->ID); ?>"><?php esc_html_e('Start First Reading', 'tfp-dashboard'); ?></button></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- GLOBAL FOOTER -->
    <div class="tfp-week__homework-global-footer">
        <div class="tfp-week__homework-footer-left">
            <!-- Back to video is now inside the sidebar, keep it there as per design, or should we move it here? -->
              <button class="tfp-dash-btn tfp-dash-btn--primary tfp-reading-back-video" onclick="window.location.search = '?lesson_id=<?php echo $week->ID; ?>&tab=video'"><svg xmlns="http://www.w3.org/2000/svg" width="5" height="8" viewBox="0 0 5 8" fill="none">
                <path d="M4.93994 0.94L1.88661 4L4.93994 7.06L3.99994 8L-5.88141e-05 4L3.99994 -4.10887e-08L4.93994 0.94Z" fill="currentColor"/>
                </svg> <?php esc_html_e('Back to Video', 'tfp-dashboard'); ?></button>
        </div>
        
        <div class="tfp-week__homework-footer-right">
            <a href="?lesson_id=<?php echo $week->ID; ?>&tab=homework" class="tfp-dash-btn tfp-dash-btn--primary tfp-reading-go-homework" style="<?php echo $all_completed ? 'display:inline-flex;' : 'display:none;'; ?>"><?php esc_html_e('Continue to Homework', 'tfp-dashboard'); ?> <svg xmlns="http://www.w3.org/2000/svg" width="12" height="10" viewBox="0 0 12 10" fill="none" style="margin-left:8px;"><path d="M1 5H11M7 9L11 5L7 1" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
        </div>
    </div>
    <?php
}

function tfp_dashboard_render_week_homework_tab($week, $user_id)
{
    $questions = tfp_week_get_homework_questions($week->ID, true);
    
    if (empty($questions)) {
        tfp_dashboard_render_week_placeholder_tab('homework', 'Homework');
        return;
    }

    $progress = tfp_ld_get_week_progress($user_id, $week->ID);
    $answers = tfp_week_get_homework_answers($user_id, $week->ID);
    $homework_progress = tfp_week_homework_progress($user_id, $week->ID);
    $total = $homework_progress['total'];
    $completed = $homework_progress['completed'];
    $homework_complete = $total > 0 && $completed === $total;
    $is_submitted = !empty($progress['homework']) && $homework_complete;
    $can_continue_to_quiz = !empty($progress['homework']) && $homework_complete;

    $reading_url = "?lesson_id={$week->ID}&tab=reading";
    
    // Initial state calculation for JS
    $initial_state = 'state-1';
    if ($is_submitted) {
        $initial_state = 'state-review'; // Post-submission review
    } elseif ($completed === $total) {
        $initial_state = 'state-3'; // Ready to submit
    } elseif (!empty($answers)) {
        $initial_state = 'state-2'; // In progress
    }
    ?>
    <div class="tfp-week__homework" data-lesson-id="<?php echo esc_attr($week->ID); ?>" data-state="<?php echo esc_attr($initial_state); ?>" data-submitted="<?php echo $is_submitted ? '1' : '0'; ?>" data-completed="<?php echo esc_attr($completed); ?>" data-total="<?php echo esc_attr($total); ?>">
        <div class="tfp-week__homework-sidebar">
            <h4 class="tfp-week__homework-progress-title">
                <?php printf(esc_html__('Homework Progress — %d of %d Completed', 'tfp-dashboard'), $completed, $total); ?>
            </h4>
            
            <div class="tfp-week__homework-list">
                <?php foreach ($questions as $index => $q) : 
                    $is_answered = tfp_week_is_question_answered($q, isset($answers[$q['id']]) ? $answers[$q['id']] : []);
                    $status_text = $is_answered ? __('Completed', 'tfp-dashboard') : __('Not Started', 'tfp-dashboard');
                    $btn_text = $is_answered ? __('Edit Answer', 'tfp-dashboard') : __('Answer Question', 'tfp-dashboard');
                ?>
                <div class="tfp-week__homework-list-item <?php echo $is_answered ? 'is-completed' : ''; ?>" data-question-id="<?php echo esc_attr($q['id']); ?>" data-index="<?php echo $index; ?>" style="cursor: pointer;">
                    <div class="tfp-week__homework-list-item-info">
                        <div class="tfp-week__homework-list-item-title"><?php echo esc_html(wp_trim_words($q['prompt'], 5, '...')); ?></div>
                        <div class="tfp-week__homework-list-item-status">Status: <span><?php echo esc_html($status_text); ?></span></div>
                    </div>
                    <button class="tfp-dash-btn tfp-dash-btn--sm <?php echo $is_answered ? 'tfp-reded-btn' : 'tfp-dash-btn--primary'; ?> tfp-homework-nav-btn">
                        <?php echo esc_html($btn_text); ?>
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="tfp-week__homework-main">
            <!-- STATE 1: Ready to Begin -->
            <div class="tfp-week__homework-panel tfp-week__homework-state-1" <?php echo ($initial_state === 'state-1') ? 'style="display:block;"' : 'style="display:none;"'; ?>>
                <h3 class="tfp-week__homework-title"><?php printf(esc_html__('Ready to Begin Homework: %s', 'tfp-dashboard'), esc_html($week->post_title)); ?></h3>
                <div class="tfp-week__homework-desc">
                    <p><?php esc_html_e("This section helps you reflect on what you've learned through the readings. Take your time and answer thoughtfully before moving to the quiz.", 'tfp-dashboard'); ?></p>
                </div>
                <div class="tfp-week__homework-actions" style="margin-top: 24px;">
                    <button class="tfp-dash-btn tfp-reded-btn tfp-homework-start-btn"><?php esc_html_e('Start Homework', 'tfp-dashboard'); ?></button>
                </div>
            </div>

            <!-- STATE 2: In Progress (Questions) -->
            <div class="tfp-week__homework-panel tfp-week__homework-state-2" <?php echo ($initial_state === 'state-2') ? 'style="display:block;"' : 'style="display:none;"'; ?>>
                <?php foreach ($questions as $index => $q) : 
                    $ans = isset($answers[$q['id']]) ? $answers[$q['id']] : [];
                ?>
                <div class="tfp-week__homework-question-container" data-question-id="<?php echo esc_attr($q['id']); ?>" data-index="<?php echo $index; ?>" style="display:none;">
                    <h4 class="tfp-week__homework-q-counter"><?php printf(esc_html__('Question %d of %d', 'tfp-dashboard'), $index + 1, $total); ?></h4>
                    <div class="tfp-week__homework-q-prompt">
                        <span class="tfp-week__homework-q-num"><?php echo ($index + 1); ?>.</span>
                        <span class="tfp-week__homework-q-text"><?php echo esc_html($q['prompt']); ?></span>
                    </div>
                    
                    <div class="tfp-week__homework-q-inputs">
                        <?php if ($q['type'] === 'multiple_choice' && !empty($q['options'])) : ?>
                            <div class="tfp-week__homework-options">
                                <?php foreach ($q['options'] as $opt_idx => $opt_text) : 
                                    $checked = (isset($ans['selected_index']) && (int)$ans['selected_index'] === $opt_idx) ? 'checked' : '';
                                ?>
                                <label class="tfp-week__homework-option">
                                    <input type="radio" name="hw_<?php echo esc_attr($q['id']); ?>" value="<?php echo esc_attr($opt_idx); ?>" <?php echo $checked; ?>>
                                    <span><?php echo esc_html($opt_text); ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($q['type'] === 'both') : 
                            $yn = isset($ans['yes_no']) ? $ans['yes_no'] : '';
                        ?>
                            <div class="tfp-week__homework-yesno">
                                <label><input type="radio" name="yn_<?php echo esc_attr($q['id']); ?>" value="yes" <?php echo $yn === 'yes' ? 'checked' : ''; ?>> <?php esc_html_e('Yes', 'tfp-dashboard'); ?></label>
                                <label><input type="radio" name="yn_<?php echo esc_attr($q['id']); ?>" value="no" <?php echo $yn === 'no' ? 'checked' : ''; ?>> <?php esc_html_e('No', 'tfp-dashboard'); ?></label>
                            </div>
                        <?php endif; ?>

                        <?php if ($q['type'] === 'written' || $q['type'] === 'both') : 
                            $text = isset($ans['text']) ? $ans['text'] : '';
                        ?>
                            <div class="tfp-week__homework-textarea">
                                <textarea name="text_<?php echo esc_attr($q['id']); ?>" placeholder="<?php esc_attr_e('type your answer here...', 'tfp-dashboard'); ?>" rows="6"><?php echo esc_textarea($text); ?></textarea>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="tfp-week__homework-nav" data-index="<?php echo $index; ?>" style="display:<?php echo ($index === 0) ? 'flex' : 'none'; ?>; align-items:center; margin-top:24px;">
                        <?php if ($index > 0) : ?>
                            <button class="tfp-dash-btn tfp-dash-btn--primary tfp-homework-prev" data-target="<?php echo esc_attr($questions[$index - 1]['id']); ?>" style="margin-right:12px;"><?php esc_html_e('Previous', 'tfp-dashboard'); ?></button>
                        <?php endif; ?>
                        
                        <?php if ($index < $total - 1) : ?>
                            <button class="tfp-dash-btn tfp-reded-btn tfp-homework-next" data-target="<?php echo esc_attr($questions[$index + 1]['id']); ?>"><?php esc_html_e('Next', 'tfp-dashboard'); ?></button>
                        <?php else : ?>
                            <button class="tfp-dash-btn tfp-reded-btn tfp-homework-finish"><?php esc_html_e('Finish', 'tfp-dashboard'); ?></button>
                        <?php endif; ?>
                        <span class="tfp-homework-saving-indicator" style="display:none; margin-left:12px; font-size:12px; color:var(--tfp-dash-muted);"><?php esc_html_e('Saving...', 'tfp-dashboard'); ?></span>
                    </div>

                </div>
                <?php endforeach; ?>
            </div>

            <!-- STATE 3: All answered (Ready to submit) -->
            <div class="tfp-week__homework-panel tfp-week__homework-state-3" <?php echo ($initial_state === 'state-3') ? 'style="display:block;"' : 'style="display:none;"'; ?>>
                <h3 class="tfp-week__homework-title"><?php esc_html_e('Homework Complete', 'tfp-dashboard'); ?></h3>
                <div class="tfp-week__homework-desc">
                    <p><?php esc_html_e("You've answered all the questions for this section. Review your responses if needed, then submit your homework for review to unlock the next step.", 'tfp-dashboard'); ?></p>
                </div>
                <div class="tfp-week__homework-actions" style="margin-top: 24px; display: flex; gap: 16px;">
                    <button class="tfp-dash-btn tfp-dash-btn--primary tfp-homework-review-btn"><?php esc_html_e('Review Answers', 'tfp-dashboard'); ?></button>
                    <button class="tfp-dash-btn tfp-reded-btn tfp-homework-submit-btn"><?php esc_html_e('Submit Homework for Review', 'tfp-dashboard'); ?></button>
                </div>
            </div>

            <!-- STATE 4: Review (Read-only after submission) -->
            <div class="tfp-week__homework-panel tfp-week__homework-state-review" <?php echo ($initial_state === 'state-review') ? 'style="display:block;"' : 'style="display:none;"'; ?>>
                <h3 class="tfp-week__homework-title"><?php esc_html_e('Review Answers:', 'tfp-dashboard'); ?></h3>
                <div class="tfp-week__homework-review-list">
                    <?php foreach ($questions as $index => $q) : 
                        $ans = isset($answers[$q['id']]) ? $answers[$q['id']] : [];
                    ?>
                    <div class="tfp-week__homework-review-item">
                        <div class="tfp-week__homework-review-q">
                            <strong><?php printf(esc_html__('Q%d. %s', 'tfp-dashboard'), $index + 1, esc_html($q['prompt'])); ?></strong>
                        </div>
                        <div class="tfp-week__homework-review-a">
                            <?php 
                            if ($q['type'] === 'multiple_choice') {
                                $opt_idx = isset($ans['selected_index']) ? (int)$ans['selected_index'] : -1;
                                $ans_text = isset($q['options'][$opt_idx]) ? $q['options'][$opt_idx] : '—';
                                echo '<em>' . esc_html__('Your Answer: ', 'tfp-dashboard') . '</em>' . esc_html($ans_text);
                            } else {
                                if ($q['type'] === 'both' && isset($ans['yes_no'])) {
                                    echo '<strong>' . esc_html(ucfirst($ans['yes_no'])) . '</strong><br>';
                                }
                                $text = isset($ans['text']) ? $ans['text'] : '—';
                                echo '<em>' . esc_html__('Your Answer: ', 'tfp-dashboard') . '</em><br>' . nl2br(esc_html($text));
                            }
                            ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (!$is_submitted) : ?>
                <div class="tfp-week__homework-actions" style="margin-top: 32px; display: flex; justify-content: flex-end;">
                    <button class="tfp-dash-btn tfp-reded-btn tfp-homework-submit-btn"><?php esc_html_e('Submit Homework for Review', 'tfp-dashboard'); ?></button>
                </div>
                <?php endif; ?>
            </div>

        </div>
        
    </div>

    <!-- GLOBAL FOOTER -->
    <div class="tfp-week__homework-global-footer">
        <div class="tfp-week__homework-footer-left">
            <a href="<?php echo esc_url($reading_url); ?>" class="tfp-dash-btn tfp-dash-btn--primary tfp-week__homework-back"><svg xmlns="http://www.w3.org/2000/svg" width="5" height="8" viewBox="0 0 5 8" fill="none" style="margin-right:8px;"><path d="M4.93994 0.94L1.88661 4L4.93994 7.06L3.99994 8L-5.88141e-05 4L3.99994 -4.10887e-08L4.93994 0.94Z" fill="currentColor"/></svg> <?php esc_html_e('Back to Reading', 'tfp-dashboard'); ?></a>
        </div>
        
        <div class="tfp-week__homework-footer-right">
            <a href="<?php echo esc_url(add_query_arg(['lesson_id' => $week->ID, 'tab' => 'quiz'])); ?>" class="tfp-dash-btn tfp-dash-btn--primary tfp-homework-next-step-btn<?php echo $can_continue_to_quiz ? '' : ' is-disabled'; ?>" aria-disabled="<?php echo $can_continue_to_quiz ? 'false' : 'true'; ?>"<?php echo $can_continue_to_quiz ? '' : ' tabindex="-1"'; ?>><?php esc_html_e('Continue to Quiz', 'tfp-dashboard'); ?> <svg xmlns="http://www.w3.org/2000/svg" width="12" height="10" viewBox="0 0 12 10" fill="none" style="margin-left:8px;"><path d="M1 5H11M7 9L11 5L7 1" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
        </div>
    </div>
    <?php
}
