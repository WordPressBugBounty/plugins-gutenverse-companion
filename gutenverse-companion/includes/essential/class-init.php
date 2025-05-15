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
		add_action( 'admin_notices', array( $this, 'notice_theme_version' ), 99 );
	}

	/**
	 * Notice Theme Version
	 */
	public function notice_theme_version() {
		// skip if compatible.
		if ( defined( 'GUTENVERSE_FRAMEWORK_VERSION' ) ) {
			if ( defined( 'GUTENVERSE_FRAMEWORK_REQUIRED_VERSION' ) && version_compare( GUTENVERSE_FRAMEWORK_VERSION, GUTENVERSE_FRAMEWORK_REQUIRED_VERSION, '>=' ) ) {
				return;
			}
			?>
			<style>
				.notice.framework-version{
					border-left-color : #E72525;
					border-left-width: 4px;
					display: flex;
					padding: 10px;
					gap: 15px;
					margin-bottom: 20px;				
				}
				.notice.framework-version svg{
					margin-top: 15px;
				}
			</style>
		
			<div class="gutenverse-notice-inner">
				<div class="notice install-gutenverse-plugin-notice framework-version">
					<div class="notice-icon-wrapper">
						<svg width="40" height="40" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M40 20C40 31.0457 31.0457 40 20 40C8.9543 40 0 31.0457 0 20C0 8.9543 8.9543 0 20 0C31.0457 0 40 8.9543 40 20Z" fill="#FBE4E4"/>
						<path d="M20.6904 12.2624C20.4199 11.7511 19.5787 11.7511 19.3082 12.2624L12.2721 25.5528C12.2087 25.6719 12.1773 25.8054 12.1809 25.9402C12.1845 26.0751 12.223 26.2067 12.2926 26.3223C12.3622 26.4379 12.4606 26.5335 12.5781 26.5997C12.6956 26.666 12.8283 26.7007 12.9632 26.7004H27.0353C27.1702 26.7007 27.3028 26.666 27.4202 26.5998C27.5376 26.5336 27.6359 26.4381 27.7054 26.3226C27.775 26.207 27.8134 26.0755 27.8169 25.9407C27.8205 25.8059 27.789 25.6725 27.7257 25.5535L20.6904 12.2624ZM20.7811 24.3551H19.2175V22.7915H20.7811V24.3551ZM19.2175 21.2279V17.319H20.7811L20.7818 21.2279H19.2175Z" fill="#E72525"/>
						</svg>
					</div>
				
					<div class="gutenverse-notice-content">
						<h3><?php esc_html_e( 'Please Update Your Theme.', '--gctd--' ); ?></h3>
						<p><?php esc_html_e( 'You\'re currently using an older version of the theme.', '--gctd--' ); ?></p>					
						<p><?php esc_html_e( 'We recommend updating to the latest version to ensure full compatibility with the current version of Gutenverse. Using an outdated theme alongside a newer Gutenverse version may cause conflicts or issues on your site.', '--gctd--' ); ?></p>					
					</div>
				</div>
			</div>
			<?php
		}
	}
}
