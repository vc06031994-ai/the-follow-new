<?php
/**
 * Loaded via template_include for pages using "TFP Dashboard — Program".
 */

if (!defined('ABSPATH')) exit;

tfp_dashboard_require_login();
tfp_dashboard_shell_start('program');
tfp_dashboard_render_program_content();
tfp_dashboard_shell_end();
