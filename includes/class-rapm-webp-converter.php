<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts an uploaded image to WebP and compresses it toward a target
 * file size, server-side — this is what lets a client upload whatever
 * JPG/PNG they have on hand instead of needing to know what WebP is or
 * how to make one. Imagick is tried first (more consistently available
 * and higher quality across shared hosting than GD's WebP support, which
 * many hosts compile without); GD is the fallback.
 *
 * Dimensions are only ever touched via resize_to() — a plain, lossless
 * scale, called by RAPM_Upload_Handler only when the source is already
 * the right shape (see RAPM_Slots::aspect_ratio_matches()). A wrong
 * *shape* still needs a human to fix (cropping or squashing it
 * automatically risks cutting off or warping whatever the photo is of),
 * so that's validated and rejected before this class is ever reached.
 */
class RAPM_Webp_Converter {

	public static function is_available() {
		return self::imagick_supports_webp() || function_exists( 'imagewebp' );
	}

	/**
	 * Scales $source_path to exactly $target_width x $target_height — no
	 * cropping, just a resize. Only ever called by
	 * RAPM_Upload_Handler::validate_convert_sideload() after confirming
	 * (via RAPM_Slots::aspect_ratio_matches()) the source is already
	 * close enough to the target's shape that a plain scale is a safe,
	 * lossless fit, not a distortion or a guess at what to crop out.
	 * Returns a new temp file path the caller must clean up, or a
	 * WP_Error if this host has neither Imagick nor GD available.
	 */
	public static function resize_to( $source_path, $target_width, $target_height ) {
		if ( extension_loaded( 'imagick' ) && class_exists( 'Imagick' ) ) {
			try {
				$image = new Imagick( $source_path );
				$image->resizeImage( $target_width, $target_height, Imagick::FILTER_LANCZOS, 1, false );
				$tmp = wp_tempnam( 'rapm-resized' );
				$image->writeImage( $tmp );
				$image->clear();
				$image->destroy();
				return $tmp;
			} catch ( Exception $e ) {
				return new WP_Error( 'rapm_resize_failed', $e->getMessage() );
			}
		}

		if ( function_exists( 'imagecreatetruecolor' ) ) {
			$info = getimagesize( $source_path );
			if ( ! $info ) {
				return new WP_Error( 'rapm_resize_failed', __( 'Could not read the image to resize it.', 'rapm' ) );
			}
			switch ( $info['mime'] ) {
				case 'image/jpeg':
					$src = imagecreatefromjpeg( $source_path );
					break;
				case 'image/png':
					$src = imagecreatefrompng( $source_path );
					break;
				case 'image/webp':
					$src = function_exists( 'imagecreatefromwebp' ) ? imagecreatefromwebp( $source_path ) : false;
					break;
				case 'image/gif':
					$src = imagecreatefromgif( $source_path );
					break;
				default:
					$src = false;
			}
			if ( ! $src ) {
				return new WP_Error( 'rapm_resize_failed', __( 'Unsupported image type to resize.', 'rapm' ) );
			}

			$dst = imagecreatetruecolor( $target_width, $target_height );
			imagecopyresampled( $dst, $src, 0, 0, 0, 0, $target_width, $target_height, imagesx( $src ), imagesy( $src ) );
			imagedestroy( $src );

			$tmp = wp_tempnam( 'rapm-resized' );
			imagepng( $dst, $tmp ); // Format doesn't matter here — convert() re-encodes to WebP right after this.
			imagedestroy( $dst );
			return $tmp;
		}

		return new WP_Error( 'rapm_resize_unavailable', __( 'This server can\'t automatically resize images (no Imagick or GD found).', 'rapm' ) );
	}

	private static function imagick_supports_webp() {
		if ( ! extension_loaded( 'imagick' ) || ! class_exists( 'Imagick' ) ) {
			return false;
		}
		return in_array( 'WEBP', Imagick::queryFormats( 'WEBP' ), true );
	}

	/**
	 * Converts $source_path to WebP. If $max_kb is given, steps quality
	 * down until the result fits (or quality hits a floor where further
	 * reduction would visibly degrade a sale banner more than it's worth).
	 * Returns array( 'path', 'width', 'height', 'size_kb' ) — $path is a
	 * new temp file the caller is responsible for cleaning up — or a
	 * WP_Error if no WebP-capable extension is available on this host.
	 */
	public static function convert( $source_path, $max_kb = 0 ) {
		if ( self::imagick_supports_webp() ) {
			$result = self::convert_with_imagick( $source_path, $max_kb );
			if ( ! is_wp_error( $result ) ) {
				return $result;
			}
		}
		if ( function_exists( 'imagewebp' ) ) {
			$result = self::convert_with_gd( $source_path, $max_kb );
			if ( ! is_wp_error( $result ) ) {
				return $result;
			}
		}
		return new WP_Error(
			'rapm_no_webp_support',
			__( 'This host has neither Imagick nor GD WebP support available, so images can\'t be auto-converted. Please upload a .webp file directly, or ask your host to enable WebP support.', 'rapm' )
		);
	}

	private static function convert_with_imagick( $source_path, $max_kb ) {
		try {
			$image = new Imagick( $source_path );
			$image->setImageFormat( 'webp' );
			// Flatten transparency onto white — a promo banner is always
			// opaque, and an unflattened alpha channel can bloat WebP size.
			if ( $image->getImageAlphaChannel() ) {
				$image->setImageBackgroundColor( new ImagickPixel( 'white' ) );
				$image = $image->mergeImageLayers( Imagick::LAYERMETHOD_FLATTEN );
			}

			$quality = 85;
			$tmp     = '';
			do {
				if ( $tmp && file_exists( $tmp ) ) {
					wp_delete_file( $tmp );
				}
				$image->setImageCompressionQuality( $quality );
				$tmp = wp_tempnam( 'rapm-webp.webp' );
				$image->writeImage( $tmp );
				$size_kb = filesize( $tmp ) / 1024;
				$quality -= 10;
			} while ( $max_kb && $size_kb > $max_kb && $quality >= 35 );

			$dims = getimagesize( $tmp );
			$image->clear();
			$image->destroy();

			return array(
				'path'    => $tmp,
				'width'   => $dims ? $dims[0] : 0,
				'height'  => $dims ? $dims[1] : 0,
				'size_kb' => round( $size_kb, 1 ),
			);
		} catch ( Exception $e ) {
			return new WP_Error( 'rapm_imagick_failed', $e->getMessage() );
		}
	}

	private static function convert_with_gd( $source_path, $max_kb ) {
		$info = getimagesize( $source_path );
		if ( ! $info ) {
			return new WP_Error( 'rapm_gd_failed', __( 'Could not read the uploaded image.', 'rapm' ) );
		}

		switch ( $info['mime'] ) {
			case 'image/jpeg':
				$src = imagecreatefromjpeg( $source_path );
				break;
			case 'image/png':
				$src = imagecreatefrompng( $source_path );
				break;
			case 'image/webp':
				$src = function_exists( 'imagecreatefromwebp' ) ? imagecreatefromwebp( $source_path ) : false;
				break;
			case 'image/gif':
				$src = imagecreatefromgif( $source_path );
				break;
			default:
				$src = false;
		}
		if ( ! $src ) {
			return new WP_Error( 'rapm_gd_failed', __( 'Unsupported image type for conversion.', 'rapm' ) );
		}

		// Flatten transparency onto white, same reasoning as the Imagick path.
		$w    = imagesx( $src );
		$h    = imagesy( $src );
		$flat = imagecreatetruecolor( $w, $h );
		$white = imagecolorallocate( $flat, 255, 255, 255 );
		imagefill( $flat, 0, 0, $white );
		imagecopy( $flat, $src, 0, 0, 0, 0, $w, $h );
		imagedestroy( $src );

		$quality = 85;
		$tmp     = '';
		do {
			if ( $tmp && file_exists( $tmp ) ) {
				wp_delete_file( $tmp );
			}
			$tmp = wp_tempnam( 'rapm-webp.webp' );
			imagewebp( $flat, $tmp, $quality );
			$size_kb = filesize( $tmp ) / 1024;
			$quality -= 10;
		} while ( $max_kb && $size_kb > $max_kb && $quality >= 35 );

		imagedestroy( $flat );

		return array(
			'path'    => $tmp,
			'width'   => $w,
			'height'  => $h,
			'size_kb' => round( $size_kb, 1 ),
		);
	}
}
