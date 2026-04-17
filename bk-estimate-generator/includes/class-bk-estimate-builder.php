<?php
/**
 * BK Estimate Builder
 *
 * Assembles all data needed for an estimate into a single structured array
 * that is passed to the HTML renderer, PDF generator, and email composer.
 *
 * @package BK_Estimate_Generator
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BK_Estimate_Builder
 *
 * Gathers estimate, customer, pool shape, agent, and company data from
 * multiple sources (JetEngine custom meta tables, BK Pools tables, and
 * WordPress attachments) and returns a single structured array.
 *
 * @since 1.0.0
 */
class BK_Estimate_Builder {

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Builds the full estimate data array for a given estimate post.
	 *
	 * Data sources:
	 *  - Estimate meta  : get_post_meta() on the estimate post (JetEngine intercepts → wp_estimate_meta).
	 *  - Lead agents    : wp_bk_lead_agents (our own table written by the matcher).
	 *  - Install pricing: wp_bk_agent_pricing (our own table seeded in Phase 1).
	 *  - Agent profile  : get_post_meta() on each builder post (JetEngine intercepts → wp_builders_meta).
	 *  - Pool shape     : get_post_meta() on the pool-shapes post (JetEngine intercepts → wp_pool_shapes_meta).
	 *  - Company        : BK_Settings::get_setting().
	 *
	 * @since 1.0.0
	 *
	 * @param int $estimate_post_id The estimate CPT post ID.
	 * @return array|WP_Error Structured estimate data array, or WP_Error on failure.
	 */
	public static function build( int $estimate_post_id ): array|WP_Error {
		// -- 1. Estimate meta ------------------------------------------------

		$token   = (string) get_post_meta( $estimate_post_id, 'estimate_token', true );
		$url     = (string) get_post_meta( $estimate_post_id, 'estimate_url', true );
		$created = (string) get_post_meta( $estimate_post_id, 'estimate_created', true );
		$expiry  = (string) get_post_meta( $estimate_post_id, 'estimate_expiry', true );

		if ( empty( $token ) ) {
			return new WP_Error( 'missing_token', sprintf( 'Estimate %d has no estimate_token meta value.', $estimate_post_id ) );
		}

		// -- 2. Customer data ------------------------------------------------

		$customer = array(
			'name'     => (string) get_post_meta( $estimate_post_id, 'customer_name', true ),
			'email'    => (string) get_post_meta( $estimate_post_id, 'customer_email', true ),
			'phone'    => (string) get_post_meta( $estimate_post_id, 'customer_phone', true ),
			'suburb'   => (string) get_post_meta( $estimate_post_id, 'suburb_name', true ),
			'area'     => (string) get_post_meta( $estimate_post_id, 'area_name', true ),
			'province' => (string) get_post_meta( $estimate_post_id, 'province', true ),
		);

		if ( empty( $customer['email'] ) ) {
			return new WP_Error( 'missing_customer_email', sprintf( 'Estimate %d has no customer_email meta value.', $estimate_post_id ) );
		}

		// -- 3. Pool shape data ----------------------------------------------

		$pool_shape_id    = (int) get_post_meta( $estimate_post_id, 'pool_shape_id', true );
		$shell_price_excl = (float) get_post_meta( $estimate_post_id, 'shell_price', true );

		if ( ! $pool_shape_id ) {
			return new WP_Error( 'missing_pool_shape', sprintf( 'Estimate %d has no pool_shape_id meta value.', $estimate_post_id ) );
		}

		$pool_shape = self::build_pool_shape( $pool_shape_id, $shell_price_excl );

		// -- 4. Lead agents from wp_bk_lead_agents ---------------------------

		$lead_rows = self::fetch_lead_rows( $estimate_post_id );

		if ( empty( $lead_rows ) ) {
			return new WP_Error( 'no_lead_rows', sprintf( 'No rows found in wp_bk_lead_agents for estimate %d.', $estimate_post_id ) );
		}

		// -- 5. Build per-agent data -----------------------------------------

		$agents = array();

		foreach ( $lead_rows as $lead ) {
			$agent_post_id = (int) $lead['agent_post_id'];
			$agent_data    = self::build_agent( $agent_post_id, $lead, $pool_shape_id, $shell_price_excl );

			if ( is_wp_error( $agent_data ) ) {
				error_log( sprintf(
					'BK Estimate Builder — skipping agent %d for estimate %d: %s',
					$agent_post_id,
					$estimate_post_id,
					$agent_data->get_error_message()
				) );
				continue;
			}

			$agents[] = $agent_data;
		}

		if ( empty( $agents ) ) {
			return new WP_Error( 'no_valid_agents', sprintf( 'No valid agent data could be built for estimate %d.', $estimate_post_id ) );
		}

		// -- 6. Company settings ---------------------------------------------

		$company_logo_id = (int) BK_Settings::get_setting( 'company_logo_id', 0 );
		$company         = array(
			'name'     => (string) BK_Settings::get_setting( 'company_name', 'BK Pools' ),
			'email'    => (string) BK_Settings::get_setting( 'company_email', '' ),
			'phone'    => (string) BK_Settings::get_setting( 'company_phone', '' ),
			'logo_url' => $company_logo_id ? (string) wp_get_attachment_url( $company_logo_id ) : '',
		);

		// -- 7. VAT ----------------------------------------------------------

		$vat_rate = BK_Helpers::get_vat_rate();

		// -- 8. Assemble -----------------------------------------------------

		return array(
			'estimate'    => array(
				'post_id'        => $estimate_post_id,
				'token'          => $token,
				'url'            => $url,
				'created'        => $created,
				'expiry'         => $expiry,
				'expiry_display' => self::format_date_display( $expiry ),
			),
			'customer'    => $customer,
			'pool_shape'  => $pool_shape,
			'agents'      => $agents,
			'company'     => $company,
			'vat_rate'    => $vat_rate,
			'vat_display' => round( $vat_rate * 100 ) . '%',
		);
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Builds the pool shape sub-array.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $pool_shape_id    The pool-shapes CPT post ID.
	 * @param float $shell_price_excl Shell price excluding VAT from the estimate snapshot.
	 * @return array Pool shape data array.
	 */
	private static function build_pool_shape( int $pool_shape_id, float $shell_price_excl ): array {
		$shape_image_id     = (int) get_post_meta( $pool_shape_id, 'shape_image', true );
		$installed_image_id = (int) get_post_meta( $pool_shape_id, 'installed_image', true );

		return array(
			'post_id'           => $pool_shape_id,
			'name'              => (string) get_post_meta( $pool_shape_id, 'shape_name', true ),
			'code'              => (string) get_post_meta( $pool_shape_id, 'shape_code', true ),
			'shell_price_excl'  => $shell_price_excl,
			'shell_price_incl'  => BK_Helpers::calculate_vat( $shell_price_excl ),
			'dimensions_length' => (float) get_post_meta( $pool_shape_id, 'dimensions_length', true ),
			'dimensions_width'  => (float) get_post_meta( $pool_shape_id, 'dimensions_width', true ),
			'depth_shallow'     => (float) get_post_meta( $pool_shape_id, 'dimensions_depth_shallow', true ),
			'depth_deep'        => (float) get_post_meta( $pool_shape_id, 'dimensions_depth_deep', true ),
			'water_volume'      => (float) get_post_meta( $pool_shape_id, 'water_volume', true ),
			'diagram_url'       => $shape_image_id ? (string) wp_get_attachment_url( $shape_image_id ) : '',
			'installed_url'     => $installed_image_id ? (string) wp_get_attachment_url( $installed_image_id ) : '',
			'description'       => (string) get_post_meta( $pool_shape_id, 'shape_description', true ),
		);
	}

	/**
	 * Fetches all lead agent rows for an estimate from wp_bk_lead_agents.
	 *
	 * @since 1.0.0
	 *
	 * @param int $estimate_post_id The estimate CPT post ID.
	 * @return array Array of ARRAY_A rows, ordered by total_estimate_incl ASC.
	 */
	private static function fetch_lead_rows( int $estimate_post_id ): array {
		global $wpdb;

		$table = BK_Database::get_table_name( 'lead_agents' );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE estimate_post_id = %d ORDER BY total_estimate_incl ASC",
				$estimate_post_id
			),
			ARRAY_A
		);

		if ( $wpdb->last_error ) {
			error_log( 'BK Estimate Builder — error fetching lead rows: ' . $wpdb->last_error );
		}

		return $rows ?: array();
	}

	/**
	 * Builds the data array for a single agent.
	 *
	 * Combines lead row data (distance, travel fee, total) with the agent's
	 * all-inclusive pricing from wp_bk_agent_pricing and profile from wp_builders_meta.
	 *
	 * @since 1.1.0
	 *
	 * @param int   $agent_post_id    The builder CPT post ID.
	 * @param array $lead             Row from wp_bk_lead_agents.
	 * @param int   $pool_shape_id    The pool-shapes CPT post ID.
	 * @param float $shell_price_excl Shell price excluding VAT (retained for compatibility, not displayed).
	 * @return array|WP_Error Agent data array, or WP_Error if install pricing is missing.
	 */
	private static function build_agent( int $agent_post_id, array $lead, int $pool_shape_id, float $shell_price_excl ): array|WP_Error {
		global $wpdb;

		// Fetch the agent's all-inclusive price from wp_bk_agent_pricing.
		// installed_price_incl is the agent's single all-in price (VAT inclusive).
		$pricing_table = BK_Database::get_table_name( 'agent_pricing' );
		$pricing       = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT installed_price_excl, installed_price_incl FROM `{$pricing_table}` WHERE agent_post_id = %d AND pool_shape_post_id = %d LIMIT 1",
				$agent_post_id,
				$pool_shape_id
			),
			ARRAY_A
		);

		if ( empty( $pricing ) ) {
			return new WP_Error(
				'missing_pricing',
				sprintf( 'No pricing found for agent %d / shape %d.', $agent_post_id, $pool_shape_id )
			);
		}

		// Agent's all-inclusive price (replaces shell + install breakdown).
		$install_price_incl = (float) $pricing['installed_price_incl'];
		$travel_fee_excl    = (float) $lead['travel_fee'];
		$travel_fee_incl    = BK_Helpers::calculate_vat( $travel_fee_excl );

		// Agent profile via get_post_meta (JetEngine intercepts → wp_builders_meta).
		$company_logo_id  = (int) get_post_meta( $agent_post_id, 'company_logo', true );
		$estimate_post_id = (int) $lead['estimate_post_id'];

		return array(
			'post_id'              => $agent_post_id,
			'company_name'         => (string) get_post_meta( $agent_post_id, 'company_name', true ),
			'company_logo_url'     => $company_logo_id ? (string) wp_get_attachment_url( $company_logo_id ) : '',
			'contact_name'         => (string) get_post_meta( $agent_post_id, 'contact_name', true ),
			'contact_number'       => (string) get_post_meta( $agent_post_id, 'contact_number', true ),
			'email'                => (string) get_post_meta( $agent_post_id, 'email', true ),
			'phone'                => (string) get_post_meta( $agent_post_id, 'phone', true ),
			'distance_km'          => (float) $lead['distance_km'],
			'install_price_incl'   => $install_price_incl,
			'travel_fee_excl'      => $travel_fee_excl,
			'travel_fee_incl'      => $travel_fee_incl,
			'total_estimate_incl'  => (float) $lead['total_estimate_incl'],
			'estimate_includes'    => (string) get_post_meta( $agent_post_id, 'estimate_includes', true ),
			'terms_and_conditions' => (string) get_post_meta( $agent_post_id, 'terms_and_conditions', true ),
			'payment_structure'    => (string) get_post_meta( $agent_post_id, 'payment_structure', true ),
			'pdf_url'              => self::fetch_agent_pdf_url( $estimate_post_id, $agent_post_id ),
		);
	}

	/**
	 * Fetches the PDF download URL for a specific agent/estimate pair.
	 *
	 * @since 1.1.0
	 *
	 * @param int $estimate_post_id The estimate CPT post ID.
	 * @param int $agent_post_id    The builder CPT post ID.
	 * @return string PDF URL, or empty string if not yet generated.
	 */
	private static function fetch_agent_pdf_url( int $estimate_post_id, int $agent_post_id ): string {
		global $wpdb;

		$table = BK_Database::get_table_name( 'estimate_pdfs' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$url = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT pdf_url FROM `{$table}` WHERE estimate_post_id = %d AND agent_post_id = %d LIMIT 1",
				$estimate_post_id,
				$agent_post_id
			)
		);

		return $url ? (string) $url : '';
	}

	/**
	 * Formats a MySQL datetime string for human-readable display.
	 *
	 * @since 1.0.0
	 *
	 * @param string $datetime MySQL datetime string (e.g. '2026-05-15 00:00:00').
	 * @return string Formatted date, e.g. '15 May 2026'. Empty string on failure.
	 */
	private static function format_date_display( string $datetime ): string {
		if ( empty( $datetime ) ) {
			return '';
		}

		$timestamp = strtotime( $datetime );

		if ( false === $timestamp ) {
			return '';
		}

		return date_i18n( 'j F Y', $timestamp );
	}
}
