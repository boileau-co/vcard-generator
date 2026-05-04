<?php

namespace BCO\vCard;

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
		add_action( 'wp_ajax_bco_vcard_qr_svg', [ self::class, 'ajax_qr_svg' ] );
		add_action( 'wp_ajax_bco_vcard_qr_png', [ self::class, 'ajax_qr_png' ] );
	}

	public static function columns( array $columns ): array {
		$tracking = (bool) get_option( 'bco_vcard_scan_tracking', true );

		$new = [
			'cb'        => $columns['cb'] ?? '<input type="checkbox">',
			'bco_name'  => __( 'Name', 'bco-vcard' ),
			'bco_title' => __( 'Title', 'bco-vcard' ),
			'bco_url'   => __( 'URL', 'bco-vcard' ),
			'bco_qr'    => __( 'QR', 'bco-vcard' ),
			'bco_status' => __( 'Status', 'bco-vcard' ),
		];

		if ( $tracking ) {
			$new['bco_scans']      = __( 'Scans', 'bco-vcard' );
			$new['bco_last_scan']  = __( 'Last Scan', 'bco-vcard' );
		}

		$new['date'] = $columns['date'] ?? __( 'Date', 'bco-vcard' );

		return $new;
	}

	public static function column_content( string $column, int $post_id ): void {
		$post = get_post( $post_id );

		switch ( $column ) {
			case 'bco_name':
				echo '<strong><a href="' . esc_url( get_edit_post_link( $post_id ) ) . '">' . esc_html( $post->post_title ) . '</a></strong>';
				break;

			case 'bco_title':
				echo esc_html( (string) get_post_meta( $post_id, '_bco_vcard_job_title', true ) );
				break;

			case 'bco_url':
				$slug = $post->post_name;
				$url  = Helpers::vcard_url( $slug );
				$display = '/' . get_option( 'bco_vcard_slug_base', 'v' ) . '/' . esc_html( $slug );
				echo '<span class="bco-url-wrap">';
				echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $display ) . '</a> ';
				echo '<button type="button" class="bco-copy-url button-link" data-url="' . esc_attr( $url ) . '" title="' . esc_attr__( 'Copy URL', 'bco-vcard' ) . '">';
				echo '<span class="dashicons dashicons-clipboard"></span>';
				echo '</button>';
				echo '</span>';
				break;

			case 'bco_qr':
				$url   = Helpers::vcard_url( $post->post_name );
				$nonce = wp_create_nonce( 'bco_qr_download_' . $post_id );
				echo '<button type="button" class="bco-qr-preview button-link"'
					. ' data-post-id="' . esc_attr( (string) $post_id ) . '"'
					. ' data-url="' . esc_attr( $url ) . '"'
					. ' data-nonce="' . esc_attr( $nonce ) . '"'
					. ' data-slug="' . esc_attr( $post->post_name ) . '"'
					. ' title="' . esc_attr__( 'View QR Code', 'bco-vcard' ) . '">';
				echo '<span class="dashicons dashicons-qrcode"></span>';
				echo '</button>';
				break;

			case 'bco_status':
				$active = get_post_meta( $post_id, '_bco_vcard_active', true );
				$is_active = $active !== '0';
				if ( $is_active ) {
					echo '<span class="bco-badge bco-badge--active">' . esc_html__( 'Active', 'bco-vcard' ) . '</span>';
				} else {
					echo '<span class="bco-badge bco-badge--inactive">' . esc_html__( 'Inactive', 'bco-vcard' ) . '</span>';
				}
				break;

			case 'bco_scans':
				echo esc_html( (string) (int) get_post_meta( $post_id, '_bco_vcard_scan_count', true ) );
				break;

			case 'bco_last_scan':
				$ts = get_post_meta( $post_id, '_bco_vcard_last_scanned', true );
				if ( $ts ) {
					$time = strtotime( $ts );
					echo esc_html( human_time_diff( $time, time() ) . ' ' . __( 'ago', 'bco-vcard' ) );
				} else {
					echo '—';
				}
				break;
		}
	}

	public static function sortable_columns( array $columns ): array {
		$columns['bco_scans'] = 'bco_scans';
		return $columns;
	}

	public static function sort_scans( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( 'bco_scans' !== $query->get( 'orderby' ) ) {
			return;
		}
		$query->set( 'meta_key', '_bco_vcard_scan_count' );
		$query->set( 'orderby', 'meta_value_num' );
	}

	public static function add_qr_meta_box(): void {
		add_meta_box(
			'bco-vcard-qr',
			__( 'QR Code', 'bco-vcard' ),
			[ self::class, 'render_qr_meta_box' ],
			PostType::SLUG,
			'side',
			'default'
		);
	}

	public static function render_qr_meta_box( \WP_Post $post ): void {
		if ( 'auto-draft' === $post->post_status || '' === $post->post_name ) {
			echo '<p class="description">' . esc_html__( 'Save the vCard first to generate a QR code.', 'bco-vcard' ) . '</p>';
			return;
		}

		$url     = Helpers::vcard_url( $post->post_name );
		$svg_uri = QrGenerator::svg_data_uri( $url );
		?>
		<div class="bco-qr-box">
			<?php if ( $svg_uri ) : ?>
				<img src="<?php echo esc_attr( $svg_uri ); ?>" alt="<?php esc_attr_e( 'QR Code', 'bco-vcard' ); ?>" style="width:100%;height:auto;display:block;background:#fff;">
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'QR library not available. Run composer install.', 'bco-vcard' ); ?></p>
			<?php endif; ?>

			<p style="margin-top:8px;">
				<a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=bco_vcard_qr_svg&post_id=' . $post->ID . '&_wpnonce=' . wp_create_nonce( 'bco_qr_download_' . $post->ID ) ) ); ?>" class="button" download="qr-<?php echo esc_attr( $post->post_name ); ?>.svg">
					<?php esc_html_e( 'Download SVG', 'bco-vcard' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=bco_vcard_qr_png&post_id=' . $post->ID . '&_wpnonce=' . wp_create_nonce( 'bco_qr_download_' . $post->ID ) ) ); ?>" class="button" download="qr-<?php echo esc_attr( $post->post_name ); ?>.png">
					<?php esc_html_e( 'Download PNG', 'bco-vcard' ); ?>
				</a>
				<button type="button" class="button bco-copy-url" data-url="<?php echo esc_attr( $url ); ?>">
					<?php esc_html_e( 'Copy URL', 'bco-vcard' ); ?>
				</button>
			</p>
			<p class="description" style="margin-top:8px;font-size:11px;">
				<?php esc_html_e( 'Print specs: minimum 0.8 in (20 mm). Recommended: 1 in (25 mm) for business cards. Do not crop the white border (quiet zone). Use SVG for any print application.', 'bco-vcard' ); ?>
			</p>
		</div>
		<?php
	}

	public static function ajax_qr_svg(): void {
		$post_id = (int) ( $_GET['post_id'] ?? 0 );
		if ( ! $post_id || ! check_ajax_referer( 'bco_qr_download_' . $post_id, '_wpnonce', false ) ) {
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
		if ( ! $post_id || ! check_ajax_referer( 'bco_qr_download_' . $post_id, '_wpnonce', false ) ) {
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
			'bco-vcard-admin',
			BCO_VCARD_URL . 'assets/css/admin.css',
			[],
			BCO_VCARD_VERSION
		);

		wp_enqueue_script(
			'bco-vcard-admin',
			BCO_VCARD_URL . 'assets/js/admin.js',
			[ 'jquery' ],
			BCO_VCARD_VERSION,
			true
		);

		wp_localize_script( 'bco-vcard-admin', 'bcovCardAdmin', [
			'copied'    => __( 'Copied!', 'bco-vcard' ),
			'copyFail'  => __( 'Copy failed', 'bco-vcard' ),
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
		] );
	}
}
