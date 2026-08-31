<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Centralizes what an asset links to — a plain URL, a WordPress post/page
 * (Elementor-built pages are just posts, so this covers those too), or a
 * WooCommerce product/category. Every renderer calls resolve_url() rather
 * than re-implementing this branching, so a new display mode never has to
 * know the difference between a "post" and a "wc_product" destination.
 */
class RAPM_Destination {

	public static function types() {
		$types = array(
			'url'  => __( 'A specific link', 'rapm' ),
			'post' => __( 'A page or post on this site', 'rapm' ),
		);
		if ( class_exists( 'WooCommerce' ) ) {
			$types['wc_product']  = __( 'A WooCommerce product', 'rapm' );
			$types['wc_category'] = __( 'A WooCommerce category', 'rapm' );
		}
		return $types;
	}

	public static function resolve_url( $type, $value ) {
		switch ( $type ) {
			case 'post':
				return $value ? get_permalink( (int) $value ) : '';
			case 'wc_product':
				if ( ! class_exists( 'WooCommerce' ) || ! $value ) {
					return '';
				}
				return get_permalink( (int) $value );
			case 'wc_category':
				if ( ! class_exists( 'WooCommerce' ) || ! $value ) {
					return '';
				}
				$link = get_term_link( (int) $value, 'product_cat' );
				return is_wp_error( $link ) ? '' : $link;
			case 'url':
			default:
				return $value ? esc_url_raw( $value ) : '';
		}
	}

	/**
	 * Reads an asset's stored destination and returns its resolved URL, or
	 * '' if the asset has none set — renderers can always safely check
	 * truthiness rather than separately checking type/value.
	 */
	public static function url_for_asset( $asset_id ) {
		$type  = get_post_meta( $asset_id, '_rapm_destination_type', true );
		$value = get_post_meta( $asset_id, '_rapm_destination_value', true );
		if ( ! $type ) {
			return '';
		}
		return self::resolve_url( $type, $value );
	}
}
