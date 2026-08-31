<?php
/**
 * Plugin Name: RA Promo Manager
 * Description: Validated, scheduled promotional assets (hero banners and more) for client sites — enforces correct image dimensions/format/size on upload, schedules reliably even behind full-page caching, and links out to WordPress content, Elementor pages, or WooCommerce products/categories. Shortcode: [rapm_hero placement="default"].
 * Version: 1.1.1
 * Author: RA Marketing
 * Text Domain: rapm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RAPM_VERSION', '1.1.1' );
define( 'RAPM_DIR', plugin_dir_path( __FILE__ ) );
define( 'RAPM_URL', plugin_dir_url( __FILE__ ) );

require_once RAPM_DIR . 'includes/class-rapm-post-types.php';
require_once RAPM_DIR . 'includes/class-rapm-slots.php';
require_once RAPM_DIR . 'includes/class-rapm-webp-converter.php';
require_once RAPM_DIR . 'includes/class-rapm-destination.php';
require_once RAPM_DIR . 'includes/class-rapm-admin-settings.php';
require_once RAPM_DIR . 'includes/class-rapm-upload-handler.php';
require_once RAPM_DIR . 'includes/class-rapm-admin-list.php';
require_once RAPM_DIR . 'includes/class-rapm-hero-carousel.php';
require_once RAPM_DIR . 'includes/class-rapm-elementor.php';

final class RAPM_Plugin {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		add_action( 'init', array( 'RAPM_Post_Types', 'register' ) );

		add_filter( 'manage_rapm_asset_posts_columns', array( 'RAPM_Admin_List', 'columns' ) );
		add_action( 'manage_rapm_asset_posts_custom_column', array( 'RAPM_Admin_List', 'column_content' ), 10, 2 );

		add_action( 'admin_menu', array( 'RAPM_Upload_Handler', 'add_menu' ) );
		add_action( 'admin_init', array( 'RAPM_Upload_Handler', 'maybe_redirect_native_add_new' ) );
		add_action( 'admin_enqueue_scripts', array( 'RAPM_Upload_Handler', 'enqueue_admin_assets' ) );
		add_action( 'admin_post_rapm_save_asset', array( 'RAPM_Upload_Handler', 'handle_save' ) );

		add_action( 'admin_menu', array( 'RAPM_Admin_Settings', 'add_menu' ) );
		add_action( 'admin_init', array( 'RAPM_Admin_Settings', 'register_settings' ) );

		add_shortcode( 'rapm_hero', array( 'RAPM_Hero_Carousel', 'shortcode_hero' ) );
		add_shortcode( 'rapm_fold_banner', array( 'RAPM_Hero_Carousel', 'shortcode_fold_banner' ) );
		add_action( 'wp_enqueue_scripts', array( 'RAPM_Hero_Carousel', 'enqueue' ) );
		add_filter( 'rocket_delay_js_exclusions', array( 'RAPM_Hero_Carousel', 'exclude_from_rocket_delay' ) );

		add_action( 'elementor/widgets/register', array( 'RAPM_Elementor', 'register_widgets' ) );
		add_action( 'elementor/elements/categories_registered', array( 'RAPM_Elementor', 'register_category' ) );
	}

	public function activate() {
		RAPM_Post_Types::register();
		flush_rewrite_rules();
	}

	public function deactivate() {
		flush_rewrite_rules();
	}
}

RAPM_Plugin::instance();
