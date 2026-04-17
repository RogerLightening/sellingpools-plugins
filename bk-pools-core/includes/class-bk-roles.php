<?php
/**
 * BK Roles
 *
 * Registers and removes the custom WordPress roles used by the BK Pools platform.
 *
 * @package BK_Pools_Core
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BK_Roles
 *
 * Manages the two BK Pools custom roles:
 *  - bk_agent   — Pool installation agents (builders). Restricted WP access.
 *  - bk_manager — BK Pools platform managers. Editor-level WP access plus custom caps.
 *
 * @since 1.0.0
 */
class BK_Roles {

	/**
	 * Role slug for the agent role.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const ROLE_AGENT = 'bk_agent';

	/**
	 * Role slug for the manager role.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const ROLE_MANAGER = 'bk_manager';

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Creates the BK Pools custom roles.
	 *
	 * Called on plugin activation. Skips gracefully if roles already exist
	 * to avoid overwriting any capability changes made via third-party tools.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function create_roles(): void {
		self::create_agent_role();
		self::create_manager_role();
	}

	/**
	 * Removes the BK Pools custom roles.
	 *
	 * Called on plugin deactivation. Any users assigned to these roles will
	 * revert to the WordPress default "no role" state and should be reassigned
	 * before deactivation in a production environment.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function remove_roles(): void {
		remove_role( self::ROLE_AGENT );
		remove_role( self::ROLE_MANAGER );
	}

	// -------------------------------------------------------------------------
	// Private role builders
	// -------------------------------------------------------------------------

	/**
	 * Creates the bk_agent role.
	 *
	 * Agents have minimal WordPress capabilities — only enough to log in and
	 * interact with the BK Pools front-end application. They cannot access
	 * the WordPress admin dashboard.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private static function create_agent_role(): void {
		// Do not overwrite an existing role.
		if ( null !== get_role( self::ROLE_AGENT ) ) {
			return;
		}

		add_role(
			self::ROLE_AGENT,
			__( 'BK Agent', 'bk-pools-core' ),
			array(
				// WordPress core — minimum required for login.
				'read'               => true,

				// BK Pools custom capabilities.
				'bk_view_leads'      => true,  // View assigned leads.
				'bk_manage_leads'    => true,  // Update lead status, notes, rating.
				'bk_manage_pricing'  => true,  // Edit own pricing.
				'bk_manage_profile'  => true,  // Edit own agent profile.
			)
		);
	}

	/**
	 * Creates the bk_manager role.
	 *
	 * Managers receive all standard WordPress editor capabilities plus the
	 * full set of BK Pools custom capabilities needed to administer the platform.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private static function create_manager_role(): void {
		// Do not overwrite an existing role.
		if ( null !== get_role( self::ROLE_MANAGER ) ) {
			return;
		}

		// Start with the full WordPress editor capability set.
		$editor_role = get_role( 'editor' );
		$capabilities = $editor_role ? $editor_role->capabilities : array();

		// Merge in BK Pools custom capabilities.
		$bk_capabilities = array(
			'bk_view_leads'      => true,  // View all leads.
			'bk_manage_leads'    => true,  // Manage all leads.
			'bk_view_reports'    => true,  // View performance reports.
			'bk_manage_agents'   => true,  // Manage agent accounts.
			'bk_manage_settings' => true,  // Manage BK Pools settings.
		);

		add_role(
			self::ROLE_MANAGER,
			__( 'BK Pools Manager', 'bk-pools-core' ),
			array_merge( $capabilities, $bk_capabilities )
		);
	}
}
