<?php
/**
 * Shared dashboard shell: collapsible/offcanvas sidebar + the wrapper
 * that every template opens with. One place to edit the nav for every
 * dashboard page.
 */

if (!defined('ABSPATH')) exit;

/**
 * Nav items shown in the sidebar. Filterable so future items (Programs,
 * Certificates, Notifications...) can be added without editing this file.
 */
function tfp_dashboard_nav_items()
{
    $items = [
        ['id' => 'home',          'label' => __('Home', 'tfp-dashboard'),          'icon' => 'home',     'template' => 'tfp-dashboard-home'],
        ['id' => 'program',       'label' => __('Program', 'tfp-dashboard'),       'icon' => 'program',  'template' => 'tfp-dashboard-program'],
        ['id' => 'grades',        'label' => __('Grades', 'tfp-dashboard'),        'icon' => 'grades',   'template' => 'tfp-dashboard-grades'],
        ['id' => 'communication', 'label' => __('Communication', 'tfp-dashboard'), 'icon' => 'chat',     'template' => 'tfp-dashboard-communication'],
        ['id' => 'documents',     'label' => __('Documents', 'tfp-dashboard'),     'icon' => 'doc',      'template' => 'tfp-dashboard-documents'],
        ['id' => 'calendar',      'label' => __('Calendar', 'tfp-dashboard'),      'icon' => 'calendar', 'template' => 'tfp-dashboard-calendar'],
        ['id' => 'profile',       'label' => __('Profile', 'tfp-dashboard'),       'icon' => 'user',     'template' => 'tfp-dashboard-profile'],
    ];

    return apply_filters('tfp_dashboard_nav_items', $items);
}

/**
 * Renders the sidebar. $active is the nav item id currently on screen
 * (e.g. 'home', 'communication') so it gets the highlighted state.
 */
function tfp_dashboard_render_sidebar($active = '')
{
    ?>
    <aside class="tfp-dash-sidebar" data-tfp-sidebar>
        <div class="tfp-dash-sidebar__top">
            <button type="button" class="tfp-dash-sidebar__toggle" data-tfp-sidebar-toggle aria-label="<?php esc_attr_e('Toggle sidebar', 'tfp-dashboard'); ?>">
                <?php echo tfp_dashboard_icon('toggle'); ?>
            </button>
        </div>

        <div class="tfp-dash-sidebar__brand">
            <img
                src="<?php echo esc_url(TFP_DASH_URL . 'assets/images/logo-expanded.svg'); ?>"
                alt="<?php echo esc_attr(get_bloginfo('name')); ?>"
                class="tfp-dash-sidebar__logo tfp-dash-sidebar__logo--expanded"
            >
            <img
                src="<?php echo esc_url(TFP_DASH_URL . 'assets/images/logo-collapsed.svg'); ?>"
                alt="<?php echo esc_attr(get_bloginfo('name')); ?>"
                class="tfp-dash-sidebar__logo tfp-dash-sidebar__logo--collapsed"
            >
        </div>

        <nav class="tfp-dash-sidebar__nav" aria-label="<?php esc_attr_e('Dashboard navigation', 'tfp-dashboard'); ?>">
            <?php foreach (tfp_dashboard_nav_items() as $item) : ?>
                <a
                    href="<?php echo esc_url(tfp_dashboard_get_url($item['template'])); ?>"
                    class="tfp-dash-sidebar__link<?php echo $active === $item['id'] ? ' is-active' : ''; ?>"
                >
                    <span class="tfp-dash-sidebar__icon"><?php echo tfp_dashboard_icon($item['icon']); ?></span>
                    <span class="tfp-dash-sidebar__label"><?php echo esc_html($item['label']); ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="tfp-dash-sidebar__footer">
            <div class="tfp-dash-sidebar__profile">
                <span class="tfp-dash-sidebar__avatar"><?php echo tfp_dashboard_avatar(36); ?></span>
                <span class="tfp-dash-sidebar__profile-text">
                    <span class="tfp-dash-sidebar__name"><?php echo esc_html(tfp_dashboard_user_name()); ?></span>
                    <span class="tfp-dash-sidebar__role"><?php echo esc_html(tfp_dashboard_user_role_label()); ?></span>
                </span>
            </div>
            <a href="<?php echo esc_url(tfp_dashboard_logout_url()); ?>" class="tfp-dash-sidebar__logout">
                <span class="tfp-dash-sidebar__icon"><?php echo tfp_dashboard_icon('logout'); ?></span>
                <span class="tfp-dash-sidebar__label"><?php esc_html_e('Logout', 'tfp-dashboard'); ?></span>
            </a>
        </div>
    </aside>
    <div class="tfp-dash-overlay" data-tfp-sidebar-overlay></div>
    <?php
}

/**
 * Opens the shell: <html>...<body> + sidebar + <main> start.
 * Every template calls this, renders its own content, then calls
 * tfp_dashboard_shell_end().
 */
function tfp_dashboard_shell_start($active)
{
    ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html(wp_get_document_title()); ?></title>
    <?php wp_head(); ?>
</head>
<body <?php body_class('tfp-dashboard-body'); ?>>
<?php wp_body_open(); ?>
<div class="tfp-dashboard" data-tfp-dashboard>
    <?php tfp_dashboard_render_sidebar($active); ?>
    <main class="tfp-dash-main">
    <?php
}

function tfp_dashboard_shell_end()
{
    ?>
    </main>
</div>
<?php wp_footer(); ?>
</body>
</html>
    <?php
}
