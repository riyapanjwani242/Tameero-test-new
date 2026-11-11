<?php
/**
 * The template for displaying all static pages
 *
 * @package Urdu_News
 * @version 0.1-trial
 */

get_header();
?>

<div class="article-layout-container site-container">
    <div class="article-main-column">
        <?php
        while ( have_posts() ) :
            the_post();
            ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class( 'static-page-article' ); ?>>

                <!-- Page Header: Title in Nastaliq -->
                <header class="page-header">
                    <h1 class="page-title entry-title">
                        <?php the_title(); ?>
                    </h1>
                </header>

                <!-- Featured Image if set (urdu-hero) -->
                <?php if ( has_post_thumbnail() ) : ?>
                    <div class="page-featured-image-wrap">
                        <?php the_post_thumbnail( 'urdu-hero', array( 'class' => 'page-featured-img', 'loading' => 'eager' ) ); ?>
                        <?php
                        $caption = wp_get_attachment_caption( get_post_thumbnail_id() );
                        if ( ! empty( $caption ) ) :
                        ?>
                            <figcaption class="page-image-caption"><?php echo esc_html( $caption ); ?></figcaption>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Page Body Content (styled for Urdu readability) -->
                <div class="page-body entry-content">
                    <?php
                    the_content();

                    wp_link_pages(
                        array(
                            'before'      => '<div class="page-links">' . esc_html__( 'صفحات:', 'urdu-news' ),
                            'after'       => '</div>',
                            'link_before' => '<span class="page-number bidi-isolate">',
                            'link_after'  => '</span>',
                        )
                    );
                    ?>
                </div>

            </article>
        <?php endwhile; ?>
    </div><!-- .article-main-column -->

    <!-- Optional Sidebar -->
    <?php get_sidebar(); ?>

</div><!-- .article-layout-container -->

<?php
get_footer();
