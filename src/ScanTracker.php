<?php

namespace VCardGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ScanTracker {

	public static function register(): void {
		// Nothing to hook at registration time; recording happens in Rewrite::handle_request().
	}

	/**
	 * Increment the scan counter for a post if:
	 *  - tracking is enabled in settings, and
	 *  - the request is not from a known bot.
	 */
	public static function record( int $post_id ): void {
		if ( ! (bool) get_option( 'vcard_generator_scan_tracking', true ) ) {
			return;
		}

		if ( self::is_bot() ) {
			return;
		}

		$count = (int) get_post_meta( $post_id, '_vcard_generator_scan_count', true );
		update_post_meta( $post_id, '_vcard_generator_scan_count', $count + 1 );
		update_post_meta( $post_id, '_vcard_generator_last_scanned', current_time( 'mysql', true ) );
	}

	/**
	 * Determine if the current request is from a known bot.
	 */
	private static function is_bot(): bool {
		$ua = strtolower( (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ) );
		if ( '' === $ua ) {
			return false;
		}

		$bots = apply_filters( 'vcard_generator_bot_user_agents', self::default_bot_list() );

		foreach ( $bots as $bot ) {
			if ( str_contains( $ua, strtolower( (string) $bot ) ) ) {
				return true;
			}
		}

		return false;
	}

	private static function default_bot_list(): array {
		return [
			'googlebot',
			'bingbot',
			'duckduckbot',
			'baiduspider',
			'yandexbot',
			'slurp',
			'facebookexternalhit',
			'twitterbot',
			'linkedinbot',
			'slackbot',
			'discordbot',
			'whatsapp',
			'telegrambot',
			'skypeuripreview',
			'applebot',
			'uptimerobot',
			'pingdom',
			'gtmetrix',
			'semrushbot',
			'ahrefsbot',
			'mj12bot',
			'dotbot',
		];
	}
}
