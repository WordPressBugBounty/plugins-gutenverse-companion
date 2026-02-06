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
	 *
	 * @return int|null
	 */
	public static function handle_file( $url ) {
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

			try {
				$attach_data = wp_generate_attachment_metadata( $attach_id, $file_loc );
				wp_update_attachment_metadata( $attach_id, $attach_data );
			} catch ( \Exception $e ) {
				// none.
			} catch ( \Throwable $t ) {
				// none.
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

		$theme_mode  = apply_filters( 'gutenverse_plus_mechanism_content_type', 'normal' );
		$theme_dir   = get_template_directory();
		$theme_url   = get_template_directory_uri();
		$theme_slug  = get_option( 'stylesheet' );
		$active_slug = get_stylesheet();

		$page_slug = sanitize_title( $title );
		$page_slug = str_replace( '-', '_', $page_slug );
		$json_path = "{$theme_dir}/gutenverse-pages/{$page_slug}.json";

		if ( ! $wp_filesystem->exists( $json_path ) ) {
			return new \WP_Error( 'file_not_found', 'Page definition not found.' );
		}

		$page = json_decode( $wp_filesystem->get_contents( $json_path ), true );
		if ( empty( $page ) ) {
			return new \WP_Error( 'invalid_json', 'Invalid page JSON.' );
		}

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
		* -------------------- */
		$content = str_replace(
			array( '{{home_url}}', "\'" ),
			array( $theme_url, "'" ),
			$page['content']
		);

		/*
		--------------------
		* Import patterns
		* -------------------- */
		foreach ( array( 'core-patterns', 'pro-patterns', 'gutenverse-patterns' ) as $pattern_key ) {
			if ( ! empty( $page[ $pattern_key ] ) ) {
				$this->import_synced_patterns( $page[ $pattern_key ], $theme_slug, $inserted_content );
			}
		}

		/*
		--------------------
		* Image handling
		* -------------------- */
		if ( ! empty( $page['image_arr'] ) ) {
			$images = json_decode( $page['image_arr'] );
			if ( empty( $images ) ) {
				/**
				 * 1. Convert single quotes to double quotes
				 * 2. Decode as JSON
				 */
				$json   = preg_replace( "/'/", '"', $page['image_arr'] );
				$images = json_decode( $json, true );
			}
			if ( is_array( $images ) ) {
				$content = wp_slash( $content );

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
			}
		}

		/*
		--------------------
		* Page creation/update
		* -------------------- */
		$page_id = null;

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
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $page_id );
		}

		/*
		--------------------
		* Store inserted page
		* -------------------- */
		if ( $page_id ) {
			$slug                               = sanitize_title( $page['pagetitle'] );
			$inserted_content['pages'][ $slug ] = array(
				'id'              => $page_id,
				'is_remapped'     => false,
				'has_placeholder' => ! empty( $page['placeholder'] ) ? true : false,
				'placeholder'     => ! empty( $page['placeholder'] ) ? $page['placeholder'] : '',
			);

			/**Check if content has menu */
			preg_match_all( '/"menuId":(\d+)/', $content, $matches );
			if ( ! empty( $matches[0] ) ) {
				$inserted_content['content_has_menus'][] = $page_id;
			}

			update_option(
				"gutenverse_{$active_slug}_content_inserted",
				$inserted_content
			);
		}

		return $page_id;
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
			$menu_name   = get_option( 'stylesheet' ) . ' ' . ( (int) $key + 1 );
			$old_menu_id = $menu->menu_id;

			$menu_obj = wp_get_nav_menu_object( $menu_name );
			$menu_id  = $menu_obj ? $menu_obj->term_id : wp_create_nav_menu( $menu_name );

			if ( is_wp_error( $menu_id ) ) {
				continue;
			}

			// Fetch existing items ONCE
			$existing_items = wp_get_nav_menu_items( $menu_id );
			$item_map       = array();

			if ( $existing_items ) {
				foreach ( $existing_items as $item ) {
					$object_slug                                    = get_post_meta( $item->ID, '_' . $active_slug . '_menu_item_object_slug', true );
					$item_map[ $item->object . '_' . $object_slug ] = array(
						'id'     => $item->ID,
						'object' => $item->object,
					);
				}
			}

			$parent_map = array();

			foreach ( $menu->menu_data as $idx => $data ) {
				$menu_parent = 0;
				$url         = $data->url ?? '#';

				/* Parent mapping */
				if ( null !== $data->parent && isset( $parent_map[ $data->parent ] ) ) {
					$menu_parent = $parent_map[ $data->parent ];
				}

				$existed = $item_map[ $data->type . '_' . $data->object_slug ] ?? null;

				$args = array(
					'menu-item-title'     => $data->title,
					'menu-item-status'    => 'publish',
					'menu-item-parent-id' => $menu_parent,
				);

				/* Page links */
				if ( $data->object_slug && 'page' === $data->type ) {
					$page_id = null;

					// 1. Get page ID from inserted content map
					if (
					isset( $inserted_content['pages'][ $data->object_slug ]['id'] ) &&
					$inserted_content['pages'][ $data->object_slug ]['id']
					) {
						$page_id = (int) $inserted_content['pages'][ $data->object_slug ]['id'];
					}

					// 2. Fallback: find existing page by path
					if ( ! $page_id ) {
						$existed_page = get_page_by_path( $data->object_slug, OBJECT, 'page' );

						if ( $existed_page instanceof WP_Post ) {
							$page_id = (int) $existed_page->ID;
						}
					}

					if ( $existed && 'page' === $existed['object'] ) {
						continue;
					}

					// 3. Final validation and menu args
					if ( $page_id ) {
						$post = get_post( $page_id );

						if ( $post && 'page' === $post->post_type ) {
							$args['menu-item-type']      = 'post_type';
							$args['menu-item-object']    = 'page';
							$args['menu-item-object-id'] = $page_id;
						}
					}
				}

				/* Term links */
				elseif ( $data->object_slug && in_array( $data->type, array( 'category', 'post_tag' ), true ) ) {
					$term = get_term_by( 'slug', 'dummy-' . $data->object_slug, $data->type );

					if ( $term->taxonomy === $existed['object'] ) {
						continue;
					}
					if ( $term && ! is_wp_error( $term ) ) {
						$args['menu-item-type']      = 'taxonomy';
						$args['menu-item-object']    = $term->taxonomy;
						$args['menu-item-object-id'] = (int) $term->term_id;
					}
				}

				/* Fallback to custom link */
				else {
					$args['menu-item-type'] = 'custom';
					$args['menu-item-url']  = esc_url_raw( $url ?? '#' );
				}

				$menu_item_id = $item_map[ $data->type . '_' . $data->object_slug ]['id'] ?? 0;

				$menu_item_id = wp_update_nav_menu_item(
					$menu_id,
					$menu_item_id,
					$args
				);

				$exist_object_slug = get_post_meta( $menu_item_id, '_' . $active_slug . '_menu_item_object_slug', true );
				if ( ! $exist_object_slug && 'custom' !== $args['menu-item-type'] ) {
					update_post_meta( $menu_item_id, '_' . $active_slug . '_menu_item_object_slug', $data->object_slug );
				}

				if ( ! is_wp_error( $menu_item_id ) && ! empty( $data->have_child ) ) {
					$parent_map[ $idx ] = $menu_item_id;
				}
			}

			if ( $old_menu_id !== $menu_id ) {
				$inserted_content['menus'][ 'old_' . $old_menu_id ] = array(
					'old_id' => $old_menu_id,
					'id'     => $menu_id,
					'name'   => $menu_name,
				);
			}
		}

		update_option( $option_key, $inserted_content );

		return true;
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
	public function import_synced_patterns( $patterns, $theme_slug, &$inserted_content ) {
		$pattern_list = get_option( $theme_slug . '_synced_pattern_imported', false );
		if ( ! $pattern_list ) {
			$pattern_list = array();
		}

		foreach ( $patterns as $block_pattern ) {
			$pattern_file = get_theme_file_path( '/inc/patterns/' . $block_pattern . '.php' );
			$pattern_data = require $pattern_file;

			if ( (bool) $pattern_data['is_sync'] ) {
				$post    = get_page_by_path( $block_pattern . '-synced', OBJECT, 'wp_block' );
				$post_id = $post ? $post->ID : null;
				if ( empty( $post ) ) {
					/**Download Image */
					$content = wp_slash( $pattern_data['content'] );
					if ( $pattern_data['images'] ) {
						$images = json_decode( $pattern_data['images'] );
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
							}
						}
					}
					$post_id = wp_insert_post(
						array(
							'post_name'    => $block_pattern . '-synced',
							'post_title'   => $pattern_data['title'],
							'post_content' => $content,
							'post_status'  => 'publish',
							'post_author'  => 1,
							'post_type'    => 'wp_block',
						)
					);
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

					$pattern_data['content']  = '<!-- wp:block {"ref":' . $post_id . '} /-->';
					$pattern_data['inserter'] = false;
					$pattern_data['slug']     = $block_pattern;

					$pattern_list[] = $pattern_data;
					/**Check if content has menu */
					preg_match_all( '/"menuId":(\d+)/', $content, $matches );
					if ( ! empty( $matches[0] ) ) {
						$inserted_content['content_has_menus'][] = $post_id;
					}
				}
			}
		}

		update_option( $theme_slug . '_synced_pattern_imported', $pattern_list );
	}

	/**
	 * Handle Import Posts
	 */
	public function import_posts() {
		try {
			global $wp_filesystem;

			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();

			$type = apply_filters( 'gutenverse_plus_mechanism_content_type', 'normal' );
			if ( ! in_array( $type, array( 'news' ), true ) ) {
				return new WP_REST_Response(
					array(
						'message' => 'Import failed due to an unexpected error while importing dummy content. Please try again.',
					),
					400
				);
			}

			$base_path         = get_theme_file_path( '/posts' );
			$active_theme_slug = get_stylesheet();
			$inserted_dummies  = get_option(
				'gutenverse_' . $active_theme_slug . '_dummy_inserted',
				array(
					'posts'                => array(),
					'posts-dummies'        => array(),
					'category'             => array(),
					'category-dummies'     => array(),
					'post_tag'             => array(),
					'post_tag-dummies'     => array(),
					'post_content_images'  => array(),
					'post_featured_images' => array(),
				)
			);

			/*
			-----------------------------
			* Import Taxonomies
			* ----------------------------- */
			$post_categories_path = $base_path . '/post_categories.json';
			if ( file_exists( $post_categories_path ) ) {
				$data = json_decode( file_get_contents( $post_categories_path ), true );
				$this->import_taxonomies( $data, 'category', $inserted_dummies );
			}

			$post_tags_path = $base_path . '/post_tags.json';
			if ( file_exists( $post_tags_path ) ) {
				$data = json_decode( file_get_contents( $post_tags_path ), true );
				$this->import_taxonomies( $data, 'post_tag', $inserted_dummies );
			}

			/*
			-----------------------------
			* Prepare Post Contents
			* ----------------------------- */
			$prepared_contents = $this->prepare_post_contents(
				$base_path . '/post_content.json',
				$inserted_dummies
			);

			/*
			-----------------------------
			* Prepare Featured Images
			* ----------------------------- */
			$prepared_images = $this->prepare_featured_images(
				$base_path . '/post_images.json',
				$inserted_dummies
			);

			/*
			-----------------------------
			* Insert / Update Posts
			* ----------------------------- */
			$posts_path = trailingslashit( $base_path . '/post-list' );
			$files      = $wp_filesystem->dirlist( $posts_path );

			if ( empty( $files ) ) {
				throw new \Exception( 'No post files found.' );
			}

			foreach ( $files as $file ) {
				if ( $file['name'] === '.DS_Store' ) {
					continue;
				}

				$post_data = json_decode(
					file_get_contents( $posts_path . $file['name'] ),
					true
				);

				if ( empty( $post_data['name'] ) ) {
					continue;
				}

				$content = $prepared_contents[ array_rand( $prepared_contents ) ];
				$image   = $prepared_images[ array_rand( $prepared_images ) ];

				$post_id = $this->insert_or_update_post(
					$post_data,
					$content,
					$image['id']
				);

				/* Register dummy mapping */
				$inserted_dummies['posts'][ '{post|id|' . $post_data['ID'] . '}' ]    = $post_id;
				$inserted_dummies['posts'][ '{post|title|' . $post_data['ID'] . '}' ] = $post_data['title'];
				$inserted_dummies['posts-dummies'][]                                  = $post_id;

				/* Categories & Tags */
				$this->assign_terms(
					$post_id,
					$post_data['categories'] ?? array(),
					'category',
					$inserted_dummies
				);

				$this->assign_terms(
					$post_id,
					$post_data['tags'] ?? array(),
					'post_tag',
					$inserted_dummies
				);
			}

			update_option(
				'gutenverse_' . $active_theme_slug . '_dummy_inserted',
				$inserted_dummies
			);

		} catch ( \Throwable $th ) {
			return new WP_REST_Response(
				array(
					'message' => 'Import failed due to an unexpected error while importing dummy content. Please try again.',
				),
				400
			);
		}

		return new WP_REST_Response(
			array(
				'message' => 'Posts imported successfully',
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
			$content = $post['content'];

			foreach ( $post['images'] as $img ) {
				$image                                     = self::check_image_exist( $img ) ?: self::handle_file( $img );
				$inserted_dummies['post_content_images'][] = $image['id'];
				$content                                   = str_replace( $img, $image['url'], $content );
			}

			$results[] = array(
				'content' => $content,
				'excerpt' => $post['excerpt'],
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
	 * Insert or Update Post
	 *
	 * @param array $data .
	 * @param array $content .
	 * @param int   $thumbnail_id .
	 *
	 * @return int
	 */
	private function insert_or_update_post( $data, $content, $thumbnail_id ) {
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
			'post_type'    => 'post',
			'post_date'    => $data['date'],
		);

		if ( $existing ) {
			$postarr['ID'] = $existing[0]->ID;
			$post_id       = wp_update_post( $postarr );
		} else {
			$post_id = wp_insert_post( $postarr );
		}

		set_post_thumbnail( $post_id, $thumbnail_id );

		return $post_id;
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
					'category'             => array(),
					'category-dummies'     => array(),
					'post_tag'             => array(),
					'post_tag-dummies'     => array(),
					'post_content_images'  => array(),
					'post_featured_images' => array(),
				)
			);

			$merge_all_dummies = array_merge( $inserted_dummies['posts'], $inserted_dummies['category'], $inserted_dummies['post_tag'] );

			/**Remap Patterns */
			$inserted_patterns = $inserted_content['patterns'];
			foreach ( $inserted_patterns as &$pattern ) {
				if ( $pattern['is_remapped'] ) {
					continue;
				}
				$post = get_post( $pattern['id'] );
				if ( $post ) {
					$content = $post->post_content;

					/**Has Menu */
					if ( in_array( $pattern['id'], $inserted_content['content_has_menus'] ) ) {
						$content = $this->check_navbar( $content, $inserted_content['menus'] );
					}

					/**Has Dummy */
					foreach ( $pattern['placeholder'] as $key => $dummy ) {
						$placeholder = $dummy;
						$parts       = explode( '|', trim( $placeholder, '{}' ) );
						$part        = $parts[2] ?? null;
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

					/**Has Menu */
					if ( in_array( $page['id'], $inserted_content['content_has_menus'] ) ) {
						$content = $this->check_navbar( $content, $inserted_content['menus'] );
					}

					/**Has Dummy */
					foreach ( $page['placeholder'] as $key => $dummy ) {
						$placeholder = $dummy;

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

			update_option( 'gutenverse_' . get_stylesheet() . '_content_inserted', $inserted_content );
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
			$keys              = array(
				'posts-dummies',
				'category-dummies',
				'tag-dummies',
				'post_content_images',
				'post_featured_images',
			);
			foreach ( $keys as $key ) {
				$dummies = $dummy_inserted[ $key ];
				foreach ( $dummies as $dummy ) {
					if ( is_numeric( $dummy ) ) {
						wp_delete_post( $dummy );
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
			if ( in_array( $block['blockName'], $block_menus ) ) {
				$block_before = serialize_block( $block );
				$block_after  = '';

				if ( ! empty( $block['attrs']['menuId'] ) ) {
					$original_menu_id = $block['attrs']['menuId'];
					$menu_exists      = $placeholder_data[ 'old_' . $original_menu_id ];
					if ( $menu_exists ) {
						$menu_id                  = $menu_exists['id'];
						$block['attrs']['menuId'] = $menu_id;
					}
					$block_after = serialize_block( $block );
				}

				$content = str_replace( $block_before, $block_after, $content );
			}
		}
		return $content;
	}
}
