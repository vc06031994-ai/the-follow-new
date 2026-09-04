<?php
if (!defined('ABSPATH')) exit;

tfp_dashboard_require_login();
tfp_dashboard_shell_start('home'); // reached via Billing/Home, not its own sidebar tab
tfp_dashboard_render_financial_aid_content();
tfp_dashboard_shell_end();
