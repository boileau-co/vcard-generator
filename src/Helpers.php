<?php

namespace BCO\vCard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Helpers {

	/**
	 * Escape a vCard text value per RFC 6350.
	 * Escapes: \ ; , and newlines.
	 */
	public static function vcard_escape( string $value ): string {
		$value = str_replace( '\\', '\\\\', $value );
		$value = str_replace( ';', '\;', $value );
		$value = str_replace( ',', '\,', $value );
		$value = str_replace( "\r\n", '\n', $value );
		$value = str_replace( "\n", '\n', $value );
		return $value;
	}

	/**
	 * RFC 6350 line folding: lines over 75 octets must be folded.
	 * Fold by inserting CRLF + SPACE before the 75th octet boundary.
	 */
	public static function fold_line( string $line ): string {
		// Work in bytes (UTF-8 safe via mb_strlen with 8bit encoding).
		if ( strlen( $line ) <= 75 ) {
			return $line;
		}

		$out    = '';
		$offset = 0;
		$len    = strlen( $line );
		$first  = true;

		while ( $offset < $len ) {
			$chunk_max = $first ? 75 : 74; // First line 75, continuation lines 74 (+1 for the leading space).
			$chunk     = substr( $line, $offset, $chunk_max );
			if ( ! $first ) {
				$out .= "\r\n " . $chunk;
			} else {
				$out .= $chunk;
			}
			$offset += strlen( $chunk );
			$first   = false;
		}

		return $out;
	}

	/**
	 * Build a full public vCard URL for a given post slug.
	 */
	public static function vcard_url( string $slug ): string {
		$base = get_option( 'bco_vcard_slug_base', 'v' );
		return trailingslashit( home_url() ) . trailingslashit( $base ) . $slug;
	}
}
