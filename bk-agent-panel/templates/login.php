<?php
/**
 * Agent panel login form.
 *
 * Shown when an unauthenticated user visits the panel page.
 *
 * @package BK_Agent_Panel
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bk_logo_id  = (int) BK_Settings::get_setting( 'company_logo_id', 0 );
$bk_logo_url = $bk_logo_id ? wp_get_attachment_url( $bk_logo_id ) : '';
$company     = BK_Settings::get_setting( 'company_name', 'BK Pools' );
$panel_url   = BK_Agent_Auth::get_panel_url();

// Handle login form submission (for non-JS fallback).
$login_error = '';

if ( isset( $_POST['bk_login_submit'] ) ) {
	check_admin_referer( 'bk_agent_login' );

	$credentials = array(
		'user_login'    => sanitize_user( wp_unslash( $_POST['log'] ?? '' ) ),
		'user_password' => wp_unslash( $_POST['pwd'] ?? '' ),
		'remember'      => ! empty( $_POST['rememberme'] ),
	);

	$user = wp_signon( $credentials, is_ssl() );

	if ( is_wp_error( $user ) ) {
		$login_error = $user->get_error_message();
	} else {
		wp_safe_redirect( $panel_url );
		exit;
	}
}
?>
<div class="bk-agent-panel bk-panel-login">
	<div class="bk-login-card">

		<div class="bk-login-card__header">
			<?php if ( $bk_logo_url ) : ?>
				<img src="<?php echo esc_url( $bk_logo_url ); ?>" alt="<?php echo esc_attr( $company ); ?>" class="bk-login-card__logo">
			<?php else : ?>
				<h2 class="bk-login-card__title"><?php echo esc_html( $company ); ?></h2>
			<?php endif; ?>
			<p class="bk-login-card__subtitle"><?php esc_html_e( 'Agent Login', 'bk-agent-panel' ); ?></p>
		</div>

		<?php if ( $login_error ) : ?>
			<div class="bk-notice bk-notice--error">
				<?php echo wp_kses_post( $login_error ); ?>
			</div>
		<?php endif; ?>

		<form class="bk-login-form" method="post" action="">
			<?php wp_nonce_field( 'bk_agent_login' ); ?>

			<div class="bk-form-group">
				<label class="bk-form-label" for="bk-login-username">
					<?php esc_html_e( 'Username or Email', 'bk-agent-panel' ); ?>
				</label>
				<input
					id="bk-login-username"
					class="bk-form-input"
					type="text"
					name="log"
					autocomplete="username"
					required
				>
			</div>

			<div class="bk-form-group">
				<label class="bk-form-label" for="bk-login-password">
					<?php esc_html_e( 'Password', 'bk-agent-panel' ); ?>
				</label>
				<input
					id="bk-login-password"
					class="bk-form-input"
					type="password"
					name="pwd"
					autocomplete="current-password"
					required
				>
			</div>

			<div class="bk-form-group bk-form-group--inline">
				<label class="bk-form-checkbox">
					<input type="checkbox" name="rememberme" value="forever">
					<?php esc_html_e( 'Remember me', 'bk-agent-panel' ); ?>
				</label>
				<a href="<?php echo esc_url( wp_lostpassword_url() ); ?>" class="bk-login-form__forgot">
					<?php esc_html_e( 'Forgot password?', 'bk-agent-panel' ); ?>
				</a>
			</div>

			<button type="submit" name="bk_login_submit" class="bk-btn bk-btn--primary bk-btn--full">
				<?php esc_html_e( 'Log In', 'bk-agent-panel' ); ?>
			</button>
		</form>

	</div>
</div>
