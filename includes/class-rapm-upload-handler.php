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
		$img_desktop    = (int) $m( '_rapm_image_desktop_id' );
		$img_mobile     = (int) $m( '_rapm_image_mobile_id' );
		$desktop_source = $m( '_rapm_image_desktop_source', 'upload' );
		$desktop_url    = $m( '_rapm_image_desktop_url' );
		$desktop_synced = $m( '_rapm_image_desktop_synced_at' );
		$desktop_error  = $m( '_rapm_image_desktop_sync_error' );
		$mobile_source  = $m( '_rapm_image_mobile_source', 'upload' );
		$mobile_url     = $m( '_rapm_image_mobile_url' );
		$mobile_synced  = $m( '_rapm_image_mobile_synced_at' );
		$mobile_error   = $m( '_rapm_image_mobile_sync_error' );
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
		$has_images   = (bool) $kind['desktop']; // false for text-only kinds (Marquee)
		$desktop_slot = $has_images ? $slots[ $kind['desktop'] ] : null;
		$mobile_slot  = $has_images ? $slots[ $kind['mobile'] ] : null;

		// sanitize_key() is for slugs (lowercase alphanumeric + dashes only)
		// and would silently mangle a real sentence — every space, capital
		// letter, and punctuation mark stripped out. sanitize_text_field()
		// is the correct one for actual human-readable text. No separate
		// url-decode needed here: PHP already decodes $_GET values (fail()
		// below only needs to encode once, when building the redirect URL).
		$error_message = isset( $_GET['rapm_error'] ) ? sanitize_text_field( wp_unslash( $_GET['rapm_error'] ) ) : '';
		?>
		<div class="wrap">
			<h1>
				<?php echo $is_edit ? esc_html__( 'Edit Promotional Asset', 'rapm' ) : esc_html__( 'Add New Promotional Asset', 'rapm' ); ?>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=rapm_asset&page=rapm-help' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Help & FAQ', 'rapm' ); ?></a>
			</h1>
			<style>
				.rapm-dest-picker-results { position: relative; }
				.rapm-dest-picker-list { position: absolute; z-index: 10; margin: 0; padding: 4px 0; list-style: none; background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 2px 6px rgba(0,0,0,.15); max-width: 25em; max-height: 16em; overflow-y: auto; }
				.rapm-dest-picker-list li { padding: 6px 10px; cursor: pointer; }
				.rapm-dest-picker-list li:hover { background: #f0f0f1; }
				#rapm-dest-picker-current { font-weight: 600; }
			</style>

			<?php if ( $error_message ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $error_message ); ?></p></div>
			<?php endif; ?>

			<?php if ( isset( $_GET['rapm_saved'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><strong><?php esc_html_e( 'Saved.', 'rapm' ); ?></strong> <?php esc_html_e( 'This promotion is saved. If its start date has arrived, it\'s already live anywhere the shortcode below has been added.', 'rapm' ); ?></p></div>
			<?php endif; ?>

			<?php if ( $is_edit ) : ?>
				<?php
				$placement_slug = sanitize_title( $placement ?: 'default' );
				$shortcode_text = 'default' === $placement_slug
					? '[' . $kind['shortcode'] . ']'
					: '[' . $kind['shortcode'] . ' placement="' . $placement_slug . '"]';
				?>
				<div class="notice notice-info" style="padding:12px 16px;">
					<p style="margin:0 0 6px;"><strong><?php esc_html_e( 'Where this shows up on the site', 'rapm' ); ?></strong></p>
					<p style="margin:0 0 6px;"><?php esc_html_e( 'This promotion only appears where this has been added to the website:', 'rapm' ); ?> <code><?php echo esc_html( $shortcode_text ); ?></code></p>
					<?php if ( $kind['has_elementor_widget'] ) : ?>
						<p style="margin:0 0 6px;"><?php esc_html_e( 'If this site uses Elementor, the "Promo Carousel" widget (under the Promo Manager category) can add it there instead of typing that code.', 'rapm' ); ?></p>
					<?php endif; ?>
					<p style="margin:0;"><?php esc_html_e( 'Not sure if it\'s already on a page? Ask whoever manages the website — it only needs to be added once per spot, not again for every new promotion you add here.', 'rapm' ); ?></p>
				</div>
			<?php endif; ?>

			<table class="form-table" style="max-width:700px;">
				<tr>
					<th><label for="rapm_kind_selector"><?php esc_html_e( 'Type of Promotion', 'rapm' ); ?></label></th>
					<td>
						<select id="rapm_kind_selector">
							<?php
							foreach ( $kinds as $key => $info ) :
								if ( $info['desktop'] ) {
									$opt_desktop = $slots[ $info['desktop'] ];
									$opt_mobile  = $slots[ $info['mobile'] ];
									$opt_label   = $info['desktop'] === $info['mobile']
										? sprintf(
											/* translators: 1: kind label, 2: card width, 3: card height */
											__( '%1$s — %2$dx%3$d', 'rapm' ),
											$info['label'],
											$opt_desktop['width'],
											$opt_desktop['height']
										)
										: sprintf(
											/* translators: 1: kind label, 2: desktop width, 3: desktop height, 4: mobile width, 5: mobile height */
											__( '%1$s — Desktop %2$dx%3$d, Mobile %4$dx%5$d', 'rapm' ),
											$info['label'],
											$opt_desktop['width'],
											$opt_desktop['height'],
											$opt_mobile['width'],
											$opt_mobile['height']
										);
								} else {
									$opt_label = $info['label'];
								}
								?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $kind_key, $key ); ?>><?php echo esc_html( $opt_label ); ?></option>
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
							<?php if ( $has_images ) : ?>
								<p class="description"><strong><?php esc_html_e( 'This is not about phones vs. computers', 'rapm' ); ?></strong> — <?php esc_html_e( 'every promotion already shows the right picture on both automatically once you upload one of each below. Leave this as "default" for that.', 'rapm' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
				</table>

				<?php if ( $has_images ) : ?>
				<h2><?php esc_html_e( 'Images', 'rapm' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Upload whatever picture you have — any common format (JPG, PNG, whatever your phone or camera saves) is fine. This tool will automatically resize/convert it for you if needed, and will tell you clearly if it can\'t be used.', 'rapm' ); ?></p>
				<p class="description"><?php esc_html_e( 'Upload both below on every promotion — visitors on a computer automatically see the Desktop one, visitors on a phone automatically see the Mobile one. You never need to create a separate promotion for each device.', 'rapm' ); ?></p>
				<table class="form-table">
					<tr>
						<th><label><?php esc_html_e( 'Desktop Promotion', 'rapm' ); ?></label></th>
						<td>
							<?php if ( $img_desktop ) : ?>
								<?php echo wp_get_attachment_image( $img_desktop, array( 240, 75 ), false, array( 'style' => 'display:block;margin-bottom:8px;border-radius:6px;object-fit:cover;' ) ); ?>
							<?php endif; ?>

							<label class="rapm-source-choice"><input type="radio" name="rapm_image_desktop_source" class="rapm-source-radio" data-target="desktop" value="upload" <?php checked( 'upload', $desktop_source ); ?> /> <?php esc_html_e( 'Upload a file', 'rapm' ); ?></label>
							<label class="rapm-source-choice"><input type="radio" name="rapm_image_desktop_source" class="rapm-source-radio" data-target="desktop" value="link" <?php checked( 'link', $desktop_source ); ?> /> <?php esc_html_e( 'Use a link', 'rapm' ); ?></label>

							<div id="rapm-desktop-upload-row" style="margin-top:8px;<?php echo 'link' === $desktop_source ? 'display:none;' : ''; ?>">
								<input type="file" id="rapm_image_desktop" name="rapm_image_desktop" accept="image/*" <?php echo ( $img_desktop || 'link' === $desktop_source ) ? '' : 'required'; ?> />
							</div>
							<div id="rapm-desktop-link-row" style="margin-top:8px;<?php echo 'link' === $desktop_source ? '' : 'display:none;'; ?>">
								<input type="url" id="rapm_image_desktop_url" name="rapm_image_desktop_url" class="regular-text" value="<?php echo esc_attr( $desktop_url ); ?>" placeholder="https://…" />
								<p class="description"><?php esc_html_e( 'Paste a direct link to the picture, or a Google Drive share link (set the file\'s sharing setting to "Anyone with the link"). We\'ll check this link every hour and automatically update the picture if it changes — you never have to come back and re-upload it yourself.', 'rapm' ); ?></p>
								<?php if ( $desktop_error ) : ?>
									<p class="description" style="color:#b32d2e;"><?php echo esc_html( sprintf( __( 'Couldn\'t update from this link: %s Still showing the last picture that worked — nothing is broken on the live site.', 'rapm' ), $desktop_error ) ); ?></p>
								<?php elseif ( $desktop_synced ) : ?>
									<p class="description"><?php echo esc_html( sprintf( __( 'Last checked: %s ago', 'rapm' ), human_time_diff( strtotime( $desktop_synced ) ) ) ); ?></p>
								<?php endif; ?>
							</div>

							<p class="description"><?php echo esc_html( sprintf( __( 'This picture should be %1$d by %2$d (width by height, in pixels). If it\'s the same shape at a different size (say, an export at twice the resolution), it\'s resized automatically — no need to fix that yourself. If it\'s a different shape entirely, you\'ll see exactly what you uploaded vs. what\'s needed so you know what to fix.', 'rapm' ), $desktop_slot['width'], $desktop_slot['height'] ) ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label><?php esc_html_e( 'Mobile Promotion', 'rapm' ); ?></label></th>
						<td>
							<?php if ( $img_mobile ) : ?>
								<?php echo wp_get_attachment_image( $img_mobile, array( 120, 213 ), false, array( 'style' => 'display:block;margin-bottom:8px;border-radius:6px;object-fit:cover;' ) ); ?>
							<?php endif; ?>

							<label class="rapm-source-choice"><input type="radio" name="rapm_image_mobile_source" class="rapm-source-radio" data-target="mobile" value="upload" <?php checked( 'upload', $mobile_source ); ?> /> <?php esc_html_e( 'Upload a file', 'rapm' ); ?></label>
							<label class="rapm-source-choice"><input type="radio" name="rapm_image_mobile_source" class="rapm-source-radio" data-target="mobile" value="link" <?php checked( 'link', $mobile_source ); ?> /> <?php esc_html_e( 'Use a link', 'rapm' ); ?></label>

							<div id="rapm-mobile-upload-row" style="margin-top:8px;<?php echo 'link' === $mobile_source ? 'display:none;' : ''; ?>">
								<input type="file" id="rapm_image_mobile" name="rapm_image_mobile" accept="image/*" />
							</div>
							<div id="rapm-mobile-link-row" style="margin-top:8px;<?php echo 'link' === $mobile_source ? '' : 'display:none;'; ?>">
								<input type="url" id="rapm_image_mobile_url" name="rapm_image_mobile_url" class="regular-text" value="<?php echo esc_attr( $mobile_url ); ?>" placeholder="https://…" />
								<p class="description"><?php esc_html_e( 'Paste a direct link to the picture, or a Google Drive share link (set the file\'s sharing setting to "Anyone with the link"). We\'ll check this link every hour and automatically update the picture if it changes.', 'rapm' ); ?></p>
								<?php if ( $mobile_error ) : ?>
									<p class="description" style="color:#b32d2e;"><?php echo esc_html( sprintf( __( 'Couldn\'t update from this link: %s Still showing the last picture that worked — nothing is broken on the live site.', 'rapm' ), $mobile_error ) ); ?></p>
								<?php elseif ( $mobile_synced ) : ?>
									<p class="description"><?php echo esc_html( sprintf( __( 'Last checked: %s ago', 'rapm' ), human_time_diff( strtotime( $mobile_synced ) ) ) ); ?></p>
								<?php endif; ?>
							</div>

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
				<style>
					.rapm-source-choice { font-size: 13px; margin-right: 16px; font-weight: normal; }
				</style>
				<script>
					( function () {
						function syncImageSource( target ) {
							var checked = document.querySelector( 'input[name="rapm_image_' + target + '_source"]:checked' );
							var val = checked ? checked.value : 'upload';
							document.getElementById( 'rapm-' + target + '-upload-row' ).style.display = 'link' === val ? 'none' : '';
							document.getElementById( 'rapm-' + target + '-link-row' ).style.display = 'link' === val ? '' : 'none';
						}
						document.querySelectorAll( '.rapm-source-radio' ).forEach( function ( radio ) {
							radio.addEventListener( 'change', function () { syncImageSource( this.getAttribute( 'data-target' ) ); } );
						} );
					} )();
				</script>
				<?php endif; // $has_images ?>

				<?php if ( $has_images ) : ?>
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
				<?php else : ?>
				<h2><?php esc_html_e( 'Text', 'rapm' ); ?></h2>
				<p class="description"><?php esc_html_e( 'This type doesn\'t use a picture — just type the text below.', 'rapm' ); ?></p>
				<?php endif; ?>

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
						<tr>
							<th><label for="rapm_text_align"><?php esc_html_e( 'Text Alignment', 'rapm' ); ?></label></th>
							<td>
								<select id="rapm_text_align" name="rapm_text_align">
									<option value="left" <?php selected( $m( '_rapm_text_align', 'left' ), 'left' ); ?>><?php esc_html_e( 'Left', 'rapm' ); ?></option>
									<option value="center" <?php selected( $m( '_rapm_text_align', 'left' ), 'center' ); ?>><?php esc_html_e( 'Center', 'rapm' ); ?></option>
									<option value="right" <?php selected( $m( '_rapm_text_align', 'left' ), 'right' ); ?>><?php esc_html_e( 'Right', 'rapm' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th><label for="rapm_text_color"><?php esc_html_e( 'Text Color', 'rapm' ); ?></label></th>
							<td>
								<input type="color" id="rapm_text_color" name="rapm_text_color" value="<?php echo esc_attr( $m( '_rapm_text_color', '#ffffff' ) ); ?>" style="height:32px;width:60px;padding:2px;vertical-align:middle;" />
								<p class="description"><?php esc_html_e( 'Only changes the headline and smaller line — the button always stays white for readability.', 'rapm' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="rapm_text_style"><?php esc_html_e( 'Text Style', 'rapm' ); ?></label></th>
							<td>
								<select id="rapm_text_style" name="rapm_text_style">
									<option value="bold" <?php selected( $m( '_rapm_text_style', 'bold' ), 'bold' ); ?>><?php esc_html_e( 'Bold (default)', 'rapm' ); ?></option>
									<option value="elegant" <?php selected( $m( '_rapm_text_style', 'bold' ), 'elegant' ); ?>><?php esc_html_e( 'Elegant', 'rapm' ); ?></option>
									<option value="minimal" <?php selected( $m( '_rapm_text_style', 'bold' ), 'minimal' ); ?>><?php esc_html_e( 'Minimal', 'rapm' ); ?></option>
								</select>
							</td>
						</tr>
						<?php $site_fonts = RAPM_Elementor::global_fonts(); ?>
						<?php if ( $site_fonts ) : ?>
							<tr>
								<th><label for="rapm_text_font"><?php esc_html_e( 'Typeface', 'rapm' ); ?></label></th>
								<td>
									<select id="rapm_text_font" name="rapm_text_font">
										<option value=""><?php esc_html_e( 'Site Default', 'rapm' ); ?></option>
										<?php foreach ( $site_fonts as $font_id => $font_title ) : ?>
											<option value="<?php echo esc_attr( $font_id ); ?>" <?php selected( $m( '_rapm_text_font' ), $font_id ); ?>><?php echo esc_html( $font_title ); ?></option>
										<?php endforeach; ?>
									</select>
									<p class="description"><?php esc_html_e( 'These are the same fonts already set up for this website in Elementor (Site Settings > Global Fonts). Picking one keeps the promotion\'s text matching the rest of the site — including automatically, if that font is ever changed later.', 'rapm' ); ?></p>
								</td>
							</tr>
						<?php endif; ?>
					</table>
				</div>
				<?php if ( $has_images ) : ?>
				<script>
					( function () {
						var typeFields = document.getElementById( 'rapm-text-fields' );
						var modeType   = document.getElementById( 'rapm_text_mode_type' );
						var modeImage  = document.getElementById( 'rapm_text_mode_image' );
						function sync() {
							typeFields.style.display = modeType.checked ? '' : 'none';
						}
						modeType.addEventListener( 'change', sync );
						modeImage.addEventListener( 'change', sync );
					} )();
				</script>
				<?php endif; ?>

				<?php if ( $has_images ) : ?>
				<h2><?php esc_html_e( 'Live Preview', 'rapm' ); ?></h2>
				<p class="description"><?php esc_html_e( 'This shows exactly what visitors will see, updating as you type or choose a picture. If something looks off — text overlapping, hard to read, etc. — fix it here before saving.', 'rapm' ); ?></p>
				<div id="rapm-preview-wrap" style="max-width:600px;margin-bottom:24px;">
					<div id="rapm-preview" class="rapm-hero" style="aspect-ratio:<?php echo esc_attr( $desktop_slot['width'] . '/' . $desktop_slot['height'] ); ?>;background:#333;">
						<div class="rapm-slide" style="width:100%;height:100%;">
							<img id="rapm-preview-img" src="<?php echo $img_desktop ? esc_url( wp_get_attachment_image_url( $img_desktop, 'full' ) ) : ''; ?>" alt="" style="width:100%;height:100%;object-fit:cover;display:<?php echo $img_desktop ? 'block' : 'none'; ?>;" />
							<?php
							$preview_font_map    = RAPM_Elementor::font_family_map();
							$preview_font_family = isset( $preview_font_map[ $m( '_rapm_text_font' ) ] ) ? $preview_font_map[ $m( '_rapm_text_font' ) ] : '';
							?>
							<div class="rapm-slide-copy" id="rapm-preview-copy" data-align="<?php echo esc_attr( $m( '_rapm_text_align', 'left' ) ); ?>" data-style="<?php echo esc_attr( $m( '_rapm_text_style', 'bold' ) ); ?>" style="color:<?php echo esc_attr( $m( '_rapm_text_color', '#ffffff' ) ); ?>;<?php echo $preview_font_family ? 'font-family:' . esc_attr( $preview_font_family ) . ';' : ''; ?>">
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
						var alignInput    = document.getElementById( 'rapm_text_align' );
						var colorInput    = document.getElementById( 'rapm_text_color' );
						var styleInput    = document.getElementById( 'rapm_text_style' );
						var fileInput     = document.getElementById( 'rapm_image_desktop' );
						var previewImg    = document.getElementById( 'rapm-preview-img' );
						var previewCopy   = document.getElementById( 'rapm-preview-copy' );
						var previewEmpty  = document.getElementById( 'rapm-preview-empty' );

						function setText( el, value ) {
							el.textContent = value;
							el.style.display = value ? '' : 'none';
						}
						var fontInput   = document.getElementById( 'rapm_text_font' );
						var fontFamilyMap = <?php echo wp_json_encode( RAPM_Elementor::font_family_map() ); ?>;

						function updateCopy() {
							setText( document.getElementById( 'rapm-preview-headline' ), headlineInput.value );
							setText( document.getElementById( 'rapm-preview-subhead' ), subheadInput.value );
							setText( document.getElementById( 'rapm-preview-cta' ), ctaInput.value );
							previewCopy.setAttribute( 'data-align', alignInput.value );
							previewCopy.setAttribute( 'data-style', styleInput.value );
							previewCopy.style.color = colorInput.value;
							previewCopy.style.fontFamily = ( fontInput && fontFamilyMap[ fontInput.value ] ) ? fontFamilyMap[ fontInput.value ] : '';
						}
						[ headlineInput, subheadInput, ctaInput ].forEach( function ( el ) {
							el.addEventListener( 'input', updateCopy );
						} );
						[ alignInput, styleInput ].forEach( function ( el ) {
							el.addEventListener( 'change', updateCopy );
						} );
						if ( fontInput ) {
							fontInput.addEventListener( 'change', updateCopy );
						}
						colorInput.addEventListener( 'input', updateCopy );
						updateCopy();

						fileInput.addEventListener( 'change', function () {
							if ( ! this.files || ! this.files[0] ) { return; }
							previewImg.src = URL.createObjectURL( this.files[0] );
							previewImg.style.display = 'block';
							previewEmpty.style.display = 'none';
						} );
					} )();
				</script>
				<?php endif; // $has_images ?>

				<h2><?php esc_html_e( 'Where It Goes When Clicked', 'rapm' ); ?></h2>
				<?php $curated = RAPM_Destination::decode_curated_value( 'curated' === $dest_type ? $dest_value : '' ); ?>
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
					<tr class="rapm-dest-row" data-for="url">
						<th><label for="rapm_dest_url"><?php esc_html_e( 'Web address', 'rapm' ); ?></label></th>
						<td><input type="url" id="rapm_dest_url" class="regular-text rapm-dest-input" data-dest-type="url" value="<?php echo 'url' === $dest_type ? esc_attr( $dest_value ) : ''; ?>" placeholder="https://…" />
							<p class="description"><?php esc_html_e( 'Paste the full web address, starting with https://', 'rapm' ); ?></p>
						</td>
					</tr>
					<tr class="rapm-dest-row" data-for="post,wc_product,wc_category,wc_brand">
						<th><label for="rapm_dest_picker" id="rapm-dest-picker-label"><?php esc_html_e( 'Start typing to search', 'rapm' ); ?></label></th>
						<td>
							<input type="text" id="rapm_dest_picker" class="regular-text" autocomplete="off" placeholder="<?php esc_attr_e( 'Start typing a name…', 'rapm' ); ?>" />
							<div id="rapm-dest-picker-results" class="rapm-dest-picker-results"></div>
							<p class="description" id="rapm-dest-picker-current"></p>
						</td>
					</tr>
					<tr class="rapm-dest-row" data-for="search">
						<th><label for="rapm_dest_search_words"><?php esc_html_e( 'Search words', 'rapm' ); ?></label></th>
						<td><input type="text" id="rapm_dest_search_words" class="regular-text rapm-dest-input" data-dest-type="search" value="<?php echo 'search' === $dest_type ? esc_attr( $dest_value ) : ''; ?>" placeholder="<?php esc_attr_e( 'e.g. leather sectional', 'rapm' ); ?>" />
							<p class="description"><?php esc_html_e( 'Whatever words a visitor would type into the site\'s search box.', 'rapm' ); ?></p>
						</td>
					</tr>
					<tr class="rapm-dest-row" data-for="curated">
						<th><label for="rapm_curated_picker"><?php esc_html_e( 'Specific products', 'rapm' ); ?></label></th>
						<td>
							<input type="text" id="rapm_curated_picker" class="regular-text" autocomplete="off" placeholder="<?php esc_attr_e( 'Search by product name or SKU…', 'rapm' ); ?>" />
							<div id="rapm-curated-picker-results" class="rapm-dest-picker-results"></div>
							<p class="description"><?php esc_html_e( 'Click a result to add it. Add as many as you like — they\'ll show first, in the order you add them.', 'rapm' ); ?></p>
							<ul id="rapm-curated-selected-list" style="list-style:none;margin:10px 0 0;padding:0;"></ul>
							<textarea id="rapm_curated_skus" name="rapm_curated_skus" rows="3" class="large-text" style="display:none;"><?php echo esc_textarea( implode( "\n", $curated['skus'] ) ); ?></textarea>

							<p class="description" style="margin-top:14px;"><?php esc_html_e( 'Have a lot of SKUs? Upload a spreadsheet instead of searching one at a time — save it as a .csv file with a column titled "SKU" containing the product SKUs.', 'rapm' ); ?></p>
							<input type="file" id="rapm_curated_csv" accept=".csv" />
							<p class="description" id="rapm-curated-csv-status"></p>
							<p style="margin-top:16px;">
								<label for="rapm_curated_fallback_type"><strong><?php esc_html_e( 'Then fill in the rest of the page with...', 'rapm' ); ?></strong></label><br />
								<select id="rapm_curated_fallback_type" name="rapm_curated_fallback_type">
									<option value="none" <?php selected( $curated['fallback_type'], 'none' ); ?>><?php esc_html_e( 'Nothing else — just the SKUs above', 'rapm' ); ?></option>
									<option value="search" <?php selected( $curated['fallback_type'], 'search' ); ?>><?php esc_html_e( 'Search results for some words', 'rapm' ); ?></option>
									<option value="category" <?php selected( $curated['fallback_type'], 'category' ); ?>><?php esc_html_e( 'A product category', 'rapm' ); ?></option>
									<option value="brand" <?php selected( $curated['fallback_type'], 'brand' ); ?>><?php esc_html_e( 'A brand', 'rapm' ); ?></option>
								</select>
							</p>
							<p>
								<input type="text" id="rapm_curated_fallback_value" name="rapm_curated_fallback_value" class="regular-text" value="<?php echo esc_attr( $curated['fallback_value'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Living Room, or Ashley Furniture, or leather sofa — type it exactly as it appears on the site', 'rapm' ); ?>" />
							</p>
						</td>
					</tr>
				</table>
				<input type="hidden" id="rapm_dest_value" name="rapm_dest_value" value="<?php echo in_array( $dest_type, array( 'post', 'wc_product', 'wc_category', 'wc_brand' ), true ) ? esc_attr( $dest_value ) : ''; ?>" />
				<script>
					( function () {
						var destType     = document.getElementById( 'rapm_dest_type' );
						var rows         = document.querySelectorAll( '.rapm-dest-row' );
						var hiddenValue  = document.getElementById( 'rapm_dest_value' );
						var picker       = document.getElementById( 'rapm_dest_picker' );
						var pickerLabel  = document.getElementById( 'rapm-dest-picker-label' );
						var resultsBox   = document.getElementById( 'rapm-dest-picker-results' );
						var currentLabel = document.getElementById( 'rapm-dest-picker-current' );
						var ajaxUrl      = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
						var searchNonce  = <?php echo wp_json_encode( wp_create_nonce( 'rapm_search_destination' ) ); ?>;
						var pickerTypes  = [ 'post', 'wc_product', 'wc_category', 'wc_brand' ];
						var debounceTimer;
						var PICKER_TEXT = {
							post:        { label: <?php echo wp_json_encode( __( 'Start typing a page or post name', 'rapm' ) ); ?>, placeholder: <?php echo wp_json_encode( __( 'Start typing a name…', 'rapm' ) ); ?> },
							wc_product:  { label: <?php echo wp_json_encode( __( 'Search by product name or SKU', 'rapm' ) ); ?>, placeholder: <?php echo wp_json_encode( __( 'Search by product name or SKU…', 'rapm' ) ); ?> },
							wc_category: { label: <?php echo wp_json_encode( __( 'Start typing a category name', 'rapm' ) ); ?>, placeholder: <?php echo wp_json_encode( __( 'Start typing a name…', 'rapm' ) ); ?> },
							wc_brand:    { label: <?php echo wp_json_encode( __( 'Start typing a brand name', 'rapm' ) ); ?>, placeholder: <?php echo wp_json_encode( __( 'Start typing a name…', 'rapm' ) ); ?> }
						};

						function syncRows() {
							var current = destType.value;
							rows.forEach( function ( row ) {
								var allowed = row.getAttribute( 'data-for' ).split( ',' );
								row.style.display = allowed.indexOf( current ) === -1 ? 'none' : '';
							} );
							var text = PICKER_TEXT[ current ];
							if ( text ) {
								pickerLabel.textContent = text.label;
								picker.setAttribute( 'placeholder', text.placeholder );
							}
						}

						// The plain url/search-words fields and the picker all
						// share one hidden field, since only one is ever visible
						// (and thus meaningful) for a given destination type.
						document.querySelectorAll( '.rapm-dest-input' ).forEach( function ( el ) {
							el.addEventListener( 'input', function () {
								if ( this.getAttribute( 'data-dest-type' ) === destType.value ) {
									hiddenValue.value = this.value;
								}
							} );
						} );
						destType.addEventListener( 'change', function () {
							syncRows();
							var matching = document.querySelector( '.rapm-dest-input[data-dest-type="' + destType.value + '"]' );
							hiddenValue.value = matching ? matching.value : '';
							currentLabel.textContent = '';
							picker.value = '';
							resultsBox.innerHTML = '';
						} );
						syncRows();

						function renderResults( results ) {
							resultsBox.innerHTML = '';
							if ( ! results.length ) {
								return;
							}
							var list = document.createElement( 'ul' );
							list.className = 'rapm-dest-picker-list';
							results.forEach( function ( item ) {
								var li = document.createElement( 'li' );
								li.textContent = item.label;
								li.addEventListener( 'click', function () {
									hiddenValue.value = item.id;
									picker.value = item.label;
									currentLabel.textContent = <?php echo wp_json_encode( __( 'Selected: ', 'rapm' ) ); ?> + item.label;
									resultsBox.innerHTML = '';
								} );
								list.appendChild( li );
							} );
							resultsBox.appendChild( list );
						}

						picker.addEventListener( 'input', function () {
							var term = this.value.trim();
							clearTimeout( debounceTimer );
							if ( term.length < 2 ) {
								resultsBox.innerHTML = '';
								return;
							}
							debounceTimer = setTimeout( function () {
								var url = ajaxUrl + '?action=rapm_search_destination&nonce=' + encodeURIComponent( searchNonce )
									+ '&type=' + encodeURIComponent( destType.value ) + '&term=' + encodeURIComponent( term );
								fetch( url ).then( function ( r ) { return r.json(); } ).then( function ( res ) {
									if ( res.success ) {
										renderResults( res.data.results );
									}
								} );
							}, 300 );
						} );

						// On load, if editing an asset that already points at a
						// picker-based destination, resolve its ID back to a
						// readable name — only the ID is stored.
						if ( pickerTypes.indexOf( destType.value ) !== -1 && hiddenValue.value ) {
							var resolveUrl = ajaxUrl + '?action=rapm_search_destination&nonce=' + encodeURIComponent( searchNonce )
								+ '&type=' + encodeURIComponent( destType.value ) + '&resolve_id=' + encodeURIComponent( hiddenValue.value );
							fetch( resolveUrl ).then( function ( r ) { return r.json(); } ).then( function ( res ) {
								if ( res.success && res.data.label ) {
									currentLabel.textContent = <?php echo wp_json_encode( __( 'Currently: ', 'rapm' ) ); ?> + res.data.label;
								}
							} );
						}
					} )();
				</script>

				<style>
					.rapm-curated-chip { display: flex; align-items: center; justify-content: space-between; gap: 10px; background: #f0f0f1; border: 1px solid #dcdcde; border-radius: 4px; padding: 6px 10px; margin-bottom: 6px; font-size: 13px; max-width: 420px; }
					.rapm-curated-chip.is-unresolved { border-color: #d63638; }
					.rapm-curated-chip button { background: none; border: none; color: #b32d2e; cursor: pointer; font-size: 15px; line-height: 1; padding: 0 2px; }
				</style>
				<script>
					( function () {
						var picker      = document.getElementById( 'rapm_curated_picker' );
						var resultsBox  = document.getElementById( 'rapm-curated-picker-results' );
						var listEl      = document.getElementById( 'rapm-curated-selected-list' );
						var skusField   = document.getElementById( 'rapm_curated_skus' );
						var ajaxUrl     = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
						var searchNonce = <?php echo wp_json_encode( wp_create_nonce( 'rapm_search_destination' ) ); ?>;
						var selected    = []; // [{ sku, label, found }]
						var debounceTimer;

						function renderChips() {
							listEl.innerHTML = '';
							selected.forEach( function ( item, index ) {
								var li = document.createElement( 'li' );
								li.className = 'rapm-curated-chip' + ( item.found === false ? ' is-unresolved' : '' );
								var span = document.createElement( 'span' );
								span.textContent = item.label;
								var btn = document.createElement( 'button' );
								btn.type = 'button';
								btn.setAttribute( 'aria-label', <?php echo wp_json_encode( __( 'Remove', 'rapm' ) ); ?> );
								btn.textContent = '\u00d7';
								btn.addEventListener( 'click', function () {
									selected.splice( index, 1 );
									renderChips();
								} );
								li.appendChild( span );
								li.appendChild( btn );
								listEl.appendChild( li );
							} );
							skusField.value = selected.map( function ( item ) { return item.sku; } ).join( '\n' );
						}

						function addProduct( sku, label, found ) {
							if ( ! sku || selected.some( function ( item ) { return item.sku === sku; } ) ) {
								return;
							}
							selected.push( { sku: sku, label: label, found: found } );
							renderChips();
						}

						picker.addEventListener( 'input', function () {
							var term = this.value.trim();
							clearTimeout( debounceTimer );
							if ( term.length < 2 ) {
								resultsBox.innerHTML = '';
								return;
							}
							debounceTimer = setTimeout( function () {
								var url = ajaxUrl + '?action=rapm_search_destination&nonce=' + encodeURIComponent( searchNonce )
									+ '&type=wc_product&term=' + encodeURIComponent( term );
								fetch( url ).then( function ( r ) { return r.json(); } ).then( function ( res ) {
									resultsBox.innerHTML = '';
									if ( ! res.success || ! res.data.results.length ) {
										return;
									}
									var list = document.createElement( 'ul' );
									list.className = 'rapm-dest-picker-list';
									res.data.results.forEach( function ( item ) {
										if ( ! item.sku ) {
											return;
										}
										var li = document.createElement( 'li' );
										li.textContent = item.label;
										li.addEventListener( 'click', function () {
											addProduct( item.sku, item.label, true );
											picker.value = '';
											resultsBox.innerHTML = '';
										} );
										list.appendChild( li );
									} );
									resultsBox.appendChild( list );
								} );
							}, 300 );
						} );

						var existingSkus = skusField.value.split( '\n' ).map( function ( s ) { return s.trim(); } ).filter( Boolean );
						if ( existingSkus.length ) {
							var resolveUrl = ajaxUrl + '?action=rapm_search_destination&nonce=' + encodeURIComponent( searchNonce )
								+ '&type=wc_product&resolve_skus=' + encodeURIComponent( existingSkus.join( ',' ) );
							fetch( resolveUrl ).then( function ( r ) { return r.json(); } ).then( function ( res ) {
								if ( res.success ) {
									res.data.results.forEach( function ( item ) {
										addProduct( item.sku, item.found ? item.label : ( <?php echo wp_json_encode( __( 'SKU: ', 'rapm' ) ); ?> + item.label ), item.found );
									} );
								}
							} );
						}

						// Optional bulk path: a client's own spreadsheet of SKUs,
						// with a "SKU" column, instead of searching one at a time.
						var csvInput  = document.getElementById( 'rapm_curated_csv' );
						var csvStatus = document.getElementById( 'rapm-curated-csv-status' );
						csvInput.addEventListener( 'change', function () {
							if ( ! this.files || ! this.files[0] ) { return; }
							var formData = new FormData();
							formData.append( 'action', 'rapm_import_skus' );
							formData.append( 'nonce', searchNonce );
							formData.append( 'file', this.files[0] );
							csvStatus.textContent = <?php echo wp_json_encode( __( 'Reading file…', 'rapm' ) ); ?>;
							fetch( ajaxUrl, { method: 'POST', body: formData } ).then( function ( r ) { return r.json(); } ).then( function ( res ) {
								csvInput.value = '';
								if ( ! res.success ) {
									csvStatus.textContent = res.data && res.data.message ? res.data.message : <?php echo wp_json_encode( __( 'Something went wrong reading that file.', 'rapm' ) ); ?>;
									return;
								}
								res.data.found.forEach( function ( item ) { addProduct( item.sku, item.label, true ); } );

								var parts = [];
								if ( res.data.found.length ) {
									parts.push( res.data.found.length + <?php echo wp_json_encode( ' ' . __( 'added.', 'rapm' ) ); ?> );
								}
								if ( res.data.not_found.length ) {
									parts.push( res.data.not_found.length + <?php echo wp_json_encode( ' ' . __( "weren't found on this site.", 'rapm' ) ); ?> );
								}
								if ( res.data.truncated ) {
									parts.push( <?php echo wp_json_encode( __( 'Only the first 2,000 rows were read — split larger files into smaller ones.', 'rapm' ) ); ?> );
								}
								csvStatus.textContent = parts.join( ' ' );

								if ( res.data.not_found.length ) {
									var link = document.createElement( 'a' );
									link.href = '#';
									link.textContent = <?php echo wp_json_encode( __( 'Download the list of SKUs that weren\'t found', 'rapm' ) ); ?>;
									link.style.marginLeft = '6px';
									link.addEventListener( 'click', function ( e ) {
										e.preventDefault();
										var csvContent = 'SKU\n' + res.data.not_found.join( '\n' );
										var blob = new Blob( [ csvContent ], { type: 'text/csv' } );
										var url = URL.createObjectURL( blob );
										var a = document.createElement( 'a' );
										a.href = url;
										a.download = 'skus-not-found.csv';
										document.body.appendChild( a );
										a.click();
										document.body.removeChild( a );
										URL.revokeObjectURL( url );
									} );
									csvStatus.appendChild( link );
								}
							} ).catch( function () {
								csvStatus.textContent = <?php echo wp_json_encode( __( 'Something went wrong reading that file.', 'rapm' ) ); ?>;
							} );
						} );
					} )();
				</script>

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
		$kind       = RAPM_Slots::kind( $kind_key );
		$has_images = (bool) $kind['desktop'];

		// Validate + convert images BEFORE touching the post itself, so a
		// bad upload never leaves a half-saved asset behind.
		$slots          = RAPM_Slots::all();
		$new_desktop_id = null;
		$new_mobile_id  = null;
		$desktop_source = 'upload';
		$mobile_source  = 'upload';
		$desktop_url    = '';
		$mobile_url     = '';

		if ( $has_images ) {
			$desktop_source = isset( $_POST['rapm_image_desktop_source'] ) && 'link' === $_POST['rapm_image_desktop_source'] ? 'link' : 'upload';
			$mobile_source  = isset( $_POST['rapm_image_mobile_source'] ) && 'link' === $_POST['rapm_image_mobile_source'] ? 'link' : 'upload';

			if ( 'link' === $desktop_source ) {
				$desktop_url = isset( $_POST['rapm_image_desktop_url'] ) ? esc_url_raw( wp_unslash( $_POST['rapm_image_desktop_url'] ) ) : '';
				if ( $desktop_url ) {
					$result = RAPM_Link_Source::fetch_and_validate( $desktop_url, $slots[ $kind['desktop'] ], $asset_id ?: 0 );
					if ( is_wp_error( $result ) ) {
						self::fail( $back, $result->get_error_message() );
					}
					$new_desktop_id = $result;
				}
			} elseif ( ! empty( $_FILES['rapm_image_desktop']['tmp_name'] ) ) {
				$result = self::process_upload( $_FILES['rapm_image_desktop'], $slots[ $kind['desktop'] ], $asset_id ?: 0 );
				if ( is_wp_error( $result ) ) {
					self::fail( $back, $result->get_error_message() );
				}
				$new_desktop_id = $result;
			}

			if ( 'link' === $mobile_source ) {
				$mobile_url = isset( $_POST['rapm_image_mobile_url'] ) ? esc_url_raw( wp_unslash( $_POST['rapm_image_mobile_url'] ) ) : '';
				if ( $mobile_url ) {
					$result = RAPM_Link_Source::fetch_and_validate( $mobile_url, $slots[ $kind['mobile'] ], $asset_id ?: 0 );
					if ( is_wp_error( $result ) ) {
						self::fail( $back, $result->get_error_message() );
					}
					$new_mobile_id = $result;
				}
			} elseif ( ! empty( $_FILES['rapm_image_mobile']['tmp_name'] ) ) {
				$result = self::process_upload( $_FILES['rapm_image_mobile'], $slots[ $kind['mobile'] ], $asset_id ?: 0 );
				if ( is_wp_error( $result ) ) {
					self::fail( $back, $result->get_error_message() );
				}
				$new_mobile_id = $result;
			}

			// A desktop image is the one truly required piece — without it
			// the carousel has nothing to show and silently skips this
			// asset entirely (see RAPM_Hero_Carousel::render_slide()).
			// Catch that here with a clear message rather than letting
			// someone publish an asset that will never actually appear
			// anywhere, with no error to explain why.
			$has_desktop_image = $new_desktop_id || ( $is_edit && get_post_meta( $asset_id, '_rapm_image_desktop_id', true ) );
			if ( ! $has_desktop_image ) {
				$desktop_required_msg = 'link' === $desktop_source
					? __( 'Please paste a link to a desktop picture — it\'s required for this asset to actually display anywhere.', 'rapm' )
					: __( 'Please upload a desktop image — it\'s required for this asset to actually display anywhere.', 'rapm' );
				self::fail( $back, $desktop_required_msg );
			}
		} else {
			// Text-only kind — no current kind is image-less, but a site
			// could register one via the rapm_slots/kinds filters, so this
			// stays as a safety net: text is what's required instead.
			$headline_check = isset( $_POST['rapm_headline'] ) ? trim( wp_unslash( $_POST['rapm_headline'] ) ) : '';
			if ( '' === $headline_check ) {
				self::fail( $back, __( 'Please enter the text — it\'s required for this promotion to actually display anywhere.', 'rapm' ) );
			}
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

		update_post_meta( $asset_id, '_rapm_image_desktop_source', $desktop_source );
		if ( 'link' === $desktop_source && null !== $new_desktop_id ) {
			update_post_meta( $asset_id, '_rapm_image_desktop_url', $desktop_url );
			update_post_meta( $asset_id, '_rapm_image_desktop_synced_at', current_time( 'mysql' ) );
			delete_post_meta( $asset_id, '_rapm_image_desktop_sync_error' );
		} elseif ( 'upload' === $desktop_source ) {
			delete_post_meta( $asset_id, '_rapm_image_desktop_url' );
			delete_post_meta( $asset_id, '_rapm_image_desktop_synced_at' );
			delete_post_meta( $asset_id, '_rapm_image_desktop_sync_error' );
		}

		update_post_meta( $asset_id, '_rapm_image_mobile_source', $mobile_source );
		if ( 'link' === $mobile_source && null !== $new_mobile_id ) {
			update_post_meta( $asset_id, '_rapm_image_mobile_url', $mobile_url );
			update_post_meta( $asset_id, '_rapm_image_mobile_synced_at', current_time( 'mysql' ) );
			delete_post_meta( $asset_id, '_rapm_image_mobile_sync_error' );
		} elseif ( 'upload' === $mobile_source ) {
			delete_post_meta( $asset_id, '_rapm_image_mobile_url' );
			delete_post_meta( $asset_id, '_rapm_image_mobile_synced_at' );
			delete_post_meta( $asset_id, '_rapm_image_mobile_sync_error' );
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
			'rapm_text_align'   => array( __CLASS__, 'sanitize_text_align' ),
			'rapm_text_color'   => array( __CLASS__, 'sanitize_text_color' ),
			'rapm_text_style'   => array( __CLASS__, 'sanitize_text_style' ),
			'rapm_text_font'    => array( __CLASS__, 'sanitize_text_font' ),
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
		$dest_type = isset( $_POST['rapm_dest_type'] ) ? sanitize_key( wp_unslash( $_POST['rapm_dest_type'] ) ) : 'url';
		update_post_meta( $asset_id, '_rapm_destination_type', $dest_type );

		switch ( $dest_type ) {
			case 'url':
				$dest_value = isset( $_POST['rapm_dest_value'] ) ? esc_url_raw( wp_unslash( $_POST['rapm_dest_value'] ) ) : '';
				break;
			case 'search':
				$dest_value = isset( $_POST['rapm_dest_value'] ) ? sanitize_text_field( wp_unslash( $_POST['rapm_dest_value'] ) ) : '';
				break;
			case 'post':
			case 'wc_product':
			case 'wc_category':
			case 'wc_brand':
				$dest_value = isset( $_POST['rapm_dest_value'] ) ? absint( $_POST['rapm_dest_value'] ) : 0;
				break;
			case 'curated':
				$dest_value = RAPM_Destination::build_curated_value(
					isset( $_POST['rapm_curated_skus'] ) ? wp_unslash( $_POST['rapm_curated_skus'] ) : '',
					isset( $_POST['rapm_curated_fallback_type'] ) ? sanitize_key( wp_unslash( $_POST['rapm_curated_fallback_type'] ) ) : 'none',
					isset( $_POST['rapm_curated_fallback_value'] ) ? wp_unslash( $_POST['rapm_curated_fallback_value'] ) : ''
				);
				break;
			default:
				$dest_value = '';
		}
		update_post_meta( $asset_id, '_rapm_destination_value', $dest_value );
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
		return self::validate_convert_sideload( $file['tmp_name'], $file['name'], $slot, $parent_id );
	}

	/**
	 * The core validation/conversion/sideload pipeline — starting from any
	 * image file already sitting on disk, regardless of how it got there.
	 * process_upload() (a posted file) and RAPM_Link_Source (a downloaded
	 * URL) both funnel through this one method, so a link-sourced image is
	 * never held to a looser standard than a directly uploaded one.
	 *
	 * The actual file type is read from the file's own bytes
	 * (getimagesize()'s mime), not trusted from $original_filename's
	 * extension — a downloaded URL often has no useful filename at all
	 * (e.g. a Google Drive export link), so extension-sniffing would
	 * reject perfectly good images.
	 */
	public static function validate_convert_sideload( $tmp_path, $original_filename, $slot, $parent_id ) {
		$dims = getimagesize( $tmp_path );
		if ( ! $dims ) {
			return new WP_Error( 'rapm_unreadable', __( 'Could not read that image file.', 'rapm' ) );
		}
		list( $width, $height ) = $dims;
		$mime = isset( $dims['mime'] ) ? $dims['mime'] : '';

		if ( ! in_array( $mime, array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif' ), true ) ) {
			return new WP_Error( 'rapm_not_image', __( 'That file doesn\'t look like an image.', 'rapm' ) );
		}

		$was_resized = false;

		if ( ! RAPM_Slots::dimensions_match( $slot, $width, $height ) ) {
			if ( ! RAPM_Slots::aspect_ratio_matches( $slot, $width, $height ) ) {
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

			// Same shape, just a different resolution (e.g. a 2x export) —
			// a plain scale is a safe, lossless fit with nothing to crop
			// or distort, so this is auto-corrected rather than rejected.
			if ( ! RAPM_Webp_Converter::is_available() ) {
				return new WP_Error( 'rapm_no_webp_support', __( 'This server can\'t auto-resize or auto-convert images (no Imagick or GD support found). Please crop or re-export this to the exact size and try again.', 'rapm' ) );
			}
			$resized = RAPM_Webp_Converter::resize_to( $tmp_path, $slot['width'], $slot['height'] );
			if ( is_wp_error( $resized ) ) {
				return $resized;
			}
			$tmp_path    = $resized;
			$was_resized = true;
		}

		$already_webp_and_small = ! $was_resized && 'image/webp' === $mime && ( filesize( $tmp_path ) / 1024 ) <= $slot['max_kb'];
		$base_name              = sanitize_file_name( pathinfo( $original_filename, PATHINFO_FILENAME ) ) ?: 'rapm-image'; // phpcs:ignore

		if ( $already_webp_and_small ) {
			$upload_path = $tmp_path;
			$upload_name = wp_unique_filename( wp_upload_dir()['path'], $base_name . '.webp' );
		} else {
			if ( ! RAPM_Webp_Converter::is_available() ) {
				return new WP_Error( 'rapm_no_webp_support', __( 'This server can\'t auto-convert images to WebP (no Imagick or GD WebP support found). Please upload a .webp file directly, or ask your host to enable WebP support.', 'rapm' ) );
			}
			$converted = RAPM_Webp_Converter::convert( $tmp_path, $slot['max_kb'] );
			if ( $was_resized && file_exists( $tmp_path ) ) {
				wp_delete_file( $tmp_path ); // The resize step's own intermediate file — convert() has already read it into a fresh output file above.
			}
			if ( is_wp_error( $converted ) ) {
				return $converted;
			}
			$upload_path = $converted['path'];
			$upload_name = wp_unique_filename( wp_upload_dir()['path'], $base_name . '.webp' );
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

	public static function sanitize_text_align( $value ) {
		return in_array( $value, array( 'left', 'center', 'right' ), true ) ? $value : 'left';
	}

	public static function sanitize_text_style( $value ) {
		return in_array( $value, array( 'bold', 'elegant', 'minimal' ), true ) ? $value : 'bold';
	}

	public static function sanitize_text_color( $value ) {
		$color = sanitize_hex_color( $value );
		return $color ? $color : '#ffffff';
	}

	/**
	 * Whitelisted against whatever Elementor global fonts actually exist
	 * on this site right now, not just any string the form happened to
	 * post — a stale/tampered value can't silently reference a font id
	 * that no longer exists.
	 */
	public static function sanitize_text_font( $value ) {
		$available = RAPM_Elementor::global_fonts();
		return isset( $available[ $value ] ) ? $value : '';
	}

	private static function fail( $back_url, $message ) {
		wp_safe_redirect( add_query_arg( 'rapm_error', rawurlencode( $message ), $back_url ) );
		exit;
	}
}
