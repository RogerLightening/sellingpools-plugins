<?php
/**
 * Plugin Name: SellingPools Agent Panel
 * Description: Frontend dashboard and CRM for SellingPools agents to manage leads, pricing, and profiles.
 * Version:     1.2.3
 * Author:      Lightning Digital
 * Text Domain: bk-agent-panel
 * Requires PHP: 8.0
 *
 * @package BK_Agent_Panel
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'BK_PANEL_VERSION',    '1.2.3' );
define( 'BK_PANEL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BK_PANEL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// -------------------------------------------------------------------------
// Activation — create the Agent Dashboard page with the panel shortcode.
// -------------------------------------------------------------------------

register_activation_hook(
	__FILE__,
	static function (): void {
		$existing = get_option( 'bk_agent_panel_page_id' );

		if ( $existing && get_post( $existing ) ) {
			return;
		}

		$page_id = wp_insert_post(
			array(
				'post_title'   => 'Agent Dashboard',
				'post_name'    => 'agent-dashboard',
				'post_content' => '[bk_agent_panel]',
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_author'  => 1,
			)
		);

		if ( $page_id && ! is_wp_error( $page_id ) ) {
			update_option( 'bk_agent_panel_page_id', $page_id );
		}
	}
);

// -------------------------------------------------------------------------
// Boot on plugins_loaded.
// -------------------------------------------------------------------------

add_action( 'plugins_loaded', 'bk_agent_panel_boot', 20 );

/**
 * Boots the plugin after all plugins are loaded.
 *
 * @since 1.0.0
 * @return void
 */
function bk_agent_panel_boot(): void {
	if ( ! defined( 'BK_POOLS_VERSION' ) ) {
		add_action(
			'admin_notices',
			static function (): void {
				echo '<div class="notice notice-error"><p>'
					. esc_html__( 'SellingPools Agent Panel requires the SellingPools Core plugin to be active.', 'bk-agent-panel' )
					. '</p></div>';
			}
		);
		return;
	}

	// Load all class files.
	require_once BK_PANEL_PLUGIN_DIR . 'includes/class-bk-agent-auth.php';
	require_once BK_PANEL_PLUGIN_DIR . 'includes/class-bk-agent-dashboard.php';
	require_once BK_PANEL_PLUGIN_DIR . 'includes/class-bk-agent-leads.php';
	require_once BK_PANEL_PLUGIN_DIR . 'includes/class-bk-agent-crm.php';
	require_once BK_PANEL_PLUGIN_DIR . 'includes/class-bk-agent-pricing.php';
	require_once BK_PANEL_PLUGIN_DIR . 'includes/class-bk-agent-profile.php';
	require_once BK_PANEL_PLUGIN_DIR . 'includes/class-bk-agent-router.php';

	// Initialise.
	new BK_Agent_Auth();
	new BK_Agent_CRM();
	new BK_Agent_Router();

	// Register update checker (PUC is loaded by bk-pools-core).
	bk_pools_register_update_checker(
		'https://raw.githubusercontent.com/RogerLightening/sellingpools-plugins/main/update-manifests/bk-agent-panel.json',
		__FILE__,
		'bk-agent-panel'
	);
}
