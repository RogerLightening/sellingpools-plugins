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

/**
 * Formats pool dimensions as "L × W m" with an optional depth hint.
 *
 * @param array $row Pricing row with dimension keys.
 * @return string Formatted dimensions string, or em-dash if length/width are both 0.
 */
$format_dimensions = static function ( array $row ): string {
	$length = (float) ( $row['dimensions_length'] ?? 0 );
	$width  = (float) ( $row['dimensions_width'] ?? 0 );

	if ( $length <= 0 && $width <= 0 ) {
		return '<span class="bk-text-muted">—</span>';
	}

	$dims    = sprintf( '%s &times; %s m', rtrim( rtrim( number_format( $length, 2, '.', '' ), '0' ), '.' ), rtrim( rtrim( number_format( $width, 2, '.', '' ), '0' ), '.' ) );
	$shallow = (float) ( $row['depth_shallow'] ?? 0 );
	$deep    = (float) ( $row['depth_deep'] ?? 0 );

	if ( $shallow > 0 && $deep > 0 ) {
		$depth = $shallow === $deep
			? sprintf( '%s m deep', rtrim( rtrim( number_format( $shallow, 2, '.', '' ), '0' ), '.' ) )
			: sprintf( '%s&ndash;%s m deep', rtrim( rtrim( number_format( $shallow, 2, '.', '' ), '0' ), '.' ), rtrim( rtrim( number_format( $deep, 2, '.', '' ), '0' ), '.' ) );
		$dims .= '<br><span class="bk-dimensions-depth">' . $depth . '</span>';
	}

	return $dims;
};
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
						<th><?php esc_html_e( 'Dimensions', 'bk-agent-panel' ); ?></th>
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
								<?php if ( $row['shape_image_url'] ) : ?>
									<br>
									<a
										href="#"
										class="bk-shape-image-link"
										data-bk-shape-image
										data-image-url="<?php echo esc_url( $row['shape_image_url'] ); ?>"
										data-image-title="<?php echo esc_attr( $row['shape_name'] ); ?>"
									><?php esc_html_e( 'View image', 'bk-agent-panel' ); ?></a>
								<?php endif; ?>
							</td>
							<td><code><?php echo esc_html( $row['shape_code'] ); ?></code></td>
							<td class="bk-dimensions-cell">
								<?php
								// $format_dimensions returns already-escaped HTML; the numeric bits pass
								// through number_format and the static string is under our control.
								echo $format_dimensions( $row ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								?>
							</td>
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

<!-- Shape image modal -->
<div class="bk-modal" id="bk-shape-image-modal" data-bk-modal hidden>
	<div class="bk-modal__backdrop" data-bk-modal-close></div>
	<div class="bk-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="bk-shape-image-title">
		<div class="bk-modal__header">
			<h3 class="bk-modal__title" id="bk-shape-image-title"></h3>
			<button type="button" class="bk-modal__close" data-bk-modal-close aria-label="<?php esc_attr_e( 'Close', 'bk-agent-panel' ); ?>">&times;</button>
		</div>
		<div class="bk-modal__body">
			<img src="" alt="" class="bk-modal__image" data-bk-modal-image>
		</div>
	</div>
</div>
