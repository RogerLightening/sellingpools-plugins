<?php
/**
 * BK Estimate HTML
 *
 * Thin wrapper around the estimate-page.php template.
 * Primarily used by BK_Estimate_Page to keep the rendering path clean.
 *
 * @package BK_Estimate_Generator
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BK_Estimate_HTML
 *
 * Renders the public estimate page HTML by loading the plugin template.
 *
 * @since 1.0.0
 */
class BK_Estimate_HTML {

	/**
	 * Renders and outputs the estimate page HTML for the given data.
	 *
	 * Outputs directly to the buffer (use ob_start() before calling if you
	 * need the result as a string).
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Structured estimate data from BK_Estimate_Builder::build().
	 * @return void
	 */
	public static function render( array $data ): void {
		include BK_ESTIMATOR_PLUGIN_DIR . 'templates/estimate-page.php';
	}
}
