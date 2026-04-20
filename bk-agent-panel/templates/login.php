<?php
/**
 * Agent panel login form.
 *
 * Shown when an unauthenticated user visits the panel page. The POST is
 * handled in BK_Agent_Router::maybe_handle_login() before any output, so
 * wp_signon() can still set cookies and redirect.
 *
 * Variables expected from caller scope:
 *
 * @var string $login_error Error message from wp_signon(), or empty string.
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
$login_error = $login_error ?? '';
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
