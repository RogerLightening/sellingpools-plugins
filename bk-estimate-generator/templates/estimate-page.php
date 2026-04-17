<?php
/**
 * Public estimate page template.
 *
 * Standalone full-page template — no WordPress theme chrome.
 * Loaded by BK_Estimate_Page::handle_template_redirect() with $data in scope.
 *
 * @package BK_Estimate_Generator
 * @since   1.0.0
 *
 * @var array $data Structured estimate data from BK_Estimate_Builder::build().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$estimate  = $data['estimate'];
$customer  = $data['customer'];
$pool      = $data['pool_shape'];
$agents    = $data['agents'];
$company   = $data['company'];
$vat_label = esc_html( $data['vat_display'] );

wp_head();
?>
<!DOCTYPE html>
<html lang="en-ZA">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>
		<?php
		echo esc_html(
			sprintf(
				'Your Pool Estimate — %s | %s',
				$pool['name'],
				$company['name']
			)
		);
		?>
	</title>
	<?php wp_head(); ?>
</head>
<body class="bk-estimate-body">

<!-- =========================================================
     HEADER
     ========================================================= -->
<header class="bk-estimate-header">
	<div class="bk-estimate-container">
		<div class="bk-estimate-header__inner">
			<?php if ( $company['logo_url'] ) : ?>
				<img
					class="bk-estimate-header__logo"
					src="<?php echo esc_url( $company['logo_url'] ); ?>"
					alt="<?php echo esc_attr( $company['name'] ); ?>"
				>
			<?php else : ?>
				<span class="bk-estimate-header__company-name"><?php echo esc_html( $company['name'] ); ?></span>
			<?php endif; ?>

			<div class="bk-estimate-header__meta">
				<p class="bk-estimate-header__valid">
					<?php
					printf(
						/* translators: %s: formatted expiry date */
						esc_html__( 'Valid until %s', 'bk-estimate-generator' ),
						'<strong>' . esc_html( $estimate['expiry_display'] ) . '</strong>'
					);
					?>
				</p>
			</div>
		</div>
	</div>
</header>

<!-- =========================================================
     HERO: Customer + Pool Summary
     ========================================================= -->
<section class="bk-estimate-hero">
	<div class="bk-estimate-container">
		<h1 class="bk-estimate-hero__heading">
			<?php
			printf(
				/* translators: %s: customer first name */
				esc_html__( 'Hi %s, here is your pool estimate', 'bk-estimate-generator' ),
				esc_html( explode( ' ', trim( $customer['name'] ) )[0] )
			);
			?>
		</h1>
		<p class="bk-estimate-hero__sub">
			<?php
			printf(
				/* translators: 1: suburb, 2: pool shape name */
				esc_html__( 'Based on your location in %1$s, we have matched you with %2$d pool installation specialists for the %3$s.', 'bk-estimate-generator' ),
				esc_html( $customer['suburb'] ),
				count( $agents ),
				esc_html( $pool['name'] )
			);
			?>
		</p>
	</div>
</section>

<!-- =========================================================
     POOL SHAPE DETAILS
     ========================================================= -->
<section class="bk-estimate-pool">
	<div class="bk-estimate-container">
		<h2 class="bk-estimate-section-heading">
			<?php echo esc_html( $pool['name'] ); ?>
		</h2>

		<div class="bk-estimate-pool__inner">

			<?php if ( $pool['diagram_url'] ) : ?>
				<div class="bk-estimate-pool__image">
					<img
						src="<?php echo esc_url( $pool['diagram_url'] ); ?>"
						alt="<?php echo esc_attr( $pool['name'] ); ?> diagram"
					>
				</div>
			<?php endif; ?>

			<div class="bk-estimate-pool__specs">
				<dl class="bk-estimate-specs">
					<div class="bk-estimate-specs__row">
						<dt><?php esc_html_e( 'Length', 'bk-estimate-generator' ); ?></dt>
						<dd><?php echo esc_html( $pool['dimensions_length'] ); ?> m</dd>
					</div>
					<div class="bk-estimate-specs__row">
						<dt><?php esc_html_e( 'Width', 'bk-estimate-generator' ); ?></dt>
						<dd><?php echo esc_html( $pool['dimensions_width'] ); ?> m</dd>
					</div>
					<div class="bk-estimate-specs__row">
						<dt><?php esc_html_e( 'Depth (shallow end)', 'bk-estimate-generator' ); ?></dt>
						<dd><?php echo esc_html( $pool['depth_shallow'] ); ?> m</dd>
					</div>
					<div class="bk-estimate-specs__row">
						<dt><?php esc_html_e( 'Depth (deep end)', 'bk-estimate-generator' ); ?></dt>
						<dd><?php echo esc_html( $pool['depth_deep'] ); ?> m</dd>
					</div>
					<?php if ( $pool['water_volume'] ) : ?>
					<div class="bk-estimate-specs__row">
						<dt><?php esc_html_e( 'Water volume', 'bk-estimate-generator' ); ?></dt>
						<dd><?php echo esc_html( number_format( $pool['water_volume'] ) ); ?> litres</dd>
					</div>
					<?php endif; ?>
				</dl>

				<?php if ( $pool['description'] ) : ?>
					<div class="bk-estimate-pool__description">
						<?php echo wp_kses_post( $pool['description'] ); ?>
					</div>
				<?php endif; ?>
			</div>

		</div><!-- /.bk-estimate-pool__inner -->
	</div>
</section>

<!-- =========================================================
     AGENT QUOTE CARDS
     ========================================================= -->
<section class="bk-estimate-quotes">
	<div class="bk-estimate-container">
		<h2 class="bk-estimate-section-heading">
			<?php esc_html_e( 'Your Quotes', 'bk-estimate-generator' ); ?>
		</h2>
		<p class="bk-estimate-quotes__note">
			<?php
			printf(
				/* translators: %s: VAT percentage */
				esc_html__( 'All prices include VAT at %s. Quotes are sorted from lowest to highest total.', 'bk-estimate-generator' ),
				esc_html( $data['vat_display'] )
			);
			?>
		</p>

		<div class="bk-estimate-cards">
			<?php foreach ( $agents as $index => $agent ) : ?>
				<?php include BK_ESTIMATOR_PLUGIN_DIR . 'templates/estimate-agent-card.php'; ?>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<!-- =========================================================
     FOOTER
     ========================================================= -->
<footer class="bk-estimate-footer">
	<div class="bk-estimate-container">
		<div class="bk-estimate-footer__inner">
			<div class="bk-estimate-footer__branding">
				<?php if ( $company['logo_url'] ) : ?>
					<img
						src="<?php echo esc_url( $company['logo_url'] ); ?>"
						alt="<?php echo esc_attr( $company['name'] ); ?>"
						class="bk-estimate-footer__logo"
					>
				<?php else : ?>
					<strong><?php echo esc_html( $company['name'] ); ?></strong>
				<?php endif; ?>
			</div>
			<div class="bk-estimate-footer__contact">
				<?php if ( $company['phone'] ) : ?>
					<a href="tel:<?php echo esc_attr( $company['phone'] ); ?>">
						<?php echo esc_html( $company['phone'] ); ?>
					</a>
				<?php endif; ?>
				<?php if ( $company['email'] ) : ?>
					<a href="mailto:<?php echo esc_attr( $company['email'] ); ?>">
						<?php echo esc_html( $company['email'] ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>
		<p class="bk-estimate-footer__disclaimer">
			<?php
			printf(
				/* translators: %s: formatted expiry date */
				esc_html__( 'This estimate is valid until %s. Prices are subject to change after this date. Contact the agent directly to accept a quote or arrange a site inspection.', 'bk-estimate-generator' ),
				esc_html( $estimate['expiry_display'] )
			);
			?>
		</p>
		<p class="bk-estimate-footer__credit">
			<?php
			printf(
				/* translators: %s: SellingPools.com link */
				esc_html__( 'Developed by: %s', 'bk-estimate-generator' ),
				'<a href="https://sellingpools.com" target="_blank" rel="noopener noreferrer">SellingPools.com</a>'
			);
			?>
		</p>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
