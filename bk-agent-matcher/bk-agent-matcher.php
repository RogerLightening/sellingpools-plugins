<?php
/**
 * BK Agent Matcher
 *
 * Suburb autocomplete and proximity-based agent matching for the BK Pools lead generation platform.
 *
 * @package           BK_Agent_Matcher
 * @author            Lightning Digital
 * @copyright         2026 Lightning Digital
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       SellingPools Agent Matcher
 * Plugin URI:        https://sellingpools.com
 * Description:       Suburb autocomplete and proximity-based agent matching for SellingPools lead generation.
 * Version:           1.2.2
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Lightning Digital
 * Author URI:        https://lightningdigital.co.za
 * Text Domain:       bk-agent-matcher
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
define( 'BK_MATCHER_VERSION', '1.2.2' );

/**
 * Absolute path to this plugin's directory (with trailing slash).
 *
 * @var string
 */
define( 'BK_MATCHER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Public URL to this plugin's directory (with trailing slash).
 *
 * @var string
 */
define( 'BK_MATCHER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * JetFormBuilder form ID for the BK Pools Estimate Request form.
 *
 * @var int
 */
define( 'BK_MATCHER_FORM_ID', 1037 );

// -------------------------------------------------------------------------
// Bootstrap on plugins_loaded — after bk-pools-core has had a chance to load.
// -------------------------------------------------------------------------

add_action( 'plugins_loaded', 'bk_matcher_init', 20 );

/**
 * Initialises the BK Agent Matcher plugin.
 *
 * Checks that BK Pools Core is active before loading class files.
 * Registers an admin notice and bails early if core is missing.
 *
 * @since 1.0.0
 *
 * @return void
 */
function bk_matcher_init(): void {
	if ( ! defined( 'BK_POOLS_VERSION' ) ) {
		add_action( 'admin_notices', 'bk_matcher_missing_core_notice' );
		return;
	}

	// Load all class files.
	require_once BK_MATCHER_PLUGIN_DIR . 'includes/class-bk-suburb-lookup.php';
	require_once BK_MATCHER_PLUGIN_DIR . 'includes/class-bk-matcher.php';
	require_once BK_MATCHER_PLUGIN_DIR . 'includes/class-bk-matcher-hooks.php';
	// Initialise the suburb autocomplete AJAX handler.
	new BK_Suburb_Lookup();

	// Initialise the JetFormBuilder / save_post hooks.
	new BK_Matcher_Hooks();

	// Note: BK_JFB_Redirect (class-bk-jfb-redirect.php) is no longer loaded.
	// JFB's own Redirect To Page action now handles the post-submission redirect,
	// using the estimate_token written by the hidden form field so the URL is
	// available to JFB before the after-send hook fires.

	// Register update checker (PUC is loaded by bk-pools-core).
	bk_pools_register_update_checker(
		'https://raw.githubusercontent.com/RogerLightening/sellingpools-plugins/main/update-manifests/bk-agent-matcher.json',
		__FILE__,
		'bk-agent-matcher'
	);
}

/**
 * Displays an admin notice when BK Pools Core is not active.
 *
 * @since 1.0.0
 *
 * @return void
 */
function bk_matcher_missing_core_notice(): void {
	?>
	<div class="notice notice-error">
		<p>
			<strong><?php esc_html_e( 'BK Agent Matcher', 'bk-agent-matcher' ); ?>:</strong>
			<?php esc_html_e( 'BK Agent Matcher requires BK Pools Core to be installed and activated.', 'bk-agent-matcher' ); ?>
		</p>
	</div>
	<?php
}
