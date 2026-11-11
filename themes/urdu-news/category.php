<?php
/**
 * The template for displaying category archive pages
 *
 * @package Urdu_News
 * @version 0.1-trial
 */

get_header();

$cat_obj    = get_queried_object();
$cat_slug   = ( $cat_obj && isset( $cat_obj->slug ) ) ? $cat_obj->slug : '';
$cat_name   = single_cat_title( '', false );
$cat_desc   = category_description();
$cat_accent = urdu_news_get_category_accent( $cat_slug );
?>

<div class="archive-layout-container site-container">
    <div class="archive-main-column">

        <!-- Category Header Band (Category title in Nastaliq, description, accent pill) -->
        <header class="category-page-header">
            <div class="category-header-top">
                <span class="category-accent-bar" style="background-color: <?php echo esc_attr( $cat_accent ); ?>;" aria-hidden="true"></span>
                <div class="category-header-titles">
                    <span class="category-type-badge" style="background-color: <?php echo esc_attr( $cat_accent ); ?>;"><?php esc_html_e( 'شعبہ', 'urdu-news' ); ?></span>
                    <h1 class="category-page-title"><?php echo esc_html( $cat_name ); ?></h1>
                </div>
            </div>
            <?php if ( ! empty( $cat_desc ) ) : ?>
                <div class="category-page-description">
                    <?php echo wp_kses_post( $cat_desc ); ?>
                </div>
            <?php endif; ?>
        </header>

        <!-- Grid of Post Cards (image 4:3, title, meta, excerpt) -->
        <div class="archive-posts-grid">
            <?php if ( have_posts() ) : ?>
                <?php
                while ( have_posts() ) :
                    the_post();
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

            <?php else : ?>
                <!-- Fallback: if category has no posts, show sample cards so page never looks broken -->
                <?php
                $sample_archive_stories = urdu_news_get_archive_sample_stories( $cat_slug, 6 );
                foreach ( $sample_archive_stories as $sa_idx => $sa_story ) :
                ?>
                    <article class="archive-card is-sample-card">
                        <div class="archive-card-thumb-wrap">
                            <a href="<?php echo esc_url( $sa_story['url'] ); ?>" tabindex="-1" aria-hidden="true">
                                <div class="archive-placeholder-gradient gradient-sub-<?php echo esc_attr( ( $sa_idx % 4 ) + 1 ); ?>" aria-hidden="true"></div>
                            </a>
                            <span class="archive-card-cat-tag" style="background-color: <?php echo esc_attr( $cat_accent ); ?>;">
                                <?php echo esc_html( $cat_name ); ?>
                            </span>
                        </div>
                        <div class="archive-card-content">
                            <h2 class="archive-card-title">
                                <a href="<?php echo esc_url( $sa_story['url'] ); ?>"><?php echo wp_kses_post( $sa_story['title'] ); ?></a>
                            </h2>
                            <div class="archive-card-meta">
                                <span class="meta-item"><span class="bidi-isolate"><?php echo wp_kses_post( $sa_story['time'] ); ?></span></span>
                                <span class="meta-sep" aria-hidden="true">•</span>
                                <span class="meta-item"><span class="bidi-isolate"><?php esc_html_e( 'خصوصی رپورٹ', 'urdu-news' ); ?></span></span>
                            </div>
                            <div class="archive-card-excerpt">
                                <p><?php echo wp_kses_post( $sa_story['excerpt'] ); ?></p>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
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

    </div><!-- .archive-main-column -->

    <!-- Sidebar included -->
    <?php get_sidebar(); ?>

</div><!-- .archive-layout-container -->

<?php
get_footer();
