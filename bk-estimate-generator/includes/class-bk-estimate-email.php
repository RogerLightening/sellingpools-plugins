<?php
/**
 * BK Estimate Email
 *
 * Composes and sends the estimate email to the customer via wp_mail().
 *
 * @package BK_Estimate_Generator
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BK_Estimate_Email
 *
 * Sends an HTML email to the customer with:
 *  - A summary of the three agent quotes.
 *  - A call-to-action button linking to the online estimate page.
 *  - All three agent PDFs as file attachments (if generated successfully).
 *
 * @since 1.0.0
 */
class BK_Estimate_Email {

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Sends the estimate email to the customer.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $estimate_post_id The estimate CPT post ID.
	 * @param array $data             Structured estimate data from BK_Estimate_Builder::build().
	 * @param array $pdf_paths        Array of PDF info from BK_Estimate_PDF::generate().
	 * @return bool True if wp_mail() accepted the message, false otherwise.
	 */
	public static function send( int $estimate_post_id, array $data, array $pdf_paths ): bool {
		$customer     = $data['customer'];
		$pool         = $data['pool_shape'];
		$estimate     = $data['estimate'];
		$company      = $data['company'];

		$to      = $customer['email'];
		$subject = sprintf(
			/* translators: 1: pool shape name */
			__( 'Your Pool Estimate — %s | BK Pools', 'bk-estimate-generator' ),
			$pool['name']
		);

		// Build email headers — no need for add_filter/remove_filter dance.
		$from_name  = $company['name'] ?: 'BK Pools';
		$from_email = $company['email'] ?: get_option( 'admin_email' );

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', $from_name, $from_email ),
		);

		// Render HTML body from template.
		$body = self::render_email_body( $data );

		// Build attachments list from generated PDFs.
		$attachments = array();

		foreach ( $pdf_paths as $pdf ) {
			if ( ! empty( $pdf['pdf_path'] ) && file_exists( $pdf['pdf_path'] ) ) {
				$attachments[] = $pdf['pdf_path'];
			}
		}

		$sent = wp_mail( $to, $subject, $body, $headers, $attachments );

		if ( ! $sent ) {
			error_log( sprintf(
				'BK Estimate Email — wp_mail() returned false for estimate %d (to: %s).',
				$estimate_post_id,
				$to
			) );
		}

		return $sent;
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Renders the HTML email body from the email template.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Structured estimate data.
	 * @return string HTML email body.
	 */
	private static function render_email_body( array $data ): string {
		ob_start();
		include BK_ESTIMATOR_PLUGIN_DIR . 'templates/email-template.php';
		return ob_get_clean();
	}
}
