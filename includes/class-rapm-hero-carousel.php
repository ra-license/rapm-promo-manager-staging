<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [rapm_hero] and [rapm_fold_banner] — both display "kinds" share this one
 * carousel/scheduling engine, parameterized by which kind's slot pair
 * (and which placement) to query. Evolves NovaSlider's proven Swiper.js +
 * client-side-schedule approach (already live on kemperhomefurnishings.com)
 * onto the validated rapm_asset data model: every slide now comes from a
 * properly-sized, WebP, size-capped asset with real HTML copy instead of a
 * free-form, unvalidated slide array.
 */
class RAPM_Hero_Carousel {

	public static function shortcode_hero( $atts ) {
		return self::render( 'hero', $atts, 'rapm_hero' );
	}

	public static function shortcode_fold_banner( $atts ) {
		return self::render( 'fold_banner', $atts, 'rapm_fold_banner' );
	}

	private static function render( $kind_key, $atts, $tag ) {
		$defaults = RAPM_Admin_Settings::get();
		$atts     = shortcode_atts(
			array(
				'placement' => 'default',
				'autoplay'  => $defaults['default_autoplay'] ? 'yes' : 'no',
				'speed'     => $defaults['default_autoplay_speed'],
				'nav'       => $defaults['default_nav_style'],
			),
			$atts,
			$tag
		);

		$placement = sanitize_title( $atts['placement'] );
		$kind      = RAPM_Slots::kind( $kind_key );
		$slots     = RAPM_Slots::all();

		$query = new WP_Query(
			array(
				'post_type'      => 'rapm_asset',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'menu_order date',
				'order'          => 'ASC',
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'   => '_rapm_placement',
						'value' => $placement,
					),
					array(
						'key'   => '_rapm_kind',
						'value' => $kind_key,
					),
				),
			)
		);

		if ( ! $query->have_posts() ) {
			return '';
		}

		$instance_id  = 'rapm-' . $placement . '-' . wp_unique_id();
		$desktop_slot = $slots[ $kind['desktop'] ];
		$mobile_slot  = $slots[ $kind['mobile'] ];

		ob_start();
		?>
		<style>
			.<?php echo esc_attr( $instance_id ); ?> { width: 100%; margin: 0 auto; overflow: hidden; position: relative; aspect-ratio: <?php echo esc_html( $desktop_slot['width'] . ' / ' . $desktop_slot['height'] ); ?>; }
			@media (max-width: 768px) { .<?php echo esc_attr( $instance_id ); ?> { aspect-ratio: <?php echo esc_html( $mobile_slot['width'] . ' / ' . $mobile_slot['height'] ); ?>; } }
		</style>
		<div class="swiper rapm-hero <?php echo esc_attr( $instance_id ); ?>" style="display:none;" data-rapm-carousel>
			<div class="swiper-wrapper">
				<?php foreach ( $query->posts as $post ) : self::render_slide( $post->ID ); endforeach; ?>
			</div>
			<?php if ( 'both' === $atts['nav'] || 'arrows' === $atts['nav'] ) : ?>
				<div class="swiper-button-next rapm-nav-btn"></div>
				<div class="swiper-button-prev rapm-nav-btn"></div>
			<?php endif; ?>
			<?php if ( 'both' === $atts['nav'] || 'dots' === $atts['nav'] ) : ?>
				<div class="swiper-pagination"></div>
			<?php endif; ?>
		</div>
		<script>
			( function () {
				function init() {
					if ( typeof RAPM_Schedule === 'undefined' ) { return; }
					RAPM_Schedule.init( '.<?php echo esc_js( $instance_id ); ?>', {
						loopMinSlides: 2,
						effect: 'slide',
						autoplay: <?php echo 'yes' === $atts['autoplay'] ? 'true' : 'false'; ?>,
						autoplaySpeed: <?php echo (int) $atts['speed']; ?>,
						nav: <?php echo wp_json_encode( $atts['nav'] ); ?>
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

	private static function render_slide( $asset_id ) {
		$headline     = get_post_meta( $asset_id, '_rapm_headline', true );
		$subhead      = get_post_meta( $asset_id, '_rapm_subhead', true );
		$cta_text     = get_post_meta( $asset_id, '_rapm_cta_text', true );
		$alt          = get_post_meta( $asset_id, '_rapm_alt_text', true );
		$starts_at    = get_post_meta( $asset_id, '_rapm_starts_at', true );
		$ends_at      = get_post_meta( $asset_id, '_rapm_ends_at', true );
		$dest_type    = get_post_meta( $asset_id, '_rapm_destination_type', true );
		$dest_value   = get_post_meta( $asset_id, '_rapm_destination_value', true );
		$url          = RAPM_Destination::resolve_url( $dest_type, $dest_value );
		$desktop_id   = (int) get_post_meta( $asset_id, '_rapm_image_desktop_id', true );
		$mobile_id    = (int) get_post_meta( $asset_id, '_rapm_image_mobile_id', true );
		$desktop_src  = $desktop_id ? wp_get_attachment_image_url( $desktop_id, 'full' ) : '';
		$mobile_src   = $mobile_id ? wp_get_attachment_image_url( $mobile_id, 'full' ) : '';

		if ( ! $desktop_src ) {
			return; // No usable image — nothing to show for this asset.
		}
		?>
		<div class="swiper-slide rapm-slide" data-rapm-start="<?php echo esc_attr( $starts_at ); ?>" data-rapm-end="<?php echo esc_attr( $ends_at ); ?>">
			<picture>
				<?php if ( $mobile_src ) : ?>
					<source media="(max-width: 768px)" srcset="<?php echo esc_url( $mobile_src ); ?>" />
				<?php endif; ?>
				<img src="<?php echo esc_url( $desktop_src ); ?>" alt="<?php echo esc_attr( $alt ); ?>" loading="lazy" style="width:100%;height:100%;object-fit:cover;display:block;" />
			</picture>
			<?php if ( $headline || $subhead || $cta_text ) : ?>
				<div class="rapm-slide-copy">
					<?php if ( $headline ) : ?><h2 class="rapm-headline"><?php echo esc_html( $headline ); ?></h2><?php endif; ?>
					<?php if ( $subhead ) : ?><p class="rapm-subhead"><?php echo esc_html( $subhead ); ?></p><?php endif; ?>
					<?php if ( $cta_text ) : ?><span class="rapm-cta-btn"><?php echo esc_html( $cta_text ); ?></span><?php endif; ?>
				</div>
			<?php endif; ?>
			<?php if ( $url ) : ?>
				<a href="<?php echo esc_url( $url ); ?>" class="rapm-slide-link" aria-label="<?php echo esc_attr( $headline ? $headline : $alt ); ?>"></a>
			<?php endif; ?>
		</div>
		<?php
		echo '<script type="application/ld+json">' . wp_json_encode( self::schema_for_asset( $asset_id, $desktop_src, $url ), JSON_UNESCAPED_SLASHES ) . '</script>'; // phpcs:ignore
	}

	private static function schema_for_asset( $asset_id, $image_url, $url ) {
		$headline = get_post_meta( $asset_id, '_rapm_headline', true );
		$schema   = array(
			'@context' => 'https://schema.org',
			'@type'    => 'ImageObject',
			'contentUrl' => $image_url,
			'name'       => $headline ? $headline : get_the_title( $asset_id ),
		);
		if ( $url ) {
			$schema['url'] = $url;
		}
		return $schema;
	}

	const SHORTCODE_TAGS = array( 'rapm_hero', 'rapm_fold_banner' );

	public static function should_load_assets() {
		$force_load_on = apply_filters( 'rapm_force_load_ids', array() );

		if ( is_singular() ) {
			global $post;
			if ( $post ) {
				if ( in_array( $post->ID, $force_load_on, true ) ) {
					return true;
				}
				foreach ( self::SHORTCODE_TAGS as $tag ) {
					if ( has_shortcode( $post->post_content, $tag ) ) {
						return true;
					}
					// Elementor's Shortcode widget stores content in
					// _elementor_data, not post_content, so has_shortcode()
					// above can't see it directly — check the fully-built
					// content too.
					if ( has_shortcode( apply_filters( 'the_content', $post->post_content ), $tag ) ) {
						return true;
					}
				}
			}
		}

		if ( is_active_widget( false, false, 'text', true ) || is_active_widget( false, false, 'shortcode', true ) ) {
			return true;
		}

		return false;
	}

	public static function enqueue() {
		if ( ! self::should_load_assets() ) {
			return;
		}

		wp_enqueue_style( 'rapm-swiper-css', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css', array(), '11.0' );
		wp_enqueue_style( 'rapm-hero-css', RAPM_URL . 'assets/css/rapm-hero.css', array( 'rapm-swiper-css' ), RAPM_VERSION );
		wp_enqueue_script( 'rapm-swiper-js', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js', array(), '11.0', true );
		wp_enqueue_script( 'rapm-schedule-js', RAPM_URL . 'assets/js/rapm-schedule.js', array( 'rapm-swiper-js' ), RAPM_VERSION, true );
	}

	/**
	 * If WP Rocket's "Delay JavaScript Execution" is on, it can hold
	 * Swiper back until the visitor scrolls/clicks, leaving the carousel
	 * as a static image stack until then. No-op on sites without WP Rocket.
	 */
	public static function exclude_from_rocket_delay( $exclusions ) {
		$exclusions[] = 'swiper-bundle';
		$exclusions[] = 'rapm-schedule';
		return $exclusions;
	}
}
