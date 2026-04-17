<?php
/**
 * Lead detail partial — included inside the expandable row in leads-list.php.
 *
 * @var array  $lead         Single lead data array from BK_Agent_Leads::get_leads().
 * @var string $panel_url    Panel page permalink.
 *
 * @package BK_Agent_Panel
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="bk-lead-detail">

	<div class="bk-lead-detail__grid">

		<!-- Customer info -->
		<div class="bk-lead-detail__section">
			<h3 class="bk-lead-detail__heading"><?php esc_html_e( 'Customer', 'bk-agent-panel' ); ?></h3>
			<dl class="bk-dl">
				<dt><?php esc_html_e( 'Name', 'bk-agent-panel' ); ?></dt>
				<dd><?php echo esc_html( $lead['customer_name'] ); ?></dd>

				<dt><?php esc_html_e( 'Email', 'bk-agent-panel' ); ?></dt>
				<dd>
					<a href="mailto:<?php echo esc_attr( $lead['customer_email'] ); ?>">
						<?php echo esc_html( $lead['customer_email'] ); ?>
					</a>
				</dd>

				<dt><?php esc_html_e( 'Phone', 'bk-agent-panel' ); ?></dt>
				<dd>
					<a href="tel:<?php echo esc_attr( $lead['customer_phone'] ); ?>">
						<?php echo esc_html( $lead['customer_phone'] ); ?>
					</a>
				</dd>

				<dt><?php esc_html_e( 'Location', 'bk-agent-panel' ); ?></dt>
				<dd><?php echo esc_html( $lead['suburb_name'] . ', ' . $lead['area_name'] ); ?></dd>

				<dt><?php esc_html_e( 'Province', 'bk-agent-panel' ); ?></dt>
				<dd><?php echo esc_html( $lead['province'] ); ?></dd>
			</dl>
		</div>

		<!-- Pricing breakdown -->
		<div class="bk-lead-detail__section">
			<h3 class="bk-lead-detail__heading"><?php esc_html_e( 'Pricing Breakdown', 'bk-agent-panel' ); ?></h3>
			<dl class="bk-dl">
				<dt><?php esc_html_e( 'Distance', 'bk-agent-panel' ); ?></dt>
				<dd><?php echo esc_html( number_format( $lead['distance_km'], 1 ) ); ?> km</dd>

				<dt><?php esc_html_e( 'Travel Fee', 'bk-agent-panel' ); ?></dt>
				<dd>
					<?php echo $lead['travel_fee'] > 0
						? esc_html( BK_Helpers::format_currency( $lead['travel_fee'] ) )
						: esc_html__( 'Included', 'bk-agent-panel' ); ?>
				</dd>

				<dt><?php esc_html_e( 'Total (incl. VAT)', 'bk-agent-panel' ); ?></dt>
				<dd><strong><?php echo esc_html( BK_Helpers::format_currency( $lead['total_estimate_incl'] ) ); ?></strong></dd>
			</dl>

			<?php if ( $lead['pdf_url'] ) : ?>
				<a
					href="<?php echo esc_url( $lead['pdf_url'] ); ?>"
					class="bk-btn bk-btn--secondary bk-btn--sm"
					target="_blank"
					rel="noopener"
				>
					<?php esc_html_e( 'Download PDF', 'bk-agent-panel' ); ?>
				</a>
			<?php else : ?>
				<span class="bk-text-muted" style="font-size:12px"><?php esc_html_e( 'PDF not available', 'bk-agent-panel' ); ?></span>
			<?php endif; ?>
		</div>

		<!-- Timeline -->
		<div class="bk-lead-detail__section">
			<h3 class="bk-lead-detail__heading"><?php esc_html_e( 'Timeline', 'bk-agent-panel' ); ?></h3>
			<ul class="bk-timeline">
				<li class="bk-timeline__item">
					<span class="bk-timeline__label"><?php esc_html_e( 'Assigned', 'bk-agent-panel' ); ?></span>
					<span class="bk-timeline__value">
						<?php echo $lead['assigned_at']
							? esc_html( date_i18n( 'j M Y, H:i', strtotime( $lead['assigned_at'] ) ) )
							: '—'; ?>
					</span>
				</li>
				<?php if ( $lead['first_response_at'] ) : ?>
					<li class="bk-timeline__item">
						<span class="bk-timeline__label"><?php esc_html_e( 'First Response', 'bk-agent-panel' ); ?></span>
						<span class="bk-timeline__value">
							<?php echo esc_html( date_i18n( 'j M Y, H:i', strtotime( $lead['first_response_at'] ) ) ); ?>
						</span>
					</li>
				<?php endif; ?>
				<?php if ( $lead['status_updated_at'] ) : ?>
					<li class="bk-timeline__item">
						<span class="bk-timeline__label"><?php esc_html_e( 'Last Update', 'bk-agent-panel' ); ?></span>
						<span class="bk-timeline__value">
							<?php echo esc_html( date_i18n( 'j M Y, H:i', strtotime( $lead['status_updated_at'] ) ) ); ?>
						</span>
					</li>
				<?php endif; ?>
			</ul>
		</div>

	</div><!-- /.bk-lead-detail__grid -->

	<!-- Notes -->
	<div class="bk-lead-detail__notes">
		<h3 class="bk-lead-detail__heading"><?php esc_html_e( 'Notes', 'bk-agent-panel' ); ?></h3>
		<textarea
			class="bk-form-input bk-notes-textarea"
			data-lead-agent-id="<?php echo esc_attr( $lead['lead_agent_id'] ); ?>"
			data-bk-notes
			rows="4"
			placeholder="<?php esc_attr_e( 'Add notes about this lead…', 'bk-agent-panel' ); ?>"
		><?php echo esc_textarea( $lead['lead_notes'] ); ?></textarea>
		<button
			type="button"
			class="bk-btn bk-btn--primary bk-btn--sm"
			data-bk-save-notes="<?php echo esc_attr( $lead['lead_agent_id'] ); ?>"
		>
			<?php esc_html_e( 'Save Notes', 'bk-agent-panel' ); ?>
		</button>
		<span class="bk-notes-saved-msg bk-hidden" data-bk-notes-msg="<?php echo esc_attr( $lead['lead_agent_id'] ); ?>">
			<?php esc_html_e( 'Notes saved.', 'bk-agent-panel' ); ?>
		</span>
	</div>

</div><!-- /.bk-lead-detail -->
