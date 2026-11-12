<?php
/**
 * TE_Categories — Categories manager (Edit Mode ke andar).
 *
 * REST (sab par permission: Edit Mode available + manage_categories):
 *   GET  /tameero/v1/categories
 *   POST /tameero/v1/categories            { name }                       → add
 *   POST /tameero/v1/categories/rename     { id, name, change_slug }      → rename
 *   POST /tameero/v1/categories/delete     { id, reassign_to }            → delete
 *
 * Usool (brief se locked):
 *  - Rename par slug/URL default mein FROZEN rehta hai; "URL bhi badlo"
 *    par hi slug badalta hai aur purane URL se naye par 301 redirect
 *    map mein darj hota hai (option: tameero_edit_redirect_map).
 *  - Delete par posts KABHI delete nahi hote — har post ko pehle
 *    reassign target par lagaya jata hai, phir term delete hota hai.
 *  - Redirect sirf tab check hota hai jab map khaali na ho AUR
 *    request 404 ho (visitor footprint amal mein zero — manzoor-shuda hal).
 *
 * @package TameeroEditMode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TE_Categories {

	const OPTION_REDIRECTS = 'tameero_edit_redirect_map';

	/**
	 * Is theme ki categories taxonomy (config se; default "category",
	 * Dukaan par "product_cat").
	 *
	 * @return string
	 */
	private static function tax() {
		return TE_Config::categories_taxonomy();
	}

	public static function register_routes() {
		$permission = function () {
			return tameero_edit_available() && current_user_can( 'manage_categories' );
		};
		register_rest_route(
			'tameero/v1',
			'/categories',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ __CLASS__, 'list_categories' ],
					'permission_callback' => $permission,
				],
				[
					'methods'             => 'POST',
					'callback'            => [ __CLASS__, 'add_category' ],
					'permission_callback' => $permission,
				],
			]
		);
		register_rest_route(
			'tameero/v1',
			'/categories/rename',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'rename_category' ],
				'permission_callback' => $permission,
			]
		);
		register_rest_route(
			'tameero/v1',
			'/categories/delete',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'delete_category' ],
				'permission_callback' => $permission,
			]
		);
	}

	/**
	 * Categories ki taza list (counts ke sath).
	 *
	 * @return WP_REST_Response
	 */
	public static function list_categories() {
		return rest_ensure_response( [ 'ok' => true, 'categories' => self::payload() ] );
	}

	/**
	 * @return array
	 */
	private static function payload() {
		$out   = [];
		$terms = get_terms( [ 'taxonomy' => self::tax(), 'hide_empty' => false ] );
		if ( is_wp_error( $terms ) ) {
			return $out;
		}
		foreach ( $terms as $cat ) {
			$out[] = [
				'id'    => (int) $cat->term_id,
				'name'  => $cat->name,
				'slug'  => $cat->slug,
				'count' => (int) $cat->count,
			];
		}
		return $out;
	}

	/**
	 * Nayi category.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function add_category( $request ) {
		$name = sanitize_text_field( (string) $request->get_param( 'name' ) );
		if ( '' === $name ) {
			return new WP_Error( 'te_cat_name', 'Category ka naam khaali hai.', [ 'status' => 400 ] );
		}
		$result = wp_insert_term( $name, self::tax() );
		if ( is_wp_error( $result ) ) {
			if ( 'term_exists' === $result->get_error_code() ) {
				return new WP_Error( 'te_cat_exists', 'Is naam/slug ki category pehle se mojood hai.', [ 'status' => 409 ] );
			}
			return new WP_Error( 'te_cat_add', $result->get_error_message(), [ 'status' => 400 ] );
		}
		return rest_ensure_response( [ 'ok' => true, 'categories' => self::payload() ] );
	}

	/**
	 * Rename — slug default frozen; change_slug par slug + 301.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rename_category( $request ) {
		$id          = (int) $request->get_param( 'id' );
		$name        = sanitize_text_field( (string) $request->get_param( 'name' ) );
		$change_slug = (bool) $request->get_param( 'change_slug' );
		$term        = get_term( $id, self::tax() );
		if ( ! $term || is_wp_error( $term ) ) {
			return new WP_Error( 'te_cat_missing', 'Category mojood nahi hai.', [ 'status' => 404 ] );
		}
		if ( '' === $name ) {
			return new WP_Error( 'te_cat_name', 'Category ka naam khaali hai.', [ 'status' => 400 ] );
		}

		$args = [ 'name' => $name ];
		if ( $change_slug ) {
			$new_slug = sanitize_title( $name );
			if ( '' !== $new_slug && $new_slug !== $term->slug ) {
				// Slug ki takraar ho to WP khud -2 lagata hai wp_update_term mein nahi;
				// pehle check karke saaf error do.
				$existing = get_term_by( 'slug', $new_slug, self::tax() );
				if ( $existing && (int) $existing->term_id !== $id ) {
					return new WP_Error( 'te_cat_slug', 'Is slug ki category pehle se mojood hai — naam thora badal kar koshish karein.', [ 'status' => 409 ] );
				}
				$args['slug'] = $new_slug;
			}
		}

		$result = wp_update_term( $id, self::tax(), $args );
		if ( is_wp_error( $result ) ) {
			return new WP_Error( 'te_cat_rename', $result->get_error_message(), [ 'status' => 400 ] );
		}

		if ( isset( $args['slug'] ) ) {
			$map              = get_option( self::OPTION_REDIRECTS, [] );
			if ( ! is_array( $map ) ) {
				$map = [];
			}
			$map[ $term->slug ] = $args['slug'];
			update_option( self::OPTION_REDIRECTS, $map );
		}

		return rest_ensure_response( [ 'ok' => true, 'categories' => self::payload() ] );
	}

	/**
	 * Delete — pehle posts reassign, phir term delete. Posts kabhi delete nahi.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function delete_category( $request ) {
		$id          = (int) $request->get_param( 'id' );
		$reassign_to = (int) $request->get_param( 'reassign_to' );
		$term        = get_term( $id, self::tax() );
		if ( ! $term || is_wp_error( $term ) ) {
			return new WP_Error( 'te_cat_missing', 'Category mojood nahi hai.', [ 'status' => 404 ] );
		}
		if ( $reassign_to === $id ) {
			return new WP_Error( 'te_cat_reassign', 'Posts ko usi category mein nahi bheja ja sakta jo delete ho rahi hai.', [ 'status' => 400 ] );
		}
		if ( $reassign_to > 0 ) {
			$target = get_term( $reassign_to, self::tax() );
			if ( ! $target || is_wp_error( $target ) ) {
				return new WP_Error( 'te_cat_reassign', 'Reassign wali category mojood nahi hai.', [ 'status' => 400 ] );
			}
		} else {
			// 0 = taxonomy ki default category (post category par
			// default_category; product_cat par WooCommerce ki
			// default_product_cat).
			$default_option = 'category' === self::tax() ? 'default_category' : 'default_' . self::tax();
			$reassign_to    = (int) get_option( $default_option );
			if ( $reassign_to === $id ) {
				return new WP_Error( 'te_cat_default', 'Ye is ki default category hai — pehle koi doosri category chunein jis mein posts jayen.', [ 'status' => 400 ] );
			}
			if ( $reassign_to <= 0 && 'category' !== self::tax() ) {
				return new WP_Error( 'te_cat_reassign', 'Pehle wo category chunein jis mein is ki products/posts jayen — is taxonomy ki koi default category set nahi hai.', [ 'status' => 400 ] );
			}
		}

		// Har post/object ko pehle target par lagao (append), taake koi
		// post bila-category na reh jaye; phir term delete karo.
		$object_ids = get_objects_in_term( $id, self::tax() );
		if ( ! is_wp_error( $object_ids ) ) {
			foreach ( $object_ids as $object_id ) {
				wp_set_object_terms( $object_id, [ $reassign_to ], self::tax(), true );
			}
		}

		$deleted = wp_delete_term( $id, self::tax() );
		if ( is_wp_error( $deleted ) || ! $deleted ) {
			return new WP_Error( 'te_cat_delete', 'Category delete nahi ho saki.', [ 'status' => 500 ] );
		}

		// Is category ki taraf jane wale purane redirects saaf karo (slug ab
		// ghayab hai — entry reh jaye to map mein mardah rasta reh jata hai).
		$map = get_option( self::OPTION_REDIRECTS, [] );
		if ( is_array( $map ) ) {
			$changed = false;
			foreach ( $map as $old_slug => $target_slug ) {
				if ( $target_slug === $term->slug || $old_slug === $term->slug ) {
					unset( $map[ $old_slug ] );
					$changed = true;
				}
			}
			if ( $changed ) {
				update_option( self::OPTION_REDIRECTS, $map );
			}
		}

		return rest_ensure_response( [ 'ok' => true, 'categories' => self::payload() ] );
	}

	/**
	 * 301 redirect: sirf tab jab map khaali na ho aur request 404 de rahi ho.
	 * template_redirect par lagta hai — har aam page view par koi kaam nahi.
	 */
	public static function maybe_redirect() {
		if ( ! is_404() ) {
			return;
		}
		$map = get_option( self::OPTION_REDIRECTS, [] );
		if ( ! is_array( $map ) || empty( $map ) ) {
			return;
		}
		// NOTE: REQUEST_URI par sanitize_text_field NAHI lagana — wo percent-encoded
		// (%d8%a7...) slug ko tor deta hai; slug agay ja kar sanitize_title hota hai.
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Recommended -- sirf path parhna hai, slug baad mein sanitize_title hota hai
		$path        = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
		$base        = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( '' !== $base && '/' !== $base ) {
			$path = preg_replace( '#^' . preg_quote( $base, '#' ) . '#', '', $path ) ?? $path;
		}
		$segments = array_values( array_filter( explode( '/', trim( $path, '/' ) ) ) );
		// Category permastruct: pehla segment base (masalan "category"), doosra slug.
		if ( count( $segments ) < 2 ) {
			return;
		}
		$slug = $segments[ count( $segments ) - 1 ];
		$slug = sanitize_title( $slug );
		if ( isset( $map[ $slug ] ) ) {
			$term = get_term_by( 'slug', $map[ $slug ], self::tax() );
			if ( $term && ! is_wp_error( $term ) ) {
				$link = get_term_link( $term );
				if ( ! is_wp_error( $link ) ) {
					wp_safe_redirect( $link, 301 );
					exit;
				}
			}
		}
	}
}
