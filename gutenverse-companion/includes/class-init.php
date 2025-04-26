<?php
/**
 * Gutenverse Companion Main class
 *
 * @author Jegstudio
 * @since 1.0.0
 * @package gutenverse
 */

namespace Gutenverse_Companion;

use Gutenverse_Companion\Essential\Init as EssentialInit;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Init
 *
 * @package gutenverse-companion
 */
class Init {
	/**
	 * Instance of Init.
	 *
	 * @var Init
	 */
	private static $instance;

	/**
	 * Hold instance of dashboard
	 *
	 * @var Dashboard
	 */
	public $dashboard;

	/**
	 * Hold instance of essential
	 *
	 * @var Essential
	 */
	public $essential;

	/**
	 * Hold instance of wizard
	 *
	 * @var Wizard
	 */
	public $wizard;

	/**
	 * Hold API Variable Instance.
	 *
	 * @var Api
	 */
	public $api;

	/**
	 * Singleton page for Init Class
	 *
	 * @return Gutenverse
	 */
	public static function instance() {
		if ( null === static::$instance ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	/**
	 * Init constructor.
	 */
	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'init_api' ) );
		add_action( 'after_setup_theme', array( $this, 'plugin_loaded' ) );
	}

	/**
	 * Change Stylesheet Directory.
	 *
	 * @return string
	 */
	public function change_stylesheet_directory() {
		return isset( get_option( 'gutenverse_companion_template_options' )['template_dir'] ) ? get_option( 'gutenverse_companion_template_options' )['template_dir'] : 0;
	}

	/**
	 * Enable Override Stylesheet Directory.
	 *
	 * @return mixed
	 */
	public function is_change_stylesheet_directory() {
		return (bool) get_option( 'gutenverse_companion_template_options' ) && isset( get_option( 'gutenverse_companion_template_options' )['active_theme'] ) && get_option( 'gutenverse_companion_template_options' )['active_theme'] === wp_get_theme()->get_template();
	}

	/**
	 * Plugin Loaded.
	 */
	public function plugin_loaded() {
		if ( apply_filters( 'jeg_theme_essential_mode_on', false ) || apply_filters( 'gutenverse_companion_essential_mode_on', false ) ) {
			$this->essential = new EssentialInit();
		} else {
			add_filter( 'gutenverse_stylesheet_directory', array( $this, 'change_stylesheet_directory' ) );
			add_filter( 'gutenverse_themes_override_mechanism', array( $this, 'is_change_stylesheet_directory' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'dashboard_enqueue_scripts' ) );
			add_action( 'wp_ajax_gutenverse_companion_notice_close', array( $this, 'companion_notice_close' ) );
			add_action( 'admin_notices', array( $this, 'theme_install_notice' ) );
			$this->dashboard = new Dashboard();

		}
	}

	/**
	 * Init Rest API
	 */
	public function init_api() {
		$this->api = Api::instance();
	}

	/**
	 * Dashboard scripts.
	 */
	public function dashboard_enqueue_scripts() {
		if ( current_user_can( 'manage_options' ) && ! get_option( 'gutenverse-companion-base-theme-notice' ) ) {
			wp_enqueue_script(
				'notice-script',
				GUTENVERSE_COMPANION_URL . '/assets/admin/js/notice.js',
				array(),
				GUTENVERSE_COMPANION_NOTICE_VERSION,
				true
			);
		}
	}

	/**
	 * Admin Notice for page upgrade.
	 */
	public function theme_install_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$done = get_option( 'gutenverse-companion-base-theme-notice' );

		if ( ! $done ) {
			?>
			<div class="notice gutenverse-upgrade-notice page-content-upgrade">
				<div class="notice-logo">
					<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M10 0C4.47754 0 0 4.47915 0 10C0 15.5241 4.47754 20 10 20C15.5225 20 20 15.5241 20 10C20 4.47915 15.5225 0 10 0ZM10 4.43548C10.9353 4.43548 11.6935 5.19371 11.6935 6.12903C11.6935 7.06435 10.9353 7.82258 10 7.82258C9.06468 7.82258 8.30645 7.06435 8.30645 6.12903C8.30645 5.19371 9.06468 4.43548 10 4.43548ZM12.2581 14.6774C12.2581 14.9446 12.0414 15.1613 11.7742 15.1613H8.22581C7.95859 15.1613 7.74194 14.9446 7.74194 14.6774V13.7097C7.74194 13.4425 7.95859 13.2258 8.22581 13.2258H8.70968V10.6452H8.22581C7.95859 10.6452 7.74194 10.4285 7.74194 10.1613V9.19355C7.74194 8.92633 7.95859 8.70968 8.22581 8.70968H10.8065C11.0737 8.70968 11.2903 8.92633 11.2903 9.19355V13.2258H11.7742C12.0414 13.2258 12.2581 13.4425 12.2581 13.7097V14.6774Z" fill="#FFC908"/>
					</svg>
				</div>
				<div class="notice-content">
					<h2><?php esc_html_e( 'Action Required - Install Gutenverse Theme', 'gutenverse-companion' ); ?></h2>
					<p><?php echo esc_html__( 'Gutenverse Companion is the complementary plugin to Gutenverse theme. It adds a bunch of great features to the theme and acts as an unlocker for the Gutenverse Pro package. In order to take full advantage of all features it has to offer - please install and activate the Gutenverse theme also.', 'gutenverse-companion' ); ?></p>
					<div class="gutenverse-companion-notice-action">
						<a class='close-notification' href="#"><?php esc_html_e( 'Close notification', 'gutenverse-companion' ); ?></a>
					</div>
				</div>
			</div>
			<?php
		}
	}

	/**
	 * Change option page upgrade to true.
	 */
	public function companion_notice_close() {
		update_option( 'gutenverse-companion-base-theme-notice', true );
	}
}
