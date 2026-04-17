<?php
/**
 * Pricing management template.
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

$pricing_rows = BK_Agent_Pricing::get_pricing( $agent_post_id );
?>

<div class="bk-section-header">
	<h1 class="bk-section-title"><?php esc_html_e( 'Pricing Management', 'bk-agent-panel' ); ?></h1>
</div>

<!-- Pricing table -->
<div class="bk-panel-card">
	<div class="bk-panel-card__header">
		<h2 class="bk-panel-card__title">
			<?php esc_html_e( 'Pool Shape Pricing', 'bk-agent-panel' ); ?>
			<span class="bk-badge"><?php echo esc_html( count( $pricing_rows ) ); ?></span>
		</h2>
		<p class="bk-help-text"><?php esc_html_e( 'Enter your all-inclusive price per pool shape (VAT included). Click a price to edit — changes save automatically.', 'bk-agent-panel' ); ?></p>
	</div>

	<?php if ( empty( $pricing_rows ) ) : ?>
		<p class="bk-empty-state"><?php esc_html_e( 'No pool shapes found.', 'bk-agent-panel' ); ?></p>
	<?php else : ?>
		<div class="bk-table-wrapper">
			<table class="bk-table bk-pricing-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Shape', 'bk-agent-panel' ); ?></th>
						<th><?php esc_html_e( 'Code', 'bk-agent-panel' ); ?></th>
						<th class="bk-text-right"><?php esc_html_e( 'All-Inclusive Price (incl. VAT)', 'bk-agent-panel' ); ?></th>
						<th class="bk-text-center"><?php esc_html_e( 'Available', 'bk-agent-panel' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $pricing_rows as $row ) : ?>
						<tr
							class="bk-pricing-table__row<?php echo ! $row['has_pricing'] ? ' bk-pricing-table__row--no-price' : ''; ?>"
							data-pricing-id="<?php echo esc_attr( $row['pricing_id'] ?? '' ); ?>"
							data-shape-id="<?php echo esc_attr( $row['pool_shape_id'] ); ?>"
						>
							<td>
								<?php echo esc_html( $row['shape_name'] ); ?>
								<?php if ( ! $row['has_pricing'] ) : ?>
									<span class="bk-badge bk-badge--warning"><?php esc_html_e( 'Set price', 'bk-agent-panel' ); ?></span>
								<?php endif; ?>
							</td>
							<td><code><?php echo esc_html( $row['shape_code'] ); ?></code></td>
							<td class="bk-text-right">
								<input
									type="number"
									class="bk-price-input"
									value="<?php echo esc_attr( $row['has_pricing'] ? number_format( $row['installed_price_incl'], 2, '.', '' ) : '' ); ?>"
									placeholder="<?php esc_attr_e( 'Enter all-in price', 'bk-agent-panel' ); ?>"
									min="0"
									step="0.01"
									data-pricing-id="<?php echo esc_attr( $row['pricing_id'] ?? '' ); ?>"
									data-shape-id="<?php echo esc_attr( $row['pool_shape_id'] ); ?>"
									data-bk-price-input
								>
							</td>
							<td class="bk-text-center">
								<?php if ( $row['has_pricing'] ) : ?>
									<label class="bk-toggle">
										<input
											type="checkbox"
											class="bk-availability-toggle"
											data-pricing-id="<?php echo esc_attr( $row['pricing_id'] ); ?>"
											data-bk-availability
											<?php checked( $row['is_available'] ); ?>
										>
										<span class="bk-toggle__slider"></span>
									</label>
								<?php else : ?>
									<span class="bk-text-muted">—</span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>
