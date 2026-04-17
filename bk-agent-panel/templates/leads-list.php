<?php
/**
 * Leads list template.
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

// phpcs:disable WordPress.Security.NonceVerification.Recommended
$current_status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : 'all';
$search         = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';
$current_page   = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
$lead_id        = isset( $_GET['lead_id'] ) ? (int) $_GET['lead_id'] : 0;
// phpcs:enable

$status_filter = ( 'all' !== $current_status && in_array( $current_status, BK_Agent_Leads::VALID_STATUSES, true ) )
	? $current_status : null;

$result = BK_Agent_Leads::get_leads(
	$agent_post_id,
	array(
		'status'   => $status_filter,
		'page'     => $current_page,
		'per_page' => 20,
		'orderby'  => 'date',
		'order'    => 'DESC',
		'search'   => $search ?: null,
	)
);

$counts = BK_Agent_Leads::get_status_counts( $agent_post_id );

$status_labels = array(
	'all'          => __( 'All', 'bk-agent-panel' ),
	'new'          => __( 'New', 'bk-agent-panel' ),
	'contacted'    => __( 'Contacted', 'bk-agent-panel' ),
	'no_answer'    => __( 'No Answer', 'bk-agent-panel' ),
	'wrong_number' => __( 'Wrong Number', 'bk-agent-panel' ),
	'site_visit'   => __( 'Site Visit', 'bk-agent-panel' ),
	'quoted'       => __( 'Quoted', 'bk-agent-panel' ),
	'won'          => __( 'Won', 'bk-agent-panel' ),
	'lost'         => __( 'Lost', 'bk-agent-panel' ),
	'stale'        => __( 'Stale', 'bk-agent-panel' ),
);

$all_statuses = array_merge( array( 'all' ), BK_Agent_Leads::VALID_STATUSES );
?>

<div class="bk-section-header">
	<h1 class="bk-section-title"><?php esc_html_e( 'My Leads', 'bk-agent-panel' ); ?></h1>
</div>

<!-- Status filter tabs -->
<div class="bk-status-tabs" role="tablist">
	<?php foreach ( $all_statuses as $status_slug ) : ?>
		<?php
		$tab_url  = remove_query_arg( 'paged', add_query_arg( array( 'section' => 'leads', 'status' => $status_slug ), $panel_url ) );
		$is_active = $current_status === $status_slug;
		$count     = $counts[ $status_slug ] ?? 0;
		?>
		<a
			href="<?php echo esc_url( $tab_url ); ?>"
			class="bk-status-tab<?php echo $is_active ? ' bk-status-tab--active' : ''; ?> bk-status-tab--<?php echo esc_attr( $status_slug ); ?>"
			role="tab"
			aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
		>
			<?php echo esc_html( $status_labels[ $status_slug ] ?? $status_slug ); ?>
			<span class="bk-status-tab__count"><?php echo esc_html( $count ); ?></span>
		</a>
	<?php endforeach; ?>
</div>

<!-- Search -->
<form class="bk-search-bar" method="get" action="">
	<input type="hidden" name="section" value="leads">
	<?php if ( $status_filter ) : ?>
		<input type="hidden" name="status" value="<?php echo esc_attr( $current_status ); ?>">
	<?php endif; ?>
	<input
		type="search"
		name="search"
		class="bk-form-input bk-search-bar__input"
		placeholder="<?php esc_attr_e( 'Search by customer name…', 'bk-agent-panel' ); ?>"
		value="<?php echo esc_attr( $search ); ?>"
	>
	<button type="submit" class="bk-btn bk-btn--secondary"><?php esc_html_e( 'Search', 'bk-agent-panel' ); ?></button>
	<?php if ( $search ) : ?>
		<a href="<?php echo esc_url( remove_query_arg( 'search' ) ); ?>" class="bk-btn bk-btn--ghost"><?php esc_html_e( 'Clear', 'bk-agent-panel' ); ?></a>
	<?php endif; ?>
</form>

<!-- Leads table -->
<?php if ( empty( $result['leads'] ) ) : ?>
	<div class="bk-empty-state bk-empty-state--card">
		<p><?php esc_html_e( 'No leads found matching your filters.', 'bk-agent-panel' ); ?></p>
	</div>
<?php else : ?>
	<div class="bk-table-wrapper">
		<table class="bk-table bk-leads-table" id="bk-leads-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Date', 'bk-agent-panel' ); ?></th>
					<th><?php esc_html_e( 'Customer', 'bk-agent-panel' ); ?></th>
					<th><?php esc_html_e( 'Pool Shape', 'bk-agent-panel' ); ?></th>
					<th><?php esc_html_e( 'Suburb', 'bk-agent-panel' ); ?></th>
					<th><?php esc_html_e( 'Distance', 'bk-agent-panel' ); ?></th>
					<th><?php esc_html_e( 'Status', 'bk-agent-panel' ); ?></th>
					<?php if ( ! class_exists( 'BK_Feature_Toggles' ) || BK_Feature_Toggles::is_enabled( 'feature_star_ratings' ) ) : ?>
					<th><?php esc_html_e( 'Rating', 'bk-agent-panel' ); ?></th>
					<?php endif; ?>
					<th class="bk-text-right"><?php esc_html_e( 'Total', 'bk-agent-panel' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $result['leads'] as $lead ) : ?>
					<?php
					$hours = (int) $lead['hours_since_assigned'];

					if ( 'new' === $lead['lead_status'] ) {
						if ( $hours < 24 ) {
							$urgency_class = 'bk-urgency--green';
						} elseif ( $hours < 48 ) {
							$urgency_class = 'bk-urgency--amber';
						} else {
							$urgency_class = 'bk-urgency--red';
						}
					} else {
						$urgency_class = '';
					}

					$row_id     = 'bk-lead-' . (int) $lead['lead_agent_id'];
					$detail_id  = 'bk-detail-' . (int) $lead['lead_agent_id'];
					$is_open    = $lead_id === $lead['lead_agent_id'];
					?>

					<!-- Lead row -->
					<tr
						class="bk-leads-table__row<?php echo $is_open ? ' bk-leads-table__row--open' : ''; ?>"
						id="<?php echo esc_attr( $row_id ); ?>"
						data-lead-id="<?php echo esc_attr( $lead['lead_agent_id'] ); ?>"
					>
						<td>
							<?php if ( $urgency_class ) : ?>
								<span class="bk-urgency-dot <?php echo esc_attr( $urgency_class ); ?>"></span>
							<?php endif; ?>
							<?php echo esc_html( date_i18n( 'j M Y', strtotime( $lead['assigned_at'] ) ) ); ?>
						</td>
						<td>
							<strong><?php echo esc_html( $lead['customer_name'] ); ?></strong>
						</td>
						<td><?php echo esc_html( $lead['pool_shape_name'] ); ?></td>
						<td><?php echo esc_html( $lead['suburb_name'] ); ?><?php if ( $lead['area_name'] ) : ?>, <small><?php echo esc_html( $lead['area_name'] ); ?></small><?php endif; ?></td>
						<td><?php echo esc_html( number_format( $lead['distance_km'], 1 ) ); ?> km</td>
						<td>
							<select
								class="bk-status-select bk-status-select--<?php echo esc_attr( $lead['lead_status'] ); ?>"
								data-lead-agent-id="<?php echo esc_attr( $lead['lead_agent_id'] ); ?>"
								data-bk-status-select
							>
								<?php foreach ( BK_Agent_Leads::VALID_STATUSES as $s ) : ?>
									<option value="<?php echo esc_attr( $s ); ?>"<?php selected( $lead['lead_status'], $s ); ?>>
										<?php echo esc_html( $status_labels[ $s ] ?? $s ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
						<?php if ( ! class_exists( 'BK_Feature_Toggles' ) || BK_Feature_Toggles::is_enabled( 'feature_star_ratings' ) ) : ?>
						<td>
							<span class="bk-star-rating" data-lead-agent-id="<?php echo esc_attr( $lead['lead_agent_id'] ); ?>" data-rating="<?php echo esc_attr( $lead['lead_quality_rating'] ?? 0 ); ?>" data-bk-stars>
								<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
									<button
										type="button"
										class="bk-star<?php echo ( $lead['lead_quality_rating'] && $i <= $lead['lead_quality_rating'] ) ? ' bk-star--filled' : ''; ?>"
										data-value="<?php echo esc_attr( $i ); ?>"
										aria-label="<?php echo esc_attr( sprintf( __( 'Rate %d', 'bk-agent-panel' ), $i ) ); ?>"
									>&#9733;</button>
								<?php endfor; ?>
							</span>
						</td>
						<?php endif; ?>
						<td class="bk-text-right">
							<?php echo esc_html( BK_Helpers::format_currency( $lead['total_estimate_incl'] ) ); ?>
						</td>
						<td>
							<button type="button" class="bk-btn bk-btn--ghost bk-btn--sm bk-lead-toggle-btn" data-bk-lead-toggle="<?php echo esc_attr( $detail_id ); ?>" aria-expanded="<?php echo $is_open ? 'true' : 'false'; ?>">
								<span class="bk-lead-toggle-btn__icon"><?php echo $is_open ? '&#9650;' : '&#9660;'; ?></span>
							</button>
						</td>
					</tr>

					<!-- Detail row -->
					<tr class="bk-leads-table__detail<?php echo $is_open ? '' : ' bk-hidden'; ?>" id="<?php echo esc_attr( $detail_id ); ?>">
						<td colspan="<?php echo ( ! class_exists( 'BK_Feature_Toggles' ) || BK_Feature_Toggles::is_enabled( 'feature_star_ratings' ) ) ? '9' : '8'; ?>">
							<?php include BK_PANEL_PLUGIN_DIR . 'templates/lead-detail.php'; ?>
						</td>
					</tr>

				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<!-- Pagination -->
	<?php if ( $result['pages'] > 1 ) : ?>
		<div class="bk-pagination">
			<?php if ( $current_page > 1 ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'paged', $current_page - 1 ) ); ?>" class="bk-btn bk-btn--ghost bk-btn--sm">
					&laquo; <?php esc_html_e( 'Previous', 'bk-agent-panel' ); ?>
				</a>
			<?php endif; ?>

			<span class="bk-pagination__info">
				<?php
				printf(
					/* translators: 1: current page, 2: total pages */
					esc_html__( 'Page %1$d of %2$d', 'bk-agent-panel' ),
					$current_page,
					$result['pages']
				);
				?>
			</span>

			<?php if ( $current_page < $result['pages'] ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'paged', $current_page + 1 ) ); ?>" class="bk-btn bk-btn--ghost bk-btn--sm">
					<?php esc_html_e( 'Next', 'bk-agent-panel' ); ?> &raquo;
				</a>
			<?php endif; ?>
		</div>
	<?php endif; ?>

<?php endif; ?>
