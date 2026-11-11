<?php
/**
 * The main template file
 *
 * @package Urdu_News
 * @version 0.1-trial
 */

get_header();
?>

<?php if ( get_theme_mod( 'urdu_news_enable_ticker', true ) ) : ?>
<!-- Section 3: Breaking Ticker (Red #C8102E badge + horizontally scrolling strip — ticker category ki taaza posts, warna sample headlines) -->
<section class="breaking-ticker" aria-label="<?php esc_attr_e( 'بریکنگ نیوز پٹی', 'urdu-news' ); ?>">
    <div class="breaking-ticker-inner site-container">
        <!-- Red #C8102E badge pinned at the RTL start -->
        <div class="breaking-badge">
            <?php esc_html_e( 'بریکنگ نیوز', 'urdu-news' ); ?>
        </div>

        <!-- Horizontally scrolling strip with keyframe animation, pauses on hover -->
        <div class="ticker-content" tabindex="0">
            <div class="ticker-track">
                <?php
                // Ticker data (v1.0 fix): theme_mod 'urdu_news_ticker_category'
                // (customizer / setup-site jo category deta hai) ki taaza tareen
                // posts; category khaali ho ya koi post na ho to neeche wali
                // sample headlines hi fallback ke tor par chalti hain.
                $un_ticker_items = array();
                $un_ticker_cat   = (int) get_theme_mod( 'urdu_news_ticker_category', 0 );
                if ( $un_ticker_cat > 0 ) {
                    $un_ticker_query = new WP_Query( array(
                        'post_type'           => 'post',
                        'post_status'         => 'publish',
                        'cat'                 => $un_ticker_cat,
                        'posts_per_page'      => 8,
                        'ignore_sticky_posts' => true,
                        'no_found_rows'       => true,
                    ) );
                    foreach ( $un_ticker_query->posts as $un_ticker_post ) {
                        $un_ticker_items[] = array(
                            'title' => get_the_title( $un_ticker_post ),
                            'url'   => get_permalink( $un_ticker_post ),
                        );
                    }
                    wp_reset_postdata();
                }
                if ( empty( $un_ticker_items ) ) {
                    // Demo fallback — 'html' is liye ke in mein bidi-isolate spans hain.
                    $un_ticker_items = array(
                        array( 'html' => 'قومی اسمبلی میں اہم آئینی بل منظور، ملک بھر کے تعلیمی اور عدالتی نظام میں انقلابی اصلاحات کا اعلان' ),
                        array( 'html' => 'عالمی مالیاتی فنڈ کے ساتھ معاشی مذاکرات حتمی مرحلے میں داخل، پاکستان کو <span class="bidi-isolate">1.1</span> ارب ڈالر کی اگلی قسط ملنے کی منظوری' ),
                        array( 'html' => 'آسٹریلیا کے خلاف تاریخی ون ڈے سیریز میں پاکستان کی شاندار فتح، قومی کرکٹ ٹیم نے آخری میچ <span class="bidi-isolate">6</span> وکٹوں سے جیت لیا' ),
                        array( 'html' => 'محکمہ موسمیات کی تازہ ترین ایڈوائزری: بالائی علاقوں میں بارش اور برفباری، درجہ حرارت میں <span class="bidi-isolate">4</span> سے <span class="bidi-isolate">6</span> ڈگری تک کمی کا امکان' ),
                    );
                }
                // Do set (doosra aria-hidden) — seamless loop ke liye marquee ki
                // mojooda sakht bilkul waisi hi rakhi hai.
                for ( $un_set = 0; $un_set < 2; $un_set++ ) :
                    foreach ( $un_ticker_items as $un_item ) :
                        if ( isset( $un_item['url'] ) ) :
                ?>
                <a href="<?php echo esc_url( $un_item['url'] ); ?>" class="ticker-item"<?php echo $un_set ? ' aria-hidden="true"' : ''; ?>>
                    <span><?php echo esc_html( $un_item['title'] ); ?></span>
                </a>
                        <?php else : ?>
                <a href="#" class="ticker-item"<?php echo $un_set ? ' aria-hidden="true"' : ''; ?>>
                    <span><?php echo $un_item['html']; // Sample fallback — andar sirf bidi-isolate spans hain ?></span>
                </a>
                        <?php endif; ?>
                <span class="ticker-bullet" aria-hidden="true">◆</span>
                <?php
                    endforeach;
                endfor;
                ?>
            </div>
    </div>
</section>
<?php endif; ?>

<!-- Section 4: Top Stories Hero (1 + 4 composition) -->
<section class="top-stories-section site-container" aria-label="<?php esc_attr_e( 'اہم خبریں', 'urdu-news' ); ?>">
    <div class="hero-grid">
        <!-- 1. Large Hero Story (16:9 aspect ratio, docked bottom gradient overlay) -->
        <article class="hero-main-story">
            <div class="hero-image-block">
                <!-- Plain CSS gradient block (no external image URLs) -->
                <div class="hero-placeholder-gradient" aria-hidden="true"></div>

                <!-- Headline docked at the bottom over dark gradient overlay -->
                <div class="hero-content-overlay">
                    <span class="hero-category-tag">اہم ترین</span>
                    <h2 class="hero-headline">
                        <a href="#">پاکستان میں معاشی بحالی کا نیا مرحلہ: صنعتی پیداوار میں <span class="bidi-isolate">8.4%</span> کا ریکارڈ اضافہ اور برآمدات کے نئے اہداف مقرر</a>
                    </h2>
                    <div class="hero-meta">
                        <span class="meta-item"><span class="bidi-isolate">25</span> منٹ پہلے</span>
                        <span class="meta-sep" aria-hidden="true">•</span>
                        <span class="meta-item">خصوصی رپورٹ</span>
                    </div>
                </div>
            </div>
        </article>

        <!-- 2. Four Compact Side Stories (Horizontal cards: 4:3 small image block + headline + meta) -->
        <div class="hero-side-stories">
            <!-- Side Story 1: Politics / National -->
            <article class="side-story-card">
                <div class="side-image-block">
                    <div class="side-placeholder-gradient gradient-1" aria-hidden="true"></div>
                </div>
                <div class="side-content-block">
                    <h3 class="side-story-headline">
                        <a href="#">وفاقی کابینہ نے توانائی کے شعبے میں جدید اصلاحات اور بجلی کے ترسیلی نظام کی اپ گریڈیشن کی منظوری دے دی</a>
                    </h3>
                    <div class="side-story-meta">
                        <span class="story-tag">قومی</span>
                        <span class="meta-time"><span class="bidi-isolate">45</span> منٹ پہلے</span>
                    </div>
                </div>
            </article>

            <!-- Side Story 2: Cricket (Uses Green #006341 tag strictly) -->
            <article class="side-story-card">
                <div class="side-image-block">
                    <div class="side-placeholder-gradient gradient-2" aria-hidden="true"></div>
                </div>
                <div class="side-content-block">
                    <h3 class="side-story-headline">
                        <a href="#">قومی کرکٹ ٹیم کی ورلڈ کپ تیاریاں: تربیتی کیمپ میں کھلاڑیوں کی بھرپور پریکٹس اور فزیکل ٹریننگ</a>
                    </h3>
                    <div class="side-story-meta">
                        <span class="story-tag tag-cricket">کرکٹ</span>
                        <span class="meta-time"><span class="bidi-isolate">2</span> گھنٹے پہلے</span>
                    </div>
                </div>
            </article>

            <!-- Side Story 3: Business / Economy -->
            <article class="side-story-card">
                <div class="side-image-block">
                    <div class="side-placeholder-gradient gradient-3" aria-hidden="true"></div>
                </div>
                <div class="side-content-block">
                    <h3 class="side-story-headline">
                        <a href="#">اسٹاک مارکیٹ میں تیزی کا تسلسل: <span class="bidi-isolate">100</span> انڈیکس میں <span class="bidi-isolate">650</span> پوائنٹس کا اضافہ، سرمایہ کاروں کا بھرپور اعتماد</a>
                    </h3>
                    <div class="side-story-meta">
                        <span class="story-tag">تجارت</span>
                        <span class="meta-time"><span class="bidi-isolate">3</span> گھنٹے پہلے</span>
                    </div>
                </div>
            </article>

            <!-- Side Story 4: Weather -->
            <article class="side-story-card">
                <div class="side-image-block">
                    <div class="side-placeholder-gradient gradient-4" aria-hidden="true"></div>
                </div>
                <div class="side-content-block">
                    <h3 class="side-story-headline">
                        <a href="#">ملک کے بالائی علاقوں میں برفباری اور میدانی علاقوں میں شدید دھند کا راج، حد نگاہ انتہائی محدود</a>
                    </h3>
                    <div class="side-story-meta">
                        <span class="story-tag">موسم</span>
                        <span class="meta-time"><span class="bidi-isolate">4</span> گھنٹے پہلے</span>
                    </div>
                </div>
            </article>
        </div>
    </div>
</section>

<?php if ( get_theme_mod( 'urdu_news_enable_financial_strip', true ) ) : ?>
<!-- Section 5: Financial Strip (Slim strip with sample exchange items & commodities) -->
<section class="financial-strip-section site-container" aria-label="<?php esc_attr_e( 'مالیاتی اشاریے اور مارکیٹ ریٹس', 'urdu-news' ); ?>">
    <div class="financial-strip-inner">
        <div class="financial-strip-label">
            <span class="financial-badge-icon" aria-hidden="true">📊</span>
            <span class="financial-title"><?php esc_html_e( 'مارکیٹ ریٹس', 'urdu-news' ); ?></span>
            <span class="financial-tag-sample"><?php esc_html_e( 'تخمینہ', 'urdu-news' ); ?></span>
        </div>
        <div class="financial-items-track">
            <div class="financial-item">
                <span class="finance-item-label">USD / PKR</span>
                <span class="finance-item-val"><span class="bidi-isolate">278.45</span></span>
                <span class="finance-item-trend trend-up" aria-label="<?php esc_attr_e( 'اضافہ', 'urdu-news' ); ?>"><span class="bidi-isolate">+0.15</span> <span aria-hidden="true">▲</span></span>
            </div>
            <div class="finance-divider" aria-hidden="true">|</div>
            <div class="financial-item">
                <span class="finance-item-label">EUR / PKR</span>
                <span class="finance-item-val"><span class="bidi-isolate">302.30</span></span>
                <span class="finance-item-trend trend-up" aria-label="<?php esc_attr_e( 'اضافہ', 'urdu-news' ); ?>"><span class="bidi-isolate">+0.25</span> <span aria-hidden="true">▲</span></span>
            </div>
            <div class="finance-divider" aria-hidden="true">|</div>
            <div class="financial-item">
                <span class="finance-item-label">GBP / PKR</span>
                <span class="finance-item-val"><span class="bidi-isolate">354.80</span></span>
                <span class="finance-item-trend trend-down" aria-label="<?php esc_attr_e( 'کمی', 'urdu-news' ); ?>"><span class="bidi-isolate">-0.10</span> <span aria-hidden="true">▼</span></span>
            </div>
            <div class="finance-divider" aria-hidden="true">|</div>
            <div class="financial-item">
                <span class="finance-item-label"><?php esc_html_e( 'سونا (فی تولہ)', 'urdu-news' ); ?></span>
                <span class="finance-item-val"><span class="bidi-isolate">242,500</span> <?php esc_html_e( 'روپے', 'urdu-news' ); ?></span>
                <span class="finance-item-trend trend-up" aria-label="<?php esc_attr_e( 'اضافہ', 'urdu-news' ); ?>"><span class="bidi-isolate">+500</span> <span aria-hidden="true">▲</span></span>
            </div>
            <div class="finance-divider" aria-hidden="true">|</div>
            <div class="financial-item">
                <span class="finance-item-label"><?php esc_html_e( 'پیٹرول (فی لیٹر)', 'urdu-news' ); ?></span>
                <span class="finance-item-val"><span class="bidi-isolate">275.60</span> <?php esc_html_e( 'روپے', 'urdu-news' ); ?></span>
                <span class="finance-item-trend trend-steady"><span class="bidi-isolate">0.00</span> —</span>
            </div>
            <div class="finance-divider" aria-hidden="true">|</div>
            <div class="financial-item">
                <span class="finance-item-label">PSX-100</span>
                <span class="finance-item-val"><span class="bidi-isolate">81,450</span></span>
                <span class="finance-item-trend trend-up" aria-label="<?php esc_attr_e( 'اضافہ', 'urdu-news' ); ?>"><span class="bidi-isolate">+642</span> <span aria-hidden="true">▲</span></span>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Section 6: Category Sections (Siyasat, Khel, Karobar, Technology, Entertainment) -->
<?php if ( get_theme_mod( 'urdu_news_enable_category_sections', true ) ) : ?>
<?php
$urdu_news_categories = array(
    'siyasat' => array(
        'name'        => 'سیاست',
        'slug'        => 'siyasat',
        'accent'      => '#1d4ed8',
        'class'       => 'cat-siyasat',
        'description' => 'قومی اور بین الاقوامی سیاسی پیش رفت',
        'samples'     => array(
            array(
                'title'     => 'قومی ترجیحات اور نئی پالیسی فریم ورک: پارلیمانی کمیٹی نے عوامی فلاحی منصوبوں کی رفتار تیز کرنے کی سفارش کر دی',
                'url'       => '#',
                'thumbnail' => '',
                'excerpt'   => 'وفاقی و صوبائی سطح پر تعاون کو فروغ دینے اور بنیادی عوامی سہولیات کی فراہمی کے لیے نئے ترقیاتی اقدامات کا جامع جائزہ لیا گیا۔',
                'time'      => '30 منٹ پہلے',
                'is_sample' => true,
            ),
            array(
                'title'     => 'صوبائی اسمبلی میں بلدیاتی نظام کو بااختیار بنانے کا ترمیمی مسودہ قانون غور کے لیے پیش',
                'url'       => '#',
                'thumbnail' => '',
                'excerpt'   => '',
                'time'      => '1 گھنٹہ پہلے',
                'is_sample' => true,
            ),
            array(
                'title'     => 'انتخابی فہرستوں کی تجدید کا کام مکمل، شفافیت کو یقینی بنانے کے لیے ڈیجیٹل سسٹم فعال',
                'url'       => '#',
                'thumbnail' => '',
                'excerpt'   => '',
                'time'      => '2 گھنٹے پہلے',
                'is_sample' => true,
            ),
            array(
                'title'     => 'شہری سہولیات کی فراہمی کے لیے بین المحکماتی کوآرڈینیشن کمیٹی کا پہلا مشاورتی اجلاس',
                'url'       => '#',
                'thumbnail' => '',
                'excerpt'   => '',
                'time'      => '3 گھنٹے پہلے',
                'is_sample' => true,
            ),
        ),
    ),
    'khel' => array(
        'name'        => 'کھیل',
        'slug'        => 'khel',
        'accent'      => '#006341',
        'class'       => 'cat-khel',
        'description' => 'کرکٹ، ہاکی اور دیگر کھیلوں کے مقابلے',
        'samples'     => array(
            array(
                'title'     => 'قومی کرکٹ چیمپئن شپ کا فائنل معرکہ: نوجوان کھلاڑیوں کی شاندار کارکردگی اور نئے ٹیلنٹ کے ریکارڈز',
                'url'       => '#',
                'thumbnail' => '',
                'excerpt'   => 'ڈومیسٹک سیزن کے فیصلہ کن مقابلے میں دونوں ٹیموں کے درمیان سخت مقابلہ، شائقین کرکٹ کی بڑی تعداد اسٹیڈیم میں موجود۔',
                'time'      => '45 منٹ پہلے',
                'is_sample' => true,
            ),
            array(
                'title'     => 'عالمی ہاکی ایونٹ کے لیے قومی کھلاڑیوں کا تربیتی کیمپ دوسرے ہفتے میں داخل',
                'url'       => '#',
                'thumbnail' => '',
                'excerpt'   => '',
                'time'      => '2 گھنٹے پہلے',
                'is_sample' => true,
            ),
            array(
                'title'     => 'ایشین ایتھلیٹکس چیمپئن شپ میں جیولین تھرو مقابلے کے لیے قومی ایتھلیٹ کی بھرپور تیاریاں',
                'url'       => '#',
                'thumbnail' => '',
                'excerpt'   => '',
                'time'      => '3 گھنٹے پہلے',
                'is_sample' => true,
            ),
            array(
                'title'     => 'قومی ٹی ٹوئنٹی ٹورنامنٹ: راولپنڈی اور پشاور کے درمیان فائنل کل کھیلا جائے گا',
                'url'       => '#',
                'thumbnail' => '',
                'excerpt'   => '',
                'time'      => '5 گھنٹے پہلے',
                'is_sample' => true,
            ),
        ),
    ),
    'karobar' => array(
        'name'        => 'کاروبار',
        'slug'        => 'karobar',
        'accent'      => '#0891b2',
        'class'       => 'cat-karobar',
        'description' => 'معیشت، تجارت، زر مبادلہ اور سرمایہ کاری',
        'samples'     => array(
            array(
                'title'     => 'اسٹاک مارکیٹ اور بیرونی تجارت میں بہتری: ترسیلاتِ زر میں <span class="bidi-isolate">12%</span> کا اضافہ اور صنعتی ترقی کے نئے اہداف',
                'url'       => '#',
                'thumbnail' => '',
                'excerpt'   => 'مرکزی بینک کے مطابق برآمدی شعبے کو مراعات کی فراہمی سے تجارتی خسارے میں نمایاں کمی اور سرمایہ کاروں کا اعتماد بحال ہوا ہے۔',
                'time'      => '1 گھنٹہ پہلے',
                'is_sample' => true,
            ),
            array(
                'title'     => 'زرعی اجناس کی برآمدات میں اضافہ، کاٹن اور باسمتی چاول کی ملکی منڈیوں میں استحکام',
                'url'       => '#',
                'thumbnail' => '',
                'excerpt'   => '',
                'time'      => '2 گھنٹے پہلے',
                'is_sample' => true,
            ),
            array(
                'title'     => 'چھوٹے اور درمیانے درجے کے کاروباری اداروں <span class="bidi-isolate">(SME)</span> کے لیے آسان شرائط پر نئے قرضوں کا اجراء',
                'url'       => '#',
                'thumbnail' => '',
                'excerpt'   => '',
                'time'      => '4 گھنٹے پہلے',
                'is_sample' => true,
            ),
            array(
                'title'     => 'روپے کے مقابلے میں ڈالر کی قیمت مستحکم، غیر ملکی زر مبادلہ کے ذخائر میں بہتری کی نوید',
                'url'       => '#',
                'thumbnail' => '',
                'excerpt'   => '',
                'time'      => '6 گھنٹے پہلے',
                'is_sample' => true,
            ),
        ),
    ),
    'technology' => array(
        'name'        => 'ٹیکنالوجی',
        'slug'        => 'technology',
        'accent'      => '#7c3aed',
        'class'       => 'cat-technology',
        'description' => 'آئی ٹی، ڈیجیٹل سروسز اور مصنوعی ذہانت',
        'samples'     => array(
            array(
                'title'     => 'پاکستان میں ڈیجیٹل معیشت کا فروغ: انفارمیشن ٹیکنالوجی کے برآمدی حجم میں ریکارڈ سنگ میل عبور',
                'url'       => '#',
                'thumbnail' => '',
                'excerpt'   => 'نوجوان فری لانسرز اور سافٹ ویئر ڈویلپرز کی بڑھتی ہوئی تعداد سے ملکی آئی ٹی خدمات کی عالمی مارکیٹ میں مانگ میں اضافہ ہوا۔',
                'time'      => '2 گھنٹے پہلے',
                'is_sample' => true,
            ),
            array(
                'title'     => 'ملک بھر میں تیز رفتار <span class="bidi-isolate">5G</span> اسپیکٹرم کی نیلامی کے حتمی مراحل کی تیاری مکمل',
                'url'       => '#',
                'thumbnail' => '',
                'excerpt'   => '',
                'time'      => '3 گھنٹے پہلے',
                'is_sample' => true,
            ),
            array(
                'title'     => 'مصنوعی ذہانت اور کلاؤڈ کمپیوٹنگ کے شعبے میں طلبہ کے لیے قومی تربیتی اسکیم کا آغاز',
                'url'       => '#',
                'thumbnail' => '',
                'excerpt'   => '',
                'time'      => '5 گھنٹے پہلے',
                'is_sample' => true,
            ),
            array(
                'title'     => 'مقامی طور پر تیار کردہ موبائل فنانشل ایپ کو بین الاقوامی سائبر سیکیورٹی ایوارڈ سے نوازا گیا',
                'url'       => '#',
                'thumbnail' => '',
                'excerpt'   => '',
                'time'      => '7 گھنٹے پہلے',
                'is_sample' => true,
            ),
        ),
    ),
    'entertainment' => array(
        'name'        => 'تفریح',
        'slug'        => 'entertainment',
        'accent'      => '#d97706',
        'class'       => 'cat-entertainment',
        'description' => 'فلم، ٹی وی، موسیقی اور ثقافتی سرگرمیاں',
        'samples'     => array(
            array(
                'title'     => 'قومی فلم انڈسٹری کی نئی پیش رفت: بین الاقوامی فلم فیسٹیول میں پاکستانی تخلیقات کی شاندار پذیرائی',
                'url'       => '#',
                'thumbnail' => '',
                'excerpt'   => 'مقامی سینما گھروں کی بحالی اور منفرد موضوعات پر مبنی دستاویزی فلموں نے فلم بینوں اور بین الاقوامی ناقدین کے دل جیت لیے۔',
                'time'      => '3 گھنٹے پہلے',
                'is_sample' => true,
            ),
            array(
                'title'     => 'لاہور کے تاریخی الحمرا ہال میں تین روزہ روایتی صوفی میوزک اور ثقافتی میلے کا شاندار آغاز',
                'url'       => '#',
                'thumbnail' => '',
                'excerpt'   => '',
                'time'      => '4 گھنٹے پہلے',
                'is_sample' => true,
            ),
            array(
                'title'     => 'قومی ورثے اور روایتی لوک فنون کے تحفظ کے لیے نئے نمائش ہال کا افتتاح کر دیا گیا',
                'url'       => '#',
                'thumbnail' => '',
                'excerpt'   => '',
                'time'      => '6 گھنٹے پہلے',
                'is_sample' => true,
            ),
            array(
                'title'     => 'ڈرامہ نگاری اور اسٹیج تھیٹر کی ترویج کے لیے سالانہ ایوارڈز تقریب کی تاریخ کا باضابطہ اعلان',
                'url'       => '#',
                'thumbnail' => '',
                'excerpt'   => '',
                'time'      => '8 گھنٹے پہلے',
                'is_sample' => true,
            ),
        ),
    ),
);

foreach ( $urdu_news_categories as $cat_key => $cat_data ) :
    $cat_stories = urdu_news_get_category_stories( $cat_data['slug'], $cat_data['samples'], 4 );
    $medium_story = isset( $cat_stories[0] ) ? $cat_stories[0] : null;
    $small_stories = array_slice( $cat_stories, 1, 3 );
?>
<section class="category-section site-container <?php echo esc_attr( $cat_data['class'] ); ?>" aria-label="<?php echo esc_attr( $cat_data['name'] ); ?>">
    <!-- Section Header Band (Nastaliq title + accent label + more link) -->
    <div class="category-header-band">
        <div class="category-header-start">
            <span class="category-accent-pill" style="background-color: <?php echo esc_attr( $cat_data['accent'] ); ?>;" aria-hidden="true"></span>
            <h2 class="category-title"><?php echo esc_html( $cat_data['name'] ); ?></h2>
            <span class="category-desc-note"><?php echo esc_html( $cat_data['description'] ); ?></span>
        </div>
        <div class="category-header-end">
            <a href="<?php echo esc_url( home_url( '/category/' . $cat_data['slug'] . '/' ) ); ?>" class="category-more-link">
                <?php esc_html_e( 'مزید خبریں', 'urdu-news' ); ?>
                <span class="more-arrow" aria-hidden="true">←</span>
            </a>
        </div>
    </div>

    <!-- Category Grid: 1 Medium Story + 3 Small Stories -->
    <div class="category-grid">
        <?php if ( $medium_story ) : ?>
        <article class="category-medium-story">
            <div class="cat-medium-thumb-wrap">
                <?php if ( ! empty( $medium_story['thumbnail'] ) ) : ?>
                    <img src="<?php echo esc_url( $medium_story['thumbnail'] ); ?>" alt="<?php echo esc_attr( wp_strip_all_tags( $medium_story['title'] ) ); ?>" class="cat-medium-img" loading="lazy">
                <?php else : ?>
                    <div class="cat-medium-placeholder-gradient" style="background: linear-gradient(145deg, #1e293b 0%, #334155 100%);" aria-hidden="true"></div>
                <?php endif; ?>
                <span class="cat-story-tag" style="background-color: <?php echo esc_attr( $cat_data['accent'] ); ?>;"><?php echo esc_html( $cat_data['name'] ); ?></span>
            </div>
            <div class="cat-medium-content">
                <h3 class="cat-medium-headline">
                    <a href="<?php echo esc_url( $medium_story['url'] ); ?>"><?php echo wp_kses_post( $medium_story['title'] ); ?></a>
                </h3>
                <?php if ( ! empty( $medium_story['excerpt'] ) ) : ?>
                    <p class="cat-medium-excerpt"><?php echo wp_kses_post( $medium_story['excerpt'] ); ?></p>
                <?php endif; ?>
                <div class="cat-story-meta">
                    <span class="cat-meta-time"><span class="bidi-isolate"><?php echo wp_kses_post( $medium_story['time'] ); ?></span></span>
                </div>
            </div>
        </article>
        <?php endif; ?>

        <div class="category-small-stories">
            <?php foreach ( $small_stories as $s_idx => $s_story ) : ?>
            <article class="category-small-card">
                <div class="cat-small-thumb-wrap">
                    <?php if ( ! empty( $s_story['thumbnail'] ) ) : ?>
                        <img src="<?php echo esc_url( $s_story['thumbnail'] ); ?>" alt="<?php echo esc_attr( wp_strip_all_tags( $s_story['title'] ) ); ?>" class="cat-small-img" loading="lazy">
                    <?php else : ?>
                        <div class="cat-small-placeholder-gradient gradient-sub-<?php echo esc_attr( ( $s_idx % 3 ) + 1 ); ?>" aria-hidden="true"></div>
                    <?php endif; ?>
                </div>
                <div class="cat-small-content">
                    <h4 class="cat-small-headline">
                        <a href="<?php echo esc_url( $s_story['url'] ); ?>"><?php echo wp_kses_post( $s_story['title'] ); ?></a>
                    </h4>
                    <div class="cat-story-meta">
                        <span class="cat-meta-time"><span class="bidi-isolate"><?php echo wp_kses_post( $s_story['time'] ); ?></span></span>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endforeach; ?>
<?php endif; ?>

<?php if ( get_theme_mod( 'urdu_news_enable_video_section', true ) ) : ?>
<!-- Section 7: Video News Section (The ONE dark band of the page) -->
<section class="video-news-section" aria-label="<?php esc_attr_e( 'ویڈیو خبریں', 'urdu-news' ); ?>">
    <div class="site-container">
        <div class="video-section-header">
            <div class="video-header-start">
                <span class="video-section-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17 10.5V7c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h12c.55 0 1-.45 1-1v-3.5l4 4v-11l-4 4z"/></svg>
                </span>
                <h2 class="video-section-title"><?php esc_html_e( 'ویڈیو خبریں', 'urdu-news' ); ?></h2>
                <span class="video-desc-note"><?php esc_html_e( 'اہم واقعات کے تصویری مناظر اور دستاویزی ویڈیوز', 'urdu-news' ); ?></span>
            </div>
            <a href="#" class="video-more-link">
                <?php esc_html_e( 'تمام ویڈیوز', 'urdu-news' ); ?>
                <span class="more-arrow" aria-hidden="true">←</span>
            </a>
        </div>

        <div class="video-cards-grid">
            <!-- Video Card 1 -->
            <article class="video-card" data-embed-url="https://www.youtube-nocookie.com/embed/placeholder1">
                <div class="video-thumb-container">
                    <div class="video-thumb-placeholder video-bg-1" aria-hidden="true"></div>
                    <button type="button" class="video-play-btn" aria-label="<?php esc_attr_e( 'ویڈیو چلائیں: دستاویزی رپورٹ', 'urdu-news' ); ?>">
                        <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M8 5v14l11-7z" fill="currentColor"/></svg>
                    </button>
                    <span class="video-duration bidi-isolate">05:32</span>
                </div>
                <div class="video-card-body">
                    <span class="video-category-tag"><?php esc_html_e( 'دستاویزی رپورٹ', 'urdu-news' ); ?></span>
                    <h3 class="video-card-title">
                        <a href="#"><?php esc_html_e( 'پاکستان کے قدرتی وسائل اور متبادل توانائی کے نئے تاریخی منصوبوں پر خصوصی رپورٹ', 'urdu-news' ); ?></a>
                    </h3>
                    <span class="video-card-time"><span class="bidi-isolate">2</span> <?php esc_html_e( 'گھنٹے پہلے', 'urdu-news' ); ?></span>
                </div>
            </article>

            <!-- Video Card 2 -->
            <article class="video-card" data-embed-url="https://www.youtube-nocookie.com/embed/placeholder2">
                <div class="video-thumb-container">
                    <div class="video-thumb-placeholder video-bg-2" aria-hidden="true"></div>
                    <button type="button" class="video-play-btn" aria-label="<?php esc_attr_e( 'ویڈیو چلائیں: کھیل خصوصی', 'urdu-news' ); ?>">
                        <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M8 5v14l11-7z" fill="currentColor"/></svg>
                    </button>
                    <span class="video-duration bidi-isolate">03:45</span>
                </div>
                <div class="video-card-body">
                    <span class="video-category-tag"><?php esc_html_e( 'کھیل خصوصی', 'urdu-news' ); ?></span>
                    <h3 class="video-card-title">
                        <a href="#"><?php esc_html_e( 'قومی کرکٹ ٹیم کی ورلڈ کپ ٹریننگ: کوچنگ کیمپ اور کھلاڑیوں کی مشقوں کے مناظر', 'urdu-news' ); ?></a>
                    </h3>
                    <span class="video-card-time"><span class="bidi-isolate">4</span> <?php esc_html_e( 'گھنٹے پہلے', 'urdu-news' ); ?></span>
                </div>
            </article>

            <!-- Video Card 3 -->
            <article class="video-card" data-embed-url="https://www.youtube-nocookie.com/embed/placeholder3">
                <div class="video-thumb-container">
                    <div class="video-thumb-placeholder video-bg-3" aria-hidden="true"></div>
                    <button type="button" class="video-play-btn" aria-label="<?php esc_attr_e( 'ویڈیو چلائیں: ٹیک ڈائجسٹ', 'urdu-news' ); ?>">
                        <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M8 5v14l11-7z" fill="currentColor"/></svg>
                    </button>
                    <span class="video-duration bidi-isolate">04:10</span>
                </div>
                <div class="video-card-body">
                    <span class="video-category-tag"><?php esc_html_e( 'ٹیک ڈائجسٹ', 'urdu-news' ); ?></span>
                    <h3 class="video-card-title">
                        <a href="#"><?php esc_html_e( 'جدید مصنوعی ذہانت اور روبوٹکس کے ہمارے روزمرہ طرزِ زندگی پر حیران کن اثرات', 'urdu-news' ); ?></a>
                    </h3>
                    <span class="video-card-time"><span class="bidi-isolate">6</span> <?php esc_html_e( 'گھنٹے پہلے', 'urdu-news' ); ?></span>
                </div>
            </article>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ( get_theme_mod( 'urdu_news_enable_tabbed_rail', true ) ) : ?>
<!-- Section 8: Popular / Latest Tabbed Rail (Vanilla-JS tab switch, 5 numbered headlines per tab) -->
<?php
$popular_samples = array(
    array(
        'title' => 'قومی معیشت میں برآمدات کا حجم بڑھانے کے لیے جدید صنعتی زونز کے قیام کا باقاعدہ آغاز',
        'url'   => '#',
        'time'  => '1 گھنٹہ پہلے',
    ),
    array(
        'title' => 'وفاقی تعلیمی بورڈ کا نیا امتحانی ماڈل متعارف: رٹہ سسٹم کے خاتمے اور فکری صلاحیتوں پر زور',
        'url'   => '#',
        'time'  => '2 گھنٹے پہلے',
    ),
    array(
        'title' => 'قومی شاہراہوں کی مرمت اور موٹروے نیٹ ورک کی توسیع کے لیے کثیر ملکی فنڈنگ منظور',
        'url'   => '#',
        'time'  => '3 گھنٹے پہلے',
    ),
    array(
        'title' => 'ملک کے مختلف شہروں میں موسمی بارشیں، کسانوں کے لیے گندم کی فصل کے حوالے سے مفید قرار',
        'url'   => '#',
        'time'  => '5 گھنٹے پہلے',
    ),
    array(
        'title' => 'شہر قائد میں پینے کے صاف پانی کی فراہمی کے بڑے میگا پروجیکٹ کے اگلے مرحلے پر کام تیز',
        'url'   => '#',
        'time'  => '7 گھنٹے پہلے',
    ),
);

$latest_samples = array(
    array(
        'title' => 'محکمہ موسمیات: آئندہ <span class="bidi-isolate">24</span> گھنٹوں کے دوران بالائی علاقوں میں موسم خشک اور سرد رہنے کی پیشگوئی',
        'url'   => '#',
        'time'  => '15 منٹ پہلے',
    ),
    array(
        'title' => 'اسٹاک ایکسچینج میں کاروباری دن کے اختتام پر انڈیکس میں مثبت رجحان، حجم میں اضافہ',
        'url'   => '#',
        'time'  => '35 منٹ پہلے',
    ),
    array(
        'title' => 'قومی ریلوے کا مسافر ٹرینوں کے اوقات کار میں بہتری اور ڈیجیٹل ٹکٹنگ کے نفاذ کا فیصلہ',
        'url'   => '#',
        'time'  => '50 منٹ پہلے',
    ),
    array(
        'title' => 'ضلعی انتظامیہ کی اشیائے خورونوش کی قیمتوں پر کنٹرول کے لیے پرائس مجسٹریٹس کو خصوصی ہدایات',
        'url'   => '#',
        'time'  => '1 گھنٹہ پہلے',
    ),
    array(
        'title' => 'صحت عامہ: ملک بھر کے مراکز میں بنیادی حفاظتی ٹیکہ جات کی مہم کامیابی سے جاری',
        'url'   => '#',
        'time'  => '2 گھنٹے پہلے',
    ),
);

$popular_stories = urdu_news_get_tabbed_stories( 'popular', $popular_samples );
$latest_stories  = urdu_news_get_tabbed_stories( 'latest', $latest_samples );
?>
<section class="popular-latest-section site-container" aria-label="<?php esc_attr_e( 'مقبول اور تازہ ترین خبریں', 'urdu-news' ); ?>">
    <div class="tabbed-rail-header">
        <div class="tabbed-rail-tabs" role="tablist" aria-label="<?php esc_attr_e( 'خبروں کی درجہ بندی', 'urdu-news' ); ?>">
            <button type="button" class="rail-tab-btn is-active" id="tab-popular" role="tab" aria-selected="true" aria-controls="panel-popular" data-tab="popular">
                <span class="tab-indicator-icon" aria-hidden="true">🔥</span>
                <span><?php esc_html_e( 'سب سے زیادہ پڑھی جانے والی', 'urdu-news' ); ?></span>
            </button>
            <button type="button" class="rail-tab-btn" id="tab-latest" role="tab" aria-selected="false" aria-controls="panel-latest" data-tab="latest">
                <span class="tab-indicator-icon" aria-hidden="true">⏱️</span>
                <span><?php esc_html_e( 'تازہ ترین خبریں', 'urdu-news' ); ?></span>
            </button>
        </div>
    </div>

    <!-- Panel 1: Most Read (Popular) -->
    <div id="panel-popular" class="rail-tab-panel is-active" role="tabpanel" aria-labelledby="tab-popular">
        <ol class="rail-story-list">
            <?php foreach ( $popular_stories as $p_idx => $p_item ) : ?>
            <li class="rail-story-item">
                <span class="rail-rank-badge bidi-isolate"><?php echo esc_html( $p_idx + 1 ); ?></span>
                <div class="rail-story-content">
                    <h3 class="rail-story-title">
                        <a href="<?php echo esc_url( $p_item['url'] ); ?>"><?php echo wp_kses_post( $p_item['title'] ); ?></a>
                    </h3>
                    <span class="rail-story-time"><span class="bidi-isolate"><?php echo wp_kses_post( $p_item['time'] ); ?></span></span>
                </div>
            </li>
            <?php endforeach; ?>
        </ol>
    </div>

    <!-- Panel 2: Latest -->
    <div id="panel-latest" class="rail-tab-panel" role="tabpanel" aria-labelledby="tab-latest" hidden>
        <ol class="rail-story-list">
            <?php foreach ( $latest_stories as $l_idx => $l_item ) : ?>
            <li class="rail-story-item">
                <span class="rail-rank-badge bidi-isolate"><?php echo esc_html( $l_idx + 1 ); ?></span>
                <div class="rail-story-content">
                    <h3 class="rail-story-title">
                        <a href="<?php echo esc_url( $l_item['url'] ); ?>"><?php echo wp_kses_post( $l_item['title'] ); ?></a>
                    </h3>
                    <span class="rail-story-time"><span class="bidi-isolate"><?php echo wp_kses_post( $l_item['time'] ); ?></span></span>
                </div>
            </li>
            <?php endforeach; ?>
        </ol>
    </div>
</section>
<?php endif; ?>

<?php if ( get_theme_mod( 'urdu_news_enable_opinion', true ) ) : ?>
<!-- Section 9: Opinion Section (Columnist cards: round avatar placeholder, name, column title, excerpt) -->
<section class="opinion-section site-container" aria-label="<?php esc_attr_e( 'کالم و آراء', 'urdu-news' ); ?>">
    <div class="section-heading-band">
        <div class="heading-start">
            <span class="heading-accent-bar" style="background-color: #22303f;" aria-hidden="true"></span>
            <h2 class="section-title"><?php esc_html_e( 'کالم و آراء', 'urdu-news' ); ?></h2>
            <span class="section-subtitle"><?php esc_html_e( 'فکری مباحث، تجزیے اور ممتاز دانشوروں کے خیالات', 'urdu-news' ); ?></span>
        </div>
        <a href="#" class="section-more-link">
            <?php esc_html_e( 'تمام کالم نگار', 'urdu-news' ); ?>
            <span class="more-arrow" aria-hidden="true">←</span>
        </a>
    </div>

    <div class="opinion-cards-grid">
        <!-- Opinion Card 1 -->
        <article class="opinion-card">
            <div class="opinion-author-header">
                <div class="opinion-avatar-placeholder avatar-grad-1" aria-hidden="true">
                    <span>ام</span>
                </div>
                <div class="opinion-author-info">
                    <h3 class="opinion-author-name">ڈاکٹر ارشد محمود</h3>
                    <span class="opinion-author-role"><?php esc_html_e( 'سینئر تجزیہ کار و استاد', 'urdu-news' ); ?></span>
                </div>
            </div>
            <h4 class="opinion-column-title">
                <a href="#"><?php esc_html_e( 'قومی معیشت کے بنیادی ستون اور پیداواری اصلاحات کا ناگزیر راستہ', 'urdu-news' ); ?></a>
            </h4>
            <p class="opinion-excerpt">
                <?php esc_html_e( 'صنعتی ترقی اور خود انحصاری کے حصول کے لیے ضروری ہے کہ ہم مقامی وسائل کو ترجیح دیں اور بنیادی مینوفیکچرنگ کی لاگت کم کرنے کی جامع منصوبہ بندی کریں۔', 'urdu-news' ); ?>
            </p>
            <div class="opinion-meta">
                <span class="meta-read-time"><span class="bidi-isolate">5</span> <?php esc_html_e( 'منٹ کا مطالعہ', 'urdu-news' ); ?></span>
            </div>
        </article>

        <!-- Opinion Card 2 -->
        <article class="opinion-card">
            <div class="opinion-author-header">
                <div class="opinion-avatar-placeholder avatar-grad-2" aria-hidden="true">
                    <span>زن</span>
                </div>
                <div class="opinion-author-info">
                    <h3 class="opinion-author-name">سیدہ زہرہ نقوی</h3>
                    <span class="opinion-author-role"><?php esc_html_e( 'ماہر سماجیات و قلم کار', 'urdu-news' ); ?></span>
                </div>
            </div>
            <h4 class="opinion-column-title">
                <a href="#"><?php esc_html_e( 'جدید تعلیم، ڈیجیٹل ہنر اور نئی نسل کے لیے مساوی مواقع کی فراہمی', 'urdu-news' ); ?></a>
            </h4>
            <p class="opinion-excerpt">
                <?php esc_html_e( 'تعلیمی اداروں میں جدید ترین نصاب اور سائنسی تحقیق پر مبنی تدریس کے بغیر ہم ایک بااختیار اور باوقار علمی معاشرے کے خواب کو تعبیر نہیں دے سکتے۔', 'urdu-news' ); ?>
            </p>
            <div class="opinion-meta">
                <span class="meta-read-time"><span class="bidi-isolate">4</span> <?php esc_html_e( 'منٹ کا مطالعہ', 'urdu-news' ); ?></span>
            </div>
        </article>

        <!-- Opinion Card 3 -->
        <article class="opinion-card">
            <div class="opinion-author-header">
                <div class="opinion-avatar-placeholder avatar-grad-3" aria-hidden="true">
                    <span>طب</span>
                </div>
                <div class="opinion-author-info">
                    <h3 class="opinion-author-name">طارق فاروق بخاری</h3>
                    <span class="opinion-author-role"><?php esc_html_e( 'ماحولیاتی پالیسی ریسرچر', 'urdu-news' ); ?></span>
                </div>
            </div>
            <h4 class="opinion-column-title">
                <a href="#"><?php esc_html_e( 'موسمیاتی چیلنجز اور ماحول دوست ترقیاتی منصوبوں کی فوری ضرورت', 'urdu-news' ); ?></a>
            </h4>
            <p class="opinion-excerpt">
                <?php esc_html_e( 'ماحولیاتی تحفظ اور قدرتی آفات کے منفی اثرات سے شہریوں کو محفوظ رکھنے کے لیے ایک پائیدار، شفاف اور سنجیدہ قومی حکمتِ عملی مرتب کرنا ناگزیر ہے۔', 'urdu-news' ); ?>
            </p>
            <div class="opinion-meta">
                <span class="meta-read-time"><span class="bidi-isolate">6</span> <?php esc_html_e( 'منٹ کا مطالعہ', 'urdu-news' ); ?></span>
            </div>
        </article>

        <!-- Opinion Card 4 -->
        <article class="opinion-card">
            <div class="opinion-author-header">
                <div class="opinion-avatar-placeholder avatar-grad-4" aria-hidden="true">
                    <span>حخ</span>
                </div>
                <div class="opinion-author-info">
                    <h3 class="opinion-author-name">پروفیسر حمید خان</h3>
                    <span class="opinion-author-role"><?php esc_html_e( 'مورخ اور کالم نگار', 'urdu-news' ); ?></span>
                </div>
            </div>
            <h4 class="opinion-column-title">
                <a href="#"><?php esc_html_e( 'تاریخ کے دریچے: ہمارے معاشرتی رویے اور رواداری کی قدیم اقدار', 'urdu-news' ); ?></a>
            </h4>
            <p class="opinion-excerpt">
                <?php esc_html_e( 'قومی تاریخ اور ثقافتی ورثے کا سنجیدہ مطالعہ ہمیں یاد دلاتا ہے کہ مکالمہ، باہمی احترام اور برداشت ہی کسی بھی مہذب معاشرے کی اصل اساس ہیں۔', 'urdu-news' ); ?>
            </p>
            <div class="opinion-meta">
                <span class="meta-read-time"><span class="bidi-isolate">4</span> <?php esc_html_e( 'منٹ کا مطالعہ', 'urdu-news' ); ?></span>
            </div>
        </article>
    </div>
</section>
<?php endif; ?>

<?php if ( get_theme_mod( 'urdu_news_enable_live_strip', true ) ) : ?>
<!-- Section 10: Live TV / Live Blog Strip (Red #C8102E LIVE dot + embed-ready slot) -->
<section class="live-stream-section site-container" aria-label="<?php esc_attr_e( 'براہِ راست نشریات', 'urdu-news' ); ?>">
    <div class="live-stream-wrapper">
        <div class="live-stream-top-bar">
            <div class="live-indicator-wrap">
                <span class="live-dot" aria-hidden="true"></span>
                <span class="live-title-badge"><?php esc_html_e( 'براہِ راست نشریات', 'urdu-news' ); ?> <span class="bidi-isolate">(Live TV)</span></span>
            </div>
            <div class="live-subtext"><?php esc_html_e( '24 گھنٹے براہِ راست نشریات اور خصوصی لائیو کوریج', 'urdu-news' ); ?></div>
        </div>

        <div class="live-stream-player-slot" id="live-stream-embed-container" data-embed-url="">
            <div class="live-player-placeholder">
                <div class="live-player-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="7" width="20" height="15" rx="2" ry="2"></rect>
                        <polyline points="17 2 12 7 7 2"></polyline>
                    </svg>
                </div>
                <div class="live-player-badge">
                    <span class="live-dot" aria-hidden="true"></span>
                    <span><?php esc_html_e( 'براہِ راست اسٹریمنگ سلاٹ', 'urdu-news' ); ?></span>
                </div>
                <h3 class="live-player-heading"><?php esc_html_e( 'اردو نیوز کی لائیو اسٹریمنگ یہاں نشر کی جائے گی', 'urdu-news' ); ?></h3>
                <p class="live-player-desc"><?php esc_html_e( 'یہ سلاٹ کسٹمائزر یا ویڈیو فیڈ کے ذریعے خودکار طور پر براہِ راست اسٹریمنگ پلیئر سے تبدیل ہو جائے گا۔', 'urdu-news' ); ?></p>
                <div class="live-player-action">
                    <span class="live-status-pill"><?php esc_html_e( 'سگنل: آن لائن', 'urdu-news' ); ?></span>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ( get_theme_mod( 'urdu_news_enable_gallery', true ) ) : ?>
<!-- Section 11: Image Gallery Strip (Horizontal row of 5 image cards with captions) -->
<section class="image-gallery-section site-container" aria-label="<?php esc_attr_e( 'تصویری کہانیاں', 'urdu-news' ); ?>">
    <div class="section-heading-band">
        <div class="heading-start">
            <span class="heading-accent-bar" style="background-color: #0284c7;" aria-hidden="true"></span>
            <h2 class="section-title"><?php esc_html_e( 'تصویری کہانیاں', 'urdu-news' ); ?></h2>
            <span class="section-subtitle"><?php esc_html_e( 'ملک و قوم اور دنیا بھر کے دلکش مناظر تصاویر کے آئینے میں', 'urdu-news' ); ?></span>
        </div>
        <a href="#" class="section-more-link">
            <?php esc_html_e( 'تمام گیلریز', 'urdu-news' ); ?>
            <span class="more-arrow" aria-hidden="true">←</span>
        </a>
    </div>

    <div class="image-gallery-grid">
        <!-- Photo Card 1 -->
        <article class="gallery-card">
            <div class="gallery-thumb-wrap">
                <div class="gallery-placeholder-gradient gal-grad-1" aria-hidden="true"></div>
                <span class="gallery-counter">
                    <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M12 12m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0" fill="none" stroke="currentColor" stroke-width="2"/><path d="M5 7h1a2 2 0 0 0 2 -2a1 1 0 0 1 1 -1h6a1 1 0 0 1 1 1a2 2 0 0 0 2 2h1a2 2 0 0 1 2 2v9a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-9a2 2 0 0 1 2 -2" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                    <span><span class="bidi-isolate">8</span> <?php esc_html_e( 'تصاویر', 'urdu-news' ); ?></span>
                </span>
            </div>
            <h3 class="gallery-card-title">
                <a href="#"><?php esc_html_e( 'شمالی علاقہ جات میں موسم سرما کی پہلی برفباری: وادی ہنزہ اور اسکردو کے دلکش مناظر', 'urdu-news' ); ?></a>
            </h3>
        </article>

        <!-- Photo Card 2 -->
        <article class="gallery-card">
            <div class="gallery-thumb-wrap">
                <div class="gallery-placeholder-gradient gal-grad-2" aria-hidden="true"></div>
                <span class="gallery-counter">
                    <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M12 12m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0" fill="none" stroke="currentColor" stroke-width="2"/><path d="M5 7h1a2 2 0 0 0 2 -2a1 1 0 0 1 1 -1h6a1 1 0 0 1 1 1a2 2 0 0 0 2 2h1a2 2 0 0 1 2 2v9a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-9a2 2 0 0 1 2 -2" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                    <span><span class="bidi-isolate">6</span> <?php esc_html_e( 'تصاویر', 'urdu-news' ); ?></span>
                </span>
            </div>
            <h3 class="gallery-card-title">
                <a href="#"><?php esc_html_e( 'قومی کھیلوں کے سنسنی خیز فائنل مقابلے: جوش و جذبے سے لبریز لمحات کا احاطہ', 'urdu-news' ); ?></a>
            </h3>
        </article>

        <!-- Photo Card 3 -->
        <article class="gallery-card">
            <div class="gallery-thumb-wrap">
                <div class="gallery-placeholder-gradient gal-grad-3" aria-hidden="true"></div>
                <span class="gallery-counter">
                    <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M12 12m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0" fill="none" stroke="currentColor" stroke-width="2"/><path d="M5 7h1a2 2 0 0 0 2 -2a1 1 0 0 1 1 -1h6a1 1 0 0 1 1 1a2 2 0 0 0 2 2h1a2 2 0 0 1 2 2v9a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-9a2 2 0 0 1 2 -2" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                    <span><span class="bidi-isolate">10</span> <?php esc_html_e( 'تصاویر', 'urdu-news' ); ?></span>
                </span>
            </div>
            <h3 class="gallery-card-title">
                <a href="#"><?php esc_html_e( 'روایتی لوک ثقافتی میلہ: دستکاروں، فنکاروں اور مقامی روایات کی رنگین دنیا', 'urdu-news' ); ?></a>
            </h3>
        </article>

        <!-- Photo Card 4 -->
        <article class="gallery-card">
            <div class="gallery-thumb-wrap">
                <div class="gallery-placeholder-gradient gal-grad-4" aria-hidden="true"></div>
                <span class="gallery-counter">
                    <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M12 12m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0" fill="none" stroke="currentColor" stroke-width="2"/><path d="M5 7h1a2 2 0 0 0 2 -2a1 1 0 0 1 1 -1h6a1 1 0 0 1 1 1a2 2 0 0 0 2 2h1a2 2 0 0 1 2 2v9a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-9a2 2 0 0 1 2 -2" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                    <span><span class="bidi-isolate">5</span> <?php esc_html_e( 'تصاویر', 'urdu-news' ); ?></span>
                </span>
            </div>
            <h3 class="gallery-card-title">
                <a href="#"><?php esc_html_e( 'شاہراہِ قراقرم پر طلوعِ آفتاب کا مسحور کن منظر اور بلند و بالا پہاڑوں کا جادو', 'urdu-news' ); ?></a>
            </h3>
        </article>

        <!-- Photo Card 5 -->
        <article class="gallery-card">
            <div class="gallery-thumb-wrap">
                <div class="gallery-placeholder-gradient gal-grad-5" aria-hidden="true"></div>
                <span class="gallery-counter">
                    <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M12 12m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0" fill="none" stroke="currentColor" stroke-width="2"/><path d="M5 7h1a2 2 0 0 0 2 -2a1 1 0 0 1 1 -1h6a1 1 0 0 1 1 1a2 2 0 0 0 2 2h1a2 2 0 0 1 2 2v9a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-9a2 2 0 0 1 2 -2" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                    <span><span class="bidi-isolate">7</span> <?php esc_html_e( 'تصاویر', 'urdu-news' ); ?></span>
                </span>
            </div>
            <h3 class="gallery-card-title">
                <a href="#"><?php esc_html_e( 'قدیم تاریخی عمارات کی بحالی: لاہور اور ملتان کے تاریخی فن تعمیر کی حفاظت', 'urdu-news' ); ?></a>
            </h3>
        </article>
    </div>
</section>
<?php endif; ?>

<?php if ( get_theme_mod( 'urdu_news_enable_authors', true ) ) : ?>
<!-- Section 12: Authors Strip (Row of author chips: avatar placeholder, name, role) -->
<section class="authors-section site-container" aria-label="<?php esc_attr_e( 'ہمارے نمائندے اور تجزیہ کار', 'urdu-news' ); ?>">
    <div class="section-heading-band">
        <div class="heading-start">
            <span class="heading-accent-bar" style="background-color: #475569;" aria-hidden="true"></span>
            <h2 class="section-title"><?php esc_html_e( 'ہمارے نمائندے اور تجزیہ کار', 'urdu-news' ); ?></h2>
            <span class="section-subtitle"><?php esc_html_e( 'باوثوق اور زمینی حقائق پر مبنی رپورٹنگ کی ٹیم', 'urdu-news' ); ?></span>
        </div>
    </div>

    <div class="authors-grid">
        <!-- Author Chip 1 -->
        <div class="author-chip">
            <div class="author-avatar author-av-1" aria-hidden="true">
                <span>کہ</span>
            </div>
            <div class="author-chip-details">
                <h3 class="author-name">کامران ہاشمی</h3>
                <span class="author-role"><?php esc_html_e( 'نمائندہ خصوصی، اسلام آباد', 'urdu-news' ); ?></span>
            </div>
        </div>

        <!-- Author Chip 2 -->
        <div class="author-chip">
            <div class="author-avatar author-av-2" aria-hidden="true">
                <span>فر</span>
            </div>
            <div class="author-chip-details">
                <h3 class="author-name">فاطمہ رضوی</h3>
                <span class="author-role"><?php esc_html_e( 'سینئر معاشی تجزیہ کار', 'urdu-news' ); ?></span>
            </div>
        </div>

        <!-- Author Chip 3 -->
        <div class="author-chip">
            <div class="author-avatar author-av-3" aria-hidden="true">
                <span>باد</span>
            </div>
            <div class="author-chip-details">
                <h3 class="author-name">بلال احمد ڈار</h3>
                <span class="author-role"><?php esc_html_e( 'کھیلوں کے نامہ نگار', 'urdu-news' ); ?></span>
            </div>
        </div>

        <!-- Author Chip 4 -->
        <div class="author-chip">
            <div class="author-avatar author-av-4" aria-hidden="true">
                <span>عص</span>
            </div>
            <div class="author-chip-details">
                <h3 class="author-name">عائشہ صدیقی</h3>
                <span class="author-role"><?php esc_html_e( 'ماحولیات و سماجی امور', 'urdu-news' ); ?></span>
            </div>
        </div>

        <!-- Author Chip 5 -->
        <div class="author-chip">
            <div class="author-avatar author-av-5" aria-hidden="true">
                <span>سق</span>
            </div>
            <div class="author-chip-details">
                <h3 class="author-name">سلمان قریشی</h3>
                <span class="author-role"><?php esc_html_e( 'ٹیکنالوجی اور ڈیجیٹل میڈیا', 'urdu-news' ); ?></span>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ( get_theme_mod( 'urdu_news_enable_newsletter', true ) ) : ?>
<!-- Section 13: Newsletter Section (Email input + button, form slot only, Urdu copy) -->
<section class="newsletter-section" aria-label="<?php esc_attr_e( 'خبرنامہ سبسکرائب کریں', 'urdu-news' ); ?>">
    <div class="site-container">
        <div class="newsletter-card">
            <div class="newsletter-content">
                <span class="newsletter-badge-icon" aria-hidden="true">📬</span>
                <h2 class="newsletter-title"><?php esc_html_e( 'اردو نیوز کا روزانہ خبرنامہ سبسکرائب کریں', 'urdu-news' ); ?></h2>
                <p class="newsletter-desc">
                    <?php esc_html_e( 'ملک و قوم اور دنیا بھر کی اہم ترین خبریں، معاشی تجزیے اور خصوصی رپورٹس ہر صبح براہِ راست اپنے ای میل ان باکس میں حاصل کریں۔', 'urdu-news' ); ?>
                </p>

                <form class="newsletter-form" action="#" method="post" aria-label="<?php esc_attr_e( 'نیوز لیٹر فارم', 'urdu-news' ); ?>">
                    <div class="newsletter-input-group">
                        <label for="newsletter-email" class="screen-reader-text"><?php esc_html_e( 'ای میل پتہ', 'urdu-news' ); ?></label>
                        <input type="email" id="newsletter-email" name="newsletter_email" class="newsletter-input" placeholder="<?php esc_attr_e( 'اپنا ای میل پتہ درج کریں...', 'urdu-news' ); ?>" required>
                        <button type="submit" class="newsletter-submit-btn"><?php esc_html_e( 'سبسکرائب کریں', 'urdu-news' ); ?></button>
                    </div>
                    <p class="newsletter-privacy">
                        <?php esc_html_e( 'ہم آپ کی رازداری کا مکمل احترام کرتے ہیں۔ کسی بھی وقت باآسانی رکنیت ختم کرنے کی سہولت۔', 'urdu-news' ); ?>
                    </p>
                </form>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php
get_footer();
