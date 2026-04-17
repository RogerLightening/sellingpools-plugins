<?php
/**
 * BK Performance Tracker
 *
 * Admin performance dashboard, feature toggles, and automated tasks for BK Pools.
 *
 * @package           BK_Performance_Tracker
 * @author            Lightning Digital
 * @copyright         2026 Lightning Digital
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       SellingPools Performance Tracker
 * Plugin URI:        https://sellingpools.com
 * Description:       Admin performance dashboard, feature toggles, and automated tasks for SellingPools.
 * Version:           1.2.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Lightning Digital
 * Author URI:        https://lightningdigital.co.za
 * Text Domain:       bk-performance-tracker
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.txt
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin version.
 *
 * @var string
 */
define( 'BK_TRACKER_VERSION', '1.2.0' );

/**
 * Absolute path to the plugin directory (with trailing slash).
 *
 * @var string
 */
define( 'BK_TRACKER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Public URL to the plugin directory (with trailing slash).
 *
 * @var string
 */
define( 'BK_TRACKER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// -------------------------------------------------------------------------
// Activation / deactivation hooks.
//
// These must be registered at file-load time (not inside plugins_loaded)
// so WordPress can find them when the plugin is activated/deactivated.
// -------------------------------------------------------------------------

/**
 * Runs on plugin activation.
 *
 * Schedules daily cron events for stale-lead flagging and monthly resets.
 *
 * @return void
 */
function bk_tracker_activate(): void {
	if ( ! wp_next_scheduled( 'bk_pools_flag_stale_leads' ) ) {
		wp_schedule_event( time(), 'daily', 'bk_pools_flag_stale_leads' );
	}
	if ( ! wp_next_scheduled( 'bk_pools_monthly_reset' ) ) {
		wp_schedule_event( time(), 'daily', 'bk_pools_monthly_reset' );
	}
}
register_activation_hook( __FILE__, 'bk_tracker_activate' );

/**
 * Runs on plugin deactivation.
 *
 * Removes scheduled cron events.
 *
 * @return void
 */
function bk_tracker_deactivate(): void {
	wp_clear_scheduled_hook( 'bk_pools_flag_stale_leads' );
	wp_clear_scheduled_hook( 'bk_pools_monthly_reset' );
}
register_deactivation_hook( __FILE__, 'bk_tracker_deactivate' );

// -------------------------------------------------------------------------
// Bootstrap — deferred to plugins_loaded priority 20.
//
// WordPress loads plugins alphabetically: bk-performance-tracker loads
// BEFORE bk-pools-core, so BK_POOLS_VERSION is not defined at file-load
// time. We defer the dependency check and all class loading to priority 20
// of plugins_loaded, by which point bk-pools-core (priority 10) has fully
// initialised and fired bk_pools_loaded.
// -------------------------------------------------------------------------

/**
 * Loads class files and initialises the plugin after bk-pools-core is ready.
 *
 * @since 1.0.0
 *
 * @return void
 */
function bk_tracker_load(): void {
	if ( ! defined( 'BK_POOLS_VERSION' ) ) {
		add_action(
			'admin_notices',
			static function (): void {
				echo '<div class="notice notice-error"><p>';
				esc_html_e(
					'BK Performance Tracker requires the BK Pools Core plugin to be active.',
					'bk-performance-tracker'
				);
				echo '</p></div>';
			}
		);
		return;
	}

	require_once BK_TRACKER_PLUGIN_DIR . 'includes/class-bk-feature-toggles.php';
	require_once BK_TRACKER_PLUGIN_DIR . 'includes/class-bk-performance-metrics.php';
	require_once BK_TRACKER_PLUGIN_DIR . 'includes/class-bk-leaderboard.php';
	require_once BK_TRACKER_PLUGIN_DIR . 'includes/class-bk-performance-admin.php';
	require_once BK_TRACKER_PLUGIN_DIR . 'includes/class-bk-cron-jobs.php';

	// bk_pools_loaded fires at plugins_loaded priority 10 — it has already
	// fired by the time we reach priority 20, so call init directly.
	bk_tracker_init();

	// Register update checker (PUC is loaded by bk-pools-core).
	bk_pools_register_update_checker(
		'https://raw.githubusercontent.com/RogerLightening/sellingpools-plugins/main/update-manifests/bk-performance-tracker.json',
		__FILE__,
		'bk-performance-tracker'
	);
}
add_action( 'plugins_loaded', 'bk_tracker_load', 20 );

/**
 * Initialises the BK Performance Tracker plugin.
 *
 * @since 1.0.0
 *
 * @return void
 */
function bk_tracker_init(): void {
	new BK_Performance_Admin();
	new BK_Cron_Jobs();
}
