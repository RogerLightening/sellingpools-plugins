<?php
/**
 * BK Feature Toggles
 *
 * Provides static helper methods for checking whether agent-facing features
 * are enabled. Settings are stored in the bk_pools_settings option alongside
 * the bk-pools-core settings.
 *
 * All templates should call BK_Feature_Toggles::is_enabled() wrapped in a
 * class_exists() guard so they degrade gracefully when this plugin is inactive:
 *
 *   if ( ! class_exists( 'BK_Feature_Toggles' ) || BK_Feature_Toggles::is_enabled( 'feature_star_ratings' ) ) {
 *       // render stars
 *   }
 *
 * @package BK_Performance_Tracker
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BK_Feature_Toggles
 *
 * @since 1.0.0
 */
class BK_Feature_Toggles {

	/**
	 * Fallback defaults — all features on except the reward system.
	 *
	 * @since 1.0.0
	 * @var array<string, int>
	 */
	private static array $defaults = array(
		'feature_top_agents'   => 1,
		'feature_star_ratings' => 1,
		'feature_leaderboard'  => 1,
		'feature_rewards'      => 0,
	);

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Returns true if the given feature is enabled in the platform settings.
	 *
	 * Falls back to the class default if the setting has never been explicitly
	 * saved. If BK_Settings (bk-pools-core) is not available, reads directly
	 * from the option array.
	 *
	 * @since 1.0.0
	 *
	 * @param string $feature_key Feature key, e.g. 'feature_star_ratings'.
	 * @return bool
	 */
	public static function is_enabled( string $feature_key ): bool {
		$default = self::$defaults[ $feature_key ] ?? 1;

		if ( class_exists( 'BK_Settings' ) ) {
			return (bool) BK_Settings::get_setting( $feature_key, $default );
		}

		// Direct fallback when bk-pools-core is unavailable (should not happen
		// in production, but keeps the call safe).
		$settings = get_option( 'bk_pools_settings', array() );

		if ( isset( $settings[ $feature_key ] ) ) {
			return (bool) $settings[ $feature_key ];
		}

		return (bool) $default;
	}
}
