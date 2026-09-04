<?php
if (!defined('ABSPATH')) exit;

function tfp_dashboard_render_update_profile_content()
{
    $user = wp_get_current_user();
    $profile_url = function_exists('tfp_dashboard_get_url') ? tfp_dashboard_get_url('tfp-dashboard-profile') : '#';

    tfp_dashboard_render_page_header(
        __('Update Profile', 'tfp-dashboard'),
        sprintf(esc_html__('%s, Edit Your Profile', 'tfp-dashboard'), esc_html($user->first_name ?: $user->display_name)),
        __('Keep your profile up to date so we can connect you with classes, facilitators, and reminders.', 'tfp-dashboard')
    );
    ?>
    <div class="tfp-dash-panel tfp-dash-form-card">
        <form data-tfp-profile-form>
            <input type="hidden" name="section" value="personal">

            <div class="tfp-dash-form__grid">
                <div class="tfp-dash-form__photo">
                    <span data-tfp-avatar-preview><?php echo tfp_dashboard_avatar(160); ?></span>
                    <label class="tfp-dash-form__photo-upload">
                        <?php esc_html_e('Update My Photo', 'tfp-dashboard'); ?>
                        <input type="file" accept="image/*" data-tfp-avatar-input hidden>
                    </label>
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
                        <?php esc_html_e('Address', 'tfp-dashboard'); ?>
                        <input type="text" name="address" value="<?php echo esc_attr(get_user_meta($user->ID, 'tfp_address', true)); ?>">
                    </label>
                    <label>
                        <?php esc_html_e('Phone Number', 'tfp-dashboard'); ?>
                        <input type="text" name="phone" value="<?php echo esc_attr(get_user_meta($user->ID, 'billing_phone', true)); ?>">
                    </label>
                    <label>
                        <?php esc_html_e('Date of Birth', 'tfp-dashboard'); ?>
                        <input type="date" name="date_of_birth" value="<?php echo esc_attr(get_user_meta($user->ID, 'tfp_date_of_birth', true)); ?>">
                    </label>
                    <label>
                        <?php esc_html_e('Timezone', 'tfp-dashboard'); ?>
                        <input type="text" name="timezone" value="<?php echo esc_attr(get_user_meta($user->ID, 'tfp_timezone', true)); ?>" placeholder="<?php esc_attr_e('e.g. PST', 'tfp-dashboard'); ?>">
                    </label>

                    <label>
                        <?php esc_html_e('City', 'tfp-dashboard'); ?>
                        <input type="text" name="shipping_city" value="<?php echo esc_attr(get_user_meta($user->ID, 'shipping_city', true)); ?>">
                    </label>
                    <label>
                        <?php esc_html_e('ZIP / Postal Code', 'tfp-dashboard'); ?>
                        <input type="text" name="shipping_postcode" value="<?php echo esc_attr(get_user_meta($user->ID, 'shipping_postcode', true)); ?>">
                    </label>
                    <label>
                        <?php esc_html_e('Country', 'tfp-dashboard'); ?>
                        <input type="text" name="shipping_country" value="<?php echo esc_attr(get_user_meta($user->ID, 'shipping_country', true)); ?>">
                    </label>

                    <label>
                        <?php esc_html_e('Discord User Name', 'tfp-dashboard'); ?>
                        <input type="text" name="discord_username" value="<?php echo esc_attr(get_user_meta($user->ID, 'tfp_discord_username', true)); ?>" placeholder="<?php esc_attr_e('e.g. yourname_1234', 'tfp-dashboard'); ?>">
                    </label>

                    <label>
                        <?php esc_html_e('Email', 'tfp-dashboard'); ?>
                        <input type="email" name="email" value="<?php echo esc_attr($user->user_email); ?>" required>
                    </label>

                    <label class="tfp-dash-form__checkbox">
                        <input type="checkbox" name="email_optin" value="1" <?php checked((int) get_user_meta($user->ID, 'tfp_email_optin', true), 1); ?>>
                        <?php esc_html_e('I agree to receive emails from The Follow Project. I can unsubscribe anytime.', 'tfp-dashboard'); ?>
                    </label>

                    <button type="submit" class="tfp-dash-btn tfp-dash-btn--primary tfp-dash-form__submit">
                        <?php esc_html_e('Update Information', 'tfp-dashboard'); ?>
                    </button>

                    <p class="tfp-dash-form__status" data-tfp-profile-status role="status"></p>

                    <a href="<?php echo esc_url($profile_url); ?>" class="tfp-dash-btn tfp-dash-btn--primary">
                        <?php esc_html_e('Back to Profile', 'tfp-dashboard'); ?>
                    </a>
                </div>
            </div>
        </form>
    </div>
    <?php
}
