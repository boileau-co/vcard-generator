<?php

namespace VCardGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PostType {

	const SLUG = 'vcard_generator';

	/** Slug auto-derived from the names as they were *before* the current save. */
	private static string $prev_derived_slug = '';

	public static function register(): void {
		add_action( 'init', [ self::class, 'register_cpt' ] );
		add_action( 'admin_menu', [ self::class, 'add_top_level_menu' ] );
		add_action( 'pre_post_update', [ self::class, 'capture_prev_slug' ] );
		add_action( 'save_post_' . self::SLUG, [ self::class, 'sync_title' ], 10, 2 );
	}

	public static function register_cpt(): void {
		$labels = [
			'name'               => __( 'vCards', 'vcard-generator' ),
			'singular_name'      => __( 'vCard', 'vcard-generator' ),
			'add_new'            => __( 'Add New', 'vcard-generator' ),
			'add_new_item'       => __( 'Add New vCard', 'vcard-generator' ),
			'edit_item'          => __( 'Edit vCard', 'vcard-generator' ),
			'new_item'           => __( 'New vCard', 'vcard-generator' ),
			'view_item'          => __( 'View vCard', 'vcard-generator' ),
			'search_items'       => __( 'Search vCards', 'vcard-generator' ),
			'not_found'          => __( 'No vCards found.', 'vcard-generator' ),
			'not_found_in_trash' => __( 'No vCards found in trash.', 'vcard-generator' ),
			'all_items'          => __( 'All vCards', 'vcard-generator' ),
			'menu_name'          => __( 'vCards', 'vcard-generator' ),
		];

		register_post_type( self::SLUG, [
			'labels'              => $labels,
			'public'              => false,
			'publicly_queryable'  => false,
			'show_ui'             => true,
			'show_in_menu'        => false, // Shown via custom top-level menu.
			'show_in_nav_menus'   => false,
			'show_in_rest'        => false,
			'supports'            => [ 'title' ],
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
		] );
	}

	public static function add_top_level_menu(): void {
		add_menu_page(
			__( 'vCards', 'vcard-generator' ),
			__( 'vCards', 'vcard-generator' ),
			'edit_posts',
			'edit.php?post_type=' . self::SLUG,
			'',
			'dashicons-id-alt',
			25
		);
	}

	/**
	 * Snapshot the auto-derived slug from existing meta before WP/Fields writes new values.
	 * Fires on pre_post_update, so meta still holds the previous first/last name.
	 */
	public static function capture_prev_slug( int $post_id ): void {
		if ( get_post_type( $post_id ) !== self::SLUG ) {
			return;
		}
		$first = trim( (string) get_post_meta( $post_id, '_vcard_generator_first_name', true ) );
		$last  = trim( (string) get_post_meta( $post_id, '_vcard_generator_last_name', true ) );
		self::$prev_derived_slug = sanitize_title( trim( "$first $last" ) );
	}

	/**
	 * Auto-populate post title and conditionally update the slug on save.
	 * The slug is only auto-updated when it is empty (first save) or still
	 * matches the previously-auto-derived value. A manually-set slug is preserved.
	 */
	public static function sync_title( int $post_id, \WP_Post $post ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Fields::save (priority 5) has already written the new names to meta.
		$first = trim( (string) get_post_meta( $post_id, '_vcard_generator_first_name', true ) );
		$last  = trim( (string) get_post_meta( $post_id, '_vcard_generator_last_name', true ) );

		if ( '' === $first && '' === $last ) {
			return;
		}

		$full_name    = trim( "$first $last" );
		$derived_slug = sanitize_title( $full_name );

		// The slug explicitly submitted with this request.
		$submitted_slug = sanitize_title( wp_unslash( $_POST['post_name'] ?? '' ) );

		// Auto-update only when the slug is empty (first save) or the user hasn't
		// deviated from the previously-auto-derived value.
		$new_slug = ( '' === $submitted_slug || $submitted_slug === self::$prev_derived_slug )
			? $derived_slug
			: $submitted_slug;

		// Avoid infinite loop by unhooking before updating.
		remove_action( 'save_post_' . self::SLUG, [ self::class, 'sync_title' ], 10 );

		wp_update_post( [
			'ID'         => $post_id,
			'post_title' => $full_name,
			'post_name'  => $new_slug,
		] );

		add_action( 'save_post_' . self::SLUG, [ self::class, 'sync_title' ], 10, 2 );
	}
}
