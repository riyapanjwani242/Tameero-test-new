<?php
/**
 * The template for displaying the footer
 *
 * Simple closing footer only — full footer design comes later.
 *
 * @package Urdu_News
 * @version 0.1-trial
 */

?>
    </main><!-- #primary -->

    <!-- Section 10: Footer Completion (Mega-Footer: 4 Columns + Bottom Bar) -->
    <footer id="colophon" class="site-footer">
        <div class="site-footer-inner site-container">
            <div class="footer-mega-grid">
                <!-- Column 1: About & Logo -->
                <div class="footer-col footer-col-about">
                    <h2 class="footer-brand-title">
                        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">اردو نیوز</a>
                    </h2>
                    <p class="footer-about-text">
                        <?php
                        $footer_about = get_theme_mod(
                            'urdu_news_footer_about_text',
                            esc_html__( 'اردو نیوز پاکستان کا ممتاز اور بااعتماد ڈیجیٹل ادارہ ہے جو قارئین کو لمحہ بہ لمحہ باوثوق، غیر جانبدار اور تحقیقی خبریں، تجزیے اور خصوصی دستاویزی رپورٹس فراہم کرتا ہے۔', 'urdu-news' )
                        );
                        echo wp_kses_post( $footer_about );
                        ?>
                    </p>
                    <div class="footer-motto">
                        <span class="motto-dot" aria-hidden="true">•</span>
                        <span><?php esc_html_e( 'حقائق، دیانت اور بروقت آگاہی کا ترجمان', 'urdu-news' ); ?></span>
                    </div>
                </div>

                <!-- Column 2: Quick Links -->
                <div class="footer-col footer-col-links">
                    <h3 class="footer-heading"><?php esc_html_e( 'فوری لنکس', 'urdu-news' ); ?></h3>
                    <ul class="footer-nav-list">
                        <li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'صفحہ اول', 'urdu-news' ); ?></a></li>
                        <li><a href="#"><?php esc_html_e( 'تازہ ترین خبریں', 'urdu-news' ); ?></a></li>
                        <li><a href="#"><?php esc_html_e( 'براہِ راست نشریات (Live TV)', 'urdu-news' ); ?></a></li>
                        <li><a href="#"><?php esc_html_e( 'بریکنگ نیوز آرکائیو', 'urdu-news' ); ?></a></li>
                        <li><a href="#"><?php esc_html_e( 'ادارتی پالیسی', 'urdu-news' ); ?></a></li>
                        <li><a href="#"><?php esc_html_e( 'رازداری کی پالیسی', 'urdu-news' ); ?></a></li>
                        <li><a href="#"><?php esc_html_e( 'ہم سے رابطہ کریں', 'urdu-news' ); ?></a></li>
                    </ul>
                </div>

                <!-- Column 3: Categories -->
                <div class="footer-col footer-col-categories">
                    <h3 class="footer-heading"><?php esc_html_e( 'اہم شعبہ جات', 'urdu-news' ); ?></h3>
                    <ul class="footer-nav-list">
                        <li><a href="<?php echo esc_url( home_url( '/category/siyasat/' ) ); ?>"><?php esc_html_e( 'سیاست و پارلیمان', 'urdu-news' ); ?></a></li>
                        <li><a href="<?php echo esc_url( home_url( '/category/khel/' ) ); ?>"><?php esc_html_e( 'کھیل اور کرکٹ', 'urdu-news' ); ?></a></li>
                        <li><a href="<?php echo esc_url( home_url( '/category/karobar/' ) ); ?>"><?php esc_html_e( 'کاروبار و معیشت', 'urdu-news' ); ?></a></li>
                        <li><a href="<?php echo esc_url( home_url( '/category/technology/' ) ); ?>"><?php esc_html_e( 'سائنس و ٹیکنالوجی', 'urdu-news' ); ?></a></li>
                        <li><a href="<?php echo esc_url( home_url( '/category/entertainment/' ) ); ?>"><?php esc_html_e( 'تفریح اور ثقافت', 'urdu-news' ); ?></a></li>
                        <li><a href="#"><?php esc_html_e( 'کالم اور آراء', 'urdu-news' ); ?></a></li>
                        <li><a href="#"><?php esc_html_e( 'ویڈیو رپورٹس', 'urdu-news' ); ?></a></li>
                    </ul>
                </div>

                <!-- Column 4: Contact & Social -->
                <div class="footer-col footer-col-contact">
                    <h3 class="footer-heading"><?php esc_html_e( 'رابطہ اور سوشل میڈیا', 'urdu-news' ); ?></h3>
                    <div class="footer-contact-details">
                        <div class="footer-contact-row">
                            <span class="contact-icon" aria-hidden="true">📍</span>
                            <span><?php esc_html_e( 'پریس میڈیا ہاؤس، بلیو ایریا، اسلام آباد، پاکستان', 'urdu-news' ); ?></span>
                        </div>
                        <div class="footer-contact-row">
                            <span class="contact-icon" aria-hidden="true">✉️</span>
                            <a href="mailto:info@urdunews.example"><span class="bidi-isolate">info@urdunews.example</span></a>
                        </div>
                        <div class="footer-contact-row">
                            <span class="contact-icon" aria-hidden="true">📞</span>
                            <a href="tel:+9251111222333"><span class="bidi-isolate">+92 51 111 222 333</span></a>
                        </div>
                    </div>

                    <!-- Social Icons -->
                    <div class="footer-social-wrap" aria-label="<?php esc_attr_e( 'سوشل میڈیا روابط', 'urdu-news' ); ?>">
                        <?php
                        $f_fb_url = get_theme_mod( 'urdu_news_social_facebook', 'https://facebook.com' );
                        $f_x_url  = get_theme_mod( 'urdu_news_social_x', 'https://x.com' );
                        $f_yt_url = get_theme_mod( 'urdu_news_social_youtube', 'https://youtube.com' );
                        $f_ig_url = get_theme_mod( 'urdu_news_social_instagram', 'https://instagram.com' );
                        $f_wa_url = get_theme_mod( 'urdu_news_social_whatsapp', '' );
                        ?>
                        <?php if ( ! empty( $f_fb_url ) ) : ?>
                        <a href="<?php echo esc_url( $f_fb_url ); ?>" class="footer-social-btn" aria-label="Facebook" rel="noopener noreferrer">
                            <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.84 3.44 8.87 8 9.8V15H8v-3h2V9.5C10 7.57 11.57 6 13.5 6H16v3h-2c-.55 0-1 .45-1 1v2h3v3h-3v6.95C18.05 21.45 22 17.19 22 12z"/></svg>
                        </a>
                        <?php endif; ?>
                        <?php if ( ! empty( $f_x_url ) ) : ?>
                        <a href="<?php echo esc_url( $f_x_url ); ?>" class="footer-social-btn" aria-label="X" rel="noopener noreferrer">
                            <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                        </a>
                        <?php endif; ?>
                        <?php if ( ! empty( $f_yt_url ) ) : ?>
                        <a href="<?php echo esc_url( $f_yt_url ); ?>" class="footer-social-btn" aria-label="YouTube" rel="noopener noreferrer">
                            <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                        </a>
                        <?php endif; ?>
                        <?php if ( ! empty( $f_ig_url ) ) : ?>
                        <a href="<?php echo esc_url( $f_ig_url ); ?>" class="footer-social-btn" aria-label="Instagram" rel="noopener noreferrer">
                            <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                        </a>
                        <?php endif; ?>
                    </div>

                    <!-- WhatsApp Tag (Permitted #006341 green) -->
                    <div class="footer-whatsapp-box">
                        <a href="<?php echo esc_url( ! empty( $f_wa_url ) ? $f_wa_url : '#' ); ?>" class="whatsapp-channel-btn" rel="noopener noreferrer">
                            <span class="whatsapp-badge-icon" aria-hidden="true">💬</span>
                            <span><?php esc_html_e( 'ہمارا واٹس ایپ چینل جوائن کریں', 'urdu-news' ); ?></span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Bottom Copyright Bar -->
            <div class="footer-bottom-bar">
                <p class="footer-copy">
                    <?php
                    $footer_copyright = get_theme_mod( 'urdu_news_footer_copyright', '' );
                    if ( ! empty( $footer_copyright ) ) :
                        echo esc_html( $footer_copyright );
                    else :
                    ?>
                        &copy; <span class="bidi-isolate"><?php echo esc_html( date( 'Y' ) ); ?></span> <?php bloginfo( 'name' ); ?> — <?php esc_html_e( 'تمام جملہ حقوق محفوظ ہیں۔', 'urdu-news' ); ?>
                    <?php endif; ?>
                </p>
                <div class="footer-bottom-links">
                    <a href="#"><?php esc_html_e( 'سروس کی شرائط', 'urdu-news' ); ?></a>
                    <span class="sep" aria-hidden="true">•</span>
                    <a href="#"><?php esc_html_e( 'رازداری', 'urdu-news' ); ?></a>
                    <span class="sep" aria-hidden="true">•</span>
                    <a href="#"><?php esc_html_e( 'سائٹ میپ', 'urdu-news' ); ?></a>
                </div>
            </div>
        </div>
    </footer>
</div><!-- #page -->

<?php wp_footer(); ?>

<!-- Lightweight Vanilla JS for Mobile Nav, Tabbed Rail, and Video Embeds -->
<script>
(function() {
    'use strict';

    // 1. Mobile Navigation Toggle
    var toggle = document.querySelector('.menu-toggle');
    var nav = document.querySelector('.primary-nav');
    if (toggle && nav) {
        toggle.addEventListener('click', function() {
            var isExpanded = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', !isExpanded);
            nav.classList.toggle('is-open');
        });
    }

    // 2. Tabbed Rail Switching (Most Read | Latest)
    var tabButtons = document.querySelectorAll('.rail-tab-btn');
    var tabPanels = {
        'popular': document.getElementById('panel-popular'),
        'latest': document.getElementById('panel-latest')
    };

    if (tabButtons.length > 0) {
        tabButtons.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var targetTab = btn.getAttribute('data-tab');

                tabButtons.forEach(function(b) {
                    b.classList.remove('is-active');
                    b.setAttribute('aria-selected', 'false');
                });

                btn.classList.add('is-active');
                btn.setAttribute('aria-selected', 'true');

                for (var key in tabPanels) {
                    if (tabPanels.hasOwnProperty(key) && tabPanels[key]) {
                        if (key === targetTab) {
                            tabPanels[key].classList.add('is-active');
                            tabPanels[key].removeAttribute('hidden');
                        } else {
                            tabPanels[key].classList.remove('is-active');
                            tabPanels[key].setAttribute('hidden', 'hidden');
                        }
                    }
                }
            });
        });
    }

    // 3. YouTube Embed Video Readiness
    var videoCards = document.querySelectorAll('.video-card');
    videoCards.forEach(function(card) {
        card.addEventListener('click', function(e) {
            var embedUrl = card.getAttribute('data-embed-url');
            var thumbContainer = card.querySelector('.video-thumb-container');
            if (embedUrl && thumbContainer && !card.querySelector('iframe')) {
                // If clicked, load YouTube-ready iframe smoothly
                var iframe = document.createElement('iframe');
                iframe.setAttribute('src', embedUrl + '?autoplay=1&rel=0');
                iframe.setAttribute('title', card.querySelector('.video-card-title a') ? card.querySelector('.video-card-title a').textContent : 'Video');
                iframe.setAttribute('frameborder', '0');
                iframe.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture');
                iframe.setAttribute('allowfullscreen', 'allowfullscreen');
                iframe.className = 'video-embed-iframe';
                thumbContainer.innerHTML = '';
                thumbContainer.appendChild(iframe);
            }
        });
    });
})();
</script>

</body>
</html>
