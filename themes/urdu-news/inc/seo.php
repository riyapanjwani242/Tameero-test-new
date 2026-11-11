<?php
/**
 * Urdu News SEO, Open Graph, and Schema.org Metadata
 *
 * @package Urdu_News
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Output Open Graph and Twitter Card metadata in the document head.
 */
function urdu_news_seo_opengraph() {
    // 1. Determine title
    $title = '';
    if ( is_singular() ) {
        $title = get_the_title();
    } elseif ( is_front_page() || is_home() ) {
        $title = get_bloginfo( 'name' );
        $desc  = get_bloginfo( 'description' );
        if ( ! empty( $desc ) ) {
            $title .= ' — ' . $desc;
        }
    } elseif ( is_category() || is_tag() || is_tax() ) {
        $title = single_term_title( '', false ) . ' — ' . get_bloginfo( 'name' );
    } elseif ( is_author() ) {
        $title = get_the_author() . ' — ' . get_bloginfo( 'name' );
    } elseif ( is_archive() ) {
        $title = get_the_archive_title() . ' — ' . get_bloginfo( 'name' );
    } elseif ( is_search() ) {
        /* translators: %s: search query */
        $title = sprintf( esc_html__( 'نتائج تلاش: %s', 'urdu-news' ), get_search_query() ) . ' — ' . get_bloginfo( 'name' );
    } elseif ( is_404() ) {
        $title = esc_html__( 'صفحہ نہیں ملا (404)', 'urdu-news' ) . ' — ' . get_bloginfo( 'name' );
    } else {
        $title = wp_get_document_title();
    }
    $title = wp_strip_all_tags( $title );

    // 2. Determine description
    $description = '';
    if ( is_singular() ) {
        $post = get_post();
        if ( has_excerpt( $post ) ) {
            $description = get_the_excerpt( $post );
        } elseif ( ! empty( $post->post_content ) ) {
            $description = wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 35, '...' );
        }
    } elseif ( is_category() || is_tag() || is_tax() ) {
        $term_desc = term_description();
        if ( ! empty( $term_desc ) ) {
            $description = wp_strip_all_tags( $term_desc );
        }
    }

    if ( empty( $description ) ) {
        $description = get_bloginfo( 'description' );
    }
    if ( empty( $description ) ) {
        $description = esc_html__( 'اردو نیوز - پاکستان اور دنیا بھر کی تازہ ترین، باوثوق اور تحقیقی خبریں، تجزیے اور خصوصی رپورٹس۔', 'urdu-news' );
    }
    $description = wp_strip_all_tags( $description );

    // 3. Determine og:type (article on single, website elsewhere)
    $type = is_singular( 'post' ) ? 'article' : 'website';

    // 4. Determine canonical URL
    $url = '';
    if ( is_singular() ) {
        $url = get_permalink();
    } elseif ( is_front_page() || is_home() ) {
        $url = home_url( '/' );
    } elseif ( is_category() || is_tag() || is_tax() ) {
        $term = get_queried_object();
        if ( $term && ! is_wp_error( $term ) ) {
            $url = get_term_link( $term );
        }
    } elseif ( is_author() ) {
        $author_id = get_queried_object_id();
        if ( $author_id ) {
            $url = get_author_posts_url( $author_id );
        }
    }

    if ( empty( $url ) || is_wp_error( $url ) ) {
        global $wp;
        $url = home_url( add_query_arg( array(), isset( $wp->request ) ? $wp->request : '' ) );
    }

    // 5. Determine image (featured image on single, site icon / logo elsewhere)
    $image = '';
    if ( is_singular() && has_post_thumbnail() ) {
        $image = get_the_post_thumbnail_url( get_the_ID(), 'full' );
    } elseif ( has_custom_logo() ) {
        $logo_id  = get_theme_mod( 'custom_logo' );
        $logo_src = wp_get_attachment_image_src( $logo_id, 'full' );
        if ( ! empty( $logo_src[0] ) ) {
            $image = $logo_src[0];
        }
    } elseif ( function_exists( 'get_site_icon_url' ) && get_site_icon_url( 512 ) ) {
        $image = get_site_icon_url( 512 );
    }

    $site_name = get_bloginfo( 'name' );
    ?>
    <!-- Open Graph Metadata -->
    <meta property="og:locale" content="ur_PK">
    <meta property="og:site_name" content="<?php echo esc_attr( $site_name ); ?>">
    <meta property="og:type" content="<?php echo esc_attr( $type ); ?>">
    <meta property="og:title" content="<?php echo esc_attr( $title ); ?>">
    <meta property="og:description" content="<?php echo esc_attr( $description ); ?>">
    <meta property="og:url" content="<?php echo esc_url( $url ); ?>">
    <?php if ( ! empty( $image ) ) : ?>
    <meta property="og:image" content="<?php echo esc_url( $image ); ?>">
    <?php endif; ?>
    <?php if ( is_singular( 'post' ) ) : ?>
    <meta property="article:published_time" content="<?php echo esc_attr( get_the_date( 'c' ) ); ?>">
    <meta property="article:modified_time" content="<?php echo esc_attr( get_the_modified_date( 'c' ) ); ?>">
    <meta property="article:author" content="<?php echo esc_attr( get_the_author() ); ?>">
    <?php endif; ?>

    <!-- Twitter Card Metadata -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo esc_attr( $title ); ?>">
    <meta name="twitter:description" content="<?php echo esc_attr( $description ); ?>">
    <?php if ( ! empty( $image ) ) : ?>
    <meta name="twitter:image" content="<?php echo esc_url( $image ); ?>">
    <?php endif; ?>
    <?php
}
add_action( 'wp_head', 'urdu_news_seo_opengraph', 5 );

/**
 * Output JSON-LD NewsArticle schema on single posts.
 */
function urdu_news_seo_newsarticle_schema() {
    if ( ! is_single() ) {
        return;
    }

    $post_id = get_the_ID();
    $post    = get_post( $post_id );

    if ( ! $post ) {
        return;
    }

    // Headline
    $headline = wp_strip_all_tags( get_the_title( $post_id ) );

    // Dates in ISO 8601
    $date_published = get_the_date( 'c', $post_id );
    $date_modified  = get_the_modified_date( 'c', $post_id );

    // Author
    $author_id   = $post->post_author;
    $author_name = get_the_author_meta( 'display_name', $author_id );
    if ( empty( $author_name ) ) {
        $author_name = get_the_author();
    }
    $author_url = get_author_posts_url( $author_id );

    // Publisher
    $publisher_name = get_bloginfo( 'name' );
    $publisher_url  = home_url( '/' );

    // Description
    $description = '';
    if ( has_excerpt( $post_id ) ) {
        $description = wp_strip_all_tags( get_the_excerpt( $post_id ) );
    } elseif ( ! empty( $post->post_content ) ) {
        $description = wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 35, '...' );
    }

    // Assemble schema array
    $schema = array(
        '@context'         => 'https://schema.org',
        '@type'            => 'NewsArticle',
        'mainEntityOfPage' => array(
            '@type' => 'WebPage',
            '@id'   => get_permalink( $post_id ),
        ),
        'headline'         => $headline,
        'datePublished'    => $date_published,
        'dateModified'     => $date_modified,
        'author'           => array(
            '@type' => 'Person',
            'name'  => $author_name,
            'url'   => $author_url,
        ),
        'publisher'        => array(
            '@type' => 'NewsMediaOrganization',
            'name'  => $publisher_name,
            'url'   => $publisher_url,
        ),
    );

    // Featured Image URL
    if ( has_post_thumbnail( $post_id ) ) {
        $image_url = get_the_post_thumbnail_url( $post_id, 'full' );
        if ( ! empty( $image_url ) ) {
            $schema['image'] = array(
                $image_url,
            );
        }
    }

    // Custom Logo for Publisher
    if ( has_custom_logo() ) {
        $logo_id  = get_theme_mod( 'custom_logo' );
        $logo_src = wp_get_attachment_image_src( $logo_id, 'full' );
        if ( ! empty( $logo_src[0] ) ) {
            $schema['publisher']['logo'] = array(
                '@type' => 'ImageObject',
                'url'   => $logo_src[0],
            );
        }
    }

    // Description
    if ( ! empty( $description ) ) {
        $schema['description'] = $description;
    }

    ?>
    <!-- NewsArticle JSON-LD Schema -->
    <script type="application/ld+json">
    <?php echo wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ); ?>
    </script>
    <?php
}
add_action( 'wp_head', 'urdu_news_seo_newsarticle_schema', 20 );
