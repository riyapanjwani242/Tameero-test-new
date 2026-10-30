<?php
/**
 * Dukaan — theme setup, assets, menus and widget areas.
 *
 * Urdu-first RTL WooCommerce shop theme for Pakistani sellers.
 *
 * @package Dukaan
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DUKAAN_VERSION', '1.0.0' );

/**
 * Theme support, text domain, menus.
 */
function dukaan_setup() {
	load_theme_textdomain( 'dukaan', get_template_directory() . '/languages' );

	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'custom-logo', array(
		'height'      => 120,
		'width'       => 320,
		'flex-height' => true,
		'flex-width'  => true,
	) );
	add_theme_support( 'html5', array(
		'search-form',
		'comment-form',
		'comment-list',
		'gallery',
		'caption',
		'style',
		'script',
	) );
	add_theme_support( 'customize-selective-refresh-widgets' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );

	register_nav_menus( array(
		'primary' => esc_html__( 'Primary Menu', 'dukaan' ),
		'footer'  => esc_html__( 'Footer Menu', 'dukaan' ),
	) );
}
add_action( 'after_setup_theme', 'dukaan_setup' );

/**
 * Content width.
 */
function dukaan_content_width() {
	$GLOBALS['content_width'] = apply_filters( 'dukaan_content_width', 1200 );
}
add_action( 'after_setup_theme', 'dukaan_content_width', 0 );

/**
 * Widget areas: shop sidebar + 3 footer columns.
 */
function dukaan_widgets_init() {
	register_sidebar( array(
		'name'          => esc_html__( 'Shop Sidebar', 'dukaan' ),
		'id'            => 'sidebar-1',
		'description'   => esc_html__( 'Shop aur product pages ke side mein nazar ata hai.', 'dukaan' ),
		'before_widget' => '<section id="%1$s" class="widget %2$s">',
		'after_widget'  => '</section>',
		'before_title'  => '<h3 class="widget-title">',
		'after_title'   => '</h3>',
	) );

	foreach ( array( 1, 2, 3 ) as $i ) {
		register_sidebar( array(
			'name'          => sprintf( esc_html__( 'Footer Column %d', 'dukaan' ), $i ),
			'id'            => 'footer-' . $i,
			'description'   => esc_html__( 'Footer ki columns mein nazar ata hai.', 'dukaan' ),
			'before_widget' => '<section id="%1$s" class="widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h3 class="widget-title">',
			'after_title'   => '</h3>',
		) );
	}
}
add_action( 'widgets_init', 'dukaan_widgets_init' );

/**
 * Fonts + styles + scripts.
 */
function dukaan_enqueue_assets() {
	$version = DUKAAN_VERSION;

	// Urdu font — Nastaliq ko zyada line-height chahiye hoti hai
	// (assets/css/dukaan.css mein body line-height 2 set hai).
	wp_enqueue_style(
		'dukaan-fonts',
		'https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu:wght@400;600;700&display=swap',
		array(),
		null
	);

	wp_enqueue_style( 'dukaan-style', get_stylesheet_uri(), array(), $version );

	wp_enqueue_style(
		'dukaan-main',
		get_template_directory_uri() . '/assets/css/dukaan.css',
		array( 'dukaan-style' ),
		$version
	);

	// Customizer ki primary color — CSS variable ke tor par.
	$primary = dukaan_primary_color();
	if ( '#7b1e2b' !== $primary ) {
		wp_add_inline_style(
			'dukaan-main',
			':root { --dukaan-primary: ' . esc_attr( $primary ) . '; ' .
			'--dukaan-primary-dark: ' . esc_attr( $primary ) . '; }'
		);
	}

	wp_enqueue_script(
		'dukaan-main',
		get_template_directory_uri() . '/assets/js/dukaan.js',
		array(),
		$version,
		true
	);

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'dukaan_enqueue_assets' );

/**
 * Customizer se primary color (default maroon).
 */
function dukaan_primary_color() {
	$color = get_theme_mod( 'dukaan_primary_color', '#7b1e2b' );
	$color = sanitize_hex_color( $color );
	return $color ? $color : '#7b1e2b';
}

/**
 * Customizer se WhatsApp number — sirf digits (country code ke sath),
 * misalan 923001234567. Khali ho to khali string.
 */
function dukaan_whatsapp_number() {
	$raw = (string) get_theme_mod( 'dukaan_whatsapp_number', '' );
	return preg_replace( '/[^0-9]/', '', $raw );
}

/**
 * WhatsApp order link kisi product ke liye — message pehle se likha hua.
 *
 * @param WC_Product|null $product Product object (guard caller ki zimmedari).
 * @return string Khali agar number set na ho.
 */
function dukaan_whatsapp_link( $product = null ) {
	$number = dukaan_whatsapp_number();
	if ( '' === $number ) {
		return '';
	}

	if ( $product ) {
		$price = wp_strip_all_tags( $product->get_price_html() );
		$text  = sprintf(
			/* translators: 1: product name, 2: price, 3: product URL */
			__( 'Assalam-o-Alaikum! Mujhe ye product order karna hai: %1$s — %2$s — %3$s', 'dukaan' ),
			$product->get_name(),
			$price,
			get_permalink( $product->get_id() )
		);
	} else {
		$text = __( 'Assalam-o-Alaikum! Mujhe order karna hai.', 'dukaan' );
	}

	return 'https://wa.me/' . $number . '?text=' . rawurlencode( $text );
}

require get_template_directory() . '/inc/customizer.php';
require get_template_directory() . '/inc/woocommerce.php';
