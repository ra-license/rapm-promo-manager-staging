<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [rapm_curated_results] — renders a product grid from a "curated"
 * destination's query args: specific SKUs shown first, in the order
 * given, then a fallback query (search words, a brand, or a category)
 * filling in the rest up to a max count. The config lives entirely in
 * the URL's query string (set by RAPM_Destination::curated_url()), not
 * in a separate stored list, so this page works for any asset that
 * links here — nothing to manage beyond the one page itself.
 */
class RAPM_Curated_Results {

	public static function shortcode( $atts ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return '';
		}

		$skus_raw       = isset( $_GET['rapm_skus'] ) ? sanitize_text_field( wp_unslash( $_GET['rapm_skus'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$fallback_type  = isset( $_GET['rapm_fallback_type'] ) ? sanitize_key( wp_unslash( $_GET['rapm_fallback_type'] ) ) : 'none'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$fallback_value = isset( $_GET['rapm_fallback_value'] ) ? sanitize_text_field( wp_unslash( $_GET['rapm_fallback_value'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$pinned_ids = array();
		foreach ( array_filter( array_map( 'trim', explode( ',', $skus_raw ) ) ) as $sku ) {
			$product_id = wc_get_product_id_by_sku( $sku );
			if ( $product_id ) {
				$pinned_ids[] = $product_id;
			}
		}
		$pinned_ids = array_values( array_unique( $pinned_ids ) );

		$max_total     = (int) apply_filters( 'rapm_curated_results_max', 24 );
		$fallback_slots = max( 0, $max_total - count( $pinned_ids ) );
		$fallback_ids   = array();

		if ( $fallback_slots > 0 && 'none' !== $fallback_type && $fallback_value ) {
			$args = array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => $fallback_slots,
				'post__not_in'   => $pinned_ids,
				'fields'         => 'ids',
			);
			if ( 'search' === $fallback_type ) {
				$args['s'] = $fallback_value;
			} elseif ( 'category' === $fallback_type ) {
				// Matched by name, not slug — the account manager types the
				// category exactly as it reads on the site, not a URL slug.
				$args['tax_query'] = array( array( 'taxonomy' => 'product_cat', 'field' => 'name', 'terms' => $fallback_value ) ); // phpcs:ignore
			} elseif ( 'brand' === $fallback_type ) {
				$args['tax_query'] = array( array( 'taxonomy' => RAPM_Destination::brand_taxonomy(), 'field' => 'name', 'terms' => $fallback_value ) ); // phpcs:ignore
			}
			$fallback_query = new WP_Query( $args );
			$fallback_ids   = $fallback_query->posts;
		}

		$all_ids = array_merge( $pinned_ids, $fallback_ids );
		if ( empty( $all_ids ) ) {
			return '<p>' . esc_html__( 'No products to show right now.', 'rapm' ) . '</p>';
		}

		// Reuses WooCommerce's own loop markup/template part, not a custom
		// grid — this is what makes the result visually match the rest of
		// the shop's product grid (theme styling, hooks/badges other
		// plugins add, etc.) instead of looking like a separate system.
		ob_start();
		echo woocommerce_product_loop_start( false ); // phpcs:ignore
		global $product;
		foreach ( $all_ids as $product_id ) {
			$post_object = get_post( $product_id );
			if ( ! $post_object ) {
				continue;
			}
			$GLOBALS['post'] = $post_object; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			setup_postdata( $post_object );
			$product = wc_get_product( $product_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			wc_get_template_part( 'content', 'product' );
		}
		wp_reset_postdata();
		echo woocommerce_product_loop_end( false ); // phpcs:ignore
		return ob_get_clean();
	}
}
