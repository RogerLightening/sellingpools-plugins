<?php
/**
 * BK Matcher
 *
 * Core agent matching logic for the BK Pools lead generation platform.
 * Finds the 3 nearest active agents for a new estimate, calculates all
 * pricing (including travel fees and VAT), and writes the results to the
 * wp_bk_lead_agents table and estimate post meta.
 *
 * @package BK_Agent_Matcher
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BK_Matcher
 *
 * All methods are static. Entry point is BK_Matcher::match_agents_to_lead().
 *
 * Dependencies (from bk-pools-core):
 *  - BK_Database::get_table_name()
 *  - BK_Haversine::distance()
 *  - BK_Helpers::calculate_vat()
 *  - BK_Helpers::calculate_travel_fee()
 *  - BK_Helpers::generate_estimate_token()
 *  - BK_Settings::get_setting()
 *
 * @since 1.0.0
 */
class BK_Matcher {

	/**
	 * Number of agents to assign per estimate.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const AGENTS_PER_LEAD = 3;

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Matches the nearest active agents to a new estimate and writes all records.
	 *
	 * Steps:
	 *  1. Guards against double-processing via the _bk_agents_matched meta flag.
	 *  2. Resolves the suburb's lat/lng from the JetEngine CCT table.
	 *  3. Fetches all active agents in one SQL query against wp_builders_meta.
	 *  4. Calculates Haversine distances; filters by max_travel_radius_km with fallback.
	 *  5. Prices each of the top 3 agents (shell + install + travel, all incl. VAT).
	 *  6. Inserts rows into wp_bk_lead_agents.
	 *  7. Writes snapshot meta to the estimate post.
	 *  8. Fires bk_pools_agents_matched for the Phase 3 estimate generator.
	 *
	 * @since 1.0.0
	 *
	 * @param int $estimate_post_id WordPress post ID of the estimate CPT post.
	 * @return array<int, array<string, mixed>>|WP_Error
	 *         Array of matched agent data on success, WP_Error on failure.
	 */
	public static function match_agents_to_lead( int $estimate_post_id ): array|WP_Error {
		// -- 1. Guard against double-processing --------------------------------

		if ( get_post_meta( $estimate_post_id, '_bk_agents_matched', true ) ) {
			return new WP_Error(
				'already_matched',
				sprintf(
					/* translators: %d: estimate post ID */
					__( 'Estimate %d has already been processed.', 'bk-agent-matcher' ),
					$estimate_post_id
				)
			);
		}

		// -- 2. Resolve suburb coordinates -------------------------------------

		$suburb_id = (int) get_post_meta( $estimate_post_id, 'suburb_id', true );

		if ( ! $suburb_id ) {
			return new WP_Error(
				'missing_suburb_id',
				sprintf(
					/* translators: %d: estimate post ID */
					__( 'Estimate %d has no suburb_id meta value.', 'bk-agent-matcher' ),
					$estimate_post_id
				)
			);
		}

		$suburb = self::get_suburb_by_id( $suburb_id );

		if ( is_wp_error( $suburb ) ) {
			return $suburb;
		}

		$suburb_lat = $suburb['latitude'];
		$suburb_lng = $suburb['longitude'];

		// Snapshot suburb coordinates onto the estimate post.
		update_post_meta( $estimate_post_id, 'customer_latitude', $suburb_lat );
		update_post_meta( $estimate_post_id, 'customer_longitude', $suburb_lng );

		// -- 3. Fetch all active agents ----------------------------------------

		$agents = self::fetch_active_agents();

		if ( empty( $agents ) ) {
			return new WP_Error(
				'no_active_agents',
				__( 'No active agents found in the system.', 'bk-agent-matcher' )
			);
		}

		// -- 4. Calculate distances and select top agents ----------------------

		$agents_with_distance = self::calculate_distances( $suburb_lat, $suburb_lng, $agents );

		if ( empty( $agents_with_distance ) ) {
			return new WP_Error(
				'no_agents_with_coordinates',
				__( 'No active agents have valid coordinates.', 'bk-agent-matcher' )
			);
		}

		// Sort by distance ascending.
		usort( $agents_with_distance, static fn( $a, $b ) => $a['distance_km'] <=> $b['distance_km'] );

		// Filter to agents within their own travel radius.
		// max_travel_radius_km is a text column — cast to float before comparison.
		$within_radius = array_filter(
			$agents_with_distance,
			static fn( $a ) => ! (float) $a['max_travel_radius_km']
				|| $a['distance_km'] <= (float) $a['max_travel_radius_km']
		);

		// Fall back to all agents if fewer than AGENTS_PER_LEAD are within radius.
		$pool = count( $within_radius ) >= self::AGENTS_PER_LEAD
			? array_values( $within_radius )
			: $agents_with_distance;

		$selected = array_slice( $pool, 0, self::AGENTS_PER_LEAD );

		// -- 5. Calculate pricing for each selected agent ----------------------

		$pool_shape_id    = (int) get_post_meta( $estimate_post_id, 'pool_shape_id', true );
		$shell_price_excl = $pool_shape_id
			? (float) get_post_meta( $pool_shape_id, 'shell_price', true )
			: 0.00;
		$pool_shape_name  = $pool_shape_id ? get_the_title( $pool_shape_id ) : '';

		$matched_agents = array();

		foreach ( $selected as $agent ) {
			$pricing = self::calculate_agent_pricing(
				$agent,
				$shell_price_excl,
				$pool_shape_id
			);

			if ( is_wp_error( $pricing ) ) {
				error_log( sprintf(
					'BK Matcher — skipping agent %d for estimate %d: %s',
					(int) $agent['object_ID'],
					$estimate_post_id,
					$pricing->get_error_message()
				) );
				continue;
			}

			$matched_agents[] = array_merge( $agent, $pricing );
		}

		if ( empty( $matched_agents ) ) {
			return new WP_Error(
				'no_agents_priced',
				__( 'No agents could be priced for this estimate. Check agent pricing table.', 'bk-agent-matcher' )
			);
		}

		// -- 6. Insert rows into wp_bk_lead_agents ----------------------------

		$insert_result = self::insert_lead_agent_rows( $estimate_post_id, $matched_agents );

		if ( is_wp_error( $insert_result ) ) {
			return $insert_result;
		}

		// -- 7. Write snapshot meta to the estimate post -----------------------

		// Use the token JFB already saved from the hidden form field, or generate
		// a fresh one if none is present (e.g. manual admin save via the fallback hook).
		$existing_token = (string) get_post_meta( $estimate_post_id, 'estimate_token', true );
		if ( ! empty( $existing_token ) ) {
			$token = $existing_token;
		} else {
			$token = BK_Helpers::generate_estimate_token();
			update_post_meta( $estimate_post_id, 'estimate_token', $token );
		}

		$estimate_url  = site_url( '/estimate/view/' . $token );
		$validity_days = (int) BK_Settings::get_setting( 'estimate_validity_days', 30 );
		$expiry_date   = gmdate( 'Y-m-d H:i:s', strtotime( "+{$validity_days} days" ) );
		$now           = current_time( 'mysql' );

		update_post_meta( $estimate_post_id, 'estimate_url', $estimate_url );
		update_post_meta( $estimate_post_id, 'estimate_expiry', $expiry_date );
		update_post_meta( $estimate_post_id, 'estimate_created', $now );
		update_post_meta( $estimate_post_id, 'shell_price', $shell_price_excl );
		update_post_meta( $estimate_post_id, 'pool_shape_name', $pool_shape_name );
		update_post_meta( $estimate_post_id, 'assigned_agents', wp_json_encode( $matched_agents ) );

		// Set the processing flag last — any earlier failure leaves this unset,
		// allowing a retry.
		update_post_meta( $estimate_post_id, '_bk_agents_matched', '1' );

		// -- 8. Fire the handoff action for Phase 3 (estimate generator) ------

		/**
		 * Fires after agents have been matched and all records written.
		 *
		 * Phase 3 (bk-estimate-generator) hooks here to build and send PDF estimates.
		 *
		 * @since 1.0.0
		 *
		 * @param int                              $estimate_post_id The estimate CPT post ID.
		 * @param array<int, array<string, mixed>> $matched_agents   The matched and priced agents.
		 */
		do_action( 'bk_pools_agents_matched', $estimate_post_id, $matched_agents );

		return $matched_agents;
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Looks up a suburb record from the JetEngine CCT table by its _ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int $suburb_id The CCT record ID (_ID column).
	 * @return array{latitude: float, longitude: float}|WP_Error
	 *         Suburb coordinates, or WP_Error if not found.
	 */
	private static function get_suburb_by_id( int $suburb_id ): array|WP_Error {
		global $wpdb;

		$table = $wpdb->prefix . 'jet_cct_suburbs';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name safely built from $wpdb->prefix.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT latitude, longitude FROM `{$table}` WHERE _ID = %d LIMIT 1",
				$suburb_id
			),
			ARRAY_A
		);

		if ( $wpdb->last_error ) {
			error_log( 'BK Matcher — suburb lookup error: ' . $wpdb->last_error );
		}

		if ( empty( $row ) || null === $row['latitude'] || null === $row['longitude'] ) {
			return new WP_Error(
				'suburb_not_found',
				sprintf(
					/* translators: %d: suburb CCT record ID */
					__( 'Suburb ID %d was not found or has no coordinates.', 'bk-agent-matcher' ),
					$suburb_id
				)
			);
		}

		return array(
			'latitude'  => (float) $row['latitude'],
			'longitude' => (float) $row['longitude'],
		);
	}

	/**
	 * Fetches all published, active builder agents in a single SQL query.
	 *
	 * Queries wp_builders_meta (JetEngine custom meta table) joined with wp_posts.
	 * This is more efficient than looping get_post_meta() for each agent.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>> Raw agent rows, or empty array on failure.
	 */
	private static function fetch_active_agents(): array {
		global $wpdb;

		$builders_meta = $wpdb->prefix . 'builders_meta';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names safely built from $wpdb->prefix.
		// Note: JetEngine Custom Meta Tables use object_ID (not post_id) as the FK to wp_posts.
		// is_active is stored as the string 'true'/'false' by JetEngine.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					bm.object_ID,
					bm.linked_user_id,
					bm.company_name,
					bm.physical_address_lat,
					bm.physical_address_lng,
					bm.max_travel_radius_km,
					bm.travel_fee_enabled,
					bm.travel_fee_min_distance_km,
					bm.travel_fee_type,
					bm.travel_fee_rate
				FROM `{$builders_meta}` bm
				INNER JOIN `{$wpdb->posts}` p ON p.ID = bm.object_ID
				WHERE p.post_type   = %s
				  AND p.post_status = %s
				  AND bm.is_active  = %s",
				'builders',
				'publish',
				'true'
			),
			ARRAY_A
		);

		if ( $wpdb->last_error ) {
			error_log( 'BK Matcher — error fetching active agents: ' . $wpdb->last_error );
			return array();
		}

		return $results ?: array();
	}

	/**
	 * Appends Haversine distance to each agent row and removes agents with
	 * missing or invalid coordinates.
	 *
	 * Uses BK_Haversine::distance( lat1, lon1, lat2, lon2 ) from bk-pools-core.
	 *
	 * @since 1.0.0
	 *
	 * @param float                            $suburb_lat Suburb latitude.
	 * @param float                            $suburb_lng Suburb longitude.
	 * @param array<int, array<string, mixed>> $agents     Raw agent rows from fetch_active_agents().
	 * @return array<int, array<string, mixed>> Agent rows with 'distance_km' key added.
	 */
	private static function calculate_distances( float $suburb_lat, float $suburb_lng, array $agents ): array {
		$results = array();

		foreach ( $agents as $agent ) {
			$agent_lat = (float) ( $agent['physical_address_lat'] ?? 0 );
			$agent_lng = (float) ( $agent['physical_address_lng'] ?? 0 );

			// Skip agents with missing coordinates.
			if ( ! $agent_lat || ! $agent_lng ) {
				continue;
			}

			// BK_Haversine::distance( lat1, lon1, lat2, lon2 ) — returns 0.0 for invalid coords.
			$distance_km = BK_Haversine::distance( $suburb_lat, $suburb_lng, $agent_lat, $agent_lng );

			// Treat a 0.0 result where coords clearly differ as invalid.
			if ( 0.0 === $distance_km
				&& ( abs( $suburb_lat - $agent_lat ) > 0.0001 || abs( $suburb_lng - $agent_lng ) > 0.0001 )
			) {
				continue;
			}

			$results[] = array_merge(
				$agent,
				array( 'distance_km' => $distance_km )
			);
		}

		return $results;
	}

	/**
	 * Calculates all pricing for a single agent/estimate/shape combination.
	 *
	 * Looks up the agent's installed price from wp_bk_agent_pricing for the
	 * given pool shape, then computes travel fee and all VAT-inclusive totals.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $agent            Agent row including distance_km.
	 * @param float                $shell_price_excl Pool shell price excl. VAT.
	 * @param int                  $pool_shape_id    Pool shape post ID.
	 * @return array<string, mixed>|WP_Error Pricing data array, or WP_Error on failure.
	 */
	private static function calculate_agent_pricing(
		array $agent,
		float $shell_price_excl,
		int $pool_shape_id
	): array|WP_Error {
		global $wpdb;

		// object_ID is the JetEngine custom meta table FK to wp_posts.ID.
		$agent_post_id = (int) $agent['object_ID'];
		$pricing_table = BK_Database::get_table_name( 'agent_pricing' );

		// Look up this agent's all-inclusive price for the requested pool shape.
		// installed_price_incl is the agent's single all-in price (VAT inclusive).
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name safely built.
		$pricing_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT installed_price_excl, installed_price_incl
				FROM `{$pricing_table}`
				WHERE agent_post_id      = %d
				  AND pool_shape_post_id = %d
				  AND is_available       = 1
				LIMIT 1",
				$agent_post_id,
				$pool_shape_id
			),
			ARRAY_A
		);

		if ( $wpdb->last_error ) {
			error_log( 'BK Matcher — pricing lookup error for agent ' . $agent_post_id . ': ' . $wpdb->last_error );
		}

		// The agent's all-inclusive price (incl. VAT) — replaces the old shell+install split.
		$install_price_incl = $pricing_row ? (float) $pricing_row['installed_price_incl'] : 0.00;
		$install_price_excl = $pricing_row ? (float) $pricing_row['installed_price_excl'] : 0.00;

		// Build travel fee settings from agent meta columns.
		$travel_settings = array(
			'travel_fee_enabled'         => 'true' === (string) ( $agent['travel_fee_enabled'] ?? '' ),
			'travel_fee_min_distance_km' => (float) ( $agent['travel_fee_min_distance_km'] ?? 0 ),
			'travel_fee_type'            => (string) ( $agent['travel_fee_type'] ?? 'fixed_per_km' ),
			'travel_fee_rate'            => (float) ( $agent['travel_fee_rate'] ?? 0 ),
		);

		// Travel fee is calculated on top of the agent's all-in price.
		// For percentage-based fees, the excl. price is used as the base.
		$travel_fee_excl     = BK_Helpers::calculate_travel_fee(
			(float) $agent['distance_km'],
			$travel_settings,
			$install_price_excl
		);
		$travel_fee_incl     = BK_Helpers::calculate_vat( $travel_fee_excl );
		$total_estimate_incl = round( $install_price_incl + $travel_fee_incl, 2 );

		return array(
			'install_price_excl'  => $install_price_excl,
			'install_price_incl'  => $install_price_incl,
			'travel_fee_excl'     => $travel_fee_excl,
			'travel_fee_incl'     => $travel_fee_incl,
			'total_estimate_incl' => $total_estimate_incl,
		);
	}

	/**
	 * Inserts one row per matched agent into wp_bk_lead_agents.
	 *
	 * Uses $wpdb->insert() (parameterised) — never raw SQL concatenation.
	 * Logs insert errors but only returns WP_Error if every single insert fails.
	 *
	 * @since 1.0.0
	 *
	 * @param int                              $estimate_post_id The estimate CPT post ID.
	 * @param array<int, array<string, mixed>> $matched_agents   Agents with distance and pricing.
	 * @return true|WP_Error True on success, WP_Error if all inserts fail.
	 */
	private static function insert_lead_agent_rows( int $estimate_post_id, array $matched_agents ): bool|WP_Error {
		global $wpdb;

		$table        = BK_Database::get_table_name( 'lead_agents' );
		$now          = current_time( 'mysql' );
		$error_count  = 0;

		foreach ( $matched_agents as $agent ) {
			$result = $wpdb->insert(
				$table,
				array(
					'estimate_post_id'    => $estimate_post_id,
					'agent_post_id'       => (int) $agent['object_ID'],
					'agent_user_id'       => (int) $agent['linked_user_id'],
					'distance_km'         => (float) $agent['distance_km'],
					'travel_fee'          => (float) $agent['travel_fee_excl'],
					'total_estimate_incl' => (float) $agent['total_estimate_incl'],
					'lead_status'         => 'new',
					'assigned_at'         => $now,
					'created_at'          => $now,
				),
				array( '%d', '%d', '%d', '%f', '%f', '%f', '%s', '%s', '%s' )
			);

			if ( false === $result ) {
				++$error_count;
				error_log( sprintf(
					'BK Matcher — insert error for estimate %d, agent %d: %s',
					$estimate_post_id,
					(int) $agent['object_ID'],
					$wpdb->last_error
				) );
			}
		}

		if ( $error_count === count( $matched_agents ) ) {
			return new WP_Error(
				'insert_failed',
				__( 'All lead_agent row inserts failed. See PHP error log for details.', 'bk-agent-matcher' )
			);
		}

		return true;
	}
}
