<?php
/**
 * Dukaan — Customizer: "Dukaan Theme" section.
 *
 * WhatsApp number, announcement bar, primary color, hero aur footer note.
 *
 * @package Dukaan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Phone number sanitize: sirf digits rakho (wa.me ko yahi chahiye).
 */
function dukaan_sanitize_phone( $value ) {
	return preg_replace( '/[^0-9]/', '', (string) $value );
}

/**
 * Checkbox sanitize.
 */
function dukaan_sanitize_checkbox( $value ) {
	return (bool) $value;
}

function dukaan_customize_register( $wp_customize ) {
	$wp_customize->add_section( 'dukaan_theme', array(
		'title'       => __( 'Dukaan Theme', 'dukaan' ),
		'description' => __( 'Dukaan ki khaas settings — WhatsApp number, offer wali patti, rang aur hero.', 'dukaan' ),
		'priority'    => 30,
	) );

	// --- WhatsApp number ---
	$wp_customize->add_setting( 'dukaan_whatsapp_number', array(
		'default'           => '',
		'sanitize_callback' => 'dukaan_sanitize_phone',
		'transport'         => 'refresh',
	) );
	$wp_customize->add_control( 'dukaan_whatsapp_number', array(
		'label'       => __( 'WhatsApp Number', 'dukaan' ),
		'description' => __( 'Country code ke sath, + ya 0 ke baghair — misalan 923001234567. Ye number product page ke "WhatsApp Order" button mein istemal hota hai. Khali chhor dein to button nahi dikhega.', 'dukaan' ),
		'section'     => 'dukaan_theme',
		'type'        => 'text',
		'input_attrs' => array( 'placeholder' => '923001234567' ),
	) );

	// --- Announcement bar on/off ---
	$wp_customize->add_setting( 'dukaan_announcement_on', array(
		'default'           => true,
		'sanitize_callback' => 'dukaan_sanitize_checkbox',
		'transport'         => 'refresh',
	) );
	$wp_customize->add_control( 'dukaan_announcement_on', array(
		'label'   => __( 'Announcement Bar On Karein', 'dukaan' ),
		'section' => 'dukaan_theme',
		'type'    => 'checkbox',
	) );

	// --- Announcement text ---
	$wp_customize->add_setting( 'dukaan_announcement_text', array(
		'default'           => __( 'Delivery pure Pakistan mein — advance payment par order confirm hota hai.', 'dukaan' ),
		'sanitize_callback' => 'sanitize_text_field',
		'transport'         => 'refresh',
	) );
	$wp_customize->add_control( 'dukaan_announcement_text', array(
		'label'   => __( 'Announcement Text', 'dukaan' ),
		'section' => 'dukaan_theme',
		'type'    => 'text',
	) );

	// --- Primary color ---
	$wp_customize->add_setting( 'dukaan_primary_color', array(
		'default'           => '#7b1e2b',
		'sanitize_callback' => 'sanitize_hex_color',
		'transport'         => 'refresh',
	) );
	$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'dukaan_primary_color', array(
		'label'       => __( 'Primary Color', 'dukaan' ),
		'description' => __( 'Header, buttons aur links ka rang (default maroon #7b1e2b).', 'dukaan' ),
		'section'     => 'dukaan_theme',
	) ) );

	// --- Hero heading ---
	$wp_customize->add_setting( 'dukaan_hero_heading', array(
		'default'           => __( 'Khush Aamdeed — Hamari Dukaan', 'dukaan' ),
		'sanitize_callback' => 'sanitize_text_field',
		'transport'         => 'refresh',
	) );
	$wp_customize->add_control( 'dukaan_hero_heading', array(
		'label'   => __( 'Hero Heading (Home Page)', 'dukaan' ),
		'section' => 'dukaan_theme',
		'type'    => 'text',
	) );

	// --- Hero text ---
	$wp_customize->add_setting( 'dukaan_hero_text', array(
		'default'           => __( 'Behtareen quality bedsheets aur kapray — WhatsApp par order karein, delivery pure Pakistan mein.', 'dukaan' ),
		'sanitize_callback' => 'sanitize_textarea_field',
		'transport'         => 'refresh',
	) );
	$wp_customize->add_control( 'dukaan_hero_text', array(
		'label'   => __( 'Hero Text (Home Page)', 'dukaan' ),
		'section' => 'dukaan_theme',
		'type'    => 'textarea',
	) );

	// --- Footer note ---
	$wp_customize->add_setting( 'dukaan_footer_note', array(
		'default'           => '',
		'sanitize_callback' => 'sanitize_text_field',
		'transport'         => 'refresh',
	) );
	$wp_customize->add_control( 'dukaan_footer_note', array(
		'label'       => __( 'Footer Note', 'dukaan' ),
		'description' => __( 'Footer ke neeche nazar ane wali line — misalan shop ka address ya timing.', 'dukaan' ),
		'section'     => 'dukaan_theme',
		'type'        => 'text',
	) );
}
add_action( 'customize_register', 'dukaan_customize_register' );
