<?php
/**
 * Init Class
 *
 * @author Jegstudio
 * @since 1.0.0
 * @package gutenverse-companion
 */

namespace Gutenverse_Companion\Essential;

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
	protected static $instance;

	/**
	 * Hold instance of assets
	 *
	 * @var Assets
	 */
	public $assets;

	/**
	 * Style Generator
	 *
	 * @var Style_Generator
	 */
	public $style_generator;

	/**
	 * Instance of Blocks.
	 *
	 * @var Blocks
	 */
	protected $blocks;

	/**
	 * API
	 *
	 * @var API
	 */
	public $api;

	/**
	 * Singleton page for Init Class
	 *
	 * @return Init
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
	public function __construct() {
		/**
		 * 'jeg_theme_essential_mode_on' deprecated since version 1.0.1 Use 'gutenverse_companion_essential_mode_on' instead.
		 */
		if ( class_exists( 'Gutenverse_Initialize_Framework' ) ) {
			$this->init_hook();
		}
	}

	/**
	 * Initialize Class.
	 */
	public function init_class() {
		if ( ! class_exists( '\Gutenverse\Pro\License' ) ) {
			$this->blocks          = new Blocks();
			$this->style_generator = new Style_Generator();
		}
		$this->assets = new Assets();
		$this->api    = new Api();
	}

	/**
	 * Init Hook
	 */
	public function init_hook() {
		add_action( 'gutenverse_after_init_framework', array( $this, 'init_class' ) );
	}
}
