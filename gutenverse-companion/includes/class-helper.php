<?php
/**
 * Helper class
 *
 * @author Jegstudio
 * @package gutenverse-companion
 */

namespace Gutenverse_Companion;

use WP_Post;
use WP_Query;
use WP_REST_Response;

/**
 * Class Helper
 *
 * @package gutenverse-companion
 */
class Helper {
	/**
	 * Return image
	 *
	 * @param string $url Image attachment url.
	 *
	 * @return array|null
	 */
	public static function check_image_exist( $url ) {
		$attachments = new \WP_Query(
			array(
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'meta_query'  => array(
					array(
						'key'     => '_import_source',
						'value'   => $url,
						'compare' => '=',
					),
				),
			)
		);
		if ( $attachments->have_posts() ) {
			foreach ( $attachments->posts as $post ) {
				$attachment_url = wp_get_attachment_url( $post->ID );
				return array(
					'id'  => $post->ID,
					'url' => $attachment_url,
				);
			}
		}

		return false;
	}

	/**
	 * Handle Import file, and return File ID when process complete
	 *
	 * @param string $url URL of file.
	 * @param string $alt Alt text of image.
	 *
	 * @return int|null
	 */
	public static function handle_file( $url, $alt = '' ) {
		$file_name = basename( $url );
		$upload    = wp_upload_bits( $file_name, null, '' );
		self::fetch_file( $url, $upload['file'] );

		if ( $upload['file'] ) {
			$file_loc  = $upload['file'];
			$file_name = basename( $upload['file'] );
			$file_type = wp_check_filetype( $file_name );

			$attachment = array(
				'post_mime_type' => $file_type['type'],
				'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $file_name ) ),
				'post_content'   => '',
				'post_status'    => 'inherit',
			);

			include_once ABSPATH . 'wp-admin/includes/image.php';
			$attach_id = wp_insert_attachment( $attachment, $file_loc );
			update_post_meta( $attach_id, '_import_source', $url );
			if ( ! empty( $alt ) ) {
				update_post_meta( $attach_id, '_wp_attachment_image_alt', $alt );
			}

			try {
				$attach_data = wp_generate_attachment_metadata( $attach_id, $file_loc );
				wp_update_attachment_metadata( $attach_id, $attach_data );
			} catch ( \Exception $e ) {
				gutenverse_rlog( $e->getMessage() );
			}

			return array(
				'id'  => $attach_id,
				'url' => $upload['url'],
			);
		} else {
			return null;
		}
	}
	/**
	 * Download file and save to file system
	 *
	 * @param string $url File URL.
	 * @param string $file_path file path.
	 * @param string $endpoint Endpoint.
	 *
	 * @return array|bool
	 */
	public static function fetch_file( $url, $file_path, $endpoint = '' ) {
		$http     = new \WP_Http();
		$response = $http->get(
			add_query_arg(
				array(
					'sslverify' => false,
				),
				$url
			)
		);
		if ( is_wp_error( $response ) ) {
			return false;
		}

		$headers             = wp_remote_retrieve_headers( $response );
		$headers['response'] = wp_remote_retrieve_response_code( $response );

		if ( false === $file_path ) {
			return $headers;
		}

		$body = wp_remote_retrieve_body( $response );

		// GET request - write it to the supplied filename.
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;
		$wp_filesystem->put_contents( $file_path, $body, FS_CHMOD_FILE );

		return $headers;
	}
	/**
	 * Create pages and assign templates.
	 *
	 * @param object $request .
	 * @param bool   $replace_if_exist .
	 *
	 * @return int|string
	 */
	public function handle_pages( $request, $replace_if_exist = false ) {
		global $wp_filesystem;

		require_once ABSPATH . 'wp-admin/includes/file.php';

		if ( ! WP_Filesystem() ) {
			return new \WP_Error( 'filesystem_error', 'Filesystem not available.' );
		}

		$title = $request->get_param( 'title' );
		if ( empty( $title ) ) {
			return new \WP_Error( 'missing_title', 'Page title is required.' );
		}

		$theme_mode = apply_filters( 'gutenverse_plus_mechanism_content_type', 'normal' );
		if ( 'normal' === $theme_mode ) {
			$theme_mode = apply_filters( 'gutenverse_jegtheme_theme_type', 'normal' );
		}
		$theme_dir   = get_template_directory();
		$theme_url   = get_template_directory_uri();
		$theme_slug  = get_option( 'stylesheet' );
		$active_slug = get_stylesheet();

		$page_slug = strtolower( str_replace( ' ', '_', $title ) );
		$json_path = "{$theme_dir}/gutenverse-pages/{$page_slug}.json";

		if ( ! $wp_filesystem->exists( $json_path ) ) {
			return new \WP_Error( 'file_not_found', 'Page definition not found.' );
		}

		$page = json_decode( $wp_filesystem->get_contents( $json_path ), true );

		if ( empty( $page ) ) {
			return new \WP_Error( 'invalid_json', 'Invalid page JSON.' );
		}

		$inserted_dummies = get_option(
			'gutenverse_' . $active_slug . '_dummy_inserted',
			array(
				'posts'                => array(),
				'posts-dummies'        => array(),
				'products'             => array(),
				'products-dummies'     => array(),
				'category'             => array(),
				'category-dummies'     => array(),
				'post_tag'             => array(),
				'post_tag-dummies'     => array(),
				'product_cat'          => array(),
				'product_cat-dummies'  => array(),
				'product_tag'          => array(),
				'product_tag-dummies'  => array(),
				'post_content_images'  => array(),
				'post_featured_images' => array(),
			)
		);

		$inserted_content = get_option(
			"gutenverse_{$active_slug}_content_inserted",
			array(
				'pages'             => array(),
				'patterns'          => array(),
				'menus'             => array(),
				'content_has_menus' => array(),
			)
		);

		/*
		--------------------
		* Content preparation
		* --------------------
		*/
		$content = str_replace(
			array( '{{home_url}}', "\'" ),
			array( $theme_url, "'" ),
			$page['content']
		);

		/*
		--------------------
		* Import patterns
		* --------------------
		*/
		foreach ( array( 'core-patterns', 'pro-patterns', 'gutenverse-patterns' ) as $pattern_key ) {
			if ( ! empty( $page[ $pattern_key ] ) ) {
				$this->import_patterns( $page[ $pattern_key ], $theme_slug, $inserted_content );
			}
		}

		/*
		--------------------
		* Image handling
		* --------------------
		*/
		$image_importer_ver = $page['image_importer_ver'] ?? null;
		if ( ! empty( $page['image_arr'] ) ) {
			$images = json_decode( $page['image_arr'] );
			/**
			 * 1. Convert single quotes to double quotes
			 * 2. Decode as JSON
			 */
			$json   = preg_replace( "/'/", '"', $page['image_arr'] );
			$images = json_decode( $json, true );
			if ( is_array( $images ) ) {
				if ( ! $image_importer_ver ) {
					$content = preg_replace( '/(?<!\\\\)\\\\u([0-9a-fA-F]{4})/', '\\\\\\\\u$1', $content );

					foreach ( $images as $image ) {
						$img_url = $image['image_url'];
						$img_id  = $image['image_id'];
						if ( empty( $img_url ) ) {
							$img_url = $image->image_url;
							$img_id  = $image->image_id;
						}

						$data = self::check_image_exist( $img_url );
						if ( ! $data ) {
							$data = self::handle_file( $img_url );
						}

						if ( ! empty( $data['url'] ) ) {
							$content = str_replace( $img_url, $data['url'], $content );
						}

						if ( ! empty( $img_id ) && 'null' !== $img_id ) {
							$content = str_replace(
								'"imageId":' . $img_id,
								'"imageId":' . $data['id'],
								$content
							);
						}
					}
				} else {
					foreach ( $images as $key => $image ) {
						$url     = $key;
						$pattern = $image['pattern'];
						$data    = self::check_image_exist( $url );
						$alt     = isset( $image['alt'] ) ?? '';
						if ( ! $data ) {
							$data = self::handle_file( $url, $alt );
						}
						foreach ( $pattern as $p ) {
							$placeholder_arr        = explode( '|', trim( $p, '{}' ) );
							$placeholder_value_type = end( $placeholder_arr );
							switch ( $placeholder_value_type ) {
								case 'url':
									$placeholder_data_type = $placeholder_arr[1];
									if ( 'case2' === $placeholder_data_type ) {
										$placeholder_data_size = $placeholder_arr[3];
										$target                = wp_get_attachment_image_url( $data['id'], $placeholder_data_size );
									} else {
										$target = wp_get_attachment_url( $data['id'] );
									}
									break;
								case 'id':
								default:
									$target = $data['id'];
									break;
							}
							$content = str_replace( $p, $target, $content );
						}
					}
				}
			}
		}

		/*
		--------------------
		* Page creation/update
		* --------------------
		*/
		$page_id = null;
		$content = $this->pattern_fix_content_attribute_value( $content );
		$content = $this->escape_specific_regex_pattern( $content );

		// Step 1: Ensure at least one backslash.
		$content = preg_replace( '/(?<!\\\\)u([0-9a-fA-F]{4})/', '\\\\u$1', $content );

		$content = preg_replace_callback(
			'/\\\\{1,3}u([0-9a-fA-F]{4})/',
			function ( $m ) {
				return '\\u' . $m[1]; // always 4 slashes.
			},
			$content
		);
		$content = $this->decode_unicode_sequences( $content );

		if ( 'news' === $theme_mode ) {

			$page_id = wp_insert_post(
				array(
					'post_title'    => $page['pagetitle'],
					'post_content'  => $content,
					'post_status'   => 'publish',
					'post_type'     => 'page',
					'page_template' => $page['template'],
				)
			);

		} else {
			$query = new \WP_Query(
				array(
					'post_type'      => 'page',
					'post_status'    => 'publish',
					'name'           => sanitize_title( $page['pagetitle'] ),
					'posts_per_page' => 1,
				)
			);

			if ( $query->have_posts() ) {
				$existing = $query->posts[0];
				$page_id  = $existing->ID;

				$update = array(
					'ID'            => $page_id,
					'page_template' => $page['template'],
				);

				if ( $replace_if_exist ) {
					$update['post_title']   = $page['pagetitle'];
					$update['post_content'] = $content;
				}

				wp_update_post( $update );
			} else {
				$page_id = wp_insert_post(
					array(
						'post_title'    => $page['pagetitle'],
						'post_content'  => $content,
						'post_status'   => 'publish',
						'post_type'     => 'page',
						'page_template' => $page['template'],
					)
				);
			}
		}
		if ( ! empty( $page['is_homepage'] ) && $page_id ) {
			update_option( 'show_on_front', 'page', false );
			update_option( 'page_on_front', $page_id, false );
		}

		/*
		--------------------
		* Store inserted page
		* --------------------
		*/
		if ( $page_id ) {
			$slug                               = sanitize_title( $page['pagetitle'] );
			$inserted_content['pages'][ $slug ] = array(
				'id'              => $page_id,
				'is_remapped'     => false,
				'has_placeholder' => ! empty( $page['placeholder'] ) ? true : false,
				'placeholder'     => ! empty( $page['placeholder'] ) ? $page['placeholder'] : '',
			);

			/**Check if content has menu */
			preg_match_all( '/("(?:menuId|subMenuList)"\s*:\s*)(["\']?)(\d+)\2/', $content, $matches );
			
			if ( ! empty( $matches[0] ) ) {
				$inserted_content['content_has_menus'][] = $page_id;
			}

			update_option(
				"gutenverse_{$active_slug}_content_inserted",
				$inserted_content,
				false
			);

			/* Register dummy mapping */
			if ( isset( $page['pageName'] ) ) {
				$inserted_dummies['posts'][ '{page|id|' . $page['pageName'] . '}' ]    = $page_id;
				$inserted_dummies['posts'][ '{page|title|' . $page['pageName'] . '}' ] = $page['pagetitle'];
			} else {
				$inserted_dummies['posts'][ '{page|id|' . $slug . '}' ]    = $page_id;
				$inserted_dummies['posts'][ '{page|title|' . $slug . '}' ] = $page['pagetitle'];
			}

			update_option(
				"gutenverse_{$active_slug}_dummy_inserted",
				$inserted_dummies,
				false
			);
		}

		return $page_id;
	}


	/**
	 * Escape specific regex pattern.
	 *
	 * @param string $string .
	 * @return string
	 */
	public function escape_specific_regex_pattern( $string ) {
		return preg_replace_callback(
			'/\\\\\+\[0-9\]\+\[0-9\\\\s\\\\-\]\*/',
			function ( $matches ) {
				return str_replace( '\\', '\\\\', $matches[0] );
			},
			$string
		);
	}

	/**
	 * Create menus.
	 *
	 * @return int|string
	 */
	public function handle_menus() {
		global $wp_filesystem;

		require_once ABSPATH . 'wp-admin/includes/file.php';

		if ( ! WP_Filesystem() ) {
			return new \WP_Error( 'filesystem_error', 'Filesystem not available.' );
		}

		$theme_dir   = get_template_directory();
		$active_slug = get_stylesheet();

		$option_key       = "gutenverse_{$active_slug}_content_inserted";
		$inserted_content = get_option(
			$option_key,
			array(
				'pages'    => array(),
				'patterns' => array(),
				'menus'    => array(),
			)
		);

		$menu_file = "{$theme_dir}/assets/misc/menu.json";
		if ( ! $wp_filesystem->exists( $menu_file ) ) {
			return new \WP_Error( 'menu_file_missing', 'Menu JSON not found.' );
		}

		$menus = json_decode( $wp_filesystem->get_contents( $menu_file ) );
		if ( empty( $menus ) ) {
			return new \WP_Error( 'invalid_menu_json', 'Invalid menu JSON.' );
		}

		foreach ( $menus as $key => $menu ) {
			$old_menu_id = $menu->menu_id;
			$menu_name   = get_option( 'stylesheet' ) . ' ' . ( (int) $key + 1 );

			$menu_obj = wp_get_nav_menu_object( $menu_name );
			$menu_id  = $menu_obj ? (int) $menu_obj->term_id : wp_create_nav_menu( $menu_name );

			if ( is_wp_error( $menu_id ) ) {
				continue;
			}

			/*
			-------------------------
			 * Build stable lookup map
			 * ------------------------
			 */

			$existing_items = wp_get_nav_menu_items( $menu_id );
			$item_map       = array();

			if ( $existing_items ) {
				foreach ( $existing_items as $item ) {

					$key = get_post_meta(
						$item->ID,
						'_' . $active_slug . '_menu_unique_key',
						true
					);

					if ( $key ) {
						$item_map[ $key ] = (int) $item->ID;
					}
				}
			}

			$parent_map = array();
			$position   = 1;

			foreach ( $menu->menu_data as $idx => $data ) {

				$menu_parent = 0;

				if ( null !== $data->parent && isset( $parent_map[ $data->parent ] ) ) {
					$menu_parent = $parent_map[ $data->parent ];
				}

				$unique_key = $this->generate_menu_unique_key( $data );

				$menu_item_id = $item_map[ $unique_key ] ?? 0;

				$args = array(
					'menu-item-title'     => $data->title,
					'menu-item-status'    => 'publish',
					'menu-item-parent-id' => $menu_parent,
					'menu-item-position'  => $position,
				);

				/* -------- Page -------- */
				if ( $data->object_slug && 'page' === $data->type ) {

					$page_id = 0;

					if (
					isset( $inserted_content['pages'][ $data->object_slug ]['id'] ) &&
					$inserted_content['pages'][ $data->object_slug ]['id']
					) {
						$page_id = (int) $inserted_content['pages'][ $data->object_slug ]['id'];
					}

					if ( ! $page_id ) {
						$page = get_page_by_path( $data->object_slug, OBJECT, 'page' );
						if ( $page instanceof \WP_Post ) {
							$page_id = (int) $page->ID;
						}
					}

					if ( $page_id ) {
						$args['menu-item-type']      = 'post_type';
						$args['menu-item-object']    = 'page';
						$args['menu-item-object-id'] = $page_id;
					}
				} elseif ( $data->object_slug && in_array( $data->type, array( 'category', 'post_tag' ), true ) ) {
					$term = get_term_by( 'slug', 'dummy-' . $data->object_slug, $data->type );

					if ( $term && ! is_wp_error( $term ) ) {
						$args['menu-item-type']      = 'taxonomy';
						$args['menu-item-object']    = $term->taxonomy;
						$args['menu-item-object-id'] = (int) $term->term_id;
					}
				} else {
					$args['menu-item-type'] = 'custom';
					$args['menu-item-url']  = esc_url_raw( $data->url ?? '#' );
				}

				$menu_item_id = wp_update_nav_menu_item(
					$menu_id,
					$menu_item_id,
					$args
				);

				if ( is_wp_error( $menu_item_id ) ) {
					continue;
				}

				/* Store stable key */
				update_post_meta(
					$menu_item_id,
					'_' . $active_slug . '_menu_unique_key',
					$unique_key
				);

				if ( ! empty( $data->have_child ) ) {
					$parent_map[ $idx ] = $menu_item_id;
				}

				++$position;
			}
			if ( $old_menu_id !== $menu_id ) {
				$inserted_content['menus'][ 'old_' . $old_menu_id ] = array(
					'old_id' => $old_menu_id,
					'id'     => $menu_id,
					'name'   => $menu_name,
				);
			}
		}

		update_option( $option_key, $inserted_content, false );

		return true;
	}

	/**
	 * Generate menu unique key.
	 *
	 * @param object $data Menu data.
	 * @return string
	 */
	private function generate_menu_unique_key( $data ) {

		if ( ! empty( $data->object_slug ) ) {
			return $data->type . '_' . $data->object_slug;
		}

		// Custom link fallback.
		return 'custom_' . sanitize_title( $data->title );
	}

	/**
	 * Download plugin file
	 *
	 * @param string $url .
	 */
	public function download_plugin_file( $url ) {
		$url = esc_url_raw( $url );
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return false;
		}
		$temp_file = download_url( $url );
		if ( is_wp_error( $temp_file ) ) {
			return false;
		}
		return $temp_file;
	}

	/**
	 * Create Synced Pattern
	 *
	 * @param WP_REST_Request $request Request Object.
	 */
	public function install_plugin( $request ) {
		$download_url = $request->get_param( 'download_url' );

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		WP_Filesystem();
		global $wp_filesystem;

		$temp_file = $this->download_plugin_file( $download_url );
		if ( ! $temp_file ) {
			return array(
				'status'  => 'failed',
				'message' => 'Failed to download the plugin',
			);
		}

		$plugin_dir = WP_PLUGIN_DIR;
		$unzip_file = unzip_file( $temp_file, $plugin_dir );

		if ( is_wp_error( $unzip_file ) ) {
			return array(
				'status'  => 'failed',
				'message' => 'Failed to unzip the plugin',
			);
		}

		unlink( $temp_file );
		return array(
			'status'  => 'success',
			'message' => 'Plugin installed successfully!',
		);
	}

	/**
	 * Create Synced Pattern
	 *
	 * @param array  $patterns .
	 * @param string $theme_slug .
	 * @param array  $inserted_content .
	 */
	public function import_patterns( $patterns, $theme_slug, &$inserted_content ) {
		$pattern_list = get_option( $theme_slug . '_synced_pattern_imported', false );
		if ( ! $pattern_list ) {
			$pattern_list = array();
		}

		$async_patterns = get_option( $theme_slug . '_async_pattern_imported', false );
		if ( ! $async_patterns ) {
			$async_patterns = array();
		}
		foreach ( $patterns as $block_pattern ) {
			$pattern_file = get_theme_file_path( '/inc/patterns/' . $block_pattern . '.php' );
			$pattern_data = require $pattern_file;

			$post    = get_page_by_path( $block_pattern . '-synced', OBJECT, 'wp_block' );
			$post_id = $post ? $post->ID : null;
			/**Download Image */
			// Escape unicode data twice.
			$content            = preg_replace( '/(?<!\\\\)\\\\u([0-9a-fA-F]{4})/', '\\\\\\\\u$1', $pattern_data['content'] );
			$image_importer_ver = $pattern_data['image_importer_ver'] ?? null;
			if ( isset( $pattern_data['images'] ) && ! empty( $pattern_data['images'] ) ) {
				$images = json_decode( $pattern_data['images'] );
				if ( ! $image_importer_ver ) {
					foreach ( $images as $key => $image ) {
						$url  = $image->image_url;
						$data = self::check_image_exist( $url );
						if ( ! $data ) {
							$data = self::handle_file( $url );
						}
						$content  = str_replace( $url, $data['url'], $content );
						$image_id = $image->image_id;
						if ( $image_id && 'null' !== $image_id ) {
							$content = str_replace( '"imageId\":' . $image_id, '"imageId\":' . $data['id'], $content );
							$content = str_replace( '"imageId":' . $image_id, '"imageId":' . $data['id'], $content );
						}
					}
				} else {
					foreach ( $images as $key => $image ) {
						$url     = $key;
						$pattern = $image->pattern;
						$data    = self::check_image_exist( $url );
						$alt     = isset( $image->alt ) ?? '';
						if ( ! $data ) {
							$data = self::handle_file( $url, $alt );
						}
						foreach ( $pattern as $p ) {
							$placeholder_arr        = explode( '|', trim( $p, '{}' ) );
							$placeholder_value_type = end( $placeholder_arr );
							switch ( $placeholder_value_type ) {
								case 'url':
									$placeholder_data_type = $placeholder_arr[1];
									if ( 'case2' === $placeholder_data_type ) {
										$placeholder_data_size = $placeholder_arr[3];
										$target                = wp_get_attachment_image_url( $data['id'], $placeholder_data_size );
									} else {
										$target = wp_get_attachment_url( $data['id'] );
									}
									break;
								case 'id':
								default:
									$target = $data['id'];
									break;
							}
							$content = str_replace( $p, $target, $content );
						}
					}
				}
			}

			$content = $this->pattern_fix_content_attribute_value( $content );
			$content = $this->escape_specific_regex_pattern( $content );
			$content = $this->decode_unicode_sequences( $content );

			$post_name = $block_pattern;
			if ( (bool) $pattern_data['is_sync'] ) {
				$post_name = $block_pattern . '-synced';
			}

			$existing_pattern = get_page_by_path( $post_name, OBJECT, 'wp_block' );
			if ( $existing_pattern ) {
				// Update existing pattern.
				$post_id = wp_update_post(
					array(
						'ID'           => $existing_pattern->ID,
						'post_title'   => $pattern_data['title'],
						'post_content' => $content,
						'post_status'  => 'publish',
					)
				);
			} else {
				// Insert new pattern.
				$post_id = wp_insert_post(
					array(
						'post_name'    => $post_name,
						'post_title'   => $pattern_data['title'],
						'post_content' => $content,
						'post_status'  => 'publish',
						'post_author'  => 1,
						'post_type'    => 'wp_block',
					)
				);
			}
			if ( isset( $pattern_data['placeholder'] ) ) {
				$inserted_content['patterns'][] = array(
					'id'          => $post_id,
					'is_remapped' => false,
					'placeholder' => ! empty( $pattern_data['placeholder'] ) ? $pattern_data['placeholder'] : '',
				);
			}
			if ( ! is_wp_error( $post_id ) ) {
				$pattern_category = $pattern_data['categories'];
				foreach ( $pattern_category as $category ) {
					wp_set_object_terms( $post_id, $category, 'wp_pattern_category' );
				}
			}
			if ( (bool) $pattern_data['is_sync'] ) {
				$pattern_data['content']  = '<!-- wp:block {"ref":' . $post_id . '} /-->';
				$pattern_data['inserter'] = false;
				$pattern_data['slug']     = $block_pattern;
				$pattern_list[]           = $pattern_data;
			} else {
				$pattern_data['slug'] = $block_pattern;
				$async_patterns[]     = $pattern_data;
				update_post_meta( $post_id, 'wp_pattern_sync_status', 'unsynced' );
			}

				/**Check if content has menu */
				preg_match_all( '/("(?:menuId|subMenuList)"\s*:\s*)(["\']?)(\d+)\2/', $content, $matches );
			if ( ! empty( $matches[0] ) ) {
				$inserted_content['content_has_menus'][] = $post_id;
			}
		}

		update_option( $theme_slug . '_synced_pattern_imported', $pattern_list, false );
		update_option( $theme_slug . '_async_pattern_imported', $async_patterns, false );
	}

	/**
	 * Pattern fix content attribute value.
	 *
	 * @param string $content .
	 *
	 * @return string
	 */
	public function pattern_fix_content_attribute_value( $content ) {
		// $content = preg_replace( '/(?<!\\\\)u([0-9a-fA-F]{4})/', '\\\\u$1', $content );
		$content = html_entity_decode( stripslashes( $content ), ENT_NOQUOTES );
		$blocks  = parse_blocks( $content );
		$this->process_blocks( $blocks );
		$content = serialize_blocks( $blocks );
		return $content;
	}

	/**
	 * Process blocks for dinamic.
	 *
	 * @param array $blocks .
	 */
	public function process_blocks( &$blocks ) {

		foreach ( $blocks as &$block ) {

			if ( ! empty( $block['attrs'] ) ) {
				self::extract_and_replace_dinamic_content_pattern(
					$block['attrs'],
				);
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				self::process_blocks(
					$block['innerBlocks'],
				);
			}
		}
	}

	/**
	 * Import theme options from assets/misc/options.json.
	 *
	 * @return WP_REST_Response|\WP_Error
	 */
	public function import_options() {
		global $wp_filesystem;

		require_once ABSPATH . 'wp-admin/includes/file.php';

		if ( ! WP_Filesystem() ) {
			return new \WP_Error( 'filesystem_error', 'Filesystem not available.' );
		}

		$theme_dir     = get_template_directory();
		$active_slug   = get_stylesheet();
		$options_file  = "{$theme_dir}/assets/misc/options.json";
		$tracking_key  = "gutenverse_{$active_slug}_option_imported";
		$imported_keys = get_option( $tracking_key, array() );

		if ( ! $wp_filesystem->exists( $options_file ) ) {
			return new WP_REST_Response(
				array(
					'message' => 'Options file not found. Skipping options import.',
				),
				200
			);
		}

		$option_images_file = "{$theme_dir}/assets/misc/options_images.json";
		$prepared_images    = array();

		if ( $wp_filesystem->exists( $option_images_file ) ) {
			$option_images   = json_decode( $wp_filesystem->get_contents( $option_images_file ), true );
			$prepared_images = $this->prepare_options_images( $option_images );
		}

		$options = json_decode( $wp_filesystem->get_contents( $options_file ), true );
		if ( ! is_array( $options ) ) {
			return new \WP_Error( 'invalid_options_json', 'Invalid options JSON.' );
		}

		foreach ( $options as $option_key => $option_value ) {
			$current_option = get_option( $option_key, null );
			$merged_option  = $this->merge_import_option_value( $current_option, $option_value, $prepared_images );

			if ( 'gvnews_settings' === $option_key ) {
				$merged_option = $this->remap_gvnews_settings_option_images( $merged_option, $prepared_images );
				$merged_option = $this->prepare_gvnews_settings( $merged_option );
			}

			update_option( $option_key, $merged_option, false );

			if ( ! in_array( $option_key, $imported_keys, true ) ) {
				$imported_keys[] = $option_key;
			}
		}

		update_option( $tracking_key, array_values( array_unique( $imported_keys ) ), false );

		return new WP_REST_Response(
			array(
				'message' => 'Theme options imported successfully.',
			),
			200
		);
	}


	/**
	 * Prepare Option Images
	 *
	 * @param mixed $option_images .
	 * @return array
	 */
	public function prepare_options_images( $option_images ) {
		$prepared_images = array();
		if ( is_object( $option_images ) ) {
			$option_images = (array) $option_images;
		}

		foreach ( $option_images as $img ) {
			if ( is_object( $img ) ) {
				$img = (array) $img;
			}

			if ( empty( $img['image_url'] ) || empty( $img['type'] ) || empty( $img['placeholders'] ) ) {
				continue;
			}

			$image = self::check_image_exist( $img['image_url'] );
			if ( ! $image ) {
				$image = self::handle_file( $img['image_url'] );
			}

			foreach ( $img['placeholders'] as $holder ) {
				$placeholder_parts = explode( '|', trim( $holder, '{}' ) );
				$placeholder_type  = end( $placeholder_parts );
				$img_val           = 'id' === $placeholder_type ? $image['id'] : $image['url'];

				$prepared_images[ $img['type'] ][ $holder ] = $img_val;
			}
		}
		return $prepared_images;
	}

	/**
	 * Recursively merge imported option values into an existing option value.
	 *
	 * Only keys present in the imported data are changed. Sequential arrays are
	 * treated as explicit replacements.
	 *
	 * @param mixed $current_value .
	 * @param mixed $import_value .
	 * @param array $prepared_images .
	 *
	 * @return mixed
	 */
	private function merge_import_option_value( $current_value, $import_value, $prepared_images ) {
		if ( ! is_array( $import_value ) ) {
			return $import_value;
		}

		if ( ! is_array( $current_value ) ) {
			return $import_value;
		}

		if ( array_is_list( $import_value ) || array_is_list( $current_value ) ) {
			return $import_value;
		}

		$merged_value = $current_value;

		foreach ( $import_value as $key => $value ) {
			$merged_value[ $key ] = array_key_exists( $key, $current_value )
				? $this->merge_import_option_value( $current_value[ $key ], $value, $prepared_images )
				: $value;
		}

		return $merged_value;
	}

	/**
	 * Replace gvnews ads image placeholders with prepared option images.
	 *
	 * @param mixed $settings .
	 * @param array $prepared_images .
	 *
	 * @return mixed
	 */
	private function remap_gvnews_settings_option_images( $settings, $prepared_images ) {
		if ( empty( $prepared_images ) ) {
			return $settings;
		}

		if ( is_array( $settings ) ) {
			foreach ( $settings as $key => $value ) {
				$settings[ $key ] = $this->remap_gvnews_settings_option_images( $value, $prepared_images );
			}

			return $settings;
		}

		if ( ! is_string( $settings ) ) {
			return $settings;
		}

		foreach ( $prepared_images as $image_group ) {
			if ( ! is_array( $image_group ) || ! array_key_exists( $settings, $image_group ) ) {
				continue;
			}

			return $image_group[ $settings ];
		}

		return $settings;
	}

	/**
	 * Extract and replace dinamic content.
	 *
	 * @param array $data .
	 */
	public function extract_and_replace_dinamic_content_pattern( &$data ) {
		$attribute_names = array(
			'dynamicDataList',
			'textDynamicList',
			'focusTextDynamicList',
			'subTextDynamicList',
			'titleDynamicList',
			'descriptionDynamicList',
			'badgeDynamicList',
			'nameDynamicList',
			'jobDynamicList',
		);

		$attribute_content = array(
			'title',
			'content',
			'text',
			'focusText',
			'subText',
			'description',
			'badge',
			'name',
			'job',
		);
		foreach ( $data as $key => &$value ) {
			if ( in_array( $key, $attribute_names, true ) ) {
				foreach ( $value as &$value1 ) {
					foreach ( $value1 as $value2_key => &$value2 ) {
						if ( 'value' === $value2_key ) {

							$value2 = str_replace( '"', "'", $value2 );
						}
					}
				}
			}
			if ( in_array( $key, $attribute_content, true ) ) {
				$value = str_replace( '"', "'", $value );
			}
		}
	}

	/**
	 * Handle Import Posts
	 *
	 * @throws \Exception .
	 *
	 * @return WP_REST_Response
	 */
	public function import_posts( $request = null ) {
		global $wp_filesystem;

			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();

			$template = null;
		if ( $request && method_exists( $request, 'get_param' ) ) {
			$template = sanitize_text_field( $request->get_param( 'template' ) );
		}

			$type = apply_filters( 'gutenverse_plus_mechanism_content_type', 'normal' );

		if ( 'normal' === $type ) {
			$type = apply_filters( 'gutenverse_jegtheme_theme_type', 'normal' );
		}

		if ( empty( $template ) && ! in_array( $type, array( 'news' ), false ) ) {
			return new WP_REST_Response(
				array(
					'message' => 'Import failed due to an unexpected error while importing dummy content. Please try again.',
				),
				400
			);
		}

			$base_path         = get_theme_file_path( '/posts' );
			$product_base_path = get_theme_file_path( '/products' );
		if ( ! empty( $template ) ) {
			$upload_dir      = wp_upload_dir();
			$demo_folder     = trim( preg_replace( '/[^a-z0-9]+/i', '-', strtolower( $template ) ), '-' );
			$demo_base_path  = trailingslashit( $upload_dir['basedir'] ) . GUTENVERSE_COMPANION . '/' . $demo_folder . '/demo';
			$base_path       = $demo_base_path . '/posts';
			$product_base_path = $demo_base_path . '/products';
		}
			$active_theme_slug = get_stylesheet();
			$inserted_dummies  = get_option(
				'gutenverse_' . $active_theme_slug . '_dummy_inserted',
				array(
					'posts'                => array(),
					'posts-dummies'        => array(),
					'products'             => array(),
					'products-dummies'     => array(),
					'category'             => array(),
					'category-dummies'     => array(),
					'post_tag'             => array(),
					'post_tag-dummies'     => array(),
					'product_cat'          => array(),
					'product_cat-dummies'  => array(),
					'product_tag'          => array(),
					'product_tag-dummies'  => array(),
					'post_content_images'  => array(),
					'post_featured_images' => array(),
				)
			);

			$imported_any        = false;
			$skip_product_import = false;

		if ( is_dir( $base_path ) ) {
			$imported_any = $this->import_dummy_post_type(
				array(
					'base_path'          => $base_path,
					'post_type'          => 'post',
					'file_prefix'        => 'post',
					'list_directory'     => 'post-list',
					'mapping_key'        => 'posts',
					'tracking_key'       => 'posts-dummies',
					'placeholder_prefix' => 'post',
					'taxonomies'         => array(
						'category' => 'post_categories.json',
						'post_tag' => 'post_tags.json',
					),
					'term_assignments'   => array(
						'category' => 'categories',
						'post_tag' => 'tags',
					),
				),
				$inserted_dummies,
				$wp_filesystem
			) || $imported_any;
		}
		if ( is_dir( $product_base_path ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';

			$woocommerce_active  = is_plugin_active( 'woocommerce/woocommerce.php' ) || is_plugin_active_for_network( 'woocommerce/woocommerce.php' );
			$skip_product_import = ! $woocommerce_active;
		}

		if ( is_dir( $product_base_path ) && ! $skip_product_import && post_type_exists( 'product' ) ) {
			$imported_any = $this->import_dummy_post_type(
				array(
					'base_path'          => $product_base_path,
					'post_type'          => 'product',
					'file_prefix'        => 'product',
					'list_directory'     => 'product-list',
					'mapping_key'        => 'products',
					'tracking_key'       => 'products-dummies',
					'placeholder_prefix' => 'product',
					'taxonomies'         => array(
						'product_cat' => 'product_categories.json',
						'product_tag' => 'product_tags.json',
					),
					'term_assignments'   => array(
						'product_cat' => 'categories',
						'product_tag' => 'tags',
					),
				),
				$inserted_dummies,
				$wp_filesystem
			) || $imported_any;
		}

		if ( ! $imported_any && ! $skip_product_import ) {
			throw new \Exception( 'No importable dummy posts or products folders found.' );
		}

		update_option(
			'gutenverse_' . $active_theme_slug . '_dummy_inserted',
			$inserted_dummies,
			false
		);

		return new WP_REST_Response(
			array(
				'message' => 'Dummy content imported successfully',
			),
			200
		);
	}

	/**
	 * Import Taxonomies
	 *
	 * @param array  $data .
	 * @param string $type .
	 * @param array  $inserted_dummies .
	 *
	 * @return void
	 */
	private function import_taxonomies( $data, $type, &$inserted_dummies ) {
		$taxonomy_bin = array();
		$inserted_any = false; // ✅ Track if any taxonomy was inserted in this run

		foreach ( $data as $taxonomy ) {
			// Skip if already exists.
			$check_if_exist = get_term_by( 'slug', $taxonomy['slug'], $type );
			if ( $check_if_exist && ! is_wp_error( $check_if_exist ) ) {
				continue;
			}

			$parent_id  = 0;
			$can_insert = true;

			// Handle parent term if defined.
			if ( ! empty( $taxonomy['parent'] ) ) {
				$parent = get_term_by( 'slug', $taxonomy['parent'], $type );

				// Parent doesn't exist yet — postpone.
				if ( ! $parent || is_wp_error( $parent ) ) {
					$can_insert = false;
				} else {
					$parent_id = $parent->term_id; // ✅ use correct property
				}
			}

			if ( $can_insert ) {
				$result = wp_insert_term(
					$taxonomy['name'],
					$type,
					array(
						'slug'   => 'dummy-' . $taxonomy['slug'],
						'parent' => $parent_id,
					)
				);
				if ( ! is_wp_error( $result ) ) {
					$inserted_any = true; // ✅ We made progress
					$term_id      = (int) $result['term_id'];
					$term         = get_term( $term_id, $type );

					if ( $term && ! is_wp_error( $term ) ) {
						$dummies_title = '{' . $type . '|title|' . $taxonomy['slug'] . '}';
						$dummies_id    = '{' . $type . '|id|' . $taxonomy['slug'] . '}';

						if ( ! isset( $inserted_dummies[ $type ][ $dummies_title ] ) ) {
							$inserted_dummies[ $type ][ $dummies_title ] = $term->name;
						}

						if ( ! isset( $inserted_dummies[ $type ][ $dummies_id ] ) ) {
							$inserted_dummies[ $type ][ $dummies_id ] = $term->term_id;
							$inserted_dummies[ $type . '-dummies' ][] = $term->term_id;
						}
					}
				} else {
					// Log error for debugging.
					gutenverse_rlog( 'Failed to insert term: ' . $taxonomy['slug'] . ' - ' . $result->get_error_message() );
				}
			} else {
				// Can't insert now, try again in the next round.
				$taxonomy_bin[] = $taxonomy;
			}
		}

		// ✅ Only recurse if there are still items AND progress was made this round
		if ( ! empty( $taxonomy_bin ) && $inserted_any ) {
			$this->import_taxonomies( $taxonomy_bin, $type, $inserted_dummies );
		} elseif ( ! empty( $taxonomy_bin ) && ! $inserted_any ) {
			// 🛑 Stop recursion and log unresolved taxonomies to avoid infinite loop
			gutenverse_rlog( 'Some taxonomies could not be imported due to missing parents:' );
			gutenverse_rlog( $taxonomy_bin );
		}
	}

	/**
	 * Import dummy post type data from a theme folder.
	 *
	 * @param array              $config .
	 * @param array              $inserted_dummies .
	 * @param WP_Filesystem_Base $wp_filesystem .
	 *
	 * @throws \Exception When no importable files are found.
	 *
	 * @return bool
	 */
	private function import_dummy_post_type( $config, &$inserted_dummies, $wp_filesystem ) {
		foreach ( $config['taxonomies'] as $taxonomy => $filename ) {
			$taxonomy_path = $config['base_path'] . '/' . $filename;
			if ( file_exists( $taxonomy_path ) ) {
				$data = json_decode( file_get_contents( $taxonomy_path ), true );
				$this->import_taxonomies( $data, $taxonomy, $inserted_dummies );
			}
		}

		$image_in_each_dummies = array( 'product' );

		$prepared_contents = $this->prepare_post_contents(
			$config['base_path'] . '/' . $config['file_prefix'] . '_content.json',
			$inserted_dummies
		);
		$prepared_images   = array();
		if ( ! in_array( $config['post_type'], $image_in_each_dummies, true ) ) {
			$prepared_images = $this->prepare_featured_images(
				$config['base_path'] . '/' . $config['file_prefix'] . '_images.json',
				$inserted_dummies
			);
		}

		$items_path = trailingslashit( $config['base_path'] . '/' . $config['list_directory'] );
		$files      = $wp_filesystem->dirlist( $items_path );

		if ( empty( $files ) ) {
			throw new \Exception(
				sprintf(
					'No %s files found.',
					esc_html( $config['post_type'] )
				)
			);
		}

		$imported_any = false;

		foreach ( $files as $file ) {
			if ( '.DS_Store' === $file['name'] ) {
				continue;
			}

			$item_data = json_decode(
				file_get_contents( $items_path . $file['name'] ),
				true
			);

			if ( empty( $item_data['name'] ) ) {
				continue;
			}

			$content = $this->get_random_prepared_content( $prepared_contents );

			if ( in_array( $item_data['post_type'], $image_in_each_dummies, true ) ) {

				$prepared_images = array();
				$images_list     = $item_data['images'] ?? array();

				if ( is_object( $images_list ) ) {
					$images_list = (array) $images_list;
				}

				foreach ( $images_list as $img ) {
					if ( is_object( $img ) ) {
						$img = (array) $img;
					}

					if ( empty( $img['image_url'] ) || empty( $img['type'] ) || empty( $img['placeholders'] ) ) {
						continue;
					}

					$image = self::check_image_exist( $img['image_url'] );
					if ( ! $image ) {
						$image = self::handle_file( $img['image_url'] );
					}

					foreach ( $img['placeholders'] as $holder ) {
						$placeholder_parts = explode( '|', trim( $holder, '{}' ) );
						$placeholder_type  = end( $placeholder_parts );
						$img_val           = 'id' === $placeholder_type ? $image['id'] : $image['url'];

						$prepared_images[ $img['type'] ][ $holder ] = $img_val;
					}
				}
			}
			$item_id = $this->insert_or_update_post(
				$item_data,
				$content,
				$prepared_images,
				$item_data['post_type']
			);
			$inserted_dummies[ $config['mapping_key'] ][ '{' . $config['placeholder_prefix'] . '|id|' . $item_data['ID'] . '}' ]    = $item_id;
			$inserted_dummies[ $config['mapping_key'] ][ '{' . $config['placeholder_prefix'] . '|title|' . $item_data['ID'] . '}' ] = $item_data['title'];
			$inserted_dummies[ $config['tracking_key'] ][] = $item_id;

			foreach ( $config['term_assignments'] as $taxonomy => $source_key ) {
				$this->assign_terms(
					$item_id,
					$item_data[ $source_key ] ?? array(),
					$taxonomy,
					$inserted_dummies
				);
			}

			$imported_any = true;
		}

		return $imported_any;
	}

	/**
	 * Decode unicode sequences
	 *
	 * @param string $content .
	 * @return string
	 */
	private function decode_unicode_sequences( $content ) {
		return preg_replace_callback(
			'/\\\\u([0-9a-fA-F]{4})/',
			function ( $matches ) {

				$hex = strtolower( $matches[1] );

				// Always keep quotes escaped.
				if ( '0022' === $hex ) {
					return '\'';
				}

				$codepoint = hexdec( $hex );

				return mb_convert_encoding(
					pack( 'n', $codepoint ),
					'UTF-8',
					'UTF-16BE'
				);
			},
			$content
		);
	}
	/**
	 * Prepare Post Contents
	 *
	 * @param string $file .
	 * @param array  $inserted_dummies .
	 *
	 * @return array
	 */
	private function prepare_post_contents( $file, &$inserted_dummies ) {
		$results = array();

		if ( ! file_exists( $file ) ) {
			return $results;
		}

		$data = json_decode( file_get_contents( $file ), true );

		foreach ( $data as $post ) {
			$content            = $post['content'];
			$image_importer_ver = $post['image_importer_ver'] ?? null;

			if ( ! $image_importer_ver ) {
				foreach ( $post['images'] as $img ) {
					$image                                     = self::check_image_exist( $img ) ?? self::handle_file( $img );
					$inserted_dummies['post_content_images'][] = $image['id'];
					$content                                   = str_replace( $img, $image['url'], $content );
				}
			} elseif ( isset( $post['images'] ) ) {
				foreach ( $post['images'] as $key => $image ) {
					if ( is_string( $image ) ) {
						$data = self::check_image_exist( $image );
						if ( ! $data ) {
							$data = self::handle_file( $image );
						}

						$inserted_dummies['post_content_images'][] = $data['id'];
						$content                                   = str_replace( $image, $data['url'], $content );
						continue;
					}

					if ( is_object( $image ) ) {
						$image = (array) $image;
					}

					if ( ! is_array( $image ) || empty( $image['pattern'] ) ) {
						continue;
					}

					$url     = $key;
					$pattern = $image['pattern'];
					$data    = self::check_image_exist( $url );
					$alt     = $image['alt'] ?? '';
					if ( ! $data ) {
						$data = self::handle_file( $url, $alt );
					}
					foreach ( $pattern as $p ) {
						$placeholder_arr        = explode( '|', trim( $p, '{}' ) );
						$placeholder_value_type = end( $placeholder_arr );
						switch ( $placeholder_value_type ) {
							case 'url':
								$placeholder_data_type = $placeholder_arr[1];
								if ( 'case2' === $placeholder_data_type ) {
									$placeholder_data_size = $placeholder_arr[3];
									$target                = wp_get_attachment_image_url( $data['id'], $placeholder_data_size );
								} else {
									$target = wp_get_attachment_url( $data['id'] );
								}
								break;
							case 'id':
							default:
								$target = $data['id'];
								break;
						}
						$content = str_replace( $p, $target, $content );
					}
					$inserted_dummies['post_content_images'][] = $data['id'];
				}
			}

			$content = $this->decode_unicode_sequences( $content );

			$results[] = array(
				'content' => $content,
				'excerpt' => isset( $post['excerpt'] ) ? $post['excerpt'] : '',
			);
		}

		return $results;
	}

	/**
	 * Prepare Featured Images
	 *
	 * @param string $file .
	 * @param array  $inserted_dummies .
	 *
	 * @return array
	 */
	private function prepare_featured_images( $file, &$inserted_dummies ) {
		$results = array();

		if ( ! file_exists( $file ) ) {
			return $results;
		}

		$data = json_decode( file_get_contents( $file ), true );

		foreach ( $data as $post ) {
			$image = self::check_image_exist( $post['image_url'] );
			if ( ! $image ) {
				$image = self::handle_file( $post['image_url'] );
			}

			$inserted_dummies['post_featured_images'][] = $image['id'];

			$results[] = array(
				'id'  => $image['id'],
				'url' => $image['url'],
			);
		}

		return $results;
	}

	/**
	 * Get a random prepared content entry with a safe fallback.
	 *
	 * @param array $prepared_contents .
	 *
	 * @return array
	 */
	private function get_random_prepared_content( $prepared_contents ) {
		if ( empty( $prepared_contents ) ) {
			return array(
				'content' => '',
				'excerpt' => '',
			);
		}

		return $prepared_contents[ array_rand( $prepared_contents ) ];
	}

	/**
	 * Get a random prepared image entry with a safe fallback.
	 *
	 * @param array $prepared_images .
	 *
	 * @return array
	 */
	private function get_random_prepared_image( $prepared_images ) {
		if ( empty( $prepared_images ) ) {
			return array(
				'id'  => 0,
				'url' => '',
			);
		}

		return $prepared_images[ array_rand( $prepared_images ) ];
	}

	/**
	 * Insert or Update Post
	 *
	 * @param array  $data .
	 * @param array  $content .
	 * @param array  $prepared_images .
	 * @param string $post_type .
	 *
	 * @return int
	 */
	private function insert_or_update_post( $data, $content, $prepared_images, $post_type = 'post' ) {
		$args = array(
			'name'        => $data['name'],
			'post_type'   => 'post',
			'numberposts' => 1,
		);

		$existing = get_posts( $args );

		$postarr = array(
			'post_name'    => $data['name'],
			'post_title'   => $data['title'],
			'post_content' => $content['content'],
			'post_excerpt' => $content['excerpt'],
			'post_status'  => 'publish',
			'post_author'  => get_current_user_id(),
			'post_type'    => $post_type,
			'post_date'    => $data['date'],
		);

		if ( $existing ) {
			wp_delete_post( $existing[0]->ID, true );
		}

		$post_id = wp_insert_post( $postarr, true );

		if ( is_wp_error( $post_id ) ) {
			gutenverse_rlog(
				'Post insert/update failed',
				$post_id->get_error_message(),
				$postarr
			);

			return $post_id;
		}

		if ( ! empty( $data['post_format'] ) && $data['post_format'] ) {
			set_post_format( $post_id, $data['post_format'] );
		}
		$this->sync_meta_data( $post_id, $data, $prepared_images );

		if ( 'product' !== $post_type ) {

			$image = $this->get_random_prepared_image( $prepared_images );

			if ( ! empty( $image['id'] ) ) {

				$thumbnail_id = (int) $image['id'];
				set_post_thumbnail( $post_id, $thumbnail_id );
			}
		}

		return $post_id;
	}

	/**
	 * Sync Meta data for dummies
	 *
	 * @param int   $post_id .
	 * @param array $data .
	 * @param array $prepared_images .
	 *
	 * @return void
	 */
	private function sync_meta_data( $post_id, $data, $prepared_images ) {
		$metas = $data['metas'];
		wp_set_object_terms( $post_id, 'simple', 'product_type' );
		$unique_process_meta = array( '_thumbnail_id', '_product_image_gallery' );
		foreach ( $metas as $key => $meta ) {
			if ( in_array( $key, $unique_process_meta, true ) ) {
				switch ( $key ) {
					case '_thumbnail_id':
						if (
							'product' === $data['post_type'] &&
							! empty( $prepared_images['product_featured_image'] ) &&
							is_scalar( $meta ) &&
							isset( $prepared_images['product_featured_image'][ $meta ] )
						) {
							set_post_thumbnail( $post_id, (int) $prepared_images['product_featured_image'][ $meta ] );
						}
						break;
					case '_product_image_gallery':
						if ( ! empty( $prepared_images['product_gallery'] ) ) {
							$arr_val = array();
							foreach ( $meta as $mt ) {
								if (
									is_scalar( $mt ) &&
									isset( $prepared_images['product_gallery'][ $mt ] )
								) {
									$arr_val[] = (int) $prepared_images['product_gallery'][ $mt ];
								}
							}

							$string_val = implode( ',', $arr_val );

							if ( ! empty( $string_val ) ) {
								update_post_meta( $post_id, $key, (string) $string_val );
							}
						}

						break;
				}
			} else {
				update_post_meta( $post_id, $key, $meta );
			}
		}
	}

	/**
	 * Assign Terms
	 *
	 * @param int    $post_id .
	 * @param array  $terms .
	 * @param string $taxonomy .
	 * @param array  $inserted_dummies .
	 *
	 * @return void
	 */
	private function assign_terms( $post_id, $terms, $taxonomy, &$inserted_dummies ) {
		$term_ids = array();

		foreach ( $terms as $term_data ) {
			$term = get_term_by( 'slug', 'dummy-' . $term_data['slug'], $taxonomy );
			if ( ! $term ) {
				$term = wp_insert_term(
					$term_data['name'],
					$taxonomy,
					array( 'slug' => 'dummy-' . $term_data['slug'] )
				);
			}
			if ( ! is_wp_error( $term ) ) {
				$term_id    = is_array( $term ) ? $term['term_id'] : $term->term_id;
				$term_ids[] = $term_id;

				$inserted_dummies[ $taxonomy ][ '{' . $taxonomy . '|id|' . $term_data['slug'] . '}' ] = $term_id;

				$inserted_dummies[ $taxonomy ][ '{' . $taxonomy . '|title|' . $term_data['slug'] . '}' ] = $term_data['name'];

				$inserted_dummies[ $taxonomy . '-dummies' ][] = $term_id;
			}
		}

		if ( $term_ids ) {
			wp_set_post_terms( $post_id, $term_ids, $taxonomy );
		}
	}

	/**
	 * Remapping Content Placeholder
	 */
	public function remapping_content_placeholder() {
		try {
			$inserted_content = get_option(
				'gutenverse_' . get_stylesheet() . '_content_inserted',
				array(
					'pages'             => array(),
					'patterns'          => array(),
					'menus'             => array(),
					'content_has_menus' => array(),
				)
			);
			$inserted_dummies = get_option(
				'gutenverse_' . get_stylesheet() . '_dummy_inserted',
				array(
					'posts'                => array(),
					'posts-dummies'        => array(),
					'products'             => array(),
					'products-dummies'     => array(),
					'category'             => array(),
					'category-dummies'     => array(),
					'post_tag'             => array(),
					'post_tag-dummies'     => array(),
					'product_cat'          => array(),
					'product_cat-dummies'  => array(),
					'product_tag'          => array(),
					'product_tag-dummies'  => array(),
					'post_content_images'  => array(),
					'post_featured_images' => array(),
				)
			);

			$merge_all_dummies                    = array_merge( $inserted_dummies['posts'], $inserted_dummies['products'], $inserted_dummies['category'], $inserted_dummies['post_tag'], $inserted_dummies['product_cat'], $inserted_dummies['product_tag'] );
			$current_user                         = wp_get_current_user();
			$merge_all_dummies['{author|id|1}']   = $current_user->ID;
			$merge_all_dummies['{author|name|1}'] = $current_user->display_name;
			/**Remap Patterns */
			$inserted_patterns = $inserted_content['patterns'];
			foreach ( $inserted_patterns as &$pattern ) {
				if ( $pattern['is_remapped'] ) {
					continue;
				}
				$post = get_post( $pattern['id'] );
				if ( $post ) {
					$content = $post->post_content;

					/**Has Dummy */
					foreach ( $pattern['placeholder'] as $key => $dummy ) {
						$placeholder = $dummy;
						$parts       = explode( '|', trim( $placeholder, '{}' ) );
						$part        = $parts[1] ?? null;
						$target      = isset( $merge_all_dummies[ $placeholder ] ) ? $merge_all_dummies[ $placeholder ] : '';
						if ( 'id' !== $part ) {
							$target = isset( $merge_all_dummies[ $placeholder ] ) ? $merge_all_dummies[ $placeholder ] : 'Placeholder';
						}
						$content = str_replace( $placeholder, $target, $content );
					}

					wp_update_post(
						array(
							'ID'           => $pattern['id'],
							'post_content' => $content,
						)
					);
					$pattern['is_remapped'] = true;
				}
			}

			/**Remap Pages */
			$inserted_pages = $inserted_content['pages'];
			foreach ( $inserted_pages as &$page ) {
				if ( $page['is_remapped'] || ! $page['has_placeholder'] ) {
					continue;
				}
				$post = get_post( $page['id'] );
				if ( $post ) {
					$content = $post->post_content;

					/**Has Dummy */
					foreach ( $page['placeholder'] as $key => $dummy ) {
						$placeholder = $dummy;
						$parts       = explode( '|', trim( $placeholder, '{}' ) );
						$part        = $parts[1] ?? null;
						$target      = isset( $merge_all_dummies[ $placeholder ] ) ? $merge_all_dummies[ $placeholder ] : '';
						if ( 'id' !== $part ) {
							$target = isset( $merge_all_dummies[ $placeholder ] ) ? $merge_all_dummies[ $placeholder ] : 'Placeholder';
						}
						$content = str_replace( $placeholder, $target, $content );
					}

					wp_update_post(
						array(
							'ID'           => $page['id'],
							'post_content' => $content,
						)
					);
					$page['is_remapped'] = true;
				}
			}

			/**Has Menu */
			$content_with_menu = $inserted_content['content_has_menus'] ?? array();

			$content_with_menu = array_map( 'intval', (array) $content_with_menu );

			$posts = array_map( 'get_post', $content_with_menu );
			$posts = array_filter( $posts ); // remove invalid IDs.
			foreach ( $posts as $post ) {
				$content = $post->post_content;
				$content = $this->check_navbar( $content, $inserted_content['menus'] );
				wp_update_post(
					array(
						'ID'           => $post->ID,
						'post_content' => wp_slash( $content ),
					)
				);
			}

			$this->remap_imported_dummy_post_meta( $inserted_dummies, $merge_all_dummies );
			$this->remap_imported_options( $inserted_dummies );

			update_option( 'gutenverse_' . get_stylesheet() . '_content_inserted', $inserted_content, false );

			flush_rewrite_rules();
		} catch ( \Throwable $th ) {
			return new WP_REST_Response(
				array(
					'message' => 'Remapping content failed!',
				),
				400
			);
		}
		return new WP_REST_Response(
			array(
				'message' => 'Remapping content success!',
			),
			200
		);
	}

	/**
	 * Delete Dummies
	 */
	public function delete_dummies() {
		try {
			$active_theme_slug = get_stylesheet();
			$dummy_inserted    = get_option( 'gutenverse_' . $active_theme_slug . '_dummy_inserted', array() );
			$post_keys         = array(
				'posts-dummies',
				'products-dummies',
				'post_content_images',
				'post_featured_images',
			);
			$term_keys         = array(
				'category-dummies',
				'post_tag-dummies',
				'product_cat-dummies',
				'product_tag-dummies',
			);

			foreach ( $post_keys as $key ) {
				$dummies = $dummy_inserted[ $key ] ?? array();
				foreach ( $dummies as $dummy ) {
					if ( is_numeric( $dummy ) ) {
						wp_delete_post( $dummy );
					}
				}
			}

			foreach ( $term_keys as $taxonomy_key ) {
				$dummies  = $dummy_inserted[ $taxonomy_key ] ?? array();
				$taxonomy = str_replace( '-dummies', '', $taxonomy_key );
				foreach ( $dummies as $dummy ) {
					if ( is_numeric( $dummy ) ) {
						wp_delete_term( (int) $dummy, $taxonomy );
					}
				}
			}

			delete_option( 'gutenverse_' . $active_theme_slug . '_dummy_inserted' );
		} catch ( \Throwable $th ) {
			return new WP_REST_Response(
				array(
					'message' => 'Dummy data deleted failed',
				),
				400
			);
		}

		return new WP_REST_Response(
			array(
				'message' => 'Dummy data deleted successfully',
			),
			200
		);
	}

	/**
	 * Check Navbar if exists change the menuId
	 *
	 * @param string $content .
	 * @param array  $placeholder_data .
	 *
	 * @return string
	 */
	public function check_navbar( $content, $placeholder_data ) {
		$html_blocks = parse_blocks( $content );
		$blocks      = _flatten_blocks( $html_blocks );
		$block_menus = array(
			'gutenverse/nav-menu',
			'gutenverse/mega-menu-item',
		);
		foreach ( $blocks as $block ) {
			if ( in_array( $block['blockName'], $block_menus, true ) ) {
				$menu_attribute = 'gutenverse/mega-menu-item' === $block['blockName'] ? 'subMenuList' : 'menuId';
				if ( 'subMenuList' === $menu_attribute && 'list' !== ( $block['attrs']['subMenuType'] ?? '' ) ) {
					continue;
				}
				$block_before = serialize_block( $block );
				$block_after  = $block_before;

				if ( ! empty( $block['attrs'][ $menu_attribute ] ) && is_numeric( $block['attrs'][ $menu_attribute ] ) ) {
					$original_menu_id = $block['attrs'][ $menu_attribute ];
					$mapped_menu_id   = $placeholder_data[ 'old_' . $original_menu_id ]['id'] ?? null;

					if ( ! empty( $mapped_menu_id ) && is_numeric( $mapped_menu_id ) ) {
						$block['attrs'][ $menu_attribute ] = (int) $mapped_menu_id;
						$content = str_replace( $block_before, serialize_block( $block ), $content );
						continue;
					}

					$menu_exists      = wp_get_nav_menu_object( 'menu-' . $original_menu_id );

					if ( ! $menu_exists ) {
						$menu_id = wp_create_nav_menu( 'menu-' . $original_menu_id );
						if ( is_wp_error( $menu_id ) ) {
							continue;
						}

						wp_update_nav_menu_item(
							$menu_id,
							0,
							array(
								'menu-item-title'  => 'Home',
								'menu-item-url'    => home_url( '/' ),
								'menu-item-status' => 'publish',
							)
						);
					} else {
						$menu_id = $menu_exists->term_id;
					}
					$block['attrs'][ $menu_attribute ] = $menu_id;
					$block_after                       = serialize_block( $block );
				}

				$content = str_replace( $block_before, $block_after, $content );
			}
		}
		return $content;
	}

	/**
	 * Remap imported theme options after pages, dummies, and menus exist.
	 *
	 * @param array $inserted_dummies .
	 *
	 * @return void
	 */
	private function remap_imported_options( $inserted_dummies ) {
		$active_slug  = get_stylesheet();
		$tracking_key = "gutenverse_{$active_slug}_option_imported";
		$option_keys  = get_option( $tracking_key, array() );

		if ( empty( $option_keys ) || ! is_array( $option_keys ) ) {
			return;
		}

		$placeholder_map                    = array_merge( $inserted_dummies['posts'], $inserted_dummies['products'], $inserted_dummies['category'], $inserted_dummies['post_tag'], $inserted_dummies['product_cat'], $inserted_dummies['product_tag'] );
		$current_user                       = wp_get_current_user();
		$placeholder_map['{author|id|1}']   = $current_user->ID;
		$placeholder_map['{author|name|1}'] = $current_user->display_name;

		foreach ( array_unique( $option_keys ) as $option_key ) {
			$option_value = get_option( $option_key, null );

			if ( null === $option_value ) {
				continue;
			}

			$remapped_value = $this->remap_option_placeholders( $option_value, $placeholder_map );

			update_option( $option_key, $remapped_value, false );
		}
	}

	/**
	 * Recursively remap placeholders inside imported option data.
	 *
	 * @param mixed $value .
	 * @param array $placeholder_map .
	 *
	 * @return mixed
	 */
	private function remap_option_placeholders( $value, $placeholder_map ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $item ) {
				$value[ $key ] = $this->remap_option_placeholders( $item, $placeholder_map );
			}

			return $value;
		}

		if ( is_string( $value ) ) {
			return preg_replace_callback(
				'/\{(?:page|post|product|category|post_tag|product_cat|product_tag|author|image)\|[^{}]+\}/',
				function ( $matches ) use ( $placeholder_map ) {
					return $this->resolve_option_placeholder( $matches[0], $placeholder_map );
				},
				$value
			);
		}

		return $value;
	}

	/**
	 * Resolve a single placeholder used inside imported theme options.
	 *
	 * @param string $placeholder .
	 * @param array  $placeholder_map .
	 *
	 * @return string|int
	 */
	private function resolve_option_placeholder( $placeholder, $placeholder_map ) {
		$parts = explode( '|', trim( $placeholder, '{}' ) );
		$type  = $parts[0] ?? '';

		if ( 'image' === $type ) {
			return $this->resolve_option_image_placeholder( $parts );
		}

		return isset( $placeholder_map[ $placeholder ] ) ? $placeholder_map[ $placeholder ] : '';
	}

	/**
	 * Resolve an image placeholder used inside imported theme options.
	 *
	 * @param array $parts .
	 *
	 * @return string|int
	 */
	private function resolve_option_image_placeholder( $parts ) {
		$image = $this->get_imported_option_image( $parts );

		if ( empty( $image['id'] ) ) {
			return '';
		}

		$value_type = end( $parts );
		if ( 'url' === $value_type ) {
			if ( 'case2' === ( $parts[1] ?? '' ) ) {
				$size = $parts[3] ?? 'full';
				return wp_get_attachment_image_url( $image['id'], $size ) ?: $image['url'];
			}

			return $image['url'];
		}

		return $image['id'];
	}

	/**
	 * Find or import a theme image referenced by an options placeholder.
	 *
	 * @param array $parts .
	 *
	 * @return array|null
	 */
	private function get_imported_option_image( $parts ) {
		$source_parts = $parts;
		array_pop( $source_parts );
		$source_key = 'theme-option-image://' . implode( '/', $source_parts );
		$existing   = self::check_image_exist( $source_key );

		if ( $existing ) {
			return $existing;
		}

		$file_path = $this->locate_option_image_file( $parts );
		if ( empty( $file_path ) ) {
			return null;
		}

		return $this->import_local_option_image( $file_path, $source_key );
	}

	/**
	 * Locate the source file for a theme option image placeholder.
	 *
	 * @param array $parts .
	 *
	 * @return string|null
	 */
	private function locate_option_image_file( $parts ) {
		$theme_dir = get_template_directory();
		$group     = $parts[1] ?? '';
		$name      = $parts[2] ?? '';

		if ( empty( $name ) ) {
			return null;
		}

		$search_roots = array_filter(
			array_unique(
				array(
					trailingslashit( $theme_dir . '/assets/misc' ),
					trailingslashit( $theme_dir . '/assets/img' ),
					trailingslashit( $theme_dir . '/assets/images' ),
					trailingslashit( $theme_dir . '/assets/misc/images' ),
				)
			)
		);

		foreach ( $search_roots as $root ) {
			$candidate_patterns = array();

			if ( 'case2' !== $group ) {
				$candidate_patterns[] = $root . $group . '/' . $name . '.*';
				$candidate_patterns[] = $root . $group . '/' . $name . '/*';
			}

			$candidate_patterns[] = $root . $name . '.*';

			foreach ( $candidate_patterns as $pattern ) {
				$matches = glob( $pattern );
				if ( ! empty( $matches ) ) {
					foreach ( $matches as $match ) {
						if ( is_file( $match ) ) {
							return $match;
						}
					}
				}
			}
		}

		foreach ( $search_roots as $root ) {
			if ( ! is_dir( $root ) ) {
				continue;
			}

			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS )
			);

			foreach ( $iterator as $file ) {
				if ( ! $file->isFile() ) {
					continue;
				}

				if ( pathinfo( $file->getFilename(), PATHINFO_FILENAME ) !== (string) $name ) {
					continue;
				}

				if ( 'case2' !== $group && basename( dirname( $file->getPathname() ) ) !== (string) $group ) {
					continue;
				}

				return $file->getPathname();
			}
		}

		return null;
	}

	/**
	 * Import a local theme image into the media library.
	 *
	 * @param string $file_path .
	 * @param string $source_key .
	 *
	 * @return array|null
	 */
	private function import_local_option_image( $file_path, $source_key ) {
		$file_contents = file_get_contents( $file_path );

		if ( false === $file_contents ) {
			return null;
		}

		$upload = wp_upload_bits( basename( $file_path ), null, $file_contents );
		if ( ! empty( $upload['error'] ) ) {
			return null;
		}

		$file_type  = wp_check_filetype( basename( $upload['file'] ) );
		$attachment = array(
			'post_mime_type' => $file_type['type'],
			'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $upload['file'] ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		include_once ABSPATH . 'wp-admin/includes/image.php';
		$attach_id = wp_insert_attachment( $attachment, $upload['file'] );

		if ( is_wp_error( $attach_id ) ) {
			return null;
		}

		update_post_meta( $attach_id, '_import_source', $source_key );

		try {
			$attach_data = wp_generate_attachment_metadata( $attach_id, $upload['file'] );
			wp_update_attachment_metadata( $attach_id, $attach_data );
		} catch ( \Throwable $th ) {
			gutenverse_rlog( $th->getMessage() );
		}

		return array(
			'id'  => $attach_id,
			'url' => $upload['url'],
		);
	}

	/**
	 * Prepare special gvnews settings after remapping.
	 *
	 * @param mixed $settings .
	 *
	 * @return mixed
	 */
	private function prepare_gvnews_settings( $settings ) {
		if ( ! is_array( $settings ) ) {
			return $settings;
		}

		$features = array();

		if ( isset( $settings['features'] ) ) {
			if ( class_exists( '\GUTENVERSE\NEWS\ESSENTIAL\Features' ) && is_array( $settings['features'] ) ) {
				$features = array_values(
					array_filter(
						$settings['features'],
						'is_string'
					)
				);

				\GUTENVERSE\NEWS\ESSENTIAL\Features::activate_features( $features );
			}

			$settings['features'] = $features;
		}

		return $settings;
	}

	/**
	 * Remap imported dummy post metas using the placeholder map.
	 *
	 * @param array $inserted_dummies .
	 * @param array $placeholder_map .
	 *
	 * @return void
	 */
	private function remap_imported_dummy_post_meta( $inserted_dummies, $placeholder_map ) {
		$post_ids = array_merge(
			$inserted_dummies['posts-dummies'] ?? array(),
			$inserted_dummies['products-dummies'] ?? array()
		);

		$post_ids       = array_values( array_unique( array_map( 'intval', $post_ids ) ) );
		$skip_meta_keys = array( '_thumbnail_id', '_product_image_gallery' );

		foreach ( $post_ids as $post_id ) {
			if ( empty( $post_id ) ) {
				continue;
			}

			$meta_values = get_post_meta( $post_id );
			foreach ( $meta_values as $meta_key => $values ) {
				unset( $values );

				if ( in_array( $meta_key, $skip_meta_keys, true ) ) {
					continue;
				}

				$current_meta = get_post_meta( $post_id, $meta_key, true );
				$remapped     = $this->remap_dummy_meta_placeholders( $current_meta, $placeholder_map );

				if ( $remapped !== $current_meta ) {
					update_post_meta( $post_id, $meta_key, $remapped );
				}
			}
		}
	}

	/**
	 * Recursively remap placeholders inside imported dummy post meta values.
	 *
	 * @param mixed $value .
	 * @param array $placeholder_map .
	 *
	 * @return mixed
	 */
	private function remap_dummy_meta_placeholders( $value, $placeholder_map ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $item ) {
				$value[ $key ] = $this->remap_dummy_meta_placeholders( $item, $placeholder_map );
			}

			return $value;
		}

		if ( is_string( $value ) ) {
			return preg_replace_callback(
				'/\{(?:page|post|product|category|post_tag|product_cat|product_tag|author)\|[^{}]+\}/',
				function ( $matches ) use ( $placeholder_map ) {
					return isset( $placeholder_map[ $matches[0] ] ) ? $placeholder_map[ $matches[0] ] : '';
				},
				$value
			);
		}

		return $value;
	}
}
