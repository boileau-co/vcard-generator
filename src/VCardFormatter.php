<?php

namespace VCardGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VCardFormatter {

	/**
	 * Build the vCard 3.0 string for a given post.
	 * Returns empty string if the post is invalid or inactive.
	 */
	public static function build( \WP_Post $post ): string {
		$fields = Fields::get_field_values( $post->ID );

		$first = $fields['first_name'];
		$last  = $fields['last_name'];

		if ( '' === $first && '' === $last ) {
			return '';
		}

		$e = [ Helpers::class, 'vcard_escape' ];

		$lines = [];
		$lines[] = 'BEGIN:VCARD';
		$lines[] = 'VERSION:3.0';

		// N: family;given;additional;prefix;suffix
		$lines[] = 'N:' . call_user_func( $e, $last ) . ';' . call_user_func( $e, $first ) . ';;;';

		// FN
		$full_name = trim( "$first $last" );
		$lines[] = 'FN:' . call_user_func( $e, $full_name );

		// ORG
		$org_name   = (string) get_option( 'vcard_generator_org_name', '' );
		$department = $fields['department'];
		if ( '' !== $org_name ) {
			if ( '' !== $department ) {
				$lines[] = 'ORG:' . call_user_func( $e, $org_name ) . ';' . call_user_func( $e, $department );
			} else {
				$lines[] = 'ORG:' . call_user_func( $e, $org_name );
			}
		}

		// TITLE
		if ( '' !== $fields['job_title'] ) {
			$lines[] = 'TITLE:' . call_user_func( $e, $fields['job_title'] );
		}

		// Mobile phone
		if ( '' !== $fields['mobile_phone'] ) {
			$lines[] = 'TEL;TYPE=CELL,VOICE:' . $fields['mobile_phone'];
		}

		// Work phone (falls back to org main phone if empty)
		$work_phone = $fields['work_phone'];
		if ( '' === $work_phone ) {
			$org_phone  = (string) get_option( 'vcard_generator_org_phone', '' );
			$work_phone = PhoneNormalizer::normalize( $org_phone );
		}
		if ( '' !== $work_phone ) {
			$lines[] = 'TEL;TYPE=WORK,VOICE:' . $work_phone;
		}

		// Email
		if ( '' !== $fields['email'] ) {
			$lines[] = 'EMAIL;TYPE=WORK:' . call_user_func( $e, $fields['email'] );
		}

		// Organization URL
		$org_url = (string) get_option( 'vcard_generator_org_website', '' );
		if ( '' !== $org_url ) {
			$lines[] = 'URL:' . call_user_func( $e, $org_url );
		}

		// Personal URL
		if ( '' !== $fields['personal_url'] ) {
			$lines[] = 'URL:' . call_user_func( $e, $fields['personal_url'] );
		}

		// LinkedIn URL
		if ( '' !== $fields['linkedin_url'] ) {
			$lines[] = 'URL:' . call_user_func( $e, $fields['linkedin_url'] );
		}

		// ADR: post office box;extended address;street;city;state;postal;country
		$street  = (string) get_option( 'vcard_generator_address_street', '' );
		$city    = (string) get_option( 'vcard_generator_address_city', '' );
		$state   = (string) get_option( 'vcard_generator_address_state', '' );
		$zip     = (string) get_option( 'vcard_generator_address_zip', '' );
		$country = (string) get_option( 'vcard_generator_address_country', '' );

		if ( $street || $city || $state || $zip || $country ) {
			$lines[] = 'ADR;TYPE=WORK:;;'
				. call_user_func( $e, $street ) . ';'
				. call_user_func( $e, $city ) . ';'
				. call_user_func( $e, $state ) . ';'
				. call_user_func( $e, $zip ) . ';'
				. call_user_func( $e, $country );
		}

		// REV: post modified time in UTC ISO 8601.
		$modified = get_post_modified_time( 'Y-m-d\TH:i:s\Z', true, $post );
		$lines[] = 'REV:' . $modified;

		$lines[] = 'END:VCARD';

		// Fold long lines and join with CRLF.
		$folded = array_map( [ Helpers::class, 'fold_line' ], $lines );
		return implode( "\r\n", $folded ) . "\r\n";
	}

	/**
	 * Return the .vcf filename for a post (firstname-lastname.vcf).
	 */
	public static function filename( \WP_Post $post ): string {
		$first = (string) get_post_meta( $post->ID, '_vcard_generator_first_name', true );
		$last  = (string) get_post_meta( $post->ID, '_vcard_generator_last_name', true );
		return sanitize_file_name( strtolower( trim( "$first-$last" ) ) ) . '.vcf';
	}
}
