<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [rapm_marquee items="4"] — a compact row of square image tiles (a
 * "quick links" strip), not a scrolling ticker. Every tile is forced
 * through the same 1080x1080 slot (RAPM_Slots) regardless of the source
 * photo's own shape, which is exactly what keeps every tile the same
 * size on screen — the original version of this (elsewhere on the site)
 * displayed each image at its own native proportions, so the row visibly
 * varied tile to tile depending on what a client happened to upload.
 *
 * `items` controls how many tiles sit in one row (1–5, default 4); any
 * additional active tiles beyond that wrap onto further rows rather than
 * being hidden, so a client's promotion is never silently dropped just
 * because the row is "full."
 */
class RAPM_Marquee {

	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'placement' => 'default',
				'items'     => RAPM_Admin_Settings::get( 'default_marquee_items' ),
			),
			$atts,
			'rapm_marquee'
		);

		$placement = sanitize_title( $atts['placement'] );
		$per_row   = max( 1, min( 5, (int) $atts['items'] ) );

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
		<div class="rapm-marquee <?php echo esc_attr( $instance_id ); ?>" data-rapm-marquee style="display:none;--rapm-marquee-items:<?php echo (int) $per_row; ?>;">
			<?php foreach ( $query->posts as $post ) : self::render_tile( $post->ID ); endforeach; ?>
		</div>
		<script>
			( function () {
				function init() {
					if ( typeof RAPM_Schedule === 'undefined' ) { return; }
					var root = document.querySelector( '.<?php echo esc_js( $instance_id ); ?>' );

					RAPM_Schedule.watch( root, '.rapm-marquee-tile', function ( active ) {
						var all = root.querySelectorAll( '.rapm-marquee-tile' );
						Array.prototype.forEach.call( all, function ( el ) { el.style.display = 'none'; } );
						active.forEach( function ( el ) { el.style.display = ''; } );
						root.style.display = active.length ? '' : 'none';
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

	private static function render_tile( $asset_id ) {
		$headline    = get_post_meta( $asset_id, '_rapm_headline', true );
		$subhead     = get_post_meta( $asset_id, '_rapm_subhead', true );
		$alt         = get_post_meta( $asset_id, '_rapm_alt_text', true );
		$starts_at   = get_post_meta( $asset_id, '_rapm_starts_at', true );
		$ends_at     = get_post_meta( $asset_id, '_rapm_ends_at', true );
		$dest_type   = get_post_meta( $asset_id, '_rapm_destination_type', true );
		$dest_value  = get_post_meta( $asset_id, '_rapm_destination_value', true );
		$url         = RAPM_Destination::resolve_url( $dest_type, $dest_value );
		$desktop_id  = (int) get_post_meta( $asset_id, '_rapm_image_desktop_id', true );
		$desktop_src = $desktop_id ? wp_get_attachment_image_url( $desktop_id, 'full' ) : '';

		if ( ! $desktop_src ) {
			return; // No usable image — nothing to show for this tile.
		}

		$tag = $url ? 'a' : 'div';
		?>
		<<?php echo esc_html( $tag ); ?> <?php echo $url ? 'href="' . esc_url( $url ) . '"' : ''; ?> class="rapm-marquee-tile" data-rapm-start="<?php echo esc_attr( $starts_at ); ?>" data-rapm-end="<?php echo esc_attr( $ends_at ); ?>">
			<span class="rapm-marquee-tile-image">
				<img src="<?php echo esc_url( $desktop_src ); ?>" alt="<?php echo esc_attr( $alt ); ?>" loading="lazy" />
			</span>
			<?php if ( $headline || $subhead ) : ?>
				<span class="rapm-marquee-tile-caption">
					<?php if ( $headline ) : ?><span class="rapm-marquee-tile-headline"><?php echo esc_html( $headline ); ?></span><?php endif; ?>
					<?php if ( $subhead ) : ?><span class="rapm-marquee-tile-subhead"><?php echo esc_html( $subhead ); ?></span><?php endif; ?>
				</span>
			<?php endif; ?>
		</<?php echo esc_html( $tag ); ?>>
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
