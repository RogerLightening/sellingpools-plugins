<?php
/**
 * BK Leaderboard
 *
 * Renders HTML leaderboard components used by the admin performance dashboard.
 *
 * @package BK_Performance_Tracker
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BK_Leaderboard
 *
 * @since 1.0.0
 */
class BK_Leaderboard {

	/**
	 * Medal labels for the top three positions (gold, silver, bronze).
	 *
	 * @since 1.0.0
	 * @var array<int, string>
	 */
	private static array $medals = array(
		1 => '🥇',
		2 => '🥈',
		3 => '🥉',
	);

	// -------------------------------------------------------------------------
	// Rendering
	// -------------------------------------------------------------------------

	/**
	 * Renders an HTML leaderboard table for the admin dashboard.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array<string, mixed>> $entries  Leaderboard rows from BK_Performance_Metrics.
	 * @param int                             $limit    Maximum entries shown. Default 10.
	 * @return void
	 */
	public static function render( array $entries, int $limit = 10 ): void {
		if ( empty( $entries ) ) {
			echo '<p class="bk-tracker-empty">';
			esc_html_e( 'No sales recorded this month yet.', 'bk-performance-tracker' );
			echo '</p>';
			return;
		}

		$entries = array_slice( $entries, 0, $limit );
		?>
		<table class="widefat striped bk-tracker-leaderboard-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Rank', 'bk-performance-tracker' ); ?></th>
					<th><?php esc_html_e( 'Company', 'bk-performance-tracker' ); ?></th>
					<th class="bk-tracker-num"><?php esc_html_e( 'Sales', 'bk-performance-tracker' ); ?></th>
					<th class="bk-tracker-num"><?php esc_html_e( 'Conversion', 'bk-performance-tracker' ); ?></th>
					<th class="bk-tracker-num"><?php esc_html_e( 'Revenue', 'bk-performance-tracker' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $entries as $entry ) : ?>
					<?php
					$rank  = (int) $entry['rank'];
					$medal = self::$medals[ $rank ] ?? '';
					$class = $rank <= 3 ? ' bk-tracker-leaderboard-table__row--top' . $rank : '';
					?>
					<tr class="<?php echo esc_attr( ltrim( $class ) ); ?>">
						<td>
							<?php if ( $medal ) : ?>
								<span aria-hidden="true"><?php echo $medal; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<?php endif; ?>
							<?php echo esc_html( $rank ); ?>
						</td>
						<td><?php echo esc_html( $entry['company_name'] ); ?></td>
						<td class="bk-tracker-num"><strong><?php echo esc_html( (string) $entry['won_this_month'] ); ?></strong></td>
						<td class="bk-tracker-num"><?php echo esc_html( $entry['conversion_rate'] . '%' ); ?></td>
						<td class="bk-tracker-num"><?php echo esc_html( BK_Helpers::format_currency( $entry['revenue'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Returns the rank label (medal emoji or numeric string) for a given position.
	 *
	 * @since 1.0.0
	 *
	 * @param int $rank 1-based rank number.
	 * @return string
	 */
	public static function rank_label( int $rank ): string {
		return self::$medals[ $rank ] ?? (string) $rank;
	}
}
