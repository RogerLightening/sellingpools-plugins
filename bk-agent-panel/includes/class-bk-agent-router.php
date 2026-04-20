<?php
/**
 * BK Agent Router
 *
 * Registers the [bk_agent_panel] shortcode and routes panel section requests
 * to the correct template.
 *
 * @package BK_Agent_Panel
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BK_Agent_Router
 *
 * URL structure (all on the same WP page via shortcode):
 *  /agent-dashboard/                              — Dashboard
 *  /agent-dashboard/?section=leads                — Leads list
 *  /agent-dashboard/?section=leads&lead_id=123    — Single lead detail
 *  /agent-dashboard/?section=pricing              — Pricing management
 *  /agent-dashboard/?section=profile              — Profile & settings
 *
 * @since 1.0.0
 */
class BK_Agent_Router {

	/**
	 * Valid panel sections.
	 *
	 * @since 1.0.0
	 * @var string[]
	 */
	const SECTIONS = array( 'dashboard', 'leads', 'pricing', 'profile' );

	/**
	 * Constructor — registers the shortcode and template-redirect hook.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_shortcode( 'bk_agent_panel', array( $this, 'render_panel' ) );
		add_action( 'template_redirect', array( $this, 'handle_template_redirect' ) );
	}

	// -------------------------------------------------------------------------
	// Template-redirect handler (primary render path)
	// -------------------------------------------------------------------------

	/**
	 * Intercepts the panel page on template_redirect and outputs a standalone
	 * HTML page — bypassing the Bricks (or any other) theme entirely.
	 *
	 * This mirrors the pattern used by BK_Estimate_Page::handle_template_redirect().
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_template_redirect(): void {
		$panel_page_id = (int) get_option( 'bk_agent_panel_page_id' );

		if ( ! $panel_page_id || ! is_page( $panel_page_id ) ) {
			return;
		}

		// Handle the login form POST before any output, so wp_signon()'s
		// setcookie() and the wp_login-hooked redirect can fire while headers
		// are still modifiable. On success, BK_Agent_Auth::redirect_after_login()
		// exits before we reach the include below.
		$login_error = $this->maybe_handle_login();

		$access        = BK_Agent_Auth::check_access();
		$section       = $this->get_section();
		$panel_url     = BK_Agent_Auth::get_panel_url();
		$agent_post_id = $access['agent_post_id'];

		// Enqueue panel assets (CSS + JS + localized data).
		$this->enqueue_assets( $agent_post_id, $section, $panel_url );

		// Output the full standalone page and bail before the theme renders.
		include BK_PANEL_PLUGIN_DIR . 'templates/panel-standalone.php';
		exit;
	}

	/**
	 * Processes the login form POST, if present.
	 *
	 * Must be called before any output so wp_signon() can set auth cookies
	 * and the wp_login action can redirect.
	 *
	 * @since 1.2.1
	 * @return string Error message on failure, empty string otherwise.
	 */
	private function maybe_handle_login(): string {
		if ( ! isset( $_POST['bk_login_submit'] ) ) {
			return '';
		}

		check_admin_referer( 'bk_agent_login' );

		$credentials = array(
			'user_login'    => sanitize_user( wp_unslash( $_POST['log'] ?? '' ) ),
			'user_password' => wp_unslash( $_POST['pwd'] ?? '' ),
			'remember'      => ! empty( $_POST['rememberme'] ),
		);

		$user = wp_signon( $credentials, is_ssl() );

		if ( is_wp_error( $user ) ) {
			return $user->get_error_message();
		}

		// Safety net — in normal flow redirect_after_login has already exited.
		wp_safe_redirect( BK_Agent_Auth::get_panel_url() );
		exit;
	}

	// -------------------------------------------------------------------------
	// Shortcode callback (fallback only — template_redirect fires first)
	// -------------------------------------------------------------------------

	/**
	 * Shortcode fallback: [bk_agent_panel]
	 *
	 * In normal operation this never runs because handle_template_redirect()
	 * already exited.  Kept so the page content field is not blank in wp-admin.
	 *
	 * @since 1.0.0
	 *
	 * @return string Empty string — content already sent.
	 */
	public function render_panel(): string {
		return '';
	}

	// -------------------------------------------------------------------------
	// Public helpers
	// -------------------------------------------------------------------------

	/**
	 * Returns the sanitised current section name.
	 *
	 * @since 1.0.0
	 * @return string One of: dashboard, leads, pricing, profile.
	 */
	public function get_section(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$raw = isset( $_GET['section'] ) ? sanitize_key( $_GET['section'] ) : 'dashboard';

		return in_array( $raw, self::SECTIONS, true ) ? $raw : 'dashboard';
	}

	/**
	 * Returns the sanitised lead_id query parameter, or 0.
	 *
	 * @since 1.0.0
	 * @return int Lead agent row ID.
	 */
	public function get_lead_id(): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_GET['lead_id'] ) ? (int) $_GET['lead_id'] : 0;
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Enqueues panel CSS and JS with localised data.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $agent_post_id Builder post ID of the current agent.
	 * @param string $section       Current section slug.
	 * @param string $panel_url     Panel page URL.
	 * @return void
	 */
	private function enqueue_assets( int $agent_post_id, string $section, string $panel_url ): void {
		wp_enqueue_style(
			'bk-agent-panel',
			BK_PANEL_PLUGIN_URL . 'assets/css/agent-panel.css',
			array(),
			BK_PANEL_VERSION
		);

		wp_enqueue_script(
			'bk-agent-panel',
			BK_PANEL_PLUGIN_URL . 'assets/js/agent-panel.js',
			array(),
			BK_PANEL_VERSION,
			true
		);

		wp_localize_script(
			'bk-agent-panel',
			'bkPanel',
			array(
				'ajax_url'      => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'bk_agent_panel' ),
				'agent_post_id' => $agent_post_id,
				'panel_url'     => $panel_url,
				'statuses'      => array(
					'new', 'contacted', 'no_answer', 'wrong_number',
					'site_visit', 'quoted', 'won', 'lost', 'stale',
				),
				'status_labels' => array(
					'new'          => __( 'New', 'bk-agent-panel' ),
					'contacted'    => __( 'Contacted', 'bk-agent-panel' ),
					'no_answer'    => __( 'No Answer', 'bk-agent-panel' ),
					'wrong_number' => __( 'Wrong Number', 'bk-agent-panel' ),
					'site_visit'   => __( 'Site Visit', 'bk-agent-panel' ),
					'quoted'       => __( 'Quoted', 'bk-agent-panel' ),
					'won'          => __( 'Won', 'bk-agent-panel' ),
					'lost'         => __( 'Lost', 'bk-agent-panel' ),
					'stale'        => __( 'Stale', 'bk-agent-panel' ),
				),
			)
		);

		// On the profile section, also enqueue the suburb autocomplete from bk-agent-matcher.
		if ( 'profile' === $section ) {
			$matcher_dir = WP_PLUGIN_DIR . '/bk-agent-matcher/';
			$matcher_url = WP_PLUGIN_URL . '/bk-agent-matcher/';

			if ( file_exists( $matcher_dir . 'assets/css/suburb-autocomplete.css' ) ) {
				wp_enqueue_style(
					'bk-suburb-autocomplete',
					$matcher_url . 'assets/css/suburb-autocomplete.css',
					array(),
					BK_PANEL_VERSION
				);
			}

			if ( file_exists( $matcher_dir . 'assets/js/suburb-autocomplete.js' ) ) {
				wp_enqueue_script(
					'bk-suburb-autocomplete',
					$matcher_url . 'assets/js/suburb-autocomplete.js',
					array(),
					BK_PANEL_VERSION,
					true
				);

				wp_localize_script(
					'bk-suburb-autocomplete',
					'bk_suburb_params',
					array(
						'ajax_url'  => admin_url( 'admin-ajax.php' ),
						'nonce'     => wp_create_nonce( 'bk_suburb_search' ),
						'min_chars' => 2,
					)
				);
			}
		}
	}

	/**
	 * Renders an error message for access-denied scenarios.
	 *
	 * @since 1.0.0
	 *
	 * @param string $error Error code from BK_Agent_Auth::check_access().
	 * @return void
	 */
	private function render_access_error( string $error ): void {
		if ( 'not_logged_in' === $error ) {
			include BK_PANEL_PLUGIN_DIR . 'templates/login.php';
			return;
		}

		echo '<div class="bk-agent-panel bk-panel-error">';

		if ( 'no_capability' === $error ) {
			echo '<p>' . esc_html__( 'You do not have permission to access the agent panel.', 'bk-agent-panel' ) . '</p>';
		} elseif ( 'no_profile' === $error ) {
			echo '<p>' . esc_html__( 'Your agent profile has not been configured yet. Please contact SellingPools to get set up.', 'bk-agent-panel' ) . '</p>';
		}

		echo '</div>';
	}
}
