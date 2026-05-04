<?php

namespace VCardGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings {

	const OPTION_GROUP = 'vcard_generator_settings';
	const PAGE_SLUG    = 'vcard-generator-settings';

	public static function register(): void {
		add_action( 'admin_menu', [ self::class, 'add_settings_page' ] );
		add_action( 'admin_init', [ self::class, 'register_settings' ] );
		add_action( 'update_option_vcard_generator_slug_base', [ self::class, 'on_slug_base_change' ], 10, 2 );
	}

	public static function add_settings_page(): void {
		add_submenu_page(
			'edit.php?post_type=' . PostType::SLUG,
			__( 'vCard Generator Settings', 'vcard-generator' ),
			__( 'Settings', 'vcard-generator' ),
			'manage_options',
			self::PAGE_SLUG,
			[ self::class, 'render_page' ]
		);
	}

	public static function register_settings(): void {
		// --- Section 1: Organization defaults ---
		add_settings_section( 'vcard_generator_org', __( 'Organization Defaults', 'vcard-generator' ), '__return_false', self::PAGE_SLUG );

		$org_fields = [
			'vcard_generator_org_name'       => __( 'Organization Name', 'vcard-generator' ),
			'vcard_generator_org_website'    => __( 'Organization Website', 'vcard-generator' ),
			'vcard_generator_org_phone'      => __( 'Main Work Phone', 'vcard-generator' ),
			'vcard_generator_address_street' => __( 'Street Address', 'vcard-generator' ),
			'vcard_generator_address_city'   => __( 'City', 'vcard-generator' ),
			'vcard_generator_address_state'  => __( 'State / Province', 'vcard-generator' ),
			'vcard_generator_address_zip'    => __( 'ZIP / Postal Code', 'vcard-generator' ),
			'vcard_generator_address_country' => __( 'Country', 'vcard-generator' ),
		];
		foreach ( $org_fields as $key => $label ) {
			register_setting( self::OPTION_GROUP, $key, [ 'sanitize_callback' => 'sanitize_text_field' ] );
			add_settings_field( $key, $label, [ self::class, 'render_text_field' ], self::PAGE_SLUG, 'vcard_generator_org', [ 'option' => $key ] );
		}

		// --- Section 2: URL configuration ---
		add_settings_section( 'vcard_generator_url', __( 'URL Configuration', 'vcard-generator' ), [ self::class, 'render_url_section_desc' ], self::PAGE_SLUG );

		register_setting( self::OPTION_GROUP, 'vcard_generator_slug_base', [
			'sanitize_callback' => function ( $val ) {
				return sanitize_title( trim( (string) $val ) ) ?: 'v';
			},
		] );
		add_settings_field( 'vcard_generator_slug_base', __( 'URL Slug Base', 'vcard-generator' ), [ self::class, 'render_slug_base_field' ], self::PAGE_SLUG, 'vcard_generator_url' );

		// --- Section 3: QR code defaults ---
		add_settings_section( 'vcard_generator_qr', __( 'QR Code Defaults', 'vcard-generator' ), '__return_false', self::PAGE_SLUG );

		register_setting( self::OPTION_GROUP, 'vcard_generator_ecc_level', [ 'sanitize_callback' => [ self::class, 'sanitize_ecc' ] ] );
		add_settings_field( 'vcard_generator_ecc_level', __( 'Error Correction Level', 'vcard-generator' ), [ self::class, 'render_ecc_field' ], self::PAGE_SLUG, 'vcard_generator_qr' );

		// --- Section 4: Scan tracking ---
		add_settings_section( 'vcard_generator_tracking', __( 'Scan Tracking', 'vcard-generator' ), [ self::class, 'render_tracking_section_desc' ], self::PAGE_SLUG );

		register_setting( self::OPTION_GROUP, 'vcard_generator_scan_tracking', [ 'sanitize_callback' => 'absint' ] );
		add_settings_field( 'vcard_generator_scan_tracking', __( 'Enable Scan Tracking', 'vcard-generator' ), [ self::class, 'render_tracking_toggle' ], self::PAGE_SLUG, 'vcard_generator_tracking' );

		// --- Section 5: Danger zone ---
		add_settings_section( 'vcard_generator_danger', __( 'Danger Zone', 'vcard-generator' ), '__return_false', self::PAGE_SLUG );

		register_setting( self::OPTION_GROUP, 'vcard_generator_delete_on_uninstall', [ 'sanitize_callback' => 'absint' ] );
		add_settings_field( 'vcard_generator_delete_on_uninstall', __( 'Delete data on uninstall', 'vcard-generator' ), [ self::class, 'render_delete_on_uninstall' ], self::PAGE_SLUG, 'vcard_generator_danger' );
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'vCard Generator Settings', 'vcard-generator' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	public static function render_text_field( array $args ): void {
		$key = $args['option'];
		$val = esc_attr( (string) get_option( $key, '' ) );
		echo "<input type=\"text\" id=\"{$key}\" name=\"{$key}\" value=\"{$val}\" class=\"regular-text\">";
	}

	public static function render_url_section_desc(): void {
		echo '<p>' . esc_html__( 'Changing the slug base flushes rewrite rules automatically.', 'vcard-generator' ) . '</p>';
		$base = esc_html( get_option( 'vcard_generator_slug_base', 'v' ) );
		$home = esc_html( trailingslashit( home_url() ) );
		echo "<p>" . esc_html__( 'Current pattern:', 'vcard-generator' ) . " <code>{$home}{$base}/{slug}</code></p>";
	}

	public static function render_slug_base_field(): void {
		$val = esc_attr( get_option( 'vcard_generator_slug_base', 'v' ) );
		echo "<input type=\"text\" id=\"vcard_generator_slug_base\" name=\"vcard_generator_slug_base\" value=\"{$val}\" class=\"small-text\">";
		echo '<p class="description">' . esc_html__( 'Default: v. Only lowercase letters, numbers, and hyphens.', 'vcard-generator' ) . '</p>';
	}

	public static function render_ecc_field(): void {
		$current = get_option( 'vcard_generator_ecc_level', 'M' );
		$levels  = [
			'L' => __( 'L – 7% (lowest density, most reliable)', 'vcard-generator' ),
			'M' => __( 'M – 15% (recommended for business cards)', 'vcard-generator' ),
			'Q' => __( 'Q – 25%', 'vcard-generator' ),
			'H' => __( 'H – 30% (highest, densest code)', 'vcard-generator' ),
		];
		echo '<select id="vcard_generator_ecc_level" name="vcard_generator_ecc_level">';
		foreach ( $levels as $value => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $value ),
				selected( $current, $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
	}

	public static function render_tracking_section_desc(): void {
		echo '<p>' . esc_html__( 'Tracks the number of times each vCard URL is accessed. Only stores a count and a timestamp — no IP address, user agent, or personal data.', 'vcard-generator' ) . '</p>';
	}

	public static function render_tracking_toggle(): void {
		$enabled = (bool) get_option( 'vcard_generator_scan_tracking', true );
		echo '<label><input type="checkbox" name="vcard_generator_scan_tracking" value="1"' . checked( $enabled, true, false ) . '> ' . esc_html__( 'Enable', 'vcard-generator' ) . '</label>';
	}

	public static function sanitize_ecc( $val ): string {
		return in_array( $val, [ 'L', 'M', 'Q', 'H' ], true ) ? $val : 'M';
	}

	public static function render_delete_on_uninstall(): void {
		$enabled = (bool) get_option( 'vcard_generator_delete_on_uninstall', false );
		echo '<label><input type="checkbox" name="vcard_generator_delete_on_uninstall" value="1"' . checked( $enabled, true, false ) . '> ' . esc_html__( 'When this plugin is deleted, permanently remove all vCard posts, post meta, and plugin options.', 'vcard-generator' ) . '</label>';
		echo '<p class="description" style="color:#b32d2e;">' . esc_html__( 'This cannot be undone. Default: off.', 'vcard-generator' ) . '</p>';
	}

	public static function on_slug_base_change( $old, $new ): void {
		if ( $old !== $new ) {
			Rewrite::flush();
		}
	}

	public static function set_defaults(): void {
		add_option( 'vcard_generator_slug_base', 'v' );
		add_option( 'vcard_generator_ecc_level', 'M' );
		add_option( 'vcard_generator_scan_tracking', '1' );
	}

	public static function get( string $key, $default = '' ) {
		return get_option( $key, $default );
	}
}
