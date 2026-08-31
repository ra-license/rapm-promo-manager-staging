<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Centralizes what an asset links to. Every renderer calls resolve_url()
 * rather than re-implementing this branching, so a new display mode never
 * has to know the difference between a "post" and a "wc_product"
 * destination — or a "search" vs. "curated" one.
 *
 * $value is a plain scalar (a URL string, or a numeric ID) for every type
 * except 'curated', where it's a JSON-encoded structure — see
 * build_curated_value()/decode_curated_value() — since that one type needs
 * more than one piece of data (pinned SKUs + a fallback).
 */
class RAPM_Destination {

	/**
	 * The taxonomy this site uses for product brands, so 'wc_brand' and
	 * curated-list brand fallbacks work whether a site uses WooCommerce's
	 * own native Brands feature (product_brand, WC 8.6+) or an older/
	 * different convention (commonly pa_brand, a global attribute used as
	 * a taxonomy). Filterable per-site rather than hardcoded, since this
	 * plugin runs across multiple client sites that may differ.
	 */
	public static function brand_taxonomy() {
		return apply_filters( 'rapm_brand_taxonomy', RAPM_Admin_Settings::get( 'brand_taxonomy' ) );
	}

	public static function types() {
		$types = array(
			'url'  => __( 'A specific link', 'rapm' ),
			'post' => __( 'A page or post on this site', 'rapm' ),
		);
		if ( class_exists( 'WooCommerce' ) ) {
			$types['wc_product']  = __( 'A specific product', 'rapm' );
			$types['wc_category'] = __( 'A product category', 'rapm' );
			if ( taxonomy_exists( self::brand_taxonomy() ) ) {
				$types['wc_brand'] = __( 'A specific brand', 'rapm' );
			}
			$types['search']  = __( 'Search results for some words', 'rapm' );
			$types['curated'] = __( 'A hand-picked list of products (with more filled in automatically)', 'rapm' );
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
			case 'wc_brand':
				if ( ! class_exists( 'WooCommerce' ) || ! $value ) {
					return '';
				}
				$link = get_term_link( (int) $value, self::brand_taxonomy() );
				return is_wp_error( $link ) ? '' : $link;
			case 'search':
				if ( ! class_exists( 'WooCommerce' ) || ! $value ) {
					return '';
				}
				// A blank setting means native WordPress/WooCommerce search;
				// a site running a dedicated search results page (e.g. Fast
				// Simon in "Premium" mode, confirmed to use its own
				// /search-results page rather than the native one) gets
				// configured to point here instead — see Settings.
				$base_url   = RAPM_Admin_Settings::get( 'search_results_base_url' );
				$query_param = RAPM_Admin_Settings::get( 'search_results_query_param' );
				if ( $base_url ) {
					return add_query_arg( array( $query_param => rawurlencode( $value ) ), $base_url );
				}
				return add_query_arg(
					array( 's' => rawurlencode( $value ), 'post_type' => 'product' ),
					home_url( '/' )
				);
			case 'curated':
				return self::curated_url( $value );
			case 'url':
			default:
				return $value ? esc_url_raw( $value ) : '';
		}
	}

	/**
	 * A "curated" destination points at this site's Curated Results page
	 * (set once under Promo Manager > Settings) with the pinned SKUs and
	 * fallback encoded as query args — the config lives in the link
	 * itself, not in a separate stored list, so it's portable and doesn't
	 * need its own admin screen to manage.
	 */
	private static function curated_url( $json_value ) {
		$config = self::decode_curated_value( $json_value );
		if ( empty( $config['skus'] ) && empty( $config['fallback_value'] ) ) {
			return '';
		}
		$page_url = RAPM_Admin_Settings::get( 'curated_results_page_url' );
		if ( ! $page_url ) {
			return '';
		}
		return add_query_arg(
			array(
				'rapm_skus'           => rawurlencode( implode( ',', $config['skus'] ) ),
				'rapm_fallback_type'  => rawurlencode( $config['fallback_type'] ),
				'rapm_fallback_value' => rawurlencode( $config['fallback_value'] ),
			),
			$page_url
		);
	}

	/**
	 * Builds the JSON structure stored in _rapm_destination_value for a
	 * 'curated' destination from the Add/Edit Asset form's own separate
	 * fields (a SKU textarea, a fallback-type dropdown, a fallback-value
	 * field) — the one place those get combined into the single stored
	 * value.
	 */
	public static function build_curated_value( $skus_raw, $fallback_type, $fallback_value ) {
		$skus = array_filter( array_map( 'trim', preg_split( '/[\r\n,]+/', (string) $skus_raw ) ) );
		return wp_json_encode(
			array(
				'skus'           => array_values( $skus ),
				'fallback_type'  => in_array( $fallback_type, array( 'search', 'brand', 'category', 'none' ), true ) ? $fallback_type : 'none',
				'fallback_value' => sanitize_text_field( $fallback_value ),
			)
		);
	}

	public static function decode_curated_value( $json_value ) {
		$decoded = json_decode( (string) $json_value, true );
		return wp_parse_args(
			is_array( $decoded ) ? $decoded : array(),
			array( 'skus' => array(), 'fallback_type' => 'none', 'fallback_value' => '' )
		);
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

	/**
	 * Backs the Add/Edit Asset form's search-as-you-type picker
	 * (wp_ajax_rapm_search_destination) — account managers pick a page,
	 * product, category, or brand by name, never by ID, matching the
	 * "these people aren't technical" requirement everywhere else in this
	 * plugin's UI. Also handles the "resolve" mode used to show the
	 * currently-saved selection's name when the form loads for editing,
	 * since only the ID is stored.
	 */
	public static function ajax_search() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'rapm' ) ), 403 );
		}
		check_ajax_referer( 'rapm_search_destination', 'nonce' );

		$type = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '';

		if ( isset( $_GET['resolve_id'] ) ) {
			wp_send_json_success( array( 'label' => self::label_for( $type, absint( $_GET['resolve_id'] ) ) ) );
		}

		// Used to redisplay an existing "hand-picked list" as named chips on
		// edit — only the raw SKUs are stored, so their product names need
		// looking up again. A SKU that no longer matches any product still
		// gets a chip (labeled with the SKU itself), since the SKU is what's
		// actually saved and shouldn't silently vanish from the list.
		if ( isset( $_GET['resolve_skus'] ) && class_exists( 'WooCommerce' ) ) {
			$skus    = array_filter( array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( $_GET['resolve_skus'] ) ) ) ) );
			$results = array();
			foreach ( $skus as $sku ) {
				$product_id = wc_get_product_id_by_sku( $sku );
				$results[]  = array(
					'sku'   => $sku,
					'label' => $product_id ? get_the_title( $product_id ) : $sku,
					'found' => (bool) $product_id,
				);
			}
			wp_send_json_success( array( 'results' => $results ) );
		}

		$term = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';
		if ( '' === $term ) {
			wp_send_json_success( array( 'results' => array() ) );
		}

		$results = array();

		switch ( $type ) {
			case 'post':
				$excluded = array_filter( array( 'attachment', 'rapm_asset', class_exists( 'WooCommerce' ) ? 'product' : '' ) );
				$types    = array_diff( get_post_types( array( 'public' => true ) ), $excluded );
				$query    = new WP_Query(
					array(
						'post_type'      => array_values( $types ),
						'post_status'    => 'publish',
						's'              => $term,
						'posts_per_page' => 20,
						'fields'         => 'ids',
					)
				);
				foreach ( $query->posts as $id ) {
					$results[] = array( 'id' => $id, 'label' => get_the_title( $id ) );
				}
				break;

			case 'wc_product':
				if ( ! class_exists( 'WooCommerce' ) ) {
					break;
				}
				$exclude   = array();
				$sku_match = wc_get_product_id_by_sku( trim( $term ) );
				if ( $sku_match ) {
					/* translators: %s: the SKU that was matched */
					$results[] = array( 'id' => $sku_match, 'sku' => trim( $term ), 'label' => sprintf( __( '%1$s (SKU: %2$s)', 'rapm' ), get_the_title( $sku_match ), trim( $term ) ) );
					$exclude[] = $sku_match;
				}
				$query = new WP_Query(
					array(
						'post_type'      => 'product',
						'post_status'    => 'publish',
						's'              => $term,
						'post__not_in'   => $exclude,
						'posts_per_page' => 20,
						'fields'         => 'ids',
					)
				);
				foreach ( $query->posts as $id ) {
					$sku = get_post_meta( $id, '_sku', true );
					/* translators: 1: product name, 2: SKU */
					$results[] = array( 'id' => $id, 'sku' => $sku, 'label' => $sku ? sprintf( __( '%1$s (SKU: %2$s)', 'rapm' ), get_the_title( $id ), $sku ) : get_the_title( $id ) );
				}
				break;

			case 'wc_category':
				if ( ! class_exists( 'WooCommerce' ) ) {
					break;
				}
				$terms = get_terms( array( 'taxonomy' => 'product_cat', 'name__like' => $term, 'number' => 20, 'hide_empty' => false ) );
				foreach ( is_wp_error( $terms ) ? array() : $terms as $t ) {
					$results[] = array( 'id' => $t->term_id, 'label' => $t->name );
				}
				break;

			case 'wc_brand':
				if ( ! class_exists( 'WooCommerce' ) || ! taxonomy_exists( self::brand_taxonomy() ) ) {
					break;
				}
				$terms = get_terms( array( 'taxonomy' => self::brand_taxonomy(), 'name__like' => $term, 'number' => 20, 'hide_empty' => false ) );
				foreach ( is_wp_error( $terms ) ? array() : $terms as $t ) {
					$results[] = array( 'id' => $t->term_id, 'label' => $t->name );
				}
				break;
		}

		wp_send_json_success( array( 'results' => $results ) );
	}

	private static function label_for( $type, $id ) {
		if ( ! $id ) {
			return '';
		}
		switch ( $type ) {
			case 'post':
			case 'wc_product':
				return get_the_title( $id );
			case 'wc_category':
				$term = get_term( $id, 'product_cat' );
				return ( $term && ! is_wp_error( $term ) ) ? $term->name : '';
			case 'wc_brand':
				$term = get_term( $id, self::brand_taxonomy() );
				return ( $term && ! is_wp_error( $term ) ) ? $term->name : '';
			default:
				return '';
		}
	}
}
