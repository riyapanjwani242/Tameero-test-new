<?php
/**
 * The template for displaying search results pages
 *
 * @package Urdu_News
 * @version 0.1-trial
 */

get_header();

global $wp_query;
$search_query = get_search_query();
$total_posts  = $wp_query->found_posts;
?>

<div class="archive-layout-container site-container">
    <div class="archive-main-column">

        <!-- Search Header (bidi-isolated query + repeated search form) -->
        <header class="search-page-header">
            <div class="search-header-banner">
                <div class="search-header-title-row">
                    <span class="search-badge-icon" aria-hidden="true">🔍</span>
                    <h1 class="search-page-title">
                        <?php esc_html_e( 'نتائج تلاش برائے:', 'urdu-news' ); ?>
                        <span class="search-term-isolated bidi-isolate">&ldquo;<?php echo esc_html( $search_query ); ?>&rdquo;</span>
                    </h1>
                </div>
                <p class="search-count-text">
                    <?php if ( have_posts() ) : ?>
                        <?php
                        printf(
                            /* translators: %s: number of search results */
                            esc_html__( 'آپ کی تلاش کے مطابق <span class="bidi-isolate">%s</span> خبریں اور مضامین دستیاب ہیں۔', 'urdu-news' ),
                            esc_html( $total_posts )
                        );
                        ?>
                    <?php else : ?>
                        <?php esc_html_e( 'کوئی نتیجہ دستیاب نہیں ہو سکا۔', 'urdu-news' ); ?>
                    <?php endif; ?>
                </p>
            </div>

            <!-- Repeated Search Form -->
            <div class="search-repeated-box">
                <form role="search" method="get" class="search-repeated-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
                    <label class="screen-reader-text" for="search-page-input"><?php esc_html_e( 'دوبارہ تلاش کریں', 'urdu-news' ); ?></label>
                    <input type="search" id="search-page-input" class="search-repeated-input" placeholder="<?php esc_attr_e( 'دوبارہ تلاش کریں یا نئے الفاظ درج کریں...', 'urdu-news' ); ?>" value="<?php echo esc_attr( $search_query ); ?>" name="s" required />
                    <button type="submit" class="search-repeated-btn"><?php esc_html_e( 'تلاش کریں', 'urdu-news' ); ?></button>
                </form>
            </div>
        </header>

        <!-- Search Results Grid (Reusing category grid cards) -->
        <?php if ( have_posts() ) : ?>
            <div class="archive-posts-grid">
                <?php
                while ( have_posts() ) :
                    the_post();

                    $categories = get_the_category();
                    $first_cat  = ! empty( $categories ) ? $categories[0] : null;
                    $cat_name   = $first_cat ? $first_cat->name : esc_html__( 'خبریں', 'urdu-news' );
                    $cat_slug   = $first_cat ? $first_cat->slug : '';
                    $cat_accent = urdu_news_get_category_accent( $cat_slug );
                    ?>
                    <article id="post-<?php the_ID(); ?>" <?php post_class( 'archive-card' ); ?>>
                        <div class="archive-card-thumb-wrap">
                            <a href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
                                <?php if ( has_post_thumbnail() ) : ?>
                                    <?php the_post_thumbnail( 'urdu-side', array( 'class' => 'archive-card-img', 'loading' => 'lazy' ) ); ?>
                                <?php else : ?>
                                    <div class="archive-placeholder-gradient" aria-hidden="true"></div>
                                <?php endif; ?>
                            </a>
                            <span class="archive-card-cat-tag" style="background-color: <?php echo esc_attr( $cat_accent ); ?>;">
                                <?php echo esc_html( $cat_name ); ?>
                            </span>
                        </div>
                        <div class="archive-card-content">
                            <h2 class="archive-card-title">
                                <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                            </h2>
                            <div class="archive-card-meta">
                                <span class="meta-item"><span class="bidi-isolate"><?php echo urdu_news_get_post_urdu_date(); ?></span></span>
                                <span class="meta-sep" aria-hidden="true">•</span>
                                <span class="meta-item"><span class="bidi-isolate"><?php echo urdu_news_get_reading_time(); ?></span></span>
                            </div>
                            <div class="archive-card-excerpt">
                                <?php the_excerpt(); ?>
                            </div>
                        </div>
                    </article>
                <?php endwhile; ?>
            </div>

            <!-- Pagination (the_posts_pagination with Urdu labels) -->
            <?php
            the_posts_pagination(
                array(
                    'mid_size'           => 2,
                    'prev_text'          => sprintf( '%s <span class="nav-arrow" aria-hidden="true">→</span>', esc_html__( 'پچھلا صفحہ', 'urdu-news' ) ),
                    'next_text'          => sprintf( '<span class="nav-arrow" aria-hidden="true">←</span> %s', esc_html__( 'اگلا صفحہ', 'urdu-news' ) ),
                    'before_page_number' => '<span class="bidi-isolate">',
                    'after_page_number'  => '</span>',
                    'screen_reader_text' => esc_html__( 'صفحات کی نیویگیشن', 'urdu-news' ),
                )
            );
            ?>

        <?php else : ?>
            <!-- Friendly Urdu "No Results" State with Suggestions -->
            <div class="search-no-results-card">
                <div class="no-results-icon" aria-hidden="true">💡</div>
                <h2 class="no-results-heading"><?php esc_html_e( 'معذرت! آپ کی تلاش کے مطابق کوئی خبر یا مضمون نہیں ملا', 'urdu-news' ); ?></h2>
                <p class="no-results-desc">
                    <?php esc_html_e( 'ہم آپ کی مطلوبہ معلومات تلاش کرنے میں مدد کرنا چاہتے ہیں۔ براہِ کرم درج ذیل تجاویز پر عمل کریں:', 'urdu-news' ); ?>
                </p>

                <ul class="search-suggestions-list">
                    <li><?php esc_html_e( 'یقینی بنائیں کہ تمام الفاظ کے ہجے (Spelling) درست ہیں۔', 'urdu-news' ); ?></li>
                    <li><?php esc_html_e( 'مختلف یا زیادہ عمومی الفاظ کے ساتھ تلاش کرنے کی کوشش کریں۔', 'urdu-news' ); ?></li>
                    <li><?php esc_html_e( 'الفاظ کی تعداد کم کریں اور صرف بنیادی موضوع یا نام لکھیں۔', 'urdu-news' ); ?></li>
                    <li><?php esc_html_e( 'تلاش کی اصطلاحات میں مخصوص علامات کا استعمال نہ کریں۔', 'urdu-news' ); ?></li>
                </ul>

                <div class="no-results-categories-section">
                    <h3 class="no-results-cats-title"><?php esc_html_e( 'یا اہم شعبہ جات کی تازہ ترین خبریں ملاحظہ کریں:', 'urdu-news' ); ?></h3>
                    <div class="no-results-cats-chips">
                        <a href="<?php echo esc_url( home_url( '/category/siyasat/' ) ); ?>" class="quick-cat-btn" style="--btn-accent: #1d4ed8;">
                            <span>سیاست و قومی امور</span>
                        </a>
                        <a href="<?php echo esc_url( home_url( '/category/khel/' ) ); ?>" class="quick-cat-btn" style="--btn-accent: #006341;">
                            <span>کھیل اور کرکٹ</span>
                        </a>
                        <a href="<?php echo esc_url( home_url( '/category/karobar/' ) ); ?>" class="quick-cat-btn" style="--btn-accent: #0891b2;">
                            <span>کاروبار و معیشت</span>
                        </a>
                        <a href="<?php echo esc_url( home_url( '/category/technology/' ) ); ?>" class="quick-cat-btn" style="--btn-accent: #7c3aed;">
                            <span>سائنس و ٹیکنالوجی</span>
                        </a>
                        <a href="<?php echo esc_url( home_url( '/category/entertainment/' ) ); ?>" class="quick-cat-btn" style="--btn-accent: #d97706;">
                            <span>تفریح و ثقافت</span>
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div><!-- .archive-main-column -->

    <!-- Sidebar included -->
    <?php get_sidebar(); ?>

</div><!-- .archive-layout-container -->

<?php
get_footer();
