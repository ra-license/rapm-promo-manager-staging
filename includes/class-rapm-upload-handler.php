<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The validated "Add/Edit Asset" admin form — the actual replacement for
 * dropping a raw file into the Media Library. Every image upload here is
 * dimension-checked against its slot BEFORE anything is accepted (a wrong
 * crop is rejected with the exact numbers so it can be fixed), then
 * auto-converted to WebP and compressed toward the slot's size target so
 * the person uploading never has to know what WebP is.
 */
class RAPM_Upload_Handler {

	const NONCE_ACTION = 'rapm_save_asset';

	public static function add_menu() {
		add_submenu_page(
			'edit.php?post_type=rapm_asset',
			__( 'Add New Asset', 'rapm' ),
			__( 'Add New Asset', 'rapm' ),
			'edit_posts',
			'rapm-add-asset',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Redirects the native "Add New" screen (which, with no 'thumbnail'/
	 * 'editor' support declared, is just a bare title field) to this
	 * validated form instead — the native screen has no useful path to
	 * actually create a working asset.
	 */
	public static function maybe_redirect_native_add_new() {
		global $pagenow;
		if ( 'post-new.php' === $pagenow && isset( $_GET['post_type'] ) && 'rapm_asset' === $_GET['post_type'] ) {
			wp_safe_redirect( admin_url( 'edit.php?post_type=rapm_asset&page=rapm-add-asset' ) );
			exit;
		}
	}

	public static function render_page() {
		$asset_id = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
		$is_edit  = $asset_id && 'rapm_asset' === get_post_type( $asset_id );

		$m = function ( $key, $default = '' ) use ( $asset_id, $is_edit ) {
			if ( ! $is_edit ) {
				return $default;
			}
			$val = get_post_meta( $asset_id, $key, true );
			return '' === $val ? $default : $val;
		};

		$title       = $is_edit ? get_the_title( $asset_id ) : '';
		$placement   = $m( '_rapm_placement', 'default' );
		$dest_type   = $m( '_rapm_destination_type', 'url' );
		$dest_value  = $m( '_rapm_destination_value' );
		$img_desktop = (int) $m( '_rapm_image_desktop_id' );
		$img_mobile  = (int) $m( '_rapm_image_mobile_id' );
		$slots       = RAPM_Slots::all();
		$kinds       = RAPM_Slots::kinds();

		// The kind can come from the URL (switching it before any file is
		// chosen, so the right dimensions show immediately) or from the
		// asset being edited; defaults to 'hero'.
		$kind_key = isset( $_GET['kind'] ) ? sanitize_key( wp_unslash( $_GET['kind'] ) ) : $m( '_rapm_kind', 'hero' );
		if ( ! isset( $kinds[ $kind_key ] ) ) {
			$kind_key = 'hero';
		}
		$kind         = RAPM_Slots::kind( $kind_key );
		$desktop_slot = $slots[ $kind['desktop'] ];
		$mobile_slot  = $slots[ $kind['mobile'] ];

		$error_key = isset( $_GET['rapm_error'] ) ? sanitize_key( wp_unslash( $_GET['rapm_error'] ) ) : '';
		?>
		<div class="wrap">
			<h1><?php echo $is_edit ? esc_html__( 'Edit Promotional Asset', 'rapm' ) : esc_html__( 'Add New Promotional Asset', 'rapm' ); ?></h1>

			<?php if ( $error_key ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( wp_unslash( rawurldecode( $error_key ) ) ); ?></p></div>
			<?php endif; ?>

			<table class="form-table" style="max-width:700px;">
				<tr>
					<th><label for="rapm_kind_selector"><?php esc_html_e( 'Kind', 'rapm' ); ?></label></th>
					<td>
						<select id="rapm_kind_selector">
							<?php foreach ( $kinds as $key => $info ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $kind_key, $key ); ?>><?php echo esc_html( $info['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Which required image sizes apply below. Changing this reloads the page.', 'rapm' ); ?></p>
					</td>
				</tr>
			</table>
			<script>
				document.getElementById( 'rapm_kind_selector' ).addEventListener( 'change', function () {
					var url = new URL( window.location.href );
					url.searchParams.set( 'kind', this.value );
					window.location.href = url.toString();
				} );
			</script>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="rapm_save_asset" />
				<input type="hidden" name="asset_id" value="<?php echo esc_attr( $asset_id ); ?>" />
				<input type="hidden" name="rapm_kind" value="<?php echo esc_attr( $kind_key ); ?>" />
				<?php wp_nonce_field( self::NONCE_ACTION, 'rapm_nonce' ); ?>

				<table class="form-table">
					<tr>
						<th><label for="rapm_title"><?php esc_html_e( 'Internal Name', 'rapm' ); ?></label></th>
						<td><input type="text" id="rapm_title" name="rapm_title" class="regular-text" value="<?php echo esc_attr( $title ); ?>" required />
							<p class="description"><?php esc_html_e( 'For your own reference in the admin list — not shown to site visitors.', 'rapm' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="rapm_placement"><?php esc_html_e( 'Placement', 'rapm' ); ?></label></th>
						<td><input type="text" id="rapm_placement" name="rapm_placement" value="<?php echo esc_attr( $placement ); ?>" />
							<p class="description"><?php echo esc_html( sprintf( __( 'Which [rapm_hero placement="..."] or [rapm_fold_banner placement="..."] this belongs to (matched separately per Kind). Leave as "default" unless this site needs more than one %s.', 'rapm' ), strtolower( $kind['label'] ) ) ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Images', 'rapm' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Each image is checked against its required size before it\'s accepted, then automatically converted to WebP and compressed — you don\'t need to convert anything yourself. Only choose a file here if you\'re adding one for the first time or replacing the current one.', 'rapm' ); ?></p>
				<table class="form-table">
					<tr>
						<th><label for="rapm_image_desktop"><?php echo esc_html( $desktop_slot['label'] ); ?></label></th>
						<td>
							<?php if ( $img_desktop ) : ?>
								<?php echo wp_get_attachment_image( $img_desktop, array( 240, 75 ), false, array( 'style' => 'display:block;margin-bottom:8px;border-radius:6px;object-fit:cover;' ) ); ?>
							<?php endif; ?>
							<input type="file" id="rapm_image_desktop" name="rapm_image_desktop" accept="image/*" />
							<p class="description"><?php echo esc_html( sprintf( __( 'Needs to be %1$dx%2$d px. Any common image format is fine — it\'ll be converted to WebP automatically.', 'rapm' ), $desktop_slot['width'], $desktop_slot['height'] ) ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="rapm_image_mobile"><?php echo esc_html( $mobile_slot['label'] ); ?></label></th>
						<td>
							<?php if ( $img_mobile ) : ?>
								<?php echo wp_get_attachment_image( $img_mobile, array( 120, 213 ), false, array( 'style' => 'display:block;margin-bottom:8px;border-radius:6px;object-fit:cover;' ) ); ?>
							<?php endif; ?>
							<input type="file" id="rapm_image_mobile" name="rapm_image_mobile" accept="image/*" />
							<p class="description"><?php echo esc_html( sprintf( __( 'Needs to be %1$dx%2$d px.', 'rapm' ), $mobile_slot['width'], $mobile_slot['height'] ) ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="rapm_alt_text"><?php esc_html_e( 'Image Description (alt text)', 'rapm' ); ?></label></th>
						<td><input type="text" id="rapm_alt_text" name="rapm_alt_text" class="regular-text" value="<?php echo esc_attr( $m( '_rapm_alt_text' ) ); ?>" placeholder="<?php esc_attr_e( 'e.g. Living room with cream sectional and walnut coffee table', 'rapm' ); ?>" />
							<p class="description"><?php esc_html_e( 'Describe the image itself, not the offer — the headline below already carries that as real text.', 'rapm' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Copy', 'rapm' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Rendered as real text over the image, never baked into it — this is what keeps it readable to search engines, screen readers, and AI answer tools, and lets you update wording without re-uploading anything.', 'rapm' ); ?></p>
				<table class="form-table">
					<tr>
						<th><label for="rapm_headline"><?php esc_html_e( 'Headline', 'rapm' ); ?></label></th>
						<td><input type="text" id="rapm_headline" name="rapm_headline" class="regular-text" value="<?php echo esc_attr( $m( '_rapm_headline' ) ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="rapm_subhead"><?php esc_html_e( 'Subheadline', 'rapm' ); ?></label></th>
						<td><input type="text" id="rapm_subhead" name="rapm_subhead" class="regular-text" value="<?php echo esc_attr( $m( '_rapm_subhead' ) ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="rapm_cta_text"><?php esc_html_e( 'Button Text', 'rapm' ); ?></label></th>
						<td><input type="text" id="rapm_cta_text" name="rapm_cta_text" value="<?php echo esc_attr( $m( '_rapm_cta_text', __( 'Shop Now', 'rapm' ) ) ); ?>" /></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Link', 'rapm' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><label for="rapm_dest_type"><?php esc_html_e( 'Links To', 'rapm' ); ?></label></th>
						<td>
							<select id="rapm_dest_type" name="rapm_dest_type">
								<?php foreach ( RAPM_Destination::types() as $type => $label ) : ?>
									<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $dest_type, $type ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="rapm_dest_value"><?php esc_html_e( 'Link Target', 'rapm' ); ?></label></th>
						<td><input type="text" id="rapm_dest_value" name="rapm_dest_value" class="regular-text" value="<?php echo esc_attr( $dest_value ); ?>" placeholder="https://…" />
							<p class="description"><?php esc_html_e( 'A full URL for "A specific link", or the numeric post/product/category ID for the others (find it in that item\'s own edit-screen URL in wp-admin).', 'rapm' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Schedule', 'rapm' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><label for="rapm_starts_at"><?php esc_html_e( 'Starts', 'rapm' ); ?></label></th>
						<td><input type="datetime-local" id="rapm_starts_at" name="rapm_starts_at" value="<?php echo esc_attr( $m( '_rapm_starts_at' ) ); ?>" />
							<p class="description"><?php esc_html_e( 'Leave blank to start showing immediately once published.', 'rapm' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="rapm_ends_at"><?php esc_html_e( 'Ends', 'rapm' ); ?></label></th>
						<td><input type="datetime-local" id="rapm_ends_at" name="rapm_ends_at" value="<?php echo esc_attr( $m( '_rapm_ends_at' ) ); ?>" />
							<p class="description"><?php esc_html_e( 'Leave blank to run indefinitely. This works reliably even behind a full-page cache plugin — it\'s checked in the visitor\'s own browser, not baked into a cached page.', 'rapm' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button( $is_edit ? __( 'Save Asset', 'rapm' ) : __( 'Create Asset', 'rapm' ) ); ?>
			</form>
		</div>
		<?php
	}

	public static function handle_save() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'rapm' ) );
		}
		check_admin_referer( self::NONCE_ACTION, 'rapm_nonce' );

		$asset_id = isset( $_POST['asset_id'] ) ? absint( $_POST['asset_id'] ) : 0;
		$is_edit  = $asset_id && 'rapm_asset' === get_post_type( $asset_id );

		$back = $is_edit
			? admin_url( 'edit.php?post_type=rapm_asset&page=rapm-add-asset&edit=' . $asset_id )
			: admin_url( 'edit.php?post_type=rapm_asset&page=rapm-add-asset' );

		$title = isset( $_POST['rapm_title'] ) ? sanitize_text_field( wp_unslash( $_POST['rapm_title'] ) ) : '';
		if ( '' === $title ) {
			self::fail( $back, __( 'Please enter an internal name for this asset.', 'rapm' ) );
		}

		$kinds    = RAPM_Slots::kinds();
		$kind_key = isset( $_POST['rapm_kind'] ) ? sanitize_key( wp_unslash( $_POST['rapm_kind'] ) ) : 'hero';
		if ( ! isset( $kinds[ $kind_key ] ) ) {
			$kind_key = 'hero';
		}
		$kind = RAPM_Slots::kind( $kind_key );

		// Validate + convert images BEFORE touching the post itself, so a
		// bad upload never leaves a half-saved asset behind.
		$slots          = RAPM_Slots::all();
		$new_desktop_id = null;
		$new_mobile_id  = null;

		if ( ! empty( $_FILES['rapm_image_desktop']['tmp_name'] ) ) {
			$result = self::process_upload( $_FILES['rapm_image_desktop'], $slots[ $kind['desktop'] ], $asset_id ?: 0 );
			if ( is_wp_error( $result ) ) {
				self::fail( $back, $result->get_error_message() );
			}
			$new_desktop_id = $result;
		}
		if ( ! empty( $_FILES['rapm_image_mobile']['tmp_name'] ) ) {
			$result = self::process_upload( $_FILES['rapm_image_mobile'], $slots[ $kind['mobile'] ], $asset_id ?: 0 );
			if ( is_wp_error( $result ) ) {
				self::fail( $back, $result->get_error_message() );
			}
			$new_mobile_id = $result;
		}

		if ( $is_edit ) {
			wp_update_post( array( 'ID' => $asset_id, 'post_title' => $title ) );
		} else {
			$asset_id = wp_insert_post(
				array(
					'post_title'  => $title,
					'post_type'   => 'rapm_asset',
					'post_status' => 'publish',
				)
			);
			if ( is_wp_error( $asset_id ) || ! $asset_id ) {
				self::fail( $back, __( 'Something went wrong creating this asset. Please try again.', 'rapm' ) );
			}
			// Images were uploaded (and parented to post 0) before this post
			// existed to parent them to — re-parent now so they don't sit in
			// the Media Library looking unattached.
			foreach ( array( $new_desktop_id, $new_mobile_id ) as $attachment_id ) {
				if ( $attachment_id ) {
					wp_update_post( array( 'ID' => $attachment_id, 'post_parent' => $asset_id ) );
				}
			}
		}

		if ( null !== $new_desktop_id ) {
			update_post_meta( $asset_id, '_rapm_image_desktop_id', $new_desktop_id );
		}
		if ( null !== $new_mobile_id ) {
			update_post_meta( $asset_id, '_rapm_image_mobile_id', $new_mobile_id );
		}

		update_post_meta( $asset_id, '_rapm_kind', $kind_key );

		$meta_fields = array(
			'rapm_placement'    => 'sanitize_title',
			'rapm_alt_text'     => 'sanitize_text_field',
			'rapm_headline'     => 'sanitize_text_field',
			'rapm_subhead'      => 'sanitize_text_field',
			'rapm_cta_text'     => 'sanitize_text_field',
			'rapm_dest_type'    => 'sanitize_key',
			'rapm_starts_at'    => 'sanitize_text_field',
			'rapm_ends_at'      => 'sanitize_text_field',
		);
		foreach ( $meta_fields as $field => $sanitizer ) {
			if ( isset( $_POST[ $field ] ) ) {
				update_post_meta( $asset_id, '_' . $field, call_user_func( $sanitizer, wp_unslash( $_POST[ $field ] ) ) );
			}
		}
		if ( isset( $_POST['rapm_dest_value'] ) ) {
			$dest_type = isset( $_POST['rapm_dest_type'] ) ? sanitize_key( wp_unslash( $_POST['rapm_dest_type'] ) ) : 'url';
			$raw_value = wp_unslash( $_POST['rapm_dest_value'] );
			$value     = 'url' === $dest_type ? esc_url_raw( $raw_value ) : absint( $raw_value );
			update_post_meta( $asset_id, '_rapm_destination_value', $value );
		}
		if ( empty( get_post_meta( $asset_id, '_rapm_placement', true ) ) ) {
			update_post_meta( $asset_id, '_rapm_placement', 'default' );
		}

		wp_safe_redirect( admin_url( 'edit.php?post_type=rapm_asset&page=rapm-add-asset&edit=' . $asset_id . '&rapm_saved=1' ) );
		exit;
	}

	/**
	 * Validates one uploaded file against a slot's required dimensions
	 * (hard reject — a wrong crop needs a human, not software, to fix),
	 * then converts it to WebP/compresses it and sideloads it into the
	 * Media Library. Returns the new attachment ID, or a WP_Error with a
	 * message stating the slot's requirement against what was actually
	 * uploaded.
	 */
	private static function process_upload( $file, $slot, $parent_id ) {
		if ( ! empty( $file['error'] ) && UPLOAD_ERR_OK !== $file['error'] ) {
			return new WP_Error( 'rapm_upload_error', __( 'The file failed to upload — please try again.', 'rapm' ) );
		}

		$filetype = wp_check_filetype( $file['name'] );
		if ( empty( $filetype['type'] ) || 0 !== strpos( $filetype['type'], 'image/' ) ) {
			return new WP_Error( 'rapm_not_image', __( 'That file doesn\'t look like an image.', 'rapm' ) );
		}

		$dims = getimagesize( $file['tmp_name'] );
		if ( ! $dims ) {
			return new WP_Error( 'rapm_unreadable', __( 'Could not read that image file.', 'rapm' ) );
		}
		list( $width, $height ) = $dims;

		if ( ! RAPM_Slots::dimensions_match( $slot, $width, $height ) ) {
			return new WP_Error(
				'rapm_wrong_dimensions',
				sprintf(
					/* translators: 1: required width, 2: required height, 3: actual width, 4: actual height */
					__( 'This needs to be %1$dx%2$d px. The file you uploaded is %3$dx%4$d px — please crop or re-export it to the right size and try again.', 'rapm' ),
					$slot['width'],
					$slot['height'],
					$width,
					$height
				)
			);
		}

		$already_webp_and_small = 'image/webp' === $filetype['type'] && ( filesize( $file['tmp_name'] ) / 1024 ) <= $slot['max_kb'];

		if ( $already_webp_and_small ) {
			$upload_path = $file['tmp_name'];
			$upload_name = wp_unique_filename( wp_upload_dir()['path'], sanitize_file_name( $file['name'] ) );
		} else {
			if ( ! RAPM_Webp_Converter::is_available() ) {
				return new WP_Error( 'rapm_no_webp_support', __( 'This server can\'t auto-convert images to WebP (no Imagick or GD WebP support found). Please upload a .webp file directly, or ask your host to enable WebP support.', 'rapm' ) );
			}
			$converted = RAPM_Webp_Converter::convert( $file['tmp_name'], $slot['max_kb'] );
			if ( is_wp_error( $converted ) ) {
				return $converted;
			}
			$upload_path = $converted['path'];
			$upload_name = wp_unique_filename( wp_upload_dir()['path'], pathinfo( $file['name'], PATHINFO_FILENAME ) . '.webp' );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$sideload = array(
			'name'     => $upload_name,
			'tmp_name' => $upload_path,
			'type'     => 'image/webp',
			'size'     => filesize( $upload_path ),
		);

		$attachment_id = media_handle_sideload( $sideload, $parent_id );

		if ( ! $already_webp_and_small && file_exists( $upload_path ) ) {
			wp_delete_file( $upload_path ); // media_handle_sideload() copies it in; the temp original is no longer needed.
		}

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		return $attachment_id;
	}

	private static function fail( $back_url, $message ) {
		wp_safe_redirect( add_query_arg( 'rapm_error', rawurlencode( $message ), $back_url ) );
		exit;
	}
}
