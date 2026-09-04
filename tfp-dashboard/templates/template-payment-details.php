<?php
if (!defined('ABSPATH')) exit;

tfp_dashboard_require_login();
tfp_dashboard_shell_start('home'); // payment details should use the same shell as Home
tfp_dashboard_render_payment_details_content();
tfp_dashboard_shell_end();
