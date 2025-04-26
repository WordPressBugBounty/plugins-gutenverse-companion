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

		$upload_dir   = wp_upload_dir();
		$target_dir   = trailingslashit( $upload_dir['basedir'] ) . GUTENVERSE_COMPANION . '/' . trim( preg_replace( '/[^a-z0-9]+/i', '-', strtolower( $name ) ), '-' );
		$source_dir   = $target_dir . '/demo/templates';
		$template_dir = $target_dir . '/templates';

		global $wp_filesystem;

		if ( ! function_exists( 'request_filesystem_credentials' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! $wp_filesystem->is_dir( $source_dir ) ) {
			echo 'Source directory does not exist!';
			return false;
		}

		if ( ! $wp_filesystem->is_dir( $template_dir ) ) {
			$wp_filesystem->mkdir( $template_dir );
		}

		$html_files = $wp_filesystem->dirlist( $source_dir, true );

		foreach ( $html_files as $file_name => $file_info ) {
			if ( 'html' === pathinfo( $file_name, PATHINFO_EXTENSION ) ) {
				$file_path = trailingslashit( $source_dir ) . $file_name;
				$content   = $wp_filesystem->get_contents( $file_path );

				foreach ( $pattern as $pat ) {
					foreach ( $pat as $key => $id ) {
						$content = str_replace( "{{{$key}}}", $id, $content );
					}
				}

				$target_file_path = trailingslashit( $template_dir ) . $file_name;
				$wp_filesystem->put_contents( $target_file_path, $content );

			}
		}

		$this->restore_templates( $name );

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
		$file      = esc_url( $request->get_param( 'file' ) );
		$installed = sanitize_text_field( $request->get_param( 'installed' ) );
		$active    = sanitize_text_field( $request->get_param( 'active' ) );

		if ( $active ) {
			$this->backup_templates( $active );
		}

		return $this->demo_download( $file, $name, $installed );
	}

	/**
	 * Restore Edited Tempaltes
	 *
	 * @param string $name .
	 */
	public function restore_templates( $name ) {
		$upload_dir = wp_upload_dir();
		$target_dir = trailingslashit( $upload_dir['basedir'] ) . GUTENVERSE_COMPANION . '/' . trim( preg_replace( '/[^a-z0-9]+/i', '-', strtolower( $name ) ), '-' ) . '/backup-templates';

		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ( $wp_filesystem->is_dir( $target_dir ) ) {
			$files = $wp_filesystem->dirlist( $target_dir );

			if ( ! empty( $files ) ) {
				foreach ( $files as $file_name => $file_info ) {
					$file_path = trailingslashit( $target_dir ) . $file_name;

					if ( strpos( $file_name, '.json' ) !== false ) {
						$json_data = $wp_filesystem->get_contents( $file_path );
						$post_data = json_decode( $json_data, true );

						if ( is_array( $post_data ) && ! empty( $post_data ) ) {

							$post_id = wp_insert_post(
								array(
									'post_type'    => 'wp_template',
									'post_name'    => $post_data['post_name'],
									'post_title'   => $post_data['post_title'],
									'post_content' => $post_data['post_content'],
									'post_excerpt' => $post_data['post_excerpt'],
									'post_status'  => 'publish',
									'meta_input'   => array(
										'origin' => 'theme',
									),
								)
							);

							if ( ! is_wp_error( $post_id ) ) {
								wp_set_object_terms( $post_id, get_option( 'stylesheet' ), 'wp_theme' );
							}
						}
					}
				}

				$wp_filesystem->rmdir( $target_dir, true );
			}
		}
	}

	/**
	 * Backup Edited Tempaltes
	 *
	 * @param string $name .
	 */
	public function backup_templates( $name ) {
		$saved_template_query = new \WP_Query(
			array(
				'post_type'   => 'wp_template',
				'meta_key'    => 'origin',
				'meta_value'  => 'theme',
				'post_status' => 'publish',
			)
		);

		if ( $saved_template_query->have_posts() ) {
			foreach ( $saved_template_query->posts as $post ) {
				$upload_dir = wp_upload_dir();
				$target_dir = trailingslashit( $upload_dir['basedir'] ) . GUTENVERSE_COMPANION . '/' . trim( preg_replace( '/[^a-z0-9]+/i', '-', strtolower( $name ) ), '-' ) . '/backup-templates';

				if ( ! file_exists( $target_dir ) ) {
					wp_mkdir_p( $target_dir );
				}

				$post_data = array(
					'post_name'    => $post->post_name,
					'post_title'   => $post->post_title,
					'post_content' => $post->post_content,
					'post_excerpt' => $post->post_excerpt,
				);

				$json_data = wp_json_encode( $post_data, JSON_PRETTY_PRINT );

				$file_name = $post->post_name . '.json';

				$file_path = trailingslashit( $target_dir ) . $file_name;

				global $wp_filesystem;
				if ( ! $wp_filesystem ) {
					require_once ABSPATH . 'wp-admin/includes/file.php';
					WP_Filesystem();
				}

				if ( $wp_filesystem->put_contents( $file_path, $json_data, FS_CHMOD_FILE ) ) {
					wp_delete_post( $post->ID, true );
				}
			}
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
		$theme_slug = sanitize_text_field( $request->get_param( 'theme_slug' ) );
		$key        = sanitize_text_field( $request->get_param( 'key' ) );

		$request_body = wp_json_encode(
			array(
				'base_theme' => $theme_slug,
				'key'        => $key,
			)
		);

		$response = wp_remote_post(
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
		$data          = json_decode( $response_body, true );

		if ( empty( $data ) ) {
			return new \WP_Error( 'no_data', 'No demo data found', array( 'status' => 404 ) );
		}

		if ( isset( $data['demo_list'] ) ) {
			foreach ( $data['demo_list'] as &$demo ) {
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
			}
			unset( $demo );
		}

		return rest_ensure_response( $data );
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
			$file_data = include $file_path;
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
		$content = $request->get_param( 'content' );
		$slug    = sanitize_text_field( $request->get_param( 'slug' ) );
		$title   = sanitize_text_field( $request->get_param( 'title' ) );

		$meta_key   = 'gutenverse_companion_pattern_slug';
		$meta_value = $slug;

		$existing_block_query = new \WP_Query(
			array(
				'post_type'   => 'wp_block',
				'meta_key'    => $meta_key,
				'meta_value'  => $meta_value,
				'post_status' => 'publish',
				'fields'      => 'ids', // Only get post IDs.
			)
		);

		if ( $existing_block_query->have_posts() ) {
			return rest_ensure_response(
				array(
					'slug' => $slug,
					'id'   => $existing_block_query->posts[0],
				)
			);
		}

		$block_data = array(
			'post_title'   => $title,
			'post_content' => wp_slash( $content ),
			'post_status'  => 'publish',
			'post_type'    => 'wp_block',
		);

		$post_id = wp_insert_post( $block_data );

		if ( $post_id && ! is_wp_error( $post_id ) ) {
			update_post_meta( $post_id, $meta_key, $meta_value );
			return rest_ensure_response(
				array(
					'slug' => $slug,
					'id'   => $post_id,
				)
			);
		}
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

			foreach ( $pattern as $pat ) {
				foreach ( $pat as $key => $id ) {
					$content = str_replace( "{{{$key}}}", $id, $content );
				}
			}

			$new_page = array(
				'post_title'    => $value['pagetitle'] . ' - ' . $name,
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
			}

			$page_id = wp_insert_post( $new_page );

			if ( $value['is_homepage'] && $page_id ) {
				update_option( 'show_on_front', 'page' );
				update_option( 'page_on_front', $page_id );
			}
		}

		return true;
	}
}
