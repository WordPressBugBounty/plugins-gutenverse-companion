<?php
/**
 * REST APIs class
 *
 * @author Jegstudio
 * @since 1.0.0
 * @package gutenverse-companion
 */

namespace Gutenverse_Companion;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use WP_Error;
use WP_Query;
use WP_REST_Response;
use ZipArchive;

/**
 * Class Api
 *
 * @package gutenverse-companion
 */
class Api {
	/**
	 * Instance of Gutenverse.
	 *
	 * @var Api
	 */
	private static $instance;

	/**
	 * Endpoint Path
	 *
	 * @var string
	 */
	const ENDPOINT = 'gutenverse-companion/v1';

	/**
	 * Singleton page for Gutenverse Class
	 *
	 * @return Api
	 */
	public static function instance() {
		if ( null === static::$instance ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	/**
	 * Blocks constructor.
	 */
	private function __construct() {
		if ( did_action( 'rest_api_init' ) ) {
			$this->register_routes();
		}
	}

	/**
	 * Register Gutenverse APIs
	 */
	private function register_routes() {
		/**
		 * Backend routes.
		 */
		register_rest_route(
			self::ENDPOINT,
			'demo/get',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'demo_get' ),
				'permission_callback' => function () {
					if ( ! current_user_can( 'manage_options' ) ) {
						return new \WP_Error(
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
			'demo/import',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'demo_import' ),
				'permission_callback' => function () {
					if ( ! current_user_can( 'manage_options' ) ) {
						return new \WP_Error(
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
			'demo/assign',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'demo_assign' ),
				'permission_callback' => function () {
					if ( ! current_user_can( 'manage_options' ) ) {
						return new \WP_Error(
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
			'pattern/get',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'pattern_get' ),
				'permission_callback' => function () {
					if ( ! current_user_can( 'manage_options' ) ) {
						return new \WP_Error(
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
			'pattern/insert',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'pattern_insert' ),
				'permission_callback' => function () {
					if ( ! current_user_can( 'manage_options' ) ) {
						return new \WP_Error(
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
			'demo/pages',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'demo_pages' ),
				'permission_callback' => function () {
					if ( ! current_user_can( 'manage_options' ) ) {
						return new \WP_Error(
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
			'import/images',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'import_images' ),
				'permission_callback' => function () {
					if ( ! current_user_can( 'manage_options' ) ) {
						return new \WP_Error(
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
				'methods'             => 'POST',
				'callback'            => array( $this, 'import_menus' ),
				'permission_callback' => function () {
					if ( ! current_user_can( 'manage_options' ) ) {
						return new \WP_Error(
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

	/**
	 * Import Images
	 *
	 * @param object $request images.
	 */
	public function import_images( $request ) {
		$image = $request->get_param( 'imageUrl' );

		$data = $this->check_image_exist( $image );
		if ( ! $data ) {
			$data = $this->import_image( $image );
		}

		return $data;
	}

	/**
	 * Return image
	 *
	 * @param string $url Image attachment url.
	 *
	 * @return array|null
	 */
	public function check_image_exist( $url ) {
		$attachments = new \WP_Query(
			array(
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'meta_query'  => array(
					array(
						'key'     => '_import_source',
						'value'   => $url,
						'compare' => 'LIKE',
					),
				),
			)
		);

		foreach ( $attachments->posts as $post ) {
			$attachment_url = wp_get_attachment_url( $post->ID );
			return array(
				'id'  => $post->ID,
				'url' => $attachment_url,
			);
		}

		return $attachments->posts;
	}


	/**
	 * Import an image into the media library
	 *
	 * @param string $url Image URL to import.
	 * @return array|null
	 */
	public function import_image( $url ) {
		$response = wp_remote_get( $url );

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$image_data = wp_remote_retrieve_body( $response );
		$filename   = basename( $url );

		$upload = wp_upload_bits( $filename, null, $image_data );

		if ( $upload['error'] ) {
			return null;
		}

		$attachment = array(
			'guid'           => $upload['url'],
			'post_mime_type' => $upload['type'],
			'post_title'     => sanitize_file_name( $filename ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attach_id = wp_insert_attachment( $attachment, $upload['file'] );

		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attach_data = wp_generate_attachment_metadata( $attach_id, $upload['file'] );
		wp_update_attachment_metadata( $attach_id, $attach_data );

		add_post_meta( $attach_id, '_import_source', $url, true );

		$imported_options = get_option( 'gutenverse-companion-imported-options' );
		if ( $imported_options ) {
			$imported_media            = $imported_options['media'];
			$imported_media[]          = $attach_id;
			$imported_options['media'] = $imported_media;
			update_option( 'gutenverse-companion-imported-options', $imported_options );
		}

		return array(
			'id'  => $attach_id,
			'url' => $upload['url'],
		);
	}

	/**
	 * Assign Demo
	 *
	 * @param object $request .
	 */
	public function demo_assign( $request ) {
		$name    = sanitize_text_field( $request->get_param( 'template' ) );
		$pattern = $request->get_param( 'pattern' );

		$upload_dir = wp_upload_dir();
		$target_dir = trailingslashit( $upload_dir['basedir'] ) . GUTENVERSE_COMPANION . '/' . trim( preg_replace( '/[^a-z0-9]+/i', '-', strtolower( $name ) ), '-' );
		$this->assign_templates( $target_dir, $pattern );
		$this->assign_parts( $target_dir, $pattern );
		$this->set_global_fonts( $target_dir );

		return update_option(
			'gutenverse_companion_template_options',
			array(
				'active_theme' => wp_get_theme()->get_template(),
				'active_demo'  => $name,
				'template_dir' => $target_dir,
			)
		);
	}

	/**
	 * Assign Template
	 *
	 * @param string $target_dir .
	 * @param array  $pattern .
	 */
	public function assign_templates( $target_dir, $pattern ) {
		$source_template_dir = $target_dir . '/demo/templates';
		$target_template_dir = $target_dir . '/templates';

		global $wp_filesystem;

		if ( ! function_exists( 'request_filesystem_credentials' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! $wp_filesystem->is_dir( $source_template_dir ) ) {
			echo 'Source directory does not exist!';
			return false;
		}

		if ( ! $wp_filesystem->is_dir( $target_template_dir ) ) {
			$wp_filesystem->mkdir( $target_template_dir );
		}

		$html_template_files = $wp_filesystem->dirlist( $source_template_dir, true );

		foreach ( $html_template_files as $file_name => $file_info ) {
			if ( 'html' === pathinfo( $file_name, PATHINFO_EXTENSION ) ) {
				$file_path = trailingslashit( $source_template_dir ) . $file_name;
				$content   = $wp_filesystem->get_contents( $file_path );

				foreach ( $pattern as $pat ) {
					foreach ( $pat as $key => $id ) {
						$content = str_replace( "{{{$key}}}", $id, $content );
					}
				}

				$target_file_path = trailingslashit( $target_template_dir ) . $file_name;
				$wp_filesystem->put_contents( $target_file_path, $content );

			}
		}
	}

	/**
	 * Assign Parts
	 *
	 * @param string $target_dir .
	 * @param array  $pattern .
	 */
	public function assign_parts( $target_dir, $pattern ) {
		$source_parts_dir = $target_dir . '/demo/parts';
		$target_parts_dir = $target_dir . '/parts';

		global $wp_filesystem;

		if ( ! function_exists( 'request_filesystem_credentials' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! $wp_filesystem->is_dir( $source_parts_dir ) ) {
			echo 'Source directory does not exist!';
			return false;
		}

		if ( ! $wp_filesystem->is_dir( $target_parts_dir ) ) {
			$wp_filesystem->mkdir( $target_parts_dir );
		}

		$html_parts_files = $wp_filesystem->dirlist( $source_parts_dir, true );

		foreach ( $html_parts_files as $file_name => $file_info ) {
			if ( 'html' === pathinfo( $file_name, PATHINFO_EXTENSION ) ) {
				$file_path = trailingslashit( $source_parts_dir ) . $file_name;
				$content   = $wp_filesystem->get_contents( $file_path );

				foreach ( $pattern as $pat ) {
					foreach ( $pat as $key => $id ) {
						$content = str_replace( "{{{$key}}}", $id, $content );
					}
				}

				$target_file_path = trailingslashit( $target_parts_dir ) . $file_name;
				$wp_filesystem->put_contents( $target_file_path, $content );

			}
		}
	}

	/**
	 * Set Global Font
	 *
	 * @param string $target_dir .
	 */
	public function set_global_fonts( $target_dir ) {
		$font_dir      = $target_dir . '/demo/global/font.json';
		$fonts_options = get_option( 'gutenverse-global-variable-font-' . get_stylesheet(), array() );
		if ( file_exists( $font_dir ) ) {
			$json_content = file_get_contents( $font_dir );
			$fonts        = json_decode( $json_content, true );
			foreach ( $fonts as $font ) {
				$existing_ids = array_column( $fonts_options, 'id' );

				if ( ! in_array( $font['id'], $existing_ids, true ) ) {
					$fonts_options[] = array(
						'id'   => $font['id'],
						'name' => $font['name'],
						'font' => $font['font'],
					);
				}
			}
			update_option( 'gutenverse-global-variable-font-' . get_stylesheet(), $fonts_options );
		}
	}

	/**
	 * Demo Download
	 *
	 * @param string $zip_url .
	 * @param string $name .
	 * @param bool   $installed .
	 */
	public function demo_download( $zip_url, $name, $installed ) {
		global $wp_filesystem;

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		WP_Filesystem();

		$upload_dir = wp_upload_dir();
		$target_dir = trailingslashit( $upload_dir['basedir'] ) . GUTENVERSE_COMPANION . '/';

		if ( ! $wp_filesystem->is_dir( $target_dir ) ) {
			$wp_filesystem->mkdir( $target_dir );
		}

		$target_dir = $target_dir . trim( preg_replace( '/[^a-z0-9]+/i', '-', strtolower( $name ) ), '-' ) . '/';

		if ( ! $wp_filesystem->is_dir( $target_dir ) ) {
			$wp_filesystem->mkdir( $target_dir );
		}

		$target_dir = $target_dir . 'demo/';

		if ( ! $wp_filesystem->is_dir( $target_dir ) ) {
			$wp_filesystem->mkdir( $target_dir );
		} elseif ( $installed ) {
			return true;
		}

		$filename = basename( wp_parse_url( $zip_url, PHP_URL_PATH ) );

		$zip_file = $target_dir . $filename;

		$response = wp_remote_get( $zip_url );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'download_error', 'Failed to download the ZIP file.' );
		}

		$zip_contents = wp_remote_retrieve_body( $response );

		if ( empty( $zip_contents ) ) {
			return new WP_Error( 'empty_file', 'The downloaded ZIP file is empty.' );
		}

		if ( ! $wp_filesystem->is_dir( $target_dir ) ) {
			$wp_filesystem->mkdir( $target_dir );
		}

		if ( ! $wp_filesystem->put_contents( $zip_file, $zip_contents ) ) {
			return new WP_Error( 'write_error', 'Failed to write the ZIP file.' );
		}

		$zip = new ZipArchive();

		if ( $zip->open( $zip_file ) === true ) {
			$zip->extractTo( $target_dir );
			$zip->close();

			$wp_filesystem->delete( $zip_file );

			return 'ZIP file extracted successfully.';
		} else {
			return new WP_Error( 'extraction_error', 'Failed to extract the ZIP file.' );
		}
	}

	/**
	 * Import Demo
	 *
	 * @param object $request .
	 */
	public function demo_import( $request ) {
		$name      = sanitize_text_field( $request->get_param( 'name' ) );
		$demo_id   = sanitize_text_field( $request->get_param( 'demo_id' ) );
		$installed = sanitize_text_field( $request->get_param( 'installed' ) );
		$active    = sanitize_text_field( $request->get_param( 'active' ) );
		$key       = sanitize_text_field( $request->get_param( 'key' ) );

		if ( $active ) {
			$this->get_companion_global_color();
		}

		/**Get File Url */
		$request_body    = wp_json_encode(
			array(
				'demo_id' => $demo_id,
				'key'     => $key,
			)
		);
		$response        = wp_remote_post(
			GUTENVERSE_COMPANION_LIBRARY_URL . 'wp-json/gutenverse-server/v4/companion/demo',
			array(
				'body'    => $request_body,
				'headers' => array(
					'Content-Type' => 'application/json',
					'Origin'       => $request->get_header( 'origin' ),
				),
			)
		);
		$file            = json_decode( wp_remote_retrieve_body( $response ) );
		$status_response = wp_remote_retrieve_response_code( $response );
		if ( is_wp_error( $response ) || 200 !== $status_response ) {
			return new WP_REST_Response(
				array(
					'message' => 'Unable to import/switch companion demo : ' . $file->message,
				),
				400
			);
		}
		$this->remove_previous_demo_data( $name );
		$file          = json_decode( wp_remote_retrieve_body( $response ) );
		$imported_data = array(
			'demo_name' => $name,
			'demo_id'   => $demo_id,
		);
		update_option( 'gutenverse-companion-imported-options', $imported_data );
		return $this->demo_download( $file, $name, $installed );
	}

	/**
	 * Removing Previous Demo Data
	 *
	 * @param string $name .
	 */
	public function remove_previous_demo_data( $name ) {
		$imported_options = get_option( 'gutenverse-companion-imported-options' );

		/**Removing data */
		$this->delete_posts( $imported_options['pages'], 'post' );
		$this->delete_posts( $imported_options['patterns'], 'post' );
		$this->delete_posts( $imported_options['media'], 'post' );

		/**Removing saved template part and template */
		$upload_dir          = wp_upload_dir();
		$target_dir          = trailingslashit( $upload_dir['basedir'] ) . GUTENVERSE_COMPANION . '/' . trim( preg_replace( '/[^a-z0-9]+/i', '-', strtolower( $name ) ), '-' );
		$source_template_dir = $target_dir . '/demo/templates';
		$source_parts_dir    = $target_dir . '/demo/parts';

		/**Removing template */
		$this->delete_template_and_parts( $source_template_dir, 'wp_template' );
		$this->delete_template_and_parts( $source_parts_dir, 'wp_template_part' );
	}

	/**
	 * Delete Template and Parts
	 *
	 * @param string $source .
	 * @param string $post_type .
	 */
	public function delete_template_and_parts( $source, $post_type ) {
		global $wp_filesystem;

		if ( ! function_exists( 'request_filesystem_credentials' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$html_files = $wp_filesystem->dirlist( $source, true );
		foreach ( $html_files as $file_name => $file_info ) {
			if ( 'html' === pathinfo( $file_name, PATHINFO_EXTENSION ) ) {
				$filename       = pathinfo( $file_name, PATHINFO_FILENAME );
				$template_posts = get_posts(
					array(
						'post_type'      => $post_type,
						'name'           => $filename,
						'posts_per_page' => 1,
						'post_status'    => 'any',
					)
				);

				if ( ! empty( $template_posts ) ) {
					wp_delete_post( $template_posts[0]->ID, true );
				}
			}
		}
	}

	/**
	 * Delete Posts
	 *
	 * @param array  $posts .
	 * @param string $type .
	 */
	public function delete_posts( $posts, $type = 'post' ) {
		foreach ( $posts as $post_id ) {
			switch ( $type ) {
				case 'media':
					wp_delete_attachment( $post_id, true );
					break;
				case 'post':
				default:
					wp_delete_post( $post_id, true );
					break;
			}
		}
	}

	/**
	 * Get Companion Theme Global Color
	 */
	public function get_companion_global_color() {
		$theme       = wp_get_theme();
		$global_data = get_page_by_path( sprintf( 'wp-global-styles-%s', urlencode( $theme->get_stylesheet() ) ), OBJECT, 'wp_global_styles' );

		if ( $global_data ) {
			wp_delete_post( $global_data->ID, false );
		}
	}

	/**
	 * Get demo data
	 *
	 * @param object $request .
	 *
	 * @return boolean
	 */
	public function demo_get( $request ) {
		$theme = wp_get_theme();

		/**Check if file exist */
		$upload_dir       = wp_upload_dir();
		$upload_base_path = $upload_dir['basedir'];
		$file_path        = $upload_base_path . '/gutenverse-companion/' . $theme->get_stylesheet() . '/data.json';

		global $wp_filesystem;

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		/**Check schedule fetch */
		$companion_data = get_option( 'gutenverse-companion-' . urlencode( $theme->get_stylesheet() ), false );
		$fetch_time     = null;
		$now            = time();
		if ( $companion_data ) {
			$fetch_time = $companion_data['fetch_time'];
		}
		$this->update_demo_data( $request );

		if ( null === $fetch_time || $fetch_time < $now ) {
			/**Update demo data and fetch time */
			$this->update_demo_data( $request );
			$next_fetch = $now + ( 24 * 60 * 60 );
			update_option(
				'gutenverse-companion-' . urlencode( $theme->get_stylesheet() ),
				array(
					'fetch_time' => $next_fetch,
				)
			);
		}

		if ( ! $wp_filesystem->exists( $file_path ) ) {
			$this->update_demo_data( $request );
		}

		$data = $this->demo_data( $request );
		return rest_ensure_response( $data );
	}

	/**
	 * Demo data
	 *
	 * @param object $request .
	 * @return array
	 */
	public function demo_data( $request ) {
		/**Get file Path */
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;
		$basedir   = wp_upload_dir()['basedir'];
		$theme     = wp_get_theme();
		$directory = $basedir . '/gutenverse-companion/' . $theme->get_stylesheet();
		if ( ! is_dir( $directory ) ) {
			wp_mkdir_p( $directory );
		}
		$file_path = $directory . '/data.json';

		/**Get Json Data */
		$json = array();
		if ( $wp_filesystem->exists( $file_path ) ) {
			$file = $wp_filesystem->get_contents( $file_path );
			$json = json_decode( $file, true );
		}

		/**Get License Tier */
		$key      = sanitize_text_field( $request->get_param( 'key' ) );
		$arr_tier = array(
			'general',
		);
		if ( $key ) {
			$request_body = wp_json_encode(
				array(
					'key'    => $key,
					'domain' => $request->get_header( 'origin' ),
				)
			);
			$response     = wp_remote_post(
				GUTENVERSE_LICENSE_SERVER . '/wp-json/gutenverse-pro/v2/license/tier',
				array(
					'body'      => $request_body,
					'headers'   => array(
						'Content-Type' => 'application/json',
						'Origin'       => $request->get_header( 'origin' ),
					),
					'sslverify' => false,
				)
			);

			if ( is_wp_error( $response ) ) {
				$error_message = $response->get_error_message();
				return new WP_REST_Response(
					array(
						'message' => 'Unable to fetch tier license : ' . $error_message,
					),
					400
				);
			}
			$tier_levels = array(
				'personal'     => array( 'general', 'personal' ),
				'professional' => array( 'general', 'personal', 'professional' ),
				'agency'       => array( 'general', 'personal', 'professional', 'agency' ),
				'enterprise'   => array( 'general', 'personal', 'professional', 'agency', 'enterprise' ),
			);
			$tier        = json_decode( wp_remote_retrieve_body( $response ) );
			$arr_tier    = $tier_levels[ $tier ];
		}

		/**Add status to array list */
		if ( isset( $json['demo_list'] ) ) {
			foreach ( $json['demo_list'] as &$demo ) {
				$name       = $demo['title'];
				$upload_dir = wp_upload_dir();
				$target_dir = trailingslashit( $upload_dir['basedir'] ) . GUTENVERSE_COMPANION . '/' . trim( preg_replace( '/[^a-z0-9]+/i', '-', strtolower( $name ) ), '-' ) . '/demo';

				global $wp_filesystem;

				if ( ! function_exists( 'WP_Filesystem' ) ) {
					require_once ABSPATH . 'wp-admin/includes/file.php';
				}

				WP_Filesystem();

				$demo['status']['exists']         = (bool) $wp_filesystem->is_dir( $target_dir );
				$demo['status']['using_template'] = isset( get_option( 'gutenverse_companion_template_options' )['active_demo'] ) && get_option( 'gutenverse_companion_template_options' )['active_demo'] === $name;
				$need_upgrade                     = false;
				if ( ! in_array( $demo['tier'], $arr_tier, false ) ) {
					$need_upgrade = true;
				}
				$all_tier = array( 'general', 'personal', 'professional', 'agency', 'enterprise' );
				$tier_index = array_search( $demo['tier'], $all_tier );
				if ( $index !== false ) {
					$tiers_after                     = array_slice( $all_tier, $tier_index + 1 );
					$demo['status']['required_tier'] = $tiers_after;

				}
				$demo['status']['need_upgrade'] = $need_upgrade;

			}
			unset( $demo );
		}
		return $json;
	}

	/**
	 * Update Demo Data
	 *
	 * @param object $request .
	 */
	public function update_demo_data( $request ) {
		$theme_slug = sanitize_text_field( $request->get_param( 'theme_slug' ) );
		$key        = sanitize_text_field( $request->get_param( 'key' ) );

		/**Check if directory exist */
		$basedir   = wp_upload_dir()['basedir'];
		$theme     = wp_get_theme();
		$directory = $basedir . '/gutenverse-companion/' . $theme->get_stylesheet();
		if ( ! is_dir( $directory ) ) {
			wp_mkdir_p( $directory );
		}
		$file_path = $directory . '/data.json';

		/**Fetch data */
		$request_body = wp_json_encode(
			array(
				'base_theme' => $theme_slug,
				'key'        => $key,
			)
		);
		$response     = wp_remote_post(
			GUTENVERSE_COMPANION_LIBRARY_URL . 'wp-json/gutenverse-server/v4/companion/list',
			array(
				'body'    => $request_body,
				'headers' => array(
					'Content-Type' => 'application/json',
					'Origin'       => $request->get_header( 'origin' ),
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'request_failed', 'Unable to fetch demo data', array( 'status' => 500 ) );
		}

		$response_body = wp_remote_retrieve_body( $response );
		/**Save data to json file */
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;
		$wp_filesystem->put_contents( $file_path, $response_body, FS_CHMOD_FILE );
	}

	/**
	 * Get patterns from PHP files in the specified directory.
	 *
	 * @param WP_REST_Request $request The request instance.
	 * @return WP_REST_Response|WP_Error The response object or error.
	 */
	public function pattern_get( $request ) {
		$name = sanitize_text_field( $request->get_param( 'template' ) );

		$upload_dir = wp_upload_dir();
		$target_dir = trailingslashit( $upload_dir['basedir'] ) . GUTENVERSE_COMPANION . '/' . trim( preg_replace( '/[^a-z0-9]+/i', '-', strtolower( $name ) ), '-' ) . '/demo/patterns/';

		if ( ! file_exists( $target_dir ) || ! is_dir( $target_dir ) ) {
			return new WP_Error( 'invalid_directory', 'The specified directory does not exist or is not a directory.', array( 'status' => 404 ) );
		}

		$php_files = glob( trailingslashit( $target_dir ) . '*.php' );

		if ( empty( $php_files ) ) {
			return new WP_Error( 'no_files', 'No PHP files found in the specified directory.', array( 'status' => 404 ) );
		}

		$valid_arrays = array();

		foreach ( $php_files as $file_path ) {
			$file_data           = include $file_path;
			$file_data['images'] = json_decode( $file_data['images'], true );
			if ( is_array( $file_data ) ) {
				$valid_arrays[ str_replace( '.php', '', basename( $file_path ) ) ] = $file_data;
			}
		}

		if ( empty( $valid_arrays ) ) {
			return new WP_Error( 'no_valid_arrays', 'No valid arrays found in the PHP files.', array( 'status' => 400 ) );
		}
		return rest_ensure_response( $valid_arrays );
	}

	/**
	 * Get patterns from PHP files in the specified directory.
	 *
	 * @param WP_REST_Request $request The request instance.
	 * @return WP_REST_Response|WP_Error The response object or error.
	 */
	public function pattern_insert( $request ) {
		$content    = $request->get_param( 'content' );
		$slug       = sanitize_text_field( $request->get_param( 'slug' ) );
		$title      = sanitize_text_field( $request->get_param( 'title' ) );
		$title_demo = sanitize_text_field( $request->get_param( 'demo_slug' ) );
		$additional = sanitize_text_field( $request->get_param( 'additional' ) );

		$additional = json_decode( $additional, true );

		$meta_key   = 'gutenverse_companion_pattern_slug';
		$meta_value = $slug;

		$existing_block_query = new \WP_Query(
			array(
				'post_type'   => 'wp_block',
				'meta_key'    => $meta_key,
				'meta_value'  => $meta_value,
				'post_status' => 'publish',
				'fields'      => 'ids',
			)
		);

		if ( isset( $additional ) ) {
			foreach ( $additional as $datas ) {
				foreach ( $datas as $key => $data ) {
					if ( 'acf-data' === $key && function_exists( 'acf_determine_internal_post_type' ) ) {
						foreach ( $data as $to_import ) {
							$post_type = acf_determine_internal_post_type( $to_import['key'] );
							$post      = acf_get_internal_post_type_post( $to_import['key'], $post_type );

							if ( $post ) {
								$to_import['ID'] = $post->ID;
							}
							$to_import = acf_import_internal_post_type( $to_import, $post_type );
						}
					} elseif ( 'post-demo' === $key ) {
						foreach ( $data as $post_data ) {
							$existing_post = get_posts(
								array(
									'title'       => $post_data['title'],
									'post_type'   => $post_data['type'],
									'post_status' => 'any',
									'numberposts' => 1,
								)
							);

							if ( ! empty( $existing_post ) ) {
								continue;
							}

							$featured_image_id = null;
							if ( ! empty( $post_data['featured_image'] ) ) {
								$image_data = $this->check_image_exist( $post_data['featured_image'] );
								if ( ! $image_data ) {
									$image_data = $this->import_image( $post_data['featured_image'] );
								}
								$featured_image_id = $image_data['id'];
							}

							$post_args = array(
								'post_title'    => $post_data['title'],
								'post_content'  => $post_data['content'],
								'post_excerpt'  => $post_data['excerpt'],
								'post_status'   => $post_data['status'],
								'post_type'     => $post_data['type'],
								'post_author'   => get_current_user_id(),
								'post_date'     => $post_data['date'],
								'post_modified' => $post_data['modified'],
							);

							$post_id = wp_insert_post( $post_args );

							if ( $post_id && ! is_wp_error( $post_id ) ) {
								if ( ! empty( $post_data['meta'] ) ) {
									foreach ( $post_data['meta'] as $meta_key => $meta_values ) {
										$meta_values = maybe_unserialize( $meta_values );
										if ( is_array( $meta_values ) ) {
											$processed_array = array();
											foreach ( $meta_values as $item ) {
												if ( filter_var( $item, FILTER_VALIDATE_URL ) ) {
													$image_data = $this->check_image_exist( $item );
													if ( ! $image_data ) {
														$image_data = $this->import_image( $item );
													}
													$attachment_id     = $image_data['id'];
													$processed_array[] = $attachment_id ? $attachment_id : $item;
												} else {
													$processed_array[] = $item;
												}
											}
											update_post_meta( $post_id, $meta_key, $processed_array );
										} elseif ( filter_var( $meta_values, FILTER_VALIDATE_URL ) ) {
											$image_data = $this->check_image_exist( $meta_values );
											if ( ! $image_data ) {
												$image_data = $this->import_image( $meta_values );
											}
											$attachment_id = $image_data['id'];
											update_post_meta( $post_id, $meta_key, $attachment_id ? $attachment_id : $meta_values );
										} else {
											update_post_meta( $post_id, $meta_key, $meta_values );
										}
									}
								}

								if ( $featured_image_id ) {
									set_post_thumbnail( $post_id, $featured_image_id );
								}

								if ( ! empty( $post_data['attached_images'] ) ) {
									foreach ( $post_data['attached_images'] as $image_url ) {
										$image_data = $this->check_image_exist( $image_url );
										if ( ! $image_data ) {
											$image_data = $this->import_image( $image_url );
										}
										$attachment_id = $image_data['id'];
										if ( $attachment_id ) {
											wp_update_post(
												array(
													'ID' => $attachment_id,
													'post_parent' => $post_id,
												)
											);
										}
									}
								}
							}
						}
					}
				}
			}
		}

		if ( $existing_block_query->have_posts() ) {
			return rest_ensure_response(
				array(
					'slug' => $slug,
					'id'   => $existing_block_query->posts[0],
				)
			);
		}

		$demo_slug    = strtolower( str_replace( ' ', '-', $title_demo ) );
		$pattern_list = get_option( $demo_slug . '_' . get_stylesheet() . '_companion_synced_pattern_imported', false );

		$content = $this->check_navbar( $content );

		$block_data = array(
			'post_title'   => $title,
			'post_content' => wp_slash( $content ),
			'post_status'  => 'publish',
			'post_type'    => 'wp_block',
		);

		$post_id          = wp_insert_post( $block_data );
		$imported_options = get_option( 'gutenverse-companion-imported-options' );
		if ( $imported_options ) {
			$imported_patterns            = $imported_options['patterns'];
			$imported_patterns[]          = $post_id;
			$imported_options['patterns'] = $imported_patterns;
			update_option( 'gutenverse-companion-imported-options', $imported_options );
		}
		update_post_meta( $post_id, $meta_key, $meta_value );

		$pattern_list[] = array(
			'slug'     => $slug,
			'title'    => $title,
			'content'  => '<!-- wp:block {"ref":' . $post_id . '} /-->',
			'inserter' => false,
		);

		update_option( $demo_slug . '_' . get_stylesheet() . '_companion_synced_pattern_imported', $pattern_list );

		return rest_ensure_response(
			array(
				'slug' => $slug,
				'id'   => $post_id ?? '',
			)
		);
	}

	/**
	 * Import demo pages
	 *
	 * @param object $request .
	 *
	 * @return boolean
	 */
	public function demo_pages( $request ) {
		global $wp_filesystem;
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();

		$name       = sanitize_text_field( $request->get_param( 'template' ) );
		$misc       = sanitize_text_field( $request->get_param( 'misc' ) );
		$pattern    = $request->get_param( 'pattern' );
		$upload_dir = wp_upload_dir();
		$target_dir = trailingslashit( $upload_dir['basedir'] ) . GUTENVERSE_COMPANION . '/' . trim( preg_replace( '/[^a-z0-9]+/i', '-', strtolower( $name ) ), '-' ) . '/demo/gutenverse-pages/';
		$files      = glob( $target_dir . '/*' );
		$pages      = array();

		foreach ( $files as $file ) {
			$json_file_data = $wp_filesystem->get_contents( $file );
			$pages[]        = json_decode( $json_file_data, true );
		}

		foreach ( $pages as $value ) {
			$page_id = null;
			$content = $value['content'];
			$images  = $value['images'];

			foreach ( $pattern as $pat ) {
				foreach ( $pat as $key => $id ) {
					$content = str_replace( "{{{$key}}}", $id, $content );
				}
			}

			foreach ( $images as $index => $url ) {
				$url  = json_decode( $url );
				$data = $this->check_image_exist( $url );
				if ( ! $data ) {
					$data = $this->import_image( $image );
				}
				$final_url   = $data['url'];
				$placeholder = '{{{image:' . $index . ':url}}}';
				$content     = str_replace( $placeholder, $final_url, $content );
			}

			$content = $this->check_navbar( $content );

			$new_page = array(
				'post_title'    => $value['pagetitle'],
				'post_content'  => wp_slash( $content ),
				'post_status'   => 'publish',
				'post_type'     => 'page',
				'page_template' => $value['template'],
			);

			$original_title = $new_page['post_title'];
			$suffix         = 2;

			$args = array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'title'       => $original_title,
				'fields'      => 'ids',
			);

			$query = new \WP_Query( $args );

			while ( $query->have_posts() ) {
				if ( 'do-not-import' === $misc ) {
					return true;
				} elseif ( 'keep-a-copy' === $misc ) {
					$new_page['post_title'] = $original_title . ' #' . $suffix;

					$query = new \WP_Query(
						array(
							'post_type'   => 'page',
							'post_status' => 'publish',
							'title'       => $new_page['post_title'],
							'fields'      => 'ids',
						)
					);

					++$suffix;
				} elseif ( 'replace' === $misc ) {
					$query->the_post();
					$post_id = get_the_ID();

					wp_update_post(
						array(
							'ID'           => $post_id,
							'post_content' => wp_slash( $content ),
						)
					);

					return true;
				}
			}

			$page_id          = wp_insert_post( $new_page );
			$imported_options = get_option( 'gutenverse-companion-imported-options' );
			if ( $imported_options ) {
				$imported_page             = $imported_options['pages'];
				$imported_page[]           = $page_id;
				$imported_options['pages'] = $imported_page;
				update_option( 'gutenverse-companion-imported-options', $imported_options );
			}

			if ( $value['is_homepage'] && $page_id ) {
				update_option( 'show_on_front', 'page' );
				update_option( 'page_on_front', $page_id );
			}
		}

		return true;
	}

	/**
	 * Check Navbar if exists change the menuId
	 *
	 * @param string $content .
	 *
	 * @return string
	 */
	public function check_navbar( $content ) {
		$html_blocks = parse_blocks( $content );
		$blocks      = _flatten_blocks( $html_blocks );

		foreach ( $blocks as $block ) {
			if ( 'gutenverse/nav-menu' === $block['blockName'] ) {
				$block_before = serialize_block( $block );
				$block_after  = '';

				if ( ! empty( $block['attrs']['menuId'] ) ) {
					$original_menu_id = $block['attrs']['menuId'];
					$menu_exists      = wp_get_nav_menu_object( 'menu-' . $original_menu_id );

					if ( ! $menu_exists ) {
						$menu_id = wp_create_nav_menu( 'menu-' . $original_menu_id );
						wp_update_nav_menu_item(
							$menu_id,
							0,
							array(
								'menu-item-title'  => 'Home',
								'menu-item-url'    => home_url( '/' ),
								'menu-item-status' => 'publish',
							)
						);
						$imported_options = get_option( 'gutenverse-companion-imported-options' );
						if ( $imported_options ) {
							$imported_menus            = $imported_options['menus'];
							$imported_menus[]          = array(
								'original_menu_id' => $original_menu_id,
								'created_menu_id'  => $menu_id,
								'menu_name'        => 'menu-' . $original_menu_id,
							);
							$imported_options['menus'] = $imported_menus;
							update_option( 'gutenverse-companion-imported-options', $imported_options );
						}
					} else {
						$menu_id = $menu_exists->term_id;
					}
					$block['attrs']['menuId'] = $menu_id;
					$block_after              = serialize_block( $block );
				}

				$content = str_replace( $block_before, $block_after, $content );
			}
		}
		return $content;
	}

	/**
	 * Import demo pages
	 *
	 * @param object $request .
	 *
	 * @return boolean
	 */
	public function import_menus( $request ) {
		global $wp_filesystem;
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();

		$upload_dir = wp_upload_dir();
		$name       = sanitize_text_field( $request->get_param( 'template' ) );
		$target_dir = trailingslashit( $upload_dir['basedir'] ) . GUTENVERSE_COMPANION . '/' . trim( preg_replace( '/[^a-z0-9]+/i', '-', strtolower( $name ) ), '-' ) . '/demo/misc/menu.json';

		/**Get Json Data */
		$json = array();
		if ( $wp_filesystem->exists( $target_dir ) ) {
			$file = $wp_filesystem->get_contents( $target_dir );
			$json = json_decode( $file, true );
			foreach ( $json as $menu ) {
				$original_menu_id = $menu['menu_id'];
				$menu_exists      = wp_get_nav_menu_object( 'menu-' . $original_menu_id );

				if ( $menu_exists ) {
					/**Remove Dummy Item */
					foreach ( $menu_items as $item ) {
						wp_delete_post( $item->ID, true );
					}

					/**Add Actual Item */
					$menu_id   = $menu_exists->term_id;
					$parent_id = array();
					foreach ( $menu['menu_data'] as $idx => $data ) {
						$menu_parent = 0;
						$url         = $data['url'];
						if ( null !== $data['parent'] ) {
							foreach ( $parent_id as $pr_id ) {
								if ( strval( $pr_id['idx'] ) === strval( $data['parent'] ) ) {
									$menu_parent = $pr_id['menu_id'];
								}
							}
						}
						if ( $data['object_slug'] && ( 'page' === $data['type'] ) ) {
							$args = array(
								'name'        => $data['object_slug'],
								'post_type'   => 'page',
								'post_status' => 'publish',
								'numberposts' => 1,
							);

							$query = new WP_Query( $args );

							if ( $query->have_posts() ) {
								$page = $query->posts[0]; // Get the first result
							} else {
								$page = null; // No page found
							}

							wp_reset_postdata();
							if ( $page ) {
								$url = get_permalink( $page->ID );
							}
						}

						$menu_items   = wp_get_nav_menu_items( $menu_id );
						$menu_item_id = 0;
						if ( $menu_items ) {
							foreach ( $menu_items as $menu_item ) {
								if ( $menu_item->title === $data['title'] ) {
									$menu_item_id = $menu_item->ID;
								}
							}
						}
						$menu_item_id = wp_update_nav_menu_item(
							$menu_id,
							$menu_item_id,
							array(
								'menu-item-title'     => $data['title'],
								'menu-item-url'       => $url,
								'menu-item-status'    => 'publish',
								'menu-item-parent-id' => $menu_parent,
							)
						);
						if ( $data['have_child'] ) {
							$parent_id[] = array(
								'idx'     => $idx,
								'menu_id' => $menu_item_id,
							);
						}
					}
				}
			}
		}
		return true;
	}
}
