<?php
/**
 * BK Settings
 *
 * Registers and renders the BK Pools admin settings page using the WordPress Settings API.
 *
 * @package BK_Pools_Core
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BK_Settings
 *
 * Provides the top-level "BK Pools → Settings" admin menu page.
 * All settings are stored as a single serialised array under the option
 * key 'bk_pools_settings'.
 *
 * Usage from other plugins:
 *   $vat = BK_Settings::get_setting( 'vat_rate', 0.15 );
 *
 * @since 1.0.0
 */
class BK_Settings {

	/**
	 * WordPress option key for the settings array.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const OPTION_KEY = 'bk_pools_settings';

	/**
	 * Default values for all settings.
	 *
	 * @since 1.0.0
	 * @var array<string, mixed>
	 */
	private static array $defaults = array(
		'vat_rate'                    => 0.15,
		'estimate_validity_days'      => 30,
		'stale_lead_days'             => 7,
		'company_name'                => 'BK Pools',
		'company_email'               => '',
		'company_phone'               => '',
		'company_logo_id'             => '',
		'reward_top_seller_discount'  => 0.05,
		'reward_milestone_10'         => 0,
		'reward_milestone_25'         => 0,
		'reward_milestone_50'         => 0,
		// Feature toggles — managed by bk-performance-tracker but stored here.
		'feature_top_agents'          => 1,
		'feature_star_ratings'        => 1,
		'feature_leaderboard'         => 1,
		'feature_rewards'             => 0,
		// Plugin updates via GitHub.
		'github_token'                => '',
	);

	// -------------------------------------------------------------------------
	// Bootstrap
	// -------------------------------------------------------------------------

	/**
	 * Registers all WordPress hooks for the settings page.
	 *
	 * Called from bk_pools_init() on the plugins_loaded hook.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( static::class, 'register_menu' ) );
		add_action( 'admin_init', array( static::class, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( static::class, 'enqueue_media_uploader' ) );
	}

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Retrieves a single setting value from the stored options array.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key     The setting key (e.g. 'vat_rate').
	 * @param mixed  $default Fallback value if the key is not found.
	 * @return mixed The setting value or $default.
	 */
	public static function get_setting( string $key, mixed $default = null ): mixed {
		$settings = get_option( self::OPTION_KEY, array() );

		if ( isset( $settings[ $key ] ) ) {
			return $settings[ $key ];
		}

		// Fall back to our own defaults before the caller's $default.
		if ( array_key_exists( $key, self::$defaults ) ) {
			return self::$defaults[ $key ];
		}

		return $default;
	}

	// -------------------------------------------------------------------------
	// Menu registration
	// -------------------------------------------------------------------------

	/**
	 * Registers the top-level BK Pools admin menu and the Settings sub-page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register_menu(): void {
		// Top-level menu item.
		add_menu_page(
			__( 'BK Pools', 'bk-pools-core' ),
			__( 'BK Pools', 'bk-pools-core' ),
			'manage_options',      // Checked separately; custom cap falls through for managers.
			'bk-pools',
			array( static::class, 'render_settings_page' ),
			'dashicons-swimming',
			58
		);

		// Settings sub-page (same callback as parent so clicking the top item loads settings).
		add_submenu_page(
			'bk-pools',
			__( 'BK Pools Settings', 'bk-pools-core' ),
			__( 'Settings', 'bk-pools-core' ),
			'manage_options',
			'bk-pools',
			array( static::class, 'render_settings_page' )
		);
	}

	// -------------------------------------------------------------------------
	// Settings API registration
	// -------------------------------------------------------------------------

	/**
	 * Registers settings sections and fields via the WordPress Settings API.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register_settings(): void {
		register_setting(
			'bk_pools_settings_group',
			self::OPTION_KEY,
			array(
				'sanitize_callback' => array( static::class, 'sanitise_settings' ),
				'default'           => self::$defaults,
			)
		);

		// -- Section: General --------------------------------------------------
		add_settings_section(
			'bk_pools_section_general',
			__( 'General Settings', 'bk-pools-core' ),
			'__return_false',
			'bk-pools-settings'
		);

		add_settings_field(
			'company_name',
			__( 'Company Name', 'bk-pools-core' ),
			array( static::class, 'field_text' ),
			'bk-pools-settings',
			'bk_pools_section_general',
			array( 'key' => 'company_name' )
		);

		add_settings_field(
			'company_email',
			__( 'Company Email', 'bk-pools-core' ),
			array( static::class, 'field_email' ),
			'bk-pools-settings',
			'bk_pools_section_general',
			array( 'key' => 'company_email' )
		);

		add_settings_field(
			'company_phone',
			__( 'Company Phone', 'bk-pools-core' ),
			array( static::class, 'field_tel' ),
			'bk-pools-settings',
			'bk_pools_section_general',
			array( 'key' => 'company_phone' )
		);

		add_settings_field(
			'company_logo_id',
			__( 'Company Logo', 'bk-pools-core' ),
			array( static::class, 'field_media' ),
			'bk-pools-settings',
			'bk_pools_section_general',
			array( 'key' => 'company_logo_id' )
		);

		// -- Section: Estimates & Leads ----------------------------------------
		add_settings_section(
			'bk_pools_section_estimates',
			__( 'Estimates & Leads', 'bk-pools-core' ),
			'__return_false',
			'bk-pools-settings'
		);

		add_settings_field(
			'vat_rate',
			__( 'VAT Rate', 'bk-pools-core' ),
			array( static::class, 'field_number' ),
			'bk-pools-settings',
			'bk_pools_section_estimates',
			array(
				'key'         => 'vat_rate',
				'step'        => '0.01',
				'min'         => '0',
				'max'         => '1',
				'description' => __( 'Decimal, e.g. 0.15 = 15% VAT.', 'bk-pools-core' ),
			)
		);

		add_settings_field(
			'estimate_validity_days',
			__( 'Estimate Validity (days)', 'bk-pools-core' ),
			array( static::class, 'field_number' ),
			'bk-pools-settings',
			'bk_pools_section_estimates',
			array(
				'key'         => 'estimate_validity_days',
				'step'        => '1',
				'min'         => '1',
				'description' => __( 'Number of days before an estimate expires.', 'bk-pools-core' ),
			)
		);

		add_settings_field(
			'stale_lead_days',
			__( 'Stale Lead Threshold (days)', 'bk-pools-core' ),
			array( static::class, 'field_number' ),
			'bk-pools-settings',
			'bk_pools_section_estimates',
			array(
				'key'         => 'stale_lead_days',
				'step'        => '1',
				'min'         => '1',
				'description' => __( 'Days before a "new" lead is flagged as "stale".', 'bk-pools-core' ),
			)
		);

		// -- Section: Feature Toggles -----------------------------------------
		add_settings_section(
			'bk_pools_section_feature_toggles',
			__( 'Feature Toggles', 'bk-pools-core' ),
			array( static::class, 'render_feature_toggles_intro' ),
			'bk-pools-settings'
		);

		add_settings_field(
			'feature_top_agents',
			__( 'Top Agents Dashboard Section', 'bk-pools-core' ),
			array( static::class, 'field_checkbox' ),
			'bk-pools-settings',
			'bk_pools_section_feature_toggles',
			array(
				'key'         => 'feature_top_agents',
				'description' => __( 'Show the "Top Agents This Month" card on the agent dashboard.', 'bk-pools-core' ),
			)
		);

		add_settings_field(
			'feature_star_ratings',
			__( 'Lead Star Ratings', 'bk-pools-core' ),
			array( static::class, 'field_checkbox' ),
			'bk-pools-settings',
			'bk_pools_section_feature_toggles',
			array(
				'key'         => 'feature_star_ratings',
				'description' => __( 'Enable 1–5 star lead quality ratings on the leads list.', 'bk-pools-core' ),
			)
		);

		add_settings_field(
			'feature_leaderboard',
			__( 'Monthly Leaderboard', 'bk-pools-core' ),
			array( static::class, 'field_checkbox' ),
			'bk-pools-settings',
			'bk_pools_section_feature_toggles',
			array(
				'key'         => 'feature_leaderboard',
				'description' => __( 'Show the monthly leaderboard ranking on the agent dashboard.', 'bk-pools-core' ),
			)
		);

		add_settings_field(
			'feature_rewards',
			__( 'Reward System', 'bk-pools-core' ),
			array( static::class, 'field_checkbox' ),
			'bk-pools-settings',
			'bk_pools_section_feature_toggles',
			array(
				'key'         => 'feature_rewards',
				'description' => __( 'Enable reward system and display reward messaging to agents.', 'bk-pools-core' ),
				'id'          => 'feature_rewards',
			)
		);

		// -- Section: Plugin Updates ------------------------------------------
		add_settings_section(
			'bk_pools_section_updates',
			__( 'Plugin Updates', 'bk-pools-core' ),
			array( static::class, 'render_updates_intro' ),
			'bk-pools-settings'
		);

		add_settings_field(
			'github_token',
			__( 'GitHub Access Token', 'bk-pools-core' ),
			array( static::class, 'field_password' ),
			'bk-pools-settings',
			'bk_pools_section_updates',
			array(
				'key'         => 'github_token',
				'description' => __( 'Personal access token (classic) with repo scope. Required for WordPress to check for and download plugin updates from the private GitHub repository.', 'bk-pools-core' ),
			)
		);

		// -- Section: Agent Rewards --------------------------------------------
		add_settings_section(
			'bk_pools_section_rewards',
			__( 'Agent Rewards', 'bk-pools-core' ),
			'__return_false',
			'bk-pools-settings'
		);

		add_settings_field(
			'reward_top_seller_discount',
			__( 'Top Seller Discount Rate', 'bk-pools-core' ),
			array( static::class, 'field_number' ),
			'bk-pools-settings',
			'bk_pools_section_rewards',
			array(
				'key'         => 'reward_top_seller_discount',
				'step'        => '0.01',
				'min'         => '0',
				'max'         => '1',
				'description' => __( 'Decimal, e.g. 0.05 = 5% discount for top seller.', 'bk-pools-core' ),
			)
		);

		add_settings_field(
			'reward_milestone_10',
			__( 'Milestone Bonus — 10 Sales (R)', 'bk-pools-core' ),
			array( static::class, 'field_number' ),
			'bk-pools-settings',
			'bk_pools_section_rewards',
			array(
				'key'         => 'reward_milestone_10',
				'step'        => '1',
				'min'         => '0',
				'description' => __( 'Bonus amount (Rand) awarded at 10 lifetime sales.', 'bk-pools-core' ),
			)
		);

		add_settings_field(
			'reward_milestone_25',
			__( 'Milestone Bonus — 25 Sales (R)', 'bk-pools-core' ),
			array( static::class, 'field_number' ),
			'bk-pools-settings',
			'bk_pools_section_rewards',
			array(
				'key'         => 'reward_milestone_25',
				'step'        => '1',
				'min'         => '0',
				'description' => __( 'Bonus amount (Rand) awarded at 25 lifetime sales.', 'bk-pools-core' ),
			)
		);

		add_settings_field(
			'reward_milestone_50',
			__( 'Milestone Bonus — 50 Sales (R)', 'bk-pools-core' ),
			array( static::class, 'field_number' ),
			'bk-pools-settings',
			'bk_pools_section_rewards',
			array(
				'key'         => 'reward_milestone_50',
				'step'        => '1',
				'min'         => '0',
				'description' => __( 'Bonus amount (Rand) awarded at 50 lifetime sales.', 'bk-pools-core' ),
			)
		);
	}

	// -------------------------------------------------------------------------
	// Field renderers
	// -------------------------------------------------------------------------

	/**
	 * Renders a text input field.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $args Field arguments passed from add_settings_field().
	 * @return void
	 */
	public static function field_text( array $args ): void {
		$key   = $args['key'];
		$value = self::get_setting( $key, '' );
		printf(
			'<input type="text" id="%1$s" name="%2$s[%1$s]" value="%3$s" class="regular-text" />',
			esc_attr( $key ),
			esc_attr( self::OPTION_KEY ),
			esc_attr( $value )
		);
		self::render_description( $args );
	}

	/**
	 * Renders an email input field.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $args Field arguments.
	 * @return void
	 */
	public static function field_email( array $args ): void {
		$key   = $args['key'];
		$value = self::get_setting( $key, '' );
		printf(
			'<input type="email" id="%1$s" name="%2$s[%1$s]" value="%3$s" class="regular-text" />',
			esc_attr( $key ),
			esc_attr( self::OPTION_KEY ),
			esc_attr( $value )
		);
		self::render_description( $args );
	}

	/**
	 * Renders a telephone input field.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $args Field arguments.
	 * @return void
	 */
	public static function field_tel( array $args ): void {
		$key   = $args['key'];
		$value = self::get_setting( $key, '' );
		printf(
			'<input type="tel" id="%1$s" name="%2$s[%1$s]" value="%3$s" class="regular-text" />',
			esc_attr( $key ),
			esc_attr( self::OPTION_KEY ),
			esc_attr( $value )
		);
		self::render_description( $args );
	}

	/**
	 * Renders a number input field.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $args Field arguments. Supports 'step', 'min', 'max', 'description'.
	 * @return void
	 */
	public static function field_number( array $args ): void {
		$key   = $args['key'];
		$value = self::get_setting( $key, 0 );
		$step  = isset( $args['step'] ) ? esc_attr( $args['step'] ) : '1';
		$min   = isset( $args['min'] ) ? ' min="' . esc_attr( $args['min'] ) . '"' : '';
		$max   = isset( $args['max'] ) ? ' max="' . esc_attr( $args['max'] ) . '"' : '';

		printf(
			'<input type="number" id="%1$s" name="%2$s[%1$s]" value="%3$s" step="%4$s"%5$s%6$s class="small-text" />',
			esc_attr( $key ),
			esc_attr( self::OPTION_KEY ),
			esc_attr( (string) $value ),
			$step,
			$min,
			$max
		);
		self::render_description( $args );
	}

	/**
	 * Renders a checkbox (toggle) field.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $args Field arguments. Uses 'key' and 'description'.
	 * @return void
	 */
	public static function field_checkbox( array $args ): void {
		$key     = $args['key'];
		$checked = (bool) self::get_setting( $key, 0 );
		printf(
			'<label><input type="checkbox" id="%1$s" name="%2$s[%1$s]" value="1"%3$s /> %4$s</label>',
			esc_attr( $key ),
			esc_attr( self::OPTION_KEY ),
			checked( $checked, true, false ),
			isset( $args['description'] ) ? esc_html( $args['description'] ) : ''
		);
	}

	/**
	 * Renders the introductory text for the Plugin Updates section.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public static function render_updates_intro(): void {
		echo '<p class="description">';
		esc_html_e( 'Configure the GitHub token so WordPress can check for plugin updates from the private sellingpools-plugins repository.', 'bk-pools-core' );
		echo '</p>';
	}

	/**
	 * Renders a password input field (value is masked in the browser).
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $args Field arguments.
	 * @return void
	 */
	public static function field_password( array $args ): void {
		$key   = $args['key'];
		$value = self::get_setting( $key, '' );
		printf(
			'<input type="password" id="%1$s" name="%2$s[%1$s]" value="%3$s" class="regular-text" autocomplete="new-password" />',
			esc_attr( $key ),
			esc_attr( self::OPTION_KEY ),
			esc_attr( $value )
		);
		self::render_description( $args );
	}

	/**
	 * Renders the introductory text for the Feature Toggles section and enqueues
	 * the inline JS that shows/hides the reward fields based on the toggle state.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function render_feature_toggles_intro(): void {
		echo '<p class="description">';
		esc_html_e( 'Control which features are visible to agents in their dashboard.', 'bk-pools-core' );
		echo '</p>';

		// Inline JS — show/hide the Agent Rewards section based on feature_rewards checkbox.
		?>
		<script>
		( function () {
			function toggleRewards() {
				var cb        = document.getElementById( 'feature_rewards' );
				var rewardsTbl = document.querySelector( '#bk_pools_section_rewards + table, h2 + table, .bk-rewards-section' );

				// Walk the DOM: find all rows in the Agent Rewards section.
				var headings = document.querySelectorAll( '.wrap h2' );
				var rewardsHeading = null;
				headings.forEach( function ( h ) {
					if ( h.textContent.trim().indexOf( 'Agent Rewards' ) !== -1 ) {
						rewardsHeading = h;
					}
				} );

				if ( ! cb || ! rewardsHeading ) { return; }

				var show = cb.checked;
				var el   = rewardsHeading.nextElementSibling;
				// Stop at the next section heading OR the submit button paragraph
				// so we never accidentally hide the Save Settings button.
				while ( el && el.tagName !== 'H2' && ! el.classList.contains( 'submit' ) ) {
					el.style.display = show ? '' : 'none';
					el = el.nextElementSibling;
				}
				// Also toggle the heading itself.
				rewardsHeading.style.display = show ? '' : 'none';
			}

			document.addEventListener( 'DOMContentLoaded', function () {
				var cb = document.getElementById( 'feature_rewards' );
				if ( cb ) {
					toggleRewards();
					cb.addEventListener( 'change', toggleRewards );
				}
			} );
		}() );
		</script>
		<?php
	}

	/**
	 * Renders a WordPress media picker field for logo/image selection.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $args Field arguments.
	 * @return void
	 */
	public static function field_media( array $args ): void {
		$key        = $args['key'];
		$attachment_id = (int) self::get_setting( $key, 0 );
		$image_url  = $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'thumbnail' ) : '';

		?>
		<div class="bk-media-field">
			<input
				type="hidden"
				id="<?php echo esc_attr( $key ); ?>"
				name="<?php echo esc_attr( self::OPTION_KEY ); ?>[<?php echo esc_attr( $key ); ?>]"
				value="<?php echo esc_attr( (string) $attachment_id ); ?>"
			/>
			<div class="bk-media-preview" style="margin-bottom: 8px;">
				<?php if ( $image_url ) : ?>
					<img src="<?php echo esc_url( $image_url ); ?>" alt="" style="max-width:150px;height:auto;display:block;" />
				<?php endif; ?>
			</div>
			<button type="button" class="button bk-media-upload" data-target="<?php echo esc_attr( $key ); ?>">
				<?php esc_html_e( 'Select Image', 'bk-pools-core' ); ?>
			</button>
			<?php if ( $attachment_id ) : ?>
				<button type="button" class="button bk-media-remove" data-target="<?php echo esc_attr( $key ); ?>" style="margin-left:4px;">
					<?php esc_html_e( 'Remove', 'bk-pools-core' ); ?>
				</button>
			<?php endif; ?>
		</div>
		<?php
		self::render_description( $args );
	}

	// -------------------------------------------------------------------------
	// Sanitisation
	// -------------------------------------------------------------------------

	/**
	 * Sanitises all settings values before they are stored in the database.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|mixed $input Raw input from the settings form.
	 * @return array<string, mixed> Sanitised settings array.
	 */
	public static function sanitise_settings( mixed $input ): array {
		if ( ! is_array( $input ) ) {
			return self::$defaults;
		}

		// Capability check — only managers or admins may save settings.
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'bk_manage_settings' ) ) {
			add_settings_error(
				self::OPTION_KEY,
				'bk_pools_permission_denied',
				__( 'You do not have permission to change BK Pools settings.', 'bk-pools-core' ),
				'error'
			);
			return (array) get_option( self::OPTION_KEY, self::$defaults );
		}

		$sanitised = array();

		// Floats.
		$float_keys = array( 'vat_rate', 'reward_top_seller_discount' );
		foreach ( $float_keys as $key ) {
			$sanitised[ $key ] = isset( $input[ $key ] )
				? (float) $input[ $key ]
				: self::$defaults[ $key ];
		}

		// Integers.
		$int_keys = array(
			'estimate_validity_days',
			'stale_lead_days',
			'company_logo_id',
			'reward_milestone_10',
			'reward_milestone_25',
			'reward_milestone_50',
		);
		foreach ( $int_keys as $key ) {
			$sanitised[ $key ] = isset( $input[ $key ] )
				? absint( $input[ $key ] )
				: self::$defaults[ $key ];
		}

		// Text strings.
		$sanitised['company_name'] = isset( $input['company_name'] )
			? sanitize_text_field( $input['company_name'] )
			: self::$defaults['company_name'];

		// Email.
		$sanitised['company_email'] = isset( $input['company_email'] )
			? sanitize_email( $input['company_email'] )
			: '';

		// Phone — strip non-numeric except leading +.
		$sanitised['company_phone'] = isset( $input['company_phone'] )
			? BK_Helpers::sanitise_phone( $input['company_phone'] )
			: '';

		// GitHub token — store as-is (sanitize_text_field strips non-printable chars).
		$sanitised['github_token'] = isset( $input['github_token'] )
			? sanitize_text_field( $input['github_token'] )
			: '';

		// Feature toggles — checkboxes are absent from $_POST when unchecked.
		$checkbox_keys = array(
			'feature_top_agents',
			'feature_star_ratings',
			'feature_leaderboard',
			'feature_rewards',
		);
		foreach ( $checkbox_keys as $key ) {
			$sanitised[ $key ] = isset( $input[ $key ] ) ? 1 : 0;
		}

		return $sanitised;
	}

	// -------------------------------------------------------------------------
	// Page renderer
	// -------------------------------------------------------------------------

	/**
	 * Renders the BK Pools Settings admin page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function render_settings_page(): void {
		// Capability check.
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'bk_manage_settings' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'bk-pools-core' ) );
		}
		?>
		<div class="wrap bk-pools-settings-wrap">
			<h1 class="bk-pools-settings-title">
				<span class="dashicons dashicons-swimming" aria-hidden="true"></span>
				<?php esc_html_e( 'BK Pools Settings', 'bk-pools-core' ); ?>
			</h1>

			<?php settings_errors( self::OPTION_KEY ); ?>

			<form method="post" action="options.php" novalidate="novalidate">
				<?php
				settings_fields( 'bk_pools_settings_group' );
				do_settings_sections( 'bk-pools-settings' );
				submit_button( __( 'Save Settings', 'bk-pools-core' ) );
				?>
			</form>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Assets
	// -------------------------------------------------------------------------

	/**
	 * Enqueues the WordPress media uploader on BK Pools settings pages.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public static function enqueue_media_uploader( string $hook_suffix ): void {
		if ( ! str_contains( $hook_suffix, 'bk-pools' ) ) {
			return;
		}

		wp_enqueue_media();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Outputs an optional description paragraph beneath a settings field.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $args Field arguments; may contain a 'description' key.
	 * @return void
	 */
	private static function render_description( array $args ): void {
		if ( ! empty( $args['description'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
		}
	}
}
