<?php
/**
 * Load more utility class
 *
 * The class is used to generate the load more button, perform the ajax call
 * and append the result to the specified element.
 *
 * The template, used to render each element, have to be stored into
 * the template-parts folder, and called:
 *
 * "{$post_type}-single-item.php"
 *
 * @package load-more
 */

namespace Nine3;

use function Nine3\Stella\Icons\svg;

define( 'LAMA_VERSION', '1.0.1' );

/**
 * Load More Class
 */
class Lama {
	/**
	 * Used to know which type of HTML filter to generate.
	 */
	const SELECT   = 'select';
	const RADIO    = 'radio';
	const CHECKBOX = 'checkbox';

	/**
	 * The $query variable used when invoking ::start( ... )
	 * This information is needed by the end method.
	 * 
	 * N.B. Updated this property to public due to the need to access it ina a template.
	 * Lama needs rebuilding at some point.
	 *
	 * @var WP_Query
	 */
	public static $current_query;

	/**
	 * If true add an hidden input so custom pagination will be automatically injected
	 * in the custom
	 *
	 * @var boolean
	 */
	private static $show_pagination = false;

	/**
	 * Current query offset stored as need to be added after the closing tag.
	 *
	 * @var int
	 */
	private static $offset = 0;

	/**
	 * The current form name.
	 *
	 * @var string
	 */
	private static $current_name = null;

	/**
	 * Used to check if need to load the basic assets/lama.css file.
	 *
	 * @var boolean
	 */
	private static $load_basic_css = false;

	/**
	 * The unique id to identify the current query.
	 *
	 * @var string
	 */
	private static $unique_id = null;

	/**
	 * Array to be saved in the temp file.
	 *
	 * This array stores the query data needed by LAMA to work properly
	 *
	 * @var array
	 */
	private static $temp_args = [];

	/**
	 * Array to be saved in the temp file.
	 *
	 * This array stores the internal LAMA parameters.
	 *
	 * @var array
	 */
	private static $temp_lama = [];

	/**
	 * Initialise the class, by adding the required ajax wp hook.
	 *
	 * @param bool $basic_css if true inject a basic CSS used to animate the custom dropdown.
	 */
	public static function init( $basic_css = true ) {
		add_action( 'wp_ajax_nine3_lama', [ __CLASS__, 'load_more' ] );
		add_action( 'wp_ajax_nopriv_nine3_lama', [ __CLASS__, 'load_more' ] );

		// The script is included only when calling the Lama\start() function.
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'register_script' ] );

		/**
		 * When using lama with the main query the following parameter will be automatically used when
		 * doing dynamic content:
		 *
		 * -> query=main or when specifing the parameter `lama=1` in your custom WP_Query.
		 *
		 * In this case when refreshing the page, lama will take care of applying the filters present in the url.
		 */
		add_action( 'pre_get_posts', [ __CLASS__, 'pre_get_posts' ], 99, 1 );

		self::$load_basic_css = $basic_css;
	}

	/**
	 * Register the script needed.
	 */
	public static function register_script() {
		/**
		 * We cannot use plugins_url function because the class might be
		 * installed in theme using composer.
		 */
		$url  = trailingslashit( str_replace( ABSPATH, get_site_url( null, '/' ), __DIR__ ) );
		// $url = get_site_url( null );
		$dist = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? 'src/' : 'dist/';
		$dist = 'src/';

		wp_register_script( 'nine3-lama', $url . $dist . 'lama.js', [ 'jquery' ], LAMA_VERSION, true );

		/**
		 * During development version might have a situation where the HTTPs is not forced yet.
		 * And querying HTTPs url from a normal HTTP causes the request to fail.
		 * So, removing the HTTP(s) protocol from the string we make sure
		 * to avoid this kind of situation.
		 */
		$data = array(
			'ajaxurl'    => preg_replace( '/https?:\/\//', '//', admin_url( 'admin-ajax.php' ) ),
			// 'ajaxurl'    => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( 'lama' ),
			'archiveurl' => get_post_type() == 'post' ? get_permalink( get_option( 'page_for_posts' ) ) : get_post_type_archive_link( get_post_type() ),
		);

		wp_localize_script( 'nine3-lama', 'lama', $data );

		// Load the basic css?
		if ( self::$load_basic_css ) {
			wp_enqueue_style( 'lama-css', $url . 'assets/lama.css', [], LAMA_VERSION );
		}
	}

	/**
	 * Parse the data sent via $_POST and so loads the new posts to be loaded.
	 */
	public static function load_more() {
		check_ajax_referer( 'lama', 'nonce' );

		if ( ! isset( $_POST['lamaFilter'] ) ) {
			die( -1 );
		}

		// Lama has been initialised.
		do_action( 'lama_init' );

		// The form name (used for the filters).
		$name               = sanitize_title( wp_unslash( $_POST['lamaName'] ) );
		self::$current_name = $name;

		// Allow to run some action for a specific filter only.
		do_action( 'lama_init__' . $name );

		// The WP_Query arguments.
		$params = [];

		if ( isset( $_POST['params'] ) ) {
			$post_data = wp_unslash( $_POST['params'] );
			parse_str( $post_data, $params );
			unset( $params['query'] );
		}

		// Build the WP_Query $args parameters.
		$args = [
			'post_status' => 'publish',
		];

		// Load the temp file generated with the "hidden" settings.
		$query_args                = [];
		$lama                      = []; // Internal parameters.
		list( $query_args, $lama ) = self::get_temp_data();

		if ( is_array( $query_args ) ) {
			$args = array_merge( $args, $query_args );
		}

		foreach ( $params as $key => $param ) {
			if ( stripos( $key, 'lama-' ) === 0 ) {
				$key = str_replace( 'lama-', '', $key );

				$lama[ $key ] = $param;
			} elseif ( is_array( $param ) ) {
				continue;
			} else {
				$params[ $key ] = maybe_unserialize( $param );
			}
		}

		self::debug( 'FORM NAME: ' . self::$current_name );
		self::debug( 'Params received:' );
		self::debug( $params );

		$args = self::filter_wp_query( $args, $params );

		if ( empty( $params['filter-search'] ) ) {
			self::debug( 'SEARCH EMPTY' );
			unset( $args['s'] );
		}

		// $name is defined only after running ::start, so cannot be used in the pre_get_posts.
		$args = apply_filters( 'lama_args__' . $name, $args, $params, $lama );

		self::debug( 'WP_Query arguments applied:' );
		self::debug( $args );

		// WP_Query run.
		$posts     = [];
		$tax_terms = [];
		$post_type = $args['post_type'] ?? 'post';

		if ( ! empty( $args ) ) {
			$posts = new \WP_Query( $args );

			self::debug( $posts->request, '(SQL) ' );

			self::$current_query = $posts;

			// This block of code cross-references and disables taxonomies based on whats found in the post type.
			// This needs a refactor (taken from Mitie).

			// if ( $post_type === 'post' || $post_type === 'resource' ) {
			// 	$args['posts_per_page'] = -1;
			// 	$filter_posts = new \WP_Query( $args );

			// 	$post_ids = wp_list_pluck( $filter_posts->posts, 'ID' );

			// 	foreach ( $post_ids as $post_id ) {
			// 		// This is custom functionality to compile a list of all taxonomy terms for each post.
			// 		// This is later used in lama.js to disable dropdown options.
			// 		$filter_tax = [
			// 			'category',
			// 			'topic',
			// 		];
			// 		if ( $post_type === 'resource' ) {
			// 			$filter_tax = [
			// 				'resource-type',
			// 				'topic',
			// 			];
			// 		}
					
			// 		if ( ! empty( $filter_tax ) ) {
			// 			foreach ( $filter_tax as $ftx ) {
			// 				$all_tax_obj[] = get_the_terms( $post_id, $ftx );
			// 			}
			// 		}
					
			// 		if ( is_array( $all_tax_obj ) || is_object( $all_tax_obj ) ) {
			// 			foreach ( $all_tax_obj as $tax_obj ) {
			// 				foreach ( $tax_obj as $single_tax ) {
			// 					if ( ! in_array( $single_tax->slug, $tax_terms, true ) ) {
			// 						array_push( $tax_terms, $single_tax->slug );
			// 					}
			// 				}
			// 			}
			// 		}
			// 	}
			// }
		}

		// Allow 3rd party to inject HTML before the lama's loop.
		do_action( 'lama_before_loop__' . $name, $posts, $args, $params );

		/**
		 * By default the class, for each single post, is looking for the following file name:
		 *
		 * -> template-parts/[post-type]-single-item.php
		 *
		 * or
		 *
		 * -> template-parts/[post-type]-single-item-none.php
		 *
		 * if there are no results.
		 *
		 * The default file name can be override using lama_template_[name] filter
		 */
		if ( ( method_exists( $posts, 'have_posts' ) ) ) {
			if ( $posts->have_posts() ) {
				$count = 0;
				while ( $posts->have_posts() ) {
					$posts->the_post();

					$post_type = get_post_type( get_the_ID() );

					$template = apply_filters( 'lama_template__' . $name, 'template-parts/' . $post_type . '-single-item', $post_type, $args );

					self::debug( sprintf( 'Using single template: "%s" for "%s (%d)"', $template, get_the_title(), get_the_ID() ) );

					if ( $post_type !== null ) {
						get_template_part( $template );
					}

					// Allow 3rd party to inject HTML inside lama's loop.
					do_action( 'lama_inside_loop__' . $name, $count );

					$count++;
				}
			} else {
				$template = apply_filters( 'lama_template__' . $name . '_none', 'template-parts/' . $post_type . '-single-item-none', $post_type, $args );

				self::debug( sprintf( 'Using single template: "%s" for "%s (%d)"', $template, get_the_title(), get_the_ID() ) );

				if ( $post_type !== null ) {
					get_template_part( $template );
				}
			}
		}

		if ( is_array( $args ) ) {
			$found      = intval( $posts->found_posts );
			$post_count = intval( $posts->post_count );

			// Let's put back the custom pagination :).
			if ( isset( $lama['pagination'] ) ) {
				self::pagination( $posts, true, $lama );
			}

			// Need to show the # of posts found?
			if ( isset( $lama['posts-found'] ) ) {
				self::posts_found( $lama['posts-found-single'], $lama['posts-found-plural'] );
			}
		}

		/**
		 * User can add some extra HTML after the loop, like pagination, etc.
		 */
		do_action( 'lama_after_loop__' . $name, $posts, $args, $params );

		if ( isset( $found ) ) {
			$offset      = intval( $args['offset'] ?? 0 );
			$lama_offset = $offset + $post_count;

			printf( '<lama-posts-count>%d</lama-posts-count>', (int) $post_count );
			printf( '<lama-offset>%d</lama-offset>', (int) $lama_offset );
			printf( '<lama-found>%d</lama-found>', (int) $found );
		}

		if ( isset( $tax_terms ) ) {
			$tax_terms = implode( ',', $tax_terms );
			printf( '<lama-tax>%s</lama-tax>', $tax_terms );
		}

		wp_reset_postdata();

		die();
	}

	/**
	 * Filter the WP_Query arguments by applying the data got from the URL.
	 * This functions has to be called manually!
	 *
	 * @param array $args array of arguments to pass to WP_Query.
	 * @param array $params the $_POST data.
	 */
	public static function filter_wp_query( $args, $params = [] ) {
		$is_search = isset( $params['filter-search'] ) & ! empty( $params['filter-search'] );

		/**
		 * To avoid conflict with WP the taxonomy names are prefixed with "filter-"
		 * While internal parameters are prefixed with 'lama-'
		 */
		$lama   = [];
		$data   = [];
		$meta   = [];
		$others = [];
		foreach ( $params as $key => $value ) {
			$key   = sanitize_text_field( $key );
			$value = is_array( $value ) ? $value : sanitize_text_field( $value );

			if ( strpos( $key, 'lama-' ) === 0 ) {
				$key = str_replace( 'lama-', '', $key );

				$lama[ $key ] = $value;
			} elseif ( strpos( $key, 'meta-' ) === 0 ) {
				$key = str_replace( 'meta-', '', $key );

				/**
				 * The meta field is always defined when using LAMA's built-in functions.
				 * So, need to check if the filter is needed to be applied.
				 */
				if ( empty( $value ) ) {
					/**
					 * If the meta value passed is empty, we need to check if it exists in the original
					 * query and if so we have to remove it!
					 */
					$meta_query = $args['meta_query'] ?? [];

					foreach ( $meta_query as $id => $arg ) {
						if ( $arg['key'] === $key ) {
							unset( $args['meta_query'][ $id ] );
						}
					}

					continue;
				}

				/**
				 * Check if the filter is present as a previous $params, if so we need to:
				 *  - Convert the "value" as array (if is not an array yet)
				 *  - append it to the list of values
				 */
				if ( isset( $meta[ $key ] ) ) {
					if ( ! is_array( $meta[ $key ]['value'] ) ) {
						$meta[ $key ]['compare'] = 'IN';
						$meta[ $key ]['value']   = [ $meta[ $key ]['value'] ];
					}

					if ( is_array( $value ) ) {
						foreach ( $value as $val ) {
							$meta[ $key ]['value'][] = sanitize_title( $val );
						}
					} else {
						$meta[ $key ]['value'][] = sanitize_title( $value );
					}
				} else {
					if ( is_array( $value ) ) {
						$value = array_map(
							function( $val ) {
									return sanitize_title( $val );
							},
							$value
						);
					} else {
						$value = sanitize_title( $value );
					}

					$meta[ $key ] = [
						'key'   => str_replace( 'meta-', '', $key ),
						'value' => $value,
					];
				}
			} elseif ( stripos( $key, 'filter-' ) === 0 ) {
				$key = str_replace( 'filter-', '', $key );

				$data[ $key ] = $value;
			}
		}

		// Sanitize the $_POST data.
		// Also, any filter that is not a taxonomy will be ignored.
		$taxonomies = [];
		foreach ( $data as $key => $value ) {

			if ( taxonomy_exists( $key ) ) {
				if ( ! is_array( $value ) ) {
					$value = [ $value ];
				}

				$taxonomies[ $key ] = $value;
			} else {
				$others[ $key ] = $value;
			}
		}

		/**
		 * Only "special" key are kept, like:
		 * - search
		 * - sort
		 * - sortby
		 * - offset
		 *
		 * This will prevent the user from passing any custom argument to the WP_Query, like:
		 *  https://example.com?posts_per_page=-1&post_type=post
		 */
		$sort   = $others['sort'] ?? '';
		$sortby = $others['sortby'] ?? '';
		self::debug( $others, 'others ' );

		if ( ! empty( $sort ) ) {
			$args['order'] = $sort;
		}

		if ( ! empty( $sortby ) ) {
			$args['orderby'] = 'post_' . $sortby;
		}

		// Search?
		$search = $others['search'] ?? '';
		if ( ! empty( $search ) ) {
			$args['s'] = $search;
		}

		// Offset conflicts with 'paged', can't use both.
		// Also offset have to be used only when clicking the LOAD MORE button.
		$append = $_POST['lamaAppend'] ?? false;
		self::debug( $append, 'Append: ' );
		if ( $append === 'true' && isset( $params['posts-offset'] ) ) {
			$args['offset'] = intval( $params['posts-offset'] );
		}

		$args = self::modify_wp_query_args( $args, $taxonomies, $meta );

		// Allow 3rd part to modify the $args array.
		return apply_filters( 'lama_args', $args, $params );
	}

	/**
	 * Apply the pre_get_posts filter
	 *
	 * Is it possible to allow LAMA to filter your query by just adding the 'lama' => '1', to the
	 * arguments of your WP_Query.
	 *
	 * @param object $query the WP_Query object.
	 *
	 * @return void
	 */
	public static function pre_get_posts( $query ) {
		$filter_main_query = isset( $_GET['query'] ) && ! empty( $_GET['query'] ) && $query->is_main_query();
		$need_filtering    = ! empty( $query->get( 'lama' ) );

		if ( $filter_main_query || $need_filtering ) {
			$args = [];

			// Allow 3rd part to modify the $args array.
			if ( $filter_main_query ) {
				self::$current_name = sanitize_title( wp_unslash( $_GET['query'] ) );
			}

			/**
			 * When passing 'lama' => '...' to the custom query, normal pagination does not get considered
			 * we have to manually check it.
			 */
			if ( $need_filtering ) {
				self::$current_name = sanitize_title( $query->get( 'lama' ) );

				$current_page = max( 1, get_query_var( 'paged' ) );

				if ( $current_page > 1 ) {
					$args['paged'] = $current_page;
				}
			}

			self::debug( 'FORM NAME: ' . self::$current_name );
			self::debug( 'Params received:' );
			self::debug( $_GET );

			// Prevent the parameter "posts-offset" from being passed in the URL.
			if ( isset( $_GET['posts-offset'] ) ) {
				unset( $_GET['posts-offset'] );
			}

			$args = self::filter_wp_query( $args, $_GET );
			$args = apply_filters( 'lama_args__' . self::$current_name, $args, $_GET, [] );

			self::debug( 'Custom $args values:' );
			self::debug( $args );

			if ( is_array( $args ) ) {
				foreach ( $args as $key => $value ) {
					$query->set( $key, $value );
				}

				self::debug( 'WP_Query query_vars:' );
				self::debug( array_filter( $query->query_vars ) );
			}
		}
	}

	/**
	 * Use the $args to properly set up the argument array needed for the WP_Query.
	 *
	 * For example:
	 * Information like 'taxonomy' are passed as simple array/string by the form, so we need
	 * to convert it in a "taxonomy_query" array used by WP_Query.
	 *
	 * @param array $args WP_Query args to modify.
	 * @param array $taxonomies list of taxonomies to filter.
	 * @param array $meta the meta_query data.
	 */
	private static function modify_wp_query_args( $args, $taxonomies = [], $meta = [] ) {
		/**
		 * Append the taxonomy term
		 */
		if ( isset( $args['taxonomy'] ) && isset( $args['term_taxonomy_id'] ) ) {
			$tax_query[] = [
				'taxonomy' => $args['taxonomy'],
				'field'    => 'term_id',
				'terms'    => array( (int) $args['term_taxonomy_id'] ),
			];

			unset( $args['taxonomy'] );
			unset( $args['term_taxonomy_id'] );
		}

		// Check if there is any taxonomy to filter.
		if ( ! isset( $args['tax_query'] ) || ! is_array( $args['tax_query'] ) ) {
			$args['tax_query'] = [];
		}

		self::debug( $taxonomies, '$taxonomies ' );
		foreach ( $taxonomies as $taxonomy => $values ) {
			if ( is_array( $values ) ) {
				$values = array_filter( $values );
			}

			if ( empty( $values ) ) {
				continue;
			}

			$tq = [
				'taxonomy' => $taxonomy,
				'field'    => 'slug',
				'terms'    => $values,
			];

			if ( is_array( $values ) ) {
				$tq['compare'] = 'IN';
			}

			$args['tax_query'][] = $tq;
		}

		/**
		 * Category?
		 */
		// Filter by category id(s).
		if ( isset( $args['category'] ) ) {
			$values = $args['category'];

			if ( ! is_array( $values ) ) {
				$values = array( $values );
			}

			$args['category__in'] = $values;
			unset( $args['category'] );
		}

		// Filter by category name(s).
		if ( isset( $args['category-name'] ) ) {
			$values = $args['category-name'];

			if ( is_array( $values ) ) {
				$values = implode( ',', array( $values ) );
			}

			$args['category_name'] = $values;

			unset( $args['category__in'] );
			unset( $args['category-name'] );
			unset( $args['categoryName'] );
		}

		/**
		 * Tag?
		 */
		if ( isset( $args['tag'] ) ) {
			$values = $args['tag'];

			if ( ! is_array( $values ) ) {
				$values = array( $values );
			}
			$args['tag__in'] = $values;
			unset( $args['tag'] );
		}

		/**
		 * Meta filter?
		 */
		if ( ! empty( $meta ) ) {
			if ( ! isset( $args['meta_key'] ) ) {
				$args['meta_query'] = [];
			}

			$args['meta_query'] += $meta;
		}

		return $args;
	}

	/**
	 * Generate the LOAD MORE opening form tag form
	 *
	 * The function add all the following extra informations as hidden inputs:
	 *
	 *  'lama-name'      => a friendly name that will be used to run filters and actions.
	 *  'lama-auto-load' =>     auto load new elements on page scroll.
	 *  'is-search'      => to know if we're on the search page.
	 *  'page-id'        => the current page id.
	 *
	 * @param string   $name a friendly name used to identify the current lama's form when applying the filters.
	 * @param string   $class the custom class to append.
	 * @param bool     $auto_load if true will automatically trigger the load more on page scroll. Default: false.
	 * @param WP_Query $query the custom query used. This is needed to figure out if there are more posts to load.
	 *
	 * @throws Exception Exception if 'name' parameter is empty.
	 */
	public static function start( $name, $class = '', $auto_load = false, & $query = null ) {
		global $wp_query;

		if ( empty( $name ) ) {
			echo 'LAMA: No name specified!';
			throw new Exception( 'LAMA: No name specified!' );
		}

		// Include the load-more.js script.
		wp_enqueue_script( 'nine3-lama' );

		// Friendly name used to trigger actions and filters.
		$name = sanitize_title( $name );

		// Which query to use to figure out what to do here?
		if ( is_null( $query ) ) {
			$query = $wp_query;
		}
		self::$current_query = $query;

		/**
		 * The temporary file used to store the data needed by LAMA.
		 * More information are provided in the documentation of the generate_hidden_fields function.
		 */
		self::$unique_id = spl_object_hash( $query );

		// The form tag.
		$classes = [ 'lama' ];

		// Are there more items to be "loaded"?
		// Get the posts_per_page from the query.
		$count        = isset( $query->posts ) ? count( $query->posts ) : 0;
		$found_posts  = $query->found_posts ?? 0;
		self::$offset = $count;

		// Audo load?
		if ( $auto_load ) {
			self::hidden_lama_field( 'auto-load', 1 );
		}

		/**
		 * Are there more posts to be loaded?
		 */
		if ( $count >= $found_posts ) {
			$classes[] = 'lama-more--none';
		}

		$classes = join( ' ', $classes );
		$class   = trim( $classes . ' ' . $class );
		$action  = '';

		/**
		 * Add the hidden field "lama-id" to let us LAMA know the name of the temporary file.
		 *
		 * This information is needed only when performing the AJAX request, so we append it as data-attribute
		 * as on the page load we already have what we need to make the custom filter working.
		 */
		$uid = self::$unique_id;
		printf(
			'<form id="filters" name="%s" action="%s" method="get" class="%s" data-uid="%s">',
			esc_attr( $name ),
			esc_attr( $action ),
			esc_attr( $class ),
			esc_attr( $uid )
		);

		self::$current_name = $name;
		self::generate_hidden_fields( $query );
	}

	/**
	 * Define the start of the container used to append/inject the new
	 * elements loaded via ajax.
	 *
	 * @param string $class the extra class to use for the container.
	 * @return void
	 */
	public static function container_start( $class = '' ) {
		printf(
			'<div id="%s" class="lama-container %s">',
			esc_attr( self::$current_name ),
			esc_attr( $class )
		);
	}

	/**
	 * The end of the lama container.
	 *
	 * @param bool $show_pagination if true add the custom pagination.
	 * @return void
	 */
	public static function container_end( $show_pagination = false ) {
		/**
		 * The pagination has to be part of the container, as it has to be deleted for every request.
		 */
		if ( $show_pagination ) {
			self::pagination( self::$current_query );
		}

		echo '</div>';

		/**
		 * The field doesn't have to be deleted on the ajax request, that's
		 * why is outside the container
		 */
		if ( $show_pagination ) {
			self::hidden_lama_field( 'pagination', 1 );
		}
	}

	/**
	 * The hidden fields are needed by LAMA to let it know which parameters exists
	 * in the query used.
	 *
	 * This information is needed to make the class working properly, because information
	 * like current taxonomy, or post type have to be passed to the WP_Query called made by
	 * lama when performing dynamic filtering or "load more".
	 *
	 * All the fields with the prefix 'query' are going to be stored in a temp file.
	 * This because exposing them as hidden fields will allow the user to manipulate them,
	 * and so potientally expose all the content of the website via LAMA.
	 *
	 * While all the ones prefixed with `lama-` will be stored inside the $temp_lama and also
	 * added to the temp file.
	 *
	 * # Why we need that?
	 *  Because we want LAMA to work out of the box with minimum configuration :)
	 *
	 * # Scenario:
	 *  Suppose the following filter in the "books" archive page:
	 *   - AZ => Sort by title, ASC
	 *   - ZA => Sort by title, DESC
	 *   - Newest => Sort by date, DESC
	 *   - Oldest => Sort by date, ASC
	 *
	 * # WordPress:
	 *  The main query generated by WordPress contains at the least the following information:
	 *    - post_type = 'book'
	 *
	 * # LAMA:
	 *  Suppose the client choose one of the sorting, the AJAX request is performed.
	 *  At this point the only information we get from the request is:
	 *   - sortby=title&sort=asc
	 *   - lama-query=books
	 *
	 *  What we expect at this point is showing the "Books" sorted by title in ascending order.
	 *  The problem here is that we don't know what post type apply, is not mentioned anywhere.
	 *
	 *  That's why we store the information in the temp file self::$temp_file.
	 *  Its content would look like:
	 *   <?php
	 *     $args['post_type'] = 'books'
	 *
	 *  The same idea is used for custom queries that might contain some tax_query or meta_query
	 *  filtering. So, in this case we have to retain the information.
	 *
	 * # Could we store the information in the form itself as "hidden" input?
	 *  Yes, but this will allow the user to be able to alter all the parameters.
	 *  This might be not a big issue, but will expose all the content you have
	 *  on the website, also gatered content.
	 *
	 * # Why don't "fix" the query parameters programmatically?
	 *  A possible solution would be use the `lama_args__` hook provided by the class and
	 *  allow the developer to add the missing parameters, but we want LAMA to be simple to use
	 *  and so take care of that for us :)
	 *
	 * The "query" information is temporarily stored into the $temp_args array and only saved
	 * when invoking the `LAMA::end` function.
	 * Also, in case LAMA cannot generate the temp file the hook `lama_temp_failed` hook is triggered.
	 *
	 * @param string $name the field name.
	 * @param string $value the field value.
	 * @param string $prefix the prefix to prepend to the field.
	 */
	public static function hidden_field( $name, $value, $prefix = 'field-' ) {
		$value = apply_filters( 'lama_hidden_field', $value, $name );
		$value = apply_filters( 'lama_hidden_field__' . self::$current_name, $value, $name );

		// Something to do here?
		if ( empty( $value ) || $value === 0 ) {
			return;
		}

		if ( $prefix === 'query-' ) {
			self::$temp_args[ $name ] = $value;
		} elseif ( $prefix === 'lama-' ) {
			self::$temp_lama[ $name ] = $value;
		} else {
			if ( is_array( $value ) || is_object( $value ) ) {
				$value = maybe_serialize( $value );
			}

			printf(
				'<input type="hidden" name="%s%s" value="%s" />',
				esc_attr( $prefix ),
				esc_attr( sanitize_title( $name ) ),
				esc_attr( $value )
			);
		}
	}

	/**
	 * Utility function used to call hidden_field with the 3rd parameter set to 'lama-'
	 *
	 * @param string $name  the field name.
	 * @param string $value the field value.
	 * @return void
	 */
	private static function hidden_lama_field( $name, $value ) {
		self::hidden_field( $name, $value, 'lama-' );
	}

	/**
	 * Utility function used to call hidden_field with the 3rd parameter set to 'query'-'
	 *
	 * @param string $name  the field name.
	 * @param string $value the field value.
	 * @return void
	 */
	private static function hidden_query_field( $name, $value ) {
		self::hidden_field( $name, $value, 'query-' );
	}

	/**
	 * Generate the input hidden fields needed for the class to work properly
	 *
	 * @param WP_Query $query the WP_Query used to retrieve the query_vars information.
	 */
	private static function generate_hidden_fields( & $query ) {
		$query_vars = $query->query_vars;

		/**
		 * Keys that do not have to be "passed" to the load more.
		 */
		$to_ignore = [
			'update_post_term_cache',
			'no_found_rows',
			'comments_per_page',
			'lazy_load_term_meta',
			'update_post_meta_cache',
			'nopaging',
			'lama',
			'cache-results',
			'posts_per_page',
			'order',
			'orderby',
			'query-cache_results',
			'cache_results',
			'paged',
		];

		/**
		 * Used to know if we need to append new items or replace the content.
		 *
		 * 0 => no load more (so ignore the offset parameter)
		 * 1 => load more
		 */
		self::hidden_lama_field( 'more', 0 );

		/**
		 * When using tax_query with a single term, wp also set the property:
		 *  - taxonomy
		 *  - term
		 *
		 * But we don't need them in the ajax call.
		 */
		$tax_query = $query->get( 'tax_query' );
		$taxonomy  = $query->get( 'taxonomy' );
		$term      = $query->get( 'term' );
		if ( ! empty( $tax_query ) && ! empty( $taxonomy ) && ! empty( $term ) ) {
			foreach ( $tax_query as $tax ) {
				if ( isset( $tax['taxonomy'] ) && $tax['taxonomy'] === $taxonomy ) {
					$to_ignore[] = 'taxonomy';
					$to_ignore[] = 'term';

					break;
				}
			}
		}

		/**
		 * Do not need to store the 'tax_query' if the filter is applied via $_GET
		 */
		if ( is_array( $tax_query ) ) {
			$tax_to_apply = [];

			foreach ( $tax_query as $id => $tq ) {
				if ( isset( $_GET[ 'filter-' . $tq['taxonomy'] ] ) ) {
					$to_ignore[] = 'tax_query';
				} else {
					$tax_to_apply[ $id ] = $tq;
				}
			}

			self::hidden_lama_field( 'tax_query', $tax_to_apply, 'query-' );
		}

		/**
		 * We don't have to store the meta_query if the filter is applied via $_GET,
		 * otherwise it will be applied every time we remove the filter.
		 */
		$meta_query = $query->get( 'meta_query' );
		if ( is_array( $meta_query ) ) {
			$meta_to_apply = [];

			foreach ( $meta_query as $id => $meta ) {
				// If is not in the URL has to be applied.
				if ( isset( $_GET[ 'meta-' . $meta['key'] ] ) ) {
					$to_ignore[] = 'meta_query';
				} else {
					$meta_to_apply[ $id ] = $meta;
				}
			}

			self::hidden_lama_field( 'meta_query', $meta_to_apply );
		}

		foreach ( $_GET as $filter => $value ) {
			if ( $filter === 'filter-category' ) {
				$to_ignore[] = 'category_name';
				$to_ignore[] = 'cat';
			}
		}

		/**
		 * Sometime the `order` and `orderby` parameters causes problems to the
		 * main query, having wp displaying wrong result for the page.
		 * So, to avoid conflicts we internally use the keyword 'sort'
		 */
		self::hidden_query_field( 'order', $query->get( 'order' ) );
		self::hidden_query_field( 'orderby', $query->get( 'orderby' ) );

		// Let's store all the information needed.
		foreach ( $query_vars as $key => $value ) {
			if ( ! empty( $value ) && ! in_array( $key, $to_ignore ) ) {
				self::hidden_query_field( $key, $value );
			}
		}

		// Let's add some custom information needed internally.
		self::hidden_lama_field( 'base_url', explode( '?', get_pagenum_link( 1 ) )[0] );
		self::hidden_lama_field( 'page_id', get_queried_object_id() );

		if ( $query->is_main_query() ) {
			self::hidden_field( 'query', self::$current_name, '' );
		}
	}

	/**
	 * Close the form tag and add pagination if request.
	 *
	 * The pagination is a modified version of the standard WP one.
	 *
	 * @param bool   $load_more if true add a submit "load more" button.
	 * @param string $button_label the button label.
	 */
	public static function end( $load_more = false, $button_label = null ) {
		// The offset.
		self::hidden_field( 'offset', self::$offset, 'posts-' );

		if ( $load_more ) {
			if ( $button_label === null ) {
				$button_label = __( 'LOAD MORE', 'lama' );
			}

			echo '<button type="submit" class="lama-submit" value="load-more">' . esc_html( $button_label ) . '</button>';
		}

		// Save the "temp" data.
		$temp_file = trailingslashit( sys_get_temp_dir() ) . 'lama-' . self::$unique_id;

		// Save the "temp" data.
		$query_data = var_export( self::$temp_args, true );
		$lama_data  = var_export( self::$temp_lama, true );
		$temp_data  = '<?php $query_args = ' . $query_data . '; $lama_args = ' . $lama_data . ';';

		$saved = file_put_contents( $temp_file, $temp_data, LOCK_EX );

		if ( ! $saved ) {
			self::log( 'LAMA: cannot generate temp file, LAMA will not work properly!' );

			do_action( 'lama_temp_failed' );
		}

		echo '</form>';
	}

	/**
	 * Add a search input field with the data-filter attribute
	 *
	 * @param array $args arguments to customise the search field.
	 * @return void
	 */
	public static function add_search_filter( $args ) {
		$class = trim( $args['class'] . ' lama-filter' );

		printf(
			'<div class="%s">',
			esc_attr( $class )
		);

		$search = $_GET['filter-search'] ?? ''; // PHPCS: XSS Ok.
		printf(
			'<input type="search" name="filter-search" value="%s" class="lama-search %s__input" placeholder="%s" data-debouce="%d" />',
			esc_attr( $search ),
			esc_attr( $args['class'] ),
			esc_attr( $args['placeholder'] ),
			isset( $args['debounce'] ) ? esc_attr( $args['debounce'] ) : 200
		);

		if ( isset( $args['icon'] ) ) {
			printf(
				'<button class="%s__icon">%s</button>',
				esc_html( $args['class'] ),
				$args['icon']
			);
		}

		echo '</div>';
	}

	/**
	 * Generate the Lama's filter using with all the items
	 * from the specified category.
	 *
	 * @param string $taxonomy the taxonomy to filter.
	 * @param array  $args the arguments.
	 * @param enum   $style type of HTML selector to generate (SELECT / COMBO).
	 *
	 * @throws Exception When using an unknown style.
	 */
	public static function add_taxonomy_filter( $taxonomy, $args = [], $style = LAMA::SELECT ) {
		$term_args = $args['term-args'] ?? [];
		// $terms     = get_terms( $taxonomy, $term_args );
		$terms     = get_terms(
			array_merge(
				$term_args,
				['taxonomy' => $taxonomy]
			)
		);

		$values = [];

		foreach ( $terms as $term ) {
			$values[ $term->slug ] = $term->name;
		}

		$args['name']   = $taxonomy;
		$args['values'] = $values;

		if ( $style === LAMA::SELECT ) {
			self::add_dropdown_filter( $args );
		} elseif ( $style === LAMA::CHECKBOX ) {
			self::add_checkbox_filter( $args );
		} elseif ( $style === LAMA::RADIO ) {
			self::add_radio_filter( $args );
		} else {
			throw new Exception( 'LAMA: Unkown style specified for add_taxonomy_filter: ' . $style );
		}
	}

	/**
	 * Utility function used to call add_radio_or_checkbox_filter with 2nd parameter set as 'checkbox'
	 *
	 * @param array $args the arguments needed to generate the HTML output (see documentation).
	 */
	public static function add_checkbox_filter( $args ) {
		self::add_radio_or_checkbox_filter( $args, 'checkbox' );
	}

	/**
	 * Utility function used to call add_radio_or_checkbox_filter with 2nd parameter set as 'radio'
	 *
	 * @param array $args the arguments needed to generate the HTML output (see documentation).
	 */
	public static function add_radio_filter( $args ) {
		self::add_radio_or_checkbox_filter( $args, 'radio' );
	}

	/**
	 * Generate a <select> "filter" from the source array.
	 *
	 * @param array $args arguments used to generate the <select> tag.
	 *
	 * @throws \Exception If the name parameter is empty.
	 * @throws \Exception If the values parameter is not an array.
	 */
	public static function add_dropdown_filter( $args = [] ) {
		$defaults = [
			'name'            => '', // required!
			'values'          => [], // required!
			'placeholder'     => '',
			'clearable'       => true,
			'class'           => '',
			'custom-style'    => true,
			'multiple'        => false,
			'icon'            => '',
			'button-type'     => 'submit',
			'is-meta-filter'  => false,
			'term-args'       => [],
			'container_class' => '',
			'before'          => '',
			'after'           => '',
			'custom-name'     => '',
			'selected'        => '',
		];

		$args = wp_parse_args( $args, $defaults );

		if ( empty( $args['name'] ) ) {
			echo 'LAMA: No name specified for the filter!';
			throw new \Exception( 'LAMA: No name specified for the filter!' );
		}

		if ( ! is_array( $args['values'] ) ) {
			echo 'LAMA: "Values" argument must be an array!';
			throw new \Exception( 'LAMA: "Values" argument must be an array' );
		}

		// Is meta filter or custom name?
		$is_meta_filter = $args['is-meta-filter'];
		$custom_filter  = $args['custom-name'];

		// Check if the filter is present in the url.
		$name        = sanitize_title( $args['name'] );
		$class       = trim( $args['class'] . ' lama__select lama-filter' );
		$filter      = $is_meta_filter ? 'meta' : 'filter';
		if ( $custom_filter ) {
			$filter = $custom_filter;
		}
		$filter_name = sprintf( '%s-%s', $filter, $name );

		// Add selected parameter.
		$custom_selected = $args['selected'];

		if ( ! empty( $custom_selected ) ) {
			$selected = $custom_selected;
		} else {
			if( isset( $_COOKIE[ $filter_name ] ) && ! isset( $_GET[ $filter_name ] ) ) {
				$selected = sanitize_text_field( wp_unslash( $_COOKIE[ $filter_name ] ) );
			} else if ( isset( $_GET[ $filter_name ] ) ) {
				$selected = sanitize_text_field( wp_unslash( $_GET[ $filter_name ] ) );
			} else {
				$selected = '';
			}
		}

		if ( $args['before'] ) {
			echo $args['before']; // PHPCS: XSS Ok.
		}

		$select_class = $class . ' ' . $args['container_class'];

		// If not using the custom-style we need to let the JS know about it, as we
		// need to handle the change event for this element.
		if ( ! $args['custom-style'] ) {
			$select_class .= ' default-style';
		}

		printf(
			'<select name="%s-%s" data-filter="filter-%s" class="%s" %s>',
			esc_attr( $filter ),
			esc_attr( $name ),
			esc_attr( $name ),
			esc_attr( $select_class ),
			$args['multiple'] ? 'multiple' : ''
		);

		/**
		 * The placeholder, if set, is added as disbaled option.
		 */
		if ( ! empty( $args['placeholder'] ) ) {
			printf(
				'<option %s disabled="disabled" style="display: none;">%s</option>',
				selected( null, $selected, false ),
				esc_html( $args['placeholder'] )
			);
		}

		/**
		 * 'Clearable' option.
		 * if the the arg is a string then it is used as the option's label.
		 */
		if ( $args['clearable'] ) {
			$label          = is_string( $args['clearable'] ) ? $args['clearable'] : 'Show all';
			if ( $args['custom-name'] ) {
				$args['values'] = [ 'null' => $label ] + $args['values'];
			} else {
				$args['values'] = [ '' => $label ] + $args['values'];
			}
		}

		foreach ( $args['values'] as $value => $label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $value ),
				$value !== '' ? selected( $value, $selected, false ) : '',
				esc_html( $label )
			);
		}
		echo '</select>';

		/**
		 * The <li> tag can't be styles
		 * http://msdn.microsoft.com/en-us/library/ms535877(v=vs.85).aspx
		 */
		if ( $args['custom-style'] ) {
			self::add_custom_style_filter( $name, $args, $selected );
		}

		if ( $args['after'] ) {
			echo $args['after'];  // PHPCS: XSS Ok.
		}
	}

	/**
	 * Generate the HTML for the custom <select>
	 *
	 * @param string $name the filter name.
	 * @param array  $args the filter arguments.
	 * @param string $selected the selected value.
	 * @return void
	 */
	private static function add_custom_style_filter( $name, $args, $selected ) {
		$values = $args['values'];

		// BEM base class.
		$class = isset( $args['class'] ) && ! empty( $args['class'] ) ? esc_attr( $args['class'] ) : esc_attr( $name );
		printf(
			'<div class="lama__dropdown %s">',
			esc_attr( $class . ' ' . $args['container_class'] )
		);

		$selected_label = '';
		if ( $selected !== '' ) {
			foreach ( $values as $value => $label ) {
				if ( $selected == $value ) {
					$selected_label = $label;

					break;
				}
			}
		} elseif ( ! empty( $args['placeholder'] ) ) {
			$selected_label = $args['placeholder'];
		} else {
			$selected_label = current( $values );
		}

		printf(
			'<button type="button" class="lama__dropdown__selected %s__selected">
				<span class="lama__dropdown__selected__label %s__span">%s</span>
				<span class="lama__dropdown__selected__icon %s__icon">%s</span>
			</button>',
			esc_html( $class ),
			esc_html( $class ),
			esc_html( $selected_label ),
			esc_html( $class ),
			$args['icon']  // Need to allow the developer to pass SVG or <img> tags. PHPCS: XSS Ok.
		);

		printf(
			'<div class="lama__dropdown__list %s__list" style="display: none"><ul class="lama__dropdown__list__items %s__list__items">',
			esc_html( $class ),
			esc_html( $class )
		);

		foreach ( $values as $key => $value ) {
			printf(
				'<li class="lama__dropdown__list__item %s__list__item">
					<button type="%s" data-filter="filter-%s" class="lama__dropdown__list__button %s__list__button" name="filter-%s" value="%s">
					%s
				</li>',
				esc_html( $class ),
				esc_html( $args['button-type'] ),
				esc_attr( $args['name'] ),
				esc_html( $class ),
				esc_attr( $args['name'] ),
				esc_html( $key ),
				esc_html( $value )
			);
		}

		echo '</ul></div></div>';
	}

	/**
	 * Add radio buttons for the filter specified
	 *
	 * @param array  $args arguments.
	 * @param string $type the filter type: LAMA::RADIO, LAMA::CHECKBOX.
	 */
	public static function add_radio_or_checkbox_filter( array $args, string $type ) {
		$name           = $args['name'];
		$class          = $args['class'];
		$values         = $args['values'];
		$icon           = $args['icon'] ?? '';
		$placeholder    = $args['placeholder'] ?? false;
		$is_meta_filter = $args['is-meta-filter'] ?? false;

		$filter      = $is_meta_filter ? 'meta-' : 'filter-';
		$filter_name = sprintf( '%s%s', $filter, $name );

		/**
		 * Check if the field is present in the $current_query as taxonomy or meta field
		 */
		$selected = '';
		$filter   = sanitize_title( $filter );
		if ( isset( $_GET[ $filter_name ] ) ) {
			$selected = wp_unslash( $_GET[ $filter_name ] );
		} else {
			$tax_query = self::$current_query->get( 'tax_query' );

			if ( ! empty( $tax_query ) ) {
				$selected = [];

				foreach ( $tax_query as $tax_value ) {
					$tax_key = $tax_value['taxonomy'];
					$slug    = $tax_value['terms'];

					if ( $tax_key === $name ) {
						if ( ! is_array( $slug ) ) {
							$slug = [ $slug ];
						}

						$selected = array_merge( $selected, $slug );
					}
				}
			}
		}

		if ( empty( $type ) ) {
			throw new Exception( 'LAMA: Please specify the field type' );
		}

		// checkbox value is an array.
		if ( $type === 'checkbox' ) {
			$filter_name = $filter_name . '[]';
		}

		// Add a default option for radio buttons.
		if ( $type === 'radio' && $placeholder ) {
			$checked = $selected === false ? ' checked' : '';

			$values = [ '' => $args['placeholder'] ] + $values;
		}

		if ( isset( $args['container_class'] ) ) {
			echo '<div class="' . $args['container_class'] . '">';
		}

		if ( isset( $args['toggle'] ) ) {
			echo '<div class="checkbox-trigger" data-luna-toggle="' . $args['toggle'] . '"></div>';
		}

		if ( $type === 'checkbox' && $placeholder ) {
			echo '<span class="placeholder">' . $placeholder . '</span>';
		}

		if ( isset( $args['before'] ) ) {
			echo $args['before'];
		}

		foreach ( $values as $key => $value ) {
			$checked = '';
			if ( ( is_array( $selected ) && in_array( $key, $selected ) ) || ( is_string( $selected ) && $selected == $key ) ) {
				$checked = ' checked';
			}

			if ( isset( $args['item_before'] ) ) {
				echo $args['item_before']; // PHPCS: XSS Ok.
			}

			printf(
				'<input
					class="%s lama__%s lama-filter"
					type="%s"
					id="filter-%s-%s"
					name="%s"
					value="%s"
					data-filter="filter-%s"
					%s
				/>
				<label class="%s__label lama__%s__label" 
					for="filter-%s-%s">%s%s</label>',
				esc_attr( $class ),
				esc_attr( $type ),
				esc_attr( $type ),
				esc_attr( $name ),
				esc_attr( $key ),
				esc_attr( $filter_name ),
				esc_attr( $key ),
				esc_attr( $name ),
				esc_attr( $checked ),
				esc_attr( $class ),
				esc_attr( $type ),
				esc_attr( $name ),
				esc_attr( $key ),
				esc_html( $value ),
				$icon // PHPCS: XSS Ok.
			);

			if ( isset( $args['item_after'] ) ) {
				echo $args['item_after']; // PHPCS: XSS Ok.
			}
		}

		if ( isset( $args['after'] ) ) {
			echo $args['after'];
		}

		if ( isset( $args['container_class'] ) ) {
			echo '</div>';
		}
	}

	/**
	 * Show how many posts have been found using the single and plural
	 * form
	 *
	 * The self::$current_query is be used to get the # of posts found.
	 *
	 * @param string $single The text that will be used if $number is 1.
	 * @param string $plural The text that will be used if $number is plural.
	 */
	public static function posts_found( $single = '', $plural = '', $return = false ) {
		if ( $return ) {
			return self::$current_query->found_posts;
		}

		$query = self::$current_query->query_vars;

		$page_total = (
			$query['posts_per_page'] < self::$current_query->found_posts
			? $query['posts_per_page']
			: self::$current_query->found_posts
		);

		$page = array_key_exists( 'paged', $query ) && $query['paged'] ? $query['paged'] : 1;

		// Calculate paginated posts.
		$current = ( $page - 1 ) * $page_total + 1;
		$total   = self::$current_query->found_posts;
		if ( ( $page_total * $page ) < self::$current_query->found_posts ) {
			$total = ( $page_total * $page );
		}

		$found_string = sprintf( __( 'Showing %d-%d of %d', 'stella' ), $current, $total, self::$current_query->found_posts );

		if ( wp_doing_ajax() ) {
			echo '<lama-posts-found-label>' . $found_string . '</lama-posts-found-label>';
		} else {
			self::hidden_lama_field( 'posts-found', 1 );
			echo '<span class="lama-posts-found__label">' . $found_string . '</span>';
		}
	}

	/**
	 * The Custom pagination
	 *
	 * @param bool     $show_ends Whether to show links to the first and last page (where applicable).
	 * @param WP_Query $query the main or custom query
	 * @return void
	 */
	public static function pagination( & $query, $show_ends = true, $params = [] ) {
		global $wp_query, $wp;

		// Needed by the pagination template.
		global $current_page, $show_ends, $first_page, $args, $total_pages, $last_page;

		$total_pages = $query->max_num_pages;
		if ( $total_pages < 2 ) {
			return;
		}

		$current_page = max( 1, get_query_var( 'paged' ) );

		// Get any custom $_GET params from the url, these will be appended to page links further down.
		$custom_params = count( $_GET ) > 0 ? '?' . http_build_query( $_GET ) : '';

		// get the base url of the current archive/taxonomy/whatever page without any pagination queries.
		/**
		 * The base url points to wp-includes/admin.php when doing ajax calls.
		 */
		if ( isset( $params['base_url'] ) ) {
			$base_url = $params['base_url'];
		} else {
			$base_url = explode( '?', get_pagenum_link( 1 ) )[0];
		}

		// get the current filter args from $params, needs to be appended to the base_url.
		if ( isset( $_POST['params'] ) ) {
			parse_str( $_POST['params'], $params_array );
			foreach ( $params_array as $key => $value ) {
				if (
					strpos( $key, 'lama-' ) !== false ||
					empty( $value ) ||
					stripos( $key, 'query-' ) !== false ||
					stripos( $key, 'posts-' ) !== false
				) {
					unset( $params_array[ $key ] );
				}
			}
			$params_string = http_build_query( $params_array );
			if ( strpos( $custom_params, '?' ) === 0 ) {
				$custom_params .= $params_string;
			} else {
				$custom_params = '?' . $params_string;
			}
		}

		// current category / taxonomy / archive url for first link.
		$first_page = $base_url . $custom_params;
		$last_page  = $base_url . __( 'page' ) . '/' . $total_pages . $custom_params;

		$next_icon = svg( 'icon-chevron-right', false );
		$prev_icon = svg( 'icon-chevron-left', false );
	
		$args = [
			'base'      => $base_url . '%_%' . $custom_params . '#' . self::$current_name,
			// 'format'    => 'page/%#%',
			'format'    => __( 'page' ) . '/%#%',
			'current'   => $current_page,
			'total'     => $total_pages,
			'prev_text' => '<span class="nav-inline-dash">' . $prev_icon . '</span>',
			'next_text' => '<span class="nav-inline-dash">' . $next_icon . '</span>',
		];

		$args = apply_filters( 'lama_pagination_args', $args, $show_ends, $params );
		$args = apply_filters( 'lama_pagination_args__' . self::$current_name, $args, $show_ends, $params );

		$lama_template = dirname( __FILE__ ) . '/templates/pagination.php';
		
		$template      = apply_filters( 'lama_pagination_template', $lama_template, $query, $args, $params );
		$template      = apply_filters( 'lama_pagination_template__' . self::$current_name, $template, $query, $args, $params );
		
		self::debug( $args, '(pagination) ' );
		self::debug( $template, 'Pagination Template: ' );
		
		if ( $template ) {
			include $template;
		}
	}

	/**
	 * Get the query_args and lama_args data stored in the temp file
	 *
	 * @return array
	 */
	private static function get_temp_data() {
		$uid    = sanitize_title( $_POST['uid'] );
		$return = [ [], [] ];

		self::debug( 'UID: ' . $uid );
		self::debug( $_POST, '$_POST: ' );

		// Nothing to do here.
		if ( empty( $uid ) ) {
			return $return;
		}

		// Save the "temp" data.
		$temp_file = trailingslashit( sys_get_temp_dir() ) . 'lama-' . $uid;
		if ( ! file_exists( $temp_file ) ) {
			self::debug( 'LAMA: cannot load temp file :(' );

			do_action( 'lama_temp_failed' );

			return $return;
		}

		include $temp_file;

		self::debug( 'Parameters loaded' );
		self::debug( $query_args, '($query_args) ' );
		self::debug( $lama_args, '($lama_args) ' );

		return [ $query_args, $lama_args ];
	}

	/**
	 * Show debug message, if WP_DEBUG is defined and enabled.
	 *
	 * @param mixed $message the log message. It will be converted in string using the print_r function.
	 * @return void
	 */
	public static function debug( $message, $prefix = '' ) {
		$log = 'LAMA: ' . $prefix . print_r( $message, true );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( $log );
		} elseif ( defined( 'LAMA_DEBUG' ) && LAMA_DEBUG ) {
			if ( is_string( $log ) ) {
				$log = date( 'Y-m-d H:i:s: ' ) . $log;
			}

			file_put_contents( ABSPATH . '/wp-content/lama.log', $log . PHP_EOL, FILE_APPEND );
		}
	}
}
