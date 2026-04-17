<?php
/**
 * BK Pools Core
 *
 * Foundation plugin for the BK Pools lead generation platform.
 * Provides database tables, custom roles, settings, and shared utilities.
 *
 * @package           BK_Pools_Core
 * @author            Lightning Digital
 * @copyright         2026 Lightning Digital
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       SellingPools Core
 * Plugin URI:        https://sellingpools.com
 * Description:       Foundation plugin for the SellingPools lead generation platform. Provides database tables, custom roles, settings, and shared utilities.
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Lightning Digital
 * Author URI:        https://lightningdigital.co.za
 * Text Domain:       bk-pools-core
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
define( 'BK_POOLS_VERSION', '1.0.0' );

/**
 * Absolute path to the plugin directory (with trailing slash).
 *
 * @var string
 */
define( 'BK_POOLS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Public URL to the plugin directory (with trailing slash).
 *
 * @var string
 */
define( 'BK_POOLS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Default VAT rate (15%). Overridable via the BK Pools Settings page.
 *
 * @var float
 */
define( 'BK_POOLS_VAT_RATE', 0.15 );

// -------------------------------------------------------------------------
// Load all class files.
// -------------------------------------------------------------------------

require_once BK_POOLS_PLUGIN_DIR . 'includes/class-bk-database.php';
require_once BK_POOLS_PLUGIN_DIR . 'includes/class-bk-roles.php';
require_once BK_POOLS_PLUGIN_DIR . 'includes/class-bk-settings.php';
require_once BK_POOLS_PLUGIN_DIR . 'includes/class-bk-helpers.php';
require_once BK_POOLS_PLUGIN_DIR . 'includes/class-bk-haversine.php';

// -------------------------------------------------------------------------
// Activation hook.
// -------------------------------------------------------------------------

/**
 * Runs on plugin activation.
 *
 * Creates database tables and registers custom roles.
 *
 * @return void
 */
function bk_pools_activate(): void {
	BK_Database::create_tables();
	BK_Roles::create_roles();

	// Flush rewrite rules after activation.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'bk_pools_activate' );

// -------------------------------------------------------------------------
// Deactivation hook.
// -------------------------------------------------------------------------

/**
 * Runs on plugin deactivation.
 *
 * Removes custom roles. Database tables are intentionally preserved
 * to avoid data loss on accidental deactivation.
 *
 * @return void
 */
function bk_pools_deactivate(): void {
	BK_Roles::remove_roles();
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'bk_pools_deactivate' );

// -------------------------------------------------------------------------
// Bootstrap on init.
// -------------------------------------------------------------------------

/**
 * Initialises the BK Pools Core plugin.
 *
 * Registers the admin settings page and fires the bk_pools_loaded action
 * so dependent plugins can confirm core is active.
 *
 * @return void
 */
function bk_pools_init(): void {
	// Initialise the settings page (registers menus and settings).
	BK_Settings::init();

	/**
	 * Fires after BK Pools Core has fully loaded.
	 *
	 * Dependent plugins should hook here to confirm core is active
	 * before registering their own features.
	 *
	 * @since 1.0.0
	 */
	do_action( 'bk_pools_loaded' );
}
add_action( 'plugins_loaded', 'bk_pools_init' );

// -------------------------------------------------------------------------
// Enqueue admin assets.
// -------------------------------------------------------------------------

/**
 * Enqueues admin stylesheet and script on BK Pools admin pages.
 *
 * @param string $hook_suffix The current admin page hook suffix.
 * @return void
 */
function bk_pools_enqueue_admin_assets( string $hook_suffix ): void {
	// Only load on BK Pools admin pages.
	if ( ! str_contains( $hook_suffix, 'bk-pools' ) ) {
		return;
	}

	wp_enqueue_style(
		'bk-pools-admin',
		BK_POOLS_PLUGIN_URL . 'assets/css/admin.css',
		array(),
		BK_POOLS_VERSION
	);

	wp_enqueue_script(
		'bk-pools-admin',
		BK_POOLS_PLUGIN_URL . 'assets/js/admin.js',
		array( 'jquery' ),
		BK_POOLS_VERSION,
		true
	);

	// Pass data to the admin script.
	wp_localize_script(
		'bk-pools-admin',
		'bkPoolsAdmin',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'bk_pools_admin_nonce' ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'bk_pools_enqueue_admin_assets' );
