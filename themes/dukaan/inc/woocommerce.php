<?php
/**
 * Dukaan — WooCommerce integration.
 *
 * HAR cheez class_exists( 'WooCommerce' ) guard ke sath hai — theme
 * baghair WooCommerce ke bhi salamti se chalta hai.
 *
 * @package Dukaan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooCommerce' ) ) {
	return;
}

/**
 * WooCommerce theme support + product gallery features.
 */
function dukaan_woocommerce_setup() {
	add_theme_support( 'woocommerce', array(
		'thumbnail_image_width'         => 600,
		'single_image_width'            => 800,
		'product_grid'                  => array(
			'default_rows'    => 3,
			'default_columns' => 3,
		),
	) );
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );
}
add_action( 'after_setup_theme', 'dukaan_woocommerce_setup' );

/**
 * Apne wrappers — default Woo wrapper hata kar container wala.
 */
remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );

function dukaan_wrapper_start() {
	echo '<main id="primary" class="dukaan-main dukaan-shop-main"><div class="dukaan-container">';
}
add_action( 'woocommerce_before_main_content', 'dukaan_wrapper_start', 10 );

function dukaan_wrapper_end() {
	echo '</div></main>';
}
add_action( 'woocommerce_after_main_content', 'dukaan_wrapper_end', 10 );

/**
 * Shop columns / per-page.
 */
function dukaan_loop_columns() {
	return 3;
}
add_filter( 'loop_shop_columns', 'dukaan_loop_columns' );

function dukaan_loop_per_page() {
	return 12;
}
add_filter( 'loop_shop_per_page', 'dukaan_loop_per_page', 20 );

/**
 * Sale badge par % off — "Sale!" ki jagah asal bachat.
 */
function dukaan_sale_flash( $html, $post, $product ) {
	if ( ! $product ) {
		return $html;
	}

	$percent = 0;
	if ( $product->is_type( 'simple' ) || $product->is_type( 'external' ) ) {
		$regular = (float) $product->get_regular_price();
		$sale    = (float) $product->get_sale_price();
		if ( $regular > 0 && $sale >= 0 && $sale < $regular ) {
			$percent = (int) round( ( ( $regular - $sale ) / $regular ) * 100 );
		}
	} elseif ( $product->is_type( 'variable' ) ) {
		foreach ( $product->get_children() as $child_id ) {
			$child   = wc_get_product( $child_id );
			if ( ! $child ) {
				continue;
			}
			$regular = (float) $child->get_regular_price();
			$sale    = (float) $child->get_sale_price();
			if ( $regular > 0 && $sale < $regular ) {
				$p       = (int) round( ( ( $regular - $sale ) / $regular ) * 100 );
				$percent = max( $percent, $p );
			}
		}
	}

	if ( $percent > 0 ) {
		return '<span class="onsale">-' . $percent . '%</span>';
	}
	return '<span class="onsale">' . esc_html__( 'Sale!', 'dukaan' ) . '</span>';
}
add_filter( 'woocommerce_sale_flash', 'dukaan_sale_flash', 10, 3 );

/**
 * SAB SE KHAAS FEATURE — single product page par Add to Cart ke
 * baad WhatsApp Order button. Number khali ho to kuch nahi dikhta.
 */
function dukaan_whatsapp_order_button() {
	global $product;

	if ( ! $product ) {
		return;
	}

	$link = dukaan_whatsapp_link( $product );
	if ( '' === $link ) {
		return;
	}

	printf(
		'<a class="dukaan-whatsapp-btn" href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
		esc_url( $link ),
		esc_html__( 'WhatsApp par Order Karein', 'dukaan' )
	);
}
// Add to cart form (summary, priority 30) ke fori baad.
add_action( 'woocommerce_single_product_summary', 'dukaan_whatsapp_order_button', 35 );

/**
 * Header cart count — AJAX add-to-cart par khud refresh ho.
 *
 * @param array $fragments WooCommerce fragments.
 * @return array
 */
function dukaan_cart_count_fragment( $fragments ) {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return $fragments;
	}
	$count = WC()->cart->get_cart_contents_count();
	ob_start();
	?>
	<span class="dukaan-cart-count"><?php echo esc_html( $count ); ?></span>
	<?php
	$fragments['.dukaan-cart-count'] = ob_get_clean();
	return $fragments;
}
add_filter( 'woocommerce_add_to_cart_fragments', 'dukaan_cart_count_fragment' );

/**
 * Product card mein bhi WhatsApp ka chhota link? Nahi — card saaf
 * rakha gaya hai; WhatsApp button sirf single product page par hai
 * (wahan customer order ke mood mein hota hai).
 */
