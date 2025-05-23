<?php
/**
 * Style Generator Class
 *
 * @author Jegstudio
 * @since 1.0.0
 * @package gutenverse-companion
 */

namespace Gutenverse_Companion\Essential;

use Gutenverse\Style\Style_Interface;
use Gutenverse_Companion\Essential\Style\Advance_Tabs;
use Gutenverse_Companion\Essential\Style\Mega_Menu;
use Gutenverse_Companion\Essential\Style\Mega_Menu_Item;

/**
 * Class Style Generator
 *
 * @package gutenverse-companion
 */
class Style_Generator {
	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'gutenverse_custom_font_pro', array( $this, 'get_custom_font' ), 10, 2 );
		add_action( 'gutenverse_generated_style', array( $this, 'generated_style_animation' ), 30 );
		add_action( 'gutenverse_generated_style', array( $this, 'generate_style_text_clip' ), 30 );
		add_action( 'gutenverse_generated_style', array( $this, 'generate_style_transform' ), 30 );
		add_action( 'gutenverse_generated_style', array( $this, 'generate_text_child_style' ), 30 );
		add_action( 'gutenverse_section_style', array( $this, 'section_sticky' ) );
		add_action( 'gutenverse_column_style', array( $this, 'column_sticky' ) );
		add_action( 'gutenverse_form_builder_style', array( $this, 'form_builder_sticky' ) );
		add_filter( 'gutenverse_block_style_instance', array( $this, 'get_block_style_instance' ), 32, 3 );
	}

	/**
	 * Flag if we can render sticky.
	 *
	 * @param array $sticky Sticky data.
	 *
	 * @return bool
	 */
	public function can_render_sticky( $sticky ) {
		$flag = false;

		foreach ( $sticky as $enable ) {
			$flag = $flag || $enable;
		}

		return $flag;
	}
	/**
	 * Filter Generated Style
	 *
	 * @param Style_Interface $instance Instance of style.
	 */
	public function column_sticky( $instance ) {
		$attrs      = $instance->get_attributes();
		$element_id = $instance->get_element_id();

		if ( $this->can_render_sticky( $attrs['sticky'] ) ) {
			if ( isset( $attrs['stickyPosition'] ) ) {
				if ( 'top' === $attrs['stickyPosition'] && isset( $attrs['topSticky'] ) ) {
					$instance->inject_style(
						array(
							'selector'       => ".guten-column.{$element_id}.pinned",
							'property'       => function ( $value ) use ( $instance ) {
								return $instance->handle_unit_point( $value, 'top' );
							},
							'value'          => $attrs['topSticky'],
							'device_control' => true,
							'ignore_empty'   => true,
						)
					);

					$instance->inject_style(
						array(
							'selector'       => ".guten-column.{$element_id}.pinned",
							'property'       => function ( $value, $device ) use ( $instance, $attrs ) {
								if ( isset( $attrs['sticky'][ $device ] ) ) {
									return $instance->handle_unit_point( $value, 'top' );
								}
								return '';
							},
							'value'          => $attrs['topSticky'],
							'device_control' => true,
							'ignore_empty'   => true,
						)
					);
				}

				if ( 'bottom' === $attrs['stickyPosition'] && isset( $attrs['bottomSticky'] ) ) {
					$instance->inject_style(
						array(
							'selector'       => ".guten-column.{$element_id}.pinned",
							'property'       => function ( $value, $device ) use ( $instance, $attrs ) {
								if ( $attrs['sticky'][ $device ] ) {
									return $instance->handle_unit_point( $value, 'bottom' );
								}
								return '';
							},
							'value'          => $attrs['bottomSticky'],
							'device_control' => true,
						)
					);
				}
			}

			if ( isset( $attrs['stickyIndex'] ) ) {
				$instance->inject_style(
					array(
						'selector'       => ".guten-column.{$element_id}.pinned",
						'property'       => function ( $value ) {
							return "z-index: {$value};";
						},
						'value'          => $attrs['stickyIndex'],
						'device_control' => false,
					)
				);
			}

			if ( isset( $attrs['stickyBackground'] ) ) {
				$instance->inject_style(
					array(
						'selector'       => ".guten-column.{$element_id}.pinned",
						'property'       => function ( $value ) use ( $instance ) {
							return $instance->handle_color( $value, 'background-color' );
						},
						'value'          => $attrs['stickyBackground'],
						'device_control' => false,
					)
				);
			}

			if ( isset( $attrs['stickyBoxShadow'] ) ) {
				$instance->inject_style(
					array(
						'selector'       => ".guten-column.{$element_id}.pinned",
						'property'       => function ( $value ) use ( $instance ) {
							return $instance->handle_box_shadow( $value );
						},
						'value'          => $attrs['stickyBoxShadow'],
						'device_control' => false,
					)
				);
			}
		}
	}

	/**
	 * Filter Generated Style
	 *
	 * @param Style_Interface $instance Instance of style.
	 */
	public function section_sticky( $instance ) {
		$attrs      = $instance->get_attributes();
		$element_id = $instance->get_element_id();

		if ( $this->can_render_sticky( $attrs['sticky'] ) ) {
			if ( isset( $attrs['stickyPosition'] ) ) {
				if ( 'top' === $attrs['stickyPosition'] && isset( $attrs['topSticky'] ) ) {
					$instance->inject_style(
						array(
							'selector'       => "section.guten-section.{$element_id}.pinned",
							'property'       => function ( $value ) use ( $instance ) {
								return $instance->handle_unit_point( $value, 'top' );
							},
							'value'          => $attrs['topSticky'],
							'device_control' => true,
							'ignore_empty'   => true,
						)
					);

					$instance->inject_style(
						array(
							'selector'       => ".guten-section-wrapper.pinned.section-{$element_id}",
							'property'       => function ( $value, $device ) use ( $instance, $attrs ) {
								if ( isset( $attrs['sticky'][ $device ] ) ) {
									return $instance->handle_unit_point( $value, 'top' );
								}
								return '';
							},
							'value'          => $attrs['topSticky'],
							'device_control' => true,
							'ignore_empty'   => true,
						)
					);
				}

				if ( 'bottom' === $attrs['stickyPosition'] && isset( $attrs['bottomSticky'] ) ) {
					$instance->inject_style(
						array(
							'selector'       => ".guten-section-wrapper.pinned.section-{$element_id}",
							'property'       => function ( $value, $device ) use ( $instance, $attrs ) {
								if ( $attrs['sticky'][ $device ] ) {
									return $instance->handle_unit_point( $value, 'bottom' );
								}
								return '';
							},
							'value'          => $attrs['bottomSticky'],
							'device_control' => true,
						)
					);
				}
			}

			if ( isset( $attrs['stickyIndex'] ) ) {
				$instance->inject_style(
					array(
						'selector'       => ".guten-section-wrapper.pinned.section-{$element_id}",
						'property'       => function ( $value ) {
							return "z-index: {$value};";
						},
						'value'          => $attrs['stickyIndex'],
						'device_control' => false,
					)
				);
			}

			if ( isset( $attrs['stickyBackground'] ) ) {
				$instance->inject_style(
					array(
						'selector'       => ".section-wrapper.pinned > section.guten-section.{$element_id}",
						'property'       => function ( $value ) use ( $instance ) {
							return $instance->handle_color( $value, 'background-color' );
						},
						'value'          => $attrs['stickyBackground'],
						'device_control' => false,
					)
				);
			}

			if ( isset( $attrs['stickyBoxShadow'] ) ) {
				$instance->inject_style(
					array(
						'selector'       => ".section-wrapper.pinned > section.guten-section.{$element_id}",
						'property'       => function ( $value ) use ( $instance ) {
							return $instance->handle_box_shadow( $value );
						},
						'value'          => $attrs['stickyBoxShadow'],
						'device_control' => false,
					)
				);
			}
		}
	}

	/**
	 * Filter Generated Style
	 *
	 * @param Style_Interface $instance Instance of style.
	 */
	public function form_builder_sticky( $instance ) {
		$attrs      = $instance->get_attributes();
		$element_id = $instance->get_element_id();

		if ( $this->can_render_sticky( $attrs['sticky'] ) ) {
			if ( isset( $attrs['stickyPosition'] ) ) {
				if ( 'top' === $attrs['stickyPosition'] && isset( $attrs['topSticky'] ) ) {
					$instance->inject_style(
						array(
							'selector'       => ".guten-form-builder.{$element_id}.pinned",
							'property'       => function ( $value ) use ( $instance ) {
								return $instance->handle_unit_point( $value, 'top' );
							},
							'value'          => $attrs['topSticky'],
							'device_control' => true,
							'ignore_empty'   => true,
						)
					);

					$instance->inject_style(
						array(
							'selector'       => ".guten-form-builder.{$element_id}.pinned",
							'property'       => function ( $value, $device ) use ( $instance, $attrs ) {
								if ( isset( $attrs['sticky'][ $device ] ) ) {
									return $instance->handle_unit_point( $value, 'top' );
								}
								return '';
							},
							'value'          => $attrs['topSticky'],
							'device_control' => true,
							'ignore_empty'   => true,
						)
					);
				}

				if ( 'bottom' === $attrs['stickyPosition'] && isset( $attrs['bottomSticky'] ) ) {
					$instance->inject_style(
						array(
							'selector'       => ".guten-form-builder.{$element_id}.pinned",
							'property'       => function ( $value, $device ) use ( $instance, $attrs ) {
								if ( $attrs['sticky'][ $device ] ) {
									return $instance->handle_unit_point( $value, 'bottom' );
								}
								return '';
							},
							'value'          => $attrs['bottomSticky'],
							'device_control' => true,
						)
					);
				}
			}

			if ( isset( $attrs['stickyIndex'] ) ) {
				$instance->inject_style(
					array(
						'selector'       => ".guten-form-builder.{$element_id}.pinned",
						'property'       => function ( $value ) {
							return "z-index: {$value};";
						},
						'value'          => $attrs['stickyIndex'],
						'device_control' => false,
					)
				);
			}

			if ( isset( $attrs['stickyBackground'] ) ) {
				$instance->inject_style(
					array(
						'selector'       => ".guten-form-builder.{$element_id}.pinned",
						'property'       => function ( $value ) use ( $instance ) {
							return $instance->handle_color( $value, 'background-color' );
						},
						'value'          => $attrs['stickyBackground'],
						'device_control' => false,
					)
				);
			}

			if ( isset( $attrs['stickyBoxShadow'] ) ) {
				$instance->inject_style(
					array(
						'selector'       => ".guten-form-builder.{$element_id}.pinned",
						'property'       => function ( $value ) use ( $instance ) {
							return $instance->handle_box_shadow( $value );
						},
						'value'          => $attrs['stickyBoxShadow'],
						'device_control' => false,
					)
				);
			}
		}
	}

	/**
	 * Filter Generated Style
	 *
	 * @param Style_Interface $instance Instance of style.
	 */
	public function generated_style_animation( $instance ) {
		// Animation on Pro.
		if ( in_array( 'animation', $instance->get_features(), true ) ) {
			$attrs      = $instance->get_attributes();
			$element_id = $instance->get_element_id();

			if ( isset( $attrs['animation'] ) && ! empty( $attrs['animation'] ) ) {
				$instance->inject_style(
					array(
						'selector'       => ".{$element_id}",
						'property'       => function ( $value ) {
							if ( isset( $value['delay'] ) ) {
								$delay = $value['delay'] / 1000;
								return "animation-delay: {$delay}s;";
							}
						},
						'value'          => $attrs['animation'],
						'device_control' => false,
					)
				);
			}
		}
	}

	/**
	 * Handle Background Processing.
	 *
	 * @param Style_Interface $instance Instance.
	 * @param string          $selector selector.
	 * @param array           $text_clip Value of Color.
	 */
	public function handle_text_clip( $instance, $selector, $text_clip ) {
		if ( ! isset( $text_clip['type'] ) || '' === $text_clip['type'] ) {
			return;
		}

		$instance->inject_style(
			array(
				'selector'       => $selector,
				'property'       => function () {
					return '-webkit-background-clip: text !important; -webkit-text-fill-color: transparent;';
				},
				'value'          => $text_clip,
				'device_control' => false,
			)
		);

		if ( 'image' === $text_clip['type'] ) {
			if ( isset( $text_clip['image'] ) ) {
				$instance->inject_style(
					array(
						'selector'       => $selector,
						'property'       => function ( $value ) {
							return "background-image: url({$value['image']});";
						},
						'value'          => $text_clip['image'],
						'device_control' => false,
					)
				);
			}

			if ( isset( $text_clip['position'] ) ) {
				$instance->inject_style(
					array(
						'selector'       => $selector,
						'property'       => function ( $value ) {
							if ( 'custom' !== $value && 'default' !== $value ) {
								return "background-position: {$value};";
							}
						},
						'value'          => $text_clip['position'],
						'device_control' => false,
					)
				);

				if ( isset( $text_clip['xposition'] ) ) {
					$instance->inject_style(
						array(
							'selector'       => $selector,
							'property'       => function ( $value ) {
								$position = $value['position'];
								$xposition = isset( $value['xposition'] ) ? $value['xposition'] : false;

								if ( 'custom' === $position && $xposition ) {
									return ! empty( $xposition['point'] ) ? "background-position-x: {$xposition['point']}{$xposition['unit']};" : null;
								}

								return null;
							},
							'value'          => $instance->merge_options(
								array(
									'position'  => $text_clip['position'],
									'xposition' => $text_clip['xposition'],
								)
							),
							'device_control' => false,
						)
					);
				}

				if ( isset( $text_clip['yposition'] ) ) {
					$instance->inject_style(
						array(
							'selector'       => $selector,
							'property'       => function ( $value ) {
								$position = $value['position'];
								$yposition = isset( $value['yposition'] ) ? $value['yposition'] : false;

								if ( 'custom' === $position && $yposition ) {
									return ! empty( $yposition['point'] ) ? "background-position-y: {$yposition['point']}{$yposition['unit']};" : null;
								}

								return null;
							},
							'value'          => $instance->merge_options(
								array(
									'position'  => $text_clip['position'],
									'yposition' => $text_clip['yposition'],
								)
							),
							'device_control' => false,
						)
					);
				}
			}

			if ( isset( $text_clip['repeat'] ) ) {
				$instance->inject_style(
					array(
						'selector'       => $selector,
						'property'       => function ( $value ) {
							return "background-repeat: {$value};";
						},
						'value'          => $text_clip['repeat'],
						'device_control' => false,
					)
				);
			}

			if ( isset( $text_clip['size'] ) ) {
				$instance->inject_style(
					array(
						'selector'       => $selector,
						'property'       => function ( $value ) {
							if ( 'custom' !== $value && 'default' !== $value ) {
								return "background-size: {$value};";
							}
						},
						'value'          => $text_clip['size'],
						'device_control' => false,
					)
				);

				if ( isset( $text_clip['size'] ) && isset( $text_clip['width'] ) ) {
					$instance->inject_style(
						array(
							'selector'       => $selector,
							'property'       => function ( $value ) {
								$size = $value['size'];
								$width = isset( $value['width'] ) ? $value['width'] : null;

								if ( 'custom' === $size && $width ) {
									return "background-size: {$width['point']}{$width['unit']};";
								}

								return null;
							},
							'value'          => $instance->merge_options(
								array(
									'size'  => $text_clip['size'],
									'width' => $text_clip['width'],
								)
							),
							'device_control' => false,
						)
					);
				}
			}

			if ( isset( $text_clip['blendMode'] ) && ! empty( $text_clip['blendMode'] ) ) {
				$instance->inject_style(
					array(
						'selector'       => $selector,
						'property'       => function ( $value ) {
							return "background-blend-mode: {$value};";
						},
						'value'          => $text_clip['blendMode'],
						'device_control' => false,
					)
				);
			}

			if ( isset( $text_clip['fixed'] ) ) {
				$instance->inject_style(
					array(
						'selector'       => $selector,
						'property'       => function ( $value ) {
							$bg_attachment = '';

							if ( is_bool( $value ) ) {
								$fixed = $value ? 'fixed' : 'scroll';
								$bg_attachment = "background-attachment: {$fixed};";
							}

							return $bg_attachment;
						},
						'ignore_empty'   => true,
						'value'          => $text_clip['fixed'],
						'device_control' => false,
					)
				);
			}
		} elseif ( 'gradient' === $text_clip['type'] ) {
			$instance->inject_style(
				array(
					'selector'       => $selector,
					'property'       => function ( $value ) {
						$gradient_color        = $value['gradientColor'];
						$gradient_type         = $value['gradientType'];
						$gradient_angle        = $value['gradientAngle'];
						$gradient_radial       = $value['gradientRadial'];

						if ( ! empty( $gradient_color ) ) {
							$colors = array();

							foreach ( $gradient_color as $gradient ) {
								$offset  = $gradient['offset'] * 100;
								$colors[] = "{$gradient['color']} {$offset}%";
							}

							$colors = join( ',', $colors );

							if ( 'radial' === $gradient_type ) {
								return "background: radial-gradient(at {$gradient_radial}, {$colors});";
							} else {
								return "background: linear-gradient({$gradient_angle}deg, {$colors});";
							}
						}
					},
					'value'          => array(
						'gradientColor'       => isset( $text_clip['gradientColor'] ) ? $text_clip['gradientColor'] : null,
						'gradientPosition'    => isset( $text_clip['gradientPosition'] ) ? $text_clip['gradientPosition'] : 0,
						'gradientEndColor'    => isset( $text_clip['gradientEndColor'] ) ? $text_clip['gradientEndColor'] : null,
						'gradientEndPosition' => isset( $text_clip['gradientEndPosition'] ) ? $text_clip['gradientEndPosition'] : 100,
						'gradientType'        => isset( $text_clip['gradientType'] ) ? $text_clip['gradientType'] : 'linear',
						'gradientAngle'       => isset( $text_clip['gradientAngle'] ) ? $text_clip['gradientAngle'] : 180,
						'gradientRadial'      => isset( $text_clip['gradientRadial'] ) ? $text_clip['gradientRadial'] : 'center center',
					),
					'device_control' => false,
				)
			);
		}
	}

	/**
	 * Text Clip.
	 *
	 * @param Style_Interface $instance Instance of style.
	 */
	public function generate_style_text_clip( $instance ) {
		$attrs      = $instance->get_attributes();
		$element_id = $instance->get_element_id();

		if ( 'advanced-heading' === $instance->get_name() ) {
			if ( isset( $attrs['mainTextClip'] ) && ! empty( $attrs['mainTextClip'] ) ) {
				$this->handle_text_clip( $instance, ".{$element_id} .heading-title", $attrs['mainTextClip'] );
			}

			if ( isset( $attrs['focusTextClip'] ) && ! empty( $attrs['focusTextClip'] ) ) {
				$this->handle_text_clip( $instance, ".{$element_id} .heading-focus", $attrs['focusTextClip'] );
			}
		}

		if ( 'animated-text' === $instance->get_name() ) {
			if ( isset( $attrs['textClip'] ) && ! empty( $attrs['textClip'] ) ) {
				$this->handle_text_clip( $instance, ".{$element_id} .text-content .letter", $attrs['textClip'] );
			}
		}

		if ( 'heading' === $instance->get_name() ) {
			if ( isset( $attrs['textClip'] ) && ! empty( $attrs['textClip'] ) ) {
				$this->handle_text_clip( $instance, "h1.guten-element.{$element_id}, h2.guten-element.{$element_id}, h3.guten-element.{$element_id}, h4.guten-element.{$element_id}, h5.guten-element.{$element_id}, h6.guten-element.{$element_id}", $attrs['textClip'] );
			}
		}
	}

	/**
	 * Handle Transform.
	 *
	 * @param Style_Interface $instance Instance.
	 * @param string          $selector selector.
	 * @param array           $transform Value of Transform.
	 */
	public function handle_transform( $instance, $selector, $transform ) {
		$instance->inject_style(
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

		if ( isset( $transform['ease'] ) && ! empty( $transform['ease'] ) ) {
			$instance->inject_style(
				array(
					'selector'       => "{$selector}",
					'property'       => function ( $value ) {
						$value_ = ! gutenverse_truly_empty( $value ) ? $value : 'linear';
						return "transition-timing-function: {$value_};";
					},
					'value'          => $transform['ease'],
					'device_control' => false,
				)
			);
		}

		if ( isset( $transform['transformOrigin'] ) && ! empty( $transform['transformOrigin'] ) ) {
			$instance->inject_style(
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

		$transform_values = $instance->multi_style_values(
			array(
				array(
					'value' => ! empty( $transform['perspective'] ) ? $transform['perspective'] : null,
					'style' => function ( $value ) {
						return ! gutenverse_truly_empty( $value['point'] ) ? "perspective({$value['point']}{$value['unit']})" : '';
					},
				),
				array(
					'value' => ! empty( $transform['rotateZ'] ) ? $transform['rotateZ'] : null,
					'style' => function ( $value ) {
						return ! gutenverse_truly_empty( $value['point'] ) ? "rotate({$value['point']}{$value['unit']})" : '';
					},
				),
				array(
					'value' => ! empty( $transform['rotateX'] ) ? $transform['rotateX'] : null,
					'style' => function ( $value ) {
						return ! gutenverse_truly_empty( $value['point'] ) ? "rotateX({$value['point']}{$value['unit']})" : '';
					},
				),
				array(
					'value' => ! empty( $transform['rotateY'] ) ? $transform['rotateY'] : null,
					'style' => function ( $value ) {
						return ! gutenverse_truly_empty( $value['point'] ) ? "rotateY({$value['point']}{$value['unit']})" : '';
					},
				),
				array(
					'value' => ! empty( $transform['scaleX'] ) ? $transform['scaleX'] : null,
					'style' => function ( $value ) {
						return ! gutenverse_truly_empty( $value ) ? "scaleX({$value})" : '';
					},
				),
				array(
					'value' => ! empty( $transform['scaleY'] ) ? $transform['scaleY'] : null,
					'style' => function ( $value ) {
						return ! gutenverse_truly_empty( $value ) ? "scaleY({$value})" : '';
					},
				),
				array(
					'value' => ! empty( $transform['moveZ'] ) ? $transform['moveZ'] : null,
					'style' => function ( $value ) {
						return ! gutenverse_truly_empty( $value['point'] ) ? "translateZ({$value['point']}{$value['unit']})" : '';
					},
				),
				array(
					'value' => ! empty( $transform['moveX'] ) ? $transform['moveX'] : null,
					'style' => function ( $value ) {
						return ! gutenverse_truly_empty( $value['point'] ) ? "translateX({$value['point']}{$value['unit']})" : '';
					},
				),
				array(
					'value' => ! empty( $transform['moveY'] ) ? $transform['moveY'] : null,
					'style' => function ( $value ) {
						return ! gutenverse_truly_empty( $value['point'] ) ? "translateY({$value['point']}{$value['unit']})" : '';
					},
				),
				array(
					'value' => ! empty( $transform['skewX'] ) ? $transform['skewX'] : null,
					'style' => function ( $value ) {
						return ! gutenverse_truly_empty( $value['point'] ) ? "skewX({$value['point']}{$value['unit']})" : '';
					},
				),
				array(
					'value' => ! empty( $transform['skewY'] ) ? $transform['skewY'] : null,
					'style' => function ( $value ) {
						return ! gutenverse_truly_empty( $value['point'] ) ? "skewY({$value['point']}{$value['unit']})" : '';
					},
				),
			)
		);

		$instance->inject_style(
			array(
				'selector'       => "{$selector}",
				'property'       => function ( $value ) {
					return "transform: {$value};";
				},
				'value'          => $transform_values,
				'device_control' => true,
			)
		);

		if ( isset( $transform['opacity'] ) && ! gutenverse_truly_empty( $transform['opacity'] ) ) {
			$instance->inject_style(
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
	 * @param Style_Interface $instance Instance.
	 * @param string          $selector selector.
	 * @param array           $transform Value of Transform.
	 */
	public function handle_transform_hover( $instance, $selector, $transform ) {
		$transform_values = $instance->multi_style_values(
			array(
				array(
					'value' => ! empty( $transform['perspectiveHover'] ) ? $transform['perspectiveHover'] : null,
					'style' => function ( $value ) {
						return ! gutenverse_truly_empty( $value['point'] ) ? "perspective({$value['point']}{$value['unit']})" : '';
					},
				),
				array(
					'value' => ! empty( $transform['rotateZHover'] ) ? $transform['rotateZHover'] : null,
					'style' => function ( $value ) {
						return ! gutenverse_truly_empty( $value['point'] ) ? "rotate({$value['point']}{$value['unit']})" : '';
					},
				),
				array(
					'value' => ! empty( $transform['rotateXHover'] ) ? $transform['rotateXHover'] : null,
					'style' => function ( $value ) {
						return ! gutenverse_truly_empty( $value['point'] ) ? "rotateX({$value['point']}{$value['unit']})" : '';
					},
				),
				array(
					'value' => ! empty( $transform['rotateYHover'] ) ? $transform['rotateYHover'] : null,
					'style' => function ( $value ) {
						return ! gutenverse_truly_empty( $value['point'] ) ? "rotateY({$value['point']}{$value['unit']})" : '';
					},
				),
				array(
					'value' => ! empty( $transform['scaleXHover'] ) ? $transform['scaleXHover'] : null,
					'style' => function ( $value ) {
						return ! gutenverse_truly_empty( $value ) ? "scaleX({$value})" : '';
					},
				),
				array(
					'value' => ! empty( $transform['scaleYHover'] ) ? $transform['scaleYHover'] : null,
					'style' => function ( $value ) {
						return ! gutenverse_truly_empty( $value ) ? "scaleY({$value})" : '';
					},
				),
				array(
					'value' => ! empty( $transform['moveZHover'] ) ? $transform['moveZHover'] : null,
					'style' => function ( $value ) {
						return ! gutenverse_truly_empty( $value['point'] ) ? "translateZ({$value['point']}{$value['unit']})" : '';
					},
				),
				array(
					'value' => ! empty( $transform['moveXHover'] ) ? $transform['moveXHover'] : null,
					'style' => function ( $value ) {
						return ! gutenverse_truly_empty( $value['point'] ) ? "translateX({$value['point']}{$value['unit']})" : '';
					},
				),
				array(
					'value' => ! empty( $transform['moveYHover'] ) ? $transform['moveYHover'] : null,
					'style' => function ( $value ) {
						return ! gutenverse_truly_empty( $value['point'] ) ? "translateY({$value['point']}{$value['unit']})" : '';
					},
				),
				array(
					'value' => ! empty( $transform['skewXHover'] ) ? $transform['skewXHover'] : null,
					'style' => function ( $value ) {
						return ! gutenverse_truly_empty( $value['point'] ) ? "skewX({$value['point']}{$value['unit']})" : '';
					},
				),
				array(
					'value' => ! empty( $transform['skewYHover'] ) ? $transform['skewYHover'] : null,
					'style' => function ( $value ) {
						return ! gutenverse_truly_empty( $value['point'] ) ? "skewY({$value['point']}{$value['unit']})" : '';
					},
				),
			)
		);

		$instance->inject_style(
			array(
				'selector'       => "{$selector}",
				'property'       => function ( $value ) {
					return "transform: {$value};";
				},
				'value'          => $transform_values,
				'device_control' => true,
			)
		);

		if ( isset( $transform['opacityHover'] ) && ! gutenverse_truly_empty( $transform['opacityHover'] ) ) {
			$instance->inject_style(
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
	}

	/**
	 * Transform.
	 *
	 * @param Style_Interface $instance Instance of style.
	 */
	public function generate_style_transform( $instance ) {
		$attrs      = $instance->get_attributes();
		$element_id = $instance->get_element_id();
		$selectors  = $instance->get_features();

		if ( isset( $attrs['transform'] ) && ! empty( $attrs['transform'] ) ) {
			$normal    = ! empty( $selectors['transform'] ) ? $selectors['transform']['normal'] : ".{$element_id}";
			$hover     = ! empty( $selectors['transform'] ) ? $selectors['transform']['hover'] : ".{$element_id}:hover";
			$transform = $attrs['transform'];
			$this->handle_transform( $instance, $normal, $transform );
			$this->handle_transform_hover( $instance, $hover, $transform );
		}
	}

	/**
	 * Get Block Style Instance.
	 *
	 * @param mixed  $instance Instance.
	 * @param string $name Block Name.
	 * @param array  $attrs Block Attribute.
	 *
	 * @return Style_Abstract
	 */
	public function get_block_style_instance( $instance, $name, $attrs ) {
		switch ( $name ) {
			case 'gutenverse/mega-menu':
				$instance = new Mega_Menu( $attrs );
				break;
			case 'gutenverse/mega-menu-item':
				$instance = new Mega_Menu_Item( $attrs );
				break;
			case 'gutenverse-pro/advance-tabs':
				$instance = new Advance_Tabs( $attrs );
				break;

		}
		return $instance;
	}
	/**
	 * Get Custom Font
	 *
	 * @param array $variable_fonts .
	 * @param array $custom_fonts .
	 *
	 * @return array $font_families .
	 */
	public function get_custom_font( $variable_fonts, $custom_fonts ) {
		$font_families = array_merge( $custom_fonts, $variable_fonts );
		return $font_families;
	}
	/**
	 * Generate Text Child Style Highlight
	 *
	 * @param mixed $instance .
	 */
	public function generate_text_child_style( $instance ) {
		$attrs           = $instance->get_attributes();
		$element_id      = $instance->get_element_id();
		$arr_childs_name = array( 'textChilds', 'descriptionChilds', 'nameChilds', 'jobChilds', 'titleChilds', 'badgeChilds', 'focusTextChilds', 'subTextChilds' );
		foreach ( $arr_childs_name as $child_name ) {
			if ( isset( $attrs[ $child_name ] ) ) {
				foreach ( $attrs[ $child_name ] as $child ) {
					if ( isset( $child['color'] ) ) {
						$instance->inject_style(
							array(
								'selector'       => ".{$element_id} #{$child['id']}",
								'property'       => function ( $value ) use ( $instance ) {
									return $instance->handle_color( $value, 'color' );
								},
								'value'          => $child['color'],
								'device_control' => false,
							)
						);
					}
					if ( isset( $child['colorHover'] ) ) {
						$instance->inject_style(
							array(
								'selector'       => ".{$element_id} #{$child['id']}:hover",
								'property'       => function ( $value ) use ( $instance ) {
									return $instance->handle_color( $value, 'color' );
								},
								'value'          => $child['colorHover'],
								'device_control' => false,
							)
						);
					}
					if ( isset( $child['typography'] ) ) {
						$instance->inject_typography(
							array(
								'selector'       => ".{$element_id} #{$child['id']}",
								'property'       => function ( $value ) {},
								'value'          => $child['typography'],
								'device_control' => false,
							)
						);
					}
					if ( isset( $child['typographyHover'] ) ) {
						$instance->inject_typography(
							array(
								'selector'       => ".{$element_id} #{$child['id']}:hover",
								'property'       => function ( $value ) {},
								'value'          => $child['typographyHover'],
								'device_control' => false,
							)
						);
					}
					if ( isset( $child['textClip'] ) ) {
						$this->handle_text_clip( $instance, ".{$element_id} #{$child['id']}", $child['textClip'] );
					}
					if ( isset( $child['textClipHover'] ) ) {
						$this->handle_text_clip( $instance, ".{$element_id} #{$child['id']}:hover", $child['textClipHover'] );
					}
					if ( isset( $child['background'] ) ) {
						$instance->handle_background( ".{$element_id} #{$child['spanId']}", $child['background'] );
					}
					if ( isset( $child['backgroundHover'] ) ) {
						$instance->handle_background( ".{$element_id} #{$child['spanId']}:hover", $child['backgroundHover'] );
					}
					if ( isset( $child['margin'] ) ) {
						$instance->inject_style(
							array(
								'selector'       => ".{$element_id} #{$child['spanId']}",
								'property'       => function ( $value ) use ( $instance ) {
									return $instance->handle_dimension( $value, 'margin' );
								},
								'value'          => $child['margin'],
								'device_control' => true,
							)
						);
					}
					if ( isset( $child['marginHover'] ) ) {
						$instance->inject_style(
							array(
								'selector'       => ".{$element_id} #{$child['spanId']}:hover",
								'property'       => function ( $value ) use ( $instance ) {
									return $instance->handle_dimension( $value, 'margin' );
								},
								'value'          => $child['marginHover'],
								'device_control' => true,
							)
						);
					}
					if ( isset( $child['padding'] ) ) {
						$instance->inject_style(
							array(
								'selector'       => ".{$element_id} #{$child['id']}",
								'property'       => function ( $value ) use ( $instance ) {
									return $instance->handle_dimension( $value, 'padding' );
								},
								'value'          => $child['padding'],
								'device_control' => true,
							)
						);
					}
					if ( isset( $child['paddingHover'] ) ) {
						$instance->inject_style(
							array(
								'selector'       => ".{$element_id} #{$child['id']}:hover",
								'property'       => function ( $value ) use ( $instance ) {
									return $instance->handle_dimension( $value, 'padding' );
								},
								'value'          => $child['paddingHover'],
								'device_control' => true,
							)
						);
					}
					if ( isset( $child['borderNormal'] ) ) {
						$instance->inject_style(
							array(
								'selector'       => ".{$element_id} #{$child['id']}",
								'property'       => function ( $value ) use ( $instance ) {
									return $instance->handle_border_responsive( $value );
								},
								'value'          => $child['borderNormal'],
								'device_control' => true,
							)
						);
					}
					if ( isset( $child['borderHover'] ) ) {
						$instance->inject_style(
							array(
								'selector'       => ".{$element_id} #{$child['id']}:hover",
								'property'       => function ( $value ) use ( $instance ) {
									return $instance->handle_border_responsive( $value );
								},
								'value'          => $child['borderHover'],
								'device_control' => true,
							)
						);
					}
				}
			}
		}
	}
}
