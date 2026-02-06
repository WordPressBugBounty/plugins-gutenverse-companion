<?php
/**
 * Gutenverse Theme class
 *
 * @author Jegstudio
 * @package gutenverse-companion
 */

namespace Gutenverse_Companion\Gutenverse_Theme;

use Gutenverse_Companion\Dashboard;
use Gutenverse_Companion\Helper;
use WP_Error;

/**
 * Class Gutenverse_Theme
 *
 * @package gutenverse-companion
 */
class Gutenverse_Theme {
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
	 * Blocks constructor.
	 */
	public function __construct() {
		$this->theme_slug = get_option( 'stylesheet' );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'admin_menu', array( $this, 'theme_wizard' ) );
		add_action( 'admin_init', array( $this, 'theme_redirect' ), 99 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 99 );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );

		/**Remove if Designer has make all theme to 100% */
		add_filter( 'wp_theme_json_data_theme', array( $this, 'layout_max_width_theme_json' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_script' ), 999 );
	}

	/**
	 * Overwrite layout max width css
	 *
	 * @return void
	 */
	public function frontend_script() {
		$global_settings = wp_get_global_settings();

		// Get layout sizes (may or may not exist) .
		$layout_content_size = isset( $global_settings['layout']['contentSize'] ) ? $global_settings['layout']['contentSize'] : null;
		$layout_wide_size    = isset( $global_settings['layout']['wideSize'] ) ? $global_settings['layout']['wideSize'] : null;

		/** Normalize and check values */
		$is_full_width = (
			( ! empty( $layout_content_size ) && '100%' === $layout_content_size ) ||
			( ! empty( $layout_wide_size ) && '100%' === $layout_wide_size )
		);

		// If not full width, enqueue a custom CSS to make it full width.
		if ( ! $is_full_width ) {
			wp_enqueue_style(
				'gutenverse-overwrite-layout',
				GUTENVERSE_COMPANION_URL . '/assets/css/companion-overwrite-layout.css',
				array(),
				GUTENVERSE_COMPANION_VERSION
			);
		}
	}

	/**
	 * Overwrite layout max width theme json (Remove after designer make it all theme to 100%)
	 *
	 * @param object $theme_json_data .
	 * @return object
	 */
	public function layout_max_width_theme_json( $theme_json_data ) {

		$theme_json = $theme_json_data->get_data();
		if ( is_object( $theme_json ) && method_exists( $theme_json, 'get_raw_data' ) ) {
			$data = $theme_json->get_raw_data();
		} else {
			$data = $theme_json;
		}

		if ( ! isset( $data['settings']['layout'] ) ) {
			$data['settings']['layout'] = array();
		}

		$data['settings']['layout']['contentSize'] = '100%';
		$data['settings']['layout']['wideSize']    = '100%';

		if ( ! isset( $data['styles'] ) ) {
			$data['styles'] = array();
		}
		$data['styles']['layout'] = array(
			'contentSize' => '100%',
			'wideSize'    => '100%',
		);
		$theme_json_data->update_with( $data );
		return $theme_json_data;
	}

	/**
	 * Enqueue Scripts.
	 */
	public function enqueue_scripts() {
		if ( is_admin() ) {
			$include = array_values(
				array_unique(
					array_merge(
						( include GUTENVERSE_COMPANION_DIR . '/lib/dependencies/gutenverse-theme-wizard.asset.php' )['dependencies'],
						array( 'wp-api-fetch' )
					)
				)
			);
			wp_enqueue_script( 'wp-api-fetch' );

			wp_enqueue_style(
				'gutenverse-companion-gutenverse-theme-wizard',
				GUTENVERSE_COMPANION_URL . '/assets/css/gutenverse-theme-wizard.css',
				array(),
				GUTENVERSE_COMPANION_VERSION
			);

			wp_enqueue_script(
				'gutenverse-companion-gutenverse-theme-wizard',
				GUTENVERSE_COMPANION_URL . '/assets/js/gutenverse-theme-wizard.js',
				$include,
				GUTENVERSE_COMPANION_VERSION,
				true
			);
			if ( ! $this->gutenverse_check_if_script_localized( 'GutenverseCompanionConfig' ) ) {
				$companion_dashboard = new Dashboard();
				$config              = $companion_dashboard->companion_config();
				wp_localize_script( 'gutenverse-companion-gutenverse-theme-wizard', 'GutenverseCompanionConfig', $config );
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
	 * Wizard Menu.
	 */
	public function theme_wizard() {
		add_theme_page(
			get_option( $this->theme_slug . '_wizard_setup_done' ) !== 'yes' ? 'Wizard Setup' : '',
			'Wizard Setup',
			'manage_options',
			'theme-wizard',
			array( $this, 'theme_wizard_page' ),
			99
		);
	}

	/**
	 * Wizard Page.
	 */
	public function theme_wizard_page() {
		?>
		<div id="gutenverse-theme-wizard"></div>
		<?php
	}

	/**
	 * Add Menu
	 */
	public function admin_menu() {
		$theme = wp_get_theme();
		$title = $theme->get( 'Name' );
		$slug  = $theme->get_template();
		add_theme_page(
			$title . ' Dashboard',
			$title . ' Dashboard',
			'manage_options',
			$slug . '-dashboard',
			array( $this, 'load_dashboard' ),
			1
		);
	}

	/**
	 * Template page
	 */
	public function load_dashboard() {
		?>
			<div id="gutenverse-theme-dashboard">
			</div>
		<?php
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
		if ( ! apply_filters( 'gutenverse_companion_base_theme', false ) ) {
			if ( $this->is_wizard_done() ) {
				update_option( $this->theme_slug . '_wizard_setup_done', 'yes' );
				wp_safe_redirect( admin_url( 'themes.php?page=' . $this->theme_slug . '-dashboard' ) );
			}

			if ( get_option( $this->theme_slug . '_wizard_init_done' ) !== 'yes' ) {

				update_option( $this->theme_slug . '_wizard_init_done', 'yes' );
				wp_safe_redirect( admin_url( 'admin.php?page=theme-wizard' ) );
				exit;
			}
		}
	}

	/**
	 * Register APIs
	 */
	public function register_routes() {
		if ( ! is_admin() && ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$helper = new Helper();
		/**
		 * Backend routes.
		 */

		// Themes.
		register_rest_route(
			self::ENDPOINT,
			'pages/assign',
			array(
				'methods'             => 'POST',
				'callback'            => function($request) use ( $helper ) {
					return $helper->handle_pages($request, true);
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
	}

	
}