<?php
/**
 * BK Agent Dashboard
 *
 * Assembles all data for the agent dashboard home view.
 *
 * @package BK_Agent_Panel
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BK_Agent_Dashboard
 *
 * @since 1.0.0
 */
class BK_Agent_Dashboard {

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Returns all data needed for the dashboard template.
	 *
	 * @since 1.0.0
	 *
	 * @param int $agent_post_id Builder CPT post ID.
	 * @return array Dashboard data array.
	 */
	public static function get_dashboard_data( int $agent_post_id ): array {
		global $wpdb;

		$lead_agents_table  = BK_Database::get_table_name( 'lead_agents' );
		$builders_meta      = $wpdb->prefix . 'builders_meta';
		$estimate_meta      = $wpdb->prefix . 'estimate_meta';

		// -- 1. Agent name / company -----------------------------------------

		$agent_name   = (string) get_post_meta( $agent_post_id, 'contact_name', true );
		$company_name = (string) get_post_meta( $agent_post_id, 'company_name', true );

		// -- 2. Stats ---------------------------------------------------------

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$stats_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) AS total_leads,
					SUM(CASE WHEN lead_status = 'won' THEN 1 ELSE 0 END) AS won_leads,
					SUM(CASE WHEN MONTH(assigned_at) = MONTH(CURDATE()) AND YEAR(assigned_at) = YEAR(CURDATE()) THEN 1 ELSE 0 END) AS leads_this_month,
					SUM(CASE WHEN lead_status = 'won' AND MONTH(status_updated_at) = MONTH(CURDATE()) AND YEAR(status_updated_at) = YEAR(CURDATE()) THEN 1 ELSE 0 END) AS won_this_month,
					AVG(lead_quality_rating) AS avg_quality_given
				FROM `{$lead_agents_table}`
				WHERE agent_post_id = %d",
				$agent_post_id
			),
			ARRAY_A
		);

		$total_leads       = (int) ( $stats_row['total_leads'] ?? 0 );
		$won_leads         = (int) ( $stats_row['won_leads'] ?? 0 );
		$leads_this_month  = (int) ( $stats_row['leads_this_month'] ?? 0 );
		$won_this_month    = (int) ( $stats_row['won_this_month'] ?? 0 );
		$avg_quality       = $stats_row['avg_quality_given'] ? round( (float) $stats_row['avg_quality_given'], 1 ) : 0.0;
		$conversion_rate   = $total_leads > 0 ? round( ( $won_leads / $total_leads ) * 100, 1 ) : 0.0;

		// -- 3. Recent new leads ---------------------------------------------

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$new_lead_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					la.id AS lead_agent_id,
					la.estimate_post_id,
					la.assigned_at,
					TIMESTAMPDIFF(HOUR, la.assigned_at, NOW()) AS hours_since,
					em.customer_name,
					em.pool_shape_name,
					em.suburb_name
				FROM `{$lead_agents_table}` la
				INNER JOIN `{$estimate_meta}` em ON em.object_ID = la.estimate_post_id
				WHERE la.agent_post_id = %d
				  AND la.lead_status = 'new'
				ORDER BY la.assigned_at ASC
				LIMIT 5",
				$agent_post_id
			),
			ARRAY_A
		);

		$recent_new_leads = array();

		foreach ( $new_lead_rows as $row ) {
			$recent_new_leads[] = array(
				'lead_agent_id'    => (int) $row['lead_agent_id'],
				'estimate_post_id' => (int) $row['estimate_post_id'],
				'customer_name'    => (string) $row['customer_name'],
				'pool_shape_name'  => (string) $row['pool_shape_name'],
				'suburb_name'      => (string) $row['suburb_name'],
				'assigned_at'      => (string) $row['assigned_at'],
				'hours_since'      => (int) $row['hours_since'],
			);
		}

		// -- 4. Leaderboard --------------------------------------------------

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$leaderboard_rows = $wpdb->get_results(
			"SELECT
				bm.company_name,
				bm.object_ID AS agent_post_id,
				COUNT(*) AS won_this_month
			FROM `{$lead_agents_table}` la
			INNER JOIN `{$builders_meta}` bm ON bm.object_ID = la.agent_post_id
			WHERE la.lead_status = 'won'
			  AND MONTH(la.status_updated_at) = MONTH(CURDATE())
			  AND YEAR(la.status_updated_at) = YEAR(CURDATE())
			GROUP BY la.agent_post_id
			ORDER BY won_this_month DESC
			LIMIT 3",
			ARRAY_A
		);

		// Re-run as a non-prepared query since there are no user inputs.
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching
		$leaderboard_rows = $wpdb->get_results(
			"SELECT
				bm.company_name,
				bm.object_ID AS agent_post_id,
				COUNT(*) AS won_this_month
			FROM `{$lead_agents_table}` la
			INNER JOIN `{$builders_meta}` bm ON bm.object_ID = la.agent_post_id
			WHERE la.lead_status = 'won'
			  AND MONTH(la.status_updated_at) = MONTH(CURDATE())
			  AND YEAR(la.status_updated_at) = YEAR(CURDATE())
			GROUP BY la.agent_post_id
			ORDER BY won_this_month DESC
			LIMIT 3",
			ARRAY_A
		);
		// phpcs:enable

		$leaderboard = array();

		foreach ( $leaderboard_rows as $row ) {
			$leaderboard[] = array(
				'company_name'    => (string) $row['company_name'],
				'won_this_month'  => (int) $row['won_this_month'],
				'is_current_agent' => (int) $row['agent_post_id'] === $agent_post_id,
			);
		}

		// -- 5. Assemble -----------------------------------------------------

		return array(
			'agent_name'       => $agent_name,
			'company_name'     => $company_name,
			'stats'            => array(
				'total_leads'       => $total_leads,
				'leads_this_month'  => $leads_this_month,
				'won_leads'         => $won_leads,
				'won_this_month'    => $won_this_month,
				'conversion_rate'   => $conversion_rate,
				'avg_quality_given' => $avg_quality,
			),
			'recent_new_leads' => $recent_new_leads,
			'leaderboard'      => $leaderboard,
		);
	}
}
