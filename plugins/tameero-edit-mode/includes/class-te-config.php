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
	 * Tamam controls ka map: theme_mod => control definition.
	 * Save endpoint ki validation is par chalti hai — jo mod is map
	 * mein NAHI, wo kabhi save nahi ho sakta (allowlist).
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
				if ( ! empty( $control['theme_mod'] ) && ! empty( $control['type'] ) ) {
					$map[ $control['theme_mod'] ] = $control;
				}
			}
		}
		return $map;
	}

	/**
	 * Har declared mod ki current qeemat (theme_mod, warna default).
	 *
	 * @return array theme_mod => value
	 */
	public static function current_values() {
		$values = [];
		foreach ( self::controls_map() as $mod => $control ) {
			$default        = $control['default'] ?? null;
			$values[ $mod ] = get_theme_mod( $mod, $default );
		}
		return $values;
	}
}
