<?php
/**
 * Urdu News functions and definitions
 *
 * @package Urdu_News
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Sets up theme defaults and registers support for various WordPress features.
 */
function urdu_news_setup() {
    // Load theme textdomain for translation support.
    load_theme_textdomain( 'urdu-news', get_template_directory() . '/languages' );

    // Add default posts and comments RSS feed links to head.
    add_theme_support( 'automatic-feed-links' );

    // Let WordPress manage the document title.
    add_theme_support( 'title-tag' );

    // Enable support for Post Thumbnails on posts and pages.
    add_theme_support( 'post-thumbnails' );

    // Switch default core markup to output valid HTML5.
    add_theme_support(
        'html5',
        array(
            'search-form',
            'comment-form',
            'comment-list',
            'gallery',
            'caption',
            'style',
            'script',
        )
    );

    // Support responsive embedded content (YouTube, Vimeo, etc.).
    add_theme_support( 'responsive-embeds' );

    // Add support for custom logo.
    add_theme_support(
        'custom-logo',
        array(
            'height'      => 80,
            'width'       => 260,
            'flex-height' => true,
            'flex-width'  => true,
        )
    );

    // Register primary navigation menu.
    register_nav_menus(
        array(
            'primary' => esc_html__( 'Primary Menu', 'urdu-news' ),
        )
    );

    // Register custom image sizes required by the brief.
    add_image_size( 'urdu-hero', 1200, 675, true );       // 16:9 aspect ratio
    add_image_size( 'urdu-side', 400, 300, true );        // 4:3 aspect ratio
    add_image_size( 'urdu-hero-mobile', 800, 450, true ); // 16:9 mobile
}
add_action( 'after_setup_theme', 'urdu_news_setup' );

/**
 * Enqueue scripts and styles.
 */
function urdu_news_scripts() {
    // Google Fonts: Noto Nastaliq Urdu (400, 700) and Noto Naskh Arabic (400, 700) with font-display: swap.
    $fonts_url = 'https://fonts.googleapis.com/css2?family=Noto+Naskh+Arabic:wght@400;700&family=Noto+Nastaliq+Urdu:wght@400;700&display=swap';

    wp_enqueue_style(
        'urdu-news-fonts',
        $fonts_url,
        array(),
        null
    );

    // Enqueue main theme stylesheet.
    wp_enqueue_style(
        'urdu-news-style',
        get_stylesheet_uri(),
        array( 'urdu-news-fonts' ),
        '1.0.0'
    );
}
add_action( 'wp_enqueue_scripts', 'urdu_news_scripts' );

// Require Theme Customizer definitions.
require_once get_template_directory() . '/inc/customizer.php';

// Require SEO, Open Graph, and NewsArticle Schema.
require_once get_template_directory() . '/inc/seo.php';

/**
 * Fallback callback for primary navigation menu when no menu is set.
 */
function urdu_news_default_nav_menu() {
    $menu_items = array(
        'صفحہ اول'            => home_url( '/' ),
        'پاکستان'             => '#',
        'بین الاقوامی'        => '#',
        'کھیل'                => '#',
        'تجارت'               => '#',
        'سائنس و ٹیکنالوجی'   => '#',
        'صحت'                => '#',
        'موسم'                => '#',
    );

    echo '<nav id="site-navigation" class="primary-nav" aria-label="' . esc_attr__( 'بنیادی مینو', 'urdu-news' ) . '">';
    echo '<ul id="primary-menu" class="nav-menu">';
    $is_first = true;
    foreach ( $menu_items as $title => $url ) {
        $classes = 'menu-item' . ( $is_first ? ' current-menu-item' : '' );
        echo '<li class="' . esc_attr( $classes ) . '">';
        echo '<a href="' . esc_url( $url ) . '">' . esc_html( $title ) . '</a>';
        echo '</li>';
        $is_first = false;
    }
    echo '</ul>';
    echo '</nav>';
}

/**
 * Render current date in Urdu with bidi-isolate wrapped numbers.
 */
function urdu_news_render_current_urdu_date() {
    $days = array(
        'Sunday'    => 'اتوار',
        'Monday'    => 'پیر',
        'Tuesday'   => 'منگل',
        'Wednesday' => 'بدھ',
        'Thursday'  => 'جمعرات',
        'Friday'    => 'جمعہ',
        'Saturday'  => 'ہفتہ',
    );

    $months = array(
        'January'   => 'جنوری',
        'February'  => 'فروری',
        'March'     => 'مارچ',
        'April'     => 'اپریل',
        'May'       => 'مئی',
        'June'      => 'جون',
        'July'      => 'جولائی',
        'August'    => 'اگست',
        'September' => 'ستمبر',
        'October'   => 'اکتوبر',
        'November'  => 'نومبر',
        'December'  => 'دسمبر',
    );

    $current_day_en   = date( 'l' );
    $current_month_en = date( 'F' );
    $current_day_ur   = isset( $days[ $current_day_en ] ) ? $days[ $current_day_en ] : $current_day_en;
    $current_month_ur = isset( $months[ $current_month_en ] ) ? $months[ $current_month_en ] : $current_month_en;
    $current_date_num = date( 'j' );
    $current_year_num = date( 'Y' );

    printf(
        '%1$s، <span class="bidi-isolate">%2$s</span> %3$s <span class="bidi-isolate">%4$sء</span>',
        esc_html( $current_day_ur ),
        esc_html( $current_date_num ),
        esc_html( $current_month_ur ),
        esc_html( $current_year_num )
    );
}

/**
 * Helper to fetch category stories from WordPress or fall back to sample stories (Born-Complete principle).
 *
 * @param string $category_slug Category slug to query.
 * @param array  $sample_stories Fallback array of sample stories.
 * @param int    $count Number of stories needed (default 4: 1 medium + 3 small).
 * @return array Array of story items with title, url, thumbnail, excerpt, and time.
 */
function urdu_news_get_category_stories( $category_slug, $sample_stories, $count = 4 ) {
    $stories = array();

    if ( function_exists( 'get_posts' ) ) {
        $posts = get_posts(
            array(
                'category_name'  => sanitize_title( $category_slug ),
                'posts_per_page' => $count,
                'post_status'    => 'publish',
            )
        );

        if ( ! empty( $posts ) ) {
            foreach ( $posts as $p ) {
                $post_id   = $p->ID;
                $thumb_url = has_post_thumbnail( $post_id ) ? get_the_post_thumbnail_url( $post_id, 'urdu-side' ) : '';
                $excerpt   = get_the_excerpt( $post_id );
                if ( empty( $excerpt ) ) {
                    $excerpt = wp_trim_words( $p->post_content, 20 );
                }

                $stories[] = array(
                    'title'     => get_the_title( $post_id ),
                    'url'       => get_permalink( $post_id ),
                    'thumbnail' => $thumb_url,
                    'excerpt'   => wp_strip_all_tags( $excerpt ),
                    'time'      => human_time_diff( get_the_time( 'U', $post_id ), time() ) . ' ' . esc_html__( 'پہلے', 'urdu-news' ),
                    'is_sample' => false,
                );
            }
        }
    }

    // Fill any missing slots with sample stories so section is always born-complete
    $current_count = count( $stories );
    if ( $current_count < $count && ! empty( $sample_stories ) ) {
        $sample_idx = 0;
        for ( $i = $current_count; $i < $count; $i++ ) {
            if ( isset( $sample_stories[ $sample_idx ] ) ) {
                $stories[] = $sample_stories[ $sample_idx ];
                $sample_idx++;
            }
        }
    }

    return $stories;
}

/**
 * Helper to fetch popular or latest stories for the tabbed rail with sample fallback.
 *
 * @param string $type 'popular' (by comments) or 'latest' (by date).
 * @param array  $sample_stories Fallback array of 5 sample stories.
 * @return array Array of 5 story items with title, url, time.
 */
function urdu_news_get_tabbed_stories( $type, $sample_stories ) {
    $stories = array();

    if ( function_exists( 'get_posts' ) ) {
        $args = array(
            'posts_per_page' => 5,
            'post_status'    => 'publish',
        );

        if ( 'popular' === $type ) {
            $args['orderby'] = 'comment_count';
            $args['order']   = 'DESC';
        } else {
            $args['orderby'] = 'date';
            $args['order']   = 'DESC';
        }

        $posts = get_posts( $args );

        if ( ! empty( $posts ) ) {
            foreach ( $posts as $p ) {
                $post_id   = $p->ID;
                $stories[] = array(
                    'title'     => get_the_title( $post_id ),
                    'url'       => get_permalink( $post_id ),
                    'time'      => human_time_diff( get_the_time( 'U', $post_id ), time() ) . ' ' . esc_html__( 'پہلے', 'urdu-news' ),
                    'is_sample' => false,
                );
            }
        }
    }

    // Fill missing slots from sample stories
    $current_count = count( $stories );
    if ( $current_count < 5 && ! empty( $sample_stories ) ) {
        $sample_idx = 0;
        for ( $i = $current_count; $i < 5; $i++ ) {
            if ( isset( $sample_stories[ $sample_idx ] ) ) {
                $stories[] = $sample_stories[ $sample_idx ];
                $sample_idx++;
            }
        }
    }

    return $stories;
}

/**
 * Register widget areas required by the theme.
 */
function urdu_news_widgets_init() {
    register_sidebar(
        array(
            'name'          => esc_html__( 'Main Sidebar', 'urdu-news' ),
            'id'            => 'sidebar-main',
            'description'   => esc_html__( 'بنیادی سائیڈ بار کے ویجٹس یہاں ظاہر ہوں گے۔', 'urdu-news' ),
            'before_widget' => '<section id="%1$s" class="sidebar-widget widget %2$s">',
            'after_widget'  => '</section>',
            'before_title'  => '<h3 class="widget-title">',
            'after_title'   => '</h3>',
        )
    );

    register_sidebar(
        array(
            'name'          => esc_html__( 'Footer Widget Area', 'urdu-news' ),
            'id'            => 'sidebar-footer',
            'description'   => esc_html__( 'فوٹر کے اضافی ویجٹس کا ایریا۔', 'urdu-news' ),
            'before_widget' => '<div id="%1$s" class="footer-widget %2$s">',
            'after_widget'  => '</div>',
            'before_title'  => '<h4 class="footer-widget-title">',
            'after_title'   => '</h4>',
        )
    );
}
add_action( 'widgets_init', 'urdu_news_widgets_init' );

/**
 * Calculate reading time in Urdu with bidi-isolated digits.
 *
 * @param int|null $post_id Optional post ID.
 * @return string Formatted reading time string in Urdu.
 */
function urdu_news_get_reading_time( $post_id = null ) {
    $post_id = $post_id ? $post_id : get_the_ID();
    $content = get_post_field( 'post_content', $post_id );
    $text    = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $content ) ) );

    if ( ! empty( $text ) ) {
        $words_array = explode( ' ', $text );
        $word_count  = count( $words_array );
    } else {
        $word_count = 350;
    }

    $minutes = max( 1, (int) ceil( $word_count / 180 ) );

    return sprintf(
        /* translators: %s: number of minutes */
        '<span class="bidi-isolate">%s</span> ' . esc_html__( 'منٹ کا مطالعہ', 'urdu-news' ),
        esc_html( $minutes )
    );
}

/**
 * Format post published date in Urdu with bidi-isolated numbers.
 *
 * @param int|null $post_id Optional post ID.
 * @return string Urdu formatted date string.
 */
function urdu_news_get_post_urdu_date( $post_id = null ) {
    $post_id = $post_id ? $post_id : get_the_ID();

    $months = array(
        '01' => 'جنوری',
        '02' => 'فروری',
        '03' => 'مارچ',
        '04' => 'اپریل',
        '05' => 'مئی',
        '06' => 'جون',
        '07' => 'جولائی',
        '08' => 'اگست',
        '09' => 'ستمبر',
        '10' => 'اکتوبر',
        '11' => 'نومبر',
        '12' => 'دسمبر',
    );

    $day   = get_the_date( 'j', $post_id );
    $month = get_the_date( 'm', $post_id );
    $year  = get_the_date( 'Y', $post_id );

    $month_ur = isset( $months[ $month ] ) ? $months[ $month ] : get_the_date( 'F', $post_id );

    return sprintf(
        '<span class="bidi-isolate">%1$s</span> %2$s <span class="bidi-isolate">%3$sء</span>',
        esc_html( $day ),
        esc_html( $month_ur ),
        esc_html( $year )
    );
}

/**
 * Get accent color for category slug or term object.
 *
 * @param string|WP_Term|int $category Category slug, term object, or ID.
 * @return string Hex color string.
 */
function urdu_news_get_category_accent( $category = '' ) {
    $slug = '';
    if ( is_object( $category ) && isset( $category->slug ) ) {
        $slug = $category->slug;
    } elseif ( is_numeric( $category ) ) {
        $term = get_term( $category, 'category' );
        if ( $term && ! is_wp_error( $term ) ) {
            $slug = $term->slug;
        }
    } elseif ( is_string( $category ) ) {
        $slug = $category;
    }

    $accents = array(
        'siyasat'       => '#1d4ed8',
        'khel'          => '#006341',
        'karobar'       => '#0891b2',
        'technology'    => '#7c3aed',
        'entertainment' => '#d97706',
    );

    return isset( $accents[ $slug ] ) ? $accents[ $slug ] : '#22303f';
}

/**
 * Insert an in-article ad slot (or saved Customizer ad code) after the second paragraph.
 *
 * @param string $content Full article HTML content.
 * @return string Modified article content with ad slot.
 */
function urdu_news_insert_in_article_ad( $content ) {
    $in_article_ad = get_theme_mod( 'urdu_news_in_article_ad', '' );
    if ( ! empty( $in_article_ad ) ) {
        $ad_html = '<div class="ad-slot ad-slot-in-article ad-slot-active" role="complementary" aria-label="' . esc_attr__( 'اشتہار', 'urdu-news' ) . '">' . $in_article_ad . '</div>';
    } else {
        $ad_html = '<div class="ad-slot ad-slot-in-article" role="complementary" aria-label="' . esc_attr__( 'اشتہار', 'urdu-news' ) . '">
            <span class="ad-slot-label">' . esc_html__( 'اشتہار کی جگہ', 'urdu-news' ) . '</span>
            <span class="ad-slot-dimension bidi-isolate">728x90 / 300x250</span>
        </div>';
    }

    $paragraphs = explode( '</p>', $content );
    if ( count( $paragraphs ) > 2 ) {
        $first_part = $paragraphs[0] . '</p>' . $paragraphs[1] . '</p>' . $ad_html;
        $rest_part  = implode( '</p>', array_slice( $paragraphs, 2 ) );
        return $first_part . $rest_part;
    }

    return $content . $ad_html;
}

/**
 * Render responsive live stream embed player from URL with graceful fallback.
 *
 * @param string $url Live stream URL (YouTube, Facebook, etc.).
 * @return string HTML embed markup.
 */
function urdu_news_get_live_tv_embed( $url ) {
    $url = trim( $url );
    if ( empty( $url ) ) {
        return '';
    }

    if ( function_exists( 'wp_oembed_get' ) ) {
        $embed = wp_oembed_get( $url );
        if ( ! empty( $embed ) && ! is_wp_error( $embed ) ) {
            return '<div class="live-tv-responsive-embed" style="inline-size: 100%; block-size: 100%;">' . $embed . '</div>';
        }
    }

    if ( preg_match( '/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]+)/i', $url, $matches ) ) {
        $embed_src = 'https://www.youtube-nocookie.com/embed/' . $matches[1] . '?autoplay=1&rel=0';
        return '<iframe src="' . esc_url( $embed_src ) . '" title="' . esc_attr__( 'لائیو نشریات', 'urdu-news' ) . '" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen class="live-tv-embed-iframe" style="inline-size: 100%; block-size: 100%; border: 0;"></iframe>';
    }

    return '<iframe src="' . esc_url( $url ) . '" title="' . esc_attr__( 'لائیو نشریات', 'urdu-news' ) . '" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen class="live-tv-embed-iframe" style="inline-size: 100%; block-size: 100%; border: 0;"></iframe>';
}

/**
 * Get related posts for single.php with realistic sample fallbacks.
 *
 * @param int $post_id Current post ID.
 * @param int $category_id Category ID.
 * @param int $count Number of related posts (default 3).
 * @return array Array of related post items.
 */
function urdu_news_get_related_posts( $post_id, $category_id = 0, $count = 3 ) {
    $related = array();

    if ( function_exists( 'get_posts' ) ) {
        $args = array(
            'posts_per_page' => $count,
            'post__not_in'   => array( $post_id ),
            'post_status'    => 'publish',
        );

        if ( $category_id ) {
            $args['cat'] = $category_id;
        }

        $posts = get_posts( $args );
        if ( ! empty( $posts ) ) {
            foreach ( $posts as $p ) {
                $pid       = $p->ID;
                $thumb_url = has_post_thumbnail( $pid ) ? get_the_post_thumbnail_url( $pid, 'urdu-side' ) : '';
                $related[] = array(
                    'title'     => get_the_title( $pid ),
                    'url'       => get_permalink( $pid ),
                    'thumbnail' => $thumb_url,
                    'date'      => urdu_news_get_post_urdu_date( $pid ),
                    'is_sample' => false,
                );
            }
        }
    }

    // Fallback sample cards if fewer than requested count
    $needed = $count - count( $related );
    if ( $needed > 0 ) {
        $samples = array(
            array(
                'title'     => 'قومی ترجیحات اور نئی پالیسی فریم ورک: پارلیمانی کمیٹی نے عوامی فلاحی منصوبوں کی رفتار تیز کرنے کی سفارش کر دی',
                'url'       => '#',
                'thumbnail' => '',
                'date'      => '<span class="bidi-isolate">10</span> نومبر <span class="bidi-isolate">2026ء</span>',
                'is_sample' => true,
            ),
            array(
                'title'     => 'اسٹاک مارکیٹ اور بیرونی تجارت میں بہتری: ترسیلاتِ زر میں <span class="bidi-isolate">12%</span> کا اضافہ اور صنعتی ترقی کے نئے اہداف',
                'url'       => '#',
                'thumbnail' => '',
                'date'      => '<span class="bidi-isolate">9</span> نومبر <span class="bidi-isolate">2026ء</span>',
                'is_sample' => true,
            ),
            array(
                'title'     => 'پاکستان میں ڈیجیٹل معیشت کا فروغ: انفارمیشن ٹیکنالوجی کے برآمدی حجم میں نیا سنگ میل عبور',
                'url'       => '#',
                'thumbnail' => '',
                'date'      => '<span class="bidi-isolate">8</span> نومبر <span class="bidi-isolate">2026ء</span>',
                'is_sample' => true,
            ),
            array(
                'title'     => 'قومی کرکٹ چیمپئن شپ کا فائنل معرکہ: نوجوان کھلاڑیوں کی شاندار کارکردگی اور نئے ٹیلنٹ کے ریکارڈز',
                'url'       => '#',
                'thumbnail' => '',
                'date'      => '<span class="bidi-isolate">7</span> نومبر <span class="bidi-isolate">2026ء</span>',
                'is_sample' => true,
            ),
        );

        for ( $i = 0; $i < $needed; $i++ ) {
            if ( isset( $samples[ $i ] ) ) {
                $related[] = $samples[ $i ];
            }
        }
    }

    return $related;
}

/**
 * Get archive fallback sample stories.
 *
 * @param string $category_slug Category slug or empty for generic.
 * @param int    $count Number of cards.
 * @return array Array of sample items.
 */
function urdu_news_get_archive_sample_stories( $category_slug = '', $count = 6 ) {
    $archive_samples = array(
        array(
            'title'     => 'وفاقی تعلیمی بورڈ کا نیا امتحانی ماڈل متعارف: رٹہ سسٹم کے خاتمے اور فکری صلاحیتوں پر زور',
            'excerpt'   => 'جدید امتحانی طریقہ کار کے تحت طلبہ کی فکری اور تجزیاتی صلاحیتوں کو جانچنے کے لیے امتحانی نصاب میں اہم تبدیلیاں نافذ العمل۔',
            'url'       => '#',
            'time'      => '<span class="bidi-isolate">1</span> گھنٹہ پہلے',
            'category'  => 'قومی',
        ),
        array(
            'title'     => 'قومی معیشت میں برآمدات کا حجم بڑھانے کے لیے جدید صنعتی زونز کے قیام کا باقاعدہ آغاز',
            'excerpt'   => 'صنعتی پیداوار میں خود کفالت اور برآمدات کو فروغ دینے کے لیے جدید ٹیکنالوجی پر مبنی خصوصی اقتصادی زونز قائم کیے جا رہے ہیں۔',
            'url'       => '#',
            'time'      => '<span class="bidi-isolate">2</span> گھنٹے پہلے',
            'category'  => 'کاروبار',
        ),
        array(
            'title'     => 'آسٹریلیا کے خلاف تاریخی ون ڈے سیریز میں پاکستان کی شاندار فتح، نوجوان کھلاڑیوں کے شاندار ریکارڈز',
            'excerpt'   => 'قومی کرکٹ ٹیم نے شاندار کارکردگی کا مظاہرہ کرتے ہوئے سیریز اپنے نام کر لی، باؤلرز اور بلے بازوں کی عمدہ فیلڈنگ کی تعریف۔',
            'url'       => '#',
            'time'      => '<span class="bidi-isolate">3</span> گھنٹے پہلے',
            'category'  => 'کھیل',
        ),
        array(
            'title'     => 'ملک بھر میں تیز رفتار 5G اسپیکٹرم کی نیلامی کے حتمی مراحل کی تیاری مکمل، ڈیجیٹل سروسز میں انقلاب متوقع',
            'excerpt'   => 'ٹیلی کام سیکٹر میں نئی سرمایہ کاری اور صارفین کو جدید تیز رفتار انٹرنیٹ کی فراہمی کے لیے جامع روڈ میپ جاری کر دیا گیا۔',
            'url'       => '#',
            'time'      => '<span class="bidi-isolate">4</span> گھنٹے پہلے',
            'category'  => 'ٹیکنالوجی',
        ),
        array(
            'title'     => 'لاہور کے تاریخی الحمرا ہال میں تین روزہ روایتی صوفی میوزک اور ثقافتی میلے کا شاندار انعقاد',
            'excerpt'   => 'ملک بھر سے نامور فنکاروں، گلوکاروں اور شائقین کی بڑی تعداد نے ثقافتی نمائش اور موسیقی کی محفلوں میں شرکت کی۔',
            'url'       => '#',
            'time'      => '<span class="bidi-isolate">5</span> گھنٹے پہلے',
            'category'  => 'تفریح',
        ),
        array(
            'title'     => 'محکمہ موسمیات کی تازہ ترین ایڈوائزری: بالائی علاقوں میں بارش اور برفباری، سردی کی لہر میں اضافہ',
            'excerpt'   => 'شمالی علاقوں میں درجہ حرارت نقطہ انجماد سے نیچے گرنے کی پیشگوئی، سیاحوں کو محتاط رہنے اور حفاظتی تدابیر اختیار کرنے کی ہدایت۔',
            'url'       => '#',
            'time'      => '<span class="bidi-isolate">6</span> گھنٹے پہلے',
            'category'  => 'موسم',
        ),
    );

    return array_slice( $archive_samples, 0, $count );
}
