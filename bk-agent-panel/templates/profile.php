<?php
/**
 * Profile & settings template.
 *
 * @var int    $agent_post_id Builder CPT post ID.
 * @var string $panel_url     Panel page permalink.
 *
 * @package BK_Agent_Panel
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$profile = BK_Agent_Profile::get_profile( $agent_post_id );
?>

<div class="bk-section-header">
	<h1 class="bk-section-title"><?php esc_html_e( 'Profile & Settings', 'bk-agent-panel' ); ?></h1>
</div>

<form class="bk-profile-form" id="bk-profile-form" enctype="multipart/form-data">

	<!-- =====================================================
	     Company Information
	     ===================================================== -->
	<div class="bk-panel-card">
		<div class="bk-panel-card__header">
			<h2 class="bk-panel-card__title"><?php esc_html_e( 'Company Information', 'bk-agent-panel' ); ?></h2>
		</div>
		<div class="bk-panel-card__body bk-form-grid">

			<div class="bk-form-group bk-form-group--full">
				<label class="bk-form-label" for="bk-company-name">
					<?php esc_html_e( 'Company Name', 'bk-agent-panel' ); ?>
				</label>
				<input
					id="bk-company-name"
					type="text"
					name="company_name"
					class="bk-form-input"
					value="<?php echo esc_attr( $profile['company_name'] ); ?>"
					required
				>
			</div>

			<div class="bk-form-group bk-form-group--full">
				<label class="bk-form-label"><?php esc_html_e( 'Company Logo', 'bk-agent-panel' ); ?></label>
				<div class="bk-logo-upload" id="bk-logo-upload">
					<?php if ( $profile['company_logo_url'] ) : ?>
						<img
							src="<?php echo esc_url( $profile['company_logo_url'] ); ?>"
							alt="<?php esc_attr_e( 'Company logo', 'bk-agent-panel' ); ?>"
							class="bk-logo-preview"
							id="bk-logo-preview"
						>
					<?php else : ?>
						<div class="bk-logo-placeholder" id="bk-logo-preview">
							<?php esc_html_e( 'No logo uploaded', 'bk-agent-panel' ); ?>
						</div>
					<?php endif; ?>
					<div class="bk-logo-upload__actions">
						<label class="bk-btn bk-btn--secondary bk-btn--sm" for="bk-logo-file">
							<?php esc_html_e( 'Upload Logo', 'bk-agent-panel' ); ?>
						</label>
						<input type="file" id="bk-logo-file" name="logo" accept="image/*" class="bk-sr-only" data-bk-logo-upload>
						<?php if ( $profile['company_logo_url'] ) : ?>
							<button type="button" class="bk-btn bk-btn--ghost bk-btn--sm" data-bk-remove-logo>
								<?php esc_html_e( 'Remove', 'bk-agent-panel' ); ?>
							</button>
						<?php endif; ?>
					</div>
					<p class="bk-help-text"><?php esc_html_e( 'PNG or JPG, max 2 MB. Appears on customer estimates.', 'bk-agent-panel' ); ?></p>
				</div>
			</div>

			<div class="bk-form-group">
				<label class="bk-form-label" for="bk-contact-name">
					<?php esc_html_e( 'Contact Name', 'bk-agent-panel' ); ?>
				</label>
				<input
					id="bk-contact-name"
					type="text"
					name="contact_name"
					class="bk-form-input"
					value="<?php echo esc_attr( $profile['contact_name'] ); ?>"
				>
			</div>

			<div class="bk-form-group">
				<label class="bk-form-label" for="bk-email">
					<?php esc_html_e( 'Contact Email', 'bk-agent-panel' ); ?>
				</label>
				<input
					id="bk-email"
					type="email"
					name="email"
					class="bk-form-input"
					value="<?php echo esc_attr( $profile['email'] ); ?>"
				>
			</div>

			<div class="bk-form-group">
				<label class="bk-form-label" for="bk-phone">
					<?php esc_html_e( 'Contact Phone', 'bk-agent-panel' ); ?>
				</label>
				<input
					id="bk-phone"
					type="tel"
					name="phone"
					class="bk-form-input"
					value="<?php echo esc_attr( $profile['phone'] ); ?>"
				>
			</div>

		</div>
	</div>

	<!-- =====================================================
	     Location
	     ===================================================== -->
	<div class="bk-panel-card">
		<div class="bk-panel-card__header">
			<h2 class="bk-panel-card__title"><?php esc_html_e( 'Location', 'bk-agent-panel' ); ?></h2>
		</div>
		<div class="bk-panel-card__body bk-form-grid">

			<div class="bk-form-group bk-form-group--full">
				<label class="bk-form-label" for="bk-physical-address">
					<?php esc_html_e( 'Physical Address', 'bk-agent-panel' ); ?>
				</label>
				<input
					id="bk-physical-address"
					type="text"
					name="physical_address"
					class="bk-form-input"
					value="<?php echo esc_attr( $profile['physical_address'] ); ?>"
				>
			</div>

			<div class="bk-form-group">
				<label class="bk-form-label" for="bk-suburb-search">
					<?php esc_html_e( 'Base Suburb', 'bk-agent-panel' ); ?>
				</label>
				<input
					id="bk-suburb-search"
					type="text"
					class="bk-form-input"
					value="<?php echo esc_attr( $profile['suburb_name'] . ( ! empty( $profile['suburb_area'] ) ? ', ' . $profile['suburb_area'] : '' ) ); ?>"
					data-bk-suburb-autocomplete="true"
					data-bk-target-id="suburb_id"
					data-bk-target-province="province"
					placeholder="<?php esc_attr_e( 'Search suburb…', 'bk-agent-panel' ); ?>"
					autocomplete="off"
				>
				<input
					type="hidden"
					name="suburb_id"
					id="bk-suburb-id"
					value="<?php echo esc_attr( $profile['suburb_id'] ); ?>"
				>
				<p class="bk-help-text"><?php esc_html_e( 'Distance to customers is calculated from this suburb.', 'bk-agent-panel' ); ?></p>
			</div>

			<div class="bk-form-group">
				<label class="bk-form-label" for="bk-province">
					<?php esc_html_e( 'Province', 'bk-agent-panel' ); ?>
				</label>
				<input
					id="bk-province"
					type="text"
					name="province"
					class="bk-form-input"
					value="<?php echo esc_attr( $profile['province'] ); ?>"
					readonly
					placeholder="<?php esc_attr_e( 'Auto-filled from suburb', 'bk-agent-panel' ); ?>"
				>
			</div>

		</div>
	</div>

	<!-- =====================================================
	     Travel Fee Settings
	     ===================================================== -->
	<div class="bk-panel-card">
		<div class="bk-panel-card__header">
			<h2 class="bk-panel-card__title"><?php esc_html_e( 'Travel Fee Settings', 'bk-agent-panel' ); ?></h2>
		</div>
		<div class="bk-panel-card__body bk-form-grid">

			<div class="bk-form-group bk-form-group--full">
				<label class="bk-toggle bk-toggle--labelled">
					<input
						type="checkbox"
						name="travel_fee_enabled"
						id="bk-travel-fee-enabled"
						class="bk-toggle__input"
						value="1"
						data-bk-travel-toggle
						<?php checked( $profile['travel_fee_enabled'] ); ?>
					>
					<span class="bk-toggle__slider"></span>
					<span class="bk-toggle__label"><?php esc_html_e( 'Charge travel fee', 'bk-agent-panel' ); ?></span>
				</label>
			</div>

			<div class="bk-travel-fee-fields<?php echo ! $profile['travel_fee_enabled'] ? ' bk-hidden' : ''; ?>" id="bk-travel-fee-fields">

				<div class="bk-form-group">
					<label class="bk-form-label" for="bk-min-free-distance">
						<?php esc_html_e( 'Free Distance (km)', 'bk-agent-panel' ); ?>
					</label>
					<input
						id="bk-min-free-distance"
						type="number"
						name="travel_fee_min_distance_km"
						class="bk-form-input"
						value="<?php echo esc_attr( $profile['travel_fee_min_distance_km'] ); ?>"
						min="0"
						step="1"
						placeholder="50"
					>
					<p class="bk-help-text"><?php esc_html_e( 'No travel fee charged within this distance.', 'bk-agent-panel' ); ?></p>
				</div>

				<div class="bk-form-group">
					<label class="bk-form-label" for="bk-fee-type">
						<?php esc_html_e( 'Fee Type', 'bk-agent-panel' ); ?>
					</label>
					<select id="bk-fee-type" name="travel_fee_type" class="bk-form-select" data-bk-fee-type>
						<option value="per_km" <?php selected( $profile['travel_fee_type'], 'per_km' ); ?>>
							<?php esc_html_e( 'Per km (excl. VAT)', 'bk-agent-panel' ); ?>
						</option>
						<option value="percentage" <?php selected( $profile['travel_fee_type'], 'percentage' ); ?>>
							<?php esc_html_e( 'Percentage of install price', 'bk-agent-panel' ); ?>
						</option>
					</select>
				</div>

				<div class="bk-form-group" id="bk-rate-group">
					<label class="bk-form-label" for="bk-fee-rate" id="bk-fee-rate-label">
						<?php echo 'percentage' === $profile['travel_fee_type']
							? esc_html__( 'Percentage (%)', 'bk-agent-panel' )
							: esc_html__( 'Rate per km (R)', 'bk-agent-panel' ); ?>
					</label>
					<input
						id="bk-fee-rate"
						type="number"
						name="travel_fee_rate"
						class="bk-form-input"
						value="<?php echo esc_attr( $profile['travel_fee_rate'] ); ?>"
						min="0"
						step="0.01"
					>
				</div>

				<div class="bk-form-group">
					<label class="bk-form-label" for="bk-max-radius">
						<?php esc_html_e( 'Max Travel Radius (km)', 'bk-agent-panel' ); ?>
					</label>
					<input
						id="bk-max-radius"
						type="number"
						name="max_travel_radius_km"
						class="bk-form-input"
						value="<?php echo esc_attr( $profile['max_travel_radius_km'] ); ?>"
						min="0"
						step="1"
						placeholder="200"
					>
					<p class="bk-help-text"><?php esc_html_e( 'Leads beyond this radius will not be assigned to you.', 'bk-agent-panel' ); ?></p>
				</div>

			</div><!-- /.bk-travel-fee-fields -->

		</div>
	</div>

	<!-- =====================================================
	     Estimate Content
	     ===================================================== -->
	<div class="bk-panel-card">
		<div class="bk-panel-card__header">
			<h2 class="bk-panel-card__title"><?php esc_html_e( 'Estimate Content', 'bk-agent-panel' ); ?></h2>
		</div>
		<div class="bk-panel-card__body">

			<div class="bk-form-group">
				<label class="bk-form-label" for="bk_estimate_includes">
					<?php esc_html_e( 'Estimate Includes', 'bk-agent-panel' ); ?>
				</label>
				<?php
				wp_editor(
					$profile['estimate_includes'],
					'bk_estimate_includes',
					array(
						'textarea_name' => 'estimate_includes',
						'media_buttons' => false,
						'textarea_rows' => 6,
						'teeny'         => true,
						'quicktags'     => true,
					)
				);
				?>
				<p class="bk-help-text" style="margin-top:6px"><?php esc_html_e( 'List what is included in your all-inclusive price (e.g. excavation, coping, paving, pump & filter).', 'bk-agent-panel' ); ?></p>
			</div>

			<div class="bk-form-group" style="margin-top:20px">
				<label class="bk-form-label" for="bk_payment_structure">
					<?php esc_html_e( 'Payment Structure', 'bk-agent-panel' ); ?>
				</label>
				<?php
				wp_editor(
					$profile['payment_structure'],
					'bk_payment_structure',
					array(
						'textarea_name' => 'payment_structure',
						'media_buttons' => false,
						'textarea_rows' => 4,
						'teeny'         => true,
						'quicktags'     => true,
					)
				);
				?>
				<p class="bk-help-text" style="margin-top:6px"><?php esc_html_e( 'e.g. 50% deposit, 50% on completion.', 'bk-agent-panel' ); ?></p>
			</div>

			<div class="bk-form-group" style="margin-top:20px">
				<label class="bk-form-label" for="bk_terms_and_conditions">
					<?php esc_html_e( 'Terms & Conditions', 'bk-agent-panel' ); ?>
				</label>
				<?php
				wp_editor(
					$profile['terms_and_conditions'],
					'bk_terms_and_conditions',
					array(
						'textarea_name' => 'terms_and_conditions',
						'media_buttons' => false,
						'textarea_rows' => 10,
						'teeny'         => true,
						'quicktags'     => true,
					)
				);
				?>
				<p class="bk-help-text" style="margin-top:6px"><?php esc_html_e( 'Printed on the estimate PDF sent to customers.', 'bk-agent-panel' ); ?></p>
			</div>

		</div>
	</div>

	<!-- Save -->
	<div class="bk-profile-actions">
		<button type="submit" class="bk-btn bk-btn--primary bk-btn--lg" data-bk-profile-save>
			<?php esc_html_e( 'Save Profile', 'bk-agent-panel' ); ?>
		</button>
		<span class="bk-save-feedback bk-hidden" id="bk-profile-feedback"></span>
	</div>

</form>
