<?php
/**
 * The template for displaying all single posts
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

            $categories = get_the_category();
            $first_cat  = ! empty( $categories ) ? $categories[0] : null;
            $cat_slug   = $first_cat ? $first_cat->slug : '';
            $cat_name   = $first_cat ? $first_cat->name : esc_html__( 'عام خبریں', 'urdu-news' );
            $cat_url    = $first_cat ? get_category_link( $first_cat->term_id ) : home_url( '/' );
            $cat_id     = $first_cat ? $first_cat->term_id : 0;
            $cat_accent = urdu_news_get_category_accent( $cat_slug );

            // Share URLs using actual post permalink and title
            $post_url_encoded   = rawurlencode( get_permalink() );
            $post_title_encoded = rawurlencode( get_the_title() );
            $fb_share_url       = 'https://www.facebook.com/sharer/sharer.php?u=' . $post_url_encoded;
            $x_share_url        = 'https://twitter.com/intent/tweet?text=' . $post_title_encoded . '&url=' . $post_url_encoded;
            $wa_share_url       = 'https://api.whatsapp.com/send?text=' . $post_title_encoded . '%20' . $post_url_encoded;

            // Author metadata
            $author_name = get_the_author();
            $author_bio  = get_the_author_meta( 'description' );
            if ( empty( $author_bio ) ) {
                $author_bio = esc_html__( 'سینئر صحافی اور کالم نگار، قومی و بین الاقوامی امور اور تجزیاتی صحافت سے وابستہ۔', 'urdu-news' );
            }
            $author_initial = mb_substr( $author_name, 0, 1, 'UTF-8' );
            ?>

            <article id="post-<?php the_ID(); ?>" <?php post_class( 'single-article-content' ); ?>>

                <!-- 1. Breadcrumbs & Category Chip & Headline (Nastaliq, no clamp) -->
                <header class="article-header">
                    <nav class="article-breadcrumb" aria-label="<?php esc_attr_e( 'بریڈ کرمبز', 'urdu-news' ); ?>">
                        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="breadcrumb-item"><?php esc_html_e( 'صفحہ اول', 'urdu-news' ); ?></a>
                        <span class="breadcrumb-sep" aria-hidden="true">/</span>
                        <a href="<?php echo esc_url( $cat_url ); ?>" class="breadcrumb-item"><?php echo esc_html( $cat_name ); ?></a>
                    </nav>

                    <div class="article-category-chip-wrap">
                        <a href="<?php echo esc_url( $cat_url ); ?>" class="article-category-chip" style="background-color: <?php echo esc_attr( $cat_accent ); ?>;">
                            <?php echo esc_html( $cat_name ); ?>
                        </a>
                    </div>

                    <h1 class="article-headline entry-title">
                        <?php the_title(); ?>
                    </h1>

                    <!-- 2. Meta line (author, Urdu date, reading time estimate) -->
                    <div class="article-meta-line">
                        <span class="meta-item meta-author">
                            <span class="meta-icon" aria-hidden="true">✍️</span>
                            <span class="author-label"><?php the_author_posts_link(); ?></span>
                        </span>
                        <span class="meta-sep" aria-hidden="true">•</span>
                        <span class="meta-item meta-date">
                            <span class="meta-icon" aria-hidden="true">📅</span>
                            <span><?php echo urdu_news_get_post_urdu_date(); ?></span>
                        </span>
                        <span class="meta-sep" aria-hidden="true">•</span>
                        <span class="meta-item meta-reading-time">
                            <span class="meta-icon" aria-hidden="true">⏱️</span>
                            <span><?php echo urdu_news_get_reading_time(); ?></span>
                        </span>
                    </div>
                </header>

                <!-- 3. Featured Image (urdu-hero: 1200x675) -->
                <div class="article-featured-image-wrap">
                    <?php if ( has_post_thumbnail() ) : ?>
                        <?php the_post_thumbnail( 'urdu-hero', array( 'class' => 'article-featured-img', 'loading' => 'eager' ) ); ?>
                        <?php
                        $thumb_caption = wp_get_attachment_caption( get_post_thumbnail_id() );
                        if ( ! empty( $thumb_caption ) ) :
                        ?>
                            <figcaption class="article-image-caption"><?php echo esc_html( $thumb_caption ); ?></figcaption>
                        <?php endif; ?>
                    <?php else : ?>
                        <div class="article-featured-placeholder" aria-hidden="true">
                            <div class="placeholder-graphic">
                                <span class="placeholder-icon">📷</span>
                                <span class="placeholder-label"><?php echo esc_html( $cat_name ); ?> — <?php esc_html_e( 'اردو نیوز تصویری کوریج', 'urdu-news' ); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- 4. Article Body (Naskh, line-height 1.9+, comfortable measure) with in-article ad slot after 2nd paragraph -->
                <div class="article-body entry-content">
                    <?php
                    // In-article AdSense slot (Customizer: urdu_news_in_article_ad outputs ad when set, otherwise keeps placeholder)
                    $in_article_ad     = get_theme_mod( 'urdu_news_in_article_ad', '' );
                    $raw_content       = get_the_content();
                    $filtered_content  = apply_filters( 'the_content', $raw_content );
                    $content_with_ad   = urdu_news_insert_in_article_ad( $filtered_content );
                    echo $content_with_ad; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

                    wp_link_pages(
                        array(
                            'before'      => '<div class="page-links">' . esc_html__( 'صفحات:', 'urdu-news' ),
                            'after'       => '</div>',
                            'link_before' => '<span class="page-number bidi-isolate">',
                            'link_after'  => '</span>',
                        )
                    );
                    ?>

                    <!-- Tags list -->
                    <?php
                    $tags = get_the_tags();
                    if ( ! empty( $tags ) ) :
                    ?>
                        <div class="article-tags-wrap">
                            <span class="tags-heading-label">
                                <span class="tag-icon" aria-hidden="true">🏷️</span>
                                <span><?php esc_html_e( 'موضوعات / ٹیگز:', 'urdu-news' ); ?></span>
                            </span>
                            <div class="tags-chips-list">
                                <?php foreach ( $tags as $tag_item ) : ?>
                                    <a href="<?php echo esc_url( get_tag_link( $tag_item->term_id ) ); ?>" class="tag-chip">
                                        #<?php echo esc_html( $tag_item->name ); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- 5. Share Buttons (Facebook, X, WhatsApp permitted #006341) -->
                <div class="article-share-section">
                    <span class="share-title">
                        <span class="share-icon" aria-hidden="true">📤</span>
                        <span><?php esc_html_e( 'خبر شیئر کریں:', 'urdu-news' ); ?></span>
                    </span>
                    <div class="article-share-buttons">
                        <a href="<?php echo esc_url( $fb_share_url ); ?>" class="share-btn share-facebook" target="_blank" rel="noopener noreferrer" aria-label="Facebook">
                            <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.84 3.44 8.87 8 9.8V15H8v-3h2V9.5C10 7.57 11.57 6 13.5 6H16v3h-2c-.55 0-1 .45-1 1v2h3v3h-3v6.95C18.05 21.45 22 17.19 22 12z"/></svg>
                            <span>Facebook</span>
                        </a>
                        <a href="<?php echo esc_url( $x_share_url ); ?>" class="share-btn share-x" target="_blank" rel="noopener noreferrer" aria-label="X">
                            <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                            <span>X (Twitter)</span>
                        </a>
                        <!-- WhatsApp share button uses permitted green #006341 -->
                        <a href="<?php echo esc_url( $wa_share_url ); ?>" class="share-btn share-whatsapp" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp">
                            <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                            <span>WhatsApp</span>
                        </a>
                    </div>
                </div>

                <!-- 6. Author Box (Avatar placeholder, name, bio line) -->
                <div class="article-author-box">
                    <div class="author-box-avatar" aria-hidden="true">
                        <span class="author-avatar-initial"><?php echo esc_html( $author_initial ); ?></span>
                    </div>
                    <div class="author-box-content">
                        <span class="author-box-label"><?php esc_html_e( 'مضمون نگار', 'urdu-news' ); ?></span>
                        <h3 class="author-box-name">
                            <a href="<?php echo esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ); ?>">
                                <?php echo esc_html( $author_name ); ?>
                            </a>
                        </h3>
                        <p class="author-box-bio"><?php echo esc_html( $author_bio ); ?></p>
                    </div>
                </div>

                <!-- Prev/Next Post Links -->
                <nav class="article-navigation" aria-label="<?php esc_attr_e( 'پچھلی اور اگلی خبر', 'urdu-news' ); ?>">
                    <div class="nav-links">
                        <div class="nav-previous">
                            <?php
                            previous_post_link(
                                '%link',
                                '<span class="nav-meta-desc">← ' . esc_html__( 'پچھلی خبر', 'urdu-news' ) . '</span><span class="nav-post-title">%title</span>'
                            );
                            ?>
                        </div>
                        <div class="nav-next">
                            <?php
                            next_post_link(
                                '%link',
                                '<span class="nav-meta-desc">' . esc_html__( 'اگلی خبر', 'urdu-news' ) . ' →</span><span class="nav-post-title">%title</span>'
                            );
                            ?>
                        </div>
                    </div>
                </nav>

                <!-- 7. Related Posts (3 cards from same category, excluding current; samples if none) -->
                <?php
                $related_posts = urdu_news_get_related_posts( get_the_ID(), $cat_id, 3 );
                if ( ! empty( $related_posts ) ) :
                ?>
                    <section class="article-related-section" aria-label="<?php esc_attr_e( 'متعلقہ خبریں', 'urdu-news' ); ?>">
                        <div class="related-section-header">
                            <span class="related-accent-bar" style="background-color: <?php echo esc_attr( $cat_accent ); ?>;" aria-hidden="true"></span>
                            <h3 class="related-section-title"><?php esc_html_e( 'متعلقہ خبریں', 'urdu-news' ); ?></h3>
                            <span class="related-cat-name"><?php echo esc_html( $cat_name ); ?></span>
                        </div>
                        <div class="related-posts-grid">
                            <?php foreach ( $related_posts as $r_idx => $rel_story ) : ?>
                                <article class="related-card">
                                    <div class="related-thumb-wrap">
                                        <?php if ( ! empty( $rel_story['thumbnail'] ) ) : ?>
                                            <img src="<?php echo esc_url( $rel_story['thumbnail'] ); ?>" alt="<?php echo esc_attr( wp_strip_all_tags( $rel_story['title'] ) ); ?>" class="related-img" loading="lazy">
                                        <?php else : ?>
                                            <div class="related-placeholder-gradient gradient-rel-<?php echo esc_attr( ( $r_idx % 3 ) + 1 ); ?>" aria-hidden="true"></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="related-content">
                                        <h4 class="related-title">
                                            <a href="<?php echo esc_url( $rel_story['url'] ); ?>"><?php echo wp_kses_post( $rel_story['title'] ); ?></a>
                                        </h4>
                                        <div class="related-meta">
                                            <span class="related-date"><span class="bidi-isolate"><?php echo wp_kses_post( $rel_story['date'] ); ?></span></span>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

            </article>

        <?php endwhile; ?>
    </div><!-- .article-main-column -->

    <!-- 8. Sidebar beside article on desktop (stacks below on mobile) -->
    <?php get_sidebar(); ?>

</div><!-- .article-layout-container -->

<?php
get_footer();
