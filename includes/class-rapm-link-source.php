<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fetches a promotional image from an outside link — a direct file URL, or
 * a Google Drive share link — and runs it through the exact same
 * dimension/format/size validation as a direct upload
 * (RAPM_Upload_Handler::validate_convert_sideload()), since that
 * validation is the one thing this plugin exists to guarantee and a
 * link-sourced image should never be held to a looser standard.
 */
class RAPM_Link_Source {

	/**
	 * Rewrites a Google Drive "share" link (drive.google.com/file/d/ID/view
	 * or drive.google.com/open?id=ID) into Drive's direct-download URL.
	 * Any other host passes through unchanged.
	 */
	public static function normalize_url( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! $host || false === strpos( $host, 'drive.google.com' ) ) {
			return $url;
		}
		$file_id = '';
		if ( preg_match( '#/file/d/([a-zA-Z0-9_-]+)#', $url, $m ) ) {
			$file_id = $m[1];
		} elseif ( preg_match( '#[?&]id=([a-zA-Z0-9_-]+)#', $url, $m ) ) {
			$file_id = $m[1];
		}
		if ( ! $file_id ) {
			return $url;
		}
		return 'https://drive.google.com/uc?export=download&id=' . rawurlencode( $file_id );
	}

	public static function is_drive_url( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		return $host && false !== strpos( $host, 'drive.google.com' );
	}

	/**
	 * Downloads $url, validates it against $slot, converts/compresses it,
	 * and sideloads it into the Media Library. Returns the new attachment
	 * ID, or a WP_Error explaining what went wrong — including a
	 * Drive-specific hint, since Drive sometimes serves an HTML "can't
	 * scan this file for viruses" warning page instead of the file itself
	 * for larger files, which downloads successfully but isn't a picture.
	 */
	public static function fetch_and_validate( $url, $slot, $parent_id ) {
		$url = esc_url_raw( $url );
		if ( ! $url ) {
			return new WP_Error( 'rapm_bad_url', __( 'That doesn\'t look like a valid web address.', 'rapm' ) );
		}

		$is_drive  = self::is_drive_url( $url );
		$fetch_url = self::normalize_url( $url );

		require_once ABSPATH . 'wp-admin/includes/file.php';
		$tmp_path = download_url( $fetch_url, 45 );

		if ( is_wp_error( $tmp_path ) ) {
			return new WP_Error(
				'rapm_fetch_failed',
				sprintf(
					/* translators: %s: the underlying download error */
					__( 'Could not download that link: %s', 'rapm' ),
					$tmp_path->get_error_message()
				)
			);
		}

		$dims = getimagesize( $tmp_path );
		if ( ! $dims ) {
			wp_delete_file( $tmp_path );
			if ( $is_drive ) {
				return new WP_Error(
					'rapm_not_an_image',
					__( 'That Google Drive link didn\'t return a picture directly. Make sure the file\'s sharing setting is "Anyone with the link," and try again — very large files can also get blocked behind a "can\'t scan for viruses" warning page instead of downloading directly.', 'rapm' )
				);
			}
			return new WP_Error(
				'rapm_not_an_image',
				__( 'That link didn\'t return a picture — double check it\'s a direct link to the image file itself, not a page that shows the image.', 'rapm' )
			);
		}

		$filename_hint = basename( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		$result        = RAPM_Upload_Handler::validate_convert_sideload( $tmp_path, $filename_hint ?: 'linked-image', $slot, $parent_id );

		if ( file_exists( $tmp_path ) ) {
			wp_delete_file( $tmp_path );
		}

		return $result;
	}
}
