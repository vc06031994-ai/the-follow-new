<?php
if (!defined('ABSPATH')) exit;

function tfp_dashboard_render_profile_content()
{
    $user = wp_get_current_user();
    $user_id = $user->ID;

    $full_name = trim($user->first_name . ' ' . $user->last_name);
    if (empty($full_name)) {
        $full_name = $user->display_name;
    }

    $phone     = get_user_meta($user_id, 'billing_phone', true);
    $address   = get_user_meta($user_id, 'tfp_address', true);
    $dob       = get_user_meta($user_id, 'tfp_date_of_birth', true);
    $timezone  = get_user_meta($user_id, 'tfp_timezone', true);
    $discord   = get_user_meta($user_id, 'tfp_discord_username', true);

    $preferred_contact = (array) get_user_meta($user_id, 'tfp_preferred_contact', true);
    $notifications      = (array) get_user_meta($user_id, 'tfp_notification_settings', true);

    $contact_options = [
        'email'   => __('Email', 'tfp-dashboard'),
        'discord' => __('Discord', 'tfp-dashboard'),
        'phone'   => __('Phone', 'tfp-dashboard'),
    ];

    $notification_options = [
        'payments'               => __('Payments', 'tfp-dashboard'),
        'facilitator_alerts'     => __('Facilitator Alerts', 'tfp-dashboard'),
        'class_reminders'        => __('Class Reminders', 'tfp-dashboard'),
        'communication_tickets'  => __('Communication Tickets', 'tfp-dashboard'),
    ];

    $card = function_exists('tfp_billing_get_default_card_summary') ? tfp_billing_get_default_card_summary($user_id) : null;
    $billing_email = function_exists('tfp_billing_get_billing_email') ? tfp_billing_get_billing_email($user_id) : $user->user_email;
    $payment_details_url = function_exists('tfp_dashboard_get_url') ? tfp_dashboard_get_url('tfp-dashboard-payment-details') : '#';

    tfp_dashboard_render_page_header(
        __('Profile', 'tfp-dashboard'),
        sprintf(esc_html__('%s, Edit Your Profile', 'tfp-dashboard'), esc_html($user->first_name ?: $user->display_name)),
        __('Keep your profile up to date so we can connect you with classes, facilitators, and reminders.', 'tfp-dashboard')
    );
    ?>

    <!-- Header identity card -->
    <div class="tfp-dash-panel tfp-profile-header">
        <div class="tfp-profile-header__avatar-wrap">
            <span class="tfp-profile-header__avatar" data-tfp-avatar-preview>
                <?php echo tfp_dashboard_avatar(96); ?>
            </span>
            <label class="tfp-profile-header__avatar-edit">
                <?php echo tfp_dashboard_icon('edit'); ?>
                <input type="file" accept="image/*" data-tfp-avatar-input hidden>
            </label>
        </div>

        <div class="tfp-profile-header__info">
            <h2 class="tfp-profile-header__name"><?php echo esc_html($full_name); ?></h2>
            <div class="tfp-profile-header__meta">
                <?php if ($discord) : ?>
                    <span><?php echo tfp_dashboard_icon('discord'); ?><?php echo esc_html($discord); ?></span>
                <?php endif; ?>
                <span><?php echo tfp_dashboard_icon('mail'); ?><?php echo esc_html($user->user_email); ?></span>
                <?php if ($timezone) : ?>
                    <span><?php echo tfp_dashboard_icon('clock'); ?><?php printf(esc_html__('Timezone: %s', 'tfp-dashboard'), esc_html($timezone)); ?></span>
                <?php endif; ?>
            </div>
            <span class="tfp-profile-header__badge"><?php echo esc_html(tfp_dashboard_user_role_label()); ?></span>
        </div>
    </div>

    <div class="tfp-profile-grid">

        <!-- Personal Information -->
        <div class="tfp-dash-panel tfp-profile-section">
            <div class="tfp-profile-section__header">
                <h5><?php esc_html_e('Personal Information', 'tfp-dashboard'); ?></h5>
                <a href="<?php echo esc_url(function_exists('tfp_dashboard_get_url') ? tfp_dashboard_get_url('tfp-dashboard-update-profile') : '#'); ?>" class="tfp-dash-btn tfp-dash-btn--primary tfp-profile-section__edit-btn">
                    <?php echo tfp_dashboard_icon('edit'); ?> <?php esc_html_e('Edit', 'tfp-dashboard'); ?>
                </a>
            </div>

            <div class="tfp-profile-section__view">
                <div class="tfp-profile-row"><span><?php esc_html_e('Full Name', 'tfp-dashboard'); ?></span><p data-field="full_name"><?php echo esc_html($full_name); ?></p></div>
                <div class="tfp-profile-row"><span><?php esc_html_e('Email', 'tfp-dashboard'); ?></span><p data-field="email"><?php echo esc_html($user->user_email); ?></p></div>
                <div class="tfp-profile-row"><span><?php esc_html_e('Phone Number', 'tfp-dashboard'); ?></span><p data-field="phone"><?php echo esc_html($phone ?: '—'); ?></p></div>
                <div class="tfp-profile-row"><span><?php esc_html_e('Date of Birth', 'tfp-dashboard'); ?></span><p data-field="dob"><?php echo esc_html($dob ?: '—'); ?></p></div>
                <div class="tfp-profile-row"><span><?php esc_html_e('Address', 'tfp-dashboard'); ?></span><p data-field="address"><?php echo esc_html($address ?: '—'); ?></p></div>
                <div class="tfp-profile-row"><span><?php esc_html_e('Timezone', 'tfp-dashboard'); ?></span><p data-field="timezone"><?php echo esc_html($timezone ?: '—'); ?></p></div>
            </div>
        </div>

        <!-- Billing Information (view-only — real card editing happens via WooCommerce's secure Payment Details page) -->
         <div >
              <div class="tfp-dash-panel tfp-profile-section">
            <div class="tfp-profile-section__header">
                <h5><?php esc_html_e('Billing Information', 'tfp-dashboard'); ?></h5>
                <a href="<?php echo esc_url($payment_details_url); ?>" class="tfp-dash-btn tfp-dash-btn--primary">
                    <?php echo tfp_dashboard_icon('edit'); ?> <?php esc_html_e('Edit', 'tfp-dashboard'); ?>
                </a>
            </div>
            <p class="tfp-profile-section__sub"><?php esc_html_e('Your saved payment method from enrollment', 'tfp-dashboard'); ?></p>

            <div class="tfp-profile-section__view">
                <?php if ($card) : ?>
                    <div class="tfp-profile-row"><span><?php esc_html_e('Card on File', 'tfp-dashboard'); ?></span><p><?php printf('%s ending in %s', esc_html($card['brand']), esc_html($card['last4'])); ?></p></div>
                    <div class="tfp-profile-row"><span><?php esc_html_e('Expiration', 'tfp-dashboard'); ?></span><p><?php echo esc_html($card['expiry']); ?></p></div>
                <?php else : ?>
                    <div class="tfp-profile-row"><span><?php esc_html_e('Card on File', 'tfp-dashboard'); ?></span><p><?php esc_html_e('No card saved yet', 'tfp-dashboard'); ?></p></div>
                <?php endif; ?>
                <div class="tfp-profile-row"><span><?php esc_html_e('Billing Email', 'tfp-dashboard'); ?></span><p><?php echo esc_html($billing_email); ?></p></div>
            </div>
        </div>

        <!-- Connection Details -->
        <div class="tfp-dash-panel tfp-profile-section" data-tfp-section="connection" style="margin-top:20px">
            <div class="tfp-profile-section__header">
                <h5><?php esc_html_e('Connection Details', 'tfp-dashboard'); ?></h5>
                <button type="button" class="tfp-dash-btn tfp-dash-btn--primary tfp-profile-section__edit-btn" data-tfp-edit-toggle>
                    <?php echo tfp_dashboard_icon('edit'); ?> <?php esc_html_e('Edit', 'tfp-dashboard'); ?>
                </button>
            </div>
            <p class="tfp-profile-section__sub"><?php esc_html_e('How we reach you outside the LMS', 'tfp-dashboard'); ?></p>

            <div class="tfp-profile-section__view">
                <div class="tfp-profile-row"><span><?php esc_html_e('Discord Username', 'tfp-dashboard'); ?></span><p data-field="discord_username"><?php echo esc_html($discord ?: '—'); ?></p></div>
                <div class="tfp-profile-row">
                    <span><?php esc_html_e('Preferred Contact', 'tfp-dashboard'); ?></span>
                    <p data-field="preferred_contact"><?php echo esc_html(!empty($preferred_contact) ? implode(', ', array_map(fn($k) => $contact_options[$k] ?? $k, $preferred_contact)) : '—'); ?></p>
                </div>
                <div class="tfp-profile-row">
                    <span><?php esc_html_e('Notification Settings', 'tfp-dashboard'); ?></span>
                    <p data-field="notification_settings"><?php echo esc_html(!empty($notifications) ? implode(', ', array_map(fn($k) => $notification_options[$k] ?? $k, $notifications)) : '—'); ?></p>
                </div>
            </div>

            <form class="tfp-profile-section__edit-form" data-tfp-profile-form data-section="connection" hidden>
                <input type="hidden" name="section" value="connection">
                <label>
                    <?php esc_html_e('Discord Username', 'tfp-dashboard'); ?>
                    <input type="text" name="discord_username" value="<?php echo esc_attr($discord); ?>" placeholder="<?php esc_attr_e('e.g. yourname_1234', 'tfp-dashboard'); ?>">
                </label>

                <fieldset class="tfp-profile-checkgroup">
                    <span><?php esc_html_e('Preferred Contact', 'tfp-dashboard'); ?></span>
                    <?php foreach ($contact_options as $key => $label) : ?>
                        <div class="tfp-profile-checkgroup__item">
                            <label class="tfp-dash-form__checkbox">
                                <input type="checkbox" name="preferred_contact[]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, $preferred_contact, true)); ?>>
                                <?php echo esc_html($label); ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </fieldset>

                <fieldset class="tfp-profile-checkgroup">
                    <span><?php esc_html_e('Notification Settings', 'tfp-dashboard'); ?></span>
                    <?php foreach ($notification_options as $key => $label) : ?>
                        <div class="tfp-profile-checkgroup__item">
                            <label class="tfp-dash-form__checkbox">
                                <input type="checkbox" name="notification_settings[]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, $notifications, true)); ?>>
                                <?php echo esc_html($label); ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </fieldset>

                <div class="tfp-profile-section__edit-actions">
                    <button type="button" class="tfp-dash-btn tfp-dash-btn--primary" data-tfp-edit-cancel><?php esc_html_e('Cancel', 'tfp-dashboard'); ?></button>
                    <button type="submit" class="tfp-dash-btn tfp-dash-btn--primary"><?php esc_html_e('Save Changes', 'tfp-dashboard'); ?></button>
                </div>
                <p class="tfp-dash-form__status" data-tfp-profile-status role="status"></p>
            </form>
        </div>
    </div>
    <?php
}
