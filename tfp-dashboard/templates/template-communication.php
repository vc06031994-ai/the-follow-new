<?php
/**
 * Loaded via template_include for pages using "TFP Dashboard — Communication".
 */

if (!defined('ABSPATH')) exit;

tfp_dashboard_require_login();
tfp_dashboard_shell_start('communication');
tfp_dashboard_render_communication_content();
tfp_dashboard_shell_end();
