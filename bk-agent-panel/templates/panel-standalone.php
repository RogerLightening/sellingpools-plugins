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
 *
 * @package BK_Agent_Panel
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Suppress the WordPress admin bar — must be done before wp_head().
add_filter( 'show_admin_bar', '__return_false' );
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
	</style>
</head>
<body class="bk-agent-panel-page">
<?php
if ( ! $access['ok'] ) {
	// Not logged in or no profile — show the login screen.
	include BK_PANEL_PLUGIN_DIR . 'templates/login.php';
} else {
	// Authenticated agent — show the full panel layout.
	include BK_PANEL_PLUGIN_DIR . 'templates/panel-layout.php';
}
?>
<?php wp_footer(); ?>
</body>
</html>
