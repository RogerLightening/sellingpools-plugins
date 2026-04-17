<?php
/**
 * BK Matcher Hooks
 *
 * Integrates the agent matcher with JetFormBuilder's form submission pipeline
 * via a Call Hook action, and provides a save_post fallback for estimates
 * created outside of JetFormBuilder (e.g. WP admin manual saves).
 *
 * @package BK_Agent_Matcher
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BK_Matcher_Hooks
 *
 * Registers two hooks:
 *
 *  1. Primary — jet-form-builder/custom-action/bk_estimate_inserted
 *     Triggered by a JFB "Call Hook" action (hook name: bk_estimate_inserted)
 *     placed after the Insert Post action in form 1037. JFB processes actions
 *     sequentially; by the time the Call Hook action runs, Post_Meta_Property
 *     has already called update_post_meta() for all form fields, so suburb_id
 *     and all other estimate meta are committed to wp_estimate_meta.
 *     Arguments: ( $request, $action_handler )
 *
 *  2. Fallback — save_post_estimate (priority 99)
 *     Catches estimate posts created outside JetFormBuilder (e.g. manual WP
 *     admin saves) where meta IS committed before save_post fires. The
 *     _bk_agents_matched flag prevents double execution if the primary hook
 *     already ran.
 *
 * @since 1.0.0
 */
class BK_Matcher_Hooks {

	/**
	 * Constructor — registers all WordPress hooks.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Primary: JFB Call Hook action fires after Insert Post + meta write.
		add_action(
			'jet-form-builder/custom-action/bk_estimate_inserted',
			array( $this, 'handle_jfb_call_hook' ),
			10,
			2
		);

		// Fallback: for estimates created outside JetFormBuilder.
		add_action(
			'save_post_estimate',
			array( $this, 'handle_save_post' ),
			99,
			3
		);
	}

	// -------------------------------------------------------------------------
	// Hook callbacks
	// -------------------------------------------------------------------------

	/**
	 * Primary JetFormBuilder handler — fired by the Call Hook action in form 1037.
	 *
	 * By the time this runs, JFB's Post_Meta_Property::do_after() has already
	 * called update_post_meta() for all form fields, committing suburb_id and
	 * all other estimate meta to JetEngine's custom meta table.
	 *
	 * @since 1.0.0
	 *
	 * @param array                                              $request        Submitted form data.
	 * @param \Jet_Form_Builder\Actions\Action_Handler           $action_handler JFB action handler.
	 * @return void
	 */
	public function handle_jfb_call_hook( array $request, $action_handler ): void {
		$post_id = (int) ( $action_handler->response_data['inserted_post_id'] ?? 0 );

		if ( ! $post_id ) {
			error_log( 'BK Matcher [call-hook] — could not determine inserted post ID.' );
			return;
		}

		if ( 'estimate' !== get_post_type( $post_id ) ) {
			return;
		}

		$result = BK_Matcher::match_agents_to_lead( $post_id );

		if ( is_wp_error( $result ) ) {
			error_log( sprintf(
				'BK Matcher [call-hook] — estimate %d: [%s] %s',
				$post_id,
				$result->get_error_code(),
				$result->get_error_message()
			) );
		}
	}

	/**
	 * Fallback save_post handler for estimate posts created outside JetFormBuilder.
	 *
	 * Guards against:
	 *  - WordPress autosaves.
	 *  - Post revisions.
	 *  - Double execution via the _bk_agents_matched flag.
	 *  - Posts where suburb_id is not yet present (not a JFB submission).
	 *
	 * @since 1.0.0
	 *
	 * @param int      $post_id The post ID being saved.
	 * @param \WP_Post $post    The post object.
	 * @param bool     $update  True if updating an existing post, false for a new post.
	 * @return void
	 */
	public function handle_save_post( int $post_id, \WP_Post $post, bool $update ): void {
		// Bail on autosaves.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Bail on revisions.
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Bail if already matched — primary JFB hook already ran.
		if ( get_post_meta( $post_id, '_bk_agents_matched', true ) ) {
			return;
		}

		// Bail if suburb_id is not present — meta isn't ready (JFB path) or
		// not a valid estimate (manual save without required fields).
		if ( ! get_post_meta( $post_id, 'suburb_id', true ) ) {
			return;
		}

		$result = BK_Matcher::match_agents_to_lead( $post_id );

		if ( is_wp_error( $result ) ) {
			if ( 'already_matched' !== $result->get_error_code() ) {
				error_log( sprintf(
					'BK Matcher [save_post] — estimate %d: [%s] %s',
					$post_id,
					$result->get_error_code(),
					$result->get_error_message()
				) );
			}
		}
	}
}
