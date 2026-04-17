<?php
/**
 * Panel layout wrapper.
 *
 * All panel section templates are loaded inside this wrapper.
 * Variables available from BK_Agent_Router::render_panel():
 *
 * @var int    $agent_post_id Builder CPT post ID.
 * @var string $section       Current section slug.
 * @var string $panel_url     Panel page permalink.
 *
 * @package BK_Agent_Panel
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$router        = new BK_Agent_Router();
$company_name  = (string) get_post_meta( $agent_post_id, 'company_name', true );
$contact_name  = (string) get_post_meta( $agent_post_id, 'contact_name', true );
$company_logo  = (int) get_post_meta( $agent_post_id, 'company_logo', true );
$logo_url      = $company_logo ? wp_get_attachment_url( $company_logo ) : '';
$bk_logo_url   = BK_Settings::get_setting( 'company_logo_id' )
	? wp_get_attachment_url( (int) BK_Settings::get_setting( 'company_logo_id' ) )
	: '';
$logout_url    = wp_logout_url( $panel_url );

$nav_items = array(
	'dashboard' => __( 'Dashboard', 'bk-agent-panel' ),
	'leads'     => __( 'Leads', 'bk-agent-panel' ),
	'pricing'   => __( 'Pricing', 'bk-agent-panel' ),
	'profile'   => __( 'Profile', 'bk-agent-panel' ),
);
?>
<div class="bk-agent-panel">

	<!-- =====================================================
	     HEADER
	     ===================================================== -->
	<header class="bk-panel-header">
		<div class="bk-panel-header__inner">
			<div class="bk-panel-header__brand">
				<?php if ( $bk_logo_url ) : ?>
					<img src="<?php echo esc_url( $bk_logo_url ); ?>" alt="BK Pools" class="bk-panel-header__logo">
				<?php else : ?>
					<span class="bk-panel-header__site-name"><?php echo esc_html( BK_Settings::get_setting( 'company_name', 'BK Pools' ) ); ?></span>
				<?php endif; ?>
			</div>

			<div class="bk-panel-header__agent">
				<?php if ( $logo_url ) : ?>
					<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $company_name ); ?>" class="bk-panel-header__agent-logo">
				<?php endif; ?>
				<span class="bk-panel-header__agent-name"><?php echo esc_html( $contact_name ?: $company_name ); ?></span>
			</div>

			<div class="bk-panel-header__actions">
				<button class="bk-panel-nav__toggle" aria-label="<?php esc_attr_e( 'Toggle navigation', 'bk-agent-panel' ); ?>" aria-expanded="false" data-bk-nav-toggle>
					<span></span><span></span><span></span>
				</button>
				<a href="<?php echo esc_url( $logout_url ); ?>" class="bk-btn bk-btn--ghost bk-btn--sm">
					<?php esc_html_e( 'Log Out', 'bk-agent-panel' ); ?>
				</a>
			</div>
		</div>
	</header>

	<!-- =====================================================
	     NAV
	     ===================================================== -->
	<nav class="bk-panel-nav" data-bk-nav>
		<ul class="bk-panel-nav__list">
			<?php foreach ( $nav_items as $slug => $label ) : ?>
				<li class="bk-panel-nav__item">
					<a
						href="<?php echo esc_url( add_query_arg( 'section', $slug, $panel_url ) ); ?>"
						class="bk-panel-nav__link<?php echo $section === $slug ? ' bk-panel-nav__link--active' : ''; ?>"
					>
						<?php echo esc_html( $label ); ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</nav>

	<!-- =====================================================
	     CONTENT
	     ===================================================== -->
	<main class="bk-panel-content">
		<?php
		$template_map = array(
			'dashboard' => 'dashboard.php',
			'leads'     => 'leads-list.php',
			'pricing'   => 'pricing.php',
			'profile'   => 'profile.php',
		);

		$template_file = BK_PANEL_PLUGIN_DIR . 'templates/' . ( $template_map[ $section ] ?? 'dashboard.php' );

		if ( file_exists( $template_file ) ) {
			include $template_file;
		}
		?>
	</main>

	<!-- =====================================================
	     FOOTER
	     ===================================================== -->
	<footer class="bk-panel-footer">
		<p>
			&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?>
			<?php echo esc_html( BK_Settings::get_setting( 'company_name', 'BK Pools' ) ); ?>
			&mdash; <?php esc_html_e( 'Powered by Lightning Digital', 'bk-agent-panel' ); ?>
		</p>
	</footer>

</div><!-- /.bk-agent-panel -->
