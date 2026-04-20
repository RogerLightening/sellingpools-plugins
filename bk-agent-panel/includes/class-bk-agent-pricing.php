<?php
/**
 * BK Agent Pricing
 *
 * Retrieves all pool shapes with the agent's current pricing for the
 * pricing management view.
 *
 * @package BK_Agent_Panel
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BK_Agent_Pricing
 *
 * @since 1.0.0
 */
class BK_Agent_Pricing {

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Returns all pool shapes with the agent's pricing for each shape.
	 *
	 * Uses a LEFT JOIN so shapes with no pricing row are still included,
	 * allowing the agent to see and set missing prices.
	 *
	 * @since 1.0.0
	 *
	 * @param int $agent_post_id Builder CPT post ID.
	 * @return array Array of shape+pricing rows.
	 */
	public static function get_pricing( int $agent_post_id ): array {
		global $wpdb;

		$pricing_table      = BK_Database::get_table_name( 'agent_pricing' );
		$pool_shapes_meta   = $wpdb->prefix . 'pool_shapes_meta';
		$posts              = $wpdb->posts;

		// LEFT JOIN: all published pool-shapes posts with the agent's pricing (if any).
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					p.ID              AS pool_shape_id,
					psm.shape_name,
					psm.shape_code,
					psm.shell_price,
					psm.shape_image,
					psm.dimensions_length,
					psm.dimensions_width,
					psm.dimensions_depth_shallow,
					psm.dimensions_depth_deep,
					ap.id             AS pricing_id,
					ap.installed_price_excl,
					ap.installed_price_incl,
					ap.is_available
				FROM `{$posts}` p
				INNER JOIN `{$pool_shapes_meta}` psm ON psm.object_ID = p.ID
				LEFT JOIN `{$pricing_table}` ap
					ON ap.pool_shape_post_id = p.ID
					AND ap.agent_post_id = %d
				WHERE p.post_type   = %s
				  AND p.post_status = %s
				ORDER BY psm.shape_name ASC",
				$agent_post_id,
				'pool-shapes',
				'publish'
			),
			ARRAY_A
		);

		if ( $wpdb->last_error ) {
			error_log( 'BK Agent Pricing — DB error: ' . $wpdb->last_error );
			return array();
		}

		$result = array();

		foreach ( $rows as $row ) {
			$has_pricing     = null !== $row['pricing_id'];
			$shape_image_id  = (int) $row['shape_image'];
			$shape_image_url = $shape_image_id ? (string) wp_get_attachment_url( $shape_image_id ) : '';

			$result[] = array(
				'pricing_id'           => $has_pricing ? (int) $row['pricing_id'] : null,
				'pool_shape_id'        => (int) $row['pool_shape_id'],
				'shape_name'           => (string) $row['shape_name'],
				'shape_code'           => (string) $row['shape_code'],
				'shell_price_excl'     => (float) $row['shell_price'],
				'shape_image_id'       => $shape_image_id,
				'shape_image_url'      => $shape_image_url,
				'dimensions_length'    => (float) $row['dimensions_length'],
				'dimensions_width'     => (float) $row['dimensions_width'],
				'depth_shallow'        => (float) $row['dimensions_depth_shallow'],
				'depth_deep'           => (float) $row['dimensions_depth_deep'],
				'installed_price_excl' => $has_pricing ? (float) $row['installed_price_excl'] : 0.0,
				'installed_price_incl' => $has_pricing ? (float) $row['installed_price_incl'] : 0.0,
				'is_available'         => $has_pricing ? (bool) $row['is_available'] : true,
				'has_pricing'          => $has_pricing,
			);
		}

		return $result;
	}
}
