<?php
/**
 * BK Agent CRM
 *
 * All AJAX endpoints for the agent panel. Every endpoint verifies the nonce,
 * checks capabilities, and confirms data ownership before writing anything.
 *
 * @package BK_Agent_Panel
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BK_Agent_CRM
 *
 * @since 1.0.0
 */
class BK_Agent_CRM {

	/**
	 * Valid lead status values (must match DB ENUM).
	 *
	 * @since 1.0.0
	 * @var string[]
	 */
	const VALID_STATUSES = array(
		'new', 'contacted', 'no_answer', 'wrong_number',
		'site_visit', 'quoted', 'won', 'lost', 'stale',
	);

	/**
	 * Constructor — registers all AJAX action hooks.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$endpoints = array(
			'bk_update_lead_status',
			'bk_update_lead_rating',
			'bk_update_lead_notes',
			'bk_update_agent_pricing',
			'bk_toggle_shape_availability',
			'bk_save_agent_profile',
			'bk_upload_agent_logo',
			'bk_remove_agent_logo',
		);

		foreach ( $endpoints as $action ) {
			add_action( 'wp_ajax_' . $action, array( $this, $action ) );
		}
	}

	// -------------------------------------------------------------------------
	// Lead endpoints
	// -------------------------------------------------------------------------

	/**
	 * AJAX: Update a lead's status.
	 *
	 * POST params: lead_agent_id (int), status (string), nonce.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function bk_update_lead_status(): void {
		$this->verify_nonce_and_cap( 'bk_manage_leads' );

		$lead_agent_id = (int) ( $_POST['lead_agent_id'] ?? 0 );
		$new_status    = sanitize_key( $_POST['status'] ?? '' );

		error_log( 'BK CRM status update: ' . print_r( array( 'lead_agent_id' => $lead_agent_id, 'status' => $new_status ), true ) );

		if ( ! $lead_agent_id || ! in_array( $new_status, self::VALID_STATUSES, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'bk-agent-panel' ) ) );
		}

		$lead = $this->get_owned_lead( $lead_agent_id );

		if ( ! $lead ) {
			wp_send_json_error( array( 'message' => __( 'Lead not found.', 'bk-agent-panel' ) ) );
		}

		global $wpdb;
		$table = BK_Database::get_table_name( 'lead_agents' );

		$update_data   = array(
			'lead_status'       => $new_status,
			'status_updated_at' => current_time( 'mysql' ),
		);
		$update_format = array( '%s', '%s' );

		// Track first response if this is the first status change from 'new'.
		if ( 'new' === $lead['lead_status'] && 'new' !== $new_status && empty( $lead['first_response_at'] ) ) {
			$update_data['first_response_at'] = current_time( 'mysql' );
			$update_format[]                  = '%s';
		}

		$old_status = (string) $lead['lead_status'];

		$wpdb->update(
			$table,
			$update_data,
			array( 'id' => $lead_agent_id ),
			$update_format,
			array( '%d' )
		);

		if ( $wpdb->last_error ) {
			wp_send_json_error( array( 'message' => __( 'Database error.', 'bk-agent-panel' ) ) );
		}

		/**
		 * Fires after a lead's status has been successfully updated.
		 *
		 * Used by bk-performance-tracker to keep sales counters in sync.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $lead_agent_id  Row ID in wp_bk_lead_agents.
		 * @param string $old_status     Status before the change.
		 * @param string $new_status     Status after the change.
		 * @param int    $agent_post_id  Builder CPT post ID.
		 */
		do_action(
			'bk_pools_lead_status_changed',
			$lead_agent_id,
			$old_status,
			$new_status,
			(int) $lead['agent_post_id']
		);

		$labels = array(
			'new'          => __( 'New', 'bk-agent-panel' ),
			'contacted'    => __( 'Contacted', 'bk-agent-panel' ),
			'no_answer'    => __( 'No Answer', 'bk-agent-panel' ),
			'wrong_number' => __( 'Wrong Number', 'bk-agent-panel' ),
			'site_visit'   => __( 'Site Visit', 'bk-agent-panel' ),
			'quoted'       => __( 'Quoted', 'bk-agent-panel' ),
			'won'          => __( 'Won', 'bk-agent-panel' ),
			'lost'         => __( 'Lost', 'bk-agent-panel' ),
			'stale'        => __( 'Stale', 'bk-agent-panel' ),
		);

		wp_send_json_success( array(
			'message'           => sprintf(
				/* translators: %s: new status label */
				__( 'Status updated to %s.', 'bk-agent-panel' ),
				$labels[ $new_status ] ?? $new_status
			),
			'status'            => $new_status,
			'status_label'      => $labels[ $new_status ] ?? $new_status,
			'status_updated_at' => current_time( 'mysql' ),
		) );
	}

	/**
	 * AJAX: Update a lead's quality rating.
	 *
	 * POST params: lead_agent_id (int), rating (int 0–5, 0 = unrate), nonce.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function bk_update_lead_rating(): void {
		$this->verify_nonce_and_cap( 'bk_manage_leads' );

		// Respect the star-ratings feature toggle.
		if ( class_exists( 'BK_Feature_Toggles' ) && ! BK_Feature_Toggles::is_enabled( 'feature_star_ratings' ) ) {
			wp_send_json_error( array( 'message' => __( 'Rating feature is currently disabled.', 'bk-agent-panel' ) ) );
		}

		$lead_agent_id = (int) ( $_POST['lead_agent_id'] ?? 0 );
		$rating        = (int) ( $_POST['rating'] ?? 0 );

		// 0 is allowed — it means "clear rating".
		if ( ! $lead_agent_id || $rating < 0 || $rating > 5 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'bk-agent-panel' ) ) );
		}

		if ( ! $this->get_owned_lead( $lead_agent_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Lead not found.', 'bk-agent-panel' ) ) );
		}

		global $wpdb;

		$wpdb->update(
			BK_Database::get_table_name( 'lead_agents' ),
			// Store NULL when rating is 0 (unrated).
			array( 'lead_quality_rating' => $rating ?: null ),
			array( 'id' => $lead_agent_id ),
			array( $rating ? '%d' : 'NULL' ),
			array( '%d' )
		);

		wp_send_json_success( array(
			'rating'  => $rating,
			'message' => $rating
				? sprintf( __( 'Rating set to %d.', 'bk-agent-panel' ), $rating )
				: __( 'Rating cleared.', 'bk-agent-panel' ),
		) );
	}

	/**
	 * AJAX: Update a lead's notes.
	 *
	 * POST params: lead_agent_id (int), notes (string), nonce.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function bk_update_lead_notes(): void {
		$this->verify_nonce_and_cap( 'bk_manage_leads' );

		$lead_agent_id = (int) ( $_POST['lead_agent_id'] ?? 0 );
		$notes         = sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) );

		if ( ! $lead_agent_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'bk-agent-panel' ) ) );
		}

		if ( ! $this->get_owned_lead( $lead_agent_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Lead not found.', 'bk-agent-panel' ) ) );
		}

		global $wpdb;

		$wpdb->update(
			BK_Database::get_table_name( 'lead_agents' ),
			array( 'lead_notes' => $notes ),
			array( 'id' => $lead_agent_id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( $wpdb->last_error ) {
			wp_send_json_error( array( 'message' => __( 'Database error.', 'bk-agent-panel' ) ) );
		}

		wp_send_json_success( array(
			'message' => __( 'Notes saved.', 'bk-agent-panel' ),
			'notes'   => $notes,
		) );
	}

	// -------------------------------------------------------------------------
	// Pricing endpoints
	// -------------------------------------------------------------------------

	/**
	 * AJAX: Update (or create) an agent's all-inclusive price for a pool shape.
	 *
	 * POST params: pricing_id (int, 0 for new), pool_shape_id (int), price_incl (float), nonce.
	 *
	 * The price submitted is the agent's all-inclusive price INCLUDING VAT.
	 * The excl. figure is back-calculated for storage. When pricing_id is 0 the
	 * handler upserts by (agent_post_id, pool_shape_post_id), creating the row
	 * if it does not yet exist.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function bk_update_agent_pricing(): void {
		$this->verify_nonce_and_cap( 'bk_manage_pricing' );

		$pricing_id    = (int) ( $_POST['pricing_id'] ?? 0 );
		$pool_shape_id = (int) ( $_POST['pool_shape_id'] ?? 0 );
		$price_incl    = (float) ( $_POST['price_incl'] ?? 0 ); // Agent enters all-inclusive (incl. VAT) price.

		error_log( 'BK pricing update: ' . print_r(
			array(
				'pricing_id'    => $pricing_id,
				'pool_shape_id' => $pool_shape_id,
				'price_incl'    => $price_incl,
			),
			true
		) );

		if ( ( ! $pricing_id && ! $pool_shape_id ) || $price_incl < 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'bk-agent-panel' ) ) );
		}

		$agent_post_id = BK_Agent_Auth::get_agent_post_id();

		if ( ! $agent_post_id ) {
			wp_send_json_error( array( 'message' => __( 'Agent profile not found.', 'bk-agent-panel' ) ) );
		}

		// Back-calculate the excl. price from the incl. VAT price for storage.
		$vat_rate   = BK_Helpers::get_vat_rate();
		$price_excl = $vat_rate > 0 ? round( $price_incl / ( 1 + $vat_rate ), 2 ) : $price_incl;

		global $wpdb;
		$table = BK_Database::get_table_name( 'agent_pricing' );

		if ( $pricing_id > 0 ) {
			// ---- Update existing row ----------------------------------------
			if ( ! $this->get_owned_pricing_row( $pricing_id ) ) {
				wp_send_json_error( array( 'message' => __( 'Pricing record not found.', 'bk-agent-panel' ) ) );
			}

			$wpdb->update(
				$table,
				array(
					'installed_price_excl' => $price_excl,
					'installed_price_incl' => $price_incl,
					'updated_at'           => current_time( 'mysql' ),
				),
				array( 'id' => $pricing_id ),
				array( '%f', '%f', '%s' ),
				array( '%d' )
			);
		} else {
			// ---- Upsert by agent + shape ------------------------------------
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$existing_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM `{$table}` WHERE agent_post_id = %d AND pool_shape_post_id = %d LIMIT 1",
					$agent_post_id,
					$pool_shape_id
				)
			);

			if ( $existing_id ) {
				$pricing_id = (int) $existing_id;

				$wpdb->update(
					$table,
					array(
						'installed_price_excl' => $price_excl,
						'installed_price_incl' => $price_incl,
						'updated_at'           => current_time( 'mysql' ),
					),
					array( 'id' => $pricing_id ),
					array( '%f', '%f', '%s' ),
					array( '%d' )
				);
			} else {
				$wpdb->insert(
					$table,
					array(
						'agent_post_id'        => $agent_post_id,
						'pool_shape_post_id'   => $pool_shape_id,
						'installed_price_excl' => $price_excl,
						'installed_price_incl' => $price_incl,
						'is_available'         => 1,
						'updated_at'           => current_time( 'mysql' ),
					),
					array( '%d', '%d', '%f', '%f', '%d', '%s' )
				);
				$pricing_id = (int) $wpdb->insert_id;
			}
		}

		if ( $wpdb->last_error ) {
			error_log( 'BK pricing update DB error: ' . $wpdb->last_error );
			wp_send_json_error( array( 'message' => __( 'Database error.', 'bk-agent-panel' ) ) );
		}

		wp_send_json_success( array(
			'message'                  => __( 'Price saved.', 'bk-agent-panel' ),
			'pricing_id'               => $pricing_id,
			'installed_price_incl'     => $price_incl,
			'installed_price_incl_fmt' => BK_Helpers::format_currency( $price_incl ),
		) );
	}

	/**
	 * AJAX: Toggle availability of a pool shape for an agent.
	 *
	 * POST params: pricing_id (int), available (int 0|1), nonce.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function bk_toggle_shape_availability(): void {
		$this->verify_nonce_and_cap( 'bk_manage_pricing' );

		$pricing_id = (int) ( $_POST['pricing_id'] ?? 0 );
		$available  = (int) ( $_POST['available'] ?? 0 );
		$available  = $available ? 1 : 0;

		if ( ! $pricing_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'bk-agent-panel' ) ) );
		}

		if ( ! $this->get_owned_pricing_row( $pricing_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Pricing record not found.', 'bk-agent-panel' ) ) );
		}

		global $wpdb;

		$wpdb->update(
			BK_Database::get_table_name( 'agent_pricing' ),
			array(
				'is_available' => $available,
				'updated_at'   => current_time( 'mysql' ),
			),
			array( 'id' => $pricing_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		if ( $wpdb->last_error ) {
			wp_send_json_error( array( 'message' => __( 'Database error.', 'bk-agent-panel' ) ) );
		}

		wp_send_json_success( array(
			'message'      => $available
				? __( 'Pool shape marked as available.', 'bk-agent-panel' )
				: __( 'Pool shape marked as unavailable.', 'bk-agent-panel' ),
			'is_available' => $available,
		) );
	}

	// -------------------------------------------------------------------------
	// Profile endpoints
	// -------------------------------------------------------------------------

	/**
	 * AJAX: Save agent profile fields.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function bk_save_agent_profile(): void {
		$this->verify_nonce_and_cap( 'bk_manage_profile' );

		$agent_post_id = BK_Agent_Auth::get_agent_post_id();

		if ( ! $agent_post_id ) {
			wp_send_json_error( array( 'message' => __( 'Agent profile not found.', 'bk-agent-panel' ) ) );
		}

		// Scalar fields — key matches both the POST param name and the meta key.
		$scalar_fields = array(
			'company_name'               => 'sanitize_text_field',
			'contact_name'               => 'sanitize_text_field',
			'email'                      => 'sanitize_email',
			'phone'                      => array( 'BK_Helpers', 'sanitise_phone' ),
			'physical_address'           => 'sanitize_text_field',
			'province'                   => 'sanitize_text_field',
			'travel_fee_min_distance_km' => 'floatval',
			'travel_fee_type'            => 'sanitize_key',
			'travel_fee_rate'            => 'floatval',
			'max_travel_radius_km'       => 'floatval',
			'bio'                        => 'sanitize_textarea_field',
		);

		foreach ( $scalar_fields as $field => $sanitiser ) {
			if ( ! isset( $_POST[ $field ] ) ) {
				continue;
			}

			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$raw   = wp_unslash( $_POST[ $field ] );
			$value = is_array( $sanitiser )
				? call_user_func( $sanitiser, $raw )
				: $sanitiser( $raw );

			update_post_meta( $agent_post_id, $field, $value );
		}

		// Boolean: travel_fee_enabled — stored as 'true'/'false' string.
		$travel_fee_enabled = ( isset( $_POST['travel_fee_enabled'] ) && '1' === (string) $_POST['travel_fee_enabled'] )
			? 'true' : 'false';
		update_post_meta( $agent_post_id, 'travel_fee_enabled', $travel_fee_enabled );

		// WYSIWYG / rich-text fields.
		foreach ( array( 'estimate_includes', 'terms_and_conditions', 'payment_structure' ) as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				update_post_meta( $agent_post_id, $field, wp_kses_post( wp_unslash( $_POST[ $field ] ) ) );
			}
		}

		// Suburb ID + derive lat/lng from JetEngine CCT.
		if ( isset( $_POST['suburb_id'] ) ) {
			$suburb_id = (int) $_POST['suburb_id'];
			update_post_meta( $agent_post_id, 'suburb_id', $suburb_id );
			error_log( 'BK save profile: suburb_id=' . $suburb_id . ' for agent_post_id=' . $agent_post_id );

			if ( $suburb_id ) {
				$this->update_agent_coordinates( $agent_post_id, $suburb_id );
			} else {
				// User cleared the suburb field — drop the stale coordinates so
				// distance calculations don't use the previous suburb's lat/lng.
				delete_post_meta( $agent_post_id, 'physical_address_lat' );
				delete_post_meta( $agent_post_id, 'physical_address_lng' );
				error_log( 'BK save profile: cleared physical_address_lat/lng' );
			}
		} else {
			error_log( 'BK save profile: suburb_id not in POST — autocomplete hidden input may be missing or disabled' );
		}

		wp_send_json_success( array( 'message' => __( 'Profile saved successfully.', 'bk-agent-panel' ) ) );
	}

	/**
	 * AJAX: Upload and set a new company logo.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function bk_upload_agent_logo(): void {
		$this->verify_nonce_and_cap( 'bk_manage_profile' );

		$agent_post_id = BK_Agent_Auth::get_agent_post_id();

		if ( ! $agent_post_id ) {
			wp_send_json_error( array( 'message' => __( 'Agent profile not found.', 'bk-agent-panel' ) ) );
		}

		if ( empty( $_FILES['logo'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file uploaded.', 'bk-agent-panel' ) ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$overrides = array( 'test_form' => false );
		$file      = wp_handle_upload( $_FILES['logo'], $overrides );

		if ( isset( $file['error'] ) ) {
			wp_send_json_error( array( 'message' => $file['error'] ) );
		}

		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => $file['type'],
				'post_title'     => sanitize_file_name( $file['file'] ),
				'post_status'    => 'inherit',
			),
			$file['file']
		);

		if ( is_wp_error( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ) );
		}

		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $file['file'] ) );
		update_post_meta( $agent_post_id, 'company_logo', $attachment_id );

		wp_send_json_success( array(
			'message'       => __( 'Logo uploaded.', 'bk-agent-panel' ),
			'url'           => wp_get_attachment_url( $attachment_id ),
			'attachment_id' => $attachment_id,
		) );
	}

	/**
	 * AJAX: Remove the agent's company logo.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function bk_remove_agent_logo(): void {
		$this->verify_nonce_and_cap( 'bk_manage_profile' );

		$agent_post_id = BK_Agent_Auth::get_agent_post_id();

		if ( ! $agent_post_id ) {
			wp_send_json_error( array( 'message' => __( 'Agent profile not found.', 'bk-agent-panel' ) ) );
		}

		delete_post_meta( $agent_post_id, 'company_logo' );

		wp_send_json_success( array( 'message' => __( 'Logo removed.', 'bk-agent-panel' ) ) );
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Verifies the panel nonce and a required capability. Dies on failure.
	 *
	 * @since 1.0.0
	 *
	 * @param string $capability WordPress capability to check.
	 * @return void
	 */
	private function verify_nonce_and_cap( string $capability ): void {
		check_ajax_referer( 'bk_agent_panel', 'nonce' );

		if ( ! current_user_can( $capability ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bk-agent-panel' ) ), 403 );
		}
	}

	/**
	 * Returns a lead row only if it belongs to the current user.
	 *
	 * @since 1.0.0
	 *
	 * @param int $lead_agent_id Row ID in wp_bk_lead_agents.
	 * @return array|null Lead row as ARRAY_A, or null if not found / not owned.
	 */
	private function get_owned_lead( int $lead_agent_id ): ?array {
		$agent_post_id = BK_Agent_Auth::get_agent_post_id();

		if ( ! $agent_post_id ) {
			return null;
		}

		global $wpdb;

		$table = BK_Database::get_table_name( 'lead_agents' );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE id = %d AND agent_post_id = %d LIMIT 1",
				$lead_agent_id,
				$agent_post_id
			),
			ARRAY_A
		);

		return $row ?: null;
	}

	/**
	 * Returns a pricing row only if it belongs to the current agent.
	 *
	 * @since 1.0.0
	 *
	 * @param int $pricing_id Row ID in wp_bk_agent_pricing.
	 * @return array|null Pricing row as ARRAY_A, or null if not found / not owned.
	 */
	private function get_owned_pricing_row( int $pricing_id ): ?array {
		global $wpdb;

		$agent_post_id = BK_Agent_Auth::get_agent_post_id();

		if ( ! $agent_post_id ) {
			return null;
		}

		$table = BK_Database::get_table_name( 'agent_pricing' );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE id = %d AND agent_post_id = %d LIMIT 1",
				$pricing_id,
				$agent_post_id
			),
			ARRAY_A
		);

		return $row ?: null;
	}

	/**
	 * Updates an agent's lat/lng meta from a suburb CCT record.
	 *
	 * @since 1.0.0
	 *
	 * @param int $agent_post_id Builder CPT post ID.
	 * @param int $suburb_id     JetEngine CCT suburb _ID.
	 * @return void
	 */
	private function update_agent_coordinates( int $agent_post_id, int $suburb_id ): void {
		global $wpdb;

		$suburbs_table = $wpdb->prefix . 'jet_cct_suburbs';

		$suburb = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT latitude, longitude FROM `{$suburbs_table}` WHERE _ID = %d LIMIT 1",
				$suburb_id
			)
		);

		if ( ! $suburb ) {
			error_log( 'BK save profile: suburb_id ' . $suburb_id . ' not found in ' . $suburbs_table );
			return;
		}

		update_post_meta( $agent_post_id, 'physical_address_lat', (float) $suburb->latitude );
		update_post_meta( $agent_post_id, 'physical_address_lng', (float) $suburb->longitude );
		error_log( 'BK save profile: updated lat=' . $suburb->latitude . ' lng=' . $suburb->longitude . ' from suburb_id=' . $suburb_id );
	}
}
