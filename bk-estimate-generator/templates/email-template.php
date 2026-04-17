<?php
/**
 * HTML email body template.
 *
 * Loaded by BK_Estimate_Email::send() via ob_start() / include.
 * Uses inline styles throughout for email client compatibility.
 *
 * @package BK_Estimate_Generator
 * @since   1.0.0
 *
 * @var array $data Structured estimate data from BK_Estimate_Builder::build().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$estimate = $data['estimate'];
$customer = $data['customer'];
$pool     = $data['pool_shape'];
$agents   = $data['agents'];
$company  = $data['company'];

$first_name = esc_html( explode( ' ', trim( $customer['name'] ) )[0] );
?>
<!DOCTYPE html>
<html lang="en-ZA">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( sprintf( 'Your Pool Estimate — %s', $pool['name'] ) ); ?></title>
</head>
<body style="margin:0;padding:0;background:#f4f7fa;font-family:Arial,Helvetica,sans-serif;color:#333333;">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f7fa;padding:32px 16px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.07);">

	<!-- Header -->
	<tr>
		<td style="background:#1a6ea8;padding:28px 32px;text-align:center;">
			<?php if ( $company['logo_url'] ) : ?>
				<img src="<?php echo esc_url( $company['logo_url'] ); ?>" alt="<?php echo esc_attr( $company['name'] ); ?>" style="max-height:50px;max-width:180px;display:inline-block;">
			<?php else : ?>
				<span style="color:#ffffff;font-size:22px;font-weight:bold;"><?php echo esc_html( $company['name'] ); ?></span>
			<?php endif; ?>
		</td>
	</tr>

	<!-- Greeting -->
	<tr>
		<td style="padding:32px 32px 0;">
			<h1 style="font-size:22px;color:#1a4a6e;margin:0 0 12px;">
				<?php echo esc_html( sprintf( 'Hi %s,', $first_name ) ); ?>
			</h1>
			<p style="font-size:15px;line-height:1.6;color:#444;margin:0 0 16px;">
				<?php
				echo esc_html( sprintf(
					'Thank you for your interest in the %s. We\'ve prepared estimates from %d pool installation specialists in your area.',
					$pool['name'],
					count( $agents )
				) );
				?>
			</p>
			<p style="font-size:14px;color:#666;margin:0 0 24px;">
				<?php echo esc_html( sprintf( 'Location: %s, %s, %s', $customer['suburb'], $customer['area'], $customer['province'] ) ); ?>
			</p>
		</td>
	</tr>

	<!-- Quote summary rows -->
	<tr>
		<td style="padding:0 32px 24px;">
			<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
				<thead>
					<tr style="background:#f0f5fa;">
						<th style="padding:10px 12px;text-align:left;font-size:12px;color:#555;border-bottom:2px solid #dce6f0;font-weight:bold;">
							<?php esc_html_e( 'Specialist', 'bk-estimate-generator' ); ?>
						</th>
						<th style="padding:10px 12px;text-align:right;font-size:12px;color:#555;border-bottom:2px solid #dce6f0;font-weight:bold;">
							<?php esc_html_e( 'Total (incl. VAT)', 'bk-estimate-generator' ); ?>
						</th>
						<th style="padding:10px 12px;text-align:right;font-size:12px;color:#555;border-bottom:2px solid #dce6f0;font-weight:bold;">
							<?php esc_html_e( 'Distance', 'bk-estimate-generator' ); ?>
						</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $agents as $i => $agent ) : ?>
						<tr style="background:<?php echo $i % 2 === 0 ? '#ffffff' : '#f7fafd'; ?>;">
							<td style="padding:10px 12px;font-size:14px;color:#333;border-bottom:1px solid #eee;">
								<?php echo esc_html( $agent['company_name'] ); ?>
							</td>
							<td style="padding:10px 12px;font-size:14px;font-weight:bold;color:#1a6ea8;text-align:right;border-bottom:1px solid #eee;">
								<?php echo esc_html( BK_Helpers::format_currency( $agent['total_estimate_incl'] ) ); ?>
							</td>
							<td style="padding:10px 12px;font-size:13px;color:#666;text-align:right;border-bottom:1px solid #eee;">
								<?php echo esc_html( number_format( (float) $agent['distance_km'], 1 ) ); ?> km
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</td>
	</tr>

	<!-- CTA button -->
	<tr>
		<td style="padding:0 32px 32px;text-align:center;">
			<p style="font-size:14px;color:#555;margin:0 0 20px;">
				<?php esc_html_e( 'View the full breakdown — including pricing details, dimensions, agent contacts, terms and payment structures — by clicking the button below.', 'bk-estimate-generator' ); ?>
			</p>
			<a
				href="<?php echo esc_url( $estimate['url'] ); ?>"
				style="display:inline-block;background:#1a6ea8;color:#ffffff;text-decoration:none;font-size:15px;font-weight:bold;padding:14px 32px;border-radius:6px;"
			>
				<?php esc_html_e( 'View Your Full Estimate Online', 'bk-estimate-generator' ); ?>
			</a>
		</td>
	</tr>

	<!-- Validity notice -->
	<tr>
		<td style="background:#f0f5fa;padding:16px 32px;border-top:1px solid #dce6f0;">
			<p style="font-size:13px;color:#666;margin:0;">
				<?php
				echo esc_html( sprintf(
					'Your estimate is valid until %s. After this date, prices may be subject to change.',
					$estimate['expiry_display']
				) );
				?>
			</p>
		</td>
	</tr>

	<!-- Attachment note (only if PDFs attached) -->
	<tr>
		<td style="padding:16px 32px 0;">
			<p style="font-size:13px;color:#555;margin:0;">
				<?php esc_html_e( 'We have attached a detailed PDF estimate from each specialist to this email for your records.', 'bk-estimate-generator' ); ?>
			</p>
		</td>
	</tr>

	<!-- Contact us -->
	<tr>
		<td style="padding:20px 32px 32px;">
			<p style="font-size:13px;color:#666;margin:0;">
				<?php esc_html_e( 'If you have any questions, please contact us:', 'bk-estimate-generator' ); ?>
				<?php if ( $company['phone'] ) : ?>
					<a href="tel:<?php echo esc_attr( $company['phone'] ); ?>" style="color:#1a6ea8;"><?php echo esc_html( $company['phone'] ); ?></a>
				<?php endif; ?>
				<?php if ( $company['phone'] && $company['email'] ) : ?>
					<?php esc_html_e( 'or', 'bk-estimate-generator' ); ?>
				<?php endif; ?>
				<?php if ( $company['email'] ) : ?>
					<a href="mailto:<?php echo esc_attr( $company['email'] ); ?>" style="color:#1a6ea8;"><?php echo esc_html( $company['email'] ); ?></a>
				<?php endif; ?>
			</p>
		</td>
	</tr>

	<!-- Footer -->
	<tr>
		<td style="background:#1a4a6e;padding:20px 32px;text-align:center;">
			<p style="color:#a8bfd4;font-size:11px;margin:0 0 4px;">
				<?php echo esc_html( $company['name'] ); ?>
			</p>
			<p style="color:#6a8fb0;font-size:10px;margin:0;">
				<?php
				printf(
					/* translators: %s: estimate reference token */
					esc_html__( 'Estimate reference: %s', 'bk-estimate-generator' ),
					esc_html( $estimate['token'] )
				);
				?>
			</p>
		</td>
	</tr>

</table>
</td></tr>
</table>

</body>
</html>
