<?php
if (!defined('ABSPATH')) exit;

function tfp_dashboard_render_communication_content()
{
    $user_id = get_current_user_id();
    $is_staff = tfp_dashboard_user_is_staff($user_id);

    $open_count     = tfp_dashboard_count_tickets_by_status($user_id, 'open');
    $pending_count  = tfp_dashboard_count_tickets_by_status($user_id, 'pending');
    $resolved_count = tfp_dashboard_count_tickets_by_status($user_id, 'resolved');

    $tickets = tfp_dashboard_get_visible_tickets($user_id);

    tfp_dashboard_render_page_header(
        __('Communication', 'tfp-dashboard'),
        __('Communication', 'tfp-dashboard'),
        __('Submit and track support tickets — access issues, billing questions, and technical help.', 'tfp-dashboard')
    );
    ?>
    <div class="tfp-dash-stats">
        <div class="tfp-dash-panel tfp-dash-stat">
            <span class="tfp-dash-stat__label"><?php esc_html_e('Open', 'tfp-dashboard'); ?></span>
            <span class="tfp-dash-stat__value"><?php echo esc_html($open_count); ?></span>
            <span class="tfp-dash-stat__sub"><?php esc_html_e('Active tickets awaiting response', 'tfp-dashboard'); ?></span>
        </div>
        <div class="tfp-dash-panel tfp-dash-stat">
            <span class="tfp-dash-stat__label"><?php esc_html_e('Pending Reply', 'tfp-dashboard'); ?></span>
            <span class="tfp-dash-stat__value tfp-dash-stat__value--amber"><?php echo esc_html($pending_count); ?></span>
            <span class="tfp-dash-stat__sub"><?php esc_html_e('Waiting on your response', 'tfp-dashboard'); ?></span>
        </div>
        <div class="tfp-dash-panel tfp-dash-stat">
            <span class="tfp-dash-stat__label"><?php esc_html_e('Resolved', 'tfp-dashboard'); ?></span>
            <span class="tfp-dash-stat__value"><?php echo esc_html($resolved_count); ?></span>
            <span class="tfp-dash-stat__sub"><?php esc_html_e('All completed tickets', 'tfp-dashboard'); ?></span>
        </div>
    </div>

    <div class="tfp-dash-chatlayout">
        <aside class="tfp-dash-ticketlist">
            <div class="tfp-dash-ticketlist__search">
                <span class="tfp-dash-ticketlist__search-icon"><?php echo tfp_dashboard_icon('search'); ?></span>
                <input type="search" placeholder="<?php esc_attr_e('Search Name or Cohort', 'tfp-dashboard'); ?>" data-tfp-ticket-search>
            </div>

            <div class="tfp-dash-ticketlist__tabs" data-tfp-ticket-tabs>
                <button type="button" class="is-active" data-filter="all"><?php esc_html_e('All', 'tfp-dashboard'); ?></button>
                <button type="button" data-filter="open"><?php esc_html_e('Open', 'tfp-dashboard'); ?></button>
                <button type="button" data-filter="access"><?php esc_html_e('Access', 'tfp-dashboard'); ?></button>
                <button type="button" data-filter="technical"><?php esc_html_e('Technical', 'tfp-dashboard'); ?></button>
            </div>

            <button type="button" class="tfp-dash-btn tfp-dash-btn--outline tfp-dash-ticketlist__new" data-tfp-new-ticket>
                <?php echo tfp_dashboard_icon('plus'); ?> <?php esc_html_e('New Ticket', 'tfp-dashboard'); ?>
            </button>

            <div class="tfp-dash-ticketlist__items">
                <?php if (empty($tickets)) : ?>
                    <p class="tfp-dash-ticketlist__empty"><?php esc_html_e('No tickets yet.', 'tfp-dashboard'); ?></p>
                <?php endif; ?>

                <?php foreach ($tickets as $ticket) :
                    $category = get_post_meta($ticket->ID, '_tfp_category', true);
                    $status   = get_post_meta($ticket->ID, '_tfp_status', true);
                    $owner_id = (int) get_post_meta($ticket->ID, '_tfp_student_id', true);
                    $owner    = get_userdata($owner_id);
                    $last     = tfp_chat_get_last_message($ticket->ID);
                    $categories = tfp_dashboard_ticket_categories();
                    ?>
                    <button
                        type="button"
                        class="tfp-dash-ticketlist__item"
                        data-tfp-ticket-item
                        data-ticket-id="<?php echo esc_attr($ticket->ID); ?>"
                        data-category="<?php echo esc_attr($category); ?>"
                        data-status="<?php echo esc_attr($status); ?>"
                        data-search="<?php echo esc_attr(strtolower($ticket->post_title . ' ' . ($owner ? $owner->display_name : ''))); ?>"
                    >
                        <span class="tfp-dash-ticketlist__avatar">
                            <?php echo $owner ? get_avatar($owner_id, 36) : ''; ?>
                        </span>
                        <span class="tfp-dash-ticketlist__meta">
                            <span class="tfp-dash-ticketlist__name">
                                <?php echo esc_html($owner ? $owner->display_name : $ticket->post_title); ?>
                            </span>
                            <span class="tfp-dash-ticketlist__category tfp-dash-ticketlist__category--<?php echo esc_attr($status); ?>">
                                <?php echo esc_html($categories[$category] ?? ucfirst($category)); ?>
                            </span>
                            <span class="tfp-dash-ticketlist__preview">
                                <?php echo $last ? esc_html(wp_trim_words(wp_strip_all_tags($last->message), 6)) : esc_html($ticket->post_title); ?>
                            </span>
                        </span>
                    </button>
                <?php endforeach; ?>
            </div>
        </aside>

        <section class="tfp-dash-chatpanel" data-tfp-chatpanel>
            <div class="tfp-dash-chatpanel__empty" data-tfp-chat-empty>
                <p><?php esc_html_e('Select a ticket from the list, or start a new one.', 'tfp-dashboard'); ?></p>
            </div>

            <div class="tfp-dash-chatpanel__thread" data-tfp-chat-thread hidden>
                <div class="tfp-dash-chatpanel__header">
                    <div>
                        <h2 class="tfp-dash-chatpanel__title" data-tfp-chat-title></h2>
                        <p class="tfp-dash-chatpanel__subtitle" data-tfp-chat-subtitle></p>
                    </div>
                    <div class="tfp-dash-chatpanel__actions">
                        <span class="tfp-dash-badge" data-tfp-chat-status></span>
                        <button type="button" class="tfp-dash-btn tfp-dash-btn--outline" data-tfp-mark-resolved>
                            <?php esc_html_e('Mark Resolved', 'tfp-dashboard'); ?>
                        </button>
                    </div>
                </div>

                <div class="tfp-dash-chatpanel__messages" data-tfp-chat-messages></div>

                <form class="tfp-dash-chatpanel__reply" data-tfp-chat-reply>
                    <input type="text" placeholder="<?php esc_attr_e('Type a reply...', 'tfp-dashboard'); ?>" data-tfp-chat-input required>
                    <button type="submit" class="tfp-dash-btn tfp-dash-btn--primary">
                        <?php echo tfp_dashboard_icon('send'); ?> <?php esc_html_e('Reply', 'tfp-dashboard'); ?>
                    </button>
                </form>
            </div>
        </section>
    </div>

    <div class="tfp-dash-modal" data-tfp-new-ticket-modal hidden>
        <div class="tfp-dash-modal__backdrop" data-tfp-modal-close></div>
        <form class="tfp-dash-modal__box" data-tfp-new-ticket-form>
            <h2><?php esc_html_e('New Support Ticket', 'tfp-dashboard'); ?></h2>

            <label>
                <?php esc_html_e('Subject', 'tfp-dashboard'); ?>
                <input type="text" name="subject" required>
            </label>

            <label>
                <?php esc_html_e('Category', 'tfp-dashboard'); ?>
                <select name="category" required>
                    <?php foreach (tfp_dashboard_ticket_categories() as $key => $label) : ?>
                        <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <?php esc_html_e('Message', 'tfp-dashboard'); ?>
                <textarea name="message" rows="4" required></textarea>
            </label>

            <div class="tfp-dash-modal__buttons">
                <button type="button" class="tfp-dash-btn tfp-dash-btn--outline" data-tfp-modal-close><?php esc_html_e('Cancel', 'tfp-dashboard'); ?></button>
                <button type="submit" class="tfp-dash-btn tfp-dash-btn--primary"><?php esc_html_e('Create Ticket', 'tfp-dashboard'); ?></button>
            </div>
        </form>
    </div>
    <?php
}
