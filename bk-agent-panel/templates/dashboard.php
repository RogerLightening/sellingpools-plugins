<?php
/**
 * Dashboard home template.
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

$data  = BK_Agent_Dashboard::get_dashboard_data( $agent_post_id );
$stats = $data['stats'];

/**
 * Render a stat card.
 *
 * @param string     $label Card label.
 * @param string|int $value Display value.
 * @param string     $mod   Optional modifier class.
 */
$stat_card = static function ( string $label, string $value, string $mod = '' ) : void {
	echo '<div class="bk-stat-card' . ( $mod ? ' ' . esc_attr( $mod ) : '' ) . '">';
	echo '<span class="bk-stat-card__value">' . esc_html( $value ) . '</span>';
	echo '<span class="bk-stat-card__label">' . esc_html( $label ) . '</span>';
	echo '</div>';
};

$star_display = static function ( float $rating ) : string {
	$html = '<span class="bk-stars">';
	for ( $i = 1; $i <= 5; $i++ ) {
		$html .= '<span class="bk-star' . ( $i <= $rating ? ' bk-star--filled' : '' ) . '">&#9733;</span>';
	}
	$html .= '</span>';
	return $html;
};
?>

<!-- Welcome bar -->
<div class="bk-panel-welcome">
	<div>
		<h1 class="bk-panel-welcome__heading">
			<?php
			printf(
				/* translators: %s: agent name */
				esc_html__( 'Welcome back, %s', 'bk-agent-panel' ),
				esc_html( $data['agent_name'] ?: $data['company_name'] )
			);
			?>
		</h1>
		<p class="bk-panel-welcome__company"><?php echo esc_html( $data['company_name'] ); ?></p>
	</div>
</div>

<!-- Stats cards -->
<div class="bk-stats-grid">
	<?php
	$stat_card( __( 'Total Leads', 'bk-agent-panel' ), (string) $stats['total_leads'] );
	$stat_card( __( 'Leads This Month', 'bk-agent-panel' ), (string) $stats['leads_this_month'] );
	$stat_card( __( 'Won This Month', 'bk-agent-panel' ), (string) $stats['won_this_month'], 'bk-stat-card--success' );
	$stat_card( __( 'Conversion Rate', 'bk-agent-panel' ), $stats['conversion_rate'] . '%' );
	?>
	<?php if ( ! class_exists( 'BK_Feature_Toggles' ) || BK_Feature_Toggles::is_enabled( 'feature_star_ratings' ) ) : ?>
	<div class="bk-stat-card">
		<span class="bk-stat-card__value">
			<?php
			if ( $stats['avg_quality_given'] > 0 ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $star_display( $stats['avg_quality_given'] );
			} else {
				echo '—';
			}
			?>
		</span>
		<span class="bk-stat-card__label"><?php esc_html_e( 'Avg Lead Quality', 'bk-agent-panel' ); ?></span>
	</div>
	<?php endif; ?>
</div>

<div class="bk-dashboard-grid">

	<!-- New leads requiring attention -->
	<div class="bk-panel-card">
		<div class="bk-panel-card__header">
			<h2 class="bk-panel-card__title"><?php esc_html_e( 'Attention Needed', 'bk-agent-panel' ); ?></h2>
			<a href="<?php echo esc_url( add_query_arg( array( 'section' => 'leads', 'status' => 'new' ), $panel_url ) ); ?>" class="bk-link">
				<?php esc_html_e( 'View all new leads', 'bk-agent-panel' ); ?>
			</a>
		</div>

		<?php if ( empty( $data['recent_new_leads'] ) ) : ?>
			<p class="bk-empty-state"><?php esc_html_e( 'No new leads waiting — you\'re all caught up!', 'bk-agent-panel' ); ?></p>
		<?php else : ?>
			<ul class="bk-new-leads-list">
				<?php foreach ( $data['recent_new_leads'] as $lead ) : ?>
					<?php
					$hours = (int) $lead['hours_since'];

					if ( $hours < 24 ) {
						$urgency_class = 'bk-urgency--green';
						$urgency_label = sprintf( __( '%d hours ago', 'bk-agent-panel' ), $hours );
					} elseif ( $hours < 48 ) {
						$urgency_class = 'bk-urgency--amber';
						$urgency_label = sprintf( __( '%d hours ago', 'bk-agent-panel' ), $hours );
					} else {
						$urgency_class = 'bk-urgency--red';
						$urgency_label = sprintf( __( '%d hours ago', 'bk-agent-panel' ), $hours );
					}
					?>
					<li class="bk-new-lead-item">
						<span class="bk-urgency-dot <?php echo esc_attr( $urgency_class ); ?>" title="<?php echo esc_attr( $urgency_label ); ?>"></span>
						<div class="bk-new-lead-item__body">
							<strong><?php echo esc_html( $lead['customer_name'] ); ?></strong>
							<span class="bk-new-lead-item__meta">
								<?php echo esc_html( $lead['pool_shape_name'] ); ?>
								&mdash;
								<?php echo esc_html( $lead['suburb_name'] ); ?>
							</span>
						</div>
						<span class="bk-new-lead-item__time"><?php echo esc_html( $urgency_label ); ?></span>
						<a
							href="<?php echo esc_url( add_query_arg( array( 'section' => 'leads', 'lead_id' => $lead['lead_agent_id'] ), $panel_url ) ); ?>"
							class="bk-btn bk-btn--primary bk-btn--sm"
						>
							<?php esc_html_e( 'View', 'bk-agent-panel' ); ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>

	<?php if ( ! class_exists( 'BK_Feature_Toggles' ) || BK_Feature_Toggles::is_enabled( 'feature_top_agents' ) ) : ?>
	<!-- Leaderboard / Top Agents card -->
	<div class="bk-panel-card">
		<div class="bk-panel-card__header">
			<h2 class="bk-panel-card__title"><?php esc_html_e( 'Top Agents This Month', 'bk-agent-panel' ); ?></h2>
		</div>

		<?php if ( ! class_exists( 'BK_Feature_Toggles' ) || BK_Feature_Toggles::is_enabled( 'feature_leaderboard' ) ) : ?>
			<?php if ( empty( $data['leaderboard'] ) ) : ?>
				<p class="bk-empty-state"><?php esc_html_e( 'No sales recorded this month yet.', 'bk-agent-panel' ); ?></p>
			<?php else : ?>
				<table class="bk-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Rank', 'bk-agent-panel' ); ?></th>
							<th><?php esc_html_e( 'Company', 'bk-agent-panel' ); ?></th>
							<th class="bk-text-right"><?php esc_html_e( 'Sales', 'bk-agent-panel' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $data['leaderboard'] as $rank => $entry ) : ?>
							<tr class="<?php echo $entry['is_current_agent'] ? 'bk-table__row--highlight' : ''; ?>">
								<td><?php echo esc_html( $rank + 1 ); ?></td>
								<td>
									<?php echo esc_html( $entry['company_name'] ); ?>
									<?php if ( $entry['is_current_agent'] ) : ?>
										<span class="bk-badge bk-badge--accent"><?php esc_html_e( 'You', 'bk-agent-panel' ); ?></span>
									<?php endif; ?>
								</td>
								<td class="bk-text-right"><?php echo esc_html( $entry['won_this_month'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		<?php endif; ?><!-- /feature_leaderboard -->
	</div>
	<?php endif; ?><!-- /feature_top_agents -->

</div><!-- /.bk-dashboard-grid -->

<?php if ( class_exists( 'BK_Feature_Toggles' ) && BK_Feature_Toggles::is_enabled( 'feature_rewards' ) ) : ?>
<!-- Rewards section -->
<div class="bk-panel-card">
	<div class="bk-panel-card__header">
		<h2 class="bk-panel-card__title"><?php esc_html_e( 'Rewards & Milestones', 'bk-agent-panel' ); ?></h2>
	</div>
	<div class="bk-panel-card__body">
		<?php
		$total_sales   = (int) get_post_meta( $agent_post_id, 'total_sales_count', true );
		$monthly_sales = (int) get_post_meta( $agent_post_id, 'monthly_sales_count', true );

		$milestone_10 = (int) BK_Settings::get_setting( 'reward_milestone_10', 0 );
		$milestone_25 = (int) BK_Settings::get_setting( 'reward_milestone_25', 0 );
		$milestone_50 = (int) BK_Settings::get_setting( 'reward_milestone_50', 0 );
		$milestones   = array( 10 => $milestone_10, 25 => $milestone_25, 50 => $milestone_50 );

		$next_milestone = null;
		$next_bonus     = 0;
		foreach ( $milestones as $threshold => $bonus ) {
			if ( $bonus > 0 && $total_sales < $threshold ) {
				$next_milestone = $threshold;
				$next_bonus     = $bonus;
				break;
			}
		}
		?>
		<p>
			<?php
			printf(
				/* translators: 1: monthly sales count, 2: lifetime sales count */
				esc_html__( 'You have %1$d sale(s) this month and %2$d lifetime sales.', 'bk-agent-panel' ),
				$monthly_sales,
				$total_sales
			);
			?>
		</p>
		<?php if ( $next_milestone ) : ?>
			<p>
				<?php
				printf(
					/* translators: 1: remaining sales, 2: milestone threshold, 3: bonus amount */
					esc_html__( '%1$d more sale(s) to reach the %2$d-sale milestone — R%3$s bonus!', 'bk-agent-panel' ),
					$next_milestone - $total_sales,
					$next_milestone,
					number_format( $next_bonus )
				);
				?>
			</p>
		<?php else : ?>
			<p><?php esc_html_e( 'You have reached all available milestones — congratulations!', 'bk-agent-panel' ); ?></p>
		<?php endif; ?>
	</div>
</div>
<?php endif; ?><!-- /feature_rewards -->

<!-- Quick links -->
<div class="bk-quick-links">
	<a href="<?php echo esc_url( add_query_arg( 'section', 'leads', $panel_url ) ); ?>" class="bk-btn bk-btn--primary">
		<?php esc_html_e( 'View All Leads', 'bk-agent-panel' ); ?>
	</a>
	<a href="<?php echo esc_url( add_query_arg( 'section', 'pricing', $panel_url ) ); ?>" class="bk-btn bk-btn--secondary">
		<?php esc_html_e( 'Manage Pricing', 'bk-agent-panel' ); ?>
	</a>
	<a href="<?php echo esc_url( add_query_arg( 'section', 'profile', $panel_url ) ); ?>" class="bk-btn bk-btn--ghost">
		<?php esc_html_e( 'Edit Profile', 'bk-agent-panel' ); ?>
	</a>
</div>
