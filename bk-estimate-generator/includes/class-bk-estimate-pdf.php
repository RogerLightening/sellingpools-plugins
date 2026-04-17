<?php
/**
 * BK Estimate PDF
 *
 * Generates one PDF per agent per estimate using DomPDF.
 * Falls back gracefully if the DomPDF vendor directory is not present.
 *
 * @package BK_Estimate_Generator
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BK_Estimate_PDF
 *
 * Renders a per-agent HTML document with inline CSS and converts it to PDF
 * via DomPDF. PDFs are saved to wp-content/uploads/bk-estimates/{post_id}/
 * and references are stored in wp_bk_estimate_pdfs.
 *
 * @since 1.0.0
 */
class BK_Estimate_PDF {

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Generates one PDF per agent in the estimate data array.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $estimate_post_id The estimate CPT post ID.
	 * @param array $data             Structured estimate data from BK_Estimate_Builder::build().
	 * @return array Array of generated PDF info: [['agent_post_id','pdf_path','pdf_url'], ...]
	 */
	public static function generate( int $estimate_post_id, array $data ): array {
		// Load DomPDF.
		$autoload = BK_ESTIMATOR_PLUGIN_DIR . 'vendor/autoload.php';

		if ( ! file_exists( $autoload ) ) {
			error_log( 'BK Estimate PDF: DomPDF not installed. Run `composer install` in the bk-estimate-generator plugin directory.' );
			return array();
		}

		require_once $autoload;

		if ( ! class_exists( \Dompdf\Dompdf::class ) ) {
			error_log( 'BK Estimate PDF: Dompdf class not found after autoload.' );
			return array();
		}

		// Prepare the uploads directory.
		$upload_dir    = wp_upload_dir();
		$base_dir      = trailingslashit( $upload_dir['basedir'] ) . 'bk-estimates/' . $estimate_post_id;
		$base_url      = trailingslashit( $upload_dir['baseurl'] ) . 'bk-estimates/' . $estimate_post_id;

		if ( ! wp_mkdir_p( $base_dir ) ) {
			error_log( 'BK Estimate PDF: Failed to create directory: ' . $base_dir );
			return array();
		}

		$results = array();

		foreach ( $data['agents'] as $agent ) {
			$agent_post_id = (int) $agent['post_id'];
			$filename      = 'estimate-' . $agent_post_id . '.pdf';
			$pdf_path      = $base_dir . '/' . $filename;
			$pdf_url       = $base_url . '/' . $filename;

			$html = self::build_pdf_html( $data, $agent );

			$pdf_file = self::render_pdf( $html, $pdf_path );

			if ( ! $pdf_file ) {
				error_log( sprintf(
					'BK Estimate PDF: Failed to render PDF for agent %d / estimate %d.',
					$agent_post_id,
					$estimate_post_id
				) );
				continue;
			}

			// Store reference in wp_bk_estimate_pdfs.
			self::store_pdf_reference( $estimate_post_id, $agent_post_id, $pdf_path, $pdf_url );

			$results[] = array(
				'agent_post_id' => $agent_post_id,
				'agent_name'    => $agent['company_name'],
				'pdf_path'      => $pdf_path,
				'pdf_url'       => $pdf_url,
			);
		}

		return $results;
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Builds the full HTML document for a single agent's PDF.
	 *
	 * All CSS is inline — DomPDF does not load external stylesheets.
	 * Images are embedded as base64 data URIs so they render in the PDF.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data  Full estimate data array.
	 * @param array $agent Single agent data array from $data['agents'].
	 * @return string HTML document string.
	 */
	private static function build_pdf_html( array $data, array $agent ): string {
		$customer    = $data['customer'];
		$pool        = $data['pool_shape'];
		$estimate    = $data['estimate'];
		$vat_display = esc_html( $data['vat_display'] );

		// Inline images as base64.
		$agent_logo_src = self::inline_image( $agent['company_logo_url'] );
		$shape_img_src  = self::inline_image( $pool['diagram_url'] );

		// Currency formatting.
		$install_fmt = esc_html( BK_Helpers::format_currency( $agent['install_price_incl'] ) );
		$travel_fmt  = $agent['travel_fee_incl'] > 0
			? esc_html( BK_Helpers::format_currency( $agent['travel_fee_incl'] ) )
			: null;
		$total_fmt   = esc_html( BK_Helpers::format_currency( $agent['total_estimate_incl'] ) );

		$distance_fmt = number_format( (float) $agent['distance_km'], 1 );

		ob_start();
		?>
<!DOCTYPE html>
<html lang="en-ZA">
<head>
<meta charset="UTF-8">
<title>Pool Estimate</title>
<style>
	* { margin: 0; padding: 0; box-sizing: border-box; }
	body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #333; line-height: 1.5; }
	.page { padding: 30px 40px; }
	/* Header */
	.header { border-bottom: 3px solid #1a6ea8; padding-bottom: 16px; margin-bottom: 20px; }
	.header-inner { display: table; width: 100%; }
	.header-logo { display: table-cell; vertical-align: middle; width: 120px; }
	.header-logo img { max-width: 110px; max-height: 60px; }
	.header-details { display: table-cell; vertical-align: middle; padding-left: 20px; }
	.header-details h1 { font-size: 18px; color: #1a6ea8; margin-bottom: 4px; }
	.header-details p { font-size: 10px; color: #666; margin-top: 2px; }
	.header-details .contact-line { font-size: 10px; color: #444; margin-top: 3px; }
	/* Section headings */
	.section-heading { background: #1a6ea8; color: #fff; padding: 6px 10px; font-size: 11px; font-weight: bold; margin: 18px 0 8px 0; }
	/* Tables */
	table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
	td, th { padding: 6px 8px; }
	.data-table td { border: 1px solid #ddd; font-size: 10px; }
	.data-table tr:nth-child(even) td { background: #f7f9fb; }
	/* Pricing table */
	.pricing-table th { background: #1a6ea8; color: #fff; text-align: left; font-size: 10px; border: 1px solid #1a6ea8; }
	.pricing-table td { border: 1px solid #ddd; font-size: 10px; }
	.pricing-table tr:nth-child(even) td { background: #f7f9fb; }
	.pricing-table .total-row td { background: #1a6ea8; color: #fff; font-weight: bold; border-color: #1a6ea8; }
	.text-right { text-align: right; }
	/* Shape image */
	.shape-img { max-width: 180px; max-height: 120px; margin-bottom: 8px; }
	/* Contact block */
	.contact-grid { display: table; width: 100%; }
	.contact-cell { display: table-cell; width: 50%; font-size: 10px; }
	/* Content sections */
	.tc-content { font-size: 9px; color: #555; line-height: 1.4; }
	.tc-content p { margin-bottom: 6px; }
	/* Footer */
	.footer { border-top: 2px solid #1a6ea8; margin-top: 24px; padding-top: 10px; font-size: 9px; color: #888; display: table; width: 100%; }
	.footer-left { display: table-cell; }
	.footer-right { display: table-cell; text-align: right; }
	.footer-right a { color: #1a6ea8; text-decoration: none; }
</style>
</head>
<body>
<div class="page">

	<!-- Header: agent branding + contact -->
	<div class="header">
		<div class="header-inner">
			<div class="header-logo">
				<?php if ( $agent_logo_src ) : ?>
					<img src="<?php echo $agent_logo_src; ?>" alt="<?php echo esc_attr( $agent['company_name'] ); ?>">
				<?php endif; ?>
			</div>
			<div class="header-details">
				<h1><?php echo esc_html( $agent['company_name'] ); ?></h1>
				<p>Pool Installation Estimate</p>
				<?php if ( $agent['contact_name'] ) : ?>
					<p class="contact-line"><?php echo esc_html( $agent['contact_name'] ); ?></p>
				<?php endif; ?>
				<?php if ( $agent['phone'] ) : ?>
					<p class="contact-line">&#9990; <?php echo esc_html( $agent['phone'] ); ?></p>
				<?php endif; ?>
				<?php if ( $agent['email'] ) : ?>
					<p class="contact-line">&#9993; <?php echo esc_html( $agent['email'] ); ?></p>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<!-- Customer details -->
	<div class="section-heading">Customer Details</div>
	<div class="contact-grid">
		<div class="contact-cell">
			<strong>Name:</strong> <?php echo esc_html( $customer['name'] ); ?><br>
			<strong>Phone:</strong> <?php echo esc_html( $customer['phone'] ); ?><br>
			<strong>Email:</strong> <?php echo esc_html( $customer['email'] ); ?>
		</div>
		<div class="contact-cell">
			<strong>Location:</strong> <?php echo esc_html( $customer['suburb'] ); ?>, <?php echo esc_html( $customer['area'] ); ?><br>
			<strong>Province:</strong> <?php echo esc_html( $customer['province'] ); ?><br>
			<strong>Distance from agent:</strong> <?php echo esc_html( $distance_fmt ); ?> km
		</div>
	</div>

	<!-- Pool shape -->
	<div class="section-heading">Pool Details &mdash; <?php echo esc_html( $pool['name'] ); ?></div>
	<?php if ( $shape_img_src ) : ?>
		<img class="shape-img" src="<?php echo $shape_img_src; ?>" alt="<?php echo esc_attr( $pool['name'] ); ?>">
	<?php endif; ?>
	<table class="data-table">
		<tr><td><strong>Shape</strong></td><td><?php echo esc_html( $pool['name'] ); ?> (<?php echo esc_html( $pool['code'] ); ?>)</td>
			<td><strong>Length &times; Width</strong></td><td><?php echo esc_html( $pool['dimensions_length'] ); ?> m &times; <?php echo esc_html( $pool['dimensions_width'] ); ?> m</td></tr>
		<tr><td><strong>Depth (shallow)</strong></td><td><?php echo esc_html( $pool['depth_shallow'] ); ?> m</td>
			<td><strong>Depth (deep)</strong></td><td><?php echo esc_html( $pool['depth_deep'] ); ?> m</td></tr>
		<tr><td><strong>Water volume</strong></td><td><?php echo esc_html( number_format( $pool['water_volume'] ) ); ?> litres</td>
			<td></td><td></td></tr>
	</table>

	<!-- Pricing -->
	<div class="section-heading">Pricing (incl. <?php echo $vat_display; ?> VAT)</div>
	<table class="pricing-table">
		<thead><tr><th>Item</th><th class="text-right">Amount</th></tr></thead>
		<tbody>
			<tr><td>All-Inclusive Installation</td><td class="text-right"><?php echo $install_fmt; ?></td></tr>
			<?php if ( null !== $travel_fmt ) : ?>
				<tr><td>Travel Fee</td><td class="text-right"><?php echo $travel_fmt; ?></td></tr>
			<?php endif; ?>
		</tbody>
		<tfoot>
			<tr class="total-row"><td><strong>Total</strong></td><td class="text-right"><strong><?php echo $total_fmt; ?></strong></td></tr>
		</tfoot>
	</table>

	<!-- Estimate includes -->
	<?php if ( ! empty( $agent['estimate_includes'] ) ) : ?>
		<div class="section-heading">Estimate Includes</div>
		<div class="tc-content"><?php echo wp_kses_post( $agent['estimate_includes'] ); ?></div>
	<?php endif; ?>

	<!-- Agent contact details -->
	<div class="section-heading">Agent Contact Details</div>
	<div class="contact-grid">
		<div class="contact-cell">
			<strong><?php echo esc_html( $agent['company_name'] ); ?></strong><br>
			<?php if ( $agent['contact_name'] ) : ?>
				<?php echo esc_html( $agent['contact_name'] ); ?><br>
			<?php endif; ?>
			<?php if ( $agent['phone'] ) : ?>
				&#9990; <?php echo esc_html( $agent['phone'] ); ?><br>
			<?php endif; ?>
			<?php if ( $agent['email'] ) : ?>
				&#9993; <?php echo esc_html( $agent['email'] ); ?>
			<?php endif; ?>
		</div>
	</div>

	<!-- Payment structure -->
	<?php if ( ! empty( $agent['payment_structure'] ) ) : ?>
		<div class="section-heading">Payment Structure</div>
		<div class="tc-content"><?php echo wp_kses_post( $agent['payment_structure'] ); ?></div>
	<?php endif; ?>

	<!-- Terms and conditions -->
	<?php if ( ! empty( $agent['terms_and_conditions'] ) ) : ?>
		<div class="section-heading">Terms &amp; Conditions</div>
		<div class="tc-content"><?php echo wp_kses_post( $agent['terms_and_conditions'] ); ?></div>
	<?php endif; ?>

	<!-- Footer -->
	<div class="footer">
		<div class="footer-left">
			<strong>Valid until:</strong> <?php echo esc_html( $estimate['expiry_display'] ); ?>
			&nbsp;&nbsp;|&nbsp;&nbsp; Ref: <?php echo esc_html( $estimate['token'] ); ?>
		</div>
		<div class="footer-right">
			Generated by: <a href="https://sellingpools.com">SellingPools.com</a>
		</div>
	</div>

</div>
</body>
</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Renders an HTML string to PDF using DomPDF and saves it to disk.
	 *
	 * @since 1.0.0
	 *
	 * @param string $html     Full HTML document string.
	 * @param string $pdf_path Absolute filesystem path to write the PDF to.
	 * @return bool True on success, false on failure.
	 */
	private static function render_pdf( string $html, string $pdf_path ): bool {
		try {
			$options = new \Dompdf\Options();
			$options->set( 'defaultFont', 'Arial' );
			$options->set( 'isRemoteEnabled', false ); // Images embedded as base64 — no remote needed.
			$options->set( 'isHtml5ParserEnabled', true );
			$options->set( 'isFontSubsettingEnabled', true );

			$dompdf = new \Dompdf\Dompdf( $options );
			$dompdf->loadHtml( $html, 'UTF-8' );
			$dompdf->setPaper( 'A4', 'portrait' );
			$dompdf->render();

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			$bytes = file_put_contents( $pdf_path, $dompdf->output() );

			return false !== $bytes;
		} catch ( \Throwable $e ) {
			error_log( 'BK Estimate PDF: DomPDF exception: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Stores a PDF reference in the wp_bk_estimate_pdfs table.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $estimate_post_id The estimate CPT post ID.
	 * @param int    $agent_post_id    The builder CPT post ID.
	 * @param string $pdf_path         Absolute filesystem path.
	 * @param string $pdf_url          Public URL.
	 * @return void
	 */
	private static function store_pdf_reference( int $estimate_post_id, int $agent_post_id, string $pdf_path, string $pdf_url ): void {
		global $wpdb;

		$table = BK_Database::get_table_name( 'estimate_pdfs' );

		$wpdb->replace(
			$table,
			array(
				'estimate_post_id' => $estimate_post_id,
				'agent_post_id'    => $agent_post_id,
				'pdf_path'         => $pdf_path,
				'pdf_url'          => $pdf_url,
				'generated_at'     => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s' )
		);

		if ( $wpdb->last_error ) {
			error_log( 'BK Estimate PDF — error storing PDF reference: ' . $wpdb->last_error );
		}
	}

	/**
	 * Converts an image URL to an inline base64 data URI for PDF embedding.
	 *
	 * Strategy:
	 *  1. If the URL is within the WordPress uploads directory, attempt a direct
	 *     filesystem read (fast, no HTTP overhead).
	 *  2. If the filesystem read fails for any reason (wrong basedir, migrated
	 *     site, CDN mapping), fall back to fetching via wp_remote_get().
	 *  3. If both attempts fail, return an empty string (image omitted silently).
	 *
	 * @since 1.1.0
	 *
	 * @param string $url Public URL of the image.
	 * @return string Base64 data URI, or empty string on failure.
	 */
	private static function inline_image( string $url ): string {
		if ( empty( $url ) ) {
			return '';
		}

		$upload_dir   = wp_upload_dir();
		$uploads_url  = trailingslashit( $upload_dir['baseurl'] );
		$uploads_base = trailingslashit( $upload_dir['basedir'] );

		// -- Attempt 1: Filesystem read (fast path for uploads images) ----------
		if ( str_starts_with( $url, $uploads_url ) ) {
			$relative_path = substr( $url, strlen( $uploads_url ) );
			$file_path     = $uploads_base . $relative_path;

			if ( file_exists( $file_path ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				$data = file_get_contents( $file_path );

				if ( false !== $data && '' !== $data ) {
					$mime = mime_content_type( $file_path ) ?: 'image/jpeg';
					return 'data:' . $mime . ';base64,' . base64_encode( $data );
				}
			}
		}

		// -- Attempt 2: HTTP fetch via WordPress HTTP API (fallback) ------------
		// Handles migrated sites, CDN URLs, or any URL not in the uploads dir.
		$response = wp_remote_get(
			$url,
			array(
				'timeout'   => 10,
				'sslverify' => false, // Allow self-signed certs on staging servers.
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( 'BK Estimate PDF: Could not fetch image (' . $url . '): ' . $response->get_error_message() );
			return '';
		}

		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			error_log( 'BK Estimate PDF: Non-200 response for image: ' . $url );
			return '';
		}

		$data         = wp_remote_retrieve_body( $response );
		$content_type = wp_remote_retrieve_header( $response, 'content-type' );
		$mime         = $content_type ? strtok( $content_type, ';' ) : 'image/jpeg';

		if ( empty( $data ) ) {
			return '';
		}

		return 'data:' . $mime . ';base64,' . base64_encode( $data );
	}
}
