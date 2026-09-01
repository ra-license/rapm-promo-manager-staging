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
}
