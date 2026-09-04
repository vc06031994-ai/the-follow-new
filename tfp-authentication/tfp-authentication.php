<?php
/*
Plugin Name: TFP Authentication
Version: 1.2.1
*/
if(!defined('ABSPATH')) exit;
define('TFP_AUTH_VERSION','1.2.1');
define('TFP_AUTH_PATH', plugin_dir_path(__FILE__));
define('TFP_AUTH_URL', plugin_dir_url(__FILE__));

if(!defined('TFP_LOGIN_POPUP_ID')){
	define('TFP_LOGIN_POPUP_ID', 2093);
}
if(!defined('TFP_REGISTER_POPUP_ID')){
	define('TFP_REGISTER_POPUP_ID', 2104);
}
require_once TFP_AUTH_PATH.'includes/login.php';
require_once TFP_AUTH_PATH.'includes/register.php';
require_once TFP_AUTH_PATH.'includes/account-menu.php';
require_once TFP_AUTH_PATH.'includes/dashboard-shortcodes.php';
require_once TFP_AUTH_PATH.'includes/account-page/template.php';
require_once TFP_AUTH_PATH.'includes/account-page/ajax.php';
/**
 * Register frontend plugin component styles.
 */
function tfp_auth_register_styles(){
	if(!function_exists('wp_register_style')) return;

	$base = plugins_url('assets/css/', __FILE__);
	wp_register_style('tfp-auth-buttons', $base . 'buttons.css', [], defined('TFP_AUTH_VERSION') ? TFP_AUTH_VERSION : null);
	wp_register_style('tfp-auth-forms', $base . 'forms.css', [], defined('TFP_AUTH_VERSION') ? TFP_AUTH_VERSION : null);
	wp_register_style('tfp-auth-popup', $base . 'popup.css', [], defined('TFP_AUTH_VERSION') ? TFP_AUTH_VERSION : null);
	wp_register_style('tfp-auth-utils', $base . 'utilities.css', [], defined('TFP_AUTH_VERSION') ? TFP_AUTH_VERSION : null);
	wp_register_style('tfp-auth-base', $base . 'tfp-auth.css', [], defined('TFP_AUTH_VERSION') ? TFP_AUTH_VERSION : null);

	if(function_exists('wp_register_script')){
		wp_register_script('tfp-auth-script', plugins_url('assets/js/auth.js', __FILE__), ['jquery'], defined('TFP_AUTH_VERSION') ? TFP_AUTH_VERSION : null, true);
		wp_register_script('tfp-account-script', plugins_url('assets/js/account.js', __FILE__), ['jquery'], defined('TFP_AUTH_VERSION') ? TFP_AUTH_VERSION : null, true);
	}
}
add_action('wp_enqueue_scripts','tfp_auth_register_styles');

/**
 * Enqueue frontend plugin component styles when rendered.
 */
function tfp_auth_enqueue_styles(){
	if(!function_exists('wp_enqueue_style')) return;
	// wp_enqueue_style('tfp-auth-buttons');
	wp_enqueue_style('tfp-auth-forms');
	wp_enqueue_style('tfp-auth-popup');
	wp_enqueue_style('tfp-auth-utils');
	wp_enqueue_style('tfp-auth-base');

	if(function_exists('wp_enqueue_script')){
		wp_enqueue_script('tfp-auth-script');

		global $tfp_login_has_errors;
		$auto_open = false;
		if ( isset( $_POST['tfp-login-nonce'] ) && true === $tfp_login_has_errors ) {

		$auto_open = true;
		}

		wp_localize_script('tfp-auth-script', 'tfpAuthSettings', [
			'ajaxUrl'             => admin_url('admin-ajax.php'),
			'loginPopupID'        => defined('TFP_LOGIN_POPUP_ID') ? TFP_LOGIN_POPUP_ID : 2093,
			'registerPopupID'     => defined('TFP_REGISTER_POPUP_ID') ? TFP_REGISTER_POPUP_ID : 2104,
			'autoOpenLoginPopup'  => $auto_open,
		]);

		// Enqueue the new account JS specifically when we might be on the account page, or globally for now.
		wp_enqueue_script('tfp-account-script');
		wp_localize_script('tfp-account-script', 'tfpAccountSettings', [
			'ajaxUrl' => admin_url('admin-ajax.php'),
			'nonce'   => wp_create_nonce('tfp_account_nonce'),
		]);
	}
}

/**
 * Load plugin styles and scripts on the front end.
 */
add_action('wp_enqueue_scripts', 'tfp_auth_enqueue_styles');
add_action('init', function () {

    if ( isset($_GET['tfp_test']) ) {

        wp_die('INIT WORKING');

    }

});
