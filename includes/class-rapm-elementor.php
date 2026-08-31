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
}
