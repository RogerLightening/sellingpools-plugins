<?php
/**
 * BK Performance Metrics
 *
 * Calculation engine for all agent and platform-level performance data.
 * All methods are static and query the BK Pools database tables directly.
 *
 * @package BK_Performance_Tracker
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BK_Performance_Metrics
 *
 * @since 1.0.0
 */
class BK_Performance_Metrics {

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Returns performance metrics for every agent, sorted by conversion rate desc.
	 *
	 * A single efficient query with conditional aggregation is used so the whole
	 * result set is fetched in one round-trip, then enriched with contact_name
	 * via post meta (one call per agent, cached by WP's object cache).
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>> One entry per agent.
	 */
	public static function get_all_agent_metrics(): array {
		global $wpdb;

		$lead_agents_table = BK_Database::get_table_name( 'lead_agents' );
		$builders_meta     = $wpdb->prefix . 'builders_meta';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			"SELECT
				la.agent_post_id,
				bm.company_name,
				COUNT(*)                                                           AS total_leads,
				SUM( CASE WHEN MONTH(la.assigned_at) = MONTH(CURDATE())
				               AND YEAR(la.assigned_at)  = YEAR(CURDATE())
				          THEN 1 ELSE 0 END )                                      AS leads_this_month,
				SUM( CASE WHEN la.lead_status = 'won'  THEN 1 ELSE 0 END )         AS won_leads,
				SUM( CASE WHEN la.lead_status = 'won'
				               AND MONTH(la.status_updated_at) = MONTH(CURDATE())
				               AND YEAR(la.status_updated_at)  = YEAR(CURDATE())
				          THEN 1 ELSE 0 END )                                      AS won_this_month,
				SUM( CASE WHEN la.lead_status = 'lost'         THEN 1 ELSE 0 END ) AS lost_leads,
				SUM( CASE WHEN la.lead_status = 'stale'        THEN 1 ELSE 0 END ) AS stale_leads,
				SUM( CASE WHEN la.lead_status = 'no_answer'    THEN 1 ELSE 0 END ) AS no_answer_count,
				SUM( CASE WHEN la.lead_status = 'wrong_number' THEN 1 ELSE 0 END ) AS wrong_number_count,
				AVG( TIMESTAMPDIFF( HOUR, la.assigned_at, la.first_response_at ) ) AS avg_response_hours,
				AVG( la.lead_quality_rating )                                      AS avg_quality_rating,
				SUM( CASE WHEN la.lead_status = 'won'
				          THEN la.total_estimate_incl ELSE 0 END )                 AS total_revenue,
				SUM( CASE WHEN la.lead_status = 'won'
				               AND MONTH(la.status_updated_at) = MONTH(CURDATE())
				               AND YEAR(la.status_updated_at)  = YEAR(CURDATE())
				          THEN la.total_estimate_incl ELSE 0 END )                 AS revenue_this_month
			FROM `{$lead_agents_table}` la
			INNER JOIN `{$builders_meta}` bm ON bm.object_ID = la.agent_post_id
			GROUP BY la.agent_post_id, bm.company_name
			ORDER BY ( won_leads / NULLIF( total_leads, 0 ) ) DESC",
			ARRAY_A
		);

		if ( ! $rows ) {
			return array();
		}

		$metrics = array();

		foreach ( $rows as $row ) {
			$agent_post_id  = (int) $row['agent_post_id'];
			$total_leads    = (int) $row['total_leads'];
			$won_leads      = (int) $row['won_leads'];

			$metrics[] = array(
				'agent_post_id'      => $agent_post_id,
				'company_name'       => (string) $row['company_name'],
				'contact_name'       => (string) get_post_meta( $agent_post_id, 'contact_name', true ),
				'total_leads'        => $total_leads,
				'leads_this_month'   => (int) $row['leads_this_month'],
				'won_leads'          => $won_leads,
				'won_this_month'     => (int) $row['won_this_month'],
				'lost_leads'         => (int) $row['lost_leads'],
				'stale_leads'        => (int) $row['stale_leads'],
				'no_answer_count'    => (int) $row['no_answer_count'],
				'wrong_number_count' => (int) $row['wrong_number_count'],
				'conversion_rate'    => $total_leads > 0
					? round( ( $won_leads / $total_leads ) * 100, 1 )
					: 0.0,
				'avg_response_hours' => $row['avg_response_hours'] !== null
					? round( (float) $row['avg_response_hours'], 1 )
					: null,
				'avg_quality_rating' => $row['avg_quality_rating'] !== null
					? round( (float) $row['avg_quality_rating'], 1 )
					: null,
				'total_revenue'      => round( (float) $row['total_revenue'], 2 ),
				'revenue_this_month' => round( (float) $row['revenue_this_month'], 2 ),
			);
		}

		return $metrics;
	}

	/**
	 * Returns platform-wide totals and per-status lead counts.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public static function get_platform_summary(): array {
		global $wpdb;

		$lead_agents_table = BK_Database::get_table_name( 'lead_agents' );
		$builders_meta     = $wpdb->prefix . 'builders_meta';

		// Aggregate stats.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			"SELECT
				COUNT(*)                                                                AS total_leads,
				SUM( CASE WHEN MONTH(assigned_at) = MONTH(CURDATE())
				               AND YEAR(assigned_at) = YEAR(CURDATE())
				          THEN 1 ELSE 0 END )                                           AS total_leads_this_month,
				SUM( CASE WHEN lead_status = 'won'  THEN 1 ELSE 0 END )                 AS total_won,
				SUM( CASE WHEN lead_status = 'won'
				               AND MONTH(status_updated_at) = MONTH(CURDATE())
				               AND YEAR(status_updated_at)  = YEAR(CURDATE())
				          THEN 1 ELSE 0 END )                                           AS total_won_this_month,
				SUM( CASE WHEN lead_status = 'lost'  THEN 1 ELSE 0 END )                AS total_lost,
				SUM( CASE WHEN lead_status = 'stale' THEN 1 ELSE 0 END )                AS total_stale,
				SUM( CASE WHEN lead_status = 'won'
				          THEN total_estimate_incl ELSE 0 END )                         AS total_revenue,
				SUM( CASE WHEN lead_status = 'won'
				               AND MONTH(status_updated_at) = MONTH(CURDATE())
				               AND YEAR(status_updated_at)  = YEAR(CURDATE())
				          THEN total_estimate_incl ELSE 0 END )                         AS revenue_this_month,
				AVG( TIMESTAMPDIFF( HOUR, assigned_at, first_response_at ) )            AS avg_response_hours,
				COUNT( DISTINCT agent_post_id )                                         AS total_agents
			FROM `{$lead_agents_table}`",
			ARRAY_A
		);

		// Active agents (at least one lead this month).
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$active_agents = (int) $wpdb->get_var(
			"SELECT COUNT( DISTINCT agent_post_id )
			FROM `{$lead_agents_table}`
			WHERE MONTH(assigned_at) = MONTH(CURDATE())
			  AND YEAR(assigned_at)  = YEAR(CURDATE())"
		);

		// Per-status counts.
		$statuses = array( 'new', 'contacted', 'no_answer', 'wrong_number', 'site_visit', 'quoted', 'won', 'lost', 'stale' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$status_rows = $wpdb->get_results(
			"SELECT lead_status, COUNT(*) AS cnt
			FROM `{$lead_agents_table}`
			GROUP BY lead_status",
			ARRAY_A
		);

		$leads_by_status = array_fill_keys( $statuses, 0 );
		foreach ( $status_rows as $sr ) {
			if ( isset( $leads_by_status[ $sr['lead_status'] ] ) ) {
				$leads_by_status[ $sr['lead_status'] ] = (int) $sr['cnt'];
			}
		}

		$total_leads = (int) ( $row['total_leads'] ?? 0 );
		$total_won   = (int) ( $row['total_won'] ?? 0 );

		return array(
			'total_leads'            => $total_leads,
			'total_leads_this_month' => (int) ( $row['total_leads_this_month'] ?? 0 ),
			'total_won'              => $total_won,
			'total_won_this_month'   => (int) ( $row['total_won_this_month'] ?? 0 ),
			'total_lost'             => (int) ( $row['total_lost'] ?? 0 ),
			'total_stale'            => (int) ( $row['total_stale'] ?? 0 ),
			'overall_conversion'     => $total_leads > 0
				? round( ( $total_won / $total_leads ) * 100, 1 )
				: 0.0,
			'total_revenue'          => round( (float) ( $row['total_revenue'] ?? 0 ), 2 ),
			'revenue_this_month'     => round( (float) ( $row['revenue_this_month'] ?? 0 ), 2 ),
			'avg_response_hours'     => $row['avg_response_hours'] !== null
				? round( (float) $row['avg_response_hours'], 1 )
				: null,
			'total_agents'           => (int) ( $row['total_agents'] ?? 0 ),
			'active_agents'          => $active_agents,
			'leads_by_status'        => $leads_by_status,
		);
	}

	/**
	 * Returns agents ranked by won leads this month.
	 *
	 * @since 1.0.0
	 *
	 * @param int $limit Maximum number of agents to return. Default 10.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_monthly_leaderboard( int $limit = 10 ): array {
		global $wpdb;

		$lead_agents_table = BK_Database::get_table_name( 'lead_agents' );
		$builders_meta     = $wpdb->prefix . 'builders_meta';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					la.agent_post_id,
					bm.company_name,
					COUNT(*) AS won_this_month,
					SUM( la.total_estimate_incl ) AS revenue,
					( COUNT(*) / NULLIF( total.total_leads, 0 ) * 100 ) AS conversion_rate
				FROM `{$lead_agents_table}` la
				INNER JOIN `{$builders_meta}` bm ON bm.object_ID = la.agent_post_id
				INNER JOIN (
					SELECT agent_post_id, COUNT(*) AS total_leads
					FROM `{$lead_agents_table}`
					GROUP BY agent_post_id
				) total ON total.agent_post_id = la.agent_post_id
				WHERE la.lead_status = 'won'
				  AND MONTH(la.status_updated_at) = MONTH(CURDATE())
				  AND YEAR(la.status_updated_at)  = YEAR(CURDATE())
				GROUP BY la.agent_post_id, bm.company_name, total.total_leads
				ORDER BY won_this_month DESC
				LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		if ( ! $rows ) {
			return array();
		}

		$leaderboard = array();

		foreach ( $rows as $rank => $row ) {
			$leaderboard[] = array(
				'rank'            => $rank + 1,
				'agent_post_id'   => (int) $row['agent_post_id'],
				'company_name'    => (string) $row['company_name'],
				'won_this_month'  => (int) $row['won_this_month'],
				'conversion_rate' => round( (float) ( $row['conversion_rate'] ?? 0 ), 1 ),
				'revenue'         => round( (float) ( $row['revenue'] ?? 0 ), 2 ),
			);
		}

		return $leaderboard;
	}

	/**
	 * Returns per-agent total_sales_count and monthly_sales_count from builders_meta.
	 *
	 * Used by the reward status section.
	 *
	 * @since 1.0.0
	 *
	 * @param int $agent_post_id Builder CPT post ID.
	 * @return array{total_sales: int, monthly_sales: int}
	 */
	public static function get_agent_sales_counts( int $agent_post_id ): array {
		return array(
			'total_sales'   => (int) get_post_meta( $agent_post_id, 'total_sales_count', true ),
			'monthly_sales' => (int) get_post_meta( $agent_post_id, 'monthly_sales_count', true ),
		);
	}
}
