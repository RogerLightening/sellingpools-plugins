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
 * Version:           1.2.3
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
define( 'BK_POOLS_VERSION', '1.2.3' );

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
// Load Plugin Update Checker (bundled in vendor/puc/).
// This must be loaded early so all plugins can register their checkers.
// -------------------------------------------------------------------------

if ( file_exists( BK_POOLS_PLUGIN_DIR . 'vendor/puc/load-v5p6.php' ) ) {
	require_once BK_POOLS_PLUGIN_DIR . 'vendor/puc/load-v5p6.php';
}

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

	// Register GitHub auth filter so update checks on raw.githubusercontent.com
	// and release-asset downloads on github.com work with private repositories.
	bk_pools_register_github_auth();

	// Register this plugin's update checker.
	bk_pools_register_update_checker(
		'https://raw.githubusercontent.com/RogerLightening/sellingpools-plugins/main/update-manifests/bk-pools-core.json',
		__FILE__,
		'bk-pools-core'
	);

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
// Plugin update helpers (available to all dependent plugins).
// -------------------------------------------------------------------------

/**
 * Adds an Authorization header to GitHub requests so update checks and
 * release-asset downloads work with the private sellingpools-plugins repo.
 *
 * Hooked once by bk_pools_init(); dependent plugins do not need to call this.
 *
 * @since 1.1.0
 *
 * @return void
 */
function bk_pools_register_github_auth(): void {
	$token = BK_Settings::get_setting( 'github_token', '' );

	if ( empty( $token ) ) {
		return;
	}

	add_filter(
		'http_request_args',
		static function ( array $args, string $url ) use ( $token ): array {
			// Inject the token for raw content and API/release downloads.
			if (
				str_contains( $url, 'raw.githubusercontent.com/RogerLightening' ) ||
				str_contains( $url, 'api.github.com/repos/RogerLightening' ) ||
				str_contains( $url, 'github.com/RogerLightening/sellingpools-plugins/releases' )
			) {
				if ( ! isset( $args['headers'] ) || ! is_array( $args['headers'] ) ) {
					$args['headers'] = array();
				}
				$args['headers']['Authorization'] = 'token ' . $token;
			}
			return $args;
		},
		10,
		2
	);
}

/**
 * Registers a Plugin Update Checker instance for a given plugin.
 *
 * Uses PUC's JSON-file update source. The JSON file is hosted in the
 * sellingpools-plugins GitHub repository under update-manifests/.
 *
 * Called by bk-pools-core for itself, and by each dependent plugin's boot
 * function after confirming bk-pools-core is active (which ensures PUC is loaded).
 *
 * @since 1.1.0
 *
 * @param string $metadata_url Absolute URL to the plugin's update-manifest JSON file.
 * @param string $plugin_file  Absolute path to the plugin's main PHP file (__FILE__).
 * @param string $slug         Plugin folder/slug (e.g. 'bk-agent-panel').
 * @return void
 */
function bk_pools_register_update_checker( string $metadata_url, string $plugin_file, string $slug ): void {
	if ( ! class_exists( 'YahnisElsts\PluginUpdateChecker\v5p6\PucFactory' ) ) {
		return;
	}

	YahnisElsts\PluginUpdateChecker\v5p6\PucFactory::buildUpdateChecker(
		$metadata_url,
		$plugin_file,
		$slug
	);
}

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
