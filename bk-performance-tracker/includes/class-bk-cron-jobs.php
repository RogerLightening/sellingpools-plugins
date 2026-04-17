<?php
/**
 * BK Cron Jobs
 *
 * Registers and handles all scheduled tasks for the BK Pools platform:
 *   1. Flag stale leads — runs daily, marks 'new' leads past the threshold as 'stale'.
 *   2. Monthly reset   — runs daily, resets monthly_sales_count on the 1st of the month.
 *
 * Also hooks into the bk_pools_lead_status_changed action (fired by bk-agent-panel's
 * CRM class) to keep total_sales_count and monthly_sales_count in sync.
 *
 * @package BK_Performance_Tracker
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BK_Cron_Jobs
 *
 * @since 1.0.0
 */
class BK_Cron_Jobs {

	/**
	 * Constructor — registers all WordPress hooks.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'bk_pools_flag_stale_leads', array( $this, 'flag_stale_leads' ) );
		add_action( 'bk_pools_monthly_reset', array( $this, 'monthly_reset' ) );
		add_action( 'bk_pools_lead_status_changed', array( $this, 'handle_status_change' ), 10, 4 );
	}

	// -------------------------------------------------------------------------
	// Cron handlers
	// -------------------------------------------------------------------------

	/**
	 * Flags 'new' leads older than the stale_lead_days threshold as 'stale'.
	 *
	 * Runs daily via the bk_pools_flag_stale_leads cron event.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function flag_stale_leads(): void {
		global $wpdb;

		$stale_days = (int) BK_Settings::get_setting( 'stale_lead_days', 7 );

		if ( $stale_days < 1 ) {
			$stale_days = 7;
		}

		$table = BK_Database::get_table_name( 'lead_agents' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = $wpdb->query(
			$wpdb->prepare(
				"UPDATE `{$table}`
				SET lead_status = 'stale', status_updated_at = %s
				WHERE lead_status = 'new'
				  AND assigned_at < DATE_SUB( NOW(), INTERVAL %d DAY )",
				current_time( 'mysql' ),
				$stale_days
			)
		);

		error_log( sprintf( 'BK Cron: Flagged %d lead(s) as stale.', (int) $count ) );
	}

	/**
	 * Resets monthly_sales_count to 0 for all builder posts on the 1st of each month.
	 *
	 * Runs daily but short-circuits unless today is the 1st and the reset has
	 * not already been performed this month.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function monthly_reset(): void {
		// Only run on the 1st of the month.
		if ( gmdate( 'j' ) !== '1' ) {
			return;
		}

		$current_month = gmdate( 'Y-m' );
		$last_reset    = get_option( 'bk_pools_last_monthly_reset', '' );

		// Already reset this month — bail out.
		if ( $last_reset === $current_month ) {
			return;
		}

		global $wpdb;

		$builders_meta = $wpdb->prefix . 'builders_meta';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query(
			"UPDATE `{$builders_meta}` SET monthly_sales_count = '0'"
		);

		update_option( 'bk_pools_last_monthly_reset', $current_month );

		error_log( 'BK Cron: Monthly sales counters reset.' );
	}

	// -------------------------------------------------------------------------
	// Status-change handler
	// -------------------------------------------------------------------------

	/**
	 * Updates total_sales_count and monthly_sales_count on the agent's builder
	 * meta record whenever a lead status changes to or from 'won'.
	 *
	 * Hooked onto bk_pools_lead_status_changed (fired from bk-agent-panel's
	 * BK_Agent_CRM::bk_update_lead_status()).
	 *
	 * @since 1.0.0
	 *
	 * @param int    $lead_agent_id  Row ID in wp_bk_lead_agents.
	 * @param string $old_status     Status before the change.
	 * @param string $new_status     Status after the change.
	 * @param int    $agent_post_id  Builder CPT post ID.
	 * @return void
	 */
	public function handle_status_change( int $lead_agent_id, string $old_status, string $new_status, int $agent_post_id ): void {
		if ( ! $agent_post_id ) {
			return;
		}

		$gained_won = ( 'won' === $new_status && 'won' !== $old_status );
		$lost_won   = ( 'won' === $old_status && 'won' !== $new_status );

		if ( ! $gained_won && ! $lost_won ) {
			return;
		}

		// total_sales_count.
		$total = max( 0, (int) get_post_meta( $agent_post_id, 'total_sales_count', true ) );
		$total = $gained_won ? $total + 1 : max( 0, $total - 1 );
		update_post_meta( $agent_post_id, 'total_sales_count', $total );

		// monthly_sales_count.
		$monthly = max( 0, (int) get_post_meta( $agent_post_id, 'monthly_sales_count', true ) );
		$monthly = $gained_won ? $monthly + 1 : max( 0, $monthly - 1 );
		update_post_meta( $agent_post_id, 'monthly_sales_count', $monthly );
	}
}
