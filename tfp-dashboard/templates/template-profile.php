<?php
if (!defined('ABSPATH')) exit;

tfp_dashboard_require_login();
tfp_dashboard_shell_start('profile');
tfp_dashboard_render_profile_content();
tfp_dashboard_shell_end();
