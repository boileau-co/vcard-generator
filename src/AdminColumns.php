<?php

namespace VCardGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AdminColumns {

	public static function register(): void {
		$cpt = PostType::SLUG;

		add_filter( "manage_{$cpt}_posts_columns", [ self::class, 'columns' ] );
		add_action( "manage_{$cpt}_posts_custom_column", [ self::class, 'column_content' ], 10, 2 );
		add_filter( "manage_edit-{$cpt}_sortable_columns", [ self::class, 'sortable_columns' ] );
		add_action( 'pre_get_posts', [ self::class, 'sort_scans' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
		add_action( 'add_meta_boxes', [ self::class, 'add_qr_meta_box' ] );

		// AJAX handlers for QR downloads.
		add_action( 'wp_ajax_vcard_generator_qr_svg', [ self::class, 'ajax_qr_svg' ] );
		add_action( 'wp_ajax_vcard_generator_qr_png', [ self::class, 'ajax_qr_png' ] );
	}

	public static function columns( array $columns ): array {
		$tracking = (bool) get_option( 'vcard_generator_scan_tracking', true );

		$new = [
			'cb'                    => $columns['cb'] ?? '<input type="checkbox">',
			'vcg_name'              => __( 'Name', 'vcard-generator' ),
			'vcg_title'             => __( 'Title', 'vcard-generator' ),
			'vcg_url'               => __( 'URL', 'vcard-generator' ),
			'vcg_qr'                => __( 'QR', 'vcard-generator' ),
			'vcg_status'            => __( 'Status', 'vcard-generator' ),
		];

		if ( $tracking ) {
			$new['vcg_scans']     = __( 'Scans', 'vcard-generator' );
			$new['vcg_last_scan'] = __( 'Last Scan', 'vcard-generator' );
		}

		$new['date'] = $columns['date'] ?? __( 'Date', 'vcard-generator' );

		return $new;
	}

	public static function column_content( string $column, int $post_id ): void {
		$post = get_post( $post_id );

		switch ( $column ) {
			case 'vcg_name':
				echo '<strong><a href="' . esc_url( get_edit_post_link( $post_id ) ) . '">' . esc_html( $post->post_title ) . '</a></strong>';
				break;

			case 'vcg_title':
				echo esc_html( (string) get_post_meta( $post_id, '_vcard_generator_job_title', true ) );
				break;

			case 'vcg_url':
				$slug    = $post->post_name;
				$url     = Helpers::vcard_url( $slug );
				$display = '/' . get_option( 'vcard_generator_slug_base', 'v' ) . '/' . esc_html( $slug );
				echo '<span class="vcard-generator-url-wrap">';
				echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $display ) . '</a> ';
				echo '<button type="button" class="vcard-generator-copy-url button-link" data-url="' . esc_attr( $url ) . '" title="' . esc_attr__( 'Copy URL', 'vcard-generator' ) . '">';
				echo '<span class="dashicons dashicons-clipboard"></span>';
				echo '</button>';
				echo '</span>';
				break;

			case 'vcg_qr':
				$url   = Helpers::vcard_url( $post->post_name );
				$nonce = wp_create_nonce( 'vcard_generator_qr_download_' . $post_id );
				echo '<button type="button" class="vcard-generator-qr-preview button-link"'
					. ' data-post-id="' . esc_attr( (string) $post_id ) . '"'
					. ' data-url="' . esc_attr( $url ) . '"'
					. ' data-nonce="' . esc_attr( $nonce ) . '"'
					. ' data-slug="' . esc_attr( $post->post_name ) . '"'
					. ' title="' . esc_attr__( 'View QR Code', 'vcard-generator' ) . '">';
				echo '<span class="dashicons dashicons-qrcode"></span>';
				echo '</button>';
				break;

			case 'vcg_status':
				$active    = get_post_meta( $post_id, '_vcard_generator_active', true );
				$is_active = $active !== '0';
				if ( $is_active ) {
					echo '<span class="vcard-generator-badge vcard-generator-badge--active">' . esc_html__( 'Active', 'vcard-generator' ) . '</span>';
				} else {
					echo '<span class="vcard-generator-badge vcard-generator-badge--inactive">' . esc_html__( 'Inactive', 'vcard-generator' ) . '</span>';
				}
				break;

			case 'vcg_scans':
				echo esc_html( (string) (int) get_post_meta( $post_id, '_vcard_generator_scan_count', true ) );
				break;

			case 'vcg_last_scan':
				$ts = get_post_meta( $post_id, '_vcard_generator_last_scanned', true );
				if ( $ts ) {
					$time = strtotime( $ts );
					echo esc_html( human_time_diff( $time, time() ) . ' ' . __( 'ago', 'vcard-generator' ) );
				} else {
					echo '—';
				}
				break;
		}
	}

	public static function sortable_columns( array $columns ): array {
		$columns['vcg_scans'] = 'vcg_scans';
		return $columns;
	}

	public static function sort_scans( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( 'vcg_scans' !== $query->get( 'orderby' ) ) {
			return;
		}
		$query->set( 'meta_key', '_vcard_generator_scan_count' );
		$query->set( 'orderby', 'meta_value_num' );
	}

	public static function add_qr_meta_box(): void {
		add_meta_box(
			'vcard-generator-qr',
			__( 'QR Code', 'vcard-generator' ),
			[ self::class, 'render_qr_meta_box' ],
			PostType::SLUG,
			'side',
			'default'
		);
	}

	public static function render_qr_meta_box( \WP_Post $post ): void {
		if ( 'auto-draft' === $post->post_status || '' === $post->post_name ) {
			echo '<p class="description">' . esc_html__( 'Save the vCard first to generate a QR code.', 'vcard-generator' ) . '</p>';
			return;
		}

		$url     = Helpers::vcard_url( $post->post_name );
		$svg_uri = QrGenerator::svg_data_uri( $url );
		?>
		<div class="vcard-generator-qr-box">
			<?php if ( $svg_uri ) : ?>
				<img src="<?php echo esc_attr( $svg_uri ); ?>" alt="<?php esc_attr_e( 'QR Code', 'vcard-generator' ); ?>" style="width:100%;height:auto;display:block;background:#fff;">
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'QR library not available. Run composer install.', 'vcard-generator' ); ?></p>
			<?php endif; ?>

			<p style="margin-top:8px;">
				<a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=vcard_generator_qr_svg&post_id=' . $post->ID . '&_wpnonce=' . wp_create_nonce( 'vcard_generator_qr_download_' . $post->ID ) ) ); ?>" class="button" download="qr-<?php echo esc_attr( $post->post_name ); ?>.svg">
					<?php esc_html_e( 'Download SVG', 'vcard-generator' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=vcard_generator_qr_png&post_id=' . $post->ID . '&_wpnonce=' . wp_create_nonce( 'vcard_generator_qr_download_' . $post->ID ) ) ); ?>" class="button" download="qr-<?php echo esc_attr( $post->post_name ); ?>.png">
					<?php esc_html_e( 'Download PNG', 'vcard-generator' ); ?>
				</a>
				<button type="button" class="button vcard-generator-copy-url" data-url="<?php echo esc_attr( $url ); ?>">
					<?php esc_html_e( 'Copy URL', 'vcard-generator' ); ?>
				</button>
			</p>
			<p class="description" style="margin-top:8px;font-size:11px;">
				<?php esc_html_e( 'Print specs: minimum 0.8 in (20 mm). Recommended: 1 in (25 mm) for business cards. Do not crop the white border (quiet zone). Use SVG for any print application.', 'vcard-generator' ); ?>
			</p>
		</div>
		<?php
	}

	public static function ajax_qr_svg(): void {
		$post_id = (int) ( $_GET['post_id'] ?? 0 );
		if ( ! $post_id || ! check_ajax_referer( 'vcard_generator_qr_download_' . $post_id, '_wpnonce', false ) ) {
			wp_die( '', '', 403 );
		}
		$post = get_post( $post_id );
		if ( ! $post || $post->post_type !== PostType::SLUG ) {
			wp_die( '', '', 404 );
		}
		$url = Helpers::vcard_url( $post->post_name );
		$svg = QrGenerator::svg( $url );
		header( 'Content-Type: image/svg+xml' );
		header( 'Content-Disposition: attachment; filename="qr-' . sanitize_file_name( $post->post_name ) . '.svg"' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $svg;
		exit;
	}

	public static function ajax_qr_png(): void {
		$post_id = (int) ( $_GET['post_id'] ?? 0 );
		if ( ! $post_id || ! check_ajax_referer( 'vcard_generator_qr_download_' . $post_id, '_wpnonce', false ) ) {
			wp_die( '', '', 403 );
		}
		$post = get_post( $post_id );
		if ( ! $post || $post->post_type !== PostType::SLUG ) {
			wp_die( '', '', 404 );
		}
		$url = Helpers::vcard_url( $post->post_name );
		$png = QrGenerator::png( $url );
		header( 'Content-Type: image/png' );
		header( 'Content-Disposition: attachment; filename="qr-' . sanitize_file_name( $post->post_name ) . '.png"' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $png;
		exit;
	}

	public static function enqueue_assets( string $hook ): void {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}
		if ( ! in_array( $screen->id, [ PostType::SLUG, 'edit-' . PostType::SLUG ], true ) ) {
			return;
		}

		wp_enqueue_style(
			'vcard-generator-admin',
			VCARD_GENERATOR_URL . 'assets/css/admin.css',
			[],
			VCARD_GENERATOR_VERSION
		);

		wp_enqueue_script(
			'vcard-generator-admin',
			VCARD_GENERATOR_URL . 'assets/js/admin.js',
			[ 'jquery' ],
			VCARD_GENERATOR_VERSION,
			true
		);

		wp_localize_script( 'vcard-generator-admin', 'vcardGeneratorAdmin', [
			'copied'   => __( 'Copied!', 'vcard-generator' ),
			'copyFail' => __( 'Copy failed', 'vcard-generator' ),
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
		] );
	}
}
