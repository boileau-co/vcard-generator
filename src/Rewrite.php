<?php

namespace VCardGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rewrite {

	const QUERY_VAR = 'vcard_generator_slug';

	public static function register(): void {
		add_action( 'init', [ self::class, 'add_rewrite_rule' ] );
		add_filter( 'query_vars', [ self::class, 'add_query_var' ] );
		add_action( 'template_redirect', [ self::class, 'handle_request' ] );
	}

	public static function add_rewrite_rule(): void {
		$base = get_option( 'vcard_generator_slug_base', 'v' );
		add_rewrite_rule(
			'^' . preg_quote( $base, '/' ) . '/([^/]+)/?$',
			'index.php?' . self::QUERY_VAR . '=$matches[1]',
			'top'
		);
	}

	public static function add_query_var( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	public static function handle_request(): void {
		$slug = get_query_var( self::QUERY_VAR );
		if ( ! $slug ) {
			return;
		}

		$post = self::get_active_post( $slug );

		if ( ! $post ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			include get_query_template( '404' );
			exit;
		}

		$is_head = ( isset( $_SERVER['REQUEST_METHOD'] ) && strtoupper( $_SERVER['REQUEST_METHOD'] ) === 'HEAD' );

		// Increment scan count (not for HEAD requests).
		if ( ! $is_head ) {
			ScanTracker::record( $post->ID );
		}

		$vcf      = VCardFormatter::build( $post );
		$filename = VCardFormatter::filename( $post );

		header( 'Content-Type: text/vcard; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
		header( 'X-Accel-Expires: 0' );

		if ( ! $is_head ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $vcf;
		}

		exit;
	}

	private static function get_active_post( string $slug ): ?\WP_Post {
		$posts = get_posts( [
			'post_type'      => PostType::SLUG,
			'post_status'    => 'publish',
			'name'           => $slug,
			'posts_per_page' => 1,
			'no_found_rows'  => true,
		] );

		if ( empty( $posts ) ) {
			return null;
		}

		$post = $posts[0];

		// Check active toggle (defaults to on for published posts without the meta).
		$active = get_post_meta( $post->ID, '_vcard_generator_active', true );
		if ( $active === '0' ) {
			return null;
		}

		return $post;
	}

	public static function flush(): void {
		self::add_rewrite_rule();
		flush_rewrite_rules();
	}
}
