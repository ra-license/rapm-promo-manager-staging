<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The registry of image "slots" this plugin enforces — each one a
 * placement (e.g. the desktop hero) with its own required dimensions,
 * format, and max file size. Registered via a filter so a specific client
 * site can add its own slots with zero core changes, matching how
 * CEC_Elementor registers its widget category. Numeric overrides (a client
 * needing a different size than the agency default) live in this plugin's
 * settings option, merged over the filter-registered defaults — the label
 * and format stay code-defined so a site can't accidentally break a
 * slot's contract by fat-fingering a settings field.
 */
class RAPM_Slots {

	public static function defaults() {
		return array(
			'hero_desktop' => array(
				'label'                  => __( 'Hero — Desktop', 'rapm' ),
				'width'                  => 1920,
				'height'                 => 600,
				'aspect_ratio_tolerance' => 0.02,
				'max_kb'                 => 300,
				'format'                 => 'webp',
			),
			'hero_mobile'  => array(
				'label'                  => __( 'Hero — Mobile', 'rapm' ),
				'width'                  => 1080,
				'height'                 => 1920,
				'aspect_ratio_tolerance' => 0.02,
				'max_kb'                 => 300,
				'format'                 => 'webp',
			),
		);
	}

	/**
	 * Every registered slot, with this site's numeric overrides (width,
	 * height, max_kb) merged in from Settings. label/format always come
	 * from code, never from the overrides, so a stored override can't
	 * silently rename a slot or change what format it requires.
	 */
	public static function all() {
		$slots     = apply_filters( 'rapm_slots', self::defaults() );
		$overrides = RAPM_Admin_Settings::get( 'slot_overrides' );
		if ( ! is_array( $overrides ) ) {
			$overrides = array();
		}

		foreach ( $slots as $key => $slot ) {
			if ( empty( $overrides[ $key ] ) || ! is_array( $overrides[ $key ] ) ) {
				continue;
			}
			foreach ( array( 'width', 'height', 'max_kb', 'aspect_ratio_tolerance' ) as $field ) {
				if ( isset( $overrides[ $key ][ $field ] ) && '' !== $overrides[ $key ][ $field ] ) {
					$slots[ $key ][ $field ] = $overrides[ $key ][ $field ];
				}
			}
		}

		return $slots;
	}

	public static function get( $key ) {
		$slots = self::all();
		return isset( $slots[ $key ] ) ? $slots[ $key ] : null;
	}

	/**
	 * Checks a pixel width/height against a slot's required dimensions,
	 * allowing its aspect_ratio_tolerance as a small margin rather than
	 * demanding an exact pixel match — client exports are rarely
	 * pixel-perfect, and demanding they be generates support tickets for
	 * no real benefit over "close enough to not visibly distort."
	 */
	public static function dimensions_match( $slot, $width, $height ) {
		if ( ! $slot ) {
			return false;
		}
		$tolerance = isset( $slot['aspect_ratio_tolerance'] ) ? (float) $slot['aspect_ratio_tolerance'] : 0;
		$w_diff    = abs( $width - $slot['width'] ) / $slot['width'];
		$h_diff    = abs( $height - $slot['height'] ) / $slot['height'];
		return $w_diff <= $tolerance && $h_diff <= $tolerance;
	}
}
