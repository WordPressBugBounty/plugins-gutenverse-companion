<?php
/**
 * Dashboard class
 *
 * @author Jegstudio
 * @since 1.0.0
 * @package gutenverse-companion
 */

namespace Gutenverse_Companion;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Dashboard
 *
 * @package gutenverse-companion
 */
class Dashboard {
	/**
	 * Type
	 *
	 * @var string
	 */
	const TYPE = 'gutenverse-companion';

	/**
	 * Id
	 *
	 * @var id
	 */
	public $id;

	/**
	 * Init constructor.
	 */
	public function __construct() {
		$this->id = 'tabbed-template';
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 99 );
		if ( apply_filters( 'gutenverse_companion_base_theme', false ) ) {
			add_action( 'admin_menu', array( $this, 'parent_menu' ) );
			add_action( 'admin_menu', array( $this, 'child_menu' ) );
			add_action( 'admin_init', array( $this, 'companion_redirect' ), 99 );
		}
	}

	/**
	 * Theme redirect
	 */
	public function companion_redirect() {
		if ( get_option( 'gutenverse-companion_wizard_init_done' ) !== 'yes' ) {
			update_option( 'gutenverse-companion_wizard_init_done', 'yes' );
			wp_safe_redirect( admin_url( 'admin.php?page=gutenverse-companion' ) );
			exit;
		}
	}

	/**
	 * Enqueue scripts
	 */
	public function enqueue_scripts() {
		global $current_screen;

		if ( $current_screen->is_block_editor ) {
			return;
		}

		$include = ( include GUTENVERSE_COMPANION_DIR . '/lib/dependencies/wizard.asset.php' )['dependencies'];

		wp_enqueue_style(
			'gutenevrse-companion-wizard',
			GUTENVERSE_COMPANION_URL . '/assets/css/wizard.css',
			array(),
			GUTENVERSE_COMPANION_VERSION
		);

		wp_enqueue_script(
			'gutenverse-companion-wizard',
			GUTENVERSE_COMPANION_URL . '/assets/js/wizard.js',
			$include,
			GUTENVERSE_COMPANION_VERSION,
			true
		);

		wp_enqueue_script(
			'gutenverse-companion-dashboard',
			GUTENVERSE_COMPANION_URL . '/assets/js/companion.js',
			null,
			GUTENVERSE_COMPANION_VERSION,
			true
		);

		wp_localize_script(
			'gutenverse-companion-dashboard',
			'GutenverseRootConfig',
			$this->companion_config()
		);

		wp_localize_script( 'gutenverse-companion-wizard', 'GutenverseCompanionConfig', $this->companion_config() );
	}

	/**
	 * Account config.
	 */
	public function companion_config() {
		$config            = array(
			'dashboard'   => admin_url( 'admin.php?page=gutenverse-companion' ),
			'admin_url'   => admin_url(),
			'plugins_url' => plugins_url(),
			'images'      => GUTENVERSE_COMPANION_URL . '/assets/img',
			'upgradePro'  => 'https://gutenverse.com/pro',
			'theme_slug'  => wp_get_theme()->get_template(),
		);
		$config['plugins'] = self::list_plugin();

		return $config;
	}

	/**
	 * Get List Of Installed Plugin.
	 *
	 * @return array
	 */
	public static function list_plugin() {
		$plugins = array();
		$active  = array();

		foreach ( get_option( 'active_plugins' ) as  $plugin ) {
			$active[] = explode( '/', $plugin )[0];
		}

		foreach ( get_plugins() as $key => $plugin ) {
			$slug             = explode( '/', $key )[0];
			$data             = array();
			$data['active']   = in_array( $slug, $active, true );
			$data['version']  = $plugin['Version'];
			$data['name']     = $plugin['Name'];
			$data['path']     = str_replace( '.php', '', $key );
			$plugins[ $slug ] = $data;
		}

		return $plugins;
	}

	/**
	 * Gutenverse Dashboard Config
	 *
	 * @return array
	 */
	public function gutenverse_companion_dashboard_config() {
		$config = array();

		return apply_filters( 'gutenverse_companion_dashboard_config', $config );
	}

	/**
	 * Parent Menu
	 */
	public function parent_menu() {
		add_menu_page(
			esc_html__( 'Companion', 'gutenverse-companion' ),
			esc_html__( 'Companion', 'gutenverse-companion' ),
			'manage_options',
			self::TYPE,
			null,
			GUTENVERSE_COMPANION_URL . '/assets/img/icon-companion-dashboard.svg',
			30
		);
	}

	/**
	 * Child Menu
	 */
	public function child_menu() {
		add_submenu_page(
			self::TYPE,
			esc_html__( 'Wizard Setup', 'gutenverse-companion' ),
			esc_html__( 'Wizard Setup', 'gutenverse-companion' ),
			'manage_options',
			self::TYPE,
			array( $this, 'load_companion_wizard' ),
			1
		);
	}

	/**
	 * Load Gutenverse Pro Activation Page
	 */
	public function load_companion_wizard() {
		?>
		<div id="gutenverse-companion-wizard">
		</div>
		<?php
	}
}
