<?php
/**
 * BK JFB Redirect
 *
 * Hooks into JetFormBuilder's after-send action to redirect the user to their
 * personalised estimate page after a successful form 1037 submission.
 *
 * Execution order within a single page-reload request:
 *  1. JFB runs the Insert Post action  → estimate post is created.
 *  2. JFB runs the Call Hook action    → BK_Matcher_Hooks::handle_jfb_call_hook()
 *                                        → BK_Matcher::match_agents_to_lead()
 *                                           writes estimate_url to post meta and
 *                                           fires bk_pools_agents_matched.
 *                                        → BK_Estimate_Generator::generate_estimate()
 *                                           builds PDFs and sends the customer email.
 *  3. jet-form-builder/form-handler/after-send fires  ← we hook here.
 *  4. JFB's Reload_Response calls wp_redirect( response_data['redirect'] ).
 *
 * @package BK_Agent_Matcher
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BK_JFB_Redirect
 *
 * Reads estimate_url from the newly created estimate post and injects it as
 * JFB's redirect target so the customer lands on their personalised estimate
 * page immediately after submitting the form.
 *
 * Falls back to:
 *  1. The 'estimate_thankyou_url' BK Settings value (if configured).
 *  2. The site home URL as a safe last resort.
 *
 * @since 1.0.0
 */
class BK_JFB_Redirect {

	/**
	 * Constructor — registers the JFB after-send hook.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action(
			'jet-form-builder/form-handler/after-send',
			array( $this, 'maybe_redirect' ),
			10,
			2
		);
	}

	// -------------------------------------------------------------------------
	// Hook callback
	// -------------------------------------------------------------------------

	/**
	 * Injects the estimate URL as the JFB redirect target.
	 *
	 * For Page Reload mode, JFB's Reload_Response reads
	 * $action_handler->response_data['redirect'] and passes it to wp_redirect().
	 * Setting it here (in after-send, after all actions have run) ensures the
	 * matcher and generator have both completed before we read the meta value.
	 *
	 * @since 1.0.0
	 *
	 * @param object $form_handler The JFB Form_Handler instance.
	 * @param bool   $is_success   Whether the form submission succeeded.
	 * @return void
	 */
	public function maybe_redirect( $form_handler, bool $is_success ): void {
		// Only act on our specific estimate request form.
		if ( (int) $form_handler->form_id !== BK_MATCHER_FORM_ID ) {
			return;
		}

		// Only redirect on successful submissions — let JFB show its own error
		// state for failed ones.
		if ( ! $is_success ) {
			return;
		}

		// Resolve the estimate post ID JFB inserted during this submission.
		$post_id = (int) ( $form_handler->action_handler->response_data['inserted_post_id'] ?? 0 );

		$redirect_url = '';

		if ( $post_id ) {
			// estimate_url was written by BK_Matcher::match_agents_to_lead()
			// via update_post_meta() during the Call Hook action above.
			// get_post_meta() is transparent to JetEngine custom meta table routing.
			$meta_url = get_post_meta( $post_id, 'estimate_url', true );

			if ( $meta_url && filter_var( $meta_url, FILTER_VALIDATE_URL ) ) {
				$redirect_url = $meta_url;
			} else {
				error_log( sprintf(
					'BK JFB Redirect — estimate_url not found or invalid for post %d (meta: %s). Falling back.',
					$post_id,
					var_export( $meta_url, true )
				) );
			}
		} else {
			error_log( 'BK JFB Redirect — no inserted_post_id in JFB response_data for form ' . BK_MATCHER_FORM_ID . '.' );
		}

		// Fallback 1: configured thank-you page.
		if ( ! $redirect_url && class_exists( 'BK_Settings' ) ) {
			$thankyou = (string) BK_Settings::get_setting( 'estimate_thankyou_url', '' );
			if ( $thankyou && filter_var( $thankyou, FILTER_VALIDATE_URL ) ) {
				$redirect_url = $thankyou;
			}
		}

		// Fallback 2: site home URL.
		if ( ! $redirect_url ) {
			$redirect_url = home_url( '/' );
		}

		/**
		 * Filters the final redirect URL after a successful estimate form submission.
		 *
		 * @since 1.0.0
		 *
		 * @param string $redirect_url The URL to redirect to.
		 * @param int    $post_id      The estimate post ID (0 if unavailable).
		 * @param object $form_handler The JFB Form_Handler instance.
		 */
		$redirect_url = (string) apply_filters(
			'bk_jfb_estimate_redirect_url',
			$redirect_url,
			$post_id,
			$form_handler
		);

		// Inject into JFB's response data. Reload_Response passes this value
		// directly to wp_redirect() when building the page-reload response.
		$form_handler->action_handler->response_data['redirect'] = esc_url_raw( $redirect_url );
	}
}
