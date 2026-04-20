<?php
/**
 * BK Agent Auth
 *
 * Handles login redirection, admin access blocking, and agent identity
 * resolution for the BK Agent Panel.
 *
 * @package BK_Agent_Panel
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BK_Agent_Auth
 *
 * @since 1.0.0
 */
class BK_Agent_Auth {

	/**
	 * In-process cache: user_id → agent post ID.
	 *
	 * @since 1.0.0
	 * @var array<int, int|false>
	 */
	private static array $agent_post_cache = array();

	/**
	 * Constructor — registers WordPress hooks.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Redirect agents away from wp-admin.
		add_action( 'admin_init', array( $this, 'block_admin_access' ) );

		// Redirect agents to the panel after login.
		add_action( 'wp_login', array( $this, 'redirect_after_login' ), 10, 2 );
	}

	// -------------------------------------------------------------------------
	// Public static API
	// -------------------------------------------------------------------------

	/**
	 * Returns the builder CPT post ID linked to a given WP user.
	 *
	 * Queries wp_builders_meta WHERE linked_user_id = $user_id.
	 * Result is cached per-request in a static array.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id WP user ID. Defaults to the current user.
	 * @return int|false Builder post ID, or false if not found.
	 */
	public static function get_agent_post_id( int $user_id = 0 ): int|false {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id ) {
			return false;
		}

		if ( array_key_exists( $user_id, self::$agent_post_cache ) ) {
			return self::$agent_post_cache[ $user_id ];
		}

		global $wpdb;

		// linked_user_id column is text type in JetEngine — compare as string.
		$builders_meta = $wpdb->prefix . 'builders_meta';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$object_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT object_ID FROM `{$builders_meta}` WHERE linked_user_id = %s LIMIT 1",
				(string) $user_id
			)
		);

		$result = $object_id ? (int) $object_id : false;

		self::$agent_post_cache[ $user_id ] = $result;

		return $result;
	}

	/**
	 * Checks whether a WP user has the bk_agent role.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id WP user ID. Defaults to the current user.
	 * @return bool True if the user has the bk_agent role.
	 */
	public static function is_agent( int $user_id = 0 ): bool {
		$user = $user_id ? get_user_by( 'id', $user_id ) : wp_get_current_user();

		if ( ! $user || ! $user->ID ) {
			return false;
		}

		return in_array( BK_Roles::ROLE_AGENT, (array) $user->roles, true );
	}

	/**
	 * Returns the URL of the agent panel page.
	 *
	 * @since 1.0.0
	 * @return string Panel page URL, or home URL as fallback.
	 */
	public static function get_panel_url(): string {
		$page_id = (int) get_option( 'bk_agent_panel_page_id' );

		if ( $page_id ) {
			return (string) get_permalink( $page_id );
		}

		return home_url( '/agent-dashboard/' );
	}

	/**
	 * Performs a full access check for the panel.
	 *
	 * @since 1.0.0
	 * @return array{ok: bool, error: string, agent_post_id: int}
	 */
	public static function check_access(): array {
		if ( ! is_user_logged_in() ) {
			error_log( 'BK Panel auth: not logged in' );
			return array(
				'ok'            => false,
				'error'         => 'not_logged_in',
				'agent_post_id' => 0,
			);
		}

		$user_id  = get_current_user_id();
		$is_agent = current_user_can( 'bk_view_leads' );
		$is_admin = current_user_can( 'manage_options' );

		error_log( 'BK Panel auth: user_id=' . $user_id . ' bk_view_leads=' . ( $is_agent ? '1' : '0' ) . ' manage_options=' . ( $is_admin ? '1' : '0' ) );

		// Admins get full access so they can preview / support the panel,
		// even if they don't have the bk_view_leads capability or a linked
		// agent profile.
		if ( ! $is_agent && ! $is_admin ) {
			error_log( 'BK Panel auth: denied — no capability' );
			return array(
				'ok'            => false,
				'error'         => 'no_capability',
				'agent_post_id' => 0,
			);
		}

		$agent_post_id = self::get_agent_post_id();
		error_log( 'BK Panel auth: agent_post_id=' . (int) $agent_post_id );

		if ( ! $agent_post_id ) {
			if ( $is_admin ) {
				error_log( 'BK Panel auth: admin without agent profile — allowed through' );
				return array(
					'ok'            => true,
					'error'         => '',
					'agent_post_id' => 0,
				);
			}
			error_log( 'BK Panel auth: denied — no agent profile' );
			return array(
				'ok'            => false,
				'error'         => 'no_profile',
				'agent_post_id' => 0,
			);
		}

		error_log( 'BK Panel auth: access granted' );
		return array(
			'ok'            => true,
			'error'         => '',
			'agent_post_id' => $agent_post_id,
		);
	}

	// -------------------------------------------------------------------------
	// Hook callbacks
	// -------------------------------------------------------------------------

	/**
	 * Prevents bk_agent users from accessing wp-admin.
	 *
	 * Allows AJAX requests through (admin-ajax.php) so panel endpoints work.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function block_admin_access(): void {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}

		if ( ! self::is_agent() ) {
			return;
		}

		wp_safe_redirect( self::get_panel_url() );
		exit;
	}

	/**
	 * Redirects bk_agent users to the panel after login instead of wp-admin.
	 *
	 * @since 1.0.0
	 *
	 * @param string  $user_login Username.
	 * @param WP_User $user       The logged-in user object.
	 * @return void
	 */
	public function redirect_after_login( string $user_login, WP_User $user ): void {
		if ( ! in_array( BK_Roles::ROLE_AGENT, (array) $user->roles, true ) ) {
			return;
		}

		wp_safe_redirect( self::get_panel_url() );
		exit;
	}
}
