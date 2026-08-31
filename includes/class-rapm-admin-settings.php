<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAPM_Admin_Settings {

	const OPTION = 'rapm_settings';

	public static function defaults() {
		return array(
			'slot_overrides'  => array(),
			'default_autoplay'       => 0,
			'default_autoplay_speed' => 7000,
			'default_nav_style'      => 'both',
		);
	}

	public static function get( $key = null ) {
		$opts = wp_parse_args( get_option( self::OPTION, array() ), self::defaults() );
		if ( $key ) {
			return isset( $opts[ $key ] ) ? $opts[ $key ] : null;
		}
		return $opts;
	}

	public static function add_menu() {
		add_submenu_page(
			'edit.php?post_type=rapm_asset',
			__( 'Promo Manager Settings', 'rapm' ),
			__( 'Settings', 'rapm' ),
			'manage_options',
			'rapm-settings',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function register_settings() {
		register_setting( self::OPTION, self::OPTION, array( __CLASS__, 'sanitize' ) );
	}

	public static function sanitize( $input ) {
		$out = self::defaults();

		$out['default_autoplay']       = isset( $input['default_autoplay'] ) ? 1 : 0;
		$out['default_autoplay_speed'] = isset( $input['default_autoplay_speed'] ) ? max( 2000, absint( $input['default_autoplay_speed'] ) ) : $out['default_autoplay_speed'];
		$out['default_nav_style']      = isset( $input['default_nav_style'] ) && in_array( $input['default_nav_style'], array( 'both', 'arrows', 'dots', 'none' ), true )
			? $input['default_nav_style']
			: $out['default_nav_style'];

		$overrides = array();
		if ( isset( $input['slot_overrides'] ) && is_array( $input['slot_overrides'] ) ) {
			foreach ( $input['slot_overrides'] as $slot_key => $fields ) {
				$slot_key = sanitize_key( $slot_key );
				foreach ( array( 'width', 'height', 'max_kb' ) as $field ) {
					if ( isset( $fields[ $field ] ) && '' !== $fields[ $field ] ) {
						$overrides[ $slot_key ][ $field ] = absint( $fields[ $field ] );
					}
				}
				if ( isset( $fields['aspect_ratio_tolerance'] ) && '' !== $fields['aspect_ratio_tolerance'] ) {
					$overrides[ $slot_key ]['aspect_ratio_tolerance'] = max( 0, min( 0.5, (float) $fields['aspect_ratio_tolerance'] ) );
				}
			}
		}
		$out['slot_overrides'] = $overrides;

		return $out;
	}

	public static function render_page() {
		$opts  = self::get();
		$slots = RAPM_Slots::defaults(); // labels/format from code, not merged overrides, for the form's own field labels.
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Promo Manager Settings', 'rapm' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( self::OPTION ); ?>

				<h2><?php esc_html_e( 'Slot Overrides', 'rapm' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Every slot has a sensible default (e.g. the desktop hero is 1920x600, WebP, under 300KB). Only fill in a field here if this site genuinely needs a different size than that default — leave blank to keep it.', 'rapm' ); ?></p>
				<table class="widefat striped" style="max-width:900px;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Slot', 'rapm' ); ?></th>
							<th><?php esc_html_e( 'Width (px)', 'rapm' ); ?></th>
							<th><?php esc_html_e( 'Height (px)', 'rapm' ); ?></th>
							<th><?php esc_html_e( 'Max Size (KB)', 'rapm' ); ?></th>
							<th><?php esc_html_e( 'Tolerance (0–0.5)', 'rapm' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $slots as $key => $slot ) : $ov = isset( $opts['slot_overrides'][ $key ] ) ? $opts['slot_overrides'][ $key ] : array(); ?>
							<tr>
								<td><strong><?php echo esc_html( $slot['label'] ); ?></strong><br /><span class="description"><?php echo esc_html( sprintf( '%1$dx%2$d, %3$s, under %4$dKB', $slot['width'], $slot['height'], strtoupper( $slot['format'] ), $slot['max_kb'] ) ); ?></span></td>
								<td><input type="number" min="1" name="<?php echo esc_attr( self::OPTION ); ?>[slot_overrides][<?php echo esc_attr( $key ); ?>][width]" value="<?php echo esc_attr( $ov['width'] ?? '' ); ?>" placeholder="<?php echo esc_attr( $slot['width'] ); ?>" style="width:90px;" /></td>
								<td><input type="number" min="1" name="<?php echo esc_attr( self::OPTION ); ?>[slot_overrides][<?php echo esc_attr( $key ); ?>][height]" value="<?php echo esc_attr( $ov['height'] ?? '' ); ?>" placeholder="<?php echo esc_attr( $slot['height'] ); ?>" style="width:90px;" /></td>
								<td><input type="number" min="1" name="<?php echo esc_attr( self::OPTION ); ?>[slot_overrides][<?php echo esc_attr( $key ); ?>][max_kb]" value="<?php echo esc_attr( $ov['max_kb'] ?? '' ); ?>" placeholder="<?php echo esc_attr( $slot['max_kb'] ); ?>" style="width:90px;" /></td>
								<td><input type="number" min="0" max="0.5" step="0.01" name="<?php echo esc_attr( self::OPTION ); ?>[slot_overrides][<?php echo esc_attr( $key ); ?>][aspect_ratio_tolerance]" value="<?php echo esc_attr( $ov['aspect_ratio_tolerance'] ?? '' ); ?>" placeholder="<?php echo esc_attr( $slot['aspect_ratio_tolerance'] ); ?>" style="width:90px;" /></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<h2><?php esc_html_e( 'Carousel Defaults', 'rapm' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Applied to both [rapm_hero] and [rapm_fold_banner] unless a specific shortcode overrides them with its own attributes. Auto-rotation is off by default — current UX research finds carousels perform best when they don\'t auto-advance past what a visitor is actually looking at.', 'rapm' ); ?></p>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Autoplay', 'rapm' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[default_autoplay]" value="1" <?php checked( $opts['default_autoplay'], 1 ); ?> /> <?php esc_html_e( 'Auto-advance slides', 'rapm' ); ?></label></td>
					</tr>
					<tr>
						<th><label for="rapm_speed"><?php esc_html_e( 'Autoplay Speed (ms)', 'rapm' ); ?></label></th>
						<td><input type="number" min="2000" step="500" id="rapm_speed" name="<?php echo esc_attr( self::OPTION ); ?>[default_autoplay_speed]" value="<?php echo esc_attr( $opts['default_autoplay_speed'] ); ?>" />
							<p class="description"><?php esc_html_e( 'At least 6-8 seconds (6000-8000ms) is recommended if autoplay is on, so a slide is actually readable before it changes.', 'rapm' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="rapm_nav"><?php esc_html_e( 'Navigation Style', 'rapm' ); ?></label></th>
						<td>
							<select id="rapm_nav" name="<?php echo esc_attr( self::OPTION ); ?>[default_nav_style]">
								<option value="both" <?php selected( $opts['default_nav_style'], 'both' ); ?>><?php esc_html_e( 'Arrows & Dots', 'rapm' ); ?></option>
								<option value="arrows" <?php selected( $opts['default_nav_style'], 'arrows' ); ?>><?php esc_html_e( 'Arrows Only', 'rapm' ); ?></option>
								<option value="dots" <?php selected( $opts['default_nav_style'], 'dots' ); ?>><?php esc_html_e( 'Dots Only', 'rapm' ); ?></option>
								<option value="none" <?php selected( $opts['default_nav_style'], 'none' ); ?>><?php esc_html_e( 'None', 'rapm' ); ?></option>
							</select>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<h2><?php esc_html_e( 'Shortcodes', 'rapm' ); ?></h2>
			<table class="widefat striped" style="max-width:800px;">
				<tr><td><code>[rapm_hero]</code></td><td><?php esc_html_e( 'Full hero carousel for the "default" placement.', 'rapm' ); ?></td></tr>
				<tr><td><code>[rapm_fold_banner]</code></td><td><?php esc_html_e( 'The shorter fold-banner carousel for the "default" placement — a separate kind of asset from the hero, added the same way under Promo > Add New Asset with "Kind" set to Fold Banner.', 'rapm' ); ?></td></tr>
				<tr><td><code>[rapm_hero placement="category-living-room"]</code></td><td><?php esc_html_e( 'A separate carousel scoped to just that placement — set the same placement value when adding assets. Works the same way for [rapm_fold_banner].', 'rapm' ); ?></td></tr>
			</table>
		</div>
		<?php
	}
}
