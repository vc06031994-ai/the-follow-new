<?php
if (!defined('ABSPATH')) exit;

/**
 * A single reusable task card: name, heading, description, progress ring,
 * button. $type lets future logic (billing, payment) plug in their own %
 * source via the tfp_dashboard_task_card_percent filter without rewriting
 * this.
 */
function tfp_dashboard_render_task_card($type, $name, $heading, $description, $button_text, $button_url)
{
    $percent = 0;

    if ($type === 'profile') {
        $percent = tfp_dashboard_profile_completion();
    } elseif ($type === 'billing' && function_exists('tfp_billing_user_has_saved_payment_method')) {
        $percent = tfp_billing_user_has_saved_payment_method() ? 100 : 0;
    } elseif ($type === 'program_payment' && function_exists('tfp_billing_user_has_paid')) {
        $percent = tfp_billing_user_has_paid() ? 100 : 0;
    }

    // Future task types (billing, program_payment, etc.) can hook in here.
    $percent = apply_filters('tfp_dashboard_task_card_percent', $percent, $type);

    ?>
    <div class="tfp-dash-panel tfp-dash-taskcard">
        <div class="tfp-dash-taskcard__body">
            <h3 class="tfp-dash-taskcard__title"><?php echo esc_html($name); ?></h3>
            <p class="tfp-dash-taskcard__label"><?php echo esc_html($heading); ?></p>
            <p class="tfp-dash-taskcard__desc"><?php echo esc_html($description); ?></p>
            <a href="<?php echo esc_url($button_url); ?>" class="tfp-dash-btn tfp-dash-btn--primary">
                <?php echo esc_html($button_text); ?>
            </a>
        </div>
        <div class="tfp-dash-taskcard__ring">
            <?php echo tfp_dashboard_render_progress_ring($percent, 88, 7); ?>
            <span class="tfp-dash-taskcard__ring-label"><?php esc_html_e('complete', 'tfp-dashboard'); ?></span>
        </div>
    </div>
    <?php
}

function tfp_dashboard_render_home_content()
{
    $name = tfp_dashboard_user_first_name();
    if (empty($name)) {
        $name = tfp_dashboard_user_name();
    }

    $state = function_exists('tfp_dashboard_get_program_state') ? tfp_dashboard_get_program_state() : null;

    tfp_dashboard_render_page_header(
        __('Home', 'tfp-dashboard'),
        sprintf(esc_html__('Welcome back, %s', 'tfp-dashboard'), esc_html(tfp_dashboard_user_name())),
        __("Here's where you are today — your progress, what's due, and what's coming up.", 'tfp-dashboard')
    );

    // The state-aware program card is always the top of Home: it shows Unpaid,
    // Waitlisted, Invited or Enrolled and gives each state its own actions, so
    // a paid student never sees the same view as one who hasn't paid.
    if ($state) {
        tfp_dashboard_render_program_card($state);
    }

    $is_enrolled = $state
        ? ($state['status'] === 'enrolled')
        : (function_exists('tfp_billing_user_has_paid') && tfp_billing_user_has_paid());

    $has_order_confirmation = isset($_GET['order_id'], $_GET['order_key'])
        && absint($_GET['order_id'])
        && !empty($_GET['order_key']);

    if ($is_enrolled) {
        // Enrolled Home is intentionally lean: the program card above + the
        // profile/billing card only. The weekly journey, current lesson, stat
        // tiles and pending tasks now live on the dedicated "Continue Program"
        // page (tfp-dashboard-program), reached from the card's button.
        tfp_dashboard_render_home_task_cards($name, true);
        if ($has_order_confirmation && function_exists('tfp_course_render_order_confirmation_modal')) {
            tfp_course_render_order_confirmation_modal();
        }
        return;
    }

    // Not enrolled yet → one consolidated "finish setting up" task card plus
    // the Browse-Cohorts + course-checkout modals that power "Choose cohort & Pay".
    tfp_dashboard_render_home_task_cards($name);

    if (function_exists('tfp_course_render_modals')) {
        tfp_course_render_modals($state);
    }
    if (function_exists('tfp_course_render_order_confirmation_modal')) {
        tfp_course_render_order_confirmation_modal();
    }
}

/**
 * State-aware program card shown at the top of Home. Reuses the image-left
 * "billing card" layout from the (now removed) billing page and drives the four
 * enrollment states from the mockups — Unpaid, Waitlisted, Invited, Enrolled —
 * each with its own status badge (colored dot), description and actions:
 *
 *   unpaid     → Choose cohort & Pay + Apply for financial aid
 *   waitlisted → Choose cohort & Pay        (financial aid pending review)
 *   invited    → Choose cohort & Pay        (financial aid approved)
 *   enrolled   → Continue Program + Request Support
 */
function tfp_dashboard_render_program_card($state)
{
    $user_id      = get_current_user_id();
    $program_name = function_exists('tfp_dashboard_current_program') ? tfp_dashboard_current_program() : __('Your Program', 'tfp-dashboard');
    $fa_url       = function_exists('tfp_dashboard_get_url') ? tfp_dashboard_get_url('tfp-dashboard-financial-aid') : '#';
    $program_url  = function_exists('tfp_dashboard_get_url') ? tfp_dashboard_get_url('tfp-dashboard-program') : '#';
    $support_url  = function_exists('tfp_dashboard_get_url') ? tfp_dashboard_get_url('tfp-dashboard-communication') : '#';
    $status       = isset($state['status']) ? $state['status'] : 'unpaid';
    $course_id    = isset($state['course_id']) ? (int) $state['course_id'] : 0;

    $badges = [
        'unpaid'     => __('Unpaid', 'tfp-dashboard'),
        'waitlisted' => __('Waitlisted', 'tfp-dashboard'),
        'invited'    => __('Invited', 'tfp-dashboard'),
        'enrolled'   => __('Enrolled', 'tfp-dashboard'),
    ];
    $badge_label = isset($badges[$status]) ? $badges[$status] : $badges['unpaid'];

    // Card image: the program's WooCommerce product image, falling back to the
    // LearnDash course featured image, then a placeholder.
    $image_html = '';
    if (function_exists('tfp_billing_get_program_product')) {
        $product = tfp_billing_get_program_product($user_id);
        if ($product) {
            $image_html = $product->get_image('medium');
        }
    }
    if (!$image_html && $course_id) {
        $image_html = get_the_post_thumbnail($course_id, 'medium');
    }
    if (!$image_html && function_exists('wc_placeholder_img')) {
        $image_html = wc_placeholder_img('medium');
    }

    // Compact meta line above the title: duration + a state-specific fact.
    $weeks_count = ($course_id && function_exists('tfp_ld_get_weeks')) ? count(tfp_ld_get_weeks($course_id)) : 0;
    $meta_bits = [];
    if ($weeks_count > 0) {
        $meta_bits[] = sprintf(_n('%d Week', '%d Weeks', $weeks_count, 'tfp-dashboard'), $weeks_count);
    }
    if ($status === 'enrolled' && !empty($state['cohort'])) {
        $c = $state['cohort'];
        if (!empty($c['schedule'])) {
            $meta_bits[] = sprintf(__('Meets %s', 'tfp-dashboard'), $c['schedule']);
        }
        if (!empty($c['facilitator'])) {
            $meta_bits[] = sprintf(__('Facilitated by %s', 'tfp-dashboard'), $c['facilitator']);
        }
    } elseif ($status === 'invited' && $state['fa_status'] === 'approved' && $state['fa_discount'] > 0) {
        $meta_bits[] = sprintf(__('Aid: Approved (%d%% off)', 'tfp-dashboard'), (int) $state['fa_discount']);
    } elseif ($status === 'waitlisted') {
        $meta_bits[] = __('Financial aid under review', 'tfp-dashboard');
    } else {
        $meta_bits[] = __('Weekly · Class-paced', 'tfp-dashboard');
    }
    $meta_line = implode(' • ', $meta_bits);

    // State description + call to action.
    if ($status === 'enrolled') {
        if (!empty($state['cohort'])) {
            $c = $state['cohort'];
            $desc = trim(sprintf(
                __('You\'re enrolled in %1$s%2$s. Jump back into your weekly lessons any time.', 'tfp-dashboard'),
                $c['name'],
                !empty($c['schedule']) ? ' · ' . $c['schedule'] : ''
            ));
        } else {
            $desc = __("You're enrolled. Jump back into your weekly lessons any time.", 'tfp-dashboard');
        }
    } elseif ($status === 'invited') {
        if ($state['fa_status'] === 'approved' && $state['fa_discount'] > 0) {
            $desc = sprintf(__('Financial aid approved — %d%% off your tuition. Choose your cohort and complete payment to secure your seat.', 'tfp-dashboard'), (int) $state['fa_discount']);
        } else {
            $desc = __("You've been invited to enroll. Choose a cohort and complete your payment to secure your seat.", 'tfp-dashboard');
        }
    } elseif ($status === 'waitlisted') {
        $desc = __("Your financial aid application is under review. We'll email you once it's approved — you can still choose a cohort and pay any time to secure your seat now.", 'tfp-dashboard');
    } else { // unpaid
        $desc = __('Secure your seat in the program. Choose a cohort and pay, or apply for financial aid first.', 'tfp-dashboard');
    }

    $details_url = $course_id ? get_permalink($course_id) : '';
    ?>
    <div class=" tfp-program-card tfp-program-card--<?php echo esc_attr($status); ?>">
        <div class="tfp-program-card__visual">
            <?php echo $image_html; // WooCommerce/LearnDash image markup — escaped by core ?>
        </div>
        <div class="tfp-program-card__content">
            <div class="tfp-program-card__topline">
                <?php if ($meta_line !== '') : ?>
                    <p class="tfp-program-card__meta"><?php echo esc_html($meta_line); ?></p>
                <?php else : ?>
                    <span></span>
                <?php endif; ?>
               
            </div>

            <h3 class="tfp-program-card__title"><?php echo esc_html($program_name); ?></h3>
            <p class="tfp-program-card__desc"><?php echo esc_html($desc); ?></p>

            <div class="tfp-program-card__actions">
                <?php if ($status === 'enrolled') : ?>
                    <a href="<?php echo esc_url($program_url); ?>" class="tfp-dash-btn tfp-dash-btn--primary">
                        <?php esc_html_e('Continue Program', 'tfp-dashboard'); ?>
                    </a>
                    <a href="<?php echo esc_url($support_url); ?>" class="tfp-dash-btn tfp-dash-btn--outline">
                        <?php esc_html_e('Request Support', 'tfp-dashboard'); ?>
                    </a>
                <?php else : ?>
                          <?php if ($status !== 'enrolled') : ?>
                    <button type="button" class="tfp-program-card__cancel tfp-dash-btn" data-tfp-cancel-registration data-logout-url="<?php echo esc_url(tfp_dashboard_logout_url()); ?>">
                        <?php esc_html_e('Cancel registration', 'tfp-dashboard'); ?>
                    </button>
                <?php endif; ?>
                    <button type="button" class="tfp-dash-btn tfp-dash-btn--primary" data-tfp-open-cohorts>
                        <?php esc_html_e('Choose cohort & Pay', 'tfp-dashboard'); ?>
                    </button>
                 
                    <?php if ($status === 'unpaid') : ?>
                        <a href="<?php echo esc_url($fa_url); ?>" class="tfp-dash-btn tfp-dash-btn--primary">
                            <?php esc_html_e('Apply for financial aid', 'tfp-dashboard'); ?>
                        </a>
                    <?php endif; ?>
                 
                <?php endif; ?>
            </div>

            <div class="tfp-program-card__footer">
                <?php if ($details_url) : ?>
                    <a class="tfp-program-card__details" href="<?php echo esc_url($details_url); ?>" target="_blank" rel="noopener">
                        <?php esc_html_e('View program details', 'tfp-dashboard'); ?>
                    </a>
                <?php endif; ?>

                 <span class="tfp-program-card__badge tfp-program-card__badge--<?php echo esc_attr($status); ?>">
                    <?php echo esc_html($badge_label); ?>
                </span>
     
            </div>
        </div>
    </div>
    <?php
}

/**
 * The "before you're fully set up" view — the combined Profile & Billing task
 * card. For non-enrolled students it's introduced with a "finish your
 * registration" lead; enrolled students (who reach this from the lean Home) see
 * just the card, since their setup copy no longer applies.
 */
function tfp_dashboard_render_home_task_cards($name, $is_enrolled = false)
{
    $profile_url = function_exists('tfp_dashboard_get_url') ? tfp_dashboard_get_url('tfp-dashboard-profile') : '#';
    ?>
    <div class="tfp-dash-section">
        <!-- <?php if (!$is_enrolled) : ?>
            <h2 class="tfp-dash-section__hi"><?php printf(esc_html__('Hi, %s', 'tfp-dashboard'), esc_html($name)); ?></h2>
            <p class="tfp-dash-section__lead"><?php esc_html_e("You've confirmed your email. Let's finish your registration so you can unlock your class dashboard.", 'tfp-dashboard'); ?></p>
        <?php endif; ?> -->

        <div class="tfp-dash-taskcards">
            <?php
            // Payment now happens through the "Choose cohort & Pay" modal on the
            // program card above, so Home only keeps one setup card: profile +
            // billing information, combined.
            tfp_dashboard_render_task_card(
                'profile',
                tfp_dashboard_user_name(),
                __('Complete Your Profile & Billing Information', 'tfp-dashboard'),
                __("We'll use this to personalize your class experience, connect you to updates and discussions, and keep your billing details ready for checkout.", 'tfp-dashboard'),
                __('Go to Profile', 'tfp-dashboard'),
                $profile_url
            );

            /**
             * Once paid, future cards (LearnDash progress, next meeting,
             * etc.) will plug in here.
             */
            do_action('tfp_dashboard_home_after_profile_card');
            ?>
        </div>
    </div>
    <?php
}
