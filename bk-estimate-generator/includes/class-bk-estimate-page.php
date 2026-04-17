<?php
/**
 * BK Estimate Page
 *
 * Registers a custom rewrite rule so customers can view their estimate at a
 * clean, token-based URL without authentication.
 *
 * @package BK_Estimate_Generator
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BK_Estimate_Page
 *
 * URL structure: /estimate/view/{token}
 *
 * On template_redirect:
 *  1. Checks for the bk_estimate_token query var.
 *  2. Looks up the estimate in wp_estimate_meta by token.
 *  3. Validates expiry.
 *  4. Builds estimate data via BK_Estimate_Builder::build().
 *  5. Loads the estimate-page.php template and exits.
 *
 * @since 1.0.0
 */
class BK_Estimate_Page {

	/**
	 * WordPress query var name for the estimate token.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const QUERY_VAR = 'bk_estimate_token';

	/**
	 * Rewrite rule regex.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const REWRITE_REGEX = 'estimate/view/([a-zA-Z0-9]+)/?$';

	/**
	 * Constructor — registers WordPress hooks.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_query_var_and_rules' ) );
		add_action( 'template_redirect', array( $this, 'handle_template_redirect' ) );
		add_filter( 'query_vars', array( $this, 'add_query_var' ) );
	}

	// -------------------------------------------------------------------------
	// Hook callbacks
	// -------------------------------------------------------------------------

	/**
	 * Registers the custom rewrite rule on init.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_query_var_and_rules(): void {
		self::register_rewrite_rules();
	}

	/**
	 * Adds the custom query var to WordPress's recognised vars list.
	 *
	 * @since 1.0.0
	 *
	 * @param array $vars Existing query vars.
	 * @return array Modified query vars.
	 */
	public function add_query_var( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Handles the template redirect for the estimate page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_template_redirect(): void {
		$token = get_query_var( self::QUERY_VAR );

		if ( empty( $token ) ) {
			return;
		}

		// Sanitise token — only alphanumeric characters are valid.
		$token = sanitize_text_field( $token );

		// Look up estimate by token in JetEngine's custom meta table.
		$estimate_post_id = self::find_estimate_by_token( $token );

		if ( ! $estimate_post_id ) {
			self::render_error_page(
				__( 'Estimate Not Found', 'bk-estimate-generator' ),
				__( 'Sorry, we could not find an estimate matching this link. Please contact BK Pools for assistance.', 'bk-estimate-generator' )
			);
			return;
		}

		// Check expiry.
		$expiry = (string) get_post_meta( $estimate_post_id, 'estimate_expiry', true );

		if ( ! empty( $expiry ) && strtotime( $expiry ) < current_time( 'timestamp' ) ) {
			self::render_error_page(
				__( 'Estimate Expired', 'bk-estimate-generator' ),
				__( 'This estimate has expired. Please submit a new request or contact BK Pools to request an updated estimate.', 'bk-estimate-generator' )
			);
			return;
		}

		// Build estimate data.
		$data = BK_Estimate_Builder::build( $estimate_post_id );

		if ( is_wp_error( $data ) ) {
			error_log( sprintf(
				'BK Estimate Page — failed to build estimate %d: [%s] %s',
				$estimate_post_id,
				$data->get_error_code(),
				$data->get_error_message()
			) );
			self::render_error_page(
				__( 'Estimate Unavailable', 'bk-estimate-generator' ),
				__( 'Your estimate is temporarily unavailable. Please try again shortly or contact BK Pools.', 'bk-estimate-generator' )
			);
			return;
		}

		// Enqueue styles.
		wp_enqueue_style(
			'bk-estimate-page',
			BK_ESTIMATOR_PLUGIN_URL . 'assets/css/estimate-page.css',
			array(),
			BK_ESTIMATOR_VERSION
		);

		// Load template — $data is available in template scope.
		$template = BK_ESTIMATOR_PLUGIN_DIR . 'templates/estimate-page.php';
		include $template;
		exit;
	}

	// -------------------------------------------------------------------------
	// Public static helpers (called from activation hook)
	// -------------------------------------------------------------------------

	/**
	 * Registers the estimate custom rewrite rule.
	 *
	 * Must be called from both the activation hook and the init hook so
	 * that the rule survives across requests.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register_rewrite_rules(): void {
		add_rewrite_rule(
			self::REWRITE_REGEX,
			'index.php?' . self::QUERY_VAR . '=$matches[1]',
			'top'
		);
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Finds an estimate post ID by its token, querying wp_estimate_meta directly.
	 *
	 * Uses a direct query because we are searching by value across all rows —
	 * get_post_meta() cannot do a reverse lookup efficiently.
	 *
	 * @since 1.0.0
	 *
	 * @param string $token The estimate token.
	 * @return int The estimate post ID, or 0 if not found.
	 */
	private static function find_estimate_by_token( string $token ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is safely constructed from $wpdb->prefix.
		$meta_table = $wpdb->prefix . 'estimate_meta';

		$object_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT object_ID FROM `{$meta_table}` WHERE estimate_token = %s LIMIT 1",
				$token
			)
		);

		return (int) $object_id;
	}

	/**
	 * Renders a minimal standalone error page and exits.
	 *
	 * @since 1.0.0
	 *
	 * @param string $title   Page heading.
	 * @param string $message Body message.
	 * @return void
	 */
	private static function render_error_page( string $title, string $message ): void {
		$company_name = BK_Settings::get_setting( 'company_name', 'BK Pools' );

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
		<!DOCTYPE html>
		<html lang="en-ZA">
		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title><?php echo esc_html( $title ); ?> — <?php echo esc_html( $company_name ); ?></title>
			<style>
				body { font-family: Arial, sans-serif; background: #f4f7fa; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
				.error-card { background: #fff; border-radius: 8px; padding: 48px; max-width: 480px; text-align: center; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
				h1 { color: #1a4a6e; font-size: 1.5rem; margin-bottom: 16px; }
				p { color: #555; line-height: 1.6; }
				.company { color: #1a4a6e; font-weight: bold; margin-top: 32px; font-size: 0.9rem; }
			</style>
		</head>
		<body>
			<div class="error-card">
				<h1><?php echo esc_html( $title ); ?></h1>
				<p><?php echo esc_html( $message ); ?></p>
				<p class="company"><?php echo esc_html( $company_name ); ?></p>
			</div>
		</body>
		</html>
		<?php
		// phpcs:enable
		exit;
	}
}
