<?php
/**
 * BK Haversine
 *
 * Great-circle distance calculations and nearest-agent lookup for the BK Pools platform.
 *
 * @package BK_Pools_Core
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BK_Haversine
 *
 * Stateless library for calculating distances between geographic coordinates
 * using the Haversine formula, and for finding the nearest active agents to
 * a given suburb.
 *
 * All methods are static — instantiation is not required or intended.
 *
 * @since 1.0.0
 */
class BK_Haversine {

	/**
	 * Mean radius of the Earth in kilometres.
	 *
	 * @since 1.0.0
	 * @var float
	 */
	const EARTH_RADIUS_KM = 6371.0;

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Calculates the great-circle distance between two points on the Earth's surface.
	 *
	 * Uses the Haversine formula. Returns 0.0 if any coordinate is outside
	 * the valid WGS-84 bounds (lat: ±90°, lng: ±180°).
	 *
	 * @since 1.0.0
	 *
	 * @param float $lat1 Latitude of point 1 in decimal degrees (−90 to 90).
	 * @param float $lon1 Longitude of point 1 in decimal degrees (−180 to 180).
	 * @param float $lat2 Latitude of point 2 in decimal degrees (−90 to 90).
	 * @param float $lon2 Longitude of point 2 in decimal degrees (−180 to 180).
	 * @return float Distance in kilometres, rounded to 2 decimal places, or 0.0 on invalid input.
	 */
	public static function distance( float $lat1, float $lon1, float $lat2, float $lon2 ): float {
		if ( ! self::are_coordinates_valid( $lat1, $lon1 ) || ! self::are_coordinates_valid( $lat2, $lon2 ) ) {
			return 0.0;
		}

		// Convert degrees to radians.
		$lat1_rad = deg2rad( $lat1 );
		$lat2_rad = deg2rad( $lat2 );
		$delta_lat = deg2rad( $lat2 - $lat1 );
		$delta_lon = deg2rad( $lon2 - $lon1 );

		// Haversine formula.
		$a = sin( $delta_lat / 2 ) ** 2
			+ cos( $lat1_rad ) * cos( $lat2_rad ) * sin( $delta_lon / 2 ) ** 2;

		$c = 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );

		return round( self::EARTH_RADIUS_KM * $c, 2 );
	}

	/**
	 * Finds the nearest active agents to a given suburb.
	 *
	 * Looks up the suburb's lat/lng from the JetEngine CCT table
	 * ({prefix}jet_cct_suburbs), queries all active builders, calculates
	 * distances using the Haversine formula, and returns the $limit closest agents.
	 *
	 * If fewer than $limit agents fall within their own max_travel_radius_km,
	 * the method falls back to all active agents and returns the closest $limit.
	 *
	 * @since 1.0.0
	 *
	 * @param int $suburb_id  The CCT suburb record ID (from wp_jet_cct_suburbs).
	 * @param int $limit      Maximum number of agents to return. Default 3.
	 * @return array<int, array{
	 *     agent_post_id: int,
	 *     agent_user_id: int,
	 *     distance_km: float,
	 *     company_name: string
	 * }> Array of agent data sorted by distance ascending. Empty on failure.
	 */
	public static function find_nearest_agents( int $suburb_id, int $limit = 3 ): array {
		global $wpdb;

		// -- 1. Look up suburb lat/lng from JetEngine CCT table -----------------

		$suburb_table = $wpdb->prefix . 'jet_cct_suburbs';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is safely constructed from $wpdb->prefix.
		// Note: the JetEngine CCT suburbs table stores coordinates as 'latitude' and 'longitude'.
		$suburb = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT latitude, longitude FROM `{$suburb_table}` WHERE _ID = %d LIMIT 1",
				$suburb_id
			)
		);

		if ( ! $suburb || null === $suburb->latitude || null === $suburb->longitude ) {
			error_log( sprintf( 'BK Haversine — suburb ID %d not found or missing coordinates.', $suburb_id ) );
			return array();
		}

		$suburb_lat = (float) $suburb->latitude;
		$suburb_lng = (float) $suburb->longitude;

		if ( ! self::are_coordinates_valid( $suburb_lat, $suburb_lng ) ) {
			error_log( sprintf( 'BK Haversine — suburb ID %d has invalid coordinates (%f, %f).', $suburb_id, $suburb_lat, $suburb_lng ) );
			return array();
		}

		// -- 2. Fetch all active agents (builders CPT) --------------------------

		$agents = self::fetch_active_agents();

		if ( empty( $agents ) ) {
			return array();
		}

		// -- 3. Calculate distance from suburb to each agent --------------------

		$agents_with_distance = array();

		foreach ( $agents as $agent ) {
			$agent_lat = (float) ( $agent['lat'] ?? 0 );
			$agent_lng = (float) ( $agent['lng'] ?? 0 );

			if ( ! self::are_coordinates_valid( $agent_lat, $agent_lng ) ) {
				continue;
			}

			$distance_km = self::distance( $suburb_lat, $suburb_lng, $agent_lat, $agent_lng );

			$agents_with_distance[] = array(
				'agent_post_id' => (int) $agent['post_id'],
				'agent_user_id' => (int) $agent['user_id'],
				'distance_km'   => $distance_km,
				'company_name'  => (string) $agent['company_name'],
				'max_radius'    => isset( $agent['max_travel_radius_km'] ) ? (float) $agent['max_travel_radius_km'] : null,
			);
		}

		// -- 4. Sort by distance ascending -------------------------------------

		usort( $agents_with_distance, static fn( $a, $b ) => $a['distance_km'] <=> $b['distance_km'] );

		// -- 5. Filter to agents within their own travel radius ----------------

		$within_radius = array_filter(
			$agents_with_distance,
			static fn( $a ) => null === $a['max_radius'] || $a['distance_km'] <= $a['max_radius']
		);

		// Fall back to all agents if insufficient results within radius.
		$pool = count( $within_radius ) >= $limit ? $within_radius : $agents_with_distance;

		// -- 6. Slice, strip internal keys, and return -------------------------

		$result = array_slice( array_values( $pool ), 0, $limit );

		return array_map(
			static fn( $a ) => array(
				'agent_post_id' => $a['agent_post_id'],
				'agent_user_id' => $a['agent_user_id'],
				'distance_km'   => $a['distance_km'],
				'company_name'  => $a['company_name'],
			),
			$result
		);
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Validates that a lat/lng pair is within WGS-84 bounds.
	 *
	 * @since 1.0.0
	 *
	 * @param float $lat Latitude (must be between −90 and 90).
	 * @param float $lng Longitude (must be between −180 and 180).
	 * @return bool True if both values are within valid bounds.
	 */
	private static function are_coordinates_valid( float $lat, float $lng ): bool {
		return $lat >= -90.0 && $lat <= 90.0 && $lng >= -180.0 && $lng <= 180.0;
	}

	/**
	 * Fetches all active builder agents from the WordPress database.
	 *
	 * Queries the builders CPT (post_type = 'builders') for published posts
	 * where the is_active post meta is truthy, then retrieves the geographic
	 * and profile meta for each agent.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>> Array of agent data arrays.
	 */
	private static function fetch_active_agents(): array {
		global $wpdb;

		// One query: join posts → postmeta for all required meta keys at once.
		// Using a pivot approach via MAX(CASE WHEN …) for efficiency.
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- query uses only hardcoded values.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					p.ID AS post_id,
					MAX(CASE WHEN pm.meta_key = 'agent_user_id'          THEN pm.meta_value END) AS user_id,
					MAX(CASE WHEN pm.meta_key = 'company_name'           THEN pm.meta_value END) AS company_name,
					MAX(CASE WHEN pm.meta_key = 'physical_address_lat'   THEN pm.meta_value END) AS lat,
					MAX(CASE WHEN pm.meta_key = 'physical_address_lng'   THEN pm.meta_value END) AS lng,
					MAX(CASE WHEN pm.meta_key = 'max_travel_radius_km'   THEN pm.meta_value END) AS max_travel_radius_km
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
				INNER JOIN {$wpdb->postmeta} active_pm
					ON active_pm.post_id = p.ID
					AND active_pm.meta_key = %s
					AND active_pm.meta_value = %s
				WHERE p.post_type   = %s
				  AND p.post_status = %s
				GROUP BY p.ID",
				'is_active',
				'1',
				'builders',
				'publish'
			),
			ARRAY_A
		);
		// phpcs:enable

		if ( $wpdb->last_error ) {
			error_log( 'BK Haversine — error fetching active agents: ' . $wpdb->last_error );
			return array();
		}

		return $results ?: array();
	}
}
