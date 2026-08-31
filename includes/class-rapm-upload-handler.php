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
	 * Loads the same CSS the live carousel uses, only on this one admin
	 * screen, so the on-page preview (image + text overlay together) is a
	 * pixel-accurate WYSIWYG of what actually ships — not a separate
	 * approximation that could itself mislead someone into thinking a
	 * clash is fine when it isn't.
	 */
	public static function enqueue_admin_assets() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'rapm-add-asset' !== $page ) {
			return;
		}
		wp_enqueue_style( 'rapm-hero-css', RAPM_URL . 'assets/css/rapm-hero.css', array(), RAPM_VERSION );
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

		// Explicitly stored, not inferred from whether the text fields are
		// empty — a brand-new asset and an existing one where "already on
		// the image" was deliberately chosen both have empty fields, and
		// need to be told apart to restore the right choice on re-edit.
		$text_mode = $m( '_rapm_text_mode', 'type' );

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
					<th><label for="rapm_kind_selector"><?php esc_html_e( 'Type of Promotion', 'rapm' ); ?></label></th>
					<td>
						<select id="rapm_kind_selector">
							<?php foreach ( $kinds as $key => $info ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $kind_key, $key ); ?>><?php echo esc_html( $info['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Choose which one before uploading pictures below — each type needs a different picture size, and picking this first tells you the right size to use.', 'rapm' ); ?></p>
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
						<th><label for="rapm_placement"><?php esc_html_e( 'Which Spot on the Site', 'rapm' ); ?></label></th>
						<td><input type="text" id="rapm_placement" name="rapm_placement" value="<?php echo esc_attr( $placement ); ?>" />
							<p class="description"><?php echo esc_html( sprintf( __( 'Just leave this as "default" unless someone has told you this site shows more than one %s in different spots (like one on the homepage and a different one on a category page) and asked you to type a specific name here.', 'rapm' ), strtolower( $kind['label'] ) ) ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Images', 'rapm' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Upload whatever picture you have — any common format (JPG, PNG, whatever your phone or camera saves) is fine. This tool will automatically resize/convert it for you if needed, and will tell you clearly if it can\'t be used.', 'rapm' ); ?></p>
				<table class="form-table">
					<tr>
						<th><label for="rapm_image_desktop"><?php echo esc_html( $desktop_slot['label'] ); ?></label></th>
						<td>
							<?php if ( $img_desktop ) : ?>
								<?php echo wp_get_attachment_image( $img_desktop, array( 240, 75 ), false, array( 'style' => 'display:block;margin-bottom:8px;border-radius:6px;object-fit:cover;' ) ); ?>
							<?php endif; ?>
							<input type="file" id="rapm_image_desktop" name="rapm_image_desktop" accept="image/*" <?php echo $img_desktop ? '' : 'required'; ?> />
							<p class="description"><?php echo esc_html( sprintf( __( 'This picture needs to be exactly %1$d by %2$d (width by height, in pixels — this is usually shown when you export, crop, or resize a photo). If it\'s the wrong size, you\'ll see exactly what you uploaded vs. what\'s needed so you know what to fix.', 'rapm' ), $desktop_slot['width'], $desktop_slot['height'] ) ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="rapm_image_mobile"><?php echo esc_html( $mobile_slot['label'] ); ?></label></th>
						<td>
							<?php if ( $img_mobile ) : ?>
								<?php echo wp_get_attachment_image( $img_mobile, array( 120, 213 ), false, array( 'style' => 'display:block;margin-bottom:8px;border-radius:6px;object-fit:cover;' ) ); ?>
							<?php endif; ?>
							<input type="file" id="rapm_image_mobile" name="rapm_image_mobile" accept="image/*" />
							<p class="description"><?php echo esc_html( sprintf( __( 'The version shown on phones — needs to be exactly %1$d by %2$d.', 'rapm' ), $mobile_slot['width'], $mobile_slot['height'] ) ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="rapm_alt_text"><?php esc_html_e( 'What\'s in the Picture', 'rapm' ); ?></label></th>
						<td><input type="text" id="rapm_alt_text" name="rapm_alt_text" class="regular-text" value="<?php echo esc_attr( $m( '_rapm_alt_text' ) ); ?>" placeholder="<?php esc_attr_e( 'e.g. Living room with cream sectional and walnut coffee table', 'rapm' ); ?>" />
							<p class="description"><?php esc_html_e( 'A short, plain description of what the picture shows (not the sale/offer — that goes below). This helps people using a screen reader, and helps the picture show up in search results.', 'rapm' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Sale Text', 'rapm' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Does your picture already show the price, sale, or date on it?', 'rapm' ); ?></th>
						<td>
							<label style="display:block;margin-bottom:8px;">
								<input type="radio" name="rapm_text_mode" id="rapm_text_mode_type" value="type" <?php checked( 'type', $text_mode ); ?> />
								<?php esc_html_e( 'No — I\'ll type it below (recommended)', 'rapm' ); ?>
							</label>
							<label style="display:block;">
								<input type="radio" name="rapm_text_mode" id="rapm_text_mode_image" value="image" <?php checked( 'image', $text_mode ); ?> />
								<?php esc_html_e( 'Yes — it\'s already printed on the picture, I don\'t need to type anything below', 'rapm' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Pick whichever is true. Typing something below AND having it already on the picture means it would show up twice, on top of itself.', 'rapm' ); ?></p>
						</td>
					</tr>
				</table>

				<div id="rapm-text-fields" <?php echo 'image' === $text_mode ? 'style="display:none;"' : ''; ?>>
					<table class="form-table">
						<tr>
							<th><label for="rapm_headline"><?php esc_html_e( 'Headline', 'rapm' ); ?></label></th>
							<td><input type="text" id="rapm_headline" name="rapm_headline" class="regular-text" value="<?php echo esc_attr( $m( '_rapm_headline' ) ); ?>" placeholder="<?php esc_attr_e( 'e.g. Labor Day Sale', 'rapm' ); ?>" /></td>
						</tr>
						<tr>
							<th><label for="rapm_subhead"><?php esc_html_e( 'Smaller line under the headline', 'rapm' ); ?></label></th>
							<td><input type="text" id="rapm_subhead" name="rapm_subhead" class="regular-text" value="<?php echo esc_attr( $m( '_rapm_subhead' ) ); ?>" placeholder="<?php esc_attr_e( 'e.g. Up to 30% off sofas and sectionals', 'rapm' ); ?>" /></td>
						</tr>
						<tr>
							<th><label for="rapm_cta_text"><?php esc_html_e( 'Button Text', 'rapm' ); ?></label></th>
							<td><input type="text" id="rapm_cta_text" name="rapm_cta_text" value="<?php echo esc_attr( $m( '_rapm_cta_text' ) ); ?>" placeholder="<?php esc_attr_e( 'e.g. Shop Now', 'rapm' ); ?>" /></td>
						</tr>
					</table>
				</div>
				<script>
					( function () {
						var typeFields = document.getElementById( 'rapm-text-fields' );
						function sync() {
							var wantsTyped = document.getElementById( 'rapm_text_mode_type' ).checked;
							typeFields.style.display = wantsTyped ? '' : 'none';
						}
						document.getElementById( 'rapm_text_mode_type' ).addEventListener( 'change', sync );
						document.getElementById( 'rapm_text_mode_image' ).addEventListener( 'change', sync );
					} )();
				</script>

				<h2><?php esc_html_e( 'Live Preview', 'rapm' ); ?></h2>
				<p class="description"><?php esc_html_e( 'This shows exactly what visitors will see, updating as you type or choose a picture. If something looks off — text overlapping, hard to read, etc. — fix it here before saving.', 'rapm' ); ?></p>
				<div id="rapm-preview-wrap" style="max-width:600px;margin-bottom:24px;">
					<div id="rapm-preview" class="rapm-hero" style="aspect-ratio:<?php echo esc_attr( $desktop_slot['width'] . '/' . $desktop_slot['height'] ); ?>;background:#333;">
						<div class="rapm-slide" style="width:100%;height:100%;">
							<img id="rapm-preview-img" src="<?php echo $img_desktop ? esc_url( wp_get_attachment_image_url( $img_desktop, 'full' ) ) : ''; ?>" alt="" style="width:100%;height:100%;object-fit:cover;display:<?php echo $img_desktop ? 'block' : 'none'; ?>;" />
							<div class="rapm-slide-copy">
								<h2 class="rapm-headline" id="rapm-preview-headline"></h2>
								<p class="rapm-subhead" id="rapm-preview-subhead"></p>
								<span class="rapm-cta-btn" id="rapm-preview-cta"></span>
							</div>
						</div>
					</div>
					<p class="description" id="rapm-preview-empty" style="<?php echo $img_desktop ? 'display:none;' : ''; ?>"><?php esc_html_e( 'Choose a desktop image above to preview it here.', 'rapm' ); ?></p>
				</div>
				<script>
					( function () {
						var headlineInput = document.getElementById( 'rapm_headline' );
						var subheadInput  = document.getElementById( 'rapm_subhead' );
						var ctaInput      = document.getElementById( 'rapm_cta_text' );
						var fileInput     = document.getElementById( 'rapm_image_desktop' );
						var previewImg    = document.getElementById( 'rapm-preview-img' );
						var previewEmpty  = document.getElementById( 'rapm-preview-empty' );

						function setText( el, value ) {
							el.textContent = value;
							el.style.display = value ? '' : 'none';
						}
						function updateCopy() {
							setText( document.getElementById( 'rapm-preview-headline' ), headlineInput.value );
							setText( document.getElementById( 'rapm-preview-subhead' ), subheadInput.value );
							setText( document.getElementById( 'rapm-preview-cta' ), ctaInput.value );
						}
						[ headlineInput, subheadInput, ctaInput ].forEach( function ( el ) {
							el.addEventListener( 'input', updateCopy );
						} );
						updateCopy();

						fileInput.addEventListener( 'change', function () {
							if ( ! this.files || ! this.files[0] ) { return; }
							previewImg.src = URL.createObjectURL( this.files[0] );
							previewImg.style.display = 'block';
							previewEmpty.style.display = 'none';
						} );
					} )();
				</script>

				<h2><?php esc_html_e( 'Where It Goes When Clicked', 'rapm' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><label for="rapm_dest_type"><?php esc_html_e( 'Send visitors to...', 'rapm' ); ?></label></th>
						<td>
							<select id="rapm_dest_type" name="rapm_dest_type">
								<?php foreach ( RAPM_Destination::types() as $type => $label ) : ?>
									<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $dest_type, $type ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'If you\'re not sure which to pick, use "A specific link" — it\'s just a normal web address, like the ones in your browser\'s address bar.', 'rapm' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="rapm_dest_value"><?php esc_html_e( 'Address / ID', 'rapm' ); ?></label></th>
						<td><input type="text" id="rapm_dest_value" name="rapm_dest_value" class="regular-text" value="<?php echo esc_attr( $dest_value ); ?>" placeholder="https://…" />
							<p class="description"><?php esc_html_e( 'For "A specific link," paste the full web address (starting with https://). For the other options, ask whoever manages the website for the ID number — it\'s not something you can guess.', 'rapm' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'When It Should Show', 'rapm' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><label for="rapm_starts_at"><?php esc_html_e( 'Start showing on', 'rapm' ); ?></label></th>
						<td><input type="datetime-local" id="rapm_starts_at" name="rapm_starts_at" value="<?php echo esc_attr( $m( '_rapm_starts_at' ) ); ?>" />
							<p class="description"><?php esc_html_e( 'Leave blank to start showing right away.', 'rapm' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="rapm_ends_at"><?php esc_html_e( 'Stop showing on', 'rapm' ); ?></label></th>
						<td><input type="datetime-local" id="rapm_ends_at" name="rapm_ends_at" value="<?php echo esc_attr( $m( '_rapm_ends_at' ) ); ?>" />
							<p class="description"><?php esc_html_e( 'Leave blank to keep showing until you come back and change it. You can trust this date/time — it\'ll turn off exactly when it says, automatically, no matter what.', 'rapm' ); ?></p>
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

		// A desktop image is the one truly required piece — without it the
		// carousel has nothing to show and silently skips this asset
		// entirely (see RAPM_Hero_Carousel::render_slide()). Catch that
		// here with a clear message rather than letting someone publish an
		// asset that will never actually appear anywhere, with no error to
		// explain why.
		$has_desktop_image = $new_desktop_id || ( $is_edit && get_post_meta( $asset_id, '_rapm_image_desktop_id', true ) );
		if ( ! $has_desktop_image ) {
			self::fail( $back, __( 'Please upload a desktop image — it\'s required for this asset to actually display anywhere.', 'rapm' ) );
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

		$text_mode = isset( $_POST['rapm_text_mode'] ) && 'image' === $_POST['rapm_text_mode'] ? 'image' : 'type';
		update_post_meta( $asset_id, '_rapm_text_mode', $text_mode );

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
			// The text fields are only hidden with CSS when "already on the
			// image" is chosen — the browser still submits whatever value
			// was left in them. Force them empty here rather than trust
			// that, so switching to "already on the image" can't leave a
			// stale headline/subhead/CTA saved underneath it.
			if ( 'image' === $text_mode && in_array( $field, array( 'rapm_headline', 'rapm_subhead', 'rapm_cta_text' ), true ) ) {
				update_post_meta( $asset_id, '_' . $field, '' );
				continue;
			}
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
