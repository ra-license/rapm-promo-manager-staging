<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [rapm_marquee] — a scrolling text ticker. Reuses the rapm_asset data
 * model and the shared scheduling engine (RAPM_Schedule.watch()), but not
 * Swiper — text-only content doesn't need a slide library, just a CSS
 * animation. Kind "marquee" has no image slots at all (see RAPM_Slots),
 * so every item here is just a headline (+ optional button text) linking
 * to a destination.
 */
class RAPM_Marquee {

	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'placement' => 'default',
				'speed'     => 40, // Seconds for one full loop of the track.
			),
			$atts,
			'rapm_marquee'
		);

		$placement = sanitize_title( $atts['placement'] );

		$query = new WP_Query(
			array(
				'post_type'      => 'rapm_asset',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'menu_order date',
				'order'          => 'ASC',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					'relation' => 'AND',
					array(
						'key'   => '_rapm_placement',
						'value' => $placement,
					),
					array(
						'key'   => '_rapm_kind',
						'value' => 'marquee',
					),
				),
			)
		);

		if ( ! $query->have_posts() ) {
			return '';
		}

		$instance_id = 'rapm-marquee-' . $placement . '-' . wp_unique_id();

		ob_start();
		?>
		<div class="rapm-marquee <?php echo esc_attr( $instance_id ); ?>" data-rapm-marquee style="display:none;--rapm-marquee-speed:<?php echo (int) $atts['speed']; ?>s;">
			<div class="rapm-marquee-track">
				<?php foreach ( $query->posts as $post ) : self::render_item( $post->ID ); endforeach; ?>
			</div>
		</div>
		<script>
			( function () {
				function init() {
					if ( typeof RAPM_Schedule === 'undefined' ) { return; }
					var root  = document.querySelector( '.<?php echo esc_js( $instance_id ); ?>' );
					var track = root.querySelector( '.rapm-marquee-track' );

					RAPM_Schedule.watch( root, '.rapm-marquee-item', function ( active ) {
						if ( ! active.length ) {
							root.style.display = 'none';
							return;
						}
						track.innerHTML = '';
						// Duplicated once so the CSS scroll animation
						// (translateX to -50%) loops seamlessly.
						active.concat( active ).forEach( function ( el ) {
							track.appendChild( el.cloneNode( true ) );
						} );
						root.style.display = '';
					} );
				}
				if ( document.readyState === 'loading' ) {
					document.addEventListener( 'DOMContentLoaded', init );
				} else {
					init();
				}
			} )();
		</script>
		<?php
		wp_reset_postdata();
		return ob_get_clean();
	}

	private static function render_item( $asset_id ) {
		$headline   = get_post_meta( $asset_id, '_rapm_headline', true );
		$cta_text   = get_post_meta( $asset_id, '_rapm_cta_text', true );
		$starts_at  = get_post_meta( $asset_id, '_rapm_starts_at', true );
		$ends_at    = get_post_meta( $asset_id, '_rapm_ends_at', true );
		$dest_type  = get_post_meta( $asset_id, '_rapm_destination_type', true );
		$dest_value = get_post_meta( $asset_id, '_rapm_destination_value', true );
		$url        = RAPM_Destination::resolve_url( $dest_type, $dest_value );

		if ( ! $headline ) {
			return; // No text — nothing to show for this item.
		}

		$text = $cta_text ? $headline . ' — ' . $cta_text : $headline;
		?>
		<span class="rapm-marquee-item" data-rapm-start="<?php echo esc_attr( $starts_at ); ?>" data-rapm-end="<?php echo esc_attr( $ends_at ); ?>">
			<?php if ( $url ) : ?>
				<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $text ); ?></a>
			<?php else : ?>
				<?php echo esc_html( $text ); ?>
			<?php endif; ?>
		</span>
		<?php
	}

	public static function should_load_assets() {
		if ( is_singular() ) {
			global $post;
			if ( $post && ( has_shortcode( $post->post_content, 'rapm_marquee' ) || has_shortcode( apply_filters( 'the_content', $post->post_content ), 'rapm_marquee' ) ) ) {
				return true;
			}
		}
		return is_active_widget( false, false, 'text', true ) || is_active_widget( false, false, 'shortcode', true );
	}

	public static function enqueue() {
		if ( ! self::should_load_assets() ) {
			return;
		}
		wp_enqueue_style( 'rapm-marquee-css', RAPM_URL . 'assets/css/rapm-marquee.css', array(), RAPM_VERSION );
		wp_enqueue_script( 'rapm-schedule-js', RAPM_URL . 'assets/js/rapm-schedule.js', array(), RAPM_VERSION, true );
	}
}
