<?php
if (!defined('ABSPATH')) exit;

function tfp_dashboard_render_financial_aid_content()
{
    $user = wp_get_current_user();

    tfp_dashboard_render_page_header(
        __('Apply Financial Aid', 'tfp-dashboard'),
        __('Apply for Financial Aid?', 'tfp-dashboard'),
        __('Keep your profile up to date so we can connect you with classes, facilitators, and reminders.', 'tfp-dashboard')
    );
    ?>
    <div class="tfp-dash-billing-detail__intro">
         <h2 class="tfp-dash-section__hi"><?php printf(esc_html__('%s, Apply for Scholarship or Financial Assistance', 'tfp-dashboard'), esc_html($user->first_name ?: $user->display_name)); ?></h2>
        <p class="tfp-dash-section__lead"><?php esc_html_e('Please complete this application, and our team will review your request within 3–5 business days.', 'tfp-dashboard'); ?></p>

    </div>
    <div class="tfp-dash-panel tfp-dash-form-card">
       

        <form class="tfp-dash-form" data-tfp-financial-aid-form enctype="multipart/form-data">
            <div class="tfp-dash-form__grid">
                <div class="tfp-dash-form__photo">
                    <?php echo tfp_dashboard_avatar(140); ?>
                </div>

                <div class="tfp-dash-form__fields">
                    <label>
                        <?php esc_html_e('First Name', 'tfp-dashboard'); ?>
                        <input type="text" name="first_name" value="<?php echo esc_attr($user->first_name); ?>" required>
                    </label>
                    <label>
                        <?php esc_html_e('Last Name', 'tfp-dashboard'); ?>
                        <input type="text" name="last_name" value="<?php echo esc_attr($user->last_name); ?>" required>
                    </label>
                    <label>
                        <?php esc_html_e('Phone Number', 'tfp-dashboard'); ?>
                        <input type="text" name="phone" value="<?php echo esc_attr(get_user_meta($user->ID, 'billing_phone', true)); ?>" required>
                    </label>
                    <label>
                        <?php esc_html_e('Address/Location', 'tfp-dashboard'); ?>
                        <input type="text" name="address">
                    </label>
                    <label>
                        <?php esc_html_e('Country', 'tfp-dashboard'); ?>
                        <input type="text" name="country">
                    </label>

                    <label>
                        <?php esc_html_e('Which program are you applying for?', 'tfp-dashboard'); ?>
                        <select name="program_id" required>
                            <option value=""><?php esc_html_e('Select a program', 'tfp-dashboard'); ?></option>
                            <?php foreach (tfp_dashboard_get_programs_list() as $program) : ?>
                                <option value="<?php echo esc_attr($program['id']); ?>"><?php echo esc_html($program['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <fieldset>
                        <legend><?php esc_html_e('Have you already registered for this program?', 'tfp-dashboard'); ?></legend>
                        <label class="tfp-dash-form__radio"><input type="radio" name="already_registered" value="yes" required> <?php esc_html_e('Yes', 'tfp-dashboard'); ?></label>
                        <label class="tfp-dash-form__radio"><input type="radio" name="already_registered" value="no"> <?php esc_html_e('No', 'tfp-dashboard'); ?></label>
                    </fieldset>

                    <fieldset>
                        <legend><?php esc_html_e('Do you need full or partial aid?', 'tfp-dashboard'); ?></legend>
                        <label class="tfp-dash-form__radio"><input type="radio" name="aid_type" value="full" required> <?php esc_html_e('Full', 'tfp-dashboard'); ?></label>
                        <label class="tfp-dash-form__radio"><input type="radio" name="aid_type" value="partial"> <?php esc_html_e('Partial', 'tfp-dashboard'); ?></label>
                    </fieldset>

                    <label>
                        <?php esc_html_e('If partial, how much can you contribute? (e.g. $25, $50, etc.)', 'tfp-dashboard'); ?>
                        <input type="text" name="contribution_amount">
                    </label>

                    <label>
                        <?php esc_html_e('Please describe your current financial situation:', 'tfp-dashboard'); ?>
                        <textarea name="financial_situation" rows="3" required></textarea>
                    </label>

                    <fieldset>
                        <legend><?php esc_html_e('Do you currently have a job?', 'tfp-dashboard'); ?></legend>
                        <label class="tfp-dash-form__radio"><input type="radio" name="has_job" value="yes" required> <?php esc_html_e('Yes', 'tfp-dashboard'); ?></label>
                        <label class="tfp-dash-form__radio"><input type="radio" name="has_job" value="no"> <?php esc_html_e('No', 'tfp-dashboard'); ?></label>
                    </fieldset>

                    <label>
                        <?php esc_html_e('If yes: What kind of work do you do, and how much do you earn monthly?', 'tfp-dashboard'); ?>
                        <input type="text" name="job_details">
                    </label>

                    <label>
                        <?php esc_html_e('How many people are in your household?', 'tfp-dashboard'); ?>
                        <input type="text" name="household_size">
                    </label>

                    <fieldset>
                        <legend><?php esc_html_e('Are you supporting anyone else financially?', 'tfp-dashboard'); ?></legend>
                        <label class="tfp-dash-form__radio"><input type="radio" name="supporting_others" value="yes" required> <?php esc_html_e('Yes', 'tfp-dashboard'); ?></label>
                        <label class="tfp-dash-form__radio"><input type="radio" name="supporting_others" value="no"> <?php esc_html_e('No', 'tfp-dashboard'); ?></label>
                    </fieldset>

                    <fieldset>
                        <legend><?php esc_html_e('Have you received aid from us before?', 'tfp-dashboard'); ?></legend>
                        <label class="tfp-dash-form__radio"><input type="radio" name="received_aid_before" value="yes" required> <?php esc_html_e('Yes', 'tfp-dashboard'); ?></label>
                        <label class="tfp-dash-form__radio"><input type="radio" name="received_aid_before" value="no"> <?php esc_html_e('No', 'tfp-dashboard'); ?></label>
                    </fieldset>

                    <label>
                        <?php esc_html_e('Referral Code (optional):', 'tfp-dashboard'); ?>
                        <input type="text" name="referral_code" placeholder="<?php esc_attr_e('Enter a code if you were referred by a partner, mentor, or event.', 'tfp-dashboard'); ?>">
                    </label>

                    <label>
                        <?php esc_html_e('Recommendation Letter (optional):', 'tfp-dashboard'); ?>
                        <input type="file" name="recommendation_letter" accept=".pdf,.doc,.docx,.jpg,.png">
                    </label>

                    <label>
                        <?php esc_html_e("Anything else you'd like to share?", 'tfp-dashboard'); ?>
                        <input type="text" name="additional_comments">
                    </label>

                    <button type="submit" class="tfp-dash-btn tfp-dash-btn--primary tfp-dash-form__submit">
                        <?php esc_html_e('Submit Application', 'tfp-dashboard'); ?>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <div class="tfp-dash-modal" data-tfp-aid-success-modal hidden>
        <div class="tfp-dash-modal__backdrop"></div>
        <div class="tfp-dash-modal__box tfp-dash-modal__box--center">
            <?php
            $logo_id = get_theme_mod('custom_logo');
            if ($logo_id) {
                echo wp_get_attachment_image($logo_id, 'medium');
            }
            ?>
            <h4><?php esc_html_e('Thank You for Your Application', 'tfp-dashboard'); ?></h4>
            <p><?php esc_html_e('Our team will review your application and email you with next steps within 3–5 business days. Please keep an eye on your inbox (and spam folder).', 'tfp-dashboard'); ?></p>
            <a href="<?php echo esc_url(tfp_dashboard_get_url('tfp-dashboard-home')); ?>" class="tfp-dash-btn tfp-dash-btn--primary">
                <?php esc_html_e('Go to Dashboard', 'tfp-dashboard'); ?>
            </a>
        </div>
    </div>
    <?php
}

/**
 * Programs list for the dropdown. Uses LearnDash courses because
 * `tfp_program_choice` stores the course ID, not a WooCommerce product ID.
 */
function tfp_dashboard_get_programs_list()
{
    $programs = [];

    if (!post_type_exists('sfwd-courses')) {
        return $programs;
    }

    $posts = get_posts([
        'post_type'      => 'sfwd-courses',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ]);

    foreach ($posts as $post) {
        $programs[] = ['id' => $post->ID, 'title' => $post->post_title];
    }

    return $programs;
}
