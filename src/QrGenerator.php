<?php

namespace BCO\vCard;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class QrGenerator {

	/**
	 * Generate a QR code SVG string for the given URL.
	 */
	public static function svg( string $url, ?string $ecc_level = null ): string {
		if ( ! class_exists( QRCode::class ) ) {
			return '';
		}

		$options = new QROptions( [
			'outputType'  => QRCode::OUTPUT_SVG,
			'eccLevel'    => self::ecc_constant( $ecc_level ?? (string) get_option( 'bco_vcard_ecc_level', 'M' ) ),
			'quietZone'   => 4,
			'addQuietzone' => true,
			'moduleValues' => [
				// finder
				1536 => '#000000',
				6    => '#000000',
				// alignment
				2048 => '#000000',
				// timing
				3072 => '#000000',
				// format
				3584 => '#000000',
				// version
				4096 => '#000000',
				// data dark
				1024 => '#000000',
				// data light / background
				512  => '#FFFFFF',
				2    => '#FFFFFF',
				4    => '#FFFFFF',
			],
			'svgViewBoxSize' => null,
		] );

		return ( new QRCode( $options ) )->render( $url );
	}

	/**
	 * Generate a QR code PNG as raw binary (1024×1024).
	 */
	public static function png( string $url, ?string $ecc_level = null ): string {
		if ( ! class_exists( QRCode::class ) ) {
			return '';
		}

		$options = new QROptions( [
			'outputType'   => QRCode::OUTPUT_IMAGE_PNG,
			'eccLevel'     => self::ecc_constant( $ecc_level ?? (string) get_option( 'bco_vcard_ecc_level', 'M' ) ),
			'quietZone'    => 4,
			'addQuietzone' => true,
			'scale'        => 10, // 10px per module → ~100 modules × 10 = 1000px; oversized and cropped by ImageCreate at 1024.
			'imageBase64'  => false,
		] );

		return ( new QRCode( $options ) )->render( $url );
	}

	/**
	 * Map single-letter ECC level string to chillerlan constant.
	 */
	private static function ecc_constant( string $level ): int {
		return match( strtoupper( $level ) ) {
			'L'     => EccLevel::L,
			'Q'     => EccLevel::Q,
			'H'     => EccLevel::H,
			default => EccLevel::M,
		};
	}

	/**
	 * Return a data URI for an SVG (for inline img tags).
	 */
	public static function svg_data_uri( string $url, ?string $ecc_level = null ): string {
		$svg = self::svg( $url, $ecc_level );
		if ( '' === $svg ) {
			return '';
		}
		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}
}
