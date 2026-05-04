<?php

namespace VCardGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PostType {

	const SLUG = 'vcard_generator';

	public static function register(): void {
		add_action( 'init', [ self::class, 'register_cpt' ] );
		add_action( 'admin_menu', [ self::class, 'add_top_level_menu' ] );
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
	 * Auto-populate post title from first + last name meta on save.
	 */
	public static function sync_title( int $post_id, \WP_Post $post ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		$first = trim( (string) get_post_meta( $post_id, '_vcard_generator_first_name', true ) );
		$last  = trim( (string) get_post_meta( $post_id, '_vcard_generator_last_name', true ) );

		if ( '' === $first && '' === $last ) {
			return;
		}

		$full_name = trim( "$first $last" );

		// Avoid infinite loop by unhooking before updating.
		remove_action( 'save_post_' . self::SLUG, [ self::class, 'sync_title' ], 10 );

		wp_update_post( [
			'ID'         => $post_id,
			'post_title' => $full_name,
			'post_name'  => sanitize_title( $full_name ),
		] );

		add_action( 'save_post_' . self::SLUG, [ self::class, 'sync_title' ], 10, 2 );
	}
}
