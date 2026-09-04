<?php
if (!defined('ABSPATH')) exit;

tfp_dashboard_require_login();
// Hide the global sidebar on the week view to match the Figma layout.
add_filter('body_class', function($classes){ $classes[] = 'tfp-no-sidebar'; return $classes; });
tfp_dashboard_shell_start('home'); // reached via Home's Continue Lesson button, not its own sidebar tab
tfp_dashboard_render_week_content();
tfp_dashboard_shell_end();
