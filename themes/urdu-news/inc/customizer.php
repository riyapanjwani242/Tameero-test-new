<?php
/**
 * Urdu News Theme Customizer
 *
 * @package Urdu_News
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Register Theme Customizer settings, controls, sections, and panel.
 *
 * @param WP_Customize_Manager $wp_customize Theme Customizer object.
 */
function urdu_news_customize_register( $wp_customize ) {

    // -------------------------------------------------------------------------
    // Main Panel: Urdu News Theme Settings
    // -------------------------------------------------------------------------
    $wp_customize->add_panel(
        'urdu_news_theme_panel',
        array(
            'title'       => esc_html__( 'اردو نیوز ترتیبات', 'urdu-news' ),
            'description' => esc_html__( 'اردو نیوز تھیم کے رنگ، سیکشنز، ٹکر، لائیو ٹی وی، اور اشتہارات کی ترتیبات۔', 'urdu-news' ),
            'priority'    => 30,
        )
    );

    // =========================================================================
    // Section 1: Colors & Theme (رنگ اور ڈیزائن)
    // =========================================================================
    $wp_customize->add_section(
        'urdu_news_colors_section',
        array(
            'title'       => esc_html__( 'رنگ اور ڈیزائن (Colors & Design)', 'urdu-news' ),
            'description' => esc_html__( 'تھیم کے بنیادی اور ثانوی رنگ تبدیل کریں۔ بریکنگ نیوز اور لائیو سگنل بنیادی رنگ کے تابع ہیں۔', 'urdu-news' ),
            'panel'       => 'urdu_news_theme_panel',
            'priority'    => 10,
        )
    );

    // Primary Color (defaults to #C8102E for breaking/live elements)
    $wp_customize->add_setting(
        'urdu_news_primary_color',
        array(
            'default'           => '#C8102E',
            'sanitize_callback' => 'sanitize_hex_color',
            'transport'         => 'refresh',
        )
    );
    $wp_customize->add_control(
        new WP_Customize_Color_Control(
            $wp_customize,
            'urdu_news_primary_color',
            array(
                'label'       => esc_html__( 'بنیادی رنگ / بریکنگ نیوز (Primary Color)', 'urdu-news' ),
                'description' => esc_html__( 'بریکنگ نیوز، لائیو سگنل اور اہم بٹنز کا بنیادی رنگ (ڈیفالٹ: #C8102E)', 'urdu-news' ),
                'section'     => 'urdu_news_colors_section',
                'settings'    => 'urdu_news_primary_color',
            )
        )
    );

    // Accent Color (defaults to #22303f for dark slate bars & headings)
    $wp_customize->add_setting(
        'urdu_news_accent_color',
        array(
            'default'           => '#22303f',
            'sanitize_callback' => 'sanitize_hex_color',
            'transport'         => 'refresh',
        )
    );
    $wp_customize->add_control(
        new WP_Customize_Color_Control(
            $wp_customize,
            'urdu_news_accent_color',
            array(
                'label'       => esc_html__( 'ثانوی رنگ (Accent / Dark Slate Color)', 'urdu-news' ),
                'description' => esc_html__( 'ٹاپ بار، ہیڈر لنکس اور ایکسنٹ بارز کا ثانوی رنگ (ڈیفالٹ: #22303f)', 'urdu-news' ),
                'section'     => 'urdu_news_colors_section',
                'settings'    => 'urdu_news_accent_color',
            )
        )
    );

    // =========================================================================
    // Section 2: Breaking Ticker (بریکنگ نیوز پٹی)
    // =========================================================================
    $wp_customize->add_section(
        'urdu_news_ticker_section',
        array(
            'title'       => esc_html__( 'بریکنگ نیوز پٹی (Breaking Ticker)', 'urdu-news' ),
            'description' => esc_html__( 'بریکنگ نیوز پٹی کی فعال حالت، شعبہ اور رفتار کی ترتیبات۔', 'urdu-news' ),
            'panel'       => 'urdu_news_theme_panel',
            'priority'    => 20,
        )
    );

    // Ticker On/Off Toggle
    $wp_customize->add_setting(
        'urdu_news_enable_ticker',
        array(
            'default'           => true,
            'sanitize_callback' => 'urdu_news_sanitize_checkbox',
            'transport'         => 'refresh',
        )
    );
    $wp_customize->add_control(
        'urdu_news_enable_ticker',
        array(
            'label'       => esc_html__( 'بریکنگ نیوز پٹی فعال کریں (Enable Ticker)', 'urdu-news' ),
            'section'     => 'urdu_news_ticker_section',
            'type'        => 'checkbox',
        )
    );

    // Ticker Category Dropdown
    $categories_choices = array(
        0 => esc_html__( 'تمام زمرہ جات / تازہ ترین نمونہ سرخیاں (All Categories / Sample)', 'urdu-news' ),
    );
    if ( function_exists( 'get_categories' ) ) {
        $cats = get_categories( array( 'hide_empty' => false ) );
        if ( ! empty( $cats ) && ! is_wp_error( $cats ) ) {
            foreach ( $cats as $cat ) {
                $categories_choices[ $cat->term_id ] = $cat->name;
            }
        }
    }

    $wp_customize->add_setting(
        'urdu_news_ticker_category',
        array(
            'default'           => 0,
            'sanitize_callback' => 'absint',
            'transport'         => 'refresh',
        )
    );
    $wp_customize->add_control(
        'urdu_news_ticker_category',
        array(
            'label'       => esc_html__( 'ٹکر کے لیے شعبہ منتخب کریں (Ticker Category)', 'urdu-news' ),
            'description' => esc_html__( 'اگر آپ کسی مخصوص شعبے کی خبریں ٹکر میں چلانا چاہتے ہیں تو منتخب کریں۔', 'urdu-news' ),
            'section'     => 'urdu_news_ticker_section',
            'type'        => 'select',
            'choices'     => $categories_choices,
        )
    );

    // Ticker Speed (range in seconds)
    $wp_customize->add_setting(
        'urdu_news_ticker_speed',
        array(
            'default'           => 36,
            'sanitize_callback' => 'absint',
            'transport'         => 'refresh',
        )
    );
    $wp_customize->add_control(
        'urdu_news_ticker_speed',
        array(
            'label'       => esc_html__( 'ٹکر کی رفتار (سیکنڈز) (Ticker Speed)', 'urdu-news' ),
            'description' => esc_html__( 'ایک مکمل چکر کا وقت (15 تا 90 سیکنڈز)۔ کم نمبر = تیز رفتار، زیادہ نمبر = پرسکون مطالعہ۔', 'urdu-news' ),
            'section'     => 'urdu_news_ticker_section',
            'type'        => 'number',
            'input_attrs' => array(
                'min'  => 15,
                'max'  => 90,
                'step' => 2,
            ),
        )
    );

    // =========================================================================
    // Section 3: Homepage Sections (صفحہ اول کے سیکشنز)
    // =========================================================================
    $wp_customize->add_section(
        'urdu_news_homepage_sections',
        array(
            'title'       => esc_html__( 'صفحہ اول کے سیکشنز (Homepage Sections)', 'urdu-news' ),
            'description' => esc_html__( 'صفحہ اول کے مختلف سیکشنز کو آن یا آف کریں۔', 'urdu-news' ),
            'panel'       => 'urdu_news_theme_panel',
            'priority'    => 30,
        )
    );

    // 1. Financial Strip
    $wp_customize->add_setting(
        'urdu_news_enable_financial_strip',
        array(
            'default'           => true,
            'sanitize_callback' => 'urdu_news_sanitize_checkbox',
            'transport'         => 'refresh',
        )
    );
    $wp_customize->add_control(
        'urdu_news_enable_financial_strip',
        array(
            'label'       => esc_html__( 'مالیاتی پٹی (Financial Strip)', 'urdu-news' ),
            'section'     => 'urdu_news_homepage_sections',
            'type'        => 'checkbox',
        )
    );

    // 2. Category Sections
    $wp_customize->add_setting(
        'urdu_news_enable_category_sections',
        array(
            'default'           => true,
            'sanitize_callback' => 'urdu_news_sanitize_checkbox',
            'transport'         => 'refresh',
        )
    );
    $wp_customize->add_control(
        'urdu_news_enable_category_sections',
        array(
            'label'       => esc_html__( 'شعبہ جاتی خبریں (Category Sections: سیاست، کھیل، وغیرہ)', 'urdu-news' ),
            'section'     => 'urdu_news_homepage_sections',
            'type'        => 'checkbox',
        )
    );

    // 3. Video Section
    $wp_customize->add_setting(
        'urdu_news_enable_video_section',
        array(
            'default'           => true,
            'sanitize_callback' => 'urdu_news_sanitize_checkbox',
            'transport'         => 'refresh',
        )
    );
    $wp_customize->add_control(
        'urdu_news_enable_video_section',
        array(
            'label'       => esc_html__( 'ویڈیو خبریں سیکشن (Video News Section)', 'urdu-news' ),
            'section'     => 'urdu_news_homepage_sections',
            'type'        => 'checkbox',
        )
    );

    // 4. Popular / Latest Tabs
    $wp_customize->add_setting(
        'urdu_news_enable_tabbed_rail',
        array(
            'default'           => true,
            'sanitize_callback' => 'urdu_news_sanitize_checkbox',
            'transport'         => 'refresh',
        )
    );
    $wp_customize->add_control(
        'urdu_news_enable_tabbed_rail',
        array(
            'label'       => esc_html__( 'مقبول اور تازہ ترین ٹیبز (Popular / Latest Tabs)', 'urdu-news' ),
            'section'     => 'urdu_news_homepage_sections',
            'type'        => 'checkbox',
        )
    );

    // 5. Opinion Section
    $wp_customize->add_setting(
        'urdu_news_enable_opinion',
        array(
            'default'           => true,
            'sanitize_callback' => 'urdu_news_sanitize_checkbox',
            'transport'         => 'refresh',
        )
    );
    $wp_customize->add_control(
        'urdu_news_enable_opinion',
        array(
            'label'       => esc_html__( 'کالم و آراء (Opinion Section)', 'urdu-news' ),
            'section'     => 'urdu_news_homepage_sections',
            'type'        => 'checkbox',
        )
    );

    // 6. Live Strip
    $wp_customize->add_setting(
        'urdu_news_enable_live_strip',
        array(
            'default'           => true,
            'sanitize_callback' => 'urdu_news_sanitize_checkbox',
            'transport'         => 'refresh',
        )
    );
    $wp_customize->add_control(
        'urdu_news_enable_live_strip',
        array(
            'label'       => esc_html__( 'براہِ راست نشریات پٹی (Live TV / Stream Strip)', 'urdu-news' ),
            'section'     => 'urdu_news_homepage_sections',
            'type'        => 'checkbox',
        )
    );

    // 7. Image Gallery
    $wp_customize->add_setting(
        'urdu_news_enable_gallery',
        array(
            'default'           => true,
            'sanitize_callback' => 'urdu_news_sanitize_checkbox',
            'transport'         => 'refresh',
        )
    );
    $wp_customize->add_control(
        'urdu_news_enable_gallery',
        array(
            'label'       => esc_html__( 'تصویری کہانیاں (Image Gallery)', 'urdu-news' ),
            'section'     => 'urdu_news_homepage_sections',
            'type'        => 'checkbox',
        )
    );

    // 8. Authors Strip
    $wp_customize->add_setting(
        'urdu_news_enable_authors',
        array(
            'default'           => true,
            'sanitize_callback' => 'urdu_news_sanitize_checkbox',
            'transport'         => 'refresh',
        )
    );
    $wp_customize->add_control(
        'urdu_news_enable_authors',
        array(
            'label'       => esc_html__( 'ہمارے نمائندے اور تجزیہ کار (Authors Strip)', 'urdu-news' ),
            'section'     => 'urdu_news_homepage_sections',
            'type'        => 'checkbox',
        )
    );

    // 9. Newsletter Section
    $wp_customize->add_setting(
        'urdu_news_enable_newsletter',
        array(
            'default'           => true,
            'sanitize_callback' => 'urdu_news_sanitize_checkbox',
            'transport'         => 'refresh',
        )
    );
    $wp_customize->add_control(
        'urdu_news_enable_newsletter',
        array(
            'label'       => esc_html__( 'خبرنامہ / نیوزلیٹر (Newsletter Section)', 'urdu-news' ),
            'section'     => 'urdu_news_homepage_sections',
            'type'        => 'checkbox',
        )
    );

    // =========================================================================
    // Section 4: Live TV Embed (براہِ راست نشریات)
    // =========================================================================
    $wp_customize->add_section(
        'urdu_news_live_tv_section',
        array(
            'title'       => esc_html__( 'براہِ راست نشریات (Live TV Embed)', 'urdu-news' ),
            'description' => esc_html__( 'لائیو ٹی وی اسٹریمنگ کا یو آر ایل درج کریں۔ یوٹیوب یا فیس بک کا لائیو لنک خودکار طور پر ایمبیڈ ہو جائے گا۔ خالی رہنے پر خوبصورت پلیس ہولڈر ظاہر رہے گا۔', 'urdu-news' ),
            'panel'       => 'urdu_news_theme_panel',
            'priority'    => 40,
        )
    );

    $wp_customize->add_setting(
        'urdu_news_live_tv_url',
        array(
            'default'           => '',
            'sanitize_callback' => 'esc_url_raw',
            'transport'         => 'refresh',
        )
    );
    $wp_customize->add_control(
        'urdu_news_live_tv_url',
        array(
            'label'       => esc_html__( 'لائیو اسٹریم یو آر ایل (Live Stream URL)', 'urdu-news' ),
            'description' => esc_html__( 'مثال: https://www.youtube.com/watch?v=... یا https://youtu.be/...', 'urdu-news' ),
            'section'     => 'urdu_news_live_tv_section',
            'type'        => 'url',
        )
    );

    // =========================================================================
    // Section 5: AdSense Slots (اشتہارات کے سلاٹس)
    // =========================================================================
    $wp_customize->add_section(
        'urdu_news_ads_section',
        array(
            'title'       => esc_html__( 'اشتہارات کے سلاٹس (AdSense Slots)', 'urdu-news' ),
            'description' => esc_html__( 'گوگل ایڈسینس یا کسٹم بینر اشتہار کا کوڈ یہاں درج کریں۔ جب خالی ہو گا تو فرنٹ اینڈ پر کوئی کوڈ ظاہر نہیں ہوگا (کسٹمائزر میں اشارہ ظاہر ہو سکتا ہے)۔ کوئی اصلی اشتہاری کوڈ پہلے سے شامل نہیں۔', 'urdu-news' ),
            'panel'       => 'urdu_news_theme_panel',
            'priority'    => 50,
        )
    );

    // 1. Header Ad Slot
    $wp_customize->add_setting(
        'urdu_news_header_ad',
        array(
            'default'           => '',
            'sanitize_callback' => 'urdu_news_sanitize_ad_code',
            'transport'         => 'refresh',
        )
    );
    $wp_customize->add_control(
        'urdu_news_header_ad',
        array(
            'label'       => esc_html__( 'ہیڈر اشتہار کوڈ (Header Ad - 728x90)', 'urdu-news' ),
            'description' => esc_html__( 'ہیڈر کے نیچے نمایاں پوزیشن پر ظاہر ہونے والا بینر کوڈ۔', 'urdu-news' ),
            'section'     => 'urdu_news_ads_section',
            'type'        => 'textarea',
        )
    );

    // 2. Sidebar Ad Slot
    $wp_customize->add_setting(
        'urdu_news_sidebar_ad',
        array(
            'default'           => '',
            'sanitize_callback' => 'urdu_news_sanitize_ad_code',
            'transport'         => 'refresh',
        )
    );
    $wp_customize->add_control(
        'urdu_news_sidebar_ad',
        array(
            'label'       => esc_html__( 'سائیڈ بار اشتہار کوڈ (Sidebar Ad - 300x250)', 'urdu-news' ),
            'description' => esc_html__( 'سائیڈ بار میں سرچ کے بعد ظاہر ہونے والا اشتہار۔', 'urdu-news' ),
            'section'     => 'urdu_news_ads_section',
            'type'        => 'textarea',
        )
    );

    // 3. In-Article Ad Slot
    $wp_customize->add_setting(
        'urdu_news_in_article_ad',
        array(
            'default'           => '',
            'sanitize_callback' => 'urdu_news_sanitize_ad_code',
            'transport'         => 'refresh',
        )
    );
    $wp_customize->add_control(
        'urdu_news_in_article_ad',
        array(
            'label'       => esc_html__( 'مضمون کے اندر اشتہار کوڈ (In-Article Ad)', 'urdu-news' ),
            'description' => esc_html__( 'خبر کی تفصیل کے دوسرے پیراگراف کے بعد خودکار طور پر ظاہر ہونے والا اشتہار۔', 'urdu-news' ),
            'section'     => 'urdu_news_ads_section',
            'type'        => 'textarea',
        )
    );

    // =========================================================================
    // Section 6: Social Links (سوشل میڈیا روابط)
    // =========================================================================
    $wp_customize->add_section(
        'urdu_news_social_section',
        array(
            'title'       => esc_html__( 'سوشل میڈیا لنکس (Social Links)', 'urdu-news' ),
            'description' => esc_html__( 'ٹاپ بار، سائیڈ بار اور فوٹر میں استعمال ہونے والے سوشل لنکس درج کریں۔', 'urdu-news' ),
            'panel'       => 'urdu_news_theme_panel',
            'priority'    => 60,
        )
    );

    // Facebook
    $wp_customize->add_setting(
        'urdu_news_social_facebook',
        array(
            'default'           => 'https://facebook.com',
            'sanitize_callback' => 'esc_url_raw',
            'transport'         => 'refresh',
        )
    );
    $wp_customize->add_control(
        'urdu_news_social_facebook',
        array(
            'label'   => esc_html__( 'فیس بک (Facebook URL)', 'urdu-news' ),
            'section' => 'urdu_news_social_section',
            'type'    => 'url',
        )
    );

    // X / Twitter
    $wp_customize->add_setting(
        'urdu_news_social_x',
        array(
            'default'           => 'https://x.com',
            'sanitize_callback' => 'esc_url_raw',
            'transport'         => 'refresh',
        )
    );
    $wp_customize->add_control(
        'urdu_news_social_x',
        array(
            'label'   => esc_html__( 'ایکس / ٹویٹر (X / Twitter URL)', 'urdu-news' ),
            'section' => 'urdu_news_social_section',
            'type'    => 'url',
        )
    );

    // YouTube
    $wp_customize->add_setting(
        'urdu_news_social_youtube',
        array(
            'default'           => 'https://youtube.com',
            'sanitize_callback' => 'esc_url_raw',
            'transport'         => 'refresh',
        )
    );
    $wp_customize->add_control(
        'urdu_news_social_youtube',
        array(
            'label'   => esc_html__( 'یوٹیوب (YouTube URL)', 'urdu-news' ),
            'section' => 'urdu_news_social_section',
            'type'    => 'url',
        )
    );

    // Instagram
    $wp_customize->add_setting(
        'urdu_news_social_instagram',
        array(
            'default'           => 'https://instagram.com',
            'sanitize_callback' => 'esc_url_raw',
            'transport'         => 'refresh',
        )
    );
    $wp_customize->add_control(
        'urdu_news_social_instagram',
        array(
            'label'   => esc_html__( 'انسٹاگرام (Instagram URL)', 'urdu-news' ),
            'section' => 'urdu_news_social_section',
            'type'    => 'url',
        )
    );

    // WhatsApp
    $wp_customize->add_setting(
        'urdu_news_social_whatsapp',
        array(
            'default'           => '',
            'sanitize_callback' => 'esc_url_raw',
            'transport'         => 'refresh',
        )
    );
    $wp_customize->add_control(
        'urdu_news_social_whatsapp',
        array(
            'label'       => esc_html__( 'واٹس ایپ چینل (WhatsApp Channel URL)', 'urdu-news' ),
            'description' => esc_html__( 'اختیاری: اپنے واٹس ایپ چینل یا گروپ کا لنک درج کریں۔', 'urdu-news' ),
            'section'     => 'urdu_news_social_section',
            'type'        => 'url',
        )
    );

    // =========================================================================
    // Section 7: Footer Settings (فوٹر کی ترتیبات)
    // =========================================================================
    $wp_customize->add_section(
        'urdu_news_footer_section',
        array(
            'title'       => esc_html__( 'فوٹر اور کاپی رائٹ (Footer Settings)', 'urdu-news' ),
            'description' => esc_html__( 'فوٹر کے "ہمارے متعلق" کالم اور کاپی رائٹ لائن کو اپنی ضرورت کے مطابق تبدیل کریں۔', 'urdu-news' ),
            'panel'       => 'urdu_news_theme_panel',
            'priority'    => 70,
        )
    );

    // Footer About Text
    $wp_customize->add_setting(
        'urdu_news_footer_about_text',
        array(
            'default'           => esc_html__( 'اردو نیوز پاکستان کا ممتاز اور بااعتماد ڈیجیٹل ادارہ ہے جو قارئین کو لمحہ بہ لمحہ باوثوق، غیر جانبدار اور تحقیقی خبریں، تجزیے اور خصوصی دستاویزی رپورٹس فراہم کرتا ہے۔', 'urdu-news' ),
            'sanitize_callback' => 'wp_kses_post',
            'transport'         => 'refresh',
        )
    );
    $wp_customize->add_control(
        'urdu_news_footer_about_text',
        array(
            'label'       => esc_html__( 'ہمارے متعلق تحریر (Footer About Text)', 'urdu-news' ),
            'description' => esc_html__( 'فوٹر کے پہلے کالم میں لوگو کے نیچے ظاہر ہونے والی تحریر۔', 'urdu-news' ),
            'section'     => 'urdu_news_footer_section',
            'type'        => 'textarea',
        )
    );

    // Copyright Line Override
    $wp_customize->add_setting(
        'urdu_news_footer_copyright',
        array(
            'default'           => '',
            'sanitize_callback' => 'sanitize_text_field',
            'transport'         => 'refresh',
        )
    );
    $wp_customize->add_control(
        'urdu_news_footer_copyright',
        array(
            'label'       => esc_html__( 'کاپی رائٹ کی تحریر (Copyright Override)', 'urdu-news' ),
            'description' => esc_html__( 'ڈیفالٹ حقوق کی تحریر تبدیل کرنے کے لیے درج کریں۔ خالی رکھنے پر خودکار تاریخ اور جملہ حقوق کی محفوظ لائن ظاہر ہوگی۔', 'urdu-news' ),
            'section'     => 'urdu_news_footer_section',
            'type'        => 'text',
        )
    );
}
add_action( 'customize_register', 'urdu_news_customize_register' );

/**
 * Sanitize checkbox inputs.
 *
 * @param mixed $checked Input value.
 * @return bool True if checked, false otherwise.
 */
function urdu_news_sanitize_checkbox( $checked ) {
    return ( isset( $checked ) && true === (bool) $checked );
}

/**
 * Sanitize advertising code allowing scripts and iframes for administrators.
 *
 * @param string $input Raw ad code.
 * @return string Sanitized code.
 */
function urdu_news_sanitize_ad_code( $input ) {
    if ( current_user_can( 'unfiltered_html' ) ) {
        return $input;
    }
    return wp_kses_post( $input );
}

/**
 * Output dynamic Customizer CSS variables via wp_head.
 *
 * Sets CSS variables for primary and accent colors, as well as ticker speed.
 * Defaults match current design values (#C8102E primary, #22303f accent).
 */
function urdu_news_customizer_css() {
    $primary_color = sanitize_hex_color( get_theme_mod( 'urdu_news_primary_color', '#C8102E' ) );
    $accent_color  = sanitize_hex_color( get_theme_mod( 'urdu_news_accent_color', '#22303f' ) );
    $ticker_speed  = absint( get_theme_mod( 'urdu_news_ticker_speed', 36 ) );

    if ( empty( $primary_color ) ) {
        $primary_color = '#C8102E';
    }
    if ( empty( $accent_color ) ) {
        $accent_color = '#22303f';
    }
    if ( $ticker_speed < 10 || $ticker_speed > 120 ) {
        $ticker_speed = 36;
    }
    ?>
    <style id="urdu-news-customizer-css">
        :root {
            --primary-color: <?php echo esc_attr( $primary_color ); ?>;
            --accent-color: <?php echo esc_attr( $accent_color ); ?>;
            --ticker-speed: <?php echo esc_attr( $ticker_speed ); ?>s;
        }
    </style>
    <?php
}
add_action( 'wp_head', 'urdu_news_customizer_css', 100 );
