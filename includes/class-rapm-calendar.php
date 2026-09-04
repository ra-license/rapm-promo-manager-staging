<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [rapm_promotions_calendar] — a month-grid view of scheduled promotions
 * across the site (or a specific kind/placement, via shortcode
 * attributes). Deliberately distinct from the separate Community Events
 * Calendar plugin: this shows RA Promo Manager's own promotional assets
 * and their scheduled date ranges, not community-submitted events, and
 * has no public submission path at all — every asset it can show is one
 * created through the validated Add/Edit Asset admin form.
 *
 * Rendered client-side from an embedded JSON payload, for the same
 * caching-safety reason as every other display mode here: a full-page
 * cache plugin only ever serves a static snapshot, so "what month is it,
 * what's active on which day" has to be decided in the visitor's own
 * browser, not baked in at render time.
 *
 * Only promotions with BOTH a start and an end date are included — an
 * evergreen asset with no schedule isn't a "promotion with dates" in the
 * sense a calendar is useful for.
 */
class RAPM_Calendar {

	/**
	 * How many published promotions currently qualify to appear on
	 * [rapm_promotions_calendar] (both a start and end date set) — the
	 * same rule the shortcode itself applies. Used by the Sliders
	 * dashboard to show a live count next to the shortcode, rather than
	 * just describing what it does.
	 */
	public static function count_scheduled() {
		$posts = get_posts(
			array(
				'post_type'      => 'rapm_asset',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					'relation' => 'AND',
					array( 'key' => '_rapm_starts_at', 'value' => '', 'compare' => '!=' ),
					array( 'key' => '_rapm_ends_at', 'value' => '', 'compare' => '!=' ),
				),
			)
		);
		return count( $posts );
	}

	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'placement' => '',
				'kind'      => '',
			),
			$atts,
			'rapm_promotions_calendar'
		);

		$meta_query = array( 'relation' => 'AND' );
		if ( $atts['placement'] ) {
			$meta_query[] = array( 'key' => '_rapm_placement', 'value' => sanitize_title( $atts['placement'] ) );
		}
		if ( $atts['kind'] ) {
			$meta_query[] = array( 'key' => '_rapm_kind', 'value' => sanitize_key( $atts['kind'] ) );
		}
		$meta_query[] = array( 'key' => '_rapm_starts_at', 'value' => '', 'compare' => '!=' );
		$meta_query[] = array( 'key' => '_rapm_ends_at', 'value' => '', 'compare' => '!=' );

		$query = new WP_Query(
			array(
				'post_type'      => 'rapm_asset',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_query'     => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);

		$items = array();
		foreach ( $query->posts as $post ) {
			$starts = get_post_meta( $post->ID, '_rapm_starts_at', true );
			$ends   = get_post_meta( $post->ID, '_rapm_ends_at', true );
			if ( ! $starts || ! $ends ) {
				continue;
			}
			$headline   = get_post_meta( $post->ID, '_rapm_headline', true );
			$dest_type  = get_post_meta( $post->ID, '_rapm_destination_type', true );
			$dest_value = get_post_meta( $post->ID, '_rapm_destination_value', true );
			$items[]    = array(
				'label' => $headline ? $headline : get_the_title( $post->ID ),
				'url'   => RAPM_Destination::resolve_url( $dest_type, $dest_value ),
				'start' => gmdate( 'Y-m-d', strtotime( $starts ) ),
				'end'   => gmdate( 'Y-m-d', strtotime( $ends ) ),
			);
		}
		wp_reset_postdata();

		if ( ! $items ) {
			return '<p class="rapm-calendar-empty">' . esc_html__( 'No scheduled promotions right now.', 'rapm' ) . '</p>';
		}

		$instance_id = 'rapm-cal-' . wp_unique_id();

		ob_start();
		?>
		<div class="rapm-calendar <?php echo esc_attr( $instance_id ); ?>" data-rapm-calendar>
			<div class="rapm-calendar-header">
				<button type="button" class="rapm-calendar-prev" aria-label="<?php esc_attr_e( 'Previous month', 'rapm' ); ?>">&lsaquo;</button>
				<span class="rapm-calendar-title"></span>
				<button type="button" class="rapm-calendar-next" aria-label="<?php esc_attr_e( 'Next month', 'rapm' ); ?>">&rsaquo;</button>
			</div>
			<div class="rapm-calendar-grid"></div>
			<div class="rapm-calendar-day-detail" hidden></div>
		</div>
		<script type="application/json" class="rapm-calendar-data"><?php echo wp_json_encode( $items ); ?></script>
		<script>
			( function () {
				function init() {
					var root = document.querySelector( '.<?php echo esc_js( $instance_id ); ?>' );
					var data = JSON.parse( root.nextElementSibling.textContent );

					function pad( n ) { return n < 10 ? '0' + n : '' + n; }
					function toISO( y, m, d ) { return y + '-' + pad( m + 1 ) + '-' + pad( d ); }

					var today     = new Date();
					var viewYear  = today.getFullYear();
					var viewMonth = today.getMonth();

					var titleEl  = root.querySelector( '.rapm-calendar-title' );
					var gridEl   = root.querySelector( '.rapm-calendar-grid' );
					var detailEl = root.querySelector( '.rapm-calendar-day-detail' );
					var weekdayLabels = [
						<?php
						echo wp_json_encode( __( 'Sun', 'rapm' ) ) . ',' . wp_json_encode( __( 'Mon', 'rapm' ) ) . ',' . wp_json_encode( __( 'Tue', 'rapm' ) ) . ','
							. wp_json_encode( __( 'Wed', 'rapm' ) ) . ',' . wp_json_encode( __( 'Thu', 'rapm' ) ) . ',' . wp_json_encode( __( 'Fri', 'rapm' ) ) . ',' . wp_json_encode( __( 'Sat', 'rapm' ) );
						?>
					];

					function showDetail( list ) {
						detailEl.innerHTML = '';
						detailEl.hidden = false;
						var ul = document.createElement( 'ul' );
						list.forEach( function ( item ) {
							var li = document.createElement( 'li' );
							if ( item.url ) {
								var a = document.createElement( 'a' );
								a.href = item.url;
								a.textContent = item.label;
								li.appendChild( a );
							} else {
								li.textContent = item.label;
							}
							ul.appendChild( li );
						} );
						detailEl.appendChild( ul );
					}

					function render() {
						gridEl.innerHTML = '';
						detailEl.hidden = true;

						var first       = new Date( viewYear, viewMonth, 1 );
						var startDay    = first.getDay();
						var daysInMonth = new Date( viewYear, viewMonth + 1, 0 ).getDate();
						var todayIso    = toISO( today.getFullYear(), today.getMonth(), today.getDate() );

						titleEl.textContent = new Intl.DateTimeFormat( undefined, { month: 'long', year: 'numeric' } ).format( first );

						weekdayLabels.forEach( function ( label ) {
							var el = document.createElement( 'div' );
							el.className = 'rapm-calendar-weekday';
							el.textContent = label;
							gridEl.appendChild( el );
						} );

						for ( var i = 0; i < startDay; i++ ) {
							var blank = document.createElement( 'div' );
							blank.className = 'rapm-calendar-cell is-empty';
							gridEl.appendChild( blank );
						}

						for ( var day = 1; day <= daysInMonth; day++ ) {
							var iso = toISO( viewYear, viewMonth, day );
							var todays = data.filter( function ( item ) { return item.start <= iso && iso <= item.end; } );

							var cell = document.createElement( 'button' );
							cell.type = 'button';
							cell.className = 'rapm-calendar-cell'
								+ ( todays.length ? ' has-promo' : '' )
								+ ( iso === todayIso ? ' is-today' : '' );

							var num = document.createElement( 'span' );
							num.className = 'rapm-calendar-daynum';
							num.textContent = day;
							cell.appendChild( num );

							if ( todays.length ) {
								var dot = document.createElement( 'span' );
								dot.className = 'rapm-calendar-dot';
								dot.textContent = todays.length;
								cell.appendChild( dot );
								cell.addEventListener( 'click', ( function ( list ) {
									return function () { showDetail( list ); };
								} )( todays ) );
							}

							gridEl.appendChild( cell );
						}
					}

					root.querySelector( '.rapm-calendar-prev' ).addEventListener( 'click', function () {
						viewMonth--;
						if ( viewMonth < 0 ) { viewMonth = 11; viewYear--; }
						render();
					} );
					root.querySelector( '.rapm-calendar-next' ).addEventListener( 'click', function () {
						viewMonth++;
						if ( viewMonth > 11 ) { viewMonth = 0; viewYear++; }
						render();
					} );

					render();
				}
				if ( document.readyState === 'loading' ) {
					document.addEventListener( 'DOMContentLoaded', init );
				} else {
					init();
				}
			} )();
		</script>
		<?php
		return ob_get_clean();
	}

	public static function should_load_assets() {
		if ( is_singular() ) {
			global $post;
			if ( $post && ( has_shortcode( $post->post_content, 'rapm_promotions_calendar' ) || has_shortcode( apply_filters( 'the_content', $post->post_content ), 'rapm_promotions_calendar' ) ) ) {
				return true;
			}
		}
		return is_active_widget( false, false, 'text', true ) || is_active_widget( false, false, 'shortcode', true );
	}

	public static function enqueue() {
		if ( ! self::should_load_assets() ) {
			return;
		}
		wp_enqueue_style( 'rapm-calendar-css', RAPM_URL . 'assets/css/rapm-calendar.css', array(), RAPM_VERSION );
	}
}
