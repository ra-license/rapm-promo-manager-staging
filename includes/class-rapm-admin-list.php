<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAPM_Admin_List {

	public static function columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['rapm_preview']   = __( 'Preview', 'rapm' );
				$new['rapm_placement'] = __( 'Placement', 'rapm' );
				$new['rapm_status']    = __( 'Status', 'rapm' );
				$new['rapm_edit']      = __( 'Edit', 'rapm' );
			}
		}
		return $new;
	}

	public static function column_content( $column, $post_id ) {
		switch ( $column ) {
			case 'rapm_preview':
				$desktop_id = (int) get_post_meta( $post_id, '_rapm_image_desktop_id', true );
				if ( $desktop_id ) {
					echo wp_get_attachment_image( $desktop_id, array( 90, 30 ), false, array( 'style' => 'display:block;border-radius:4px;object-fit:cover;' ) );
				} else {
					echo '<span style="color:#999;font-style:italic;">' . esc_html__( 'No image', 'rapm' ) . '</span>';
				}
				break;

			case 'rapm_placement':
				echo esc_html( get_post_meta( $post_id, '_rapm_placement', true ) ?: 'default' ); // phpcs:ignore
				break;

			case 'rapm_status':
				echo esc_html( self::schedule_status_label( $post_id ) );
				break;

			case 'rapm_edit':
				echo '<a href="' . esc_url( admin_url( 'edit.php?post_type=rapm_asset&page=rapm-add-asset&edit=' . $post_id ) ) . '" class="button button-small">' . esc_html__( 'Edit', 'rapm' ) . '</a>';
				break;
		}
	}

	private static function schedule_status_label( $post_id ) {
		$starts = get_post_meta( $post_id, '_rapm_starts_at', true );
		$ends   = get_post_meta( $post_id, '_rapm_ends_at', true );
		$now    = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp

		if ( $starts && strtotime( $starts ) > $now ) {
			return __( 'Scheduled', 'rapm' );
		}
		if ( $ends && strtotime( $ends ) < $now ) {
			return __( 'Expired', 'rapm' );
		}
		return __( 'Live', 'rapm' );
	}
}
