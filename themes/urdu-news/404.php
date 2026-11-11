<?php
/**
 * The template for displaying 404 pages (not found)
 *
 * @package Urdu_News
 * @version 0.1-trial
 */

get_header();

// Fetch 4 latest headlines (real or fallback)
$latest_headlines = array();
if ( function_exists( 'get_posts' ) ) {
    $lp_query = get_posts(
        array(
            'posts_per_page' => 4,
            'post_status'    => 'publish',
        )
    );

    if ( ! empty( $lp_query ) ) {
        foreach ( $lp_query as $lp ) {
            $pid       = $lp->ID;
            $thumb_url = has_post_thumbnail( $pid ) ? get_the_post_thumbnail_url( $pid, 'urdu-side' ) : '';
            $cats      = get_the_category( $pid );
            $c_name    = ! empty( $cats ) ? $cats[0]->name : esc_html__( 'اہم خبر', 'urdu-news' );
            $c_slug    = ! empty( $cats ) ? $cats[0]->slug : '';

            $latest_headlines[] = array(
                'title'     => get_the_title( $pid ),
                'url'       => get_permalink( $pid ),
                'thumbnail' => $thumb_url,
                'category'  => $c_name,
                'accent'    => urdu_news_get_category_accent( $c_slug ),
                'date'      => urdu_news_get_post_urdu_date( $pid ),
            );
        }
    }
}

// Fallback sample headlines if no posts available
$sample_404_stories = array(
    array(
        'title'     => 'قومی اسمبلی میں اہم آئینی بل منظور، تعلیمی اور عدالتی نظام میں انقلابی اصلاحات کا اعلان',
        'url'       => '#',
        'thumbnail' => '',
        'category'  => 'قومی',
        'accent'    => '#1d4ed8',
        'date'      => '<span class="bidi-isolate">11</span> نومبر <span class="bidi-isolate">2026ء</span>',
    ),
    array(
        'title'     => 'اسٹاک مارکیٹ میں تاریخی تیزی: 100 انڈیکس میں ریکارڈ اضافہ، معاشی اعشاریوں میں بہتری',
        'url'       => '#',
        'thumbnail' => '',
        'category'  => 'کاروبار',
        'accent'    => '#0891b2',
        'date'      => '<span class="bidi-isolate">10</span> نومبر <span class="bidi-isolate">2026ء</span>',
    ),
    array(
        'title'     => 'آسٹریلیا کے خلاف تاریخی ون ڈے سیریز میں پاکستان کی شاندار فتح، قومی ٹیم نے ٹرافی جیت لی',
        'url'       => '#',
        'thumbnail' => '',
        'category'  => 'کھیل',
        'accent'    => '#006341',
        'date'      => '<span class="bidi-isolate">9</span> نومبر <span class="bidi-isolate">2026ء</span>',
    ),
    array(
        'title'     => 'ملک بھر میں تیز رفتار 5G اسپیکٹرم کی نیلامی کے حتمی مراحل کی تیاری مکمل، ڈیجیٹل سروسز فعال',
        'url'       => '#',
        'thumbnail' => '',
        'category'  => 'ٹیکنالوجی',
        'accent'    => '#7c3aed',
        'date'      => '<span class="bidi-isolate">8</span> نومبر <span class="bidi-isolate">2026ء</span>',
    ),
);

$current_count = count( $latest_headlines );
if ( $current_count < 4 ) {
    for ( $i = $current_count; $i < 4; $i++ ) {
        if ( isset( $sample_404_stories[ $i ] ) ) {
            $latest_headlines[] = $sample_404_stories[ $i ];
        }
    }
}
?>

<div class="error-404-container site-container">
    <div class="error-404-card">

        <!-- 1. Big Nastaliq "404" -->
        <div class="error-404-badge-wrap">
            <span class="error-404-number bidi-isolate">404</span>
        </div>

        <!-- 2. Urdu Message -->
        <h1 class="error-404-title">
            <?php esc_html_e( 'صفحہ دستیاب نہیں ہے', 'urdu-news' ); ?>
        </h1>
        <p class="error-404-message">
            <?php esc_html_e( 'معذرت، آپ جس صفحے کی تلاش میں ہیں وہ موجود نہیں ہے، شاید اسے حذف کر دیا گیا ہے یا اس کا لنک تبدیل ہو چکا ہے۔ مطلوبہ مواد تلاش کرنے کے لیے سرچ باکس استعمال کریں یا صفحہ اول پر جائیں۔', 'urdu-news' ); ?>
        </p>

        <!-- 3. Search Form -->
        <div class="error-404-search-wrap">
            <form role="search" method="get" class="error-404-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
                <label class="screen-reader-text" for="error-search-input"><?php esc_html_e( 'خبر تلاش کریں', 'urdu-news' ); ?></label>
                <input type="search" id="error-search-input" class="error-404-input" placeholder="<?php esc_attr_e( 'خبر یا موضوع تلاش کریں...', 'urdu-news' ); ?>" name="s" required />
                <button type="submit" class="error-404-btn"><?php esc_html_e( 'تلاش کریں', 'urdu-news' ); ?></button>
            </form>
        </div>

        <!-- 4. Links to Homepage and Main Categories -->
        <div class="error-404-links-section">
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="btn-home-action">
                <span class="home-icon" aria-hidden="true">🏠</span>
                <span><?php esc_html_e( 'صفحہ اول پر واپس جائیں', 'urdu-news' ); ?></span>
            </a>

            <div class="error-category-shortcuts">
                <span class="shortcuts-label"><?php esc_html_e( 'یا اہم شعبہ جات منتخب کریں:', 'urdu-news' ); ?></span>
                <div class="shortcuts-chips">
                    <a href="<?php echo esc_url( home_url( '/category/siyasat/' ) ); ?>" class="shortcut-chip" style="--chip-accent: #1d4ed8;">
                        <span>سیاست</span>
                    </a>
                    <a href="<?php echo esc_url( home_url( '/category/khel/' ) ); ?>" class="shortcut-chip" style="--chip-accent: #006341;">
                        <span>کھیل</span>
                    </a>
                    <a href="<?php echo esc_url( home_url( '/category/karobar/' ) ); ?>" class="shortcut-chip" style="--chip-accent: #0891b2;">
                        <span>کاروبار</span>
                    </a>
                    <a href="<?php echo esc_url( home_url( '/category/technology/' ) ); ?>" class="shortcut-chip" style="--chip-accent: #7c3aed;">
                        <span>ٹیکنالوجی</span>
                    </a>
                    <a href="<?php echo esc_url( home_url( '/category/entertainment/' ) ); ?>" class="shortcut-chip" style="--chip-accent: #d97706;">
                        <span>تفریح</span>
                    </a>
                </div>
            </div>
        </div>

    </div><!-- .error-404-card -->

    <!-- 5. Four Latest Headlines -->
    <section class="error-latest-section" aria-label="<?php esc_attr_e( 'تازہ ترین خبریں', 'urdu-news' ); ?>">
        <div class="error-latest-header">
            <span class="error-latest-icon" aria-hidden="true">📰</span>
            <h2 class="error-latest-title"><?php esc_html_e( 'تازہ ترین اہم خبریں', 'urdu-news' ); ?></h2>
        </div>

        <div class="error-latest-grid">
            <?php foreach ( $latest_headlines as $h_idx => $headline ) : ?>
                <article class="latest-headline-card">
                    <div class="latest-headline-thumb">
                        <a href="<?php echo esc_url( $headline['url'] ); ?>" tabindex="-1" aria-hidden="true">
                            <?php if ( ! empty( $headline['thumbnail'] ) ) : ?>
                                <img src="<?php echo esc_url( $headline['thumbnail'] ); ?>" alt="<?php echo esc_attr( wp_strip_all_tags( $headline['title'] ) ); ?>" class="latest-card-img" loading="lazy">
                            <?php else : ?>
                                <div class="latest-placeholder-gradient gradient-sub-<?php echo esc_attr( ( $h_idx % 4 ) + 1 ); ?>" aria-hidden="true"></div>
                            <?php endif; ?>
                        </a>
                        <span class="latest-cat-badge" style="background-color: <?php echo esc_attr( $headline['accent'] ); ?>;">
                            <?php echo esc_html( $headline['category'] ); ?>
                        </span>
                    </div>
                    <div class="latest-headline-content">
                        <h3 class="latest-headline-title">
                            <a href="<?php echo esc_url( $headline['url'] ); ?>"><?php echo wp_kses_post( $headline['title'] ); ?></a>
                        </h3>
                        <div class="latest-headline-meta">
                            <span class="meta-time"><span class="bidi-isolate"><?php echo wp_kses_post( $headline['date'] ); ?></span></span>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

</div><!-- .error-404-container -->

<?php
get_footer();
