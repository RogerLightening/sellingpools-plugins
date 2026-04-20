<?php
/**
 * Standalone page wrapper for the agent panel.
 *
 * Loaded by BK_Agent_Router::handle_template_redirect() with exit() after
 * include, so no theme template (Bricks or otherwise) ever renders.
 *
 * Variables available from handle_template_redirect():
 *
 * @var array  $access        Result of BK_Agent_Auth::check_access().
 * @var int    $agent_post_id Builder CPT post ID (0 if not logged in).
 * @var string $section       Current panel section slug.
 * @var string $panel_url     Panel page permalink.
 * @var string $login_error   Error message from wp_signon(), or empty string.
 *
 * @package BK_Agent_Panel
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Suppress the WordPress admin bar — must be done before wp_head().
add_filter( 'show_admin_bar', '__return_false' );

error_log( 'BK Panel template: panel-standalone.php entered — $access=' . wp_json_encode( $access ?? null ) );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php esc_html_e( 'Agent Dashboard', 'bk-agent-panel' ); ?> &mdash; <?php bloginfo( 'name' ); ?></title>
	<?php wp_head(); ?>
	<style>
		/* Safety net — hide admin bar and any theme remnants. */
		#wpadminbar { display: none !important; }
		html.wp-toolbar { padding-top: 0 !important; }
		html { margin-top: 0 !important; }
		/* Ensure panel fills viewport with no body margin from theme resets. */
		body.bk-agent-panel-page { margin: 0; padding: 0; background: #F4F6F9; }
		/* Fallback access-denied card — styled inline so it renders even if
		   the panel stylesheet failed to load. */
		.bk-panel-denied {
			max-width: 440px;
			margin: 80px auto;
			padding: 40px 32px;
			background: #fff;
			border-radius: 10px;
			box-shadow: 0 6px 24px rgba( 0, 0, 0, 0.08 );
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
			text-align: center;
			color: #222;
		}
		.bk-panel-denied h1 { margin: 0 0 12px; font-size: 22px; font-weight: 600; }
		.bk-panel-denied p  { margin: 0 0 16px; line-height: 1.5; color: #555; }
		.bk-panel-denied a.bk-panel-denied__btn {
			display: inline-block;
			padding: 10px 20px;
			background: #0073aa;
			color: #fff;
			text-decoration: none;
			border-radius: 6px;
			font-weight: 500;
		}
		.bk-panel-denied a.bk-panel-denied__btn:hover { background: #005f8e; }
		.bk-panel-denied__meta {
			margin-top: 18px;
			font-size: 12px;
			color: #999;
		}
	</style>
</head>
<body class="bk-agent-panel-page">
<?php
// Defensive guard — if $access isn't set (e.g. template included out-of-band),
// fall back to an explicit error rather than a blank page.
if ( ! isset( $access ) || ! is_array( $access ) ) {
	error_log( 'BK Panel template: $access is not set — rendering fallback' );
	?>
	<div class="bk-panel-denied">
		<h1><?php esc_html_e( 'Agent Dashboard Unavailable', 'bk-agent-panel' ); ?></h1>
		<p><?php esc_html_e( 'The dashboard could not determine your access state. Please refresh or log in again.', 'bk-agent-panel' ); ?></p>
		<p><a class="bk-panel-denied__btn" href="<?php echo esc_url( wp_login_url( home_url( '/agent-dashboard/' ) ) ); ?>"><?php esc_html_e( 'Log In', 'bk-agent-panel' ); ?></a></p>
	</div>
	<?php
} elseif ( ! $access['ok'] ) {
	$error = (string) ( $access['error'] ?? 'unknown' );
	error_log( 'BK Panel template: access denied — error=' . $error );

	if ( 'not_logged_in' === $error ) {
		include BK_PANEL_PLUGIN_DIR . 'templates/login.php';
	} else {
		?>
		<div class="bk-panel-denied">
			<h1><?php esc_html_e( 'Access Denied', 'bk-agent-panel' ); ?></h1>
			<?php if ( 'no_capability' === $error ) : ?>
				<p><?php esc_html_e( 'Your account does not have permission to view the agent panel.', 'bk-agent-panel' ); ?></p>
			<?php elseif ( 'no_profile' === $error ) : ?>
				<p><?php esc_html_e( 'Your agent profile has not been set up yet. Please contact SellingPools to complete onboarding.', 'bk-agent-panel' ); ?></p>
			<?php else : ?>
				<p><?php echo esc_html( sprintf( /* translators: %s: auth error code */ __( 'Access check failed: %s', 'bk-agent-panel' ), $error ) ); ?></p>
			<?php endif; ?>
			<p><a class="bk-panel-denied__btn" href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>"><?php esc_html_e( 'Log Out', 'bk-agent-panel' ); ?></a></p>
			<p class="bk-panel-denied__meta">
				<?php echo esc_html( sprintf( 'user_id=%d · error=%s', get_current_user_id(), $error ) ); ?>
			</p>
		</div>
		<?php
	}
} else {
	error_log( 'BK Panel template: access granted — including panel-layout.php (agent_post_id=' . (int) ( $access['agent_post_id'] ?? 0 ) . ')' );
	include BK_PANEL_PLUGIN_DIR . 'templates/panel-layout.php';
	error_log( 'BK Panel template: panel-layout.php include returned' );
}
?>
<?php wp_footer(); ?>
</body>
</html>
