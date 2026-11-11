<?php
/**
 * The header for the Urdu News theme
 *
 * @package Urdu_News
 * @version 0.1-trial
 */

?>
<!DOCTYPE html>
<html dir="rtl" lang="ur" <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<div id="page" class="site">
    <a class="skip-link screen-reader-text" href="#primary"><?php esc_html_e( 'مواد پر جائیں', 'urdu-news' ); ?></a>

    <!-- Section 1: Top Bar (Dark slate #22303f background, white text) -->
    <div class="top-bar">
        <div class="top-bar-inner site-container">
            <!-- RTL Start: Current Date in Urdu + Social Links -->
            <div class="top-bar-start">
                <div class="top-bar-date">
                    <?php urdu_news_render_current_urdu_date(); ?>
                </div>
                <div class="top-bar-social" aria-label="<?php esc_attr_e( 'سوشل میڈیا لنکس', 'urdu-news' ); ?>">
                    <?php
                    $fb_url = get_theme_mod( 'urdu_news_social_facebook', 'https://facebook.com' );
                    $x_url  = get_theme_mod( 'urdu_news_social_x', 'https://x.com' );
                    $yt_url = get_theme_mod( 'urdu_news_social_youtube', 'https://youtube.com' );
                    $ig_url = get_theme_mod( 'urdu_news_social_instagram', 'https://instagram.com' );
                    $wa_url = get_theme_mod( 'urdu_news_social_whatsapp', '' );
                    ?>
                    <?php if ( ! empty( $fb_url ) ) : ?>
                    <a href="<?php echo esc_url( $fb_url ); ?>" class="social-link" aria-label="Facebook" rel="noopener noreferrer">
                        <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.84 3.44 8.87 8 9.8V15H8v-3h2V9.5C10 7.57 11.57 6 13.5 6H16v3h-2c-.55 0-1 .45-1 1v2h3v3h-3v6.95C18.05 21.45 22 17.19 22 12z"/></svg>
                    </a>
                    <?php endif; ?>
                    <?php if ( ! empty( $x_url ) ) : ?>
                    <a href="<?php echo esc_url( $x_url ); ?>" class="social-link" aria-label="X" rel="noopener noreferrer">
                        <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                    </a>
                    <?php endif; ?>
                    <?php if ( ! empty( $yt_url ) ) : ?>
                    <a href="<?php echo esc_url( $yt_url ); ?>" class="social-link" aria-label="YouTube" rel="noopener noreferrer">
                        <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                    </a>
                    <?php endif; ?>
                    <?php if ( ! empty( $ig_url ) ) : ?>
                    <a href="<?php echo esc_url( $ig_url ); ?>" class="social-link" aria-label="Instagram" rel="noopener noreferrer">
                        <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                    </a>
                    <?php endif; ?>
                    <?php if ( ! empty( $wa_url ) ) : ?>
                    <a href="<?php echo esc_url( $wa_url ); ?>" class="social-link" aria-label="WhatsApp" rel="noopener noreferrer">
                        <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- RTL End: Live TV with Red Dot + Search Icon -->
            <div class="top-bar-end">
                <?php
                $live_tv_url = get_theme_mod( 'urdu_news_live_tv_url', '' );
                ?>
                <a href="<?php echo esc_url( ! empty( $live_tv_url ) ? $live_tv_url : '#' ); ?>" class="live-tv-link">
                    <span class="live-dot" aria-hidden="true"></span>
                    <span class="live-label"><span class="bidi-isolate">Live TV</span></span>
                </a>
                <button type="button" class="top-bar-search-toggle" aria-label="<?php esc_attr_e( 'تلاش', 'urdu-news' ); ?>">
                    <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Section 2: Header (Logo, Primary Nav, Search, ☰ Toggle) -->
    <header id="masthead" class="site-header">
        <div class="main-header-inner site-container">
            <!-- Site Title / Logo -->
            <div class="site-branding">
                <h1 class="site-title">
                    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">اردو نیوز</a>
                </h1>
            </div>

            <!-- Mobile Navigation Toggle Button -->
            <button class="menu-toggle" aria-controls="site-navigation" aria-expanded="false" aria-label="<?php esc_attr_e( 'مینو کھولیں', 'urdu-news' ); ?>">
                <span class="menu-toggle-icon" aria-hidden="true">☰</span>
                <span class="menu-toggle-label"><?php esc_html_e( 'مینو', 'urdu-news' ); ?></span>
            </button>

            <!-- Horizontal Primary Navigation -->
            <?php
            wp_nav_menu(
                array(
                    'theme_location' => 'primary',
                    'menu_id'        => 'primary-menu',
                    'menu_class'     => 'nav-menu',
                    'container'      => 'nav',
                    'container_class'=> 'primary-nav',
                    'container_id'   => 'site-navigation',
                    'fallback_cb'    => 'urdu_news_default_nav_menu',
                )
            );
            ?>

            <!-- Header Search Box -->
            <div class="header-search">
                <form role="search" method="get" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
                    <label class="screen-reader-text" for="header-search-input"><?php esc_html_e( 'تلاش کریں', 'urdu-news' ); ?></label>
                    <input type="search" id="header-search-input" class="search-field" placeholder="<?php esc_attr_e( 'خبریں تلاش کریں...', 'urdu-news' ); ?>" value="<?php echo get_search_query(); ?>" name="s" />
                    <button type="submit" class="search-submit" aria-label="<?php esc_attr_e( 'تلاش کریں', 'urdu-news' ); ?>">
                        <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    </button>
                </form>
            </div>
        </div>
    </header>

    <main id="primary" class="site-main">
