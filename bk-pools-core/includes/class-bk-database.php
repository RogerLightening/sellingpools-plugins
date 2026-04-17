<?php
/**
 * BK Database
 *
 * Handles creation and schema management for all BK Pools custom database tables.
 *
 * @package BK_Pools_Core
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BK_Database
 *
 * Creates and manages the three BK Pools custom database tables:
 *  - {prefix}bk_agent_pricing    — Per-agent per-shape installed prices.
 *  - {prefix}bk_lead_agents      — Junction table linking estimates to assigned agents.
 *  - {prefix}bk_estimate_pdfs    — Generated PDF file references per agent per estimate.
 *
 * Other plugins should reference table names via BK_Database::get_table_name()
 * rather than constructing them manually.
 *
 * @since 1.0.0
 */
class BK_Database {

	/**
	 * Internal DB schema version.
	 * Increment this constant when the schema changes to trigger dbDelta() on update.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const DB_VERSION = '1.0.0';

	/**
	 * WordPress option key used to store the installed DB schema version.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const DB_VERSION_OPTION = 'bk_pools_db_version';

	/**
	 * Bare (un-prefixed) table identifiers.
	 * Use BK_Database::get_table_name( $key ) to obtain the full name.
	 *
	 * @since 1.0.0
	 * @var array<string, string>
	 */
	private static array $table_keys = array(
		'agent_pricing' => 'bk_agent_pricing',
		'lead_agents'   => 'bk_lead_agents',
		'estimate_pdfs' => 'bk_estimate_pdfs',
	);

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Returns the full, prefixed table name for the given key.
	 *
	 * Usage from other plugins:
	 *   $table = BK_Database::get_table_name( 'agent_pricing' );
	 *
	 * @since 1.0.0
	 *
	 * @param string $table One of 'agent_pricing', 'lead_agents', 'estimate_pdfs'.
	 * @return string Full table name, e.g. "wp_bk_agent_pricing".
	 */
	public static function get_table_name( string $table ): string {
		global $wpdb;

		if ( ! array_key_exists( $table, self::$table_keys ) ) {
			/* translators: %s: table key name */
			_doing_it_wrong( __METHOD__, sprintf( esc_html__( 'Unknown BK Pools table key: %s', 'bk-pools-core' ), esc_html( $table ) ), '1.0.0' );
			return '';
		}

		return $wpdb->prefix . self::$table_keys[ $table ];
	}

	/**
	 * Creates or upgrades all BK Pools custom tables.
	 *
	 * Called on plugin activation and on any subsequent activation when
	 * the stored DB version differs from self::DB_VERSION.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function create_tables(): void {
		$installed_version = get_option( self::DB_VERSION_OPTION, '' );

		// Only run dbDelta if tables have never been created or schema has changed.
		if ( version_compare( $installed_version, self::DB_VERSION, '>=' ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		self::create_agent_pricing_table( $charset_collate );
		self::create_lead_agents_table( $charset_collate );
		self::create_estimate_pdfs_table( $charset_collate );

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	// -------------------------------------------------------------------------
	// Private table builders
	// -------------------------------------------------------------------------

	/**
	 * Creates or upgrades the bk_agent_pricing table.
	 *
	 * Stores per-agent per-pool-shape installed prices.
	 *
	 * @since 1.0.0
	 *
	 * @param string $charset_collate The charset/collation string from $wpdb.
	 * @return void
	 */
	private static function create_agent_pricing_table( string $charset_collate ): void {
		$table = self::get_table_name( 'agent_pricing' );

		$sql = "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			agent_post_id BIGINT(20) UNSIGNED NOT NULL,
			pool_shape_post_id BIGINT(20) UNSIGNED NOT NULL,
			installed_price_excl DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			installed_price_incl DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			is_available TINYINT(1) NOT NULL DEFAULT 1,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY agent_shape (agent_post_id, pool_shape_post_id),
			KEY agent_id (agent_post_id),
			KEY shape_id (pool_shape_post_id)
		) {$charset_collate};";

		dbDelta( $sql );

		if ( ! empty( $GLOBALS['wpdb']->last_error ) ) {
			error_log( 'BK Pools Core — error creating bk_agent_pricing table: ' . $GLOBALS['wpdb']->last_error );
		}
	}

	/**
	 * Creates or upgrades the bk_lead_agents table.
	 *
	 * Junction table linking each estimate to its assigned agents.
	 * This is the core CRM data table for the platform.
	 *
	 * @since 1.0.0
	 *
	 * @param string $charset_collate The charset/collation string from $wpdb.
	 * @return void
	 */
	private static function create_lead_agents_table( string $charset_collate ): void {
		$table = self::get_table_name( 'lead_agents' );

		$sql = "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			estimate_post_id BIGINT(20) UNSIGNED NOT NULL,
			agent_post_id BIGINT(20) UNSIGNED NOT NULL,
			agent_user_id BIGINT(20) UNSIGNED NOT NULL,
			distance_km DECIMAL(8,2) NOT NULL DEFAULT 0.00,
			travel_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			total_estimate_incl DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			lead_status ENUM('new','contacted','no_answer','wrong_number','site_visit','quoted','won','lost','stale') NOT NULL DEFAULT 'new',
			lead_quality_rating TINYINT(1) UNSIGNED DEFAULT NULL,
			lead_notes TEXT DEFAULT NULL,
			status_updated_at DATETIME DEFAULT NULL,
			first_response_at DATETIME DEFAULT NULL,
			assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY estimate_agent (estimate_post_id, agent_post_id),
			KEY agent_status (agent_user_id, lead_status),
			KEY estimate_id (estimate_post_id)
		) {$charset_collate};";

		dbDelta( $sql );

		if ( ! empty( $GLOBALS['wpdb']->last_error ) ) {
			error_log( 'BK Pools Core — error creating bk_lead_agents table: ' . $GLOBALS['wpdb']->last_error );
		}
	}

	/**
	 * Creates or upgrades the bk_estimate_pdfs table.
	 *
	 * Stores generated PDF file references per agent per estimate.
	 *
	 * @since 1.0.0
	 *
	 * @param string $charset_collate The charset/collation string from $wpdb.
	 * @return void
	 */
	private static function create_estimate_pdfs_table( string $charset_collate ): void {
		$table = self::get_table_name( 'estimate_pdfs' );

		$sql = "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			estimate_post_id BIGINT(20) UNSIGNED NOT NULL,
			agent_post_id BIGINT(20) UNSIGNED NOT NULL,
			pdf_path VARCHAR(500) NOT NULL,
			pdf_url VARCHAR(500) NOT NULL,
			generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY estimate_agent_pdf (estimate_post_id, agent_post_id)
		) {$charset_collate};";

		dbDelta( $sql );

		if ( ! empty( $GLOBALS['wpdb']->last_error ) ) {
			error_log( 'BK Pools Core — error creating bk_estimate_pdfs table: ' . $GLOBALS['wpdb']->last_error );
		}
	}
}
