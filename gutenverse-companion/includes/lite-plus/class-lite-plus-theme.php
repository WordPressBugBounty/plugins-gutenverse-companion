<?php
/**
 * Lite Plus Theme Class
 *
 * @package gutenverse-companion
 */

namespace Gutenverse_Companion\Lite_Plus;

use Gutenverse_Companion\Dashboard;
use Gutenverse_Companion\Helper;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Lite_Plus_Theme
 */
class Lite_Plus_Theme {

	/**
	 * Endpoint Path
	 *
	 * @var string
	 */
	const ENDPOINT = 'gtb-themes-backend/v1';

	/**
	 * Theme Slug
	 *
	 * @var string
	 */
	private $theme_slug = '';

	/**
	 * Constructor.
	 */
	public function __construct() {

		if ( ! apply_filters( 'gutenverse_tp_plus_mechanism', false ) && ! apply_filters( 'gutenverse_pro_plus_mechanism', false ) ) {
			return;
		}
		$this->theme_slug = get_option( 'stylesheet' );

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'admin_menu', array( $this, 'theme_wizard' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'theme_redirect' ), 99 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 99 );
	}

	/**
	 * Wizard Menu.
	 */
	public function theme_wizard() {
		if ( get_option( $this->theme_slug . '_lite_plus_wizard_setup_done' ) === 'yes' ) {
			return;
		}
		add_theme_page(
			'Wizard Setup',
			'Wizard Setup',
			'manage_options',
			$this->theme_slug . '-wizard',
			array( $this, 'theme_wizard_page' ),
			99
		);
	}

	/**
	 * Wizard Page.
	 */
	public function theme_wizard_page() {
		?>
		<div id="lite-plus-wizard"></div>
		<?php
	}

	/**
	 * Add Menu
	 */
	public function admin_menu() {
		$theme = wp_get_theme();
		$title = $theme->get( 'Name' );
		add_theme_page(
			$title . ' Dashboard',
			$title . ' Dashboard',
			'manage_options',
			$this->theme_slug . '-dashboard',
			array( $this, 'load_dashboard' ),
			1
		);
	}

	/**
	 * Template page
	 */
	public function load_dashboard() {
		?>
			<div id="lite-plus-dashboard">
			</div>
		<?php
	}

	/**
	 * Enqueue Scripts.
	 */
	public function enqueue_scripts() {
		if ( ! isset( $_GET['page'] ) || ! in_array( $_GET['page'], array( $this->theme_slug . '-dashboard', $this->theme_slug . '-wizard' ), true ) ) {
			return;
		}

		if ( is_admin() ) {
			$include = array_values(
				array_unique(
					array_merge(
						( include GUTENVERSE_COMPANION_DIR . '/lib/dependencies/lite-plus-dashboard.asset.php' )['dependencies'],
						array( 'wp-api-fetch' )
					)
				)
			);
			wp_enqueue_script( 'wp-api-fetch' );

			wp_enqueue_script(
				'gutenverse-lite-plus-theme',
				GUTENVERSE_COMPANION_URL . '/assets/js/lite-plus-dashboard.js',
				$include,
				GUTENVERSE_COMPANION_VERSION,
				true
			);

			wp_enqueue_style(
				'gutenverse-lite-plus-theme',
				GUTENVERSE_COMPANION_URL . '/assets/css/lite-plus-dashboard.css',
				array(),
				GUTENVERSE_COMPANION_VERSION
			);

			if ( ! $this->gutenverse_check_if_script_localized( 'GutenverseCompanionConfig' ) ) {
				$companion_dashboard = new Dashboard();
				$config              = $companion_dashboard->companion_config();
				wp_localize_script( 'gutenverse-lite-plus-theme', 'GutenverseCompanionConfig', $config );
			}
		}
		wp_enqueue_style(
			'gutenverse-companion-dashboard-inter-font',
			GUTENVERSE_COMPANION_URL . '/assets/dashboard-fonts/inter/inter.css',
			array(),
			GUTENVERSE_COMPANION_VERSION
		);

		wp_enqueue_style(
			'gutenverse-companion-dashboard-jakarta-sans-font',
			GUTENVERSE_COMPANION_URL . '/assets/dashboard-fonts/plus-jakarta-sans/plus-jakarta-sans.css',
			array(),
			GUTENVERSE_COMPANION_VERSION
		);
	}

	/**
	 * Check if script localized
	 *
	 * @param string $handle Handle.
	 *
	 * @return boolean
	 */
	public function gutenverse_check_if_script_localized( $handle ) {
		global $wp_scripts;

		if ( ! is_a( $wp_scripts, 'WP_Scripts' ) ) {
			return false;
		}

		if ( isset( $wp_scripts->registered[ $handle ] ) ) {
			$script = $wp_scripts->registered[ $handle ];
			if ( ! empty( $script->extra['data'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check parameter.
	 */
	private function is_wizard_done() {
		return isset( $_GET['page'] ) && isset( $_GET['wizard_setup_done'] ) && $_GET['page'] === $this->theme_slug . '-dashboard' && $_GET['wizard_setup_done'] === 'yes';
	}

	/**
	 * Theme Redirect.
	 */
	public function theme_redirect() {
		if ( $this->is_wizard_done() ) {
			update_option( $this->theme_slug . '_lite_plus_wizard_setup_done', 'yes' );
			wp_safe_redirect( admin_url( 'themes.php?page=' . $this->theme_slug . '-dashboard' ) );
		}

		if ( get_option( $this->theme_slug . '_lite_plus_wizard_init_done' ) !== 'yes' ) {
			update_option( $this->theme_slug . '_lite_plus_wizard_init_done', 'yes' );
			wp_safe_redirect( admin_url( 'themes.php?page=' . $this->theme_slug . '-wizard' ) );
			exit;
		}
	}

	/**
	 * Register APIs
	 */
	public function register_routes() {
		if ( ! is_admin() && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		/**
		 * Backend routes.
		 */

		$helper = new Helper();

		/**Assign Pages Normal */
		register_rest_route(
			self::ENDPOINT,
			'pages/assign',
			array(
				'methods'             => 'POST',
				'callback'            => function ( $request ) use ( $helper ) {
					return $helper->handle_pages( $request, true );
				},
				'permission_callback' => function () {
					if ( ! current_user_can( 'manage_options' ) ) {
						return new WP_Error(
							'forbidden_permission',
							esc_html__( 'Forbidden Access', 'gutenverse-companion' ),
							array( 'status' => 403 )
						);
					}

					return true;
				},
			)
		);
		/**Assign Pages News */
		register_rest_route(
			self::ENDPOINT,
			'pages/assign-news',
			array(
				'methods'             => 'POST',
				'callback'            => function ( $request ) use ( $helper ) {
					return $helper->handle_pages( $request, false );
				},
				'permission_callback' => function () {
					if ( ! current_user_can( 'manage_options' ) ) {
						return new WP_Error(
							'forbidden_permission',
							esc_html__( 'Forbidden Access', 'gutenverse-companion' ),
							array( 'status' => 403 )
						);
					}

					return true;
				},
			)
		);

		register_rest_route(
			self::ENDPOINT,
			'import/menus',
			array(
				'methods'             => 'GET',
				'callback'            => array( $helper, 'handle_menus' ),
				'permission_callback' => function () {
					if ( ! current_user_can( 'manage_options' ) ) {
						return new WP_Error(
							'forbidden_permission',
							esc_html__( 'Forbidden Access', 'gutenverse-companion' ),
							array( 'status' => 403 )
						);
					}

					return true;
				},
			)
		);

		register_rest_route(
			self::ENDPOINT,
			'import/posts',
			array(
				'methods'             => 'GET',
				'callback'            => array( $helper, 'import_posts' ),
				'permission_callback' => function () {
					if ( ! current_user_can( 'manage_options' ) ) {
						return new WP_Error(
							'forbidden_permission',
							esc_html__( 'Forbidden Access', 'gutenverse-companion' ),
							array( 'status' => 403 )
						);
					}

					return true;
				},
			)
		);

		register_rest_route(
			self::ENDPOINT,
			'install/plugins',
			array(
				'methods'             => 'POST',
				'callback'            => array( $helper, 'install_plugin' ),
				'permission_callback' => function () {
					if ( ! current_user_can( 'manage_options' ) ) {
						return new WP_Error(
							'forbidden_permission',
							esc_html__( 'Forbidden Access', 'gutenverse-companion' ),
							array( 'status' => 403 )
						);
					}

					return true;
				},
			)
		);

		register_rest_route(
			self::ENDPOINT,
			'delete/posts-dummies',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $helper, 'delete_dummies' ),
				'permission_callback' => function () {
					if ( ! current_user_can( 'manage_options' ) ) {
						return new WP_Error(
							'forbidden_permission',
							esc_html__( 'Forbidden Access', 'gutenverse-companion' ),
							array( 'status' => 403 )
						);
					}

					return true;
				},
			)
		);

		register_rest_route(
			self::ENDPOINT,
			'assign/remapping',
			array(
				'methods'             => 'GET',
				'callback'            => array( $helper, 'remapping_content_placeholder' ),
				'permission_callback' => function () {
					if ( ! current_user_can( 'manage_options' ) ) {
						return new WP_Error(
							'forbidden_permission',
							esc_html__( 'Forbidden Access', 'gutenverse-companion' ),
							array( 'status' => 403 )
						);
					}

					return true;
				},
			)
		);
	}
}
