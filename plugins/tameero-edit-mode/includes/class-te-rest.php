<?php
/**
 * TE_REST — Edit Mode ke REST endpoints.
 *
 * POST /wp-json/tameero/v1/save-settings  { values: { theme_mod: value, ... } }
 *   Atomic batch: pehle TAMAM values manifest ke against validate
 *   hoti hain; aik bhi ghalat ho to kuch save NAHI hota (400).
 *
 * POST /wp-json/tameero/v1/render-section { section: "ticker" }
 *   Homepage ko server-side render karke sirf us section ka HTML
 *   deta hai taake save ke baad canvas ka wo hissa taza ho jaye.
 *
 * Dono endpoints: sirf logged-in admin (edit_theme_options) +
 * theme ke pas edit config hona lazmi (warna 403 — hibernation).
 *
 * @package TameeroEditMode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TE_REST {

	public static function register_routes() {
		register_rest_route(
			'tameero/v1',
			'/save-settings',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'save_settings' ],
				'permission_callback' => 'tameero_edit_available',
			]
		);
		register_rest_route(
			'tameero/v1',
			'/render-section',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'render_section' ],
				'permission_callback' => 'tameero_edit_available',
			]
		);
		TE_Categories::register_routes();
		TE_Safety::register_routes();
	}

	/**
	 * Atomic save — validate sab, phir save sab.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function save_settings( $request ) {
		$values = $request->get_param( 'values' );
		if ( ! is_array( $values ) || empty( $values ) ) {
			return new WP_Error( 'te_empty', 'Koi tabdeeli nahi bheji gayi.', [ 'status' => 400 ] );
		}

		$map     = TE_Config::controls_map();
		$clean   = [];
		$errors  = [];
		foreach ( $values as $mod => $raw ) {
			if ( ! isset( $map[ $mod ] ) ) {
				$errors[ $mod ] = 'Ye setting Edit Mode mein declared nahi hai.';
				continue;
			}
			$result = self::validate_value( $map[ $mod ], $raw );
			if ( is_wp_error( $result ) ) {
				$errors[ $mod ] = $result->get_error_message();
			} else {
				$clean[ $mod ] = $result;
			}
		}
		if ( ! empty( $errors ) ) {
			return new WP_Error(
				'te_validation',
				'Kuch settings durust nahi hain — kuch bhi save nahi hua.',
				[ 'status' => 400, 'fields' => $errors ]
			);
		}

		// Lagu karne se pehle rolling backup ka snapshot lo (Phase 4).
		TE_Safety::snapshot_before_save();
		foreach ( $clean as $mod => $value ) {
			set_theme_mod( $mod, $value );
		}

		$saved = [];
		foreach ( $clean as $mod => $value ) {
			$saved[ $mod ] = get_theme_mod( $mod, $value );
		}
		return rest_ensure_response( [ 'ok' => true, 'saved' => $saved ] );
	}

	/**
	 * Aik value ko us ke control type ke hisaab se validate + sanitize.
	 *
	 * @param array $control Control definition (manifest se).
	 * @param mixed $raw     Client se aya raw value.
	 * @return mixed|WP_Error Sanitized value ya error.
	 */
	public static function validate_value( $control, $raw ) {
		$type = $control['type'];
		switch ( $type ) {
			case 'toggle':
				if ( is_bool( $raw ) ) {
					return $raw;
				}
				if ( 1 === $raw || '1' === $raw || 'true' === $raw ) {
					return true;
				}
				if ( 0 === $raw || '0' === $raw || 'false' === $raw || '' === $raw ) {
					return false;
				}
				return new WP_Error( 'te_type', 'Toggle ke liye true/false chahiye.' );

			case 'text':
				if ( ! is_scalar( $raw ) ) {
					return new WP_Error( 'te_type', 'Text durust nahi hai.' );
				}
				$value = sanitize_text_field( (string) $raw );
				if ( strlen( $value ) > 500 ) {
					return new WP_Error( 'te_long', 'Text bohat lamba hai (hadd 500 haroof).' );
				}
				return $value;

			case 'textarea':
				if ( ! is_scalar( $raw ) ) {
					return new WP_Error( 'te_type', 'Text durust nahi hai.' );
				}
				$value = sanitize_textarea_field( (string) $raw );
				if ( strlen( $value ) > 5000 ) {
					return new WP_Error( 'te_long', 'Text bohat lamba hai (hadd 5000 haroof).' );
				}
				return $value;

			case 'url':
				if ( ! is_scalar( $raw ) ) {
					return new WP_Error( 'te_type', 'URL durust nahi hai.' );
				}
				$value = trim( (string) $raw );
				if ( '' === $value ) {
					return '';
				}
				$clean = esc_url_raw( $value );
				if ( '' === $clean ) {
					return new WP_Error( 'te_url', 'URL durust nahi hai.' );
				}
				return $clean;

			case 'color':
				$value = is_scalar( $raw ) ? sanitize_hex_color( (string) $raw ) : null;
				if ( ! $value ) {
					return new WP_Error( 'te_color', 'Rang ka hex code durust nahi hai.' );
				}
				return $value;

			case 'stepper':
				if ( ! is_numeric( $raw ) ) {
					return new WP_Error( 'te_type', 'Adad chahiye.' );
				}
				$min = isset( $control['min'] ) ? (int) $control['min'] : 1;
				$max = isset( $control['max'] ) ? (int) $control['max'] : 12;
				return max( $min, min( $max, (int) $raw ) );

			case 'category_select':
				if ( ! is_numeric( $raw ) ) {
					return new WP_Error( 'te_type', 'Category id durust nahi hai.' );
				}
				$id = (int) $raw;
				if ( 0 === $id ) {
					return 0; // 0 = koi category nahi (masalan ticker band).
				}
				$taxonomy = $control['taxonomy'] ?? 'category';
				$term     = get_term( $id, $taxonomy );
				if ( ! $term || is_wp_error( $term ) ) {
					return new WP_Error( 'te_term', 'Category mojood nahi hai.' );
				}
				return $id;

			case 'select':
			case 'layout_picker':
				$keys = [];
				foreach ( (array) ( $control['options'] ?? [] ) as $option ) {
					if ( isset( $option['key'] ) ) {
						$keys[] = (string) $option['key'];
					}
				}
				if ( ! is_scalar( $raw ) || ! in_array( (string) $raw, $keys, true ) ) {
					return new WP_Error( 'te_option', 'Ye option durust nahi hai.' );
				}
				return (string) $raw;
		}
		return new WP_Error( 'te_unknown_type', 'Control type pehchana nahi gaya.' );
	}

	/**
	 * Homepage render karke aik section ka HTML wapas karo.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function render_section( $request ) {
		$section_id = (string) $request->get_param( 'section' );
		if ( '' === $section_id || ! TE_Config::section( $section_id ) ) {
			return new WP_Error( 'te_section', 'Section pehchana nahi gaya.', [ 'status' => 404 ] );
		}

		$html = self::render_homepage_html();
		if ( is_wp_error( $html ) ) {
			return $html;
		}

		$element_id = 'tameero-section-' . $section_id;
		$doc        = new DOMDocument();
		$prev       = libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );

		$node = $doc->getElementById( $element_id );
		if ( ! $node ) {
			return new WP_Error( 'te_not_found', 'Section page par nahi mila (ho sakta hai wo ab band ho).', [ 'status' => 404 ] );
		}
		return rest_ensure_response( [ 'ok' => true, 'html' => $doc->saveHTML( $node ) ] );
	}

	/**
	 * Theme ke homepage template ko output buffer mein render karo.
	 * Main query ko aarzi tor par home query se badal kar asal globals
	 * wapas rakh diye jate hain — REST request ka baqi hissa mutasir nahi hota.
	 *
	 * @return string|WP_Error
	 */
	private static function render_homepage_html() {
		global $wp_query, $wp_the_query, $post;

		$orig_query     = $wp_query;
		$orig_the_query = $wp_the_query;
		$orig_post      = $post;

		$home_query          = new WP_Query( [ 'post_type' => 'post', 'post_status' => 'publish' ] );
		$wp_query            = $home_query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- aarzi, neeche restore hota hai
		$wp_the_query        = $home_query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_query'] = $home_query;

		$level = ob_get_level();
		ob_start();
		try {
			include get_stylesheet_directory() . '/index.php';
			$html = ob_get_clean();
		} catch ( Throwable $e ) {
			while ( ob_get_level() > $level ) {
				ob_end_clean();
			}
			$wp_query            = $orig_query;
			$wp_the_query        = $orig_the_query;
			$GLOBALS['wp_query'] = $orig_query;
			$post                = $orig_post;
			return new WP_Error( 'te_render', 'Homepage render nahi ho saka: ' . $e->getMessage(), [ 'status' => 500 ] );
		}

		$wp_query            = $orig_query;
		$wp_the_query        = $orig_the_query;
		$GLOBALS['wp_query'] = $orig_query;
		$post                = $orig_post;

		if ( false === $html || '' === trim( (string) $html ) ) {
			return new WP_Error( 'te_render', 'Homepage render khaali aya.', [ 'status' => 500 ] );
		}
		return $html;
	}
}
