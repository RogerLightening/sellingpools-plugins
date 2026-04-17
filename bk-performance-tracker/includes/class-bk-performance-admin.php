<?php
/**
 * BK Performance Admin
 *
 * Registers the "BK Pools → Performance Dashboard" wp-admin sub-page and
 * enqueues the associated stylesheet.
 *
 * @package BK_Performance_Tracker
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BK_Performance_Admin
 *
 * @since 1.0.0
 */
class BK_Performance_Admin {

	/**
	 * The wp-admin page hook suffix returned by add_submenu_page().
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private string $page_hook = '';

	/**
	 * Constructor — registers all WordPress hooks.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	// -------------------------------------------------------------------------
	// Menu registration
	// -------------------------------------------------------------------------

	/**
	 * Adds the Performance Dashboard sub-page under the BK Pools top-level menu.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_menu(): void {
		$this->page_hook = add_submenu_page(
			'bk-pools',                                               // Parent slug (BK Pools top-level menu).
			__( 'Performance Dashboard', 'bk-performance-tracker' ),
			__( 'Performance', 'bk-performance-tracker' ),
			'manage_options',
			'bk-performance-dashboard',
			array( $this, 'render_page' )
		);
	}

	// -------------------------------------------------------------------------
	// Asset enqueueing
	// -------------------------------------------------------------------------

	/**
	 * Enqueues the admin dashboard stylesheet on the Performance Dashboard page.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( $hook_suffix !== $this->page_hook ) {
			return;
		}

		wp_enqueue_style(
			'bk-performance-tracker-admin',
			BK_TRACKER_PLUGIN_URL . 'assets/css/admin-dashboard.css',
			array(),
			BK_TRACKER_VERSION
		);
	}

	// -------------------------------------------------------------------------
	// Page renderer
	// -------------------------------------------------------------------------

	/**
	 * Renders the Performance Dashboard admin page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'bk-performance-tracker' ) );
		}

		include BK_TRACKER_PLUGIN_DIR . 'templates/admin-dashboard.php';
	}
}
