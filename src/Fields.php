<?php

namespace BCO\vCard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Fields {

	public static function register(): void {
		add_action( 'add_meta_boxes', [ self::class, 'add_meta_boxes' ] );
		add_action( 'save_post_' . PostType::SLUG, [ self::class, 'save' ], 5, 2 );
		add_action( 'admin_notices', [ self::class, 'validation_notice' ] );
	}

	public static function add_meta_boxes(): void {
		add_meta_box(
			'bco-vcard-contact-details',
			__( 'Contact Details', 'bco-vcard' ),
			[ self::class, 'render_contact_details' ],
			PostType::SLUG,
			'normal',
			'high'
		);

		add_meta_box(
			'bco-vcard-stats',
			__( 'Stats', 'bco-vcard' ),
			[ self::class, 'render_stats' ],
			PostType::SLUG,
			'side',
			'low'
		);
	}

	public static function render_contact_details( \WP_Post $post ): void {
		wp_nonce_field( 'bco_vcard_save_fields', 'bco_vcard_nonce' );

		$fields = self::get_field_values( $post->ID );
		?>
		<style>
			.bco-vcard-fields { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 20px; }
			.bco-vcard-fields .full-width { grid-column: 1 / -1; }
			.bco-vcard-fields label { display: block; font-weight: 600; margin-bottom: 4px; }
			.bco-vcard-fields input[type="text"],
			.bco-vcard-fields input[type="url"],
			.bco-vcard-fields input[type="email"] { width: 100%; }
			.bco-vcard-active-row { margin-top: 12px; }
		</style>
		<div class="bco-vcard-fields">
			<div>
				<label for="bco_first_name"><?php esc_html_e( 'First Name', 'bco-vcard' ); ?> <span style="color:red">*</span></label>
				<input type="text" id="bco_first_name" name="bco_first_name" value="<?php echo esc_attr( $fields['first_name'] ); ?>" required>
			</div>
			<div>
				<label for="bco_last_name"><?php esc_html_e( 'Last Name', 'bco-vcard' ); ?> <span style="color:red">*</span></label>
				<input type="text" id="bco_last_name" name="bco_last_name" value="<?php echo esc_attr( $fields['last_name'] ); ?>" required>
			</div>
			<div>
				<label for="bco_job_title"><?php esc_html_e( 'Job Title', 'bco-vcard' ); ?></label>
				<input type="text" id="bco_job_title" name="bco_job_title" value="<?php echo esc_attr( $fields['job_title'] ); ?>">
			</div>
			<div>
				<label for="bco_department"><?php esc_html_e( 'Department', 'bco-vcard' ); ?></label>
				<input type="text" id="bco_department" name="bco_department" value="<?php echo esc_attr( $fields['department'] ); ?>">
			</div>
			<div>
				<label for="bco_mobile_phone"><?php esc_html_e( 'Mobile Phone', 'bco-vcard' ); ?></label>
				<input type="text" id="bco_mobile_phone" name="bco_mobile_phone" value="<?php echo esc_attr( $fields['mobile_phone'] ); ?>" placeholder="+1 616 555 1234">
			</div>
			<div>
				<label for="bco_work_phone"><?php esc_html_e( 'Work Phone', 'bco-vcard' ); ?></label>
				<input type="text" id="bco_work_phone" name="bco_work_phone" value="<?php echo esc_attr( $fields['work_phone'] ); ?>" placeholder="+1 616 555 4321">
			</div>
			<div class="full-width">
				<label for="bco_email"><?php esc_html_e( 'Email', 'bco-vcard' ); ?></label>
				<input type="email" id="bco_email" name="bco_email" value="<?php echo esc_attr( $fields['email'] ); ?>">
			</div>
			<div class="full-width">
				<label for="bco_personal_url"><?php esc_html_e( 'Personal URL', 'bco-vcard' ); ?></label>
				<input type="url" id="bco_personal_url" name="bco_personal_url" value="<?php echo esc_attr( $fields['personal_url'] ); ?>" placeholder="https://example.com/bio">
			</div>
			<div class="full-width">
				<label for="bco_linkedin_url"><?php esc_html_e( 'LinkedIn URL', 'bco-vcard' ); ?></label>
				<input type="url" id="bco_linkedin_url" name="bco_linkedin_url" value="<?php echo esc_attr( $fields['linkedin_url'] ); ?>" placeholder="https://linkedin.com/in/jane-smith">
			</div>
			<div class="full-width bco-vcard-active-row">
				<label>
					<input type="checkbox" name="bco_active" value="1" <?php checked( $fields['active'], '1' ); ?>>
					<?php esc_html_e( 'Active (public URL enabled)', 'bco-vcard' ); ?>
				</label>
			</div>
		</div>
		<?php
	}

	public static function render_stats( \WP_Post $post ): void {
		$tracking_enabled = (bool) get_option( 'bco_vcard_scan_tracking', true );

		if ( ! $tracking_enabled ) {
			echo '<p>' . esc_html__( 'Scan tracking is disabled in Settings.', 'bco-vcard' ) . '</p>';
			return;
		}

		$count       = (int) get_post_meta( $post->ID, '_bco_vcard_scan_count', true );
		$last_scanned = get_post_meta( $post->ID, '_bco_vcard_last_scanned', true );
		?>
		<p><strong><?php esc_html_e( 'Total Scans:', 'bco-vcard' ); ?></strong> <?php echo esc_html( $count ); ?></p>
		<p><strong><?php esc_html_e( 'Last Scan:', 'bco-vcard' ); ?></strong>
			<?php
			if ( $last_scanned ) {
				$ts = strtotime( $last_scanned );
				echo esc_html( human_time_diff( $ts, time() ) . ' ' . __( 'ago', 'bco-vcard' ) );
			} else {
				esc_html_e( 'Never', 'bco-vcard' );
			}
			?>
		</p>
		<?php
	}

	public static function save( int $post_id, \WP_Post $post ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['bco_vcard_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bco_vcard_nonce'] ) ), 'bco_vcard_save_fields' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$first = sanitize_text_field( wp_unslash( $_POST['bco_first_name'] ?? '' ) );
		$last  = sanitize_text_field( wp_unslash( $_POST['bco_last_name'] ?? '' ) );

		// Validate required fields and prevent publish if missing.
		if ( '' === $first || '' === $last ) {
			// Flag for admin notice; unhook the update to keep as draft.
			update_post_meta( $post_id, '_bco_vcard_missing_name', '1' );
			if ( 'publish' === $post->post_status ) {
				remove_action( 'save_post_' . PostType::SLUG, [ self::class, 'save' ], 5 );
				wp_update_post( [ 'ID' => $post_id, 'post_status' => 'draft' ] );
				add_action( 'save_post_' . PostType::SLUG, [ self::class, 'save' ], 5, 2 );
			}
			return;
		}

		delete_post_meta( $post_id, '_bco_vcard_missing_name' );

		update_post_meta( $post_id, '_bco_vcard_first_name', $first );
		update_post_meta( $post_id, '_bco_vcard_last_name', $last );
		update_post_meta( $post_id, '_bco_vcard_job_title', sanitize_text_field( wp_unslash( $_POST['bco_job_title'] ?? '' ) ) );
		update_post_meta( $post_id, '_bco_vcard_department', sanitize_text_field( wp_unslash( $_POST['bco_department'] ?? '' ) ) );
		update_post_meta( $post_id, '_bco_vcard_email', sanitize_email( wp_unslash( $_POST['bco_email'] ?? '' ) ) );

		$mobile = PhoneNormalizer::normalize( wp_unslash( $_POST['bco_mobile_phone'] ?? '' ) );
		update_post_meta( $post_id, '_bco_vcard_mobile_phone', $mobile );

		$work = PhoneNormalizer::normalize( wp_unslash( $_POST['bco_work_phone'] ?? '' ) );
		update_post_meta( $post_id, '_bco_vcard_work_phone', $work );

		$personal_url = esc_url_raw( wp_unslash( $_POST['bco_personal_url'] ?? '' ) );
		update_post_meta( $post_id, '_bco_vcard_personal_url', $personal_url );

		$linkedin = esc_url_raw( wp_unslash( $_POST['bco_linkedin_url'] ?? '' ) );
		if ( '' !== $linkedin && ! preg_match( '#^https://(www\.)?linkedin\.com/#i', $linkedin ) ) {
			$linkedin = '';
		}
		update_post_meta( $post_id, '_bco_vcard_linkedin_url', $linkedin );

		update_post_meta( $post_id, '_bco_vcard_active', isset( $_POST['bco_active'] ) ? '1' : '0' );
	}

	public static function validation_notice(): void {
		$screen = get_current_screen();
		if ( ! $screen || $screen->id !== PostType::SLUG ) {
			return;
		}
		global $post;
		if ( ! $post || '1' !== get_post_meta( $post->ID, '_bco_vcard_missing_name', true ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p>' . esc_html__( 'First Name and Last Name are required. The vCard has been saved as a draft.', 'bco-vcard' ) . '</p></div>';
	}

	public static function get_field_values( int $post_id ): array {
		return [
			'first_name'   => (string) get_post_meta( $post_id, '_bco_vcard_first_name', true ),
			'last_name'    => (string) get_post_meta( $post_id, '_bco_vcard_last_name', true ),
			'job_title'    => (string) get_post_meta( $post_id, '_bco_vcard_job_title', true ),
			'department'   => (string) get_post_meta( $post_id, '_bco_vcard_department', true ),
			'mobile_phone' => (string) get_post_meta( $post_id, '_bco_vcard_mobile_phone', true ),
			'work_phone'   => (string) get_post_meta( $post_id, '_bco_vcard_work_phone', true ),
			'email'        => (string) get_post_meta( $post_id, '_bco_vcard_email', true ),
			'personal_url' => (string) get_post_meta( $post_id, '_bco_vcard_personal_url', true ),
			'linkedin_url' => (string) get_post_meta( $post_id, '_bco_vcard_linkedin_url', true ),
			'active'       => get_post_meta( $post_id, '_bco_vcard_active', true ) !== '0' ? '1' : '0',
		];
	}
}
