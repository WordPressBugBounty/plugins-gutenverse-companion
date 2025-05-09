<?php
/**
 * Jeg Theme Essential Style Default
 *
 * @author Jegstudio
 * @since 1.0.0
 * @package gutenverse-companion
 */

namespace Gutenverse_Companion\Essential\Style;

use Gutenverse\Framework\Style_Abstract;

/**
 * Class Style Default
 *
 * @package gutenverse-companion
 */
class Style_Default extends Style_Abstract {
	/**
	 * Block Directory
	 *
	 * @var string
	 */
	protected $block_dir = '';

	/**
	 * Generate Element.
	 */
	protected function generate() {
		// Do nothing.
	}

	/**
	 * The Constructor
	 */
	public function __construct() {
		$directory = apply_filters( 'gutenverse_companion_essential_assets_directory', false );
		/**
		 * 'jeg_theme_essential_assets_directory' deprecated since version 1.0.1 Use 'gutenverse_companion_essential_assets_directory' instead.
		 */
		if ( ! $directory ) {
			$directory = apply_filters( 'jeg_theme_essential_assets_directory', false );
		}
		if ( $directory ) {
			$this->block_dir = $directory . '/block/essential/';
		}
	}
	/**
	 * Handle Transform.
	 *
	 * @param string $selector selector.
	 * @param array  $transform Value of Transform.
	 */
	public function handle_transform( $selector, $transform ) {
		foreach ( $transform as $key => $data ) {
			if ( is_array( $data ) && 'duration' === $key ) {
				$is_empty   = 0;
				$count_data = count( $data );
				foreach ( $data as $value ) {
					if ( ! $value ) {
						++$is_empty;
					}
				}
				if ( $count_data === $is_empty ) {
					$transform[ $key ] = array(
						'Desktop' => '0.4',
					);
				}
			}
		}
		$this->inject_style(
			array(
				'selector'       => "{$selector}",
				'property'       => function ( $value ) {
					$value_ = ! gutenverse_truly_empty( $value ) ? $value : '0.4';
					return "transition: transform {$value_}s, opacity {$value_}s;";
				},
				'value'          => ! empty( $transform['duration'] ) ? $transform['duration'] : array(),
				'device_control' => true,
			)
		);
		$this->inject_style(
			array(
				'selector'       => "{$selector}",
				'property'       => function ( $value ) {
					$value_ = $value;
					return "transition-delay:{$value_}s;";
				},
				'value'          => ! empty( $transform['delay'] ) ? $transform['delay'] : array(),
				'device_control' => true,
			)
		);

		if ( isset( $transform['ease'] ) && ! empty( $transform['ease'] ) ) {
			$this->inject_style(
				array(
					'selector'       => "{$selector}",
					'property'       => function ( $value ) {
						$value_ = ! gutenverse_truly_empty( $value ) ? $value : 'linear';
						return "transition-timing-function: {$value_};";
					},
					'value'          => $transform['ease'],
					'device_control' => true,
				)
			);
		}

		if ( isset( $transform['transformOrigin'] ) && ! empty( $transform['transformOrigin'] ) ) {
			$this->inject_style(
				array(
					'selector'       => "{$selector}",
					'property'       => function ( $value ) {
						return "transform-origin: {$value};";
					},
					'value'          => $transform['transformOrigin'],
					'device_control' => true,
				)
			);
		}
		$transform_values = $this->multi_style_values_all_value(
			array(
				array(
					'value' => isset( $transform['perspective'] ) ? $transform['perspective'] : null,
					'style' => function ( $value ) {
						return "perspective({$value['point']}{$value['unit']})";
					},
				),
				array(
					'value' => isset( $transform['rotateZ'] ) ? $transform['rotateZ'] : null,
					'style' => function ( $value ) {
						return "rotate({$value['point']}{$value['unit']})";
					},
				),
				array(
					'value' => isset( $transform['rotateX'] ) ? $transform['rotateX'] : null,
					'style' => function ( $value ) {
						return "rotateX({$value['point']}{$value['unit']})";
					},
				),
				array(
					'value' => isset( $transform['rotateY'] ) ? $transform['rotateY'] : null,
					'style' => function ( $value ) {
						return "rotateY({$value['point']}{$value['unit']})";
					},
				),
				array(
					'value' => isset( $transform['scaleX'] ) ? $transform['scaleX'] : null,
					'style' => function ( $value ) {
						return "scaleX({$value})";
					},
				),
				array(
					'value' => isset( $transform['scaleY'] ) ? $transform['scaleY'] : null,
					'style' => function ( $value ) {
						return "scaleY({$value})";
					},
				),
				array(
					'value' => isset( $transform['moveZ'] ) ? $transform['moveZ'] : null,
					'style' => function ( $value ) {
						return "translateZ({$value['point']}{$value['unit']})";
					},
				),
				array(
					'value' => isset( $transform['moveX'] ) ? $transform['moveX'] : null,
					'style' => function ( $value ) {
						return "translateX({$value['point']}{$value['unit']})";
					},
				),
				array(
					'value' => isset( $transform['moveY'] ) ? $transform['moveY'] : null,
					'style' => function ( $value ) {
						return "translateY({$value['point']}{$value['unit']})";
					},
				),
				array(
					'value' => isset( $transform['skewX'] ) ? $transform['skewX'] : null,
					'style' => function ( $value ) {
						return "skewX({$value['point']}{$value['unit']})";
					},
				),
				array(
					'value' => isset( $transform['skewY'] ) ? $transform['skewY'] : null,
					'style' => function ( $value ) {
						return "skewY({$value['point']}{$value['unit']})";
					},
				),
			)
		);
		$this->inject_style(
			array(
				'selector'       => "{$selector}",
				'property'       => function ( $value ) {
					return "transform: {$value};";
				},
				'value'          => $transform_values,
				'device_control' => true,
			)
		);

		if ( isset( $transform['opacity'] ) && ! empty( $transform['opacity'] ) ) {
			$this->inject_style(
				array(
					'selector'       => "{$selector}",
					'property'       => function ( $value ) {
						$value_ = ! gutenverse_truly_empty( $value ) ? $value : '1';
						return "opacity: {$value_};";
					},
					'value'          => $transform['opacity'],
					'device_control' => false,
				)
			);
		}
	}

	/**
	 * Handle Transform Hover.
	 *
	 * @param string $selector selector.
	 * @param array  $transform Value of Transform.
	 */
	public function handle_transform_hover( $selector, $transform ) {
		$transform_values = $this->multi_style_values(
			array(
				array(
					'value' => isset( $transform['perspectiveHover'] ) ? $transform['perspectiveHover'] : null,
					'style' => function ( $value ) {
						return "perspective({$value['point']}{$value['unit']})";
					},
				),
				array(
					'value' => isset( $transform['rotateZHover'] ) ? $transform['rotateZHover'] : null,
					'style' => function ( $value ) {
						return "rotate({$value['point']}{$value['unit']})";
					},
				),
				array(
					'value' => isset( $transform['rotateXHover'] ) ? $transform['rotateXHover'] : null,
					'style' => function ( $value ) {
						return "rotateX({$value['point']}{$value['unit']})";
					},
				),
				array(
					'value' => isset( $transform['rotateYHover'] ) ? $transform['rotateYHover'] : null,
					'style' => function ( $value ) {
						return "rotateY({$value['point']}{$value['unit']})";
					},
				),
				array(
					'value' => isset( $transform['scaleXHover'] ) ? $transform['scaleXHover'] : null,
					'style' => function ( $value ) {
						return "scaleX({$value})";
					},
				),
				array(
					'value' => isset( $transform['scaleYHover'] ) ? $transform['scaleYHover'] : null,
					'style' => function ( $value ) {
						return "scaleY({$value})";
					},
				),
				array(
					'value' => isset( $transform['moveZHover'] ) ? $transform['moveZHover'] : null,
					'style' => function ( $value ) {
						return "translateZ({$value['point']}{$value['unit']})";
					},
				),
				array(
					'value' => isset( $transform['moveXHover'] ) ? $transform['moveXHover'] : null,
					'style' => function ( $value ) {
						return "translateX({$value['point']}{$value['unit']})";
					},
				),
				array(
					'value' => isset( $transform['moveYHover'] ) ? $transform['moveYHover'] : null,
					'style' => function ( $value ) {
						return "translateY({$value['point']}{$value['unit']})";
					},
				),
				array(
					'value' => isset( $transform['skewXHover'] ) ? $transform['skewXHover'] : null,
					'style' => function ( $value ) {
						return "skewX({$value['point']}{$value['unit']})";
					},
				),
				array(
					'value' => isset( $transform['skewYHover'] ) ? $transform['skewYHover'] : null,
					'style' => function ( $value ) {
						return "skewY({$value['point']}{$value['unit']})";
					},
				),
			)
		);

		if ( isset( $transform['opacityHover'] ) && ! gutenverse_truly_empty( $transform['opacityHover'] ) ) {
			$this->inject_style(
				array(
					'selector'       => "{$selector}",
					'property'       => function ( $value ) {
						return "opacity: {$value};";
					},
					'value'          => $transform['opacityHover'],
					'device_control' => false,
				)
			);
		}
		$this->inject_style(
			array(
				'selector'       => "{$selector}",
				'property'       => function ( $value ) {
					return "transform: {$value};";
				},
				'value'          => $transform_values,
				'device_control' => true,
			)
		);
	}
}
