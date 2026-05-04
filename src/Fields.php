<?php

namespace VCardGenerator;

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
			'vcard-generator-contact-details',
			__( 'Contact Details', 'vcard-generator' ),
			[ self::class, 'render_contact_details' ],
			PostType::SLUG,
			'normal',
			'high'
		);

		add_meta_box(
			'vcard-generator-stats',
			__( 'Stats', 'vcard-generator' ),
			[ self::class, 'render_stats' ],
			PostType::SLUG,
			'side',
			'low'
		);
	}

	public static function render_contact_details( \WP_Post $post ): void {
		wp_nonce_field( 'vcard_generator_save_fields', 'vcard_generator_nonce' );

		$fields = self::get_field_values( $post->ID );
		?>
		<style>
			.vcard-generator-fields { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 20px; }
			.vcard-generator-fields .full-width { grid-column: 1 / -1; }
			.vcard-generator-fields label { display: block; font-weight: 600; margin-bottom: 4px; }
			.vcard-generator-fields input[type="text"],
			.vcard-generator-fields input[type="url"],
			.vcard-generator-fields input[type="email"] { width: 100%; }
			.vcard-generator-active-row { margin-top: 12px; }
		</style>
		<div class="vcard-generator-fields">
			<div>
				<label for="vcard_generator_first_name"><?php esc_html_e( 'First Name', 'vcard-generator' ); ?> <span style="color:red">*</span></label>
				<input type="text" id="vcard_generator_first_name" name="vcard_generator_first_name" value="<?php echo esc_attr( $fields['first_name'] ); ?>" required>
			</div>
			<div>
				<label for="vcard_generator_last_name"><?php esc_html_e( 'Last Name', 'vcard-generator' ); ?> <span style="color:red">*</span></label>
				<input type="text" id="vcard_generator_last_name" name="vcard_generator_last_name" value="<?php echo esc_attr( $fields['last_name'] ); ?>" required>
			</div>
			<div>
				<label for="vcard_generator_job_title"><?php esc_html_e( 'Job Title', 'vcard-generator' ); ?></label>
				<input type="text" id="vcard_generator_job_title" name="vcard_generator_job_title" value="<?php echo esc_attr( $fields['job_title'] ); ?>">
			</div>
			<div>
				<label for="vcard_generator_department"><?php esc_html_e( 'Department', 'vcard-generator' ); ?></label>
				<input type="text" id="vcard_generator_department" name="vcard_generator_department" value="<?php echo esc_attr( $fields['department'] ); ?>">
			</div>
			<div>
				<label for="vcard_generator_mobile_phone"><?php esc_html_e( 'Mobile Phone', 'vcard-generator' ); ?></label>
				<input type="text" id="vcard_generator_mobile_phone" name="vcard_generator_mobile_phone" value="<?php echo esc_attr( $fields['mobile_phone'] ); ?>" placeholder="+1 616 555 1234">
			</div>
			<div>
				<label for="vcard_generator_work_phone"><?php esc_html_e( 'Work Phone', 'vcard-generator' ); ?></label>
				<input type="text" id="vcard_generator_work_phone" name="vcard_generator_work_phone" value="<?php echo esc_attr( $fields['work_phone'] ); ?>" placeholder="+1 616 555 4321">
			</div>
			<div class="full-width">
				<label for="vcard_generator_email"><?php esc_html_e( 'Email', 'vcard-generator' ); ?></label>
				<input type="email" id="vcard_generator_email" name="vcard_generator_email" value="<?php echo esc_attr( $fields['email'] ); ?>">
			</div>
			<div class="full-width">
				<label for="vcard_generator_personal_url"><?php esc_html_e( 'Personal URL', 'vcard-generator' ); ?></label>
				<input type="url" id="vcard_generator_personal_url" name="vcard_generator_personal_url" value="<?php echo esc_attr( $fields['personal_url'] ); ?>" placeholder="https://example.com/bio">
			</div>
			<div class="full-width">
				<label for="vcard_generator_linkedin_url"><?php esc_html_e( 'LinkedIn URL', 'vcard-generator' ); ?></label>
				<input type="url" id="vcard_generator_linkedin_url" name="vcard_generator_linkedin_url" value="<?php echo esc_attr( $fields['linkedin_url'] ); ?>" placeholder="https://linkedin.com/in/jane-smith">
			</div>
			<div class="full-width vcard-generator-active-row">
				<label>
					<input type="checkbox" name="vcard_generator_active" value="1" <?php checked( $fields['active'], '1' ); ?>>
					<?php esc_html_e( 'Active (public URL enabled)', 'vcard-generator' ); ?>
				</label>
			</div>
		</div>
		<?php
	}

	public static function render_stats( \WP_Post $post ): void {
		$tracking_enabled = (bool) get_option( 'vcard_generator_scan_tracking', true );

		if ( ! $tracking_enabled ) {
			echo '<p>' . esc_html__( 'Scan tracking is disabled in Settings.', 'vcard-generator' ) . '</p>';
			return;
		}

		$count        = (int) get_post_meta( $post->ID, '_vcard_generator_scan_count', true );
		$last_scanned = get_post_meta( $post->ID, '_vcard_generator_last_scanned', true );
		?>
		<p><strong><?php esc_html_e( 'Total Scans:', 'vcard-generator' ); ?></strong> <?php echo esc_html( $count ); ?></p>
		<p><strong><?php esc_html_e( 'Last Scan:', 'vcard-generator' ); ?></strong>
			<?php
			if ( $last_scanned ) {
				$ts = strtotime( $last_scanned );
				echo esc_html( human_time_diff( $ts, time() ) . ' ' . __( 'ago', 'vcard-generator' ) );
			} else {
				esc_html_e( 'Never', 'vcard-generator' );
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
		if ( ! isset( $_POST['vcard_generator_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vcard_generator_nonce'] ) ), 'vcard_generator_save_fields' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$first = sanitize_text_field( wp_unslash( $_POST['vcard_generator_first_name'] ?? '' ) );
		$last  = sanitize_text_field( wp_unslash( $_POST['vcard_generator_last_name'] ?? '' ) );

		// Validate required fields and prevent publish if missing.
		if ( '' === $first || '' === $last ) {
			// Flag for admin notice; unhook the update to keep as draft.
			update_post_meta( $post_id, '_vcard_generator_missing_name', '1' );
			if ( 'publish' === $post->post_status ) {
				remove_action( 'save_post_' . PostType::SLUG, [ self::class, 'save' ], 5 );
				wp_update_post( [ 'ID' => $post_id, 'post_status' => 'draft' ] );
				add_action( 'save_post_' . PostType::SLUG, [ self::class, 'save' ], 5, 2 );
			}
			return;
		}

		delete_post_meta( $post_id, '_vcard_generator_missing_name' );

		update_post_meta( $post_id, '_vcard_generator_first_name', $first );
		update_post_meta( $post_id, '_vcard_generator_last_name', $last );
		update_post_meta( $post_id, '_vcard_generator_job_title', sanitize_text_field( wp_unslash( $_POST['vcard_generator_job_title'] ?? '' ) ) );
		update_post_meta( $post_id, '_vcard_generator_department', sanitize_text_field( wp_unslash( $_POST['vcard_generator_department'] ?? '' ) ) );
		update_post_meta( $post_id, '_vcard_generator_email', sanitize_email( wp_unslash( $_POST['vcard_generator_email'] ?? '' ) ) );

		$mobile = PhoneNormalizer::normalize( wp_unslash( $_POST['vcard_generator_mobile_phone'] ?? '' ) );
		update_post_meta( $post_id, '_vcard_generator_mobile_phone', $mobile );

		$work = PhoneNormalizer::normalize( wp_unslash( $_POST['vcard_generator_work_phone'] ?? '' ) );
		update_post_meta( $post_id, '_vcard_generator_work_phone', $work );

		$personal_url = esc_url_raw( wp_unslash( $_POST['vcard_generator_personal_url'] ?? '' ) );
		update_post_meta( $post_id, '_vcard_generator_personal_url', $personal_url );

		$linkedin = esc_url_raw( wp_unslash( $_POST['vcard_generator_linkedin_url'] ?? '' ) );
		if ( '' !== $linkedin && ! preg_match( '#^https://(www\.)?linkedin\.com/#i', $linkedin ) ) {
			$linkedin = '';
		}
		update_post_meta( $post_id, '_vcard_generator_linkedin_url', $linkedin );

		update_post_meta( $post_id, '_vcard_generator_active', isset( $_POST['vcard_generator_active'] ) ? '1' : '0' );
	}

	public static function validation_notice(): void {
		$screen = get_current_screen();
		if ( ! $screen || $screen->id !== PostType::SLUG ) {
			return;
		}
		global $post;
		if ( ! $post || '1' !== get_post_meta( $post->ID, '_vcard_generator_missing_name', true ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p>' . esc_html__( 'First Name and Last Name are required. The vCard has been saved as a draft.', 'vcard-generator' ) . '</p></div>';
	}

	public static function get_field_values( int $post_id ): array {
		return [
			'first_name'   => (string) get_post_meta( $post_id, '_vcard_generator_first_name', true ),
			'last_name'    => (string) get_post_meta( $post_id, '_vcard_generator_last_name', true ),
			'job_title'    => (string) get_post_meta( $post_id, '_vcard_generator_job_title', true ),
			'department'   => (string) get_post_meta( $post_id, '_vcard_generator_department', true ),
			'mobile_phone' => (string) get_post_meta( $post_id, '_vcard_generator_mobile_phone', true ),
			'work_phone'   => (string) get_post_meta( $post_id, '_vcard_generator_work_phone', true ),
			'email'        => (string) get_post_meta( $post_id, '_vcard_generator_email', true ),
			'personal_url' => (string) get_post_meta( $post_id, '_vcard_generator_personal_url', true ),
			'linkedin_url' => (string) get_post_meta( $post_id, '_vcard_generator_linkedin_url', true ),
			'active'       => get_post_meta( $post_id, '_vcard_generator_active', true ) !== '0' ? '1' : '0',
		];
	}
}
