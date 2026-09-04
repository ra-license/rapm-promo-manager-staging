<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAPM_Elementor {

	public static function register_category( $elements_manager ) {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			return;
		}
		$elements_manager->add_category(
			'rapm-promo',
			array(
				'title' => __( 'Promo Manager', 'rapm' ),
				'icon'  => 'fa fa-plug',
			)
		);
	}

	public static function register_widgets( $widgets_manager ) {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			return;
		}
		require_once RAPM_DIR . 'includes/elementor/class-widget-hero-carousel.php';
		$widgets_manager->register( new RAPM_Widget_Hero_Carousel() );
	}

	/**
	 * The site's own Elementor Global Fonts (Site Settings > Global Fonts —
	 * the 4 built-in slots plus any custom-named ones an editor added),
	 * keyed by their stable _id ('primary'/'secondary'/'text'/'accent' for
	 * the built-ins, an auto-generated id for custom ones) and valued by
	 * their human-readable title — exactly the name shown in Elementor's
	 * own editor, so an account manager recognizes it. Lets a promotion's
	 * text use the same typeface as the rest of the site instead of a
	 * fixed preset, and stay in sync automatically if that font is ever
	 * changed in Elementor (see RAPM_Hero_Carousel — the chosen id is
	 * rendered as a CSS var() reference, never a resolved font name).
	 *
	 * Empty on any site without Elementor active, or one where Elementor
	 * is active but no kit has ever been touched.
	 */
	public static function global_fonts() {
		if ( ! did_action( 'elementor/loaded' ) || ! class_exists( '\Elementor\Plugin' ) ) {
			return array();
		}
		$kits_manager = \Elementor\Plugin::$instance->kits_manager;
		if ( ! $kits_manager ) {
			return array();
		}
		$kit = $kits_manager->get_active_kit_for_frontend();
		if ( ! $kit ) {
			return array();
		}

		$entries = array_merge(
			(array) $kit->get_settings( 'system_typography' ),
			(array) $kit->get_settings( 'custom_typography' )
		);

		$fonts = array();
		foreach ( $entries as $entry ) {
			if ( ! empty( $entry['_id'] ) && ! empty( $entry['title'] ) ) {
				$fonts[ $entry['_id'] ] = $entry['title'];
			}
		}
		return $fonts;
	}

	/**
	 * The literal font-family name behind each global font id (e.g.
	 * 'primary' => 'Roboto'). Only for the Add/Edit Asset form's own Live
	 * Preview, which renders inside wp-admin — Elementor's
	 * --e-global-typography-*-font-family CSS variables are only ever
	 * declared on the real front end (scoped to body.elementor-kit-{id}),
	 * so they don't exist for a preview box inside wp-admin to read. The
	 * actual public-facing render (RAPM_Hero_Carousel etc.) always uses
	 * the var() reference from font_family_css() instead, never this, so
	 * it keeps auto-syncing with Elementor rather than freezing a name.
	 */
	public static function font_family_map() {
		if ( ! did_action( 'elementor/loaded' ) || ! class_exists( '\Elementor\Plugin' ) ) {
			return array();
		}
		$kits_manager = \Elementor\Plugin::$instance->kits_manager;
		if ( ! $kits_manager ) {
			return array();
		}
		$kit = $kits_manager->get_active_kit_for_frontend();
		if ( ! $kit ) {
			return array();
		}

		$entries = array_merge(
			(array) $kit->get_settings( 'system_typography' ),
			(array) $kit->get_settings( 'custom_typography' )
		);

		$map = array();
		foreach ( $entries as $entry ) {
			if ( ! empty( $entry['_id'] ) && ! empty( $entry['typography_font_family'] ) ) {
				$map[ $entry['_id'] ] = $entry['typography_font_family'];
			}
		}
		return $map;
	}

	/**
	 * A `font-family` CSS declaration referencing an Elementor global
	 * font's own live CSS variable — never a resolved/hardcoded font
	 * name, so it keeps tracking whatever that font is set to in
	 * Elementor even if it's changed later. Empty string if no font_id
	 * given (falls back to whatever the surrounding CSS already sets).
	 */
	public static function font_family_css( $font_id ) {
		if ( ! $font_id ) {
			return '';
		}
		return 'font-family:var(--e-global-typography-' . sanitize_html_class( $font_id ) . '-font-family, inherit);';
	}

	/**
	 * The site's own Elementor Global Colors (Site Settings > Global
	 * Colors — the 4 built-in slots plus any custom-named ones an editor
	 * added), keyed by _id and valued by their human-readable title.
	 * Same shape and same reasoning as global_fonts() above. Empty on any
	 * site without Elementor active, or one where no kit has been
	 * touched.
	 */
	public static function global_colors() {
		if ( ! did_action( 'elementor/loaded' ) || ! class_exists( '\Elementor\Plugin' ) ) {
			return array();
		}
		$kits_manager = \Elementor\Plugin::$instance->kits_manager;
		if ( ! $kits_manager ) {
			return array();
		}
		$kit = $kits_manager->get_active_kit_for_frontend();
		if ( ! $kit ) {
			return array();
		}

		$entries = array_merge(
			(array) $kit->get_settings( 'system_colors' ),
			(array) $kit->get_settings( 'custom_colors' )
		);

		$colors = array();
		foreach ( $entries as $entry ) {
			if ( ! empty( $entry['_id'] ) && ! empty( $entry['title'] ) ) {
				$colors[ $entry['_id'] ] = $entry['title'];
			}
		}
		return $colors;
	}

	/**
	 * The literal hex value behind each global color id — for admin-side
	 * previews only (Settings' "detected" hint), same reasoning as
	 * font_family_map(): Elementor's --e-global-color-* variables are
	 * only ever declared on the real front end.
	 */
	public static function color_value_map() {
		if ( ! did_action( 'elementor/loaded' ) || ! class_exists( '\Elementor\Plugin' ) ) {
			return array();
		}
		$kits_manager = \Elementor\Plugin::$instance->kits_manager;
		if ( ! $kits_manager ) {
			return array();
		}
		$kit = $kits_manager->get_active_kit_for_frontend();
		if ( ! $kit ) {
			return array();
		}

		$entries = array_merge(
			(array) $kit->get_settings( 'system_colors' ),
			(array) $kit->get_settings( 'custom_colors' )
		);

		$map = array();
		foreach ( $entries as $entry ) {
			if ( ! empty( $entry['_id'] ) && ! empty( $entry['color'] ) ) {
				$map[ $entry['_id'] ] = $entry['color'];
			}
		}
		return $map;
	}

	/**
	 * The one value every front-end accent color in this plugin (right
	 * now: the Promotions Calendar) should resolve to, in priority order:
	 * 1) an explicit hex typed under Promo Manager > Settings — a
	 *    deliberate human choice always wins, since Elementor's "Accent"
	 *    or "Primary" slot isn't guaranteed to be the color a site
	 *    actually wants used here;
	 * 2) Elementor's own Global "Accent" color if set, else "Primary" —
	 *    auto-detected via a live var() reference (not a resolved hex),
	 *    so it keeps tracking Elementor if that color is ever changed;
	 * 3) $fallback, on any site with neither.
	 */
	public static function resolve_accent_color_css( $fallback = '#b5651d' ) {
		$manual = trim( (string) RAPM_Admin_Settings::get( 'accent_color' ) );
		if ( $manual ) {
			return $manual;
		}
		$colors  = self::global_colors();
		$auto_id = isset( $colors['accent'] ) ? 'accent' : ( isset( $colors['primary'] ) ? 'primary' : '' );
		if ( $auto_id ) {
			return 'var(--e-global-color-' . sanitize_html_class( $auto_id ) . ', ' . $fallback . ')';
		}
		return $fallback;
	}
}
