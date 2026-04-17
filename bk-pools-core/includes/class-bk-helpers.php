<?php
/**
 * BK Helpers
 *
 * Shared static utility methods used across all BK Pools plugins.
 *
 * @package BK_Pools_Core
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BK_Helpers
 *
 * Provides stateless helper methods for VAT calculation, currency formatting,
 * travel fee calculation, phone sanitisation, token generation, and estimate
 * expiry checking.
 *
 * All methods are static — instantiation is not required or intended.
 *
 * @since 1.0.0
 */
class BK_Helpers {

	/**
	 * Calculates the VAT-inclusive amount from an exclusive amount.
	 *
	 * The VAT rate is read from the BK Pools settings page; the constant
	 * BK_POOLS_VAT_RATE (0.15) is used as a hard fallback.
	 *
	 * @since 1.0.0
	 *
	 * @param float $amount_excl Amount excluding VAT.
	 * @return float Amount including VAT, rounded to 2 decimal places.
	 */
	public static function calculate_vat( float $amount_excl ): float {
		$rate = self::get_vat_rate();
		return round( $amount_excl * ( 1 + $rate ), 2 );
	}

	/**
	 * Formats a monetary amount as South African Rand.
	 *
	 * Output format: "R 1 234.56"
	 *  - Space as thousands separator.
	 *  - Period as decimal separator.
	 *  - "R " prefix (with a non-breaking space for clean display).
	 *
	 * @since 1.0.0
	 *
	 * @param float $amount The monetary amount to format.
	 * @return string Formatted currency string, e.g. "R 1 234.56".
	 */
	public static function format_currency( float $amount ): string {
		// number_format with a space as thousands separator.
		$formatted = number_format( abs( $amount ), 2, '.', ' ' );
		$prefix    = $amount < 0 ? '-R ' : 'R ';
		return $prefix . $formatted;
	}

	/**
	 * Returns the current VAT rate from BK Pools settings.
	 *
	 * Falls back to the BK_POOLS_VAT_RATE constant (0.15) if the settings
	 * class is unavailable or the setting has not been saved yet.
	 *
	 * @since 1.0.0
	 *
	 * @return float VAT rate as a decimal, e.g. 0.15 for 15%.
	 */
	public static function get_vat_rate(): float {
		if ( class_exists( 'BK_Settings' ) ) {
			return (float) BK_Settings::get_setting( 'vat_rate', BK_POOLS_VAT_RATE );
		}

		return defined( 'BK_POOLS_VAT_RATE' ) ? (float) BK_POOLS_VAT_RATE : 0.15;
	}

	/**
	 * Calculates the travel fee for an agent's service call to a lead suburb.
	 *
	 * Returns the fee EXCLUDING VAT. The calling code is responsible for
	 * applying VAT via calculate_vat() if required.
	 *
	 * For 'percentage_of_install' type, pass the optional $install_price_excl
	 * parameter. If omitted, the raw rate (as a decimal) is returned and the
	 * calling code must apply it to the install price.
	 *
	 * @since 1.0.0
	 *
	 * @param float                $distance_km      Distance from agent to suburb in kilometres.
	 * @param array<string, mixed> $agent_settings   Agent travel settings. Expected keys:
	 *                                                - travel_fee_enabled       (bool)
	 *                                                - travel_fee_min_distance_km (float)
	 *                                                - travel_fee_type          ('fixed_per_km'|'percentage_of_install')
	 *                                                - travel_fee_rate          (float)
	 * @param float|null           $install_price_excl Optional install price excl. VAT, used when
	 *                                                  type is 'percentage_of_install'.
	 * @return float Travel fee excluding VAT, rounded to 2 decimal places.
	 */
	public static function calculate_travel_fee(
		float $distance_km,
		array $agent_settings,
		?float $install_price_excl = null
	): float {
		// No travel fee configured.
		if ( empty( $agent_settings['travel_fee_enabled'] ) ) {
			return 0.00;
		}

		$min_distance = (float) ( $agent_settings['travel_fee_min_distance_km'] ?? 0 );

		// Within the free-travel zone.
		if ( $distance_km <= $min_distance ) {
			return 0.00;
		}

		$type          = (string) ( $agent_settings['travel_fee_type'] ?? 'fixed_per_km' );
		$rate          = (float) ( $agent_settings['travel_fee_rate'] ?? 0 );
		$excess_km     = $distance_km - $min_distance;

		if ( 'fixed_per_km' === $type ) {
			return round( $excess_km * $rate, 2 );
		}

		if ( 'percentage_of_install' === $type ) {
			if ( null !== $install_price_excl ) {
				return round( $install_price_excl * $rate, 2 );
			}

			// Caller did not supply install price — return the raw rate so they can apply it.
			return round( $rate, 4 );
		}

		return 0.00;
	}

	/**
	 * Sanitises a phone number string.
	 *
	 * Strips all non-numeric characters except a leading "+" (international prefix).
	 *
	 * @since 1.0.0
	 *
	 * @param string $phone Raw phone number input.
	 * @return string Sanitised phone number, e.g. "+27821234567".
	 */
	public static function sanitise_phone( string $phone ): string {
		$phone   = trim( $phone );
		$leading = str_starts_with( $phone, '+' ) ? '+' : '';
		$digits  = preg_replace( '/[^0-9]/', '', $phone );

		return $leading . ( $digits ?? '' );
	}

	/**
	 * Generates a cryptographically random 32-character token.
	 *
	 * Used to create unique, unguessable URLs for estimate PDF downloads
	 * and other one-time access links.
	 *
	 * @since 1.0.0
	 *
	 * @return string 32-character alphanumeric token.
	 */
	public static function generate_estimate_token(): string {
		return wp_generate_password( 32, false, false );
	}

	/**
	 * Determines whether an estimate has passed its validity expiry date.
	 *
	 * @since 1.0.0
	 *
	 * @param string $created_date ISO 8601 or MySQL datetime string (e.g. "2026-01-15 10:30:00").
	 * @return bool True if the estimate has expired, false if still valid or if the date is unparseable.
	 */
	public static function is_estimate_expired( string $created_date ): bool {
		$validity_days = (int) BK_Settings::get_setting( 'estimate_validity_days', 30 );

		$created_ts = strtotime( $created_date );
		if ( false === $created_ts ) {
			// Unparseable date — treat as not expired to avoid false positives.
			error_log( 'BK Pools Helpers — could not parse estimate created_date: ' . $created_date );
			return false;
		}

		$expiry_ts = strtotime( "+{$validity_days} days", $created_ts );

		return time() > $expiry_ts;
	}
}
