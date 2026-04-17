<?php
/**
 * BK Agent Profile
 *
 * Retrieves agent profile data for the profile and settings view.
 *
 * @package BK_Agent_Panel
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BK_Agent_Profile
 *
 * @since 1.0.0
 */
class BK_Agent_Profile {

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Returns the agent's current profile data for the edit form.
	 *
	 * @since 1.0.0
	 *
	 * @param int $agent_post_id Builder CPT post ID.
	 * @return array Profile data array.
	 */
	public static function get_profile( int $agent_post_id ): array {
		$company_logo_id  = (int) get_post_meta( $agent_post_id, 'company_logo', true );
		$profile_image_id = (int) get_post_meta( $agent_post_id, 'profile_image', true );

		// Suburb data from JetEngine CCT.
		$suburb_id   = (int) get_post_meta( $agent_post_id, 'suburb_id', true );
		$suburb_name = '';
		$suburb_area = '';

		if ( $suburb_id ) {
			global $wpdb;
			$suburbs_table = $wpdb->prefix . 'jet_cct_suburbs';
			$suburb_row    = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT suburb, area FROM `{$suburbs_table}` WHERE _ID = %d LIMIT 1",
					$suburb_id
				)
			);

			if ( $suburb_row ) {
				$suburb_name = (string) $suburb_row->suburb;
				$suburb_area = (string) $suburb_row->area;
			}
		}

		return array(
			'post_id'                    => $agent_post_id,
			'company_name'               => (string) get_post_meta( $agent_post_id, 'company_name', true ),
			'company_logo_id'            => $company_logo_id,
			'company_logo_url'           => $company_logo_id ? (string) wp_get_attachment_url( $company_logo_id ) : '',
			'contact_name'               => (string) get_post_meta( $agent_post_id, 'contact_name', true ),
			'email'                      => (string) get_post_meta( $agent_post_id, 'email', true ),
			'phone'                      => (string) get_post_meta( $agent_post_id, 'phone', true ),
			'physical_address'           => (string) get_post_meta( $agent_post_id, 'physical_address', true ),
			'suburb_id'                  => $suburb_id,
			'suburb_name'                => $suburb_name,
			'suburb_area'                => $suburb_area,
			'province'                   => (string) get_post_meta( $agent_post_id, 'province', true ),
			'lat'                        => (float) get_post_meta( $agent_post_id, 'physical_address_lat', true ),
			'lng'                        => (float) get_post_meta( $agent_post_id, 'physical_address_lng', true ),
			'travel_fee_enabled'         => 'true' === (string) get_post_meta( $agent_post_id, 'travel_fee_enabled', true ),
			'travel_fee_min_distance_km' => (float) get_post_meta( $agent_post_id, 'travel_fee_min_distance_km', true ),
			'travel_fee_type'            => (string) get_post_meta( $agent_post_id, 'travel_fee_type', true ) ?: 'fixed_per_km',
			'travel_fee_rate'            => (float) get_post_meta( $agent_post_id, 'travel_fee_rate', true ),
			'max_travel_radius_km'       => (float) get_post_meta( $agent_post_id, 'max_travel_radius_km', true ),
			'estimate_includes'          => (string) get_post_meta( $agent_post_id, 'estimate_includes', true ),
			'terms_and_conditions'       => (string) get_post_meta( $agent_post_id, 'terms_and_conditions', true ),
			'payment_structure'          => (string) get_post_meta( $agent_post_id, 'payment_structure', true ),
			'bio'                        => (string) get_post_meta( $agent_post_id, 'bio', true ),
			'profile_image_url'          => $profile_image_id ? (string) wp_get_attachment_url( $profile_image_id ) : '',
			'is_active'                  => 'true' === (string) get_post_meta( $agent_post_id, 'is_active', true ),
		);
	}
}
