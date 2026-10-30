<?php
/**
 * Dukaan additions — fork layer on top of Purple (Automattic, GPL).
 *
 * 1. "Dukaan" pattern category.
 * 2. Noto Nastaliq Urdu font (Google Fonts) enqueue — theme.json sirf
 *    family declare karta hai, asal font yahan se load hota hai.
 * 3. WhatsApp Order dynamic block (dukaan/whatsapp-order) — single
 *    product page par Add to Cart ke paas render hota hai.
 * 4. WhatsApp number ki EK config jagah: Settings → Dukaan
 *    (option: dukaan_whatsapp_number). Khali ho to button gayab.
 *
 * @package dukaan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ------------------------------------------------------------------ */
/* Pattern category                                                    */
/* ------------------------------------------------------------------ */
function dukaan_register_pattern_category() {
	register_block_pattern_category(
		'dukaan',
		array( 'label' => __( 'Dukaan', 'dukaan' ) )
	);
}
add_action( 'init', 'dukaan_register_pattern_category' );

/* ------------------------------------------------------------------ */
/* Urdu font                                                           */
/* ------------------------------------------------------------------ */
function dukaan_enqueue_fonts() {
	wp_enqueue_style(
		'dukaan-nastaliq',
		'https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu:wght@400;600;700&display=swap',
		array(),
		null
	);
}
add_action( 'wp_enqueue_scripts', 'dukaan_enqueue_fonts' );
add_action( 'enqueue_block_editor_assets', 'dukaan_enqueue_fonts' );

/* ------------------------------------------------------------------ */
/* WhatsApp number — single config point                               */
/* ------------------------------------------------------------------ */
function dukaan_get_whatsapp_number() {
	$raw = (string) get_option( 'dukaan_whatsapp_number', '' );
	$num = preg_replace( '/[^0-9]/', '', $raw );
	/**
	 * Developers: code se number badalna ho to ye filter use karein.
	 *
	 * @param string $num Digits-only number (country code ke sath).
	 */
	return apply_filters( 'dukaan_whatsapp_number', $num );
}

function dukaan_sanitize_whatsapp_number( $value ) {
	return preg_replace( '/[^0-9]/', '', (string) $value );
}

function dukaan_register_settings() {
	register_setting( 'dukaan_settings', 'dukaan_whatsapp_number', array(
		'type'              => 'string',
		'sanitize_callback' => 'dukaan_sanitize_whatsapp_number',
		'default'           => '',
	) );
	add_settings_section( 'dukaan_main', __( 'WhatsApp Orders', 'dukaan' ), '__return_false', 'dukaan' );
	add_settings_field(
		'dukaan_whatsapp_number',
		__( 'WhatsApp Number', 'dukaan' ),
		'dukaan_whatsapp_field_html',
		'dukaan',
		'dukaan_main'
	);
}
add_action( 'admin_init', 'dukaan_register_settings' );

function dukaan_whatsapp_field_html() {
	$value = esc_attr( (string) get_option( 'dukaan_whatsapp_number', '' ) );
	echo '<input type="text" name="dukaan_whatsapp_number" value="' . $value . '" class="regular-text" placeholder="923001234567">';
	echo '<p class="description">' . esc_html__( 'Country code ke sath, + ya 0 ke baghair (misalan 923001234567). Product page ke WhatsApp Order button ke liye. Khali chhor dein to button nahi dikhega.', 'dukaan' ) . '</p>';
}

function dukaan_settings_menu() {
	add_options_page(
		__( 'Dukaan', 'dukaan' ),
		__( 'Dukaan', 'dukaan' ),
		'manage_options',
		'dukaan',
		'dukaan_settings_page_html'
	);
}
add_action( 'admin_menu', 'dukaan_settings_menu' );

function dukaan_settings_page_html() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Dukaan Settings', 'dukaan' ); ?></h1>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'dukaan_settings' );
			do_settings_sections( 'dukaan' );
			submit_button();
			?>
		</form>
	</div>
	<?php
}

/* ------------------------------------------------------------------ */
/* WhatsApp Order block (dynamic, server-rendered)                     */
/* ------------------------------------------------------------------ */
function dukaan_render_whatsapp_block( $attributes, $content, $block ) {
	// Sirf asal product context mein render ho.
	if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_product' ) ) {
		return '';
	}
	$post_id = get_the_ID();
	if ( ! $post_id || 'product' !== get_post_type( $post_id ) ) {
		return '';
	}
	$product = wc_get_product( $post_id );
	if ( ! $product ) {
		return '';
	}

	$number = dukaan_get_whatsapp_number();
	if ( '' === $number ) {
		return '';
	}

	$price_text = html_entity_decode( wp_strip_all_tags( wc_price( $product->get_price() ) ), ENT_QUOTES, 'UTF-8' );
	$price_text = trim( str_replace( "\xc2\xa0", ' ', $price_text ) );
	$message = sprintf(
		/* translators: 1: product name, 2: price, 3: product URL */
		__( 'Assalam-o-Alaikum! Mujhe ye product order karna hai: %1$s — %2$s — %3$s', 'dukaan' ),
		$product->get_name(),
		$price_text,
		get_permalink( $post_id )
	);
	$url = 'https://wa.me/' . $number . '?text=' . rawurlencode( $message );

	$wrapper = get_block_wrapper_attributes( array( 'class' => 'dukaan-whatsapp-wrap' ) );
	return '<div ' . $wrapper . '><a class="dukaan-whatsapp-btn" href="' . esc_url( $url )
		. '" target="_blank" rel="noopener noreferrer">'
		. esc_html__( 'WhatsApp par Order Karein', 'dukaan' ) . '</a></div>';
}

function dukaan_register_whatsapp_block() {
	wp_register_script(
		'dukaan-whatsapp-block-editor',
		get_template_directory_uri() . '/assets/js/whatsapp-block.js',
		array( 'wp-blocks', 'wp-element', 'wp-server-side-render', 'wp-block-editor' ),
		wp_get_theme()->get( 'Version' ),
		true
	);

	register_block_type( 'dukaan/whatsapp-order', array(
		'editor_script'   => 'dukaan-whatsapp-block-editor',
		'render_callback' => 'dukaan_render_whatsapp_block',
		'attributes'      => array(),
	) );
}
add_action( 'init', 'dukaan_register_whatsapp_block' );
