<?php
/**
 * Loaded via template_include for pages using "TFP Dashboard — Home".
 */

if (!defined('ABSPATH')) exit;

tfp_dashboard_require_login();
tfp_dashboard_shell_start('home');
tfp_dashboard_render_home_content();
tfp_dashboard_shell_end();
