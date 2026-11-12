<?php
/**
 * TE_Config — theme ki Edit Mode declaration load karna.
 *
 * Pehla source: active theme ke root mein `tameero-edit-config.json`.
 * Us ke baad `tameero_edit_config` filter ko mauqa milta hai ke wo
 * config ko badal/barha sakay (spec topic 5: JSON-first + PHP filter).
 *
 * Config ki shakal:
 * {
 *   "version": "1.0.0",
 *   "theme": "urdu-news",
 *   "global":  { "title": "عمومی ترتیبات", "controls": [ ... ] },
 *   "sections": [
 *     { "id": "ticker", "title": "بریکنگ نیوز", "controls": [ ... ] }
 *   ]
 * }
 * Har control: { id, theme_mod, type, label, default, ...type-specific }
 * Types: toggle, text, textarea, url, color, stepper, category_select,
 *        select, layout_picker (select ki tarah validate hota hai).
 *
 * Storage (v1.1, Route A — FSE themes ke liye):
 * Har control ki values kis jagah rehti hain, `storage` field batata hai:
 *  - "theme_mod" (default): theme_mod — key = control.theme_mod.
 *  - "option": WP option — key = "opt:" . control.option
 *    (masalan Dukaan ka dukaan_whatsapp_number).
 *  - "global_style": FSE Global Styles ki palette entry — key =
 *    "gs:" . control.gs (palette slug; masalan "gold"). Rang theme.json
 *    ki asal palette se merge hokar user global styles mein likha jata hai.
 *
 * @package TameeroEditMode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TE_Config {

	/** @var array|null */
	private static $config = null;

	/** @var bool */
	private static $loaded = false;

	/**
	 * Config load karo (aik dafa, cache ke sath). Theme ke pas
	 * declaration na ho to null — plugin hibernate ho jata hai.
	 *
	 * @return array|null
	 */
	public static function load() {
		if ( self::$loaded ) {
			return self::$config;
		}
		self::$loaded = true;

		$config = null;
		$file   = get_stylesheet_directory() . '/tameero-edit-config.json';
		if ( file_exists( $file ) ) {
			$raw = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( false !== $raw ) {
				$decoded = json_decode( $raw, true );
				if ( is_array( $decoded ) ) {
					$config = $decoded;
				}
			}
		}

		/**
		 * Edit config filter — theme/plugin code JSON ko modify kar sakta hai.
		 *
		 * @param array|null $config JSON se aya hua config (ya null).
		 */
		$config = apply_filters( 'tameero_edit_config', $config );

		if ( ! is_array( $config ) || ( empty( $config['sections'] ) && empty( $config['global'] ) ) ) {
			self::$config = null;
			return null;
		}

		if ( empty( $config['sections'] ) || ! is_array( $config['sections'] ) ) {
			$config['sections'] = [];
		}
		self::$config = $config;
		return self::$config;
	}

	/**
	 * Sections ki list.
	 *
	 * @return array
	 */
	public static function sections() {
		$config = self::load();
		return $config ? $config['sections'] : [];
	}

	/**
	 * Global (site-level) controls wala hissa, ho to.
	 *
	 * @return array|null
	 */
	public static function global_block() {
		$config = self::load();
		if ( $config && ! empty( $config['global'] ) && is_array( $config['global'] ) ) {
			return $config['global'];
		}
		return null;
	}

	/**
	 * Section id se section dhundo.
	 *
	 * @param string $id Section id (data-tameero-section ki value;
	 *                   category sections ke liye "category-<slug>").
	 * @return array|null
	 */
	public static function section( $id ) {
		foreach ( self::sections() as $section ) {
			if ( isset( $section['id'] ) && $section['id'] === $id ) {
				return $section;
			}
			// Category pattern: manifest mein "category" section ho to
			// "category-<slug>" wali har asal section usi se map hoti hai.
			if ( isset( $section['id'] ) && 'category' === $section['id'] && 0 === strpos( $id, 'category-' ) ) {
				return $section;
			}
		}
		return null;
	}

	/**
	 * Aik control ki save/read key. Theme_mod controls ke liye key =
	 * theme_mod ka naam (purana bartao); option controls ke liye
	 * "opt:<option-naam>"; global_style controls ke liye "gs:<slug>".
	 *
	 * @param array $control Control definition.
	 * @return string|null Key — control adhoora ho to null.
	 */
	public static function control_key( $control ) {
		$storage = $control['storage'] ?? 'theme_mod';
		if ( 'option' === $storage ) {
			return ! empty( $control['option'] ) ? 'opt:' . $control['option'] : null;
		}
		if ( 'global_style' === $storage ) {
			return ! empty( $control['gs'] ) ? 'gs:' . $control['gs'] : null;
		}
		return ! empty( $control['theme_mod'] ) ? (string) $control['theme_mod'] : null;
	}

	/**
	 * Categories manager kis taxonomy par chale — config ka top-level
	 * `categories_taxonomy` (default "category"; Dukaan par "product_cat").
	 *
	 * @return string
	 */
	public static function categories_taxonomy() {
		$config = self::load();
		$tax    = $config['categories_taxonomy'] ?? 'category';
		return is_string( $tax ) && '' !== $tax ? $tax : 'category';
	}

	/**
	 * Tamam controls ka map: key => control definition.
	 * Save endpoint ki validation is par chalti hai — jo key is map
	 * mein NAHI, wo kabhi save nahi ho sakti (allowlist).
	 *
	 * @return array
	 */
	public static function controls_map() {
		$map    = [];
		$config = self::load();
		if ( ! $config ) {
			return $map;
		}
		$blocks = $config['sections'];
		$global = self::global_block();
		if ( $global && ! empty( $global['controls'] ) ) {
			$blocks[] = $global;
		}
		foreach ( $blocks as $block ) {
			foreach ( (array) ( $block['controls'] ?? [] ) as $control ) {
				$key = self::control_key( $control );
				if ( null !== $key && ! empty( $control['type'] ) ) {
					$map[ $key ] = $control;
				}
			}
		}
		return $map;
	}

	/**
	 * Har declared control ki current qeemat (us ke storage se).
	 *
	 * @return array key => value
	 */
	public static function current_values() {
		$values = [];
		foreach ( self::controls_map() as $key => $control ) {
			$default         = $control['default'] ?? null;
			$values[ $key ]  = self::read_value( $control, $default );
		}
		return $values;
	}

	/**
	 * Aik control ki current qeemat parho (storage ke hisaab se).
	 *
	 * @param array $control Control definition.
	 * @param mixed $default Na mile to ye.
	 * @return mixed
	 */
	public static function read_value( $control, $default = null ) {
		$storage = $control['storage'] ?? 'theme_mod';
		if ( 'option' === $storage ) {
			return get_option( $control['option'], $default );
		}
		if ( 'global_style' === $storage ) {
			$color = self::global_style_color( (string) $control['gs'] );
			return null !== $color ? $color : $default;
		}
		return get_theme_mod( $control['theme_mod'], $default );
	}

	/* ---------------- Global Styles helpers ---------------- */

	/**
	 * Merged (theme + user) palette mein aik slug ka mojooda rang.
	 *
	 * @param string $slug Palette slug.
	 * @return string|null Rang ya null (slug palette mein nahi).
	 */
	public static function global_style_color( $slug ) {
		if ( ! class_exists( 'WP_Theme_JSON_Resolver' ) ) {
			return null;
		}
		$settings = WP_Theme_JSON_Resolver::get_merged_data()->get_settings();
		foreach ( (array) ( $settings['color']['palette']['theme'] ?? [] ) as $entry ) {
			if ( isset( $entry['slug'] ) && $entry['slug'] === $slug && isset( $entry['color'] ) ) {
				return (string) $entry['color'];
			}
		}
		return null;
	}

	/**
	 * User global styles post ka decoded data (na ho to khaali array).
	 *
	 * @return array
	 */
	public static function user_global_styles_data() {
		if ( ! class_exists( 'WP_Theme_JSON_Resolver' ) ) {
			return [];
		}
		$post_id = WP_Theme_JSON_Resolver::get_user_global_styles_post_id();
		if ( ! $post_id ) {
			return [];
		}
		$post = get_post( $post_id );
		if ( ! $post ) {
			return [];
		}
		$data = json_decode( (string) $post->post_content, true );
		return is_array( $data ) ? $data : [];
	}

	/**
	 * User global styles post ka data likho (poora content badal kar).
	 *
	 * @param array $data Theme.json shakal ka data.
	 * @return bool Kamyab hua ya nahi.
	 */
	public static function write_global_styles_data( $data ) {
		if ( ! class_exists( 'WP_Theme_JSON_Resolver' ) ) {
			return false;
		}
		$post_id = WP_Theme_JSON_Resolver::get_user_global_styles_post_id();
		if ( ! $post_id ) {
			return false;
		}
		$result = wp_update_post(
			[
				'ID'           => $post_id,
				'post_content' => wp_slash( wp_json_encode( $data ) ),
			],
			true
		);
		if ( is_wp_error( $result ) ) {
			return false;
		}
		WP_Theme_JSON_Resolver::clean_cached_data();
		return true;
	}

	/**
	 * Global styles: kai palette rang aik sath likho (slug => color).
	 * Maujooda user data parh kar sirf palette entries merge hoti hain —
	 * baqi user settings (fonts waghera) waisi ki waisi rehti hain.
	 *
	 * @param array $slug_colors slug => hex color.
	 * @return bool
	 */
	public static function apply_global_style_colors( $slug_colors ) {
		if ( empty( $slug_colors ) ) {
			return true;
		}
		$data    = self::user_global_styles_data();
		$palette = $data['settings']['color']['palette']['theme'] ?? [];
		if ( ! is_array( $palette ) ) {
			$palette = [];
		}
		foreach ( $slug_colors as $slug => $color ) {
			$found = false;
			foreach ( $palette as $i => $entry ) {
				if ( isset( $entry['slug'] ) && $entry['slug'] === $slug ) {
					$palette[ $i ]['color'] = $color;
					$found                  = true;
					break;
				}
			}
			if ( ! $found ) {
				$palette[] = [ 'slug' => $slug, 'color' => $color, 'name' => $slug ];
			}
		}
		$data['settings']['color']['palette']['theme'] = $palette;
		if ( empty( $data['version'] ) ) {
			$data['version'] = 3;
		}
		return self::write_global_styles_data( $data );
	}
}
