<?php
/**
 * BK Suburb Lookup
 *
 * Provides the AJAX-powered suburb autocomplete used in the JetFormBuilder
 * lead capture form. Searches the JetEngine CCT suburbs table.
 *
 * @package BK_Agent_Matcher
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BK_Suburb_Lookup
 *
 * Handles:
 *  - Front-end script/style enqueuing for suburb autocomplete.
 *  - AJAX search endpoint (public — no login required).
 *
 * The JavaScript targets any input with the attribute
 * data-bk-suburb-autocomplete="true" or name="suburb_search".
 *
 * @since 1.0.0
 */
class BK_Suburb_Lookup {

	/**
	 * AJAX action name.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const AJAX_ACTION = 'bk_suburb_search';

	/**
	 * Nonce action string.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const NONCE_ACTION = 'bk_suburb_search';

	/**
	 * Maximum number of suburb results to return per search.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const MAX_RESULTS = 15;

	/**
	 * Minimum input length before a search is fired.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const MIN_CHARS = 2;

	/**
	 * Constructor — registers all WordPress hooks.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// AJAX for authenticated users.
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'handle_search' ) );
		// AJAX for unauthenticated visitors (public estimate form).
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, array( $this, 'handle_search' ) );
		// Enqueue front-end assets.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	// -------------------------------------------------------------------------
	// Asset enqueuing
	// -------------------------------------------------------------------------

	/**
	 * Enqueues the suburb autocomplete stylesheet and script on the front end.
	 *
	 * Passes AJAX URL, a nonce, and configuration values to the script via
	 * wp_localize_script(). Loaded globally so it is available on whichever
	 * page hosts the JetFormBuilder estimate form.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		wp_enqueue_style(
			'bk-suburb-autocomplete',
			BK_MATCHER_PLUGIN_URL . 'assets/css/suburb-autocomplete.css',
			array(),
			BK_MATCHER_VERSION
		);

		wp_enqueue_script(
			'bk-suburb-autocomplete',
			BK_MATCHER_PLUGIN_URL . 'assets/js/suburb-autocomplete.js',
			array(),
			BK_MATCHER_VERSION,
			true  // Load in footer.
		);

		wp_localize_script(
			'bk-suburb-autocomplete',
			'bk_suburb_params',
			array(
				'ajax_url'  => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( self::NONCE_ACTION ),
				'min_chars' => self::MIN_CHARS,
			)
		);
	}

	// -------------------------------------------------------------------------
	// AJAX handler
	// -------------------------------------------------------------------------

	/**
	 * Handles the suburb search AJAX request.
	 *
	 * Verifies the nonce, sanitises the search term, queries the CCT suburbs
	 * table, and returns a JSON response with matching suburb records.
	 *
	 * @since 1.0.0
	 *
	 * @return never Terminates via wp_send_json_success() or wp_send_json_error().
	 */
	public function handle_search(): never {
		// Verify nonce — protects against CSRF even on a public endpoint.
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		$term = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';

		if ( mb_strlen( $term ) < self::MIN_CHARS ) {
			wp_send_json_success( array() );
		}

		$results = $this->search_suburbs( $term );

		wp_send_json_success( $results );
	}

	// -------------------------------------------------------------------------
	// Database query
	// -------------------------------------------------------------------------

	/**
	 * Queries the JetEngine CCT suburbs table for matching records.
	 *
	 * Results are ordered so that suburb-name starts-with matches come first,
	 * followed by area starts-with matches, then all other contains matches.
	 * Within each tier, results are sorted alphabetically by suburb.
	 *
	 * @since 1.0.0
	 *
	 * @param string $term The sanitised search term (minimum 2 characters).
	 * @return array<int, array{
	 *     id: int,
	 *     label: string,
	 *     suburb: string,
	 *     area: string,
	 *     province: string,
	 *     lat: float,
	 *     lng: float
	 * }> Array of matching suburb records.
	 */
	private function search_suburbs( string $term ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'jet_cct_suburbs';

		// Escape special LIKE characters in the term.
		$escaped = $wpdb->esc_like( $term );

		// Pattern for WHERE clause (contains match).
		$contains = '%' . $escaped . '%';

		// Pattern for ORDER BY CASE (starts-with gets priority).
		$starts_with = $escaped . '%';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name safely built from $wpdb->prefix.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT _ID, suburb, area, province, latitude, longitude
				FROM `{$table}`
				WHERE suburb LIKE %s OR area LIKE %s
				ORDER BY
					CASE
						WHEN suburb LIKE %s THEN 1
						WHEN area   LIKE %s THEN 2
						ELSE 3
					END,
					suburb ASC
				LIMIT %d",
				$contains,
				$contains,
				$starts_with,
				$starts_with,
				self::MAX_RESULTS
			),
			ARRAY_A
		);

		if ( $wpdb->last_error ) {
			error_log( 'BK Suburb Lookup — query error: ' . $wpdb->last_error );
			return array();
		}

		if ( empty( $rows ) ) {
			return array();
		}

		return array_map(
			static function ( array $row ): array {
				$suburb   = (string) $row['suburb'];
				$area     = (string) $row['area'];
				$province = (string) $row['province'];

				// Build the display label shown in the dropdown.
				$label = $area
					? sprintf( '%s, %s (%s)', $suburb, $area, $province )
					: sprintf( '%s (%s)', $suburb, $province );

				return array(
					'id'       => (int) $row['_ID'],
					'label'    => $label,
					'suburb'   => $suburb,
					'area'     => $area,
					'province' => $province,
					'lat'      => (float) $row['latitude'],
					'lng'      => (float) $row['longitude'],
				);
			},
			$rows
		);
	}
}
