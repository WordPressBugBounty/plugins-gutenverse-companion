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
use Gutenverse_Companion\Gutenverse_Theme\Gutenverse_Theme;
use Gutenverse_Companion\Lite_Plus\Lite_Plus_Theme;

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
	 * Hold instance of gutenverse theme
	 *
	 * @var GutenverseTheme
	 */
	public $gutenverse_theme;

	/**
	 * Hold instance of wizard
	 *
	 * @var Wizard
	 */
	public $wizard;

	/**
	 * Hold instance of lite plus theme
	 *
	 * @var Lite_Plus_Theme
	 */
	public $lite_plus_theme;

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
		add_action( 'init', array( $this, 'register_block_patterns' ), 9 );
		add_action( 'init', array( $this, 'activating_gutenverse_theme_dashboard' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_global_scripts' ), 99 );
		add_action( 'wp_enqueue_scripts', array( $this, 'remove_enqueue_default_style' ), 21 );
	}

	/**
	 * Remove Default Style when import default demo on Unibiz Theme
	 */
	public function remove_enqueue_default_style() {
		$theme        = wp_get_theme(); // omit slug to get current theme
		$demo_options = get_option( 'gutenverse-companion-imported-options', false );
		if ( $demo_options && isset( $demo_options['demo_id'] ) && 'default' !== $demo_options['demo_id'] && 'Unibiz' === $theme->get( 'Name' ) ) {
			wp_dequeue_style( 'unibiz-style' );
			wp_dequeue_style( 'preset' );
		}
	}
	/**
	 * Enqueue Global Script
	 */
	public function enqueue_global_scripts() {
		$include = ( include GUTENVERSE_COMPANION_DIR . '/lib/dependencies/notices.asset.php' )['dependencies'];

		wp_enqueue_script(
			'gutenverse-companion-notices',
			GUTENVERSE_COMPANION_URL . '/assets/js/notices.js',
			$include,
			GUTENVERSE_COMPANION_VERSION,
			true
		);
	}

	/**
	 * Activating Gutenverse Theme Dashboard
	 */
	public function activating_gutenverse_theme_dashboard() {
		if ( defined( 'GUTENVERSE_COMPANION_REQUIRED_VERSION' ) ) {
			$active_plugins = get_option( 'active_plugins' );
			$companion_ver  = null;
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
			foreach ( $active_plugins as $plugin_path ) {
				$slug = dirname( $plugin_path );
				if ( 'gutenverse-companion' === $slug ) {
					$plugin_data   = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_path );
					$companion_ver = $plugin_data['Version'];
				}
			}
			if ( $this->check_is_gutenverse_theme( $companion_ver ) ) {
				$this->gutenverse_theme = new Gutenverse_Theme();
			}
		}
	}

	/**
	 * Check if The theme is really a Gutenverse Theme (old)
	 *
	 * @param string $companion_ver Companion Version.
	 *
	 * @return bool
	 */
	public function check_is_gutenverse_theme( $companion_ver ) {
		return isset( $companion_ver ) && version_compare( $companion_ver, GUTENVERSE_COMPANION_REQUIRED_VERSION, '>=' ) && ! apply_filters( 'gutenverse_companion_base_theme', false ) && ! apply_filters( 'gutenverse_wporg_plus_mechanism', false ) && ! apply_filters( 'gutenverse_pro_plus_mechanism', false ) && ! apply_filters( 'gutenverse_tp_plus_mechanism', false );
	}


	/**
	 * Register Block Patterns.
	 */
	public function register_block_patterns() {
		$companion_data = get_option( 'gutenverse_companion_template_options', false );
		if ( ! isset( $companion_data['active_demo'] ) ) {
			return;
		}
		$slug         = strtolower( str_replace( ' ', '-', $companion_data['active_demo'] ) );
		$pattern_list = get_option( $slug . '_' . get_stylesheet() . '_companion_synced_pattern_imported', false );
		if ( ! $pattern_list ) {
			return;
		}
		foreach ( $pattern_list as $block_pattern ) {
			register_block_pattern(
				$block_pattern['slug'],
				$block_pattern
			);
		}
	}

	/**
	 * Change Stylesheet Directory.
	 *
	 * @param string $def Default Directory.
	 *
	 * @return string
	 */
	public function change_stylesheet_directory( $def ) {
		return isset( get_option( 'gutenverse_companion_template_options' )['template_dir'] ) ? get_option( 'gutenverse_companion_template_options' )['template_dir'] : $def;
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
			global $wp_version;
			if ( version_compare( $wp_version, '6.5', '>=' ) ) {
				add_filter( 'gutenverse_themes_override_mechanism', array( $this, 'is_change_stylesheet_directory' ) );
			} else {
				add_filter( 'gutenverse_template_path', array( $this, 'template_path' ), null, 3 );
				add_filter( 'gutenverse_themes_template', array( $this, 'add_template' ), 10, 2 );
				add_filter( 'gutenverse_themes_override_mechanism', '__return_false', 20 );
			}
			add_filter( 'gutenverse_stylesheet_directory', array( $this, 'change_stylesheet_directory' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'dashboard_enqueue_scripts' ) );
			add_action( 'wp_ajax_gutenverse_companion_notice_close', array( $this, 'companion_notice_close' ) );
			$this->dashboard = new Dashboard();
		}
		$this->lite_plus_theme = new Lite_Plus_Theme();
	}

	/**
	 * Add Template to Editor.
	 *
	 * @param array $template_files Path to Template File.
	 * @param array $template_type Template Type.
	 *
	 * @return array
	 */
	public function add_template( $template_files, $template_type ) {
		$dir = isset( get_option( 'gutenverse_companion_template_options' )['template_dir'] ) ? get_option( 'gutenverse_companion_template_options' )['template_dir'] : false;

		if ( ! $dir ) {
			return $template_files;
		}
		$template_files = array();
		if ( 'wp_template' === $template_type ) {
			$demo_template_path  = trailingslashit( $dir ) . 'templates/';
			$demo_template_files = glob( $demo_template_path . '*.html' );
			if ( $demo_template_files ) {
				foreach ( $demo_template_files as $file ) {
					$slug             = pathinfo( $file, PATHINFO_FILENAME );
					$template_files[] = array(
						'slug'  => $slug,
						'path'  => $file,
						'theme' => get_template(),
						'type'  => 'wp_template',
					);
				}
			}
		}

		if ( 'wp_template_part' === $template_type ) {
			$demo_part_path  = trailingslashit( $dir ) . 'parts/';
			$demo_part_files = glob( $demo_part_path . '*.html' );
			if ( $demo_part_files ) {
				foreach ( $demo_part_files as $file ) {
					$slug             = pathinfo( $file, PATHINFO_FILENAME );
					$template_files[] = array(
						'slug'  => $slug,
						'path'  => $file,
						'theme' => get_template(),
						'type'  => 'wp_template_part',
					);
				}
			}
		}
		return $template_files;
	}

	/**
	 * Use gutenverse template file instead.
	 *
	 * @param string $template_file Path to Template File.
	 * @param string $theme_slug Theme Slug.
	 * @param string $template_slug Template Slug.
	 *
	 * @return string
	 */
	public function template_path( $template_file, $theme_slug, $template_slug ) {
		$dir = isset( get_option( 'gutenverse_companion_template_options' )['template_dir'] ) ? get_option( 'gutenverse_companion_template_options' )['template_dir'] : false;

		if ( ! $dir ) {
			return $template_file;
		}

		$template_file = $this->get_template_path( $template_slug );

		return $template_file;
	}

	/**
	 * Get Template Path.
	 *
	 * @param string $template_slug Template Slug.
	 *
	 * @return string
	 */
	public function get_template_path( $template_slug ) {

		$dir = isset( get_option( 'gutenverse_companion_template_options' )['template_dir'] ) ? get_option( 'gutenverse_companion_template_options' )['template_dir'] : false;

		if ( ! $dir ) {
			return false;
		}

		$demo_template_path  = trailingslashit( $dir ) . 'templates/';
		$demo_template_files = glob( $demo_template_path . '*.html' );

		if ( $demo_template_files ) {
			foreach ( $demo_template_files as $file ) {
				$slug = pathinfo( $file, PATHINFO_FILENAME );
				if ( $template_slug === $slug ) {
					return $file;
				}
			}
		}

		$demo_part_path  = trailingslashit( $dir ) . 'parts/';
		$demo_part_files = glob( $demo_part_path . '*.html' );

		if ( $demo_part_files ) {
			foreach ( $demo_part_files as $file ) {
				$slug = pathinfo( $file, PATHINFO_FILENAME );
				if ( $template_slug === $slug ) {
					return $file;
				}
			}
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
	 * Change option page upgrade to true.
	 */
	public function companion_notice_close() {
		update_option( 'gutenverse-companion-base-theme-notice', true );
	}
}
