<?php
if(!defined('ABSPATH')) exit;

// Add initialization handler for login form submission
add_action('init', function(){
	if ( ! isset( $_POST['tfp-login-nonce'] ) ) {
		return;
	}

	// Verify security nonce
	check_admin_referer( 'tfp-login-action', 'tfp-login-nonce' );

	global $tfp_login_has_errors, $tfp_login_errors;
	$tfp_login_has_errors = false;
	$tfp_login_errors = [];

	// Sanitize inputs
	$email    = isset( $_POST['username'] ) ? sanitize_email( wp_unslash( $_POST['username'] ) ) : '';
	$password = isset( $_POST['password'] ) ? $_POST['password'] : '';
	$remember = ! empty( $_POST['rememberme'] );

	$errors = [];

	// 1. Validate Email field is not empty
	if ( empty( $email ) ) {
		$errors[] = __( 'Email is required.', 'tfp-authentication' );
	}
	// 2. Validate Password field is not empty
	if ( empty( $password ) ) {
		$errors[] = __( 'Password is required.', 'tfp-authentication' );
	}
	// 3. Validate email format
	if ( ! empty( $email ) && ! is_email( $email ) ) {
		$errors[] = __( 'Please enter a valid email address.', 'tfp-authentication' );
	}

	if ( ! empty( $errors ) ) {
		$tfp_login_has_errors = true;
		$tfp_login_errors = $errors;
		foreach ( $errors as $error ) {
			if ( function_exists( 'wc_add_notice' ) ) {
				wc_add_notice( $error, 'error' );
			}
		}
		return;
	}

	// Authenticate using wp_signon() or WooCommerce authentication APIs
	// Check if user exists by email first
	$user = get_user_by( 'email', $email );
	if ( ! $user ) {
		// Fallback: try by username
		$user = get_user_by( 'login', $email );
	}

	if ( ! $user ) {
		$tfp_login_has_errors = true;
		$tfp_login_errors[] = __( 'Invalid email or password.', 'tfp-authentication' );
		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( __( 'Invalid email or password.', 'tfp-authentication' ), 'error' );
		}
		return;
	}

	$creds = [
		'user_login'    => $user->user_login,
		'user_password' => $password,
		'remember'      => $remember,
	];

	$auth_user = wp_signon( $creds, is_ssl() );

	if ( is_wp_error( $auth_user ) ) {
		$tfp_login_has_errors = true;
		$error_code = $auth_user->get_error_code();

		if ( in_array( $error_code, [ 'disabled_account', 'account_disabled', 'user_disabled' ], true ) ) {
			$msg = __( 'Your account has been disabled.', 'tfp-authentication' );
		} elseif ( in_array( $error_code, [ 'pending_approval', 'account_pending', 'user_pending' ], true ) ) {
			$msg = __( 'Your account is pending approval.', 'tfp-authentication' );
		} else {
			$msg = __( 'Invalid email or password.', 'tfp-authentication' );
		}

		$tfp_login_errors[] = $msg;
		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( $msg, 'error' );
		}
		return;
	}

	// Double check custom status in user meta for plugins that don't block wp_signon
	$user_status    = get_user_meta( $auth_user->ID, 'user_status', true );
	$approve_status = get_user_meta( $auth_user->ID, 'pw_approve_user_status', true );

	if ( 'disabled' === $user_status || 'disabled' === $approve_status ) {
		$tfp_login_has_errors = true;
		wp_logout();
		$tfp_login_errors[] = __( 'Your account has been disabled.', 'tfp-authentication' );
		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( __( 'Your account has been disabled.', 'tfp-authentication' ), 'error' );
		}
		return;
	}

	if ( 'pending' === $user_status || 'pending' === $approve_status ) {
		$tfp_login_has_errors = true;
		wp_logout();
		$tfp_login_errors[] = __( 'Your account is pending approval.', 'tfp-authentication' );
		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( __( 'Your account is pending approval.', 'tfp-authentication' ), 'error' );
		}
		return;
	}

	// Login user is successful!
	wp_set_current_user( $auth_user->ID );

	// Redirect logic
	$redirect_url = ! empty( $_POST['redirect'] ) ? esc_url_raw( wp_unslash( $_POST['redirect'] ) ) : '';
	if ( empty( $redirect_url ) ) {
		$redirect_url = wp_get_referer();
		if ( ! $redirect_url ) {
			$redirect_url = home_url( $_SERVER['REQUEST_URI'] );
		}
	}

	wp_safe_redirect( $redirect_url );
	exit;
});

add_shortcode('tfp_login_form', function(){
	if(is_user_logged_in()) {
		return '<p>' . esc_html__('You are already logged in.', 'tfp-authentication') . '</p>';
	}

	tfp_auth_enqueue_styles();

	ob_start();

	// Output WooCommerce notices if there are any still in the notice store
	$has_notices_printed = false;
	if ( function_exists( 'wc_notice_count' ) && wc_notice_count( 'error' ) > 0 ) {
		if ( function_exists( 'woocommerce_output_all_notices' ) ) {
			woocommerce_output_all_notices();
			$has_notices_printed = true;
		}
	}

	// Fallback/direct notice display specifically for TFP login form errors
	global $tfp_login_errors;
	if ( ! $has_notices_printed && ! empty( $tfp_login_errors ) ) {
		echo '<ul class="woocommerce-error" role="alert">';
		foreach ( $tfp_login_errors as $error ) {
			echo '<li>' . esc_html( $error ) . '</li>';
		}
		echo '</ul>';
	}

	$username_val = isset( $_POST['username'] ) ? sanitize_email( wp_unslash( $_POST['username'] ) ) : '';
	?>
	<div class="tfp-login-form-container">
		<form method="post" class="woocommerce-form woocommerce-form-login login tfp-login-form">
			
			<p class="tfp-form-group">
				<label class="tfp-label" for="username"><?php echo esc_html__('Email', 'tfp-authentication'); ?></label>
				<input class="tfp-input" type="email" name="username" id="username" autocomplete="username" value="<?php echo esc_attr( $username_val ); ?>" required>
			</p>
			
			<p class="tfp-form-group tfp-password-wrapper">
				<label class="tfp-label" for="password"><?php echo esc_html__('Password', 'tfp-authentication'); ?></label>
				<span class="tfp-password-field-container">
					<input class="tfp-input" type="password" name="password" id="password" autocomplete="current-password" required>
					<span class="tfp-password-toggle" role="button" aria-label="<?php echo esc_attr__('Toggle password visibility', 'tfp-authentication'); ?>">
						<!-- Eye closed (visible state toggle) -->
						<svg class="eye-icon-closed" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
							<circle cx="12" cy="12" r="3"></circle>
						</svg>
						<!-- Eye open (hidden state toggle) -->
						<svg class="eye-icon-open tfp-hidden" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
							<line x1="1" y1="1" x2="23" y2="23"></line>
						</svg>
					</span>
				</span>
			</p>
			
			<p class="tfp-forgot-password">
				<a href="<?php echo esc_url( wc_lostpassword_url() ); ?>"><?php echo esc_html__('Forgot your Password?', 'tfp-authentication'); ?></a>
			</p>

			<p class="tfp-rememberme-group">
				<label class="tfp-checkbox">
					<input type="checkbox" name="rememberme" id="rememberme" value="forever">
					<span class="tfp-checkbox-label"><?php echo esc_html__('Remember me', 'tfp-authentication'); ?></span>
				</label>
			</p>
			
			<?php wp_nonce_field('tfp-login-action', 'tfp-login-nonce'); ?>
			<input type="hidden" name="redirect" value="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>" />
			
			<p class="tfp-login-btn-wrap">
				<button type="submit" name="tfp_login_submit" value="1" class="tfp-btn tfp-btn-primary tfp-btn-lg tfp-w-100"><?php echo esc_html__('Log In', 'tfp-authentication'); ?></button>
			</p>
			
			<p class="tfp-register-btn-wrap">
				<button type="button" class="tfp-btn tfp-btn-outline tfp-btn-lg tfp-w-100 js-tfp-switch-to-register"><?php echo esc_html__('Register', 'tfp-authentication'); ?></button>
			</p>
			
			<p class="tfp-footer-note">
				<?php
				printf(
					/* translators: 1: Terms & Conditions link, 2: Privacy Policy link */
					esc_html__( 'By proceeding ahead you agree to %1$s and %2$s', 'tfp-authentication' ),
					'<a href="#" class="tfp-footer-link">' . esc_html__( 'Terms & Conditions', 'tfp-authentication' ) . '</a>',
					'<a href="#" class="tfp-footer-link">' . esc_html__( 'Privacy Policy', 'tfp-authentication' ) . '</a>'
				);
				?>
			</p>
			
		</form>
	</div>
	<?php
	return ob_get_clean();
});
