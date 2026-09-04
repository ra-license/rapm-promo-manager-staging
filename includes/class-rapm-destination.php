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

	/**
	 * Backs the "hand-picked list" form's optional bulk-import (.csv or
	 * .xlsx) — a client's own spreadsheet of SKUs (a column titled "SKU")
	 * instead of searching for each product one at a time. Every SKU is
	 * checked against this site's actual catalog; a SKU that doesn't match
	 * any product is reported back rather than silently dropped, so the
	 * client can see exactly what wasn't found and follow up on it.
	 */
	public static function ajax_import_skus() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'rapm' ) ), 403 );
		}
		check_ajax_referer( 'rapm_search_destination', 'nonce' );

		if ( empty( $_FILES['file']['tmp_name'] ) || ! empty( $_FILES['file']['error'] ) ) {
			wp_send_json_error( array( 'message' => __( 'That file didn\'t upload correctly — please try again.', 'rapm' ) ) );
		}

		$extension = strtolower( pathinfo( sanitize_file_name( $_FILES['file']['name'] ), PATHINFO_EXTENSION ) );

		if ( 'csv' === $extension ) {
			$result = self::read_csv_skus( $_FILES['file']['tmp_name'] );
		} elseif ( 'xlsx' === $extension ) {
			$result = self::read_xlsx_skus( $_FILES['file']['tmp_name'] );
		} else {
			wp_send_json_error( array( 'message' => __( 'Please upload a .csv or .xlsx (Excel) file.', 'rapm' ) ) );
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		list( $raw_skus, $truncated ) = $result;

		$found     = array();
		$not_found = array();
		$seen      = array();

		foreach ( $raw_skus as $raw_sku ) {
			$sku = trim( $raw_sku );
			if ( '' === $sku || isset( $seen[ $sku ] ) ) {
				continue;
			}
			$seen[ $sku ] = true;

			$product_id = class_exists( 'WooCommerce' ) ? wc_get_product_id_by_sku( $sku ) : 0;
			if ( $product_id ) {
				$found[] = array( 'sku' => $sku, 'label' => get_the_title( $product_id ) );
			} else {
				$not_found[] = $sku;
			}
		}

		wp_send_json_success(
			array(
				'found'     => $found,
				'not_found' => $not_found,
				'truncated' => $truncated,
			)
		);
	}

	/**
	 * Reads the "SKU" column of an uploaded .csv file. Returns
	 * array( $raw_skus, $was_truncated ), or a WP_Error.
	 */
	private static function read_csv_skus( $path ) {
		$handle = fopen( $path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen
		if ( ! $handle ) {
			return new WP_Error( 'rapm_csv_unreadable', __( 'Could not read that file.', 'rapm' ) );
		}

		$header = fgetcsv( $handle );
		if ( ! $header ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose
			return new WP_Error( 'rapm_csv_empty', __( 'That file looks empty.', 'rapm' ) );
		}

		$sku_col = null;
		foreach ( $header as $i => $col ) {
			if ( 'sku' === strtolower( trim( $col ) ) ) {
				$sku_col = $i;
				break;
			}
		}
		if ( null === $sku_col ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose
			return new WP_Error( 'rapm_csv_no_sku_column', __( 'Couldn\'t find a column titled "SKU" in that file\'s first row.', 'rapm' ) );
		}

		$skus      = array();
		$row_count = 0;
		$max_rows  = 2000;

		while ( $row_count < $max_rows && ( $row = fgetcsv( $handle ) ) !== false ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition
			++$row_count;
			if ( isset( $row[ $sku_col ] ) ) {
				$skus[] = $row[ $sku_col ];
			}
		}
		$truncated = $row_count >= $max_rows;
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose

		return array( $skus, $truncated );
	}

	/**
	 * Reads the "SKU" column of an uploaded .xlsx file — a small,
	 * purpose-built reader (ZipArchive + the XML inside, both built into
	 * PHP core) rather than pulling in a full spreadsheet library, since
	 * all that's actually needed is one column of one sheet. Returns
	 * array( $raw_skus, $was_truncated ), or a WP_Error.
	 */
	private static function read_xlsx_skus( $path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'rapm_xlsx_unavailable', __( 'This server can\'t read Excel files (the ZipArchive extension is missing). Please save it as a .csv file instead.', 'rapm' ) );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			return new WP_Error( 'rapm_xlsx_unreadable', __( 'Could not open that Excel file — it may be corrupted, or not really an .xlsx file.', 'rapm' ) );
		}

		// Text cells are usually stored as an index into this shared table
		// rather than the literal text, so it has to be read first.
		$shared_strings = array();
		$shared_xml     = $zip->getFromName( 'xl/sharedStrings.xml' );
		if ( false !== $shared_xml ) {
			$shared = simplexml_load_string( $shared_xml, 'SimpleXMLElement', LIBXML_NOCDATA );
			if ( $shared ) {
				foreach ( $shared->si as $si ) {
					$text = '';
					foreach ( $si->xpath( './/t' ) as $t ) {
						$text .= (string) $t;
					}
					$shared_strings[] = $text;
				}
			}
		}

		// The first sheet isn't reliably xl/worksheets/sheet1.xml — resolve
		// it properly via the workbook's own relationship mapping.
		$sheet_path   = 'xl/worksheets/sheet1.xml';
		$workbook_xml = $zip->getFromName( 'xl/workbook.xml' );
		$rels_xml     = $zip->getFromName( 'xl/_rels/workbook.xml.rels' );
		if ( $workbook_xml && $rels_xml ) {
			$workbook = simplexml_load_string( $workbook_xml );
			$rels     = simplexml_load_string( $rels_xml );
			if ( $workbook && $rels && isset( $workbook->sheets->sheet[0] ) ) {
				$r_attrs = $workbook->sheets->sheet[0]->attributes( 'http://schemas.openxmlformats.org/officeDocument/2006/relationships' );
				$rid     = isset( $r_attrs['id'] ) ? (string) $r_attrs['id'] : '';
				foreach ( $rels->Relationship as $rel ) {
					if ( (string) $rel['Id'] === $rid ) {
						$target = (string) $rel['Target'];
						// Target is either relative to the xl/ folder (the
						// common case, e.g. "worksheets/sheet1.xml") or an
						// absolute in-zip path (e.g. "/xl/worksheets/sheet1.xml")
						// — treating a relative one as absolute would double
						// up the xl/ prefix and fail to find the real file.
						$sheet_path = 0 === strpos( $target, '/' ) ? ltrim( $target, '/' ) : 'xl/' . $target;
						break;
					}
				}
			}
		}

		$sheet_xml = $zip->getFromName( $sheet_path );
		$zip->close();

		if ( false === $sheet_xml ) {
			return new WP_Error( 'rapm_xlsx_unreadable', __( 'Could not read that Excel file\'s data.', 'rapm' ) );
		}

		$sheet = simplexml_load_string( $sheet_xml, 'SimpleXMLElement', LIBXML_NOCDATA );
		if ( ! $sheet || ! isset( $sheet->sheetData ) ) {
			return new WP_Error( 'rapm_xlsx_unreadable', __( 'Could not read that Excel file\'s data.', 'rapm' ) );
		}

		$rows = array();
		foreach ( $sheet->sheetData->row as $row ) {
			$cells = array();
			foreach ( $row->c as $c ) {
				$ref = (string) $c['r']; // e.g. "B7"
				if ( ! preg_match( '/^([A-Z]+)/', $ref, $col_match ) ) {
					continue;
				}
				$type = (string) $c['t'];
				if ( 's' === $type ) {
					// Shared string: <v> holds an index into sharedStrings.xml,
					// not the text itself — the common case from Excel/Google Sheets.
					$idx                     = (int) $c->v;
					$cells[ $col_match[1] ] = isset( $shared_strings[ $idx ] ) ? $shared_strings[ $idx ] : '';
				} elseif ( 'inlineStr' === $type ) {
					// Inline string: the text lives in <is><t>, not <v> at all —
					// what tools like openpyxl write by default instead of
					// using the shared string table. Checked directly against
					// a real generated file rather than assumed.
					$text = '';
					foreach ( $c->xpath( './/t' ) as $t ) {
						$text .= (string) $t;
					}
					$cells[ $col_match[1] ] = $text;
				} else {
					// Numbers, formula-result strings (t="str"), etc. — the
					// literal value is already in <v>.
					$cells[ $col_match[1] ] = (string) $c->v;
				}
			}
			$rows[] = $cells;
		}

		if ( ! $rows ) {
			return new WP_Error( 'rapm_xlsx_empty', __( 'That file looks empty.', 'rapm' ) );
		}

		$header  = array_shift( $rows );
		$sku_col = null;
		foreach ( $header as $col => $label ) {
			if ( 'sku' === strtolower( trim( $label ) ) ) {
				$sku_col = $col;
				break;
			}
		}
		if ( null === $sku_col ) {
			return new WP_Error( 'rapm_xlsx_no_sku_column', __( 'Couldn\'t find a column titled "SKU" in that file\'s first row.', 'rapm' ) );
		}

		$max_rows  = 2000;
		$truncated = count( $rows ) > $max_rows;
		$rows      = array_slice( $rows, 0, $max_rows );

		$skus = array();
		foreach ( $rows as $row ) {
			if ( isset( $row[ $sku_col ] ) ) {
				$skus[] = $row[ $sku_col ];
			}
		}

		return array( $skus, $truncated );
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
