<?php
/**
 * Plugin Name: SellingPools Estimate Generator
 * Description: HTML and PDF estimate generation with email delivery for SellingPools.
 * Version:     1.2.0
 * Author:      Lightning Digital
 * Text Domain: bk-estimate-generator
 * Requires PHP: 8.0
 *
 * @package BK_Estimate_Generator
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'BK_ESTIMATOR_VERSION',    '1.2.0' );
define( 'BK_ESTIMATOR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BK_ESTIMATOR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// -------------------------------------------------------------------------
// Activation / deactivation hooks — registered before any early return.
// -------------------------------------------------------------------------

register_activation_hook(
	__FILE__,
	static function (): void {
		// Class files are not yet loaded during activation — require explicitly.
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-bk-estimate-page.php';
		BK_Estimate_Page::register_rewrite_rules();
		flush_rewrite_rules();
	}
);

register_deactivation_hook(
	__FILE__,
	static function (): void {
		flush_rewrite_rules();
	}
);

// -------------------------------------------------------------------------
// Boot on plugins_loaded.
// -------------------------------------------------------------------------

add_action( 'plugins_loaded', 'bk_estimate_generator_boot', 20 );

/**
 * Boots the plugin after all plugins are loaded.
 *
 * Verifies that bk-pools-core is active before requiring any class files
 * so that class resolution never fails. Shows an admin notice and bails
 * early if the dependency is missing.
 *
 * @since 1.0.0
 * @return void
 */
function bk_estimate_generator_boot(): void {
	if ( ! defined( 'BK_POOLS_VERSION' ) ) {
		add_action(
			'admin_notices',
			static function (): void {
				echo '<div class="notice notice-error"><p>'
					. esc_html__( 'BK Estimate Generator requires the BK Pools Core plugin to be active.', 'bk-estimate-generator' )
					. '</p></div>';
			}
		);
		return;
	}

	// Load class files.
	require_once BK_ESTIMATOR_PLUGIN_DIR . 'includes/class-bk-estimate-builder.php';
	require_once BK_ESTIMATOR_PLUGIN_DIR . 'includes/class-bk-estimate-html.php';
	require_once BK_ESTIMATOR_PLUGIN_DIR . 'includes/class-bk-estimate-pdf.php';
	require_once BK_ESTIMATOR_PLUGIN_DIR . 'includes/class-bk-estimate-email.php';
	require_once BK_ESTIMATOR_PLUGIN_DIR . 'includes/class-bk-estimate-page.php';

	// Initialise.
	new BK_Estimate_Page();
	new BK_Estimate_Generator();

	// Register update checker (PUC is loaded by bk-pools-core).
	bk_pools_register_update_checker(
		'https://raw.githubusercontent.com/RogerLightening/sellingpools-plugins/main/update-manifests/bk-estimate-generator.json',
		__FILE__,
		'bk-estimate-generator'
	);
}

/**
 * Class BK_Estimate_Generator
 *
 * Main controller. Hooks into bk_pools_agents_matched and orchestrates the
 * full estimate generation pipeline: build data → generate PDFs → send email.
 *
 * TODO: For high-traffic sites, move generate_estimate() to a background job
 * via wp_schedule_single_event() so the form submission request is not
 * blocked by PDF rendering and SMTP. For now it runs synchronously.
 *
 * @since 1.0.0
 */
class BK_Estimate_Generator {

	/**
	 * Constructor — registers WordPress hooks.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'bk_pools_agents_matched', array( $this, 'generate_estimate' ), 10, 2 );
	}

	/**
	 * Orchestrates the full estimate generation pipeline.
	 *
	 * Called by bk_pools_agents_matched after the matcher assigns agents and
	 * writes rows to wp_bk_lead_agents.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $estimate_post_id The estimate CPT post ID.
	 * @param array $matched_agents   Array of matched agent data from BK_Matcher.
	 * @return void
	 */
	public function generate_estimate( int $estimate_post_id, array $matched_agents ): void {
		// 1. Assemble all estimate data into a structured array.
		$data = BK_Estimate_Builder::build( $estimate_post_id );

		if ( is_wp_error( $data ) ) {
			error_log( sprintf(
				'BK Estimate: Builder failed for estimate %d: [%s] %s',
				$estimate_post_id,
				$data->get_error_code(),
				$data->get_error_message()
			) );
			return;
		}

		// 2. Generate per-agent PDFs.
		$pdfs = BK_Estimate_PDF::generate( $estimate_post_id, $data );

		if ( empty( $pdfs ) ) {
			error_log( 'BK Estimate: PDF generation returned empty for estimate ' . $estimate_post_id . '. Continuing with link-only email.' );
		}

		// 3. Send estimate email to customer.
		$sent = BK_Estimate_Email::send( $estimate_post_id, $data, $pdfs );

		if ( ! $sent ) {
			error_log( 'BK Estimate: Email delivery failed for estimate ' . $estimate_post_id );
		}

		error_log( 'BK Estimate: Successfully generated estimate for post ' . $estimate_post_id );
	}
}
