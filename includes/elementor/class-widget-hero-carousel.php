<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAPM_Widget_Hero_Carousel extends \Elementor\Widget_Base {

	public function get_name() {
		return 'rapm_hero_carousel';
	}

	public function get_title() {
		return __( 'Promo Carousel', 'rapm' );
	}

	public function get_icon() {
		return 'eicon-slider-push';
	}

	public function get_categories() {
		return array( 'rapm-promo' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_content',
			array( 'label' => __( 'Content', 'rapm' ) )
		);

		$this->add_control(
			'kind',
			array(
				'label'   => __( 'Kind', 'rapm' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'hero',
				'options' => array(
					'hero'        => __( 'Hero (full carousel)', 'rapm' ),
					'fold_banner' => __( 'Fold Banner (shorter, near the fold)', 'rapm' ),
				),
			)
		);

		$this->add_control(
			'placement',
			array(
				'label'       => __( 'Placement', 'rapm' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => 'default',
				'description' => __( 'Matches the "Placement" field set on each asset under Promo > Add New Asset.', 'rapm' ),
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$tag      = 'fold_banner' === $settings['kind'] ? 'rapm_fold_banner' : 'rapm_hero';
		echo do_shortcode( '[' . $tag . ' placement="' . esc_attr( $settings['placement'] ) . '"]' );
	}
}
