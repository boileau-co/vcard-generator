<?php

namespace VCardGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PhoneNormalizer {

	/**
	 * Normalize a phone number to E.164 format (+XXXXXXXXXXX).
	 * Returns empty string if the number is empty or unparseable.
	 */
	public static function normalize( string $raw ): string {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return '';
		}

		// Strip everything except digits and leading +.
		$digits_only = preg_replace( '/[^\d+]/', '', $raw );

		// If it already has a leading + we keep it; otherwise assume US/Canada (+1).
		if ( str_starts_with( $digits_only, '+' ) ) {
			$e164 = $digits_only;
		} else {
			// Strip leading country code if user already included a leading 1 for NANP.
			if ( strlen( $digits_only ) === 11 && str_starts_with( $digits_only, '1' ) ) {
				$e164 = '+' . $digits_only;
			} elseif ( strlen( $digits_only ) === 10 ) {
				$e164 = '+1' . $digits_only;
			} else {
				// For international numbers without a +, trust the input as-is.
				$e164 = '+' . $digits_only;
			}
		}

		// Validate: E.164 is + followed by 7–15 digits.
		if ( ! preg_match( '/^\+\d{7,15}$/', $e164 ) ) {
			return '';
		}

		return $e164;
	}
}
