<?php
/**
 * Admin performance dashboard template.
 *
 * Rendered by BK_Performance_Admin::render_page().
 * All data is fetched via BK_Performance_Metrics static methods.
 *
 * @package BK_Performance_Tracker
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$summary     = BK_Performance_Metrics::get_platform_summary();
$agents      = BK_Performance_Metrics::get_all_agent_metrics();
$leaderboard = BK_Performance_Metrics::get_monthly_leaderboard( 10 );

$rewards_enabled = BK_Feature_Toggles::is_enabled( 'feature_rewards' );

// Status colour map — mirrors the agent panel status palette.
$status_colours = array(
	'new'          => '#2E86C1',
	'contacted'    => '#1565C0',
	'no_answer'    => '#E65100',
	'wrong_number' => '#E74C3C',
	'site_visit'   => '#6A1B9A',
	'quoted'       => '#E67E22',
	'won'          => '#27AE60',
	'lost'         => '#7F8C8D',
	'stale'        => '#9E9E9E',
);

$status_labels = array(
	'new'          => __( 'New', 'bk-performance-tracker' ),
	'contacted'    => __( 'Contacted', 'bk-performance-tracker' ),
	'no_answer'    => __( 'No Answer', 'bk-performance-tracker' ),
	'wrong_number' => __( 'Wrong Number', 'bk-performance-tracker' ),
	'site_visit'   => __( 'Site Visit', 'bk-performance-tracker' ),
	'quoted'       => __( 'Quoted', 'bk-performance-tracker' ),
	'won'          => __( 'Won', 'bk-performance-tracker' ),
	'lost'         => __( 'Lost', 'bk-performance-tracker' ),
	'stale'        => __( 'Stale', 'bk-performance-tracker' ),
);

$max_status_count = $summary['total_leads'] > 0 ? max( $summary['leads_by_status'] ) : 1;
?>
<div class="wrap bk-tracker-wrap">

	<h1 class="bk-tracker-page-title">
		<span class="dashicons dashicons-chart-bar" aria-hidden="true"></span>
		<?php esc_html_e( 'Performance Dashboard', 'bk-performance-tracker' ); ?>
	</h1>

	<p class="bk-tracker-generated">
		<?php
		printf(
			/* translators: %s: date and time */
			esc_html__( 'Data as at %s', 'bk-performance-tracker' ),
			esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) )
		);
		?>
	</p>

	<!-- =====================================================
	     Platform Summary Cards
	     ===================================================== -->
	<div class="bk-tracker-summary-grid">

		<div class="bk-tracker-card bk-tracker-card--stat">
			<span class="bk-tracker-card__value"><?php echo esc_html( (string) $summary['total_leads'] ); ?></span>
			<span class="bk-tracker-card__label"><?php esc_html_e( 'Total Leads', 'bk-performance-tracker' ); ?></span>
		</div>

		<div class="bk-tracker-card bk-tracker-card--stat">
			<span class="bk-tracker-card__value"><?php echo esc_html( (string) $summary['total_leads_this_month'] ); ?></span>
			<span class="bk-tracker-card__label"><?php esc_html_e( 'Leads This Month', 'bk-performance-tracker' ); ?></span>
		</div>

		<div class="bk-tracker-card bk-tracker-card--stat bk-tracker-card--success">
			<span class="bk-tracker-card__value"><?php echo esc_html( (string) $summary['total_won_this_month'] ); ?></span>
			<span class="bk-tracker-card__label"><?php esc_html_e( 'Won This Month', 'bk-performance-tracker' ); ?></span>
		</div>

		<div class="bk-tracker-card bk-tracker-card--stat">
			<span class="bk-tracker-card__value"><?php echo esc_html( $summary['overall_conversion'] . '%' ); ?></span>
			<span class="bk-tracker-card__label"><?php esc_html_e( 'Overall Conversion', 'bk-performance-tracker' ); ?></span>
		</div>

		<div class="bk-tracker-card bk-tracker-card--stat bk-tracker-card--revenue">
			<span class="bk-tracker-card__value"><?php echo esc_html( BK_Helpers::format_currency( $summary['total_revenue'] ) ); ?></span>
			<span class="bk-tracker-card__label"><?php esc_html_e( 'Total Revenue', 'bk-performance-tracker' ); ?></span>
		</div>

		<div class="bk-tracker-card bk-tracker-card--stat bk-tracker-card--revenue">
			<span class="bk-tracker-card__value"><?php echo esc_html( BK_Helpers::format_currency( $summary['revenue_this_month'] ) ); ?></span>
			<span class="bk-tracker-card__label"><?php esc_html_e( 'Revenue This Month', 'bk-performance-tracker' ); ?></span>
		</div>

		<div class="bk-tracker-card bk-tracker-card--stat">
			<span class="bk-tracker-card__value">
				<?php echo $summary['avg_response_hours'] !== null
					? esc_html( $summary['avg_response_hours'] . 'h' )
					: '—'; ?>
			</span>
			<span class="bk-tracker-card__label"><?php esc_html_e( 'Avg Response Time', 'bk-performance-tracker' ); ?></span>
		</div>

		<div class="bk-tracker-card bk-tracker-card--stat">
			<span class="bk-tracker-card__value">
				<?php echo esc_html( $summary['active_agents'] . ' / ' . $summary['total_agents'] ); ?>
			</span>
			<span class="bk-tracker-card__label"><?php esc_html_e( 'Active / Total Agents', 'bk-performance-tracker' ); ?></span>
		</div>

	</div><!-- /.bk-tracker-summary-grid -->

	<!-- =====================================================
	     Leads by Status
	     ===================================================== -->
	<div class="bk-tracker-panel">
		<h2 class="bk-tracker-panel__title"><?php esc_html_e( 'Leads by Status', 'bk-performance-tracker' ); ?></h2>
		<div class="bk-tracker-status-bars">
			<?php foreach ( $summary['leads_by_status'] as $status => $count ) :
				$pct   = $summary['total_leads'] > 0 ? round( ( $count / $summary['total_leads'] ) * 100, 1 ) : 0;
				$bar_w = $max_status_count > 0 ? round( ( $count / $max_status_count ) * 100 ) : 0;
				$colour = $status_colours[ $status ] ?? '#7F8C8D';
			?>
				<div class="bk-tracker-status-bar">
					<span class="bk-tracker-status-bar__label" style="color:<?php echo esc_attr( $colour ); ?>">
						<?php echo esc_html( $status_labels[ $status ] ?? $status ); ?>
					</span>
					<div class="bk-tracker-status-bar__track">
						<div
							class="bk-tracker-status-bar__fill"
							style="width:<?php echo esc_attr( $bar_w . '%' ); ?>; background:<?php echo esc_attr( $colour ); ?>"
						></div>
					</div>
					<span class="bk-tracker-status-bar__count">
						<?php echo esc_html( $count . ' (' . $pct . '%)' ); ?>
					</span>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

	<!-- =====================================================
	     Agent Performance Table
	     ===================================================== -->
	<div class="bk-tracker-panel">
		<h2 class="bk-tracker-panel__title">
			<?php esc_html_e( 'Agent Performance', 'bk-performance-tracker' ); ?>
			<span class="bk-tracker-sort-hint"><?php esc_html_e( '(click a column header to sort)', 'bk-performance-tracker' ); ?></span>
		</h2>

		<?php if ( empty( $agents ) ) : ?>
			<p class="bk-tracker-empty"><?php esc_html_e( 'No agent data found.', 'bk-performance-tracker' ); ?></p>
		<?php else : ?>
			<div class="bk-tracker-table-wrap">
				<table class="widefat striped bk-tracker-agents-table" id="bk-tracker-agents-table" data-sortable>
					<thead>
						<tr>
							<th data-col="company_name"><?php esc_html_e( 'Company', 'bk-performance-tracker' ); ?></th>
							<th data-col="contact_name"><?php esc_html_e( 'Contact', 'bk-performance-tracker' ); ?></th>
							<th class="bk-tracker-num" data-col="total_leads"><?php esc_html_e( 'Total', 'bk-performance-tracker' ); ?></th>
							<th class="bk-tracker-num" data-col="leads_this_month"><?php esc_html_e( 'This Month', 'bk-performance-tracker' ); ?></th>
							<th class="bk-tracker-num" data-col="won_leads"><?php esc_html_e( 'Won', 'bk-performance-tracker' ); ?></th>
							<th class="bk-tracker-num" data-col="lost_leads"><?php esc_html_e( 'Lost', 'bk-performance-tracker' ); ?></th>
							<th class="bk-tracker-num" data-col="stale_leads"><?php esc_html_e( 'Stale', 'bk-performance-tracker' ); ?></th>
							<th class="bk-tracker-num" data-col="conversion_rate"><?php esc_html_e( 'Conv %', 'bk-performance-tracker' ); ?></th>
							<th class="bk-tracker-num" data-col="avg_response_hours"><?php esc_html_e( 'Resp (h)', 'bk-performance-tracker' ); ?></th>
							<th class="bk-tracker-num" data-col="avg_quality_rating"><?php esc_html_e( 'Avg ★', 'bk-performance-tracker' ); ?></th>
							<th class="bk-tracker-num" data-col="total_revenue"><?php esc_html_e( 'Revenue', 'bk-performance-tracker' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $agents as $idx => $agent ) :
							$is_top        = ( 0 === $idx );
							$slow_response = $agent['avg_response_hours'] !== null && $agent['avg_response_hours'] > 48;
							$no_leads      = 0 === $agent['leads_this_month'];

							$row_class = '';
							if ( $is_top ) { $row_class = 'bk-tracker-row--top'; }
							elseif ( $slow_response ) { $row_class = 'bk-tracker-row--slow'; }
							elseif ( $no_leads ) { $row_class = 'bk-tracker-row--inactive'; }
						?>
							<tr
								class="<?php echo esc_attr( $row_class ); ?>"
								data-company_name="<?php echo esc_attr( strtolower( $agent['company_name'] ) ); ?>"
								data-contact_name="<?php echo esc_attr( strtolower( $agent['contact_name'] ) ); ?>"
								data-total_leads="<?php echo esc_attr( (string) $agent['total_leads'] ); ?>"
								data-leads_this_month="<?php echo esc_attr( (string) $agent['leads_this_month'] ); ?>"
								data-won_leads="<?php echo esc_attr( (string) $agent['won_leads'] ); ?>"
								data-lost_leads="<?php echo esc_attr( (string) $agent['lost_leads'] ); ?>"
								data-stale_leads="<?php echo esc_attr( (string) $agent['stale_leads'] ); ?>"
								data-conversion_rate="<?php echo esc_attr( (string) $agent['conversion_rate'] ); ?>"
								data-avg_response_hours="<?php echo esc_attr( $agent['avg_response_hours'] !== null ? (string) $agent['avg_response_hours'] : '-1' ); ?>"
								data-avg_quality_rating="<?php echo esc_attr( $agent['avg_quality_rating'] !== null ? (string) $agent['avg_quality_rating'] : '-1' ); ?>"
								data-total_revenue="<?php echo esc_attr( (string) $agent['total_revenue'] ); ?>"
							>
								<td>
									<?php if ( $is_top ) : ?><span class="bk-tracker-badge bk-tracker-badge--top"><?php esc_html_e( 'Top', 'bk-performance-tracker' ); ?></span><?php endif; ?>
									<?php echo esc_html( $agent['company_name'] ); ?>
								</td>
								<td><?php echo esc_html( $agent['contact_name'] ?: '—' ); ?></td>
								<td class="bk-tracker-num"><?php echo esc_html( (string) $agent['total_leads'] ); ?></td>
								<td class="bk-tracker-num"><?php echo esc_html( (string) $agent['leads_this_month'] ); ?></td>
								<td class="bk-tracker-num bk-tracker-num--won"><?php echo esc_html( (string) $agent['won_leads'] ); ?></td>
								<td class="bk-tracker-num"><?php echo esc_html( (string) $agent['lost_leads'] ); ?></td>
								<td class="bk-tracker-num bk-tracker-num--muted"><?php echo esc_html( (string) $agent['stale_leads'] ); ?></td>
								<td class="bk-tracker-num"><strong><?php echo esc_html( $agent['conversion_rate'] . '%' ); ?></strong></td>
								<td class="bk-tracker-num<?php echo $slow_response ? ' bk-tracker-num--warn' : ''; ?>">
									<?php echo $agent['avg_response_hours'] !== null ? esc_html( $agent['avg_response_hours'] . 'h' ) : '—'; ?>
								</td>
								<td class="bk-tracker-num">
									<?php echo $agent['avg_quality_rating'] !== null ? esc_html( (string) $agent['avg_quality_rating'] ) : '—'; ?>
								</td>
								<td class="bk-tracker-num"><?php echo esc_html( BK_Helpers::format_currency( $agent['total_revenue'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<p class="bk-tracker-legend">
				<span class="bk-tracker-legend__item bk-tracker-legend__item--top"><?php esc_html_e( 'Top performer', 'bk-performance-tracker' ); ?></span>
				<span class="bk-tracker-legend__item bk-tracker-legend__item--slow"><?php esc_html_e( 'Avg response &gt; 48 h', 'bk-performance-tracker' ); ?></span>
				<span class="bk-tracker-legend__item bk-tracker-legend__item--inactive"><?php esc_html_e( 'No leads this month', 'bk-performance-tracker' ); ?></span>
			</p>
		<?php endif; ?>
	</div>

	<!-- =====================================================
	     Monthly Leaderboard
	     ===================================================== -->
	<div class="bk-tracker-panel">
		<h2 class="bk-tracker-panel__title"><?php esc_html_e( 'Monthly Leaderboard', 'bk-performance-tracker' ); ?></h2>
		<?php BK_Leaderboard::render( $leaderboard, 10 ); ?>
	</div>

	<!-- =====================================================
	     Lead Quality Overview
	     ===================================================== -->
	<div class="bk-tracker-panel">
		<h2 class="bk-tracker-panel__title"><?php esc_html_e( 'Lead Quality Overview', 'bk-performance-tracker' ); ?></h2>

		<?php if ( empty( $agents ) ) : ?>
			<p class="bk-tracker-empty"><?php esc_html_e( 'No data yet.', 'bk-performance-tracker' ); ?></p>
		<?php else :
			// Compute overall platform average rating.
			$rated_agents = array_filter( $agents, static fn( $a ) => $a['avg_quality_rating'] !== null );
			$platform_avg = count( $rated_agents ) > 0
				? round( array_sum( array_column( $rated_agents, 'avg_quality_rating' ) ) / count( $rated_agents ), 1 )
				: null;

			$low_raters  = array_filter( $rated_agents, static fn( $a ) => $a['avg_quality_rating'] < 2 );
			$high_raters = array_filter( $rated_agents, static fn( $a ) => $a['avg_quality_rating'] >= 4 );
		?>
			<p class="bk-tracker-quality-avg">
				<?php if ( $platform_avg !== null ) : ?>
					<?php
					printf(
						/* translators: %s: average rating */
						esc_html__( 'Platform average quality rating: %s / 5', 'bk-performance-tracker' ),
						'<strong>' . esc_html( (string) $platform_avg ) . '</strong>'
					);
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo ' ';
					for ( $i = 1; $i <= 5; $i++ ) {
						echo '<span style="color:' . ( $i <= $platform_avg ? '#F1C40F' : '#BDC3C7' ) . '">&#9733;</span>';
					}
					?>
				<?php else : ?>
					<?php esc_html_e( 'No quality ratings recorded yet.', 'bk-performance-tracker' ); ?>
				<?php endif; ?>
			</p>

			<?php if ( ! empty( $low_raters ) ) : ?>
				<h3 class="bk-tracker-quality__heading bk-tracker-quality__heading--warn">
					<?php esc_html_e( 'Agents with consistently low ratings (avg &lt; 2) — flagged for review', 'bk-performance-tracker' ); ?>
				</h3>
				<ul class="bk-tracker-quality__list">
					<?php foreach ( $low_raters as $agent ) : ?>
						<li>
							<?php echo esc_html( $agent['company_name'] ); ?>
							&mdash;
							<?php echo esc_html( sprintf( __( 'avg %.1f / 5', 'bk-performance-tracker' ), $agent['avg_quality_rating'] ) ); ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<?php if ( ! empty( $high_raters ) ) : ?>
				<h3 class="bk-tracker-quality__heading bk-tracker-quality__heading--good">
					<?php esc_html_e( 'Agents with consistently high ratings (avg ≥ 4)', 'bk-performance-tracker' ); ?>
				</h3>
				<ul class="bk-tracker-quality__list">
					<?php foreach ( $high_raters as $agent ) : ?>
						<li>
							<?php echo esc_html( $agent['company_name'] ); ?>
							&mdash;
							<?php echo esc_html( sprintf( __( 'avg %.1f / 5', 'bk-performance-tracker' ), $agent['avg_quality_rating'] ) ); ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		<?php endif; ?>
	</div>

	<?php if ( $rewards_enabled ) : ?>
	<!-- =====================================================
	     Reward Status
	     ===================================================== -->
	<div class="bk-tracker-panel">
		<h2 class="bk-tracker-panel__title"><?php esc_html_e( 'Reward Status', 'bk-performance-tracker' ); ?></h2>

		<?php
		$top_seller_discount = (float) BK_Settings::get_setting( 'reward_top_seller_discount', 0.05 );
		$milestone_10        = (int) BK_Settings::get_setting( 'reward_milestone_10', 0 );
		$milestone_25        = (int) BK_Settings::get_setting( 'reward_milestone_25', 0 );
		$milestone_50        = (int) BK_Settings::get_setting( 'reward_milestone_50', 0 );
		$milestones          = array( 10 => $milestone_10, 25 => $milestone_25, 50 => $milestone_50 );

		// Top seller this month.
		if ( ! empty( $leaderboard ) ) :
			$top        = $leaderboard[0];
			$top_pct    = round( $top_seller_discount * 100, 1 );
		?>
			<p>
				<?php
				printf(
					/* translators: 1: company name, 2: sales count, 3: discount pct */
					esc_html__( 'Current monthly leader: %1$s (%2$d sales) — eligible for %3$s%% discount on next order.', 'bk-performance-tracker' ),
					'<strong>' . esc_html( $top['company_name'] ) . '</strong>',
					esc_html( (string) $top['won_this_month'] ),
					esc_html( (string) $top_pct )
				);
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</p>
		<?php endif; ?>

		<?php if ( ! empty( $agents ) && array_sum( $milestones ) > 0 ) : ?>
			<h3 class="bk-tracker-quality__heading"><?php esc_html_e( 'Milestone Progress', 'bk-performance-tracker' ); ?></h3>
			<table class="widefat striped bk-tracker-milestones-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Agent', 'bk-performance-tracker' ); ?></th>
						<th class="bk-tracker-num"><?php esc_html_e( 'Lifetime Sales', 'bk-performance-tracker' ); ?></th>
						<th><?php esc_html_e( 'Next Milestone', 'bk-performance-tracker' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $agents as $agent ) :
						$counts = BK_Performance_Metrics::get_agent_sales_counts( $agent['agent_post_id'] );
						$lifetime = $counts['total_sales'];

						// Determine next applicable milestone.
						$next_milestone = null;
						$next_bonus     = 0;
						foreach ( $milestones as $threshold => $bonus ) {
							if ( $bonus > 0 && $lifetime < $threshold ) {
								$next_milestone = $threshold;
								$next_bonus     = $bonus;
								break;
							}
						}
						if ( null === $next_milestone ) { continue; } // Already past all milestones.
					?>
						<tr>
							<td><?php echo esc_html( $agent['company_name'] ); ?></td>
							<td class="bk-tracker-num"><?php echo esc_html( (string) $lifetime ); ?></td>
							<td>
								<?php
								printf(
									/* translators: 1: sales away count, 2: threshold, 3: bonus */
									esc_html__( '%1$d away from %2$d-sale milestone (R%3$s bonus)', 'bk-performance-tracker' ),
									esc_html( (string) ( $next_milestone - $lifetime ) ),
									esc_html( (string) $next_milestone ),
									esc_html( number_format( $next_bonus ) )
								);
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php endif; ?>

</div><!-- /.bk-tracker-wrap -->

<script>
/* Vanilla JS column sort for the agent performance table. */
( function () {
	var table = document.getElementById( 'bk-tracker-agents-table' );
	if ( ! table ) { return; }

	var headers = table.querySelectorAll( 'thead th[data-col]' );
	var sortDir = {};

	headers.forEach( function ( th ) {
		th.style.cursor = 'pointer';
		th.title = '<?php esc_attr_e( 'Click to sort', 'bk-performance-tracker' ); ?>';

		th.addEventListener( 'click', function () {
			var col    = th.getAttribute( 'data-col' );
			var isAsc  = sortDir[ col ] !== 'asc';
			sortDir[ col ] = isAsc ? 'asc' : 'desc';

			var tbody = table.querySelector( 'tbody' );
			var rows  = Array.from( tbody.querySelectorAll( 'tr' ) );

			rows.sort( function ( a, b ) {
				var aVal = a.getAttribute( 'data-' + col ) || '';
				var bVal = b.getAttribute( 'data-' + col ) || '';

				// Numeric sort for numeric columns.
				var aNum = parseFloat( aVal );
				var bNum = parseFloat( bVal );
				if ( ! isNaN( aNum ) && ! isNaN( bNum ) ) {
					return isAsc ? aNum - bNum : bNum - aNum;
				}

				// String sort.
				return isAsc
					? aVal.localeCompare( bVal )
					: bVal.localeCompare( aVal );
			} );

			rows.forEach( function ( row ) { tbody.appendChild( row ); } );

			// Update sort indicators.
			headers.forEach( function ( h ) { h.removeAttribute( 'data-sort-dir' ); } );
			th.setAttribute( 'data-sort-dir', isAsc ? 'asc' : 'desc' );
		} );
	} );
}() );
</script>
