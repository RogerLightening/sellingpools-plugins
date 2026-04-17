<?php
/**
 * BK Agent Leads
 *
 * Fetches and paginates the lead list for an agent.
 *
 * @package BK_Agent_Panel
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BK_Agent_Leads
 *
 * @since 1.0.0
 */
class BK_Agent_Leads {

	/**
	 * Valid ENUM values for lead_status.
	 *
	 * @since 1.0.0
	 * @var string[]
	 */
	const VALID_STATUSES = array(
		'new', 'contacted', 'no_answer', 'wrong_number',
		'site_visit', 'quoted', 'won', 'lost', 'stale',
	);

	/**
	 * Valid orderby columns mapped to their SQL expression.
	 *
	 * @since 1.0.0
	 * @var array<string, string>
	 */
	const ORDER_COLUMNS = array(
		'date'     => 'la.assigned_at',
		'customer' => 'em.customer_name',
		'status'   => 'la.lead_status',
		'distance' => 'la.distance_km',
		'rating'   => 'la.lead_quality_rating',
		'total'    => 'la.total_estimate_incl',
	);

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Fetches paginated, filtered leads for a given agent.
	 *
	 * CRITICAL: the WHERE clause always includes `agent_post_id = %d` so
	 * agents can only ever see their own leads.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $agent_post_id Builder CPT post ID.
	 * @param array $args {
	 *     Optional filter/sort parameters.
	 *     @type string|null $status   Filter by lead_status.
	 *     @type int         $page     Page number. Default 1.
	 *     @type int         $per_page Rows per page. Default 20.
	 *     @type string      $orderby  Column name. Default 'date'.
	 *     @type string      $order    'ASC' or 'DESC'. Default 'DESC'.
	 *     @type string|null $search   Customer name search.
	 * }
	 * @return array{leads: array, total: int, page: int, per_page: int, pages: int}
	 */
	public static function get_leads( int $agent_post_id, array $args = array() ): array {
		global $wpdb;

		$lead_agents_table = BK_Database::get_table_name( 'lead_agents' );
		$estimate_meta     = $wpdb->prefix . 'estimate_meta';
		$estimate_pdfs     = BK_Database::get_table_name( 'estimate_pdfs' );

		// Sanitise args.
		$status   = isset( $args['status'] ) && in_array( $args['status'], self::VALID_STATUSES, true )
			? $args['status'] : null;
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = min( 100, max( 1, (int) ( $args['per_page'] ?? 20 ) ) );
		$orderby  = array_key_exists( $args['orderby'] ?? '', self::ORDER_COLUMNS )
			? self::ORDER_COLUMNS[ $args['orderby'] ] : 'la.assigned_at';
		$order    = strtoupper( $args['order'] ?? 'DESC' ) === 'ASC' ? 'ASC' : 'DESC';
		$search   = ! empty( $args['search'] ) ? sanitize_text_field( $args['search'] ) : null;
		$offset   = ( $page - 1 ) * $per_page;

		// Build WHERE conditions.
		$where_parts = array( 'la.agent_post_id = %d' );
		$where_vals  = array( $agent_post_id );

		if ( $status ) {
			$where_parts[] = 'la.lead_status = %s';
			$where_vals[]  = $status;
		}

		if ( $search ) {
			$where_parts[] = 'em.customer_name LIKE %s';
			$where_vals[]  = '%' . $wpdb->esc_like( $search ) . '%';
		}

		$where_sql = implode( ' AND ', $where_parts );

		// Count query.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				FROM `{$lead_agents_table}` la
				INNER JOIN `{$estimate_meta}` em ON em.object_ID = la.estimate_post_id
				WHERE {$where_sql}",
				...$where_vals
			)
		);

		// Data query.
		$data_vals   = array_merge( $where_vals, array( $per_page, $offset ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					la.id AS lead_agent_id,
					la.estimate_post_id,
					la.distance_km,
					la.travel_fee,
					la.total_estimate_incl,
					la.lead_status,
					la.lead_quality_rating,
					la.lead_notes,
					la.assigned_at,
					la.status_updated_at,
					la.first_response_at,
					TIMESTAMPDIFF(HOUR, la.assigned_at, NOW()) AS hours_since_assigned,
					em.customer_name,
					em.customer_email,
					em.customer_phone,
					em.pool_shape_name,
					em.suburb_name,
					em.area_name,
					em.province,
					epdf.pdf_url
				FROM `{$lead_agents_table}` la
				INNER JOIN `{$estimate_meta}` em ON em.object_ID = la.estimate_post_id
				LEFT JOIN `{$estimate_pdfs}` epdf
					ON epdf.estimate_post_id = la.estimate_post_id
					AND epdf.agent_post_id   = la.agent_post_id
				WHERE {$where_sql}
				ORDER BY {$orderby} {$order}
				LIMIT %d OFFSET %d",
				...$data_vals
			),
			ARRAY_A
		);

		$leads = array();

		foreach ( $rows as $row ) {
			$leads[] = array(
				'lead_agent_id'        => (int) $row['lead_agent_id'],
				'estimate_post_id'     => (int) $row['estimate_post_id'],
				'customer_name'        => (string) $row['customer_name'],
				'customer_email'       => (string) $row['customer_email'],
				'customer_phone'       => (string) $row['customer_phone'],
				'pool_shape_name'      => (string) $row['pool_shape_name'],
				'suburb_name'          => (string) $row['suburb_name'],
				'area_name'            => (string) $row['area_name'],
				'province'             => (string) $row['province'],
				'distance_km'          => (float) $row['distance_km'],
				'travel_fee'           => (float) $row['travel_fee'],
				'total_estimate_incl'  => (float) $row['total_estimate_incl'],
				'lead_status'          => (string) $row['lead_status'],
				'lead_quality_rating'  => null !== $row['lead_quality_rating'] ? (int) $row['lead_quality_rating'] : null,
				'lead_notes'           => (string) ( $row['lead_notes'] ?? '' ),
				'assigned_at'          => (string) $row['assigned_at'],
				'status_updated_at'    => (string) ( $row['status_updated_at'] ?? '' ),
				'first_response_at'    => (string) ( $row['first_response_at'] ?? '' ),
				'pdf_url'              => (string) ( $row['pdf_url'] ?? '' ),
				'hours_since_assigned' => (int) $row['hours_since_assigned'],
			);
		}

		return array(
			'leads'    => $leads,
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
			'pages'    => $total > 0 ? (int) ceil( $total / $per_page ) : 1,
		);
	}

	/**
	 * Returns lead counts grouped by status for the filter tabs.
	 *
	 * @since 1.0.0
	 *
	 * @param int $agent_post_id Builder CPT post ID.
	 * @return array<string, int> Status slug → count.
	 */
	public static function get_status_counts( int $agent_post_id ): array {
		global $wpdb;

		$table = BK_Database::get_table_name( 'lead_agents' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT lead_status, COUNT(*) AS cnt
				FROM `{$table}`
				WHERE agent_post_id = %d
				GROUP BY lead_status",
				$agent_post_id
			),
			ARRAY_A
		);

		$counts = array_fill_keys( self::VALID_STATUSES, 0 );
		$total  = 0;

		foreach ( $rows as $row ) {
			$status = $row['lead_status'];
			$count  = (int) $row['cnt'];

			if ( isset( $counts[ $status ] ) ) {
				$counts[ $status ] = $count;
			}

			$total += $count;
		}

		return array_merge( array( 'all' => $total ), $counts );
	}
}
